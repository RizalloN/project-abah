<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title>@hasSection('title')@yield('title') - @endif{{ config('app.name', 'Dashboard A-Six') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/a-six-logo.svg') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
    @yield('styles')

    <style>
        :root {
            --bri-nusantara: #0857c3;
            --bri-cakrawala: #307fe2;
            --bri-mentari: #71c5e8;
            --bri-ink: #053b82;
            --bri-night: #042a5f;
            --bri-mist: #f2f7ff;
            --bri-white: #ffffff;
            --app-safe-top: env(safe-area-inset-top, 0px);
            --app-safe-right: env(safe-area-inset-right, 0px);
            --app-safe-bottom: env(safe-area-inset-bottom, 0px);
            --app-safe-left: env(safe-area-inset-left, 0px);
            --app-page-gutter: clamp(0.75rem, 1.6vw, 1.5rem);
            --app-control-height: 38px;
        }

        body {
            font-family: "Inter", "Segoe UI", sans-serif;
            color: #0f172a;
            background: var(--bri-mist);
        }

        .main-header.modern-navbar {
            min-height: 64px;
            background: #ffffff !important;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(8, 87, 195, 0.08) !important;
            box-shadow: 0 10px 30px rgba(8, 87, 195, 0.04);
            padding: 0.58rem 1rem;
        }

        .modern-navbar .nav-link {
            color: #334155 !important;
        }

        .modern-navbar .menu-toggle {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.22s ease;
        }

        .modern-navbar .menu-toggle:hover {
            background: #f1f5f9;
            color: var(--bri-nusantara) !important;
            transform: translateY(-1px);
            border-color: var(--bri-cakrawala);
        }

        .modern-user-trigger {
            min-height: 48px;
            border-radius: 14px;
            padding: 0.35rem 0.72rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.22s ease;
        }

        .modern-user-trigger:hover {
            background: #f1f5f9;
            color: var(--bri-nusantara) !important;
            border-color: var(--bri-cakrawala);
            transform: translateY(-1px);
        }

        .modern-user-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bri-cakrawala), var(--bri-mentari));
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
            border: 2px solid rgba(255, 255, 255, 0.24);
            box-shadow: 0 4px 12px rgba(48, 127, 226, 0.2);
        }

        .modern-navbar-brand {
            display: inline-flex;
            align-items: center;
            min-width: 0;
            margin-left: 0.72rem;
            padding-left: 0.88rem;
            border-left: 1px solid #e2e8f0;
        }

        .modern-navbar-brand .title {
            display: block;
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: 0.01em;
        }

        .modern-navbar-brand .subtitle {
            display: block;
            color: var(--bri-nusantara);
            font-size: 0.62rem;
            font-weight: 750;
            line-height: 1.1;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .modern-user-menu {
            min-width: 220px;
            border: 1px solid rgba(8, 87, 195, 0.1);
            border-radius: 16px;
            padding: 0.5rem;
            box-shadow: 0 10px 30px rgba(8, 87, 195, 0.08);
        }

        .modern-user-menu .dropdown-item {
            border-radius: 12px;
            font-weight: 600;
            color: #dc2626;
            padding-top: 0.7rem;
            padding-bottom: 0.7rem;
        }

        .modern-user-menu .dropdown-item:hover {
            background: #fef2f2;
            color: #b91c1c;
        }

        .modern-user-name {
            font-size: 0.9rem;
            line-height: 1.05;
            color: #0f172a;
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .modern-user-caption {
            font-size: 0.62rem;
            letter-spacing: 0.15em;
            color: var(--bri-nusantara);
        }

        .user-branch-scope-lock {
            border-color: #cbd5e1 !important;
            background-color: #f1f5f9 !important;
            color: #334155 !important;
            cursor: not-allowed !important;
            opacity: 1 !important;
        }

        button.user-branch-scope-lock .fa-chevron-down,
        button.user-branch-scope-lock .dropdown-toggle-icon {
            display: none !important;
        }

        .modern-navbar .dropdown-toggle::after {
            display: none;
        }

        @media (max-width: 575.98px) {
            .main-header.modern-navbar {
                padding-left: 0.6rem;
                padding-right: 0.6rem;
            }

            .modern-navbar-brand {
                display: none;
            }

            .modern-user-trigger {
                padding: 0.3rem;
            }

            .modern-user-badge {
                margin-right: 0 !important;
            }
        }

        .content-wrapper {
            background:
                radial-gradient(circle at 10% 4%, rgba(113, 197, 232, 0.18), transparent 32%),
                radial-gradient(circle at 95% 6%, rgba(48, 127, 226, 0.2), transparent 28%),
                linear-gradient(180deg, #f7fbff 0%, #eef5ff 100%);
            padding-top: 1.25rem;
            --report-first-col-width: 240px;
            --report-data-col-width: 96px;
            --report-th-font-size: 0.65rem;
            --report-td-font-size: 0.70rem;
            --report-th-padding: 10px 6px;
            --report-td-padding: 6px 8px;
        }

        .content {
            padding-top: 2.25rem;
            padding-bottom: 2rem;
        }

        .card {
            border: 1px solid rgba(8, 87, 195, 0.12);
            border-radius: 1rem;
            box-shadow: 0 18px 34px -28px rgba(8, 87, 195, 0.18);
            overflow: hidden;
        }

        .card-header {
            border-bottom: 1px solid rgba(8, 87, 195, 0.1);
        }

        .card-title {
            font-weight: 700;
        }

        .btn {
            border-radius: 0.8rem;
            font-weight: 700;
            padding: 0.64rem 1rem;
            box-shadow: 0 14px 24px -20px rgba(8, 87, 195, 0.3);
        }

        .btn-primary,
        .bg-primary {
            background: linear-gradient(135deg, var(--bri-nusantara), var(--bri-cakrawala));
            border-color: var(--bri-nusantara);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: linear-gradient(135deg, #0749a5, #236bcc);
            border-color: #0749a5;
        }

        .btn-success {
            background: linear-gradient(135deg, #0b4fba, #0857c3);
            border-color: #0857c3;
        }

        .btn-light,
        .badge-light {
            background: #f7fbff !important;
            color: #33547a !important;
            border: 1px solid rgba(8, 87, 195, 0.16);
        }

        .badge {
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .form-control,
        .custom-file-label,
        .select2-container--default .select2-selection--single {
            min-height: calc(2.4rem + 2px);
            border-radius: 0.8rem !important;
            border-color: #cfddf5 !important;
            box-shadow: none !important;
        }

        .form-control:focus,
        .custom-file-input:focus ~ .custom-file-label,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--bri-cakrawala) !important;
            box-shadow: 0 0 0 0.2rem rgba(48, 127, 226, 0.16) !important;
        }

        .custom-file-label {
            padding-top: 0.72rem;
            color: #56708f;
        }

        .custom-file-label::after {
            height: calc(2.4rem + 0px);
            border-radius: 0 0.8rem 0.8rem 0;
            background: #f4f8ff;
            color: #0b3b80;
            padding-top: 0.72rem;
        }

        .table {
            color: #153256;
        }

        .table thead th {
            border-bottom-width: 1px;
            border-bottom-color: rgba(8, 87, 195, 0.2);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(113, 197, 232, 0.16) !important;
        }

        .table.table-hover tbody tr.row-total > td,
        .table.table-hover tbody tr.row-total > th,
        .table.table-hover tbody tr.row-total:hover > td,
        .table.table-hover tbody tr.row-total:hover > th,
        .table.table-hover tbody tr.row-total-blue > td,
        .table.table-hover tbody tr.row-total-blue > th,
        .table.table-hover tbody tr.row-total-blue:hover > td,
        .table.table-hover tbody tr.row-total-blue:hover > th {
            background-color: var(--row-total-bg, #0857c3) !important;
            color: var(--row-total-color, #ffffff) !important;
            border-color: var(--row-total-border, inherit) !important;
        }

        .content-wrapper .table-container {
            width: 100%;
            overflow-x: auto !important;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        }

        .content-wrapper .table-report {
            table-layout: fixed !important;
            width: max-content;
            min-width: 100%;
        }

        .content-wrapper .table-report th,
        .content-wrapper .table-report td {
            min-width: var(--report-data-col-width);
            width: var(--report-data-col-width);
            white-space: nowrap !important;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .content-wrapper .table-report th {
            font-size: var(--report-th-font-size) !important;
            padding: var(--report-th-padding) !important;
        }

        .content-wrapper .table-report td {
            font-size: var(--report-td-font-size) !important;
            padding: var(--report-td-padding) !important;
        }

        .content-wrapper .table-report th.text-left,
        .content-wrapper .table-report td.text-left,
        .content-wrapper .table-report th.align-middle:first-child,
        .content-wrapper .table-report td:first-child {
            min-width: var(--report-first-col-width) !important;
            width: var(--report-first-col-width) !important;
        }

        .content-wrapper .table-container .table-report thead > tr:first-child > th:first-child:not(.sticky-col),
        .content-wrapper .table-container .table-report tbody td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table-report tfoot td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table-report tfoot th:first-child:not(.sticky-col) {
            position: sticky;
            left: 0;
            background-clip: padding-box;
            box-shadow: 10px 0 14px -14px rgba(15, 23, 42, 0.35);
        }

        .content-wrapper .table-container .table-report thead > tr:first-child > th:first-child:not(.sticky-col) {
            z-index: 12;
        }

        .content-wrapper .table-container .table-report tbody td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table-report tfoot td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table-report tfoot th:first-child:not(.sticky-col) {
            z-index: 4;
            background-color: #ffffff;
        }

        .content-wrapper .table-container .table.table-hover tbody tr:hover > td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr:hover > th:first-child:not(.sticky-col) {
            background-color: rgba(113, 197, 232, 0.16) !important;
        }

        .content-wrapper .table-container .table.table-hover tbody tr.row-total > td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr.row-total > th:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr.row-total:hover > td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr.row-total:hover > th:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr.row-total-blue > td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr.row-total-blue > th:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr.row-total-blue:hover > td:first-child:not(.sticky-col),
        .content-wrapper .table-container .table.table-hover tbody tr.row-total-blue:hover > th:first-child:not(.sticky-col) {
            background-color: var(--row-total-bg, #0857c3) !important;
            color: var(--row-total-color, #ffffff) !important;
            border-color: var(--row-total-border, inherit) !important;
        }

        @media (max-width: 575.98px) {
            .content-wrapper {
                --report-first-col-width: 170px;
                --report-data-col-width: 74px;
                --report-th-font-size: 0.58rem;
                --report-td-font-size: 0.62rem;
                --report-th-padding: 8px 4px;
                --report-td-padding: 6px 4px;
            }
        }

        @media (min-width: 576px) and (max-width: 991.98px) {
            .content-wrapper {
                --report-first-col-width: 200px;
                --report-data-col-width: 84px;
                --report-th-font-size: 0.62rem;
                --report-td-font-size: 0.68rem;
                --report-th-padding: 9px 5px;
                --report-td-padding: 6px 6px;
            }
        }

        @media (max-width: 991.98px) {
            .content-wrapper .report-tabs {
                gap: 0.15rem;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            .content-wrapper .report-tabs .nav-link {
                font-size: 0.85rem !important;
                padding: 10px 12px !important;
            }
        }

        .main-sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--bri-nusantara) 0%, var(--bri-cakrawala) 100%) !important;
            color: #ffffff !important;
            box-shadow: 0 6px 20px rgba(8, 87, 195, 0.25) !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
        }

        .main-sidebar .nav-link:hover {
            background: rgba(8, 87, 195, 0.05) !important;
            color: var(--bri-nusantara) !important;
        }

        .main-sidebar .nav-treeview > .nav-item > .nav-link.active {
            background: rgba(8, 87, 195, 0.06) !important;
            color: var(--bri-nusantara) !important;
        }

        /* Seamless Sidebar Transitions */
        .sidebar-mini .main-sidebar,
        .sidebar-mini .main-sidebar .brand-link,
        .sidebar-mini .main-sidebar .sidebar,
        .sidebar-mini .main-sidebar .nav-sidebar .nav-link,
        .sidebar-mini .main-sidebar .sidebar-user-panel,
        .sidebar-mini .main-sidebar .sidebar-brand-badge,
        .sidebar-mini .main-sidebar .sidebar-user-avatar,
        .sidebar-mini .main-sidebar .nav-icon,
        .sidebar-mini .main-sidebar .right {
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1) !important;
        }

        /* Styling when sidebar is collapsed and not hovered */
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) {
            width: 4.8rem !important;
        }
        
        .sidebar-mini.sidebar-collapse .main-sidebar .brand-link,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .brand-link {
            padding: 1rem 0.5rem !important;
            justify-content: center !important;
            transition: none !important;
            transform: none !important;
            width: 4.8rem !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-user-panel {
            padding: 0.6rem 0.3rem !important;
            justify-content: center !important;
            margin-left: 0.3rem;
            margin-right: 0.3rem;
            width: calc(100% - 0.6rem);
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar > .nav-item > .nav-link {
            padding: 0.6rem 0 !important;
            justify-content: center !important;
            width: calc(100% - 0.8rem);
            margin: 0 auto 0.35rem auto !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-brand-badge,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-user-avatar,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-icon {
            margin-right: 0 !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-brand-text,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-user-info,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar p,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar .right,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-header,
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar .nav-treeview {
            display: none !important;
        }

        /* Hovering over collapsed sidebar (Keeps it collapsed to prevent layout bugs) */
        .sidebar-mini.sidebar-collapse .main-sidebar:hover,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused {
            width: 4.8rem !important;
            box-shadow: none !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-brand-text,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-brand-text,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-info,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-info,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar p,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-sidebar p,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar .right,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-sidebar .right,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-header,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-header,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar .nav-treeview,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-treeview {
            display: none !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .brand-link,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .brand-link {
            padding: 1rem 0.5rem !important;
            justify-content: center !important;
            transition: none !important;
            transform: none !important;
            width: 4.8rem !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-panel,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-panel {
            padding: 0.6rem 0.3rem !important;
            justify-content: center !important;
            margin-left: 0.3rem;
            margin-right: 0.3rem;
            width: calc(100% - 0.6rem);
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar > .nav-item > .nav-link,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-sidebar > .nav-item > .nav-link {
            padding: 0.6rem 0 !important;
            justify-content: center !important;
            width: calc(100% - 0.8rem);
            margin: 0 auto 0.35rem auto !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-brand-badge,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-brand-badge,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-avatar,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-avatar,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-icon,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-icon {
            margin-right: 0 !important;
        }


        .content-wrapper,
        .content-wrapper .content,
        .content-wrapper .container-fluid {
            transition: opacity 250ms ease, transform 300ms var(--ease-out-expo), filter 250ms ease;
            will-change: opacity, transform, filter;
        }

        body.page-transition-leaving .content-wrapper,
        body.page-transition-leaving .content-wrapper .content,
        body.page-transition-leaving .content-wrapper .container-fluid {
            opacity: 0.45;
            transform: translateY(6px);
            filter: blur(4px);
            pointer-events: none;
        }

        .page-transition-bar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 2000;
            height: 3px;
            width: 100%;
            transform-origin: left;
            transform: scaleX(0);
            background: linear-gradient(90deg, var(--bri-nusantara) 0%, var(--bri-mentari) 100%);
            box-shadow: 0 2px 10px rgba(8, 87, 195, 0.35);
            opacity: 0;
            transition: transform 320ms ease-out, opacity 180ms ease;
            pointer-events: none;
        }

        body.page-transition-active .page-transition-bar {
            opacity: 1;
            transform: scaleX(0.82);
        }

        body.page-transition-finishing .page-transition-bar {
            opacity: 0;
            transform: scaleX(1);
        }

        .route-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 2600;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1rem, 3vw, 2rem);
            background: rgba(248, 250, 252, 0.45);
            backdrop-filter: blur(18px) saturate(1.2);
            -webkit-backdrop-filter: blur(18px) saturate(1.2);
            opacity: 0;
            pointer-events: none;
            transition: opacity 250ms ease;
        }

        body.page-route-navigating .route-loading-overlay,
        body.page-form-submitting .route-loading-overlay,
        body.page-data-loading .route-loading-overlay {
            opacity: 1;
            pointer-events: auto;
        }

        .route-loading-card {
            width: min(390px, calc(100vw - 2rem));
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            box-shadow: 
                0 8px 32px 0 rgba(8, 87, 195, 0.08), 
                0 20px 40px -10px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: clamp(1.2rem, 3.5vw, 1.5rem);
            color: #0f172a;
            transform: translateY(12px) scale(0.97);
            transition: transform 300ms cubic-bezier(0.34, 1.56, 0.64, 1), opacity 250ms ease;
            opacity: 0;
        }

        body.page-route-navigating .route-loading-card,
        body.page-form-submitting .route-loading-card,
        body.page-data-loading .route-loading-card {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .route-loading-head {
            display: flex;
            align-items: center;
            gap: 0.95rem;
        }

        .route-loading-mark {
            width: 46px;
            height: 46px;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--bri-nusantara);
            background: rgba(8, 87, 195, 0.05);
            border: 1px solid rgba(8, 87, 195, 0.12);
            border-radius: 14px;
            flex: 0 0 auto;
            box-shadow: 0 4px 12px rgba(8, 87, 195, 0.04);
        }

        .route-loading-mark::before {
            content: "";
            position: absolute;
            inset: -2px;
            border: 3px solid transparent;
            border-top-color: var(--bri-nusantara);
            border-right-color: var(--bri-cakrawala);
            border-radius: 16px;
            animation: route-loading-spin 0.95s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }

        .route-loading-mark i {
            position: relative;
            z-index: 1;
            font-size: 0.95rem;
            animation: route-loading-bounce 1.5s ease-in-out infinite;
        }

        .route-loading-title {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #0f172a;
        }

        .route-loading-subtitle {
            margin-top: 0.2rem;
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 600;
            line-height: 1.4;
        }

        .route-loading-bar {
            height: 4px;
            margin: 1.1rem 0 0.9rem;
            overflow: hidden;
            background: rgba(8, 87, 195, 0.06);
            border-radius: 999px;
        }

        .route-loading-bar span {
            display: block;
            width: 42%;
            height: 100%;
            background: linear-gradient(90deg, var(--bri-nusantara), var(--bri-cakrawala), var(--bri-mentari));
            border-radius: inherit;
            animation: route-loading-scan 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }

        .route-loading-note {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin: 0;
            font-size: 0.72rem;
            color: #475569;
            font-weight: 700;
        }

        .route-loading-dots {
            display: inline-flex;
            gap: 0.25rem;
        }

        .route-loading-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--bri-cakrawala);
            opacity: 0.35;
            animation: route-loading-dot 1.2s ease-in-out infinite;
        }

        .route-loading-dots span:nth-child(2) {
            animation-delay: 0.16s;
        }

        .route-loading-dots span:nth-child(3) {
            animation-delay: 0.32s;
        }

        @keyframes route-loading-spin {
            to { transform: rotate(360deg); }
        }

        @keyframes route-loading-scan {
            0% { transform: translateX(-115%); }
            50% { transform: translateX(80%); }
            100% { transform: translateX(235%); }
        }

        @keyframes route-loading-bounce {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(3px); }
        }

        @keyframes route-loading-dot {
            0%, 100% { opacity: 0.3; transform: translateY(0); }
            50% { opacity: 1; transform: translateY(-2px); }
        }

        /* Responsive application shell */
        html,
        body {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            overflow-x: clip;
        }

        body {
            min-width: 0;
        }

        .wrapper,
        .content-wrapper,
        .content-wrapper .content,
        .content-wrapper .container-fluid {
            min-width: 0;
        }

        .content-wrapper .container-fluid {
            width: 100%;
            max-width: 1840px;
            margin-left: auto;
            margin-right: auto;
            padding-left: max(var(--app-page-gutter), var(--app-safe-left));
            padding-right: max(var(--app-page-gutter), var(--app-safe-right));
        }

        .content-wrapper .container-fluid .container-fluid {
            max-width: none;
            padding-left: 0;
            padding-right: 0;
        }

        .content-wrapper .row,
        .content-wrapper [class^="col-"],
        .content-wrapper [class*=" col-"],
        .content-wrapper .card,
        .content-wrapper .card-body,
        .content-wrapper .card-header,
        .content-wrapper .tab-content,
        .content-wrapper .tab-pane {
            min-width: 0;
        }

        .content-wrapper img,
        .content-wrapper svg,
        .content-wrapper canvas,
        .content-wrapper video {
            max-width: 100%;
        }

        .content-wrapper .card,
        .content-wrapper .modal-content,
        .content-wrapper .alert {
            max-width: 100%;
        }

        .content-wrapper .input-group,
        .content-wrapper .input-group > .form-control,
        .content-wrapper .select2-container,
        .content-wrapper .select2-selection,
        .content-wrapper .dropdown,
        .content-wrapper form {
            min-width: 0;
        }

        .content-wrapper .select2-container {
            max-width: 100%;
        }

        .content-wrapper .nav-tabs {
            max-width: 100%;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
        }

        .content-wrapper .nav-tabs .nav-item,
        .content-wrapper .nav-tabs .nav-link {
            flex: 0 0 auto;
        }

        .dropdown-menu {
            max-width: calc(100vw - max(1rem, var(--app-safe-left)) - max(1rem, var(--app-safe-right)));
            overflow-wrap: anywhere;
        }

        .modal-dialog {
            width: auto;
            max-width: min(720px, calc(100vw - max(1.5rem, var(--app-safe-left)) - max(1.5rem, var(--app-safe-right))));
            margin: max(0.75rem, var(--app-safe-top)) auto max(0.75rem, var(--app-safe-bottom));
        }

        .modal-dialog.modal-lg,
        .modal-dialog.modal-xl {
            max-width: min(1140px, calc(100vw - max(1.5rem, var(--app-safe-left)) - max(1.5rem, var(--app-safe-right))));
        }

        .modal-content {
            max-height: calc(100dvh - max(1.5rem, var(--app-safe-top)) - max(1.5rem, var(--app-safe-bottom)));
            overflow: hidden;
        }

        .modal-body {
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .swal2-container {
            padding: max(0.75rem, var(--app-safe-top)) max(0.75rem, var(--app-safe-right)) max(0.75rem, var(--app-safe-bottom)) max(0.75rem, var(--app-safe-left)) !important;
        }

        .swal2-popup {
            max-width: calc(100vw - max(1.5rem, var(--app-safe-left)) - max(1.5rem, var(--app-safe-right))) !important;
            max-height: calc(100dvh - max(1.5rem, var(--app-safe-top)) - max(1.5rem, var(--app-safe-bottom)));
            overflow-x: hidden;
            overflow-y: auto;
        }

        .content-wrapper .table-responsive,
        .content-wrapper .table-container,
        .content-wrapper [class*="table-wrap"],
        .content-wrapper [class*="table-scroll"] {
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .content-wrapper .abah-table-scroll,
        .content-wrapper .table-responsive,
        .content-wrapper .table-container,
        .content-wrapper [class*="table-wrap"],
        .content-wrapper [class*="table-scroll"] {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            max-height: min(72vh, 820px);
            max-height: min(72dvh, 820px);
            overflow-x: auto;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-gutter: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #ffffff;
        }

        @media (pointer: fine) {
            .content-wrapper .abah-table-scroll,
            .content-wrapper .table-responsive,
            .content-wrapper .table-container,
            .content-wrapper [class*="table-wrap"],
            .content-wrapper [class*="table-scroll"] {
                scrollbar-gutter: stable;
            }
        }

        .content-wrapper .abah-table-scroll {
            position: relative;
            border-radius: 10px;
        }

        .content-wrapper .abah-table-scroll::-webkit-scrollbar,
        .content-wrapper .table-responsive::-webkit-scrollbar,
        .content-wrapper .table-container::-webkit-scrollbar,
        .content-wrapper [class*="table-wrap"]::-webkit-scrollbar,
        .content-wrapper [class*="table-scroll"]::-webkit-scrollbar {
            width: 11px;
            height: 12px;
        }

        .content-wrapper .abah-table-scroll::-webkit-scrollbar-track,
        .content-wrapper .table-responsive::-webkit-scrollbar-track,
        .content-wrapper .table-container::-webkit-scrollbar-track,
        .content-wrapper [class*="table-wrap"]::-webkit-scrollbar-track,
        .content-wrapper [class*="table-scroll"]::-webkit-scrollbar-track {
            background: #ffffff;
            border-radius: 999px;
            box-shadow: inset 0 0 0 1px #e2e8f0;
        }

        .content-wrapper .abah-table-scroll::-webkit-scrollbar-thumb,
        .content-wrapper .table-responsive::-webkit-scrollbar-thumb,
        .content-wrapper .table-container::-webkit-scrollbar-thumb,
        .content-wrapper [class*="table-wrap"]::-webkit-scrollbar-thumb,
        .content-wrapper [class*="table-scroll"]::-webkit-scrollbar-thumb {
            min-width: 64px;
            border: 3px solid #ffffff;
            border-radius: 999px;
            background: #cbd5e1;
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.3);
        }

        .content-wrapper .abah-table-scroll:hover::-webkit-scrollbar-thumb,
        .content-wrapper .table-responsive:hover::-webkit-scrollbar-thumb,
        .content-wrapper .table-container:hover::-webkit-scrollbar-thumb,
        .content-wrapper [class*="table-wrap"]:hover::-webkit-scrollbar-thumb,
        .content-wrapper [class*="table-scroll"]:hover::-webkit-scrollbar-thumb {
            background: #94a3b8;
        }

        .content-wrapper .abah-table-managed {
            width: max-content;
            min-width: 100%;
            margin-bottom: 0;
        }

        .content-wrapper .abah-table-managed thead th {
            position: sticky;
            top: var(--abah-table-head-top, 0px);
            z-index: 30;
            background-clip: padding-box;
            box-shadow:
                inset 0 -1px 0 rgba(148, 163, 184, 0.28),
                0 8px 18px -18px rgba(15, 23, 42, 0.55);
        }

        .content-wrapper .abah-table-managed thead th.sticky-col,
        .content-wrapper .abah-table-managed thead th[class*="sticky"] {
            z-index: 45;
        }

        .content-wrapper .abah-table-managed th,
        .content-wrapper .abah-table-managed td {
            vertical-align: middle;
            max-width: min(42vw, 420px);
        }

        .content-wrapper .abah-table-managed th:not(.text-wrap):not(.text-break),
        .content-wrapper .abah-table-managed td:not(.text-wrap):not(.text-break) {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .content-wrapper .btn,
        .content-wrapper .form-control,
        .content-wrapper .custom-select,
        .content-wrapper .select2-container--default .select2-selection--single {
            max-width: 100%;
        }

        .content-wrapper .loan-title-hero,
        .content-wrapper .rm-mikro-hero,
        .content-wrapper .kpi-hero,
        .content-wrapper .fh-hero,
        .content-wrapper .daily-panel-head,
        .content-wrapper :where(
            .dashboard-hero,
            .area6-hero,
            .report-hero,
            .import-hero,
            .admin-hero,
            .market-hero,
            .hourly-hero,
            .dana-hero,
            .casa-hero,
            .dormant-hero,
            .payroll-hero,
            .kinerja-konsumer-hero,
            .kinerja-header,
            .dashboard-header,
            [data-ui="hero"],
            [data-ui="banner"]
        ) {
            max-width: 100%;
        }

        .content-wrapper .loan-title-hero__desc,
        .content-wrapper .rm-mikro-hero p,
        .content-wrapper .kpi-hero p,
        .content-wrapper .fh-hero p,
        .content-wrapper .daily-panel-desc,
        .content-wrapper :where(
            .dashboard-subtitle,
            .report-subtitle,
            .page-subtitle,
            .hero-subtitle,
            .hero-description,
            .hero-desc,
            .panel-subtitle,
            .section-subtitle,
            .kinerja-report-card__subtitle,
            [data-ui="description"]
        ) {
            display: -webkit-box;
            overflow: hidden;
            max-width: 78ch;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            text-overflow: ellipsis;
        }

        .content-wrapper .loan-filter-modern,
        .content-wrapper .fh-filter,
        .content-wrapper .kpi-toolbar,
        .content-wrapper .daily-filter-shell,
        .content-wrapper .report-filter-card .card-body,
        .content-wrapper .loan-filter-grid,
        .content-wrapper :where(
            .lux-filter-card,
            .lux-filter-grid,
            .kinerja-konsumer-filters,
            .market-filter-panel,
            .hourly-filter-shell,
            .dana-filter-card,
            .casa-shell,
            .dormant-shell,
            .import-filter-card,
            .import-toolbar,
            .report-toolbar,
            .admin-toolbar,
            .filter-card,
            .filter-panel,
            .filter-shell,
            .filter-toolbar,
            .filter-bar,
            [data-ui="filter"],
            [data-ui="toolbar"]
        ) {
            max-width: 100%;
        }

        .content-wrapper .loan-filter-label,
        .content-wrapper .daily-filter-label,
        .content-wrapper .report-filter-label,
        .content-wrapper .fh-field label,
        .content-wrapper :where(
            .filter-label,
            .import-label,
            .casa-filter-label,
            .dormant-filter-label,
            .kinerja-filter-label,
            [class*="-filter-label"],
            [class*="filter-label"]
        ) {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-height: 760px), (max-width: 991.98px) {
            .content-wrapper .loan-title-hero,
            .content-wrapper .rm-mikro-hero,
            .content-wrapper .kpi-hero,
            .content-wrapper .fh-hero,
            .content-wrapper .daily-panel-head,
            .content-wrapper :where(
                .dashboard-hero,
                .area6-hero,
                .report-hero,
                .import-hero,
                .admin-hero,
                .market-hero,
                .hourly-hero,
                .dana-hero,
                .casa-hero,
                .dormant-hero,
                .payroll-hero,
                .kinerja-konsumer-hero,
                .kinerja-header,
                .dashboard-header,
                [data-ui="hero"],
                [data-ui="banner"]
            ) {
                margin-bottom: 0.75rem !important;
                padding: 0.85rem 1rem !important;
                border-radius: 12px !important;
            }

            .content-wrapper .loan-title-hero__title,
            .content-wrapper .rm-mikro-hero h1,
            .content-wrapper .kpi-hero h1,
            .content-wrapper .fh-hero h1,
            .content-wrapper .daily-panel-title,
            .content-wrapper :where(
                .page-title,
                .report-title,
                .dashboard-title,
                .hero-title,
                .section-title,
                .kinerja-report-card__title,
                [data-ui="title"]
            ) {
                font-size: clamp(1.12rem, 4.4vw, 1.45rem) !important;
                line-height: 1.12 !important;
            }

            .content-wrapper .loan-title-hero__badge,
            .content-wrapper .kpi-eyebrow,
            .content-wrapper .fh-eyebrow,
            .content-wrapper .daily-title-badge {
                margin-bottom: 0.25rem !important;
                font-size: 0.6rem !important;
            }

            .content-wrapper .loan-title-hero__desc,
            .content-wrapper .rm-mikro-hero p,
            .content-wrapper .kpi-hero p,
            .content-wrapper .fh-hero p,
            .content-wrapper .daily-panel-desc,
            .content-wrapper :where(
                .dashboard-subtitle,
                .report-subtitle,
                .page-subtitle,
                .hero-subtitle,
                .hero-description,
                .hero-desc,
                .panel-subtitle,
                .section-subtitle,
                .kinerja-report-card__subtitle,
                [data-ui="description"]
            ) {
                margin-top: 0.25rem !important;
                font-size: 0.74rem !important;
                line-height: 1.35 !important;
                -webkit-line-clamp: 1;
            }

            .content-wrapper .loan-filter-modern,
            .content-wrapper .fh-filter,
            .content-wrapper .kpi-toolbar,
            .content-wrapper .daily-filter-shell,
            .content-wrapper .card.loan-shell .card-body,
            .content-wrapper .report-filter-card .card-body,
            .content-wrapper :where(
                .lux-filter-card,
                .kinerja-konsumer-filters,
                .market-filter-panel,
                .hourly-filter-shell,
                .dana-filter-card,
                .casa-shell .card-body,
                .dormant-shell .card-body,
                .import-filter-card,
                .import-toolbar,
                .report-toolbar,
                .admin-toolbar,
                .filter-card,
                .filter-panel,
                .filter-shell,
                .filter-toolbar,
                .filter-bar,
                [data-ui="filter"],
                [data-ui="toolbar"]
            ) {
                margin-bottom: 0.85rem !important;
                padding: 0.75rem !important;
                border-radius: 12px !important;
            }

            .content-wrapper :where(
                .form-group,
                .input-group,
                .filter-group,
                .kinerja-filter-group,
                [data-ui="field"]
            ) {
                min-width: 0;
                margin-bottom: 0.55rem !important;
            }

            .content-wrapper :where(
                .form-control,
                .custom-select,
                .select2-container--default .select2-selection--single,
                .btn
            ) {
                min-height: 34px !important;
                font-size: 0.8rem !important;
            }

            .content-wrapper .abah-table-scroll,
            .content-wrapper .table-responsive,
            .content-wrapper .table-container,
            .content-wrapper [class*="table-wrap"],
            .content-wrapper [class*="table-scroll"] {
                max-height: min(76vh, 760px);
            }
        }

        @media (orientation: landscape) and (max-height: 640px) {
            .content-wrapper .loan-title-hero__desc,
            .content-wrapper .rm-mikro-hero p,
            .content-wrapper .kpi-hero p,
            .content-wrapper .fh-hero p,
            .content-wrapper .daily-panel-desc,
            .content-wrapper :where(
                .dashboard-subtitle,
                .report-subtitle,
                .page-subtitle,
                .hero-subtitle,
                .hero-description,
                .hero-desc,
                .panel-subtitle,
                .section-subtitle,
                .kinerja-report-card__subtitle,
                [data-ui="description"]
            ),
            .content-wrapper .fh-hint,
            .content-wrapper .kpi-table-title span,
            .content-wrapper .fh-subtitle {
                display: none !important;
            }

            .content-wrapper :where(
                .filter-card,
                .filter-panel,
                .filter-shell,
                .filter-toolbar,
                .filter-bar,
                .lux-filter-card,
                .kinerja-konsumer-filters,
                .market-filter-panel,
                .hourly-filter-shell,
                .dana-filter-card,
                .import-toolbar,
                .report-toolbar,
                .admin-toolbar
            ) {
                padding-top: 0.55rem !important;
                padding-bottom: 0.55rem !important;
            }

            .content-wrapper .kpi-meta-grid,
            .content-wrapper .daily-kpi-grid {
                gap: 0.5rem !important;
                margin-bottom: 0.65rem !important;
            }

            .content-wrapper .kpi-meta,
            .content-wrapper .daily-kpi {
                padding: 0.55rem 0.7rem !important;
            }

            .content-wrapper .abah-table-scroll,
            .content-wrapper .table-responsive,
            .content-wrapper .table-container,
            .content-wrapper [class*="table-wrap"],
            .content-wrapper [class*="table-scroll"] {
                max-height: calc(100vh - 118px);
            }
        }

        @media (min-width: 1800px) {
            .content-wrapper .container-fluid {
                max-width: 1920px;
            }
        }

        @media (min-width: 2200px) {
            .content-wrapper .container-fluid {
                max-width: 2160px;
            }
        }

        @media (max-width: 991.98px) {
            .content-wrapper {
                padding-top: 0.75rem;
            }

            .content {
                padding-top: 1rem;
                padding-bottom: 1.35rem;
            }

            .main-header.modern-navbar {
                min-height: 58px;
            }

            .content-wrapper .card {
                border-radius: 0.9rem;
            }
        }

        @media (max-width: 575.98px) {
            .content-wrapper {
                background: linear-gradient(180deg, #f7fbff 0%, #eef5ff 100%);
                padding-top: 0.55rem;
            }

            .content {
                padding-top: 0.75rem;
                padding-bottom: 1rem;
            }

            .content-wrapper .container-fluid {
                --app-page-gutter: 0.65rem;
            }

            .content-wrapper .card-header > .d-flex,
            .content-wrapper .card-footer > .d-flex,
            .content-wrapper .card-tools,
            .content-wrapper [data-ui="actions"] {
                width: 100%;
                min-width: 0;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .content-wrapper .card-tools {
                float: none;
                margin-right: 0;
            }

            .modal-dialog,
            .modal-dialog.modal-lg,
            .modal-dialog.modal-xl {
                max-width: calc(100vw - max(0.75rem, var(--app-safe-left)) - max(0.75rem, var(--app-safe-right)));
                margin-top: max(0.4rem, var(--app-safe-top));
                margin-bottom: max(0.4rem, var(--app-safe-bottom));
            }

            .modal-content {
                max-height: calc(100dvh - max(0.8rem, var(--app-safe-top)) - max(0.8rem, var(--app-safe-bottom)));
            }

            .content-wrapper .abah-table-managed th,
            .content-wrapper .abah-table-managed td {
                max-width: min(68vw, 300px);
            }

            .content-wrapper .abah-table-scroll,
            .content-wrapper .table-responsive,
            .content-wrapper .table-container,
            .content-wrapper [class*="table-wrap"],
            .content-wrapper [class*="table-scroll"] {
                max-height: max(420px, calc(100dvh - 150px));
            }

            .modern-navbar .menu-toggle,
            .modern-user-trigger {
                min-width: 40px;
                min-height: 40px;
            }

            .modern-user-trigger .fa-chevron-down {
                display: none;
            }

            .modern-user-badge {
                width: 38px;
                height: 38px;
            }

            .route-loading-card {
                border-radius: 14px;
            }

            .route-loading-title {
                font-size: 0.9rem;
            }

            .route-loading-subtitle,
            .route-loading-note {
                font-size: 0.68rem;
            }
        }

        @media (max-width: 359.98px) {
            .content-wrapper .container-fluid {
                --app-page-gutter: 0.45rem;
            }

            .main-header.modern-navbar {
                padding-left: max(0.4rem, var(--app-safe-left));
                padding-right: max(0.4rem, var(--app-safe-right));
            }

            .content-wrapper .card-header,
            .content-wrapper .card-body,
            .content-wrapper .card-footer {
                padding-left: 0.65rem;
                padding-right: 0.65rem;
            }

            .swal2-container {
                padding-left: max(0.4rem, var(--app-safe-left)) !important;
                padding-right: max(0.4rem, var(--app-safe-right)) !important;
            }
        }

        @media (pointer: coarse) {
            .content-wrapper :where(.form-control, .custom-select, .select2-selection--single, .btn:not(.btn-xs)) {
                min-height: var(--app-control-height) !important;
            }
        }

        @media (orientation: landscape) and (max-height: 640px) {
            .content-wrapper .abah-table-scroll,
            .content-wrapper .table-responsive,
            .content-wrapper .table-container,
            .content-wrapper [class*="table-wrap"],
            .content-wrapper [class*="table-scroll"] {
                max-height: max(180px, calc(100dvh - 118px));
            }
        }

        @media (max-height: 540px) and (orientation: landscape) {
            .route-loading-overlay {
                align-items: flex-start;
                padding-top: 0.75rem;
            }

            .route-loading-card {
                padding: 0.85rem;
            }

            .route-loading-bar {
                margin-top: 0.75rem;
                margin-bottom: 0.65rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .content-wrapper,
            .content-wrapper .content,
            .content-wrapper .container-fluid,
            .page-transition-bar,
            .route-loading-overlay,
            .route-loading-card,
            .route-loading-mark::before,
            .route-loading-bar span,
            .route-loading-dots span {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="page-transition-bar" aria-hidden="true"></div>
    <div class="route-loading-overlay" id="route-loading-overlay" role="status" aria-live="polite" aria-label="Memuat halaman">
        <div class="route-loading-card">
            <div class="route-loading-head">
                <div class="route-loading-mark"><i class="fas fa-arrow-right"></i></div>
                <div>
                    <p class="route-loading-title" id="route-loading-title">Memuat halaman</p>
                    <div class="route-loading-subtitle" id="route-loading-subtitle">Menyiapkan tampilan berikutnya dengan data terbaru.</div>
                </div>
            </div>
            <div class="route-loading-bar" aria-hidden="true"><span></span></div>
            <p class="route-loading-note">
                <span>Mohon tunggu sebentar</span>
                <span class="route-loading-dots" aria-hidden="true"><span></span><span></span><span></span></span>
            </p>
        </div>
    </div>

    <nav class="main-header modern-navbar navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link menu-toggle" data-widget="pushmenu" href="#" role="button" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-flex align-items-center">
                <div class="modern-navbar-brand" aria-label="A-Six dashboard">
                    <span>
                        <span class="title">A-Six Dashboard</span>
                        <span class="subtitle">Area 6 Monitoring</span>
                    </span>
                </div>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link modern-user-trigger d-flex align-items-center" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                    <span class="modern-user-badge mr-3">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</span>
                    <span class="d-none d-sm-block">
                        <span class="d-block font-weight-bold modern-user-name">{{ Auth::user()->pn }} - {{ Auth::user()->name }}</span>
                        <span class="d-block text-uppercase font-weight-bold modern-user-caption">
                            {{ !empty($userBranchScope) ? 'Akses ' . $userBranchScope['label'] : 'Akses Area 6' }}
                        </span>
                    </span>
                    <i class="fas fa-chevron-down ml-3" style="font-size: 0.75rem; color: #71c5e8;"></i>
                </a>

                <div class="dropdown-menu dropdown-menu-right modern-user-menu">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i>
                            Log Out
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    @include('layouts.sidebar')

    <div class="content-wrapper">


        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>
</div>

<script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('adminlte/dist/js/adminlte.min.js') }}"></script>
<script src="{{ asset('adminlte/plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('adminlte/plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>
<script>
    $(function () {
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('.content-wrapper');
        if (!root) {
            return;
        }

        const wrapperSelector = [
            '.abah-table-scroll',
            '.table-responsive',
            '.table-container',
            '.kinerja-table-container',
            '[class*="table-wrap"]',
            '[class*="table-scroll"]'
        ].join(',');
        const skipSelector = [
            '[data-abah-no-table-guard]',
            '.no-abah-table-guard',
            '.select2-results',
            '.fc',
            '.fc-scrollgrid'
        ].join(',');
        let syncFrame = null;

        const shouldSkipTable = function (table) {
            return table.closest(skipSelector)
                || table.closest('.dropdown-menu')
                || table.closest('.note-editor')
                || table.classList.contains('dataTable')
                || table.dataset.abahNoTableGuard === '1';
        };

        const ensureWrapper = function (table) {
            if (table.closest(wrapperSelector)) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'abah-table-scroll';
            wrapper.dataset.abahAutoTableScroll = '1';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        };

        const syncHeaderOffsets = function (table) {
            const rows = table.tHead ? Array.from(table.tHead.rows) : [];
            let top = 0;

            rows.forEach(function (row) {
                Array.from(row.cells).forEach(function (cell) {
                    cell.style.setProperty('--abah-table-head-top', top + 'px');
                });

                top += Math.ceil(row.getBoundingClientRect().height || 0);
            });
        };

        const syncReadableCellTitles = function (table) {
            table.querySelectorAll('th, td').forEach(function (cell) {
                if (cell.hasAttribute('title') || cell.closest('[data-abah-no-cell-title]')) {
                    return;
                }

                const text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
                if (text.length > 18) {
                    cell.setAttribute('title', text);
                }
            });
        };

        const enhanceTable = function (table) {
            if (!(table instanceof HTMLTableElement) || shouldSkipTable(table)) {
                return;
            }

            ensureWrapper(table);
            table.classList.add('abah-table-managed');
            syncHeaderOffsets(table);
            syncReadableCellTitles(table);
        };

        const enhanceTables = function () {
            syncFrame = null;
            root.querySelectorAll('table').forEach(enhanceTable);
        };

        const scheduleEnhance = function () {
            if (syncFrame !== null) {
                return;
            }

            syncFrame = window.requestAnimationFrame(enhanceTables);
        };

        const observer = new MutationObserver(scheduleEnhance);
        observer.observe(root, {
            childList: true,
            subtree: true
        });

        window.addEventListener('resize', scheduleEnhance);
        window.addEventListener('load', scheduleEnhance);
        document.addEventListener('shown.bs.tab', scheduleEnhance);
        document.addEventListener('shown.bs.collapse', scheduleEnhance);
        document.addEventListener('shown.bs.modal', scheduleEnhance);

        if ('ResizeObserver' in window) {
            const resizeObserver = new ResizeObserver(scheduleEnhance);
            resizeObserver.observe(root);
        }

        scheduleEnhance();
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const body = document.body;
        const routeLoader = document.getElementById('route-loading-overlay');
        const routeLoadingTitle = document.getElementById('route-loading-title');
        const routeLoadingSubtitle = document.getElementById('route-loading-subtitle');
        if (routeLoader) {
            body.appendChild(routeLoader);
        }
        const prefetchCache = new Set();
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const shouldPrefetch = document.documentElement.dataset.prefetchDocuments === 'true';
        const dashboardLandingPath = new URL(@json(route('dashboard')), window.location.origin).pathname;
        let transitionTimeout = null;
        let routeLoadingTimeout = null;
        let overlayDelayTimeout = null;

        const clearPageTransition = function () {
            if (transitionTimeout) {
                window.clearTimeout(transitionTimeout);
                transitionTimeout = null;
            }

            body.classList.remove('page-transition-active', 'page-transition-leaving', 'page-transition-finishing');
        };

        const clearRouteLoading = function () {
            if (overlayDelayTimeout) {
                window.clearTimeout(overlayDelayTimeout);
                overlayDelayTimeout = null;
            }
            if (routeLoadingTimeout) {
                window.clearTimeout(routeLoadingTimeout);
                routeLoadingTimeout = null;
            }

            body.classList.remove('page-route-navigating', 'page-form-submitting', 'page-data-loading');
        };

        const showRouteLoading = function (title, subtitle, className) {
            if (overlayDelayTimeout) {
                window.clearTimeout(overlayDelayTimeout);
                overlayDelayTimeout = null;
            }

            if (routeLoadingTitle && title) {
                routeLoadingTitle.textContent = title;
            }

            if (routeLoadingSubtitle && subtitle) {
                routeLoadingSubtitle.textContent = subtitle;
            }

            body.classList.add(className || 'page-route-navigating');

            if (routeLoadingTimeout) {
                window.clearTimeout(routeLoadingTimeout);
            }

            routeLoadingTimeout = window.setTimeout(function () {
                clearPageTransition();

                if (routeLoadingSubtitle && body.classList.contains('page-route-navigating')) {
                    routeLoadingSubtitle.textContent = 'Koneksi masih memproses halaman berikutnya. Mohon tunggu sebentar.';
                }
            }, 8000);
        };

        // Expose route loading functions globally to allow programmatic control in scripts
        window.showRouteLoading = showRouteLoading;
        window.clearRouteLoading = clearRouteLoading;

        const isDashboardLandingLink = function (link) {
            if (!link) {
                return false;
            }

            const url = new URL(link.href, window.location.origin);
            return url.origin === window.location.origin && url.pathname === dashboardLandingPath;
        };

        const isInternalNavigableLink = function (link) {
            if (!link || link.target === '_blank' || link.hasAttribute('download')) {
                return false;
            }

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                return false;
            }

            const url = new URL(link.href, window.location.origin);
            if (url.origin !== window.location.origin) {
                return false;
            }

            return url.pathname + url.search !== window.location.pathname + window.location.search;
        };

        const prefetchLink = function (link) {
            if (!shouldPrefetch) {
                return;
            }

            if (!isInternalNavigableLink(link)) {
                return;
            }

            const url = new URL(link.href, window.location.origin);
            const key = url.pathname + url.search;
            if (prefetchCache.has(key)) {
                return;
            }

            prefetchCache.add(key);
            const hint = document.createElement('link');
            hint.rel = 'prefetch';
            hint.href = url.href;
            hint.as = 'document';
            document.head.appendChild(hint);
        };

        document.addEventListener('mouseover', function (event) {
            const link = event.target.closest('a[href]');
            prefetchLink(link);
        });

        document.addEventListener('touchstart', function (event) {
            const link = event.target.closest('a[href]');
            prefetchLink(link);
        }, { passive: true });

        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');
            if (!isInternalNavigableLink(link)) {
                return;
            }

            if (link.hasAttribute('data-no-route-loading') || link.closest('[data-no-route-loading]')) {
                return;
            }

            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            if (event.defaultPrevented) {
                return;
            }

            // Clear any active timers before starting new one
            if (overlayDelayTimeout) {
                window.clearTimeout(overlayDelayTimeout);
                overlayDelayTimeout = null;
            }

            if (!reducedMotion) {
                body.classList.add('page-transition-active', 'page-transition-leaving');
                window.setTimeout(function () {
                    body.classList.add('page-transition-finishing');
                }, 140);
                transitionTimeout = window.setTimeout(clearPageTransition, 1200);
            }

            // Show page transition loading overlay with a 150ms debounce delay
            // This prevents visual flickering for instant loads but triggers elegantly for slow queries.
            const title = isDashboardLandingLink(link) ? 'Memuat landing page' : 'Memuat halaman';
            const subtitle = isDashboardLandingLink(link)
                ? 'Menyiapkan ringkasan Area 6 dan snapshot dashboard.'
                : 'Menyiapkan tampilan berikutnya dengan data terbaru.';

            overlayDelayTimeout = window.setTimeout(function () {
                showRouteLoading(title, subtitle);
            }, 150);
        });

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
                return;
            }

            if (form.target === '_blank' || form.hasAttribute('data-no-route-loading')) {
                return;
            }

            if (overlayDelayTimeout) {
                window.clearTimeout(overlayDelayTimeout);
                overlayDelayTimeout = null;
            }

            showRouteLoading('Memproses permintaan', 'Mengirim parameter dan menyiapkan hasil terbaru.', 'page-form-submitting');
        });

        window.addEventListener('pageshow', function () {
            clearPageTransition();
            clearRouteLoading();
        });
        window.addEventListener('load', function () {
            clearPageTransition();
            clearRouteLoading();
        });
        window.addEventListener('pagehide', function () {
            clearPageTransition();
            clearRouteLoading();
        });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                clearPageTransition();
                clearRouteLoading();
            }
        });
    });
</script>

@stack('scripts')
@yield('scripts')

@if(!empty($userBranchScope))
<script>
    (function () {
        const scope = @json($userBranchScope);
        const branchFieldNames = new Set([
            'cabang',
            'cabang1',
            'kanca',
            'mismatch_cabang1',
            'wilayah',
            'branch_office',
            'kantor_cabang'
        ]);
        const branchChoiceSelector = [
            'input[type="checkbox"][name]',
            'input[type="radio"][name]',
            '.filter-branch-checkbox',
            '.dormant-branch-checkbox'
        ].join(',');
        const branchToggleSelector = [
            '#filterBranchDropdown',
            '#businessClusterBranchDropdown',
            '#dormantBranchDropdown',
            '#cabangDropdownToggle',
            '[data-loan-dropdown-toggle="kanca"]',
            '[data-loan-dropdown-toggle="cabang"]',
            '[data-dana-dropdown-toggle="cabang"]',
            '[data-daily-dropdown-toggle="kanca"]'
        ].join(',');
        const branchMenuSelector = [
            '#filterBranchMenu',
            '#businessClusterBranchMenu',
            '#dormantBranchMenu',
            '#cabangDropdownMenu',
            '[data-loan-dropdown-menu="kanca"]',
            '[data-loan-dropdown-menu="cabang"]',
            '[data-dana-dropdown-menu="cabang"]',
            '[data-daily-dropdown-menu="kanca"]'
        ].join(',');
        const branchOptionSelector = [
            '#cabangOptions .filter-single-option',
            '[data-loan-dropdown="kanca"] .loan-dropdown-option',
            '[data-loan-dropdown="cabang"] .loan-dropdown-option',
            '[data-dana-dropdown-menu="cabang"] [data-value]',
            '[data-kanca-option]'
        ].join(',');
        const normalizedTargets = [scope.label, scope.upper_label, scope.plain_label, scope.key, scope.slug, scope.code4, scope.code5]
            .map(normalizeBranchToken)
            .filter(Boolean);

        function normalizeBranchToken(value) {
            return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        }

        function isBranchField(element) {
            if (element.hasAttribute('data-user-scope-admin-control')) {
                return false;
            }

            const name = String(element.getAttribute('name') || '').replace(/\[\]$/, '');
            if (branchFieldNames.has(name)) {
                return true;
            }

            if (element.matches && element.matches('.filter-branch-checkbox, .dormant-branch-checkbox')) {
                return true;
            }

            if (!['SELECT', 'INPUT'].includes(element.tagName)) {
                return false;
            }

            const id = normalizeBranchToken([
                element.id,
                element.getAttribute('aria-label'),
                element.getAttribute('data-filter'),
                element.getAttribute('data-field')
            ].join(' '));
            return ['CABANG', 'KANCA', 'BRANCH', 'WILAYAH'].some(function (token) {
                return id.includes(token);
            });
        }

        function isScopeOption(element) {
            const token = normalizeBranchToken((element.value || '') + ' ' + (element.textContent || ''));
            return normalizedTargets.some(function (target) {
                return target.length >= 4 && token.includes(target);
            });
        }

        function lockSelect(select) {
            if (!isBranchField(select) || select.dataset.userBranchLocked === '1') {
                return;
            }

            const options = Array.from(select.options || []);
            const match = options.find(isScopeOption);
            if (!match) {
                return;
            }

            options.forEach(function (option) {
                option.selected = option === match;
                option.hidden = option !== match;
            });
            select.disabled = true;
            select.dataset.userBranchLocked = '1';
            select.classList.add('user-branch-scope-lock');
            select.title = 'Akses data dikunci untuk ' + scope.label;
            select.dispatchEvent(new Event('change', { bubbles: true }));

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(select).trigger('change.select2');
            }
        }

        function lockChoice(input) {
            if (!isBranchField(input) || input.dataset.userBranchLocked === '1') {
                return;
            }

            const label = input.id ? document.querySelector('label[for="' + CSS.escape(input.id) + '"]') : null;
            input.checked = isScopeOption({
                value: input.value,
                textContent: label ? label.textContent : ''
            });
            input.disabled = true;
            input.dataset.userBranchLocked = '1';
            input.classList.add('user-branch-scope-lock');

            const item = input.closest('label, .dropdown-item');
            if (item) {
                item.hidden = !input.checked;
            }
        }

        function lockCustomOption(option) {
            const match = isScopeOption({
                value: option.getAttribute('data-value') || option.getAttribute('data-kanca-option') || '',
                textContent: option.getAttribute('data-label') || option.textContent || ''
            });

            option.hidden = !match;
            option.setAttribute('aria-disabled', 'true');
            option.dataset.userBranchLocked = '1';
            option.classList.toggle('active', match);
            option.classList.toggle('is-active', match);
        }

        function lockBranchToggle(toggle) {
            toggle.disabled = true;
            toggle.setAttribute('aria-disabled', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.dataset.userBranchLocked = '1';
            toggle.classList.add('user-branch-scope-lock');
            toggle.title = 'Akses data dikunci untuk ' + scope.label;

            const label = toggle.querySelector([
                '.branch-dropdown-label',
                '.casa-dropdown-label',
                '.dana-dropdown-text',
                '.daily-dropdown-toggle-text',
                '.loan-dropdown-text',
                '.loan-dropdown-label'
            ].join(','));
            if (label) {
                label.textContent = scope.label;
            }

            const root = toggle.closest([
                '.branch-filter-dropdown',
                '.loan-dropdown',
                '.loan-dropdown-shell',
                '.dana-dropdown',
                '.daily-dropdown',
                '.dormant-filter-dropdown'
            ].join(','));
            if (root) {
                root.classList.remove('show', 'open', 'is-open');
                root.querySelectorAll('.show').forEach(function (element) {
                    element.classList.remove('show');
                });
            }
        }

        function closeBranchMenu(menu) {
            menu.classList.remove('show', 'open', 'is-open');
            menu.setAttribute('aria-hidden', 'true');
        }

        function lockBranchControls(root) {
            const target = root && root.querySelectorAll ? root : document;
            const elements = [];
            const nativeSelector = 'select, ' + branchChoiceSelector;
            if (target.matches && target.matches(nativeSelector)) {
                elements.push(target);
            }
            target.querySelectorAll(nativeSelector).forEach(function (element) {
                elements.push(element);
            });

            elements.forEach(function (element) {
                if (element.tagName === 'SELECT') {
                    lockSelect(element);
                } else {
                    lockChoice(element);
                }
            });

            const customOptions = [];
            if (target.matches && target.matches(branchOptionSelector)) {
                customOptions.push(target);
            }
            target.querySelectorAll(branchOptionSelector).forEach(function (option) {
                customOptions.push(option);
            });
            customOptions.forEach(lockCustomOption);

            const toggles = [];
            if (target.matches && target.matches(branchToggleSelector)) {
                toggles.push(target);
            }
            target.querySelectorAll(branchToggleSelector).forEach(function (toggle) {
                toggles.push(toggle);
            });
            toggles.forEach(lockBranchToggle);

            const menus = [];
            if (target.matches && target.matches(branchMenuSelector)) {
                menus.push(target);
            }
            target.querySelectorAll(branchMenuSelector).forEach(function (menu) {
                menus.push(menu);
            });
            menus.forEach(closeBranchMenu);

            document.querySelectorAll('#filter_branch_office_label, #businessClusterBranchLabel, #kancaLabel, #cabangDropdownLabel').forEach(function (label) {
                label.textContent = scope.label;
            });
        }

        lockBranchControls(document);
        document.addEventListener('DOMContentLoaded', function () {
            lockBranchControls(document);
        });

        document.addEventListener('click', function (event) {
            const target = event.target instanceof Element ? event.target : null;
            if (!target || (!target.closest(branchToggleSelector) && !target.closest(branchOptionSelector))) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            lockBranchControls(document);
        }, true);

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        lockBranchControls(node);
                        const parentSelect = node.closest ? node.closest('select') : null;
                        if (parentSelect) {
                            lockSelect(parentSelect);
                        }
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    })();
</script>
@endif

@stack('modals')
    @include('report.partials.floating-scrollbar')
</body>
</html>
