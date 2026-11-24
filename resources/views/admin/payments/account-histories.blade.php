<div class="payments-table-wrap" role="region" aria-label="Consumer payment accounts" tabindex="0">
    <table class="table align-middle payments-table payments-accounts-table">
        <thead>
            <tr>
                <th scope="col">Consumer</th>
                <th scope="col">Account Number</th>
                <th scope="col">Meter Number</th>
                <th scope="col">Latest Transaction</th>
                <th scope="col">Transactions</th>
                <th scope="col" class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paymentAccounts as $account)
                @php
                    $historyId = 'payment-history-'.$account['id'];
                    $history = $account['history'];
                    $meterNumber = $account['consumer']?->meter_number;
                @endphp
                <tr id="payment-account-{{ $account['id'] }}">
                    <td class="payments-cell-primary">{{ $account['consumer']?->full_name ?? 'Unknown consumer' }}</td>
                    <td>{{ $account['consumer']?->account_number ?? '—' }}</td>
                    <td class="payments-cell-nowrap">{{ filled($meterNumber) ? $meterNumber : '—' }}</td>
                    <td>{{ $account['latest_date']?->format('M d, Y') ?? 'N/A' }}</td>
                    <td>{{ $history->total() }}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap"
                            aria-controls="{{ $historyId }}" aria-expanded="{{ $account['expanded'] ? 'true' : 'false' }}" data-payment-history-toggle>
                            <i class="bi bi-chevron-down me-1" aria-hidden="true"></i>View Payments
                        </button>
                    </td>
                </tr>
                <tr id="{{ $historyId }}" class="payments-history-row" @if (! $account['expanded']) hidden @endif>
                    <td colspan="6">
                        @include('admin.payments.transaction-table', ['entries' => $history, 'groupedHistory' => true])
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                            <p class="text-secondary mb-0">Showing {{ $history->firstItem() }} to {{ $history->lastItem() }} of {{ $history->total() }} transactions</p>
                            @if ($history->hasPages())
                                <nav class="d-flex gap-2" aria-label="Transactions for {{ $account['consumer']?->full_name ?? 'this consumer' }}">
                                    @if ($history->onFirstPage())
                                        <button type="button" class="btn btn-sm btn-outline-primary" disabled>Previous</button>
                                    @else
                                        <a class="btn btn-sm btn-outline-primary" href="{{ $history->previousPageUrl() }}">Previous</a>
                                    @endif
                                    @if ($history->hasMorePages())
                                        <a class="btn btn-sm btn-outline-primary" href="{{ $history->nextPageUrl() }}">Next</a>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-primary" disabled>Next</button>
                                    @endif
                                </nav>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="payments-empty-state">No {{ strtolower($recordType) }} payments match the selected filters.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($paymentAccounts->count() > 0)
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
        <p class="text-secondary mb-0">Showing {{ $paymentAccounts->firstItem() }} to {{ $paymentAccounts->lastItem() }} of {{ $paymentAccounts->total() }} accounts</p>
        {{ $paymentAccounts->links() }}
    </div>
@endif
