.stmt-document {
    width: 100%;
    max-width: 850px;
    margin: 0 auto;
    color: #202020;
    background: #fff;
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.25;
}

.app-shell.stmt-page .stmt-document table {
    color: inherit;
}

.stmt-document .stmt-consumer-field strong {
    text-transform: uppercase;
}

.stmt-document .stmt-unit {
    white-space: nowrap;
}

.stmt-document table {
    width: 100%;
    border: 1px solid #303030;
    border-collapse: collapse;
    table-layout: fixed;
}

.stmt-document th,
.stmt-document td {
    padding: 4px 7px;
    border: 1px solid #414141;
    text-align: left;
    vertical-align: middle;
    overflow-wrap: break-word;
}

.stmt-document th {
    font-weight: normal;
}

.stmt-document .stmt-bill {
    border: 2px solid #303030;
}

.stmt-label-column {
    width: 44%;
}

.stmt-reading-column {
    width: 15%;
}

.stmt-unit-column {
    width: 9%;
}

.stmt-amount-column {
    width: 32%;
}

.stmt-detail-column {
    width: 50%;
}

.stmt-document .stmt-header {
    padding: 8px 7px 5px;
    text-align: center;
    font-weight: bold;
    line-height: 1.45;
}

.stmt-organization,
.stmt-document h1,
.stmt-band,
.stmt-total th {
    text-transform: uppercase;
}

.stmt-document h1 {
    margin: 18px 0 0;
    color: inherit;
    font-family: inherit;
    font-size: 1.16em;
    font-weight: bold;
    line-height: 1.2;
}

.stmt-inline-field {
    display: inline-block;
    margin-right: 1em;
}

.stmt-document .stmt-period,
.stmt-document .stmt-reminder {
    color: #762b35;
    font-weight: bold;
}

.stmt-document .stmt-band {
    text-align: center;
    font-weight: bold;
}

.stmt-document .stmt-number {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.stmt-document .stmt-indent {
    padding-left: 2em;
}

.stmt-document .stmt-total th,
.stmt-document .stmt-total td {
    font-weight: bold;
}

.stmt-document .stmt-reminder {
    text-align: center;
    font-style: italic;
}

.stmt-loan-note {
    font-size: 0.85em;
}

.stmt-details {
    margin-top: 10px;
    font-size: 0.9em;
}

@media screen and (max-width: 520px) {
    .stmt-document {
        font-size: 12px;
    }

    .stmt-document th,
    .stmt-document td {
        padding: 4px;
    }

    .stmt-document h1 {
        margin-top: 12px;
    }

    .stmt-label-column {
        width: 43%;
    }

    .stmt-unit-column {
        width: 10%;
    }

    .stmt-inline-field {
        margin-right: 0.55em;
    }
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 11mm;
    }

    html,
    body {
        min-height: 0 !important;
        height: auto !important;
        overflow: visible !important;
        background: #fff !important;
    }

    .stmt-page .sidebar,
    .stmt-page .sidebar-backdrop,
    .stmt-page .sidebar-reopen-toggle,
    .stmt-page .navbar,
    .stmt-page .topbar,
    .stmt-page .stmt-screen-only,
    body > .modal,
    body > .modal-backdrop {
        display: none !important;
    }

    .app-shell.stmt-page,
    .stmt-page .main-content,
    .stmt-page .content-wrapper {
        display: block !important;
        width: auto !important;
        max-width: none !important;
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        animation: none !important;
    }

    .stmt-page .stmt-document {
        max-width: none;
        font-size: 9.5pt;
    }

    .stmt-document th,
    .stmt-document td {
        padding: 2pt 4pt;
    }

    .stmt-document .stmt-bill,
    .stmt-document tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/shared/billing/statement-styles.blade.php ENDPATH**/ ?>