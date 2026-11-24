@php
    $money = static fn ($value) => $value === null ? 'Not recorded' : '₱'.number_format((float) $value, 2);
    $approvedAmount = $loan->applicationStatusCategory() === 'Approved' ? $money($loan->amount) : 'Not approved';
@endphp
<div class="loan-breakdown" data-breakdown-loan-id="{{ $loan->id }}">
    <section class="loan-financial-card loan-applicant-card mb-3" aria-labelledby="loan-applicant-heading">
        <h3 id="loan-applicant-heading">Applicant Information</h3>
        <dl class="loan-financial-rows">
            <div><dt>Applicant Name</dt><dd>{{ $loan->consumer?->full_name ?? 'Not recorded' }}</dd></div>
            <div><dt>Account Number</dt><dd>{{ $loan->consumer?->account_number ?? 'Not recorded' }}</dd></div>
            <div><dt>Application Date</dt><dd>{{ $loan->applied_at?->format('M d, Y') ?? 'Not recorded' }}</dd></div>
        </dl>
    </section>
    <div class="loan-financial-cards">
        <section class="loan-financial-card" aria-labelledby="loan-summary-heading">
            <h3 id="loan-summary-heading">Loan Summary</h3>
            <dl class="loan-financial-rows">
                <div><dt>Total Installation Cost</dt><dd>{{ $money($loan->installation_cost_total) }}</dd></div>
                <div><dt>Approved Loan Amount</dt><dd>{{ $approvedAmount }}</dd></div>
                <div><dt>Interest Rate</dt><dd>Not recorded</dd></div>
                <div><dt>Repayment Term</dt><dd>{{ $loan->term_months }} months</dd></div>
                <div class="loan-financial-highlight"><dt>Monthly Installment</dt><dd>{{ $money($loan->monthly_installment) }}</dd></div>
                <div><dt>Total Amount Paid</dt><dd>{{ $money($loan->total_repaid) }}</dd></div>
                <div class="loan-financial-highlight"><dt>Remaining Balance</dt><dd>{{ $money($loan->balance_remaining) }}</dd></div>
            </dl>
        </section>
        <section class="loan-financial-card" aria-labelledby="loan-installation-heading">
            <h3 id="loan-installation-heading">Installation Cost Breakdown</h3>
            <dl class="loan-financial-rows">
                <div><dt>Water Meter</dt><dd>{{ $money($loan->material_meter_cost) }}</dd></div>
                <div><dt>Hose</dt><dd>{{ $money($loan->material_hose_cost) }}</dd></div>
                <div><dt>Other Materials</dt><dd>{{ $money($loan->material_other_cost) }}</dd></div>
                <div class="loan-financial-total"><dt>Total Installation Cost</dt><dd>{{ $money($loan->installation_cost_total) }}</dd></div>
            </dl>
        </section>
    </div>

    <h3>Monthly Installment Schedule</h3>
    @if ($breakdown['schedule_state'] === 'pending')
        <p class="text-secondary">Installment schedule available after approval.</p>
    @elseif ($breakdown['schedule_state'] === 'rejected')
        <p class="text-secondary">No installment schedule is available for a rejected application.</p>
    @elseif ($breakdown['schedule_state'] === 'missing_approval')
        <p class="text-secondary">The approval date is not recorded, so installment months cannot be shown.</p>
    @elseif ($breakdown['schedule_state'] === 'invalid_term')
        <p class="text-secondary">The repayment term is not available.</p>
    @else
        @if (! $breakdown['has_plan'])
            <p class="small text-secondary">A complete saved schedule is not available. Recorded installment amounts are shown where available.</p>
        @else
            <p class="small text-secondary">Months start after approval; due dates are shown when recorded.</p>
        @endif
        @if ($breakdown['record_mismatch'])
            <p class="alert alert-warning small">Some recorded installment amounts differ from the saved schedule. Recorded amounts are shown; no adjustment has been made.</p>
        @endif
        @if ($breakdown['period_mismatch'])
            <p class="alert alert-warning small">Some recorded billing periods differ from the approval-based months shown here. Recorded due dates are unchanged.</p>
        @endif
        <table class="table loan-breakdown-table">
            <thead><tr><th>Installment Month</th><th class="loan-breakdown-date">Due Date</th><th class="loan-breakdown-number">Scheduled Amount</th><th class="loan-breakdown-number">Paid / Credit</th><th class="loan-breakdown-number">Remaining</th></tr></thead>
            <tbody>
                @foreach ($breakdown['rows'] as $row)
                    <tr>
                        <td data-label="Installment Month">{{ $row['month'] }}</td>
                        <td data-label="Due Date" class="loan-breakdown-date">{{ $row['due_date']?->format('M d, Y') ?? 'To Be Scheduled' }}</td>
                        <td data-label="Amount Due" class="loan-breakdown-number">{{ $money($row['amount_due']) }}</td>
                        <td data-label="Paid / Credit" class="loan-breakdown-number">{{ $row['amount_paid'] === null ? 'Not recorded' : $money($row['amount_paid']) }}</td>
                        <td data-label="Remaining" class="loan-breakdown-number">{{ $row['remaining'] === null ? $money($row['amount_due']) : $money($row['remaining']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr class="loan-breakdown-total"><th colspan="2" scope="row">Scheduled Total</th><td class="loan-breakdown-number">{{ $money($breakdown['total_due']) }}</td><td colspan="2"></td></tr></tfoot>
        </table>
    @endif

</div>
