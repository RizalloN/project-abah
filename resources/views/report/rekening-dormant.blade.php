@extends('layouts.admin')

@section('title', 'Rekening Dormant')

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

    .dormant-dashboard {
        padding-bottom: 1.5rem;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    .dormant-shell,
    .dormant-table-shell {
        border: 1px solid var(--border-color);
        border-radius: 16px;
        background: var(--surface-color);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        transition: box-shadow 0.3s ease;
    }

    .dormant-shell { overflow: visible; }
    .dormant-shell .card-body, .dormant-table-shell .card-body {
        background: var(--surface-color);
        border-radius: 16px;
    }
    .dormant-shell .card-body { overflow: visible; }

    .dormant-page-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary-blue-dark);
        margin-bottom: 0.2rem;
    }

    .dormant-filter-grid .form-group { margin-bottom: 1rem; }

    .dormant-filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .dormant-filter-control,
    .dormant-filter-control.select2-selection {
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

    .dormant-filter-control:focus {
        border-color: var(--primary-blue-light) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
        outline: none;
    }

    .dormant-filter-control:disabled {
        background: #f1f5f9 !important; /* slate-100 */
        color: var(--text-muted) !important;
        cursor: not-allowed;
    }

    .dormant-filter-dropdown { position: relative; }
    .dormant-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        background: var(--surface-color);
        font-weight: 500;
    }

    .dormant-dropdown-toggle:disabled {
        background: #f1f5f9;
        cursor: not-allowed;
        opacity: 1;
    }

    .dormant-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dormant-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 1080;
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

    .dormant-dropdown-menu.show { display: block; }
    .dormant-dropdown-menu .dropdown-item {
        padding: 0.5rem 1rem;
        cursor: pointer;
        margin-bottom: 0;
        transition: background-color 0.2s ease;
    }

    .dormant-dropdown-menu .dropdown-item:hover { background-color: #f1f5f9; }
    .dormant-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dormant-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
        cursor: pointer;
    }
    .dormant-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 500;
        cursor: pointer;
    }

    .dormant-filter-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.8rem;
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-top: 0.15rem;
    }

    .dormant-loading-chip {
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

    .dormant-loading-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--primary-blue-light);
        animation: dormantPulse 1.6s infinite;
    }

    @keyframes dormantPulse {
        0% { transform: scale(0.95); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(0.95); opacity: 0.5; }
    }

    .dormant-table-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .dormant-table-heading h5 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
    }

    .dormant-table-unit {
        margin-top: 0.35rem;
        color: var(--text-muted);
        font-size: 0.82rem;
        font-weight: 600;
    }

    .dormant-table-badge {
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

    .dormant-table-stage {
        position: relative;
        min-height: 420px;
    }

    .dormant-loading-overlay {
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

    .dormant-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .dormant-loading-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
    }

    .dormant-loading-copy {
        max-width: 480px;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin: 0;
    }

    .dormant-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .dormant-table-wrap::-webkit-scrollbar { height: 8px; }
    .dormant-table-wrap::-webkit-scrollbar-track { background: transparent; }
    .dormant-table-wrap::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }

    .dormant-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        background: #ffffff;
    }

    .dormant-table th, .dormant-table td {
        padding: 0.85rem 1rem;
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
        text-align: right;
        vertical-align: middle;
    }

    .dormant-table th:last-child, .dormant-table td:last-child {
        border-right: none;
    }

    .dormant-table thead th {
        color: var(--table-header-text);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-align: center;
        border-bottom: 2px solid rgba(0,0,0,0.1) !important;
    }

    .dormant-table .head-branch {
        background: var(--table-header-bg);
        text-align: left;
        min-width: 210px;
        position: sticky;
        left: 0;
        z-index: 11;
        box-shadow: none;
    }

    .dormant-table .head-group { background: var(--table-header-bg); }
    .dormant-table .head-sub { background: #274bba; } /* Lighter blue */

    .dormant-table tbody th {
        background: #ffffff;
        color: var(--text-main);
        text-align: left;
        font-size: 0.85rem;
        font-weight: 600;
        position: sticky;
        left: 0;
        z-index: 10;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        transition: background-color 0.15s ease;
    }

    .dormant-table tbody td {
        background: var(--surface-color);
        color: var(--text-main);
        font-weight: 500;
        white-space: nowrap;
        transition: background-color 0.15s ease;
    }

    .dormant-table tbody tr:hover td { background-color: #f1f5f9; }
    .dormant-table tbody tr:hover th { background-color: #f1f5f9; }

    .dormant-table tbody td.metric-current { background: #ffffff; color: var(--text-main); font-weight: 700; }
    .dormant-table tbody td.metric-positive { color: #16a34a; font-weight: 700; } /* green-600 */
    .dormant-table tbody td.metric-negative { color: #dc2626; font-weight: 700; } /* red-600 */
    .dormant-table tbody td.metric-neutral { color: var(--text-muted); font-weight: 500; }

    .dormant-table tfoot th, .dormant-table tfoot td {
        background: #e0e7ff !important; /* blue-100 */
        color: var(--primary-blue-dark) !important;
        font-weight: 700;
        border-top: 2px solid var(--primary-blue-light) !important;
        position: sticky;
        bottom: 0;
        z-index: 9;
    }
    
    .dormant-table tfoot th {
        left: 0;
        z-index: 12;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    }

    .dormant-empty-state {
        padding: 3.5rem 1rem;
        text-align: center;
        color: var(--text-muted);
    }
    .dormant-empty-state strong {
        display: block;
        margin-bottom: 0.4rem;
        color: var(--primary-blue-dark);
        font-size: 1.1rem;
    }
</style>
@include('report._bri-report-ui')

<div class="dormant-dashboard pt-4">

    <div class="card dormant-shell mb-4">
        <div class="card-body p-4">
            <form id="dormantFilterForm" method="GET" action="{{ route('report.rekening-dormant') }}">
                <div class="d-flex flex-wrap align-items-center justify-content-end mb-4 pb-3 border-bottom">
                    <div class="dormant-filter-meta">
                        <span><i class="fas fa-clock text-primary mr-1"></i> Periode aktif: <strong id="dormantActivePeriodMeta">-</strong></span>
                        <span><i class="fas fa-history text-primary mr-1"></i> M-1: <strong id="dormantComparisonPeriodMeta">-</strong></span>
                    </div>
                </div>

                <div class="row dormant-filter-grid">
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="dormant-filter-label">Branch Office (Kanca)</label>
                            <div class="dormant-filter-dropdown">
                                <button type="button" id="dormantBranchDropdown" class="form-control dormant-filter-control dormant-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                                    <span id="dormantBranchLabel" class="dormant-dropdown-label">Area 6 - All</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div id="dormantBranchMenu" class="dormant-dropdown-menu" aria-labelledby="dormantBranchDropdown">
                                    <div class="dropdown-item text-muted small">Memuat opsi...</div>
                                </div>
                            </div>
                            <div id="dormantBranchInputs"></div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="dormant-filter-label">Nama Uker</label>
                            <div class="dormant-filter-dropdown">
                                <button type="button" id="dormantUnitDropdown" class="form-control dormant-filter-control dormant-dropdown-toggle" aria-haspopup="true" aria-expanded="false" disabled>
                                    <span id="dormantUnitLabel" class="dormant-dropdown-label">ALL UKER</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div id="dormantUnitMenu" class="dormant-dropdown-menu" aria-labelledby="dormantUnitDropdown">
                                    <div class="dropdown-item text-muted small">Pilih branch office</div>
                                </div>
                            </div>
                            <div id="dormantUnitInputs"></div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="dormant-filter-label">Periode Akhir</label>
                            <input id="dormantPeriodInput" type="date" name="posisi" class="form-control dormant-filter-control" value="{{ $defaultPeriod }}" max="{{ $defaultPeriod }}">
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center mt-3 pt-3 border-top" style="gap:.75rem;">
                    <button id="dormantSubmitButton" type="submit" class="btn btn-primary font-weight-bold" style="border-radius: 8px; min-height: 42px; padding: 0 1.5rem;">
                        <i class="fas fa-filter mr-2"></i>Tampilkan Data
                    </button>
                    <button id="dormantResetButton" type="button" class="btn btn-light font-weight-bold text-muted" style="border-radius: 8px; min-height: 42px; padding: 0 1.5rem;">Reset Filter</button>
                    <div id="dormantLoadingChip" class="dormant-loading-chip d-none ml-auto"><span class="dormant-loading-dot"></span>Sedang Mengolah...</div>
                </div>
            </form>
        </div>
    </div>

    <div class="card dormant-table-shell">
        <div class="card-body p-4">
            <div class="dormant-table-heading">
                <div>
                    <h5><i class="fas fa-table text-primary mr-2"></i>Rekening Dormant</h5>
                    <div class="dormant-table-unit">Satuan: Jumlah Rekening</div>
                </div>
                <div class="dormant-table-badge"><i class="fas fa-calendar-day text-primary"></i><span id="dormantPeriodBadge">-</span></div>
            </div>

            <div class="dormant-table-stage">
                <div id="dormantLoadingOverlay" class="dormant-loading-overlay">
                    <i class="fas fa-bed fa-3x text-primary opacity-50 mb-3"></i>
                    <div class="dormant-loading-title">Siap Memuat Data</div>
                    <p class="dormant-loading-copy">Pilih filter lalu klik <strong>Tampilkan Data</strong>.</p>
                </div>

                <div class="dormant-table-wrap">
                    <table class="dormant-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="head-branch dormant-group-label" data-default-label="Branch Office" data-filtered-label="UKER">Branch Office</th>
                                <th colspan="4" class="head-group">Rekening Dormant</th>
                            </tr>
                            <tr>
                                <th id="dormantHeaderCurrent" class="head-sub">Periode Terakhir</th>
                                <th id="dormantHeaderMtd" class="head-sub">MtD</th>
                                <th id="dormantHeaderYtd" class="head-sub">YtD</th>
                                <th id="dormantHeaderYoy" class="head-sub">YoY</th>
                            </tr>
                        </thead>
                        <tbody id="dormantTableBody">
                            <tr>
                                <td colspan="5" class="dormant-empty-state">
                                    <i class="fas fa-inbox fa-2x text-muted mb-3 opacity-50"></i>
                                    <strong>Belum ada data</strong> Klik <strong>Tampilkan Data</strong>.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr id="dormantTableFoot">
                                <th>Grand Total</th><td>-</td><td>-</td><td>-</td><td>-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="dormantPaginationContainer" style="margin-top: 1.5rem;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ────────────────────── Elements ──────────────────────
    const form = document.getElementById('dormantFilterForm');
    const periodInput = document.getElementById('dormantPeriodInput');
    const branchDropdown = document.getElementById('dormantBranchDropdown');
    const branchMenu = document.getElementById('dormantBranchMenu');
    const branchLabel = document.getElementById('dormantBranchLabel');
    const unitDropdown = document.getElementById('dormantUnitDropdown');
    const unitMenu = document.getElementById('dormantUnitMenu');
    const unitLabel = document.getElementById('dormantUnitLabel');
    const branchInputs = document.getElementById('dormantBranchInputs');
    const unitInputs = document.getElementById('dormantUnitInputs');
    const submitButton = document.getElementById('dormantSubmitButton');
    const resetButton = document.getElementById('dormantResetButton');
    const chip = document.getElementById('dormantLoadingChip');
    const overlay = document.getElementById('dormantLoadingOverlay');
    const tableBody = document.getElementById('dormantTableBody');
    const tableFoot = document.getElementById('dormantTableFoot');
    const activeMeta = document.getElementById('dormantActivePeriodMeta');
    const comparisonMeta = document.getElementById('dormantComparisonPeriodMeta');
    const badge = document.getElementById('dormantPeriodBadge');
    const currentHeader = document.getElementById('dormantHeaderCurrent');
    const mtdHeader = document.getElementById('dormantHeaderMtd');
    const ytdHeader = document.getElementById('dormantHeaderYtd');
    const yoyHeader = document.getElementById('dormantHeaderYoy');

    // ────────────────────── Config ──────────────────────
    const filtersUrl = @json(route('report.rekening-dormant.filters'));
    const dataUrl = @json(route('report.data.rekening-dormant'));
    const csrfToken = @json(csrf_token());
    const defaultPeriod = @json($defaultPeriod);
    const initialBranches = @json($selectedBranches ?? []);
    const initialUnits = @json($selectedUnits ?? []);

    // ────────────────────── State ──────────────────────
    let activeController = null;
    let activeFilterController = null;
    let branchOptions = [];
    let unitOptions = [];
    let selectedBranches = Array.isArray(initialBranches) ? initialBranches : [];
    let selectedUnits = Array.isArray(initialUnits) ? initialUnits : [];

    // Pagination state
    let allRows = [];
    const ROWS_PER_PAGE = 25;
    let currentPage = 1;
    let lastRequestParams = null;
    let cachedFilterResponse = {};

    // ────────────────────── Utilities ──────────────────────
    function appendArrayParams(params, key, values) {
        values.forEach(value => {
            if (value) params.append(`${key}[]`, value);
        });
    }

    function formatDate(value) {
        if (!value) return '-';
        return new Intl.DateTimeFormat('id-ID').format(new Date(value + 'T00:00:00'));
    }

    function formatNumber(value) {
        if (value === null || value === undefined || value === '') return '-';
        const number = Number(value);
        return Number.isNaN(number) ? '-' : new Intl.NumberFormat('id-ID').format(number);
    }

    function deltaText(value) {
        const n = Number(value || 0);
        return n > 0 ? `+${formatNumber(n)}` : formatNumber(n);
    }

    function cellClass(value, isCurrent = false) {
        if (isCurrent) return 'metric-current';
        const n = Number(value || 0);
        if (n > 0) return 'metric-positive';
        if (n < 0) return 'metric-negative';
        return 'metric-neutral';
    }

    // ────────────────────── Rendering ──────────────────────
    function renderRowsPage(rows, pageNum = 1) {
        allRows = rows;
        currentPage = Math.max(1, Math.min(pageNum, Math.ceil((rows.length / ROWS_PER_PAGE) || 1)));

        if (!rows || rows.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="5" class="dormant-empty-state"><i class="fas fa-inbox fa-2x text-muted mb-3 opacity-50"></i><strong>Data tidak ditemukan</strong>Coba ubah periode atau filter branch office agar hasil report tersedia.</td></tr>`;
            renderPagination(0);
            return;
        }

        const startIdx = (currentPage - 1) * ROWS_PER_PAGE;
        const endIdx = Math.min(startIdx + ROWS_PER_PAGE, rows.length);
        const pageRows = rows.slice(startIdx, endIdx);

        // Fast path for small datasets (< 50 rows total)
        const isSmallDataset = rows.length < 50;
        let html = '';
        pageRows.forEach(row => {
            html += `<tr>
                <th>${row.branch || '-'}</th>
                <td class="${cellClass(row.current, true)}">${formatNumber(row.current)}</td>
                <td class="${cellClass(row.mtd)}">${deltaText(row.mtd)}</td>
                <td class="${cellClass(row.ytd)}">${deltaText(row.ytd)}</td>
                <td class="${cellClass(row.yoy)}">${deltaText(row.yoy)}</td>
            </tr>`;
        });
        tableBody.innerHTML = html;

        // Lazy render pagination only if needed
        if (!isSmallDataset || Math.ceil(rows.length / ROWS_PER_PAGE) > 1) {
            setTimeout(() => renderPagination(rows.length), 0);
        }
    }

    function renderRows(rows) {
        renderRowsPage(rows, 1);
    }

    function renderPagination(totalRows) {
        if (totalRows === 0) {
            const paginationContainer = document.getElementById('dormantPaginationContainer');
            if (paginationContainer) {
                paginationContainer.innerHTML = '';
            }
            return;
        }

        const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE);
        if (totalPages <= 1) {
            const paginationContainer = document.getElementById('dormantPaginationContainer');
            if (paginationContainer) {
                paginationContainer.innerHTML = '';
            }
            return;
        }

        const paginationContainer = document.getElementById('dormantPaginationContainer');
        if (!paginationContainer) return;

        let paginationHtml = '<nav aria-label="Table Pagination" style="margin-top: 1rem;"><ul class="pagination mb-0" style="justify-content: center;">';
        
        // Previous button
        paginationHtml += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>← Sebelumnya</a>
        </li>`;

        // Page buttons
        const maxPagesToShow = 5;
        const startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
        const endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (startPage > 1) {
            paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if (startPage > 2) paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}" ${i === currentPage ? 'aria-current="page"' : ''}>${i}</a>
            </li>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        // Next button
        paginationHtml += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>Berikutnya →</a>
        </li>`;

        paginationHtml += '</ul></nav>';
        paginationContainer.innerHTML = paginationHtml;

        // Attach click handlers
        paginationContainer.querySelectorAll('[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                if (e.target.closest('.disabled')) return;
                const page = parseInt(e.target.dataset.page);
                renderRowsPage(allRows, page);
                renderPagination(allRows.length);
                // Update badge with current page
                const totalPages = Math.ceil(allRows.length / ROWS_PER_PAGE) || 1;
                badge.textContent = `${badge.textContent.split('|')[0].trim()} | ${allRows.length} row${allRows.length !== 1 ? 's' : ''} (hal. ${currentPage}/${totalPages})`;
                // Scroll to table
                document.getElementById('dormantTableBody')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }

    function renderFoot(total = {}) {
        tableFoot.innerHTML = `
            <th>Grand Total</th>
            <td>${formatNumber(total.current ?? null)}</td>
            <td>${deltaText(total.mtd ?? null)}</td>
            <td>${deltaText(total.ytd ?? null)}</td>
            <td>${deltaText(total.yoy ?? null)}</td>
        `;
    }

    function updateHeaders(labels = {}) {
        currentHeader.textContent = labels.curr && labels.curr !== '-' ? labels.curr : 'Periode Terakhir';
        mtdHeader.textContent = labels.mtd && labels.mtd !== '-' ? `MtD vs ${labels.mtd}` : 'MtD';
        ytdHeader.textContent = labels.ytd && labels.ytd !== '-' ? `YtD vs ${labels.ytd}` : 'YtD';
        yoyHeader.textContent = labels.yoy && labels.yoy !== '-' ? `YoY vs ${labels.yoy}` : 'YoY';
    }

    function updateGroupLabel(groupLabel) {
        document.querySelectorAll('.dormant-group-label').forEach(el => {
            el.textContent = groupLabel === 'UKER'
                ? (el.dataset.filteredLabel || 'UKER')
                : (el.dataset.defaultLabel || 'Branch Office');
        });
    }

    function setOverlay(title, copy) {
        overlay.classList.remove('is-hidden');
        overlay.querySelector('.dormant-loading-title').textContent = title;
        overlay.querySelector('.dormant-loading-copy').textContent = copy;
    }

    function hideOverlay() {
        overlay.classList.add('is-hidden');
    }

    function resetTableState() {
        updateGroupLabel('BRANCH OFFICE');
        tableBody.innerHTML = `<tr><td colspan="5" class="dormant-empty-state"><i class="fas fa-inbox fa-2x text-muted mb-3 opacity-50"></i><strong>Belum ada data</strong>Klik <strong>Tampilkan Data</strong>.</td></tr>`;
        renderFoot({});
        activeMeta.textContent = '-';
        comparisonMeta.textContent = '-';
        badge.textContent = '-';
        updateHeaders({});
        setOverlay('Siap Memuat Data', 'Pilih filter lalu klik Tampilkan Data.');
        const paginationContainer = document.getElementById('dormantPaginationContainer');
        if (paginationContainer) paginationContainer.innerHTML = '';
        allRows = [];
        currentPage = 1;
    }

    function renderHiddenInputs(container, name, values) {
        const fragment = document.createDocumentFragment();
        values.forEach(value => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `${name}[]`;
            input.value = String(value);
            fragment.appendChild(input);
        });
        container.innerHTML = '';
        container.appendChild(fragment);
    }

    function updateBranchLabel() {
        branchLabel.textContent = selectedBranches.length > 0 ? selectedBranches.join(', ') : 'Area 6 - All';
    }

    function updateUnitLabel() {
        unitLabel.textContent = selectedUnits.length > 0 ? selectedUnits.join(', ') : 'ALL UKER';
    }

    function closeMenus(except = null) {
        if (except !== 'branch') {
            branchMenu.classList.remove('show');
            branchDropdown.setAttribute('aria-expanded', 'false');
        }
        if (except !== 'unit') {
            unitMenu.classList.remove('show');
            unitDropdown.setAttribute('aria-expanded', 'false');
        }
    }

    function getCheckedValues(selector) {
        return Array.from(document.querySelectorAll(selector))
            .filter(el => el.checked)
            .map(el => String(el.value))
            .filter(Boolean);
    }

    function renderBranchMenu() {
        if (branchOptions.length === 0) {
            branchMenu.innerHTML = '<div class="dropdown-item text-muted small">Tidak ada opsi</div>';
            return;
        }

        const fragment = document.createDocumentFragment();
        branchOptions.forEach((item, index) => {
            const value = String(item.value ?? item);
            const label = String(item.label ?? item);
            const checked = selectedBranches.includes(value) ? 'checked' : '';

            const labelEl = document.createElement('label');
            labelEl.className = 'dropdown-item';
            labelEl.setAttribute('for', `dormant_branch_${index}`);
            labelEl.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input dormant-branch-checkbox" type="checkbox" value="${value}" id="dormant_branch_${index}" ${checked}>
                    <span class="form-check-label">${label}</span>
                </div>
            `;
            fragment.appendChild(labelEl);
        });
        branchMenu.innerHTML = '';
        branchMenu.appendChild(fragment);

        // Event delegation untuk checkbox changes
        branchMenu.querySelectorAll('.dormant-branch-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                selectedBranches = getCheckedValues('.dormant-branch-checkbox');
                selectedUnits = [];
                renderHiddenInputs(branchInputs, 'kantor_cabang', selectedBranches);
                renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
                updateBranchLabel();
                updateUnitLabel();
                loadFilterOptions();
            });
        });
    }

    function renderUnitMenu() {
        if (selectedBranches.length === 0) {
            unitDropdown.disabled = true;
            unitMenu.innerHTML = '<div class="dropdown-item text-muted small">Pilih branch office</div>';
            selectedUnits = [];
            renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
            updateUnitLabel();
            return;
        }

        unitDropdown.disabled = false;

        if (unitOptions.length === 0) {
            unitMenu.innerHTML = '<div class="dropdown-item text-muted small">Tidak ada opsi</div>';
            selectedUnits = [];
            renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
            updateUnitLabel();
            return;
        }

        const fragment = document.createDocumentFragment();
        unitOptions.forEach((item, index) => {
            const value = String(item.value ?? item);
            const label = String(item.label ?? item);
            const checked = selectedUnits.includes(value) ? 'checked' : '';

            const labelEl = document.createElement('label');
            labelEl.className = 'dropdown-item';
            labelEl.setAttribute('for', `dormant_unit_${index}`);
            labelEl.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input dormant-unit-checkbox" type="checkbox" value="${value}" id="dormant_unit_${index}" ${checked}>
                    <span class="form-check-label">${label}</span>
                </div>
            `;
            fragment.appendChild(labelEl);
        });
        unitMenu.innerHTML = '';
        unitMenu.appendChild(fragment);

        unitMenu.querySelectorAll('.dormant-unit-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                selectedUnits = getCheckedValues('.dormant-unit-checkbox');
                renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
                updateUnitLabel();
            });
        });
    }

    function applyFilterPayload(payload) {
        branchOptions = payload.branch_options || [];
        unitOptions = payload.unit_options || [];
        selectedBranches = payload.selected_branches || [];
        selectedUnits = payload.selected_units || [];
        renderHiddenInputs(branchInputs, 'kantor_cabang', selectedBranches);
        renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
        renderBranchMenu();
        renderUnitMenu();
        updateBranchLabel();
        updateUnitLabel();
        activeMeta.textContent = formatDate(payload.selected_period);
        comparisonMeta.textContent = formatDate(payload.comparison_period);
    }

    // ────────────────────── API Calls ──────────────────────
    async function loadFilterOptions() {
        if (activeFilterController) {
            activeFilterController.abort();
        }

        if (!periodInput.value) {
            branchOptions = [];
            unitOptions = [];
            selectedBranches = [];
            selectedUnits = [];
            renderHiddenInputs(branchInputs, 'kantor_cabang', selectedBranches);
            renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
            renderBranchMenu();
            renderUnitMenu();
            activeMeta.textContent = '-';
            comparisonMeta.textContent = '-';
            return;
        }

        const params = new URLSearchParams();
        params.set('posisi', periodInput.value);
        appendArrayParams(params, 'kantor_cabang', selectedBranches);
        appendArrayParams(params, 'unit_kerja', selectedUnits);
        const cacheKey = params.toString();

        // Check cache first
        if (cachedFilterResponse[cacheKey]) {
            applyFilterPayload(cachedFilterResponse[cacheKey]);
            branchDropdown.disabled = !periodInput.value;
            if (selectedBranches.length === 0) {
                unitDropdown.disabled = true;
            }
            return;
        }

        activeFilterController = new AbortController();
        const timeoutId = window.setTimeout(() => {
            activeFilterController?.abort('timeout');
        }, 3500);

        branchDropdown.disabled = true;
        unitDropdown.disabled = true;
        branchMenu.innerHTML = '<div class="dropdown-item text-muted small">Memuat opsi...</div>';
        unitMenu.innerHTML = '<div class="dropdown-item text-muted small">Memuat opsi...</div>';

        try {
            const response = await fetch(`${filtersUrl}?${cacheKey}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                signal: activeFilterController.signal
            });

            if (!response.ok) throw new Error('Gagal memuat opsi filter');
            const payload = await response.json();
            cachedFilterResponse[cacheKey] = payload;
            applyFilterPayload(payload);
        } catch (error) {
            if (error.name !== 'AbortError') {
                branchOptions = [];
                unitOptions = [];
                selectedBranches = [];
                selectedUnits = [];
                renderHiddenInputs(branchInputs, 'kantor_cabang', selectedBranches);
                renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
                renderBranchMenu();
                renderUnitMenu();
                activeMeta.textContent = '-';
                comparisonMeta.textContent = '-';
            }
        } finally {
            window.clearTimeout(timeoutId);
            branchDropdown.disabled = !periodInput.value;
            if (selectedBranches.length === 0) {
                unitDropdown.disabled = true;
            }
        }
    }

    async function loadReport(pushHistory = false) {
        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();
        const formData = new FormData(form);
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (value) params.append(key, value);
        }

        const paramStr = params.toString();
        if (lastRequestParams === paramStr) return;
        lastRequestParams = paramStr;

        chip.classList.remove('d-none');
        submitButton.disabled = true;
        setOverlay('Sedang Mengolah', 'Memproses data rekening dormant.');

        try {
            const response = await fetch(dataUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: paramStr,
                signal: activeController.signal
            });

            if (!response.ok) throw new Error('Gagal memuat report');
            const payload = await response.json();

            updateGroupLabel(payload.group_label || 'BRANCH OFFICE');
            renderRows(payload.data || []);
            renderFoot(payload.total || {});
            updateHeaders(payload.labels || {});
            activeMeta.textContent = formatDate(payload.effective_dates?.curr);
            comparisonMeta.textContent = formatDate(payload.effective_dates?.mtd);
            const dataLength = (payload.data || []).length;
            const totalPages = Math.ceil(dataLength / ROWS_PER_PAGE) || 1;
            badge.textContent = `${formatDate(payload.effective_dates?.curr)} | ${dataLength} row${dataLength !== 1 ? 's' : ''} (hal. 1/${totalPages})`;
            hideOverlay();

            if (pushHistory) {
                const pageUrl = new URL(@json(route('report.rekening-dormant')), window.location.origin);
                params.forEach((value, key) => pageUrl.searchParams.append(key, value));
                window.history.replaceState({}, '', pageUrl.toString());
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                updateGroupLabel('BRANCH OFFICE');
                tableBody.innerHTML = `<tr><td colspan="5" class="dormant-empty-state"><i class="fas fa-exclamation-triangle fa-2x text-danger mb-3 opacity-50"></i><strong>Gagal memuat report</strong>Coba ulangi.</td></tr>`;
                renderFoot({});
                badge.textContent = '-';
                setOverlay('Siap Memuat Data', 'Silakan coba lagi.');
            }
        } finally {
            chip.classList.add('d-none');
            submitButton.disabled = false;
        }
    }

    // ────────────────────── Event Listeners ──────────────────────
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadReport(true);
    });

    branchDropdown.addEventListener('click', function (event) {
        event.preventDefault();
        if (branchDropdown.disabled) return;
        const willShow = !branchMenu.classList.contains('show');
        closeMenus();
        branchMenu.classList.toggle('show', willShow);
        branchDropdown.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    });

    unitDropdown.addEventListener('click', function (event) {
        event.preventDefault();
        if (unitDropdown.disabled) return;
        const willShow = !unitMenu.classList.contains('show');
        closeMenus();
        unitMenu.classList.toggle('show', willShow);
        unitDropdown.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.dormant-filter-dropdown')) {
            closeMenus();
        }
    });

    periodInput.addEventListener('change', function () {
        selectedBranches = [];
        selectedUnits = [];
        renderHiddenInputs(branchInputs, 'kantor_cabang', selectedBranches);
        renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
        updateBranchLabel();
        updateUnitLabel();
        resetTableState();
        loadFilterOptions();
    });

    resetButton.addEventListener('click', function () {
        periodInput.value = defaultPeriod;
        selectedBranches = [];
        selectedUnits = [];
        renderHiddenInputs(branchInputs, 'kantor_cabang', selectedBranches);
        renderHiddenInputs(unitInputs, 'unit_kerja', selectedUnits);
        updateBranchLabel();
        updateUnitLabel();
        branchOptions = [];
        unitOptions = [];
        renderBranchMenu();
        renderUnitMenu();
        resetTableState();
        loadFilterOptions();
    });

    // ────────────────────── Initialization ──────────────────────
    resetTableState();
    loadFilterOptions();
});
</script>
@endsection
