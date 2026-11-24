<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ConnectionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ConnectionApplicationResubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function application(array $overrides = []): Consumer
    {
        $consumer = Consumer::create(array_merge([
            'full_name' => 'Rejected Applicant',
            'email' => 'rejected.applicant@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Non-Member',
            'requested_membership_type' => 'Non-Member',
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
            'property_type' => 'Residential',
            'ownership_status' => 'Owner',
            'valid_id_presented' => 'National ID',
            'address_block' => 'Block 5',
            'address_street' => 'Old Street',
            'service_purok' => 'Purok 1-C',
            'service_address' => 'Block 5, Old Street, Purok 1-C, Via Vida, Panabo City, Philippines',
            'contact_number' => '09171234567',
        ], $overrides));

        app(ConnectionRequestService::class)->createForApplication($consumer);

        return $consumer;
    }

    private function rejectApplication(Consumer $consumer, array $payload = []): User
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch(route('consumers.reject', $consumer), array_merge([
                'requirements_reviewed' => 1,
                'rejection_reason' => 'Please correct the requirements identified by the administrator.',
                'correction_fields' => [
                    'valid_id_file',
                    'proof_of_residency_file',
                    'address_block',
                    'address_street',
                    'contact_number',
                ],
            ], $payload))
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        return $admin;
    }

    public function test_rejection_email_contains_a_working_edit_and_resubmit_button(): void
    {
        $consumer = $this->application();
        $captured = [];

        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$captured): bool {
                $captured = ['view' => $view, 'data' => $data];

                return $view === 'emails.consumer.connection-rejected';
            })->andReturn(true);

        $this->rejectApplication($consumer, [
            'rejection_reason' => 'Your Valid ID is blurry and the Contact Number is incorrect.',
            'correction_fields' => ['valid_id_file', 'contact_number'],
        ]);

        $this->assertNotEmpty($captured);

        $html = view($captured['view'], $captured['data'])->render();

        $this->assertStringContainsString('Resubmit Requirements', $html);
        $this->assertStringStartsWith('https://consumers.example.test/', $captured['data']['resubmitUrl']);
        $this->assertStringContainsString(e($captured['data']['resubmitUrl']), $html);
        $this->assertContains('Valid ID', $captured['data']['correctionLabels']);
        $this->assertContains('Contact Number', $captured['data']['correctionLabels']);

        $this->get($captured['data']['resubmitUrl'])
            ->assertOk()
            ->assertSee('Edit and Resubmit Application')
            ->assertSee('Rejection Reason')
            ->assertSee('Your Valid ID is blurry and the Contact Number is incorrect.')
            ->assertSee('Needs correction')
            ->assertSee('name="valid_id_file"', false)
            ->assertSee('name="contact_number"', false)
            ->assertDontSee('name="full_name"', false);
    }

    public function test_correction_link_requires_a_valid_signature(): void
    {
        Mail::fake();
        $consumer = $this->application();
        $this->rejectApplication($consumer);

        $this->get(route('connection.resubmit.edit', ['consumer' => $consumer]))
            ->assertForbidden();

        $this->get(route('connection.resubmit.edit', ['consumer' => $consumer]).'?signature=invalid')
            ->assertForbidden();
    }

    public function test_resubmission_updates_only_the_flagged_fields_and_documents(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('requirements/valid-id/old-id.pdf', 'old id');
        Storage::disk('local')->put('requirements/proof-of-residency/keep-proof.pdf', 'keep proof');

        $consumer = $this->application([
            'full_name' => 'Original Name',
            'email' => 'original@example.com',
            'valid_id_path' => 'requirements/valid-id/old-id.pdf',
            'proof_of_residency_path' => 'requirements/proof-of-residency/keep-proof.pdf',
        ]);

        $admin = $this->rejectApplication($consumer, [
            'rejection_reason' => 'Block and Contact Number are wrong; replace the Valid ID.',
            'correction_fields' => ['address_block', 'contact_number', 'valid_id_file'],
        ]);

        $url = URL::temporarySignedRoute(
            'connection.resubmit.store',
            now()->addDay(),
            ['consumer' => $consumer->id],
        );

        $response = $this->post($url, [
            'address_block' => 'Block 9',
            'contact_number' => '09179998888',
            'full_name' => 'Hacked Name',
            'address_street' => 'Hacked Street',
            'valid_id_file' => UploadedFile::fake()->createWithContent('new-id.pdf', '%PDF-1.4 replacement id'),
            'proof_of_residency_file' => UploadedFile::fake()->createWithContent('hacked-proof.pdf', '%PDF-1.4 hacked proof'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $consumer->refresh();

        $this->assertSame('Block 9', $consumer->address_block);
        $this->assertSame('09179998888', $consumer->contact_number);
        $this->assertSame('Original Name', $consumer->full_name);
        $this->assertSame('Old Street', $consumer->address_street);
        $this->assertSame(
            'Block 9, Old Street, Purok 1-C, Via Vida, Panabo City, Philippines',
            $consumer->service_address,
        );

        $this->assertNotSame('requirements/valid-id/old-id.pdf', $consumer->valid_id_path);
        $this->assertTrue(Storage::disk('local')->exists($consumer->valid_id_path));
        $this->assertSame(
            'requirements/proof-of-residency/keep-proof.pdf',
            $consumer->proof_of_residency_path,
        );

        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertNotNull($consumer->resubmitted_at);
        $this->assertSame('Block and Contact Number are wrong; replace the Valid ID.', $consumer->rejection_reason);

        $connectionRequest = $consumer->serviceRequests()
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->firstOrFail();

        $this->assertSame(ServiceRequest::STATUS_PENDING, $connectionRequest->status);
        $this->assertNull($connectionRequest->reviewed_at);
        $this->assertNull($connectionRequest->reviewed_by_user_id);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
        ]);
    }

    public function test_resubmitting_after_the_application_was_already_reopened_is_a_no_op(): void
    {
        Mail::fake();
        $consumer = $this->application();
        $this->rejectApplication($consumer);

        $url = URL::temporarySignedRoute(
            'connection.resubmit.store',
            now()->addDay(),
            ['consumer' => $consumer->id],
        );

        $consumer->refresh();
        $consumer->update(['status' => 'Pending Verification']);

        $this->post($url, [
            'contact_number' => '09170000000',
        ])->assertRedirect();

        $consumer->refresh();

        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertSame('09171234567', $consumer->contact_number);
    }

    public function test_a_second_rejection_sends_a_fresh_correction_email(): void
    {
        Storage::fake('local');
        $consumer = $this->application();
        $admin = $this->rejectApplication($consumer);

        $this->actingAs($admin)
            ->post(URL::temporarySignedRoute('connection.resubmit.store', now()->addDay(), ['consumer' => $consumer->id]), [
                'address_block' => 'Block 8',
                'address_street' => 'New Street',
                'contact_number' => '09178887777',
                'valid_id_file' => UploadedFile::fake()->createWithContent('id.pdf', '%PDF-1.4 corrected id'),
                'proof_of_residency_file' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 corrected proof'),
            ])
            ->assertRedirect();

        $consumer->refresh();
        $this->assertSame('Pending Verification', $consumer->status);

        $captured = [];

        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$captured): bool {
                $captured = ['view' => $view, 'data' => $data];

                return $view === 'emails.consumer.connection-rejected';
            })->andReturn(true);

        $this->actingAs($admin)
            ->patch(route('consumers.reject', $consumer), [
                'requirements_reviewed' => 1,
                'rejection_reason' => 'The Contact Number is still incorrect.',
                'correction_fields' => ['contact_number'],
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $this->assertNotEmpty($captured);

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => ConsumerNotificationLog::EVENT_CONNECTION_REJECTED.'_1',
            'status' => ConsumerNotificationLog::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => ConsumerNotificationLog::EVENT_CONNECTION_REJECTED.'_2',
            'status' => ConsumerNotificationLog::STATUS_SENT,
        ]);
    }

    public function test_admin_can_resend_the_correction_link_for_a_rejected_application(): void
    {
        $consumer = $this->application();
        $admin = $this->rejectApplication($consumer);

        $captured = [];

        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$captured): bool {
                $captured = ['view' => $view, 'data' => $data];

                return $view === 'emails.consumer.connection-rejected';
            })->andReturn(true);

        $this->actingAs($admin)
            ->post(route('consumers.resend-correction-link', $consumer))
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHas('success');

        $this->assertNotEmpty($captured);
        $this->get($captured['data']['resubmitUrl'])->assertOk();

        $this->actingAs($admin)
            ->get(route('consumers.index'))
            ->assertOk()
            ->assertSee('Re-send Correction Link')
            ->assertViewHas('correctionFieldsByConsumer', fn ($fields) => in_array('Valid ID', $fields[$consumer->id], true));
    }
}
