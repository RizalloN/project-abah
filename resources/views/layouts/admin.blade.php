<!DOCTYPE html>
<html>
<head>
    <title>Project ABAH</title>

    <!-- Responsive -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="{{ asset('adminlte/dist/css/adminlte.min.css') }}">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .main-header.modern-navbar {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 10px 35px -24px rgba(15, 23, 42, 0.28);
        }

        .modern-navbar .nav-link {
            color: #334155;
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
            background: #f1f5f9;
            color: #0f172a;
        }

        .modern-user-trigger {
            border-radius: 18px;
            padding: 0.4rem 0.75rem;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.5);
        }

        .modern-user-trigger:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .modern-user-badge {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dcfce7, #ccfbf1);
            color: #0f766e;
            font-weight: 700;
            text-transform: uppercase;
        }

        .modern-user-menu {
            min-width: 220px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 18px;
            padding: 0.5rem;
            box-shadow: 0 20px 45px -25px rgba(15, 23, 42, 0.35);
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
            background: linear-gradient(180deg, #f8fafc 0%, #eef4f7 100%);
        }

        .content-header {
            padding-top: 1.15rem;
            padding-bottom: 0.35rem;
        }

        .content-header h3 {
            font-size: 1.65rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0;
        }

        .card {
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 1.15rem;
            box-shadow: 0 18px 40px -30px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }

        .card-header {
            border-bottom: 1px solid rgba(226, 232, 240, 0.75);
        }

        .card-title {
            font-weight: 700;
        }

        .card-outline.card-primary,
        .card-outline.card-success,
        .card-outline.card-warning {
            border-top-width: 0;
        }

        .btn {
            border-radius: 0.9rem;
            font-weight: 700;
            padding: 0.7rem 1rem;
            box-shadow: 0 14px 26px -20px rgba(15, 23, 42, 0.45);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f766e, #115e59);
            border-color: #0f766e;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: linear-gradient(135deg, #0d5f59, #134e4a);
            border-color: #0d5f59;
        }

        .btn-success {
            background: linear-gradient(135deg, #15803d, #166534);
            border-color: #15803d;
        }

        .btn-light,
        .badge-light {
            background: #f8fafc !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0;
        }

        .form-control,
        .custom-file-label,
        .select2-container--default .select2-selection--single {
            min-height: calc(2.4rem + 2px);
            border-radius: 0.95rem !important;
            border-color: #dbe4ee !important;
            box-shadow: none !important;
        }

        .form-control:focus,
        .custom-file-input:focus ~ .custom-file-label,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #0f766e !important;
            box-shadow: 0 0 0 0.2rem rgba(15, 118, 110, 0.14) !important;
        }

        .custom-file-label {
            padding-top: 0.72rem;
            color: #64748b;
        }

        .custom-file-label::after {
            height: calc(2.4rem + 0px);
            border-radius: 0 0.95rem 0.95rem 0;
            background: #f8fafc;
            color: #0f172a;
            padding-top: 0.72rem;
        }

        .table {
            color: #1e293b;
        }

        .table thead th {
            border-bottom-width: 1px;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(15, 118, 110, 0.06) !important;
        }

        .main-sidebar .nav-link.active {
            background: linear-gradient(135deg, rgba(45, 212, 191, 0.28), rgba(15, 23, 42, 0.18)) !important;
            color: #ffffff !important;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .main-sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff !important;
        }

        .main-sidebar .nav-treeview > .nav-item > .nav-link.active {
            background: rgba(255, 255, 255, 0.12) !important;
        }

        .sidebar-mini .main-sidebar .nav-link {
            transition: padding 0.2s ease, margin 0.2s ease, background-color 0.2s ease;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .brand-link.sidebar-brand-link {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 0.5rem !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-brand-inner,
        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-user-inner {
            justify-content: center !important;
            width: 100%;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-brand-badge,
        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-user-avatar {
            margin-right: 0 !important;
            flex-shrink: 0;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-brand-text,
        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-user-info,
        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-header,
        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-treeview,
        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .right {
            display: none !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar {
            padding-left: 0.45rem !important;
            padding-right: 0.45rem !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-user-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 0.35rem !important;
            margin-top: 0.75rem !important;
            margin-bottom: 1rem !important;
            border-radius: 18px;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-user-avatar {
            width: 42px !important;
            height: 42px !important;
            border-radius: 14px !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar > .nav-item {
            width: 100%;
            margin-bottom: 0 !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar > .nav-item > .nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 46px;
            margin-bottom: 0 !important;
            padding: 0.65rem 0.5rem !important;
            border-radius: 16px !important;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-icon {
            width: auto !important;
            margin: 0 !important;
            font-size: 1.05rem;
        }

        .sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar > .nav-item > .nav-link p {
            display: none !important;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">

<div class="wrapper">

    <!-- 🔥 NAVBAR -->
    <nav class="main-header modern-navbar navbar navbar-expand navbar-white navbar-light">

        <!-- Hamburger -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link menu-toggle" data-widget="pushmenu" href="#">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <!-- Right -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link modern-user-trigger d-flex align-items-center" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                    <span class="modern-user-badge mr-3">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</span>
                    <span class="d-none d-sm-block">
                        <span class="d-block font-weight-bold" style="font-size: 0.92rem; line-height: 1.05;">{{ Auth::user()->pn }} - {{ Auth::user()->name }}</span>
                        <span class="d-block text-uppercase" style="font-size: 0.62rem; letter-spacing: 0.16em; color: #94a3b8;">Account Center</span>
                    </span>
                    <i class="fas fa-chevron-down ml-3" style="font-size: 0.75rem; color: #64748b;"></i>
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

    <!-- 🔥 SIDEBAR -->
    @include('layouts.sidebar')

    <!-- 🔥 CONTENT WRAPPER -->
    <div class="content-wrapper">

        <!-- HEADER -->
        <div class="content-header">
            <div class="container-fluid">
                <h3>@yield('title')</h3>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>

    </div>

</div>

<!-- jQuery -->
<script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>

<!-- Bootstrap -->
<script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

<!-- AdminLTE -->
<script src="{{ asset('adminlte/dist/js/adminlte.min.js') }}"></script>

@yield('scripts')

</body>
</html>
