<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptPrintLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receipt_uses_a_print_ready_document_without_dashboard_chrome(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$consumer, $payment] = $this->verifiedPayment($admin);

        $response = $this->actingAs($admin)->get(route('payments.receipt', $payment));

        $response->assertOk()
            ->assertSee('Digital Receipt')
            ->assertSee('Back to Payments')
            ->assertDontSee('Print Receipt')
            ->assertSee('Download Receipt')
            ->assertSee('href="'.route('payments.receipt.pdf', $payment).'" class="btn btn-primary"', false)
            ->assertSee('Via-Vida Waterworks Association')
            ->assertSee('Official consumer payment receipt')
            ->assertSee($payment->receipt_number)
            ->assertSee('Consumer Name')
            ->assertSee($consumer->full_name)
            ->assertSee('Account Number')
            ->assertSee($consumer->account_number)
            ->assertSee('Meter Number')
            ->assertSee($consumer->meter_number)
            ->assertSee('Verification')
            ->assertSee('Verification Status')
            ->assertSee('Payment Reference Number (PRN)')
            ->assertSee($payment->bill->prn)
            ->assertSee('System Payment Reference')
            ->assertSee($payment->reference_number)
            ->assertSee('Transaction/Reference Number')
            ->assertSee($payment->gateway_reference)
            ->assertSee('Payment Method')
            ->assertSee($payment->payment_method)
            ->assertSee('Amount Paid')
            ->assertSee('Previous Balance')
            ->assertSee('Remaining Balance After Payment')
            ->assertSee('id="digital-receipt"', false)
            ->assertSee('receipt-page', false)
            ->assertSee('receipt-document', false)
            ->assertSee('receipt-print-only', false)
            ->assertSee('receipt-screen-only', false)
            ->assertSee('@page', false)
            ->assertSee('size: A4 portrait', false)
            ->assertSee('@media print', false)
            ->assertSee('.receipt-page .topbar', false)
            ->assertSee('.receipt-page .content-wrapper > :not(.receipt-document)', false)
            ->assertSee('break-inside: avoid', false)
            ->assertSee('table-header-group', false);
    }

    public function test_consumer_receipt_uses_the_same_print_ready_layout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        [$consumer, $payment] = $this->verifiedPayment($admin, $consumerUser);

        $response = $this->actingAs($consumerUser)->get(route('payments.receipt', $payment));

        $response->assertOk()
            ->assertSee('Via-Vida Waterworks Association')
            ->assertSee($consumer->full_name)
            ->assertSee($payment->receipt_number)
            ->assertDontSee('Print Receipt')
            ->assertSee('Download Receipt')
            ->assertSee('receipt-document', false)
            ->assertSee('size: A4 portrait', false)
            ->assertSee('@media print', false);
    }

    public function test_consumer_receipt_shows_previous_and_remaining_balances_for_each_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Receipt Balance Consumer',
            'account_number' => 'RECEIPT-BAL-1001',
            'meter_number' => 'RECEIPT-BAL-M-1001',
            'service_address' => 'Purok 2-A, Via Vida, Panabo City',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PRN-RECEIPT-BAL-1001',
            'amount' => 1000.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $firstPayment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-RECEIPT-FIRST',
            'receipt_number' => 'OR-RECEIPT-FIRST',
            'amount' => 400.00,
            'payment_date' => now()->subDays(2)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'gateway_reference' => 'GC-RECEIPT-FIRST',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $secondPayment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-RECEIPT-SECOND',
            'receipt_number' => 'OR-RECEIPT-SECOND',
            'amount' => 600.00,
            'payment_date' => now()->subDay()->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'gateway_reference' => 'GC-RECEIPT-SECOND',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $firstReceipt = $this->actingAs($consumerUser)->get(route('payments.receipt', $firstPayment));
        $firstReceipt->assertOk()
            ->assertSeeInOrder(['Previous Balance', 'P 1,000.00', 'Remaining Balance After Payment', 'P 600.00'])
            ->assertSee($firstPayment->reference_number)
            ->assertDontSee($secondPayment->reference_number);

        $secondReceipt = $this->actingAs($consumerUser)->get(route('payments.receipt', $secondPayment));
        $secondReceipt->assertOk()
            ->assertSeeInOrder(['Previous Balance', 'P 600.00', 'Remaining Balance After Payment', 'P 0.00'])
            ->assertSee($secondPayment->reference_number)
            ->assertDontSee($firstPayment->reference_number);
    }

    public function test_pending_payment_receipt_is_not_accessible(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payment = $this->verifiedPayment($admin)[1];

        $payment->update([
            'status' => 'Pending',
            'receipt_number' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('payments.receipt', $payment))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('payments.receipt.pdf', $payment))
            ->assertNotFound();
    }

    public function test_consumer_cannot_view_another_consumers_receipt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'consumer']);
        $otherConsumer = User::factory()->create(['role' => 'consumer']);
        $payment = $this->verifiedPayment($admin, $owner)[1];

        $this->actingAs($otherConsumer)
            ->get(route('payments.receipt', $payment))
            ->assertForbidden();

        $this->actingAs($otherConsumer)
            ->get(route('payments.receipt.pdf', $payment))
            ->assertForbidden();
    }

    public function test_consumer_can_download_own_verified_receipt_as_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $payment = $this->verifiedPayment($admin, $consumerUser)[1];

        $download = $this->actingAs($consumerUser)
            ->get(route('payments.receipt.pdf', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('viaflow-payment-receipt-or-print-1001.pdf');

        $pdf = $download->getContent();
        $this->assertSame(1, preg_match('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s*\]/', $pdf, $page));
        $this->assertEqualsWithDelta(148, (float) $page[1] * 25.4 / 72, 0.02);
        $this->assertEqualsWithDelta(210, (float) $page[2] * 25.4 / 72, 0.02);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    /**
     * @return array{Consumer, Payment}
     */
    private function verifiedPayment(User $admin, ?User $consumerUser = null): array
    {
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser?->id,
            'full_name' => 'Print Layout Consumer',
            'account_number' => 'PRINT-1001',
            'meter_number' => 'MET-1001',
            'service_address' => 'Purok 2-A, Via Vida, Panabo City',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PRN-PRINT-1001',
            'amount' => 825.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $payment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-PRINT-1001',
            'receipt_number' => 'OR-PRINT-1001',
            'amount' => 825.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'gateway_reference' => 'GC-PRINT-1001',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'notes' => 'Paid in full at the counter.',
        ]);

        return [$consumer, $payment];
    }
}
