<!DOCTYPE html>
<html>
<head>
    <title>Project ABAH</title>

    <!-- Responsive -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="{{ asset('adminlte/dist/css/adminlte.min.css') }}">
</head>

<body class="hold-transition sidebar-mini layout-fixed">

<div class="wrapper">

    <!-- 🔥 NAVBAR -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">

        <!-- Hamburger -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <!-- Right -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link">
                    {{ Auth::user()->pn }} - {{ Auth::user()->name }}
                </span>
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