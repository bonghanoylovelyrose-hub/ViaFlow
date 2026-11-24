<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeparateWaterLoanBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_water_and_loan_payments_are_separately_allocated_and_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Separate Billing Consumer',
            'account_number' => 'SEP-1001',
            'meter_number' => 'SEP-M-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $consumerUser->id,
            'amount' => 300,
            'term_months' => 3,
            'purpose' => 'Installation materials',
            'status' => 'Approved',
            'monthly_installment' => 100,
            'applied_at' => '2026-08-01',
            'approved_at' => '2026-08-01',
            'start_billing_period' => '2026-09-01',
            'installment_schedule_snapshot' => [
                ['month' => 1, 'amount' => 100],
                ['month' => 2, 'amount' => 100],
                ['month' => 3, 'amount' => 100],
            ],
        ]);
        $waterBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'bill_type' => 'Water',
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'prn' => 'SEP-WATER-001',
            'amount' => 250,
            'status' => 'Unpaid',
        ]);

        $this->actingAs($admin)->post(route('billing.generate'), [
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'base_charge' => 150,
            'member_unit_rate' => 18,
            'non_member_unit_rate' => 20,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('billing.generate'), [
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'base_charge' => 150,
            'member_unit_rate' => 18,
            'non_member_unit_rate' => 20,
        ])->assertRedirect();

        $loanBill = Bill::query()->where('bill_type', 'Loan')->where('loan_id', $loan->id)->firstOrFail();
        $installment = LoanInstallment::query()->where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame($loanBill->id, $installment->bill_id);
        $this->assertSame(1, Bill::query()->where('bill_type', 'Loan')->where('loan_id', $loan->id)->count());
        $this->assertSame(2, Bill::query()->where('consumer_id', $consumer->id)->count());

        $waterToken = (string) Str::uuid();
        $this->actingAs($admin)->post(route('payments.store'), [
            'bill_id' => $waterBill->id,
            'payment_type' => 'Water',
            'submission_key' => $waterToken,
            'amount' => 50,
            'payment_date' => '2026-09-10',
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ])->assertRedirect();

        $this->assertSame(200.0, Bill::query()->findOrFail($waterBill->id)->balance_due);
        $this->assertSame(100.0, Bill::query()->findOrFail($loanBill->id)->balance_due);

        $loanToken = (string) Str::uuid();
        $loanPaymentData = [
            'bill_id' => $loanBill->id,
            'payment_type' => 'Loan',
            'submission_key' => $loanToken,
            'amount' => 40,
            'payment_date' => '2026-09-11',
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ];
        $this->actingAs($admin)->post(route('payments.store'), $loanPaymentData)->assertRedirect();
        $this->actingAs($admin)->post(route('payments.store'), $loanPaymentData)->assertSessionHasErrors('submission_key');

        $installment->refresh();
        $this->assertSame(40.0, (float) $installment->amount_paid);
        $this->assertSame(60.0, (float) $installment->remaining_balance);
        $this->assertSame(200.0, Bill::query()->findOrFail($waterBill->id)->balance_due);
        $this->assertSame(60.0, Bill::query()->findOrFail($loanBill->id)->balance_due);
        $this->assertSame('Water', Payment::query()->where('bill_id', $waterBill->id)->firstOrFail()->payment_type);
        $loanPayment = Payment::query()->where('bill_id', $loanBill->id)->firstOrFail();
        $this->assertSame('Loan', $loanPayment->payment_type);
        $this->actingAs($admin)->get(route('payments.receipt', $loanPayment))
            ->assertOk()->assertSee('Loan Payment')->assertSee('Loan Installment')->assertSee('#1');
        $waterTab = ['view' => 'water-payments'];
        $loanTab = ['view' => 'loan-payments'];
        $this->actingAs($consumerUser)->get(route('payments.index', $waterTab))
            ->assertOk()
            ->assertSee('action="'.route('payments.store', $waterTab).'"', false)
            ->assertSee('id="consumer_payment_bill_selector" name="bill_id"', false)
            ->assertDontSee('consumer_payment_type_selector', false)
            ->assertDontSee('name="payment_type"', false)
            ->assertSee('SEP-WATER-001')
            ->assertDontSee('loan_option_advance', false);
        $this->get(route('payments.index', $loanTab))
            ->assertOk()
            ->assertSee('action="'.route('payments.store', $loanTab).'"', false)
            ->assertSee('id="consumer_payment_bill_selector" name="loan_id"', false)
            ->assertDontSee('consumer_payment_type_selector', false)
            ->assertDontSee('name="payment_type"', false)
            ->assertSee('Select Active Loan')
            ->assertSee('Advance Payment')
            ->assertSee('Loan #'.$loan->id);
        $this->actingAs($admin)->get(route('payments.index'))
            ->assertOk()
            ->assertSee('payment_type_selector', false)
            ->assertSee('Water Bill')
            ->assertSee('Loan Payment')
            ->assertSee($waterBill->prn)
            ->assertDontSee($loanBill->prn)
            ->assertViewHas('paymentConsumers', fn ($directory) => $directory->firstWhere('id', $consumer->id)['loans'][0]['id'] === $loan->id);

        $this->actingAs($consumerUser)->get(route('billing.loan'))
            ->assertOk()->assertSee('Loan Installment Bills')->assertSee('Installment');
        $this->actingAs($consumerUser)->get(route('billing.index'))
            ->assertOk()->assertSee('Water Billing')->assertSee('SEP-WATER-001');

        $onlinePayment = [
            'amount' => 20,
            'payment_date' => '2026-09-12',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
        ];
        $this->post(route('payments.store', $waterTab), $onlinePayment + [
            'bill_id' => $waterBill->id, 'gateway_reference' => 'SEP-CONSUMER-WATER',
        ])->assertSessionHasNoErrors()->assertRedirect(route('payments.index', $waterTab));
        $this->post(route('payments.store', $loanTab), $onlinePayment + [
            'loan_id' => $loan->id, 'gateway_reference' => 'SEP-CONSUMER-LOAN',
        ])->assertSessionHasNoErrors()->assertRedirect(route('payments.index', $loanTab));
        $this->assertDatabaseHas('payments', [
            'gateway_reference' => 'SEP-CONSUMER-WATER', 'payment_type' => 'Water',
            'bill_id' => $waterBill->id, 'loan_id' => null, 'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('payments', [
            'gateway_reference' => 'SEP-CONSUMER-LOAN', 'payment_type' => 'Loan',
            'bill_id' => null, 'loan_id' => $loan->id, 'status' => 'Pending',
        ]);

        $this->post(route('payments.store', $waterTab), $onlinePayment + [
            'loan_id' => $loan->id, 'payment_type' => 'Loan', 'gateway_reference' => 'SEP-WRONG-TAB-LOAN',
        ])->assertSessionHasErrors('loan_id');
        $this->post(route('payments.store', $loanTab), $onlinePayment + [
            'bill_id' => $waterBill->id, 'payment_type' => 'Water', 'gateway_reference' => 'SEP-WRONG-TAB-WATER',
        ])->assertSessionHasErrors('bill_id');
        $this->assertDatabaseMissing('payments', ['gateway_reference' => 'SEP-WRONG-TAB-LOAN']);
        $this->assertDatabaseMissing('payments', ['gateway_reference' => 'SEP-WRONG-TAB-WATER']);

        $this->actingAs($admin);
        foreach (['SEP-CONSUMER-WATER', 'SEP-CONSUMER-LOAN'] as $reference) {
            $payment = Payment::query()->where('gateway_reference', $reference)->firstOrFail();
            $this->patch(route('payments.verify', $payment))->assertRedirect(route('payments.receipt', $payment));
            $this->assertSame('Verified', $payment->fresh()->status);
        }
        $this->assertSame(180.0, $waterBill->fresh()->balance_due);
        $this->assertSame(40.0, $loanBill->fresh()->balance_due);
    }

    public function test_rejected_or_pending_loan_payment_does_not_reduce_balance_and_mismatched_type_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Loan Payment Consumer',
            'account_number' => 'SEP-2001',
            'meter_number' => 'SEP-M-2001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'amount' => 100,
            'term_months' => 1,
            'purpose' => 'Installation materials',
            'status' => 'Approved',
            'monthly_installment' => 100,
            'applied_at' => '2026-08-01',
        ]);
        $loanBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'bill_type' => 'Loan',
            'loan_id' => $loan->id,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'prn' => 'SEP-LOAN-2001',
            'amount' => 100,
            'loan_installment_amount' => 100,
            'status' => 'Unpaid',
        ]);
        $installment = LoanInstallment::query()->create([
            'loan_id' => $loan->id,
            'bill_id' => $loanBill->id,
            'installment_number' => 1,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'required_amount' => 100,
            'amount_paid' => 0,
            'remaining_balance' => 100,
            'status' => 'Billed',
        ]);

        $this->actingAs($consumerUser)->post(route('payments.store'), [
            'bill_id' => $loanBill->id,
            'payment_type' => 'Loan',
            'amount' => 25,
            'payment_date' => '2026-09-10',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'SEP-REJECT-1',
        ])->assertRedirect();
        $pending = Payment::query()->where('gateway_reference', 'SEP-REJECT-1')->firstOrFail();
        $this->actingAs($admin)->patch(route('payments.reject', $pending), ['rejection_reason' => 'Receipt could not be verified.'])->assertRedirect();
        $this->assertSame(100.0, (float) $installment->fresh()->remaining_balance);

        $this->actingAs($consumerUser)->post(route('payments.store'), [
            'bill_id' => $loanBill->id,
            'payment_type' => 'Water',
            'amount' => 25,
            'payment_date' => '2026-09-10',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'SEP-MISMATCH-1',
        ])->assertSessionHasErrors('bill_id');
        $this->assertSame(100.0, (float) $installment->fresh()->remaining_balance);

        $this->actingAs($consumerUser)->post(route('payments.store'), [
            'bill_id' => $loanBill->id,
            'payment_type' => 'Loan',
            'amount' => 30,
            'payment_date' => '2026-09-12',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'SEP-VERIFY-1',
        ])->assertRedirect();
        $toVerify = Payment::query()->where('gateway_reference', 'SEP-VERIFY-1')->firstOrFail();
        $this->assertSame('Pending', $toVerify->status);
        $this->assertSame(100.0, (float) $installment->fresh()->remaining_balance);
        $this->actingAs($admin)->patch(route('payments.verify', $toVerify))->assertRedirect();
        $this->assertSame('Verified', $toVerify->fresh()->status);
        $this->assertSame(70.0, (float) $installment->fresh()->remaining_balance);
    }

    public function test_billing_submodules_are_available_and_loan_records_are_consumer_scoped(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'No Loan Consumer',
            'account_number' => 'NAV-1001',
            'meter_number' => 'NAV-M-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);
        $waterBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'bill_type' => 'Water',
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'prn' => 'NAV-WATER-BILL',
            'amount' => 125,
            'status' => 'Unpaid',
        ]);

        $otherConsumer = Consumer::query()->create([
            'full_name' => 'Other Loan Consumer',
            'account_number' => 'NAV-2001',
            'meter_number' => 'NAV-M-2001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);
        $loan = Loan::query()->create([
            'consumer_id' => $otherConsumer->id,
            'amount' => 75,
            'term_months' => 1,
            'purpose' => 'Installation materials',
            'status' => 'Approved',
            'monthly_installment' => 75,
            'applied_at' => '2026-08-01',
        ]);
        $bill = Bill::query()->create([
            'consumer_id' => $otherConsumer->id,
            'bill_type' => 'Loan',
            'loan_id' => $loan->id,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'prn' => 'NAV-OTHER-LOAN-BILL',
            'amount' => 75,
            'loan_installment_amount' => 75,
        ]);
        LoanInstallment::query()->create([
            'loan_id' => $loan->id,
            'bill_id' => $bill->id,
            'installment_number' => 1,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'required_amount' => 75,
            'amount_paid' => 0,
            'remaining_balance' => 75,
            'status' => 'Billed',
        ]);

        $this->actingAs($consumerUser)->get(route('billing.loan'))
            ->assertOk()
            ->assertSee('Water Billing')
            ->assertSee('Loan Billing')
            ->assertSee('₱0.00')
            ->assertSee('No loan bills generated yet.')
            ->assertDontSee('NAV-OTHER-LOAN-BILL');
        $this->actingAs($consumerUser)->get(route('billing.index'))
            ->assertOk()->assertSee('NAV-WATER-BILL')->assertDontSee('NAV-OTHER-LOAN-BILL');

        $this->actingAs($admin)->get(route('billing.index'))
            ->assertOk()->assertSee('Water Billing')->assertSee('Loan Billing')
            ->assertSee('data-billing-account="'.$consumer->id.'"', false)
            ->assertDontSee('data-billing-account="'.$otherConsumer->id.'"', false);
        $this->get(route('billing.monthly-history', $consumer))
            ->assertOk()->assertSee('NAV-WATER-BILL')->assertDontSee('NAV-OTHER-LOAN-BILL');
        $this->actingAs($admin)->get(route('billing.loan'))
            ->assertOk()->assertSee('NAV-OTHER-LOAN-BILL');
        $this->actingAs($consumerUser)->get(route('payments.index', ['view' => 'loan-payments']))
            ->assertOk()
            ->assertSee('No approved loans are currently available for payment.')
            ->assertDontSee('NAV-OTHER-LOAN-BILL');
        $this->get(route('payments.index', ['view' => 'water-payments']))
            ->assertOk()
            ->assertSee('NAV-WATER-BILL')
            ->assertDontSee('NAV-OTHER-LOAN-BILL');
    }
}
