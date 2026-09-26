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

    .timeseries-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        background: linear-gradient(135deg, #003b75 0%, #00529c 100%);
        border-bottom: 1px solid rgba(219, 229, 239, 0.92);
        color: #ffffff;
    }

    .timeseries-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(120deg, rgba(255, 255, 255, 0.05), transparent 40%);
        opacity: 0.72;
    }

    .timeseries-title-wrap {
        max-width: 760px;
    }

    .timeseries-title-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.55rem;
        padding: 0.32rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.24);
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.64rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .timeseries-title-badge i {
        color: #ffb15c;
    }

    .timeseries-title {
        margin: 0;
        font-size: clamp(1.35rem, 2.35vw, 2.35rem);
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 0.035em;
        line-height: 1.08;
        text-transform: uppercase;
        text-shadow: 0 10px 26px rgba(0, 18, 50, 0.28);
    }

    .timeseries-title::after {
        content: '';
        display: block;
        width: min(130px, 38vw);
        height: 3px;
        margin: 0.7rem 0 0;
        border-radius: 999px;
        background: linear-gradient(90deg, #ff671f, #f9b233, rgba(255, 255, 255, 0.9));
        box-shadow: 0 8px 18px rgba(255, 103, 31, 0.28);
    }

    .timeseries-subtitle {
        margin: 0.65rem 0 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.78rem;
        line-height: 1.6;
        max-width: 620px;
    }

    .timeseries-hero-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
        padding-top: 2.15rem;
    }

    .timeseries-hero .btn-export-all {
        min-height: 32px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.45);
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-weight: 800;
        letter-spacing: 0.025em;
        font-size: 0.68rem;
        padding: 0.34rem 0.72rem !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16);
    }

    .timeseries-hero .btn-export-all:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.68);
    }

    .timeseries-unit-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.38rem 0.68rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    /* Filter Sidebar/Top Styling */
    .filter-card {
        background:
            linear-gradient(180deg, rgba(235, 243, 255, 0.98) 0%, rgba(255, 255, 255, 0.98) 76%),
            var(--filter-card-bg);
        border: 1px solid rgba(8, 87, 195, 0.14);
        border-radius: 1.25rem;
        box-shadow: 0 18px 38px -28px rgba(8, 87, 195, 0.32);
        margin-bottom: 1rem;
        overflow: visible !important;
        position: relative;
        z-index: 100;
    }

    .filter-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 1rem;
        right: 1rem;
        height: 3px;
        border-radius: 999px;
        background: linear-gradient(90deg, #00529c, #3b82f6, #ffb15c);
    }

    .filter-card .card-body {
        padding: 1rem 1rem 0.95rem !important;
        overflow: visible !important;
    }

    .filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #4b6285;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .timeseries-dimension-select {
        width: 100%;
        min-height: 42px;
        border: 1px solid #cbd8e8;
        border-radius: 12px;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%);
        color: #334155;
        font-size: 0.88rem;
        font-weight: 700;
        padding: 0.55rem 0.85rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 12px 22px -20px rgba(15, 23, 42, 0.2);
    }

    .timeseries-dimension-select:focus {
        border-color: #0857c3;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.12);
        outline: none;
    }

    .filter-workflow-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .filter-workflow-heading h2 {
        margin: 0;
        color: #102a4c;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    .filter-workflow-heading p {
        margin: 0.25rem 0 0;
        color: #526987;
        font-size: 0.78rem;
        line-height: 1.45;
    }

    .filter-ready-state {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 30px;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: #e9f8ef;
        color: #17653a;
        font-size: 0.72rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .filter-ready-state i {
        font-size: 0.46rem;
    }

    .filter-ready-state.is-pending {
        background: #fff4e5;
        color: #8a4b08;
    }

    .filter-workflow-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.7rem;
    }

    .filter-step {
        min-width: 0;
        padding: 0.78rem;
        border: 1px solid #dbe6f2;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.86);
    }

    .filter-step:focus-within {
        border-color: #307fe2;
        box-shadow: 0 0 0 3px rgba(48, 127, 226, 0.1);
    }

    .filter-step-heading {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.48rem;
    }

    .filter-step-heading label {
        margin: 0;
        color: #1e3657;
        font-size: 0.76rem;
        font-weight: 800;
    }

    .filter-step-index {
        display: inline-grid;
        width: 22px;
        height: 22px;
        place-items: center;
        border-radius: 50%;
        background: #e3efff;
        color: #0857c3;
        font-size: 0.68rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }

    .filter-step small {
        display: block;
        min-height: 2.3em;
        margin-top: 0.42rem;
        color: #61738e;
        font-size: 0.68rem;
        line-height: 1.35;
    }

    .filter-step .timeseries-dimension-select,
    .filter-action-row .period-month-select {
        min-height: 46px;
        padding-top: 0.55rem;
        padding-bottom: 0.55rem;
        background-color: #ffffff;
        font-size: 0.82rem;
    }

    .timeseries-dimension-select:disabled {
        cursor: not-allowed;
        background: #edf2f7;
        color: #738198;
        opacity: 1;
    }

    .filter-action-row {
        display: grid;
        grid-template-columns: minmax(190px, 0.7fr) minmax(0, 1.7fr) minmax(180px, 0.6fr);
        align-items: end;
        gap: 0.8rem;
        margin-top: 0.85rem;
        padding-top: 0.85rem;
        border-top: 1px solid #dbe6f2;
    }

    .filter-period-control {
        position: relative;
        min-width: 0;
    }

    .filter-selection-summary {
        min-width: 0;
        padding: 0 0.25rem 0.3rem;
    }

    .filter-summary-label {
        display: block;
        margin-bottom: 0.18rem;
        color: #687b96;
        font-size: 0.66rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .filter-selection-summary strong {
        display: block;
        overflow: hidden;
        color: #183556;
        font-size: 0.82rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #applyFilters {
        min-height: 46px;
        white-space: nowrap;
    }

    #applyFilters:disabled {
        cursor: wait;
        opacity: 0.72;
        transform: none;
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

    .btn-export-jpg {
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        padding: 0;
        cursor: pointer;
        margin-left: 0.75rem;
    }

    .btn-export-jpg:hover {
        background: #f8fbff;
        border-color: #0857c3;
        color: #0857c3;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(8, 87, 195, 0.15);
    }

    .btn-export-jpg i {
        font-size: 0.85rem;
    }

    .chart-header > .d-flex {
        min-width: 0;
        max-width: 100%;
    }

    .chart-header .unit-badge {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Capture Status Modal Premium Styles */
    .capture-status-modal .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(4, 42, 95, 0.2);
        overflow: hidden;
    }

    .capture-status-modal .modal-body {
        padding: 3rem 2rem;
    }

    .capture-status-modal-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
    }

    .icon-loading { background: rgba(8, 87, 195, 0.1); color: #0857c3; }
    .icon-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

    .capture-status-modal .btn-primary {
        border-radius: 12px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .modal-backdrop.show {
        backdrop-filter: none;
        background-color: rgba(15, 23, 42, 0.12);
    }

    body.modal-open .dashboard-timeseries,
    body.modal-open .content-wrapper,
    body.modal-open .main-content {
        filter: none !important;
        backdrop-filter: none !important;
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

    .period-month-select {
        width: 100%;
        min-height: 42px;
        padding: 0.6rem 2.4rem 0.6rem 1rem;
        background: rgba(255, 255, 255, 0.86);
        border: 1px solid #dbe5ef;
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

    #applyFilters {
        min-height: 42px;
        border: none;
        border-radius: 0.85rem;
        background: linear-gradient(135deg, #00529c 0%, #1d4ed8 100%);
        font-weight: 800;
        letter-spacing: 0.02em;
        box-shadow: 0 14px 24px -18px rgba(8, 87, 195, 0.72);
    }

    #applyFilters:hover {
        filter: saturate(1.08);
        transform: translateY(-1px);
    }

    .filter-mobile-toggle {
        display: none;
        width: 100%;
        background: #ffffff;
        border-bottom: 1px solid rgba(8, 87, 195, 0.1);
        border-radius: 1.25rem 1.25rem 0 0;
        padding: 0.35rem 0.5rem;
    }

    .btn-filter-toggle {
        background: transparent;
        border: none;
        padding: 0.42rem 0.62rem;
        width: 100%;
        text-align: left;
        color: #00529C;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
    }

    .btn-filter-toggle:focus {
        outline: none;
        box-shadow: none;
    }

    .btn-filter-toggle .toggle-arrow-icon {
        transition: transform 0.2s ease;
        color: #8b9eb7;
    }

    .filter-card.is-open .toggle-arrow-icon {
        transform: rotate(180deg);
    }

    @media (max-width: 1199.98px) {
        .filter-workflow-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .filter-mobile-toggle {
            display: flex !important;
        }

        .filter-card .card-body {
            display: none !important;
            padding: 0.75rem 1rem 0.95rem !important;
        }

        .filter-card.is-open .card-body {
            display: block !important;
        }

        .filter-label {
            font-size: 0.68rem !important;
            margin-bottom: 0.25rem !important;
        }

        .timeseries-dimension-select,
        .period-month-select,
        #applyFilters {
            min-height: 46px !important;
            font-size: 0.82rem !important;
        }
    }

    @media (max-width: 767.98px) {
        .dashboard-timeseries > .d-flex,
        .dashboard-timeseries > .timeseries-hero {
            align-items: flex-start !important;
            flex-direction: column;
            gap: 0.75rem;
        }

        .timeseries-hero {
            padding: 1.15rem 1rem;
            margin-inline: 0;
        }

        .timeseries-hero-actions {
            width: 100%;
            justify-content: flex-start;
            padding-top: 0.35rem;
        }

        .timeseries-hero .btn-export-all,
        .timeseries-unit-badge {
            width: 100%;
            justify-content: center;
        }

        .timeseries-title::after {
            margin-left: 0;
        }

        .filter-workflow-heading {
            align-items: stretch;
            flex-direction: column;
        }

        .filter-ready-state {
            align-self: flex-start;
        }

        .filter-workflow-grid,
        .filter-action-row {
            grid-template-columns: minmax(0, 1fr);
        }

        .filter-step small {
            min-height: 0;
        }

        .filter-selection-summary {
            padding-inline: 0;
        }

        .filter-selection-summary strong {
            overflow: visible;
            text-overflow: clip;
            white-space: normal;
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
    <div class="timeseries-hero px-4 py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <h1 class="timeseries-title m-0" style="font-size: 1.5rem; font-weight: 800; letter-spacing: 0.02em;">TIMESERIES DASHBOARD</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button id="captureAllBtn" class="btn btn-sm btn-export-all">
                <i class="fas fa-file-image mr-1"></i> EXPORT A4
            </button>
        </div>
    </div>

    <!-- Guided Filters -->
    <div class="card filter-card is-open" aria-labelledby="timeseries-filter-title">
        <div class="filter-mobile-toggle">
            <button type="button" class="btn btn-filter-toggle d-flex align-items-center justify-content-between w-100" id="btn-toggle-filters"
                    aria-expanded="true" aria-controls="timeseries-filter-content">
                <span class="btn-toggle-text text-truncate font-weight-bold"><i class="fas fa-sliders-h mr-2"></i> FILTER DATA</span>
                <span class="active-filters-badge text-truncate text-muted small" id="filter-summary-badge">Simpanan | Area 6</span>
                <i class="fas fa-chevron-down toggle-arrow-icon ml-2"></i>
            </button>
        </div>
        <div class="card-body p-4" id="timeseries-filter-content">
            @php
                $selectedKancaFilter = $dashboardPage['selected']['kanca'] ?? [];
                $selectedKancaValue = is_string($selectedKancaFilter) ? $selectedKancaFilter : 'all';
            @endphp
            <div class="filter-workflow-heading">
                <div>
                    <h2 id="timeseries-filter-title">Susun tampilan timeseries</h2>
                    <p>Pilih dari kiri ke kanan. Opsi berikutnya menyesuaikan pilihan sebelumnya.</p>
                </div>
                <span class="filter-ready-state" id="filterReadyState" role="status" aria-live="polite">
                    <i class="fas fa-circle" aria-hidden="true"></i> Siap ditampilkan
                </span>
            </div>

            <div class="filter-workflow-grid" id="timeseriesFilterWorkflow">
                <div class="filter-step" data-step="1">
                    <div class="filter-step-heading"><span class="filter-step-index">1</span><label for="kancaInput">Cabang</label></div>
                    <select id="kancaInput" class="timeseries-dimension-select"
                            data-user-branch-locked="{{ ($dashboardPage['selected']['branch_locked'] ?? false) ? '1' : '0' }}"
                            @disabled($dashboardPage['selected']['branch_locked'] ?? false)>
                        @foreach(($dashboardPage['filters']['kanca'] ?? []) as $item)
                            <option value="{{ $item['value'] }}" @selected($selectedKancaValue === $item['value'])>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    <small>Tentukan wilayah cabang.</small>
                </div>

                <div class="filter-step" data-step="2" id="unitFilterColumn">
                    <div class="filter-step-heading"><span class="filter-step-index">2</span><label for="unitInput">Unit Kerja</label></div>
                    <select id="unitInput" class="timeseries-dimension-select">
                        <option value="all">Semua Unit Kerja</option>
                    </select>
                    <small id="unitFilterHint">Pilih cabang untuk melihat unit.</small>
                </div>

                <div class="filter-step" data-step="3">
                    <div class="filter-step-heading"><span class="filter-step-index">3</span><label for="categoryInput">Sub Item</label></div>
                    <select id="categoryInput" class="timeseries-dimension-select">
                        <option value="pinjaman" @selected(($dashboardPage['selected']['category'] ?? '') === 'pinjaman')>Pinjaman</option>
                        <option value="simpanan" @selected(($dashboardPage['selected']['category'] ?? '') === 'simpanan')>Simpanan</option>
                        <option value="sml" @selected(($dashboardPage['selected']['category'] ?? '') === 'sml')>SML</option>
                        <option value="npl" @selected(($dashboardPage['selected']['category'] ?? '') === 'npl')>NPL</option>
                        <option value="simpanan_casa" @selected(($dashboardPage['selected']['category'] ?? '') === 'simpanan_casa')>CASA</option>
                        <option value="ldr" @selected(($dashboardPage['selected']['category'] ?? '') === 'ldr')>LDR</option>
                        <option value="recovery" @selected(($dashboardPage['selected']['category'] ?? '') === 'recovery')>REC DH</option>
                    </select>
                    <small>Pilih indikator yang dianalisis.</small>
                </div>

                <div class="filter-step" data-step="4">
                    <div class="filter-step-heading"><span class="filter-step-index">4</span><label for="segmentInput">Segmen</label></div>
                    <select id="segmentInput" class="timeseries-dimension-select"></select>
                    <small id="segmentFilterHint">Segmen mengikuti jenis unit.</small>
                </div>

                <div class="filter-step" data-step="5">
                    <div class="filter-step-heading"><span class="filter-step-index">5</span><label for="productInput">Produk</label></div>
                    <select id="productInput" class="timeseries-dimension-select"></select>
                    <small id="productFilterHint">Produk mengikuti sub item dan segmen.</small>
                </div>
            </div>

            <div class="filter-action-row">
                <div class="filter-period-control">
                    <label class="filter-label" for="periodMonthFilter">Periode Akhir</label>
                    <select id="periodMonthFilter" class="period-month-select">
                        @foreach(($dashboardPage['filters']['period_month'] ?? []) as $item)
                            <option value="{{ $item['value'] }}" @selected(($dashboardPage['selected']['period_month'] ?? '') === $item['value'])>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-selection-summary" aria-live="polite">
                    <span class="filter-summary-label">Pilihan aktif</span>
                    <strong id="filterSelectionSummary">-</strong>
                </div>
                <button id="applyFilters" class="btn btn-primary">
                    <i class="fas fa-chart-line mr-2" aria-hidden="true"></i><span>Tampilkan Data</span>
                </button>
            </div>
        </div>
    </div>
 
     <!-- Capture Target Area -->
     <div id="timeseriesCaptureArea" style="background: #fdfdfe; padding: 1.5rem 0.5rem; border-radius: 20px;">
         <!-- Summary Chart Container -->
         <div class="row mb-3" id="summaryChartContainer">
         <div class="col-12">
             <div class="card chart-card summary-chart-card">
                 <div class="chart-header">
                     <h5 class="chart-title" id="summaryChartTitle"><i class="fas fa-chart-area mr-2 text-primary"></i>Area 6 - Konsolidasi</h5>
                    <div class="d-flex align-items-center">
                        <div class="unit-badge" id="summaryChartBadge">Total Konsolidasi Selected Branches</div>
                        <button class="btn-export-jpg ml-2" onclick="window.downloadTimeseriesChart('summary', 'Timeseries-Area6-Consolidation')" title="Export to JPG" aria-label="Export grafik konsolidasi ke JPG">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
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

    <!-- Capture Status Modal -->
    <div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <!-- Loading State -->
                    <div id="captureProgressUI">
                        <div class="capture-status-modal-icon icon-loading">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Menyusun Laporan A4</h4>
                        <p class="text-muted mb-0">Sedang menyusun konsolidasi dan empat cabang ke dalam satu gambar A4 portrait. Mohon tunggu sebentar...</p>
                    </div>

                    <!-- Error State -->
                    <div id="captureErrorUI" class="d-none">
                        <div class="capture-status-modal-icon icon-error">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Gagal Mengambil Snapshot</h4>
                        <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kendala saat menyusun snapshot A4.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Tutup & Coba Lagi
                        </button>
                    </div>

                    <!-- Success State -->
                    <div id="captureSuccessUI" class="d-none">
                        <div class="capture-status-modal-icon icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Snapshot Berhasil!</h4>
                        <p class="text-muted mb-4">Snapshot A4 dalam satu file JPG telah berhasil diunduh ke perangkat Anda.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Selesai
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
    (function() {
        console.log('Timeseries Dashboard Script Initializing...');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        function init() {
            const routes = @json($dashboardPage['routes']);
            const initialTimeseriesData = @json($dashboardPage['initialData'] ?? []);
            let currentCategory = @json($dashboardPage['selected']['category']);
            let currentSegment = @json($dashboardPage['selected']['segment'] ?? 'total');
            let currentProduct = @json($dashboardPage['selected']['product'] ?? 'total');
            let currentRecoverySegment = @json($dashboardPage['selected']['recovery_segment'] ?? '');
            let currentRecoveryProduct = @json($dashboardPage['selected']['recovery_product'] ?? '');
            let charts = {};
            let activeRequestId = 0;
            const totalArea6Count = 4;

            // --- Data Definitions ---
            const allKancasData = @json($dashboardPage['filters']['kanca']);
            const allUnitsData = @json($dashboardPage['filters']['unit_kerja']);
            const selectedKancasInitial = @json($dashboardPage['selected']['kanca']);
            const selectedUnitInitial = @json($dashboardPage['selected']['unit_kerja']);

            // --- Filter Definitions ---
            const kancaInput = document.getElementById('kancaInput');
            const unitInput = document.getElementById('unitInput');
            const categoryInput = document.getElementById('categoryInput');
            const segmentInput = document.getElementById('segmentInput');
            const productInput = document.getElementById('productInput');
            const periodMonthSelect = document.getElementById('periodMonthFilter');
            const applyBtn = document.getElementById('applyFilters');
            const filterReadyState = document.getElementById('filterReadyState');

            function selectedKancaLabel() {
                return kancaInput?.options[kancaInput.selectedIndex]?.text?.trim() || 'Area 6';
            }

            // --- Export JPG Logic ---
            window.downloadTimeseriesChart = function(chartKey, fileName) {
                const chart = charts[chartKey];
                if (!chart) {
                    console.error('Chart not found for key:', chartKey);
                    return;
                }

                // Create a temporary canvas to add white background (JPG doesn't support transparency)
                const canvas = chart.canvas;
                const tempCanvas = document.createElement('canvas');
                const ctx = tempCanvas.getContext('2d');

                tempCanvas.width = canvas.width;
                tempCanvas.height = canvas.height;

                // Fill with white background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);

                // Draw the original chart canvas on top
                ctx.drawImage(canvas, 0, 0);

                // Trigger download
                const link = document.createElement('a');
                link.download = `${fileName}.jpg`;
                link.href = tempCanvas.toDataURL('image/jpeg', 0.9);
                link.click();
            };

            // --- Capture All Logic (A4 Portrait Composer) ---
            const captureBtn = document.getElementById('captureAllBtn');
            const captureModal = document.getElementById('captureStatusModal');
            const progressUI = document.getElementById('captureProgressUI');
            const errorUI = document.getElementById('captureErrorUI');
            const successUI = document.getElementById('captureSuccessUI');
            const errorMessageUI = document.getElementById('captureErrorMessage');

            const A4_EXPORT = {
                width: 2480,
                height: 3508,
                marginX: 150,
                marginY: 135,
                headerHeight: 260,
                footerHeight: 80,
                sectionGap: 58,
                branchGap: 50,
            };

            function waitFrame() {
                return new Promise(resolve => requestAnimationFrame(() => resolve()));
            }

            function sanitizeFilePart(value) {
                return String(value || 'timeseries')
                    .trim()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .substring(0, 80) || 'timeseries';
            }

            function getCanvasChart(canvas) {
                if (!canvas || !window.Chart || typeof Chart.getChart !== 'function') {
                    return null;
                }

                return Chart.getChart(canvas);
            }

            function getVisibleChartEntries() {
                return Array.from(document.querySelectorAll('.chart-card'))
                    .filter(card => card.offsetParent !== null)
                    .map(card => {
                        const canvas = card.querySelector('canvas');
                        const chart = getCanvasChart(canvas);
                        if (!chart) return null;

                        return {
                            chart,
                            title: card.querySelector('.chart-title')?.textContent?.trim() || 'Timeseries Chart',
                            badge: card.querySelector('.unit-badge')?.textContent?.trim() || 'Daily Trend',
                        };
                    })
                    .filter(Boolean);
            }

            function cloneChartDatasets(chart, isCompact = false) {
                return chart.data.datasets.map((dataset, index) => {
                    const isLatest = index === chart.data.datasets.length - 1;

                    return {
                        label: dataset.label,
                        data: Array.isArray(dataset.data) ? dataset.data.slice() : dataset.data,
                        borderColor: dataset.borderColor,
                        backgroundColor: dataset.backgroundColor,
                        borderWidth: isCompact ? (isLatest ? 2 : 1.25) : (isLatest ? 2.75 : 1.75),
                        pointRadius: isCompact ? (isLatest ? 2 : 0) : (isLatest ? 3 : 0),
                        pointHoverRadius: isCompact ? (isLatest ? 4 : 2) : (isLatest ? 5 : 3),
                        pointBorderWidth: dataset.pointBorderWidth ?? (isLatest ? 1.5 : 0),
                        tension: dataset.tension ?? 0.32,
                        fill: dataset.fill,
                        clip: false,
                        spanGaps: dataset.spanGaps ?? false,
                        borderDash: dataset.borderDash || [],
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round',
                    };
                });
            }

            function buildExportChartOptions(chart, isCompact = false) {
                const originalOptions = chart.options || {};
                const originalScales = originalOptions.scales || {};
                const yTicksCallback = originalScales.y?.ticks?.callback;
                const tooltipLabelCallback = originalOptions.plugins?.tooltip?.callbacks?.label;
                const fontScale = isCompact ? 0.68 : 1;

                return {
                    responsive: false,
                    maintainAspectRatio: false,
                    devicePixelRatio: 2,
                    animation: false,
                    events: [],
                    layout: {
                        padding: isCompact
                            ? { top: 14, right: 18, bottom: 18, left: 10 }
                            : { top: 36, right: 42, bottom: 42, left: 26 }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: isCompact ? 12 : 30,
                                pointStyle: 'line',
                                boxWidth: isCompact ? 20 : 28,
                                boxHeight: isCompact ? 8 : 10,
                                color: '#475569',
                                font: { weight: '600', size: Math.round(23 * fontScale) }
                            }
                        },
                        tooltip: {
                            enabled: false,
                            callbacks: {
                                label: tooltipLabelCallback
                            }
                        }
                    },
                    scales: {
                        y: {
                            display: true,
                            beginAtZero: false,
                            min: originalScales.y?.min,
                            max: originalScales.y?.max,
                            grace: 0,
                            border: {
                                display: true,
                                color: '#cbd5e1'
                            },
                            title: {
                                display: true,
                                text: originalScales.y?.title?.text || 'Value (Rp Miliar)',
                                color: '#334155',
                                font: { weight: '600', size: Math.round(23 * fontScale) }
                            },
                            grid: {
                                color: 'rgba(15, 23, 42, 0.06)',
                                drawTicks: true
                            },
                            ticks: {
                                maxTicksLimit: isCompact ? 5 : 7,
                                padding: isCompact ? 8 : 18,
                                display: true,
                                color: '#475569',
                                font: { size: Math.round(22 * fontScale), weight: '500' },
                                callback: yTicksCallback
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
                                padding: isCompact ? 8 : 18,
                                color: '#475569',
                                font: { size: Math.round(22 * fontScale), weight: '500' },
                                autoSkip: true,
                                maxTicksLimit: isCompact ? 8 : 16
                            },
                            title: {
                                display: !isCompact,
                                text: 'Tanggal',
                                color: '#64748b',
                                padding: { top: 16 },
                                font: { size: Math.round(22 * fontScale), weight: '600' }
                            }
                        }
                    }
                };
            }

            async function renderChartForExport(chart, width, height, isCompact = false) {
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const exportChart = new Chart(canvas.getContext('2d'), {
                    type: chart.config.type || 'line',
                    data: {
                        labels: Array.isArray(chart.data.labels) ? chart.data.labels.slice() : chart.data.labels,
                        datasets: cloneChartDatasets(chart, isCompact)
                    },
                    options: buildExportChartOptions(chart, isCompact)
                });

                exportChart.resize(width, height);
                exportChart.update('none');
                await waitFrame();

                return { canvas, exportChart };
            }

            function drawRoundedRect(ctx, x, y, width, height, radius) {
                ctx.beginPath();
                ctx.moveTo(x + radius, y);
                ctx.lineTo(x + width - radius, y);
                ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
                ctx.lineTo(x + width, y + height - radius);
                ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
                ctx.lineTo(x + radius, y + height);
                ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
                ctx.lineTo(x, y + radius);
                ctx.quadraticCurveTo(x, y, x + radius, y);
                ctx.closePath();
            }

            function drawTextEllipsis(ctx, text, x, y, maxWidth) {
                const source = String(text || '');
                if (ctx.measureText(source).width <= maxWidth) {
                    ctx.fillText(source, x, y);
                    return;
                }

                let trimmed = source;
                while (trimmed.length > 0 && ctx.measureText(`${trimmed}...`).width > maxWidth) {
                    trimmed = trimmed.slice(0, -1);
                }
                ctx.fillText(`${trimmed}...`, x, y);
            }

            function drawExportHeader(ctx) {
                const { width, marginX, marginY } = A4_EXPORT;
                let category = selectedOptionLabel(categoryInput, '-');
                const segmentLabel = currentSegmentLabel();
                if (segmentLabel) {
                    category += ' - ' + segmentLabel;
                }
                const productLabel = currentProductLabel();
                if (productLabel) {
                    category += ' - ' + productLabel;
                }
                const periodSelect = document.getElementById('periodMonthFilter');
                const period = periodSelect?.options[periodSelect.selectedIndex]?.text || '-';
                const unit = selectedOptionLabel(unitInput, 'Semua Unit');
                const kanca = selectedKancaLabel();

                ctx.fillStyle = '#0857c3';
                ctx.fillRect(0, 0, width, 24);

                ctx.fillStyle = '#0f172a';
                ctx.font = 'bold 64px "Inter", "Segoe UI", Arial, sans-serif';
                ctx.fillText('Timeseries Analytics Dashboard', marginX, marginY + 35);

                ctx.fillStyle = '#475569';
                ctx.font = '600 30px "Inter", "Segoe UI", Arial, sans-serif';
                drawTextEllipsis(ctx, `Kategori: ${category}   |   Periode: ${period}`, marginX, marginY + 92, width - (marginX * 2));
                drawTextEllipsis(ctx, `Filter: ${kanca}   |   Unit: ${unit}`, marginX, marginY + 138, width - (marginX * 2) - 220);

                ctx.fillStyle = '#eaf2ff';
                drawRoundedRect(ctx, width - marginX - 190, marginY + 86, 190, 62, 18);
                ctx.fill();
                ctx.fillStyle = '#0857c3';
                ctx.font = 'bold 28px "Inter", "Segoe UI", Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('A4', width - marginX - 95, marginY + 126);
                ctx.textAlign = 'left';

                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(marginX, marginY + 178);
                ctx.lineTo(width - marginX, marginY + 178);
                ctx.stroke();
            }

            function drawExportFooter(ctx) {
                const { width, height, marginX } = A4_EXPORT;

                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(marginX, height - 82);
                ctx.lineTo(width - marginX, height - 82);
                ctx.stroke();

                ctx.fillStyle = '#94a3b8';
                ctx.font = '600 22px "Inter", "Segoe UI", Arial, sans-serif';
                ctx.fillText(`Generated ${new Date().toLocaleString('id-ID')}`, marginX, height - 42);
            }

            async function drawChartCard(ctx, entry, x, y, width, height, isCompact = false) {
                const radius = isCompact ? 20 : 28;
                const headerHeight = isCompact ? 82 : 116;
                const titleX = x + (isCompact ? 28 : 52);
                const titleY = y + (isCompact ? 52 : 74);
                const titleMaxWidth = width - (isCompact ? 56 : 390);

                ctx.save();
                ctx.shadowColor = 'rgba(15, 23, 42, 0.10)';
                ctx.shadowBlur = isCompact ? 16 : 28;
                ctx.shadowOffsetY = isCompact ? 7 : 12;
                ctx.fillStyle = '#ffffff';
                drawRoundedRect(ctx, x, y, width, height, radius);
                ctx.fill();
                ctx.restore();

                ctx.strokeStyle = '#dbeafe';
                ctx.lineWidth = 3;
                drawRoundedRect(ctx, x, y, width, height, radius);
                ctx.stroke();

                ctx.fillStyle = '#0f172a';
                ctx.font = `${isCompact ? 'bold 24px' : 'bold 36px'} "Inter", "Segoe UI", Arial, sans-serif`;
                drawTextEllipsis(ctx, entry.title, titleX, titleY, titleMaxWidth);

                if (!isCompact) {
                    ctx.fillStyle = '#eaf2ff';
                    drawRoundedRect(ctx, x + width - 310, y + 35, 250, 54, 20);
                    ctx.fill();
                    ctx.fillStyle = '#0857c3';
                    ctx.font = 'bold 21px "Inter", "Segoe UI", Arial, sans-serif';
                    ctx.textAlign = 'center';
                    drawTextEllipsis(ctx, entry.badge, x + width - 185, y + 70, 210);
                    ctx.textAlign = 'left';
                }

                ctx.strokeStyle = '#eef2f7';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(x, y + headerHeight);
                ctx.lineTo(x + width, y + headerHeight);
                ctx.stroke();

                const chartPaddingX = isCompact ? 24 : 55;
                const chartPaddingBottom = isCompact ? 22 : 55;
                const chartTop = y + headerHeight + (isCompact ? 10 : 19);
                const chartWidth = width - (chartPaddingX * 2);
                const chartHeight = height - headerHeight - chartPaddingBottom;
                const renderedChart = await renderChartForExport(entry.chart, chartWidth, chartHeight, isCompact);
                const chartCanvas = renderedChart.canvas;
                ctx.drawImage(chartCanvas, x + chartPaddingX, chartTop, chartWidth, chartHeight);
                renderedChart.exportChart.destroy();
            }

            function resolveA4LayoutEntries(chartEntries) {
                const summary = chartEntries.find(entry => entry.chart === charts.summary) || chartEntries[0];
                const branches = chartEntries
                    .filter(entry => entry !== summary)
                    .slice(0, 4);

                return { summary, branches };
            }

            if (captureBtn) {
                captureBtn.addEventListener('click', async function() {
                    const chartEntries = getVisibleChartEntries();
                    if (chartEntries.length === 0) return;

                    // Show Modal with Loading State
                    if (window.jQuery) {
                        window.jQuery(captureModal).modal('show');
                        progressUI.classList.remove('d-none');
                        errorUI.classList.add('d-none');
                        successUI.classList.add('d-none');
                    }

                    const originalBtnHtml = captureBtn.innerHTML;
                    captureBtn.disabled = true;
                    captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> CAPTURING...';

                    try {
                        const contentTop = A4_EXPORT.marginY + A4_EXPORT.headerHeight;
                        const contentHeight = A4_EXPORT.height - contentTop - A4_EXPORT.footerHeight - A4_EXPORT.marginY;
                        const cardWidth = A4_EXPORT.width - (A4_EXPORT.marginX * 2);
                        const summaryHeight = Math.floor((contentHeight - A4_EXPORT.sectionGap) / 2);
                        const branchGridTop = contentTop + summaryHeight + A4_EXPORT.sectionGap;
                        const branchGridHeight = contentHeight - summaryHeight - A4_EXPORT.sectionGap;
                        const branchCardWidth = Math.floor((cardWidth - A4_EXPORT.branchGap) / 2);
                        const branchCardHeight = Math.floor((branchGridHeight - A4_EXPORT.branchGap) / 2);
                        let categoryText = selectedOptionLabel(categoryInput, 'Timeseries');
                        const segmentLabel = currentSegmentLabel();
                        if (segmentLabel) {
                            categoryText += '-' + segmentLabel;
                        }
                        const productLabel = currentProductLabel();
                        if (productLabel) categoryText += '-' + productLabel;
                        const category = sanitizeFilePart(categoryText);
                        const timestamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
                        const { summary, branches } = resolveA4LayoutEntries(chartEntries);
                        const pageCanvas = document.createElement('canvas');
                        pageCanvas.width = A4_EXPORT.width;
                        pageCanvas.height = A4_EXPORT.height;
                        const ctx = pageCanvas.getContext('2d');

                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, pageCanvas.width, pageCanvas.height);
                        drawExportHeader(ctx);

                        await drawChartCard(ctx, summary, A4_EXPORT.marginX, contentTop, cardWidth, summaryHeight);

                        for (let itemIndex = 0; itemIndex < branches.length; itemIndex++) {
                            const col = itemIndex % 2;
                            const row = Math.floor(itemIndex / 2);
                            const x = A4_EXPORT.marginX + (col * (branchCardWidth + A4_EXPORT.branchGap));
                            const y = branchGridTop + (row * (branchCardHeight + A4_EXPORT.branchGap));
                            await drawChartCard(ctx, branches[itemIndex], x, y, branchCardWidth, branchCardHeight, true);
                        }

                        drawExportFooter(ctx);

                        const link = document.createElement('a');
                        link.download = `Timeseries-A4-${category}-${timestamp}.jpg`;
                        link.href = pageCanvas.toDataURL('image/jpeg', 0.95);
                        link.click();

                        // Show Success UI
                        progressUI.classList.add('d-none');
                        successUI.classList.remove('d-none');
                    } catch (err) {
                        console.error('Stitching failure:', err);
                        progressUI.classList.add('d-none');
                        errorUI.classList.remove('d-none');
                        errorMessageUI.textContent = 'Gagal menyusun laporan A4. Pastikan seluruh grafik sudah muncul sempurna dan coba lagi.';
                    } finally {
                        captureBtn.disabled = false;
                        captureBtn.innerHTML = originalBtnHtml;
                    }
                });
            }

            // Set initial selected state in memory
            const normalizedInitialKancas = Array.isArray(selectedKancasInitial)
                ? selectedKancasInitial
                : (selectedKancasInitial ? [selectedKancasInitial] : []);
            let activeKancas = new Set(normalizedInitialKancas);

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
                        ? '<i class="fas fa-spinner fa-spin mr-2" aria-hidden="true"></i><span>Memuat Data</span>'
                        : '<i class="fas fa-chart-line mr-2" aria-hidden="true"></i><span>Tampilkan Data</span>';
                }

                if (filterReadyState && isLoading) {
                    filterReadyState.classList.remove('is-pending');
                    filterReadyState.innerHTML = '<i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Memuat grafik';
                }
            }

            function selectedOptionLabel(select, fallback = '') {
                return select?.options[select.selectedIndex]?.text?.trim() || fallback;
            }

            function selectedUnitKind() {
                return unitInput?.options[unitInput.selectedIndex]?.dataset?.unitKind || 'all';
            }

            function markFiltersPending() {
                if (filterReadyState) {
                    filterReadyState.classList.add('is-pending');
                    filterReadyState.innerHTML = '<i class="fas fa-circle" aria-hidden="true"></i> Pilihan berubah';
                }
                updateTimeseriesFilterSummary();
            }

            function markFiltersApplied() {
                if (filterReadyState) {
                    filterReadyState.classList.remove('is-pending');
                    filterReadyState.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Data terbaru tampil';
                }
            }

            function updateTimeseriesFilterSummary() {
                const summaryParts = [
                    selectedOptionLabel(kancaInput, 'Area 6'),
                    selectedOptionLabel(unitInput, 'Semua Unit Kerja'),
                    selectedOptionLabel(categoryInput, 'Simpanan'),
                    selectedOptionLabel(segmentInput, 'Semua Segmen'),
                    selectedOptionLabel(productInput, 'Semua Produk'),
                ];
                const conciseSummary = summaryParts.filter(Boolean).join(' · ');
                const selectionSummary = document.getElementById('filterSelectionSummary');
                const mobileSummary = document.getElementById('filter-summary-badge');
                if (selectionSummary) selectionSummary.textContent = conciseSummary;
                if (mobileSummary) mobileSummary.textContent = conciseSummary;
            }

            function classifyUnit(label) {
                const normalized = String(label || '').trim().toUpperCase();
                if (/^(KC|KCP)\b/.test(normalized)) return 'ritel';
                if (/^UNIT\b/.test(normalized) || normalized.includes('-- UNIT')) return 'micro';
                return 'all';
            }

            function rebuildKancaOptions() {
                if (!kancaInput) return;
                const initialValue = activeKancas.size === 1 ? Array.from(activeKancas)[0] : 'all';
                kancaInput.value = Array.from(kancaInput.options).some(option => option.value === initialValue)
                    ? initialValue
                    : 'all';
                rebuildUnitOptions(selectedUnitInitial || 'all');
            }

            function rebuildUnitOptions(preferredValue = 'all') {
                if (!unitInput) return;
                const hasSingleBranch = activeKancas.size === 1;
                const currentValue = preferredValue || unitInput.value || 'all';
                const unitOptions = [{ value: 'all', label: hasSingleBranch ? 'Semua Unit Kerja' : 'Pilih satu cabang lebih dulu', unitKind: 'all' }];

                if (hasSingleBranch) {
                    allUnitsData.forEach(unit => {
                        if (unit.value !== 'all' && activeKancas.has(unit.kanca_value)) {
                            unitOptions.push({ ...unit, unitKind: classifyUnit(unit.label) });
                        }
                    });
                }

                unitInput.innerHTML = '';
                unitOptions.forEach(unit => {
                    const option = new Option(unit.label, unit.value, false, unit.value === currentValue);
                    option.dataset.unitKind = unit.unitKind;
                    unitInput.add(option);
                });
                unitInput.disabled = !hasSingleBranch;
                if (!unitOptions.some(unit => unit.value === currentValue)) unitInput.value = 'all';

                const unitHint = document.getElementById('unitFilterHint');
                if (unitHint) {
                    unitHint.textContent = hasSingleBranch
                        ? 'Pilih unit spesifik atau seluruh unit cabang.'
                        : 'Pilih satu cabang untuk membuka daftar unit.';
                }
                syncSegmentOptions(false);
                updateTimeseriesFilterSummary();
            }

            if (kancaInput) {
                kancaInput.addEventListener('change', () => {
                    activeKancas.clear();
                    if (kancaInput.value === 'all') {
                        allKancasData
                            .filter(kanca => kanca.value !== 'all')
                            .forEach(kanca => activeKancas.add(kanca.value));
                    } else {
                        activeKancas.add(kancaInput.value);
                    }
                    rebuildUnitOptions('all');
                    markFiltersPending();
                });
            }

            if (unitInput) {
                unitInput.addEventListener('change', () => {
                    syncSegmentOptions(true);
                    markFiltersPending();
                });
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
                        segment: currentSegment,
                        product: currentProduct,
                        unit_kerja: unit,
                        period_month: periodMonth
                    });
                    if (currentCategory === 'recovery') {
                        queryParams.set('recovery_segment', currentRecoverySegment);
                        queryParams.set('recovery_product', currentRecoveryProduct);
                    }
                    selectedKanca.forEach(k => queryParams.append('kanca[]', k));

                    const response = await fetch(`${routes.data}?${queryParams.toString()}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json();
                    if (requestId !== activeRequestId) return;

                    if (!hasTimeseriesData(data)) {
                        renderEmptyChart();
                        markFiltersApplied();
                        return;
                    }

                    renderCharts(data, selectedKanca.length);
                    markFiltersApplied();
                } catch (error) {
                    console.error('Failed to fetch timeseries data:', error);
                    renderEmptyChart();
                    if (filterReadyState) {
                        filterReadyState.classList.add('is-pending');
                        filterReadyState.innerHTML = '<i class="fas fa-exclamation-circle" aria-hidden="true"></i> Gagal memuat data';
                    }
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
                { border: '#7c3aed', bg: 'rgba(124, 58, 237, 0.035)' },
                { border: '#d97706', bg: 'rgba(217, 119, 6, 0.035)' },
                { border: '#059669', bg: 'rgba(5, 150, 105, 0.035)' },
                { border: '#0857c3', bg: 'rgba(8, 87, 195, 0.055)' },
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

            function createChartConfig(title, months, datasets, isSummary = false, valueType = 'currency') {
                const yAxisBounds = resolveYAxisBounds(datasets, isSummary);
                const isPercent = valueType === 'percent';
                const isMillion = valueType === 'currency_million';

                return {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 31}, (_, i) => i + 1),
                        datasets: datasets.map((d, i) => {
                            const isLatest = i === datasets.length - 1;
                            const observedPointCount = Array.isArray(d.data)
                                ? d.data.filter(value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))).length
                                : 0;
                            const showSparsePoint = observedPointCount <= 1;

                            return {
                                label: d.label,
                                data: d.data,
                                borderColor: monthColors[i % monthColors.length].border,
                                backgroundColor: monthColors[i % monthColors.length].bg,
                                borderWidth: isLatest ? 2.25 : 1.35,
                                pointRadius: showSparsePoint ? 3 : (isLatest ? 2.25 : 0),
                                pointHoverRadius: isLatest ? 5 : 3,
                                pointBorderWidth: (isLatest || showSparsePoint) ? 1.5 : 0,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: monthColors[i % monthColors.length].border,
                                tension: 0.32,
                                fill: isLatest,
                                clip: false,
                                // Snapshot periods are not always imported every calendar day.
                                // Connect observed points visually without manufacturing values for missing dates.
                                spanGaps: true,
                                borderDash: isLatest ? [] : [4, 4],
                                borderCapStyle: 'round',
                                borderJoinStyle: 'round'
                            };
                        })
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        devicePixelRatio: 2.5,
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
                                    pointStyle: 'line',
                                    boxWidth: 28,
                                    boxHeight: 8,
                                    color: '#475569',
                                    padding: 14,
                                    font: { weight: '600', size: 10 }
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
                                            const formattedValue = new Intl.NumberFormat('id-ID', {
                                                maximumFractionDigits: isPercent ? 2 : 0
                                            }).format(context.parsed.y);
                                            label += isPercent
                                                ? formattedValue + '%'
                                                : (isMillion ? 'Rp ' + formattedValue + ' Juta' : formattedValue + ' Rp M');
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
                                    text: isPercent
                                        ? 'Persentase (%)'
                                        : (isMillion ? 'Value (Rp Juta)' : 'Value (Rp Miliar)'),
                                    color: '#475569',
                                    font: { weight: '600', size: 10 }
                                },
                                grid: {
                                    color: 'rgba(15, 23, 42, 0.055)',
                                    drawTicks: true
                                },
                                ticks: {
                                    maxTicksLimit: isSummary ? 7 : 6,
                                    padding: 10,
                                    display: true,
                                    color: '#64748b',
                                    font: { size: 10, weight: '500' },
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
                                    color: '#64748b',
                                    font: { size: 10, weight: '500' },
                                    autoSkip: true,
                                    maxTicksLimit: isSummary ? 16 : 12
                                },
                                title: {
                                    display: isSummary,
                                    text: 'Tanggal',
                                    color: '#64748b',
                                    padding: { top: 10 },
                                    font: { size: 10, weight: '600' }
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
                const valueType = data.value_type || 'currency';

                const summaryContainer = document.getElementById('summaryChartContainer');
                if (summaryContainer && summaryContainer.style.display !== 'none') {
                    const summaryCanvas = document.getElementById('summaryChart');
                    if (!summaryCanvas) return;

                    const summaryCtx = summaryCanvas.getContext('2d');
                    const summaryDatasets = data.months.map(month => ({
                        label: getMonthName(month),
                        data: data.area_total[month] || new Array(31).fill(null)
                    }));
                    charts['summary'] = new Chart(summaryCtx, createChartConfig('Total Area', data.months, summaryDatasets, true, valueType));
                    
                    const badge = document.getElementById('summaryChartBadge');
                    if (badge) {
                        badge.textContent = selectedCount === 0 ? 'Total Konsolidasi Area 6' : 'Total Konsolidasi (4 Cabang Dipilih)';
                    }

                    // Dynamically update the summary chart title
                    const titleEl = document.getElementById('summaryChartTitle');
                    if (titleEl) {
                        let label = 'Area 6 - ' + selectedOptionLabel(categoryInput, 'Konsolidasi');
                        const segmentLabel = currentSegmentLabel();
                        if (segmentLabel) {
                            label += ' (' + segmentLabel + ')';
                        }
                        const productLabel = currentProductLabel();
                        if (productLabel) label += ' / ' + productLabel;
                        titleEl.innerHTML = `<i class="fas fa-chart-area mr-2 text-primary"></i>${escapeHtml(label)}`;
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
                    const unitSuffix = (unitInput && unitInput.value !== 'all') ? selectedOptionLabel(unitInput) : 'Konsolidasi';

                    // Resolve selected segment label
                    let segLabel = '';
                    const segmentLabel = currentSegmentLabel();
                    if (segmentLabel) {
                        segLabel = ' (' + segmentLabel + ')';
                    }
                    const productLabel = currentProductLabel();
                    if (productLabel) {
                        segLabel = segLabel
                            ? segLabel.replace(/\)$/, ` / ${productLabel})`)
                            : ` (${productLabel})`;
                    }
                    if (currentCategory === 'recovery') {
                        const dimensions = [currentRecoverySegment, currentRecoveryProduct].filter(Boolean);
                        if (dimensions.length > 0) segLabel += ' - ' + dimensions.join(' / ');
                    }

                    branchNames.forEach(branch => {
                        const col = document.createElement('div');
                        col.className = isFullWidth ? 'col-12 mb-4' : 'col-lg-6 mb-3';
                        const canvasId = `chart_${branch.replace(/[^\w-]/g, '_')}`;
                        const displayTitle = `${branch} - ${unitSuffix}${segLabel}`;
                        
                        col.innerHTML = `
                            <div class="card chart-card">
                                <div class="chart-header">
                                    <h5 class="chart-title">${escapeHtml(displayTitle)}</h5>
                                    <div class="d-flex align-items-center">
                                        <span class="unit-badge">Daily Trend</span>
                                        <button type="button" class="btn-export-jpg ml-2" title="Export to JPG" aria-label="Export ${escapeHtml(displayTitle)} ke JPG">
                                            <i class="fas fa-camera"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="chart-body branch-chart-body ${isFullWidth ? 'tall' : ''}">
                                    <div class="chart-canvas-frame">
                                        <canvas id="${canvasId}"></canvas>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(col);
                        const exportButton = col.querySelector('.btn-export-jpg');
                        if (exportButton) {
                            exportButton.addEventListener('click', function () {
                                window.downloadTimeseriesChart(branch, 'Timeseries-' + branch.replace(/[^\w-]/g, '_'));
                            });
                        }

                        const ctx = document.getElementById(canvasId).getContext('2d');
                        const datasets = data.months.map(month => ({
                            label: getMonthName(month),
                            data: (data.series[branch] && data.series[branch][month]) ? data.series[branch][month] : new Array(31).fill(null)
                        }));
                        charts[branch] = new Chart(ctx, createChartConfig(branch, data.months, datasets, false, valueType));
                    });
                }
            }

            const allSegmentOption = { value: 'total', label: 'Semua Segmen' };
            const ritelSegmentOption = { value: 'ritel', label: 'Ritel' };
            const microSegmentOption = { value: 'micro', label: 'Micro' };
            const wholesaleSegmentOption = { value: 'wholesale', label: 'Wholesale' };
            const allProductOption = { value: 'total', label: 'Semua Produk' };
            const ritelLoanProducts = [
                allProductOption,
                { value: 'small', label: 'Small / SME' },
                { value: 'kecil', label: 'Kecil' },
                { value: 'kecil_non_cashcoll', label: 'Kecil Non Cash Collateral' },
                { value: 'cashcoll', label: 'Cash Collateral' },
                { value: 'consumer', label: 'Consumer' },
                { value: 'briguna_konsumer', label: 'Briguna' },
                { value: 'kpr', label: 'KPR' },
                { value: 'kkb', label: 'KKB' },
            ];
            const microLoanProducts = [
                allProductOption,
                { value: 'briguna_mikro', label: 'Briguna Mikro' },
                { value: 'kupedes', label: 'Kupedes' },
                { value: 'kur_mikro', label: 'KUR Mikro' },
                { value: 'kur_kecil', label: 'KUR Kecil' },
                { value: 'kur_kpp', label: 'KUR KPP' },
            ];

            const filterConfig = {
                pinjaman: { segments: [allSegmentOption, ritelSegmentOption, microSegmentOption], products: 'loan' },
                simpanan: {
                    segments: [allSegmentOption, ritelSegmentOption, microSegmentOption, wholesaleSegmentOption],
                    products: [allProductOption, { value: 'giro', label: 'Giro' }, { value: 'tabungan', label: 'Tabungan' }, { value: 'deposito', label: 'Deposito' }],
                },
                sml: { segments: [allSegmentOption, ritelSegmentOption, microSegmentOption], products: 'loan' },
                npl: { segments: [allSegmentOption, ritelSegmentOption, microSegmentOption], products: 'loan' },
                simpanan_casa: {
                    segments: [allSegmentOption, ritelSegmentOption, microSegmentOption, wholesaleSegmentOption],
                    products: [allProductOption, { value: 'giro', label: 'Giro' }, { value: 'tabungan', label: 'Tabungan' }],
                },
                ldr: { segments: [allSegmentOption, ritelSegmentOption, microSegmentOption], products: [allProductOption] },
                recovery: { segments: [ritelSegmentOption, microSegmentOption], products: [allProductOption] },
            };

            function segmentOptionsForContext() {
                const config = filterConfig[currentCategory] || filterConfig.simpanan;
                const unitKind = selectedUnitKind();
                if (unitKind === 'micro') return [microSegmentOption];
                if (unitKind === 'ritel') {
                    if (['simpanan', 'simpanan_casa'].includes(currentCategory)) {
                        return [allSegmentOption, ritelSegmentOption, microSegmentOption, wholesaleSegmentOption];
                    }
                    return [ritelSegmentOption, microSegmentOption];
                }
                return config.segments;
            }

            function productOptionsForContext() {
                const config = filterConfig[currentCategory] || filterConfig.simpanan;
                if (config.products !== 'loan') return config.products;
                if (currentSegment === 'ritel') return ritelLoanProducts;
                if (currentSegment === 'micro') return microLoanProducts;
                return [allProductOption, ...ritelLoanProducts.slice(1), ...microLoanProducts.slice(1)];
            }

            function fillSelect(select, options, selectedValue) {
                if (!select) return '';
                select.innerHTML = '';
                options.forEach(item => select.add(new Option(item.label, item.value, false, item.value === selectedValue)));
                const resolved = options.some(item => item.value === selectedValue) ? selectedValue : (options[0]?.value || '');
                select.value = resolved;
                select.disabled = options.length <= 1;
                return resolved;
            }

            function syncProductOptions() {
                const options = productOptionsForContext();
                currentProduct = fillSelect(productInput, options, currentProduct);
                const hint = document.getElementById('productFilterHint');
                if (hint) {
                    hint.textContent = options.length <= 1
                        ? 'Sub item ini ditampilkan sebagai total produk.'
                        : `${options.length - 1} produk tersedia untuk pilihan ini.`;
                }
            }

            function syncSegmentOptions(preferUnitDefault = false) {
                const options = segmentOptionsForContext();
                const unitKind = selectedUnitKind();
                let preferred = currentSegment;
                if (preferUnitDefault && ['ritel', 'micro'].includes(unitKind)) preferred = unitKind;
                currentSegment = fillSelect(segmentInput, options, preferred);
                const hint = document.getElementById('segmentFilterHint');
                if (hint) {
                    hint.textContent = unitKind === 'ritel'
                        ? 'KC/KCP otomatis diarahkan ke Ritel; pilihan lain tetap tersedia.'
                        : (unitKind === 'micro'
                            ? 'BRI Unit otomatis menggunakan segmen Micro.'
                            : 'Pilih segmen untuk mempersempit data.');
                }
                syncProductOptions();
            }

            function normalizeLegacySelection() {
                if (['simpanan', 'simpanan_casa'].includes(currentCategory)
                    && ['giro', 'tabungan', 'deposito'].includes(currentSegment)
                    && currentProduct === 'total') {
                    currentProduct = currentSegment;
                    currentSegment = 'total';
                }
                if (['pinjaman', 'sml', 'npl'].includes(currentCategory)
                    && ['small', 'consumer'].includes(currentSegment)
                    && currentProduct === 'total') {
                    currentProduct = currentSegment;
                    currentSegment = 'ritel';
                }
            }

            function currentSegmentLabel() {
                return selectedOptionLabel(segmentInput, currentSegment);
            }

            function currentProductLabel() {
                return currentProduct === 'total' ? '' : selectedOptionLabel(productInput, currentProduct);
            }

            if (categoryInput) {
                categoryInput.addEventListener('change', function () {
                    currentCategory = this.value;
                    currentSegment = selectedUnitKind() === 'all' && currentCategory === 'recovery' ? 'ritel' : 'total';
                    currentProduct = 'total';
                    syncSegmentOptions(true);
                    markFiltersPending();
                });
            }

            if (segmentInput) {
                segmentInput.addEventListener('change', function () {
                    currentSegment = this.value;
                    currentProduct = 'total';
                    syncProductOptions();
                    markFiltersPending();
                });
            }

            if (productInput) {
                productInput.addEventListener('change', function () {
                    currentProduct = this.value;
                    markFiltersPending();
                });
            }

            if (periodMonthSelect) periodMonthSelect.addEventListener('change', markFiltersPending);
            if (applyBtn) applyBtn.addEventListener('click', fetchData);

            const toggleBtn = document.getElementById('btn-toggle-filters');
            const filterCard = document.querySelector('.filter-card');
            if (toggleBtn && filterCard) {
                toggleBtn.addEventListener('click', function () {
                    filterCard.classList.toggle('is-open');
                    const isOpen = filterCard.classList.contains('is-open');
                    toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    const toggleText = toggleBtn.querySelector('.btn-toggle-text');
                    if (toggleText) {
                        toggleText.innerHTML = isOpen
                            ? '<i class="fas fa-times mr-2"></i> SEMBUNYIKAN'
                            : '<i class="fas fa-sliders-h mr-2"></i> FILTER DATA';
                    }
                });
            }
            // Initial Initialization
            normalizeLegacySelection();
            rebuildKancaOptions();
            updateTimeseriesFilterSummary();
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
