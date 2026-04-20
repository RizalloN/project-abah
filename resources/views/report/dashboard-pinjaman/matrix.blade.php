@extends('layouts.admin')

@section('title', 'Matrix Pergeseran Kolek')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<div class="loan-dashboard pt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="loan-page-title">Matrix Pergeseran Kolek</h1>
            <p class="text-muted mb-0">Analisis pergerakan kualitas pinjaman antar periode.</p>
        </div>
    </div>

    <div id="loanMatrixPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <form id="loanFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman.matrix') }}">
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
                                <input id="loanPeriodeInput" type="date" name="periode" class="form-control loan-filter-control" value="{{ $requestedPeriod ?: $selectedPeriod }}" max="{{ $periods->first() }}">
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

                    <div class="d-flex flex-wrap align-items-center loan-filter-actions" style="gap: 0.75rem;">
                        <button id="loanSubmitButton" type="submit" class="btn btn-primary">
                            <i class="fas fa-filter mr-1"></i>
                            Tampilkan
                        </button>
                        <a href="{{ route('report.dashboard-pinjaman.matrix') }}" class="btn btn-light">Reset</a>
                        <div id="loanLoadingChip" class="loan-loading-chip d-none">
                            <span class="loan-loading-dot"></span>
                            Sedang Mengolah
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell animate-reveal">
            <div class="card-body p-4">
                <div class="loan-table-heading">
                    <div>
                        <h5>Matriks Pergerakan Kualitas Pinjaman</h5>
                        <p>Membandingkan kualitas <span id="loanMatrixPeriodBadge" class="badge badge-info">{{ \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') }} vs {{ \Carbon\Carbon::parse($comparisonPeriod)->format('d/m/Y') }}</span></p>
                    </div>
                </div>

                <div class="loan-table-stage">
                    <div id="loanLoadingOverlay" class="loan-loading-overlay is-hidden">
                        <div class="loan-loading-dot"></div>
                        <span id="loanLoadingPhase" class="loan-loading-title">Menyiapkan Data</span>
                        <p id="loanLoadingCopy" class="loan-loading-copy">Mohon tunggu sebentar...</p>
                        <div class="loan-loading-progress">
                            <div class="loan-loading-progress-meta">
                                <span>Progress</span>
                                <span id="loanLoadingPercent">0%</span>
                            </div>
                            <div class="loan-loading-progress-track">
                                <div id="loanLoadingProgressBar" class="loan-loading-progress-bar"></div>
                            </div>
                        </div>
                    </div>

                    <div class="loan-matrix-wrap">
                        <table class="loan-matrix">
                            <thead>
                                <tr>
                                    <th class="matrix-before">Kualitas M-1</th>
                                    <th colspan="{{ count($matrixColumns) }}" class="matrix-after-group">Kualitas Current (MtD)</th>
                                    <th rowspan="2" class="matrix-total-head py-3">Total Movement<br><span id="loanTotalValueHeader">per Baris</span></th>
                                    <th colspan="4" class="matrix-subhead">Data Output (IDR)</th>
                                </tr>
                                <tr>
                                    <th class="matrix-before">Bucket</th>
                                    @foreach($matrixColumns as $col)
                                        <th class="matrix-subhead">{{ $col }}</th>
                                    @endforeach
                                    <th>Turunan Pokok</th>
                                    <th>Suplesi</th>
                                    <th>PH</th>
                                    <th>Lunas</th>
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
                            <tfoot id="loanMatrixFoot"></tfoot>
                        </table>
                    </div>
                </div>

                <div class="loan-legend">
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-stagnant"></span> Stagnan</div>
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-up"></span> Membaik (Upgrade)</div>
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-down"></span> Memburuk (Downgrade)</div>
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-new-account"></span> New Account</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanFilterForm');
        const periodInput = document.getElementById('loanPeriodeInput');
        const segmenSelect = document.getElementById('loanSegmenSelect');
        const produkSelect = document.getElementById('loanProdukSelect');
        const cabangSelect = document.getElementById('loanCabangSelect');
        const unitSelect = document.getElementById('loanUnitSelect');
        const body = document.getElementById('loanMatrixBody');
        const foot = document.getElementById('loanMatrixFoot');
        const overlay = document.getElementById('loanLoadingOverlay');
        const loadingCopy = document.getElementById('loanLoadingCopy');
        const loadingPhase = document.getElementById('loanLoadingPhase');
        const loadingPercent = document.getElementById('loanLoadingPercent');
        const loadingProgressBar = document.getElementById('loanLoadingProgressBar');
        const chip = document.getElementById('loanLoadingChip');
        const submitButton = document.getElementById('loanSubmitButton');
        const periodBadge = document.getElementById('loanMatrixPeriodBadge');
        const activePeriodMeta = document.getElementById('loanActivePeriodMeta');
        const comparisonPeriodMeta = document.getElementById('loanComparisonPeriodMeta');
        const totalValueHeader = document.getElementById('loanTotalValueHeader');

        const filtersUrl = @json(route('report.dashboard-pinjaman.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.data'));
        const qualityColumns = @json($matrixColumns);
        const outputColumns = ['principal_reduction', 'suplesi', 'ph', 'lunas'];
        const qualityRanks = @json(array_flip($matrixColumns));

        let activeFilterController = null;
        let activeController = null;
        let activeMatrixRequestId = 0;
        let activeFilterRequestId = 0;
        let isNavigatingAway = false;
        let filterReloadTimer = null;
        let isRefreshingFilters = false;

        const filterSelects = [
            { element: segmenSelect, placeholder: 'Semua Segmen' },
            { element: produkSelect, placeholder: 'Semua Produk' },
            { element: cabangSelect, placeholder: 'Semua Kantor Cabang' },
            { element: unitSelect, placeholder: 'Semua Unit Kerja' }
        ];

        function abortInFlightRequests() {
            if (activeController) activeController.abort();
            if (activeFilterController) activeFilterController.abort();
            window.clearTimeout(filterReloadTimer);
        }

        function releaseLoadingUi() {
            overlay.classList.add('is-hidden');
            chip.classList.add('d-none');
            submitButton.disabled = false;
        }

        function startLoadingProgress() {
            updateLoadingProgress(8, 'Mengambil Data', 'Menghubungi server...');
            overlay.classList.remove('is-hidden');
            chip.classList.remove('d-none');
            submitButton.disabled = true;
        }

        function updateLoadingProgress(value, phase, copy) {
            const progress = Math.max(0, Math.min(100, Math.round(value)));
            if (loadingPhase) loadingPhase.textContent = phase;
            if (loadingCopy) loadingCopy.textContent = copy;
            if (loadingPercent) loadingPercent.textContent = `${progress}%`;
            if (loadingProgressBar) loadingProgressBar.style.width = `${progress}%`;
        }

        async function loadFilterOptions() {
            if (activeFilterController) activeFilterController.abort();
            const requestId = ++activeFilterRequestId;
            if (!periodInput.value) {
                activePeriodMeta.textContent = '-';
                comparisonPeriodMeta.textContent = '-';
                return;
            }

            activeFilterController = new AbortController();
            const params = new URLSearchParams();
            params.set('periode', periodInput.value);
            ['segmen_dashboard', 'produk_dashboard', 'cabang1', 'unit1'].forEach(key => {
                const select = document.getElementById('loan' + key.charAt(0).toUpperCase() + key.slice(1).replace('1', '').replace('_dashboard', '') + 'Select');
                (window.jQuery(select).val() || []).forEach(v => params.append(key + '[]', v));
            });

            try {
                const response = await fetch(`${filtersUrl}?${params.toString()}`, { signal: activeFilterController.signal });
                const payload = await response.json();
                if (requestId !== activeFilterRequestId) return;

                activePeriodMeta.textContent = formatDate(payload.selected_period);
                comparisonPeriodMeta.textContent = formatDate(payload.comparison_period);
                
                isRefreshingFilters = true;
                setSelectOptions(segmenSelect, payload.segments || [], 'Semua Segmen');
                setSelectOptions(produkSelect, payload.products || [], 'Semua Produk');
                setSelectOptions(cabangSelect, payload.branches || [], 'Semua Kantor Cabang');
                setSelectOptions(unitSelect, payload.units || [], 'Semua Unit Kerja');
                isRefreshingFilters = false;
            } catch (e) {}
        }

        function setSelectOptions(select, items, placeholder) {
            const selected = parseSelectedDataset(select);
            select.innerHTML = '';
            items.forEach(item => {
                const opt = new Option(item, item, false, selected.includes(String(item)));
                select.add(opt);
            });
            window.jQuery(select).trigger('change');
        }

        async function loadMatrix(pushHistory = false) {
            if (activeController) activeController.abort();
            activeController = new AbortController();
            const requestId = ++activeMatrixRequestId;
            const params = new URLSearchParams(new FormData(form));
            params.set('_ts', Date.now());

            startLoadingProgress();
            try {
                const response = await fetch(`${dataUrl}?${params.toString()}`, { signal: activeController.signal });
                const payload = await response.json();
                if (requestId !== activeMatrixRequestId) return;

                renderRows(payload.matrix_rows);
                renderFoot(payload.grand_totals, payload.grand_total_value);
                periodBadge.textContent = `${formatDate(payload.selected_period)} vs ${formatDate(payload.comparison_period)}`;
                
                if (pushHistory) window.history.replaceState({}, '', `?${params.toString()}`);
                updateLoadingProgress(100, 'Selesai', 'Data dimuat.');
                setTimeout(releaseLoadingUi, 300);
            } catch (e) {
                if (e.name !== 'AbortError') releaseLoadingUi();
            }
        }

        function renderRows(rows) {
            body.innerHTML = rows.map(row => {
                const cells = row.values.map((val, idx) => {
                    const rowRank = qualityRanks[row.label];
                    const cls = row.label === 'New Account' ? 'matrix-new-account' : (rowRank === idx ? 'matrix-stagnant' : (rowRank > idx ? 'matrix-up' : 'matrix-down'));
                    return `<td class="${val ? cls : 'matrix-empty'}">${formatNumber(val)}</td>`;
                }).join('');
                return `<tr><th>${row.label}</th>${cells}<td class="matrix-total-col">${formatNumber(row.total)}</td>${outputColumns.map(c => `<td>${formatNumber(row.metrics[c])}</td>`).join('')}</tr>`;
            }).join('');
        }

        function renderFoot(totals, totalVal) {
            foot.innerHTML = `<tr><th>Grand Total</th>${qualityColumns.map((_, i) => `<td>${formatNumber(totals.matrix[i])}</td>`).join('')}<td class="matrix-total-col">${formatNumber(totalVal)}</td>${outputColumns.map(c => `<td>${formatNumber(totals.metrics[c])}</td>`).join('')}</tr>`;
        }

        form.addEventListener('submit', e => { e.preventDefault(); loadMatrix(true); });
        periodInput.addEventListener('change', () => { loadFilterOptions(); });
        
        filterSelects.forEach(({element}) => {
            initMultiSelect(element, element.dataset.placeholder);
            window.jQuery(element).on('change', () => {
                syncSelectedDataset(element);
                if (!isRefreshingFilters) loadFilterOptions();
            });
        });

        if (periodInput.value) loadFilterOptions();
    });
</script>
@endpush

@endsection
