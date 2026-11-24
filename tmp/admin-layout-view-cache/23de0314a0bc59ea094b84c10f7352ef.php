<?php
    $activePage = 'payments';
    $formatPaymentReference = fn (string $reference): string => preg_replace('/^\D+/', '', str_replace('-', '', $reference)) ?: $reference;
?>

<?php $__env->startSection('title', 'Payments'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .payments-shell {
        display: grid;
        gap: 1.25rem;
    }

    .payments-page-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem 1.5rem;
    }

    .payments-page-heading-copy {
        flex: 1 1 420px;
        min-width: 0;
        max-width: 72ch;
    }

    .payments-page-heading-copy p {
        max-width: 70ch;
        margin-bottom: 0;
    }

    .payments-heading-actions {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
    }

    .payments-heading-actions .btn {
        white-space: nowrap;
    }

    .payments-alert-stack {
        display: grid;
        gap: 0.85rem;
    }

    .payments-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        align-items: stretch;
    }

    .payments-summary-card {
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100%;
        padding: 1.15rem 1.25rem;
        gap: 0.5rem;
    }

    .payments-summary-label {
        color: var(--wb-muted);
        font-size: 0.84rem;
        font-weight: 700;
        line-height: 1.35;
        min-height: 2.7em;
    }

    .payments-summary-value {
        margin: 0;
        color: var(--wb-text);
        font-size: clamp(1.45rem, 2vw, 1.9rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: 0;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .payments-summary-value-amount {
        font-size: clamp(1.25rem, 1.7vw, 1.65rem);
    }

    .payments-summary-badge {
        align-self: flex-start;
        margin-top: auto;
        white-space: nowrap;
    }

    .payments-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr);
        gap: 1.25rem;
        align-items: stretch;
    }

    .payments-panel {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-width: 0;
        height: 100%;
    }

    .payments-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
    }

    .payments-panel-head-copy {
        flex: 1 1 220px;
        min-width: 0;
    }

    .payments-panel-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .payments-panel-copy {
        margin: 0.25rem 0 0;
        color: var(--wb-muted);
        font-size: 0.88rem;
        line-height: 1.45;
    }

    .payments-panel-badge {
        flex: 0 0 auto;
        align-self: flex-start;
        white-space: nowrap;
    }

    .payments-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    .payments-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
    }

    .payments-open-table {
        min-width: 490px;
    }

    .payments-history-table {
        min-width: 780px;
    }

    .payments-table thead th {
        padding: 0.75rem 0.55rem;
        background: rgba(244, 248, 251, 0.98);
        color: #5f6b76;
        font-size: 0.69rem;
        line-height: 1.25;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        white-space: nowrap;
        border-bottom: 1px solid rgba(219, 230, 238, 0.9);
        vertical-align: middle;
    }

    .payments-table tbody td {
        padding: 0.78rem 0.55rem;
        vertical-align: middle;
        border-bottom: 1px solid rgba(219, 230, 238, 0.55);
        background: rgba(255, 255, 255, 0.94);
        color: var(--wb-text);
        font-size: 0.875rem;
    }

    .payments-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .payments-table thead th:first-child,
    .payments-table tbody td:first-child {
        padding-left: 0.75rem;
    }

    .payments-table thead th:last-child,
    .payments-table tbody td:last-child {
        padding-right: 0.75rem;
    }

    .payments-table .payments-cell-nowrap,
    .payments-table .payments-currency,
    .payments-table .payments-date {
        white-space: nowrap;
    }

    .payments-table .payments-currency {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }

    .payments-cell-primary {
        font-weight: 700;
    }

    .payments-cell-meta {
        color: var(--wb-muted);
        font-size: 0.7rem;
        line-height: 1.35;
        white-space: nowrap;
    }

    .payments-date {
        font-size: 0.8rem;
    }

    .payments-open-table .payments-col-prn {
        min-width: 145px;
    }

    .payments-open-table .payments-col-consumer {
        min-width: 155px;
    }

    .payments-open-table .payments-col-balance {
        min-width: 100px;
    }

    .payments-open-table .payments-col-status {
        min-width: 85px;
    }

    .payments-history-table .payments-col-reference {
        min-width: 120px;
    }

    .payments-history-table .payments-col-consumer {
        min-width: 120px;
    }

    .payments-history-table .payments-col-period {
        min-width: 62px;
    }

    .payments-history-table .payments-col-amount {
        min-width: 78px;
    }

    .payments-history-table .payments-col-remaining {
        min-width: 90px;
    }

    .payments-history-table .payments-col-status {
        min-width: 74px;
    }

    .payments-history-table .payments-col-date {
        min-width: 76px;
    }

    .payments-history-table .payments-col-actions {
        min-width: 150px;
    }

    .payments-history-table thead th.payments-col-period,
    .payments-history-table thead th.payments-col-amount,
    .payments-history-table thead th.payments-col-remaining {
        white-space: normal;
    }

    .payments-history-table thead th.payments-col-actions,
    .payments-history-table tbody td.payments-col-actions {
        border-left: 1px solid rgba(219, 230, 238, 0.75);
    }

    .payments-row-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        gap: 0.35rem;
        white-space: nowrap;
    }

    .payments-row-actions .btn {
        min-width: 70px;
        padding-inline: 0.5rem;
        white-space: nowrap;
    }

    .payments-filter-form {
        display: grid;
        grid-template-columns: minmax(200px, 1fr) minmax(130px, 0.55fr) minmax(130px, 0.55fr) auto;
        align-items: center;
        gap: 0.65rem;
        width: 100%;
        margin-bottom: 1rem;
    }

    .payments-filter-form .form-control,
    .payments-filter-form .form-select {
        min-height: 2.55rem;
        height: 2.55rem;
        background: #fff !important;
        background-color: #fff !important;
        color: var(--wb-text) !important;
        border-color: rgba(219, 230, 238, 0.8) !important;
        font-size: 0.88rem;
    }

    .payments-filter-form .form-control::placeholder {
        color: var(--wb-muted) !important;
    }

    .payments-filter-form .form-select option {
        background: #fff;
        color: var(--wb-text);
    }

    .payments-filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .payments-filter-actions .btn {
        min-height: 2.55rem;
        white-space: nowrap;
    }

    .payments-summary-note {
        margin: 0;
        color: var(--wb-muted);
        font-size: 0.86rem;
    }

    .payments-action-note {
        color: var(--wb-muted);
        font-size: 0.85rem;
    }

    .payments-pagination {
        display: flex;
        justify-content: flex-start;
        overflow-x: auto;
    }

    .payments-pagination nav {
        width: 100%;
    }

    .payments-empty-state {
        padding: 2.75rem 1rem;
        color: var(--wb-muted);
        text-align: center;
    }

    .payment-consumer-picker {
        position: relative;
    }

    .payment-consumer-results {
        position: absolute;
        top: calc(100% + 0.4rem);
        right: 0;
        left: 0;
        z-index: 1080;
        max-height: min(20rem, 55vh);
        overflow-y: auto;
        padding: 0.35rem;
        background: var(--wb-surface);
        border: 1px solid var(--wb-border);
        border-radius: 0.9rem;
        box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.15);
    }

    .payment-consumer-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
        width: 100%;
        padding: 0.7rem 0.75rem;
        border: 0;
        border-radius: 0.7rem;
        background: transparent;
        color: var(--wb-text);
        text-align: left;
    }

    .payment-consumer-option + .payment-consumer-option {
        margin-top: 0.15rem;
    }

    .payment-consumer-option:hover,
    .payment-consumer-option.is-active {
        background: color-mix(in srgb, var(--wb-primary) 8%, var(--wb-surface));
    }

    .payment-consumer-option:focus-visible {
        outline: 2px solid var(--wb-primary);
        outline-offset: -2px;
    }

    .payment-consumer-option-main,
    .payment-consumer-option-side {
        display: flex;
        flex-direction: column;
        gap: 0.22rem;
        min-width: 0;
    }

    .payment-consumer-option-main {
        flex: 1 1 auto;
    }

    .payment-consumer-option-side {
        flex: 0 0 auto;
        align-items: flex-end;
        text-align: right;
    }

    .payment-consumer-option-name {
        overflow: hidden;
        font-size: 0.86rem;
        font-weight: 800;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payment-consumer-option-meta {
        overflow: hidden;
        color: var(--wb-muted);
        font-size: 0.72rem;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payment-consumer-option-balance {
        color: var(--wb-text);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        white-space: nowrap;
    }

    .payment-consumer-option .consumer-service-status {
        gap: 0.18rem;
    }

    .payment-consumer-empty {
        padding: 1rem 0.85rem;
        color: var(--wb-muted);
        font-size: 0.84rem;
        text-align: center;
    }

    .payment-consumer-card {
        display: grid;
        gap: 0.9rem;
        padding: 1rem;
        background: color-mix(in srgb, var(--wb-primary) 4%, var(--wb-surface));
        border: 1px solid var(--wb-border);
        border-radius: 1rem;
    }

    .payment-consumer-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
    }

    .payment-consumer-card-identity {
        flex: 1 1 260px;
        min-width: 0;
    }

    .payment-consumer-card-name {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .payment-consumer-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 1rem;
        margin-top: 0.35rem;
        color: var(--wb-muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .payment-consumer-card-meta strong {
        color: var(--wb-text);
        font-weight: 700;
    }

    .payment-consumer-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .payment-consumer-stat {
        min-width: 0;
        padding: 0.75rem 0.85rem;
        background: var(--wb-surface);
        border: 1px solid var(--wb-border);
        border-radius: 0.8rem;
    }

    .payment-consumer-stat-label {
        color: var(--wb-muted);
        font-size: 0.69rem;
        font-weight: 800;
        letter-spacing: 0.035em;
        line-height: 1.3;
        text-transform: uppercase;
    }

    .payment-consumer-stat-value {
        margin-top: 0.3rem;
        color: var(--wb-text);
        font-size: 1.2rem;
        font-variant-numeric: tabular-nums;
        font-weight: 800;
        line-height: 1.15;
    }

    .payment-consumer-stat-value.is-settled {
        color: #15803d;
    }

    .payment-consumer-balance-note {
        margin: 0;
        color: var(--wb-muted);
        font-size: 0.78rem;
        line-height: 1.45;
    }

    .payment-amount-feedback {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.78rem;
    }

    .payment-amount-input {
        appearance: textfield;
        -moz-appearance: textfield;
    }

    .payment-amount-input::-webkit-outer-spin-button,
    .payment-amount-input::-webkit-inner-spin-button {
        margin: 0;
        -webkit-appearance: none;
    }

    @media (max-width: 575.98px) {
        .payment-consumer-option {
            align-items: flex-start;
            flex-direction: column;
        }

        .payment-consumer-option-side {
            align-items: flex-start;
            text-align: left;
        }

        .payment-consumer-stats {
            grid-template-columns: 1fr;
        }
    }

    .payment-proof-preview {
        border: 1px solid rgba(219, 230, 238, 0.95);
        border-radius: 1rem;
        overflow: hidden;
        background: #f8fbfd;
    }

    .payment-proof-preview img,
    .payment-proof-preview iframe {
        width: 100%;
        display: block;
        border: 0;
    }

    .payment-proof-preview img {
        max-height: 420px;
        object-fit: contain;
        background: #fff;
    }

    .payment-proof-preview iframe {
        height: 420px;
        background: #fff;
    }

    @media (max-width: 1849.98px) {
        .payments-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1199.98px) {
        .payments-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .payments-page-heading {
            align-items: stretch;
        }

        .payments-page-heading-copy {
            flex: 0 1 auto;
        }

        .payments-heading-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .payments-filter-form {
            grid-template-columns: minmax(0, 1fr) minmax(130px, 0.5fr) minmax(130px, 0.5fr) auto;
        }
    }

    @media (max-width: 767.98px) {
        .payments-page-heading {
            flex-direction: column;
            align-items: stretch;
        }

        .payments-heading-actions {
            width: 100%;
        }

        .payments-heading-actions .btn {
            width: 100%;
        }

        .payments-summary-label {
            min-height: 0;
        }

        .payments-panel-head {
            flex-direction: column;
            align-items: stretch;
        }

        .payments-panel-head-copy {
            flex: 0 1 auto;
        }

        .payments-filter-form {
            grid-template-columns: 1fr 1fr;
        }

        .payments-filter-search,
        .payments-filter-actions {
            grid-column: 1 / -1;
        }

        .payments-filter-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .payments-summary-grid {
            grid-template-columns: 1fr;
        }

        .payments-filter-form {
            grid-template-columns: 1fr;
        }

        .payments-filter-search,
        .payments-filter-actions {
            grid-column: auto;
        }

        .payments-filter-actions {
            justify-content: stretch;
        }

        .payments-filter-actions .btn {
            flex: 1 1 auto;
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
            <div class="page-heading payments-page-heading">
                <div class="payments-page-heading-copy">
                    <h1>Payment Monitoring</h1>
                    <p>Manage counter and online payments, verify incoming submissions, and generate digital receipts for verified payments.</p>
                </div>
                <div class="payments-heading-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="bi bi-wallet2 me-2"></i>Record Payment</button>
                </div>
            </div>

            <div class="payments-shell">
                <?php if(session('success') || $errors->any()): ?>
                    <div class="payments-alert-stack">
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
                    </div>
                <?php endif; ?>

                <div class="payments-summary-grid">
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Pending Verification</div>
                        <h2 class="payments-summary-value"><?php echo e(number_format($stats['pending'])); ?></h2>
                        <span class="badge badge-soft-warning payments-summary-badge">Awaiting review</span>
                    </div>
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Verified Today</div>
                        <h2 class="payments-summary-value"><?php echo e(number_format($stats['verified_today'])); ?></h2>
                        <span class="badge badge-soft-success payments-summary-badge">Cleared today</span>
                    </div>
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Online Submissions</div>
                        <h2 class="payments-summary-value"><?php echo e(number_format($stats['online_submissions'])); ?></h2>
                        <span class="badge badge-soft-primary payments-summary-badge">Digital queue</span>
                    </div>
                    <div class="dashboard-card payments-summary-card">
                        <div class="payments-summary-label">Verified Amount Today</div>
                        <h2 class="payments-summary-value payments-summary-value-amount">P <?php echo e(number_format($stats['verified_amount_today'], 2)); ?></h2>
                        <span class="badge badge-soft-info payments-summary-badge">Daily intake</span>
                    </div>
                </div>

                <div class="payments-grid" data-admin-records-root>
                    <div class="panel-card p-4 h-100 payments-panel" data-admin-freeze-section>
                        <div class="payments-panel-head" data-admin-freeze>
                            <div class="payments-panel-head-copy">
                                <h2 class="payments-panel-title">Open Bills for Payment</h2>
                                <p class="payments-panel-copy">Unpaid and overdue bills ready for payment recording.</p>
                            </div>
                            <span class="badge badge-soft-warning payments-panel-badge"><?php echo e(number_format($availableBills->count())); ?> open bills</span>
                        </div>

                        <div class="payments-table-wrap" role="region" aria-label="Open bills for payment" tabindex="0">
                            <table class="table align-middle payments-table payments-open-table">
                                <thead>
                                    <tr>
                                        <th class="payments-col-prn" scope="col">PRN</th>
                                        <th class="payments-col-consumer" scope="col">Consumer</th>
                                        <th class="payments-col-balance" scope="col">Balance</th>
                                        <th class="payments-col-status" scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $availableBills->take(10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bill): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr>
                                        <td class="payments-cell-nowrap payments-cell-primary"><?php echo e($bill->prn); ?></td>
                                        <td class="payments-col-consumer">
                                            <div class="payments-cell-primary"><?php echo e($bill->consumer->full_name); ?></div>
                                            <div class="payments-cell-meta"><?php echo e($bill->consumer->account_number); ?></div>
                                        </td>
                                        <td class="payments-currency">P <?php echo e(number_format((float) $bill->balance_due, 2)); ?></td>
                                        <td>
                                            <span class="badge payments-cell-nowrap <?php echo e($bill->status === 'Overdue' ? 'badge-soft-danger' : 'badge-soft-warning'); ?>"><?php echo e($bill->status); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="4">
                                                <div class="payments-empty-state">No unpaid bills available for payment.</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if($availableBills->count() > 10): ?>
                            <p class="payments-summary-note">Only the first 10 open bills are shown in this summary.</p>
                        <?php endif; ?>
                    </div>

                    <div class="panel-card p-4 h-100 payments-panel" data-admin-freeze-section>
                        <div class="payments-panel-head" data-admin-freeze>
                            <div class="payments-panel-head-copy">
                                <h2 class="payments-panel-title">Payment History</h2>
                                <p class="payments-panel-copy">Filter the payment records by status, channel, or reference.</p>
                            </div>
                            <span class="badge badge-soft-primary payments-panel-badge"><?php echo e(number_format($payments->total())); ?> records</span>
                        </div>

                        <form method="GET" action="<?php echo e(route('payments.index')); ?>" class="payments-filter-form" data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                            <input
                                type="text"
                                class="form-control payments-filter-search"
                                id="payment_filter_search"
                                name="search"
                                value="<?php echo e($filters['search']); ?>"
                                placeholder="Search reference, PRN, or consumer"
                                aria-label="Search payment history"
                            >
                            <select class="form-select" id="payment_filter_status" name="status" aria-label="Filter by payment status">
                                <option value="">All statuses</option>
                                <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status); ?>" <?php if($filters['status'] === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <select class="form-select" id="payment_filter_channel" name="channel" aria-label="Filter by payment channel">
                                <option value="">All channels</option>
                                <?php $__currentLoopData = $channels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($channel); ?>" <?php if($filters['channel'] === $channel): echo 'selected'; endif; ?>><?php echo e($channel); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <div class="payments-filter-actions">
                                <noscript>
                                    <button type="submit" class="btn btn-primary">Apply filters</button>
                                </noscript>
                                <a href="<?php echo e(route('payments.index')); ?>" class="btn btn-light border">Clear</a>
                            </div>
                        </form>

                        <div class="payments-table-wrap" role="region" aria-label="Payment history" tabindex="0">
                            <table class="table align-middle payments-table payments-history-table">
                                <thead>
                                    <tr>
                                        <th class="payments-col-reference" scope="col">Reference</th>
                                        <th class="payments-col-consumer" scope="col">Consumer</th>
                                        <th class="payments-col-period" scope="col">Billing Period</th>
                                        <th class="payments-col-amount" scope="col">Amount Paid</th>
                                        <th class="payments-col-remaining" scope="col">Remaining Balance</th>
                                        <th class="payments-col-status" scope="col">Status</th>
                                        <th class="payments-col-date" scope="col">Date</th>
                                        <th class="payments-col-actions text-end" scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php
                                            $remainingBalanceLabel = $payment->status === 'Verified'
                                                ? $payment->remaining_balance_after_payment
                                                : $payment->bill->balance_due;
                                        ?>
                                        <tr>
                                            <td class="payments-col-reference">
                                                <div class="payments-cell-primary payments-cell-nowrap"><?php echo e($formatPaymentReference($payment->reference_number)); ?></div>
                                                <div class="payments-cell-meta"><?php echo e($payment->bill->prn); ?></div>
                                            </td>
                                            <td class="payments-col-consumer">
                                                <div class="payments-cell-primary"><?php echo e($payment->consumer->full_name); ?></div>
                                                <div class="payments-cell-meta"><?php echo e($payment->consumer->account_number); ?></div>
                                            </td>
                                            <td class="payments-date text-secondary"><?php echo e($payment->bill->billing_period?->format('M Y') ?? 'N/A'); ?></td>
                                            <td class="payments-currency">P <?php echo e(number_format((float) $payment->amount, 2)); ?></td>
                                            <td class="payments-currency">P <?php echo e(number_format((float) $remainingBalanceLabel, 2)); ?></td>
                                            <td>
                                                <span class="badge payments-cell-nowrap <?php echo e(match ($payment->status) {
                                                    'Verified' => 'badge-soft-success',
                                                    'Rejected' => 'badge-soft-danger',
                                                    default => 'badge-soft-warning',
                                                }); ?>">
                                                    <?php echo e($payment->status); ?>

                                                </span>
                                            </td>
                                            <td class="payments-date text-secondary"><?php echo e($payment->payment_date->format('M d, Y')); ?></td>
                                            <td class="text-end payments-col-actions">
                                                <div class="payments-row-actions">
                                                    <?php if($payment->status === 'Verified'): ?>
                                                        <a href="<?php echo e(route('payments.receipt', $payment)); ?>" class="btn btn-sm btn-light border">Receipt</a>
                                                        <?php if($payment->proof_path): ?>
                                                            <a href="<?php echo e(route('uploads.serve', ['path' => $payment->proof_path])); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Proof</a>
                                                        <?php endif; ?>
                                                    <?php elseif($canVerifyPayments && $payment->status === 'Pending'): ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#paymentReviewModal"
                                                            data-payment-id="<?php echo e($payment->id); ?>"
                                                            data-payment-reference="<?php echo e($formatPaymentReference($payment->reference_number)); ?>"
                                                            data-payment-prn="<?php echo e($payment->bill->prn); ?>"
                                                            data-payment-consumer="<?php echo e($payment->consumer->full_name); ?>"
                                                            data-payment-account="<?php echo e($payment->consumer->account_number); ?>"
                                                            data-payment-channel="<?php echo e($payment->payment_channel ?? 'Counter'); ?>"
                                                            data-payment-method="<?php echo e($payment->payment_method); ?>"
                                                            data-payment-amount="<?php echo e(number_format((float) $payment->amount, 2, '.', '')); ?>"
                                                            data-payment-date="<?php echo e($payment->payment_date->format('M d, Y')); ?>"
                                                            data-payment-gateway="<?php echo e($payment->gateway_reference); ?>"
                                                            data-payment-notes="<?php echo e($payment->notes); ?>"
                                                            data-payment-proof="<?php echo e($payment->proof_path ? route('uploads.serve', ['path' => $payment->proof_path]) : ''); ?>"
                                                            data-payment-balance="<?php echo e(number_format((float) max(0, $payment->bill->balance_due - $payment->amount), 2, '.', '')); ?>"
                                                        >
                                                            Review
                                                        </button>
                                                        <?php if($payment->proof_path): ?>
                                                            <a href="<?php echo e(route('uploads.serve', ['path' => $payment->proof_path])); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Proof</a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-secondary small"><?php echo e($payment->proof_path ? 'See proof in records' : 'No action'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="8">
                                                <div class="payments-empty-state">No payment records yet.</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="payments-pagination mt-3">
                            <?php echo e($payments->links()); ?>

                        </div>
                        <p class="payments-action-note mt-3 mb-0">Use the filters to narrow the table before reviewing receipts or verifying pending submissions.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Record Payment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="<?php echo e(route('payments.store')); ?>" id="paymentCreateForm" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="payment_consumer_search">Consumer Account</label>
                            <div class="payment-consumer-picker" id="payment_consumer_picker">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                                    <input
                                        type="text"
                                        class="form-control <?php if($errors->has('consumer_id') || $errors->has('bill_id')): ?> is-invalid <?php endif; ?>"
                                        id="payment_consumer_search"
                                        placeholder="Search by consumer name, meter number, or account number"
                                        autocomplete="off"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="payment_consumer_results"
                                        aria-haspopup="listbox"
                                    >
                                    <button class="btn btn-outline-secondary" type="button" id="payment_consumer_clear" aria-label="Clear selected consumer">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="payment_consumer_id" name="consumer_id" value="<?php echo e(old('consumer_id')); ?>">
                                <input type="hidden" id="bill_id" name="bill_id" value="<?php echo e(old('bill_id')); ?>">
                                <div class="payment-consumer-results d-none" id="payment_consumer_results" role="listbox" aria-label="Matching consumer accounts"></div>
                            </div>
                            <div class="form-text">Search all consumer accounts, including inactive and disconnected accounts. No account is excluded from balance settlement.</div>
                            <?php $__errorArgs = ['consumer_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <?php $__errorArgs = ['bill_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="col-12 d-none" id="payment_consumer_details">
                            <div class="payment-consumer-card">
                                <div class="payment-consumer-card-top">
                                    <div class="payment-consumer-card-identity">
                                        <h3 class="payment-consumer-card-name" id="payment_consumer_name">-</h3>
                                        <div class="payment-consumer-card-meta">
                                            <span>Account No. <strong id="payment_consumer_account">-</strong></span>
                                            <span>Meter No. <strong id="payment_consumer_meter">-</strong></span>
                                        </div>
                                    </div>
                                    <span class="consumer-service-status" id="payment_consumer_status_wrap">
                                        <span class="badge consumer-status-badge" id="payment_consumer_status_badge">
                                            <i class="bi bi-info-circle" id="payment_consumer_status_icon" aria-hidden="true"></i>
                                            <span id="payment_consumer_status_label">-</span>
                                        </span>
                                        <span class="consumer-status-sub" id="payment_consumer_status_sub"></span>
                                    </span>
                                </div>
                                <div class="payment-consumer-stats">
                                    <div class="payment-consumer-stat">
                                        <div class="payment-consumer-stat-label">Total Outstanding Balance</div>
                                        <div class="payment-consumer-stat-value">P <span id="payment_consumer_total_outstanding">0.00</span></div>
                                    </div>
                                    <div class="payment-consumer-stat">
                                        <div class="payment-consumer-stat-label">Balance After Payment</div>
                                        <div class="payment-consumer-stat-value" id="payment_consumer_balance_after_wrap">P <span id="payment_consumer_balance_after">0.00</span></div>
                                    </div>
                                </div>
                                <p class="payment-consumer-balance-note">The total includes every unpaid previous balance and current charge attached to this account.</p>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="payment_consumer_no_balance">
                            <div class="alert alert-warning mb-0">
                                <strong>No outstanding balance.</strong>
                                <span>There is nothing to settle for this consumer account.</span>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="disconnection_payment_notice">
                            <div class="alert alert-danger mb-0">
                                <strong id="payment_full_requirement_title">Must Pay in Full</strong><br>
                                <span id="payment_full_requirement_message">This account is inactive or disconnected. Full settlement is required before reactivation.</span><br>
                                Total Outstanding Balance: P <span id="notice_total_outstanding">0.00</span><br>
                                Required Payment Amount: P <span id="notice_required_payment">0.00</span><br>
                                <span id="payment_full_requirement_rule">Full payment is required. Partial payment is not allowed. The balance after payment must reach P 0.00.</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="amount">Amount</label>
                            <input type="number" class="form-control payment-amount-input" id="amount" name="amount" step="0.01" min="0.01" inputmode="decimal" autocomplete="off" placeholder="0.00" value="<?php echo e(old('amount')); ?>" disabled required>
                            <div class="form-text" id="payment_amount_help">Select a consumer, then enter the amount received at the counter.</div>
                            <span class="payment-amount-feedback text-danger d-none" id="payment_amount_feedback" role="alert"></span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="payment_date">Payment Date</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo e(old('payment_date', now()->toDateString())); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="payment_channel">Payment Channel</label>
                            <select class="form-select" id="payment_channel" name="payment_channel" required>
                                <?php $__currentLoopData = $channels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($channel); ?>" <?php if(old('payment_channel', $canVerifyPayments ? 'Counter' : 'Online') === $channel): echo 'selected'; endif; ?>><?php echo e($channel); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="payment_method">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <?php $__currentLoopData = $paymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($method); ?>" <?php if(old('payment_method', $canVerifyPayments ? 'Cash' : 'GCash') === $method): echo 'selected'; endif; ?>><?php echo e($method); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="gateway_reference">Reference / Transaction Number</label>
                            <input type="text" class="form-control" id="gateway_reference" name="gateway_reference" value="<?php echo e(old('gateway_reference')); ?>" placeholder="Required for online payments">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="proof">Payment Receipt</label>
                            <input type="file" class="form-control" id="proof" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="small text-muted mt-2" id="proof_file_name">No file selected yet.</div>
                            <div id="receipt_parse_status" class="small mt-2"></div>
                            <div class="payment-proof-preview mt-3 d-none" id="receipt_preview_wrap">
                                <img id="receipt_preview_image" alt="Uploaded receipt preview" class="d-none">
                                <iframe id="receipt_preview_pdf" title="Uploaded receipt preview" class="d-none"></iframe>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="sender_name">Sender Name</label>
                            <input type="text" class="form-control" id="sender_name" name="sender_name" value="<?php echo e(old('sender_name')); ?>" placeholder="Optional sender name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="sender_phone">Sender Phone Number</label>
                            <input type="text" class="form-control" id="sender_phone" name="sender_phone" value="<?php echo e(old('sender_phone')); ?>" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="notes">Notes</label>
                            <input type="text" class="form-control" id="notes" name="notes" value="<?php echo e(old('notes')); ?>" placeholder="<?php echo e($canVerifyPayments ? 'Optional verification note' : 'Optional payment note'); ?>">
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <?php echo e($canVerifyPayments ? 'Admin-recorded payments are verified immediately and now issue a digital receipt. Online submissions can also keep a reference and proof attachment.' : 'Consumer submissions are treated as online payments, stored as pending, and receive a digital receipt only after admin verification.'); ?>

                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="paymentCreateForm" class="btn btn-primary" disabled>Submit Payment</button>
            </div>
        </div>
    </div>
</div>

<?php if($canVerifyPayments): ?>
    <div class="modal fade" id="paymentReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Review Payment Submission</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Reference</div>
                            <div class="fw-semibold" id="review_reference">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">PRN</div>
                            <div class="fw-semibold" id="review_prn">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Consumer</div>
                            <div class="fw-semibold" id="review_consumer">-</div>
                            <div class="small text-secondary" id="review_account">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Payment Date</div>
                            <div class="fw-semibold" id="review_date">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Channel</div>
                            <div class="fw-semibold" id="review_channel">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Method</div>
                            <div class="fw-semibold" id="review_method">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Amount</div>
                            <div class="fw-semibold" id="review_amount">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Gateway Reference</div>
                            <div class="fw-semibold" id="review_gateway">N/A</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Remaining Balance If Accepted</div>
                            <div class="fw-semibold" id="review_balance">-</div>
                        </div>
                        <div class="col-12">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Notes</div>
                            <div class="text-secondary" id="review_notes">No notes provided.</div>
                        </div>
                        <div class="col-12" id="review_proof_wrap">
                            <div class="small text-uppercase text-secondary fw-semibold mb-2">Attached Proof</div>
                            <div class="payment-proof-preview mb-3 d-none" id="review_proof_preview_wrap">
                                <img id="review_proof_image" alt="Payment proof preview" class="d-none">
                                <iframe id="review_proof_pdf" title="Payment proof preview" class="d-none"></iframe>
                            </div>
                            <a id="review_proof_link" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">Open Proof Attachment</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <form method="POST" id="paymentRejectForm">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <button type="submit" class="btn btn-outline-danger">Reject</button>
                    </form>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                        <form method="POST" id="paymentVerifyForm">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <button type="submit" class="btn btn-success">Verify and Issue Receipt</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paymentConsumers = <?php echo json_encode($paymentConsumers, 15, 512) ?>;
            const oldConsumerId = <?php echo json_encode((string) old('consumer_id', ''), 512) ?>;
            const oldBillId = <?php echo json_encode((string) old('bill_id', ''), 512) ?>;
            const oldAmount = <?php echo json_encode(old('amount'), 15, 512) ?>;

            const paymentForm = document.getElementById('paymentCreateForm');
            const amountInput = document.getElementById('amount');
            const paymentDateInput = document.getElementById('payment_date');
            const gatewayReferenceInput = document.getElementById('gateway_reference');
            const proofInput = document.getElementById('proof');
            const proofFileName = document.getElementById('proof_file_name');
            const parseStatus = document.getElementById('receipt_parse_status');
            const previewWrap = document.getElementById('receipt_preview_wrap');
            const previewImage = document.getElementById('receipt_preview_image');
            const previewPdf = document.getElementById('receipt_preview_pdf');
            const paymentChannelInput = document.getElementById('payment_channel');
            const paymentMethodInput = document.getElementById('payment_method');
            const senderPhoneInput = document.getElementById('sender_phone');
            const billInput = document.getElementById('bill_id');
            const consumerPicker = document.getElementById('payment_consumer_picker');
            const consumerSearchInput = document.getElementById('payment_consumer_search');
            const consumerSearchResults = document.getElementById('payment_consumer_results');
            const consumerIdInput = document.getElementById('payment_consumer_id');
            const consumerClearButton = document.getElementById('payment_consumer_clear');
            const consumerDetails = document.getElementById('payment_consumer_details');
            const consumerName = document.getElementById('payment_consumer_name');
            const consumerAccount = document.getElementById('payment_consumer_account');
            const consumerMeter = document.getElementById('payment_consumer_meter');
            const consumerStatusWrap = document.getElementById('payment_consumer_status_wrap');
            const consumerStatusBadge = document.getElementById('payment_consumer_status_badge');
            const consumerStatusIcon = document.getElementById('payment_consumer_status_icon');
            const consumerStatusLabel = document.getElementById('payment_consumer_status_label');
            const consumerStatusSub = document.getElementById('payment_consumer_status_sub');
            const consumerTotalOutstanding = document.getElementById('payment_consumer_total_outstanding');
            const consumerBalanceAfter = document.getElementById('payment_consumer_balance_after');
            const consumerBalanceAfterWrap = document.getElementById('payment_consumer_balance_after_wrap');
            const consumerNoBalance = document.getElementById('payment_consumer_no_balance');
            const amountHelp = document.getElementById('payment_amount_help');
            const amountFeedback = document.getElementById('payment_amount_feedback');
            const disconnectionNotice = document.getElementById('disconnection_payment_notice');
            const noticeTitle = document.getElementById('payment_full_requirement_title');
            const noticeMessage = document.getElementById('payment_full_requirement_message');
            const noticeRule = document.getElementById('payment_full_requirement_rule');
            const noticeTotalOutstanding = document.getElementById('notice_total_outstanding');
            const noticeRequiredPayment = document.getElementById('notice_required_payment');
            const submitButton = document.querySelector('[form="paymentCreateForm"][type="submit"]');

            let selectedConsumer = null;
            let matchingConsumers = [];
            let activeResultIndex = -1;

            const formatMoney = (value) => Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            function hasValue(input) {
                return Boolean(input && String(input.value || '').trim() !== '');
            }

            function isReceiptScanActive() {
                return paymentChannelInput?.value === 'Online' && Boolean(proofInput?.files?.length);
            }

            function isFullPaymentRequired() {
                if (!selectedConsumer || Number(selectedConsumer.total_outstanding || 0) <= 0) {
                    return false;
                }

                return selectedConsumer.status_key === 'inactive'
                    || Boolean(selectedConsumer.has_disconnection_notice);
            }

            function syncBillAnchor() {
                if (!selectedConsumer || !selectedConsumer.bills.length) {
                    billInput.value = '';
                    return;
                }

                // The hidden bill is only an anchor for the payment record.
                // Allocation itself always starts from the oldest unpaid bill.
                billInput.value = String(selectedConsumer.bills[selectedConsumer.bills.length - 1].id);
            }

            function updateAmountPreview() {
                const hasConsumer = Boolean(selectedConsumer);
                const totalOutstanding = Number(selectedConsumer?.total_outstanding || 0);
                const enteredAmount = Number(amountInput.value || 0);
                const hasEnteredAmount = hasValue(amountInput);
                const fullPaymentRequired = isFullPaymentRequired();
                const exceedsLimit = hasConsumer && hasEnteredAmount && enteredAmount > totalOutstanding + 0.009;
                const belowFullRequirement = fullPaymentRequired
                    && hasEnteredAmount
                    && enteredAmount < totalOutstanding - 0.009;

                if (consumerTotalOutstanding) {
                    consumerTotalOutstanding.textContent = formatMoney(totalOutstanding);
                }

                if (amountInput) {
                    if (hasConsumer && totalOutstanding > 0) {
                        amountInput.max = totalOutstanding.toFixed(2);
                    } else {
                        amountInput.removeAttribute('max');
                    }
                }

                if (consumerBalanceAfter) {
                    const remaining = Math.max(0, totalOutstanding - Math.max(0, enteredAmount));
                    consumerBalanceAfter.textContent = formatMoney(remaining);
                    consumerBalanceAfterWrap?.classList.toggle(
                        'is-settled',
                        hasConsumer && remaining <= 0 && !exceedsLimit,
                    );
                }

                let feedbackMessage = '';

                if (exceedsLimit) {
                    feedbackMessage = `Payment amount cannot exceed the total outstanding balance of P ${formatMoney(totalOutstanding)}.`;
                } else if (belowFullRequirement) {
                    feedbackMessage = `Full payment of P ${formatMoney(totalOutstanding)} is required to settle this account.`;
                }

                if (amountFeedback) {
                    amountFeedback.textContent = feedbackMessage;
                    amountFeedback.classList.toggle('d-none', feedbackMessage === '');
                }

                amountInput?.classList.toggle('is-invalid', feedbackMessage !== '');

                if (amountHelp) {
                    if (!hasConsumer) {
                        amountHelp.textContent = 'Select a consumer, then enter the amount received at the counter.';
                    } else if (!selectedConsumer.bills.length || totalOutstanding <= 0) {
                        amountHelp.textContent = 'This consumer has no outstanding balance to settle.';
                    } else {
                        amountHelp.textContent = 'Enter the amount received. Payments are applied to the oldest unpaid bill first (FIFO).';
                    }
                }
            }

            function updateFullPaymentNotice() {
                const totalOutstanding = Number(selectedConsumer?.total_outstanding || 0);
                const inactiveAccount = selectedConsumer?.status_key === 'inactive';
                const noticeActive = Boolean(selectedConsumer?.has_disconnection_notice);
                const active = Boolean(selectedConsumer)
                    && totalOutstanding > 0
                    && (inactiveAccount || noticeActive);

                disconnectionNotice?.classList.toggle('d-none', !active);

                if (noticeTitle) {
                    noticeTitle.textContent = inactiveAccount ? 'Must Pay in Full' : 'Disconnection Notice';
                }

                if (noticeMessage) {
                    noticeMessage.textContent = inactiveAccount
                        ? 'This account is inactive or disconnected. Full settlement is required before reactivation.'
                        : 'A disconnection notice is active on this account.';
                }

                if (noticeRule) {
                    noticeRule.textContent = 'Full payment is required. Partial payment is not allowed. The balance after payment must reach P 0.00.';
                }

                if (noticeTotalOutstanding) {
                    noticeTotalOutstanding.textContent = formatMoney(totalOutstanding);
                }

                if (noticeRequiredPayment) {
                    noticeRequiredPayment.textContent = formatMoney(totalOutstanding);
                }
            }

            function requiredPaymentFieldsAreFilled() {
                if (!selectedConsumer || !selectedConsumer.bills.length) {
                    return false;
                }

                const totalOutstanding = Number(selectedConsumer.total_outstanding || 0);
                const enteredAmount = Number(amountInput.value || 0);
                const fullPaymentRequired = isFullPaymentRequired();

                if (!hasValue(billInput)
                    || !hasValue(amountInput)
                    || enteredAmount <= 0
                    || enteredAmount > totalOutstanding + 0.009
                    || (fullPaymentRequired && enteredAmount < totalOutstanding - 0.009)
                    || !hasValue(paymentDateInput)
                    || !hasValue(paymentChannelInput)
                    || !hasValue(paymentMethodInput)) {
                    return false;
                }

                if (paymentChannelInput?.value === 'Online' && !hasValue(gatewayReferenceInput)) {
                    return false;
                }

                return true;
            }

            function updateSubmitState() {
                updateFullPaymentNotice();
                updateAmountPreview();

                if (submitButton) {
                    submitButton.disabled = !requiredPaymentFieldsAreFilled();
                }
            }

            function setSubmitEnabled(enabled) {
                if (submitButton) {
                    submitButton.disabled = !(enabled && requiredPaymentFieldsAreFilled());
                }
            }

            function setAutofilledFieldsLocked(locked) {
                if (isReceiptScanActive()) {
                    amountInput.readOnly = locked;
                    paymentDateInput.readOnly = locked;
                    gatewayReferenceInput.readOnly = locked;
                    return;
                }

                amountInput.readOnly = false;
                paymentDateInput.readOnly = false;
                gatewayReferenceInput.readOnly = false;
            }

            function clearDetectedFields() {
                if (isReceiptScanActive()) {
                    amountInput.value = '';
                    paymentDateInput.value = '';
                    gatewayReferenceInput.value = '';
                }

                setAutofilledFieldsLocked(true);
                setSubmitEnabled(false);

                updateSubmitState();
            }

            function applyDetectedFields(fields) {
                if (fields?.reference_number) {
                    gatewayReferenceInput.value = fields.reference_number;
                }

                if (fields?.amount) {
                    amountInput.value = fields.amount;
                }

                if (fields?.payment_date) {
                    paymentDateInput.value = fields.payment_date;
                }

                if (fields?.payment_method && paymentMethodInput) {
                    [...paymentMethodInput.options].forEach((option) => {
                        if (option.value.toLowerCase() === fields.payment_method.toLowerCase()) {
                            paymentMethodInput.value = option.value;
                        }
                    });
                }

                if (fields?.phone_number && senderPhoneInput) {
                    senderPhoneInput.value = fields.phone_number;
                }

                updateSubmitState();
            }

            function updatePreview(file) {
                previewWrap.classList.add('d-none');
                previewImage.classList.add('d-none');
                previewPdf.classList.add('d-none');
                previewImage.removeAttribute('src');
                previewPdf.removeAttribute('src');

                if (!file) {
                    proofFileName.textContent = 'No file selected yet.';
                    clearDetectedFields();
                    return;
                }

                proofFileName.textContent = file.name;
                previewWrap.classList.remove('d-none');

                if (file.type === 'application/pdf') {
                    previewPdf.src = URL.createObjectURL(file);
                    previewPdf.classList.remove('d-none');
                    return;
                }

                if (file.type.startsWith('image/')) {
                    previewImage.src = URL.createObjectURL(file);
                    previewImage.classList.remove('d-none');
                    return;
                }

                previewWrap.classList.add('d-none');
            }

            async function parseReceipt(file) {
                if (!file || !parseStatus) {
                    return;
                }

                parseStatus.className = 'small mt-2 text-muted';
                parseStatus.textContent = 'Analyzing receipt...';
                setSubmitEnabled(false);

                const formData = new FormData();
                formData.append('proof', file);
                formData.append('_token', <?php echo json_encode(csrf_token(), 15, 512) ?>);

                try {
                    const response = await fetch(<?php echo json_encode(route('payments.parse-receipt'), 15, 512) ?>, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const responseText = await response.text();
                    let data = {};

                    try {
                        data = responseText ? JSON.parse(responseText) : {};
                    } catch (parseError) {
                        throw new Error(`Receipt scan returned HTTP ${response.status} instead of JSON. ${responseText.slice(0, 160)}`);
                    }

                    if (!response.ok || !data.ok) {
                        parseStatus.className = 'small mt-2 text-warning';
                        parseStatus.textContent = data.message || 'Some receipt details could not be detected. Enter the missing reference number, amount, or payment date manually.';
                        applyDetectedFields(data.fields || {});
                        setAutofilledFieldsLocked(false);
                        updateSubmitState();
                        return;
                    }

                    applyDetectedFields(data.fields || {});
                    setAutofilledFieldsLocked(true);
                    setSubmitEnabled(true);

                    parseStatus.className = 'small mt-2 text-success';
                    parseStatus.textContent = 'Receipt details detected. Amount, reference number, and payment date are locked.';
                } catch (error) {
                    parseStatus.className = 'small mt-2 text-warning';
                    parseStatus.textContent = `Receipt scan failed before detection completed. You can still enter the reference number, amount, and payment date manually. ${error.message || 'Check storage/logs/laravel.log for the server-side OCR error.'}`;
                    setAutofilledFieldsLocked(false);
                    updateSubmitState();
                }
            }

            function createConsumerStatusNode(consumer) {
                const wrapper = document.createElement('span');
                wrapper.className = 'consumer-service-status';

                if (consumer.status_description) {
                    wrapper.title = consumer.status_description;
                }

                const badge = document.createElement('span');
                badge.className = 'badge consumer-status-badge ' + (consumer.status_badge_class || 'badge-soft-primary');

                const icon = document.createElement('i');
                icon.className = 'bi ' + (consumer.status_icon || 'bi-info-circle');
                icon.setAttribute('aria-hidden', 'true');

                const label = document.createElement('span');
                label.textContent = consumer.status_label || '';

                badge.append(icon, label);

                const subLabel = document.createElement('span');
                subLabel.className = 'consumer-status-sub ' + (consumer.status_sub_class || '');
                subLabel.textContent = consumer.status_sub_label || '';

                wrapper.append(badge, subLabel);

                return wrapper;
            }

            function hideConsumerResults() {
                consumerSearchResults?.classList.add('d-none');
                consumerSearchInput?.setAttribute('aria-expanded', 'false');
                consumerSearchInput?.removeAttribute('aria-activedescendant');
                activeResultIndex = -1;
            }

            function setActiveConsumerResult(index) {
                if (!matchingConsumers.length) {
                    activeResultIndex = -1;
                    return;
                }

                activeResultIndex = (index + matchingConsumers.length) % matchingConsumers.length;

                [...consumerSearchResults.querySelectorAll('.payment-consumer-option')].forEach((option, optionIndex) => {
                    const active = optionIndex === activeResultIndex;
                    option.classList.toggle('is-active', active);
                    option.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                const activeOption = consumerSearchResults.querySelectorAll('.payment-consumer-option')[activeResultIndex];

                if (activeOption) {
                    consumerSearchInput.setAttribute('aria-activedescendant', activeOption.id);
                    activeOption.scrollIntoView({ block: 'nearest' });
                }
            }

            function renderConsumerResults() {
                if (!consumerSearchResults) {
                    return;
                }

                const query = String(consumerSearchInput?.value || '').trim().toLowerCase();
                const matches = paymentConsumers.filter((consumer) => {
                    if (query === '') {
                        return true;
                    }

                    return [consumer.name, consumer.account_number, consumer.meter_number]
                        .filter(Boolean)
                        .some((value) => String(value).toLowerCase().includes(query));
                });

                matchingConsumers = matches.slice(0, query === '' ? 10 : 40);
                activeResultIndex = -1;
                consumerSearchResults.innerHTML = '';

                if (!matchingConsumers.length) {
                    const empty = document.createElement('div');
                    empty.className = 'payment-consumer-empty';
                    empty.textContent = paymentConsumers.length
                        ? 'No consumer account matches your search.'
                        : 'No consumer accounts are available.';
                    consumerSearchResults.append(empty);
                    consumerSearchResults.classList.remove('d-none');
                    consumerSearchInput?.setAttribute('aria-expanded', 'true');
                    return;
                }

                matchingConsumers.forEach((consumer, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'payment-consumer-option';
                    option.id = `payment-consumer-option-${consumer.id}`;
                    option.dataset.consumerId = String(consumer.id);
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.title = consumer.status_description || '';

                    const main = document.createElement('span');
                    main.className = 'payment-consumer-option-main';

                    const name = document.createElement('span');
                    name.className = 'payment-consumer-option-name';
                    name.textContent = consumer.name || 'Unnamed consumer';

                    const meta = document.createElement('span');
                    meta.className = 'payment-consumer-option-meta';
                    meta.textContent = `Account ${consumer.account_number || 'N/A'} · Meter ${consumer.meter_number || 'N/A'}`;

                    main.append(name, meta);

                    const side = document.createElement('span');
                    side.className = 'payment-consumer-option-side';

                    const balance = document.createElement('span');
                    balance.className = 'payment-consumer-option-balance';
                    balance.textContent = `P ${formatMoney(consumer.total_outstanding)}`;

                    side.append(createConsumerStatusNode(consumer), balance);

                    option.append(main, side);
                    option.addEventListener('mouseenter', () => setActiveConsumerResult(index));

                    consumerSearchResults.append(option);
                });

                consumerSearchResults.classList.remove('d-none');
                consumerSearchInput?.setAttribute('aria-expanded', 'true');
            }

            function renderSelectedConsumer(consumer) {
                consumerName.textContent = consumer.name || 'Unnamed consumer';
                consumerAccount.textContent = consumer.account_number || 'Not assigned';
                consumerMeter.textContent = consumer.meter_number || 'Not assigned';

                consumerStatusBadge.className = 'badge consumer-status-badge ' + (consumer.status_badge_class || 'badge-soft-primary');
                consumerStatusIcon.className = 'bi ' + (consumer.status_icon || 'bi-info-circle');
                consumerStatusLabel.textContent = consumer.status_label || '-';
                consumerStatusSub.className = 'consumer-status-sub ' + (consumer.status_sub_class || '');
                consumerStatusSub.textContent = consumer.status_sub_label || '';
                consumerStatusWrap.title = consumer.status_description || '';

                consumerDetails.classList.remove('d-none');
                consumerNoBalance.classList.toggle('d-none', consumer.bills.length > 0);
            }

            function selectConsumer(consumer, options = {}) {
                if (!consumer) {
                    return;
                }

                selectedConsumer = consumer;
                consumerIdInput.value = String(consumer.id);
                consumerSearchInput.value = consumer.name || '';
                consumerSearchInput.classList.remove('is-invalid');
                hideConsumerResults();
                renderSelectedConsumer(consumer);
                syncBillAnchor();

                const hasOutstanding = consumer.bills.length > 0
                    && Number(consumer.total_outstanding || 0) > 0;

                amountInput.disabled = !hasOutstanding;

                if (hasOutstanding
                    && options.amount !== undefined
                    && options.amount !== null
                    && String(options.amount).trim() !== '') {
                    amountInput.value = options.amount;
                } else {
                    amountInput.value = '';
                }

                updateSubmitState();
            }

            function resetConsumerSelection(options = {}) {
                const clearSearch = options.clearSearch !== false;

                selectedConsumer = null;
                consumerIdInput.value = '';
                billInput.value = '';
                consumerDetails.classList.add('d-none');
                consumerNoBalance.classList.add('d-none');
                consumerSearchInput.classList.remove('is-invalid');
                hideConsumerResults();

                amountInput.value = '';
                amountInput.disabled = true;
                amountInput.classList.remove('is-invalid');
                amountInput.removeAttribute('max');
                amountFeedback.textContent = '';
                amountFeedback.classList.add('d-none');

                if (clearSearch) {
                    consumerSearchInput.value = '';
                }

                updateSubmitState();
            }

            consumerSearchInput?.addEventListener('focus', function () {
                if (selectedConsumer) {
                    this.select();
                    return;
                }

                renderConsumerResults();
            });

            consumerSearchInput?.addEventListener('input', function () {
                if (selectedConsumer) {
                    resetConsumerSelection({ clearSearch: false });
                }

                renderConsumerResults();
            });

            consumerSearchInput?.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();

                    if (consumerSearchResults.classList.contains('d-none')) {
                        renderConsumerResults();
                    }

                    setActiveConsumerResult(activeResultIndex + 1);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();

                    if (consumerSearchResults.classList.contains('d-none')) {
                        renderConsumerResults();
                    }

                    setActiveConsumerResult(activeResultIndex - 1);
                    return;
                }

                if (event.key === 'Enter' && !consumerSearchResults.classList.contains('d-none') && matchingConsumers.length) {
                    event.preventDefault();
                    selectConsumer(matchingConsumers[activeResultIndex >= 0 ? activeResultIndex : 0]);
                    return;
                }

                if (event.key === 'Escape') {
                    hideConsumerResults();
                }
            });

            consumerSearchResults?.addEventListener('click', function (event) {
                const option = event.target.closest('[data-consumer-id]');

                if (!option) {
                    return;
                }

                const consumer = paymentConsumers.find((item) => String(item.id) === option.dataset.consumerId);

                if (consumer) {
                    selectConsumer(consumer);
                }
            });

            consumerClearButton?.addEventListener('click', function () {
                resetConsumerSelection();
                consumerSearchInput.focus();
                renderConsumerResults();
            });

            document.addEventListener('click', function (event) {
                if (consumerPicker && !consumerPicker.contains(event.target)) {
                    hideConsumerResults();
                }
            });

            proofInput?.addEventListener('change', function () {
                const file = this.files[0] || null;
                updatePreview(file);

                if (file && paymentChannelInput && paymentMethodInput) {
                    paymentChannelInput.value = 'Online';
                    if (paymentMethodInput.value === 'Cash') {
                        paymentMethodInput.value = 'Bank Transfer';
                    }
                }

                clearDetectedFields();
                parseReceipt(file);
            });

            paymentChannelInput?.addEventListener('change', clearDetectedFields);
            paymentMethodInput?.addEventListener('change', clearDetectedFields);
            [amountInput, paymentDateInput, paymentChannelInput, paymentMethodInput, gatewayReferenceInput].forEach((input) => {
                input?.addEventListener('input', updateSubmitState);
                input?.addEventListener('change', updateSubmitState);
            });

            let initialConsumer = null;

            if (oldConsumerId !== '') {
                initialConsumer = paymentConsumers.find((consumer) => String(consumer.id) === oldConsumerId) || null;
            }

            if (!initialConsumer && oldBillId !== '') {
                initialConsumer = paymentConsumers.find((consumer) => consumer.bills.some((bill) => String(bill.id) === oldBillId)) || null;
            }

            if (initialConsumer) {
                selectConsumer(initialConsumer, {
                    amount: oldAmount,
                });
            } else {
                resetConsumerSelection();
            }

            setAutofilledFieldsLocked(isReceiptScanActive());
            updateSubmitState();
        });
    </script>

    <?php if($canVerifyPayments): ?>
        <script>
            const paymentReviewModal = document.getElementById('paymentReviewModal');

            if (paymentReviewModal) {
                paymentReviewModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const paymentId = button.getAttribute('data-payment-id');
                    const proofUrl = button.getAttribute('data-payment-proof');

                    document.getElementById('review_reference').textContent = button.getAttribute('data-payment-reference') || '-';
                    document.getElementById('review_prn').textContent = button.getAttribute('data-payment-prn') || '-';
                    document.getElementById('review_consumer').textContent = button.getAttribute('data-payment-consumer') || '-';
                    document.getElementById('review_account').textContent = button.getAttribute('data-payment-account') || '-';
                    document.getElementById('review_date').textContent = button.getAttribute('data-payment-date') || '-';
                    document.getElementById('review_channel').textContent = button.getAttribute('data-payment-channel') || '-';
                    document.getElementById('review_method').textContent = button.getAttribute('data-payment-method') || '-';
                    document.getElementById('review_amount').textContent = `P ${button.getAttribute('data-payment-amount') || '0.00'}`;
                    document.getElementById('review_gateway').textContent = button.getAttribute('data-payment-gateway') || 'N/A';
                    document.getElementById('review_balance').textContent = `P ${button.getAttribute('data-payment-balance') || '0.00'}`;
                    document.getElementById('review_notes').textContent = button.getAttribute('data-payment-notes') || 'No notes provided.';

                    document.getElementById('paymentVerifyForm').action = `/payments/${paymentId}/verify`;
                    document.getElementById('paymentRejectForm').action = `/payments/${paymentId}/reject`;

                    const proofWrap = document.getElementById('review_proof_wrap');
                    const proofLink = document.getElementById('review_proof_link');
                    const proofPreviewWrap = document.getElementById('review_proof_preview_wrap');
                    const proofImage = document.getElementById('review_proof_image');
                    const proofPdf = document.getElementById('review_proof_pdf');

                    if (proofUrl) {
                        proofWrap.classList.remove('d-none');
                        proofLink.href = proofUrl;

                        const normalizedProofUrl = proofUrl.toLowerCase();
                        const isPdf = normalizedProofUrl.includes('.pdf');
                        const isImage = ['.jpg', '.jpeg', '.png', '.gif', '.webp'].some((extension) => normalizedProofUrl.includes(extension));

                        proofPreviewWrap.classList.remove('d-none');
                        proofImage.classList.add('d-none');
                        proofPdf.classList.add('d-none');
                        proofImage.removeAttribute('src');
                        proofPdf.removeAttribute('src');

                        if (isPdf) {
                            proofPdf.src = proofUrl;
                            proofPdf.classList.remove('d-none');
                        } else if (isImage) {
                            proofImage.src = proofUrl;
                            proofImage.classList.remove('d-none');
                        } else {
                            proofPreviewWrap.classList.add('d-none');
                        }
                    } else {
                        proofWrap.classList.add('d-none');
                        proofLink.href = '#';
                        proofPreviewWrap.classList.add('d-none');
                        proofImage.classList.add('d-none');
                        proofPdf.classList.add('d-none');
                        proofImage.removeAttribute('src');
                        proofPdf.removeAttribute('src');
                    }
                });
            }
        </script>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <script>
            const paymentModalElement = document.getElementById('paymentModal');
            if (paymentModalElement) {
                const paymentModal = new bootstrap.Modal(paymentModalElement);
                paymentModal.show();
            }
        </script>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/payments/index.blade.php ENDPATH**/ ?>