<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use App\Services\Dss\IncidentDssService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IncidentDssTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_ranks_hotspots_and_handles_missing_locations_without_double_counting(): void
    {
        $this->seedIncidentScenario();

        $dss = app(IncidentDssService::class)->build();

        $this->assertSame(8, $dss['summary']['total']);
        $this->assertSame(6, $dss['summary']['open']);
        $this->assertSame(6, $dss['summary']['pending']);
        $this->assertSame(2, $dss['summary']['resolved']);
        $this->assertSame(25.0, $dss['summary']['resolution_rate']);
        $this->assertSame(1.0, $dss['summary']['average_resolution_days']);
        $this->assertSame(0, $dss['summary']['invalid_resolution_timestamps']);
        $this->assertSame(40, $dss['summary']['oldest_open_days']);
        $this->assertSame(1, $dss['summary']['open_past_response_deadline']);
        $this->assertSame(2, $dss['summary']['missing_purok']);
        $this->assertSame(3, $dss['summary']['missing_block']);
        $this->assertSame(1, $dss['summary']['fully_unspecified']);
        $this->assertSame(0, $dss['summary']['future_reported_at']);
        $this->assertSame('Water Leak', $dss['summary']['top_category']);
        $this->assertSame(37.5, $dss['summary']['top_category_share']);
        $this->assertSame('Purok 2-B / Block not recorded', $dss['summary']['top_location']);
        $this->assertSame(1, $dss['summary']['unlocated_total']);

        $this->assertCount(7, $dss['hotspots']);
        $this->assertSame('Purok 2-B / Block not recorded', $dss['hotspots'][0]['label']);
        $this->assertSame('Location not specified', collect($dss['hotspots'])->firstWhere('label', 'Location not specified')['label']);
        $this->assertSame(6, array_sum($dss['charts']['area_totals']['counts']));
        $this->assertSame(6, $dss['summary']['open']);

        $locatedTotal = collect($dss['hotspots'])->reject(fn (array $hotspot): bool => $hotspot['label'] === 'Location not specified')->sum('total');
        $this->assertSame(
            $dss['summary']['total'],
            $locatedTotal + $dss['summary']['unlocated_total'],
            'Each incident must be counted once across purok/block groups.'
        );

        $partialGroup = collect($dss['hotspots'])->firstWhere('label', 'Purok not recorded / Block 7');
        $this->assertNotNull($partialGroup);
        $this->assertTrue($partialGroup['is_partial']);

        $urgentPriority = collect($dss['recommendations'])->firstWhere('badge', 'Urgent');
        $this->assertSame('Purok 2-B / Block not recorded', $urgentPriority['location']);
        $this->assertSame(1, $urgentPriority['unresolved_count']);
        $this->assertSame(40, $urgentPriority['oldest_report_age_days']);
        $this->assertStringContainsString('30-day response deadline', $urgentPriority['reason']);
        $this->assertTrue(collect($dss['recommendations'])->contains(fn (array $item): bool => $item['badge'] === 'High Priority'));
        $this->assertTrue(collect($dss['recommendations'])->contains(fn (array $item): bool => $item['badge'] === 'Monitor'));

        $this->assertTrue($dss['charts']['has_data']);
        $this->assertCount(7, $dss['charts']['hotspots']['labels']);
        $this->assertSame(6, array_sum($dss['charts']['area_totals']['counts']));
        $this->assertSame(6, array_sum($dss['charts']['area_priority']['open']));
        $this->assertSame(8, array_sum(array_map(
            fn (array $dataset): int => array_sum($dataset['data']),
            $dss['charts']['area_categories']['datasets'],
        )));
        $this->assertSame(8, array_sum($dss['charts']['trend']['reported']));
        $trendReported = collect($dss['trend']['labels'])->combine($dss['trend']['reported']);
        $trendResolved = collect($dss['trend']['labels'])->combine($dss['trend']['resolved']);
        $this->assertSame(1, $trendReported['Sep 2026']);
        $this->assertSame(7, $trendReported['Oct 2026']);
        $this->assertSame(2, $trendResolved['Oct 2026']);
        $this->assertSame('Increasing', $dss['trend']['direction']);
        $this->assertSame(6, $dss['trend']['latest_month_delta']);
        $this->assertSame(6, array_sum($dss['charts']['area_totals']['counts']));
        $this->assertSame('Location not specified', collect($dss['inspection_list'])->firstWhere('location', 'Location not specified')['location']);
    }

    public function test_unresolved_location_graph_sorts_counts_and_includes_unspecified_location(): void
    {
        foreach ([1, 1] as $block) {
            $this->incident([
                'purok' => 'Purok 1-A', 'block' => (string) $block,
                'status' => 'Pending', 'reported_at' => now()->subDays($block),
            ]);
        }
        $this->incident([
            'purok' => 'Purok 1-B', 'block' => '3',
            'status' => 'Ongoing', 'reported_at' => now()->subDay(),
        ]);
        $this->incident([
            'purok' => null, 'block' => null,
            'status' => 'Pending', 'reported_at' => now(),
        ]);

        $dss = app(IncidentDssService::class)->build();
        $counts = $dss['charts']['area_totals']['counts'];

        $this->assertSame([2, 1, 1], array_slice($counts, 0, 3));
        $this->assertSame('Purok 1-A / Block 1', $dss['charts']['area_totals']['labels'][0]);
        $this->assertContains('Location not specified', $dss['charts']['area_totals']['labels']);
        $this->assertSame($dss['summary']['open'], array_sum($counts));
    }

    public function test_dashboard_uses_all_records_and_ignores_legacy_filter_query_parameters(): void
    {
        $this->seedIncidentScenario();
        $this->incident([
            'purok' => 'Purok 1-A',
            'block' => '9',
            'category' => 'Legacy Category',
            'status' => 'Ongoing',
            'reported_at' => '2025-12-12 10:00:00',
        ]);

        $dss = app(IncidentDssService::class)->build();
        $this->assertSame(9, $dss['summary']['total']);
        $this->assertSame(7, $dss['summary']['open']);
        $this->assertSame(9, array_sum($dss['charts']['trend']['reported']));
        $this->assertContains('Legacy Category', collect($dss['categories'])->pluck('label')->all());
        $this->assertContains('Dec 2025', $dss['trend']['labels']);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dss.incidents', [
                'year' => 2026,
                'month' => 10,
                'category' => 'Meter Concern',
                'status' => 'Resolved',
                'purok' => 'Purok 1-C',
                'block' => '4',
            ]))
            ->assertOk()
            ->assertDontSee('Automatic filters')
            ->assertDontSee('dss_year', false)
            ->assertDontSee('Location hotspot details')
            ->assertDontSee('Status and category breakdown')
            ->assertDontSee('Data quality');
        $this->assertSame(9, $response->viewData('dss')['summary']['total']);
    }

    public function test_invalid_resolution_timestamps_are_flagged_and_excluded_from_average(): void
    {
        $this->incident([
            'purok' => 'Purok 1-A',
            'block' => '1',
            'status' => 'Resolved',
            'reported_at' => '2026-10-10 12:00:00',
            'resolved_at' => '2026-10-10 04:00:00',
        ]);

        $this->incident([
            'purok' => 'Purok 1-A',
            'block' => '2',
            'status' => 'Resolved',
            'reported_at' => '2026-10-11 12:00:00',
            'resolved_at' => '2026-10-12 12:00:00',
        ]);

        $dss = app(IncidentDssService::class)->build();

        $this->assertSame(2, $dss['summary']['resolved']);
        $this->assertSame(1, $dss['summary']['invalid_resolution_timestamps']);
        $this->assertSame(1.0, $dss['summary']['average_resolution_days']);
        $this->assertContains(
            'Average resolution time uses only valid timestamps; inconsistent timestamps are excluded.',
            $dss['data_notes'],
        );

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dss.incidents'))
            ->assertOk()
            ->assertDontSee('Average resolution time uses only valid timestamps; inconsistent timestamps are excluded.')
            ->assertDontSee('Location hotspot details')
            ->assertDontSee('Status and category breakdown')
            ->assertDontSee('Data quality');
    }

    public function test_future_reported_timestamps_are_flagged_and_excluded_from_open_age(): void
    {
        $this->incident([
            'purok' => 'Purok 1-A',
            'block' => '1',
            'status' => 'Pending',
            'reported_at' => '2026-10-23 10:00:00',
        ]);

        $dss = app(IncidentDssService::class)->build();

        $this->assertSame(1, $dss['summary']['total']);
        $this->assertSame(1, $dss['summary']['open']);
        $this->assertSame(1, $dss['summary']['future_reported_at']);
        $this->assertSame(0, $dss['summary']['oldest_open_days']);
        $this->assertSame(0, $dss['summary']['open_past_response_deadline']);
        $this->assertContains(
            'Future-dated incident reports are excluded from open-age calculations until their reported time is corrected.',
            $dss['data_notes'],
        );
    }

    public function test_page_renders_database_charts_without_removed_panels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedIncidentScenario();

        $response = $this->actingAs($admin)
            ->get(route('dss.incidents'))
            ->assertOk()
            ->assertSee('Incident DSS')
            ->assertSee('Unresolved Reports by Location')
            ->assertSee('Priority Inspection List')
            ->assertSee('Area response priority')
            ->assertSee('Incident categories by area')
            ->assertSee('incidentAreaPriorityChart', false)
            ->assertSee('severity is not recorded')
            ->assertSee('Urgent')
            ->assertSee('High Priority')
            ->assertSee('Monitor')
            ->assertSee('validated risk predictions')
            ->assertSee('Purok 1-C / Block 4')
            ->assertDontSee('Automatic filters')
            ->assertDontSee('Location hotspot details')
            ->assertDontSee('Status and category breakdown')
            ->assertDontSee('Data quality');
        $this->assertSame(8, $response->viewData('dss')['summary']['total']);
    }

    public function test_empty_dataset_returns_safe_values(): void
    {
        $dss = app(IncidentDssService::class)->build();

        $this->assertSame(0, $dss['summary']['total']);
        $this->assertNull($dss['summary']['average_resolution_days']);
        $this->assertNull($dss['summary']['top_category']);
        $this->assertSame([], $dss['hotspots']);
        $this->assertSame([], $dss['recommendations']);
        $this->assertFalse($dss['charts']['has_data']);
        $this->assertFalse($dss['trend']['has_data']);
        $this->assertSame([], $dss['charts']['area_totals']['labels']);
        $this->assertSame([], $dss['charts']['area_priority']['labels']);
        $this->assertSame([], $dss['charts']['area_categories']['datasets']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dss.incidents'))
            ->assertOk()
            ->assertSee('No incident data available.');
    }

    public function test_dashboard_aggregates_refresh_from_updated_incident_records(): void
    {
        $incident = $this->incident([
            'purok' => 'Purok 1-A',
            'block' => '3',
            'status' => 'Pending',
            'reported_at' => '2026-10-12 10:00:00',
        ]);
        $service = app(IncidentDssService::class);

        $before = $service->build();
        $this->assertSame(1, $before['summary']['total']);
        $this->assertSame(1, array_sum($before['charts']['area_priority']['open']));

        $incident->update(['status' => 'Resolved', 'resolved_at' => '2026-10-13 10:00:00']);

        $after = $service->build();
        $this->assertSame(1, $after['summary']['total']);
        $this->assertSame(0, $after['summary']['open']);
        $this->assertSame(1, $after['summary']['resolved']);
        $this->assertSame([], $after['charts']['area_priority']['labels']);
    }

    public function test_configured_location_priority_badges_explain_rules_and_clear_after_resolution(): void
    {
        config([
            'dss.incidents.response_deadline_days' => 5,
            'dss.incidents.high_priority.unresolved_backlog_count' => 10,
            'dss.incidents.high_priority.unresolved_share_percent' => 100,
            'dss.incidents.high_priority.recurrence_count' => 3,
            'dss.incidents.high_priority.recurrence_window_days' => 30,
        ]);

        $overdue = $this->incident([
            'purok' => 'Purok 1-A', 'block' => '1', 'status' => 'Pending',
            'reported_at' => now()->subDays(6),
        ]);
        $this->incident([
            'purok' => 'Purok 1-A', 'block' => '1', 'status' => 'Resolved',
            'reported_at' => now()->subDays(3), 'resolved_at' => now()->subDays(2),
        ]);

        foreach ([1, 2, 3] as $daysAgo) {
            $this->incident([
                'purok' => 'Purok 1-B', 'block' => '2', 'status' => 'Resolved',
                'reported_at' => now()->subDays($daysAgo), 'resolved_at' => now()->subDays(max(0, $daysAgo - 1)),
            ]);
        }

        $this->incident([
            'purok' => 'Purok 1-C', 'block' => '3', 'status' => 'Pending',
            'reported_at' => now()->subDay(),
        ]);
        $this->incident([
            'purok' => 'Purok 1-C', 'block' => '3', 'status' => 'Resolved',
            'reported_at' => now()->subDays(2), 'resolved_at' => now()->subDay(),
        ]);

        $recommendations = collect(app(IncidentDssService::class)->build()['recommendations'])->keyBy('location');
        $urgent = $recommendations['Purok 1-A / Block 1'];
        $this->assertSame('Urgent', $urgent['badge']);
        $this->assertSame(1, $urgent['unresolved_count']);
        $this->assertSame(6, $urgent['oldest_report_age_days']);
        $this->assertStringContainsString('5-day response deadline', $urgent['reason']);
        $this->assertNotEmpty($urgent['action']);
        $this->assertSame('High Priority', $recommendations['Purok 1-B / Block 2']['badge']);
        $this->assertStringContainsString('3 reports', $recommendations['Purok 1-B / Block 2']['reason']);
        $this->assertSame('Monitor', $recommendations['Purok 1-C / Block 3']['badge']);

        $overdue->update(['status' => 'Resolved', 'resolved_at' => now()]);
        $afterResolution = collect(app(IncidentDssService::class)->build()['recommendations'])->keyBy('location');
        $this->assertSame('Monitor', $afterResolution['Purok 1-A / Block 1']['badge']);
    }

    private function seedIncidentScenario(): void
    {
        $this->incident([
            'purok' => 'Purok 1-C',
            'block' => '4',
            'category' => 'Water Leak',
            'status' => 'Pending',
            'reported_at' => '2026-10-12 10:00:00',
        ]);

        $this->incident([
            'purok' => 'Purok 1-C',
            'block' => '4',
            'category' => 'Water Leak',
            'status' => 'Resolved',
            'reported_at' => '2026-10-02 10:00:00',
            'resolved_at' => '2026-10-03 10:00:00',
        ]);

        $this->incident([
            'purok' => 'Purok 1-C',
            'block' => '5',
            'category' => 'Water Quality Issue',
            'status' => 'Pending',
            'reported_at' => '2026-10-17 10:00:00',
        ]);

        $this->incident([
            'purok' => 'Purok 1-A',
            'block' => '2',
            'category' => 'Meter Concern',
            'status' => 'Resolved',
            'reported_at' => '2026-10-07 10:00:00',
            'resolved_at' => '2026-10-08 10:00:00',
        ]);

        $this->incident([
            'purok' => null,
            'block' => null,
            'category' => 'No Water Supply',
            'status' => 'Pending',
            'reported_at' => '2026-10-21 10:00:00',
        ]);

        $this->incident([
            'purok' => null,
            'block' => '7',
            'category' => 'Water Leak',
            'status' => 'Pending',
            'reported_at' => '2026-10-20 10:00:00',
        ]);

        $this->incident([
            'purok' => 'Purok 2-A',
            'block' => null,
            'category' => 'No Water Supply',
            'status' => 'Pending',
            'reported_at' => '2026-10-19 10:00:00',
        ]);

        $this->incident([
            'purok' => 'Purok 2-B',
            'block' => null,
            'category' => 'Meter Concern',
            'status' => 'Pending',
            'reported_at' => '2026-09-12 10:00:00',
        ]);
    }

    private function incident(array $overrides = []): Incident
    {
        $location = array_key_exists('purok', $overrides)
            ? ($overrides['purok'] ?? 'Via Vida')
            : 'Purok 1-C';

        return Incident::query()->create(array_merge([
            'reported_by' => 'DSS Reporter',
            'category' => 'Water Leak',
            'location' => $location,
            'purok' => 'Purok 1-C',
            'block' => '4',
            'description' => 'DSS test incident',
            'status' => 'Pending',
            'reported_at' => '2026-10-10 10:00:00',
        ], $overrides));
    }
}
