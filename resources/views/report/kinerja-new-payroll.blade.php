@extends('layouts.admin')

@section('title', 'Kinerja New Payroll')

@section('content')
<style>
    .report-filter-card,
    .report-data-card {
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: visible;
        box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08) !important;
    }
    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: #ffffff;
    }
    .report-filter-card .card-body { overflow: visible; }
    .report-filter-card .form-control {
        border-radius: 10px;
        min-height: 40px;
    }
    .branch-filter-dropdown,
    .uker-filter-dropdown { position: relative; }
    .branch-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        background: #fff;
    }
    .branch-dropdown-toggle:disabled {
        background: #e9ecef;
        cursor: not-allowed;
        opacity: 1;
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
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        padding: 8px 0;
    }
    .branch-dropdown-menu.show,
    .uker-dropdown-menu.show { display: block; }
    .branch-dropdown-menu .dropdown-item,
    .uker-dropdown-menu .dropdown-item {
        padding: 0.45rem 1rem;
        cursor: pointer;
        margin-bottom: 0;
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
    }
    .branch-dropdown-menu .form-check-label,
    .uker-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 500;
        cursor: pointer;
    }
    .table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table-report {
        border-collapse: collapse;
        width: 100%;
        table-layout: auto;
        white-space: nowrap;
    }
    .table-report th, .table-report td {
        vertical-align: middle !important;
        border: 1px solid #dee2e6;
    }
    .table-report th { font-size: 0.65rem; padding: 10px 6px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 8px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    .bg-header-main { background-color: #0056b3 !important; color: #ffffff !important; border-color: #004085 !important; }
    .bg-header-sub { background-color: #8fb5df !important; color: #102a43 !important; font-weight: bold; border-color: #7ea7d3 !important; }
    .col-block { background-color: #dbe9f8; }
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; }
    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #7ba7e6 !important; color: #102a43 !important; font-weight: bold; }
    .row-total td { font-weight: bold; }
    .text-negative { color: #ff0000; }
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>

<div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-dark text-sm font-weight-bold mb-1">Periode Akhir <i class="fas fa-edit text-primary ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                    <div class="branch-filter-dropdown">
                        <button type="button" class="form-control font-weight-bold branch-dropdown-toggle" id="filterBranchDropdown" aria-haspopup="true" aria-expanded="false">
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
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Uker</label>
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
                    <label class="text-muted text-sm mb-1">Posisi RKA</label>
                    <input type="text" id="filter_rka" class="form-control" value="-" disabled>
                </div>
            </div>
        </div>
        <div class="mt-3 small text-muted">
            Snapshot data efektif:
            <span id="effectiveSnapshot" class="font-weight-bold">-</span>
            <span id="loadingIndicator" class="ml-2 text-primary d-none">
                <i class="fas fa-spinner fa-spin"></i> Memuat data...
            </span>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 report-data-card">
    <div class="card-header bg-white p-0 border-bottom-0">
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab">
                    <i class="fas fa-chart-pie mr-1"></i> Total
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-rekening" role="tab">
                    <i class="fas fa-university mr-1"></i> Rekening New Payroll
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-saldo" role="tab">
                    <i class="fas fa-wallet mr-1"></i> Saldo New Payroll
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-total" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-header-main align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 210px;">BRANCH OFFICE</th>
                                <th colspan="5" class="bg-header-main">New Rekening Payroll</th>
                                <th colspan="5" class="bg-header-main">Saldo New Payroll</th>
                                <th colspan="3" class="bg-header-main">Kualitas New Payroll</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr">-</th>
                                <th>%YoY</th>
                                <th>YoY</th>
                                <th class="lbl-rka rka-col">RKA</th>
                                <th class="rka-col">Penc (%)</th>
                                <th class="lbl-curr">-</th>
                                <th>%YoY</th>
                                <th>YoY</th>
                                <th class="lbl-rka rka-col">RKA</th>
                                <th class="rka-col">Penc (%)</th>
                                <th class="lbl-curr">-</th>
                                <th>%YoY</th>
                                <th>YoY</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-total"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-rekening" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th colspan="8" class="bg-header-main">New Rekening Payroll</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="text-left col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 210px;">Branch Office</th>
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
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th colspan="8" class="bg-header-main">Saldo New Payroll</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="text-left col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 210px;">Branch Office</th>
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

    function metricCells(metric, decimals = 0, useBlockedClass = true) {
        const blockClass = useBlockedClass ? 'col-block' : '';

        return `
            <td class="${blockClass}">${formatNumber(metric.curr, decimals)}</td>
            <td class="${negativeClass(metric.yoy_pct)}">${formatPercent(metric.yoy_pct)}</td>
            <td class="${negativeClass(metric.yoy)}">${formatSigned(metric.yoy, decimals)}</td>
            <td class="rka-col">${formatNumber(metric.rka, decimals)}</td>
            <td class="rka-col">${formatPercent(metric.penc_pct)}</td>
        `;
    }

    function metricTrendCells(metric, decimals = 0) {
        return `
            <td>${formatNumber(metric.yoy_prev, decimals)}</td>
            <td>${formatNumber(metric.prev, decimals)}</td>
            <td class="col-block">${formatNumber(metric.curr, decimals)}</td>
            <td class="${negativeClass(metric.yoy)}">${formatSigned(metric.yoy, decimals)}</td>
            <td class="${negativeClass(metric.yoy_pct)}">${formatPercent(metric.yoy_pct)}</td>
            <td class="rka-col">${formatNumber(metric.rka, decimals)}</td>
            <td class="rka-col">${formatPercent(metric.penc_pct)}</td>
        `;
    }

    function kualitasCells(metric) {
        return `
            <td class="col-block">${formatNumber(metric.curr)}</td>
            <td>${formatPercent(metric.yoy_pct)}</td>
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
        const rows = data.map(row => `
            <tr>
                <td class="text-left font-weight-bold">${row.branch}</td>
                ${metricCells(row.rekening, 0)}
                ${metricCells(row.saldo, 2)}
                ${kualitasCells(row.kualitas || emptyMetric())}
            </tr>
        `).join('');

        return rows + `
            <tr class="row-total">
                <td class="text-left">${total.branch}</td>
                ${metricCells(total.rekening, 0, false)}
                ${metricCells(total.saldo, 2, false)}
                ${kualitasCells(total.kualitas || emptyMetric())}
            </tr>
        `;
    }

    function buildMetricTable(data, total, metricKey, decimals = 0) {
        const rows = data.map(row => `
            <tr>
                <td class="text-left font-weight-bold">${row.branch}</td>
                ${metricTrendCells(row[metricKey], decimals)}
            </tr>
        `).join('');

        return rows + `
            <tr class="row-total">
                <td class="text-left">${total.branch}</td>
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
            renderRows('tbody-total', '<tr><td colspan="14" class="text-center text-danger py-4">Gagal memuat data payroll.</td></tr>');
            renderRows('tbody-rekening', '<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat data payroll.</td></tr>');
            renderRows('tbody-saldo', '<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat data payroll.</td></tr>');
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
