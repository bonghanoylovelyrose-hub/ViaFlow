@extends('layouts.app')

@php($activePage = 'reports')
@php($periodQuery = array_intersect_key($filters->toQuery(), array_flip(['month', 'year'])))

@section('title', 'Reports Center')

@push('styles')
<style>
    .reports-catalog {
        position: static;
    }

    .reports-catalog-category {
        color: var(--vf-navy);
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        margin: 1rem 0 0.4rem;
        text-transform: uppercase;
    }

    .reports-catalog-category:first-child {
        margin-top: 0;
    }

    .reports-catalog-link {
        align-items: flex-start;
        border-radius: 0.65rem;
        color: var(--vf-navy);
        display: flex;
        gap: 0.65rem;
        padding: 0.6rem 0.7rem;
        text-decoration: none;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .reports-catalog-link:hover {
        background: #f1f5f9;
        color: var(--vf-navy);
    }

    .reports-catalog-link.active {
        background: var(--vf-background);
        color: var(--vf-navy);
        font-weight: 600;
    }

    .reports-catalog-link i {
        color: var(--vf-navy);
        font-size: 0.9rem;
        margin-top: 0.15rem;
    }

    .reports-catalog-link small {
        color: var(--vf-royal);
        display: block;
        font-size: 0.68rem;
        font-weight: 400;
        margin-top: 0.1rem;
    }

    .report-stats {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
        margin-bottom: 1.25rem;
    }

    .report-stat {
        background: #f8fafc;
        border: 1px solid var(--vf-light);
        border-radius: 0.8rem;
        padding: 0.85rem 1rem;
    }

    .report-stat span {
        color: var(--vf-navy);
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .report-stat strong {
        color: var(--vf-navy);
        display: block;
        font-size: 1.2rem;
        margin-top: 0.25rem;
        overflow-wrap: anywhere;
    }

    .report-table-wrap {
        border: 1px solid var(--vf-light);
        border-radius: 0.75rem;
        max-height: none;
        overflow-x: auto;
        overflow-y: visible;
    }

    .report-table {
        font-size: 0.8rem;
        margin-bottom: 0;
    }

    .report-table thead th {
        background: #f8fafc;
        border-bottom: 1px solid var(--vf-light);
        color: var(--vf-navy);
        font-size: 0.68rem;
        letter-spacing: 0.03em;
        position: static;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .report-table td {
        border-color: #eef2f7;
        max-width: 280px;
        overflow-wrap: anywhere;
        vertical-align: top;
    }

    .report-table tbody tr:hover {
        background: #fbfdff;
    }

    .report-table .report-money,
    .report-table .report-number {
        text-align: right;
        white-space: nowrap;
    }

    .report-table .report-date,
    .report-table .report-datetime,
    .report-table .report-nowrap {
        white-space: nowrap;
    }

    .report-description {
        color: var(--vf-navy);
        font-size: 0.86rem;
        margin-bottom: 0;
        max-width: 760px;
    }

    .report-toolbar {
        align-items: flex-start;
        border-bottom: 1px solid var(--vf-light);
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
    }

    @media (max-width: 1199.98px) {
        .reports-catalog {
            position: static;
        }

        .reports-catalog-links {
            display: grid;
            gap: 0.1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .reports-catalog-category {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 575.98px) {
        .report-table {
            font-size: 0.75rem;
        }

        .report-stat strong {
            font-size: 1.05rem;
        }
    }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Reports Center</h1>
                    <p class="mb-0">One place for operational, financial, loan, and connection reports. Every filter applies to the table and all three export formats.</p>
                </div>
            </div>

            <div class="row g-4" data-admin-natural-page-scroll>
                <div class="col-xl-3">
                    <div class="panel-card reports-catalog p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <div class="fw-semibold">Report Catalog</div>
                                <div class="text-secondary small">{{ count($definitions) }} available reports</div>
                            </div>
                            <span class="badge text-bg-light border">Admin</span>
                        </div>

                        <div class="reports-catalog-links">
                            @foreach ($groupedDefinitions as $category => $group)
                                <div class="reports-catalog-category">{{ $category }}</div>
                                @foreach ($group as $definition)
                                    <a
                                        href="{{ route('reports.index', array_merge($periodQuery, ['report' => $definition->key])) }}"
                                        class="reports-catalog-link {{ $definition->key === $reportKey ? 'active' : '' }}"
                                    >
                                        <i class="bi bi-file-earmark-bar-graph"></i>
                                        <span>
                                            {{ $definition->label }}
                                            <small>{{ $definition->dateBasis }} &middot; {{ count($definition->columns) }} columns</small>
                                        </span>
                                    </a>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-xl-9">
                    <div class="panel-card p-3 p-lg-4 mb-4">
                        <form method="GET" action="{{ route('reports.index') }}" data-auto-filter data-auto-filter-debounce="450">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-lg-4">
                                    <label for="report" class="form-label small fw-semibold">Report</label>
                                    <select name="report" id="report" class="form-select">
                                        @foreach ($groupedDefinitions as $category => $group)
                                            <optgroup label="{{ $category }}">
                                                @foreach ($group as $definition)
                                                    <option value="{{ $definition->key }}" @selected($definition->key === $reportKey)>
                                                        {{ $definition->label }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12 col-lg-8">
                                    <label for="year" class="form-label small fw-semibold">Period</label>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <x-period-picker id="year" month-id="month" :years="$years" :selected-year="$filters->year"
                                            :selected-month="$filters->month" class="flex-grow-1" />
                                        <button type="submit" name="month" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear month filter">Clear month</button>
                                        <button type="submit" name="year" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear year filter">Clear year</button>
                                    </div>
                                </div>

                                @if ($result->definition->statusOptions !== [])
                                    <div class="col-12 col-sm-6 col-lg-4">
                                        
                                        <div class="d-flex align-items-center gap-2 status-filter-display" style="min-width:0"><select aria-label="Status filter" data-status-filter data-clearable-filter style="min-width:0;flex:1 1 0" name="status" id="status" class="form-select">
                                            @foreach ($result->definition->statusOptions as $status)
                                                <option value="{{ $status }}" @selected($filters->status === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
<button type="submit" name="status" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear status filter">Clear</button>
</div>
                                    </div>
                                @endif

                                @if ($result->definition->categoryOptions !== [])
                                    <div class="col-12 col-sm-6 col-lg-4">
                                        <label for="category" class="form-label small fw-semibold">Category</label>
                                        <div class="d-flex align-items-center gap-2" style="min-width:0"><select data-clearable-filter style="min-width:0;flex:1 1 0" name="category" id="category" class="form-select">
                                            @foreach ($result->definition->categoryOptions as $category)
                                                <option value="{{ $category }}" @selected($filters->category === $category)>{{ $category }}</option>
                                            @endforeach
                                        </select>
<button type="submit" name="category" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear category filter">Clear</button>
</div>
                                    </div>
                                @endif

                                @if ($result->definition->channelOptions !== [])
                                    <div class="col-12 col-sm-6 col-lg-4">
                                        <label for="channel" class="form-label small fw-semibold">Channel</label>
                                        <div class="d-flex align-items-center gap-2" style="min-width:0"><select data-clearable-filter style="min-width:0;flex:1 1 0" name="channel" id="channel" class="form-select">
                                            @foreach ($result->definition->channelOptions as $channel)
                                                <option value="{{ $channel }}" @selected($filters->channel === $channel)>{{ $channel === 'Counter' ? 'Over the Counter' : $channel }}</option>
                                            @endforeach
                                        </select>
<button type="submit" name="channel" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear channel filter">Clear</button>
</div>
                                    </div>
                                @endif

                                @if ($result->definition->typeOptions !== [])
                                    <div class="col-12 col-sm-6 col-lg-4">
                                        <label for="type" class="form-label small fw-semibold">Type</label>
                                        <div class="d-flex align-items-center gap-2" style="min-width:0"><select data-clearable-filter style="min-width:0;flex:1 1 0" name="type" id="type" class="form-select">
                                            @foreach ($result->definition->typeOptions as $type)
                                                <option value="{{ $type }}" @selected($filters->type === $type)>{{ $type }}</option>
                                            @endforeach
                                        </select>
<button type="submit" name="type" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear type filter">Clear</button>
</div>
                                    </div>
                                @endif

                                <div class="col-12 col-lg-auto d-flex gap-2">
                                    <noscript>
                                        <button type="submit" class="btn btn-primary">Apply filters</button>
                                    </noscript>
                                    @if ($filters->month !== null || $filters->year !== null || filled($filters->status) || filled($filters->category) || filled($filters->channel) || filled($filters->type))<a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Reset</a>@endif
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="panel-card p-3 p-lg-4">
                        <div class="report-toolbar">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h2 class="h5 mb-0">{{ $result->definition->label }}</h2>
                                    <span class="badge text-bg-light border">{{ $result->definition->category }}</span>
                                </div>
                                <p class="report-description mt-1">{{ $result->definition->description }}</p>
                                <div class="text-secondary small mt-2">
                                    <i class="bi bi-calendar3 me-1"></i>{{ $result->filters->periodLabel() }}
                                    <span class="mx-2">&middot;</span>
                                    Date basis: {{ $result->definition->dateBasis }}
                                    <span class="mx-2">&middot;</span>
                                    {{ number_format($result->total) }} {{ \Illuminate\Support\Str::plural('record', $result->total) }}
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('reports.export', $result->exportQuery('pdf')) }}" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                </a>
                                <a href="{{ route('reports.export', $result->exportQuery('xlsx')) }}" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                </a>
                                <a href="{{ route('reports.export', $result->exportQuery('csv')) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-filetype-csv me-1"></i> CSV
                                </a>
                            </div>
                        </div>

                        <div class="report-stats">
                            @foreach ($result->summary as $item)
                                <div class="report-stat">
                                    <span>{{ $item['label'] }}</span>
                                    <strong>{{ \App\Support\ReportValue::display($item['value'], $item['type']) }}</strong>
                                </div>
                            @endforeach
                        </div>

                        @php($nowrapKeys = ['prn', 'account_number', 'reference_number', 'receipt_number', 'loan_id', 'reference', 'bill_prn', 'meter_number', 'request_type'])
                        <div class="report-table-wrap">
                            <table class="table table-sm report-table" style="min-width: {{ max(950, count($result->definition->columns) * 110) }}px;">
                                <thead>
                                    <tr>
                                        @foreach ($result->definition->columns as $key => $column)
                                            @php($headerClass = $column['type'] === 'money' ? 'report-money' : ($column['type'] === 'number' ? 'report-number' : ''))
                                            <th class="{{ trim($headerClass.' '.(in_array($key, $nowrapKeys, true) ? 'report-nowrap' : '')) }}">
                                                {{ $column['label'] }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($result->rows as $row)
                                        <tr>
                                            @foreach ($result->definition->columns as $key => $column)
                                                @php($value = $row[$key] ?? null)
                                                @php($cellClass = match ($column['type']) {
                                                    'money' => 'report-money',
                                                    'number' => 'report-number',
                                                    'date' => 'report-date',
                                                    'datetime' => 'report-datetime',
                                                    default => '',
                                                })
                                                @php($cellClass = trim($cellClass.' '.(in_array($key, $nowrapKeys, true) ? 'report-nowrap' : '')))
                                                <td class="{{ $cellClass }}">
                                                    @if ($column['type'] === 'status')
                                                        @if ($value === null || $value === '')
                                                            <span class="text-secondary">—</span>
                                                        @else
                                                            @php($tone = match (strtolower((string) $value)) {
                                                                'active', 'verified', 'paid', 'completed', 'resolved' => 'success',
                                                                'pending', 'for review', 'pending verification', 'pending review', 'unpaid', 'for consumer confirmation' => 'warning',
                                                                'rejected', 'overdue', 'disconnected', 'issued' => 'danger',
                                                                'for reconnection', 'ongoing', 'paid with balance', 'needs revisit', 'approved' => 'info',
                                                                default => 'secondary',
                                                            })
                                                            <span class="badge text-bg-{{ $tone }}">{{ $value }}</span>
                                                        @endif
                                                    @elseif ($column['type'] === 'long_text')
                                                        <span title="{{ $value }}">{{ \Illuminate\Support\Str::limit((string) $value, 100) ?: '—' }}</span>
                                                    @else
                                                        {{ \App\Support\ReportValue::display($value, $column['type']) }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($result->definition->columns) }}" class="text-center py-5 text-secondary">
                                                <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                                No records found for {{ $result->filters->periodLabel() }}.
                                                Try another month, year, or reset the filters.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @php($paginator = $result->paginator())
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <div class="text-secondary small">
                                @if ($paginator->total() > 0)
                                    Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}
                                    of {{ number_format($paginator->total()) }} records
                                @else
                                    No records to display
                                @endif
                            </div>
                            {{ $paginator->links() }}
                        </div>

                        <div class="text-secondary small mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            This report is read-only. Exported PDF, Excel, and CSV files use the same filters and data shown above.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection
