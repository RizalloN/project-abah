@extends('layouts.admin')

@section('title', 'Report Recovery')

@section('content')
<style>
    :root {
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --surface-color: #ffffff;
        --bg-color: #f8fafc;
        --border-color: #e2e8f0;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --table-header-bg: var(--primary-blue-dark);
        --table-header-text: #ffffff;
        --accent-color: #f59e0b;
        --loan-blue-soft: #eaf2ff;
    }

    .kejar-laba-wrapper {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
        padding-top: 0.75rem;
        padding-bottom: 2rem;
    }

    .kejar-laba-card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: visible; /* Changed from hidden to allow dropdowns to pop out */
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        margin-bottom: 1.5rem;
    }

    .kejar-laba-card-header {
        padding: 1.5rem 1.75rem;
        background: linear-gradient(to right, #f8fafc, #ffffff);
        border-bottom: 1px solid var(--border-color);
    }

    .kejar-laba-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding: 1.45rem 1.25rem;
        border-radius: 16px 16px 0 0;
        background:
            radial-gradient(circle at 12% 18%, rgba(255, 103, 31, 0.16), transparent 26%),
            radial-gradient(circle at 88% 10%, rgba(59, 130, 246, 0.22), transparent 28%),
            linear-gradient(135deg, #003b75 0%, #00529c 48%, #0f4c97 100%);
        color: #ffffff;
        box-shadow: 0 18px 40px -30px rgba(0, 55, 116, 0.55);
    }

    .kejar-laba-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(120deg, rgba(255, 255, 255, 0.12), transparent 35%),
            repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 18px);
        opacity: 0.72;
    }

    .kejar-laba-title-wrap {
        width: min(100%, 860px);
        text-align: center;
        padding: 0.05rem 1rem;
    }

    .kejar-laba-title-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.6rem;
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

    .kejar-laba-title {
        margin: 0;
        font-size: clamp(1.18rem, 2.05vw, 2rem);
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 0.035em;
        line-height: 1.08;
        text-transform: uppercase;
        text-shadow: 0 10px 26px rgba(0, 18, 50, 0.28);
    }

    .kejar-laba-title::after {
        content: '';
        display: block;
        width: min(130px, 38vw);
        height: 3px;
        margin: 0.7rem auto 0;
        border-radius: 999px;
        background: linear-gradient(90deg, #ff671f, #f9b233, rgba(255, 255, 255, 0.9));
        box-shadow: 0 8px 18px rgba(255, 103, 31, 0.28);
    }

    .kejar-laba-subtitle {
        margin: 0.65rem auto 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.78rem;
        line-height: 1.6;
        max-width: 660px;
    }

    .kejar-laba-date-badge {
        position: absolute;
        right: 1.25rem;
        top: 1.25rem;
        border-radius: 999px;
        font-weight: 800;
        background: rgba(255, 255, 255, 0.14);
        color: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.22);
    }

    @media (max-width: 767.98px) {
        .kejar-laba-date-badge {
            position: static;
            margin-top: 1rem;
        }
    }

    .filter-section {
        padding: 1.75rem 1.5rem;
        background: #ffffff;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        z-index: 50;
    }

    /* ── Premium Modern Selectors ── */
    .loan-filter-modern {
        display: grid;
        grid-template-columns: repeat(4, 1fr) auto;
        gap: 1rem;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(25px);
        padding: 1.5rem;
        border-radius: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 
            0 10px 15px -3px rgba(0, 0, 0, 0.05),
            0 30px 60px -20px rgba(8, 87, 195, 0.2);
        margin-bottom: 2.5rem;
        position: relative;
        z-index: 1000;
        align-items: flex-end;
    }

    .kejar-laba-card, .filter-section {
        overflow: visible !important;
    }

    .loan-filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        position: relative;
    }

    /* Descending z-index for items */
    .loan-filter-item:nth-child(1) { z-index: 40; }
    .loan-filter-item:nth-child(2) { z-index: 30; }
    .loan-filter-item:nth-child(3) { z-index: 20; }
    .loan-filter-item:nth-child(4) { z-index: 10; }

    .loan-filter-modern .loan-filter-label {
        font-size: 0.72rem;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-left: 0.65rem;
    }

    .loan-dropdown {
        position: relative;
        width: 100%;
    }

    .loan-dropdown-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: var(--primary-blue);
        font-size: 0.95rem;
        pointer-events: none;
        opacity: 0.8;
    }

    .loan-dropdown-toggle {
        width: 100%;
        height: 52px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0 1rem 0 2.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        font-size: 0.88rem;
        color: #1e293b;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
    }

    .loan-dropdown-toggle:hover {
        border-color: var(--primary-blue-light);
        background: #f8fafc;
        transform: translateY(-1px);
    }

    .loan-dropdown.is-open { z-index: 3100 !important; }
    .loan-dropdown.is-open .loan-dropdown-toggle {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1);
    }

    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 100%;
        min-width: 280px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(25px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 1.25rem;
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
        z-index: 3000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(12px);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 400px;
        overflow-y: auto;
        padding: 0.65rem;
    }

    .loan-dropdown.is-open .loan-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .loan-dropdown-option {
        width: 100%;
        padding: 0.72rem 1rem;
        border: none;
        background: transparent;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 0.85rem;
        font-weight: 700;
        font-size: 0.85rem;
        color: #475569;
        transition: all 0.2s;
        text-align: left;
        margin-bottom: 2px;
    }

    .loan-dropdown-option:hover { background: #f1f5f9; color: var(--primary-blue); }
    .loan-dropdown-option.is-active { background: rgba(30, 64, 175, 0.06); color: var(--primary-blue); }

    .loan-dropdown-check {
        width: 1.2rem;
        height: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 0.75rem;
        color: white;
        flex-shrink: 0;
    }

    .loan-dropdown-option.is-active .loan-dropdown-check {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .btn-loan-modern-submit {
        height: 52px;
        min-width: 160px;
        padding: 0 1.5rem;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--primary-blue) 0%, #1e3a8a 100%);
        color: white;
        border: none;
        font-weight: 800;
        font-size: 0.9rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        transition: all 0.3s;
        box-shadow: 0 8px 16px rgba(30, 64, 175, 0.25);
    }

    .btn-loan-modern-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(30, 64, 175, 0.35);
    }

    .loan-dropdown-search {
        padding: 0.5rem;
        position: sticky;
        top: -0.65rem;
        background: white;
        z-index: 10;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 0.5rem;
        border-radius: 1rem 1rem 0 0;
    }

    .loan-dropdown-search input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.82rem;
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
        border-radius: 10px;
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
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        outline: none;
    }

    /* Multi-select Dropdown Style (from Dashboard Harian) */
    .daily-dropdown {
        position: relative;
        width: 100%;
    }

    .daily-dropdown-toggle {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: #f9fafb;
        color: var(--text-main);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.6rem 1rem;
        font-weight: 700;
        font-size: 0.88rem;
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
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 15px 30px -10px rgba(0,0,0,0.1);
        z-index: 100;
        padding: 0.5rem;
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
        padding: 0.6rem 0.75rem;
        border-radius: 8px;
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

    .is-active .daily-dropdown-check {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .daily-dropdown-check i {
        color: white;
        font-size: 10px;
        display: none;
    }

    .is-active .daily-dropdown-check i {
        display: block;
    }

    .btn-apply {
        background: var(--primary-blue);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.78rem 1.75rem;
        font-weight: 700;
        font-size: 0.95rem;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.2);
        cursor: pointer;
    }

    .btn-apply:hover {
        background: var(--primary-blue-dark);
        transform: translateY(-1px);
    }

    /* Searchable Dropdown Extensions */
    .daily-search-shell {
        padding: 0.5rem 0.75rem 0.45rem;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        position: sticky;
        top: 0;
        z-index: 10;
        backdrop-filter: blur(8px);
    }

    .daily-search-inner {
        position: relative;
    }

    .daily-search-inner i {
        position: absolute;
        left: 0.65rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    .daily-search-input {
        width: 100%;
        padding: 0.45rem 0.65rem 0.45rem 1.85rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 500;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .daily-search-input:focus {
        background: #ffffff;
        border-color: var(--primary-blue-light);
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
        background: #e2e8f0;
        border-radius: 10px;
    }

    /* Table Wrapper with Sticky Viewport */
    .kejar-laba-table-shell {
        position: relative;
        width: 100%;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        z-index: 10;
    }

    /* Integration with report.partials.sticky-table-viewport-style */
    @include('report.partials.sticky-table-viewport-style', [
        'wrapperSelector' => '.kejar-laba-table-shell',
        'tableSelector' => '.kejar-laba-table'
    ])

    .kejar-laba-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: auto;
        background: #ffffff;
    }

    .kejar-laba-table thead th {
        background-color: var(--table-header-bg) !important;
        color: var(--table-header-text);
        text-transform: uppercase;
        font-size: 0.72rem;
        padding: 0.85rem 1.1rem;
        font-weight: 800;
        z-index: 30;
        border-right: 1px solid rgba(255,255,255,0.08);
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        white-space: nowrap;
    }

    .kejar-laba-table thead tr:nth-child(2) th {
        background: #274bba !important;
        padding: 0.55rem 0.75rem;
    }

    .kejar-laba-table tbody td {
        font-size: 0.82rem;
        background: #ffffff;
        font-variant-numeric: tabular-nums;
        padding: 0.85rem 1.1rem;
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
    }

    .kejar-laba-table tbody tr:nth-child(even) td {
        background: #fafbfd;
    }

    .kejar-laba-table tbody tr:hover td {
        background: #f1f5f9;
    }

    /* Fixed Headers and Columns Color Fix */
    .kejar-laba-table th.sticky-col {
        background-color: var(--table-header-bg) !important;
        z-index: 40 !important; /* Above regular sticky headers */
    }
    
    .kejar-laba-table td.sticky-col {
        background-color: #ffffff !important;
        z-index: 20; /* Above regular cells, below headers */
    }
    
    .kejar-laba-table tr:nth-child(even) td.sticky-col {
        background: #fafbfd !important;
    }

    .sticky-col {
        position: sticky;
        left: 0;
        box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.1);
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-weight-bold { font-weight: 700; }
    
    .negative-value { color: #dc2626; font-weight: 700; }
    .positive-value { color: #15803d; font-weight: 700; }
    .zero-value { color: var(--text-muted); opacity: 0.5; }

    .currency-symbol { font-size: 0.65rem; margin-right: 2px; color: var(--text-muted); font-weight: normal; }
</style>

<div class="kejar-laba-wrapper pt-4">
    <div class="kejar-laba-card">
        <div class="kejar-laba-card-header kejar-laba-hero d-flex flex-wrap align-items-center justify-content-center gap-3">
            <div class="kejar-laba-title-wrap">
                <div class="kejar-laba-title-badge">
                    <i class="fas fa-university"></i>
                    <span>BRI Recovery Performance</span>
                </div>
                <h1 class="kejar-laba-title">REPORT RECOVERY</h1>
                <div class="kejar-laba-subtitle">Monitoring pencapaian Recovery berdasarkan data Cognos secara ringkas dan profesional.</div>
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
            <form action="{{ route('report.dashboard-pinjaman.kejar-laba') }}" method="GET" id="filterForm">
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
                                        <span>{{ \Carbon\Carbon::parse($period)->translatedFormat('d M Y') }}</span>
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

                    {{-- Posisi RKA --}}
                    <div class="loan-filter-item">
                        <label class="loan-filter-label">Posisi RKA</label>
                        <div class="loan-dropdown" data-loan-dropdown="rka">
                            <i class="fas fa-chart-line loan-dropdown-icon"></i>
                            <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="rka">
                                <span class="loan-dropdown-text">Pilih Posisi</span>
                                <i class="fas fa-chevron-down small opacity-50"></i>
                            </button>
                            <div class="loan-dropdown-menu" data-loan-dropdown-menu="rka">
                                @foreach($posisi_rka_options as $opt)
                                    <div class="loan-dropdown-option {{ (isset($selectedRka) && $selectedRka === $opt['value']) ? 'is-active' : '' }}" data-value="{{ $opt['value'] }}">
                                        <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                        <span>{{ $opt['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <select name="rka_period" id="rkaInput" class="d-none">
                                @foreach($posisi_rka_options as $opt)
                                    <option value="{{ $opt['value'] }}" {{ (isset($selectedRka) && $selectedRka === $opt['value']) ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
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

                    // Standard Select Sync (Periode & RKA)
                    ['periode', 'rka'].forEach(type => {
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

                    if (!disableUnit) {
                        allUnitsData.forEach(unit => {
                            if (unit.value === 'all') return;
                            if (selected.includes(unit.kanca_value)) {
                                const opt = document.createElement('div');
                                opt.className = `loan-dropdown-option ${unit.value === unitInput.value ? 'is-active' : ''}`;
                                opt.dataset.value = unit.value;
                                opt.innerHTML = `<div class="loan-dropdown-check"><i class="fas fa-check"></i></div><span>${unit.label}</span>`;
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

                    if (disableUnit) {
                        unitInput.value = 'all';
                        unitLabel.textContent = 'Semua Unit Kerja';
                    }
                }
        </script>

        <div class="kejar-laba-table-shell">
            <table class="kejar-laba-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky-col">No</th>
                        <th rowspan="2" class="sticky-col" style="left: 64px;">Kanca</th>
                        <th rowspan="2">BUC</th>
                        <th rowspan="2">{{ $isArea6AllSelected ? 'BRANCH OFFICE' : 'Unit' }}</th>
                        <th colspan="4" class="text-center">Recovery (M-1)</th>
                        <th colspan="4" class="text-center">Recovery ({{ \Carbon\Carbon::parse($selectedPeriod)->translatedFormat('d M Y') }})</th>
                        <th colspan="4" class="text-center">RKA (Target)</th>
                        <th colspan="4" class="text-center">Delta (MtD vs RKA)</th>
                    </tr>
                    <tr>
                        <!-- Recovery M-1 -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                        <!-- Recovery Curr -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                        <!-- RKA -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                        <!-- Delta -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="text-center sticky-col">{{ $row['no'] }}</td>
                            <td class="sticky-col" style="left: 64px; font-weight: 700; color: var(--primary-blue-dark);">{{ $row['kanca'] }}</td>
                            <td class="text-center" style="font-weight: 600;">{{ $row['buc'] }}</td>
                            <td style="min-width: 250px; font-weight: 600;">
                                {{ $isArea6AllSelected ? ($row['branch_office'] ?? $row['unit']) : $row['unit'] }}
                            </td>
                            
                            {{-- Recovery M-1 --}}
                            @include('report.partials.kejar-laba-metrics', ['metrics' => $row['recovery_m1']])
                            
                            {{-- Recovery Current --}}
                            @include('report.partials.kejar-laba-metrics', ['metrics' => $row['recovery_curr']])
                            
                            {{-- RKA --}}
                            @include('report.partials.kejar-laba-metrics', ['metrics' => $row['rka']])
                            
                            {{-- Delta --}}
                            @foreach(['micro', 'small', 'consumer', 'total'] as $seg)
                                <td class="text-right {{ $row['delta'][$seg] < 0 ? 'negative-value' : ($row['delta'][$seg] > 0 ? 'positive-value' : 'zero-value') }}">
                                    @if($row['delta'][$seg] != 0)
                                        <span class="currency-symbol">Rp</span>{{ number_format($row['delta'][$seg], 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="20" class="text-center py-5 text-muted">
                                <i class="fas fa-info-circle mr-2"></i> Tidak ada data untuk periode yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('report.partials.sticky-table-viewport-script', [
            'wrapperSelector' => '.kejar-laba-table-shell',
            'tableSelector' => '.kejar-laba-table',
            'visibleRowLimit' => 30
        ])
    </div>
</div>
@endsection
