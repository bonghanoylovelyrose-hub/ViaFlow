<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanBillingSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanAdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scenario(): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Loan Consumer', 'account_number' => 'ADV-'.$user->id, 'status' => 'Active', 'membership_type' => 'Member']);
        $loan = Loan::create([
            'consumer_id' => $consumer->id, 'amount' => 240, 'term_months' => 6, 'monthly_installment' => 40,
            'purpose' => 'Loan', 'status' => 'Approved', 'applied_at' => '2026-08-01', 'approved_at' => '2026-08-01',
            'start_billing_period' => '2026-09-01', 'next_due_date' => '2026-09-30',
            'installment_schedule_snapshot' => array_map(fn ($month) => ['month' => $month, 'amount' => 40], range(1, 6)),
        ]);
        $bill = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Loan', 'loan_id' => $loan->id,
            'billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'prn' => 'LN-'.$loan->id.'-1',
            'amount' => 40, 'loan_installment_amount' => 40, 'status' => 'Unpaid']);
        LoanInstallment::create(['loan_id' => $loan->id, 'bill_id' => $bill->id, 'installment_number' => 1,
            'billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'required_amount' => 40,
            'amount_paid' => 0, 'remaining_balance' => 40, 'status' => 'Billed']);
        $water = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30', 'prn' => 'W-'.$loan->id, 'amount' => 150, 'status' => 'Unpaid']);

        return [$user, $loan, $bill, $water];
    }

    private function submit(User $user, Bill $bill, float $amount): Payment
    {
        $ref = (string) Str::uuid();
        $this->actingAs($user)->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => $amount, 'payment_date' => '2026-09-10', 'payment_channel' => 'Online',
            'payment_method' => 'GCash', 'gateway_reference' => $ref])->assertSessionHasNoErrors();

        return Payment::where('gateway_reference', $ref)->firstOrFail();
    }

    public function test_monthly_partial_advance_and_full_payments_are_allocated_oldest_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([
            [20, [20, 0, 0, 0, 0, 0]],
            [40, [40, 0, 0, 0, 0, 0]],
            [50, [40, 10, 0, 0, 0, 0]],
            [80, [40, 40, 0, 0, 0, 0]],
            [240, [40, 40, 40, 40, 40, 40]],
        ] as [$amount, $expected]) {
            [$user, $loan, $bill, $water] = $this->scenario();
            $payment = $this->submit($user, $bill, $amount);
            $this->assertSame('Pending', $payment->status);
            $this->assertSame(0.0, $loan->fresh()->total_repaid);
            $this->assertSame(40.0, $bill->fresh()->balance_due);
            $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
            $loan->refresh();
            $rows = $loan->installments()->orderBy('installment_number')->get();
            $this->assertSame($expected, $rows->map(fn ($row) => (int) $row->amount_paid)->all());
            $this->assertSame(240.0 - $amount, $loan->balance_remaining);
            $this->assertSame(150.0, $water->fresh()->balance_due);
            $this->assertSame(6, $loan->term_months);
            $this->assertSame(1, $loan->installments_billed);
            $this->assertSame(40.0, $loan->amount_billed);
            $this->assertSame($amount === 240 ? 'Completed' : 'Approved', $loan->status);
            $this->assertSame((float) $amount, (float) $payment->fresh()->loanRepayment->allocations()->sum('amount'));
            $receipt = $this->actingAs($user)->get(route('payments.receipt', $payment))->assertOk()->assertSee('Installments covered and advance credits')->assertSee('September 2026');
            if ($amount > 40) {
                $receipt->assertSee('October 2026')->assertSee('Advance credit');
            }
            if ($amount === 240) {
                $this->get(route('loans.index'))->assertSee('Fully Paid');
            }
        }
    }

    public function test_prepaid_months_do_not_generate_duplicate_bills_and_partial_month_is_reduced(): void
    {
        [$user, $loan, $bill] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $payment = $this->submit($user, $bill, 80);
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
        $billing = ['billing_period' => '2026-10-01', 'due_date' => '2026-10-31', 'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20];
        $this->post(route('billing.generate'), $billing)->assertSessionHasNoErrors();
        $this->assertFalse(Bill::where('loan_id', $loan->id)->whereDate('billing_period', '2026-10-01')->exists());
        $this->assertSame('Paid', $loan->installments()->where('installment_number', 2)->firstOrFail()->status);

        [$user2, $loan2, $bill2] = $this->scenario();
        $payment2 = $this->submit($user2, $bill2, 50);
        $this->actingAs($admin)->patch(route('payments.verify', $payment2))->assertSessionHasNoErrors();
        $this->post(route('billing.generate'), $billing)->assertSessionHasNoErrors();
        $next = $loan2->installments()->where('installment_number', 2)->firstOrFail();
        $this->assertNotNull($next->bill_id);
        $this->assertSame(30.0, $next->bill->balance_due);
        $this->assertSame(40.0, (float) $next->required_amount);
        $this->assertSame(2, $loan2->fresh()->installments_billed);
        $this->assertSame(80.0, $loan2->fresh()->amount_billed);
    }

    public function test_pending_rejected_duplicate_and_overpayment_do_not_change_loan_or_water(): void
    {
        [$user, $loan, $bill, $water] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->submit($user, $bill, 200);
        $second = $this->submit($user, $bill, 200);
        $this->assertSame(0.0, $loan->fresh()->total_repaid);
        $this->actingAs($admin)->patch(route('payments.verify', $first))->assertSessionHasNoErrors();
        $this->patch(route('payments.verify', $second))->assertSessionHasErrors('amount');
        $this->assertSame('Pending', $second->fresh()->status);
        $this->patch(route('payments.reject', $second), ['rejection_reason' => 'Duplicate payment'])->assertRedirect();
        $this->patch(route('payments.verify', $first))->assertSessionHasErrors('status');
        $this->assertSame(40.0, $loan->fresh()->balance_remaining);
        $this->assertSame(150.0, $water->fresh()->balance_due);
        $this->assertSame(1, $loan->repayments()->count());
        $this->actingAs($user)->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => 40.01, 'payment_date' => '2026-09-10', 'payment_channel' => 'Online', 'payment_method' => 'GCash', 'gateway_reference' => 'OVER-123'])->assertSessionHasErrors('amount');
    }

    public function test_paid_first_bill_can_anchor_a_later_payment_and_receipts_keep_their_original_balances(): void
    {
        [$user, $loan, $bill] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->submit($user, $bill, 50);
        $this->actingAs($admin)->patch(route('payments.verify', $first))->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('payments.index', ['view' => 'loan-payments']))
            ->assertSee('Monthly Payment')->assertSee('Pay in Full')->assertSee('data-loan-balance="190.00"', false);
        $second = $this->submit($user, $bill, 30);
        $this->actingAs($admin)->patch(route('payments.verify', $second))->assertSessionHasNoErrors();
        $this->assertSame(160.0, $loan->fresh()->balance_remaining);
        $this->assertSame(40.0, (float) $loan->installments()->where('installment_number', 2)->firstOrFail()->amount_paid);
        $response = $this->actingAs($user)->get(route('payments.receipt', $first))->assertOk();
        $response->assertViewHas('previousBalance', 240.0)->assertViewHas('remainingBalance', 190.0)->assertSee('October 2026');
        $this->get(route('payments.receipt.pdf', $first))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $final = $this->submit($user, $bill, 160);
        $this->actingAs($admin)->patch(route('payments.verify', $final))->assertSessionHasNoErrors();
        $this->assertSame('Completed', $loan->fresh()->status);
        $this->assertSame(6, $loan->installments()->where('status', 'Paid')->count());
        $this->get(route('loans.index', ['tab' => 'all']))->assertSee('Fully Paid');
        $this->actingAs(User::factory()->create(['role' => 'consumer']))->get(route('payments.receipt', $first))->assertForbidden();
    }

    public function test_manual_office_repayment_uses_same_allocation_and_preserves_water(): void
    {
        [, $loan, , $water] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('loans.repayments.store', $loan), ['amount' => 50, 'payment_date' => '2026-09-10'])->assertSessionHasNoErrors();
        $this->assertSame(190.0, $loan->fresh()->balance_remaining);
        $this->assertSame(10.0, (float) $loan->installments()->where('installment_number', 2)->firstOrFail()->amount_paid);
        $repayment = $loan->repayments()->whereNull('payment_id')->firstOrFail();
        $this->assertSame(2, $repayment->allocations()->count());
        $accounts = $this->get(route('payments.index', [
            'view' => 'loan-payments', 'search' => strtolower($loan->consumer->meter_number), 'year' => 2026, 'month' => 9, 'status' => 'Paid',
        ]))->assertOk()->viewData('paymentAccounts');
        $this->assertSame([$loan->consumer_id], $accounts->getCollection()->pluck('id')->all());
        $this->assertSame([$repayment->id], $accounts->first()['history']->getCollection()->where('kind', 'loan-repayment')->pluck('record.id')->all());
        $this->assertSame(150.0, $water->fresh()->balance_due);
    }

    public function test_ambiguous_legacy_water_link_is_not_modified_by_a_loan_payment(): void
    {
        [$user, $loan, $bill, $water] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $loan->installments()->firstOrFail()->update(['bill_id' => $water->id]);
        $payment = Payment::create(['bill_id' => $bill->id, 'consumer_id' => $loan->consumer_id, 'payment_type' => 'Loan',
            'reference_number' => 'INVALID-LINK', 'amount' => 40, 'payment_date' => '2026-09-10',
            'payment_channel' => 'Online', 'payment_method' => 'GCash', 'status' => 'Pending']);
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertNotFound();
        $this->assertSame(150.0, $water->fresh()->balance_due);
        $this->assertSame('Pending', $payment->fresh()->status);
        $this->assertDatabaseCount('loan_repayment_allocations', 0);
    }

    public function test_background_billing_skips_prepaid_months_and_is_idempotent(): void
    {
        [$user, $loan, $bill, $water] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $payment = $this->submit($user, $bill, 50);
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
        $sync = app(LoanBillingSynchronizer::class);
        $sync->synchronize($loan->consumer_id, Carbon::parse('2026-10-01'));
        $sync->synchronize($loan->consumer_id, Carbon::parse('2026-10-01'));
        $next = $loan->installments()->where('installment_number', 2)->firstOrFail();
        $this->assertSame(30.0, $next->bill->balance_due);
        $this->assertSame(1, Bill::where('loan_id', $loan->id)->whereDate('billing_period', '2026-10-01')->count());
        $this->assertSame(150.0, $water->fresh()->balance_due);

        [$user2, $loan2, $bill2] = $this->scenario();
        $payment2 = $this->submit($user2, $bill2, 80);
        $this->actingAs($admin)->patch(route('payments.verify', $payment2))->assertSessionHasNoErrors();
        $sync->synchronize($loan2->consumer_id, Carbon::parse('2026-10-01'));
        $this->assertFalse(Bill::where('loan_id', $loan2->id)->whereDate('billing_period', '2026-10-01')->exists());
        $this->assertSame(0.0, (float) $loan2->installments()->where('installment_number', 2)->firstOrFail()->remaining_balance);
    }

    public function test_incomplete_legacy_schedule_does_not_invent_future_installments(): void
    {
        [$user, $loan, $bill] = $this->scenario();
        $loan->update(['installment_schedule_snapshot' => null]);
        $this->actingAs($user)->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => 80, 'payment_date' => '2026-09-10', 'payment_channel' => 'Online', 'payment_method' => 'GCash', 'gateway_reference' => 'LEGACY-INCOMPLETE'])->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('payments', ['gateway_reference' => 'LEGACY-INCOMPLETE']);
        $this->assertSame(1, $loan->installments()->count());
        $this->assertSame(240.0, $loan->fresh()->balance_remaining);
        $this->assertDatabaseCount('loan_repayment_allocations', 0);
    }

    public function test_schedule_based_loan_payments_work_without_any_monthly_bill(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([20 => [20, 0], 40 => [40, 0], 50 => [40, 10], 80 => [40, 40], 240 => [40, 40]] as $amount => $expected) {
            $user = User::factory()->create(['role' => 'consumer']);
            $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Schedule Consumer', 'account_number' => 'DIRECT-'.$user->id, 'status' => 'Active', 'membership_type' => 'Member']);
            $loan = Loan::create(['consumer_id' => $consumer->id, 'amount' => 240, 'term_months' => 6, 'monthly_installment' => 40,
                'purpose' => 'Loan', 'status' => 'Approved', 'applied_at' => '2026-08-01', 'start_billing_period' => '2026-10-01',
                'installment_schedule_snapshot' => array_map(fn ($month) => ['month' => $month, 'amount' => 40], range(1, 6))]);
            $form = $this->actingAs($user)->get(route('payments.index', ['view' => 'loan-payments']))->assertOk();
            $form->assertSee('Select Active Loan')->assertSee('Monthly Payment')->assertSee('Custom Amount')->assertSee('Pay in Full')->assertSee('Loan #'.$loan->id)
                ->assertSee('loan_option_monthly', false)->assertSee('loan_option_custom', false)->assertSee('loan_option_full', false)
                ->assertSee('Payment Allocation Preview')->assertSee('loan_summary_due', false)->assertSee('loan_summary_balance', false)
                ->assertDontSee('No loan bills available');
            $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
            $reference = 'DIRECT-'.$user->id;
            $data = ['loan_id' => $loan->id, 'payment_type' => 'Loan', 'amount' => $amount, 'payment_date' => '2026-09-10',
                'payment_channel' => 'Online', 'payment_method' => 'GCash', 'gateway_reference' => $reference];
            $this->post(route('payments.store'), $data)->assertSessionHasNoErrors();
            $payment = Payment::where('gateway_reference', $reference)->firstOrFail();
            $this->assertNull($payment->bill_id);
            $this->assertSame('Pending', $payment->status);
            $this->assertSame(240.0, $loan->fresh()->balance_remaining);
            $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
            $this->assertSame($expected, $loan->installments()->orderBy('installment_number')->limit(2)->get()->map(fn ($row) => (int) $row->amount_paid)->all());
            $this->assertSame(240.0 - $amount, $loan->fresh()->balance_remaining);
            $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
            $this->actingAs($user)->get(route('payments.receipt', $payment))->assertOk()->assertSee('October 2026')->assertViewHas('remainingBalance', 240.0 - $amount);
            $this->get(route('payments.receipt.pdf', $payment))->assertOk();
            $this->post(route('payments.store'), $data)->assertSessionHasErrors('gateway_reference');
            if ($amount === 240) $this->assertSame('Completed', $loan->fresh()->status);
        }
    }

    public function test_direct_loan_payments_enforce_ownership_status_and_remaining_balance(): void
    {
        [$owner, $loan, , $water] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'consumer']);
        $data = ['loan_id' => $loan->id, 'payment_type' => 'Loan', 'amount' => 241, 'payment_date' => '2026-09-10',
            'payment_channel' => 'Online', 'payment_method' => 'GCash', 'gateway_reference' => 'DIRECT-GUARD'];
        $this->actingAs($other)->post(route('payments.store'), $data)->assertForbidden();
        $this->actingAs($owner)->post(route('payments.store'), $data)->assertSessionHasErrors('amount');
        $data['amount'] = 40;
        $this->post(route('payments.store'), $data)->assertSessionHasNoErrors();
        $pending = Payment::where('gateway_reference', 'DIRECT-GUARD')->firstOrFail();
        $this->actingAs($admin)->get(route('payments.index', ['view' => 'review']))->assertOk()
            ->assertSee('Loan #'.$loan->id)->assertSee('data-payment-balance="200.00"', false);
        $this->patch(route('payments.reject', $pending), ['rejection_reason' => 'Invalid receipt'])->assertRedirect();
        $this->assertSame(240.0, $loan->fresh()->balance_remaining);
        $this->assertSame(150.0, $water->fresh()->balance_due);
        $this->assertSame('Rejected', $pending->fresh()->status);
        $loan->update(['status' => 'Pending Review']);
        $data['gateway_reference'] = 'DIRECT-NOT-ACTIVE';
        $this->actingAs($owner)->post(route('payments.store'), $data)->assertSessionHasErrors('loan_id');
        $this->assertDatabaseMissing('payments', ['gateway_reference' => 'DIRECT-NOT-ACTIVE']);
    }

    public function test_direct_pending_payments_are_rechecked_when_admin_verifies_them(): void
    {
        [$owner, $loan] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $data = ['loan_id' => $loan->id, 'payment_type' => 'Loan', 'amount' => 200, 'payment_date' => '2026-09-10',
            'payment_channel' => 'Online', 'payment_method' => 'GCash', 'gateway_reference' => 'DIRECT-PENDING-1'];
        $this->actingAs($owner)->post(route('payments.store'), $data)->assertSessionHasNoErrors();
        $data['gateway_reference'] = 'DIRECT-PENDING-2';
        $this->post(route('payments.store'), $data)->assertSessionHasNoErrors();
        $first = Payment::where('gateway_reference', 'DIRECT-PENDING-1')->firstOrFail();
        $second = Payment::where('gateway_reference', 'DIRECT-PENDING-2')->firstOrFail();
        $this->actingAs($admin)->patch(route('payments.verify', $first))->assertSessionHasNoErrors();
        $this->patch(route('payments.verify', $second))->assertSessionHasErrors('amount');
        $this->assertSame('Pending', $second->fresh()->status);
        $this->assertSame(40.0, $loan->fresh()->balance_remaining);
        $this->assertSame(1, $loan->repayments()->count());
    }
}
