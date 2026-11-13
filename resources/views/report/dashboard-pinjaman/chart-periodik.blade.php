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
    /* Modern Corporate Flat Aesthetic (Excel-Inspired) Styling */
    .loan-dashboard .card.loan-shell,
    .loan-dashboard .chart-card {
        border-radius: 0px !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03) !important;
        background: #ffffff !important;
        overflow: visible !important;
        position: relative;
        z-index: 5;
    }

    .loan-dashboard .card.loan-shell::before,
    .loan-dashboard .chart-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px !important;
        background: var(--loan-blue) !important; /* Pristine solid corporate blue line */
        z-index: 10;
    }

    .loan-dashboard .card.loan-shell {
        z-index: 3000;
    }

    .loan-dashboard .card.loan-shell .card-body,
    .loan-dashboard .loan-filter-toolbar,
    .loan-dashboard .loan-filter-item,
    .loan-dashboard .loan-dropdown-shell {
        position: relative;
    }

    .loan-dashboard .card.loan-shell .card-body {
        z-index: 3010;
        overflow: visible !important;
    }

    .loan-dashboard .loan-filter-toolbar {
        z-index: 3020;
    }

    .loan-dashboard .loan-filter-item {
        z-index: 3030;
    }

    .loan-dashboard .loan-dropdown-shell {
        z-index: 3040;
    }

    .loan-dashboard .loan-dropdown-menu {
        z-index: 3050;
    }

    .loan-filter-label {
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #042a5f;
        margin-bottom: 0.35rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .loan-filter-label i {
        color: var(--loan-blue);
        font-size: 0.75rem;
    }

    /* Dropdowns */
    .loan-dropdown-toggle {
        border-radius: 0px !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: none !important;
        min-height: 40px !important;
        height: 40px !important;
        padding: 0.35rem 0.75rem !important;
        font-size: 0.82rem !important;
        font-weight: 700 !important;
        background: #ffffff !important;
        color: var(--loan-blue-ink);
        text-align: left;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        width: 100%;
    }

    .loan-dropdown-toggle:hover,
    .loan-dropdown-toggle:focus {
        border-color: var(--loan-blue) !important;
        background: #f8fbff !important;
        box-shadow: none !important;
        outline: none;
    }

    .loan-dropdown-toggle[disabled] {
        background: #eef2f7 !important;
        border-color: #cbd5e1 !important;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .loan-dropdown-menu {
        border-radius: 0px !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08) !important;
        padding: 0.25rem !important;
        background: #ffffff;
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        z-index: 2000;
        display: none;
        max-height: 350px;
        overflow-y: auto;
    }

    .loan-dropdown-menu.show {
        display: block;
    }

    .loan-dropdown-item {
        border-radius: 0px !important;
        padding: 0.5rem 0.75rem !important;
        font-size: 0.8rem !important;
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .loan-dropdown-item:hover {
        background: #eff6ff !important;
    }

    .loan-dropdown-item.active {
        background: #dbeafe !important;
        color: #1e40af !important;
        font-weight: 700 !important;
    }

    .loan-dropdown-item.active .form-check-label {
        color: #1e40af !important;
        font-weight: 700 !important;
    }

    .loan-dropdown-item .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 0;
        cursor: pointer;
        width: 100%;
    }

    .loan-dropdown-item input[type="checkbox"] {
        width: 1rem;
        height: 1rem;
        cursor: pointer;
        accent-color: var(--loan-blue);
        border: 1px solid #cbd5e1;
    }

    .loan-dropdown-item .form-check-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        user-select: none;
    }

    /* Flat Action Buttons */
    .btn-flat-primary {
        border-radius: 0px !important;
        min-height: 40px !important;
        height: 40px !important;
        padding: 0 1.25rem !important;
        background-color: var(--loan-blue) !important;
        border-color: var(--loan-blue) !important;
        color: #ffffff !important;
        font-size: 0.82rem !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.5rem !important;
        transition: all 0.15s ease !important;
        border: 1px solid var(--loan-blue) !important;
        cursor: pointer;
    }

    .btn-flat-primary:hover,
    .btn-flat-primary:focus {
        background-color: var(--loan-blue-deep) !important;
        border-color: var(--loan-blue-deep) !important;
        box-shadow: none !important;
        color: #ffffff !important;
        text-decoration: none !important;
    }

    .btn-flat-primary:disabled {
        background-color: #cbd5e1 !important;
        border-color: #cbd5e1 !important;
        color: #94a3b8 !important;
        cursor: not-allowed !important;
    }

    .btn-flat-secondary {
        border-radius: 0px !important;
        min-height: 40px !important;
        height: 40px !important;
        padding: 0 1rem !important;
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        color: #475569 !important;
        font-size: 0.82rem !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.15s ease !important;
        text-decoration: none !important;
    }

    .btn-flat-secondary:hover,
    .btn-flat-secondary:focus {
        background-color: #f8fafc !important;
        color: #1e293b !important;
        border-color: #94a3b8 !important;
        text-decoration: none !important;
    }

    /* KPI Overrides */
    .chart-periodik-kpi {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0px !important;
        box-shadow: none !important;
        padding: 1rem 1.25rem !important;
        min-height: 112px !important;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: border-color 0.15s ease, background 0.15s ease !important;
        position: relative;
    }

    .chart-periodik-kpi:hover {
        transform: none !important;
        border-color: var(--loan-blue) !important;
        background: #f8fbff !important;
        box-shadow: none !important;
    }

    .chart-periodik-kpi__label {
        font-size: 0.68rem !important;
        font-weight: 800 !important;
        color: #475569 !important;
        text-transform: uppercase;
        letter-spacing: 0.05em !important;
        margin-bottom: 0.25rem;
    }

    .chart-periodik-kpi__value {
        font-size: 1.55rem !important;
        font-weight: 800 !important;
        color: #042a5f !important;
        font-variant-numeric: tabular-nums;
        line-height: 1.2 !important;
        margin-bottom: 0.25rem !important;
    }

    .chart-periodik-kpi__hint {
        font-size: 0.7rem !important;
        color: #64748b !important;
    }

    .chart-periodik-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 20px;
    }

    /* Chart Cards and Canvas */
    .chart-card__header {
        padding: 1rem 1.25rem !important;
        border-bottom: 1px solid #cbd5e1 !important;
        background: #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }

    .chart-card__title {
        font-size: 0.92rem !important;
        font-weight: 800 !important;
        color: #042a5f !important;
        margin-bottom: 0 !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .chart-card__badge {
        background: #e0f2fe !important;
        color: #0369a1 !important;
        padding: 0.3rem 0.6rem !important;
        border-radius: 0px !important;
        border: 1px solid #bae6fd !important;
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.35rem !important;
        white-space: nowrap;
    }

    .chart-card__body {
        padding: 1.25rem !important;
        flex: 1;
        position: relative;
    }

    .chart-canvas-wrap {
        position: relative;
        height: 320px;
        width: 100%;
    }

    .cp-chart-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: 1fr; gap: 20px; margin-bottom: 28px; align-items: stretch; }
    .cp-dashboard .loan-title-hero__wrap { width: 100%; min-width: 0; }
    .cp-dashboard .loan-title-hero__desc { display: block !important; -webkit-line-clamp: unset !important; max-width: 780px; margin: 8px auto 0; white-space: normal; overflow: visible; text-overflow: clip; line-height: 1.7; }
    .cp-chart-column { min-width: 0; display: flex; }
    .cp-chart-row .chart-card { width: 100%; min-width: 0; margin: 0; display: flex; flex-direction: column; }
    .cp-chart-row .chart-card__header { min-height: 68px; flex-shrink: 0; }
    .cp-chart-row .chart-card__body { display: flex; flex-direction: column; min-width: 0; }
    .cp-chart-row .chart-canvas-wrap { flex: 1; min-height: 280px; height: 300px; }
    .cp-composition-groups { display: grid; gap: 18px; min-width: 0; }
    .cp-composition-group { min-width: 0; padding-top: 16px; border-top: 1px solid #dce7f3; }
    .cp-composition-group:first-child { padding-top: 0; border-top: 0; }
    .cp-composition-group header { display: flex; align-items: start; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
    .cp-composition-group h3 { font-size: 13px; font-weight: 750; color: #153e6d; margin: 0 0 3px; }
    .cp-composition-group header p { font-size: 10px; color: #60758f; margin: 0; }
    .cp-composition-total { font-size: 17px; font-weight: 750; color: #145fba; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cp-composition-total small { font-size: 10px; font-weight: 500; color: #60758f; margin-left: 5px; }
    .cp-composition-group .cp-bar-list { gap: 6px; }
    .cp-composition-group .cp-bar-row { grid-template-columns: minmax(116px, 1fr) minmax(32px, 1fr) auto; align-items: center; gap: 8px; min-height: 20px; }
    .cp-composition-group .cp-bar-label { grid-column: 1; grid-row: 1; font-size: 10px; }
    .cp-composition-group .cp-bar-track { grid-column: 2; grid-row: 1; height: 7px; }
    .cp-composition-group .cp-bar-value { grid-column: 3; grid-row: 1; font-size: 11px; gap: 6px; }
    .cp-composition-unavailable { font-size: 11px; line-height: 1.8; background: #f7faff; color: #60758f; padding: 12px; margin: 0; }
    .cp-trend-legend { display: flex; flex-wrap: wrap; gap: 10px 16px; padding-top: 18px; font-size: 11px; color: #42526b; }
    .cp-trend-legend button { display: inline-flex; align-items: center; gap: 7px; max-width: 100%; overflow-wrap: anywhere; border: 0; background: transparent; color: inherit; font: inherit; padding: 3px 0; text-align: left; min-height: 24px; }
    .cp-trend-legend button[aria-pressed="false"] { opacity: .55; text-decoration: line-through; }
    .cp-trend-legend button:focus-visible { outline: 2px solid #307fe2; outline-offset: 4px; }
    .cp-trend-legend i { width: 9px; height: 9px; flex-shrink: 0; border-radius: 50%; }
    .cp-bar-list { display: grid; gap: 15px; min-width: 0; }
    .cp-bar-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: baseline; gap: 7px 12px; }
    .cp-bar-label { font-size: 11px; font-weight: 650; color: #385474; overflow-wrap: anywhere; line-height: 1.6; }
    .cp-bar-value { display: flex; align-items: baseline; gap: 8px; font-size: 13px; font-weight: 750; color: #123e74; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cp-bar-value small { font-size: 10px; font-weight: 500; color: #60758f; min-width: 38px; text-align: right; }
    .cp-bar-track { grid-column: 1 / -1; height: 9px; background: #edf3fa; border-radius: 3px; overflow: hidden; }
    .cp-bar-fill { height: 100%; border-radius: 3px; }
    .cp-bar-caption { font-size: 11px; line-height: 1.7; color: #60758f; margin: 0 0 20px; }
    .cp-quality-section { margin-top: 8px; margin-bottom: 24px; }
    .cp-quality-heading { display: flex; justify-content: space-between; align-items: start; gap: 18px; margin: 10px 0 18px; }
    .cp-quality-heading h2 { font-size: 18px; font-weight: 750; color: #093a74; margin: 0 0 7px; }
    .cp-quality-heading p { margin: 0; max-width: 800px; font-size: 12px; line-height: 1.8; color: #60758f; }
    .cp-quality-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); grid-auto-rows: 1fr; gap: 20px; align-items: stretch; }
    .cp-quality-card { grid-column: span 2; min-width: 0; border: 1px solid #d2e0ef; background: #fff; padding: 20px; border-top: 3px solid #307fe2; display: flex; flex-direction: column; }
    .cp-quality-card:nth-child(4) { grid-column: 2 / span 2; }
    .cp-quality-card:nth-child(5) { grid-column: 4 / span 2; }
    .cp-quality-card header { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px; min-height: 92px; padding-bottom: 16px; margin-bottom: 18px; border-bottom: 1px solid #e4edf7; }
    .cp-quality-card h3 { min-height: 38px; line-height: 1.4; font-size: 14px; font-weight: 750; color: #153e6d; margin: 0 0 5px; overflow-wrap: anywhere; }
    .cp-quality-card > .cp-bar-list { flex: 1; min-height: 298px; align-content: space-between; }
    .cp-quality-card header p { font-size: 11px; color: #60758f; margin: 0; }
    .cp-quality-total { font-size: 22px; font-weight: 750; color: #145fba; font-variant-numeric: tabular-nums; white-space: nowrap; line-height: 1.1; text-align: right; }
    .cp-quality-total small { display: block; margin-top: 6px; font-size: 10px; font-weight: 500; color: #60758f; }
    .cp-quality-note { font-size: 12px; color: #7e5b1e; background: #fff9ee; border: 1px solid #efdfba; padding: 12px 16px; line-height: 1.8; margin: 0 0 16px; }
    .cp-quality-empty { flex: 1; min-height: 298px; display: grid; place-items: center; margin: 0; padding: 24px 12px; background: #f7faff; text-align: center; color: #60758f; font-size: 12px; }
    @media (min-width: 1900px) { .cp-quality-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } .cp-quality-card:nth-child(n) { grid-column: auto; } }
    @media (max-width: 1319.98px) { .cp-quality-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } .cp-quality-card:nth-child(n) { grid-column: span 2; } .cp-quality-card:last-child { grid-column: 2 / span 2; } }
    @media (max-width: 991.98px) { .cp-chart-row { grid-template-columns: minmax(0, 1fr); grid-auto-rows: auto; } }
    @media (max-width: 575.98px) {
        .cp-composition-group .cp-bar-row { grid-template-columns: minmax(0, 1fr) auto; gap: 5px 12px; }
        .cp-composition-group .cp-bar-track { grid-column: 1 / -1; grid-row: 2; }
        .cp-composition-group .cp-bar-value { grid-column: 2; }
        .cp-composition-group .cp-bar-list { gap: 12px; }
    }
    @media (max-width: 767.98px) { .cp-quality-grid { grid-template-columns: minmax(0, 1fr); } .cp-quality-card:nth-child(n) { grid-column: auto; } .cp-quality-heading { flex-direction: column; } .cp-quality-card { padding: 18px; } .cp-quality-card header { min-height: 82px; } .cp-quality-card > .cp-bar-list, .cp-quality-empty { min-height: 270px; } .cp-bar-value { font-size: 12px; gap: 6px; } .cp-trend-legend { font-size: 10px; } }

    .chart-empty-state {
        height: 320px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #64748b;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
    }

    /* Toolbar Layout */
    .loan-filter-toolbar {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr) minmax(0, 1fr) auto;
        align-items: flex-end;
        gap: 0.75rem;
        width: 100%;
    }

    .loan-filter-item {
        min-width: 0;
    }

    .loan-filter-item.item-period {
        flex: 0 1 185px;
    }

    .loan-filter-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 0 auto;
        min-height: 40px;
        height: 40px;
    }
</style>
@include('report.dashboard-pinjaman._partials._styles_dropdown')
<style>
    @media (max-width: 1199.98px) {
        .chart-periodik-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .loan-filter-toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .loan-filter-actions { justify-content: flex-end; }
    }

    @media (max-width: 575.98px) {
        .chart-periodik-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .chart-periodik-kpi { padding: 14px !important; min-height: 112px !important; }
        .chart-periodik-kpi__value { font-size: 1.3rem !important; overflow-wrap: anywhere; }
        .chart-periodik-kpi__hint { font-size: .65rem !important; line-height: 1.6; }
        .loan-filter-toolbar { grid-template-columns: minmax(0, 1fr); }
        .loan-filter-actions { justify-content: stretch; }
        .loan-filter-actions .btn-flat-primary { flex: 1; }
        .cp-chart-row { gap: 20px; }

        .chart-card__header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 0.5rem !important;
        }
    }
</style>

<div class="loan-dashboard cp-dashboard pt-4 px-3">
    <div class="loan-title-hero d-flex flex-wrap justify-content-center align-items-center animate-reveal">
        <div class="loan-title-hero__wrap text-center">
            <div class="loan-title-hero__badge mx-auto">
                <i class="fas fa-chart-line"></i>
                <span>Loan Pattern Trend</span>
            </div>
            <h1 class="loan-title-hero__title">CHART PERIODIK</h1>
            <p class="loan-title-hero__desc">
                Tren pola pembayaran snapshot bulanan dan musiman per kantor cabang serta unit kerja.
            </p>
        </div>
    </div>

    <div class="card loan-shell mb-4 animate-reveal">
        <div class="card-body p-3">
            <form id="chartPeriodikForm" method="GET" action="{{ route('report.dashboard-pinjaman.chart-periodik') }}">
                <div class="loan-filter-toolbar">
                    
                    <!-- Periode Terakhir -->
                    <div class="loan-filter-item item-period">
                        <label class="loan-filter-label mb-1">
                            <i class="fas fa-calendar-alt"></i> Periode
                        </label>
                        <div class="loan-dropdown-shell" id="periodeDropdownShell">
                            <input type="hidden" name="periode" id="chartPeriodikPeriode" value="{{ $selected_period }}">
                            <button type="button" class="loan-dropdown-toggle" id="periodeDropdownToggle" aria-haspopup="true" aria-expanded="false">
                                <span class="loan-dropdown-label" id="periodeDropdownLabel">
                                    {{ $selected_period ? \Carbon\Carbon::parse($selected_period)->format('d M Y') : 'Pilih Periode' }}
                                </span>
                                <i class="fas fa-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                            </button>
                            <div class="loan-dropdown-menu" id="periodeDropdownMenu">
                                <div id="periodeOptions">
                                    @foreach($periods as $periode)
                                        <div class="loan-dropdown-item filter-single-option {{ $periode === $selected_period ? 'active' : '' }}" 
                                            data-value="{{ $periode }}" 
                                            data-label="{{ \Carbon\Carbon::parse($periode)->format('d M Y') }}">
                                            <span class="form-check-label">{{ \Carbon\Carbon::parse($periode)->format('d M Y') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kantor Cabang -->
                    <div class="loan-filter-item">
                        <label class="loan-filter-label mb-1">
                            <i class="fas fa-university"></i> Kantor Cabang
                        </label>
                        <div class="loan-dropdown-shell" id="cabangDropdownShell">
                            <input type="hidden" name="cabang1" id="chartPeriodikCabang" value="{{ $selected_branch }}">
                            <button type="button" class="loan-dropdown-toggle" id="cabangDropdownToggle" aria-haspopup="true" aria-expanded="false">
                                <span class="loan-dropdown-label" id="cabangDropdownLabel">
                                    {{ collect($branch_options)->firstWhere('value', $selected_branch)['label'] ?? 'Pilih Cabang' }}
                                </span>
                                <i class="fas fa-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                            </button>
                            <div class="loan-dropdown-menu" id="cabangDropdownMenu">
                                <div id="cabangOptions">
                                    @foreach($branch_options as $branchOption)
                                        <div class="loan-dropdown-item filter-single-option {{ $branchOption['value'] === $selected_branch ? 'active' : '' }}" 
                                            data-value="{{ $branchOption['value'] }}" 
                                            data-label="{{ $branchOption['label'] }}">
                                            <span class="form-check-label">{{ $branchOption['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Unit Kerja -->
                    <div class="loan-filter-item">
                        <label class="loan-filter-label mb-1">
                            <i class="fas fa-store"></i> Unit Kerja
                        </label>
                        <div class="loan-dropdown-shell" id="unitDropdownShell">
                            <button type="button" class="loan-dropdown-toggle" id="unitDropdownToggle" aria-haspopup="true" aria-expanded="false">
                                <span class="loan-dropdown-label" id="unitDropdownLabel">Pilih Unit Kerja</span>
                                <i class="fas fa-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                            </button>
                            <div class="loan-dropdown-menu" id="unitDropdownMenu">
                                <div id="unitCheckboxes">
                                    @forelse($unit_options as $unitOption)
                                        <div class="loan-dropdown-item">
                                            <div class="form-check">
                                                <input class="form-check-input filter-unit-checkbox" type="checkbox" 
                                                    name="unit1[]" 
                                                    value="{{ $unitOption['value'] }}" 
                                                    id="unit_{{ $unitOption['value'] }}"
                                                    @checked(in_array($unitOption['value'], $selectedUnitValues, true))>
                                                <label class="form-check-label" for="unit_{{ $unitOption['value'] }}">
                                                    {{ $unitOption['label'] }}
                                                </label>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="p-3 text-center text-muted" style="font-size: 0.85rem;">
                                            Pilih kanca untuk memuat unit
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="loan-filter-actions">
                        <button type="submit" class="btn btn-flat-primary" id="chartPeriodikRefreshButton">
                            <i class="fas fa-sync-alt" id="refreshBtnIcon"></i> <span id="refreshBtnText">MUAT</span>
                        </button>
                        <a href="{{ route('report.dashboard-pinjaman.chart-periodik') }}" class="btn btn-flat-secondary" title="Reset Filter">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="chart-periodik-summary animate-reveal mb-4">
        <div class="chart-periodik-kpi">
            <div>
                <div class="chart-periodik-kpi__label">Total Rekening</div>
                <div class="chart-periodik-kpi__value" id="chartPeriodikTotalRekening">{{ number_format((int) ($chart['summary']['total_rekening'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="chart-periodik-kpi__hint"><i class="fas fa-info-circle mr-1"></i> Data scope aktif.</div>
        </div>
        <div class="chart-periodik-kpi">
            <div>
                <div class="chart-periodik-kpi__label">Jumlah Pola</div>
                <div class="chart-periodik-kpi__value" id="chartPeriodikPatternCount">{{ number_format((int) ($chart['summary']['pattern_count'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="chart-periodik-kpi__hint"><i class="fas fa-tags mr-1"></i> Pola unik terdeteksi.</div>
        </div>
        <div class="chart-periodik-kpi">
            <div>
                <div class="chart-periodik-kpi__label">Pola Dominan</div>
                <div class="chart-periodik-kpi__value" style="font-size: 1.4rem;" id="chartPeriodikTopPattern">{{ $chart['summary']['top_pattern'] ?? '-' }}</div>
            </div>
            <div class="chart-periodik-kpi__hint"><i class="fas fa-crown mr-1"></i> Frekuensi tertinggi.</div>
        </div>
        <div class="chart-periodik-kpi" style="background: #042a5f !important; border: 1px solid #042a5f !important; border-radius: 0px !important;">
            <div>
                <div class="chart-periodik-kpi__label" style="color: rgba(255, 255, 255, 0.7) !important;">Scope & Periode</div>
                <div class="chart-periodik-kpi__value" style="color: #ffffff !important; font-size: 1.25rem !important;" id="chartPeriodikScopeLabel">{{ $chart['scope_label'] ?? 'Area 6 - All' }}</div>
            </div>
            <div class="chart-periodik-kpi__hint" style="color: rgba(255, 255, 255, 0.8) !important;" id="chartPeriodikPeriodLabel">
                <i class="fas fa-calendar-alt mr-1"></i> {{ $selected_period_label ?? '-' }}
            </div>
        </div>
    </div>

    <div class="animate-reveal cp-chart-row">
        <div class="cp-chart-column">
            <div class="chart-card">
                <div class="chart-card__header">
                    <div>
                        <h5 class="chart-card__title">Trend Pola Pembayaran</h5>
                    </div>
                    <div class="chart-card__badge">
                        <i class="fas fa-chart-line"></i>
                        <span id="chartPeriodikTrendBadge">{{ $selected_period_label ?? '-' }}</span>
                    </div>
                </div>
                <div class="chart-card__body">
                    <p class="cp-bar-caption">Perbandingan jumlah rekening menurut pola pembayaran pada hingga enam posisi laporan terakhir.</p>
                    <div id="chartPeriodikTrendEmpty" class="chart-empty-state d-none">
                        <div>
                            <strong>Belum ada data trend</strong>
                            <span>Pilih periode atau cabang untuk memunculkan garis trend pola pembayaran.</span>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartPeriodikTrendCanvas" role="img" aria-label="Tren jumlah rekening menurut pola pembayaran"></canvas>
                    </div>
                    <div id="chartPeriodikTrendLegend" class="cp-trend-legend" aria-label="Legenda pola pembayaran"></div>
                </div>
            </div>
        </div>
        <div class="cp-chart-column">
            <div class="chart-card">
                <div class="chart-card__header">
                    <div>
                        <h5 class="chart-card__title">Komposisi Periode Aktif</h5>
                    </div>
                    <div class="chart-card__badge">
                        <i class="fas fa-chart-bar" aria-hidden="true"></i>
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
                    <p class="cp-bar-caption">Pola pembayaran untuk Total Pinjaman, SML, dan NPL. Jumlah rekening dan persentase dihitung dalam masing-masing kelompok.</p>
                    <div id="chartPeriodikCompositionBars" class="cp-composition-groups" aria-label="Pola pembayaran Total Pinjaman, SML, dan NPL"></div>
                </div>
            </div>
        </div>
    </div>
    <section class="cp-quality-section" aria-labelledby="chartPeriodikQualityTitle">
        <header class="cp-quality-heading"><div><h2 id="chartPeriodikQualityTitle">Pola pembayaran per kolektibilitas</h2><p>Komposisi rekening pada periode aktif untuk kolek 1–5. Panjang batang dan persentase menunjukkan proporsi dalam masing-masing kolek.</p></div><span class="chart-card__badge" id="chartPeriodikQualityBadge">{{ $selected_period_label ?? '-' }}</span></header>
        <p id="chartPeriodikQualityNote" class="cp-quality-note" role="status" hidden></p>
        <div id="chartPeriodikQualityGrid" class="cp-quality-grid" aria-live="polite"></div>
    </section>
</div>

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
@include('report.dashboard-pinjaman._partials._scripts_shared')
@include('report.dashboard-pinjaman._partials._scripts_dropdown')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const periodSelect = document.getElementById('chartPeriodikPeriode');
        const branchSelect = document.getElementById('chartPeriodikCabang');
        const form = document.getElementById('chartPeriodikForm');
        const statusChip = document.getElementById('chartPeriodikStatus');
        const refreshButton = document.getElementById('chartPeriodikRefreshButton');
        const periodDropdownToggle = document.getElementById('periodeDropdownToggle');
        const periodDropdownMenu = document.getElementById('periodeDropdownMenu');
        const periodDropdownLabel = document.getElementById('periodeDropdownLabel');
        const periodOptionsContainer = document.getElementById('periodeOptions');
        const periodDropdownShell = document.getElementById('periodeDropdownShell');

        const cabangDropdownToggle = document.getElementById('cabangDropdownToggle');
        const cabangDropdownMenu = document.getElementById('cabangDropdownMenu');
        const cabangDropdownLabel = document.getElementById('cabangDropdownLabel');
        const cabangOptionsContainer = document.getElementById('cabangOptions');
        const cabangDropdownShell = document.getElementById('cabangDropdownShell');

        const unitDropdownToggle = document.getElementById('unitDropdownToggle');
        const unitDropdownMenu = document.getElementById('unitDropdownMenu');
        const unitDropdownLabel = document.getElementById('unitDropdownLabel');
        const unitCheckboxesContainer = document.getElementById('unitCheckboxes');
        const unitDropdownShell = document.getElementById('unitDropdownShell');
        const totalRekeningEl = document.getElementById('chartPeriodikTotalRekening');
        const patternCountEl = document.getElementById('chartPeriodikPatternCount');
        const topPatternEl = document.getElementById('chartPeriodikTopPattern');
        const scopeLabelEl = document.getElementById('chartPeriodikScopeLabel');
        const periodLabelEl = document.getElementById('chartPeriodikPeriodLabel');
        const trendBadgeEl = document.getElementById('chartPeriodikTrendBadge');
        const pieBadgeEl = document.getElementById('chartPeriodikPieBadge');
        const trendCanvas = document.getElementById('chartPeriodikTrendCanvas');
        const compositionBars = document.getElementById('chartPeriodikCompositionBars');
        const trendLegend = document.getElementById('chartPeriodikTrendLegend');
        const qualityGrid = document.getElementById('chartPeriodikQualityGrid');
        const qualityNote = document.getElementById('chartPeriodikQualityNote');
        const trendEmpty = document.getElementById('chartPeriodikTrendEmpty');
        const pieEmpty = document.getElementById('chartPeriodikPieEmpty');

        const filtersUrl = @json(route('report.dashboard-pinjaman.chart-periodik.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.chart-periodik.data'));
        const initialChartPayload = @json($chart);
        const initialPeriodOptions = @json(collect($periods ?? [])->map(fn ($period) => [
            'value' => (string) $period,
            'label' => \Carbon\Carbon::parse($period)->format('d M Y'),
        ])->values()->all());
        const initialBranchOptions = @json($branch_options ?? []);
        const initialSelectedPeriod = @json($selected_period ?? null);
        const initialSelectedBranch = @json($selected_branch ?? 'all');
        const initialUnitOptions = @json($unit_options ?? []);
        const initialSelectedUnits = @json($selectedUnitValues);

        let trendChart = null;
        let filtersController = null;
        let dataController = null;
        let suppressFilterReload = false;
        let suppressUnitReload = false;

        function initSelects() {
            // Standard selects are removed
        }

        function bindFilterEvents() {
            if (window.jQuery) {
                $(periodOptionsContainer).off('click', '.filter-single-option').on('click', '.filter-single-option', function() {
                    const val = $(this).data('value');
                    const label = $(this).data('label');
                    periodSelect.value = val;
                    periodDropdownLabel.textContent = label;
                    $(periodOptionsContainer).find('.filter-single-option').removeClass('active');
                    $(this).addClass('active');
                    periodDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                });

                $(cabangOptionsContainer).off('click', '.filter-single-option').on('click', '.filter-single-option', function() {
                    const val = $(this).data('value');
                    const label = $(this).data('label');
                    branchSelect.value = val;
                    cabangDropdownLabel.textContent = label;
                    $(cabangOptionsContainer).find('.filter-single-option').removeClass('active');
                    $(this).addClass('active');
                    cabangDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                });

                $(unitCheckboxesContainer).off('change', '.filter-unit-checkbox').on('change', '.filter-unit-checkbox', function () {
                    if (suppressUnitReload) return;
                    updateUnitLabel();
                    reloadData();
                });
                return;
            }

            // Vanilla Fallback
            periodOptionsContainer.addEventListener('click', function(e) {
                const item = e.target.closest('.filter-single-option');
                if (item) {
                    periodSelect.value = item.dataset.value;
                    periodDropdownLabel.textContent = item.dataset.label;
                    periodOptionsContainer.querySelectorAll('.filter-single-option').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    periodDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                }
            });

            cabangOptionsContainer.addEventListener('click', function(e) {
                const item = e.target.closest('.filter-single-option');
                if (item) {
                    branchSelect.value = item.dataset.value;
                    cabangDropdownLabel.textContent = item.dataset.label;
                    cabangOptionsContainer.querySelectorAll('.filter-single-option').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    cabangDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                }
            });
            
            unitCheckboxesContainer.addEventListener('change', function (e) {
                if (e.target.classList.contains('filter-unit-checkbox')) {
                    if (suppressUnitReload) return;
                    updateUnitLabel();
                    reloadData();
                }
            });
        }

        function updateUnitLabel() {
            updateMultiDropdownLabel(unitCheckboxesContainer, unitDropdownLabel, 'Pilih Unit Kerja');
        }

        function setStatus(message) {
            if (statusChip) {
                statusChip.textContent = message;
            }
        }

        const refreshBtnIcon = document.getElementById('refreshBtnIcon');
        const refreshBtnText = document.getElementById('refreshBtnText');

        function setLoading(isLoading, message) {
            refreshButton.disabled = isLoading;
            
            // disable dropdown toggles
            if (periodDropdownToggle) periodDropdownToggle.disabled = isLoading;
            if (cabangDropdownToggle) cabangDropdownToggle.disabled = isLoading;
            if (unitDropdownToggle) unitDropdownToggle.disabled = isLoading;

            if (isLoading) {
                if (refreshBtnIcon) {
                    refreshBtnIcon.className = 'fas fa-spinner fa-spin';
                }
                if (refreshBtnText) {
                    refreshBtnText.textContent = 'MENGOLAH...';
                }
            } else {
                if (refreshBtnIcon) {
                    refreshBtnIcon.className = 'fas fa-sync-alt';
                }
                if (refreshBtnText) {
                    refreshBtnText.textContent = 'MUAT';
                }
            }
            setStatus(message || (isLoading ? 'Mengambil data...' : 'Siap menampilkan data.'));
        }

        function selectedUnitValues() {
            return Array.from(unitCheckboxesContainer.querySelectorAll('.filter-unit-checkbox:checked')).map(cb => cb.value);
        }

        function rebuildSingleSelectOptions(hiddenInput, container, labelEl, options, selectedValue) {
            rebuildSingleDropdownOptions(hiddenInput, container, labelEl, options, selectedValue);
        }

        function hydrateBaseFilterOptions() {
            suppressFilterReload = true;
            rebuildSingleSelectOptions(
                periodSelect,
                periodOptionsContainer,
                periodDropdownLabel,
                initialPeriodOptions,
                initialSelectedPeriod || initialChartPayload?.selected_period || ''
            );
            rebuildSingleSelectOptions(
                branchSelect,
                cabangOptionsContainer,
                cabangDropdownLabel,
                initialBranchOptions,
                initialSelectedBranch || initialChartPayload?.selected_branch || 'all'
            );
            suppressFilterReload = false;
        }

        function rebuildUnitOptions(options, preservedValues = []) {
            suppressUnitReload = true;
            rebuildMultiDropdownOptions(unitCheckboxesContainer, unitDropdownToggle, unitDropdownLabel, options, preservedValues, 'Tidak ada unit scope ini');
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

        function renderBars(container, labels, values, total, colorFor) {
            container.replaceChildren();
            labels.forEach((label, index) => {
                const value = Number(values[index] || 0);
                const share = total > 0 ? value / total * 100 : 0;
                const row = document.createElement('div');
                row.className = 'cp-bar-row';
                row.setAttribute('role', 'listitem');
                const name = document.createElement('span');
                name.className = 'cp-bar-label';
                name.textContent = label;
                const number = document.createElement('span');
                number.className = 'cp-bar-value';
                number.textContent = formatNumber(value);
                const percent = document.createElement('small');
                percent.textContent = share > 0 && share < 0.1 ? '<0,1%' : share.toLocaleString('id-ID', { maximumFractionDigits: 1, minimumFractionDigits: 1 }) + '%';
                number.append(percent);
                const track = document.createElement('div');
                track.className = 'cp-bar-track';
                track.setAttribute('aria-hidden', 'true');
                const fill = document.createElement('div');
                fill.className = 'cp-bar-fill';
                fill.style.width = share + '%';
                fill.style.backgroundColor = colorFor(label);
                track.append(fill);
                row.append(name, number, track);
                container.append(row);
            });
        }

        function renderComposition(payload, colorFor) {
            compositionBars.replaceChildren();
            (payload.composition || []).forEach(group => {
                const section = document.createElement('section');
                section.className = 'cp-composition-group';
                section.dataset.group = group.key;
                const header = document.createElement('header');
                const copy = document.createElement('div');
                const title = document.createElement('h3');
                title.id = `cp-composition-${group.key}-title`;
                title.textContent = group.label;
                section.setAttribute('aria-labelledby', title.id);
                const basis = document.createElement('p');
                basis.textContent = group.basis;
                copy.append(title, basis);
                const total = document.createElement('div');
                total.className = 'cp-composition-total';
                total.textContent = group.available ? formatNumber(group.total) : '—';
                const unit = document.createElement('small');
                unit.textContent = group.available ? 'rekening' : 'belum tersedia';
                total.append(unit);
                header.append(copy, total);
                section.append(header);
                if (group.available) {
                    const bars = document.createElement('div');
                    bars.className = 'cp-bar-list';
                    bars.setAttribute('role', 'list');
                    bars.setAttribute('aria-label', `Jumlah rekening dan proporsi pola pembayaran ${group.label}`);
                    renderBars(bars, group.labels || [], group.values || [], group.total || 0, colorFor);
                    section.append(bars);
                } else {
                    const notice = document.createElement('p');
                    notice.className = 'cp-composition-unavailable';
                    notice.textContent = group.reason || 'Rincian kolektibilitas belum tersedia pada periode ini.';
                    section.append(notice);
                }
                compositionBars.append(section);
            });
        }

        function renderQuality(payload, colorFor) {
            const quality = payload.quality || {};
            document.getElementById('chartPeriodikQualityBadge').textContent = payload.selected_period_label || '-';
            qualityGrid.replaceChildren();
            qualityNote.hidden = quality.available && !quality.unclassified;
            qualityNote.textContent = quality.available
                ? `${formatNumber(quality.unclassified || 0)} rekening belum terklasifikasi karena nilai kolek sumber kosong atau tidak valid. Rekening ini tetap dihitung dalam komposisi periode aktif.`
                : (quality.reason || 'Data kolektibilitas pada periode ini belum tersedia.');
            const groups = quality.groups || [
                { kolek: 1, label: 'Lancar' }, { kolek: 2, label: 'SML' },
                { kolek: 3, label: 'Kurang Lancar' }, { kolek: 4, label: 'Diragukan' }, { kolek: 5, label: 'Macet' },
            ];
            groups.forEach(group => {
                const card = document.createElement('article');
                card.className = 'cp-quality-card';
                card.dataset.kolek = group.kolek;
                const header = document.createElement('header');
                const copy = document.createElement('div');
                const title = document.createElement('h3');
                title.id = `cp-quality-${group.kolek}-title`;
                title.textContent = `Kolek ${group.kolek} · ${group.label}`;
                card.setAttribute('aria-labelledby', title.id);
                const hint = document.createElement('p');
                hint.textContent = 'Pola pembayaran pada periode aktif';
                copy.append(title, hint);
                const total = document.createElement('div');
                total.className = 'cp-quality-total';
                total.textContent = quality.available ? formatNumber(group.total || 0) : '—';
                const caption = document.createElement('small');
                caption.textContent = quality.available ? 'rekening' : 'belum tersedia';
                total.append(caption);
                header.append(copy, total);
                card.append(header);
                if (quality.available && group.total > 0) {
                    const bars = document.createElement('div');
                    bars.className = 'cp-bar-list';
                    bars.setAttribute('role', 'list');
                    bars.setAttribute('aria-label', `Jumlah rekening menurut pola pembayaran pada kolek ${group.kolek}`);
                    renderBars(bars, group.labels || [], group.values || [], group.total, colorFor);
                    card.append(bars);
                } else {
                    const empty = document.createElement('p');
                    empty.className = 'cp-quality-empty';
                    empty.textContent = quality.available ? 'Tidak ada rekening pada kolek ini untuk filter aktif.' : 'Rincian kolektibilitas belum tersedia.';
                    card.append(empty);
                }
                qualityGrid.append(card);
            });
        }

        function renderCharts(payload) {
            updateSummary(payload);
            destroyCharts();

            const trendLabels = payload.trend?.labels || [];
            const trendDatasets = payload.trend?.datasets || [];
            const pieLabels = payload.pie?.labels || [];
            const pieValues = payload.pie?.values || [];
            const palette = ['#0857c3', '#ff671f', '#14b8a6', '#7c3aed', '#0f9d58', '#f59e0b', '#64748b', '#ec4899'];
            const patternOrder = [...new Set([...trendDatasets.map(item => item.label), ...pieLabels])];
            const colorFor = label => palette[Math.max(0, patternOrder.indexOf(label)) % palette.length];
            trendLegend.replaceChildren();
            trendDatasets.forEach((dataset, index) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.setAttribute('aria-pressed', 'true');
                item.setAttribute('aria-label', `Tampilkan atau sembunyikan tren ${dataset.label}`);
                const dot = document.createElement('i');
                dot.style.backgroundColor = colorFor(dataset.label);
                dot.setAttribute('aria-hidden', 'true');
                item.append(dot, document.createTextNode(dataset.label));
                item.addEventListener('click', () => {
                    if (!trendChart) return;
                    const visible = !trendChart.isDatasetVisible(index);
                    trendChart.setDatasetVisibility(index, visible);
                    trendChart.update();
                    item.setAttribute('aria-pressed', String(visible));
                });
                trendLegend.append(item);
            });

            toggleEmptyState(trendCanvas, trendEmpty, trendLabels.length > 0 && trendDatasets.length > 0);
            trendCanvas.parentElement.classList.toggle('d-none', !trendLabels.length || !trendDatasets.length);
            toggleEmptyState(compositionBars, pieEmpty, pieLabels.length > 0 && pieValues.length > 0);
            renderComposition(payload, colorFor);
            renderQuality(payload, colorFor);

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
                        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 300 },
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: false,
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
                                    autoSkip: true,
                                    maxTicksLimit: window.innerWidth < 768 ? 3 : 6,
                                    font: {
                                        family: 'inherit',
                                        size: 10,
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
                if (payload.branch_options && payload.selected_branch) {
                    suppressFilterReload = true;
                    rebuildSingleSelectOptions(branchSelect, cabangOptionsContainer, cabangDropdownLabel, payload.branch_options, payload.selected_branch);
                    suppressFilterReload = false;
                }
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
                    if (window.jQuery && window.jQuery.fn.select2) {
                        window.jQuery(periodSelect).trigger('change.select2');
                    }
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

        hydrateBaseFilterOptions();
        initSelects();
        rebuildUnitOptions(initialUnitOptions, initialSelectedUnits);
        renderCharts(initialChartPayload);
        updateUrl();
        bindFilterEvents();

        // Custom Dropdown Handlers
        initDropdownHandlers(periodDropdownShell, periodDropdownToggle, periodDropdownMenu);
        initDropdownHandlers(cabangDropdownShell, cabangDropdownToggle, cabangDropdownMenu);
        initDropdownHandlers(unitDropdownShell, unitDropdownToggle, unitDropdownMenu);

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
            setStatus('Siap menampilkan data.');
        } else {
            setStatus('Periode tidak tersedia.');
        }
    });
</script>
@endpush
@endsection
