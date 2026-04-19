<style>
    .main-sidebar {
        background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
        border-right: 1px solid rgba(8, 87, 195, 0.14);
        overflow-x: hidden !important;
    }

    .main-sidebar .sidebar {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -ms-overflow-style: none;
        scrollbar-width: none;
        scrollbar-gutter: stable;
        padding-top: 0.1rem;
        padding-bottom: 6rem !important; /* Fix for Windows taskbar cutoff */
    }

    .main-sidebar .os-host,
    .main-sidebar .os-padding,
    .main-sidebar .os-viewport,
    .main-sidebar nav,
    .main-sidebar .nav-sidebar {
        overflow-x: hidden !important;
    }

    .main-sidebar .sidebar::-webkit-scrollbar {
        width: 0;
        height: 0;
    }

    .main-sidebar .nav-sidebar {
        gap: 0.22rem;
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
        border-radius: 13px;
        margin-bottom: 0.35rem;
        color: #0b3b80;
        border: 1px solid transparent;
        position: relative;
        overflow: hidden;
        transition: all 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(108deg, rgba(255, 255, 255, 0.45) 0%, rgba(255, 255, 255, 0) 55%);
        opacity: 0;
        transition: opacity 220ms ease;
        pointer-events: none;
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
        color: #1f63ba;
        transition: transform 0.25s var(--ease-out-back);
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link p .right {
        position: static;
        margin-left: auto;
        padding-top: 0.18rem;
        flex-shrink: 0;
        transition: transform 0.25s var(--ease-out-back);
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover {
        background: linear-gradient(125deg, #0857c3 0%, #307fe2 100%);
        border-color: rgba(8, 87, 195, 0.72);
        color: #ffffff;
        transform: translateX(4px);
        box-shadow: 0 14px 24px -16px rgba(4, 42, 95, 0.76);
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover .nav-icon {
        transform: scale(1.18) translateY(-1px);
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover p .right {
        transform: translateX(2px);
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover::after {
        opacity: 0.72;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover .nav-icon,
    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover p,
    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover .right {
        color: #ffffff !important;
    }

    .main-sidebar .nav-sidebar > .nav-item.menu-open > .nav-link {
        margin-bottom: 0.45rem !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview {
        margin: 0.2rem 0 0.65rem 0;
        padding: 0 0 0 1.45rem;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link {
        display: flex;
        align-items: center;
        width: 100%;
        min-height: 38px;
        margin-bottom: 0.22rem;
        padding: 0.52rem 0.8rem 0.52rem 0.6rem;
        border-radius: 11px;
        color: #2f5e95;
        transition: all 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
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
        margin-right: 0.75rem;
        font-size: 0.9rem;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link:hover {
        background: linear-gradient(125deg, #0857c3 0%, #307fe2 100%);
        color: #ffffff;
        transform: translateX(2px);
        box-shadow: 0 12px 20px -16px rgba(4, 42, 95, 0.72);
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link:hover .nav-icon,
    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link:hover p {
        color: #ffffff !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.active {
        background: linear-gradient(125deg, #0857c3 0%, #307fe2 100%) !important;
        box-shadow: 0 12px 20px -16px rgba(4, 42, 95, 0.72);
        color: #ffffff;
        border-color: rgba(8, 87, 195, 0.72) !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.active .nav-icon,
    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.active p {
        color: #ffffff !important;
    }

    .main-sidebar .nav-sidebar .nav-header {
        display: block !important;
        width: 100%;
        padding: 0.9rem 0.85rem 0.45rem !important;
        margin: 0;
        font-size: 0.68rem !important;
        font-weight: 700;
        line-height: 1;
        letter-spacing: 0.16em !important;
        text-align: left !important;
        color: #507aa6 !important;
    }

    .sidebar-brand-link {
        background: linear-gradient(135deg, #053b82 0%, #0857c3 58%, #307fe2 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.16);
    }

    .sidebar-brand-badge {
        width: 74px;
        height: 56px;
        border-radius: 18px;
        padding: 5px;
        background: rgba(255, 255, 255, 0.06);
        border: 1.8px solid rgba(255, 255, 255, 0.58);
        color: #ffffff;
        font-weight: 800;
        overflow: hidden;
        box-shadow: 0 12px 24px -14px rgba(0, 26, 78, 0.7);
    }

    .sidebar-brand-mark {
        width: 100%;
        height: 100%;
        border-radius: 12px;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        overflow: hidden;
    }

    .sidebar-brand-badge img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        transform: scale(1.02);
        transform-origin: center;
    }

    .sidebar-brand-fallback {
        display: none;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        font-size: 0.74rem;
        font-weight: 800;
        color: #0857c3;
        letter-spacing: 0.06em;
    }

    .sidebar-brand-text .title {
        color: #ffffff;
        font-size: 1.08rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        line-height: 1.05;
        text-transform: uppercase;
    }

    .sidebar-brand-text .subtitle {
        font-size: 0.62rem;
        letter-spacing: 0.28em;
        color: rgba(221, 238, 255, 0.88);
        text-transform: uppercase;
        margin-top: 0.2rem;
    }

    .sidebar-user-panel {
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid rgba(8, 87, 195, 0.14);
        box-shadow: 0 12px 20px -18px rgba(4, 42, 95, 0.4);
    }

    .sidebar-user-avatar {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: linear-gradient(140deg, #0857c3, #307fe2);
        color: #ffffff;
    }

    .sidebar-user-info .name {
        font-size: 0.95rem;
        font-weight: 700;
        color: #053b82;
    }

    .sidebar-user-info .pn {
        font-size: 0.78rem;
        color: #5378a0;
    }

    .main-sidebar .nav-sidebar .report-digital-treeview .submenu-single-line p {
        white-space: nowrap;
    }



    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .nav-treeview {
        padding-left: 1.1rem;
    }

    body.sidebar-hover-open.sidebar-mini.sidebar-collapse .main-sidebar .nav-sidebar .report-digital-treeview {
        padding-left: 1.05rem;
    }



    .main-sidebar .nav-link.active {
        background: linear-gradient(125deg, #0857c3 0%, #307fe2 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 14px 24px -16px rgba(4, 42, 95, 0.76);
        border-color: rgba(8, 87, 195, 0.75) !important;
    }

    .main-sidebar .nav-link.active .nav-icon,
    .main-sidebar .nav-link.active p,
    .main-sidebar .nav-link.active .right {
        color: #ffffff !important;
    }

    .sidebar-job-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.5rem;
        height: 1.5rem;
        padding: 0 .42rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        color: #fff;
        font-size: .69rem;
        font-weight: 800;
        line-height: 1;
        box-shadow: 0 12px 22px -16px rgba(127, 29, 29, .72);
    }

    .main-sidebar .nav-link:hover .sidebar-job-badge,
    .main-sidebar .nav-link.active .sidebar-job-badge {
        background: rgba(255,255,255,.18);
        color: #fff;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.2);
    }
</style>

<aside class="main-sidebar elevation-4">
    <a href="{{ route('dashboard') }}" class="brand-link border-0 py-4 px-3 sidebar-brand-link">
        <div class="d-flex align-items-center justify-content-center sidebar-brand-inner">
            <span class="d-inline-flex align-items-center justify-content-center mr-3 sidebar-brand-badge">
                <span class="sidebar-brand-mark">
                    <img
                        src="{{ asset('images/a-six-logo.svg') }}"
                        alt="Logo A-Six"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                    >
                    <span class="sidebar-brand-fallback">ASIX</span>
                </span>
            </span>
            <div class="brand-text sidebar-brand-text">
                <div class="title">A-Six</div>
                <div class="subtitle">Dashboard Portal</div>
            </div>
        </div>
    </a>

    <div class="sidebar px-2 pb-3">
        <div class="user-panel mt-3 mb-4 p-3 sidebar-user-panel">
            <div class="d-flex align-items-center justify-content-center sidebar-user-inner">
                <div class="image mr-3 d-inline-flex align-items-center justify-content-center font-weight-bold sidebar-user-avatar">
                    {{ strtoupper(substr(Auth::user()?->name ?? 'U', 0, 2)) }}
                </div>
                <div class="info sidebar-user-info">
                    <div class="name">{{ Auth::user()?->name }}</div>
                    <div class="pn">{{ Auth::user()?->pn }}</div>
                </div>
            </div>
        </div>

        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="{{ route('dashboard.harian') }}" class="nav-link {{ request()->routeIs('dashboard.harian') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-calendar-day"></i>
                        <p>Dashboard Harian</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard Simpanan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('report.dashboard-pinjaman', 'report.dashboard-pinjaman.matrix', 'report.dashboard-pinjaman.kolek-tidak-sesuai', 'report.dashboard-pinjaman.kejar-laba', 'report.dashboard-pinjaman.filters', 'report.dashboard-pinjaman.data', 'report.dashboard-pinjaman.kolek-tidak-sesuai.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman', 'report.dashboard-pinjaman.matrix', 'report.dashboard-pinjaman.kolek-tidak-sesuai', 'report.dashboard-pinjaman.kejar-laba', 'report.dashboard-pinjaman.filters', 'report.dashboard-pinjaman.data', 'report.dashboard-pinjaman.kolek-tidak-sesuai.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <p>
                            Dashboard Pinjaman
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.matrix') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.matrix') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Matrix Pergeseran Kolek</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.kolek-tidak-sesuai') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Kolek Tidak Sesuai</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.kejar-laba') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.kejar-laba') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Report Kejar Laba</p>
                            </a>
                        </li>
                    </ul>
                </li>

                @if(Auth::user()?->isAdmin())
                <li class="nav-header text-uppercase">MANAGEMENT</li>

                <li class="nav-item {{ request()->routeIs('job-management.*', 'file-management.*', 'user-management.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('job-management.*', 'file-management.*', 'user-management.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>
                            Management
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('job-management.index') }}" class="nav-link {{ request()->routeIs('job-management.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-tasks"></i>
                                <p>
                                    Job Management
                                    <span
                                        id="sidebar-job-badge"
                                        class="sidebar-job-badge {{ ($activeImportJobCount ?? 0) > 0 ? '' : 'd-none' }}"
                                        data-fetch-url="{{ route('job-management.data') }}"
                                    >{{ ($activeImportJobCount ?? 0) > 99 ? '99+' : ($activeImportJobCount ?? 0) }}</span>
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('file-management.index') }}" class="nav-link {{ request()->routeIs('file-management.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-folder-open"></i>
                                <p>File Management</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('user-management.index') }}" class="nav-link {{ request()->routeIs('user-management.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-users-cog"></i>
                                <p>User Management</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->routeIs('import.*', 'report-management.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('import.*', 'report-management.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Report
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('import.index') }}" class="nav-link {{ request()->routeIs('import.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-upload"></i>
                                <p>Import Data</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report-management.index') }}" class="nav-link {{ request()->routeIs('report-management.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-database"></i>
                                <p>Kelola Report</p>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif

                <li class="nav-header text-uppercase">REPORT</li>

                <li class="nav-item {{ request()->is('report/optimalisasi-digital*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/optimalisasi-digital*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Optimalisasi Digital Channel
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview report-digital-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.edc') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.edc') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance EDC</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.qris') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.qris') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
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
                            <a href="{{ route('report.brimo') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.brimo') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance BRImo</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.brilink') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.brilink') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Performance Brilink</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link submenu-single-line">
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
                            Rekening Transaksi Debitur
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.rasiocasa.debitur') }}" class="nav-link {{ request()->routeIs('report.rasiocasa.debitur') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Rasio CASA Debitur</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.rekening-dormant') }}" class="nav-link {{ request()->routeIs('report.rekening-dormant') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Rekening Dormant</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->is('report/peningkatan-payroll-berkualitas*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/peningkatan-payroll-berkualitas*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-money-check-alt"></i>
                        <p>
                            Peningkatan Payroll Berkualitas
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.kinerja.newpayroll') }}" class="nav-link {{ request()->routeIs('report.kinerja.newpayroll') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Kinerja New Payroll</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->is('report/kolaborasi-perusahaan-anak*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('report/kolaborasi-perusahaan-anak*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-handshake"></i>
                        <p>
                            Kolaborasi Perusahaan Anak
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.kolaborasi.referral') }}" class="nav-link {{ request()->routeIs('report.kolaborasi.referral') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Program Referral Partner Perusahaan Anak</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->routeIs('report.kolaborasi.bodboc') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.kolaborasi.bodboc') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-tie"></i>
                        <p>
                            Optimalisasi Nasabah Prioritas BOD/BOC Nasabah Wholesale dan Komersial
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.kolaborasi.bodboc') }}" class="nav-link {{ request()->routeIs('report.kolaborasi.bodboc') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Nasabah Prioritas BOD/BOC</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const badge = document.getElementById('sidebar-job-badge');
    if (!badge || !badge.dataset.fetchUrl) {
        return;
    }

    let sidebarJobBadgeTimer = null;

    async function refreshSidebarJobBadge() {
        try {
            const response = await fetch(`${badge.dataset.fetchUrl}?status=all&page=1&per_page=1`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') {
                return;
            }

            const count = Number(payload?.summary?.active_jobs || 0);
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.remove('d-none');
            } else {
                badge.textContent = '0';
                badge.classList.add('d-none');
            }
        } catch (_) {
        }
    }

    refreshSidebarJobBadge();
    sidebarJobBadgeTimer = window.setInterval(refreshSidebarJobBadge, 8000);

    window.addEventListener('beforeunload', function () {
        if (sidebarJobBadgeTimer) {
            window.clearInterval(sidebarJobBadgeTimer);
        }
    });
});
</script>
