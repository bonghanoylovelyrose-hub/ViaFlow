@php
    $logoSrc = 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('images/via-vida-logo.svg')));
    $money = fn (float|int|string|null $amount): string => 'P '.number_format((float) $amount, 2);
    $receiptNumber = $payment->receipt_number ?: $payment->reference_number;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ViaFlow Payment Receipt — {{ $receiptNumber }}</title>
    <style>
        @page {
            margin: 8mm;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9pt;
            line-height: 11pt;
            overflow-wrap: anywhere;
        }

        p, h1, h2 {
            margin: 0;
        }

        .receipt-parties,
        .receipt-table,
        .receipt-summary {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .receipt-header {
            position: relative;
            min-height: 9mm;
            padding-left: 12mm;
            page-break-inside: avoid;
        }

        .receipt-logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 9mm;
            height: 9mm;
        }

        .receipt-brand {
            font-size: 12pt;
            line-height: 14pt;
        }

        .receipt-subtitle,
        .receipt-footer {
            color: #444444;
            font-size: 8pt;
        }

        .receipt-number {
            margin: 1mm 0 1.5mm;
            padding-bottom: 1mm;
            border-bottom: 0.75pt solid #888888;
        }

        .receipt-number strong {
            font-size: 10.5pt;
        }

        .receipt-label {
            margin-bottom: 0.3mm;
            color: #444444;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .receipt-parties td,
        .receipt-summary td {
            width: 50%;
            padding: 0;
            vertical-align: top;
        }

        .receipt-parties td:first-child,
        .receipt-summary td:first-child {
            padding-right: 2mm;
        }

        .receipt-parties td:last-child,
        .receipt-summary td:last-child {
            padding-left: 2mm;
            border-left: 0.5pt solid #d5d5d5;
        }

        .receipt-party-name {
            font-size: 9.5pt;
            font-weight: bold;
        }

        .receipt-status {
            display: inline-block;
            margin-left: 0.75mm;
            padding: 0.25mm 1.2mm;
            border: 0.5pt solid #888888;
            border-radius: 0.75mm;
            font-size: 8.5pt;
            font-weight: bold;
        }

        .receipt-field strong {
            color: #444444;
            font-size: 8.5pt;
        }

        .receipt-section {
            margin-top: 1.5mm;
        }

        .receipt-section-title {
            page-break-after: avoid;
        }

        .receipt-table th,
        .receipt-table td {
            padding: 0.45mm 1.1mm;
            border-bottom: 0.5pt solid #d5d5d5;
            text-align: left;
            vertical-align: top;
        }

        .receipt-table th {
            color: #444444;
            font-size: 8pt;
            text-transform: uppercase;
        }

        .receipt-details th:first-child {
            width: 42%;
        }

        .receipt-allocations th:first-child {
            width: 54%;
        }

        .receipt-allocations th:nth-child(2) {
            width: 23%;
        }

        .receipt-details tbody td:first-child {
            color: #444444;
            font-size: 8.5pt;
        }

        .receipt-table .receipt-value {
            text-align: right;
        }

        .receipt-table .receipt-amount td {
            border-top: 0.75pt solid #888888;
            border-bottom: 0.75pt solid #888888;
            font-weight: bold;
            vertical-align: middle;
        }

        .receipt-table .receipt-amount td:last-child {
            font-size: 12pt;
            line-height: 14pt;
        }

        .receipt-summary {
            margin-top: 0.5mm;
        }

        .receipt-notes {
            margin-top: 1.5mm;
            padding-top: 1mm;
            border-top: 0.5pt solid #d5d5d5;
            font-size: 8.5pt;
        }

        .receipt-notes p {
            orphans: 2;
            widows: 2;
            page-break-after: avoid;
        }

        .receipt-footer {
            margin-top: 1.5mm;
            padding-top: 1mm;
            border-top: 0.75pt solid #888888;
            page-break-before: avoid;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <header class="receipt-header">
        <img src="{{ $logoSrc }}" alt="Via-Vida logo" class="receipt-logo">
        <h1 class="receipt-brand">Via-Vida Waterworks Association</h1>
        <div class="receipt-subtitle">Official consumer payment receipt</div>
    </header>

    <div class="receipt-number">
        <span class="receipt-label">Receipt</span>
        <strong>{{ $receiptNumber }}</strong>
    </div>

    <table class="receipt-parties">
        <tr>
            <td>
                <div class="receipt-label">Consumer Name</div>
                <div class="receipt-party-name">{{ $payment->consumer->full_name }}</div>
                <div class="receipt-field"><strong>Account Number:</strong> {{ $payment->consumer->account_number }}</div>
                <div class="receipt-field"><strong>Meter Number:</strong> {{ $payment->consumer->meter_number }}</div>
                <div class="receipt-field">{{ $payment->consumer->service_address }}</div>
            </td>
            <td>
                <div><span class="receipt-label">Verification Status</span><span class="receipt-status">{{ $payment->status }}</span></div>
                <div class="receipt-field"><strong>Verified At:</strong> {{ $payment->verified_at?->format('F d, Y h:i A') ?? 'N/A' }}</div>
                <div class="receipt-field"><strong>Verified By:</strong> {{ $payment->verifier?->name ?? 'System' }}</div>
            </td>
        </tr>
    </table>

    <div class="receipt-section">
        <table class="receipt-table receipt-details">
            <thead>
                <tr><th>Payment Details</th><th class="receipt-value">Detail</th></tr>
            </thead>
            <tbody>
                <tr><td>Payment Category</td><td class="receipt-value">{{ $payment->payment_type }} Payment</td></tr>
                @if ($payment->payment_type === 'Loan')
                    <tr><td>{{ $payment->bill ? 'Loan Installment' : 'Loan' }}</td><td class="receipt-value">#{{ $payment->bill?->loanInstallment?->installment_number ?? $payment->loan_id ?? $payment->bill?->loan_id ?? 'N/A' }}</td></tr>
                @endif
                <tr><td>Payment Reference Number (PRN)</td><td class="receipt-value">{{ $payment->bill?->prn ?? 'N/A' }}</td></tr>
                <tr><td>System Payment Reference</td><td class="receipt-value">{{ $payment->reference_number }}</td></tr>
                <tr><td>Transaction/Reference Number</td><td class="receipt-value">{{ $payment->gateway_reference ?: 'N/A' }}</td></tr>
                <tr><td>Payment Date</td><td class="receipt-value">{{ $payment->payment_date->format('F d, Y') }}</td></tr>
                <tr><td>Payment Method</td><td class="receipt-value">{{ $payment->payment_method }}</td></tr>
                <tr><td>Channel</td><td class="receipt-value">{{ $payment->payment_channel ?? 'Counter' }}</td></tr>
                <tr class="receipt-amount"><td>Amount Paid</td><td class="receipt-value">{{ $money($payment->amount) }}</td></tr>
                <tr><td>Previous Balance</td><td class="receipt-value">{{ $money($previousBalance) }}</td></tr>
                <tr><td>Remaining Balance After Payment</td><td class="receipt-value">{{ $money($remainingBalance) }}</td></tr>
            </tbody>
        </table>
    </div>

    @if ($loanAllocations->isNotEmpty())
        <div class="receipt-section">
            <h2 class="receipt-label receipt-section-title">Installments covered and advance credits</h2>
            <table class="receipt-table receipt-allocations">
                <thead>
                    <tr><th>Month</th><th class="receipt-value">Applied</th><th class="receipt-value">Remaining</th></tr>
                </thead>
                <tbody>
                    @foreach ($loanAllocations as $allocation)
                        <tr>
                            <td>
                                {{ $allocation->installment?->billing_period?->format('F Y') ?? 'Not recorded' }} (Installment #{{ $allocation->installment?->installment_number }})
                                @if ($allocation->installment?->billing_period?->gt($payment->payment_date->copy()->startOfMonth())) Advance credit @endif
                            </td>
                            <td class="receipt-value">{{ $money($allocation->amount) }}</td>
                            <td class="receipt-value">{{ $money($allocation->installment_balance_after) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="receipt-section">
        <h2 class="receipt-label receipt-section-title">Consumer Bill</h2>
        @if ($payment->bill)
            <table class="receipt-summary">
                <tr><td>
                    <div class="receipt-field"><strong>Billing Period:</strong> {{ $payment->bill->billing_period?->format('F Y') ?? 'N/A' }}</div>
                    <div class="receipt-field"><strong>Due Date:</strong> {{ $payment->bill->due_date?->format('F d, Y') ?? 'N/A' }}</div>
                </td><td>
                    <div class="receipt-field"><strong>Total Bill:</strong> {{ $money($payment->bill->amount) }}</div>
                    <div class="receipt-field"><strong>Total Verified Paid:</strong> {{ $money($payment->bill->verified_paid_amount) }}</div>
                </td></tr>
            </table>
        @else
            <div>Loan #{{ $payment->loan_id }} repayment schedule</div>
        @endif
    </div>

    <div class="receipt-notes">
        <div class="receipt-label receipt-section-title">Notes</div>
        <p>{{ $payment->notes ?: 'No additional notes were recorded for this payment.' }}</p>
    </div>

    <footer class="receipt-footer">
        <div>This is a system-generated official receipt. No signature is required.</div>
        <div>Generated {{ now()->format('F d, Y h:i A') }}</div>
    </footer>
</body>
</html>
