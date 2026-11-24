@php
    $showAccountColumns = $recordType === 'Review';
@endphp

<div class="payments-table-wrap" role="region" aria-label="Bills and payments records" tabindex="0">
    <table @class(['table align-middle payments-table payments-records-table', 'payments-review-table' => $showAccountColumns])>
        <thead>
            <tr>
                <th scope="col">Reference / PRN</th>
                @if (! $groupedHistory)
                    <th scope="col">Consumer</th>
                    @if ($showAccountColumns)
                        <th scope="col">Account Number</th>
                        <th scope="col">Meter Number</th>
                    @endif
                @elseif ($recordType === 'Loan')
                    <th scope="col">Loan Reference</th>
                @endif
                <th scope="col">{{ $groupedHistory ? 'Billing Period' : 'Payment Date' }}</th>
                <th scope="col">{{ $showBills && $recordType === 'Loan' ? 'Amount Due' : 'Amount' }}</th>
                @if ($showBills && $recordType === 'Loan')<th scope="col">Amount Paid</th>@endif
                <th scope="col">Remaining Balance</th>
                <th scope="col">Payment Status</th>
                <th scope="col">Bill Status</th>
                <th scope="col">Channel</th>
                <th scope="col">{{ $groupedHistory ? 'Payment Date' : 'Date' }}</th>
                <th class="text-end" scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                @php
                    $record = $entry['record'];
                @endphp
                @if ($entry['kind'] === 'bill')
                    <tr data-record-kind="bill" data-record-id="{{ $record->id }}">
                        <td>
                            <div class="payments-cell-primary payments-cell-nowrap">{{ $record->prn }}</div>
                            <div class="payments-cell-meta">{{ $recordType }} bill</div>
                        </td>
                        <td>
                            <div class="payments-cell-primary">{{ $record->consumer?->full_name ?? 'Unknown consumer' }}</div>
                            @unless ($showAccountColumns)
                                <div class="payments-cell-meta">{{ $record->consumer?->account_number }}</div>
                            @endunless
                        </td>
                        @if ($showAccountColumns)
                            <td class="payments-cell-nowrap">{{ $record->consumer?->account_number ?? '—' }}</td>
                            <td class="payments-cell-nowrap">{{ $record->consumer?->meter_number ?? '—' }}</td>
                        @endif
                        <td class="payments-date text-secondary">
                            @if ($recordType === 'Loan')
                                #{{ $record->loanInstallment?->installment_number ?? '—' }} · Due {{ $record->loanInstallment?->due_date?->format('M d, Y') ?? $record->due_date?->format('M d, Y') ?? 'N/A' }}
                                <div class="payments-cell-meta">{{ $record->billing_period?->format('M Y') }}</div>
                            @else
                                {{ $record->billing_period?->format('M Y') ?? 'N/A' }}
                            @endif
                        </td>
                        <td class="payments-currency">P {{ number_format((float) $record->amount, 2) }}</td>
                        @if ($recordType === 'Loan')<td class="payments-currency">P {{ number_format((float) $record->verified_paid_amount, 2) }}</td>@endif
                        <td class="payments-currency">P {{ number_format((float) $record->balance_due, 2) }}</td>
                        <td class="text-secondary">—</td>
                        <td><span class="badge payments-cell-nowrap {{ $record->paymentSettlementStatus() === 'Paid' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $record->paymentSettlementStatus() }}</span></td>
                        <td class="text-secondary">—</td>
                        <td class="payments-date text-secondary">Due {{ $record->due_date?->format('M d, Y') ?? 'N/A' }}</td>
                        <td class="text-end">@if ($record->balance_due > 0 && $recordType !== 'Review')<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal" data-payment-consumer-id="{{ $record->consumer_id }}" data-payment-bill-id="{{ $record->id }}" data-payment-type="{{ $recordType }}">Record {{ $recordType }} Payment</button>@else<span class="text-secondary small">—</span>@endif</td>
                    </tr>
                @elseif ($entry['kind'] === 'loan-repayment')
                    @php
                        $repayment = $record;
                        $billingPeriods = \App\Services\AdminPaymentMonitoring::billingPeriods($repayment->bill, $repayment);
                    @endphp
                    <tr data-record-kind="loan-repayment" data-record-id="{{ $repayment->id }}">
                        <td><div class="payments-cell-primary">{{ $repayment->bill?->prn ?? 'Loan repayment' }}</div><div class="payments-cell-meta">Loan installment #{{ $repayment->bill?->loanInstallment?->installment_number ?? '—' }}</div></td>
                        <td>Loan #{{ $repayment->loan_id }}</td>
                        <td class="payments-date text-secondary">
                            @forelse ($billingPeriods as $period)
                                <div>{{ $period }}</div>
                            @empty
                                Not recorded
                            @endforelse
                        </td>
                        <td class="payments-currency">P {{ number_format((float) $repayment->amount, 2) }}</td>
                        <td class="payments-currency">—</td>
                        <td><span class="badge badge-soft-success">Recorded</span></td>
                        <td><span class="badge payments-cell-nowrap {{ $repayment->bill?->paymentSettlementStatus() === 'Paid' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $repayment->bill?->paymentSettlementStatus() ?? 'N/A' }}</span></td>
                        <td>Over the Counter</td><td class="payments-date text-secondary">{{ $repayment->payment_date?->format('M d, Y') ?? 'N/A' }}</td>
                        <td class="text-end text-secondary small">Recorded by {{ $repayment->recorder?->name ?? 'Admin' }}</td>
                    </tr>
                @else
                    @php
                        $payment = $record;
                        $remainingBalanceLabel = $payment->loan_id && $payment->loan
                            ? app(\App\Services\LoanRepaymentAllocationService::class)->remainingBalance($payment->loan)
                            : $payment->bill?->balance_due;
                        $billSettlementStatus = $payment->billStatusLabel();
                        $billingPeriods = $groupedHistory
                            ? \App\Services\AdminPaymentMonitoring::billingPeriods($payment->bill, $recordType === 'Loan' ? $payment->loanRepayment : null)
                            : collect();
                        $paymentDate = $payment->payment_date?->format('M d, Y') ?? 'Not recorded';
                        $timestampLabel = $payment->status === 'Verified' ? 'Verified Date' : 'Submission Date';
                        $timestamp = \App\Services\AdminPaymentMonitoring::formatTimestamp($payment->status === 'Verified' ? $payment->verified_at : $payment->created_at);
                    @endphp
                    <tr data-record-kind="payment" data-record-id="{{ $payment->id }}">
                        <td>
                            <div class="payments-cell-primary payments-cell-nowrap">{{ $formatPaymentReference($payment->reference_number) }}</div>
                                <div class="payments-cell-meta">{{ $payment->payment_type }} · {{ $payment->bill?->prn ?? ($payment->loan_id && ! $groupedHistory ? 'Loan #'.$payment->loan_id : 'No PRN') }}</div>
                        </td>
                        @if (! $groupedHistory)
                            <td>
                                <div class="payments-cell-primary">{{ $payment->consumer?->full_name ?? 'Unknown consumer' }}</div>
                                @unless ($showAccountColumns)
                                    <div class="payments-cell-meta">{{ $payment->consumer?->account_number }}</div>
                                @endunless
                            </td>
                            @if ($showAccountColumns)
                                <td class="payments-cell-nowrap">{{ $payment->consumer?->account_number ?? '—' }}</td>
                                <td class="payments-cell-nowrap">{{ $payment->consumer?->meter_number ?? '—' }}</td>
                            @endif
                        @elseif ($recordType === 'Loan')
                            @php $loanId = $payment->loan_id ?? $payment->bill?->loanInstallment?->loan_id ?? $payment->bill?->loan_id; @endphp
                            <td>{{ $loanId ? 'Loan #'.$loanId : '—' }}</td>
                        @endif
                        <td class="payments-date text-secondary">
                            @if ($groupedHistory)
                                @forelse ($billingPeriods as $period)
                                    <div>{{ $period }}</div>
                                @empty
                                    Not recorded
                                @endforelse
                            @else
                                {{ $paymentDate }}
                            @endif
                        </td>
                        <td class="payments-currency">P {{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="payments-currency">P {{ number_format((float) $remainingBalanceLabel, 2) }}</td>
                        <td>
                            <span class="badge payments-cell-nowrap {{ match ($payment->status) {
                                'Verified' => 'badge-soft-success',
                                'Rejected' => 'badge-soft-danger',
                                default => 'badge-soft-warning',
                            } }}">{{ $payment->status }}</span>
                        </td>
                        <td><span class="badge payments-cell-nowrap {{ $billSettlementStatus === 'N/A' ? 'bg-light text-secondary' : ($billSettlementStatus === 'Paid' ? 'badge-soft-success' : 'badge-soft-warning') }}">{{ $billSettlementStatus }}</span></td>
                        <td>
                            {{ $paymentChannelLabel($payment->payment_channel) }}
                            @if ($groupedHistory && $payment->payment_method)
                                <div class="payments-cell-meta">{{ $payment->payment_method }}</div>
                            @endif
                        </td>
                        <td class="payments-date text-secondary">
                            {{ $paymentDate }}
                            @if ($groupedHistory)
                                <div class="payments-cell-meta"><strong>{{ $timestampLabel }}</strong> {{ $timestamp }}</div>
                            @endif
                        </td>
                        <td class="text-end payments-col-actions">
                            <div class="payments-row-actions">
                                @if ($payment->status === 'Verified')
                                    <a href="{{ route('payments.receipt', $payment) }}" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#receiptPreviewModal" aria-label="View receipt for payment {{ $payment->reference_number }}">Receipt</a>
                                    @if ($payment->proof_path)
                                        <a href="{{ route('uploads.serve', ['path' => $payment->proof_path]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Proof</a>
                                    @endif
                                @elseif ($canVerifyPayments && in_array($payment->status, ['Pending', 'Rejected'], true))
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#paymentReviewModal"
                                        data-payment-id="{{ $payment->id }}"
                                        data-payment-status="{{ $payment->status }}"
                                        data-payment-rejection-reason="{{ $payment->rejection_reason }}"
                                        data-payment-reference="{{ $formatPaymentReference($payment->reference_number) }}"
                                        data-payment-prn="{{ $payment->bill?->prn ?? ($payment->loan_id ? 'Loan #'.$payment->loan_id : '') }}"
                                        data-payment-consumer="{{ $payment->consumer?->full_name }}"
                                        data-payment-account="{{ $payment->consumer?->account_number }}"
                                        data-payment-channel="{{ $paymentChannelLabel($payment->payment_channel) }}"
                                        data-payment-method="{{ $payment->payment_method }}"
                                        data-payment-amount="{{ number_format((float) $payment->amount, 2, '.', '') }}"
                                        data-payment-date="{{ $paymentDate }}"
                                        @if ($groupedHistory)
                                            data-billing-periods="{{ $billingPeriods->join(', ') }}"
                                            data-submission-date="{{ $timestamp }}"
                                        @endif
                                        data-payment-gateway="{{ $payment->gateway_reference }}"
                                        data-payment-notes="{{ $payment->notes }}"
                                        data-payment-proof="{{ $payment->proof_path ? route('uploads.serve', ['path' => $payment->proof_path]) : '' }}"
                                        data-payment-balance="{{ number_format((float) max(0, ($remainingBalanceLabel ?? 0) - $payment->amount), 2, '.', '') }}"
                                    >{{ $payment->status === 'Rejected' ? 'View Details' : 'Review' }}</button>
                                    @if ($payment->proof_path)
                                        <a href="{{ route('uploads.serve', ['path' => $payment->proof_path]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Proof</a>
                                    @endif
                                @else
                                    <span class="text-secondary small">{{ $payment->proof_path ? 'See proof in records' : 'No action' }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ $showAccountColumns ? 12 : ($groupedHistory && $recordType === 'Water' ? 9 : 10) }}"><div class="payments-empty-state">No {{ $showAccountColumns ? 'pending payments' : strtolower($recordType).' '.($showBills ? 'bills' : 'payments') }} match the selected filters.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
