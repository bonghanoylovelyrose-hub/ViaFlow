<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\InstallationRescheduleRequest;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanMaterialRate;
use App\Models\MeterAssignment;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanAgreementService;
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
        $terms = new \DOMDocument;
        $terms->loadHTML($document, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($terms);
        $paragraphs = $xpath->query('//div[@class="terms"]/p');
        $this->assertSame($loan->terms_text, implode("\n\n", array_map(fn ($paragraph) => $paragraph->textContent, iterator_to_array($paragraphs))));
        $this->assertSame(LoanTerms::paragraphs()[3], $xpath->evaluate('string(//div[@class="terms"]/p[5]/strong)'));
        foreach ([
            'Agreement Test Consumer', 'AGREEMENT-1', 'Loan #'.$loan->id, LoanTerms::VERSION,
            '₱500.00', '0%', '3 months', '₱166.67', '₱166.66', 'Dec 19, 2026',
            'Jan 19, 2027', 'Feb 19, 2027', 'Nov 19, 2026 9:30 AM', 'Nov 20, 2026 10:00 AM',
        ] as $value) {
            $this->assertStringContainsString($value, $document);
        }

        $bytes = $agreement->pdfBytes();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertLessThan(1024 * 1024, strlen($agreement->pdf_base64), 'The encoded PDF must fit within XAMPP MySQL\'s 1 MiB packet limit.');
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

    public function test_initial_installation_email_attaches_the_exact_saved_agreement_once(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 09:30:00'));
        config(['app.public_url' => 'https://consumers.example.test']);
        [$user, $loan] = $this->submitApplication();
        $password = $user->getRawOriginal('password');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->approveApplication($admin, $loan);
        $this->post(route('loans.agreement.release', $loan))->assertSessionHasNoErrors();
        $saved = $loan->agreement()->sole();
        $this->assertMailAttachment($loan, $saved);

        $otherConsumer = Consumer::create([
            'full_name' => 'Another Agreement Consumer',
            'email' => 'other-agreement@example.test',
            'account_number' => 'AGREEMENT-2',
            'status' => 'Active',
        ]);
        $otherLoan = $loan->fresh()->replicate()->fill(['consumer_id' => $otherConsumer->id, 'applied_by_user_id' => null]);
        $otherLoan->save();
        $otherAgreement = app(LoanAgreementService::class)->finalize($otherLoan)->refresh();
        $this->assertNotSame($saved->pdfBytes(), $otherAgreement->pdfBytes());
        Mail::mailer()->getSymfonyTransport()->flush();

        $data = [
            'meter_number' => 'MT-NEW-AGREEMENT-1',
            'installation_date' => '2026-11-23',
            'installation_time' => '14:30',
            'installation_notes' => 'Call upon arrival.',
        ];
        $this->postJson(route('loans.assign-and-schedule', $loan), $data)->assertOk()->assertJson(['success' => true]);
        $this->assertInstallationAttachment($loan, $saved);
        $this->assertDatabaseCount('loan_agreements', 2);
        $this->assertSame($saved->getAttributes(), $saved->fresh()->getAttributes());
        $this->assertSame($otherAgreement->getAttributes(), $otherAgreement->fresh()->getAttributes());

        $this->postJson(route('loans.assign-and-schedule', $loan), $data)->assertUnprocessable();
        $this->post(route('loans.installation-email.retry', $loan))->assertSessionHas('success', 'The installation confirmation email was already sent.');
        $this->assertInstallationAttachment($loan, $saved);
        $this->assertSame(1, MeterAssignment::query()->where('meter_number', $data['meter_number'])->count());
        $this->assertSame($password, $user->fresh()->getRawOriginal('password'));
        $this->assertSame($data['meter_number'], $user->fresh()->username);

        $html = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getHtmlBody();
        foreach ([
            'Your water service account is active.', 'Agreement Test Consumer', 'AGREEMENT-1',
            'New Assigned Meter Number:', 'Username:</strong> '.$user->fresh()->username,
            'Registered Email Address:</strong> '.$loan->consumer->email, '2:30 PM (Philippine Time)',
        ] as $content) {
            $this->assertStringContainsString($content, $html);
        }
        $this->assertStringNotContainsString('password', strtolower($html));
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $link = (new \DOMXPath($document))->query('//a[normalize-space(.)="Request for Reschedule"]')->item(0)->getAttribute('href');
        $this->assertStringStartsWith('https://consumers.example.test/installation/reschedule/loan/'.$loan->id.'?signature=', $link);

        auth()->logout();
        $page = $this->get($link)->assertOk()->assertSee('November 23, 2026 at 2:30 PM (Philippine Time)')->assertSee('AGREEMENT-1');
        $formAction = $page->viewData('formAction');
        $this->assertSame('consumers.example.test', parse_url($formAction, PHP_URL_HOST));
        $this->from($link)->post($formAction, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->from($link)->post($formAction, [
            'reason' => 'Work schedule conflict', 'new_installation_date' => '2026-12-01',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $request = $loan->installationRescheduleRequests()->sole();
        $this->assertSame($loan->consumer_id, $request->consumer_id);
        $this->assertSame('2026-11-23 14:30:00', $request->original_installation_date->toDateString().' '.$request->original_installation_time);
        $this->assertNull($request->new_installation_date);

        $pending = route('loans.index', ['tab' => 'pending', 'pending' => 'reschedule']);
        $this->actingAs($admin)->get($pending)->assertOk()->assertSee('Work schedule conflict')->assertSee('November 23, 2026 at 2:30 PM');
        $this->from($pending)->patch(route('installation-reschedules.assign', $request), [
            'new_installation_date' => '2026-11-26', 'new_installation_time' => '16:15',
        ])->assertSessionHasNoErrors();
        $this->assertSame(InstallationRescheduleRequest::STATUS_ASSIGNED, $request->fresh()->status);
        $this->assertSame('2026-11-26 16:15:00', $loan->fresh()->installationSchedule()->toDateTimeString());
        $this->assertSame($saved->pdfBytes(), $saved->fresh()->pdfBytes());
        $updatedEmail = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame($loan->consumer->email, $updatedEmail->getTo()[0]->getAddress());
        $this->assertStringContainsString('November 26, 2026 at 4:15 PM (Philippine Time)', $updatedEmail->getHtmlBody());

        auth()->logout();
        $this->get($link)->assertOk()->assertSee('November 26, 2026 at 4:15 PM (Philippine Time)');
        $this->actingAs($admin)->patch(route('loans.installation-status.update', $loan), [
            'installation_status' => 'Completed', 'physical_installation_confirmed' => '1',
        ])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get($link)->assertOk()->assertSee('This installation has already been completed.')
            ->assertSee('November 26, 2026 at 4:15 PM (Philippine Time)')->assertDontSee('data-reschedule-form>', false);
        $this->from($link)->post($formAction, ['reason' => 'Another time'])
            ->assertSessionHasErrors(['reason' => 'This installation has already been completed.']);
        $this->assertSame(1, $loan->installationRescheduleRequests()->count());
    }

    public function test_installation_mail_failure_preserves_the_schedule_and_finalized_pdf_for_retry(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 09:30:00'));
        [$user, $loan] = $this->submitApplication();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->approveApplication($admin, $loan);
        $blockDelivery = true;
        $this->app['events']->listen(MessageSending::class, function (MessageSending $event) use (&$blockDelivery): ?bool {
            return $blockDelivery && str_ends_with($event->message->getSubject() ?? '', 'Loan Installation Scheduled') ? false : null;
        });

        $retry = route('loans.installation-email.retry', $loan);
        $this->post($retry)->assertNotFound();
        $this->postJson(route('loans.assign-and-schedule', $loan), [
            'meter_number' => 'MT-NEW-AGREEMENT-1',
            'installation_date' => '2026-11-23',
            'installation_time' => '14:30',
            'installation_notes' => 'Call upon arrival.',
        ])->assertOk()->assertJson(['success' => true])->assertSessionHas('email_error');

        $loan->refresh();
        $saved = $loan->agreement()->sole();
        $this->assertSame('MT-NEW-AGREEMENT-1', $loan->consumer->meter_number);
        $this->assertSame('Scheduled', $loan->installation_status);
        $this->assertSame('2026-11-23', $loan->installation_date->toDateString());
        $this->assertSame('14:30', substr($loan->installation_time, 0, 5));
        $this->assertSame(['166.67', '166.67', '166.66'], $loan->installments()->orderBy('installment_number')->pluck('required_amount')->all());
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
        $delivery = ConsumerNotificationLog::query()->where('event', $loan->initialInstallationEmailEvent())->sole();
        $this->assertSame(ConsumerNotificationLog::STATUS_FAILED, $delivery->status);
        $this->get(route('loans.index', ['loan' => $loan->id]))->assertOk()
            ->assertSee('Meter and installation schedule saved.')->assertSee($retry, false)->assertSee('Retry Installation Email');
        $this->actingAs($user)->post($retry)->assertForbidden();

        $blockDelivery = false;
        $this->actingAs($admin)->post($retry)->assertSessionHas('success', 'The installation confirmation email has been sent.');
        $this->assertInstallationAttachment($loan, $saved);
        $this->assertSame(LoanAgreement::SENT, $saved->fresh()->email_status);
        $this->assertSame($saved->issued_at->toDateTimeString(), $saved->fresh()->issued_at->toDateTimeString());
        $this->assertSame($saved->pdfBytes(), $saved->fresh()->pdfBytes());
        $this->assertSame(ConsumerNotificationLog::STATUS_SENT, $delivery->refresh()->status);
        $this->assertDatabaseCount('loan_agreements', 1);

        $this->post(route('loans.agreement.release', $loan))->assertSessionHasNoErrors();
        $this->post($retry)->assertSessionHas('success', 'The installation confirmation email was already sent.');
        $this->assertInstallationAttachment($loan, $saved);
        $this->assertCount(0, $this->messages());
        $this->get(route('loans.index', ['loan' => $loan->id]))->assertOk()->assertDontSee('Retry Installation Email');
    }

    private function assertInstallationAttachment(Loan $loan, LoanAgreement $agreement): void
    {
        $email = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertStringEndsWith('Loan Installation Scheduled', $email->getSubject());
        $this->assertSame($loan->consumer->email, $email->getTo()[0]->getAddress());
        foreach (['MT-NEW-AGREEMENT-1', 'November 23, 2026', '2:30 PM', 'Call upon arrival.', 'installment schedule are attached'] as $value) {
            $this->assertStringContainsString($value, $email->getHtmlBody());
        }
        $this->assertCount(1, $email->getAttachments());
        $pdf = $email->getAttachments()[0];
        $this->assertSame($agreement->filename(), $pdf->getFilename());
        $this->assertSame('application/pdf', $pdf->getContentType());
        $this->assertSame('attachment', $pdf->getPreparedHeaders()->getHeaderBody('Content-Disposition'));
        $this->assertSame($agreement->pdfBytes(), $pdf->getBody());
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
        return Mail::mailer()->getSymfonyTransport()->messages()
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
