<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanMaterialRate;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterReadingBillingComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meter_reading_uses_latest_billed_current_reading_for_next_cycle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer();

        MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => '2026-07-01',
            'previous_reading' => 50,
            'current_reading' => 50,
            'consumption' => 0,
            'status' => 'Encoded',
        ]);

        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => '2026-08-08',
                'current_reading' => 130,
                'status' => 'Encoded',
            ])
            ->assertRedirect(route('meter-readings.index'));

        $firstCycleReading = MeterReading::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('reading_date', '2026-08-08')
            ->firstOrFail();

        $this->assertEquals(50.00, (float) $firstCycleReading->previous_reading);
        $this->assertEquals(130.00, (float) $firstCycleReading->current_reading);
        $this->assertEquals(80.00, (float) $firstCycleReading->consumption);

        $this->actingAs($admin)
            ->post(route('billing.generate'), $this->billingPayload('2026-08-01'))
            ->assertRedirect(route('billing.index', ['period' => '2026-08']));

        $augustBill = Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('billing_period', '2026-08-01')
            ->firstOrFail();

        $this->assertEquals(80.00, (float) $augustBill->units_consumed);
        $this->assertEquals(20.00, (float) $augustBill->unit_rate);
        $this->assertEquals(1750.00, (float) $augustBill->amount);

        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => '2026-09-08',
                'current_reading' => 145,
                'status' => 'Encoded',
            ])
            ->assertRedirect(route('meter-readings.index'));

        $secondCycleReading = MeterReading::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('reading_date', '2026-09-08')
            ->firstOrFail();

        $this->assertEquals(130.00, (float) $secondCycleReading->previous_reading);
        $this->assertEquals(145.00, (float) $secondCycleReading->current_reading);
        $this->assertEquals(15.00, (float) $secondCycleReading->consumption);

        $this->actingAs($reader)
            ->from(route('meter-readings.index'))
            ->post(route('meter-readings.store'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => '2026-10-08',
                'current_reading' => 120,
                'status' => 'Encoded',
            ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHasErrors([
                'current_reading' => 'Current reading must be greater than the previous reading.',
            ]);

        $this->assertDatabaseMissing('meter_readings', [
            'consumer_id' => $consumer->id,
            'reading_date' => '2026-10-08',
        ]);
    }

    public function test_current_reading_must_exceed_previous_including_leading_zeros(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer();
        MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => '2026-08-08',
            'previous_reading' => 10,
            'current_reading' => 10,
            'consumption' => 0,
            'status' => 'Encoded',
        ]);
        foreach (['10.00', '5.00', '00005.00', '00010.00'] as $invalid) {
            $this->actingAs($reader)->post(route('meter-readings.store'), [
                'meter_number' => $consumer->meter_number,
                'reading_date' => '2026-09-08',
                'current_reading' => $invalid,
            ])->assertSessionHasErrors(['current_reading' => 'Current reading must be greater than the previous reading.']);
            $this->assertFalse(MeterReading::query()->where('consumer_id', $consumer->id)->whereDate('reading_date', '2026-09-08')->exists());
        }
        $this->actingAs($reader)->post(route('meter-readings.store'), [
            'meter_number' => $consumer->meter_number,
            'reading_date' => '2026-09-08',
            'current_reading' => '10.001',
        ])->assertSessionHasErrors('current_reading');
        $this->assertFalse(MeterReading::query()->where('consumer_id', $consumer->id)->whereDate('reading_date', '2026-09-08')->exists());

        $this->actingAs($reader)->post(route('meter-readings.store'), [
            'meter_number' => $consumer->meter_number,
            'reading_date' => '2026-09-08',
            'current_reading' => '00010.01',
        ])->assertSessionHasNoErrors();
        $reading = MeterReading::query()->where('consumer_id', $consumer->id)->whereDate('reading_date', '2026-09-08')->firstOrFail();
        $this->assertEquals(10.01, (float) $reading->current_reading);
        $this->assertEquals(0.01, (float) $reading->consumption);
    }

    public function test_generating_same_billing_month_does_not_create_duplicate_bill(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer();

        MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => '2026-08-08',
            'previous_reading' => 50,
            'current_reading' => 130,
            'consumption' => 80,
            'status' => 'Encoded',
        ]);

        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-08-01'));
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-08-01'));

        $this->assertSame(1, Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('billing_period', '2026-08-01')
            ->count());
    }

    public function test_admin_initializes_pending_loan_and_bills_monthly_installments_once_per_cycle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['membership_type' => 'Member']);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $admin->id,
            'amount' => 0,
            'term_months' => 3,
            'purpose' => 'Installation materials',
            'status' => 'Pending Review',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 0,
            'applied_at' => '2026-08-01',
        ]);

        $this->createReading($consumer, $reader, '2026-08-08', 100);

        $this->actingAs($admin)
            ->post(route('billing.generate'), $this->billingPayload('2026-08-01'))
            ->assertSessionHasErrors("loan_amounts.{$loan->id}");

        $this->actingAs($admin)
            ->post(route('billing.generate'), $this->billingPayload('2026-08-01', [
                'loan_amounts' => [$loan->id => 500],
            ]))
            ->assertRedirect(route('billing.index', ['period' => '2026-08']));

        $loan->refresh();
        $this->assertEquals(500.00, (float) $loan->amount);
        $this->assertEquals(3, (int) $loan->term_months);
        $this->assertEquals(166.67, (float) $loan->monthly_installment);
        $this->assertEquals('2026-09-01', $loan->start_billing_period->toDateString());
        $this->assertSame('Approved', $loan->status);

        $augustBill = $this->billFor($consumer, '2026-08-01');
        $this->assertEquals(0.00, (float) $augustBill->loan_installment_amount);
        $this->assertNull($augustBill->loan_id);

        $this->payBill($augustBill, $admin);
        $this->createReading($consumer, $reader, '2026-09-08', 115);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-09-01'));
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-09-01'));

        $septemberWaterBill = $this->billFor($consumer, '2026-09-01');
        $septemberLoanBill = Bill::query()->where('loan_id', $loan->id)->where('bill_type', 'Loan')->whereDate('billing_period', '2026-09-01')->firstOrFail();
        $this->assertNull($septemberWaterBill->loan_id);
        $this->assertSame('Water', $septemberWaterBill->bill_type);
        $this->assertSame('Loan', $septemberLoanBill->bill_type);
        $this->assertEquals(166.67, (float) $septemberLoanBill->amount);
        $this->assertSame(1, Bill::query()->where('loan_id', $loan->id)->whereDate('billing_period', '2026-09-01')->count());

        $this->payBill($septemberWaterBill, $admin);
        $this->payBill($septemberLoanBill, $admin);
        $this->createReading($consumer, $reader, '2026-10-08', 130);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-10-01'));
        $octoberWaterBill = $this->billFor($consumer, '2026-10-01');
        $octoberLoanBill = Bill::query()->where('loan_id', $loan->id)->where('bill_type', 'Loan')->whereDate('billing_period', '2026-10-01')->firstOrFail();
        $this->assertEquals(0.00, (float) $octoberWaterBill->loan_installment_amount);
        $this->assertEquals(166.67, (float) $octoberLoanBill->amount);
        $this->payBill($octoberWaterBill, $admin);
        $this->payBill($octoberLoanBill, $admin);
        $this->createReading($consumer, $reader, '2026-11-08', 145);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-11-01'));
        $novemberWaterBill = $this->billFor($consumer, '2026-11-01');
        $novemberLoanBill = Bill::query()->where('loan_id', $loan->id)->where('bill_type', 'Loan')->whereDate('billing_period', '2026-11-01')->firstOrFail();
        $this->assertEquals(0.00, (float) $novemberWaterBill->loan_installment_amount);
        $this->assertEquals(166.66, (float) $novemberLoanBill->amount);
        $this->payBill($novemberWaterBill, $admin);
        $this->payBill($novemberLoanBill, $admin);
        $this->createReading($consumer, $reader, '2026-12-08', 160);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-12-01'));
        $decemberBill = $this->billFor($consumer, '2026-12-01');
        $this->assertEquals(0.00, (float) $decemberBill->loan_installment_amount);

        $loan->refresh()->loadCount(['bills' => fn ($query) => $query->where('loan_installment_amount', '>', 0)]);
        $this->assertEquals(3, $loan->installments_billed);
        $this->assertEquals(0, $loan->remaining_installments);
        $this->assertEquals(500.00, $loan->amount_billed);
        $this->assertEquals(500.00, $loan->total_repaid);
        $this->assertEquals(0.00, $loan->unpaid_loan_balance);
        $this->assertSame('Completed', $loan->status);
    }

    public function test_loan_blocks_new_application_until_all_billed_installments_are_fully_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer([
            'user_id' => $consumerUser->id,
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
            'membership_started_at' => '2026-01-01',
        ]);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $consumerUser->id,
            'amount' => 0,
            'term_months' => 3,
            'purpose' => 'Installation materials',
            'status' => 'Pending Review',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 0,
            'applied_at' => '2026-08-01',
        ]);
        $loan->forceFill([
            'status' => 'Approved',
            'amount' => 500,
            'monthly_installment' => 166.67,
            'start_billing_period' => '2026-08-01',
            'installment_schedule_snapshot' => [
                ['month' => 1, 'amount' => 166.67],
                ['month' => 2, 'amount' => 166.67],
                ['month' => 3, 'amount' => 166.66],
            ],
        ])->save();
        $this->createReading($consumer, $reader, '2026-08-08', 100);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-08-01'));
        $waterBill = $this->billFor($consumer, '2026-08-01');
        $loanBill = Bill::query()->where('loan_id', $loan->id)->where('bill_type', 'Loan')->firstOrFail();

        $this->payBill($waterBill, $admin);
        $this->assertEquals(0.00, $loan->fresh()->total_repaid, 'Settling a water bill must not change loan repayment totals.');
        $this->actingAs($admin)->post(route('payments.store'), $this->paymentPayload($loanBill, 80))->assertRedirect();
        $this->assertEquals(80.00, $loan->fresh()->total_repaid);
        $this->assertEquals(86.67, (float) $loanBill->fresh()->balance_due);
        $this->assertEquals(0.00, $waterBill->fresh()->balance_due);
    }

    public function test_unpaid_loan_installments_stay_separate_and_allocate_oldest_first(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 8)->startOfDay());

        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer([
            'user_id' => $consumerUser->id,
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
            'membership_started_at' => '2026-01-01',
        ]);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $consumerUser->id,
            'amount' => 500,
            'term_months' => 3,
            'purpose' => 'Installation materials',
            'status' => 'Approved',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 166.67,
            'applied_at' => '2026-08-01',
            'approved_at' => '2026-08-01',
            'start_billing_period' => '2026-08-01',
            'installment_schedule_snapshot' => [
                ['month' => 1, 'amount' => 166.67],
                ['month' => 2, 'amount' => 166.67],
                ['month' => 3, 'amount' => 166.66],
            ],
        ]);

        $this->createReading($consumer, $reader, '2026-08-08', 1);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-08-01', ['due_date' => '2026-09-15']));
        $waterBill = $this->billFor($consumer, '2026-08-01');
        $monthOne = LoanInstallment::query()->where('loan_id', $loan->id)->where('installment_number', 1)->firstOrFail();
        $loanBillOne = Bill::query()->findOrFail($monthOne->bill_id);
        $this->assertSame('Water', $waterBill->bill_type);
        $this->assertSame('Loan', $loanBillOne->bill_type);
        $this->assertNotSame($waterBill->id, $loanBillOne->id);

        $this->travelTo(now()->setDate(2026, 9, 8)->startOfDay());
        $this->createReading($consumer, $reader, '2026-09-08', 2);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-09-01', ['due_date' => '2026-09-30']));
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-09-01', ['due_date' => '2026-09-30']));
        $monthTwo = LoanInstallment::query()->where('loan_id', $loan->id)->where('installment_number', 2)->firstOrFail();
        $loanBillTwo = Bill::query()->findOrFail($monthTwo->bill_id);
        $this->assertSame(2, LoanInstallment::query()->where('loan_id', $loan->id)->count());
        $this->assertSame(1, Bill::query()->where('bill_type', 'Loan')->where('loan_id', $loan->id)->whereDate('billing_period', '2026-09-01')->count());
        $this->assertSame('Loan', $loanBillTwo->bill_type);
        $this->assertSame('2026-09-15', $monthOne->fresh()->due_date->toDateString());
        $this->assertSame('Billed', $monthOne->fresh()->status);
        $this->assertEquals(166.67, (float) $loanBillTwo->amount);

        $this->actingAs($consumerUser)->get(route('payments.index', ['view' => 'loan-payments']))
            ->assertOk()
            ->assertViewHas('payments', fn ($payments): bool => $payments->total() === 0)
            ->assertViewHas('paymentFormBills', fn ($bills): bool => $bills->contains(fn (Bill $bill): bool => $bill->bill_type === 'Loan' && $bill->loan_id === $loan->id));

        $this->travelTo(now()->setDate(2026, 9, 15)->startOfDay());
        $waterBalanceBeforeLoanPayment = (float) $waterBill->fresh()->balance_due;
        $this->actingAs($admin)->post(route('payments.store'), $this->paymentPayload($loanBillOne, 80))->assertRedirect();
        $monthOne->refresh();
        $monthTwo->refresh();
        $this->assertSame('Partially Paid', $monthOne->status);
        $this->assertEquals(80.00, (float) $monthOne->amount_paid);
        $this->assertEquals(86.67, (float) $monthOne->remaining_balance);
        $this->assertEquals(0.00, (float) $monthTwo->amount_paid);
        $this->assertEquals(166.67, (float) $monthTwo->remaining_balance);
        $this->assertSame($waterBalanceBeforeLoanPayment, (float) $waterBill->fresh()->balance_due);

        $this->travelTo(now()->setDate(2026, 10, 8)->startOfDay());
        $this->createReading($consumer, $reader, '2026-10-08', 3);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-10-01', ['due_date' => '2026-10-31']));
        $monthTwo->refresh();
        $monthThree = LoanInstallment::query()->where('loan_id', $loan->id)->where('installment_number', 3)->firstOrFail();
        $this->assertSame('2026-09-30', $monthTwo->due_date->toDateString());
        $this->assertSame('2026-11-15', $monthThree->due_date->toDateString());
        $this->assertSame('Overdue', $monthTwo->status);
        $this->assertSame('Billed', $monthThree->status);
        $this->assertEquals(3, LoanInstallment::query()->where('loan_id', $loan->id)->count());
        $loan->refresh();
        $this->assertEquals(3, $loan->installments_billed);
        $this->assertSame('Approved', $loan->status);
    }

    public function test_manual_loan_repayment_updates_oldest_unpaid_installment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['membership_type' => 'Member']);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $admin->id,
            'amount' => 500,
            'term_months' => 3,
            'purpose' => 'Installation materials',
            'status' => 'Approved',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 166.67,
            'start_billing_period' => '2026-08-01',
            'applied_at' => '2026-07-01',
            'approved_at' => '2026-07-01',
            'installment_schedule_snapshot' => [
                ['month' => 1, 'amount' => 166.67],
                ['month' => 2, 'amount' => 166.67],
                ['month' => 3, 'amount' => 166.66],
            ],
        ]);

        $this->createReading($consumer, $reader, '2026-08-08', 1);
        $this->actingAs($admin)->post(route('billing.generate'), $this->billingPayload('2026-08-01'));

        $monthOne = LoanInstallment::query()
            ->where('loan_id', $loan->id)
            ->where('installment_number', 1)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('loans.repayments.store', $loan), [
                'amount' => 100,
                'payment_date' => '2026-08-31',
                'notes' => 'Manual installment repayment',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $monthOne->refresh();
        $this->assertSame('Partially Paid', $monthOne->status);
        $this->assertEquals(100.00, (float) $monthOne->amount_paid);
        $this->assertEquals(66.67, (float) $monthOne->remaining_balance);

        $this->actingAs($admin)
            ->post(route('loans.repayments.store', $loan), [
                'amount' => 66.67,
                'payment_date' => '2026-09-01',
                'notes' => 'Manual installment completion',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $monthOne->refresh();
        $loan->refresh();
        $this->assertSame('Paid', $monthOne->status);
        $this->assertEquals(166.67, (float) $monthOne->amount_paid);
        $this->assertEquals(0.00, (float) $monthOne->remaining_balance);
        $this->assertEquals(166.67, $loan->total_repaid);
        $this->assertSame('Approved', $loan->status);
    }

    public function test_generate_billing_rejects_invalid_pending_loan_term(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['membership_type' => 'Member']);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $admin->id,
            'amount' => 0,
            'term_months' => 5,
            'purpose' => 'Invalid term setup',
            'status' => 'Pending Review',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 0,
            'applied_at' => '2026-08-01',
        ]);

        $this->createReading($consumer, $reader, '2026-08-08', 100);

        $this->actingAs($admin)
            ->post(route('billing.generate'), $this->billingPayload('2026-08-01', [
                'loan_amounts' => [$loan->id => 500],
            ]))
            ->assertSessionHasErrors("loan_amounts.{$loan->id}");
    }

    public function test_admin_generate_billing_rejects_second_loan_initialization_for_active_unpaid_loan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer(['membership_type' => 'Member']);
        Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $admin->id,
            'amount' => 500,
            'term_months' => 3,
            'purpose' => 'Existing loan',
            'status' => 'Approved',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 166.67,
            'start_billing_period' => '2026-08-01',
            'applied_at' => '2026-07-01',
            'approved_at' => '2026-07-01',
        ]);
        $pendingLoan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $admin->id,
            'amount' => 0,
            'term_months' => 6,
            'purpose' => 'Second loan setup',
            'status' => 'Pending Review',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 0,
            'applied_at' => '2026-08-01',
        ]);
        $this->createReading($consumer, $reader, '2026-08-08', 100);

        $this->actingAs($admin)
            ->post(route('billing.generate'), $this->billingPayload('2026-08-01', [
                'loan_amounts' => [$pendingLoan->id => 300],
            ]))
            ->assertSessionHasErrors("loan_amounts.{$pendingLoan->id}");

        $this->assertEquals(0.00, (float) $pendingLoan->refresh()->amount);
    }

    public function test_loan_installment_rounding_adjusts_only_final_installment(): void
    {
        $this->assertSame([166.67, 166.67, 166.66], $this->installmentsFor(500, 3));
        $this->assertSame([83.33, 83.33, 83.33, 83.33, 83.33, 83.35], $this->installmentsFor(500, 6));
        $this->assertSame([41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.63], $this->installmentsFor(500, 12));
    }

    private function installmentsFor(float $amount, int $termMonths): array
    {
        $loan = new Loan([
            'amount' => $amount,
            'term_months' => $termMonths,
            'monthly_installment' => round($amount / $termMonths, 2),
        ]);

        return collect(range(1, $termMonths))
            ->map(fn (int $installmentNumber) => $loan->installmentAmountForNumber($installmentNumber))
            ->all();
    }

    public function test_admin_generate_billing_displays_pending_loan_amount_setup_preview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer(['membership_type' => 'Member']);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $admin->id,
            'amount' => 0,
            'term_months' => 12,
            'purpose' => 'Installation materials',
            'status' => 'Pending Review',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 0,
            'applied_at' => '2026-08-01',
        ]);

        $response = $this->actingAs($admin)->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Loan Setup');
        $response->assertSee('Loan Amount');
        $response->assertSee('name="loan_amounts['.$loan->id.']"', false);
        $response->assertSee('Repayment Term: 12 months');
        $response->assertSee('Monthly Loan Installment:');
    }

    private function createConsumer(array $overrides = []): Consumer
    {
        return Consumer::query()->create(array_merge([
            'full_name' => 'Test Consumer',
            'account_number' => 'TC-1001',
            'meter_number' => 'MT-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $overrides));
    }

    private function createReading(Consumer $consumer, User $reader, string $readingDate, float $currentReading): MeterReading
    {
        return MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => $readingDate,
            'previous_reading' => 0,
            'current_reading' => $currentReading,
            'consumption' => $currentReading,
            'status' => 'Encoded',
        ]);
    }

    private function billFor(Consumer $consumer, string $billingPeriod): Bill
    {
        return Bill::query()
            ->where('consumer_id', $consumer->id)
            ->where('bill_type', 'Water')
            ->whereDate('billing_period', $billingPeriod)
            ->firstOrFail();
    }

    private function payBill(Bill $bill, User $admin): void
    {
        $payment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $bill->consumer_id,
            'payment_type' => $bill->bill_type,
            'reference_number' => 'PAY-'.$bill->id,
            'amount' => $bill->amount,
            'payment_date' => $bill->due_date,
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $bill->refresh()->syncPaymentStatus();
        if ($bill->bill_type === 'Loan') {
            app(\App\Services\PaymentAllocationService::class)->allocateVerifiedLoanPayment($payment, $admin);
        }
    }

    private function paymentPayload(Bill $bill, float $amount): array
    {
        return [
            'bill_id' => $bill->id,
            'amount' => $amount,
            'payment_date' => $bill->due_date->toDateString(),
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
            'notes' => 'Workflow payment',
        ];
    }

    private function billingPayload(string $billingPeriod, array $overrides = []): array
    {
        return array_merge([
            'billing_period' => $billingPeriod,
            'due_date' => '2026-08-31',
            'base_charge' => 150.00,
            'member_unit_rate' => 18.00,
            'non_member_unit_rate' => 20.00,
        ], $overrides);
    }
}
