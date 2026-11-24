<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementEmail;
use App\Mail\AnnouncementPublished;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\User;
use App\Services\AnnouncementNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementAudienceAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_everyone_announcement_is_visible_and_queued_individually_for_registered_email_accounts(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = User::factory()->create(['role' => 'consumer', 'email' => 'consumer-audience@example.test']);
        $reader = User::factory()->create(['role' => 'meter_reader', 'email' => 'reader-audience@example.test']);

        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Everyone notice',
            'body' => 'Visible to all accounts.',
            'category' => 'Community Update',
            'audience' => 'Everyone',
        ])->assertRedirect(route('announcements.index'));

        $announcement = Announcement::query()->firstOrFail();
        $this->assertDatabaseCount('announcement_deliveries', 3);
        Queue::assertPushed(SendAnnouncementEmail::class, 3);
        Queue::assertPushed(SendAnnouncementEmail::class, fn (SendAnnouncementEmail $job) => $job->connection === 'database' && $job->queue === 'announcements');
        $this->actingAs($consumer)->get(route('announcements.show', $announcement))->assertOk()->assertSee('Everyone notice');
        $this->actingAs($reader)->get(route('announcements.index'))->assertSee('Everyone notice');
    }

    public function test_meter_reader_audience_is_restricted_for_feed_details_and_photo_access(): void
    {
        Queue::fake();
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = User::factory()->create(['role' => 'consumer']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $announcement = Announcement::query()->create([
            'user_id' => $admin->id,
            'title' => 'Reader only',
            'body' => 'Private to meter readers.',
            'category' => 'Operations',
            'audience' => 'Meter Readers',
            'published_at' => now(),
            'photo_path' => UploadedFile::fake()->image('reader.png')->store('announcements', 'local'),
        ]);
        app(AnnouncementNotificationService::class)->queueForPublishedAnnouncement($announcement);
        $this->assertSame([$reader->id], AnnouncementDelivery::query()->pluck('user_id')->all());
        Queue::assertPushed(SendAnnouncementEmail::class, 1);

        $this->actingAs($reader)->get(route('announcements.index'))->assertSee('Reader only');
        $this->actingAs($reader)->get(route('announcements.show', $announcement))->assertOk();
        $this->actingAs($reader)->get(route('announcements.photo', $announcement))->assertOk();
        $this->actingAs($consumer)->get(route('announcements.index'))->assertDontSee('Reader only');
        $this->actingAs($consumer)->get(route('announcements.show', $announcement))->assertForbidden();
        $this->actingAs($consumer)->get(route('announcements.photo', $announcement))->assertForbidden();
        $this->actingAs($consumer)->get(route('uploads.serve', ['path' => $announcement->photo_path]))->assertNotFound();
    }

    public function test_specific_user_audience_uploads_image_and_prevents_duplicate_delivery_jobs(): void
    {
        Queue::fake();
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $selected = User::factory()->create(['role' => 'consumer', 'email' => 'selected@example.test']);
        $unselected = User::factory()->create(['role' => 'consumer', 'email' => 'unselected@example.test']);

        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Selected audience',
            'body' => 'Only selected accounts can read this.',
            'category' => 'Community Update',
            'audience' => 'Specific Users',
            'recipient_user_ids' => [$selected->id],
            'photo' => UploadedFile::fake()->image('notice.png'),
        ])->assertRedirect(route('announcements.index'));

        $announcement = Announcement::query()->firstOrFail();
        $oldPhotoPath = $announcement->photo_path;
        $this->assertNotEmpty($announcement->photo_path);
        Storage::disk('local')->assertExists($announcement->photo_path);
        $this->assertDatabaseCount('announcement_deliveries', 1);
        Queue::assertPushed(SendAnnouncementEmail::class, 1);

        $this->actingAs($selected)->get(route('announcements.show', $announcement))->assertOk();
        $this->actingAs($unselected)->get(route('announcements.show', $announcement))->assertForbidden();
        $this->actingAs($unselected)->get(route('announcements.photo', $announcement))->assertForbidden();

        app(AnnouncementNotificationService::class)->queueForPublishedAnnouncement($announcement);
        $this->assertSame(1, AnnouncementDelivery::query()->where('announcement_id', $announcement->id)->count());
        Queue::assertPushed(SendAnnouncementEmail::class, 1);

        $this->actingAs($admin)->patch(route('announcements.update', $announcement), [
            'title' => 'Edited without resending',
            'body' => 'Updated body.',
            'category' => 'Community Update',
            'audience' => 'Specific Users',
            'recipient_user_ids' => [$selected->id],
            'photo' => UploadedFile::fake()->image('replacement.png'),
        ])->assertRedirect(route('announcements.index'));
        Storage::disk('local')->assertMissing($oldPhotoPath);
        $replacementPath = $announcement->fresh()->photo_path;
        Storage::disk('local')->assertExists($replacementPath);
        Queue::assertPushed(SendAnnouncementEmail::class, 1);

        $this->actingAs($admin)->patch(route('announcements.update', $announcement), [
            'title' => 'Edited without resending',
            'body' => 'Updated body.',
            'category' => 'Community Update',
            'audience' => 'Specific Users',
            'recipient_user_ids' => [$selected->id],
            'remove_photo' => '1',
        ])->assertRedirect(route('announcements.index'));
        $this->assertNull($announcement->fresh()->photo_path);
        Storage::disk('local')->assertMissing($replacementPath);
        Queue::assertPushed(SendAnnouncementEmail::class, 1);
    }

    public function test_email_job_records_successful_individual_delivery(): void
    {
        Mail::fake();
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'consumer', 'email' => 'delivery@example.test']);
        $photoPath = UploadedFile::fake()->image('announcement.png')->store('announcements', 'local');
        $announcement = Announcement::query()->create([
            'title' => 'Delivery status test',
            'body' => 'This message is addressed to one account.',
            'category' => 'Community Update',
            'audience' => 'Everyone',
            'published_at' => now(),
            'photo_path' => $photoPath,
        ]);
        $delivery = AnnouncementDelivery::query()->create([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'recipient_email' => $user->email,
            'status' => 'Queued',
            'queued_at' => now(),
        ]);

        (new SendAnnouncementEmail($delivery->id))->handle();
        $mailable = new AnnouncementPublished($announcement);
        $mailable->build();

        $this->assertDatabaseHas('announcement_deliveries', [
            'id' => $delivery->id,
            'status' => 'Sent',
            'attempts' => 1,
        ]);
        Mail::assertSent(AnnouncementPublished::class, fn (AnnouncementPublished $mail) => $mail->hasTo($user->email));
        $this->assertTrue($mailable->hasAttachmentFromStorageDisk('local', $photoPath, 'announcement-image.png'));
    }

    public function test_publish_does_not_send_smtp_synchronously_when_default_queue_is_sync(): void
    {
        config(['queue.default' => 'sync']);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        User::factory()->create(['role' => 'consumer', 'email' => 'async-recipient@example.test']);

        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Publish without waiting on SMTP',
            'body' => 'The email is put on the database queue.',
            'category' => 'Community Update',
            'audience' => 'Everyone',
        ])->assertRedirect(route('announcements.index'));

        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseCount('announcement_deliveries', 2);
        $this->assertDatabaseCount('jobs', 2);
    }
}
