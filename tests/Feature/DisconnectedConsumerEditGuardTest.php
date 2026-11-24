<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the edit guard also applies to For Reconnection accounts and that
 * genuine reconnection approval still works.
 */
class DisconnectedConsumerEditGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeConsumer(string $status): Consumer
    {
        return Consumer::query()->create([
            'full_name' => 'Disconnected Person',
            'email' => 'disconnected@example.com',
            'account_number' => 'DC-'.$status,
            'meter_number' => 'DC-M-'.$status,
            'status' => $status,
            'membership_type' => 'Non-Member',
        ]);
    }

    public function test_admin_can_still_edit_profile_fields_of_a_disconnected_consumer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->makeConsumer('Disconnected');

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => 'Renamed While Disconnected',
                'email' => 'disconnected@example.com',
                'status' => 'Disconnected',
                'membership_type' => 'Non-Member',
                'contact_number' => '09171234567',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed While Disconnected', $consumer->refresh()->full_name);
        $this->assertSame('Disconnected', $consumer->status);
    }

    public function test_a_plain_edit_cannot_silently_reactivate_a_disconnected_consumer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->makeConsumer('Disconnected');

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => 'Trying To Reactivate',
                'email' => 'disconnected@example.com',
                'status' => 'Active',
                'membership_type' => 'Non-Member',
            ])
            ->assertSessionHasErrors('status');

        $this->assertNotSame('Active', $consumer->refresh()->status);
    }

    public function test_a_for_reconnection_account_keeps_its_status_on_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->makeConsumer('For Reconnection');

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => 'Renamed Awaiting Reconnection',
                'email' => 'disconnected@example.com',
                'status' => 'Active',
                'membership_type' => 'Non-Member',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('For Reconnection', $consumer->refresh()->status);
    }

    public function test_an_active_consumer_can_still_be_edited_normally(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->makeConsumer('Active');

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => 'Normal Active Edit',
                'email' => 'disconnected@example.com',
                'status' => 'For Review',
                'membership_type' => 'Member',
            ])
            ->assertSessionHasNoErrors();

        $consumer->refresh();
        $this->assertSame('Normal Active Edit', $consumer->full_name);
        $this->assertSame('For Review', $consumer->status);
    }

    public function test_reconnection_approval_still_moves_the_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->makeConsumer('For Reconnection');
        $consumer->forceFill(['user_id' => $user->id])->save();

        $request = ServiceRequest::query()->create([
            'consumer_id' => $consumer->id,
            'request_type' => 'Reconnection',
            'status' => 'Pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), ['status' => 'Approved'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Approved', $request->refresh()->status);
        $this->assertSame('For Reconnection', $consumer->refresh()->status);
    }
}