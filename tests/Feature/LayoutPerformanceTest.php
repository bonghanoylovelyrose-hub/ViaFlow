<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LayoutPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layout_metadata_is_queried_once_per_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $notificationCounts = 0;
        DB::listen(function ($query) use (&$notificationCounts): void {
            if (str_contains($query->sql, 'count(*)') && str_contains($query->sql, '"notifications"')) {
                $notificationCounts++;
            }
        });

        $this->actingAs($admin)->get(route('staff.index'))->assertOk()->assertSee('Staff Management');
        $this->assertSame(1, $notificationCounts);

        $this->get(route('staff.index'))->assertOk();
        $this->assertSame(2, $notificationCounts);
    }
}
