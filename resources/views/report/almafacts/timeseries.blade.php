@extends('layouts.admin')

@section('title', 'Timeseries Almafact')

@section('styles')
<style>
    .dashboard-timeseries {
        --ts-ink: var(--app-ink, #0b1f3a);
        --ts-muted: var(--app-muted, #526987);
        --ts-surface: var(--app-surface, #ffffff);
        --ts-soft: var(--app-surface-soft, #f7faff);
        --ts-line: var(--app-line, #d8e5f7);
        --ts-line-strong: var(--app-line-strong, #b9cfee);
        --ts-accent: #0857c3;
        --ts-accent-soft: rgba(8, 87, 195, 0.09);
        --ts-up: #0f7a4f;
        --ts-up-soft: rgba(15, 122, 79, 0.1);
        --ts-down: #b4232a;
        --ts-down-soft: rgba(180, 35, 42, 0.09);
        --ts-shadow: 0 1px 2px rgba(11, 31, 58, 0.04), 0 12px 28px -22px rgba(8, 62, 140, 0.38);
        --ts-radius: 16px;
        --ts-radius-sm: 10px;
        padding-bottom: 2rem;
        min-width: 0;
        overflow-x: clip;
        color: var(--ts-ink);
    }

    html[data-theme="dark"] .dashboard-timeseries {
        --ts-accent: #5899f3;
        --ts-accent-soft: rgba(88, 153, 243, 0.14);
        --ts-up: #5fd3a0;
        --ts-up-soft: rgba(95, 211, 160, 0.13);
        --ts-down: #ff8a8f;
        --ts-down-soft: rgba(255, 138, 143, 0.12);
        --ts-shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 16px 32px -24px rgba(0, 0, 0, 0.7);
    }

    .dashboard-timeseries *,
    .dashboard-timeseries *::before,
    .dashboard-timeseries *::after {
        box-sizing: border-box;
    }

    /* Header */
    .ts-head {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.25rem;
        padding: 1.2rem 1.4rem;
        margin-bottom: 1rem;
        border-radius: var(--ts-radius);
        background: linear-gradient(135deg, #053b82 0%, #0857c3 55%, #2f7ddf 100%);
        box-shadow: 0 18px 36px -26px rgba(5, 59, 130, 0.75);
        color: #ffffff;
    }

    .ts-head::after {
        content: '';
        position: absolute;
        inset: auto -8% -60% auto;
        width: 340px;
        height: 340px;
        z-index: -1;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.14), transparent 68%);
    }

    .ts-head-copy {
        min-width: 0;
    }

    .ts-eyebrow {
        display: block;
        margin-bottom: 0.3rem;
        color: #9fd3ef;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .timeseries-title {
        margin: 0;
        color: #ffffff;
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        line-height: 1.15;
    }

    .ts-context {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.5rem;
        margin: 0.45rem 0 0;
        color: rgba(255, 255, 255, 0.86);
        font-size: 0.86rem;
        font-weight: 500;
    }

    .ts-context strong {
        color: #ffffff;
        font-weight: 700;
    }

    .ts-context-sep {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
    }

    .ts-head .btn-export-all {
        flex: none;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 40px;
        padding: 0 1rem;
        border-radius: var(--ts-radius-sm);
        border: 1px solid rgba(255, 255, 255, 0.38);
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-size: 0.8rem;
        font-weight: 700;
        transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    }

    .ts-head .btn-export-all:hover {
        background: rgba(255, 255, 255, 0.22);
        border-color: rgba(255, 255, 255, 0.7);
        color: #ffffff;
    }

    .ts-head .btn-export-all:active {
        transform: translateY(1px);
    }

    .ts-head .btn-export-all:focus-visible {
        outline: 2px solid #ffffff;
        outline-offset: 2px;
    }

    /* Filter bar */
    .filter-card {
        position: relative;
        z-index: 100;
        margin-bottom: 1rem;
        padding: 0.9rem 1rem 1rem;
        background: var(--ts-surface);
        border: 1px solid var(--ts-line);
        border-radius: var(--ts-radius);
        box-shadow: var(--ts-shadow);
    }

    .ts-filter-grid {
        display: grid;
        grid-template-columns: minmax(170px, 1fr) minmax(190px, 1.15fr) minmax(250px, 1.75fr) minmax(120px, 0.6fr);
        gap: 0.75rem;
        align-items: end;
    }

    .ts-field {
        min-width: 0;
    }

    .filter-label {
        display: block;
        margin-bottom: 0.38rem;
        color: var(--ts-muted);
        font-size: 0.74rem;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .branch-filter-dropdown,
    .metric-dropdown-shell {
        position: relative;
        width: 100%;
    }

    .branch-dropdown-toggle,
    .metric-dropdown-toggle {
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0 0.85rem;
        background: var(--ts-soft);
        border: 1px solid var(--ts-line);
        border-radius: var(--ts-radius-sm);
        color: var(--ts-ink);
        text-align: left;
        cursor: pointer;
        transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    }

    .branch-dropdown-toggle:hover,
    .metric-dropdown-toggle:hover {
        border-color: var(--ts-line-strong);
        background: var(--ts-surface);
    }

    .branch-dropdown-toggle[aria-expanded="true"],
    .metric-dropdown-toggle[aria-expanded="true"] {
        border-color: var(--ts-accent);
        background: var(--ts-surface);
        box-shadow: 0 0 0 3px var(--ts-accent-soft);
    }

    .branch-dropdown-toggle:focus-visible,
    .metric-dropdown-toggle:focus-visible {
        outline: none;
        border-color: var(--ts-accent);
        box-shadow: 0 0 0 3px var(--ts-accent-soft);
    }

    .ts-field-icon {
        flex: none;
        width: 16px;
        color: var(--ts-accent);
        font-size: 0.82rem;
        text-align: center;
    }

    .branch-dropdown-label,
    .metric-dropdown-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .ts-caret {
        flex: none;
        color: var(--ts-muted);
        font-size: 0.7rem;
        transition: transform 0.18s ease;
    }

    [aria-expanded="true"] > .ts-caret {
        transform: rotate(180deg);
    }

    .branch-dropdown-toggle:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .branch-dropdown-menu,
    .metric-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        z-index: 1050;
        display: none;
        min-width: 100%;
        max-width: min(440px, calc(100vw - 2rem));
        overflow: hidden;
        background: var(--ts-surface);
        border: 1px solid var(--ts-line);
        border-radius: 12px;
        box-shadow: 0 24px 48px -24px rgba(8, 40, 90, 0.45);
        animation: tsMenuIn 0.16s ease-out;
    }

    .metric-dropdown-menu {
        width: max(100%, 360px);
    }

    .branch-dropdown-menu.show,
    .metric-dropdown-menu.show {
        display: block;
    }

    .metric-dropdown-search {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.65rem 0.8rem;
        border-bottom: 1px solid var(--ts-line);
        background: var(--ts-soft);
        color: var(--ts-muted);
    }

    .metric-dropdown-search input {
        width: 100%;
        border: 0;
        outline: none;
        background: transparent;
        color: var(--ts-ink);
        font-size: 0.86rem;
        font-weight: 500;
    }

    .options-container,
    .metric-options {
        max-height: 320px;
        overflow-y: auto;
        padding: 0.35rem;
        overscroll-behavior: contain;
    }

    .metric-group-title {
        padding: 0.6rem 0.6rem 0.3rem;
        color: var(--ts-muted);
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .metric-empty {
        display: none;
        padding: 1rem 0.75rem;
        color: var(--ts-muted);
        font-size: 0.84rem;
        text-align: center;
    }

    .branch-option,
    .metric-option {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.52rem 0.6rem;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--ts-ink);
        font-size: 0.86rem;
        font-weight: 500;
        text-align: left;
        cursor: pointer;
        user-select: none;
        transition: background 0.14s ease, color 0.14s ease;
    }

    .branch-option:hover,
    .metric-option:hover,
    .branch-option:focus-visible,
    .metric-option:focus-visible {
        outline: none;
        background: var(--ts-accent-soft);
    }

    .branch-option.selected,
    .metric-option.selected {
        color: var(--ts-accent);
        font-weight: 700;
    }

    .branch-checkbox-ui,
    .metric-option-check {
        flex: none;
        width: 16px;
        display: flex;
        justify-content: center;
        color: var(--ts-accent);
        font-size: 0.72rem;
        visibility: hidden;
    }

    .branch-option.selected .branch-checkbox-ui,
    .metric-option.selected .metric-option-check {
        visibility: visible;
    }

    .branch-option-label {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .metric-option-num {
        flex: none;
        min-width: 1.6rem;
        color: var(--ts-muted);
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    .metric-option.selected .metric-option-num {
        color: var(--ts-accent);
    }

    @keyframes tsMenuIn {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* KPI strip */
    .ts-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .ts-kpi {
        min-width: 0;
        padding: 0.85rem 1rem;
        background: var(--ts-surface);
        border: 1px solid var(--ts-line);
        border-radius: 14px;
        box-shadow: var(--ts-shadow);
    }

    .ts-kpi-label {
        display: block;
        color: var(--ts-muted);
        font-size: 0.74rem;
        font-weight: 600;
    }

    .ts-kpi-value {
        display: block;
        margin-top: 0.3rem;
        color: var(--ts-ink);
        font-size: 1.32rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ts-kpi-value small {
        margin-left: 0.2rem;
        color: var(--ts-muted);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .ts-kpi-note {
        display: block;
        margin-top: 0.3rem;
        color: var(--ts-muted);
        font-size: 0.74rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ts-kpi[data-tone="up"] .ts-kpi-value { color: var(--ts-up); }
    .ts-kpi[data-tone="down"] .ts-kpi-value { color: var(--ts-down); }

    .ts-kpi.is-loading .ts-kpi-value {
        color: transparent;
        border-radius: 6px;
        background: linear-gradient(90deg, var(--ts-soft), var(--ts-line), var(--ts-soft));
        background-size: 200% 100%;
        animation: tsShimmer 1.1s linear infinite;
    }

    @keyframes tsShimmer {
        from { background-position: 100% 0; }
        to { background-position: -100% 0; }
    }

    /* Charts */
    #timeseriesCaptureArea {
        background: transparent;
    }

    .ts-chart-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .ts-chart-grid > .is-full {
        grid-column: 1 / -1;
    }

    .chart-card {
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: var(--ts-surface);
        border: 1px solid var(--ts-line);
        border-radius: var(--ts-radius);
        box-shadow: var(--ts-shadow);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .chart-card:hover {
        border-color: var(--ts-line-strong);
    }

    .summary-chart-card {
        margin-bottom: 0.75rem;
    }

    .chart-header {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1rem 0.35rem;
    }

    .chart-heading {
        min-width: 0;
    }

    .chart-title {
        margin: 0;
        color: var(--ts-ink);
        font-size: 0.98rem;
        font-weight: 700;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .chart-subtitle {
        display: block;
        margin-top: 0.12rem;
        color: var(--ts-muted);
        font-size: 0.76rem;
    }

    .chart-meta {
        flex: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-stat {
        color: var(--ts-ink);
        font-size: 0.95rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ts-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.16rem 0.48rem;
        border-radius: 6px;
        background: var(--ts-accent-soft);
        color: var(--ts-accent);
        font-size: 0.72rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ts-chip[data-tone="up"] { background: var(--ts-up-soft); color: var(--ts-up); }
    .ts-chip[data-tone="down"] { background: var(--ts-down-soft); color: var(--ts-down); }

    .unit-badge {
        max-width: 100%;
        padding: 0.18rem 0.5rem;
        border-radius: 6px;
        background: var(--ts-soft);
        border: 1px solid var(--ts-line);
        color: var(--ts-muted);
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chart-body {
        position: relative;
        flex: 1 1 auto;
        padding: 0.25rem 0.85rem 0.85rem 0.6rem;
        overflow: hidden;
    }

    .chart-canvas-frame {
        position: relative;
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
    }

    .chart-canvas-frame canvas {
        display: block !important;
        width: 100% !important;
        height: 100% !important;
    }

    .summary-chart-body {
        height: 340px;
    }

    .branch-chart-body {
        height: 250px;
    }

    .branch-chart-body.tall {
        height: 360px;
    }

    .loading-spinner {
        width: 18px;
        height: 18px;
        border: 2px solid var(--ts-line);
        border-top-color: var(--ts-accent);
        border-radius: 50%;
        animation: tsSpin 0.8s linear infinite;
    }

    .ts-loading {
        display: none;
        align-items: center;
        gap: 0.4rem;
        color: var(--ts-muted);
        font-size: 0.74rem;
        font-weight: 600;
    }

    @keyframes tsSpin {
        to { transform: rotate(360deg); }
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 3rem 1.5rem;
        border: 1px dashed var(--ts-line-strong);
        border-radius: var(--ts-radius);
        background: var(--ts-surface);
        color: var(--ts-muted);
        text-align: center;
    }

    .empty-state i {
        margin-bottom: 0.4rem;
        color: var(--ts-accent);
        font-size: 1.8rem;
        opacity: 0.55;
    }

    .empty-state h4 {
        margin: 0;
        color: var(--ts-ink);
        font-size: 1rem;
        font-weight: 700;
    }

    .empty-state p {
        margin: 0;
        font-size: 0.86rem;
    }

    /* Capture status modal */
    .capture-status-modal .modal-content {
        overflow: hidden;
        border: none;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(4, 42, 95, 0.25);
    }

    .capture-status-modal .modal-body {
        padding: 2.5rem 2rem;
    }

    .capture-status-modal-icon {
        width: 72px;
        height: 72px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.25rem;
        border-radius: 50%;
        font-size: 2.1rem;
    }

    .icon-loading { background: rgba(8, 87, 195, 0.1); color: #0857c3; }
    .icon-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

    .capture-status-modal .btn-primary {
        padding: 0.55rem 1.4rem;
        border-radius: 10px;
        font-weight: 600;
    }

    .modal-backdrop.show {
        background-color: rgba(15, 23, 42, 0.18);
    }

    @media (prefers-reduced-motion: reduce) {
        .dashboard-timeseries *,
        .dashboard-timeseries *::before,
        .dashboard-timeseries *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }
    }

    @media (max-width: 1180px) {
        .ts-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ts-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .summary-chart-body {
            height: 300px;
        }

        .branch-chart-body {
            height: 240px;
        }
    }

    @media (max-width: 767.98px) {
        .ts-head {
            flex-direction: column;
            align-items: stretch;
            padding: 1rem;
        }

        .timeseries-title {
            font-size: 1.25rem;
        }

        .ts-head .btn-export-all {
            justify-content: center;
        }

        .ts-chart-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .chart-header {
            flex-wrap: wrap;
        }

        .summary-chart-body {
            height: 270px;
        }

        .branch-chart-body,
        .branch-chart-body.tall {
            height: 230px;
        }
    }

    @media (max-width: 575.98px) {
        .ts-filter-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .filter-card {
            padding: 0.8rem;
        }

        .ts-kpi {
            padding: 0.7rem 0.8rem;
        }

        .ts-kpi-value {
            font-size: 1.08rem;
        }

        .metric-dropdown-menu {
            width: 100%;
        }

        .chart-body {
            padding: 0.2rem 0.4rem 0.6rem 0.2rem;
        }
    }
</style>
@endsection

@section('content')
@php
    // Shortcuts pinned at the top of the keterangan list.
    $primaryMetrics = [
        '15. Laba Setelah Pajak',
        '13. Laba Sebelum Pajak',
        '10. PPOP',
        '01. Pendapatan Bunga',
        '04. Beban Bunga',
        '09. Overhead Cost',
    ];
    $metricGroups = [
        'Utama' => array_values(array_filter($primaryMetrics, fn ($metric) => in_array($metric, $metrics, true))),
        'Laba rugi & volume' => [],
        'Rasio (%)' => [],
    ];
    foreach ($metrics as $metric) {
        if (in_array($metric, $primaryMetrics, true)) {
            continue;
        }
        $metricGroups[str_contains((string) $metric, '(%)') ? 'Rasio (%)' : 'Laba rugi & volume'][] = $metric;
    }
    $splitMetric = static function (string $metric): array {
        return preg_match('/^(\d+)\.\s*(.+)$/', $metric, $parts) === 1 ? [$parts[1], $parts[2]] : ['', $metric];
    };
    $selectedUnitLabel = 'Semua Unit Kerja';
    foreach ($units as $unitOption) {
        if ($unitOption['value'] === $selectedUnit && $unitOption['kanca_value'] === $selectedBranch) {
            $selectedUnitLabel = $unitOption['label'];
        }
    }
@endphp
<div class="dashboard-timeseries">
    <header class="ts-head">
        <div class="ts-head-copy">
            <span class="ts-eyebrow">Dashboard Almafacts</span>
            <h1 class="timeseries-title">Timeseries Almafact</h1>
            <p class="ts-context" id="tsContext" aria-live="polite">
                <strong data-ts-context="metric">{{ $selectedMetric }}</strong>
                <span class="ts-context-sep" aria-hidden="true"></span>
                <span data-ts-context="scope">{{ $selectedBranchLabel }}{{ $selectedUnit !== 'all' ? ' · ' . $selectedUnitLabel : '' }}</span>
                <span class="ts-context-sep" aria-hidden="true"></span>
                <span data-ts-context="year">Tahun {{ $selectedYear }}</span>
            </p>
        </div>
        <button id="captureAllBtn" type="button" class="btn-export-all">
            <i class="fas fa-file-image" aria-hidden="true"></i> Export A4
        </button>
    </header>

    <section class="filter-card" aria-label="Filter timeseries">
        <div class="ts-filter-grid">
            <div class="ts-field">
                <span class="filter-label" id="kancaFieldLabel">Kantor cabang</span>
                <div class="branch-filter-dropdown" id="kancaDropdownShell">
                    <button type="button" class="branch-dropdown-toggle" id="kancaDropdown" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="kancaFieldLabel kancaLabel">
                        <i class="fas fa-building ts-field-icon" aria-hidden="true"></i>
                        <span class="branch-dropdown-label" id="kancaLabel">{{ $selectedBranchLabel }}</span>
                        <i class="fas fa-chevron-down ts-caret" aria-hidden="true"></i>
                    </button>
                    <div class="branch-dropdown-menu" id="kancaMenu">
                        <input type="hidden" id="kancaInput" value="{{ $selectedBranch }}">
                        <div class="options-container" id="kancaOptionsList" role="listbox" aria-labelledby="kancaFieldLabel">
                            @foreach($branchOptions as $val => $lbl)
                                <button type="button" role="option" class="branch-option {{ $selectedBranch === $val ? 'selected' : '' }}" aria-selected="{{ $selectedBranch === $val ? 'true' : 'false' }}" data-value="{{ $val }}" data-label="{{ $lbl }}">
                                    <span class="branch-checkbox-ui"><i class="fas fa-check"></i></span>
                                    <span class="branch-option-label">{{ $lbl }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="ts-field">
                <span class="filter-label" id="unitFieldLabel">Unit kerja</span>
                <div class="branch-filter-dropdown" id="unitDropdownShell">
                    <button type="button" class="branch-dropdown-toggle" id="unitDropdown" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="unitFieldLabel unitLabel">
                        <i class="fas fa-store ts-field-icon" aria-hidden="true"></i>
                        <span class="branch-dropdown-label" id="unitLabel">{{ $selectedUnitLabel }}</span>
                        <i class="fas fa-chevron-down ts-caret" aria-hidden="true"></i>
                    </button>
                    <div class="branch-dropdown-menu" id="unitMenu">
                        <input type="hidden" id="unitInput" value="{{ $selectedUnit }}">
                        <div class="options-container" id="unitOptions" role="listbox" aria-labelledby="unitFieldLabel">
                            {{-- Populated by JS from the selected kantor cabang --}}
                        </div>
                    </div>
                </div>
            </div>

            <div class="ts-field">
                <span class="filter-label" id="metricFieldLabel">Keterangan</span>
                <div class="metric-dropdown-shell" id="metricDropdownShell">
                    <button type="button" class="metric-dropdown-toggle" id="metricDropdownToggle" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="metricFieldLabel metricDropdownLabel">
                        <i class="fas fa-list-ul ts-field-icon" aria-hidden="true"></i>
                        <span class="metric-dropdown-label" id="metricDropdownLabel">{{ $selectedMetric }}</span>
                        <i class="fas fa-chevron-down ts-caret" aria-hidden="true"></i>
                    </button>
                    <div class="metric-dropdown-menu" id="metricDropdownMenu">
                        <label class="metric-dropdown-search">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <span class="sr-only">Cari keterangan</span>
                            <input type="search" id="metricDropdownSearch" placeholder="Cari keterangan, mis. NIM atau laba" autocomplete="off">
                        </label>
                        <div class="metric-options" id="metricOptionsList" role="listbox" aria-labelledby="metricFieldLabel">
                            @foreach($metricGroups as $groupLabel => $groupMetrics)
                                @if($groupMetrics !== [])
                                    <div class="metric-group" data-metric-group>
                                        <div class="metric-group-title">{{ $groupLabel }}</div>
                                        @foreach($groupMetrics as $met)
                                            @php [$metricNumber, $metricName] = $splitMetric((string) $met); @endphp
                                            <button type="button" role="option" class="metric-option {{ $selectedMetric === $met ? 'selected' : '' }}" aria-selected="{{ $selectedMetric === $met ? 'true' : 'false' }}" data-value="{{ $met }}" data-label="{{ $met }}">
                                                <span class="metric-option-check"><i class="fas fa-check"></i></span>
                                                <span class="metric-option-num">{{ $metricNumber }}</span>
                                                <span>{{ $metricName }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                            <div class="metric-empty" id="metricEmpty">Keterangan tidak ditemukan.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ts-field">
                <span class="filter-label" id="yearFieldLabel">Tahun</span>
                <div class="branch-filter-dropdown" id="yearDropdownShell">
                    <button type="button" class="branch-dropdown-toggle" id="yearDropdown" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="yearFieldLabel yearLabel">
                        <i class="fas fa-calendar-alt ts-field-icon" aria-hidden="true"></i>
                        <span class="branch-dropdown-label" id="yearLabel">{{ $selectedYear }}</span>
                        <i class="fas fa-chevron-down ts-caret" aria-hidden="true"></i>
                    </button>
                    <div class="branch-dropdown-menu" id="yearMenu">
                        <input type="hidden" id="yearInput" value="{{ $selectedYear }}">
                        <div class="options-container" id="yearOptions" role="listbox" aria-labelledby="yearFieldLabel">
                            @foreach($years as $yr)
                                <button type="button" role="option" class="branch-option {{ $selectedYear === (int) $yr ? 'selected' : '' }}" aria-selected="{{ $selectedYear === (int) $yr ? 'true' : 'false' }}" data-value="{{ $yr }}" data-label="{{ $yr }}">
                                    <span class="branch-checkbox-ui"><i class="fas fa-check"></i></span>
                                    <span class="branch-option-label">{{ $yr }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ts-kpis" id="tsKpis" aria-label="Ringkasan">
        <div class="ts-kpi" data-kpi="latest">
            <span class="ts-kpi-label" data-kpi-label>Realisasi terakhir</span>
            <span class="ts-kpi-value" data-kpi-value>–</span>
            <span class="ts-kpi-note" data-kpi-note>&nbsp;</span>
        </div>
        <div class="ts-kpi" data-kpi="rka">
            <span class="ts-kpi-label" data-kpi-label>Capaian RKA</span>
            <span class="ts-kpi-value" data-kpi-value>–</span>
            <span class="ts-kpi-note" data-kpi-note>&nbsp;</span>
        </div>
        <div class="ts-kpi" data-kpi="yoy">
            <span class="ts-kpi-label" data-kpi-label>Dibanding tahun lalu</span>
            <span class="ts-kpi-value" data-kpi-value>–</span>
            <span class="ts-kpi-note" data-kpi-note>&nbsp;</span>
        </div>
        <div class="ts-kpi" data-kpi="mom">
            <span class="ts-kpi-label" data-kpi-label>Perubahan dari bulan sebelumnya</span>
            <span class="ts-kpi-value" data-kpi-value>–</span>
            <span class="ts-kpi-note" data-kpi-note>&nbsp;</span>
        </div>
    </section>

    <div id="timeseriesCaptureArea">
        <div class="card chart-card summary-chart-card" id="summaryChartContainer">
            <div class="chart-header">
                <div class="chart-heading">
                    <h5 class="chart-title" id="summaryChartTitle">{{ $selectedMetric }}</h5>
                    <span class="chart-subtitle" id="summaryChartSubtitle">Tren bulanan realisasi dan tahun sebelumnya</span>
                </div>
                <div class="chart-meta">
                    <span class="ts-loading" id="summaryLoading" role="status"><span class="loading-spinner" aria-hidden="true"></span>Memuat</span>
                    <span class="unit-badge" id="summaryChartBadge">Konsolidasi Area 6</span>
                </div>
            </div>
            <div class="chart-body summary-chart-body">
                <div class="chart-canvas-frame">
                    <canvas id="summaryChart" role="img" aria-label="Grafik tren bulanan"></canvas>
                </div>
            </div>
        </div>

        <div class="ts-chart-grid" id="individualChartsContainer">
            {{-- Populated by JS: one card per cabang or unit kerja --}}
        </div>
    </div>

    <!-- Capture Status Modal -->
    <div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div id="captureProgressUI">
                        <div class="capture-status-modal-icon icon-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <h4 class="font-weight-bold text-dark mb-2">Menyusun Laporan A4</h4>
                        <p class="text-muted mb-0">Sedang merender grafik resolusi tinggi dan merakit lembar A4...</p>
                    </div>
                    <div id="captureErrorUI" class="d-none">
                        <div class="capture-status-modal-icon icon-error">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="font-weight-bold text-danger mb-2">Gagal Ekspor</h4>
                        <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kendala saat menyusun snapshot A4.</p>
                        <button type="button" class="btn btn-primary" data-dismiss="modal">Tutup</button>
                    </div>
                    <div id="captureSuccessUI" class="d-none">
                        <div class="capture-status-modal-icon icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="font-weight-bold text-success mb-2">Laporan Siap</h4>
                        <p class="text-muted mb-4">Laporan timeseries A4 berhasil diekspor dan diunduh.</p>
                        <button type="button" class="btn btn-primary" data-dismiss="modal">Tutup</button>
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
        const initialTimeseriesData = @json($initialData);
        const allUnitsData = @json($units);
        const branchOptionsMap = @json($branchOptions);

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        let charts = {};
        let activeRequestId = 0;
        let chartResizeFrame = null;

        // Active State Selection
        let currentMetric = @json($selectedMetric);
        let currentBranch = @json($selectedBranch);
        let currentUnit = @json($selectedUnit);
        let currentYear = @json($selectedYear);

        const routes = {
            data: "{{ route('report.dashboard-almafacts.timeseries.data') }}"
        };

        function init() {
            // --- Filter DOM ---
            const kancaToggle = document.getElementById('kancaDropdown');
            const kancaMenu = document.getElementById('kancaMenu');
            const kancaLabel = document.getElementById('kancaLabel');
            const kancaInput = document.getElementById('kancaInput');
            const kancaOptions = document.getElementById('kancaOptionsList');

            const unitToggle = document.getElementById('unitDropdown');
            const unitMenu = document.getElementById('unitMenu');
            const unitLabel = document.getElementById('unitLabel');
            const unitInput = document.getElementById('unitInput');
            const unitOptionsContainer = document.getElementById('unitOptions');

            const yearToggle = document.getElementById('yearDropdown');
            const yearMenu = document.getElementById('yearMenu');
            const yearLabel = document.getElementById('yearLabel');
            const yearInput = document.getElementById('yearInput');
            const yearOptions = document.getElementById('yearOptions');

            const metricToggle = document.getElementById('metricDropdownToggle');
            const metricMenu = document.getElementById('metricDropdownMenu');
            const metricLabel = document.getElementById('metricDropdownLabel');
            const metricSearch = document.getElementById('metricDropdownSearch');
            const metricOptionsList = document.getElementById('metricOptionsList');
            const metricEmpty = document.getElementById('metricEmpty');

            const summaryLoading = document.getElementById('summaryLoading');
            const kpiStrip = document.getElementById('tsKpis');
            const pageRoot = document.querySelector('.dashboard-timeseries');
            let lastRenderedData = null;

            const dropdowns = [
                { toggle: kancaToggle, menu: kancaMenu },
                { toggle: unitToggle, menu: unitMenu },
                { toggle: metricToggle, menu: metricMenu, onOpen: openMetricMenu },
                { toggle: yearToggle, menu: yearMenu },
            ].filter(entry => entry.toggle && entry.menu);

            function closeAllDropdowns() {
                dropdowns.forEach(({ toggle, menu }) => {
                    menu.classList.remove('show');
                    toggle.setAttribute('aria-expanded', 'false');
                });
            }

            dropdowns.forEach(entry => {
                entry.menu.addEventListener('click', event => event.stopPropagation());
                entry.toggle.addEventListener('click', event => {
                    event.stopPropagation();
                    if (entry.toggle.disabled) return;
                    const wasOpen = entry.menu.classList.contains('show');
                    closeAllDropdowns();
                    if (wasOpen) return;
                    entry.menu.classList.add('show');
                    entry.toggle.setAttribute('aria-expanded', 'true');
                    if (entry.onOpen) {
                        entry.onOpen();
                    } else {
                        const selected = entry.menu.querySelector('.selected') || entry.menu.querySelector('[role="option"]');
                        if (selected) selected.focus({ preventScroll: true });
                    }
                });
            });

            document.addEventListener('click', closeAllDropdowns);
            document.addEventListener('keydown', event => {
                if (event.key !== 'Escape') return;
                const open = dropdowns.find(entry => entry.menu.classList.contains('show'));
                if (!open) return;
                closeAllDropdowns();
                open.toggle.focus();
            });

            // Arrow keys move between options inside an open list.
            dropdowns.forEach(({ menu }) => {
                menu.addEventListener('keydown', event => {
                    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
                    const options = Array.from(menu.querySelectorAll('[role="option"]')).filter(opt => opt.offsetParent !== null);
                    if (options.length === 0) return;
                    event.preventDefault();
                    const index = options.indexOf(document.activeElement);
                    const next = event.key === 'ArrowDown'
                        ? options[Math.min(options.length - 1, index + 1)]
                        : (index <= 0 ? (metricSearch && menu === metricMenu ? metricSearch : options[0]) : options[index - 1]);
                    next.focus();
                });
            });

            function markSelected(container, value) {
                if (!container) return;
                container.querySelectorAll('[role="option"]').forEach(opt => {
                    const selected = opt.getAttribute('data-value') === String(value);
                    opt.classList.toggle('selected', selected);
                    opt.setAttribute('aria-selected', selected ? 'true' : 'false');
                });
            }

            // --- Kantor cabang ---
            if (kancaOptions) {
                kancaOptions.querySelectorAll('.branch-option').forEach(opt => {
                    opt.addEventListener('click', () => {
                        const val = opt.getAttribute('data-value');
                        closeAllDropdowns();
                        kancaToggle.focus();
                        if (val === currentBranch) return;
                        kancaInput.value = val;
                        kancaLabel.textContent = opt.getAttribute('data-label');
                        markSelected(kancaOptions, val);
                        currentBranch = val;
                        rebuildUnitOptions();
                        fetchData();
                    });
                });
            }

            // --- Keterangan ---
            function filterMetricOptions(term) {
                if (!metricOptionsList) return;
                const needle = String(term || '').trim().toLowerCase();
                let visible = 0;
                metricOptionsList.querySelectorAll('[data-metric-group]').forEach(group => {
                    let groupVisible = 0;
                    group.querySelectorAll('.metric-option').forEach(opt => {
                        const match = !needle || (opt.getAttribute('data-label') || '').toLowerCase().includes(needle);
                        opt.hidden = !match;
                        groupVisible += match ? 1 : 0;
                    });
                    group.hidden = groupVisible === 0;
                    visible += groupVisible;
                });
                if (metricEmpty) metricEmpty.style.display = visible === 0 ? 'block' : 'none';
            }

            function openMetricMenu() {
                if (!metricSearch) return;
                metricSearch.value = '';
                filterMetricOptions('');
                const selected = metricOptionsList ? metricOptionsList.querySelector('.metric-option.selected') : null;
                if (selected) selected.scrollIntoView({ block: 'nearest' });
                setTimeout(() => metricSearch.focus(), 0);
            }

            function selectMetric(value) {
                closeAllDropdowns();
                metricToggle.focus();
                if (value === currentMetric) return;
                currentMetric = value;
                if (metricLabel) metricLabel.textContent = value;
                markSelected(metricOptionsList, value);
                fetchData();
            }

            if (metricSearch) {
                metricSearch.addEventListener('input', () => filterMetricOptions(metricSearch.value));
                metricSearch.addEventListener('keydown', event => {
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        const first = Array.from(metricOptionsList.querySelectorAll('.metric-option')).find(opt => !opt.hidden);
                        if (first) first.focus();
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        const first = Array.from(metricOptionsList.querySelectorAll('.metric-option')).find(opt => !opt.hidden && opt.offsetParent !== null);
                        if (first) selectMetric(first.getAttribute('data-value'));
                    }
                });
            }

            if (metricOptionsList) {
                metricOptionsList.querySelectorAll('.metric-option').forEach(opt => {
                    opt.addEventListener('click', () => selectMetric(opt.getAttribute('data-value')));
                });
            }

            // --- Tahun ---
            if (yearOptions) {
                yearOptions.querySelectorAll('.branch-option').forEach(opt => {
                    opt.addEventListener('click', () => {
                        const val = opt.getAttribute('data-value');
                        closeAllDropdowns();
                        yearToggle.focus();
                        if (Number(val) === Number(currentYear)) return;
                        yearInput.value = val;
                        yearLabel.textContent = opt.getAttribute('data-label');
                        markSelected(yearOptions, val);
                        currentYear = Number(val);
                        fetchData();
                    });
                });
            }

            // --- Unit kerja (depends on kantor cabang) ---
            function rebuildUnitOptions() {
                if (!unitOptionsContainer) return;

                const selectedBranch = kancaInput ? kancaInput.value : 'area6';
                const branchUnits = selectedBranch === 'area6'
                    ? []
                    : allUnitsData.filter(unit => unit.kanca_value === selectedBranch);
                const options = [{ value: 'all', label: 'Semua Unit Kerja' }].concat(branchUnits);
                const current = options.find(option => option.value === (unitInput ? unitInput.value : 'all')) || options[0];

                unitOptionsContainer.innerHTML = '';
                options.forEach(option => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'branch-option';
                    button.setAttribute('role', 'option');
                    button.setAttribute('data-value', option.value);
                    button.setAttribute('data-label', option.label);
                    button.innerHTML = `<span class="branch-checkbox-ui"><i class="fas fa-check"></i></span><span class="branch-option-label">${escapeHtml(option.label)}</span>`;
                    button.addEventListener('click', () => {
                        closeAllDropdowns();
                        unitToggle.focus();
                        if (option.value === currentUnit) return;
                        selectUnit(option.value, option.label);
                        fetchData();
                    });
                    unitOptionsContainer.appendChild(button);
                });

                selectUnit(current.value, current.label);
                if (unitToggle) {
                    unitToggle.disabled = branchUnits.length === 0;
                    unitToggle.title = branchUnits.length === 0 ? 'Pilih kantor cabang untuk melihat unit kerja' : '';
                }
            }

            function selectUnit(value, label) {
                if (unitInput) unitInput.value = value;
                if (unitLabel) unitLabel.textContent = label;
                currentUnit = value;
                markSelected(unitOptionsContainer, value);
            }

            // --- Labels & formatting ---
            function isPercentMetric() {
                return /\(%\)/.test(String(currentMetric));
            }

            function branchDisplayLabel() {
                return (kancaLabel && kancaLabel.textContent.trim()) || branchOptionsMap[currentBranch] || currentBranch;
            }

            function scopeDisplayLabel() {
                if (currentUnit && currentUnit !== 'all') {
                    return `${branchDisplayLabel()} · ${unitLabel ? unitLabel.textContent.trim() : currentUnit}`;
                }
                return branchDisplayLabel();
            }

            function updateContext() {
                const set = (key, text) => {
                    const el = document.querySelector(`[data-ts-context="${key}"]`);
                    if (el) el.textContent = text;
                };
                set('metric', currentMetric);
                set('scope', scopeDisplayLabel());
                set('year', `Tahun ${currentYear}`);
            }

            function syncUrl() {
                if (!window.history || !window.history.replaceState) return;
                const params = new URLSearchParams(window.location.search);
                params.set('metric', currentMetric);
                params.set('cabang', currentBranch);
                params.set('unit_kerja', currentUnit);
                params.set('year', String(currentYear));
                window.history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`);
            }

            const numberFormat = (value, digits) => new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits,
            }).format(value);

            function formatAmount(value) {
                if (!Number.isFinite(value)) return '–';
                if (isPercentMetric()) return `${numberFormat(value, 2)}%`;
                const abs = Math.abs(value);
                const sign = value < 0 ? '-' : '';
                // Source values are in Rp juta.
                if (abs >= 1000000) return `${sign}Rp${numberFormat(abs / 1000000, 2)} T`;
                if (abs >= 1000) return `${sign}Rp${numberFormat(abs / 1000, 2)} M`;
                return `${sign}Rp${numberFormat(abs, 0)} Jt`;
            }

            function formatSignedAmount(value) {
                if (!Number.isFinite(value)) return '–';
                if (isPercentMetric()) return `${value > 0 ? '+' : ''}${numberFormat(value, 2)} pp`;
                return `${value > 0 ? '+' : ''}${formatAmount(value)}`;
            }

            function formatPercent(value, signed = false) {
                if (!Number.isFinite(value)) return '–';
                return `${signed && value > 0 ? '+' : ''}${numberFormat(value, 1)}%`;
            }

            function toneOf(value) {
                if (!Number.isFinite(value) || Math.abs(value) < 1e-9) return '';
                return value > 0 ? 'up' : 'down';
            }

            // --- Series helpers (Realisasi / RKA / tahun lalu) ---
            function datasetRole(label) {
                if (String(label).includes('RKA')) return 'rka';
                if (String(label).includes(String(currentYear - 1))) return 'prev';
                return 'current';
            }

            function seriesSnapshot(labels, datasets) {
                const byRole = { current: null, rka: null, prev: null };
                (datasets || []).forEach(dataset => {
                    const role = datasetRole(dataset.label);
                    if (!byRole[role]) byRole[role] = dataset;
                });
                const values = role => (byRole[role] && Array.isArray(byRole[role].data) ? byRole[role].data : [])
                    .map(value => (value === null || value === '' ? NaN : Number(value)));
                const current = values('current');
                let last = -1;
                current.forEach((value, index) => { if (Number.isFinite(value)) last = index; });
                // Compare with the latest earlier month that has data (a month can be missing at the source).
                let previous = -1;
                for (let index = last - 1; index >= 0; index--) {
                    if (Number.isFinite(current[index])) { previous = index; break; }
                }

                return {
                    last,
                    month: last >= 0 ? (labels[last] || '') : '',
                    prevMonth: previous >= 0 ? (labels[previous] || '') : '',
                    current: last >= 0 ? current[last] : NaN,
                    previousMonth: previous >= 0 ? current[previous] : NaN,
                    rka: last >= 0 ? values('rka')[last] : NaN,
                    lastYear: last >= 0 ? values('prev')[last] : NaN,
                    hasRka: Boolean(byRole.rka),
                };
            }

            function setKpi(key, value, note, tone = '') {
                if (!kpiStrip) return;
                const card = kpiStrip.querySelector(`[data-kpi="${key}"]`);
                if (!card) return;
                card.querySelector('[data-kpi-value]').textContent = value;
                card.querySelector('[data-kpi-note]').textContent = note || ' ';
                if (tone) card.setAttribute('data-tone', tone); else card.removeAttribute('data-tone');
            }

            function renderKpis(data) {
                const snap = seriesSnapshot(data.labels || [], data.summary ? data.summary.datasets : []);
                if (snap.last < 0) {
                    resetKpis('Belum ada realisasi');
                    return;
                }

                const position = `Posisi ${snap.month} ${currentYear}`;
                setKpi('latest', formatAmount(snap.current), position);

                if (snap.hasRka && Number.isFinite(snap.rka) && snap.rka > 0) {
                    const achievement = (snap.current / snap.rka) * 100;
                    setKpi('rka', formatPercent(achievement), `Target ${formatAmount(snap.rka)}`, achievement >= 100 ? 'up' : 'down');
                } else {
                    setKpi('rka', '–', snap.hasRka ? 'Target RKA belum tersedia' : 'RKA hanya untuk Laba Setelah Pajak');
                }

                if (Number.isFinite(snap.lastYear)) {
                    if (isPercentMetric()) {
                        const delta = snap.current - snap.lastYear;
                        setKpi('yoy', formatSignedAmount(delta), `${snap.month} ${currentYear - 1}: ${formatAmount(snap.lastYear)}`, toneOf(delta));
                    } else if (snap.lastYear !== 0) {
                        const growth = ((snap.current - snap.lastYear) / Math.abs(snap.lastYear)) * 100;
                        setKpi('yoy', formatPercent(growth, true), `${snap.month} ${currentYear - 1}: ${formatAmount(snap.lastYear)}`, toneOf(growth));
                    } else {
                        setKpi('yoy', '–', `${snap.month} ${currentYear - 1}: ${formatAmount(snap.lastYear)}`);
                    }
                } else {
                    setKpi('yoy', '–', `Data ${currentYear - 1} tidak tersedia`);
                }

                if (Number.isFinite(snap.previousMonth)) {
                    const delta = snap.current - snap.previousMonth;
                    setKpi('mom', formatSignedAmount(delta), `${snap.prevMonth} → ${snap.month}`, toneOf(delta));
                } else {
                    setKpi('mom', '–', 'Belum ada bulan pembanding');
                }
            }

            function resetKpis(note = ' ') {
                ['latest', 'rka', 'yoy', 'mom'].forEach(key => setKpi(key, '–', note));
            }

            // --- Data ---
            async function fetchData() {
                const requestId = ++activeRequestId;
                updateContext();
                syncUrl();
                setLoadingState(true);

                try {
                    const queryParams = new URLSearchParams({
                        metric: currentMetric,
                        cabang: kancaInput ? kancaInput.value : 'area6',
                        unit_kerja: unitInput ? unitInput.value : 'all',
                        year: yearInput ? yearInput.value : String(currentYear),
                    });

                    const response = await fetch(`${routes.data}?${queryParams.toString()}`, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const data = await response.json();
                    if (requestId !== activeRequestId) return;

                    if (!hasTimeseriesData(data)) {
                        renderEmptyChart('Tidak ada data untuk filter terpilih', 'Coba keterangan, kantor cabang, atau tahun lain.');
                        return;
                    }

                    renderCharts(data);
                } catch (error) {
                    if (requestId !== activeRequestId) return;
                    console.error('Failed to fetch timeseries data:', error);
                    renderEmptyChart('Data gagal dimuat', 'Koneksi ke server terputus atau sedang sibuk. Pilih ulang filter untuk mencoba lagi.');
                } finally {
                    if (requestId === activeRequestId) {
                        setLoadingState(false);
                    }
                }
            }

            function setLoadingState(isLoading) {
                if (summaryLoading) summaryLoading.style.display = isLoading ? 'inline-flex' : 'none';
                if (kpiStrip) {
                    kpiStrip.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                    kpiStrip.querySelectorAll('.ts-kpi').forEach(card => card.classList.toggle('is-loading', isLoading));
                }
            }

            function hasTimeseriesData(data) {
                return Boolean(data && data.summary && Array.isArray(data.summary.datasets) && data.summary.datasets.length > 0);
            }

            function renderEmptyChart(title = 'Tidak ada data untuk filter terpilih', message = 'Coba keterangan, kantor cabang, atau tahun lain.') {
                Object.values(charts).forEach(c => { try { c.destroy(); } catch (e) {} });
                charts = {};
                lastRenderedData = null;
                resetKpis();
                const summaryCanvas = document.getElementById('summaryChart');
                if (summaryCanvas) {
                    summaryCanvas.getContext('2d').clearRect(0, 0, summaryCanvas.width, summaryCanvas.height);
                }
                const container = document.getElementById('individualChartsContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="is-full">
                            <div class="empty-state">
                                <i class="fas fa-chart-line" aria-hidden="true"></i>
                                <h4>${escapeHtml(title)}</h4>
                                <p>${escapeHtml(message)}</p>
                            </div>
                        </div>
                    `;
                }
            }

            // --- Chart styling ---
            const monthColors = [
                { border: '#0857c3', bg: 'rgba(8, 87, 195, 0.12)' }, // Selected year
                { border: '#d97706', bg: 'rgba(217, 119, 6, 0.04)' }, // RKA
                { border: '#7b8aa0', bg: 'rgba(123, 138, 160, 0.03)' }, // Previous year
            ];

            function themeColors() {
                const styles = getComputedStyle(pageRoot || document.documentElement);
                const read = (name, fallback) => (styles.getPropertyValue(name) || '').trim() || fallback;
                const dark = document.documentElement.getAttribute('data-theme') === 'dark';
                return {
                    ink: read('--ts-ink', '#0b1f3a'),
                    muted: read('--ts-muted', '#526987'),
                    line: read('--ts-line', '#d8e5f7'),
                    surface: read('--ts-surface', '#ffffff'),
                    current: dark ? '#5899f3' : '#0857c3',
                    currentRgb: dark ? '88, 153, 243' : '8, 87, 195',
                    rka: dark ? '#f5a524' : '#d97706',
                    prev: dark ? '#93a3b8' : '#7b8aa0',
                    grid: dark ? 'rgba(226, 236, 247, 0.07)' : 'rgba(11, 31, 58, 0.06)',
                };
            }

            function niceStep(range, intervals) {
                const raw = range / Math.max(1, intervals);
                if (!Number.isFinite(raw) || raw <= 0) return 1;
                const magnitude = Math.pow(10, Math.floor(Math.log10(raw)));
                const normalized = raw / magnitude;
                const nice = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 2.5 ? 2.5 : normalized <= 5 ? 5 : 10;
                return nice * magnitude;
            }

            // Round the y-axis to readable steps instead of raw min/max values.
            function resolveYAxisBounds(datasets, isSummary = false, intervals = 5) {
                const values = datasets
                    .flatMap(dataset => Array.isArray(dataset.data) ? dataset.data : [])
                    .filter(value => value !== null && value !== undefined && value !== '')
                    .map(value => Number(value))
                    .filter(value => Number.isFinite(value));

                if (values.length === 0) {
                    return {};
                }

                let min = Math.min(...values);
                let max = Math.max(...values);
                if (max - min < Math.max(Math.abs(max), 1) * 0.02) {
                    const pad = Math.max(Math.abs(max) * 0.05, isPercentMetric() ? 0.5 : 5);
                    min -= pad;
                    max += pad;
                }

                const step = niceStep(max - min, intervals);
                return {
                    min: Math.floor(min / step) * step,
                    max: Math.ceil(max / step) * step,
                    stepSize: step,
                };
            }

            function tickFormatter(stepSize) {
                const step = Number(stepSize) || 1;
                const digits = step >= 1 ? 0 : (step >= 0.1 ? 1 : 2);
                return value => isPercentMetric()
                    ? `${numberFormat(value, digits)}%`
                    : numberFormat(value, 0);
            }

            function createChartConfig(title, labels, datasets, isSummary = false) {
                const isPhone = window.matchMedia('(max-width: 575.98px)').matches;
                const isCompact = window.matchMedia('(max-width: 767.98px)').matches;
                const intervals = isCompact ? 4 : (isSummary ? 6 : 4);
                const yAxisBounds = resolveYAxisBounds(datasets, isSummary, intervals);
                const colors = themeColors();
                const tickSize = isPhone ? 9 : (isCompact ? 10 : 11);
                const latestBorderWidth = isPhone ? 2.2 : (isSummary ? 3 : 2.5);
                const otherBorderWidth = isPhone ? 1.4 : 1.8;
                const percent = isPercentMetric();

                return {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: datasets.map(d => {
                            const role = datasetRole(d.label);
                            const isLatest = role === 'current';
                            const color = isLatest ? colors.current : (role === 'rka' ? colors.rka : colors.prev);

                            return {
                                type: 'line',
                                label: d.label,
                                data: d.data,
                                yAxisID: 'y',
                                borderColor: color,
                                backgroundColor: isLatest
                                    ? (context => {
                                        const area = context.chart.chartArea;
                                        if (!area) return `rgba(${colors.currentRgb}, 0.1)`;
                                        const gradient = context.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                                        gradient.addColorStop(0, `rgba(${colors.currentRgb}, 0.2)`);
                                        gradient.addColorStop(1, `rgba(${colors.currentRgb}, 0)`);
                                        return gradient;
                                    })
                                    : 'transparent',
                                borderWidth: isLatest ? latestBorderWidth : otherBorderWidth,
                                pointRadius: isLatest ? (isPhone ? 2.2 : 3) : 0,
                                pointHoverRadius: isLatest ? 5 : 4,
                                pointBackgroundColor: colors.surface,
                                pointBorderColor: color,
                                pointBorderWidth: 2,
                                tension: 0.3,
                                fill: isLatest ? 'start' : false,
                                clip: false,
                                spanGaps: true,
                                borderDash: role === 'rka' ? [6, 5] : (role === 'prev' ? [2, 4] : []),
                                borderCapStyle: 'round',
                                borderJoinStyle: 'round',
                                order: isLatest ? 1 : 2,
                            };
                        })
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        devicePixelRatio: isPhone ? 1.75 : 2,
                        animation: { duration: 450 },
                        layout: { padding: { top: 4, right: isCompact ? 6 : 12, bottom: 0, left: 0 } },
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'end',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'line',
                                    boxWidth: isCompact ? 18 : 24,
                                    boxHeight: 6,
                                    color: colors.muted,
                                    padding: isCompact ? 8 : 14,
                                    font: { weight: '600', size: tickSize }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(11, 31, 58, 0.94)',
                                padding: 10,
                                cornerRadius: 8,
                                titleFont: { size: 12, weight: '700' },
                                bodyFont: { size: 12 },
                                boxPadding: 4,
                                callbacks: {
                                    label: function(context) {
                                        const label = context.dataset.label ? `${context.dataset.label}: ` : '';
                                        if (context.parsed.y === null) return label + '–';
                                        return label + (percent
                                            ? `${numberFormat(context.parsed.y, 2)}%`
                                            : `${numberFormat(context.parsed.y, 2)} Rp Juta`);
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
                                border: { display: false },
                                title: {
                                    display: !isPhone,
                                    text: percent ? 'Persen (%)' : 'Nilai (Rp Juta)',
                                    color: colors.muted,
                                    font: { weight: '600', size: tickSize }
                                },
                                grid: { color: colors.grid, drawTicks: false },
                                ticks: {
                                    stepSize: yAxisBounds.stepSize,
                                    padding: 8,
                                    color: colors.muted,
                                    font: { size: tickSize, weight: '500' },
                                    callback: tickFormatter(yAxisBounds.stepSize)
                                }
                            },
                            x: {
                                display: true,
                                border: { display: true, color: colors.line },
                                grid: { display: false },
                                ticks: {
                                    padding: 6,
                                    color: colors.muted,
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: isPhone ? 6 : 12,
                                    font: { size: tickSize, weight: '500' }
                                }
                            }
                        }
                    }
                };
            }

            function renderCharts(data) {
                Object.values(charts).forEach(c => { try { c.destroy(); } catch (e) {} });
                charts = {};
                lastRenderedData = data;

                updateContext();
                renderKpis(data);

                const summaryCanvas = document.getElementById('summaryChart');
                if (summaryCanvas) {
                    charts['summary'] = new Chart(summaryCanvas.getContext('2d'), createChartConfig('Total Analytics', data.labels, data.summary.datasets, true));

                    const badge = document.getElementById('summaryChartBadge');
                    if (badge) {
                        badge.textContent = currentBranch === 'area6'
                            ? 'Konsolidasi Area 6'
                            : (currentUnit === 'all' ? `Konsolidasi ${branchDisplayLabel()}` : scopeDisplayLabel());
                    }

                    const titleEl = document.getElementById('summaryChartTitle');
                    if (titleEl) titleEl.textContent = currentMetric;
                    const subtitleEl = document.getElementById('summaryChartSubtitle');
                    if (subtitleEl) {
                        const hasRka = (data.summary.datasets || []).some(dataset => datasetRole(dataset.label) === 'rka');
                        subtitleEl.textContent = hasRka
                            ? 'Tren bulanan realisasi, RKA, dan tahun sebelumnya'
                            : 'Tren bulanan realisasi dan tahun sebelumnya';
                    }
                }

                const container = document.getElementById('individualChartsContainer');
                if (container) {
                    container.innerHTML = '';
                    const seriesKeys = Object.keys(data.series || {}).sort();
                    const isFullWidth = seriesKeys.length === 1;

                    seriesKeys.forEach(key => {
                        const datasets = data.series[key].datasets;
                        const snap = seriesSnapshot(data.labels || [], datasets);
                        let chip = '';
                        if (snap.last >= 0 && Number.isFinite(snap.lastYear)) {
                            const delta = isPercentMetric()
                                ? snap.current - snap.lastYear
                                : (snap.lastYear !== 0 ? ((snap.current - snap.lastYear) / Math.abs(snap.lastYear)) * 100 : NaN);
                            if (Number.isFinite(delta)) {
                                const text = isPercentMetric() ? formatSignedAmount(delta) : formatPercent(delta, true);
                                chip = `<span class="ts-chip" data-tone="${toneOf(delta)}" title="Dibanding ${escapeHtml(snap.month)} ${currentYear - 1}">${escapeHtml(text)} YoY</span>`;
                            }
                        }

                        const col = document.createElement('div');
                        col.className = isFullWidth ? 'is-full' : '';
                        const canvasId = `chart_${key.replace(/[^\w-]/g, '_')}`;
                        col.innerHTML = `
                            <div class="card chart-card">
                                <div class="chart-header">
                                    <div class="chart-heading">
                                        <h5 class="chart-title">${escapeHtml(key)}</h5>
                                        <span class="chart-subtitle">${snap.last >= 0 ? `Posisi ${escapeHtml(snap.month)} ${currentYear}` : 'Belum ada realisasi'}</span>
                                    </div>
                                    <div class="chart-meta">
                                        <span class="chart-stat">${escapeHtml(snap.last >= 0 ? formatAmount(snap.current) : '–')}</span>
                                        ${chip}
                                        <span class="unit-badge sr-only">Trend Bulanan</span>
                                    </div>
                                </div>
                                <div class="chart-body branch-chart-body ${isFullWidth ? 'tall' : ''}">
                                    <div class="chart-canvas-frame">
                                        <canvas id="${canvasId}" role="img" aria-label="Grafik tren bulanan ${escapeHtml(key)}"></canvas>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(col);

                        const ctx = document.getElementById(canvasId).getContext('2d');
                        charts[key] = new Chart(ctx, createChartConfig(key, data.labels, datasets, false));
                    });
                }

                scheduleChartResize();
            }

            function resizeVisibleCharts() {
                chartResizeFrame = null;
                Object.values(charts).forEach(chart => {
                    try {
                        chart.resize();
                    } catch (error) {}
                });
            }

            function scheduleChartResize() {
                if (chartResizeFrame !== null) {
                    return;
                }

                chartResizeFrame = window.requestAnimationFrame(resizeVisibleCharts);
            }

            window.addEventListener('resize', scheduleChartResize);
            window.addEventListener('orientationchange', scheduleChartResize);

            // Re-draw with the new palette when the theme toggles.
            new MutationObserver(() => {
                if (lastRenderedData) renderCharts(lastRenderedData);
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

            // --- Capture A4 Page Layout Composer ---
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
                            badge: card.querySelector('.unit-badge')?.textContent?.trim() || 'Trend Bulanan',
                        };
                    })
                    .filter(Boolean);
            }

            function cloneChartDatasets(chart, isCompact = false) {
                return chart.data.datasets.map((dataset, index) => {
                    const isRka = dataset.label.includes('RKA');
                    const isPrev = dataset.label.includes(String(currentYear - 1));
                    const isLatest = !isRka && !isPrev;

                    return {
                        type: 'line',
                        label: dataset.label,
                        data: Array.isArray(dataset.data) ? dataset.data.slice() : dataset.data,
                        yAxisID: 'y',
                        borderColor: isLatest ? monthColors[0].border : (isRka ? monthColors[1].border : monthColors[2].border),
                        backgroundColor: isLatest ? monthColors[0].bg : (isRka ? monthColors[1].bg : monthColors[2].bg),
                        borderWidth: isCompact ? (isLatest ? 2.6 : 1.6) : (isLatest ? 3.25 : 2),
                        pointRadius: isCompact ? (isLatest ? 2 : 1.2) : (isLatest ? 4 : 2.5),
                        tension: dataset.tension ?? 0.28,
                        fill: isLatest,
                        spanGaps: true,
                        borderDash: isRka ? [5, 5] : (isPrev ? [3, 3] : []),
                        borderCapStyle: 'round',
                        borderJoinStyle: 'round'
                    };
                });
            }

            function buildExportChartOptions(chart, isCompact = false) {
                const originalOptions = chart.options || {};
                const originalScales = originalOptions.scales || {};
                const yTicksCallback = originalScales.y?.ticks?.callback;
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
                        }
                    },
                    scales: {
                        y: {
                            display: true,
                            beginAtZero: false,
                            min: originalScales.y?.min,
                            max: originalScales.y?.max,
                            border: { display: true, color: '#cbd5e1' },
                            title: {
                                display: true,
                                text: originalScales.y?.title?.text || 'Nilai (Rp Juta)',
                                color: '#334155',
                                font: { weight: '600', size: Math.round(23 * fontScale) }
                            },
                            grid: { color: 'rgba(15, 23, 42, 0.06)', drawTicks: true },
                            ticks: {
                                maxTicksLimit: isCompact ? 5 : 7,
                                padding: isCompact ? 8 : 18,
                                display: true,
                                color: '#475569',
                                font: { size: Math.round(22 * fontScale), weight: '500' },
                                stepSize: originalScales.y?.ticks?.stepSize,
                                callback: yTicksCallback
                            }
                        },
                        x: {
                            display: true,
                            border: { display: true, color: '#cbd5e1' },
                            grid: { display: false, drawTicks: true },
                            ticks: {
                                display: true,
                                padding: isCompact ? 8 : 18,
                                color: '#475569',
                                font: { size: Math.round(22 * fontScale), weight: '500' }
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
                const metric = currentMetric;
                const branch = branchOptionsMap[currentBranch] || currentBranch;
                const unitName = currentUnit === 'all' ? 'Semua Unit' : (allUnitsData.find(u => u.value === currentUnit)?.label || currentUnit);

                ctx.fillStyle = '#0857c3';
                ctx.fillRect(0, 0, width, 24);

                ctx.fillStyle = '#0f172a';
                ctx.font = 'bold 64px "Inter", "Segoe UI", Arial, sans-serif';
                ctx.fillText('Timeseries Almafact', marginX, marginY + 35);

                ctx.fillStyle = '#475569';
                ctx.font = '600 30px "Inter", "Segoe UI", Arial, sans-serif';
                drawTextEllipsis(ctx, `Keterangan: ${metric}   |   Tahun: ${currentYear}`, marginX, marginY + 92, width - (marginX * 2));
                drawTextEllipsis(ctx, `Cabang: ${branch}   |   Unit: ${unitName}`, marginX, marginY + 138, width - (marginX * 2) - 220);

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

                        const category = sanitizeFilePart(currentMetric);
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
                        link.download = `Timeseries-Almafact-A4-${category}-${timestamp}.jpg`;
                        link.href = pageCanvas.toDataURL('image/jpeg', 0.95);
                        link.click();

                        progressUI.classList.add('d-none');
                        successUI.classList.remove('d-none');
                    } catch (err) {
                        console.error('Stitching failure:', err);
                        progressUI.classList.add('d-none');
                        errorUI.classList.add('d-none');
                        successUI.classList.add('d-none');
                        errorUI.classList.remove('d-none');
                        errorMessageUI.textContent = 'Gagal menyusun laporan A4. Pastikan seluruh grafik sudah muncul sempurna dan coba lagi.';
                    } finally {
                        captureBtn.disabled = false;
                        captureBtn.innerHTML = originalBtnHtml;
                    }
                });
            }

            // Initial view state setup
            rebuildUnitOptions();
            if (!window.Chart) {
                console.error('Chart.js belum termuat untuk Timeseries Almafact.');
                renderEmptyChart();
                setLoadingState(false);
            } else if (hasTimeseriesData(initialTimeseriesData)) {
                renderCharts(initialTimeseriesData);
                setLoadingState(false);
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
