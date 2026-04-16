@extends('layouts.admin')

@section('title', 'Dashboard Harian')

@section('styles')
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
        
        --daily-no-width: 60px;
        --daily-label-width: 280px;
        --daily-position-width: 110px;
        --daily-delta-width: 100px;
        --daily-rka-width: 110px;
    }

    .daily-dashboard {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    /* Surface & Cards */
    .daily-surface {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        overflow: hidden;
        transition: box-shadow 0.3s ease;
    }

    .daily-panel-head {
        background: linear-gradient(to right, #f8fafc, #ffffff);
        border-bottom: 1px solid var(--border-color);
    }

    .daily-panel-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
        letter-spacing: -0.01em;
    }

    .daily-panel-desc {
        margin: 0.5rem 0 0;
        color: var(--text-muted);
        font-size: 0.9rem;
        line-height: 1.6;
    }

    /* Chips & Badges */
    .daily-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 9999px;
        padding: 0.35rem 0.85rem;
        background: #eff6ff; /* blue-50 */
        border: 1px solid #bfdbfe; /* blue-200 */
        color: var(--primary-blue);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .daily-scope-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 8px;
        padding: 0.4rem 0.75rem;
        background: #f1f5f9; /* slate-100 */
        border: 1px solid var(--border-color);
        color: var(--text-main);
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    .daily-scope-chip:hover {
        background: #e2e8f0;
    }

    /* Typography */
    .daily-filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    /* KPIs */
    .daily-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1rem;
    }

    .daily-kpi {
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-left: 4px solid var(--primary-blue-light);
    }

    .daily-kpi:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    }

    .daily-kpi .label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .daily-kpi .value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.2;
    }

    /* Form Controls */
    .select2-container--default .select2-selection--single {
        border: 1px solid var(--border-color) !important;
        border-radius: 8px !important;
        height: 42px !important;
        display: flex;
        align-items: center;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--primary-blue-light) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--text-main) !important;
        font-weight: 500;
        line-height: normal !important;
        padding-left: 12px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    
    .btn-primary {
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
        border-radius: 8px;
        font-weight: 600;
        padding: 0.6rem 1.25rem;
        transition: all 0.2s ease;
    }
    .btn-primary:hover {
        background-color: var(--primary-blue-dark);
        border-color: var(--primary-blue-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
    }
    .btn-primary:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.4) !important;
    }

    /* Table Wrapper */
    .daily-table-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .daily-table-wrap::-webkit-scrollbar {
        height: 8px;
    }
    .daily-table-wrap::-webkit-scrollbar-track {
        background: transparent;
    }
    .daily-table-wrap::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }

    .daily-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        background: #ffffff;
    }

    .daily-table th, .daily-table td {
        padding: 0.75rem 1rem;
        vertical-align: middle;
        white-space: nowrap;
        border-bottom: 1px solid var(--border-color);
        border-right: 1px solid var(--border-color);
    }
    .daily-table th:last-child, .daily-table td:last-child {
        border-right: none;
    }
    .daily-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Table Headers */
    .daily-table thead th {
        background: var(--table-header-bg);
        color: var(--table-header-text);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        text-align: center;
        border-bottom: 2px solid rgba(0,0,0,0.1);
        border-right: 1px solid rgba(255,255,255,0.1);
    }
    .daily-table thead tr.column-row th {
        background: #274bba; /* Slightly lighter for sub-headers */
        font-size: 0.65rem;
    }

    /* Table Cells */
    .daily-table tbody td {
        font-size: 0.8rem;
        color: var(--text-main);
        text-align: right; /* Numeric columns usually right aligned */
        font-variant-numeric: tabular-nums;
    }

    /* Specific Column Alignments */
    .daily-table .sticky-no, .daily-table .group-no {
        text-align: center !important;
        width: var(--daily-no-width);
        min-width: var(--daily-no-width);
    }
    .daily-table .sticky-label, .daily-table .group-label {
        text-align: left !important;
        width: var(--daily-label-width);
        min-width: var(--daily-label-width);
        font-weight: 500;
    }

    /* Column Widths */
    .daily-table .position-col {
        width: var(--daily-position-width);
        min-width: var(--daily-position-width);
    }
    .daily-table .delta-col {
        width: var(--daily-delta-width);
        min-width: var(--daily-delta-width);
    }
    .daily-table .rka-col {
        width: var(--daily-rka-width);
        min-width: var(--daily-rka-width);
    }

    /* Sticky Columns */
    .daily-table .sticky-no {
        position: sticky;
        left: 0;
        z-index: 10;
        background: #ffffff;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    }
    .daily-table .sticky-label {
        position: sticky;
        left: var(--daily-no-width);
        z-index: 10;
        background: #ffffff;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    }
    
    .daily-table thead .sticky-no, 
    .daily-table thead .sticky-label {
        z-index: 15;
        background: var(--table-header-bg);
        box-shadow: none;
    }

    /* Row Hover and Striping */
    .daily-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .daily-table tbody tr:hover {
        background-color: #f1f5f9;
    }
    .daily-table tbody tr:hover .sticky-no,
    .daily-table tbody tr:hover .sticky-label {
        background-color: #f1f5f9;
    }

    /* Hierarchical Rows Styling */
    .daily-table .metric-block-simpanan td,
    .daily-table .metric-block-os td,
    .daily-table .metric-block-sml td,
    .daily-table .metric-block-npl td,
    .daily-table .metric-block-casa td,
    .daily-table .metric-block-ldr td {
        background-color: #e0e7ff; /* blue-100 for parent rows */
        font-weight: 700;
        color: var(--primary-blue-dark);
    }
    .daily-table .metric-block-simpanan .sticky-no,
    .daily-table .metric-block-simpanan .sticky-label,
    .daily-table .metric-block-os .sticky-no,
    .daily-table .metric-block-os .sticky-label,
    .daily-table .metric-block-sml .sticky-no,
    .daily-table .metric-block-sml .sticky-label,
    .daily-table .metric-block-npl .sticky-no,
    .daily-table .metric-block-npl .sticky-label,
    .daily-table .metric-block-casa .sticky-no,
    .daily-table .metric-block-casa .sticky-label,
    .daily-table .metric-block-ldr .sticky-no,
    .daily-table .metric-block-ldr .sticky-label {
        background-color: #e0e7ff;
    }

    .daily-table tbody tr.metric-block-simpanan:hover td,
    .daily-table tbody tr.metric-block-os:hover td,
    .daily-table tbody tr.metric-block-sml:hover td,
    .daily-table tbody tr.metric-block-npl:hover td,
    .daily-table tbody tr.metric-block-casa:hover td,
    .daily-table tbody tr.metric-block-ldr:hover td {
        background-color: #dbeafe; /* blue-200 */
    }
    .daily-table tbody tr.metric-block-simpanan:hover .sticky-no,
    .daily-table tbody tr.metric-block-simpanan:hover .sticky-label,
    .daily-table tbody tr.metric-block-os:hover .sticky-no,
    .daily-table tbody tr.metric-block-os:hover .sticky-label,
    .daily-table tbody tr.metric-block-sml:hover .sticky-no,
    .daily-table tbody tr.metric-block-sml:hover .sticky-label,
    .daily-table tbody tr.metric-block-npl:hover .sticky-no,
    .daily-table tbody tr.metric-block-npl:hover .sticky-label,
    .daily-table tbody tr.metric-block-casa:hover .sticky-no,
    .daily-table tbody tr.metric-block-casa:hover .sticky-label,
    .daily-table tbody tr.metric-block-ldr:hover .sticky-no,
    .daily-table tbody tr.metric-block-ldr:hover .sticky-label {
        background-color: #dbeafe;
    }

    .daily-table .row-depth-1 .metric-label { padding-left: 1rem; font-weight: 600; color: var(--text-main); }
    .daily-table .row-depth-2 .metric-label { padding-left: 2rem; color: var(--text-muted); font-weight: 500;}
    .daily-table .row-depth-3 .metric-label { padding-left: 3rem; color: #94a3b8; font-size: 0.75rem; }

    /* Utilities */
    .delta-positive { color: #16a34a !important; font-weight: 700; } /* green-600 */
    .delta-negative { color: #dc2626 !important; font-weight: 700; } /* red-600 */
    
    .daily-empty {
        padding: 3rem;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.95rem;
    }

    .daily-loading {
        position: relative;
        pointer-events: none;
    }
    .daily-loading::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.6);
        backdrop-filter: blur(2px);
        z-index: 20;
    }

    .position-col-hidden {
        display: none !important;
    }

    .header-subnote {
        display: block;
        font-size: 0.6rem;
        opacity: 0.8;
        margin-top: 2px;
        font-weight: normal;
    }

    .daily-table tbody tr.row-hidden-by-scope {
        display: none;
    }

    /* Scrollbar track below table */
    .daily-table-sticky-footer {
        position: sticky;
        bottom: 0;
        z-index: 16;
        margin-top: -1px;
        padding: 0.55rem 0.75rem 0.8rem;
        background: linear-gradient(180deg, rgba(247, 251, 255, 0.12), rgba(247, 251, 255, 0.95));
        backdrop-filter: blur(10px);
        border-top: 1px solid var(--border-color);
    }
    .daily-table-sticky-track {
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid var(--border-color);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 transparent;
    }
    .daily-table-sticky-spacer {
        min-width: 1846px;
        height: 1px;
    }

    @media (max-width: 991.98px) {
        .daily-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .daily-dashboard {
            --daily-label-width: 220px;
            --daily-position-width: 94px;
            --daily-delta-width: 94px;
            --daily-rka-width: 94px;
        }

        .daily-table col.numeric-col {
            width: 94px !important;
        }

        .daily-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="daily-dashboard" id="daily-dashboard-root">
    <div class="daily-surface mb-4" id="daily-surface">
        <div class="daily-panel-head p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center">
                <div class="mb-3 mb-lg-0 pr-lg-4">
                    <span class="daily-meta-chip mb-2">
                        <i class="fas fa-chart-line mr-1"></i>
                        Dashboard Harian Snapshot
                    </span>
                    <h1 class="daily-panel-title">Perbandingan Posisi, Delta, dan RKA Harian</h1>
                    <p class="daily-panel-desc">
                        Data dibangun dari snapshot agregat <code>ssa_simpanan</code> dan <code>ssa_pinjaman</code>.
                    </p>
                </div>

                <div class="daily-meta-chip bg-white border-0 shadow-sm">
                    <i class="fas fa-database text-primary"></i>
                    <span data-source-label class="text-dark">{{ data_get($dashboardPage, 'initialData.summary.source', 'dashboard_harian_snapshots') }}</span>
                </div>
            </div>
        </div>

        <div class="p-4 border-bottom bg-white">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-4">
                <div class="mb-3 mb-lg-0">
                    <div class="daily-filter-label">Scope Aktif</div>
                    <div class="daily-scope">
                        <span class="daily-scope-chip"><i class="fas fa-map-marker-alt text-primary"></i> <span data-scope-kanca>{{ data_get($dashboardPage, 'initialData.summary.kanca_label', 'Semua Kanca') }}</span></span>
                        <span class="daily-scope-chip"><i class="fas fa-sitemap text-primary"></i> <span data-scope-unit>{{ data_get($dashboardPage, 'initialData.summary.unit_label', 'Semua Unit Kerja') }}</span></span>
                        <span class="daily-scope-chip"><i class="fas fa-clock text-primary"></i> <span data-scope-posisi>{{ data_get($dashboardPage, 'initialData.selected_period_label', 'Belum ada data') }}</span></span>
                        <span class="daily-scope-chip"><i class="fas fa-bullseye text-primary"></i> <span data-scope-rka>{{ data_get($dashboardPage, 'initialData.selected_rka_label', 'Belum ada data') }}</span></span>
                    </div>
                </div>

                <div class="text-lg-right bg-light p-2 px-3 rounded shadow-sm">
                    <div class="daily-filter-label mb-1">Status Data</div>
                    <div class="text-dark font-weight-bold" data-scope-summary>Filter belum dijalankan.</div>
                </div>
            </div>

            <div class="daily-kpi-grid mb-4">
                <div class="daily-kpi">
                    <div class="label">Total Simpanan</div>
                    <div class="value" data-kpi-simpanan>-</div>
                </div>
                <div class="daily-kpi">
                    <div class="label">Total Pinjaman Non Commercial</div>
                    <div class="value" data-kpi-os>-</div>
                </div>
                <div class="daily-kpi">
                    <div class="label">% CASA</div>
                    <div class="value" data-kpi-casa>-</div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <label class="daily-filter-label" for="filter-kanca">Kanca</label>
                    <select id="filter-kanca" class="form-control select2"></select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <label class="daily-filter-label" for="filter-unit">Unit Kerja</label>
                    <select id="filter-unit" class="form-control select2"></select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <label class="daily-filter-label" for="filter-posisi-terakhir">Posisi Terakhir</label>
                    <select id="filter-posisi-terakhir" class="form-control select2"></select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <label class="daily-filter-label" for="filter-posisi-rka">Posisi RKA</label>
                    <select id="filter-posisi-rka" class="form-control select2"></select>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top">
                <div class="text-muted small mb-2 mb-lg-0">
                    <i class="fas fa-info-circle mr-1"></i> Pilih filter lalu klik <strong>Terapkan Filter</strong> untuk menghitung snapshot terbaru.
                </div>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-primary shadow-sm" id="btn-apply-daily-filter">
                        <i class="fas fa-filter mr-2"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </div>

        <div class="p-4 bg-white">
            <div class="daily-table-wrap">
                <table class="table daily-table">
                    <colgroup>
                        <col style="width: 60px;">
                        <col style="width: 280px;">
                        <col style="width: 110px;" class="numeric-col">
                        <col style="width: 110px;" class="numeric-col">
                        <col style="width: 110px;" class="numeric-col">
                        <col style="width: 110px;" class="numeric-col">
                        <col style="width: 110px;" class="numeric-col position-col-h1">
                        <col style="width: 110px;" class="numeric-col">
                        <col style="width: 100px;" class="numeric-col">
                        <col style="width: 100px;" class="numeric-col">
                        <col style="width: 100px;" class="numeric-col">
                        <col style="width: 110px;" class="numeric-col">
                        <col style="width: 110px;" class="numeric-col">
                    </colgroup>
                    <thead>
                        <tr class="group-row text-center">
                            <th class="sticky-no group-no" rowspan="2">No</th>
                            <th class="sticky-label group-label text-left" rowspan="2">Keterangan</th>
                            <th class="group-position" colspan="6" data-position-group-colspan>Perbandingan Posisi</th>
                            <th class="group-delta" colspan="3">Delta Terhadap</th>
                            <th class="group-rka" colspan="2">Perbandingan RKA</th>
                        </tr>
                        <tr class="column-row text-center">
                            <th class="value-col position-col">
                                <span class="column-heading"><span class="main" data-label-yoy>-</span></span>
                            </th>
                            <th class="value-col position-col">
                                <span class="column-heading"><span class="main" data-label-ytd>-</span></span>
                            </th>
                            <th class="value-col position-col">
                                <span class="column-heading"><span class="main" data-label-mtm>-</span></span>
                            </th>
                            <th class="value-col position-col">
                                <span class="column-heading"><span class="main" data-label-mtd>-</span></span>
                            </th>
                            <th class="value-col position-col position-col-h1">
                                <span class="column-heading"><span class="main" data-label-h1>-</span></span>
                            </th>
                            <th class="value-col position-col bg-primary border-primary">
                                <span class="column-heading"><span class="main text-white" data-label-current>-</span></span>
                            </th>
                            <th class="value-col delta-col">
                                <span class="column-heading"><span class="main" data-label-delta-yoy>-</span><span class="header-subnote text-white-50">YoY</span></span>
                            </th>
                            <th class="value-col delta-col">
                                <span class="column-heading"><span class="main" data-label-delta-ytd>-</span><span class="header-subnote text-white-50">YtD</span></span>
                            </th>
                            <th class="value-col delta-col">
                                <span class="column-heading"><span class="main" data-label-delta-dtd>-</span><span class="header-subnote text-white-50">DtD</span></span>
                            </th>
                            <th class="value-col rka-col">
                                <span class="column-heading"><span class="main" data-label-rka>-</span></span>
                            </th>
                            <th class="value-col rka-col">
                                <span class="column-heading"><span class="main" data-label-rka-dec>-</span></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="daily-dashboard-body">
                        <tr><td colspan="13" class="daily-empty"><i class="fas fa-spinner fa-spin mr-2"></i> Memuat data dashboard harian...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="daily-table-sticky-footer">
                <div class="daily-table-sticky-track" data-sticky-scrollbar>
                    <div class="daily-table-sticky-spacer" aria-hidden="true"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    window.dailyDashboardPage = @json($dashboardPage ?? []);
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const page = window.dailyDashboardPage || {};
        const dataUrl = page.routes ? page.routes.data : '';
        const initialFilters = page.filters || {};
        const initialSelected = page.selected || {};
        const initialData = page.initialData || {};
        const surface = document.getElementById('daily-surface');
        const body = document.getElementById('daily-dashboard-body');
        const scopeKanca = document.querySelector('[data-scope-kanca]');
        const scopeUnit = document.querySelector('[data-scope-unit]');
        const scopePosisi = document.querySelector('[data-scope-posisi]');
        const scopeRka = document.querySelector('[data-scope-rka]');
        const scopeSummary = document.querySelector('[data-scope-summary]');
        const sourceLabel = document.querySelector('[data-source-label]');
        const kpiSimpanan = document.querySelector('[data-kpi-simpanan]');
        const kpiOs = document.querySelector('[data-kpi-os]');
        const kpiCasa = document.querySelector('[data-kpi-casa]');
        const headerLabels = {
            yoy: document.querySelector('[data-label-yoy]'),
            ytd: document.querySelector('[data-label-ytd]'),
            mtm: document.querySelector('[data-label-mtm]'),
            mtd: document.querySelector('[data-label-mtd]'),
            h1: document.querySelector('[data-label-h1]'),
            current: document.querySelector('[data-label-current]'),
            rka: document.querySelector('[data-label-rka]'),
            rkaDec: document.querySelector('[data-label-rka-dec]'),
            deltaYoy: document.querySelector('[data-label-delta-yoy]'),
            deltaYtd: document.querySelector('[data-label-delta-ytd]'),
            deltaDtd: document.querySelector('[data-label-delta-dtd]'),
        };
        const positionGroupColspan = document.querySelector('[data-position-group-colspan]');
        const positionH1Header = document.querySelector('[data-label-h1]').closest('th');
        const tableWrap = document.querySelector('.daily-table-wrap');
        const stickyScrollbar = document.querySelector('[data-sticky-scrollbar]');
        const stickySpacer = document.querySelector('.daily-table-sticky-spacer');
        const applyButton = document.getElementById('btn-apply-daily-filter');
        const selects = {
            kanca: document.getElementById('filter-kanca'),
            unit_kerja: document.getElementById('filter-unit'),
            posisi_terakhir: document.getElementById('filter-posisi-terakhir'),
            posisi_rka: document.getElementById('filter-posisi-rka'),
        };
        let latestFilters = initialFilters;
        const MILLION_UNIT = 1000000;
        const BILLION_UNIT = 1000000000;
        const TABLE_MONEY_UNIT = BILLION_UNIT;
        const TABLE_MONEY_LABEL = 'M';
        const currencyFormatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const formatCurrency = function (value) {
            return currencyFormatter.format(Number(value || 0) / TABLE_MONEY_UNIT) + ' ' + TABLE_MONEY_LABEL;
        };

        const formatMiliar = function (value) {
            return currencyFormatter.format(Number(value || 0) / BILLION_UNIT) + ' M';
        };

        const formatPercent = function (value) {
            return Number(value || 0).toFixed(2).replace('.', ',') + '%';
        };

        const formatDateSlash = function (value) {
            if (!value) {
                return '-';
            }

            const parts = String(value).slice(0, 10).split('-');
            if (parts.length !== 3) {
                return String(value);
            }

            return parts[2] + '/' + parts[1] + '/' + parts[0];
        };

        const formatMonthYear = function (value) {
            if (!value) {
                return '-';
            }

            const raw = String(value).slice(0, 7);
            if (!/^\d{4}-\d{2}$/.test(raw)) {
                return String(value);
            }

            const [year, month] = raw.split('-');
            const date = new Date(Number(year), Number(month) - 1, 1);

            return new Intl.DateTimeFormat('id-ID', { month: 'short', year: 'numeric' }).format(date);
        };

        const formatValue = function (value, type) {
            return type === 'percent' ? formatPercent(value) : formatCurrency(value);
        };

        const escapeHtml = function (value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        };

        const togglePositionColumns = function (visible) {
            const show = Boolean(visible);
            const hiddenClass = 'position-col-hidden';

            [positionH1Header].forEach(function (node) {
                if (!node) {
                    return;
                }

                node.classList.toggle(hiddenClass, !show);
            });

            document.querySelectorAll('[data-position-col="h1"]').forEach(function (cell) {
                cell.classList.toggle(hiddenClass, !show);
            });

            document.querySelectorAll('col.position-col-h1').forEach(function (cell) {
                cell.classList.toggle(hiddenClass, !show);
            });

            if (positionGroupColspan) {
                positionGroupColspan.setAttribute('colspan', show ? '6' : '5');
            }
        };

        const syncStickyScrollbarWidth = function () {
            if (!stickySpacer || !tableWrap) {
                return;
            }

            const table = tableWrap.querySelector('.daily-table');
            if (!table) {
                return;
            }

            stickySpacer.style.minWidth = table.scrollWidth + 'px';
        };

        const syncScrollLeft = function (source, target) {
            if (!source || !target) {
                return;
            }

            target.scrollLeft = source.scrollLeft;
        };

        const populateSelect = function (select, options, selectedValue) {
            if (!select) {
                return;
            }

            const normalizedSelected = selectedValue || 'all';
            const html = (options || []).map(function (option) {
                const value = option.value || 'all';
                const label = option.label || value;
                const selected = String(value) === String(normalizedSelected) ? 'selected' : '';

                return '<option value="' + value + '" ' + selected + '>' + label + '</option>';
            }).join('');

            select.innerHTML = html;
            $(select).trigger('change.select2');
        };

        const scopedUnitOptions = function (filters, kancaValue) {
            return (filters.unit_kerja || []).filter(function (option) {
                if ((option.value || 'all') === 'all') {
                    return true;
                }

                if (!kancaValue || kancaValue === 'all') {
                    return true;
                }

                return String(option.kanca_value || '') === String(kancaValue);
            });
        };

        const syncUnitSelect = function (filters, preferredUnit) {
            const unitOptions = scopedUnitOptions(filters, selects.kanca.value || 'all');
            const selectedUnit = unitOptions.some(function (option) {
                return String(option.value || '') === String(preferredUnit || 'all');
            }) ? (preferredUnit || 'all') : 'all';

            populateSelect(selects.unit_kerja, unitOptions, selectedUnit);
        };

        const currentState = function () {
            return {
                kanca: selects.kanca.value || 'all',
                unit_kerja: selects.unit_kerja.value || 'all',
                posisi_terakhir: selects.posisi_terakhir.value || '',
                posisi_rka: selects.posisi_rka.value || '',
            };
        };

        const isMicroUnitSelection = function () {
            if (!selects.unit_kerja) {
                return false;
            }

            const selectedOption = selects.unit_kerja.options[selects.unit_kerja.selectedIndex];
            const label = selectedOption ? String(selectedOption.text || '').trim().toUpperCase() : '';

            return /\bUNIT\b/.test(label);
        };

        const renderTable = function (payload) {
            const rows = payload.rows || [];
            const periods = payload.comparison_periods || {};
            const hasH1 = Boolean(periods.h1 && periods.h1.period);
            const emptyColspan = hasH1 ? 13 : 12;
            const blockClassMap = {
                total_simpanan: 'metric-block-simpanan',
                total_os: 'metric-block-os',
                total_sml_pct_non_commercial: 'metric-block-sml',
                total_npl_pct_non_commercial: 'metric-block-npl',
                casa_pct: 'metric-block-casa',
                ldr_non_commercial: 'metric-block-ldr',
            };
            const sectionClassMap = {
                simpanan_ritel: 'section-ritel',
                simpanan_mikro: 'section-mikro',
                simpanan_wholesale: 'section-wholesale',
                commercial_os: 'section-commercial',
                sme_os: 'section-ritel',
                consumer_os: 'section-consumer',
                micro_os: 'section-mikro',
                commercial_sml: 'section-commercial',
                sme_sml: 'section-ritel',
                consumer_sml: 'section-consumer',
                micro_sml: 'section-mikro',
                commercial_npl: 'section-commercial',
                sme_npl: 'section-ritel',
                consumer_npl: 'section-consumer',
                micro_npl: 'section-mikro',
            };
            const hiddenWhenUnitSelected = new Set([
                'simpanan_ritel',
                'giro_ritel',
                'deposito_ritel',
                'tabungan_ritel',
                'casa_ritel',
                'ldr_ritel_non_commercial',
            ]);
            const hideRitelRows = isMicroUnitSelection();

            togglePositionColumns(hasH1);
            syncStickyScrollbarWidth();

            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="' + emptyColspan + '" class="daily-empty"><i class="fas fa-box-open mr-2 text-muted"></i>Tidak ada data untuk filter terpilih.</td></tr>';
                return;
            }

            body.innerHTML = rows.map(function (row, index) {
                const value = row.values || {};
                const delta = row.deltas || {};
                const rowCells = [];
                const deltaClass = function (amount) {
                    if (Number(amount) > 0) {
                        return 'delta-positive';
                    }

                    if (Number(amount) < 0) {
                        return 'delta-negative';
                    }

                    return 'text-muted';
                };

                rowCells.push('<td class="sticky-no font-weight-bold text-center">' + (index + 1) + '</td>');
                rowCells.push('<td class="sticky-label"><span class="metric-label" title="' + escapeHtml(row.label) + '">' + escapeHtml(row.label) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.yoy, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.ytd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtm, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtd, row.type) + '</span></td>');

                if (hasH1) {
                    rowCells.push('<td class="value-col position-col position-col-h1" data-position-col="h1"><span class="cell-text">' + formatValue(value.h1, row.type) + '</span></td>');
                }

                rowCells.push('<td class="value-col position-col metric-value font-weight-bold bg-light"><span class="cell-text text-primary">' + formatValue(value.current, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.yoy) + '"><span class="cell-text">' + formatValue(delta.yoy, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.ytd) + '"><span class="cell-text">' + formatValue(delta.ytd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.dtd) + '"><span class="cell-text">' + formatValue(delta.dtd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka, row.type) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka_dec, row.type) + '</span></td>');

                const rowClasses = ['row-depth-' + row.depth];
                if (blockClassMap[row.key]) {
                    rowClasses.push(blockClassMap[row.key]);
                }
                if (sectionClassMap[row.key]) {
                    rowClasses.push(sectionClassMap[row.key]);
                }
                if (hideRitelRows && hiddenWhenUnitSelected.has(row.key)) {
                    rowClasses.push('row-hidden-by-scope');
                }

                return '<tr class="' + rowClasses.join(' ') + '">' + rowCells.join('') + '</tr>';
            }).join('');
        };

        const applyPayload = function (payload) {
            const summary = payload.summary || {};
            const periods = payload.comparison_periods || {};
            const filters = payload.available_filters || initialFilters;
            const hasH1 = Boolean(periods.h1 && periods.h1.period);
            latestFilters = filters;
            const current = currentState();

            populateSelect(selects.kanca, filters.kanca || [], current.kanca);
            syncUnitSelect(filters, current.unit_kerja);
            populateSelect(selects.posisi_terakhir, filters.posisi_terakhir || [], payload.selected_period || current.posisi_terakhir);
            populateSelect(selects.posisi_rka, filters.posisi_rka || [], payload.selected_rka_period ? payload.selected_rka_period.slice(0, 7) : current.posisi_rka);

            scopeKanca.textContent = summary.kanca_label || 'Semua Kanca';
            scopeUnit.textContent = summary.unit_label || 'Semua Unit Kerja';
            scopePosisi.textContent = payload.selected_period_label || 'Belum ada data';
            scopeRka.textContent = periods.rka ? formatMonthYear(periods.rka.period) : 'Belum ada data';
            scopeSummary.innerHTML = '<i class="fas fa-list mr-1"></i> Baris tampil: ' + (summary.row_count || 0).toLocaleString('id-ID') + '. <br><small class="text-muted font-weight-normal mt-1 d-block">Data dari: ' + (summary.source || 'source_fallback') + '</small>';
            sourceLabel.textContent = summary.source || 'source_fallback';
            kpiSimpanan.textContent = formatMiliar(summary.current_total_simpanan || 0);
            kpiOs.textContent = formatMiliar(summary.current_total_os_non_commercial || 0);
            kpiCasa.textContent = formatPercent(summary.current_casa_pct || 0);

            headerLabels.yoy.textContent = periods.yoy ? formatDateSlash(periods.yoy.period) : '-';
            headerLabels.ytd.textContent = periods.ytd ? formatDateSlash(periods.ytd.period) : '-';
            headerLabels.mtm.textContent = periods.mtm ? formatDateSlash(periods.mtm.period) : '-';
            headerLabels.mtd.textContent = periods.mtd ? formatDateSlash(periods.mtd.period) : '-';
            headerLabels.h1.textContent = hasH1 ? formatDateSlash(periods.h1.period) : '-';
            headerLabels.current.textContent = payload.selected_period ? formatDateSlash(payload.selected_period) : '-';
            headerLabels.rka.textContent = periods.rka ? formatMonthYear(periods.rka.period) : '-';
            headerLabels.rkaDec.textContent = periods.rka_dec ? formatDateSlash(periods.rka_dec.period) : '-';
            headerLabels.deltaYoy.textContent = periods.yoy ? formatDateSlash(periods.yoy.period) : '-';
            headerLabels.deltaYtd.textContent = periods.ytd ? formatDateSlash(periods.ytd.period) : '-';
            headerLabels.deltaDtd.textContent = hasH1 ? formatDateSlash(periods.h1.period) : '-';

            togglePositionColumns(hasH1);
            syncStickyScrollbarWidth();

            renderTable(payload);
        };

        const fetchData = function () {
            if (!dataUrl) {
                return;
            }

            const params = new URLSearchParams(currentState());
            surface.classList.add('daily-loading');
            if (applyButton) {
                applyButton.disabled = true;
                applyButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memuat Data...';
            }

            fetch(dataUrl + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    applyPayload(payload);
                })
                .catch(function () {
                    const hidden = positionH1Header && positionH1Header.classList.contains('position-col-hidden');
                    body.innerHTML = '<tr><td colspan="' + (hidden ? 12 : 13) + '" class="daily-empty text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Gagal memuat data dashboard harian.</td></tr>';
                })
                .finally(function () {
                    surface.classList.remove('daily-loading');
                    if (applyButton) {
                        applyButton.disabled = false;
                        applyButton.innerHTML = '<i class="fas fa-filter mr-2"></i> Terapkan Filter';
                    }
                });
        };

        const refreshUnitOptions = function () {
            if (!dataUrl) {
                syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
                return;
            }

            const params = new URLSearchParams(currentState());

            fetch(dataUrl + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    latestFilters = payload.available_filters || initialFilters;
                    syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
                })
                .catch(function () {
                    syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
                });
        };

        populateSelect(selects.kanca, initialFilters.kanca || [], initialSelected.kanca || 'all');
        syncUnitSelect(initialFilters, initialSelected.unit_kerja || 'all');
        populateSelect(selects.posisi_terakhir, initialFilters.posisi_terakhir || [], initialSelected.posisi_terakhir || '');
        populateSelect(selects.posisi_rka, initialFilters.posisi_rka || [], initialSelected.posisi_rka || '');
        body.innerHTML = '<tr><td colspan="13" class="daily-empty"><i class="fas fa-filter mr-2 text-muted"></i>Filter belum dijalankan. Pilih parameter lalu klik Terapkan Filter.</td></tr>';

        if (initialData && Object.keys(initialData).length) {
            applyPayload(initialData);
        } else {
            sourceLabel.textContent = '-';
        }

        if (applyButton) {
            applyButton.addEventListener('click', fetchData);
        }

        if (selects.kanca) {
            selects.kanca.addEventListener('change', function () {
                refreshUnitOptions();
            });
        }

        if (tableWrap && stickyScrollbar) {
            tableWrap.addEventListener('scroll', function () {
                syncScrollLeft(tableWrap, stickyScrollbar);
            });

            stickyScrollbar.addEventListener('scroll', function () {
                syncScrollLeft(stickyScrollbar, tableWrap);
            });
        }

        window.addEventListener('resize', syncStickyScrollbarWidth);
        syncStickyScrollbarWidth();
    });
</script>
@endsection