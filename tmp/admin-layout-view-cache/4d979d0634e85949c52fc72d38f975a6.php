<?php
    $activePage = 'dss-loans';
?>

<?php $__env->startSection('title', 'Loan DSS'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .dss-filter-card {
        border-radius: 1.25rem;
        border: 1px solid rgba(148, 163, 184, .22);
        background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
    }

    .dss-filter-card .form-label {
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .dss-kpi {
        height: 100%;
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 1.1rem;
        background: #fff;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .055);
    }

    .dss-kpi-label {
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .dss-kpi-value {
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.1;
    }

    .dss-chart-card {
        height: 100%;
    }

    .dss-chart-wrap {
        height: 280px;
        position: relative;
    }

    .dss-empty-state {
        align-items: center;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 1rem;
        color: #64748b;
        display: flex;
        justify-content: center;
        min-height: 190px;
        padding: 1.25rem;
        text-align: center;
    }

    .dss-recommendation {
        align-items: flex-start;
        background: #f8fafc;
        border-left: 4px solid #94a3b8;
        border-radius: .9rem;
        display: flex;
        gap: .85rem;
        padding: .95rem 1rem;
    }

    .dss-recommendation + .dss-recommendation {
        margin-top: .75rem;
    }

    .dss-recommendation-critical {
        background: #fff5f5;
        border-left-color: #dc3545;
    }

    .dss-recommendation-warning {
        background: #fffbeb;
        border-left-color: #f59e0b;
    }

    .dss-recommendation-info {
        background: #eff6ff;
        border-left-color: #3b82f6;
    }

    .dss-recommendation-success {
        background: #f0fdf4;
        border-left-color: #22c55e;
    }

    .dss-recommendation-icon {
        align-items: center;
        background: rgba(255, 255, 255, .85);
        border-radius: .75rem;
        display: flex;
        flex: 0 0 2.25rem;
        height: 2.25rem;
        justify-content: center;
        width: 2.25rem;
    }

    .dss-mini-label {
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .dss-table td,
    .dss-table th {
        vertical-align: middle;
    }

    .dss-progress {
        height: .48rem;
        background: #eef2f7;
        border-radius: 999px;
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
                    <h1>Loan DSS</h1>
                    <p>Decision support for loan approvals, portfolio exposure, repayment performance, and delinquency. Showing <?php echo e($dss['period_label']); ?>.</p>
                </div>
                <a href="<?php echo e(route('dss.incidents')); ?>" class="btn btn-outline-primary">
                    <i class="bi bi-exclamation-triangle me-2"></i>Incident DSS
                </a>
            </div>

            <div class="panel-card dss-filter-card p-3 p-lg-4 mb-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <div class="dss-mini-label">Automatic filters</div>
                        <div class="small text-secondary">Every filter updates the analysis immediately. No Apply button is needed.</div>
                    </div>
                    <a href="<?php echo e(route('dss.loans')); ?>" class="btn btn-sm btn-light border">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                </div>

                <form method="GET" action="<?php echo e(route('dss.loans')); ?>" data-auto-filter data-auto-filter-debounce="450">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="loan_dss_year">Year</label>
                            <select class="form-select" id="loan_dss_year" name="year">
                                <?php $__currentLoopData = $dss['options']['years']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($year); ?>" <?php if((int) $dss['filters']['year'] === (int) $year): echo 'selected'; endif; ?>><?php echo e($year); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="loan_dss_month">Month</label>
                            <select class="form-select" id="loan_dss_month" name="month">
                                <option value="" <?php if($dss['filters']['month'] === null): echo 'selected'; endif; ?>>All months</option>
                                <?php $__currentLoopData = $dss['options']['months']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($month['value']); ?>" <?php if((int) $dss['filters']['month'] === (int) $month['value']): echo 'selected'; endif; ?>><?php echo e($month['label']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="loan_dss_status">Status</label>
                            <select class="form-select" id="loan_dss_status" name="status">
                                <option value="">All statuses</option>
                                <?php $__currentLoopData = $dss['options']['statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status); ?>" <?php if($dss['filters']['status'] === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="loan_dss_risk">Risk level</label>
                            <select class="form-select" id="loan_dss_risk" name="risk">
                                <option value="">All risk levels</option>
                                <?php $__currentLoopData = $dss['options']['risks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $risk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($risk); ?>" <?php if($dss['filters']['risk'] === $risk): echo 'selected'; endif; ?>><?php echo e($risk === '__unspecified__' ? 'Not assessed' : $risk); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="loan_dss_term">Term</label>
                            <select class="form-select" id="loan_dss_term" name="term">
                                <option value="">All terms</option>
                                <?php $__currentLoopData = $dss['options']['terms']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $term): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($term); ?>" <?php if((int) $dss['filters']['term'] === (int) $term): echo 'selected'; endif; ?>><?php echo e($term); ?> months</option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <?php $__currentLoopData = $dss['data_notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="alert alert-warning border-0 rounded-3"><?php echo e($note); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-4">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Applications</div>
                        <div class="dss-kpi-value mt-2"><?php echo e(number_format($dss['summary']['total'])); ?></div>
                        <div class="small text-secondary mt-1"><?php echo e(number_format($dss['summary']['pending'])); ?> pending review</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Approval rate</div>
                        <div class="dss-kpi-value mt-2"><?php echo e($dss['summary']['approval_rate'] !== null ? number_format($dss['summary']['approval_rate'], 1).'%' : 'N/A'); ?></div>
                        <div class="small text-secondary mt-1">
                            <?php echo e($dss['summary']['approved']); ?> approved of <?php echo e($dss['summary']['decided']); ?> decided
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Rejection rate</div>
                        <div class="dss-kpi-value mt-2"><?php echo e($dss['summary']['rejection_rate'] !== null ? number_format($dss['summary']['rejection_rate'], 1).'%' : 'N/A'); ?></div>
                        <div class="small text-secondary mt-1">
                            <?php echo e($dss['summary']['decision_coverage'] !== null ? number_format($dss['summary']['decision_coverage'], 1).'% of applications decided' : 'No decisions recorded'); ?>

                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Outstanding balance</div>
                        <div class="dss-kpi-value mt-2">₱<?php echo e(number_format($dss['summary']['outstanding_balance'], 2)); ?></div>
                        <div class="small text-secondary mt-1"><?php echo e(number_format($dss['summary']['portfolio_count'])); ?> portfolio loan(s)</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Overdue loans</div>
                        <div class="dss-kpi-value mt-2"><?php echo e(number_format($dss['summary']['overdue_count'])); ?></div>
                        <div class="small text-secondary mt-1">₱<?php echo e(number_format($dss['summary']['overdue_amount'], 2)); ?> overdue amount</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Collection rate</div>
                        <div class="dss-kpi-value mt-2"><?php echo e($dss['summary']['collection_rate'] !== null ? number_format($dss['summary']['collection_rate'], 1).'%' : 'N/A'); ?></div>
                        <div class="small text-secondary mt-1">
                            <?php echo e($dss['summary']['on_time_rate'] !== null ? number_format($dss['summary']['on_time_rate'], 1).'% on-time payments' : 'No paid installments yet'); ?>

                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4" data-admin-records-root>
                <div class="col-xl-4">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Application status</h2>
                                <p class="small text-secondary mb-0">Count of matching applications by current status.</p>
                            </div>
                            <i class="bi bi-pie-chart text-primary"></i>
                        </div>
                        <?php if($dss['charts']['has_data']): ?>
                            <div class="dss-chart-wrap">
                                <canvas id="loanStatusChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dss-empty-state">No loan applications for the selected filters.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Monthly application, approval, and collection trend</h2>
                                <p class="small text-secondary mb-0">Applications and approvals in <?php echo e($dss['filters']['year']); ?>, plus recorded repayments for the matching loans.</p>
                            </div>
                            <i class="bi bi-bar-chart-line text-primary"></i>
                        </div>
                        <?php if($dss['charts']['monthly']['has_data']): ?>
                            <div class="dss-chart-wrap">
                                <canvas id="loanMonthlyChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dss-empty-state">No monthly application or collection data for the selected year.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Portfolio aging</h2>
                                <p class="small text-secondary mb-0">Outstanding amounts by days past due.</p>
                            </div>
                            <i class="bi bi-hourglass-split text-warning"></i>
                        </div>
                        <?php if(array_sum($dss['charts']['aging']['amounts']) > 0): ?>
                            <div class="dss-chart-wrap">
                                <canvas id="loanAgingChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dss-empty-state">No outstanding balance to age for the selected filters.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="panel-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Repayment performance</h2>
                                <p class="small text-secondary mb-0">Collection, on-time payment, and installment coverage.</p>
                            </div>
                            <i class="bi bi-cash-stack text-success"></i>
                        </div>

                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Active principal</span>
                            <span class="fw-semibold">₱<?php echo e(number_format($dss['summary']['approved_principal'], 2)); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Total repaid</span>
                            <span class="fw-semibold">₱<?php echo e(number_format($dss['summary']['total_repaid'], 2)); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Collection rate</span>
                            <span class="fw-semibold"><?php echo e($dss['summary']['collection_rate'] !== null ? number_format($dss['summary']['collection_rate'], 1).'%' : 'N/A'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">On-time payment rate</span>
                            <span class="fw-semibold"><?php echo e($dss['summary']['on_time_rate'] !== null ? number_format($dss['summary']['on_time_rate'], 1).'%' : 'N/A'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Average days late</span>
                            <span class="fw-semibold"><?php echo e($dss['summary']['average_days_late'] !== null ? number_format($dss['summary']['average_days_late'], 1) : 'N/A'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Billed / paid installments</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['installments_billed'])); ?> / <?php echo e(number_format($dss['summary']['installments_paid'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="small text-secondary">Overdue installments</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['installments_overdue'])); ?></span>
                        </div>

                        <?php if(! $dss['summary']['has_installments'] && ! $dss['summary']['has_repayments'] && $dss['summary']['portfolio_count'] > 0): ?>
                            <div class="alert alert-warning border-0 rounded-3 small mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                No installment or repayment records are available for the matching loans. Collection metrics will remain incomplete until those records are created.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-7">
                    <div class="panel-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Recommended actions</h2>
                                <p class="small text-secondary mb-0">Rule-based recommendations from the current filtered records.</p>
                            </div>
                            <i class="bi bi-lightbulb text-warning"></i>
                        </div>

                        <?php $__currentLoopData = $dss['recommendations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recommendation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="dss-recommendation dss-recommendation-<?php echo e($recommendation['severity']); ?>">
                                <div class="dss-recommendation-icon">
                                    <i class="bi <?php echo e($recommendation['severity'] === 'critical' ? 'bi-exclamation-octagon-fill text-danger' : ($recommendation['severity'] === 'warning' ? 'bi-exclamation-triangle-fill text-warning' : ($recommendation['severity'] === 'success' ? 'bi-check-circle-fill text-success' : 'bi-info-circle-fill text-primary'))); ?>"></i>
                                </div>
                                <div>
                                    <div class="fw-bold"><?php echo e($recommendation['title']); ?></div>
                                    <div class="small text-secondary mt-1"><?php echo e($recommendation['message']); ?></div>
                                    <div class="small mt-2"><span class="fw-semibold">Next step:</span> <?php echo e($recommendation['action']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="panel-card p-4 mb-4">
                        <h2 class="h6 fw-bold mb-3">Status and risk breakdown</h2>
                        <div class="dss-mini-label mb-2">Status</div>
                        <?php $__currentLoopData = $dss['status_breakdown']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span><?php echo e($status['label']); ?></span>
                                <span class="text-secondary"><?php echo e(number_format($status['total'])); ?></span>
                            </div>
                            <div class="progress dss-progress mb-2">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo e($status['share']); ?>%" aria-valuenow="<?php echo e($status['share']); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        <div class="dss-mini-label mt-3 mb-2">Risk level</div>
                        <?php $__currentLoopData = $dss['risk_breakdown']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $risk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span><?php echo e($risk['label']); ?></span>
                                <span class="text-secondary"><?php echo e(number_format($risk['total'])); ?></span>
                            </div>
                            <div class="progress dss-progress mb-2">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo e($risk['share']); ?>%" aria-valuenow="<?php echo e($risk['share']); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        <?php if(count($dss['term_breakdown']) > 0): ?>
                            <div class="dss-mini-label mt-3 mb-2">Term</div>
                            <?php $__currentLoopData = $dss['term_breakdown']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $term): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="d-flex justify-content-between align-items-center small py-1">
                                    <span><?php echo e($term['label']); ?></span>
                                    <span class="text-secondary"><?php echo e(number_format($term['total'])); ?></span>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </div>

                    <div class="panel-card p-4">
                        <h2 class="h6 fw-bold mb-3">Data quality</h2>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Pending requested amount</span>
                            <span class="fw-semibold">₱<?php echo e(number_format($dss['summary']['pending_requested_amount'], 2)); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Oldest pending application</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['oldest_pending_days'])); ?> day(s)</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Average approval lead time</span>
                            <span class="fw-semibold"><?php echo e($dss['summary']['average_approval_days'] !== null ? number_format($dss['summary']['average_approval_days'], 1).' day(s)' : 'N/A'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Invalid approval timestamps</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['invalid_approval_timestamps'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Unassessed risk</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['unassessed_risk'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="small text-secondary">Approved with zero amount</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['zero_amount_approved'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-1">Loan applications</h2>
                        <p class="small text-secondary mb-0">Latest 15 matching applications. Amounts are in Philippine pesos.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo e(number_format($dss['summary']['total'])); ?> matching application(s)</span>
                </div>

                <div class="table-responsive">
                    <table class="table dss-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th class="text-end">Amount</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th>Risk</th>
                                <th>Applied</th>
                                <th class="text-end">Outstanding</th>
                                <th>Repayment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $dss['applications']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php
                                    $statusClass = match ($application['status']) {
                                        'Approved', 'Active', 'Ongoing' => 'bg-success-subtle text-success-emphasis',
                                        'Completed' => 'bg-primary-subtle text-primary-emphasis',
                                        'Rejected' => 'bg-danger-subtle text-danger-emphasis',
                                        default => 'bg-warning-subtle text-warning-emphasis',
                                    };
                                    $riskClass = match ($application['risk_level']) {
                                        'High Risk' => 'bg-danger-subtle text-danger-emphasis',
                                        'Medium Risk' => 'bg-warning-subtle text-warning-emphasis',
                                        'Low Risk' => 'bg-success-subtle text-success-emphasis',
                                        default => 'bg-secondary-subtle text-secondary-emphasis',
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($application['consumer']); ?></div>
                                        <?php if($application['account_number']): ?>
                                            <div class="small text-secondary"><?php echo e($application['account_number']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">₱<?php echo e(number_format($application['amount'], 2)); ?></td>
                                    <td><?php echo e($application['term_months']); ?> mo.</td>
                                    <td><span class="badge <?php echo e($statusClass); ?>"><?php echo e($application['status']); ?></span></td>
                                    <td><span class="badge <?php echo e($riskClass); ?>"><?php echo e($application['risk_level'] ?: 'Not assessed'); ?></span></td>
                                    <td class="small text-secondary"><?php echo e($application['applied_at'] ?: '—'); ?></td>
                                    <td class="text-end">
                                        <?php echo e($application['is_portfolio'] ? '₱'.number_format($application['outstanding'], 2) : '—'); ?>

                                    </td>
                                    <td>
                                        <?php if(! $application['is_portfolio']): ?>
                                            <span class="badge bg-light text-secondary border">Not applicable</span>
                                        <?php elseif($application['is_overdue']): ?>
                                            <span class="badge bg-danger">Overdue <?php echo e(number_format($application['overdue_days'])); ?>d</span>
                                        <?php elseif($application['amount'] <= 0): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis">No amount</span>
                                        <?php elseif($application['outstanding'] <= 0): ?>
                                            <span class="badge bg-success-subtle text-success-emphasis">Settled</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Current</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-secondary py-4">No loan applications match the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartData = <?php echo json_encode($dss['charts'], 15, 512) ?>;
        const defaultColors = ['#0d6efd', '#20c997', '#f59e0b', '#dc3545', '#6f42c1', '#0dcaf0', '#adb5bd'];

        const statusCanvas = document.getElementById('loanStatusChart');

        if (statusCanvas) {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.status.labels,
                    datasets: [{
                        data: chartData.status.counts,
                        backgroundColor: defaultColors,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
                    }
                }
            });
        }

        const monthlyCanvas = document.getElementById('loanMonthlyChart');

        if (monthlyCanvas) {
            new Chart(monthlyCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.monthly.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Applications',
                            data: chartData.monthly.applications,
                            backgroundColor: 'rgba(13, 110, 253, .78)',
                            borderRadius: 5,
                            yAxisID: 'y',
                        },
                        {
                            type: 'line',
                            label: 'Approvals',
                            data: chartData.monthly.approvals,
                            borderColor: '#6f42c1',
                            backgroundColor: 'rgba(111, 66, 193, .12)',
                            fill: false,
                            tension: .35,
                            pointRadius: 3,
                            borderDash: [5, 4],
                            yAxisID: 'y',
                        },
                        {
                            type: 'line',
                            label: 'Collections (₱)',
                            data: chartData.monthly.collections,
                            borderColor: '#20c997',
                            backgroundColor: 'rgba(32, 201, 151, .12)',
                            fill: false,
                            tension: .35,
                            pointRadius: 3,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, position: 'left' },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                callback: function (value) {
                                    return '₱' + Number(value).toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
                    }
                }
            });
        }

        const agingCanvas = document.getElementById('loanAgingChart');

        if (agingCanvas) {
            new Chart(agingCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.aging.labels,
                    datasets: [{
                        label: 'Outstanding amount',
                        data: chartData.aging.amounts,
                        backgroundColor: ['#20c997', '#0dcaf0', '#f59e0b', '#fd7e14', '#dc3545'],
                        borderRadius: 5,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return '₱' + Number(value).toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return '₱' + Number(context.raw).toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/dss/loans.blade.php ENDPATH**/ ?>