<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LocalNovemberBillingScenarioSeeder extends Seeder
{
    /** Monthly cumulative closing readings and whether September has a bill. */
    private const SCENARIOS = [
        'MT-0909' => ['id' => 88, 'closing' => [8, 17, 27], 'notice' => true, 'payment' => 0],
        'MT-9191' => ['id' => 87, 'closing' => [12, 22, 37], 'notice' => true, 'payment' => 0],
        'MT-3333' => ['id' => 109, 'closing' => [4, 14, 21], 'notice' => false, 'payment' => 100],
        'MT-7777' => ['id' => 80, 'closing' => [6, 21, 29], 'notice' => false, 'payment' => 0],
    ];

    public function run(BillingComputationService $billing, ConsumerBalanceService $balances): void
    {
        $db = DB::connection();
        if (! app()->isLocal() || $db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'water_billing_systems'
            || ! in_array($db->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            || strcasecmp($db->selectOne('SELECT @@hostname AS host')->host, gethostname()) !== 0) {
            throw new RuntimeException('The billing fixtures require the local ViaFlow development database.');
        }

        DB::transaction(function () use ($billing, $balances): void {
            $reader = User::query()->whereIn('role', ['staff', 'meter_reader'])->orderBy('id')->firstOrFail();
            $admin = User::query()->where('role', 'admin')->sole();

            foreach (self::SCENARIOS as $meter => $scenario) {
                $consumer = Consumer::query()->whereKey($scenario['id'])->where('meter_number', $meter)->lockForUpdate()->sole();
                if ($consumer->status !== 'Active' || $consumer->disconnected_at || ! $consumer->user?->isConsumer()) {
                    throw new RuntimeException("The expected connected development account for {$meter} is unavailable.");
                }
                $this->seedScenario($consumer, $scenario, $reader, $admin, $billing, $balances);
            }
        });
    }

    private function seedScenario(Consumer $consumer, array $scenario, User $reader, User $admin, BillingComputationService $billing, ConsumerBalanceService $balances): void
    {
        $meter = $consumer->meter_number;
        $fixtures = $consumer->bills()->onlyBillingFixtures()->orderBy('billing_period')->get();
        $readingCount = $consumer->meterReadings()->onlyBillingFixtures()->count();
        $noticeCount = $consumer->disconnectionNotices()->onlyBillingFixtures()->count();
        $paymentCount = Payment::query()->onlyBillingFixtures()->where('consumer_id', $consumer->id)->count();
        if ($fixtures->isNotEmpty() || $readingCount || $noticeCount || $paymentCount) {
            if ($fixtures->count() !== ($scenario['notice'] ? 3 : 2) || $readingCount !== 3
                || $noticeCount !== (int) $scenario['notice'] || $paymentCount !== (int) ($scenario['payment'] > 0)) {
                throw new RuntimeException("An incomplete billing fixture already exists for {$meter}.");
            }

            return;
        }

        $rate = $consumer->hasVerifiedMembership() ? BillingComputationService::MEMBER_UNIT_RATE : BillingComputationService::NON_MEMBER_UNIT_RATE;
        $previousClosing = 0;
        $previousBill = null;
        $demoBills = collect();

        foreach ($scenario['closing'] as $index => $closing) {
            $month = 9 + $index;
            $date = Carbon::create(2026, $month, 25, 9, 0, 0, 'Asia/Manila');
            $period = $date->copy()->startOfMonth();
            $reading = new MeterReading([
                'consumer_id' => $consumer->id,
                'meter_number' => $meter,
                'reader_id' => $reader->id,
                'reading_date' => $date->toDateString(),
                'previous_reading' => $previousClosing,
                'current_reading' => $closing,
                'consumption' => $closing - $previousClosing,
                'status' => MeterReading::STATUS_ENCODED,
                'remarks' => 'Development billing test reading',
                'submitted_at' => now(),
            ]);
            $reading->forceFill(['is_billing_fixture' => true])->save();
            $previousClosing = $closing;

            if ($month === 9 && ! $scenario['notice']) {
                continue;
            }
            if ($month === 11 && $scenario['notice']) {
                $this->issueNotice($consumer, $demoBills, $previousBill, $balances);
            }

            $charges = $billing->calculate((float) $reading->consumption, BillingComputationService::DEFAULT_BASE_CHARGE, $rate, $previousBill?->balance_due ?? 0);
            $bill = new Bill([
                'consumer_id' => $consumer->id,
                'bill_type' => 'Water',
                'meter_reading_id' => $reading->id,
                'billing_period' => $period->toDateString(),
                'due_date' => $date->copy()->addDays(15)->toDateString(),
                'prn' => 'DEMO-WATER-'.$meter.'-'.$period->format('Ym'),
                'amount' => $charges['total_due'],
                'base_charge' => $charges['base_charge'],
                'water_charge' => $charges['current_charges'],
                'arrears_amount' => $charges['previous_balance'],
                'carried_forward_from_bill_id' => $previousBill?->id,
                'units_consumed' => $reading->consumption,
                'unit_rate' => $rate,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);
            $bill->forceFill(['created_at' => $date->copy()->utc(), 'is_demo' => true, 'is_billing_fixture' => true]);
            $bill->status = $bill->hasBeenIssued() && $balances->isOverdue($bill) ? 'Overdue' : 'Unpaid';
            $bill->save();
            $previousBill?->carryBalanceInto($bill);

            if ($month === 10 && $scenario['payment'] > 0) {
                $payment = new Payment([
                    'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'payment_type' => 'Water',
                    'reference_number' => 'DEMO-WATER-'.$meter.'-202610-PAY', 'amount' => $scenario['payment'],
                    'payment_date' => '2026-11-01', 'payment_method' => 'Cash', 'payment_channel' => 'Counter',
                    'status' => 'Verified', 'verified_at' => now(), 'verified_by' => $admin->id,
                    'notes' => 'Development billing test payment',
                ]);
                $payment->forceFill(['is_billing_fixture' => true])->save();
                $bill->syncPaymentStatus(false);
            }
            $demoBills->push($bill);
            $previousBill = $bill;
        }
    }

    private function issueNotice(Consumer $consumer, Collection $historical, Bill $october, ConsumerBalanceService $balances): void
    {
        // The existing issued November statement can trigger the two-prior-month
        // rule today. The scheduled demo statement contributes no November debt.
        $issuedNovember = $consumer->bills()->where('bill_type', 'Water')->whereDate('billing_period', '2026-11-01')->sole();
        $standing = $balances->waterNoticeStandingForBills($historical->concat([$issuedNovember]));
        if ($standing['months_unpaid'] !== 2 || ! $standing['notice_bill']?->is($issuedNovember) || $october->balance_due <= 0) {
            throw new RuntimeException("The demo balances are not yet eligible for a notice for {$consumer->meter_number}.");
        }
        $issuedAt = now();
        $notice = new DisconnectionNotice([
            'consumer_id' => $consumer->id, 'bill_id' => $october->id, 'source' => DisconnectionNotice::SOURCE_WATER,
            'status' => DisconnectionNotice::STATUS_ACTIVE,
            'issued_at' => $issuedAt, 'deadline_at' => $issuedAt->copy()->addDays(ConsumerBalanceService::PAYMENT_GRACE_DAYS),
            'outstanding_amount' => $october->balance_due, 'months_unpaid' => $standing['months_unpaid'],
            'disconnection_reason' => DisconnectionNotice::reasonForSources(water: true, loan: false),
            'notes' => 'Development billing test notice',
        ]);
        $notice->forceFill(['is_billing_fixture' => true])->save();
    }
}
