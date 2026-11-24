<style>
    .app-shell:not(.admin-dashboard) .btn.viaflow-add-payment {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 10.625rem;
        min-height: 2.625rem;
        padding: 0.5rem 1rem;
        border: 1px solid #0755B8;
        border-radius: 0.75rem;
        background: linear-gradient(120deg, #0755B8, #168FE5);
        color: #fff;
        font-weight: 700;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(7, 85, 184, 0.22);
        transition: background-color 200ms ease, box-shadow 200ms ease, border-color 200ms ease;
    }
    .app-shell:not(.admin-dashboard) .btn.viaflow-add-payment i { color: inherit; }
    .app-shell:not(.admin-dashboard) .btn.viaflow-add-payment:not(:disabled):hover {
        box-shadow: 0 5px 16px rgba(22, 143, 229, 0.4);
    }
    .app-shell:not(.admin-dashboard) .btn.viaflow-add-payment:not(:disabled):active {
        box-shadow: 0 2px 7px rgba(7, 85, 184, 0.22);
    }
    .app-shell:not(.admin-dashboard) .btn.viaflow-add-payment:focus-visible {
        outline: 3px solid #168FE5;
        outline-offset: 3px;
    }
    .app-shell:not(.admin-dashboard) .btn.viaflow-add-payment:disabled {
        background: #a9b8c9;
        border-color: #a9b8c9;
        color: #fff;
        opacity: 1;
        box-shadow: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .app-shell:not(.admin-dashboard) .btn.viaflow-add-payment { transition: none !important; }
    }
    /* Shared compact Water and Loan balance card. */
    .payment-portal-wrapper .payment-balance-card { padding: 1rem !important; }
    .payment-balance-card .payment-balance-layout { align-items: start !important; gap: 0.875rem; }
    .payment-balance-card .payment-balance-layout > :first-child > .text-uppercase { margin-bottom: 0.25rem !important; }
    .payment-balance-card .display-5 { font-size: 1.875rem; line-height: 1.15; margin-bottom: 0.25rem !important; font-variant-numeric: tabular-nums; }
    .payment-balance-card .payment-requirements { display: block; width: fit-content; max-width: 100%; padding: 0.5rem 0.75rem; margin-top: 0.5rem !important; font-size: 0.8125rem; line-height: 1.25; }
    .payment-balance-card .payment-requirements > .fw-bold { margin-bottom: 0.25rem !important; }
    .payment-balance-card .payment-requirements > .mt-2 { margin-top: 0.25rem !important; }
    .payment-balance-card .payment-balance-action { align-self: stretch; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .app-shell:not(.admin-dashboard) .payment-balance-card .payment-balance-action .btn.viaflow-add-payment { width: auto; min-height: 2.625rem; padding: 0.5rem 1rem; }
    .payment-balance-card .payment-balance-notice { font-size: 0.8125rem; line-height: 1.35; padding: 0.5rem 0.625rem; }
    @media (min-width: 768px) {
        .payment-balance-card .payment-balance-layout { grid-template-columns: minmax(0, 1fr) minmax(16rem, 22rem); gap: 1rem; }
    }
    @media (max-width: 767.98px) {
        .payment-balance-card .payment-balance-action { width: 100%; }
        .payment-balance-card .payment-balance-notice { max-width: 100%; }
    }
</style>
