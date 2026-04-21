@extends('layouts.admin')

@section('title', 'Matrix Pergeseran Kolek')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<div class="loan-dashboard pt-4 px-3">
    <div id="loanMatrixPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <form id="loanFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman.matrix') }}">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 pb-3 border-bottom">
                        <div>
                            <h2 class="mb-1 font-weight-bold text-dark" style="font-size: 1.75rem; letter-spacing: -0.02em;">Matrix Pergeseran Kolek</h2>
                            <p class="text-muted font-weight-bold mb-0" style="font-size: 0.85rem;">Analisis pergerakan kualitas pinjaman antar periode.</p>
                        </div>
                        <div class="mt-3 mt-lg-0 text-lg-right">
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

                    <div class="d-flex flex-wrap align-items-center loan-filter-actions mt-3" style="gap: 0.75rem; padding-top: 1.5rem; border-top: 1px dashed #e2e8f0;">
                        <button id="loanSubmitButton" type="submit" class="btn btn-dark px-4 font-weight-bold" style="border-radius: 12px; height: 44px; letter-spacing: 0.05em; background: #0f172a;">
                            <i class="fas fa-search mr-2"></i>
                            TAMPILKAN
                        </button>
                        <a href="{{ route('report.dashboard-pinjaman.matrix') }}" class="btn btn-outline-secondary px-4 font-weight-bold" style="border-radius: 12px; height: 44px;">RESET</a>
                        <div id="loanLoadingChip" class="loan-loading-chip d-none ml-2">
                            <span class="loan-loading-dot"></span>
                            MENGOLAH DATA
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell animate-reveal">
            <div class="card-body p-4">
                <div class="loan-table-heading mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-th-large mr-2 text-muted"></i> Data Matriks Pergeseran <span id="loanMatrixPeriodBadge" class="text-primary ml-1" style="font-size: 0.9rem;">{{ \Carbon\Carbon::parse($selectedPeriod)->format('d/m/Y') }} vs {{ \Carbon\Carbon::parse($comparisonPeriod)->format('d/m/Y') }}</span></h5>
                        <div class="text-right">
                             <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.7rem;">UNIT: IDR JUTA</span>
                        </div>
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

        function buildRowHtml(row) {
            const rowRank = qualityRanks[row.label];
            const isNewAccount = row.label === 'New Account';
            let html = `<tr><th>${row.label}</th>`;
            
            for (let idx = 0; idx < row.values.length; idx++) {
                const val = row.values[idx];
                const cls = isNewAccount ? 'matrix-new-account' : (rowRank === idx ? 'matrix-stagnant' : (rowRank > idx ? 'matrix-up' : 'matrix-down'));
                html += `<td class="${val ? cls : 'matrix-empty'}">${formatNumber(val)}</td>`;
            }
            
            html += `<td class="matrix-total-col">${formatNumber(row.total)}</td>`;
            
            for (let i = 0; i < outputColumns.length; i++) {
                const col = outputColumns[i];
                html += `<td>${formatNumber(row.metrics[col])}</td>`;
            }
            
            html += '</tr>';
            return html;
        }

        function renderRows(rows) {
            const chunkSize = Math.max(12, Math.ceil(rows.length / 8));
            
            if (rows.length <= 15) {
                // Small dataset: render directly
                body.innerHTML = rows.map(buildRowHtml).join('');
                updateLoadingProgress(95, 'Memformat Data', 'Hampir selesai...');
            } else {
                // Large dataset: progressive rendering with DocumentFragment
                body.innerHTML = '';
                let index = 0;
                
                function renderChunk() {
                    const fragment = document.createDocumentFragment();
                    const endIndex = Math.min(index + chunkSize, rows.length);
                    
                    for (let i = index; i < endIndex; i++) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = buildRowHtml(rows[i]).slice(4, -5); // Extract inner HTML
                        fragment.appendChild(tr);
                    }
                    
                    body.appendChild(fragment);
                    
                    const progress = Math.round((endIndex / rows.length) * 90) + 5;
                    updateLoadingProgress(progress, 'Memformat Data', `${endIndex} dari ${rows.length} baris...`);
                    
                    index = endIndex;
                    if (index < rows.length) {
                        requestAnimationFrame(renderChunk);
                    }
                }
                
                renderChunk();
            }
        }

        function renderFoot(totals, totalVal) {
            let html = '<tr><th>Grand Total</th>';
            
            for (let i = 0; i < qualityColumns.length; i++) {
                html += `<td>${formatNumber(totals.matrix[i])}</td>`;
            }
            
            html += `<td class="matrix-total-col">${formatNumber(totalVal)}</td>`;
            
            for (let i = 0; i < outputColumns.length; i++) {
                const col = outputColumns[i];
                html += `<td>${formatNumber(totals.metrics[col])}</td>`;
            }
            
            html += '</tr>';
            foot.innerHTML = html;
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
