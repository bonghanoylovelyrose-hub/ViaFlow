<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduledAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scheduled_announcement_appears_only_during_its_active_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 03:00:00', 'UTC'));
        Queue::fake();
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $user = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $user->id, 'full_name' => 'Consumer', 'status' => 'Active', 'membership_type' => 'Non-Member', 'account_number' => 'A-1', 'meter_number' => 'M-1']);

        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Scheduled interruption', 'body' => 'Water service update.', 'category' => 'Water Interruption',
            'audience' => 'Consumers', 'priority' => 'Urgent', 'is_pinned' => '1',
            'published_at' => '2026-11-07T12:00', 'expires_at' => '2026-11-07T13:00',
            'affected_area' => 'Purok 1-C', 'service_starts_at' => '2026-11-07T12:15',
            'service_restores_at' => '2026-11-07T12:45',
            'photo' => UploadedFile::fake()->createWithContent('advisory.pdf', "%PDF-1.4\n% test attachment\n"),
        ])->assertRedirect(route('announcements.index'))->assertSessionHasNoErrors();

        $announcement = Announcement::query()->firstOrFail();
        $this->assertSame('2026-11-07 04:00:00', $announcement->published_at->format('Y-m-d H:i:s'));
        $this->assertSame('Urgent', $announcement->priority);
        Storage::disk('local')->assertExists($announcement->photo_path);
        Queue::assertNotPushed(SendAnnouncementEmail::class);
        $this->actingAs($user)->get(route('announcements.index'))->assertDontSee('Scheduled interruption');
        $this->get(route('announcements.show', $announcement))->assertForbidden();

        Carbon::setTestNow(Carbon::parse('2026-11-07 04:00:00', 'UTC'));
        $this->get(route('announcements.index'))->assertOk()->assertSee('Scheduled interruption')->assertSee('Open PDF attachment');
        $this->get(route('dashboard'))->assertOk()->assertSee('Scheduled interruption');
        $this->get(route('announcements.show', $announcement))->assertOk()->assertSee('Purok 1-C');
        Artisan::call('announcements:dispatch-due');
        Queue::assertPushed(SendAnnouncementEmail::class, 1);
        Artisan::call('announcements:dispatch-due');
        Queue::assertPushed(SendAnnouncementEmail::class, 1);

        Carbon::setTestNow(Carbon::parse('2026-11-07 05:00:00', 'UTC'));
        $this->get(route('announcements.index'))->assertDontSee('Scheduled interruption');
        $this->get(route('dashboard'))->assertDontSee('Scheduled interruption');
        $this->get(route('announcements.show', $announcement))->assertForbidden();
        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
    }

    public function test_invalid_schedule_and_restoration_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Test', 'body' => 'Details', 'category' => 'Maintenance', 'audience' => 'Everyone',
            'published_at' => '2026-11-07T12:00', 'expires_at' => '2026-11-07T11:00',
            'service_starts_at' => '2026-11-07T12:30', 'service_restores_at' => '2026-11-07T12:15',
        ])->assertSessionHasErrors(['expires_at', 'service_restores_at']);
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_consumer_group_visibility_and_email_recipients_match(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $member = User::factory()->create(['role' => 'consumer']);
        $nonMember = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $member->id, 'full_name' => 'Member', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);
        Consumer::create(['user_id' => $nonMember->id, 'full_name' => 'Non-member', 'status' => 'Active', 'membership_type' => 'Non-Member']);
        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Member notice', 'body' => 'Member details', 'category' => 'Community Announcement', 'audience' => 'Member Consumers',
        ])->assertRedirect(route('announcements.index'))->assertSessionHasNoErrors();
        $announcement = Announcement::query()->firstOrFail();
        $this->assertSame([$member->id], AnnouncementDelivery::query()->pluck('user_id')->all());
        $this->actingAs($member)->get(route('announcements.index'))->assertSee('Member notice');
        $this->actingAs($nonMember)->get(route('announcements.index'))->assertDontSee('Member notice');
        $this->get(route('announcements.show', $announcement))->assertForbidden();
    }

    public function test_expired_queued_email_is_not_sent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 05:00:00', 'UTC'));
        \Illuminate\Support\Facades\Mail::fake();
        $user = User::factory()->create(['role' => 'consumer']);
        $announcement = Announcement::create([
            'title' => 'Expired notice', 'body' => 'Old notice', 'category' => 'General Notice', 'audience' => 'Everyone',
            'published_at' => now()->subHours(2), 'expires_at' => now()->subHour(),
        ]);
        $delivery = AnnouncementDelivery::create(['announcement_id' => $announcement->id, 'user_id' => $user->id, 'recipient_email' => $user->email, 'status' => 'Queued', 'queued_at' => now()->subHours(2)]);
        (new SendAnnouncementEmail($delivery->id))->handle();
        $this->assertSame('Cancelled', $delivery->fresh()->status);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_omitted_publish_time_defaults_to_now_with_a_future_expiration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 03:00:00', 'UTC'));
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Immediate', 'body' => 'Details', 'category' => 'General Notice',
            'audience' => 'Everyone', 'expires_at' => '2026-11-07T12:00',
        ])->assertRedirect(route('announcements.index'))->assertSessionHasNoErrors();
        $this->assertTrue(Announcement::firstOrFail()->isActive());
    }

    public function test_editing_a_scheduled_announcement_to_publish_now_queues_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 03:00:00', 'UTC'));
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        User::factory()->create(['role' => 'consumer']);
        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Later', 'body' => 'Details', 'category' => 'Maintenance',
            'audience' => 'Consumers', 'published_at' => '2026-11-07T12:00',
        ])->assertSessionHasNoErrors();
        $announcement = Announcement::firstOrFail();
        Queue::assertNotPushed(SendAnnouncementEmail::class);

        $this->patch(route('announcements.update', $announcement), [
            'title' => 'Now', 'body' => 'Details', 'category' => 'Maintenance',
            'audience' => 'Consumers', 'published_at' => '2026-11-07T11:00',
        ])->assertSessionHasNoErrors();
        Queue::assertPushed(SendAnnouncementEmail::class, 1);
        $this->assertNotNull($announcement->fresh()->notifications_queued_at);
    }
}
