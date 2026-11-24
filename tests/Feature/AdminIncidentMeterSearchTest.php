<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminIncidentMeterSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_searches_real_meter_numbers_and_can_log_incident_for_selected_consumer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = Consumer::create([
            'full_name' => 'Meter Search Consumer', 'status' => 'Active', 'membership_type' => 'Non-Member',
            'account_number' => 'AC-123', 'meter_number' => 'MT-123',
            'service_address' => 'Block 4, Via Vida', 'service_purok' => 'Purok 1-C', 'address_block' => '4',
        ]);
        $this->actingAs($admin)->getJson(route('incidents.meter-search', ['q' => '123']))
            ->assertOk()->assertJsonFragment(['id' => $consumer->id, 'full_name' => 'Meter Search Consumer', 'meter_number' => 'MT-123']);
        $this->getJson(route('incidents.meter-search', ['q' => 'NO-MATCH']))->assertOk()->assertExactJson([]);
        $this->get(route('incidents.index'))->assertOk()->assertSee('Search Meter Number')->assertSee('Consumer Details');

        $payload = [
            'consumer_id' => $consumer->id, 'meter_number' => 'MT-123', 'reported_by' => $admin->name,
            'category' => 'Water Leak', 'location' => 'Another location', 'purok' => 'Purok 1-C',
            'block' => '4', 'description' => 'Water leak at the service connection.',
        ];
        $this->post(route('incidents.store'), $payload)->assertRedirect(route('incidents.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('incidents', ['consumer_id' => $consumer->id, 'location' => 'Another location', 'reported_by' => $admin->name]);
    }

    public function test_admin_must_select_a_matching_current_meter_and_search_is_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = Consumer::create(['full_name' => 'Consumer', 'status' => 'Active', 'membership_type' => 'Non-Member', 'meter_number' => 'MT-456']);
        $payload = ['reported_by' => 'Admin', 'category' => 'Water Leak', 'location' => 'Via Vida', 'purok' => 'Purok 1-C', 'description' => 'Leak'];

        $this->actingAs($admin)->post(route('incidents.store'), $payload)->assertSessionHasErrors(['consumer_id', 'meter_number']);
        $this->post(route('incidents.store'), $payload + ['consumer_id' => $consumer->id, 'meter_number' => 'MT-OTHER'])->assertSessionHasErrors('consumer_id');
        $this->assertDatabaseCount('incidents', 0);
        $this->actingAs($reader)->getJson(route('incidents.meter-search', ['q' => 'MT']))->assertForbidden();
    }

    public function test_admin_can_attach_evidence_to_a_meter_linked_incident(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = Consumer::create(['full_name' => 'Consumer', 'status' => 'Active', 'membership_type' => 'Non-Member', 'meter_number' => 'MT-789']);
        $this->actingAs($admin)->post(route('incidents.store'), [
            'consumer_id' => $consumer->id, 'meter_number' => 'MT-789', 'reported_by' => $admin->name,
            'category' => 'Meter Concern', 'location' => 'Via Vida', 'purok' => 'Purok 1-C',
            'description' => 'Meter needs attention.', 'photo' => UploadedFile::fake()->image('meter.jpg'),
        ])->assertRedirect(route('incidents.index'))->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();
        $this->assertSame($consumer->id, $incident->consumer_id);
        $this->assertSame('image', $incident->media_type);
        Storage::disk('public')->assertExists($incident->photo_path);
    }
}
