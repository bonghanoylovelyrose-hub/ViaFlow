<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanMaterialRate;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanIncomeOcrService;
use App\Support\LoanTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LoanAgreementDeliveryTest extends TestCase
{
    use RefreshDatabase;
    private $captureMessages;

    public function test_accepted_agreement_is_issued_once_after_explicit_release_and_remains_available_after_payment(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 09:30:00'));
        [$user, $loan] = $this->submitApplication();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertSame(LoanTerms::text(3), $loan->terms_text);
        $this->assertSame(LoanTerms::VERSION, $loan->terms_version);
        $this->assertSame('2026-11-19 09:30:00', $loan->terms_agreed_at->toDateTimeString());
        $this->actingAs($admin)->post(route('loans.agreement.release', $loan))->assertSessionHasErrors('agreement');
        $this->approveApplication($admin, $loan);

        $this->actingAs($admin)->get(route('loans.index', ['loan' => $loan->id]))
            ->assertOk()->assertSee('Release Installment Schedule');
        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertDontSee('View Loan Agreement');
        $this->get(route('loans.agreement.show', $loan))->assertNotFound();
        $this->post(route('loans.agreement.release', $loan))->assertForbidden();
        $this->assertDatabaseCount('loan_agreements', 0);
        $this->assertCount(0, $this->messages());

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => '127.0.0.1',
                'port' => (int) getenv('LOAN_AGREEMENT_CAPTURE_PORT'),
                'auto_tls' => false,
                'timeout' => 5,
            ],
        ]);
        $this->captureMessages = collect();
        $this->app['events']->listen(\Illuminate\Mail\Events\MessageSent::class, function ($event): void {
            $this->captureMessages->push($event->sent->getSymfonySentMessage());
        });
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));
        $this->actingAs($admin)->post(route('loans.agreement.release', $loan))->assertSessionHasNoErrors();
        $loan->refresh();
        $agreement = $loan->agreement()->firstOrFail();
        $this->assertSame('2026-11-20 10:00:00', $agreement->issued_at->toDateTimeString());
        $this->assertSame(LoanAgreement::SENT, $agreement->email_status);
        $this->assertNotNull($agreement->email_sent_at);
        $this->assertSame($loan->consumer->email, $agreement->email_recipient);

        $installments = $loan->installments()->orderBy('installment_number')->get();
        $this->assertSame(['166.67', '166.67', '166.66'], $installments->pluck('required_amount')->all());
        $this->assertSame(
            ['2026-12-19', '2027-01-19', '2027-02-19'],
            $installments->map(fn ($installment) => $installment->due_date->toDateString())->all(),
        );
        $this->assertSame(LoanTerms::text(3), $loan->terms_text);
        $this->assertSame(LoanTerms::VERSION, $loan->terms_version);
        $this->assertSame('2026-11-19 09:30:00', $loan->terms_agreed_at->toDateTimeString());

        $document = view('shared.loans.agreement-pdf', [
            'loan' => $loan,
            'installments' => $installments,
            'issuedAt' => $agreement->issued_at,
            'total' => $installments->sum('required_amount'),
        ])->render();
        $this->assertStringContainsString(e($loan->terms_text), $document);
        foreach ([
            'Agreement Test Consumer', 'AGREEMENT-1', 'Loan #'.$loan->id, LoanTerms::VERSION,
            '₱500.00', '0%', '3 months', '₱166.67', '₱166.66', 'Dec 19, 2026',
            'Jan 19, 2027', 'Feb 19, 2027', 'Nov 19, 2026 9:30 AM', 'Nov 20, 2026 10:00 AM',
        ] as $value) {
            $this->assertStringContainsString($value, $document);
        }

        $bytes = $agreement->pdfBytes();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertMailAttachment($loan, $agreement);

        $this->actingAs($user)->get(route('loans.index'))->assertOk()
            ->assertSee('View Loan Agreement')->assertSee('Download PDF');
        $inline = $this->get(route('loans.agreement.show', $loan))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="'.$agreement->filename().'"');
        $download = $this->get(route('loans.agreement.show', ['loan' => $loan, 'download' => 1]))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$agreement->filename().'"');
        $this->assertSame($bytes, $inline->getContent());
        $this->assertSame($bytes, $download->getContent());
        file_put_contents(getenv('LOAN_AGREEMENT_CAPTURE_DIR').'/viewed.pdf', $download->getContent());

        $this->actingAs(User::factory()->create(['role' => 'consumer']))
            ->get(route('loans.agreement.show', $loan))->assertForbidden();

        $this->actingAs($admin)->post(route('loans.agreement.release', $loan))->assertSessionHasNoErrors();
        $this->post(route('loans.agreement.retry-email', $loan))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('loan_agreements', 1);
        $this->assertCount(1, $this->messages());

        $this->actingAs($user)->post(route('payments.store'), [
            'loan_id' => $loan->id,
            'payment_type' => 'Loan',
            'amount' => 500,
            'payment_date' => today()->toDateString(),
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'AGREEMENT-FULL-PAYMENT',
        ])->assertSessionHasNoErrors();
        $payment = Payment::query()->where('gateway_reference', 'AGREEMENT-FULL-PAYMENT')->sole();
        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
        $this->patch(route('loans.update', $loan), ['review_status' => 'Completed'])->assertSessionHasNoErrors();
        $this->assertSame('Completed', $loan->fresh()->status);
        $this->assertSame(0.0, $loan->fresh()->balance_remaining);
        $this->actingAs($user)->get(route('loans.index'))->assertOk()
            ->assertSee('Fully Paid')->assertSee('View Loan Agreement');
        $this->assertSame($bytes, $this->get(route('loans.agreement.show', $loan))->assertOk()->getContent());
    }

    public function test_failed_email_preserves_document_and_only_admin_can_retry_with_the_same_attachment(): void
    {
        [$user, $loan] = $this->submitApplication();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->approveApplication($admin, $loan);
        $blockDelivery = true;
        $this->app['events']->listen(MessageSending::class, function (MessageSending $event) use (&$blockDelivery): void {
            if ($blockDelivery && str_contains($event->message->getSubject() ?? '', 'Agreement and Installment Schedule')) {
                throw new RuntimeException('Simulated mail transport failure.');
            }
        });

        $this->post(route('loans.agreement.release', $loan))->assertSessionHasNoErrors();
        $agreement = $loan->agreement()->firstOrFail();
        $bytes = $agreement->pdfBytes();
        $this->assertSame(LoanAgreement::FAILED, $agreement->email_status);
        $this->assertNull($agreement->email_sent_at);
        $this->assertCount(0, $this->messages());

        $this->get(route('loans.index', ['loan' => $loan->id]))->assertOk()->assertSee('Retry Agreement Email');
        $this->post(route('loans.agreement.retry-email', $loan))->assertSessionHasNoErrors();
        $this->assertSame(LoanAgreement::FAILED, $agreement->fresh()->email_status);
        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee('View Loan Agreement');
        $this->assertSame($bytes, $this->get(route('loans.agreement.show', $loan))->assertOk()->getContent());
        $this->post(route('loans.agreement.retry-email', $loan))->assertForbidden();

        $blockDelivery = false;
        $this->actingAs($admin)->post(route('loans.agreement.release', $loan))->assertSessionHasNoErrors();
        $this->assertCount(0, $this->messages(), 'A duplicate release does not retry failed delivery.');
        $this->post(route('loans.agreement.retry-email', $loan))->assertSessionHasNoErrors();

        $this->assertSame(LoanAgreement::SENT, $agreement->fresh()->email_status);
        $this->assertSame($bytes, $agreement->fresh()->pdfBytes());
        $this->assertDatabaseCount('loan_agreements', 1);
        $this->assertMailAttachment($loan, $agreement);
    }

    public function test_release_requires_both_historical_acceptance_and_a_complete_saved_schedule(): void
    {
        [, $loan] = $this->submitApplication();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->approveApplication($admin, $loan);

        $loan->update(['terms_text' => null]);
        $this->post(route('loans.agreement.release', $loan))->assertSessionHasErrors('agreement');

        $loan->update(['terms_text' => LoanTerms::text(3), 'installment_schedule_snapshot' => []]);
        $this->post(route('loans.agreement.release', $loan))->assertSessionHasErrors('agreement');
        $this->assertDatabaseCount('loan_agreements', 0);
        $this->assertCount(0, $this->messages());
    }

    private function submitApplication(): array
    {
        Storage::fake('local');
        $this->app->instance(LoanIncomeOcrService::class, new class extends LoanIncomeOcrService
        {
            public function extract(UploadedFile $file): array
            {
                return [];
            }
        });

        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create([
            'user_id' => $user->id,
            'full_name' => 'Agreement Test Consumer',
            'email' => 'agreement-consumer@example.test',
            'account_number' => 'AGREEMENT-1',
            'meter_number' => 'MT-AGREEMENT-1',
            'status' => 'Active',
            'membership_type' => 'Member',
            'membership_verification_status' => 'Verified',
            'membership_started_at' => now()->subYear(),
        ]);
        LoanMaterialRate::create(['water_meter_cost' => 250, 'hose_cost' => 150, 'other_materials_cost' => 100, 'is_active' => true]);

        $token = $this->actingAs($user)->postJson(route('loans.confirm-submission'), [
            'term_months' => 3,
            'terms_accepted' => 1,
            'confirmed' => 1,
        ])->assertOk()->json('confirmation_token');
        $this->post(route('loans.store'), [
            'term_months' => 3,
            'purpose' => 'Water connection materials',
            'proof_of_income' => UploadedFile::fake()->image('proof.png'),
            'terms_accepted' => 1,
            'confirmation_token' => $token,
        ])->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        return [$user, $consumer->loans()->sole()];
    }

    private function approveApplication(User $admin, Loan $loan): void
    {
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), [
            'identity_confirmed' => 1,
            'document_authentic' => 1,
            'income_source_verified' => 1,
            'verified_monthly_income' => 6200,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
    }

    private function messages()
    {
        return ($this->captureMessages ?? Mail::mailer()->getSymfonyTransport()->messages())
            ->filter(fn ($sent) => str_contains($sent->getOriginalMessage()->getSubject() ?? '', 'Agreement and Installment Schedule'))
            ->values();
    }

    private function assertMailAttachment(Loan $loan, LoanAgreement $agreement): void
    {
        $messages = $this->messages();
        $this->assertCount(1, $messages);
        $email = $messages->sole()->getOriginalMessage();
        $this->assertSame($loan->consumer->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('complete installment schedule are attached', $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $attachment = $email->getAttachments()[0];
        $this->assertSame($agreement->filename(), $attachment->getFilename());
        $this->assertSame('application', $attachment->getMediaType());
        $this->assertSame('pdf', $attachment->getMediaSubtype());
        $this->assertSame($agreement->pdfBytes(), $attachment->getBody());
    }
}
