<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_announcements(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin);

        $this->post(route('announcements.store'), [
            'title' => 'Temporary Interruption',
            'body' => 'Water service will pause for line maintenance.',
            'category' => 'Service Advisory',
            'audience' => 'Everyone',
            'is_pinned' => '1',
        ])->assertRedirect(route('announcements.index'));

        $announcement = Announcement::firstOrFail();

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Temporary Interruption',
            'is_pinned' => true,
        ]);

        $this->patch(route('announcements.update', $announcement), [
            'title' => 'Temporary Interruption Updated',
            'body' => 'Service resumes after noon.',
            'category' => 'Service Advisory',
            'audience' => 'Staff',
        ])->assertRedirect(route('announcements.index'));

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Temporary Interruption Updated',
            'audience' => 'Staff',
        ]);

        $this->delete(route('announcements.destroy', $announcement))
            ->assertRedirect(route('announcements.index'));

        $this->assertSoftDeleted('announcements', ['id' => $announcement->id, 'deleted_by' => $admin->id]);
        $this->assertNotNull(Announcement::withTrashed()->findOrFail($announcement->id)->edited_at);
        $this->get(route('announcements.index', ['deleted' => 1]))->assertOk()->assertSee('Temporary Interruption Updated')->assertSee('Deleted');
        $this->get(route('announcements.index'))->assertDontSee('Temporary Interruption Updated');
    }

    public function test_consumer_only_sees_consumer_visible_announcements(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        Announcement::query()->create([
            'title' => 'Visible to All',
            'body' => 'General community update.',
            'category' => 'Community Update',
            'audience' => 'Everyone',
            'published_at' => now(),
        ]);

        Announcement::query()->create([
            'title' => 'Visible to Consumers',
            'body' => 'Billing reminder for households.',
            'category' => 'Billing Notice',
            'audience' => 'Consumers',
            'published_at' => now(),
        ]);

        Announcement::query()->create([
            'title' => 'Staff Only Memo',
            'body' => 'Internal routing note.',
            'category' => 'Operations',
            'audience' => 'Staff',
            'published_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Visible to All')
            ->assertSee('Visible to Consumers')
            ->assertDontSee('Staff Only Memo');
    }

    public function test_non_admin_cannot_manage_announcements(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $announcement = Announcement::query()->create([
            'title' => 'Existing',
            'body' => 'Existing body.',
            'category' => 'Community Update',
            'audience' => 'Everyone',
            'published_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->post(route('announcements.store'), [
                'title' => 'Blocked',
                'body' => 'Blocked body.',
                'category' => 'Community Update',
                'audience' => 'Everyone',
            ])
            ->assertForbidden();

        $this->actingAs($consumer)
            ->patch(route('announcements.update', $announcement), [
                'title' => 'Blocked edit',
                'body' => 'Blocked body.',
                'category' => 'Community Update',
                'audience' => 'Everyone',
            ])
            ->assertForbidden();

        $this->actingAs($consumer)
            ->delete(route('announcements.destroy', $announcement))
            ->assertForbidden();
    }

    public function test_announcement_filters_narrow_admin_and_consumer_feeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        Announcement::query()->create([
            'title' => 'Maintenance Notice',
            'body' => 'Line maintenance schedule.',
            'category' => 'Service Advisory',
            'audience' => 'Everyone',
            'published_at' => now(),
        ]);
        Announcement::query()->create([
            'title' => 'Billing Reminder',
            'body' => 'Monthly billing reminder.',
            'category' => 'Billing Notice',
            'audience' => 'Consumers',
            'published_at' => now(),
        ]);
        Announcement::query()->create([
            'title' => 'Staff Routing',
            'body' => 'Internal operations routing.',
            'category' => 'Operations',
            'audience' => 'Staff',
            'published_at' => now(),
        ]);

        $adminCategory = $this->actingAs($admin)->get(route('announcements.index', ['category' => 'Billing Notice']));
        $adminCategory->assertOk()
            ->assertSee('Billing Reminder')
            ->assertDontSee('Maintenance Notice')
            ->assertDontSee('Staff Routing')
            ->assertDontSee('Announcement Feed')
            ->assertDontSee('announcement_filter_category', false)
            ->assertDontSee('announcement_filter_search', false)
            ->assertDontSee('announcement_filter_audience', false)
            ->assertDontSee('>Clear</a>', false);

        $adminAudience = $this->actingAs($admin)->get(route('announcements.index', ['audience' => 'Staff']));
        $adminAudience->assertOk()
            ->assertSee('Staff Routing')
            ->assertDontSee('Maintenance Notice');
        $this->assertNotContains('Billing Reminder', $adminAudience->viewData('announcements')->pluck('title')->all());

        $adminSearch = $this->actingAs($admin)->get(route('announcements.index', ['search' => 'maintenance']));
        $adminSearch->assertOk()
            ->assertSee('Maintenance Notice')
            ->assertDontSee('Staff Routing');
        $this->assertNotContains('Billing Reminder', $adminSearch->viewData('announcements')->pluck('title')->all());

        $consumerFeed = $this->actingAs($consumer)->get(route('announcements.index', ['category' => 'Billing Notice']));
        $consumerFeed->assertOk()
            ->assertSee('Billing Reminder')
            ->assertDontSee('Maintenance Notice')
            ->assertDontSee('Staff Routing')
            ->assertSee('data-auto-filter', false);
    }
}
