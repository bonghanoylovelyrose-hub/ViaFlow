<?php
    $activePage = 'billing-water';
    $filteredBills = $filteredBills ?? $bills;
    $selectedBillingPeriod = $selectedBillingPeriod ?? ($filters['billing_period'] ?? '');
    $visibleBills = $filteredBills->getCollection();
    $visibleOpenCount = $visibleBills->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])->count();
    $billingSummary = $billingSummary ?? [
        'count' => $filteredBills->total(),
        'total_amount' => $visibleBills->sum(fn ($bill) => (float) $bill->amount),
        'open_count' => $visibleOpenCount,
        'previous_balance' => $visibleBills->sum(fn ($bill) => (float) ($bill->billing_breakdown['previous_unpaid_balance'] ?? 0)),
        'balance_due' => $visibleBills->sum(fn ($bill) => (float) $bill->balance_due),
    ];
    $billingHeading = $billingHeading ?? (($filters['status'] ?? '') === ''
        ? 'Water Billing'
        : (($filters['status'] ?? '').' Bills'));
    $hasBillingFilters = ($filters['status'] ?? '') !== ''
        || ($filters['billing_period'] ?? '') !== ''
        || ($filters['prn'] ?? '') !== '';

    $statusClass = fn (?string $status) => match ($status) {
        'Paid', 'Approved' => 'is-complete',
        'Overdue', 'Rejected' => 'is-urgent',
        default => 'is-pending',
    };
?>

<?php $__env->startPush('styles'); ?>
<style>
    .billing-page {
        --billing-primary: var(--vf-royal);
        --billing-teal: var(--vf-royal);
        --billing-ink: var(--vf-navy);
        --billing-surface: #ffffff;
        --billing-bg: #f5f7fa;
        --billing-border: var(--vf-light);
        --billing-muted: color-mix(in srgb, var(--billing-ink) 55%, var(--billing-surface));
        color: var(--billing-ink);
    }

    .billing-page * {
        letter-spacing: 0;
    }

    .billing-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1rem;
        align-items: end;
        margin-bottom: 1rem;
    }


    .cons-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.25rem;
        background: linear-gradient(130deg, var(--vf-navy) 0%, var(--vf-royal) 40%, var(--vf-royal) 75%, var(--vf-royal) 100%);
        padding: 2.25rem 2.5rem 2rem;
        color: #fff;
        margin-bottom: 1.25rem;
    }

    .cons-hero::after {
        content: '';
        position: absolute;
        right: -4rem;
        bottom: -4rem;
        width: 18rem;
        height: 18rem;
        border-radius: 50%;
        background: rgba(255,255,255,0.07);
        pointer-events: none;
    }

    .cons-hero::before {
        content: '';
        position: absolute;
        right: 5rem;
        bottom: -6rem;
        width: 24rem;
        height: 24rem;
        border-radius: 50%;
        background: rgba(255,255,255,0.04);
        pointer-events: none;
    }

    .cons-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        background: rgba(255,255,255,0.14);
        border: 1px solid rgba(255,255,255,0.22);
        border-radius: 999px;
        padding: 0.3rem 0.85rem;
        color: rgba(255,255,255,0.92);
        margin-bottom: 1.1rem;
    }

    .cons-hero-layout {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 3rem;
        align-items: center;
        position: relative;
        z-index: 1;
    }

    .cons-balance-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: rgba(255,255,255,0.68);
        margin-bottom: 0.35rem;
    }

    .cons-balance-amount {
        font-size: 3.2rem;
        font-weight: 800;
        line-height: 1.05;
        color: #fff;
        margin-bottom: 0.4rem;
    }

    .cons-balance-note {
        font-size: 0.82rem;
        color: rgba(255,255,255,0.6);
        font-weight: 500;
        margin-bottom: 1.4rem;
    }

    .cons-hero-actions {
        display: flex;
        gap: 0.6rem;
        flex-wrap: wrap;
    }

    .cons-btn-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        padding: 0 1.4rem;
        height: 2.55rem;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.875rem;
        text-decoration: none;
        transition: all 0.18s ease;
        cursor: pointer;
        border: none;
        white-space: nowrap;
    }

    .cons-btn-pill:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.18);
    }

    .cons-btn-white {
        background: rgba(255,255,255,0.92);
        color: var(--billing-ink);
    }

    .cons-btn-ghost {
        background: rgba(255,255,255,0.12);
        border: 1.5px solid rgba(255,255,255,0.35);
        color: #fff;
    }

    /* Right side account meta in 2-col grid */
    .cons-hero-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.1rem 2.25rem;
    }

    .cons-hero-meta-label {
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: rgba(255,255,255,0.55);
        margin-bottom: 0.22rem;
    }

    .cons-hero-meta-value {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
    }

    .cons-hero-meta-wide {
        grid-column: 1 / -1;
    }


    .cons-overview-card {
        background: var(--billing-surface);
        border: 1px solid var(--billing-border);
        border-radius: 1.15rem;
        padding: 1.75rem 2rem;
        margin-bottom: 1.25rem;
    }

    .cons-overview-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .cons-section-label {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--billing-muted);
        margin-bottom: 0.2rem;
    }

    .cons-section-title {
        font-size: 1.25rem;
        font-weight: 800;
        margin: 0;
        color: var(--billing-ink);
    }

    .cons-stats-row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }

    .cons-stat-tile {
        background: var(--billing-bg);
        border: 1px solid var(--billing-border);
        border-radius: 0.9rem;
        padding: 1.1rem 1.25rem;
    }

    .cons-stat-tile-label {
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--billing-muted);
        margin-bottom: 0.4rem;
    }

    .cons-stat-tile-value {
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.15;
        color: var(--billing-ink);
        margin-bottom: 0.2rem;
    }

    .cons-stat-tile-sub {
        font-size: 0.78rem;
        font-weight: 500;
        color: var(--billing-muted);
    }

    .billing-eyebrow,
    .billing-label,
    .billing-table thead th,
    .billing-meta {
        color: color-mix(in srgb, var(--billing-ink) 62%, var(--billing-surface));
    }

    .billing-eyebrow,
    .billing-label,
    .billing-table thead th {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .billing-title {
        margin: 0.25rem 0 0;
        font-size: 1.85rem;
        font-weight: 800;
        line-height: 1.15;
    }

    .billing-copy {
        margin: 0.5rem 0 0;
        max-width: 62rem;
        color: color-mix(in srgb, var(--billing-ink) 68%, var(--billing-surface));
        font-weight: 500;
    }

    .billing-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .billing-button,
    .billing-button-outline,
    .billing-icon-button {
        border-radius: 0.8rem;
        font-weight: 800;
        min-height: 2.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .billing-button {
        border: 1px solid var(--billing-primary);
        background: var(--billing-primary);
        color: var(--billing-surface);
    }

    .billing-button:hover {
        color: var(--billing-surface);
        background: color-mix(in srgb, var(--billing-primary) 86%, var(--billing-ink));
        border-color: color-mix(in srgb, var(--billing-primary) 86%, var(--billing-ink));
    }

    .billing-button-outline,
    .billing-icon-button {
        border: 1px solid color-mix(in srgb, var(--billing-primary) 30%, var(--billing-surface));
        background: var(--billing-surface);
        color: var(--billing-primary);
    }

    .billing-button-outline:hover,
    .billing-icon-button:hover {
        color: var(--billing-primary);
        background: color-mix(in srgb, var(--billing-primary) 8%, var(--billing-surface));
    }

    .billing-icon-button {
        width: 2.4rem;
        min-height: 2.4rem;
        border-radius: 0.75rem;
    }

    .billing-card {
        border: 1px solid color-mix(in srgb, var(--billing-primary) 18%, var(--billing-surface));
        border-radius: 1rem;
        background: color-mix(in srgb, var(--billing-primary) 3%, var(--billing-surface));
        width: 100%;
        min-width: 0;
    }

    .billing-card-head {
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .billing-card-head > * {
        min-width: 0;
        max-width: 100%;
    }

    .billing-section-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .billing-section-copy,
    .billing-meta {
        margin: 0.25rem 0 0;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .billing-stat-row {
        margin-bottom: 1rem;
    }

    .billing-filter {
        display: grid;
        grid-template-columns: minmax(150px, 0.8fr) minmax(160px, 0.9fr) minmax(220px, 1.2fr) auto;
        gap: 0.75rem;
        align-items: end;
        margin-bottom: 1rem;
    }

    .billing-field label {
        margin-bottom: 0.35rem;
    }

    .billing-field .form-control,
    .billing-field .form-select,
    .billing-modal .form-control,
    .billing-modal .form-select,
    .billing-modal textarea {
        min-height: 2.75rem;
        border-radius: 0.8rem;
        border-color: color-mix(in srgb, var(--billing-primary) 20%, var(--billing-surface));
        color: var(--billing-ink);
        font-weight: 600;
    }

    .billing-field .form-control:focus,
    .billing-field .form-select:focus,
    .billing-modal .form-control:focus,
    .billing-modal .form-select:focus,
    .billing-modal textarea:focus {
        border-color: var(--billing-primary);
        box-shadow: none;
    }

    .billing-filter-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .billing-filter-actions .billing-button,
    .billing-filter-actions .billing-button-outline {
        width: 6.5rem;
        padding-inline: 1rem;
    }

    .billing-table-card {
        overflow: hidden;
        border: 1px solid color-mix(in srgb, var(--billing-primary) 15%, var(--billing-surface));
        border-radius: 0.95rem;
        background: var(--billing-surface);
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }

    .billing-table-card .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }

    .billing-table {
        margin-bottom: 0;
    }

    .billing-table thead th {
        padding: 0.9rem 1rem;
        border-bottom: 1px solid color-mix(in srgb, var(--billing-primary) 14%, var(--billing-surface));
        background: color-mix(in srgb, var(--billing-primary) 5%, var(--billing-surface));
    }

    .billing-table tbody td {
        padding: 1rem;
        border-color: color-mix(in srgb, var(--billing-primary) 10%, var(--billing-surface));
    }

    .billing-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .billing-name {
        font-weight: 800;
        line-height: 1.25;
    }

    .billing-amount {
        font-weight: 800;
        white-space: nowrap;
    }

    .billing-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 1.8rem;
        padding: 0.3rem 0.65rem;
        border-radius: 999px;
        border: 1px solid color-mix(in srgb, var(--billing-primary) 22%, var(--billing-surface));
        color: var(--billing-primary);
        background: color-mix(in srgb, var(--billing-primary) 7%, var(--billing-surface));
        font-size: 0.76rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .billing-badge.is-complete {
        border-color: color-mix(in srgb, var(--vf-green) 28%, var(--billing-surface));
        background: color-mix(in srgb, var(--vf-green) 12%, var(--billing-surface));
        color: var(--vf-green);
    }

    .billing-badge.is-urgent {
        border-color: color-mix(in srgb, #ef4444 34%, var(--billing-surface));
        background: color-mix(in srgb, #ef4444 12%, var(--billing-surface));
        color: #dc2626;
    }

    .billing-badge.is-pending {
        border-color: color-mix(in srgb, #f59e0b 36%, var(--billing-surface));
        background: color-mix(in srgb, #f59e0b 14%, var(--billing-surface));
        color: #b45309;
    }

    .billing-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .billing-grid > * {
        width: 100%;
        min-width: 0;
    }

    .billing-note {
        margin-bottom: 1rem;
        padding: 0.85rem 1rem;
        border-radius: 0.9rem;
        color: var(--billing-primary);
        background: color-mix(in srgb, var(--billing-primary) 8%, var(--billing-surface));
        font-weight: 700;
    }

    .billing-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: color-mix(in srgb, var(--billing-ink) 62%, var(--billing-surface));
        font-weight: 700;
    }

    .billing-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-top: 1rem;
    }

    .billing-alert {
        display: flex;
        gap: 0.65rem;
        align-items: start;
        margin-bottom: 1rem;
        padding: 0.9rem 1rem;
        border: 1px solid color-mix(in srgb, var(--billing-primary) 18%, var(--billing-surface));
        border-radius: 0.9rem;
        color: var(--billing-ink);
        background: var(--billing-surface);
        font-weight: 700;
    }

    .billing-alert-success-icon {
        color: var(--vf-green);
        background: #fff !important;
        border-radius: 50%;
    }

    .billing-modal .modal-content {
        border: 1px solid color-mix(in srgb, var(--billing-primary) 18%, var(--billing-surface));
        border-radius: 1rem;
        color: var(--billing-ink);
    }

    .billing-modal .modal-footer .billing-button,
    .billing-modal .modal-footer .billing-button-outline {
        padding-inline: 1rem;
        border-width: 1px !important;
        border-style: solid !important;
    }

    .billing-modal .modal-footer .billing-button {
        border-color: var(--billing-primary) !important;
        color: var(--billing-surface) !important;
    }

    .billing-modal .modal-footer .billing-button-outline {
        border-color: color-mix(in srgb, var(--billing-primary) 30%, var(--billing-surface)) !important;
        color: var(--billing-primary) !important;
        background: var(--billing-surface) !important;
    }

    .billing-modal .modal-title {
        font-weight: 800;
    }

    @media (max-width: 1199.98px) {
        .billing-stat-row,
        .billing-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .billing-filter {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .billing-grid {
            grid-template-columns: 1fr;
        }

        .billing-card-head {
            flex-direction: column;
            align-items: stretch;
        }

        .billing-card-head .billing-button,
        .billing-card-head .billing-button-outline {
            width: 100%;
        }

        .cons-hero-layout { grid-template-columns: 1fr; gap: 1.5rem; }
        .cons-hero-meta { grid-template-columns: repeat(2, 1fr); }
        .cons-stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 767.98px) {
        .billing-hero,
        .billing-card-head,
        .billing-pagination {
            grid-template-columns: 1fr;
            flex-direction: column;
            align-items: stretch;
        }

        .billing-actions,
        .billing-filter-actions {
            justify-content: stretch;
        }

        .billing-actions > *,
        .billing-filter-actions > * {
            flex: 1 1 auto;
        }

        .billing-grid,
        .billing-filter {
            grid-template-columns: 1fr;
        }

        .billing-title {
            font-size: 1.55rem;
        }

        .billing-table {
            min-width: 560px;
        }

        .cons-balance-amount { font-size: 2.4rem; }
        .cons-hero { padding: 1.5rem; }
        .cons-overview-card { padding: 1.25rem; }
        .cons-stats-row { grid-template-columns: 1fr; }
    }


    .cons-card {
        background: var(--billing-surface);
        border: 1px solid var(--billing-border);
        border-radius: 1.15rem;
        padding: 1.75rem 2rem;
        margin-bottom: 1.25rem;
    }

    .cons-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .cons-card-filter {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .cons-card-filter-label {
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--billing-muted);
        white-space: nowrap;
    }

    .cons-card-filter .form-control {
        height: 2.4rem;
        border-radius: 0.7rem;
        border: 1px solid var(--billing-border);
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--billing-ink);
        min-width: 0;
    }

    .cons-card-filter .form-control:focus {
        border-color: var(--billing-primary);
        box-shadow: none;
    }

    .cons-apply-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        height: 2.4rem;
        padding: 0 1.1rem;
        border-radius: 0.7rem;
        background: var(--billing-primary);
        color: #fff;
        font-size: 0.85rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: opacity 0.15s;
        white-space: nowrap;
    }

    .cons-apply-btn:hover { opacity: 1; }

    /* Full bill list rows */
    .cons-bill-row {
        display: grid;
        grid-template-columns: minmax(8.5rem, 1.3fr) minmax(4rem, .65fr) repeat(3, minmax(5.5rem, 1fr)) minmax(6rem, 1fr) minmax(6.5rem, 1fr) auto;
        align-items: center;
        gap: .75rem;
        padding: .85rem 1rem;
        border: 1px solid var(--billing-border);
        border-radius: 0.9rem;
        margin-bottom: 0.65rem;
        background: var(--billing-surface);
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .cons-bill-row:last-child { margin-bottom: 0; }

    .cons-bill-row:hover {
        border-color: color-mix(in srgb, var(--billing-primary) 35%, var(--billing-surface));
        box-shadow: 0 2px 10px rgba(7, 84, 200, 0.07);
    }

    .cons-bill-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.65rem;
        background: color-mix(in srgb, var(--billing-primary) 8%, var(--billing-surface));
        border: 1px solid color-mix(in srgb, var(--billing-primary) 18%, var(--billing-surface));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--billing-primary);
        font-size: 1rem;
        flex-shrink: 0;
    }

    .cons-bill-identity {
        display: flex;
        align-items: center;
        gap: .65rem;
        min-width: 0;
    }

    .cons-bill-identity > div:last-child { min-width: 0; }

    .cons-bill-period {
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--billing-ink);
        margin-bottom: 0.1rem;
    }

    .cons-bill-prn {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--billing-muted);
    }

    .cons-bill-field-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--billing-muted);
        margin-bottom: 0.15rem;
    }

    .cons-bill-field-value {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--billing-ink);
    }

    .cons-bill-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        justify-self: end;
        gap: 0.4rem;
    }

    .cons-view-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.4rem 0.9rem;
        border-radius: 0.6rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--billing-primary);
        border: 1.5px solid color-mix(in srgb, var(--billing-primary) 30%, var(--billing-surface));
        background: var(--billing-surface);
        text-decoration: none;
        transition: all 0.15s;
        white-space: nowrap;
    }

    .cons-view-btn:hover {
        background: color-mix(in srgb, var(--billing-primary) 8%, var(--billing-surface));
        color: var(--billing-primary);
    }

    .cons-bill-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        color: var(--billing-muted);
        font-weight: 600;
        font-size: 0.9rem;
    }

    .cons-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--billing-border);
    }

    .cons-pagination-info {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--billing-muted);
    }

    @media (max-width: 575.98px) {
        .billing-card {
            padding: 1rem !important;
        }

        .billing-table {
            min-width: 520px;
        }

        .cons-hero-actions { flex-direction: column; }
        .cons-btn-pill { width: 100%; justify-content: center; }
        .cons-hero-meta { grid-template-columns: 1fr; }
        .cons-card { padding: 1.25rem; }
        .cons-card-filter { flex-wrap: wrap; }
    }
    /* Consumer billing layout and navigation */
    .billing-page:has(.cons-overview-card) .topbar { z-index: 1030; overflow: visible; }
    .billing-page:has(.cons-overview-card) .topbar .dropdown-menu { max-width: calc(100vw - 1.5rem); overflow-wrap: anywhere; }
    .billing-page:has(.cons-overview-card) .content-wrapper { min-width: 0; }
    .billing-page .billing-hero, .billing-page .billing-hero > div, .billing-page .cons-hero-layout > div { min-width: 0; }
    .billing-page :is(.billing-title, .billing-copy, .cons-hero-meta-value, .cons-stat-tile-value, .cons-stat-tile-sub, .cons-bill-field-value, .cons-bill-prn) { overflow-wrap: anywhere; }
    .billing-page .cons-overview-card { border-color: var(--vf-light); box-shadow: 0 8px 24px rgba(7, 84, 200, .10); }
    .billing-page .cons-stat-tile { min-width: 0; height: 100%; background: var(--vf-background); border-color: var(--vf-light); }
    .billing-page .cons-stat-tile-value { color: var(--vf-navy); }
    .billing-page .cons-bill-row > * { min-width: 0; }
    @media (max-width: 1250px) {
        .billing-page .cons-bill-row {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem 1rem;
        }
    }
    @media (max-width: 767.98px) {
        .billing-page .cons-bill-row { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .billing-page .cons-bill-identity { grid-column: 1; grid-row: 1; }
        .billing-page .cons-bill-actions { grid-column: 2; grid-row: 1; align-items: flex-end; }
        .billing-page .cons-bill-icon { width: 2rem; height: 2rem; }
        .billing-page .cons-view-btn { padding: .4rem .55rem; }
        .billing-page .cons-hero { padding: 1.25rem; }
    }

    @media (max-width: 360px) {
        .billing-page .cons-bill-identity { grid-column: 1 / -1; grid-row: auto; }
        .billing-page .cons-bill-actions { grid-column: 1 / -1; grid-row: auto; }
    }
</style>
<?php $__env->stopPush(); ?>

<div class="app-shell d-lg-flex billing-page">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => $activePage], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="content-wrapper">
            <?php if(! $canManageBilling): ?>

                <div class="billing-hero" style="margin-bottom:1.25rem;">
                    <div>
                        <h1 class="billing-title">Water Billing</h1>
                        <p class="billing-copy">Review your current balance, usage patterns, and account actions from one place.</p>
                    </div>
                </div>


                <?php
                    $consLatestBill   = $visibleBills->sortByDesc('billing_period')->first();
                    $consConsumer     = $consLatestBill?->consumer;
                    $consPaymentSummary = $consumerPaymentRuleSummary ?? [
                        'active' => false,
                        'notice' => 'None',
                        'total_outstanding' => $consumerTotalBalanceDue ?? $consLatestBill?->balance_due ?? 0,
                        'required_payment_amount' => null,
                        'message' => 'Partial payment is allowed.',
                    ];
                    $consTotalDue     = (float) ($consPaymentSummary['total_outstanding'] ?? $consumerTotalBalanceDue ?? $consLatestBill?->balance_due ?? 0);
                    $consUsage = $consLatestBill?->units_consumed ?? $consLatestBill?->meterReading?->consumption;
                    $consLatestUsage = $consUsage !== null ? number_format((float) $consUsage, 1).' m³' : 'No reading recorded yet';
                    $consLatestPeriod = $consLatestBill?->billing_period?->format('M Y') ?? 'No billing period yet';
                    $consOpenCount    = $visibleOpenCount;
                    $consHasDisconnectionNotice = (bool) ($consPaymentSummary['active'] ?? false);
                    $consRequiredPaymentAmount = $consHasDisconnectionNotice ? (float) ($consPaymentSummary['required_payment_amount'] ?? $consTotalDue) : null;
                    $consPaymentRuleMessage = $consPaymentSummary['message'] ?? ($consHasDisconnectionNotice
                        ? 'Full payment is required. Partial payment is not allowed.'
                        : 'Partial payment is allowed.');
                    $consVerifiedPaidAmount = (float) $visibleBills->sum(fn ($bill) => $bill->verified_paid_amount);
                    $consPaymentStatus = match (true) {
                        $consHasDisconnectionNotice => 'Disconnection Notice',
                        round($consTotalDue, 2) <= 0.0 => 'Paid',
                        $consVerifiedPaidAmount > 0 => 'Paid with Balance',
                        default => 'Unpaid',
                    };
                ?>
                <div class="cons-hero">
                    <div class="cons-eyebrow">
                        <i class="bi bi-lightning-fill"></i> Account Billing Snapshot
                    </div>
                    <div class="cons-hero-layout">
                        
                        <div>
                            <div class="cons-balance-label">Total balance</div>
                            <div class="cons-balance-amount">&#8369;<?php echo e(number_format($consTotalDue, 2)); ?></div>
                            <div class="cons-balance-note">
                                Remaining balance after verified payments for the latest bill.
                            </div>
                            <div class="cons-hero-actions">
                                <a href="<?php echo e(route('payments.index')); ?>" class="cons-btn-pill cons-btn-white">
                                    <i class="bi bi-credit-card"></i> Pay Online
                                </a>
                                <button class="cons-btn-pill cons-btn-ghost" onclick="window.location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh Overview
                                </button>
                            </div>
                        </div>
                        
                        <?php if($consConsumer): ?>
                            <div class="cons-hero-meta d-none d-sm-block">
                                <div>
                                    <div class="cons-hero-meta-label">Account Holder</div>
                                    <div class="cons-hero-meta-value"><?php echo e($consConsumer->full_name); ?></div>
                                </div>
                                <div>
                                    <div class="cons-hero-meta-label">Account Number</div>
                                    <div class="cons-hero-meta-value"><?php echo e($consConsumer->account_number); ?></div>
                                </div>
                                <div class="cons-hero-meta-wide">
                                    <div class="cons-hero-meta-label">Service Area</div>
                                    <div class="cons-hero-meta-value"><?php echo e($consConsumer->service_address); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="billing-alert" role="alert">
                    <i class="bi <?php echo e($consHasDisconnectionNotice ? 'bi-exclamation-triangle' : 'bi-check-circle'); ?> mt-1"></i>
                    <div>
                        <strong>Account Payment Requirements</strong><br>
                        Payment Status: <strong><?php echo e($consPaymentStatus); ?></strong><br>
                        Disconnection Notice: <strong><?php echo e($consHasDisconnectionNotice ? 'Active' : 'None'); ?></strong><br>
                        Total Outstanding Balance: <strong>&#8369;<?php echo e(number_format($consTotalDue, 2)); ?></strong><br>
                        <?php if($consHasDisconnectionNotice): ?>
                            Required Payment Amount: <strong>&#8369;<?php echo e(number_format($consRequiredPaymentAmount, 2)); ?></strong><br>
                        <?php endif; ?>
                        <?php echo e($consPaymentRuleMessage); ?>

                    </div>
                </div>


                <div class="cons-overview-card">
                    <div class="cons-overview-header">
                        <div>
                            <div class="cons-section-label">Current Bill</div>
                            <h2 class="cons-section-title">Billing Overview</h2>
                        </div>
                        <?php if($consLatestBill): ?>
                            <?php if (isset($component)) { $__componentOriginal8c81617a70e11bcf247c4db924ab1b62 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-badge','data' => ['status' => $consLatestBill->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($consLatestBill->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $attributes = $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $component = $__componentOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="cons-stats-row">
                        <div class="cons-stat-tile">
                            <div class="cons-stat-tile-label">Latest Period</div>
                            <div class="cons-stat-tile-value"><?php echo e($consLatestPeriod); ?></div>
                            <div class="cons-stat-tile-sub">Most recent generated bill</div>
                        </div>
                        <div class="cons-stat-tile">
                            <div class="cons-stat-tile-label">Latest Usage</div>
                            <div class="cons-stat-tile-value"><?php echo e($consLatestUsage); ?></div>
                            <div class="cons-stat-tile-sub">Recorded consumption units</div>
                        </div>
                        <div class="cons-stat-tile">
                            <div class="cons-stat-tile-label">Outstanding Bills</div>
                            <div class="cons-stat-tile-value"><?php echo e($consOpenCount); ?></div>
                            <div class="cons-stat-tile-sub">Open records awaiting settlement</div>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <div class="billing-hero">
                    <div>
                        <h1 class="billing-title"><?php echo e($billingHeading); ?></h1>
                        <p class="billing-copy">Review billing records, check payment status, and request connection support from one clean workspace.</p>
                    </div>
                    <div class="billing-actions">
                        <button class="billing-button-outline" type="button" data-bs-toggle="modal" data-bs-target="#billingGenerationModal">
                            <i class="bi bi-gear"></i>Generate Cycle
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(session('success')): ?>
                <div class="billing-alert" role="alert">
                    <i class="bi bi-check-circle mt-1 billing-alert-success-icon"></i>
                    <div><?php echo e(session('success')); ?></div>
                </div>
            <?php endif; ?>

            <?php if($errors->any()): ?>
                <div class="billing-alert" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div><?php echo e($errors->first()); ?></div>
                </div>
            <?php endif; ?>

            <?php if($canManageBilling): ?>

                <div class="panel-card p-4" data-admin-records-root data-admin-freeze-section>
                    <div class="billing-card-head" data-admin-freeze>
                        <div>
                            <h2 class="billing-section-title">Billing Records</h2>
                            <p class="billing-section-copy">Filter by period, status, or PRN to narrow the current billing list.</p>
                        </div>
                        <span class="billing-badge"><?php echo e(number_format((float) $billingSummary['total_amount'], 2)); ?> total amount</span>
                    </div>

                    <form method="GET" action="<?php echo e(route('billing.index')); ?>" class="billing-filter" data-admin-freeze data-auto-filter data-auto-filter-debounce="450">
                        <div class="billing-field">
                            <label class="billing-label" for="billing_filter_period">Period</label>
                            <input type="month" id="billing_filter_period" name="billing_period" value="<?php echo e($filters['billing_period']); ?>" class="form-control">
                        </div>
                        <div class="billing-field">
                            
                            <div class="d-flex align-items-center gap-2 status-filter-display" style="min-width:0"><select aria-label="Status filter" data-status-filter data-clearable-filter style="min-width:0;flex:1 1 0" id="billing_filter_status" name="status" class="form-select">
                                <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status); ?>" <?php if($filters['status'] === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
<button type="submit" name="status" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear status filter">Clear</button>
</div>
                        </div>
                        <div class="billing-field">
                            <label class="billing-label" for="billing_filter_prn">PRN</label>
                            <input type="text" id="billing_filter_prn" name="prn" value="<?php echo e($filters['prn']); ?>" class="form-control" placeholder="Search PRN">
                        </div>
                        <div class="billing-filter-actions">
                            <?php if($filters['billing_period'] !== '' || $filters['status'] !== '' || $filters['prn'] !== ''): ?><a href="<?php echo e(route('billing.index')); ?>" class="billing-button-outline">Reset</a><?php endif; ?>
                            <noscript>
                                <button type="submit" class="billing-button">Apply filters</button>
                            </noscript>
                        </div>
                    </form>

                        <div class="table-responsive">
                            <table class="table align-middle billing-table">
                                <thead>
                                    <tr>
                                        <th>Consumer</th>
                                        <th>Billing Period</th>
                                        <th>PRN</th>
                                        <th>Previous</th>
                                        <th>Current</th>
                                        <th>Consumption</th>
                                        <th>Rate</th>
                                        <th>Previous Balance</th>
                                        <th>Current Charges</th>
                                        <th>Total Due</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th class="text-end">Statement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $billingRecords; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <?php if($record->record_type === 'reading'): ?>
                                            <?php $reading = $record->item; ?>
                                            <tr>
                                                <td><div class="billing-name"><?php echo e($reading->consumer->full_name); ?></div><div class="billing-meta"><?php echo e($reading->consumer->account_number); ?></div></td>
                                                <td><?php echo e($reading->reading_date->format('F Y')); ?></td>
                                                <td>—</td>
                                                <td><?php echo e(number_format((float) $reading->previous_reading, 2)); ?></td>
                                                <td><?php echo e(number_format((float) $reading->current_reading, 2)); ?></td>
                                                <td><?php echo e(number_format((float) $reading->consumption, 2)); ?> m3</td>
                                                <td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $bill = $record->item; ?>
                                        <?php
                                            $breakdown = $bill->billing_breakdown;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="billing-name"><?php echo e($bill->consumer->full_name); ?></div>
                                                <div class="billing-meta"><?php echo e($bill->consumer->account_number); ?></div>
                                            </td>
                                            <td><?php echo e($bill->billing_period->format('F Y')); ?></td>
                                            <td><span class="billing-badge"><?php echo e($bill->prn); ?></span>
                                                <?php if($bill->is_demo): ?><span class="badge bg-secondary">Demo</span><?php endif; ?>
                                            </td>
                                            <td><?php echo e(number_format((float) ($breakdown['previous_meter_reading'] ?? 0), 2)); ?></td>
                                            <td><?php echo e(number_format((float) ($breakdown['current_meter_reading'] ?? 0), 2)); ?></td>
                                            <td><?php echo e(number_format((float) ($breakdown['total_consumption'] ?? 0), 2)); ?> m3</td>
                                            <td>&#8369;<?php echo e(number_format((float) ($breakdown['rate_per_cubic_meter'] ?? 0), 2)); ?></td>
                                            <td class="billing-amount">&#8369;<?php echo e(number_format((float) ($breakdown['previous_unpaid_balance'] ?? 0), 2)); ?></td>
                                            <td class="billing-amount">&#8369;<?php echo e(number_format((float) ($breakdown['current_bill_amount'] ?? 0), 2)); ?></td>
                                            <td class="billing-amount">&#8369;<?php echo e(number_format((float) $bill->amount, 2)); ?></td>
                                            <td class="billing-amount"><?php echo e($bill->statement_balance === null ? 'Not available' : '₱'.number_format($bill->statement_balance, 2)); ?><?php if($bill->balanceIsCarriedForward()): ?><div class="billing-meta">Carried forward</div><?php endif; ?></td>
                                            <td>
                                                <?php
                                                    $billDisplayStatus = $bill->balanceIsCarriedForward() ? 'Carried Forward' : $bill->status;
                                                    $billDisplayClass = match ($bill->status) {
                                                        'Paid' => 'badge-soft-success',
                                                        'Overdue' => 'badge-soft-danger',
                                                        default => 'badge-soft-warning',
                                                    };
                                                ?>
                                                <span class="badge <?php echo e($billDisplayClass); ?>"><?php echo e($billDisplayStatus); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?php echo e(route('billing.show', array_filter(['bill' => $bill->id, 'billing_period' => $selectedBillingPeriod ?: null]))); ?>" class="billing-icon-button" aria-label="View billing statement">
                                                    <i class="bi bi-arrow-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr>
                                            <td colspan="13">
                                                <div class="billing-empty">
                                                    <div><?php echo e($hasBillingFilters ? 'No billing records match the selected filters.' : 'No billing records yet.'); ?></div>
                                                    <button class="billing-button mt-3" type="button" data-bs-toggle="modal" data-bs-target="#billingModal">Create Billing Record</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <div class="billing-pagination">
                        <p class="billing-meta mb-0">
                            <?php if($billingRecords->total() > 0): ?>
                                Showing <?php echo e($billingRecords->firstItem()); ?> to <?php echo e($billingRecords->lastItem()); ?> of <?php echo e($billingRecords->total()); ?> records
                            <?php else: ?>
                                <?php echo e($hasBillingFilters ? 'No billing records match the selected filters' : 'No billing records yet'); ?>

                            <?php endif; ?>
                        </p>
                        <?php echo e($billingRecords->links()); ?>

                    </div>
                </div>
            <?php else: ?>

                <div class="cons-card">
                    <div class="cons-card-header">
                        <div>
                            <div class="cons-section-label">Billing Records</div>
                            <h2 class="cons-section-title">Full bill list</h2>
                            <p style="font-size:0.83rem;font-weight:500;color:var(--billing-muted);margin:0.25rem 0 0;">Filter by billing month to narrow the records shown below.</p>
                        </div>
                        <?php if($consHasDisconnectionNotice): ?>
                            <span class="badge badge-soft-danger">Disconnection Notice</span>
                        <?php endif; ?>
                        <form method="GET" action="<?php echo e(route('billing.index')); ?>" class="cons-card-filter" id="consumerBillingPeriodFilterForm" data-auto-filter data-auto-filter-debounce="450">
                            <span class="cons-card-filter-label">Filter Period</span>
                            <input type="month" id="cons_period" name="billing_period" value="<?php echo e($selectedBillingPeriod); ?>" class="form-control">
                            <noscript>
                                <button type="submit" class="cons-apply-btn border-0">Apply filters</button>
                            </noscript>
                            <?php if($selectedBillingPeriod !== ''): ?>
                                <a href="<?php echo e(route('billing.index')); ?>" class="cons-apply-btn text-decoration-none">
                                    Show All
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php $__empty_1 = true; $__currentLoopData = $filteredBills; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bill): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $billBreakdown = $bill->billing_breakdown;
                        ?>
                        <div class="cons-bill-row">
                            <div class="cons-bill-identity">
                                <div class="cons-bill-icon">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <div>
                                    <div class="cons-bill-period"><?php echo e($bill->billing_period->format('F Y')); ?></div>
                                    <div class="cons-bill-prn">PRN: <?php echo e($bill->prn); ?>

                                        <?php if($bill->is_demo): ?><span class="badge bg-secondary">Demo</span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="cons-bill-field-label">Usage</div>
                                <div class="cons-bill-field-value"><?php echo e(($bill->units_consumed ?? $bill->meterReading?->consumption) !== null ? number_format((float) ($bill->units_consumed ?? $bill->meterReading?->consumption), 1).' m³' : 'No reading recorded yet'); ?></div>
                            </div>
                            <div>
                                <div class="cons-bill-field-label">Previous Balance</div>
                                <div class="cons-bill-field-value">&#8369;<?php echo e(number_format((float) ($billBreakdown['previous_unpaid_balance'] ?? 0), 2)); ?></div>
                            </div>
                            <div>
                                <div class="cons-bill-field-label">Current Charges</div>
                                <div class="cons-bill-field-value">&#8369;<?php echo e(number_format((float) ($billBreakdown['current_bill_amount'] ?? 0), 2)); ?></div>
                            </div>
                            <div>
                                <div class="cons-bill-field-label">Total Amount</div>
                                <div class="cons-bill-field-value">&#8369;<?php echo e(number_format((float) $bill->amount, 2)); ?></div>
                            </div>
                            <div>
                                <div class="cons-bill-field-label">Total Balance</div>
                                <div class="cons-bill-field-value"><?php echo e($bill->statement_balance === null ? 'Not available' : '₱'.number_format($bill->statement_balance, 2)); ?><?php if($bill->balanceIsCarriedForward()): ?> <span class="small">(carried forward)</span><?php endif; ?></div>
                            </div>
                            <div>
                                <div class="cons-bill-field-label">Payment Deadline</div>
                                <div class="cons-bill-field-value"><?php echo e($bill->due_date->format('M d, Y')); ?></div>
                            </div>
                            <div class="cons-bill-actions">
                                <a href="<?php echo e(route('billing.show', array_filter(['bill' => $bill->id, 'billing_period' => $selectedBillingPeriod ?: null]))); ?>" class="cons-view-btn">
                                    View Statement
                                </a>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="cons-bill-empty">
                            <i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;opacity:0.4;"></i>
                            <?php echo e($selectedBillingPeriod !== '' ? 'No billing record found for the selected month.' : 'No billing records found.'); ?>

                        </div>
                    <?php endif; ?>

                    <?php if($filteredBills->hasPages()): ?>
                        <div class="cons-pagination">
                            <span class="cons-pagination-info">
                                Showing <?php echo e($filteredBills->firstItem()); ?> to <?php echo e($filteredBills->lastItem()); ?> of <?php echo e($filteredBills->total()); ?> records
                            </span>
                            <?php echo e($filteredBills->links()); ?>

                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<?php if($canManageBilling): ?>
    <div class="modal fade billing-modal" id="billingGenerationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Generate Billing Cycle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="<?php echo e(route('billing.generate')); ?>" id="billingGenerationForm">
                        <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="generation_billing_period">Billing Period</label>
                                <input type="month" class="form-control" id="generation_billing_period" name="billing_period" value="<?php echo e(old('billing_period', now()->format('Y-m'))); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="generation_due_date">Due Date</label>
                                <input type="date" class="form-control" id="generation_due_date" name="due_date" value="<?php echo e(old('due_date', now()->addDays(15)->toDateString())); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="base_charge">Base Charge</label>
                                <input type="number" class="form-control" id="base_charge" name="base_charge" step="0.01" min="0" value="<?php echo e(old('base_charge', $defaults['base_charge'])); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="member_unit_rate">Member Unit Rate</label>
                                <input type="number" class="form-control" id="member_unit_rate" name="member_unit_rate" step="0.01" min="0" value="<?php echo e(old('member_unit_rate', $defaults['member_unit_rate'])); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="non_member_unit_rate">Non-Member Unit Rate</label>
                                <input type="number" class="form-control" id="non_member_unit_rate" name="non_member_unit_rate" step="0.01" min="0" value="<?php echo e(old('non_member_unit_rate', $defaults['non_member_unit_rate'])); ?>" required>
                            </div>
                            <?php if(($pendingLoanSetups ?? collect())->isNotEmpty()): ?>
                                <div class="col-12">
                                    <div class="billing-meta fw-semibold mb-2">Loan Setup</div>
                                    <div class="d-grid gap-3">
                                        <?php $__currentLoopData = $pendingLoanSetups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pendingLoan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $oldLoanAmount = old("loan_amounts.{$pendingLoan->id}");
                                                $previewAmount = is_numeric($oldLoanAmount) ? round((float) $oldLoanAmount / max(1, (int) $pendingLoan->term_months), 2) : 0;
                                            ?>
                                            <div class="billing-table-card p-3">
                                                <div class="d-flex justify-content-between gap-3 flex-wrap mb-2">
                                                    <div>
                                                        <div class="fw-bold"><?php echo e($pendingLoan->consumer?->full_name ?? 'Consumer'); ?></div>
                                                        <div class="billing-meta"><?php echo e($pendingLoan->consumer?->account_number ?? 'No account number'); ?></div>
                                                    </div>
                                                    <span class="billing-badge"><?php echo e($pendingLoan->term_months); ?> months</span>
                                                </div>
                                                <label class="form-label fw-semibold" for="loan_amount_<?php echo e($pendingLoan->id); ?>">Loan Amount</label>
                                                <input
                                                    type="number"
                                                    class="form-control billing-loan-amount"
                                                    id="loan_amount_<?php echo e($pendingLoan->id); ?>"
                                                    name="loan_amounts[<?php echo e($pendingLoan->id); ?>]"
                                                    step="0.01"
                                                    min="0.01"
                                                    value="<?php echo e($oldLoanAmount); ?>"
                                                    data-loan-term="<?php echo e($pendingLoan->term_months); ?>"
                                                    data-preview-id="loan_preview_<?php echo e($pendingLoan->id); ?>"
                                                    required
                                                >
                                                <div class="billing-meta mt-2" id="loan_preview_<?php echo e($pendingLoan->id); ?>">
                                                    Loan Amount: &#8369;<?php echo e(is_numeric($oldLoanAmount) ? number_format((float) $oldLoanAmount, 2) : '0.00'); ?><br>
                                                    Repayment Term: <?php echo e($pendingLoan->term_months); ?> months<br>
                                                    Monthly Loan Installment: &#8369;<?php echo e(number_format($previewAmount, 2)); ?>

                                                </div>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <div class="billing-meta">Member bills use the member rate. Non-member bills use the non-member rate automatically.</div>
                                <div class="billing-meta mt-1">Each bill total is the previous outstanding balance plus the current month's water charges. Penalty and minimum charges are no longer applied to new bills.</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="billing-button-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="billingGenerationForm" class="billing-button">Generate Bills</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade billing-modal" id="billingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Create Billing Record</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="<?php echo e(route('billing.store')); ?>" id="billingCreateForm">
                        <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="consumer_id">Consumer</label>
                                <select class="form-select" id="consumer_id" name="consumer_id" required>
                                    <option value="">Select consumer</option>
                                    <?php $__currentLoopData = $consumers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $consumer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($consumer->id); ?>" <?php if((string) old('consumer_id') === (string) $consumer->id): echo 'selected'; endif; ?>>
                                            <?php echo e($consumer->account_number); ?> - <?php echo e($consumer->full_name); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="billing_period">Billing Period</label>
                                <input type="month" class="form-control" id="billing_period" name="billing_period" value="<?php echo e(old('billing_period', now()->format('Y-m'))); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="due_date">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo e(old('due_date', now()->addDays(15)->toDateString())); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="prn">PRN</label>
                                <input type="text" class="form-control" id="prn" name="prn" value="<?php echo e(old('prn')); ?>" placeholder="PRN-230011" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="amount">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" value="<?php echo e(old('amount')); ?>" placeholder="425.00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="status">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($status); ?>" <?php if(old('status', 'Unpaid') === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="months_unpaid">Months Unpaid</label>
                                <input type="number" class="form-control" id="months_unpaid" name="months_unpaid" min="0" max="24" value="<?php echo e(old('months_unpaid', 0)); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="notice_level">Notice Level</label>
                                <input type="text" class="form-control" id="notice_level" name="notice_level" value="<?php echo e(old('notice_level')); ?>" placeholder="Final warning">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="request_type">Request Type</label>
                                <select class="form-select" id="request_type" name="request_type">
                                    <option value="">None</option>
                                    <?php $__currentLoopData = $requestTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($type); ?>" <?php if(old('request_type') === $type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="request_status">Request Status</label>
                                <select class="form-select" id="request_status" name="request_status">
                                    <option value="">None</option>
                                    <?php $__currentLoopData = $requestStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $requestStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($requestStatus); ?>" <?php if(old('request_status') === $requestStatus): echo 'selected'; endif; ?>><?php echo e($requestStatus); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="billing-button-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="billingCreateForm" class="billing-button">Save Billing Record</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php $__env->startPush('scripts'); ?>
    <?php if($canManageBilling): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.billing-loan-amount').forEach(function (input) {
                    const preview = document.getElementById(input.dataset.previewId || '');
                    const term = Number(input.dataset.loanTerm || 0);

                    if (! preview || ! [3, 6, 12].includes(term)) {
                        return;
                    }

                    const formatMoney = function (value) {
                        return Number(value || 0).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    };

                    const refreshPreview = function () {
                        const loanAmount = Number(input.value || 0);
                        const installment = term > 0 ? Math.round((loanAmount / term) * 100) / 100 : 0;

                        preview.innerHTML = `Loan Amount: &#8369;${formatMoney(loanAmount)}<br>Repayment Term: ${term} months<br>Monthly Loan Installment: &#8369;${formatMoney(installment)}`;
                    };

                    input.addEventListener('input', refreshPreview);
                    refreshPreview();
                });
            });
        </script>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <script>
            const shouldShowGenerationModal = <?php echo json_encode($canManageBilling && (old('member_unit_rate') !== null || old('non_member_unit_rate') !== null || old('base_charge') !== null || old('loan_amounts') !== null), 15, 512) ?>;
            const shouldShowBillingModal = <?php echo json_encode(! session('service_request_modal') && ! session('service_request_review_id'), 15, 512) ?>;
            const modalElement = shouldShowBillingModal
                ? document.getElementById(shouldShowGenerationModal ? 'billingGenerationModal' : 'billingModal')
                : null;

            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        </script>
    <?php endif; ?>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/shared/billing/index-ui.blade.php ENDPATH**/ ?>