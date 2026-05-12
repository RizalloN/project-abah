<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        }

        body {
            font-family: "Inter", "Segoe UI", sans-serif;
            color: #0f172a;
            background: var(--bri-mist);
        }

        .main-header.modern-navbar {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(8, 87, 195, 0.14);
            box-shadow: 0 10px 28px -24px rgba(8, 87, 195, 0.45);
        }

        .modern-navbar .nav-link {
            color: var(--bri-ink);
        }

        .modern-navbar .menu-toggle {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s ease;
        }

        .modern-navbar .menu-toggle:hover {
            background: rgba(48, 127, 226, 0.12);
            color: var(--bri-nusantara);
        }

        .modern-user-trigger {
            border-radius: 18px;
            padding: 0.4rem 0.75rem;
            border: 1px solid rgba(8, 87, 195, 0.16);
            background: var(--bri-white);
            box-shadow: 0 12px 28px -24px rgba(8, 87, 195, 0.45);
        }

        .modern-user-trigger:hover {
            background: #f7fbff;
            color: #082f66;
        }

        .modern-user-badge {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(140deg, var(--bri-nusantara), var(--bri-cakrawala));
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
        }

        .modern-user-menu {
            min-width: 220px;
            border: 1px solid rgba(8, 87, 195, 0.16);
            border-radius: 16px;
            padding: 0.5rem;
            box-shadow: 0 18px 38px -24px rgba(8, 87, 195, 0.32);
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
            box-shadow: 0 18px 34px -28px rgba(4, 42, 95, 0.28);
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
            box-shadow: 0 14px 24px -20px rgba(4, 42, 95, 0.45);
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
            background: linear-gradient(120deg, rgba(113, 197, 232, 0.36), rgba(48, 127, 226, 0.5)) !important;
            color: #ffffff !important;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .main-sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff !important;
        }

        .main-sidebar .nav-treeview > .nav-item > .nav-link.active {
            background: rgba(113, 197, 232, 0.2) !important;
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
        
        .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .brand-link {
            padding: 0.8rem 0.5rem !important;
            justify-content: center !important;
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

        /* Hovering over collapsed sidebar (Expands it) */
        .sidebar-mini.sidebar-collapse .main-sidebar:hover,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused {
            width: 270px !important;
            box-shadow: 18px 0 38px -24px rgba(4, 42, 95, 0.56) !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-brand-text,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-brand-text,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-info,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-info,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar p,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-sidebar p {
            display: flex !important;
            animation: fadeIn 0.3s ease-in-out;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar .right,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-sidebar .right,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-header,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-header {
            display: block !important;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .brand-link,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .brand-link {
            justify-content: flex-start !important;
            padding: 1rem 0.75rem !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-panel,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-panel {
            padding: 0.75rem !important;
            justify-content: flex-start !important;
            width: 100%;
            margin-left: 0;
            margin-right: 0;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar > .nav-item > .nav-link,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-sidebar > .nav-item > .nav-link {
            padding: 0.65rem 0.75rem !important;
            justify-content: flex-start !important;
            width: 100%;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-brand-badge,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-brand-badge,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-avatar,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-avatar,
        .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-icon,
        .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-icon {
            margin-right: 0.75rem !important;
        }


        .content-wrapper,
        .content-wrapper .content,
        .content-wrapper .container-fluid {
            transition: opacity 180ms ease, transform 220ms ease;
            will-change: opacity, transform;
        }

        body.page-transition-leaving .content-wrapper,
        body.page-transition-leaving .content-wrapper .content,
        body.page-transition-leaving .content-wrapper .container-fluid {
            opacity: 0;
            transform: translateY(4px);
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

        @media (prefers-reduced-motion: reduce) {
            .content-wrapper,
            .content-wrapper .content,
            .content-wrapper .container-fluid,
            .page-transition-bar {
                transition: none !important;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="page-transition-bar" aria-hidden="true"></div>

    <nav class="main-header modern-navbar navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link menu-toggle" data-widget="pushmenu" href="#" role="button" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link modern-user-trigger d-flex align-items-center" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                    <span class="modern-user-badge mr-3">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</span>
                    <span class="d-none d-sm-block">
                        <span class="d-block font-weight-bold" style="font-size: 0.92rem; line-height: 1.05; color: #0b3b80;">{{ Auth::user()->pn }} - {{ Auth::user()->name }}</span>
                        <span class="d-block text-uppercase" style="font-size: 0.62rem; letter-spacing: 0.16em; color: #5b7da7;">A-Six Account</span>
                    </span>
                    <i class="fas fa-chevron-down ml-3" style="font-size: 0.75rem; color: #4f72a0;"></i>
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
        const body = document.body;
        const prefetchCache = new Set();
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const shouldPrefetch = document.documentElement.dataset.prefetchDocuments === 'true';
        let transitionTimeout = null;

        const clearPageTransition = function () {
            if (transitionTimeout) {
                window.clearTimeout(transitionTimeout);
                transitionTimeout = null;
            }

            body.classList.remove('page-transition-active', 'page-transition-leaving', 'page-transition-finishing');
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

            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            if (event.defaultPrevented) {
                return;
            }

            if (!reducedMotion) {
                body.classList.add('page-transition-active', 'page-transition-leaving');
                window.setTimeout(function () {
                    body.classList.add('page-transition-finishing');
                }, 140);
                transitionTimeout = window.setTimeout(clearPageTransition, 1200);
            }
        }, true);

        window.addEventListener('pageshow', clearPageTransition);
        window.addEventListener('pagehide', clearPageTransition);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                clearPageTransition();
            }
        });
    });
</script>

@stack('scripts')
@yield('scripts')

@stack('modals')
    @include('report.partials.floating-scrollbar')
</body>
</html>
