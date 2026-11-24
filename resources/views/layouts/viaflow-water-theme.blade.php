@auth
<style>
    .app-shell:not(.admin-dashboard) { --water-dark: var(--vf-navy); --water-teal: var(--vf-royal); --water-aqua: var(--vf-bright); --water-heading: var(--vf-navy); --water-muted: var(--vf-navy); }
    .app-shell:not(.admin-dashboard) .sidebar { background: linear-gradient(165deg, var(--vf-navy) 0%, var(--vf-navy) 52%, var(--vf-royal) 100%) !important; isolation: isolate; }
    .app-shell:not(.admin-dashboard) .sidebar::after { content: ''; position: absolute; z-index: -1; pointer-events: none; left: -55%; right: -45%; bottom: -28px; height: 125px; border-radius: 50% 48% 0 0; background: linear-gradient(165deg, rgba(22, 141, 255, .16) 0 35%, rgba(22, 141, 255, .24) 36% 68%, rgba(22, 141, 255, .13) 69%); }
    .app-shell:not(.admin-dashboard) .sidebar :is(.nav-link, .sidebar-brand, .sidebar-nav-toggle), .app-shell:not(.admin-dashboard) .sidebar .nav-link i { color: #fff !important; }
    .app-shell:not(.admin-dashboard) .sidebar :is(.nav-link, .sidebar-nav-row) { transition: background .2s ease, transform .2s ease, box-shadow .2s ease; }
    .app-shell:not(.admin-dashboard) .sidebar :is(.nav-link:hover, .sidebar-nav-row:hover) { background: rgba(22, 141, 255, .18); }
    .app-shell:not(.admin-dashboard) .sidebar :is(.nav-link.active, .sidebar-nav-row.active) { background: linear-gradient(110deg, var(--vf-royal), var(--vf-bright)) !important; box-shadow: 0 5px 14px rgba(6, 43, 128, .2); }
    .app-shell:not(.admin-dashboard) .sidebar-nav-row:is(.active, :hover) > .nav-link { background: transparent !important; box-shadow: none; }
    .app-shell:not(.admin-dashboard) .main-content { min-width: 0; background: linear-gradient(150deg, var(--vf-background) 0%, var(--vf-background) 55%, #f6fdff 100%); }
    .app-shell:not(.admin-dashboard) .content-wrapper { position: relative; isolation: isolate; animation: viaflow-water-entrance .4s ease-out both; }
    .app-shell:not(.admin-dashboard) .content-wrapper::before { content: ''; position: absolute; z-index: -1; top: 0; right: 0; width: min(38vw, 460px); height: 150px; pointer-events: none; background: radial-gradient(ellipse at 100% -30%, rgba(22, 141, 255, .15) 0 35%, transparent 36%), radial-gradient(ellipse at 100% -15%, rgba(7, 84, 200, .08) 0 48%, transparent 49%); }
    .app-shell:not(.admin-dashboard) :is(.page-heading h1, .panel-card h1, .panel-card h2, .panel-card h3, .dashboard-card h2, .dashboard-card h3, .announcement-card h2, .stat-value, .metric-value) { color: var(--vf-navy); }
    .app-shell:not(.admin-dashboard) :is(.page-heading p, .text-secondary, .form-text) { color: var(--vf-navy) !important; }
    .app-shell:not(.admin-dashboard) :is(.panel-card, .dashboard-card, .announcement-card, .billing-card, .dash-kpi) { min-width: 0; background: linear-gradient(145deg, #fff, #f7fdff) !important; border: 1px solid var(--vf-light) !important; border-radius: 16px !important; box-shadow: 0 8px 24px rgba(7, 84, 200, .10) !important; transition: box-shadow .2s ease, border-color .2s ease; }
    .app-shell:not(.admin-dashboard) :is(.dashboard-card, .announcement-card, .dash-kpi):hover { border-color: var(--vf-bright) !important; box-shadow: 0 13px 28px rgba(7, 84, 200, .16) !important; }
    .app-shell:not(.admin-dashboard) :is(.dashboard-card, .panel-card) :is(.feature-icon, .metric-icon) { background: linear-gradient(135deg, var(--vf-royal), var(--vf-bright)) !important; color: #fff !important; }
    .app-shell:not(.admin-dashboard) :is(.table, table) { --bs-table-bg: transparent; --bs-table-border-color: var(--vf-light); color: var(--vf-navy); }
    .app-shell:not(.admin-dashboard) :is(.table, table) thead th { background: var(--vf-background); color: var(--vf-navy); border-color: var(--vf-light); }
    .app-shell:not(.admin-dashboard) :is(.form-control, .form-select), body:has(.app-shell:not(.admin-dashboard)) .modal :is(.form-control, .form-select) { border-color: var(--vf-light); border-radius: .65rem; transition: border-color .2s ease, box-shadow .2s ease; }
    .app-shell:not(.admin-dashboard) :is(.form-control, .form-select):focus, body:has(.app-shell:not(.admin-dashboard)) .modal :is(.form-control, .form-select):focus { border-color: var(--vf-royal) !important; box-shadow: 0 0 0 .2rem rgba(22, 141, 255, .16) !important; }
    .app-shell:not(.admin-dashboard) .btn-primary, body:has(.app-shell:not(.admin-dashboard)) .modal .btn-primary { background: linear-gradient(120deg, var(--vf-navy), var(--vf-royal)) !important; border-color: var(--vf-royal) !important; color: #fff !important; }
    .app-shell:not(.admin-dashboard) .btn, body:has(.app-shell:not(.admin-dashboard)) .modal .btn { transition: background-color .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .app-shell:not(.admin-dashboard) .btn:hover, body:has(.app-shell:not(.admin-dashboard)) .modal .btn:hover { box-shadow: 0 6px 14px rgba(6, 43, 128, .15); }
    body:has(.app-shell:not(.admin-dashboard)) .modal-content { border: 1px solid var(--vf-light) !important; border-radius: 16px !important; box-shadow: 0 16px 42px rgba(6, 43, 128, .18) !important; }
    body:has(.app-shell:not(.admin-dashboard)) .modal-title { color: var(--vf-navy); }
    .app-shell:not(.admin-dashboard) .page-link { color: var(--vf-navy); border-color: var(--vf-light); }
    .app-shell:not(.admin-dashboard) .page-item.active .page-link { color: #fff; background: var(--vf-royal); border-color: var(--vf-royal); }
    @keyframes viaflow-water-entrance { from { opacity: .98; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 575.98px) { .app-shell:not(.admin-dashboard) .content-wrapper::before { width: 45%; height: 90px; } }
    @media (prefers-reduced-motion: reduce) { .app-shell:not(.admin-dashboard) .content-wrapper { animation: none !important; } .app-shell:not(.admin-dashboard) :is(.panel-card, .dashboard-card, .announcement-card, .sidebar .nav-link, .sidebar-nav-row, .btn, .form-control, .form-select) { transition: none !important; } }
</style>
@endauth
