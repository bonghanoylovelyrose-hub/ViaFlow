<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentLocationCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_select_a_valid_purok(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('incidents.index'))
            ->post(route('incidents.store'), $this->adminPayload(['purok' => null]))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasErrors('purok');

        $this->actingAs($admin)
            ->from(route('incidents.index'))
            ->post(route('incidents.store'), $this->adminPayload(['purok' => 'Purok 9-Z']))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasErrors('purok');

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_admin_can_record_purok_and_a_normalized_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('incidents.store'), $this->adminPayload([
                'purok' => 'Purok 1-C',
                'block' => 'Block 7',
            ]))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();

        $this->assertSame('Purok 1-C', $incident->location);
        $this->assertSame('Purok 1-C', $incident->purok);
        $this->assertSame('7', $incident->block);
    }

    public function test_consumer_can_fall_back_to_profile_purok_and_block(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Profile Location Consumer',
            'account_number' => 'PROFILE-LOC-1001',
            'meter_number' => 'PROFILE-LOC-M-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
            'service_purok' => 'Purok 1-B',
            'address_block' => '3',
        ]);

        $this->actingAs($consumerUser)
            ->post(route('incidents.store'), [
                'category' => 'Water Leak',
                'description' => 'Leak near the service line.',
            ])
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();

        $this->assertSame('Via Vida', $incident->location);
        $this->assertSame('Purok 1-B', $incident->purok);
        $this->assertSame('3', $incident->block);
    }

    public function test_consumer_can_choose_a_valid_purok_and_block(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Selected Location Consumer',
            'account_number' => 'SELECT-LOC-1001',
            'meter_number' => 'SELECT-LOC-M-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
            'service_purok' => 'Purok 1-B',
        ]);

        $this->actingAs($consumerUser)->get(route('incidents.index'))->assertOk()
            ->assertSee('id="location" name="location" value="Via Vida" readonly', false)
            ->assertSee('id="purok" name="purok" required', false)
            ->assertSee('id="block" name="block"', false);

        $this->post(route('incidents.store'), [
                'category' => 'Meter Concern',
                'location' => 'Near the meter',
                'purok' => 'Purok 2-D',
                'block' => 'Blk. 12',
                'description' => 'Meter is difficult to read.',
            ])
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();

        $this->assertSame('Via Vida', $incident->location);
        $this->assertSame('Purok 2-D', $incident->purok);
        $this->assertSame('12', $incident->block);
    }

    public function test_incident_pages_show_purok_and_block_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('for="purok"', false)
            ->assertSee('for="block"', false)
            ->assertSee('Select purok')
            ->assertSee('Purok 1-C');
    }

    private function adminPayload(array $overrides = []): array
    {
        $consumer = Consumer::query()->firstOrCreate(['meter_number' => 'ADMIN-LOC-M-1001'], [
            'full_name' => 'Incident Location Consumer',
            'account_number' => 'ADMIN-LOC-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        return array_merge([
            'consumer_id' => $consumer->id,
            'meter_number' => $consumer->meter_number,
            'reported_by' => 'Incident Admin',
            'category' => 'Water Leak',
            'location' => 'Purok 1-C',
            'purok' => 'Purok 1-C',
            'description' => 'Leak near the main line.',
        ], $overrides);
    }
}
