@extends('layouts.admin')

@section('title', 'Tunggakan Kecil')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .loan-shell {
        position: relative;
        z-index: 3;
        overflow: visible;
    }

    .loan-table-shell {
        position: relative;
        z-index: 1;
    }

    .loan-shell .card-body {
        overflow: visible;
        position: relative;
        z-index: 2;
    }

    .small-arrears-filter-dropdown {
        position: relative;
        z-index: 1085;
        margin-bottom: 0.5rem;
    }

    .small-arrears-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        min-height: 48px;
        padding: 0.65rem 1rem;
        border: 2px solid #9fc0ea;
        border-radius: 16px;
        background: #ffffff;
        color: var(--loan-blue-ink);
        font-weight: 700;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.9), 0 8px 18px rgba(15, 23, 42, 0.06);
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .small-arrears-dropdown-toggle:hover,
    .small-arrears-dropdown-toggle:focus {
        border-color: #5c8fd3;
        box-shadow: 0 0 0 0.18rem rgba(60, 113, 190, 0.12);
        outline: none;
    }

    .small-arrears-dropdown-toggle:disabled {
        background: #eef2f7;
        border-color: #d9e3f1;
        color: #94a3b8;
        box-shadow: none;
        cursor: not-allowed;
    }

    .small-arrears-dropdown-label {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: left;
    }

    .small-arrears-dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        z-index: 1100;
        display: none;
        max-height: 280px;
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid #d9e2ef;
        border-radius: 14px;
        box-shadow: 0 18px 35px rgba(15, 23, 42, 0.14);
        padding: 0.4rem 0;
    }

    .small-arrears-dropdown-menu.show {
        display: block;
    }

    .small-arrears-dropdown-item {
        display: block;
        margin: 0;
        padding: 0.55rem 1rem;
        cursor: pointer;
    }

    .small-arrears-dropdown-item:hover {
        background: #f5f9ff;
    }

    .small-arrears-dropdown-check {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin: 0;
        color: #334155;
        font-weight: 700;
    }

    .small-arrears-dropdown-check input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: #366ab2;
    }

    .small-arrears-dropdown-check span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .loan-small-table-wrap {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .loan-small-table {
        width: 100%;
        min-width: 790px;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        color: #1f2937;
        font-size: 0.78rem;
    }

    .loan-small-table col.col-group {
        width: 210px;
    }

    .loan-small-table col.col-position-count,
    .loan-small-table col.col-position-rp {
        width: 70px;
    }

    .loan-small-table col.col-export {
        width: 110px;
    }

    .loan-small-table thead th,
    .loan-small-table tbody th,
    .loan-small-table tbody td,
    .loan-small-table tfoot th,
    .loan-small-table tfoot td {
        height: 30px;
        padding: 0.42rem 0.5rem !important;
        vertical-align: middle !important;
        white-space: normal;
        line-height: 1.25;
        border-right: 1px solid #dbe3ef !important;
        border-bottom: 1px solid #dbe3ef !important;
    }

    .loan-small-table thead th:last-child,
    .loan-small-table tbody td:last-child,
    .loan-small-table tfoot td:last-child {
        border-right: none !important;
    }

    .loan-small-table thead th {
        background: var(--loan-blue-deep) !important;
        color: #ffffff !important;
        text-align: center;
        letter-spacing: 0;
        text-transform: none;
        font-size: 0.72rem;
        font-weight: 800;
        padding: 0.46rem 0.5rem !important;
        white-space: nowrap;
        border-right: 1px solid rgba(255, 255, 255, 0.18) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.18) !important;
    }

    .loan-small-table thead tr:first-child th {
        background: var(--loan-blue-ink) !important;
        color: #ffffff !important;
    }

    .loan-small-table thead tr:nth-child(2) th,
    .loan-small-table thead tr:nth-child(3) th {
        background: var(--loan-blue) !important;
        color: #ffffff !important;
    }

    .loan-small-table .sticky-first,
    .loan-small-table tbody th,
    .loan-small-table tfoot th {
        position: sticky;
        left: 0;
        z-index: 2;
    }

    .loan-small-table .sticky-first {
        z-index: 4;
    }

    .loan-small-table tbody th,
    .loan-small-table tfoot th {
        background: #ffffff;
        color: #0f172a;
        text-align: left;
        width: 210px;
        min-width: 210px;
        max-width: 210px;
        box-shadow: 2px 0 6px rgba(4, 42, 95, 0.05);
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 800;
    }

    .loan-small-table tbody td {
        background: #ffffff;
        font-weight: 600;
        height: 34px;
    }

    .loan-small-table tbody td.metric-positive {
        color: #16a34a;
        font-weight: 800;
    }

    .loan-small-table tbody td.metric-negative {
        color: #dc2626;
        font-weight: 800;
    }

    .loan-small-table tbody td.metric-neutral {
        color: #64748b;
        font-weight: 700;
    }

    .loan-small-table tfoot th,
    .loan-small-table tfoot td {
        background: var(--loan-blue-ink) !important;
        color: #ffffff !important;
        font-weight: 800;
    }

    .loan-small-table tbody tr:hover th,
    .loan-small-table tbody tr:hover td {
        background: #f1f7ff;
    }

    .loan-small-table tbody tr:nth-child(even) th,
    .loan-small-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .loan-small-table tbody td,
    .loan-small-table tfoot td {
        text-align: center;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .loan-small-table .sub-head {
        min-width: 70px;
        max-width: 70px;
    }

    .loan-small-table .position-group-head {
        border-left: 2px solid rgba(255, 255, 255, 0.34) !important;
        border-right: 2px solid rgba(255, 255, 255, 0.34) !important;
    }

    .loan-small-table .position-start {
        border-left: 2px solid #94a3b8 !important;
    }

    .loan-small-table .position-end {
        border-right: 2px solid #94a3b8 !important;
    }

    .loan-small-table .position-count {
        text-align: center;
        font-variant-numeric: tabular-nums;
        font-weight: 800;
    }

    .loan-small-table .position-rp {
        text-align: center;
        font-variant-numeric: tabular-nums;
        font-weight: 800;
        padding-right: 0.5rem !important;
        padding-left: 0.5rem !important;
    }

    .loan-small-table .text-start-important {
        text-align: left !important;
    }

    .loan-small-table .btn {
        min-height: 26px;
        padding: 0.18rem 0.45rem;
        border-radius: 6px;
        font-size: 0.7rem;
        line-height: 1.2;
    }

    .loan-small-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--loan-muted);
        background: var(--loan-surface-soft);
    }

    .loan-small-empty strong {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--loan-blue-ink);
    }
</style>

<div class="loan-dashboard pt-4">
    <div class="loan-title-hero d-flex flex-wrap justify-content-center align-items-center">
        <div class="loan-title-hero__wrap">
            <div class="loan-title-hero__badge">
                <i class="fas fa-coins"></i>
                <span>BRI Loan Monitoring</span>
            </div>
            <h1 class="loan-title-hero__title">TUNGGAKAN KECIL</h1>
            <p class="loan-title-hero__desc">Monitoring jumlah rekening pinjaman dengan total tunggakan pokok, bunga, dan penalti di bawah Rp100.000 pada titik posisi 31 Desember tahun lalu, bulan lalu, dan hari sekarang.</p>
        </div>
    </div>

    <div class="card loan-shell mb-5 animate-reveal">
        <div class="card-body p-4">
            <form id="smallArrearsForm" method="GET" action="{{ route('report.dashboard-pinjaman.tunggakan-kecil') }}">
                <div class="row loan-filter-grid">
                    <div class="col-xl-4 col-lg-4 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label">Branch Office (Kanca)</label>
                            <div class="small-arrears-filter-dropdown">
                                <button type="button" id="smallArrearsBranchToggle" class="small-arrears-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                                    <span id="smallArrearsBranchLabel" class="small-arrears-dropdown-label">Area 6 - All</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div id="smallArrearsBranchMenu" class="small-arrears-dropdown-menu" aria-labelledby="smallArrearsBranchToggle">
                                    @foreach($branchOptions as $branch)
                                        @php($branchId = 'small_arrears_branch_' . \Illuminate\Support\Str::slug($branch, '_'))
                                        <label class="small-arrears-dropdown-item" for="{{ $branchId }}">
                                            <span class="small-arrears-dropdown-check">
                                                <input
                                                    class="small-arrears-branch-checkbox"
                                                    type="checkbox"
                                                    value="{{ $branch }}"
                                                    id="{{ $branchId }}"
                                                    @checked(in_array($branch, $selectedBranches, true))
                                                >
                                                <span>{{ $branch === 'AREA_6_ALL' ? 'Area 6 - All' : $branch }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label">Nama Unit</label>
                            <div class="small-arrears-filter-dropdown">
                                <button type="button" id="smallArrearsUnitToggle" class="small-arrears-dropdown-toggle" aria-haspopup="true" aria-expanded="false" @disabled($isAreaAllSelected)>
                                    <span id="smallArrearsUnitLabel" class="small-arrears-dropdown-label">ALL UKER</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div id="smallArrearsUnitMenu" class="small-arrears-dropdown-menu" aria-labelledby="smallArrearsUnitToggle">
                                    @foreach($unitOptions as $unit)
                                        @php($unitId = 'small_arrears_unit_' . \Illuminate\Support\Str::slug($unit, '_'))
                                        <label class="small-arrears-dropdown-item" for="{{ $unitId }}">
                                            <span class="small-arrears-dropdown-check">
                                                <input
                                                    class="small-arrears-unit-checkbox"
                                                    type="checkbox"
                                                    value="{{ $unit }}"
                                                    id="{{ $unitId }}"
                                                    @checked(in_array($unit, $selectedUnits, true))
                                                >
                                                <span>{{ $unit }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label">Periode Posisi</label>
                            <input id="smallArrearsPeriodInput" type="date" name="periode" class="form-control loan-filter-control" value="{{ $selectedPeriod }}" max="{{ $availablePeriods->first() }}">
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center mt-3 pt-3 border-top" style="gap:.75rem;">
                    <button id="smallArrearsSubmitButton" type="submit" class="btn btn-primary font-weight-bold" style="border-radius: 12px; min-height: 44px; padding: 0 1.5rem;">
                        <i class="fas fa-filter mr-2"></i>Tampilkan Data
                    </button>
                    <button id="smallArrearsResetButton" type="button" class="btn btn-light font-weight-bold text-muted" style="border-radius: 12px; min-height: 44px; padding: 0 1.5rem;">Reset</button>
                    <div id="smallArrearsLoadingChip" class="loan-loading-chip d-none ml-auto">
                        <span class="loan-loading-dot"></span>Mengolah Jumlah Rekening Tunggakan Kecil
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="smallArrearsTableCard" class="card loan-table-shell mt-5 animate-reveal d-none">
        <div class="card-body p-4">
            <div class="loan-table-heading">
                <div>
                    <h5>Tabel Tunggakan Kecil</h5>
                    <div class="text-muted small">Kolom posisi menampilkan jumlah rekening unik, sedangkan total tunggakan menampilkan nominal rupiah.</div>
                </div>
                <div class="loan-table-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="smallArrearsBadge">Memuat posisi...</span>
                </div>
            </div>

            <div class="loan-small-table-wrap">
                <table class="table loan-summary-table loan-small-table mb-0">
                    <colgroup>
                        <col class="col-group">
                        <col class="col-position-count">
                        <col class="col-position-rp">
                        <col class="col-position-count">
                        <col class="col-position-rp">
                        <col class="col-position-count">
                        <col class="col-position-rp">
                        <col class="col-export">
                    </colgroup>
                    <thead>
                        <tr>
                            <th id="smallArrearsGroupHead" rowspan="3" class="sticky-first text-start-important" data-default-label="Branch Office" data-filtered-label="Uker">Branch Office</th>
                            <th colspan="6">Posisi</th>
                            <th rowspan="3" class="sub-head">Export Detail</th>
                        </tr>
                        <tr>
                            <th id="smallArrearsYtdHead" colspan="2" class="sub-head position-group-head">31/12</th>
                            <th id="smallArrearsMtdHead" colspan="2" class="sub-head position-group-head">Bulan Lalu</th>
                            <th id="smallArrearsCurrentSubHead" colspan="2" class="sub-head position-group-head">{{ $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') : 'Hari Ini' }}</th>
                        </tr>
                        <tr>
                            <th class="sub-head position-start position-count">Rek</th>
                            <th class="sub-head position-end position-rp">Rp</th>
                            <th class="sub-head position-start position-count">Rek</th>
                            <th class="sub-head position-end position-rp">Rp</th>
                            <th class="sub-head position-start position-count">Rek</th>
                            <th class="sub-head position-end position-rp">Rp</th>
                        </tr>
                    </thead>
                    <tbody id="smallArrearsBody">
                        <tr>
                            <td colspan="8" class="loan-small-empty">
                                <strong>Belum ada data</strong>
                                Klik <strong>Tampilkan Data</strong> untuk memuat tabel.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr id="smallArrearsFoot">
                            <th>Grand Total</th>
                            <td>0</td>
                            <td>0</td>
                            <td>0</td>
                            <td>0</td>
                            <td>0</td>
                            <td>0</td>
                            <td>-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('smallArrearsForm');
        const periodInput = document.getElementById('smallArrearsPeriodInput');
        const branchToggle = document.getElementById('smallArrearsBranchToggle');
        const branchMenu = document.getElementById('smallArrearsBranchMenu');
        const branchLabel = document.getElementById('smallArrearsBranchLabel');
        const unitToggle = document.getElementById('smallArrearsUnitToggle');
        const unitMenu = document.getElementById('smallArrearsUnitMenu');
        const unitLabel = document.getElementById('smallArrearsUnitLabel');
        const submitButton = document.getElementById('smallArrearsSubmitButton');
        const resetButton = document.getElementById('smallArrearsResetButton');
        const chip = document.getElementById('smallArrearsLoadingChip');
        const badge = document.getElementById('smallArrearsBadge');
        const groupHead = document.getElementById('smallArrearsGroupHead');
        const currentSubHead = document.getElementById('smallArrearsCurrentSubHead');
        const ytdHead = document.getElementById('smallArrearsYtdHead');
        const mtdHead = document.getElementById('smallArrearsMtdHead');
        const body = document.getElementById('smallArrearsBody');
        const foot = document.getElementById('smallArrearsFoot');

        const filtersUrl = @json(route('report.dashboard-pinjaman.tunggakan-kecil.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.tunggakan-kecil.data'));
        const exportUrl = @json(route('report.dashboard-pinjaman.tunggakan-kecil.export'));
        const pageUrl = @json(route('report.dashboard-pinjaman.tunggakan-kecil'));
        const defaultPeriod = @json($selectedPeriod);
        const areaAllValue = 'AREA_6_ALL';
        const areaBranches = @json($effectiveBranches);
        const allBranchOptions = @json($branchOptions->values()->all());
        const initialUnitOptions = @json($unitOptions->values()->all());
        const initialSelectedBranches = @json($selectedBranches);
        const initialSelectedUnits = @json($selectedUnits);
        const initialIsAreaAll = @json($isAreaAllSelected);
        const allUkerValue = 'ALL_UKER';

        function getBranchOptionLabel(option) {
            return option === areaAllValue ? 'AREA 6 All' : option;
        }

        function normalizeBranchSelection(selectedValues) {
            const selected = (selectedValues || []).filter(Boolean);

            if (selected.length === 0 || selected.includes(areaAllValue)) {
                return [areaAllValue];
            }

            return selected.filter(value => value !== areaAllValue);
        }

        function isAreaAllSelected(selectedValues) {
            return normalizeBranchSelection(selectedValues).includes(areaAllValue);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getCheckedValues(selector) {
            return Array.from(document.querySelectorAll(selector))
                .filter(input => input.checked)
                .map(input => input.value);
        }

        function getSelectedBranches() {
            return normalizeBranchSelection(getCheckedValues('.small-arrears-branch-checkbox'));
        }

        function getSelectedUnits() {
            return getCheckedValues('.small-arrears-unit-checkbox');
        }

        function getEffectiveUnitSelections() {
            const selected = getSelectedUnits();

            if (selected.length === 0 || selected.includes(allUkerValue)) {
                return [];
            }

            return selected.filter(value => value !== allUkerValue);
        }

        function closeDropdown(menu, toggle) {
            menu.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function toggleDropdown(menu, toggle) {
            const nextState = !menu.classList.contains('show');
            closeDropdown(branchMenu, branchToggle);
            closeDropdown(unitMenu, unitToggle);

            if (nextState) {
                menu.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            }
        }

        function renderBranchOptions(selectedValues) {
            const normalized = normalizeBranchSelection(selectedValues);
            branchMenu.innerHTML = allBranchOptions.map(option => {
                const optionId = `small_arrears_branch_${option.toLowerCase().replace(/[^a-z0-9]+/g, '_')}`;
                const checked = normalized.includes(option) ? 'checked' : '';

                return `
                    <label class="small-arrears-dropdown-item" for="${optionId}">
                        <span class="small-arrears-dropdown-check">
                            <input class="small-arrears-branch-checkbox" type="checkbox" value="${escapeHtml(option)}" id="${optionId}" ${checked}>
                            <span>${escapeHtml(getBranchOptionLabel(option))}</span>
                        </span>
                    </label>
                `;
            }).join('');

            updateBranchLabel(normalized);
        }

        function applyBranchSelection(selectedValues) {
            const normalized = normalizeBranchSelection(selectedValues);
            const isAreaAll = normalized.includes(areaAllValue);

            renderBranchOptions(normalized);

            if (isAreaAll) {
                renderUnitOptions([], []);
                setUnitSelectDisabled(true);
            } else {
                setUnitSelectDisabled(false);
            }

            return normalized;
        }

        function normalizeUnitSelection(options, selectedValues) {
            const safeOptions = Array.isArray(options) ? options : [];
            const filtered = (selectedValues || []).filter(value => safeOptions.includes(value));

            if (filtered.length === 0 || filtered.includes(allUkerValue)) {
                return safeOptions.includes(allUkerValue) ? [allUkerValue] : [];
            }

            return filtered.filter(value => value !== allUkerValue);
        }

        function renderUnitOptions(options, selectedValues) {
            const safeOptions = Array.isArray(options) ? options : [];
            const nextSelected = normalizeUnitSelection(safeOptions, selectedValues);

            unitMenu.innerHTML = safeOptions.map(option => {
                const optionId = `small_arrears_unit_${option.toLowerCase().replace(/[^a-z0-9]+/g, '_')}`;
                const checked = nextSelected.includes(option) ? 'checked' : '';
                const label = option === allUkerValue ? 'ALL UKER' : option;

                return `
                    <label class="small-arrears-dropdown-item" for="${optionId}">
                        <span class="small-arrears-dropdown-check">
                            <input class="small-arrears-unit-checkbox" type="checkbox" value="${escapeHtml(option)}" id="${optionId}" ${checked}>
                            <span>${escapeHtml(label)}</span>
                        </span>
                    </label>
                `;
            }).join('');

            updateUnitLabel(nextSelected);

            return nextSelected;
        }

        function setUnitSelectDisabled(disabled) {
            unitToggle.disabled = disabled;
            if (disabled) {
                closeDropdown(unitMenu, unitToggle);
            }
        }

        function updateBranchLabel(selectedValues = getSelectedBranches()) {
            branchLabel.textContent = isAreaAllSelected(selectedValues)
                ? 'Area 6 - All'
                : (selectedValues.length ? selectedValues.join(', ') : 'Area 6 - All');
        }

        function updateUnitLabel(selectedValues = getSelectedUnits()) {
            const filtered = selectedValues.filter(value => value !== allUkerValue);
            unitLabel.textContent = filtered.length ? filtered.join(', ') : 'ALL UKER';
        }

        function formatCount(value) {
            return formatNumber(Number(value || 0));
        }

        function formatCurrency(value) {
            const amount = Number(value || 0);
            return formatNumber(amount);
        }

        function updateBadge(period, branches, units, areaAll = false) {
            const periodText = period ? formatDate(period) : 'Belum pilih posisi';
            const effectiveUnits = (units || []).filter(value => value !== allUkerValue);
            const scope = areaAll
                ? 'AREA 6 All'
                : (effectiveUnits.length > 0
                    ? `${effectiveUnits.length} unit`
                    : (branches.length > 0 ? `${branches.length} branch office` : 'Area 6 - All'));

            badge.textContent = `${periodText} | ${scope}`;
        }

        function updateHeaders(payload) {
            ytdHead.textContent = payload.labels?.ytd ? formatDate(payload.labels.ytd) : '31/12';
            mtdHead.textContent = payload.labels?.mtd ? formatDate(payload.labels.mtd) : 'Bulan Lalu';
            currentSubHead.textContent = payload.selected_period ? formatDate(payload.selected_period) : 'Hari Ini';
        }

        function updateGroupHead(groupLabel) {
            const isUnit = groupLabel === 'UKER';
            groupHead.textContent = isUnit
                ? (groupHead.dataset.filteredLabel || 'Uker')
                : (groupHead.dataset.defaultLabel || 'Branch Office');
        }

        function renderFoot(total = {}) {
            foot.innerHTML = `
                <th>Grand Total</th>
                <td class="position-start position-count">${formatCount(total.ytd)}</td>
                <td class="position-end position-rp">${formatCurrency(total.ytd_tunggakan)}</td>
                <td class="position-start position-count">${formatCount(total.mtd)}</td>
                <td class="position-end position-rp">${formatCurrency(total.mtd_tunggakan)}</td>
                <td class="position-start position-count">${formatCount(total.current)}</td>
                <td class="position-end position-rp">${formatCurrency(total.current_tunggakan ?? total.total_tunggakan)}</td>
                <td>-</td>
            `;
        }

        function renderEmptyState(message, payload = {}) {
            updateGroupHead(payload.group_label || 'BRANCH OFFICE');
            updateHeaders(payload);
            body.innerHTML = `
                <tr>
                    <td colspan="8" class="loan-small-empty">
                        <strong>Data tidak ditemukan</strong>
                        ${message}
                    </td>
                </tr>
            `;
            renderFoot(payload.total || {});
            updateBadge(
                payload.selected_period || periodInput.value,
                payload.effective_branches || [],
                payload.selected_units || [],
                payload.is_area_all === true
            );
        }

        function buildExportUrl(payload, row) {
            const params = new URLSearchParams();
            if (payload.selected_period) {
                params.set('periode', payload.selected_period);
            }

            if (payload.is_area_all === true) {
                params.append('cabang1[]', row.label);
            } else {
                (payload.effective_branches || []).forEach(value => params.append('cabang1[]', value));
                params.append('unit1[]', row.label);
            }

            return `${exportUrl}?${params.toString()}`;
        }

        function renderRows(payload) {
            const rows = payload.rows || [];

            updateGroupHead(payload.group_label || 'BRANCH OFFICE');
            updateHeaders(payload);
            updateBadge(payload.selected_period, payload.effective_branches || [], payload.selected_units || [], payload.is_area_all === true);

            if (rows.length === 0) {
                renderEmptyState('Coba ubah filter branch office, unit, atau tanggal posisi.', payload);
                return;
            }

            body.innerHTML = rows.map(row => `
                <tr>
                    <th class="text-start-important">${row.label}</th>
                    <td class="position-start position-count">${formatCount(row.ytd)}</td>
                    <td class="position-end position-rp">${formatCurrency(row.ytd_tunggakan)}</td>
                    <td class="position-start position-count">${formatCount(row.mtd)}</td>
                    <td class="position-end position-rp">${formatCurrency(row.mtd_tunggakan)}</td>
                    <td class="position-start position-count">${formatCount(row.current)}</td>
                    <td class="position-end position-rp">${formatCurrency(row.current_tunggakan ?? row.total_tunggakan)}</td>
                    <td>
                        <a class="btn btn-sm btn-outline-success" href="${buildExportUrl(payload, row)}">
                            <i class="fas fa-file-excel mr-1"></i>Export Excel
                        </a>
                    </td>
                </tr>
            `).join('');

            renderFoot(payload.total || {});
        }

        async function loadFilters() {
            const params = new URLSearchParams();
            if (periodInput.value) {
                params.set('periode', periodInput.value);
            }
            getSelectedBranches().forEach(value => params.append('cabang1[]', value));
            getEffectiveUnitSelections().forEach(value => params.append('unit1[]', value));

            const response = await fetch(`${filtersUrl}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json();
            const selectedBranches = normalizeBranchSelection(payload.selected_branches || []);
            renderBranchOptions(selectedBranches);
            const selectedUnits = renderUnitOptions(
                payload.unit_options || [],
                payload.is_area_all ? [] : (payload.selected_units && payload.selected_units.length ? payload.selected_units : [allUkerValue])
            );
            setUnitSelectDisabled(payload.is_area_all === true);
            if (payload.selected_period) {
                periodInput.value = payload.selected_period;
            }
            updateBadge(payload.selected_period || periodInput.value, payload.effective_branches || [], selectedUnits, payload.is_area_all === true);
        }

        async function loadData(pushHistory = false) {
            const tableCard = document.getElementById('smallArrearsTableCard');
            tableCard.classList.remove('d-none');

            const selectedBranches = getSelectedBranches();
            const selectedUnits = getEffectiveUnitSelections();

            chip.classList.remove('d-none');
            submitButton.disabled = true;

            try {
                const params = new URLSearchParams();
                if (periodInput.value) {
                    params.set('periode', periodInput.value);
                }
                selectedBranches.forEach(value => params.append('cabang1[]', value));
                selectedUnits.forEach(value => params.append('unit1[]', value));

                const response = await fetch(`${dataUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();
                renderRows(payload);

                if (pushHistory) {
                    const nextUrl = new URL(pageUrl, window.location.origin);
                    if (periodInput.value) {
                        nextUrl.searchParams.set('periode', periodInput.value);
                    }
                    selectedBranches.forEach(value => nextUrl.searchParams.append('cabang1[]', value));
                    selectedUnits.forEach(value => nextUrl.searchParams.append('unit1[]', value));
                    window.history.replaceState({}, '', nextUrl.toString());
                }
            } catch (_) {
                renderEmptyState('Terjadi kendala saat memuat data. Coba ulangi beberapa saat lagi.', {
                    selected_period: periodInput.value,
                    selected_branches: selectedBranches,
                    selected_units: selectedUnits,
                    total: { current: 0, ytd: 0, mtd: 0, current_tunggakan: 0, ytd_tunggakan: 0, mtd_tunggakan: 0, total_tunggakan: 0 },
                });
            } finally {
                chip.classList.add('d-none');
                submitButton.disabled = false;
            }
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            await loadData(true);
        });

        branchToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleDropdown(branchMenu, branchToggle);
        });

        unitToggle.addEventListener('click', function (event) {
            if (unitToggle.disabled) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            toggleDropdown(unitMenu, unitToggle);
        });

        document.addEventListener('change', function (event) {
            if (event.target.matches('.small-arrears-branch-checkbox')) {
                const target = event.target;
                const checkedValues = getCheckedValues('.small-arrears-branch-checkbox');

                if (target.value === areaAllValue) {
                    if (target.checked) {
                        document.querySelectorAll('.small-arrears-branch-checkbox').forEach(input => {
                            input.checked = input.value === areaAllValue;
                        });
                    } else if (checkedValues.length === 0) {
                        target.checked = true;
                    }
                } else if (target.checked) {
                    const areaAllCheckbox = document.querySelector('.small-arrears-branch-checkbox[value="' + areaAllValue + '"]');
                    if (areaAllCheckbox) {
                        areaAllCheckbox.checked = false;
                    }
                }

                const normalized = getCheckedValues('.small-arrears-branch-checkbox');
                const selectedBranches = normalized.length ? normalized : [areaAllValue];
                const appliedBranches = applyBranchSelection(selectedBranches);

                loadFilters().catch(() => null);
                updateBadge(periodInput.value, appliedBranches, getSelectedUnits(), appliedBranches.includes(areaAllValue));
            }

            if (event.target.matches('.small-arrears-unit-checkbox')) {
                if (event.target.value === allUkerValue && event.target.checked) {
                    document.querySelectorAll('.small-arrears-unit-checkbox').forEach(input => {
                        input.checked = input.value === allUkerValue;
                    });
                } else if (event.target.value !== allUkerValue && event.target.checked) {
                    const allUkerCheckbox = document.querySelector('.small-arrears-unit-checkbox[value="' + allUkerValue + '"]');
                    if (allUkerCheckbox) {
                        allUkerCheckbox.checked = false;
                    }
                } else {
                    const selectedUnits = getCheckedValues('.small-arrears-unit-checkbox');
                    if (selectedUnits.length === 0) {
                        const allUkerCheckbox = document.querySelector('.small-arrears-unit-checkbox[value="' + allUkerValue + '"]');
                        if (allUkerCheckbox) {
                            allUkerCheckbox.checked = true;
                        }
                    }
                }
                updateUnitLabel();
            }
        });

        periodInput.addEventListener('change', function () {
            loadFilters().catch(() => null);
        });

        resetButton.addEventListener('click', async function () {
            periodInput.value = defaultPeriod || '';
            renderBranchOptions([areaAllValue]);
            renderUnitOptions(initialUnitOptions, []);
            setUnitSelectDisabled(true);
            await loadFilters();
            
            const tableCard = document.getElementById('smallArrearsTableCard');
            tableCard.classList.add('d-none');
            
            // Re-sync badge to empty state period
            updateBadge(periodInput.value, [areaAllValue], [], true);
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.small-arrears-filter-dropdown')) {
                closeDropdown(branchMenu, branchToggle);
                closeDropdown(unitMenu, unitToggle);
            }
        });

        renderBranchOptions(initialSelectedBranches);
        renderUnitOptions(initialUnitOptions, initialSelectedUnits);

        if (initialIsAreaAll) {
            setUnitSelectDisabled(true);
        }

        loadFilters()
            .catch(() => renderEmptyState('Filter belum siap dimuat.', {
                selected_period: periodInput.value,
                effective_branches: areaBranches,
                is_area_all: initialIsAreaAll,
                total: { current: 0, ytd: 0, mtd: 0, total_tunggakan: 0 },
            }));
    });
</script>
@endpush

@endsection
