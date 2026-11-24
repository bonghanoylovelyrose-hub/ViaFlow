@extends('layouts.app')

@php
    $activePage = 'dss-incidents';
@endphp

@section('title', 'Incident DSS')

@push('styles')
<style>
    .dss-kpi {
        height: 100%;
        border: 1px solid rgba(7, 84, 200, .18);
        border-radius: 1.1rem;
        background: #fff;
        box-shadow: 0 14px 34px rgba(6, 43, 128, .055);
    }

    .dss-kpi-label {
        color: var(--vf-navy);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .dss-kpi-value {
        color: var(--vf-navy);
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1.1;
    }

    .dss-chart-card {
        height: 100%;
    }

    .dss-chart-wrap {
        height: 280px;
        position: relative;
    }

    .dss-empty-state {
        align-items: center;
        background: #f8fafc;
        border: 1px dashed var(--vf-light);
        border-radius: 1rem;
        color: var(--vf-navy);
        display: flex;
        justify-content: center;
        min-height: 190px;
        padding: 1.25rem;
        text-align: center;
    }

    .dss-recommendation {
        align-items: flex-start;
        background: #f8fafc;
        border-left: 4px solid var(--vf-royal);
        border-radius: .9rem;
        display: flex;
        gap: .85rem;
        padding: .95rem 1rem;
    }

    .dss-recommendation + .dss-recommendation {
        margin-top: .75rem;
    }

    .dss-recommendation-urgent {
        background: #fff5f5;
        border-left-color: #dc3545;
    }

    .dss-recommendation-warning {
        background: #fffbeb;
        border-left-color: #f59e0b;
    }

    .dss-recommendation-secondary {
        background: #f8fafc;
        border-left-color: var(--vf-navy);
    }

</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Incident DSS</h1>
                    <p>Decision support using all recorded incident reports.</p>
                </div>
                <a href="{{ route('dss.loans') }}" class="btn btn-outline-primary">
                    <i class="bi bi-bank me-2"></i>Loan DSS
                </a>
            </div>

            @foreach ($dss['data_notes'] as $note)
                @unless (str_starts_with($note, 'Average resolution time uses only valid timestamps'))
                    <div class="alert alert-warning border-0 rounded-3">{{ $note }}</div>
                @endunless
            @endforeach

            @unless ($dss['charts']['has_data'])
                <div class="alert alert-info border-0 rounded-3">No incident data available.</div>
            @endunless

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Total incidents</div>
                        <div class="dss-kpi-value mt-2">{{ number_format($dss['summary']['total']) }}</div>
                        <div class="small text-secondary mt-1">All recorded incident reports</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Still open</div>
                        <div class="dss-kpi-value mt-2">{{ number_format($dss['summary']['open']) }}</div>
                        <div class="small text-secondary mt-1">{{ number_format($dss['summary']['pending']) }} pending review</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Resolved</div>
                        <div class="dss-kpi-value mt-2">{{ number_format($dss['summary']['resolved']) }}</div>
                        <div class="small text-secondary mt-1">{{ number_format($dss['summary']['resolution_rate'], 1) }}% resolution rate</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Average resolution</div>
                        <div class="dss-kpi-value mt-2">
                            {{ $dss['summary']['average_resolution_days'] !== null ? number_format($dss['summary']['average_resolution_days'], 1).' days' : 'N/A' }}
                        </div>
                        <div class="small text-secondary mt-1">
                            {{ $dss['summary']['invalid_resolution_timestamps'] > 0 ? $dss['summary']['invalid_resolution_timestamps'].' invalid timestamp(s) excluded' : 'Valid resolved timestamps only' }}
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Top hotspot</div>
                        <div class="dss-kpi-value mt-2 fs-6">{{ $dss['summary']['top_location'] ?: 'No located hotspot' }}</div>
                        <div class="small text-secondary mt-1">
                            {{ $dss['summary']['top_category'] ? $dss['summary']['top_category'].' leads with '.number_format($dss['summary']['top_category_share'], 1).'% of reports' : 'Not enough data' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Unresolved Reports by Location</h2>
                                <p class="small text-secondary mb-0">All unresolved database reports, ranked highest first. Missing location data is included.</p>
                            </div>
                            <i class="bi bi-geo-alt text-primary"></i>
                        </div>
                        @if (count($dss['charts']['area_totals']['labels']) > 0)
                            <div class="dss-chart-wrap">
                                <canvas id="incidentAreaTotalsChart"></canvas>
                            </div>
                        @else
                            <div class="dss-empty-state">No incident data available.</div>
                        @endif
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Area response priority</h2>
                                <p class="small text-secondary mb-0">Ranked by actual unresolved incident count; severity is not recorded.</p>
                            </div>
                            <i class="bi bi-exclamation-circle text-warning"></i>
                        </div>
                        @if (count($dss['charts']['area_priority']['labels']) > 0)
                            <div class="dss-chart-wrap">
                                <canvas id="incidentAreaPriorityChart"></canvas>
                            </div>
                        @else
                            <div class="dss-empty-state">No unresolved incident data available.</div>
                        @endif
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Incident categories by area</h2>
                                <p class="small text-secondary mb-0">Recorded category counts for each located area.</p>
                            </div>
                            <i class="bi bi-tags text-primary"></i>
                        </div>
                        @if (count($dss['charts']['area_categories']['labels']) > 0 && count($dss['charts']['area_categories']['datasets']) > 0)
                            <div class="dss-chart-wrap">
                                <canvas id="incidentAreaCategoriesChart"></canvas>
                            </div>
                        @else
                            <div class="dss-empty-state">No incident category data available.</div>
                        @endif
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Incident trend</h2>
                                <p class="small text-secondary mb-0">
                                    Reported and resolved records by month. Recent direction:
                                    {{ $dss['trend']['direction'] ?? 'Not enough monthly data' }}
                                    @if ($dss['trend']['latest_month_delta'] !== null)
                                        ({{ $dss['trend']['latest_month_delta'] > 0 ? '+' : '' }}{{ $dss['trend']['latest_month_delta'] }} reports vs. previous month).
                                    @endif
                                </p>
                            </div>
                            <i class="bi bi-graph-up-arrow text-primary"></i>
                        </div>
                        @if ($dss['charts']['trend']['has_data'])
                            <div class="dss-chart-wrap">
                                <canvas id="incidentTrendChart"></canvas>
                            </div>
                        @else
                            <div class="dss-empty-state">No incident data available.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="panel-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-1">Incident priority recommendations</h2>
                        <p class="small text-secondary mb-1">
                            Configured rules: Urgent when unresolved reports exceed {{ $dss['priority_rules']['response_deadline_days'] }} days; High Priority at least
                            {{ $dss['priority_rules']['high_priority']['unresolved_backlog_count'] }} unresolved reports or
                            {{ number_format($dss['priority_rules']['high_priority']['unresolved_share_percent']) }}% open share, or at least
                            {{ $dss['priority_rules']['high_priority']['recurrence_count'] }} reports within
                            {{ $dss['priority_rules']['high_priority']['recurrence_window_days'] }} days.
                        </p>
                        <p class="small text-secondary mb-0">These are configurable operational triage rules, not incident severity ratings or validated risk predictions.</p>
                    </div>
                    <i class="bi bi-lightbulb text-warning"></i>
                </div>
                @forelse ($dss['recommendations'] as $recommendation)
                    <div class="dss-recommendation dss-recommendation-{{ $recommendation['badge_class'] }}">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge text-bg-{{ $recommendation['badge_class'] }}">{{ $recommendation['badge'] }}</span>
                                <span class="fw-bold">{{ $recommendation['location'] }}</span>
                            </div>
                            <div class="small mt-2">
                                <strong>Unresolved reports:</strong> {{ number_format($recommendation['unresolved_count']) }}
                                <span class="mx-1">·</span>
                                <strong>Oldest unresolved report:</strong>
                                {{ $recommendation['oldest_report_age_days'] === null ? 'No valid date' : number_format($recommendation['oldest_report_age_days']).' days old (reported '.$recommendation['oldest_report_date'].')' }}
                                <span class="mx-1">·</span>
                                <strong>Most common issue:</strong>
                                {{ $recommendation['most_common_issue'] ? $recommendation['most_common_issue'].' ('.$recommendation['most_common_issue_count'].' unresolved)' : 'Not recorded' }}
                            </div>
                            <div class="small text-secondary mt-1">Reports in configured recurrence window ({{ $recommendation['recurrence_start_date'] }}–{{ $recommendation['recurrence_end_date'] }}): {{ number_format($recommendation['recent_report_count']) }}; reports past response deadline: {{ number_format($recommendation['overdue_count']) }}.</div>
                            <div class="small text-secondary mt-1">{{ $recommendation['reason'] }}</div>
                            <div class="small mt-2"><span class="fw-semibold">Recommended action:</span> {{ $recommendation['action'] }}</div>
                        </div>
                    </div>
                @empty
                    <div class="dss-empty-state">No incident data available.</div>
                @endforelse
            </div>

        </div>
    </main>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartData = @json($dss['charts']);
        const chartColors = ['#0f7890', '#56a7b5', '#e3a83b', '#d85b55', '#7b70b8', '#55a5c0', '#7d9c70', '#9a7a61'];
        const areaTotalsCanvas = document.getElementById('incidentAreaTotalsChart');

        if (areaTotalsCanvas) {
            new Chart(areaTotalsCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.area_totals.labels,
                    datasets: [{
                        label: 'Unresolved reports',
                        data: chartData.area_totals.counts,
                        backgroundColor: chartColors[0],
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } }
                }
            });
        }

        const areaPriorityCanvas = document.getElementById('incidentAreaPriorityChart');

        if (areaPriorityCanvas) {
            new Chart(areaPriorityCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.area_priority.labels,
                    datasets: [{
                        label: 'Unresolved incidents (priority proxy)',
                        data: chartData.area_priority.open,
                        backgroundColor: '#e3a83b',
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } }
                }
            });
        }

        const areaCategoriesCanvas = document.getElementById('incidentAreaCategoriesChart');

        if (areaCategoriesCanvas) {
            new Chart(areaCategoriesCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.area_categories.labels,
                    datasets: chartData.area_categories.datasets.map((dataset, index) => ({
                        ...dataset,
                        backgroundColor: chartColors[index % chartColors.length],
                        borderRadius: 3,
                    }))
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                        y: { stacked: true }
                    },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } }
                }
            });
        }

        const trendCanvas = document.getElementById('incidentTrendChart');

        if (trendCanvas) {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: chartData.trend.labels,
                    datasets: [
                        {
                            label: 'Reported',
                            data: chartData.trend.reported,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, .12)',
                            fill: true,
                            tension: .35,
                            pointRadius: 3,
                        },
                        {
                            label: 'Resolved (same reporting month)',
                            data: chartData.trend.resolved,
                            borderColor: '#20c997',
                            backgroundColor: 'rgba(32, 201, 151, .12)',
                            fill: false,
                            tension: .35,
                            pointRadius: 3,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
                    }
                }
            });
        }

    });
</script>
@endpush
