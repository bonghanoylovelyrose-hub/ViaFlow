@extends('layouts.app')

@php
    $activePage = 'loans';
    $rescheduleUsage = auth()->user()->consumer ? app(\App\Services\InstallationRescheduleService::class)->usage(auth()->user()->consumer) : null;
@endphp

@section('title', 'Loans')

@push('styles')
<style>
    .loans-filter-form {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: flex-end;
        align-items: center;
    }

    .loans-filter-group {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .loan-application-flow {
        display: grid;
        gap: 1rem;
    }

    #loanModal [hidden] { display: none !important; }
    .loan-agreement-text { display: grid; gap: .85rem; color: var(--vf-navy); line-height: 1.55; }
    .loan-agreement-text p { margin: 0; }
    .loan-selected-term { margin: 0 0 1rem; color: var(--vf-navy); font-size: .9rem; }
    .loan-terms-consent { display: flex; align-items: flex-start; gap: .65rem; margin-top: 1.2rem; padding: .85rem 1rem; border: 1px solid var(--vf-light); border-radius: .7rem; background: var(--vf-background); }
    .loan-terms-consent .form-check-input { float: none; flex: 0 0 auto; margin: .2rem 0 0; }
    .loan-confirmation-copy { margin: 0; font-size: 1rem; font-weight: 700; color: var(--vf-navy); }
    .loan-form-step {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 0.9rem;
        padding: 1rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 0.8rem;
        background: #fff;
    }

    .loan-step-number {
        width: 2rem;
        height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(7, 84, 200, 0.1);
        color: var(--wb-primary);
        font-weight: 800;
        line-height: 1;
    }

    .loan-step-body {
        min-width: 0;
    }

    .loan-step-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .loan-step-copy,
    .loan-field-help {
        color: var(--wb-muted);
        font-size: 0.86rem;
    }

    .loan-step-copy {
        margin: 0.15rem 0 0.85rem;
    }

    .loan-field-help {
        margin-top: 0.35rem;
    }

    .loan-form-summary {
        padding: 0.9rem 1rem;
        border: 1px solid rgba(7, 84, 200, 0.16);
        border-radius: 0.8rem;
        background: rgba(7, 84, 200, 0.05);
        color: var(--wb-muted);
        font-size: 0.9rem;
    }

    .loan-cost-breakdown,
    .loan-payment-schedule {
        display: grid;
        gap: 0.55rem;
        padding: 0.9rem 1rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 0.8rem;
        background: #fff;
    }

    .loan-cost-row,
    .loan-schedule-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
    }

    .loan-cost-row.total,
    .loan-schedule-total {
        padding-top: 0.55rem;
        border-top: 1px solid rgba(216, 237, 255, 0.95);
        font-weight: 800;
    }

    .loan-breakdown-loading[hidden],
    .loan-breakdown-content[hidden],
    .loan-breakdown-unavailable[hidden] {
        display: none !important;
    }

    .loans-filter-label {
        margin: 0;
        color: var(--wb-muted);
        font-size: 0.85rem;
        font-weight: 700;
        white-space: nowrap;
    }

    @media (max-width: 991.98px) {
        .loans-filter-form {
            justify-content: stretch;
        }

        .loans-filter-group {
            width: 100%;
        }

        .loans-filter-group .form-control,
        .loans-filter-group .form-select {
            flex: 1 1 auto;
        }

        .loan-form-step {
            grid-template-columns: 1fr;
        }

    }
    /* Consumer loan workspace */
    .loan-layout { display: grid; grid-template-columns: minmax(0, 1fr); align-items: start; gap: 1rem; width: 100%; margin: 0 0 1.5rem; }
    .loan-layout > div { display: block; min-width: 0; }
    .loan-layout .panel-card { min-width: 0; height: auto; box-sizing: border-box; margin-bottom: 0 !important; }
    .loan-layout .panel-card { border: 1px solid var(--vf-light); box-shadow: 0 8px 24px rgba(7, 84, 200, .10); }
    .loan-layout .section-title { color: var(--vf-navy); }

    .loan-terms > .d-flex, .loan-cost-row { gap: 1rem; align-items: baseline; }
    .loan-terms > .d-flex > :first-child, .loan-cost-row > :first-child { min-width: 0; }
    .loan-terms > .d-flex strong, .loan-cost-row strong { text-align: right; overflow-wrap: anywhere; }
    .loan-verification-note { padding: .8rem 1rem; margin-top: .4rem; background: var(--vf-background); border: 1px solid var(--vf-light); border-radius: .75rem; }
    .loan-overview-card .loan-cost-breakdown { background: #f7fdff; border-color: var(--vf-light); }
    .loan-overview-card .loan-cost-row.total { color: var(--vf-navy); border-color: var(--vf-light); }
    .loan-queue-header { display: grid; gap: 1rem; }
    .loan-queue-header .loans-filter-form { display: grid; grid-template-columns: minmax(0,16rem); gap: .75rem; align-items: end; justify-content: start; }
    .loan-queue-header .loans-filter-group { display: grid; gap: .3rem; min-width: 0; width: auto; }
    .loan-queue-header :is(.form-control, .form-select, .btn) { min-height: 2.5rem; min-width: 0; width: 100%; }
    .loan-queue-header .status-filter-display { display: flex; align-items: center; gap: .5rem; width: 100%; min-width: 0; }
    .loan-queue-header .status-filter-display .form-select { flex: 1 1 0; width: auto; min-width: 0; }
    .loan-queue-header .status-filter-display .btn { flex: 0 0 auto; width: auto; }
    .loan-queue-header .status-filter-display .status-filter-value { max-width: calc(100% - 6.5rem); }


    .loan-records { container-type: inline-size; width: 100%; min-width: 0; overflow: visible; }
    .loan-records .table { width: 100%; min-width: 0; table-layout: fixed; margin-bottom: 0; }
    .loan-records :is(th, td) { overflow-wrap: anywhere; white-space: normal; vertical-align: top; }
    .loan-records .loan-empty-row td { text-align: center; }
    @container (max-width: 780px) {
        .loan-queue-table, .loan-queue-table tbody, .loan-queue-table tr, .loan-queue-table td { display: block; width: 100%; }
        .loan-queue-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .loan-queue-table tbody tr:not(.loan-empty-row) { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .75rem; padding: 1rem; margin-bottom: .75rem; border: 1px solid var(--vf-light); border-radius: .8rem; }
        .loan-queue-table tbody tr:not(.loan-empty-row) td { padding: 0; border: 0; }
        .loan-queue-table td[data-label]::before { content: attr(data-label); display: block; color: var(--vf-navy); font-size: .72rem; font-weight: 700; margin-bottom: .25rem; }
    }
    @media (max-width: 575.98px) {
        .loan-queue-header .loans-filter-form { grid-template-columns: minmax(0,1fr); }
        .loan-queue-table tbody tr:not(.loan-empty-row) { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .loan-layout .panel-card { padding: 1.1rem !important; }
    }
    @media (max-width: 360px) { .loan-queue-table tbody tr:not(.loan-empty-row) { grid-template-columns: minmax(0,1fr); } }
    @container (max-width: 1050px) { .loan-records .loan-number::before { text-align: left; } }
    /* Financial rows share a fixed right edge and tabular figures. */
    .loan-cost-row, .loan-schedule-row, .loan-income-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) max-content;
        align-items: baseline;
        gap: 1rem;
        min-width: 0;
    }
    .loan-cost-row > :first-child, .loan-schedule-row > :first-child, .loan-income-row > :first-child { min-width: 0; text-align: left; }
    .loan-cost-row > :last-child, .loan-schedule-row > :last-child, .loan-income-row > :last-child {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .loan-terms > .d-flex { gap: 1rem; }
    .loan-terms strong, .loan-admin-facts strong, .loan-number, input[type=number] { text-align: right; font-variant-numeric: tabular-nums; }
    .loan-records .table .loan-number, .loan-operational-table .loan-number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .loan-records .table .loan-number::before { text-align: left; white-space: normal; }
    @media (max-width: 360px) {
        .loan-cost-row, .loan-schedule-row, .loan-income-row { gap: .5rem; }
    }
    .loan-application-list { container-type: inline-size; display: grid; gap: .75rem; min-width: 0; }
    .loan-application-card { display: flex; flex-direction: column; gap: .75rem; min-width: 0; padding: 1rem; border: 1px solid var(--vf-light); border-radius: .8rem; }
    .loan-correction-notice { padding: .85rem; border: 1px solid #e8bb73; border-radius: .65rem; background: #fff8eb; color: #623b08; }
    .loan-correction-notice h3 { margin: 0 0 .35rem; color: #754406; font-size: .95rem; font-weight: 800; }
    .loan-correction-notice p { margin: 0; font-size: .84rem; line-height: 1.45; }
    .loan-correction-reason { margin-top: .65rem; padding-top: .6rem; border-top: 1px solid #e8bb73; font-size: .84rem; overflow-wrap: anywhere; }
    .loan-card-section { display: grid; gap: .65rem; min-width: 0; padding-bottom: .8rem; border-bottom: 1px solid var(--vf-light); }
    .loan-card-section-title { margin: 0; color: var(--vf-navy); font-size: .82rem; line-height: 1.35; font-weight: 800; }
    .loan-card-grid { display: grid; gap: .8rem 1rem; min-width: 0; align-items: start; }
    .loan-card-grid-two { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .loan-card-grid-three { grid-template-columns: repeat(3,minmax(0,1fr)); }
    .loan-card-field { display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; gap: .3rem; min-width: 0; }
    .loan-card-label { min-width: 0; color: var(--vf-navy); font-size: .75rem; line-height: 1.4; font-weight: 700; text-align: left; overflow-wrap: anywhere; }
    .loan-card-value { min-width: 0; color: var(--vf-navy); font-size: .875rem; line-height: 1.4; font-weight: 600; text-align: left; overflow-wrap: anywhere; }
    .loan-card-details { display: grid; gap: .2rem; min-width: 0; align-content: start; }

    @container (max-width: 350px) { .loan-card-grid-two { grid-template-columns: minmax(0,1fr); } }
    .loan-application-message { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; padding: .5rem .65rem; border: 1px solid var(--vf-light); border-radius: .6rem; background: var(--vf-background); font-size: .8rem; }
    .loan-application-status { display: inline-block; padding: .2rem .5rem; border-radius: 999px; font-weight: 700; white-space: nowrap; }
    .loan-status-pending { color: #8a4900; background: #fff0d3; }
    .loan-status-approved { color: #116534; background: #ddf5e5; }
    .loan-status-rejected { color: #a31c28; background: #fde4e6; }
    .loan-breakdown-button { margin-left: auto; padding: .25rem .45rem; font-size: .75rem; white-space: nowrap; transition: box-shadow .2s ease, transform .2s ease; }
    .loan-breakdown-button:hover { transform: translateY(-1px); }
    .loan-breakdown-button:active { transform: translateY(0); }
    body:has(#loanBreakdownModal.show) .modal-backdrop.show { background-color: #062b80; opacity: .48; backdrop-filter: blur(4px); }
    .loan-breakdown h3 { font-size: .95rem; font-weight: 800; margin: 0 0 .65rem; }
    .loan-financial-cards { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); align-items: start; gap: .875rem; margin-bottom: 1.25rem; }
    .loan-financial-card { min-width: 0; padding: 1.125rem; border: 1px solid var(--vf-light); border-radius: .75rem; background: #f7fbff; }
    .loan-financial-card h3 { margin: 0 0 .85rem; color: var(--vf-navy); }
    .loan-financial-rows { display: grid; gap: .7rem; margin: 0; }
    .loan-financial-rows > div { display: grid; grid-template-columns: minmax(0,1fr) max-content; align-items: baseline; gap: .75rem; min-width: 0; }
    .loan-financial-rows dt { min-width: 0; color: var(--wb-muted); font-size: .8rem; line-height: 1.35; font-weight: 600; overflow-wrap: anywhere; }
    .loan-financial-rows dd { margin: 0; color: var(--vf-navy); font-size: .85rem; line-height: 1.35; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .loan-applicant-card .loan-financial-rows dd { white-space: normal; overflow-wrap: anywhere; }
    .loan-financial-rows .loan-financial-highlight { padding: .35rem .5rem; margin: -.35rem -.5rem; border-radius: .4rem; background: var(--vf-background); }
    .loan-financial-highlight :is(dt,dd), .loan-financial-total :is(dt,dd) { font-weight: 800; }
    .loan-financial-rows .loan-financial-total { padding-top: .7rem; border-top: 1px solid var(--vf-light); }
    .loan-breakdown-table { font-size: .82rem; table-layout: fixed; width: 100%; }
    .loan-breakdown-table :is(th,td) { padding: .5rem; vertical-align: middle; }
    .loan-breakdown-number { text-align: right; font-variant-numeric: tabular-nums; }
    .loan-breakdown-date { text-align: center; }
    .loan-breakdown-total { font-weight: 800; background: var(--vf-background); }
    .loan-breakdown-total > * { background-color: var(--vf-background) !important; }

    @media (max-width: 767.98px) {
        .loan-financial-cards { grid-template-columns: minmax(0,1fr); }
        #loanBreakdownModal .loan-breakdown-table { min-width: 0; max-width: 100%; }
        .loan-breakdown-table, .loan-breakdown-table tbody, .loan-breakdown-table tr, .loan-breakdown-table td { display: block; width: 100%; }
        .loan-breakdown-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
        .loan-breakdown-table tr { padding: .6rem; margin-bottom: .6rem; border: 1px solid var(--vf-light); border-radius: .6rem; }
        .loan-breakdown-table td { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: .6rem; padding: .2rem 0; border: 0; text-align: right; overflow-wrap: anywhere; }
        .loan-breakdown-table td::before { content: attr(data-label); text-align: left; font-weight: 700; }
        .loan-breakdown-table td.loan-breakdown-date { text-align: center; }
        .loan-breakdown-table tfoot { display: block; width: 100%; }
        .loan-breakdown-table tfoot tr { display: grid; grid-template-columns: minmax(0,1fr) auto; align-items: baseline; gap: .6rem; }
        .loan-breakdown-table tfoot :is(th,td) { display: block; width: auto; padding: .2rem 0; }
        .loan-breakdown-table tfoot td::before { content: none; }

    }
    @media (prefers-reduced-motion: reduce) { .loan-breakdown-button { transition: none; } .loan-breakdown-button:hover { transform: none; } }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <x-loan-disconnection-notices :consumer="auth()->user()->consumer" />
            <div class="page-heading">
                <div>
                    <h1>Loan Management</h1>
                    <p>Capture applications, evaluate consumer repayment profile, and monitor upcoming installments.</p>
                </div>
                @if ($canApplyForLoan)
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loanModal"><i class="bi bi-journal-check me-2"></i>Apply for Loan</button>
                @else
                    <button class="btn btn-secondary" type="button" disabled aria-describedby="loanIneligibleMessage"><i class="bi bi-lock me-2"></i>Apply Loan Unavailable</button>
                @endif
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6"><div class="dashboard-card p-4 h-100"><div class="text-secondary small">Approved Loans</div><h2 class="fw-bold mt-2 mb-1">{{ number_format($stats['approved']) }}</h2><span class="badge badge-soft-success">Active portfolio</span></div></div>
                <div class="col-md-6"><div class="dashboard-card p-4 h-100"><div class="text-secondary small">Pending Review</div><h2 class="fw-bold mt-2 mb-1">{{ number_format($stats['pending']) }}</h2><span class="badge badge-soft-warning">Awaiting action</span></div></div>
            </div>

            <div class="consumer-loans-main-grid loan-layout">
                <div class="consumer-loans-form-column">
                    <div class="panel-card loan-overview-card p-4">
                        @if (session('success'))
                            <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-check-circle mt-1"></i>
                                <div>{{ session('success') }}</div>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-exclamation-circle mt-1"></i>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        @endif

                        @unless ($loansTableExists)
                            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-tools mt-1"></i>
                                <div>Loans is installed in code but the database table is not created yet. Run <code>php artisan migrate</code> to enable it.</div>
                            </div>
                        @endunless

                        <div class="section-title">Loan Application Form</div>
                        @if ($hasBlockingLoan)
                            <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert" id="loanIneligibleMessage">
                                <i class="bi bi-lock mt-1"></i>
                                <div>
                                    <div class="fw-semibold">A loan application is already pending review.</div>
                                    <div>Wait for its decision before submitting another application.</div>
                                </div>
                            </div>
                        @elseif (! $loanCostsAvailable)
                            <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert" id="loanIneligibleMessage">
                                <i class="bi bi-lock mt-1"></i>
                                <div>The installation cost is not configured yet. Please contact the administrator.</div>
                            </div>
                        @else
                            <p class="text-secondary">Registered member accounts may apply. DSS and staff review actual income evidence, payment records, and account history after submission.</p>
                            <div class="d-grid gap-2 loan-terms">
                                <div class="d-flex justify-content-between"><span class="text-secondary">Interest</span><strong>0%</strong></div>
                                <div class="d-flex justify-content-between"><span class="text-secondary">Repayment terms</span><strong>3, 6, or 12 months</strong></div>
                                <div class="small text-secondary loan-verification-note">Your proof of income will be reviewed by an administrator.</div>
                            </div>
                            @if ($loanCostsAvailable)
                                <div class="loan-cost-breakdown mt-4">
                                    <div class="fw-bold">Installation Cost Breakdown</div>
                                    <div class="loan-cost-row"><span class="text-secondary">Water Meter</span><strong>₱{{ number_format((float) $loanCostBreakdown['meter'], 2) }}</strong></div>
                                    <div class="loan-cost-row"><span class="text-secondary">Hose</span><strong>₱{{ number_format((float) $loanCostBreakdown['hose'], 2) }}</strong></div>
                                    <div class="loan-cost-row"><span class="text-secondary">Other Materials</span><strong>₱{{ number_format((float) $loanCostBreakdown['other_materials'], 2) }}</strong></div>
                                    <div class="loan-cost-row total"><span>Total Installation Cost</span><strong>₱{{ number_format((float) $loanCostBreakdown['total_installation_cost'], 2) }}</strong></div>
                                </div>
                            @else
                                <div class="alert alert-warning mt-4 mb-0" id="loanIneligibleMessage">
                                    The installation cost is currently unavailable. Please contact the administrator.
                                </div>
                            @endif
                        @endif
                    </div>

                </div>

                <div class="consumer-loans-queue-column">
                    <div class="panel-card p-4">
                        <div class="loan-queue-header mb-3">
                            <div class="section-title mb-0">Application Queue</div>
                            <form method="GET" action="{{ route('loans.index') }}" class="loans-filter-form" data-auto-filter data-auto-filter-debounce="450">
                                <div class="loans-filter-group">
                                    
                                    <div class="d-flex align-items-center gap-2 status-filter-display" style="min-width:0"><select aria-label="Status filter" data-status-filter data-clearable-filter style="min-width:0;flex:1 1 0" class="form-select form-select-sm" id="loan_filter_status" name="status">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
<button type="submit" name="status" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear status filter">Clear</button>
</div>
                                </div>

                                <noscript>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Apply filters</button>
                                </noscript>

                            </form>
                        </div>
                        @include('consumer.loans.application-queue')
                        @if ($loans->total() > 0)
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <p class="text-secondary mb-0">Showing {{ $loans->firstItem() }} to {{ $loans->lastItem() }} of {{ $loans->total() }} loan applications</p>
                                {{ $loans->links() }}
                            </div>
                        @endif
                    </div>


                </div>
            </div>
        </div>
    </main>
</div>

@if ($canApplyForLoan)
<div class="modal fade" id="loanModal" tabindex="-1" aria-labelledby="loanModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="loanModalTitle">Apply for Loan</h2>
                    <div class="small text-secondary">Complete each section before submitting for DSS and admin review.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('loans.store') }}" id="loanCreateForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="confirmation_token" id="loan_confirmation_token" value="">
                    <div id="loanApplicationStep">
                    <div class="loan-application-flow">
                        <section class="loan-form-step" aria-labelledby="loan-step-account">
                            <span class="loan-step-number" aria-hidden="true">1</span>
                            <div class="loan-step-body">
                                <h3 class="loan-step-title" id="loan-step-account">Account Review</h3>
                                <p class="loan-step-copy">Confirm which account will be evaluated for loan eligibility.</p>
                                @if ($canManageLoans)
                                    <label class="form-label fw-semibold" for="consumer_id">Borrower Account</label>
                                    <select class="form-select" id="consumer_id" name="consumer_id" required aria-describedby="consumer_id_help">
                                        <option value="">Select consumer</option>
                                        @foreach ($consumers as $consumer)
                                            <option value="{{ $consumer->id }}" @selected((string) old('consumer_id') === (string) $consumer->id)>{{ $consumer->account_number }} - {{ $consumer->full_name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="loan-field-help" id="consumer_id_help">Choose the consumer whose billing, payment, and consumption history should be checked.</div>
                                @else
                                    <div class="loan-form-summary">
                                        This application will use your current consumer account and DSS eligibility status.
                                    </div>
                                @endif
                            </div>
                        </section>

                        <section class="loan-form-step" aria-labelledby="loan-step-income">
                            <span class="loan-step-number" aria-hidden="true">2</span>
                            <div class="loan-step-body">
                                <h3 class="loan-step-title" id="loan-step-income">Proof of Income</h3>
                                <p class="loan-step-copy">Upload a clear copy of your proof of income. Your monthly income will be reviewed and verified by the administrator.</p>
                                @include('shared.income-proof-upload', ['uploadId' => 'proof_of_income'])
                                @error('proof_of_income') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </section>

                        <section class="loan-form-step" aria-labelledby="loan-step-term">
                            <span class="loan-step-number" aria-hidden="true">3</span>
                            <div class="loan-step-body">
                                <h3 class="loan-step-title" id="loan-step-term">Repayment Plan</h3>
                                <p class="loan-step-copy">Select how long you need to repay the loan.</p>
                                <label class="form-label fw-semibold" for="term_months">Preferred Repayment Term</label>
                                <select class="form-select" id="term_months" name="term_months" required aria-describedby="term_months_help">
                                    <option value="3" @selected(old('term_months') == '3')>3 months</option>
                                    <option value="6" @selected(old('term_months', '6') == '6')>6 months</option>
                                    <option value="12" @selected(old('term_months') == '12')>12 months</option>
                                </select>
                                <div class="loan-field-help" id="term_months_help">Shorter terms mean fewer months to repay; longer terms spread payment over more months.</div>
                                <div
                                    class="loan-payment-schedule mt-3"
                                    data-costs-available="{{ $loanCostsAvailable ? 'true' : 'false' }}"
                                    data-loan-breakdown='@json($loanCostBreakdown)'
                                    data-loan-schedules='@json($loanInstallmentSchedules)'
                                    data-loan-total="{{ number_format((float) $loanCostBreakdown['total_installation_cost'], 2, '.', '') }}"
                                >
                                    <div class="loan-breakdown-loading d-flex align-items-center gap-2">
                                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                        <span>Loading installation costs...</span>
                                    </div>
                                    <div class="loan-breakdown-unavailable alert alert-warning mb-0" hidden>
                                        The installation cost is currently unavailable. Please contact the administrator.
                                    </div>
                                    <div class="loan-breakdown-content" hidden>
                                        <div class="fw-bold">Installation Cost Breakdown</div>
                                        <div class="loan-cost-row"><span>Water Meter</span><strong id="loanCostMeter">-</strong></div>
                                        <div class="loan-cost-row"><span>Hose</span><strong id="loanCostHose">-</strong></div>
                                        <div class="loan-cost-row"><span>Other Materials</span><strong id="loanCostOtherMaterials">-</strong></div>
                                        <div class="loan-cost-row total"><span>Total Installation Cost</span><strong id="loanCostTotal">-</strong></div>
                                        <div class="loan-cost-row"><span>Selected Term</span><strong id="selectedLoanTerm">6 months</strong></div>
                                        <div class="loan-cost-row"><span>Estimated Monthly Installment</span><strong id="estimatedMonthlyInstallment">-</strong></div>
                                        <div class="fw-bold">Monthly Payment Schedule:</div>
                                        <div id="loanScheduleRows" class="d-grid gap-2"></div>
                                        <div class="loan-cost-row loan-schedule-total"><span>Total Repayment</span><strong id="loanScheduleTotal">-</strong></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="loan-form-step" aria-labelledby="loan-step-purpose">
                            <span class="loan-step-number" aria-hidden="true">4</span>
                            <div class="loan-step-body">
                                <h3 class="loan-step-title" id="loan-step-purpose">Purpose and Review Notes</h3>
                                <p class="loan-step-copy">Briefly explain why you need this loan so reviewers understand the request.</p>
                                <label class="form-label fw-semibold" for="purpose">Loan Purpose</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="4" maxlength="1000" required aria-describedby="purpose_help">{{ old('purpose') }}</textarea>
                                <div class="loan-field-help" id="purpose_help">Example: water connection materials, emergency repair, or account support. Maximum 1000 characters.</div>
                            </div>
                        </section>
                    </div>
                    </div>
                    <section id="loanTermsStep" aria-labelledby="loanTermsTitle" hidden>
                        <h3 class="h5 fw-bold mb-3" id="loanTermsTitle">{{ \App\Support\LoanTerms::TITLE }}</h3>
                        <p class="loan-selected-term">Selected repayment term: <strong id="loan_selected_agreement_term">{{ old('term_months', '6') }} months</strong></p>
                        <div class="loan-agreement-text">
                            @foreach (\App\Support\LoanTerms::paragraphs() as $paragraph)
                                <p>@if (\App\Support\LoanTerms::isEmphasized($paragraph))<strong>{{ $paragraph }}</strong>@else{{ $paragraph }}@endif</p>
                            @endforeach
                        </div>
                        <div class="form-check loan-terms-consent">
                            <input class="form-check-input" type="checkbox" name="terms_accepted" id="loan_terms_accepted" value="1" required>
                            <label class="form-check-label fw-semibold" for="loan_terms_accepted">I have read and agree to the Loan Meter Application Terms and Conditions.</label>
                        </div>
                        @error('terms_accepted') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </section>
                    <section id="loanConfirmationStep" aria-labelledby="loanConfirmationTitle" hidden>
                        <h3 class="h5 fw-bold mb-3" id="loanConfirmationTitle">Confirm Submission</h3>
                        <p class="loan-confirmation-copy">Are you sure you want to submit your loan application?</p>
                        <p class="small text-danger mt-3 mb-0" id="loan_confirmation_error" role="alert" hidden></p>
                    </section>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" id="loanCloseButton" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="loanNextButton">Next</button>
                <button type="button" class="btn btn-light border" id="loanTermsBackButton" hidden>Back</button>
                <button type="button" class="btn btn-primary" id="loanTermsSubmitButton" disabled hidden>Submit</button>
                <button type="button" class="btn btn-light border" id="loanConfirmCancelButton" hidden>Cancel</button>
                <button type="button" class="btn btn-primary" id="loanConfirmButton" hidden>Confirm Submission</button>
            </div>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="loanBreakdownModal" tabindex="-1" aria-labelledby="loanBreakdownTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="loanBreakdownTitle">Payment Breakdown</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close payment breakdown"></button></div>
            <div class="modal-body" id="loanBreakdownBody" aria-live="polite"></div>
        </div>
    </div>
</div>
@include('shared.income-proof-quality')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loanCreateForm');
    if (!form) return;
    const modal = document.getElementById('loanModal');
    const application = document.getElementById('loanApplicationStep');
    const terms = document.getElementById('loanTermsStep');
    const confirmation = document.getElementById('loanConfirmationStep');
    const accepted = document.getElementById('loan_terms_accepted');
    const token = document.getElementById('loan_confirmation_token');
    const term = document.getElementById('term_months');
    const next = document.getElementById('loanNextButton');
    const close = document.getElementById('loanCloseButton');
    const back = document.getElementById('loanTermsBackButton');
    const submit = document.getElementById('loanTermsSubmitButton');
    const cancel = document.getElementById('loanConfirmCancelButton');
    const confirm = document.getElementById('loanConfirmButton');
    const error = document.getElementById('loan_confirmation_error');
    let confirmed = false;
    let submitting = false;

    function showStep(step) {
        application.hidden = step !== 'application';
        terms.hidden = step !== 'terms';
        confirmation.hidden = step !== 'confirmation';
        close.hidden = step !== 'application';
        next.hidden = step !== 'application';
        back.hidden = step !== 'terms';
        submit.hidden = step !== 'terms';
        cancel.hidden = step !== 'confirmation';
        confirm.hidden = step !== 'confirmation';
        modal.querySelector('.modal-body').scrollTop = 0;
    }

    function updateTerms() {
        document.getElementById('loan_selected_agreement_term').textContent = term.value + ' months';
    }

    term.addEventListener('change', function () {
        accepted.checked = false;
        submit.disabled = true;
        token.value = '';
        updateTerms();
    });
    accepted.addEventListener('change', function () {
        submit.disabled = !accepted.checked;
        token.value = '';
    });
    next.addEventListener('click', function () {
        for (const field of application.querySelectorAll('input, select, textarea')) {
            if (!field.disabled && !field.checkValidity()) {
                field.reportValidity();
                return;
            }
        }
        updateTerms();
        showStep('terms');
    });
    back.addEventListener('click', () => showStep('application'));
    submit.addEventListener('click', function () {
        if (!accepted.checked || submitting) return;
        error.hidden = true;
        showStep('confirmation');
    });
    cancel.addEventListener('click', function () {
        if (!submitting) showStep('terms');
    });
    form.addEventListener('submit', function (event) {
        if (!confirmed || !token.value || !accepted.checked) event.preventDefault();
    });
    confirm.addEventListener('click', async function () {
        if (submitting || !accepted.checked) return;
        submitting = true;
        confirm.disabled = true;
        cancel.disabled = true;
        error.hidden = true;
        try {
            const response = await fetch('{{ route('loans.confirm-submission', [], false) }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: JSON.stringify({ terms_accepted: 1, confirmed: 1, term_months: term.value }),
            });
            const result = await response.json();
            if (!response.ok || !result.confirmation_token) throw new Error('Unable to confirm the application. Please try again.');
            token.value = result.confirmation_token;
            confirmed = true;
            form.requestSubmit();
        } catch (exception) {
            submitting = false;
            confirm.disabled = false;
            cancel.disabled = false;
            error.textContent = exception.message || 'Unable to confirm the application. Please try again.';
            error.hidden = false;
        }
    });
    modal.addEventListener('hidden.bs.modal', function () {
        if (submitting) return;
        confirmed = false;
        token.value = '';
        showStep('application');
    });
    updateTerms();
});
</script>
<script>
(() => {
    const modal = document.getElementById('loanBreakdownModal');
    const body = document.getElementById('loanBreakdownBody');
    let request = null;
    modal?.addEventListener('show.bs.modal', async (event) => {
        const url = event.relatedTarget?.dataset.breakdownUrl;
        if (!url) { event.preventDefault(); return; }
        request?.abort(); request = new AbortController(); const activeRequest = request;
        body.textContent = 'Loading payment breakdown…';
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: activeRequest.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Request failed');
            const content = await response.text();
            if (request === activeRequest) body.innerHTML = content;
        } catch (error) {
            if (error.name !== 'AbortError' && request === activeRequest) body.textContent = 'Unable to load this payment breakdown. Please close and try again.';
        }
    });
    modal?.addEventListener('hidden.bs.modal', () => { request?.abort(); request = null; body.textContent = ''; });
})();
</script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const termSelect = document.getElementById('term_months');
            const schedulePanel = document.querySelector('.loan-payment-schedule');
            const scheduleRows = document.getElementById('loanScheduleRows');
            const selectedTerm = document.getElementById('selectedLoanTerm');
            const estimatedMonthlyInstallment = document.getElementById('estimatedMonthlyInstallment');
            const scheduleTotal = document.getElementById('loanScheduleTotal');
            const loadingState = document.querySelector('.loan-breakdown-loading');
            const breakdownContent = document.querySelector('.loan-breakdown-content');
            const unavailableState = document.querySelector('.loan-breakdown-unavailable');

            if (!termSelect || !schedulePanel || !scheduleRows || !selectedTerm || !estimatedMonthlyInstallment || !scheduleTotal) {
                return;
            }

            const costsAvailable = schedulePanel.dataset.costsAvailable === 'true';
            const breakdown = JSON.parse(schedulePanel.dataset.loanBreakdown || '{}');
            const schedules = JSON.parse(schedulePanel.dataset.loanSchedules || '{}');
            const total = Number(schedulePanel.dataset.loanTotal || 0);
            const money = new Intl.NumberFormat('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const renderSchedule = () => {
                const term = termSelect.value;
                const rows = schedules[term] || [];
                const firstInstallment = rows.length > 0 ? Number(rows[0].amount || 0) : 0;

                selectedTerm.textContent = `${term} months`;
                estimatedMonthlyInstallment.textContent = `₱${money.format(firstInstallment)}`;
                scheduleRows.innerHTML = rows.map((row) => (
                    `<div class="loan-schedule-row"><span>Month ${row.month}</span><strong>₱${money.format(Number(row.amount || 0))}</strong></div>`
                )).join('');
                scheduleTotal.textContent = `₱${money.format(total)}`;
            };

            window.setTimeout(() => {
                if (loadingState) {
                    loadingState.hidden = true;
                }

                if (!costsAvailable) {
                    if (unavailableState) {
                        unavailableState.hidden = false;
                    }
                    termSelect.disabled = true;
                    return;
                }

                document.getElementById('loanCostMeter').textContent = `₱${money.format(Number(breakdown.meter || 0))}`;
                document.getElementById('loanCostHose').textContent = `₱${money.format(Number(breakdown.hose || 0))}`;
                document.getElementById('loanCostOtherMaterials').textContent = `₱${money.format(Number(breakdown.other_materials || 0))}`;
                document.getElementById('loanCostTotal').textContent = `₱${money.format(total)}`;

                if (breakdownContent) {
                    breakdownContent.hidden = false;
                }

                termSelect.addEventListener('change', renderSchedule);
                renderSchedule();
            }, 0);
        });
    </script>

    @if ($errors->any() && $canApplyForLoan)
        <script>
            const loanModalElement = document.getElementById('loanModal');
            if (loanModalElement) {
                const loanModal = new bootstrap.Modal(loanModalElement);
                loanModal.show();
            }
        </script>
    @endif
@endpush
