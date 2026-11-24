<?php
    $activePage = 'loans';
?>

<?php $__env->startSection('title', 'Loans'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .loans-summary-row {
        margin-top: -1.1rem;
    }

    .application-queue-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        background: #fff !important;
    }

    .application-queue-filter-form {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-left: auto;
    }

    .application-queue-filter-form .form-control,
    .application-queue-filter-form .form-select {
        width: auto;
        min-width: 160px;
        background: #fff !important;
    }

    .application-queue-filter-form .btn {
        background: #fff !important;
    }

    .application-queue-filter-form .form-control[name="search"] {
        min-width: 240px;
    }

    .loan-application-flow {
        display: grid;
        gap: 1rem;
    }

    .loan-form-step {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 0.9rem;
        padding: 1rem;
        border: 1px solid rgba(219, 230, 238, 0.95);
        border-radius: 0.8rem;
        background: #fff;
    }

    .loan-step-number {
        width: 2rem;
        height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(15, 110, 140, 0.1);
        color: var(--wb-primary);
        font-weight: 800;
        line-height: 1;
    }

    .loan-step-body {
        min-width: 0;
    }

    .loan-step-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .loan-step-copy,
    .loan-field-help {
        color: var(--wb-muted);
        font-size: 0.86rem;
    }

    .loan-step-copy {
        margin: 0.15rem 0 0.85rem;
    }

    .loan-field-help {
        margin-top: 0.35rem;
    }

    .loan-form-summary {
        padding: 0.9rem 1rem;
        border: 1px solid rgba(15, 110, 140, 0.16);
        border-radius: 0.8rem;
        background: rgba(15, 110, 140, 0.05);
        color: var(--wb-muted);
        font-size: 0.9rem;
    }

    .loan-cost-breakdown {
        display: grid;
        gap: 0.55rem;
        padding: 0.9rem 1rem;
        border: 1px solid rgba(219, 230, 238, 0.95);
        border-radius: 0.8rem;
        background: #fff;
    }

    .loan-cost-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
    }

    .loan-cost-row.total {
        padding-top: 0.55rem;
        border-top: 1px solid rgba(219, 230, 238, 0.95);
        font-weight: 800;
    }

    .application-queue-table th,
    .application-queue-table td {
        white-space: nowrap;
    }

    .application-queue-table td:first-child,
    .application-queue-table td:nth-child(2),
    .application-queue-table td:nth-child(6) {
        white-space: normal;
        min-width: 11rem;
    }

    @media (max-width: 767.98px) {
        .application-queue-toolbar {
            align-items: stretch;
        }

        .application-queue-filter-form {
            width: 100%;
            margin-left: 0;
            justify-content: stretch;
        }

        .application-queue-filter-form .form-control,
        .application-queue-filter-form .form-select,
        .application-queue-filter-form .btn {
            width: 100%;
            min-width: 0;
        }

        .loan-form-step {
            grid-template-columns: 1fr;
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
                    <h1>Loan Management</h1>
                    <p>Capture applications, evaluate consumer repayment profile, and monitor upcoming installments.</p>
                </div>
            </div>

            <div class="row g-4 mb-4 loans-summary-row">
                <div class="col-md-6 col-xl-3"><div class="dashboard-card p-4 h-100"><div class="text-secondary small">Approved Loans</div><h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['approved'])); ?></h2><span class="badge badge-soft-success">Active portfolio</span></div></div>
                <div class="col-md-6 col-xl-3"><div class="dashboard-card p-4 h-100"><div class="text-secondary small">Awaiting Installation Schedule</div><h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['pending'])); ?></h2><span class="badge badge-soft-warning">Needs schedule</span></div></div>
                <div class="col-md-6 col-xl-3"><div class="dashboard-card p-4 h-100"><div class="text-secondary small">Eligible Applications</div><h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['pending'])); ?></h2><span class="badge badge-soft-success">DSS passed</span></div></div>
                <div class="col-md-6 col-xl-3"><div class="dashboard-card p-4 h-100"><div class="text-secondary small">Repayments Due</div><h2 class="fw-bold mt-2 mb-1"><?php echo e(number_format($stats['due_this_week'])); ?></h2><span class="badge badge-soft-primary">Next 7 days</span></div></div>
            </div>

            <div class="row g-4" data-admin-records-root>
                <div class="col-xl-5">
                    <div class="panel-card p-4 mb-4">
                        <?php if(session('success')): ?>
                            <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-check-circle mt-1"></i>
                                <div><?php echo e(session('success')); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if($errors->any()): ?>
                            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-exclamation-circle mt-1"></i>
                                <div><?php echo e($errors->first()); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (! ($loansTableExists)): ?>
                            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-tools mt-1"></i>
                                <div>Loans is installed in code but the database table is not created yet. Run <code>php artisan migrate</code> to enable it.</div>
                            </div>
                        <?php endif; ?>

                        <div class="section-title">Loan Administration</div>
                        <p class="text-secondary">Admins can configure installation costs, review consumer-submitted applications, and monitor repayments.</p>
                        <div class="d-grid gap-2">
                            <div class="d-flex justify-content-between"><span class="text-secondary">Interest</span><strong>0%</strong></div>
                            <div class="d-flex justify-content-between"><span class="text-secondary">Eligible terms</span><strong>3, 6, or 12 months</strong></div>
                            <div class="d-flex justify-content-between"><span class="text-secondary">Approval flow</span><strong>Admin review</strong></div>
                        </div>
                    </div>

                    <div class="panel-card p-4 mb-4">
                        <div class="section-title">Installation Cost Breakdown</div>
                        <form method="POST" action="<?php echo e(route('loan-material-rates.update')); ?>">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="water_meter_cost">Water Meter</label>
                                    <div class="input-group">
                                        <span class="input-group-text">P</span>
                                        <input type="number" class="form-control" id="water_meter_cost" name="water_meter_cost" min="0" step="0.01" value="<?php echo e(old('water_meter_cost', number_format((float) $loanCostBreakdown['meter'], 2, '.', ''))); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="hose_cost">Hose</label>
                                    <div class="input-group">
                                        <span class="input-group-text">P</span>
                                        <input type="number" class="form-control" id="hose_cost" name="hose_cost" min="0" step="0.01" value="<?php echo e(old('hose_cost', number_format((float) $loanCostBreakdown['hose'], 2, '.', ''))); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="other_materials_cost">Other Materials</label>
                                    <div class="input-group">
                                        <span class="input-group-text">P</span>
                                        <input type="number" class="form-control" id="other_materials_cost" name="other_materials_cost" min="0" step="0.01" value="<?php echo e(old('other_materials_cost', number_format((float) $loanCostBreakdown['other_materials'], 2, '.', ''))); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="loan-cost-breakdown mt-3">
                                <div class="loan-cost-row"><span class="text-secondary">Meter</span><strong>P <?php echo e(number_format((float) $loanCostBreakdown['meter'], 2)); ?></strong></div>
                                <div class="loan-cost-row"><span class="text-secondary">Hose</span><strong>P <?php echo e(number_format((float) $loanCostBreakdown['hose'], 2)); ?></strong></div>
                                <div class="loan-cost-row"><span class="text-secondary">Other Materials</span><strong>P <?php echo e(number_format((float) $loanCostBreakdown['other_materials'], 2)); ?></strong></div>
                                <div class="loan-cost-row total"><span>Total Installation Cost</span><strong>P <?php echo e(number_format((float) $loanCostBreakdown['total_installation_cost'], 2)); ?></strong></div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Update Costs</button>
                        </form>
                    </div>

                    <div class="panel-card p-4">
                        <div class="section-title">Installation Workflow</div>
                        <p class="text-secondary mb-0">Consumer applications in the queue have already passed DSS eligibility. Schedule the installation date and time to notify the applicant.</p>
                    </div>
                </div>

                <div class="col-xl-7">
                    <div class="panel-card p-4 mb-4">
                        <div class="application-queue-toolbar mb-3" data-admin-freeze>
                            <div>
                                <div class="section-title mb-0">Application Queue</div>
                                <p class="small text-secondary mb-0">Pending-review applications only. Search by applicant, account, or purpose.</p>
                            </div>
                            <form method="GET" action="<?php echo e(route('loans.index')); ?>" class="application-queue-filter-form" data-auto-filter data-auto-filter-debounce="450">
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    id="loan_filter_search"
                                    name="search"
                                    value="<?php echo e($filters['search']); ?>"
                                    placeholder="Search applicant or purpose"
                                >
                                <noscript>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Apply filters</button>
                                </noscript>
                                <a href="<?php echo e(route('loans.index')); ?>" class="btn btn-sm btn-light border">Clear</a>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle application-queue-table">
                                <thead>
                                    <tr>
                                        <th>Applicant Information</th>
                                        <th>Installation Cost Details</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Installation Status</th>
                                        <th>Installation Schedule</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $loans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $loan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($loan->consumer->full_name); ?></div>
                                                <div class="small text-secondary"><?php echo e($loan->consumer->account_number); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">P <?php echo e(number_format((float) $loan->installation_cost_total, 2)); ?></div>
                                                <div class="small text-secondary"><?php echo e($loan->term_months); ?> months at P <?php echo e(number_format((float) $loan->monthly_installment, 2)); ?></div>
                                            </td>
                                            <td>P <?php echo e(number_format((float) $loan->total_repaid, 2)); ?></td>
                                            <td>P <?php echo e(number_format((float) $loan->balance_remaining, 2)); ?></td>
                                            <td><span class="badge <?php echo e(match ($loan->installation_status ?? 'Not Scheduled') {
                                                'Scheduled', 'Completed' => 'badge-soft-success',
                                                default => 'badge-soft-warning',
                                            }); ?>"><?php echo e($loan->installation_status ?? 'Not Scheduled'); ?></span></td>
                                            <td>
                                                <?php if($loan->installation_date && $loan->installation_time): ?>
                                                    <div class="fw-semibold"><?php echo e($loan->installation_date->format('M d, Y')); ?></div>
                                                    <div class="small text-secondary"><?php echo e(\Illuminate\Support\Carbon::createFromFormat('H:i:s', strlen((string) $loan->installation_time) === 5 ? $loan->installation_time.':00' : (string) $loan->installation_time)->format('g:i A')); ?></div>
                                                <?php else: ?>
                                                    <span class="text-secondary small">Not scheduled</span>
                                                <?php endif; ?>
                                                <?php
                                                    $pendingLoanReschedule = $loan->installationRescheduleRequests()
                                                        ->where('status', \App\Models\InstallationRescheduleRequest::STATUS_PENDING)
                                                        ->latest('id')
                                                        ->first();
                                                ?>
                                                <?php if($pendingLoanReschedule): ?>
                                                    <div class="mt-1">
                                                        <a href="<?php echo e(route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'reschedules', 'search' => $loan->consumer->full_name])); ?>" class="badge bg-warning text-dark text-decoration-none" title="Review under Pending Accounts">
                                                            <i class="bi bi-clock-history me-1"></i>Reschedule Requested
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-light border"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#loanScheduleModal"
                                                    data-loan-id="<?php echo e($loan->id); ?>"
                                                    data-loan-borrower="<?php echo e($loan->consumer->full_name); ?>"
                                                    data-loan-account="<?php echo e($loan->consumer->account_number); ?>"
                                                    data-loan-amount="<?php echo e(number_format((float) $loan->amount, 2, '.', '')); ?>"
                                                    data-loan-term="<?php echo e($loan->term_months); ?>"
                                                    data-loan-meter-cost="<?php echo e(number_format((float) $loan->material_meter_cost, 2, '.', '')); ?>"
                                                    data-loan-hose-cost="<?php echo e(number_format((float) $loan->material_hose_cost, 2, '.', '')); ?>"
                                                    data-loan-other-cost="<?php echo e(number_format((float) $loan->material_other_cost, 2, '.', '')); ?>"
                                                    data-loan-total-cost="<?php echo e(number_format((float) $loan->installation_cost_total, 2, '.', '')); ?>"
                                                    data-loan-monthly-installment="<?php echo e(number_format((float) $loan->monthly_installment, 2, '.', '')); ?>"
                                                    data-loan-installment-schedule='<?php echo json_encode($loan->installment_schedule_snapshot ?: [], 15, 512) ?>'
                                                    data-loan-installation-status="<?php echo e($loan->installation_status ?? 'Not Scheduled'); ?>"
                                                    data-loan-installation-date="<?php echo e($loan->installation_date?->format('Y-m-d')); ?>"
                                                    data-loan-installation-time="<?php echo e($loan->installation_time ? substr((string) $loan->installation_time, 0, 5) : ''); ?>"
                                                    data-loan-installation-notes="<?php echo e($loan->installation_notes); ?>"
                                                >
                                                    <?php echo e($loan->installation_date && $loan->installation_time ? 'Update Schedule' : 'Schedule Installation'); ?>

                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="7" class="text-center py-5 text-secondary">No applications awaiting installation schedule.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                            <p class="text-secondary mb-0">
                                <?php if($loans->total() > 0): ?>
                                    Showing <?php echo e($loans->firstItem()); ?> to <?php echo e($loans->lastItem()); ?> of <?php echo e($loans->total()); ?> loan applications
                                <?php else: ?>
                                    No loan applications yet
                                <?php endif; ?>
                            </p>
                            <?php echo e($loans->links()); ?>

                        </div>
                    </div>

                    <div class="panel-card p-4 mb-4">
                        <div class="section-title">Scheduled Installations</div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Borrower</th><th>Installation Date</th><th>Installation Time</th><th>Installation Notes</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $scheduledInstallations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $loan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($loan->consumer->full_name); ?></div>
                                                <div class="small text-secondary"><?php echo e($loan->consumer->account_number); ?></div>
                                            </td>
                                            <td><?php echo e($loan->installation_date?->format('M d, Y')); ?></td>
                                            <td><?php echo e($loan->installation_time ? \Illuminate\Support\Carbon::createFromFormat('H:i:s', strlen((string) $loan->installation_time) === 5 ? $loan->installation_time.':00' : (string) $loan->installation_time)->format('g:i A') : '-'); ?></td>
                                            <td><?php echo e($loan->installation_notes ?: '-'); ?></td>
                                            <td class="text-end">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-light border"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#loanScheduleModal"
                                                    data-loan-id="<?php echo e($loan->id); ?>"
                                                    data-loan-borrower="<?php echo e($loan->consumer->full_name); ?>"
                                                    data-loan-account="<?php echo e($loan->consumer->account_number); ?>"
                                                    data-loan-amount="<?php echo e(number_format((float) $loan->amount, 2, '.', '')); ?>"
                                                    data-loan-term="<?php echo e($loan->term_months); ?>"
                                                    data-loan-meter-cost="<?php echo e(number_format((float) $loan->material_meter_cost, 2, '.', '')); ?>"
                                                    data-loan-hose-cost="<?php echo e(number_format((float) $loan->material_hose_cost, 2, '.', '')); ?>"
                                                    data-loan-other-cost="<?php echo e(number_format((float) $loan->material_other_cost, 2, '.', '')); ?>"
                                                    data-loan-total-cost="<?php echo e(number_format((float) $loan->installation_cost_total, 2, '.', '')); ?>"
                                                    data-loan-monthly-installment="<?php echo e(number_format((float) $loan->monthly_installment, 2, '.', '')); ?>"
                                                    data-loan-installment-schedule='<?php echo json_encode($loan->installment_schedule_snapshot ?: [], 15, 512) ?>'
                                                    data-loan-installation-status="<?php echo e($loan->installation_status ?? 'Not Scheduled'); ?>"
                                                    data-loan-installation-date="<?php echo e($loan->installation_date?->format('Y-m-d')); ?>"
                                                    data-loan-installation-time="<?php echo e($loan->installation_time ? substr((string) $loan->installation_time, 0, 5) : ''); ?>"
                                                    data-loan-installation-notes="<?php echo e($loan->installation_notes); ?>"
                                                >
                                                    Update Schedule
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-secondary">No scheduled installations yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="panel-card p-4 mb-4">
                        <div class="section-title">Loan Installments</div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Installment</th>
                                        <th>Billing Period</th>
                                        <th>Required Amount</th>
                                        <th>Amount Paid</th>
                                        <th>Remaining Balance</th>
                                        <th>Due Date</th>
                                        <th>Payment Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $loanInstallments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $installment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td><?php echo e($installment->loan->consumer->full_name); ?></td>
                                            <td>Month <?php echo e($installment->installment_number); ?></td>
                                            <td><?php echo e($installment->billing_period->format('F Y')); ?></td>
                                            <td>P <?php echo e(number_format((float) $installment->required_amount, 2)); ?></td>
                                            <td>P <?php echo e(number_format((float) $installment->amount_paid, 2)); ?></td>
                                            <td>P <?php echo e(number_format((float) $installment->remaining_balance, 2)); ?></td>
                                            <td><?php echo e($installment->due_date?->format('M d, Y') ?? '-'); ?></td>
                                            <td><?php echo e($installment->payment_date?->format('M d, Y') ?? '-'); ?></td>
                                            <td><span class="badge <?php echo e(match ($installment->status) {
                                                'Paid' => 'badge-soft-success',
                                                'Partially Paid', 'Billed' => 'badge-soft-warning',
                                                'Overdue' => 'badge-soft-danger',
                                                default => 'badge-soft-primary',
                                            }); ?>"><?php echo e($installment->status); ?></span></td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="9" class="text-center py-4 text-secondary">No loan installments have been billed yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<?php if($canManageLoans): ?>
    <div class="modal fade" id="loanScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Schedule Installation</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Applicant</div><div class="fw-semibold" id="loan_schedule_borrower">-</div><div class="small text-secondary" id="loan_schedule_account">-</div></div>
                        <div class="col-md-3"><div class="small text-uppercase text-secondary fw-semibold mb-1">Total Installation Cost</div><div class="fw-semibold" id="loan_schedule_amount">-</div></div>
                        <div class="col-md-3"><div class="small text-uppercase text-secondary fw-semibold mb-1">Selected Repayment Term</div><div class="fw-semibold" id="loan_schedule_term">-</div></div>
                        <div class="col-md-6"><div class="small text-uppercase text-secondary fw-semibold mb-1">Installation Status</div><div class="fw-semibold" id="loan_current_installation_status">-</div></div>
                    </div>
                    <div class="loan-cost-breakdown mb-4">
                        <div class="section-title mb-1">Material Cost Breakdown</div>
                        <div class="loan-cost-row"><span class="text-secondary">Water Meter</span><strong id="loan_schedule_meter_cost">P 0.00</strong></div>
                        <div class="loan-cost-row"><span class="text-secondary">Hose</span><strong id="loan_schedule_hose_cost">P 0.00</strong></div>
                        <div class="loan-cost-row"><span class="text-secondary">Other Materials</span><strong id="loan_schedule_other_cost">P 0.00</strong></div>
                        <div class="loan-cost-row total"><span>Total Installation Cost</span><strong id="loan_schedule_total_cost">P 0.00</strong></div>
                    </div>
                    <div class="loan-cost-breakdown mb-4">
                        <div class="section-title mb-1">Monthly Installment Schedule</div>
                        <div class="loan-cost-row"><span class="text-secondary">Selected Repayment Term</span><strong id="loan_schedule_selected_term">-</strong></div>
                        <div class="loan-cost-row"><span class="text-secondary">Monthly Installment</span><strong id="loan_schedule_monthly_installment">P 0.00</strong></div>
                        <div id="loan_schedule_installments" class="d-grid gap-2"></div>
                    </div>
                    <form method="POST" id="loanInstallationForm">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div class="section-title">Installation Schedule</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="loan_installation_date">Installation Date</label>
                                <input type="date" class="form-control" id="loan_installation_date" name="installation_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="loan_installation_time">Installation Time</label>
                                <input type="time" class="form-control" id="loan_installation_time" name="installation_time" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="loan_installation_notes">Installation Notes</label>
                                <textarea class="form-control" id="loan_installation_notes" name="installation_notes" rows="3"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="loanInstallationForm" class="btn btn-outline-primary">Save Installation Schedule</button>
                </div>
            </div>
        </div>
    </div>

    <?php if($repaymentsTableExists): ?>
        <div class="modal fade" id="loanRepaymentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Post Loan Repayment</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="fw-semibold" id="loan_repayment_borrower">-</div>
                            <div class="small text-secondary">Remaining balance: <span id="loan_repayment_balance">P 0.00</span></div>
                        </div>
                        <form method="POST" id="loanRepaymentForm">
                            <?php echo csrf_field(); ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="repayment_amount">Amount</label>
                                    <input type="number" class="form-control" id="repayment_amount" name="amount" min="0.01" step="0.01" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="repayment_payment_date">Payment Date</label>
                                    <input type="date" class="form-control" id="repayment_payment_date" name="payment_date" value="<?php echo e(now()->toDateString()); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="repayment_notes">Notes</label>
                                    <input type="text" class="form-control" id="repayment_notes" name="notes" placeholder="Optional repayment note">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="loanRepaymentForm" class="btn btn-primary">Post Repayment</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <?php if($canManageLoans): ?>
        <script>
            const loanScheduleModal = document.getElementById('loanScheduleModal');
            const formatPeso = (value) => `P ${Number.parseFloat(value || 0).toFixed(2)}`;

            if (loanScheduleModal) {
                loanScheduleModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const loanId = button.getAttribute('data-loan-id');
                    const term = button.getAttribute('data-loan-term') || '-';
                    let installments = [];

                    try {
                        installments = JSON.parse(button.getAttribute('data-loan-installment-schedule') || '[]');
                    } catch (error) {
                        installments = [];
                    }

                    document.getElementById('loan_schedule_borrower').textContent = button.getAttribute('data-loan-borrower') || '-';
                    document.getElementById('loan_schedule_account').textContent = button.getAttribute('data-loan-account') || '-';
                    document.getElementById('loan_schedule_amount').textContent = formatPeso(button.getAttribute('data-loan-total-cost'));
                    document.getElementById('loan_schedule_term').textContent = `${term} months`;
                    document.getElementById('loan_schedule_meter_cost').textContent = formatPeso(button.getAttribute('data-loan-meter-cost'));
                    document.getElementById('loan_schedule_hose_cost').textContent = formatPeso(button.getAttribute('data-loan-hose-cost'));
                    document.getElementById('loan_schedule_other_cost').textContent = formatPeso(button.getAttribute('data-loan-other-cost'));
                    document.getElementById('loan_schedule_total_cost').textContent = formatPeso(button.getAttribute('data-loan-total-cost'));
                    document.getElementById('loan_schedule_selected_term').textContent = `${term} months`;
                    document.getElementById('loan_schedule_monthly_installment').textContent = formatPeso(button.getAttribute('data-loan-monthly-installment'));
                    document.getElementById('loan_schedule_installments').innerHTML = installments.length
                        ? installments.map((installment) => `
                            <div class="loan-cost-row">
                                <span class="text-secondary">Month ${installment.month}</span>
                                <strong>${formatPeso(installment.amount)}</strong>
                            </div>
                        `).join('')
                        : '<div class="text-secondary small">No installment schedule recorded.</div>';
                    document.getElementById('loan_current_installation_status').textContent = button.getAttribute('data-loan-installation-status') || 'Not Scheduled';
                    document.getElementById('loan_installation_date').value = button.getAttribute('data-loan-installation-date') || '';
                    document.getElementById('loan_installation_time').value = button.getAttribute('data-loan-installation-time') || '';
                    document.getElementById('loan_installation_notes').value = button.getAttribute('data-loan-installation-notes') || '';
                    document.getElementById('loanInstallationForm').action = `/loans/${loanId}/installation-schedule`;
                });
            }
        </script>

        <?php if($repaymentsTableExists): ?>
            <script>
                const loanRepaymentModal = document.getElementById('loanRepaymentModal');

                if (loanRepaymentModal) {
                    loanRepaymentModal.addEventListener('show.bs.modal', (event) => {
                        const button = event.relatedTarget;
                        const loanId = button.getAttribute('data-loan-id');
                        const balance = button.getAttribute('data-loan-balance') || '0.00';
                        const installment = button.getAttribute('data-loan-installment') || '';

                        document.getElementById('loan_repayment_borrower').textContent = button.getAttribute('data-loan-borrower') || '-';
                        document.getElementById('loan_repayment_balance').textContent = `P ${balance}`;
                        document.getElementById('repayment_amount').value = installment;
                        document.getElementById('loanRepaymentForm').action = `/loans/${loanId}/repayments`;
                    });
                }
            </script>
        <?php endif; ?>
    <?php endif; ?>

<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/loans/index.blade.php ENDPATH**/ ?>