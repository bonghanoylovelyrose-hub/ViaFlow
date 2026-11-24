{{-- Shared presentation for consumer and meter reader screens. --}}
@if (auth()->check() && (auth()->user()->isConsumer() || auth()->user()->isMeterReader()))
<style>
@media screen {
    :root { --viaflow-dark: var(--vf-navy); --viaflow-teal: var(--vf-royal); --viaflow-aqua: var(--vf-bright); --viaflow-heading: var(--vf-navy); --viaflow-muted: var(--vf-navy); --viaflow-border: var(--vf-light); --viaflow-shadow: 0 8px 24px rgba(7, 84, 200, .10); }
    .app-shell { --wb-primary: var(--viaflow-dark); --wb-primary-dark: var(--viaflow-dark); --wb-accent: var(--viaflow-teal); --wb-text: var(--viaflow-heading); --wb-muted: var(--viaflow-muted); --wb-border: var(--viaflow-border); }
    .app-shell :is(.consumer-stat, .reader-stat) { min-width: 0; background: linear-gradient(145deg,#fff,#f7fdff); border: 1px solid var(--viaflow-border); border-radius: 16px; box-shadow: var(--viaflow-shadow); transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .app-shell :is(.consumer-stat, .reader-stat):hover { transform: translateY(-3px); border-color: var(--vf-bright); box-shadow: 0 13px 28px rgba(7, 84, 200, .16); }
    .app-shell :is(.consumer-stat-value, .reader-stat-value) { color: var(--viaflow-heading); overflow-wrap: anywhere; }
    .app-shell :is(.consumer-stat-label, .reader-stat-label) { color: var(--viaflow-muted); }
    .app-shell :is(.meter-queue-card, .meter-records-table) { border-color: var(--viaflow-border); background: #f7fdff; }
    .app-shell :is(.meter-queue-card, .meter-records-table) thead th { background: var(--vf-background); color: var(--viaflow-heading); border-color: var(--vf-light); }
    .app-shell :is(.meter-queue-card, .meter-records-table) tbody td { border-color: var(--vf-background); }
    .app-shell :is(.meter-focus-card, .meter-search-result) { background: linear-gradient(145deg,var(--vf-background),#fff); border-color: var(--viaflow-border); }
    .app-shell :is(.form-control, .form-select), .modal :is(.form-control, .form-select) { border-color: var(--vf-light); }
    .modal :is(.form-control, .form-select):focus { border-color: var(--viaflow-teal) !important; box-shadow: 0 0 0 .2rem rgba(22, 141, 255, .16) !important; }
    .modal-content, .reading-preview-card { border: 1px solid var(--viaflow-border) !important; border-radius: 16px !important; box-shadow: 0 16px 42px rgba(6, 43, 128, .18) !important; }
    .modal :is(.modal-title, .meter-reading-value, .reading-value), .reading-preview-card .reading-value { color: var(--viaflow-heading); }
    .modal :is(.meter-focus-card, .meter-search-result) { background: linear-gradient(145deg,var(--vf-background),#fff); border-color: var(--viaflow-border); }
    .modal .btn-primary { background: linear-gradient(120deg,var(--viaflow-dark),var(--viaflow-teal)) !important; border-color: var(--viaflow-teal) !important; color: #fff !important; }
    .app-shell .table-responsive { max-width: 100%; }
    @media (max-width: 767.98px) { .app-shell :is(.consumer-dashboard-grid, .reader-dashboard-grid) { grid-template-columns: repeat(2,minmax(0,1fr)); } }
    @media (max-width: 380px) { .app-shell :is(.consumer-dashboard-grid, .reader-dashboard-grid) { grid-template-columns: 1fr; } }
    @media (prefers-reduced-motion: reduce) { .app-shell :is(.consumer-stat, .reader-stat) { transition: none; } .app-shell :is(.consumer-stat, .reader-stat):hover { transform: none; } }
}
</style>
@endif

{{-- These account flows may also be opened from a signed, unauthenticated link. --}}
<style>
@media screen {
    :is(.resubmit-page, .reschedule-page, .disconnection-screen) { background: radial-gradient(ellipse at 100% 0,rgba(22, 141, 255, .15),transparent 42%),linear-gradient(150deg,var(--vf-background) 0%,var(--vf-background) 55%,#f6fdff 100%); }
    :is(.resubmit-shell, .reschedule-card, .disconnection-popup) { background: linear-gradient(145deg,#fff,#f7fdff); border: 1px solid var(--vf-light); border-radius: 16px; box-shadow: 0 8px 24px rgba(7, 84, 200, .10); }
    :is(.resubmit-page, .reschedule-page, .disconnection-screen) :is(h1,h2,h3) { color: var(--vf-navy); }
    :is(.resubmit-page, .reschedule-page, .disconnection-screen) :is(.form-control,.form-select) { border-color: var(--vf-light); border-radius: .65rem; }
    :is(.resubmit-page, .reschedule-page, .disconnection-screen) :is(.form-control,.form-select):focus { border-color: var(--vf-royal); box-shadow: 0 0 0 .2rem rgba(22, 141, 255, .16); }
    :is(.resubmit-page, .reschedule-page, .disconnection-screen) .btn-primary { background: linear-gradient(120deg,var(--vf-navy),var(--vf-royal)); border-color: var(--vf-royal); color: #fff; }
    @media (max-width: 575.98px) { :is(.resubmit-page, .reschedule-page, .disconnection-screen) { padding: 1rem; } :is(.resubmit-shell, .reschedule-card) { padding: 1.25rem; } }
}
</style>
