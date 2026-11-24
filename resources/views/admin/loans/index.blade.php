@extends('layouts.app')

@php
    $activePage = 'loans';
@endphp

@section('title', 'Loans')

@push('styles')
<style>
    .application-queue-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        background: #fff !important;
    }

    .application-queue-filter-form {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-left: auto;
    }

    .application-queue-filter-form .form-control,
    .application-queue-filter-form .form-select {
        width: auto;
        min-width: 160px;
        background: #fff !important;
    }

    .application-queue-filter-form .btn {
        background: #fff !important;
    }

    .application-queue-filter-form .form-control[name="search"] {
        min-width: 240px;
    }

    .loan-application-flow {
        display: grid;
        gap: 1rem;
    }

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

    .loan-cost-breakdown {
        display: grid;
        gap: 0.55rem;
        padding: 0.9rem 1rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 0.8rem;
        background: #fff;
    }

    .loan-cost-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
    }

    .loan-cost-row.total {
        padding-top: 0.55rem;
        border-top: 1px solid rgba(216, 237, 255, 0.95);
        font-weight: 800;
    }

    .loan-admin-facts { display: grid; gap: .65rem; }
    .loan-admin-facts > div { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding-bottom: .55rem; border-bottom: 1px solid var(--vf-background); }
    .loan-admin-facts > div:last-child { border-bottom: 0; padding-bottom: 0; }
    .loan-admin-facts span { color: var(--wb-muted); }
    .loan-admin-facts strong { text-align: right; }
    .loan-operational-table { font-size: .88rem; }
    .income-proof-preview img { display: block; width: 100%; max-height: 360px; object-fit: contain; border: 1px solid #dee2e6; border-radius: .5rem; background: #f8fafc; }
    .loan-operational-table thead th { background: #f3f7f8; color: var(--vf-navy); font-size: .75rem; white-space: nowrap; }
    .loan-operational-table tbody td { vertical-align: middle; }

    .application-queue-table th,
    .application-queue-table td {
        white-space: nowrap;
    }

    .application-queue-table td:first-child,
    .application-queue-table td:nth-child(2),
    .application-queue-table td:nth-child(6) {
        white-space: normal;
        min-width: 11rem;
    }

    @media (max-width: 767.98px) {
        .application-queue-toolbar {
            align-items: stretch;
        }

        .application-queue-filter-form {
            width: 100%;
            margin-left: 0;
            justify-content: stretch;
        }

        .application-queue-filter-form .form-control,
        .application-queue-filter-form .form-select,
        .application-queue-filter-form .btn {
            width: 100%;
            min-width: 0;
        }

        .loan-form-step {
            grid-template-columns: 1fr;
        }
    }
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
    .dss-detail-section + .dss-detail-section { border-top: 1px solid #dbe5f5; margin-top: 1rem; padding-top: 1rem; }
    .dss-detail-section h3 { color: #12376f; font-size: .95rem; font-weight: 700; }
    .dss-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .dss-detail-grid > div { display:grid; gap:.2rem; min-width:0; }
    .dss-detail-label { color:#52647c; font-size:.8rem; font-weight:600; }
    .dss-detail-financial { display:grid; gap:.5rem; }
    .dss-detail-financial > div { display:grid; grid-template-columns:minmax(0,1fr) max-content; gap:1rem; }
    .dss-detail-financial dd { margin:0; text-align:right; font-variant-numeric:tabular-nums; }
    .dss-proof-preview img { max-width:100%; max-height:300px; object-fit:contain; }
    @media (max-width:575px) { .dss-detail-grid { grid-template-columns:1fr; } }
    .loan-tab-nav { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem; }
    .loan-tab-nav a { display:inline-block; padding:.5rem 1rem; border:1px solid var(--vf-royal, #164ea0); border-radius:.5rem; color:var(--vf-royal, #164ea0); background:#fff; font-weight:700; text-decoration:none; }
    .loan-tab-nav a.active { color:#fff; background:var(--vf-royal, #164ea0); }
    body.loan-details-open .modal-backdrop {
        background-color: rgba(0, 0, 0, .35);
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }
    #adminLoanDetails, #adminLoanDetails .modal-dialog, #adminLoanDetails .modal-content {
        filter: none;
        -webkit-backdrop-filter: none;
        backdrop-filter: none;
    }
    #adminLoanDetails { z-index: 1060; }
    #adminLoanDetails .modal-content { background: #fff; opacity: 1; }
    .loan-terms strong, .loan-admin-facts strong, .loan-number, input[type=number] { text-align: right; font-variant-numeric: tabular-nums; }
    .loan-records .table .loan-number, .loan-operational-table .loan-number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .loan-records .table .loan-number::before { text-align: left; white-space: normal; }
    @media (max-width: 360px) {
        .loan-cost-row, .loan-schedule-row, .loan-income-row { gap: .5rem; }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const element = document.getElementById('adminLoanDetails');
    if (!element || !window.bootstrap) return;
    document.body.appendChild(element);
    element.addEventListener('show.bs.modal', () => document.body.classList.add('loan-details-open'));
    element.addEventListener('hidden.bs.modal', () => document.body.classList.remove('loan-details-open'));
    const modal = bootstrap.Modal.getOrCreateInstance(element), body = document.getElementById('adminLoanDetailsBody');
    document.querySelectorAll('[data-admin-loan]').forEach(button => button.addEventListener('click', function () {
        const id = button.dataset.adminLoan, template = document.getElementById('admin-loan-details-' + id);
        if (!template) return;
        body.replaceChildren(template.content.cloneNode(true));
        element.dataset.focusTarget = button.dataset.adminFocus ? button.dataset.adminFocus + id : '';
        modal.show();
    }));
    element.addEventListener('shown.bs.modal', function () { if (element.dataset.focusTarget) document.getElementById(element.dataset.focusTarget)?.focus(); });
    document.querySelectorAll('template[data-combined-modal]').forEach(template => document.body.append(template.content.cloneNode(true)));
    document.querySelectorAll('.combined-loan-form').forEach(form => form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = form.querySelector('button[type=submit]'), error = form.querySelector('[role=alert]');
        button.disabled = true; error.hidden = true;
        try {
            const response = await fetch(form.action, { method:'POST', body:new FormData(form), headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }, credentials:'same-origin' });
            const result = await response.json();
            if (!response.ok) { error.textContent = result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'Could not save the assignment.'); error.hidden = false; button.disabled = false; return; }
            window.location.reload();
        } catch (_) { error.textContent = 'Could not confirm the result. Refresh the page before trying again.'; error.hidden = false; button.disabled = false; }
    }));
    const reopen = @json(session('meter_assignment_loan_id'));
    if (reopen) document.querySelector('[data-admin-loan="' + String(reopen).replace(/[^0-9]/g, '') + '"][data-admin-focus="meter-number-"]')?.click();
});
</script>
@endpush
@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading" data-admin-records-root>
                <div>
                    <h1>Loan Management</h1>
                    <p>Manage scheduled installations, loan servicing, and repayments. Saved application decisions and evidence remain in DSS Loan.</p>
                </div>
            </div>

            <nav class="loan-tab-nav" aria-label="Loan application categories">
                @foreach (['all' => 'ALL', 'pending' => 'PENDING', 'costs' => 'SET COST'] as $tabKey => $tabLabel)
                    <a href="{{ route('loans.index', ['tab' => $tabKey]) }}" class="{{ $loanTab === $tabKey ? 'active' : '' }}" @if ($loanTab === $tabKey) aria-current="page" @endif>{{ $tabLabel }}</a>
                @endforeach
            </nav>
            @if (session('agreement_status'))
                <div class="alert alert-info" role="status">{{ session('agreement_status') }}</div>
            @endif
            @error('agreement')
                <div class="alert alert-danger" role="alert">{{ $message }}</div>
            @enderror
            @if (session('income_verification_message'))
                <div class="alert alert-info" role="status">{{ session('income_verification_message') }}</div>
            @endif
            @if ($loanTab === 'pending')
                <nav class="loan-tab-nav" aria-label="Pending loan requests">
                    <a href="{{ route('loans.index', ['tab' => 'pending', 'pending' => 'income']) }}" class="{{ $pendingTab === 'income' ? 'active' : '' }}" @if ($pendingTab === 'income') aria-current="page" @endif>Income Verification</a>
                    <a href="{{ route('loans.index', ['tab' => 'pending', 'pending' => 'reschedule']) }}" class="{{ $pendingTab === 'reschedule' ? 'active' : '' }}" @if ($pendingTab === 'reschedule') aria-current="page" @endif>Installation Reschedule</a>
                </nav>
            @endif
            @if ($loanTab === 'all')
            @if (session('email_error'))
                <div class="alert alert-warning" role="alert">{{ session('email_error') }}</div>
            @elseif (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            <section class="panel-card p-3 p-lg-4 mb-4" aria-labelledby="loan-management-title">
                <h2 id="loan-management-title" class="section-title mb-3">Loan Application Management</h2>
                <form method="GET" action="{{ route('loans.index') }}" class="row g-2 align-items-end mb-3" id="loan-application-filters" data-auto-filter data-auto-filter-debounce="450">
                    <input type="hidden" name="tab" value="all">
                    <div class="col-md-4 col-12">
                        <label class="form-label fw-semibold small text-secondary" for="application-search">Search</label>
                        <input class="form-control" id="application-search" type="search" name="application_search" value="{{ $applicationSearch }}" placeholder="Consumer name or account number">
                    </div>
                    <div class="col-md-3 col-12">
                        <label class="form-label fw-semibold small text-secondary" for="application-status">Loan Status</label>
                        <select class="form-select" id="application-status" name="application_status">
                            @foreach (['all' => 'All Status', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                <option value="{{ $value }}" @selected($applicationStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-12">
                        <label class="form-label fw-semibold small text-secondary" for="installation_status">Installation Status</label>
                        <select class="form-select" id="installation_status" name="installation_status">
                            <option value="all" @selected($installationStatus === 'all')>All Installations</option>
                            <option value="pending" @selected($installationStatus === 'pending')>Pending Installation</option>
                            <option value="completed" @selected($installationStatus === 'completed')>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-12 d-flex gap-2">
                        <noscript><button type="submit" class="btn btn-primary w-100">Apply filters</button></noscript>
                        <a href="{{ route('loans.index', ['tab' => 'all']) }}" class="btn btn-light border w-100"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear</a>
                    </div>
                </form>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0" style="min-width: 760px">
                    <thead><tr><th>Account Number</th><th>Consumer Name</th><th>Loan Status</th><th>Income Verification Status</th><th>Installation Status</th><th>Actions</th></tr></thead><tbody>
                    @forelse ($adminApplications as $application)
                        @php($decision = in_array($application->latestDssEvaluation?->recommendation, ['Approved', 'Rejected'], true) ? $application->latestDssEvaluation->recommendation : $application->applicationStatusCategory())
                        @php($evaluation = $application->latestDssEvaluation)
                        @php($hasReschedule = $decision === 'Approved' && $application->has_pending_installation_reschedule)
                        <tr><td>{{ $application->consumer?->account_number ?? 'Not recorded' }}</td><td>{{ $application->consumer?->full_name ?? 'Consumer record unavailable' }}</td><td>{{ in_array($application->status, array_merge(\App\Models\Loan::PAYMENT_STATUSES, ['Completed']), true) && (float) $application->amount > 0 && $application->balance_remaining <= 0 ? 'Fully Paid' : $decision }}</td><td>{{ $application->income_verification_status ?: 'Pending Verification' }}</td>
                        <td>
                            <div class="consumer-installation-details">
                                @include('admin.installations.loan-status', ['loanInstallation' => $application, 'consumer' => $application->consumer])
                            </div>
                        </td>
                        <td><div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-admin-loan="{{ $application->id }}">View Details</button>
                            @if ($decision === 'Approved' && $application->latestDssEvaluation?->recommendation === 'Approved' && $application->consumer && $application->status === 'Ready for Scheduling' && ! $application->installation_date && ! $hasReschedule)
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#combinedLoanSchedule{{ $application->id }}">Assign Meter &amp; Schedule Installation</button>
                            @endif
                            @if ($application->installation_date && $application->installation_time && $application->consumer && (int) $retryableInstallationEmails->get($application->initialInstallationEmailEvent()) === (int) $application->consumer_id)
                                <form method="POST" action="{{ route('loans.installation-email.retry', $application) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Retry Installation Email</button>
                                </form>
                            @endif
                        </div></td></tr>
                        @if ($decision === 'Approved' && $application->consumer && $application->status === 'Ready for Scheduling' && ! $application->installation_date && ! $hasReschedule)
                        <template data-combined-modal><div class="modal fade" id="combinedLoanSchedule{{ $application->id }}" tabindex="-1" aria-labelledby="combinedLoanTitle{{ $application->id }}" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                            <div class="modal-header"><h2 class="modal-title fs-5" id="combinedLoanTitle{{ $application->id }}">Assign Meter &amp; Schedule Installation</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                            <form method="POST" action="{{ route('loans.assign-and-schedule', $application) }}" class="combined-loan-form">@csrf
                                <div class="modal-body"><div class="alert alert-danger" role="alert" hidden></div><h3 class="fs-6 fw-bold">Consumer Information</h3><dl class="row small"><dt class="col-5">Consumer Name</dt><dd class="col-7">{{ $application->consumer->full_name }}</dd><dt class="col-5">Account Number</dt><dd class="col-7">{{ $application->consumer->account_number }}</dd><dt class="col-5">Registered Email</dt><dd class="col-7">{{ $application->consumer->email ?: 'Not recorded' }}</dd></dl>
                                <h3 class="fs-6 fw-bold">Meter Assignment</h3><div class="mb-3"><label class="form-label" for="combined-meter-{{ $application->id }}">New Meter Number</label><input class="form-control" id="combined-meter-{{ $application->id }}" name="meter_number" maxlength="50" required></div>
                                <h3 class="fs-6 fw-bold">Installation Schedule (Philippine Time)</h3><div class="row g-3"><div class="col-sm-6"><label class="form-label" for="combined-date-{{ $application->id }}">Installation Date</label><input class="form-control" type="date" id="combined-date-{{ $application->id }}" name="installation_date" min="{{ today(\App\Models\Loan::INSTALLATION_TIMEZONE)->toDateString() }}" required></div><div class="col-sm-6"><label class="form-label" for="combined-time-{{ $application->id }}">Installation Time</label><input class="form-control" type="time" id="combined-time-{{ $application->id }}" name="installation_time" required></div><div class="col-12"><label class="form-label" for="combined-notes-{{ $application->id }}">Installation Notes (optional)</label><textarea class="form-control" id="combined-notes-{{ $application->id }}" name="installation_notes" maxlength="1000"></textarea></div></div></div>
                                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Confirm Assignment &amp; Schedule</button></div>
                            </form>
                        </div></div></div></template>
                        @endif
                        <template id="admin-loan-details-{{ $application->id }}">@include('admin.loans._loan-detail-template', ['application' => $application, 'evaluation' => $evaluation, 'decision' => $decision, 'manageInstallation' => true])</template>
                    @empty<tr><td colspan="6" class="text-center text-secondary">No applications in this category.</td></tr>@endforelse
                    </tbody></table></div>
                <div class="mt-3">{{ $adminApplications->links() }}</div>
            </section>
            @include('admin.installations.completion-modal')
            @endif
            <div class="modal fade" id="adminLoanDetails" tabindex="-1" aria-labelledby="adminLoanDetailsTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="adminLoanDetailsTitle">Application Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="adminLoanDetailsBody"></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
            @if ($loanTab === 'pending' && $pendingTab === 'reschedule')
                <section class="panel-card p-3 p-lg-4 mb-4" aria-labelledby="loan-reschedules-title">
                    <h2 id="loan-reschedules-title" class="section-title mb-3">Loan Rescheduling Requests</h2>
                    @if (session('email_error'))
                        <div class="alert alert-warning" role="alert">{{ session('email_error') }}</div>
                    @endif
                    @forelse ($pendingLoanReschedules as $pendingRequest)
                            <div class="border rounded p-3 mb-2">
                                <div class="fw-semibold">{{ $pendingRequest->consumer?->full_name ?? 'Consumer record unavailable' }} — Loan #{{ $pendingRequest->loan_id }} <span class="badge bg-warning text-dark">Reschedule Requested</span></div>
                                <p class="small my-2">{{ $pendingRequest->reason }}</p>
                                <p class="small my-2"><strong>Assigned meter:</strong> {{ $pendingRequest->consumer?->meter_number ?: 'Not assigned' }} <span class="mx-1">·</span> <strong>Current schedule:</strong> {{ $pendingRequest->formattedOriginalSchedule() ?: 'Not scheduled' }}</p>
                                <form method="POST" action="{{ route('installation-reschedules.assign', $pendingRequest) }}" class="row g-2 align-items-end">@csrf @method('PATCH')
                                    <div class="col-sm-6"><label class="form-label" for="pending-date-{{ $pendingRequest->id }}">New installation date</label><input class="form-control" id="pending-date-{{ $pendingRequest->id }}" name="new_installation_date" type="date" min="{{ today(\App\Models\Loan::INSTALLATION_TIMEZONE)->toDateString() }}" required></div>
                                    <div class="col-sm-6"><label class="form-label" for="pending-time-{{ $pendingRequest->id }}">New installation time (Philippine Time)</label><input class="form-control" id="pending-time-{{ $pendingRequest->id }}" name="new_installation_time" type="time" required></div>
                                    <div class="col-sm-8">
                                        <label class="form-label" for="pending-notes-{{ $pendingRequest->id }}">Notes (optional)</label>
                                        <textarea class="form-control" id="pending-notes-{{ $pendingRequest->id }}" name="admin_notes" maxlength="1000" rows="1"></textarea>
                                    </div>
                                    <div class="col-sm-4"><button class="btn btn-primary" type="submit">Assign New Schedule</button></div>
                                </form>
                            </div>
                    @empty
                        <p class="text-secondary mb-0">No installation rescheduling requests are pending.</p>
                    @endforelse
                </section>
            @endif
            @if ($loanTab === 'pending' && $pendingTab === 'income')
            <section class="panel-card p-3 p-lg-4 mb-4" id="pending-income-verifications" aria-labelledby="income-verification-title">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div><h2 id="income-verification-title" class="section-title mb-1">Pending Income Verification</h2><p class="small text-secondary mb-0">Review the uploaded proof. DSS determines eligibility after verification.</p></div>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('dss.loans', ['status' => 'Needs Review']) }}">Open DSS Needs Review</a>
                </div>
                @if (session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <div class="table-responsive"><table class="table table-hover align-middle mb-0 loan-operational-table" style="min-width: 850px;">
                    <thead><tr><th>Applicant</th><th>Account Number</th><th>Proof of Income</th><th>Loan Reference</th><th>Income Verification</th><th>Submission Date</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse ($pendingIncomeVerifications as $verificationLoan)
                            <tr>
                                <td class="fw-semibold">{{ $verificationLoan->consumer?->full_name ?? 'Consumer record unavailable' }}</td>
                                <td>{{ $verificationLoan->consumer?->account_number ?? 'Not recorded' }}</td>
                                <td>@if (filled($verificationLoan->proof_of_income_path)) @if (strtolower(pathinfo($verificationLoan->proof_of_income_path, PATHINFO_EXTENSION)) === 'pdf') <a href="{{ route('loans.income-proof', $verificationLoan) }}" target="_blank" rel="noopener">View PDF</a> @else <img src="{{ route('loans.income-proof', $verificationLoan) }}" alt="Proof of income" style="max-width:96px;max-height:64px;object-fit:contain;border-radius:.4rem"> @endif @else Not uploaded @endif</td>
                                <td>Loan #{{ $verificationLoan->id }}</td>
                                <td>{{ $verificationLoan->income_verification_status === 'Verified' ? 'Verified' : ($verificationLoan->income_verification_status === 'Rejected' ? 'Rejected' : 'Pending Verification') }}</td>
                                <td class="text-nowrap">{{ $verificationLoan->applied_at?->format('M j, Y') ?? 'Not recorded' }}</td>
                                <td>
                                    @if ($verificationLoan->income_verification_status === 'Verified')
                                        <span class="badge badge-soft-warning">DSS needs review</span>
                                    @elseif (filled($verificationLoan->proof_of_income_path))
                                        <button type="button" class="btn btn-sm btn-primary text-nowrap" data-income-verification="{{ $verificationLoan->id }}" data-income-name="{{ $verificationLoan->consumer?->full_name ?? 'Loan applicant' }}">Verify Proof of Income</button>
                                    @else
                                        <span class="badge badge-soft-warning">Awaiting proof of income</span>
                                    @endif
                                </td>
                            </tr>
                            <template id="income-verification-template-{{ $verificationLoan->id }}">
                                <div class="row g-3 mb-3">
                                    <div class="col-sm-6"><strong>Account:</strong> {{ $verificationLoan->consumer?->account_number ?? 'Not recorded' }}</div>
                                    <div class="col-sm-6"><strong>Loan:</strong> #{{ $verificationLoan->id }}</div>
                                    <div class="col-sm-6"><strong>Submitted:</strong> {{ $verificationLoan->applied_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</div>
                                    <div class="col-sm-6"><strong>Income verification:</strong> {{ $verificationLoan->income_verification_status ?: 'Pending Verification' }}</div>
                                </div>

                                @if (filled($verificationLoan->proof_of_income_path))
                                    <div class="income-proof-preview mb-3" data-admin-income-proof>
                                        @if (strtolower(pathinfo($verificationLoan->proof_of_income_path, PATHINFO_EXTENSION)) !== 'pdf')
                                            <button type="button" class="income-preview-button" data-admin-enlarge aria-label="Enlarge proof of income"><img src="{{ route('loans.income-proof', $verificationLoan) }}" alt="Uploaded Proof of Income for {{ $verificationLoan->consumer?->full_name ?? 'loan applicant' }}"></button>
                                            <div class="small mt-2" data-admin-quality role="status">Checking image quality…</div>
                                        @else
                                            <iframe src="{{ route('loans.income-proof', $verificationLoan) }}" title="Proof of income PDF" style="width:100%;height:260px;border:1px solid var(--vf-light);border-radius:.5rem"></iframe>
                                            <div class="small text-secondary mt-2">PDF quality is not automatically assessed.</div>
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-admin-enlarge>Enlarge PDF</button>
                                        @endif
                                        <div class="small text-secondary mt-1">Quality is an estimate, not verification of authenticity or income.</div>
                                    </div>
                                @else
                                    <div class="alert alert-warning mb-3">No proof of income is attached to this submitted application. Keep it in Pending Review until the required evidence is available; no DSS decision will be recorded.</div>
                                @endif
                                @if (filled($verificationLoan->proof_of_income_path))
                                    <form method="POST" action="{{ route('loans.verify-income', $verificationLoan) }}" class="income-verification-form">
                                    @csrf
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-primary" type="submit" name="decision" value="confirm">Confirm Verification</button>
                                        <button class="btn btn-outline-danger" type="button" data-reject-income-url="{{ route('loans.verify-income', $verificationLoan) }}">Reject Verification</button>
                                    </div>
                                    </form>
                                @endif
                            </template>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary py-4">No applications are waiting for income verification.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                @if ($pendingIncomeVerifications->hasPages())<div class="mt-3">{{ $pendingIncomeVerifications->links() }}</div>@endif
            </section>

            @endif
            @if ($loanTab !== 'costs')
                @if ($loanTab !== 'all' && session('success'))
                    <div class="alert alert-success d-flex align-items-start gap-2" role="alert"><i class="bi bi-check-circle mt-1"></i><div>{{ session('success') }}</div></div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="bi bi-exclamation-circle mt-1"></i><div>{{ $errors->first() }}</div></div>
                @endif
                @unless ($loansTableExists)
                    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert"><i class="bi bi-tools mt-1"></i><div>Loans is installed in code but the database table is not created yet. Run <code>php artisan migrate</code> to enable it.</div></div>
                @endunless
            @else
            <div class="row g-3 mb-4">
                <div class="col-xl-5">
                    <section class="panel-card p-3 p-lg-4 h-100">
                        @if (session('success'))
                            <div class="alert alert-success d-flex align-items-start gap-2" role="alert"><i class="bi bi-check-circle mt-1"></i><div>{{ session('success') }}</div></div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="bi bi-exclamation-circle mt-1"></i><div>{{ $errors->first() }}</div></div>
                        @endif
                        @unless ($loansTableExists)
                            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert"><i class="bi bi-tools mt-1"></i><div>Loans is installed in code but the database table is not created yet. Run <code>php artisan migrate</code> to enable it.</div></div>
                        @endunless
                        <div class="section-title">Loan Administration</div>
                        <p class="text-secondary mb-3">Manage loan servicing and repayment. DSS Loan holds saved application decisions, supporting evidence, and scheduling eligibility.</p>
                        <div class="loan-admin-facts">
                            <div><span>Interest</span><strong>0%</strong></div>
                            <div><span>Eligible terms</span><strong>3, 6, or 12 months</strong></div>
                            <div><span>Application decisions</span><strong>Managed in DSS Loan</strong></div>
                        </div>
                    </section>
                </div>
                @if ($loanTab === 'costs')
                <div class="col-xl-7">
                    <section class="panel-card p-3 p-lg-4 h-100">
                        <div class="section-title">Installation Cost Breakdown</div>
                        <form method="POST" action="{{ route('loan-material-rates.update') }}">
                            @csrf
                            @method('PATCH')
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold" for="water_meter_cost">Water Meter</label><div class="input-group"><span class="input-group-text">₱</span><input type="number" class="form-control" id="water_meter_cost" name="water_meter_cost" min="0" step="0.01" value="{{ old('water_meter_cost', number_format((float) $loanCostBreakdown['meter'], 2, '.', '')) }}" required></div></div>
                                <div class="col-md-6"><label class="form-label fw-semibold" for="other_materials_cost">Other Materials</label><div class="input-group"><span class="input-group-text">₱</span><input type="number" class="form-control" id="other_materials_cost" name="other_materials_cost" min="0" step="0.01" value="{{ old('other_materials_cost', number_format((float) $loanCostBreakdown['other_materials'], 2, '.', '')) }}" required></div></div>
                            </div>
                            <div class="loan-cost-breakdown mt-3">
                                <div class="loan-cost-row"><span class="text-secondary">Meter</span><strong>₱{{ number_format((float) $loanCostBreakdown['meter'], 2) }}</strong></div>
                                <div class="loan-cost-row"><span class="text-secondary">Other Materials</span><strong>₱{{ number_format((float) $loanCostBreakdown['other_materials'], 2) }}</strong></div>
                                <div class="loan-cost-row total"><span>Total Installation Cost</span><strong>₱{{ number_format((float) $loanCostBreakdown['total_installation_cost'], 2) }}</strong></div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Update Costs</button>
                        </form>
                    </section>
                </div>
                @endif
            </div>
            @endif

        </div>
    </main>
</div>

@if ($canManageLoans)
    <div class="modal fade" id="incomeVerificationModal" tabindex="-1" aria-labelledby="incomeVerificationModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 rounded-4">
            <div class="modal-header"><div><h2 class="modal-title fs-5" id="incomeVerificationModalTitle">Verify Proof of Income</h2><div class="small text-secondary" id="incomeVerificationModalSubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="incomeVerificationModalBody"></div>
        </div></div>
    </div>
    <div class="modal fade" id="incomeRejectionModal" tabindex="-1" aria-labelledby="incomeRejectionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form method="POST" id="incomeRejectionForm">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <div class="modal-header"><h2 class="modal-title fs-5" id="incomeRejectionModalTitle">Reject Verification</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><label class="form-label" for="income-rejection-reason">Reason for rejecting proof of income</label><textarea class="form-control" id="income-rejection-reason" name="rejection_reason" minlength="8" maxlength="2000" required></textarea><div class="form-text">The consumer will receive this reason by email.</div></div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Reject Verification</button></div>
            </form>
        </div></div>
    </div>
        @if ($repaymentsTableExists)
        <div class="modal fade" id="loanRepaymentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Post Loan Repayment</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="fw-semibold" id="loan_repayment_borrower">-</div>
                            <div class="small text-secondary loan-cost-row"><span>Remaining balance:</span><strong id="loan_repayment_balance">₱0.00</strong></div>
                        </div>
                        <form method="POST" id="loanRepaymentForm">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="repayment_amount">Amount</label>
                                    <input type="number" class="form-control" id="repayment_amount" name="amount" min="0.01" step="0.01" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="repayment_payment_date">Payment Date</label>
                                    <input type="date" class="form-control" id="repayment_payment_date" name="payment_date" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="repayment_notes">Notes</label>
                                    <input type="text" class="form-control" id="repayment_notes" name="notes" placeholder="Optional repayment note">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="loanRepaymentForm" class="btn btn-primary">Post Repayment</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
@include('shared.income-proof-quality')
@endsection

@push('scripts')
    @if ($canManageLoans)
        <script>
            const incomeVerificationModal = document.getElementById('incomeVerificationModal');
            if (incomeVerificationModal && window.bootstrap) {
                const incomeModal = window.bootstrap.Modal.getOrCreateInstance(incomeVerificationModal);
                const incomeModalTitle = document.getElementById('incomeVerificationModalTitle');
                const incomeModalSubtitle = document.getElementById('incomeVerificationModalSubtitle');
                const incomeModalBody = document.getElementById('incomeVerificationModalBody');
                document.querySelectorAll('[data-income-verification]').forEach(button => button.addEventListener('click', () => {
                    const loanId = button.getAttribute('data-income-verification');
                    const template = document.getElementById('income-verification-template-' + loanId);
                    if (!template) return;
                    incomeModalTitle.textContent = 'Verify Proof of Income';
                    incomeModalSubtitle.textContent = (button.getAttribute('data-income-name') || 'Loan applicant') + ' · Loan #' + loanId;
                    incomeModalBody.replaceChildren(template.content.cloneNode(true));
                    const proof = incomeModalBody.querySelector('[data-admin-income-proof]');
                    const proofImage = proof?.querySelector('img');
                    const proofFrame = proof?.querySelector('iframe');
                    if (proofImage) window.assessIncomeProof(proofImage, proof.querySelector('[data-admin-quality]'));
                    proof?.querySelector('[data-admin-enlarge]')?.addEventListener('click', () => window.openIncomeProofPreview(proofImage?.src || proofFrame?.src, Boolean(proofFrame)));
                    incomeModalBody.querySelector('[data-reject-income-url]')?.addEventListener('click', event => {
                        document.getElementById('incomeRejectionForm').action = event.currentTarget.dataset.rejectIncomeUrl;
                        document.getElementById('income-rejection-reason').value = '';
                        incomeVerificationModal.addEventListener('hidden.bs.modal', () => {
                            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('incomeRejectionModal')).show();
                        }, { once: true });
                        incomeModal.hide();
                    });
                    incomeModal.show();
                }));
                const focusedLoanId = @json($focusedLoanId);
                if (focusedLoanId) {
                    document.querySelector(`[data-income-verification="${focusedLoanId}"]`)?.click();
                }
            }

        </script>

        @if ($repaymentsTableExists)
            <script>
                const loanRepaymentModal = document.getElementById('loanRepaymentModal');

                if (loanRepaymentModal) {
                    loanRepaymentModal.addEventListener('show.bs.modal', (event) => {
                        const button = event.relatedTarget;
                        const loanId = button.getAttribute('data-loan-id');
                        const balance = button.getAttribute('data-loan-balance') || '0.00';
                        const installment = button.getAttribute('data-loan-installment') || '';

                        document.getElementById('loan_repayment_borrower').textContent = button.getAttribute('data-loan-borrower') || '-';
                        document.getElementById('loan_repayment_balance').textContent = `₱${Number(balance).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                        document.getElementById('repayment_amount').value = installment;
                        document.getElementById('loanRepaymentForm').action = `/loans/${loanId}/repayments`;
                    });
                }
            </script>
        @endif
    @endif

@endpush
