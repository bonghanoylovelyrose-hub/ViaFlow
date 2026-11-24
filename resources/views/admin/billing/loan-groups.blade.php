@push('styles')
    <style>
        #admin-loan-billing-table > thead th {
            white-space: normal;
        }

        #admin-loan-billing-table [data-loan-schedule-toggle] {
            min-width: 11.5rem;
        }

        .loan-monthly-billings {
            --loan-schedule-border: #dce5f0;
            --loan-schedule-text: #1e293b;
        }

        .loan-monthly-billings > td,
        .loan-monthly-billings .table {
            background-color: var(--wb-surface);
        }

        .loan-monthly-billings > td > .table-responsive {
            contain: inline-size;
            border: 1px solid var(--loan-schedule-border);
            border-radius: 0.65rem;
        }

        .loan-monthly-billings > td,
        .loan-monthly-billings .table > :is(thead, tbody) > tr > * {
            border-color: var(--loan-schedule-border) !important;
        }

        .loan-monthly-billings .table > :is(thead, tbody) > tr > * {
            padding: 0.5rem 0.75rem;
            background-color: var(--wb-surface);
            color: var(--loan-schedule-text) !important;
        }

        .loan-monthly-billings .table > thead > tr > th {
            background-color: #f1f5f9 !important;
        }

        .loan-monthly-billings .table > tbody > tr:nth-child(even) > td {
            background-color: #f8fafc;
        }

        .loan-monthly-billings .table > tbody > tr:last-child > td {
            border-bottom-width: 0;
        }

        .loan-monthly-billings .table .small.text-secondary {
            color: #475569 !important;
        }

        .loan-monthly-billings .text-bg-success {
            --loan-badge-bg: var(--bs-success-bg-subtle, #d1e7dd);
            --loan-badge-text: var(--bs-success-text-emphasis, #0a3622);
        }

        .loan-monthly-billings .text-bg-warning {
            --loan-badge-bg: var(--bs-warning-bg-subtle, #fff3cd);
            --loan-badge-text: var(--bs-warning-text-emphasis, #664d03);
        }

        .loan-monthly-billings .text-bg-danger {
            --loan-badge-bg: var(--bs-danger-bg-subtle, #f8d7da);
            --loan-badge-text: var(--bs-danger-text-emphasis, #58151c);
        }

        .loan-monthly-billings .badge:is(.text-bg-success, .text-bg-warning, .text-bg-danger) {
            background-color: var(--loan-badge-bg) !important;
            color: var(--loan-badge-text) !important;
        }
    </style>
@endpush

@if ($hasLoanBillingFilters)
    <p class="small text-secondary">Loans appear when an installment matches the filters. Expand a loan to see its full schedule.</p>
@endif

<div class="table-responsive">
    <table class="table align-middle" id="admin-loan-billing-table">
        <thead>
            <tr>
                <th scope="col">Consumer</th>
                <th scope="col">Account Number</th>
                <th scope="col" class="text-nowrap">Meter Number</th>
                <th scope="col">Loan Reference</th>
                <th scope="col">Installments</th>
                <th scope="col">Total Scheduled Amount</th>
                <th scope="col">Amount Paid</th>
                <th scope="col">Remaining Balance</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($loanBills as $installments)
                @php
                    $firstInstallment = $installments->first();
                    $loanId = $firstInstallment->loan_id;
                    $consumer = $firstInstallment->loan?->consumer;
                    $needsReview = in_array($loanId, $loanScheduleSynchronization['review_loan_ids'] ?? [], true);
                @endphp
                <tr data-loan-id="{{ $loanId }}">
                    <td>
                        <div class="fw-semibold">{{ $consumer?->full_name ?? 'Unknown consumer' }}</div>
                    </td>
                    <td>{{ $consumer?->account_number ?? '—' }}</td>
                    <td class="text-nowrap">{{ filled($consumer?->meter_number) ? $consumer->meter_number : '—' }}</td>
                    <th scope="row">Loan #{{ $loanId }}</th>
                    <td>{{ $installments->count() }}</td>
                    <td>₱{{ number_format($installments->sum('required_amount'), 2) }}</td>
                    <td>{{ $needsReview ? 'Under review' : '₱'.number_format($installments->sum('amount_paid'), 2) }}</td>
                    <td class="fw-semibold">{{ $needsReview ? 'Under review' : '₱'.number_format($installments->sum('remaining_balance'), 2) }}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-loan-schedule-toggle aria-expanded="false" aria-controls="loan-monthly-billings-{{ $loanId }}">
                            <span data-loan-schedule-label>Show Monthly Billings</span><span class="visually-hidden"> for Loan #{{ $loanId }}</span>
                        </button>
                    </td>
                </tr>
                <tr id="loan-monthly-billings-{{ $loanId }}" class="loan-monthly-billings" hidden>
                    <td colspan="9" class="p-3">
                        @include('shared.billing.loan-installments', ['installments' => $installments, 'scheduleLoanId' => $loanId])
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@push('scripts')
    <script>
        document.getElementById('admin-loan-billing-table').addEventListener('click', function (event) {
            const button = event.target.closest('[data-loan-schedule-toggle]');

            if (!button) return;

            const schedule = document.getElementById(button.getAttribute('aria-controls'));
            const expanded = schedule.hidden;
            schedule.hidden = !expanded;
            button.setAttribute('aria-expanded', String(expanded));
            button.querySelector('[data-loan-schedule-label]').textContent = expanded ? 'Hide Monthly Billings' : 'Show Monthly Billings';
        });
    </script>
@endpush
