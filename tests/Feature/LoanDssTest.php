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
use App\Services\Dss\LoanApplicationEvaluationService;
use App\Services\Dss\LoanDssService;
use App\Services\DssEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanDssTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_computes_decision_rates_portfolio_overdue_and_repayment_metrics(): void
    {
        [$approved, $pending, $rejected] = $this->seedLoanScenario();

        $dss = app(LoanDssService::class)->build(['year' => 2026]);

        $this->assertSame(3, $dss['summary']['total']);
        $this->assertSame(1, $dss['summary']['pending']);
        $this->assertSame(1, $dss['summary']['approved']);
        $this->assertSame(1, $dss['summary']['rejected']);
        $this->assertSame(2, $dss['summary']['decided']);
        $this->assertSame(50.0, $dss['summary']['approval_rate']);
        $this->assertSame(50.0, $dss['summary']['rejection_rate']);
        $this->assertSame(66.7, $dss['summary']['decision_coverage']);
        $this->assertSame(1800.0, $dss['summary']['total_amount']);
        $this->assertSame(500.0, $dss['summary']['pending_requested_amount']);
        $this->assertSame(1000.0, $dss['summary']['approved_principal']);
        $this->assertSame(500.0, $dss['summary']['outstanding_balance']);
        $this->assertSame(500.0, $dss['summary']['total_repaid']);
        $this->assertSame(1000.0, $dss['summary']['total_released']);
        $this->assertSame(1, $dss['summary']['portfolio_count']);
        $this->assertSame(1, $dss['summary']['overdue_count']);
        $this->assertSame(500.0, $dss['summary']['overdue_amount']);
        $this->assertSame(1, $dss['summary']['overdue_installments']);
        $this->assertSame(100.0, $dss['summary']['delinquency_rate']);
        $this->assertSame(50.0, $dss['summary']['collection_rate']);
        $this->assertSame(100.0, $dss['summary']['on_time_rate']);
        $this->assertNull($dss['summary']['average_days_late']);
        $this->assertSame(2, $dss['summary']['installments_billed']);
        $this->assertSame(1, $dss['summary']['installments_paid']);
        $this->assertSame(1, $dss['summary']['installments_overdue']);
        $this->assertTrue($dss['summary']['has_installments']);
        $this->assertTrue($dss['summary']['has_repayments']);
        $this->assertSame(51, $dss['summary']['oldest_pending_days']);
        $this->assertSame(1.0, $dss['summary']['average_approval_days']);
        $this->assertSame(0, $dss['summary']['invalid_approval_timestamps']);
        $this->assertSame(1, $dss['summary']['high_risk']);
        $this->assertSame(0, $dss['summary']['unassessed_risk']);
        $this->assertSame(0, $dss['summary']['zero_amount_approved']);

        $this->assertSame(['Current', '1-30 days', '31-60 days', '61-90 days', '90+ days'], $dss['aging']['labels']);
        $this->assertSame(0.0, $dss['aging']['amounts'][0]);
        $this->assertSame(500.0, $dss['aging']['amounts'][1]);
        $this->assertSame(1, $dss['monthly']['applications'][7]);
        $this->assertSame(2, $dss['monthly']['applications'][8]);
        $this->assertSame(1, $dss['monthly']['approvals'][7]);
        $this->assertSame(500.0, $dss['monthly']['collections'][7]);
        $this->assertSame(1000.0, $dss['monthly']['releases'][7]);
        $this->assertSame(500.0, $dss['monthly']['repayments'][7]);
        $this->assertSame(0.0, $dss['monthly']['collections'][8]);
        $this->assertSame(1000.0, array_sum($dss['monthly']['releases']));
        $this->assertSame(500.0, array_sum($dss['monthly']['repayments']));
        $this->assertSame(500.0, array_sum($dss['charts']['overdue_locations']['amounts']));
        $this->assertTrue($dss['charts']['overdue_locations']['has_schedule_data']);
        $this->assertSame(['Paid', 'Partially Paid', 'Overdue', 'Upcoming'], $dss['charts']['repayment_status']['labels']);
        $this->assertTrue($dss['charts']['repayment_status']['has_schedule_data']);
        $repaymentCounts = array_combine($dss['charts']['repayment_status']['labels'], $dss['charts']['repayment_status']['counts']);
        $this->assertSame(1, $repaymentCounts['Paid']);
        $this->assertSame(1, $repaymentCounts['Overdue']);

        $statusCounts = collect($dss['status_breakdown'])->pluck('total', 'label');
        $this->assertSame(1, $statusCounts['Approved']);
        $this->assertSame(1, $statusCounts['Pending Review']);
        $this->assertSame(1, $statusCounts['Rejected']);
        $this->assertSame(3, array_sum($dss['charts']['status']['counts']));

        $riskCounts = collect($dss['risk_breakdown'])->pluck('total', 'label');
        $this->assertSame(1, $riskCounts['Low Risk']);
        $this->assertSame(1, $riskCounts['Medium Risk']);
        $this->assertSame(1, $riskCounts['High Risk']);

        $this->assertCount(1, $dss['term_breakdown']);
        $this->assertSame('6 months', $dss['term_breakdown'][0]['label']);
        $this->assertSame(3, $dss['term_breakdown'][0]['total']);

        $approvedApplication = collect($dss['applications'])->firstWhere('id', $approved->id);
        $this->assertNotNull($approvedApplication);
        $this->assertTrue($approvedApplication['is_portfolio']);
        $this->assertTrue($approvedApplication['is_overdue']);
        $this->assertSame(21, $approvedApplication['overdue_days']);
        $this->assertSame(500.0, $approvedApplication['outstanding']);

        $pendingApplication = collect($dss['applications'])->firstWhere('id', $pending->id);
        $this->assertNotNull($pendingApplication);
        $this->assertFalse($pendingApplication['is_portfolio']);
        $this->assertNull($pendingApplication['outstanding']);
        $this->assertFalse($pendingApplication['is_overdue']);

        $rejectedApplication = collect($dss['applications'])->firstWhere('id', $rejected->id);
        $this->assertNotNull($rejectedApplication);
        $this->assertFalse($rejectedApplication['is_portfolio']);
        $this->assertNull($rejectedApplication['outstanding']);

        $titles = collect($dss['recommendations'])->pluck('title');
        $this->assertTrue($titles->contains(fn (string $title): bool => str_contains($title, 'Follow up overdue installments')));
    }

    public function test_schedule_chart_and_location_overdue_use_remaining_amounts_and_due_dates(): void
    {
        $consumer = $this->consumer('Schedule states');
        $consumer->update(['service_purok' => 'Purok 1-A', 'address_block' => 'Block 4']);
        $loan = $this->loan($consumer, [
            'amount' => 1000,
            'status' => 'Approved',
            'applied_at' => '2026-08-01 10:00:00',
            'approved_at' => '2026-08-02 10:00:00',
        ]);

        $schedules = [
            [100, 100, '2026-09-01'],
            [100, 30, '2026-11-01'],
            [100, 20, '2026-10-01'],
            [100, 0, '2026-11-22'],
            [50, 0, '2026-10-22'],
        ];
        foreach ($schedules as $index => [$required, $paid, $dueDate]) {
            $this->installment($loan, [
                'installment_number' => $index + 1,
                'billing_period' => Carbon::parse('2026-09-01')->addMonths($index)->toDateString(),
                'required_amount' => $required,
                'amount_paid' => $paid,
                'remaining_balance' => $required - $paid,
                'due_date' => $dueDate,
                'status' => $paid >= $required ? 'Paid' : ($paid > 0 ? 'Partially Paid' : 'Billed'),
            ]);
        }
        $this->repayment($loan, ['amount' => 150, 'payment_date' => '2026-10-10']);

        $dss = app(LoanDssService::class)->build(['year' => 2026]);
        $statusCounts = array_combine($dss['repayment_status']['labels'], $dss['repayment_status']['counts']);
        $statusAmounts = array_combine($dss['repayment_status']['labels'], $dss['repayment_status']['amounts']);

        $this->assertSame(1, $statusCounts['Paid']);
        $this->assertSame(1, $statusCounts['Partially Paid']);
        $this->assertSame(1, $statusCounts['Overdue']);
        $this->assertSame(2, $statusCounts['Upcoming']);
        $this->assertSame(80.0, $statusAmounts['Overdue']);
        $this->assertSame(80.0, $dss['summary']['overdue_amount']);
        $this->assertSame(80.0, array_sum($dss['charts']['overdue_locations']['amounts']));
        $this->assertSame('Purok 1-A / Block 4', $dss['charts']['overdue_locations']['labels'][0]);
        $this->assertSame('DSS Consumer Schedule states', $dss['recommendations'][0]['cases'][0]['consumer']);
        $this->assertSame(80.0, $dss['recommendations'][0]['cases'][0]['amount']);
        $this->assertStringContainsString('1 missed installment', $dss['recommendations'][0]['message']);
        $this->assertStringContainsString('80.00', $dss['recommendations'][0]['message']);
        $this->assertStringContainsString('Oct 1, 2026', $dss['recommendations'][0]['message']);
    }

    public function test_unverified_payment_link_is_excluded_until_payment_is_verified(): void
    {
        $consumer = $this->consumer('Payment verification');
        $loan = $this->loan($consumer, [
            'amount' => 1000,
            'status' => 'Approved',
            'applied_at' => '2026-08-01 10:00:00',
            'approved_at' => '2026-08-02 10:00:00',
            'next_due_date' => null,
        ]);
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'bill_type' => 'Loan',
            'loan_id' => $loan->id,
            'billing_period' => '2026-10-01',
            'due_date' => '2026-10-31',
            'prn' => 'DSS-VERIFY-BILL',
            'amount' => 500,
            'status' => 'Unpaid',
        ]);
        $payment = Payment::query()->create([
            'consumer_id' => $consumer->id,
            'bill_id' => $bill->id,
            'reference_number' => 'DSS-VERIFY-PAYMENT',
            'amount' => 500,
            'payment_date' => '2026-10-10',
            'payment_method' => 'Cash',
            'status' => 'Pending',
        ]);
        LoanRepayment::query()->create([
            'loan_id' => $loan->id,
            'bill_id' => $bill->id,
            'payment_id' => $payment->id,
            'amount' => 500,
            'payment_date' => '2026-10-10',
        ]);

        $pending = app(LoanDssService::class)->build(['year' => 2026]);
        $this->assertSame(0.0, $pending['summary']['total_repaid']);
        $this->assertSame(1000.0, $pending['summary']['outstanding_balance']);
        $this->assertSame(0.0, array_sum($pending['monthly']['repayments']));

        $payment->update(['status' => 'Verified']);
        $verified = app(LoanDssService::class)->build(['year' => 2026]);
        $this->assertSame(500.0, $verified['summary']['total_repaid']);
        $this->assertSame(500.0, $verified['summary']['outstanding_balance']);
        $this->assertSame(500.0, array_sum($verified['monthly']['repayments']));
    }

    public function test_advances_and_office_payments_appear_before_release_without_double_counting(): void
    {
        $loan = $this->loan($this->consumer('Advance'), [
            'amount' => 120, 'status' => 'Ready for Scheduling', 'next_due_date' => '2026-08-10',
        ]);
        $this->installment($loan, [
            'due_date' => '2026-08-10', 'required_amount' => 60, 'amount_paid' => 0,
            'remaining_balance' => 60, 'status' => 'Billed', 'payment_date' => null,
        ]);
        $this->installment($loan, [
            'installment_number' => 2, 'billing_period' => '2026-12-01', 'due_date' => '2026-12-10', 'required_amount' => 60,
            'amount_paid' => 0, 'remaining_balance' => 60, 'status' => 'Upcoming', 'payment_date' => null,
        ]);
        foreach ([
            ['Loan', 'Verified', 25],
            ['Loan', 'Verified', 15],
            ['Loan', 'Pending', 40],
            ['Loan', 'Rejected', 50],
            ['Water', 'Verified', 60],
        ] as $index => [$type, $status, $amount]) {
            $payment = Payment::query()->create([
                'consumer_id' => $loan->consumer_id, 'loan_id' => $loan->id, 'payment_type' => $type,
                'reference_number' => 'DSS-ADVANCE-'.$index, 'amount' => $amount, 'payment_date' => '2026-08-25',
                'payment_method' => 'Cash', 'status' => $status,
            ]);
            if ($index !== 1) {
                $this->repayment($loan, ['payment_id' => $payment->id, 'amount' => $amount, 'payment_date' => '2026-08-25']);
            }
        }
        $this->repayment($loan, ['amount' => 10, 'payment_date' => '2026-08-26']);

        $dss = app(LoanDssService::class)->build(['year' => 2026]);
        $this->assertSame(0.0, $dss['summary']['total_released']);
        $this->assertSame(50.0, $dss['summary']['total_repaid']);
        $this->assertSame(70.0, $dss['summary']['outstanding_balance']);
        $this->assertSame(10.0, $dss['summary']['overdue_amount']);
        $this->assertSame(50.0, $dss['monthly']['repayments'][7]);
        $this->assertSame(10.0, array_sum($dss['charts']['overdue_locations']['amounts']));
        $this->assertSame([0, 0, 1, 1], $dss['charts']['repayment_status']['counts']);
        $this->assertSame(0.0, (float) $loan->installments()->sum('amount_paid'), 'Reading DSS never mutates the schedule.');
    }

    public function test_completion_records_release_date_and_monthly_chart_uses_it(): void
    {
        $loan = $this->loan($this->consumer('Release timing'), [
            'status' => 'Ready for Scheduling', 'installation_status' => 'Scheduled',
            'installation_date' => '2026-09-02', 'installation_time' => '10:00',
        ]);
        $this->saveDecision($loan, 'Approved', '2026-08-02 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('loans.installation-status.update', $loan), [
            'installation_status' => 'Completed',
            'physical_installation_confirmed' => '1',
        ])
            ->assertSessionHasNoErrors();

        $releasedAt = $loan->fresh()->released_at;
        $this->assertTrue($releasedAt->eq(now()));
        $dss = $this->get(route('dss.loans', ['year' => 2026]))->assertOk()->viewData('dss');
        $this->assertSame(1000.0, $dss['monthly']['releases'][now()->month - 1]);
        $this->assertSame(0.0, $dss['monthly']['releases'][7]);
        $this->assertSame('Approved', $loan->latestDssEvaluation()->first()->recommendation);
    }

    public function test_year_filters_only_monthly_cash_flow(): void
    {
        $this->seedLoanScenario();

        $priorYearConsumer = $this->consumer('Prior year release');
        $priorYearRelease = $this->loan($priorYearConsumer, [
            'amount' => 600,
            'status' => 'Approved',
            'applied_at' => '2025-12-15 10:00:00',
            'approved_at' => '2026-05-02 10:00:00',
            'next_due_date' => null,
        ]);
        $this->repayment($priorYearRelease, ['amount' => 100, 'payment_date' => '2026-05-12']);

        $service = app(LoanDssService::class);

        $annual = $service->build(['year' => 2026]);
        $this->assertSame(4, $annual['summary']['total']);
        $this->assertSame(1600.0, array_sum($annual['monthly']['releases']));
        $this->assertSame(600.0, array_sum($annual['monthly']['repayments']));
        $this->assertSame(600.0, $annual['monthly']['releases'][4]);
        $this->assertSame(100.0, $annual['monthly']['repayments'][4]);
        $withOldFilterQuery = $service->build([
            'year' => 2025, 'month' => 12, 'status' => 'Rejected', 'risk' => 'High Risk', 'term' => 12,
        ]);
        $this->assertSame(4, $withOldFilterQuery['summary']['total']);
        $this->assertSame(1600.0, $withOldFilterQuery['summary']['total_released']);
        $this->assertSame(4, array_sum($withOldFilterQuery['charts']['status']['counts']));
        $this->assertSame(0.0, array_sum($withOldFilterQuery['monthly']['releases']));
        $this->assertSame(0.0, array_sum($withOldFilterQuery['monthly']['repayments']));

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)
            ->get(route('dss.loans', ['year' => 2026, 'month' => 9, 'status' => 'Rejected', 'risk' => 'High Risk', 'term' => 12]))
            ->assertOk()
            ->assertSee('Released Principal')
            ->assertDontSee('Automatic filters')
            ->assertSee('loan-cash-flow-year')
            ->assertDontSee('loan_dss_status');
        $this->assertSame(4, $response->viewData('dss')['summary']['total']);
        $unfiltered = $this->actingAs($admin)
            ->get(route('dss.loans', ['year' => 'year', 'status' => 'Rejected']))
            ->assertOk();
        $this->assertSame(4, $unfiltered->viewData('dss')['summary']['total']);
        $this->assertSame('all', $unfiltered->viewData('decisionReport')['filters']['year']);
        $this->assertSame('all', $unfiltered->viewData('decisionReport')['filters']['status']);

        $future = $this->loan($this->consumer('Future release'), [
            'amount' => 250,
            'status' => 'Approved',
            'applied_at' => '2026-12-01 10:00:00',
            'approved_at' => '2028-05-01 10:00:00',
        ]);
        $this->repayment($future, ['amount' => 50, 'payment_date' => '2028-05-12']);
        $allYears = $this->get(route('dss.loans', ['year' => 'all']))->assertOk()->viewData('dss');
        $this->assertSame('All Years', $allYears['period_label']);
        $this->assertContains(2028, $allYears['options']['years']);
        $this->assertSame(1850.0, array_sum($allYears['monthly']['releases']));
        $this->assertSame(650.0, array_sum($allYears['monthly']['repayments']));

        $default = $this->get(route('dss.loans'))->assertOk()->viewData('dss');
        $this->assertSame('all', $default['filters']['year']);
        $this->assertSame($allYears['monthly'], $default['monthly']);
        $olderYear = $this->get(route('dss.loans', ['year' => 2014]))->assertOk()->viewData('dss');
        $this->assertSame(2014, $olderYear['filters']['year']);
        $this->assertFalse($olderYear['monthly']['has_data']);

        $may = $this->get(route('dss.loans', ['year' => 2026, 'month' => 5]))->assertOk()->viewData('dss');
        $this->assertSame('May 2026', $may['period_label']);
        $this->assertSame(['May'], $may['monthly']['labels']);
        $this->assertSame([600.0], $may['monthly']['releases']);
        $this->assertSame([100.0], $may['monthly']['repayments']);
        $this->assertSame($allYears['summary'], $may['summary']);
    }

    public function test_missing_repayment_data_is_surfaced_instead_of_reporting_a_false_collection_rate(): void
    {
        $consumer = $this->consumer('Pending Only');
        $this->loan($consumer, [
            'amount' => 500,
            'status' => 'Pending Review',
            'risk_level' => 'Medium Risk',
            'approved_at' => null,
            'next_due_date' => null,
        ]);

        $dss = app(LoanDssService::class)->build(['year' => 2026]);

        $this->assertSame(1, $dss['summary']['total']);
        $this->assertSame(1, $dss['summary']['pending']);
        $this->assertSame(0, $dss['summary']['decided']);
        $this->assertNull($dss['summary']['approval_rate']);
        $this->assertNull($dss['summary']['rejection_rate']);
        $this->assertSame(0.0, $dss['summary']['decision_coverage']);
        $this->assertNull($dss['summary']['collection_rate']);
        $this->assertSame(0, $dss['summary']['portfolio_count']);
        $this->assertTrue(collect($dss['recommendations'])->pluck('title')->contains('No overdue loan installments'));
        $this->assertFalse(collect($dss['data_notes'])->contains(
            fn (string $note): bool => str_contains($note, 'Installment and repayment records are unavailable')
        ));
    }

    public function test_zero_amount_and_invalid_approval_timestamp_are_flagged(): void
    {
        $consumer = $this->consumer('Invalid Timestamp');
        $this->loan($consumer, [
            'amount' => 0,
            'status' => 'Approved',
            'risk_level' => 'High Risk',
            'applied_at' => '2026-10-10 12:00:00',
            'approved_at' => '2026-10-10 04:00:00',
            'next_due_date' => null,
        ]);

        $dss = app(LoanDssService::class)->build(['year' => 2026]);

        $this->assertSame(1, $dss['summary']['invalid_approval_timestamps']);
        $this->assertSame(1, $dss['summary']['zero_amount_approved']);
        $this->assertNull($dss['summary']['average_approval_days']);
        $this->assertSame(1, $dss['summary']['high_risk']);
        $this->assertSame(0, $dss['summary']['portfolio_count']);
        $this->assertNull($dss['summary']['collection_rate']);
        $this->assertFalse($dss['summary']['has_installments']);
        $this->assertFalse($dss['summary']['has_repayments']);
        $this->assertSame([
            'total_released' => true,
            'total_repaid' => false,
            'outstanding_balance' => false,
            'overdue_amount' => false,
        ], $dss['metric_availability']);
        $this->assertTrue(collect($dss['data_notes'])->contains(
            fn (string $note): bool => str_contains($note, 'Installment and repayment records are unavailable')
        ));

        $titles = collect($dss['recommendations'])->pluck('title');
        $this->assertTrue($titles->contains('No overdue loan installments'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dss.loans'))
            ->assertOk()
            ->assertSee('Released Principal')
            ->assertSee('N/A');
    }

    public function test_page_renders_loan_charts_without_removed_filter_or_notice_panels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$approvedLoan, $reviewLoan, $rejectedLoan] = $this->seedLoanScenario();
        $this->saveDecision($approvedLoan, 'Approved', '2026-08-02 10:00:00');
        $this->saveDecision($reviewLoan, 'Needs Review', '2026-08-04 10:00:00');
        $this->saveDecision($rejectedLoan, 'Rejected', '2026-08-03 10:00:00', [[
            'id' => 'verified_membership', 'label' => 'Verified membership', 'value' => false,
            'required' => true, 'unit' => 'boolean', 'passed' => false, 'explanation' => 'Recorded false; required true.',
        ]]);

        $this->actingAs($admin)
            ->get(route('dss.loans', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Loan DSS')
            ->assertSee('Released Principal')
            ->assertSee('Approved vs. Rejected')
            ->assertSee('Monthly Loan Releases and Repayments')
            ->assertSee('Overdue Balances by Purok/Block')
            ->assertSee('Repayment Status')
            ->assertSee('Recommended Actions')
            ->assertDontSee('Automatic filters')
            ->assertSee('loan-cash-flow-year')
            ->assertDontSee('loan_dss_month')
            ->assertDontSee('loan_dss_status')
            ->assertDontSee('loan_dss_risk')
            ->assertDontSee('loan_dss_term')
            ->assertDontSee('Installment and repayment records are unavailable')
            ->assertDontSee('Approval lead time uses only valid timestamps')
            ->assertSee('Application Results')
            ->assertSee('View Details')
            ->assertDontSee('Needs Review queue')
            ->assertDontSee('Verify in Loan')
            ->assertDontSee('Verify pending proofs in Loan Management')
            ->assertDontSee('Decision year')
            ->assertDontSee('Final decision')
            ->assertDontSee('loan-dss-filters')
            ->assertSee('data-period-month', false)
            ->assertDontSee('>Apply</button>', false)
            ->assertDontSee('<th>Evaluation Evidence</th>', false)
            ->assertSee('loanApplicationDetailsModal', false);
    }

    public function test_decision_details_and_status_specific_actions_render_inside_modal_templates(): void
    {
        $consumer = $this->consumer('Modal Actions');
        $reviewLoan = $this->loan($consumer, [
            'status' => 'Needs Review',
            'applied_at' => '2026-10-10 10:00:00',
        ]);
        LoanDssEvaluation::query()->create([
            'loan_id' => $reviewLoan->id,
            'recommendation' => 'Needs Review',
            'evaluation_type' => 'initial',
            'criteria_version' => 'test-criteria',
            'evaluated_values' => ['recorded_test_value' => 17],
            'income_evidence' => ['ocr_status' => 'Incomplete', 'ocr_confidence' => 33],
            'income_verification_status' => 'Needs Review',
            'failed_criteria' => [],
            'missing_information' => ['Trusted income-source verification'],
            'evidence_hash' => hash('sha256', 'review-'.$reviewLoan->id),
            'evaluated_at' => '2026-10-10 10:05:00',
        ]);

        $approvedLoan = $this->loan($this->consumer('Approved Modal'), [
            'status' => 'Ready for Scheduling',
            'review_status' => 'Reviewed',
            'applied_at' => '2026-10-11 10:00:00',
        ]);
        $this->saveDecision($approvedLoan, 'Approved', '2026-10-11 10:05:00');
        $this->assertSame('Ready for Scheduling', $approvedLoan->fresh()->status);
        $this->assertSame('Approved', $approvedLoan->fresh('latestDssEvaluation')->latestDssEvaluation->recommendation);

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('dss.loans', ['status' => 'Needs Review']));
        $this->assertSame(200, $response->status());
        $approvedResponse = $this->actingAs($admin)->get(route('dss.loans', ['status' => 'Approved']));
        $html = $response->getContent().$approvedResponse->getContent();
        foreach ([
            'Ready for Scheduling',
            'loan-details-template-'.$approvedLoan->id,
        ] as $expected) {
            $this->assertTrue(str_contains($html, $expected), 'Expected DSS Loan content was missing: '.$expected);
        }
        $this->assertStringNotContainsString('loan-details-template-'.$reviewLoan->id, $html);
        $this->assertStringNotContainsString('Verify in Loan', $html);
        $this->assertStringNotContainsString('Verify pending proofs in Loan Management', $html);
        $approvedTemplateStart = strpos($html, 'id="loan-details-template-'.$approvedLoan->id.'"');
        $this->assertNotFalse($approvedTemplateStart);
        $approvedTemplateEnd = strpos($html, '</template>', $approvedTemplateStart);
        $this->assertNotFalse($approvedTemplateEnd);
        $approvedTemplate = substr($html, $approvedTemplateStart, $approvedTemplateEnd - $approvedTemplateStart);
        $this->assertStringNotContainsString('name="installation_date"', $approvedTemplate);
        $this->assertStringNotContainsString('name="identity_confirmed"', $approvedTemplate);
        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'all']))
            ->assertSee('Assign Meter &amp; Schedule Installation', false)
            ->assertSee('name="installation_date"', false);
    }

    public function test_dashboard_ignores_stale_filters_and_shows_all_final_decisions_across_years(): void
    {
        [$approvedLoan, $reviewLoan, $rejectedLoan] = $this->seedLoanScenario();
        $this->saveDecision($approvedLoan, 'Approved', '2024-08-02 10:00:00');
        $this->saveDecision($reviewLoan, 'Needs Review', '2025-08-04 10:00:00');
        $this->saveDecision($rejectedLoan, 'Rejected', '2026-08-03 10:00:00', [[
            'id' => 'verified_membership', 'label' => 'Verified membership', 'value' => false,
            'required' => true, 'unit' => 'boolean', 'passed' => false, 'explanation' => 'Recorded false; required true.',
        ]]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('dss.loans', ['year' => 2026, 'status' => 'Rejected']))->assertOk();
        $report = $response->viewData('decisionReport');
        $this->assertSame(['approved' => 1, 'rejected' => 1], $report['summary']);
        $this->assertSame(['Approved', 'Rejected'], $report['chart']['labels']);
        $this->assertSame([1, 1], $report['chart']['counts'], 'Stale year and status query parameters do not restrict dashboard outcomes.');
        $this->assertSame(2, $report['applications']->total());
        $this->assertSame(['Approved', 'Rejected'], $report['applications']->getCollection()->pluck('dss_decision_status')->unique()->sort()->values()->all());

        $approvedResponse = $this->actingAs($admin)->get(route('dss.loans', ['year' => 2026, 'status' => 'Approved']))->assertOk();
        $approvedReport = $approvedResponse->viewData('decisionReport');
        $this->assertSame($report['summary'], $approvedReport['summary']);
        $this->assertSame([1, 1], $approvedReport['chart']['counts']);
        $this->assertSame(2, $approvedReport['applications']->total());

        $allResponse = $this->actingAs($admin)->get(route('dss.loans', ['year' => 2026]))->assertOk();
        $allReport = $allResponse->viewData('decisionReport');
        $this->assertSame(2, $allReport['applications']->total(), 'All decisions lists only Approved and Rejected records.');
        $this->assertSame(['approved' => 1, 'rejected' => 1], $allReport['summary']);
        $this->assertSame(['Approved', 'Rejected'], $allReport['applications']->getCollection()->pluck('dss_decision_status')->unique()->sort()->values()->all());
        $allResponse->assertDontSee('Needs Review queue')
            ->assertDontSee('Verify in Loan')
            ->assertDontSee('Verify pending proofs in Loan Management');

        $staleReviewFilter = $this->actingAs($admin)->get(route('dss.loans', ['year' => 2026, 'status' => 'Needs Review']))->assertOk()->viewData('decisionReport');
        $this->assertSame(2, $staleReviewFilter['applications']->total());
        $this->assertSame(['Approved', 'Rejected'], $staleReviewFilter['applications']->getCollection()->pluck('dss_decision_status')->unique()->sort()->values()->all());
    }

    public function test_outcome_chart_excludes_needs_review_and_shows_its_empty_state_when_only_reviews_exist(): void
    {
        [, $reviewLoan] = $this->seedLoanScenario();
        $this->saveDecision($reviewLoan, 'Needs Review', '2026-08-04 10:00:00');

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dss.loans', ['year' => 2026]))
            ->assertOk()
            ->assertSee('No approved or rejected applications found.')
            ->assertSee('No approved or rejected applications yet.');

        $chart = $response->viewData('decisionReport')['chart'];
        $this->assertSame(['Approved', 'Rejected'], $chart['labels']);
        $this->assertSame([0, 0], $chart['counts']);
    }

    public function test_new_rejection_status_change_records_its_actual_decision_timestamp(): void
    {
        $loan = $this->loan($this->consumer('Decision Timestamp'), [
            'status' => 'Pending Review',
            'approved_at' => null,
            'decision_at' => null,
        ]);

        $loan->update(['status' => 'Rejected']);

        $this->assertTrue($loan->fresh()->decision_at->equalTo(now()));
    }

    public function test_nonmember_and_address_do_not_affect_dss_recommendation(): void
    {
        $consumer = $this->consumer('Non Member');
        $consumer->update(['membership_type' => 'Non-Member', 'membership_verification_status' => 'Not Required', 'service_address' => 'Outside Via Vida']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->loan($consumer, [
            'status' => 'Rejected',
            'approved_at' => null,
            'decision_at' => '2026-10-01 09:30:00',
            'income_verification_status' => 'Verified',
            'income_verified_by' => $admin->id,
            'income_verified_at' => now(),
        ]);

        $evaluation = app(LoanApplicationEvaluationService::class)
            ->evaluateAndSave($loan->fresh('consumer'), app(DssEligibilityService::class));
        $this->actingAs($admin)
            ->get(route('dss.loans', ['year' => 2026]))
            ->assertOk();
        $failed = collect($evaluation->failed_criteria)->keyBy('id');
        $this->assertSame('Approved', $evaluation->recommendation);
        $this->assertSame('reassessment', $evaluation->evaluation_type);
        $this->assertSame(LoanApplicationEvaluationService::CRITERIA_VERSION, $evaluation->criteria_version);
        $this->assertNotNull($evaluation->evaluated_at);
        $this->assertArrayNotHasKey('verified_membership', $failed->all());
        $this->assertArrayNotHasKey('verified_membership', $evaluation->evaluated_values);
        $this->assertNotContains('Registered Via Vida Townhomes Homeowners Association Member.', $evaluation->evaluated_values['approval_reasons']);
        $this->assertSame('Rejected', $loan->fresh()->status, 'The DSS recommendation must not mutate the Admin decision.');
        LoanDssEvaluation::query()->where('recommendation', 'Rejected')->get()->each(function (LoanDssEvaluation $saved): void {
            $this->assertNotEmpty($saved->failed_criteria);
            foreach ($saved->failed_criteria as $criterion) {
                $this->assertArrayHasKey('value', $criterion);
                $this->assertArrayHasKey('required', $criterion);
                $this->assertNotSame('', $criterion['explanation']);
            }
        });

        $this->actingAs($admin)->get(route('dss.loans'))->assertOk();
        $this->assertSame(1, LoanDssEvaluation::query()->where('loan_id', $loan->id)->count(), 'Unchanged evidence must not create duplicate evaluations.');
    }

    public function test_dss_modal_filters_obsolete_reasons_from_legacy_records_without_mutating_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $approvedLoan = $this->loan($this->consumer('Legacy approval reasons'), [
            'status' => 'Approved', 'approved_at' => '2026-10-01 10:00:00', 'decision_at' => '2026-10-01 10:00:00',
        ]);
        $approved = $this->saveDecision($approvedLoan, 'Approved', '2026-10-01 10:00:00');
        $approved->update(['evaluated_values' => ['approval_reasons' => [
            'Proof of income was verified by an administrator.',
            'Your consumer account is verified and activated.',
            'Registered Via Vida Townhomes Homeowners Association Member.',
            'Address verification passed for this residence.',
        ]]]);

        $rejectedLoan = $this->loan($this->consumer('Legacy rejection reasons'), [
            'status' => 'Rejected', 'approved_at' => null, 'decision_at' => '2026-10-02 10:00:00',
        ]);
        $rejected = $this->saveDecision($rejectedLoan, 'Rejected', '2026-10-02 10:00:00', [
            ['id' => 'verified_membership', 'label' => 'Verified VVTHOA membership', 'explanation' => 'Association membership is not verified.'],
            ['id' => 'address_verification', 'label' => 'Residential address verification', 'explanation' => 'The address is outside the eligible area.'],
            ['id' => 'late_payment_history', 'label' => 'Late payment history', 'explanation' => 'One late water payment was recorded.'],
        ]);

        $html = $this->actingAs($admin)->get(route('dss.loans', ['year' => 2026]))->assertOk()->getContent();

        $this->assertStringContainsString('DSS Approval Reasons', $html);
        $this->assertStringContainsString('Proof of income was verified by an administrator.', $html);
        $this->assertStringContainsString('Late payment history', $html);
        $this->assertStringNotContainsString('Registered Via Vida Townhomes Homeowners Association Member.', $html);
        $this->assertStringNotContainsString('Verified VVTHOA membership', $html);
        $this->assertStringNotContainsString('Address verification passed', $html);
        $this->assertStringNotContainsString('Residential address verification', $html);
        $this->assertStringNotContainsString('outside the eligible area', $html);

        $this->assertContains('Registered Via Vida Townhomes Homeowners Association Member.', $approved->fresh()->evaluated_values['approval_reasons']);
        $this->assertSame(3, count($rejected->fresh()->failed_criteria), 'Historical rejection evidence remains stored unchanged.');
    }
    public function test_pagination_changes_only_the_application_rows_not_report_totals(): void
    {
        $consumer = $this->consumer('Pagination');
        for ($i = 1; $i <= 12; $i++) {
            $loan = $this->loan($consumer, [
                'status' => 'Rejected',
                'approved_at' => null,
                'decision_at' => sprintf('2026-10-%02d 10:00:00', $i),
            ]);
            $this->saveDecision($loan, 'Rejected', sprintf('2026-10-%02d 10:00:00', $i), [[
                'id' => 'monthly_income', 'label' => 'Monthly income', 'value' => 4000,
                'required' => 5000, 'unit' => 'PHP per month', 'passed' => false, 'explanation' => 'Recorded 4000; required 5000.',
            ]]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->actingAs($admin)->get(route('dss.loans', ['year' => 2026, 'status' => 'Rejected']))->assertOk()->viewData('decisionReport');
        $this->assertSame(['approved' => 0, 'rejected' => 12], $first['summary']);
        $this->assertSame(['Approved', 'Rejected'], $first['chart']['labels']);
        $this->assertSame([0, 12], $first['chart']['counts']);
        $this->assertSame(12, $first['applications']->total());
        $this->assertCount(10, $first['applications']);

        $second = $this->actingAs($admin)->get(route('dss.loans', ['year' => 2026, 'status' => 'Rejected', 'page' => 2]))->assertOk()->viewData('decisionReport');
        $this->assertSame(['approved' => 0, 'rejected' => 12], $second['summary']);
        $this->assertSame(12, $second['applications']->total());
        $this->assertCount(2, $second['applications']);
    }

    public function test_missing_membership_data_does_not_require_dss_review(): void
    {
        $consumer = $this->consumer('Missing Membership Data');
        $consumer->update(['membership_verification_status' => '']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = $this->loan($consumer, [
            'status' => 'Rejected',
            'approved_at' => null,
            'decision_at' => '2026-10-02 10:00:00',
            'income_verification_status' => 'Verified',
            'income_verified_by' => $admin->id,
            'income_verified_at' => now(),
        ]);

        $evaluation = app(LoanApplicationEvaluationService::class)
            ->evaluateAndSave($loan->fresh('consumer'), app(DssEligibilityService::class));
        $this->actingAs($admin)->get(route('dss.loans'))->assertOk();

        $this->assertSame('Approved', $evaluation->recommendation);
        $this->assertSame('reassessment', $evaluation->evaluation_type);
        $this->assertNotContains('Membership verification status', $evaluation->missing_information);
        $this->assertEmpty($evaluation->failed_criteria);
        $this->assertSame('Rejected', $loan->fresh()->status);
    }

    public function test_empty_dataset_returns_safe_values(): void
    {
        $dss = app(LoanDssService::class)->build(['year' => 2026]);

        $this->assertSame(0, $dss['summary']['total']);
        $this->assertNull($dss['summary']['approval_rate']);
        $this->assertNull($dss['summary']['rejection_rate']);
        $this->assertNull($dss['summary']['decision_coverage']);
        $this->assertSame(0.0, $dss['summary']['outstanding_balance']);
        $this->assertNull($dss['summary']['collection_rate']);
        $this->assertNull($dss['summary']['average_approval_days']);
        $this->assertSame('success', $dss['recommendations'][0]['severity']);
        $this->assertFalse($dss['charts']['has_data']);
        $this->assertFalse($dss['charts']['monthly']['has_data']);
        $this->assertFalse($dss['charts']['overdue_locations']['has_schedule_data']);
        $this->assertFalse($dss['charts']['repayment_status']['has_schedule_data']);
        $this->assertSame([
            'total_released' => false,
            'total_repaid' => false,
            'outstanding_balance' => false,
            'overdue_amount' => false,
        ], $dss['metric_availability']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dss.loans'))
            ->assertOk()
            ->assertSee('No approved or rejected applications yet.')
            ->assertSee('N/A')
            ->assertSee('id="loanMonthlyChart"', false)
            ->assertSee('dss-overdue-empty-chart', false)
            ->assertSee('No data available yet')
            ->assertSee('Repayment status legend')
            ->assertSee('PHP');
    }

    /**
     * @return array{0: Loan, 1: Loan, 2: Loan}
     */
    private function saveDecision(Loan $loan, string $recommendation, string $evaluatedAt, array $failed = []): LoanDssEvaluation
    {
        return LoanDssEvaluation::query()->create([
            'loan_id' => $loan->id,
            'recommendation' => $recommendation,
            'evaluation_type' => 'initial',
            'criteria_version' => 'test-criteria',
            'evaluated_values' => ['test_record' => true],
            'income_evidence' => [],
            'income_verification_status' => 'Verified',
            'failed_criteria' => $failed,
            'missing_information' => [],
            'evidence_hash' => hash('sha256', $loan->id.'|'.$recommendation.'|'.$evaluatedAt),
            'evaluated_at' => $evaluatedAt,
        ]);
    }

    private function seedLoanScenario(): array
    {
        $approvedConsumer = $this->consumer('Approved');
        $pendingConsumer = $this->consumer('Pending');
        $rejectedConsumer = $this->consumer('Rejected');

        $approved = $this->loan($approvedConsumer, [
            'amount' => 1000,
            'status' => 'Approved',
            'risk_level' => 'Medium Risk',
            'applied_at' => '2026-08-01 10:00:00',
            'approved_at' => '2026-08-02 10:00:00',
            'next_due_date' => '2026-10-01',
        ]);

        $pending = $this->loan($pendingConsumer, [
            'amount' => 500,
            'status' => 'Pending Review',
            'risk_level' => 'High Risk',
            'applied_at' => '2026-09-01 10:00:00',
            'approved_at' => null,
            'next_due_date' => null,
        ]);

        $rejected = $this->loan($rejectedConsumer, [
            'amount' => 300,
            'status' => 'Rejected',
            'risk_level' => 'Low Risk',
            'applied_at' => '2026-09-15 10:00:00',
            'approved_at' => null,
            'decision_at' => '2026-09-15 10:00:00',
            'next_due_date' => null,
        ]);

        $this->installment($approved, [
            'installment_number' => 1,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-01',
            'required_amount' => 500,
            'amount_paid' => 500,
            'remaining_balance' => 0,
            'payment_date' => '2026-08-30',
            'status' => 'Paid',
        ]);

        $this->installment($approved, [
            'installment_number' => 2,
            'billing_period' => '2026-10-01',
            'due_date' => '2026-10-01',
            'required_amount' => 500,
            'amount_paid' => 0,
            'remaining_balance' => 500,
            'payment_date' => null,
            'status' => 'Overdue',
        ]);

        $this->repayment($approved, [
            'amount' => 500,
            'payment_date' => '2026-08-30',
        ]);

        return [$approved, $pending, $rejected];
    }

    private function consumer(string $suffix): Consumer
    {
        return Consumer::query()->create([
            'full_name' => 'DSS Consumer '.$suffix,
            'account_number' => 'DSS-'.$suffix,
            'meter_number' => 'DSS-M-'.$suffix,
            'status' => 'Active',
            'membership_type' => 'Member',
            'membership_verification_status' => 'Verified',
        ]);
    }

    private function loan(Consumer $consumer, array $overrides = []): Loan
    {
        return Loan::query()->create(array_merge([
            'consumer_id' => $consumer->id,
            'amount' => 1000,
            'term_months' => 6,
            'purpose' => 'DSS loan application',
            'status' => 'Approved',
            'risk_level' => 'Medium Risk',
            'monthly_installment' => 166.67,
            'applied_at' => '2026-08-01 10:00:00',
            'approved_at' => '2026-08-02 10:00:00',
            'next_due_date' => '2026-10-01',
        ], $overrides));
    }

    private function installment(Loan $loan, array $overrides = []): LoanInstallment
    {
        return LoanInstallment::query()->create(array_merge([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-01',
            'required_amount' => 500,
            'amount_paid' => 500,
            'remaining_balance' => 0,
            'payment_date' => '2026-08-30',
            'status' => 'Paid',
        ], $overrides));
    }

    private function repayment(Loan $loan, array $overrides = []): LoanRepayment
    {
        return LoanRepayment::query()->create(array_merge([
            'loan_id' => $loan->id,
            'amount' => 500,
            'payment_date' => '2026-08-30',
        ], $overrides));
    }
}
