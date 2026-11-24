<?php
    $activePage = 'dashboard';
    $currentBill = $billingSummary['current_bill'] ?? null;
    $currentBreakdown = $currentBill?->billing_breakdown ?? [];
?>

<?php $__env->startSection('title', 'My Dashboard'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .consumer-dashboard-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    }

    .consumer-stat {
        padding: 1.25rem;
        background: #fff;
        border: 1px solid rgba(216, 237, 255, 0.88);
        border-radius: 1rem;
    }

    .consumer-stat-label {
        color: var(--wb-muted);
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .consumer-stat-value {
        margin-top: 0.45rem;
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.15;
    }

    .consumer-dashboard-panels {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.9fr);
        gap: 1rem;
    }

    @media (max-width: 1199.98px) {
        .consumer-dashboard-panels {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 1200px) {
        .consumer-dashboard-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .consumer-dashboard-grid,
        .consumer-dashboard-panels {
            grid-template-columns: 1fr;
        }
    }
    /* Keep titles, primary figures and notes on shared rows. */
    .consumer-stat { min-width: 0; }
    .consumer-stat-label { min-height: 2.6em; line-height: 1.3; }
    .consumer-stat-value { font-variant-numeric: tabular-nums; }
    .consumer-stat .small { font-variant-numeric: tabular-nums; line-height: 1.35; }
    .dashboard-number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    @supports (grid-template-rows: subgrid) {
        .consumer-stat { display: grid; grid-row: span 3; grid-template-rows: subgrid; row-gap: 0; }
    }
    @media (max-width: 380px) { .consumer-stat-label { min-height: 0; } }
    /* Compact KPI cards without restricting longer descriptions. */
    .consumer-dashboard-grid { gap: 0.75rem; }
    .consumer-stat { padding: 0.875rem; min-height: 6.5rem; }
    .consumer-stat-label { font-size: 0.6875rem; font-weight: 600; line-height: 1.3; min-height: 1.3em; }
    .consumer-stat-value { margin-top: 0.25rem; font-size: 1.4375rem; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .consumer-stat .small { margin-top: 0.25rem !important; font-size: 0.6875rem; line-height: 1.3; }
    @media (min-width: 1200px) { .consumer-dashboard-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    @media (min-width: 992px) and (max-width: 1199.98px) { .consumer-dashboard-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 768px) and (max-width: 991.98px) { .consumer-dashboard-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767.98px) { .app-shell .content-wrapper .consumer-dashboard-grid { grid-template-columns: minmax(0, 1fr); } }
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
                    <h1>My Dashboard</h1>
                    <p>Review your current balance, billing history, and recent meter readings.</p>
                </div>
                <a href="<?php echo e(route('billing.index')); ?>" class="btn btn-primary">View My Bills</a>
            </div>

            <?php if($consumer): ?>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <span class="small text-uppercase text-secondary fw-semibold">Service Status</span>
                    <?php if (isset($component)) { $__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.consumer-status-badge','data' => ['consumer' => $consumer]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('consumer-status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['consumer' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($consumer)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62)): ?>
<?php $attributes = $__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62; ?>
<?php unset($__attributesOriginal80e56fe1f0ea739e9cdec58b8ec4ba62); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62)): ?>
<?php $component = $__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62; ?>
<?php unset($__componentOriginal80e56fe1f0ea739e9cdec58b8ec4ba62); ?>
<?php endif; ?>
                    <?php if($consumer->account_number): ?>
                        <span class="small text-secondary">Account #<?php echo e($consumer->account_number); ?></span>
                    <?php endif; ?>
                    <?php if($consumer->meter_number): ?>
                        <span class="small text-secondary">&middot; Meter <?php echo e($consumer->meter_number); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if(auth()->user()?->requiresPasswordChange()): ?>
                <div id="tempPasswordReminderBanner" class="alert alert-danger d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 mb-4 rounded-3 border-danger shadow-sm" role="alert" style="background-color: #fee2e2; border: 1px solid #ef4444; color: #991b1b;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-danger flex-shrink-0"></i>
                        <span class="fw-semibold">
                            You are using a temporary password. You can change it from your profile at any time.
                        </span>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm px-3 py-2 fw-semibold text-nowrap" id="dismissTempPasswordReminderBtn">
                        Dismiss
                    </button>
                </div>
            <?php endif; ?>

            <?php if($announcements->isNotEmpty()): ?>
                <section class="panel-card p-3 p-md-4 mb-4" aria-labelledby="dashboard-announcements-title">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 fw-bold mb-0" id="dashboard-announcements-title">Announcements</h2>
                        <a href="<?php echo e(route('announcements.index')); ?>">View all</a>
                    </div>
                    <?php $__currentLoopData = $announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="border-top py-2">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <a class="fw-semibold" href="<?php echo e(route('announcements.show', $announcement)); ?>"><?php echo e($announcement->title); ?></a>
                                <?php if($announcement->edited_at): ?><span class="badge badge-soft-neutral">Edited</span><?php endif; ?>
                                <?php if($announcement->is_pinned): ?><span class="badge badge-soft-warning">Pinned</span><?php endif; ?>
                                <?php if($announcement->priority !== 'Normal'): ?><span class="badge badge-soft-primary"><?php echo e($announcement->priority); ?></span><?php endif; ?>
                            </div>
                            <div class="small text-secondary"><?php echo e($announcement->category); ?> · <?php echo e($announcement->published_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A')); ?> PHT</div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </section>
            <?php endif; ?>

            <?php if(! $consumer): ?>
                <div class="panel-card p-4">
                    <div class="section-title mb-2">Account Not Linked</div>
                    <p class="text-secondary mb-0">No consumer record is linked to this user account yet.</p>
                </div>
            <?php else: ?>
                <div class="consumer-dashboard-grid mb-4">
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Current Charges</div>
                        <div class="consumer-stat-value">P <?php echo e(number_format((float) ($currentBreakdown['current_bill_amount'] ?? 0), 2)); ?></div>
                        <div class="small text-secondary mt-2"><?php echo e($currentBill?->billing_period?->format('F Y') ?? 'No bill generated'); ?></div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Previous Balance Added</div>
                        <div class="consumer-stat-value <?php echo e(((float) ($billingSummary['previous_balance_added'] ?? 0)) > 0 ? 'text-danger' : 'text-success'); ?>">P <?php echo e(number_format((float) ($billingSummary['previous_balance_added'] ?? 0), 2)); ?></div>
                        <div class="small text-secondary mt-2">Carried into the current bill</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Current Total Due</div>
                        <div class="consumer-stat-value">P <?php echo e(number_format((float) ($currentBill?->amount ?? 0), 2)); ?></div>
                        <div class="small text-secondary mt-2">Current charges plus carried balance</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Outstanding Balance</div>
                        <div class="consumer-stat-value <?php echo e(((float) ($billingSummary['outstanding_balance'] ?? 0)) > 0 ? 'text-danger' : 'text-success'); ?>">P <?php echo e(number_format((float) ($billingSummary['outstanding_balance'] ?? 0), 2)); ?></div>
                        <div class="small text-secondary mt-2"><?php echo e($currentBill?->billing_period?->format('F Y') ?? 'No bill generated'); ?> · after verified payments</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Total Billing</div>
                        <div class="consumer-stat-value">P <?php echo e(number_format((float) ($billingSummary['total_billing'] ?? 0), 2)); ?></div>
                        <div class="small text-secondary mt-2">All generated bills on record</div>
                    </div>
                    <div class="consumer-stat">
                        <div class="consumer-stat-label">Previous Bills</div>
                        <div class="consumer-stat-value"><?php echo e(number_format((int) ($billingSummary['previous_billing_count'] ?? 0))); ?></div>
                        <div class="small text-secondary mt-2"><?php echo e(number_format((float) ($billingSummary['total_consumption'] ?? 0), 2)); ?> cu.m total usage</div>
                    </div>
                </div>

                <div class="consumer-dashboard-panels">
                    <div class="panel-card p-4">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                            <div>
                                <div class="section-title mb-1">Previous Billing History</div>
                                <p class="text-secondary mb-0"></p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th class="dashboard-number">Previous Balance</th>
                                        <th class="dashboard-number">Current Charges</th>
                                        <th class="dashboard-number">Total Due</th>
                                        <th class="dashboard-number">Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $billingHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bill): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php
                                            $breakdown = $bill->billing_breakdown;
                                        ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e($bill->billing_period?->format('F Y') ?? 'N/A'); ?></td>
                                            <td class="dashboard-number">P <?php echo e(number_format((float) ($breakdown['previous_unpaid_balance'] ?? 0), 2)); ?></td>
                                            <td class="dashboard-number">P <?php echo e(number_format((float) ($breakdown['current_bill_amount'] ?? 0), 2)); ?></td>
                                            <td class="fw-semibold dashboard-number">P <?php echo e(number_format((float) $bill->amount, 2)); ?></td>
                                            <td class="fw-semibold dashboard-number <?php echo e($bill->balance_due > 0 ? 'text-danger' : 'text-success'); ?>">P <?php echo e(number_format((float) $bill->balance_due, 2)); ?></td>
                                            <td><span class="badge <?php echo e($bill->status === 'Paid' ? 'badge-soft-success' : ($bill->status === 'Overdue' ? 'badge-soft-danger' : 'badge-soft-warning')); ?>"><?php echo e($bill->status); ?></span></td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="6" class="text-center py-5 text-secondary">No billing history available yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="panel-card p-4">
                        <div class="section-title mb-3">Meter Reading History</div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Date</th><th class="dashboard-number">Previous</th><th class="dashboard-number">Current</th><th class="dashboard-number">Consumption</th></tr></thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $readingHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e($reading->reading_date?->format('M d, Y') ?? 'N/A'); ?></td>
                                            <td class="dashboard-number"><?php echo e(number_format((float) $reading->previous_reading, 2)); ?></td>
                                            <td class="dashboard-number"><?php echo e(number_format((float) $reading->current_reading, 2)); ?></td>
                                            <td class="dashboard-number"><?php echo e(number_format((float) $reading->consumption, 2)); ?> cu.m</td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-secondary">No meter readings available yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php echo $__env->make('consumer.account.disconnection-history', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php if(session('show_temporary_password_reminder') || auth()->user()?->requiresPasswordChange()): ?>
    <div class="modal fade" id="temporaryPasswordReminderModal" tabindex="-1" aria-labelledby="temporaryPasswordReminderTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="temporaryPasswordReminderTitle">Change Password</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">You signed in with a temporary password. You can change it in your profile whenever you choose.</div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Not now</button><a href="<?php echo e(route('consumer.profile.show')); ?>" class="btn btn-primary">Change Password</a></div>
        </div></div>
    </div>
<?php endif; ?>
<?php if(session('loan_access_denied')): ?>
    <div class="modal fade" id="loanAccessDeniedModal" tabindex="-1" aria-labelledby="loanAccessDeniedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="loanAccessDeniedModalLabel">Loan Services Unavailable</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"><?php echo e(session('loan_access_denied')); ?></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Understood</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php if(session('loan_access_denied')): ?>
    <?php $__env->startPush('scripts'); ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                new bootstrap.Modal(document.getElementById('loanAccessDeniedModal')).show();
            });
        </script>
    <?php $__env->stopPush(); ?>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordReminder = document.getElementById('temporaryPasswordReminderModal');
        if (passwordReminder && window.bootstrap) bootstrap.Modal.getOrCreateInstance(passwordReminder).show();
        const dismissBtn = document.getElementById('dismissTempPasswordReminderBtn');
        const reminderBanner = document.getElementById('tempPasswordReminderBanner');
        if (dismissBtn && reminderBanner) {
            dismissBtn.addEventListener('click', function (e) {
                e.preventDefault();
                reminderBanner.remove();
            });
        }
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/consumer/dashboard.blade.php ENDPATH**/ ?>