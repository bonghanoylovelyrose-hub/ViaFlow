@extends('layouts.app')

@php
    $activePage = 'dashboard';
@endphp

@section('title', 'Meter Reader Dashboard')

@push('styles')
<style>
    .reader-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .reader-stat {
        padding: 1.25rem;
        background: #fff;
        border: 1px solid rgba(216, 237, 255, 0.88);
        border-radius: 1rem;
    }

    .reader-stat-label {
        color: var(--wb-muted);
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .reader-stat-value {
        margin-top: 0.45rem;
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.15;
    }

    .reader-dashboard-panels {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
        gap: 1rem;
    }

    @media (max-width: 1199.98px) {
        .reader-dashboard-grid,
        .reader-dashboard-panels {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .reader-dashboard-grid,
        .reader-dashboard-panels {
            grid-template-columns: 1fr;
        }
    }
    /* Keep titles, primary figures and notes on shared rows. */
    .reader-stat { min-width: 0; }
    .reader-stat-label { min-height: 2.6em; line-height: 1.3; }
    .reader-stat-value { font-variant-numeric: tabular-nums; }
    .reader-stat .small { font-variant-numeric: tabular-nums; line-height: 1.35; }
    .dashboard-number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    @supports (grid-template-rows: subgrid) {
        .reader-stat { display: grid; grid-row: span 3; grid-template-rows: subgrid; row-gap: 0; }
    }
    @media (max-width: 380px) { .reader-stat-label { min-height: 0; } }
    /* Compact KPI cards without restricting longer descriptions. */
    .reader-dashboard-grid { gap: 0.75rem; }
    .reader-stat { padding: 0.875rem; min-height: 6.5rem; }
    .reader-stat-label { font-size: 0.6875rem; font-weight: 600; line-height: 1.3; min-height: 1.3em; }
    .reader-stat-value { margin-top: 0.25rem; font-size: 1.4375rem; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .reader-stat .small { margin-top: 0.25rem !important; font-size: 0.6875rem; line-height: 1.3; }
    @media (max-width: 767.98px) { .app-shell .content-wrapper .reader-dashboard-grid { grid-template-columns: minmax(0, 1fr); } }
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
                    <h1>Meter Reader Dashboard</h1>
                    <p>Track submitted readings and previous billing periods without exposing consumer balances.</p>
                </div>
                <a href="{{ route('meter-readings.index') }}" class="btn btn-primary">Open Meter Readings</a>
            </div>

            <div class="reader-dashboard-grid mb-4">
                <div class="reader-stat">
                    <div class="reader-stat-label">Submitted Readings</div>
                    <div class="reader-stat-value">{{ number_format((int) ($readerSummary['total_readings'] ?? 0)) }}</div>
                    <div class="small text-secondary mt-2">All readings assigned to you</div>
                </div>
                <div class="reader-stat">
                    <div class="reader-stat-label">Consumers Read</div>
                    <div class="reader-stat-value">{{ number_format((int) ($readerSummary['consumers_read'] ?? 0)) }}</div>
                    <div class="small text-secondary mt-2">Unique consumer meters</div>
                </div>
                <div class="reader-stat">
                    <div class="reader-stat-label">Total Consumption</div>
                    <div class="reader-stat-value">{{ number_format((float) ($readerSummary['total_consumption'] ?? 0), 2) }}</div>
                    <div class="small text-secondary mt-2">Cubic meters recorded</div>
                </div>
                <div class="reader-stat">
                    <div class="reader-stat-label">Ready For Billing</div>
                    <div class="reader-stat-value">{{ number_format((int) ($readerSummary['ready_for_billing'] ?? 0)) }}</div>
                    <div class="small text-secondary mt-2">Encoded readings without generated bills</div>
                </div>
            </div>

            <div class="reader-dashboard-panels">
                <div class="panel-card p-4">
                    <div class="section-title mb-1">Billed Reading Periods</div>
                    <p class="text-secondary mb-3">Periods with submitted readings and recorded consumption.</p>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Consumer</th>
                                    <th>Period</th>
                                    <th class="dashboard-number">Consumption</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($billingHistory as $bill)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $bill->consumer?->full_name ?? 'Unassigned consumer' }}</div>
                                            <div class="small text-secondary">{{ $bill->consumer?->account_number ?? '—' }} · {{ $bill->consumer?->meter_number ?? 'No meter number' }}</div>
                                        </td>
                                        <td class="text-secondary">{{ $bill->billing_period?->format('F Y') ?? 'N/A' }}</td>
                                        <td class="dashboard-number">{{ number_format((float) ($bill->meterReading?->consumption ?? $bill->units_consumed ?? 0), 2) }} cu.m</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center py-5 text-secondary">No billed readings yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel-card p-4">
                    <div class="section-title mb-1">Meter Reading History</div>
                    <p class="text-secondary mb-3">Recent meter activity used by administrators for billing generation.</p>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Consumer</th>
                                    <th>Date</th>
                                    <th class="dashboard-number">Previous</th>
                                    <th class="dashboard-number">Current</th>
                                    <th class="dashboard-number">Consumption</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($readingHistory as $reading)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $reading->consumer?->full_name ?? 'Unassigned consumer' }}</div>
                                            <div class="small text-secondary">{{ $reading->consumer?->meter_number ?? 'No meter number' }}</div>
                                        </td>
                                        <td class="text-secondary">{{ $reading->reading_date?->format('M d, Y') ?? 'N/A' }}</td>
                                        <td class="dashboard-number">{{ number_format((float) $reading->previous_reading, 2) }}</td>
                                        <td class="dashboard-number">{{ number_format((float) $reading->current_reading, 2) }}</td>
                                        <td class="dashboard-number">{{ number_format((float) $reading->consumption, 2) }} cu.m</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center py-5 text-secondary">No meter readings submitted yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection
