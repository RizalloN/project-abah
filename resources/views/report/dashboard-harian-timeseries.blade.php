@extends('layouts.admin')

@section('title', 'Timeseries Dashboard Harian')

@section('styles')
<style>
    :root {
        --filter-card-bg: #ffffff;
        --chart-card-bg: #ffffff;
        --accent-dark: #0f172a;
    }

    .dashboard-timeseries {
        padding-bottom: 2rem;
        min-width: 0;
    }

    .dashboard-timeseries h1 {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }

    .dashboard-timeseries .text-muted {
        font-size: 0.85rem;
    }

    .timeseries-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        border-radius: 0 0 1.4rem 1.4rem;
        margin: -1rem -0.25rem 1rem;
        padding: 1.45rem 1.25rem;
        background:
            radial-gradient(circle at 10% 18%, rgba(255, 103, 31, 0.18), transparent 27%),
            radial-gradient(circle at 88% 12%, rgba(59, 130, 246, 0.22), transparent 30%),
            linear-gradient(135deg, #003b75 0%, #00529c 48%, #0f4c97 100%);
        color: #ffffff;
        box-shadow: 0 18px 40px -30px rgba(0, 55, 116, 0.55);
    }

    .timeseries-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(120deg, rgba(255, 255, 255, 0.13), transparent 36%),
            repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 18px);
        opacity: 0.72;
    }

    .timeseries-title-wrap {
        max-width: 760px;
    }

    .timeseries-title-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.55rem;
        padding: 0.32rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.24);
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.64rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .timeseries-title-badge i {
        color: #ffb15c;
    }

    .timeseries-title {
        margin: 0;
        font-size: clamp(1.35rem, 2.35vw, 2.35rem);
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 0.035em;
        line-height: 1.08;
        text-transform: uppercase;
        text-shadow: 0 10px 26px rgba(0, 18, 50, 0.28);
    }

    .timeseries-title::after {
        content: '';
        display: block;
        width: min(130px, 38vw);
        height: 3px;
        margin: 0.7rem 0 0;
        border-radius: 999px;
        background: linear-gradient(90deg, #ff671f, #f9b233, rgba(255, 255, 255, 0.9));
        box-shadow: 0 8px 18px rgba(255, 103, 31, 0.28);
    }

    .timeseries-subtitle {
        margin: 0.65rem 0 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.78rem;
        line-height: 1.6;
        max-width: 620px;
    }

    .timeseries-hero-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
        padding-top: 2.15rem;
    }

    .timeseries-hero .btn-export-all {
        min-height: 32px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.45);
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-weight: 800;
        letter-spacing: 0.025em;
        font-size: 0.68rem;
        padding: 0.34rem 0.72rem !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16);
    }

    .timeseries-hero .btn-export-all:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.68);
    }

    .timeseries-unit-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.38rem 0.68rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    /* Filter Sidebar/Top Styling */
    .filter-card {
        background:
            linear-gradient(180deg, rgba(235, 243, 255, 0.98) 0%, rgba(255, 255, 255, 0.98) 76%),
            var(--filter-card-bg);
        border: 1px solid rgba(8, 87, 195, 0.14);
        border-radius: 1.25rem;
        box-shadow: 0 18px 38px -28px rgba(8, 87, 195, 0.32);
        margin-bottom: 1rem;
        overflow: visible !important;
        position: relative;
        z-index: 100;
    }

    .filter-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 1rem;
        right: 1rem;
        height: 3px;
        border-radius: 999px;
        background: linear-gradient(90deg, #00529c, #3b82f6, #ffb15c);
    }

    .filter-card .card-body {
        padding: 1rem 1rem 0.95rem !important;
        overflow: visible !important;
    }

    .filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #4b6285;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .category-selector {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .category-btn {
        padding: 0.6rem 1.2rem;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        background: rgba(255, 255, 255, 0.82);
        color: #475569;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .category-btn:hover {
        background: #ffffff;
        border-color: rgba(8, 87, 195, 0.25);
    }

    .category-btn.active {
        background: linear-gradient(135deg, #0857c3 0%, #307fe2 100%);
        color: white;
        border-color: #0857c3;
        box-shadow: 0 8px 20px -8px rgba(8, 87, 195, 0.5);
    }

    /* Chart Card Styling */
    .chart-card {
        background: var(--chart-card-bg);
        border: 1px solid rgba(8, 87, 195, 0.08);
        border-radius: 1rem;
        box-shadow: 0 4px 20px -10px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    .chart-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -20px rgba(8, 87, 195, 0.2);
    }

    .chart-header {
        padding: 0.9rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .chart-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .chart-body {
        padding: 1.25rem 1.75rem 2.1rem 1.9rem;
        flex: 0 0 auto;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
    }

    .chart-canvas-frame {
        position: relative;
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
        overflow: hidden;
    }

    /* Fixed heights to prevent vertical stretching */
    .summary-chart-body {
        height: 330px !important;
        max-height: none !important;
        padding: 0.35rem 1.25rem 1rem 1.35rem;
        overflow: hidden;
    }

    .branch-chart-body {
        height: 280px !important;
        max-height: 280px !important;
    }

    .branch-chart-body.tall {
        height: 400px !important;
        max-height: 400px !important;
    }

    .chart-canvas-frame canvas {
        width: 100% !important;
        height: 100% !important;
        display: block !important;
    }

    .summary-chart-card {
        border: 1px solid rgba(8, 87, 195, 0.2);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        min-height: 390px;
    }

    .summary-chart-card:hover {
        transform: none;
    }

    .loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 1.5rem;
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #0857c3;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .unit-badge {
        font-size: 0.65rem;
        padding: 0.25rem 0.6rem;
        border-radius: 2rem;
        background: rgba(8, 87, 195, 0.1);
        color: #0857c3;
        font-weight: 700;
        text-transform: uppercase;
    }

    .btn-export-jpg {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        padding: 0;
        cursor: pointer;
        margin-left: 0.75rem;
    }

    .btn-export-jpg:hover {
        background: #f8fbff;
        border-color: #0857c3;
        color: #0857c3;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(8, 87, 195, 0.15);
    }

    .btn-export-jpg i {
        font-size: 0.85rem;
    }

    /* Capture Status Modal Premium Styles */
    .capture-status-modal .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    .capture-status-modal .modal-body {
        padding: 3rem 2rem;
    }

    .capture-status-modal-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
    }

    .icon-loading { background: rgba(8, 87, 195, 0.1); color: #0857c3; }
    .icon-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

    .capture-status-modal .btn-primary {
        border-radius: 12px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .modal-backdrop.show {
        backdrop-filter: none;
        background-color: rgba(15, 23, 42, 0.12);
    }

    body.modal-open .dashboard-timeseries,
    body.modal-open .content-wrapper,
    body.modal-open .main-content {
        filter: none !important;
        backdrop-filter: none !important;
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4rem 2rem;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }

    /* Custom Premium Dropdown (adapted from Performance EDC) */
    .branch-filter-dropdown {
        position: relative;
        width: 100%;
    }

    .branch-dropdown-toggle {
        width: 100%;
        min-height: 42px;
        padding: 0.6rem 1rem;
        background: rgba(255, 255, 255, 0.86);
        border: 1px solid #dbe5ef;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .branch-dropdown-toggle:hover {
        border-color: #0857c3;
        background: #fff;
        box-shadow: 0 10px 24px -22px rgba(8, 87, 195, 0.42);
    }

    .period-month-select {
        width: 100%;
        min-height: 42px;
        padding: 0.6rem 2.4rem 0.6rem 1rem;
        background: rgba(255, 255, 255, 0.86);
        border: 1px solid #dbe5ef;
        border-radius: 0.75rem;
        color: #1e293b;
        font-size: 0.88rem;
        font-weight: 600;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        cursor: pointer;
    }

    .period-month-select:focus {
        outline: none;
        border-color: #0857c3;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.12);
        background: #ffffff;
    }

    .period-select-shell {
        position: relative;
    }

    #applyFilters {
        min-height: 42px;
        border: none;
        border-radius: 0.85rem;
        background: linear-gradient(135deg, #00529c 0%, #1d4ed8 100%);
        font-weight: 800;
        letter-spacing: 0.02em;
        box-shadow: 0 14px 24px -18px rgba(8, 87, 195, 0.72);
    }

    #applyFilters:hover {
        filter: saturate(1.08);
        transform: translateY(-1px);
    }

    .period-select-shell i {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: #64748b;
        font-size: 0.75rem;
    }

    .branch-dropdown-label {
        font-size: 0.88rem;
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 1.25rem;
    }

    .branch-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 15px 35px -10px rgba(8, 87, 195, 0.2);
        z-index: 9999 !important;
        display: none;
        overflow: hidden;
        animation: slideDown 0.2s ease-out;
    }

    .branch-dropdown-menu.show {
        display: block !important;
    }

    .options-container {
        max-height: 280px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .options-container::-webkit-scrollbar {
        width: 5px;
    }

    .options-container::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }

    .branch-option {
        display: flex;
        align-items: center;
        padding: 0.6rem 0.75rem;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: background 0.2s;
        gap: 0.75rem;
        user-select: none;
    }

    .branch-option:hover {
        background: #f1f5f9;
    }

    .branch-option input {
        display: none;
    }

    .branch-checkbox-ui {
        width: 18px;
        height: 18px;
        border: 2px solid #cbd5e1;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .branch-option.selected .branch-checkbox-ui {
        background: #0857c3;
        border-color: #0857c3;
    }

    .branch-checkbox-ui i {
        color: white;
        font-size: 10px;
        display: none;
    }

    .branch-option.selected .branch-checkbox-ui i {
        display: block;
    }

    .branch-option-label {
        font-size: 0.88rem;
        font-weight: 500;
        color: #334155;
    }

    .branch-option.selected .branch-option-label {
        font-weight: 700;
        color: #0857c3;
    }

    .select-all-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #0857c3;
        text-transform: uppercase;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .select-all-btn:hover {
        background: #eff6ff;
        border-radius: 0.5rem;
    }

    @media (max-width: 767.98px) {
        .dashboard-timeseries > .d-flex,
        .dashboard-timeseries > .timeseries-hero {
            align-items: flex-start !important;
            flex-direction: column;
            gap: 0.75rem;
        }

        .timeseries-hero {
            padding: 1.15rem 1rem;
            margin-inline: 0;
        }

        .timeseries-hero-actions {
            width: 100%;
            justify-content: flex-start;
            padding-top: 0.35rem;
        }

        .timeseries-hero .btn-export-all,
        .timeseries-unit-badge {
            width: 100%;
            justify-content: center;
        }

        .timeseries-title::after {
            margin-left: 0;
        }

        .category-selector {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .category-btn {
            width: 100%;
            padding-inline: 0.75rem;
        }

        .chart-body {
            overflow-x: auto;
            overflow-y: hidden;
            padding: 1rem;
        }

        .summary-chart-body {
            height: 340px !important;
            max-height: none !important;
            padding: 0.5rem 0.9rem 1.1rem 1rem;
        }

        .branch-chart-body {
            height: 270px !important;
            max-height: 270px !important;
        }

        .summary-chart-body .chart-canvas-frame,
        .branch-chart-body .chart-canvas-frame {
            min-width: 660px;
        }

        .chart-header {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.5rem;
        }
    }
</style>
@endsection

@section('content')
<div class="dashboard-timeseries">
    <!-- Header -->
    <div class="timeseries-hero d-flex align-items-center justify-content-between">
        <div class="timeseries-title-wrap">
            <div class="timeseries-title-badge">
                <i class="fas fa-university"></i>
                <span>BRI Monthly Trend</span>
            </div>
            <h1 class="timeseries-title">TIMESERIES DASHBOARD</h1>
            <p class="timeseries-subtitle">Tren keragaan harian berdasarkan perspektif bulanan untuk memantau pergerakan metrik utama Area secara lebih tajam.</p>
        </div>
        <div class="timeseries-hero-actions">
            <button id="captureAllBtn" class="btn btn-sm px-3 btn-export-all">
                <i class="fas fa-file-image mr-2"></i> EXPORT A4 (PORTRAIT)
            </button>
            <div class="timeseries-unit-badge">
                <i class="fas fa-layer-group"></i>
                <span>Satuan: Dalam Rp Miliar (Rp M)</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body p-4">
            <div class="row align-items-end">
                <div class="col-lg-3 mb-3 mb-lg-0">
                    <label class="filter-label">Kategori Metrik</label>
                    <div class="category-selector" id="categorySelector">
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'simpanan' ? 'active' : '' }}" data-value="simpanan">Simpanan</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'pinjaman' ? 'active' : '' }}" data-value="pinjaman">Pinjaman</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'sml' ? 'active' : '' }}" data-value="sml">SML</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'npl' ? 'active' : '' }}" data-value="npl">NPL</button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Kantor Cabang</label>
                    <div class="branch-filter-dropdown" id="kancaDropdownShell">
                        <div class="branch-dropdown-toggle" id="kancaDropdown">
                            <span class="branch-dropdown-label" id="kancaLabel">Memuat Cabang...</span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="kancaMenu">
                            <div class="options-container" id="kancaOptionsList">
                                {{-- Will be populated by JS --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Unit Kerja</label>
                    <div class="branch-filter-dropdown" id="unitDropdownShell">
                        <div class="branch-dropdown-toggle" id="unitDropdown">
                            <span class="branch-dropdown-label" id="unitLabel">Semua Unit Kerja</span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="unitMenu">
                            <input type="hidden" id="unitInput" value="{{ $dashboardPage['selected']['unit_kerja'] }}">
                            <div class="options-container" id="unitOptions">
                                {{-- Will be populated by JS --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Periode Akhir</label>
                    <div class="branch-filter-dropdown" id="periodDropdownShell">
                        <div class="branch-dropdown-toggle" id="periodDropdown">
                            <span class="branch-dropdown-label" id="periodLabel">
                                @php
                                    $selectedLabel = collect($dashboardPage['filters']['period_month'] ?? [])->firstWhere('value', $dashboardPage['selected']['period_month'] ?? '')['label'] ?? 'Pilih Periode';
                                @endphp
                                {{ $selectedLabel }}
                            </span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="periodMenu">
                            <input type="hidden" id="periodMonthFilter" value="{{ $dashboardPage['selected']['period_month'] ?? '' }}">
                            <div class="options-container" id="periodOptions">
                                @foreach(($dashboardPage['filters']['period_month'] ?? []) as $item)
                                    <div class="branch-option {{ ($dashboardPage['selected']['period_month'] ?? '') === $item['value'] ? 'selected' : '' }}" data-value="{{ $item['value'] }}" data-label="{{ $item['label'] }}">
                                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                                        <span class="branch-option-label">{{ $item['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2">
                    <button id="applyFilters" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-sync-alt mr-2"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Capture Target Area -->
    <div id="timeseriesCaptureArea" style="background: #fdfdfe; padding: 1.5rem 0.5rem; border-radius: 20px;">
        <!-- Summary Chart Container -->
        <div class="row mb-3" id="summaryChartContainer">
        <div class="col-12">
            <div class="card chart-card summary-chart-card">
                <div class="chart-header">
                    <h5 class="chart-title"><i class="fas fa-chart-area mr-2 text-primary"></i>Area 6 - Konsolidasi</h5>
                    <div class="d-flex align-items-center">
                        <div class="unit-badge" id="summaryChartBadge">Total Konsolidasi Selected Branches</div>
                        <button class="btn-export-jpg ml-2" onclick="window.downloadTimeseriesChart('summary', 'Timeseries-Area6-Consolidation')" title="Export to JPG">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-body summary-chart-body">
                    <div class="loading-overlay" id="summaryLoading">
                        <div class="loading-spinner"></div>
                    </div>
                    <div class="chart-canvas-frame">
                        <canvas id="summaryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Individual Branch Charts -->
        <div class="row g-3" id="individualChartsContainer" style="margin-top: 0;">
            <!-- Dynamic Content -->
        </div>
    </div>

    <!-- Capture Status Modal -->
    <div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <!-- Loading State -->
                    <div id="captureProgressUI">
                        <div class="capture-status-modal-icon icon-loading">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Menyusun Laporan A4</h4>
                        <p class="text-muted mb-0">Sedang menyusun konsolidasi dan empat cabang ke dalam satu gambar A4 portrait. Mohon tunggu sebentar...</p>
                    </div>

                    <!-- Error State -->
                    <div id="captureErrorUI" class="d-none">
                        <div class="capture-status-modal-icon icon-error">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Gagal Mengambil Snapshot</h4>
                        <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kendala saat menyusun snapshot A4.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Tutup & Coba Lagi
                        </button>
                    </div>

                    <!-- Success State -->
                    <div id="captureSuccessUI" class="d-none">
                        <div class="capture-status-modal-icon icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Snapshot Berhasil!</h4>
                        <p class="text-muted mb-4">Snapshot A4 dalam satu file JPG telah berhasil diunduh ke perangkat Anda.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Selesai
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
    (function() {
        console.log('Timeseries Dashboard Script Initializing...');
        
        function init() {
            const routes = @json($dashboardPage['routes']);
            const initialTimeseriesData = @json($dashboardPage['initialData'] ?? []);
            let currentCategory = '{{ $dashboardPage['selected']['category'] }}';
            let charts = {};
            let activeRequestId = 0;
            const totalArea6Count = 4;

            // --- Data Definitions ---
            const allKancasData = @json($dashboardPage['filters']['kanca']);
            const allUnitsData = @json($dashboardPage['filters']['unit_kerja']);
            const selectedKancasInitial = @json($dashboardPage['selected']['kanca']);
            const selectedUnitInitial = '{{ $dashboardPage['selected']['unit_kerja'] }}';

            // --- Custom Dropdown Shell Definitions ---
            const kancaToggle = document.getElementById('kancaDropdown');
            const kancaMenu = document.getElementById('kancaMenu');
            const kancaOptionsContainer = document.getElementById('kancaOptionsList');
            const kancaLabel = document.getElementById('kancaLabel');
            
            const unitToggle = document.getElementById('unitDropdown');
            const unitMenu = document.getElementById('unitMenu');
            const unitLabel = document.getElementById('unitLabel');
            const unitInput = document.getElementById('unitInput');
            const unitOptionsContainer = document.getElementById('unitOptions');
            const periodMonthSelect = document.getElementById('periodMonthFilter');
            const applyBtn = document.getElementById('applyFilters');

            // --- Export JPG Logic ---
            window.downloadTimeseriesChart = function(chartKey, fileName) {
                const chart = charts[chartKey];
                if (!chart) {
                    console.error('Chart not found for key:', chartKey);
                    return;
                }

                // Create a temporary canvas to add white background (JPG doesn't support transparency)
                const canvas = chart.canvas;
                const tempCanvas = document.createElement('canvas');
                const ctx = tempCanvas.getContext('2d');

                tempCanvas.width = canvas.width;
                tempCanvas.height = canvas.height;

                // Fill with white background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);

                // Draw the original chart canvas on top
                ctx.drawImage(canvas, 0, 0);

                // Trigger download
                const link = document.createElement('a');
                link.download = `${fileName}.jpg`;
                link.href = tempCanvas.toDataURL('image/jpeg', 0.9);
                link.click();
            };

            // --- Capture All Logic (A4 Portrait Composer) ---
            const captureBtn = document.getElementById('captureAllBtn');
            const captureModal = document.getElementById('captureStatusModal');
            const progressUI = document.getElementById('captureProgressUI');
            const errorUI = document.getElementById('captureErrorUI');
            const successUI = document.getElementById('captureSuccessUI');
            const errorMessageUI = document.getElementById('captureErrorMessage');

            const A4_EXPORT = {
                width: 2480,
                height: 3508,
                marginX: 150,
                marginY: 135,
                headerHeight: 260,
                footerHeight: 80,
                sectionGap: 58,
                branchGap: 50,
            };

            function waitFrame() {
                return new Promise(resolve => requestAnimationFrame(() => resolve()));
            }

            function sanitizeFilePart(value) {
                return String(value || 'timeseries')
                    .trim()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .substring(0, 80) || 'timeseries';
            }

            function getCanvasChart(canvas) {
                if (!canvas || !window.Chart || typeof Chart.getChart !== 'function') {
                    return null;
                }

                return Chart.getChart(canvas);
            }

            function getVisibleChartEntries() {
                return Array.from(document.querySelectorAll('.chart-card'))
                    .filter(card => card.offsetParent !== null)
                    .map(card => {
                        const canvas = card.querySelector('canvas');
                        const chart = getCanvasChart(canvas);
                        if (!chart) return null;

                        return {
                            chart,
                            title: card.querySelector('.chart-title')?.textContent?.trim() || 'Timeseries Chart',
                            badge: card.querySelector('.unit-badge')?.textContent?.trim() || 'Daily Trend',
                        };
                    })
                    .filter(Boolean);
            }

            function cloneChartDatasets(chart, isCompact = false) {
                return chart.data.datasets.map((dataset, index) => {
                    const isLatest = index === chart.data.datasets.length - 1;

                    return {
                        label: dataset.label,
                        data: Array.isArray(dataset.data) ? dataset.data.slice() : dataset.data,
                        borderColor: dataset.borderColor,
                        backgroundColor: dataset.backgroundColor,
                        borderWidth: isCompact ? (isLatest ? 4 : 3) : (isLatest ? 7 : 5),
                        pointRadius: isCompact ? (isLatest ? 4 : 2) : (isLatest ? 8 : 4),
                        pointHoverRadius: isCompact ? (isLatest ? 4 : 2) : (isLatest ? 8 : 4),
                        tension: dataset.tension ?? 0.4,
                        fill: dataset.fill,
                        clip: false,
                        spanGaps: dataset.spanGaps ?? false,
                        borderDash: dataset.borderDash || [],
                    };
                });
            }

            function buildExportChartOptions(chart, isCompact = false) {
                const originalOptions = chart.options || {};
                const originalScales = originalOptions.scales || {};
                const yTicksCallback = originalScales.y?.ticks?.callback;
                const tooltipLabelCallback = originalOptions.plugins?.tooltip?.callbacks?.label;
                const fontScale = isCompact ? 0.68 : 1;

                return {
                    responsive: false,
                    maintainAspectRatio: false,
                    devicePixelRatio: 2,
                    animation: false,
                    events: [],
                    layout: {
                        padding: isCompact
                            ? { top: 14, right: 18, bottom: 18, left: 10 }
                            : { top: 36, right: 42, bottom: 42, left: 26 }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: isCompact ? 12 : 30,
                                boxWidth: isCompact ? 12 : 18,
                                boxHeight: isCompact ? 12 : 18,
                                font: { weight: 'bold', size: Math.round(25 * fontScale) }
                            }
                        },
                        tooltip: {
                            enabled: false,
                            callbacks: {
                                label: tooltipLabelCallback
                            }
                        }
                    },
                    scales: {
                        y: {
                            display: true,
                            beginAtZero: false,
                            min: originalScales.y?.min,
                            max: originalScales.y?.max,
                            grace: 0,
                            border: {
                                display: true,
                                color: '#cbd5e1'
                            },
                            title: {
                                display: true,
                                text: 'Value (Rp Miliar)',
                                color: '#334155',
                                font: { weight: 'bold', size: Math.round(24 * fontScale) }
                            },
                            grid: {
                                color: 'rgba(15, 23, 42, 0.08)',
                                drawTicks: true
                            },
                            ticks: {
                                maxTicksLimit: isCompact ? 5 : 7,
                                padding: isCompact ? 8 : 18,
                                display: true,
                                color: '#475569',
                                font: { size: Math.round(23 * fontScale), weight: '600' },
                                callback: yTicksCallback
                            }
                        },
                        x: {
                            display: true,
                            border: {
                                display: true,
                                color: '#cbd5e1'
                            },
                            grid: {
                                display: false,
                                drawTicks: true
                            },
                            ticks: {
                                display: true,
                                padding: isCompact ? 8 : 18,
                                color: '#475569',
                                font: { size: Math.round(23 * fontScale), weight: '600' },
                                autoSkip: true,
                                maxTicksLimit: isCompact ? 8 : 16
                            },
                            title: {
                                display: !isCompact,
                                text: 'Tanggal',
                                color: '#64748b',
                                padding: { top: 16 },
                                font: { size: Math.round(23 * fontScale), weight: 'bold' }
                            }
                        }
                    }
                };
            }

            async function renderChartForExport(chart, width, height, isCompact = false) {
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const exportChart = new Chart(canvas.getContext('2d'), {
                    type: chart.config.type || 'line',
                    data: {
                        labels: Array.isArray(chart.data.labels) ? chart.data.labels.slice() : chart.data.labels,
                        datasets: cloneChartDatasets(chart, isCompact)
                    },
                    options: buildExportChartOptions(chart, isCompact)
                });

                exportChart.resize(width, height);
                exportChart.update('none');
                await waitFrame();

                return { canvas, exportChart };
            }

            function drawRoundedRect(ctx, x, y, width, height, radius) {
                ctx.beginPath();
                ctx.moveTo(x + radius, y);
                ctx.lineTo(x + width - radius, y);
                ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
                ctx.lineTo(x + width, y + height - radius);
                ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
                ctx.lineTo(x + radius, y + height);
                ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
                ctx.lineTo(x, y + radius);
                ctx.quadraticCurveTo(x, y, x + radius, y);
                ctx.closePath();
            }

            function drawTextEllipsis(ctx, text, x, y, maxWidth) {
                const source = String(text || '');
                if (ctx.measureText(source).width <= maxWidth) {
                    ctx.fillText(source, x, y);
                    return;
                }

                let trimmed = source;
                while (trimmed.length > 0 && ctx.measureText(`${trimmed}...`).width > maxWidth) {
                    trimmed = trimmed.slice(0, -1);
                }
                ctx.fillText(`${trimmed}...`, x, y);
            }

            function drawExportHeader(ctx) {
                const { width, marginX, marginY } = A4_EXPORT;
                const category = document.querySelector('.category-btn.active')?.textContent?.trim() || '-';
                const periodSelect = document.getElementById('periodMonthFilter');
                const period = periodSelect?.options[periodSelect.selectedIndex]?.text || '-';
                const unit = unitLabel?.textContent?.trim() || 'Semua Unit';
                const kanca = kancaLabel?.textContent?.trim() || 'Semua Kanca';

                ctx.fillStyle = '#0857c3';
                ctx.fillRect(0, 0, width, 24);

                ctx.fillStyle = '#0f172a';
                ctx.font = 'bold 64px "Inter", "Segoe UI", Arial, sans-serif';
                ctx.fillText('Timeseries Analytics Dashboard', marginX, marginY + 35);

                ctx.fillStyle = '#475569';
                ctx.font = '600 30px "Inter", "Segoe UI", Arial, sans-serif';
                drawTextEllipsis(ctx, `Kategori: ${category}   |   Periode: ${period}`, marginX, marginY + 92, width - (marginX * 2));
                drawTextEllipsis(ctx, `Filter: ${kanca}   |   Unit: ${unit}`, marginX, marginY + 138, width - (marginX * 2) - 220);

                ctx.fillStyle = '#eaf2ff';
                drawRoundedRect(ctx, width - marginX - 190, marginY + 86, 190, 62, 18);
                ctx.fill();
                ctx.fillStyle = '#0857c3';
                ctx.font = 'bold 28px "Inter", "Segoe UI", Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('A4', width - marginX - 95, marginY + 126);
                ctx.textAlign = 'left';

                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(marginX, marginY + 178);
                ctx.lineTo(width - marginX, marginY + 178);
                ctx.stroke();
            }

            function drawExportFooter(ctx) {
                const { width, height, marginX } = A4_EXPORT;

                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(marginX, height - 82);
                ctx.lineTo(width - marginX, height - 82);
                ctx.stroke();

                ctx.fillStyle = '#94a3b8';
                ctx.font = '600 22px "Inter", "Segoe UI", Arial, sans-serif';
                ctx.fillText(`Generated ${new Date().toLocaleString('id-ID')}`, marginX, height - 42);
            }

            async function drawChartCard(ctx, entry, x, y, width, height, isCompact = false) {
                const radius = isCompact ? 20 : 28;
                const headerHeight = isCompact ? 82 : 116;
                const titleX = x + (isCompact ? 28 : 52);
                const titleY = y + (isCompact ? 52 : 74);
                const titleMaxWidth = width - (isCompact ? 56 : 390);

                ctx.save();
                ctx.shadowColor = 'rgba(15, 23, 42, 0.10)';
                ctx.shadowBlur = isCompact ? 16 : 28;
                ctx.shadowOffsetY = isCompact ? 7 : 12;
                ctx.fillStyle = '#ffffff';
                drawRoundedRect(ctx, x, y, width, height, radius);
                ctx.fill();
                ctx.restore();

                ctx.strokeStyle = '#dbeafe';
                ctx.lineWidth = 3;
                drawRoundedRect(ctx, x, y, width, height, radius);
                ctx.stroke();

                ctx.fillStyle = '#0f172a';
                ctx.font = `${isCompact ? 'bold 24px' : 'bold 36px'} "Inter", "Segoe UI", Arial, sans-serif`;
                drawTextEllipsis(ctx, entry.title, titleX, titleY, titleMaxWidth);

                if (!isCompact) {
                    ctx.fillStyle = '#eaf2ff';
                    drawRoundedRect(ctx, x + width - 310, y + 35, 250, 54, 20);
                    ctx.fill();
                    ctx.fillStyle = '#0857c3';
                    ctx.font = 'bold 21px "Inter", "Segoe UI", Arial, sans-serif';
                    ctx.textAlign = 'center';
                    drawTextEllipsis(ctx, entry.badge, x + width - 185, y + 70, 210);
                    ctx.textAlign = 'left';
                }

                ctx.strokeStyle = '#eef2f7';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(x, y + headerHeight);
                ctx.lineTo(x + width, y + headerHeight);
                ctx.stroke();

                const chartPaddingX = isCompact ? 24 : 55;
                const chartPaddingBottom = isCompact ? 22 : 55;
                const chartTop = y + headerHeight + (isCompact ? 10 : 19);
                const chartWidth = width - (chartPaddingX * 2);
                const chartHeight = height - headerHeight - chartPaddingBottom;
                const renderedChart = await renderChartForExport(entry.chart, chartWidth, chartHeight, isCompact);
                const chartCanvas = renderedChart.canvas;
                ctx.drawImage(chartCanvas, x + chartPaddingX, chartTop, chartWidth, chartHeight);
                renderedChart.exportChart.destroy();
            }

            function resolveA4LayoutEntries(chartEntries) {
                const summary = chartEntries.find(entry => entry.chart === charts.summary) || chartEntries[0];
                const branches = chartEntries
                    .filter(entry => entry !== summary)
                    .slice(0, 4);

                return { summary, branches };
            }

            if (captureBtn) {
                captureBtn.addEventListener('click', async function() {
                    const chartEntries = getVisibleChartEntries();
                    if (chartEntries.length === 0) return;

                    // Show Modal with Loading State
                    if (window.jQuery) {
                        window.jQuery(captureModal).modal('show');
                        progressUI.classList.remove('d-none');
                        errorUI.classList.add('d-none');
                        successUI.classList.add('d-none');
                    }

                    const originalBtnHtml = captureBtn.innerHTML;
                    captureBtn.disabled = true;
                    captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> CAPTURING...';

                    try {
                        const contentTop = A4_EXPORT.marginY + A4_EXPORT.headerHeight;
                        const contentHeight = A4_EXPORT.height - contentTop - A4_EXPORT.footerHeight - A4_EXPORT.marginY;
                        const cardWidth = A4_EXPORT.width - (A4_EXPORT.marginX * 2);
                        const summaryHeight = Math.floor((contentHeight - A4_EXPORT.sectionGap) / 2);
                        const branchGridTop = contentTop + summaryHeight + A4_EXPORT.sectionGap;
                        const branchGridHeight = contentHeight - summaryHeight - A4_EXPORT.sectionGap;
                        const branchCardWidth = Math.floor((cardWidth - A4_EXPORT.branchGap) / 2);
                        const branchCardHeight = Math.floor((branchGridHeight - A4_EXPORT.branchGap) / 2);
                        const category = sanitizeFilePart(document.querySelector('.category-btn.active')?.textContent || 'Timeseries');
                        const timestamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
                        const { summary, branches } = resolveA4LayoutEntries(chartEntries);
                        const pageCanvas = document.createElement('canvas');
                        pageCanvas.width = A4_EXPORT.width;
                        pageCanvas.height = A4_EXPORT.height;
                        const ctx = pageCanvas.getContext('2d');

                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, pageCanvas.width, pageCanvas.height);
                        drawExportHeader(ctx);

                        await drawChartCard(ctx, summary, A4_EXPORT.marginX, contentTop, cardWidth, summaryHeight);

                        for (let itemIndex = 0; itemIndex < branches.length; itemIndex++) {
                            const col = itemIndex % 2;
                            const row = Math.floor(itemIndex / 2);
                            const x = A4_EXPORT.marginX + (col * (branchCardWidth + A4_EXPORT.branchGap));
                            const y = branchGridTop + (row * (branchCardHeight + A4_EXPORT.branchGap));
                            await drawChartCard(ctx, branches[itemIndex], x, y, branchCardWidth, branchCardHeight, true);
                        }

                        drawExportFooter(ctx);

                        const link = document.createElement('a');
                        link.download = `Timeseries-A4-${category}-${timestamp}.jpg`;
                        link.href = pageCanvas.toDataURL('image/jpeg', 0.95);
                        link.click();

                        // Show Success UI
                        progressUI.classList.add('d-none');
                        successUI.classList.remove('d-none');
                    } catch (err) {
                        console.error('Stitching failure:', err);
                        progressUI.classList.add('d-none');
                        errorUI.classList.remove('d-none');
                        errorMessageUI.textContent = 'Gagal menyusun laporan A4. Pastikan seluruh grafik sudah muncul sempurna dan coba lagi.';
                    } finally {
                        captureBtn.disabled = false;
                        captureBtn.innerHTML = originalBtnHtml;
                    }
                });
            }

            // Set initial selected state in memory
            let activeKancas = new Set(selectedKancasInitial || []);

            function hasTimeseriesData(data) {
                return Boolean(data && Array.isArray(data.months) && data.months.length > 0);
            }

            function syncSummaryVisibility(selectedKanca) {
                const summaryContainer = document.getElementById('summaryChartContainer');
                const shouldShowSummary = selectedKanca.length === 0 || selectedKanca.length === totalArea6Count;

                if (summaryContainer) {
                    summaryContainer.style.display = shouldShowSummary ? 'block' : 'none';
                }

                return shouldShowSummary;
            }

            function setLoadingState(isLoading, shouldShowSummary) {
                const loading = document.getElementById('summaryLoading');
                if (loading) {
                    loading.style.display = isLoading && shouldShowSummary ? 'flex' : 'none';
                }

                if (applyBtn) {
                    applyBtn.disabled = isLoading;
                    applyBtn.innerHTML = isLoading
                        ? '<i class="fas fa-spinner fa-spin mr-2"></i> Memuat'
                        : '<i class="fas fa-sync-alt mr-2"></i> Update';
                }
            }

            // --- Dropdown Management ---
            function closeAllDropdowns() {
                if (kancaMenu) kancaMenu.classList.remove('show');
                if (unitMenu) unitMenu.classList.remove('show');
                if (typeof periodMenu !== 'undefined' && periodMenu) periodMenu.classList.remove('show');
                
                // Reset z-indices
                const kancaShell = document.getElementById('kancaDropdownShell');
                const unitShell = document.getElementById('unitDropdownShell');
                const periodShell = document.getElementById('periodDropdownShell');
                if (kancaShell) kancaShell.style.zIndex = '';
                if (unitShell) unitShell.style.zIndex = '';
                if (periodShell) periodShell.style.zIndex = '';
            }

            document.addEventListener('click', closeAllDropdowns);
            
            if (kancaMenu) kancaMenu.addEventListener('click', (e) => e.stopPropagation());
            if (unitMenu) unitMenu.addEventListener('click', (e) => e.stopPropagation());
            
            const periodMenu = document.getElementById('periodMenu');
            const periodLabel = document.getElementById('periodLabel');
            const periodToggle = document.getElementById('periodDropdown');
            const periodOptions = document.getElementById('periodOptions');
            const periodInput = document.getElementById('periodMonthFilter');

            if (periodMenu) periodMenu.addEventListener('click', (e) => e.stopPropagation());

            if (kancaToggle) {
                kancaToggle.addEventListener('click', (e) => {
                    console.log('Kanca Toggle Clicked');
                    e.stopPropagation();
                    const wasOpen = kancaMenu.classList.contains('show');
                    closeAllDropdowns();
                    if (!wasOpen) {
                        kancaMenu.classList.add('show');
                        const shell = document.getElementById('kancaDropdownShell');
                        if (shell) shell.style.zIndex = '1001';
                    }
                });
            }

            if (unitToggle) {
                unitToggle.addEventListener('click', (e) => {
                    console.log('Unit Toggle Clicked');
                    e.stopPropagation();
                    const wasOpen = unitMenu.classList.contains('show');
                    closeAllDropdowns();
                    if (!wasOpen) {
                        unitMenu.classList.add('show');
                        const shell = document.getElementById('unitDropdownShell');
                        if (shell) shell.style.zIndex = '1001';
                    }
                });
            }

            if (periodToggle) {
                periodToggle.addEventListener('click', (e) => {
                    console.log('Period Toggle Clicked');
                    e.stopPropagation();
                    const wasOpen = periodMenu.classList.contains('show');
                    closeAllDropdowns();
                    if (!wasOpen) {
                        periodMenu.classList.add('show');
                        const shell = document.getElementById('periodDropdownShell');
                        if (shell) shell.style.zIndex = '1001';
                    }
                });
            }

            // --- Period Filter Logic ---
            if (periodOptions) {
                periodOptions.querySelectorAll('.branch-option').forEach(opt => {
                    opt.addEventListener('click', () => {
                        const val = opt.getAttribute('data-value');
                        const label = opt.getAttribute('data-label');
                        
                        if (periodInput) periodInput.value = val;
                        if (periodLabel) periodLabel.textContent = label;
                        
                        periodOptions.querySelectorAll('.branch-option').forEach(o => o.classList.remove('selected'));
                        opt.classList.add('selected');
                        
                        closeAllDropdowns();
                    });
                });
            }

            // --- Kantor Cabang Logic ---
            function rebuildKancaOptions() {
                if (!kancaOptionsContainer) return;
                kancaOptionsContainer.innerHTML = '';
                
                allKancasData.forEach(k => {
                    if (k.value === 'all') return;
                    const opt = document.createElement('div');
                    opt.className = `branch-option ${activeKancas.has(k.value) ? 'selected' : ''}`;
                    opt.setAttribute('data-value', k.value);
                    opt.innerHTML = `
                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                        <span class="branch-option-label">${k.label}</span>
                    `;
                    opt.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (activeKancas.has(k.value)) {
                            activeKancas.delete(k.value);
                        } else {
                            activeKancas.add(k.value);
                        }
                        opt.classList.toggle('selected');
                        updateKancaLabel();
                    });
                    kancaOptionsContainer.appendChild(opt);
                });
                updateKancaLabel();
            }

            function updateKancaLabel() {
                const count = activeKancas.size;
                const total = allKancasData.filter(k => k.value !== 'all').length;
                
                if (count === 0) {
                    kancaLabel.textContent = 'Semua Kantor Cabang';
                } else if (count === total) {
                    kancaLabel.textContent = 'Semua Cabang Dipilih';
                } else if (count === 1) {
                    const firstVal = Array.from(activeKancas)[0];
                    const k = allKancasData.find(x => x.value === firstVal);
                    kancaLabel.textContent = k ? k.label : '1 Cabang Dipilih';
                } else {
                    kancaLabel.textContent = `${count} Cabang Dipilih`;
                }

                rebuildUnitOptions();
            }

            // --- Unit Dropdown Logic ---
            function rebuildUnitOptions() {
                if (!unitOptionsContainer) return;
                
                const currentUnit = unitInput ? unitInput.value : 'all';
                let foundCurrentUnit = currentUnit === 'all';
                let currentUnitLabel = 'Semua Unit Kerja';

                unitOptionsContainer.innerHTML = `
                    <div class="branch-option ${currentUnit === 'all' ? 'selected' : ''}" data-value="all">
                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                        <span class="branch-option-label">Semua Unit Kerja</span>
                    </div>
                `;

                allUnitsData.forEach(unit => {
                    if (unit.value === 'all') return;
                    // Show if no kanca selected or unit belongs to selected kanca
                    if (activeKancas.size === 0 || activeKancas.has(unit.kanca_value)) {
                        const opt = document.createElement('div');
                        opt.className = `branch-option ${unit.value === currentUnit ? 'selected' : ''}`;
                        opt.setAttribute('data-value', unit.value);
                        opt.innerHTML = `
                            <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                            <span class="branch-option-label">${unit.label}</span>
                        `;
                        
                        if (unit.value === currentUnit) {
                            foundCurrentUnit = true;
                            currentUnitLabel = unit.label;
                        }

                        opt.addEventListener('click', (e) => {
                            e.stopPropagation();
                            selectUnit(unit.value, unit.label);
                        });
                        unitOptionsContainer.appendChild(opt);
                    }
                });

                const allOpt = unitOptionsContainer.querySelector('[data-value="all"]');
                if (allOpt) {
                    allOpt.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectUnit('all', 'Semua Unit Kerja');
                    });
                }

                if (!foundCurrentUnit) {
                    selectUnit('all', 'Semua Unit Kerja');
                } else if (unitLabel) {
                    unitLabel.textContent = currentUnitLabel;
                }
            }

            function selectUnit(value, label) {
                if (unitInput) unitInput.value = value;
                if (unitLabel) unitLabel.textContent = label;
                closeAllDropdowns();
                
                if (unitOptionsContainer) {
                    unitOptionsContainer.querySelectorAll('.branch-option').forEach(o => {
                        o.classList.toggle('selected', o.getAttribute('data-value') === value);
                    });
                }
            }

            // --- Core Logic ---
            async function fetchData() {
                console.log('Fetching Data for Kancas:', Array.from(activeKancas));
                const selectedKanca = Array.from(activeKancas);
                const unit = unitInput ? unitInput.value : 'all';
                const periodMonth = periodMonthSelect ? periodMonthSelect.value : '';
                const requestId = ++activeRequestId;
                const shouldShowSummary = syncSummaryVisibility(selectedKanca);
                setLoadingState(true, shouldShowSummary);

                try {
                    const queryParams = new URLSearchParams({
                        category: currentCategory,
                        unit_kerja: unit,
                        period_month: periodMonth
                    });
                    selectedKanca.forEach(k => queryParams.append('kanca[]', k));

                    const response = await fetch(`${routes.data}?${queryParams.toString()}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json();
                    if (requestId !== activeRequestId) return;

                    if (!hasTimeseriesData(data)) {
                        renderEmptyChart();
                        return;
                    }

                    renderCharts(data, selectedKanca.length);
                } catch (error) {
                    console.error('Failed to fetch timeseries data:', error);
                    renderEmptyChart();
                } finally {
                    if (requestId === activeRequestId) {
                        setLoadingState(false, shouldShowSummary);
                    }
                }
            }

            function renderEmptyChart() {
                Object.values(charts).forEach(c => { try { c.destroy(); } catch(e) {} });
                charts = {};
                const container = document.getElementById('individualChartsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="col-12 text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <h4>Tidak ada data untuk filter terpilih</h4>
                                <p>Silakan sesuaikan filter atau pilih kantor cabang lain.</p>
                            </div>
                        </div>
                    `;
                }
            }

            const monthColors = [
                { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.05)' },
                { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.05)' },
                { border: '#10b981', bg: 'rgba(16, 185, 129, 0.05)' },
                { border: '#0857c3', bg: 'rgba(8, 87, 195, 0.1)' },
            ];

            function resolveYAxisBounds(datasets, isSummary = false) {
                const values = datasets
                    .flatMap(dataset => Array.isArray(dataset.data) ? dataset.data : [])
                    .filter(value => value !== null && value !== undefined && value !== '')
                    .map(value => typeof value === 'number' ? value : Number(value))
                    .filter(value => Number.isFinite(value));

                if (values.length === 0) {
                    return {};
                }

                const min = Math.min(...values);
                const max = Math.max(...values);
                const naturalSpread = max - min;
                const minRange = Math.max(Math.abs(max) * (isSummary ? 0.025 : 0.015), isSummary ? 25 : 10, 1);
                const effectiveSpread = Math.max(naturalSpread, minRange);
                const center = (min + max) / 2;
                const pad = effectiveSpread * 0.12;
                const rawMin = naturalSpread < minRange
                    ? center - (minRange / 2) - pad
                    : min - pad;
                const rawMax = naturalSpread < minRange
                    ? center + (minRange / 2) + pad
                    : max + pad;

                return {
                    min: min >= 0 ? Math.max(0, rawMin) : rawMin,
                    max: rawMax,
                };
            }

            function createChartConfig(title, months, datasets, isSummary = false) {
                const yAxisBounds = resolveYAxisBounds(datasets, isSummary);

                return {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 31}, (_, i) => i + 1),
                        datasets: datasets.map((d, i) => {
                            const isLatest = i === datasets.length - 1;
                            return {
                                label: d.label,
                                data: d.data,
                                borderColor: monthColors[i % monthColors.length].border,
                                backgroundColor: monthColors[i % monthColors.length].bg,
                                borderWidth: isLatest ? 4 : 2.5,
                                pointRadius: isLatest ? 4.5 : 2,
                                pointHoverRadius: 8,
                                tension: 0.4,
                                fill: isLatest,
                                clip: false,
                                spanGaps: false,
                                borderDash: isLatest ? [] : [5, 3]
                            };
                        })
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        devicePixelRatio: 2.5,
                        layout: {
                            padding: {
                                top: isSummary ? 16 : 24,
                                right: isSummary ? 16 : 26,
                                bottom: isSummary ? 36 : 30,
                                left: isSummary ? 12 : 12
                            }
                        },
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: { weight: 'bold', size: 10 }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                                padding: 12,
                                titleFont: { size: 13, weight: 'bold' },
                                bodyFont: { size: 12 },
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('id-ID').format(context.parsed.y) + ' Rp M';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                display: true,
                                beginAtZero: false,
                                min: yAxisBounds.min,
                                max: yAxisBounds.max,
                                grace: 0,
                                border: {
                                    display: true,
                                    color: '#cbd5e1'
                                },
                                title: {
                                    display: true,
                                    text: 'Value (Rp Miliar)',
                                    font: { weight: 'bold', size: 10 }
                                },
                                grid: {
                                    color: 'rgba(15, 23, 42, 0.07)',
                                    drawTicks: true
                                },
                                ticks: {
                                    maxTicksLimit: isSummary ? 7 : 6,
                                    padding: 10,
                                    display: true,
                                    font: { size: 10 },
                                    callback: function(value) {
                                        const scale = this.chart.scales.y;
                                        const spread = Math.abs(scale.max - scale.min);
                                        let precision = 0;
                                        if (spread < 2) precision = 2;
                                        else if (spread < 20) precision = 1;

                                        return new Intl.NumberFormat('id-ID', { 
                                            maximumFractionDigits: precision,
                                            minimumFractionDigits: (spread < 20 && value % 1 !== 0) ? precision : 0
                                        }).format(value);
                                    }
                                }
                            },
                            x: {
                                display: true,
                                border: {
                                    display: true,
                                    color: '#cbd5e1'
                                },
                                grid: {
                                    display: false,
                                    drawTicks: true
                                },
                                ticks: {
                                    display: true,
                                    padding: 10,
                                    font: { size: 10 },
                                    autoSkip: true,
                                    maxTicksLimit: isSummary ? 16 : 12
                                },
                                title: {
                                    display: isSummary,
                                    text: 'Tanggal',
                                    color: '#64748b',
                                    padding: { top: 10 },
                                    font: { size: 10, weight: 'bold' }
                                }
                            }
                        }
                    }
                };
            }

            function getMonthName(monthStr) {
                const date = new Date(monthStr + '-01');
                return date.toLocaleString('id-ID', { month: 'long', year: 'numeric' });
            }

            function renderCharts(data, selectedCount) {
                Object.values(charts).forEach(c => { try { c.destroy(); } catch(e) {} });
                charts = {};

                const summaryContainer = document.getElementById('summaryChartContainer');
                if (summaryContainer && summaryContainer.style.display !== 'none') {
                    const summaryCanvas = document.getElementById('summaryChart');
                    if (!summaryCanvas) return;

                    const summaryCtx = summaryCanvas.getContext('2d');
                    const summaryDatasets = data.months.map(month => ({
                        label: getMonthName(month),
                        data: data.area_total[month] || new Array(31).fill(null)
                    }));
                    charts['summary'] = new Chart(summaryCtx, createChartConfig('Total Area', data.months, summaryDatasets, true));
                    
                    const badge = document.getElementById('summaryChartBadge');
                    if (badge) {
                        badge.textContent = selectedCount === 0 ? 'Total Konsolidasi Area 6' : 'Total Konsolidasi (4 Cabang Dipilih)';
                    }
                }

                const container = document.getElementById('individualChartsContainer');
                if (container) {
                    container.innerHTML = '';
                    const branchNames = Object.keys(data.series || {}).sort();
                    if (branchNames.length === 0) {
                        renderEmptyChart();
                        return;
                    }

                    const isFullWidth = branchNames.length === 1;
                    const unitSuffix = (unitInput && unitInput.value !== 'all') ? unitLabel.textContent : 'Konsolidasi';

                    branchNames.forEach(branch => {
                        const col = document.createElement('div');
                        col.className = isFullWidth ? 'col-12 mb-4' : 'col-lg-6 mb-3';
                        const canvasId = `chart_${branch.replace(/[^\w-]/g, '_')}`;
                        const displayTitle = `${branch} - ${unitSuffix}`;
                        
                        col.innerHTML = `
                            <div class="card chart-card">
                                <div class="chart-header">
                                    <h5 class="chart-title">${displayTitle}</h5>
                                    <div class="d-flex align-items-center">
                                        <span class="unit-badge">Daily Trend</span>
                                        <button class="btn-export-jpg ml-2" onclick="window.downloadTimeseriesChart('${branch}', 'Timeseries-${branch.replace(/[^\w-]/g, '_')}')" title="Export to JPG">
                                            <i class="fas fa-camera"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="chart-body branch-chart-body ${isFullWidth ? 'tall' : ''}">
                                    <div class="chart-canvas-frame">
                                        <canvas id="${canvasId}"></canvas>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(col);

                        const ctx = document.getElementById(canvasId).getContext('2d');
                        const datasets = data.months.map(month => ({
                            label: getMonthName(month),
                            data: (data.series[branch] && data.series[branch][month]) ? data.series[branch][month] : new Array(31).fill(null)
                        }));
                        charts[branch] = new Chart(ctx, createChartConfig(branch, data.months, datasets));
                    });
                }
            }

            // Category Selector
            const categoryBtns = document.querySelectorAll('.category-btn');
            categoryBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    categoryBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentCategory = this.getAttribute('data-value');
                    fetchData();
                });
            });

            if (applyBtn) {
                applyBtn.addEventListener('click', fetchData);
            }

            if (periodMonthSelect) {
                periodMonthSelect.addEventListener('change', fetchData);
            }

            // Initial Initialization
            rebuildKancaOptions();
            syncSummaryVisibility(Array.from(activeKancas));
            if (hasTimeseriesData(initialTimeseriesData)) {
                renderCharts(initialTimeseriesData, activeKancas.size);
                setLoadingState(false, syncSummaryVisibility(Array.from(activeKancas)));
            } else {
                fetchData();
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();

</script>
@endsection
