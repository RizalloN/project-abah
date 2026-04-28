@extends('layouts.admin')

@section('title', 'Rasio CASA Debitur')

@section('content')
<style>
    :root {
        --primary-blue: #1e40af; /* blue-800 */
        --primary-blue-light: #3b82f6; /* blue-500 */
        --primary-blue-dark: #1e3a8a; /* blue-900 */
        --surface-color: #ffffff;
        --bg-color: #f8fafc; /* slate-50 */
        --border-color: #e2e8f0; /* slate-200 */
        --text-main: #0f172a; /* slate-900 */
        --text-muted: #64748b; /* slate-500 */
        --table-header-bg: var(--primary-blue-dark);
        --table-header-text: #ffffff;
    }

    .casa-dashboard {
        padding-bottom: 1.5rem;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    .casa-shell,
    .casa-table-shell {
        border: 1px solid var(--border-color);
        border-radius: 16px;
        background: var(--surface-color);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        transition: box-shadow 0.3s ease;
    }

    .casa-shell {
        overflow: visible;
    }

    .casa-shell .card-body,
    .casa-table-shell .card-header,
    .casa-table-shell .card-body {
        background: var(--surface-color);
        border-radius: 16px;
    }

    .casa-page-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary-blue-dark);
        margin-bottom: 0.35rem;
    }

    .casa-page-copy {
        color: var(--text-muted);
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    .casa-filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .casa-filter-control {
        border-radius: 8px !important;
        min-height: 42px !important;
        height: 42px !important;
        border: 1px solid var(--border-color) !important;
        background: var(--surface-color) !important;
        font-size: 0.94rem;
        display: flex;
        align-items: center;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .casa-filter-control:focus {
        border-color: var(--primary-blue-light) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
        outline: none;
    }

    .casa-filter-control:disabled {
        background: #f1f5f9 !important; /* slate-100 */
        color: var(--text-muted) !important;
        cursor: not-allowed;
    }

    .branch-filter-dropdown,
    .uker-filter-dropdown {
        position: relative;
    }

    .casa-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        background: var(--surface-color);
        font-weight: 500;
    }

    .casa-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .casa-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 1050;
        display: none;
        width: 100%;
        max-height: 260px;
        overflow-y: auto;
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        padding: 8px 0;
    }

    .casa-dropdown-menu.show {
        display: block;
    }

    .casa-dropdown-menu .dropdown-item {
        padding: 0.5rem 1rem;
        cursor: pointer;
        margin-bottom: 0;
        transition: background-color 0.2s ease;
    }
    
    .casa-dropdown-menu .dropdown-item:hover {
        background-color: #f1f5f9;
    }

    .casa-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .casa-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
        cursor: pointer;
    }

    .casa-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 500;
        cursor: pointer;
    }

    .casa-filter-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }

    .casa-action {
        min-width: 150px;
        min-height: 42px;
        border-radius: 8px;
        font-weight: 600;
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
        transition: all 0.2s ease;
    }
    
    .casa-action:hover {
        background-color: var(--primary-blue-dark);
        border-color: var(--primary-blue-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
    }

    .casa-loading-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        border-radius: 999px;
        padding: 0.4rem 0.85rem;
        background: #eff6ff; /* blue-50 */
        color: var(--primary-blue);
        font-size: 0.8rem;
        font-weight: 700;
        border: 1px solid #bfdbfe; /* blue-200 */
    }

    .casa-loading-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--primary-blue-light);
        animation: casaPulse 1.6s infinite;
    }

    .mtd-icon {
        font-size: 0.8em;
    }

    @keyframes casaPulse {
        0% { transform: scale(0.95); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(0.95); opacity: 0.5; }
    }

    .casa-table-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .casa-table-heading h5 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
    }

    .casa-table-heading p {
        margin: 0.25rem 0 0;
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .casa-table-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 8px;
        padding: 0.45rem 0.75rem;
        background: #f8fafc;
        border: 1px solid var(--border-color);
        color: var(--text-main);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .casa-table-stage {
        position: relative;
        min-height: 520px;
    }

    .casa-loading-overlay {
        position: absolute;
        inset: 0;
        z-index: 20;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        justify-content: center;
        align-items: center;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(4px);
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .casa-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .casa-loading-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
    }

    .casa-loading-copy {
        max-width: 520px;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin: 0;
    }

    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    
    .table-container::-webkit-scrollbar {
        height: 8px;
    }
    .table-container::-webkit-scrollbar-track {
        background: transparent;
    }
    .table-container::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }

    .table-report {
        border-collapse: separate;
        border-spacing: 0;
        width: max-content;
        min-width: 100%;
        table-layout: auto;
        white-space: nowrap;
        margin: 0;
    }

    .table-report th,
    .table-report td {
        vertical-align: middle !important;
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
        padding: 0.6rem 1rem; /* Compact padding */
    }
    
    .table-report th:last-child, .table-report td:last-child {
        border-right: none;
    }

    .table-report th {
        font-size: 0.75rem; /* Slightly larger for clarity */
        text-align: center;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .table-report td {
        font-size: 0.8rem;
        text-align: right;
        background: var(--surface-color);
        font-variant-numeric: tabular-nums;
        transition: background-color 0.15s ease;
    }

    .table-report td.text-left {
        text-align: left;
    }

    /* Sticky First Column */
    .table-report .sticky-col {
        position: sticky;
        left: 0;
        background: #ffffff;
        z-index: 8;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        font-weight: 600;
        text-align: left;
    }

    .bg-header-main {
        background: var(--table-header-bg) !important;
        color: var(--table-header-text) !important;
        border-bottom: 2px solid rgba(0,0,0,0.1) !important;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .bg-header-main.sticky-col {
        z-index: 11;
        box-shadow: none;
    }

    .bg-header-sub {
        background: #274bba !important; /* Lighter blue */
        color: var(--table-header-text) !important;
        border-bottom: 2px solid rgba(0,0,0,0.1) !important;
        position: sticky;
        top: 38px; /* Adjusted for compact padding */
        z-index: 9;
    }

    .bg-header-sub-light {
        background: #3b82f6 !important; /* Even lighter blue */
        color: var(--table-header-text) !important;
        border-bottom: 2px solid rgba(0,0,0,0.1) !important;
        position: sticky;
        top: 76px; /* Adjusted for compact padding */
        z-index: 9;
    }

    .table-hover tbody tr:hover td { 
        background-color: #f1f5f9; 
    }
    .table-hover tbody tr:hover .sticky-col {
        background-color: #f1f5f9;
    }

    .row-total td {
        background-color: var(--table-header-bg) !important;
        color: var(--table-header-text) !important;
        font-weight: 700;
        border-top: 2px solid var(--primary-blue-light) !important;
    }

    .row-total:hover td {
        background-color: var(--table-header-bg) !important;
        color: var(--table-header-text) !important;
    }

    .loading-row td {
        text-align: center !important;
        color: var(--text-muted);
        font-style: italic;
        padding: 2.5rem 1rem !important;
    }

    .val-up { color: #16a34a; /* green-600 */ font-weight: 600; }
    .val-down { color: #dc2626; /* red-600 */ font-weight: 600; }

    .ratio-positive {
        background-color: #dcfce7 !important;
        color: #198754 !important;
        font-weight: bold;
    }

    .ratio-negative {
        background-color: #fee2e2 !important;
        color: #dc3545 !important;
        font-weight: bold;
    }

    .ratio-neutral {
        color: var(--text-muted) !important;
        font-weight: 600;
    }

    .nav-tabs.report-tabs {
        border-bottom: 1px solid var(--border-color);
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        scrollbar-width: none; /* Hide scrollbar but still scrollable */
        -ms-overflow-style: none;
    }

    .nav-tabs.report-tabs .nav-link {
        border: none;
        font-weight: 600;
        color: var(--text-muted);
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
        background: transparent;
        transition: all 0.2s ease;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px; /* Seamless tab integration */
    }

    .nav-tabs.report-tabs .nav-link.active {
        border-bottom: 2px solid var(--primary-blue-light);
        color: var(--primary-blue-light);
        background: transparent;
    }

    .nav-tabs.report-tabs .nav-link:hover:not(.active) {
        border-bottom: 2px solid var(--border-color);
        color: var(--text-main);
    }

    .nav-tabs.report-tabs::-webkit-scrollbar {
        display: none;
    }

    @media (max-width: 767.98px) {
        .casa-action {
            width: 100%;
        }
        .casa-filter-meta {
            gap: 0.45rem 0.9rem;
        }
    }
</style>
@include('report._bri-report-ui')
<style>
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr:hover > td,
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr:hover > th {
        background-color: #ffffff !important;
        background-image: none !important;
    }
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr:hover > .sticky-col {
        background-color: #ffffff !important;
    }
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr:hover > td.ratio-positive {
        background-color: #dcfce7 !important;
    }
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr:hover > td.ratio-negative {
        background-color: #fee2e2 !important;
    }
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr.row-total > td,
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr.row-total > th,
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr.row-total:hover > td,
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr.row-total:hover > th {
        background-color: var(--table-header-bg) !important;
        background-image: none !important;
        color: var(--table-header-text) !important;
        font-weight: 700;
    }
    .content-wrapper .table-container table.table-report.casa-no-hover tbody tr.row-total:hover > .sticky-col {
        background-color: var(--table-header-bg) !important;
    }
</style>

<div class="casa-dashboard pt-4">
    <div class="card card-outline card-primary shadow-sm mb-4 casa-shell">
        <div class="card-body p-4">
            <div class="d-none">
                <h1 class="casa-page-title"><i class="fas fa-percentage text-primary mr-2"></i>Rasio CASA Debitur</h1>
                <p class="casa-page-copy">Pilih periode akhir lalu klik <strong>Tampilkan</strong> untuk menjalankan query dan memuat ringkasan rasio CASA per branch.</p>
            </div>

            <form id="filterForm">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <div class="form-group mb-3 mb-md-0">
                            <label class="casa-filter-label">Periode Akhir</label>
                            <input type="date" id="filter_posisi" name="posisi" class="form-control casa-filter-control" value="{{ $defaultPeriod ?? date('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3 mb-md-0">
                            <label class="casa-filter-label">Branch Office (Kanca)</label>
                            <div class="branch-filter-dropdown">
                                <button type="button" class="form-control casa-filter-control casa-dropdown-toggle" id="filterBranchDropdown" aria-haspopup="true" aria-expanded="false">
                                    <span id="filter_branch_office_label" class="casa-dropdown-label">Area 6 - All</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div class="casa-dropdown-menu" id="filterBranchMenu" aria-labelledby="filterBranchDropdown">
                                    @forelse(($branchOptions ?? collect()) as $branchOption)
                                        <label class="dropdown-item" for="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                            <div class="form-check">
                                                <input class="form-check-input filter-branch-checkbox" type="checkbox" value="{{ $branchOption }}" id="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                                <span class="form-check-label">{{ $branchOption }}</span>
                                            </div>
                                        </label>
                                    @empty
                                        <div class="dropdown-item text-muted small px-3">Data branch belum tersedia</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3 mb-md-0">
                            <label class="casa-filter-label">Nama Uker</label>
                            <div class="uker-filter-dropdown">
                                <button type="button" class="form-control casa-filter-control casa-dropdown-toggle" id="filterUkerDropdown" aria-haspopup="true" aria-expanded="false" disabled>
                                    <span id="filter_nama_uker_label" class="casa-dropdown-label">ALL UKER</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div class="casa-dropdown-menu" id="filterUkerMenu" aria-labelledby="filterUkerDropdown"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" id="submitButton" class="btn btn-primary btn-block casa-action">
                            <i class="fas fa-play mr-2"></i> Tampilkan Data
                        </button>
                    </div>
                </div>
            </form>

            <div class="casa-filter-meta">
                <span><i class="fas fa-info-circle text-primary mr-1"></i> <strong>Mode:</strong> Manual Query</span>
                <span><i class="fas fa-map-marker-alt text-primary mr-1"></i> <strong>Area:</strong> KC Madiun, Magetan, Ngawi, Ponorogo</span>
                <span id="filterMetaPeriod"><i class="fas fa-clock text-primary mr-1"></i> <strong>Periode aktif:</strong> Belum dijalankan</span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4 casa-table-shell">
        <div class="card-body p-4">
            <div class="casa-table-heading">
                <div>
                    <h5><i class="fas fa-table text-primary mr-2"></i>Ringkasan Rasio CASA</h5>
                    <p>Query akan berjalan setelah filter dikirim. Data ditampilkan per branch dan dikelompokkan ke tab sesuai segmen.</p>
                </div>
                <div class="d-flex align-items-center flex-wrap justify-content-end" style="gap: 0.75rem;">
                    <span id="loadingChip" class="casa-loading-chip d-none">
                        <span class="casa-loading-dot"></span>
                        Memproses query...
                    </span>
                    <span id="summaryBadge" class="casa-table-badge"><i class="fas fa-info-circle text-muted"></i> Belum ada data</span>
                </div>
            </div>

            <div class="casa-table-stage">
                <div id="tableOverlay" class="casa-loading-overlay">
                    <div class="text-center mb-3">
                        <i class="fas fa-chart-bar fa-3x text-primary opacity-50 mb-3"></i>
                        <div class="casa-loading-title" id="overlayTitle">Siap Memuat Data</div>
                        <p class="casa-loading-copy" id="overlayCopy">Pilih periode akhir lalu klik <strong>Tampilkan Data</strong> untuk menjalankan query rasio CASA debitur.</p>
                    </div>
                </div>

                <ul class="nav nav-tabs report-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab">
                            <i class="fas fa-chart-pie mr-1"></i> Total
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-briguna-kpr" role="tab">
                            <i class="fas fa-home mr-1"></i> BRIGUNA & KPR
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-mikro-smc" role="tab">
                            <i class="fas fa-store mr-1"></i> MIKRO & SMC
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-per-rm" role="tab">
                            <i class="fas fa-user-tie mr-1"></i> Per RM
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-total" role="tabpanel">
                        <!-- Section Konsolidasi -->
                        <div id="section-konsolidasi">
                            <h6 class="mt-4 mb-2 font-weight-bold text-primary section-title-extra d-none">
                                <i class="fas fa-layer-group mr-1"></i> KONSOLIDASI (ALL UNIT)
                            </h6>
                            <div class="table-container">
                                <table class="table table-report casa-no-hover m-0">
                                    <thead>
                                        <tr>
                                            <th rowspan="3" class="bg-header-main sticky-col align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 170px;">BRANCH OFFICE</th>
                                            <th colspan="7" class="bg-header-main">TOTAL</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <tr class="bg-header-sub-light">
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-total"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Section Ritel -->
                        <div id="section-ritel" class="d-none">
                            <h6 class="mt-4 mb-2 font-weight-bold text-primary section-title-extra">
                                <i class="fas fa-building mr-1"></i> RITEL (KC & KCP)
                            </h6>
                            <div class="table-container">
                                <table class="table table-report casa-no-hover m-0">
                                    <thead>
                                        <tr>
                                            <th rowspan="3" class="bg-header-main sticky-col align-middle col-group-label" style="min-width: 170px;">UKER RITEL</th>
                                            <th colspan="7" class="bg-header-main">TOTAL RITEL</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <tr class="bg-header-sub-light">
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-ritel"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Section Mikro -->
                        <div id="section-micro" class="d-none">
                            <h6 class="mt-4 mb-2 font-weight-bold text-primary section-title-extra">
                                <i class="fas fa-store mr-1"></i> MIKRO (UNIT ONLY)
                            </h6>
                            <div class="table-container">
                                <table class="table table-report casa-no-hover m-0">
                                    <thead>
                                        <tr>
                                            <th rowspan="3" class="bg-header-main sticky-col align-middle col-group-label" style="min-width: 170px;">UKER MIKRO</th>
                                            <th colspan="7" class="bg-header-main">TOTAL MIKRO</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <tr class="bg-header-sub-light">
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th class="lbl-prev-th">-</th>
                                            <th class="lbl-curr-th">-</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-micro"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-briguna-kpr" role="tabpanel">
                        <div class="table-container">
                            <table class="table table-report casa-no-hover m-0">
                                <thead>
                                    <tr>
                                        <th rowspan="3" class="bg-header-main sticky-col align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 170px;">BRANCH OFFICE</th>
                                        <th colspan="7" class="bg-header-main">BRIGUNA</th>
                                        <th colspan="7" class="bg-header-main" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">KPR</th>
                                    </tr>
                                    <tr class="bg-header-sub">
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                        <th colspan="2" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                    </tr>
                                    <tr class="bg-header-sub-light">
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                        <th class="lbl-prev-th" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-briguna-kpr"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-mikro-smc" role="tabpanel">
                        <div class="table-container">
                            <table class="table table-report casa-no-hover m-0">
                                <thead>
                                    <tr>
                                        <th rowspan="3" class="bg-header-main sticky-col align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 170px;">BRANCH OFFICE</th>
                                        <th colspan="7" class="bg-header-main">MIKRO</th>
                                        <th colspan="7" class="bg-header-main" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">SMC</th>
                                    </tr>
                                    <tr class="bg-header-sub">
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                        <th colspan="2" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                    </tr>
                                    <tr class="bg-header-sub-light">
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                        <th class="lbl-prev-th" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-mikro-smc"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-per-rm" role="tabpanel">
                        <div class="mb-3 p-3 bg-light rounded" style="border: 1px solid #e2e8f0;">
                            <form id="filterFormPerRm" class="row align-items-end" style="gap: 1rem;">
                                <div class="col-md-3">
                                    <label class="casa-filter-label">Cabang</label>
                                    <select id="filter_cabang1_rm" name="cabang1" class="form-control casa-filter-control">
                                        <option value="">Pilih Cabang</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="casa-filter-label">Unit Kerja</label>
                                    <select id="filter_unit1_rm" name="unit1" class="form-control casa-filter-control">
                                        <option value="">Pilih Unit Kerja</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" id="submitButtonPerRm" class="btn btn-primary btn-block">
                                        <i class="fas fa-play mr-2"></i> Proses
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <span id="loadingChipPerRm" class="casa-loading-chip d-none w-100 justify-content-center">
                                        <span class="casa-loading-dot"></span>
                                        Memproses...
                                    </span>
                                </div>
                            </form>
                        </div>

                        <div class="table-container">
                            <table class="table table-report casa-no-hover m-0">
                                <thead>
                                    <tr>
                                        <th rowspan="3" class="bg-header-main sticky-col align-middle col-group-label" style="min-width: 170px;">RM / MANTRI</th>
                                        <th colspan="7" class="bg-header-main">TOTAL</th>
                                    </tr>
                                    <tr class="bg-header-sub">
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                    </tr>
                                    <tr class="bg-header-sub-light">
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-per-rm"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filterForm');
    const submitButton = document.getElementById('submitButton');
    const loadingChip = document.getElementById('loadingChip');
    const summaryBadge = document.getElementById('summaryBadge');
    const filterMetaPeriod = document.getElementById('filterMetaPeriod');
    const overlay = document.getElementById('tableOverlay');
    const overlayTitle = document.getElementById('overlayTitle');
    const overlayCopy = document.getElementById('overlayCopy');
    const filterPosisi = document.getElementById('filter_posisi');
    const branchUkerMap = @json($branchUkerMap ?? []);
    let activeRequest = null;

    function getSelectedBranches() {
        return $('.filter-branch-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function getSelectedUkers() {
        return $('.filter-uker-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function getAvailableUkers() {
        const selectedBranches = getSelectedBranches();
        if (!selectedBranches.length) {
            return [];
        }

        const ukerSet = new Set();
        selectedBranches.forEach(function (branch) {
            (branchUkerMap[branch] || []).forEach(function (uker) {
                if (uker) {
                    ukerSet.add(uker);
                }
            });
        });

        return Array.from(ukerSet).sort(function (a, b) {
            return a.localeCompare(b, 'id');
        });
    }

    function updateBranchLabel() {
        const selectedBranches = getSelectedBranches();
        $('#filter_branch_office_label').text(selectedBranches.length ? selectedBranches.join(', ') : 'Area 6 - All');
    }

    function updateUkerLabel() {
        const selectedUkers = getSelectedUkers();
        $('#filter_nama_uker_label').text(selectedUkers.length ? selectedUkers.join(', ') : 'ALL UKER');
    }

    function closeBranchDropdown() {
        $('#filterBranchMenu').removeClass('show');
        $('#filterBranchDropdown').attr('aria-expanded', 'false');
    }

    function closeUkerDropdown() {
        $('#filterUkerMenu').removeClass('show');
        $('#filterUkerDropdown').attr('aria-expanded', 'false');
    }

    function updateGroupLabel(label) {
        const normalizedLabel = (label || 'BRANCH OFFICE').toUpperCase();
        $('.col-group-label').each(function () {
            const $label = $(this);
            const nextText = normalizedLabel === 'UKER'
                ? ($label.data('filtered-label') || 'UKER')
                : ($label.data('default-label') || 'BRANCH OFFICE');
            $label.text(nextText);
        });
    }

    function syncNamaUkerOptions() {
        const availableUkers = getAvailableUkers();
        const selectedUkers = getSelectedUkers();
        const ukerMenuElement = document.getElementById('filterUkerMenu');
        ukerMenuElement.innerHTML = '';

        // Use DocumentFragment untuk batch DOM insertion
        const fragment = document.createDocumentFragment();
        
        availableUkers.forEach(function (uker, index) {
            const safeId = uker.toLowerCase().replace(/[^a-z0-9]+/g, '_');
            const isChecked = selectedUkers.includes(uker) ? 'checked' : '';
            
            const label = document.createElement('label');
            label.className = 'dropdown-item';
            label.setAttribute('for', `uker_${safeId}`);
            label.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input filter-uker-checkbox" type="checkbox" value="${$('<div>').text(uker).html()}" id="uker_${safeId}" ${isChecked}>
                    <span class="form-check-label">${$('<div>').text(uker).html()}</span>
                </div>
            `;
            fragment.appendChild(label);
        });
        
        ukerMenuElement.appendChild(fragment);

        const shouldDisable = availableUkers.length === 0;
        if (shouldDisable) {
            closeUkerDropdown();
        }
        document.getElementById('filterUkerDropdown').disabled = shouldDisable;
        document.getElementById('filterUkerDropdown').setAttribute('aria-expanded', 'false');
        updateUkerLabel();
    }

    function formatNum(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(parseFloat(num));
    }

    function formatPct(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(num)) + '%';
    }

    function getRatioClass(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '';
        const val = parseFloat(num);
        if (val < 0) return 'ratio-negative';
        if (val > 0) return 'ratio-positive';
        return 'ratio-neutral';
    }

    function formatMtd(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        const val = parseFloat(num);
        const text = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val) + '%';
        if (val > 0) return `<span class="val-up"><i class="fas fa-arrow-up mr-1 mtd-icon"></i>${text}</span>`;
        if (val < 0) return `<span class="val-down"><i class="fas fa-arrow-down mr-1 mtd-icon"></i>${text}</span>`;
        return text;
    }

    function createDataCells(dt, isSeparator = false) {
        dt = dt || {};
        const rasioPrevClass = getRatioClass(dt.rasio_prev);
        const rasioCurrClass = getRatioClass(dt.rasio_curr);
        const mtdClass = getRatioClass(dt.mtd);
        const sepStyle = isSeparator ? 'border-left: 2px solid rgba(0,0,0,0.1) !important;' : '';

        return `
            <td style="${sepStyle}">${formatNum(dt.os_prev)}</td>
            <td style="background-color: #f8fafc;">${formatNum(dt.os_curr)}</td>
            <td>${formatNum(dt.casa_prev)}</td>
            <td style="background-color: #f8fafc;">${formatNum(dt.casa_curr)}</td>
            <td class="${rasioPrevClass}">${formatPct(dt.rasio_prev)}</td>
            <td class="font-weight-bold ${rasioCurrClass}">${formatPct(dt.rasio_curr)}</td>
            <td class="${mtdClass}">${formatMtd(dt.mtd)}</td>
        `;
    }

    function updateTableLabels(prev, curr) {
        $('.lbl-prev-th').text(prev || '-');
        $('.lbl-curr-th').text(curr || '-');
    }

    function setOverlay(title, copy, visible = true) {
        overlayTitle.textContent = title;
        overlayCopy.innerHTML = copy;
        overlay.classList.toggle('is-hidden', !visible);
    }

    function renderMessage(message) {
        const html = `
            <tr class="loading-row">
                <td colspan="15" class="text-center">
                    <div class="py-4">
                        <i class="fas fa-inbox fa-2x text-muted mb-3 d-block opacity-50"></i>
                        ${message}
                    </div>
                </td>
            </tr>`;
        $('#tbody-total').html(html.replace('15', '8'));
        $('#tbody-briguna-kpr').html(html);
        $('#tbody-mikro-smc').html(html);
    }

    function renderSingleTableBody(tbodyId, dataList, totalData, segmentKey = 'total', isDual = false, segmentKey2 = null) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        
        tbody.innerHTML = '';
        const fragment = document.createDocumentFragment();
        
        dataList.forEach(function(row) {
            const tr = document.createElement('tr');
            const branchCell = `<td class="sticky-col text-left font-weight-bold">${row.branch || '-'}</td>`;
            
            if (isDual && segmentKey2) {
                tr.innerHTML = branchCell + createDataCells(row[segmentKey]) + createDataCells(row[segmentKey2], true);
            } else {
                tr.innerHTML = branchCell + createDataCells(row[segmentKey]);
            }
            fragment.appendChild(tr);
        });
        
        tbody.appendChild(fragment);
        
        // Append Total Row
        const trTotal = document.createElement('tr');
        trTotal.className = 'row-total';
        const totalBranchCell = `<td class="sticky-col text-left">${totalData.branch || 'TOTAL'}</td>`;
        
        if (isDual && segmentKey2) {
            trTotal.innerHTML = totalBranchCell + createDataCells(totalData[segmentKey]) + createDataCells(totalData[segmentKey2], true);
        } else {
            trTotal.innerHTML = totalBranchCell + createDataCells(totalData[segmentKey]);
        }
        tbody.appendChild(trTotal);
    }

    function renderRows(dataList, totalData, ritelData = [], ritelTotal = null, microData = [], microTotal = null, isBranchFiltered = false) {
        // Render Main Tables
        renderSingleTableBody('tbody-total', dataList, totalData, 'total');
        renderSingleTableBody('tbody-briguna-kpr', dataList, totalData, 'briguna', true, 'kpr');
        renderSingleTableBody('tbody-mikro-smc', dataList, totalData, 'mikro', true, 'smc');
        
        // Handle Ritel & Mikro Tables
        const sectionRitel = document.getElementById('section-ritel');
        const sectionMicro = document.getElementById('section-micro');
        const sectionKonsolidasiTitle = document.querySelector('#section-konsolidasi .section-title-extra');
        
        if (isBranchFiltered && ritelTotal && microTotal) {
            sectionRitel.classList.remove('d-none');
            sectionMicro.classList.remove('d-none');
            if (sectionKonsolidasiTitle) sectionKonsolidasiTitle.classList.remove('d-none');
            
            renderSingleTableBody('tbody-ritel', ritelData, ritelTotal, 'total');
            renderSingleTableBody('tbody-micro', microData, microTotal, 'total');
        } else {
            sectionRitel.classList.add('d-none');
            sectionMicro.classList.add('d-none');
            if (sectionKonsolidasiTitle) sectionKonsolidasiTitle.classList.add('d-none');
        }
    }

    function resetTableState() {
        updateTableLabels('-', '-');
        updateGroupLabel('BRANCH OFFICE');
        updateBranchLabel();
        updateUkerLabel();
        summaryBadge.innerHTML = '<i class="fas fa-info-circle text-muted mr-1"></i> Belum ada data';
        filterMetaPeriod.innerHTML = '<i class="fas fa-clock text-primary mr-1"></i> <strong>Periode aktif:</strong> belum dijalankan';
        renderMessage('Belum ada data. Klik <strong>Tampilkan Data</strong>.');
        setOverlay('Siap Memuat Data', 'Pilih filter lalu klik Tampilkan Data.', true);
    }

    async function loadData() {
        if (activeRequest && typeof activeRequest.abort === 'function') {
            activeRequest.abort();
        }

        loadingChip.classList.remove('d-none');
        submitButton.disabled = true;
        renderMessage('Memproses rasio CASA debitur...');
        setOverlay('Sedang Mengolah', 'Memproses data rasio CASA debitur.', true);

        activeRequest = $.ajax({
            url: "{{ route('report.data.rasiocasa') }}",
            type: 'POST',
            data: {
                posisi: filterPosisi.value,
                branch_office: getSelectedBranches(),
                nama_uker: getSelectedUkers(),
                _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
        });

        try {
            const res = await activeRequest;

            if (res.status !== 'success') {
                renderMessage(res.message || 'Data tidak berhasil dimuat dari server.');
                summaryBadge.innerHTML = '<i class="fas fa-exclamation-triangle text-danger mr-1"></i> Gagal memuat data';
                setOverlay('Gagal Memuat Data', res.message || 'Terjadi kendala saat memproses query. Silakan coba lagi.', true);
                return;
            }

            const labels = res.labels || {};
            const effectiveDates = res.effective_dates || {};
            const dataList = res.data || [];
            const totalData = res.total || {};
            const meta = res.meta || {};
            const currentDate = effectiveDates.curr || filterPosisi.value;
            const hasAnyData = meta.has_rows === true || dataList.length > 0;
            const rowLabel = (res.group_label || 'BRANCH OFFICE').toUpperCase() === 'UKER' ? 'uker' : 'branch';
            const summaryLabel = rowLabel === 'uker' ? 'uker' : 'branch';

            updateTableLabels(labels.prev || '-', labels.curr || '-');
            updateGroupLabel(res.group_label);

            if (currentDate) {
                filterPosisi.value = currentDate;
            }

            filterMetaPeriod.innerHTML = `<i class="fas fa-clock text-primary mr-1"></i> <strong>Periode aktif:</strong> ${labels.curr || '-'} | <strong>Perbandingan:</strong> ${labels.prev || '-'}`;

            if (!hasAnyData) {
                renderMessage(`Tidak ada data untuk tanggal ${currentDate}. Coba pilih tanggal lain.`);
                summaryBadge.innerHTML = '<i class="fas fa-info-circle text-warning mr-1"></i> Data kosong';
                setOverlay('Tidak Ada Data', `Tidak ada data untuk periode <strong>${currentDate}</strong>.`, true);
                return;
            }

            renderRows(dataList, totalData, res.ritel_data, res.ritel_total, res.micro_data, res.micro_total, res.is_branch_filtered);
            summaryBadge.innerHTML = `<i class="fas fa-check-circle text-success mr-1"></i> ${dataList.length} ${summaryLabel} | ${labels.curr || currentDate}`;
            setOverlay('Data Siap Ditampilkan', 'Data siap ditampilkan.', false);
        } catch (xhr) {
            if (xhr && xhr.statusText === 'abort') {
                return;
            }

            let errorMsg = 'Gagal memuat data. ';
            if (xhr && xhr.status === 500) errorMsg += 'Server error. Periksa log system.';
            else errorMsg += 'Silakan coba lagi.';

            renderMessage(errorMsg);
            summaryBadge.innerHTML = '<i class="fas fa-exclamation-triangle text-danger mr-1"></i> Gagal memuat data';
            setOverlay('Gagal Memuat Data', errorMsg, true);
        } finally {
            loadingChip.classList.add('d-none');
            submitButton.disabled = false;
            activeRequest = null;
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadData();
    });

    $('#filterBranchDropdown').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeUkerDropdown();
        $('#filterBranchMenu').toggleClass('show');
        $(this).attr('aria-expanded', $('#filterBranchMenu').hasClass('show') ? 'true' : 'false');
    });

    $('#filterUkerDropdown').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled')) return;
        closeBranchDropdown();
        $('#filterUkerMenu').toggleClass('show');
        $(this).attr('aria-expanded', $('#filterUkerMenu').hasClass('show') ? 'true' : 'false');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.branch-filter-dropdown').length) {
            closeBranchDropdown();
        }
        if (!$(e.target).closest('.uker-filter-dropdown').length) {
            closeUkerDropdown();
        }
    });

    $(document).on('change', '.filter-branch-checkbox', function () {
        syncNamaUkerOptions();
        updateBranchLabel();
    });

    $(document).on('change', '.filter-uker-checkbox', function () {
        updateUkerLabel();
    });

    // ============================================================
    // Handler untuk Rasio CASA Per RM
    // ============================================================
    const cabang1Select = document.getElementById('filter_cabang1_rm');
    const unit1Select = document.getElementById('filter_unit1_rm');
    const submitButtonPerRm = document.getElementById('submitButtonPerRm');
    const loadingChipPerRm = document.getElementById('loadingChipPerRm');
    const branchOptionsPerRm = @json($branchOptions ?? []);
    let activeRequestPerRm = null;

    function populatePerRmBranches() {
        cabang1Select.innerHTML = '<option value="">Pilih Cabang</option>';

        branchOptionsPerRm.forEach(function(branch) {
            const option = document.createElement('option');
            option.value = branch;
            option.textContent = branch;
            cabang1Select.appendChild(option);
        });

        cabang1Select.disabled = branchOptionsPerRm.length === 0;
    }

    function populatePerRmUnits(selectedCabang) {
        unit1Select.innerHTML = '<option value="">Pilih Unit Kerja</option>';

        if (!selectedCabang) {
            unit1Select.disabled = true;
            return;
        }

        const units = Array.isArray(branchUkerMap[selectedCabang]) ? branchUkerMap[selectedCabang] : [];
        units.forEach(function(unit) {
            const option = document.createElement('option');
            option.value = unit;
            option.textContent = unit;
            unit1Select.appendChild(option);
        });

        unit1Select.disabled = units.length === 0;
    }

    cabang1Select.addEventListener('change', function() {
        populatePerRmUnits(this.value);
    });

    // Load data rasio CASA per RM
    async function loadDataPerRm() {
        const selectedCabang = cabang1Select.value;
        const selectedUnit = unit1Select.value;

        if (!selectedCabang || !selectedUnit) {
            alert('Pilih cabang dan unit kerja terlebih dahulu.');
            return;
        }

        if (activeRequestPerRm) {
            activeRequestPerRm.abort();
        }

        loadingChipPerRm.classList.remove('d-none');
        submitButtonPerRm.disabled = true;

        activeRequestPerRm = $.ajax({
            url: "{{ route('report.data.rasiocasa-per-rm') }}",
            type: 'POST',
            data: {
                posisi: filterPosisi.value,
                cabang1: selectedCabang,
                unit1: selectedUnit,
                _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
        });

        try {
            const res = await activeRequestPerRm;

            if (res.status !== 'success') {
                alert(res.message || 'Data tidak berhasil dimuat dari server.');
                return;
            }

            const dataList = res.data || [];
            const totalData = res.total || {};
            const labels = res.labels || {};

            // Update table labels
            updateTableLabels(labels.prev || '-', labels.curr || '-');

            // Render rows untuk tab per-rm
            const tbodyPerRm = document.getElementById('tbody-per-rm');
            tbodyPerRm.innerHTML = '';

            if (dataList.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="8" class="text-center text-muted">Tidak ada data untuk filter yang dipilih.</td>';
                tbodyPerRm.appendChild(tr);
            } else {
                const fragment = document.createDocumentFragment();
                
                dataList.forEach(function(row) {
                    const tr = document.createElement('tr');
                    const branchCell = `<td class="sticky-col text-left font-weight-bold">${row.branch || '-'}</td>`;
                    tr.innerHTML = branchCell + createDataCells(row.total);
                    fragment.appendChild(tr);
                });

                tbodyPerRm.appendChild(fragment);
            }

            // Append total row
            const trTotal = document.createElement('tr');
            trTotal.className = 'row-total';
            const totalBranchCell = `<td class="sticky-col text-left">${totalData.branch || 'TOTAL'}</td>`;
            trTotal.innerHTML = totalBranchCell + createDataCells(totalData.total);
            tbodyPerRm.appendChild(trTotal);

        } catch (xhr) {
            if (xhr && xhr.statusText === 'abort') {
                return;
            }
            alert('Gagal memuat data per RM. Silakan coba lagi.');
        } finally {
            loadingChipPerRm.classList.add('d-none');
            submitButtonPerRm.disabled = false;
            activeRequestPerRm = null;
        }
    }

    submitButtonPerRm.addEventListener('click', function() {
        loadDataPerRm();
    });

    // Populate initial Per RM filters from data already rendered on the page
    populatePerRmBranches();
    populatePerRmUnits('');

    resetTableState();
    syncNamaUkerOptions();
});
</script>
@endsection
