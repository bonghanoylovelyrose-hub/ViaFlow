@push('styles')
<style>
    .page-heading.inactive-account-heading {
        flex-direction: row;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .inactive-account-heading h1 {
        margin-bottom: 0;
    }

    @media (max-width: 575.98px) {
        .inactive-account-heading .btn {
            padding: 0.45rem 0.65rem;
            font-size: 0.8rem;
        }
    }

    .inactive-account-tabs {
        display: inline-flex;
        align-self: flex-start;
        gap: 0.25rem;
        max-width: 100%;
        margin-bottom: 0.875rem;
        padding: 0.25rem;
        border: 1px solid var(--wb-border);
        border-radius: 0.7rem;
        background: var(--vf-background);
    }

    .inactive-account-tab {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 0;
        padding: 0.45rem 0.7rem;
        border-radius: 0.45rem;
        color: var(--vf-navy);
        font-size: clamp(0.8rem, 3.4vw, 0.875rem);
        font-weight: 700;
        text-align: center;
        text-decoration: none;
    }

    .inactive-account-tab:hover {
        background: var(--vf-light);
    }

    .inactive-account-tab:focus-visible {
        outline: 2px solid var(--vf-royal);
        outline-offset: 2px;
    }

    .inactive-account-tab[aria-current="page"] {
        background: var(--vf-royal);
        color: var(--vf-white);
    }

    .inactive-records {
        container: inactive-records / inline-size;
        min-width: 0;
        padding: clamp(0.75rem, 2vw, 1.25rem) !important;
    }

    .admin-records-area .inactive-records.admin-records-surface {
        padding: clamp(0.75rem, 2vw, 1.25rem) !important;
    }

    .inactive-records .form-label {
        margin-bottom: 0.3rem;
    }

    #history-filters :is(.form-control, .form-select, .btn) {
        min-height: 2.65rem;
    }

    .inactive-records .table-responsive {
        border: 1px solid var(--wb-border);
        border-radius: 0.65rem;
    }

    .inactive-records :is(#consumer-accounts-table, #disconnection-history-table) {
        width: 100%;
        min-width: 700px;
        margin-bottom: 0;
        font-size: 0.875rem;
        table-layout: fixed;
    }

    .inactive-records #consumer-accounts-table col.consumer-col-standing { width: 8%; }
    .inactive-records #consumer-accounts-table col.consumer-col-status { width: 11%; }
    .inactive-records #consumer-accounts-table col.consumer-col-actions { width: 28%; }

    #disconnection-history-table col.history-time { width: 17%; }
    #disconnection-history-table col.history-status { width: 17%; }
    #disconnection-history-table col.history-actions { width: 7.5rem; }

    .inactive-records :is(#consumer-accounts-table, #disconnection-history-table) thead th {
        --bs-table-bg: var(--vf-background);
        color: var(--vf-navy);
        font-size: 0.75rem;
        vertical-align: middle;
        white-space: normal;
    }

    .inactive-records :is(#consumer-accounts-table, #disconnection-history-table) tbody > tr > td {
        padding: 0.6rem 0.55rem;
        vertical-align: middle;
        overflow-wrap: anywhere;
    }

    .inactive-records tbody tr:last-child > td {
        border-bottom: 0;
    }

    .inactive-records .badge {
        max-width: 100%;
        text-align: left;
        white-space: normal;
    }

    .inactive-records .consumer-status-badge > span {
        min-width: 0;
    }

    .inactive-records .consumer-status-badge {
        line-height: 1.2;
    }

    .inactive-records #consumer-accounts-table .consumer-actions {
        justify-content: flex-end;
    }

    .inactive-records #consumer-accounts-table .consumer-actions .btn {
        min-height: 2.2rem;
        font-size: 0.8rem;
    }

    .inactive-records .consumer-actions > .text-success {
        flex: 1 0 100%;
    }

    .inactive-record-label {
        display: none;
    }

    .inactive-records nav[aria-label="Pagination Navigation"] {
        margin-top: 0.8rem;
    }

    @container inactive-records (max-width: 699px) {
        .inactive-records .table-responsive {
            border: 0;
            border-radius: 0;
        }

        .inactive-records :is(#consumer-accounts-table, #disconnection-history-table) {
            display: block;
            min-width: 0;
        }

        .inactive-records table colgroup {
            display: none;
        }

        .inactive-records table thead {
            position: absolute !important;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            transform: none !important;
        }

        .inactive-records table tbody {
            display: grid;
            gap: 0.6rem;
        }

        .inactive-records table tbody > tr {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem 0.8rem;
            padding: 0.75rem;
            border: 1px solid var(--wb-border);
            border-radius: 0.65rem;
        }

        .inactive-records :is(#consumer-accounts-table, #disconnection-history-table) tbody > tr > td {
            display: block;
            padding: 0;
            border: 0;
        }

        .inactive-records table td:first-child,
        .inactive-records table td:last-child {
            grid-column: 1 / -1;
        }

        .inactive-records table td:first-child:not([colspan]) {
            font-size: 0.95rem;
        }

        .inactive-records :is(#consumer-accounts-table, #disconnection-history-table) td:last-child:not([colspan]) {
            padding-top: 0.55rem;
            border-top: 1px solid var(--wb-border);
            text-align: left !important;
        }

        .inactive-record-label {
            display: block;
            margin-bottom: 0.12rem;
            color: var(--bs-secondary-color);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .inactive-records #consumer-accounts-table .consumer-actions {
            min-width: 0;
            justify-content: flex-start;
        }
    }
</style>
@endpush
