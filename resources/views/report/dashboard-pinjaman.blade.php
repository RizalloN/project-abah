@extends('layouts.admin')

@section('title', 'Dashboard Pinjaman')

@section('content')
<style>
    .loan-dashboard {
        padding-bottom: 1.5rem;
    }

    .loan-shell,
    .loan-table-shell {
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22);
    }

    .loan-shell .card-body,
    .loan-table-shell .card-body {
        background: #ffffff;
    }

    .loan-page-title {
        font-size: clamp(1.7rem, 2.7vw, 2.5rem);
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.45rem;
    }

    .loan-filter-grid .form-group {
        margin-bottom: 1rem;
    }

    .loan-filter-label {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.45rem;
    }

    .loan-filter-control,
    .loan-filter-control.select2-selection {
        border-radius: 14px !important;
        min-height: 44px !important;
        height: 44px !important;
        border-color: #cfdae6 !important;
        background: #ffffff !important;
        font-size: 0.94rem;
        display: flex;
        align-items: center;
    }

    .loan-filter-control:disabled {
        background: #edf2f7 !important;
        color: #64748b !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control {
        min-height: 44px !important;
        height: 44px !important;
        padding: 0 2rem 0 0.75rem !important;
        display: flex !important;
        align-items: center !important;
        overflow: hidden !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-selection__choice {
        display: none !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-selection__rendered {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 44px !important;
        color: #475569 !important;
        font-size: 0.94rem !important;
        transform: translateY(-1px);
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-search--inline {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-search__field {
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
        opacity: 0 !important;
        cursor: pointer !important;
    }

    .select2-container--bootstrap4 .select2-selection--multiple.loan-filter-control .select2-selection__clear {
        position: absolute !important;
        right: 0.75rem !important;
        top: 50% !important;
        margin: 0 !important;
        transform: translateY(-50%) !important;
        line-height: 1 !important;
    }

    .select2-container--bootstrap4 .select2-selection--single.loan-filter-control {
        height: 44px !important;
        padding: 0 2rem 0 0.75rem !important;
        display: flex !important;
        align-items: center !important;
    }

    .loan-filter-summary-empty {
        color: #64748b !important;
    }

    .loan-select2-option {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .loan-select2-option input {
        pointer-events: none;
    }

    .loan-filter-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        color: #64748b;
        font-size: 0.84rem;
        margin-top: 0.25rem;
    }

    .loan-table-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .loan-table-heading h5 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .loan-table-heading p {
        margin: 0.25rem 0 0;
        color: #64748b;
        font-size: 0.88rem;
    }

    .loan-table-unit {
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .loan-loading-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        border-radius: 999px;
        padding: 0.55rem 0.9rem;
        background: linear-gradient(135deg, #eff6ff, #ecfeff);
        color: #0f766e;
        font-size: 0.8rem;
        font-weight: 800;
    }

    .loan-loading-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #14b8a6;
        box-shadow: 0 0 0 rgba(20, 184, 166, 0.45);
        animation: loanPulse 1.6s infinite;
    }

    @keyframes loanPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.45); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(20, 184, 166, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0); }
    }

    .loan-table-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 999px;
        padding: 0.45rem 0.7rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.79rem;
        font-weight: 700;
    }

    .loan-table-stage {
        position: relative;
        min-height: 520px;
    }

    .loan-loading-overlay {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        justify-content: center;
        align-items: center;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.96));
        backdrop-filter: blur(4px);
        transition: opacity 0.28s ease, visibility 0.28s ease;
    }

    .loan-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .loan-loading-title {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }

    .loan-loading-copy {
        max-width: 480px;
        text-align: center;
        color: #64748b;
        font-size: 0.9rem;
        margin: 0;
    }

    .loan-skeleton-grid {
        width: min(780px, 100%);
        display: grid;
        grid-template-columns: 220px repeat(9, 1fr);
        gap: 0.75rem;
    }

    .loan-skeleton-cell {
        height: 16px;
        border-radius: 999px;
        background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
        background-size: 220% 100%;
        animation: loanShimmer 1.3s infinite linear;
    }

    .loan-skeleton-cell.is-wide {
        height: 18px;
    }

    @keyframes loanShimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .loan-matrix-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .loan-matrix {
        width: 100%;
        min-width: 1580px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .loan-matrix th,
    .loan-matrix td {
        padding: 12px 10px;
        border-right: 1px solid rgba(255, 255, 255, 0.3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        text-align: right;
        vertical-align: middle;
    }

    .loan-matrix thead th {
        color: #ffffff;
        font-size: 0.86rem;
        font-weight: 800;
        text-align: center;
        white-space: nowrap;
    }

    .loan-matrix thead th.matrix-wrap-head {
        white-space: normal;
        min-width: 170px;
        line-height: 1.35;
    }

    .loan-matrix .matrix-wrap-head .matrix-head-copy {
        display: inline-block;
    }

    .loan-matrix .matrix-before {
        background: #f59e0b;
        text-align: left;
        min-width: 180px;
        position: sticky;
        left: 0;
        z-index: 3;
    }

    .loan-matrix .matrix-after-group,
    .loan-matrix .matrix-subhead {
        background: #1d4ed8;
    }

    .loan-matrix .matrix-total-head {
        background: #0f766e;
    }

    .loan-matrix tbody th {
        background: #fb923c;
        color: #ffffff;
        text-align: left;
        font-size: 0.9rem;
        font-weight: 800;
        position: sticky;
        left: 0;
        z-index: 2;
    }

    .loan-matrix tbody td {
        background: #e8f1fb;
        color: #334155;
        font-weight: 700;
        white-space: nowrap;
    }

    .loan-matrix tbody td.matrix-empty {
        color: #94a3b8;
        background: #f8fafc;
    }

    .loan-matrix tbody td.matrix-stagnant {
        background: #ffffff;
        color: #334155;
    }

    .loan-matrix tbody td.matrix-up {
        background: #22c55e;
        color: #ffffff;
    }

    .loan-matrix tbody td.matrix-down {
        background: #ef4444;
        color: #ffffff;
    }

    .loan-matrix tbody td.matrix-new-account {
        background: #d1d5db;
        color: #334155;
    }

    .loan-matrix .matrix-total-col {
        background: #ccfbf1 !important;
        color: #115e59 !important;
        font-weight: 800;
    }

    .loan-matrix tfoot th,
    .loan-matrix tfoot td {
        background: #0f172a;
        color: #ffffff;
        font-weight: 800;
    }

    .loan-matrix tfoot .matrix-total-col {
        background: #0f766e !important;
        color: #ffffff !important;
    }

    .loan-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.85rem;
        margin-top: 1rem;
    }

    .loan-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #475569;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .loan-legend-swatch {
        width: 14px;
        height: 14px;
        border-radius: 4px;
    }

    .loan-empty-state {
        padding: 3rem 1rem;
        text-align: center;
        color: #64748b;
    }

    .loan-empty-state strong {
        display: block;
        margin-bottom: 0.4rem;
        color: #0f172a;
    }
</style>

<div class="loan-dashboard">
    <div class="mb-4">
        <h2 class="loan-page-title">Dashboard Pinjaman</h2>
    </div>

    <div class="card loan-shell mb-4">
        <div class="card-body p-4">
            <form id="loanFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman') }}">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                    <div>
                        <h5 class="mb-1 font-weight-bold text-dark">Filter Dashboard</h5>
                        <div class="loan-filter-meta">
                            <span>Periode aktif: <strong id="loanActivePeriodMeta">{{ $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') : '-' }}</strong></span>
                            <span>Pembanding M-1: <strong id="loanComparisonPeriodMeta">{{ $comparisonPeriod ? \Carbon\Carbon::parse($comparisonPeriod)->format('d/m/Y') : '-' }}</strong></span>
                        </div>
                    </div>
                </div>

                <div class="row loan-filter-grid">
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label">Periode</label>
                            <input
                                id="loanPeriodeInput"
                                type="date"
                                name="periode"
                                class="form-control loan-filter-control"
                                value="{{ $requestedPeriod ?: $selectedPeriod }}"
                                max="{{ $periods->first() }}"
                            >
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label">Segmen</label>
                            <select id="loanSegmenSelect" name="segmen_dashboard[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Segmen" data-selected='@json($filters["segmen"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label">Produk</label>
                            <select id="loanProdukSelect" name="produk_dashboard[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Produk" data-selected='@json($filters["produk"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label">Regional Office</label>
                            <input type="text" class="form-control loan-filter-control" value="Area 6" disabled>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label">Kantor Cabang</label>
                            <select id="loanCabangSelect" name="cabang1[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Kantor Cabang" data-selected='@json($filters["cabang"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label">Unit Kerja</label>
                            <select id="loanUnitSelect" name="unit1[]" class="form-control select2 loan-filter-control loan-filter-multiselect" multiple data-placeholder="Semua Unit Kerja" data-selected='@json($filters["unit"] ?? [])'>
                                <option value="">Pilih periode dulu</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center" style="gap: 0.75rem;">
                    <button id="loanSubmitButton" type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-1"></i>
                        Tampilkan
                    </button>
                    <a href="{{ route('report.dashboard-pinjaman') }}" class="btn btn-light">
                        Reset
                    </a>
                    <div id="loanLoadingChip" class="loan-loading-chip d-none">
                        <span class="loan-loading-dot"></span>
                        Sedang Mengolah
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card loan-table-shell">
        <div class="card-body p-4">
            <div class="loan-table-heading">
                <div>
                    <h5>Matriks Pergerakan Kualitas Pinjaman</h5>
                    <div class="loan-table-unit">Satuan: Rp Juta</div>
                </div>
                <div class="loan-table-badge">
                    <i class="fas fa-table"></i>
                    <span id="loanPeriodBadge">
                        {{ $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') : '-' }} vs {{ $comparisonPeriod ? \Carbon\Carbon::parse($comparisonPeriod)->format('d/m/Y') : '-' }}
                    </span>
                </div>
            </div>

            <div class="loan-table-stage">
                <div id="loanLoadingOverlay" class="loan-loading-overlay">
                    <div class="loan-loading-title">Sedang Mengolah</div>
                    <p class="loan-loading-copy">Loading...</p>
                    <div class="loan-skeleton-grid" aria-hidden="true">
                        @for ($row = 0; $row < 7; $row++)
                            <div class="loan-skeleton-cell is-wide"></div>
                            @for ($col = 0; $col < 6; $col++)
                                <div class="loan-skeleton-cell"></div>
                            @endfor
                        @endfor
                    </div>
                </div>

                <div class="loan-matrix-wrap">
                    <table class="loan-matrix">
                        <thead>
                            <tr>
                                <th rowspan="2" class="matrix-before">Kualitas Before</th>
                                <th colspan="{{ count($matrixColumns) }}" class="matrix-after-group">Kualitas After</th>
                                <th rowspan="2" class="matrix-total-head matrix-wrap-head">
                                    <span id="loanTotalValueHeader" class="matrix-head-copy">
                                        Total Nilai<br>({{ $selectedPeriod ? \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') : 'Periode Terakhir' }})
                                    </span>
                                </th>
                                <th rowspan="2" class="matrix-subhead">Turunan Pokok</th>
                                <th rowspan="2" class="matrix-subhead">Suplesi</th>
                                <th rowspan="2" class="matrix-subhead">PH</th>
                                <th rowspan="2" class="matrix-subhead">Lunas</th>
                            </tr>
                            <tr>
                                @foreach ($matrixColumns as $column)
                                    <th class="matrix-subhead">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody id="loanMatrixBody">
                            <tr>
                                <td colspan="{{ count($matrixColumns) + 6 }}" class="loan-empty-state">
                                    <strong>Filter belum dijalankan</strong>
                                    Pilih periode atau filter lain lalu klik <strong>Tampilkan</strong>.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr id="loanMatrixFoot">
                                <th>Grand Total</th>
                                @foreach ($matrixColumns as $column)
                                    <td>-</td>
                                @endforeach
                                <td class="matrix-total-col">-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="loan-legend">
                <span class="loan-legend-item">
                    <span class="loan-legend-swatch" style="background:#22c55e;"></span>
                    Naik
                </span>
                <span class="loan-legend-item">
                    <span class="loan-legend-swatch" style="background:#ef4444;"></span>
                    Turun
                </span>
                <span class="loan-legend-item">
                    <span class="loan-legend-swatch" style="background:#d1d5db;"></span>
                    New Account
                </span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanFilterForm');
        const body = document.getElementById('loanMatrixBody');
        const foot = document.getElementById('loanMatrixFoot');
        const overlay = document.getElementById('loanLoadingOverlay');
        const chip = document.getElementById('loanLoadingChip');
        const periodBadge = document.getElementById('loanPeriodBadge');
        const submitButton = document.getElementById('loanSubmitButton');
        const periodInput = document.getElementById('loanPeriodeInput');
        const activePeriodMeta = document.getElementById('loanActivePeriodMeta');
        const comparisonPeriodMeta = document.getElementById('loanComparisonPeriodMeta');
        const totalValueHeader = document.getElementById('loanTotalValueHeader');
        const segmenSelect = document.getElementById('loanSegmenSelect');
        const produkSelect = document.getElementById('loanProdukSelect');
        const cabangSelect = document.getElementById('loanCabangSelect');
        const unitSelect = document.getElementById('loanUnitSelect');
        const dataUrl = @json(route('report.dashboard-pinjaman.data'));
        const filtersUrl = @json(route('report.dashboard-pinjaman.filters'));
        const qualityColumns = @json($matrixColumns);
        const outputColumns = ['principal_reduction', 'suplesi', 'ph', 'lunas'];
        const qualityRanks = qualityColumns.reduce((accumulator, column, index) => {
            accumulator[column] = index;
            return accumulator;
        }, {});
        let activeController = null;
        let activeFilterController = null;
        let isRefreshingFilters = false;
        let filterReloadTimer = null;

        const filterSelects = [
            { element: segmenSelect, placeholder: 'Semua Segmen' },
            { element: produkSelect, placeholder: 'Semua Produk' },
            { element: cabangSelect, placeholder: 'Semua Kantor Cabang' },
            { element: unitSelect, placeholder: 'Semua Unit Kerja' },
        ];

        filterSelects.forEach(({ element }) => {
            element.dataset.state = periodInput.value ? 'idle' : 'disabled';
        });

        function parseSelectedDataset(select) {
            try {
                const parsed = JSON.parse(select.dataset.selected || '[]');
                return Array.isArray(parsed) ? parsed.map(String) : [];
            } catch (error) {
                return [];
            }
        }

        function syncSelectedDataset(select) {
            select.dataset.selected = JSON.stringify(window.jQuery(select).val() || []);
        }

        function buildOptionTemplate(option) {
            if (!option.id) {
                return option.text;
            }

            const isChecked = option.element ? option.element.selected : false;
            const wrapper = document.createElement('span');
            wrapper.className = 'loan-select2-option';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = isChecked;

            const label = document.createElement('span');
            label.textContent = option.text;

            wrapper.appendChild(checkbox);
            wrapper.appendChild(label);

            return wrapper;
        }

        function initMultiSelect(select, placeholder) {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
                return;
            }

            const $select = window.jQuery(select);
            if ($select.data('select2')) {
                $select.select2('destroy');
            }

            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder,
                closeOnSelect: false,
                allowClear: true,
                language: {
                    noResults: function () {
                        const state = select.dataset.state || 'ready';
                        if (state === 'loading') {
                            return 'Memuat opsi...';
                        }

                        if (state === 'empty') {
                            return 'Tidak ada opsi';
                        }

                        return 'Tidak ada opsi';
                    },
                },
                templateResult: buildOptionTemplate,
                templateSelection: function (data) {
                    return data.text;
                },
                escapeMarkup: function (markup) {
                    return markup;
                },
            });
        }

        function formatNumber(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            const number = Number(value) / 1000000;

            if (Number.isNaN(number)) {
                return '-';
            }

            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(number);
        }

        function formatDate(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(value + 'T00:00:00');
            return new Intl.DateTimeFormat('id-ID').format(date);
        }

        function formatHeaderDate(value) {
            if (!value) {
                return 'Periode Terakhir';
            }

            const date = new Date(value + 'T00:00:00');
            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(date);
        }

        function updateTotalValueHeader(period) {
            if (!totalValueHeader) {
                return;
            }

            totalValueHeader.innerHTML = `Total Nilai<br>(${formatHeaderDate(period)})`;
        }

        function setSelectOptions(select, items, placeholder, selectedValues = []) {
            select.innerHTML = '';
            select.dataset.state = items.length ? 'ready' : (periodInput.value ? 'empty' : 'disabled');
            const normalizedSelectedValues = Array.isArray(selectedValues)
                ? selectedValues.map(String)
                : [];

            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item;
                option.textContent = item;
                option.selected = normalizedSelectedValues.includes(String(item));
                select.appendChild(option);
            });

            select.disabled = !periodInput.value;
            select.dataset.selected = JSON.stringify(
                normalizedSelectedValues.filter((value) => items.map(String).includes(value))
            );
            refreshSelectUi(select);
        }

        function setFilterLoadingState(isLoading) {
            filterSelects.forEach(({ element, placeholder }) => {
                element.disabled = isLoading || !periodInput.value;
                element.dataset.state = isLoading ? 'loading' : (periodInput.value ? 'ready' : 'disabled');

                if (isLoading) {
                    element.innerHTML = '';
                } else if (!periodInput.value) {
                    element.innerHTML = '';
                } else if (!element.options.length) {
                    element.innerHTML = '';
                }

                refreshSelectUi(element);
            });
        }

        function scheduleFilterReload() {
            window.clearTimeout(filterReloadTimer);
            filterReloadTimer = window.setTimeout(function () {
                loadFilterOptions();
            }, 250);
        }

        function refreshSelectUi(select) {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                const placeholder = select.dataset.placeholder || '';
                initMultiSelect(select, placeholder);
                const selectedValues = parseSelectedDataset(select);
                window.jQuery(select).val(selectedValues).trigger('change.select2');
                updateSelectSummary(select);
            }
        }

        function updateSelectSummary(select) {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
                return;
            }

            const $select = window.jQuery(select);
            const select2 = $select.data('select2');

            if (!select2 || !select2.$container) {
                return;
            }

            const selectedItems = ($select.select2('data') || [])
                .filter((item) => item && item.id)
                .map((item) => String(item.text || '').trim())
                .filter(Boolean);

            const summary = selectedItems.length === 0
                ? (select.dataset.placeholder || '')
                : selectedItems.length <= 2
                    ? selectedItems.join(', ')
                    : `${selectedItems.slice(0, 2).join(', ')}, ...`;

            const rendered = select2.$container.find('.select2-selection__rendered');
            rendered
                .text(summary)
                .attr('title', selectedItems.length ? selectedItems.join(', ') : (select.dataset.placeholder || ''))
                .toggleClass('loan-filter-summary-empty', selectedItems.length === 0);
        }

        function appendParams(params, key, values) {
            values.forEach((value) => {
                if (value) {
                    params.append(`${key}[]`, value);
                }
            });
        }

        function collectSelectedValues(select) {
            return (window.jQuery(select).val() || []).filter(Boolean);
        }

        function getCellClass(rowLabel, columnIndex, value) {
            if (value === null || value === undefined || value === '') {
                return 'matrix-empty';
            }

            if (rowLabel === 'New Account') {
                return 'matrix-new-account';
            }

            const rowRank = qualityRanks[rowLabel];
            if (rowRank === undefined) {
                return '';
            }

            if (rowRank === columnIndex) {
                return 'matrix-stagnant';
            }

            return rowRank > columnIndex ? 'matrix-up' : 'matrix-down';
        }

        function renderRows(rows) {
            if (!rows || rows.length === 0) {
                body.innerHTML = `
                    <tr>
                        <td colspan="${qualityColumns.length + 6}" class="loan-empty-state">
                            <strong>Data tidak ditemukan</strong>
                            Coba ubah periode atau filter agar hasil pivot tersedia.
                        </td>
                    </tr>
                `;
                return;
            }

            body.innerHTML = rows.map((row) => {
                const cells = row.values.map((value, index) => {
                    const extraClass = getCellClass(row.label, index, value);
                    return `<td class="${extraClass}">${formatNumber(value)}</td>`;
                }).join('');
                const metricCells = outputColumns.map((key) => {
                    return `<td>${formatNumber(row.metrics?.[key] ?? null)}</td>`;
                }).join('');

                return `
                    <tr>
                        <th>${row.label}</th>
                        ${cells}
                        <td class="matrix-total-col">${formatNumber(row.total)}</td>
                        ${metricCells}
                    </tr>
                `;
            }).join('');
        }

        function renderFoot(grandTotals, grandTotalValue) {
            const totalCells = qualityColumns.map((column, index) => {
                return `<td>${formatNumber(grandTotals?.matrix?.[index] ?? null)}</td>`;
            }).join('');
            const metricTotals = outputColumns.map((key) => {
                return `<td>${formatNumber(grandTotals?.metrics?.[key] ?? null)}</td>`;
            }).join('');

            foot.innerHTML = `
                <th>Grand Total</th>
                ${totalCells}
                <td class="matrix-total-col">${formatNumber(grandTotalValue)}</td>
                ${metricTotals}
            `;
        }

        async function loadFilterOptions() {
            if (activeFilterController) {
                activeFilterController.abort();
            }

            if (!periodInput.value) {
                setFilterLoadingState(false);
                activePeriodMeta.textContent = '-';
                comparisonPeriodMeta.textContent = '-';
                return;
            }

            activeFilterController = new AbortController();
            const timeoutId = window.setTimeout(function () {
                activeFilterController?.abort('timeout');
            }, 15000);
            setFilterLoadingState(true);

            const params = new URLSearchParams();
            params.set('periode', periodInput.value);
            appendParams(params, 'segmen_dashboard', collectSelectedValues(segmenSelect));
            appendParams(params, 'produk_dashboard', collectSelectedValues(produkSelect));
            appendParams(params, 'cabang1', collectSelectedValues(cabangSelect));
            appendParams(params, 'unit1', collectSelectedValues(unitSelect));

            try {
                const response = await fetch(`${filtersUrl}?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: activeFilterController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat opsi filter.');
                }

                const payload = await response.json();
                activePeriodMeta.textContent = formatDate(payload.selected_period);
                comparisonPeriodMeta.textContent = formatDate(payload.comparison_period);
                updateTotalValueHeader(payload.selected_period);

                isRefreshingFilters = true;
                setSelectOptions(segmenSelect, payload.segments || [], 'Semua Segmen', parseSelectedDataset(segmenSelect));
                setSelectOptions(produkSelect, payload.products || [], 'Semua Produk', parseSelectedDataset(produkSelect));
                setSelectOptions(cabangSelect, payload.branches || [], 'Semua Kantor Cabang', parseSelectedDataset(cabangSelect));
                setSelectOptions(unitSelect, payload.units || [], 'Semua Unit Kerja', parseSelectedDataset(unitSelect));
                isRefreshingFilters = false;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setFilterLoadingState(false);
                    activePeriodMeta.textContent = '-';
                    comparisonPeriodMeta.textContent = '-';
                    filterSelects.forEach(({ element }) => {
                        element.dataset.state = 'empty';
                        refreshSelectUi(element);
                    });
                }
            } finally {
                window.clearTimeout(timeoutId);
                isRefreshingFilters = false;

                if (activeFilterController?.signal.aborted) {
                    return;
                }

                filterSelects.forEach(({ element }) => {
                    element.disabled = !periodInput.value;
                });
            }
        }

        async function loadMatrix(pushHistory = false) {
            if (activeController) {
                activeController.abort();
            }

            activeController = new AbortController();

            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }

            overlay.classList.remove('is-hidden');
            chip.classList.remove('d-none');
            submitButton.disabled = true;

            try {
                const response = await fetch(`${dataUrl}?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: activeController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat data dashboard.');
                }

                const payload = await response.json();
                renderRows(payload.matrix_rows);
                renderFoot(payload.grand_totals, payload.grand_total_value);
                periodBadge.textContent = `${formatDate(payload.selected_period)} vs ${formatDate(payload.comparison_period)}`;
                updateTotalValueHeader(payload.selected_period);

                if (pushHistory) {
                    const pageUrl = new URL(@json(route('report.dashboard-pinjaman')), window.location.origin);
                    params.forEach((value, key) => pageUrl.searchParams.append(key, value));
                    window.history.replaceState({}, '', pageUrl.toString());
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    body.innerHTML = `
                        <tr>
                            <td colspan="${qualityColumns.length + 6}" class="loan-empty-state">
                                <strong>Gagal memuat dashboard</strong>
                                Silakan coba lagi.
                            </td>
                        </tr>
                    `;
                    renderFoot([], null);
                    periodBadge.textContent = '- vs -';
                }
            } finally {
                overlay.classList.add('is-hidden');
                chip.classList.add('d-none');
                submitButton.disabled = false;
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            loadMatrix(true);
        });

        periodInput.addEventListener('change', function () {
            [segmenSelect, produkSelect, cabangSelect, unitSelect].forEach((element) => {
                element.dataset.selected = '[]';
            });

            loadFilterOptions();
        });

        [segmenSelect, produkSelect, cabangSelect, unitSelect].forEach((element) => {
            refreshSelectUi(element);

            window.jQuery(element).on('change', function () {
                syncSelectedDataset(element);
                updateSelectSummary(element);

                if (!isRefreshingFilters && periodInput.value) {
                    scheduleFilterReload();
                }
            });
        });

        overlay.classList.add('is-hidden');
        setFilterLoadingState(!periodInput.value);
        updateTotalValueHeader(periodInput.value || @json($selectedPeriod));

        if (periodInput.value) {
            loadFilterOptions();
        }

    });
</script>
@endsection
