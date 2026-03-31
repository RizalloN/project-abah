<aside class="main-sidebar sidebar-dark-primary elevation-4">

    <a href="{{ route('dashboard') }}" class="brand-link">
        <span class="brand-text font-weight-light ml-3">Project ABAH</span>
    </a>

    <div class="sidebar">

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

        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

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

                <li class="nav-header">REPORT</li>

                <li class="nav-item {{ request()->is('report/optimalisasi-digital*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/optimalisasi-digital*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            1. Optimalisasi Digital Channel
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
                            <a href="{{ route('report.qris') }}" class="nav-link {{ request()->routeIs('report.qris') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon text-success"></i>
                                <p>Performance QRIS</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Perform. CASA Merchant</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.brimo') }}" class="nav-link {{ request()->routeIs('report.brimo') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon text-primary"></i>
                                <p>Performance BRImo</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.brilink') }}" class="nav-link {{ request()->routeIs('report.brilink') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon text-warning"></i>
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

                <li class="nav-item {{ request()->is('report/rekening-transaksi-debitur*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/rekening-transaksi-debitur*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-wallet"></i>
                        <p>
                            2. Rekening Transaksi Debitur
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.rasiocasa.debitur') }}" class="nav-link {{ request()->routeIs('report.rasiocasa.debitur') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Rasio CASA Debitur</p>
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>
        </nav>

    </div>
</aside>