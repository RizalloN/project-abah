@extends('layouts.admin')

@section('title', 'Kinerja New Payroll')

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

    .report-wrapper {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
        padding-bottom: 1.5rem;
    }

    .report-filter-card,
    .report-data-card {
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: visible;
        background: var(--surface-color);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        transition: box-shadow 0.3s ease;
    }
    
    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: var(--surface-color);
        border-radius: 16px;
    }
    
    .report-filter-card .card-body { overflow: visible; }
    
    .report-filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .report-filter-card .form-control {
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

    .report-filter-card .form-control:focus {
        border-color: var(--primary-blue-light) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
        outline: none;
    }

    .report-filter-card .form-control:disabled {
        background: #f1f5f9 !important; /* slate-100 */
        color: var(--text-muted) !important;
        cursor: not-allowed;
    }

    .branch-filter-dropdown,
    .uker-filter-dropdown { position: relative; }
    
    .branch-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        background: var(--surface-color);
        font-weight: 500;
    }
    
    .branch-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .branch-dropdown-menu,
    .uker-dropdown-menu {
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
    
    .branch-dropdown-menu.show,
    .uker-dropdown-menu.show { display: block; }
    
    .branch-dropdown-menu .dropdown-item,
    .uker-dropdown-menu .dropdown-item {
        padding: 0.5rem 1rem;
        cursor: pointer;
        margin-bottom: 0;
        transition: background-color 0.2s ease;
    }
    
    .branch-dropdown-menu .dropdown-item:hover,
    .uker-dropdown-menu .dropdown-item:hover {
        background-color: #f1f5f9;
    }

    .branch-dropdown-menu .form-check,
    .uker-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .branch-dropdown-menu .form-check-input,
    .uker-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
        cursor: pointer;
    }
    
    .branch-dropdown-menu .form-check-label,
    .uker-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 500;
        cursor: pointer;
    }
    
    .table-container { 
        width: 100%; 
        overflow-x: auto; 
        -webkit-overflow-scrolling: touch; 
        border-radius: 0 0 12px 12px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .table-container::-webkit-scrollbar { height: 8px; }
    .table-container::-webkit-scrollbar-track { background: transparent; }
    .table-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }

    .table-report {
        border-collapse: separate;
        border-spacing: 0;
        width: max-content;
        min-width: 100%;
        table-layout: auto;
        white-space: nowrap;
        margin: 0;
    }
    
    .table-report th, .table-report td {
        vertical-align: middle !important;
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
        padding: 0.75rem 1rem;
    }

    .table-report th:last-child, .table-report td:last-child {
        border-right: none;
    }

    .table-report th { 
        font-size: 0.7rem; 
        text-align: center; 
        font-weight: 600; 
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .table-report td { 
        font-size: 0.8rem; 
        text-align: right; 
        background: var(--surface-color);
        font-variant-numeric: tabular-nums;
        transition: background-color 0.15s ease;
    }

    .table-report td.text-left { text-align: left; }
    
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
        top: 41px;
        z-index: 9;
    }
    
    .col-block { background-color: #f1f5f9; } /* slate-100 */
    .rka-col { background-color: #fffbeb !important; color: #b45309 !important; } /* amber-50 / amber-700 */
    
    .table-hover tbody tr:hover td { background-color: #f1f5f9; }
    .table-hover tbody tr:hover .sticky-col { background-color: #f1f5f9; }
    
    .row-total td { 
        background-color: #e0e7ff !important; /* blue-100 */
        color: var(--primary-blue-dark) !important; 
        font-weight: 700; 
        border-top: 2px solid var(--primary-blue-light) !important;
    }
    
    .row-total:hover td {
        background-color: #dbeafe !important;
    }

    .text-negative { color: #dc2626; font-weight: 700; } /* red-600 */
    
    .nav-tabs.report-tabs { 
        border-bottom: 1px solid var(--border-color); 
        flex-wrap: nowrap; 
        overflow-x: auto; 
        overflow-y: hidden; 
        white-space: nowrap; 
        scrollbar-width: thin; 
    }
    
    .nav-tabs.report-tabs .nav-link { 
        border: none; 
        font-weight: 600; 
        color: var(--text-muted); 
        padding: 1rem 1.25rem; 
        font-size: 0.9rem; 
        background: transparent; 
        transition: all 0.2s ease;
        border-bottom: 2px solid transparent;
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
</style>
@include('report._bri-report-ui')

<div class="report-wrapper pt-4">
    <div class="card card-outline card-primary shadow-sm mb-4 report-filter-card">
        <div class="card-body p-4">
            <div class="d-none">
                <h2 class="mb-4 pb-3 border-bottom" style="font-size: 1.5rem; font-weight: 800; color: var(--primary-blue-dark);">
                    <i class="fas fa-money-check-alt text-primary mr-2"></i> Kinerja New Payroll
                </h2>
            </div>

            <div class="row align-items-end">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="report-filter-label">Periode Akhir <i class="fas fa-calendar-alt text-primary ml-1"></i></label>
                        <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="report-filter-label">Branch Office (Kanca)</label>
                        <div class="branch-filter-dropdown">
                            <button type="button" class="form-control branch-dropdown-toggle" id="filterBranchDropdown" aria-haspopup="true" aria-expanded="false">
                                <span id="filter_branch_office_label" class="branch-dropdown-label">Area 6 - All</span>
                                <i class="fas fa-chevron-down text-muted"></i>
                            </button>
                            <div class="branch-dropdown-menu" id="filterBranchMenu" aria-labelledby="filterBranchDropdown">
                                @foreach(($branchOptions ?? collect()) as $branchOption)
                                    <label class="dropdown-item" for="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                        <div class="form-check">
                                            <input class="form-check-input filter-branch-checkbox" type="checkbox" value="{{ strtoupper($branchOption) }}" id="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                            <span class="form-check-label">{{ strtoupper($branchOption) }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="report-filter-label">Nama Uker</label>
                        <div class="uker-filter-dropdown">
                            <button type="button" class="form-control branch-dropdown-toggle" id="filterUkerDropdown" aria-haspopup="true" aria-expanded="false" disabled>
                                <span id="filter_nama_uker_label" class="branch-dropdown-label">ALL UKER</span>
                                <i class="fas fa-chevron-down text-muted"></i>
                            </button>
                            <div class="uker-dropdown-menu" id="filterUkerMenu" aria-labelledby="filterUkerDropdown"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label class="report-filter-label">Posisi RKA</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text border-right-0" style="background: #f8fafc; border-radius: 8px 0 0 8px;"><i class="fas fa-bullseye text-muted"></i></span>
                            </div>
                            <input type="text" id="filter_rka" class="form-control border-left-0 pl-0" style="border-radius: 0 8px 8px 0;" value="-" disabled>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 pt-3 border-top d-flex align-items-center text-muted" style="font-size: 0.85rem;">
                <i class="fas fa-info-circle text-primary mr-2"></i> Snapshot data efektif:
                <strong id="effectiveSnapshot" class="ml-1 text-dark">-</strong>
                <span id="loadingIndicator" class="ml-3 text-primary d-none font-weight-bold">
                    <i class="fas fa-circle-notch fa-spin mr-1"></i> Memuat data...
                </span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4 report-data-card">
        <div class="card-header bg-white p-0 border-bottom-0" style="border-radius: 16px 16px 0 0;">
            <ul class="nav nav-tabs report-tabs px-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab">
                        <i class="fas fa-chart-pie mr-2"></i> Total
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-rekening" role="tab">
                        <i class="fas fa-university mr-2"></i> Rekening New Payroll
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-saldo" role="tab">
                        <i class="fas fa-wallet mr-2"></i> Saldo New Payroll
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-total" role="tabpanel">
                    <div class="table-container">
                        <table class="table table-report m-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="bg-header-main sticky-col align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 210px;">BRANCH OFFICE</th>
                                    <th colspan="5" class="bg-header-main">New Rekening Payroll</th>
                                    <th colspan="5" class="bg-header-main" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">Saldo New Payroll</th>
                                    <th colspan="3" class="bg-header-main" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">Kualitas New Payroll</th>
                                </tr>
                                <tr class="bg-header-sub">
                                    <th class="lbl-curr">-</th>
                                    <th>%YoY</th>
                                    <th>YoY</th>
                                    <th class="lbl-rka rka-col">RKA</th>
                                    <th class="rka-col">Penc (%)</th>
                                    <th class="lbl-curr" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">-</th>
                                    <th>%YoY</th>
                                    <th>YoY</th>
                                    <th class="lbl-rka rka-col">RKA</th>
                                    <th class="rka-col">Penc (%)</th>
                                    <th class="lbl-curr" style="border-left: 2px solid rgba(255,255,255,0.4) !important;">-</th>
                                    <th>%YoY</th>
                                    <th>YoY</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-total">
                                <tr><td colspan="14" class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-3 opacity-50 d-block"></i> Belum ada data</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-rekening" role="tabpanel">
                    <div class="table-container">
                        <table class="table table-report m-0">
                            <thead>
                                <tr>
                                    <th colspan="8" class="bg-header-main">New Rekening Payroll</th>
                                </tr>
                                <tr class="bg-header-sub">
                                    <th class="text-left sticky-col bg-header-sub col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 210px; z-index: 11; box-shadow: none;">Branch Office</th>
                                    <th class="lbl-yoy">-</th>
                                    <th class="lbl-prev">-</th>
                                    <th class="lbl-curr">-</th>
                                    <th>YoY</th>
                                    <th>YoY (%)</th>
                                    <th class="lbl-rka rka-col">RKA</th>
                                    <th class="rka-col">Penc (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-rekening"></tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-saldo" role="tabpanel">
                    <div class="table-container">
                        <table class="table table-report m-0">
                            <thead>
                                <tr>
                                    <th colspan="8" class="bg-header-main">Saldo New Payroll</th>
                                </tr>
                                <tr class="bg-header-sub">
                                    <th class="text-left sticky-col bg-header-sub col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 210px; z-index: 11; box-shadow: none;">Branch Office</th>
                                    <th class="lbl-yoy">-</th>
                                    <th class="lbl-prev">-</th>
                                    <th class="lbl-curr">-</th>
                                    <th>YoY</th>
                                    <th>YoY (%)</th>
                                    <th class="lbl-rka rka-col">RKA</th>
                                    <th class="rka-col">Penc (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-saldo"></tbody>
                        </table>
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
    const filterPosisi = document.getElementById('filter_posisi');
    const filterRka = document.getElementById('filter_rka');
    const effectiveSnapshot = document.getElementById('effectiveSnapshot');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const branchDropdown = document.getElementById('filterBranchDropdown');
    const branchMenu = document.getElementById('filterBranchMenu');
    const branchLabel = document.getElementById('filter_branch_office_label');
    const ukerDropdown = document.getElementById('filterUkerDropdown');
    const ukerMenu = document.getElementById('filterUkerMenu');
    const ukerLabel = document.getElementById('filter_nama_uker_label');
    const branchUkerMap = @json(collect($branchUkerMap ?? [])->mapWithKeys(function ($ukers, $branch) {
        return [
            strtoupper(trim((string) $branch)) => collect($ukers)
                ->map(fn ($uker) => strtoupper(trim((string) $uker)))
                ->values()
                ->all()
        ];
    }));
    let selectedBranches = [];
    let selectedUkers = [];

    function normalizeUpper(value) {
        return String(value || '').trim().toUpperCase();
    }

    function getCheckedValues(selector) {
        return Array.from(document.querySelectorAll(selector))
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => normalizeUpper(checkbox.value))
            .filter(Boolean);
    }

    function syncGroupLabels() {
        const isFiltered = selectedBranches.length > 0;
        document.querySelectorAll('.col-group-label').forEach((label) => {
            label.textContent = isFiltered
                ? (label.dataset.filteredLabel || 'UKER')
                : (label.dataset.defaultLabel || 'BRANCH OFFICE');
        });
    }

    function updateBranchLabel() {
        branchLabel.textContent = selectedBranches.length > 0 ? selectedBranches.join(', ') : 'Area 6 - All';
        syncGroupLabels();
    }

    function renderUkerOptions() {
        const availableUkers = [...new Set(
            selectedBranches.flatMap((branch) => branchUkerMap[branch] || [])
        )].sort();

        selectedUkers = selectedUkers.filter((uker) => availableUkers.includes(uker));

        if (selectedBranches.length === 0) {
            ukerDropdown.disabled = true;
            ukerLabel.textContent = 'ALL UKER';
            ukerMenu.innerHTML = '';
            selectedUkers = [];
            return;
        }

        ukerDropdown.disabled = false;

        if (availableUkers.length === 0) {
            ukerMenu.innerHTML = '<div class="dropdown-item text-muted small">Data uker belum tersedia</div>';
            ukerLabel.textContent = 'ALL UKER';
            return;
        }

        ukerMenu.innerHTML = availableUkers.map((uker) => {
            const id = `uker_${uker.toLowerCase().replace(/[^a-z0-9]+/g, '_')}`;
            const checked = selectedUkers.includes(uker) ? 'checked' : '';
            return `
                <label class="dropdown-item" for="${id}">
                    <div class="form-check">
                        <input class="form-check-input filter-uker-checkbox" type="checkbox" value="${uker}" id="${id}" ${checked}>
                        <span class="form-check-label">${uker}</span>
                    </div>
                </label>
            `;
        }).join('');

        bindUkerCheckboxes();
        updateUkerLabel();
    }

    function updateUkerLabel() {
        ukerLabel.textContent = selectedUkers.length > 0 ? selectedUkers.join(', ') : 'ALL UKER';
    }

    function closeMenus(except = null) {
        if (except !== 'branch') {
            branchMenu.classList.remove('show');
            branchDropdown.setAttribute('aria-expanded', 'false');
        }
        if (except !== 'uker') {
            ukerMenu.classList.remove('show');
            ukerDropdown.setAttribute('aria-expanded', 'false');
        }
    }

    function bindUkerCheckboxes() {
        document.querySelectorAll('.filter-uker-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', function () {
                selectedUkers = getCheckedValues('.filter-uker-checkbox');
                updateUkerLabel();
                loadReport();
            });
        });
    }

    function formatNumber(value, decimals = 0) {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
            return '-';
        }

        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(Number(value));
    }

    function formatSigned(value, decimals = 0) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return '-';
        }

        const number = Number(value);
        const formatted = formatNumber(Math.abs(number), decimals);
        return number < 0 ? `(${formatted})` : formatted;
    }

    function formatPercent(value) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return '-';
        }

        return `${formatNumber(value, 1)}%`;
    }

    function negativeClass(value) {
        return Number(value) < 0 ? 'text-negative' : '';
    }

    function emptyMetric() {
        return {
            curr: null,
            prev: null,
            yoy_prev: null,
            yoy: null,
            yoy_pct: null,
            rka: null,
            penc_pct: null
        };
    }

    function metricCells(metric, decimals = 0, useBlockedClass = true, isSeparator = false) {
        const blockClass = useBlockedClass ? 'col-block' : '';
        const sepStyle = isSeparator ? 'border-left: 2px solid rgba(0,0,0,0.1) !important;' : '';

        return `
            <td class="${blockClass}" style="${sepStyle}">${formatNumber(metric.curr, decimals)}</td>
            <td class="${negativeClass(metric.yoy_pct)}">${formatPercent(metric.yoy_pct)}</td>
            <td class="${negativeClass(metric.yoy)}">${formatSigned(metric.yoy, decimals)}</td>
            <td class="rka-col">${formatNumber(metric.rka, decimals)}</td>
            <td class="rka-col font-weight-bold">${formatPercent(metric.penc_pct)}</td>
        `;
    }

    function metricTrendCells(metric, decimals = 0) {
        return `
            <td>${formatNumber(metric.yoy_prev, decimals)}</td>
            <td>${formatNumber(metric.prev, decimals)}</td>
            <td class="col-block font-weight-bold text-dark">${formatNumber(metric.curr, decimals)}</td>
            <td class="${negativeClass(metric.yoy)}">${formatSigned(metric.yoy, decimals)}</td>
            <td class="${negativeClass(metric.yoy_pct)}">${formatPercent(metric.yoy_pct)}</td>
            <td class="rka-col">${formatNumber(metric.rka, decimals)}</td>
            <td class="rka-col font-weight-bold">${formatPercent(metric.penc_pct)}</td>
        `;
    }

    function kualitasCells(metric, isSeparator = false) {
        const sepStyle = isSeparator ? 'border-left: 2px solid rgba(0,0,0,0.1) !important;' : '';
        return `
            <td class="col-block" style="${sepStyle}">${formatNumber(metric.curr)}</td>
            <td class="${negativeClass(metric.yoy_pct)}">${formatPercent(metric.yoy_pct)}</td>
            <td class="${negativeClass(metric.yoy)}">${formatSigned(metric.yoy)}</td>
        `;
    }

    function renderRows(targetId, rowsHtml) {
        document.getElementById(targetId).innerHTML = rowsHtml;
    }

    function updateLabels(labels) {
        document.querySelectorAll('.lbl-curr').forEach(el => el.textContent = labels.curr || '-');
        document.querySelectorAll('.lbl-prev').forEach(el => el.textContent = labels.prev || '-');
        document.querySelectorAll('.lbl-yoy').forEach(el => el.textContent = labels.yoy_prev || '-');
        document.querySelectorAll('.lbl-rka').forEach(el => el.textContent = labels.rka || 'RKA');
        filterRka.value = labels.rka || '-';
    }

    function buildTotalTable(data, total) {
        if (!data || data.length === 0) return '<tr><td colspan="14" class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-3 opacity-50 d-block"></i> Data tidak ditemukan</td></tr>';

        const rows = data.map(row => `
            <tr>
                <td class="sticky-col text-left font-weight-bold text-dark">${row.branch}</td>
                ${metricCells(row.rekening, 0, true, false)}
                ${metricCells(row.saldo, 2, true, true)}
                ${kualitasCells(row.kualitas || emptyMetric(), true)}
            </tr>
        `).join('');

        return rows + `
            <tr class="row-total">
                <td class="sticky-col text-left">${total.branch}</td>
                ${metricCells(total.rekening, 0, false, false)}
                ${metricCells(total.saldo, 2, false, true)}
                ${kualitasCells(total.kualitas || emptyMetric(), true)}
            </tr>
        `;
    }

    function buildMetricTable(data, total, metricKey, decimals = 0) {
        if (!data || data.length === 0) return '<tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-3 opacity-50 d-block"></i> Data tidak ditemukan</td></tr>';

        const rows = data.map(row => `
            <tr>
                <td class="sticky-col text-left font-weight-bold text-dark">${row.branch}</td>
                ${metricTrendCells(row[metricKey], decimals)}
            </tr>
        `).join('');

        return rows + `
            <tr class="row-total">
                <td class="sticky-col text-left">${total.branch}</td>
                ${metricTrendCells(total[metricKey], decimals)}
            </tr>
        `;
    }

    async function loadReport() {
        loadingIndicator.classList.remove('d-none');

        try {
            const response = await fetch('{{ route('report.data.newpayroll') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    posisi: filterPosisi.value,
                    branch_office: selectedBranches,
                    nama_uker: selectedUkers
                })
            });

            const result = await response.json();

            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Gagal memuat data payroll.');
            }

            updateLabels(result.labels || {});
            effectiveSnapshot.textContent = result.effective_snapshot || '-';
            syncGroupLabels();

            renderRows('tbody-total', buildTotalTable(result.data || [], result.total || {
                branch: 'TOTAL AREA 6',
                rekening: emptyMetric(),
                saldo: emptyMetric(),
                kualitas: emptyMetric()
            }));

            renderRows('tbody-rekening', buildMetricTable(result.data || [], result.total || {
                branch: 'TOTAL AREA 6',
                rekening: emptyMetric()
            }, 'rekening', 0));

            renderRows('tbody-saldo', buildMetricTable(result.data || [], result.total || {
                branch: 'TOTAL AREA 6',
                saldo: emptyMetric()
            }, 'saldo', 2));
        } catch (error) {
            effectiveSnapshot.textContent = 'Gagal memuat data';
            renderRows('tbody-total', '<tr><td colspan="14" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-3 d-block opacity-50"></i> Gagal memuat data payroll.</td></tr>');
            renderRows('tbody-rekening', '<tr><td colspan="8" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-3 d-block opacity-50"></i> Gagal memuat data payroll.</td></tr>');
            renderRows('tbody-saldo', '<tr><td colspan="8" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-3 d-block opacity-50"></i> Gagal memuat data payroll.</td></tr>');
        } finally {
            loadingIndicator.classList.add('d-none');
        }
    }

    branchDropdown.addEventListener('click', function (event) {
        event.preventDefault();
        const willShow = !branchMenu.classList.contains('show');
        closeMenus();
        branchMenu.classList.toggle('show', willShow);
        branchDropdown.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    });

    ukerDropdown.addEventListener('click', function (event) {
        event.preventDefault();
        if (ukerDropdown.disabled) {
            return;
        }
        const willShow = !ukerMenu.classList.contains('show');
        closeMenus();
        ukerMenu.classList.toggle('show', willShow);
        ukerDropdown.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    });

    document.querySelectorAll('.filter-branch-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            selectedBranches = getCheckedValues('.filter-branch-checkbox');
            updateBranchLabel();
            renderUkerOptions();
            loadReport();
        });
    });

    document.addEventListener('click', function (event) {
        if (!branchDropdown.closest('.branch-filter-dropdown').contains(event.target)
            && !ukerDropdown.closest('.uker-filter-dropdown').contains(event.target)) {
            closeMenus();
        }
    });

    filterPosisi.addEventListener('change', loadReport);
    updateBranchLabel();
    renderUkerOptions();
    loadReport();
});
</script>
@endsection
