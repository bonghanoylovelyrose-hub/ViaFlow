<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\LoanStatusNotification;
use App\Services\Dss\LoanApplicationEvaluationService;
use App\Services\DssEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoanDssAutoEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 12:00:00');
        Storage::fake('local');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function application(): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'DSS Applicant',
            'account_number' => 'DSS-'.$user->id, 'meter_number' => 'M-'.$user->id,
            'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);
        $path = 'loan-income-proofs/test-'.$user->id.'.png';
        Storage::disk('local')->put($path, 'original proof');
        $loan = Loan::create(['consumer_id' => $consumer->id, 'amount' => 500, 'term_months' => 6,
            'monthly_installment' => 83.33, 'purpose' => 'Connection', 'status' => 'Needs Review',
            'review_status' => 'Pending Review', 'income_verification_status' => 'Pending Verification',
            'proof_of_income_path' => $path, 'applied_at' => now()]);

        return [$user, $admin, $consumer, $loan];
    }

    private function bill(Consumer $consumer, string $period, string $due, float $amount, array $extra = []): Bill
    {
        return Bill::create(array_merge(['consumer_id' => $consumer->id, 'bill_type' => 'Water',
            'billing_period' => $period, 'due_date' => $due, 'prn' => 'DSS-'.$consumer->id.'-'.$period,
            'amount' => $amount, 'status' => 'Unpaid'], $extra));
    }

    private function payment(Bill $bill, string $date, float $amount, string $status = 'Verified'): void
    {
        Payment::create(['bill_id' => $bill->id, 'consumer_id' => $bill->consumer_id, 'payment_type' => 'Water',
            'reference_number' => 'DSS-PAY-'.Payment::count(), 'amount' => $amount, 'payment_date' => $date,
            'payment_channel' => 'Counter', 'payment_method' => 'Cash', 'status' => $status]);
    }

    public function test_confirmation_automatically_approves_without_an_income_amount_and_notifies_both_roles_once(): void
    {
        [$user, $admin, $consumer, $loan] = $this->application();
        $previous = $this->bill($consumer, '2026-09-01', '2026-09-20', 100);
        $this->payment($previous, '2026-09-19', 100);
        $this->bill($consumer, '2026-11-01', '2026-11-20', 120);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();

        $loan->refresh();
        $evaluation = $loan->latestDssEvaluation;
        $this->assertSame('Verified', $loan->income_verification_status);
        $this->assertNull($loan->income_verified_monthly_amount);
        $this->assertSame('Approved', $evaluation->recommendation);
        $this->assertSame('Ready for Scheduling', $loan->status);
        $this->assertEquals(120.0, $evaluation->evaluated_values['water_bill_outstanding_balance']);
        $this->assertEquals(0.0, $evaluation->evaluated_values['overdue_water_balance']);
        $this->assertSame(0, $evaluation->evaluated_values['overdue_water_months']);
        $this->assertNotEmpty($evaluation->evaluated_values['approval_reasons']);
        $this->assertNull(collect($evaluation->failed_criteria)->firstWhere('id', 'monthly_income'));
        Notification::assertSentTo($user, LoanStatusNotification::class, fn ($notice) => $notice->toArray($user)['title'] === 'Loan application Approved');
        Notification::assertSentTo($admin, LoanStatusNotification::class, fn ($notice) => $notice->toArray($admin)['title'] === 'DSS loan evaluation: Approved');
        $this->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertNotFound();
        $this->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))->assertDontSee('Run DSS Evaluation');
        $this->get(route('loans.index', ['tab' => 'all']))->assertSee('DSS Approval Reasons')->assertSee('Payment History Evidence')->assertSee('Overdue water months');
        $this->actingAs($user)->get(route('loans.index'))->assertSee('View DSS approval reasons');
        $this->assertSame(1, LoanDssEvaluation::where('loan_id', $loan->id)->count());
    }

    public function test_bill_due_today_or_inside_configured_grace_is_not_overdue(): void
    {
        [$user, $admin, $consumer, $loan] = $this->application();
        $this->bill($consumer, '2026-10-01', '2026-11-05', 100);
        $this->bill($consumer, '2026-11-01', '2026-11-06', 100);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $loan->fresh()->latestDssEvaluation->recommendation);
        $this->assertEquals(0.0, $loan->fresh()->latestDssEvaluation->evaluated_values['overdue_water_balance']);
    }

    public function test_verified_late_partial_payment_and_unpaid_overdue_balance_are_recorded(): void
    {
        [$user, $admin, $consumer, $loan] = $this->application();
        $bill = $this->bill($consumer, '2026-10-01', '2026-10-20', 100);
        $this->payment($bill, '2026-10-19', 40);
        $this->payment($bill, '2026-10-25', 10);
        $this->payment($bill, '2026-10-26', 50, 'Pending');
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $evaluation = $loan->fresh()->latestDssEvaluation;
        $this->assertSame('Rejected', $evaluation->recommendation);
        $this->assertSame('Rejected', $loan->fresh()->status);
        $this->assertSame(1, $evaluation->evaluated_values['late_payments_last_six_months']);
        $this->assertSame(5, $evaluation->evaluated_values['late_payment_history'][0]['late_payments'][0]['days_late']);
        $this->assertCount(1, $evaluation->evaluated_values['late_payment_history'][0]['late_payments']);
        $this->assertEquals(50.0, $evaluation->evaluated_values['overdue_water_balance']);
        $this->assertSame(1, $evaluation->evaluated_values['overdue_water_months']);
        $this->assertNotNull(collect($evaluation->failed_criteria)->firstWhere('id', 'late_payment_history'));
        $this->assertNotNull(collect($evaluation->failed_criteria)->firstWhere('id', 'outstanding_balance'));
        $this->actingAs($admin)->get(route('dss.loans'))->assertSee('Verified late payments')->assertSee('5 day(s) late')->assertSee('Overdue water months');
        Notification::assertSentTo($admin, LoanStatusNotification::class, fn ($notice) => $notice->toArray($admin)['title'] === 'DSS loan evaluation: Rejected');
        Notification::assertSentTo($user, LoanStatusNotification::class, fn ($notice) => $notice->toArray($user)['title'] === 'Loan application Rejected');
    }

    public function test_late_payment_frequency_is_assessed_even_when_bills_are_fully_paid(): void
    {
        [$user, $admin, $consumer, $loan] = $this->application();
        $first = $this->bill($consumer, '2026-09-01', '2026-09-20', 100);
        $second = $this->bill($consumer, '2026-10-01', '2026-10-20', 100);
        $this->payment($first, '2026-09-23', 100);
        $this->payment($second, '2026-10-25', 100);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $evaluation = $loan->fresh()->latestDssEvaluation;
        $this->assertSame('Rejected', $evaluation->recommendation);
        $this->assertSame(2, $evaluation->evaluated_values['late_payments_last_six_months']);
        $this->assertCount(2, $evaluation->evaluated_values['late_payment_history']);
        $this->assertEquals(0, $evaluation->evaluated_values['overdue_water_balance']);
        $this->assertNotNull(collect($evaluation->failed_criteria)->firstWhere('id', 'late_payment_history'));
        $this->assertNull(collect($evaluation->failed_criteria)->firstWhere('id', 'outstanding_balance'));
    }

    public function test_recent_late_payment_on_an_older_bill_is_included_in_payment_history(): void
    {
        [, $admin, $consumer, $loan] = $this->application();
        $bill = $this->bill($consumer, '2026-01-01', '2026-01-20', 100);
        $this->payment($bill, '2026-10-25', 100);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $evaluation = $loan->fresh()->latestDssEvaluation;
        $this->assertSame('Rejected', $evaluation->recommendation);
        $this->assertSame(1, $evaluation->evaluated_values['late_payments_last_six_months']);
        $this->assertSame('2026-01', $evaluation->evaluated_values['late_payment_history'][0]['billing_period']);
    }

    public function test_previously_verified_pending_review_is_reconciled_once_without_rewriting_history(): void
    {
        [$user, $admin, , $loan] = $this->application();
        $loan->update(['income_verification_status' => 'Verified', 'income_verified_by' => $admin->id, 'income_verified_at' => now()]);
        $old = LoanDssEvaluation::create(['loan_id' => $loan->id, 'recommendation' => 'Needs Review', 'evaluation_type' => 'initial',
            'criteria_version' => 'previous-version', 'evaluated_values' => [], 'income_evidence' => [],
            'failed_criteria' => [], 'missing_information' => ['Verified monthly income'],
            'evidence_hash' => hash('sha256', 'previous'), 'evaluated_at' => now()->subMinute()]);
        $this->actingAs($admin)->get(route('loans.index'))->assertDontSee('Run DSS Evaluation');
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertSame('Approved', $loan->fresh()->latestDssEvaluation->recommendation);
        $this->assertSame(['Verified monthly income'], $old->fresh()->missing_information);
        $this->get(route('loans.index'))->assertOk();
        $this->assertSame(2, LoanDssEvaluation::where('loan_id', $loan->id)->count());
        Notification::assertSentToTimes($user, LoanStatusNotification::class, 1);
        Notification::assertSentToTimes($admin, LoanStatusNotification::class, 1);
    }

    public function test_carried_overdue_amounts_are_counted_once_by_original_month_and_prior_loans_are_considered(): void
    {
        [$user, $admin, $consumer, $loan] = $this->application();
        $first = $this->bill($consumer, '2026-08-01', '2026-08-20', 100);
        $second = $this->bill($consumer, '2026-09-01', '2026-09-20', 170, ['arrears_amount' => 100]);
        $third = $this->bill($consumer, '2026-11-01', '2026-11-20', 240, ['arrears_amount' => 170]);
        $first->update(['carried_forward_to_bill_id' => $second->id]);
        $second->update(['carried_forward_to_bill_id' => $third->id]);
        Loan::create(['consumer_id' => $consumer->id, 'amount' => 80, 'term_months' => 3, 'monthly_installment' => 26.67,
            'purpose' => 'Prior loan', 'status' => 'Approved', 'applied_at' => now()->subYear()]);
        DisconnectionNotice::create(['consumer_id' => $consumer->id, 'status' => DisconnectionNotice::STATUS_SETTLED,
            'issued_at' => now()->subYear(), 'settled_at' => now()->subYear()->addDays(2), 'outstanding_amount' => 100, 'months_unpaid' => 3]);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $values = $loan->fresh()->latestDssEvaluation->evaluated_values;
        $this->assertEquals(240.0, $values['water_bill_outstanding_balance']);
        $this->assertEquals(170.0, $values['overdue_water_balance']);
        $this->assertSame(2, $values['overdue_water_months']);
        $this->assertEquals(80.0, $values['previous_loan_outstanding_balance']);
        $this->assertCount(2, $values['overdue_water_history']);
        $this->assertCount(1, $values['disconnection_notice_history']);
        $this->assertSame('Rejected', $loan->fresh()->status);
    }

    public function test_unverified_proof_cannot_be_evaluated(): void
    {
        [, , , $loan] = $this->application();
        try {
            app(LoanApplicationEvaluationService::class)->evaluateAndSave($loan->load('consumer'), app(DssEligibilityService::class));
            $this->fail('DSS must require verified proof.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
        }
    }

    public function test_pending_membership_does_not_block_and_repeated_evaluation_is_not_duplicated(): void
    {
        [$user, $admin, $consumer, $loan] = $this->application();
        $consumer->update(['membership_verification_status' => 'Pending']);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $loan->fresh()->latestDssEvaluation->recommendation);
        $this->post(route('loans.evaluate-verified-income', $loan))->assertSessionHasNoErrors();
        $this->post(route('loans.evaluate-verified-income', $loan))->assertSessionHasNoErrors();
        $this->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))->assertDontSee('Run DSS Evaluation');
        $this->assertSame(1, LoanDssEvaluation::where('loan_id', $loan->id)->count());
        Notification::assertSentToTimes($user, LoanStatusNotification::class, 1);
        Notification::assertSentToTimes($admin, LoanStatusNotification::class, 1);
    }
}
