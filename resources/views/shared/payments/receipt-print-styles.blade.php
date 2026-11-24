@push('styles')
<style>
    .receipt-print-only {
        display: none !important;
    }

    @page {
        size: A4 portrait;
        margin: 12mm 16mm;
    }

    @media print {
        html,
        body {
            width: auto !important;
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
            scrollbar-gutter: auto !important;
            background: #ffffff !important;
        }

        body {
            color: #111827 !important;
            font-family: "Plus Jakarta Sans", Arial, sans-serif !important;
            font-size: 10pt !important;
            line-height: 1.4 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .receipt-page .sidebar,
        .receipt-page .sidebar-backdrop,
        .receipt-page .sidebar-reopen-toggle,
        .receipt-page .topbar,
        .receipt-page .page-heading,
        .receipt-page .content-wrapper > :not(.receipt-document),
        body > .modal,
        body > .modal-backdrop {
            display: none !important;
        }

        .receipt-page.app-shell {
            display: block !important;
            min-height: 0 !important;
            overflow: visible !important;
        }

        .receipt-page :is(.main-content, .content-wrapper) {
            width: 100% !important;
            max-width: none !important;
            height: auto !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
            background: #ffffff !important;
            animation: none !important;
            transition: none !important;
        }

        .receipt-page .content-wrapper::before {
            display: none !important;
        }

        .receipt-page #digital-receipt {
            --receipt-ink: #111111;
            --receipt-muted: #444444;
            --receipt-rule: #d5d5d5;
            --receipt-emphasis-rule: #888888;
            --receipt-heading-background: #ffffff;
            --receipt-total-background: #ffffff;
            width: 100% !important;
            max-width: var(--receipt-width) !important;
            margin: 0 auto !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: #ffffff !important;
            box-shadow: none !important;
            transition: none !important;
            color: var(--receipt-ink) !important;
            font-size: 9.5pt;
        }

        .receipt-document .receipt-header {
            padding-bottom: 1.75mm;
            margin-bottom: 2mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .receipt-document .receipt-brand {
            gap: 2mm;
        }

        .receipt-document .receipt-logo {
            width: 9mm;
            height: 9mm;
            filter: grayscale(1);
        }

        .receipt-document .receipt-brand-name {
            font-size: 11pt;
        }

        .receipt-document .receipt-brand-subtitle {
            font-size: 8.5pt;
        }

        .receipt-document .receipt-number {
            margin-top: 2mm;
        }

        .receipt-document :is(.receipt-payer-name, .receipt-number-value) { font-size: 10pt; }

        .receipt-document .receipt-parties > * {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .receipt-document :is(.receipt-parties, .receipt-summary) > * + * {
            margin-top: 1.5mm;
            padding-top: 1.5mm;
        }

        .receipt-document :is(.receipt-label, .receipt-number-label) { font-size: 8pt; }

        .receipt-document .receipt-meta dt,
        .receipt-document .receipt-details-table tbody td:first-child { font-size: 8.5pt; }

        .receipt-document .receipt-table-wrap {
            overflow: visible !important;
            margin-bottom: 2mm;
        }

        .receipt-document .receipt-table {
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            border-collapse: collapse !important;
        }

        .receipt-document .receipt-table thead {
            display: table-header-group !important;
        }

        .receipt-document .receipt-table :is(th, td) {
            padding: 1.1mm 1.5mm;
            border-width: 0 0 1px;
        }

        .receipt-document .receipt-table thead th { font-size: 8pt; }

        .receipt-document .receipt-table tbody tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .receipt-document .receipt-amount-row td:last-child { font-size: 11pt; }

        .receipt-document .receipt-notes {
            break-inside: auto;
            page-break-inside: auto;
        }

        .receipt-document .receipt-notes p {
            orphans: 3;
            widows: 3;
        }

        .receipt-document .receipt-summary,
        .receipt-document .receipt-notes,
        .receipt-document .receipt-notes p {
            break-after: avoid;
        }

        .receipt-document .status-badge {
            display: inline-block !important;
            padding: 0.3mm 2mm !important;
            border: 1px solid var(--receipt-emphasis-rule) !important;
            border-radius: 1mm !important;
            background: #ffffff !important;
            color: var(--receipt-ink) !important;
            font-size: 8.5pt !important;
            line-height: 1.3 !important;
        }

        .receipt-document .status-badge::before {
            display: none !important;
        }

        .receipt-document .btn,
        .receipt-document .receipt-screen-only {
            display: none !important;
        }

        .receipt-document .receipt-footer {
            display: block !important;
            margin-top: 3mm;
            padding-top: 2mm;
            border-top: 1px solid var(--receipt-emphasis-rule);
            color: var(--receipt-muted);
            font-size: 8pt !important;
            break-before: avoid;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .receipt-document .receipt-footer span { display: block; }

        .receipt-document h1,
        .receipt-document h2,
        .receipt-document h3,
        .receipt-document .receipt-label {
            break-after: avoid;
            page-break-after: avoid;
        }

        .receipt-print-only {
            display: block !important;
        }
    }
</style>
@endpush
