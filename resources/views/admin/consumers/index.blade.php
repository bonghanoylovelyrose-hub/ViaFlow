@extends('layouts.app')

@php
    $activePage = 'consumers';
    $isAllConsumers = $filters['account_category'] === '';
    $isPendingAccounts = $filters['account_category'] === 'pending';
    $usesSingleClear = in_array($filters['account_category'], ['', 'pending', 'rejected', 'inactive'], true);
    $clearConsumerFilters = array_filter([
        'account_category' => $filters['account_category'],
        'pending_type' => $isPendingAccounts ? $pendingType : null,
    ]);
    $isActiveAccounts = $filters['account_category'] === 'active';
    $isInactiveAccounts = $filters['account_category'] === 'inactive';
    $consumerStatusService = app(\App\Services\ConsumerStatusService::class);
    $connectionApplications = app(\App\Services\ConnectionApplicationService::class);
    $pendingHeadings = [
        'all' => ['All Pending Accounts', 'View and manage all pending consumer applications and reschedule requests.'],
        'applications' => ['Connection Applications', 'Review and manage pending water connection applications.'],
        'reschedules' => ['Reschedule Requests', 'Review and manage consumer installation reschedule requests.'],
    ];
@endphp

@section('title', 'Consumers')

@push('styles')
    <style>
        .consumer-section-controls { display: flow-root; }

        #account-filters .form-control,
        #account-filters .form-select,
        #account-filters .btn { min-height: 2.65rem; }

        #account-filters.active-account-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 16.5rem), 1fr));
            align-items: end;
            gap: .75rem;
            max-width: 52rem;
        }

        .active-account-filters .account-filter-field {
            min-width: 0;
            max-width: 23rem;
        }

        #account-filters.active-account-filters.is-column-aligned {
            grid-template-columns: var(--membership-filter-start) calc(var(--installation-filter-start) - var(--membership-filter-start)) minmax(0, 1fr);
            column-gap: 0;
            max-width: none;
        }

        .active-account-filters.is-column-aligned .account-filter-field { max-width: 16.5rem; }
        .active-account-filters.is-column-aligned .account-filter-field:has(> #search) { margin-inline-end: .75rem; }

        .active-account-filters :is(.form-control, .form-select, .btn) { height: 2.75rem; }

        .active-account-filters :is(.form-control, .form-select) {
            padding-inline-start: .625rem;
            font-size: .875rem;
        }

        .active-account-filters .form-select {
            padding-inline-end: 1.625rem;
            background-position: right .375rem center;
        }

        .active-account-filters .btn {
            padding-inline: .5rem;
            border-radius: .8rem;
        }

        .active-account-filters > noscript { grid-column: 1 / -1; order: 1; }

        @media (max-width: 359.98px) {
            .active-account-filters :is(.form-control, .form-select) { font-size: .85rem; }
        }

        #consumer-accounts-table {
            font-size: 0.92rem;
            table-layout: fixed;
            width: 100%;
        }

        #consumer-accounts-table col.consumer-col-name { width: 18%; }
        #consumer-accounts-table col.consumer-col-account { width: 11%; }
        #consumer-accounts-table col.consumer-col-meter { width: 11%; }
        #consumer-accounts-table col.consumer-col-membership { width: 13%; }
        #consumer-accounts-table col.consumer-col-standing { width: 9%; }
        #consumer-accounts-table col.consumer-col-status { width: 13%; }
        #consumer-accounts-table col.consumer-col-actions { width: 25%; }

        #consumer-accounts-table thead th {
            white-space: nowrap;
            font-size: 0.72rem;
            letter-spacing: 0.035em;
            vertical-align: middle;
        }

        #consumer-accounts-table tbody > tr > td {
            padding: 0.65rem 0.55rem;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-break: normal;
        }

        #consumer-accounts-table .consumer-table-secondary {
            display: none !important;
        }

        #consumer-accounts-table .consumer-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.35rem;
        }

        #consumer-accounts-table .consumer-actions form {
            display: inline-flex;
            margin: 0;
        }

        #consumer-accounts-table .consumer-actions-active {
            flex-wrap: nowrap;
            justify-content: flex-end;
        }

        #consumer-accounts-table:has(col.consumer-col-installation) {
            min-width: 1180px;
        }

        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-name { width: 15%; }
        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-account { width: 10%; }
        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-meter { width: 10%; }
        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-membership { width: 11%; }
        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-standing { width: 8%; }
        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-status { width: 10%; }
        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-installation { width: 21%; }
        #consumer-accounts-table:has(col.consumer-col-installation) col.consumer-col-actions { width: 15%; }

        #consumer-accounts-table .consumer-actions .btn {
            min-height: 2.15rem;
            padding: 0.32rem 0.55rem;
            border-radius: 0.65rem !important;
            font-size: 0.73rem;
            line-height: 1.1;
            white-space: nowrap;
        }

        #consumer-accounts-table .consumer-actions .btn i {
            margin-right: 0.2rem !important;
        }

        #consumer-accounts-table .consumer-actions .me-2,
        #consumer-accounts-table .consumer-actions .ms-2 {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        @media (max-width: 1100px) {
            #consumer-accounts-table {
                font-size: 0.84rem;
            }

            #consumer-accounts-table .consumer-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 767.98px) {
            #consumer-accounts-table {
                table-layout: auto;
                min-width: 900px;
            }

            #consumer-accounts-table .consumer-actions {
                min-width: 220px;
            }

            #consumer-accounts-table:has(col.consumer-col-installation) .consumer-actions-active {
                min-width: 0;
            }
        }

        #consumerDetailsModal .modal-dialog {
            width: min(900px, calc(100vw - 2rem));
            max-width: none;
        }

        #consumerDetailsModal .modal-content {
            max-height: calc(100vh - 2rem);
        }

        #consumerDetailsModal .modal-header,
        #consumerDetailsModal .modal-footer {
            padding: 0.65rem 0.9rem;
        }

        #consumerDetailsModal .modal-title {
            font-size: 0.98rem;
        }

        #consumerDetailsModal .modal-body {
            min-height: 0;
            overflow-y: auto;
            padding: 0.75rem 0.9rem;
        }

        #consumerDetailsModal .consumer-details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }

        #consumerDetailsModal .consumer-details-section {
            min-width: 0;
            margin: 0;
            padding: 0.6rem;
            border: 1px solid var(--bs-border-color);
            border-radius: 0.55rem;
            background: var(--bs-tertiary-bg);
        }

        #consumerDetailsModal .consumer-details-section:first-child {
            grid-column: 1 / -1;
        }

        #consumerDetailsModal .consumer-details-section h3 {
            font-size: 0.74rem;
            letter-spacing: 0.02em;
            margin-bottom: 0.45rem !important;
        }

        #consumerDetailsModal .consumer-details-section .row {
            --bs-gutter-x: 0.45rem;
            --bs-gutter-y: 0.45rem;
        }

        #consumerDetailsModal .consumer-details-section .border {
            padding: 0.42rem !important;
            border-color: rgba(var(--bs-secondary-rgb), 0.18) !important;
            background: var(--bs-body-bg) !important;
        }

        #consumerDetailsModal .consumer-details-section .small {
            font-size: 0.59rem;
            line-height: 1.15;
        }

        #consumerDetailsModal .consumer-details-section .fw-medium {
            font-size: 0.72rem;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        body:has(#resendLoginDetailsModal[open]) > .modal-backdrop {
            background-color: rgba(15, 23, 42, 0.28);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        #resendLoginDetailsModal {
            width: min(430px, calc(100vw - 2rem));
            max-height: calc(100dvh - 2rem);
            margin: auto;
            padding: 0;
            border: 0;
            border-radius: 1rem;
            background: transparent;
            overscroll-behavior: contain;
        }

        #resendLoginDetailsModal::backdrop {
            background-color: rgba(15, 23, 42, 0.24);
        }

        #resendLoginDetailsModal .modal-content {
            border: 1px solid var(--wb-border) !important;
            border-radius: 1rem !important;
            box-shadow: 0 1.25rem 3rem rgba(6, 43, 128, 0.2) !important;
        }

        #resendLoginDetailsModal .resend-confirm-icon {
            width: 2.65rem;
            height: 2.65rem;
            display: inline-grid;
            place-items: center;
            border-radius: 0.8rem;
            background: color-mix(in srgb, var(--wb-primary) 10%, var(--wb-surface));
            color: var(--wb-primary);
            font-size: 1.25rem;
        }

        #resendLoginDetailsModal .resend-confirm-email {
            display: block;
            margin-top: 0.35rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--wb-border);
            border-radius: 0.7rem;
            background: color-mix(in srgb, var(--wb-primary) 4%, var(--wb-surface));
            color: var(--wb-text);
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        #resendLoginDetailsModal .modal-footer {
            gap: 0.55rem;
        }

        #resendLoginDetailsModal .modal-footer .btn {
            min-width: 7rem;
        }

        #consumerEditModal .modal-dialog {
            width: min(1180px, calc(100vw - 2rem));
            max-width: none;
        }

        #consumerEditModal .modal-content {
            max-height: calc(100vh - 2rem);
        }

        #consumerEditModal .modal-header,
        #consumerEditModal .modal-footer {
            flex-shrink: 0;
            padding: 0.7rem 1rem;
        }

        #consumerEditModal .modal-body {
            overflow-y: auto;
            padding: 0.8rem 1rem;
        }

        #consumerEditModal .edit-consumer-section {
            padding: 0.7rem;
            border: 1px solid var(--bs-border-color);
            border-radius: 0.6rem;
            background: var(--bs-tertiary-bg);
        }

        #consumerEditModal .edit-consumer-section + .edit-consumer-section {
            margin-top: 0.7rem;
        }

        #consumerEditModal .edit-consumer-section-heading {
            margin-bottom: 0.55rem;
            color: var(--bs-emphasis-color);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        #consumerEditModal .form-label {
            margin-bottom: 0.22rem;
            font-size: 0.74rem;
        }

        #consumerEditModal .form-control,
        #consumerEditModal .form-select {
            min-height: 2.05rem;
            padding: 0.32rem 0.55rem;
            font-size: 0.82rem;
        }

        #consumerEditModal textarea.form-control {
            min-height: 4rem;
        }

        #consumerEditModal .form-check {
            margin-top: 0 !important;
            min-height: 1.8rem;
            padding-top: 0.2rem;
        }

        #consumerEditModal .form-check-label,
        #consumerEditModal .form-text {
            font-size: 0.72rem;
            line-height: 1.25;
        }

        #consumerEditModal .edit-consumer-checks {
            --bs-gutter-x: 0.8rem;
            --bs-gutter-y: 0.35rem;
        }

        @media (min-width: 768px) {
            #consumerEditForm:has(#edit_status_field[hidden]) .edit-account-identifier { width: 50%; }
        }

        @media (max-width: 991.98px) {
            #consumerDetailsModal .modal-body {
                overflow-y: auto;
            }

            #consumerDetailsModal .consumer-details-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            #consumerDetailsModal .modal-dialog {
                width: calc(100vw - 1rem);
                margin: 0.5rem auto;
            }

            #consumerDetailsModal .consumer-details-grid {
                grid-template-columns: 1fr;
            }
        }

        #disconnection-records .table-danger > td {
            --bs-table-bg: #f8d7da;
            --bs-table-color: #842029;
            background-color: #f8d7da !important;
            color: #842029 !important;
        }

        #disconnection-records .table-danger .bg-danger {
            background-color: #b02a37 !important;
            color: #fff !important;
        }

        /* Application review: one scrollable body, with clear review and decision sections. */
        #consumerActivateModal .modal-content { max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); overflow: hidden; }
        #consumerActivateModal .modal-header, #consumerActivateModal .modal-footer { flex: 0 0 auto; background: var(--vf-white); z-index: 1; }
        #consumerActivateModal .modal-body { min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 1.25rem; background: var(--vf-background); }
        #consumerActivateModal .review-stack, #consumerActivateModal #activateInfoContent { display: grid; gap: 1.25rem; }
        #consumerActivateModal .review-section { min-width: 0; background: var(--vf-white); border: 1px solid var(--vf-light); border-radius: .85rem; box-shadow: 0 4px 14px rgba(7,84,200,.07); padding: 1.25rem; }
        #consumerActivateModal .review-section-heading { color: var(--vf-navy); font-size: 1rem; font-weight: 700; margin: 0 0 1rem; }
        #consumerActivateModal .review-section-heading--tinted { background: var(--vf-light); border-radius: .55rem; padding: .75rem .9rem; }
        #consumerActivateModal .review-field-label { color: var(--vf-navy); font-size: .7rem; letter-spacing: .025em; text-transform: uppercase; font-weight: 600; margin-bottom: .25rem; }
        #consumerActivateModal .review-field-value { overflow-wrap: anywhere; }
        #consumerActivateModal .review-section .form-label { margin-bottom: .4rem; }
        #consumerActivateModal .review-section > :last-child { margin-bottom: 0; }
        #consumerActivateModal .modal-footer { gap: .5rem; }
        @media (max-width: 575.98px) {
            #consumerActivateModal .modal-content { max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); }
            #consumerActivateModal .modal-body { padding: 1rem; }
            #consumerActivateModal .review-section { padding: 1rem; }
            #consumerActivateModal .modal-footer > div { display: flex; flex-wrap: wrap; gap: .5rem; }
            #consumerActivateModal .modal-footer > div .me-2 { margin-right: 0 !important; }
        }

        #consumerActivateModal [hidden] { display: none !important; }
        #consumerActivateModal .modal-header { display: block; }
        #consumerActivateModal .wizard-header-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        #consumerActivateModal .wizard-progress { display: flex; gap: .4rem; margin: .8rem 0 0; padding: 0 0 .2rem; list-style: none; overflow-x: auto; }
        #consumerActivateModal .wizard-progress li { flex: 1 0 auto; display: flex; align-items: center; gap: .35rem; color: var(--vf-navy); font-size: .72rem; }
        #consumerActivateModal .wizard-progress span { display: grid; place-items: center; flex: none; width: 1.45rem; height: 1.45rem; border: 1px solid var(--vf-light); border-radius: 50%; background: var(--vf-white); }
        #consumerActivateModal .wizard-progress [aria-current=step] span { background: var(--vf-royal); border-color: var(--vf-royal); color: var(--vf-white); }
        #consumerActivateModal .wizard-progress .is-complete span { background: var(--vf-light); }
        #consumerActivateModal .wizard-choice { display: block; cursor: pointer; border: 1px solid var(--vf-light); border-radius: .65rem; padding: 1rem; margin-top: .75rem; }
        #consumerActivateModal .wizard-choice:has(input:checked) { border-color: var(--vf-royal); background: var(--vf-background); }
        #consumerActivateModal .wizard-footer-actions { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }
        #consumerActivateModal .wizard-summary dt { color: var(--vf-navy); font-size: .8rem; margin-top: .9rem; }
        #consumerActivateModal .wizard-summary dd { margin: .25rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; }
        @media(max-width:575.98px) { #consumerActivateModal .wizard-progress li { font-size: .68rem; } #consumerActivateModal .wizard-progress { gap: .65rem; } }
    </style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div @class(['page-heading', 'inactive-account-heading' => $isInactiveAccounts])>
                <div>
                    @if ($isInactiveAccounts)
                        <h1 id="consumerPageTitle">Inactive Accounts</h1>
                    @else
                        <h1 id="consumerPageTitle">{{ $filters['account_category'] === 'pending' ? $pendingHeadings[$pendingType][0] : 'Consumer Management' }}</h1>
                        <p id="consumerPageDescription">{{ $filters['account_category'] === 'pending' ? $pendingHeadings[$pendingType][1] : ($filters['account_category'] === 'active' ? 'Manage disconnection notices and active accounts in separate, independently filtered tables.' : 'Browse, search, and maintain consumer account records with a simple table workflow.') }}</p>
                    @endif
                </div>
                @if ($isAllConsumers)
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#consumerModal">
                            <i class="bi bi-person-plus me-2"></i>Add Consumer
                        </button>
                    </div>
                @endif
            </div>

            @if ($isInactiveAccounts)
                @include('admin.consumers.account-tabs')
            @endif

            <div @class(['panel-card', 'p-4' => ! $isInactiveAccounts, 'inactive-records' => $isInactiveAccounts]) data-admin-records-root data-admin-page-scroll data-admin-freeze-section @if ($filters['account_category'] === 'active') data-admin-section-scroll @endif>
                @if (session('success'))
                    <div class="alert alert-success d-flex align-items-center gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-check-circle"></i>
                        <div><span class="fw-bold">Success:</span> {{ session('success') }}</div>
                    </div>
                @endif

                @if (session('info'))
                    <div class="alert alert-info d-flex align-items-center gap-2" role="status" data-admin-freeze>
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <div>{{ session('info') }}</div>
                    </div>
                @endif

                @if (session('email_error'))
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-envelope-exclamation mt-1"></i>
                        <div><span class="fw-bold">Email delivery failed:</span> {{ session('email_error') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                {{-- This section is exclusive to Consumers -> Active Accounts. --}}
                @if ($filters['account_category'] === 'active' && $disconnectionNotices)
                    @php
                        $hasNoticeFilters = $filters['notice_search'] !== '' || $filters['notice_status'] !== '' || $filters['notice_membership'] !== '';
                    @endphp
                    <div class="card mb-3" id="active-disconnection-notices" data-admin-freeze-section>
                        <div class="consumer-section-controls">
                        <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h2 class="fw-bold fs-5 mb-1"><i class="bi bi-journal-text me-2"></i>Disconnection Notice</h2>
                                <div class="small text-secondary">Disconnection notices for unpaid water bills or overdue meter loans. Red rows have passed the three-day grace period.</div>
                            </div>
                            <span class="badge bg-secondary">
                                {{ number_format($disconnectionNoticeCount) }} account{{ $disconnectionNoticeCount === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <div class="consumer-section-controls px-4 pt-4">
                            <form method="GET" id="notice-filters" action="{{ route('consumers.index') }}" class="row g-2 mb-3 align-items-end" data-auto-filter data-auto-filter-debounce="450">
                                <input type="hidden" name="account_category" value="active">
                                @if (request()->has('page'))
                                    <input type="hidden" name="page" value="{{ $consumers->currentPage() }}">
                                @endif
                                @if ($filters['search'] !== '')
                                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                @endif
                                @if ($filters['membership'] !== '')
                                    <input type="hidden" name="membership" value="{{ $filters['membership'] }}">
                                @endif
                                @if ($filters['membership_verification'] !== '')
                                    <input type="hidden" name="membership_verification" value="{{ $filters['membership_verification'] }}">
                                @endif
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary" for="notice_search">Search</label>
                                    <input type="text" class="form-control" id="notice_search" name="notice_search" value="{{ $filters['notice_search'] }}" placeholder="Search by name, account, or meter number">
                                </div>
                                <div class="col-md-3">
                                    
                                    <div class="d-flex align-items-center gap-2" style="min-width:0"><select aria-label="Status filter" style="min-width:0;flex:1 1 0" class="form-select" id="notice_status" name="notice_status">
                                        @foreach ($noticeStatusFilters as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" @selected($filters['notice_status'] === $statusValue)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
<button type="submit" name="notice_status" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear status filter">Clear</button>
</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary" for="notice_membership">Membership</label>
                                    <div class="d-flex align-items-center gap-2" style="min-width:0"><select data-clearable-filter style="min-width:0;flex:1 1 0" class="form-select" id="notice_membership" name="notice_membership">
                                        @foreach ($membershipFilters as $membershipValue => $membershipLabel)
                                            @if ($membershipValue !== '')<option value="{{ $membershipValue }}" @selected($filters['notice_membership'] === $membershipValue)>{{ $membershipLabel }}</option>@endif
                                        @endforeach
                                    </select>
<button type="submit" name="notice_membership" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear notice membership filter">Clear</button>
</div>
                                </div>
                                <div class="col-md-2 col-12 d-flex gap-2">
                                    <noscript>
                                        <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                                    </noscript>
                                    @if ($hasNoticeFilters)<a href="{{ route('consumers.index', array_merge(request()->only(['search', 'membership', 'membership_verification', 'page']), ['account_category' => 'active'])) }}" class="btn btn-light border w-100"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters</a>@endif
                                </div>
                            </form>
                        </div>
                        </div>
                        <div class="card-body p-4 pt-0">

                                <div class="table-responsive">
                                    <table class="table align-middle mb-0" id="disconnection-records">
                                        <thead>
                                            <tr>
                                                <th>Account Number</th>
                                                <th>Consumer Name</th>
                                                <th>Meter Number</th>
                                                <th>Membership</th>
                                                <th>Outstanding Balance</th>
                                                <th>Disconnection Reason</th>
                                                <th>Payment Status</th>
                                                <th>Disconnection Notice Date</th>
                                                <th>Grace Period / Payment Deadline</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($disconnectionNotices as $notice)
                                                @php
                                                    $noticeConsumer = $notice['consumer'];
                                                    $noticeTimezone = 'Asia/Manila';
                                                    $noticePayload = base64_encode(json_encode([
                                                        'id' => $noticeConsumer->id,
                                                        'application_reference' => $noticeConsumer->application_reference,
                                                        'full_name' => $noticeConsumer->full_name,
                                                        'email' => $noticeConsumer->email,
                                                        'account_number' => $noticeConsumer->account_number,
                                                        'meter_number' => $noticeConsumer->meter_number,
                                                        'meter_history' => [],
                                                        'membership_type' => $noticeConsumer->membership_type,
                                                        'requested_membership_type' => $noticeConsumer->requested_membership_type,
                                                        'membership_verification_status' => $noticeConsumer->membership_verification_status,
                                                        'membership_verification_notes' => $noticeConsumer->membership_verification_notes,
                                                        'status' => $noticeConsumer->status,
                                                        'status_display' => $consumerStatusService->present($noticeConsumer),
                                                        'property_type' => $noticeConsumer->property_type,
                                                        'ownership_status' => $noticeConsumer->ownership_status,
                                                        'valid_id_presented' => $noticeConsumer->valid_id_presented,
                                                        'service_address' => $noticeConsumer->service_address,
                                                        'address_block' => $noticeConsumer->address_block,
                                                        'address_street' => $noticeConsumer->address_street,
                                                        'contact_number' => $noticeConsumer->contact_number,
                                                        'membership_renewal_date' => optional($noticeConsumer->membership_renewal_date)->toDateString(),
                                                        'reconnection_date' => optional($noticeConsumer->reconnection_date)->toDateString(),
                                                        'installation_date' => optional($noticeConsumer->installation_date)->toDateString(),
                                                        'housing_rights_documented' => (bool) $noticeConsumer->housing_rights_documented,
                                                        'resident_of_community' => (bool) $noticeConsumer->resident_of_community,
                                                        'association_member' => (bool) $noticeConsumer->association_member,
                                                        'association_registered' => (bool) $noticeConsumer->association_registered,
                                                        'housing_loan_contract_submitted' => (bool) $noticeConsumer->housing_loan_contract_submitted,
                                                        'valid_id_submitted' => (bool) $noticeConsumer->valid_id_submitted,
                                                        'proof_of_residency_submitted' => (bool) $noticeConsumer->proof_of_residency_submitted,
                                                        'other_document_details' => $noticeConsumer->other_document_details,
                                                    ]));
                                                @endphp
                                                <tr @class(['table-danger' => $notice['record_status'] === 'Ready for Disconnection']) data-record-status="{{ $notice['record_status'] }}">
                                                    <td>{{ $noticeConsumer->account_number ?: 'Pending assignment' }}</td>
                                                    <td>
                                                        <div class="fw-semibold">{{ $noticeConsumer->full_name }}</div>
                                                        <div class="small text-secondary">{{ $noticeConsumer->application_reference }}</div>
                                                    </td>
                                                    <td>{{ $noticeConsumer->meter_number ?: 'Pending assignment' }}</td>
                                                    <td>
                                                        <div class="fw-semibold">{{ $noticeConsumer->membership_type }}</div>
                                                        @if ($noticeConsumer->hasPendingMembershipVerification())
                                                            <div class="small text-secondary">Verification pending</div>
                                                        @elseif ($noticeConsumer->hasVerifiedMembership())
                                                            <div class="small text-secondary">Verified member</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold">P {{ number_format($notice['total_outstanding'], 2) }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold">{{ $notice['reason'] }}</div>
                                                        @if ($notice['water_outstanding'] > 0)
                                                            <div class="small">Water: P {{ number_format($notice['water_outstanding'], 2) }}</div>
                                                        @endif
                                                        @if ($notice['loan_outstanding'] > 0)
                                                            <div class="small">Meter loan: P {{ number_format($notice['loan_outstanding'], 2) }}</div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $notice['total_outstanding'] > 0 ? 'Unpaid' : 'Paid' }}</td>
                                                    <td>
                                                        <div>{{ $notice['notice_date']?->copy()->timezone($noticeTimezone)->format('M d, Y') ?: '—' }}</div>
                                                        @if ($notice['notice_billing_period'])
                                                            <div class="small text-secondary">Billing period {{ $notice['notice_billing_period']->format('M d, Y') }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($notice['payment_deadline'])
                                                            <div>{{ $notice['payment_deadline']->copy()->timezone($noticeTimezone)->format('M d, Y g:i A') }} Philippine time</div>
                                                        @else
                                                            <span class="text-secondary">—</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span @class(['badge', 'bg-danger' => $notice['record_status'] === 'Ready for Disconnection', 'bg-success' => $notice['record_status'] === 'Resolved', 'bg-warning text-dark' => $notice['record_status'] === 'Within Grace Period', 'bg-secondary' => $notice['record_status'] === 'Unpaid'])>{{ $notice['record_status'] }}</span>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                                            @if ($notice['can_confirm_disconnection'])
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-danger text-nowrap"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#consumerDisconnectionModal"
                                                                    data-disconnection-url="{{ route('consumers.confirm-disconnection', $noticeConsumer) }}"
                                                                    data-disconnection-name="{{ $noticeConsumer->full_name }}"
                                                                    data-disconnection-account="{{ $noticeConsumer->account_number ?: 'Pending assignment' }}"
                                                                    data-disconnection-meter="{{ $noticeConsumer->meter_number ?: 'Pending assignment' }}"
                                                                    data-disconnection-balance="{{ number_format($notice['total_outstanding'], 2) }}"
                                                                    data-disconnection-status="{{ $notice['record_status'] }}"
                                                                >
                                                                    <i class="bi bi-plug me-1" aria-hidden="true"></i>Confirm Disconnection
                                                                </button>
                                                            @elseif ($notice['has_active_notice'] && ! $notice['is_billing_fixture'])
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-secondary text-nowrap"
                                                                    disabled
                                                                    title="Available only after the three-day payment window closes."
                                                                >
                                                                    <i class="bi bi-plug me-1"></i>Confirm Disconnection
                                                                </button>
                                                            @endif
                                                            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap"
                                                                data-bs-toggle="modal" data-bs-target="#disconnectionNoticeDetails-{{ $noticeConsumer->id }}">
                                                                View Details
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-outline-secondary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#consumerEditModal"
                                                                data-notice-edit-url="{{ route('consumers.update-from-disconnection-notice', $noticeConsumer) }}"
                                                                data-consumer='{{ $noticePayload }}'
                                                                title="Open this consumer's account details"
                                                            >
                                                                <i class="bi bi-pencil-square me-1"></i>Edit
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="11" class="text-center text-secondary py-4">{{ $hasNoticeFilters ? 'No disconnection records match the current filters.' : 'No disconnection records found.' }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                            @if ($disconnectionNotices->total() > 0)
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                                    <p class="text-secondary mb-0">
                                        Showing {{ $disconnectionNotices->firstItem() }}–{{ $disconnectionNotices->lastItem() }} of {{ $disconnectionNotices->total() }} account{{ $disconnectionNotices->total() === 1 ? '' : 's' }}
                                    </p>
                                    {{ $disconnectionNotices->onEachSide(1)->links('admin.consumers.disconnection-pagination') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($filters['account_category'] === 'active')
                <section id="active-accounts" aria-labelledby="active-accounts-title" data-admin-freeze-section>
                <div class="consumer-section-controls" data-admin-freeze>
                    <h2 class="fs-5 fw-bold" id="active-accounts-title">Active Accounts</h2>
                    <p class="small text-secondary">Active accounts without an active disconnection notice.</p>
                @endif
                <form method="GET" id="account-filters" action="{{ route('consumers.index') }}" @class(['mb-3', 'active-account-filters' => $isActiveAccounts, 'row align-items-end' => ! $isActiveAccounts, 'g-2' => $isInactiveAccounts]) data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                    @if ($isActiveAccounts)
                        @foreach (request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']) as $noticeFilter => $noticeValue)
                            <input type="hidden" name="{{ $noticeFilter }}" value="{{ $noticeValue }}">
                        @endforeach
                        <noscript>
                            <button type="submit" class="btn btn-primary">Apply filters</button>
                        </noscript>
                    @endif
                    @if ($filters['account_category'] !== '')
                        <input type="hidden" name="account_category" value="{{ $filters['account_category'] }}">
                    @endif
                    @if (($filters['pending_type'] ?? '') !== '' && $filters['account_category'] === 'pending')
                        <input type="hidden" name="pending_type" value="{{ $filters['pending_type'] }}">
                    @endif
                    @if ($filters['membership_verification'] !== '')
                        <input type="hidden" name="membership_verification" value="{{ $filters['membership_verification'] }}">
                    @endif
                    <div class="{{ $isActiveAccounts ? 'account-filter-field' : 'col-md-6 col-12' }}">
                        <label class="form-label fw-semibold small text-secondary" for="search">Search</label>
                        <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] }}" placeholder="{{ $isInactiveAccounts ? 'Name, account, or meter number' : 'Search by name, account, or meter number' }}">
                    </div>
                    <div @class(['account-filter-field' => $isActiveAccounts, 'col-md-6 col-12' => $usesSingleClear, 'col-md-4 col-12' => ! $isActiveAccounts && ! $usesSingleClear])>
                        <label class="form-label fw-semibold small text-secondary" for="membership">Membership</label>
                        <div class="d-flex align-items-center gap-2" style="min-width:0">
                            <select data-clearable-filter style="min-width:0;flex:1 1 0" class="form-select" id="membership" name="membership">
                                @foreach ($membershipFilters as $membershipValue => $membershipLabel)
                                    <option value="{{ $membershipValue }}" @selected($filters['membership'] === $membershipValue)>{{ $membershipLabel }}</option>
                                @endforeach
                            </select>
                            @if ($usesSingleClear)
                                <a href="{{ route('consumers.index', $clearConsumerFilters) }}"
                                   class="btn btn-outline-primary btn-sm flex-shrink-0 d-inline-flex align-items-center"
                                   aria-label="Clear all consumer filters" @if ($isPendingAccounts) data-pending-clear @endif>Clear</a>
                            @else
                                <button type="submit" name="membership" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear membership filter">Clear</button>
                            @endif
                        </div>
                    </div>
                    @if ($isActiveAccounts)
                        <div class="account-filter-field">
                            <label class="form-label fw-semibold small text-secondary" for="installation_status">Installation Status</label>
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select" id="installation_status" name="installation_status" style="min-width:0;flex:1 1 0">
                                    <option value="all" @selected($filters['installation_status'] === 'all')>All Installations</option>
                                    <option value="pending" @selected($filters['installation_status'] === 'pending')>Pending Installation</option>
                                    <option value="completed" @selected($filters['installation_status'] === 'completed')>Completed</option>
                                </select>
                                <button type="submit" name="installation_status" value="all" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear installation status filter">Clear</button>
                            </div>
                        </div>
                    @elseif ($usesSingleClear)
                        <noscript class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">Apply filters</button>
                        </noscript>
                    @else
                        <div class="col-md-2 col-12 d-flex gap-2">
                            <noscript>
                                <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                            </noscript>
                            @if ($filters['search'] !== '' || $filters['membership'] !== '' || $filters['membership_verification'] !== '')<a href="{{ route('consumers.index', $clearConsumerFilters) }}" class="btn btn-light border w-100"><i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters</a>@endif
                        </div>
                    @endif
                </form>
                
                @if ($filters['account_category'] === 'pending')
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap" id="pendingTypeFilters" data-admin-freeze>
                        <span class="small fw-bold text-secondary text-uppercase me-1">Pending Type:</span>
                        <a href="{{ route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'all']))) }}"
                           class="btn btn-sm {{ ($pendingType ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}" data-pending-type="all" @if ($pendingType === 'all') aria-current="page" @endif>
                            All Pending ({{ ($pendingApplicationsCount ?? 0) + ($pendingReschedulesCount ?? 0) }})
                        </a>
                        <a href="{{ route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'applications']))) }}"
                           class="btn btn-sm {{ ($pendingType ?? '') === 'applications' ? 'btn-primary' : 'btn-outline-secondary' }}" data-pending-type="applications" @if ($pendingType === 'applications') aria-current="page" @endif>
                            Connection Applications ({{ $pendingApplicationsCount ?? 0 }})
                        </a>
                        <a href="{{ route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'reschedules']))) }}"
                           class="btn btn-sm {{ ($pendingType ?? '') === 'reschedules' ? 'btn-primary' : 'btn-outline-secondary' }}" data-pending-type="reschedules" @if ($pendingType === 'reschedules') aria-current="page" @endif>
                            <i class="bi bi-calendar-event me-1"></i>Reschedule Requests ({{ $pendingReschedulesCount ?? 0 }})
                        </a>
                    </div>
                @endif

                @if ($filters['membership_verification'] === 'pending')
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 mb-3" role="status" @if ($filters['account_category'] !== 'active') data-admin-freeze @endif>
                        <span><i class="bi bi-person-check me-2"></i>Showing pending membership verifications.</span>
                        <a
                            href="{{ route('consumers.index', array_merge(request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']), array_filter(['account_category' => $filters['account_category'], 'membership' => 'member'], fn ($value) => $value !== null && $value !== ''))) }}"
                            class="small fw-semibold"
                        >View all members</a>
                    </div>
                @endif

                @if ($filters['account_category'] === 'active')
                </div>
                @endif
                @if ($filters['account_category'] === 'pending')
                    <p id="pendingLoading" class="small text-secondary" role="status" hidden>Loading pending records…</p>
                @endif
                <div class="table-responsive">
                    <table class="table align-middle" id="consumer-accounts-table" @if ($isInactiveAccounts) role="table" aria-labelledby="consumerPageTitle" @endif>
                        <colgroup>
                            <col class="consumer-col-name">
                            <col class="consumer-col-account">
                            <col class="consumer-col-meter">
                            <col class="consumer-col-membership">
                            <col class="consumer-col-standing">
                            <col class="consumer-col-status">
                            @if ($filters['account_category'] === 'active')
                                <col class="consumer-col-installation">
                            @endif
                            <col class="consumer-col-actions">
                        </colgroup>
                        <thead @if ($isInactiveAccounts) role="rowgroup" @endif>
                            <tr><th>Consumer Name</th><th>Account Number</th><th>Meter Number</th><th @if ($isActiveAccounts) data-account-filter-column="membership" @endif>Membership</th><th>Standing</th><th>Status</th>@if ($isActiveAccounts)<th data-account-filter-column="installation_status">INSTALLATION DETAILS</th>@endif<th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody @if ($isInactiveAccounts) role="rowgroup" @endif>
                            @forelse ($consumers as $consumer)
                                @php
                                    $awaitingRequirements = $consumer->status === \App\Models\Consumer::STATUS_PENDING_REQUIREMENTS;
                                    $canVerifyConsumer = $consumer->isAwaitingConnectionReview()
                                        && blank($consumer->user_id) && blank($consumer->meter_number);
                                    $requiredCorrections = $connectionApplications->requiredCorrections($consumer);
                                    $missingRequirementFields = $awaitingRequirements
                                        ? $requiredCorrections
                                        : ($canVerifyConsumer ? $connectionApplications->missingRequirementFields($consumer) : []);
                                    $reconnectionId = $consumer->status === 'Active' ? $latestReconnectionIds->get($consumer->id) : null;
                                    $scheduleEmail = $reconnectionId ? $reconnectionEmails->get('consumer_reconnected_'.$reconnectionId) : null;
                                    $scheduleEmailStatus = $reconnectionId ? match ($scheduleEmail?->status) {
                                        \App\Models\ConsumerNotificationLog::STATUS_SENT => 'Accepted by mail server',
                                        \App\Models\ConsumerNotificationLog::STATUS_FAILED => 'Email not accepted',
                                        \App\Models\ConsumerNotificationLog::STATUS_PENDING => 'Pending submission',
                                        default => 'Not yet submitted',
                                    } : null;
                                    $canRetryScheduleEmail = $reconnectionId && $scheduleEmail?->status !== \App\Models\ConsumerNotificationLog::STATUS_SENT;
                                    $canResendLoginDetails = $consumer->status === 'Active'
                                        && $consumer->user_id && filled($consumer->email)
                                        && ! in_array($consumer->id, $pendingEmailVerificationIds, true);
                                    $rescheduleUsage = app(\App\Services\InstallationRescheduleService::class)->usage($consumer, $consumer->reconnection_pending ? $consumer->latestReconnection : null);
                                    $consumerPayload = base64_encode(json_encode([
                                        'id' => $consumer->id,
                                        'billing_history_url' => route('consumers.billing-history', $consumer, false),
                                        'application_reference' => $consumer->application_reference,
                                        'full_name' => $consumer->full_name,
                                        'email' => $consumer->email,
                                        'reconnection_email_status' => $scheduleEmailStatus,
                                        'retry_reactivation_email_url' => $canRetryScheduleEmail ? route('consumers.retry-reactivation-email', $consumer, false) : null,
                                        'resend_login_details_url' => $canResendLoginDetails ? route('consumers.resend-credentials', $consumer, false) : null,
                                        'account_number' => $consumer->account_number,
                                        'meter_number' => $consumer->meter_number,
                                        'meter_history' => $meterHistory[$consumer->id] ?? [],
                                        'membership_type' => $consumer->membership_type,
                                        'requested_membership_type' => $consumer->requested_membership_type,
                                        'membership_verification_status' => $consumer->membership_verification_status,
                                        'membership_verification_notes' => $consumer->membership_verification_notes,
                                        'connection_request_status' => optional($newConnectionRequests->get($consumer->id))->status,
                                        'connection_request_admin_notes' => optional($newConnectionRequests->get($consumer->id))->admin_notes,
                                        'status' => $consumer->status,
                                        'status_display' => $consumerStatusService->present($consumer),
                                        'awaiting_requirements' => $awaitingRequirements,
                                        'required_corrections' => $requiredCorrections,
                                        'missing_requirements' => array_values(array_intersect_key($correctionFieldOptions, array_flip($missingRequirementFields))),
                                        'standing' => $consumerRag[$consumer->id]['label'] ?? 'Unknown',
                                        'reschedule_usage' => $rescheduleUsage,
                                        'property_type' => $consumer->property_type,
                                        'ownership_status' => $consumer->ownership_status,
                                        'valid_id_presented' => $consumer->valid_id_presented,
                                        'service_address' => $consumer->service_address,
                                        'address_block' => $consumer->address_block,
                                        'address_street' => $consumer->address_street,
                                        'contact_number' => $consumer->contact_number,
                                        'membership_renewal_date' => optional($consumer->membership_renewal_date)->toDateString(),
                                        'reconnection_date' => optional($consumer->reconnection_date)->toDateString(),
                                        'reconnection_pending' => $consumer->reconnection_pending,
                                        'reconnection_schedule' => \App\Support\ReconnectionSchedule::format($consumer->reconnection_scheduled_at),
                                        'reconnection_completed_at' => $consumer->reconnection_pending ? null : \App\Support\ReconnectionSchedule::format($consumer->reconnected_at),
                                        'installation_date' => optional($consumer->installation_date)->toDateString(),
                                        'installation_completed_at' => optional($consumer->installation_completed_at)->format('F d, Y g:i A'),
                                        'installation_completed_by' => $consumer->installationCompletedBy?->name,
                                        'housing_rights_documented' => (bool) $consumer->housing_rights_documented,
                                        'resident_of_community' => (bool) $consumer->resident_of_community,
                                        'association_member' => (bool) $consumer->association_member,
                                        'association_registered' => (bool) $consumer->association_registered,
                                        'housing_loan_contract_submitted' => (bool) $consumer->housing_loan_contract_submitted,
                                        'valid_id_submitted' => (bool) $consumer->valid_id_submitted,
                                        'proof_of_residency_submitted' => (bool) $consumer->proof_of_residency_submitted,
                                        'membership_document_type' => $consumer->membership_document_type,
                                        'other_document_details' => $consumer->other_document_details,
                                        'rejection_reason' => $consumer->rejection_reason,
                                        'rejection_fields' => $correctionFieldsByConsumer[$consumer->id] ?? [],
                                        'rejected_at' => optional($consumer->rejected_at)->format('F d, Y g:i A'),
                                        'resubmitted_at' => optional($consumer->resubmitted_at)->format('F d, Y g:i A'),
                                        'registered_at' => optional($consumer->created_at)->format('F d, Y g:i A'),
                                        'valid_id_url' => $consumer->valid_id_path ? route('uploads.serve', ['path' => $consumer->valid_id_path]) : null,
                                        'proof_of_residency_url' => $consumer->proof_of_residency_path ? route('uploads.serve', ['path' => $consumer->proof_of_residency_path]) : null,
                                        'membership_document_urls' => collect($consumer->membership_document_paths ?? [])
                                            ->map(fn ($path) => route('uploads.serve', ['path' => $path]))
                                            ->values()
                                            ->all(),
                                        'other_document_url' => $consumer->other_document_path ? route('uploads.serve', ['path' => $consumer->other_document_path]) : null,
                                    ]));
                                    $consumerPendingReschedules = $pendingReschedulesByConsumer[$consumer->id] ?? collect();
                                @endphp
                                <tr @if ($isInactiveAccounts) role="row" @endif>
                                    @if ($isInactiveAccounts)
                                    <td role="cell"><div class="fw-semibold">{{ $consumer->full_name }}</div></td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Account Number</span>{{ $consumer->account_number ?: 'Pending assignment' }}</td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Meter Number</span>{{ $consumer->meter_number ?: 'Pending assignment' }}</td>
                                    <td role="cell">
                                        <span class="inactive-record-label" aria-hidden="true">Membership</span>
                                        <div class="fw-semibold">{{ $consumer->membership_type }}</div>
                                        @if ($consumer->hasPendingMembershipVerification())
                                            <span class="badge bg-warning text-dark">Verification pending</span>
                                        @elseif ($consumer->hasVerifiedMembership())
                                            <span class="badge bg-success">Verified member</span>
                                        @elseif ($consumer->membership_verification_status === \App\Models\Consumer::MEMBERSHIP_VERIFICATION_REJECTED)
                                            <span class="badge bg-danger">Claim rejected</span>
                                        @endif
                                    </td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Standing</span>{{ ($consumerRag[$consumer->id] ?? ['label' => 'Unknown'])['label'] }}</td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Status</span><x-consumer-status-badge :consumer="$consumer" /></td>
                                    @else
                                    <td>
                                        <div class="fw-semibold">{{ $consumer->full_name }}</div>
                                    </td>
                                    <td>{{ $consumer->account_number ?: 'Pending assignment' }}</td>
                                    <td>{{ $consumer->meter_number ?: 'Pending assignment' }}</td>
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $consumer->membership_type }}
                                            @if ($consumer->hasPendingMembershipVerification())
                                                <span class="badge bg-warning text-dark ms-1">Verification pending</span>
                                            @elseif ($consumer->hasVerifiedMembership())
                                                <span class="badge bg-success ms-1">Verified member</span>
                                            @elseif ($consumer->membership_verification_status === \App\Models\Consumer::MEMBERSHIP_VERIFICATION_REJECTED)
                                                <span class="badge bg-danger ms-1">Claim rejected</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $rag = $consumerRag[$consumer->id] ?? ['level' => 'green', 'label' => 'Unknown'];
                                        @endphp
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <span class="rag-dot rag-{{ $rag['level'] }}"></span>
                                            <span class="rag-{{ $rag['level'] }}-text" style="font-size:0.82rem;">{{ $rag['label'] }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        <x-consumer-status-badge :consumer="$consumer" :show-application-stage="$isPendingAccounts" />
                                    </td>
                                    @endif
                                    @if ($filters['account_category'] === 'active')
                                        <td>
                                            <div class="consumer-installation-details">
                                                @if ($consumer->reconnection_pending && $consumer->latestReconnection)
                                                    @php
                                                        $scheduled = \App\Support\ReconnectionSchedule::format($consumer->reconnection_scheduled_at, 'M d, Y g:i A');
                                                    @endphp
                                                    <span class="badge bg-warning text-dark">Pending Reconnection</span>
                                                    <span class="consumer-installation-date">Scheduled: {{ $scheduled }} (Philippine time)</span>
                                                    <form method="POST" action="{{ route('consumers.reconnection-complete', ['consumer' => $consumer, 'reconnection' => $consumer->latestReconnection] + request()->query()) }}" class="installation-completion-form" data-reconnection data-consumer-name="{{ $consumer->full_name }}" data-account-number="{{ $consumer->account_number }}" data-scheduled-date="{{ $scheduled }} (Philippine time)" data-installation-status="Pending Reconnection">
                                                        @csrf
                                                        <button type="button" class="btn btn-sm btn-outline-success" data-installation-complete-trigger>Mark as Completed</button>
                                                    </form>
                                                @elseif ($consumer->latestReconnection?->completed_at)
                                                    <span class="badge bg-success">Completed</span>
                                                    <span class="consumer-installation-date">Reconnected: {{ \App\Support\ReconnectionSchedule::format($consumer->latestReconnection->completed_at, 'M d, Y g:i A') }} (Philippine time)</span>
                                                @else
                                                    @include('admin.installations.status', [
                                                        'completed' => $consumer->installation_completed_at !== null,
                                                        'completedAt' => $consumer->installation_completed_at,
                                                        'scheduledDate' => $consumer->installation_date,
                                                        'completionUrl' => route('consumers.installation-complete', ['consumer' => $consumer] + request()->query()),
                                                    ])
                                                @endif
                                                @if ($rescheduleUsage['used'] > 0 && ($consumer->reconnection_pending || $consumer->installation_date))
                                                    <span class="consumer-installation-date">{{ $rescheduleUsage['used'] }} of {{ $rescheduleUsage['limit'] }} used</span>
                                                @endif
                                                @if ($scheduleEmailStatus)
                                                    <span class="consumer-installation-date">Schedule email: {{ $scheduleEmailStatus }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                    <td class="text-end" @if ($isInactiveAccounts) role="cell" @endif>
                                        <div class="consumer-actions {{ $filters['account_category'] === 'active' ? 'consumer-actions-active' : '' }}">
                                        @if ($isInactiveAccounts)
                                            @include('admin.consumers.reconnect-action')
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#consumerEditModal" data-consumer='{{ $consumerPayload }}'>Edit</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#consumerDetailsModal" data-consumer-details='{{ $consumerPayload }}'>View Details</button>
                                        @else
                                        @if ($filters['account_category'] !== 'active')
                                        @foreach ($consumerPendingReschedules as $reschedule)
                                            @php
                                                $reconnection = $reschedule->isReconnection() ? $reschedule->reconnection : null;
                                                $requestUsage = app(\App\Services\InstallationRescheduleService::class)->usage($consumer, $reconnection);
                                                $requestHistory = $rescheduleHistoryByConsumer[$consumer->id] ?? collect();
                                                if ($reconnection) {
                                                    $requestHistory = $requestHistory->where('consumer_reconnection_id', $reconnection->id);
                                                }
                                                $reschedulePayload = base64_encode(json_encode([
                                                    'id' => $reschedule->id,
                                                    'consumer_id' => $consumer->id,
                                                    'consumer_name' => $consumer->full_name,
                                                    'account_number' => $consumer->account_number ?: $consumer->application_reference,
                                                    'reschedule_type' => $reschedule->reschedule_type,
                                                    'is_loan' => $reschedule->isLoan(),
                                                    'is_reconnection' => $reschedule->isReconnection(),
                                                    'preferred_date' => $reschedule->preferred_installation_date?->toDateString(),
                                                    'preferred_time' => $reschedule->preferred_installation_time ? substr($reschedule->preferred_installation_time, 0, 5) : '',
                                                    'preferred_schedule' => $reschedule->formattedPreferredSchedule(),
                                                    'usage' => $requestUsage,
                                                    'original_date' => optional($reschedule->original_installation_date)->toDateString(),
                                                    'original_schedule_formatted' => $reschedule->formattedOriginalSchedule(),
                                                    'original_time' => $reschedule->original_installation_time ? substr((string) $reschedule->original_installation_time, 0, 5) : '',
                                                    'reason' => $reschedule->reason,
                                                    'created_at' => $reschedule->created_at->format('M d, Y g:i A'),
                                                    'assign_url' => route('installation-reschedules.assign', $reschedule),
                                                    'history' => $requestHistory->map(fn ($h) => [
                                                        'status' => ucfirst($h->status),
                                                        'original' => $h->formattedOriginalSchedule(),
                                                        'new' => $h->formattedNewSchedule(),
                                                        'reason' => $h->reason,
                                                        'admin_notes' => $h->admin_notes,
                                                        'reviewed_at' => optional($h->reviewed_at)->format('M d, Y'),
                                                    ])->values()->all(),
                                                ]));
                                            @endphp
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-info text-white me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#adminReviewRescheduleModal"
                                                data-reschedule="{{ $reschedulePayload }}"
                                                title="Assign a new {{ $reschedule->isReconnection() ? 'reconnection' : 'installation' }} date and time"
                                                @disabled(! $requestUsage['available'])
                                            >
                                                <i class="bi bi-calendar2-check me-1"></i>Assign New Schedule
                                            </button>
                                        @endforeach
                                        @endif
                                        @if (in_array($consumer->id, $pendingEmailVerificationIds, true))
                                            <span class="badge bg-warning text-dark me-2">Email verification pending</span>
                                            <form method="POST" action="{{ route('consumers.resend-email-verification', $consumer) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-primary me-2">Resend Email Verification</button></form>
                                        @endif
                                        @if ($filters['account_category'] !== 'active' && ! $awaitingRequirements && $consumer->hasPendingMembershipVerification() && ! $canVerifyConsumer)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerEditModal"
                                                data-consumer='{{ $consumerPayload }}'
                                                title="Review the existing VVTHOA membership claim"
                                            >
                                                <i class="bi bi-person-check me-1"></i>Review Membership
                                            </button>
                                        @endif
                                        {{-- Profile details stay editable for verified accounts,
                                             including those awaiting reconnection. --}}
                                        @if ($filters['account_category'] === 'inactive')
                                            @include('admin.consumers.reconnect-action')
                                        @endif
                                        @if (in_array($consumer->status, ['Active', 'For Review', 'Disconnected', 'For Reconnection'], true))
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerEditModal"
                                                data-consumer='{{ $consumerPayload }}'
                                            >
                                                Edit
                                            </button>
                                        @endif
                                        @if ($filters['account_category'] !== 'active' && $canVerifyConsumer)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-success me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerActivateModal"
                                                data-consumer="{{ $consumerPayload }}"
                                            >
                                                <i class="bi bi-check-circle me-1"></i>
                                                Verify
                                            </button>
                                        @endif
                                        @if ($filters['account_category'] !== 'active' && $consumer->status === 'Rejected' && $consumer->email)
                                            <form
                                                method="POST"
                                                action="{{ route('consumers.resend-correction-link', $consumer) }}"
                                                class="d-inline"
                                                onsubmit="return confirm('Re-send the correction link to {{ $consumer->email }}?');"
                                            >
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger me-2" title="Send a fresh Edit and Resubmit link">
                                                    <i class="bi bi-envelope-arrow-up me-1"></i>Re-send Correction Link
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#consumerDetailsModal" data-consumer-details='{{ $consumerPayload }}'>View Details</button>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr @if ($isInactiveAccounts) role="row" @endif>
                                    <td colspan="{{ $filters['account_category'] === 'active' ? 8 : 7 }}" class="text-center {{ $isInactiveAccounts ? 'py-3' : 'py-5' }}" @if ($isInactiveAccounts) role="cell" @endif>
                                        <div class="text-secondary mb-2">{{ $filters['account_category'] === 'active' ? 'No active accounts match the current filters.' : 'No consumers found.' }}</div>
                                        @if ($isAllConsumers)
                                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#consumerModal">
                                                    Add the first consumer
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3" id="consumerAccountsPagination">
                    <p class="text-secondary mb-0">
                        @if ($consumers->total() > 0)
                            Showing {{ $consumers->firstItem() }} to {{ $consumers->lastItem() }} of {{ $consumers->total() }} consumers
                        @else
                            No consumer records yet
                        @endif
                    </p>
                    {{ $consumers->links() }}
                </div>
                @if ($filters['account_category'] === 'active')
                </section>
                @endif
            </div>
        </div>
    </main>
</div>



@include('admin.consumers.connection-application-modal')

<div class="modal fade" id="consumerEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5">Edit Consumer</h2>
                    <div class="mt-1" id="consumerEditStatusBadge"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="#" id="consumerEditForm">
                    @csrf
                    @method('PATCH')
                    <div class="edit-consumer-section">
                        <div class="edit-consumer-section-heading">Personal Information</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="edit_full_name">Full Name</label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" value="{{ old('full_name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="edit_email">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" value="{{ old('email') }}">
                                <div class="form-text">A changed address must be verified before new login details are sent.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="edit_contact_number">Contact Number</label>
                                <input type="text" inputmode="numeric" pattern="[0-9]{10,15}" class="form-control js-digits-only" id="edit_contact_number" name="contact_number" value="{{ old('contact_number') }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold" for="edit_service_address">Service Address</label>
                                <input type="text" class="form-control" id="edit_service_address" name="service_address" value="{{ old('service_address') }}">
                            </div>
                        </div>
                    </div>

                    <div class="edit-consumer-section">
                        <div class="edit-consumer-section-heading">Account Information</div>
                        <div class="row g-2">
                            <div class="col-md-4 edit-account-identifier">
                                <label class="form-label fw-semibold" for="edit_account_number">Account Number</label>
                                <input type="text" class="form-control" id="edit_account_number" name="account_number" value="{{ old('account_number') }}">
                            </div>
                            <div class="col-md-4 edit-account-identifier">
                                <label class="form-label fw-semibold" for="edit_meter_number">Meter Number</label>
                                <input type="text" class="form-control" id="edit_meter_number" value="{{ old('meter_number') }}" readonly>
                            </div>
                            <div class="col-md-4" id="edit_status_field">
                                <label class="form-label fw-semibold" for="edit_status">Account Status</label>
                                <select class="form-select" id="edit_status" name="status" required data-status-locked-on="Disconnected">
                                    <option value="Active">Active</option>
                                    <option value="Disconnected">Disconnected</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                                <div class="form-text d-none" id="edit_status_locked_note">Status is locked while the account is awaiting reconnection.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="edit_reconnection_date">Reconnection Date</label>
                                <input type="date" class="form-control" id="edit_reconnection_date" name="reconnection_date" value="{{ old('reconnection_date') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="edit_installation_date">Installation Date</label>
                                <input type="date" class="form-control" id="edit_installation_date" name="installation_date" value="{{ old('installation_date') }}">
                            </div>
                        </div>
                    </div>

                    <div class="edit-consumer-section">
                        <div class="edit-consumer-section-heading">Property Information</div>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="edit_property_type">Property Type</label>
                                <select class="form-select" id="edit_property_type" name="property_type">
                                    <option value="">Select property type</option>
                                    <option value="Residential">Residential</option>
                                    <option value="Commercial">Commercial</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="edit_ownership_status">Ownership Status</label>
                                <select class="form-select" id="edit_ownership_status" name="ownership_status">
                                    <option value="">Select ownership status</option>
                                    <option value="Owner">Owner</option>
                                    <option value="Tenant">Tenant</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="edit_address_block">Block</label>
                                <input type="text" class="form-control" id="edit_address_block" name="address_block" value="{{ old('address_block') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="edit_address_street">Street</label>
                                <input type="text" class="form-control" id="edit_address_street" name="address_street" value="{{ old('address_street') }}">
                            </div>
                        </div>
                    </div>

                    <div class="edit-consumer-section">
                        <div class="edit-consumer-section-heading">Membership Information</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="edit_membership_type">Membership Type</label>
                                <select class="form-select" id="edit_membership_type" name="membership_type" required>
                                    @foreach ($membershipTypes as $membershipType)
                                        <option value="{{ $membershipType }}">{{ $membershipType }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold" for="edit_membership_verification_notes">Membership Verification Notes</label>
                                <textarea class="form-control" id="edit_membership_verification_notes" name="membership_verification_notes" rows="2" maxlength="1000" placeholder="Optional: record what was checked or why the membership claim was not verified.">{{ old('membership_verification_notes') }}</textarea>
                                <div class="form-text">Changing the membership type records a new verification decision with your account and the current time.</div>
                            </div>
                            <div class="col-12 d-none" id="edit_membership_pending_panel">
                                <div class="alert alert-warning mb-0 py-2 px-3">
                                    <div class="fw-semibold small"><i class="bi bi-person-check me-1"></i>VVTHOA membership verification pending</div>
                                    <div class="small mt-1">Choosing <strong>Member</strong> verifies the existing membership and unlocks member benefits. Choosing <strong>Non-Member</strong> records the claim as not verified.</div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="edit_membership_verification_confirmed" name="membership_verification_confirmed" @checked(old('membership_verification_confirmed'))>
                                        <label class="form-check-label" for="edit_membership_verification_confirmed">I reviewed the membership evidence and confirm this decision.</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="edit-consumer-section">
                        <div class="edit-consumer-section-heading">Document Verification</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="edit_valid_id_presented">Valid ID Presented</label>
                                <input type="text" class="form-control" id="edit_valid_id_presented" name="valid_id_presented" value="{{ old('valid_id_presented') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="edit_other_document_details">Other Document Details</label>
                                <input type="text" class="form-control" id="edit_other_document_details" name="other_document_details" value="{{ old('other_document_details') }}">
                            </div>
                        </div>
                        <div class="row edit-consumer-checks mt-2">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_housing_rights_documented" name="housing_rights_documented" @checked(old('housing_rights_documented'))>
                                <label class="form-check-label" for="edit_housing_rights_documented">Housing rights documented</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_resident_of_community" name="resident_of_community" @checked(old('resident_of_community'))>
                                <label class="form-check-label" for="edit_resident_of_community">Resident of community</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_association_member" name="association_member" @checked(old('association_member'))>
                                <label class="form-check-label" for="edit_association_member">Association member</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_association_registered" name="association_registered" @checked(old('association_registered'))>
                                <label class="form-check-label" for="edit_association_registered">Association registered</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_housing_loan_contract_submitted" name="housing_loan_contract_submitted" @checked(old('housing_loan_contract_submitted'))>
                                <label class="form-check-label" for="edit_housing_loan_contract_submitted">Housing loan contract submitted</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_valid_id_submitted" name="valid_id_submitted" @checked(old('valid_id_submitted'))>
                                <label class="form-check-label" for="edit_valid_id_submitted">Valid ID submitted</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_proof_of_residency_submitted" name="proof_of_residency_submitted" @checked(old('proof_of_residency_submitted'))>
                                <label class="form-check-label" for="edit_proof_of_residency_submitted">Proof of residency submitted</label>
                            </div>
                        </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="consumerEditForm" class="btn btn-primary">Update Consumer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="consumerDetailsModal" tabindex="-1" aria-labelledby="consumerDetailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="consumerDetailsModalTitle">Consumer Details</h2>
                    <div class="small text-secondary" id="consumerDetailsModalSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="consumerDetailsModalBody">
                <div class="text-center text-secondary py-4">Select a consumer to view details.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    <dialog id="resendLoginDetailsModal" aria-labelledby="resendLoginDetailsTitle" aria-describedby="resendLoginDetailsDescription">
        <div class="modal-content">
            <div class="modal-header border-0 pb-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="resend-confirm-icon flex-shrink-0" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                    <div>
                        <h2 class="modal-title fs-5 mb-1" id="resendLoginDetailsTitle">Resend Login Details?</h2>
                        <p class="small text-secondary mb-0" id="resendLoginDetailsDescription">Send the account details to the registered email? The current password will remain valid.</p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-2" data-resend-dismiss aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="small text-secondary">Registered email address</div>
                <div class="resend-confirm-email" id="resendLoginDetailsEmail"></div>
            </div>
            <div class="modal-footer border-0 pt-2">
                <button type="button" class="btn btn-outline-secondary" id="resendLoginDetailsCancel" data-resend-dismiss autofocus>Cancel</button>
                <button type="button" class="btn btn-primary" id="resendLoginDetailsSend">
                    <i class="bi bi-send me-1" aria-hidden="true"></i><span>Send Email</span>
                </button>
            </div>
        </div>
    </dialog>
</div>

<template id="consumerDetailsAccountAccessTemplate">
    <section class="consumer-details-section" aria-labelledby="consumerDetailsAccountAccessTitle">
        <h3 id="consumerDetailsAccountAccessTitle">Account Access</h3>
        <p class="small text-secondary">Send the account access email to the verified email address. The current password remains valid.</p>
        <form method="POST" action="#" class="resend-login-form" data-consumer-email="">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-envelope me-1" aria-hidden="true"></i>Resend Login Details
            </button>
        </form>
    </section>
</template>

<template id="consumerDetailsReactivationEmailTemplate">
    <section class="consumer-details-section" aria-labelledby="consumerDetailsReactivationEmailTitle">
        <h3 id="consumerDetailsReactivationEmailTitle">Reconnection Schedule Email</h3>
        <p class="small text-secondary" data-schedule-email-status></p>
        <form method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">Resend Reconnection Schedule Email</button>
        </form>
    </section>
</template>

<div class="modal fade" id="consumerActivateModal" tabindex="-1" aria-labelledby="consumerReviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div class="wizard-header-top">
                    <div>
                        <h2 class="modal-title fs-5" id="consumerReviewTitle">Verify Connection Application</h2>
                        <div class="mt-1" id="consumerActivateStatusBadge"></div>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close"></button>
                </div>
                <ol class="wizard-progress" aria-label="Application review progress">
                    <li><span>1</span>Applicant</li><li><span>2</span>Requirements</li><li><span>3</span>Decision</li><li><span>4</span>Details</li><li><span>5</span>Confirm</li>
                </ol>
            </div>
            <div class="modal-body">
                @if ($errors->any() && session('consumer_modal') === 'activate')
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div id="activateInfoContent">
                    <div class="text-center text-secondary py-3">Loading applicant information...</div>
                </div>
                <section id="reviewMissingRequirements" class="review-section" hidden>
                    <h3 class="review-section-heading">Missing Requirements</h3>
                    <ul class="mb-0" id="reviewMissingRequirementsList" hidden></ul>
                    <p class="text-secondary mb-0" id="reviewMissingRequirementsEmpty" hidden>No missing requirements identified.</p>
                </section>
                <div id="reviewRequirementsConfirmation" class="review-section form-check" hidden>
                    <input type="checkbox" class="form-check-input ms-0 me-2" id="reviewRequirementsChecked">
                    <label class="form-check-label fw-semibold" for="reviewRequirementsChecked">I have reviewed the submitted connection requirements.</label>
                </div>

                <section class="review-section" id="reviewDecision" hidden aria-labelledby="review-decision-heading">
                    <h3 class="review-section-heading" id="review-decision-heading">Application Decision</h3>
                    <p class="small text-secondary">Choose a decision to continue.</p>
                    <p class="alert alert-warning" id="reviewApprovalUnavailable" hidden>Approval becomes available after the applicant submits the missing requirements.</p>
                    <label class="wizard-choice"><input type="radio" name="review_decision" value="approve"> <strong>Approve Application</strong><span class="d-block small text-secondary ms-4">Enter membership and account activation details.</span></label>
                    <label class="wizard-choice"><input type="radio" name="review_decision" value="reject"> <strong>Reject Application</strong><span class="d-block small text-secondary ms-4">Select corrections and provide a reason.</span></label>
                </section>
                <form method="POST" action="#" id="consumerActivateForm" class="review-stack" hidden>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="requirements_reviewed" value="0">
                    <section class="review-section" aria-labelledby="review-membership-heading">
                    <h3 class="review-section-heading review-section-heading--tinted" id="review-membership-heading">Activation &amp; Membership Decision</h3>
                    <p class="small text-secondary mb-3">
                        Approving here activates the account, records the membership decision, and marks the linked water connection request as approved.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_membership_type">Membership Type</label>
                        <select class="form-select" id="activation_membership_type" name="membership_type" required>
                            @foreach ($membershipTypes as $membershipType)
                                <option value="{{ $membershipType }}" @selected(old('membership_type') === $membershipType)>{{ $membershipType }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="activation_membership_type_help"></div>
                    </div>
                    <div class="alert alert-warning d-none" id="activation_membership_pending_alert">
                        <div class="fw-semibold"><i class="bi bi-person-check me-1"></i>This applicant declared existing VVTHOA membership.</div>
                        <div class="small mt-1">Activating as <strong>Member</strong> verifies the existing membership and applies member billing rates. Activating as <strong>Non-Member</strong> records the claim as not verified. The water connection request is marked approved either way.</div>
                    </div>
                    <div class="border rounded-3 p-3 mb-3 d-none" id="activation_membership_confirmation">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="activation_membership_verification_confirmed" name="membership_verification_confirmed" @checked(old('membership_verification_confirmed'))>
                            <label class="form-check-label fw-semibold" for="activation_membership_verification_confirmed">
                                I have reviewed the applicant's membership claim and supporting documents and confirm that this applicant is an officially registered VVTHOA member.
                            </label>
                            <div class="invalid-feedback" id="activation_membership_confirmation_error">Please confirm that the applicant's association membership has been verified.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_membership_verification_notes">Membership Verification Notes</label>
                        <textarea class="form-control" id="activation_membership_verification_notes" name="membership_verification_notes" rows="2" maxlength="1000" placeholder="Optional: record what was checked or why a claim was not verified.">{{ old('membership_verification_notes') }}</textarea>
                    </div>
                    </section>
                    <section class="review-section" aria-labelledby="review-meter-heading">
                    <h3 class="review-section-heading" id="review-meter-heading">Meter &amp; Account Activation</h3>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_meter_number">Meter Number</label>
                        <input type="text" class="form-control" id="activation_meter_number" name="meter_number" value="{{ old('meter_number') }}" placeholder="Enter meter number" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold" for="activation_installation_date">Installation Date</label>
                        <input type="date" class="form-control" id="activation_installation_date" name="installation_date" value="{{ old('installation_date') }}" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold" for="activation_installation_time">Installation Time</label>
                        <input type="time" class="form-control" id="activation_installation_time" name="installation_time" value="{{ old('installation_time') }}" required>
                    </div>
                    </section>
                </form>

                <div id="activateRejectSection" class="review-section" hidden>
                    <h6 class="fw-bold mb-3 text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Reject Application
                    </h6>
                    <p class="small text-secondary">
                        Rejecting the application marks the water connection request as rejected. A still-pending membership claim is also recorded as not verified; an already-verified membership is retained.
                    </p>
                    <form method="POST" action="#" id="consumerRejectForm">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="requirements_reviewed" value="0">
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Requirements to Correct <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                @foreach ($correctionFieldOptions as $fieldKey => $fieldLabel)
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="correction_fields[]"
                                                value="{{ $fieldKey }}"
                                                id="correction_field_{{ $fieldKey }}"
                                                @checked(in_array($fieldKey, old('correction_fields', []), true))
                                                @if ($loop->first) required @endif
                                            >
                                            <label class="form-check-label" for="correction_field_{{ $fieldKey }}">{{ $fieldLabel }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text">
                                Only the selected fields and documents can be edited by the applicant when they resubmit.
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="rejection_reason">Rejection Reason / Admin Notes <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="Explain why this connection request is being rejected..." required>{{ old('rejection_reason') }}</textarea>
                        </div>
                    </form>
                </div>
                <section class="review-section" id="reviewConfirmation" hidden aria-labelledby="review-confirmation-heading">
                    <h3 class="review-section-heading" id="review-confirmation-heading">Final Confirmation</h3>
                    <p class="small text-secondary">Review the decision before submitting.</p>
                    <dl class="wizard-summary mb-0" id="reviewSummary"></dl>
                </section>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <div class="wizard-footer-actions">
                    <button type="button" class="btn btn-outline-primary" id="reviewBack" hidden>&larr; Back</button>
                    <button type="button" class="btn btn-primary" id="reviewNext">Next &rarr;</button>
                    <button type="button" class="btn btn-success" id="reviewConfirm" hidden>Confirm Approval</button>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.consumers.assign-reschedule-modal')
@if ($filters['account_category'] === 'inactive')
    @include('admin.consumers.reconnect-modal')
@endif

@if ($filters['account_category'] === 'active')
    @foreach ($disconnectionNotices as $notice)
        @include('admin.consumers.disconnection-notice-details', ['notice' => $notice])
    @endforeach

    @if ($disconnectionNotices?->getCollection()->contains('can_confirm_disconnection', true))
        @include('admin.consumers.disconnection-modal')
    @endif

    @include('admin.installations.completion-modal')
@endif

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const activeAccountFilters = document.querySelector('.active-account-filters');
            if (activeAccountFilters) {
                const accountTable = document.getElementById('consumer-accounts-table');
                const tableViewport = accountTable.parentElement;
                const membershipColumn = accountTable.querySelector('[data-account-filter-column="membership"]');
                const installationColumn = accountTable.querySelector('[data-account-filter-column="installation_status"]');

                const alignAccountFilters = () => {
                    const formLeft = activeAccountFilters.getBoundingClientRect().left;
                    const columnStart = column => column.getBoundingClientRect().left - formLeft
                        + parseFloat(getComputedStyle(column).paddingLeft);
                    const membershipStart = columnStart(membershipColumn);
                    const installationStart = columnStart(installationColumn);
                    const rem = parseFloat(getComputedStyle(document.documentElement).fontSize);
                    const viewportRight = tableViewport.getBoundingClientRect().left + tableViewport.clientWidth;
                    // Use the starting position to keep the layout stable as the table scrolls.
                    const installationRoom = viewportRight - formLeft - installationStart - tableViewport.scrollLeft;
                    const canAlign = installationRoom >= 15 * rem;

                    activeAccountFilters.classList.toggle('is-column-aligned', canAlign);
                    if (canAlign) {
                        activeAccountFilters.style.setProperty('--membership-filter-start', `${membershipStart}px`);
                        activeAccountFilters.style.setProperty('--installation-filter-start', `${installationStart}px`);
                    }
                };

                const columnObserver = new ResizeObserver(alignAccountFilters);
                columnObserver.observe(accountTable);
                columnObserver.observe(tableViewport);
                tableViewport.addEventListener('scroll', alignAccountFilters, { passive: true });
                alignAccountFilters();
            }

            const pendingFilters = document.getElementById('pendingTypeFilters');
            if (pendingFilters) {
                const headings = @json($pendingHeadings);
                const title = document.getElementById('consumerPageTitle');
                const description = document.getElementById('consumerPageDescription');
                const table = document.getElementById('consumer-accounts-table');
                const pagination = document.getElementById('consumerAccountsPagination');
                const loading = document.getElementById('pendingLoading');
                const pendingInput = document.querySelector('#account-filters input[name="pending_type"]');
                const pendingClear = document.querySelector('#account-filters [data-pending-clear]');
                let pendingRequest;

                async function selectPendingType(url, pushHistory) {
                    const selected = new URL(url, window.location.href);
                    const type = headings[selected.searchParams.get('pending_type')] ? selected.searchParams.get('pending_type') : 'all';
                    title.textContent = headings[type][0];
                    description.textContent = headings[type][1];
                    pendingFilters.querySelectorAll('[data-pending-type]').forEach(function (link) {
                        const active = link.dataset.pendingType === type;
                        link.classList.toggle('btn-primary', active);
                        link.classList.toggle('btn-outline-secondary', !active);
                        if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
                    });
                    if (pendingInput) pendingInput.value = type;
                    if (pendingClear) {
                        const clearUrl = new URL(pendingClear.href);
                        clearUrl.searchParams.set('pending_type', type);
                        pendingClear.href = clearUrl.href;
                    }
                    if (pendingRequest) pendingRequest.abort();
                    const controller = new AbortController();
                    pendingRequest = controller;
                    table.setAttribute('aria-busy', 'true');
                    table.tBodies[0].hidden = true;
                    pagination.style.setProperty('display', 'none', 'important');
                    loading.hidden = false;

                    try {
                        const response = await fetch(selected.href, { signal: controller.signal, credentials: 'same-origin' });
                        if (!response.ok || response.redirected) throw new Error('Unable to load pending records');
                        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const newBody = doc.querySelector('#consumer-accounts-table tbody');
                        const newPagination = doc.getElementById('consumerAccountsPagination');
                        if (!newBody || !newPagination || !doc.getElementById('pendingTypeFilters')) throw new Error('Invalid response');
                        if (pendingRequest !== controller) return;
                        table.tBodies[0].replaceChildren(...newBody.childNodes);
                        pagination.replaceChildren(...newPagination.childNodes);
                        if (pushHistory) history.pushState(null, '', selected.href);
                        table.tBodies[0].hidden = false;
                        pagination.style.removeProperty('display');
                        loading.hidden = true;
                        table.removeAttribute('aria-busy');
                    } catch (error) {
                        if (error.name !== 'AbortError') window.location.assign(selected.href);
                    }
                }

                pendingFilters.addEventListener('click', function (event) {
                    const link = event.target.closest('[data-pending-type]');
                    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    event.preventDefault();
                    selectPendingType(link.href, true);
                });
                window.addEventListener('popstate', function () {
                    if (new URL(window.location.href).searchParams.get('account_category') === 'pending') {
                        selectPendingType(window.location.href, false);
                    } else {
                        window.location.reload();
                    }
                });
            }
            const activateModal = document.getElementById('consumerActivateModal');
            const activateForm = document.getElementById('consumerActivateForm');
            const editModal = document.getElementById('consumerEditModal');
            const editForm = document.getElementById('consumerEditForm');
            const rejectForm = document.getElementById('consumerRejectForm');
            const detailsModal = document.getElementById('consumerDetailsModal');
            const detailsModalBody = document.getElementById('consumerDetailsModalBody');
            const detailsModalTitle = document.getElementById('consumerDetailsModalTitle');
            const detailsModalSubtitle = document.getElementById('consumerDetailsModalSubtitle');
            const detailsAccountAccessTemplate = document.getElementById('consumerDetailsAccountAccessTemplate');
            const detailsReactivationEmailTemplate = document.getElementById('consumerDetailsReactivationEmailTemplate');
            const resendLoginDetailsModal = document.getElementById('resendLoginDetailsModal');
            const resendLoginDetailsEmail = document.getElementById('resendLoginDetailsEmail');
            const resendLoginDetailsSend = document.getElementById('resendLoginDetailsSend');
            if (detailsModal && resendLoginDetailsModal && resendLoginDetailsSend) {
                const dismissButtons = resendLoginDetailsModal.querySelectorAll('[data-resend-dismiss]');
                let resendLoginDetailsForm = null;

                detailsModal.addEventListener('submit', function (event) {
                    const form = event.target;
                    if (!form.matches('.resend-login-form')) return;
                    event.preventDefault();
                    resendLoginDetailsForm = form;
                    resendLoginDetailsEmail.textContent = form.dataset.consumerEmail || 'the registered email address';
                    resendLoginDetailsModal.showModal();
                });

                dismissButtons.forEach(button => button.addEventListener('click', () => resendLoginDetailsModal.close()));
                resendLoginDetailsModal.addEventListener('click', function (event) {
                    if (event.target === resendLoginDetailsModal && !resendLoginDetailsSend.disabled) {
                        resendLoginDetailsModal.close();
                    }
                });

                // Let the native dialog handle Escape without dismissing View Details.
                resendLoginDetailsModal.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') event.stopPropagation();
                });
                resendLoginDetailsModal.addEventListener('cancel', function (event) {
                    if (resendLoginDetailsSend.disabled) event.preventDefault();
                });

                resendLoginDetailsSend.addEventListener('click', function () {
                    if (!resendLoginDetailsForm || resendLoginDetailsSend.disabled) return;

                    resendLoginDetailsSend.disabled = true;
                    dismissButtons.forEach(button => { button.disabled = true; });
                    resendLoginDetailsSend.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><span>Sending…</span>';

                    // Submit the original CSRF-protected form to the existing resend route.
                    HTMLFormElement.prototype.submit.call(resendLoginDetailsForm);
                });

                window.addEventListener('pageshow', function (event) {
                    if (!event.persisted || !resendLoginDetailsSend.disabled) return;
                    resendLoginDetailsModal.close();
                    resendLoginDetailsSend.disabled = false;
                    dismissButtons.forEach(button => { button.disabled = false; });
                    resendLoginDetailsSend.innerHTML = '<i class="bi bi-send me-1" aria-hidden="true"></i><span>Send Email</span>';
                });
            }

            const escapeHtml = function (value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const hasDetailsValue = function (value) {
                if (value === null || value === undefined) return false;
                if (typeof value === 'string') {
                    const normalized = value.trim().toLowerCase();
                    return normalized !== '' && !['not provided', 'not recorded', 'unknown'].includes(normalized);
                }
                return true;
            };

            const renderDetailsSection = function (section) {
                const fields = section.fields.filter(field => hasDetailsValue(field.value));
                if (!fields.length) return '';

                return '<section class="consumer-details-section"><h3 class="fw-bold">' + escapeHtml(section.title)
                    + '</h3><div class="row">' + fields.map(function (field) {
                        return '<div class="col-sm-6"><div class="small text-secondary text-uppercase fw-bold mb-1">'
                            + escapeHtml(field.label) + '</div><div class="fw-medium">' + escapeHtml(field.value) + '</div></div>';
                    }).join('') + '</div></section>';
            };

            if (detailsModal && detailsModalBody) {
                detailsModal.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    if (!trigger) return;

                    const payload = JSON.parse(atob(trigger.getAttribute('data-consumer-details') || 'e30='));

                    if (detailsModalTitle) {
                        detailsModalTitle.textContent = payload.full_name ? payload.full_name + ' — Consumer Details' : 'Consumer Details';
                    }
                    if (detailsModalSubtitle) {
                        detailsModalSubtitle.textContent = hasDetailsValue(payload.account_number) ? 'Account ' + payload.account_number : '';
                    }

                    const sections = [
                        { title: 'Personal Information', fields: [
                            { label: 'Full Name', value: payload.full_name },
                            { label: 'Email Address', value: payload.email },
                            { label: 'Contact Number', value: payload.contact_number },
                            { label: 'Service Address', value: payload.service_address },
                            { label: 'Property Type', value: payload.property_type },
                            { label: 'Ownership Status', value: payload.ownership_status },
                        ] },
                        { title: 'Account Details', fields: [
                            { label: 'Account Number', value: payload.account_number },
                            { label: 'Meter Number', value: payload.meter_number },
                            { label: 'Account Status', value: payload.status_display?.label || payload.status },
                            { label: 'Registered On', value: payload.registered_at },
                        ] },
                        { title: 'Membership', fields: [
                            { label: 'Membership Type', value: payload.membership_type },
                            { label: 'Verification Status', value: payload.membership_verification_status },
                        ] },
                        { title: 'Connection Status', fields: [
                            { label: 'Connection Request Status', value: payload.connection_request_status },
                            { label: 'Installation Date', value: payload.installation_date },
                            { label: 'Installation Completed', value: payload.installation_completed_at },
                            { label: 'Completed By', value: payload.installation_completed_by },
                            { label: 'Original Reconnection Date', value: payload.reconnection_date },
                            { label: 'Scheduled Reconnection (Philippine time)', value: payload.reconnection_schedule },
                            { label: 'Reconnection Completed (Philippine time)', value: payload.reconnection_completed_at },
                        ] },
                        { title: 'Billing', fields: [
                            { label: 'Account Standing', value: payload.standing },
                        ] },
                    ];
                    detailsModalBody.innerHTML = '<div class="consumer-details-grid">' + sections.map(renderDetailsSection).join('') + '</div>';
                    const detailsGrid = detailsModalBody.querySelector('.consumer-details-grid');
                    if (payload.reconnection_email_status) {
                        const reactivationEmail = detailsReactivationEmailTemplate.content.cloneNode(true);
                        reactivationEmail.querySelector('[data-schedule-email-status]').textContent = payload.reconnection_email_status;
                        const retryForm = reactivationEmail.querySelector('form');
                        if (!payload.retry_reactivation_email_url) {
                            retryForm.remove();
                        } else {
                            const retryButton = retryForm.querySelector('button');
                            retryForm.action = payload.retry_reactivation_email_url;
                            retryForm.addEventListener('submit', function (event) {
                                if (retryButton.disabled) {
                                    event.preventDefault();
                                    return;
                                }
                                retryButton.disabled = true;
                                retryButton.textContent = 'Sending…';
                            });
                        }
                        detailsGrid.appendChild(reactivationEmail);
                    }
                    if (payload.resend_login_details_url && detailsAccountAccessTemplate && detailsGrid) {
                        const accountAccess = detailsAccountAccessTemplate.content.cloneNode(true);
                        const resendForm = accountAccess.querySelector('.resend-login-form');
                        resendForm.action = payload.resend_login_details_url;
                        resendForm.dataset.consumerEmail = payload.email || '';
                        detailsGrid.appendChild(accountAccess);
                    }
                    const history = document.createElement('div');
                    history.className = 'mt-3';
                    history.setAttribute('aria-live', 'polite');
                    history.textContent = 'Loading billing history…';
                    detailsModalBody.append(history);
                    fetch(payload.billing_history_url, { credentials: 'same-origin' }).then(response => {
                        if (!response.ok || response.redirected) throw new Error('Billing history unavailable');
                        return response.text();
                    }).then(html => {
                        history.innerHTML = html;
                    }).catch(() => {
                        history.textContent = 'Billing history could not be loaded.';
                    });
                });
            }

            const renderConsumerStatusBadge = function (elementId, display) {
                const target = document.getElementById(elementId);

                if (! target) {
                    return;
                }

                target.innerHTML = '';

                if (! display || ! display.label) {
                    return;
                }

                const wrapper = document.createElement('span');
                wrapper.className = 'consumer-service-status';

                if (display.description) {
                    wrapper.title = display.description;
                }

                const badge = document.createElement('span');
                badge.className = 'badge consumer-status-badge ' + (display.badge_class || 'badge-soft-primary');

                const icon = document.createElement('i');
                icon.className = 'bi ' + (display.icon || 'bi-info-circle');
                icon.setAttribute('aria-hidden', 'true');

                const label = document.createElement('span');
                label.textContent = display.label;

                badge.append(icon, label);

                const subLabel = document.createElement('span');
                subLabel.className = 'consumer-status-sub ' + (display.sub_class || '');
                subLabel.textContent = display.sub_label || '';

                wrapper.append(badge, subLabel);
                target.appendChild(wrapper);
            };

            // Contact numbers accept numeric digits only.
            document.querySelectorAll('.js-digits-only').forEach(function (input) {
                input.addEventListener('input', function () {
                    const cleaned = input.value.replace(/[^0-9]/g, '').slice(0, 15);

                    if (cleaned !== input.value) {
                        input.value = cleaned;
                    }
                });
            });

            if (activateModal && activateForm) {
                const reviewDecision = document.getElementById('reviewDecision');
                const reviewConfirmation = document.getElementById('reviewConfirmation');
                const reviewInfo = document.getElementById('activateInfoContent');
                const reviewBack = document.getElementById('reviewBack');
                const reviewNext = document.getElementById('reviewNext');
                const reviewConfirm = document.getElementById('reviewConfirm');
                const reviewSummary = document.getElementById('reviewSummary');
                const reviewRequirements = document.getElementById('reviewRequirementsConfirmation');
                const requirementsChecked = document.getElementById('reviewRequirementsChecked');
                const missingRequirements = document.getElementById('reviewMissingRequirements');
                const missingRequirementsList = document.getElementById('reviewMissingRequirementsList');
                const missingRequirementsEmpty = document.getElementById('reviewMissingRequirementsEmpty');
                const approvalUnavailable = document.getElementById('reviewApprovalUnavailable');
                const reviewProgress = Array.from(activateModal.querySelectorAll('.wizard-progress li'));
                const reviewRadios = Array.from(reviewDecision.querySelectorAll('input[name="review_decision"]'));
                const corrections = Array.from(rejectForm.querySelectorAll('input[name="correction_fields[]"]'));
                let reviewStep = 1, reviewPath = '', reviewDirty = false, reviewSubmitting = false, reviewApplicant = '';
                let reviewClosingWithX = false, reviewDiscardOnHidden = false, reviewIgnoreRecovery = false;

                function syncCorrections() {
                    if (corrections.length) corrections[0].required = !corrections.some(input => input.checked);
                }

                function syncRequirementsReview() {
                    [activateForm, rejectForm].forEach(form => {
                        form.elements.requirements_reviewed.value = requirementsChecked.checked ? '1' : '0';
                    });
                    reviewNext.disabled = (reviewStep === 2 && !requirementsChecked.checked) || (reviewStep === 3 && !reviewPath);
                }

                function showReviewStep() {
                    reviewInfo.hidden = reviewStep > 2;
                    reviewRequirements.hidden = reviewStep !== 2;
                    missingRequirements.hidden = reviewStep !== 2;
                    reviewInfo.querySelectorAll('[data-review-info-step]').forEach(section => {
                        section.hidden = Number(section.dataset.reviewInfoStep) !== reviewStep;
                    });
                    reviewDecision.hidden = reviewStep !== 3;
                    activateForm.hidden = reviewStep !== 4 || reviewPath !== 'approve';
                    document.getElementById('activateRejectSection').hidden = reviewStep !== 4 || reviewPath !== 'reject';
                    reviewConfirmation.hidden = reviewStep !== 5;
                    reviewBack.hidden = reviewStep === 1;
                    reviewNext.hidden = reviewStep === 5;
                    syncRequirementsReview();
                    reviewNext.textContent = reviewStep === 4 ? 'Continue to Confirmation →' : 'Next →';
                    reviewConfirm.hidden = reviewStep !== 5;
                    reviewConfirm.disabled = reviewSubmitting;
                    reviewConfirm.textContent = reviewPath === 'reject' ? 'Confirm Rejection' : 'Confirm Approval';
                    reviewConfirm.classList.toggle('btn-success', reviewPath !== 'reject');
                    reviewConfirm.classList.toggle('btn-danger', reviewPath === 'reject');
                    reviewProgress.forEach((item, index) => {
                        item.classList.toggle('is-complete', index + 1 < reviewStep);
                        if (index + 1 === reviewStep) item.setAttribute('aria-current', 'step');
                        else item.removeAttribute('aria-current');
                    });
                    activateModal.querySelector('.modal-body').scrollTop = 0;
                }

                function showReviewSummary() {
                    reviewSummary.replaceChildren();
                    const add = (label, value) => {
                        const term = document.createElement('dt'), detail = document.createElement('dd');
                        term.textContent = label;
                        detail.textContent = value || 'Not provided';
                        reviewSummary.append(term, detail);
                    };
                    add('Decision', reviewPath === 'approve' ? 'Approve Application' : 'Reject Application');
                    add('Applicant Name', reviewApplicant);
                    if (reviewPath === 'approve') {
                        add('Membership Type', document.getElementById('activation_membership_type').value);
                        const confirmation = document.getElementById('activation_membership_verification_confirmed');
                        add('Membership Confirmation', confirmation.required ? (confirmation.checked ? 'Confirmed' : 'Not confirmed') : 'Not required');
                        add('Meter Number', document.getElementById('activation_meter_number').value);
                        add('Installation Date', document.getElementById('activation_installation_date').value);
                        add('Membership Verification Notes', document.getElementById('activation_membership_verification_notes').value);
                    } else {
                        add('Requirements to Correct', corrections.filter(input => input.checked).map(input => rejectForm.querySelector('label[for="' + input.id + '"]').textContent.trim()).join(', '));
                        add('Rejection Reason', document.getElementById('rejection_reason').value);
                    }
                }

                reviewRadios.forEach(radio => radio.addEventListener('change', function () {
                    reviewPath = this.value;
                    reviewDirty = true;
                    showReviewStep();
                }));
                corrections.forEach(input => input.addEventListener('change', syncCorrections));
                [activateForm, rejectForm].forEach(form => {
                    form.addEventListener('input', () => { reviewDirty = true; });
                    form.addEventListener('change', () => { reviewDirty = true; });
                    form.addEventListener('submit', function (event) {
                        const selected = reviewPath === 'approve' ? activateForm : rejectForm;
                        if (reviewStep !== 5 || selected !== form || reviewSubmitting) {
                            event.preventDefault();
                            return;
                        }
                        reviewSubmitting = true;
                        reviewDirty = false;
                        reviewConfirm.disabled = true;
                    });
                });
                reviewBack.addEventListener('click', function () {
                    if (reviewStep > 1) { reviewStep--; showReviewStep(); }
                });
                reviewNext.addEventListener('click', function () {
                    if (reviewStep === 2 && !requirementsChecked.checked) return;
                    if (reviewStep === 3 && !reviewPath) return;
                    if (reviewStep === 4) {
                        syncCorrections();
                        const form = reviewPath === 'approve' ? activateForm : rejectForm;
                        if (!form.reportValidity()) return;
                        showReviewSummary();
                    }
                    if (reviewStep < 5) { reviewStep++; showReviewStep(); }
                });
                requirementsChecked.addEventListener('change', syncRequirementsReview);
                reviewConfirm.addEventListener('click', function () {
                    const form = reviewPath === 'approve' ? activateForm : rejectForm;
                    if (!form.checkValidity()) { reviewStep = 4; showReviewStep(); form.reportValidity(); return; }
                    if (!reviewSubmitting) form.requestSubmit();
                });
                let reviewXPending = false;
                function closeReviewWithX() {
                    reviewClosingWithX = true;
                    try {
                        bootstrap.Modal.getOrCreateInstance(activateModal).hide();
                    } finally {
                        reviewClosingWithX = false;
                    }
                }
                activateModal.querySelector('.wizard-header-top .btn-close').addEventListener('click', function (event) {
                    event.stopPropagation();
                    const modal = bootstrap.Modal.getOrCreateInstance(activateModal);
                    if (modal._isTransitioning && modal._isShown) {
                        if (!reviewXPending) {
                            reviewXPending = true;
                            activateModal.addEventListener('shown.bs.modal', function () {
                                reviewXPending = false;
                                closeReviewWithX();
                            }, { once: true });
                        }
                        return;
                    }
                    closeReviewWithX();
                });
                activateModal.addEventListener('hide.bs.modal', function (event) {
                    if (reviewClosingWithX) {
                        reviewDiscardOnHidden = true;
                        return;
                    }
                    if (reviewDirty && !reviewSubmitting && !window.confirm('Discard changes to this application review?')) event.preventDefault();
                });
                activateModal.addEventListener('hidden.bs.modal', function () {
                    if (!reviewDiscardOnHidden) return;
                    reviewDiscardOnHidden = false;
                    reviewIgnoreRecovery = true;
                    reviewDirty = false;
                    reviewSubmitting = false;
                    reviewStep = 1;
                    reviewPath = '';
                    activateForm.reset();
                    rejectForm.reset();
                    reviewRadios.forEach(radio => { radio.checked = false; });
                    reviewSummary.replaceChildren();
                    activateModal.querySelector('.modal-body > .alert-danger')?.remove();
                    syncCorrections();
                    showReviewStep();
                });

                activateModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));
                    const consumerId = payload.id;
                    const reviewRecover = !reviewIgnoreRecovery && @json($errors->any() && session('consumer_modal') === 'activate') && String(consumerId) === String(@json(session('consumer_activate_id')));
                    const awaitingRequirements = payload.awaiting_requirements;
                    approvalUnavailable.hidden = !awaitingRequirements;
                    reviewDecision.querySelector('[value="approve"]').disabled = awaitingRequirements;
                    missingRequirementsList.hidden = payload.missing_requirements.length === 0;
                    missingRequirementsEmpty.hidden = !missingRequirementsList.hidden;
                    missingRequirementsList.replaceChildren(...payload.missing_requirements.map(requirement => {
                        const item = document.createElement('li');
                        item.textContent = requirement;
                        return item;
                    }));
                    reviewApplicant = payload.full_name || '';
                    reviewSubmitting = false;
                    reviewDirty = false;
                    requirementsChecked.checked = reviewRecover && Boolean(Number(@json(old('requirements_reviewed', '0'))));
                    reviewStep = reviewRecover ? (requirementsChecked.checked ? 4 : 2) : 1;
                    reviewPath = reviewRecover ? (@json(old('rejection_reason')) !== null ? 'reject' : 'approve') : '';
                    if (awaitingRequirements && reviewPath === 'approve') {
                        reviewStep = 2;
                        reviewPath = '';
                    }
                    reviewRadios.forEach(radio => { radio.checked = radio.value === reviewPath; });

                    renderConsumerStatusBadge('consumerActivateStatusBadge', payload.status_display);

                    activateForm.action = `{{ url('/consumers') }}/${consumerId}/activate`;

                    const membershipSelect = document.getElementById('activation_membership_type');
                    membershipSelect.value = (reviewRecover && @json(old('membership_type'))) || payload.requested_membership_type || payload.membership_type || 'Non-Member';

                    const membershipNotes = document.getElementById('activation_membership_verification_notes');
                    membershipNotes.value = (reviewRecover && @json(old('membership_verification_notes'))) || payload.membership_verification_notes || '';

                    const membershipConfirmed = document.getElementById('activation_membership_verification_confirmed');
                    membershipConfirmed.checked = reviewRecover && Boolean(@json(old('membership_verification_confirmed')));

                    const membershipPending = payload.requested_membership_type === 'Member'
                        && payload.membership_verification_status === 'Pending';
                    const membershipPendingAlert = document.getElementById('activation_membership_pending_alert');
                    const membershipConfirmation = document.getElementById('activation_membership_confirmation');
                    const membershipHelp = document.getElementById('activation_membership_type_help');

                    const syncMembershipVerificationUi = function () {
                        const selectedMembership = membershipSelect.value;
                        const needsConfirmation = selectedMembership === 'Member';

                        membershipPendingAlert.classList.toggle('d-none', ! membershipPending);
                        membershipConfirmation.classList.toggle('d-none', ! needsConfirmation);
                        membershipConfirmed.required = needsConfirmation;
                        membershipConfirmed.setCustomValidity(needsConfirmation && !membershipConfirmed.checked
                            ? "Please confirm that the applicant's association membership has been verified."
                            : '');
                        if (!needsConfirmation || membershipConfirmed.checked) {
                            membershipConfirmed.classList.remove('is-invalid');
                            membershipConfirmed.removeAttribute('aria-invalid');
                        }

                        if (selectedMembership === 'Member') {
                            membershipHelp.textContent = membershipPending
                                ? 'Approving this existing VVTHOA membership verifies it and applies member billing rates.'
                                : 'This account will be recorded as a verified member.';
                        } else {
                            membershipHelp.textContent = membershipPending
                                ? 'Selecting Non-Member records the existing membership claim as not verified and uses the non-member rate.'
                                : 'This account will be recorded as a non-member.';
                        }
                    };

                    membershipSelect.onchange = syncMembershipVerificationUi;
                    membershipConfirmed.onchange = syncMembershipVerificationUi;
                    membershipConfirmed.oninvalid = function () {
                        membershipConfirmed.classList.add('is-invalid');
                        membershipConfirmed.setAttribute('aria-invalid', 'true');
                    };
                    membershipConfirmed.setAttribute('aria-describedby', 'activation_membership_confirmation_error');
                    syncMembershipVerificationUi();

                    document.getElementById('activation_meter_number').value = (reviewRecover && @json(old('meter_number'))) || payload.meter_number || '';
                    document.getElementById('activation_installation_date').value = (reviewRecover && @json(old('installation_date'))) || '';
                    document.getElementById('activation_installation_time').value = (reviewRecover && @json(old('installation_time'))) || '';

                    if (rejectForm) {
                        rejectForm.action = `{{ url('/consumers') }}/${consumerId}/reject`;
                        document.getElementById('rejection_reason').value = (reviewRecover && @json(old('rejection_reason'))) || '';
                        rejectForm.querySelectorAll('input[name="correction_fields[]"]').forEach(function (checkbox) {
                            checkbox.disabled = checkbox.value === 'membership_document_photos' && payload.requested_membership_type !== 'Member';
                            checkbox.closest('.col-md-6').hidden = checkbox.disabled;
                            checkbox.checked = !checkbox.disabled && (payload.required_corrections.includes(checkbox.value)
                                || (reviewRecover && (@json(old('correction_fields', []))).includes(checkbox.value)));
                        });
                        syncCorrections();
                    }

                    const infoContent = document.getElementById('activateInfoContent');

                    if (infoContent) {
                        const boolYesNo = function (value) { return value ? 'Yes' : 'No'; };

                        const fields = [
                            { key: 'full_name', label: 'Full Name' },
                            { key: 'email', label: 'Email' },
                            { key: 'contact_number', label: 'Contact Number' },
                            { key: 'service_address', label: 'Service Address' },
                            { key: 'application_reference', label: 'Application Reference' },
                            { key: 'account_number', label: 'Account Number' },
                            { key: 'registered_at', label: 'Registered On' },
                            { key: 'requested_membership_type', label: 'Declared VVTHOA Membership' },
                            { key: 'membership_type', label: 'Effective Classification' },
                            { key: 'membership_verification_status', label: 'Membership Verification' },
                            { key: 'connection_request_status', label: 'Water Connection Request' },
                            { key: 'property_type', label: 'Property Type' },
                            { key: 'ownership_status', label: 'Ownership Status' },
                            { key: 'valid_id_presented', label: 'Valid ID Presented' },
                            { key: 'rejection_fields', label: 'Requirements to Correct', type: 'list' },
                            { key: 'housing_rights_documented', label: 'Housing Rights Documented', type: 'bool' },
                            { key: 'resident_of_community', label: 'Resident of Community', type: 'bool' },
                            { key: 'association_member', label: 'Association Member', type: 'bool' },
                            { key: 'association_registered', label: 'Association Registered', type: 'bool' },
                            { key: 'housing_loan_contract_submitted', label: 'Housing Loan Contract Submitted', type: 'bool' },
                            { key: 'membership_document_type', label: 'Selected Document Type' },
                            { key: 'other_document_details', label: 'Other Document Details' },
                        ];

                        let html = '';
                        const fieldGroups = [
                            { heading: 'Applicant Information', fields: fields.filter(field => ['full_name','email','contact_number','service_address','application_reference','account_number','registered_at','property_type','ownership_status','valid_id_presented'].includes(field.key)) },
                            { heading: 'Application Verification', fields: fields.filter(field => !['full_name','email','contact_number','service_address','application_reference','account_number','registered_at','property_type','ownership_status','valid_id_presented'].includes(field.key)) },
                        ];

                        fieldGroups.forEach(function (group, groupIndex) {
                            html += '<section class="review-section" data-review-info-step="' + (groupIndex + 1) + '"><h3 class="review-section-heading">' + group.heading + '</h3><div class="row g-3">';
                            for (const field of group.fields) {
                                let value = payload[field.key];

                                if (value === null || value === undefined || value === '') {
                                    value = '<span class="text-secondary">Not provided</span>';
                                } else if (field.type === 'bool') {
                                    value = boolYesNo(value);
                                } else if (field.type === 'list') {
                                    value = (Array.isArray(value) && value.length > 0)
                                        ? escapeHtml(value.join(', '))
                                        : '<span class="text-secondary">None recorded</span>';
                                } else {
                                    value = escapeHtml(value);
                                }

                                html += '<div class="col-md-6"><div class="review-field-label">' + field.label + '</div><div class="fw-medium review-field-value">' + value + '</div></div>';
                            }
                            html += '</div>';
                            if (groupIndex === 0) html += '</section>';
                        });

                        html += '<div class="review-documents"><h6 class="fw-bold mt-4 mb-3">Valid ID Document</h6>';

                        if (payload.valid_id_url) {
                            html += '<div class="mb-3"><a href="' + payload.valid_id_url + '" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-eye me-2"></i>View Valid ID</a><div class="small text-secondary mt-2">Opens the uploaded identification document in a new tab for review.</div></div>';
                        } else {
                            html += '<div class="text-secondary small fst-italic mb-3">No valid ID uploaded</div>';
                        }

                        html += '<h6 class="fw-bold mt-3 mb-2">Proof of Ownership/Residency</h6>';

                        if (payload.proof_of_residency_url) {
                            html += '<div class="mb-3"><a href="' + payload.proof_of_residency_url + '" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-eye me-2"></i>View Proof of Ownership/Residency</a></div>';
                        } else {
                            html += '<div class="text-secondary small fst-italic mb-3">No proof of ownership/residency uploaded</div>';
                        }

                        html += '<h6 class="fw-bold mt-3 mb-2">Membership Document Photos</h6>';
                        html += '<div class="small text-secondary mb-2">Selected document type: <strong>' + escapeHtml(payload.membership_document_type || 'Not provided') + '</strong></div>';

                        const membershipDocumentUrls = Array.isArray(payload.membership_document_urls) ? payload.membership_document_urls : [];

                        if (membershipDocumentUrls.length > 0) {
                            html += '<div class="row g-3">';
                            membershipDocumentUrls.forEach(function (url, index) {
                                const label = escapeHtml((payload.membership_document_type || 'Membership Document') + ' Photo ' + (index + 1));
                                html += '<div class="col-md-6"><a href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt="' + label + '" class="img-fluid border rounded-3" style="max-height:220px;width:100%;object-fit:contain;cursor:pointer;background:#f8f9fa;" onerror="this.style.display=\'none\'"></a></div>';
                            });
                            html += '</div>';
                        } else {
                            html += '<div class="text-secondary small fst-italic mb-3">No membership document photos uploaded</div>';
                        }

                        if (payload.other_document_url) {
                            html += '<h6 class="fw-bold mt-3 mb-2">Other Document</h6>';
                            html += '<a href="' + payload.other_document_url + '" target="_blank" rel="noopener"><img src="' + payload.other_document_url + '" alt="Other Document" class="img-fluid border rounded-3" style="max-height:200px;width:100%;object-fit:contain;cursor:pointer;background:#f8f9fa;" onerror="this.style.display=\'none\'"></a>';
                        }

                        html += '</div></section>';
                        infoContent.innerHTML = html;
                        const documents = infoContent.querySelector('.review-documents');
                        if (documents) infoContent.querySelector('[data-review-info-step="1"]').appendChild(documents.cloneNode(true));
                    }
                    showReviewStep();
                    if (reviewRecover) reviewDirty = true;
                });
            }

            if (editModal && editForm) {
                editModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));

                    const noticeEditUrl = button.dataset.noticeEditUrl;
                    renderConsumerStatusBadge('consumerEditStatusBadge', payload.status_display);

                    editForm.action = noticeEditUrl || `{{ url('/consumers') }}/${payload.id}`;
                    document.getElementById('edit_full_name').value = @json(old('full_name')) || payload.full_name || '';
                    document.getElementById('edit_email').value = @json(old('email')) || payload.email || '';
                    document.getElementById('edit_account_number').value = @json(old('account_number')) || payload.account_number || '';
                    document.getElementById('edit_meter_number').value = @json(old('meter_number')) || payload.meter_number || '';
                    document.getElementById('edit_membership_type').value = @json(old('membership_type')) || payload.membership_type || 'Non-Member';
                    document.getElementById('edit_membership_verification_notes').value = @json(old('membership_verification_notes')) || payload.membership_verification_notes || '';

                    const editMembershipPending = payload.membership_verification_status === 'Pending';
                    const editMembershipPendingPanel = document.getElementById('edit_membership_pending_panel');
                    const editMembershipConfirmed = document.getElementById('edit_membership_verification_confirmed');

                    if (editMembershipPendingPanel) {
                        editMembershipPendingPanel.classList.toggle('d-none', ! editMembershipPending);
                    }

                    if (editMembershipConfirmed) {
                        editMembershipConfirmed.checked = editMembershipPending
                            && Boolean(@json(old('membership_verification_confirmed')));
                        editMembershipConfirmed.required = editMembershipPending;
                    }

                    document.getElementById('edit_property_type').value = @json(old('property_type')) || payload.property_type || '';
                    document.getElementById('edit_ownership_status').value = @json(old('ownership_status')) || payload.ownership_status || '';
                    document.getElementById('edit_service_address').value = @json(old('service_address')) || payload.service_address || '';
                    document.getElementById('edit_address_block').value = @json(old('address_block')) || payload.address_block || '';
                    document.getElementById('edit_address_street').value = @json(old('address_street')) || payload.address_street || '';
                    document.getElementById('edit_contact_number').value = @json(old('contact_number')) || payload.contact_number || '';
                    document.getElementById('edit_valid_id_presented').value = @json(old('valid_id_presented')) || payload.valid_id_presented || '';
                    document.getElementById('edit_reconnection_date').value = @json(old('reconnection_date')) || payload.reconnection_date || '';
                    document.getElementById('edit_installation_date').value = @json(old('installation_date')) || payload.installation_date || '';

                    const editStatus = document.getElementById('edit_status');
                    document.getElementById('edit_status_field').hidden = Boolean(noticeEditUrl);
                    editStatus.disabled = Boolean(noticeEditUrl);
                    document.getElementById('edit_status_hidden')?.remove();
                    if (! noticeEditUrl) {
                        const selectedEditStatus = @json(old('status')) || payload.status || 'Active';
                        const allowedEditStatuses = ['Active', 'Disconnected', 'Rejected'];
                        const lockedStatuses = (editStatus.dataset.statusLockedOn || '').split(',').map((value) => value.trim());
                        const statusLocked = payload.reconnection_pending || lockedStatuses.includes(payload.status) || ! allowedEditStatuses.includes(selectedEditStatus);
                        const statusNote = document.getElementById('edit_status_locked_note');
                        editStatus.value = selectedEditStatus;
                        editStatus.disabled = statusLocked;
                        statusNote.classList.toggle('d-none', ! statusLocked);
                        if (statusLocked) {
                            statusNote.textContent = allowedEditStatuses.includes(payload.status)
                                ? 'Status is locked while the account is awaiting reconnection.'
                                : 'This workflow status is managed by the application workflow.';
                            // A disabled select is not submitted, so mirror the value.
                            editStatus.form.appendChild(Object.assign(document.createElement('input'), {
                                type: 'hidden',
                                name: 'status',
                                id: 'edit_status_hidden',
                                value: lockedStatuses.includes(payload.status) ? payload.status : selectedEditStatus,
                            }));
                        }
                    }
                    document.getElementById('edit_other_document_details').value = @json(old('other_document_details')) || payload.other_document_details || '';
                    document.getElementById('edit_housing_rights_documented').checked = Boolean(@json(old('housing_rights_documented')) || payload.housing_rights_documented);
                    document.getElementById('edit_resident_of_community').checked = Boolean(@json(old('resident_of_community')) || payload.resident_of_community);
                    document.getElementById('edit_association_member').checked = Boolean(@json(old('association_member')) || payload.association_member);
                    document.getElementById('edit_association_registered').checked = Boolean(@json(old('association_registered')) || payload.association_registered);
                    document.getElementById('edit_housing_loan_contract_submitted').checked = Boolean(@json(old('housing_loan_contract_submitted')) || payload.housing_loan_contract_submitted);
                    document.getElementById('edit_valid_id_submitted').checked = Boolean(@json(old('valid_id_submitted')) || payload.valid_id_submitted);
                    document.getElementById('edit_proof_of_residency_submitted').checked = Boolean(@json(old('proof_of_residency_submitted')) || payload.proof_of_residency_submitted);
                });
            }

            const assignmentModal = document.getElementById('adminReviewRescheduleModal');
            if (assignmentModal) {
                assignmentModal.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    if (!trigger) return;
                    const payload = JSON.parse(atob(trigger.getAttribute('data-reschedule') || 'e30='));
                    const form = document.getElementById('assignRescheduleForm');
                    form.reset();
                    form.action = payload.assign_url;
                    document.getElementById('arr_consumer_name').textContent = payload.consumer_name || '';
                    document.getElementById('arr_account').textContent = payload.account_number || '';
                    document.getElementById('arr_type_badge').textContent = payload.is_reconnection ? 'Reconnection' : (payload.is_loan ? 'Loan Installation' : 'Connection Installation');
                    document.getElementById('arr_original_schedule').textContent = payload.original_schedule_formatted || '';
                    document.getElementById('arr_reason').textContent = (payload.reason || '') + (payload.preferred_schedule ? ' Preferred schedule: ' + payload.preferred_schedule : '');
                    document.getElementById('arr_new_date').value = payload.preferred_date || '';
                    document.getElementById('arr_new_time').value = payload.preferred_time || '';
                    const dateInput = document.getElementById('arr_new_date');
                    dateInput.min = payload.is_reconnection ? dateInput.dataset.manilaMin : dateInput.dataset.defaultMin;
                    const scheduleType = payload.is_reconnection ? 'Reconnection' : 'Installation';
                    document.getElementById('arr_schedule_type').textContent = scheduleType.toLowerCase();
                    document.querySelector('label[for="arr_new_date"]').textContent = 'New ' + scheduleType + ' Date';
                    document.querySelector('label[for="arr_new_time"]').textContent = 'New ' + scheduleType + ' Time';
                    document.getElementById('arr_reconnection_timezone').hidden = !payload.is_reconnection;
                    const usage = payload.usage;
                    document.getElementById('arr_usage').textContent = usage.used + ' of ' + usage.limit + ' used; ' + usage.remaining + ' attempts remaining.';
                    document.getElementById('assignRescheduleButton').disabled = !usage.available;
                    const historyList = document.getElementById('arr_history_list');
                    historyList.replaceChildren();
                    const history = payload.history || [];
                    document.getElementById('arr_history_container').classList.toggle('d-none', history.length === 0);
                    history.forEach(function (entry) {
                        const item = document.createElement('p');
                        item.textContent = entry.status + ': ' + entry.original + (entry.new ? ' → ' + entry.new : '') + '. Reason: ' + entry.reason + (entry.admin_notes ? '. Notes: ' + entry.admin_notes : '');
                        historyList.appendChild(item);
                    });
                });
            }
        });
    </script>

    @if ($errors->any() && ! in_array(session('consumer_modal'), ['reconnect', 'none'], true))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalTarget = @json(session('consumer_modal', 'single'));
                const targetElement = document.getElementById(
                    modalTarget === 'bulk'
                        ? 'consumerBulkUploadModal'
                        : (modalTarget === 'activate' ? 'consumerActivateModal' : (modalTarget === 'edit' ? 'consumerEditModal' : 'consumerModal'))
                );

                if (! targetElement) {
                    return;
                }

                let modalTrigger = null;

                const findConsumerTrigger = function (selector, consumerId, noticeEdit = false) {
                    return Array.from(document.querySelectorAll('[data-bs-target="' + selector + '"]')).find(function (button) {
                        if (noticeEdit && ! button.dataset.noticeEditUrl) return false;
                        try {
                            const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));

                            return payload.id === consumerId;
                        } catch (error) {
                            return false;
                        }
                    }) || null;
                };

                if (modalTarget === 'activate') {
                    const activateForm = document.getElementById('consumerActivateForm');
                    const consumerId = @json(session('consumer_activate_id'));

                    if (activateForm && consumerId) {
                        activateForm.action = `{{ url('/consumers') }}/${consumerId}/activate`;
                        modalTrigger = findConsumerTrigger('#consumerActivateModal', consumerId);
                    }
                }

                if (modalTarget === 'edit') {
                    const editForm = document.getElementById('consumerEditForm');
                    const consumerId = @json(session('consumer_edit_id'));
                    const noticeEdit = @json(session('consumer_notice_edit', false));

                    if (editForm && consumerId) {
                        editForm.action = `{{ url('/consumers') }}/${consumerId}`;
                        modalTrigger = findConsumerTrigger('#consumerEditModal', consumerId, noticeEdit);
                        if (noticeEdit && ! modalTrigger) return;
                    }
                }

                const modal = new bootstrap.Modal(targetElement);
                modal.show(modalTrigger || undefined);
            });
        </script>
    @endif
@endpush















