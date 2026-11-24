<?php

namespace Tests\Feature;

use App\Mail\ReconnectionScheduleConfirmation;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\User;
use App\Services\ConsumerPasswordSetupService;
use App\Services\ConsumerReconnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ActivationEmailSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 01:30:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        config(['support.email' => 'support@example.test', 'support.phone' => '09171234567']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(): Consumer
    {
        $user = User::factory()->create(['role' => 'consumer', 'password' => Hash::make('DoNotEmailThisSecret123!')]);

        return Consumer::query()->create([
            'user_id' => $user->id, 'full_name' => 'Secure Activation Consumer', 'email' => $user->email,
            'account_number' => 'ACT-100', 'meter_number' => 'ACT-METER-100', 'status' => 'Disconnected', 'membership_type' => 'Non-Member',
        ]);
    }

    private function activate(Consumer $consumer): array
    {
        $captured = [];
        Mail::shouldReceive('send')->once()->withArgs(function (ReconnectionScheduleConfirmation $mail) use (&$captured, $consumer): bool {
            $this->assertSame('Active', $consumer->fresh()->status);
            $this->assertDatabaseCount('consumer_reconnections', 1);
            $content = $mail->content();
            $captured = ['view' => $content->view, 'data' => $content->with];

            return $mail->hasTo($consumer->email);
        })->andReturn(\Mockery::mock(SentMessage::class));
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'reconnection_date' => '2026-11-05', 'installation_scheduled_at' => '2026-11-06 09:30:00',
        ])->assertSessionHasNoErrors();

        return $captured;
    }

    public function test_activation_email_has_all_account_schedule_login_support_and_secure_link_details(): void
    {
        $consumer = $this->account();
        $originalPassword = $consumer->user->password;
        $email = $this->activate($consumer);
        $html = view($email['view'], $email['data'])->render();
        foreach ([$consumer->full_name, $consumer->account_number, $consumer->meter_number, $consumer->email,
            ReconnectionScheduleConfirmation::SUBJECT, 'Active / Pending Reconnection', 'November 06, 2026', '9:30 AM', 'https://consumers.example.test/login',
            'Scheduled Reconnection Time (Philippine Time)', 'authorized representative is available',
            'Temporary Password', $email['data']['temporaryPassword'],
            'Your water service reconnection has been scheduled', 'Meter Number (Username)', 'Registered Email Address',
            'Set / Reset Password Securely', 'Request for Reschedule', 'Login Now', 'support@example.test', '09171234567'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
        $this->assertStringNotContainsString('Admin Notes:', $html);
        $htmlWithNotes = view($email['view'], array_replace($email['data'], ['adminNotes' => 'Use the side gate.']))->render();
        $this->assertStringContainsString('Admin Notes:</strong> Use the side gate.', $htmlWithNotes);
        $this->assertStringNotContainsString('DoNotEmailThisSecret123!', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertNotSame($originalPassword, $consumer->user->fresh()->password);
        $this->assertTrue(Hash::check($email['data']['temporaryPassword'], $consumer->user->fresh()->password));
        $this->assertNull($consumer->reconnections()->sole()->getRawOriginal('pending_credentials'));
        $this->assertDatabaseHas('consumer_notification_logs', ['consumer_id' => $consumer->id, 'event' => 'consumer_reconnected_1', 'status' => 'sent']);
        $this->assertNotNull(ConsumerNotificationLog::query()->where('event', 'consumer_reconnected_1')->sole()->sent_at);
    }

    public function test_email_login_supports_password_replacement_and_a_refreshed_form_session(): void
    {
        $consumer = $this->account();
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'reconnection_date' => '2026-11-05', 'installation_scheduled_at' => '2026-11-06 09:30:00',
        ])->assertSessionHasNoErrors();
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame(ReconnectionScheduleConfirmation::SUBJECT, $message->getSubject());
        $this->assertSame($consumer->email, $message->getTo()[0]->getAddress());
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$message->getHtmlBody());
        $links = [];
        foreach ($document->getElementsByTagName('a') as $link) {
            $links[trim($link->textContent)] = $link->getAttribute('href');
        }
        $temporaryPassword = $document->getElementsByTagName('code')->item(0)->textContent;
        Auth::logout();
        $this->app->instance('env', 'local'); // Exercise the real CSRF middleware.

        $reschedule = $this->get($links['Request for Reschedule'])->assertOk()->assertSee('Request for Reschedule');
        $rescheduleAction = 'https://consumers.example.test'.$reschedule->viewData('formAction');
        $this->post($rescheduleAction, [
            '_token' => session()->token(), 'preferred_date' => '2026-11-08', 'preferred_time' => '15:00', 'reason' => 'Work conflict.',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('installation_reschedule_requests', ['consumer_id' => $consumer->id, 'status' => 'pending']);
        $this->get($links['Set / Reset Password Securely'])->assertOk();
        $loginUrl = $links['Login Now'];
        $this->get($loginUrl)->assertOk()->assertSee('name="_token"', false);
        $credentials = ['login' => $consumer->meter_number, 'password' => $temporaryPassword];
        $this->post($loginUrl, $credentials + ['_token' => 'stale'])->assertStatus(303)->assertRedirect('/login');
        $this->get($loginUrl)->assertSee('Your session was refreshed.');
        $this->post($loginUrl, $credentials + ['_token' => session()->token()])
            ->assertRedirect('/dashboard')->assertSessionHas('show_temporary_password_reminder', true);
        $this->assertTrue($consumer->user->fresh()->must_change_reactivation_password);
        $this->get('/account/change-password')->assertOk()->assertSee('Change Temporary Password');
        $this->patch('/account/change-password', [
            '_token' => session()->token(), 'current_password' => $temporaryPassword,
            'password' => 'ChangedPassword456!', 'password_confirmation' => 'ChangedPassword456!',
        ])->assertRedirect('/dashboard');
        $this->assertFalse($consumer->user->fresh()->must_change_reactivation_password);
        $this->assertTrue(Hash::check('ChangedPassword456!', $consumer->user->fresh()->password));
        $this->get('/dashboard')->assertOk();
        $this->get($links['Set / Reset Password Securely'])->assertForbidden();
        Auth::logout();
        $this->post($loginUrl, $credentials + ['_token' => session()->token()])->assertSessionHasErrors('login');
        $this->post($loginUrl, ['login' => $consumer->meter_number, 'password' => 'ChangedPassword456!', '_token' => session()->token()])
            ->assertRedirect('/dashboard');
    }

    public function test_reconnection_email_link_uses_saved_event_when_account_schedule_is_missing(): void
    {
        $consumer = $this->account();
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-06 09:30:00',
        ])->assertSessionHasNoErrors();
        $record = $consumer->reconnections()->sole();
        $consumer->forceFill(['reconnection_scheduled_at' => null])->save();

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $document = new \DOMDocument;
        @$document->loadHTML($message->getHtmlBody());
        $link = (new \DOMXPath($document))->query('//a[normalize-space(.)="Request for Reschedule"]')->item(0)->getAttribute('href');
        Auth::logout();
        $page = $this->get($link)->assertOk()->assertSee('November 06, 2026 9:30 AM');
        $this->post('https://consumers.example.test'.$page->viewData('formAction'), [
            'preferred_date' => '2026-11-08', 'preferred_time' => '15:00', 'reason' => 'Need to be home for reconnection.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $request = $consumer->installationRescheduleRequests()->sole();
        $this->assertSame($record->id, $request->consumer_reconnection_id);
        $this->assertSame('2026-11-06 09:30:00', $request->original_installation_date->toDateString().' '.$request->original_installation_time);
        $this->actingAs($this->admin)->get(route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'reschedules']))
            ->assertOk()->assertSee($consumer->full_name)->assertSee('Assign New Schedule');
        $this->patch(route('installation-reschedules.assign', $request), [
            'new_installation_date' => '2026-11-08', 'new_installation_time' => '15:00',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-08 07:00:00', $consumer->fresh()->reconnection_scheduled_at->toDateTimeString());
    }

    public function test_password_setup_is_hashed_in_storage_and_can_be_used_only_once(): void
    {
        $consumer = $this->account();
        $email = $this->activate($consumer);
        $url = $email['data']['passwordSetupUrl'];
        $token = basename(parse_url($url, PHP_URL_PATH));
        $stored = DB::table('consumer_password_setups')->where('user_id', $consumer->user_id)->first();
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertNotSame($token, $stored->token_hash);
        Auth::logout();
        $this->app->instance('env', 'local');
        $this->get($url)->assertOk()->assertSee('Set Your Password');
        $newPassword = ['password' => 'NewSecureAccount456!', 'password_confirmation' => 'NewSecureAccount456!'];
        $this->post($url, $newPassword + ['_token' => 'stale'])->assertStatus(303)
            ->assertRedirect(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY));
        $this->get($url)->assertOk()->assertSee('Your session was refreshed.');
        $this->post($url, $newPassword + ['_token' => session()->token()])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewSecureAccount456!', $consumer->user->fresh()->password));
        $this->get($url)->assertForbidden();
        $this->post($url, $newPassword + ['_token' => session()->token()])->assertForbidden();
        $this->assertDatabaseCount('consumer_password_setups', 0);
        $this->post(route('login.attempt'), ['login' => $consumer->meter_number, 'password' => 'NewSecureAccount456!', '_token' => session()->token()])->assertRedirect(route('dashboard'));
    }

    public function test_expired_replaced_and_tampered_password_links_are_rejected(): void
    {
        $consumer = $this->account();
        $email = $this->activate($consumer);
        $old = $email['data']['passwordSetupUrl'];
        $new = app(ConsumerPasswordSetupService::class)->createLink($consumer->fresh());
        $this->get($old)->assertForbidden();
        $this->get(str_replace('/'.$consumer->user_id.'/', '/'.$this->admin->id.'/', $new))->assertForbidden();
        Carbon::setTestNow(now()->addMinutes(61));
        $this->get($new)->assertForbidden();
        $this->assertTrue(Hash::check($email['data']['temporaryPassword'], $consumer->user->fresh()->password));
    }

    public function test_unpaid_or_rolled_back_activation_sends_no_email_or_password_link(): void
    {
        $consumer = $this->account();
        Mail::shouldReceive('send')->never();
        try {
            DB::transaction(function () use ($consumer): void {
                $result = app(ConsumerReconnectionService::class)->reconnect($consumer, $this->admin, Carbon::parse('2026-11-06 09:30:00'));
                $this->assertSame(ConsumerNotificationLog::RESULT_PENDING, $result);
                $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'consumer_reconnected_1', 'status' => 'pending', 'sent_at' => null]);
                throw new \RuntimeException('Rollback activation');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback activation', $exception->getMessage());
        }
        $this->assertSame('Disconnected', $consumer->refresh()->status);
        $this->assertDatabaseCount('consumer_reconnections', 0);
        $this->assertDatabaseCount('consumer_password_setups', 0);
        Bill::query()->create(['consumer_id' => $consumer->id, 'prn' => 'ACT-DEBT', 'billing_period' => '2026-10-01', 'due_date' => '2026-10-31', 'amount' => 50, 'status' => 'Unpaid']);
        $this->post(route('consumers.reconnect', $consumer), ['confirm_full_payment' => 1, 'reconnection_date' => '2026-11-05', 'installation_scheduled_at' => '2026-11-06 09:30:00'])->assertSessionHasErrors('reconnection');
        $this->assertDatabaseCount('consumer_notification_logs', 0);
    }

    public function test_smtp_failure_records_failed_delivery_after_successful_activation(): void
    {
        $consumer = $this->account();
        $attempted = null;
        Mail::shouldReceive('send')->once()->withArgs(function (ReconnectionScheduleConfirmation $mail) use (&$attempted): bool {
            $attempted = $mail->content()->with;

            return true;
        })->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->post(route('consumers.reconnect', $consumer), ['confirm_full_payment' => 1, 'reconnection_date' => '2026-11-05', 'installation_scheduled_at' => '2026-11-06 09:30:00'])->assertSessionHas('email_error');
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'consumer_reconnected_1', 'status' => 'failed']);
        $this->assertNull(ConsumerNotificationLog::query()->sole()->sent_at);
        $this->assertStringNotContainsString($attempted['temporaryPassword'], $consumer->reconnections()->sole()->getRawOriginal('pending_credentials'));
        $passwordHash = $consumer->user->fresh()->password;
        Mail::shouldReceive('send')->once()->withArgs(fn (ReconnectionScheduleConfirmation $mail) => $mail->content()->with['temporaryPassword'] === $attempted['temporaryPassword']
            && $mail->content()->with['passwordSetupUrl'] === $attempted['passwordSetupUrl'])->andReturn(\Mockery::mock(SentMessage::class));
        $this->get(route('consumers.index', ['account_category' => 'active']))
            ->assertSee('Schedule email: Email not accepted')->assertSee('Resend Reconnection Schedule Email');
        $this->post(route('consumers.retry-reactivation-email', $consumer))->assertSessionHas('info', 'The mail server accepted the reconnection schedule email.');
        $this->post(route('consumers.retry-reactivation-email', $consumer))->assertSessionHas('info', 'The mail server already accepted the reconnection schedule email.');
        $this->get(route('consumers.index', ['account_category' => 'active']))->assertSee('Schedule email: Accepted by mail server');
        $this->assertSame($passwordHash, $consumer->user->fresh()->password);
        $this->assertDatabaseCount('consumer_reconnections', 1);
        $this->assertDatabaseCount('consumer_notification_logs', 1);
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'consumer_reconnected_1', 'status' => 'sent']);
        $this->assertNull($consumer->reconnections()->sole()->getRawOriginal('pending_credentials'));
    }

    public function test_loopback_configuration_sends_a_signed_reschedule_link_without_changing_credentials(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000', 'app.public_url' => 'http://127.0.0.1:8000']);
        $consumer = $this->account();
        $password = $consumer->user->password;
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-06T09:30',
        ])->assertSessionHas('info')->assertSessionMissing('email_error');
        $this->assertSame('Active', $consumer->fresh()->status);
        $this->assertSame($password, $consumer->user->fresh()->password);
        $this->assertDatabaseCount('consumer_password_setups', 0);
        $this->post(route('consumers.retry-reactivation-email', $consumer))->assertSessionHas('info');
        $email = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $email->getTo()[0]->getAddress());
        $html = $email->getHtmlBody();
        foreach ([$consumer->full_name, $consumer->account_number, $consumer->meter_number, 'November 06, 2026', '9:30 AM'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
        foreach (['Temporary Password', 'Set / Reset Password Securely', 'Login Now'] as $detail) {
            $this->assertStringNotContainsString($detail, $html);
        }
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $link = (new \DOMXPath($document))->query('//a[normalize-space(.)="Request for Reschedule"]')->item(0)->getAttribute('href');
        $this->assertStringStartsWith('http://127.0.0.1:8000/reconnection/1/reschedule?', $link);
        Auth::logout();
        $this->get($link)->assertOk()->assertSee('3 reschedule attempts remaining');
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'consumer_reconnected_1', 'status' => 'sent']);
    }

    public function test_schedule_retry_uses_latest_saved_schedule_after_the_consumer_replaces_their_password(): void
    {
        $consumer = $this->account();
        $consumer->update(['installation_date' => '2025-01-05']);
        $reject = true;
        $this->app['events']->listen(MessageSending::class, function ($event) use (&$reject): ?bool {
            return $reject && $event->message->getSubject() === ReconnectionScheduleConfirmation::SUBJECT ? false : null;
        });
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-06T09:30',
        ])->assertSessionHas('email_error')->assertSessionMissing('info');

        $consumer->user->forceFill(['password' => 'MyReplacementPassword1!', 'must_change_reactivation_password' => false])->save();
        $passwordHash = $consumer->user->fresh()->password;
        $consumer->update(['reconnection_scheduled_at' => '2026-11-10 06:45:00']);
        $reject = false;
        $this->post(route('consumers.retry-reactivation-email', $consumer))->assertSessionHas('info', 'The mail server accepted the reconnection schedule email.');
        $this->post(route('consumers.retry-reactivation-email', $consumer))->assertSessionHas('info', 'The mail server already accepted the reconnection schedule email.');

        $email = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $email->getTo()[0]->getAddress());
        $this->assertSame(ReconnectionScheduleConfirmation::SUBJECT, $email->getSubject());
        $this->assertStringContainsString('November 10, 2026', $email->getHtmlBody());
        $this->assertStringContainsString('2:45 PM', $email->getHtmlBody());
        $this->assertStringNotContainsString('January 05, 2025', $email->getHtmlBody());
        $this->assertStringNotContainsString('Temporary Password', $email->getHtmlBody());
        $this->assertSame($passwordHash, $consumer->user->fresh()->password);
        $this->assertDatabaseCount('consumer_reconnections', 1);
        $this->assertDatabaseCount('consumer_notification_logs', 1);
    }

    public function test_log_mailer_cannot_be_reported_as_accepted_and_schedule_remains_retryable(): void
    {
        $consumer = $this->account();
        config(['mail.default' => 'failover']);
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-06T09:30',
        ])->assertSessionHas('email_error')->assertSessionMissing('info');

        $this->assertTrue($consumer->refresh()->reconnection_pending);
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'consumer_reconnected_1', 'status' => 'failed', 'sent_at' => null]);
        $this->assertDatabaseCount('consumer_password_setups', 0);
        config(['mail.default' => 'array']);
        $this->post(route('consumers.retry-reactivation-email', $consumer))->assertSessionHas('info', 'The mail server accepted the reconnection schedule email.');
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_registered_schedule_email_does_not_require_a_linked_login(): void
    {
        $consumer = $this->account();
        $consumer->update(['user_id' => null]);
        $this->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-06T09:30',
        ])->assertSessionHas('info', 'The mail server accepted the reconnection schedule email.');

        $email = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $email->getTo()[0]->getAddress());
        $this->assertStringNotContainsString('Temporary Password', $email->getHtmlBody());
    }
}
