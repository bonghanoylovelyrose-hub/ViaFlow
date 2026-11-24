<style>
    .admin-dashboard { --dashboard-dark: var(--vf-navy); --dashboard-teal: var(--vf-royal); --dashboard-aqua: var(--vf-bright); --dashboard-pale: var(--vf-background); --dashboard-heading: var(--vf-navy); --dashboard-muted: var(--vf-navy); }
    .admin-dashboard .sidebar { background: linear-gradient(165deg, var(--vf-navy) 0%, var(--vf-navy) 52%, var(--vf-royal) 100%) !important; isolation: isolate; }
    .admin-dashboard .sidebar::after { content: ''; position: absolute; z-index: -1; pointer-events: none; left: -55%; right: -45%; bottom: -28px; height: 125px; border-radius: 50% 48% 0 0; background: linear-gradient(165deg, rgba(22, 141, 255, .16) 0 35%, rgba(22, 141, 255, .24) 36% 68%, rgba(22, 141, 255, .13) 69%); }
    .admin-dashboard .sidebar :is(.nav-link, .sidebar-brand, .sidebar-nav-toggle) { color: #fff !important; }
    .admin-dashboard .sidebar .nav-link i { color: #fff !important; }
    .admin-dashboard .sidebar .nav-link, .admin-dashboard .sidebar-nav-row { transition: background .2s ease, transform .2s ease, box-shadow .2s ease; }
    .admin-dashboard .sidebar .nav-link:hover, .admin-dashboard .sidebar-nav-row:hover { background: rgba(22, 141, 255, .18); }
    .admin-dashboard .sidebar .nav-link.active, .admin-dashboard .sidebar-nav-row.active { background: linear-gradient(110deg, var(--vf-royal), var(--vf-bright)) !important; box-shadow: 0 5px 14px rgba(6, 43, 128, .2); }
    .admin-dashboard .sidebar-nav-row.active > .nav-link, .admin-dashboard .sidebar-nav-row:hover > .nav-link { background: transparent !important; box-shadow: none; }
    .admin-dashboard .main-content { min-width: 0; background: linear-gradient(150deg, var(--vf-background) 0%, var(--vf-background) 55%, #f6fdff 100%); }
    .admin-dashboard .content-wrapper { position: relative; isolation: isolate; animation: dashboard-water-entrance .4s ease-out both; }
    .admin-dashboard .content-wrapper::before { content: ''; position: absolute; z-index: -1; top: 0; right: 0; width: min(38vw, 460px); height: 150px; pointer-events: none; background: radial-gradient(ellipse at 100% -30%, rgba(22, 141, 255, .15) 0 35%, transparent 36%), radial-gradient(ellipse at 100% -15%, rgba(7, 84, 200, .08) 0 48%, transparent 49%); }
    .admin-dashboard :is(.page-heading h1, .dash-panel-title, .dash-kpi-value, .dash-summary-value, .dash-list-item-title, .dash-queue-title) { color: var(--vf-navy) !important; }
    .admin-dashboard :is(.page-heading p, .dash-kpi-label, .dash-kpi-note, .dash-panel-subtitle, .dash-summary-label, .dash-note, .dash-list-item-meta, .dash-queue-description, .dash-status-legend-item) { color: var(--vf-navy) !important; }
    .admin-dashboard .dash-kpi, .admin-dashboard .dash-panel { min-width: 0; background: linear-gradient(145deg, #fff, #f7fdff) !important; border: 1px solid var(--vf-light) !important; border-radius: 16px !important; box-shadow: 0 8px 24px rgba(7, 84, 200, .10) !important; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .admin-dashboard .dash-kpi:hover, .admin-dashboard .dash-panel:hover { transform: translateY(-3px); border-color: var(--vf-bright) !important; box-shadow: 0 13px 28px rgba(7, 84, 200, .16) !important; }
    .admin-dashboard .dash-kpi-icon { background: linear-gradient(135deg, var(--vf-royal), var(--vf-bright)) !important; color: #fff !important; box-shadow: 0 5px 12px rgba(7, 84, 200, .16); }
    .admin-dashboard .dash-kpi-value, .admin-dashboard .dash-kpi-label, .admin-dashboard .dash-kpi-note { overflow-wrap: anywhere; }
    .admin-dashboard .dash-summary-item { background: var(--vf-background) !important; border: 1px solid var(--vf-light) !important; border-radius: .8rem; }
    .admin-dashboard .dash-panel-link, .admin-dashboard .dash-queue-action { color: var(--vf-navy); }
    .admin-dashboard .content-wrapper .btn { transition: transform .2s ease, box-shadow .2s ease; }
    .admin-dashboard .content-wrapper .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(6, 43, 128, .15); }
    .admin-dashboard .dash-visual canvas { max-width: 100%; }
    @keyframes dashboard-water-entrance { from { opacity: .98; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 575.98px) { .admin-dashboard .dash-panel-head { flex-wrap: wrap; } .admin-dashboard .content-wrapper::before { width: 45%; height: 90px; } }
    @media (prefers-reduced-motion: reduce) { .admin-dashboard .content-wrapper { animation: none !important; } .admin-dashboard :is(.dash-kpi, .dash-panel, .sidebar .nav-link, .sidebar-nav-row, .content-wrapper .btn) { transition: none !important; } .admin-dashboard :is(.dash-kpi, .dash-panel, .content-wrapper .btn):hover { transform: none; } }
</style>
