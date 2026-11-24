<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanMaterialRate;
use App\Models\User;
use App\Notifications\LoanStatusNotification;
use App\Services\Dss\LoanApplicationEvaluationService;
use App\Services\LoanIncomeOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanManualIncomeVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
        Storage::fake('local');
        $this->app->instance(LoanIncomeOcrService::class, new class extends LoanIncomeOcrService {
            public function extract(UploadedFile $file): array { return []; }
        });
    }

    private function applicant(): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create([
            'user_id' => $user->id, 'full_name' => 'Loan Applicant', 'account_number' => 'TEST-'.$user->id,
            'meter_number' => 'M-'.$user->id, 'status' => 'Active', 'membership_type' => 'Member',
            'membership_verification_status' => 'Verified', 'membership_started_at' => now()->subYear(),
        ]);
        LoanMaterialRate::create(['water_meter_cost' => 250, 'hose_cost' => 150, 'other_materials_cost' => 100, 'is_active' => true]);

        return [$user, $consumer];
    }

    private function payload(UploadedFile $proof): array
    {
        $token = $this->postJson(route('loans.confirm-submission'), ['term_months' => 6, 'terms_accepted' => 1, 'confirmed' => 1])->assertOk()->json('confirmation_token');
        return ['proof_of_income' => $proof, 'term_months' => 6, 'purpose' => 'Connection materials', 'terms_accepted' => 1, 'confirmation_token' => $token];
    }

    public function test_direct_verification_url_opens_review_or_shows_the_saved_correction_status(): void
    {
        [$user] = $this->applicant();
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))
            ->assertSessionHasNoErrors();
        $loan = Loan::query()->sole();
        $url = route('loans.verify-income', $loan);
        $this->get($url)->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $queueUrl = route('loans.index', ['tab' => 'pending', 'pending' => 'income', 'loan' => $loan->id]);
        $this->actingAs($admin)->get($url)->assertRedirect($queueUrl);
        $this->get($queueUrl)->assertOk()->assertSee('Confirm Verification')->assertSee('data-income-verification="'.$loan->id.'"', false);
        $this->assertSame('Pending Verification', $loan->fresh()->income_verification_status);
        $this->assertDatabaseCount('loan_dss_evaluations', 0);

        $loan->update(['income_verification_status' => 'Rejected', 'income_rejection_reason' => 'The uploaded document could not be read.']);
        $applicationUrl = route('loans.index', ['tab' => 'all', 'loan' => $loan->id]);
        $this->get($url)->assertRedirect($applicationUrl);
        $this->get($applicationUrl)->assertOk()->assertSee('needs correction. The consumer must upload a replacement')
            ->assertSee('data-admin-loan="'.$loan->id.'"', false)->assertDontSee('Confirm Verification');
        $this->assertSame('Rejected', $loan->fresh()->income_verification_status);
        $this->assertDatabaseCount('loan_dss_evaluations', 0);
    }

    public function test_consumer_uploads_pdf_admin_verifies_and_consumer_sees_result(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $page = $this->actingAs($user)->get(route('loans.index'))->assertOk();
        $page->assertDontSee('name="monthly_income"', false)->assertSee('name="proof_of_income"', false)
            ->assertSee('application/pdf', false)->assertSee('Your monthly income will be reviewed and verified by the administrator.');
        $document = new \Dompdf\Dompdf();
        $document->loadHtml('<p>Proof of income test document</p>');
        $document->render();
        $pdf = UploadedFile::fake()->createWithContent('income.pdf', $document->output());
        $this->post(route('loans.store'), $this->payload($pdf) + [
            'monthly_income' => 999999, 'income_verified_monthly_amount' => 999999, 'income_verification_status' => 'Verified',
        ])->assertSessionHasNoErrors();
        $loan = Loan::query()->firstOrFail();
        $this->assertNull($loan->monthly_income);
        $this->assertNull($loan->income_verified_monthly_amount);
        $this->assertSame('Pending Verification', $loan->income_verification_status);
        $this->assertSame('Needs Review', $loan->status);
        Storage::disk('local')->assertExists($loan->proof_of_income_path);
        $this->get(route('loans.index'))->assertSee('Income verification: Pending Verification');
        $this->post(route('loans.verify-income', $loan), [])->assertForbidden();
        $this->actingAs($admin)->get(route('loans.income-proof', $loan))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))
            ->assertSee('Verify Proof of Income')->assertSee('Proof of income PDF')->assertSee(route('loans.income-proof', $loan), false)
            ->assertSee('Confirm Verification')->assertSee('Reject Verification')
            ->assertDontSee('Record verification findings')->assertDontSee('Identity verification')
            ->assertDontSee('Document verification')->assertDontSee('Income source verification')
            ->assertDontSee('Verified monthly income')->assertDontSee('Verification findings and supporting notes')
            ->assertDontSee('name="identity_confirmed"', false)->assertDontSee('name="document_authentic"', false)
            ->assertDontSee('name="income_source_verified"', false)->assertDontSee('name="verified_monthly_income"', false)
            ->assertDontSee('name="verification_notes"', false);
        $this->post(route('loans.verify-income', $loan), [
            'identity_confirmed' => '1', 'document_authentic' => '1', 'income_source_verified' => '1',
            'verified_monthly_income' => 5000, 'verification_notes' => 'Document and source checked.',
        ])->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame('Verified', $loan->income_verification_status);
        $this->assertEquals(5000, $loan->income_verified_monthly_amount);
        $this->assertSame('Ready for Scheduling', $loan->status);
        $this->assertSame('Reviewed', $loan->review_status);
        $this->assertSame('Document and source checked.', $loan->income_verification_notes);
        $this->assertSame($admin->id, $loan->income_verified_by);
        $this->assertTrue($loan->income_ocr_data['staff_verification']['identity_confirmed']);
        $this->assertDatabaseHas('loan_dss_evaluations', ['loan_id' => $loan->id, 'recommendation' => 'Approved']);
        Storage::disk('local')->assertExists($loan->proof_of_income_path);
        $this->actingAs($user)->get(route('loans.index'))->assertSee('Income verification: Verified');
    }

    public function test_missing_or_invalid_document_is_rejected_and_admin_can_reject_proof_with_reason(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($user)->post(route('loans.store'), ['term_months' => 6, 'purpose' => 'Materials'])->assertSessionHasErrors('proof_of_income');
        $this->post(route('loans.store'), $this->payload(UploadedFile::fake()->createWithContent('fake.pdf', 'not a pdf')))->assertSessionHasErrors('proof_of_income');
        $this->post(route('loans.store'), $this->payload(UploadedFile::fake()->createWithContent('truncated.pdf', "%PDF-1.4\n%%EOF")))->assertSessionHasErrors('proof_of_income');
        $validImage = UploadedFile::fake()->image('source.png', 400, 400);
        $this->post(route('loans.store'), $this->payload(UploadedFile::fake()->createWithContent('broken.png', substr(file_get_contents($validImage->getPathname()), 0, 40))))->assertSessionHasErrors('proof_of_income');
        $this->post(route('loans.store'), $this->payload(UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')))->assertSessionHasErrors('proof_of_income');
        $this->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $loan->update(['income_verification_notes' => 'Existing staff findings']);
        $path = $loan->proof_of_income_path;
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'reject', 'rejection_reason' => ''])->assertSessionHasErrors('rejection_reason');
        $this->assertSame('Pending Verification', $loan->fresh()->income_verification_status);
        Mail::shouldReceive('send')->once()->withArgs(function ($view, $data, $callback) use ($user): bool {
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email());
            $callback($message);
            return $view === 'emails.consumer.loan-income-rejected'
                && $data['reason'] === 'Document is too unclear to read.'
                && $message->getSymfonyMessage()->getTo()[0]->getAddress() === $user->email
                && str_contains(view($view, $data)->render(), 'Document is too unclear to read.')
                && str_contains(view($view, $data)->render(), 'correct the issue and resubmit');
        });
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'reject', 'rejection_reason' => 'Document is too unclear to read.'])->assertSessionHasNoErrors();
        $this->assertSame('Rejected', $loan->fresh()->income_verification_status);
        $this->assertNull($loan->fresh()->income_verified_monthly_amount);
        $this->assertSame('Needs Review', $loan->fresh()->status);
        $this->assertSame('Document is too unclear to read.', $loan->fresh()->income_rejection_reason);
        $this->assertSame('Existing staff findings', $loan->fresh()->income_verification_notes);
        $this->assertSame($admin->id, $loan->fresh()->income_verified_by);
        $this->assertSame($path, $loan->fresh()->proof_of_income_path);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
        $response = $this->actingAs($user)->get(route('loans.index'))
            ->assertSee('Income verification: Needs Correction')
            ->assertSee('Proof of Income Needs Correction')
            ->assertSee('Your loan application has NOT been rejected. Please upload a new Proof of Income document to continue the verification process.')
            ->assertSee('Reason from admin:')->assertSee('Document is too unclear to read.')
            ->assertSee('Upload New Proof of Income')->assertSee('Submit Replacement')
            ->assertSee('Selected proof of income preview')->assertSee('Selected proof of income PDF')
            ->assertDontSee('Income verification: Rejected');
        $html = $response->getContent();
        $article = substr($html, strpos($html, '<article class="loan-application-card"'));
        $article = substr($article, 0, strpos($article, '</article>'));
        $this->assertLessThan(strpos($article, 'Reason from admin:'), strpos($article, 'Proof of Income Needs Correction'));
        $this->assertLessThan(strpos($article, 'Upload New Proof of Income'), strpos($article, 'Reason from admin:'));
        $this->assertStringNotContainsString('Applicant Information', $article);
        $this->get(route('loans.payment-breakdown', $loan))->assertSee('Applicant Information')->assertSee('Loan Applicant');
        $this->actingAs($admin)->patch(route('loans.update', $loan), ['review_status' => 'Reviewed'])->assertSessionHasErrors('review_status');
    }

    public function test_only_owner_can_replace_rejected_proof_and_previous_rejection_is_retained(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $other = User::factory()->create(['role' => 'consumer']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('first.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $oldPath = $loan->proof_of_income_path;
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'reject', 'rejection_reason' => 'The uploaded image is unreadable.'])->assertSessionHasNoErrors();
        $this->post(route('loans.evaluate-verified-income', $loan))->assertStatus(409);
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
        $replacement = UploadedFile::fake()->image('replacement.png', 900, 900);
        $expected = hash_file('sha256', $replacement->getPathname());
        $this->actingAs($other)->post(route('loans.income-proof.replace', $loan), ['proof_of_income' => $replacement])->assertForbidden();
        $this->actingAs($user)->get(route('loans.index'))->assertSee('Submit Replacement');
        $this->post(route('loans.income-proof.replace', $loan), ['proof_of_income' => $replacement])->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame('Pending Verification', $loan->income_verification_status);
        $this->assertSame('Needs Review', $loan->status);
        $this->assertNotSame($oldPath, $loan->proof_of_income_path);
        Storage::disk('local')->assertExists([$oldPath, $loan->proof_of_income_path]);
        $this->assertSame($expected, hash('sha256', Storage::disk('local')->get($loan->proof_of_income_path)));
        $this->assertSame('The uploaded image is unreadable.', $loan->income_ocr_data['prior_proof_rejections'][0]['reason']);
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
        $notice = $admin->notifications()->where('type', LoanStatusNotification::class)->get()
            ->first(fn ($notification) => $notification->data['title'] === 'Replacement income proof submitted');
        $this->assertNotNull($notice);
        $this->assertSame(route('loans.index', ['tab' => 'pending', 'pending' => 'income']), $notice->data['url']);
        $this->post(route('loans.income-proof.replace', $loan), ['proof_of_income' => UploadedFile::fake()->image('again.png')])->assertNotFound();
        $this->actingAs($admin)->get(route('loans.income-proof', $loan))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))->assertSee('Verify Proof of Income')->assertSee(route('loans.income-proof', $loan), false);
        $this->post(route('loans.verify-income', $loan), [
            'identity_confirmed' => '1', 'document_authentic' => '1', 'income_source_verified' => '1',
            'verified_monthly_income' => 5000, 'verification_notes' => 'Replacement document verified.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertDatabaseHas('loan_dss_evaluations', ['loan_id' => $loan->id, 'recommendation' => 'Approved']);
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_correction_accepts_supported_replacements_and_defers_dss_until_admin_verifies(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('initial.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $document = new \Dompdf\Dompdf();
        $document->loadHtml('<p>Replacement income document</p>');
        $document->render();
        $files = [
            UploadedFile::fake()->image('replacement.jpg', 600, 600),
            UploadedFile::fake()->image('replacement.png', 600, 600),
            UploadedFile::fake()->createWithContent('replacement.pdf', $document->output()),
        ];

        foreach ($files as $index => $file) {
            $this->actingAs($admin)->post(route('loans.verify-income', $loan), [
                'decision' => 'reject', 'rejection_reason' => 'Please upload a clearer income document.',
            ])->assertSessionHasNoErrors();
            $previousPath = $loan->fresh()->proof_of_income_path;
            if ($index === 0) {
                $this->actingAs($user)->post(route('loans.income-proof.replace', $loan), ['proof_of_income' => UploadedFile::fake()->create('document.txt', 5, 'text/plain')])->assertSessionHasErrors('proof_of_income');
                $this->post(route('loans.income-proof.replace', $loan), ['proof_of_income' => UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('proof_of_income');
                $this->assertSame('Rejected', $loan->fresh()->income_verification_status);
                $this->assertSame($previousPath, $loan->fresh()->proof_of_income_path);
            }
            $this->actingAs($user)->post(route('loans.income-proof.replace', $loan), ['proof_of_income' => $file])->assertSessionHasNoErrors();
            $loan->refresh();
            $this->assertSame('Pending Verification', $loan->income_verification_status);
            $this->assertSame('Needs Review', $loan->status);
            $this->assertCount($index + 1, $loan->income_ocr_data['prior_proof_rejections']);
            Storage::disk('local')->assertExists([$previousPath, $loan->proof_of_income_path]);
            $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
            $this->actingAs($admin)->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))
                ->assertSee('Loan #'.$loan->id)->assertSee('Verify Proof of Income');
            $this->post(route('loans.evaluate-verified-income', $loan))->assertStatus(409);
            $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
        }

        $this->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('Verified', $loan->fresh()->income_verification_status);
        $this->assertDatabaseHas('loan_dss_evaluations', ['loan_id' => $loan->id, 'income_verification_status' => 'Verified']);
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_confirmation_runs_dss_and_decides_the_loan(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.jpg')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), [
            'identity_confirmed' => '1', 'document_authentic' => '1', 'income_source_verified' => '1',
            'verified_monthly_income' => 4999, 'verification_notes' => 'Document and source checked.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertSame('Verified', $loan->fresh()->income_verification_status);
        $this->assertDatabaseHas('loan_dss_evaluations', ['loan_id' => $loan->id, 'recommendation' => 'Approved']);
    }

    public function test_confirmation_requires_all_verification_checks_and_rejection_survives_mail_failure(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), [
            'identity_confirmed' => '1', 'document_authentic' => '0', 'income_source_verified' => '1',
            'verified_monthly_income' => 5000, 'verification_notes' => 'Document cannot be verified.',
        ])->assertSessionHasErrors('document_authentic');
        $this->assertSame('Pending Verification', $loan->fresh()->income_verification_status);
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->post(route('loans.verify-income', $loan), ['decision' => 'reject', 'rejection_reason' => 'Document is not readable.'])->assertSessionHasNoErrors();
        $this->assertSame('Rejected', $loan->fresh()->income_verification_status);
        $this->post(route('loans.verify-income', $loan), ['decision' => 'reject', 'rejection_reason' => 'Another rejection reason.'])->assertStatus(409);
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
    }

    public function test_confirmation_without_findings_preserves_evidence_and_runs_dss(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $evidence = ['ocr_status' => 'Readable', 'extracted_monthly_income' => 8000, 'previous_findings' => 'retained'];
        $loan->update(['income_ocr_data' => $evidence, 'income_verification_notes' => 'Existing verification notes']);
        $path = $loan->proof_of_income_path;
        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))
            ->assertSee('Uploaded Proof of Income', false)->assertSee('data-admin-enlarge', false);
        $this->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame('Verified', $loan->income_verification_status);
        $this->assertSame($admin->id, $loan->income_verified_by);
        $this->assertNull($loan->income_verified_monthly_amount);
        $this->assertSame('Existing verification notes', $loan->income_verification_notes);
        $this->assertSame($evidence, $loan->income_ocr_data);
        $this->assertSame($path, $loan->proof_of_income_path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('Ready for Scheduling', $loan->status);
        $this->assertSame('Approved', $loan->latestDssEvaluation->recommendation);
        $this->assertNotContains('Verified monthly income', $loan->latestDssEvaluation->missing_information);
    }

    public function test_confirmation_without_findings_keeps_previously_verified_income_and_notes(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $evidence = ['staff_verification' => ['identity_confirmed' => true, 'document_authentic' => true, 'income_source_verified' => true, 'notes' => 'Original notes']];
        $loan->update(['income_ocr_data' => $evidence, 'income_verified_monthly_amount' => 5000, 'income_verification_notes' => 'Original notes']);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertEquals(5000, $loan->income_verified_monthly_amount);
        $this->assertSame('Original notes', $loan->income_verification_notes);
        $this->assertSame($evidence, $loan->income_ocr_data);
        $this->assertSame('Ready for Scheduling', $loan->status);
    }

    public function test_dss_failure_rolls_back_confirmation_and_allows_retry(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $evaluation = \Mockery::mock(LoanApplicationEvaluationService::class);
        $evaluation->shouldReceive('evaluateAndSave')->once()->andThrow(new \RuntimeException('DSS unavailable'));
        $this->app->instance(LoanApplicationEvaluationService::class, $evaluation);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), [
            'identity_confirmed' => '1', 'document_authentic' => '1', 'income_source_verified' => '1',
            'verified_monthly_income' => 5000, 'verification_notes' => 'Document and source checked.',
        ])->assertStatus(500);
        $this->assertSame('Pending Verification', $loan->fresh()->income_verification_status);
        $this->assertSame('Needs Review', $loan->fresh()->status);
        $this->assertDatabaseMissing('loan_dss_evaluations', ['loan_id' => $loan->id]);
    }

    public function test_previously_verified_pending_application_is_evaluated_automatically_once(): void
    {
        [$user] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $loan->update([
            'income_verification_status' => 'Verified', 'income_verified_by' => $admin->id,
            'income_verified_at' => now(), 'income_verified_monthly_amount' => 5000,
            'income_ocr_data' => ['staff_verification' => [
                'identity_confirmed' => true, 'document_authentic' => true, 'income_source_verified' => true,
            ]],
        ]);
        $this->post(route('loans.evaluate-verified-income', $loan))->assertForbidden();
        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))->assertDontSee('Run DSS Evaluation');
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertDatabaseHas('loan_dss_evaluations', ['loan_id' => $loan->id, 'recommendation' => 'Approved']);
        $this->get(route('loans.index', ['tab' => 'pending', 'pending' => 'income']))->assertDontSee('Run DSS Evaluation');
        $this->assertSame(1, \App\Models\LoanDssEvaluation::query()->where('loan_id', $loan->id)->count());
    }

    public function test_pending_membership_does_not_prevent_dss_approval(): void
    {
        [$user, $consumer] = $this->applicant();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('loans.store'), $this->payload(UploadedFile::fake()->image('income.png')))->assertSessionHasNoErrors();
        $loan = Loan::firstOrFail();
        $consumer->update(['membership_verification_status' => 'Pending']);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), [
            'identity_confirmed' => '1', 'document_authentic' => '1', 'income_source_verified' => '1',
            'verified_monthly_income' => 5000, 'verification_notes' => 'Document and source checked.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Verified', $loan->fresh()->income_verification_status);
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertSame('Approved', $loan->fresh()->latestDssEvaluation->recommendation);
        $this->assertNotContains('Membership verification is pending', $loan->fresh()->latestDssEvaluation->missing_information);
    }
}
