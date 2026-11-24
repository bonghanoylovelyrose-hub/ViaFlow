<?php
    $activePage = 'dashboard';
    $financial = $dashboard['financial'];
    $accounts = $dashboard['accounts'];
    $queueCount = collect($dashboard['queues'])->sum('count');
    $reportPeriod = $period->toQuery();
    $formatCardValue = function (array $card): string {
        return match ($card['type']) {
            'money' => '₱'.number_format((float) $card['value'], 2),
            'percent' => number_format((float) $card['value'], 1).'%',
            'volume' => number_format((float) $card['value'], 2).' m³',
            default => number_format((float) $card['value']),
        };
    };
    $toneClass = fn (string $tone): string => match ($tone) {
        'success' => 'dash-tone-success',
        'warning' => 'dash-tone-warning',
        'danger' => 'dash-tone-danger',
        'info' => 'dash-tone-info',
        default => 'dash-tone-primary',
    };
?>

<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .dash-kpi {
        background: var(--wb-surface);
        border: 1px solid var(--wb-border);
        border-radius: 1rem;
        height: 100%;
        padding: 1rem;
    }

    .dash-kpi-head {
        align-items: flex-start;
        display: flex;
        gap: 0.75rem;
        justify-content: space-between;
        margin-bottom: 0.8rem;
    }

    .dash-kpi-icon {
        align-items: center;
        border-radius: 0.8rem;
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 1.05rem;
        height: 2.5rem;
        justify-content: center;
        width: 2.5rem;
    }

    .dash-tone-primary {
        background: color-mix(in srgb, var(--wb-primary) 11%, var(--wb-surface));
        color: var(--wb-primary);
    }

    .dash-tone-success {
        background: color-mix(in srgb, var(--vf-green) 12%, var(--wb-surface));
        color: var(--vf-green);
    }

    .dash-tone-warning {
        background: color-mix(in srgb, #f59e0b 15%, var(--wb-surface));
        color: #b45309;
    }

    .dash-tone-danger {
        background: color-mix(in srgb, #ef4444 12%, var(--wb-surface));
        color: #dc2626;
    }

    .dash-tone-info {
        background: color-mix(in srgb, var(--vf-bright) 12%, var(--wb-surface));
        color: var(--vf-bright);
    }

    .dash-kpi-label {
        color: var(--wb-muted);
        font-size: 0.73rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .dash-kpi-value {
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.2;
        margin: 0.2rem 0 0.35rem;
    }

    .dash-kpi-note {
        color: var(--wb-muted);
        font-size: 0.76rem;
        line-height: 1.35;
        min-height: 2.05rem;
    }

    .dash-change {
        border: 1px solid var(--wb-border);
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 0.16rem 0.45rem;
        white-space: nowrap;
    }

    .dash-change-success {
        background: color-mix(in srgb, var(--vf-green) 10%, var(--wb-surface));
        border-color: color-mix(in srgb, var(--vf-green) 30%, var(--wb-surface));
        color: var(--vf-green);
    }

    .dash-change-danger {
        background: color-mix(in srgb, #ef4444 10%, var(--wb-surface));
        border-color: color-mix(in srgb, #ef4444 30%, var(--wb-surface));
        color: #dc2626;
    }

    .dash-change-neutral {
        background: color-mix(in srgb, var(--wb-text) 5%, var(--wb-surface));
        color: var(--wb-muted);
    }

    .dash-panel {
        display: flex;
        flex-direction: column;
        height: 100%;
        padding: 1.15rem;
    }

    .dash-panel-head {
        align-items: flex-start;
        display: flex;
        gap: 0.75rem;
        justify-content: space-between;
    }

    .dash-panel-title {
        font-size: 1rem;
        font-weight: 800;
        margin-bottom: 0.15rem;
    }

    .dash-panel-subtitle {
        color: var(--wb-muted);
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .dash-panel-link {
        color: var(--wb-primary);
        font-size: 0.78rem;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .dash-summary-grid {
        display: grid;
        gap: 0.55rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin-top: 0.9rem;
    }

    .dash-summary-item {
        background: color-mix(in srgb, var(--wb-primary) 4%, var(--wb-surface));
        border: 1px solid var(--wb-border);
        border-radius: 0.7rem;
        min-width: 0;
        padding: 0.6rem 0.7rem;
    }

    .dash-summary-label {
        color: var(--wb-muted);
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .dash-summary-value {
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.25;
        margin-top: 0.15rem;
        overflow-wrap: anywhere;
    }

    .dash-visual {
        height: 285px;
        margin-top: 0.9rem;
        min-width: 0;
        position: relative;
    }

    .dash-visual-sm {
        height: 225px;
    }

    .dash-empty {
        align-items: center;
        background: color-mix(in srgb, var(--wb-primary) 4%, var(--wb-surface));
        border: 1px dashed var(--wb-border);
        border-radius: 0.8rem;
        color: var(--wb-muted);
        display: flex;
        flex-direction: column;
        font-size: 0.82rem;
        gap: 0.35rem;
        justify-content: center;
        margin-top: 0.9rem;
        min-height: 190px;
        padding: 1rem;
        text-align: center;
    }

    .dash-list {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        margin-top: 0.9rem;
    }

    .dash-list-item {
        align-items: flex-start;
        background: color-mix(in srgb, var(--wb-primary) 4%, var(--wb-surface));
        border: 1px solid var(--wb-border);
        border-radius: 0.7rem;
        display: flex;
        gap: 0.55rem;
        padding: 0.55rem 0.65rem;
    }

    .dash-list-item-body {
        min-width: 0;
    }

    .dash-list-item-title {
        font-size: 0.82rem;
        font-weight: 800;
    }

    .dash-list-item-meta {
        color: var(--wb-muted);
        font-size: 0.73rem;
        line-height: 1.35;
    }

    .dash-dot {
        border-radius: 50%;
        display: inline-block;
        flex: 0 0 auto;
        height: 0.55rem;
        margin-top: 0.28rem;
        width: 0.55rem;
    }

    .dash-queue-item {
        align-items: flex-start;
        border: 1px solid var(--wb-border);
        border-radius: 0.8rem;
        color: var(--wb-text);
        display: flex;
        gap: 0.65rem;
        padding: 0.7rem;
        text-decoration: none;
        transition: background-color 0.15s ease, transform 0.15s ease;
    }

    .dash-queue-item:hover {
        background: color-mix(in srgb, var(--wb-primary) 5%, var(--wb-surface));
        color: var(--wb-text);
        transform: translateX(2px);
    }

    .dash-queue-icon {
        align-items: center;
        border-radius: 0.7rem;
        display: inline-flex;
        flex: 0 0 auto;
        height: 2rem;
        justify-content: center;
        width: 2rem;
    }

    .dash-queue-title {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        font-size: 0.82rem;
        font-weight: 800;
        gap: 0.4rem;
    }

    .dash-queue-description {
        color: var(--wb-muted);
        font-size: 0.73rem;
        line-height: 1.35;
        margin-top: 0.1rem;
    }

    .dash-queue-item .min-w-0 {
        min-width: 0;
    }

    .dash-queue-action {
        color: var(--wb-primary);
        font-size: 0.72rem;
        font-weight: 800;
        margin-top: 0.25rem;
    }

    .dash-status-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.8rem;
        margin-top: 0.8rem;
    }

    .dash-status-legend-item {
        align-items: center;
        color: var(--wb-muted);
        display: inline-flex;
        font-size: 0.75rem;
        gap: 0.35rem;
    }

    .dash-note {
        color: var(--wb-muted);
        font-size: 0.72rem;
        line-height: 1.4;
        margin-top: 0.75rem;
    }

    @media (max-width: 575.98px) {
        .dash-visual {
            height: 245px;
        }

        .dash-summary-grid {
            grid-template-columns: 1fr;
        }

    }
    /* Align each KPI's header, title, figure and note across the row. */
    .dashboard-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-left: 0; margin-right: 0; }
    .dashboard-kpi-grid > div { width: auto; min-width: 0; padding: 0; margin: 0; }
    .dash-kpi-label { line-height: 1.3; min-height: 1.9em; }
    .dash-kpi-value, .dash-summary-value, .dash-change, .dash-status-legend-item strong { font-variant-numeric: tabular-nums; }
    .dash-summary-item { min-width: 0; }
    .dash-summary-label { min-height: 2.5em; }
    @supports (grid-template-rows: subgrid) {
        .dashboard-kpi-grid > div { display: grid; grid-row: span 4; grid-template-rows: subgrid; }
        .dashboard-kpi-grid .dash-kpi { display: grid; grid-row: span 4; grid-template-rows: subgrid; row-gap: 0; }
    }
    @media (max-width: 1199.98px) { .dashboard-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .dashboard-kpi-grid { grid-template-columns: minmax(0, 1fr); } .dash-summary-label { min-height: 0; } }
    /* Compact KPI cards, retaining the shared row alignment. */
    .dashboard-kpi-grid { gap: 0.75rem; }
    .dashboard-kpi-grid .dash-kpi { padding: 0.875rem; min-height: 8.5rem; }
    .dash-kpi-head { margin-bottom: 0.3rem; gap: 0.5rem; }
    .dash-kpi-icon { width: 2.1875rem; height: 2.1875rem; font-size: 1.0625rem; }
    .dash-change { font-size: 0.625rem; line-height: 1.25; padding: 0.15rem 0.4rem; }
    .dash-kpi-label { font-size: 0.6875rem; font-weight: 600; line-height: 1.3; min-height: 1.3em; }
    .dash-kpi-value { font-size: 1.4375rem; line-height: 1.15; margin: 0.15rem 0 0.2rem; font-variant-numeric: tabular-nums; }
    .dash-kpi-note { font-size: 0.6875rem; line-height: 1.3; min-height: 0; }
</style>
<?php echo $__env->make('admin.dashboard-water-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app-shell admin-dashboard d-lg-flex">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => $activePage], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Dashboard</h1>
                    <p class="mb-0">Financial, operational, and community metrics from all available system records.</p>
                </div>
            </div>

            <div class="row g-3 mb-4 dashboard-kpi-grid">
                <?php $__currentLoopData = $dashboard['cards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="dash-kpi">
                            <div class="dash-kpi-head">
                                <span class="dash-kpi-icon <?php echo e($toneClass($card['tone'])); ?>">
                                    <i class="bi <?php echo e($card['icon']); ?>"></i>
                                </span>
                                <span class="dash-change dash-change-<?php echo e($card['change']['tone']); ?>">
                                    <?php echo e($card['change']['label']); ?>

                                </span>
                            </div>
                            <div class="dash-kpi-label"><?php echo e($card['label']); ?></div>
                            <div class="dash-kpi-value"><?php echo e($formatCardValue($card)); ?></div>
                            <div class="dash-kpi-note"><?php echo e($card['note']); ?></div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="row g-4 mb-4" data-admin-natural-page-scroll>
                <div class="col-12">
                    <div class="panel-card dash-panel">
                        <div class="dash-panel-head">
                            <div>
                                <div class="dash-panel-title">Billing vs. Collections</div>
                                <div class="dash-panel-subtitle">
                                    Monthly totals group records from all available years. The line is the collection rate on new charges.
                                </div>
                            </div>
                            <a href="<?php echo e(route('reports.index', array_merge($reportPeriod, ['report' => 'billing']))); ?>" class="dash-panel-link">
                                Billing report <i class="bi bi-arrow-up-right"></i>
                            </a>
                        </div>

                        <div class="dash-summary-grid">
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">New charges</div>
                                <div class="dash-summary-value">₱<?php echo e(number_format($financial['new_charges'], 2)); ?></div>
                            </div>
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">Gross billed</div>
                                <div class="dash-summary-value">₱<?php echo e(number_format($financial['gross_billed'], 2)); ?></div>
                            </div>
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">Collected</div>
                                <div class="dash-summary-value">₱<?php echo e(number_format($financial['collected'], 2)); ?></div>
                            </div>
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">Carry-forward</div>
                                <div class="dash-summary-value">₱<?php echo e(number_format($financial['carry_forward'], 2)); ?></div>
                            </div>
                        </div>

                        <?php if($dashboard['charts']['financial']['has_data']): ?>
                            <div class="dash-visual">
                                <canvas id="financialChart" aria-label="Monthly new charges, collections, and collection rate" role="img"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty">
                                <i class="bi bi-bar-chart fs-4"></i>
                                <span>No billing or collection records are available.</span>
                            </div>
                        <?php endif; ?>

                        <div class="dash-note">
                            Gross billed includes carried arrears. New charges exclude them so the collection rate measures actual billings, not reused debt.
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="panel-card dash-panel">
                        <div class="dash-panel-head">
                            <div>
                                <div class="dash-panel-title">Needs Attention</div>
                                <div class="dash-panel-subtitle">Current operational queues, not period metrics.</div>
                            </div>
                            <span class="badge badge-soft-warning"><?php echo e(number_format($queueCount)); ?></span>
                        </div>

                        <div class="dash-list">
                            <?php $__empty_1 = true; $__currentLoopData = $dashboard['queues']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $queue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <a href="<?php echo e($queue['url']); ?>" class="dash-queue-item">
                                    <span class="dash-queue-icon <?php echo e($toneClass($queue['tone'])); ?>">
                                        <i class="bi <?php echo e($queue['icon']); ?>"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="dash-queue-title">
                                            <?php echo e($queue['label']); ?>

                                            <span class="badge badge-soft-primary"><?php echo e(number_format($queue['count'])); ?></span>
                                        </span>
                                        <span class="dash-queue-description d-block"><?php echo e($queue['description']); ?></span>
                                        <span class="dash-queue-action d-block"><?php echo e($queue['action']); ?> <i class="bi bi-arrow-right"></i></span>
                                    </span>
                                </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <div class="dash-empty">
                                    <i class="bi bi-check2-circle fs-4"></i>
                                    <span>No immediate action items right now.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="panel-card dash-panel">
                        <div class="dash-panel-head">
                            <div>
                                <div class="dash-panel-title">Consumer Status</div>
                                <div class="dash-panel-subtitle">Current snapshot of all registered accounts.</div>
                            </div>
                            <span class="badge badge-soft-primary">Current</span>
                        </div>

                        <div class="dash-summary-grid">
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">Accounts</div>
                                <div class="dash-summary-value"><?php echo e(number_format($accounts['total'])); ?></div>
                            </div>
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">Verified</div>
                                <div class="dash-summary-value"><?php echo e(number_format($accounts['verified_accounts'])); ?></div>
                            </div>
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">New accounts</div>
                                <div class="dash-summary-value"><?php echo e(number_format($accounts['new_accounts'])); ?></div>
                            </div>
                            <div class="dash-summary-item">
                                <div class="dash-summary-label">Members</div>
                                <div class="dash-summary-value"><?php echo e(number_format($accounts['members'])); ?></div>
                            </div>
                        </div>

                        <?php if($dashboard['charts']['account_status']['has_data']): ?>
                            <div class="dash-visual dash-visual-sm">
                                <canvas id="accountStatusChart" aria-label="Consumer accounts by current status" role="img"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty">
                                <i class="bi bi-people fs-4"></i>
                                <span>No consumer accounts found.</span>
                            </div>
                        <?php endif; ?>

                        <div class="dash-status-legend">
                            <?php $__currentLoopData = $accounts['labels']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <span class="dash-status-legend-item">
                                    <span class="dash-dot" style="background: <?php echo e(['var(--vf-royal)', '#f59e0b', 'var(--vf-bright)', '#ef4444', '#8b5cf6', 'var(--vf-royal)'][$index] ?? 'var(--vf-royal)'); ?>;"></span>
                                    <?php echo e($label); ?> <strong><?php echo e(number_format($accounts['counts'][$index])); ?></strong>
                                </span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <?php if($accounts['pending_membership'] > 0): ?>
                            <div class="alert alert-warning mt-3 mb-0 py-2 px-3">
                                <div class="fw-bold">
                                    <?php echo e(number_format($accounts['pending_membership'])); ?> pending verification<?php echo e($accounts['pending_membership'] === 1 ? '' : 's'); ?>

                                </div>
                                <a href="<?php echo e(route('consumers.index', ['membership' => 'pending_member_verification'])); ?>" class="small fw-bold">
                                    Review <?php echo e(number_format($accounts['pending_membership'])); ?> pending membership verification<?php echo e($accounts['pending_membership'] === 1 ? '' : 's'); ?>

                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="dash-note">Status history is not stored, so this chart is a current snapshot and does not change with the month filter.</div>
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
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Chart === 'undefined') {
            return;
        }

        const chartData = <?php echo json_encode($dashboard['charts'], 15, 512) ?>;
        const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 });
        const pesoWhole = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
        const whole = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 });
        const decimal = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 });
        const selectedColor = '#0f4c81';
        const billedColor = 'rgba(15, 110, 140, 0.55)';
        const collectedColor = 'rgba(22, 163, 74, 0.58)';
        const neutralGrid = 'rgba(15, 110, 140, 0.08)';

        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = '#64748b';
        Chart.defaults.borderColor = neutralGrid;
        Chart.defaults.maintainAspectRatio = false;

        const selectedIndex = chartData.financial.selected_index;
        const financialCanvas = document.getElementById('financialChart');
        if (financialCanvas) {
            const billedColors = chartData.financial.billed.map((_, index) => index === selectedIndex ? selectedColor : billedColor);
            const collectedColors = chartData.financial.collected.map((_, index) => index === selectedIndex ? '#15803d' : collectedColor);

            new Chart(financialCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'New charges',
                            data: chartData.financial.billed,
                            backgroundColor: billedColors,
                            borderRadius: 7,
                            maxBarThickness: 30,
                            yAxisID: 'y',
                        },
                        {
                            type: 'bar',
                            label: 'Collections',
                            data: chartData.financial.collected,
                            backgroundColor: collectedColors,
                            borderRadius: 7,
                            maxBarThickness: 30,
                            yAxisID: 'y',
                        },
                        {
                            type: 'line',
                            label: 'Collection rate',
                            data: chartData.financial.collection_rate,
                            borderColor: '#f59e0b',
                            backgroundColor: '#f59e0b',
                            borderWidth: 2,
                            pointRadius: chartData.financial.collection_rate.map((value, index) => value === null ? 0 : (index === selectedIndex ? 5 : 3)),
                            pointHoverRadius: 5,
                            tension: 0.35,
                            spanGaps: true,
                            yAxisID: 'rate',
                        },
                    ],
                },
                options: {
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.dataset.yAxisID === 'rate'
                                    ? `${context.dataset.label}: ${context.parsed.y === null ? 'n/a' : decimal.format(context.parsed.y) + '%'}`
                                    : `${context.dataset.label}: ${peso.format(context.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { callback: (value) => pesoWhole.format(value) }, grid: { color: neutralGrid } },
                        rate: {
                            beginAtZero: true,
                            position: 'right',
                            suggestedMax: 100,
                            grid: { drawOnChartArea: false },
                            ticks: { callback: (value) => decimal.format(value) + '%' },
                        },
                    },
                },
            });
        }

        const outstandingCanvas = document.getElementById('outstandingChart');
        if (outstandingCanvas) {
            new Chart(outstandingCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.outstanding.labels,
                    datasets: [{
                        label: 'Outstanding balance',
                        data: chartData.outstanding.amounts,
                        backgroundColor: ['#16a34a', '#f59e0b', '#f97316', '#ef4444', '#991b1b'],
                        borderRadius: 6,
                        maxBarThickness: 24,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${peso.format(context.parsed.x)} · ${whole.format(chartData.outstanding.accounts[context.dataIndex] || 0)} accounts`,
                            },
                        },
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { callback: (value) => pesoWhole.format(value) }, grid: { color: neutralGrid } },
                        y: { grid: { display: false } },
                    },
                },
            });
        }

        const accountStatusCanvas = document.getElementById('accountStatusChart');
        if (accountStatusCanvas) {
            const statusColors = ['#0754C8', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6', '#94a3b8'];
            const statusTotal = chartData.account_status.counts.reduce((sum, value) => sum + Number(value || 0), 0);

            new Chart(accountStatusCanvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.account_status.labels,
                    datasets: [{
                        data: chartData.account_status.counts,
                        backgroundColor: chartData.account_status.labels.map((_, index) => statusColors[index] || '#94a3b8'),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    cutout: '66%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = Number(context.parsed || 0);
                                    const percent = statusTotal > 0 ? (value / statusTotal) * 100 : 0;

                                    return `${context.label}: ${whole.format(value)} (${decimal.format(percent)}%)`;
                                },
                            },
                        },
                    },
                },
            });
        }

        const consumptionCanvas = document.getElementById('consumptionChart');
        if (consumptionCanvas) {
            const consumptionColors = chartData.consumption.totals.map((_, index) => index === chartData.consumption.selected_index ? selectedColor : billedColor);

            new Chart(consumptionCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Total consumption (m³)',
                            data: chartData.consumption.totals,
                            backgroundColor: consumptionColors,
                            borderRadius: 7,
                            maxBarThickness: 30,
                            yAxisID: 'y',
                        },
                        {
                            type: 'line',
                            label: 'Average per reading (m³)',
                            data: chartData.consumption.averages,
                            borderColor: '#3b82f6',
                            backgroundColor: '#3b82f6',
                            borderWidth: 2,
                            pointRadius: chartData.consumption.averages.map((value, index) => Number(value) > 0 ? (index === chartData.consumption.selected_index ? 5 : 3) : 0),
                            pointHoverRadius: 5,
                            tension: 0.35,
                            yAxisID: 'avg',
                        },
                    ],
                },
                options: {
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.dataset.label}: ${decimal.format(context.parsed.y)} m³`,
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { callback: (value) => decimal.format(value) }, grid: { color: neutralGrid } },
                        avg: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { callback: (value) => decimal.format(value) + ' m³' },
                        },
                    },
                },
            });
        }

        const loansCanvas = document.getElementById('loansChart');
        if (loansCanvas) {
            new Chart(loansCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Applications',
                            data: chartData.loans.applications,
                            backgroundColor: chartData.loans.applications.map((_, index) => index === chartData.loans.selected_index ? selectedColor : billedColor),
                            borderRadius: 7,
                            maxBarThickness: 30,
                            yAxisID: 'count',
                        },
                        {
                            type: 'line',
                            label: 'Repayments posted',
                            data: chartData.loans.repayments,
                            borderColor: '#f59e0b',
                            backgroundColor: '#f59e0b',
                            borderWidth: 2,
                            pointRadius: chartData.loans.repayments.map((value, index) => Number(value) > 0 ? (index === chartData.loans.selected_index ? 5 : 3) : 0),
                            pointHoverRadius: 5,
                            tension: 0.35,
                            yAxisID: 'money',
                        },
                    ],
                },
                options: {
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.dataset.yAxisID === 'money'
                                    ? `${context.dataset.label}: ${peso.format(context.parsed.y)}`
                                    : `${context.dataset.label}: ${whole.format(context.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false } },
                        count: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: neutralGrid } },
                        money: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { callback: (value) => pesoWhole.format(value) },
                        },
                    },
                },
            });
        }

        const incidentStatusCanvas = document.getElementById('incidentStatusChart');
        if (incidentStatusCanvas) {
            const incidentStatusColors = {
                'Pending': '#f59e0b',
                'Ongoing': '#3b82f6',
                'For Consumer Confirmation': '#8b5cf6',
                'Resolved': '#16a34a',
            };

            new Chart(incidentStatusCanvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.incidents.status_labels,
                    datasets: [{
                        data: chartData.incidents.status_counts,
                        backgroundColor: chartData.incidents.status_labels.map((label) => incidentStatusColors[label] || '#94a3b8'),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    cutout: '64%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.label}: ${whole.format(context.parsed || 0)}`,
                            },
                        },
                    },
                },
            });
        }

        const incidentCategoryCanvas = document.getElementById('incidentCategoryChart');
        if (incidentCategoryCanvas) {
            new Chart(incidentCategoryCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.incidents.category_labels,
                    datasets: [{
                        label: 'Incidents',
                        data: chartData.incidents.category_counts,
                        backgroundColor: 'rgba(15, 110, 140, 0.65)',
                        borderRadius: 6,
                        maxBarThickness: 24,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${whole.format(context.parsed.x)} incidents`,
                            },
                        },
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: neutralGrid } },
                        y: { grid: { display: false } },
                    },
                },
            });
        }
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>