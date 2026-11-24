<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanMaterialRateService;
use App\Services\LoanRepaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanBillingScheduleDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-31 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function loan(User $user, int $term, array $extra = []): Loan
    {
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Scheduled Consumer',
            'account_number' => 'SCH-'.$user->id, 'meter_number' => 'M-'.$user->id,
            'status' => 'Active', 'membership_type' => 'Member']);
        $amount = $term * 40;

        return Loan::create(array_merge([
            'consumer_id' => $consumer->id, 'amount' => $amount, 'monthly_installment' => 40,
            'term_months' => $term, 'purpose' => 'Installation', 'status' => 'Approved',
            'approved_at' => '2026-01-31 10:00:00', 'applied_at' => '2026-01-30',
            'installment_schedule_snapshot' => app(LoanMaterialRateService::class)->schedule($amount, $term),
        ], $extra));
    }

    public function test_approved_loans_show_all_saved_months_and_recover_missing_bill_idempotently(): void
    {
        foreach ([3, 6, 12] as $term) {
            $user = User::factory()->create(['role' => 'consumer']);
            $loan = $this->loan($user, $term);
            $response = $this->actingAs($user)->get(route('billing.loan'))->assertOk()
                ->assertSee('Billing Month')->assertSee('Monthly Amount Due')->assertSee('Payment Deadline')
                ->assertSee('Amount Paid')->assertSee('Remaining Balance')->assertSee('Payment Status')
                ->assertSee('Feb 2026')->assertSee('Feb 28, 2026')->assertSee('Mar 31, 2026')
                ->assertViewHas('loanBillingSummary', fn ($summary) => $summary['bill_count'] === $term && $summary['remaining_balance'] === (float) ($term * 40));
            $this->assertCount($term, $response->viewData('loanBills')->items());
            $this->assertSame('2026-02-01', $loan->fresh()->start_billing_period->toDateString());
            $this->assertSame($term, $loan->installments()->count());
            $this->assertSame(1, Bill::where('loan_id', $loan->id)->where('bill_type', 'Loan')->count());
            $this->get(route('billing.loan'))->assertOk();
            $this->assertSame($term, $loan->installments()->count());
            $this->assertSame(1, Bill::where('loan_id', $loan->id)->where('bill_type', 'Loan')->count());
        }
    }

    public function test_approved_pending_installation_shows_schedule_without_creating_a_payable_bill(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $loan = $this->loan($user, 6, ['status' => 'Ready for Scheduling']);
        $this->actingAs($user)->get(route('billing.loan'))->assertOk()
            ->assertSee('Feb 2026')->assertSee('Jul 2026')->assertSee('Scheduled')->assertSee('Pay')
            ->assertViewHas('loanBillingSummary', fn ($summary) => $summary['bill_count'] === 6);
        $this->assertSame(6, $loan->installments()->count());
        $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
    }

    public function test_existing_start_and_deadline_are_preserved_and_missing_deadlines_are_not_guessed(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $loan = $this->loan($user, 3, ['approved_at' => null, 'start_billing_period' => '2026-02-01', 'next_due_date' => '2026-02-25']);
        $this->actingAs($user)->get(route('billing.loan'))->assertOk()->assertSee('Feb 25, 2026')->assertSee('Mar 25, 2026');
        $this->assertSame('2026-02-25', $loan->installments()->where('installment_number', 1)->firstOrFail()->due_date->toDateString());

        $other = User::factory()->create(['role' => 'consumer']);
        $missing = $this->loan($other, 3, ['approved_at' => null, 'start_billing_period' => '2026-02-01', 'next_due_date' => null]);
        $this->actingAs($other)->get(route('billing.loan'))->assertOk()->assertSee('Not set — contact the administrator');
        $this->assertSame(3, $missing->installments()->count());
        $this->assertSame(0, Bill::where('loan_id', $missing->id)->count());
    }

    public function test_advance_payment_updates_scheduled_months_for_consumer_and_admin(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->loan($user, 6);
        $this->actingAs($user)->get(route('billing.loan'))->assertOk();
        $bill = Bill::where('loan_id', $loan->id)->firstOrFail();
        $this->actingAs($admin)->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => 50, 'payment_date' => '2026-01-31', 'payment_channel' => 'Counter', 'payment_method' => 'Cash'])->assertSessionHasNoErrors();
        $response = $this->actingAs($user)->get(route('billing.loan'))->assertOk()->assertSee('Paid with Balance');
        $this->assertSame(190.0, $response->viewData('loanBillingSummary')['remaining_balance']);
        $second = $loan->installments()->where('installment_number', 2)->firstOrFail();
        $this->assertSame(10.0, (float) $second->amount_paid);
        $this->assertSame(30.0, (float) $second->remaining_balance);
        $this->get(route('billing.loan', ['status' => 'Paid']))->assertViewHas('loanBills', fn ($rows) => $rows->total() === 1);
        $this->get(route('billing.loan', ['status' => 'Paid with Balance']))->assertViewHas('loanBills', fn ($rows) => $rows->total() === 1);
        $this->get(route('billing.loan', ['status' => 'Unpaid']))->assertViewHas('loanBills', fn ($rows) => $rows->total() === 4);
        $this->actingAs($admin)->get(route('billing.loan'))->assertOk()->assertSee('Paid with Balance')
            ->assertViewHas('loanBillingSummary', fn ($summary) => $summary['remaining_balance'] === 190.0);
        $this->assertSame(1, Bill::where('loan_id', $loan->id)->count());
    }

    public function test_admin_groups_by_loan_and_filters_without_truncating_monthly_billings(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->loan($user, 12);
        $otherLoan = $loan->replicate();
        $otherLoan->fill([
            'amount' => 240,
            'term_months' => 6,
            'installment_schedule_snapshot' => app(LoanMaterialRateService::class)->schedule(240, 6),
        ])->save();

        $response = $this->actingAs($admin)->get(route('billing.loan'))->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//table[@id="admin-loan-billing-table"]/tbody/tr[@data-loan-id]')->length);
        foreach ([$loan->id => 12, $otherLoan->id => 6] as $loanId => $term) {
            $this->assertSame(1, $xpath->query('//tr[@data-loan-id="'.$loanId.'"]//button[@aria-expanded="false" and @aria-controls="loan-monthly-billings-'.$loanId.'"]')->length);
            $this->assertSame($term, $xpath->query('//tr[@id="loan-monthly-billings-'.$loanId.'" and @hidden]/td/div/table/tbody/tr')->length);
        }
        $this->assertFalse($response->viewData('loanBills')->hasPages());

        $bill = Bill::where('loan_id', $loan->id)->firstOrFail();
        $this->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => 50, 'payment_date' => '2026-01-31', 'payment_channel' => 'Counter', 'payment_method' => 'Cash'])->assertSessionHasNoErrors();

        $this->get(route('billing.loan', ['search' => $bill->prn, 'status' => 'Paid']))->assertOk()
            ->assertSee('data-loan-id="'.$loan->id.'"', false)
            ->assertDontSee('data-loan-id="'.$otherLoan->id.'"', false)
            ->assertSee('Jan 2027')->assertSee('Paid with Balance')->assertSee('₱430.00')
            ->assertViewHas('loanBills', fn ($loans) => $loans->total() === 1 && $loans->items()[0]->count() === 12);

        $this->get(route('billing.loan', ['search' => $bill->prn, 'status' => 'Unpaid']))->assertOk()
            ->assertSee('No loans match these filters.');
    }

    public function test_admin_search_matches_consumer_details_and_billing_period_without_changing_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->loan(User::factory()->create(['role' => 'consumer']), 12);
        $loan->consumer->update(['full_name' => 'Elena Marie Navarro', 'account_number' => 'AC-2109', 'meter_number' => 'MT-7709']);
        $otherLoan = $this->loan(User::factory()->create(['role' => 'consumer']), 3);
        $otherLoan->consumer->update(['full_name' => 'Ramon Santos', 'account_number' => 'AC-2208', 'meter_number' => 'MT-5608']);

        $page = $this->actingAs($admin)->get(route('billing.loan'))->assertOk()
            ->assertSee('Search Consumer / Account / Meter Number')
            ->assertSee('placeholder="Search by name, account, or meter number..."', false)
            ->assertSeeInOrder(['>Consumer</th>', '>Account Number</th>', '>Meter Number</th>', '>Loan Reference</th>'], false)
            ->assertSeeInOrder(['>Elena Marie Navarro</div>', '<td>AC-2109</td>', '>MT-7709</td>'], false);
        $this->assertSame(['2027', '2026'], $page->viewData('loanBillingYears')->all());
        $summary = $page->viewData('loanBillingSummary');
        $installments = $loan->installments()->orderBy('installment_number')->get()->map->getAttributes()->all();

        foreach (['ELENA', 'navarro', 'elena marie navarro', 'ac-2109', '2109', 'mt-7709', '7709', 'mT-5608'] as $search) {
            $loans = $this->get(route('billing.loan', ['search' => $search]))->assertOk()->viewData('loanBills');
            $this->assertSame(1, $loans->total());
            $this->assertSame($search === 'mT-5608' ? $otherLoan->id : $loan->id, $loans->items()[0]->first()->loan_id);
        }

        $filters = ['search' => '7709', 'year' => 2027, 'month' => 1, 'status' => 'Unpaid'];
        $clearUrl = route('billing.loan');
        $filtered = $this->get(route('billing.loan', $filters))->assertOk()->assertSee('Show Monthly Billings')
            ->assertViewHas('loanBills', fn ($loans) => $loans->total() === 1 && $loans->items()[0]->count() === 12)
            ->assertViewHas('loanBillingSummary', $summary)
            ->assertSee('href="'.$clearUrl.'" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear Loan Billing filters">Clear</a>', false)
            ->assertDontSee('>Clear filters</a>', false)
            ->assertDontSee('aria-label="Clear status filter"', false);
        $this->assertSame(1, substr_count($filtered->getContent(), 'aria-label="Clear Loan Billing filters"'));
        $this->get($clearUrl)->assertOk()
            ->assertViewHas('loanBillingFilters', ['search' => '', 'year' => '', 'month' => '', 'status' => ''])
            ->assertViewHas('loanBills', fn ($loans) => $loans->total() === 2);

        foreach ([['year' => 2026], ['month' => 2], ['status' => 'Paid']] as $override) {
            $this->get(route('billing.loan', $override + $filters))->assertOk()->assertSee('No loans match these filters.');
        }

        $consumer = $this->actingAs($loan->consumer->user)->get(route('billing.loan', [
            'year' => 2027, 'month' => 1, 'status' => 'Unpaid', 'search' => '7709',
        ]))->assertOk();
        $this->assertSame(['2027', '2026'], $consumer->viewData('loanBillingYears')->all());
        $this->assertSame(1, $consumer->viewData('loanBills')->total());
        $this->assertSame($loan->id, $consumer->viewData('loanBills')->first()->loan_id);
        $this->get(route('billing.loan', ['year' => 2027, 'month' => 2]))->assertOk()
            ->assertViewHas('loanBills', fn ($installments) => $installments->total() === 0);
        $this->get(route('billing.loan', ['year' => '', 'status' => 'Unpaid', 'search' => '7709']))->assertOk()
            ->assertViewHas('loanBills', fn ($installments) => $installments->total() === 12);

        $this->assertSame($installments, $loan->installments()->orderBy('installment_number')->get()->map->getAttributes()->all());
    }

    public function test_consumer_can_settle_a_new_schedule_before_first_month_without_later_duplicate_bills(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->loan($user, 6);
        $this->actingAs($user)->get(route('billing.loan'))->assertOk()->assertSee('Feb 2026');
        $bill = Bill::where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame('2026-02-01', $bill->billing_period->toDateString());
        $this->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => 240, 'payment_date' => '2026-01-31', 'payment_channel' => 'Online',
            'payment_method' => 'GCash', 'gateway_reference' => 'SCHEDULE-FULL-001'])->assertSessionHasNoErrors();
        $payment = Payment::where('gateway_reference', 'SCHEDULE-FULL-001')->firstOrFail();
        $this->assertSame(240.0, $loan->fresh()->balance_remaining);
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
        $this->assertSame('Completed', $loan->fresh()->status);
        $this->assertSame(0.0, $loan->fresh()->balance_remaining);
        $this->actingAs($user)->get(route('billing.loan'))->assertViewHas('loanBillingSummary', fn ($summary) => $summary['bill_count'] === 6 && $summary['remaining_balance'] === 0.0);
        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->get(route('billing.loan'))->assertOk();
        $this->assertSame(1, Bill::where('loan_id', $loan->id)->count());
        $this->assertSame(6, $loan->installments()->where('status', 'Paid')->count());
    }

    public function test_incomplete_schedule_is_reported_without_inventing_charges(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $loan = $this->loan($user, 3, ['approved_at' => null, 'start_billing_period' => null]);
        $this->actingAs($user)->get(route('billing.loan'))->assertOk()->assertSee('An approved loan needs schedule review');
        $this->assertSame(0, $loan->installments()->count());
        $this->assertSame(0, Bill::where('loan_id', $loan->id)->count());
    }

    public function test_existing_billing_period_and_verified_payment_remain_unchanged(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $loan = $this->loan($user, 3, ['start_billing_period' => null]);
        $bill = Bill::create(['consumer_id' => $loan->consumer_id, 'loan_id' => $loan->id, 'bill_type' => 'Loan',
            'billing_period' => '2025-12-01', 'due_date' => '2025-12-20', 'prn' => 'HISTORIC-LOAN',
            'amount' => 40, 'loan_installment_amount' => 40, 'status' => 'Unpaid']);
        $payment = Payment::create(['consumer_id' => $loan->consumer_id, 'bill_id' => $bill->id, 'payment_type' => 'Loan',
            'reference_number' => 'HISTORIC-PAYMENT', 'amount' => 10, 'payment_date' => '2025-12-10',
            'payment_channel' => 'Counter', 'payment_method' => 'Cash', 'status' => 'Verified']);
        $this->actingAs($user)->get(route('billing.loan'))->assertOk()->assertSee('Dec 2025')->assertSee('Dec 20, 2025');
        $loan->refresh();
        $this->assertSame('2025-12-01', $loan->start_billing_period->toDateString());
        $this->assertSame(10.0, (float) $loan->installments()->where('installment_number', 1)->firstOrFail()->amount_paid);
        $this->assertSame(30.0, $bill->fresh()->balance_due);
        $this->assertSame('2025-12-20', $bill->fresh()->due_date->toDateString());
        $this->assertSame(1, Payment::where('reference_number', 'HISTORIC-PAYMENT')->count());
        $this->assertDatabaseHas('loan_repayments', ['loan_id' => $loan->id, 'payment_id' => $payment->id, 'amount' => 10]);
        $this->assertSame(110.0, app(LoanRepaymentAllocationService::class)->paymentLimit($loan));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => 20, 'payment_date' => '2026-01-31', 'payment_channel' => 'Counter', 'payment_method' => 'Cash'])->assertSessionHasNoErrors();
        $this->assertSame(10.0, $bill->fresh()->balance_due);
    }

    public function test_pending_historical_payment_is_not_applied_during_schedule_sync(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $loan = $this->loan($user, 3);
        $bill = Bill::create(['consumer_id' => $loan->consumer_id, 'loan_id' => $loan->id, 'bill_type' => 'Loan',
            'billing_period' => '2026-02-01', 'due_date' => '2026-02-28', 'prn' => 'PENDING-LEGACY-BILL',
            'amount' => 40, 'loan_installment_amount' => 40, 'status' => 'Unpaid']);
        $payment = Payment::create(['consumer_id' => $loan->consumer_id, 'bill_id' => $bill->id, 'payment_type' => 'Loan',
            'reference_number' => 'PENDING-LEGACY-PAYMENT', 'amount' => 40, 'payment_date' => '2026-01-30',
            'payment_channel' => 'Online', 'payment_method' => 'GCash', 'status' => 'Pending']);
        LoanRepayment::create(['loan_id' => $loan->id, 'bill_id' => $bill->id, 'payment_id' => $payment->id,
            'amount' => 40, 'payment_date' => '2026-01-30']);
        $this->actingAs($user)->get(route('billing.loan'))->assertOk();
        $this->assertSame(0.0, $loan->fresh()->total_repaid);
        $this->assertSame(40.0, $bill->fresh()->balance_due);
        $this->assertSame('Pending', $payment->fresh()->status);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasErrors('status');
        $this->assertSame('Pending', $payment->fresh()->status);
        $this->assertSame(40.0, $bill->fresh()->balance_due);
    }

    public function test_ambiguous_historical_payments_require_review_without_accepting_new_payments(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->loan($user, 3);
        $bill = Bill::create(['consumer_id' => $loan->consumer_id, 'loan_id' => $loan->id, 'bill_type' => 'Loan',
            'billing_period' => '2026-02-01', 'due_date' => '2026-02-28', 'prn' => 'AMBIGUOUS-BILL',
            'amount' => 40, 'loan_installment_amount' => 40, 'status' => 'Unpaid']);
        $payment = Payment::create(['consumer_id' => $loan->consumer_id, 'bill_id' => $bill->id, 'payment_type' => 'Loan',
            'reference_number' => 'AMBIGUOUS-VERIFIED', 'amount' => 10, 'payment_date' => '2026-01-30',
            'payment_channel' => 'Counter', 'payment_method' => 'Cash', 'status' => 'Verified']);
        LoanRepayment::create(['loan_id' => $loan->id, 'amount' => 25, 'payment_date' => '2026-01-29']);

        $this->actingAs($user)->get(route('billing.loan'))->assertOk()->assertSee('Under review')->assertSee('Awaiting review');
        $this->assertSame(1, $loan->installments()->count());
        $this->assertDatabaseMissing('loan_repayments', ['payment_id' => $payment->id]);
        $this->actingAs($admin)->post(route('payments.store'), ['bill_id' => $bill->id, 'payment_type' => 'Loan',
            'amount' => 10, 'payment_date' => '2026-01-31', 'payment_channel' => 'Counter', 'payment_method' => 'Cash'])
            ->assertSessionHasErrors('bill_id');
        $this->assertSame(1, Payment::where('bill_id', $bill->id)->count());
        $this->assertSame(1, LoanRepayment::where('loan_id', $loan->id)->count());
    }
}
