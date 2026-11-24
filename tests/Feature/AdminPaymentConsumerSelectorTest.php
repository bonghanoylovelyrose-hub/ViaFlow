<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPaymentConsumerSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_disconnected_consumer_can_pay_a_saved_loan_at_the_office_without_a_bill(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18));
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Disconnected Loan Consumer', 'account_number' => '19', 'meter_number' => 'MT-6666',
            'status' => 'Disconnected', 'membership_type' => 'Member',
        ]);
        $loan = $consumer->loans()->create([
            'status' => 'Ready for Scheduling', 'purpose' => 'Meter installation', 'amount' => 439,
            'monthly_installment' => 73.17, 'term_months' => 6, 'applied_at' => today(), 'approved_at' => today(),
            'start_billing_period' => '2026-12-01',
            'installment_schedule_snapshot' => array_map(fn (int $month) => ['month' => $month, 'amount' => $month === 6 ? 73.15 : 73.17], range(1, 6)),
        ]);
        $waterBill = $this->createBill($consumer, 'WATER-19', 90, '2026-11-01', '2026-11-30');
        $payload = [
            'consumer_id' => $consumer->id, 'loan_id' => $loan->id, 'payment_type' => 'Loan',
            'payment_channel' => 'Counter', 'payment_method' => 'Cash', 'payment_date' => today()->toDateString(),
        ];
        $this->actingAs($admin)->post(route('payments.store'), $payload + ['amount' => 146.34])->assertSessionHasNoErrors();
        $response = $this->get(route('payments.index', ['view' => 'loan-payments']))->assertOk()
            ->assertSee('Outstanding Loan Balance')->assertSee('Monthly Installment')->assertSee('Amount to Pay')
            ->assertSee('Remaining Loan Balance After Payment')->assertSee('id="payment_loan_selector" name="loan_id"', false);
        $account = $response->viewData('paymentConsumers')->firstWhere('id', $consumer->id);
        $this->assertSame('Inactive', $account['status_label']);
        $this->assertSame(90.0, $account['total_outstanding']);
        $this->assertSame(292.66, $account['outstanding_loan_balance']);
        $this->assertSame([
            'id' => $loan->id, 'balance' => 292.66, 'payment_limit' => 292.66,
            'monthly_installment' => 73.17, 'next_installment_due' => 73.17,
        ], $account['loans'][0]);
        $this->assertFalse($loan->bills()->exists());

        $other = $this->createConsumer();
        $this->post(route('payments.store'), ['consumer_id' => $other->id, 'amount' => 20] + $payload)->assertSessionHasErrors('consumer_id');
        $this->post(route('payments.store'), $payload + ['amount' => 292.67])->assertSessionHasErrors('amount');

        foreach ([[20, 272.66], [53.17, 219.49], [90, 129.49], [129.49, 0.00]] as [$amount, $remaining]) {
            $submission = $payload + ['amount' => $amount, 'submission_key' => (string) Str::uuid()];
            $response = $this->post(route('payments.store'), $submission)->assertSessionHasNoErrors();
            $payment = Payment::where('submission_key', $submission['submission_key'])->sole();
            $response->assertRedirect(route('payments.receipt', $payment));
            $this->assertSame('Verified', $payment->status);
            $this->assertSame('Loan', $payment->payment_type);
            $this->assertNull($payment->bill_id);
            $this->assertSame($remaining, $loan->fresh()->balance_remaining);
            $this->assertSame(90.0, $waterBill->fresh()->balance_due);
            $this->assertSame('Disconnected', $consumer->fresh()->status);
            $this->get(route('payments.receipt', $payment))->assertOk()
                ->assertViewHas('remainingBalance', $remaining)->assertSee('Loan Payment')->assertDontSee('WATER-19');
            $this->post(route('payments.store'), $submission)->assertSessionHasErrors('submission_key');
            $this->flushSession();
        }

        $this->assertDatabaseCount('payments', 5);
        $this->assertFalse($loan->bills()->exists());
        $this->assertFalse($consumer->reconnections()->exists());
    }

    public function test_admin_payment_form_lists_all_consumer_statuses_and_open_balances(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $active = Consumer::query()->create([
            'full_name' => 'Active Payment Consumer',
            'account_number' => 'PAY-SELECT-ACTIVE',
            'meter_number' => 'MTR-PAY-SELECT-ACTIVE',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $disconnected = Consumer::query()->create([
            'full_name' => 'Disconnected Payment Consumer',
            'account_number' => 'PAY-SELECT-INACTIVE',
            'meter_number' => 'MTR-PAY-SELECT-INACTIVE',
            'status' => 'Disconnected',
            'membership_type' => 'Non-Member',
        ]);

        $pending = Consumer::query()->create([
            'full_name' => 'Pending Payment Consumer',
            'account_number' => 'PAY-SELECT-PENDING',
            'meter_number' => 'MTR-PAY-SELECT-PENDING',
            'status' => 'Pending Verification',
            'membership_type' => 'Non-Member',
        ]);

        $this->createBill($active, 'PAY-SELECT-ACTIVE-OLD', 500.00, '2026-08-01', '2026-09-30');
        $this->createBill($active, 'PAY-SELECT-ACTIVE-NEW', 250.00, '2026-09-01', '2026-10-31');
        $this->createBill($disconnected, 'PAY-SELECT-INACTIVE-BILL', 300.00, '2026-09-01', '2026-10-31');

        $response = $this->actingAs($admin)->get(route('payments.index'));

        $response->assertOk()
            ->assertSee('id="payment_consumer_search"', false)
            ->assertSee('id="payment_consumer_total_outstanding"', false)
            ->assertSee('id="payment_consumer_balance_after"', false)
            ->assertSee('payment-amount-input', false)
            ->assertSee('appearance: textfield', false)
            ->assertSee('Must Pay in Full')
            ->assertDontSee('id="payment_consumer_open_bills"', false)
            ->assertDontSee('id="payment_bill_selection"', false)
            ->assertDontSee('allocation_scope', false)
            ->assertSee('No account is excluded from balance settlement.')
            ->assertSee('const paymentConsumers', false)
            ->assertSee('Active Payment Consumer')
            ->assertSee('Disconnected Payment Consumer');

        $paymentConsumers = $response->viewData('paymentConsumers');

        $this->assertCount(3, $paymentConsumers);

        $activeRow = $paymentConsumers->firstWhere('id', $active->id);
        $this->assertSame('Active', $activeRow['status_label']);
        $this->assertSame('Connected', $activeRow['status_sub_label']);
        $this->assertSame(750.0, $activeRow['total_outstanding']);
        $this->assertCount(2, $activeRow['bills']);
        $this->assertSame('PAY-SELECT-ACTIVE-OLD', $activeRow['bills'][0]['prn'], 'Bills are ordered oldest first for FIFO.');
        $this->assertSame('PAY-SELECT-ACTIVE-NEW', $activeRow['bills'][1]['prn']);

        $inactiveRow = $paymentConsumers->firstWhere('id', $disconnected->id);
        $this->assertSame('Inactive', $inactiveRow['status_label']);
        $this->assertSame('Disconnected', $inactiveRow['status_sub_label']);
        $this->assertSame(300.0, $inactiveRow['total_outstanding']);
        $this->assertCount(1, $inactiveRow['bills']);

        $pendingRow = $paymentConsumers->firstWhere('id', $pending->id);
        $this->assertSame('Pending', $pendingRow['status_label']);
        $this->assertSame('For Approval', $pendingRow['status_sub_label']);
        $this->assertSame(0.0, $pendingRow['total_outstanding']);
        $this->assertSame([], $pendingRow['bills']);
    }

    public function test_admin_payment_directory_flags_disconnection_notice_and_total_required_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'PAY-SELECT-NOTICE-MAY', 300.00, '2026-05-01', '2026-05-31');
        $this->createBill($consumer, 'PAY-SELECT-NOTICE-JUNE', 300.00, '2026-06-01', '2026-06-30');
        $this->createBill($consumer, 'PAY-SELECT-NOTICE-JULY', 300.00, '2026-07-01', '2026-07-31');

        $consumer->syncAccountStanding();

        $response = $this->actingAs($admin)->get(route('payments.index'));

        $response->assertOk();

        $row = $response->viewData('paymentConsumers')->firstWhere('id', $consumer->id);

        $this->assertTrue($row['has_disconnection_notice']);
        $this->assertSame(900.0, $row['total_outstanding']);
        $this->assertSame('Active', $row['status_label']);
        $this->assertSame('Connected', $row['status_sub_label']);
    }

    public function test_admin_payment_applies_to_all_outstanding_bills_fifo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $olderBill = $this->createBill($consumer, 'PAY-SELECT-FIFO-OLDER', 300.00, '2026-08-01', '2026-09-30');
        $newerBill = $this->createBill($consumer, 'PAY-SELECT-FIFO-NEWER', 500.00, '2026-09-01', '2026-10-31');

        $response = $this->actingAs($admin)->post(route('payments.store'), [
            'consumer_id' => $consumer->id,
            'bill_id' => $newerBill->id,
            'amount' => 800.00,
            'payment_date' => now()->toDateString(),
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ]);

        $payments = Payment::query()->orderBy('id')->get();

        $response->assertRedirect(route('payments.receipt', $payments->first()));

        $this->assertCount(2, $payments);
        $this->assertSame([300.00, 500.00], $payments->pluck('amount')->map(fn ($amount) => (float) $amount)->all());
        $this->assertBillBalance($olderBill, 0.00);
        $this->assertBillBalance($newerBill, 0.00);
    }

    public function test_inactive_account_must_pay_the_full_outstanding_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();
        $consumer->update(['status' => 'Disconnected']);

        $olderBill = $this->createBill($consumer, 'PAY-SELECT-INACTIVE-OLDER', 300.00, '2026-08-01', '2026-09-30');
        $newerBill = $this->createBill($consumer, 'PAY-SELECT-INACTIVE-NEWER', 500.00, '2026-09-01', '2026-10-31');

        $partialPayload = [
            'consumer_id' => $consumer->id,
            'bill_id' => $newerBill->id,
            'amount' => 799.99,
            'payment_date' => now()->toDateString(),
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ];

        $this->actingAs($admin)
            ->from(route('payments.index'))
            ->post(route('payments.store'), $partialPayload)
            ->assertRedirect(route('payments.index'))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
        $this->assertBillBalance($olderBill, 300.00);
        $this->assertBillBalance($newerBill, 500.00);

        $fullResponse = $this->actingAs($admin)->post(route('payments.store'), [
            ...$partialPayload,
            'amount' => 800.00,
        ]);

        $payments = Payment::query()->orderBy('id')->get();

        $fullResponse->assertRedirect(route('payments.receipt', $payments->first()));

        $this->assertCount(2, $payments);
        $this->assertSame([300.00, 500.00], $payments->pluck('amount')->map(fn ($amount) => (float) $amount)->all());
        $this->assertBillBalance($olderBill, 0.00);
        $this->assertBillBalance($newerBill, 0.00);
    }

    public function test_admin_payment_cannot_exceed_the_selected_account_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $bill = $this->createBill($consumer, 'PAY-SELECT-OVERPAY', 500.00, '2026-09-01', '2026-10-31');

        $this->actingAs($admin)
            ->from(route('payments.index'))
            ->post(route('payments.store'), [
                'consumer_id' => $consumer->id,
                'bill_id' => $bill->id,
                'amount' => 500.01,
                'payment_date' => now()->toDateString(),
                'payment_channel' => 'Counter',
                'payment_method' => 'Cash',
            ])
            ->assertRedirect(route('payments.index'))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
        $this->assertBillBalance($bill, 500.00);
    }

    public function test_active_account_can_still_make_a_partial_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $olderBill = $this->createBill($consumer, 'PAY-SELECT-PARTIAL-OLDER', 300.00, '2026-08-01', '2026-09-30');
        $newerBill = $this->createBill($consumer, 'PAY-SELECT-PARTIAL-NEWER', 500.00, '2026-09-01', '2026-10-31');

        $response = $this->actingAs($admin)->post(route('payments.store'), [
            'consumer_id' => $consumer->id,
            'bill_id' => $newerBill->id,
            'amount' => 200.00,
            'payment_date' => now()->toDateString(),
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ]);

        $payment = Payment::query()->firstOrFail();

        $response->assertRedirect(route('payments.receipt', $payment));

        $this->assertSame('Verified', $payment->status);
        $this->assertSame(200.00, (float) $payment->amount);
        $this->assertBillBalance($olderBill, 100.00);
        $this->assertBillBalance($newerBill, 500.00);
    }

    public function test_admin_payment_rejects_a_bill_that_does_not_belong_to_the_selected_consumer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $selectedConsumer = $this->createConsumer();
        $otherConsumer = $this->createConsumer();

        $selectedBill = $this->createBill($selectedConsumer, 'PAY-SELECT-MATCH', 500.00, '2026-09-01', '2026-10-31');
        $otherBill = $this->createBill($otherConsumer, 'PAY-SELECT-MISMATCH', 500.00, '2026-09-01', '2026-10-31');

        $this->actingAs($admin)
            ->from(route('payments.index'))
            ->post(route('payments.store'), [
                'consumer_id' => $selectedConsumer->id,
                'bill_id' => $otherBill->id,
                'amount' => 500.00,
                'payment_date' => now()->toDateString(),
                'payment_channel' => 'Counter',
                'payment_method' => 'Cash',
            ])
            ->assertRedirect(route('payments.index'))
            ->assertSessionHasErrors('consumer_id');

        $this->assertSame(0, Payment::query()->count());
        $this->assertBillBalance($selectedBill, 500.00);
        $this->assertBillBalance($otherBill, 500.00);
    }

    private function createConsumer(): Consumer
    {
        static $sequence = 0;
        $sequence++;

        return Consumer::query()->create([
            'full_name' => 'Payment Selector Consumer '.$sequence,
            'account_number' => 'PAY-SELECT-'.$sequence,
            'meter_number' => 'MTR-PAY-SELECT-'.$sequence,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
    }

    private function createBill(Consumer $consumer, string $prn, float $amount, string $period, string $dueDate): Bill
    {
        return Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => $period,
            'due_date' => $dueDate,
            'prn' => $prn,
            'amount' => $amount,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);
    }

    private function assertBillBalance(Bill $bill, float $expected): void
    {
        $bill->refresh()->loadSum([
            'payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified'),
        ], 'amount');

        $this->assertEquals($expected, $bill->balance_due);
    }
}
