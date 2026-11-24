<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\IncidentStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentResolutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_resolve_an_incident_and_stamps_the_resolution_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);

        $incident = $this->createIncident($consumer, ['status' => 'Ongoing']);

        $this->actingAs($admin)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Incident Review')
            ->assertSee('id="incidentPriorityForm"', false)
            ->assertDontSee('incidentResolveModal', false)
            ->assertDontSee('incidentResolveForm', false);

        $this->actingAs($admin)
            ->patch(route('incidents.update', $incident), [
                'status' => 'Resolved',
                'resolution_notes' => 'Pipe replaced and tested.',
            ])
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('success', 'Incident report updated successfully.');

        $incident->refresh();

        $this->assertSame('Resolved', $incident->status);
        $this->assertSame('Pipe replaced and tested.', $incident->resolution_notes);
        $this->assertNotNull($incident->resolved_at);

        // The consumer is notified of the resolution.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $consumerUser->id,
            'type' => IncidentStatusNotification::class,
        ]);
    }

    public function test_admin_cannot_resolve_an_incident_without_resolution_notes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();
        $incident = $this->createIncident($consumer, ['status' => 'Pending']);

        $this->actingAs($admin)
            ->from(route('incidents.index'))
            ->patch(route('incidents.update', $incident), [
                'status' => 'Resolved',
                'resolution_notes' => '',
            ])
            ->assertSessionHasErrors('resolution_notes');

        $this->assertSame('Pending', $incident->refresh()->status);
        $this->assertNull($incident->resolved_at);
    }

    public function test_consumer_cannot_use_the_admin_update_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $incident = $this->createIncident($consumer);

        $this->actingAs($consumerUser)
            ->patch(route('incidents.update', $incident), [
                'status' => 'Resolved',
                'resolution_notes' => 'Consumer should not be able to do this.',
            ])
            ->assertForbidden();

        $this->assertSame('Pending', $incident->refresh()->status);
    }

    public function test_pending_incidents_are_ordered_oldest_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $newest = $this->createIncident($consumer, [
            'location' => 'Newest Report',
            'reported_at' => now()->subDays(1),
        ]);
        $oldest = $this->createIncident($consumer, [
            'location' => 'Oldest Report',
            'reported_at' => now()->subDays(30),
        ]);
        $middle = $this->createIncident($consumer, [
            'location' => 'Middle Report',
            'reported_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('incidents.index', ['status' => 'Pending']))
            ->assertOk();

        $html = $response->getContent();

        // First-In, First-Out: oldest unresolved report appears first.
        $this->assertTrue(
            strpos($html, 'Oldest Report') < strpos($html, 'Middle Report'),
            'Expected the oldest pending report to be listed first.',
        );
        $this->assertTrue(
            strpos($html, 'Middle Report') < strpos($html, 'Newest Report'),
            'Expected the oldest pending report to be listed first.',
        );
    }

    public function test_resolved_incidents_are_ordered_by_most_recent_resolution_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $oldest = $this->createIncident($consumer, [
            'location' => 'Resolved Long Ago',
            'reported_at' => now()->subDays(40),
            'status' => 'Resolved',
            'resolved_at' => now()->subDays(30),
        ]);
        $newest = $this->createIncident($consumer, [
            'location' => 'Resolved Just Now',
            'reported_at' => now()->subDays(2),
            'status' => 'Resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('incidents.index', ['status' => 'Resolved']))
            ->assertOk();

        $html = $response->getContent();

        $this->assertTrue(
            strpos($html, 'Resolved Just Now') < strpos($html, 'Resolved Long Ago'),
            'Expected the newest resolution to appear at the top of the Resolved list.',
        );
    }

    public function test_pending_and_resolved_filters_split_the_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createIncident($consumer, ['location' => 'Still Open', 'status' => 'Pending']);
        $this->createIncident($consumer, [
            'location' => 'Already Closed',
            'status' => 'Resolved',
            'resolved_at' => now(),
        ]);

        $pending = $this->actingAs($admin)
            ->get(route('incidents.index', ['status' => 'Pending']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Still Open', $pending);
        $this->assertStringNotContainsString('Already Closed', $pending);

        $resolved = $this->actingAs($admin)
            ->get(route('incidents.index', ['status' => 'Resolved']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Already Closed', $resolved);
        $this->assertStringNotContainsString('Still Open', $resolved);
    }

    public function test_consumer_can_mark_own_pending_incident_as_resolved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $incident = $this->createIncident($consumer);

        $this->actingAs($consumerUser)
            ->post(route('incidents.confirm', $incident))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('success', 'Incident report marked as resolved.');

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => 'Resolved',
            'resolution_notes' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Resolved');

        $this->actingAs($consumerUser)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Resolved')
            ->assertSee('data-incident-can-resolve="0"', false);
    }

    public function test_consumer_resolution_json_updates_without_page_reload(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $incident = $this->createIncident($consumer);

        $this->actingAs($consumerUser)
            ->postJson(route('incidents.confirm', $incident))
            ->assertOk()
            ->assertJson([
                'message' => 'Incident report marked as resolved.',
                'status' => 'Resolved',
            ]);
    }

    public function test_consumer_cannot_resolve_another_consumers_incident_or_resolve_twice(): void
    {
        $firstUser = User::factory()->create(['role' => 'consumer']);
        $secondUser = User::factory()->create(['role' => 'consumer']);
        $firstConsumer = $this->createConsumer([
            'user_id' => $firstUser->id,
            'account_number' => 'INC-2001',
            'meter_number' => 'IM-2001',
        ]);
        $this->createConsumer([
            'user_id' => $secondUser->id,
            'account_number' => 'INC-2002',
            'meter_number' => 'IM-2002',
        ]);
        $incident = $this->createIncident($firstConsumer);

        $this->actingAs($secondUser)
            ->post(route('incidents.confirm', $incident))
            ->assertForbidden();

        $this->actingAs($firstUser)
            ->post(route('incidents.confirm', $incident))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('success', 'Incident report marked as resolved.');

        $this->actingAs($firstUser)
            ->post(route('incidents.confirm', $incident->refresh()))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasErrors(['incident' => 'Only pending incident reports can be marked as resolved.']);
    }

    public function test_incident_category_and_search_filters_narrow_the_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createIncident($consumer, [
            'category' => 'Water Leak',
            'location' => 'Alpha Street',
            'description' => 'Leaking pipe near the gate.',
            'status' => 'Pending',
        ]);
        $this->createIncident($consumer, [
            'category' => 'Meter Concern',
            'location' => 'Beta Street',
            'description' => 'Meter dial is unclear.',
            'status' => 'Pending',
        ]);
        $this->createIncident($consumer, [
            'category' => 'Water Leak',
            'location' => 'Gamma Street',
            'description' => 'Resolved leak near the school.',
            'status' => 'Resolved',
            'resolved_at' => now(),
        ]);

        $category = $this->actingAs($admin)->get(route('incidents.index', ['category' => 'Water Leak']));
        $category->assertOk()
            ->assertSee('Alpha Street')
            ->assertSee('Gamma Street')
            ->assertDontSee('Beta Street')
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="incident_filter_category"', false);

        $search = $this->actingAs($admin)->get(route('incidents.index', ['search' => 'Beta Street']));
        $search->assertOk()
            ->assertSee('Beta Street')
            ->assertDontSee('Alpha Street')
            ->assertDontSee('Gamma Street');

        $combined = $this->actingAs($admin)->get(route('incidents.index', [
            'status' => 'Resolved',
            'category' => 'Water Leak',
        ]));
        $combined->assertOk()
            ->assertSee('Gamma Street')
            ->assertDontSee('Alpha Street')
            ->assertDontSee('Beta Street');
    }

    public function test_consumer_reports_only_renders_the_table_when_results_are_available(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $user->id]);
        $reports = route('incidents.index');

        $empty = $this->actingAs($user)->get($reports)->assertOk()
            ->assertSee('My Report Snapshot')
            ->assertSee('No incident reports yet')
            ->assertDontSee('class="table align-middle incident-table"', false);
        $this->assertSame(1, substr_count($empty->getContent(), 'No incident reports yet'));

        $this->createIncident($consumer);
        $this->get($reports)->assertOk()
            ->assertSee('class="table align-middle incident-table"', false)
            ->assertDontSee('No incident reports yet')
            ->assertSee('Showing 1 to 1 of 1 incident reports');

        $this->get(route('incidents.index', ['status' => 'Resolved']))->assertOk()
            ->assertSee('No incident reports match your filters.')
            ->assertDontSee('No incident reports yet')
            ->assertDontSee('class="table align-middle incident-table"', false);
    }

    private function createIncident(Consumer $consumer, array $overrides = []): Incident
    {
        return Incident::query()->create(array_merge([
            'consumer_id' => $consumer->id,
            'reported_by' => 'Incident Consumer',
            'category' => 'Water Leak',
            'location' => 'Purok 1',
            'description' => 'Water leaking near the meter box.',
            'status' => 'Pending',
            'reported_at' => now(),
        ], $overrides));
    }

    private function createConsumer(array $overrides = []): Consumer
    {
        return Consumer::query()->create(array_merge([
            'full_name' => 'Incident Consumer',
            'account_number' => 'INC-1001',
            'meter_number' => 'IM-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ], $overrides));
    }
}
