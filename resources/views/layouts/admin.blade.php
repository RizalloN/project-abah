<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>

    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="{{ asset('adminlte/dist/css/adminlte.min.css') }}">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
</head>

<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- 🔥 NAVBAR -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">

        <!-- ☰ HAMBURGER -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <!-- 👤 PROFIL -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#">
                    
                    <!-- ICON -->
                    <i class="fas fa-user-circle mr-2"></i>

                    <!-- PN + NAMA -->
                    <span>
                        {{ Auth::user()->pn }} - {{ Auth::user()->name }}
                    </span>
                </a>

                <!-- DROPDOWN -->
                <div class="dropdown-menu dropdown-menu-right">

                    <a href="#" class="dropdown-item">
                        <i class="fas fa-key mr-2"></i> Ubah Password
                    </a>

                    <div class="dropdown-divider"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </button>
                    </form>

                </div>
            </li>
        </ul>
    </nav>

    <!-- 🔥 SIDEBAR -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="#" class="brand-link">
            <span class="brand-text font-weight-light ml-3">Project ABAH</span>
        </a>

        <div class="sidebar">
            <nav>
                <ul class="nav nav-pills nav-sidebar flex-column">

                    <li class="nav-item">
                        <a href="/dashboard" class="nav-link">
                            <i class="nav-icon fas fa-home"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                </ul>
            </nav>
        </div>
    </aside>

    <!-- 🔥 CONTENT -->
    <div class="content-wrapper p-3">
        @yield('content')
    </div>

</div>

<!-- jQuery -->
<script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>

<!-- Bootstrap -->
<script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

<!-- AdminLTE -->
<script src="{{ asset('adminlte/dist/js/adminlte.min.js') }}"></script>

</body>
</html>