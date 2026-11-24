<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoFilterUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_filter_pages_auto_submit_without_visible_apply_buttons(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $pages = [
            route('dashboard'),
            route('reports.index'),
            route('dss.incidents'),
            route('dss.loans'),
            route('consumers.index'),
            route('billing.index'),
            route('payments.index'),
            route('loans.index'),
            route('incidents.index'),
            route('meter-readings.index'),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($admin)->get($url);

            $response->assertOk()
                ->assertSee('data-auto-filter', false)
                ->assertSee('data-auto-filter-debounce="450"', false)
                ->assertDontSee('>Apply</button>', false)
                ->assertDontSee('>Filter</button>', false);
        }
    }

    public function test_consumer_filter_pages_auto_submit_without_visible_apply_buttons(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Auto Filter Consumer',
            'account_number' => 'AUTO-FILTER-1001',
            'meter_number' => 'AUTO-FILTER-M-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
            'membership_verification_status' => 'Verified',
            'membership_started_at' => now()->subMonths(7),
        ]);

        $pages = [
            route('billing.index'),
            route('payments.index'),
            route('loans.index'),
            route('incidents.index'),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($consumerUser)->get($url);

            $response->assertOk()
                ->assertSee('data-auto-filter', false)
                ->assertDontSee('>Apply</button>', false)
                ->assertDontSee('>Filter</button>', false);
        }
    }

    public function test_meter_reader_filter_pages_auto_submit_without_visible_apply_buttons(): void
    {
        $reader = User::factory()->create(['role' => 'meter_reader']);

        foreach ([route('meter-readings.index')] as $url) {
            $response = $this->actingAs($reader)->get($url);

            $response->assertOk()
                ->assertSee('data-auto-filter', false)
                ->assertDontSee('>Apply</button>', false)
                ->assertDontSee('>Filter</button>', false);
        }
    }
}
