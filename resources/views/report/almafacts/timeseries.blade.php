@extends('layouts.admin')

@section('title', 'Timeseries Laba Rugi')

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
        overflow-x: clip;
    }

    .dashboard-timeseries *,
    .dashboard-timeseries *::before,
    .dashboard-timeseries *::after {
        box-sizing: border-box;
    }

    .timeseries-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        background: linear-gradient(135deg, #003b75 0%, #00529c 100%);
        border-bottom: 1px solid rgba(219, 229, 239, 0.92);
        color: #ffffff;
    }

    .timeseries-hero > div:first-child {
        min-width: 0;
    }

    .timeseries-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(120deg, rgba(255, 255, 255, 0.05), transparent 40%);
        opacity: 0.72;
    }

    .timeseries-title {
        margin: 0;
        font-size: 1.75rem;
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

    .timeseries-hero-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
        padding-top: 2.15rem;
        min-width: 0;
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

    /* Filter Card Styling */
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

    .category-selector {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        min-width: 0;
    }

    .category-btn {
        padding: 0.6rem 1.2rem;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        background: rgba(255, 255, 255, 0.82);
        color: #475569;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        cursor: pointer;
        min-width: 0;
        overflow-wrap: anywhere;
        text-align: center;
    }

    .category-btn:hover {
        background: #ffffff;
        border-color: rgba(8, 87, 195, 0.25);
    }

    .category-btn.active {
        background: linear-gradient(135deg, #0857c3 0%, #307fe2 100%);
        color: white;
        border-color: #0857c3;
        box-shadow: 0 8px 20px -8px rgba(8, 87, 195, 0.5);
    }

    .metric-select-input {
        min-height: 42px;
        padding: 0.6rem 1rem;
        background: rgba(255, 255, 255, 0.86);
        border: 1px solid #dbe5ef;
        border-radius: 0.75rem;
        color: #1e293b;
        font-size: 0.88rem;
        font-weight: 600;
        transition: all 0.2s ease;
        width: 100%;
        cursor: pointer;
    }

    .metric-select-input:focus, .metric-select-input.active-select {
        outline: none;
        border-color: #0857c3;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.12);
    }

    .metric-dropdown-shell {
        min-width: 220px;
        max-width: 280px;
        position: relative;
        flex: 1 1 240px;
    }

    .metric-dropdown-toggle {
        width: 100%;
        min-height: 42px;
        padding: 0.58rem 0.78rem 0.58rem 0.92rem;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid #dbe5ef;
        border-radius: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        cursor: pointer;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .metric-dropdown-toggle:hover,
    .metric-dropdown-toggle.is-open {
        border-color: rgba(8, 87, 195, 0.45);
        background: #ffffff;
        box-shadow: 0 12px 24px -20px rgba(8, 87, 195, 0.5);
    }

    .metric-dropdown-label {
        color: #1e293b;
        font-size: 0.86rem;
        font-weight: 750;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .metric-dropdown-label.placeholder {
        color: #64748b;
    }

    .metric-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 10020;
        display: none;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid #dbeafe;
        border-radius: 1rem;
        box-shadow: 0 22px 42px -18px rgba(8, 87, 195, 0.28);
        animation: slideDown 0.18s ease-out;
        max-width: min(420px, calc(100vw - 2rem));
    }

    .metric-dropdown-menu.show {
        display: block;
    }

    .metric-dropdown-search {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.62rem 0.72rem;
        border-bottom: 1px solid #eef2f7;
        background: #f8fbff;
    }

    .metric-dropdown-search i {
        color: #64748b;
        font-size: 0.8rem;
    }

    .metric-dropdown-search input {
        width: 100%;
        border: 0;
        outline: none;
        background: transparent;
        color: #0f172a;
        font-size: 0.84rem;
        font-weight: 650;
    }

    .metric-options {
        max-height: 255px;
        overflow-y: auto;
        padding: 0.45rem;
    }

    .metric-option {
        display: flex;
        align-items: center;
        gap: 0.62rem;
        padding: 0.55rem 0.62rem;
        border-radius: 0.72rem;
        color: #334155;
        cursor: pointer;
        font-size: 0.84rem;
        font-weight: 650;
        transition: background 0.16s ease, color 0.16s ease;
    }

    .metric-option:hover {
        background: #eef6ff;
        color: #0857c3;
    }

    .metric-option.selected {
        background: #e7f0ff;
        color: #0857c3;
        font-weight: 850;
    }

    .metric-option-check {
        width: 17px;
        height: 17px;
        border-radius: 0.36rem;
        border: 1px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .metric-option.selected .metric-option-check {
        background: #0857c3;
        border-color: #0857c3;
        color: #ffffff;
    }

    .metric-option-check i {
        display: none;
        font-size: 0.58rem;
    }

    .metric-option.selected .metric-option-check i {
        display: inline-block;
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
        min-width: 0;
        overflow-wrap: anywhere;
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
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-export-jpg {
        width: 32px;
        height: 32px;
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

    /* Capture Status Modal Premium Styles */
    .capture-status-modal .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
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

    /* Custom Premium Dropdown */
    .branch-filter-dropdown {
        position: relative;
        width: 100%;
    }

    .branch-dropdown-toggle {
        width: 100%;
        min-height: 42px;
        padding: 0.6rem 1rem;
        background: rgba(255, 255, 255, 0.86);
        border: 1px solid #dbe5ef;
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
        box-shadow: 0 10px 24px -22px rgba(8, 87, 195, 0.42);
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
        max-width: min(420px, calc(100vw - 2rem));
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

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
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

        .timeseries-title {
            font-size: 1.08rem;
            line-height: 1.15;
            letter-spacing: 0.02em;
        }

        .timeseries-hero-actions {
            width: 100%;
            justify-content: flex-start;
            padding-top: 0.35rem;
        }

        .timeseries-hero .btn-export-all {
            width: 100%;
            justify-content: center;
        }

        .timeseries-title::after {
            margin-left: 0;
        }

        .category-selector {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
        }

        .category-btn {
            width: 100%;
            padding-inline: 0.75rem;
            min-height: 42px;
            font-size: 0.76rem;
            line-height: 1.2;
        }

        .metric-dropdown-shell {
            width: 100%;
            min-width: 0;
            max-width: none;
        }

        .branch-dropdown-toggle,
        .metric-dropdown-toggle,
        #applyFilters {
            min-height: 40px;
            border-radius: 0.7rem;
        }

        .branch-dropdown-label,
        .metric-dropdown-label {
            font-size: 0.8rem;
        }

        .chart-body {
            overflow-x: auto;
            overflow-y: hidden;
            padding: 0.75rem;
        }

        .summary-chart-body {
            height: 300px !important;
            max-height: none !important;
            padding: 0.45rem 0.55rem 0.8rem 0.55rem;
        }

        .branch-chart-body {
            height: 245px !important;
            max-height: 245px !important;
        }

        .summary-chart-body .chart-canvas-frame,
        .branch-chart-body .chart-canvas-frame {
            min-width: min(520px, calc(100vw - 2.5rem));
        }

        .chart-header {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.5rem;
        }

        .unit-badge {
            white-space: normal;
        }
    }

    @media (min-width: 768px) and (max-width: 1180px) {
        .timeseries-hero {
            align-items: flex-start !important;
            padding: 1rem 1.15rem !important;
        }

        .timeseries-title {
            font-size: 1.25rem !important;
            line-height: 1.15;
        }

        .timeseries-hero-actions {
            padding-top: 0.4rem;
        }

        .filter-card {
            border-radius: 1rem;
        }

        .filter-card .card-body {
            padding: 0.9rem !important;
        }

        .category-selector {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            width: 100%;
        }

        .category-btn {
            width: 100%;
            padding: 0.55rem 0.65rem;
            border-radius: 0.75rem;
            font-size: 0.78rem;
            line-height: 1.2;
        }

        .metric-dropdown-shell {
            width: 100%;
            max-width: none;
        }

        .branch-dropdown-toggle,
        .metric-dropdown-toggle,
        #applyFilters {
            min-height: 40px;
        }

        .summary-chart-card {
            min-height: 350px;
        }

        .summary-chart-body {
            height: 300px !important;
            padding: 0.5rem 0.8rem 1rem 0.9rem;
        }

        .branch-chart-body {
            height: 260px !important;
            max-height: 260px !important;
        }

        .branch-chart-body.tall {
            height: 340px !important;
            max-height: 340px !important;
        }
    }

    @media (max-width: 575.98px) {
        .filter-card {
            border-radius: 0.85rem;
            margin-bottom: 0.75rem;
        }

        .filter-card .card-body {
            padding: 0.75rem !important;
        }

        .filter-label {
            font-size: 0.66rem;
            margin-bottom: 0.35rem;
        }

        .category-selector {
            grid-template-columns: 1fr;
            gap: 0.4rem;
        }

        .category-btn {
            min-height: 38px;
            padding: 0.48rem 0.6rem;
        }

        .chart-card {
            border-radius: 0.75rem;
        }

        .chart-card:hover {
            transform: none;
        }

        .chart-header {
            padding: 0.7rem 0.75rem;
        }

        .chart-title {
            font-size: 0.82rem;
            line-height: 1.25;
        }

        .summary-chart-card {
            min-height: 310px;
        }

        .summary-chart-body {
            height: 255px !important;
        }

        .branch-chart-body {
            height: 225px !important;
            max-height: 225px !important;
        }
    }

    @media (max-width: 991.98px) {
        #timeseriesCaptureArea {
            padding: 1rem 0.35rem !important;
            border-radius: 14px !important;
        }

        .metric-dropdown-menu,
        .branch-dropdown-menu {
            max-height: min(360px, calc(100dvh - 160px));
        }

        .options-container,
        .metric-options {
            max-height: min(260px, calc(100dvh - 220px));
        }
    }
</style>
@endsection

@section('content')
@php
    $quickMetrics = [
        '15. Laba Setelah Pajak' => 'Laba Setelah Pajak',
        '13. Laba Sebelum Pajak' => 'Laba Sebelum Pajak',
        '10. PPOP' => 'PPOP',
        '01. Pendapatan Bunga' => 'Pendapatan Bunga',
        '04. Beban Bunga' => 'Beban Bunga',
        '09. Overhead Cost' => 'Overhead Cost',
    ];
    $isCustomMetric = !array_key_exists($selectedMetric, $quickMetrics);
@endphp
<div class="dashboard-timeseries">
    <!-- Header -->
    <div class="timeseries-hero px-4 py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <h1 class="timeseries-title m-0">TIMESERIES LABA RUGI</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button id="captureAllBtn" class="btn btn-sm btn-export-all">
                <i class="fas fa-file-image mr-1"></i> EXPORT A4
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body p-4">
            <!-- Row 1: Metrik Quick Buttons & Dropdown selector -->
            <div class="row mb-4">
                <div class="col-12">
                    <label class="filter-label" style="font-weight: 700; color: #475569; margin-bottom: 0.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Kategori Metrik</label>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="category-selector" id="categorySelector">
                            @foreach($quickMetrics as $value => $label)
                                <button type="button" class="category-btn {{ $selectedMetric === $value ? 'active' : '' }}" data-value="{{ $value }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div class="metric-dropdown-shell" id="metricDropdownShell">
                            <button type="button" class="metric-dropdown-toggle {{ $isCustomMetric ? 'active-select' : '' }}" id="metricDropdownToggle">
                                <span class="metric-dropdown-label {{ $isCustomMetric ? '' : 'placeholder' }}" id="metricDropdownLabel">
                                    {{ $isCustomMetric ? $selectedMetric : '-- Metrik Lainnya --' }}
                                </span>
                                <i class="fas fa-chevron-down text-muted small"></i>
                            </button>
                            <div class="metric-dropdown-menu" id="metricDropdownMenu">
                                <div class="metric-dropdown-search">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="metricDropdownSearch" placeholder="Cari metrik...">
                                </div>
                                <div class="metric-options" id="metricOptionsList">
                                    @foreach($metrics as $met)
                                        @if(!array_key_exists($met, $quickMetrics))
                                            <div class="metric-option {{ $selectedMetric === $met ? 'selected' : '' }}" data-value="{{ $met }}" data-label="{{ $met }}">
                                                <span class="metric-option-check"><i class="fas fa-check"></i></span>
                                                <span>{{ $met }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Branch, Unit, Year, and Update Button -->
            <div class="row align-items-end">
                <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Kantor Cabang</label>
                    <div class="branch-filter-dropdown" id="kancaDropdownShell">
                        <div class="branch-dropdown-toggle" id="kancaDropdown">
                            <span class="branch-dropdown-label" id="kancaLabel">{{ $selectedBranchLabel }}</span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="kancaMenu">
                            <input type="hidden" id="kancaInput" value="{{ $selectedBranch }}">
                            <div class="options-container" id="kancaOptionsList">
                                @foreach($branchOptions as $val => $lbl)
                                    <div class="branch-option {{ $selectedBranch === $val ? 'selected' : '' }}" data-value="{{ $val }}" data-label="{{ $lbl }}">
                                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                                        <span class="branch-option-label">{{ $lbl }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Unit Kerja</label>
                    <div class="branch-filter-dropdown" id="unitDropdownShell">
                        <div class="branch-dropdown-toggle" id="unitDropdown">
                            <span class="branch-dropdown-label" id="unitLabel">Semua Unit Kerja</span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="unitMenu">
                            <input type="hidden" id="unitInput" value="{{ $selectedUnit }}">
                            <div class="options-container" id="unitOptions">
                                {{-- Will be populated dynamically by JS --}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label class="filter-label">Tahun Analisis</label>
                    <div class="branch-filter-dropdown" id="yearDropdownShell">
                        <div class="branch-dropdown-toggle" id="yearDropdown">
                            <span class="branch-dropdown-label" id="yearLabel">{{ $selectedYear }}</span>
                            <i class="fas fa-chevron-down text-muted small"></i>
                        </div>
                        <div class="branch-dropdown-menu" id="yearMenu">
                            <input type="hidden" id="yearInput" value="{{ $selectedYear }}">
                            <div class="options-container" id="yearOptions">
                                @foreach($years as $yr)
                                    <div class="branch-option {{ $selectedYear === (int)$yr ? 'selected' : '' }}" data-value="{{ $yr }}" data-label="{{ $yr }}">
                                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                                        <span class="branch-option-label">{{ $yr }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
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

    <!-- Capture Target Area -->
    <div id="timeseriesCaptureArea" style="background: #fdfdfe; padding: 1.5rem 0.5rem; border-radius: 20px;">
        <!-- Summary Chart Container -->
        <div class="row mb-3" id="summaryChartContainer">
            <div class="col-12">
                <div class="card chart-card summary-chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title" id="summaryChartTitle"><i class="fas fa-chart-area mr-2 text-primary"></i>Area 6 - Konsolidasi</h5>
                        <div class="d-flex align-items-center">
                            <div class="unit-badge" id="summaryChartBadge">Total Konsolidasi Area 6</div>
                            <div class="loading-overlay" id="summaryLoading" style="position: static; width: auto; height: auto; background: transparent; backdrop-filter: none; display: none; margin-left: 0.75rem;">
                                <div class="loading-spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body summary-chart-body">
                        <div class="chart-canvas-frame">
                            <canvas id="summaryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sub Charts Container (Branches or Units) -->
        <div class="row" id="individualChartsContainer">
            {{-- Will be populated dynamically by JS --}}
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
            // Dropdown DOM Elements
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

            const applyBtn = document.getElementById('applyFilters');
            const summaryLoading = document.getElementById('summaryLoading');
            const metricToggle = document.getElementById('metricDropdownToggle');
            const metricMenu = document.getElementById('metricDropdownMenu');
            const metricLabel = document.getElementById('metricDropdownLabel');
            const metricSearch = document.getElementById('metricDropdownSearch');
            const metricOptionsList = document.getElementById('metricOptionsList');

            // --- Dropdown Toggling & Outside Clicks ---
            function closeAllDropdowns() {
                if (kancaMenu) kancaMenu.classList.remove('show');
                if (unitMenu) unitMenu.classList.remove('show');
                if (yearMenu) yearMenu.classList.remove('show');
                if (metricMenu) metricMenu.classList.remove('show');
                if (metricToggle) metricToggle.classList.remove('is-open');

                const kancaShell = document.getElementById('kancaDropdownShell');
                const unitShell = document.getElementById('unitDropdownShell');
                const yearShell = document.getElementById('yearDropdownShell');
                const metricShell = document.getElementById('metricDropdownShell');

                if (kancaShell) kancaShell.style.zIndex = '';
                if (unitShell) unitShell.style.zIndex = '';
                if (yearShell) yearShell.style.zIndex = '';
                if (metricShell) metricShell.style.zIndex = '';
            }

            document.addEventListener('click', closeAllDropdowns);

            if (kancaToggle) {
                kancaToggle.addEventListener('click', (e) => {
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

            if (yearToggle) {
                yearToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const wasOpen = yearMenu.classList.contains('show');
                    closeAllDropdowns();
                    if (!wasOpen) {
                        yearMenu.classList.add('show');
                        const shell = document.getElementById('yearDropdownShell');
                        if (shell) shell.style.zIndex = '1001';
                    }
                });
            }

            if (metricToggle) {
                metricToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const wasOpen = metricMenu && metricMenu.classList.contains('show');
                    closeAllDropdowns();
                    if (!wasOpen && metricMenu) {
                        metricMenu.classList.add('show');
                        metricToggle.classList.add('is-open');
                        const shell = document.getElementById('metricDropdownShell');
                        if (shell) shell.style.zIndex = '1002';
                        if (metricSearch) {
                            metricSearch.value = '';
                            filterMetricOptions('');
                            setTimeout(() => metricSearch.focus(), 0);
                        }
                    }
                });
            }

            // --- Dropdown Select Handlers ---
            if (kancaOptions) {
                kancaOptions.querySelectorAll('.branch-option').forEach(opt => {
                    opt.addEventListener('click', () => {
                        const val = opt.getAttribute('data-value');
                        const label = opt.getAttribute('data-label');
                        
                        if (kancaInput) kancaInput.value = val;
                        if (kancaLabel) kancaLabel.textContent = label;
                        
                        kancaOptions.querySelectorAll('.branch-option').forEach(o => o.classList.remove('selected'));
                        opt.classList.add('selected');
                        
                        currentBranch = val;
                        rebuildUnitOptions();
                        closeAllDropdowns();
                    });
                });
            }

            function filterMetricOptions(term) {
                if (!metricOptionsList) return;
                const needle = String(term || '').trim().toLowerCase();
                metricOptionsList.querySelectorAll('.metric-option').forEach(opt => {
                    const label = (opt.getAttribute('data-label') || opt.textContent || '').toLowerCase();
                    opt.style.display = !needle || label.includes(needle) ? '' : 'none';
                });
            }

            function selectCustomMetric(value, label) {
                currentMetric = value;
                quickMetricBtns.forEach(b => b.classList.remove('active'));
                if (metricLabel) {
                    metricLabel.textContent = label || value;
                    metricLabel.classList.remove('placeholder');
                }
                if (metricToggle) {
                    metricToggle.classList.add('active-select');
                }
                if (metricOptionsList) {
                    metricOptionsList.querySelectorAll('.metric-option').forEach(opt => {
                        opt.classList.toggle('selected', opt.getAttribute('data-value') === value);
                    });
                }
                closeAllDropdowns();
                fetchData();
            }

            if (metricSearch) {
                metricSearch.addEventListener('click', e => e.stopPropagation());
                metricSearch.addEventListener('input', () => filterMetricOptions(metricSearch.value));
            }

            if (metricOptionsList) {
                metricOptionsList.querySelectorAll('.metric-option').forEach(opt => {
                    opt.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectCustomMetric(opt.getAttribute('data-value'), opt.getAttribute('data-label'));
                    });
                });
            }

            if (yearOptions) {
                yearOptions.querySelectorAll('.branch-option').forEach(opt => {
                    opt.addEventListener('click', () => {
                        const val = opt.getAttribute('data-value');
                        const label = opt.getAttribute('data-label');
                        
                        if (yearInput) yearInput.value = val;
                        if (yearLabel) yearLabel.textContent = label;
                        
                        yearOptions.querySelectorAll('.branch-option').forEach(o => o.classList.remove('selected'));
                        opt.classList.add('selected');
                        
                        currentYear = Number(val);
                        closeAllDropdowns();
                    });
                });
            }

            // --- Unit Dropdown Logic ---
            function rebuildUnitOptions() {
                if (!unitOptionsContainer) return;
                
                const selectedVal = kancaInput ? kancaInput.value : 'area6';
                const currentUnitVal = unitInput ? unitInput.value : 'all';
                let foundCurrentUnit = currentUnitVal === 'all';
                let currentUnitLabel = 'Semua Unit Kerja';

                unitOptionsContainer.innerHTML = `
                    <div class="branch-option ${currentUnitVal === 'all' ? 'selected' : ''}" data-value="all">
                        <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                        <span class="branch-option-label">Semua Unit Kerja</span>
                    </div>
                `;

                if (selectedVal !== 'area6') {
                    allUnitsData.forEach(unit => {
                        if (unit.kanca_value === selectedVal) {
                            const opt = document.createElement('div');
                            opt.className = `branch-option ${unit.value === currentUnitVal ? 'selected' : ''}`;
                            opt.setAttribute('data-value', unit.value);
                            opt.setAttribute('data-label', unit.label);
                            opt.innerHTML = `
                                <div class="branch-checkbox-ui"><i class="fas fa-check"></i></div>
                                <span class="branch-option-label">${unit.label}</span>
                            `;
                            
                            if (unit.value === currentUnitVal) {
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
                }

                const allOpt = unitOptionsContainer.querySelector('[data-value="all"]');
                if (allOpt) {
                    allOpt.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectUnit('all', 'Semua Unit Kerja');
                    });
                }

                if (!foundCurrentUnit) {
                    selectUnit('all', 'Semua Unit Kerja');
                } else {
                    if (unitLabel) unitLabel.textContent = currentUnitLabel;
                }
            }

            function selectUnit(value, label) {
                if (unitInput) unitInput.value = value;
                if (unitLabel) unitLabel.textContent = label;
                currentUnit = value;
                closeAllDropdowns();
                
                if (unitOptionsContainer) {
                    unitOptionsContainer.querySelectorAll('.branch-option').forEach(o => {
                        o.classList.toggle('selected', o.getAttribute('data-value') === value);
                    });
                }
            }

            // --- Core Fetching Logic ---
            async function fetchData() {
                const metric = currentMetric;
                const branch = kancaInput ? kancaInput.value : 'area6';
                const unit = unitInput ? unitInput.value : 'all';
                const year = yearInput ? yearInput.value : '';
                const requestId = ++activeRequestId;

                setLoadingState(true);

                try {
                    const queryParams = new URLSearchParams({
                        metric: metric,
                        cabang: branch,
                        unit_kerja: unit,
                        year: year
                    });

                    const response = await fetch(`${routes.data}?${queryParams.toString()}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json();
                    if (requestId !== activeRequestId) return;

                    if (!hasTimeseriesData(data)) {
                        renderEmptyChart();
                        return;
                    }

                    renderCharts(data);
                } catch (error) {
                    console.error('Failed to fetch timeseries data:', error);
                    renderEmptyChart();
                } finally {
                    if (requestId === activeRequestId) {
                        setLoadingState(false);
                    }
                }
            }

            function setLoadingState(isLoading) {
                if (summaryLoading) {
                    summaryLoading.style.display = isLoading ? 'flex' : 'none';
                }
            }

            function hasTimeseriesData(data) {
                return Boolean(data && data.summary && Array.isArray(data.summary.datasets) && data.summary.datasets.length > 0);
            }

            function renderEmptyChart() {
                Object.values(charts).forEach(c => { try { c.destroy(); } catch(e) {} });
                charts = {};
                const summaryCanvas = document.getElementById('summaryChart');
                if (summaryCanvas) {
                    const ctx = summaryCanvas.getContext('2d');
                    ctx.clearRect(0, 0, summaryCanvas.width, summaryCanvas.height);
                }
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
                { border: '#004ea8', bg: 'rgba(0, 78, 168, 0.075)' }, // Selected Year
                { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.04)' }, // RKA
                { border: '#64748b', bg: 'rgba(100, 116, 139, 0.03)' }, // Prev Year
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
                const minRange = Math.max(Math.abs(max) * (isSummary ? 0.025 : 0.015), 5);
                const effectiveSpread = Math.max(naturalSpread, minRange);
                const center = (min + max) / 2;
                const pad = effectiveSpread * 0.12;
                const rawMin = naturalSpread < minRange ? center - (minRange / 2) - pad : min - pad;
                const rawMax = naturalSpread < minRange ? center + (minRange / 2) + pad : max + pad;

                return {
                    min: rawMin,
                    max: rawMax,
                };
            }

            function createChartConfig(title, labels, datasets, isSummary = false) {
                const yAxisBounds = resolveYAxisBounds(datasets, isSummary);
                const isPhone = window.matchMedia('(max-width: 575.98px)').matches;
                const isCompact = window.matchMedia('(max-width: 767.98px)').matches;
                const isTablet = window.matchMedia('(min-width: 768px) and (max-width: 1180px)').matches;
                const latestBorderWidth = isPhone ? 2.2 : (isCompact || isTablet ? 2.7 : 3.25);
                const otherBorderWidth = isPhone ? 1.4 : (isCompact || isTablet ? 1.7 : 2);
                const latestPointRadius = isPhone ? 2.4 : (isCompact || isTablet ? 3 : 4);
                const otherPointRadius = isPhone ? 1.8 : (isCompact || isTablet ? 2.2 : 2.5);
                const tickSize = isPhone ? 8 : (isCompact || isTablet ? 9 : 10);
                const legendSize = isPhone ? 8 : (isCompact || isTablet ? 9 : 10);

                return {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: datasets.map((d, i) => {
                            const isRka = d.label.includes('RKA');
                            const isPrev = d.label.includes(String(currentYear - 1));
                            const isLatest = !isRka && !isPrev;

                            return {
                                type: 'line',
                                label: d.label,
                                data: d.data,
                                yAxisID: 'y',
                                borderColor: isLatest ? monthColors[0].border : (isRka ? monthColors[1].border : monthColors[2].border),
                                backgroundColor: isLatest ? monthColors[0].bg : (isRka ? monthColors[1].bg : monthColors[2].bg),
                                borderWidth: isLatest ? latestBorderWidth : otherBorderWidth,
                                pointRadius: isLatest ? latestPointRadius : otherPointRadius,
                                pointHoverRadius: isLatest ? latestPointRadius + 1.5 : otherPointRadius + 1.2,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: isLatest ? monthColors[0].border : (isRka ? monthColors[1].border : monthColors[2].border),
                                pointBorderWidth: isLatest ? 2 : 1.5,
                                tension: 0.28,
                                fill: isLatest,
                                clip: false,
                                spanGaps: true,
                                borderDash: isRka ? [5, 5] : (isPrev ? [3, 3] : []),
                                borderCapStyle: 'round',
                                borderJoinStyle: 'round',
                                order: isLatest ? 1 : 2,
                            };
                        })
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        devicePixelRatio: isPhone ? 1.75 : 2.5,
                        layout: {
                            padding: {
                                top: isCompact ? 8 : (isSummary ? 16 : 24),
                                right: isCompact ? 8 : (isSummary ? 16 : 26),
                                bottom: isCompact ? 16 : (isSummary ? 36 : 30),
                                left: isCompact ? 4 : 12
                            }
                        },
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'line',
                                    boxWidth: isCompact ? 18 : 28,
                                    boxHeight: 8,
                                    color: '#475569',
                                    padding: isCompact ? 8 : 14,
                                    font: { weight: '600', size: legendSize }
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
                                            label += new Intl.NumberFormat('id-ID', {
                                                maximumFractionDigits: 2
                                            }).format(context.parsed.y) + ' Rp Juta';
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
                                border: { display: true, color: '#cbd5e1' },
                                title: {
                                    display: true,
                                    text: 'Nilai (Rp Juta)',
                                    color: '#475569',
                                    font: { weight: '600', size: tickSize }
                                },
                                grid: { color: 'rgba(15, 23, 42, 0.055)', drawTicks: true },
                                ticks: {
                                    maxTicksLimit: isCompact ? 5 : (isSummary ? 7 : 6),
                                    padding: isCompact ? 6 : 10,
                                    display: true,
                                    color: '#64748b',
                                    font: { size: tickSize, weight: '500' },
                                    callback: function(value) {
                                        return new Intl.NumberFormat('id-ID', { 
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            },
                            x: {
                                display: true,
                                border: { display: true, color: '#cbd5e1' },
                                grid: { display: false, drawTicks: true },
                                ticks: {
                                    display: true,
                                    padding: isCompact ? 6 : 10,
                                    color: '#64748b',
                                    maxRotation: isPhone ? 0 : 30,
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
                // Destroy old charts
                Object.values(charts).forEach(c => { try { c.destroy(); } catch(e) {} });
                charts = {};

                // 1. Render Summary Chart
                const summaryCanvas = document.getElementById('summaryChart');
                if (summaryCanvas) {
                    const summaryCtx = summaryCanvas.getContext('2d');
                    charts['summary'] = new Chart(summaryCtx, createChartConfig('Total Analytics', data.labels, data.summary.datasets, true));
                    
                    const badge = document.getElementById('summaryChartBadge');
                    if (badge) {
                        const selectedVal = kancaInput ? kancaInput.value : 'area6';
                        const selectedUnitVal = unitInput ? unitInput.value : 'all';
                        if (selectedVal === 'area6') {
                            badge.textContent = 'Konsolidasi Area 6';
                        } else if (selectedUnitVal === 'all') {
                            badge.textContent = `Konsolidasi ${selectedVal}`;
                        } else {
                            const unitName = allUnitsData.find(u => u.value === selectedUnitVal)?.label || selectedUnitVal;
                            badge.textContent = `${unitName}`;
                        }
                    }

                    const titleEl = document.getElementById('summaryChartTitle');
                    if (titleEl) {
                        titleEl.innerHTML = `<i class="fas fa-chart-area mr-2 text-primary"></i>${currentMetric} - Trend Analysis`;
                    }
                }

                // 2. Render Sub Charts Grid
                const container = document.getElementById('individualChartsContainer');
                if (container) {
                    container.innerHTML = '';
                    const seriesKeys = Object.keys(data.series || {}).sort();

                    if (seriesKeys.length === 0) {
                        return;
                    }

                    const isFullWidth = seriesKeys.length === 1;

                    seriesKeys.forEach(key => {
                        const col = document.createElement('div');
                        col.className = isFullWidth ? 'col-12 mb-4' : 'col-lg-6 mb-3';
                        const canvasId = `chart_${key.replace(/[^\w-]/g, '_')}`;
                        const displayTitle = `${key}`;
                        
                        col.innerHTML = `
                            <div class="card chart-card">
                                <div class="chart-header">
                                    <h5 class="chart-title">${displayTitle}</h5>
                                    <div class="d-flex align-items-center">
                                        <span class="unit-badge">Trend Bulanan</span>
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

                        const ctx = document.getElementById(canvasId).getContext('2d');
                        const datasets = data.series[key].datasets;
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

            // --- Metric Select Handler ---
            const quickMetricBtns = document.querySelectorAll('#categorySelector .category-btn');

            quickMetricBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    quickMetricBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    if (metricToggle) {
                        metricToggle.classList.remove('active-select');
                    }
                    if (metricLabel) {
                        metricLabel.textContent = '-- Metrik Lainnya --';
                        metricLabel.classList.add('placeholder');
                    }
                    if (metricOptionsList) {
                        metricOptionsList.querySelectorAll('.metric-option').forEach(opt => opt.classList.remove('selected'));
                    }
                    currentMetric = this.getAttribute('data-value');
                    fetchData();
                });
            });

            if (applyBtn) {
                applyBtn.addEventListener('click', fetchData);
            }

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
                                text: 'Nilai (Rp Juta)',
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
                ctx.fillText('Timeseries Analytics Laba Rugi', marginX, marginY + 35);

                ctx.fillStyle = '#475569';
                ctx.font = '600 30px "Inter", "Segoe UI", Arial, sans-serif';
                drawTextEllipsis(ctx, `Metrik: ${metric}   |   Tahun Analisis: ${currentYear}`, marginX, marginY + 92, width - (marginX * 2));
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
                        link.download = `Timeseries-Laba-Rugi-A4-${category}-${timestamp}.jpg`;
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
                console.error('Chart.js belum termuat untuk Timeseries Laba Rugi.');
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
