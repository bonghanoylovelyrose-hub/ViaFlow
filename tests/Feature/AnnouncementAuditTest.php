<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_edits_reach_consumers_and_deletion_retains_audit_and_cancels_delivery(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => 'Original notice', 'body' => 'Original details',
            'category' => 'General Notice', 'audience' => 'Everyone',
            'photo' => UploadedFile::fake()->image('notice.png'),
        ])->assertSessionHasNoErrors();
        $announcement = Announcement::firstOrFail();
        $attachment = $announcement->photo_path;
        Storage::disk('local')->assertExists($attachment);

        $this->actingAs($admin)->patch(route('announcements.update', $announcement), [
            'title' => 'Updated notice', 'body' => 'Updated details',
            'category' => 'Maintenance', 'audience' => 'Everyone', 'priority' => 'Important',
            'is_pinned' => '1', 'affected_area' => 'Purok 1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('announcements', 1);
        $this->assertNotNull($announcement->fresh()->edited_at);
        $this->actingAs($consumer)->get(route('announcements.index'))
            ->assertOk()->assertSee('Updated notice')->assertSee('Edited')->assertDontSee('Original notice');

        $this->actingAs($admin)->delete(route('announcements.destroy', $announcement))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('announcements', ['id' => $announcement->id, 'deleted_by' => $admin->id]);
        Storage::disk('local')->assertExists($attachment);
        $this->assertDatabaseHas('announcement_deliveries', ['announcement_id' => $announcement->id, 'user_id' => $consumer->id, 'status' => 'Cancelled']);
        $this->actingAs($consumer)->get(route('announcements.index'))->assertDontSee('Updated notice');
        $this->get(route('announcements.show', $announcement))->assertNotFound();
        $this->get(route('announcements.index', ['deleted' => 1]))->assertDontSee('Updated notice');
        $this->actingAs($admin)->get(route('announcements.index', ['deleted' => 1]))
            ->assertSee('Updated notice')->assertSee($admin->name);
    }
}
