@extends('layouts.admin')

@section('title', 'Timeseries Dashboard Harian')

@section('styles')
<style>
    :root {
        --filter-card-bg: #ffffff;
        --chart-card-bg: #ffffff;
        --accent-dark: #0f172a;
    }

    .dashboard-timeseries {
        padding-bottom: 3rem;
    }

    /* Filter Sidebar/Top Styling */
    .filter-card {
        background: var(--filter-card-bg);
        border: 1px solid rgba(8, 87, 195, 0.12);
        border-radius: 1.25rem;
        box-shadow: 0 10px 30px -15px rgba(8, 87, 195, 0.15);
        margin-bottom: 2rem;
    }

    .filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .category-selector {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .category-btn {
        padding: 0.6rem 1.2rem;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .category-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .category-btn.active {
        background: linear-gradient(135deg, #0857c3 0%, #307fe2 100%);
        color: white;
        border-color: #0857c3;
        box-shadow: 0 8px 20px -8px rgba(8, 87, 195, 0.5);
    }

    /* Chart Card Styling */
    .chart-card {
        background: var(--chart-card-bg);
        border: 1px solid rgba(8, 87, 195, 0.08);
        border-radius: 1.5rem;
        box-shadow: 0 4px 20px -10px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .chart-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -20px rgba(8, 87, 195, 0.2);
    }

    .chart-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chart-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
    }

    .chart-body {
        padding: 1.5rem;
        flex: 1;
        position: relative;
        min-height: 320px;
    }

    .summary-chart-card {
        border: 1px solid rgba(8, 87, 195, 0.2);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 1.5rem;
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #0857c3;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .unit-badge {
        font-size: 0.65rem;
        padding: 0.25rem 0.6rem;
        border-radius: 2rem;
        background: rgba(8, 87, 195, 0.1);
        color: #0857c3;
        font-weight: 700;
        text-transform: uppercase;
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4rem 2rem;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }
</style>
@endsection

@section('content')
<div class="dashboard-timeseries">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 font-weight-bold mb-1" style="color: #0f172a;">Timeseries Dashboard</h1>
            <p class="text-muted small mb-0">Tren Keragaan Harian Berdasarkan Perspektif Bulanan</p>
        </div>
        <div class="unit-badge">Satuan: Dalam Rp Miliar (Rp M)</div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body p-4">
            <div class="row align-items-end">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <label class="filter-label">Kategori Metrik</label>
                    <div class="category-selector" id="categorySelector">
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'simpanan' ? 'active' : '' }}" data-value="simpanan">Simpanan</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'pinjaman' ? 'active' : '' }}" data-value="pinjaman">Pinjaman</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'sml' ? 'active' : '' }}" data-value="sml">SML</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'npl' ? 'active' : '' }}" data-value="npl">NPL</button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label" for="kancaFilter">Kantor Cabang (Multi-select)</label>
                    <select id="kancaFilter" class="form-control select2" multiple>
                        @foreach($dashboardPage['filters']['kanca'] as $item)
                            @if($item['value'] !== 'all')
                                <option value="{{ $item['value'] }}" {{ in_array($item['value'], $dashboardPage['selected']['kanca']) ? 'selected' : '' }}>
                                    {{ $item['label'] }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label" for="unitFilter">Unit Kerja</label>
                    <select id="unitFilter" class="form-control select2">
                        @foreach($dashboardPage['filters']['unit_kerja'] as $item)
                            <option value="{{ $item['value'] }}" {{ $dashboardPage['selected']['unit_kerja'] === $item['value'] ? 'selected' : '' }}>
                                {{ $item['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <button id="applyFilters" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-sync-alt mr-2"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card chart-card summary-chart-card">
                <div class="chart-header">
                    <h5 class="chart-title"><i class="fas fa-chart-area mr-2 text-primary"></i>Total Tren Area</h5>
                    <div class="unit-badge">Total Konsolidasi Selected Branches</div>
                </div>
                <div class="chart-body">
                    <div class="loading-overlay" id="summaryLoading">
                        <div class="loading-spinner"></div>
                    </div>
                    <canvas id="summaryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Branch Charts -->
    <div class="row" id="individualChartsContainer">
        <!-- Dynamic Content -->
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const routes = @json($dashboardPage['routes']);
        let currentCategory = '{{ $dashboardPage['selected']['category'] }}';
        let charts = {};

        console.log('Timeseries Dashboard Init', { endpoint: routes.data, category: currentCategory });

        // Color palettes for months
        const monthColors = [
            { border: 'rgba(8, 87, 195, 1)', bg: 'rgba(8, 87, 195, 0.1)' },    // Blue (Current)
            { border: 'rgba(48, 127, 226, 0.8)', bg: 'rgba(48, 127, 226, 0.05)' }, // Light Blue
            { border: 'rgba(113, 197, 232, 0.8)', bg: 'rgba(113, 197, 232, 0.05)' }, // Cyan
            { border: 'rgba(148, 163, 184, 0.6)', bg: 'rgba(148, 163, 184, 0.05)' }, // Gray
        ];

        function initSelect2() {
            $('.select2').select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Pilih...',
                allowClear: false
            });
            console.log('Select2 initialized', {
                kanca: $('#kancaFilter').val(),
                unit: $('#unitFilter').val()
            });
        }

        async function fetchData() {
            const kanca = $('#kancaFilter').val() || [];
            const unit = $('#unitFilter').val() || 'all';

            $('#summaryLoading').css('display', 'flex');

            try {
                const params = $.param({
                    category: currentCategory,
                    kanca: kanca,
                    unit_kerja: unit
                });

                const url = `${routes.data}?${params}`;
                console.log('Fetch URL:', url, { category: currentCategory, kanca, unit });
                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Received data:', {
                    months: data.months,
                    seriesBranches: Object.keys(data.series || {}),
                    areaTotalMonths: Object.keys(data.area_total || {})
                });

                if (!data || !data.months) {
                    console.warn('Invalid response structure:', data);
                    renderEmptyChart();
                    return;
                }

                if (!Array.isArray(data.months) || data.months.length === 0) {
                    console.warn('Months is not array or empty:', data.months);
                    renderEmptyChart();
                    return;
                }

                renderCharts(data);
            } catch (error) {
                console.error('Failed to fetch timeseries data:', error);
                renderEmptyChart();
            } finally {
                $('#summaryLoading').hide();
            }
        }

        function renderEmptyChart() {
            // Destroy existing charts
            Object.values(charts).forEach(c => c.destroy());
            charts = {};

            const container = document.getElementById('individualChartsContainer');
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h4>Tidak ada data untuk filter terpilih</h4>
                        <p>Silakan sesuaikan filter atau pilih kantor cabang lain.</p>
                    </div>
                </div>
            `;
        }

        function createChartConfig(title, months, datasets) {
            return {
                type: 'line',
                data: {
                    labels: Array.from({length: 31}, (_, i) => i + 1),
                    datasets: datasets.map((d, i) => ({
                        label: d.label,
                        data: d.data,
                        borderColor: monthColors[i % monthColors.length].border,
                        backgroundColor: monthColors[i % monthColors.length].bg,
                        borderWidth: i === 0 ? 3 : 2,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        fill: i === 0, // Only fill the latest month
                        hidden: false
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    weight: 'bold',
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('id-ID').format(context.parsed.y) + ' Rp M';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Value (Rp Miliar)',
                                font: { weight: 'bold' }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                padding: 10,
                                font: { size: 11 },
                                callback: function(value) {
                                    return new Intl.NumberFormat('id-ID').format(value);
                                }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                padding: 10,
                                font: { size: 11 }
                            }
                        }
                    }
                }
            };
        }

        function getMonthName(monthStr) {
            const date = new Date(monthStr + '-01');
            return date.toLocaleString('id-ID', { month: 'long', year: 'numeric' });
        }

        function renderCharts(data) {
            console.log('renderCharts called with:', { months: data.months, branches: Object.keys(data.series || {}) });

            // Destroy existing charts
            Object.values(charts).forEach(c => {
                try { c.destroy(); } catch(e) {}
            });
            charts = {};

            // 1. Render Summary Chart
            try {
                const summaryCtx = document.getElementById('summaryChart');
                if (!summaryCtx) {
                    console.error('Summary chart canvas not found');
                    return;
                }

                const summaryDatasets = data.months.map(month => ({
                    label: getMonthName(month),
                    data: data.area_total[month] || new Array(31).fill(null)
                }));

                charts['summary'] = new Chart(summaryCtx.getContext('2d'), createChartConfig('Total Area', data.months, summaryDatasets));
                console.log('Summary chart rendered');
            } catch (error) {
                console.error('Error rendering summary chart:', error);
            }

            // 2. Render Individual Branch Charts
            try {
                const container = document.getElementById('individualChartsContainer');
                if (!container) {
                    console.error('Container not found');
                    return;
                }

                container.innerHTML = '';

                const branchNames = Object.keys(data.series || {}).sort();
                console.log('Branches to render:', branchNames);

                if (branchNames.length === 0) {
                    container.innerHTML = `
                        <div class="col-12 text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <h4>Tidak ada data untuk filter terpilih</h4>
                                <p>Silakan sesuaikan filter atau pilih kantor cabang lain.</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                branchNames.forEach(branch => {
                    try {
                        const col = document.createElement('div');
                        col.className = 'col-lg-6 mb-4';

                        const canvasId = `chart_${branch.replace(/\s+/g, '_')}`;
                        const card = `
                            <div class="card chart-card">
                                <div class="chart-header">
                                    <h5 class="chart-title">${branch}</h5>
                                    <span class="unit-badge">Daily Trend</span>
                                </div>
                                <div class="chart-body">
                                    <canvas id="${canvasId}"></canvas>
                                </div>
                            </div>
                        `;
                        col.innerHTML = card;
                        container.appendChild(col);

                        const canvasElem = document.getElementById(canvasId);
                        if (!canvasElem) {
                            console.error(`Canvas ${canvasId} not found after insertion`);
                            return;
                        }

                        const ctx = canvasElem.getContext('2d');
                        const datasets = data.months.map(month => ({
                            label: getMonthName(month),
                            data: (data.series[branch] && data.series[branch][month]) ? data.series[branch][month] : new Array(31).fill(null)
                        }));
                        charts[branch] = new Chart(ctx, createChartConfig(branch, data.months, datasets));
                    } catch (error) {
                        console.error(`Error rendering branch ${branch}:`, error);
                    }
                });
                console.log(`Rendered ${branchNames.length} branch charts`);
            } catch (error) {
                console.error('Error in branch chart rendering section:', error);
            }
        }

        // Event Listeners
        $('#categorySelector').on('click', '.category-btn', function() {
            $('.category-btn').removeClass('active');
            $(this).addClass('active');
            currentCategory = $(this).data('value');
            fetchData();
        });

        $('#applyFilters').on('click', fetchData);

        // Initial Load
        initSelect2();
        fetchData();
    });
</script>
@endsection
