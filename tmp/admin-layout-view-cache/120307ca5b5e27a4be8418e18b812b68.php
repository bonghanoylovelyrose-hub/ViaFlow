<?php $__env->startPush('styles'); ?>
<style>
    .meter-summary-row {
        margin-top: -1.1rem;
    }

    .meter-queue-card {
        border: 1px solid rgba(219, 230, 238, 0.75);
        border-radius: 1rem;
        overflow: hidden;
        background: rgba(250, 252, 253, 0.82);
    }

    .meter-queue-scroll {
        max-height: 26rem;
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
        color: #5f6b76;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom-color: rgba(219, 230, 238, 0.82);
    }

    .meter-queue-card tbody td {
        border-bottom-color: rgba(219, 230, 238, 0.45);
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
        overflow: hidden;
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
        grid-template-columns: minmax(260px, 1.3fr) minmax(180px, 0.8fr) auto;
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
        border: 1px solid rgba(219, 230, 238, 0.75);
        border-radius: 1rem;
        overflow: hidden;
        background: rgba(250, 252, 253, 0.82);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55);
    }

    .meter-records-table .table {
        margin-bottom: 0;
    }

    .meter-records-table thead th {
        background: rgba(244, 248, 251, 0.96);
        color: #5f6b76;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom-color: rgba(219, 230, 238, 0.82);
    }

    .meter-records-table tbody td {
        border-bottom-color: rgba(219, 230, 238, 0.45);
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
        color: #1f2a37;
        border-radius: 1.25rem;
    }

    .meter-form-column {
        padding-right: 1.5rem;
        border-right: 1px solid rgba(219, 230, 238, 0.9);
    }

    .meter-modal-input {
        background: #ffffff !important;
        border: 1px solid rgba(15, 110, 140, 0.16) !important;
        color: #1f2a37 !important;
        border-radius: 0.6rem !important;
        padding: 0.8rem 1rem !important;
        min-height: 50px !important;
        font-size: 1rem !important;
    }

    .meter-modal-input:focus {
        border-color: #0f6e8c !important;
        box-shadow: 0 0 0 0.2rem rgba(15, 110, 140, 0.12) !important;
    }

    .meter-modal-label {
        color: var(--wb-muted);
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }

    .meter-focus-card {
        border: 1px solid rgba(219, 230, 238, 0.95);
        border-radius: 1.25rem;
        background: linear-gradient(180deg, rgba(15, 110, 140, 0.08), rgba(255, 255, 255, 0.96));
    }

    .meter-reading-value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }



    .meter-search-result {
        border: 1px solid rgba(72, 183, 168, 0.22);
        border-radius: 1rem;
        background: rgba(72, 183, 168, 0.08);
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
        border-radius: 2.5rem;
        padding: 3rem 4.5rem;
        text-align: center;
        box-shadow: 0 2rem 5rem rgba(0, 0, 0, 0.25);
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
        word-break: break-all;
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 0;
    }

    .reading-value .reading-whole {
        color: #1f2a37;
        display: flex;
    }

    .reading-value .digit-slot {
        display: inline-block;
        width: 1ch;
        text-align: center;
        cursor: text;
        outline: none;
        border-radius: 0.08em;
        transition: background 0.1s;
        position: relative;
    }

    .reading-value .digit-slot:hover {
        background: rgba(15, 110, 140, 0.08);
    }

    .reading-value .decimal-sep {
        color: #1f2a37;
        margin: 0 0.03em;
        pointer-events: none;
        user-select: none;
    }

    .reading-value .reading-decimal {
        color: #dc3545;
        display: flex;
    }

    .reading-value .reading-decimal .digit-slot {
        color: #dc3545;
    }

    .reading-value .reading-decimal {
        color: #dc3545;
    }

    .reading-value.empty .reading-whole,
    .reading-value.empty .decimal-sep,
    .reading-value.empty .reading-decimal {
        color: #b0bcc7;
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
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app-shell d-lg-flex">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => $activePage], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Meter Reading Workspace</h1>
                    <p>Capture a meter photo, let the system detect the digits, and turn it into a billing-ready reading.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#meterReadingModal">
                    Read Meter
                </button>
            </div>

            <div class="row g-4 mb-4 meter-summary-row">
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Assigned Accounts</div>
                        <h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['assigned_accounts'])); ?></h2>
                        <span class="badge badge-soft-primary">Today's queue</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Completed Reads</div>
                        <h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['completed_reads'])); ?></h2>
                        <span class="badge badge-soft-success">Encoded in <?php echo e($filters['month_label']); ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-4 h-100">
                        <div class="text-secondary small">Pending Uploads</div>
                        <h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['pending_uploads'])); ?></h2>
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
                                Showing pending and done readings for <?php echo e($filters['month_label']); ?>.
                            </div>
                        </div>
                        <form method="GET" action="<?php echo e(route('meter-readings.index')); ?>" class="d-flex flex-wrap align-items-center gap-2" data-auto-filter data-auto-filter-debounce="0">
                            <?php if($filters['search'] !== ''): ?>
                                <input type="hidden" name="search" value="<?php echo e($filters['search']); ?>">
                            <?php endif; ?>
                            <?php if($filters['status'] !== ''): ?>
                                <input type="hidden" name="status" value="<?php echo e($filters['status']); ?>">
                            <?php endif; ?>
                            <label for="meter_filter_month" class="form-label small text-secondary mb-0 text-nowrap">Billing month</label>
                            <input
                                type="month"
                                class="form-control form-control-sm"
                                id="meter_filter_month"
                                name="month"
                                value="<?php echo e($filters['month']); ?>"
                            >
                            <?php if($filters['month'] !== now()->format('Y-m')): ?>
                                <a
                                    href="<?php echo e(route('meter-readings.index', array_filter(['search' => $filters['search'], 'status' => $filters['status']]))); ?>"
                                    class="btn btn-sm btn-light border"
                                >
                                    Current month
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-6">
                            <div class="panel-card p-4 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="section-title mb-0">Pending Reading Queue</div>
                                        <div class="small text-secondary mt-1">
                                            <?php echo e($filters['month_label']); ?> cycle &bull; newest verified first.
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-warning"><?php echo e($pendingConsumers->count()); ?></span>
                                </div>
                                <div class="meter-queue-card meter-queue-scroll table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead><tr><th>Consumer</th><th>Meter</th><th>Verified</th></tr></thead>
                                        <tbody>
                                            <?php $__empty_1 = true; $__currentLoopData = $pendingConsumers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $consumer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo e($consumer->full_name); ?></div>
                                                        <div class="small text-secondary"><?php echo e($consumer->account_number); ?></div>
                                                    </td>
                                                    <td><?php echo e($consumer->meter_number); ?></td>
                                                    <td class="small text-secondary"><?php echo e($consumer->verified_at?->format('M d, Y g:i A') ?? '—'); ?></td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <tr><td colspan="3" class="text-center py-4 text-secondary">No pending consumers for <?php echo e($filters['month_label']); ?>.</td></tr>
                                            <?php endif; ?>
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
                                            <?php echo e($filters['month_label']); ?> completions &bull; newest encoded first.
                                        </div>
                                    </div>
                                    <span class="badge badge-soft-success"><?php echo e($completedReadings->count()); ?></span>
                                </div>
                                <div class="meter-queue-card meter-queue-scroll table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead><tr><th>Consumer</th><th>Current</th></tr></thead>
                                        <tbody>
                                            <?php $__empty_1 = true; $__currentLoopData = $completedReadings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo e($reading->consumer->full_name); ?></div>
                                                        <div class="small text-secondary">
                                                            Encoded <?php echo e($reading->created_at->format('M d, Y g:i A')); ?>

                                                        </div>
                                                    </td>
                                                    <td><?php echo e(number_format((float) $reading->current_reading, 2)); ?></td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <tr><td colspan="2" class="text-center py-4 text-secondary">No completed readings for <?php echo e($filters['month_label']); ?>.</td></tr>
                                            <?php endif; ?>
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

                        <form method="GET" action="<?php echo e(route('meter-readings.index')); ?>" class="meter-filter-form" data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                            <input type="hidden" name="month" value="<?php echo e($filters['month']); ?>">
                            <input
                                type="text"
                                class="form-control"
                                id="meter_filter_search"
                                name="search"
                                value="<?php echo e($filters['search']); ?>"
                                placeholder="Search consumer, account, or meter number"
                            >
                            <select class="form-select" id="meter_filter_status" name="status">
                                <option value="">All statuses</option>
                                <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status); ?>" <?php if($filters['status'] === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <div class="d-flex align-items-center gap-2">
                                <noscript>
                                    <button type="submit" class="btn btn-primary">Apply filters</button>
                                </noscript>
                                <a href="<?php echo e(route('meter-readings.index')); ?>" class="btn btn-light border">Clear</a>
                            </div>
                        </form>

                        <?php if(session('success')): ?>
                            <div class="alert alert-success d-flex align-items-start gap-2 mb-0" role="alert">
                                <i class="bi bi-check-circle mt-1"></i>
                                <div><?php echo e(session('success')); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if($errors->any()): ?>
                            <div class="alert alert-danger d-flex align-items-start gap-2 mb-0" role="alert">
                                <i class="bi bi-exclamation-circle mt-1"></i>
                                <div><?php echo e($errors->first()); ?></div>
                            </div>
                        <?php endif; ?>



                        <div class="meter-records-table table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Consumer</th><th>Read Date</th><th>Previous</th><th>Current</th><th>Consumption</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $readings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($reading->consumer->full_name); ?></div>
                                                <div class="small text-secondary">
                                                    <?php echo e($reading->consumer->account_number); ?> &bull;
                                                    MT <?php echo e($reading->meter_number ?? $reading->consumer->meter_number); ?>

                                                    <?php if($reading->meter_number && $reading->meter_number !== $reading->consumer->meter_number): ?>
                                                        <span class="badge badge-soft-secondary ms-1" title="This reading was taken from a meter that has since been replaced.">replaced meter</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if($canManageAssignments): ?>
                                                    <div class="small text-secondary">Reader: <?php echo e($reading->reader->name); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?php echo e($reading->reading_date->format('M d, Y')); ?></div>
                                                <div class="small text-secondary">Encoded <?php echo e($reading->created_at->format('M d, Y g:i A')); ?></div>
                                            </td>
                                            <td><?php echo e(number_format((float) $reading->previous_reading, 2)); ?></td>
                                            <td><?php echo e(number_format((float) $reading->current_reading, 2)); ?></td>
                                            <td><?php echo e(number_format((float) $reading->consumption, 2)); ?></td>
                                            <td>
                                                <span class="badge <?php echo e(match ($reading->status) {
                                                    'Encoded' => 'badge-soft-success',
                                                    'Needs Revisit' => 'badge-soft-danger',
                                                    default => 'badge-soft-warning',
                                                }); ?>">
                                                    <?php echo e($reading->status); ?>

                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="6" class="text-center py-5 text-secondary">No meter readings recorded yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="meter-records-pagination">
                            <?php echo e($readings->links()); ?>

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
                <form method="POST" action="<?php echo e(route('meter-readings.store')); ?>" id="meterReadingCreateForm" novalidate>
                    <?php echo csrf_field(); ?>
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
                                    <input type="text" class="form-control meter-modal-input" id="meter_number" name="meter_number" value="<?php echo e(old('meter_number')); ?>" placeholder="Enter Meter ID" required autocomplete="off">
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
                                    <dt>Applicable Water Rate</dt>
                                    <dd id="result_unit_rate">-</dd>
                                </dl>
                            </div>

                            <input type="hidden" id="meter_consumer_id" name="consumer_id" value="">

                            <div class="mb-3">
                                <label class="form-label meter-modal-label" for="current_reading">Current Reading</label>
                                <input type="text" class="form-control meter-modal-input" id="current_reading" name="current_reading" inputmode="decimal" value="<?php echo e(old('current_reading')); ?>" placeholder="Enter current meter reading" required autocomplete="off">
                                <div class="invalid-feedback" id="current_reading_feedback">Please enter a valid number.</div>
                            </div>

                            <?php if($canManageAssignments): ?>
                                <div class="mb-3">
                                    <label class="form-label meter-modal-label" for="reader_id">Staff</label>
                                    <select class="form-select meter-modal-input" id="reader_id" name="reader_id" required>
                                        <option value="">Assign reader</option>
                                        <?php $__currentLoopData = $readers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reader): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($reader->id); ?>" <?php if((string) old('reader_id') === (string) $reader->id): echo 'selected'; endif; ?>><?php echo e($reader->name); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label meter-modal-label" for="reading_date">Reading Date</label>
                                <input type="date" class="form-control meter-modal-input" id="reading_date" name="reading_date" value="<?php echo e(old('reading_date', now()->toDateString())); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label meter-modal-label" for="remarks">Remarks</label>
                                <input type="text" class="form-control meter-modal-input" id="remarks" name="remarks" value="<?php echo e(old('remarks')); ?>" placeholder="Optional remarks for unclear, obstructed, or revisited meters">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3" id="saveMeterReadingBtn">Save Meter Reading</button>
                        </div>
                        <div class="col-lg-6">
                            <div class="meter-focus-card p-4 h-100">
                                <div class="text-secondary small text-uppercase fw-semibold mb-3">Billing Preview</div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Previous Meter Reading</span>
                                    <strong id="preview_previous_reading">0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Current Meter Reading</span>
                                    <strong id="preview_current_reading">0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Monthly Consumption</span>
                                    <strong><span id="preview_consumption">0.00</span> m3</strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Applicable Water Rate</span>
                                    <strong>P <span id="preview_unit_rate">0.00</span>/m3</strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Base Charge</span>
                                    <strong>P <span id="preview_base_charge">0.00</span></strong>
                                </div>
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>Previous Outstanding Balance</span>
                                    <strong>P <span id="preview_previous_balance">0.00</span></strong>
                                </div>
                                <div class="d-flex justify-content-between pt-3 fs-5">
                                    <span class="fw-semibold">Estimated Total Due</span>
                                    <strong class="text-primary">P <span id="preview_total_bill">0.00</span></strong>
                                </div>
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
                            <button type="button" class="btn btn-primary btn-lg px-5 mt-4 rounded-pill fw-bold" id="donePreviewBtn">Done</button>
                        </div>
                        <div class="reading-preview-error" id="readingPreviewError" role="alert"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="meterReadingSubmitConfirmModal" tabindex="-1" aria-labelledby="meterReadingSubmitConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; background: #ffffff; color: #1f2a37;">
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
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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
        const resultUnitRate = document.getElementById('result_unit_rate');
        const meterConsumerId = document.getElementById('meter_consumer_id');
        const currentReadingInput = document.getElementById('current_reading');
        const readingPreviewError = document.getElementById('readingPreviewError');
        const readingDateInput = document.getElementById('reading_date');
        const previousReadingDisplay = document.getElementById('previous_reading_display');
        const consumerNameDisplay = document.getElementById('consumer_name_display');
        const accountNumberDisplay = document.getElementById('account_number_display');
        const meterNumberDisplay = document.getElementById('meter_number_display');
        let meterLookupTimeout = null;
        let alreadyRead = false;
        let previousReading = 0;
        let unitRate = 0;
        let baseCharge = 0;
        let previousBalance = 0;

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

                if (!validateMeterReading()) {
                    return;
                }

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
        });

        meterReadingSubmitConfirmModalElement?.addEventListener('shown.bs.modal', function () {
            meterReadingConfirmModalTransitioning = false;
        });

        meterReadingSubmitConfirmModalElement?.addEventListener('hidden.bs.modal', function () {
            meterReadingConfirmModalTransitioning = false;

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
            const currentReading = parseFloat(currentReadingInput.value) || 0;
            const consumption = Math.max(0, currentReading - previousReading);
            const usageCharge = consumption * unitRate;
            const currentCharges = baseCharge + usageCharge;
            const totalBill = currentCharges + previousBalance;

            document.getElementById('preview_previous_reading').textContent = formatMoney(previousReading);
            document.getElementById('preview_current_reading').textContent = formatMoney(currentReading);
            document.getElementById('preview_consumption').textContent = formatMoney(consumption);
            document.getElementById('preview_unit_rate').textContent = formatMoney(unitRate);
            document.getElementById('preview_base_charge').textContent = formatMoney(baseCharge);
            const balancePreview = document.getElementById('preview_previous_balance');
            if (balancePreview) {
                balancePreview.textContent = formatMoney(previousBalance);
            }
            document.getElementById('preview_total_bill').textContent = formatMoney(totalBill);
        }

        function resetMeterResult() {
            meterSearchResult.classList.add('d-none');
            meterConsumerId.value = '';
            previousReading = 0;
            unitRate = 0;
            baseCharge = 0;
            previousBalance = 0;
            consumerNameDisplay.textContent = 'Enter a Meter ID first';
            accountNumberDisplay.textContent = '-';
            meterNumberDisplay.textContent = 'Enter a Meter ID first';
            previousReadingDisplay.textContent = '0.00';
            resultUnitRate.textContent = '-';
            setCurrentReadingError(null);
            updateBillingPreview();
        }

        function updateFocusCard(data) {
            previousReading = parseFloat(data.previous_reading) || 0;
            unitRate = parseFloat(data.unit_rate) || 0;
            baseCharge = parseFloat(data.base_charge) || 0;
            previousBalance = parseFloat(data.previous_balance) || 0;
            consumerNameDisplay.textContent = data.full_name;
            accountNumberDisplay.textContent = data.account_number;
            meterNumberDisplay.textContent = data.meter_number;
            previousReadingDisplay.textContent = data.previous_reading;
            setCurrentReadingError(null);
            updateBillingPreview();
        }

        async function lookupMeter(meterNumber) {
            if (!meterNumber || meterNumber.trim() === '') {
                meterSearchStatus.textContent = '';
                resetMeterResult();
                return;
            }

            meterSearchStatus.textContent = 'Looking up Meter ID...';
            meterSearchStatus.className = 'small text-secondary mt-1';

            const formData = new FormData();
            formData.append('meter_number', meterNumber.trim());
            formData.append('reading_date', readingDateInput.value);
            formData.append('_token', '<?php echo e(csrf_token()); ?>');

            try {
                const response = await fetch('<?php echo e(route('meter-readings.lookup-by-meter')); ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json();

                if (data.found) {
                    resultConsumerName.textContent = data.consumer.full_name;
                    resultAccountNumber.textContent = data.consumer.account_number;
                    resultPreviousReading.textContent = data.consumer.previous_reading;
                    resultUnitRate.textContent = `P ${data.consumer.unit_rate}/m3`;
                    meterConsumerId.value = data.consumer.id;
                    meterSearchResult.classList.remove('d-none');
                    updateFocusCard(data.consumer);
                    meterSearchStatus.textContent = '';
                    meterSearchStatus.className = 'small text-secondary mt-1';
                    currentReadingInput.disabled = false;
                    if (data.already_read) {
                        alreadyRead = true;
                        currentReadingInput.disabled = true;
                        const dupModal = document.getElementById('duplicateReadingModal');
                        if (dupModal) new bootstrap.Modal(dupModal).show();
                    } else {
                        alreadyRead = false;
                    }
                } else {
                    meterSearchStatus.textContent = data.message || 'No consumer found with this Meter ID.';
                    meterSearchStatus.className = 'small text-danger mt-1';
                    resetMeterResult();
                }
            } catch (error) {
                meterSearchStatus.textContent = 'Lookup failed. Please try again.';
                meterSearchStatus.className = 'small text-danger mt-1';
                resetMeterResult();
            }
        }

        function doLookup() {
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
            setCurrentReadingError(null);
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

            if (val === '' || isNaN(parseFloat(val)) || parseFloat(val) < 0) {
                currentReadingInput.classList.add('is-invalid');
                setCurrentReadingError('Please enter a valid number.');
                return false;
            }

            if (parseFloat(val) < previousReading) {
                currentReadingInput.classList.add('is-invalid');
                setCurrentReadingError('Invalid Reading: Current Meter Reading cannot be lower than the Previous Reading.');
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

        function confirmMeterReadingSubmission() {
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
            hideMeterReadingSubmitConfirmation();
            meterReadingForm.submit();
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
            document.getElementById('reading_date').value = '<?php echo e(now()->toDateString()); ?>';
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

        <?php if($errors->any()): ?>
        if (modalElement) {
            const meterReadingModal = new bootstrap.Modal(modalElement);
            meterReadingModal.show();
        }
        <?php endif; ?>

        <?php if($errors->has('duplicate_reading')): ?>
        if (duplicateModalEl) {
            new bootstrap.Modal(duplicateModalEl).show();
        }
        <?php endif; ?>
    });
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/meter-reading/meter-readings/_workspace.blade.php ENDPATH**/ ?>