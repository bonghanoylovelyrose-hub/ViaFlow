<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentBillSettlementStatusTest extends TestCase
{
    use RefreshDatabase;

    public static function paymentTypes(): array
    {
        return [['Water'], ['Loan']];
    }

    #[DataProvider('paymentTypes')]
    public function test_verified_partial_and_full_payments_update_bill_and_both_views(string $type): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Status Test', 'account_number' => 'STATUS-1', 'meter_number' => 'MT-STATUS', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $loan = $type === 'Loan' ? Loan::create(['consumer_id' => $consumer->id, 'amount' => 280, 'term_months' => 3, 'monthly_installment' => 280, 'purpose' => 'Test', 'status' => 'Approved', 'applied_at' => now()]) : null;
        $bill = Bill::create(['consumer_id' => $consumer->id, 'bill_type' => $type, 'loan_id' => $loan?->id, 'billing_period' => now()->startOfMonth()->toDateString(), 'due_date' => now()->addMonth()->toDateString(), 'prn' => 'STATUS-PRN', 'amount' => 280, 'status' => 'Unpaid']);
        if ($loan) {
            LoanInstallment::create(['loan_id' => $loan->id, 'bill_id' => $bill->id, 'installment_number' => 1, 'billing_period' => now()->startOfMonth()->toDateString(), 'due_date' => now()->addMonth()->toDateString(), 'required_amount' => 280, 'amount_paid' => 0, 'remaining_balance' => 280, 'status' => 'Billed']);
        }
        $create = fn (string $ref, float $amount, string $status) => Payment::create(['consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'payment_type' => $type, 'reference_number' => $ref, 'amount' => $amount, 'payment_date' => now()->toDateString(), 'payment_method' => 'GCash', 'payment_channel' => 'Online', 'status' => $status]);
        $rejected = $create('STATUS-REJECTED', 30, 'Pending');
        $pending = $create('STATUS-PENDING', 200, 'Pending');
        $this->actingAs($admin)->patch(route('payments.reject', $rejected), ['rejection_reason' => 'Unclear proof'])->assertSessionHasNoErrors();
        $this->assertSame('Rejected', $rejected->fresh()->status);
        $this->assertSame('Unclear proof', $rejected->fresh()->rejection_reason);
        $this->assertSame('N/A', $rejected->fresh()->billStatusLabel());

        $this->assertSame('Unpaid', $bill->fresh()->paymentSettlementStatus());
        $this->assertEquals(280, $bill->fresh()->balance_due);
        $this->actingAs($admin)->patch(route('payments.verify', $pending))->assertSessionHasNoErrors();
        $this->assertSame('Verified', $pending->fresh()->status);
        $this->assertSame('Paid with Balance', $bill->fresh()->status);
        $this->assertSame('Paid with Balance', $bill->fresh()->paymentSettlementStatus());
        $this->assertEquals(80, $bill->fresh()->balance_due);
        $this->assertEquals(200, $bill->fresh()->verified_paid_amount);
        $this->assertEquals(80, $rejected->fresh()->remaining_balance_after_payment);
        $this->assertSame('N/A', $rejected->fresh()->billStatusLabel());

        $view = $type === 'Water' ? 'water-payments' : 'loan-payments';
        $adminResponse = $this->actingAs($admin)->get(route('payments.index', ['view' => $view, 'search' => 'STATUS-REJECTED']))->assertOk();
        $this->assertSame(1, preg_match('/<tr data-record-kind="payment" data-record-id="'.$rejected->id.'">([\s\S]*?)<\/tr>/', $adminResponse->getContent(), $adminMatch));
        $this->assertStringContainsString('Rejected', $adminMatch[1]);
        $this->assertStringContainsString('N/A', $adminMatch[1]);
        $this->assertStringNotContainsString('Paid with Balance', $adminMatch[1]);
        $this->assertStringContainsString('Unclear proof', $adminMatch[1]);

        $consumerResponse = $this->actingAs($user)->get(route('payments.index', ['view' => $view, 'search' => 'STATUS-REJECTED']))->assertOk();
        $consumerResponse->assertSee('Unclear proof')->assertSee('Rejected');
        $this->assertMatchesRegularExpression('/data-reference="STATUS-REJECTED"[\s\S]*?data-bill-status="N\/A"/', $consumerResponse->getContent());

        $this->actingAs($admin)->get(route('payments.index', ['view' => $view, 'search' => 'STATUS-PENDING']))->assertOk()->assertSee('Verified')->assertSee('Paid with Balance');
        $this->actingAs($user)->get(route('payments.index', ['view' => $view, 'search' => 'STATUS-PENDING']))->assertOk()->assertSee('Verified')->assertSee('Paid with Balance');

        $final = $create('STATUS-FINAL', 80, 'Pending');
        $this->assertSame('Paid with Balance', $bill->fresh()->paymentSettlementStatus());
        $this->actingAs($admin)->patch(route('payments.verify', $final))->assertSessionHasNoErrors();
        $this->assertSame('Verified', $final->fresh()->status);
        $this->assertSame('Paid', $bill->fresh()->status);
        $this->assertSame('Paid', $bill->fresh()->paymentSettlementStatus());
        $this->assertEquals(0, $bill->fresh()->balance_due);
        $this->assertSame('N/A', $rejected->fresh()->billStatusLabel());
        $this->assertEquals(0, $rejected->fresh()->remaining_balance_after_payment);
    }
}

