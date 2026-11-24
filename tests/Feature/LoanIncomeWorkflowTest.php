<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\LoanMaterialRate;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\LoanStatusNotification;
use App\Services\Dss\LoanDssService;
use App\Services\LoanIncomeOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanIncomeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private LoanIncomeOcrService $ocr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 12:00:00');
        Storage::fake('local');

        $this->ocr = new class extends LoanIncomeOcrService
        {
            public array $result = [];

            public function extract(UploadedFile $file): array
            {
                return $this->result;
            }
        };
        $this->app->instance(LoanIncomeOcrService::class, $this->ocr);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ocr_parses_name_income_and_only_converts_clear_pay_periods(): void
    {
        $service = new LoanIncomeOcrService;

        $monthly = $service->parseText("Employee Name: Loan Applicant\nMonthly Income: PHP 5,000.00\nPay Period: Monthly");
        $this->assertSame('Loan Applicant', $monthly['extracted_name']);
        $this->assertSame(5000.0, $monthly['extracted_monthly_income']);
        $this->assertSame(100, $monthly['ocr_confidence']);

        $semiMonthly = $service->parseText("Applicant Name: Loan Applicant\nSalary: PHP 2,500.00\nPay Period: Semi-monthly");
        $this->assertSame(5000.0, $semiMonthly['extracted_monthly_income']);
        $this->assertSame('semi_monthly', $semiMonthly['extracted_pay_period']);

        $unclear = $service->parseText("Name: Loan Applicant\nIncome: PHP 5,000.00");
        $this->assertNull($unclear['extracted_monthly_income']);
        $this->assertContains('Clear pay period', $unclear['missing_fields']);
    }

    public function test_ocr_failure_and_missing_trusted_verification_stay_in_needs_review(): void
    {
        [$user, $consumer] = $this->memberWithLoanCosts();
        $this->ocr->result = $this->unreadableOcr();

        $loan = $this->submit($user, 6500);

        $this->assertSame('Needs Review', $loan->status);
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
        $this->assertSame('Unreadable', $loan->income_ocr_data['ocr_status']);
        $this->assertSame('Pending Verification', $loan->income_verification_status);
        Storage::disk('local')->assertExists($loan->proof_of_income_path);

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))
            ->assertOk()
            ->assertSee('Pending Income Verification')
            ->assertSee('Loan #'.$loan->id)
            ->assertSee('Verify Proof of Income')
            ->assertSee('Confirm Verification')
            ->assertSee(route('loans.income-proof', $loan), false)
            ->assertDontSee('name="identity_confirmed"', false)
            ->assertDontSee('OCR-extracted information')
            ->assertDontSee('Verified monthly income')
            ->assertDontSee('Verification findings and supporting notes')
            ->assertDontSee('Could not verify authenticity');
        $this->actingAs($admin)->get(route('dss.loans', ['status' => 'Needs Review']))
            ->assertOk()
            ->assertSee('No approved or rejected applications yet.')
            ->assertDontSee('Loan #'.$loan->id)
            ->assertDontSee('Open Pending Income Verification')
            ->assertDontSee('Verify in Loan')
            ->assertDontSee('name="identity_confirmed"', false)
            ->assertDontSee('Save Verification and Run DSS');

        $this->actingAs(User::factory()->create(['role' => 'consumer']))
            ->get(route('loans.income-proof', $loan))
            ->assertForbidden();

        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee('pending review');
        $this->assertSame('Loan Applicant', $consumer->fresh()->full_name);
    }

    public function test_staff_can_verify_unreadable_original_evidence_and_rerun_dss_directly(): void
    {
        [$user] = $this->memberWithLoanCosts();
        $this->ocr->result = $this->unreadableOcr();
        $loan = $this->submit($user, 6200);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        $this->verify($admin, $loan, 6200, true)->assertRedirect(route('loans.index', ['tab' => 'pending', 'pending' => 'income']));

        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertDatabaseHas('loan_dss_evaluations', [
            'loan_id' => $loan->id,
            'recommendation' => 'Approved',
            'income_verification_status' => 'Verified',
        ]);
    }

    public function test_unconfirmed_authenticity_does_not_verify_or_trigger_dss(): void
    {
        [$user, $consumer] = $this->memberWithLoanCosts();
        $this->ocr->result = $this->readableOcr(6000, 'monthly');
        $loan = $this->submit($user, 6000);
        $consumer->update(['membership_verification_status' => 'Pending']);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        $verificationInput = [
            'identity_confirmed' => '1',
            'document_authentic' => '0',
            'income_source_verified' => '1',
            'verified_monthly_income' => 6000,
            'verification_notes' => 'Document authenticity could not be confirmed from the uploaded proof.',
        ];
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), $verificationInput)
            ->assertSessionHasErrors('document_authentic');

        $loan->refresh();
        $this->assertSame('Needs Review', $loan->status);
        $this->assertSame('Pending Verification', $loan->income_verification_status);
        $this->assertNull($loan->income_verified_by);
        $this->assertNull($loan->income_verified_at);
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);

        $this->actingAs($admin)->get(route('dss.loans', ['status' => 'Needs Review']))
            ->assertOk()
            ->assertSee('No approved or rejected applications yet.')
            ->assertDontSee('Membership verification is pending')
            ->assertDontSee('Verify in Loan');
    }

    public function test_exactly_five_thousand_verified_monthly_income_passes_and_routes_to_scheduling_without_funds_release(): void
    {
        [$user, $consumer] = $this->memberWithLoanCosts();
        $this->ocr->result = $this->readableOcr(5000, 'monthly');
        $loan = $this->submit($user, 5000);

        $this->assertSame('Needs Review', $loan->fresh()->status, 'OCR alone is not trusted verification.');
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->verify($admin, $loan, 5000, true)
            ->assertRedirect(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))
            ->assertSessionHas('success', 'Income verified. DSS approved the application and moved it to Ready for Scheduling.');

        $loan->refresh();
        $this->assertSame('Ready for Scheduling', $loan->status);
        $this->assertSame('Reviewed', $loan->review_status);
        $this->assertSame('Verified', $loan->income_verification_status);
        $this->assertSame($admin->id, $loan->income_verified_by);
        $this->assertNotNull($loan->income_verified_at);
        $this->assertNotNull($loan->decision_at);
        $this->assertSame(0, $loan->repayments()->count());
        $this->assertSame(0.0, app(LoanDssService::class)->build()['summary']['total_released']);
        $this->assertEquals(
            LoanDssEvaluation::query()->where('loan_id', $loan->id)->where('recommendation', 'Approved')->firstOrFail()->evaluated_at,
            $loan->decision_at,
        );

        $this->actingAs($admin)->get(route('loans.index'))->assertOk()
            ->assertDontSee('Approved Applications')
            ->assertDontSee('Decision by DSS');
        $dssPage = $this->actingAs($admin)->get(route('dss.loans'))->assertOk();
        $this->assertSame(1, $dssPage->viewData('decisionReport')['summary']['approved']);
        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'all']))->assertSee('Schedule Installation');
        $dssPage->assertSee('Approved');
        $this->assertSame($admin->name, $loan->fresh('incomeVerifier')->incomeVerifier->name);
        $this->assertSame(1, LoanDssEvaluation::query()->where('loan_id', $loan->id)->count(), 'Page reads add no evaluations.');

        $this->actingAs($user)->get(route('loans.index'))
            ->assertOk()
            ->assertSee('Your loan application has been approved. Please wait for the admin to schedule the next step.');

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $loan), [
            'installation_date' => '2026-11-12',
            'installation_time' => '09:00',
            'return_to_dss' => '1',
        ])->assertRedirect(route('dss.loans'));
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertSame('Scheduled', $loan->fresh()->installation_status);
        $this->assertSame($consumer->id, $loan->consumer_id);
        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee('Nov 12, 2026');
    }

    public function test_no_minimum_income_is_used_in_the_automatic_decision(): void
    {
        [$user] = $this->memberWithLoanCosts();
        $this->ocr->result = $this->readableOcr(4999.99, 'monthly');
        $loan = $this->submit($user, 4999.99);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        $this->verify($admin, $loan, 4999.99)->assertSessionHasNoErrors();

        $loan->refresh();
        $evaluation = LoanDssEvaluation::query()->where('loan_id', $loan->id)->latest('id')->firstOrFail();
        $this->assertSame('Ready for Scheduling', $loan->status);
        $this->assertSame('Approved', $evaluation->recommendation);
        $this->assertNull(collect($evaluation->failed_criteria)->firstWhere('id', 'monthly_income'));
        $this->assertNotEmpty($evaluation->evaluated_values['approval_reasons']);
        $this->assertSame(1, LoanDssEvaluation::query()->where('loan_id', $loan->id)->count());
        $this->actingAs($user)->get(route('loans.index'))->assertSee('View DSS approval reasons')->assertDontSee('Minimum monthly income');
    }

    public function test_late_payment_history_is_evaluated_from_verified_database_payments(): void
    {
        [$user, $consumer] = $this->memberWithLoanCosts();
        $this->addLatePaidBill($consumer);
        $this->ocr->result = $this->readableOcr(6500, 'monthly');
        $loan = $this->submit($user, 6500);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        $this->verify($admin, $loan, 6500)->assertRedirect(route('loans.index', ['tab' => 'pending', 'pending' => 'income']));

        $evaluation = LoanDssEvaluation::query()->where('loan_id', $loan->id)->latest('id')->firstOrFail();
        $lateFailure = collect($evaluation->failed_criteria)->firstWhere('id', 'late_payment_history');
        $this->assertSame(1, $lateFailure['value']);
        $this->assertSame(0, $lateFailure['required']);
        $this->assertSame('Rejected', $loan->fresh()->status);
    }

    public function test_historical_disconnection_notice_is_saved_without_an_unconfigured_adverse_threshold(): void
    {
        [$user, $consumer] = $this->memberWithLoanCosts();
        DisconnectionNotice::query()->create([
            'consumer_id' => $consumer->id,
            'status' => DisconnectionNotice::STATUS_SETTLED,
            'issued_at' => now()->subYear(),
            'settled_at' => now()->subYear()->addDays(2),
            'outstanding_amount' => 1200,
            'months_unpaid' => 3,
        ]);
        $this->ocr->result = $this->readableOcr(6000, 'monthly');
        $loan = $this->submit($user, 6000);
        $this->verify(User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']), $loan, 6000)->assertRedirect(route('loans.index', ['tab' => 'pending', 'pending' => 'income']));

        $evaluation = LoanDssEvaluation::query()->where('loan_id', $loan->id)->latest('id')->firstOrFail();
        $history = $evaluation->evaluated_values['disconnection_notice_history'];
        $this->assertCount(1, $history);
        $this->assertSame('Settled', $history[0]['status']);
        $this->assertNotContains('disconnection_notice_history', collect($evaluation->failed_criteria)->pluck('id')->all());
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
    }

    public function test_duplicate_pending_applications_and_invalid_proof_uploads_are_blocked(): void
    {
        [$user] = $this->memberWithLoanCosts();
        $this->ocr->result = $this->readableOcr(6000, 'monthly');
        $this->submit($user, 6000);

        $this->actingAs($user)->post(route('loans.store'), $this->applicationInput(6000))
            ->assertSessionHasErrors('loan');
        $this->assertDatabaseCount('loans', 1);

        [$secondUser] = $this->memberWithLoanCosts();
        $this->actingAs($secondUser)->post(route('loans.store'), [
            ...$this->applicationInput(6000),
            'proof_of_income' => UploadedFile::fake()->create('fake.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('proof_of_income');
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_consumer_can_request_admin_review_of_incorrect_rejection_information(): void
    {
        [$user] = $this->memberWithLoanCosts();
        User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $loan = Loan::query()->create([
            'consumer_id' => $user->consumer->id,
            'amount' => 500,
            'monthly_income' => 5000,
            'term_months' => 6,
            'purpose' => 'Test review request',
            'status' => 'Rejected',
            'review_status' => 'Reviewed',
            'applied_at' => now(),
            'decision_at' => now(),
        ]);

        $this->actingAs($user)->post(route('loans.request-review', $loan), [
            'review_request_reason' => 'The verified monthly amount shown is incorrect.',
        ])->assertRedirect(route('loans.index'));

        $this->assertSame('Rejected', $loan->fresh()->status);
        $this->assertNotNull($loan->fresh()->review_requested_at);
        $this->assertDatabaseHas('notifications', ['notifiable_type' => User::class, 'type' => LoanStatusNotification::class]);
    }

    private function memberWithLoanCosts(): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Loan Applicant',
            'account_number' => 'INCOME-'.$user->id,
            'meter_number' => 'M-'.$user->id,
            'status' => 'Active',
            'membership_type' => 'Member',
            'membership_verification_status' => 'Verified',
            'membership_started_at' => now()->subYear(),
        ]);
        LoanMaterialRate::query()->create([
            'water_meter_cost' => 250,
            'hose_cost' => 150,
            'other_materials_cost' => 100,
            'is_active' => true,
        ]);

        return [$user, $consumer];
    }

    private function applicationInput(float $monthlyIncome): array
    {
        $token = $this->postJson(route('loans.confirm-submission'), ['term_months' => 6, 'terms_accepted' => 1, 'confirmed' => 1])->assertOk()->json('confirmation_token');
        return [
            'terms_accepted' => 1,
            'confirmation_token' => $token,
            'monthly_income' => $monthlyIncome,
            'proof_of_income' => UploadedFile::fake()->image('income.png', 600, 400),
            'term_months' => 6,
            'purpose' => 'Water connection materials',
        ];
    }

    private function submit(User $user, float $monthlyIncome): Loan
    {
        $response = $this->actingAs($user)->post(route('loans.store'), $this->applicationInput($monthlyIncome));
        $response->assertRedirect(route('loans.index'));

        return Loan::query()->where('consumer_id', $user->consumer->id)->latest('id')->firstOrFail();
    }

    private function verify(User $admin, Loan $loan, float $verifiedIncome, bool $returnToDss = false)
    {
        return $this->actingAs($admin)->post(route('loans.verify-income', $loan), array_filter([
            'identity_confirmed' => '1',
            'document_authentic' => '1',
            'income_source_verified' => '1',
            'verified_monthly_income' => $verifiedIncome,
            'verification_notes' => 'Original document and income source checked by staff.',
            'return_to_dss' => $returnToDss ? '1' : null,
        ], fn ($value) => $value !== null));
    }

    private function readableOcr(float $amount, string $period): array
    {
        return [
            'ocr_status' => 'Readable',
            'extracted_name' => 'Loan Applicant',
            'extracted_income_amount' => $amount,
            'extracted_pay_period' => $period,
            'extracted_monthly_income' => $amount,
            'income_amount_candidates' => [$amount],
            'missing_fields' => [],
            'ocr_confidence' => 100,
            'confidence_basis' => 'readable fields only; not authenticity',
        ];
    }

    private function unreadableOcr(): array
    {
        return [
            'ocr_status' => 'Unreadable',
            'extracted_name' => null,
            'extracted_income_amount' => null,
            'extracted_pay_period' => null,
            'extracted_monthly_income' => null,
            'income_amount_candidates' => [],
            'missing_fields' => ['Applicant name', 'Income amount', 'Clear pay period'],
            'ocr_confidence' => 0,
            'confidence_basis' => 'readable fields only; not authenticity',
        ];
    }

    private function addLatePaidBill(Consumer $consumer): void
    {
        $dueDate = now()->subMonths(2)->startOfMonth()->addDays(9);
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => $dueDate->copy()->startOfMonth(),
            'due_date' => $dueDate,
            'prn' => 'LATE-INCOME-'.$consumer->id,
            'amount' => 500,
            'status' => 'Paid',
        ]);
        Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'LATE-PAY-'.$consumer->id,
            'amount' => 500,
            'payment_date' => $dueDate->copy()->addDays(4),
            'payment_method' => 'Cash',
            'status' => 'Verified',
        ]);
    }
}
