<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanBillingRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_recovers_missing_scheduled_installments_and_keeps_accounts_scoped(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstUser = User::factory()->create(['role' => 'consumer']);
        $secondUser = User::factory()->create(['role' => 'consumer']);
        $firstConsumer = $this->consumer($firstUser, 'REC-1001');
        $secondConsumer = $this->consumer($secondUser, 'REC-1002');
        $start = today()->startOfMonth()->subMonths(2);

        $firstLoan = $this->activatedLoan($firstConsumer, $firstUser, $start, 300);
        $secondLoan = $this->activatedLoan($secondConsumer, $secondUser, $start, 600);
        LoanRepayment::query()->create([
            'loan_id' => $firstLoan->id,
            'amount' => 25,
            'payment_date' => $start->copy()->addDays(10)->toDateString(),
            'notes' => 'Recorded office repayment',
        ]);

        // Neither approval alone nor an unactivated schedule creates charges.
        Loan::query()->create([
            'consumer_id' => $firstConsumer->id,
            'applied_by_user_id' => $firstUser->id,
            'amount' => 300,
            'term_months' => 3,
            'purpose' => 'Not activated',
            'status' => 'Approved',
            'applied_at' => today()->toDateString(),
            'installment_schedule_snapshot' => [
                ['month' => 1, 'amount' => 100], ['month' => 2, 'amount' => 100], ['month' => 3, 'amount' => 100],
            ],
        ]);
        $missingScheduleLoan = Loan::query()->create([
            'consumer_id' => $firstConsumer->id,
            'applied_by_user_id' => $firstUser->id,
            'amount' => 300,
            'term_months' => 3,
            'purpose' => 'Activated without saved repayment details',
            'status' => 'Approved',
            'applied_at' => today()->toDateString(),
            'start_billing_period' => $start->toDateString(),
            'next_due_date' => $start->copy()->endOfMonth()->toDateString(),
        ]);
        Loan::query()->create([
            'consumer_id' => $firstConsumer->id,
            'applied_by_user_id' => $firstUser->id,
            'amount' => 300,
            'term_months' => 3,
            'purpose' => 'Not approved',
            'status' => 'Pending Review',
            'applied_at' => today()->toDateString(),
            'start_billing_period' => $start->toDateString(),
            'installment_schedule_snapshot' => [
                ['month' => 1, 'amount' => 100], ['month' => 2, 'amount' => 100], ['month' => 3, 'amount' => 100],
            ],
        ]);

        $this->actingAs($admin)->get(route('billing.loan'))
            ->assertOk()
            ->assertViewHas('loanBillingSummary', function (array $summary): bool {
                return $summary['bill_count'] === 6
                    && $summary['total_due'] === 900.0
                    && $summary['total_paid'] === 25.0
                    && $summary['remaining_balance'] === 875.0;
            });

        $firstInstallments = LoanInstallment::query()->where('loan_id', $firstLoan->id)->orderBy('installment_number')->get();
        $this->assertCount(3, $firstInstallments);
        $this->assertSame(25.0, (float) $firstInstallments[0]->amount_paid);
        $this->assertSame(75.0, (float) $firstInstallments[0]->remaining_balance);
        $this->assertSame(100.0, (float) $firstInstallments[1]->remaining_balance);
        $this->assertSame($start->copy()->endOfMonth()->toDateString(), $firstInstallments[0]->due_date->toDateString());

        $this->actingAs($admin)->get(route('billing.loan'));
        $this->assertSame(6, Bill::query()->where('bill_type', 'Loan')->count());
        $this->assertSame(0, Bill::query()->where('loan_id', $missingScheduleLoan->id)->count());
        $this->actingAs($firstUser)->get(route('billing.loan'))
            ->assertOk()
            ->assertViewHas('loanBillingSummary', fn (array $summary): bool => $summary['bill_count'] === 3)
            ->assertViewHas('loanBills', fn ($bills): bool => $bills->getCollection()->pluck('loan_id')->unique()->all() === [$firstLoan->id]);
        $this->assertSame(3, LoanInstallment::query()->where('loan_id', $secondLoan->id)->count());
    }

    public function test_legacy_loan_bill_without_installment_row_is_shown_and_linked_without_changing_bill_data(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->consumer($user, 'LEG-1001');
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $user->id,
            'amount' => 240,
            'term_months' => 3,
            'purpose' => 'Legacy loan',
            'status' => 'Approved',
            'applied_at' => '2026-08-01',
        ]);
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'bill_type' => 'Loan',
            'loan_id' => $loan->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-25',
            'prn' => 'LEGACY-LOAN-1',
            'amount' => 80,
            'loan_installment_amount' => 80,
            'status' => 'Unpaid',
        ]);

        $this->actingAs($user)->get(route('billing.loan'))
            ->assertOk()
            ->assertSee('LEGACY-LOAN-1')
            ->assertSee('Aug 25, 2026');

        $this->get(route('billing.loan', ['status' => 'Upcoming']))
            ->assertOk()
            ->assertSee('No bills match these filters.');

        $installment = LoanInstallment::query()->where('bill_id', $bill->id)->firstOrFail();
        $this->assertSame(80.0, (float) $installment->required_amount);
        $this->assertSame(80.0, (float) $installment->remaining_balance);
        $this->assertSame('2026-08-25', $installment->due_date->toDateString());
        $this->assertSame(80.0, (float) $bill->fresh()->amount);
    }

    public function test_no_bills_state_is_distinct_from_a_filter_with_no_matches(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $this->consumer($user, 'EMPTY-1001');

        $this->actingAs($user)->get(route('billing.loan'))
            ->assertOk()
            ->assertSee('No loan bills generated yet');
    }

    public function test_payment_module_syncs_saved_installments_and_posts_only_to_the_loan_bill(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->consumer($consumerUser, 'PAY-LOAN-1001');
        $start = today()->startOfMonth()->subMonth();
        $loan = $this->activatedLoan($consumer, $consumerUser, $start, 300);
        $waterBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'bill_type' => 'Water',
            'billing_period' => $start->toDateString(),
            'due_date' => $start->copy()->endOfMonth()->toDateString(),
            'prn' => 'PAY-LOAN-WATER',
            'amount' => 150,
            'status' => 'Unpaid',
        ]);

        $this->actingAs($admin)->get(route('payments.index', ['view' => 'loan-payments']))
            ->assertOk()
            ->assertViewHas('paymentFormBills', fn ($bills): bool => $bills->contains(fn (Bill $bill): bool => $bill->bill_type === 'Loan' && $bill->loan_id === $loan->id));

        $installment = LoanInstallment::query()->where('loan_id', $loan->id)->where('installment_number', 1)->firstOrFail();
        $bill = Bill::query()->findOrFail($installment->bill_id);
        $this->actingAs($admin)->post(route('payments.store'), [
            'bill_id' => $bill->id,
            'payment_type' => 'Loan',
            'consumer_id' => $consumer->id,
            'submission_key' => (string) Str::uuid(),
            'amount' => 40,
            'payment_date' => today()->toDateString(),
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ])->assertRedirect();

        $installment->refresh();
        $this->assertSame(40.0, (float) $installment->amount_paid);
        $this->assertSame(60.0, (float) $installment->remaining_balance);
        $this->assertSame(150.0, (float) $waterBill->fresh()->balance_due);
        $this->assertSame('Loan', Payment::query()->where('bill_id', $bill->id)->firstOrFail()->payment_type);

        $this->actingAs($consumerUser)->get(route('billing.loan'))
            ->assertOk()
            ->assertViewHas('loanBillingSummary', fn (array $summary): bool => $summary['bill_count'] === 3 && $summary['total_paid'] === 40.0 && $summary['remaining_balance'] === 260.0);
    }

    public function test_rejected_scheduled_application_and_incomplete_approved_loan_do_not_become_bills(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->consumer($consumerUser, 'BLOCKED-LOAN-1001');
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $consumerUser->id,
            'amount' => 500,
            'term_months' => 6,
            'purpose' => 'Scheduled but not approved',
            'status' => 'Pending Review',
            'review_status' => 'Installation Scheduled',
            'installation_status' => 'Scheduled',
            'installation_date' => today()->toDateString(),
            'installation_time' => '09:00',
            'applied_at' => today()->toDateString(),
            'installment_schedule_snapshot' => collect(range(1, 6))->map(fn ($month) => ['month' => $month, 'amount' => 500 / 6])->all(),
        ]);
        LoanDssEvaluation::query()->create([
            'loan_id' => $loan->id,
            'recommendation' => 'Rejected',
            'evaluation_type' => 'initial',
            'criteria_version' => 'test',
            'evidence_hash' => hash('sha256', 'test-evidence'),
            'evaluated_values' => [],
            'failed_criteria' => [],
            'missing_information' => [],
            'evaluated_at' => now(),
        ]);
        $incomplete = Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $consumerUser->id,
            'amount' => 0,
            'term_months' => 6,
            'purpose' => 'Approved but missing repayment details',
            'status' => 'Approved',
            'applied_at' => today()->toDateString(),
        ]);

        $this->actingAs($admin)->get(route('loans.index', ['loan' => $loan->id]))
            ->assertOk()
            ->assertSee('DSS Review Status')
            ->assertSeeInOrder(['Approval or Rejection Status', 'Rejected', 'Installation Information', 'Installation Status', 'Scheduled'])
            ->assertDontSee('Assign Meter &amp; Schedule Installation', false);

        $this->get(route('billing.loan'))->assertOk();

        foreach ([$loan, $incomplete] as $application) {
            $this->assertDatabaseMissing('bills', ['loan_id' => $application->id, 'bill_type' => 'Loan']);
            $this->assertDatabaseMissing('loan_installments', ['loan_id' => $application->id]);
        }
    }

    private function consumer(User $user, string $accountNumber): Consumer
    {
        return Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Loan Billing '.$accountNumber,
            'account_number' => $accountNumber,
            'meter_number' => 'M-'.$accountNumber,
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);
    }

    private function activatedLoan(Consumer $consumer, User $user, $start, float $amount): Loan
    {
        return Loan::query()->create([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $user->id,
            'amount' => $amount,
            'term_months' => 3,
            'purpose' => 'Activated loan',
            'status' => 'Approved',
            'applied_at' => $start->toDateString(),
            'monthly_installment' => $amount / 3,
            'start_billing_period' => $start->toDateString(),
            'next_due_date' => $start->copy()->endOfMonth()->toDateString(),
            'installment_schedule_snapshot' => [
                ['month' => 1, 'amount' => $amount / 3],
                ['month' => 2, 'amount' => $amount / 3],
                ['month' => 3, 'amount' => $amount / 3],
            ],
        ]);
    }
}
