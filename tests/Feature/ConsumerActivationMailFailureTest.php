<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ConsumerActivationMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_failure_does_not_fail_or_undo_consumer_activation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Mail Failure Applicant',
            'email' => 'mail.failure@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP credentials rejected'));
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Consumer notification could not be sent.',
                Mockery::on(fn (array $context): bool => $context['recipient'] === 'mail.failure@example.com'
                    && $context['event'] === \App\Models\ConsumerNotificationLog::EVENT_CONNECTION_APPROVED
                    && $context['exception'] === \RuntimeException::class
                    && $context['message'] === 'SMTP credentials rejected')
            );

        $response = $this->actingAs($admin)->patch(route('consumers.activate', $consumer), [
            'meter_number' => 'MT-MAIL-001',
            'membership_type' => 'Member',
            'membership_verification_confirmed' => true,
            'installation_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertRedirect(route('consumers.index'));
        $response->assertSessionMissing('success');
        $response->assertSessionHas('email_error');
        $response->assertSessionMissing('activation_temporary_password');

        $this->assertDatabaseHas('consumers', [
            'id' => $consumer->id,
            'status' => 'Active',
            'meter_number' => 'MT-MAIL-001',
        ]);
        $this->assertNotNull($consumer->fresh()->user_id);

        // The failure is recorded so it can be diagnosed and retried later.
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => \App\Models\ConsumerNotificationLog::EVENT_CONNECTION_APPROVED,
            'status' => \App\Models\ConsumerNotificationLog::STATUS_FAILED,
            'error_message' => 'SMTP credentials rejected',
        ]);
    }

    public function test_a_failed_notification_can_be_retried_successfully_afterwards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Retry Applicant',
            'email' => 'retry.applicant@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);

        $payload = [
            'meter_number' => 'MT-RETRY-001',
            'membership_type' => 'Member',
            'membership_verification_confirmed' => true,
            'installation_date' => now()->addWeek()->toDateString(),
        ];

        // First attempt fails (e.g. SMTP credentials not yet fixed).
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP credentials rejected'));

        $this->actingAs($admin)
            ->patch(route('consumers.activate', $consumer), $payload)
            ->assertSessionHas('email_error');

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => \App\Models\ConsumerNotificationLog::EVENT_CONNECTION_APPROVED,
            'status' => \App\Models\ConsumerNotificationLog::STATUS_FAILED,
        ]);

        // Retry once the mail server issue is resolved.
        $consumer->refresh();
        $originalHash = $consumer->user->password;
        $originalAccount = $consumer->account_number;
        Mail::shouldReceive('send')->once()->with(
            'emails.consumer.account-activated',
            Mockery::on(fn (array $data): bool => strlen($data['temporaryPassword']) === 8
                && Hash::check($data['temporaryPassword'], $originalHash)),
            Mockery::type(\Closure::class),
        )->andReturn(true);

        $this->actingAs($admin)
            ->post(route('consumers.resend-credentials', $consumer))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertSessionMissing('email_error');
        $this->assertSame($originalHash, $consumer->user->fresh()->password);
        $this->assertSame($originalAccount, $consumer->fresh()->account_number);

        // The failed row is updated in place rather than colliding.
        $this->assertSame(1, \App\Models\ConsumerNotificationLog::query()
            ->where('consumer_id', $consumer->id)
            ->where('event', \App\Models\ConsumerNotificationLog::EVENT_CONNECTION_APPROVED)
            ->count());

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'status' => \App\Models\ConsumerNotificationLog::STATUS_SENT,
            'error_message' => null,
        ]);
    }

    public function test_admin_can_resend_login_details_to_an_activated_consumer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Resend Applicant',
            'email' => 'resend.applicant@example.com',
            'account_number' => 'RS-1',
            'meter_number' => 'MT-RS-1',
            'status' => 'Active',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
            'installation_date' => now()->addWeek()->toDateString(),
        ]);

        $originalPassword = $consumerUser->password;
        $expectedConsumerId = $consumer->id;

        $captured = null;
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.consumer.account-activated',
                Mockery::on(function (array $data) use (&$captured, $expectedConsumerId): bool {
                    $captured = $data;

                    return ($data['consumer']->id ?? null) === $expectedConsumerId;
                }),
                Mockery::type(\Closure::class)
            )->andReturn(true);

        $this->actingAs($admin)
            ->post(route('consumers.resend-credentials', $consumer))
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHas('success', 'Login details re-sent to resend.applicant@example.com.');

        $this->assertNotEmpty($captured['passwordSetupUrl']);
        $this->assertNull($captured['temporaryPassword']);
        $this->assertSame($originalPassword, $consumerUser->fresh()->password);

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => \App\Models\ConsumerNotificationLog::EVENT_CREDENTIALS_RESENT,
            'status' => \App\Models\ConsumerNotificationLog::STATUS_SENT,
        ]);
    }

    public function test_resend_repairs_an_old_failed_approval_without_replacing_the_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer', 'must_change_password' => true]);
        $consumer = Consumer::create([
            'user_id' => $user->id,
            'full_name' => 'Previously Approved Applicant',
            'email' => $user->email,
            'status' => 'Active',
            'account_number' => 'LEGACY-1',
            'meter_number' => 'MT-LEGACY-1',
        ]);
        \App\Models\ConsumerNotificationLog::create([
            'consumer_id' => $consumer->id,
            'event' => \App\Models\ConsumerNotificationLog::EVENT_CONNECTION_APPROVED,
            'channel' => 'email',
            'recipient' => $consumer->email,
            'status' => 'failed',
        ]);

        $hash = $user->password;
        $this->actingAs($admin)->post(route('consumers.resend-credentials', $consumer))
            ->assertSessionHas('success')->assertSessionMissing('email_error');

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $html = $message->getHtmlBody();
        preg_match('/id="temporary-password">([A-Za-z0-9]{8})</', $html, $match);
        $this->assertNotEmpty($match[1]);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseHas('consumer_notification_logs', ['consumer_id' => $consumer->id, 'event' => 'connection_approved', 'status' => 'sent']);

        auth()->logout();
        $this->post(route('login.attempt'), ['login' => $consumer->meter_number, 'password' => $match[1]])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('temporaryPasswordReminderModal');
    }

public function test_resend_is_refused_when_the_mail_server_is_down(): void
{
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Resend Failure Applicant',
            'email' => 'resend.failure@example.com',
            'account_number' => 'RS-2',
            'meter_number' => 'MT-RS-2',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP credentials rejected'));

        $passwordBefore = $consumerUser->password;

        $this->actingAs($admin)
            ->post(route('consumers.resend-credentials', $consumer))
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHas('email_error');

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => \App\Models\ConsumerNotificationLog::EVENT_CREDENTIALS_RESENT,
            'status' => \App\Models\ConsumerNotificationLog::STATUS_FAILED,
        ]);

        // A failed send must never rotate the password, otherwise the consumer
        // is locked out using credentials that were never delivered.
        $this->assertSame($passwordBefore, $consumerUser->fresh()->password);
    }

    public function test_resend_sends_again_even_when_details_were_already_delivered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Repeat Resend Applicant',
            'email' => 'repeat.resend@example.com',
            'account_number' => 'RS-3',
            'meter_number' => 'MT-RS-3',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        Mail::shouldReceive('send')->once()->andReturn(true);

        $this->actingAs($admin)
            ->post(route('consumers.resend-credentials', $consumer))
            ->assertSessionHas('success');

        // Explicit resends deliver the details again.
        $captured = null;
        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.consumer.account-activated',
                Mockery::on(function (array $data) use (&$captured): bool {
                    $captured = $data;

                    return true;
                }),
                Mockery::type(\Closure::class)
            )->andReturn(true);

        $this->actingAs($admin)
            ->post(route('consumers.resend-credentials', $consumer))
            ->assertSessionHas('success', 'Login details re-sent to repeat.resend@example.com.');

        $this->assertNotEmpty($captured['passwordSetupUrl']);
        $this->assertNull($captured['temporaryPassword']);
    }

public function test_resend_requires_admin_role(): void
{
    $consumerUser = User::factory()->create(['role' => 'consumer']);
    $consumer = Consumer::create([
        'user_id' => $consumerUser->id,
        'full_name' => 'Not Admin',
        'email' => 'not.admin@example.com',
        'status' => 'Active',
    ]);

    $this->actingAs($consumerUser)
        ->post(route('consumers.resend-credentials', $consumer))
        ->assertForbidden();
}

public function test_duplicate_approval_email_is_not_sent_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Duplicate Notification Applicant',
            'email' => 'duplicate.notification@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);

        Mail::shouldReceive('send')->once()->andReturn(true);

        $payload = [
            'meter_number' => 'MT-DUP-001',
            'membership_type' => 'Member',
            'membership_verification_confirmed' => true,
            'installation_date' => now()->addWeek()->toDateString(),
        ];

        $this->actingAs($admin)->patch(route('consumers.activate', $consumer), $payload)
            ->assertSessionHas('success');

        // A second approval must not email the applicant again.
        Mail::shouldReceive('send')->never();

        $secondAttempt = $this->actingAs($admin)->patch(route('consumers.activate', $consumer), $payload);

        $secondAttempt
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors()
            // "Already sent" is reported as success, not as a mail failure.
            ->assertSessionHas(
                'success',
                'Connection request approved successfully. The account details email was already sent previously and was not repeated.',
            );

        $this->assertNull($secondAttempt->getSession()->get('email_error'));

        $this->assertSame(1, \App\Models\ConsumerNotificationLog::query()
            ->where('consumer_id', $consumer->id)
            ->where('status', \App\Models\ConsumerNotificationLog::STATUS_SENT)
            ->count());
    }

    public function test_approval_automatically_sends_complete_account_details_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Approved Applicant',
            'email' => 'approved.applicant@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);
        $passwordSetupUrl = null;

        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.consumer.account-activated',
                Mockery::on(function (array $data) use ($consumer, &$passwordSetupUrl): bool {
                    $passwordSetupUrl = $data['passwordSetupUrl'] ?? null;

                    return ($data['consumer']->id ?? null) === $consumer->id
                        && filled($passwordSetupUrl)
                        && strlen($data['temporaryPassword'] ?? '') === 8
                        && filled($data['installationDate'] ?? null);
                }),
                Mockery::type(\Closure::class)
            )->andReturn(true);

        $this->actingAs($admin)->patch(route('consumers.activate', $consumer), [
            'meter_number' => 'MT-APPROVED-001',
            'membership_type' => 'Member',
            'membership_verification_confirmed' => true,
            'installation_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect(route('consumers.index'))
            ->assertSessionHas('success', 'Connection request approved successfully. The account details email has been sent.')
            ->assertSessionMissing('email_error');

        $this->assertDatabaseHas('consumers', [
            'id' => $consumer->id,
            'status' => 'Active',
            'meter_number' => 'MT-APPROVED-001',
        ]);
        $consumer->refresh();
        $this->assertSame('MT-APPROVED-001', $consumer->user->username);
        $this->assertTrue($consumer->user->must_change_password);
        $this->assertNotEmpty($passwordSetupUrl);
        $this->assertDatabaseHas('consumer_password_setups', ['user_id' => $consumer->user_id]);

    }

    public function test_reused_email_application_can_be_approved_and_emailed_separately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existingUser = User::factory()->create([
            'role' => 'consumer',
            'email' => 'shared.application@example.com',
        ]);
        $existingApplication = Consumer::create([
            'user_id' => $existingUser->id,
            'full_name' => 'Existing Consumer',
            'email' => 'shared.application@example.com',
            'account_number' => 'VV-EXISTING',
            'meter_number' => 'MT-EXISTING',
            'status' => 'Active',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);
        $newApplication = Consumer::create([
            'full_name' => 'New Application',
            'email' => 'shared.application@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);

        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.consumer.account-activated',
                Mockery::on(fn (array $data): bool => ($data['consumer']->id ?? null) === $newApplication->id
                    && filled($data['passwordSetupUrl'] ?? null) && strlen($data['temporaryPassword'] ?? '') === 8),
                Mockery::type(\Closure::class)
            )->andReturn(true);

        $this->actingAs($admin)->patch(route('consumers.activate', $newApplication), [
            'meter_number' => 'MT-SHARED-002',
            'membership_type' => 'Member',
            'membership_verification_confirmed' => true,
            'installation_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect(route('consumers.index'))
            ->assertSessionHas('success');

        $newApplication->refresh();

        $this->assertSame('Active', $newApplication->status);
        $this->assertNotNull($newApplication->user_id);
        $this->assertNotSame($existingApplication->user_id, $newApplication->user_id);
        $this->assertSame('MT-SHARED-002', $newApplication->user->username);
        $this->assertNotSame($existingApplication->application_reference, $newApplication->application_reference);
    }

    public function test_connection_approval_email_template_contains_login_button_registered_email_and_preserved_details(): void
    {
        $consumer = Consumer::create([
            'full_name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'account_number' => 'AC-9901',
            'meter_number' => 'MT-9901',
            'application_reference' => 'APP-REF-2026-99',
            'status' => 'Active',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);

        $rendered = view('emails.consumer.account-activated', [
            'consumer' => $consumer,
            'temporaryPassword' => 'Temp9901',
            'installationDate' => 'November 05, 2026',
        ])->render();

        $this->assertStringContainsString('Your connection request has been approved', $rendered);
        $this->assertStringContainsString('Hello Maria Santos,', $rendered);
        $this->assertStringContainsString('APP-REF-2026-99', $rendered);
        $this->assertStringContainsString('AC-9901', $rendered);
        $this->assertStringContainsString('MT-9901', $rendered);
        $this->assertStringContainsString('Temp9901', $rendered);
        $this->assertStringContainsString('Set / Reset Password Securely', $rendered);
        $this->assertStringContainsString('November 05, 2026', $rendered);
        $this->assertStringContainsString('Registered Email Address', $rendered);
        $this->assertStringContainsString('maria.santos@example.com', $rendered);
        $this->assertStringContainsString('Forgot Password', $rendered);
        $this->assertStringContainsString('Login Now', $rendered);
        $this->assertStringContainsString(route('login'), $rendered);

    }
}
