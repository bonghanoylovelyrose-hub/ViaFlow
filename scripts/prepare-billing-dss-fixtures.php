<?php

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use App\Services\OverdueMonitoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';

// Run with the local PHP CLI. This harness calls the Laravel web routes with
// authenticated development roles and keeps the generated records in MySQL.
final class BillingDssFixtures extends TestCase
{
    private const TAG = 'Development QA: billing carry-forward and DSS payment history';

    private const PAYLOADS = [
        'MT-8181' => ['initial' => 100, 'historical' => [9, 11], 'payments' => [312, 128], 'current' => 13, 'overdue_months' => 1],
        'MT-9999' => ['initial' => 200, 'historical' => [10, 12], 'payments' => [90, 100], 'current' => 15, 'overdue_months' => 2],
    ];

    private const TABLES = ['consumers', 'bills', 'meter_readings', 'payments', 'loans', 'loan_installments', 'loan_repayments', 'disconnection_notices', 'consumer_notification_logs'];

    public function runFixtures(): void
    {
        parent::setUp();
        if (PHP_SAPI !== 'cli' || ! app()->environment('local') || DB::getDriverName() !== 'mysql'
            || DB::getDatabaseName() !== 'water_billing_systems' || DB::connection()->getConfig('host') !== '127.0.0.1') {
            throw new RuntimeException('Run these fixtures only against local ViaFlow MySQL.');
        }

        config(['session.driver' => 'array', 'mail.default' => 'array']);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $meters = Consumer::query()->whereIn('meter_number', array_keys(self::PAYLOADS))->get()->keyBy('meter_number');
        self::assertCount(2, $meters);
        self::assertEqualsCanonicalizing($meters->map(fn (Consumer $account) => (string) $account->id)->values()->all(), config('billing.local_fixture_consumer_ids'));
        $admin = User::query()->where('role', 'admin')->orderBy('id')->firstOrFail();
        $reader = User::query()->whereIn('role', ['staff', 'meter_reader'])->orderBy('id')->firstOrFail();
        $balances = app(ConsumerBalanceService::class);
        $savedProofs = [];

        try {
            $result = DB::transaction(function () use ($meters, $admin, $reader, $balances, &$savedProofs): array {
                $before = $this->snapshot();
                $cases = [];
                foreach ($meters as $meter => $consumer) {
                    Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
                    self::assertSame('Active', $consumer->status);
                    self::assertTrue($consumer->isVerified());
                    self::assertNotNull($consumer->user);
                    self::assertFalse($consumer->disconnectionNotices()->active()->exists());
                    $waterBills = $consumer->bills()->where('bill_type', 'Water')->where('prn', 'not like', 'QA-DSS-%');
                    $generated = (clone $waterBills)->whereHas('meterReading', fn ($query) => $query->where('remarks', self::TAG))->first();
                    $original = $waterBills->whereDoesntHave('meterReading', fn ($query) => $query->where('remarks', self::TAG))
                        ->orderBy('billing_period')->get();
                    self::assertNotEmpty($original);
                    self::assertTrue($original->every(fn (Bill $bill) => $bill->balance_due === 0.0), 'An existing water debt requires a separate fixture plan.');
                    $fixture = self::PAYLOADS[$meter];
                    $earliest = $original->first()->billing_period;
                    $period = $generated?->billing_period ?? $original->last()->billing_period->copy()->addMonthNoOverflow();
                    $rate = $consumer->hasVerifiedMembership() ? BillingComputationService::MEMBER_UNIT_RATE : BillingComputationService::NON_MEMBER_UNIT_RATE;
                    $previous = $fixture['initial'];
                    $sourceBills = [];
                    $previousBalance = 0.0;
                    foreach ($fixture['historical'] as $index => $units) {
                        $month = $earliest->copy()->subMonthsNoOverflow(2 - $index);
                        $source = $this->historicalBill($consumer, $reader, $admin, $month, $previous, $units, $rate, $fixture['payments'][$index], $index);
                        $sourceBills[] = $source;
                        $previousBalance += (float) $source->amount - $fixture['payments'][$index];
                        $previous += $units;
                    }
                    $reading = $consumer->meterReadings()->where('remarks', self::TAG)->whereYear('reading_date', $period->year)
                        ->whereMonth('reading_date', $period->month)->first();
                    if (! $reading) {
                        self::assertTrue($period->isSameMonth(today()), 'The next available bill period must be the current month for a new fixture run.');
                        self::assertSame($previousBalance, $balances->totalOutstandingFor($consumer->id));
                        $payload = ['meter_number' => $meter, 'reading_date' => today()->toDateString()];
                        $this->actingAs($reader)->postJson(route('meter-readings.lookup-by-meter'), $payload)->assertOk()
                            ->assertJsonPath('already_read', false)->assertJsonPath('consumer.previous_reading', number_format($previous, 2, '.', ''))
                            ->assertJsonMissingPath('consumer.previous_balance');
                        $this->actingAs($admin)->postJson(route('meter-readings.lookup-by-meter'), $payload)->assertOk()
                            ->assertJsonPath('consumer.previous_balance', number_format($previousBalance, 2, '.', ''))
                            ->assertJsonPath('consumer.outstanding_period', $sourceBills[1]->billing_period->format('F Y'));
                        $this->actingAs($reader)->post(route('meter-readings.store'), $payload + [
                            'current_reading' => $previous + $fixture['current'], 'remarks' => self::TAG,
                        ])->assertRedirect(route('meter-readings.index'))->assertSessionHasNoErrors();
                        $reading = $consumer->meterReadings()->whereDate('reading_date', $payload['reading_date'])->where('remarks', self::TAG)->sole();
                    }
                    self::assertSame((float) $previous, (float) $reading->previous_reading);
                    self::assertSame((float) $fixture['current'], (float) $reading->consumption);
                    $cases[$meter] = compact('consumer', 'reading', 'period', 'sourceBills', 'previousBalance', 'rate', 'fixture');
                }

                // Run the real batch endpoint with only these water readings in
                // scope. Existing consumers and loan schedules remain untouched.
                $originalScopes = Model::getAllGlobalScopes();
                try {
                    $ids = $meters->modelKeys();
                    Consumer::addGlobalScope('fixture-consumers', fn (Builder $query) => $query->whereIn('consumers.id', $ids));
                    MeterReading::addGlobalScope('fixture-readings', fn (Builder $query) => $query->whereIn('meter_readings.consumer_id', $ids));
                    Loan::addGlobalScope('fixture-water-only', fn (Builder $query) => $query->whereRaw('1 = 0'));
                    $period = reset($cases)['period'];
                    self::assertTrue(collect($cases)->every(fn ($case) => $case['period']->equalTo($period)));
                    $payload = [
                        'billing_period' => $period->toDateString(), 'due_date' => $period->copy()->endOfMonth()->toDateString(),
                        'base_charge' => BillingComputationService::DEFAULT_BASE_CHARGE,
                        'member_unit_rate' => BillingComputationService::MEMBER_UNIT_RATE,
                        'non_member_unit_rate' => BillingComputationService::NON_MEMBER_UNIT_RATE,
                    ];
                    for ($attempt = 0; $attempt < 2; $attempt++) {
                        $this->actingAs($admin)->post(route('billing.generate'), $payload)
                            ->assertRedirect(route('billing.index', ['period' => $period->format('Y-m')]))->assertSessionHasNoErrors();
                    }
                } finally {
                    Model::setAllGlobalScopes($originalScopes);
                }

                $result = [];
                foreach ($cases as $meter => $case) {
                    ['consumer' => $consumer, 'reading' => $reading, 'period' => $period, 'sourceBills' => $sourceBills,
                        'previousBalance' => $previousBalance, 'rate' => $rate, 'fixture' => $fixture] = $case;
                    $bill = $consumer->bills()->where('bill_type', 'Water')->whereDate('billing_period', $period)->sole();
                    $charges = BillingComputationService::DEFAULT_BASE_CHARGE + $fixture['current'] * $rate;
                    self::assertSame($reading->id, $bill->meter_reading_id);
                    self::assertSame($previousBalance, (float) $bill->arrears_amount);
                    self::assertSame($charges, (float) $bill->water_charge);
                    self::assertSame($charges + $previousBalance, $bill->balance_due);
                    self::assertSame($bill->balance_due, $balances->totalOutstandingFor($consumer->id));
                    $overdueSources = array_filter($sourceBills, fn (Bill $source) => (float) $source->amount > $source->verified_paid_amount);
                    foreach ($overdueSources as $source) {
                        self::assertSame($bill->id, $source->fresh()->carried_forward_to_bill_id);
                        self::assertSame(0.0, $source->fresh()->balance_due);
                    }
                    $loan = $this->submittedLoan($consumer, $admin, $savedProofs);
                    $evaluation = $loan->latestDssEvaluation;
                    $values = $evaluation->evaluated_values;
                    self::assertSame('Verified', $loan->income_verification_status);
                    self::assertSame('Rejected', $loan->status);
                    self::assertSame('Rejected', $evaluation->recommendation);
                    self::assertSame(1, LoanDssEvaluation::query()->where('loan_id', $loan->id)->count());
                    self::assertGreaterThanOrEqual(2, $values['late_payments_last_six_months']);
                    self::assertSame($fixture['overdue_months'], $values['overdue_water_months']);
                    self::assertSame($previousBalance, (float) $values['overdue_water_balance']);
                    $reasons = collect($evaluation->failed_criteria)->pluck('id');
                    self::assertTrue($reasons->contains('late_payment_history') && $reasons->contains('outstanding_balance'));
                    $monitor = app(OverdueMonitoringService::class)->evaluate($consumer, notify: true);
                    self::assertFalse($monitor['notice_issued']);
                    self::assertSame('Active', $consumer->fresh()->status);
                    self::assertFalse($consumer->disconnectionNotices()->active()->exists());
                    $this->verifyPages($admin, $reader, $consumer, $bill, $loan);
                    $result[$meter] = [
                        'consumer' => $consumer->full_name, 'account' => $consumer->account_number,
                        'period' => $period->format('Y-m'), 'previous_reading' => (float) $reading->previous_reading,
                        'current_reading' => (float) $reading->current_reading, 'consumption_m3' => (float) $reading->consumption,
                        'previous_balance' => $previousBalance, 'current_charges' => $charges, 'total_due' => $bill->balance_due,
                        'bill_id' => $bill->id, 'prn' => $bill->prn, 'loan_id' => $loan->id, 'loan_status' => $loan->status,
                        'late_bill_records' => $values['late_payments_last_six_months'], 'overdue_periods' => $values['overdue_water_months'],
                        'failed_dss_criteria' => $reasons->all(),
                    ];
                }

                $this->assertHistoryPreserved($before, $meters->modelKeys());
                return $result;
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($savedProofs);
            throw $error;
        }

        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
        echo 'Verified both roles and the reader against the real Laravel HTTP routes. Existing history matched and no notices or email were added.'.PHP_EOL;
    }

    private function historicalBill(Consumer $consumer, User $reader, User $admin, Carbon $month, float $previous, int $units, float $rate, float $paid, int $index): Bill
    {
        $prn = 'QA-DSS-'.$consumer->meter_number.'-'.$month->format('Ym');
        $bill = $consumer->bills()->where('prn', $prn)->first();
        if ($bill) {
            self::assertSame($paid, $bill->verified_paid_amount);
            return $bill;
        }
        self::assertFalse($consumer->bills()->where('bill_type', 'Water')->whereDate('billing_period', $month)->exists());
        $reading = MeterReading::create([
            'consumer_id' => $consumer->id, 'meter_number' => $consumer->meter_number, 'reader_id' => $reader->id,
            'reading_date' => $month->copy()->addDays(9), 'previous_reading' => $previous,
            'current_reading' => $previous + $units, 'consumption' => $units, 'status' => MeterReading::STATUS_ENCODED,
            'submitted_at' => $month->copy()->addDays(9)->setTime(9, 0), 'remarks' => self::TAG,
        ]);
        $base = BillingComputationService::DEFAULT_BASE_CHARGE;
        $charges = $base + $units * $rate;
        $due = $month->copy()->addDays(24);
        $bill = $consumer->bills()->create([
            'meter_reading_id' => $reading->id, 'bill_type' => 'Water', 'billing_period' => $month, 'due_date' => $due,
            'prn' => $prn, 'amount' => $charges, 'base_charge' => $base, 'water_charge' => $charges,
            'unit_rate' => $rate, 'units_consumed' => $units, 'status' => 'Unpaid', 'months_unpaid' => 0,
        ]);
        $paidAt = $due->copy()->addDays($index === 0 ? 5 : 8)->setTime(10, 0);
        self::assertTrue($paidAt->isPast());
        Payment::create([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'payment_type' => 'Water',
            'reference_number' => $prn.'-PAY', 'amount' => $paid, 'payment_date' => $paidAt,
            'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'status' => 'Verified',
            'verified_by' => $admin->id, 'verified_at' => $paidAt, 'notes' => self::TAG,
        ]);
        $bill->syncPaymentStatus(syncConsumer: false);
        return $bill;
    }

    private function submittedLoan(Consumer $consumer, User $admin, array &$savedProofs): Loan
    {
        $loan = $consumer->loans()->where('purpose', self::TAG)->first();
        if ($loan) {
            return $loan;
        }
        $proof = tempnam(sys_get_temp_dir(), 'viaflow-income-');
        $pdf = new Dompdf\Dompdf();
        $pdf->loadHtml('<h1>ViaFlow development test income proof</h1><p>Synthetic sample for account '.e($consumer->account_number).'.</p><p>Sample monthly earnings PHP 20,000.</p>');
        $pdf->render();
        file_put_contents($proof, $pdf->output());
        try {
            $terms = ['term_months' => 6, 'terms_accepted' => '1'];
            $token = $this->actingAs($consumer->user)->postJson(route('loans.confirm-submission'), $terms + ['confirmed' => '1'])
                ->assertOk()->json('confirmation_token');
            $this->post(route('loans.store'), $terms + [
                'confirmation_token' => $token, 'purpose' => self::TAG,
                'proof_of_income' => new UploadedFile($proof, 'development-income-proof.pdf', 'application/pdf', null, true),
            ])->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();
        } finally {
            unlink($proof);
        }
        $loan = $consumer->loans()->where('purpose', self::TAG)->sole();
        $savedProofs[] = $loan->proof_of_income_path;
        self::assertSame('Needs Review', $loan->status);
        self::assertNull($loan->latestDssEvaluation);
        $this->actingAs($admin)->get(route('loans.income-proof', $loan))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])
            ->assertRedirect(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))->assertSessionHasNoErrors();
        return $loan->fresh();
    }

    private function verifyPages(User $admin, User $reader, Consumer $consumer, Bill $bill, Loan $loan): void
    {
        // Some GET routes also reconcile records outside this scenario. Render
        // the normal views while rolling back those unrelated side effects.
        DB::beginTransaction();
        try {
            $meter = $consumer->meter_number;
            $total = number_format($bill->balance_due, 2);
            $previous = number_format((float) $bill->arrears_amount, 2);
            $reference = 'QA-DSS-'.$meter;
            $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active', 'search' => $meter]))
                ->assertOk()->assertSee($meter)->assertSee($consumer->full_name);
            $this->get(route('billing.index', ['search' => $meter]))->assertOk()->assertSee($consumer->full_name)->assertSee($total);
            $this->get(route('billing.show', $bill))->assertOk()->assertSee($bill->prn)->assertSee($previous)->assertSee($total);
            $this->get(route('payments.index', ['view' => 'water-payments', 'search' => $meter]))->assertOk()->assertSee($consumer->full_name)->assertSee($reference);
            $this->get(route('loans.index', ['tab' => 'all', 'loan' => $loan->id]))->assertOk()
                ->assertSee($consumer->full_name)->assertSee('Rejection Reason')->assertSee('Delayed or late payment records');
            $this->get(route('dss.loans'))->assertOk()->assertSee($consumer->full_name)->assertSee('Rejected');
            $this->actingAs($consumer->user)->get(route('billing.index'))->assertOk()->assertSee($bill->prn)->assertSee($total);
            $this->get(route('billing.show', $bill))->assertOk()->assertSee($previous)->assertSee($total);
            $this->get(route('payments.index'))->assertOk()->assertSee($reference)->assertSee($total);
            $this->get(route('loans.index'))->assertOk()->assertSee('View DSS rejection reasons')->assertSee('Delayed or late payment records')
                ->assertSee('Overdue water and previous-loan balances');
            $this->actingAs($reader)->get(route('meter-readings.index', ['search' => $meter, 'reading_month' => $bill->billing_period->format('Y-m')]))
                ->assertOk()->assertSee($meter)->assertSee($consumer->full_name)->assertSee(number_format((float) $bill->units_consumed, 2));
        } finally {
            DB::rollBack();
        }
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (self::TABLES as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->keyBy('id');
        }
        return $snapshot;
    }

    private function assertHistoryPreserved(array $before, array $ids): void
    {
        $after = $this->snapshot();
        foreach ($before as $table => $rows) {
            foreach ($rows as $id => $original) {
                self::assertEquals($original, $after[$table]->get($id), "Existing {$table} row {$id} changed.");
            }
            $added = $after[$table]->diffKeys($rows);
            if (in_array($table, ['bills', 'meter_readings', 'payments', 'loans'], true)) {
                self::assertTrue($added->every(fn ($row) => in_array((int) $row->consumer_id, $ids, true)), "Unexpected consumer in new {$table} records.");
            } else {
                self::assertCount(0, $added, "Unexpected new {$table} records.");
            }
        }
    }
}

try {
    (new BillingDssFixtures('runFixtures'))->runFixtures();
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL.$error->getTraceAsString().PHP_EOL);
    exit(1);
}
