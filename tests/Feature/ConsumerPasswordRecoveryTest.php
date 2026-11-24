<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\User;
use App\Services\ConsumerCredentialService;
use App\Services\ConsumerPasswordSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ConsumerPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_available(): void
    {
        $this->get(route('password.forgot'))
            ->assertOk()
            ->assertSee('Consumer accounts only')
            ->assertSee('Registered Email')
            ->assertSee('Meter Number (MT Number)')
            ->assertSee('Send Password Reset Email')
            ->assertDontSee('/forgot-password/code');
    }

    public function test_forgot_password_emails_a_secure_setup_link_and_preserves_the_current_password(): void
    {
        [$user, $consumer] = $this->approvedConsumer('recover@example.com', 'MT-REC-1', 'REC-1');

        $captured = null;

        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.consumer.password-setup',
                Mockery::on(function (array $data) use (&$captured, $consumer): bool {
                    $captured = $data;

                    return ($data['consumer']->id ?? null) === $consumer->id;
                }),
                Mockery::type(\Closure::class),
            )->andReturn(true);

        $this->post(route('password.forgot.send'), [
            'email' => 'RECOVER@example.com',
            'meter_number' => 'mt-rec-1',
        ])
            ->assertRedirect(route('password.forgot'))
            ->assertSessionHas('status', 'Password reset email sent successfully! Please check your registered email inbox.');

        $this->assertNotEmpty($captured['passwordSetupUrl']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $captured['temporaryPassword']);
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->post(route('login.attempt'), [
            'login' => 'MT-REC-1',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_forgot_password_requires_the_meter_number_to_match_the_email(): void
    {
        [$user] = $this->approvedConsumer('mismatch@example.com', 'MT-MATCH-1', 'MATCH-1');

        Mail::shouldReceive('send')->never();

        $this->post(route('password.forgot.send'), [
            'email' => 'mismatch@example.com',
            'meter_number' => 'MT-WRONG-9',
        ])
            ->assertRedirect(route('password.forgot'))
            ->assertSessionHas('warning')
            ->assertSessionMissing('status');

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_local_installation_emails_a_signed_link_and_stays_on_forgot_password(): void
    {
        [, $consumer] = $this->approvedConsumer('recovery@example.com', 'MT-REC-1', 'REC-1');
        $origin = 'http://127.0.0.1:8000';
        config(['app.public_url' => $origin, 'app.url' => $origin]);
        $this->app->instance('env', 'local');
        $forgot = $origin.'/forgot-password';
        $details = ['email' => 'RECOVERY@example.com', 'meter_number' => 'mt-rec-1'];

        $this->get($forgot)->assertOk();
        $this->post($forgot, $details + ['_token' => session()->token()])
            ->assertRedirect($forgot)
            ->assertSessionHas('status', 'Password reset email sent successfully! Please check your registered email inbox.');
        $this->get($forgot)->assertOk()->assertSee('Please check your registered email inbox.');

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $message->getTo()[0]->getAddress());
        $document = new \DOMDocument;
        @$document->loadHTML($message->getHtmlBody());
        $link = $document->getElementsByTagName('a')->item(1)->getAttribute('href');
        $this->assertStringStartsWith($origin.'/account/setup-password/', $link);
        $this->get($link)->assertOk()
            ->assertSee('New Password')
            ->assertSee('Confirm Password')
            ->assertSee('Save Password')
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="meter_number"', false)
            ->assertDontSee('name="reset_code"', false);
        $this->get($origin.'/forgot-password/code')->assertNotFound();
    }

    public function test_a_remote_request_cannot_send_a_loopback_reset_link(): void
    {
        $this->approvedConsumer('recovery@example.com', 'MT-REC-2', 'REC-2');
        $origin = 'http://127.0.0.1:8000';
        config(['app.public_url' => $origin, 'app.url' => $origin]);
        $this->app->instance('env', 'local');
        Mail::shouldReceive('send')->never();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50']);
        $forgot = $origin.'/forgot-password';
        $this->get($forgot)->assertOk();
        $this->post($forgot, ['email' => 'recovery@example.com', 'meter_number' => 'MT-REC-2', '_token' => session()->token()])
            ->assertRedirect($forgot)
            ->assertSessionHas('warning')
            ->assertSessionMissing('status');
        $this->assertDatabaseCount('consumer_password_setups', 0);
    }

    public function test_forgot_password_does_not_issue_credentials_for_pending_accounts(): void
    {
        $user = User::factory()->create([
            'role' => 'consumer',
            'email' => 'pending.recovery@example.com',
            'username' => 'MT-PENDING-9',
        ]);

        Consumer::create([
            'user_id' => $user->id,
            'full_name' => 'Pending Recovery',
            'email' => $user->email,
            'meter_number' => 'MT-PENDING-9',
            'status' => 'Pending Verification',
            'membership_type' => 'Non-Member',
        ]);

        Mail::shouldReceive('send')->never();

        $this->post(route('password.forgot.send'), [
            'email' => 'pending.recovery@example.com',
            'meter_number' => 'MT-PENDING-9',
        ])->assertSessionHas('warning')->assertSessionMissing('status');

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_shared_registered_email_only_resets_the_account_with_the_matching_meter(): void
    {
        [$firstUser, $firstConsumer] = $this->approvedConsumer('shared.recovery@example.com', 'MT-SHARE-1', 'SHARE-1');
        [$secondUser] = $this->approvedConsumer('shared.recovery@example.com', 'MT-SHARE-2', 'SHARE-2');

        Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.consumer.password-setup',
                Mockery::on(fn (array $data): bool => ($data['consumer']->id ?? null) === $firstConsumer->id),
                Mockery::type(\Closure::class),
            )->andReturn(true);

        $this->post(route('password.forgot.send'), [
            'email' => 'shared.recovery@example.com',
            'meter_number' => 'MT-SHARE-1',
        ])->assertSessionHas('status');

        $this->assertFalse($firstUser->fresh()->must_change_password);
        $this->assertDatabaseHas('consumer_password_setups', ['user_id' => $firstUser->id]);
        $this->assertDatabaseMissing('consumer_password_setups', ['user_id' => $secondUser->id]);
        $this->assertFalse($secondUser->fresh()->must_change_password);
    }

    public function test_forgot_password_mail_failure_leaves_the_current_password_unchanged(): void
    {
        [$user, $consumer] = $this->approvedConsumer('mail.failure.recovery@example.com', 'MT-FAIL-1', 'FAIL-1');
        $previousTemporaryHash = Hash::make('OldTest1');
        $previousLink = app(ConsumerPasswordSetupService::class)->createLink($consumer, temporaryPasswordHash: $previousTemporaryHash);
        $previousToken = DB::table('consumer_password_setups')->where('user_id', $user->id)->value('token_hash');

        $originalPassword = $user->password;
        $originalRememberToken = $user->remember_token;

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP credentials rejected'));

        $this->post(route('password.forgot.send'), [
            'email' => 'mail.failure.recovery@example.com',
            'meter_number' => 'MT-FAIL-1',
        ])->assertRedirect(route('password.forgot'))->assertSessionHas('warning')->assertSessionMissing('status');

        $user->refresh();

        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalRememberToken, $user->remember_token);
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseHas('consumer_password_setups', [
            'user_id' => $user->id, 'token_hash' => $previousToken, 'temporary_password_hash' => $previousTemporaryHash,
        ]);
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id, 'event' => ConsumerNotificationLog::EVENT_PASSWORD_TEMPORARY, 'status' => 'failed',
        ]);
        $this->get($previousLink)->assertOk();
        $this->post(route('login.attempt'), ['login' => $consumer->meter_number, 'password' => 'OldTest1'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_received_reset_email_allows_password_change_and_login_with_csrf_enabled(): void
    {
        [$user, $consumer] = $this->approvedConsumer('received@example.com', 'MT-REC-MAIL', 'REC-MAIL');
        $this->app->instance('env', 'local');
        $forgot = 'https://consumers.example.test/forgot-password';
        $details = ['email' => 'RECEIVED@example.com', 'meter_number' => 'mt-rec-mail'];
        $this->get($forgot)->assertOk();
        $this->post($forgot, $details + ['_token' => 'stale'])->assertStatus(303)->assertRedirect('/forgot-password');
        $this->get($forgot)->assertSee('Your session was refreshed.');
        $this->post($forgot, $details + ['_token' => session()->token()])->assertSessionHas('status');

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $message->getTo()[0]->getAddress());
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $document = new \DOMDocument;
        @$document->loadHTML($message->getHtmlBody());
        $link = $document->getElementsByTagName('a')->item(1)->getAttribute('href');
        $this->assertSame('consumers.example.test', parse_url($link, PHP_URL_HOST));
        $this->get($link)->assertOk()->assertSee('Set Your Password');
        $reset = ['password' => 'RecoveredPassword123!', 'password_confirmation' => 'RecoveredPassword123!'];
        $this->post($link, $reset + ['_token' => 'stale'])->assertStatus(303);
        $this->get($link)->assertOk();
        $this->post($link, $reset + ['_token' => session()->token()])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been set securely. Sign in with your meter number and new password.');
        $this->get($link)->assertForbidden();
        $this->get(route('login'))->assertOk();
        $this->post(route('login.attempt'), [
            'login' => $consumer->meter_number, 'password' => $reset['password'], '_token' => session()->token(),
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_emailed_temporary_password_survives_repeated_login_until_changed_in_profile(): void
    {
        [$user, $consumer] = $this->approvedConsumer('temporary@example.com', 'MT-TEMP-11', 'TEMP-11');
        $this->app->instance('env', 'local');
        $site = 'https://consumers.example.test';
        $login = $site.'/login';
        $form = $site.'/account/setup-password';
        $this->get($form)->assertRedirect(route('login'));
        $this->get($site.'/forgot-password')->assertOk();
        $this->post($site.'/forgot-password', [
            'email' => $consumer->email, 'meter_number' => $consumer->meter_number, '_token' => session()->token(),
        ])->assertSessionHas('status');

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $document = new \DOMDocument;
        @$document->loadHTML($message->getHtmlBody());
        $temporaryPassword = $document->getElementById('temporary-password')->textContent;
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $temporaryPassword);
        $this->assertSame($login, $document->getElementsByTagName('a')->item(0)->getAttribute('href'));
        $link = $document->getElementsByTagName('a')->item(1)->getAttribute('href');
        $this->assertStringContainsString($consumer->full_name, $document->textContent);
        $this->assertStringContainsString($consumer->email, $document->textContent);
        $this->assertStringContainsString($consumer->meter_number, $document->textContent);
        $temporaryHash = DB::table('consumer_password_setups')->where('user_id', $user->id)->value('temporary_password_hash');
        $this->assertTrue(Hash::check($temporaryPassword, $temporaryHash));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $attempt = ['login' => $consumer->meter_number, 'password' => $temporaryPassword, 'remember' => '1'];
        for ($cycle = 0; $cycle < 3; $cycle++) {
            $this->get($login)->assertOk();
            $this->post($login, $attempt + ['_token' => session()->token()])
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('show_temporary_password_reminder', true);
            $this->assertAuthenticatedAs($user);
            $this->get($site.'/dashboard')->assertOk()->assertSee('temporaryPasswordReminderModal')
                ->assertSee('data-bs-dismiss="modal">Not now', false);
            $this->get($site.'/billing')->assertOk();
            $this->assertDatabaseHas('consumer_password_setups', ['user_id' => $user->id, 'temporary_password_hash' => $temporaryHash]);
            $this->post($site.'/logout', ['_token' => session()->token()])->assertRedirect($login);
            $this->assertGuest();
        }

        $this->post($login, $attempt + ['_token' => session()->token()])->assertRedirect(route('dashboard'));
        $this->get($form)->assertOk()->assertSee('New Password');

        $newPassword = 'NewPermanentPassword123!';
        $this->get($site.'/profile')->assertOk();
        $this->patch($site.'/profile/password', [
            'current_password' => $temporaryPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            '_token' => session()->token(),
        ])->assertRedirect($site.'/profile')->assertSessionHas('success');
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertDatabaseMissing('consumer_password_setups', ['user_id' => $user->id]);
        $this->post($site.'/logout', ['_token' => session()->token()])->assertRedirect($login);
        $this->get($link)->assertForbidden();
        $this->post($login, $attempt + ['_token' => session()->token()])->assertSessionHasErrors('login');
        $this->post($login, ['login' => $consumer->meter_number, 'password' => 'password', '_token' => session()->token()])
            ->assertSessionHasErrors('login');
        $this->post($login, ['login' => $consumer->meter_number, 'password' => $newPassword, '_token' => session()->token()])
            ->assertRedirect($site.'/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_replacing_the_emailed_password_invalidates_it_but_link_expiration_does_not(): void
    {
        [$user, $consumer] = $this->approvedConsumer('temporary.expiry@example.com', 'MT-TEMP-12', 'TEMP-12');
        $request = ['email' => $consumer->email, 'meter_number' => $consumer->meter_number];
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $temporaryPasswords = [];
        $latestLink = null;

        foreach ([0, 1] as $index) {
            $this->post(route('password.forgot.send'), $request)->assertSessionHas('status');
            $document = new \DOMDocument;
            @$document->loadHTML($transport->messages()[$index]->getOriginalMessage()->getHtmlBody());
            $temporaryPasswords[] = $document->getElementById('temporary-password')->textContent;
            $latestLink = $document->getElementsByTagName('a')->item(1)->getAttribute('href');
        }

        $this->assertNotSame($temporaryPasswords[0], $temporaryPasswords[1]);
        $this->post(route('login.attempt'), ['login' => $consumer->meter_number, 'password' => $temporaryPasswords[0]])
            ->assertSessionHasErrors('login');
        $this->travel(2)->years();
        $this->get($latestLink)->assertForbidden();
        $this->post(route('login.attempt'), ['login' => $consumer->meter_number, 'password' => $temporaryPasswords[1]])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('password.setup.temporary'))->assertOk();
        app(ConsumerPasswordSetupService::class)->createLink($consumer);
        $this->get(route('password.setup.temporary'))->assertOk();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_consumer_with_a_temporary_password_can_access_dashboard_with_reminder_banner_and_change_password_in_profile(): void
    {
        [$user] = $this->approvedConsumer('forced.change@example.com', 'MT-FORCE-1', 'FORCE-1');

        app(ConsumerCredentialService::class)->applyTemporaryPassword($user, 'TempPass12345');

        $this->post(route('login.attempt'), [
            'login' => 'MT-FORCE-1',
            'password' => 'TempPass12345',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // Dashboard is accessible immediately and shows the noticeable red warning banner
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You are using a temporary password. You can change it from your profile at any time.')
            ->assertSee('Dismiss');

        // Other consumer pages are also accessible
        $this->get(route('billing.index'))->assertOk();

        // Consumer profile page is accessible and has the change password section with toggle buttons
        $this->get(route('consumer.profile.show'))
            ->assertOk()
            ->assertSee('Current Password')
            ->assertSee('New Password')
            ->assertSee('Confirm New Password')
            ->assertSee('password-toggle');

        // Validation: wrong current password
        $this->patch(route('consumer.profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);

        // Validation: password confirmation mismatch
        $this->patch(route('consumer.profile.password'), [
            'current_password' => 'TempPass12345',
            'password' => 'NewPassword123',
            'password_confirmation' => 'Mismatch123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);

        // Validation: password too short (< 8 chars)
        $this->patch(route('consumer.profile.password'), [
            'current_password' => 'TempPass12345',
            'password' => 'Short1',
            'password_confirmation' => 'Short1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);

        // Successful password change in profile
        $this->patch(route('consumer.profile.password'), [
            'current_password' => 'TempPass12345',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('consumer.profile.show'))
            ->assertSessionHas('success', 'Your password has been updated.');

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));

        // Re-visiting dashboard no longer displays the temporary-password warning
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('You are using a temporary password. You can change it from your profile at any time.');

        // Old temporary password can no longer log in
        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.attempt'), [
            'login' => 'MT-FORCE-1',
            'password' => 'TempPass12345',
        ])->assertSessionHasErrors('login');

        // New password logs in successfully
        $this->post(route('login.attempt'), [
            'login' => 'MT-FORCE-1',
            'password' => 'NewPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_temporary_password_flag_never_blocks_admin_or_meter_reader_accounts(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
            'must_change_password' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function approvedConsumer(string $email, string $meterNumber, string $accountNumber): array
    {
        $user = User::factory()->create([
            'role' => 'consumer',
            'email' => $email,
            'username' => $meterNumber,
        ]);

        $consumer = Consumer::create([
            'user_id' => $user->id,
            'full_name' => 'Recovery Consumer',
            'email' => $email,
            'account_number' => $accountNumber,
            'meter_number' => $meterNumber,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        return [$user->fresh(), $consumer];
    }
}
