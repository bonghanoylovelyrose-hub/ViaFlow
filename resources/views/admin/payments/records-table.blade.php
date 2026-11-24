<section class="panel-card p-4 payments-panel" data-admin-freeze-section aria-labelledby="bills-and-payments-title">
    @php
        $paymentChannelLabel = fn (?string $channel): string => $channel === 'Counter' ? 'Over the Counter' : ($channel ?: 'Over the Counter');
    @endphp

    <div class="payments-sticky-controls" data-admin-freeze>
        <div class="payments-panel-head">
            <div class="payments-panel-head-copy">
                <h2 class="payments-panel-title" id="bills-and-payments-title">Bills and Payments</h2>
                <p class="payments-panel-copy">{{ $viewType === 'review' ? 'Water and loan payments awaiting verification.' : $recordType.' payment history and verification records.' }}</p>
            </div>
            <span class="badge badge-soft-primary payments-panel-badge">{{ number_format($transactionCount) }} records</span>
        </div>

        @if ($viewType === 'review')
            <form method="GET" action="{{ route('payments.index') }}" class="payments-filter-form" data-auto-filter data-auto-filter-debounce="450">
                <input type="hidden" name="view" value="{{ $viewType }}">
                <input type="search" class="form-control payments-filter-search" id="payment_filter_search" name="search"
                    value="{{ $filters['search'] }}" placeholder="Search by name, account, meter, or reference..." aria-label="Search consumer, account, meter, or reference">
                <div class="d-flex align-items-center gap-2" style="min-width:0">
                    <select data-clearable-filter style="min-width:0;flex:1 1 0" class="form-select" id="payment_filter_type" name="payment_type" aria-label="Filter by payment type">
                        <option value="Water" @selected($paymentTypeFilter === 'Water')>Water</option>
                        <option value="Loan" @selected($paymentTypeFilter === 'Loan')>Loan</option>
                    </select>
                    <button type="submit" name="payment_type" value="All" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear payment type filter">Clear</button>
                </div>
                <div class="d-flex align-items-center gap-2" style="min-width:0">
                    <select data-clearable-filter style="min-width:0;flex:1 1 0" class="form-select" id="payment_filter_channel" name="channel" aria-label="Filter by payment channel">
                        @foreach ($channels as $channelOption)
                            <option value="{{ $channelOption }}" @selected($filters['channel'] === $channelOption)>{{ $paymentChannelLabel($channelOption) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" name="channel" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear channel filter">Clear</button>
                </div>
                <div class="payments-filter-actions">
                    <noscript><button type="submit" class="btn btn-primary">Apply filters</button></noscript>
                    @if ($filters['search'] !== '' || $filters['channel'] !== '' || $filters['status'] !== '' || $paymentTypeFilter !== 'All')
                        <a href="{{ route('payments.index', ['view' => $viewType]) }}" class="btn btn-light border">Clear filters</a>
                    @endif
                </div>
            </form>
        @else
            <form method="GET" action="{{ route('payments.index') }}" class="record-filter-form" data-auto-filter data-auto-filter-debounce="450">
                <input type="hidden" name="view" value="{{ $viewType }}">
                <div class="record-filter-field record-filter-search">
                    <label for="payment_filter_search">Search Consumer / Account / Meter / Reference</label>
                    <input type="search" class="form-control" id="payment_filter_search" name="search" value="{{ $filters['search'] }}" placeholder="Search by name, account, meter, or reference...">
                </div>
                <div class="record-filter-field">
                    <label for="payment_filter_year">Period</label>
                    <x-period-picker id="payment_filter_year" month-id="payment_filter_month" :years="$paymentYears"
                        :selected-year="$filters['year'] ?: ''" :selected-month="$filters['month']" />
                </div>
                <div class="record-filter-field">
                    <label for="payment_filter_status">Payment Status</label>
                    <select class="form-select" id="payment_filter_status" name="status">
                        <option value="">All Status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="record-filter-actions">
                    <noscript><button type="submit" class="btn btn-primary">Apply filters</button></noscript>
                    <a href="{{ route('payments.index', ['view' => $viewType]) }}" class="record-filter-clear">Clear</a>
                </div>
            </form>
        @endif
    </div>

    <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Billing category">
        @foreach (['water-payments' => 'Water Payments', 'loan-payments' => 'Loan Payments', 'review' => 'Needs Review'] as $key => $label)
            @php
                $tabFilters = array_filter([
                    'view' => $key,
                    'search' => $filters['search'] ?: null,
                    'channel' => $key === 'review' ? ($filters['channel'] ?? null) : null,
                    'year' => $key !== 'review' ? ($filters['year'] ?: null) : null,
                    'month' => $key !== 'review' ? ($filters['month'] ?: null) : null,
                    'status' => $viewType !== 'review' && $key !== 'review' ? ($filters['status'] ?: null) : null,
                    'payment_type' => $viewType === 'review' && $key === 'review' && $paymentTypeFilter !== 'All' ? $paymentTypeFilter : null,
                ], fn ($value) => $value !== null && $value !== '');
            @endphp
            <a class="btn btn-sm {{ $viewType === $key ? 'btn-primary' : 'btn-light border' }}" href="{{ route('payments.index', $tabFilters) }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($viewType === 'review')
        @include('admin.payments.transaction-table', ['entries' => $paymentRecords, 'groupedHistory' => false])
        @if ($paymentRecords->total() > 0)
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                <p class="text-secondary mb-0">Showing {{ $paymentRecords->firstItem() }} to {{ $paymentRecords->lastItem() }} of {{ $paymentRecords->total() }} records</p>
                {{ $paymentRecords->links() }}
            </div>
        @endif
    @else
        @include('admin.payments.account-histories')
    @endif
</section>
