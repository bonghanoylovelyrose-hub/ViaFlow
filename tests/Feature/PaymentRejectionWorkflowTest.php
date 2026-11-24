<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRejectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_water_and_loan_rejections_require_and_display_saved_reasons_without_paying_bills(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Payment Consumer', 'account_number' => 'REJ-1', 'meter_number' => 'MT-REJ-1', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $loan = Loan::create(['consumer_id' => $consumer->id, 'amount' => 100, 'term_months' => 3, 'purpose' => 'Installation', 'status' => 'Approved', 'monthly_installment' => 33.33, 'applied_at' => '2026-08-01']);
        $water = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'prn' => 'REJ-WATER', 'amount' => 100, 'status' => 'Unpaid']);
        $loanBill = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Loan', 'loan_id' => $loan->id, 'billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'prn' => 'REJ-LOAN', 'amount' => 100, 'status' => 'Unpaid']);
        $installment = LoanInstallment::create(['loan_id' => $loan->id, 'bill_id' => $loanBill->id, 'installment_number' => 1, 'billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'required_amount' => 100, 'amount_paid' => 0, 'remaining_balance' => 100, 'status' => 'Billed']);
        $payments = [];
        foreach (['Water' => $water, 'Loan' => $loanBill] as $type => $bill) {
            $payments[$type] = Payment::create(['consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'payment_type' => $type, 'reference_number' => 'REJECT-'.$type, 'amount' => 25, 'payment_date' => '2026-09-15', 'payment_method' => 'GCash', 'payment_channel' => 'Online', 'status' => 'Pending']);
        }
        $this->actingAs($admin)->from('/payments')->patch(route('payments.reject', $payments['Water']), [])->assertSessionHasErrors('rejection_reason');
        $this->assertSame('Pending', $payments['Water']->fresh()->status);
        foreach ($payments as $type => $payment) {
            $reason = $type.' receipt is unclear';
            $this->actingAs($admin)->patch(route('payments.reject', $payment), ['rejection_reason' => $reason])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'Rejected', 'rejection_reason' => $reason]);
            $view = $type === 'Water' ? 'water-payments' : 'loan-payments';
            $adminPage = $this->actingAs($admin)->get(route('payments.index', ['view' => $view, 'status' => 'Rejected']))->assertOk()->assertSee('View Details')->assertSee($reason);
            $this->assertSame([$payment->id], $adminPage->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());
            $consumerPage = $this->actingAs($user)->get(route('payments.index', ['view' => $view, 'status' => 'Rejected']))->assertOk()->assertSee('Rejected')->assertSee('Rejection Reason:')->assertSee($reason);
            $this->assertSame([$payment->id], $consumerPage->viewData('payments')->getCollection()->pluck('id')->all());
        }
        $this->assertEquals(100, $water->fresh()->balance_due);
        $this->assertEquals(100, $loanBill->fresh()->balance_due);
        $this->assertEquals(100, $installment->fresh()->remaining_balance);
        $this->assertDatabaseCount('loan_repayments', 0);
        $this->actingAs($admin)->patch(route('payments.verify', $payments['Water']))->assertSessionHasErrors('status');
    }

    public function test_rejected_filter_excludes_other_payment_statuses_for_both_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Filter Consumer', 'account_number' => 'REJ-2', 'meter_number' => 'MT-REJ-2', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $bill = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'prn' => 'REJ-FILTER', 'amount' => 100, 'status' => 'Unpaid']);
        foreach (['Pending', 'Verified', 'Rejected'] as $status) {
            Payment::create(['consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'payment_type' => 'Water', 'reference_number' => 'FILTER-'.$status, 'amount' => 10, 'payment_date' => '2026-09-15', 'payment_method' => 'GCash', 'payment_channel' => 'Online', 'status' => $status, 'rejection_reason' => $status === 'Rejected' ? 'Incorrect receipt' : null]);
        }
        foreach ([$admin, $user] as $actor) {
            $this->actingAs($actor)->get(route('payments.index', ['view' => 'water-payments', 'status' => 'Rejected']))->assertOk()->assertSee('FILTER-Rejected')->assertDontSee('FILTER-Pending')->assertDontSee('FILTER-Verified');
            $this->actingAs($actor)->get(route('payments.index', ['view' => 'water-payments']))->assertOk()->assertSee('FILTER-Rejected')->assertSee('FILTER-Pending')->assertSee('FILTER-Verified');
        }
    }
}
