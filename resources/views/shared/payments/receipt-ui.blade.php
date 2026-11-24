@php
    $receiptEmbedded = request()->boolean('embed');
    $receiptReference = $payment->gateway_reference ?: null;
    $receiptNumber = $payment->receipt_number ?: $payment->reference_number;
    $showAdminPaymentDates = $showAdminPaymentDates ?? false;
@endphp

@push('styles')
<style>
    .receipt-page { --receipt-width: 30rem; }
    .receipt-page .content-wrapper > .receipt-screen-only,
    .receipt-page .receipt-document {
        width: 100%;
        max-width: var(--receipt-width);
        margin-inline: auto;
        overflow-wrap: anywhere;
    }

    .receipt-page .page-heading { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
    .receipt-page .page-heading h1 { font-size: 1.125rem; margin-bottom: 0.2rem; }
    .receipt-page .page-heading p { font-size: 0.8125rem; margin-bottom: 0.125rem; }
    .receipt-page .page-heading > div { min-width: 0; }
    .receipt-page .page-heading > .d-flex { flex-wrap: wrap; }
    .receipt-preview-embedded .main-content { margin: 0 !important; width: 100%; min-width: 0; }
    .receipt-preview-embedded .content-wrapper { padding: 1rem; }
    @media (max-width: 575.98px) { .receipt-preview-embedded .content-wrapper { padding: 0.75rem; } }

    .receipt-document {
        --receipt-ink: #1e293b;
        --receipt-muted: #475569;
        --receipt-rule: #dce5ee;
        --receipt-emphasis-rule: #aab8c8;
        --receipt-heading-background: #f8fafc;
        --receipt-total-background: #f1f5f9;
        padding: clamp(0.875rem, 3vw, 1.25rem);
        color: var(--receipt-ink);
        font-size: 0.9rem;
        line-height: 1.35;
    }

    .receipt-document .receipt-header {
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--receipt-emphasis-rule);
    }

    .receipt-document .receipt-brand { gap: 0.5rem; }
    .receipt-document .receipt-logo { width: 2.5rem; height: 2.5rem; flex: 0 0 auto; }
    .receipt-document .receipt-brand-name { font-size: 1rem; line-height: 1.2; }
    .receipt-document .receipt-brand-subtitle { color: var(--receipt-muted); font-size: 0.8125rem; }
    .receipt-document .receipt-number { margin-top: 0.5rem; }
    .receipt-document .receipt-number-value { font-size: 1rem; }

    .receipt-document :is(.receipt-header, .receipt-parties, .receipt-table-wrap) { margin-bottom: 0.625rem; }
    .receipt-document :is(.receipt-parties, .receipt-summary) > * + * {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--receipt-rule);
    }

    .receipt-document :is(.receipt-label, .receipt-number-label) {
        margin: 0 0 0.15rem;
        color: var(--receipt-muted);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.035em;
        text-transform: uppercase;
    }

    .receipt-document .receipt-payer-name { font-size: 1rem; }
    .receipt-document .receipt-meta {
        display: grid;
        grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
        align-items: baseline;
        gap: 0.15rem 0.625rem;
        margin: 0.2rem 0 0;
    }

    .receipt-document .receipt-meta dd { margin: 0; }
    .receipt-document .receipt-meta dt,
    .receipt-document .receipt-details-table tbody td:first-child {
        color: var(--receipt-muted);
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .receipt-document .receipt-address { margin-top: 0.2rem; }
    .receipt-document .receipt-notes p { margin: 0; }
    .receipt-document .receipt-notes a { margin-top: 0.5rem; }
    .receipt-document .receipt-table { width: 100%; min-width: 0; margin: 0; table-layout: fixed; }
    .receipt-document .receipt-details-table th:first-child { width: 52%; }
    .receipt-document .receipt-allocations-table th:first-child { width: 44%; }
    .receipt-document .receipt-table :is(th, td) {
        padding: 0.3rem 0.45rem;
        border-color: var(--receipt-rule) !important;
        color: var(--receipt-ink);
        white-space: normal;
        vertical-align: top;
    }

    .receipt-document .receipt-table thead th {
        background: var(--receipt-heading-background) !important;
        color: var(--receipt-muted) !important;
        font-size: 0.75rem;
        letter-spacing: 0.035em;
    }

    .receipt-document .receipt-table .text-end { font-variant-numeric: tabular-nums; }
    .receipt-document .receipt-table .receipt-amount-row td {
        vertical-align: middle;
        border-block: 1px solid var(--receipt-emphasis-rule) !important;
        background: var(--receipt-total-background);
    }

    .receipt-document .receipt-amount-row td:last-child { font-size: 1.125rem; font-weight: 800; }
</style>
@endpush
@include('shared.payments.receipt-print-styles')

<div class="app-shell d-lg-flex receipt-page {{ $receiptEmbedded ? 'receipt-preview-embedded' : '' }}">
    @unless ($receiptEmbedded) @include('layouts.sidebar', ['activePage' => $activePage]) @endunless
    <main class="main-content flex-grow-1">
        @unless ($receiptEmbedded) @include('layouts.navbar') @endunless
        <div class="content-wrapper">
            <div class="page-heading receipt-screen-only">
                <div>
                    <h1>Digital Receipt</h1>
                    <p>Official receipt number: <strong>{{ $receiptNumber }}</strong></p>
                    @if ($receiptReference)
                        <p class="mb-0">Reference or transaction number: <strong>{{ $receiptReference }}</strong></p>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2">
                    @unless ($receiptEmbedded) <a href="{{ route('payments.index') }}" class="small text-decoration-underline">Back to Payments</a> @endunless
                    <a href="{{ route('payments.receipt.pdf', $payment) }}" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Download Receipt
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2 receipt-screen-only" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            <article class="panel-card receipt-document" id="digital-receipt" aria-labelledby="receipt-heading" data-admin-natural-page-scroll>
                <header class="receipt-header">
                    <div class="d-flex align-items-center receipt-brand">
                        <img src="{{ asset('images/via-vida-logo.svg') }}" alt="Via-Vida logo" class="receipt-logo">
                        <div>
                            <div class="fw-bold receipt-brand-name" id="receipt-heading">Via-Vida Waterworks Association</div>
                            <div class="receipt-brand-subtitle">Official consumer payment receipt</div>
                        </div>
                    </div>
                    <div class="receipt-number">
                        <div class="receipt-number-label">Receipt</div>
                        <div class="fw-semibold receipt-number-value">{{ $receiptNumber }}</div>
                    </div>
                </header>

                <div class="receipt-parties">
                    <div>
                        <div class="receipt-label">Consumer Name</div>
                        <div class="fw-bold receipt-payer-name">{{ $payment->consumer->full_name }}</div>
                        <dl class="receipt-meta">
                            <dt>Account Number:</dt><dd>{{ $payment->consumer->account_number }}</dd>
                            <dt>Meter Number:</dt><dd>{{ $payment->consumer->meter_number }}</dd>
                        </dl>
                        <div class="receipt-address">{{ $payment->consumer->service_address }}</div>
                    </div>
                    <div>
                        <div class="receipt-label">Verification</div>
                        <dl class="receipt-meta">
                            <dt>Verification Status:</dt><dd><x-status-badge :status="$payment->status" /></dd>
                            @if ($showAdminPaymentDates)
                                <dt>Verified Date</dt><dd>{{ \App\Services\AdminPaymentMonitoring::formatTimestamp($payment->verified_at) }}</dd>
                            @else
                                <dt>Verified At:</dt><dd>{{ $payment->verified_at?->format('F d, Y h:i A') ?? 'N/A' }}</dd>
                            @endif
                            <dt>Verified By:</dt><dd>{{ $payment->verifier?->name ?? 'System' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="table-responsive receipt-table-wrap">
                    <table class="table align-middle receipt-details-table receipt-table">
                        <thead><tr><th>Description</th><th class="text-end">Detail</th></tr></thead>
                        <tbody>
                            <tr><td>Payment Category</td><td class="text-end">{{ $payment->payment_type }} Payment</td></tr>
                            @if ($payment->payment_type === 'Loan')
                                <tr><td>{{ $payment->bill ? 'Loan Installment' : 'Loan' }}</td><td class="text-end">#{{ $payment->bill?->loanInstallment?->installment_number ?? $payment->loan_id ?? 'N/A' }}</td></tr>
                            @endif
                            <tr><td>Payment Reference Number (PRN)</td><td class="text-end">{{ $payment->bill?->prn ?? 'N/A' }}</td></tr>
                            <tr><td>System Payment Reference</td><td class="text-end">{{ $payment->reference_number }}</td></tr>
                            <tr><td>Transaction/Reference Number</td><td class="text-end">{{ $receiptReference ?: 'N/A' }}</td></tr>
                            <tr><td>Payment Date</td><td class="text-end">{{ $payment->payment_date->format($showAdminPaymentDates ? 'M d, Y' : 'F d, Y') }}</td></tr>
                            <tr><td>Payment Method</td><td class="text-end">{{ $payment->payment_method }}</td></tr>
                            <tr><td>Channel</td><td class="text-end">{{ $payment->payment_channel ?? 'Counter' }}</td></tr>
                            <tr class="receipt-amount-row"><td>Amount Paid</td><td class="text-end">P {{ number_format((float) $payment->amount, 2) }}</td></tr>
                            <tr><td>Previous Balance</td><td class="text-end">P {{ number_format($previousBalance, 2) }}</td></tr>
                            <tr><td>Remaining Balance After Payment</td><td class="text-end">P {{ number_format($remainingBalance, 2) }}</td></tr>
                        </tbody>
                    </table>
                </div>

                @if ($loanAllocations->isNotEmpty())
                    <h2 class="receipt-label">Installments covered and advance credits</h2>
                    <div class="table-responsive receipt-table-wrap"><table class="table receipt-table receipt-allocations-table">
                        <thead><tr><th>{{ $showAdminPaymentDates ? 'Billing Period' : 'Month' }}</th><th class="text-end">Applied</th><th class="text-end">Remaining</th></tr></thead>
                        <tbody>@foreach ($loanAllocations as $allocation)
                            <tr><td>{{ $allocation->installment?->billing_period?->format($showAdminPaymentDates ? 'M Y' : 'F Y') ?? 'Not recorded' }} (Installment #{{ $allocation->installment?->installment_number }}) @if ($allocation->installment?->billing_period?->gt($payment->payment_date->copy()->startOfMonth()))<span class="small">Advance credit</span>@endif</td><td class="text-end">P {{ number_format((float) $allocation->amount, 2) }}</td><td class="text-end">P {{ number_format((float) $allocation->installment_balance_after, 2) }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
                <div class="receipt-summary">
                    <div>
                        <div class="receipt-label">Consumer Bill</div>
                        @if ($payment->bill)
                            <dl class="receipt-meta">
                                <dt>Billing Period:</dt><dd>{{ $payment->bill->billing_period?->format($showAdminPaymentDates ? 'M Y' : 'F Y') ?? 'N/A' }}</dd>
                                <dt>Due Date:</dt><dd>{{ $payment->bill->due_date?->format($showAdminPaymentDates ? 'M d, Y' : 'F d, Y') ?? 'N/A' }}</dd>
                                <dt>Total Bill:</dt><dd>P {{ number_format((float) $payment->bill->amount, 2) }}</dd>
                                <dt>Total Verified Paid:</dt><dd>P {{ number_format((float) $payment->bill->verified_paid_amount, 2) }}</dd>
                            </dl>
                        @else
                            <div>Loan #{{ $payment->loan_id }} repayment schedule</div>
                        @endif
                    </div>
                    <div class="receipt-notes">
                        <div class="receipt-label">Notes</div>
                        <p>{{ $payment->notes ?: 'No additional notes were recorded for this payment.' }}</p>
                        @if ($payment->proof_path)
                            <a href="{{ route('uploads.serve', ['path' => $payment->proof_path]) }}" target="_blank" class="d-inline-block small text-decoration-underline receipt-screen-only">Open Attached Proof</a>
                        @endif
                    </div>
                </div>

                <footer class="receipt-footer receipt-print-only">
                    <span>This is a system-generated official receipt. No signature is required.</span>
                    <span>Generated {{ now()->format('F d, Y h:i A') }}</span>
                </footer>
            </article>
        </div>
    </main>
</div>
