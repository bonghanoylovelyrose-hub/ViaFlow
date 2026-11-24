<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ConsumerEmailUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AdminConnectionApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(): array
    {
        return [
            'first_name' => 'Office',
            'last_name' => 'Applicant',
            'email' => 'office.connection.qa@gmail.com',
            'service_country' => 'Philippines',
            'service_city' => 'Panabo City',
            'service_barangay_subdivision' => 'Via Vida',
            'service_purok' => 'Purok 1-C',
            'address_block' => 'Block 5',
            'address_street' => 'Vista Street',
            'contact_number' => '09171234567',
            'property_type' => 'Residential',
            'ownership_status' => 'Owner',
        ];
    }

    private function pending(string $type = 'applications'): string
    {
        return route('consumers.index', ['account_category' => 'pending', 'pending_type' => $type]);
    }

    public function test_office_application_with_documents_enters_pending_verification(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('consumers.index'))->assertOk()
            ->assertSeeInOrder(['Are you a VVTHOA member?', 'Consumer Information', 'Requirements Submission'])
            ->assertSee('enctype="multipart/form-data"', false);
        $payload = $this->applicant() + [
            'is_viohoa_member' => 'no',
            'acknowledgment_accepted' => 1,
            'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('id.png'),
            'status' => 'Active',
            'meter_number' => 'FORGED-METER',
        ];
        $this->post(route('consumers.store'), $payload)->assertRedirect($this->pending())->assertSessionHasNoErrors();

        $consumer = Consumer::query()->sole();
        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertNull($consumer->meter_number);
        $this->assertNull($consumer->account_number);
        $this->assertNull($consumer->user_id);
        $this->assertSame(ServiceRequest::STATUS_PENDING, $consumer->serviceRequests()->sole()->status);
        Storage::disk('local')->assertExists($consumer->valid_id_path);
        $this->get($this->pending())->assertOk()->assertSee($consumer->full_name)->assertSee('Pending Verification');
        $this->get($this->pending('all'))->assertOk()->assertSee($consumer->full_name);
        $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk()
            ->assertViewHas('consumers', fn ($rows) => $rows->isEmpty());
    }

    public function test_applicant_uploads_missing_membership_documents_from_email_then_receives_approval(): void
    {
        Storage::fake('local');
        $rejectMail = true;
        $pending = $this->pending();
        $this->app['events']->listen(MessageSending::class, function () use (&$rejectMail): void {
            if ($rejectMail) {
                throw new TransportException('451 Temporary SMTP rejection');
            }
        });
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('consumers.store'), $this->applicant() + [
            'is_viohoa_member' => 'yes',
            'housing_rights_documented' => 'yes',
            'resident_of_community' => 'yes',
            'association_member' => 'yes',
            'association_registered' => 'yes',
            'agreement_accepted' => 1,
            'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('id.png'),
        ])->assertRedirect($this->pending())->assertSessionHasNoErrors()->assertSessionHas('email_error');

        $consumer = Consumer::query()->sole();
        $originalId = $consumer->valid_id_path;
        $request = $consumer->serviceRequests()->sole();
        $this->assertSame(Consumer::STATUS_PENDING_REQUIREMENTS, $consumer->status);
        $sendUrl = route('consumers.send-requirements-link', $consumer);
        $this->get($this->pending())->assertOk()->assertSee('Pending Requirements')->assertDontSee('Send Requirements Link')
            ->assertSee('data-bs-target="#consumerActivateModal"', false)->assertSee('Missing Requirements')
            ->assertDontSee('Request Missing Documents');
        $this->get($this->pending('all'))->assertOk()->assertSee($consumer->full_name);
        $this->from($this->pending())->post($sendUrl)->assertRedirect($this->pending())
            ->assertSessionMissing('success')->assertSessionHas('email_error');
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id, 'event' => ConsumerNotificationLog::EVENT_CONNECTION_REQUIREMENTS_REQUESTED,
            'status' => ConsumerNotificationLog::STATUS_FAILED, 'error_message' => '451 Temporary SMTP rejection', 'sent_at' => null,
        ]);

        $rejectMail = false;
        $this->from($this->pending())->post($sendUrl)->assertRedirect($this->pending())
            ->assertSessionHas('success', "The mail server accepted the requirements link email for {$consumer->email}.");
        $this->assertDatabaseCount('consumers', 1);
        $this->assertDatabaseCount('service_requests', 1);
        $this->assertDatabaseCount('consumer_notification_logs', 1);
        $this->assertSame(ConsumerNotificationLog::STATUS_SENT, ConsumerNotificationLog::query()->sole()->status);

        $schedule = now()->addWeek()->toDateString();
        $approval = ['meter_number' => 'MT-OFFICE-QA', 'installation_date' => $schedule, 'installation_time' => '10:30', 'membership_type' => 'Member', 'membership_verification_confirmed' => 1];
        $this->patch(route('consumers.activate', $consumer), $approval)->assertSessionHasErrors('requirements');
        $this->patch(route('consumers.update', $consumer), [
            'full_name' => $consumer->full_name,
            'email' => $consumer->email,
            'status' => 'For Review',
            'membership_type' => 'Member',
        ])->assertSessionHasErrors('status');
        $this->assertNull($consumer->fresh()->user_id);

        $transport = Mail::mailer()->getSymfonyTransport();
        $requirementsEmail = $transport->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $requirementsEmail->getTo()[0]->getAddress());
        $document = new \DOMDocument;
        $document->loadHTML($requirementsEmail->getHtmlBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $html = $requirementsEmail->getHtmlBody();
        $this->assertStringContainsString($consumer->full_name, $html);
        $this->assertStringContainsString($consumer->application_reference, $html);
        $this->assertStringContainsString('VVTHOA membership document photos', $html);
        $this->assertStringNotContainsString('Valid ID (', $html);
        $link = (new \DOMXPath($document))->evaluate('string(//a[normalize-space(.)="Upload Requirements"]/@href)');
        $this->assertStringStartsWith('https://consumers.example.test/', $link);

        auth()->logout();
        $otherApplication = Consumer::query()->create(['full_name' => 'Other Applicant', 'status' => Consumer::STATUS_PENDING_REQUIREMENTS]);
        $this->get(route('connection.requirements.edit', $consumer))->assertForbidden();
        $swapConsumer = fn (string $url) => str_replace('/'.$consumer->id.'/requirements', '/'.$otherApplication->id.'/requirements', $url);
        $this->get($swapConsumer($link))->assertForbidden();
        $page = $this->get($link)->assertOk()
            ->assertSee('Submit Connection Requirements')->assertSee('VVTHOA Membership Document')
            ->assertDontSee('name="valid_id_file"', false)->assertHeader('Referrer-Policy', 'no-referrer');
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $submission = (new \DOMXPath($document))->evaluate('string(//form[@enctype="multipart/form-data"]/@action)');
        $this->post($swapConsumer($submission))->assertForbidden();
        $this->from($link)->post($submission)->assertRedirect($link)->assertSessionHasErrors('membership_document_photos');
        $this->assertSame(Consumer::STATUS_PENDING_REQUIREMENTS, $consumer->fresh()->status);
        $this->post($submission, [
            'membership_document_type' => 'Proof of Residency',
            'membership_document_photos' => [UploadedFile::fake()->image('membership.png')],
            'valid_id_file' => UploadedFile::fake()->image('unexpected.png'),
            'status' => 'Active',
        ])->assertRedirect($link)->assertSessionHasNoErrors();
        $consumer->refresh();
        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertSame(Consumer::STATUS_PENDING_REQUIREMENTS, $otherApplication->fresh()->status);
        $this->assertNull($consumer->meter_number);
        $this->assertNull($consumer->user_id);
        $this->assertSame($originalId, $consumer->valid_id_path);
        $this->assertSame($request->id, $consumer->serviceRequests()->sole()->id);
        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertCount(1, $transport->messages());
        Storage::disk('local')->assertExists($consumer->membership_document_paths[0]);
        $this->get($link)->assertOk()->assertSee('Pending Verification');

        $this->actingAs($admin)->get($pending)->assertOk()->assertSee($consumer->application_reference)->assertSee('Pending Verification');
        $this->from($pending)->post($sendUrl)->assertRedirect($pending)->assertSessionHasErrors('requirements');
        $this->patch(route('consumers.activate', $consumer), $approval)->assertSessionHasErrors('requirements_reviewed');
        $this->assertSame('Pending Verification', $consumer->fresh()->status);
        $this->patch(route('consumers.activate', $consumer), $approval + ['requirements_reviewed' => 1])->assertSessionHasNoErrors();
        $consumer->refresh();
        $this->assertSame('Active', $consumer->status);
        $this->assertSame($approval['meter_number'], $consumer->meter_number);
        $this->assertSame($schedule, $consumer->installation_date->toDateString());
        $this->assertSame(ServiceRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id, 'event' => ConsumerNotificationLog::EVENT_CONNECTION_APPROVED, 'status' => ConsumerNotificationLog::STATUS_SENT,
        ]);
        $approvalEmail = $transport->messages()->get(1)->getOriginalMessage();
        $this->assertSame($consumer->email, $approvalEmail->getTo()[0]->getAddress());
        $this->assertStringContainsString($approval['meter_number'], $approvalEmail->getHtmlBody());
        $this->assertStringContainsString($consumer->installation_date->format('F d, Y'), $approvalEmail->getHtmlBody());
        $this->assertStringContainsString('Request for Reschedule', $approvalEmail->getHtmlBody());
    }

    public function test_requirements_email_rejects_loopback_links_and_non_delivery_mailers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Office Applicant', 'email' => $this->applicant()['email'], 'status' => Consumer::STATUS_PENDING_REQUIREMENTS,
        ]);
        $url = route('consumers.send-requirements-link', $consumer);
        $this->actingAs($admin);

        config(['app.public_url' => 'http://127.0.0.1:8000']);
        $this->post($url)->assertSessionHas('email_error')->assertSessionMissing('success');
        $attempt = ConsumerNotificationLog::query()->sole();
        $this->assertSame(ConsumerNotificationLog::STATUS_FAILED, $attempt->status);
        $this->assertStringContainsString('Set APP_PUBLIC_URL', $attempt->error_message);

        config(['app.public_url' => 'https://consumers.example.test']);
        foreach (['log', 'failover'] as $mailer) {
            config(['mail.default' => $mailer]);
            $this->post($url)->assertSessionHas('email_error')->assertSessionMissing('success');
            $attempt->refresh();
            $this->assertSame(ConsumerNotificationLog::STATUS_FAILED, $attempt->status);
            $this->assertStringContainsString('needs a mail transport', $attempt->error_message);
        }
        $this->assertNull($attempt->sent_at);
    }

    public function test_pending_requirements_can_be_reviewed_and_rejected_with_all_missing_documents(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = $this->pending();
        $this->actingAs($admin)->post(route('consumers.store'), $this->applicant() + [
            'is_viohoa_member' => 'no', 'acknowledgment_accepted' => 1,
        ])->assertRedirect($pending);
        $consumer = Consumer::query()->sole();
        $requestId = $consumer->serviceRequests()->sole()->id;
        $this->get($pending)->assertOk()->assertDontSee('Send Requirements Link')->assertSee('data-bs-target="#consumerActivateModal"', false);
        $this->patch(route('consumers.activate', $consumer))->assertSessionHasErrors('requirements');
        $this->patch(route('consumers.reject', $consumer), [
            'rejection_reason' => 'Confirm the service street and upload your identification.',
            'correction_fields' => ['address_street'],
        ])->assertSessionHasErrors('requirements_reviewed');
        $this->patch(route('consumers.reject', $consumer), [
            'requirements_reviewed' => 1, 'rejection_reason' => 'Confirm the service street and upload your identification.',
            'correction_fields' => ['address_street'],
        ])->assertSessionHasNoErrors()->assertSessionMissing('email_error');

        $this->assertSame('Rejected', $consumer->refresh()->status);
        $this->assertEqualsCanonicalizing(['address_street', 'valid_id_file', 'valid_id_presented'], $consumer->rejection_fields);
        $email = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame($consumer->email, $email->getTo()[0]->getAddress());
        $document = new \DOMDocument;
        $document->loadHTML($email->getHtmlBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $link = (new \DOMXPath($document))->evaluate('string(//a[normalize-space(.)="Resubmit Requirements"]/@href)');
        auth()->logout();
        $page = $this->get($link)->assertOk()->assertSee('Confirm the service street and upload your identification.');
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $action = (new \DOMXPath($document))->evaluate('string(//form[@enctype="multipart/form-data"]/@action)');
        $this->from($link)->post($action, ['address_street' => 'New Street'])
            ->assertSessionHasErrors(['valid_id_presented', 'valid_id_file']);
        $this->post($action, [
            'address_street' => 'New Street', 'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('valid-id.png'),
        ])->assertRedirect($link)->assertSessionHasNoErrors();
        $this->assertSame('Pending Verification', $consumer->refresh()->status);
        $this->assertSame($requestId, $consumer->serviceRequests()->sole()->id);
        $this->assertNull($consumer->meter_number);
        $this->assertDatabaseCount('consumers', 1);
    }

    public function test_xampp_loopback_mail_links_use_the_actual_apache_mount(): void
    {
        config(['app.public_url' => 'http://127.0.0.1:8000', 'app.url' => 'http://127.0.0.1:8000']);
        $this->app->instance('env', 'local');
        $request = Request::create('http://localhost/waterbillingsystems/public/consumers/1/reject', 'PATCH', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'SCRIPT_NAME' => '/waterbillingsystems/public/index.php',
        ]);
        $urls = app(ConsumerEmailUrl::class);

        $this->assertSame('http://localhost/waterbillingsystems/public/connection/application/1/resubmit',
            $urls->forConnectionApplication('/connection/application/1/resubmit', $request));
        $this->assertSame('http://localhost/waterbillingsystems/public/login', $urls->forPasswordReset('/login', $request));

        $request->server->set('REMOTE_ADDR', '192.0.2.50');
        $this->expectExceptionMessage('Set APP_PUBLIC_URL');
        $urls->forConnectionApplication('/connection/application/1/resubmit', $request);
    }

    #[TestWith(['office'])]
    #[TestWith(['online'])]
    public function test_both_submission_methods_use_the_same_review_and_email_workflow(string $origin): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = $this->pending();
        $memberDetails = [
            'housing_rights_documented' => 'yes', 'resident_of_community' => 'yes',
            'association_member' => 'yes', 'association_registered' => 'yes', 'agreement_accepted' => 1,
            'membership_document_type' => 'Proof of Residency',
            'membership_document_photos' => [UploadedFile::fake()->image('residency.png')],
        ];
        $applicant = $this->applicant() + [
            'valid_id_presented' => 'National ID', 'valid_id_file' => UploadedFile::fake()->image('valid-id.png'),
        ];

        if ($origin === 'online') {
            $this->post(route('register.type.store'), ['is_viohoa_member' => 'yes'])->assertRedirect(route('register.member-details'));
            $this->post(route('register.member-details.store'), $memberDetails)->assertRedirect(route('register.new-connection'));
            $this->post(route('register.store'), $applicant)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        } else {
            $this->actingAs($admin)->post(route('consumers.store'), $applicant + $memberDetails + ['is_viohoa_member' => 'yes'])
                ->assertRedirect($pending)->assertSessionHasNoErrors();
        }

        $consumer = Consumer::query()->sole();
        $connection = $consumer->serviceRequests()->sole();
        $originalId = $consumer->valid_id_path;
        $originalMembership = $consumer->membership_document_paths;
        $this->assertTrue($consumer->valid_id_submitted);
        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertNull($consumer->user_id);
        $this->assertNull($consumer->meter_number);
        $this->assertSame(ServiceRequest::STATUS_PENDING, $connection->status);

        $rejectUrl = route('consumers.reject', $consumer);
        $approvalUrl = route('consumers.activate', $consumer);
        $reason = 'Please upload clearer photos of the VVTHOA document.';
        $this->actingAs($admin)->get($pending)->assertOk()->assertSee($consumer->full_name)
            ->assertSee('data-bs-target="#consumerActivateModal"', false)->assertSee('I have reviewed the submitted connection requirements.');
        $this->patch($rejectUrl, ['requirements_reviewed' => 1])->assertSessionHasErrors(['rejection_reason', 'correction_fields']);
        $this->patch($rejectUrl, ['rejection_reason' => $reason, 'correction_fields' => ['membership_document_photos']])
            ->assertSessionHasErrors('requirements_reviewed');
        $this->patch($rejectUrl, ['requirements_reviewed' => 1, 'rejection_reason' => $reason, 'correction_fields' => ['membership_document_photos']])
            ->assertSessionHasNoErrors()->assertSessionMissing('email_error');

        $this->assertSame('Rejected', $consumer->fresh()->status);
        $this->assertSame(ServiceRequest::STATUS_REJECTED, $connection->fresh()->status);
        $transport = Mail::mailer()->getSymfonyTransport();
        $rejection = $transport->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $rejection->getTo()[0]->getAddress());
        $this->assertStringContainsString($reason, $rejection->getHtmlBody());
        $document = new \DOMDocument;
        $document->loadHTML($rejection->getHtmlBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $link = (new \DOMXPath($document))->evaluate('string(//a[normalize-space(.)="Resubmit Requirements"]/@href)');
        $this->assertStringStartsWith('https://consumers.example.test/', $link);

        auth()->logout();
        $page = $this->get($link)->assertOk()->assertSee('VVTHOA Membership Document Photos');
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $submission = (new \DOMXPath($document))->evaluate('string(//form[@enctype="multipart/form-data"]/@action)');
        $this->post($submission, [
            'membership_document_type' => 'Proof of Residency',
            'membership_document_photos' => [UploadedFile::fake()->image('clear-photo.png')],
        ])->assertRedirect($link)->assertSessionHasNoErrors();
        $consumer->refresh();
        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertSame($originalId, $consumer->valid_id_path);
        $this->assertNotSame($originalMembership, $consumer->membership_document_paths);
        $this->assertSame($connection->id, $consumer->serviceRequests()->sole()->id);
        $this->assertSame(ServiceRequest::STATUS_PENDING, $connection->fresh()->status);
        $this->assertNull($consumer->user_id);

        $this->actingAs($admin)->get($pending)->assertOk()->assertSee($consumer->full_name)->assertSee('Pending Verification');
        $approval = [
            'requirements_reviewed' => 1, 'membership_verification_confirmed' => 1, 'membership_type' => 'Member',
            'meter_number' => 'VERIFY-'.$origin, 'installation_date' => now()->addWeek()->toDateString(), 'installation_time' => '10:30',
        ];
        $this->patch($approvalUrl, $approval)->assertSessionHasNoErrors()->assertSessionMissing('email_error');
        $consumer->refresh();
        $this->assertSame('Active', $consumer->status);
        $this->assertSame($approval['meter_number'], $consumer->meter_number);
        $this->assertSame($approval['installation_date'], $consumer->installation_date->toDateString());
        $this->assertSame(ServiceRequest::STATUS_APPROVED, $connection->fresh()->status);
        $this->assertCount(3, $transport->messages());
        $approved = $transport->messages()->get(1)->getOriginalMessage();
        $this->assertSame($consumer->email, $approved->getTo()[0]->getAddress());
        $this->assertStringContainsString('Your connection request has been approved', $approved->getHtmlBody());
        $this->assertStringContainsString($consumer->installation_date->format('F d, Y'), $approved->getHtmlBody());
        $confirmation = $transport->messages()->last()->getOriginalMessage();
        $this->assertSame($consumer->email, $confirmation->getTo()[0]->getAddress());
        $this->assertStringContainsString('Water Connection Installation Confirmed', $confirmation->getSubject());
        $this->assertStringContainsString($consumer->installation_date->format('F d, Y'), $confirmation->getHtmlBody());
        $this->assertStringContainsString($consumer->meter_number, $confirmation->getHtmlBody());
        $this->assertStringContainsString('Assigned Meter Number (Username)', $confirmation->getHtmlBody());
        $this->assertStringContainsString('Set Up Your Password', $confirmation->getHtmlBody());
        $this->assertStringContainsString('10:30 AM (Philippine Time)', $confirmation->getHtmlBody());
        $this->assertStringContainsString('Request for Reschedule', $confirmation->getHtmlBody());
        $this->assertSame($consumer->meter_number, $consumer->user->username);
        $this->assertStringNotContainsString('Temporary Password', $approved->getHtmlBody());
        $document->loadHTML($confirmation->getHtmlBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $setupLink = (new \DOMXPath($document))->evaluate('string(//a[normalize-space(.)="Set Up Your Password"]/@href)');
        $this->assertNotSame('', $setupLink);
        $this->get($setupLink)->assertOk()->assertSee('Set Your Password');
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id, 'event' => ConsumerNotificationLog::EVENT_CONNECTION_INSTALLATION_CONFIRMED,
            'status' => ConsumerNotificationLog::STATUS_SENT,
        ]);
        $this->patch($approvalUrl, $approval)->assertSessionHasNoErrors();
        $this->assertCount(3, $transport->messages());
    }
}
