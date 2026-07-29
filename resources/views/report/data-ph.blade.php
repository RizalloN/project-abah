@extends('layouts.admin')

@section('title', 'Data PH')

@section('content')
<style>
    :root {
        --primary-blue: #0857C3;
        --primary-blue-light: #1A73E8;
        --primary-blue-dark: #002F6C;
        --surface-color: #ffffff;
        --bg-color: #f8fafc;
        --border-color: #cbd5e1;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --table-header-bg: #002F6C;
        --table-header-text: #ffffff;
        --accent-color: #f59e0b;
        --loan-blue-soft: #eff6ff;
    }

    .kejar-laba-wrapper {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
        padding-top: 0.75rem;
        padding-bottom: 2rem;
    }

    .kejar-laba-card {
        background: var(--surface-color);
        border: 1px solid var(--border-color) !important;
        border-radius: 0px !important;
        overflow: visible;
        box-shadow: none !important;
        margin-bottom: 1.5rem;
    }

    .kejar-laba-card-header {
        padding: 1.5rem 1.75rem;
        background: #ffffff;
        border-bottom: 1px solid var(--border-color);
    }

    .kejar-laba-hero {
        position: relative;
        padding: 1.25rem 1.75rem;
        border-radius: 0px !important;
        background: var(--surface-color);
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main);
    }

    .kejar-laba-title-wrap {
        width: 100%;
        text-align: left;
        padding: 0;
    }

    .kejar-laba-title-badge {
        display: inline-block;
        margin-bottom: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 0px !important;
        background: var(--loan-blue-soft);
        color: var(--primary-blue);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border: 1px solid rgba(8, 87, 195, 0.2);
    }

    .kejar-laba-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.01em;
        line-height: 1.2;
    }

    .kejar-laba-title::after {
        content: '';
        display: block;
        width: 100px;
        height: 3px;
        margin: 0.5rem 0 0 0;
        background: var(--primary-blue);
    }

    .kejar-laba-subtitle {
        margin: 0.5rem 0 0 0;
        color: var(--text-muted);
        font-size: 0.78rem;
        line-height: 1.6;
        max-width: 660px;
    }

    .kejar-laba-date-badge {
        position: absolute;
        right: 1.75rem;
        top: 1.75rem;
        border-radius: 0px !important;
        font-weight: 700;
        font-size: 0.8rem;
        background: var(--loan-blue-soft) !important;
        color: var(--primary-blue) !important;
        border: 1px solid rgba(8, 87, 195, 0.2) !important;
        padding: 0.5rem 1rem !important;
    }

    @media (max-width: 767.98px) {
        .kejar-laba-date-badge {
            position: static;
            margin-top: 1rem;
            display: inline-block;
        }
    }

    .filter-section {
        padding: 1.25rem 1.5rem;
        background: #ffffff;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        z-index: 50;
    }

    /* ── Compact Flat Selectors ── */
    .loan-filter-modern {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(140px, auto);
        gap: 0.75rem;
        background: #ffffff;
        padding: 1rem;
        border-radius: 0px !important;
        border: 1px solid var(--border-color) !important;
        box-shadow: none !important;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 1000;
        align-items: flex-end;
        width: 100%;
    }

    .kejar-laba-card, .filter-section {
        overflow: visible !important;
    }

    .loan-filter-item {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 0.4rem;
        position: relative;
    }

    /* Descending z-index for items */
    .loan-filter-item:nth-child(1) { z-index: 40; }
    .loan-filter-item:nth-child(2) { z-index: 30; }
    .loan-filter-item:nth-child(3) { z-index: 20; }

    .loan-filter-modern .loan-filter-label {
        font-size: 0.72rem;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-left: 0.25rem;
    }

    .loan-dropdown {
        position: relative;
        width: 100%;
        min-width: 0;
    }

    .loan-dropdown-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: var(--primary-blue);
        font-size: 0.85rem;
        pointer-events: none;
        opacity: 0.8;
    }

    .loan-dropdown-toggle {
        width: 100%;
        min-width: 0;
        height: 40px;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 0px !important;
        padding: 0 0.75rem 0 2.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        font-size: 0.82rem;
        color: #1e293b;
        transition: all 0.2s ease;
        text-align: left;
    }

    .loan-dropdown-toggle:hover {
        border-color: var(--primary-blue-light);
        background: #f8fafc;
    }

    .loan-dropdown.is-open { z-index: 3100 !important; }
    .loan-dropdown.is-open .loan-dropdown-toggle {
        border-color: var(--primary-blue);
        box-shadow: none !important;
    }

    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        width: 100%;
        min-width: 260px;
        max-width: min(360px, calc(100vw - 24px));
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 0px !important;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        z-index: 3000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(4px);
        transition: all 0.2s ease;
        max-height: 350px;
        overflow-y: auto;
        padding: 0.4rem;
    }

    .loan-dropdown.is-open .loan-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .loan-dropdown-option {
        width: 100%;
        padding: 0.55rem 0.75rem;
        border: none;
        background: transparent;
        border-radius: 0px !important;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 700;
        font-size: 0.82rem;
        color: #475569;
        transition: all 0.15s;
        text-align: left;
        margin-bottom: 2px;
    }

    .loan-dropdown-option:hover { background: #f1f5f9; color: var(--primary-blue); }
    .loan-dropdown-option.is-active { background: rgba(8, 87, 195, 0.08); color: var(--primary-blue); }

    .loan-dropdown-check {
        width: 1rem;
        height: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #cbd5e1;
        border-radius: 0px !important;
        transition: all 0.15s;
        font-size: 0.65rem;
        color: white;
        flex-shrink: 0;
    }

    .loan-dropdown-option.is-active .loan-dropdown-check {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .btn-loan-modern-submit {
        height: 40px;
        min-width: 140px;
        padding: 0 1.25rem;
        border-radius: 0px !important;
        background: var(--primary-blue) !important;
        color: white;
        border: none;
        font-weight: 800;
        font-size: 0.82rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        box-shadow: none !important;
    }

    @media (max-width: 991.98px) {
        .loan-filter-modern {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .loan-filter-modern > div,
        .btn-loan-modern-submit {
            min-width: 0;
        }
    }

    @media (max-width: 767.98px) {
        .filter-section.p-4 {
            padding: 0.75rem !important;
        }

        .loan-filter-modern {
            grid-template-columns: minmax(0, 1fr);
            gap: 0.75rem;
            padding: 0.75rem;
        }

        .loan-dropdown-menu {
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        .loan-dropdown-text,
        .loan-dropdown-option span {
            min-width: 0;
            overflow-wrap: anywhere;
        }
    }

    .btn-loan-modern-submit:hover {
        background: var(--primary-blue-dark) !important;
    }

    .loan-dropdown-search {
        padding: 0.4rem;
        position: sticky;
        top: -0.4rem;
        background: white;
        z-index: 10;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 0.4rem;
        border-radius: 0px !important;
    }

    .loan-dropdown-search input {
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1px solid #cbd5e1;
        border-radius: 0px !important;
        font-size: 0.78rem;
        background: #f8fafc;
    }

    .filter-container {
        display: flex;
        align-items: flex-end;
        gap: 1.5rem;
        flex-wrap: wrap;
        width: 100%;
        justify-content: center;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        min-width: 200px;
        position: relative;
    }

    .filter-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .select-custom {
        border-radius: 0px !important;
        border: 1px solid var(--border-color);
        padding: 0.6rem 1rem;
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--text-main);
        background: #f9fafb;
        cursor: pointer;
        transition: all 0.2s ease;
        appearance: none;
        width: 100%;
    }

    .select-custom:focus {
        border-color: var(--primary-blue);
        box-shadow: none !important;
        outline: none;
    }

    /* Multi-select Dropdown Style */
    .daily-dropdown {
        position: relative;
        width: 100%;
    }

    .daily-dropdown-toggle {
        width: 100%;
        min-height: 40px;
        border: 1px solid var(--border-color);
        border-radius: 0px !important;
        background: #f9fafb;
        color: var(--text-main);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0.75rem;
        font-weight: 700;
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .daily-dropdown-toggle.is-disabled {
        opacity: 0.65;
        cursor: not-allowed;
        pointer-events: none;
    }

    .daily-dropdown-toggle:hover {
        border-color: var(--primary-blue-light);
        background: #ffffff;
    }

    .daily-dropdown-toggle-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 1.5rem;
    }

    .daily-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 0px !important;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        z-index: 100;
        padding: 0.4rem;
        display: none;
        max-height: 300px;
        overflow-y: auto;
    }

    .daily-dropdown.is-disabled .daily-dropdown-toggle {
        background: #eef2f7;
        color: #94a3b8;
        border-color: #dbe4ef;
    }

    .daily-dropdown.is-disabled .daily-dropdown-toggle:hover {
        border-color: #dbe4ef;
        background: #eef2f7;
    }

    .daily-dropdown.is-open .daily-dropdown-menu {
        display: block;
    }

    .daily-dropdown-option {
        display: flex;
        align-items: center;
        padding: 0.55rem 0.75rem;
        border-radius: 0px !important;
        cursor: pointer;
        transition: background 0.15s;
        gap: 0.75rem;
    }

    .daily-dropdown-option.is-disabled-option {
        opacity: 0.45;
        pointer-events: none;
    }

    .daily-dropdown-option:hover {
        background: #f1f5f9;
    }

    .daily-dropdown-option.is-active {
        background: #eff6ff;
        color: var(--primary-blue);
    }

    .daily-dropdown-check {
        width: 16px;
        height: 16px;
        border: 2px solid #cbd5e1;
        border-radius: 0px !important;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .is-active .daily-dropdown-check {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .daily-dropdown-check i {
        color: white;
        font-size: 8px;
        display: none;
    }

    .is-active .daily-dropdown-check i {
        display: block;
    }

    .btn-apply {
        background: var(--primary-blue);
        color: white;
        border: none;
        border-radius: 0px !important;
        padding: 0.5rem 1.5rem;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.2s;
        box-shadow: none !important;
        cursor: pointer;
    }

    .btn-apply:hover {
        background: var(--primary-blue-dark);
    }

    /* Searchable Dropdown Extensions */
    .daily-search-shell {
        padding: 0.4rem 0.6rem;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .daily-search-inner {
        position: relative;
    }

    .daily-search-inner i {
        position: absolute;
        left: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    .daily-search-input {
        width: 100%;
        padding: 0.4rem 0.5rem 0.4rem 1.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 0px !important;
        font-size: 0.78rem;
        font-weight: 500;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .daily-search-input:focus {
        background: #ffffff;
        border-color: var(--primary-blue-light);
        outline: none;
        box-shadow: none !important;
    }

    .daily-dropdown-options-list {
        max-height: 240px;
        overflow-y: auto;
        padding: 0.25rem 0;
    }

    .daily-dropdown-options-list::-webkit-scrollbar {
        width: 5px;
    }

    .daily-dropdown-options-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 0px !important;
    }

    /* Table Wrapper with Sticky Viewport */
    .kejar-laba-table-shell {
        position: relative;
        width: 100%;
        background: #ffffff;
        border: 1px solid var(--border-color) !important;
        border-radius: 0px !important;
        z-index: 10;
        box-shadow: none !important;
    }

    /* Integration with report.partials.sticky-table-viewport-style */
    @include('report.partials.sticky-table-viewport-style', [
        'wrapperSelector' => '.kejar-laba-table-shell',
        'tableSelector' => '.kejar-laba-table'
    ])

    .kejar-laba-table-shell {
        height: auto !important;
        max-height: min(72vh, 760px) !important;
        max-height: min(72dvh, 760px) !important;
        overflow: auto !important;
        overscroll-behavior: contain !important;
    }

    .kejar-laba-table {
        --ph-index-column-width: 64px;
        width: 100%;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        table-layout: auto;
        background: #ffffff;
        border-top: 1px solid var(--border-color) !important;
        border-left: 1px solid var(--border-color) !important;
    }

    .kejar-laba-table thead th {
        background-color: var(--table-header-bg) !important;
        color: var(--table-header-text);
        text-transform: uppercase;
        font-size: 0.72rem;
        padding: 0.65rem 0.85rem;
        font-weight: 800;
        z-index: 30;
        border-right: 1px solid rgba(255, 255, 255, 0.25) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.25) !important;
        white-space: nowrap;
    }

    .kejar-laba-table thead tr:first-child th {
        background-color: var(--primary-blue-dark) !important;
    }

    .kejar-laba-table thead tr:first-child th[rowspan="2"] {
        background-color: var(--primary-blue-dark) !important;
    }

    .kejar-laba-table thead tr:nth-child(2) th {
        background: var(--primary-blue) !important;
        padding: 0.45rem 0.75rem;
    }

    .kejar-laba-table tbody td {
        font-size: 0.82rem;
        background: #ffffff;
        font-variant-numeric: tabular-nums;
        padding: 0.55rem 0.85rem;
        border-right: 1px solid var(--border-color) !important;
        border-bottom: 1px solid var(--border-color) !important;
    }

    /* Thicker dividing border below each branch group */
    .kejar-laba-table tbody td[rowspan],
    .kejar-laba-table tbody tr.total-row td {
        border-bottom: 2px solid #94a3b8 !important;
    }

    /* Force background for total rows and bold style */
    .kejar-laba-table tbody tr.total-row td {
        background-color: #eff6ff !important;
        color: var(--primary-blue-dark) !important;
        font-weight: 700 !important;
    }

    .kejar-laba-table tbody tr:hover td {
        background: #f1f5f9 !important;
    }

    .kejar-laba-table tbody tr.total-row:hover td {
        background: #dbeafe !important;
    }

    /* Fixed Headers and Columns Color Fix */
    .kejar-laba-table th.sticky-col {
        background-color: var(--primary-blue-dark) !important;
        z-index: 40 !important;
    }
    
    .kejar-laba-table td.sticky-col {
        background-color: #ffffff !important;
        z-index: 20;
    }
    
    .sticky-col {
        position: sticky;
        left: 0;
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
    }

    .kejar-laba-table .ph-sticky-index {
        left: 0 !important;
        width: var(--ph-index-column-width);
        min-width: var(--ph-index-column-width);
        max-width: var(--ph-index-column-width);
    }

    .kejar-laba-table .ph-sticky-scope {
        left: var(--ph-index-column-width) !important;
    }

    /* Thicker border separating frozen pane */
    .kejar-laba-table th.sticky-col:nth-child(2),
    .kejar-laba-table td.sticky-col:nth-child(2) {
        border-right: 2.5px solid #94a3b8 !important;
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-weight-bold { font-weight: 700; }
    
    .negative-value { color: #b91c1c !important; font-weight: 700; }
    .positive-value { color: #15803d !important; font-weight: 700; }
    .zero-value { color: var(--text-muted); opacity: 0.6; }

    .currency-symbol { font-size: 0.65rem; margin-right: 2px; color: var(--text-muted); font-weight: normal; }

    .ph-nominatif-trigger {
        cursor: zoom-in;
    }

    .ph-nominatif-modal .modal-dialog {
        max-width: min(96vw, 1480px);
    }

    .ph-nominatif-modal .modal-content {
        border-radius: 8px;
        border: 1px solid #dbe3ef;
    }

    .ph-nominatif-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .ph-nominatif-summary span {
        border: 1px solid #dbe3ef;
        border-radius: 999px;
        padding: 0.25rem 0.65rem;
        background: #f8fafc;
    }

    .ph-nominatif-table-wrap {
        max-height: 68vh;
        overflow: auto;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }

    .ph-nominatif-table {
        width: max-content;
        min-width: 100%;
        margin-bottom: 0;
        font-size: 0.76rem;
    }

    .ph-nominatif-table th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #0f3f86;
        color: #ffffff;
        white-space: nowrap;
    }

    .ph-nominatif-table td {
        white-space: nowrap;
        vertical-align: middle;
    }
</style>

<div class="kejar-laba-wrapper pt-4">
    <div class="kejar-laba-card">
        <div class="kejar-laba-card-header kejar-laba-hero d-flex flex-wrap align-items-center justify-content-center gap-3">
            <div class="kejar-laba-title-wrap">
                <div class="kejar-laba-title-badge">
                    <i class="fas fa-university"></i>
                    <span>BRI Data PH Performance</span>
                </div>
                <h1 class="kejar-laba-title">DATA PH</h1>
                <div class="kejar-laba-subtitle">Monitoring pencapaian Data PH secara ringkas dan komprehensif.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge px-3 py-2 kejar-laba-date-badge">
                    <i class="fas fa-calendar-check mr-1"></i> Data per: {{ $selectedPeriodLabel }}
                </span>
            </div>
        </div>

        @php
            $isArea6AllSelected = (bool) ($isArea6All ?? false);
        @endphp

        <div class="filter-section p-4">
            <form action="{{ route('report.dashboard-pinjaman.data-ph') }}" method="GET" id="filterForm">
                <div class="loan-filter-modern">
                    {{-- Periode --}}
                    <div class="loan-filter-item">
                        <label class="loan-filter-label">Periode</label>
                        <div class="loan-dropdown" data-loan-dropdown="periode">
                            <i class="fas fa-calendar-alt loan-dropdown-icon"></i>
                            <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="periode">
                                <span class="loan-dropdown-text">Pilih Periode</span>
                                <i class="fas fa-chevron-down small opacity-50"></i>
                            </button>
                            <div class="loan-dropdown-menu" data-loan-dropdown-menu="periode">
                                @foreach($availablePeriods as $period)
                                    <div class="loan-dropdown-option {{ $selectedPeriod === $period ? 'is-active' : '' }}" data-value="{{ $period }}">
                                        <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                        <span>{{ \Carbon\Carbon::parse($period)->translatedFormat('d M y') }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <select name="periode" id="periodeInput" class="d-none">
                                @foreach($availablePeriods as $period)
                                    <option value="{{ $period }}" {{ $selectedPeriod === $period ? 'selected' : '' }}>{{ $period }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Kantor Cabang --}}
                    <div class="loan-filter-item">
                        <label class="loan-filter-label">Kantor Cabang</label>
                        <div class="loan-dropdown" data-loan-dropdown="kanca">
                            <i class="fas fa-university loan-dropdown-icon"></i>
                            <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="kanca">
                                <span class="loan-dropdown-text" id="kancaLabel">Pilih Kantor Cabang...</span>
                                <i class="fas fa-chevron-down small opacity-50"></i>
                            </button>
                            <div class="loan-dropdown-menu" data-loan-dropdown-menu="kanca">
                                <div class="loan-dropdown-option {{ $isArea6AllSelected ? 'is-active' : '' }}" data-value="all">
                                    <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                    <span>AREA-6 All</span>
                                </div>
                                @foreach($filters['kanca'] as $kc)
                                    @if($kc['value'] !== 'all')
                                        @php $active = is_array($selected['kanca']) && in_array($kc['value'], $selected['kanca']); @endphp
                                        <div class="loan-dropdown-option {{ $active ? 'is-active' : '' }}" data-value="{{ $kc['value'] }}">
                                            <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                            <span>{{ $kc['label'] }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <input type="hidden" name="kanca" id="kancaInput" value="{{ is_array($selected['kanca']) ? implode(',', $selected['kanca']) : $selected['kanca'] }}">
                        </div>
                    </div>

                    {{-- Unit Kerja --}}
                    <div class="loan-filter-item">
                        <label class="loan-filter-label">Unit Kerja</label>
                        <div class="loan-dropdown {{ $isArea6AllSelected ? 'is-disabled' : '' }}" data-loan-dropdown="unit" id="unitDropdown">
                            <i class="fas fa-store loan-dropdown-icon"></i>
                            <button type="button" class="loan-dropdown-toggle {{ $isArea6AllSelected ? 'is-disabled' : '' }}" data-loan-dropdown-toggle="unit">
                                <span class="loan-dropdown-text" id="unitLabel">Semua Unit Kerja</span>
                                <i class="fas fa-chevron-down small opacity-50"></i>
                            </button>
                            <div class="loan-dropdown-menu" data-loan-dropdown-menu="unit" style="padding: 0;">
                                <div class="loan-dropdown-search">
                                    <input type="text" placeholder="Cari unit..." id="unitSearchInput">
                                </div>
                                <div id="unitOptionsContainer">
                                    {{-- Options by JS --}}
                                </div>
                            </div>
                            <input type="hidden" name="unit_kerja" id="unitInput" value="{{ $selected['unit_kerja'] }}">
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn-loan-modern-submit w-100">
                            <i class="fas fa-search"></i> Telusuri
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script>
                // --- Premium Sync Logic ---
                function initPremiumSync() {
                    // Close all on click outside
                    document.addEventListener('click', () => {
                        document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                    });

                    // General Toggles
                    document.querySelectorAll('.loan-dropdown-toggle').forEach(toggle => {
                        toggle.addEventListener('click', (e) => {
                            const parent = toggle.closest('.loan-dropdown');
                            if (parent.classList.contains('is-disabled')) return;
                            e.stopPropagation();
                            const wasOpen = parent.classList.contains('is-open');
                            document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                            if (!wasOpen) parent.classList.add('is-open');

                            if (!wasOpen && parent.dataset.loanDropdown === 'unit') {
                                setTimeout(() => document.getElementById('unitSearchInput').focus(), 100);
                            }
                        });
                    });

                    // Standard Select Sync
                    ['periode'].forEach(type => {
                        const parent = document.querySelector(`[data-loan-dropdown="${type}"]`);
                        if (!parent) return;
                        const select = parent.querySelector('select');
                        const toggleText = parent.querySelector('.loan-dropdown-text');
                        
                        parent.querySelectorAll('.loan-dropdown-option').forEach(opt => {
                            opt.addEventListener('click', (e) => {
                                e.stopPropagation();
                                const val = opt.dataset.value;
                                select.value = val;
                                toggleText.textContent = opt.querySelector('span').textContent;
                                parent.querySelectorAll('.loan-dropdown-option').forEach(o => o.classList.remove('is-active'));
                                opt.classList.add('is-active');
                                parent.classList.remove('is-open');
                            });
                        });

                        // Initial label
                        if (select && select.selectedIndex >= 0) {
                            toggleText.textContent = select.options[select.selectedIndex].text;
                        }
                    });

                    // Unit Kerja Search Sync
                    const unitSearchInput = document.getElementById('unitSearchInput');
                    if (unitSearchInput) {
                        unitSearchInput.addEventListener('click', e => e.stopPropagation());
                        unitSearchInput.addEventListener('input', function() {
                            const term = this.value.toLowerCase();
                            document.querySelectorAll('#unitOptionsContainer .loan-dropdown-option').forEach(opt => {
                                if (opt.dataset.value === 'all') return;
                                const text = opt.querySelector('span').textContent.toLowerCase();
                                opt.style.display = text.includes(term) ? 'flex' : 'none';
                            });
                        });
                    }
                }

                initPremiumSync();

                // Initial UI state
                updateKancaUI();

                // Kanca option events
                document.querySelectorAll('[data-loan-dropdown="kanca"] .loan-dropdown-option').forEach(opt => {
                    opt.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const val = this.dataset.value;
                        const kancaInput = document.getElementById('kancaInput');
                        const area6Branches = @json($area6Branches ?? []);
                        let selected = kancaInput.value ? kancaInput.value.split(',').filter(Boolean) : [];
                        
                        function isArea6AllSelection(s) {
                            return s.length === area6Branches.length && area6Branches.every(v => s.includes(v));
                        }

                        if (val === 'all') {
                            selected = isArea6AllSelection(selected) ? [] : [...area6Branches];
                        } else {
                            if (selected.includes(val)) {
                                selected = selected.filter(v => v !== val);
                            } else {
                                selected.push(val);
                            }
                        }

                        kancaInput.value = selected.join(',');
                        updateKancaUI();
                    });
                });

                function updateKancaUI() {
                    const kancaInput = document.getElementById('kancaInput');
                    const area6Branches = @json($area6Branches ?? []);
                    const kancaLabel = document.getElementById('kancaLabel');
                    const selected = kancaInput.value ? kancaInput.value.split(',').filter(Boolean) : [];
                    
                    function isArea6AllSelection(s) {
                        return s.length === area6Branches.length && area6Branches.every(v => s.includes(v));
                    }

                    const allSelected = isArea6AllSelection(selected);
                    const options = document.querySelectorAll('[data-loan-dropdown="kanca"] .loan-dropdown-option');

                    if (allSelected) {
                        kancaLabel.textContent = 'AREA-6 All';
                        options.forEach(o => o.classList.add('is-active'));
                    } else if (selected.length === 1) {
                        kancaLabel.textContent = selected[0];
                        options.forEach(o => o.classList.toggle('is-active', o.dataset.value === selected[0]));
                        options[0].classList.remove('is-active');
                    } else if (selected.length > 1) {
                        kancaLabel.textContent = `${selected.length} Cabang`;
                        options.forEach(o => {
                            if (o.dataset.value === 'all') { o.classList.remove('is-active'); return; }
                            o.classList.toggle('is-active', selected.includes(o.dataset.value));
                        });
                    } else {
                        kancaLabel.textContent = 'Pilih Kantor Cabang...';
                        options.forEach(o => o.classList.remove('is-active'));
                    }

                    const unitDropdown = document.getElementById('unitDropdown');
                    const unitInput = document.getElementById('unitInput');
                    const unitLabel = document.getElementById('unitLabel');
                    const unitOptionsContainer = document.getElementById('unitOptionsContainer');
                    const allUnitsData = @json($filters['unit_kerja']);
                    
                    const disableUnit = allSelected;
                    unitDropdown.classList.toggle('is-disabled', disableUnit);
                    unitDropdown.querySelector('.loan-dropdown-toggle').classList.toggle('is-disabled', disableUnit);

                    unitOptionsContainer.innerHTML = `
                        <div class="loan-dropdown-option ${unitInput.value === 'all' ? 'is-active' : ''}" data-value="all">
                            <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                            <span>Semua Unit Kerja</span>
                        </div>
                    `;

                    let currentUnitStillValid = (unitInput.value === 'all');

                    if (!disableUnit) {
                        allUnitsData.forEach(unit => {
                            if (unit.value === 'all') return;
                            if (selected.includes(unit.kanca_value)) {
                                if (unit.value === unitInput.value) {
                                    currentUnitStillValid = true;
                                }
                                const opt = document.createElement('div');
                                opt.className = `loan-dropdown-option ${unit.value === unitInput.value ? 'is-active' : ''}`;
                                opt.dataset.value = unit.value;
                                opt.innerHTML = '<div class="loan-dropdown-check"><i class="fas fa-check"></i></div><span></span>';
                                opt.querySelector('span').textContent = unit.label;
                                opt.addEventListener('click', (e) => {
                                    e.stopPropagation();
                                    unitInput.value = unit.value;
                                    unitLabel.textContent = unit.label;
                                    document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                                    updateKancaUI();
                                });
                                unitOptionsContainer.appendChild(opt);
                            }
                        });
                    }

                    unitOptionsContainer.querySelector('[data-value="all"]').addEventListener('click', (e) => {
                        e.stopPropagation();
                        unitInput.value = 'all';
                        unitLabel.textContent = 'Semua Unit Kerja';
                        document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                        updateKancaUI();
                    });

                    if (disableUnit || !currentUnitStillValid) {
                        unitInput.value = 'all';
                        unitLabel.textContent = 'Semua Unit Kerja';
                        const allOpt = unitOptionsContainer.querySelector('[data-value="all"]');
                        if (allOpt) allOpt.classList.add('is-active');
                    } else if (unitInput.value !== 'all') {
                        const matchedUnit = allUnitsData.find(u => u.value === unitInput.value);
                        if (matchedUnit) {
                            unitLabel.textContent = matchedUnit.label;
                        }
                    } else {
                        unitLabel.textContent = 'Semua Unit Kerja';
                    }
                }
        </script>

        @php
            $phSegmentRows = [
                ['key' => 'micro', 'label' => 'MICRO'],
                ['key' => 'small', 'label' => 'SMALL'],
                ['key' => 'consumer_briguna', 'label' => 'KONSUMER - BRIGUNA'],
                ['key' => 'consumer_kpr', 'label' => 'KONSUMER - KPR'],
                ['key' => 'total', 'label' => 'TOTAL'],
            ];
        @endphp

        <div class="kejar-laba-table-shell" data-abah-no-table-guard="1">
            <table class="kejar-laba-table" data-abah-no-table-guard="1">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky-col ph-sticky-index" style="z-index: 50 !important;">No</th>
                        <th rowspan="2" class="sticky-col ph-sticky-scope" style="z-index: 50 !important;">{{ $isArea6AllSelected ? 'Kantor Cabang' : 'Unit Kerja' }}</th>
                        <th rowspan="2" style="z-index: 30 !important;">Segmen</th>
                        <th rowspan="2" class="text-center">SISA PH<br>{{ strtoupper($selectedPeriodLabel) }}</th>
                        <th colspan="4" class="text-center">POSISI RECOVERY</th>
                        <th colspan="3" class="text-center">DELTA PERBANDINGAN</th>
                    </tr>
                    <tr>
                        <!-- POSISI RECOVERY -->
                        <th class="text-center">{{ strtoupper($yoyPeriodLabel) }}</th>
                        <th class="text-center">{{ strtoupper($ytdPeriodLabel) }}</th>
                        <th class="text-center">{{ strtoupper($m1PeriodLabel) }}</th>
                        <th class="text-center">{{ strtoupper($selectedPeriodLabel) }}</th>
                        <!-- DELTA PERBANDINGAN -->
                        <th class="text-center">YoY</th>
                        <th class="text-center">YTD</th>
                        <th class="text-center">M-1</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $branchIndex => $row)
                        @php
                            $blockBg = $branchIndex % 2 === 0 ? '#ffffff' : '#f8fafc';
                            $phScopeLabel = $isArea6AllSelected ? ($row['kanca'] ?? '') : ($row['unit'] ?? '');
                            $phUnitFilter = $isArea6AllSelected ? 'all' : ($row['unit'] ?? 'all');
                        @endphp
                        {{-- MICRO --}}
                        <tr class="ph-nominatif-trigger" data-ph-nominatif-row data-period="{{ $selectedPeriod }}" data-segment="micro" data-segment-label="MICRO" data-kanca="{{ $row['kanca'] ?? '' }}" data-unit="{{ $phUnitFilter }}" data-scope-label="{{ $phScopeLabel }}" style="background-color: {{ $blockBg }};">
                            <td rowspan="5" class="text-center sticky-col ph-sticky-index font-weight-bold" style="background-color: {{ $blockBg }} !important; z-index: 20;">
                                {{ $row['no'] }}
                            </td>
                            <td rowspan="5" class="sticky-col ph-sticky-scope font-weight-bold" style="background-color: {{ $blockBg }} !important; color: var(--primary-blue-dark); z-index: 20;">
                                {{ $isArea6AllSelected ? $row['kanca'] : $row['unit'] }}
                            </td>
                            <td class="font-weight-bold text-uppercase" style="background-color: {{ $blockBg }}; color: #475569;">
                                MICRO
                            </td>

                            {{-- SISA PH --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['sisa_ph']['micro'] != 0 ? number_format($row['sisa_ph']['micro'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YoY --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_yoy']['micro'] != 0 ? number_format($row['recovery_yoy']['micro'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YTD --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_ytd']['micro'] != 0 ? number_format($row['recovery_ytd']['micro'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M-1 --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_m1']['micro'] != 0 ? number_format($row['recovery_m1']['micro'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M Terakhir --}}
                            <td class="text-right font-weight-bold" style="background-color: {{ $blockBg }}; color: var(--primary-blue-dark);">
                                {{ $row['recovery_curr']['micro'] != 0 ? number_format($row['recovery_curr']['micro'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- DELTA YoY --}}
                            <td class="text-right {{ $row['delta_yoy']['micro'] < 0 ? 'negative-value' : ($row['delta_yoy']['micro'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_yoy']['micro'] != 0)
                                    {{ $row['delta_yoy']['micro'] > 0 ? '+' : '' }}{{ number_format($row['delta_yoy']['micro'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA YTD --}}
                            <td class="text-right {{ $row['delta_ytd']['micro'] < 0 ? 'negative-value' : ($row['delta_ytd']['micro'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_ytd']['micro'] != 0)
                                    {{ $row['delta_ytd']['micro'] > 0 ? '+' : '' }}{{ number_format($row['delta_ytd']['micro'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA M-1 --}}
                            <td class="text-right {{ $row['delta_m1']['micro'] < 0 ? 'negative-value' : ($row['delta_m1']['micro'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_m1']['micro'] != 0)
                                    {{ $row['delta_m1']['micro'] > 0 ? '+' : '' }}{{ number_format($row['delta_m1']['micro'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>

                        {{-- SMALL --}}
                        <tr class="ph-nominatif-trigger" data-ph-nominatif-row data-period="{{ $selectedPeriod }}" data-segment="small" data-segment-label="SMALL" data-kanca="{{ $row['kanca'] ?? '' }}" data-unit="{{ $phUnitFilter }}" data-scope-label="{{ $phScopeLabel }}" style="background-color: {{ $blockBg }};">
                            <td class="font-weight-bold text-uppercase" style="background-color: {{ $blockBg }}; color: #475569;">
                                SMALL
                            </td>

                            {{-- SISA PH --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['sisa_ph']['small'] != 0 ? number_format($row['sisa_ph']['small'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YoY --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_yoy']['small'] != 0 ? number_format($row['recovery_yoy']['small'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YTD --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_ytd']['small'] != 0 ? number_format($row['recovery_ytd']['small'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M-1 --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_m1']['small'] != 0 ? number_format($row['recovery_m1']['small'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M Terakhir --}}
                            <td class="text-right font-weight-bold" style="background-color: {{ $blockBg }}; color: var(--primary-blue-dark);">
                                {{ $row['recovery_curr']['small'] != 0 ? number_format($row['recovery_curr']['small'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- DELTA YoY --}}
                            <td class="text-right {{ $row['delta_yoy']['small'] < 0 ? 'negative-value' : ($row['delta_yoy']['small'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_yoy']['small'] != 0)
                                    {{ $row['delta_yoy']['small'] > 0 ? '+' : '' }}{{ number_format($row['delta_yoy']['small'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA YTD --}}
                            <td class="text-right {{ $row['delta_ytd']['small'] < 0 ? 'negative-value' : ($row['delta_ytd']['small'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_ytd']['small'] != 0)
                                    {{ $row['delta_ytd']['small'] > 0 ? '+' : '' }}{{ number_format($row['delta_ytd']['small'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA M-1 --}}
                            <td class="text-right {{ $row['delta_m1']['small'] < 0 ? 'negative-value' : ($row['delta_m1']['small'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_m1']['small'] != 0)
                                    {{ $row['delta_m1']['small'] > 0 ? '+' : '' }}{{ number_format($row['delta_m1']['small'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>

                        {{-- KONSUMER - BRIGUNA --}}
                        <tr class="ph-nominatif-trigger" data-ph-nominatif-row data-period="{{ $selectedPeriod }}" data-segment="consumer_briguna" data-segment-label="KONSUMER - BRIGUNA" data-kanca="{{ $row['kanca'] ?? '' }}" data-unit="{{ $phUnitFilter }}" data-scope-label="{{ $phScopeLabel }}" style="background-color: {{ $blockBg }};">
                            <td class="font-weight-bold text-uppercase" style="background-color: {{ $blockBg }}; color: #475569;">
                                KONSUMER - BRIGUNA
                            </td>

                            {{-- SISA PH --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['sisa_ph']['consumer_briguna'] != 0 ? number_format($row['sisa_ph']['consumer_briguna'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YoY --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_yoy']['consumer_briguna'] != 0 ? number_format($row['recovery_yoy']['consumer_briguna'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YTD --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_ytd']['consumer_briguna'] != 0 ? number_format($row['recovery_ytd']['consumer_briguna'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M-1 --}}
                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_m1']['consumer_briguna'] != 0 ? number_format($row['recovery_m1']['consumer_briguna'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M Terakhir --}}
                            <td class="text-right font-weight-bold" style="background-color: {{ $blockBg }}; color: var(--primary-blue-dark);">
                                {{ $row['recovery_curr']['consumer_briguna'] != 0 ? number_format($row['recovery_curr']['consumer_briguna'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- DELTA YoY --}}
                            <td class="text-right {{ $row['delta_yoy']['consumer_briguna'] < 0 ? 'negative-value' : ($row['delta_yoy']['consumer_briguna'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_yoy']['consumer_briguna'] != 0)
                                    {{ $row['delta_yoy']['consumer_briguna'] > 0 ? '+' : '' }}{{ number_format($row['delta_yoy']['consumer_briguna'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA YTD --}}
                            <td class="text-right {{ $row['delta_ytd']['consumer_briguna'] < 0 ? 'negative-value' : ($row['delta_ytd']['consumer_briguna'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_ytd']['consumer_briguna'] != 0)
                                    {{ $row['delta_ytd']['consumer_briguna'] > 0 ? '+' : '' }}{{ number_format($row['delta_ytd']['consumer_briguna'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA M-1 --}}
                            <td class="text-right {{ $row['delta_m1']['consumer_briguna'] < 0 ? 'negative-value' : ($row['delta_m1']['consumer_briguna'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_m1']['consumer_briguna'] != 0)
                                    {{ $row['delta_m1']['consumer_briguna'] > 0 ? '+' : '' }}{{ number_format($row['delta_m1']['consumer_briguna'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>

                        {{-- KONSUMER - KPR --}}
                        <tr class="ph-nominatif-trigger" data-ph-nominatif-row data-period="{{ $selectedPeriod }}" data-segment="consumer_kpr" data-segment-label="KONSUMER - KPR" data-kanca="{{ $row['kanca'] ?? '' }}" data-unit="{{ $phUnitFilter }}" data-scope-label="{{ $phScopeLabel }}" style="background-color: {{ $blockBg }};">
                            <td class="font-weight-bold text-uppercase" style="background-color: {{ $blockBg }}; color: #475569;">
                                KONSUMER - KPR
                            </td>

                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['sisa_ph']['consumer_kpr'] != 0 ? number_format($row['sisa_ph']['consumer_kpr'], 0, ',', '.') : '-' }}
                            </td>

                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_yoy']['consumer_kpr'] != 0 ? number_format($row['recovery_yoy']['consumer_kpr'], 0, ',', '.') : '-' }}
                            </td>

                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_ytd']['consumer_kpr'] != 0 ? number_format($row['recovery_ytd']['consumer_kpr'], 0, ',', '.') : '-' }}
                            </td>

                            <td class="text-right" style="background-color: {{ $blockBg }};">
                                {{ $row['recovery_m1']['consumer_kpr'] != 0 ? number_format($row['recovery_m1']['consumer_kpr'], 0, ',', '.') : '-' }}
                            </td>

                            <td class="text-right font-weight-bold" style="background-color: {{ $blockBg }}; color: var(--primary-blue-dark);">
                                {{ $row['recovery_curr']['consumer_kpr'] != 0 ? number_format($row['recovery_curr']['consumer_kpr'], 0, ',', '.') : '-' }}
                            </td>

                            <td class="text-right {{ $row['delta_yoy']['consumer_kpr'] < 0 ? 'negative-value' : ($row['delta_yoy']['consumer_kpr'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_yoy']['consumer_kpr'] != 0)
                                    {{ $row['delta_yoy']['consumer_kpr'] > 0 ? '+' : '' }}{{ number_format($row['delta_yoy']['consumer_kpr'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>

                            <td class="text-right {{ $row['delta_ytd']['consumer_kpr'] < 0 ? 'negative-value' : ($row['delta_ytd']['consumer_kpr'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_ytd']['consumer_kpr'] != 0)
                                    {{ $row['delta_ytd']['consumer_kpr'] > 0 ? '+' : '' }}{{ number_format($row['delta_ytd']['consumer_kpr'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>

                            <td class="text-right {{ $row['delta_m1']['consumer_kpr'] < 0 ? 'negative-value' : ($row['delta_m1']['consumer_kpr'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $blockBg }};">
                                @if($row['delta_m1']['consumer_kpr'] != 0)
                                    {{ $row['delta_m1']['consumer_kpr'] > 0 ? '+' : '' }}{{ number_format($row['delta_m1']['consumer_kpr'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>

                        {{-- TOTAL --}}
                        <tr class="total-row ph-nominatif-trigger" data-ph-nominatif-row data-period="{{ $selectedPeriod }}" data-segment="total" data-segment-label="TOTAL" data-kanca="{{ $row['kanca'] ?? '' }}" data-unit="{{ $phUnitFilter }}" data-scope-label="{{ $phScopeLabel }}" style="background-color: #eff6ff; font-weight: bold;">
                            <td class="font-weight-bold text-uppercase" style="background-color: #eff6ff; color: #1e3a8a;">
                                TOTAL
                            </td>

                            {{-- SISA PH --}}
                            <td class="text-right" style="background-color: #eff6ff; color: #1e3a8a;">
                                {{ $row['sisa_ph']['total'] != 0 ? number_format($row['sisa_ph']['total'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YoY --}}
                            <td class="text-right" style="background-color: #eff6ff; color: #1e3a8a;">
                                {{ $row['recovery_yoy']['total'] != 0 ? number_format($row['recovery_yoy']['total'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI YTD --}}
                            <td class="text-right" style="background-color: #eff6ff; color: #1e3a8a;">
                                {{ $row['recovery_ytd']['total'] != 0 ? number_format($row['recovery_ytd']['total'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M-1 --}}
                            <td class="text-right" style="background-color: #eff6ff; color: #1e3a8a;">
                                {{ $row['recovery_m1']['total'] != 0 ? number_format($row['recovery_m1']['total'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- POSISI M Terakhir --}}
                            <td class="text-right font-weight-bold" style="background-color: #dbeafe; color: #1e3a8a;">
                                {{ $row['recovery_curr']['total'] != 0 ? number_format($row['recovery_curr']['total'], 0, ',', '.') : '-' }}
                            </td>
                            
                            {{-- DELTA YoY --}}
                            <td class="text-right {{ $row['delta_yoy']['total'] < 0 ? 'negative-value' : ($row['delta_yoy']['total'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: #eff6ff;">
                                @if($row['delta_yoy']['total'] != 0)
                                    {{ $row['delta_yoy']['total'] > 0 ? '+' : '' }}{{ number_format($row['delta_yoy']['total'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA YTD --}}
                            <td class="text-right {{ $row['delta_ytd']['total'] < 0 ? 'negative-value' : ($row['delta_ytd']['total'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: #eff6ff;">
                                @if($row['delta_ytd']['total'] != 0)
                                    {{ $row['delta_ytd']['total'] > 0 ? '+' : '' }}{{ number_format($row['delta_ytd']['total'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            
                            {{-- DELTA M-1 --}}
                            <td class="text-right {{ $row['delta_m1']['total'] < 0 ? 'negative-value' : ($row['delta_m1']['total'] > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: #eff6ff;">
                                @if($row['delta_m1']['total'] != 0)
                                    {{ $row['delta_m1']['total'] > 0 ? '+' : '' }}{{ number_format($row['delta_m1']['total'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="fas fa-info-circle mr-2"></i> Tidak ada data untuk periode yang dipilih.
                            </td>
                        </tr>
                    @endforelse

                    @if(!empty($grandTotals))
                        @foreach($phSegmentRows as $grandIndex => $segment)
                            @php
                                $segmentKey = $segment['key'];
                                $isGrandTotalRow = $segmentKey === 'total';
                                $grandBg = $isGrandTotalRow ? '#dbeafe' : '#eef6ff';
                                $grandStickyBg = $isGrandTotalRow ? '#bfdbfe' : '#eaf2ff';
                                $grandTextColor = '#0f3f86';
                                $sisaValue = (float) data_get($grandTotals, "sisa_ph.$segmentKey", 0);
                                $recoveryYoy = (float) data_get($grandTotals, "recovery_yoy.$segmentKey", 0);
                                $recoveryYtd = (float) data_get($grandTotals, "recovery_ytd.$segmentKey", 0);
                                $recoveryM1 = (float) data_get($grandTotals, "recovery_m1.$segmentKey", 0);
                                $recoveryCurr = (float) data_get($grandTotals, "recovery_curr.$segmentKey", 0);
                                $deltaYoy = (float) data_get($grandTotals, "delta_yoy.$segmentKey", 0);
                                $deltaYtd = (float) data_get($grandTotals, "delta_ytd.$segmentKey", 0);
                                $deltaM1 = (float) data_get($grandTotals, "delta_m1.$segmentKey", 0);
                            @endphp

                            <tr class="{{ $isGrandTotalRow ? 'total-row' : '' }}" style="background-color: {{ $grandBg }}; font-weight: {{ $isGrandTotalRow ? '800' : '700' }};">
                                @if($grandIndex === 0)
                                    <td rowspan="{{ count($phSegmentRows) }}" class="text-center sticky-col ph-sticky-index font-weight-bold" style="background-color: {{ $grandStickyBg }} !important; color: {{ $grandTextColor }}; z-index: 20;">
                                        GT
                                    </td>
                                    <td rowspan="{{ count($phSegmentRows) }}" class="sticky-col ph-sticky-scope font-weight-bold text-uppercase" style="background-color: {{ $grandStickyBg }} !important; color: {{ $grandTextColor }}; z-index: 20;">
                                        {{ $isArea6AllSelected ? 'Grand Total Area 6' : 'Grand Total Tampilan' }}
                                    </td>
                                @endif

                                <td class="font-weight-bold text-uppercase" style="background-color: {{ $grandBg }}; color: {{ $grandTextColor }};">
                                    {{ $segment['label'] }}
                                </td>
                                <td class="text-right" style="background-color: {{ $grandBg }}; color: {{ $grandTextColor }};">
                                    {{ $sisaValue != 0 ? number_format($sisaValue, 0, ',', '.') : '-' }}
                                </td>
                                <td class="text-right" style="background-color: {{ $grandBg }}; color: {{ $grandTextColor }};">
                                    {{ $recoveryYoy != 0 ? number_format($recoveryYoy, 0, ',', '.') : '-' }}
                                </td>
                                <td class="text-right" style="background-color: {{ $grandBg }}; color: {{ $grandTextColor }};">
                                    {{ $recoveryYtd != 0 ? number_format($recoveryYtd, 0, ',', '.') : '-' }}
                                </td>
                                <td class="text-right" style="background-color: {{ $grandBg }}; color: {{ $grandTextColor }};">
                                    {{ $recoveryM1 != 0 ? number_format($recoveryM1, 0, ',', '.') : '-' }}
                                </td>
                                <td class="text-right font-weight-bold" style="background-color: {{ $isGrandTotalRow ? '#bfdbfe' : $grandBg }}; color: {{ $grandTextColor }};">
                                    {{ $recoveryCurr != 0 ? number_format($recoveryCurr, 0, ',', '.') : '-' }}
                                </td>
                                <td class="text-right {{ $deltaYoy < 0 ? 'negative-value' : ($deltaYoy > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $grandBg }};">
                                    @if($deltaYoy != 0)
                                        {{ $deltaYoy > 0 ? '+' : '' }}{{ number_format($deltaYoy, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-right {{ $deltaYtd < 0 ? 'negative-value' : ($deltaYtd > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $grandBg }};">
                                    @if($deltaYtd != 0)
                                        {{ $deltaYtd > 0 ? '+' : '' }}{{ number_format($deltaYtd, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-right {{ $deltaM1 < 0 ? 'negative-value' : ($deltaM1 > 0 ? 'positive-value' : 'zero-value') }}" style="background-color: {{ $grandBg }};">
                                    @if($deltaM1 != 0)
                                        {{ $deltaM1 > 0 ? '+' : '' }}{{ number_format($deltaM1, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="modal fade ph-nominatif-modal" id="phNominatifModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="phNominatifTitle">Nominatif PH</h5>
                    <div class="text-muted small" id="phNominatifMeta">-</div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="ph-nominatif-summary" id="phNominatifSummary"></div>
                <div class="ph-nominatif-table-wrap">
                    <table class="table table-sm table-bordered ph-nominatif-table mb-0">
                        <thead id="phNominatifHead">
                            <tr><th>Memuat</th></tr>
                        </thead>
                        <tbody id="phNominatifBody">
                            <tr><td class="text-center text-muted py-4">Double-click baris segmen untuk melihat nominatif.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const nominatifUrl = @json(route('report.dashboard-pinjaman.data-ph.nominatif'));
        const modal = document.getElementById('phNominatifModal');
        const title = document.getElementById('phNominatifTitle');
        const meta = document.getElementById('phNominatifMeta');
        const summary = document.getElementById('phNominatifSummary');
        const head = document.getElementById('phNominatifHead');
        const body = document.getElementById('phNominatifBody');
        const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

        const escapeHtml = function (value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const formatCell = function (value, column) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            if (column.type === 'number' && !Number.isNaN(Number(value))) {
                return numberFormatter.format(Number(value));
            }

            return escapeHtml(value);
        };

        const openModal = function () {
            if (window.jQuery && modal) {
                window.jQuery(modal).modal('show');
            }
        };

        const renderLoading = function (trigger) {
            title.textContent = 'Nominatif PH - ' + (trigger.dataset.segmentLabel || '-');
            meta.textContent = [trigger.dataset.scopeLabel || '-', trigger.dataset.period || '-'].filter(Boolean).join(' | ');
            summary.innerHTML = '<span>Memuat data LW325 PH...</span>';
            head.innerHTML = '<tr><th>Memuat</th></tr>';
            body.innerHTML = '<tr><td class="text-center text-muted py-4">Mengambil nominatif...</td></tr>';
        };

        const renderPayload = function (payload) {
            const columns = payload.columns || [];
            const rows = payload.rows || [];
            const totalCount = Number(payload.total_count || 0);
            const displayCount = Number(payload.display_count || rows.length);
            const totalPokok = Number(payload.total_pokok || 0);

            summary.innerHTML = [
                '<span>Rekening: ' + numberFormatter.format(totalCount) + '</span>',
                '<span>Ditampilkan: ' + numberFormatter.format(displayCount) + '</span>',
                '<span>Total Pokok: ' + numberFormatter.format(totalPokok) + '</span>'
            ].join('');

            if (!columns.length || !rows.length) {
                head.innerHTML = '<tr><th>Nominatif</th></tr>';
                body.innerHTML = '<tr><td class="text-center text-muted py-4">Tidak ada nominatif untuk scope ini.</td></tr>';
                return;
            }

            head.innerHTML = '<tr>' + columns.map(function (column) {
                return '<th>' + escapeHtml(column.label || column.key) + '</th>';
            }).join('') + '</tr>';

            body.innerHTML = rows.map(function (row) {
                return '<tr>' + columns.map(function (column) {
                    const align = column.type === 'number' ? ' text-right' : '';
                    return '<td class="' + align.trim() + '">' + formatCell(row[column.key], column) + '</td>';
                }).join('') + '</tr>';
            }).join('');
        };

        document.querySelectorAll('[data-ph-nominatif-row]').forEach(function (row) {
            row.addEventListener('dblclick', function () {
                const params = new URLSearchParams({
                    periode: row.dataset.period || '',
                    segment: row.dataset.segment || 'total',
                    kanca: row.dataset.kanca || '',
                    unit_kerja: row.dataset.unit || 'all'
                });

                renderLoading(row);
                openModal();

                fetch(nominatifUrl + '?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            return response.text().then(function (text) {
                                throw new Error(text || 'Gagal mengambil nominatif PH.');
                            });
                        }

                        return response.json();
                    })
                    .then(renderPayload)
                    .catch(function (error) {
                        summary.innerHTML = '<span>Gagal memuat</span>';
                        head.innerHTML = '<tr><th>Error</th></tr>';
                        body.innerHTML = '<tr><td class="text-center text-danger py-4">' + escapeHtml(error.message || error) + '</td></tr>';
                    });
            });
        });
    });
</script>
@include('report.partials.sticky-table-viewport-script', [
    'wrapperSelector' => '.kejar-laba-table-shell',
    'tableSelector' => '.kejar-laba-table',
])
@endsection

