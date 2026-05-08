@extends('layouts.admin')

@section('title', 'Kolek Tidak Sesuai')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .loan-filter-modern {
        display: grid;
        grid-template-columns: repeat(2, 1fr) auto;
        gap: 1.5rem;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(25px);
        padding: 1.5rem;
        border-radius: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 
            0 10px 15px -3px rgba(0, 0, 0, 0.05),
            0 30px 60px -20px rgba(8, 87, 195, 0.2);
        margin-bottom: 2.5rem;
        position: relative;
        z-index: 1000;
        align-items: flex-end;
    }

    .loan-shell, .loan-shell .card-body {
        overflow: visible !important;
    }

    .loan-filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        position: relative;
    }

    .loan-filter-item:nth-child(1) { z-index: 20; }
    .loan-filter-item:nth-child(2) { z-index: 10; }

    .loan-filter-modern .loan-filter-label {
        font-size: 0.75rem;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-left: 0.65rem;
    }

    .loan-dropdown {
        position: relative;
        width: 100%;
    }

    .loan-dropdown-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: var(--loan-blue);
        font-size: 1.1rem;
        pointer-events: none;
        opacity: 0.8;
    }

    .loan-dropdown-toggle {
        width: 100%;
        height: 60px;
        background: #ffffff;
        border: 2px solid #e2e8f0;
        border-radius: 18px;
        padding: 0 1.5rem 0 3.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        font-size: 0.95rem;
        color: #1e293b;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .loan-dropdown-toggle:hover {
        border-color: var(--loan-blue);
        box-shadow: 0 10px 25px rgba(8, 87, 195, 0.12);
        transform: translateY(-2px);
    }

    .loan-dropdown.is-open { z-index: 3100 !important; }

    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 12px);
        left: 0;
        width: 100%;
        min-width: 320px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(25px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 1.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2);
        z-index: 3000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(15px);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 480px;
        overflow-y: auto;
        padding: 0.75rem;
    }

    .loan-dropdown.is-open .loan-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .loan-dropdown-option {
        width: 100%;
        padding: 0.85rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        font-weight: 700;
        font-size: 0.9rem;
        color: #475569;
        transition: all 0.2s;
        text-align: left;
        margin-bottom: 4px;
    }

    .loan-dropdown-option:hover { background: #f1f5f9; color: var(--loan-blue); }
    .loan-dropdown-option.is-active { background: rgba(8, 87, 195, 0.08); color: var(--loan-blue); }

    .loan-dropdown-check {
        width: 1.4rem;
        height: 1.4rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s;
        font-size: 0.8rem;
        color: white;
    }

    .loan-dropdown-option.is-active .loan-dropdown-check {
        background: var(--loan-blue);
        border-color: var(--loan-blue);
    }

    .btn-loan-modern-submit {
        height: 60px;
        min-width: 220px;
        padding: 0 2rem;
        border-radius: 18px;
        background: linear-gradient(135deg, var(--loan-blue) 0%, #1e40af 100%);
        color: white;
        border: none;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 20px rgba(8, 87, 195, 0.3);
    }

    .btn-loan-modern-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(8, 87, 195, 0.4); }

    .select2-container--bootstrap4, .loan-filter-control { display: none !important; }

    .loan-dashboard .loan-title-hero {
        justify-content: flex-start !important;
        margin-bottom: 1rem;
        padding: 1rem 0;
        border-radius: 0;
        background: transparent;
        color: #0f172a;
        box-shadow: none;
        border-bottom: 1px solid #e5e7eb;
    }

    .loan-dashboard .loan-title-hero__wrap {
        width: 100%;
        text-align: left;
    }

    .loan-dashboard .loan-title-hero__badge {
        margin-bottom: 0.35rem;
        padding: 0;
        background: transparent;
        color: #64748b;
        letter-spacing: 0;
        text-transform: none;
    }

    .loan-dashboard .loan-title-hero__badge i {
        color: #0f766e;
    }

    .loan-dashboard .loan-title-hero__title {
        color: #0f172a;
        font-size: 1.45rem;
        letter-spacing: 0;
        text-transform: none;
    }

    .loan-dashboard .loan-title-hero__desc {
        display: none;
    }

    .loan-filter-modern {
        grid-template-columns: minmax(180px, 240px) minmax(240px, 1fr) auto;
        gap: 1rem;
        background: #ffffff;
        backdrop-filter: none;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        box-shadow: none;
        margin-bottom: 0;
    }

    .loan-dropdown-toggle,
    .btn-loan-modern-submit {
        height: 46px;
        border-radius: 8px;
        transform: none !important;
    }

    .btn-loan-modern-submit {
        min-width: 150px;
        background: #0f766e;
        box-shadow: none;
        letter-spacing: 0;
        text-transform: none;
        font-weight: 700;
    }

    .btn-loan-modern-submit:hover {
        background: #115e59;
        box-shadow: none;
    }

    .loan-mismatch-summary {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin: 1rem 0;
    }

    .loan-mismatch-card {
        border-radius: 8px;
        padding: 1rem;
        background: #ffffff;
        box-shadow: none;
        transition: none;
    }

    .loan-mismatch-card:hover {
        transform: none;
        box-shadow: none;
    }

    .loan-mismatch-card::before {
        display: none;
    }

    .loan-audit-label {
        letter-spacing: 0;
        text-transform: none;
    }

    .loan-audit-value {
        font-size: 1.35rem;
    }

    .loan-mismatch-table-wrap {
        border-radius: 8px;
        overflow: auto;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .loan-mismatch-table {
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
        min-width: 1280px;
        color: #1f2937;
        font-size: 0.78rem;
    }

    .loan-mismatch-table th,
    .loan-mismatch-table td {
        border-right: 1px solid #dbe3ef !important;
        border-bottom: 1px solid #dbe3ef !important;
        line-height: 1.25;
        padding: 0.42rem 0.5rem !important;
        vertical-align: middle !important;
        white-space: normal;
    }

    .loan-mismatch-table th:last-child,
    .loan-mismatch-table td:last-child {
        border-right: none !important;
    }

    .loan-mismatch-table thead th {
        background: var(--loan-blue-deep) !important;
        color: #ffffff !important;
        letter-spacing: 0;
        text-transform: none;
        border-right: 1px solid rgba(255, 255, 255, 0.18) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.18) !important;
        font-size: 0.72rem;
        font-weight: 800;
        padding: 0.46rem 0.5rem !important;
        white-space: nowrap;
    }

    .loan-mismatch-table thead tr:first-child th {
        background: var(--loan-blue-ink) !important;
        color: #ffffff !important;
    }

    .loan-mismatch-table thead tr:nth-child(2) th {
        background: var(--loan-blue) !important;
        color: #ffffff !important;
    }

    .loan-mismatch-table tbody td {
        background: #ffffff;
        font-weight: 600;
        height: 34px;
    }

    .loan-mismatch-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .loan-mismatch-table tbody tr:hover td {
        background: #f1f7ff;
    }

    .loan-mismatch-table tbody td:first-child {
        color: #64748b;
        text-align: center;
        font-weight: 700;
    }

    .loan-mismatch-table tbody td:nth-child(2) {
        color: #0f172a;
        font-weight: 800;
        word-break: break-word;
    }

    .loan-mismatch-table tbody td:nth-child(3),
    .loan-mismatch-table tbody td:nth-child(5),
    .loan-mismatch-table tbody td:nth-child(7),
    .loan-mismatch-table tbody td:nth-child(9) {
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    .loan-mismatch-table tbody td:nth-child(4),
    .loan-mismatch-table tbody td:nth-child(6),
    .loan-mismatch-table tbody td:nth-child(8),
    .loan-mismatch-table tbody td:nth-child(10) {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .loan-mismatch-table tbody td:nth-child(11) {
        text-align: center;
    }

    .loan-mismatch-table tfoot th,
    .loan-mismatch-table tfoot td {
        background: var(--loan-blue-ink) !important;
        color: #ffffff !important;
        font-weight: 800;
        border-right: 1px solid rgba(255, 255, 255, 0.18) !important;
        border-bottom: none !important;
    }

    .loan-mismatch-table tfoot td {
        text-align: center;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .loan-mismatch-table tfoot td.text-right {
        text-align: right !important;
    }

    .loan-mismatch-table .btn {
        min-height: 28px;
        padding: 0.22rem 0.5rem;
        border-radius: 6px;
        font-size: 0.72rem;
        line-height: 1.2;
    }

    @media (max-width: 991.98px) {
        .loan-filter-modern,
        .loan-mismatch-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="loan-dashboard pt-4">
    <div class="loan-title-hero d-flex flex-wrap justify-content-center align-items-center">
        <div class="loan-title-hero__wrap">
            <div class="loan-title-hero__badge">
                <i class="fas fa-clipboard-check"></i>
                <span>Dashboard Pinjaman</span>
            </div>
            <h1 class="loan-title-hero__title">Kolek Tidak Sesuai</h1>
        </div>
    </div>

    <div id="loanMismatchPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <form id="loanMismatchForm" method="GET" action="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                        <div>
                            <h5 class="mb-1 font-weight-bold text-dark">Filter</h5>
                        </div>
                    </div>

                    <div class="loan-filter-modern animate-reveal">
                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Periode Audit</label>
                            <div class="loan-dropdown" data-loan-dropdown="periode">
                                <i class="fas fa-calendar-check loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" onclick="document.getElementById('loanMismatchPeriodeInput').showPicker()">
                                    <span class="loan-dropdown-text" id="loanMismatchPeriodeDisplay">{{ $mismatchRequestedPeriod ?: $mismatchSelectedPeriod }}</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <input id="loanMismatchPeriodeInput" type="date" name="mismatch_periode" 
                                    style="opacity: 0; position: absolute; width: 100%; height: 100%; top: 0; left: 0; pointer-events: none;" 
                                    value="{{ $mismatchRequestedPeriod ?: $mismatchSelectedPeriod }}" max="{{ $periods->first() }}">
                            </div>
                        </div>

                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Kantor Cabang</label>
                            <div class="loan-dropdown" data-loan-dropdown="cabang">
                                <i class="fas fa-university loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="cabang">
                                    <span class="loan-dropdown-text">Pilih Kantor Cabang</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <div class="loan-dropdown-menu" data-loan-dropdown-menu="cabang">
                                    <div class="px-3 py-3 text-center text-muted small">Pilih periode dulu</div>
                                </div>
                                <select id="loanMismatchCabangSelect" name="mismatch_cabang1" class="d-none" data-selected="{{ ($mismatchSelectedBranches ?? ['AREA_6_ALL'])[0] ?? 'AREA_6_ALL' }}"></select>
                            </div>
                        </div>

                        <div>
                            <button id="loanMismatchSubmitButton" type="submit" class="btn-loan-modern-submit w-100">
                                <i class="fas fa-search"></i> Proses
                            </button>
                            <div id="loanMismatchLoadingChip" class="loan-loading-chip d-none mt-2 justify-content-center">
                                <span class="loan-loading-dot"></span> Memuat
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell loan-mismatch-table-shell animate-reveal">
            <div class="card-body p-4">
                <div class="loan-table-heading">
                    <div><h5>Kolek Tidak Sesuai</h5></div>
                    <div class="loan-table-badge">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span id="loanMismatchPeriodBadge">
                            {{ $mismatchSelectedPeriod ? \Carbon\Carbon::parse($mismatchSelectedPeriod)->format('d/m/Y') : '-' }} | Area 6 - All
                        </span>
                    </div>
                </div>

                <div class="loan-mismatch-summary">
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Total Rekening KTS</div>
                        <div id="loanMismatchTotal" class="loan-audit-value text-danger">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Unit Terdampak</div>
                        <div id="loanMismatchUnits" class="loan-audit-value">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Total OS</div>
                        <div id="loanMismatchOutstanding" class="loan-audit-value">0</div>
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <div class="loan-mismatch-table-wrap">
                        <table class="table table-hover loan-mismatch-table mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width: 54px; text-align: center;">No</th>
                                    <th rowspan="2" style="width: 230px; text-align: left;">Unit Kerja</th>
                                    <th colspan="2" style="text-align: center;">Memburuk</th>
                                    <th colspan="2" style="text-align: center;">Membaik</th>
                                    <th colspan="2" style="text-align: center;">Belum Waktunya Penyesuaian</th>
                                    <th colspan="2" style="text-align: center;">Total</th>
                                    <th rowspan="2" style="width: 105px; text-align: center;">Export</th>
                                </tr>
                                <tr>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                </tr>
                            </thead>
                            <tbody id="loanMismatchBody">
                                <tr>
                                    <td colspan="11" class="loan-empty-state">
                                        <strong>Audit belum dijalankan</strong>
                                        Pilih periode dan cabang lalu klik <strong>Proses</strong>.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr id="loanMismatchFoot">
                                    <th colspan="2">Grand Total</th>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>-</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanMismatchForm');
        const periodInput = document.getElementById('loanMismatchPeriodeInput');
        const branchSelect = document.getElementById('loanMismatchCabangSelect');
        const body = document.getElementById('loanMismatchBody');
        const foot = document.getElementById('loanMismatchFoot');
        const chip = document.getElementById('loanMismatchLoadingChip');
        const submitButton = document.getElementById('loanMismatchSubmitButton');
        const badge = document.getElementById('loanMismatchPeriodBadge');
        
        const filterUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.data'));
        const exportUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.export'));
        const areaAllValue = 'AREA_6_ALL';
        const areaBranches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
        const initialSelectedBranch = @json(($mismatchSelectedBranches ?? ['AREA_6_ALL'])[0] ?? 'AREA_6_ALL');

        initMultiSelect(branchSelect, 'Pilih Kantor Cabang');

        function getSelectedBranch() {
            return normalizeBranchSelection([branchSelect.value])[0];
        }

        function normalizeBranchSelection(values) {
            const selected = (values || []).filter(Boolean);
            if (selected.length === 0 || selected.includes(areaAllValue)) {
                return [areaAllValue];
            }

            const selectedBranch = selected.find(value => areaBranches.includes(value));
            return selectedBranch ? [selectedBranch] : [areaAllValue];
        }

        function getBranchLabel(value = getSelectedBranch()) {
            if (value === areaAllValue) {
                return 'Area 6 - All';
            }

            return value || 'Area 6 - All';
        }

        function syncHiddenBranchSelect(options, selectedValue) {
            const selected = normalizeBranchSelection([selectedValue])[0];
            branchSelect.innerHTML = '';
            (options || []).forEach(branch => {
                const label = branch === areaAllValue ? 'Area 6 - All' : branch;
                branchSelect.add(new Option(label, branch, false, selected === branch));
            });
        }

        async function loadBranches() {
            if (!periodInput.value) return;
            const res = await fetch(`${filterUrl}?periode=${periodInput.value}`);
            const payload = await res.json();
            syncHiddenBranchSelect(payload.branches || [], branchSelect.dataset.selected || initialSelectedBranch);
            branchSelect.disabled = false;
            window.jQuery(branchSelect).trigger('change');
        }

        async function loadData() {
            const selectedBranch = getSelectedBranch();

            if (!periodInput.value || !selectedBranch) return;
            chip.classList.remove('d-none');
            submitButton.disabled = true;
            
            try {
                const params = new URLSearchParams();
                params.set('periode', periodInput.value);
                params.set('cabang1', selectedBranch);

                const res = await fetch(`${dataUrl}?${params.toString()}`);
                const payload = await res.json();
                renderTable(payload.summary_rows, payload.selected_period, payload.selected_branch);
                document.getElementById('loanMismatchTotal').textContent = formatNumber(payload.audit.mismatch_rows);
                document.getElementById('loanMismatchUnits').textContent = formatNumber(payload.audit.units_with_mismatch);
                document.getElementById('loanMismatchOutstanding').textContent = formatCurrency(payload.audit.total_outstanding_balance);
            } finally {
                chip.classList.add('d-none');
                submitButton.disabled = false;
            }
        }

        function formatCurrency(value) {
            const amount = Number(value || 0);
            return formatNumber(amount);
        }

        function sumRows(rows, key) {
            return rows.reduce((total, row) => total + Number(row[key] || 0), 0);
        }

        function renderFoot(rows = []) {
            const tableRows = Array.isArray(rows) ? rows : [];
            foot.innerHTML = `
                <th colspan="2">Grand Total</th>
                <td>${formatNumber(sumRows(tableRows, 'memburuk_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'memburuk_os'))}</td>
                <td>${formatNumber(sumRows(tableRows, 'kolek_membaik_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'kolek_membaik_os'))}</td>
                <td>${formatNumber(sumRows(tableRows, 'belum_waktunya_penyesuaian_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'belum_waktunya_penyesuaian_os'))}</td>
                <td>${formatNumber(sumRows(tableRows, 'mismatch_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'outstanding_balance'))}</td>
                <td>-</td>
            `;
        }

        function buildExportUrl(period, branch, row) {
            const params = new URLSearchParams();
            params.set('periode', period);
            params.set('cabang1', row.branch || branch);

            if (!row.is_branch_summary && row.unit) {
                params.set('unit1', row.unit);
            }

            return `${exportUrl}?${params.toString()}`;
        }

        function renderTable(rows, period, branch) {
            const tableRows = Array.isArray(rows) ? rows : [];

            if (!tableRows.length) {
                body.innerHTML = `
                    <tr>
                        <td colspan="11" class="loan-empty-state">
                            <strong>Tidak ada mismatch</strong>
                            Data pada periode dan cabang ini tidak memiliki kolek tidak sesuai.
                        </td>
                    </tr>
                `;
                renderFoot([]);
                badge.textContent = `${formatDate(period)} | ${branch}`;
                return;
            }

            body.innerHTML = tableRows.map((row, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td>${row.label || row.unit}</td>
                    <td>${formatNumber(row.memburuk_count)}</td>
                    <td class="text-right">${formatCurrency(row.memburuk_os)}</td>
                    <td>${formatNumber(row.kolek_membaik_count)}</td>
                    <td class="text-right">${formatCurrency(row.kolek_membaik_os)}</td>
                    <td>${formatNumber(row.belum_waktunya_penyesuaian_count)}</td>
                    <td class="text-right">${formatCurrency(row.belum_waktunya_penyesuaian_os)}</td>
                    <td class="text-danger font-weight-bold">${formatNumber(row.mismatch_count)}</td>
                    <td class="text-right">${formatCurrency(row.outstanding_balance)}</td>
                    <td><a href="${buildExportUrl(period, branch, row)}" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel mr-1"></i> Excel</a></td>
                </tr>
            `).join('');
            renderFoot(tableRows);
            badge.textContent = `${formatDate(period)} | ${branch}`;
        }

        form.addEventListener('submit', e => { e.preventDefault(); loadData(); });
        // --- Modern Selector Sync ---
        function initModernSelectors() {
            const cabangMenu = document.querySelector('[data-loan-dropdown-menu="cabang"]');
            const cabangToggle = document.querySelector('[data-loan-dropdown-toggle="cabang"]');
            const cabangText = cabangToggle.querySelector('.loan-dropdown-text');
            const periodeDisplay = document.getElementById('loanMismatchPeriodeDisplay');

            // Periode Sync
            periodInput.addEventListener('change', () => {
                periodeDisplay.textContent = periodInput.value;
                branchSelect.dataset.selected = areaAllValue;
                loadBranches().catch(() => null);
            });

            // Dropdown Toggle
            cabangToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                cabangToggle.closest('.loan-dropdown').classList.toggle('is-open');
            });

            document.addEventListener('click', () => {
                document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
            });

            // Mutation Observer to watch branch select options
            const observer = new MutationObserver(() => {
                const options = Array.from(branchSelect.options);
                cabangMenu.innerHTML = '';
                
                if (options.length === 0) {
                    cabangMenu.innerHTML = '<div class="px-3 py-3 text-center text-muted small">Pilih periode dulu</div>';
                    return;
                }

                options.forEach(opt => {
                    const item = document.createElement('div');
                    item.className = `loan-dropdown-option ${opt.selected ? 'is-active' : ''}`;
                    item.innerHTML = `<div class="loan-dropdown-check"><i class="fas fa-check"></i></div><span>${opt.text}</span>`;
                    item.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (opt.value === areaAllValue) {
                            options.forEach(option => option.selected = option.value === areaAllValue);
                        } else {
                            options.forEach(option => option.selected = option === opt);
                        }

                        branchSelect.value = opt.value;
                        branchSelect.dataset.selected = getSelectedBranch();
                        cabangText.textContent = getBranchLabel();
                        options.forEach((option, index) => {
                            if (cabangMenu.children[index]) {
                                cabangMenu.children[index].classList.toggle('is-active', option.selected);
                            }
                        });
                        window.jQuery(branchSelect).trigger('change');
                    });
                    cabangMenu.appendChild(item);
                });

                cabangText.textContent = getBranchLabel();
            });

            observer.observe(branchSelect, { childList: true });
        }

        initModernSelectors();

        if (periodInput.value) loadBranches();
    });
</script>
@endpush

@endsection
