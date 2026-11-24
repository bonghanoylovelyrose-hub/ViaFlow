@extends('layouts.app')

@php
    $activePage = 'consumers';
    $consumerStatusService = app(\App\Services\ConsumerStatusService::class);
@endphp

@section('title', 'Consumers')

@push('styles')
    <style>
        html:has(.consumer-management-page), body:has(.consumer-management-page) { overflow: hidden; }
        .consumer-management-page .main-content { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }
        .consumer-management-page .topbar, .consumer-management-page .page-heading { flex-shrink: 0; }
        .consumer-management-page .content-wrapper { flex: 1; min-height: 0; min-width: 0; display: flex; flex-direction: column; gap: 1rem; padding: 1rem; }
        .consumer-management-page .page-heading { margin-bottom: 0; align-items: center; }
        .consumer-management-page .page-heading h1 { font-size: 1.5rem; margin-bottom: 0; }
        .consumer-accounts-panel { flex: 1; min-height: 0; min-width: 0; display: flex; flex-direction: column; gap: 1rem; padding: 1rem; overflow: auto; }
        .account-list { flex: 1 0 18rem; min-height: 0; min-width: 0; max-width: 100%; display: flex; flex-direction: column; overflow: hidden; }
        .account-list > :not(.account-table-scroll) { flex-shrink: 0; }
        .account-list .account-table-scroll { flex: 1; min-height: 6rem; min-width: 0; width: 100%; overflow: auto; contain: layout paint inline-size; overscroll-behavior: contain; scrollbar-gutter: stable; border: 1px solid var(--wb-border); border-radius: .65rem; }
        .account-table-scroll table { min-width: 1040px; margin-bottom: 0; }
        #consumer-accounts-table { table-layout: fixed; min-width: 980px; }
        #consumer-accounts-table thead th { white-space: normal; line-height: 1.35; }
        #consumer-accounts-table .account-name, #consumer-accounts-table .account-balance, #consumer-accounts-table .account-actions { min-width: 0; }
        .account-table-scroll thead th { position: sticky; top: 0; z-index: 2; background: #f3f7fa; box-shadow: inset 0 -1px var(--wb-border); padding: .8rem .75rem; white-space: nowrap; font-size: .75rem; }
        .account-table-scroll tbody td { padding: .8rem .75rem; vertical-align: middle; }
        .account-table-scroll td.account-identifier { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .account-table-scroll .account-name { min-width: 170px; max-width: 240px; overflow-wrap: anywhere; }
        .account-table-scroll .account-balance { min-width: 155px; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .account-actions { min-width: 250px; max-width: 370px; }
        .account-action-buttons { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: .4rem; }
        .account-action-buttons .btn { margin: 0 !important; white-space: nowrap; min-height: 2rem; font-size: .75rem; gap: .25rem; }
        .account-pagination { font-size: .82rem; }
        .account-pagination .pagination { margin-bottom: 0; }
        .account-details-grid dt { font-size: .8rem; color: var(--wb-muted); font-weight: 500; }
        .account-details-grid dd { overflow-wrap: anywhere; margin-bottom: .85rem; }
        .consumer-management-page #active-disconnection-notices { margin-bottom: 0 !important; }
        .consumer-management-page #active-disconnection-notices .card-header { border: 0; background: transparent !important; padding: 0 0 .75rem; }
        .consumer-management-page #active-disconnection-notices .card-header h2 { font-size: 1rem !important; }
        .consumer-management-page #active-accounts { border-top: 1px solid var(--wb-border); padding-top: 1rem; }
        @media (min-width: 768px) {
            #disconnection-records th:last-child, #disconnection-records td:last-child { position: sticky; right: 0; background: var(--wb-surface); box-shadow: -1px 0 var(--wb-border); z-index: 1; }
            #disconnection-records th:last-child { z-index: 3; }
        }
        @media (max-width: 767.98px) {
            .consumer-management-page .content-wrapper { padding: .75rem; gap: .75rem; }
            .consumer-management-page .page-heading h1 { font-size: 1.2rem; }
            .consumer-accounts-panel { padding: .75rem; }
            .account-list { flex-basis: 42rem; }
            .account-table-scroll table { min-width: 980px; }
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
    </style>
@endpush

@section('body')
<div class="app-shell d-lg-flex consumer-management-page">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Consumer Management</h1>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#consumerModal">
                        <i class="bi bi-person-plus me-2"></i>Add Consumer
                    </button>
                </div>
            </div>

            <div class="panel-card consumer-accounts-panel">
                @if (session('success'))
                    <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-check-circle"></i>
                        <div><span class="fw-bold">Success:</span> {{ session('success') }}</div>
                    </div>
                @endif

                @if (session('email_error'))
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-envelope-exclamation mt-1"></i>
                        <div><span class="fw-bold">Email delivery failed:</span> {{ session('email_error') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                {{-- This section is exclusive to Consumers -> Active Accounts. --}}
                @if ($filters['account_category'] === 'active' && $disconnectionNotices)
                    @php
                        $hasNoticeFilters = $filters['notice_search'] !== '' || $filters['notice_status'] !== '' || $filters['notice_membership'] !== '';
                    @endphp
                    <section class="account-list" id="active-disconnection-notices" aria-labelledby="notice-accounts-title">
                        <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h2 class="fw-bold fs-5 mb-0" id="notice-accounts-title">Disconnection Notice</h2>
                            </div>
                            <span class="badge bg-secondary">
                                {{ number_format($disconnectionNoticeCount) }} account{{ $disconnectionNoticeCount === 1 ? '' : 's' }}
                            </span>
                        </div>
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
                                    <label class="form-label fw-semibold small text-secondary" for="notice_status">Status</label>
                                    <select class="form-select" id="notice_status" name="notice_status">
                                        @foreach ($noticeStatusFilters as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" @selected($filters['notice_status'] === $statusValue)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary" for="notice_membership">Membership</label>
                                    <select class="form-select" id="notice_membership" name="notice_membership">
                                        @foreach ($membershipFilters as $membershipValue => $membershipLabel)
                                            <option value="{{ $membershipValue }}" @selected($filters['notice_membership'] === $membershipValue)>{{ $membershipValue === '' ? 'Disconnection Records' : $membershipLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex gap-2">
                                    <noscript>
                                        <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                                    </noscript>
                                    <a href="{{ route('consumers.index', array_merge(request()->only(['search', 'membership', 'membership_verification', 'page']), ['account_category' => 'active'])) }}" class="btn btn-light border w-100">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Clear
                                    </a>
                                </div>
                            </form>

                                <div class="account-table-scroll" tabindex="0" role="region" aria-label="Disconnection notice accounts">
                                    <table class="table align-middle mb-0" id="disconnection-records">
                                        <thead>
                                            <tr>
                                                <th>Consumer Name</th>
                                                <th>Account Number</th>
                                                <th>Meter Number</th>
                                                <th>Account Status</th>
                                                <th>Connection Status</th>
                                                <th>Outstanding Balance</th>
                                                <th>Payment Status</th>
                                                <th>Disconnection Notice Date</th>
                                                <th>Grace Period / Payment Deadline</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($disconnectionNotices as $notice)
                                                @php
                                                    $noticeConsumer = $notice['consumer'];
                                                    $noticeDisplay = $consumerStatusService->present($noticeConsumer);
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
                                                <tr @class(['table-danger' => $notice['can_confirm_disconnection']]) data-record-status="{{ $notice['record_status'] }}">
                                                    <td class="account-name fw-semibold">{{ $noticeConsumer->full_name }}</td>
                                                    <td class="account-identifier">{{ $noticeConsumer->account_number ?: 'Unassigned' }}</td>
                                                    <td class="account-identifier">{{ $noticeConsumer->meter_number ?: 'Unassigned' }}</td>
                                                    <td><span class="badge {{ $noticeDisplay['badge_class'] }}">{{ $noticeDisplay['label'] }}</span></td>
                                                    <td><span class="consumer-status-sub {{ $noticeDisplay['sub_class'] }}">{{ $noticeDisplay['sub_label'] }}</span></td>
                                                    <td>
                                                        <span class="fw-semibold">P {{ number_format($notice['total_outstanding'], 2) }}</span>
                                                    </td>
                                                    <td>{{ $notice['total_outstanding'] > 0 ? 'Unpaid' : 'Paid' }}</td>
                                                    <td>
                                                        <div>{{ optional($notice['notice_date'])->format('M d, Y') ?: '—' }}</div>
                                                        @if ($notice['notice_billing_period'])
                                                            <div class="small text-secondary">Billing period {{ $notice['notice_billing_period']->format('M d, Y') }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($notice['payment_deadline'])
                                                            <div class="small text-secondary">{{ \App\Services\ConsumerBalanceService::PAYMENT_GRACE_DAYS }} full days from notice</div>
                                                            <div>{{ $notice['payment_deadline']->format('M d, Y g:i A') }}</div>
                                                            @if ($notice['can_confirm_disconnection'])
                                                                <span class="badge bg-danger mt-1" title="Three-day window elapsed">Ready for Disconnection</span>
                                                            @elseif ($notice['has_active_notice'])
                                                                <span class="badge bg-warning text-dark mt-1">Three-day window open</span>
                                                            @endif
                                                        @else
                                                            <span class="text-secondary">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end account-actions">
                                                        <div class="account-action-buttons">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#consumerDetails-{{ $noticeConsumer->id }}" aria-label="View more about {{ $noticeConsumer->full_name }}">View More</button>
                                                        @if ($notice['can_confirm_disconnection'])
                                                            <form
                                                                method="POST"
                                                                id="notice-disconnect-form-{{ $noticeConsumer->id }}"
                                                                action="{{ route('consumers.confirm-disconnection', $noticeConsumer) }}"
                                                                class="d-inline"
                                                                onsubmit="return confirm('Confirm that the physical disconnection has already been carried out for {{ $noticeConsumer->full_name }}? The account will move to Inactive Accounts.');"
                                                            >
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-danger me-2">
                                                                    <i class="bi bi-plug me-1"></i>Confirm Disconnection
                                                                </button>
                                                            </form>
                                                        @elseif ($notice['has_active_notice'])
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-outline-secondary me-2"
                                                                disabled
                                                                title="Available only after the three-day payment window closes."
                                                            >
                                                                <i class="bi bi-plug me-1"></i>Confirm Disconnection
                                                            </button>
                                                        @endif
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#consumerEditModal"
                                                            data-consumer='{{ $noticePayload }}'
                                                            title="Open this consumer's account details"
                                                        >
                                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                                        </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @push('account-details')
                                                    @include('admin.consumers.account-details', ['detailsConsumer' => $noticeConsumer, 'noticeRecord' => $notice])
                                                @endpush
                                            @empty
                                                <tr><td colspan="10" class="text-center text-secondary py-4">{{ $hasNoticeFilters ? 'No disconnection records match the current filters.' : 'No disconnection records found.' }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                            @if ($disconnectionNotices->total() > 0)
                                <div class="account-pagination d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                                    <p class="text-secondary mb-0">
                                        Showing {{ $disconnectionNotices->firstItem() }} to {{ $disconnectionNotices->lastItem() }} of {{ $disconnectionNotices->total() }} account{{ $disconnectionNotices->total() === 1 ? '' : 's' }}
                                    </p>
                                    {{ $disconnectionNotices->links() }}
                                </div>
                            @endif
                    </section>
                @endif

                <section class="account-list" id="{{ $filters['account_category'] === 'active' ? 'active-accounts' : 'consumer-accounts' }}" aria-labelledby="active-accounts-title">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h2 class="fs-6 fw-bold mb-0" id="active-accounts-title">{{ match ($filters['account_category']) {
                            'active' => 'Active Accounts', 'inactive' => 'Inactive Accounts',
                            'pending' => 'Pending Accounts', 'rejected' => 'Rejected Accounts', default => 'All Consumer Accounts',
                        } }}</h2>
                        <span class="badge bg-secondary">{{ number_format($consumers->total()) }} accounts</span>
                    </div>
                @php
                    $clearConsumerFilters = array_filter([
                        'account_category' => $filters['account_category'],
                    ], fn ($value) => $value !== null && $value !== '');
                    if ($filters['account_category'] === 'active') {
                        $clearConsumerFilters = array_merge($clearConsumerFilters, request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']));
                    }
                @endphp

                <form method="GET" id="account-filters" action="{{ route('consumers.index') }}" class="row g-2 mb-3 align-items-end" data-auto-filter data-auto-filter-debounce="450">
                    @if ($filters['account_category'] === 'active')
                        @foreach (request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']) as $noticeFilter => $noticeValue)
                            <input type="hidden" name="{{ $noticeFilter }}" value="{{ $noticeValue }}">
                        @endforeach
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
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary" for="search">Search</label>
                        <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by name, account, or meter number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-secondary" for="membership">Membership</label>
                        <select class="form-select" id="membership" name="membership">
                            @foreach ($membershipFilters as $membershipValue => $membershipLabel)
                                <option value="{{ $membershipValue }}" @selected($filters['membership'] === $membershipValue)>{{ $filters['account_category'] === 'active' && $membershipValue === '' ? 'All Memberships' : $membershipLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <noscript>
                            <button type="submit" class="btn btn-primary w-100">Apply filters</button>
                        </noscript>
                        <a href="{{ route('consumers.index', $clearConsumerFilters) }}" class="btn btn-light border w-100">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Clear
                        </a>
                    </div>
                </form>

                @if ($filters['account_category'] === 'pending')
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                        <span class="small fw-bold text-secondary text-uppercase me-1">Pending Type:</span>
                        <a href="{{ route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'all']))) }}"
                           class="btn btn-sm {{ ($pendingType ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            All Pending ({{ ($pendingApplicationsCount ?? 0) + ($pendingReschedulesCount ?? 0) }})
                        </a>
                        <a href="{{ route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'applications']))) }}"
                           class="btn btn-sm {{ ($pendingType ?? '') === 'applications' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            Connection Applications ({{ $pendingApplicationsCount ?? 0 }})
                        </a>
                        <a href="{{ route('consumers.index', array_filter(array_merge($filters, ['pending_type' => 'reschedules']))) }}"
                           class="btn btn-sm {{ ($pendingType ?? '') === 'reschedules' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            <i class="bi bi-calendar-event me-1"></i>Reschedule Requests ({{ $pendingReschedulesCount ?? 0 }})
                        </a>
                    </div>
                @endif

                @if ($filters['membership_verification'] === 'pending')
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 mb-3" role="status">
                        <span><i class="bi bi-person-check me-2"></i>Showing pending membership verifications.</span>
                        <a
                            href="{{ route('consumers.index', array_merge(request()->only(['notice_search', 'notice_status', 'notice_membership', 'notice_page']), array_filter(['account_category' => $filters['account_category'], 'membership' => 'member'], fn ($value) => $value !== null && $value !== ''))) }}"
                            class="small fw-semibold"
                        >View all members</a>
                    </div>
                @endif

                <div class="account-table-scroll" tabindex="0" role="region" aria-label="Consumer accounts">
                    <table class="table align-middle" id="consumer-accounts-table">
                        <colgroup><col style="width: 17%"><col style="width: 14%"><col style="width: 11%"><col style="width: 10%"><col style="width: 10%"><col style="width: 14%"><col style="width: 24%"></colgroup>
                        <thead><tr><th scope="col">Consumer Name</th><th scope="col">Account Number</th><th scope="col">Meter Number</th><th scope="col">Account Status</th><th scope="col">Connection Status</th><th scope="col">Outstanding Balance</th><th scope="col" class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($consumers as $consumer)
                                @php
                                    $normalizedStatus = str($consumer->status)->trim()->lower()->toString();
                                    $finalStatuses = ['active', 'rejected', 'disconnected'];
                                    $pendingStatuses = ['pending', 'pending verification', 'for review', 'for reconnection'];
                                    $canVerifyConsumer = blank($consumer->user_id)
                                        && blank($consumer->meter_number)
                                        && (
                                            in_array($normalizedStatus, $pendingStatuses, true)
                                            || (blank($consumer->account_number) && ! in_array($normalizedStatus, $finalStatuses, true))
                                        );
                                    $accountDisplay = $consumerStatusService->present($consumer);
                                    $accountBalance = $consumerBalances[$consumer->id];
                                    $consumerPendingReschedules = $pendingReschedulesByConsumer[$consumer->id] ?? collect();
                                    $consumerPayload = base64_encode(json_encode([
                                        'id' => $consumer->id,
                                        'application_reference' => $consumer->application_reference,
                                        'full_name' => $consumer->full_name,
                                        'email' => $consumer->email,
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
                                        'property_type' => $consumer->property_type,
                                        'ownership_status' => $consumer->ownership_status,
                                        'valid_id_presented' => $consumer->valid_id_presented,
                                        'service_address' => $consumer->service_address,
                                        'address_block' => $consumer->address_block,
                                        'address_street' => $consumer->address_street,
                                        'contact_number' => $consumer->contact_number,
                                        'membership_renewal_date' => optional($consumer->membership_renewal_date)->toDateString(),
                                        'reconnection_date' => optional($consumer->reconnection_date)->toDateString(),
                                        'installation_date' => optional($consumer->installation_date)->toDateString(),
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
                                        'valid_id_url' => $consumer->valid_id_path ? route('uploads.serve', ['path' => $consumer->valid_id_path]) : null,
                                        'proof_of_residency_url' => $consumer->proof_of_residency_path ? route('uploads.serve', ['path' => $consumer->proof_of_residency_path]) : null,
                                        'membership_document_urls' => collect($consumer->membership_document_paths ?? [])
                                            ->map(fn ($path) => route('uploads.serve', ['path' => $path]))
                                            ->values()
                                            ->all(),
                                        'other_document_url' => $consumer->other_document_path ? route('uploads.serve', ['path' => $consumer->other_document_path]) : null,
                                    ]));
                                @endphp
                                <tr>
                                    <td class="account-name fw-semibold">{{ $consumer->full_name }}</td>
                                    <td class="account-identifier">{{ $consumer->account_number ?: 'Unassigned' }}</td>
                                    <td class="account-identifier">{{ $consumer->meter_number ?: 'Unassigned' }}</td>
                                    <td><span class="badge {{ $accountDisplay['badge_class'] }}">{{ $accountDisplay['label'] }}</span></td>
                                    <td><span class="consumer-status-sub {{ $accountDisplay['sub_class'] }}">{{ $accountDisplay['sub_label'] }}</span></td>
                                    <td class="account-balance">
                                        <div class="fw-semibold">&#8369;{{ number_format($accountBalance, 2) }}</div>
                                        <span class="small {{ $accountBalance > 0 ? 'text-secondary' : 'text-success' }}">{{ $accountBalance > 0 ? 'Unpaid' : 'Paid' }}</span>
                                        @if ($filters['account_category'] === 'inactive' && $reconnectionEligibility[$consumer->id]['eligible'])
                                            <div class="small text-success">Eligible for reconnection</div>
                                        @endif
                                    </td>
                                    <td class="text-end account-actions">
                                        <div class="account-action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#consumerDetails-{{ $consumer->id }}" aria-label="View more about {{ $consumer->full_name }}">View More</button>
                                        @foreach ($consumerPendingReschedules as $reschedule)
                                            @php
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
                                                    'usage' => app(\App\Services\InstallationRescheduleService::class)->usage($consumer),
                                                    'original_date' => optional($reschedule->original_installation_date)->toDateString(),
                                                    'original_schedule_formatted' => $reschedule->formattedOriginalSchedule(),
                                                    'original_time' => $reschedule->original_installation_time ? substr((string) $reschedule->original_installation_time, 0, 5) : '',
                                                    'reason' => $reschedule->reason,
                                                    'created_at' => $reschedule->created_at->format('M d, Y g:i A'),
                                                    'assign_url' => route('installation-reschedules.assign', $reschedule),
                                                    'history' => ($rescheduleHistoryByConsumer[$consumer->id] ?? collect())->map(fn ($h) => [
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
                                                title="Assign a new installation date and time"
                                                @disabled(! app(\App\Services\InstallationRescheduleService::class)->usage($consumer)['available'])
                                            >
                                                <i class="bi bi-calendar2-check me-1"></i>Assign New Schedule
                                            </button>
                                        @endforeach
                                        @if ($consumer->status === 'Active')
                                            @if ($consumer->user_id && $consumer->email)
                                                <form
                                                    method="POST"
                                                    action="{{ route('consumers.resend-credentials', $consumer) }}"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Re-send login instructions and a secure password setup link to {{ $consumer->email }}?');"
                                                >
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary me-2" title="Re-send login instructions and a secure password setup link">
                                                        <i class="bi bi-envelope me-1"></i>Resend Login Details
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                        @if ($consumer->hasPendingMembershipVerification() && ! $canVerifyConsumer)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerEditModal"
                                                data-consumer='{{ $consumerPayload }}'
                                                title="Review the existing VIOHOA membership claim"
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
                                        @if ($canVerifyConsumer)
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
                                        @if ($consumer->meter_number)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-info me-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#consumerMeterModal"
                                                data-consumer="{{ $consumerPayload }}"
                                                title="Assign a newly issued meter number"
                                            >
                                                <i class="bi bi-speedometer2 me-1"></i>Assign Meter
                                            </button>
                                        @endif
                                        @if ($consumer->status === 'Rejected' && $consumer->email)
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
                                        <form method="POST" action="{{ route('consumers.destroy', $consumer) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this consumer?')">Delete</button>
                                        </form>
                                        </div>
                                    </td>
                                </tr>
                                @push('account-details')
                                    @include('admin.consumers.account-details', ['detailsConsumer' => $consumer, 'noticeRecord' => null])
                                @endpush
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-secondary mb-2">{{ $filters['account_category'] === 'active' ? 'No active accounts match the current filters.' : 'No consumers found.' }}</div>
                                        @if ($filters['account_category'] !== 'active')
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

                <div class="account-pagination d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                    <p class="text-secondary mb-0">
                        @if ($consumers->total() > 0)
                            Showing {{ $consumers->firstItem() }} to {{ $consumers->lastItem() }} of {{ $consumers->total() }} consumers
                        @else
                            No consumer records yet
                        @endif
                    </p>
                    {{ $consumers->links() }}
                </div>
                </section>
            </div>
        </div>
    </main>
</div>

@stack('account-details')


<div class="modal fade" id="consumerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Add Consumer</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('consumers.store') }}" id="consumerCreateForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="{{ old('full_name') }}" placeholder="Enter consumer name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="account_number">Account Number</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" value="{{ old('account_number') }}" placeholder="Leave blank to auto-generate 1, 2, 3...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="meter_number">Meter Number</label>
                            <input type="text" class="form-control" id="meter_number" name="meter_number" value="{{ old('meter_number') }}" placeholder="MT-78455" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="membership_type">Membership Type</label>
                            <select class="form-select" id="membership_type" name="membership_type" required>
                                @foreach ($membershipTypes as $membershipType)
                                    <option value="{{ $membershipType }}" @selected(old('membership_type', 'Non-Member') === $membershipType)>{{ $membershipType }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="service_address">Service Address</label>
                            <input type="text" class="form-control" id="service_address" name="service_address" value="{{ old('service_address') }}" placeholder="Enter service address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="contact_number">Contact Number</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]{10,15}" class="form-control js-digits-only" id="contact_number" name="contact_number" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="address_block">Block</label>
                            <input type="text" class="form-control" id="address_block" name="address_block" value="{{ old('address_block') }}" placeholder="e.g. Block 12">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="address_street">Street</label>
                            <input type="text" class="form-control" id="address_street" name="address_street" value="{{ old('address_street') }}" placeholder="e.g. Maharlika Street">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="housing_rights_documented" name="housing_rights_documented" @checked(old('housing_rights_documented'))>
                                <label class="form-check-label" for="housing_rights_documented">Pag-IBIG / house rights documented</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="association_member" name="association_member" @checked(old('association_member'))>
                                <label class="form-check-label" for="association_member">Local association member</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="association_registered" name="association_registered" @checked(old('association_registered'))>
                                <label class="form-check-label" for="association_registered">Officially registered</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="membership_renewal_date">Membership Date</label>
                            <input type="date" class="form-control" id="membership_renewal_date" name="membership_renewal_date" value="{{ old('membership_renewal_date') }}">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="consumerCreateForm" class="btn btn-primary">Save Consumer</button>
            </div>
        </div>
    </div>
</div>

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
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="edit_full_name">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" value="{{ old('full_name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="edit_email">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" value="{{ old('email') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_account_number">Account Number</label>
                            <input type="text" class="form-control" id="edit_account_number" name="account_number" value="{{ old('account_number') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_meter_number">Meter Number</label>
                            <input type="text" class="form-control" id="edit_meter_number" name="meter_number" value="{{ old('meter_number') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_status">Status</label>
                            <select class="form-select" id="edit_status" name="status" required data-status-locked-on="Disconnected,For Reconnection">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                            <div class="form-text d-none" id="edit_status_locked_note">
                                Status is locked while the account is awaiting reconnection.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_membership_type">Membership Type</label>
                            <select class="form-select" id="edit_membership_type" name="membership_type" required>
                                @foreach ($membershipTypes as $membershipType)
                                    <option value="{{ $membershipType }}">{{ $membershipType }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_property_type">Property Type</label>
                            <select class="form-select" id="edit_property_type" name="property_type">
                                <option value="">Select property type</option>
                                <option value="Residential">Residential</option>
                                <option value="Commercial">Commercial</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_ownership_status">Ownership Status</label>
                            <select class="form-select" id="edit_ownership_status" name="ownership_status">
                                <option value="">Select ownership status</option>
                                <option value="Owner">Owner</option>
                                <option value="Tenant">Tenant</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="edit_membership_verification_notes">Membership Verification Notes</label>
                            <textarea class="form-control" id="edit_membership_verification_notes" name="membership_verification_notes" rows="2" maxlength="1000" placeholder="Optional: record what was checked or why the membership claim was not verified.">{{ old('membership_verification_notes') }}</textarea>
                            <div class="form-text">Changing the membership type records a new verification decision with your account and the current time.</div>
                        </div>
                        <div class="col-12 d-none" id="edit_membership_pending_panel">
                            <div class="alert alert-warning mb-0">
                                <div class="fw-semibold"><i class="bi bi-person-check me-1"></i>VIOHOA membership verification pending</div>
                                <div class="small mt-1">
                                    Choosing <strong>Member</strong> verifies the existing membership and unlocks member benefits.
                                    Choosing <strong>Non-Member</strong> records the claim as not verified.
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="edit_membership_verification_confirmed" name="membership_verification_confirmed" @checked(old('membership_verification_confirmed'))>
                                    <label class="form-check-label" for="edit_membership_verification_confirmed">
                                        I reviewed the membership evidence and confirm this decision.
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_address_block">Block</label>
                            <input type="text" class="form-control" id="edit_address_block" name="address_block" value="{{ old('address_block') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_address_street">Street</label>
                            <input type="text" class="form-control" id="edit_address_street" name="address_street" value="{{ old('address_street') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="edit_service_address">Service Address</label>
                            <input type="text" class="form-control" id="edit_service_address" name="service_address" value="{{ old('service_address') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="edit_contact_number">Contact Number</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]{10,15}" class="form-control js-digits-only" id="edit_contact_number" name="contact_number" value="{{ old('contact_number') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="edit_valid_id_presented">Valid ID Presented</label>
                            <input type="text" class="form-control" id="edit_valid_id_presented" name="valid_id_presented" value="{{ old('valid_id_presented') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="edit_reconnection_date">Reconnection Date</label>
                            <input type="date" class="form-control" id="edit_reconnection_date" name="reconnection_date" value="{{ old('reconnection_date') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="edit_installation_date">Installation Date</label>
                            <input type="date" class="form-control" id="edit_installation_date" name="installation_date" value="{{ old('installation_date') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="edit_other_document_details">Other Document Details</label>
                            <input type="text" class="form-control" id="edit_other_document_details" name="other_document_details" value="{{ old('other_document_details') }}">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_housing_rights_documented" name="housing_rights_documented" @checked(old('housing_rights_documented'))>
                                <label class="form-check-label" for="edit_housing_rights_documented">Housing rights documented</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_resident_of_community" name="resident_of_community" @checked(old('resident_of_community'))>
                                <label class="form-check-label" for="edit_resident_of_community">Resident of community</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_association_member" name="association_member" @checked(old('association_member'))>
                                <label class="form-check-label" for="edit_association_member">Association member</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_association_registered" name="association_registered" @checked(old('association_registered'))>
                                <label class="form-check-label" for="edit_association_registered">Association registered</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_housing_loan_contract_submitted" name="housing_loan_contract_submitted" @checked(old('housing_loan_contract_submitted'))>
                                <label class="form-check-label" for="edit_housing_loan_contract_submitted">Housing loan contract submitted</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_valid_id_submitted" name="valid_id_submitted" @checked(old('valid_id_submitted'))>
                                <label class="form-check-label" for="edit_valid_id_submitted">Valid ID submitted</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_proof_of_residency_submitted" name="proof_of_residency_submitted" @checked(old('proof_of_residency_submitted'))>
                                <label class="form-check-label" for="edit_proof_of_residency_submitted">Proof of residency submitted</label>
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

<div class="modal fade" id="consumerActivateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5">Review Application &amp; Verify Account</h2>
                    <div class="mt-1" id="consumerActivateStatusBadge"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="activateInfoContent" class="mb-4">
                    <div class="text-center text-secondary py-3">Loading applicant information...</div>
                </div>

                <hr>

                <form method="POST" action="#" id="consumerActivateForm">
                    @csrf
                    @method('PATCH')
                    <h6 class="fw-bold mb-3">Activation &amp; Membership Decision</h6>
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
                        <div class="fw-semibold"><i class="bi bi-person-check me-1"></i>This applicant declared existing VIOHOA membership.</div>
                        <div class="small mt-1">Activating as <strong>Member</strong> verifies the existing membership and applies member billing rates. Activating as <strong>Non-Member</strong> records the claim as not verified. The water connection request is marked approved either way.</div>
                    </div>
                    <div class="border rounded-3 p-3 mb-3 d-none" id="activation_membership_confirmation">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="activation_membership_verification_confirmed" name="membership_verification_confirmed" @checked(old('membership_verification_confirmed'))>
                            <label class="form-check-label fw-semibold" for="activation_membership_verification_confirmed">
                                I reviewed the membership claim and supporting documents and confirm this applicant is an officially registered association member.
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_membership_verification_notes">Membership Verification Notes</label>
                        <textarea class="form-control" id="activation_membership_verification_notes" name="membership_verification_notes" rows="2" maxlength="1000" placeholder="Optional: record what was checked or why a claim was not verified.">{{ old('membership_verification_notes') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="activation_meter_number">Meter Number</label>
                        <input type="text" class="form-control" id="activation_meter_number" name="meter_number" value="{{ old('meter_number') }}" placeholder="Assign meter number" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold" for="activation_installation_date">Installation Date</label>
                        <input type="date" class="form-control" id="activation_installation_date" name="installation_date" value="{{ old('installation_date') }}" required>
                    </div>
                </form>

                <hr>

                <div id="activateRejectSection">
                    <h6 class="fw-bold mb-3 text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Reject Application
                    </h6>
                    <p class="small text-secondary">
                        Rejecting the application marks the water connection request as rejected. A still-pending membership claim is also recorded as not verified; an already-verified membership is retained.
                    </p>
                    <form method="POST" action="#" id="consumerRejectForm">
                        @csrf
                        @method('PATCH')
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
                                Requirements named in the reason below are added automatically.
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="rejection_reason">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="Explain why this connection request is being rejected..." required>{{ old('rejection_reason') }}</textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="submit" form="consumerRejectForm" class="btn btn-outline-danger">Reject Application</button>
                <div>
                    <button type="button" class="btn btn-light border me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="consumerActivateForm" class="btn btn-success">Verify and Activate</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="consumerMeterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form method="POST" id="consumerMeterForm">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Assign New Meter Number</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            <div class="small">{{ $errors->first() }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Consumer</label>
                        <div class="fw-semibold" id="meter_consumer_name">&mdash;</div>
                        <div class="small text-secondary" id="meter_consumer_account"></div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle mt-1"></i>
                        <div class="small">
                            Assigning a new meter immediately replaces the current meter number on this account, so the
                            Meter Reader module will use the new number for every future reading.
                            The previous meter number and its readings are kept on record for auditing.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="assign_current_meter">Current Meter Number</label>
                        <input type="text" class="form-control" id="assign_current_meter" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="assign_meter_number">
                            New Meter Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control @error('meter_number') is-invalid @enderror" id="assign_meter_number" name="meter_number" value="{{ old('meter_number') }}" placeholder="MT-90210" required autocomplete="off">
                        <div class="form-text">Must not already belong to another active consumer account.</div>
                        @error('meter_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="assign_meter_reason">Reason (optional)</label>
                        <input type="text" class="form-control" id="assign_meter_reason" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="e.g. Defective meter replaced">
                    </div>

                    <div id="assign_meter_history"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Assign Meter</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.consumers.assign-reschedule-modal')
@if ($filters['account_category'] === 'inactive')
    @include('admin.consumers.reconnect-modal')
@endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const activateModal = document.getElementById('consumerActivateModal');
            const activateForm = document.getElementById('consumerActivateForm');
            const editModal = document.getElementById('consumerEditModal');
            const editForm = document.getElementById('consumerEditForm');
            const rejectForm = document.getElementById('consumerRejectForm');
            const meterModal = document.getElementById('consumerMeterModal');
            const meterForm = document.getElementById('consumerMeterForm');

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

            if (meterModal && meterForm) {
                meterModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));

                    meterForm.action = `{{ url('/consumers') }}/${payload.id}/assign-meter`;
                    document.getElementById('meter_consumer_name').textContent = payload.full_name || '';
                    document.getElementById('meter_consumer_account').textContent = payload.account_number || 'No account number';
                    document.getElementById('assign_current_meter').value = payload.meter_number || '';
                    document.getElementById('assign_meter_number').value = @json(old('meter_number')) || '';
                    document.getElementById('assign_meter_reason').value = @json(old('reason')) || '';

                    const history = Array.isArray(payload.meter_history) ? payload.meter_history : [];
                    const historyEl = document.getElementById('assign_meter_history');

                    if (history.length > 1) {
                        let html = '<h6 class="fw-bold mt-3 mb-2">Meter History</h6><ul class="list-group list-group-flush small">';

                        history.forEach(function (entry) {
                            html += '<li class="list-group-item px-0 d-flex justify-content-between"><span>MT ' + entry.meter_number + '</span><span class="text-secondary">' + (entry.retired_at ? 'Retired ' + entry.retired_at : 'Active') + '</span></li>';
                        });

                        html += '</ul>';
                        historyEl.innerHTML = html;
                    } else {
                        historyEl.innerHTML = '';
                    }
                });
            }

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
                activateModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));
                    const consumerId = payload.id;

                    renderConsumerStatusBadge('consumerActivateStatusBadge', payload.status_display);

                    activateForm.action = `{{ url('/consumers') }}/${consumerId}/activate`;

                    const membershipSelect = document.getElementById('activation_membership_type');
                    membershipSelect.value = @json(old('membership_type')) || payload.requested_membership_type || payload.membership_type || 'Non-Member';

                    const membershipNotes = document.getElementById('activation_membership_verification_notes');
                    membershipNotes.value = @json(old('membership_verification_notes')) || payload.membership_verification_notes || '';

                    const membershipConfirmed = document.getElementById('activation_membership_verification_confirmed');
                    membershipConfirmed.checked = Boolean(@json(old('membership_verification_confirmed')));

                    const membershipPending = payload.requested_membership_type === 'Member'
                        && payload.membership_verification_status === 'Pending';
                    const membershipPendingAlert = document.getElementById('activation_membership_pending_alert');
                    const membershipConfirmation = document.getElementById('activation_membership_confirmation');
                    const membershipHelp = document.getElementById('activation_membership_type_help');

                    const syncMembershipVerificationUi = function () {
                        const selectedMembership = membershipSelect.value;
                        const needsConfirmation = membershipPending && selectedMembership === 'Member';

                        membershipPendingAlert.classList.toggle('d-none', ! membershipPending);
                        membershipConfirmation.classList.toggle('d-none', ! needsConfirmation);
                        membershipConfirmed.required = needsConfirmation;

                        if (selectedMembership === 'Member') {
                            membershipHelp.textContent = membershipPending
                                ? 'Approving this existing VIOHOA membership verifies it and applies member billing rates.'
                                : 'This account will be recorded as a verified member.';
                        } else {
                            membershipHelp.textContent = membershipPending
                                ? 'Selecting Non-Member records the existing membership claim as not verified and uses the non-member rate.'
                                : 'This account will be recorded as a non-member.';
                        }
                    };

                    membershipSelect.onchange = syncMembershipVerificationUi;
                    syncMembershipVerificationUi();

                    document.getElementById('activation_meter_number').value = @json(old('meter_number')) || payload.meter_number || '';
                    document.getElementById('activation_installation_date').value = @json(old('installation_date')) || '';

                    if (rejectForm) {
                        rejectForm.action = `{{ url('/consumers') }}/${consumerId}/reject`;
                        document.getElementById('rejection_reason').value = '';
                        rejectForm.querySelectorAll('input[name="correction_fields[]"]').forEach(function (checkbox) {
                            checkbox.checked = false;
                        });
                    }

                    const infoContent = document.getElementById('activateInfoContent');

                    if (infoContent) {
                        const boolYesNo = function (value) { return value ? 'Yes' : 'No'; };

                        const fields = [
                            { key: 'full_name', label: 'Full Name' },
                            { key: 'email', label: 'Email' },
                            { key: 'contact_number', label: 'Contact Number' },
                            { key: 'service_address', label: 'Service Address' },
                            { key: 'requested_membership_type', label: 'Declared VIOHOA Membership' },
                            { key: 'membership_type', label: 'Effective Classification' },
                            { key: 'membership_verification_status', label: 'Membership Verification' },
                            { key: 'membership_verification_notes', label: 'Membership Verification Notes' },
                            { key: 'connection_request_status', label: 'Water Connection Request' },
                            { key: 'connection_request_admin_notes', label: 'Connection Request Notes' },
                            { key: 'property_type', label: 'Property Type' },
                            { key: 'ownership_status', label: 'Ownership Status' },
                            { key: 'valid_id_presented', label: 'Valid ID Presented' },
                            { key: 'rejection_reason', label: 'Previous Rejection Reason' },
                            { key: 'rejection_fields', label: 'Requirements to Correct', type: 'list' },
                            { key: 'resubmitted_at', label: 'Resubmitted On' },
                            { key: 'housing_rights_documented', label: 'Housing Rights Documented', type: 'bool' },
                            { key: 'resident_of_community', label: 'Resident of Community', type: 'bool' },
                            { key: 'association_member', label: 'Association Member', type: 'bool' },
                            { key: 'association_registered', label: 'Association Registered', type: 'bool' },
                            { key: 'housing_loan_contract_submitted', label: 'Housing Loan Contract Submitted', type: 'bool' },
                            { key: 'membership_document_type', label: 'Selected Document Type' },
                            { key: 'other_document_details', label: 'Other Document Details' },
                        ];

                        let html = '<h6 class="fw-bold mb-3">Applicant Information</h6><div class="row g-3">';

                        for (const field of fields) {
                            let value = payload[field.key];

                            if (value === null || value === undefined || value === '') {
                                value = '<span class="text-secondary">Not provided</span>';
                            } else if (field.type === 'bool') {
                                value = boolYesNo(value);
                            } else if (field.type === 'list') {
                                value = (Array.isArray(value) && value.length > 0)
                                    ? value.join(', ')
                                    : '<span class="text-secondary">None recorded</span>';
                            }

                            html += '<div class="col-md-6"><div class="mb-1"><div class="small text-secondary fw-semibold text-uppercase" style="font-size:0.7rem;">' + field.label + '</div><div class="fw-medium">' + value + '</div></div></div>';
                        }

                        html += '</div>';

                        html += '<h6 class="fw-bold mt-4 mb-3">Valid ID Document</h6>';

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
                        html += '<div class="small text-secondary mb-2">Selected document type: <strong>' + (payload.membership_document_type || 'Not provided') + '</strong></div>';

                        const membershipDocumentUrls = Array.isArray(payload.membership_document_urls) ? payload.membership_document_urls : [];

                        if (membershipDocumentUrls.length > 0) {
                            html += '<div class="row g-3">';
                            membershipDocumentUrls.forEach(function (url, index) {
                                const label = (payload.membership_document_type || 'Membership Document') + ' Photo ' + (index + 1);
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

                        infoContent.innerHTML = html;
                    }
                });
            }

            if (editModal && editForm) {
                editModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;

                    if (! button) {
                        return;
                    }

                    const payload = JSON.parse(atob(button.getAttribute('data-consumer') || 'e30='));

                    renderConsumerStatusBadge('consumerEditStatusBadge', payload.status_display);

                    editForm.action = `{{ url('/consumers') }}/${payload.id}`;
                    document.getElementById('edit_full_name').value = @json(old('full_name')) || payload.full_name || '';
                    document.getElementById('edit_email').value = @json(old('email')) || payload.email || '';
                    document.getElementById('edit_account_number').value = @json(old('account_number')) || payload.account_number || '';
                    document.getElementById('edit_meter_number').value = @json(old('meter_number')) || payload.meter_number || '';
                    document.getElementById('edit_status').value = @json(old('status')) || payload.status || 'Active';
                    document.getElementById('edit_status').dataset.previousValue = payload.status || 'Active';
                    document.getElementById('edit_status').disabled = false;
                    document.getElementById('edit_status_locked_note').classList.add('d-none');
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

                    // Accounts awaiting reconnection keep their status locked.
                    const lockedStatuses = (document.getElementById('edit_status').dataset.statusLockedOn || '').split(',').map((value) => value.trim());
                    const statusHidden = document.getElementById('edit_status_hidden');
                    if (statusHidden) {
                        statusHidden.remove();
                    }
                    if (lockedStatuses.includes(payload.status)) {
                        document.getElementById('edit_status').disabled = true;
                        document.getElementById('edit_status_locked_note').classList.remove('d-none');
                        // A disabled select is not submitted, so mirror the value.
                        document.getElementById('edit_status').form.appendChild(Object.assign(document.createElement('input'), {
                            type: 'hidden',
                            name: 'status',
                            id: 'edit_status_hidden',
                            value: payload.status,
                        }));
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

    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalTarget = @json(session('consumer_modal', 'single'));
                const targetElement = document.getElementById(
                    modalTarget === 'bulk'
                        ? 'consumerBulkUploadModal'
                        : (modalTarget === 'activate' ? 'consumerActivateModal' : (modalTarget === 'edit' ? 'consumerEditModal' : (modalTarget === 'meter' ? 'consumerMeterModal' : 'consumerModal')))
                );

                if (! targetElement) {
                    return;
                }

                let modalTrigger = null;

                const findConsumerTrigger = function (selector, consumerId) {
                    return Array.from(document.querySelectorAll('[data-bs-target="' + selector + '"]')).find(function (button) {
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

                    if (editForm && consumerId) {
                        editForm.action = `{{ url('/consumers') }}/${consumerId}`;
                        modalTrigger = findConsumerTrigger('#consumerEditModal', consumerId);
                    }
                }

                if (modalTarget === 'meter') {
                    const meterForm = document.getElementById('consumerMeterForm');
                    const consumerId = @json(session('consumer_meter_id'));

                    if (meterForm && consumerId) {
                        meterForm.action = `{{ url('/consumers') }}/${consumerId}/assign-meter`;
                        modalTrigger = findConsumerTrigger('#consumerMeterModal', consumerId);
                    }
                }

                const modal = new bootstrap.Modal(targetElement);
                modal.show(modalTrigger || undefined);
            });
        </script>
    @endif
@endpush
