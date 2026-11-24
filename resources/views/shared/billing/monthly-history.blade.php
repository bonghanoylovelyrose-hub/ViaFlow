<h3 class="billing-section-title h6" id="billing-history-heading-{{ $consumer->id }}">Monthly Billing History</h3>

<div class="table-responsive bg-white">
    <table class="table align-middle billing-table" aria-labelledby="billing-history-heading-{{ $consumer->id }}">
        <thead>
            <tr>
                <th scope="col">Billing Period</th>
                <th scope="col">PRN</th>
                <th scope="col">Previous Reading</th>
                <th scope="col">Current Reading</th>
                <th scope="col">Consumption</th>
                <th scope="col">Rate</th>
                <th scope="col">Previous Balance</th>
                <th scope="col">Current Charges</th>
                <th scope="col">Total Due</th>
                <th scope="col">Outstanding Balance</th>
                <th scope="col">Payment Status</th>
                <th scope="col">Payment Deadline</th>
                <th scope="col" class="text-end">Statement</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                @continue(! $record->item)
                @php
                    $item = $record->item;
                    $isReading = $record->record_type === 'reading';
                    $period = $isReading ? $item->reading_date : $item->billing_period;
                @endphp
                <tr data-monthly-record>
                    <td>{{ $period->format('F Y') }}</td>
                    @if ($isReading)
                        <td>—</td>
                        <td>{{ number_format((float) $item->previous_reading, 2) }}</td>
                        <td>{{ number_format((float) $item->current_reading, 2) }}</td>
                        <td>{{ number_format((float) $item->consumption, 2) }} m3</td>
                        <td>—</td><td>—</td><td>—</td><td>—</td><td>—</td>
                        <td><span class="badge badge-soft-warning">Awaiting Billing</span></td>
                        <td>—</td>
                        <td class="text-end">—</td>
                    @else
                        @php
                            $breakdown = $item->billing_breakdown;
                            $badgeClass = match ($record->status) {
                                'Paid' => 'badge-soft-success',
                                'Overdue', 'Disconnection Notice' => 'badge-soft-danger',
                                default => 'badge-soft-warning',
                            };
                        @endphp
                        <td>
                            <span class="billing-badge">{{ $item->prn }}</span>
                            @if ($item->is_demo && ! $item->is_billing_fixture)<span class="badge bg-secondary">Demo</span>@endif
                            @if ($item->is_billing_fixture)
                                <div class="billing-meta">{{ $item->hasBeenIssued() ? 'Issued' : 'Scheduled' }} {{ $item->created_at?->copy()->timezone('Asia/Manila')->format('M d, Y') }}</div>
                            @endif
                            @foreach ($item->earlierIssues->where('is_billing_fixture', false) as $earlierIssue)
                                <div class="billing-meta"><a href="{{ route('billing.show', $earlierIssue) }}">Earlier issue {{ $earlierIssue->prn }}</a></div>
                            @endforeach
                        </td>
                        <td>{{ number_format((float) ($breakdown['previous_meter_reading'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($breakdown['current_meter_reading'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($breakdown['total_consumption'] ?? 0), 2) }} m3</td>
                        <td>&#8369;{{ number_format((float) ($breakdown['rate_per_cubic_meter'] ?? 0), 2) }}</td>
                        <td class="billing-amount">&#8369;{{ number_format((float) ($breakdown['previous_unpaid_balance'] ?? 0), 2) }}</td>
                        <td class="billing-amount">&#8369;{{ number_format((float) ($breakdown['current_bill_amount'] ?? 0), 2) }}</td>
                        <td class="billing-amount">&#8369;{{ number_format((float) $item->amount, 2) }}</td>
                        <td class="billing-amount">
                            @if ($item->is_billing_fixture)
                                &#8369;{{ number_format($item->balance_due, 2) }}
                                @if (! $item->hasBeenIssued())
                                    <div class="billing-meta">&#8369;{{ number_format($item->unsettledAmount(), 2) }} projected after issue</div>
                                @elseif ($item->balanceHasBeenCarriedForward() && $item->statement_balance !== null)
                                    <div class="billing-meta">&#8369;{{ number_format($item->statement_balance, 2) }} carried forward</div>
                                @endif
                            @else
                                {{ $item->statement_balance === null ? 'Not available' : '₱'.number_format($item->statement_balance, 2) }}
                                @if ($item->balanceIsCarriedForward())<div class="billing-meta">Carried forward</div>@endif
                            @endif
                            @if ($item->earlier_issue_credit > 0)
                                <div class="billing-meta">Includes &#8369;{{ number_format($item->earlier_issue_credit, 2) }} credit</div>
                            @endif
                        </td>
                        <td><span class="badge {{ $badgeClass }}">{{ $record->status }}</span></td>
                        <td>{{ $item->due_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-2">
                                <a href="{{ route('billing.show', ['bill' => $item] + $filters) }}"
                                    class="billing-icon-button" aria-label="View billing statement for {{ $period->format('F Y') }}">
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                                <a href="{{ route('billing.statement.pdf', $item) }}" class="billing-button billing-download-button">
                                    <i class="bi bi-download" aria-hidden="true"></i>Download Bill
                                </a>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="13" class="billing-empty">No monthly records match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($records->total() > 0)
    <div class="billing-pagination">
        <p class="billing-meta mb-0">Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} monthly records</p>
        <nav class="d-flex flex-wrap gap-2" aria-label="Monthly billing pages">
            @if ($records->onFirstPage())
                <button class="btn btn-outline-primary btn-sm" disabled>Previous</button>
            @else
                <a class="btn btn-outline-primary btn-sm" data-monthly-page href="{{ $records->previousPageUrl() }}">Previous</a>
            @endif
            @if ($records->hasMorePages())
                <a class="btn btn-outline-primary btn-sm" data-monthly-page href="{{ $records->nextPageUrl() }}">Next</a>
            @else
                <button class="btn btn-outline-primary btn-sm" disabled>Next</button>
            @endif
        </nav>
    </div>
@endif
