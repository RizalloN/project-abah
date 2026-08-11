@extends('layouts.admin')

@section('title', $pageTitle ?? 'Marketshare CRAS LPG')

@section('styles')
<link rel="stylesheet" href="{{ asset('vendor/leaflet-1.9.4/leaflet.css') }}">
@endsection

@section('content')
@php
    $payload = $crasMapping ?? ['ready' => false];
    $lpgPayload = data_get($payload, 'lpg', []);
    $lpgFilterOptions = data_get($lpgPayload, 'filters.options', []);
    $lpgSelectedFilters = data_get($lpgPayload, 'filters.selected', []);
    $filterOptions = data_get($payload, 'filters.options', []);
    $selectedFilters = data_get($payload, 'filters.selected', []);
    $primaryFilterLabels = [
        'periode' => 'Posisi Data',
        'wilayah' => 'Wilayah',
    ];
    $portfolioFilterLabels = [
        'sektor' => 'Sektor Ekonomi',
        'sub_sektor' => 'Sub Sektor Ekonomi',
        'loan_type' => 'Loan Type',
        'segmen' => 'Segmen',
        'produk_tiering' => 'Produk Tiering',
        'kualitas' => 'Kualitas',
    ];
    $activePortfolioFilterCount = collect(array_keys($portfolioFilterLabels))
        ->filter(fn ($key) => ($selectedFilters[$key] ?? 'all') !== 'all')
        ->count();
@endphp

<style>
    :root {
        --cras-blue: #0b5cab;
        --cras-blue-dark: #08467f;
        --cras-ink: #172033;
        --cras-muted: #64748b;
        --cras-border: #dbe3ec;
        --cras-soft: #f5f8fc;
        --cras-white: #ffffff;
        --cras-npl: #a61b35;
        --cras-sml: #a45d08;
        --cras-teal: #0f766e;
    }

    .cras-map-page {
        min-height: calc(100vh - 60px);
        padding: 1rem;
        background: var(--cras-soft);
        color: var(--cras-ink);
    }

    .cras-map-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.8rem;
        padding: 0.9rem 1rem;
        border: 1px solid var(--cras-border);
        border-left: 4px solid var(--cras-blue);
        border-radius: 6px;
        background: var(--cras-white);
    }

    .cras-map-header h1 {
        margin: 0;
        font-size: 1.18rem;
        font-weight: 800;
        line-height: 1.35;
        letter-spacing: 0;
    }

    .cras-map-header p {
        margin: 0.2rem 0 0;
        color: var(--cras-muted);
        font-size: 0.8rem;
    }

    .cras-map-period {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex: 0 0 auto;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .cras-map-period i { color: var(--cras-blue); }

    .cras-context-bar {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.7rem;
        padding: 0.72rem 0.85rem;
        border: 1px solid var(--cras-border);
        border-radius: 6px;
        background: var(--cras-white);
    }

    .cras-context-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(220px, 320px));
        gap: 0.65rem;
        min-width: 0;
    }

    .cras-view-switch {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.55rem;
        margin-bottom: 0.8rem;
        padding: 0.3rem;
        border: 1px solid var(--cras-border);
        border-radius: 6px;
        background: #eaf0f6;
    }

    .cras-view-trigger {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr);
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        min-height: 54px;
        padding: 0.52rem 0.7rem;
        border: 1px solid transparent;
        border-radius: 5px;
        background: transparent;
        color: #526174;
        text-align: left;
    }

    .cras-view-trigger > i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 5px;
        background: #d8e4ef;
        color: #46627d;
    }

    .cras-view-trigger strong,
    .cras-view-trigger small { display: block; }
    .cras-view-trigger strong { font-size: 0.8rem; }
    .cras-view-trigger small { margin-top: 0.08rem; color: #718096; font-size: 0.65rem; }

    .cras-view-trigger:hover,
    .cras-view-trigger:focus {
        background: #f8fafc;
        color: var(--cras-ink);
        outline: 0;
    }

    .cras-view-trigger.is-active {
        border-color: #b8d1e8;
        background: #ffffff;
        color: var(--cras-blue);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    }

    .cras-view-trigger.is-active > i {
        background: var(--cras-blue);
        color: #ffffff;
    }

    .cras-view-panel[hidden] { display: none !important; }

    .cras-filter-panel,
    .cras-kpi-strip,
    .cras-workspace,
    .cras-detail-band,
    .cras-unit-section {
        border: 1px solid var(--cras-border);
        border-radius: 6px;
        background: var(--cras-white);
    }

    .cras-filter-panel {
        margin-bottom: 0.8rem;
        overflow: hidden;
    }

    .cras-filter-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.72rem 0.85rem;
        border-bottom: 1px solid var(--cras-border);
        background: #f8fafc;
    }

    .cras-filter-head h2 {
        margin: 0;
        color: var(--cras-ink);
        font-size: 0.84rem;
        font-weight: 800;
    }

    .cras-filter-head p {
        margin: 0.12rem 0 0;
        color: var(--cras-muted);
        font-size: 0.68rem;
    }

    .cras-filter-status {
        color: var(--cras-blue);
        font-size: 0.68rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .cras-filter-primary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.65rem;
        padding: 0.8rem 0.85rem;
    }

    .cras-filter-primary--map {
        grid-template-columns: minmax(240px, 360px);
    }

    .cras-filter-field {
        min-width: 0;
        margin: 0;
    }

    .cras-filter-field span {
        display: block;
        margin-bottom: 0.28rem;
        color: #526174;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .cras-filter-field select {
        width: 100%;
        height: 38px;
        padding: 0 2rem 0 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: #ffffff;
        color: #243247;
        font-size: 0.78rem;
        box-shadow: none;
        text-overflow: ellipsis;
    }

    .cras-filter-field select:focus {
        border-color: var(--cras-blue);
        outline: 0;
        box-shadow: 0 0 0 3px rgba(11, 92, 171, 0.12);
    }

    .cras-filter-field select:disabled {
        cursor: not-allowed;
        background: #f1f5f9;
        color: #475569;
        opacity: 1;
    }

    .cras-focus-bar {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.7rem 0.85rem;
        border-top: 1px solid #edf1f5;
        border-bottom: 1px solid #edf1f5;
    }

    .cras-focus-label {
        flex: 0 0 auto;
        color: #526174;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .cras-focus-options,
    .cras-ranking-modes {
        display: inline-flex;
        min-width: 0;
        padding: 2px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #f1f5f9;
    }

    .cras-focus-option,
    .cras-ranking-mode {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.32rem;
        min-height: 34px;
        padding: 0.35rem 0.62rem;
        border: 0;
        border-radius: 4px;
        background: transparent;
        color: #526174;
        font-size: 0.7rem;
        font-weight: 750;
        white-space: nowrap;
    }

    .cras-focus-option:hover,
    .cras-focus-option:focus,
    .cras-ranking-mode:hover,
    .cras-ranking-mode:focus {
        color: var(--cras-ink);
        outline: 0;
    }

    .cras-focus-option.is-active,
    .cras-ranking-mode.is-active {
        background: #ffffff;
        color: var(--cras-blue);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.14);
    }

    .cras-filter-advanced {
        border-bottom: 1px solid #edf1f5;
    }

    .cras-filter-advanced summary {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.68rem 0.85rem;
        color: #334155;
        cursor: pointer;
        font-size: 0.72rem;
        font-weight: 800;
        list-style: none;
    }

    .cras-filter-advanced summary::-webkit-details-marker {
        display: none;
    }

    .cras-filter-advanced summary::after {
        content: '\f078';
        margin-left: auto;
        color: #64748b;
        font-family: 'Font Awesome 5 Free';
        font-size: 0.62rem;
        font-weight: 900;
        transition: transform 160ms ease;
    }

    .cras-filter-advanced[open] summary::after {
        transform: rotate(180deg);
    }

    .cras-filter-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 0.35rem;
        border-radius: 4px;
        background: #e7eef6;
        color: var(--cras-blue);
        font-size: 0.64rem;
    }

    .cras-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.65rem;
        padding: 0 0.85rem 0.8rem;
    }

    .cras-active-filters {
        display: none;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.38rem;
        padding: 0.58rem 0.85rem;
        border-bottom: 1px solid #edf1f5;
        background: #fbfdff;
    }

    .cras-active-filters.has-items {
        display: flex;
    }

    .cras-active-label {
        margin-right: 0.15rem;
        color: #64748b;
        font-size: 0.66rem;
        font-weight: 800;
    }

    .cras-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        min-height: 26px;
        padding: 0.25rem 0.38rem 0.25rem 0.48rem;
        border: 1px solid #cbdced;
        border-radius: 5px;
        background: #eef5fb;
        color: #24486c;
        font-size: 0.66rem;
        font-weight: 700;
    }

    .cras-filter-chip button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        padding: 0;
        border: 0;
        border-radius: 4px;
        background: transparent;
        color: #64748b;
    }

    .cras-filter-chip button:hover,
    .cras-filter-chip button:focus {
        background: #dce9f5;
        color: #173f66;
        outline: 0;
    }

    .cras-filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        padding: 0.7rem 0.85rem;
        background: #f8fafc;
    }

    .cras-filter-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        min-height: 38px;
        padding: 0.5rem 0.85rem;
        border: 1px solid var(--cras-blue);
        border-radius: 5px;
        background: var(--cras-blue);
        color: #ffffff;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .cras-filter-button:hover,
    .cras-filter-button:focus {
        background: var(--cras-blue-dark);
        color: #ffffff;
    }

    .cras-filter-button--secondary {
        border-color: #cbd5e1;
        background: #ffffff;
        color: #475569;
    }

    .cras-filter-button--secondary:hover,
    .cras-filter-button--secondary:focus {
        background: #f8fafc;
        color: #1e293b;
    }

    .cras-kpi-strip {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin-bottom: 0.8rem;
        overflow: hidden;
    }

    .cras-kpi-item {
        min-width: 0;
        padding: 0.75rem 0.9rem;
        border-right: 1px solid var(--cras-border);
    }

    .cras-kpi-item:last-child { border-right: 0; }

    .cras-kpi-label {
        display: flex;
        align-items: center;
        gap: 0.38rem;
        color: var(--cras-muted);
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .cras-kpi-label i { color: var(--cras-blue); }

    .cras-kpi-value {
        display: block;
        margin-top: 0.28rem;
        color: var(--cras-ink);
        font-size: 1.08rem;
        font-weight: 800;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .cras-kpi-item[data-tone="npl"] .cras-kpi-label i,
    .cras-kpi-item[data-tone="npl"] .cras-kpi-value {
        color: var(--cras-npl);
    }

    .cras-kpi-item[data-tone="sml"] .cras-kpi-label i,
    .cras-kpi-item[data-tone="sml"] .cras-kpi-value {
        color: var(--cras-sml);
    }

    .cras-insight-strip {
        margin-bottom: 0.8rem;
        overflow: hidden;
        border: 1px solid var(--cras-border);
        border-radius: 6px;
        background: #ffffff;
    }

    .cras-insight-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 0.8rem;
        border-bottom: 1px solid var(--cras-border);
        background: #f8fafc;
    }

    .cras-insight-head strong {
        color: var(--cras-ink);
        font-size: 0.78rem;
    }

    .cras-insight-head span {
        color: var(--cras-muted);
        font-size: 0.66rem;
    }

    .cras-insight-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .cras-insight-item {
        display: grid;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: 0.55rem;
        min-width: 0;
        padding: 0.68rem 0.75rem;
        border: 0;
        border-right: 1px solid var(--cras-border);
        background: #ffffff;
        color: #334155;
        text-align: left;
    }

    .cras-insight-item:last-child {
        border-right: 0;
    }

    .cras-insight-item:hover,
    .cras-insight-item:focus {
        background: #f6f9fc;
        outline: 0;
    }

    .cras-insight-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border: 1px solid #c9dff2;
        border-radius: 5px;
        background: #eaf3fb;
        color: var(--cras-blue);
    }

    .cras-insight-item[data-metric="npl_os"] .cras-insight-icon {
        border-color: #efc6ce;
        background: #fff1f3;
        color: var(--cras-npl);
    }

    .cras-insight-item[data-metric="sml_os"] .cras-insight-icon {
        border-color: #ecd6b6;
        background: #fff8e8;
        color: var(--cras-sml);
    }

    .cras-insight-item[data-metric="total_tunggakan"] .cras-insight-icon {
        border-color: #c9ddd8;
        background: #edf8f5;
        color: var(--cras-teal);
    }

    .cras-insight-label,
    .cras-insight-name,
    .cras-insight-value {
        display: block;
        min-width: 0;
    }

    .cras-insight-label {
        color: #64748b;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .cras-insight-name {
        margin-top: 0.14rem;
        overflow: hidden;
        color: #243247;
        font-size: 0.74rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cras-insight-value {
        margin-top: 0.1rem;
        color: var(--cras-blue);
        font-size: 0.7rem;
        font-weight: 750;
    }

    .cras-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 370px;
        min-height: 610px;
        margin-bottom: 0.8rem;
        overflow: hidden;
    }

    .cras-map-shell {
        position: relative;
        min-width: 0;
        min-height: 610px;
        background: #e8eff6;
    }

    #crasPortfolioMap {
        width: 100%;
        height: 610px;
        background: #e8eff6;
    }

    .cras-map-loading {
        position: absolute;
        inset: 0;
        z-index: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(245, 248, 252, 0.88);
        color: #475569;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .cras-map-loading.is-hidden { display: none; }

    .cras-map-toolbar {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 450;
    }

    .cras-map-reset {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        min-height: 34px;
        padding: 0.42rem 0.58rem;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: rgba(255, 255, 255, 0.96);
        color: #334155;
        font-size: 0.68rem;
        font-weight: 750;
        box-shadow: 0 8px 18px -16px rgba(15, 23, 42, 0.55);
    }

    .cras-map-reset:hover,
    .cras-map-reset:focus {
        border-color: #9eb6ce;
        color: var(--cras-blue);
        outline: 0;
    }

    .cras-map-legend {
        position: absolute;
        right: 12px;
        bottom: 12px;
        z-index: 450;
        width: 190px;
        padding: 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 8px 24px -20px rgba(15, 23, 42, 0.5);
    }

    .cras-map-legend strong {
        display: block;
        margin-bottom: 0.4rem;
        color: #334155;
        font-size: 0.72rem;
    }

    .cras-map-legend-scale {
        height: 8px;
        border-radius: 2px;
        background: linear-gradient(90deg, #e8eef5, #b7d0e8, #6fa5d2, #2676b8, #084b87);
    }

    .cras-map-legend-labels {
        display: flex;
        justify-content: space-between;
        margin-top: 0.28rem;
        color: #64748b;
        font-size: 0.64rem;
    }

    .cras-side-panel {
        min-width: 0;
        border-left: 1px solid var(--cras-border);
        background: #ffffff;
    }

    .cras-side-head {
        padding: 0.85rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-side-eyebrow {
        display: block;
        margin-bottom: 0.25rem;
        color: var(--cras-blue);
        font-size: 0.66rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .cras-side-head h2 {
        margin: 0;
        color: var(--cras-ink);
        font-size: 0.98rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .cras-side-head p {
        margin: 0.28rem 0 0;
        color: var(--cras-muted);
        font-size: 0.72rem;
        line-height: 1.45;
    }

    .cras-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 0.75rem;
        gap: 0.45rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-detail-item {
        min-width: 0;
        padding: 0.55rem;
        border: 1px solid #e5eaf0;
        border-radius: 5px;
        background: #f8fafc;
    }

    .cras-detail-item span {
        display: block;
        color: #64748b;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .cras-detail-item strong {
        display: block;
        margin-top: 0.2rem;
        color: #243247;
        font-size: 0.76rem;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .cras-ranking-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.7rem 0.85rem;
        border-bottom: 1px solid var(--cras-border);
        color: #334155;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .cras-ranking-heading {
        min-width: 0;
    }

    .cras-ranking-heading span {
        display: block;
    }

    .cras-ranking-heading small {
        display: block;
        margin-top: 0.12rem;
        color: var(--cras-muted);
        font-size: 0.62rem;
        font-weight: 600;
    }

    .cras-ranking-modes {
        flex: 0 0 auto;
    }

    .cras-ranking-mode {
        min-height: 32px;
        padding: 0.26rem 0.42rem;
        font-size: 0.62rem;
    }

    .cras-ranking-list {
        max-height: 250px;
        overflow-y: auto;
    }

    .cras-ranking-row {
        display: grid;
        grid-template-columns: 26px minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.45rem;
        width: 100%;
        min-height: 38px;
        padding: 0.45rem 0.75rem;
        border: 0;
        border-bottom: 1px solid #edf1f5;
        background: #ffffff;
        color: #334155;
        text-align: left;
        font-size: 0.68rem;
    }

    .cras-ranking-row:hover { background: #f5f8fc; }

    .cras-ranking-order {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 4px;
        background: #e7eef6;
        color: #475569;
        font-weight: 800;
    }

    .cras-ranking-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cras-ranking-row strong {
        color: var(--cras-blue);
        font-size: 0.68rem;
    }

    .cras-ranking-meta {
        display: block;
        margin-top: 0.1rem;
        overflow: hidden;
        color: #7a8798;
        font-size: 0.58rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cras-detail-band {
        margin-bottom: 0.8rem;
        overflow: hidden;
    }

    .cras-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.72rem 0.85rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-section-head h2 {
        margin: 0;
        color: var(--cras-ink);
        font-size: 0.88rem;
        font-weight: 800;
    }

    .cras-section-head span {
        color: var(--cras-muted);
        font-size: 0.68rem;
    }

    .cras-secondary-metrics {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .cras-secondary-item {
        min-width: 0;
        padding: 0.7rem 0.75rem;
        border-right: 1px solid var(--cras-border);
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-secondary-item:nth-child(6n) { border-right: 0; }
    .cras-secondary-item:nth-last-child(-n + 6) { border-bottom: 0; }

    .cras-secondary-item span {
        display: block;
        color: #64748b;
        font-size: 0.63rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .cras-secondary-item strong {
        display: block;
        margin-top: 0.24rem;
        color: #243247;
        font-size: 0.78rem;
        overflow-wrap: anywhere;
    }

    .cras-unit-section { overflow: hidden; }

    .cras-table-wrap {
        max-height: none;
        overflow-x: auto;
        overflow-y: visible;
        scrollbar-gutter: stable;
    }

    .cras-unit-table {
        width: 100%;
        min-width: 850px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.72rem;
    }

    .cras-unit-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 0.58rem 0.65rem;
        border-bottom: 1px solid #cbd5e1;
        background: #eef3f8;
        color: #475569;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .cras-unit-table td {
        padding: 0.55rem 0.65rem;
        border-bottom: 1px solid #edf1f5;
        color: #334155;
        white-space: nowrap;
    }

    .cras-unit-table tbody tr:hover td { background: #f8fafc; }
    .cras-unit-table .text-right { text-align: right; }

    .cras-map-sac-strip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.8rem;
        padding: 0.58rem 0.72rem;
        border: 1px solid var(--cras-border);
        border-radius: 6px;
        background: #ffffff;
    }

    .cras-map-sac-title {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        flex: 0 0 auto;
    }

    .cras-map-sac-title > i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 5px;
        background: #eaf3fb;
        color: var(--cras-blue);
    }

    .cras-map-sac-title strong,
    .cras-map-sac-title small { display: block; }
    .cras-map-sac-title strong { color: #243247; font-size: 0.72rem; }
    .cras-map-sac-title small { margin-top: 0.05rem; color: #718096; font-size: 0.6rem; }

    .cras-map-sac-items {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.38rem;
    }

    .cras-map-sac-item {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        min-height: 28px;
        padding: 0.25rem 0.42rem;
        border: 1px solid var(--sac-color);
        border-radius: 4px;
        background: var(--sac-soft);
        color: #334155;
        font-size: 0.64rem;
        font-weight: 750;
        white-space: nowrap;
    }

    .cras-lpg-panel {
        margin-bottom: 0.8rem;
        overflow: hidden;
        border: 1px solid var(--cras-border);
        border-radius: 6px;
        background: #ffffff;
    }

    .cras-lpg-head,
    .cras-lpg-table-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.8rem 0.9rem;
        border-bottom: 1px solid var(--cras-border);
        background: #f8fafc;
    }

    .cras-lpg-head h2,
    .cras-lpg-table-head h3 {
        margin: 0;
        color: var(--cras-ink);
        font-size: 0.9rem;
        font-weight: 800;
    }

    .cras-lpg-head p,
    .cras-lpg-table-head p {
        max-width: 900px;
        margin: 0.2rem 0 0;
        color: var(--cras-muted);
        font-size: 0.7rem;
        line-height: 1.5;
    }

    .cras-lpg-reference {
        flex: 0 0 auto;
        color: #526174;
        font-size: 0.64rem;
        font-weight: 700;
        text-align: right;
    }

    .cras-sac-legend {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.55rem 0.85rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-sac-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        min-height: 28px;
        padding: 0.25rem 0.42rem;
        border: 1px solid var(--sac-color);
        border-radius: 4px;
        background: var(--sac-soft);
    }

    .cras-sac-legend-item strong { color: #25364a; font-size: 0.65rem; }
    .cras-sac-legend-item small { color: #64748b; font-size: 0.61rem; }

    [data-sac-color="hijau"] { --sac-color: #209653; --sac-soft: #effaf3; }
    [data-sac-color="hijau_muda"] { --sac-color: #69c991; --sac-soft: #f1fbf5; }
    [data-sac-color="kuning"] { --sac-color: #e8b425; --sac-soft: #fff9e8; }
    [data-sac-color="merah"] { --sac-color: #ed4d52; --sac-soft: #fff2f2; }
    [data-sac-color="unmapped"] { --sac-color: #94a3b8; --sac-soft: #f8fafc; }

    .cras-lpg-controls {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
        align-items: end;
        gap: 0.6rem;
        padding: 0.75rem 0.85rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-lpg-controls .cras-filter-button { min-width: 108px; }

    .cras-lpg-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-lpg-kpi {
        min-width: 0;
        padding: 0.72rem 0.85rem;
        border-right: 1px solid var(--cras-border);
    }

    .cras-lpg-kpi:last-child { border-right: 0; }
    .cras-lpg-kpi span { display: block; color: #64748b; font-size: 0.62rem; font-weight: 800; text-transform: uppercase; }
    .cras-lpg-kpi strong { display: block; margin-top: 0.2rem; color: #172033; font-size: 0.92rem; line-height: 1.25; overflow-wrap: anywhere; }
    .cras-lpg-kpi[data-tone="sml"] strong { color: var(--cras-sml); }
    .cras-lpg-kpi[data-tone="npl"] strong { color: var(--cras-npl); }

    .cras-lpg-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-lpg-summary-item {
        min-width: 0;
        padding: 0.68rem 0.75rem;
        border-right: 1px solid var(--cras-border);
        border-top: 3px solid var(--sac-color);
        background: var(--sac-soft);
    }

    .cras-lpg-summary-item:last-child { border-right: 0; }
    .cras-lpg-summary-item span,
    .cras-lpg-summary-item small { display: block; color: #64748b; font-size: 0.62rem; }
    .cras-lpg-summary-item strong { display: block; margin: 0.15rem 0; color: #172033; font-size: 0.82rem; }

    .cras-lpg-table-wrap {
        width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        scrollbar-gutter: stable;
    }

    .cras-lpg-table {
        width: 100%;
        min-width: 900px;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.7rem;
    }

    .content-wrapper .cras-lpg-table.abah-table-managed {
        width: 100%;
        min-width: 900px;
        table-layout: fixed;
    }

    .cras-lpg-table th {
        padding: 0.58rem 0.62rem;
        border-right: 1px solid #d8e2ec;
        border-bottom: 1px solid #cbd5e1;
        background: #eaf1f8;
        color: #334155;
        font-size: 0.63rem;
        font-weight: 800;
        line-height: 1.3;
        text-transform: uppercase;
        white-space: normal;
        vertical-align: middle;
    }

    .cras-lpg-table td {
        padding: 0.56rem 0.62rem;
        border-right: 1px solid #edf1f5;
        border-bottom: 1px solid #e5eaf0;
        color: #334155;
        line-height: 1.4;
        vertical-align: top;
    }

    .cras-lpg-table tbody tr td:first-child { border-left: 4px solid var(--sac-color); }
    .cras-lpg-table tbody tr:hover td { background: #f8fbfd; }
    .cras-lpg-table .text-right { text-align: right; white-space: nowrap; }
    .cras-lpg-table th:nth-child(1) { width: 18%; }
    .cras-lpg-table th:nth-child(2) { width: 25%; }
    .cras-lpg-table th:nth-child(3),
    .cras-lpg-table th:nth-child(4),
    .cras-lpg-table th:nth-child(5) { width: 10%; }
    .cras-lpg-table th:nth-child(6) { width: 11%; }
    .cras-lpg-table th:nth-child(7) { width: 16%; }
    .cras-lpg-target { min-width: 0; max-width: none; overflow-wrap: anywhere; }
    .cras-lpg-risk { color: #b4232d; font-weight: 800; }

    .cras-sac-sort {
        display: inline-flex;
        flex: 0 0 auto;
        padding: 2px;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: #eef3f8;
    }

    .cras-sac-sort-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        min-height: 34px;
        padding: 0.35rem 0.58rem;
        border: 0;
        border-radius: 4px;
        background: transparent;
        color: #526174;
        font-size: 0.66rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .cras-sac-sort-button.is-active {
        background: #ffffff;
        color: var(--cras-blue);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.13);
    }

    .cras-lpg-category-list {
        display: flex;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 0.28rem;
        min-width: 0;
    }

    .cras-lpg-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.32rem;
        padding: 0.22rem 0.38rem;
        border: 1px solid var(--sac-color);
        border-radius: 4px;
        background: var(--sac-soft);
        color: #25364a;
        font-size: 0.64rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .cras-lpg-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--sac-color); }

    .cras-lpg-empty {
        padding: 2.5rem 1rem;
        color: #64748b;
        text-align: center;
    }

    .cras-empty {
        padding: 4rem 1rem;
        text-align: center;
        color: #64748b;
    }

    .cras-empty i {
        display: block;
        margin-bottom: 0.7rem;
        color: #94a3b8;
        font-size: 2rem;
    }

    .cras-tooltip {
        display: grid;
        min-width: 190px;
        gap: 0.2rem;
        color: #334155;
        font-size: 0.7rem;
    }

    .cras-tooltip strong {
        color: #172033;
        font-size: 0.8rem;
    }

    .cras-tooltip small { color: #64748b; }

    @media (max-width: 1199.98px) {
        .cras-lpg-controls { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-lpg-controls .cras-filter-button { width: 100%; }
        .cras-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-kpi-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .cras-kpi-item:nth-child(3) { border-right: 0; }
        .cras-kpi-item:nth-child(-n + 3) { border-bottom: 1px solid var(--cras-border); }
        .cras-insight-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-insight-item:nth-child(2) { border-right: 0; }
        .cras-insight-item:nth-child(-n + 2) { border-bottom: 1px solid var(--cras-border); }
        .cras-workspace { grid-template-columns: minmax(0, 1fr) 320px; min-height: 540px; }
        .cras-map-shell, #crasPortfolioMap { min-height: 540px; height: 540px; }
        .cras-secondary-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .cras-secondary-item:nth-child(3n) { border-right: 0; }
        .cras-secondary-item:nth-last-child(-n + 6) { border-bottom: 1px solid var(--cras-border); }
        .cras-secondary-item:nth-last-child(-n + 3) { border-bottom: 0; }
    }

    @media (max-width: 991.98px) {
        .cras-workspace { grid-template-columns: minmax(0, 1fr); }
        .cras-side-panel { border-top: 1px solid var(--cras-border); border-left: 0; }
        .cras-map-shell, #crasPortfolioMap { min-height: 500px; height: 500px; }
        .cras-ranking-list { max-height: 220px; }
    }

    @media (max-width: 767.98px) {
        .cras-context-bar { align-items: stretch; flex-direction: column; }
        .cras-context-fields { grid-template-columns: minmax(0, 1fr); }
        .cras-context-bar > .cras-filter-button { width: 100%; }
        .cras-lpg-head, .cras-lpg-table-head { flex-direction: column; }
        .cras-lpg-reference { text-align: left; }
        .cras-sac-sort { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
        .cras-lpg-controls { grid-template-columns: minmax(0, 1fr); }
        .cras-lpg-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-lpg-kpi:nth-child(2n) { border-right: 0; }
        .cras-lpg-kpi:nth-child(-n + 2) { border-bottom: 1px solid var(--cras-border); }
        .cras-map-sac-strip { align-items: flex-start; flex-direction: column; }
        .cras-map-sac-items { justify-content: flex-start; }
        .cras-map-page { padding: 0.65rem; }
        .cras-map-header { align-items: flex-start; flex-direction: column; }
        .cras-filter-head { align-items: flex-start; flex-direction: column; gap: 0.28rem; }
        .cras-filter-primary { grid-template-columns: minmax(0, 1fr); }
        .cras-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-focus-bar { align-items: flex-start; flex-direction: column; }
        .cras-focus-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); width: 100%; }
        .cras-filter-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-kpi-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-kpi-item:nth-child(3) { border-right: 1px solid var(--cras-border); }
        .cras-kpi-item:nth-child(2) { border-right: 0; }
        .cras-kpi-item:nth-child(4) { border-right: 0; }
        .cras-kpi-item:nth-child(-n + 4) { border-bottom: 1px solid var(--cras-border); }
        .cras-insight-grid { grid-template-columns: minmax(0, 1fr); }
        .cras-insight-item,
        .cras-insight-item:nth-child(2) { border-right: 0; border-bottom: 1px solid var(--cras-border); }
        .cras-insight-item:last-child { border-bottom: 0; }
        .cras-map-shell, #crasPortfolioMap { min-height: 420px; height: 420px; }
        .cras-map-legend { width: 155px; }
        .cras-secondary-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-secondary-item:nth-child(3n) { border-right: 1px solid var(--cras-border); }
        .cras-secondary-item:nth-child(2n) { border-right: 0; }
        .cras-secondary-item:nth-last-child(-n + 3) { border-bottom: 1px solid var(--cras-border); }
        .cras-secondary-item:nth-last-child(-n + 2) { border-bottom: 0; }
    }

    @media (max-width: 340px) {
        .cras-view-switch { grid-template-columns: minmax(0, 1fr); }
        .cras-lpg-kpis { grid-template-columns: minmax(0, 1fr); }
        .cras-lpg-kpi, .cras-lpg-kpi:nth-child(2n) { border-right: 0; border-bottom: 1px solid var(--cras-border); }
        .cras-lpg-kpi:last-child { border-bottom: 0; }
        .cras-filter-grid { grid-template-columns: minmax(0, 1fr); }
        .cras-focus-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-kpi-strip { grid-template-columns: minmax(0, 1fr); }
        .cras-kpi-item { border-right: 0; border-bottom: 1px solid var(--cras-border); }
        .cras-kpi-item:last-child { border-bottom: 0; }
        .cras-detail-grid { grid-template-columns: minmax(0, 1fr); }
        .cras-filter-actions { grid-template-columns: minmax(0, 1fr); }
    }
</style>

<main class="cras-map-page" data-cras-map-app data-data-url="{{ $crasMappingDataUrl }}">
    <header class="cras-map-header">
        <div>
            <h1><i class="fas fa-industry text-primary mr-2"></i>{{ $payload['title'] ?? 'Marketshare CRAS LPG' }}</h1>
            <p>{{ $payload['subtitle'] ?? 'Sebaran portofolio kredit per wilayah layanan.' }}</p>
        </div>
        <div class="cras-map-period">
            <i class="fas fa-calendar-alt"></i>
            <span data-cras-updated>{{ $payload['updated_at'] ?? '-' }}</span>
        </div>
    </header>

    @if(!empty($payload['ready']))
        <section class="cras-context-bar" aria-label="Konteks data CRAS">
            <div class="cras-context-fields">
                @foreach($primaryFilterLabels as $key => $label)
                    <label class="cras-filter-field" for="crasFilter{{ Illuminate\Support\Str::studly($key) }}">
                        <span>{{ $label }}</span>
                        <select id="crasFilter{{ Illuminate\Support\Str::studly($key) }}"
                                data-cras-filter="{{ $key }}"
                                @disabled($key === 'wilayah' && !empty($userBranchScope))>
                            @foreach(($filterOptions[$key] ?? []) as $option)
                                <option value="{{ $option['value'] ?? '' }}" @selected(($selectedFilters[$key] ?? 'all') === ($option['value'] ?? ''))>
                                    {{ $option['label'] ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
            <button type="button" class="cras-filter-button" data-cras-context-apply>
                <i class="fas fa-sync-alt" aria-hidden="true"></i><span>Perbarui Cakupan</span>
            </button>
        </section>

        <nav class="cras-view-switch" aria-label="Mode Marketshare CRAS LPG">
            <button type="button" class="cras-view-trigger is-active" data-cras-view-trigger="mapping" aria-pressed="true">
                <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
                <span><strong>Mapping Polygon</strong><small>Peta wilayah dan portofolio unit</small></span>
            </button>
            <button type="button" class="cras-view-trigger" data-cras-view-trigger="sac" aria-pressed="false">
                <i class="fas fa-industry" aria-hidden="true"></i>
                <span><strong>Kategori SAC</strong><small>Prioritas sektor dan kualitas kredit</small></span>
            </button>
        </nav>

        <div class="cras-view-panel" data-cras-view-panel="mapping">
        <section class="cras-filter-panel" aria-label="Filter Marketshare CRAS LPG">
            <div class="cras-filter-head">
                <div>
                    <h2><i class="fas fa-sliders-h mr-1 text-primary" aria-hidden="true"></i> Kendali Pemetaan</h2>
                    <p>Tentukan cakupan data, lalu pilih fokus analisis yang ingin dibandingkan.</p>
                </div>
                <span class="cras-filter-status" data-cras-filter-status>{{ $activePortfolioFilterCount }} filter portofolio aktif</span>
            </div>

            <div class="cras-filter-primary cras-filter-primary--map">
                <label class="cras-filter-field" for="crasHeatMetric">
                    <span>Fokus Peta dan Peringkat</span>
                    <select id="crasHeatMetric" data-cras-heat-metric>
                        @foreach(data_get($payload, 'heatmap.options', []) as $metric)
                            <option value="{{ $metric['key'] ?? '' }}" @selected(data_get($payload, 'heatmap.selected') === ($metric['key'] ?? ''))>
                                {{ $metric['label'] ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="cras-focus-bar">
                <span class="cras-focus-label">Fokus cepat</span>
                <div class="cras-focus-options" role="group" aria-label="Fokus metrik peta">
                    <button type="button" class="cras-focus-option" data-cras-metric-shortcut="baki_debet">
                        <i class="fas fa-wallet" aria-hidden="true"></i>OS
                    </button>
                    <button type="button" class="cras-focus-option" data-cras-metric-shortcut="npl_os">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>NPL
                    </button>
                    <button type="button" class="cras-focus-option" data-cras-metric-shortcut="sml_os">
                        <i class="fas fa-hourglass-half" aria-hidden="true"></i>SML
                    </button>
                    <button type="button" class="cras-focus-option" data-cras-metric-shortcut="jumlah_debitur">
                        <i class="fas fa-users" aria-hidden="true"></i>Debitur
                    </button>
                    <button type="button" class="cras-focus-option" data-cras-metric-shortcut="total_tunggakan">
                        <i class="fas fa-coins" aria-hidden="true"></i>Tunggakan
                    </button>
                </div>
            </div>

            <details class="cras-filter-advanced" data-cras-advanced @if($activePortfolioFilterCount > 0) open @endif>
                <summary>
                    <i class="fas fa-filter text-primary" aria-hidden="true"></i>
                    Filter Portofolio Lanjutan
                    <span class="cras-filter-count" data-cras-filter-count>{{ $activePortfolioFilterCount }}</span>
                </summary>
                <div class="cras-filter-grid">
                    @foreach($portfolioFilterLabels as $key => $label)
                        <label class="cras-filter-field" for="crasFilter{{ Illuminate\Support\Str::studly($key) }}">
                            <span>{{ $label }}</span>
                            <select id="crasFilter{{ Illuminate\Support\Str::studly($key) }}" data-cras-filter="{{ $key }}">
                                @foreach(($filterOptions[$key] ?? []) as $option)
                                    <option value="{{ $option['value'] ?? '' }}" @selected(($selectedFilters[$key] ?? 'all') === ($option['value'] ?? ''))>
                                        {{ $option['label'] ?? '-' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            </details>

            <div class="cras-active-filters" data-cras-active-filters aria-live="polite"></div>

            <div class="cras-filter-actions">
                <button type="button" class="cras-filter-button cras-filter-button--secondary" data-cras-reset title="Reset filter">
                    <i class="fas fa-undo-alt"></i><span>Reset</span>
                </button>
                <button type="button" class="cras-filter-button" data-cras-apply>
                    <i class="fas fa-filter"></i><span>Terapkan</span>
                </button>
            </div>
        </section>

        <section class="cras-kpi-strip" aria-live="polite">
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-wallet"></i> OS / Baki Debet</span>
                <strong class="cras-kpi-value" data-cras-kpi="baki_debet">-</strong>
            </div>
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-file-invoice-dollar"></i> Plafon</span>
                <strong class="cras-kpi-value" data-cras-kpi="plafond">-</strong>
            </div>
            <div class="cras-kpi-item" data-tone="npl">
                <span class="cras-kpi-label"><i class="fas fa-exclamation-circle"></i> NPL</span>
                <strong class="cras-kpi-value" data-cras-kpi="npl_os">-</strong>
            </div>
            <div class="cras-kpi-item" data-tone="sml">
                <span class="cras-kpi-label"><i class="fas fa-hourglass-half"></i> SML</span>
                <strong class="cras-kpi-value" data-cras-kpi="sml_os">-</strong>
            </div>
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-users"></i> Debitur</span>
                <strong class="cras-kpi-value" data-cras-kpi="jumlah_debitur">-</strong>
            </div>
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-coins"></i> Total Tunggakan</span>
                <strong class="cras-kpi-value" data-cras-kpi="total_tunggakan">-</strong>
            </div>
        </section>

        <section class="cras-map-sac-strip" aria-label="Ringkasan warna kategori SAC">
            <div class="cras-map-sac-title">
                <i class="fas fa-palette" aria-hidden="true"></i>
                <span><strong>Komposisi SAC</strong><small>Sentuhan kategori industri pada cakupan mapping aktif</small></span>
            </div>
            <div class="cras-map-sac-items" data-cras-map-sac-summary></div>
        </section>

        <section class="cras-insight-strip" aria-labelledby="crasInsightTitle">
            <div class="cras-insight-head">
                <strong id="crasInsightTitle">Wilayah yang Perlu Dilihat</strong>
                <span>Klik wilayah untuk menyorot polygon</span>
            </div>
            <div class="cras-insight-grid">
                <button type="button" class="cras-insight-item" data-cras-insight="baki_debet" data-metric="baki_debet">
                    <span class="cras-insight-icon"><i class="fas fa-wallet" aria-hidden="true"></i></span>
                    <span>
                        <span class="cras-insight-label">OS Terbesar</span>
                        <span class="cras-insight-name" data-cras-insight-name>Menunggu peta</span>
                        <span class="cras-insight-value" data-cras-insight-value>-</span>
                    </span>
                </button>
                <button type="button" class="cras-insight-item" data-cras-insight="npl_os" data-metric="npl_os">
                    <span class="cras-insight-icon"><i class="fas fa-exclamation-circle" aria-hidden="true"></i></span>
                    <span>
                        <span class="cras-insight-label">NPL Terbesar</span>
                        <span class="cras-insight-name" data-cras-insight-name>Menunggu peta</span>
                        <span class="cras-insight-value" data-cras-insight-value>-</span>
                    </span>
                </button>
                <button type="button" class="cras-insight-item" data-cras-insight="sml_os" data-metric="sml_os">
                    <span class="cras-insight-icon"><i class="fas fa-hourglass-half" aria-hidden="true"></i></span>
                    <span>
                        <span class="cras-insight-label">SML Terbesar</span>
                        <span class="cras-insight-name" data-cras-insight-name>Menunggu peta</span>
                        <span class="cras-insight-value" data-cras-insight-value>-</span>
                    </span>
                </button>
                <button type="button" class="cras-insight-item" data-cras-insight="total_tunggakan" data-metric="total_tunggakan">
                    <span class="cras-insight-icon"><i class="fas fa-coins" aria-hidden="true"></i></span>
                    <span>
                        <span class="cras-insight-label">Tunggakan Terbesar</span>
                        <span class="cras-insight-name" data-cras-insight-name>Menunggu peta</span>
                        <span class="cras-insight-value" data-cras-insight-value>-</span>
                    </span>
                </button>
            </div>
        </section>

        <section class="cras-workspace">
            <div class="cras-map-shell">
                <div id="crasPortfolioMap" role="application" aria-label="Peta polygon portofolio SSA CRAS"></div>
                <div class="cras-map-loading" data-cras-loading>Memuat polygon dan portofolio CRAS...</div>
                <div class="cras-map-toolbar">
                    <button type="button" class="cras-map-reset" data-cras-map-reset title="Tampilkan kembali seluruh wilayah">
                        <i class="fas fa-expand-arrows-alt" aria-hidden="true"></i>
                        <span>Seluruh Wilayah</span>
                    </button>
                </div>
                <div class="cras-map-legend">
                    <strong data-cras-legend-title>Baki Debet</strong>
                    <div class="cras-map-legend-scale" data-cras-legend-scale aria-hidden="true"></div>
                    <div class="cras-map-legend-labels">
                        <span data-cras-legend-min>Rendah</span>
                        <span data-cras-legend-max>Tinggi</span>
                    </div>
                </div>
            </div>

            <aside class="cras-side-panel">
                <div class="cras-side-head">
                    <span class="cras-side-eyebrow">Wilayah Aktif</span>
                    <h2 data-cras-detail-title>Seluruh Area 6</h2>
                    <p data-cras-detail-subtitle>Ringkasan seluruh unit kerja yang sesuai filter.</p>
                </div>
                <div class="cras-detail-grid" data-cras-detail-grid></div>
                <div class="cras-ranking-head">
                    <div class="cras-ranking-heading">
                        <span data-cras-ranking-title>Peringkat OS / Baki Debet</span>
                        <small data-cras-ranking-count>-</small>
                    </div>
                    <div class="cras-ranking-modes" role="group" aria-label="Tingkat peringkat wilayah">
                        <button type="button" class="cras-ranking-mode is-active" data-cras-ranking-mode="district">Kecamatan</button>
                        <button type="button" class="cras-ranking-mode" data-cras-ranking-mode="unit">Unit</button>
                    </div>
                </div>
                <div class="cras-ranking-list" data-cras-ranking></div>
            </aside>
        </section>

        <section class="cras-detail-band">
            <div class="cras-section-head">
                <h2>CKPN, Penanganan Kredit, dan Tunggakan</h2>
                <span data-cras-source-count>-</span>
            </div>
            <div class="cras-secondary-metrics" data-cras-secondary-metrics></div>
        </section>

        <section class="cras-unit-section">
            <div class="cras-section-head">
                <h2>Rincian Unit Kerja</h2>
                <span data-cras-coverage>-</span>
            </div>
            <div class="cras-table-wrap">
                <table class="cras-unit-table">
                    <thead>
                        <tr>
                            <th>Unit Kerja</th>
                            <th>Wilayah</th>
                            <th class="text-right">OS / Baki Debet</th>
                            <th class="text-right">NPL</th>
                            <th class="text-right">SML</th>
                            <th class="text-right">Debitur</th>
                            <th class="text-right" data-cras-table-metric-head>Metrik Peta</th>
                        </tr>
                    </thead>
                    <tbody data-cras-unit-table></tbody>
                </table>
            </div>
        </section>
        </div>

        <div class="cras-view-panel" data-cras-view-panel="sac" hidden>
            <section class="cras-lpg-panel" aria-labelledby="crasLpgTitle">
                <div class="cras-lpg-head">
                    <div>
                        <h2 id="crasLpgTitle"><i class="fas fa-industry mr-1 text-primary" aria-hidden="true"></i> Sector Acceptance Criteria LPG</h2>
                        <p data-cras-lpg-scope>{{ data_get($lpgPayload, 'scope_note', 'Pemetaan sektor ekonomi ke sektor industri untuk Micro dan Small.') }}</p>
                    </div>
                    <div class="cras-lpg-reference">
                        <span>Referensi: {{ data_get($lpgPayload, 'reference.file', 'SSA CRAS OLAH LPG') }}</span><br>
                        <span data-cras-lpg-row-count>{{ count(data_get($lpgPayload, 'industry_rows', [])) }} subsektor industri</span>
                    </div>
                </div>

                @if(!empty($lpgPayload['ready']))
                    <div class="cras-sac-legend" aria-label="Arti kategori warna SAC">
                        @foreach(['hijau', 'hijau_muda', 'kuning', 'merah'] as $colorKey)
                            @php($definition = data_get($lpgPayload, 'color_definitions.'.$colorKey, []))
                            <div class="cras-sac-legend-item" data-sac-color="{{ $colorKey }}">
                                <span class="cras-lpg-dot" aria-hidden="true"></span>
                                <strong>{{ data_get($definition, 'label', Illuminate\Support\Str::headline($colorKey)) }}</strong>
                                <small>{{ data_get($definition, 'meaning', '-') }}</small>
                            </div>
                        @endforeach
                    </div>

                    <div class="cras-lpg-controls">
                        @foreach([
                            'segment' => 'Segmen LPG',
                            'color' => 'Kategori SAC',
                            'industry_sector' => 'Sektor Industri',
                            'industry_sub_sector' => 'Subsektor Industri',
                        ] as $key => $label)
                            <label class="cras-filter-field" for="crasLpgFilter{{ Illuminate\Support\Str::studly($key) }}">
                                <span>{{ $label }}</span>
                                <select id="crasLpgFilter{{ Illuminate\Support\Str::studly($key) }}" data-cras-lpg-filter="{{ $key }}">
                                    @foreach(($lpgFilterOptions[$key] ?? []) as $option)
                                        <option value="{{ $option['value'] ?? '' }}" @selected(($lpgSelectedFilters[$key] ?? 'all') === ($option['value'] ?? ''))>
                                            {{ $option['label'] ?? '-' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                        <button type="button" class="cras-filter-button" data-cras-lpg-apply>
                            <i class="fas fa-filter" aria-hidden="true"></i><span>Terapkan</span>
                        </button>
                    </div>

                    <div class="cras-lpg-kpis" aria-live="polite">
                        <div class="cras-lpg-kpi"><span>Total OS</span><strong data-cras-lpg-kpi="eligible_os">-</strong></div>
                        <div class="cras-lpg-kpi" data-tone="sml"><span>SML</span><strong data-cras-lpg-kpi="sml">-</strong></div>
                        <div class="cras-lpg-kpi" data-tone="npl"><span>NPL</span><strong data-cras-lpg-kpi="npl">-</strong></div>
                        <div class="cras-lpg-kpi" data-tone="npl"><span>NPL terhadap OS</span><strong data-cras-lpg-kpi="npl_ratio">-</strong></div>
                    </div>

                    <div class="cras-lpg-table-head">
                        <div>
                            <h3 data-cras-sac-table-title>Prioritas Subsektor Industri berdasarkan OS Terbesar</h3>
                            <p data-cras-sac-table-description>Nominal SML dan NPL dihitung terhadap OS pada subsektor industri yang sama.</p>
                        </div>
                        <div class="cras-sac-sort" role="group" aria-label="Urutan prioritas SAC">
                            <button type="button" class="cras-sac-sort-button @if(($lpgSelectedFilters['sort'] ?? 'os_desc') === 'os_desc') is-active @endif" data-cras-lpg-sort="os_desc">
                                <i class="fas fa-sort-amount-down" aria-hidden="true"></i> OS Terbesar
                            </button>
                            <button type="button" class="cras-sac-sort-button @if(($lpgSelectedFilters['sort'] ?? 'os_desc') === 'npl_ratio_desc') is-active @endif" data-cras-lpg-sort="npl_ratio_desc">
                                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i> NPL &gt; 5%
                            </button>
                        </div>
                    </div>
                    <div class="cras-lpg-table-wrap">
                        <table class="cras-lpg-table">
                            <thead>
                                <tr>
                                    <th>Sektor Industri</th>
                                    <th>Subsektor Industri</th>
                                    <th class="text-right">OS</th>
                                    <th class="text-right">SML</th>
                                    <th class="text-right">NPL</th>
                                    <th class="text-right">NPL terhadap OS</th>
                                    <th>Kategori SAC</th>
                                </tr>
                            </thead>
                            <tbody data-cras-lpg-table></tbody>
                        </table>
                        <div class="cras-lpg-empty" data-cras-lpg-empty hidden>Tidak ada subsektor industri yang sesuai dengan filter SAC.</div>
                    </div>
                @else
                    <div class="cras-lpg-empty">{{ data_get($lpgPayload, 'message', 'Referensi LPG belum tersedia.') }}</div>
                @endif
            </section>
        </div>
    @else
        <section class="cras-filter-panel cras-empty">
            <i class="fas fa-map"></i>
            <strong>{{ $payload['message'] ?? 'Data SSA CRAS belum tersedia.' }}</strong>
        </section>
    @endif
</main>

<script type="application/json" id="crasMappingPayload">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet-1.9.4/leaflet.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const app = document.querySelector('[data-cras-map-app]');
    const payloadElement = document.getElementById('crasMappingPayload');
    const mapElement = document.getElementById('crasPortfolioMap');
    if (!app || !payloadElement || !mapElement || typeof window.L === 'undefined') {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(payloadElement.textContent || '{}');
    } catch (_) {
        return;
    }
    if (!payload.ready) {
        return;
    }

    const dataUrl = app.dataset.dataUrl || '';
    const filterElements = Array.from(app.querySelectorAll('[data-cras-filter]'));
    const heatMetric = app.querySelector('[data-cras-heat-metric]');
    const applyButton = app.querySelector('[data-cras-apply]');
    const contextApplyButton = app.querySelector('[data-cras-context-apply]');
    const resetButton = app.querySelector('[data-cras-reset]');
    const mapResetButton = app.querySelector('[data-cras-map-reset]');
    const loading = app.querySelector('[data-cras-loading]');
    const detailTitle = app.querySelector('[data-cras-detail-title]');
    const detailSubtitle = app.querySelector('[data-cras-detail-subtitle]');
    const detailGrid = app.querySelector('[data-cras-detail-grid]');
    const ranking = app.querySelector('[data-cras-ranking]');
    const rankingTitle = app.querySelector('[data-cras-ranking-title]');
    const rankingCount = app.querySelector('[data-cras-ranking-count]');
    const tableBody = app.querySelector('[data-cras-unit-table]');
    const tableMetricHead = app.querySelector('[data-cras-table-metric-head]');
    const lpgFilterElements = Array.from(app.querySelectorAll('[data-cras-lpg-filter]'));
    const lpgApplyButton = app.querySelector('[data-cras-lpg-apply]');
    const lpgTableBody = app.querySelector('[data-cras-lpg-table]');
    const lpgEmpty = app.querySelector('[data-cras-lpg-empty]');
    const lpgMapSummary = app.querySelector('[data-cras-map-sac-summary]');
    const lpgSortButtons = Array.from(app.querySelectorAll('[data-cras-lpg-sort]'));
    const lpgTableTitle = app.querySelector('[data-cras-sac-table-title]');
    const lpgTableDescription = app.querySelector('[data-cras-sac-table-description]');
    const viewTriggers = Array.from(app.querySelectorAll('[data-cras-view-trigger]'));
    const viewPanels = Array.from(app.querySelectorAll('[data-cras-view-panel]'));
    const activeFilters = app.querySelector('[data-cras-active-filters]');
    const filterCount = app.querySelector('[data-cras-filter-count]');
    const filterStatus = app.querySelector('[data-cras-filter-status]');
    const metricShortcuts = Array.from(app.querySelectorAll('[data-cras-metric-shortcut]'));
    const rankingModeButtons = Array.from(app.querySelectorAll('[data-cras-ranking-mode]'));
    const insightButtons = Array.from(app.querySelectorAll('[data-cras-insight]'));
    const legendScale = app.querySelector('[data-cras-legend-scale]');
    const legendMinimum = app.querySelector('[data-cras-legend-min]');
    const legendMaximum = app.querySelector('[data-cras-legend-max]');
    const metricDefinitions = () => Array.isArray(payload.metric_definitions) ? payload.metric_definitions : [];
    const metricByKey = () => new Map(metricDefinitions().map(function (metric) { return [String(metric.key), metric]; }));
    const palettes = {
        volume: ['#e8eef5', '#c8daeb', '#91b8da', '#548fc3', '#1e6bab', '#084b87'],
        npl: ['#fff1f3', '#fbcbd4', '#ee91a3', '#d95672', '#bd294a', '#8f1734'],
        sml: ['#fff8e8', '#f8e4b9', '#edc878', '#d99e32', '#b97813', '#8f5209'],
        recovery: ['#eaf7f4', '#c2e6de', '#89ccbd', '#4cac98', '#218979', '#0f6258'],
    };
    const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const compactFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    let geoData = null;
    let geoLayer = null;
    let fullBounds = null;
    let rankingMode = 'district';
    let activeView = new URLSearchParams(window.location.search).get('view') === 'sac' ? 'sac' : 'mapping';
    let lpgSort = String(payload.lpg?.filters?.selected?.sort || 'os_desc');
    let districtMetricCache = new Map();

    const map = window.L.map(mapElement, {
        zoomControl: true,
        attributionControl: true,
        minZoom: 8,
        maxZoom: 13,
        zoomSnap: 0.25,
        preferCanvas: false,
    });
    const renderer = window.L.svg({ padding: 0.5 });
    map.attributionControl.setPrefix(false);
    map.attributionControl.addAttribution('Polygon: Badan Informasi Geospasial');

    function setActiveView(view, updateUrl) {
        activeView = view === 'sac' ? 'sac' : 'mapping';
        viewTriggers.forEach(function (button) {
            const selected = button.dataset.crasViewTrigger === activeView;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        viewPanels.forEach(function (panel) {
            panel.hidden = panel.dataset.crasViewPanel !== activeView;
        });
        if (activeView === 'mapping') {
            window.requestAnimationFrame(function () { map.invalidateSize(false); });
        }
        if (updateUrl) {
            window.history.replaceState({}, '', window.location.pathname + '?' + requestParams().toString());
        }
    }

    function currentMetric() {
        return String(heatMetric?.value || payload.heatmap?.selected || 'baki_debet');
    }

    function metricDefinition(key) {
        return metricByKey().get(String(key)) || { key: key, label: key, format: 'currency' };
    }

    function formatMetric(value, key, compact) {
        const numeric = Number(value || 0);
        const definition = metricDefinition(key);
        if (definition.format === 'count') {
            return numberFormat.format(Math.round(numeric));
        }
        if (definition.format === 'percent') {
            return compactFormat.format(numeric) + '%';
        }
        if (!compact) {
            return 'Rp ' + numberFormat.format(Math.round(numeric));
        }

        const absolute = Math.abs(numeric);
        if (absolute >= 1e12) return 'Rp ' + compactFormat.format(numeric / 1e12) + ' T';
        if (absolute >= 1e9) return 'Rp ' + compactFormat.format(numeric / 1e9) + ' M';
        if (absolute >= 1e6) return 'Rp ' + compactFormat.format(numeric / 1e6) + ' Jt';
        return 'Rp ' + numberFormat.format(Math.round(numeric));
    }

    function renderLpg() {
        const lpg = payload.lpg || {};
        if (!lpg.ready) return;
        const coverage = lpg.coverage || {};
        const metrics = lpg.metrics || {};
        const kpiValues = {
            eligible_os: formatMetric(coverage.eligible_os, 'baki_debet', true),
            sml: formatMetric(metrics.sml, 'sml_os', true),
            npl: formatMetric(metrics.npl, 'npl_os', true),
            npl_ratio: compactFormat.format(Number(metrics.npl_ratio || 0)) + '%',
        };
        Object.keys(kpiValues).forEach(function (key) {
            const node = app.querySelector('[data-cras-lpg-kpi="' + key + '"]');
            if (node) node.textContent = kpiValues[key];
        });
        const rowCount = app.querySelector('[data-cras-lpg-row-count]');
        if (rowCount) {
            rowCount.textContent = numberFormat.format((lpg.industry_rows || []).length) + ' subsektor industri | coverage '
                + compactFormat.format(Number(coverage.mapping_ratio || 0)) + '%';
        }

        if (lpgMapSummary) {
            lpgMapSummary.innerHTML = '';
            (lpg.summary || []).filter(function (item) {
                return ['hijau', 'hijau_muda', 'kuning', 'merah'].includes(String(item.key || ''));
            }).forEach(function (item) {
                const badge = document.createElement('span');
                const dot = document.createElement('span');
                badge.className = 'cras-map-sac-item';
                badge.dataset.sacColor = String(item.key || 'unmapped');
                dot.className = 'cras-lpg-dot';
                badge.append(dot, document.createTextNode(String(item.label || '-') + ' ' + formatMetric(item.baki_debet, 'baki_debet', true)));
                lpgMapSummary.appendChild(badge);
            });
        }

        if (!lpgTableBody) return;
        lpgSort = String(lpg.filters?.selected?.sort || lpgSort || 'os_desc');
        lpgSortButtons.forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.crasLpgSort === lpgSort);
        });
        const riskMode = lpgSort === 'npl_ratio_desc';
        if (lpgTableTitle) {
            lpgTableTitle.textContent = riskMode
                ? 'Prioritas Subsektor dengan Rasio NPL di atas 5%'
                : 'Prioritas Subsektor Industri berdasarkan OS Terbesar';
        }
        if (lpgTableDescription) {
            lpgTableDescription.textContent = riskMode
                ? 'Hanya subsektor dengan NPL terhadap OS lebih dari 5%, diurutkan dari rasio tertinggi.'
                : 'Seluruh subsektor diurutkan dari OS terbesar; SML dan NPL dihitung pada OS subsektor yang sama.';
        }

        lpgTableBody.innerHTML = '';
        (lpg.industry_rows || []).forEach(function (item) {
            const row = document.createElement('tr');
            const categories = Array.isArray(item.sac_categories) ? item.sac_categories : [];
            row.dataset.sacColor = String(categories[0]?.color || 'unmapped');

            const industrySector = document.createElement('td');
            industrySector.className = 'cras-lpg-target';
            industrySector.textContent = String(item.industry_sector || '-');

            const industrySubSector = document.createElement('td');
            industrySubSector.className = 'cras-lpg-target';
            industrySubSector.textContent = String(item.industry_sub_sector || '-');

            const os = document.createElement('td');
            os.className = 'text-right';
            os.textContent = formatMetric(item.baki_debet, 'baki_debet', true);
            os.title = formatMetric(item.baki_debet, 'baki_debet', false);

            const sml = document.createElement('td');
            sml.className = 'text-right';
            sml.textContent = formatMetric(item.sml, 'sml_os', true);
            sml.title = formatMetric(item.sml, 'sml_os', false) + ' (' + compactFormat.format(Number(item.sml_ratio || 0)) + '% dari OS)';

            const npl = document.createElement('td');
            npl.className = 'text-right';
            npl.textContent = formatMetric(item.npl, 'npl_os', true);
            npl.title = formatMetric(item.npl, 'npl_os', false);

            const nplRatio = document.createElement('td');
            nplRatio.className = 'text-right' + (Number(item.npl_ratio || 0) > 5 ? ' cras-lpg-risk' : '');
            nplRatio.textContent = compactFormat.format(Number(item.npl_ratio || 0)) + '%';

            const colorCell = document.createElement('td');
            const categoryList = document.createElement('div');
            categoryList.className = 'cras-lpg-category-list';
            categories.forEach(function (category) {
                const badge = document.createElement('span');
                const dot = document.createElement('span');
                badge.className = 'cras-lpg-badge';
                badge.dataset.sacColor = String(category.color || 'unmapped');
                dot.className = 'cras-lpg-dot';
                const prefix = categories.length > 1 ? String(category.segment_label || '-') + ': ' : '';
                badge.append(dot, document.createTextNode(prefix + String(category.color_label || '-')));
                badge.title = String(category.meaning || '-');
                categoryList.appendChild(badge);
            });
            if (!categories.length) {
                categoryList.textContent = '-';
            }
            colorCell.appendChild(categoryList);

            row.append(industrySector, industrySubSector, os, sml, npl, nplRatio, colorCell);
            lpgTableBody.appendChild(row);
        });
        if (lpgEmpty) lpgEmpty.hidden = (lpg.industry_rows || []).length > 0;
    }

    function metricPalette(key) {
        const metric = String(key || '');
        if (metric.startsWith('npl_')) return palettes.npl;
        if (metric.startsWith('sml_')) return palettes.sml;
        if (['realisasi_ph', 'recovery_total', 'saldo_ph'].includes(metric)) return palettes.recovery;
        return palettes.volume;
    }

    function districtMetrics(code) {
        const cacheKey = String(code);
        if (districtMetricCache.has(cacheKey)) {
            return districtMetricCache.get(cacheKey);
        }
        const totals = {};
        metricDefinitions().forEach(function (metric) { totals[metric.key] = 0; });
        (payload.units || []).forEach(function (unit) {
            const codes = Array.isArray(unit.district_codes) ? unit.district_codes.map(String) : [];
            if (!codes.includes(String(code))) return;
            const divisor = Math.max(1, codes.length);
            metricDefinitions().forEach(function (metric) {
                if (metric.format === 'percent') return;
                totals[metric.key] += Number(unit.values?.[metric.key] || 0) / divisor;
            });
        });
        totals.npl_ratio = Number(totals.baki_debet || 0) > 0
            ? (Number(totals.npl_os || 0) / Number(totals.baki_debet || 0)) * 100
            : 0;
        totals.sml_ratio = Number(totals.baki_debet || 0) > 0
            ? (Number(totals.sml_os || 0) / Number(totals.baki_debet || 0)) * 100
            : 0;
        districtMetricCache.set(cacheKey, totals);
        return totals;
    }

    function visibleFeatures() {
        const codes = new Set((payload.units || []).flatMap(function (unit) {
            return Array.isArray(unit.district_codes) ? unit.district_codes.map(String) : [];
        }));
        return (geoData?.features || []).filter(function (feature) {
            return codes.has(String(feature.properties?.KDCPUM || ''));
        });
    }

    function districtRows() {
        return visibleFeatures().map(function (feature) {
            const code = String(feature.properties?.KDCPUM || '');
            return {
                code: code,
                name: String(feature.properties?.WADMKC || 'Kecamatan'),
                regency: String(feature.properties?.WADMKK || '-'),
                feature: feature,
                metrics: districtMetrics(code),
                units: districtUnits(code),
            };
        });
    }

    function heatContext(features) {
        const values = features
            .map(function (feature) {
                return Number(districtMetrics(String(feature.properties?.KDCPUM || ''))[currentMetric()] || 0);
            })
            .filter(function (value) { return value > 0; })
            .sort(function (left, right) { return left - right; });
        const palette = metricPalette(currentMetric());
        const thresholds = [];
        for (let index = 1; index < palette.length; index++) {
            const position = Math.min(values.length - 1, Math.max(0, Math.ceil((index / palette.length) * values.length) - 1));
            thresholds.push(values.length ? values[position] : 0);
        }

        return {
            palette: palette,
            thresholds: thresholds,
            minimum: values.length ? values[0] : 0,
            maximum: values.length ? values[values.length - 1] : 0,
        };
    }

    function heatColor(value, context) {
        if (!(value > 0) || !(context.maximum > 0)) return '#eef2f7';
        let index = 0;
        while (index < context.thresholds.length && value >= context.thresholds[index]) {
            index++;
        }
        return context.palette[Math.min(context.palette.length - 1, index)];
    }

    function districtUnits(code) {
        return (payload.units || []).filter(function (unit) {
            return (unit.district_codes || []).map(String).includes(String(code));
        });
    }

    function tooltipElement(feature, metrics, units) {
        const node = document.createElement('div');
        node.className = 'cras-tooltip';
        const title = document.createElement('strong');
        const region = document.createElement('small');
        title.textContent = String(feature.properties?.WADMKC || 'Kecamatan');
        region.textContent = String(feature.properties?.WADMKK || '-') + ' | ' + units.length + ' unit kerja';
        node.append(title, region);
        ['baki_debet', 'npl_os', 'sml_os', 'jumlah_debitur', currentMetric()].filter(function (key, index, values) {
            return values.indexOf(key) === index;
        }).forEach(function (key) {
            const row = document.createElement('span');
            row.textContent = metricDefinition(key).label + ': ' + formatMetric(metrics[key], key, true);
            node.appendChild(row);
        });
        return node;
    }

    function renderMap(fit) {
        if (!geoData) return;
        const features = visibleFeatures();
        const context = heatContext(features);
        if (geoLayer) map.removeLayer(geoLayer);

        geoLayer = window.L.geoJSON({ type: 'FeatureCollection', features: features }, {
            renderer: renderer,
            style: function (feature) {
                const value = Number(districtMetrics(String(feature.properties?.KDCPUM || ''))[currentMetric()] || 0);
                return {
                    color: '#ffffff',
                    weight: 1.2,
                    opacity: 1,
                    fillColor: heatColor(value, context),
                    fillOpacity: value > 0 ? 0.93 : 0.58,
                };
            },
            onEachFeature: function (feature, layer) {
                const code = String(feature.properties?.KDCPUM || '');
                const metrics = districtMetrics(code);
                const units = districtUnits(code);
                layer.bindTooltip(tooltipElement(feature, metrics, units), { sticky: true, direction: 'top', opacity: 0.98 });
                layer.on({
                    mouseover: function () {
                        layer.setStyle({ weight: 2.4, color: '#12395f', fillOpacity: 1 });
                        layer.bringToFront();
                    },
                    mouseout: function () { geoLayer.resetStyle(layer); },
                    click: function () {
                        renderDetail(String(feature.properties?.WADMKC || 'Kecamatan'), String(feature.properties?.WADMKK || '-'), metrics);
                        map.fitBounds(layer.getBounds(), { padding: [24, 24], maxZoom: 11.5 });
                    },
                });
            },
        }).addTo(map);

        const bounds = geoLayer.getBounds();
        if (geoLayer.getLayers().length && bounds.isValid()) {
            map.invalidateSize({ pan: false });
            if (fit !== false) map.fitBounds(bounds, { padding: [18, 18], maxZoom: 10.5 });
            if (!fullBounds) {
                fullBounds = bounds.pad(0.12);
                map.setMaxBounds(fullBounds);
            }
            loading?.classList.add('is-hidden');
        } else if (loading) {
            loading.textContent = 'Tidak ada polygon yang sesuai dengan kombinasi filter ini.';
            loading.classList.remove('is-hidden');
        }
        renderLegend(context);
    }

    function renderLegend(context) {
        const palette = context?.palette || metricPalette(currentMetric());
        if (legendScale) {
            legendScale.style.background = 'linear-gradient(90deg, ' + palette.join(', ') + ')';
        }
        if (legendMinimum) {
            legendMinimum.textContent = context?.minimum > 0
                ? formatMetric(context.minimum, currentMetric(), true)
                : 'Tidak ada';
        }
        if (legendMaximum) {
            legendMaximum.textContent = context?.maximum > 0
                ? formatMetric(context.maximum, currentMetric(), true)
                : 'Tidak ada';
        }
    }

    function renderDetail(title, subtitle, metrics) {
        if (detailTitle) detailTitle.textContent = title;
        if (detailSubtitle) detailSubtitle.textContent = subtitle;
        if (!detailGrid) return;
        detailGrid.innerHTML = '';
        ['baki_debet', 'npl_os', 'npl_ratio', 'sml_os', 'sml_ratio', 'jumlah_debitur', 'plafond', 'total_tunggakan'].forEach(function (key) {
            const item = document.createElement('div');
            const label = document.createElement('span');
            const value = document.createElement('strong');
            item.className = 'cras-detail-item';
            label.textContent = metricDefinition(key).label;
            value.textContent = formatMetric(metrics?.[key], key, true);
            value.title = formatMetric(metrics?.[key], key, false);
            item.append(label, value);
            detailGrid.appendChild(item);
        });
    }

    function renderKpis() {
        ['baki_debet', 'plafond', 'npl_os', 'sml_os', 'jumlah_debitur', 'total_tunggakan'].forEach(function (key) {
            const node = app.querySelector('[data-cras-kpi="' + key + '"]');
            if (!node) return;
            node.textContent = formatMetric(payload.metrics?.[key], key, true);
            node.title = formatMetric(payload.metrics?.[key], key, false);
        });
    }

    function renderSecondaryMetrics() {
        const container = app.querySelector('[data-cras-secondary-metrics]');
        if (!container) return;
        container.innerHTML = '';
        ['npl_debitur', 'sml_debitur', 'npl_ratio', 'sml_ratio', 'biaya_ckpn', 'ckpn_mo', 'realisasi_ph', 'recovery_total', 'saldo_ph', 'tunggakan_bunga', 'tunggakan_kecil', 'tunggakan_pokok', 'jumlah_rekening'].forEach(function (key) {
            const item = document.createElement('div');
            const label = document.createElement('span');
            const value = document.createElement('strong');
            item.className = 'cras-secondary-item';
            label.textContent = metricDefinition(key).label;
            value.textContent = formatMetric(payload.metrics?.[key], key, true);
            value.title = formatMetric(payload.metrics?.[key], key, false);
            item.append(label, value);
            container.appendChild(item);
        });
    }

    function sortedUnits() {
        const key = currentMetric();
        return (payload.units || []).slice().sort(function (left, right) {
            return Number(right.values?.[key] || 0) - Number(left.values?.[key] || 0);
        });
    }

    function sortedDistricts(metricKey) {
        const key = String(metricKey || currentMetric());
        return districtRows().sort(function (left, right) {
            return Number(right.metrics?.[key] || 0) - Number(left.metrics?.[key] || 0);
        });
    }

    function focusUnit(unit) {
        const codes = new Set((unit.district_codes || []).map(String));
        const layers = [];
        geoLayer?.eachLayer(function (layer) {
            if (codes.has(String(layer.feature?.properties?.KDCPUM || ''))) layers.push(layer);
        });
        if (!layers.length) return;
        const group = window.L.featureGroup(layers);
        map.fitBounds(group.getBounds(), { padding: [26, 26], maxZoom: 11.5 });
        renderDetail(unit.name, unit.branch, unit.values || {});
    }

    function focusDistrict(district) {
        let targetLayer = null;
        geoLayer?.eachLayer(function (layer) {
            if (String(layer.feature?.properties?.KDCPUM || '') === String(district.code)) {
                targetLayer = layer;
            }
        });
        if (!targetLayer) return;
        map.fitBounds(targetLayer.getBounds(), { padding: [26, 26], maxZoom: 11.5 });
        renderDetail(district.name, district.regency + ' | ' + district.units.length + ' unit kerja', district.metrics || {});
        targetLayer.openTooltip();
    }

    function renderRanking() {
        if (!ranking) return;
        const key = currentMetric();
        const entries = rankingMode === 'unit'
            ? sortedUnits().slice(0, 12).map(function (unit) {
                return {
                    name: unit.name,
                    meta: unit.branch,
                    value: Number(unit.values?.[key] || 0),
                    focus: function () { focusUnit(unit); },
                };
            })
            : sortedDistricts(key).slice(0, 12).map(function (district) {
                return {
                    name: district.name,
                    meta: district.regency + ' | ' + district.units.length + ' unit',
                    value: Number(district.metrics?.[key] || 0),
                    focus: function () { focusDistrict(district); },
                };
            });
        ranking.innerHTML = '';
        entries.forEach(function (entry, index) {
            const row = document.createElement('button');
            const order = document.createElement('span');
            const nameWrap = document.createElement('span');
            const name = document.createElement('span');
            const meta = document.createElement('small');
            const value = document.createElement('strong');
            row.type = 'button';
            row.className = 'cras-ranking-row';
            order.className = 'cras-ranking-order';
            name.className = 'cras-ranking-name';
            meta.className = 'cras-ranking-meta';
            order.textContent = String(index + 1);
            name.textContent = entry.name;
            meta.textContent = entry.meta;
            value.textContent = formatMetric(entry.value, key, true);
            nameWrap.append(name, meta);
            row.title = entry.name;
            row.append(order, nameWrap, value);
            row.addEventListener('click', entry.focus);
            ranking.appendChild(row);
        });
        if (rankingTitle) rankingTitle.textContent = 'Peringkat ' + metricDefinition(key).label;
        if (rankingCount) rankingCount.textContent = entries.length + (rankingMode === 'unit' ? ' unit kerja' : ' kecamatan');
        rankingModeButtons.forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.crasRankingMode === rankingMode);
        });
    }

    function renderTable() {
        if (!tableBody) return;
        const key = currentMetric();
        if (tableMetricHead) tableMetricHead.textContent = metricDefinition(key).label;
        tableBody.innerHTML = '';
        sortedUnits().forEach(function (unit) {
            const row = document.createElement('tr');
            [
                unit.name,
                unit.branch,
                formatMetric(unit.values?.baki_debet, 'baki_debet', true),
                formatMetric(unit.values?.npl_os, 'npl_os', true),
                formatMetric(unit.values?.sml_os, 'sml_os', true),
                formatMetric(unit.values?.jumlah_debitur, 'jumlah_debitur', true),
                formatMetric(unit.values?.[key], key, true),
            ].forEach(function (text, index) {
                const cell = document.createElement('td');
                cell.textContent = text;
                if (index >= 2) cell.className = 'text-right';
                row.appendChild(cell);
            });
            row.addEventListener('click', function () { focusUnit(unit); });
            tableBody.appendChild(row);
        });
    }

    function renderMeta() {
        const coverage = payload.coverage || {};
        const source = app.querySelector('[data-cras-source-count]');
        const coverageNode = app.querySelector('[data-cras-coverage]');
        const updated = app.querySelector('[data-cras-updated]');
        const legend = app.querySelector('[data-cras-legend-title]');
        if (source) source.textContent = numberFormat.format(Number(coverage.source_row_count || 0)) + ' baris sumber';
        if (coverageNode) coverageNode.textContent = Number(coverage.mapped_unit_count || 0) + '/' + Number(coverage.total_unit_count || 0) + ' unit terpetakan | ' + Number(coverage.mapped_district_count || 0) + ' kecamatan';
        if (updated) updated.textContent = payload.updated_at || '-';
        if (legend) legend.textContent = metricDefinition(currentMetric()).label;
        metricShortcuts.forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.crasMetricShortcut === currentMetric());
        });
    }

    function renderInsights() {
        if (!geoData) return;
        insightButtons.forEach(function (button) {
            const key = String(button.dataset.crasInsight || '');
            const district = sortedDistricts(key)[0] || null;
            const name = button.querySelector('[data-cras-insight-name]');
            const value = button.querySelector('[data-cras-insight-value]');
            button.__crasDistrict = district;
            button.disabled = !district;
            if (name) name.textContent = district?.name || 'Tidak ada data';
            if (value) value.textContent = district
                ? formatMetric(district.metrics?.[key], key, true)
                : '-';
        });
    }

    function filterLabel(select) {
        return String(select.closest('.cras-filter-field')?.querySelector('span')?.textContent || select.dataset.crasFilter || '').trim();
    }

    function activePortfolioFilters() {
        return filterElements.filter(function (select) {
            return !['periode', 'wilayah'].includes(select.dataset.crasFilter)
                && String(select.value || 'all') !== 'all';
        });
    }

    function renderActiveFilters() {
        const portfolioFilters = activePortfolioFilters();
        if (filterCount) filterCount.textContent = String(portfolioFilters.length);
        if (filterStatus) {
            filterStatus.textContent = portfolioFilters.length + ' filter portofolio aktif';
        }
        if (!activeFilters) return;
        activeFilters.innerHTML = '';
        const selectedFilters = filterElements.filter(function (select) {
            return select.dataset.crasFilter !== 'periode'
                && String(select.value || 'all') !== 'all';
        });
        activeFilters.classList.toggle('has-items', selectedFilters.length > 0);
        if (!selectedFilters.length) return;

        const label = document.createElement('span');
        label.className = 'cras-active-label';
        label.textContent = 'Filter aktif:';
        activeFilters.appendChild(label);

        selectedFilters.forEach(function (select) {
            const chip = document.createElement('span');
            const text = document.createElement('span');
            const remove = document.createElement('button');
            chip.className = 'cras-filter-chip';
            text.textContent = filterLabel(select) + ': ' + String(select.selectedOptions?.[0]?.textContent || select.value);
            remove.type = 'button';
            remove.title = 'Hapus filter ' + filterLabel(select);
            remove.setAttribute('aria-label', remove.title);
            remove.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
            if (select.disabled) {
                remove.disabled = true;
            } else {
                remove.addEventListener('click', function () {
                    select.value = 'all';
                    loadData();
                });
            }
            chip.append(text, remove);
            activeFilters.appendChild(chip);
        });
    }

    function renderOverview() {
        const coverage = payload.coverage || {};
        renderDetail(
            'Seluruh Wilayah Terpilih',
            Number(coverage.total_unit_count || 0) + ' unit kerja pada ' + Number(coverage.mapped_district_count || 0) + ' kecamatan',
            payload.metrics || {}
        );
    }

    function renderAll(fit) {
        districtMetricCache = new Map();
        renderLpg();
        renderKpis();
        renderSecondaryMetrics();
        renderOverview();
        renderRanking();
        renderTable();
        renderMeta();
        renderActiveFilters();
        renderMap(fit);
        renderInsights();
    }

    function setSelectOptions(key, options, selected) {
        const select = filterElements.find(function (element) { return element.dataset.crasFilter === key; });
        if (!select) return;
        select.innerHTML = '';
        (options || []).forEach(function (option) {
            const node = document.createElement('option');
            node.value = String(option.value ?? '');
            node.textContent = String(option.label ?? '-');
            node.selected = node.value === String(selected ?? '');
            select.appendChild(node);
        });
    }

    function syncFilters() {
        const options = payload.filters?.options || {};
        const selected = payload.filters?.selected || {};
        Object.keys(options).forEach(function (key) { setSelectOptions(key, options[key], selected[key]); });
        syncLpgFilters();
    }

    function syncLpgFilters() {
        const lpg = payload.lpg || {};
        const options = lpg.filters?.options || {};
        const selected = lpg.filters?.selected || {};
        lpgFilterElements.forEach(function (select) {
            const key = String(select.dataset.crasLpgFilter || '');
            select.innerHTML = '';
            (options[key] || []).forEach(function (option) {
                const node = document.createElement('option');
                node.value = String(option.value ?? '');
                node.textContent = String(option.label ?? '-');
                node.selected = node.value === String(selected[key] ?? 'all');
                select.appendChild(node);
            });
        });
        lpgSort = String(selected.sort || 'os_desc');
        lpgSortButtons.forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.crasLpgSort === lpgSort);
        });
    }

    function requestParams() {
        const params = new URLSearchParams();
        filterElements.forEach(function (select) {
            params.set(select.dataset.crasFilter, select.value);
        });
        lpgFilterElements.forEach(function (select) {
            params.set('lpg_' + select.dataset.crasLpgFilter, select.value);
        });
        params.set('lpg_sort', lpgSort);
        params.set('metric', currentMetric());
        params.set('view', activeView);
        return params;
    }

    async function loadData() {
        if (!dataUrl) return;
        loading.textContent = 'Mengagregasi portofolio CRAS...';
        loading.classList.remove('is-hidden');
        applyButton.disabled = true;
        if (contextApplyButton) contextApplyButton.disabled = true;
        if (lpgApplyButton) lpgApplyButton.disabled = true;
        const originalButtonHtml = applyButton.innerHTML;
        applyButton.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>Memuat</span>';
        const params = requestParams();

        try {
            const response = await fetch(dataUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            const result = await response.json().catch(function () { return {}; });
            if (!response.ok || !result.ready) throw new Error(result.message || 'Data Marketshare CRAS LPG gagal dimuat.');
            payload = result;
            syncFilters();
            if (heatMetric) heatMetric.value = result.heatmap?.selected || currentMetric();
            window.history.replaceState({}, '', window.location.pathname + '?' + requestParams().toString());
            renderAll(true);
        } catch (error) {
            loading.textContent = error.message || 'Data Marketshare CRAS LPG gagal dimuat.';
            loading.classList.remove('is-hidden');
        } finally {
            applyButton.disabled = false;
            if (contextApplyButton) contextApplyButton.disabled = false;
            if (lpgApplyButton) lpgApplyButton.disabled = false;
            applyButton.innerHTML = originalButtonHtml;
        }
    }

    applyButton?.addEventListener('click', loadData);
    contextApplyButton?.addEventListener('click', loadData);
    lpgApplyButton?.addEventListener('click', loadData);
    resetButton?.addEventListener('click', function () {
        filterElements.forEach(function (select) {
            select.value = select.dataset.crasFilter === 'periode'
                ? String(payload.filters?.options?.periode?.[0]?.value || '')
                : String(payload.filters?.options?.[select.dataset.crasFilter]?.[0]?.value || 'all');
        });
        if (heatMetric) heatMetric.value = 'baki_debet';
        lpgFilterElements.forEach(function (select) {
            select.value = String(payload.lpg?.filters?.options?.[select.dataset.crasLpgFilter]?.[0]?.value || 'all');
        });
        lpgSort = 'os_desc';
        loadData();
    });
    heatMetric?.addEventListener('change', function () {
        renderAll(false);
        window.history.replaceState({}, '', window.location.pathname + '?' + requestParams().toString());
    });

    filterElements.forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.dataset.crasFilter === 'sektor') {
                const subSector = filterElements.find(function (item) { return item.dataset.crasFilter === 'sub_sektor'; });
                if (subSector) subSector.value = 'all';
            }
            renderActiveFilters();
        });
    });

    metricShortcuts.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!heatMetric) return;
            heatMetric.value = String(button.dataset.crasMetricShortcut || 'baki_debet');
            renderAll(false);
            window.history.replaceState({}, '', window.location.pathname + '?' + requestParams().toString());
        });
    });

    rankingModeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            rankingMode = String(button.dataset.crasRankingMode || 'district');
            renderRanking();
        });
    });

    lpgSortButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            lpgSort = String(button.dataset.crasLpgSort || 'os_desc');
            lpgSortButtons.forEach(function (item) {
                item.classList.toggle('is-active', item === button);
            });
            loadData();
        });
    });

    viewTriggers.forEach(function (button) {
        button.addEventListener('click', function () {
            setActiveView(String(button.dataset.crasViewTrigger || 'mapping'), true);
        });
    });

    insightButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const key = String(button.dataset.crasInsight || 'baki_debet');
            const district = button.__crasDistrict;
            if (!district || !heatMetric) return;
            heatMetric.value = key;
            renderAll(false);
            focusDistrict(district);
            window.history.replaceState({}, '', window.location.pathname + '?' + requestParams().toString());
        });
    });

    mapResetButton?.addEventListener('click', function () {
        if (!geoLayer || !geoLayer.getLayers().length) return;
        const bounds = geoLayer.getBounds();
        if (bounds.isValid()) map.fitBounds(bounds, { padding: [18, 18], maxZoom: 10.5 });
        renderOverview();
    });

    setActiveView(activeView, false);

    fetch(payload.source?.geojson_url || '')
        .then(function (response) {
            if (!response.ok) throw new Error('Polygon wilayah gagal dimuat.');
            return response.json();
        })
        .then(function (data) {
            geoData = data;
            renderAll(true);
        })
        .catch(function (error) {
            loading.textContent = error.message;
            loading.classList.remove('is-hidden');
        });
});
</script>
@endpush
