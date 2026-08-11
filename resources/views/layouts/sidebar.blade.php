<style>
    /* Premium Bright-White & Biru Nusantara Accent Sidebar System */
    .main-sidebar {
        background: #ffffff !important;
        border-right: 1px solid rgba(8, 87, 195, 0.08) !important;
        box-shadow: 10px 0 40px rgba(8, 87, 195, 0.03) !important;
        overflow-x: hidden !important;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    }

    .main-sidebar .sidebar {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -ms-overflow-style: none;
        scrollbar-width: none;
        scrollbar-gutter: stable;
        padding-top: 0.5rem;
        padding-bottom: 6rem !important; /* Fix for taskbar cutoff */
    }

    .main-sidebar .sidebar::-webkit-scrollbar {
        width: 0;
        height: 0;
    }

    /* Seamless Outer Containers */
    .main-sidebar .os-host,
    .main-sidebar .os-padding,
    .main-sidebar .os-viewport,
    .main-sidebar nav,
    .main-sidebar .nav-sidebar {
        overflow-x: hidden !important;
    }

    .main-sidebar .nav-sidebar {
        gap: 0.25rem;
        padding: 0 0.5rem !important;
    }

    /* Keep the primary dashboard groups in the requested navigation order. */
    .main-sidebar .nav-sidebar > .sidebar-dashboard-marketshare {
        order: -6;
    }

    .main-sidebar .nav-sidebar > .sidebar-dashboard-almafacts {
        order: -5;
    }

    .main-sidebar .nav-sidebar > .sidebar-dashboard-harian {
        order: -4;
    }

    .main-sidebar .nav-sidebar > .sidebar-dashboard-simpanan {
        order: -3;
    }

    .main-sidebar .nav-sidebar > .sidebar-dashboard-pinjaman {
        order: -2;
    }

    .main-sidebar .nav-sidebar > .sidebar-dashboard-kpi {
        order: -1;
    }

    /* Brand Header Panel - Bright elegant white with subtle blue border */
    .sidebar-brand-link {
        background: #ffffff !important;
        border-bottom: 1px solid rgba(8, 87, 195, 0.06) !important;
        padding: 1.25rem 1rem !important;
        transition: all 0.3s ease !important;
        display: block !important;
        height: auto !important;
        line-height: normal !important;
    }

    .sidebar-brand-inner {
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        overflow: hidden !important;
    }

    .sidebar-brand-badge {
        width: 42px !important;
        height: 42px !important;
        border-radius: 12px !important;
        background: rgba(8, 87, 195, 0.05) !important;
        border: 1px solid rgba(8, 87, 195, 0.12) !important;
        padding: 0px !important;
        box-shadow: 0 4px 15px rgba(8, 87, 195, 0.04) !important;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
        flex-shrink: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: hidden !important;
    }

    .sidebar-brand-mark {
        width: 100% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        position: relative !important;
    }

    .sidebar-brand-badge img {
        width: 100% !important;
        height: 100% !important;
        object-fit: contain !important;
        transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    }

    .sidebar-brand-fallback {
        display: none; /* Hidden by default when image loads successfully */
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 0.85rem !important;
        letter-spacing: 0.03em !important;
        text-transform: uppercase !important;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #307fe2 0%, #0857c3 100%);
        border-radius: 10px;
    }

    .sidebar-brand-link:hover .sidebar-brand-badge {
        transform: scale(1.05) rotate(-1.5deg);
        border-color: rgba(8, 87, 195, 0.25) !important;
        background: rgba(8, 87, 195, 0.08) !important;
        box-shadow: 0 6px 20px rgba(8, 87, 195, 0.1) !important;
    }

    .sidebar-brand-link:hover .sidebar-brand-badge img {
        transform: scale(1.08);
    }

    .sidebar-brand-text {
        flex: 1 !important;
        min-width: 0 !important;
        padding-left: 0.25rem !important;
    }

    .sidebar-brand-text .title {
        color: #0f172a !important;
        font-size: 1.25rem !important;
        font-weight: 850 !important;
        letter-spacing: 0.02em !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        background: linear-gradient(120deg, #0857c3 0%, #307fe2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 2px 8px rgba(8, 87, 195, 0.05);
    }

    .sidebar-brand-text .subtitle {
        font-size: 0.62rem !important;
        letter-spacing: 0.15em !important;
        color: #0857c3 !important; /* Biru Nusantara */
        font-weight: 800 !important;
        text-transform: uppercase !important;
        margin-top: 0.12rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        opacity: 0.95 !important;
    }

    /* Modern Light Blue-Grey User Profile Card */
    .sidebar-user-panel {
        border-radius: 16px !important;
        background: #f4f8ff !important;
        border: 1px solid rgba(8, 87, 195, 0.08) !important;
        box-shadow: 0 6px 20px rgba(8, 87, 195, 0.03) !important;
        padding: 0.72rem 0.8rem !important;
        margin: 1.25rem 0.4rem !important;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    }

    .sidebar-user-panel:hover {
        background: #eef5ff !important;
        transform: translateY(-2px);
        border-color: rgba(8, 87, 195, 0.15) !important;
        box-shadow: 0 10px 25px rgba(8, 87, 195, 0.06) !important;
    }

    .sidebar-user-inner {
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        overflow: hidden !important;
    }

    .sidebar-user-avatar {
        width: 42px !important;
        height: 42px !important;
        border-radius: 12px !important;
        background: linear-gradient(135deg, #0857c3 0%, #307fe2 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(8, 87, 195, 0.2) !important;
        font-size: 0.95rem !important;
        font-weight: 750 !important;
        letter-spacing: 0.03em !important;
        border: 2px solid #ffffff !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        flex-shrink: 0 !important;
    }

    .sidebar-user-panel:hover .sidebar-user-avatar {
        transform: scale(1.05) rotate(3deg);
    }

    .sidebar-user-info {
        flex: 1 !important;
        min-width: 0 !important; /* Critical layout fix for flexbox wrapping/cutoffs */
        padding-left: 0.75rem !important;
    }

    .sidebar-user-info .name {
        font-size: 0.9rem !important;
        font-weight: 750 !important;
        color: #0f172a !important;
        line-height: 1.25;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .sidebar-user-info .pn {
        font-size: 0.75rem !important;
        color: #0857c3 !important; /* Biru Nusantara */
        font-weight: 600 !important;
        margin-top: 0.12rem;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    /* Main Navigation Sidebar Items */
    .main-sidebar .nav-sidebar > .nav-item {
        margin-bottom: 0.2rem;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link {
        display: flex;
        align-items: flex-start !important; /* Align items to top to prevent overlap when wrapping */
        width: 100%;
        min-height: 45px;
        padding: 0.75rem 2.2rem 0.75rem 0.95rem !important;
        border-radius: 12px !important;
        color: #334155 !important; /* Elegant slate-grey for clean readable look */
        font-weight: 600 !important;
        font-size: 0.92rem !important;
        border: 1px solid transparent !important;
        position: relative;
        overflow: hidden;
        background: transparent !important;
        transition: all 250ms cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link .nav-icon {
        width: 1.35rem;
        min-width: 1.35rem;
        margin-right: 0.85rem !important;
        margin-top: 0.15rem !important; /* Vertically align with top text row */
        font-size: 1.08rem !important;
        text-align: center;
        color: #0857c3 !important; /* Vibrant Biru Nusantara icons */
        transition: all 250ms cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link p {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
        flex: 1 1 auto;
        margin: 0;
        line-height: 1.35;
        white-space: normal !important; /* Resolves long text cutoffs */
        word-break: break-word !important;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link p .right {
        position: absolute !important;
        right: 0.95rem !important;
        top: 0.95rem !important;
        margin: 0 !important;
        font-size: 0.75rem !important;
        color: #64748b !important;
        transition: transform 250ms cubic-bezier(0.25, 0.8, 0.25, 1) !important;
        flex-shrink: 0 !important;
    }

    /* Hover States - Organic Micro-Movements */
    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover {
        background: rgba(8, 87, 195, 0.05) !important;
        color: #0857c3 !important;
        transform: translateX(4px) !important;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover .nav-icon {
        transform: scale(1.12) translateY(-0.5px) !important;
        color: #307fe2 !important;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link:hover p .right {
        color: #0857c3 !important;
    }

    /* Active States - Premium Blue Accent Gradients */
    .main-sidebar .nav-sidebar > .nav-item > .nav-link.active {
        background: linear-gradient(135deg, #0857c3 0%, #307fe2 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 6px 18px rgba(8, 87, 195, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link.active .nav-icon {
        color: #ffffff !important;
    }

    .main-sidebar .nav-sidebar > .nav-item > .nav-link.active p .right {
        color: #ffffff !important;
    }

    /* Elegant Vertical Glow Bar on Active Parent Link */
    .main-sidebar .nav-sidebar > .nav-item > .nav-link.active::before {
        content: "" !important;
        position: absolute !important;
        left: 0 !important;
        top: 25% !important;
        height: 50% !important;
        width: 3.5px !important;
        background: #ffffff !important;
        border-radius: 0 4px 4px 0 !important;
        box-shadow: 0 0 8px rgba(255, 255, 255, 0.9) !important;
    }

    /* Submenu Treeview - Soft Blue Connector Guides */
    .main-sidebar .nav-sidebar .nav-treeview {
        position: relative !important;
        margin: 0.15rem 0 0.5rem 0.5rem !important;
        padding: 0 0 0 1.25rem !important;
        background: transparent !important;
    }

    /* Left Guide Rail Connection Line */
    .main-sidebar .nav-sidebar .nav-treeview::before {
        content: "" !important;
        position: absolute !important;
        left: 0.5rem !important;
        top: 0 !important;
        bottom: 0 !important;
        width: 1px !important;
        background: rgba(8, 87, 195, 0.12) !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link {
        display: flex;
        align-items: flex-start !important;
        width: 100%;
        min-height: 38px;
        margin-bottom: 0.18rem;
        padding: 0.55rem 0.85rem !important;
        border-radius: 10px !important;
        color: #475569 !important;
        font-size: 0.85rem !important;
        font-weight: 550 !important;
        background: transparent !important;
        position: relative !important;
        transition: all 200ms ease !important;
        border: none !important;
        box-shadow: none !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link p {
        display: block;
        flex: 1 1 auto;
        min-width: 0;
        margin: 0;
        line-height: 1.35;
        white-space: normal !important;
        word-break: break-word !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.sidebar-nav-compact {
        align-items: center !important;
        min-height: 40px;
        padding-right: 0.65rem !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.sidebar-nav-compact p {
        font-size: 0.8rem;
        line-height: 1.25;
        overflow-wrap: anywhere;
        text-wrap: balance;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link .nav-icon {
        width: 0.9rem;
        min-width: 0.9rem;
        margin-right: 0.65rem !important;
        margin-top: 0.18rem !important;
        font-size: 0.8rem !important;
        color: rgba(8, 87, 195, 0.5) !important;
        transition: all 200ms ease !important;
    }

    /* Submenu Interactive Node Dot Indicator */
    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link::before {
        content: "" !important;
        position: absolute !important;
        left: -0.8rem !important;
        top: 0.7rem !important; /* Perfectly aligned to the first line of wrapped text */
        width: 5px !important;
        height: 5px !important;
        border-radius: 50% !important;
        background: rgba(8, 87, 195, 0.25) !important;
        transform: translateY(-50%) !important;
        transition: all 200ms ease !important;
    }

    /* Submenu Hover states */
    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link:hover {
        background: rgba(8, 87, 195, 0.04) !important;
        color: #0857c3 !important;
        transform: translateX(3px) !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link:hover .nav-icon {
        color: #307fe2 !important;
        transform: scale(1.1) !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link:hover::before {
        background: #307fe2 !important;
        transform: translateY(-50%) scale(1.4) !important;
        box-shadow: 0 0 6px rgba(48, 127, 226, 0.4) !important;
    }

    /* Submenu Active State */
    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.active {
        background: rgba(8, 87, 195, 0.06) !important;
        color: #0857c3 !important;
        font-weight: 700 !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.active .nav-icon {
        color: #0857c3 !important;
    }

    .main-sidebar .nav-sidebar .nav-treeview > .nav-item > .nav-link.active::before {
        background: #0857c3 !important;
        transform: translateY(-50%) scale(1.5) !important;
        box-shadow: 0 0 8px rgba(8, 87, 195, 0.6) !important;
    }

    /* Section Header Custom Styling */
    .main-sidebar .nav-sidebar .nav-header {
        display: block !important;
        width: 100%;
        padding: 1.6rem 0.95rem 0.6rem !important;
        margin: 0;
        font-size: 0.65rem !important;
        font-weight: 800 !important;
        line-height: 1;
        letter-spacing: 0.16em !important;
        text-align: left !important;
        color: #0857c3 !important; /* Premium Biru Nusantara category text */
        text-transform: uppercase !important;
        opacity: 0.85;
    }

    /* Job Manager Badge custom glow styling */
    .sidebar-job-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.5rem;
        height: 1.5rem;
        padding: 0 0.42rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        color: #fff;
        font-size: 0.69rem;
        font-weight: 800;
        line-height: 1;
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25);
    }

    .main-sidebar .nav-link:hover .sidebar-job-badge,
    .main-sidebar .nav-link.active .sidebar-job-badge {
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }

    /* Collapsed Sidebar Modernization Adjustments */
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) {
        width: 4.8rem !important;
    }

    .sidebar-mini.sidebar-collapse .main-sidebar .brand-link,
    .sidebar-mini.sidebar-collapse .main-sidebar:hover .brand-link,
    .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .brand-link {
        padding: 1rem 0.5rem !important;
        justify-content: center !important;
        transition: none !important;
        transform: none !important;
        width: 4.8rem !important;
    }

    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-user-panel {
        padding: 0.6rem 0.3rem !important;
        justify-content: center !important;
        margin-left: 0.3rem !important;
        margin-right: 0.3rem !important;
        width: calc(100% - 0.6rem) !important;
    }

    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar > .nav-item > .nav-link {
        padding: 0.65rem 0 !important;
        justify-content: center !important;
        width: calc(100% - 0.8rem) !important;
        margin: 0 auto 0.25rem auto !important;
    }

    .sidebar-mini.sidebar-collapse .main-sidebar .sidebar-brand-badge,
    .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-brand-badge,
    .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-brand-badge,
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-user-avatar,
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-icon {
        margin-right: 0 !important;
        transition: none !important;
        transform: none !important;
    }

    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-brand-text,
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .sidebar-user-info,
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar > .nav-item:not(:hover) p,
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar > .nav-item:not(:hover) .right,
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-header,
    .sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar > .nav-item:not(:hover) .nav-treeview {
        display: none !important;
    }

    /* Expanded collapsed Hover states (Disabled to prevent layout bugs) */
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
    }

    .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-panel,
    .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-panel {
        padding: 0.6rem 0.3rem !important;
        justify-content: center !important;
        margin-left: 0.3rem !important;
        margin-right: 0.3rem !important;
        width: calc(100% - 0.6rem) !important;
    }

    .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-sidebar > .nav-item > .nav-link,
    .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-sidebar > .nav-item > .nav-link {
        padding: 0.65rem 0 !important;
        justify-content: center !important;
        width: calc(100% - 0.8rem) !important;
        margin: 0 auto 0.25rem auto !important;
    }

    .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-brand-badge,
    .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-brand-badge,
    .sidebar-mini.sidebar-collapse .main-sidebar:hover .sidebar-user-avatar,
    .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .sidebar-user-avatar,
    .sidebar-mini.sidebar-collapse .main-sidebar:hover .nav-icon,
    .sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .nav-icon {
        margin-right: 0 !important;
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
                
                {{-- FIX DASHBOARD HARIAN DROPDOWN BUG --}}
                <li class="nav-item sidebar-dashboard-harian {{ request()->is('dashboard-harian*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('dashboard-harian*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-calendar-day"></i>
                        <p>
                            Dashboard Harian
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('dashboard.harian') }}" class="nav-link {{ request()->routeIs('dashboard.harian') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Keragaan Harian</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('dashboard.harian.keragaan-uker') }}" class="nav-link {{ request()->routeIs('dashboard.harian.keragaan-uker*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Keragaan per Uker</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('dashboard.harian.timeseries') }}" class="nav-link {{ request()->routeIs('dashboard.harian.timeseries') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Timeseries</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item sidebar-dashboard-simpanan {{ request()->routeIs('report.dashboard-dana', 'report.dashboard-dana.hourly-dpk') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.dashboard-dana', 'report.dashboard-dana.hourly-dpk') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-university"></i>
                        <p>
                            Dashboard Simpanan
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-dana') }}" class="nav-link {{ request()->routeIs('report.dashboard-dana') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Dashboard Dana</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-dana.hourly-dpk') }}" class="nav-link {{ request()->routeIs('report.dashboard-dana.hourly-dpk') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Hourly DPK</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item sidebar-dashboard-pinjaman {{ request()->routeIs('dashboard', 'report.dashboard-pinjaman*', 'report.dashboard-pinjaman.kinerjarm', 'report.dashboard-pinjaman.kolek-tidak-sesuai.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('dashboard', 'report.dashboard-pinjaman*', 'report.dashboard-pinjaman.kinerjarm', 'report.dashboard-pinjaman.kolek-tidak-sesuai.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <p>
                            Dashboard Pinjaman
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Landing Page</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.kredit') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.kredit') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Dashboard Pinjaman Kredit</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.tunggakan-kecil') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.tunggakan-kecil*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Tunggakan Kecil</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.realisasi-6-bulan-menunggak') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.realisasi-6-bulan-menunggak*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Monitoring Realisasi yang Menunggak</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.analisa-ug-npl') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.analisa-ug-npl*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Analisa UG NPL</p>
                            </a>
                        </li>
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
                            <a href="{{ route('report.dashboard-pinjaman.chart-periodik') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.chart-periodik') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Trend Periode Pembayaran</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.data-ph') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.data-ph') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Data PH</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.run-off') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.run-off') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Run OFF</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.kinerja-non-ptp') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.kinerja-non-ptp') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Histori PTP Deb</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item sidebar-dashboard-almafacts {{ request()->routeIs('report.dashboard-almafacts.financial-highlight', 'report.dashboard-almafacts.kinerja-laba-rugi', 'report.dashboard-almafacts.timeseries', 'report.dashboard-almafacts.timeseries.data') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.financial-highlight', 'report.dashboard-almafacts.kinerja-laba-rugi', 'report.dashboard-almafacts.timeseries', 'report.dashboard-almafacts.timeseries.data') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-balance-scale"></i>
                        <p>
                            Dashboard Almafacts
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.financial-highlight') }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.financial-highlight') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Financial Highlight</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.kinerja-laba-rugi') }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kinerja-laba-rugi') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Kinerja Laba Rugi</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.timeseries') }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.timeseries') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Timeseries Laba Rugi</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item sidebar-dashboard-kpi {{ request()->routeIs('report.dashboard-almafacts.kpi', 'report.dashboard-pinjaman.kinerjarm', 'report.dashboard-pinjaman.kinerjarmmikro') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kpi', 'report.dashboard-pinjaman.kinerjarm', 'report.dashboard-pinjaman.kinerjarmmikro') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <p>
                            KPI
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => 'mbm']) }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kpi') && in_array(request()->route('sheet'), [null, 'mbm'], true) ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KPI MBM</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => 'ka-unit']) }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kpi') && request()->route('sheet') === 'ka-unit' ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KPI KA Unit</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => 'rm-mikro']) }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kpi') && request()->route('sheet') === 'rm-mikro' ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KPI RM Mikro</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => 'rm-sme']) }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kpi') && request()->route('sheet') === 'rm-sme' ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KPI RM SME</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => 'mantri']) }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kpi') && request()->route('sheet') === 'mantri' ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KPI Mantri</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => 'consumer']) }}" class="nav-link {{ request()->routeIs('report.dashboard-almafacts.kpi') && request()->route('sheet') === 'consumer' ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KPI Konsumer</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.kinerjarm') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.kinerjarm') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Kinerja RM Ritel</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-pinjaman.kinerjarmmikro') }}" class="nav-link {{ request()->routeIs('report.dashboard-pinjaman.kinerjarmmikro') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Kinerja RM Mikro</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item {{ request()->routeIs('prognosa.weekly') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('prognosa.weekly') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-area"></i>
                        <p>
                            Prognosa
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('prognosa.weekly') }}" class="nav-link {{ request()->routeIs('prognosa.weekly') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Weekly Prognosa</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="{{ route('drive.index') }}" class="nav-link {{ request()->routeIs('drive.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-hdd"></i>
                        <p>Bank Pipeline</p>
                    </a>
                </li>

                <li class="nav-item sidebar-dashboard-marketshare {{ request()->routeIs('report.dashboard-dana.market-share.mapping', 'report.dashboard-dana.market-share.mapping-cras', 'report.dashboard-dana.market-share.area6', 'report.dashboard-dana.market-share.sektoral', 'report.dashboard-dana.market-share.instansi') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.dashboard-dana.market-share.mapping', 'report.dashboard-dana.market-share.mapping-cras', 'report.dashboard-dana.market-share.area6', 'report.dashboard-dana.market-share.sektoral', 'report.dashboard-dana.market-share.instansi') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Marketshare
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-dana.market-share.mapping') }}" class="nav-link {{ request()->routeIs('report.dashboard-dana.market-share.mapping') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Mapping</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-dana.market-share.mapping-cras') }}" class="nav-link sidebar-nav-compact {{ request()->routeIs('report.dashboard-dana.market-share.mapping-cras') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Marketshare CRAS LPG</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-dana.market-share.area6') }}" class="nav-link sidebar-nav-compact {{ request()->routeIs('report.dashboard-dana.market-share.area6') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Marketshare Area 6</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-dana.market-share.sektoral') }}" class="nav-link sidebar-nav-compact {{ request()->routeIs('report.dashboard-dana.market-share.sektoral') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Marketshare Sektoral</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.dashboard-dana.market-share.instansi') }}" class="nav-link {{ request()->routeIs('report.dashboard-dana.market-share.instansi') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Marketshare Instansi</p>
                            </a>
                        </li>
                    </ul>
                </li>


                @if(Auth::user()?->isAdmin())
                <li class="nav-header text-uppercase">MANAGEMENT</li>

                <li class="nav-item {{ request()->routeIs('job-management.*', 'file-management.*', 'user-management.*', 'link-management.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('job-management.*', 'file-management.*', 'user-management.*', 'link-management.*') ? 'active' : '' }}">
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
                                        data-fetch-url="{{ route('job-management.badge') }}"
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
                        <li class="nav-item">
                            <a href="{{ route('link-management.index') }}" class="nav-link {{ request()->routeIs('link-management.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-link"></i>
                                <p>Link Management</p>
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
                            <a href="{{ route('report.qlola') }}" class="nav-link submenu-single-line {{ request()->routeIs('report.qlola') ? 'active' : '' }}">
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

                <li class="nav-item {{ request()->routeIs('report.kolaborasi.business-cluster', 'report.kolaborasi.sppg') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('report.kolaborasi.business-cluster', 'report.kolaborasi.sppg') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-layer-group"></i>
                        <p>
                            Business Cluster
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('report.kolaborasi.business-cluster') }}" class="nav-link {{ request()->routeIs('report.kolaborasi.business-cluster') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Business Cluster</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('report.kolaborasi.sppg') }}" class="nav-link {{ request()->routeIs('report.kolaborasi.sppg') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>SPPG</p>
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
    let sidebarJobBadgeRequest = null;

    function scheduleSidebarJobBadgeRefresh(delay) {
        if (sidebarJobBadgeTimer) {
            window.clearTimeout(sidebarJobBadgeTimer);
        }

        if (!document.hidden) {
            sidebarJobBadgeTimer = window.setTimeout(refreshSidebarJobBadge, delay);
        }
    }

    async function refreshSidebarJobBadge() {
        if (document.hidden || sidebarJobBadgeRequest) {
            return;
        }

        sidebarJobBadgeRequest = new AbortController();

        try {
            const response = await fetch(badge.dataset.fetchUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: sidebarJobBadgeRequest.signal,
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') {
                scheduleSidebarJobBadgeRefresh(45000);
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
        } finally {
            sidebarJobBadgeRequest = null;
            const hasActiveJobs = !badge.classList.contains('d-none');
            scheduleSidebarJobBadgeRefresh(hasActiveJobs ? 15000 : 45000);
        }
    }

    refreshSidebarJobBadge();

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (sidebarJobBadgeTimer) {
                window.clearTimeout(sidebarJobBadgeTimer);
            }
            if (sidebarJobBadgeRequest) {
                sidebarJobBadgeRequest.abort();
            }
            return;
        }

        refreshSidebarJobBadge();
    });

    window.addEventListener('beforeunload', function () {
        if (sidebarJobBadgeTimer) {
            window.clearTimeout(sidebarJobBadgeTimer);
        }
        if (sidebarJobBadgeRequest) {
            sidebarJobBadgeRequest.abort();
        }
    });
});
</script>
