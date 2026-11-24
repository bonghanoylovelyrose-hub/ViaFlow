<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanBillingSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanNovemberAdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scheduledLoan(User $user, ?string $start = '2026-12-01'): Loan
    {
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Advance Consumer',
            'account_number' => 'NOV-'.$user->id, 'meter_number' => 'NOV-M-'.$user->id,
            'status' => 'Active', 'membership_type' => 'Member']);

        return Loan::create(['consumer_id' => $consumer->id, 'status' => 'Ready for Scheduling',
            'review_status' => 'Installation Scheduled', 'installation_status' => 'Scheduled',
            'amount' => 240, 'monthly_installment' => 40, 'term_months' => 6, 'purpose' => 'Installation',
            'applied_at' => '2026-11-09', 'approved_at' => '2026-11-10', 'start_billing_period' => $start,
            'installment_schedule_snapshot' => array_map(fn ($month) => ['month' => $month, 'amount' => 40], range(1, 6))]);
    }

    public function test_november_advances_cover_the_saved_december_schedule_and_future_billing_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([20 => [20, 0], 40 => [40, 0], 50 => [40, 10], 80 => [40, 40], 240 => [40, 40]] as $amount => $covered) {
            $user = User::factory()->create(['role' => 'consumer']);
            $loan = $this->scheduledLoan($user, $amount === 40 ? null : '2026-12-01');
            $this->actingAs($user)->get(route('payments.index', ['view' => 'loan-payments']))->assertOk()
                ->assertSee('Loan #'.$loan->id)->assertSee('data-first-month="Dec 2026"', false)
                ->assertSee('Monthly Payment')->assertSee('Custom Amount')->assertSee('Advance Payment')->assertSee('Pay in Full');
            $this->assertSame('2026-12-01', $loan->fresh()->start_billing_period->toDateString());
            $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
            $this->post(route('payments.store'), ['loan_id' => $loan->id, 'payment_type' => 'Loan', 'amount' => $amount,
                'payment_date' => '2026-11-18', 'payment_channel' => 'Online', 'payment_method' => 'GCash',
                'gateway_reference' => 'NOV-ADV-'.$user->id])->assertSessionHasNoErrors();
            $payment = Payment::where('gateway_reference', 'NOV-ADV-'.$user->id)->firstOrFail();
            $this->assertSame('Pending', $payment->status);
            $this->assertSame(240.0, $loan->fresh()->balance_remaining);
            $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
            $this->assertSame($covered, $loan->installments()->orderBy('installment_number')->limit(2)->get()->map(fn ($item) => (int) $item->amount_paid)->all());
            $this->assertSame(240.0 - $amount, $loan->fresh()->balance_remaining);
            $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
            $this->assertSame('Scheduled', $loan->fresh()->installation_status);
            $this->actingAs($user)->get(route('payments.receipt', $payment))->assertOk()->assertSee('December 2026');
            if ($amount > 40) {
                $this->get(route('payments.receipt', $payment))->assertSee('January 2027');
            }

            if ($amount === 240) {
                $this->get(route('loans.index'))->assertOk()->assertSee('Fully Paid')->assertSee('Installation scheduling remains in progress.');
                $loan->update(['material_meter_cost' => 100, 'material_hose_cost' => 100, 'material_other_cost' => 40, 'installation_cost_total' => 240]);
                LoanDssEvaluation::create(['loan_id' => $loan->id, 'recommendation' => 'Approved', 'evaluation_type' => 'initial',
                    'criteria_version' => 'test', 'evaluated_values' => [], 'income_evidence' => [], 'failed_criteria' => [],
                    'missing_information' => [], 'evidence_hash' => hash('sha256', 'approved'), 'evaluated_at' => now()]);
                $this->actingAs($admin)->get(route('loans.index'))->assertOk()->assertSee('Fully Paid')->assertSee('Assign Meter &amp; Schedule Installation', false);
                $this->patch(route('loans.installation-schedule.update', $loan), ['installation_date' => '2026-11-25', 'installation_time' => '09:00'])
                    ->assertSessionHasNoErrors();
                $this->assertSame('2026-11-25', $loan->fresh()->installation_date->toDateString());
                $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
                $this->post(route('billing.generate'), ['billing_period' => '2026-12-01', 'due_date' => '2026-12-31',
                    'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20])->assertSessionHasNoErrors();
                $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());

                continue;
            }
            // Billing follows the saved schedule without changing installation status.
            foreach (['2026-12-01', '2027-01-01'] as $period) {
                $data = ['billing_period' => $period, 'due_date' => Carbon::parse($period)->endOfMonth()->toDateString(),
                    'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20];
                $this->actingAs($admin)->post(route('billing.generate'), $data)->assertSessionHasNoErrors();
                $this->post(route('billing.generate'), $data)->assertSessionHasNoErrors();
            }
            $december = Bill::where('loan_id', $loan->id)->whereDate('billing_period', '2026-12-01')->get();
            $this->assertCount($amount === 20 ? 1 : 0, $december);
            if ($amount === 20) {
                $this->assertSame(20.0, $december->first()->balance_due);
            }
            $january = Bill::where('loan_id', $loan->id)->whereDate('billing_period', '2027-01-01')->get();
            $this->assertCount($amount === 80 ? 0 : 1, $january);
            if ($amount !== 80) {
                $this->assertSame($amount === 50 ? 30.0 : 40.0, $january->first()->balance_due);
            }
            $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        }
    }

    public function test_saved_start_for_another_loan_is_not_replaced_with_december(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $loan = $this->scheduledLoan($user, '2027-01-01');
        $this->actingAs($user)->get(route('payments.index', ['view' => 'loan-payments']))->assertOk()->assertSee('data-first-month="Jan 2027"', false);
        $this->assertSame('2027-01-01', $loan->fresh()->start_billing_period->toDateString());
        $this->assertSame('2027-01-01', $loan->installments()->where('installment_number', 1)->firstOrFail()->billing_period->toDateString());
    }

    public function test_activated_loan_can_be_paid_in_advance_without_generating_a_future_bill(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->scheduledLoan($user);
        $loan->update(['status' => 'Approved']);
        $this->actingAs($user)->get(route('payments.index', ['view' => 'loan-payments']))->assertOk()->assertSee('Loan #'.$loan->id);
        $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
        $this->post(route('payments.store'), ['loan_id' => $loan->id, 'payment_type' => 'Loan', 'amount' => 50,
            'payment_date' => '2026-11-18', 'payment_channel' => 'Online', 'payment_method' => 'GCash',
            'gateway_reference' => 'NOV-NO-BILL'])->assertSessionHasNoErrors();
        $payment = Payment::where('gateway_reference', 'NOV-NO-BILL')->firstOrFail();
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
        $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
        $this->assertSame(40.0, (float) $loan->installments()->where('installment_number', 1)->firstOrFail()->amount_paid);
        $this->assertSame(30.0, (float) $loan->installments()->where('installment_number', 2)->firstOrFail()->remaining_balance);
    }

    public function test_automatic_billing_for_a_scheduled_installation_respects_advance_credits(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->scheduledLoan($user);
        $this->actingAs($user)->post(route('payments.store'), ['loan_id' => $loan->id, 'payment_type' => 'Loan', 'amount' => 50,
            'payment_date' => '2026-11-18', 'payment_channel' => 'Online', 'payment_method' => 'GCash',
            'gateway_reference' => 'NOV-AUTO'])->assertSessionHasNoErrors();
        $payment = Payment::where('gateway_reference', 'NOV-AUTO')->firstOrFail();
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
        $synchronizer = app(LoanBillingSynchronizer::class);
        $synchronizer->synchronize($loan->consumer_id, Carbon::parse('2027-01-01'));
        $synchronizer->synchronize($loan->consumer_id, Carbon::parse('2027-01-01'));
        $this->assertFalse(Bill::where('loan_id', $loan->id)->whereDate('billing_period', '2026-12-01')->exists());
        $january = Bill::where('loan_id', $loan->id)->whereDate('billing_period', '2027-01-01')->get();
        $this->assertCount(1, $january);
        $this->assertSame(30.0, $january->first()->balance_due);
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
    }

    public function test_approved_loan_with_conflicting_records_is_listed_for_review_without_accepting_payment(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $loan = $this->scheduledLoan($user);
        $this->actingAs($user)->get(route('payments.index', ['view' => 'loan-payments']))->assertOk();
        $loan->installments()->where('installment_number', 1)->update(['required_amount' => 41, 'remaining_balance' => 41]);
        $this->get(route('payments.index', ['view' => 'loan-payments']))->assertOk()
            ->assertSee('Loan #'.$loan->id)->assertSee('Administrator review required')
            ->assertSee('Some approved loans need administrator review before payment.');
        $this->post(route('payments.store'), ['loan_id' => $loan->id, 'payment_type' => 'Loan', 'amount' => 40,
            'payment_date' => '2026-11-18', 'payment_channel' => 'Online', 'payment_method' => 'GCash',
            'gateway_reference' => 'NOV-REVIEW'])->assertSessionHasErrors('bill_id');
        $this->assertDatabaseMissing('payments', ['gateway_reference' => 'NOV-REVIEW']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('billing.generate'), ['billing_period' => '2026-12-01', 'due_date' => '2026-12-31',
            'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20])->assertSessionHasNoErrors();
        $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
    }
}
