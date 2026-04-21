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
        padding-bottom: 2rem;
        min-width: 0;
    }

    .dashboard-timeseries h1 {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }

    .dashboard-timeseries .text-muted {
        font-size: 0.85rem;
    }

    /* Filter Sidebar/Top Styling */
    .filter-card {
        background: var(--filter-card-bg);
        border: 1px solid rgba(8, 87, 195, 0.12);
        border-radius: 1.25rem;
        box-shadow: 0 10px 30px -15px rgba(8, 87, 195, 0.15);
        margin-bottom: 1rem;
        overflow: visible !important;
        position: relative;
        z-index: 100;
    }

    .filter-card .card-body {
        padding: 0.75rem 1rem !important;
        overflow: visible !important;
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
        border-radius: 1rem;
        box-shadow: 0 4px 20px -10px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    .chart-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -20px rgba(8, 87, 195, 0.2);
    }

    .chart-header {
        padding: 0.9rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .chart-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .chart-body {
        padding: 1.25rem 1.75rem 2.1rem 1.9rem;
        flex: 0 0 auto;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
    }

    .chart-canvas-frame {
        position: relative;
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
        overflow: hidden;
    }

    /* Fixed heights to prevent vertical stretching */
    .summary-chart-body {
        height: 330px !important;
        max-height: none !important;
        padding: 0.35rem 1.25rem 1rem 1.35rem;
        overflow: hidden;
    }

    .branch-chart-body {
        height: 280px !important;
        max-height: 280px !important;
    }

    .branch-chart-body.tall {
        height: 400px !important;
        max-height: 400px !important;
    }

    .chart-canvas-frame canvas {
        width: 100% !important;
        height: 100% !important;
        display: block !important;
    }

    .summary-chart-card {
        border: 1px solid rgba(8, 87, 195, 0.2);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        min-height: 390px;
    }

    .summary-chart-card:hover {
        transform: none;
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

    /* Custom Premium Dropdown (adapted from Performance EDC) */
    .branch-filter-dropdown {
        position: relative;
        width: 100%;
    }

    .branch-dropdown-toggle {
        width: 100%;
        min-height: 42px;
        padding: 0.6rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .branch-dropdown-toggle:hover {
        border-color: #0857c3;
        background: #fff;
    }

    .period-month-select {
        width: 100%;
        min-height: 42px;
        padding: 0.6rem 2.4rem 0.6rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        color: #1e293b;
        font-size: 0.88rem;
        font-weight: 600;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        cursor: pointer;
    }

    .period-month-select:focus {
        outline: none;
        border-color: #0857c3;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.12);
        background: #ffffff;
    }

    .period-select-shell {
        position: relative;
    }

    .period-select-shell i {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: #64748b;
        font-size: 0.75rem;
    }

    .branch-dropdown-label {
        font-size: 0.88rem;
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 1.25rem;
    }

    .branch-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 15px 35px -10px rgba(8, 87, 195, 0.2);
        z-index: 9999 !important;
        display: none;
        overflow: hidden;
        animation: slideDown 0.2s ease-out;
    }

    .branch-dropdown-menu.show {
        display: block !important;
    }

    .options-container {
        max-height: 280px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .options-container::-webkit-scrollbar {
        width: 5px;
    }

    .options-container::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }

    .branch-option {
        display: flex;
        align-items: center;
        padding: 0.6rem 0.75rem;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: background 0.2s;
        gap: 0.75rem;
        user-select: none;
    }

    .branch-option:hover {
        background: #f1f5f9;
    }

    .branch-option input {
        display: none;
    }

    .branch-checkbox-ui {
        width: 18px;
        height: 18px;
        border: 2px solid #cbd5e1;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .branch-option.selected .branch-checkbox-ui {
        background: #0857c3;
        border-color: #0857c3;
    }

    .branch-checkbox-ui i {
        color: white;
        font-size: 10px;
        display: none;
    }

    .branch-option.selected .branch-checkbox-ui i {
        display: block;
    }

    .branch-option-label {
        font-size: 0.88rem;
        font-weight: 500;
        color: #334155;
    }

    .branch-option.selected .branch-option-label {
        font-weight: 700;
        color: #0857c3;
    }

    .select-all-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #0857c3;
        text-transform: uppercase;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .select-all-btn:hover {
        background: #eff6ff;
        border-radius: 0.5rem;
    }

    @media (max-width: 767.98px) {
        .dashboard-timeseries > .d-flex {
            align-items: flex-start !important;
            flex-direction: column;
            gap: 0.75rem;
        }

        .category-selector {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .category-btn {
            width: 100%;
            padding-inline: 0.75rem;
        }

        .chart-body {
            overflow-x: auto;
            overflow-y: hidden;
            padding: 1rem;
        }

        .summary-chart-body {
            height: 340px !important;
            max-height: none !important;
            padding: 0.5rem 0.9rem 1.1rem 1rem;
        }

        .branch-chart-body {
            height: 270px !important;
            max-height: 270px !important;
        }

        .summary-chart-body .chart-canvas-frame,
        .branch-chart-body .chart-canvas-frame {
            min-width: 660px;
        }

        .chart-header {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.5rem;
        }
    }
</style>
@endsection

@section('content')
<div class="dashboard-timeseries">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
            <h1 class="h4 font-weight-bold mb-0" style="color: #0f172a;">Timeseries Dashboard</h1>
            <p class="text-muted small mb-0" style="font-size: 0.8rem;">Tren Keragaan Harian Berdasarkan Perspektif Bulanan</p>
        </div>
        <div class="unit-badge">Satuan: Dalam Rp Miliar (Rp M)</div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body p-4">
            <div class="row align-items-end">
                <div class="col-lg-3 mb-3 mb-lg-0">
                    <label class="filter-label">Kategori Metrik</label>
                    <div class="category-selector" id="categorySelector">
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'simpanan' ? 'active' : '' }}" data-value="simpanan">Simpanan</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'pinjaman' ? 'active' : '' }}" data-value="pinjaman">Pinjaman</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'sml' ? 'active' : '' }}" data-value="sml">SML</button>
                        <button class="category-btn {{ $dashboardPage['selected']['category'] === 'npl' ? 'active' : '' }}" data-value="npl">NPL</button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Kantor Cabang</label>
                    <div class="branch-filter-dropdown" id="kancaDropdownShell">
                        <div class="branch-dropdown-toggle" id="kancaDropdown">
                            <span class="branch-dropdown-label" id="kancaLabel">Memuat Cabang...</span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="kancaMenu">
                            <div class="options-container" id="kancaOptionsList">
                                {{-- Will be populated by JS --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Unit Kerja</label>
                    <div class="branch-filter-dropdown" id="unitDropdownShell">
                        <div class="branch-dropdown-toggle" id="unitDropdown">
                            <span class="branch-dropdown-label" id="unitLabel">Semua Unit Kerja</span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="unitMenu">
                            <input type="hidden" id="unitInput" value="{{ $dashboardPage['selected']['unit_kerja'] }}">
                            <div class="options-container" id="unitOptions">
                                {{-- Will be populated by JS --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label" for="periodMonthFilter">Periode Akhir</label>
                    <div class="period-select-shell">
                        <select id="periodMonthFilter" class="period-month-select">
                            @foreach(($dashboardPage['filters']['period_month'] ?? []) as $item)
                                <option value="{{ $item['value'] }}" {{ ($dashboardPage['selected']['period_month'] ?? '') === $item['value'] ? 'selected' : '' }}>
                                    {{ $item['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
                <div class="col-lg-2">
                    <button id="applyFilters" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-sync-alt mr-2"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Chart Container -->
    <div class="row mb-3" id="summaryChartContainer">
        <div class="col-12">
            <div class="card chart-card summary-chart-card">
                <div class="chart-header">
                    <h5 class="chart-title"><i class="fas fa-chart-area mr-2 text-primary"></i>Area 6 - Konsolidasi</h5>
                    <div class="unit-badge" id="summaryChartBadge">Total Konsolidasi Selected Branches</div>
                </div>
                <div class="chart-body summary-chart-body">
                    <div class="loading-overlay" id="summaryLoading">
                        <div class="loading-spinner"></div>
                    </div>
                    <div class="chart-canvas-frame">
                        <canvas id="summaryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Branch Charts -->
    <div class="row g-3" id="individualChartsContainer" style="margin-top: 0;">
        <!-- Dynamic Content -->
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
    (function() {
        console.log('Timeseries Dashboard Script Initializing...');
        
        function init() {
            const routes = @json($dashboardPage['routes']);
            const initialTimeseriesData = @json($dashboardPage['initialData'] ?? []);
            let currentCategory = '{{ $dashboardPage['selected']['category'] }}';
            let charts = {};
            let activeRequestId = 0;
            const totalArea6Count = 4;

            // --- Data Definitions ---
            const allKancasData = @json($dashboardPage['filters']['kanca']);
            const allUnitsData = @json($dashboardPage['filters']['unit_kerja']);
            const selectedKancasInitial = @json($dashboardPage['selected']['kanca']);
            const selectedUnitInitial = '{{ $dashboardPage['selected']['unit_kerja'] }}';

            // --- Custom Dropdown Shell Definitions ---
            const kancaToggle = document.getElementById('kancaDropdown');
            const kancaMenu = document.getElementById('kancaMenu');
            const kancaOptionsContainer = document.getElementById('kancaOptionsList');
            const kancaLabel = document.getElementById('kancaLabel');
            
            const unitToggle = document.getElementById('unitDropdown');
            const unitMenu = document.getElementById('unitMenu');
            const unitLabel = document.getElementById('unitLabel');
            const unitInput = document.getElementById('unitInput');
            const unitOptionsContainer = document.getElementById('unitOptions');
            const periodMonthSelect = document.getElementById('periodMonthFilter');
            const applyBtn = document.getElementById('applyFilters');

            // Set initial selected state in memory
            let activeKancas = new Set(selectedKancasInitial || []);

            function hasTimeseriesData(data) {
                return Boolean(data && Array.isArray(data.months) && data.months.length > 0);
            }

            function syncSummaryVisibility(selectedKanca) {
                const summaryContainer = document.getElementById('summaryChartContainer');
                const shouldShowSummary = selectedKanca.length === 0 || selectedKanca.length === totalArea6Count;

                if (summaryContainer) {
                    summaryContainer.style.display = shouldShowSummary ? 'block' : 'none';
                }

                return shouldShowSummary;
            }

            function setLoadingState(isLoading, shouldShowSummary) {
                const loading = document.getElementById('summaryLoading');
                if (loading) {
                    loading.style.display = isLoading && shouldShowSummary ? 'flex' : 'none';
                }

                if (applyBtn) {
                    applyBtn.disabled = isLoading;
                    applyBtn.innerHTML = isLoading
                        ? '<i class="fas fa-spinner fa-spin mr-2"></i> Memuat'
                        : '<i class="fas fa-sync-alt mr-2"></i> Update';
                }
            }

            // --- Dropdown Management ---
            function closeAllDropdowns() {
                if (kancaMenu) kancaMenu.classList.remove('show');
                if (unitMenu) unitMenu.classList.remove('show');
                
                // Reset z-indices
                const kancaShell = document.getElementById('kancaDropdownShell');
                const unitShell = document.getElementById('unitDropdownShell');
                if (kancaShell) kancaShell.style.zIndex = '';
                if (unitShell) unitShell.style.zIndex = '';
            }

            document.addEventListener('click', closeAllDropdowns);
            
            if (kancaMenu) kancaMenu.addEventListener('click', (e) => e.stopPropagation());
            if (unitMenu) unitMenu.addEventListener('click', (e) => e.stopPropagation());

            if (kancaToggle) {
                kancaToggle.addEventListener('click', (e) => {
                    console.log('Kanca Toggle Clicked');
                    e.stopPropagation();
                    const wasOpen = kancaMenu.classList.contains('show');
                    closeAllDropdowns();
                    if (!wasOpen) {
                        kancaMenu.classList.add('show');
                        const shell = document.getElementById('kancaDropdownShell');
                        if (shell) shell.style.zIndex = '1001';
                    }
                });
            }

            if (unitToggle) {
                unitToggle.addEventListener('click', (e) => {
                    console.log('Unit Toggle Clicked');
                    e.stopPropagation();
                    const wasOpen = unitMenu.classList.contains('show');
                    closeAllDropdowns();
                    if (!wasOpen) {
                        unitMenu.classList.add('show');
                        const shell = document.getElementById('unitDropdownShell');
                        if (shell) shell.style.zIndex = '1001';
                    }
                });
            }

            // --- Kantor Cabang Logic ---
            function rebuildKancaOptions() {
                if (!kancaOptionsContainer) return;
                kancaOptionsContainer.innerHTML = '';
                
                allKancasData.forEach(k => {
                    if (k.value === 'all') return;
                    const opt = document.createElement('div');
                    opt.className = `branch-option ${activeKancas.has(k.value) ? 'selected' : ''}`;
                    opt.setAttribute('data-value', k.value);
                    opt.innerHTML = `
                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                        <span class="branch-option-label">${k.label}</span>
                    `;
                    opt.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (activeKancas.has(k.value)) {
                            activeKancas.delete(k.value);
                        } else {
                            activeKancas.add(k.value);
                        }
                        opt.classList.toggle('selected');
                        updateKancaLabel();
                    });
                    kancaOptionsContainer.appendChild(opt);
                });
                updateKancaLabel();
            }

            function updateKancaLabel() {
                const count = activeKancas.size;
                const total = allKancasData.filter(k => k.value !== 'all').length;
                
                if (count === 0) {
                    kancaLabel.textContent = 'Semua Kantor Cabang';
                } else if (count === total) {
                    kancaLabel.textContent = 'Semua Cabang Dipilih';
                } else if (count === 1) {
                    const firstVal = Array.from(activeKancas)[0];
                    const k = allKancasData.find(x => x.value === firstVal);
                    kancaLabel.textContent = k ? k.label : '1 Cabang Dipilih';
                } else {
                    kancaLabel.textContent = `${count} Cabang Dipilih`;
                }

                rebuildUnitOptions();
            }

            // --- Unit Dropdown Logic ---
            function rebuildUnitOptions() {
                if (!unitOptionsContainer) return;
                
                const currentUnit = unitInput ? unitInput.value : 'all';
                let foundCurrentUnit = currentUnit === 'all';
                let currentUnitLabel = 'Semua Unit Kerja';

                unitOptionsContainer.innerHTML = `
                    <div class="branch-option ${currentUnit === 'all' ? 'selected' : ''}" data-value="all">
                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                        <span class="branch-option-label">Semua Unit Kerja</span>
                    </div>
                `;

                allUnitsData.forEach(unit => {
                    if (unit.value === 'all') return;
                    // Show if no kanca selected or unit belongs to selected kanca
                    if (activeKancas.size === 0 || activeKancas.has(unit.kanca_value)) {
                        const opt = document.createElement('div');
                        opt.className = `branch-option ${unit.value === currentUnit ? 'selected' : ''}`;
                        opt.setAttribute('data-value', unit.value);
                        opt.innerHTML = `
                            <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                            <span class="branch-option-label">${unit.label}</span>
                        `;
                        
                        if (unit.value === currentUnit) {
                            foundCurrentUnit = true;
                            currentUnitLabel = unit.label;
                        }

                        opt.addEventListener('click', (e) => {
                            e.stopPropagation();
                            selectUnit(unit.value, unit.label);
                        });
                        unitOptionsContainer.appendChild(opt);
                    }
                });

                const allOpt = unitOptionsContainer.querySelector('[data-value="all"]');
                if (allOpt) {
                    allOpt.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectUnit('all', 'Semua Unit Kerja');
                    });
                }

                if (!foundCurrentUnit) {
                    selectUnit('all', 'Semua Unit Kerja');
                } else if (unitLabel) {
                    unitLabel.textContent = currentUnitLabel;
                }
            }

            function selectUnit(value, label) {
                if (unitInput) unitInput.value = value;
                if (unitLabel) unitLabel.textContent = label;
                closeAllDropdowns();
                
                if (unitOptionsContainer) {
                    unitOptionsContainer.querySelectorAll('.branch-option').forEach(o => {
                        o.classList.toggle('selected', o.getAttribute('data-value') === value);
                    });
                }
            }

            // --- Core Logic ---
            async function fetchData() {
                console.log('Fetching Data for Kancas:', Array.from(activeKancas));
                const selectedKanca = Array.from(activeKancas);
                const unit = unitInput ? unitInput.value : 'all';
                const periodMonth = periodMonthSelect ? periodMonthSelect.value : '';
                const requestId = ++activeRequestId;
                const shouldShowSummary = syncSummaryVisibility(selectedKanca);
                setLoadingState(true, shouldShowSummary);

                try {
                    const queryParams = new URLSearchParams({
                        category: currentCategory,
                        unit_kerja: unit,
                        period_month: periodMonth
                    });
                    selectedKanca.forEach(k => queryParams.append('kanca[]', k));

                    const response = await fetch(`${routes.data}?${queryParams.toString()}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json();
                    if (requestId !== activeRequestId) return;

                    if (!hasTimeseriesData(data)) {
                        renderEmptyChart();
                        return;
                    }

                    renderCharts(data, selectedKanca.length);
                } catch (error) {
                    console.error('Failed to fetch timeseries data:', error);
                    renderEmptyChart();
                } finally {
                    if (requestId === activeRequestId) {
                        setLoadingState(false, shouldShowSummary);
                    }
                }
            }

            function renderEmptyChart() {
                Object.values(charts).forEach(c => { try { c.destroy(); } catch(e) {} });
                charts = {};
                const container = document.getElementById('individualChartsContainer');
                if (container) {
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
            }

            const monthColors = [
                { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.05)' },
                { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.05)' },
                { border: '#10b981', bg: 'rgba(16, 185, 129, 0.05)' },
                { border: '#0857c3', bg: 'rgba(8, 87, 195, 0.1)' },
            ];

            function resolveYAxisBounds(datasets, isSummary = false) {
                const values = datasets
                    .flatMap(dataset => Array.isArray(dataset.data) ? dataset.data : [])
                    .filter(value => value !== null && value !== undefined && value !== '')
                    .map(value => typeof value === 'number' ? value : Number(value))
                    .filter(value => Number.isFinite(value));

                if (values.length === 0) {
                    return {};
                }

                const min = Math.min(...values);
                const max = Math.max(...values);
                const naturalSpread = max - min;
                const minRange = Math.max(Math.abs(max) * (isSummary ? 0.025 : 0.015), isSummary ? 25 : 10, 1);
                const effectiveSpread = Math.max(naturalSpread, minRange);
                const center = (min + max) / 2;
                const pad = effectiveSpread * 0.12;
                const rawMin = naturalSpread < minRange
                    ? center - (minRange / 2) - pad
                    : min - pad;
                const rawMax = naturalSpread < minRange
                    ? center + (minRange / 2) + pad
                    : max + pad;

                return {
                    min: min >= 0 ? Math.max(0, rawMin) : rawMin,
                    max: rawMax,
                };
            }

            function createChartConfig(title, months, datasets, isSummary = false) {
                const yAxisBounds = resolveYAxisBounds(datasets, isSummary);

                return {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 31}, (_, i) => i + 1),
                        datasets: datasets.map((d, i) => {
                            const isLatest = i === datasets.length - 1;
                            return {
                                label: d.label,
                                data: d.data,
                                borderColor: monthColors[i % monthColors.length].border,
                                backgroundColor: monthColors[i % monthColors.length].bg,
                                borderWidth: isLatest ? 3.5 : 2,
                                pointRadius: isLatest ? 4 : 1.5,
                                pointHoverRadius: 7,
                                tension: 0.4,
                                fill: isLatest,
                                clip: false,
                                spanGaps: false,
                                borderDash: isLatest ? [] : [4, 2]
                            };
                        })
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: isSummary ? 16 : 24,
                                right: isSummary ? 16 : 26,
                                bottom: isSummary ? 36 : 30,
                                left: isSummary ? 12 : 12
                            }
                        },
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: { weight: 'bold', size: 10 }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                                padding: 12,
                                titleFont: { size: 13, weight: 'bold' },
                                bodyFont: { size: 12 },
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
                                display: true,
                                beginAtZero: false,
                                min: yAxisBounds.min,
                                max: yAxisBounds.max,
                                grace: 0,
                                border: {
                                    display: true,
                                    color: '#cbd5e1'
                                },
                                title: {
                                    display: true,
                                    text: 'Value (Rp Miliar)',
                                    font: { weight: 'bold', size: 10 }
                                },
                                grid: {
                                    color: 'rgba(15, 23, 42, 0.07)',
                                    drawTicks: true
                                },
                                ticks: {
                                    maxTicksLimit: isSummary ? 7 : 6,
                                    padding: 10,
                                    display: true,
                                    font: { size: 10 },
                                    callback: function(value) {
                                        const scale = this.chart.scales.y;
                                        const spread = Math.abs(scale.max - scale.min);
                                        let precision = 0;
                                        if (spread < 2) precision = 2;
                                        else if (spread < 20) precision = 1;

                                        return new Intl.NumberFormat('id-ID', { 
                                            maximumFractionDigits: precision,
                                            minimumFractionDigits: (spread < 20 && value % 1 !== 0) ? precision : 0
                                        }).format(value);
                                    }
                                }
                            },
                            x: {
                                display: true,
                                border: {
                                    display: true,
                                    color: '#cbd5e1'
                                },
                                grid: {
                                    display: false,
                                    drawTicks: true
                                },
                                ticks: {
                                    display: true,
                                    padding: 10,
                                    font: { size: 10 },
                                    autoSkip: true,
                                    maxTicksLimit: isSummary ? 16 : 12
                                },
                                title: {
                                    display: isSummary,
                                    text: 'Tanggal',
                                    color: '#64748b',
                                    padding: { top: 10 },
                                    font: { size: 10, weight: 'bold' }
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

            function renderCharts(data, selectedCount) {
                Object.values(charts).forEach(c => { try { c.destroy(); } catch(e) {} });
                charts = {};

                const summaryContainer = document.getElementById('summaryChartContainer');
                if (summaryContainer && summaryContainer.style.display !== 'none') {
                    const summaryCanvas = document.getElementById('summaryChart');
                    if (!summaryCanvas) return;

                    const summaryCtx = summaryCanvas.getContext('2d');
                    const summaryDatasets = data.months.map(month => ({
                        label: getMonthName(month),
                        data: data.area_total[month] || new Array(31).fill(null)
                    }));
                    charts['summary'] = new Chart(summaryCtx, createChartConfig('Total Area', data.months, summaryDatasets, true));
                    
                    const badge = document.getElementById('summaryChartBadge');
                    if (badge) {
                        badge.textContent = selectedCount === 0 ? 'Total Konsolidasi Area 6' : 'Total Konsolidasi (4 Cabang Dipilih)';
                    }
                }

                const container = document.getElementById('individualChartsContainer');
                if (container) {
                    container.innerHTML = '';
                    const branchNames = Object.keys(data.series || {}).sort();
                    if (branchNames.length === 0) {
                        renderEmptyChart();
                        return;
                    }

                    const isFullWidth = branchNames.length === 1;
                    const unitSuffix = (unitInput && unitInput.value !== 'all') ? unitLabel.textContent : 'Konsolidasi';

                    branchNames.forEach(branch => {
                        const col = document.createElement('div');
                        col.className = isFullWidth ? 'col-12 mb-4' : 'col-lg-6 mb-3';
                        const canvasId = `chart_${branch.replace(/[^\w-]/g, '_')}`;
                        const displayTitle = `${branch} - ${unitSuffix}`;
                        
                        col.innerHTML = `
                            <div class="card chart-card">
                                <div class="chart-header">
                                    <h5 class="chart-title">${displayTitle}</h5>
                                    <span class="unit-badge">Daily Trend</span>
                                </div>
                                <div class="chart-body branch-chart-body ${isFullWidth ? 'tall' : ''}">
                                    <div class="chart-canvas-frame">
                                        <canvas id="${canvasId}"></canvas>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(col);

                        const ctx = document.getElementById(canvasId).getContext('2d');
                        const datasets = data.months.map(month => ({
                            label: getMonthName(month),
                            data: (data.series[branch] && data.series[branch][month]) ? data.series[branch][month] : new Array(31).fill(null)
                        }));
                        charts[branch] = new Chart(ctx, createChartConfig(branch, data.months, datasets));
                    });
                }
            }

            // Category Selector
            const categoryBtns = document.querySelectorAll('.category-btn');
            categoryBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    categoryBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentCategory = this.getAttribute('data-value');
                    fetchData();
                });
            });

            if (applyBtn) {
                applyBtn.addEventListener('click', fetchData);
            }

            if (periodMonthSelect) {
                periodMonthSelect.addEventListener('change', fetchData);
            }

            // Initial Initialization
            rebuildKancaOptions();
            syncSummaryVisibility(Array.from(activeKancas));
            if (hasTimeseriesData(initialTimeseriesData)) {
                renderCharts(initialTimeseriesData, activeKancas.size);
                setLoadingState(false, syncSummaryVisibility(Array.from(activeKancas)));
            } else {
                fetchData();
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();

</script>
@endsection
