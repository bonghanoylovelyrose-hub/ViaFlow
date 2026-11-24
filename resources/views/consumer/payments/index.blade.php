@extends('layouts.app')

@php
    $activePage = 'payments';
    $billingScenario = $billingScenario ?? null;
    $historyLedger = ($hasBillingScenario ?? false) ? ['ledger' => $billingScenario ? 'demo' : 'recorded'] : [];
    $isLoanPayment = $recordType === 'Loan';
    $waterBills = $paymentFormBills->where('bill_type', 'Water');
    $selectedBill = $waterBills->firstWhere('id', (int) old('bill_id')) ?? $waterBills->first();

    $user = auth()->user();
    $consumer = $user->consumer;
    $accountNumber = $consumer->account_number ?? 'N/A';
    
    $loanAccounts = $recordType === 'Loan' ? $availableBills->pluck('loanInstallment.loan')->filter()->concat($paymentFormLoans)->unique('id') : collect();
    $payableLoans = $paymentFormLoans->where('payment_selectable', true);
    $selectedLoan = $payableLoans->firstWhere('id', (int) old('loan_id')) ?? $payableLoans->first();
    $totalBalanceDue = $recordType === 'Loan'
        ? $loanAccounts->sum(fn ($loan) => app(\App\Services\LoanRepaymentAllocationService::class)->remainingBalance($loan))
        : ($billingScenario['outstanding_balance'] ?? $availableBills->sum('balance_due'));
    $paymentSummary = $consumer
        ? ($disconnectionPaymentSummaries[$consumer->id] ?? [
            'active' => false,
            'notice' => 'None',
            'total_outstanding' => $totalBalanceDue,
            'required_payment_amount' => null,
            'message' => 'Partial payment is allowed.',
        ])
        : [
            'active' => false,
            'notice' => 'None',
            'total_outstanding' => $totalBalanceDue,
            'required_payment_amount' => null,
            'message' => 'Partial payment is allowed.',
        ];
    $hasDisconnectionNotice = $recordType === 'Water' && (bool) ($paymentSummary['active'] ?? false);
    $requiredPaymentAmount = $hasDisconnectionNotice
        ? (float) ($paymentSummary['required_payment_amount'] ?? $paymentSummary['total_outstanding'] ?? $totalBalanceDue)
        : null;
    $paymentRuleMessage = $paymentSummary['message'] ?? ($hasDisconnectionNotice
        ? 'Full payment is required. Partial payment is not allowed.'
        : 'Partial payment is allowed.');
    $formPaymentSummary = $consumer
        ? ($paymentFormDisconnectionSummaries[$consumer->id] ?? $paymentSummary)
        : $paymentSummary;
    $formHasDisconnectionNotice = (bool) ($formPaymentSummary['active'] ?? false);
    $formRequiredPaymentAmount = $formHasDisconnectionNotice
        ? (float) ($formPaymentSummary['required_payment_amount'] ?? $formPaymentSummary['total_outstanding'] ?? 0)
        : null;
    $verifiedPaidAmount = $recordType === 'Loan'
        ? (float) $loanAccounts->sum(fn ($loan) => $loan->total_repaid)
        : (float) $availableBills->sum(fn ($bill) => $bill->verified_paid_amount);
    $accountPaymentStatus = match (true) {
        $hasDisconnectionNotice => 'Disconnection Notice',
        round((float) $totalBalanceDue, 2) <= 0.0 => 'Paid',
        $verifiedPaidAmount > 0 => 'Paid with Balance',
        default => 'Unpaid',
    };
    $mostRecentBill = $billingScenario ? $billingScenario['current_bill'] : $availableBills->first();
    $statementPeriod = $mostRecentBill ? $mostRecentBill->billing_period->format('M Y') : now()->format('M Y');
    $asOfDate = now()->format('M d, Y');

    $accountPayments = $consumer?->payments()->when($billingScenario, fn ($query) => $query->onlyBillingFixtures())
        ->where('payment_type', $recordType);
    $mostRecentPayment = $accountPayments ? (clone $accountPayments)->orderByDesc('payment_date')->orderByDesc('id')->first() : null;

    $paymentsThisMonth = $accountPayments ? (clone $accountPayments)
        ->whereMonth('payment_date', now()->month)
        ->whereYear('payment_date', now()->year)
        ->get() : collect();
    
    $submittedThisMonthCount = $paymentsThisMonth->count();
    $pendingThisMonthCount = $paymentsThisMonth->where('status', 'Pending')->count();
@endphp

@section('title', 'Payments')

@push('styles')
<style>
    :root {
        --pp-bg: color-mix(in srgb, var(--vf-royal) 5%, #ffffff);
        --pp-card: #ffffff;
        --pp-border: rgba(7, 84, 200, 0.16);
        --pp-text: var(--vf-navy);
        --pp-muted: var(--vf-navy);
        --pp-accent: var(--vf-green);
        --pp-pending: #fbbf24;
        --pp-rejected: #f87171;
    }

    .payment-portal-wrapper {
        background: var(--pp-bg);
        color: var(--pp-text);
        font-family: "Plus Jakarta Sans", sans-serif;
        min-height: calc(100vh - 72px);
        margin: -1.5rem;
        padding: 2rem;
    }

    .pp-card {
        background: var(--pp-card) !important;
        border: 1px solid var(--pp-border) !important;
        border-radius: 1.25rem !important;
        color: var(--pp-text) !important;
        box-shadow: none !important;
    }

    .pp-card .text-muted {
        color: var(--pp-muted) !important;
    }

    .payment-portal-wrapper .text-white {
        color: var(--pp-text) !important;
    }

    .pp-badge-account {
        background: #ffffff;
        color: var(--pp-text);
        border: 1px solid var(--pp-border);
        font-weight: 500;
        font-size: 0.85rem;
    }

    .payment-balance-card .payment-balance-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        align-items: start;
        gap: 1rem;
        margin: 0;
    }
    .payment-balance-card .payment-balance-layout > * {
        width: auto;
        min-width: 0;
        max-width: 100%;
        padding: 0;
        margin-top: 0 !important;
    }
    .payment-balance-card .payment-balance-action { min-width: 0; }
    .payment-balance-card .payment-balance-notice {
        padding: 0.65rem 0.75rem;
        border: 1px solid rgba(22, 143, 229, 0.22);
        border-radius: 0.65rem;
        background: #edf7ff;
        color: #0755b8 !important;
        text-align: left !important;
        overflow-wrap: anywhere;
    }
    @media (min-width: 768px) {
        .payment-balance-card .payment-balance-layout {
            grid-template-columns: minmax(0, 1fr) minmax(10.5rem, 13.5rem);
            gap: 1.5rem;
        }
    }

    .pp-balance-card {
        border-left: 4px solid var(--pp-accent) !important;
    }

    .pp-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    .pp-dot-verified { background: var(--pp-accent); }
    .pp-dot-pending { background: var(--pp-pending); }
    .pp-dot-rejected { background: var(--pp-rejected); }

    .pp-btn-outline {
        border-color: var(--pp-border) !important;
        color: var(--pp-text) !important;
        background: transparent !important;
        transition: all 0.2s;
    }

    .pp-btn-outline:hover {
        background: rgba(7, 84, 200, 0.05) !important;
        border-color: rgba(7, 84, 200, 0.32) !important;
    }

    .pp-btn-pill {
        border-radius: 999px !important;
        padding-inline: 1.25rem;
    }

    .pp-filter-btn {
        background: color-mix(in srgb, var(--filter-color, var(--pp-text)) 9%, #ffffff) !important;
        border: 1px solid color-mix(in srgb, var(--filter-color, var(--pp-text)) 32%, #ffffff) !important;
        color: var(--filter-color, var(--pp-text)) !important;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .pp-filter-btn:hover {
        background: color-mix(in srgb, var(--filter-color, var(--pp-text)) 15%, #ffffff) !important;
        color: var(--filter-color, var(--pp-text)) !important;
    }

    .pp-filter-btn.active {
        background: var(--filter-color, var(--pp-text)) !important;
        color: #ffffff !important;
        border-color: var(--filter-color, var(--pp-text)) !important;
    }

    .pp-filter-all { --filter-color: var(--vf-royal); }
    .pp-filter-pending { --filter-color: #b45309; }
    .pp-filter-verified { --filter-color: var(--vf-green); }
    .pp-filter-rejected { --filter-color: var(--pp-rejected); }

    .pp-history-filter .form-control {
        min-width: 230px;
        background: #ffffff !important;
        border-color: var(--pp-border) !important;
        color: var(--pp-text) !important;
    }

    @media (max-width: 575.98px) {
        .pp-history-filter,
        .pp-history-filter .form-control {
            width: 100%;
        }
    }

    .pp-receipt-btn {
        background: #ffffff !important;
        border: 1px solid rgba(50, 165, 31, 0.32) !important;
        color: var(--vf-green) !important;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.2;
        border-radius: 999px !important;
        padding: 0.3rem 0.8rem;
        white-space: nowrap;
    }

    .pp-receipt-btn:hover {
        background: rgba(50, 165, 31, 0.08) !important;
        border-color: rgba(50, 165, 31, 0.5) !important;
        color: var(--vf-green) !important;
    }

    .pp-payment-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(7, 84, 200, 0.06);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pp-modal-input {
        background: #ffffff !important;
        border: 1px solid var(--pp-border) !important;
        color: var(--pp-text) !important;
        border-radius: 0.6rem !important;
        padding: 0.45rem 0.75rem !important;
        height: auto !important;
        min-height: 2.5rem !important;
        font-size: 0.95rem !important;
        transition: all 0.2s ease;
    }
    select.pp-modal-input { padding-inline-end: 2.25rem !important; }
    .pp-modal-input:focus {
        background: #ffffff !important;
        border-color: var(--vf-royal) !important;
        box-shadow: 0 0 0 0.2rem rgba(7, 84, 200, 0.12) !important;
        color: var(--pp-text) !important;
        outline: none !important;
    }
    .pp-modal-input::placeholder {
        color: var(--vf-royal) !important;
    }

    .pp-modal-input::-webkit-calendar-picker-indicator {
        filter: none;
    }

    #paymentModal {
        z-index: 1055;
    }
    #paymentModal .modal-content {
        background: #fff;
        color: var(--vf-navy);
        border-radius: 1rem;
    }
    #paymentModal .modal-header,
    #paymentModal .modal-body {
        padding: clamp(0.75rem, 2vw, 1.25rem);
    }
    #paymentModal .modal-body { padding-top: 0; }

    .pp-payment-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
        gap: 1rem;
    }

    .pp-payment-preview-column {
        display: grid;
        min-width: 0;
        gap: 0.85rem;
    }

    .pp-payment-panel {
        min-width: 0;
        padding: clamp(0.75rem, 2vw, 1rem);
        border: 1px solid var(--pp-border);
        border-radius: 0.85rem;
        background: #fff;
    }

    .pp-payment-section-title {
        margin-bottom: 0.65rem;
        color: var(--vf-navy);
        font-size: 1rem;
        font-weight: 750;
    }

    .pp-payment-fields .form-label { margin-bottom: 0.3rem !important; }
    .pp-payment-fields > .mb-3 { margin-bottom: 0.65rem !important; }
    .pp-payment-fields > .alert {
        padding: 0.55rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.4;
    }
    .pp-payment-fields #receipt_parse_status:empty,
    .pp-receipt-action-status:empty { display: none; }

    .pp-receipt-upload {
        display: grid;
        justify-items: center;
        gap: 0.15rem;
        padding: 0.65rem;
        border: 2px dashed rgba(7, 84, 200, 0.24);
        border-radius: 0.7rem;
        background: var(--vf-background);
        text-align: center;
        cursor: pointer;
    }
    .pp-receipt-upload .text-secondary { font-size: 0.8rem; line-height: 1.35; }

    .pp-receipt-preview {
        display: grid;
        place-items: center;
        min-height: 7rem;
        overflow: hidden;
        border: 1px solid var(--pp-border);
        border-radius: 0.7rem;
        background: #f5f9ff;
    }

    .pp-receipt-preview.has-receipt { height: clamp(13rem, 36vh, 22rem); }
    .pp-receipt-preview-media { width: 100%; height: 100%; object-fit: contain; border: 0; }
    .pp-receipt-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.65rem; }
    .pp-receipt-action-status { margin: 0.5rem 0 0; }
    #proof_file_name { overflow-wrap: anywhere; }

    .pp-organization-qr {
        width: min(100%, 320px);
        margin: 0.6rem auto 0;
        padding: 0.5rem;
        border: 1px solid var(--pp-border);
        border-radius: 0.7rem;
        background: #fff;
    }

    .pp-qr-image-window {
        position: relative;
        display: block;
        overflow: hidden;
        aspect-ratio: 1;
    }

    .pp-qr-image-window img {
        position: absolute;
        /* Frame the original QR and its white border from the 405 × 300 supplied image. */
        inset: -8.7% auto auto -26.81%;
        width: 146.74%;
        max-width: none;
        height: auto;
    }

    @media (max-width: 991.98px) {
        .pp-payment-grid { grid-template-columns: minmax(0, 1fr); gap: 0.75rem; }
    }
    #paymentSubmitConfirmModal {
        z-index: 1070;
    }
    .pp-loan-summary, .pp-loan-preview {
        background: #f3f8ff;
        border: 1px solid #d6e6fb;
        border-radius: .75rem;
        color: var(--vf-navy);
        padding: .8rem 1rem;
    }
    .pp-loan-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem 1rem; }
    .pp-loan-summary-grid span { display: block; color: #526782; font-size: .74rem; }
    .pp-loan-summary-grid strong { display: block; font-size: .95rem; }
    .pp-loan-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    .pp-loan-options .btn { align-content: center; border-radius: .6rem; font-size: .8rem; font-weight: 700; min-height: 3rem; padding: .45rem .3rem; }
    .pp-loan-options .btn-check:checked + .btn { background: #0754c8 !important; border-color: #0754c8 !important; color: #fff !important; }
    .pp-loan-options .btn-check:focus-visible + .btn { outline: 3px solid #8ebeff; outline-offset: 2px; }
    @media (max-width: 400px) { .pp-loan-options { grid-template-columns: 1fr; } .pp-loan-options .btn { min-height: 2.5rem; } }
    .modal-backdrop {
        z-index: 1050;
    }
    .modal-backdrop + .modal-backdrop,
    .modal-backdrop ~ .modal-backdrop {
        z-index: 1065;
    }
    /* Payment history controls and records */
    .pp-history-card { min-width: 0; border-color: var(--vf-light) !important; box-shadow: 0 8px 24px rgba(7, 84, 200, .10) !important; }
    .pp-history-header { margin-bottom: 1.25rem; }
    .pp-history-filter { display: grid; grid-template-columns: minmax(0, 2fr) minmax(10rem, 1fr) auto; gap: .75rem; align-items: stretch; margin-bottom: 1rem; }
    .pp-history-filter :is(.form-control, .form-select, .btn) { width: 100%; min-width: 0; min-height: 2.75rem; border-radius: .65rem; }
    .pp-history-filter .btn { display: inline-flex; align-items: center; justify-content: center; padding-inline: 1.25rem; }
    .pp-history-filter .status-filter-display > .btn { width: auto; }
    .pp-history-status { display: flex; flex-wrap: wrap; justify-content: flex-start; gap: .5rem; margin-bottom: .75rem; }
    .pp-history-status .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 2.5rem; min-width: 5.5rem; }
    .pp-history-scroll { width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; }
    .payment-history-table {
        width: 100%;
        min-width: 960px;
        table-layout: fixed;
        border-collapse: collapse;
        margin: 0;
    }
    .payment-history-table th,
    .payment-history-table td {
        padding: 12px 14px;
        text-align: left;
        vertical-align: middle;
        border-bottom: 1px solid var(--pp-border);
    }
    .payment-history-table thead th {
        color: #617690;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .035em;
        text-transform: uppercase;
    }
    .payment-history-table th:nth-child(1), .payment-history-table td:nth-child(1) { width: 30%; }
    .payment-history-table th:nth-child(2), .payment-history-table td:nth-child(2) { width: 15%; }
    .payment-history-table th:nth-child(3), .payment-history-table td:nth-child(3) { width: 15%; }
    .payment-history-table th:nth-child(4), .payment-history-table td:nth-child(4) { width: 15%; }
    .payment-history-table th:nth-child(5), .payment-history-table td:nth-child(5) { width: 25%; text-align: right; }
    .payment-history-table tbody td { color: var(--pp-text); font-size: .86rem; font-weight: 700; line-height: 1.35; }
    .payment-history-table .payment-history-reference { overflow-wrap: anywhere; }
    .payment-history-table .payment-history-actions { display: flex; align-items: center; justify-content: flex-end; gap: .5rem; white-space: nowrap; }
    .payment-history-table .payment-history-actions .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; }
    .payment-history-table .payment-history-empty { padding: 2rem 1rem; text-align: center; font-weight: 400; }
    .pp-history-empty { padding: 2rem 1rem; }
    .pp-details-btn {
        background: #fff;
        border: 1px solid rgba(7, 84, 200, .32);
        border-radius: 999px;
        color: var(--vf-royal);
        font-size: .78rem;
        font-weight: 700;
        line-height: 1.2;
        padding: .3rem .8rem;
        white-space: nowrap;
    }
    .pp-details-btn:hover { background: #edf6ff; color: var(--vf-navy); }
    .pp-details-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin: 0; }
    .pp-details-grid > div { min-width: 0; overflow-wrap: anywhere; }
    .pp-details-grid dt { color: #64748b; font-size: .72rem; font-weight: 700; letter-spacing: .04em; margin-bottom: .25rem; text-transform: uppercase; }
    .pp-details-grid dd { color: var(--vf-navy); font-size: .95rem; font-weight: 600; margin: 0; }
    .pp-details-reason { border-top: 1px solid var(--pp-border); margin-top: 1rem; padding-top: 1rem; overflow-wrap: anywhere; }
    @media (max-width: 375.98px) { .pp-details-grid { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) {
        .pp-history-filter { grid-template-columns: minmax(0, 1fr); gap: .6rem; }
        .pp-history-status .btn { flex: 1 1 0; min-width: 0; }
    }
</style>
@include('shared.payments.add-payment-button-styles')
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        
        <div class="payment-portal-wrapper">
            <div class="page-heading">
                <div>
                    <h1>My Payments</h1>
                    <p>Submit payment proof and monitor your payment history.</p>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    @if ($consumer)
                        <x-consumer-status-badge :consumer="$consumer" />
                    @endif
                    <div class="badge pp-badge-account rounded-pill px-3 py-2">Account #{{ $accountNumber }}</div>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success mb-4" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                </div>
            @endif

            <nav class="d-flex flex-wrap align-items-center gap-2 mb-4" aria-label="Billing and payment sections">
                @foreach (['water-payments' => 'Water Payments', 'loan-payments' => 'Loan Payments'] as $key => $label)
                    @php
                        $tabFilters = array_filter([
                            'view' => $key,
                            'search' => $filters['search'] ?: null,
                            'channel' => $filters['channel'] ?: null,
                            'status' => $viewType !== 'review' && $key !== 'review' ? ($filters['status'] ?: null) : null,
                        ], fn ($value) => $value !== null && $value !== '') + ($key === 'water-payments' ? $historyLedger : []);
                    @endphp
                    <a class="btn btn-sm {{ $viewType === $key ? 'btn-primary' : 'btn-light border' }}" href="{{ route('payments.index', $tabFilters) }}">{{ $label }}</a>
                @endforeach
                @if ($historyLedger)
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('payments.index', $billingScenario ? ['ledger' => 'recorded'] : []) }}">{{ $billingScenario ? 'Recorded payments' : 'Scenario payments' }}</a>
                @endif
            </nav>

            <!-- Main Balance Card -->
            <div class="pp-card pp-balance-card p-4 mb-4 {{ in_array($recordType, ['Water', 'Loan'], true) ? 'payment-balance-card' : '' }}">
                <div class="row align-items-center {{ in_array($recordType, ['Water', 'Loan'], true) ? 'payment-balance-layout' : '' }}">
                    <div class="col">
                        <div class="text-uppercase small fw-bold text-muted mb-2" style="letter-spacing: 0.05em;">{{ $recordType }} BALANCE DUE</div>
                        <div class="display-5 text-white fw-bold mb-2">₱{{ number_format($totalBalanceDue, 2) }}</div>
                        <div class="small text-muted">As of {{ $asOfDate }} · Statement period: {{ $statementPeriod }}</div>
                        @if ($billingScenario)
                            <div class="mt-3">@include('consumer.billing.scenario-notice', ['notice' => $billingScenario['notice']])</div>
                        @else
                        <div class="alert payment-requirements {{ $hasDisconnectionNotice ? 'alert-danger' : 'alert-success' }} mt-3 mb-0">
                            <div class="fw-bold mb-2">Account Payment Requirements</div>
                            <div>Payment Status: <strong>{{ $accountPaymentStatus }}</strong></div>
                            <div>Disconnection Notice: <strong>{{ $hasDisconnectionNotice ? 'Active' : 'None' }}</strong></div>
                            <div>Total Outstanding Balance: <strong>&#8369;{{ number_format($totalBalanceDue, 2) }}</strong></div>
                            @if ($hasDisconnectionNotice)
                                <div>Required Payment Amount: <strong>&#8369;{{ number_format($requiredPaymentAmount, 2) }}</strong></div>
                            @endif
                            <div class="mt-2">{{ $paymentRuleMessage }}</div>
                        </div>
                        @if ($hasDisconnectionNotice)
                            <div class="alert alert-danger mt-3 mb-0">
                                <strong>Disconnection Notice</strong><br>
                                Total Outstanding Balance: P {{ number_format($totalBalanceDue, 2) }}<br>
                                Required Payment Amount: P {{ number_format($requiredPaymentAmount, 2) }}<br>
                                Full payment is required. Partial payment is not allowed.
                            </div>
                        @endif
                        @endif
                    </div>
                    @unless ($billingScenario)
                    <div class="col-md-auto mt-3 mt-md-0 {{ in_array($recordType, ['Water', 'Loan'], true) ? 'payment-balance-action' : '' }}">
                        @if ($paymentFormBills->isNotEmpty() && $recordType !== 'Review')
                            <button class="btn viaflow-add-payment" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Payment
                            </button>
                        @else
                            <button type="button" class="btn viaflow-add-payment" @if ($recordType !== 'Review') data-bs-toggle="modal" data-bs-target="#paymentModal" @endif>
                                <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Payment
                            </button>
                            @if ($recordType === 'Review')
                                <div class="text-muted small mt-2 text-center" role="status">Please select Water Payments or Loan Payments to add a payment.</div>
                            @endif
                        @endif
                    </div>
                    @endunless
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="pp-card p-4 h-100">
                        <div class="small text-muted mb-2">Most recent payment</div>
                        <div class="h3 text-white fw-bold mb-2">₱{{ $mostRecentPayment ? number_format($mostRecentPayment->amount, 2) : '0.00' }}</div>
                        @if($mostRecentPayment)
                            <div class="small text-muted d-flex align-items-center gap-2">
                                <span class="pp-status-dot pp-dot-{{ strtolower($mostRecentPayment->status) }}"></span>
                                {{ $mostRecentPayment->status }} · {{ $mostRecentPayment->payment_date->format('M d, Y') }}
                            </div>
                        @else
                            <div class="small text-muted">No recent payments</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="pp-card p-4 h-100">
                        <div class="small text-muted mb-2">Payments this month</div>
                        <div class="h3 text-white fw-bold mb-2">{{ $submittedThisMonthCount }} submitted</div>
                        <div class="small text-muted d-flex align-items-center gap-2">
                            <span class="pp-status-dot pp-dot-pending"></span>
                            {{ $pendingThisMonthCount }} pending review
                        </div>
                    </div>
                </div>
            </div>

            @if ($showBills)
                <div class="pp-card p-4 mb-4">
                    <h3 class="h5 fw-bold mb-3">{{ $recordType }} Bills</h3>
                    <div class="table-responsive"><table class="table align-middle">
                        <thead><tr><th>Reference</th><th>{{ $recordType === 'Loan' ? 'Installment' : 'Billing Period' }}</th><th>Due Date</th><th>Amount Due</th><th>Amount Paid</th><th>Remaining Balance</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($availableBills as $bill)
                            @php $installment = $bill->loanInstallment; @endphp
                            <tr>
                                <td>{{ $bill->prn }}<div class="small text-muted">{{ $recordType }}</div></td>
                                <td>{{ $recordType === 'Loan' ? '#'.($installment?->installment_number ?? '—') : ($bill->billing_period?->format('M Y') ?? 'N/A') }}</td>
                                <td>{{ $installment?->due_date?->format('M d, Y') ?? $bill->due_date?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>₱{{ number_format((float) $bill->amount, 2) }}</td>
                                <td>₱{{ number_format((float) $bill->verified_paid_amount, 2) }}</td>
                                <td>₱{{ number_format((float) $bill->balance_due, 2) }}</td>
                                <td><span class="badge {{ $bill->paymentSettlementStatus() === 'Paid' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $bill->paymentSettlementStatus() }}</span></td>
                                <td>@if ($bill->balance_due > 0 && $recordType !== 'Review')<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal" data-bill-id="{{ $bill->id }}">Pay</button>@elseif($recordType === 'Water')<a class="btn btn-sm btn-light border" href="{{ route('billing.show', $bill) }}">Statement</a>@elseif($recordType === 'Loan')<span class="text-muted small">{{ $bill->balance_due <= 0 ? 'Settled' : 'Due' }}</span>@else<span class="text-muted small">Review required</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No {{ strtolower($recordType) }} bills found.</td></tr>
                        @endforelse
                        @foreach ($loanInstallments as $installment)
                            @if (! $installment->bill)
                                <tr><td>Schedule #{{ $installment->installment_number }}</td><td>#{{ $installment->installment_number }}</td><td>{{ $installment->due_date?->format('M d, Y') ?? 'N/A' }}</td><td>₱{{ number_format((float) $installment->required_amount, 2) }}</td><td>₱{{ number_format((float) $installment->amount_paid, 2) }}</td><td>₱{{ number_format((float) $installment->remaining_balance, 2) }}</td><td>{{ $installment->status }}</td><td>Not billed</td></tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endif

            @if (! $showBills || $viewType === 'review')
            <!-- Payment History -->
            <div class="pp-card pp-history-card p-4">
                <div class="pp-history-header">
                    <h3 class="h5 text-white fw-bold mb-1">Payment History</h3>
                    <p class="small text-muted mb-0">Filter your submitted payments by status or search by reference.</p>
                </div>
                    <form method="GET" action="{{ route('payments.index') }}" class="pp-history-filter" data-auto-filter data-auto-filter-debounce="450">
                        <input type="hidden" name="view" value="{{ $viewType }}">
                        @if ($historyLedger)<input type="hidden" name="ledger" value="{{ $historyLedger['ledger'] }}">@endif
                        @if ($filters['status'] !== '')
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                        @endif
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="payment_filter_search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Search reference or PRN"
                        >
                        <div class="d-flex align-items-center gap-2" style="min-width:0"><select data-clearable-filter style="min-width:0;flex:1 1 0" class="form-select form-select-sm" name="channel" aria-label="Filter by payment channel">
                            @foreach ($channels as $channelOption)
                                <option value="{{ $channelOption }}" @selected($filters['channel'] === $channelOption)>{{ $channelOption === 'Counter' ? 'Over the Counter' : $channelOption }}</option>
                            @endforeach
                        </select>
<button type="submit" name="channel" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear channel filter">Clear</button>
</div>
                        <noscript>
                            <button type="submit" class="btn btn-sm btn-primary">Apply filters</button>
                        </noscript>
                        @if ($filters['search'] !== '' || $filters['channel'] !== '' || $filters['status'] !== '')<a href="{{ route('payments.index', ['view' => $viewType] + $historyLedger) }}" class="btn btn-sm btn-light border">Clear filters</a>@endif
                    </form>

                <div class="pp-history-status">
                    @foreach (['' => ['label' => 'All', 'class' => 'pp-filter-all'], 'Pending' => ['label' => 'Pending', 'class' => 'pp-filter-pending'], 'Verified' => ['label' => 'Verified', 'class' => 'pp-filter-verified'], 'Rejected' => ['label' => 'Rejected', 'class' => 'pp-filter-rejected']] as $statusValue => $statusOption)
                        <a
                            href="{{ route('payments.index', array_filter(['view' => $viewType, 'status' => $statusValue !== '' ? $statusValue : null, 'search' => $filters['search'], 'channel' => $filters['channel']], fn ($value) => $value !== null && $value !== '') + $historyLedger) }}"
                            class="btn btn-sm pp-filter-btn {{ $statusOption['class'] }} {{ $filters['status'] === $statusValue ? 'active' : '' }}"
                        >{{ $statusOption['label'] }}</a>
                    @endforeach
                </div>

                <div class="pp-history-scroll">
                <table class="payment-history-table" aria-label="Payment history">
                    <colgroup><col style="width:30%"><col style="width:15%"><col style="width:15%"><col style="width:15%"><col style="width:25%"></colgroup>
                    <thead><tr><th scope="col">Payment Reference</th><th scope="col">Amount</th><th scope="col">Balance</th><th scope="col">Payment Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    @forelse($payments as $payment)
                        @php
                            $paymentStatusClass = match ($payment->status) {
                                'Verified' => 'badge-soft-success',
                                'Rejected' => 'badge-soft-danger',
                                default => 'badge-soft-warning',
                            };
                            $billStatus = $payment->billStatusLabel();
                        @endphp
                        <tr>
                            <td class="payment-history-reference">{{ $payment->reference_number }}</td>
                            <td>₱{{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->status === 'Rejected' && ! $payment->bill ? 'N/A' : '₱'.number_format((float) $payment->remaining_balance_after_payment, 2) }}</td>
                            <td><span class="badge status-badge {{ $paymentStatusClass }}">{{ $payment->status }}</span></td>
                            <td><div class="payment-history-actions">
                                    <button type="button" class="btn pp-details-btn" data-bs-toggle="modal" data-bs-target="#paymentHistoryDetailsModal"
                                        data-reference="{{ $payment->reference_number }}"
                                        data-type="{{ $payment->payment_type }}"
                                        data-prn="{{ $payment->bill?->prn ?? ($payment->loan_id ? 'Loan #'.$payment->loan_id : 'No PRN') }}"
                                        data-period="{{ $payment->bill?->billing_period?->format('M Y') ?? 'Not available' }}"
                                        data-method="{{ $payment->payment_method }}"
                                        data-date="{{ $payment->payment_date?->format('M d, Y') ?? 'Not available' }}"
                                        data-bill-status="{{ $billStatus }}"
                                        data-rejection-reason="{{ $payment->status === 'Rejected' ? ($payment->rejection_reason ?? '') : '' }}"
                                        aria-label="View details for payment {{ $payment->reference_number }}">View Details</button>
                                    @if ($payment->status === 'Verified' && ! $payment->is_billing_fixture)
                                        <a
                                            href="{{ route('payments.receipt', $payment) }}"
                                            class="btn pp-receipt-btn"
                                            data-bs-toggle="modal" data-bs-target="#receiptPreviewModal"
                                            aria-label="View receipt for payment {{ $payment->reference_number }}"
                                        >
                                            <i class="bi bi-receipt me-1"></i>View Receipt
                                        </a>
                                    @endif
                            </div></td>
                        </tr>
                    @empty
                        @if ($viewType !== 'loan-payments' || $manualLoanRepayments->isEmpty())
                            <tr><td colspan="5" class="payment-history-empty text-muted">
                                <i class="bi bi-receipt fs-1 d-block mb-3 opacity-25"></i>
                                No payments recorded.
                            </td></tr>
                        @endif
                    @endforelse
                    @if ($viewType === 'loan-payments')
                        @foreach ($manualLoanRepayments as $repayment)
                            <tr>
                                <td class="payment-history-reference">Recorded loan repayment</td>
                                <td>₱{{ number_format((float) $repayment->amount, 2) }}</td>
                                <td>{{ $repayment->bill ? '₱'.number_format((float) $repayment->bill->balance_due, 2) : 'N/A' }}</td>
                                <td><span class="badge badge-soft-success">Recorded</span></td>
                                <td><div class="payment-history-actions">
                                        <button type="button" class="btn pp-details-btn" data-bs-toggle="modal" data-bs-target="#paymentHistoryDetailsModal"
                                            data-reference="Recorded loan repayment" data-type="Loan"
                                            data-prn="{{ $repayment->bill?->prn ?? 'No PRN' }}"
                                            data-period="{{ $repayment->bill?->billing_period?->format('M Y') ?? 'Not available' }}"
                                            data-method="Not recorded"
                                            data-installment="{{ $repayment->bill?->loanInstallment?->installment_number ?? '' }}"
                                            data-date="{{ $repayment->payment_date?->format('M d, Y') ?? 'Not available' }}"
                                            data-bill-status="{{ $repayment->bill?->paymentSettlementStatus() ?? 'N/A' }}"
                                            data-rejection-reason=""
                                            aria-label="View details for recorded loan repayment">View Details</button>
                                </div></td>
                            </tr>
                        @endforeach
                    @endif
                    </tbody>
                </table>
                </div>
                
                @if($payments->hasPages())
                    <div class="mt-4 pp-pagination">
                        {{ $payments->links() }}
                    </div>
                @endif
            </div>
            @endif
        </div>
    </main>
</div>

<div class="modal fade" id="paymentHistoryDetailsModal" tabindex="-1" aria-labelledby="paymentHistoryDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <div><h2 class="modal-title fs-5 fw-bold" id="paymentHistoryDetailsTitle">Payment Details</h2><div class="small text-secondary" id="history_detail_reference"></div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="pp-details-grid">
                    <div><dt>Payment Type</dt><dd id="history_detail_type">-</dd></div>
                    <div><dt>PRN</dt><dd id="history_detail_prn">-</dd></div>
                    <div><dt>Billing Period</dt><dd id="history_detail_period">-</dd></div>
                    <div><dt>Payment Method</dt><dd id="history_detail_method">-</dd></div>
                    <div><dt>Payment Date</dt><dd id="history_detail_date">-</dd></div>
                    <div><dt>Bill Status</dt><dd id="history_detail_bill_status">-</dd></div>
                    <div id="history_detail_installment_wrap" hidden><dt>Installment</dt><dd id="history_detail_installment">-</dd></div>
                </dl>
                <div class="pp-details-reason" id="history_detail_rejection_wrap" hidden>
                    <div class="small text-secondary fw-semibold">Rejection Reason:</div>
                    <div id="history_detail_rejection_reason"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

@unless ($billingScenario)
@include('shared.payments.receipt-modal')

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" id="paymentModalLabel">Add Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('payments.store', ['view' => $viewType] + $historyLedger) }}" enctype="multipart/form-data" id="consumerPaymentForm">
                    @csrf
                    <input type="hidden" name="payment_channel" value="Online">
                    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">

                    @if ($errors->any())
                        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
                            <i class="bi bi-exclamation-circle mt-1"></i>
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    <div class="pp-payment-grid">
                        <section class="pp-payment-panel pp-payment-fields" aria-labelledby="pp-payment-fields-title">
                            <h2 class="pp-payment-section-title" id="pp-payment-fields-title">Payment Details</h2>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase mb-2" for="consumer_payment_bill_selector">{{ $isLoanPayment ? 'Select Active Loan' : 'Unpaid bill' }}</label>
                                <select class="form-select pp-modal-input" id="consumer_payment_bill_selector" name="{{ $isLoanPayment ? 'loan_id' : 'bill_id' }}" required>
                                    <option value="">{{ $isLoanPayment ? 'Select an active loan' : 'Select a bill' }}</option>
                                    @if ($isLoanPayment)
                                        @foreach ($paymentFormLoans as $loan)
                                            @php($nextInstallment = $loan->installments->first(fn ($item) => (float) $item->remaining_balance > 0))
                                            <option value="{{ $loan->id }}"
                                                data-loan-balance="{{ number_format(app(\App\Services\LoanRepaymentAllocationService::class)->remainingBalance($loan), 2, '.', '') }}"
                                                data-next-installment="{{ number_format((float) ($nextInstallment?->remaining_balance ?? 0), 2, '.', '') }}"
                                                data-first-month="{{ $loan->installments->first()?->billing_period?->format('M Y') ?? 'Not set' }}"
                                                data-monthly="{{ number_format((float) $loan->monthly_installment, 2, '.', '') }}"
                                                data-installments="{{ $loan->installments->map(fn ($item) => ['month' => $item->billing_period?->format('M Y'), 'balance' => (float) $item->remaining_balance])->values()->toJson() }}"
                                                @disabled(! $loan->payment_selectable) @selected($selectedLoan?->id === $loan->id)>
                                                Loan #{{ $loan->id }} · {{ $loan->term_months }} months{{ $loan->payment_selectable ? '' : ' · Administrator review required' }}
                                            </option>
                                        @endforeach
                                    @else
                                        @foreach ($waterBills as $bill)
                                            <option value="{{ $bill->id }}" data-reference="{{ $bill->prn }}"
                                                data-balance="{{ number_format((float) $bill->balance_due, 2, '.', '') }}" @selected($selectedBill?->id === $bill->id)>
                                                Water bill · {{ $bill->prn }} · Balance PHP {{ number_format((float) $bill->balance_due, 2) }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <div class="form-text" id="consumer_selected_bill_summary"></div>
                                @if (! $isLoanPayment && $waterBills->isEmpty())
                                    <div class="form-text">No payment is currently due.</div>
                                @endif
                                @if ($isLoanPayment)
                                    @if ($paymentFormLoans->contains(fn ($loan) => ! $loan->payment_selectable))
                                        <div class="form-text text-warning">Some approved loans need administrator review before payment.</div>
                                    @endif
                                    <div class="pp-loan-summary d-none mt-3" id="consumer_loan_summary">
                                        <div class="pp-loan-summary-grid">
                                            <div><span>Selected loan</span><strong id="loan_summary_reference">—</strong></div>
                                            <div><span>First billing month</span><strong id="loan_summary_first_month">—</strong></div>
                                            <div><span>Monthly installment</span><strong id="loan_summary_monthly">—</strong></div>
                                            <div><span>Amount due</span><strong id="loan_summary_due">—</strong></div>
                                            <div><span>Remaining loan balance</span><strong id="loan_summary_balance">—</strong></div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <fieldset id="loan_payment_options">
                                            <legend class="form-label small fw-bold text-secondary text-uppercase mb-2">Payment option</legend>
                                            <div class="pp-loan-options">
                                                <input class="btn-check" type="radio" name="loan_payment_option" id="loan_option_monthly" value="monthly" checked><label class="btn btn-outline-primary" for="loan_option_monthly">Monthly Payment</label>
                                                <input class="btn-check" type="radio" name="loan_payment_option" id="loan_option_custom" value="custom"><label class="btn btn-outline-primary" for="loan_option_custom">Custom Amount</label>
                                                <input class="btn-check" type="radio" name="loan_payment_option" id="loan_option_advance" value="advance"><label class="btn btn-outline-primary" for="loan_option_advance">Advance Payment</label>
                                                <input class="btn-check" type="radio" name="loan_payment_option" id="loan_option_full" value="full"><label class="btn btn-outline-primary" for="loan_option_full">Pay in Full</label>
                                            </div>
                                        </fieldset>
                                        <div class="pp-loan-preview mt-3">
                                            <div class="small fw-bold mb-1">Payment Allocation Preview</div>
                                            <div class="small" id="loan_allocation_preview" aria-live="polite">Select a loan to preview the installments covered.</div>
                                            <div class="small text-secondary mt-1">Installments are updated after admin verification.</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase mb-2" for="sender_name">Sender name</label>
                                    <input type="text" class="form-control pp-modal-input" id="sender_name" name="sender_name" value="{{ old('sender_name') }}" placeholder="Sender name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase mb-2" for="phone_number">Sender phone number</label>
                                    <input type="text" class="form-control pp-modal-input" id="phone_number" name="sender_phone" value="{{ old('sender_phone') }}" placeholder="09XXXXXXXXX">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase mb-2" for="payment_method">Payment method</label>
                                <select class="form-select pp-modal-input" id="payment_method" name="payment_method" required>
                                    @foreach ($paymentMethods as $method)
                                        @continue($method === 'Cash')
                                        <option value="{{ $method }}" @selected(old('payment_method', 'GCash') === $method)>{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if (! $isLoanPayment && ! $formHasDisconnectionNotice)
                                <div class="alert alert-success mb-3" id="consumer_water_payment_rule">
                                    <strong>No Disconnection Notice</strong><br>
                                    Total Outstanding Balance: &#8369;{{ number_format((float) ($formPaymentSummary['total_outstanding'] ?? 0), 2) }}<br>
                                    {{ $formPaymentSummary['message'] ?? 'Partial payment is allowed.' }}
                                </div>
                            @endif
                            @if (! $isLoanPayment && $formHasDisconnectionNotice)
                                <div class="alert alert-danger mb-3" id="consumer_water_disconnection_notice">
                                    <strong>Disconnection Notice</strong><br>
                                    Total Outstanding Balance: P {{ number_format((float) ($formPaymentSummary['total_outstanding'] ?? 0), 2) }}<br>
                                    Required Payment Amount: P {{ number_format($formRequiredPaymentAmount, 2) }}<br>
                                    Full payment is required. Partial payment is not allowed.
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase mb-2" for="gateway_reference">Reference / transaction number</label>
                                <input type="text" class="form-control pp-modal-input" id="gateway_reference" name="gateway_reference" value="{{ old('gateway_reference') }}" required placeholder="e.g. 3039939752952">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase mb-2" for="amount">Amount paid (₱)</label>
                                    <input type="number" class="form-control pp-modal-input" id="amount" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required placeholder="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase mb-2" for="payment_date">Payment date</label>
                                    <input type="date" class="form-control pp-modal-input" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required>
                                </div>
                            </div>
                            
                            <div class="pp-receipt-upload" id="proof_dropzone" role="button" tabindex="0" aria-label="Upload payment receipt">
                                <i class="bi bi-cloud-arrow-up text-primary fs-4" aria-hidden="true"></i>
                                <strong class="small">Upload payment receipt</strong>
                                <span class="small text-secondary">JPG, PNG, or PDF up to 2 MB. Reference, amount, and date auto-fill after upload.</span>
                            </div>
                            <input type="file" class="visually-hidden" id="proof" name="proof" accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="small text-muted mt-1 mb-2" id="proof_file_name">No file selected yet.</div>
                            <div id="receipt_parse_status" class="small mb-3" aria-live="polite"></div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3" id="submit_payment_button" @disabled($isLoanPayment ? ! $selectedLoan : ! $selectedBill)>Submit payment</button>
                        </section>

                        <div class="pp-payment-preview-column">
                            <section class="pp-payment-panel" aria-labelledby="pp-receipt-preview-title">
                                <h2 class="pp-payment-section-title" id="pp-receipt-preview-title">Payment Confirmation Preview</h2>
                                <div class="pp-receipt-preview" id="receipt_preview">
                                    <img alt="Uploaded payment receipt" class="pp-receipt-preview-media" id="receipt_preview_image" hidden>
                                    <iframe title="PDF receipt preview" class="pp-receipt-preview-media" id="receipt_preview_pdf" hidden></iframe>
                                    <div class="text-center p-3" id="receipt_preview_placeholder">
                                        <i class="bi bi-receipt fs-4 text-primary d-block mb-1" aria-hidden="true"></i>
                                        <span class="small text-secondary">Your uploaded receipt will appear here.</span>
                                    </div>
                                </div>
                                <div class="pp-receipt-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="receipt_download" disabled><i class="bi bi-download me-1" aria-hidden="true"></i>Download</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="receipt_share" disabled><i class="bi bi-share me-1" aria-hidden="true"></i>Share</button>
                                </div>
                                <p class="pp-receipt-action-status small text-secondary" id="receipt_action_status" role="status"></p>
                            </section>

                            <section class="pp-payment-panel" aria-labelledby="pp-organization-qr-title">
                                <h2 class="pp-payment-section-title" id="pp-organization-qr-title">Organization Payment QR</h2>
                                <p class="small text-secondary mb-0">Scan to pay Via-Vida Waterworks Association.</p>
                                <div class="pp-organization-qr">
                                    <span class="pp-qr-image-window">
                                        <img src="{{ asset('images/organization-payment-qr.png') }}" width="405" height="300" alt="Via-Vida Waterworks Association InstaPay QR code">
                                    </span>
                                </div>
                            </section>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentSubmitConfirmModal" tabindex="-1" aria-labelledby="paymentSubmitConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; background: #ffffff; color: var(--vf-navy);">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="paymentSubmitConfirmModalLabel">Confirm Payment Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-0 text-secondary">Please double-check your payment details and receipt before submitting. Are you sure you want to submit this payment for verification?</p>
            </div>
            <div class="modal-footer border-top-0 pt-0 d-flex justify-content-end gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary fw-bold px-3" data-bs-dismiss="modal" id="cancel_submit_payment_button">Cancel</button>
                <button type="button" class="btn btn-primary fw-bold px-3" id="confirm_submit_payment_button">Yes, Submit for Confirmation</button>
            </div>
        </div>
    </div>
</div>
@endunless
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('paymentHistoryDetailsModal')?.addEventListener('show.bs.modal', function (event) {
            const data = event.relatedTarget?.dataset;
            if (!data) return;
            for (const [field, key] of Object.entries({
                reference: 'reference', type: 'type', prn: 'prn', period: 'period',
                method: 'method', date: 'date', bill_status: 'billStatus',
            })) {
                document.getElementById('history_detail_' + field).textContent = data[key] || 'Not available';
            }
            const installment = data.installment || '';
            document.getElementById('history_detail_installment').textContent = installment ? '#' + installment : '';
            document.getElementById('history_detail_installment_wrap').hidden = !installment;
            const reason = data.rejectionReason || '';
            document.getElementById('history_detail_rejection_reason').textContent = reason;
            document.getElementById('history_detail_rejection_wrap').hidden = !reason;
        });
        if (!document.getElementById('consumerPaymentForm')) return;
        const amountInput = document.getElementById('amount');
        const proofInput = document.getElementById('proof');
        const proofDropzone = document.getElementById('proof_dropzone');
        const gatewayReferenceInput = document.getElementById('gateway_reference');
        const paymentDateInput = document.getElementById('payment_date');
        const paymentMethodInput = document.getElementById('payment_method');
        const senderNameInput = document.getElementById('sender_name');
        const senderPhoneInput = document.getElementById('phone_number');
        const proofFileName = document.getElementById('proof_file_name');
        const receiptPreview = document.getElementById('receipt_preview');
        const previewImage = document.getElementById('receipt_preview_image');
        const previewPdf = document.getElementById('receipt_preview_pdf');
        const previewPlaceholder = document.getElementById('receipt_preview_placeholder');
        const receiptDownload = document.getElementById('receipt_download');
        const receiptShare = document.getElementById('receipt_share');
        const receiptActionStatus = document.getElementById('receipt_action_status');
        const parseStatus = document.getElementById('receipt_parse_status');
        const submitButton = document.getElementById('submit_payment_button');
        const isLoanPayment = @json($isLoanPayment);
        const paymentSelector = document.getElementById('consumer_payment_bill_selector');
        const billSummary = document.getElementById('consumer_selected_bill_summary');
        const loanSummary = document.getElementById('consumer_loan_summary');
        const loanOptions = document.getElementById('loan_payment_options');
        const selectedLoanOption = () => document.querySelector('input[name="loan_payment_option"]:checked')?.value || 'monthly';
        const allocationPreview = document.getElementById('loan_allocation_preview');
        document.getElementById('paymentModal')?.addEventListener('show.bs.modal', function (event) {
            const billId = event.relatedTarget?.dataset.billId;
            if (!isLoanPayment && billId && [...paymentSelector.options].some((item) => item.value === billId)) {
                paymentSelector.value = billId;
                syncPaymentSelection();
            }
        });
        const paymentForm = document.getElementById('consumerPaymentForm');
        const confirmSubmitButton = document.getElementById('confirm_submit_payment_button');
        const cancelSubmitButton = document.getElementById('cancel_submit_payment_button');
        const confirmModalElement = document.getElementById('paymentSubmitConfirmModal');
        const confirmModal = confirmModalElement ? new bootstrap.Modal(confirmModalElement) : null;
        const hasDisconnectionNotice = @json($formHasDisconnectionNotice);
        const requiredPaymentAmount = Number(@json(round((float) $formRequiredPaymentAmount, 2)));
        let paymentSubmissionConfirmed = false;
        let paymentSubmissionProcessing = false;
        let receiptUrl = null;

        function syncPaymentSelection() {
            const option = paymentSelector.selectedOptions[0];
            const selected = option?.value && !option.disabled ? option : null;

            if (isLoanPayment) {
                const hasPayableLoan = [...paymentSelector.options].some((item) => item.value && !item.disabled);
                billSummary.textContent = hasPayableLoan ? '' : 'No approved loans are currently available for payment.';
                loanSummary.classList.toggle('d-none', !selected);
                loanOptions.disabled = !selected;
                if (selected) {
                    amountInput.max = selected.dataset.loanBalance;
                    document.getElementById('loan_summary_reference').textContent = `Loan #${selected.value}`;
                    document.getElementById('loan_summary_first_month').textContent = selected.dataset.firstMonth;
                    document.getElementById('loan_summary_monthly').textContent = `₱${selected.dataset.monthly}`;
                    document.getElementById('loan_summary_due').textContent = `₱${selected.dataset.nextInstallment}`;
                    document.getElementById('loan_summary_balance').textContent = `₱${selected.dataset.loanBalance}`;
                } else {
                    amountInput.removeAttribute('max');
                    amountInput.readOnly = false;
                }
                updateAllocationPreview();
            } else {
                billSummary.textContent = selected
                    ? `Reference: ${selected.dataset.reference} · Remaining balance: PHP ${selected.dataset.balance}`
                    : (paymentSelector.options.length > 1 ? 'Select a bill to view its amount and remaining balance.' : '');
            }
            setSubmitEnabled(Boolean(selected));
        }

        function updateAllocationPreview() {
            const option = paymentSelector.selectedOptions[0];
            if (!option?.value || option.disabled) {
                allocationPreview.textContent = 'Select a loan to preview the installments covered.';
                return;
            }
            let remaining = Math.round(Number(amountInput.value || 0) * 100);
            const balance = Math.round(Number(option.dataset.loanBalance) * 100);
            if (remaining > balance) {
                allocationPreview.textContent = 'Amount exceeds the remaining loan balance.';
                return;
            }
            const covered = [];
            for (const installment of JSON.parse(option.dataset.installments || '[]')) {
                const due = Math.round(Number(installment.balance) * 100);
                const applied = Math.min(remaining, due);
                if (applied > 0) covered.push(`${installment.month}: PHP ${(applied / 100).toFixed(2)} (${due === applied ? 'paid' : 'PHP ' + ((due - applied) / 100).toFixed(2) + ' remaining'})`);
                remaining -= applied;
                if (remaining <= 0) break;
            }
            allocationPreview.textContent = covered.length ? `After verification: ${covered.join('; ')}. Loan balance: PHP ${((balance - Math.round(Number(amountInput.value) * 100)) / 100).toFixed(2)}.` : 'Enter an amount to preview the installments covered.';
        }

        function applyLoanOption() {
            const option = paymentSelector.selectedOptions[0];
            if (!option?.value || option.disabled) return;
            const choice = selectedLoanOption();
            amountInput.readOnly = !['custom', 'advance'].includes(choice);
            const nextTwo = JSON.parse(option.dataset.installments || '[]').filter((item) => Number(item.balance) > 0).slice(0, 2);
            const advance = nextTwo.reduce((sum, item) => sum + Math.round(Number(item.balance) * 100), 0);
            amountInput.value = choice === 'monthly' ? option.dataset.nextInstallment : (choice === 'full' ? option.dataset.loanBalance : (choice === 'advance' ? (advance / 100).toFixed(2) : ''));
            updateAllocationPreview();
            validateFields();
            updateSubmitState();
        }

        paymentSelector.addEventListener('change', function () {
            amountInput.value = '';
            syncPaymentSelection();
            if (isLoanPayment) applyLoanOption();
        });
        if (isLoanPayment) {
            loanOptions.addEventListener('change', applyLoanOption);
            amountInput.addEventListener('input', updateAllocationPreview);
        }

        function hasValue(input) {
            return Boolean(input && String(input.value || '').trim() !== '');
        }

        function isPositiveAmount(input) {
            return hasValue(input) && Number(input.value) > 0;
        }

        function validateFields() {
            if (proofInput && !proofInput.files?.length) {
                proofInput.setCustomValidity('Please upload a payment receipt or confirmation.');
            } else if (proofInput) {
                proofInput.setCustomValidity('');
            }

            if (!isLoanPayment && hasDisconnectionNotice && amountInput) {
                if (Number(amountInput.value || 0) < requiredPaymentAmount) {
                    amountInput.setCustomValidity(`Full payment of ₱${requiredPaymentAmount.toFixed(2)} is required.`);
                } else {
                    amountInput.setCustomValidity('');
                }
            } else if (amountInput) {
                amountInput.setCustomValidity('');
            }
        }

        function requiredPaymentFieldsAreFilled() {
            return Boolean(paymentSelector.value)
                && hasValue(paymentMethodInput)
                && hasValue(gatewayReferenceInput)
                && isPositiveAmount(amountInput)
                && hasValue(paymentDateInput)
                && Boolean(proofInput?.files?.length)
                && (isLoanPayment || !hasDisconnectionNotice || Number(amountInput.value) >= requiredPaymentAmount);
        }

        function updateSubmitState() {
            if (submitButton) {
                submitButton.disabled = paymentSubmissionProcessing || !Boolean(paymentSelector.value);
            }
        }

        function setSubmitEnabled(enabled) {
            if (submitButton) {
                submitButton.disabled = paymentSubmissionProcessing || !enabled || !requiredPaymentFieldsAreFilled();
            }
        }

        function clearDetectedFields() {
            if (gatewayReferenceInput) gatewayReferenceInput.value = '';
            if (amountInput) amountInput.value = '';
            if (paymentDateInput && !paymentDateInput.value) {
                paymentDateInput.value = new Date().toISOString().split('T')[0];
            }
            if (isLoanPayment && !['custom', 'advance'].includes(selectedLoanOption())) applyLoanOption();
            validateFields();
        }

        function applyDetectedFields(fields) {
            if (fields?.reference_number && gatewayReferenceInput) {
                gatewayReferenceInput.value = fields.reference_number;
            }
            if (fields?.amount && amountInput) {
                amountInput.value = fields.amount;
                if (isLoanPayment) {
                    document.getElementById('loan_option_custom').checked = true;
                    amountInput.readOnly = false;
                    updateAllocationPreview();
                }
            }
            if (fields?.payment_date && paymentDateInput) {
                paymentDateInput.value = fields.payment_date;
            }

            if (fields?.payment_method && paymentMethodInput) {
                [...paymentMethodInput.options].forEach((option) => {
                    if (option.value.toLowerCase() === fields.payment_method.toLowerCase()) {
                        paymentMethodInput.value = option.value;
                    }
                });
            }

            if (fields?.phone_number && senderPhoneInput && !senderPhoneInput.value) {
                senderPhoneInput.value = fields.phone_number;
            }

            validateFields();
            updateSubmitState();
        }

        if (!gatewayReferenceInput?.value && !amountInput?.value) {
            clearDetectedFields();
        }

        function updatePreview(file) {
            previewImage.removeAttribute('src');
            previewPdf.removeAttribute('src');
            if (receiptUrl) URL.revokeObjectURL(receiptUrl);
            receiptUrl = file ? URL.createObjectURL(file) : null;

            const isPdf = file && (file.type === 'application/pdf' || /\.pdf$/i.test(file.name));
            receiptPreview.classList.toggle('has-receipt', Boolean(file));
            previewImage.hidden = !file || isPdf;
            previewPdf.hidden = !isPdf;
            previewPlaceholder.hidden = Boolean(file);
            receiptDownload.disabled = receiptShare.disabled = !file;
            receiptActionStatus.textContent = '';
            proofFileName.textContent = file?.name ?? 'No file selected yet.';

            if (file) {
                const media = isPdf ? previewPdf : previewImage;
                media.src = receiptUrl;
            } else {
                clearDetectedFields();
            }
        }

        receiptDownload.addEventListener('click', function () {
            const file = proofInput.files[0];
            if (!file || !receiptUrl) return;

            const link = document.createElement('a');
            link.href = receiptUrl;
            link.download = file.name;
            link.click();
            receiptActionStatus.textContent = '';
        });

        receiptShare.addEventListener('click', async function () {
            const file = proofInput.files[0];
            if (!file) return;

            const shareData = { files: [file] };
            receiptActionStatus.textContent = '';
            if (!navigator.share || !navigator.canShare?.(shareData)) {
                receiptActionStatus.textContent = 'Download the receipt to share it from this browser.';
                return;
            }

            try {
                await navigator.share(shareData);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    receiptActionStatus.textContent = 'The receipt could not be shared. You can download it instead.';
                }
            }
        });

        async function parseReceipt(file) {
            if (!file || !parseStatus) return;

            parseStatus.className = 'small mb-3 text-muted';
            parseStatus.textContent = 'Analyzing receipt...';

            const formData = new FormData();
            formData.append('proof', file);
            formData.append('_token', '{{ csrf_token() }}');

            try {
                const response = await fetch('{{ route('payments.parse-receipt') }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const responseText = await response.text();
                let data = {};

                try {
                    data = responseText ? JSON.parse(responseText) : {};
                } catch (parseError) {
                    throw new Error(`Receipt scan returned HTTP ${response.status} instead of JSON. ${responseText.slice(0, 160)}`);
                }

                if (!response.ok || !data.ok) {
                    parseStatus.className = 'small mb-3 text-warning';
                    parseStatus.textContent = data.message || 'Some receipt details could not be detected. Enter the missing reference number, amount, or payment date manually.';
                    applyDetectedFields(data.fields || {});
                    updateSubmitState();
                    return;
                }

                applyDetectedFields(data.fields || {});

                parseStatus.className = 'small mb-3 text-success';
                parseStatus.textContent = 'Receipt details detected. Please verify your reference number, amount, and payment date.';
            } catch (error) {
                parseStatus.className = 'small mb-3 text-warning';
                parseStatus.textContent = `Receipt scan note: ${error.message || 'Automatic detection unavailable. You can enter the details manually.'}`;
                updateSubmitState();
            }
        }

        if (proofInput) {
            proofInput.addEventListener('change', function () {
                const file = this.files[0];
                validateFields();
                updatePreview(file);
                if (file) {
                    parseReceipt(file);
                }
                updateSubmitState();
            });
        }

        [paymentMethodInput, gatewayReferenceInput, amountInput, paymentDateInput, senderNameInput, senderPhoneInput].forEach((input) => {
            input?.addEventListener('input', function () {
                validateFields();
                updateSubmitState();
            });
            input?.addEventListener('change', function () {
                validateFields();
                updateSubmitState();
            });
        });

        if (proofDropzone) {
            proofDropzone.addEventListener('click', () => proofInput?.click());
            proofDropzone.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    proofInput?.click();
                }
            });
            ['dragenter', 'dragover'].forEach((eventName) => {
                proofDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });
            proofDropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const files = e.dataTransfer?.files;
                if (files && files.length > 0 && proofInput) {
                    proofInput.files = files;
                    proofInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }

        paymentForm?.addEventListener('submit', function (event) {
            if (paymentSubmissionConfirmed) {
                return;
            }

            event.preventDefault();

            validateFields();

            if (!paymentForm.checkValidity() || !requiredPaymentFieldsAreFilled()) {
                updateSubmitState();
                paymentForm.reportValidity();
                return;
            }

            confirmModal?.show();
        });

        confirmSubmitButton?.addEventListener('click', function () {
            if (!paymentForm || paymentSubmissionProcessing) {
                return;
            }

            validateFields();

            if (!paymentForm.checkValidity() || !requiredPaymentFieldsAreFilled()) {
                confirmModal?.hide();
                updateSubmitState();
                paymentForm.reportValidity();
                return;
            }

            paymentSubmissionProcessing = true;
            paymentSubmissionConfirmed = true;
            confirmSubmitButton.disabled = true;
            confirmSubmitButton.textContent = 'Submitting...';
            setSubmitEnabled(false);
            paymentForm.submit();
        });

        cancelSubmitButton?.addEventListener('click', function () {
            confirmModal?.hide();
        });

        confirmModalElement?.addEventListener('hidden.bs.modal', function () {
            if (document.getElementById('paymentModal')?.classList.contains('show')) {
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
        });

        updateSubmitState();
        syncPaymentSelection();
        if (isLoanPayment && !amountInput.value) applyLoanOption();

        @if($errors->any())
            const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        @endif
    });
</script>
@endpush
