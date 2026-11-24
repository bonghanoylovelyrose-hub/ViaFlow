<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_dropdown_follow_saved_membership_changes(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create([
            'user_id' => $user->id,
            'full_name' => 'Profile Consumer',
            'account_number' => 'P-123',
            'meter_number' => 'MT-P-123',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('<span class="small text-secondary">Member</span>', false)
            ->assertSee('Membership Status</div>', false)
            ->assertSee('<div>Member</div>', false);

        $consumer->update(['membership_type' => 'Non-Member']);

        $this->actingAs($user->fresh())->get('/profile')->assertOk()
            ->assertSee('<span class="small text-secondary">Non-Member</span>', false)
            ->assertSee('<div>Non-Member</div>', false);
    }

    public function test_non_consumer_role_label_is_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->actingAs($admin)->get('/profile')->assertOk()
            ->assertSee('<span class="small text-secondary">Admin</span>', false);
    }
}
