<?php
    $activePage = 'dss-incidents';
?>

<?php $__env->startSection('title', 'Incident DSS'); ?>

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
        font-size: 1.55rem;
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

    .dss-rank {
        align-items: center;
        background: #eef6ff;
        border-radius: .7rem;
        color: #0d6efd;
        display: inline-flex;
        font-size: .8rem;
        font-weight: 800;
        height: 1.9rem;
        justify-content: center;
        width: 1.9rem;
    }

    .dss-progress {
        height: .48rem;
        background: #eef2f7;
        border-radius: 999px;
    }

    .dss-table td,
    .dss-table th {
        vertical-align: middle;
    }

    .dss-mini-label {
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
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
                    <h1>Incident DSS</h1>
                    <p>Decision support for incident hotspots, case backlog, and location data quality. Showing <?php echo e($dss['period_label']); ?>.</p>
                </div>
                <a href="<?php echo e(route('dss.loans')); ?>" class="btn btn-outline-primary">
                    <i class="bi bi-bank me-2"></i>Loan DSS
                </a>
            </div>

            <div class="panel-card dss-filter-card p-3 p-lg-4 mb-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <div class="dss-mini-label">Automatic filters</div>
                        <div class="small text-secondary">Every filter updates the analysis immediately. No Apply button is needed.</div>
                    </div>
                    <a href="<?php echo e(route('dss.incidents')); ?>" class="btn btn-sm btn-light border">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                </div>

                <form method="GET" action="<?php echo e(route('dss.incidents')); ?>" data-auto-filter data-auto-filter-debounce="450">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="dss_year">Year</label>
                            <select class="form-select" id="dss_year" name="year">
                                <?php $__currentLoopData = $dss['options']['years']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($year); ?>" <?php if((int) $dss['filters']['year'] === (int) $year): echo 'selected'; endif; ?>><?php echo e($year); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="dss_month">Month</label>
                            <select class="form-select" id="dss_month" name="month">
                                <option value="" <?php if($dss['filters']['month'] === null): echo 'selected'; endif; ?>>All months</option>
                                <?php $__currentLoopData = $dss['options']['months']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($month['value']); ?>" <?php if((int) $dss['filters']['month'] === (int) $month['value']): echo 'selected'; endif; ?>><?php echo e($month['label']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="dss_category">Category</label>
                            <select class="form-select" id="dss_category" name="category">
                                <option value="">All categories</option>
                                <?php $__currentLoopData = $dss['options']['categories']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($category); ?>" <?php if($dss['filters']['category'] === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="dss_status">Status</label>
                            <select class="form-select" id="dss_status" name="status">
                                <option value="">All statuses</option>
                                <?php $__currentLoopData = $dss['options']['statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status); ?>" <?php if($dss['filters']['status'] === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="dss_purok">Purok</label>
                            <select class="form-select" id="dss_purok" name="purok">
                                <option value="">All puroks</option>
                                <?php $__currentLoopData = $dss['options']['puroks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $purok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($purok['value']); ?>" <?php if($dss['filters']['purok'] === $purok['value']): echo 'selected'; endif; ?>><?php echo e($purok['label']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label" for="dss_block">Block</label>
                            <select class="form-select" id="dss_block" name="block">
                                <option value="">All blocks</option>
                                <?php $__currentLoopData = $dss['options']['blocks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $block): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($block['value']); ?>" <?php if($dss['filters']['block'] === $block['value']): echo 'selected'; endif; ?>><?php echo e($block['label']); ?></option>
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
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Incidents in period</div>
                        <div class="dss-kpi-value mt-2"><?php echo e(number_format($dss['summary']['total'])); ?></div>
                        <div class="small text-secondary mt-1">Matching the selected filters</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Still open</div>
                        <div class="dss-kpi-value mt-2"><?php echo e(number_format($dss['summary']['open'])); ?></div>
                        <div class="small text-secondary mt-1"><?php echo e(number_format($dss['summary']['pending'])); ?> pending review</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Resolved</div>
                        <div class="dss-kpi-value mt-2"><?php echo e(number_format($dss['summary']['resolved'])); ?></div>
                        <div class="small text-secondary mt-1"><?php echo e(number_format($dss['summary']['resolution_rate'], 1)); ?>% resolution rate</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Average resolution</div>
                        <div class="dss-kpi-value mt-2">
                            <?php echo e($dss['summary']['average_resolution_days'] !== null ? number_format($dss['summary']['average_resolution_days'], 1).' days' : 'N/A'); ?>

                        </div>
                        <div class="small text-secondary mt-1">
                            <?php echo e($dss['summary']['invalid_resolution_timestamps'] > 0 ? $dss['summary']['invalid_resolution_timestamps'].' invalid timestamp(s) excluded' : 'Valid resolved timestamps only'); ?>

                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="dss-kpi p-3 p-lg-4">
                        <div class="dss-kpi-label">Top hotspot</div>
                        <div class="dss-kpi-value mt-2 fs-6"><?php echo e($dss['summary']['top_location'] ?: 'No located hotspot'); ?></div>
                        <div class="small text-secondary mt-1">
                            <?php echo e($dss['summary']['top_category'] ? $dss['summary']['top_category'].' leads with '.number_format($dss['summary']['top_category_share'], 1).'% of reports' : 'Not enough data'); ?>

                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4" data-admin-records-root>
                <div class="col-xl-4">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Incident categories</h2>
                                <p class="small text-secondary mb-0">Count of reports by recorded category.</p>
                            </div>
                            <i class="bi bi-tags text-primary"></i>
                        </div>
                        <?php if($dss['charts']['has_data']): ?>
                            <div class="dss-chart-wrap">
                                <canvas id="incidentCategoryChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dss-empty-state">No incident data for the selected filters.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Reported and resolved trend</h2>
                                <p class="small text-secondary mb-0">Monthly reported volume and resolved cases in <?php echo e($dss['filters']['year']); ?>.</p>
                            </div>
                            <i class="bi bi-graph-up-arrow text-primary"></i>
                        </div>
                        <?php if($dss['charts']['trend']['has_data']): ?>
                            <div class="dss-chart-wrap">
                                <canvas id="incidentTrendChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dss-empty-state">No trend data for the selected year.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-7">
                    <div class="panel-card dss-chart-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Hotspot ranking</h2>
                                <p class="small text-secondary mb-0">Open and resolved incidents grouped by purok and block. Each incident is counted once.</p>
                            </div>
                            <i class="bi bi-geo-alt text-primary"></i>
                        </div>
                        <?php if(count($dss['charts']['hotspots']['labels']) > 0): ?>
                            <div class="dss-chart-wrap">
                                <canvas id="incidentHotspotChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dss-empty-state">No located incidents to rank. Missing locations are shown in the data-quality summary.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-xl-5">
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
            </div>

            <div class="row g-4">
                <div class="col-xl-7">
                    <div class="panel-card p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-1">Location hotspot details</h2>
                                <p class="small text-secondary mb-0">Ranked by total incidents, then by open cases.</p>
                            </div>
                            <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo e(count($dss['hotspots'])); ?> group(s)</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table dss-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Purok / Block</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Open</th>
                                        <th class="text-end">Resolved</th>
                                        <th class="text-end">Share</th>
                                        <th>Top category</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $dss['hotspots']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $hotspot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td><span class="dss-rank"><?php echo e($index + 1); ?></span></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($hotspot['label']); ?></div>
                                                <?php if($hotspot['is_partial']): ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis mt-1">Partial location data</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-semibold"><?php echo e(number_format($hotspot['total'])); ?></td>
                                            <td class="text-end"><?php echo e(number_format($hotspot['open'])); ?></td>
                                            <td class="text-end"><?php echo e(number_format($hotspot['resolved'])); ?></td>
                                            <td class="text-end"><?php echo e(number_format($hotspot['share'], 1)); ?>%</td>
                                            <td class="small text-secondary"><?php echo e($hotspot['top_category'] ?: 'Not recorded'); ?></td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-secondary py-4">No located hotspots for the selected filters.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="small text-secondary mt-3 mb-0">
                            Share is calculated from all matching incidents, including reports without a location.
                        </p>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="panel-card p-4 mb-4">
                        <h2 class="h6 fw-bold mb-3">Status and category breakdown</h2>
                        <div class="dss-mini-label mb-2">Status</div>
                        <?php $__currentLoopData = $dss['statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span><?php echo e($status['label']); ?></span>
                                <span class="text-secondary"><?php echo e(number_format($status['total'])); ?></span>
                            </div>
                            <div class="progress dss-progress mb-2">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo e($status['share']); ?>%" aria-valuenow="<?php echo e($status['share']); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        <div class="dss-mini-label mt-3 mb-2">Category</div>
                        <?php $__currentLoopData = $dss['categories']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span><?php echo e($category['label']); ?></span>
                                <span class="text-secondary"><?php echo e(number_format($category['total'])); ?></span>
                            </div>
                            <div class="progress dss-progress mb-2">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo e($category['share']); ?>%" aria-valuenow="<?php echo e($category['share']); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <div class="panel-card p-4">
                        <h2 class="h6 fw-bold mb-3">Data quality</h2>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Missing purok</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['missing_purok'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Missing block</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['missing_block'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Missing both</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['fully_unspecified'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="small text-secondary">Future-dated reports</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['future_reported_at'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="small text-secondary">Open more than 30 days</span>
                            <span class="fw-semibold"><?php echo e(number_format($dss['summary']['open_older_than_30_days'])); ?></span>
                        </div>

                        <?php if($dss['summary']['unlocated_total'] > 0): ?>
                            <div class="alert alert-warning border-0 rounded-3 small mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo e(number_format($dss['summary']['unlocated_total'])); ?> incident(s) have neither a purok nor a block. They remain in totals and category counts, but not in the hotspot ranking.
                            </div>
                        <?php endif; ?>
                    </div>
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

        const categoryCanvas = document.getElementById('incidentCategoryChart');

        if (categoryCanvas) {
            new Chart(categoryCanvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.categories.labels,
                    datasets: [{
                        data: chartData.categories.counts,
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

        const trendCanvas = document.getElementById('incidentTrendChart');

        if (trendCanvas) {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: chartData.trend.labels,
                    datasets: [
                        {
                            label: 'Reported',
                            data: chartData.trend.reported,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, .12)',
                            fill: true,
                            tension: .35,
                            pointRadius: 3,
                        },
                        {
                            label: 'Resolved (same reporting month)',
                            data: chartData.trend.resolved,
                            borderColor: '#20c997',
                            backgroundColor: 'rgba(32, 201, 151, .12)',
                            fill: false,
                            tension: .35,
                            pointRadius: 3,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
                    }
                }
            });
        }

        const hotspotCanvas = document.getElementById('incidentHotspotChart');

        if (hotspotCanvas) {
            new Chart(hotspotCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.hotspots.labels,
                    datasets: [
                        {
                            label: 'Open',
                            data: chartData.hotspots.open,
                            backgroundColor: '#f59e0b',
                            borderRadius: 5,
                        },
                        {
                            label: 'Resolved',
                            data: chartData.hotspots.resolved,
                            backgroundColor: '#20c997',
                            borderRadius: 5,
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                        y: { stacked: true }
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
                    }
                }
            });
        }
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/dss/incidents.blade.php ENDPATH**/ ?>