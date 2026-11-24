<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Agreement — Loan #{{ $loan->id }}</title>
    <style>
        @page { margin: 32px 38px; }
        body { color: #172839; font: 11px/1.42 'DejaVu Sans', sans-serif; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        h2 { font-size: 15px; margin: 16px 0 6px; }
        .issuer { color: #365a72; margin: 0 0 4px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #d8e2e9; padding: 4px 7px; text-align: left; }
        th, .schedule thead td { background: #edf4f8; font-weight: bold; }
        .details th { width: 25%; }
        .amount { text-align: right; }
        .terms p { margin: 0 0 8px; white-space: pre-wrap; overflow-wrap: break-word; }
        .schedule tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @php($money = static fn ($amount) => '₱'.number_format((float) $amount, 2))
    <p class="issuer">{{ config('app.name') }}</p>
    <h1>Finalized Loan Agreement</h1>

    <table class="details">
        <tbody>
            <tr><th>Consumer full name</th><td>{{ $loan->consumer->full_name }}</td><th>Account number</th><td>{{ $loan->consumer->account_number }}</td></tr>
            <tr><th>Loan reference</th><td>Loan #{{ $loan->id }}</td><th>Agreement version</th><td>{{ $loan->terms_version }}</td></tr>
            <tr><th>Agreement accepted</th><td>{{ $loan->terms_agreed_at->format('M j, Y g:i A') }}</td><th>Agreement issued</th><td>{{ $issuedAt->format('M j, Y g:i A') }}</td></tr>
            <tr><th>Approved loan amount</th><td>{{ $money($loan->amount) }}</td><th>Interest rate</th><td>0%</td></tr>
            <tr><th>Repayment term</th><td>{{ $loan->term_months }} months</td><th>Monthly installment</th><td>{{ $money($loan->monthly_installment) }}</td></tr>
            <tr><th>Total amount payable</th><td colspan="3">{{ $money($total) }}</td></tr>
        </tbody>
    </table>

    <h2>Accepted agreement</h2>
    <div class="terms">
        @foreach (explode("\n\n", $loan->terms_text) as $paragraph)
            <p>@if (\App\Support\LoanTerms::isEmphasized($paragraph))<strong>{{ $paragraph }}</strong>@else{{ $paragraph }}@endif</p>
        @endforeach
    </div>

    <h2>Finalized installment schedule</h2>
    <table class="schedule">
        <thead><tr><th scope="col">Installment</th><th scope="col">Due date</th><th scope="col" class="amount">Amount</th></tr></thead>
        <tbody>
            @foreach ($installments as $installment)
                <tr>
                    <td>{{ $installment->installment_number }}</td>
                    <td>{{ $installment->due_date->format('M j, Y') }}</td>
                    <td class="amount">{{ $money($installment->required_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
