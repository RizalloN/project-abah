<style>
    .main-sidebar .sidebar {
        overflow-x: hidden;
        padding-top: 0.1rem;
    }

    .main-sidebar .nav-sidebar {
        gap: 0.2rem;
    }

    .main-sidebar .nav-sidebar > .nav-item {
        margin-bottom: 0.15rem;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link {
        display: flex;
        align-items: flex-start;
        width: 100%;
        min-height: 46px;
        padding: 0.72rem 0.85rem;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link p {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        flex: 1 1 auto;
        margin: 0;
        line-height: 1.35;
        white-space: normal;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link .nav-icon {
        width: 1.35rem;
        min-width: 1.35rem;
        margin-right: 0.75rem;
        font-size: 1rem;
        text-align: center;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link p .right {
        position: static;
        margin-left: auto;
        padding-top: 0.18rem;
        flex-shrink: 0;
    }

    .main-sidebar .nav-sidebar > .nav-item.menu-open > .nav-link {
        margin-bottom: 0.45rem !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview {
        margin: 0.2rem 0 0.65rem 0;
        padding: 0 0 0 1.55rem;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link {
        display: flex;
        align-items: center;
        width: 100%;
        min-height: 40px;
        margin-bottom: 0.22rem;
        padding: 0.52rem 0.9rem 0.52rem 0.65rem;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link p {
        display: block;
        margin: 0;
        line-height: 1.3;
        white-space: normal;
        word-break: keep-all;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link .nav-icon {
        width: 1rem;
        min-width: 1rem;
        margin-right: 0.85rem;
        font-size: 0.95rem;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.active {
        background: rgba(255, 255, 255, 0.14) !important;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    }

    .main-sidebar .nav-sidebar .nav-header {
        display: block !important;
        width: 100%;
        padding: 0.9rem 0.85rem 0.45rem !important;
        margin: 0;
        font-size: 0.68rem !important;
        font-weight: 600;
        line-height: 1;
        letter-spacing: 0.16em !important;
        text-align: left !important;
        text-indent: 0 !important;
        transform: none !important;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-treeview {
        padding-left: 1.25rem;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link {
        min-height: 38px;
        padding: 0.48rem 0.75rem 0.48rem 0.55rem;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link p {
        line-height: 1.15;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link .nav-icon {
        margin-right: 0.7rem;
    }

    .main-sidebar .nav-sidebar .report-digital-treeview {
        padding-left: 1.2rem;
    }

    .main-sidebar .nav-sidebar .report-digital-treeview > .nav-item > .nav-link {
        min-height: 38px;
        padding: 0.48rem 0.8rem 0.48rem 0.55rem;
    }

    .main-sidebar .nav-sidebar .report-digital-treeview > .nav-item > .nav-link p {
        line-height: 1.15;
    }

    .main-sidebar .nav-sidebar .report-digital-treeview > .nav-item > .nav-link .nav-icon {
        margin-right: 0.7rem;
    }

    .main-sidebar .nav-sidebar .report-digital-treeview .submenu-single-line p {
        white-space: nowrap;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .report-digital-treeview {
        padding-left: 1.05rem;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .report-digital-treeview > .nav-item > .nav-link {
        min-height: 36px;
        padding: 0.44rem 0.72rem 0.44rem 0.5rem;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .report-digital-treeview .submenu-single-line p {
        white-space: nowrap;
    }
</style>

<aside class="main-sidebar elevation-4" style="background: linear-gradient(180deg, #020617 0%, #0f172a 32%, #134e4a 100%);">

    <a href="{{ route('dashboard') }}" class="brand-link border-0 py-4 px-3 sidebar-brand-link" style="background: rgba(255, 255, 255, 0.04);">
        <div class="d-flex align-items-center justify-content-center sidebar-brand-inner">
            <span class="d-inline-flex align-items-center justify-content-center font-weight-bold text-white mr-3 sidebar-brand-badge" style="width: 42px; height: 42px; border-radius: 14px; background: linear-gradient(135deg, rgba(45, 212, 191, 0.28), rgba(255, 255, 255, 0.14)); border: 1px solid rgba(255, 255, 255, 0.12);">
                DB
            </span>
            <div class="brand-text sidebar-brand-text">
                <div class="text-white font-weight-bold" style="font-size: 1rem; letter-spacing: 0.03em;">DigiBranch</div>
                <div class="text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.18em; color: rgba(226, 232, 240, 0.72);">Area 6 Portal</div>
            </div>
        </div>
    </a>

    <div class="sidebar px-2 pb-3">

        <div class="user-panel mt-3 mb-4 p-3 sidebar-user-panel" style="border-radius: 18px; background: rgba(255, 255, 255, 0.07); border: 1px solid rgba(255, 255, 255, 0.08);">
            <div class="d-flex align-items-center justify-content-center sidebar-user-inner">
                <div class="image mr-3 d-inline-flex align-items-center justify-content-center font-weight-bold text-white sidebar-user-avatar" style="width: 46px; height: 46px; border-radius: 16px; background: linear-gradient(135deg, rgba(45, 212, 191, 0.35), rgba(15, 23, 42, 0.35));">
                    {{ strtoupper(substr(Auth::user()?->name ?? 'U', 0, 2)) }}
                </div>
                <div class="info text-white sidebar-user-info">
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
                        <p>Dashboard Simpanan</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('report.dashboard-pinjaman') }}"
                       class="nav-link {{ request()->routeIs('report.dashboard-pinjaman') ? 'active' : '' }}"
                       style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <p>Dashboard Pinjaman</p>
                    </a>
                </li>

               
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
                    
                    <ul class="nav nav-treeview report-digital-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.edc') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.edc') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Performance EDC</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.qris') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.qris') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
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
                            <a href="{{ route('report.brimo') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.brimo') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-primary"></i>
                                <p>Performance BRImo</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.brilink') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.brilink') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-warning"></i>
                                <p>Performance Brilink</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link submenu-single-line" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
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
                        <li class="nav-item">
                            <a href="{{ route('report.rekening-dormant') }}" class="nav-link {{ request()->routeIs('report.rekening-dormant') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-warning"></i>
                                <p>Rekening Dormant</p>
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
                            <a href="{{ route('report.kinerja.newpayroll') }}" class="nav-link {{ request()->routeIs('report.kinerja.newpayroll') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Kinerja New Payroll</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->is('report/kolaborasi-perusahaan-anak*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/kolaborasi-perusahaan-anak*') ? 'active' : '' }}" style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-handshake"></i>
                        <p>
                            Kolaborasi Perusahaan Anak
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.kolaborasi.referral') }}" class="nav-link {{ request()->routeIs('report.kolaborasi.referral') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-info"></i>
                                <p>Program Referral Partner Perusahaan Anak</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->routeIs('report.kolaborasi.bodboc') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.kolaborasi.bodboc') ? 'active' : '' }}" style="border-radius: 14px; margin-bottom: 0.35rem; color: rgba(226, 232, 240, 0.88);">
                        <i class="nav-icon fas fa-user-tie"></i>
                        <p>
                            Optimalisasi Nasabah Prioritas BOD/BOC Nasabah Wholesale dan Komersial
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.kolaborasi.bodboc') }}" class="nav-link {{ request()->routeIs('report.kolaborasi.bodboc') ? 'active' : '' }}" style="border-radius: 12px; color: rgba(226, 232, 240, 0.8);">
                                <i class="far fa-circle nav-icon text-warning"></i>
                                <p>Nasabah Prioritas BOD/BOC</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

    </div>
</aside>
