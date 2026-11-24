<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumerActionsRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_actions_are_unavailable_and_existing_meter_is_preserved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Example Consumer',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'meter_number' => 'MT-EXISTING',
        ]);

        $this->actingAs($admin)->get(route('consumers.index'))
            ->assertOk()
            ->assertDontSee('Assign Meter')
            ->assertDontSee('Delete this consumer?')
            ->assertDontSee('consumerMeterModal', false);

        $this->delete('/consumers/'.$consumer->id)->assertStatus(405);
        $this->post('/consumers/'.$consumer->id.'/assign-meter', ['meter_number' => 'MT-NEW'])->assertNotFound();

        $this->patch('/consumers/'.$consumer->id, [
            'full_name' => 'Updated Consumer',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'meter_number' => 'MT-NEW',
        ])->assertRedirect(route('consumers.index'));

        $this->assertDatabaseHas('consumers', [
            'id' => $consumer->id,
            'full_name' => 'Updated Consumer',
            'meter_number' => 'MT-EXISTING',
        ]);
    }
}
