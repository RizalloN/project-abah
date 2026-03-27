<aside class="main-sidebar sidebar-dark-primary elevation-4">

    <!-- 🔥 BRAND -->
    <a href="{{ route('dashboard') }}" class="brand-link">
        <span class="brand-text font-weight-light ml-3">Project ABAH</span>
    </a>

    <!-- 🔥 SIDEBAR -->
    <div class="sidebar">

        <!-- 🔥 USER PANEL (optional tapi keren) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <i class="fas fa-user-circle fa-2x text-white"></i>
            </div>
            <div class="info">
                <a href="#" class="d-block">
                    {{ Auth::user()->pn }} <br>
                    <small>{{ Auth::user()->name }}</small>
                </a>
            </div>
        </div>

        <!-- 🔥 MENU -->
        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">

                <!-- ===================== -->
                <!-- DASHBOARD -->
                <!-- ===================== -->
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- ===================== -->
                <!-- MASTER DATA -->
                <!-- ===================== -->
                @if(Auth::user()->isAdmin())
                <li class="nav-header">MASTER DATA</li>

                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-database"></i>
                        <p>
                            Nama Report
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                </li>
                @endif

                <!-- ===================== -->
                <!-- IMPORT DATA -->
                <!-- ===================== -->
                @if(Auth::user()->isAdmin())
                <li class="nav-header">IMPORT</li>

                <li class="nav-item">
                    <a href="{{ route('import.index') }}"
                       class="nav-link {{ request()->routeIs('import.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-upload"></i>
                        <p>Import Data</p>
                    </a>
                </li>
                @endif

                <!-- ===================== -->
                <!-- REPORT DATA -->
                <!-- ===================== -->
                <li class="nav-header">REPORT</li>

                <li class="nav-item {{ request()->is('report/optimalisasi-digital*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/optimalisasi-digital*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            1. Optimalisasi Digital
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.edc') }}" class="nav-link {{ request()->routeIs('report.edc') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Performance EDC</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance Jml QRIS</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p> CASA Merchant</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance BRImo</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance Brilink</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance Qlola</p>
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>
        </nav>

    </div>
</aside>