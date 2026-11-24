<?php ($activePage = 'reports'); ?>
<?php ($periodQuery = array_intersect_key($filters->toQuery(), array_flip(['month', 'year']))); ?>

<?php $__env->startSection('title', 'Reports Center'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .reports-catalog {
        position: sticky;
        top: 1rem;
    }

    .reports-catalog-category {
        color: #64748b;
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
        color: #334155;
        display: flex;
        gap: 0.65rem;
        padding: 0.6rem 0.7rem;
        text-decoration: none;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .reports-catalog-link:hover {
        background: #f1f5f9;
        color: #0f4c81;
    }

    .reports-catalog-link.active {
        background: #e8f2fb;
        color: #0f4c81;
        font-weight: 600;
    }

    .reports-catalog-link i {
        color: #0f4c81;
        font-size: 0.9rem;
        margin-top: 0.15rem;
    }

    .reports-catalog-link small {
        color: #94a3b8;
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
        border: 1px solid #e2e8f0;
        border-radius: 0.8rem;
        padding: 0.85rem 1rem;
    }

    .report-stat span {
        color: #64748b;
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .report-stat strong {
        color: #0f172a;
        display: block;
        font-size: 1.2rem;
        margin-top: 0.25rem;
        overflow-wrap: anywhere;
    }

    .report-table-wrap {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        max-height: 65vh;
        overflow: auto;
    }

    .report-table {
        font-size: 0.8rem;
        margin-bottom: 0;
    }

    .report-table thead th {
        background: #f8fafc;
        border-bottom: 1px solid #cbd5e1;
        color: #475569;
        font-size: 0.68rem;
        letter-spacing: 0.03em;
        position: sticky;
        text-transform: uppercase;
        top: 0;
        white-space: nowrap;
        z-index: 2;
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
        color: #64748b;
        font-size: 0.86rem;
        margin-bottom: 0;
        max-width: 760px;
    }

    .report-toolbar {
        align-items: flex-start;
        border-bottom: 1px solid #e2e8f0;
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
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app-shell d-lg-flex">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => $activePage], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Reports Center</h1>
                    <p class="mb-0">One place for operational, financial, loan, and connection reports. Every filter applies to the table and all three export formats.</p>
                </div>
            </div>

            <div class="row g-4" data-admin-records-root>
                <div class="col-xl-3">
                    <div class="panel-card reports-catalog p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <div class="fw-semibold">Report Catalog</div>
                                <div class="text-secondary small"><?php echo e(count($definitions)); ?> available reports</div>
                            </div>
                            <span class="badge text-bg-light border">Admin</span>
                        </div>

                        <div class="reports-catalog-links">
                            <?php $__currentLoopData = $groupedDefinitions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="reports-catalog-category"><?php echo e($category); ?></div>
                                <?php $__currentLoopData = $group; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $definition): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <a
                                        href="<?php echo e(route('reports.index', array_merge($periodQuery, ['report' => $definition->key]))); ?>"
                                        class="reports-catalog-link <?php echo e($definition->key === $reportKey ? 'active' : ''); ?>"
                                    >
                                        <i class="bi bi-file-earmark-bar-graph"></i>
                                        <span>
                                            <?php echo e($definition->label); ?>

                                            <small><?php echo e($definition->dateBasis); ?> &middot; <?php echo e(count($definition->columns)); ?> columns</small>
                                        </span>
                                    </a>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-9">
                    <div class="panel-card p-3 p-lg-4 mb-4" data-admin-freeze data-admin-freeze-section>
                        <form method="GET" action="<?php echo e(route('reports.index')); ?>" data-auto-filter data-auto-filter-debounce="450">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-lg-4">
                                    <label for="report" class="form-label small fw-semibold">Report</label>
                                    <select name="report" id="report" class="form-select">
                                        <?php $__currentLoopData = $groupedDefinitions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <optgroup label="<?php echo e($category); ?>">
                                                <?php $__currentLoopData = $group; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $definition): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($definition->key); ?>" <?php if($definition->key === $reportKey): echo 'selected'; endif; ?>>
                                                        <?php echo e($definition->label); ?>

                                                    </option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </optgroup>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>

                                <div class="col-6 col-md-3 col-lg-2">
                                    <label for="month" class="form-label small fw-semibold">Month</label>
                                    <select name="month" id="month" class="form-select">
                                        <option value="">All Months</option>
                                        <?php $__currentLoopData = range(1, 12); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $monthNumber): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($monthNumber); ?>" <?php if($filters->month === $monthNumber): echo 'selected'; endif; ?>>
                                                <?php echo e(\Illuminate\Support\Carbon::create(now()->year, $monthNumber, 1)->format('F')); ?>

                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>

                                <div class="col-6 col-md-3 col-lg-2">
                                    <label for="year" class="form-label small fw-semibold">Year</label>
                                    <select name="year" id="year" class="form-select">
                                        <option value="">All Years</option>
                                        <?php $__currentLoopData = $years; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($year); ?>" <?php if($filters->year === $year): echo 'selected'; endif; ?>><?php echo e($year); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>

                                <?php if($result->definition->statusOptions !== []): ?>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <label for="status" class="form-label small fw-semibold">Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <option value="">All Statuses</option>
                                            <?php $__currentLoopData = $result->definition->statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($status); ?>" <?php if($filters->status === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if($result->definition->categoryOptions !== []): ?>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <label for="category" class="form-label small fw-semibold">Category</label>
                                        <select name="category" id="category" class="form-select">
                                            <option value="">All Categories</option>
                                            <?php $__currentLoopData = $result->definition->categoryOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($category); ?>" <?php if($filters->category === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if($result->definition->channelOptions !== []): ?>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <label for="channel" class="form-label small fw-semibold">Channel</label>
                                        <select name="channel" id="channel" class="form-select">
                                            <option value="">All Channels</option>
                                            <?php $__currentLoopData = $result->definition->channelOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($channel); ?>" <?php if($filters->channel === $channel): echo 'selected'; endif; ?>><?php echo e($channel); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if($result->definition->typeOptions !== []): ?>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <label for="type" class="form-label small fw-semibold">Type</label>
                                        <select name="type" id="type" class="form-select">
                                            <option value="">All Types</option>
                                            <?php $__currentLoopData = $result->definition->typeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($type); ?>" <?php if($filters->type === $type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <div class="col-12 col-lg-auto d-flex gap-2">
                                    <noscript>
                                        <button type="submit" class="btn btn-primary">Apply filters</button>
                                    </noscript>
                                    <a href="<?php echo e(route('reports.index')); ?>" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="panel-card p-3 p-lg-4">
                        <div class="report-toolbar" data-admin-freeze>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h2 class="h5 mb-0"><?php echo e($result->definition->label); ?></h2>
                                    <span class="badge text-bg-light border"><?php echo e($result->definition->category); ?></span>
                                </div>
                                <p class="report-description mt-1"><?php echo e($result->definition->description); ?></p>
                                <div class="text-secondary small mt-2">
                                    <i class="bi bi-calendar3 me-1"></i><?php echo e($result->filters->periodLabel()); ?>

                                    <span class="mx-2">&middot;</span>
                                    Date basis: <?php echo e($result->definition->dateBasis); ?>

                                    <span class="mx-2">&middot;</span>
                                    <?php echo e(number_format($result->total)); ?> <?php echo e(\Illuminate\Support\Str::plural('record', $result->total)); ?>

                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="<?php echo e(route('reports.export', $result->exportQuery('pdf'))); ?>" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                </a>
                                <a href="<?php echo e(route('reports.export', $result->exportQuery('xlsx'))); ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                </a>
                                <a href="<?php echo e(route('reports.export', $result->exportQuery('csv'))); ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-filetype-csv me-1"></i> CSV
                                </a>
                            </div>
                        </div>

                        <div class="report-stats" data-admin-freeze>
                            <?php $__currentLoopData = $result->summary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="report-stat">
                                    <span><?php echo e($item['label']); ?></span>
                                    <strong><?php echo e(\App\Support\ReportValue::display($item['value'], $item['type'])); ?></strong>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <?php ($nowrapKeys = ['prn', 'account_number', 'reference_number', 'receipt_number', 'loan_id', 'reference', 'bill_prn', 'meter_number', 'request_type']); ?>
                        <div class="report-table-wrap">
                            <table class="table table-sm report-table" style="min-width: <?php echo e(max(950, count($result->definition->columns) * 110)); ?>px;">
                                <thead>
                                    <tr>
                                        <?php $__currentLoopData = $result->definition->columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php ($headerClass = $column['type'] === 'money' ? 'report-money' : ($column['type'] === 'number' ? 'report-number' : '')); ?>
                                            <th class="<?php echo e(trim($headerClass.' '.(in_array($key, $nowrapKeys, true) ? 'report-nowrap' : ''))); ?>">
                                                <?php echo e($column['label']); ?>

                                            </th>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $result->rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <?php $__currentLoopData = $result->definition->columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php ($value = $row[$key] ?? null); ?>
                                                <?php ($cellClass = match ($column['type']) {
                                                    'money' => 'report-money',
                                                    'number' => 'report-number',
                                                    'date' => 'report-date',
                                                    'datetime' => 'report-datetime',
                                                    default => '',
                                                }); ?>
                                                <?php ($cellClass = trim($cellClass.' '.(in_array($key, $nowrapKeys, true) ? 'report-nowrap' : ''))); ?>
                                                <td class="<?php echo e($cellClass); ?>">
                                                    <?php if($column['type'] === 'status'): ?>
                                                        <?php if($value === null || $value === ''): ?>
                                                            <span class="text-secondary">—</span>
                                                        <?php else: ?>
                                                            <?php ($tone = match (strtolower((string) $value)) {
                                                                'active', 'verified', 'paid', 'completed', 'resolved' => 'success',
                                                                'pending', 'for review', 'pending verification', 'pending review', 'unpaid', 'for consumer confirmation' => 'warning',
                                                                'rejected', 'overdue', 'disconnected', 'issued' => 'danger',
                                                                'for reconnection', 'ongoing', 'paid with balance', 'needs revisit', 'approved' => 'info',
                                                                default => 'secondary',
                                                            }); ?>
                                                            <span class="badge text-bg-<?php echo e($tone); ?>"><?php echo e($value); ?></span>
                                                        <?php endif; ?>
                                                    <?php elseif($column['type'] === 'long_text'): ?>
                                                        <span title="<?php echo e($value); ?>"><?php echo e(\Illuminate\Support\Str::limit((string) $value, 100) ?: '—'); ?></span>
                                                    <?php else: ?>
                                                        <?php echo e(\App\Support\ReportValue::display($value, $column['type'])); ?>

                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="<?php echo e(count($result->definition->columns)); ?>" class="text-center py-5 text-secondary">
                                                <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                                No records found for <?php echo e($result->filters->periodLabel()); ?>.
                                                Try another month, year, or reset the filters.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php ($paginator = $result->paginator()); ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <div class="text-secondary small">
                                <?php if($paginator->total() > 0): ?>
                                    Showing <?php echo e(number_format($paginator->firstItem())); ?>–<?php echo e(number_format($paginator->lastItem())); ?>

                                    of <?php echo e(number_format($paginator->total())); ?> records
                                <?php else: ?>
                                    No records to display
                                <?php endif; ?>
                            </div>
                            <?php echo e($paginator->links()); ?>

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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/reports/index.blade.php ENDPATH**/ ?>