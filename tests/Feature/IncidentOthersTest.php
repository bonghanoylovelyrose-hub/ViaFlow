<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentOthersTest extends TestCase
{
    use RefreshDatabase;

    public function test_others_is_rendered_saved_and_filtered_for_both_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $first = User::factory()->create(['role' => 'consumer']);
        $second = User::factory()->create(['role' => 'consumer']);
        $c1 = Consumer::create(['user_id' => $first->id, 'full_name' => 'First', 'account_number' => 'I-1', 'meter_number' => 'M-1', 'status' => 'Active', 'membership_type' => 'Member', 'service_purok' => 'Purok 1-C']);
        $c2 = Consumer::create(['user_id' => $second->id, 'full_name' => 'Second', 'account_number' => 'I-2', 'meter_number' => 'M-2', 'status' => 'Active', 'membership_type' => 'Member', 'service_purok' => 'Purok 1-C']);

        foreach ([$admin, $first] as $user) {
            $html = $this->actingAs($user)->get(route('incidents.index'))->assertOk()->getContent();
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            foreach (['category', 'incident_filter_category'] as $id) {
                $this->assertSame(1, $xpath->query("//select[@id='$id']/option[@value='Others']")->length, $id);
                $this->assertSame(1, $xpath->query("//select[@id='$id']/option[@value='Water Leak']")->length, $id);
            }
            $this->assertSame($user->isAdmin() ? 1 : 0, $xpath->query("//select[@id='incident_filter_category']/option[@value='']")->length);
            $this->assertStringContainsString('Please Specify Incident Type', $html);
        }

        $base = ['category' => 'Others', 'location' => 'Zone A', 'purok' => 'Purok 1-C', 'description' => 'Service concern'];
        $this->actingAs($first)->post(route('incidents.store'), $base + ['custom_incident_type' => 'Low Water Pressure'])->assertSessionHasNoErrors();
        $this->actingAs($second)->post(route('incidents.store'), $base + ['custom_incident_type' => 'Damaged Water Valve'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('incidents.store'), $base + ['custom_incident_type' => 'Broken Water Pipe', 'consumer_id' => $c1->id, 'meter_number' => 'M-1', 'reported_by' => 'Admin'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incidents', ['category' => 'Others', 'custom_incident_type' => 'Low Water Pressure', 'consumer_id' => $c1->id]);
        $this->assertDatabaseHas('incidents', ['category' => 'Others', 'custom_incident_type' => 'Damaged Water Valve', 'consumer_id' => $c2->id]);
        $this->assertDatabaseHas('incidents', ['category' => 'Others', 'custom_incident_type' => 'Broken Water Pipe']);
        Incident::create(['consumer_id' => $c1->id, 'reported_by' => 'Legacy', 'category' => '  oThErS  ', 'custom_incident_type' => 'Legacy Concern', 'location' => 'Zone B', 'description' => 'Legacy', 'status' => 'Resolved', 'reported_at' => now()]);
        Incident::create(['consumer_id' => $c1->id, 'reported_by' => 'Leak', 'category' => 'Water Leak', 'location' => 'Zone A', 'description' => 'Leak', 'status' => 'Pending', 'reported_at' => now()]);

        $result = $this->actingAs($admin)->get(route('incidents.index', ['category' => 'Others']))->assertOk()->assertSee('Low Water Pressure')->assertSee('Damaged Water Valve')->assertSee('Broken Water Pipe')->assertSee('Legacy Concern');
        $this->assertSame(4, $result->viewData('incidents')->total());
        $this->assertSame(5, $this->get(route('incidents.index'))->viewData('incidents')->total());
        $this->assertSame(1, $this->get(route('incidents.index', ['category' => 'Others', 'status' => 'Resolved', 'search' => 'Zone B']))->viewData('incidents')->total());
        $result = $this->actingAs($first)->get(route('incidents.index', ['category' => 'Others']))->assertOk()->assertSee('Low Water Pressure')->assertSee('Broken Water Pipe')->assertSee('Legacy Concern')->assertDontSee('Damaged Water Valve');
        $this->assertSame(3, $result->viewData('incidents')->total());
    }

    public function test_others_requires_custom_type_and_preserves_input_on_validation_error(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $user->id, 'full_name' => 'First', 'account_number' => 'I-1', 'meter_number' => 'M-1', 'status' => 'Active', 'membership_type' => 'Member', 'service_purok' => 'Purok 1-C']);
        $base = ['category' => 'Others', 'location' => 'Zone A', 'description' => 'Concern'];
        $this->actingAs($user)->from(route('incidents.index'))->post(route('incidents.store'), $base)->assertSessionHasErrors('custom_incident_type');
        $this->from(route('incidents.index'))->post(route('incidents.store'), array_merge($base, ['description' => '', 'custom_incident_type' => 'Original Type']))->assertSessionHasErrors('description')->assertSessionHasInput('custom_incident_type', 'Original Type');
        $this->post(route('incidents.store'), array_merge($base, ['category' => 'Water Leak', 'custom_incident_type' => 'ignored']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incidents', ['category' => 'Water Leak', 'custom_incident_type' => null]);
    }
}
