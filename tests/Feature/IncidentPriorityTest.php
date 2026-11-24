<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IncidentPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_descriptions_are_classified_and_displayed_for_all_five_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create([
            'user_id' => $reporter->id, 'full_name' => 'Incident Reporter', 'account_number' => 'INC-1',
            'meter_number' => 'INC-M-1', 'status' => 'Active', 'membership_type' => 'Member',
            'service_purok' => 'Purok 1-C',
        ]);

        $reports = [
            ['Water Leak', 'Nagtulo ra ang gripo.', 'Non-Urgent'],
            ['Water Leak', 'Pumutok ang pangunahing tubo.', 'Urgent'],
            ['No Water Supply', 'Walang tubig sa tatlong bahay.', 'Urgent'],
            ['No Water Supply', 'Walay tubig. Palihog tan-awa.', 'Needs Review'],
            ['Water Quality Issue', 'Ang tubig is hugaw kaayo.', 'Urgent'],
            ['Water Quality Issue', 'Hindi kontaminado ang tubig, medyo malabo lang.', 'Non-Urgent'],
            ['Meter Concern', 'Sayop ang basa sa among metro.', 'Non-Urgent'],
            ['Meter Concern', 'May nakalantad na kableng may kuryente malapit sa metro.', 'Urgent'],
            ['Others', 'Pakipuntahan ang kanto.', 'Urgent', 'Nibuto ang main pipe'],
            ['Others', 'Pakitingnan po.', 'Needs Review', 'Mahinang agas'],
        ];

        foreach ($reports as $report) {
            [$category, $description, $priority] = $report;
            $this->actingAs($reporter)->post(route('incidents.store'), [
                'category' => $category,
                'description' => $description,
                'custom_incident_type' => $report[3] ?? null,
                'location' => 'Via Vida',
            ])->assertRedirect(route('incidents.index'))->assertSessionHasNoErrors();

            $incident = $consumer->incidents()->where('description', $description)->sole();
            $this->assertSame($priority, $incident->automatic_priority, $description);
            $this->assertNotEmpty($incident->classification_reason);
            $this->actingAs($admin)->get(route('incidents.index', [
                'category' => $category, 'search' => $description, 'priority' => $priority,
            ]))->assertOk()
                ->assertViewHas('incidents', fn ($incidents) => $incidents->modelKeys() === [$incident->id])
                ->assertSee('class="badge incident-priority-'.Str::slug($priority).'"', false)
                ->assertSee($incident->classification_reason);
        }
    }

    public function test_consumer_report_persists_automatic_evidence_and_admin_can_confirm_or_override_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create([
            'user_id' => $reporter->id, 'full_name' => 'Incident Reporter', 'account_number' => 'INC-1',
            'meter_number' => 'INC-M-1', 'status' => 'Active', 'membership_type' => 'Member',
            'service_purok' => 'Purok 1-C',
        ]);
        $description = 'No major pipe burst. A significant leak is flooding the small garden.';
        $this->actingAs($reporter)->post(route('incidents.store'), [
            'category' => 'Others', 'custom_incident_type' => 'Water Leak', 'location' => 'Via Vida garden',
            'description' => $description, 'priority' => 'Non-Urgent',
        ])->assertSessionHasNoErrors();

        $incident = $consumer->incidents()->sole();
        $this->assertSame('Urgent', $incident->priority);
        $this->assertSame('Urgent', $incident->automatic_priority);
        $this->assertStringContainsString('significant leak', $incident->classification_reason);
        $review = route('incidents.priority.update', $incident);
        $this->patch($review, ['priority' => 'Non-Urgent', 'priority_review_note' => 'The leak is a minor drip.'])->assertForbidden();

        $list = route('incidents.index', ['priority' => 'Urgent']);
        $this->actingAs($admin)->get($list)->assertOk()->assertSee($review, false)->assertSee($incident->classification_reason);
        $this->from($list)->patch($review, ['priority' => 'Urgent'])->assertRedirect($list)->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $incident->fresh()->priority_reviewed_by);
        $this->assertNotNull($incident->fresh()->priority_reviewed_at);
        foreach (['Critical', 'Normal'] as $oldPriority) {
            $this->patch($review, ['priority' => $oldPriority, 'priority_review_note' => 'Legacy value.'])->assertSessionHasErrors('priority');
        }
        $this->patch($review, ['priority' => 'Non-Urgent'])->assertSessionHasErrors('priority_review_note');
        $this->patch($review, ['priority' => 'Non-Urgent', 'priority_review_note' => 'Field staff confirmed a minor drip.'])->assertSessionHasNoErrors();
        $incident->refresh();
        $this->assertSame('Non-Urgent', $incident->priority);
        $this->assertSame('Urgent', $incident->automatic_priority);
        $this->assertSame('Field staff confirmed a minor drip.', $incident->priority_review_note);
        $this->assertSame($description, $incident->description);
        $this->assertSame(Incident::STATUS_PENDING, $incident->status);
        $this->patch($review, ['priority' => 'Urgent'])->assertSessionHasErrors('priority_review_note');
    }

    #[DataProvider('resolutionPaths')]
    public function test_priority_history_cannot_be_changed_after_resolution(bool $resolvedByConsumer): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Priority Reviewer']);
        $reporter = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create([
            'user_id' => $reporter->id, 'full_name' => 'Incident Reporter', 'account_number' => 'INC-1',
            'meter_number' => 'INC-M-1', 'status' => 'Active', 'membership_type' => 'Member',
        ]);
        $incident = $consumer->incidents()->create([
            'reported_by' => $consumer->full_name, 'category' => 'Water Leak', 'location' => 'Via Vida',
            'description' => 'A significant leak.', 'reported_at' => now(),
            'priority' => 'Urgent', 'automatic_priority' => 'Urgent',
            'status' => $resolvedByConsumer ? Incident::STATUS_PENDING : 'Ongoing',
        ]);
        $review = route('incidents.priority.update', $incident);
        $reviewNote = 'Field staff confirmed a minor drip.';
        $this->actingAs($admin)->patch($review, ['priority' => 'Non-Urgent', 'priority_review_note' => $reviewNote])
            ->assertSessionHas('success', 'Incident priority reviewed.');

        if ($resolvedByConsumer) {
            $this->actingAs($reporter)->postJson(route('incidents.confirm', $incident))->assertOk();
        } else {
            $this->patch(route('incidents.update', $incident), ['status' => 'Resolved', 'resolution_notes' => 'Leak repaired.'])
                ->assertSessionHas('success', 'Incident report updated successfully.');
        }

        $incident->refresh();
        $this->assertTrue($incident->isResolved());
        $this->assertSame('Non-Urgent', $incident->priority);
        $this->assertSame($resolvedByConsumer, $incident->confirmed_at !== null);
        $this->assertNotNull($incident->resolved_at);
        $this->actingAs($admin)->get(route('incidents.index', ['status' => 'Resolved']))
            ->assertOk()
            ->assertSee('data-incident-status="Resolved"', false)
            ->assertSee('data-priority="Non-Urgent"', false)
            ->assertSee('data-priority-review-note="'.$reviewNote.'"', false)
            ->assertSee('data-priority-reviewer="'.$admin->name.'"', false)
            ->assertSee('data-priority-reviewed-at="'.$incident->priority_reviewed_at->format('M d, Y h:i A').'"', false)
            ->assertSee(Incident::PRIORITY_REVIEW_LOCKED_MESSAGE);

        $stored = $incident->getRawOriginal();
        $this->travel(1)->minute();
        $this->patch($review, ['priority' => 'Urgent', 'priority_review_note' => 'Attempted override.'])
            ->assertConflict();
        $this->patchJson($review, ['priority' => 'Non-Urgent', 'priority_review_note' => 'Attempted audit change.'])
            ->assertConflict()
            ->assertJsonPath('message', Incident::PRIORITY_REVIEW_LOCKED_MESSAGE);
        $this->assertSame($stored, $incident->fresh()->getRawOriginal());
    }

    public static function resolutionPaths(): array
    {
        return ['admin resolution' => [false], 'consumer resolution' => [true]];
    }

    public function test_priority_order_and_combined_filters_apply_before_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $report = [
            'reported_by' => 'Sample reporter', 'category' => 'Water Leak', 'location' => 'Via Vida',
            'description' => 'Sample water report.', 'status' => Incident::STATUS_PENDING,
        ];
        $nonUrgentIds = collect(range(1, 10))->map(fn ($index) => Incident::create(
            $report + ['priority' => 'Non-Urgent', 'reported_at' => now()->subDays(10)->addMinutes($index)]
        )->id)->all();
        $legacy = Incident::create($report + ['reported_at' => now()->subDays(3)]);
        $olderUrgent = Incident::create($report + ['priority' => 'Urgent', 'reported_at' => now()->subDay()]);
        $newerUrgent = Incident::create($report + ['priority' => 'Urgent', 'reported_at' => now()]);
        $filters = ['category' => 'Water Leak', 'status' => 'Pending', 'search' => 'Via Vida'];
        $incidents = $this->get(route('incidents.index', $filters))->assertOk()->viewData('incidents');
        $this->assertSame([$olderUrgent->id, $newerUrgent->id, $legacy->id], $incidents->getCollection()->take(3)->modelKeys());
        $this->assertSame(13, $incidents->total());
        $this->assertSame(3, $this->get(route('incidents.index', $filters + ['page' => 2]))->assertOk()->viewData('incidents')->count());
        foreach ([
            'Urgent' => [$olderUrgent->id, $newerUrgent->id],
            'Non-Urgent' => $nonUrgentIds,
            'Needs Review' => [$legacy->id],
            'Critical' => [$olderUrgent->id, $newerUrgent->id],
            'Normal' => $nonUrgentIds,
        ] as $priority => $ids) {
            $this->assertSame($ids, $this->get(route('incidents.index', $filters + ['priority' => $priority]))->assertOk()
                ->viewData('incidents')->getCollection()->modelKeys());
        }

        $response = $this->get(route('incidents.index', $filters))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $labels = array_map(fn ($option) => trim($option->textContent), iterator_to_array($xpath->query('//select[@id="incident_filter_priority"]/option')));
        $this->assertSame(['All Priorities', 'Urgent', 'Non-Urgent', 'Needs Review'], $labels);
        $reviewLabels = array_map(fn ($option) => trim($option->textContent), iterator_to_array($xpath->query('//select[@id="incident_review_priority"]/option')));
        $this->assertSame(Incident::PRIORITIES, $reviewLabels);
        $this->assertSame('Needs Review', $legacy->fresh()->priority);
        $this->assertStringContainsString('No automatic classification', $legacy->fresh()->classification_reason);
    }

    public function test_priority_migration_updates_both_values_and_preserves_other_incident_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mapping = ['Critical' => 'Urgent', 'Normal' => 'Non-Urgent', 'Urgent' => 'Urgent', 'Needs Review' => 'Needs Review'];
        $original = [];
        foreach (array_keys($mapping) as $index => $priority) {
            $incident = Incident::create([
                'reported_by' => 'Migration test', 'category' => 'Water Leak', 'location' => 'Via Vida',
                'description' => 'Stored incident description.', 'reported_at' => now()->subDay(),
                'status' => $index % 2 === 0 ? Incident::STATUS_RESOLVED : Incident::STATUS_PENDING,
                'priority' => $priority, 'automatic_priority' => array_keys($mapping)[3 - $index],
                'classification_reason' => 'Saved classification reason.',
            ]);
            $incident->forceFill([
                'priority_review_note' => 'Saved review note.', 'priority_reviewed_at' => now(), 'priority_reviewed_by' => $admin->id,
            ])->save();
            $original[$incident->id] = $incident->refresh()->getRawOriginal();
        }

        $migration = require database_path('migrations/2026_11_24_000001_update_incident_priority_classifications.php');
        $migration->up();

        $this->assertDatabaseCount('incidents', count($original));
        foreach ($original as $id => $record) {
            $expected = array_replace($record, [
                'priority' => $mapping[$record['priority']],
                'automatic_priority' => $mapping[$record['automatic_priority']],
            ]);
            $this->assertSame($expected, Incident::findOrFail($id)->getRawOriginal());
        }
    }
}
