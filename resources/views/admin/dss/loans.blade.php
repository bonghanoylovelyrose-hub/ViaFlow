@extends('layouts.app')

@php
    $activePage = 'dss-loans';
@endphp

@section('title', 'Loan DSS')

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
        font-size: 1.5rem;
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

    .dss-chart-empty-overlay {
        align-items: center;
        color: var(--vf-navy);
        display: flex;
        flex-direction: column;
        gap: .35rem;
        inset: 1rem 1rem 3rem 3.5rem;
        justify-content: center;
        pointer-events: none;
        position: absolute;
        text-align: center;
    }

    .dss-chart-empty-overlay i { color: var(--vf-royal); font-size: 1.6rem; }
    .dss-chart-empty-overlay span { background: rgba(255, 255, 255, .92); border-radius: 999px; font-size: .78rem; padding: .3rem .7rem; }
    .dss-overdue-empty-chart { height: 280px; display: grid; grid-template-columns: 5rem minmax(0, 1fr); grid-template-rows: minmax(0, 1fr) 1.75rem; gap: .35rem .6rem; }
    .dss-overdue-axis-label { align-self: center; color: var(--vf-navy); font-size: .72rem; text-align: right; }
    .dss-overdue-plot { align-items: center; background: repeating-linear-gradient(to right, transparent 0, transparent calc(25% - 1px), var(--vf-background) calc(25% - 1px), var(--vf-background) 25%); border-bottom: 1px solid var(--vf-light); border-left: 1px solid var(--vf-light); display: flex; justify-content: center; min-width: 0; position: relative; }
    .dss-overdue-empty-message { align-items: center; background: rgba(255,255,255,.92); border: 1px solid var(--vf-light); border-radius: 1rem; color: var(--vf-navy); display: flex; flex-direction: column; gap: .35rem; max-width: 17rem; padding: 1rem; text-align: center; }
    .dss-overdue-empty-message i { color: var(--vf-royal); font-size: 1.7rem; }
    .dss-overdue-empty-message strong { color: var(--vf-navy); font-size: .88rem; }
    .dss-overdue-x-axis { color: var(--vf-navy); font-size: .72rem; grid-column: 2; text-align: center; }
    .dss-repayment-chart-area { align-items: center; display: flex; height: 210px; justify-content: center; position: relative; }
    .dss-empty-donut { align-items: center; background: conic-gradient(var(--vf-light) 0deg 360deg); border-radius: 50%; display: flex; height: 168px; justify-content: center; position: relative; width: 168px; }
    .dss-empty-donut::before { background: #fff; border-radius: 50%; content: ""; height: 116px; position: absolute; width: 116px; }
    .dss-empty-donut-label { color: var(--vf-navy); font-size: .72rem; max-width: 76px; position: relative; text-align: center; }
    .dss-repayment-legend { display: flex; flex-wrap: wrap; gap: .55rem 1rem; justify-content: center; margin-top: .6rem; }
    .dss-repayment-legend-item { align-items: center; color: var(--vf-navy); display: inline-flex; font-size: .76rem; gap: .4rem; }
    .dss-repayment-legend-swatch { border-radius: 50%; height: .6rem; width: .6rem; }

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

    .dss-recommendation-critical {
        background: #fff5f5;
        border-left-color: #dc3545;
    }

    .dss-recommendation-warning {
        background: #fffbeb;
        border-left-color: #f59e0b;
    }

    .dss-recommendation-info {
        background: var(--vf-background);
        border-left-color: var(--vf-bright);
    }

    .dss-recommendation-success {
        background: #f0fdf4;
        border-left-color: var(--vf-green);
    }

    .dss-recommendation-icon {
        align-items: center;
        background: rgba(255, 255, 255, .85);
        border-radius: .75rem;
        display: flex;
        flex: 0 0 2.25rem;
        height: 2.25rem;
        justify-content: center;
        width: 2.25rem;
    }

    .dss-evaluation-details { min-width: 360px; white-space: normal; }
    .dss-evaluation-details li + li { margin-top: .4rem; }
    .dss-status-approved { color: var(--vf-green); background: #dcfce7; }
    .dss-status-rejected { color: #991b1b; background: #fee2e2; }
    .dss-status-review { color: #92400e; background: #fef3c7; }
    .dss-kpi { box-shadow: 0 5px 18px rgba(6, 43, 128, .045); }
    .dss-kpi-value { overflow-wrap: anywhere; }
    .dss-kpi-strip { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .65rem; margin-bottom: 1.25rem; }
    .dss-kpi-strip > * { min-width: 0; }
    .dss-kpi-strip .dss-kpi { padding: .75rem !important; border-radius: .8rem; }
    .dss-kpi-strip .dss-kpi-label { font-size: .64rem; letter-spacing: .035em; }
    .dss-kpi-strip .dss-kpi-value { font-size: clamp(1rem, 1.35vw, 1.3rem); margin-top: .35rem !important; }
    .dss-kpi-strip .dss-kpi-description { color: var(--vf-navy); font-size: .68rem; line-height: 1.3; margin-top: .35rem; }
    .dss-review-kpi { color: inherit; display: block; text-decoration: none; }
    .dss-review-kpi:hover .dss-kpi { border-color: #f0ad4e; box-shadow: 0 5px 18px rgba(6, 43, 128, .09); }
    .dss-results-table { width: 100%; min-width: 720px; table-layout: fixed; }
    .dss-results-table th:nth-child(1) { width: 23%; }
    .dss-results-table th:nth-child(2) { width: 17%; }
    .dss-results-table th:nth-child(3) { width: 18%; }
    .dss-results-table th:nth-child(4) { width: 18%; }
    .dss-results-table th:nth-child(5) { width: 24%; }
    .dss-results-table thead th { background: #f3f7f8; color: var(--vf-navy); font-size: .75rem; letter-spacing: .025em; white-space: nowrap; }
    .dss-results-table tbody td { padding-top: .7rem; padding-bottom: .7rem; vertical-align: middle; }
    .dss-result-status, .dss-installation-status { white-space: nowrap; }
    .dss-result-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
    .dss-detail-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .7rem 1rem; }
    .dss-detail-grid > div { display: grid; gap: .2rem; min-width: 0; }
    .dss-detail-label { color: var(--wb-muted); font-size: .76rem; font-weight: 700; }
    .dss-detail-subheading { margin: .1rem 0 .15rem; color: var(--vf-navy); font-size: .8rem; font-weight: 700; }
    .dss-detail-reason { grid-column: 1 / -1; }
    .dss-detail-value { min-width: 0; color: var(--vf-navy); font-weight: 600; overflow-wrap: anywhere; }
    .dss-detail-financial { display: grid; gap: .5rem; margin: 0; }
    .dss-detail-financial > div { display: grid; grid-template-columns: minmax(0,1fr) max-content; align-items: baseline; gap: .75rem; min-width: 0; }
    .dss-detail-financial dt { min-width: 0; color: var(--wb-muted); font-size: .82rem; font-weight: 600; overflow-wrap: anywhere; }
    .dss-detail-financial dd { margin: 0; color: var(--vf-navy); font-size: .85rem; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .dss-detail-financial .dss-detail-total { padding-top: .5rem; border-top: 1px solid var(--vf-light); }
    .dss-detail-financial .dss-detail-total :is(dt,dd) { font-weight: 800; }
    .dss-installation-status { display: inline-flex; width: fit-content; padding: .2rem .55rem; border-radius: 999px; background: var(--vf-background); color: var(--vf-navy); font-size: .75rem; font-weight: 700; }
    .dss-result-status { display: inline-flex; align-items: center; gap: .3rem; white-space: nowrap; }
    body.dss-loan-details-open .modal-backdrop {
        background-color: rgba(0, 0, 0, .35);
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }
    #loanApplicationDetailsModal { z-index: 1060; }
    #loanApplicationDetailsModal .modal-content { background: #fff; opacity: 1; }
    .dss-details-modal .modal-body { max-height: min(72vh, 720px); overflow-y: auto; }
      .dss-proof-preview img { display: block; width: 100%; max-height: 360px; object-fit: contain; border: 1px solid #dee2e6; border-radius: .5rem; background: #f8fafc; }
    .dss-detail-section + .dss-detail-section { border-top: 1px solid var(--vf-light); margin-top: 1rem; padding-top: 1rem; }
    .dss-detail-section h3 { color: var(--vf-navy); font-size: .9rem; font-weight: 700; margin-bottom: .6rem; }
    .dss-detail-list { margin-bottom: 0; padding-left: 1.1rem; }
    .dss-detail-list li + li { margin-top: .35rem; }
    @media (max-width: 575.98px) {
        .dss-detail-grid { grid-template-columns: minmax(0,1fr); }
        .dss-chart-wrap { height: 235px; }
        .dss-details-modal .modal-body { max-height: 65vh; }
    }
    @media (max-width: 1199.98px) {
        .dss-kpi-strip { display: flex; overflow-x: auto; padding-bottom: .35rem; scroll-snap-type: x proximity; }
        .dss-kpi-strip > * { flex: 0 0 170px; scroll-snap-align: start; }
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
                    <h1>Loan DSS</h1>
                    <p>Actual loan portfolio data and saved final DSS decisions, kept separate from Admin decisions.</p>
                </div>
                <a href="{{ route('dss.incidents') }}" class="btn btn-outline-primary">
                    <i class="bi bi-exclamation-triangle me-2"></i>Incident DSS
                </a>
            </div>

            <div class="dss-kpi-strip" aria-label="Loan summary">
                <div><div class="dss-kpi">
                    <div class="dss-kpi-label">Total Approved</div>
                    <div class="dss-kpi-value text-success">{{ number_format($decisionReport['summary']['approved']) }}</div>
                    <div class="dss-kpi-description">Approved decisions</div>
                </div></div>
                <div><div class="dss-kpi">
                    <div class="dss-kpi-label">Total Rejected</div>
                    <div class="dss-kpi-value text-danger">{{ number_format($decisionReport['summary']['rejected']) }}</div>
                    <div class="dss-kpi-description">Rejected decisions</div>
                </div></div>
                <div><div class="dss-kpi">
                    <div class="dss-kpi-label">Released Principal</div>
                    <div class="dss-kpi-value">@if ($dss['metric_availability']['total_released'])&#8369;{{ number_format($dss['summary']['total_released'], 2) }}@else N/A @endif</div>
                    <div class="dss-kpi-description">Principal of released loans</div>
                </div></div>
                <div><div class="dss-kpi">
                    <div class="dss-kpi-label">Recorded Repayments</div>
                    <div class="dss-kpi-value">@if ($dss['metric_availability']['total_repaid'])&#8369;{{ number_format($dss['summary']['total_repaid'], 2) }}@else N/A @endif</div>
                    <div class="dss-kpi-description">Verified and office repayments</div>
                </div></div>
                <div><div class="dss-kpi">
                    <div class="dss-kpi-label">Unpaid Principal</div>
                    <div class="dss-kpi-value">@if ($dss['metric_availability']['outstanding_balance'])&#8369;{{ number_format($dss['summary']['outstanding_balance'], 2) }}@else N/A @endif</div>
                    <div class="dss-kpi-description">Remaining approved loan principal</div>
                </div></div>
                <div><div class="dss-kpi">
                    <div class="dss-kpi-label">Unpaid Amounts Past Due</div>
                    <div class="dss-kpi-value">@if ($dss['metric_availability']['overdue_amount'])&#8369;{{ number_format($dss['summary']['overdue_amount'], 2) }}@else N/A @endif</div>
                    <div class="dss-kpi-description">Unpaid amounts past due</div>
                </div></div>
            </div>

            <div class="row g-4 mb-4">
                    <div class="col-xl-6"><div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div>
                            <h2 class="h6 fw-bold mb-1">Approved vs. Rejected</h2>
                            <p class="small text-secondary mb-0">Saved Approved and Rejected decisions across all years.</p>
                        </div><i class="bi bi-pie-chart text-primary"></i></div>
                        @if (array_sum($decisionReport['chart']['counts']) > 0)
                            <div class="dss-chart-wrap"><canvas id="loanStatusChart"></canvas></div>
                        @else
                            <div class="dss-empty-state">No approved or rejected applications found.</div>
                        @endif
                    </div></div>
                    <div class="col-xl-6"><div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div>
                            <h2 class="h6 fw-bold mb-1">Monthly Loan Releases and Repayments</h2>
                            <p class="small text-secondary mb-0">Principal by release date and repayments by payment date.</p>
                        </div><i class="bi bi-bar-chart-line text-primary"></i></div>
                        <form action="{{ route('dss.loans') }}" method="GET" class="d-flex align-items-center gap-2 mb-3" data-auto-filter>
                            <label for="loan-cash-flow-year" class="small fw-semibold">Period</label>
                            <x-period-picker id="loan-cash-flow-year" :years="$dss['options']['years']" :selected-year="$dss['filters']['year']"
                                :selected-month="$dss['filters']['month']" all-year-value="all" />
                            <noscript><button type="submit" class="btn btn-outline-primary btn-sm">Show</button></noscript>
                        </form>
                        <div class="dss-chart-wrap">
                            <canvas id="loanMonthlyChart" aria-label="Monthly releases and repayments in Philippine pesos"></canvas>
                            @unless ($dss['charts']['monthly']['has_data'])
                                <div class="dss-chart-empty-overlay"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>No data available yet</span></div>
                            @endunless
                        </div>
                    </div></div>
                    <div class="col-xl-6"><div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div>
                            <h2 class="h6 fw-bold mb-1">Overdue Balances by Purok/Block</h2>
                            <p class="small text-secondary mb-0">Unpaid scheduled amounts past their due dates, ranked highest first.</p>
                        </div><i class="bi bi-geo-alt text-warning"></i></div>
                        @if (count($dss['charts']['overdue_locations']['labels']) > 0)
                            <div class="dss-chart-wrap"><canvas id="loanOverdueLocationChart"></canvas></div>
                        @else
                            <div class="dss-overdue-empty-chart" role="img" aria-label="Empty horizontal overdue balance chart in Philippine pesos">
                                <div class="dss-overdue-axis-label">Location</div>
                                <div class="dss-overdue-plot">
                                    <div class="dss-overdue-empty-message">
                                        <i class="bi {{ $dss['charts']['overdue_locations']['has_schedule_data'] ? 'bi-check2-circle' : 'bi-geo-alt' }}" aria-hidden="true"></i>
                                        @if ($dss['charts']['overdue_locations']['has_schedule_data'])
                                            <strong>No overdue balances</strong><span>Confirmed overdue total: &#8369;0.00</span>
                                        @else
                                            <strong>No data available yet</strong><span>Loan due-date records will appear here.</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="dss-overdue-x-axis">PHP</div>
                            </div>
                        @endif
                    </div></div>
                    <div class="col-xl-6"><div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div>
                            <h2 class="h6 fw-bold mb-1">Repayment Status</h2>
                            <p class="small text-secondary mb-0">Recorded verified or admin-posted payments and schedule due dates determine status. Any unpaid amount past due is overdue.</p>
                        </div><i class="bi bi-cash-stack text-success"></i></div>
                        <div class="dss-repayment-chart-area">
                            @if ($dss['charts']['repayment_status']['has_data'])
                                <canvas id="loanRepaymentStatusChart" aria-label="Repayment status by recorded schedule"></canvas>
                            @else
                                <div class="dss-empty-donut" aria-hidden="true"><span class="dss-empty-donut-label">{{ $dss['charts']['repayment_status']['has_schedule_data'] ? 'No repayment status available yet' : 'No data available yet' }}</span></div>
                            @endif
                        </div>
                        <div class="dss-repayment-legend" aria-label="Repayment status legend">
                            @foreach ($dss['charts']['repayment_status']['labels'] as $legendIndex => $legendLabel)
                                @php($legendColors = ['#22a06b', '#e3a83b', '#d85b55', '#56a7b5'])
                                <span class="dss-repayment-legend-item"><span class="dss-repayment-legend-swatch" style="background: {{ $legendColors[$legendIndex] ?? 'var(--vf-royal)' }}"></span>{{ $legendLabel }}@if ($dss['charts']['repayment_status']['has_data']) <span class="text-secondary">({{ $dss['charts']['repayment_status']['counts'][$legendIndex] ?? 0 }})</span>@endif</span>
                            @endforeach
                        </div>
                    </div></div>
            </div>

            @if ($dss['summary']['total'] === 0)
                <div class="dss-empty-state mb-4">No approved or rejected applications yet.</div>
            @else

                <section class="panel-card p-3 p-lg-4 mb-4" aria-labelledby="loan-decision-records-title">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                        <div>
                            <h2 id="loan-decision-records-title" class="h5 fw-bold mb-1">Application Results</h2>
                            <p class="small text-secondary mb-0">{{ number_format($decisionReport['applications']->total()) }} saved decision(s)</p>
                        </div>
                        @if ($decisionReport['undated_rejected_count'] > 0 && $decisionReport['filters']['year'] === 'all')
                            <span class="small text-secondary">{{ $decisionReport['undated_rejected_count'] }} historical rejection(s) have no recorded decision date.</span>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 dss-results-table">
                            <thead><tr><th>Applicant Name</th><th>Account Number</th><th>Application Status</th><th>Installation Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                @forelse ($decisionReport['applications'] as $application)
                                    @php($evaluation = $application->latestDssEvaluation)
                                    @php($decision = $application->dss_decision_status)
                                    @php($statusClass = $decision === 'Approved' ? 'dss-status-approved' : 'dss-status-rejected')
                                    @php($installationStatus = $application->installation_status ?: 'Not recorded')
                                    <tr>
                                        <td class="fw-semibold">{{ $application->consumer?->full_name ?? 'Consumer record unavailable' }}</td>
                                        <td>{{ $application->consumer?->account_number ?? 'Not recorded' }}</td>
                                        <td><span class="badge rounded-pill dss-result-status {{ $statusClass }}">{{ $decision }}</span></td>
                                        <td><span class="dss-installation-status">{{ $installationStatus }}</span></td>
                                        <td>
                                            <div class="dss-result-actions">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-loan-details="{{ $application->id }}" data-loan-name="{{ $application->consumer?->full_name ?? 'Loan application' }}">View Details</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <template id="loan-details-template-{{ $application->id }}">@include('admin.dss._loan-detail-template', ['application' => $application, 'evaluation' => $evaluation, 'decision' => $decision])</template>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">No approved or rejected applications yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                        <span class="small text-secondary">{{ $decisionReport['applications']->firstItem() ?? 0 }}–{{ $decisionReport['applications']->lastItem() ?? 0 }} of {{ $decisionReport['applications']->total() }}</span>
                        {{ $decisionReport['applications']->links() }}
                    </div>
                </section>
                <div class="modal fade dss-details-modal" id="loanApplicationDetailsModal" tabindex="-1" aria-labelledby="loanDetailsModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                        <div class="modal-content border-0 rounded-4">
                            <div class="modal-header"><div><h2 class="modal-title fs-5" id="loanDetailsModalTitle">Application Details</h2><div class="small text-secondary" id="loanDetailsModalSubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                            <div class="modal-body" id="loanDetailsModalBody"></div>
                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
                        </div>
                    </div>
                </div>
                <div class="panel-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div>
                        <h2 class="h6 fw-bold mb-1">Recommended Actions</h2>
                        <p class="small text-secondary mb-0">Collection follow-up is based on actual unpaid amounts and recorded due dates.</p>
                    </div><i class="bi bi-lightbulb text-warning"></i></div>
                    @foreach ($dss['recommendations'] as $recommendation)
                        <div class="dss-recommendation dss-recommendation-{{ $recommendation['severity'] }}">
                            <div class="dss-recommendation-icon">
                                <i class="bi {{ $recommendation['severity'] === 'critical' ? 'bi-exclamation-octagon-fill text-danger' : ($recommendation['severity'] === 'info' ? 'bi-calendar-check text-primary' : 'bi-check-circle-fill text-success') }}"></i>
                            </div>
                            <div>
                                <div class="fw-bold">{{ $recommendation['title'] }}</div>
                                <div class="small text-secondary mt-1">{{ $recommendation['message'] }}</div>
                                @if (! empty($recommendation['cases']))
                                    <ul class="small text-secondary mt-2 mb-0 ps-3">
                                        @foreach ($recommendation['cases'] as $case)
                                            <li>{{ $case['consumer'] }}{{ $case['account_number'] ? ' ('.$case['account_number'].')' : '' }} — {{ $case['installment_count'] }} missed installment(s), PHP {{ number_format($case['amount'], 2) }}, earliest due {{ $case['earliest_due_date'] }}.</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="small mt-2"><span class="fw-semibold">Next step:</span> {{ $recommendation['action'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const detailsModal = document.getElementById('loanApplicationDetailsModal');
        if (detailsModal && window.bootstrap) {
            document.body.appendChild(detailsModal);
            detailsModal.addEventListener('show.bs.modal', () => document.body.classList.add('dss-loan-details-open'));
            detailsModal.addEventListener('hidden.bs.modal', () => document.body.classList.remove('dss-loan-details-open'));
            const modal = window.bootstrap.Modal.getOrCreateInstance(detailsModal);
            const modalTitle = document.getElementById('loanDetailsModalTitle');
            const modalSubtitle = document.getElementById('loanDetailsModalSubtitle');
            const modalBody = document.getElementById('loanDetailsModalBody');
            document.querySelectorAll('[data-loan-details]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const id = button.getAttribute('data-loan-details');
                    const template = document.getElementById('loan-details-template-' + id);
                    if (!template) return;
                    modalTitle.textContent = button.getAttribute('data-loan-name') || 'Application Details';
                    modalSubtitle.textContent = 'Loan #' + id;
                    detailsModal.dataset.focusScheduleLoanId = button.hasAttribute('data-focus-schedule') ? id : '';
                    detailsModal.dataset.focusMeterLoanId = button.hasAttribute('data-focus-meter') ? id : '';
                    modalBody.replaceChildren(template.content.cloneNode(true));
                    modal.show();
                });
            });
            detailsModal.addEventListener('shown.bs.modal', function () {
                const scheduleId = detailsModal.dataset.focusScheduleLoanId;
                const meterId = detailsModal.dataset.focusMeterLoanId;
                if (scheduleId) modalBody.querySelector('#schedule-date-' + scheduleId)?.focus();
                else if (meterId) modalBody.querySelector('#meter-number-' + meterId)?.focus();
            });

            const reopenMeterLoanId = @json(session('meter_assignment_loan_id'));
            if (reopenMeterLoanId) {
                document.querySelector('[data-focus-meter][data-loan-details="' + String(reopenMeterLoanId).replace(/[^0-9]/g, '') + '"]')?.click();
            }
        }

        const data = @json($dss['charts']);
        const decisionChart = @json($decisionReport['chart']);
        const colors = ['#0f7890', '#56a7b5', '#e3a83b', '#d85b55', '#7b70b8', '#55a5c0', '#7d9c70', '#9a7a61'];
        const peso = value => 'PHP ' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const statusCanvas = document.getElementById('loanStatusChart');
        if (statusCanvas) new Chart(statusCanvas, {
            type: 'doughnut',
            data: { labels: decisionChart.labels, datasets: [{ data: decisionChart.counts, backgroundColor: ['#22a06b', '#dc3545'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                tooltip: { callbacks: { label: function (context) {
                    const count = Number(context.raw) || 0;
                    const total = context.dataset.data.reduce((sum, value) => sum + (Number(value) || 0), 0);
                    const percentage = total ? (count * 100 / total).toFixed(1) : '0.0';
                    return context.label + ': ' + count + ' (' + percentage + '%)';
                } } }
            } }
        });

        const monthlyCanvas = document.getElementById('loanMonthlyChart');
        if (monthlyCanvas) new Chart(monthlyCanvas, {
            type: 'bar',
            data: { labels: data.monthly.labels, datasets: [
                { label: 'Released', data: data.monthly.releases, backgroundColor: '#0f7890', borderRadius: 4 },
                { label: 'Repaid', data: data.monthly.repayments, backgroundColor: '#e3a83b', borderRadius: 4 }
            ] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, title: { display: true, text: 'PHP', color: '#64748b' }, grid: { color: '#e8eef2' }, ticks: { display: data.monthly.has_data, callback: value => peso(value) } }
                },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }, tooltip: { enabled: data.monthly.has_data, callbacks: { label: context => context.dataset.label + ': ' + peso(context.raw) } } }
            }
        });

        const locationCanvas = document.getElementById('loanOverdueLocationChart');
        if (locationCanvas) new Chart(locationCanvas, {
            type: 'bar',
            data: { labels: data.overdue_locations.labels, datasets: [{ label: 'Overdue balance (PHP)', data: data.overdue_locations.amounts, backgroundColor: '#d85b55', borderRadius: 4 }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                scales: { x: { beginAtZero: true, title: { display: true, text: 'PHP', color: '#64748b' }, grid: { color: '#e8eef2' }, ticks: { callback: value => peso(value) } } },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => peso(context.raw) } } }
            }
        });

        const repaymentCanvas = document.getElementById('loanRepaymentStatusChart');
        if (repaymentCanvas) new Chart(repaymentCanvas, {
            type: 'doughnut',
            data: { labels: data.repayment_status.labels, datasets: [{ data: data.repayment_status.counts, backgroundColor: ['#0f7890', '#e3a83b', '#d85b55', '#56a7b5'], borderWidth: 0 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: context => context.label + ': ' + context.raw + ' scheduled item(s), ' + peso(data.repayment_status.amounts[context.dataIndex]) } }
                }
            }
        });
    });
</script>
@endpush
