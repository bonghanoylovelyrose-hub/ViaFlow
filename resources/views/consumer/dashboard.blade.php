@extends('layouts.app')

@php
    $activePage = 'dashboard';
    $currentBill = $billingSummary['current_bill'] ?? null;
    $currentBreakdown = $currentBill?->billing_breakdown ?? [];
    $hasBillingScenario = $hasBillingScenario ?? false;
    $billingScenario = $billingScenario ?? null;
@endphp

@section('title', 'My Dashboard')

@push('styles')
<style>
    .consumer-dashboard-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    }

    .consumer-stat {
        padding: 1.25rem;
        background: #fff;
        border: 1px solid rgba(216, 237, 255, 0.88);
        border-radius: 1rem;
    }

    .consumer-stat-label {
        color: var(--wb-muted);
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .consumer-stat-value {
        margin-top: 0.45rem;
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.15;
    }

    .consumer-dashboard-panels {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.9fr);
        gap: 1rem;
    }

    @media (max-width: 1199.98px) {
        .consumer-dashboard-panels {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 1200px) {
        .consumer-dashboard-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .consumer-dashboard-grid,
        .consumer-dashboard-panels {
            grid-template-columns: 1fr;
        }
    }
    /* Keep titles, primary figures and notes on shared rows. */
    .consumer-stat { min-width: 0; }
    .consumer-stat-label { min-height: 2.6em; line-height: 1.3; }
    .consumer-stat-value { font-variant-numeric: tabular-nums; }
    .consumer-stat .small { font-variant-numeric: tabular-nums; line-height: 1.35; }
    .dashboard-number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    @supports (grid-template-rows: subgrid) {
        .consumer-stat { display: grid; grid-row: span 3; grid-template-rows: subgrid; row-gap: 0; }
    }
    @media (max-width: 380px) { .consumer-stat-label { min-height: 0; } }
    /* Compact KPI cards without restricting longer descriptions. */
    .consumer-dashboard-grid { gap: 0.75rem; }
    .consumer-stat { padding: 0.875rem; min-height: 6.5rem; }
    .consumer-stat-label { font-size: 0.6875rem; font-weight: 600; line-height: 1.3; min-height: 1.3em; }
    .consumer-stat-value { margin-top: 0.25rem; font-size: 1.4375rem; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .consumer-stat .small { margin-top: 0.25rem !important; font-size: 0.6875rem; line-height: 1.3; }
    @media (min-width: 1200px) { .consumer-dashboard-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    @media (min-width: 992px) and (max-width: 1199.98px) { .consumer-dashboard-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 768px) and (max-width: 991.98px) { .consumer-dashboard-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767.98px) { .app-shell .content-wrapper .consumer-dashboard-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <x-loan-disconnection-notices :consumer="$consumer" />
            <div class="page-heading">
                <div>
                    <h1>My Dashboard</h1>
                    <p>Review your current balance, billing history, and recent meter readings.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($hasBillingScenario)
                        <a href="{{ route('dashboard', $billingScenario ? ['ledger' => 'recorded'] : []) }}" class="btn btn-outline-primary">{{ $billingScenario ? 'Recorded account' : 'Scenario account' }}</a>
                    @endif
                    <a href="{{ route('billing.index', $hasBillingScenario && ! $billingScenario ? ['ledger' => 'recorded'] : []) }}" class="btn btn-primary">View My Bills</a>
                </div>
            </div>

            @if ($consumer)
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <span class="small text-uppercase text-secondary fw-semibold">Service Status</span>
                    <x-consumer-status-badge :consumer="$consumer" />
                    @if ($consumer->account_number)
                        <span class="small text-secondary">Account #{{ $consumer->account_number }}</span>
                    @endif
                    @if ($consumer->meter_number)
                        <span class="small text-secondary">&middot; Meter {{ $consumer->meter_number }}</span>
                    @endif
                </div>
            @endif

            @if ($billingScenario)
                @include('consumer.billing.scenario-notice', ['notice' => $billingScenario['notice']])
            @endif

            @if (auth()->user()?->requiresPasswordChange())
                <div id="tempPasswordReminderBanner" class="alert alert-danger d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 mb-4 rounded-3 border-danger shadow-sm" role="alert" style="background-color: #fee2e2; border: 1px solid #ef4444; color: #991b1b;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-danger flex-shrink-0"></i>
                        <span class="fw-semibold">
                            You are using a temporary password. You can change it from your profile at any time.
                        </span>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm px-3 py-2 fw-semibold text-nowrap" id="dismissTempPasswordReminderBtn">
                        Dismiss
                    </button>
                </div>
            @endif

            @if ($announcements->isNotEmpty())
                <section class="panel-card p-3 p-md-4 mb-4" aria-labelledby="dashboard-announcements-title">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 fw-bold mb-0" id="dashboard-announcements-title">Announcements</h2>
                        <a href="{{ route('announcements.index') }}">View all</a>
                    </div>
                    @foreach ($announcements as $announcement)
                        <div class="border-top py-2">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <a class="fw-semibold" href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a>
                                @if ($announcement->edited_at)<span class="badge badge-soft-neutral">Edited</span>@endif
                                @if ($announcement->is_pinned)<span class="badge badge-soft-warning">Pinned</span>@endif
                                @if ($announcement->priority !== 'Normal')<span class="badge badge-soft-primary">{{ $announcement->priority }}</span>@endif
                            </div>
                            <div class="small text-secondary">{{ $announcement->category }} · {{ $announcement->published_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</div>
                        </div>
                    @endforeach
                </section>
            @endif

            @if (! $consumer)
                <div class="panel-card p-4">
                    <div class="section-title mb-2">Account Not Linked</div>
                    <p class="text-secondary mb-0">No consumer record is linked to this user account yet.</p>
                </div>
            @else
                <div class="consumer-dashboard-grid mb-4">
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Current Charges</div>
                        <div class="consumer-stat-value">P {{ number_format((float) ($currentBreakdown['current_bill_amount'] ?? 0), 2) }}</div>
                        <div class="small text-secondary mt-2">{{ $currentBill?->billing_period?->format('F Y') ?? 'No bill generated' }}</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Previous Balance Added</div>
                        <div class="consumer-stat-value {{ ((float) ($billingSummary['previous_balance_added'] ?? 0)) > 0 ? 'text-danger' : 'text-success' }}">P {{ number_format((float) ($billingSummary['previous_balance_added'] ?? 0), 2) }}</div>
                        <div class="small text-secondary mt-2">Carried into the current bill</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Current Total Due</div>
                        <div class="consumer-stat-value">P {{ number_format((float) ($currentBill?->amount ?? 0), 2) }}</div>
                        <div class="small text-secondary mt-2">Current charges plus carried balance</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Outstanding Balance</div>
                        <div class="consumer-stat-value {{ ((float) ($billingSummary['outstanding_balance'] ?? 0)) > 0 ? 'text-danger' : 'text-success' }}">P {{ number_format((float) ($billingSummary['outstanding_balance'] ?? 0), 2) }}</div>
                        <div class="small text-secondary mt-2">{{ $currentBill?->billing_period?->format('F Y') ?? 'No bill generated' }} · after verified payments</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Total Billing</div>
                        <div class="consumer-stat-value">P {{ number_format((float) ($billingSummary['total_billing'] ?? 0), 2) }}</div>
                        <div class="small text-secondary mt-2">All generated bills on record</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Previous Bills</div>
                        <div class="consumer-stat-value">{{ number_format((int) ($billingSummary['previous_billing_count'] ?? 0)) }}</div>
                        <div class="small text-secondary mt-2">{{ number_format((float) ($billingSummary['total_consumption'] ?? 0), 2) }} cu.m total usage</div>
                    </div>
                </div>

                <div class="consumer-dashboard-panels">
                    <div class="panel-card p-4">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                            <div>
                                <div class="section-title mb-1">Previous Billing History</div>
                                <p class="text-secondary mb-0"></p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th class="dashboard-number">Previous Balance</th>
                                        <th class="dashboard-number">Current Charges</th>
                                        <th class="dashboard-number">Total Due</th>
                                        <th class="dashboard-number">Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($billingHistory as $bill)
                                        @php
                                            $breakdown = $bill->billing_breakdown;
                                            $billBalance = $billingScenario ? $bill->statement_balance : $bill->balance_due;
                                            $billStatus = $billingScenario ? $bill->billingFixtureStatus() : $bill->status;
                                        @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $bill->billing_period?->format('F Y') ?? 'N/A' }}</td>
                                            <td class="dashboard-number">P {{ number_format((float) ($breakdown['previous_unpaid_balance'] ?? 0), 2) }}</td>
                                            <td class="dashboard-number">P {{ number_format((float) ($breakdown['current_bill_amount'] ?? 0), 2) }}</td>
                                            <td class="fw-semibold dashboard-number">P {{ number_format((float) $bill->amount, 2) }}</td>
                                            <td class="fw-semibold dashboard-number {{ $billBalance > 0 ? 'text-danger' : 'text-success' }}">P {{ number_format((float) $billBalance, 2) }}</td>
                                            <td><span class="badge {{ $billStatus === 'Paid' ? 'badge-soft-success' : ($billStatus === 'Overdue' ? 'badge-soft-danger' : 'badge-soft-warning') }}">{{ $billStatus }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-5 text-secondary">No billing history available yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="panel-card p-4">
                        <div class="section-title mb-3">Meter Reading History</div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Date</th><th class="dashboard-number">Previous</th><th class="dashboard-number">Current</th><th class="dashboard-number">Consumption</th></tr></thead>
                                <tbody>
                                    @forelse ($readingHistory as $reading)
                                        <tr>
                                            <td class="fw-semibold">{{ $reading->reading_date?->format('M d, Y') ?? 'N/A' }}</td>
                                            <td class="dashboard-number">{{ number_format((float) $reading->previous_reading, 2) }}</td>
                                            <td class="dashboard-number">{{ number_format((float) $reading->current_reading, 2) }}</td>
                                            <td class="dashboard-number">{{ number_format((float) $reading->consumption, 2) }} cu.m</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-5 text-secondary">No meter readings available yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @include('consumer.account.disconnection-history')
            @endif
        </div>
    </main>
</div>

@if (session('show_temporary_password_reminder') || auth()->user()?->requiresPasswordChange())
    <div class="modal fade" id="temporaryPasswordReminderModal" tabindex="-1" aria-labelledby="temporaryPasswordReminderTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="temporaryPasswordReminderTitle">Change Password</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">You signed in with a temporary password. You can change it in your profile whenever you choose.</div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Not now</button><a href="{{ route('consumer.profile.show') }}" class="btn btn-primary">Change Password</a></div>
        </div></div>
    </div>
@endif
@if (session('loan_access_denied'))
    <div class="modal fade" id="loanAccessDeniedModal" tabindex="-1" aria-labelledby="loanAccessDeniedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="loanAccessDeniedModalLabel">Loan Services Unavailable</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">{{ session('loan_access_denied') }}</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Understood</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@if (session('loan_access_denied'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                new bootstrap.Modal(document.getElementById('loanAccessDeniedModal')).show();
            });
        </script>
    @endpush
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordReminder = document.getElementById('temporaryPasswordReminderModal');
        if (passwordReminder && window.bootstrap) bootstrap.Modal.getOrCreateInstance(passwordReminder).show();
        const dismissBtn = document.getElementById('dismissTempPasswordReminderBtn');
        const reminderBanner = document.getElementById('tempPasswordReminderBanner');
        if (dismissBtn && reminderBanner) {
            dismissBtn.addEventListener('click', function (e) {
                e.preventDefault();
                reminderBanner.remove();
            });
        }
    });
</script>
@endpush
