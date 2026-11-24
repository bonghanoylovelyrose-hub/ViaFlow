@extends('layouts.app')

@section('title', 'Disconnection History')

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => 'consumers'])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading inactive-account-heading"><h1 id="disconnection-history-title">Disconnection History</h1></div>
            @include('admin.consumers.account-tabs')

            <section class="panel-card inactive-records" aria-labelledby="disconnection-history-title" data-admin-records-root data-admin-page-scroll>
                <form method="GET" id="history-filters" action="{{ route('consumers.index') }}" class="row g-2 align-items-end mb-3" data-auto-filter>
                    <input type="hidden" name="account_category" value="disconnection_history">
                    <div class="col-md-6">
                        <label for="history-search" class="form-label">Search</label>
                        <input id="history-search" name="search" type="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Name, account, or meter number">
                    </div>
                    <div class="col-md-6">
                        <label for="history-status" class="form-label">Disconnection Status</label>
                        <div class="d-flex gap-2">
                            <select id="history-status" name="history_status" class="form-select" style="min-width:0;flex:1 1 0">
                                @foreach ($historyStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['history_status'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <a class="btn btn-outline-primary btn-sm flex-shrink-0 d-inline-flex align-items-center"
                               href="{{ route('consumers.index', ['account_category' => 'disconnection_history']) }}"
                               aria-label="Clear disconnection history filters">Clear</a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle" id="disconnection-history-table" role="table" aria-labelledby="disconnection-history-title">
                        <colgroup>
                            <col span="3">
                            <col class="history-time" span="2">
                            <col class="history-status">
                            <col class="history-actions">
                        </colgroup>
                        <thead role="rowgroup">
                            <tr>
                                <th scope="col">Consumer Name</th>
                                <th scope="col">Account Number</th>
                                <th scope="col">Meter Number</th>
                                <th scope="col">Disconnection Date &amp; Time</th>
                                <th scope="col">Reconnection Date &amp; Time</th>
                                <th scope="col">Current Account Status</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup">
                            @forelse ($records as $record)
                                @php($consumer = $record['consumer'])
                                <tr role="row">
                                    <td role="cell" class="fw-semibold">{{ $consumer->full_name }}</td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Account Number</span>{{ $consumer->account_number ?: 'Not Recorded' }}</td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Meter Number</span>{{ $consumer->meter_number ?: 'Not Recorded' }}</td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Disconnection Date &amp; Time</span>{{ $record['disconnection_date']?->format('M d, Y g:i:s A') ?? 'Not Recorded' }}</td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Reconnection Date &amp; Time</span>{{ \App\Support\ReconnectionSchedule::format($record['reconnected_at'], 'M d, Y g:i:s A') ?? 'Not Recorded' }}</td>
                                    <td role="cell"><span class="inactive-record-label" aria-hidden="true">Current Account Status</span><x-consumer-status-badge :consumer="$consumer" /></td>
                                    <td role="cell" class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#disconnection-history-{{ $loop->iteration }}">View Details</button>
                                    </td>
                                </tr>
                            @empty
                                <tr role="row"><td role="cell" colspan="7" class="text-center text-secondary py-3">No disconnection records match the current filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $records->links() }}
            </section>
        </div>
    </main>
</div>

@foreach ($records as $record)
    <div class="modal fade" id="disconnection-history-{{ $loop->iteration }}" tabindex="-1" aria-labelledby="disconnection-history-title-{{ $loop->iteration }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="disconnection-history-title-{{ $loop->iteration }}">Disconnection Details</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold">{{ $record['consumer']->full_name }} <span class="text-secondary">{{ $record['consumer']->account_number }}</span></p>
                    <dl class="mb-0">
                        <dt>Disconnection Date &amp; Time</dt>
                        <dd>{{ $record['disconnection_date']?->format('M d, Y g:i:s A') ?? 'Not Recorded' }}</dd>
                        @if (!empty($record['scheduled_reconnection_at']))
                            <dt>Scheduled Reconnection Date &amp; Time (Philippine time)</dt>
                            <dd>{{ \App\Support\ReconnectionSchedule::format($record['scheduled_reconnection_at']) }}</dd>
                        @endif
                        <dt>Reconnection Date &amp; Time (Philippine time)</dt>
                        <dd>{{ \App\Support\ReconnectionSchedule::format($record['reconnected_at'], 'M d, Y g:i:s A') ?? 'Not Recorded' }}</dd>
                        <dt>Disconnection Reason</dt>
                        <dd>{{ $record['reason'] ?? 'Not Recorded' }}</dd>
                        <dt>Outstanding Balance at Disconnection</dt>
                        <dd>{{ $record['outstanding_balance'] !== null ? 'PHP '.number_format($record['outstanding_balance'], 2) : 'Not Recorded' }}</dd>
                        <dt>Payment Settlement Date &amp; Time</dt>
                        <dd>{{ $record['payment_settlement_date']?->format('M d, Y g:i A') ?? 'Not Recorded' }}</dd>
                    </dl>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
@endforeach
@endsection
