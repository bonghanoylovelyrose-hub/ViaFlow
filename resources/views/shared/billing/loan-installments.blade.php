<div class="table-responsive">
    <table @class(['table align-middle', 'mb-0' => $isAdmin])>
        @if ($isAdmin)
            <caption class="visually-hidden">Monthly billings for Loan #{{ $scheduleLoanId }}</caption>
        @endif
        <thead><tr>
            <th scope="col">{{ $isAdmin ? 'PRN' : 'Loan Reference' }}</th><th scope="col">Installment</th><th scope="col">Billing Month</th><th scope="col">Payment Deadline</th><th scope="col">Monthly Amount Due</th><th scope="col">Amount Paid</th><th scope="col">Remaining Balance</th><th scope="col">Payment Status</th><th scope="col">Actions</th>
        </tr></thead>
        <tbody>
            @forelse ($installments as $installment)
                @php($needsReview = in_array($installment->loan_id, $loanScheduleSynchronization['review_loan_ids'] ?? [], true))
                <tr>
                    <td>
                        @if ($isAdmin)
                            {{ $installment->bill?->prn ?? '—' }}
                        @else
                            <div class="fw-semibold">Loan #{{ $installment->loan_id }}</div><div class="small text-secondary">{{ $installment->bill?->prn }}</div>
                        @endif
                    </td>
                    <td>#{{ $installment->installment_number }}</td>
                    <td>{{ $installment->billing_period?->format('M Y') ?? 'Not recorded' }}</td>
                    <td>{{ $installment->due_date?->format('M d, Y') ?? 'Not set — contact the administrator' }}@if (! $installment->bill_id && $installment->due_date)<div class="small text-secondary">Scheduled</div>@endif</td>
                    <td>₱{{ number_format((float) $installment->required_amount, 2) }}</td>
                    <td>{{ $needsReview ? 'Under review' : '₱'.number_format((float) $installment->amount_paid, 2) }}</td>
                    <td class="fw-semibold">{{ $needsReview ? 'Under review' : '₱'.number_format((float) $installment->remaining_balance, 2) }}</td>
                    <td>@if ($needsReview)<span class="badge text-bg-warning">Under review</span>@else<span class="badge {{ $installment->paymentStatusLabel() === 'Paid' ? 'text-bg-success' : ($installment->status === 'Overdue' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $installment->paymentStatusLabel() }}</span>@if (! $installment->bill_id && $installment->remaining_balance > 0)<div class="small text-secondary">Scheduled</div>@endif @endif</td>
                    <td>@if ($needsReview)<span class="text-secondary small">Awaiting review</span>@elseif ($installment->remaining_balance <= 0)<span class="text-secondary small">Settled</span>@elseif (! $isAdmin && in_array($installment->loan?->status, \App\Models\Loan::PAYMENT_STATUSES, true))<a class="btn btn-sm btn-outline-primary" href="{{ route('payments.index', ['view' => 'loan-payments']) }}">Pay</a>@elseif ($isAdmin && in_array($installment->loan?->status, ['Approved', 'Active', 'Ongoing'], true) && $installment->loan?->has_loan_bill)<a class="btn btn-sm btn-outline-primary" href="{{ route('payments.index', ['view' => 'loan-payments']) }}">Record Payment</a>@else<span class="text-secondary small">Awaiting billing setup</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-5 text-secondary">
                    @if ($summary['bill_count'] === 0)
                        Loan balance: <strong>₱0.00</strong><div class="mt-1">No loan bills generated yet.</div>
                    @else
                        No bills match these filters.
                    @endif
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>
