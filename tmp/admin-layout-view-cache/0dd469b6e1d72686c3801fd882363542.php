<?php ($activePage = 'incidents'); ?>

<?php $__env->startSection('title', 'Incidents'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .incidents-summary-row {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        margin-top: -1.1rem;
    }

    .incidents-summary-row .dashboard-card {
        min-height: 100%;
    }

    @media (min-width: 1200px) {
        .incidents-summary-row {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app-shell d-lg-flex">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => $activePage], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Incident Reporting</h1>
                    <p>Capture service issues, track response progress, and keep field-action queues visible.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#incidentModal"><i class="bi bi-exclamation-circle me-2"></i><?php echo e($canManageIncidents ? 'Log Incident' : 'Submit New Report'); ?></button>
            </div>

            <?php if(session('success')): ?>
                <div class="alert alert-success d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div><?php echo e(session('success')); ?></div>
                </div>
            <?php endif; ?>

            <?php if($errors->any()): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div><?php echo e($errors->first()); ?></div>
                </div>
            <?php endif; ?>

            <?php if (! ($incidentsTableExists)): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-tools mt-1"></i>
                    <div>Incidents is installed in code but the database table is not created yet. Run <code>php artisan migrate</code> to enable it.</div>
                </div>
            <?php endif; ?>

            <div class="mb-4 incidents-summary-row">
                <div class="dashboard-card p-4"><div class="text-secondary small"><?php echo e($canManageIncidents ? 'Total Reports' : 'My Reports'); ?></div><h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['total'])); ?></h2><span class="badge badge-soft-primary"><?php echo e($canManageIncidents ? 'Response Snapshot' : 'Report Snapshot'); ?></span></div>
                <div class="dashboard-card p-4"><div class="text-secondary small">Pending</div><h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['pending'])); ?></h2><span class="badge badge-soft-warning">Awaiting action</span></div>
                <div class="dashboard-card p-4"><div class="text-secondary small">Resolved</div><h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['resolved'])); ?></h2><span class="badge badge-soft-success">Closed reports</span></div>
            </div>

            <div class="row g-4" data-admin-records-root>
                <div class="col-12">
                    <div class="panel-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2" data-admin-freeze>
                            <div class="section-title mb-0">Submitted Reports</div>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Filter incident reports by status">
                                <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filterStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <a
                                        href="<?php echo e(route('incidents.index', array_filter(['status' => $filterStatus === 'Pending' ? 'Pending' : 'Resolved', 'category' => $filters['category'], 'search' => $filters['search']], fn ($value) => $value !== null && $value !== ''))); ?>"
                                        class="btn <?php echo e($filters['status'] === $filterStatus ? 'btn-primary' : 'btn-light border'); ?>"
                                    ><?php echo e($filterStatus); ?></a>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <a
                                    href="<?php echo e(route('incidents.index', array_filter(['category' => $filters['category'], 'search' => $filters['search']], fn ($value) => $value !== null && $value !== ''))); ?>"
                                    class="btn <?php echo e($filters['status'] === '' ? 'btn-primary' : 'btn-light border'); ?>"
                                >All</a>
                            </div>
                        </div>

                        <form method="GET" action="<?php echo e(route('incidents.index')); ?>" class="row g-2 align-items-end mb-3" data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                            <?php if($filters['status'] !== ''): ?>
                                <input type="hidden" name="status" value="<?php echo e($filters['status']); ?>">
                            <?php endif; ?>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold text-secondary" for="incident_filter_search">Search</label>
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    id="incident_filter_search"
                                    name="search"
                                    value="<?php echo e($filters['search']); ?>"
                                    placeholder="Search reporter, location, description, or account"
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary" for="incident_filter_category">Category</label>
                                <select class="form-select form-select-sm" id="incident_filter_category" name="category">
                                    <option value="">All categories</option>
                                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($category); ?>" <?php if($filters['category'] === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-center gap-2">
                                <noscript>
                                    <button type="submit" class="btn btn-sm btn-primary">Apply filters</button>
                                </noscript>
                                <a href="<?php echo e(route('incidents.index')); ?>" class="btn btn-sm btn-light border">Clear filters</a>
                            </div>
                        </form>

                        <?php if($filters['status'] === 'Pending'): ?>
                            <div class="alert alert-info py-2 px-3 small">
                                <i class="bi bi-list-ol me-1"></i>Pending reports are shown oldest first (First-In, First-Out).
                            </div>
                        <?php elseif($filters['status'] === 'Resolved'): ?>
                            <div class="alert alert-info py-2 px-3 small">
                                <i class="bi bi-clock-history me-1"></i>Resolved reports are shown with the most recently resolved first.
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Reporter</th><th>Category</th><th>Location</th><th>Status</th><th>Reported On</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $incidents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $incident): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php ($displayStatus = $incident->status === 'Resolved' ? 'Resolved' : 'Pending'); ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($incident->reported_by); ?></div>
                                                <?php if($incident->consumer): ?>
                                                    <div class="small text-secondary"><?php echo e($incident->consumer->account_number); ?> • <?php echo e($incident->consumer->full_name); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo e($incident->category); ?></td>
                                            <td>
                                                <div><?php echo e($incident->location); ?></div>
                                                <div class="small text-secondary">
                                                    <?php echo e($incident->purok ?: 'Purok not recorded'); ?>

                                                    <span aria-hidden="true">·</span>
                                                    <?php echo e($incident->block ? 'Block '.$incident->block : 'Block not recorded'); ?>

                                                </div>
                                                <div class="small text-secondary"><?php echo e(\Illuminate\Support\Str::limit($incident->description, 70)); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo e(match ($displayStatus) {
                                                    'Resolved' => 'badge-soft-success',
                                                    default => 'badge-soft-warning',
                                                }); ?>">
                                                    <?php echo e($displayStatus); ?>

                                                </span>
                                            </td>
                                            <td><?php echo e($incident->reported_at->format('M d, Y')); ?></td>
                                            <td class="text-end">
                                                <?php if($canManageIncidents): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-light border"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#incidentStatusModal"
                                                        data-incident-id="<?php echo e($incident->id); ?>"
                                                        data-incident-reporter="<?php echo e($incident->reported_by); ?>"
                                                        data-incident-category="<?php echo e($incident->category); ?>"
                                                        data-incident-location="<?php echo e($incident->location); ?>"
                                                        data-incident-description="<?php echo e($incident->description); ?>"
                                                        data-incident-reported-at="<?php echo e($incident->reported_at->format('M d, Y h:i A')); ?>"
                                                        data-incident-status="<?php echo e($displayStatus); ?>"
                                                        data-incident-photo="<?php echo e($incident->photo_path ? route('incidents.photo', $incident) : ''); ?>"
                                                        data-incident-media-type="<?php echo e($incident->evidence_media_type); ?>"
                                                        data-incident-confirmed-at="<?php echo e($incident->confirmed_at?->format('M d, Y h:i A')); ?>"
                                                    >
                                                        Review
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary ms-1"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#incidentResolveModal"
                                                        data-incident-id="<?php echo e($incident->id); ?>"
                                                        data-incident-heading="<?php echo e($incident->category); ?> &mdash; <?php echo e($incident->location); ?>"
                                                        data-incident-status="<?php echo e($incident->status); ?>"
                                                        data-incident-resolution-notes="<?php echo e($incident->resolution_notes); ?>"
                                                    >
                                                        Update
                                                    </button>
                                                <?php else: ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-light border"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#incidentStatusModal"
                                                        data-incident-id="<?php echo e($incident->id); ?>"
                                                        data-incident-reporter="<?php echo e($incident->reported_by); ?>"
                                                        data-incident-category="<?php echo e($incident->category); ?>"
                                                        data-incident-location="<?php echo e($incident->location); ?>"
                                                        data-incident-description="<?php echo e($incident->description); ?>"
                                                        data-incident-reported-at="<?php echo e($incident->reported_at->format('M d, Y h:i A')); ?>"
                                                        data-incident-status="<?php echo e($displayStatus); ?>"
                                                        data-incident-photo="<?php echo e($incident->photo_path ? route('incidents.photo', $incident) : ''); ?>"
                                                        data-incident-media-type="<?php echo e($incident->evidence_media_type); ?>"
                                                        data-incident-confirmed-at="<?php echo e($incident->confirmed_at?->format('M d, Y h:i A')); ?>"
                                                    >
                                                        View
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="6" class="text-center py-5 text-secondary">No incident reports found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                            <p class="text-secondary mb-0">
                                <?php if($incidents->total() > 0): ?>
                                    Showing <?php echo e($incidents->firstItem()); ?> to <?php echo e($incidents->lastItem()); ?> of <?php echo e($incidents->total()); ?> incident reports
                                <?php else: ?>
                                    No incident reports yet
                                <?php endif; ?>
                            </p>
                            <?php echo e($incidents->links()); ?>

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
                <h2 class="modal-title fs-5"><?php echo e($canManageIncidents ? 'Log Incident' : 'Submit Incident Report'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="<?php echo e(route('incidents.store')); ?>" enctype="multipart/form-data" id="incidentCreateForm">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <?php if($canManageIncidents): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="consumer_id">Consumer</label>
                                <select class="form-select" id="consumer_id" name="consumer_id">
                                    <option value="">No linked consumer</option>
                                    <?php $__currentLoopData = $consumers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $consumer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($consumer->id); ?>" <?php if((string) old('consumer_id') === (string) $consumer->id): echo 'selected'; endif; ?>><?php echo e($consumer->account_number); ?> - <?php echo e($consumer->full_name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="reported_by">Reported By</label>
                                <input type="text" class="form-control" id="reported_by" name="reported_by" value="<?php echo e(old('reported_by')); ?>" required>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="category">Category</label>
                            <select class="form-select" id="category" name="category" required>
                                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($category); ?>" <?php if(old('category') === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="location">Location</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo e(old('location')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="purok">Purok</label>
                            <select class="form-select <?php $__errorArgs = ['purok'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="purok" name="purok" required>
                                <option value="">Select purok</option>
                                <?php $__currentLoopData = $puroks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $purok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($purok); ?>" <?php if(old('purok') === $purok): echo 'selected'; endif; ?>><?php echo e($purok); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <?php $__errorArgs = ['purok'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="block">Block <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="block" name="block" value="<?php echo e(old('block')); ?>" placeholder="e.g. 4">
                        </div>
                        <?php if($canManageIncidents): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="reported_at">Reported At</label>
                                <input type="datetime-local" class="form-control" id="reported_at" name="reported_at" value="<?php echo e(old('reported_at', now()->format('Y-m-d\TH:i'))); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo e(old('description')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="photo">Incident Photo or Video</label>
                            <input type="file" class="form-control <?php $__errorArgs = ['photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="photo" name="photo" accept="image/jpeg,image/png,video/mp4,video/quicktime,video/x-msvideo,video/webm,.jpg,.jpeg,.png,.mp4,.mov,.avi,.webm">
                            <?php $__errorArgs = ['photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="invalid-feedback"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
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

<?php if($canManageIncidents): ?>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="incidentResolveModal" tabindex="-1" aria-hidden="true" aria-labelledby="incidentResolveModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <form method="POST" action="<?php echo e(route('incidents.index')); ?>" id="incidentResolveForm">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="incidentResolveModalLabel">Update Incident Report</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Report</div>
                            <div class="fw-semibold" id="incident_resolve_heading">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="incident_resolve_status">Status</label>
                            <select class="form-select" id="incident_resolve_status" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="For Consumer Confirmation">For Consumer Confirmation</option>
                                <option value="Resolved">Resolved</option>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="incident_resolve_notes">Resolution Notes</label>
                            <textarea class="form-control" id="incident_resolve_notes" name="resolution_notes" rows="4" maxlength="1000" placeholder="Required when marking a report as Resolved."><?php echo e(old('resolution_notes')); ?></textarea>
                            <div class="form-text">The consumer is notified automatically when a report is resolved.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
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
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        const incidentResolveModal = document.getElementById('incidentResolveModal');

        if (incidentResolveModal) {
            incidentResolveModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const incidentId = button.getAttribute('data-incident-id');
                const heading = button.getAttribute('data-incident-heading');
                const status = button.getAttribute('data-incident-status');
                const notes = button.getAttribute('data-incident-resolution-notes');

                document.getElementById('incidentResolveForm').action = `<?php echo e(url('/incidents')); ?>/${incidentId}`;
                document.getElementById('incident_resolve_heading').textContent = heading || '-';
                document.getElementById('incident_resolve_status').value = status || 'Pending';
                document.getElementById('incident_resolve_notes').value = notes || '';
            });
        }

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

                document.getElementById('incident_review_reporter').textContent = incidentReporter || '-';
                document.getElementById('incident_review_category').textContent = incidentCategory || '-';
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

    <?php if($errors->any()): ?>
        <script>
            const incidentModalElement = document.getElementById('incidentModal');
            if (incidentModalElement) {
                const incidentModal = new bootstrap.Modal(incidentModalElement);
                incidentModal.show();
            }
        </script>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/incidents/index.blade.php ENDPATH**/ ?>