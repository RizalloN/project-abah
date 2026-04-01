<aside class="main-sidebar elevation-4" style="background: linear-gradient(180deg, #020617 0%, #0f172a 32%, #134e4a 100%);">

    <a href="{{ route('dashboard') }}" class="brand-link border-0 py-4 px-3" style="background: rgba(255, 255, 255, 0.04);">
        <div class="d-flex align-items-center">
            <span class="d-inline-flex align-items-center justify-content-center font-weight-bold text-white mr-3" style="width: 42px; height: 42px; border-radius: 14px; background: linear-gradient(135deg, rgba(45, 212, 191, 0.28), rgba(255, 255, 255, 0.14)); border: 1px solid rgba(255, 255, 255, 0.12);">
                DB
            </span>
            <div>
                <div class="text-white font-weight-bold" style="font-size: 1rem; letter-spacing: 0.03em;">DigiBranch</div>
                <div class="text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.18em; color: rgba(226, 232, 240, 0.72);">Area 6 Portal</div>
            </div>
        </div>
    </a>

    <div class="sidebar px-2 pb-3">

        <div class="mt-3 mb-4 p-3" style="border-radius: 18px; background: rgba(255, 255, 255, 0.07); border: 1px solid rgba(255, 255, 255, 0.08);">
            <div class="d-flex align-items-center">
                <div class="mr-3 d-inline-flex align-items-center justify-content-center font-weight-bold text-white" style="width: 46px; height: 46px; border-radius: 16px; background: linear-gradient(135deg, rgba(45, 212, 191, 0.35), rgba(15, 23, 42, 0.35));">
                    {{ strtoupper(substr(Auth::user()?->name ?? 'U', 0, 2)) }}
                </div>
                <div class="text-white">
                    <div class="font-weight-bold" style="font-size: 0.96rem;">{{ Auth::user()?->name }}</div>
                    <div style="font-size: 0.78rem; color: rgba(226, 232, 240, 0.72);">{{ Auth::user()?->pn }}</div>
                </div>
            </div>
        </div>

        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                @if(Auth::user()?->isAdmin())
                <li class="nav-header text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.16em; color: rgba(148, 163, 184, 0.78); padding-left: 0.75rem;">Master Data</li>

                <li class="nav-item">
                    <a href="#" class="nav-link" style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-database"></i>
                        <p>
                            Nama Report
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                </li>
                @endif

                @if(Auth::user()?->isAdmin())
                <li class="nav-header text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.16em; color: rgba(148, 163, 184, 0.78); padding-left: 0.75rem;">Import</li>

                <li class="nav-item">
                    <a href="{{ route('import.index') }}"
                       class="nav-link {{ request()->routeIs('import.*') ? 'active' : '' }}"
                       style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-upload"></i>
                        <p>Import Data</p>
                    </a>
                </li>
                @endif

                <li class="nav-header text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.16em; color: rgba(148, 163, 184, 0.78); padding-left: 0.75rem;">Report</li>

                <li class="nav-item {{ request()->is('report/optimalisasi-digital*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/optimalisasi-digital*') ? 'active' : '' }}" style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Optimalisasi Digital Channel
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.edc') }}" class="nav-link {{ request()->routeIs('report.edc') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Performance EDC</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.qris') }}" class="nav-link {{ request()->routeIs('report.qris') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-success"></i>
                                <p>Performance QRIS</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Perform. CASA Merchant</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.brimo') }}" class="nav-link {{ request()->routeIs('report.brimo') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-primary"></i>
                                <p>Performance BRImo</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.brilink') }}" class="nav-link {{ request()->routeIs('report.brilink') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-warning"></i>
                                <p>Performance Brilink</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance Qlola</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->is('report/rekening-transaksi-debitur*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/rekening-transaksi-debitur*') ? 'active' : '' }}" style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-wallet"></i>
                        <p>
                            Rekening Transaksi Debitur
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.rasiocasa.debitur') }}" class="nav-link {{ request()->routeIs('report.rasiocasa.debitur') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Rasio CASA Debitur</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->is('report/peningkatan-payroll-berkualitas*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/peningkatan-payroll-berkualitas*') ? 'active' : '' }}" style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-money-check-alt"></i>
                        <p>
                            Peningkatan Payroll Berkualitas
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="#" class="nav-link" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                            <a href="#" class="nav-link" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Kinerja New Payroll</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

    </div>
</aside>
