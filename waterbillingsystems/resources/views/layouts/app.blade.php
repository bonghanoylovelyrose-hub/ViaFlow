<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ViaFlow System')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --wb-primary: #0f6e8c;
            --wb-text: #1f2a37;
            --wb-surface: #ffffff;
            --wb-primary-dark: var(--wb-primary);
            --wb-accent: var(--wb-primary);
            --wb-bg: var(--wb-surface);
            --wb-muted: color-mix(in srgb, var(--wb-text) 62%, var(--wb-surface));
            --wb-border: color-mix(in srgb, var(--wb-primary) 18%, var(--wb-surface));
            --wb-danger: var(--wb-primary);
            --wb-warning: var(--wb-primary);
            --wb-success: var(--wb-primary);
            --wb-info: var(--wb-primary);
            --sidebar-width: 244px;
        }

        html {
            scrollbar-gutter: stable;
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: color-mix(in srgb, var(--wb-primary) 5%, var(--wb-surface));
            color: var(--wb-text);
            overflow-y: scroll;
            overflow-x: hidden;
        }

        .auth-wrapper { min-height: 100vh; }
        .auth-card, .dashboard-card, .panel-card, .announcement-card {
            background: var(--wb-surface);
            border: 1px solid var(--wb-border);
            border-radius: 1rem;
            box-shadow: none;
        }

        .app-shell {
            min-height: 100vh;
            overflow-x: clip;
        }

        .sidebar {
            flex: 0 0 var(--sidebar-width);
            width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--wb-primary);
            color: var(--wb-surface);
        }

        .sidebar-brand { color: var(--wb-surface); text-decoration: none; }
        .sidebar .nav-link {
            color: color-mix(in srgb, var(--wb-surface) 78%, var(--wb-primary));
            border-radius: 0.8rem;
            padding: 0.68rem 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--wb-surface);
            background: color-mix(in srgb, var(--wb-surface) 14%, var(--wb-primary));
            transform: translateX(2px);
        }

        .sidebar .feature-icon,
        .sidebar .nav-link i {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            color: var(--wb-surface) !important;
        }

        .sidebar-footer {
            background: color-mix(in srgb, var(--wb-surface) 10%, var(--wb-primary));
            border-radius: 0.9rem;
        }

        .main-content {
            flex: 1 1 auto;
            min-width: 0;
        }
        .topbar {
            background: var(--wb-surface);
            border-bottom: 1px solid var(--wb-border);
        }

        .topbar .container-fluid {
            gap: 0.75rem;
            min-width: 0;
        }

        .content-wrapper { padding: 1.5rem; }
        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .page-heading h1 {
            font-size: 1.85rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
        }

        .page-heading p { color: var(--wb-muted); margin-bottom: 0; }
        .metric-icon, .feature-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .metric-icon { background: color-mix(in srgb, var(--wb-primary) 10%, var(--wb-surface)); color: var(--wb-primary); }
        .feature-icon { background: color-mix(in srgb, var(--wb-primary) 10%, var(--wb-surface)); color: var(--wb-primary); }
        .section-title { font-size: 1rem; font-weight: 800; margin-bottom: 1rem; }
        .table thead th {
            color: var(--wb-muted);
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0;
            border-bottom-width: 1px;
        }

        .table td, .table th { vertical-align: middle; }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        img,
        svg,
        canvas,
        iframe {
            max-width: 100%;
        }

        .row > * {
            min-width: 0;
        }

        .panel-card,
        .dashboard-card,
        .announcement-card,
        .modal-content,
        .card {
            min-width: 0;
        }
        .badge-soft-primary,
        .badge-soft-success,
        .badge-soft-warning,
        .badge-soft-danger,
        .badge-soft-info {
            background: color-mix(in srgb, var(--wb-primary) 9%, var(--wb-surface));
            border: 1px solid color-mix(in srgb, var(--wb-primary) 20%, var(--wb-surface));
            color: var(--wb-primary);
            font-weight: 800;
        }

        .chart-bars { display: flex; align-items: end; gap: 0.75rem; height: 220px; }
        .chart-bar {
            flex: 1;
            border-radius: 1rem 1rem 0.45rem 0.45rem;
            background: var(--wb-primary);
            position: relative;
        }

        .chart-label {
            display: block;
            margin-top: 0.65rem;
            text-align: center;
            color: var(--wb-muted);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-dot {
            width: 0.65rem;
            height: 0.65rem;
            border-radius: 50%;
            display: inline-block;
        }

        .timeline-item {
            display: flex;
            gap: 0.9rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px dashed var(--wb-border);
        }

        .timeline-item:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
        .danger-strip { border-left: 4px solid var(--wb-primary); }
        .avatar-badge {
            width: 2.2rem;
            height: 2.2rem;
        }

        .chart-62 { height: 62%; }
        .chart-68 { height: 68%; }
        .chart-74 { height: 74%; }
        .chart-84 { height: 84%; }
        .chart-88 { height: 88%; }
        .chart-95 { height: 95%; }

        .notification-menu {
            width: 340px;
            max-width: min(340px, calc(100vw - 2rem));
        }

        .notification-item,
        .notification-item-body,
        .notification-item-title,
        .notification-item-message {
            min-width: 0;
            max-width: 100%;
        }

        .notification-item-title,
        .notification-item-message {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .rag-indicator { white-space: nowrap; }
        .rag-dot { width: 0.65rem; height: 0.65rem; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .rag-dot-sm { width: 0.45rem; height: 0.45rem; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .rag-dot-lg { width: 0.85rem; height: 0.85rem; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .rag-green { background: #22c55e; }
        .rag-amber { background: #f59e0b; }
        .rag-red { background: #ef4444; }
        .rag-orange { background: #f97316; }
        .rag-yellow { background: #eab308; }
        .rag-blue { background: #3b82f6; }
        .rag-green-text { color: #16a34a; font-weight: 700; }
        .rag-amber-text { color: #d97706; font-weight: 700; }
        .rag-red-text { color: #dc2626; font-weight: 700; }
        .rag-orange-text { color: #ea580c; font-weight: 700; }
        .rag-yellow-text { color: #ca8a04; font-weight: 700; }
        .rag-blue-text { color: #2563eb; font-weight: 700; }

        @media (max-width: 991.98px) {
            .app-shell {
                display: block !important;
            }

            .sidebar {
                flex: 0 0 auto;
                width: 100%;
                max-width: 100%;
                min-height: auto;
            }

            .content-wrapper { padding: 1rem; }
            .page-heading {
                flex-direction: column;
                align-items: stretch;
            }

            .page-heading > * {
                max-width: 100%;
            }

            .topbar .container-fluid {
                flex-wrap: wrap;
            }
        }
    </style>
    <style>
        :root {
            --bs-primary: var(--wb-primary);
            --bs-secondary: var(--wb-text);
            --bs-success: var(--wb-primary);
            --bs-info: var(--wb-primary);
            --bs-warning: var(--wb-primary);
            --bs-danger: var(--wb-primary);
            --bs-light: var(--wb-surface);
            --bs-dark: var(--wb-text);
            --bs-body-color: var(--wb-text);
            --bs-body-bg: var(--wb-surface);
            --bs-border-color: var(--wb-border);
            --bs-link-color: var(--wb-primary);
            --bs-link-hover-color: var(--wb-primary);
        }

        body,
        .main-content,
        .content-wrapper {
            color: var(--wb-text);
        }

        body * {
            letter-spacing: 0;
        }

        .page-heading {
            align-items: end;
        }

        .page-heading h1 {
            color: var(--wb-text);
            font-size: 1.85rem;
            line-height: 1.15;
        }

        .page-heading p,
        .text-secondary,
        .text-muted,
        .form-text,
        .small.text-secondary {
            color: var(--wb-muted) !important;
        }

        .panel-card,
        .dashboard-card,
        .auth-card,
        .announcement-card,
        .modal-content,
        .card,
        [class*="summary-card"],
        [class*="stat-card"],
        [class*="focus-card"],
        [class*="preview-card"],
        [class*="qr-card"],
        [class*="table-card"],
        [class*="filter-card"],
        [class*="indicator"],
        [class*="open-item"],
        [class*="history-item"],
        .role-register-section,
        [class*="role-card"] {
            background: var(--wb-surface) !important;
            border: 1px solid var(--wb-border) !important;
            border-radius: 1rem !important;
            box-shadow: none !important;
            color: var(--wb-text);
        }

        [class*="dropzone"],
        [class*="upload"],
        [class*="empty-state"],
        [class*="workspace"],
        [class*="summary-row"],
        [class*="toolbar"],
        [class*="topline"],
        [class*="meter-phone-frame"],
        [class*="receipt-phone-frame"],
        [class*="org-qr-box"],
        [class*="password-indicator"],
        [class*="chart"] {
            border-color: var(--wb-border) !important;
            background: color-mix(in srgb, var(--wb-primary) 5%, var(--wb-surface)) !important;
            color: var(--wb-text) !important;
            box-shadow: none !important;
        }

        [class*="icon"],
        [class*="avatar"],
        .status-dot {
            background: color-mix(in srgb, var(--wb-primary) 10%, var(--wb-surface)) !important;
            color: var(--wb-primary) !important;
            border-color: var(--wb-border) !important;
        }

        .btn,
        button.btn,
        a.btn {
            min-height: 2.6rem;
            border-radius: 0.8rem !important;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }

        .btn-sm {
            min-height: 2.25rem;
            border-radius: 0.7rem !important;
        }

        .btn-primary,
        .btn-success,
        .btn-danger,
        .btn-warning,
        .btn-info {
            background: var(--wb-primary) !important;
            border-color: var(--wb-primary) !important;
            color: var(--wb-surface) !important;
        }

        .btn-outline-primary,
        .btn-outline-secondary,
        .btn-outline-success,
        .btn-outline-danger,
        .btn-outline-warning,
        .btn-outline-info,
        .btn-light,
        .btn-close,
        .minimal-link {
            border-color: var(--wb-border) !important;
            color: var(--wb-primary) !important;
        }

        .btn-outline-primary,
        .btn-outline-secondary,
        .btn-outline-success,
        .btn-outline-danger,
        .btn-outline-warning,
        .btn-outline-info,
        .btn-light {
            background: var(--wb-surface) !important;
        }

        .btn:hover,
        .minimal-link:hover {
            filter: none;
            opacity: 0.92;
        }

        .form-control,
        .form-select,
        .input-group-text,
        textarea {
            min-height: 2.7rem;
            border-color: var(--wb-border) !important;
            border-radius: 0.8rem !important;
            color: var(--wb-text) !important;
            background-color: var(--wb-surface) !important;
            font-weight: 600;
        }

        .input-group .form-control,
        .input-group .form-select,
        .input-group .input-group-text {
            border-radius: 0.8rem !important;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: var(--wb-primary) !important;
            box-shadow: none !important;
        }

        .alert,
        .alert-success,
        .alert-danger,
        .alert-warning,
        .alert-info {
            background: var(--wb-surface) !important;
            border: 1px solid var(--wb-border) !important;
            border-radius: 0.9rem !important;
            color: var(--wb-text) !important;
            font-weight: 700;
        }

        .alert-success {
            background: color-mix(in srgb, #16a34a 11%, var(--wb-surface)) !important;
            border-color: color-mix(in srgb, #16a34a 34%, var(--wb-surface)) !important;
            color: #166534 !important;
        }

        .alert-success i {
            color: #16a34a !important;
        }

        .alert-danger {
            background: color-mix(in srgb, #ef4444 10%, var(--wb-surface)) !important;
            border-color: color-mix(in srgb, #ef4444 34%, var(--wb-surface)) !important;
            color: #991b1b !important;
        }

        .alert-danger i {
            color: #dc2626 !important;
        }

        .badge,
        .badge-soft-primary,
        .badge-soft-success,
        .badge-soft-warning,
        .badge-soft-danger,
        .badge-soft-info {
            background: color-mix(in srgb, var(--wb-primary) 9%, var(--wb-surface)) !important;
            border: 1px solid var(--wb-border) !important;
            color: var(--wb-primary) !important;
            border-radius: 999px;
            font-weight: 800;
        }

        .badge-soft-success {
            background: color-mix(in srgb, #16a34a 12%, var(--wb-surface)) !important;
            border-color: color-mix(in srgb, #16a34a 32%, var(--wb-surface)) !important;
            color: #15803d !important;
        }

        .badge-soft-warning {
            background: color-mix(in srgb, #f59e0b 14%, var(--wb-surface)) !important;
            border-color: color-mix(in srgb, #f59e0b 36%, var(--wb-surface)) !important;
            color: #b45309 !important;
        }

        .badge-soft-danger {
            background: color-mix(in srgb, #ef4444 12%, var(--wb-surface)) !important;
            border-color: color-mix(in srgb, #ef4444 34%, var(--wb-surface)) !important;
            color: #dc2626 !important;
        }

        .badge-soft-info {
            background: color-mix(in srgb, #3b82f6 12%, var(--wb-surface)) !important;
            border-color: color-mix(in srgb, #3b82f6 32%, var(--wb-surface)) !important;
            color: #2563eb !important;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            line-height: 1;
        }

        .status-badge::before {
            content: "";
            width: 0.48rem;
            height: 0.48rem;
            border-radius: 50%;
            background: currentColor;
            flex: 0 0 auto;
        }

        .table {
            color: var(--wb-text);
        }

        .table thead th {
            background: color-mix(in srgb, var(--wb-primary) 5%, var(--wb-surface)) !important;
            border-color: var(--wb-border) !important;
            color: var(--wb-muted) !important;
            font-weight: 800;
        }

        .table tbody td,
        .table tbody th {
            border-color: var(--wb-border) !important;
        }

        .text-danger,
        .text-warning,
        .text-warning-emphasis,
        .text-success,
        .text-info,
        .text-primary {
            color: var(--wb-primary) !important;
        }

        .bg-white,
        .bg-light,
        .dropdown-menu,
        .list-group-item {
            background-color: var(--wb-surface) !important;
        }

        .bg-primary,
        .bg-secondary,
        .bg-success,
        .bg-danger,
        .bg-warning,
        .bg-info,
        .bg-dark,
        .bg-body-tertiary {
            background-color: var(--wb-primary) !important;
            color: var(--wb-surface) !important;
        }

        .table-primary,
        .table-secondary,
        .table-success,
        .table-danger,
        .table-warning,
        .table-info,
        .table-light {
            --bs-table-bg: color-mix(in srgb, var(--wb-primary) 5%, var(--wb-surface));
            --bs-table-color: var(--wb-text);
            --bs-table-border-color: var(--wb-border);
        }

        .progress,
        .progress-stacked {
            background: color-mix(in srgb, var(--wb-primary) 8%, var(--wb-surface)) !important;
        }

        .progress-bar,
        .password-indicator-fill,
        .password-indicator-fill.is-fair,
        .password-indicator-fill.is-good,
        .password-indicator-fill.is-strong {
            background: var(--wb-primary) !important;
            color: var(--wb-surface) !important;
        }

        .link-primary,
        .link-secondary,
        .link-success,
        .link-danger,
        .link-warning,
        .link-info,
        a {
            color: var(--wb-primary);
        }

        .border-primary,
        .border-secondary,
        .border-success,
        .border-danger,
        .border-warning,
        .border-info {
            border-color: var(--wb-border) !important;
        }

        .btn,
        .badge,
        .alert,
        .form-control,
        .form-select,
        .input-group-text,
        .modal-content,
        .dropdown-menu,
        .list-group-item,
        .table,
        .card,
        .panel-card,
        .dashboard-card,
        .auth-card,
        .announcement-card {
            --bs-primary-rgb: 15, 110, 140;
            --bs-secondary-rgb: 31, 42, 55;
            --bs-success-rgb: 15, 110, 140;
            --bs-info-rgb: 15, 110, 140;
            --bs-warning-rgb: 15, 110, 140;
            --bs-danger-rgb: 15, 110, 140;
            --bs-light-rgb: 255, 255, 255;
            --bs-dark-rgb: 31, 42, 55;
        }

        .border,
        .border-top,
        .border-bottom,
        .border-start,
        .border-end {
            border-color: var(--wb-border) !important;
        }

        .modal-header,
        .modal-footer {
            border-color: var(--wb-border) !important;
        }

        .pagination {
            gap: 0.3rem;
        }

        .page-link {
            border-color: var(--wb-border) !important;
            color: var(--wb-primary) !important;
            border-radius: 0.7rem !important;
            font-weight: 800;
        }

        .active > .page-link,
        .page-link.active {
            background: var(--wb-primary) !important;
            border-color: var(--wb-primary) !important;
            color: var(--wb-surface) !important;
        }

        .role-select-page,
        .role-login-page,
        .role-register-page,
        .register-step-page,
        .auth-wrapper {
            position: relative;
            isolation: isolate;
            background: linear-gradient(135deg, rgba(6, 37, 49, 0.52), rgba(15, 110, 140, 0.18)) !important;
            overflow: hidden;
        }

        .role-select-page::before,
        .role-login-page::before,
        .role-register-page::before,
        .register-step-page::before,
        .auth-wrapper::before {
            content: "";
            position: fixed;
            inset: -0.75rem;
            z-index: -1;
            background: url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
            filter: blur(2px);
            transform: scale(1.02);
        }

        .role-select-shell,
        .role-login-shell,
        .role-register-shell,
        .register-step-shell {
            background: var(--wb-surface) !important;
            border: 1px solid var(--wb-border) !important;
            border-radius: 1rem !important;
            box-shadow: none !important;
        }

        .role-select-visual,
        .role-login-visual {
            background: var(--wb-primary) !important;
        }

        .role-select-visual::before,
        .role-login-visual::before {
            background: color-mix(in srgb, var(--wb-primary) 88%, var(--wb-text)) !important;
            backdrop-filter: none !important;
        }

        .role-select-visual-copy,
        .role-login-visual-copy,
        .role-select-visual-copy p,
        .role-login-visual-copy p {
            color: var(--wb-surface) !important;
        }

        .role-select-visual-logo,
        .role-login-visual-logo {
            background: color-mix(in srgb, var(--wb-surface) 12%, var(--wb-primary)) !important;
            border: 1px solid color-mix(in srgb, var(--wb-surface) 24%, var(--wb-primary)) !important;
            box-shadow: none !important;
            color: var(--wb-surface) !important;
        }

        .role-select-panel,
        .role-login-panel,
        .role-register-panel {
            background: var(--wb-surface) !important;
        }

        .role-select-card h2,
        .role-login-card h2,
        .role-register-card h1,
        .register-step-head h1,
        .role-register-section-title,
        .register-section-title,
        .register-step-title,
        .role-register-progress-title,
        .role-select-label,
        .role-login-label,
        .role-register-label {
            color: var(--wb-text) !important;
        }

        .role-select-subtitle,
        .role-login-subtitle,
        .role-login-remember,
        .role-register-subtitle,
        .role-register-section-copy,
        .register-step-copy,
        .register-section-copy,
        .register-step-meta {
            color: var(--wb-muted) !important;
        }

        .role-select-primary,
        .role-login-primary,
        .role-register-primary,
        .register-step-primary {
            background: var(--wb-primary) !important;
            border: 1px solid var(--wb-primary) !important;
            color: var(--wb-surface) !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .role-register-secondary {
            background: var(--wb-surface) !important;
            border: 1px solid var(--wb-border) !important;
            color: var(--wb-primary) !important;
        }

        .role-select-input,
        .role-login-input,
        .role-login-password,
        .role-login-toggle,
        .role-register-input,
        .role-register-select,
        .role-register-textarea {
            border-color: var(--wb-border) !important;
            background: var(--wb-surface) !important;
            color: var(--wb-text) !important;
            box-shadow: none !important;
        }

        .role-select-input:focus,
        .role-login-input:focus,
        .role-login-password:focus,
        .role-register-input:focus,
        .role-register-select:focus,
        .role-register-textarea:focus {
            border-color: var(--wb-primary) !important;
            box-shadow: none !important;
        }

        .role-login-badge,
        .role-register-step-pill,
        .role-register-badge,
        .register-step-pill {
            background: color-mix(in srgb, var(--wb-primary) 9%, var(--wb-surface)) !important;
            border: 1px solid var(--wb-border) !important;
            color: var(--wb-primary) !important;
        }

        .role-login-change,
        .role-login-footer-link,
        .role-register-change,
        .role-register-footer-link {
            color: var(--wb-primary) !important;
        }

        .role-register-section,
        .register-section,
        .register-step-card,
        .register-choice-card {
            background: var(--wb-surface) !important;
            border: 1px solid var(--wb-border) !important;
            border-radius: 0.9rem !important;
            box-shadow: none !important;
            color: var(--wb-text) !important;
        }

        .role-register-section.is-borderless {
            border: 0 !important;
            background: var(--wb-surface) !important;
            padding-inline: 0 !important;
        }

        .register-step-option:checked + .register-step-card,
        .register-choice-input:checked + .register-choice-card {
            border-color: var(--wb-primary) !important;
            box-shadow: none !important;
            background: color-mix(in srgb, var(--wb-primary) 6%, var(--wb-surface)) !important;
        }

        .role-register-progress::before {
            background: var(--wb-border) !important;
        }

        .role-register-progress-fill,
        .role-register-progress-item.is-active .role-register-progress-marker,
        .role-register-progress-item.is-complete .role-register-progress-marker {
            background: var(--wb-primary) !important;
            border-color: var(--wb-primary) !important;
            color: var(--wb-surface) !important;
            box-shadow: none !important;
        }

        .role-register-progress-marker {
            background: var(--wb-surface) !important;
            border-color: var(--wb-border) !important;
            color: var(--wb-muted) !important;
        }

        @media (max-width: 767.98px) {
            html {
                font-size: 15px;
            }

            .content-wrapper {
                padding: 0.85rem;
            }

            .page-heading h1 {
                font-size: 1.55rem;
            }

            .page-heading .btn,
            .page-heading button.btn,
            .page-heading a.btn {
                width: 100%;
            }

            .topbar {
                padding-inline: 0.75rem !important;
            }

            .topbar .container-fluid > div:first-of-type {
                min-width: 0;
                flex: 1 1 0;
            }

            .topbar .container-fluid > .d-flex.ms-auto {
                width: 100%;
                justify-content: space-between;
                gap: 0.5rem !important;
                margin-left: 0 !important;
            }

            .topbar .dropdown {
                min-width: 0;
            }

            .topbar .dropdown > .btn {
                max-width: 100%;
            }

            .btn,
            button.btn,
            a.btn {
                white-space: normal;
            }

            .panel-card,
            .dashboard-card,
            .announcement-card,
            .modal-content,
            .card {
                border-radius: 0.9rem !important;
            }

            .table {
                min-width: 640px;
            }
        }

        @media (max-width: 575.98px) {
            .content-wrapper {
                padding: 0.75rem;
            }

            .topbar .container-fluid,
            .topbar .row,
            .topbar .col,
            .topbar .d-flex {
                min-width: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    @yield('body')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
