@push('styles')
<style>
    .meter-workspace, .meter-workspace .row > [class*="col-"],
    .meter-records-panel-body, .meter-cycle-bar > * {
        min-width: 0;
        max-width: 100%;
    }

    .meter-summary-row {
        margin-top: -1.1rem;
    }

    .meter-queue-card {
        border: 1px solid rgba(216, 237, 255, 0.75);
        border-radius: 1rem;
        overflow: hidden;
        background: rgba(250, 252, 253, 0.82);
    }

    .meter-queue-scroll {
        max-height: 26rem;
        overflow-x: auto;
        overflow-y: auto;
    }

    .meter-queue-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .meter-queue-card .table {
        margin-bottom: 0;
    }

    .meter-queue-card thead th {
        background: rgba(244, 248, 251, 0.96);
        color: var(--vf-navy);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom-color: rgba(216, 237, 255, 0.82);
    }

    .meter-queue-card tbody td {
        border-bottom-color: rgba(216, 237, 255, 0.45);
        background: rgba(255, 255, 255, 0.94);
    }

    .meter-queue-card tbody tr:last-child td {
        border-bottom: 0;
    }

    .meter-records-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .meter-records-panel {
        border-radius: 1.4rem;
        min-width: 0;
    }

    .meter-records-panel-body {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .meter-records-copy {
        color: var(--wb-muted);
        font-size: 0.92rem;
        margin: 0.3rem 0 0;
    }

    .meter-filter-form {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(0, 0.8fr) auto;
        gap: 0.75rem;
        align-items: center;
        margin-bottom: 1rem;
    }

    .meter-cycle-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }

    .meter-cycle-bar .form-control {
        min-height: 2.4rem;
    }

    .meter-filter-form .form-control,
    .meter-filter-form .form-select {
        min-height: 2.6rem;
    }

    .meter-records-table {
        border: 1px solid rgba(216, 237, 255, 0.75);
        border-radius: 1rem;
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        background: rgba(250, 252, 253, 0.82);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55);
    }

    .meter-records-table .table {
        margin-bottom: 0;
    }

    .meter-records-table thead th {
        background: rgba(244, 248, 251, 0.96);
        color: var(--vf-navy);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom-color: rgba(216, 237, 255, 0.82);
    }

    .meter-records-table tbody td {
        border-bottom-color: rgba(216, 237, 255, 0.45);
        background: rgba(255, 255, 255, 0.94);
    }

    .meter-records-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .meter-records-pagination {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: 0.25rem;
    }

    .meter-capture-shell {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 1.25rem;
    }

    .meter-modal-content {
        background: #ffffff;
        color: var(--vf-navy);
        border-radius: 1.25rem;
    }

    .meter-form-column {
        padding-right: 1.5rem;
        border-right: 1px solid rgba(216, 237, 255, 0.9);
    }

    .meter-modal-input {
        background: #ffffff !important;
        border: 1px solid rgba(7, 84, 200, 0.16) !important;
        color: var(--vf-navy) !important;
        border-radius: 0.6rem !important;
        padding: 0.8rem 1rem !important;
        min-height: 50px !important;
        font-size: 1rem !important;
    }

    .meter-modal-input:focus {
        border-color: var(--vf-royal) !important;
        box-shadow: 0 0 0 0.2rem rgba(7, 84, 200, 0.12) !important;
    }

    .meter-modal-label {
        color: var(--wb-muted);
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }

    .meter-focus-card {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 1.25rem;
        background: linear-gradient(180deg, rgba(7, 84, 200, 0.08), rgba(255, 255, 255, 0.96));
    }

    .meter-reading-value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }



    .meter-search-result {
        border: 1px solid rgba(7, 84, 200, 0.22);
        border-radius: 1rem;
        background: rgba(7, 84, 200, 0.08);
        padding: 1rem;
    }

    .meter-search-result dt {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--wb-muted);
        font-weight: 800;
        margin-bottom: 0.15rem;
    }

    .meter-search-result dd {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
    }

    .meter-search-result dd:last-child {
        margin-bottom: 0;
    }

    .reading-preview-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(0, 0, 0, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
    }

    .reading-preview-overlay.show {
        display: flex;
    }

    .reading-preview-card {
        background: #fff;
        border: 1px solid var(--vf-light);
        border-radius: 2.5rem;
        padding: 3rem 4.5rem;
        text-align: center;
        box-shadow: 0 1.2rem 3rem rgba(6, 43, 128, 0.18) !important;
        width: auto;
        max-width: 95vw;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .reading-preview-card .reading-label {
        font-size: clamp(1rem, 2.5vw, 2rem);
        text-transform: uppercase;
        letter-spacing: 0.15em;
        color: var(--wb-muted);
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .reading-preview-card .reading-value {
        font-size: clamp(5rem, 18vw, 14rem);
        font-weight: 900;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 0;
    }

    .reading-value .reading-whole {
        color: var(--vf-navy);
        display: flex;
    }

    .reading-value .digit-slot {
        display: inline-block;
        width: 1ch;
        text-align: center;
        cursor: text;
        border-radius: 0.08em;
        transition: background-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        position: relative;
    }

    .reading-value .digit-slot:hover {
        background: rgba(7, 84, 200, 0.08);
    }

    .reading-value .digit-slot:focus {
        outline: 2px solid var(--vf-royal);
        outline-offset: 1px;
        background: var(--vf-light);
        box-shadow: 0 0 0 0.035em rgba(7, 84, 200, 0.16);
    }

    .reading-value .digit-slot:active {
        transform: scale(0.97);
    }

    .reading-value .decimal-sep {
        color: var(--vf-navy);
        margin: 0 0.03em;
        pointer-events: none;
        user-select: none;
    }

    .reading-value .reading-decimal {
        color: #bd5260;
        display: flex;
    }

    .reading-value .reading-decimal .digit-slot {
        color: #bd5260;
    }

    .reading-preview-error {
        display: none;
        margin-top: 1.25rem;
        max-width: 32rem;
        color: #dc3545;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.5;
        text-align: center;
    }

    #donePreviewBtn {
        min-width: 8rem;
        white-space: nowrap;
        word-break: normal;
        transition: background-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    #donePreviewBtn:hover {
        box-shadow: 0 0.35rem 0.9rem rgba(7, 84, 200, 0.2);
        transform: translateY(-1px);
    }

    #donePreviewBtn:active { transform: translateY(0); }

    @media (max-width: 767.98px) {
        .reading-preview-card { padding: 2rem 1rem; }
        .reading-preview-card .reading-value { font-size: clamp(2.2rem, 10vw, 5rem); }
    }

    @media (prefers-reduced-motion: reduce) {
        .reading-value .digit-slot, #donePreviewBtn { transition: none; }
    }

    @media (max-width: 991.98px) {
        .meter-filter-form,
        .meter-capture-shell {
            grid-template-columns: 1fr;
        }

        .meter-form-column {
            padding-right: 0;
            border-right: 0;
        }

        .meter-records-head {
            flex-direction: column;
            align-items: stretch;
        }

        .meter-records-panel {
            padding: 1.15rem !important;
        }

        .meter-records-table {
            border-radius: 0.9rem;
        }

        .meter-records-pagination {
            justify-content: center;
        }
    }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper meter-workspace">
            <div class="page-heading">
                <div>
                    <h1>Meter Reading Workspace</h1>
                    <p>Capture a meter photo, let the system detect the digits, and turn it into a billing-ready reading.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($hasBillingFixtures ?? false)
                        <a href="{{ route('meter-readings.index', ($billingFixture ? [] : ['ledger' => 'demo']) + ['month' => $filters['month']]) }}" class="btn btn-outline-primary">
                            {{ $billingFixture ? 'Recorded readings' : 'Development billing demo' }}
                        </a>
                    @endif
                    @if (! ($billingFixture ?? false))
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#meterReadingModal">Read Meter</button>
                    @endif
                </div>
            </div>

            @if ($billingFixture ?? false)
                <p class="small text-secondary">Development test readings</p>
            @endif

            <div class="row g-4 mb-4 meter-summary-row">
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Assigned Accounts</div>
                        <h2 class="fw-bold mt-2 mb-1">{{ number_format($stats['assigned_accounts']) }}</h2>
                        <span class="badge badge-soft-primary">Today's queue</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Completed Reads</div>
                        <h2 class="fw-bold mt-2 mb-1">{{ number_format($stats['completed_reads']) }}</h2>
                        <span class="badge badge-soft-success">Encoded in {{ $filters['month_label'] }}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Pending Uploads</div>
                        <h2 class="fw-bold mt-2 mb-1">{{ number_format($stats['pending_uploads']) }}</h2>
                        <span class="badge badge-soft-warning">Needs capture</span>
                    </div>
                </div>
            </div>

            <div class="row g-4" data-admin-records-root>
                <div class="col-12">
                    <div class="panel-card p-3 mb-4 meter-cycle-bar" data-admin-freeze>
                        <div>
                            <div class="section-title mb-0">Reading Cycle</div>
                            <div class="small text-secondary mt-1">
                                Showing pending and done readings for {{ $filters['month_label'] }}.
                            </div>
                        </div>
                        <form method="GET" action="{{ route('meter-readings.index') }}" class="d-flex flex-wrap align-items-center gap-2" data-auto-filter data-auto-filter-debounce="0">
                            @if ($billingFixture ?? false)<input type="hidden" name="ledger" value="demo">@endif
                            @if ($filters['search'] !== '')
                                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                            @endif
                            @if ($filters['status'] !== '')
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            @endif
                            <label for="meter_filter_month" class="form-label small text-secondary mb-0 text-nowrap">Billing month</label>
                            @if ($canManageAssignments)
                                <x-period-picker id="meter_filter_month" :years="$readingYears" :allow-all="false"
                                    :selected-year="substr($filters['month'], 0, 4)" :selected-month="substr($filters['month'], 5)" />
                                <noscript><button type="submit" class="btn btn-sm btn-outline-primary">Filter readings</button></noscript>
                            @else
                                <input type="month" class="form-control form-control-sm" id="meter_filter_month" name="month" value="{{ $filters['month'] }}">
                            @endif
                            @if ($filters['month'] !== now()->format('Y-m'))
                                <a
                                    href="{{ route('meter-readings.index', array_filter(['search' => $filters['search'], 'status' => $filters['status'], 'ledger' => ($billingFixture ?? false) ? 'demo' : ''])) }}"
                                    class="btn btn-sm btn-light border"
                                >
                                    Current month
                                </a>
                            @endif
                        </form>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-6">
                            <div class="panel-card p-4 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="section-title mb-0">Pending Reading Queue</div>
                                        <div class="small text-secondary mt-1">
                                            {{ $filters['month_label'] }} cycle &bull; newest verified first.
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-warning">{{ $pendingConsumers->count() }}</span>
                                </div>
                                <div class="meter-queue-card meter-queue-scroll table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead><tr><th>Consumer</th><th>Meter</th><th>Verified</th></tr></thead>
                                        <tbody>
                                            @forelse ($pendingConsumers as $consumer)
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold">{{ $consumer->full_name }}</div>
                                                        <div class="small text-secondary">{{ $consumer->account_number }}</div>
                                                    </td>
                                                    <td>{{ $consumer->meter_number }}</td>
                                                    <td class="small text-secondary">{{ $consumer->verified_at?->format('M d, Y g:i A') ?? '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="text-center py-4 text-secondary">No pending consumers for {{ $filters['month_label'] }}.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="panel-card p-4 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="section-title mb-0">Done Readings</div>
                                        <div class="small text-secondary mt-1">
                                            {{ $filters['month_label'] }} completions &bull; newest encoded first.
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-success">{{ $completedReadings->count() }}</span>
                                </div>
                                <div class="meter-queue-card meter-queue-scroll table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead><tr><th>Consumer</th><th>Current</th></tr></thead>
                                        <tbody>
                                            @forelse ($completedReadings as $reading)
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold">{{ $reading->consumer->full_name }}</div>
                                                        <div class="small text-secondary">
                                                            Encoded {{ $reading->created_at->format('M d, Y g:i A') }}
                                                        </div>
                                                    </td>
                                                    <td>{{ number_format((float) $reading->current_reading, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="2" class="text-center py-4 text-secondary">No completed readings for {{ $filters['month_label'] }}.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-card p-4 meter-records-panel">
                        <div class="meter-records-panel-body">
                        <div class="meter-records-head" data-admin-freeze>
                            <div>
                                <div class="section-title mb-0">All Captured Readings</div>
                                <p class="meter-records-copy">Review encoded and pending captures from one cleaner list.</p>
                            </div>
                        </div>

                        <form method="GET" action="{{ route('meter-readings.index') }}" class="meter-filter-form" data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                            @if ($billingFixture ?? false)<input type="hidden" name="ledger" value="demo">@endif
                            <input type="hidden" name="month" value="{{ $filters['month'] }}">
                            <input
                                type="text"
                                class="form-control"
                                id="meter_filter_search"
                                name="search"
                                value="{{ $filters['search'] }}"
                                placeholder="Search consumer, account, or meter number"
                            >
                            <div class="d-flex align-items-center gap-2 status-filter-display" style="min-width:0"><select aria-label="Status filter" data-status-filter data-clearable-filter style="min-width:0;flex:1 1 0" class="form-select" id="meter_filter_status" name="status">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
<button type="submit" name="status" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear status filter">Clear</button>
</div>
                            <div class="d-flex align-items-center gap-2">
                                <noscript>
                                    <button type="submit" class="btn btn-primary">Apply filters</button>
                                </noscript>
                                @if ($filters['search'] !== '' || $filters['status'] !== '')<a href="{{ route('meter-readings.index', ($billingFixture ?? false) ? ['ledger' => 'demo'] : []) }}" class="btn btn-light border">Clear filters</a>@endif
                            </div>
                        </form>

                        @if (session('success'))
                            <div class="alert alert-success d-flex align-items-start gap-2 mb-0" role="alert">
                                <i class="bi bi-check-circle mt-1"></i>
                                <div>{{ session('success') }}</div>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger d-flex align-items-start gap-2 mb-0" role="alert">
                                <i class="bi bi-exclamation-circle mt-1"></i>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        @endif



                        <div class="meter-records-table table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Consumer</th><th>Read Date</th><th>Previous</th><th>Current</th><th>Consumption</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse ($readings as $reading)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $reading->consumer->full_name }}</div>
                                                <div class="small text-secondary">
                                                    {{ $reading->consumer->account_number }} &bull;
                                                    MT {{ $reading->meter_number ?? $reading->consumer->meter_number }}
                                                    @if ($reading->meter_number && $reading->meter_number !== $reading->consumer->meter_number)
                                                        <span class="badge badge-soft-secondary ms-1" title="This reading was taken from a meter that has since been replaced.">replaced meter</span>
                                                    @endif
                                                </div>
                                                @if ($canManageAssignments)
                                                    <div class="small text-secondary">Reader: {{ $reading->reader->name }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $reading->reading_date->format('M d, Y') }}</div>
                                                <div class="small text-secondary">Encoded {{ $reading->created_at->format('M d, Y g:i A') }}</div>
                                            </td>
                                            <td>{{ number_format((float) $reading->previous_reading, 2) }}</td>
                                            <td>{{ number_format((float) $reading->current_reading, 2) }}</td>
                                            <td>{{ number_format((float) $reading->consumption, 2) }}</td>
                                            <td>
                                                <span class="badge {{ match ($reading->status) {
                                                    'Encoded' => 'badge-soft-success',
                                                    'Needs Revisit' => 'badge-soft-danger',
                                                    default => 'badge-soft-warning',
                                                } }}">
                                                    {{ $reading->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-5 text-secondary">No meter readings recorded yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="meter-records-pagination">
                            {{ $readings->links() }}
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="meterReadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content meter-modal-content shadow-lg border-0">
            <div class="modal-header border-bottom-0 p-4">
                <h2 class="modal-title fs-5 fw-bold">Read Meter</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <form method="POST" action="{{ route('meter-readings.store', [], false) }}" id="meterReadingCreateForm" novalidate>
                    @csrf
                    <div class="meter-focus-card p-4 mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase fw-semibold mb-2">Consumer</div>
                                <div class="fw-semibold" id="consumer_name_display">Enter a Meter ID first</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase fw-semibold mb-2">Account No.</div>
                                <div class="fw-semibold" id="account_number_display">-</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase fw-semibold mb-2">Previous Reading</div>
                                <div class="meter-reading-value" id="previous_reading_display">0.00</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase fw-semibold mb-2">Meter ID</div>
                                <div class="fw-semibold" id="meter_number_display">Enter a Meter ID first</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6 meter-form-column">
                            <div class="mb-3">
                                <label class="form-label meter-modal-label" for="meter_number">Meter ID</label>
                                <div class="input-group">
                                    <input type="text" class="form-control meter-modal-input" id="meter_number" name="meter_number" value="{{ old('meter_number') }}" placeholder="Enter Meter ID" required autocomplete="off">
                                    <button class="btn btn-outline-primary" type="button" id="lookupMeterBtn"><i class="bi bi-search"></i></button>
                                </div>
                                <div class="small text-secondary mt-1" id="meter_search_status"></div>
                            </div>

                            <div class="meter-search-result mb-3 d-none" id="meter_search_result">
                                <dl class="mb-0">
                                    <dt>Consumer Name</dt>
                                    <dd id="result_consumer_name">-</dd>
                                    <dt>Account Number</dt>
                                    <dd id="result_account_number">-</dd>
                                    <dt>Previous Reading</dt>
                                    <dd id="result_previous_reading">-</dd>
                                    @if ($canManageAssignments)
                                        <dt>Applicable Water Rate</dt>
                                        <dd id="result_unit_rate">-</dd>
                                    @endif
                                </dl>
                            </div>

                            <input type="hidden" id="meter_consumer_id" name="consumer_id" value="">

                            <div class="mb-3">
                                <label class="form-label meter-modal-label" for="current_reading">Current Reading</label>
                                <input type="text" class="form-control meter-modal-input" id="current_reading" name="current_reading" inputmode="decimal" value="{{ old('current_reading') }}" placeholder="Enter current meter reading" required autocomplete="off">
                                <div class="invalid-feedback" id="current_reading_feedback">Please enter a valid number.</div>
                            </div>

                            @if ($canManageAssignments)
                                <div class="mb-3">
                                    <label class="form-label meter-modal-label" for="reader_id">Meter Reader</label>
                                    <select class="form-select meter-modal-input" id="reader_id" name="reader_id" required>
                                        <option value="">Assign reader</option>
                                        @foreach ($readers as $reader)
                                            <option value="{{ $reader->id }}" @selected((string) old('reader_id') === (string) $reader->id)>{{ $reader->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="mb-4">
                                <label class="form-label meter-modal-label" for="reading_date">Reading Date</label>
                                <input type="date" class="form-control meter-modal-input" id="reading_date" name="reading_date" value="{{ old('reading_date', now()->toDateString()) }}" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label meter-modal-label" for="remarks">Remarks</label>
                                <input type="text" class="form-control meter-modal-input" id="remarks" name="remarks" value="{{ old('remarks') }}" placeholder="Optional remarks for unclear, obstructed, or revisited meters">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3" id="saveMeterReadingBtn" disabled>Save Meter Reading</button>
                        </div>
                        <div class="col-lg-6">
                            <div class="meter-focus-card p-4 h-100">
                                <div class="text-secondary small text-uppercase fw-semibold mb-3">{{ $canManageAssignments ? 'Billing Preview' : 'Reading Preview' }}</div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Previous Meter Reading</span>
                                    <strong id="preview_previous_reading">0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Current Meter Reading</span>
                                    <strong id="preview_current_reading">—</strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Monthly Consumption</span>
                                    <strong><span id="preview_consumption">—</span> m3</strong>
                                </div>
                                @if ($canManageAssignments)
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Applicable Water Rate</span>
                                    <strong>P <span id="preview_unit_rate">0.00</span>/m3</strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Base Charge</span>
                                    <strong>P <span id="preview_base_charge">0.00</span></strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Previous Outstanding Balance <span id="preview_balance_period"></span></span>
                                    <strong>P <span id="preview_previous_balance">0.00</span></strong>
                                </div>
                                <div class="d-flex justify-content-between pt-3 fs-5">
                                    <span class="fw-semibold">Estimated Total Due</span>
                                    <strong class="text-primary">P <span id="preview_total_bill">—</span></strong>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>

                <div class="reading-preview-overlay" id="readingPreviewOverlay">
                    <div class="reading-preview-card">
                        <div class="reading-label">Current Reading</div>
                        <div class="reading-value empty" id="readingPreviewValue">
                            <span class="reading-whole">
                                <span class="digit-slot" id="ds0">0</span>
                                <span class="digit-slot" id="ds1">0</span>
                                <span class="digit-slot" id="ds2">0</span>
                                <span class="digit-slot" id="ds3">0</span>
                                <span class="digit-slot" id="ds4">0</span>
                            </span>
                            <span class="decimal-sep">.</span>
                            <span class="reading-decimal">
                                <span class="digit-slot" id="ds5">0</span>
                                <span class="digit-slot" id="ds6">0</span>
                            </span>
                        </div>
                        <div class="reading-preview-error" id="readingPreviewError" role="alert"></div>
                        <button type="button" class="btn btn-primary btn-lg px-5 mt-4 rounded-pill fw-bold" id="donePreviewBtn">Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="meterReadingSubmitConfirmModal" tabindex="-1" aria-labelledby="meterReadingSubmitConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; background: #ffffff; color: var(--vf-navy);">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="meterReadingSubmitConfirmModalLabel">Confirm Meter Reading Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-0 text-secondary">Are you sure you want to submit this meter reading?</p>
            </div>
            <div class="modal-footer border-top-0 pt-0 d-flex justify-content-end gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary fw-bold px-3" data-bs-dismiss="modal" id="cancelMeterReadingSubmitBtn">Cancel</button>
                <button type="button" class="btn btn-primary fw-bold px-3" id="confirmMeterReadingSubmitBtn">Yes, Submit</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="duplicateReadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 1.25rem;">
            <div class="modal-body text-center p-4">
                <div class="mb-3" style="font-size: 3rem; color: #dc3545;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h5 class="fw-bold mb-2" style="color: #dc3545;">Warning</h5>
                <p class="mb-4" style="color: #dc3545; font-weight: 600;">This account has been read already.</p>
                <button type="button" class="btn btn-danger px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('meterReadingModal');
        const meterNumberInput = document.getElementById('meter_number');
        const lookupMeterBtn = document.getElementById('lookupMeterBtn');
        const meterSearchStatus = document.getElementById('meter_search_status');
        const meterSearchResult = document.getElementById('meter_search_result');
        const resultConsumerName = document.getElementById('result_consumer_name');
        const resultAccountNumber = document.getElementById('result_account_number');
        const resultPreviousReading = document.getElementById('result_previous_reading');
        @if ($canManageAssignments)
        const resultUnitRate = document.getElementById('result_unit_rate');
        @endif
        const meterConsumerId = document.getElementById('meter_consumer_id');
        const currentReadingInput = document.getElementById('current_reading');
        const readingPreviewError = document.getElementById('readingPreviewError');
        const readingDateInput = document.getElementById('reading_date');
        const previousReadingDisplay = document.getElementById('previous_reading_display');
        const consumerNameDisplay = document.getElementById('consumer_name_display');
        const accountNumberDisplay = document.getElementById('account_number_display');
        const meterNumberDisplay = document.getElementById('meter_number_display');
        let meterLookupTimeout = null;
        let meterLookupSequence = 0;
        let alreadyRead = false;
        let previousReading = 0;
        @if ($canManageAssignments)
        let unitRate = 0;
        let baseCharge = 0;
        let previousBalance = 0;
        @endif

        const meterReadingForm = document.getElementById('meterReadingCreateForm');
        const donePreviewBtn = document.getElementById('donePreviewBtn');
        const saveMeterReadingBtn = document.getElementById('saveMeterReadingBtn');
        const meterReadingSubmitConfirmModalElement = document.getElementById('meterReadingSubmitConfirmModal');
        const confirmMeterReadingSubmitBtn = document.getElementById('confirmMeterReadingSubmitBtn');
        const cancelMeterReadingSubmitBtn = document.getElementById('cancelMeterReadingSubmitBtn');
        let meterReadingSubmissionProcessing = false;
        let meterReadingConfirmModalTransitioning = false;

        // Register the key meter-reading actions before the rest of the meter UI
        // initializes. Done only closes the digit preview and returns to the
        // Read Meter form. The confirmation modal belongs exclusively to Save
        // Meter Reading.
        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            if (target.closest('#donePreviewBtn')) {
                event.preventDefault();
                event.stopPropagation();
                syncFromSlots();

                hideReadingPreview();
                return;
            }

            if (target.closest('#saveMeterReadingBtn')) {
                event.preventDefault();
                event.stopPropagation();
                showMeterReadingSubmitConfirmation();
                return;
            }

            if (target.closest('#confirmMeterReadingSubmitBtn')) {
                event.preventDefault();
                event.stopPropagation();
                confirmMeterReadingSubmission();
                return;
            }

            if (target.closest('#cancelMeterReadingSubmitBtn')) {
                event.preventDefault();
                event.stopPropagation();
                cancelMeterReadingSubmission();
            }
        }, true);

        // The form itself never submits directly. Submission only happens after
        // the user confirms from the Save Meter Reading modal, and the Enter key
        // must not bypass that confirmation.
        meterReadingForm?.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopPropagation();
        });

        meterReadingSubmitConfirmModalElement?.addEventListener('show.bs.modal', function () {
            meterReadingConfirmModalTransitioning = true;
            modalElement.inert = true;
        });

        meterReadingSubmitConfirmModalElement?.addEventListener('shown.bs.modal', function () {
            meterReadingConfirmModalTransitioning = false;
        });

        meterReadingSubmitConfirmModalElement?.addEventListener('hidden.bs.modal', function () {
            meterReadingConfirmModalTransitioning = false;
            modalElement.inert = false;

            if (modalElement.classList.contains('show')) {
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
        });

        function formatMoney(value) {
            return (Math.round((Number(value) || 0) * 100) / 100).toFixed(2);
        }

        function setCurrentReadingError(message) {
            const feedback = document.getElementById('current_reading_feedback');
            const hasError = Boolean(message);

            feedback.textContent = message || 'Please enter a valid number.';
            feedback.style.display = hasError ? 'block' : 'none';

            if (readingPreviewError) {
                readingPreviewError.textContent = message || '';
                readingPreviewError.style.display = hasError ? 'block' : 'none';
            }
        }

        function updateBillingPreview() {
            const raw = currentReadingInput.value.trim();
            const numeric = /^\d+(?:\.\d{1,2})?$/.test(raw);
            const currentReading = numeric ? Number(raw) : NaN;
            const hasMeter = Boolean(meterConsumerId.value);
            const invalidReading = hasMeter && numeric && currentReading <= previousReading;
            const valid = hasMeter && !alreadyRead && numeric && currentReading > previousReading;
            currentReadingInput.classList.toggle('is-invalid', invalidReading);
            setCurrentReadingError(invalidReading ? 'Current reading must be greater than the previous reading.' : null);
            saveMeterReadingBtn.disabled = !valid || meterReadingSubmissionProcessing;

            document.getElementById('preview_previous_reading').textContent = formatMoney(previousReading);
            document.getElementById('preview_current_reading').textContent = valid ? formatMoney(currentReading) : '—';
            document.getElementById('preview_consumption').textContent = valid ? formatMoney(currentReading - previousReading) : '—';
            @if ($canManageAssignments)
            document.getElementById('preview_unit_rate').textContent = formatMoney(unitRate);
            document.getElementById('preview_base_charge').textContent = formatMoney(baseCharge);
            const balancePreview = document.getElementById('preview_previous_balance');
            if (balancePreview) {
                balancePreview.textContent = formatMoney(previousBalance);
            }
            document.getElementById('preview_total_bill').textContent = valid ? formatMoney(baseCharge + (currentReading - previousReading) * unitRate + previousBalance) : '—';
            @endif
        }

        function resetMeterResult() {
            meterSearchResult.classList.add('d-none');
            meterConsumerId.value = '';
            previousReading = 0;
            @if ($canManageAssignments)
            unitRate = 0;
            baseCharge = 0;
            previousBalance = 0;
            document.getElementById('preview_balance_period').textContent = '';
            @endif
            consumerNameDisplay.textContent = 'Enter a Meter ID first';
            accountNumberDisplay.textContent = '-';
            meterNumberDisplay.textContent = 'Enter a Meter ID first';
            previousReadingDisplay.textContent = '0.00';
            @if ($canManageAssignments)
            resultUnitRate.textContent = '-';
            @endif
            setCurrentReadingError(null);
            updateBillingPreview();
        }

        function updateFocusCard(data) {
            previousReading = parseFloat(data.previous_reading) || 0;
            @if ($canManageAssignments)
            unitRate = parseFloat(data.unit_rate) || 0;
            baseCharge = parseFloat(data.base_charge) || 0;
            previousBalance = parseFloat(data.previous_balance) || 0;
            document.getElementById('preview_balance_period').textContent = data.outstanding_period ? '(' + data.outstanding_period + ')' : '';
            @endif
            consumerNameDisplay.textContent = data.full_name;
            accountNumberDisplay.textContent = data.account_number;
            meterNumberDisplay.textContent = data.meter_number;
            previousReadingDisplay.textContent = data.previous_reading;
            setCurrentReadingError(null);
            updateBillingPreview();
        }

        async function refreshMeterCsrfToken() {
            const response = await fetch('{{ route('meter-readings.csrf-token', [], false) }}', {
                credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.status === 401 || response.status === 403 || response.redirected) {
                throw new Error('Your session has expired. Please sign in again.');
            }
            if (!response.ok) throw new Error('Unable to refresh your session. Please try again.');
            const data = await response.json();
            if (!data.token) throw new Error('Unable to refresh your session. Please try again.');
            meterReadingForm.querySelector('input[name="_token"]').value = data.token;
            return data.token;
        }

        async function lookupMeter(meterNumber) {
            const sequence = ++meterLookupSequence;
            const requestedMeter = meterNumber.trim();
            resetMeterResult();
            alreadyRead = false;
            currentReadingInput.disabled = false;
            if (!requestedMeter) {
                meterSearchStatus.textContent = '';
                return;
            }
            meterSearchStatus.textContent = 'Looking up Meter ID...';
            meterSearchStatus.className = 'small text-secondary mt-1';
            try {
                let response;
                for (let attempt = 0; attempt < 2; attempt++) {
                    const token = await refreshMeterCsrfToken();
                    const formData = new FormData();
                    formData.append('meter_number', requestedMeter);
                    formData.append('reading_date', readingDateInput.value);
                    formData.append('_token', token);
                    response = await fetch('{{ route('meter-readings.lookup-by-meter', [], false) }}', {
                        method: 'POST', body: formData, credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (response.status !== 419 || attempt === 1) break;
                }
                if (sequence !== meterLookupSequence || meterNumberInput.value.trim() !== requestedMeter) return;
                if (response.status === 401 || response.status === 403 || response.redirected) throw new Error('Your session has expired. Please sign in again.');
                if (response.status === 419) throw new Error('Your session changed. Please try the lookup again.');
                const data = await response.json();
                if (data.found) {
                    resultConsumerName.textContent = data.consumer.full_name;
                    resultAccountNumber.textContent = data.consumer.account_number;
                    resultPreviousReading.textContent = data.consumer.previous_reading;
                    @if ($canManageAssignments)
                    resultUnitRate.textContent = 'P ' + data.consumer.unit_rate + '/m3';
                    @endif
                    meterConsumerId.value = data.consumer.id;
                    meterSearchResult.classList.remove('d-none');
                    updateFocusCard(data.consumer);
                    meterSearchStatus.textContent = '';
                    meterSearchStatus.className = 'small text-secondary mt-1';
                    if (data.already_read) {
                        alreadyRead = true;
                        currentReadingInput.disabled = true;
                        updateBillingPreview();
                        const dupModal = document.getElementById('duplicateReadingModal');
                        if (dupModal) new bootstrap.Modal(dupModal).show();
                    }
                } else {
                    meterSearchStatus.textContent = data.message || 'Unable to look up this Meter ID. Please try again.';
                    meterSearchStatus.className = 'small text-danger mt-1';
                }
            } catch (error) {
                if (sequence !== meterLookupSequence || meterNumberInput.value.trim() !== requestedMeter) return;
                meterSearchStatus.textContent = error.message || 'Lookup failed. Please try again.';
                meterSearchStatus.className = 'small text-danger mt-1';
            }
        }

        function doLookup() {
            ++meterLookupSequence;
            resetMeterResult();
            alreadyRead = false;
            currentReadingInput.disabled = false;
            if (meterLookupTimeout) {
                clearTimeout(meterLookupTimeout);
            }
            meterLookupTimeout = setTimeout(function () {
                lookupMeter(meterNumberInput.value);
            }, 400);
        }

        meterNumberInput.addEventListener('input', doLookup);
        lookupMeterBtn.addEventListener('click', function () {
            if (meterLookupTimeout) {
                clearTimeout(meterLookupTimeout);
            }
            lookupMeter(meterNumberInput.value);
        });

        meterNumberInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (meterLookupTimeout) {
                    clearTimeout(meterLookupTimeout);
                }
                lookupMeter(meterNumberInput.value);
            }
        });

        const readingPreviewOverlay = document.getElementById('readingPreviewOverlay');
        const readingPreviewValue = document.getElementById('readingPreviewValue');
        const TOTAL_SLOTS = 7;
        const digitSlots = [];
        for (let i = 0; i < TOTAL_SLOTS; i++) {
            digitSlots.push(document.getElementById('ds' + i));
        }

        let isSyncingFromSlots = false;

        function syncFromSlots() {
            isSyncingFromSlots = true;
            let whole = '';
            for (let i = 0; i < 5; i++) whole += digitSlots[i].textContent;
            let dec = '';
            for (let i = 5; i < 7; i++) dec += digitSlots[i].textContent;
            const formatted = whole + '.' + dec;
            currentReadingInput.value = formatted;
            readingPreviewValue.className = 'reading-value';
            currentReadingInput.classList.remove('is-invalid');
            setCurrentReadingError(null);
            updateBillingPreview();
            isSyncingFromSlots = false;
        }

        digitSlots.forEach(function (slot, index) {
            slot.setAttribute('tabindex', '0');
            slot.setAttribute('role', 'button');
            slot.addEventListener('click', function () { this.focus(); });
            slot.addEventListener('keydown', function (e) {
                const digit = parseInt(e.key, 10);
                if (!isNaN(digit) && digit >= 0 && digit <= 9) {
                    e.preventDefault();
                    this.textContent = e.key;
                    if (index + 1 < TOTAL_SLOTS) digitSlots[index + 1].focus(); else this.blur();
                    syncFromSlots();
                    return;
                }
                if (e.key === 'Backspace') {
                    e.preventDefault();
                    this.textContent = '0';
                    if (index > 0) digitSlots[index - 1].focus(); else this.blur();
                    syncFromSlots();
                    return;
                }
                if (e.key === 'ArrowLeft' && index > 0) { e.preventDefault(); digitSlots[index - 1].focus(); return; }
                if (e.key === 'ArrowRight' && index + 1 < TOTAL_SLOTS) { e.preventDefault(); digitSlots[index + 1].focus(); return; }
                if (e.key === 'Tab') { syncFromSlots(); return; }
                e.preventDefault();
            });
        });

        function syncSlotsFromInput() {
            const val = currentReadingInput.value.trim();
            const num = parseFloat(val) || 0;
            const whole = String(Math.floor(num)).padStart(5, '0').slice(-5);
            const dec = String(Math.round((num % 1) * 100)).padStart(2, '0').slice(-2);
            for (let i = 0; i < 5; i++) {
                digitSlots[i].textContent = whole[i];
            }
            for (let i = 0; i < 2; i++) {
                digitSlots[5 + i].textContent = dec[i];
            }
            readingPreviewValue.className = (val === '' || parseFloat(val) === 0) ? 'reading-value empty' : 'reading-value';
        }

        function showReadingPreview() {
            syncSlotsFromInput();
            updateBillingPreview();
            readingPreviewOverlay.classList.add('show');
            readingPreviewOverlay.setAttribute('aria-hidden', 'false');
        }

        function hideReadingPreview() {
            readingPreviewOverlay.classList.remove('show');
            readingPreviewOverlay.setAttribute('aria-hidden', 'true');

            if (readingPreviewError) {
                readingPreviewError.textContent = '';
                readingPreviewError.style.display = 'none';
            }
        }

        // Clicking the field opens the large preview. Focus and blur handlers
        // are deliberately avoided: they used to close and immediately reopen
        // the overlay before the Done button could finish its click.
        currentReadingInput.addEventListener('click', showReadingPreview);

        function getMeterReadingSubmitConfirmModal() {
            if (!meterReadingSubmitConfirmModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return null;
            }

            return bootstrap.Modal.getOrCreateInstance(meterReadingSubmitConfirmModalElement);
        }

        function showMeterReadingSubmitConfirmation() {
            if (meterReadingSubmissionProcessing) {
                return;
            }

            if (!validateMeterReading()) {
                return;
            }

            getMeterReadingSubmitConfirmModal()?.show();
        }

        function hideMeterReadingSubmitConfirmation() {
            const confirmModal = getMeterReadingSubmitConfirmModal();
            if (!confirmModal) {
                return;
            }

            // Bootstrap ignores hide() while the modal is still opening. If the
            // user clicks Cancel during that short transition, close the modal
            // as soon as it has finished opening.
            if (meterReadingConfirmModalTransitioning && meterReadingSubmitConfirmModalElement) {
                meterReadingSubmitConfirmModalElement.addEventListener('shown.bs.modal', function () {
                    confirmModal.hide();
                }, { once: true });
                return;
            }

            confirmModal.hide();
        }

        function validateMeterReading() {
            const val = currentReadingInput.value.trim();

            if (alreadyRead) {
                const dupModal = document.getElementById('duplicateReadingModal');
                if (dupModal) new bootstrap.Modal(dupModal).show();
                return false;
            }

            if (!/^\d+(?:\.\d{1,2})?$/.test(val)) {
                currentReadingInput.classList.add('is-invalid');
                setCurrentReadingError('Please enter a valid number.');
                return false;
            }

            if (Number(val) <= previousReading) {
                currentReadingInput.classList.add('is-invalid');
                setCurrentReadingError('Current reading must be greater than the previous reading.');
                return false;
            }

            if (!meterConsumerId.value) {
                meterSearchStatus.textContent = 'Please look up a valid Meter ID first.';
                meterSearchStatus.className = 'small text-danger mt-1';
                setCurrentReadingError('Please look up a valid Meter ID first.');
                return false;
            }

            setCurrentReadingError(null);
            return true;
        }

        readingPreviewOverlay.addEventListener('click', function (e) {
            if (e.target === readingPreviewOverlay) {
                hideReadingPreview();
                currentReadingInput.focus();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || !readingPreviewOverlay.classList.contains('show')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            hideReadingPreview();
            currentReadingInput.focus();
        }, true);

        currentReadingInput.addEventListener('input', function () {
            if (isSyncingFromSlots) return;
            let raw = this.value.replace(/[^0-9.]/g, '');
            const dotIdx = raw.indexOf('.');
            let whole = dotIdx === -1 ? raw : raw.slice(0, dotIdx);
            let dec = dotIdx === -1 ? '' : raw.slice(dotIdx + 1).replace(/\./g, '');
            whole = whole.slice(0, 5);
            dec = dec.slice(0, 2);
            const padded = whole.padStart(5, '0');
            const formatted = dec === '' ? padded : padded + '.' + dec;
            const cursorEnd = formatted.length;
            this.value = formatted;
            this.setSelectionRange(cursorEnd, cursorEnd);
            this.classList.remove('is-invalid');
            setCurrentReadingError(null);
            syncSlotsFromInput();
            updateBillingPreview();
        });

        currentReadingInput.addEventListener('keydown', function (e) {
            if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E') {
                e.preventDefault();
            }
        });

        async function confirmMeterReadingSubmission() {
            if (!meterReadingForm || meterReadingSubmissionProcessing) {
                return;
            }

            const readingIsValid = validateMeterReading();
            const formIsValid = meterReadingForm.checkValidity();

            if (!readingIsValid || !formIsValid) {
                hideMeterReadingSubmitConfirmation();

                if (!readingIsValid) {
                    if (!meterConsumerId.value) {
                        meterNumberInput.focus();
                        meterNumberInput.select();
                    } else {
                        currentReadingInput.focus();
                    }
                }

                if (!formIsValid) {
                    meterReadingForm.reportValidity();
                }

                return;
            }

            meterReadingSubmissionProcessing = true;
            if (confirmMeterReadingSubmitBtn) {
                confirmMeterReadingSubmitBtn.disabled = true;
                confirmMeterReadingSubmitBtn.textContent = 'Submitting...';
            }
            if (saveMeterReadingBtn) saveMeterReadingBtn.disabled = true;
            if (donePreviewBtn) donePreviewBtn.disabled = true;
            try {
                await refreshMeterCsrfToken();
                hideMeterReadingSubmitConfirmation();
                meterReadingForm.submit();
            } catch (error) {
                meterReadingSubmissionProcessing = false;
                if (confirmMeterReadingSubmitBtn) {
                    confirmMeterReadingSubmitBtn.disabled = false;
                    confirmMeterReadingSubmitBtn.textContent = 'Yes, Submit';
                }
                updateBillingPreview();
                if (donePreviewBtn) donePreviewBtn.disabled = false;
                hideMeterReadingSubmitConfirmation();
                meterSearchStatus.textContent = error.message || 'Unable to submit the reading. Please try again.';
                meterSearchStatus.className = 'small text-danger mt-1';
            }
        }

        function cancelMeterReadingSubmission() {
            hideMeterReadingSubmitConfirmation();
        }

        meterReadingSubmitConfirmModalElement?.addEventListener('hidden.bs.modal', function () {
            if (modalElement.classList.contains('show')) {
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
        });

        modalElement.addEventListener('hidden.bs.modal', function () {
            if (meterReadingSubmitConfirmModalElement?.classList.contains('show')) {
                hideMeterReadingSubmitConfirmation();
            }

            meterReadingSubmissionProcessing = false;
            if (confirmMeterReadingSubmitBtn) {
                confirmMeterReadingSubmitBtn.disabled = false;
                confirmMeterReadingSubmitBtn.textContent = 'Yes, Submit';
            }
            if (saveMeterReadingBtn) saveMeterReadingBtn.disabled = false;
            donePreviewBtn.disabled = false;

            hideReadingPreview();
            meterNumberInput.value = '';
            resetMeterResult();
            meterSearchStatus.textContent = '';
            meterSearchStatus.className = 'small text-secondary mt-1';
            currentReadingInput.value = '';
            currentReadingInput.disabled = false;
            currentReadingInput.classList.remove('is-invalid');
            setCurrentReadingError(null);
            updateBillingPreview();
            document.getElementById('reading_date').value = '{{ now()->toDateString() }}';
            for (let i = 0; i < TOTAL_SLOTS; i++) digitSlots[i].textContent = '0';
            readingPreviewValue.className = 'reading-value empty';
            alreadyRead = false;
        });

        readingDateInput.addEventListener('change', function () {
            if (meterNumberInput.value.trim() !== '') {
                lookupMeter(meterNumberInput.value);
            }
        });

        const duplicateModalEl = document.getElementById('duplicateReadingModal');
        if (duplicateModalEl) {
            duplicateModalEl.addEventListener('hidden.bs.modal', function () {
                meterNumberInput.focus();
                meterNumberInput.select();
            });
        }

        @if ($errors->any())
        if (modalElement) {
            const meterReadingModal = new bootstrap.Modal(modalElement);
            meterReadingModal.show();
        }
        @endif

        @if ($errors->has('duplicate_reading'))
        if (duplicateModalEl) {
            new bootstrap.Modal(duplicateModalEl).show();
        }
        @endif
    });
</script>
@endpush
