<?php

namespace App\Services\Dss;

use App\Models\Incident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IncidentDssService
{
    public const UNSPECIFIED = '__unspecified__';

    /** @var list<string> */
    private array $puroks;

    public function __construct()
    {
        $this->puroks = collect(config('service_area.puroks', []))
            ->filter(fn (mixed $purok): bool => filled($purok))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $allIncidents = Incident::query()
            ->with('consumer')
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get();

        $allRows = $allIncidents->map(fn (Incident $incident): array => [
            'incident' => $incident,
            ...$this->resolveLocation($incident),
        ]);

        $incidents = $allIncidents;
        $rows = $allRows;
        $priorityRules = $this->priorityRules();
        $total = $incidents->count();
        $pending = $incidents->where('status', Incident::STATUS_PENDING)->count();
        $open = $incidents->filter(fn (Incident $incident): bool => in_array($incident->status, Incident::OPEN_STATUSES, true))->count();
        $resolved = $incidents->where('status', Incident::STATUS_RESOLVED)->count();

        $resolutionDurations = $incidents
            ->where('status', Incident::STATUS_RESOLVED)
            ->map(fn (Incident $incident): ?float => $this->resolutionDays($incident))
            ->filter(fn (?float $days): bool => $days !== null);

        $invalidResolutionTimestamps = $incidents
            ->where('status', Incident::STATUS_RESOLVED)
            ->filter(fn (Incident $incident): bool => $this->hasInvalidResolutionTimestamp($incident))
            ->count();

        $missingPurok = $rows->filter(fn (array $row): bool => $row['purok'] === null)->count();
        $missingBlock = $rows->filter(fn (array $row): bool => $row['block'] === null)->count();
        $fullyUnspecified = $rows
            ->filter(fn (array $row): bool => $row['purok'] === null && $row['block'] === null)
            ->count();
        $futureReported = $incidents
            ->filter(fn (Incident $incident): bool => $incident->reported_at !== null && $incident->reported_at->gt(now()))
            ->count();

        $openAgedRows = $incidents
            ->filter(fn (Incident $incident): bool => in_array($incident->status, Incident::OPEN_STATUSES, true)
                && $incident->reported_at !== null
                && $incident->reported_at->lte(now()));

        $oldestOpenDays = $openAgedRows
            ->map(fn (Incident $incident): int => (int) max(0, $incident->reported_at->copy()->startOfDay()->diffInDays(today(), false)))
            ->max() ?? 0;

        $openPastResponseDeadline = $openAgedRows
            ->filter(fn (Incident $incident): bool => $incident->reported_at->lt(today()->subDays($priorityRules['response_deadline_days'])))
            ->count();

        $categories = $this->breakdown($incidents->pluck('category'), $total);
        $statuses = $this->breakdown($incidents->pluck('status'), $total);
        $hotspots = $this->hotspots($rows, $total);
        $unlocated = $rows
            ->filter(fn (array $row): bool => $row['purok'] === null && $row['block'] === null)
            ->values();
        $topCategoryCounts = $incidents->pluck('category')->filter(fn (mixed $category): bool => filled($category))->countBy();
        $topCategoryLabel = $topCategoryCounts->sortDesc()->keys()->first();
        $topCategory = collect($categories)->firstWhere('label', $topCategoryLabel);
        $topHotspot = $hotspots[0] ?? null;

        $summary = [
            'total' => $total,
            'pending' => $pending,
            'open' => $open,
            'resolved' => $resolved,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0.0,
            'open_rate' => $total > 0 ? round(($open / $total) * 100, 1) : 0.0,
            'average_resolution_days' => $resolutionDurations->isEmpty()
                ? null
                : round((float) $resolutionDurations->avg(), 1),
            'invalid_resolution_timestamps' => $invalidResolutionTimestamps,
            'oldest_open_days' => $oldestOpenDays,
            'open_past_response_deadline' => $openPastResponseDeadline,
            'missing_purok' => $missingPurok,
            'missing_block' => $missingBlock,
            'fully_unspecified' => $fullyUnspecified,
            'future_reported_at' => $futureReported,
            'top_category' => $topCategory['label'] ?? null,
            'top_category_share' => $topCategory['share'] ?? 0.0,
            'top_location' => $topHotspot['label'] ?? null,
            'unlocated_total' => $unlocated->count(),
        ];

        $trend = $this->trend($rows);
        $priorityHotspots = collect($hotspots)
            ->filter(fn (array $hotspot): bool => $hotspot['open'] > 0)
            ->sort(fn (array $left, array $right): int => $right['open'] <=> $left['open']
                ?: $right['total'] <=> $left['total']
                ?: strcmp($left['label'], $right['label']))
            ->values();
        $areaCategoryDatasets = collect($categories)
            ->map(fn (array $category): array => [
                'label' => $category['label'],
                'data' => collect($hotspots)
                    ->map(fn (array $hotspot): int => (int) ($hotspot['category_totals'][$category['label']] ?? 0))
                    ->all(),
            ])
            ->all();

        return [
            'summary' => $summary,
            'categories' => $categories,
            'statuses' => $statuses,
            'hotspots' => $hotspots,
            'unlocated' => $unlocated,
            'trend' => $trend,
            'priority_rules' => $priorityRules,
            'recommendations' => $this->recommendations($rows, $priorityRules),
            'inspection_list' => collect($this->recommendations($rows, $priorityRules))
                ->filter(fn (array $item): bool => $item['unresolved_count'] > 0)
                ->values()->all(),
            'data_notes' => $this->dataNotes($summary),
            'charts' => [
                'has_data' => $total > 0,
                'area_totals' => [
                    'labels' => collect($hotspots)->pluck('label')->all(),
                    'counts' => collect($hotspots)->pluck('open')->all(),
                ],
                'area_priority' => [
                    'labels' => $priorityHotspots->pluck('label')->all(),
                    'open' => $priorityHotspots->pluck('open')->all(),
                ],
                'area_categories' => [
                    'labels' => collect($hotspots)->pluck('label')->all(),
                    'datasets' => $areaCategoryDatasets,
                ],
                'hotspots' => [
                    'labels' => collect($hotspots)->pluck('label')->all(),
                    'open' => collect($hotspots)->pluck('open')->all(),
                    'resolved' => collect($hotspots)->pluck('resolved')->all(),
                ],
                'categories' => [
                    'labels' => collect($categories)->pluck('label')->all(),
                    'counts' => collect($categories)->pluck('total')->all(),
                ],
                'statuses' => [
                    'labels' => collect($statuses)->pluck('label')->all(),
                    'counts' => collect($statuses)->pluck('total')->all(),
                ],
                'trend' => $trend,
            ],
        ];
    }

    /**
     * @return array{purok: ?string, block: ?string}
     */
    public function resolveLocation(Incident $incident): array
    {
        $purok = $this->normalizePurok($incident->purok)
            ?? $this->purokFromLocation($incident->location)
            ?? $this->normalizePurok($incident->consumer?->service_purok);

        $block = $this->normalizeBlock($incident->block)
            ?? $this->blockFromLocation($incident->location)
            ?? $this->normalizeBlock($incident->consumer?->address_block);

        return [
            'purok' => $purok,
            'block' => $block,
        ];
    }

    /** @return list<array{label: string, total: int, share: float}> */
    private function breakdown(Collection $values, int $total): array
    {
        $counts = $values
            ->filter(fn (mixed $value): bool => filled($value))
            ->countBy();

        return $counts
            ->sortKeys()
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'total' => $count,
                'share' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{incident: Incident, purok: ?string, block: ?string}>  $rows
     * @return list<array<string, mixed>>
     */
    private function hotspots(Collection $rows, int $total): array
    {
        $groups = $rows->groupBy(fn (array $row): string => ($row['purok'] ?? self::UNSPECIFIED).'|'.($row['block'] ?? self::UNSPECIFIED));

        $hotspots = [];

        foreach ($groups as $group) {
            $first = $group->first();
            $isUnspecified = $first['purok'] === null && $first['block'] === null;

            $incidents = $group->pluck('incident');
            $openIncidents = $incidents->filter(fn (Incident $incident): bool => in_array($incident->status, Incident::OPEN_STATUSES, true));
            $datedOpenIncidents = $openIncidents->filter(fn (Incident $incident): bool => $incident->reported_at !== null && $incident->reported_at->lte(now()));
            $oldestOpen = $datedOpenIncidents->sortBy(fn (Incident $incident): int => $incident->reported_at->timestamp)->first();
            $openCategories = $openIncidents->pluck('category')->filter(fn (mixed $category): bool => filled($category))->countBy()->sortDesc();
            $topCategory = $incidents
                ->pluck('category')
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first();
            $categoryTotals = $incidents
                ->pluck('category')
                ->filter(fn (mixed $category): bool => filled($category))
                ->countBy()
                ->all();

            $hotspots[] = [
                'purok' => $first['purok'],
                'block' => $first['block'],
                'label' => $isUnspecified ? 'Location not specified' : $this->hotspotLabel($first['purok'], $first['block']),
                'total' => $incidents->count(),
                'open' => $openIncidents->count(),
                'resolved' => $incidents->where('status', Incident::STATUS_RESOLVED)->count(),
                'share' => $total > 0 ? round(($incidents->count() / $total) * 100, 1) : 0.0,
                'top_category' => $openCategories->keys()->first() ?? $topCategory,
                'category_totals' => $categoryTotals,
                'is_partial' => $first['purok'] === null || $first['block'] === null,
                'oldest_open_at' => $oldestOpen?->reported_at,
                'oldest_open_days' => $oldestOpen ? (int) max(0, $oldestOpen->reported_at->copy()->startOfDay()->diffInDays(today(), false)) : null,
                'unresolved_issue' => $openCategories->keys()->first(),
            ];
        }

        usort($hotspots, function (array $left, array $right): int {
            return $right['open'] <=> $left['open']
                ?: ($right['oldest_open_days'] ?? -1) <=> ($left['oldest_open_days'] ?? -1)
                ?: strcmp($left['label'], $right['label']);
        });

        return $hotspots;
    }

    /**
     * @param  Collection<int, array{incident: Incident, purok: ?string, block: ?string}>  $rows
     * @return array<string, mixed>
     */
    private function trend(Collection $rows): array
    {
        $monthGroups = $rows
            ->pluck('incident')
            ->filter(fn (Incident $incident): bool => $incident->reported_at !== null && $incident->reported_at->lte(now()))
            ->groupBy(fn (Incident $incident): string => $incident->reported_at->format('Y-m'));
        $monthKeys = $monthGroups->keys()->sort()->values();
        $months = collect();
        if ($monthKeys->isNotEmpty()) {
            $cursor = Carbon::createFromFormat('!Y-m', $monthKeys->first());
            $last = Carbon::createFromFormat('!Y-m', $monthKeys->last());
            while ($cursor->lte($last)) {
                $months->push($cursor->format('Y-m'));
                $cursor->addMonth();
            }
        }
        $reported = $months->map(fn (string $month): int => $monthGroups->get($month, collect())->count())->all();
        $resolved = $months->map(fn (string $month): int => $monthGroups->get($month, collect())
            ->where('status', Incident::STATUS_RESOLVED)->count())->all();
        $lastTwo = array_slice($reported, -2);
        $delta = count($lastTwo) === 2 ? $lastTwo[1] - $lastTwo[0] : null;

        return [
            'labels' => $months->map(fn (string $month): string => Carbon::createFromFormat('!Y-m', $month)->format('M Y'))->all(),
            'reported' => $reported,
            'resolved' => $resolved,
            'has_data' => array_sum($reported) > 0,
            'direction' => $delta === null ? null : ($delta > 0 ? 'Increasing' : ($delta < 0 ? 'Decreasing' : 'No change')),
            'latest_month_delta' => $delta,
        ];
    }

    /** @return array{response_deadline_days: int, high_priority: array{unresolved_backlog_count: int, unresolved_share_percent: float, recurrence_count: int, recurrence_window_days: int}} */
    private function priorityRules(): array
    {
        return [
            'response_deadline_days' => max(1, (int) config('dss.incidents.response_deadline_days', 30)),
            'high_priority' => [
                'unresolved_backlog_count' => max(1, (int) config('dss.incidents.high_priority.unresolved_backlog_count', 5)),
                'unresolved_share_percent' => min(100, max(1, (float) config('dss.incidents.high_priority.unresolved_share_percent', 50))),
                'recurrence_count' => max(2, (int) config('dss.incidents.high_priority.recurrence_count', 3)),
                'recurrence_window_days' => max(1, (int) config('dss.incidents.high_priority.recurrence_window_days', 90)),
            ],
        ];
    }

    /**
     * @param  Collection<int, array{incident: Incident, purok: ?string, block: ?string}>  $rows
     * @param  array{response_deadline_days: int, high_priority: array{unresolved_backlog_count: int, unresolved_share_percent: float, recurrence_count: int, recurrence_window_days: int}}  $rules
     * @return list<array{badge: string, badge_class: string, location: string, reason: string, unresolved_count: int, oldest_report_age_days: ?int, action: string}>
     */
    private function recommendations(Collection $rows, array $rules): array
    {
        $recommendations = [];
        $deadline = $rules['response_deadline_days'];
        $highRules = $rules['high_priority'];
        $recurrenceCutoff = now()->subDays($highRules['recurrence_window_days']);

        foreach ($rows->groupBy(fn (array $row): string => ($row['purok'] ?? self::UNSPECIFIED).'|'.($row['block'] ?? self::UNSPECIFIED)) as $group) {
            $first = $group->first();
            $location = $first['purok'] === null && $first['block'] === null
                ? 'Location not specified'
                : $this->hotspotLabel($first['purok'], $first['block']);
            $incidents = $group->pluck('incident');
            $openIncidents = $incidents->filter(fn (Incident $incident): bool => in_array($incident->status, Incident::OPEN_STATUSES, true));
            $datedOpenIncidents = $openIncidents
                ->filter(fn (Incident $incident): bool => $incident->reported_at !== null && $incident->reported_at->lte(now()));
            $oldestOpen = $datedOpenIncidents->sortBy(fn (Incident $incident): int => $incident->reported_at->timestamp)->first();
            $oldestAge = $oldestOpen
                ? (int) max(0, $oldestOpen->reported_at->copy()->startOfDay()->diffInDays(today(), false))
                : null;
            $overdueCount = $datedOpenIncidents
                ->filter(fn (Incident $incident): bool => $incident->reported_at->lt(today()->subDays($deadline)))
                ->count();
            $recurrenceCount = $incidents
                ->filter(fn (Incident $incident): bool => $incident->reported_at !== null
                    && $incident->reported_at->betweenIncluded($recurrenceCutoff, now()))
                ->count();
            $commonIssueCounts = $openIncidents->pluck('category')
                ->filter(fn (mixed $category): bool => filled($category))->countBy()->sortDesc();
            $commonIssue = $commonIssueCounts->keys()->first();
            $unresolvedCount = $openIncidents->count();
            $unresolvedShare = $incidents->isNotEmpty() ? ($unresolvedCount / $incidents->count()) * 100 : 0;
            $backlogExceeded = $unresolvedCount >= $highRules['unresolved_backlog_count']
                || $unresolvedShare >= $highRules['unresolved_share_percent'];
            $recurrenceExceeded = $recurrenceCount >= $highRules['recurrence_count'];

            if ($overdueCount > 0) {
                $badge = 'Urgent';
                $badgeClass = 'danger';
                $reason = sprintf(
                    '%d unresolved report(s) exceed the configured %d-day response deadline.',
                    $overdueCount,
                    $deadline,
                );
                $action = sprintf(
                    'Follow up the %d overdue case(s); the oldest was reported %s.',
                    $overdueCount,
                    $oldestOpen?->reported_at?->format('M j, Y') ?? 'on an unavailable date',
                );
            } elseif ($backlogExceeded || $recurrenceExceeded) {
                $badge = 'High Priority';
                $badgeClass = 'warning';
                $reasons = [];

                if ($unresolvedCount >= $highRules['unresolved_backlog_count']) {
                    $reasons[] = sprintf(
                        'unresolved backlog is %d reports (configured threshold: at least %d)',
                        $unresolvedCount,
                        $highRules['unresolved_backlog_count'],
                    );
                }

                if ($unresolvedShare >= $highRules['unresolved_share_percent']) {
                    $reasons[] = sprintf(
                        '%.1f%% of this location\'s reports remain unresolved (configured threshold: at least %.0f%%)',
                        $unresolvedShare,
                        $highRules['unresolved_share_percent'],
                    );
                }

                if ($recurrenceExceeded) {
                    $reasons[] = sprintf(
                        '%d reports were recorded within the last %d days (threshold: %d)',
                        $recurrenceCount,
                        $highRules['recurrence_window_days'],
                        $highRules['recurrence_count'],
                    );
                }

                $reason = ucfirst(implode('; ', $reasons)).'.';
                $action = $unresolvedCount > 0
                    ? sprintf(
                        'Follow up %d unresolved case(s)%s.',
                        $unresolvedCount,
                        $commonIssue ? sprintf(', prioritizing %s (%d report(s))', $commonIssue, (int) $commonIssueCounts->first()) : '',
                    )
                    : 'Review the repeated reports for a shared cause and plan a location inspection.';
            } else {
                $badge = 'Monitor';
                $badgeClass = 'secondary';
                $reason = 'No configured response-deadline, recurring-report, or unresolved-backlog threshold is met.';
                $action = 'Continue monitoring and update the incident records as reports are addressed.';
            }

            $recommendations[] = [
                'badge' => $badge,
                'badge_class' => $badgeClass,
                'location' => $location,
                'reason' => $reason,
                'unresolved_count' => $unresolvedCount,
                'oldest_report_age_days' => $oldestAge,
                'oldest_report_date' => $oldestOpen?->reported_at?->format('M j, Y'),
                'most_common_issue' => $commonIssue,
                'most_common_issue_count' => $commonIssue ? (int) $commonIssueCounts->first() : 0,
                'overdue_count' => $overdueCount,
                'recent_report_count' => $recurrenceCount,
                'recurrence_start_date' => $recurrenceCutoff->format('M j, Y'),
                'recurrence_end_date' => now()->format('M j, Y'),
                'action' => $action,
            ];
        }

        $priorityOrder = ['Urgent' => 0, 'High Priority' => 1, 'Monitor' => 2];

        usort($recommendations, fn (array $left, array $right): int => $priorityOrder[$left['badge']] <=> $priorityOrder[$right['badge']]
            ?: $right['unresolved_count'] <=> $left['unresolved_count']
            ?: strcmp($left['location'], $right['location']));

        return $recommendations;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    private function dataNotes(array $summary): array
    {
        $notes = [];

        if ($summary['total'] > 0 && $summary['total'] < 5) {
            $notes[] = 'Small sample: fewer than five incident records are available. Interpret rates and rankings with caution.';
        }

        if ($summary['invalid_resolution_timestamps'] > 0) {
            $notes[] = 'Average resolution time uses only valid timestamps; inconsistent timestamps are excluded.';
        }

        if ($summary['future_reported_at'] > 0) {
            $notes[] = 'Future-dated incident reports are excluded from open-age calculations until their reported time is corrected.';
        }

        return $notes;
    }

    private function resolutionDays(Incident $incident): ?float
    {
        $finishedAt = $incident->resolved_at ?? $incident->confirmed_at;

        if ($incident->reported_at === null || $finishedAt === null) {
            return null;
        }

        $hours = $incident->reported_at->diffInHours($finishedAt, false);

        return $hours < 0 ? null : round($hours / 24, 1);
    }

    private function hasInvalidResolutionTimestamp(Incident $incident): bool
    {
        $finishedAt = $incident->resolved_at ?? $incident->confirmed_at;

        return $incident->reported_at !== null
            && $finishedAt !== null
            && $finishedAt->lt($incident->reported_at);
    }

    private function hotspotLabel(?string $purok, ?string $block): string
    {
        return ($purok ?: 'Purok not recorded')
            .' / '
            .($block ? 'Block '.$block : 'Block not recorded');
    }

    private function normalizePurok(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach ($this->puroks as $purok) {
            if (strcasecmp($value, $purok) === 0) {
                return $purok;
            }
        }

        return $value;
    }

    private function purokFromLocation(?string $location): ?string
    {
        $location = trim((string) $location);

        if ($location === '') {
            return null;
        }

        foreach ($this->puroks as $purok) {
            if (stripos($location, $purok) !== false) {
                return $purok;
            }
        }

        return null;
    }

    private function normalizeBlock(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\b(?:block|blk)\.?\s*([A-Za-z0-9-]+)/i', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    private function blockFromLocation(?string $location): ?string
    {
        $location = trim((string) $location);

        if ($location === '') {
            return null;
        }

        if (preg_match('/\b(?:block|blk)\.?\s*([A-Za-z0-9-]+)/i', $location, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
