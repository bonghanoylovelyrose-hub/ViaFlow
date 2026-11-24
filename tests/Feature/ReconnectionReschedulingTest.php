<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ConsumerReconnection;
use App\Models\InstallationRescheduleRequest;
use App\Models\User;
use App\Notifications\InstallationRescheduleRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReconnectionReschedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Consumer $consumer;

    private ConsumerReconnection $reconnection;

    private string $link;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 01:30:00');
        config(['mail.default' => 'array', 'app.url' => 'http://127.0.0.1:8000', 'app.public_url' => 'http://127.0.0.1:8000']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer']);
        $this->consumer = Consumer::query()->create([
            'user_id' => $user->id, 'full_name' => 'Reschedule Consumer', 'email' => $user->email,
            'account_number' => 'RS-100', 'meter_number' => 'RS-METER-100', 'status' => 'Disconnected', 'membership_type' => 'Non-Member',
            'installation_date' => '2026-08-10', 'installation_time' => '09:15:00',
        ]);
        $this->actingAs($this->admin)->post(route('consumers.reconnect', $this->consumer), [
            'confirm_full_payment' => 1, 'reconnection_date' => '2026-11-05', 'installation_scheduled_at' => '2026-11-06 09:30:00',
        ])->assertSessionHasNoErrors();
        $this->reconnection = ConsumerReconnection::query()->sole();
        $this->link = $this->rescheduleLink(Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getHtmlBody());
        Auth::logout();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function submit(): InstallationRescheduleRequest
    {
        $form = $this->get($this->link)->assertOk()->viewData('formAction');
        $this->post($form, ['preferred_date' => '2026-11-08', 'preferred_time' => '14:00', 'reason' => 'Work schedule conflict.'])
            ->assertSessionHas('success')->assertSessionHasNoErrors();

        return InstallationRescheduleRequest::query()->sole();
    }

    private function rescheduleLink(string $html): ?string
    {
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return (new \DOMXPath($document))->query('//a[normalize-space(.)="Request for Reschedule"]')->item(0)?->getAttribute('href');
    }

    public function test_secure_form_shows_current_schedule_and_accepts_preferences_without_changing_it(): void
    {
        $this->get($this->link)->assertOk()->assertSee('November 06, 2026 9:30 AM')
            ->assertSee('Preferred New Date')->assertSee('Preferred New Time')->assertSee('Reason for Rescheduling');
        $request = $this->submit();
        $this->assertSame('pending', $request->status);
        $this->assertSame('2026-11-08', $request->preferred_installation_date->toDateString());
        $this->assertSame('2026-11-06 01:30:00', $this->consumer->refresh()->reconnection_scheduled_at->toDateTimeString());
        $this->assertNull($request->new_installation_date);
        $notification = $this->admin->notifications()->where('type', InstallationRescheduleRequested::class)->sole();
        $this->assertStringContainsString('Reconnection', $notification->data['message']);
        $this->assertStringContainsString('November 08, 2026 at 2:00 PM', $notification->data['message']);
        $this->actingAs($this->admin)->get(route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'reschedules']))
            ->assertOk()->assertSee('Reschedule Consumer')->assertSee('Assign New Schedule');
    }

    public function test_admin_approval_changes_schedule_and_logs_a_professional_decision_email(): void
    {
        $request = $this->submit();
        $email = [];
        Mail::shouldReceive('send')->once()->withArgs(function (string $view, array $data) use (&$email): bool {
            $email = ['view' => $view, 'data' => $data];

            return $view === 'emails.consumer.reconnection-reschedule-decision';
        })->andReturn(\Mockery::mock(\Illuminate\Mail\SentMessage::class));
        $this->actingAs($this->admin)->patch(route('installation-reschedules.approve', $request), [
            'new_installation_date' => '2026-11-08', 'new_installation_time' => '14:00', 'admin_notes' => 'Preferred schedule confirmed.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('assigned', $request->refresh()->status);
        $this->assertSame($this->admin->id, $request->reviewed_by_user_id);
        $this->assertSame('2026-11-08 06:00:00', $this->consumer->refresh()->reconnection_scheduled_at->toDateTimeString());
        $this->assertSame('2026-08-10', $this->consumer->installation_date->toDateString());
        $this->assertSame('09:15:00', $this->consumer->installation_time);
        $this->assertSame('2026-11-06', $this->consumer->reconnection_date->toDateString());
        $this->assertNull($this->consumer->reconnected_at);
        $this->assertSame('2026-11-06 01:30:00', $this->reconnection->refresh()->installation_scheduled_at->toDateTimeString());
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'installation_reschedule_'.$request->id.'_assigned', 'status' => 'sent']);
        $html = view($email['view'], $email['data'])->render();
        $this->assertStringContainsString('November 08, 2026 at 2:00 PM', $html);
        $this->assertStringContainsString('Preferred schedule confirmed.', $html);
        $this->assertNotNull($this->rescheduleLink($html));
        $this->patch(route('installation-reschedules.approve', $request), ['new_installation_date' => '2026-11-09', 'new_installation_time' => '12:00'])
            ->assertSessionHasErrors('reschedule');
        $this->assertSame('2026-11-08 06:00:00', $this->consumer->refresh()->reconnection_scheduled_at->toDateTimeString());
    }

    public function test_rejection_is_retired_without_changing_the_confirmed_schedule(): void
    {
        $request = $this->submit();
        $this->actingAs($this->admin)->patch(route('installation-reschedules.reject', $request), ['admin_notes' => 'Old action.'])
            ->assertStatus(410);
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertSame('2026-11-06 01:30:00', $this->consumer->refresh()->reconnection_scheduled_at->toDateTimeString());
    }

    public function test_duplicate_pending_requests_are_blocked_and_do_not_notify_admin_twice(): void
    {
        $this->submit();
        $count = DB::table('notifications')->where('type', InstallationRescheduleRequested::class)->count();
        $postUrl = URL::temporarySignedRoute('reconnection.reschedule.store', now()->addHour(), ['reconnection' => $this->reconnection->id]);
        $this->post($postUrl, ['preferred_date' => '2026-11-09', 'preferred_time' => '12:00', 'reason' => 'Another request.'])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('installation_reschedule_requests', 1);
        $this->assertSame($count, DB::table('notifications')->where('type', InstallationRescheduleRequested::class)->count());
    }

    public function test_updated_emails_link_to_each_next_request_and_the_third_assignment_closes_only_this_cycle(): void
    {
        $link = $this->link;
        foreach ([1, 2, 3] as $attempt) {
            Auth::logout();
            $page = $this->get($link)->assertOk()->assertSee((4 - $attempt).' reschedule attempt');
            $action = $page->viewData('formAction');
            $date = Carbon::parse('2026-11-07')->addDays($attempt)->toDateString();
            $this->post($action, ['preferred_date' => $date, 'preferred_time' => '14:00', 'reason' => 'Work schedule conflict.'])
                ->assertSessionHasNoErrors()->assertSessionHas('success');
            $request = $this->consumer->installationRescheduleRequests()->pending()->sole();
            $this->actingAs($this->admin)->patch(route('installation-reschedules.assign', $request), [
                'new_installation_date' => $date, 'new_installation_time' => '14:00',
            ])->assertSessionHasNoErrors()->assertSessionHas('success');
            $email = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
            $this->assertStringContainsString('Updated Reconnection Schedule', $email->getSubject());
            $html = $email->getHtmlBody();
            $this->assertStringContainsString('Reschedule Attempts Remaining:</strong> '.(3 - $attempt), $html);
            $link = $this->rescheduleLink($html);
            if ($attempt < 3) {
                $this->assertNotNull($link);
                $this->get($link)->assertOk()->assertSee(Carbon::parse($date)->format('F d, Y').' 2:00 PM');
            } else {
                $this->assertNull($link);
            }
        }

        Auth::logout();
        $this->get($this->link)->assertOk()->assertSee('0 reschedule attempts remaining')->assertDontSee('name="preferred_date"', false);
        $this->post($action, ['preferred_date' => '2026-11-20', 'preferred_time' => '14:00', 'reason' => 'Fourth request.'])
            ->assertSessionHasErrors('reschedule');
        $this->assertDatabaseCount('installation_reschedule_requests', 3);

        $this->actingAs($this->admin)->post(route('consumers.reconnection-complete', [$this->consumer, $this->reconnection]))->assertSessionHasNoErrors();
        $this->consumer->refresh()->update(['status' => 'Disconnected']);
        $this->post(route('consumers.reconnect', $this->consumer), [
            'confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-15 09:30:00',
        ])->assertSessionHasNoErrors();
        $email = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $link = $this->rescheduleLink($email->getHtmlBody());
        $this->assertNotNull($link);
        Auth::logout();
        $this->get($this->link)->assertForbidden();
        $this->get($link)->assertOk()->assertSee('3 reschedule attempts remaining');
    }

    public function test_unauthorized_tampered_expired_and_incomplete_requests_are_rejected(): void
    {
        $this->get(route('reconnection.reschedule', $this->reconnection))->assertForbidden();
        $this->get($this->link.'&changed=1')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'consumer']))->get($this->link)->assertForbidden();
        Auth::logout();
        $postUrl = $this->get($this->link)->viewData('formAction');
        $this->post($postUrl, [])->assertSessionHasErrors(['preferred_date', 'preferred_time', 'reason']);
        $this->post($postUrl, ['preferred_date' => '2026-11-04', 'preferred_time' => '12:00', 'reason' => 'Past date.'])->assertSessionHasErrors('preferred_date');
        Carbon::setTestNow(now()->addDays(8));
        $this->get($this->link)->assertForbidden();
        $this->assertDatabaseCount('installation_reschedule_requests', 0);
    }

    public function test_only_admin_can_review_and_email_failure_does_not_undo_approval(): void
    {
        $request = $this->submit();
        $payload = ['new_installation_date' => '2026-11-08', 'new_installation_time' => '14:00'];
        $this->actingAs($this->consumer->user)->patch(route('installation-reschedules.approve', $request), $payload)->assertForbidden();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->actingAs($this->admin)->patch(route('installation-reschedules.approve', $request), $payload)->assertSessionHas('email_error');
        $this->assertSame('assigned', $request->refresh()->status);
        $this->assertSame('2026-11-08 06:00:00', $this->consumer->refresh()->reconnection_scheduled_at->toDateTimeString());
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'installation_reschedule_'.$request->id.'_assigned', 'status' => 'failed']);
    }
}
