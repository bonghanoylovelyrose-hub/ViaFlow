<section class="consumer-details-section">
    <h3 class="fw-bold">Recent Water Billing and Payments</h3>
    <p>Outstanding water balance <strong>₱{{ number_format($outstandingWater, 2) }}</strong></p>
    @if ($bills->isEmpty())
        <p class="text-secondary mb-0">No water bills recorded.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Billing Period</th><th>Due Date</th><th>Status</th><th>Verified Payments</th><th>Overdue Amount</th></tr></thead>
                <tbody>
                @foreach ($bills as $bill)
                    @php($overdueAmount = $balances->isOverdue($bill) ? ($remaining[$bill->id] ?? 0) : 0)
                    <tr>
                        <td><a href="{{ route('billing.show', $bill) }}">{{ $bill->billing_period?->format('M Y') }}</a>
                            @if ($bill->is_demo)<span class="badge bg-secondary">Demo</span>@endif
                        </td>
                        <td>{{ $bill->due_date?->format('M d, Y') }}</td>
                        <td>{{ $bill->balanceIsCarriedForward() ? 'Carried Forward' : $bill->computedStatus() }}</td>
                        <td>
                            @forelse ($bill->payments as $payment)
                                <div>{{ $payment->payment_date?->format('M d, Y') }} · ₱{{ number_format((float) $payment->amount, 2) }}
                                    @if ($bill->due_date && $payment->payment_date?->gt($bill->due_date))
                                        <span class="text-danger">Late</span>
                                    @endif
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>₱{{ number_format($overdueAmount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
