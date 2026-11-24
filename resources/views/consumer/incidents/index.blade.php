@extends('layouts.app')

@php
    $activePage = 'incidents';
    $consumerProfile = auth()->user()->consumer;
@endphp

@section('title', 'Incidents')

@push('styles')
<style>
    .incident-reporting .page-heading {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: .75rem 1rem;
        margin-bottom: 1rem;
    }

    .incident-reporting .incident-panels {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
    }

    .incident-reporting .panel-card { padding: clamp(.85rem, 2vw, 1.25rem); }

    .incident-reporting .incident-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .5rem;
        margin: 0;
    }

    .incident-reporting .incident-metrics > div {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .35rem .65rem;
        padding: .55rem .75rem;
        border: 1px solid var(--vf-light);
        border-radius: .65rem;
        background: var(--vf-background);
    }

    .incident-reporting .incident-metrics dt { font-size: .875rem; font-weight: 600; }
    .incident-reporting .incident-metrics dd { margin: 0; }

    .incident-reporting .incident-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: .65rem;
        margin-bottom: .85rem;
    }

    .incident-reporting .incident-search { flex: 1 1 14rem; min-width: 0; }
    .incident-reporting .incident-category { flex: 0 1 18rem; min-width: 0; }
    .incident-reporting .incident-category .form-select { flex: 1 1 0; min-width: 0; }
    .incident-reporting .incident-filters .form-label { margin-bottom: .25rem; }
    .incident-reporting .incident-filters :is(.form-control, .form-select, .btn) { min-height: 2.4rem; }

    .incident-reporting .incident-empty {
        padding: 1.15rem .75rem;
        border: 1px solid var(--vf-light);
        border-radius: .75rem;
        text-align: center;
    }

    .incident-reporting .incident-table-frame { border: 1px solid var(--vf-light); border-radius: .75rem; }
    .incident-reporting .incident-table { min-width: 0; margin: 0; font-size: .875rem; table-layout: fixed; }
    .incident-reporting .incident-table :is(th, td) { padding: .5rem .6rem; }
    .incident-reporting .incident-table td { overflow-wrap: anywhere; }
    .incident-reporting .incident-table thead th { background: var(--vf-background); font-size: .75rem; }
    .incident-reporting .incident-table th:nth-child(1) { width: 22%; }
    .incident-reporting .incident-table th:nth-child(2) { width: 15%; }
    .incident-reporting .incident-table th:nth-child(4) { width: 10%; }
    .incident-reporting .incident-table th:nth-child(5) { width: 6.75rem; }
    .incident-reporting .incident-table th:nth-child(6) { width: 4.75rem; }
    .incident-reporting .incident-table .btn { padding-inline: .5rem; white-space: nowrap; }
    .incident-reporting .incident-table tr:last-child td { border-bottom: 0; }
    .incident-reporting .incident-cell-label { display: none; }

    @media (max-width: 767.98px) {
        .incident-reporting .page-heading { grid-template-columns: minmax(0, 1fr); }
        .incident-reporting .incident-table-frame { border: 0; }
        .incident-reporting .incident-table,
        .incident-reporting .incident-table td { display: block; }

        .incident-reporting .incident-table thead {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
        }

        .incident-reporting .incident-table tbody { display: grid; gap: .55rem; }
        .incident-reporting .incident-table tbody tr {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .45rem .5rem;
            padding: .65rem .75rem;
            border: 1px solid var(--vf-light);
            border-radius: .75rem;
            background: var(--vf-white);
        }

        .incident-reporting .incident-table td { grid-column: span 2; padding: 0; border: 0; }
        .incident-reporting .incident-table td:nth-child(-n+2) { grid-column: span 3; }
        .incident-reporting .incident-table td:nth-child(3) { grid-column: 1 / -1; }
        .incident-reporting .incident-table td:last-child { align-self: end; }
        .incident-reporting .incident-cell-label {
            display: block;
            color: var(--wb-muted);
            font-size: .75rem;
            font-weight: 600;
        }
    }

    @media (max-width: 575.98px) {
        .incident-reporting .incident-metrics > div { flex-direction: column; align-items: flex-start; padding: .45rem; }
        .incident-reporting .incident-filters :is(.incident-search, .incident-category) { flex-basis: 100%; }
    }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper incident-reporting">
            <div class="page-heading">
                <div>
                    <h1>Incident Reporting</h1>
                    <p>Report water service issues and track updates.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#incidentModal"><i class="bi bi-exclamation-circle me-2"></i>{{ $canManageIncidents ? 'Log Incident' : 'Submit New Report' }}</button>
            </div>

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @unless ($incidentsTableExists)
                <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-tools mt-1"></i>
                    <div>Incidents is installed in code but the database table is not created yet. Run <code>php artisan migrate</code> to enable it.</div>
                </div>
            @endunless

            <div class="incident-panels">
                <section class="panel-card" aria-labelledby="incident-snapshot-title">
                    <h2 class="section-title mb-2" id="incident-snapshot-title">{{ $canManageIncidents ? 'Response Snapshot' : 'My Report Snapshot' }}</h2>
                    <dl class="incident-metrics">
                        <div><dt class="text-secondary">Total Reports</dt><dd><span class="badge badge-soft-primary">{{ $stats['total'] }}</span></dd></div>
                        <div><dt class="text-secondary">Pending</dt><dd><span class="badge badge-soft-warning">{{ $stats['pending'] }}</span></dd></div>
                        <div><dt class="text-secondary">Resolved</dt><dd><span class="badge badge-soft-success">{{ $stats['resolved'] }}</span></dd></div>
                    </dl>
                </section>

                <section class="panel-card" aria-labelledby="incident-reports-title">
                    <div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="section-title mb-0" id="incident-reports-title">Submitted Reports</h2>
                        <nav class="d-flex flex-wrap gap-1" aria-label="Filter incident reports by status">
                            <a
                                href="{{ route('incidents.index', array_filter(['category' => $filters['category'], 'search' => $filters['search']], fn ($value) => $value !== null && $value !== '')) }}"
                                class="btn btn-sm {{ $filters['status'] === '' ? 'btn-primary' : 'btn-light border' }}"
                                @if ($filters['status'] === '') aria-current="page" @endif
                            >All</a>
                            @foreach ($statuses as $filterStatus)
                                <a
                                    href="{{ route('incidents.index', array_filter(['status' => $filterStatus === 'Pending' ? 'Pending' : 'Resolved', 'category' => $filters['category'], 'search' => $filters['search']], fn ($value) => $value !== null && $value !== '')) }}"
                                    class="btn btn-sm {{ $filters['status'] === $filterStatus ? 'btn-primary' : 'btn-light border' }}"
                                    @if ($filters['status'] === $filterStatus) aria-current="page" @endif
                                >{{ $filterStatus }}</a>
                            @endforeach
                        </nav>
                    </div>

                    <form method="GET" action="{{ route('incidents.index') }}" class="incident-filters" data-auto-filter data-auto-filter-debounce="450">
                        @if ($filters['status'] !== '')
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                        @endif
                        <div class="incident-search">
                            <label class="form-label small fw-semibold text-secondary" for="incident_filter_search">Search</label>
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                id="incident_filter_search"
                                name="search"
                                value="{{ $filters['search'] }}"
                                placeholder="Name, location, or account"
                            >
                        </div>
                        <div class="incident-category">
                            <label class="form-label small fw-semibold text-secondary" for="incident_filter_category">Category</label>
                            <div class="d-flex align-items-center gap-2">
                                <select data-clearable-filter class="form-select form-select-sm" id="incident_filter_category" name="category">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" name="category" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear category filter">Clear</button>
                            </div>
                        </div>
                        <noscript>
                            <button type="submit" class="btn btn-sm btn-primary">Apply filters</button>
                        </noscript>
                        @if ($filters['search'] !== '' || $filters['category'] !== '' || $filters['status'] !== '')
                            <a href="{{ route('incidents.index') }}" class="btn btn-sm btn-light border d-inline-flex align-items-center">Clear filters</a>
                        @endif
                    </form>

                    @if ($incidents->isNotEmpty())
                        <div class="table-responsive incident-table-frame">
                            <table class="table align-middle incident-table" role="table" aria-labelledby="incident-reports-title">
                                <thead role="rowgroup">
                                    <tr role="row">
                                        <th scope="col" role="columnheader">Reporter</th>
                                        <th scope="col" role="columnheader">Category</th>
                                        <th scope="col" role="columnheader">Location</th>
                                        <th scope="col" role="columnheader">Status</th>
                                        <th scope="col" role="columnheader">Reported On</th>
                                        <th scope="col" role="columnheader" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody role="rowgroup">
                                    @foreach ($incidents as $incident)
                                        @php($displayStatus = $incident->status === 'Resolved' ? 'Resolved' : 'Pending')
                                        <tr role="row">
                                            <td role="cell">
                                                <span class="incident-cell-label" aria-hidden="true">Reporter</span>
                                                <div class="fw-semibold">{{ $incident->reported_by }}</div>
                                                @if ($incident->consumer)
                                                    <div class="small text-secondary">
                                                        {{ $incident->consumer->account_number }}
                                                        @if ($incident->consumer->full_name !== $incident->reported_by)
                                                            {{ $incident->consumer->account_number ? '•' : '' }} {{ $incident->consumer->full_name }}
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td role="cell">
                                                <span class="incident-cell-label" aria-hidden="true">Category</span>
                                                <div>{{ strtolower(trim($incident->category)) === 'others' ? 'Others' : $incident->category }}</div>
                                                @if ($incident->custom_incident_type)
                                                    <div class="small text-secondary">Incident Type: {{ $incident->custom_incident_type }}</div>
                                                @endif
                                            </td>
                                            <td role="cell">
                                                <span class="incident-cell-label" aria-hidden="true">Location</span>
                                                <div>{{ $incident->location }}</div>
                                                <div class="small text-secondary">
                                                    {{ $incident->purok ?: 'Purok not recorded' }}
                                                    <span aria-hidden="true">·</span>
                                                    {{ $incident->block ? 'Block '.$incident->block : 'Block not recorded' }}
                                                </div>
                                                <div class="small text-secondary">{{ \Illuminate\Support\Str::limit($incident->description, 70) }}</div>
                                            </td>
                                            <td role="cell">
                                                <span class="incident-cell-label" aria-hidden="true">Status</span>
                                                <span id="incident_status_badge_{{ $incident->id }}" class="badge {{ match ($displayStatus) {
                                                    'Resolved' => 'badge-soft-success',
                                                    default => 'badge-soft-warning',
                                                } }}">
                                                    {{ $displayStatus }}
                                                </span>
                                            </td>
                                            <td role="cell">
                                                <span class="incident-cell-label" aria-hidden="true">Reported On</span>
                                                {{ $incident->formatSubmittedAt('M d, Y') }}
                                            </td>
                                            <td role="cell" class="text-end">
                                                @if ($canManageIncidents)
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-light border"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#incidentStatusModal"
                                                        data-incident-id="{{ $incident->id }}"
                                                        data-incident-reporter="{{ $incident->reported_by }}"
                                                        data-incident-category="{{ strtolower(trim($incident->category)) === 'others' ? 'Others' : $incident->category }}"
                                                        data-incident-custom-type="{{ $incident->custom_incident_type }}"
                                                        data-incident-location="{{ $incident->location }}"
                                                        data-incident-description="{{ $incident->description }}"
                                                        data-incident-reported-at="{{ $incident->formatSubmittedAt('M d, Y h:i A') }}"
                                                        data-incident-status="{{ $displayStatus }}"
                                                        data-incident-photo="{{ $incident->photo_path ? route('incidents.photo', $incident) : '' }}"
                                                        data-incident-media-type="{{ $incident->evidence_media_type }}"
                                                    >
                                                        Review
                                                    </button>
                                                @else
                                                    <div class="d-flex justify-content-end gap-1">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-light border"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#incidentStatusModal"
                                                            data-incident-id="{{ $incident->id }}"
                                                            data-incident-reporter="{{ $incident->reported_by }}"
                                                            data-incident-category="{{ strtolower(trim($incident->category)) === 'others' ? 'Others' : $incident->category }}"
                                                            data-incident-custom-type="{{ $incident->custom_incident_type }}"
                                                            data-incident-location="{{ $incident->location }}"
                                                            data-incident-description="{{ $incident->description }}"
                                                            data-incident-reported-at="{{ $incident->formatSubmittedAt('M d, Y h:i A') }}"
                                                            data-incident-status="{{ $displayStatus }}"
                                                            data-incident-photo="{{ $incident->photo_path ? route('incidents.photo', $incident) : '' }}"
                                                            data-incident-media-type="{{ $incident->evidence_media_type }}"
                                                            data-incident-confirmed-at="{{ $incident->confirmed_at?->format('M d, Y h:i A') }}"
                                                            data-incident-resolve-url="{{ route('incidents.confirm', $incident) }}"
                                                            data-incident-can-resolve="{{ $displayStatus === 'Pending' ? '1' : '0' }}"
                                                        >
                                                            View
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="incident-empty text-secondary mb-0">
                            {{ $stats['total'] > 0 ? 'No incident reports match your filters.' : 'No incident reports yet' }}
                        </p>
                    @endif
                    @if ($incidents->isNotEmpty() || $incidents->hasPages())
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                            @if ($incidents->isNotEmpty())
                                <p class="text-secondary small mb-0">Showing {{ $incidents->firstItem() }} to {{ $incidents->lastItem() }} of {{ $incidents->total() }} incident reports</p>
                            @endif
                            {{ $incidents->links() }}
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="incidentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h2 class="modal-title fs-5">{{ $canManageIncidents ? 'Log Incident' : 'Submit Incident Report' }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('incidents.store') }}" enctype="multipart/form-data" id="incidentCreateForm">
                    @csrf
                    <div class="row g-3">
                        @if ($canManageIncidents)
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="consumer_id">Consumer</label>
                                <select class="form-select" id="consumer_id" name="consumer_id">
                                    <option value="">No linked consumer</option>
                                    @foreach ($consumers as $consumer)
                                        <option value="{{ $consumer->id }}" @selected((string) old('consumer_id') === (string) $consumer->id)>{{ $consumer->account_number }} - {{ $consumer->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="reported_by">Reported By</label>
                                <input type="text" class="form-control" id="reported_by" name="reported_by" value="{{ old('reported_by') }}" required>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="category">Category</label>
                            <select class="form-select" id="category" name="category" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6" id="custom_incident_type_wrapper" @if(old('category') !== 'Others') hidden @endif>
                            <label class="form-label fw-semibold" for="custom_incident_type">Please Specify Incident Type</label>
                            <input type="text" class="form-control @error('custom_incident_type') is-invalid @enderror" id="custom_incident_type" name="custom_incident_type" value="{{ old('custom_incident_type') }}" maxlength="255" placeholder="Enter your incident type" @if(old('category') === 'Others') required @endif>
                            @error('custom_incident_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="location">Location</label>
                            <input type="text" class="form-control" id="location" name="location" value="{{ config('service_area.barangay_subdivision') }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="purok">Purok</label>
                            <select class="form-select @error('purok') is-invalid @enderror" id="purok" name="purok" required>
                                <option value="">Select purok</option>
                                @foreach ($puroks as $purok)
                                    <option value="{{ $purok }}" @selected(old('purok', $consumerProfile?->service_purok) === $purok)>{{ $purok }}</option>
                                @endforeach
                            </select>
                            @error('purok')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="block">Block <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="block" name="block" value="{{ old('block', $consumerProfile?->address_block) }}" placeholder="e.g. 4">
                        </div>
                        @if ($canManageIncidents)
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="status">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(old('status', 'Pending') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" aria-describedby="incident-description-hint" required>{{ old('description') }}</textarea>
                            <div class="form-text" id="incident-description-hint">Describe what happened, who is affected, and any health or safety risks. You can write in English, Cebuano, or Filipino.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="photo">Incident Photo or Video</label>
                            <input type="file" class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo" accept="image/jpeg,image/png,video/mp4,video/quicktime,video/x-msvideo,video/webm,.jpg,.jpeg,.png,.mp4,.mov,.avi,.webm">
                            @error('photo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback" id="incident_evidence_error"></div>
                            <div class="form-text">Optional. Upload a JPG, PNG, MP4, MOV, AVI, or WEBM file.</div>
                            <div class="border rounded-4 p-3 mt-3 d-none" id="incident_evidence_preview">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-uppercase text-secondary fw-semibold">Selected File</span>
                                    <button type="button" class="btn btn-sm btn-light border" id="incident_evidence_remove">Remove</button>
                                </div>
                                <img src="" alt="Selected incident photo preview" class="img-fluid rounded-3 d-none" id="incident_evidence_image" style="max-height: 260px; object-fit: contain;">
                                <video controls class="w-100 rounded-3 d-none" id="incident_evidence_video" style="max-height: 320px;"></video>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="incidentCreateForm" class="btn btn-primary">Save Incident</button>
            </div>
        </div>
    </div>
</div>

@if ($canManageIncidents)
    <div class="modal fade" id="incidentStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Incident Review</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Reporter</div><div class="fw-semibold" id="incident_review_reporter">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Reported At</div><div class="fw-semibold" id="incident_review_reported_at">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Category</div><div class="fw-semibold" id="incident_review_category">-</div></div>
                        <div class="col-md-6" id="incident_review_custom_type_wrapper" hidden><div class="small text-uppercase text-secondary fw-semibold mb-1">Incident Type</div><div class="fw-semibold" id="incident_review_custom_type">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Location</div><div class="fw-semibold" id="incident_review_location">-</div></div>
                        <div class="col-12"><div class="small text-uppercase text-secondary fw-semibold mb-1">Description</div><div class="text-secondary" id="incident_review_description">-</div></div>
                        <div class="col-12" id="incident_review_photo_wrapper" style="display:none;">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Incident Photo or Video</div>
                            <img src="" alt="Uploaded incident photo" class="img-fluid rounded-3 mb-2 d-none" id="incident_review_photo_image" style="max-height: 320px; object-fit: contain;">
                            <video controls class="w-100 rounded-3 mb-2 d-none" id="incident_review_photo_video" style="max-height: 320px;"></video>
                            <a href="#" target="_blank" rel="noopener" id="incident_review_photo_link" class="btn btn-outline-secondary btn-sm">Open uploaded file</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="modal fade" id="incidentStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Incident Details</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Reporter</div><div class="fw-semibold" id="incident_review_reporter">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Reported At</div><div class="fw-semibold" id="incident_review_reported_at">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Category</div><div class="fw-semibold" id="incident_review_category">-</div></div>
                        <div class="col-md-6" id="incident_review_custom_type_wrapper" hidden><div class="small text-uppercase text-secondary fw-semibold mb-1">Incident Type</div><div class="fw-semibold" id="incident_review_custom_type">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Location</div><div class="fw-semibold" id="incident_review_location">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Status</div><div class="fw-semibold" id="incident_review_status">-</div></div>
                        <div class="col-md-6" id="incident_review_confirmed_wrapper" style="display:none;"><div class="small text-uppercase text-secondary fw-semibold mb-1">Confirmed At</div><div class="fw-semibold" id="incident_review_confirmed_at">-</div></div>
                        <div class="col-12"><div class="small text-uppercase text-secondary fw-semibold mb-1">Description</div><div class="text-secondary" id="incident_review_description">-</div></div>
                        <div class="col-12" id="incident_review_photo_wrapper" style="display:none;">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Incident Photo or Video</div>
                            <img src="" alt="Uploaded incident photo" class="img-fluid rounded-3 mb-2 d-none" id="incident_review_photo_image" style="max-height: 320px; object-fit: contain;">
                            <video controls class="w-100 rounded-3 mb-2 d-none" id="incident_review_photo_video" style="max-height: 320px;"></video>
                            <a href="#" target="_blank" rel="noopener" id="incident_review_photo_link" class="btn btn-outline-secondary btn-sm">Open uploaded file</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="incidentResolveForm" class="me-auto d-none">
                        @csrf
                        <button type="submit" class="btn btn-success" id="incidentResolveButton"></button>
                    </form>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection

@push('scripts')
    <script>
        const incidentCategoryInput = document.getElementById('category');
        const customIncidentType = document.getElementById('custom_incident_type');
        const customIncidentTypeWrapper = document.getElementById('custom_incident_type_wrapper');
        function updateCustomIncidentType() {
            const isOthers = incidentCategoryInput.value === 'Others';
            customIncidentTypeWrapper.hidden = !isOthers;
            customIncidentType.required = isOthers;
        }
        incidentCategoryInput.addEventListener('change', updateCustomIncidentType);
        updateCustomIncidentType();

        const incidentStatusModal = document.getElementById('incidentStatusModal');

        if (incidentStatusModal) {
            incidentStatusModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const incidentId = button.getAttribute('data-incident-id');
                const incidentReporter = button.getAttribute('data-incident-reporter');
                const incidentCategory = button.getAttribute('data-incident-category');
                const incidentLocation = button.getAttribute('data-incident-location');
                const incidentDescription = button.getAttribute('data-incident-description');
                const incidentReportedAt = button.getAttribute('data-incident-reported-at');
                const incidentStatus = button.getAttribute('data-incident-status');
                const incidentPhoto = button.getAttribute('data-incident-photo');
                const incidentMediaType = button.getAttribute('data-incident-media-type');
                const incidentConfirmedAt = button.getAttribute('data-incident-confirmed-at');
                const incidentResolveUrl = button.getAttribute('data-incident-resolve-url');
                const incidentCanResolve = button.getAttribute('data-incident-can-resolve') === '1';

                document.getElementById('incident_review_reporter').textContent = incidentReporter || '-';
                document.getElementById('incident_review_category').textContent = incidentCategory || '-';
                const customType = button.getAttribute('data-incident-custom-type');
                document.getElementById('incident_review_custom_type').textContent = customType || '-';
                document.getElementById('incident_review_custom_type_wrapper').hidden = !customType;
                document.getElementById('incident_review_location').textContent = incidentLocation || '-';
                document.getElementById('incident_review_description').textContent = incidentDescription || '-';
                document.getElementById('incident_review_reported_at').textContent = incidentReportedAt || '-';
                const reviewStatus = document.getElementById('incident_review_status');
                if (reviewStatus) {
                    reviewStatus.textContent = incidentStatus || '-';
                }
                const reviewConfirmedWrapper = document.getElementById('incident_review_confirmed_wrapper');
                const reviewConfirmedAt = document.getElementById('incident_review_confirmed_at');
                if (reviewConfirmedWrapper && reviewConfirmedAt) {
                    if (incidentConfirmedAt) {
                        reviewConfirmedWrapper.style.display = 'block';
                        reviewConfirmedAt.textContent = incidentConfirmedAt;
                    } else {
                        reviewConfirmedWrapper.style.display = 'none';
                    }
                }
                const reviewPhotoWrapper = document.getElementById('incident_review_photo_wrapper');
                const reviewPhotoLink = document.getElementById('incident_review_photo_link');
                const reviewPhotoImage = document.getElementById('incident_review_photo_image');
                const reviewPhotoVideo = document.getElementById('incident_review_photo_video');
                if (reviewPhotoWrapper && reviewPhotoLink && reviewPhotoImage && reviewPhotoVideo) {
                    if (incidentPhoto) {
                        reviewPhotoWrapper.style.display = 'block';
                        reviewPhotoLink.href = incidentPhoto;
                        if (incidentMediaType === 'video') {
                            reviewPhotoImage.classList.add('d-none');
                            reviewPhotoImage.src = '';
                            reviewPhotoVideo.classList.remove('d-none');
                            reviewPhotoVideo.src = incidentPhoto;
                        } else {
                            reviewPhotoVideo.classList.add('d-none');
                            reviewPhotoVideo.removeAttribute('src');
                            reviewPhotoVideo.load();
                            reviewPhotoImage.classList.remove('d-none');
                            reviewPhotoImage.src = incidentPhoto;
                        }
                    } else {
                        reviewPhotoWrapper.style.display = 'none';
                        reviewPhotoLink.href = '#';
                        reviewPhotoImage.classList.add('d-none');
                        reviewPhotoImage.src = '';
                        reviewPhotoVideo.classList.add('d-none');
                        reviewPhotoVideo.removeAttribute('src');
                        reviewPhotoVideo.load();
                    }
                }

                const incidentResolveForm = document.getElementById('incidentResolveForm');
                if (incidentResolveForm) {
                    incidentResolveForm.action = incidentResolveUrl || '#';
                    incidentResolveForm.dataset.incidentId = incidentId;
                    incidentResolveForm.classList.toggle('d-none', !incidentCanResolve);
                    const incidentResolveButton = document.getElementById('incidentResolveButton');
                    if (incidentResolveButton) {
                        incidentResolveButton.textContent = incidentCanResolve ? 'Mark as Resolved' : '';
                    }
                }
            });
        }
    </script>

    <script>
        const incidentResolveForm = document.getElementById('incidentResolveForm');
        if (incidentResolveForm) {
            incidentResolveForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (!incidentResolveForm.action || incidentResolveForm.action.endsWith('#')) {
                    return;
                }

                const token = incidentResolveForm.querySelector('input[name="_token"]')?.value;
                const response = await fetch(incidentResolveForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                });

                if (!response.ok) {
                    window.location.reload();
                    return;
                }

                const data = await response.json();
                const incidentId = incidentResolveForm.dataset.incidentId;
                const reviewStatus = document.getElementById('incident_review_status');
                const statusBadge = document.getElementById('incident_status_badge_' + incidentId);
                const trigger = document.querySelector('[data-incident-id="' + incidentId + '"][data-incident-resolve-url]');
                const alertHost = document.querySelector('.panel-card .alert')?.parentElement || document.querySelector('.content-wrapper');

                if (reviewStatus) {
                    reviewStatus.textContent = data.status || 'Resolved';
                }

                if (statusBadge) {
                    statusBadge.textContent = data.status || 'Resolved';
                    statusBadge.className = 'badge badge-soft-success';
                }

                if (trigger) {
                    trigger.setAttribute('data-incident-status', data.status || 'Resolved');
                    trigger.setAttribute('data-incident-can-resolve', '0');
                    trigger.setAttribute('data-incident-confirmed-at', data.confirmed_at || '');
                }

                incidentResolveForm.classList.add('d-none');

                if (alertHost) {
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success d-flex align-items-start gap-2';
                    alert.setAttribute('role', 'alert');
                    alert.innerHTML = '<i class="bi bi-check-circle mt-1"></i><div>' + (data.message || 'Incident report marked as resolved.') + '</div>';
                    alertHost.prepend(alert);
                }
            });
        }
    </script>

    <script>
        const incidentEvidenceInput = document.getElementById('photo');
        const incidentEvidencePreview = document.getElementById('incident_evidence_preview');
        const incidentEvidenceImage = document.getElementById('incident_evidence_image');
        const incidentEvidenceVideo = document.getElementById('incident_evidence_video');
        const incidentEvidenceRemove = document.getElementById('incident_evidence_remove');
        const incidentEvidenceError = document.getElementById('incident_evidence_error');
        const incidentCreateForm = document.getElementById('incidentCreateForm');
        let incidentEvidenceUrl = null;

        const imageExtensions = ['jpg', 'jpeg', 'png'];
        const videoExtensions = ['mp4', 'mov', 'avi', 'webm'];

        function resetIncidentEvidencePreview(clearInput = false) {
            if (incidentEvidenceUrl) {
                URL.revokeObjectURL(incidentEvidenceUrl);
                incidentEvidenceUrl = null;
            }
            if (incidentEvidenceImage) {
                incidentEvidenceImage.classList.add('d-none');
                incidentEvidenceImage.src = '';
            }
            if (incidentEvidenceVideo) {
                incidentEvidenceVideo.classList.add('d-none');
                incidentEvidenceVideo.removeAttribute('src');
                incidentEvidenceVideo.load();
            }
            if (incidentEvidencePreview) {
                incidentEvidencePreview.classList.add('d-none');
            }
            if (clearInput && incidentEvidenceInput) {
                incidentEvidenceInput.value = '';
            }
        }

        function showIncidentEvidenceError(message) {
            if (!incidentEvidenceInput || !incidentEvidenceError) {
                return;
            }

            incidentEvidenceInput.classList.add('is-invalid');
            incidentEvidenceError.textContent = message;
            resetIncidentEvidencePreview();
        }

        function clearIncidentEvidenceError() {
            if (!incidentEvidenceInput || !incidentEvidenceError) {
                return;
            }

            incidentEvidenceInput.classList.remove('is-invalid');
            incidentEvidenceError.textContent = '';
        }

        function validateIncidentEvidence(file) {
            const extension = file.name.split('.').pop().toLowerCase();

            if (imageExtensions.includes(extension)) {
                if (file.size > 4 * 1024 * 1024) {
                    return { valid: false, message: 'Images must not exceed 4 MB.' };
                }

                return { valid: true, type: 'image' };
            }

            if (videoExtensions.includes(extension)) {
                if (file.size > 50 * 1024 * 1024) {
                    return { valid: false, message: 'Videos must not exceed 50 MB.' };
                }

                return { valid: true, type: 'video' };
            }

            return { valid: false, message: 'Please upload a supported photo or video file.' };
        }

        if (incidentEvidenceInput) {
            incidentEvidenceInput.addEventListener('change', () => {
                clearIncidentEvidenceError();
                resetIncidentEvidencePreview();
                const file = incidentEvidenceInput.files[0];

                if (!file) {
                    return;
                }

                const result = validateIncidentEvidence(file);
                if (!result.valid) {
                    showIncidentEvidenceError(result.message);
                    return;
                }

                incidentEvidenceUrl = URL.createObjectURL(file);
                incidentEvidencePreview.classList.remove('d-none');

                if (result.type === 'image') {
                    incidentEvidenceImage.src = incidentEvidenceUrl;
                    incidentEvidenceImage.classList.remove('d-none');
                } else {
                    incidentEvidenceVideo.src = incidentEvidenceUrl;
                    incidentEvidenceVideo.classList.remove('d-none');
                }
            });
        }

        if (incidentEvidenceRemove) {
            incidentEvidenceRemove.addEventListener('click', () => {
                clearIncidentEvidenceError();
                resetIncidentEvidencePreview(true);
            });
        }

        if (incidentCreateForm) {
            incidentCreateForm.addEventListener('submit', (event) => {
                const file = incidentEvidenceInput?.files[0];
                if (!file) {
                    return;
                }

                const result = validateIncidentEvidence(file);
                if (!result.valid) {
                    event.preventDefault();
                    showIncidentEvidenceError(result.message);
                }
            });
        }
    </script>

    @if ($errors->any())
        <script>
            const incidentModalElement = document.getElementById('incidentModal');
            if (incidentModalElement) {
                const incidentModal = new bootstrap.Modal(incidentModalElement);
                incidentModal.show();
            }
        </script>
    @endif

@endpush
