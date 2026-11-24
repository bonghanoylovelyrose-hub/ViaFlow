<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminConsumerCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
    }

    private function consumer(): Consumer
    {
        $user = User::factory()->create(['role' => 'consumer', 'email' => 'old@example.test', 'username' => 'METER-1', 'password' => 'OldPassword123']);
        return Consumer::create(['user_id' => $user->id, 'full_name' => 'Sample Consumer', 'email' => 'old@example.test', 'account_number' => 'ACCOUNT-1', 'meter_number' => 'METER-1', 'status' => 'Active', 'membership_type' => 'Non-Member']);
    }

    public function test_active_account_resend_action_is_in_details_not_the_table(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer();

        $response = $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'active']))
            ->assertOk()
            ->assertSee('Account Access')
            ->assertSee('Resend Login Details')
            ->assertSee('resend-login-form', false)
            ->assertSee('consumerDetailsModal', false);

        $html = $response->getContent();
        $tableStart = strpos($html, 'id="consumer-accounts-table"');
        $this->assertNotFalse($tableStart);
        $tableEnd = strpos($html, '</table>', $tableStart);
        $this->assertNotFalse($tableEnd);
        $table = substr($html, $tableStart, $tableEnd - $tableStart);
        $this->assertStringNotContainsString('Resend Login Details', $table);
        $this->assertStringContainsString('View Details', $table);
        $this->assertStringContainsString('data-bs-target="#consumerEditModal"', $table);

        $this->assertSame(1, preg_match("/data-consumer-details='([^']+)'/", $table, $matches));
        $payload = json_decode(base64_decode(html_entity_decode($matches[1])), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(route('consumers.resend-credentials', $consumer, false), $payload['resend_login_details_url']);
    }
    public function test_approval_emails_temporary_password_and_consumer_can_use_dashboard_without_changing_it(): void
    {
        $admin = $this->admin();
        $consumer = Consumer::create(['full_name' => 'New Consumer', 'email' => 'new@example.test', 'status' => 'Pending Verification', 'membership_type' => 'Non-Member']);
        $sent = [];
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $data, $callback) use (&$sent): bool {
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
            $callback($message);
            $sent = ['view' => $view, 'data' => $data, 'recipient' => $message->getSymfonyMessage()->getTo()[0]->getAddress()];

            return true;
        });
        $this->actingAs($admin)->patch(route('consumers.activate', $consumer), ['meter_number' => 'METER-NEW', 'membership_type' => 'Non-Member', 'installation_date' => now()->addDays(4)->toDateString()])->assertSessionHasNoErrors()->assertSessionHas('success');
        $consumer->refresh()->load('user');
        $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active', 'installation_status' => 'pending']))
            ->assertOk()->assertSee('New Consumer')->assertSee('Pending Installation')->assertSee('Mark Complete');
        $this->assertSame('emails.consumer.account-activated', $sent['view']);
        $this->assertSame('new@example.test', $sent['recipient']);
        $this->assertSame('METER-NEW', $consumer->user->username);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $sent['data']['temporaryPassword']);
        $this->assertTrue(Hash::check($sent['data']['temporaryPassword'], $consumer->user->password));
        $this->assertNotSame($sent['data']['temporaryPassword'], $consumer->user->password);
        $this->assertStringContainsString($sent['data']['temporaryPassword'], view($sent['view'], $sent['data'])->render());
        $hashAfterApproval = $consumer->user->password;
        $this->actingAs($admin)->patch(route('consumers.activate', $consumer), ['meter_number' => 'METER-NEW', 'membership_type' => 'Non-Member', 'installation_date' => now()->addDays(4)->toDateString()])->assertSessionHas('success');
        $this->assertSame($hashAfterApproval, $consumer->user->fresh()->password);
        auth()->logout();
        $this->post(route('login.attempt'), ['login' => 'METER-NEW', 'password' => $sent['data']['temporaryPassword']])->assertRedirect(route('dashboard'))->assertSessionHas('show_temporary_password_reminder', true);
        $this->get(route('dashboard'))->assertOk()->assertSee('temporaryPasswordReminderModal', false)->assertSee('Not now');
        $this->get(route('billing.index'))->assertOk();
    }

    public function test_resend_preserves_existing_password_and_outstanding_reset_link(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer();
        $resetLink = app(\App\Services\ConsumerPasswordSetupService::class)->createLink($consumer);
        $this->assertDatabaseHas('consumer_password_setups', ['user_id' => $consumer->user_id]);
        $sent = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function ($view, $data, $callback) use (&$sent): bool {
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
            $callback($message);
            $sent[] = ['view' => $view, 'data' => $data, 'recipient' => $message->getSymfonyMessage()->getTo()[0]->getAddress()];

            return true;
        });
        $this->actingAs($admin)->post(route('consumers.resend-credentials', $consumer))->assertSessionHas('success');
        $this->assertNull($sent[0]['data']['temporaryPassword']);
        $this->assertSame('emails.consumer.account-activated', $sent[0]['view']);
        $this->assertSame('old@example.test', $sent[0]['recipient']);
        $this->assertTrue(Hash::check('OldPassword123', $consumer->user->fresh()->password));
        $this->get($resetLink)->assertOk();
        $this->actingAs($admin)->post(route('consumers.resend-credentials', $consumer))->assertSessionHas('success');
        $this->assertNull($sent[1]['data']['temporaryPassword']);
        $this->assertTrue(Hash::check('OldPassword123', $consumer->user->fresh()->password));
        $this->get($resetLink)->assertOk();
    }

    public function test_resend_delivery_failure_keeps_the_existing_password(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer();
        $oldHash = $consumer->user->password;
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $this->actingAs($admin)->post(route('consumers.resend-credentials', $consumer))->assertSessionHas('email_error');
        $this->assertSame($oldHash, $consumer->user->fresh()->password);
        $this->assertTrue(Hash::check('OldPassword123', $consumer->user->fresh()->password));
        $this->assertDatabaseHas('consumer_notification_logs', ['consumer_id' => $consumer->id, 'event' => 'credentials_resent', 'status' => 'failed']);
    }

    public function test_failed_email_verification_delivery_keeps_the_previous_password_and_can_be_retried(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer();
        $url = null;
        Mail::shouldReceive('send')->twice()->andReturnUsing(function ($view, $data, $callback) use (&$url): void {
            if ($view === 'emails.consumer.verify-email-change') {
                $url = $data['verificationUrl'];
                return;
            }
            throw new \RuntimeException('Mail unavailable');
        });
        $this->actingAs($admin)->patch(route('consumers.update', $consumer), ['full_name' => $consumer->full_name, 'email' => 'retry@example.test', 'account_number' => $consumer->account_number, 'status' => 'Active', 'membership_type' => 'Non-Member'])->assertSessionHasNoErrors();
        $form = $this->get($url)->assertOk()->viewData('formAction');
        $this->post($form)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword123', $consumer->user->fresh()->password));
        $this->assertDatabaseHas('consumer_email_verifications', ['consumer_id' => $consumer->id, 'verified_at' => null]);
        $this->get($url)->assertOk();
    }

    public function test_resending_verification_invalidates_the_previous_link_and_requires_admin(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer();
        $links = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function ($view, $data, $callback) use (&$links): void { $links[] = $data['verificationUrl']; });
        $this->actingAs($admin)->patch(route('consumers.update', $consumer), ['full_name' => $consumer->full_name, 'email' => 'changed@example.test', 'account_number' => $consumer->account_number, 'status' => 'Active', 'membership_type' => 'Non-Member'])->assertSessionHasNoErrors();
        $this->actingAs($consumer->user)->post(route('consumers.resend-email-verification', $consumer))->assertForbidden();
        $this->actingAs($admin)->post(route('consumers.resend-email-verification', $consumer))->assertSessionHas('success');
        $this->get($links[0])->assertForbidden();
        $this->get($links[1])->assertOk();
        $token = basename(parse_url($links[1], PHP_URL_PATH));
        $tampered = ($token[0] === 'A' ? 'B' : 'A').substr($token, 1);
        $this->get(str_replace($token, $tampered, $links[1]))->assertForbidden();
        $this->travel(25)->hours();
        $this->get($links[1])->assertForbidden();
    }

    public function test_email_edit_requires_ownership_confirmation_before_rotating_and_sending_credentials(): void
    {
        $admin = $this->admin();
        $consumer = $this->consumer();
        $sent = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function ($view, $data, $callback) use (&$sent): void { $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email()); $callback($message); $sent[] = ['view' => $view, 'data' => $data, 'recipient' => $message->getSymfonyMessage()->getTo()[0]->getAddress()]; });
        $this->actingAs($admin)->patch(route('consumers.update', $consumer), ['full_name' => $consumer->full_name, 'email' => 'verified-new@example.test', 'account_number' => $consumer->account_number, 'status' => 'Active', 'membership_type' => 'Non-Member'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('emails.consumer.verify-email-change', $sent[0]['view']);
        $this->assertSame('verified-new@example.test', $sent[0]['recipient']);
        $this->assertSame('verified-new@example.test', $consumer->fresh()->email);
        $this->assertTrue(Hash::check('OldPassword123', $consumer->user->fresh()->password));
        $this->assertDatabaseHas('consumer_email_verifications', ['consumer_id' => $consumer->id, 'email' => 'verified-new@example.test', 'verified_at' => null]);
        $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk()->assertSee('Email verification pending')->assertSee('Resend Email Verification');
        $this->actingAs($admin)->post(route('consumers.resend-credentials', $consumer))->assertSessionHasErrors('resend');
        $verificationUrl = $sent[0]['data']['verificationUrl'];
        $token = basename(parse_url($verificationUrl, PHP_URL_PATH));
        $this->assertSame(hash('sha256', $token), DB::table('consumer_email_verifications')->where('consumer_id', $consumer->id)->value('token_hash'));
        $form = $this->get($verificationUrl)->assertOk()->assertSee('Confirm Email')->viewData('formAction');
        $this->assertTrue(Hash::check('OldPassword123', $consumer->user->fresh()->password));
        $this->post($form)->assertOk()->assertSee('Email Verified');
        $this->assertSame('emails.consumer.login-credentials', $sent[1]['view']);
        $this->assertSame('verified-new@example.test', $sent[1]['recipient']);
        $this->assertSame('verified-new@example.test', $sent[1]['data']['consumer']->email);
        $this->assertTrue(Hash::check($sent[1]['data']['temporaryPassword'], $consumer->user->fresh()->password));
        $this->assertFalse(Hash::check('OldPassword123', $consumer->user->fresh()->password));
        $this->assertNotNull($consumer->user->fresh()->email_verified_at);
        $this->post($form)->assertForbidden();
    }
}

