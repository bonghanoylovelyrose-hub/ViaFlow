@extends('layouts.app')

@php
    $activePage = 'payments';
    $formatPaymentReference = fn (string $reference): string => preg_replace('/^\D+/', '', str_replace('-', '', $reference)) ?: $reference;
@endphp

@section('title', 'Payments')

@push('styles')
@include('shared.payments.add-payment-button-styles')
<style>
    .payments-shell {
        display: grid;
        gap: 1.25rem;
    }

    .payments-page-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem 1.5rem;
    }

    .payments-page-heading-copy {
        flex: 1 1 420px;
        min-width: 0;
        max-width: 72ch;
    }

    .payments-page-heading-copy p {
        max-width: 70ch;
        margin-bottom: 0;
    }

    .payments-heading-actions {
        display: flex;
        align-items: flex-start;
        align-self: flex-start;
        flex: 0 0 auto;
        min-width: 0;
        max-width: 100%;
    }

    .payments-heading-actions .btn {
        white-space: nowrap;
    }

    .payments-alert-stack {
        display: grid;
        gap: 0.85rem;
    }

    .payments-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        align-items: stretch;
    }

    .payments-summary-card {
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100%;
        padding: 1.15rem 1.25rem;
        gap: 0.5rem;
    }

    .payments-summary-label {
        color: var(--wb-muted);
        font-size: 0.84rem;
        font-weight: 700;
        line-height: 1.35;
        min-height: 2.7em;
    }

    .payments-summary-value {
        margin: 0;
        color: var(--wb-text);
        font-size: clamp(1.45rem, 2vw, 1.9rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: 0;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .payments-summary-value-amount {
        font-size: clamp(1.25rem, 1.7vw, 1.65rem);
    }

    .payments-summary-badge {
        align-self: flex-start;
        margin-top: auto;
        white-space: nowrap;
    }

    .payments-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1.25rem;
        align-items: stretch;
    }

    .payments-panel {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-width: 0;
        height: 100%;
    }

    .payments-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
    }

    .payments-sticky-controls {
        display: grid;
        gap: 0.75rem;
        padding-bottom: 0.75rem;
        background: var(--wb-surface);
    }

    .payments-sticky-controls :is(.payments-filter-form, .record-filter-form) {
        margin-bottom: 0;
    }

    .payments-panel-head-copy {
        flex: 1 1 220px;
        min-width: 0;
    }

    .payments-panel-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .payments-panel-copy {
        margin: 0.25rem 0 0;
        color: var(--wb-muted);
        font-size: 0.88rem;
        line-height: 1.45;
    }

    .payments-panel-badge {
        flex: 0 0 auto;
        align-self: flex-start;
        white-space: nowrap;
    }

    .payments-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    .payments-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
    }

    .payments-open-table {
        min-width: 490px;
    }

    .payments-history-table {
        min-width: 780px;
    }

    .payments-records-table {
        min-width: 1120px;
    }

    .payments-accounts-table {
        min-width: 640px;
    }

    .payments-accounts-table > thead th:last-child {
        width: 9rem;
    }

    .payments-accounts-table > tbody > .payments-history-row > td {
        background: var(--vf-background);
    }

    .payments-history-row > td > .payments-table-wrap {
        contain: inline-size;
    }

    [data-payment-history-toggle][aria-expanded="true"] .bi-chevron-down {
        display: inline-block;
        transform: rotate(180deg);
    }

    .payments-table thead th {
        padding: 0.75rem 0.55rem;
        background: rgba(244, 248, 251, 0.98);
        color: var(--vf-navy);
        font-size: 0.69rem;
        line-height: 1.25;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        white-space: nowrap;
        border-bottom: 1px solid rgba(216, 237, 255, 0.9);
        vertical-align: middle;
    }

    .payments-review-table thead th {
        white-space: normal;
    }

    .payments-table tbody td {
        padding: 0.78rem 0.55rem;
        vertical-align: middle;
        border-bottom: 1px solid rgba(216, 237, 255, 0.55);
        background: rgba(255, 255, 255, 0.94);
        color: var(--wb-text);
        font-size: 0.875rem;
    }

    .payments-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .payments-table thead th:first-child,
    .payments-table tbody td:first-child {
        padding-left: 0.75rem;
    }

    .payments-table thead th:last-child,
    .payments-table tbody td:last-child {
        padding-right: 0.75rem;
    }

    .payments-table .payments-cell-nowrap,
    .payments-table .payments-currency,
    .payments-table .payments-date {
        white-space: nowrap;
    }

    .payments-table .payments-currency {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }

    .payments-cell-primary {
        font-weight: 700;
    }

    .payments-cell-meta {
        color: var(--wb-muted);
        font-size: 0.7rem;
        line-height: 1.35;
        white-space: nowrap;
    }

    .payments-date {
        font-size: 0.8rem;
    }

    .payments-open-table .payments-col-prn {
        min-width: 145px;
    }

    .payments-open-table .payments-col-consumer {
        min-width: 155px;
    }

    .payments-open-table .payments-col-balance {
        min-width: 100px;
    }

    .payments-open-table .payments-col-status {
        min-width: 85px;
    }

    .payments-history-table .payments-col-reference {
        min-width: 120px;
    }

    .payments-history-table .payments-col-consumer {
        min-width: 120px;
    }

    .payments-history-table .payments-col-period {
        min-width: 62px;
    }

    .payments-history-table .payments-col-amount {
        min-width: 78px;
    }

    .payments-history-table .payments-col-remaining {
        min-width: 90px;
    }

    .payments-history-table .payments-col-status {
        min-width: 74px;
    }

    .payments-history-table .payments-col-date {
        min-width: 76px;
    }

    .payments-history-table .payments-col-actions {
        min-width: 150px;
    }

    .payments-history-table thead th.payments-col-period,
    .payments-history-table thead th.payments-col-amount,
    .payments-history-table thead th.payments-col-remaining {
        white-space: normal;
    }

    .payments-history-table thead th.payments-col-actions,
    .payments-history-table tbody td.payments-col-actions {
        border-left: 1px solid rgba(216, 237, 255, 0.75);
    }

    .payments-row-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        gap: 0.35rem;
        white-space: nowrap;
    }

    .payments-row-actions .btn {
        min-width: 70px;
        padding-inline: 0.5rem;
        white-space: nowrap;
    }

    .payments-filter-form {
        display: grid;
        grid-template-columns: minmax(220px, 1.2fr) minmax(130px, 0.55fr) minmax(130px, 0.55fr) auto;
        align-items: center;
        gap: 0.65rem;
        width: 100%;
        margin-bottom: 1rem;
    }

    .payments-filter-form .form-control,
    .payments-filter-form .form-select {
        min-height: 2.55rem;
        height: 2.55rem;
        background: #fff !important;
        background-color: #fff !important;
        color: var(--wb-text) !important;
        border-color: rgba(216, 237, 255, 0.8) !important;
        font-size: 0.88rem;
    }

    .payments-filter-form .form-control::placeholder {
        color: var(--wb-muted) !important;
    }

    .payments-filter-form .form-select option {
        background: #fff;
        color: var(--wb-text);
    }

    .payments-filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .payments-filter-actions .btn {
        min-height: 2.55rem;
        white-space: nowrap;
    }

    }

    .payments-summary-note {
        margin: 0;
        color: var(--wb-muted);
        font-size: 0.86rem;
    }

    .payments-action-note {
        color: var(--wb-muted);
        font-size: 0.85rem;
    }

    .payments-pagination {
        display: flex;
        justify-content: flex-start;
        overflow-x: auto;
    }

    .payments-pagination nav {
        width: 100%;
    }

    .payments-empty-state {
        padding: 2.75rem 1rem;
        color: var(--wb-muted);
        text-align: center;
    }

    .payment-consumer-picker {
        position: relative;
    }

    .payment-consumer-results {
        position: absolute;
        top: calc(100% + 0.4rem);
        right: 0;
        left: 0;
        z-index: 1080;
        max-height: min(20rem, 55vh);
        overflow-y: auto;
        padding: 0.35rem;
        background: var(--wb-surface);
        border: 1px solid var(--wb-border);
        border-radius: 0.9rem;
        box-shadow: 0 1rem 2rem rgba(6, 43, 128, 0.15);
    }

    .payment-consumer-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
        width: 100%;
        padding: 0.7rem 0.75rem;
        border: 0;
        border-radius: 0.7rem;
        background: transparent;
        color: var(--wb-text);
        text-align: left;
    }

    .payment-consumer-option + .payment-consumer-option {
        margin-top: 0.15rem;
    }

    .payment-consumer-option:hover,
    .payment-consumer-option.is-active {
        background: color-mix(in srgb, var(--wb-primary) 8%, var(--wb-surface));
    }

    .payment-consumer-option:focus-visible {
        outline: 2px solid var(--wb-primary);
        outline-offset: -2px;
    }

    .payment-consumer-option-main,
    .payment-consumer-option-side {
        display: flex;
        flex-direction: column;
        gap: 0.22rem;
        min-width: 0;
    }

    .payment-consumer-option-main {
        flex: 1 1 auto;
    }

    .payment-consumer-option-side {
        flex: 0 0 auto;
        align-items: flex-end;
        text-align: right;
    }

    .payment-consumer-option-name {
        overflow: hidden;
        font-size: 0.86rem;
        font-weight: 800;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payment-consumer-option-meta {
        overflow: hidden;
        color: var(--wb-muted);
        font-size: 0.72rem;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payment-consumer-option-balance {
        color: var(--wb-text);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        white-space: nowrap;
    }

    .payment-consumer-option .consumer-service-status {
        gap: 0.18rem;
    }

    .payment-consumer-empty {
        padding: 1rem 0.85rem;
        color: var(--wb-muted);
        font-size: 0.84rem;
        text-align: center;
    }

    .payment-consumer-card {
        display: grid;
        gap: 0.9rem;
        padding: 1rem;
        background: color-mix(in srgb, var(--wb-primary) 4%, var(--wb-surface));
        border: 1px solid var(--wb-border);
        border-radius: 1rem;
    }

    .payment-consumer-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
    }

    .payment-consumer-card-identity {
        flex: 1 1 260px;
        min-width: 0;
    }

    .payment-consumer-card-name {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .payment-consumer-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 1rem;
        margin-top: 0.35rem;
        color: var(--wb-muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .payment-consumer-card-meta strong {
        color: var(--wb-text);
        font-weight: 700;
    }

    .payment-consumer-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr));
        gap: 0.75rem;
    }

    .payment-consumer-stat {
        min-width: 0;
        padding: 0.75rem 0.85rem;
        background: var(--wb-surface);
        border: 1px solid var(--wb-border);
        border-radius: 0.8rem;
    }

    .payment-consumer-stat-label {
        color: var(--wb-muted);
        font-size: 0.69rem;
        font-weight: 800;
        letter-spacing: 0.035em;
        line-height: 1.3;
        text-transform: uppercase;
    }

    .payment-consumer-stat-value {
        margin-top: 0.3rem;
        color: var(--wb-text);
        font-size: 1.2rem;
        font-variant-numeric: tabular-nums;
        font-weight: 800;
        line-height: 1.15;
    }

    .payment-consumer-stat-value.is-settled {
        color: var(--vf-green);
    }

    .payment-consumer-balance-note {
        margin: 0;
        color: var(--wb-muted);
        font-size: 0.78rem;
        line-height: 1.45;
    }

    .payment-amount-feedback {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.78rem;
    }

    .payment-warning-text {
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .payments-shell .alert-danger,
    #paymentModal .text-danger {
        font-size: 0.82rem;
        line-height: 1.4;
    }

    #paymentModal .alert-danger.payment-inactive-warning {
        padding: 0.8rem 1rem;
        background: #FEE2E2 !important;
        border: 1px solid #FCA5A5 !important;
        color: #991B1B !important;
        font-size: 0.82rem;
        line-height: 1.45;
    }

    #paymentModal .payment-inactive-warning strong,
    #paymentModal .payment-inactive-warning span {
        color: inherit;
    }

    #paymentModal .payment-inactive-warning .payment-warning-row {
        display: block;
    }

    .payment-amount-input {
        appearance: textfield;
        -moz-appearance: textfield;
    }

    .payment-amount-input::-webkit-outer-spin-button,
    .payment-amount-input::-webkit-inner-spin-button {
        margin: 0;
        -webkit-appearance: none;
    }

    @media (max-width: 575.98px) {
        .payment-consumer-option {
            align-items: flex-start;
            flex-direction: column;
        }

        .payment-consumer-option-side {
            align-items: flex-start;
            text-align: left;
        }

        .payment-consumer-stats {
            grid-template-columns: 1fr;
        }
    }

    #paymentReviewModal .modal-content {
        overflow: hidden;
    }

    #paymentReviewModal .modal-body {
        padding: 1.25rem 1.5rem;
        overscroll-behavior: contain;
    }

    .payment-review-section + .payment-review-section {
        border-top: 1px solid var(--wb-border);
        margin-top: 1.25rem;
        padding-top: 1.25rem;
    }

    .payment-review-heading {
        color: var(--wb-heading);
        font-size: .95rem;
        font-weight: 700;
        margin: 0 0 .9rem;
    }

    .payment-review-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem 1.25rem;
    }

    .payment-review-field {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .payment-review-label {
        color: #64748b;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        margin-bottom: .25rem;
        text-transform: uppercase;
    }

    .payment-review-value {
        color: var(--wb-text);
        font-size: .9rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .payment-review-notes {
        grid-column: 1 / -1;
    }

    .payment-proof-preview {
        align-items: center;
        background: #f8fbfd;
        border: 1px solid var(--wb-border);
        border-radius: .75rem;
        display: flex;
        justify-content: center;
        margin: 0 auto .75rem;
        max-width: 440px;
        overflow: hidden;
        width: 100%;
    }

    .payment-proof-preview img,
    .payment-proof-preview iframe {
        border: 0;
        display: block;
        width: 100%;
    }

    .payment-proof-preview img {
        height: 240px;
        object-fit: contain;
    }

    .payment-proof-preview iframe {
        height: 280px;
        background: #fff;
    }

    #paymentReviewModal .modal-footer {
        gap: .75rem;
    }

    @media (max-width: 575.98px) {
        #paymentReviewModal .modal-body {
            padding: 1rem;
        }

        .payment-review-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .9rem;
        }

        .payment-proof-preview img {
            height: 200px;
        }

        #paymentReviewModal .modal-footer,
        #paymentReviewModal .payment-review-actions {
            display: flex;
            flex-wrap: wrap;
        }

        #paymentReviewModal .modal-footer > button,
        #paymentReviewModal .payment-review-actions,
        #paymentReviewModal .payment-review-actions > *,
        #paymentReviewModal .payment-review-actions button {
            width: 100%;
        }
    }

    @media (max-width: 1849.98px) {
        .payments-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1199.98px) {
        .payments-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

    }

    @media (max-width: 991.98px) {
        .payments-page-heading {
            align-items: stretch;
        }

        .payments-page-heading-copy {
            flex: 0 1 auto;
        }

        .payments-heading-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .payments-filter-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .payments-page-heading {
            flex-direction: column;
            align-items: stretch;
        }

        .payments-heading-actions {
            width: 100%;
        }

        .payments-heading-actions .btn {
            width: 100%;
        }

        .payments-summary-label {
            min-height: 0;
        }

        .payments-panel-head {
            flex-direction: column;
            align-items: stretch;
        }

        .payments-panel-head-copy {
            flex: 0 1 auto;
        }

        .payments-filter-form {
            grid-template-columns: 1fr 1fr;
        }

        .payments-filter-search,
        .payments-filter-actions {
            grid-column: 1 / -1;
        }

        .payments-filter-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .payments-summary-grid {
            grid-template-columns: 1fr;
        }

        .payments-filter-form {
            grid-template-columns: 1fr;
        }


        .payments-filter-search,
        .payments-filter-actions {
            grid-column: auto;
        }

        .payments-filter-actions {
            justify-content: stretch;
        }

        .payments-filter-actions .btn {
            flex: 1 1 auto;
        }
    }
    @include('shared.record-filter-styles')

    @media (min-width: 1200px) {
        .payments-panel .record-filter-form {
            grid-template-columns: minmax(18rem, 1.75fr) repeat(3, minmax(7rem, 1fr)) auto;
        }
    }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading payments-page-heading">
                <div class="payments-page-heading-copy">
                    <h1>Payment Monitoring</h1>
                    <p>Manage over-the-counter and online payments, verify incoming submissions, and generate digital receipts for verified payments.</p>
                </div>
                <div class="payments-heading-actions">
                    @if ($recordType !== 'Review')
                    <button type="button" class="btn viaflow-add-payment" data-bs-toggle="modal" data-bs-target="#paymentModal" data-payment-type="{{ $recordType }}"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Payment</button>
                    @endif
                </div>
            </div>

            <div class="payments-shell" data-admin-records-root data-admin-page-scroll data-admin-freeze-section>
                @if (session('success') || $errors->any())
                    <div class="payments-alert-stack">
                        @if (session('success'))
                            <div class="alert alert-success d-flex align-items-start gap-2 mb-0" role="alert">
                                <i class="bi bi-check-circle mt-1"></i>
                                <div>{{ session('success') }}</div>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger d-flex align-items-start gap-2 mb-0" role="alert">
                                <i class="bi bi-exclamation-circle mt-1"></i>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="payments-summary-grid">
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Pending Verification</div>
                        <h2 class="payments-summary-value">{{ number_format($stats['pending']) }}</h2>
                        <span class="badge badge-soft-warning payments-summary-badge">Awaiting review</span>
                    </div>
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Verified Today</div>
                        <h2 class="payments-summary-value">{{ number_format($stats['verified_today']) }}</h2>
                        <span class="badge badge-soft-success payments-summary-badge">Cleared today</span>
                    </div>
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Online Submissions</div>
                        <h2 class="payments-summary-value">{{ number_format($stats['online_submissions']) }}</h2>
                        <span class="badge badge-soft-primary payments-summary-badge">Digital queue</span>
                    </div>
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Verified Amount Today</div>
                        <h2 class="payments-summary-value payments-summary-value-amount">P {{ number_format($stats['verified_amount_today'], 2) }}</h2>
                        <span class="badge badge-soft-info payments-summary-badge">Daily intake</span>
                    </div>
                </div>

                @include('admin.payments.records-table')
            </div>
        </div>
    </main>
</div>

@include('shared.payments.receipt-modal')

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Add Payment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('payments.store') }}" id="paymentCreateForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="payment_type" name="payment_type" value="{{ $recordType }}">
                    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="payment_type_selector">Payment Type</label>
                            <select class="form-select" id="payment_type_selector">
                                <option value="Water" @selected($recordType !== 'Loan')>Water Bill</option>
                                <option value="Loan" @selected($recordType === 'Loan')>Loan Payment</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="payment_consumer_search">Consumer Account</label>
                            <div class="payment-consumer-picker" id="payment_consumer_picker">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                                    <input
                                        type="text"
                                        class="form-control @if ($errors->has('consumer_id') || $errors->has('bill_id')) is-invalid @endif"
                                        id="payment_consumer_search"
                                        placeholder="Search by consumer name, meter number, or account number"
                                        autocomplete="off"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="payment_consumer_results"
                                        aria-haspopup="listbox"
                                    >
                                    <button class="btn btn-outline-secondary" type="button" id="payment_consumer_clear" aria-label="Clear selected consumer">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="payment_consumer_id" name="consumer_id" value="{{ old('consumer_id') }}">
                                <input type="hidden" id="bill_id" name="bill_id" value="{{ old('bill_id') }}">
                                <div class="payment-consumer-results d-none" id="payment_consumer_results" role="listbox" aria-label="Matching consumer accounts"></div>
                            </div>
                            <div class="form-text">Search all consumer accounts, including inactive and disconnected accounts. No account is excluded from balance settlement.</div>
                            @error('consumer_id')
                                <div class="text-danger small mt-1 payment-warning-text">{{ $message }}</div>
                            @enderror
                            @error('bill_id')
                                <div class="text-danger small mt-1 payment-warning-text">{{ $message }}</div>
                            @enderror
                            @error('loan_id')
                                <div class="text-danger small mt-1 payment-warning-text">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 d-none" id="payment_consumer_details">
                            <div class="payment-consumer-card">
                                <div class="payment-consumer-card-top">
                                    <div class="payment-consumer-card-identity">
                                        <h3 class="payment-consumer-card-name" id="payment_consumer_name">-</h3>
                                        <div class="payment-consumer-card-meta">
                                            <span>Account No. <strong id="payment_consumer_account">-</strong></span>
                                            <span>Meter No. <strong id="payment_consumer_meter">-</strong></span>
                                        </div>
                                    </div>
                                    <span class="consumer-service-status" id="payment_consumer_status_wrap">
                                        <span class="badge consumer-status-badge" id="payment_consumer_status_badge">
                                            <i class="bi bi-info-circle" id="payment_consumer_status_icon" aria-hidden="true"></i>
                                            <span id="payment_consumer_status_label">-</span>
                                        </span>
                                        <span class="consumer-status-sub" id="payment_consumer_status_sub"></span>
                                    </span>
                                </div>
                                <div class="payment-consumer-stats">
                                    <div class="payment-consumer-stat">
                                        <div class="payment-consumer-stat-label" id="payment_outstanding_label">Total Outstanding Balance</div>
                                        <div class="payment-consumer-stat-value">P <span id="payment_consumer_total_outstanding">0.00</span></div>
                                    </div>
                                    <div class="payment-consumer-stat">
                                        <div class="payment-consumer-stat-label" id="payment_balance_after_label">Balance After Payment</div>
                                        <div class="payment-consumer-stat-value" id="payment_consumer_balance_after_wrap">P <span id="payment_consumer_balance_after">0.00</span></div>
                                    </div>
                                    <div class="payment-consumer-stat d-none" id="payment_monthly_stat">
                                        <div class="payment-consumer-stat-label">Monthly Installment</div>
                                        <div class="payment-consumer-stat-value">P <span id="payment_monthly_installment">0.00</span></div>
                                    </div>
                                </div>
                                <p class="payment-consumer-balance-note" id="payment_balance_note"></p>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="payment_consumer_no_balance">
                            <div class="alert alert-warning mb-0">
                                <strong>No outstanding balance.</strong>
                                <span>There is nothing to settle for this consumer account.</span>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="payment_bill_selection_wrap">
                            <label class="form-label fw-semibold" for="payment_bill_selector">Unpaid water bill</label>
                            <select class="form-select" id="payment_bill_selector"><option value="">Select a bill</option></select>
                            <div class="form-text" id="payment_bill_summary">Select a bill to view its amount and remaining balance.</div>
                        </div>
                        <div class="col-12 d-none" id="payment_loan_selection_wrap">
                            <label class="form-label fw-semibold" for="payment_loan_selector">Select Loan</label>
                            <select class="form-select" id="payment_loan_selector" name="loan_id" disabled><option value="">Select a loan</option></select>
                            <div class="form-text" id="payment_loan_summary"></div>
                        </div>
                        <div class="col-12 d-none" id="disconnection_payment_notice">
                            <div class="alert alert-danger payment-inactive-warning mb-0">
                                <strong id="payment_full_requirement_title" class="payment-warning-row">Must Pay in Full</strong>
                                <span id="payment_full_requirement_message" class="payment-warning-row">This account is inactive or disconnected. Full settlement is required before reactivation.</span>
                                <span class="payment-warning-row">Total Outstanding Balance: P <span id="notice_total_outstanding">0.00</span></span>
                                <span class="payment-warning-row">Required Payment Amount: P <span id="notice_required_payment">0.00</span></span>
                                <span id="payment_full_requirement_rule" class="payment-warning-row">Full payment is required. Partial payment is not allowed. The balance after payment must reach P 0.00.</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="amount" id="payment_amount_label">Amount</label>
                            <input type="number" class="form-control payment-amount-input" id="amount" name="amount" step="0.01" min="0.01" inputmode="decimal" autocomplete="off" placeholder="0.00" value="{{ old('amount') }}" disabled required>
                            <div class="d-none d-flex flex-wrap gap-2 mt-2" id="payment_loan_amount_options">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="payment_loan_monthly">Monthly Payment</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="payment_loan_full">Pay in Full</button>
                            </div>
                            <div class="form-text" id="payment_amount_help">Select a consumer, then enter the amount received over the counter.</div>
                            <span class="payment-amount-feedback payment-warning-text text-danger d-none" id="payment_amount_feedback" role="alert"></span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="payment_date">Payment Date</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="payment_channel">Payment Channel</label>
                            <select class="form-select" id="payment_channel" name="payment_channel" required>
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel }}" @selected(old('payment_channel', $canVerifyPayments ? 'Counter' : 'Online') === $channel)>{{ $channel === 'Counter' ? 'Over the Counter' : $channel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="payment_method">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method }}" @selected(old('payment_method', $canVerifyPayments ? 'Cash' : 'GCash') === $method)>{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="gateway_reference">Reference / Transaction Number</label>
                            <input type="text" class="form-control" id="gateway_reference" name="gateway_reference" value="{{ old('gateway_reference') }}" placeholder="Required for online payments">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="sender_name">Sender Name</label>
                            <input type="text" class="form-control" id="sender_name" name="sender_name" value="{{ old('sender_name') }}" placeholder="Optional sender name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="sender_phone">Sender Phone Number</label>
                            <input type="text" class="form-control" id="sender_phone" name="sender_phone" value="{{ old('sender_phone') }}" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="notes">Notes</label>
                            <input type="text" class="form-control" id="notes" name="notes" value="{{ old('notes') }}" placeholder="{{ $canVerifyPayments ? 'Optional verification note' : 'Optional payment note' }}">
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                {{ $canVerifyPayments ? 'Admin-recorded payments are verified immediately and now issue a digital receipt.' : 'Consumer submissions are treated as online payments, stored as pending, and receive a digital receipt only after admin verification.' }}
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="paymentCreateForm" class="btn btn-primary" disabled>Submit Payment</button>
            </div>
        </div>
    </div>
</div>

@if ($canVerifyPayments)
    <div class="modal fade" id="paymentReviewModal" tabindex="-1" aria-labelledby="paymentReviewTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="paymentReviewTitle">Review Payment Submission</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <section class="payment-review-section" aria-labelledby="paymentReviewInformation">
                        <h3 class="payment-review-heading" id="paymentReviewInformation">Payment Information</h3>
                        <div class="payment-review-grid">
                            <div class="payment-review-field"><div class="payment-review-label">Reference</div><div class="payment-review-value" id="review_reference">-</div></div>
                            <div class="payment-review-field"><div class="payment-review-label">PRN</div><div class="payment-review-value" id="review_prn">-</div></div>
                            <div class="payment-review-field"><div class="payment-review-label">Consumer</div><div class="payment-review-value" id="review_consumer">-</div><div class="small text-secondary" id="review_account">-</div></div>
                            <div class="payment-review-field"><div class="payment-review-label">Payment Date</div><div class="payment-review-value" id="review_date">-</div></div>
                            @if ($viewType !== 'review')
                                <div class="payment-review-field"><div class="payment-review-label">Submission Date</div><div class="payment-review-value" id="review_submission_date"></div></div>
                                <div class="payment-review-field"><div class="payment-review-label">Billing Period</div><div class="payment-review-value" id="review_billing_period"></div></div>
                            @endif
                            <div class="payment-review-field"><div class="payment-review-label">Channel</div><div class="payment-review-value" id="review_channel">-</div></div>
                            <div class="payment-review-field"><div class="payment-review-label">Method</div><div class="payment-review-value" id="review_method">-</div></div>
                            <div class="payment-review-field"><div class="payment-review-label">Amount</div><div class="payment-review-value" id="review_amount">-</div></div>
                        </div>
                    </section>
                    <section class="payment-review-section" aria-labelledby="paymentReviewDetails">
                        <h3 class="payment-review-heading" id="paymentReviewDetails">Payment Details</h3>
                        <div class="payment-review-grid">
                            <div class="payment-review-field"><div class="payment-review-label">Gateway Reference</div><div class="payment-review-value" id="review_gateway">N/A</div></div>
                            <div class="payment-review-field"><div class="payment-review-label">Remaining Balance If Accepted</div><div class="payment-review-value" id="review_balance">-</div></div>
                            <div class="payment-review-field payment-review-notes"><div class="payment-review-label">Notes</div><div class="payment-review-value" id="review_notes">No notes provided.</div></div>
                            <div class="payment-review-field payment-review-notes" id="review_rejection_wrap" hidden><div class="payment-review-label">Rejection Reason</div><div class="payment-review-value" id="review_rejection_reason"></div></div>
                        </div>
                    </section>
                    <section class="payment-review-section" id="review_proof_wrap" aria-labelledby="paymentReviewProof">
                        <h3 class="payment-review-heading" id="paymentReviewProof">Attached Proof</h3>
                        <div class="payment-proof-preview d-none" id="review_proof_preview_wrap">
                            <img id="review_proof_image" alt="Payment proof preview" class="d-none">
                            <iframe id="review_proof_pdf" title="Payment proof preview" class="d-none"></iframe>
                        </div>
                        <div class="text-center"><a id="review_proof_link" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm">View Full Image</a></div>
                    </section>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger" id="openPaymentRejectModal" data-bs-toggle="modal" data-bs-target="#paymentRejectReasonModal">Reject</button>
                    <div class="d-flex gap-2 payment-review-actions">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                        <form method="POST" id="paymentVerifyForm">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="return_to_review" value="{{ $viewType === 'review' ? '1' : '0' }}">
                            <input type="hidden" name="payment_type_filter" value="{{ $paymentTypeFilter }}">
                            <button type="submit" class="btn btn-success">Verify and Issue Receipt</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="paymentRejectReasonModal" tabindex="-1" aria-labelledby="paymentRejectReasonTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="paymentRejectReasonTitle">Reject Payment</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form method="POST" id="paymentRejectForm">
                @csrf @method('PATCH')
                <input type="hidden" name="return_to_review" value="{{ $viewType === 'review' ? '1' : '0' }}">
                <input type="hidden" name="payment_type_filter" value="{{ $paymentTypeFilter }}">
                <div class="modal-body"><label class="form-label fw-semibold" for="payment_rejection_reason">Rejection Reason</label><textarea class="form-control" id="payment_rejection_reason" name="rejection_reason" maxlength="1000" required rows="3"></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Reject Payment</button></div>
            </form>
        </div></div>
    </div>
@endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paymentConsumers = @json($paymentConsumers);
            const oldConsumerId = @json((string) old('consumer_id', ''));
            const oldBillId = @json((string) old('bill_id', ''));
            const oldLoanId = @json((string) old('loan_id', ''));
            const oldPaymentType = @json(old('payment_type'));
            const oldAmount = @json(old('amount'));

            const amountInput = document.getElementById('amount');
            const paymentDateInput = document.getElementById('payment_date');
            const gatewayReferenceInput = document.getElementById('gateway_reference');
            const paymentChannelInput = document.getElementById('payment_channel');
            const paymentMethodInput = document.getElementById('payment_method');
            const billInput = document.getElementById('bill_id');
            const paymentTypeInput = document.getElementById('payment_type');
            const paymentTypeSelector = document.getElementById('payment_type_selector');
            const billSelector = document.getElementById('payment_bill_selector');
            const billSelectorWrap = document.getElementById('payment_bill_selection_wrap');
            const billSummary = document.getElementById('payment_bill_summary');
            const loanSelector = document.getElementById('payment_loan_selector');
            const loanSelectorWrap = document.getElementById('payment_loan_selection_wrap');
            const loanSummary = document.getElementById('payment_loan_summary');
            const outstandingLabel = document.getElementById('payment_outstanding_label');
            const balanceAfterLabel = document.getElementById('payment_balance_after_label');
            const balanceNote = document.getElementById('payment_balance_note');
            const monthlyStat = document.getElementById('payment_monthly_stat');
            const monthlyInstallment = document.getElementById('payment_monthly_installment');
            const amountLabel = document.getElementById('payment_amount_label');
            const loanAmountOptions = document.getElementById('payment_loan_amount_options');
            const monthlyPayment = document.getElementById('payment_loan_monthly');
            const fullLoanPayment = document.getElementById('payment_loan_full');
            const consumerPicker = document.getElementById('payment_consumer_picker');
            const consumerSearchInput = document.getElementById('payment_consumer_search');
            const consumerSearchResults = document.getElementById('payment_consumer_results');
            const consumerIdInput = document.getElementById('payment_consumer_id');
            const consumerClearButton = document.getElementById('payment_consumer_clear');
            const consumerDetails = document.getElementById('payment_consumer_details');
            const consumerName = document.getElementById('payment_consumer_name');
            const consumerAccount = document.getElementById('payment_consumer_account');
            const consumerMeter = document.getElementById('payment_consumer_meter');
            const consumerStatusWrap = document.getElementById('payment_consumer_status_wrap');
            const consumerStatusBadge = document.getElementById('payment_consumer_status_badge');
            const consumerStatusIcon = document.getElementById('payment_consumer_status_icon');
            const consumerStatusLabel = document.getElementById('payment_consumer_status_label');
            const consumerStatusSub = document.getElementById('payment_consumer_status_sub');
            const consumerTotalOutstanding = document.getElementById('payment_consumer_total_outstanding');
            const consumerBalanceAfter = document.getElementById('payment_consumer_balance_after');
            const consumerBalanceAfterWrap = document.getElementById('payment_consumer_balance_after_wrap');
            const consumerNoBalance = document.getElementById('payment_consumer_no_balance');
            const amountHelp = document.getElementById('payment_amount_help');
            const amountFeedback = document.getElementById('payment_amount_feedback');
            const disconnectionNotice = document.getElementById('disconnection_payment_notice');
            const noticeTitle = document.getElementById('payment_full_requirement_title');
            const noticeMessage = document.getElementById('payment_full_requirement_message');
            const noticeRule = document.getElementById('payment_full_requirement_rule');
            const noticeTotalOutstanding = document.getElementById('notice_total_outstanding');
            const noticeRequiredPayment = document.getElementById('notice_required_payment');
            const submitButton = document.querySelector('[form="paymentCreateForm"][type="submit"]');

            let selectedConsumer = null;
            let matchingConsumers = [];
            let activeResultIndex = -1;
            paymentTypeSelector.value = oldPaymentType === 'Loan' || oldPaymentType === 'Water' ? oldPaymentType : paymentTypeSelector.value;
            paymentTypeInput.value = paymentTypeSelector.value;

            const formatMoney = (value) => Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            function hasValue(input) {
                return Boolean(input && String(input.value || '').trim() !== '');
            }

            function isFullPaymentRequired() {
                if (!selectedConsumer || paymentTypeInput.value !== 'Water' || Number(selectedConsumer.total_outstanding || 0) <= 0) {
                    return false;
                }

                return selectedConsumer.status_key === 'inactive'
                    || Boolean(selectedConsumer.has_disconnection_notice);
            }

            function selectedLoan() {
                return selectedConsumer?.loans.find((loan) => String(loan.id) === loanSelector.value);
            }

            function syncPaymentSelection() {
                const isLoan = paymentTypeInput.value === 'Loan';
                const loan = isLoan ? selectedLoan() : null;
                const bill = isLoan ? null : selectedConsumer?.bills.find((item) => String(item.id) === billSelector.value);
                billInput.value = bill?.id || '';
                billSelectorWrap.classList.toggle('d-none', isLoan || !selectedConsumer);
                loanSelectorWrap.classList.toggle('d-none', !isLoan || !selectedConsumer);
                loanSelector.disabled = !isLoan || !selectedConsumer;
                amountInput.disabled = isLoan ? !loan?.payment_limit : !bill;
                billSummary.textContent = bill
                    ? `Reference: ${bill.prn} · Amount due: PHP ${formatMoney(bill.amount_due)} · Remaining balance: PHP ${formatMoney(bill.balance_due)}`
                    : 'No unpaid water bill selected.';
                loanSummary.textContent = loan && loan.payment_limit < loan.balance
                    ? (loan.payment_limit > 0 ? `Up to PHP ${formatMoney(loan.payment_limit)} can be recorded. Review the schedule before taking a larger payment.` : 'Review this loan schedule before recording a payment.')
                    : '';
            }

            function populateConsumerBills(consumer) {
                if (!billSelector) return;
                billSelector.innerHTML = '<option value="">Select a bill</option>';
                (consumer?.bills || []).forEach((bill) => {
                    const option = document.createElement('option');
                    option.value = String(bill.id);
                    option.textContent = `Water bill · ${bill.prn || 'N/A'} · Balance PHP ${formatMoney(bill.balance_due)}`;
                    billSelector.append(option);
                });
                loanSelector.replaceChildren(new Option('Select a loan', ''));
                (consumer?.loans || []).forEach((loan) => {
                    loanSelector.add(new Option(`Loan #${loan.id} · Balance PHP ${formatMoney(loan.balance)}`, loan.id));
                });
            }

            function updateAmountPreview() {
                const hasConsumer = Boolean(selectedConsumer);
                const isLoan = paymentTypeInput.value === 'Loan';
                const loan = isLoan ? selectedLoan() : null;
                const categoryBalance = isLoan ? selectedConsumer?.outstanding_loan_balance : selectedConsumer?.total_outstanding;
                const totalOutstanding = Number(loan?.balance ?? categoryBalance ?? 0);
                const paymentLimit = isLoan ? Number(loan?.payment_limit || 0) : totalOutstanding;
                const enteredAmount = Number(amountInput.value || 0);
                const hasEnteredAmount = hasValue(amountInput);
                const fullPaymentRequired = isFullPaymentRequired();
                const exceedsLimit = hasConsumer && hasEnteredAmount && enteredAmount > paymentLimit + 0.009;
                const belowFullRequirement = fullPaymentRequired
                    && hasEnteredAmount
                    && enteredAmount < totalOutstanding - 0.009;

                consumerTotalOutstanding.textContent = formatMoney(totalOutstanding);
                outstandingLabel.textContent = isLoan ? 'Outstanding Loan Balance' : 'Outstanding Water Balance';
                balanceAfterLabel.textContent = isLoan ? 'Remaining Loan Balance After Payment' : 'Balance After Payment';
                amountLabel.textContent = isLoan ? 'Amount to Pay' : 'Amount';
                balanceNote.textContent = isLoan
                    ? 'The loan balance includes future installments and reflects verified advance payments.'
                    : 'The water balance includes unpaid previous balances and current water charges.';
                monthlyStat.classList.toggle('d-none', !loan);
                monthlyInstallment.textContent = formatMoney(loan?.monthly_installment);
                loanAmountOptions.classList.toggle('d-none', !loan?.payment_limit);
                monthlyPayment.disabled = !loan?.next_installment_due || loan.next_installment_due > paymentLimit;
                fullLoanPayment.disabled = !loan || paymentLimit < loan.balance;
                consumerNoBalance.classList.toggle('d-none', !hasConsumer || Number(categoryBalance) > 0);

                if (amountInput) {
                    if (hasConsumer && paymentLimit > 0) {
                        amountInput.max = paymentLimit.toFixed(2);
                    } else {
                        amountInput.removeAttribute('max');
                    }
                }

                if (consumerBalanceAfter) {
                    const remaining = Math.max(0, totalOutstanding - Math.max(0, enteredAmount));
                    consumerBalanceAfter.textContent = formatMoney(remaining);
                    consumerBalanceAfterWrap?.classList.toggle(
                        'is-settled',
                        hasConsumer && remaining <= 0 && !exceedsLimit,
                    );
                }

                let feedbackMessage = '';

                if (exceedsLimit) {
                    feedbackMessage = `Payment amount cannot exceed the payable balance of P ${formatMoney(paymentLimit)}.`;
                } else if (belowFullRequirement) {
                    feedbackMessage = `Full payment of P ${formatMoney(totalOutstanding)} is required to settle this account.`;
                }

                if (amountFeedback) {
                    amountFeedback.textContent = feedbackMessage;
                    amountFeedback.classList.toggle('d-none', feedbackMessage === '');
                }

                amountInput?.classList.toggle('is-invalid', feedbackMessage !== '');

                if (amountHelp) {
                    if (!hasConsumer) {
                        amountHelp.textContent = 'Select a consumer, then enter the amount received over the counter.';
                    } else if (isLoan) {
                        amountHelp.textContent = loan?.payment_limit
                            ? 'Enter the amount received. Partial and advance payments cover the earliest unpaid installments first.'
                            : 'Select a payable loan.';
                    } else if (!billInput.value) {
                        amountHelp.textContent = 'Select an unpaid water bill.';
                    } else {
                        amountHelp.textContent = 'Enter the amount received. Payments are applied to the oldest unpaid bill first (FIFO).';
                    }
                }
            }

            function updateFullPaymentNotice() {
                const totalOutstanding = Number(selectedConsumer?.total_outstanding || 0);
                const inactiveAccount = selectedConsumer?.status_key === 'inactive';
                const noticeActive = Boolean(selectedConsumer?.has_disconnection_notice);
                const active = Boolean(selectedConsumer)
                    && paymentTypeInput.value === 'Water'
                    && totalOutstanding > 0
                    && (inactiveAccount || noticeActive);

                disconnectionNotice?.classList.toggle('d-none', !active);

                if (noticeTitle) {
                    noticeTitle.textContent = inactiveAccount ? 'Must Pay in Full' : 'Disconnection Notice';
                }

                if (noticeMessage) {
                    noticeMessage.textContent = inactiveAccount
                        ? 'This account is inactive or disconnected. Full settlement is required before reactivation.'
                        : 'A disconnection notice is active on this account.';
                }

                if (noticeRule) {
                    noticeRule.textContent = 'Full payment is required. Partial payment is not allowed. The balance after payment must reach P 0.00.';
                }

                if (noticeTotalOutstanding) {
                    noticeTotalOutstanding.textContent = formatMoney(totalOutstanding);
                }

                if (noticeRequiredPayment) {
                    noticeRequiredPayment.textContent = formatMoney(totalOutstanding);
                }
            }

            function requiredPaymentFieldsAreFilled() {
                const isLoan = paymentTypeInput.value === 'Loan';
                const loan = isLoan ? selectedLoan() : null;
                if (!selectedConsumer || (isLoan ? !loan?.payment_limit : !billInput.value)) {
                    return false;
                }

                const totalOutstanding = isLoan ? Number(loan.payment_limit) : Number(selectedConsumer.total_outstanding || 0);
                const enteredAmount = Number(amountInput.value || 0);
                const fullPaymentRequired = isFullPaymentRequired();

                if (!hasValue(amountInput)
                    || enteredAmount <= 0
                    || enteredAmount > totalOutstanding + 0.009
                    || (fullPaymentRequired && enteredAmount < totalOutstanding - 0.009)
                    || !hasValue(paymentDateInput)
                    || !hasValue(paymentChannelInput)
                    || !hasValue(paymentMethodInput)) {
                    return false;
                }

                if (paymentChannelInput?.value === 'Online' && !hasValue(gatewayReferenceInput)) {
                    return false;
                }

                return true;
            }

            function updateSubmitState() {
                updateFullPaymentNotice();
                updateAmountPreview();

                if (submitButton) {
                    submitButton.disabled = !requiredPaymentFieldsAreFilled();
                }
            }

            function createConsumerStatusNode(consumer) {
                const wrapper = document.createElement('span');
                wrapper.className = 'consumer-service-status';

                if (consumer.status_description) {
                    wrapper.title = consumer.status_description;
                }

                const badge = document.createElement('span');
                badge.className = 'badge consumer-status-badge ' + (consumer.status_badge_class || 'badge-soft-primary');

                const icon = document.createElement('i');
                icon.className = 'bi ' + (consumer.status_icon || 'bi-info-circle');
                icon.setAttribute('aria-hidden', 'true');

                const label = document.createElement('span');
                label.textContent = consumer.status_label || '';

                badge.append(icon, label);

                const subLabel = document.createElement('span');
                subLabel.className = 'consumer-status-sub ' + (consumer.status_sub_class || '');
                subLabel.textContent = consumer.status_sub_label || '';

                wrapper.append(badge, subLabel);

                return wrapper;
            }

            function hideConsumerResults() {
                consumerSearchResults?.classList.add('d-none');
                consumerSearchInput?.setAttribute('aria-expanded', 'false');
                consumerSearchInput?.removeAttribute('aria-activedescendant');
                activeResultIndex = -1;
            }

            function setActiveConsumerResult(index) {
                if (!matchingConsumers.length) {
                    activeResultIndex = -1;
                    return;
                }

                activeResultIndex = (index + matchingConsumers.length) % matchingConsumers.length;

                [...consumerSearchResults.querySelectorAll('.payment-consumer-option')].forEach((option, optionIndex) => {
                    const active = optionIndex === activeResultIndex;
                    option.classList.toggle('is-active', active);
                    option.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                const activeOption = consumerSearchResults.querySelectorAll('.payment-consumer-option')[activeResultIndex];

                if (activeOption) {
                    consumerSearchInput.setAttribute('aria-activedescendant', activeOption.id);
                    activeOption.scrollIntoView({ block: 'nearest' });
                }
            }

            function renderConsumerResults() {
                if (!consumerSearchResults) {
                    return;
                }

                const query = String(consumerSearchInput?.value || '').trim().toLowerCase();
                const matches = paymentConsumers.filter((consumer) => {
                    if (query === '') {
                        return true;
                    }

                    return [consumer.name, consumer.account_number, consumer.meter_number]
                        .filter(Boolean)
                        .some((value) => String(value).toLowerCase().includes(query));
                });

                matchingConsumers = matches.slice(0, query === '' ? 10 : 40);
                activeResultIndex = -1;
                consumerSearchResults.innerHTML = '';

                if (!matchingConsumers.length) {
                    const empty = document.createElement('div');
                    empty.className = 'payment-consumer-empty';
                    empty.textContent = paymentConsumers.length
                        ? 'No consumer account matches your search.'
                        : 'No consumer accounts are available.';
                    consumerSearchResults.append(empty);
                    consumerSearchResults.classList.remove('d-none');
                    consumerSearchInput?.setAttribute('aria-expanded', 'true');
                    return;
                }

                matchingConsumers.forEach((consumer, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'payment-consumer-option';
                    option.id = `payment-consumer-option-${consumer.id}`;
                    option.dataset.consumerId = String(consumer.id);
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.title = consumer.status_description || '';

                    const main = document.createElement('span');
                    main.className = 'payment-consumer-option-main';

                    const name = document.createElement('span');
                    name.className = 'payment-consumer-option-name';
                    name.textContent = consumer.name || 'Unnamed consumer';

                    const meta = document.createElement('span');
                    meta.className = 'payment-consumer-option-meta';
                    meta.textContent = `Account ${consumer.account_number || 'N/A'} · Meter ${consumer.meter_number || 'N/A'}`;

                    main.append(name, meta);

                    const side = document.createElement('span');
                    side.className = 'payment-consumer-option-side';

                    const balance = document.createElement('span');
                    balance.className = 'payment-consumer-option-balance';
                    const isLoan = paymentTypeInput.value === 'Loan';
                    balance.textContent = `${isLoan ? 'Loan' : 'Water'} P ${formatMoney(isLoan ? consumer.outstanding_loan_balance : consumer.total_outstanding)}`;

                    side.append(createConsumerStatusNode(consumer), balance);

                    option.append(main, side);
                    option.addEventListener('mouseenter', () => setActiveConsumerResult(index));

                    consumerSearchResults.append(option);
                });

                consumerSearchResults.classList.remove('d-none');
                consumerSearchInput?.setAttribute('aria-expanded', 'true');
            }

            function renderSelectedConsumer(consumer) {
                consumerName.textContent = consumer.name || 'Unnamed consumer';
                consumerAccount.textContent = consumer.account_number || 'Not assigned';
                consumerMeter.textContent = consumer.meter_number || 'Not assigned';

                consumerStatusBadge.className = 'badge consumer-status-badge ' + (consumer.status_badge_class || 'badge-soft-primary');
                consumerStatusIcon.className = 'bi ' + (consumer.status_icon || 'bi-info-circle');
                consumerStatusLabel.textContent = consumer.status_label || '-';
                consumerStatusSub.className = 'consumer-status-sub ' + (consumer.status_sub_class || '');
                consumerStatusSub.textContent = consumer.status_sub_label || '';
                consumerStatusWrap.title = consumer.status_description || '';

                consumerDetails.classList.remove('d-none');
            }

            function selectConsumer(consumer, options = {}) {
                if (!consumer) {
                    return;
                }

                selectedConsumer = consumer;
                consumerIdInput.value = String(consumer.id);
                consumerSearchInput.value = consumer.name || '';
                consumerSearchInput.classList.remove('is-invalid');
                hideConsumerResults();
                renderSelectedConsumer(consumer);
                populateConsumerBills(consumer);
                billSelector.value = options.billId || consumer.bills[0]?.id || '';
                loanSelector.value = options.loanId || consumer.loans.find((loan) => loan.payment_limit > 0)?.id || consumer.loans[0]?.id || '';
                syncPaymentSelection();

                if (!amountInput.disabled
                    && options.amount !== undefined
                    && options.amount !== null
                    && String(options.amount).trim() !== '') {
                    amountInput.value = options.amount;
                } else {
                    amountInput.value = '';
                }

                updateSubmitState();
            }

            function resetConsumerSelection(options = {}) {
                const clearSearch = options.clearSearch !== false;

                selectedConsumer = null;
                consumerIdInput.value = '';
                billInput.value = '';
                if (billSelector) billSelector.value = '';
                loanSelector.value = '';
                syncPaymentSelection();
                consumerDetails.classList.add('d-none');
                consumerNoBalance.classList.add('d-none');
                consumerSearchInput.classList.remove('is-invalid');
                hideConsumerResults();

                amountInput.value = '';
                amountInput.disabled = true;
                amountInput.classList.remove('is-invalid');
                amountInput.removeAttribute('max');
                amountFeedback.textContent = '';
                amountFeedback.classList.add('d-none');

                if (clearSearch) {
                    consumerSearchInput.value = '';
                }

                updateSubmitState();
            }

            document.getElementById('paymentModal')?.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                const type = trigger?.dataset.paymentType;
                if (type === 'Water' || type === 'Loan') {
                    if (paymentTypeInput.value !== type) amountInput.value = '';
                    paymentTypeInput.value = type;
                    paymentTypeSelector.value = type;
                }
                const consumerId = trigger?.dataset.paymentConsumerId;
                const consumer = consumerId
                    ? paymentConsumers.find((item) => String(item.id) === String(consumerId))
                    : null;

                if (consumer) {
                    selectConsumer(consumer, {billId: trigger.dataset.paymentBillId});
                } else {
                    syncPaymentSelection();
                    updateSubmitState();
                }
            });

            paymentTypeSelector?.addEventListener('change', function () {
                paymentTypeInput.value = this.value;
                amountInput.value = '';
                syncPaymentSelection();
                updateSubmitState();
            });
            [billSelector, loanSelector].forEach((selector) => {
                selector.addEventListener('change', () => {
                    amountInput.value = '';
                    syncPaymentSelection();
                    updateSubmitState();
                });
            });
            monthlyPayment.addEventListener('click', () => {
                amountInput.value = selectedLoan().next_installment_due.toFixed(2);
                updateSubmitState();
            });
            fullLoanPayment.addEventListener('click', () => {
                amountInput.value = selectedLoan().balance.toFixed(2);
                updateSubmitState();
            });

            consumerSearchInput?.addEventListener('focus', function () {
                if (selectedConsumer) {
                    this.select();
                    return;
                }

                renderConsumerResults();
            });

            consumerSearchInput?.addEventListener('input', function () {
                if (selectedConsumer) {
                    resetConsumerSelection({ clearSearch: false });
                }

                renderConsumerResults();
            });

            consumerSearchInput?.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();

                    if (consumerSearchResults.classList.contains('d-none')) {
                        renderConsumerResults();
                    }

                    setActiveConsumerResult(activeResultIndex + 1);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();

                    if (consumerSearchResults.classList.contains('d-none')) {
                        renderConsumerResults();
                    }

                    setActiveConsumerResult(activeResultIndex - 1);
                    return;
                }

                if (event.key === 'Enter' && !consumerSearchResults.classList.contains('d-none') && matchingConsumers.length) {
                    event.preventDefault();
                    selectConsumer(matchingConsumers[activeResultIndex >= 0 ? activeResultIndex : 0]);
                    return;
                }

                if (event.key === 'Escape') {
                    hideConsumerResults();
                }
            });

            consumerSearchResults?.addEventListener('click', function (event) {
                const option = event.target.closest('[data-consumer-id]');

                if (!option) {
                    return;
                }

                const consumer = paymentConsumers.find((item) => String(item.id) === option.dataset.consumerId);

                if (consumer) {
                    selectConsumer(consumer);
                }
            });

            consumerClearButton?.addEventListener('click', function () {
                resetConsumerSelection();
                consumerSearchInput.focus();
                renderConsumerResults();
            });

            document.addEventListener('click', function (event) {
                if (consumerPicker && !consumerPicker.contains(event.target)) {
                    hideConsumerResults();
                }
            });

            [amountInput, paymentDateInput, paymentChannelInput, paymentMethodInput, gatewayReferenceInput].forEach((input) => {
                input?.addEventListener('input', updateSubmitState);
                input?.addEventListener('change', updateSubmitState);
            });

            let initialConsumer = null;

            if (oldConsumerId !== '') {
                initialConsumer = paymentConsumers.find((consumer) => String(consumer.id) === oldConsumerId) || null;
            }

            if (!initialConsumer && oldBillId !== '') {
                initialConsumer = paymentConsumers.find((consumer) => consumer.bills.some((bill) => String(bill.id) === oldBillId)) || null;
            }

            if (!initialConsumer && oldLoanId !== '') {
                initialConsumer = paymentConsumers.find((consumer) => consumer.loans.some((loan) => String(loan.id) === oldLoanId)) || null;
            }

            if (initialConsumer) {
                selectConsumer(initialConsumer, {
                    amount: oldAmount,
                    billId: oldBillId,
                    loanId: oldLoanId,
                });
            } else {
                resetConsumerSelection();
            }

            updateSubmitState();
        });
    </script>

    @if ($canVerifyPayments)
        <script>
            document.querySelectorAll('[data-payment-history-toggle]').forEach((button) => {
                const history = document.getElementById(button.getAttribute('aria-controls'));
                button.addEventListener('click', () => {
                    const expanded = history.hidden;
                    history.hidden = !expanded;
                    button.setAttribute('aria-expanded', String(expanded));
                });
            });

            const paymentReviewModal = document.getElementById('paymentReviewModal');

            if (paymentReviewModal) {
                paymentReviewModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const paymentId = button.getAttribute('data-payment-id');
                    const proofUrl = button.getAttribute('data-payment-proof');
                    const rejected = button.getAttribute('data-payment-status') === 'Rejected';
                    document.getElementById('openPaymentRejectModal').hidden = rejected;
                    document.getElementById('paymentVerifyForm').hidden = rejected;
                    document.getElementById('review_rejection_wrap').hidden = !rejected;
                    document.getElementById('review_rejection_reason').textContent = button.getAttribute('data-payment-rejection-reason') || 'Not recorded';
                    document.getElementById('payment_rejection_reason').value = '';

                    document.getElementById('review_reference').textContent = button.getAttribute('data-payment-reference') || '-';
                    document.getElementById('review_prn').textContent = button.getAttribute('data-payment-prn') || '-';
                    document.getElementById('review_consumer').textContent = button.getAttribute('data-payment-consumer') || '-';
                    document.getElementById('review_account').textContent = button.getAttribute('data-payment-account') || '-';
                    document.getElementById('review_date').textContent = button.getAttribute('data-payment-date') || '-';
                    const submissionDate = document.getElementById('review_submission_date');
                    if (submissionDate) {
                        submissionDate.textContent = button.dataset.submissionDate || 'Not recorded';
                        document.getElementById('review_billing_period').textContent = button.dataset.billingPeriods || 'Not recorded';
                    }
                    document.getElementById('review_channel').textContent = button.getAttribute('data-payment-channel') || '-';
                    document.getElementById('review_method').textContent = button.getAttribute('data-payment-method') || '-';
                    document.getElementById('review_amount').textContent = `P ${button.getAttribute('data-payment-amount') || '0.00'}`;
                    document.getElementById('review_gateway').textContent = button.getAttribute('data-payment-gateway') || 'N/A';
                    document.getElementById('review_balance').textContent = `P ${button.getAttribute('data-payment-balance') || '0.00'}`;
                    document.getElementById('review_notes').textContent = button.getAttribute('data-payment-notes') || 'No notes provided.';

                    document.getElementById('paymentVerifyForm').action = `/payments/${paymentId}/verify`;
                    document.getElementById('paymentRejectForm').action = `/payments/${paymentId}/reject`;

                    const proofWrap = document.getElementById('review_proof_wrap');
                    const proofLink = document.getElementById('review_proof_link');
                    const proofPreviewWrap = document.getElementById('review_proof_preview_wrap');
                    const proofImage = document.getElementById('review_proof_image');
                    const proofPdf = document.getElementById('review_proof_pdf');

                    if (proofUrl) {
                        proofWrap.classList.remove('d-none');
                        proofLink.href = proofUrl;

                        const normalizedProofUrl = proofUrl.toLowerCase();
                        const isPdf = normalizedProofUrl.includes('.pdf');
                        const isImage = ['.jpg', '.jpeg', '.png', '.gif', '.webp'].some((extension) => normalizedProofUrl.includes(extension));
                        proofLink.textContent = isPdf ? 'View Full PDF' : (isImage ? 'View Full Image' : 'Open Proof Attachment');

                        proofPreviewWrap.classList.remove('d-none');
                        proofImage.classList.add('d-none');
                        proofPdf.classList.add('d-none');
                        proofImage.removeAttribute('src');
                        proofPdf.removeAttribute('src');

                        if (isPdf) {
                            proofPdf.src = proofUrl;
                            proofPdf.classList.remove('d-none');
                        } else if (isImage) {
                            proofImage.src = proofUrl;
                            proofImage.classList.remove('d-none');
                        } else {
                            proofPreviewWrap.classList.add('d-none');
                        }
                    } else {
                        proofWrap.classList.add('d-none');
                        proofLink.href = '#';
                        proofPreviewWrap.classList.add('d-none');
                        proofImage.classList.add('d-none');
                        proofPdf.classList.add('d-none');
                        proofImage.removeAttribute('src');
                        proofPdf.removeAttribute('src');
                    }
                });
            }
        </script>
    @endif

    @if ($errors->any())
        <script>
            const paymentModalElement = document.getElementById('paymentModal');
            if (paymentModalElement) {
                const paymentModal = new bootstrap.Modal(paymentModalElement);
                paymentModal.show();
            }
        </script>
    @endif
@endpush
