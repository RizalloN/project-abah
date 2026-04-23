@extends('layouts.admin')

@section('title', 'Chart Periodik')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

@php
    $selectedUnitValues = collect($selected_units ?? [])
        ->map(fn (array $unit) => (string) ($unit['value'] ?? ''))
        ->filter()
        ->values()
        ->all();
@endphp

<style>
    .chart-periodik-shell {
        position: relative;
        overflow: hidden;
    }

    .chart-periodik-shell::after {
        content: '';
        position: absolute;
        inset: auto -15% -35% auto;
        width: 280px;
        height: 280px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(113, 197, 232, 0.18) 0%, rgba(113, 197, 232, 0.04) 48%, transparent 70%);
        pointer-events: none;
    }

    .chart-periodik-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .chart-periodik-kpi {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--loan-border);
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: var(--loan-shadow);
        padding: 1rem 1.05rem;
        min-height: 112px;
    }

    .chart-periodik-kpi::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--loan-blue-ink), var(--loan-blue), var(--loan-cyan));
    }

    .chart-periodik-kpi__label {
        font-size: 0.66rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6982a7;
        margin-bottom: 0.55rem;
    }

    .chart-periodik-kpi__value {
        font-size: clamp(1.1rem, 2vw, 1.65rem);
        font-weight: 900;
        color: var(--loan-blue-ink);
        line-height: 1.08;
        margin-bottom: 0.35rem;
    }

    .chart-periodik-kpi__hint {
        color: #6b7f99;
        font-size: 0.75rem;
        line-height: 1.45;
    }

    .chart-card {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: 18px;
        background: #ffffff;
        box-shadow: var(--loan-shadow);
        overflow: hidden;
        height: 100%;
    }

    .chart-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--loan-blue-ink), var(--loan-blue), var(--loan-cyan));
    }

    .chart-card__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.05rem 0.75rem;
        border-bottom: 1px solid #edf3fb;
    }

    .chart-card__title {
        margin: 0;
        color: var(--loan-blue-ink);
        font-size: 0.92rem;
        font-weight: 900;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .chart-card__desc {
        margin: 0.35rem 0 0;
        color: #6c7f99;
        font-size: 0.75rem;
        line-height: 1.45;
    }

    .chart-card__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.38rem 0.68rem;
        border-radius: 999px;
        background: var(--loan-blue-soft);
        color: var(--loan-blue-ink);
        font-size: 0.7rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .chart-card__body {
        padding: 1rem 1.05rem 1.05rem;
    }

    .chart-canvas-wrap {
        position: relative;
        min-height: 360px;
    }

    .chart-empty-state {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 360px;
        padding: 1.25rem;
        text-align: center;
        color: #6b7f99;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        border: 1px dashed #d6e2f1;
        border-radius: 16px;
    }

    .chart-empty-state strong {
        display: block;
        margin-bottom: 0.35rem;
        color: var(--loan-blue-ink);
    }

    .chart-periodik-actions {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .chart-periodik-note {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: 1.15fr 0.85fr;
    }

    .chart-note-panel {
        border: 1px solid var(--loan-border);
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: var(--loan-shadow);
        padding: 1rem 1.05rem;
    }

    .chart-note-panel h6 {
        margin: 0 0 0.35rem;
        color: var(--loan-blue-ink);
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .chart-note-panel p,
    .chart-note-panel li {
        color: #5f7490;
        font-size: 0.78rem;
        line-height: 1.6;
    }

    .chart-note-panel ul {
        margin: 0.45rem 0 0;
        padding-left: 1.05rem;
    }

    @media (max-width: 1199.98px) {
        .chart-periodik-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .chart-periodik-note {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 575.98px) {
        .chart-periodik-summary {
            grid-template-columns: 1fr;
        }

        .chart-card__header {
            flex-direction: column;
        }
    }
</style>

<div class="loan-dashboard pt-4 px-3">
    <div class="loan-title-hero d-flex flex-wrap justify-content-center align-items-center animate-reveal">
            <div class="loan-title-hero__wrap">
                <div class="loan-title-hero__badge">
                    <i class="fas fa-chart-pie"></i>
                    <span>Loan Pattern Trend</span>
                </div>
                <h1 class="loan-title-hero__title">CHART PERIODIK</h1>
                <p class="loan-title-hero__desc">
                Menampilkan tren pola pembayaran dari <strong>snapshot chart periodik</strong> yang dibentuk dari <strong>daily_loan_dinamis</strong> dan <strong>loan_type</strong> untuk melihat distribusi bulanan, musiman, dan kategori lain per kanca serta kode uker.
                </p>
            </div>
        </div>

    <div class="card loan-shell mb-4 animate-reveal chart-periodik-shell">
        <div class="card-body p-4">
            <form id="chartPeriodikForm" method="GET" action="{{ route('report.dashboard-pinjaman.chart-periodik') }}">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                    <div>
                        <h5 class="mb-1 font-weight-bold text-dark">Filter Periodik</h5>
                        <div class="text-muted" style="font-size: 0.8rem;">Default scope mengikuti Area 6. Kode uker akan menyesuaikan kanca yang dipilih.</div>
                    </div>
                    <div class="mt-3 mt-lg-0 loan-loading-chip">
                        <span class="loan-loading-dot"></span>
                        <span id="chartPeriodikStatus">Siap menampilkan data.</span>
                    </div>
                </div>

                <div class="row loan-filter-grid align-items-end">
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label" for="chartPeriodikPeriode">Periode Terakhir</label>
                            <select id="chartPeriodikPeriode" name="periode" class="form-control loan-filter-control chart-periodik-select">
                                @foreach($periods as $periode)
                                    <option value="{{ $periode }}" @selected($periode === $selected_period)>
                                        {{ \Carbon\Carbon::parse($periode)->format('d M Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="loan-filter-label" for="chartPeriodikCabang">Kanca</label>
                            <select id="chartPeriodikCabang" name="cabang1" class="form-control loan-filter-control chart-periodik-select">
                                @foreach($branch_options as $branchOption)
                                    <option value="{{ $branchOption['value'] }}" @selected($branchOption['value'] === $selected_branch)>
                                        {{ $branchOption['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-5 col-md-8">
                        <div class="form-group">
                            <label class="loan-filter-label" for="chartPeriodikUnit">Kode Uker</label>
                            <select
                                id="chartPeriodikUnit"
                                name="unit1[]"
                                class="form-control loan-filter-control chart-periodik-select"
                                multiple
                                data-placeholder="Semua Kode Uker"
                                data-selected='@json($selectedUnitValues)'
                            >
                                @forelse($unit_options as $unitOption)
                                    <option value="{{ $unitOption['value'] }}" @selected(in_array($unitOption['value'], $selectedUnitValues, true))>
                                        {{ $unitOption['label'] }}
                                    </option>
                                @empty
                                    <option value="">Pilih periode atau kanca dulu</option>
                                @endforelse
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4">
                        <div class="chart-periodik-actions">
                            <button type="submit" class="btn px-4 font-weight-bold" id="chartPeriodikRefreshButton" style="border-radius: 12px; height: 48px; background: linear-gradient(135deg, var(--loan-blue), #307fe2); color: #ffffff; border: none; box-shadow: 0 4px 12px rgba(8, 87, 195, 0.2);">
                                <i class="fas fa-sync-alt mr-2"></i> MUAT DATA
                            </button>
                            <a href="{{ route('report.dashboard-pinjaman.chart-periodik') }}" class="btn btn-light px-4 font-weight-bold" style="border-radius: 12px; height: 48px; border: 1px solid var(--loan-border); color: var(--loan-blue-ink);">
                                RESET
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="chart-periodik-summary animate-reveal mb-4">
        <div class="chart-periodik-kpi">
            <div class="chart-periodik-kpi__label">Total Rekening</div>
            <div class="chart-periodik-kpi__value" id="chartPeriodikTotalRekening">{{ number_format((int) ($chart['summary']['total_rekening'] ?? 0), 0, ',', '.') }}</div>
            <div class="chart-periodik-kpi__hint">Jumlah baris yang masuk scope aktif.</div>
        </div>
        <div class="chart-periodik-kpi">
            <div class="chart-periodik-kpi__label">Jumlah Pola</div>
            <div class="chart-periodik-kpi__value" id="chartPeriodikPatternCount">{{ number_format((int) ($chart['summary']['pattern_count'] ?? 0), 0, ',', '.') }}</div>
            <div class="chart-periodik-kpi__hint">Pola pembayaran unik dari lookup <code>loan_type</code>.</div>
        </div>
        <div class="chart-periodik-kpi">
            <div class="chart-periodik-kpi__label">Pola Dominan</div>
            <div class="chart-periodik-kpi__value" id="chartPeriodikTopPattern">{{ $chart['summary']['top_pattern'] ?? '-' }}</div>
            <div class="chart-periodik-kpi__hint">Pola dengan frekuensi tertinggi pada periode aktif.</div>
        </div>
        <div class="chart-periodik-kpi">
            <div class="chart-periodik-kpi__label">Scope Aktif</div>
            <div class="chart-periodik-kpi__value" id="chartPeriodikScopeLabel">{{ $chart['scope_label'] ?? 'Area 6 - All' }}</div>
            <div class="chart-periodik-kpi__hint" id="chartPeriodikPeriodLabel">{{ $selected_period_label ?? '-' }}</div>
        </div>
    </div>

    <div class="row animate-reveal" style="row-gap: 1rem;">
        <div class="col-lg-7">
            <div class="chart-card">
                <div class="chart-card__header">
                    <div>
                        <h5 class="chart-card__title">Trend Pola Pembayaran</h5>
                        <p class="chart-card__desc">Perbandingan distribusi pola pembayaran untuk enam periode terakhir hingga periode aktif.</p>
                    </div>
                    <div class="chart-card__badge">
                        <i class="fas fa-chart-line"></i>
                        <span id="chartPeriodikTrendBadge">{{ $selected_period_label ?? '-' }}</span>
                    </div>
                </div>
                <div class="chart-card__body">
                    <div id="chartPeriodikTrendEmpty" class="chart-empty-state d-none">
                        <div>
                            <strong>Belum ada data trend</strong>
                            <span>Pilih periode atau cabang untuk memunculkan garis trend pola pembayaran.</span>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartPeriodikTrendCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-card">
                <div class="chart-card__header">
                    <div>
                        <h5 class="chart-card__title">Komposisi Periode Aktif</h5>
                        <p class="chart-card__desc">Pie chart untuk melihat porsi pola pembayaran pada periode yang sedang dipilih.</p>
                    </div>
                    <div class="chart-card__badge">
                        <i class="fas fa-chart-pie"></i>
                        <span id="chartPeriodikPieBadge">{{ $selected_period_label ?? '-' }}</span>
                    </div>
                </div>
                <div class="chart-card__body">
                    <div id="chartPeriodikPieEmpty" class="chart-empty-state d-none">
                        <div>
                            <strong>Belum ada komposisi data</strong>
                            <span>Distribusi pola pembayaran akan tampil setelah data tersedia.</span>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartPeriodikPieCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="chart-periodik-note mt-4 animate-reveal">
        <div class="chart-note-panel">
            <h6>Catatan logika data</h6>
            <p class="mb-0">
                Data diturunkan dulu ke <strong>dashboard_pinjaman_chart_periodik_snapshots</strong> dari <strong>daily_loan_dinamis</strong> dan <strong>loan_type</strong>, lalu selector serta chart membaca hasil snapshot itu agar filter periode, kanca, dan kode uker tetap cepat dan konsisten.
            </p>
        </div>
        <div class="chart-note-panel">
            <h6>Contoh scope</h6>
            <ul>
                <li>Area 6 default menampilkan KC Madiun, KC Magetan, KC Ngawi, dan KC Ponorogo.</li>
                <li>Jika memilih KC Ponorogo, kode uker yang tampil akan mengikuti unit pada cabang tersebut.</li>
                <li>Contoh scope spesifik: KC Ponorogo - 3887 - Ngrayun.</li>
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const periodSelect = document.getElementById('chartPeriodikPeriode');
        const branchSelect = document.getElementById('chartPeriodikCabang');
        const unitSelect = document.getElementById('chartPeriodikUnit');
        const form = document.getElementById('chartPeriodikForm');
        const statusChip = document.getElementById('chartPeriodikStatus');
        const refreshButton = document.getElementById('chartPeriodikRefreshButton');
        const totalRekeningEl = document.getElementById('chartPeriodikTotalRekening');
        const patternCountEl = document.getElementById('chartPeriodikPatternCount');
        const topPatternEl = document.getElementById('chartPeriodikTopPattern');
        const scopeLabelEl = document.getElementById('chartPeriodikScopeLabel');
        const periodLabelEl = document.getElementById('chartPeriodikPeriodLabel');
        const trendBadgeEl = document.getElementById('chartPeriodikTrendBadge');
        const pieBadgeEl = document.getElementById('chartPeriodikPieBadge');
        const trendCanvas = document.getElementById('chartPeriodikTrendCanvas');
        const pieCanvas = document.getElementById('chartPeriodikPieCanvas');
        const trendEmpty = document.getElementById('chartPeriodikTrendEmpty');
        const pieEmpty = document.getElementById('chartPeriodikPieEmpty');

        const filtersUrl = @json(route('report.dashboard-pinjaman.chart-periodik.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.chart-periodik.data'));
        const initialChartPayload = @json($chart);
        const initialSelectedUnits = @json($selectedUnitValues);

        let trendChart = null;
        let pieChart = null;
        let filtersController = null;
        let dataController = null;
        let suppressUnitReload = false;

        function initSelect(select, options = {}) {
            const $select = window.jQuery(select);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            $select.select2(Object.assign({
                theme: 'bootstrap4',
                width: '100%',
            }, options));
        }

        function initSelects() {
            initSelect(periodSelect);
            initSelect(branchSelect);
            initMultiSelect(unitSelect, 'Semua Kode Uker');
        }

        function setStatus(message) {
            statusChip.textContent = message;
        }

        function setLoading(isLoading, message) {
            refreshButton.disabled = isLoading;
            periodSelect.disabled = isLoading;
            branchSelect.disabled = isLoading;
            unitSelect.disabled = isLoading;
            setStatus(message || (isLoading ? 'Mengambil data...' : 'Siap menampilkan data.'));
        }

        function selectedUnitValues() {
            return window.jQuery(unitSelect).val() || [];
        }

        function rebuildUnitOptions(options, preservedValues = []) {
            const normalizedPreserved = new Set((preservedValues || []).map((value) => String(value)));
            const validValues = [];
            const $unit = window.jQuery(unitSelect);

            if ($unit.hasClass('select2-hidden-accessible')) {
                $unit.select2('destroy');
            }

            unitSelect.innerHTML = '';

            if (!options.length) {
                unitSelect.add(new Option('Tidak ada kode uker untuk scope ini', '', false, false));
                unitSelect.disabled = true;
                initMultiSelect(unitSelect, 'Semua Kode Uker');
                suppressUnitReload = true;
                $unit.val(null).trigger('change');
                suppressUnitReload = false;
                return;
            }

            options.forEach((option) => {
                const isSelected = normalizedPreserved.has(String(option.value));
                unitSelect.add(new Option(option.label, option.value, false, isSelected));
                if (isSelected) {
                    validValues.push(String(option.value));
                }
            });

            unitSelect.disabled = false;
            initMultiSelect(unitSelect, 'Semua Kode Uker');
            suppressUnitReload = true;
            $unit.val(validValues).trigger('change');
            suppressUnitReload = false;
        }

        function updateSummary(payload) {
            totalRekeningEl.textContent = formatNumber(payload.summary?.total_rekening ?? 0);
            patternCountEl.textContent = formatNumber(payload.summary?.pattern_count ?? 0);
            topPatternEl.textContent = payload.summary?.top_pattern || '-';
            scopeLabelEl.textContent = payload.scope_label || 'Area 6 - All';
            periodLabelEl.textContent = payload.selected_period_label || '-';
            trendBadgeEl.textContent = payload.selected_period_label || '-';
            pieBadgeEl.textContent = payload.selected_period_label || '-';
        }

        function destroyCharts() {
            if (trendChart) {
                trendChart.destroy();
                trendChart = null;
            }

            if (pieChart) {
                pieChart.destroy();
                pieChart = null;
            }
        }

        function toggleEmptyState(canvas, emptyState, hasData) {
            if (hasData) {
                canvas.classList.remove('d-none');
                emptyState.classList.add('d-none');
                return;
            }

            canvas.classList.add('d-none');
            emptyState.classList.remove('d-none');
        }

        function renderCharts(payload) {
            updateSummary(payload);
            destroyCharts();

            const trendLabels = payload.trend?.labels || [];
            const trendDatasets = payload.trend?.datasets || [];
            const pieLabels = payload.pie?.labels || [];
            const pieValues = payload.pie?.values || [];
            const palette = ['#0857c3', '#ff671f', '#14b8a6', '#7c3aed', '#0f9d58', '#f59e0b', '#64748b', '#ec4899'];

            toggleEmptyState(trendCanvas, trendEmpty, trendLabels.length > 0 && trendDatasets.length > 0);
            toggleEmptyState(pieCanvas, pieEmpty, pieLabels.length > 0 && pieValues.length > 0);

            if (trendLabels.length > 0 && trendDatasets.length > 0) {
                trendChart = new Chart(trendCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: trendDatasets.map((dataset, index) => ({
                            label: dataset.label,
                            data: dataset.data,
                            borderColor: palette[index % palette.length],
                            backgroundColor: palette[index % palette.length] + '26',
                            borderWidth: 3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.28,
                            fill: false,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8,
                                    color: '#42526b',
                                    font: {
                                        family: 'inherit',
                                        weight: '600',
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return `${context.dataset.label}: ${formatNumber(context.raw)}`;
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: 'rgba(136, 156, 180, 0.12)',
                                },
                                ticks: {
                                    color: '#6c7f99',
                                    maxRotation: 0,
                                    font: {
                                        family: 'inherit',
                                    },
                                },
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(136, 156, 180, 0.12)',
                                },
                                ticks: {
                                    color: '#6c7f99',
                                    callback: function (value) {
                                        return formatNumber(value);
                                    },
                                    font: {
                                        family: 'inherit',
                                    },
                                },
                            },
                        },
                    },
                });
            }

            if (pieLabels.length > 0 && pieValues.length > 0) {
                pieChart = new Chart(pieCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: pieLabels,
                        datasets: [{
                            data: pieValues,
                            backgroundColor: pieLabels.map((_, index) => palette[index % palette.length]),
                            borderColor: '#ffffff',
                            borderWidth: 2,
                            hoverOffset: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    color: '#42526b',
                                    font: {
                                        family: 'inherit',
                                        weight: '600',
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const total = context.dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                                        const value = Number(context.raw || 0);
                                        const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                        return `${context.label}: ${formatNumber(value)} (${pct}%)`;
                                    },
                                },
                            },
                        },
                    },
                });
            }
        }

        function updateUrl() {
            const params = new URLSearchParams();
            if (periodSelect.value) {
                params.set('periode', periodSelect.value);
            }
            if (branchSelect.value) {
                params.set('cabang1', branchSelect.value);
            }
            selectedUnitValues().forEach((value) => params.append('unit1[]', value));

            const query = params.toString();
            const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
            window.history.replaceState({}, '', nextUrl);
        }

        async function reloadFilters() {
            if (!periodSelect.value) {
                return;
            }

            if (filtersController) {
                filtersController.abort();
            }

            filtersController = new AbortController();
            const query = new URLSearchParams({
                periode: periodSelect.value,
                cabang1: branchSelect.value,
            });

            setLoading(true, 'Menyusun opsi unit...');

            try {
                const response = await fetch(`${filtersUrl}?${query.toString()}`, {
                    signal: filtersController.signal,
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Filter request failed: ${response.status}`);
                }

                const payload = await response.json();
                const preserved = selectedUnitValues();
                rebuildUnitOptions(payload.unit_options || [], preserved);
                updateUrl();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                    setStatus('Gagal memuat opsi unit.');
                }
                throw error;
            } finally {
                setLoading(false, 'Siap menampilkan data.');
            }
        }

        async function reloadData() {
            if (!periodSelect.value) {
                destroyCharts();
                return;
            }

            if (dataController) {
                dataController.abort();
            }

            dataController = new AbortController();
            const query = new URLSearchParams();
            query.set('periode', periodSelect.value);
            query.set('cabang1', branchSelect.value);
            selectedUnitValues().forEach((value) => query.append('unit1[]', value));

            setLoading(true, 'Mengambil trend pola pembayaran...');

            try {
                const response = await fetch(`${dataUrl}?${query.toString()}`, {
                    signal: dataController.signal,
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Data request failed: ${response.status}`);
                }

                const payload = await response.json();

                if (payload.selected_period) {
                    periodSelect.value = payload.selected_period;
                }

                updateUrl();
                renderCharts(payload);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                    setStatus('Gagal memuat chart.');
                }
            } finally {
                setLoading(false, 'Siap menampilkan data.');
            }
        }

        async function reloadAll() {
            try {
                await reloadFilters();
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
            }

            await reloadData();
        }

        initSelects();
        rebuildUnitOptions(@json($unit_options ?? []), initialSelectedUnits);
        renderCharts(initialChartPayload);
        updateUrl();

        periodSelect.addEventListener('change', reloadAll);
        branchSelect.addEventListener('change', reloadAll);
        unitSelect.addEventListener('change', function () {
            if (suppressUnitReload) {
                return;
            }

            reloadData();
        });
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                reloadAll();
            });
        }

        if (!periodSelect.value && periodSelect.options.length > 0) {
            periodSelect.value = periodSelect.options[0].value;
            reloadAll();
        } else if (periodSelect.value) {
            setStatus('Mengambil snapshot awal...');
        } else {
            setStatus('Periode tidak tersedia.');
        }
    });
</script>
@endpush
@endsection
