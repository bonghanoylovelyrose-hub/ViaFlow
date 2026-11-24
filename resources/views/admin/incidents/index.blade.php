@extends('layouts.app')

@php($activePage = 'incidents')

@section('title', 'Incidents')

@push('styles')
<style>
    #incidentModal .modal-body { padding: 1.1rem 1.4rem; }
    #incidentModal .incident-section-title { color: var(--vf-navy); font-size: .95rem; font-weight: 800; margin: .35rem 0 .15rem; }
    #incidentModal .incident-details { background: #f4fafb; border: 1px solid var(--vf-light); border-radius: .8rem; padding: .8rem; }
    #incidentModal .incident-detail-label { color: var(--vf-navy); font-size: .72rem; font-weight: 700; }
    #incidentModal .incident-detail-value { color: var(--vf-navy); font-size: .86rem; overflow-wrap: anywhere; }
    #incidentModal .meter-search-results { max-height: 12rem; overflow-y: auto; border-radius: .75rem; }
    #incidentModal .meter-search-results button { display: block; width: 100%; padding: .6rem .8rem; text-align: left; background: #fff; border: 0; border-bottom: 1px solid var(--vf-light); color: var(--vf-navy); }
    #incidentModal .meter-search-results button:hover, #incidentModal .meter-search-results button:focus-visible { background: var(--vf-background); }
    .incident-priority-urgent { background: #ffedd5 !important; color: #9a3412 !important; }
    .incident-priority-non-urgent { background: #dbeafe !important; color: #1e40af !important; }
    .incident-priority-needs-review { background: #e5e7eb !important; color: #374151 !important; }
    @media (max-width: 575.98px) { #incidentModal .modal-body { padding: 1rem; } }
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
                    <h1>Incident Reporting</h1>
                    <p>Capture service issues, track response progress, and keep field-action queues visible.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#incidentModal"><i class="bi bi-exclamation-circle me-2"></i>{{ $canManageIncidents ? 'Log Incident' : 'Submit New Report' }}</button>
            </div>

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @unless ($incidentsTableExists)
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-tools mt-1"></i>
                    <div>Incidents is installed in code but the database table is not created yet. Run <code>php artisan migrate</code> to enable it.</div>
                </div>
            @endunless

            <div class="row g-4" data-admin-records-root>
                <div class="col-12">
                    <div class="panel-card p-4">
                        <div class="mb-3" data-admin-freeze>
                            <div class="section-title mb-2">Submitted Reports</div>
                            <nav class="d-flex align-items-center gap-2" aria-label="Filter incident reports by status">
                                @foreach (['', ...$statuses] as $filterStatus)
                                    <a
                                        href="{{ route('incidents.index', array_filter(['status' => $filterStatus] + $filters, fn ($value) => $value !== '')) }}"
                                        class="btn btn-sm {{ $filters['status'] === $filterStatus ? 'btn-primary' : 'btn-light border' }}"
                                        @if ($filters['status'] === $filterStatus) aria-current="page" @endif
                                    >{{ $filterStatus === '' ? 'All' : $filterStatus }}</a>
                                @endforeach
                            </nav>
                        </div>

                        <form method="GET" action="{{ route('incidents.index') }}" class="row g-2 align-items-end mb-3" data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                            @if ($filters['status'] !== '')
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            @endif
                            <noscript>
                                <div class="col-12 order-last">
                                    <button type="submit" class="btn btn-sm btn-primary">Apply filters</button>
                                </div>
                            </noscript>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary" for="incident_filter_search">Search</label>
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    id="incident_filter_search"
                                    name="search"
                                    value="{{ $filters['search'] }}"
                                    placeholder="Search reporter, location, description, or account"
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary" for="incident_filter_category">Category</label>
                                <div class="d-flex align-items-center gap-2">
                                    <select style="min-width:0;flex:1 1 0" class="form-select form-select-sm" id="incident_filter_category" name="category">
                                        <option value="">All Categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" name="category" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear category filter">Clear</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary" for="incident_filter_priority">Priority</label>
                                <div class="d-flex align-items-center gap-2">
                                    <select style="min-width:0;flex:1 1 0" class="form-select form-select-sm" id="incident_filter_priority" name="priority">
                                        <option value="">All Priorities</option>
                                        @foreach ($priorities as $priority)
                                            <option value="{{ $priority }}" @selected($filters['priority'] === $priority)>{{ $priority }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" name="priority" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear priority filter">Clear</button>
                                </div>
                            </div>
                        </form>

                        @if ($filters['status'] === 'Pending')
                            <div class="alert alert-info py-2 px-3 small">
                                <i class="bi bi-list-ol me-1"></i>Pending reports are sorted by priority, then oldest first within each priority.
                            </div>
                        @elseif ($filters['status'] === 'Resolved')
                            <div class="alert alert-info py-2 px-3 small">
                                <i class="bi bi-clock-history me-1"></i>Resolved reports are sorted by priority, then most recently resolved within each priority.
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Reporter</th><th>Category</th><th>Location</th><th>Priority</th><th>Status</th><th>Reported On</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                    @forelse ($incidents as $incident)
                                        @php($displayStatus = $incident->isResolved() ? 'Resolved' : 'Pending')
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $incident->reported_by }}</div>
                                                @if ($incident->consumer)
                                                    <div class="small text-secondary">{{ $incident->consumer->account_number }} • {{ $incident->consumer->full_name }}</div>
                                                @endif
                                            </td>
                                            <td><div>{{ strtolower(trim($incident->category)) === 'others' ? 'Others' : $incident->category }}</div>
                                                @if ($incident->custom_incident_type)
                                                    <div class="small text-secondary">Incident Type: {{ $incident->custom_incident_type }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $incident->location }}</div>
                                                <div class="small text-secondary">
                                                    {{ $incident->purok ?: 'Purok not recorded' }}
                                                    <span aria-hidden="true">·</span>
                                                    {{ $incident->block ? 'Block '.$incident->block : 'Block not recorded' }}
                                                </div>
                                                <div class="small text-secondary">{{ \Illuminate\Support\Str::limit($incident->description, 70) }}</div>
                                            </td>
                                            <td><span class="badge incident-priority-{{ \Illuminate\Support\Str::slug($incident->priority) }}">{{ $incident->priority }}</span></td>
                                            <td>
                                                <span class="badge {{ match ($displayStatus) {
                                                    'Resolved' => 'badge-soft-success',
                                                    default => 'badge-soft-warning',
                                                } }}">
                                                    {{ $displayStatus }}
                                                </span>
                                            </td>
                                            <td>{{ $incident->formatSubmittedAt('M d, Y') }}</td>
                                            <td class="text-end">
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
                                                        data-incident-confirmed-at="{{ $incident->confirmed_at?->format('M d, Y h:i A') }}"
                                                        data-priority-url="{{ route('incidents.priority.update', $incident) }}"
                                                        data-priority="{{ $incident->priority }}"
                                                        data-automatic-priority="{{ $incident->automatic_priority }}"
                                                        data-classification-reason="{{ $incident->classification_reason }}"
                                                        data-priority-review-note="{{ $incident->priority_review_note }}"
                                                        data-priority-reviewed-at="{{ $incident->priority_reviewed_at?->format('M d, Y h:i A') }}"
                                                        data-priority-reviewer="{{ $incident->priorityReviewer?->name }}"
                                                    >
                                                        Review
                                                    </button>
                                                @else
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
                                                    >
                                                        View
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center py-5 text-secondary">No incident reports found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                            <p class="text-secondary mb-0">
                                @if ($incidents->total() > 0)
                                    Showing {{ $incidents->firstItem() }} to {{ $incidents->lastItem() }} of {{ $incidents->total() }} incident reports
                                @else
                                    No incident reports yet
                                @endif
                            </p>
                            {{ $incidents->links() }}
                        </div>
                    </div>
                </div>
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
                        <div class="col-12"><h3 class="incident-section-title">Consumer Information</h3></div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="incident_meter_search">Search Meter Number</label>
                            <input type="search" class="form-control" id="incident_meter_search" name="meter_number" value="{{ old('meter_number') }}" placeholder="Enter a meter number" autocomplete="off" aria-describedby="incident_meter_status" aria-controls="incident_meter_results" required>
                            <input type="hidden" id="consumer_id" name="consumer_id" value="{{ old('consumer_id') }}">
                            <div id="incident_meter_status" class="form-text" role="status">Search and select a meter number.</div>
                            <div id="incident_meter_results" class="meter-search-results border mt-2" hidden></div>
                        </div>
                        <div class="col-12" id="incident_consumer_details" hidden>
                            <div class="incident-details">
                                <div class="fw-semibold mb-2">Consumer Details</div>
                                <div class="row g-2">
                                    <div class="col-sm-6"><div class="incident-detail-label">Consumer Name</div><div class="incident-detail-value" id="incident_consumer_name"></div></div>
                                    <div class="col-sm-6"><div class="incident-detail-label">Account Number</div><div class="incident-detail-value" id="incident_consumer_account"></div></div>
                                    <div class="col-sm-6"><div class="incident-detail-label">Meter Number</div><div class="incident-detail-value" id="incident_consumer_meter"></div></div>
                                    <div class="col-sm-6"><div class="incident-detail-label">Purok and Block</div><div class="incident-detail-value" id="incident_consumer_area"></div></div>
                                    <div class="col-12"><div class="incident-detail-label">Service Address</div><div class="incident-detail-value" id="incident_consumer_address"></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><h3 class="incident-section-title">Incident Information</h3></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="category">Incident Category</label>
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
                            <input type="text" class="form-control" id="location" name="location" value="{{ old('location') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="reported_by">Reported By</label>
                            <input type="text" class="form-control" id="reported_by" name="reported_by" value="{{ old('reported_by', auth()->user()->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="purok">Purok</label>
                            <select class="form-select @error('purok') is-invalid @enderror" id="purok" name="purok" required>
                                <option value="">Select purok</option>
                                @foreach ($puroks as $purok)
                                    <option value="{{ $purok }}" @selected(old('purok') === $purok)>{{ $purok }}</option>
                                @endforeach
                            </select>
                            @error('purok')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="block">Block <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="block" name="block" value="{{ old('block') }}" placeholder="e.g. 4">
                        </div>
                        <div class="col-12"><h3 class="incident-section-title">Incident Description</h3></div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="description">Description <span class="text-secondary fw-normal">(required)</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required>{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="photo">Incident Photo or Video <span class="text-secondary fw-normal">(optional)</span></label>
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
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
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
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Status</div><div class="fw-semibold" id="incident_review_status">-</div></div>
                        <div class="col-md-6" id="incident_review_confirmed_wrapper" style="display:none;"><div class="small text-uppercase text-secondary fw-semibold mb-1">Confirmed At</div><div class="fw-semibold" id="incident_review_confirmed_at">-</div></div>
                        <div class="col-12"><div class="small text-uppercase text-secondary fw-semibold mb-1">Description</div><div class="text-secondary" id="incident_review_description">-</div></div>
                        <div class="col-12" id="incident_review_photo_wrapper" style="display:none;">
                            <div class="small text-uppercase text-secondary fw-semibold mb-2">Incident Photo or Video</div>
                            <div class="border rounded-4 p-2 bg-light-subtle mb-2 text-center">
                                <img
                                    src=""
                                    alt="Uploaded incident photo"
                                    id="incident_review_photo_image"
                                    class="img-fluid rounded-3"
                                    style="max-height: 320px; object-fit: contain;"
                                >
                                <video
                                    controls
                                    id="incident_review_photo_video"
                                    class="w-100 rounded-3 d-none"
                                    style="max-height: 320px;"
                                ></video>
                            </div>
                            <a href="#" target="_blank" rel="noopener" id="incident_review_photo_link" class="btn btn-outline-secondary btn-sm">Open uploaded file</a>
                        </div>
                    </div>
                    <form id="incidentPriorityForm" method="POST" class="border rounded-3 p-3">
                        @csrf
                        @method('PATCH')
                        <h3 class="fs-6">Priority Review</h3>
                        <p class="small mb-1">Automatic priority <strong class="badge" id="incident_automatic_priority"></strong></p>
                        <p class="small text-secondary mb-2" id="incident_classification_reason"></p>
                        <p class="small text-secondary mb-2" id="incident_priority_reviewed"></p>
                        <p class="alert alert-secondary small py-2 mb-2" id="incident_priority_locked_message" hidden>{{ \App\Models\Incident::PRIORITY_REVIEW_LOCKED_MESSAGE }}</p>
                        <fieldset id="incident_priority_fields" disabled>
                            <label class="form-label" for="incident_review_priority" id="incident_review_priority_label">Priority</label>
                            <select class="form-select" id="incident_review_priority" name="priority" required>
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority }}">{{ $priority }}</option>
                                @endforeach
                            </select>
                            <label class="form-label mt-2" for="incident_priority_review_note">Review reason</label>
                            <textarea class="form-control" name="priority_review_note" id="incident_priority_review_note" aria-describedby="incident_priority_review_hint" rows="2" maxlength="1000"></textarea>
                            <div class="form-text" id="incident_priority_review_hint">Required when changing the priority or overriding the automatic classification.</div>
                            <button class="btn btn-primary btn-sm mt-3" type="submit">Save Priority Review</button>
                        </fieldset>
                    </form>
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
                        <div class="col-12"><div class="small text-uppercase text-secondary fw-semibold mb-1">Description</div><div class="text-secondary" id="incident_review_description">-</div></div>
                        <div class="col-12" id="incident_review_photo_wrapper" style="display:none;">
                            <div class="small text-uppercase text-secondary fw-semibold mb-2">Incident Photo or Video</div>
                            <div class="border rounded-4 p-2 bg-light-subtle mb-2 text-center">
                                <img
                                    src=""
                                    alt="Uploaded incident photo"
                                    id="incident_review_photo_image"
                                    class="img-fluid rounded-3"
                                    style="max-height: 320px; object-fit: contain;"
                                >
                                <video
                                    controls
                                    id="incident_review_photo_video"
                                    class="w-100 rounded-3 d-none"
                                    style="max-height: 320px;"
                                ></video>
                            </div>
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
@endif
@endsection

@push('scripts')
    <script>
        (function () {
            const search = document.getElementById('incident_meter_search');
            const consumerId = document.getElementById('consumer_id');
            const results = document.getElementById('incident_meter_results');
            const status = document.getElementById('incident_meter_status');
            const details = document.getElementById('incident_consumer_details');
            const restored = @json($selectedIncidentConsumer);
            let timer;
            let request;

            function showConsumer(consumer, prefillLocation) {
                search.value = consumer.meter_number;
                consumerId.value = consumer.id;
                search.setCustomValidity('');
                document.getElementById('incident_consumer_name').textContent = consumer.full_name || '—';
                document.getElementById('incident_consumer_account').textContent = consumer.account_number || 'Not assigned';
                document.getElementById('incident_consumer_meter').textContent = consumer.meter_number;
                document.getElementById('incident_consumer_address').textContent = consumer.service_address || 'Not recorded';
                document.getElementById('incident_consumer_area').textContent = [consumer.service_purok, consumer.address_block].filter(Boolean).join(' · ') || 'Not recorded';
                details.hidden = false;
                results.hidden = true;
                results.replaceChildren();
                status.textContent = 'Meter selected.';
                if (prefillLocation) {
                    document.getElementById('location').value = consumer.service_address || '';
                    const purok = document.getElementById('purok');
                    purok.value = Array.from(purok.options).some(option => option.value === consumer.service_purok) ? consumer.service_purok : '';
                    document.getElementById('block').value = consumer.address_block || '';
                }
            }

            async function findMeters(term) {
                const controller = new AbortController();
                request = controller;
                status.textContent = 'Searching meter numbers…';
                try {
                    const url = new URL(@json(route('incidents.meter-search')), window.location.origin);
                    url.searchParams.set('q', term);
                    const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('Search failed');
                    const matches = await response.json();
                    if (request !== controller) return;
                    results.replaceChildren();
                    matches.forEach(function (consumer) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = consumer.meter_number + ' — ' + consumer.full_name;
                        button.addEventListener('click', function () { showConsumer(consumer, true); });
                        results.appendChild(button);
                    });
                    results.hidden = matches.length === 0;
                    status.textContent = matches.length ? 'Select a meter number from the results.' : 'No meter number found.';
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        results.hidden = true;
                        status.textContent = 'Unable to search meter numbers. Please try again.';
                    }
                }
            }

            search.addEventListener('input', function () {
                window.clearTimeout(timer);
                if (request) request.abort();
                consumerId.value = '';
                details.hidden = true;
                results.hidden = true;
                results.replaceChildren();
                search.setCustomValidity('Select a meter number from the search results.');
                const term = search.value.trim();
                status.textContent = term ? 'Searching meter numbers…' : 'Search and select a meter number.';
                if (term) timer = window.setTimeout(() => findMeters(term), 250);
            });

            if (restored) showConsumer(restored, false);
            else {
                consumerId.value = '';
                search.setCustomValidity('Select a meter number from the search results.');
            }
        })();
    </script>
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
        const priorityForm = document.getElementById('incidentPriorityForm');
        const priorityFields = document.getElementById('incident_priority_fields');
        const prioritySelect = document.getElementById('incident_review_priority');
        const priorityNote = document.getElementById('incident_priority_review_note');
        const updatePriorityNoteRequirement = () => {
            priorityNote.required = !priorityFields.disabled && (
                prioritySelect.value !== priorityForm.dataset.currentPriority
                || prioritySelect.value !== priorityForm.dataset.automaticPriority
            );
        };
        prioritySelect?.addEventListener('change', updatePriorityNoteRequirement);
        priorityForm?.addEventListener('submit', (event) => {
            if (priorityFields.disabled) event.preventDefault();
        });

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
                if (priorityForm) {
                    const {priorityUrl, priority, automaticPriority, classificationReason, priorityReviewNote,
                        priorityReviewedAt, priorityReviewer} = button.dataset;
                    const priorityLocked = incidentStatus === @js(\App\Models\Incident::STATUS_RESOLVED);
                    priorityForm.action = priorityUrl;
                    priorityForm.dataset.currentPriority = priority;
                    priorityForm.dataset.automaticPriority = automaticPriority;
                    priorityFields.disabled = priorityLocked;
                    document.getElementById('incident_priority_locked_message').hidden = !priorityLocked;
                    document.getElementById('incident_review_priority_label').textContent = priorityLocked ? 'Final priority' : 'Priority';
                    document.getElementById('incident_priority_review_hint').hidden = priorityLocked;
                    prioritySelect.value = priority;
                    priorityNote.value = priorityReviewNote;
                    updatePriorityNoteRequirement();
                    priorityNote.placeholder = priorityLocked ? 'No review reason recorded.' : '';
                    const automaticPriorityBadge = document.getElementById('incident_automatic_priority');
                    automaticPriorityBadge.textContent = automaticPriority;
                    automaticPriorityBadge.className = 'badge incident-priority-' + automaticPriority.toLowerCase().replaceAll(' ', '-');
                    document.getElementById('incident_classification_reason').textContent = classificationReason;
                    document.getElementById('incident_priority_reviewed').textContent = priorityReviewedAt
                        ? 'Reviewed '+priorityReviewedAt+(priorityReviewer ? ' by '+priorityReviewer : '')
                        : (priorityLocked ? 'No admin priority review recorded.' : 'Awaiting admin review');
                }

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
