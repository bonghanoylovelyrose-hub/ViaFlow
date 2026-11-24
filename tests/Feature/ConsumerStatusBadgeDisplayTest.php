<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsumerStatusBadgeDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_consumer_management_table_uses_the_service_status_labels(): void
    {
        $this->consumer([
            'full_name' => 'Connected Resident',
            'status' => 'Active',
            'account_number' => 'CSB-ACTIVE',
            'meter_number' => 'CSB-M-ACTIVE',
        ]);

        $this->consumer([
            'full_name' => 'Awaiting Approval Resident',
            'status' => 'Pending Verification',
        ]);

        $this->consumer([
            'full_name' => 'Disconnected Resident',
            'status' => 'Disconnected',
            'account_number' => 'CSB-OFF',
            'meter_number' => 'CSB-M-OFF',
        ]);

        $response = $this->actingAs($this->admin)->get(route('consumers.index'));

        $response->assertOk()
            ->assertSee('Connected Resident')
            ->assertSee('Awaiting Approval Resident')
            ->assertSee('Disconnected Resident')
            ->assertSee('class="consumer-service-status"', false)
            ->assertSee('consumer-status-sub consumer-status-sub-success', false)
            ->assertSee('consumer-status-sub consumer-status-sub-warning', false)
            ->assertSee('consumer-status-sub consumer-status-sub-neutral', false)
            ->assertSee('>Active</span>', false)
            ->assertSee('>Pending</span>', false)
            ->assertSee('>Inactive</span>', false)
            ->assertSee('>Connected</span>', false)
            ->assertSee('>For Approval</span>', false)
            ->assertSee('>Disconnected</span>', false)
            ->assertSee('bi-check-circle-fill', false)
            ->assertSee('bi-clock-fill', false)
            ->assertSee('bi-slash-circle-fill', false);
    }

    public function test_completed_reconnection_is_presented_as_connected_in_consumer_management(): void
    {
        $this->consumer([
            'full_name' => 'Reconnected Resident',
            'status' => 'For Reconnection',
            'account_number' => 'CSB-RECONNECTED',
            'meter_number' => 'CSB-M-RECONNECTED',
            'disconnected_at' => Carbon::parse('2026-09-01 08:00:00'),
            'reconnected_at' => Carbon::parse('2026-09-05 08:00:00'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('consumers.index'))
            ->assertOk()
            ->assertSee('Reconnected Resident')
            ->assertSee('consumer-status-sub consumer-status-sub-success', false)
            ->assertSee('>Connected</span>', false)
            ->assertDontSee('consumer-status-sub consumer-status-sub-neutral', false);
    }

    public function test_consumer_connection_page_shows_the_service_status_badge(): void
    {
        [$user, $consumer] = $this->consumerAccount([
            'status' => 'Active',
            'account_number' => 'CSB-CONSUMER',
            'meter_number' => 'CSB-M-CONSUMER',
        ]);

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertOk()
            ->assertSee($consumer->full_name)
            ->assertSee('consumer-status-sub consumer-status-sub-success', false)
            ->assertSee('>Connected</span>', false);
    }

    public function test_disconnected_consumer_profile_redirects_to_the_status_popup(): void
    {
        [$user] = $this->consumerAccount([
            'status' => 'Disconnected',
            'account_number' => 'CSB-PROFILE',
            'meter_number' => 'CSB-M-PROFILE',
        ]);

        $this->actingAs($user)
            ->get(route('consumer.profile.show'))
            ->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertOk()->assertSee('Inactive / Disconnected');
    }

    public function test_consumer_dashboard_shows_the_service_status_badge(): void
    {
        [$user] = $this->consumerAccount([
            'status' => 'Active',
            'account_number' => 'CSB-DASHBOARD',
            'meter_number' => 'CSB-M-DASHBOARD',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Service Status')
            ->assertSee('consumer-status-sub consumer-status-sub-success', false)
            ->assertSee('>Connected</span>', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function consumer(array $attributes): Consumer
    {
        return Consumer::query()->create($attributes + [
            'full_name' => 'Status Resident',
            'membership_type' => 'Non-Member',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: Consumer}
     */
    private function consumerAccount(array $attributes): array
    {
        $user = User::factory()->create([
            'role' => 'consumer',
            'must_change_password' => false,
        ]);

        $consumer = $this->consumer($attributes + [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return [$user, $consumer];
    }
}
