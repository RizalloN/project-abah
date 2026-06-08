@extends('layouts.admin')

@section('title', 'Dashboard Dana')

@section('content')
<style>
    :root {
        --dana-bg: #f8fafc;
        --dana-primary: #0f4c81; /* Biru Nusantara */
        --dana-primary-light: #1e40af;
        --dana-accent: #3b82f6;
        --dana-success: #059669;
        --dana-danger: #dc2626;
        --dana-border: #e2e8f0;
        --dana-card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
    }

    /* Google Fonts Import for better typography */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap');

    .dana-dashboard {
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
        background-color: var(--dana-bg);
        min-height: 100vh;
        padding-bottom: 4rem;
        overflow-x: hidden;
    }

    .dana-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        background: linear-gradient(135deg, #003b75 0%, #00529c 100%);
        border-bottom: 1px solid rgba(219, 229, 239, 0.92);
        color: #ffffff;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }

    .dana-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(120deg, rgba(255, 255, 255, 0.05), transparent 40%);
        opacity: 0.72;
    }

    .title-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 2rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: white;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .animate-reveal {
        animation: reveal 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
        transform: translateY(20px);
    }

    @keyframes reveal {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stagger-2 { animation-delay: 0.1s; }
    .stagger-3 { animation-delay: 0.2s; }

    .dana-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .dana-card {
        background: white;
        border-radius: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: var(--dana-card-shadow);
        overflow: hidden;
        backdrop-filter: blur(10px);
        transition: transform 0.3s ease;
    }

    .dana-filter-bar {
        position: relative;
        z-index: 100; /* Higher than table headers (z-index: 20-25) */
        background: rgba(255, 255, 255, 0.9);
        padding: 1.5rem;
        border-radius: 1.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        align-items: flex-end;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 2.5rem;
        border: 1px solid white;
        backdrop-filter: blur(8px);
    }

    .filter-item {
        flex: 1;
        min-width: 220px;
    }

    .filter-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 0.6rem;
        display: block;
        letter-spacing: 0.05em;
    }

    .filter-select {
        width: 100%;
        height: 46px;
        border-radius: 0.85rem;
        border: 1.5px solid #e2e8f0;
        padding: 0 1.25rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: #1e293b;
        background-color: #ffffff;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .filter-select:focus {
        border-color: var(--dana-accent);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .dana-dropdown {
        position: relative;
        width: 100%;
    }

    .dana-filter-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: var(--dana-accent);
        font-size: 1rem;
        pointer-events: none;
    }

    .dana-dropdown-toggle {
        width: 100%;
        height: 52px;
        background: white;
        border: 2px solid #eef2f6;
        border-radius: 16px;
        padding: 0 1.25rem 0 3rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        font-size: 0.95rem;
        color: #1e293b;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .dana-dropdown-toggle:hover {
        border-color: var(--bri-blue);
        box-shadow: 0 4px 12px rgba(0, 114, 187, 0.08);
        transform: translateY(-1px);
    }

    .dana-dropdown.is-open .dana-dropdown-toggle {
        border-color: var(--bri-blue);
        box-shadow: 0 0 0 4px rgba(0, 114, 187, 0.1);
        background: #f8fafc;
    }

    .dana-dropdown.is-open {
        z-index: 1100; /* Ensure the active dropdown is on top of everything */
    }

    .dana-dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        left: 0;
        width: 100%; /* Match the toggle width */
        min-width: 280px; /* But allow it to be wider for long text */
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 1.5rem;
        box-shadow: 
            0 10px 15px -3px rgba(0, 0, 0, 0.1), 
            0 4px 6px -2px rgba(0, 0, 0, 0.05),
            0 25px 50px -12px rgba(0, 0, 0, 0.15);
        z-index: 1100; /* Above sticky headers and loaders */
        opacity: 0;
        visibility: hidden;
        transform: translateY(12px);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 480px; /* Increased height as requested */
        overflow-y: auto;
        padding: 0.75rem;
    }

    /* Custom scrollbar for mewah feel */
    .dana-dropdown-menu::-webkit-scrollbar {
        width: 6px;
    }
    .dana-dropdown-menu::-webkit-scrollbar-track {
        background: transparent;
    }
    .dana-dropdown-menu::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
    .dana-dropdown-menu::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }

    .dana-dropdown.is-open .dana-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dana-dropdown-option {
        width: 100%;
        padding: 0.85rem 1.25rem; /* Increased padding for better tap target */
        border: none;
        background: transparent;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        font-weight: 700;
        font-size: 0.92rem;
        color: #334155;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
        margin-bottom: 2px;
    }

    .dana-dropdown-option:hover {
        background: #f8fafc;
        color: var(--dana-accent);
        transform: translateX(4px);
    }

    .dana-dropdown-option.is-active {
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
        color: var(--dana-accent);
    }

    .dana-dropdown-check {
        width: 1.25rem;
        height: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.2s;
    }

    .dana-dropdown-option.is-active .dana-dropdown-check {
        opacity: 1;
    }

    .btn-dana-refresh {
        background: linear-gradient(135deg, var(--dana-accent) 0%, #2563eb 100%);
        color: white;
        border: none;
        padding: 0 2rem;
        border-radius: 0.85rem;
        font-weight: 700;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
        height: 46px;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .btn-dana-refresh:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        filter: brightness(1.1);
    }

    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
        backdrop-filter: blur(4px);
    }

    .dana-table-container {
        margin: 0;
        position: relative; /* For loader positioning */
    }

    .dana-table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
    }

    .dana-table thead th {
        background: #004685;
        color: #ffffff;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 0.2rem 0.15rem;
        border-bottom: 2px solid rgba(255,255,255,0.1);
        position: sticky;
        top: 0;
        z-index: 20;
        text-align: center;
    }

    .dana-table thead tr.group-row th {
        font-size: 0.7rem;
        padding: 0.3rem 0.2rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    /* Gradients for headers */
    .dana-table thead .group-position { 
        background: linear-gradient(180deg, #005bb7 0%, #004685 100%); 
        border-right: 1px solid rgba(255,255,255,0.05);
    }
    .dana-table thead .group-delta { 
        background: linear-gradient(180deg, #004a8f 0%, #003366 100%); 
        border-right: 1px solid rgba(255,255,255,0.05);
    }
    .dana-table thead .group-rka { 
        background: linear-gradient(180deg, #16a34a 0%, #15803d 100%); 
    }

    .dana-table tbody td {
        padding: 0.02rem 0.2rem; /* Extreme density */
        font-size: 0.7rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        line-height: 1.1;
        white-space: nowrap;
        transition: background-color 0.2s;
    }

    .dana-table tbody tr:not(.subtotal-row):not(.grandtotal-row):hover td {
        background-color: #f8fafc !important;
    }

    /* Disable hover on totals */
    .dana-table tbody tr.subtotal-row:hover td,
    .dana-table tbody tr.grandtotal-row:hover td {
        background-color: inherit !important;
    }

    .dana-table .branch-cell {
        font-weight: 800;
        color: #0f172a;
        text-align: left;
        padding-left: 0.75rem !important;
        background: #ffffff !important;
        font-size: 0.7rem;
        border-right: 1px solid #e2e8f0;
        position: sticky;
    }

    .dana-table .branch-name {
        max-width: 100px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dana-table .cat-cell {
        font-weight: 600;
        color: #475569;
        font-size: 0.68rem;
        padding-left: 10px !important;
    }

    .subtotal-row .cat-cell {
        max-width: none !important;
        text-overflow: clip !important;
        white-space: nowrap;
    }

    .dana-table .val-cell {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 600;
        text-align: right;
        color: #1e293b;
    }

    .dana-table .delta-cell {
        text-align: right;
        font-weight: 700;
        font-family: 'JetBrains Mono', monospace;
    }

    .text-pos { color: #059669; }
    .text-neg { color: #dc2626; }

    .perf-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 65px;
        padding: 0.4rem 0.75rem;
        border-radius: 2rem;
        font-weight: 800;
        font-size: 0.75rem;
        letter-spacing: -0.01em;
    }

    .perf-badge.bg-pos { 
        background: #ecfdf5; 
        color: #065f46; 
        border: 1px solid #d1fae5;
    }
    .perf-badge.bg-neg { 
        background: #fef2f2; 
        color: #991b1b; 
        border: 1px solid #fee2e2;
    }

    .sticky-col {
        position: sticky;
        background: white;
        z-index: 10;
        border-right: 1px solid #e2e8f0 !important;
    }

    .subtotal-row {
        background-color: #e0f2fe !important; /* High contrast light blue */
    }
    
    .subtotal-row td {
        font-weight: 800;
        color: #0369a1; /* Contrasting dark blue text */
        border-bottom: 1px solid #bae6fd;
    }

    .grandtotal-row {
        background: linear-gradient(90deg, #0f4c81 0%, #1e40af 100%) !important;
        position: sticky;
        bottom: 0;
        z-index: 25;
    }

    .grandtotal-row td {
        color: white !important;
        font-weight: 800;
        font-size: 0.9rem;
        padding: 1.25rem 1rem;
        border: none;
    }

    /* Number formatting helper */
    .val-cell span.neg-val {
        color: var(--dana-danger);
    }

    /* Capture Status Modal Refinement */
    .capture-status-modal .modal-content {
        border-radius: 2rem;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    .btn-capture-all {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(4px);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 1rem;
        font-weight: 700;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-capture-all:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .dana-dashboard .dana-card {
        border-radius: 12px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02) !important;
        border: 1px solid #e2e8f0 !important;
        background: #ffffff !important;
        overflow: hidden !important;
    }

    .dana-dashboard .dana-filter-bar {
        border-radius: 12px !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02) !important;
        border: 1px solid #e2e8f0 !important;
        background: #ffffff !important;
        padding: 1.25rem !important;
    }

    .dana-dashboard .dana-dropdown-toggle {
        border-radius: 8px !important;
        border: 1.5px solid #e2e8f0 !important;
        height: 42px !important;
        padding-left: 2.75rem !important;
        font-size: 0.88rem !important;
        box-shadow: none !important;
        transition: all 0.2s ease !important;
    }

    .dana-dashboard .dana-dropdown-toggle:hover {
        border-color: var(--dana-accent) !important;
        background: #fafbfc !important;
    }

    .dana-dashboard .dana-dropdown-menu {
        border-radius: 12px !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08) !important;
    }

    .dana-dashboard .dana-dropdown-option {
        border-radius: 8px !important;
        font-size: 0.85rem !important;
    }

    .dana-dashboard .btn-dana-refresh {
        border-radius: 8px !important;
        height: 42px !important;
        font-weight: 600 !important;
        padding: 0 1.5rem !important;
        background: linear-gradient(135deg, #0f4c81 0%, #1e40af 100%) !important;
        box-shadow: 0 4px 12px rgba(15, 76, 129, 0.2) !important;
    }

    .dana-dashboard .btn-dana-refresh:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 16px rgba(15, 76, 129, 0.3) !important;
    }

    .dana-dashboard .btn-capture-all {
        border-radius: 8px !important;
        font-weight: 600 !important;
        padding: 0.5rem 1rem !important;
    }

    .dana-dashboard .perf-badge {
        border-radius: 6px !important;
        font-size: 0.72rem !important;
        font-weight: 750 !important;
        padding: 0.25rem 0.5rem !important;
        min-width: 60px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .perf-badge.bg-warn { 
        background: #fffbeb !important; 
        color: #b45309 !important; 
        border: 1px solid #fef3c7 !important;
    }

    .capture-status-modal .modal-content {
        border-radius: 0 !important;
    }

    .capture-status-modal .btn {
        border-radius: 0 !important;
    }

    /* Modernized Classic Excel Table Grid styling - BRI Corporate Color Scheme */
    .dana-dashboard .dana-table thead th,
    .dana-dashboard .dana-table thead .group-position,
    .dana-dashboard .dana-table thead .group-delta,
    .dana-dashboard .dana-table thead .group-rka {
        background: linear-gradient(180deg, #0857c3 0%, #06469c 100%) !important;
        border: 1px solid #1e40af !important;
        color: #ffffff !important;
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        padding: 0.5rem 0.3rem !important;
    }

    .dana-dashboard .dana-table tbody td {
        border: 1px solid #cbd5e1 !important;
        background-clip: padding-box;
        padding: 0.4rem 0.5rem !important;
        font-size: 0.72rem !important;
        font-weight: 500 !important;
    }

    .dana-dashboard .subtotal-row,
    .dana-dashboard .subtotal-row td {
        background: #eef6ff !important;
        color: #0857c3 !important;
        font-weight: 700 !important;
        border-color: #cfe0f4 !important;
    }

    .dana-dashboard .grandtotal-row,
    .dana-dashboard .grandtotal-row td {
        background: linear-gradient(90deg, #0857c3 0%, #06469c 100%) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        font-size: 0.8rem !important;
        border-color: #1e40af !important;
    }

    .dana-dashboard .sticky-col,
    .dana-dashboard .branch-cell {
        border-color: #cbd5e1 !important;
    }

    .text-pos { color: #047857 !important; }
    .text-neg { color: #b91c1c !important; }

</style>

<div class="dana-dashboard">
    <div class="dana-hero d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <h1 class="m-0" style="font-size: 1.5rem; font-weight: 800; letter-spacing: 0.02em; text-transform: uppercase;">DASHBOARD DANA</h1>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button id="captureAllBtn" class="btn btn-sm btn-capture-all">
                <i class="fas fa-file-image mr-1"></i> EXPORT A4
            </button>
        </div>
    </div>

    <div class="dana-container">
        <div class="dana-filter-bar animate-reveal stagger-3">
            <div class="filter-item">
                <label class="filter-label">Periode Data</label>
                <div class="dana-dropdown" data-dana-dropdown="periode">
                    <i class="fas fa-calendar-alt dana-filter-icon"></i>
                    <button type="button" class="dana-dropdown-toggle" data-dana-dropdown-toggle="periode">
                        <span class="dana-dropdown-text">Pilih Periode</span>
                        <i class="fas fa-chevron-down small opacity-50"></i>
                    </button>
                    <div class="dana-dropdown-menu" data-dana-dropdown-menu="periode"></div>
                    <select id="filterPeriode" class="d-none">
                        @foreach($periods as $p)
                            <option value="{{ $p }}" {{ $selectedPeriod == $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="filter-item">
                <label class="filter-label">Periode RKA</label>
                <div class="dana-dropdown" data-dana-dropdown="rka">
                    <i class="fas fa-bullseye dana-filter-icon"></i>
                    <button type="button" class="dana-dropdown-toggle" data-dana-dropdown-toggle="rka">
                        <span class="dana-dropdown-text">Pilih RKA</span>
                        <i class="fas fa-chevron-down small opacity-50"></i>
                    </button>
                    <div class="dana-dropdown-menu" data-dana-dropdown-menu="rka"></div>
                    <select id="filterRka" class="d-none">
                        @foreach($rkaPeriods as $p)
                            <option value="{{ $p }}" {{ $selectedRka == $p ? 'selected' : '' }}>
                                {{ strtoupper(\Carbon\Carbon::parse($p)->translatedFormat('F Y')) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="filter-item">
                <label class="filter-label">Segmentasi</label>
                <div class="dana-dropdown" data-dana-dropdown="kategori">
                    <i class="fas fa-layer-group dana-filter-icon"></i>
                    <button type="button" class="dana-dropdown-toggle" data-dana-dropdown-toggle="kategori">
                        <span class="dana-dropdown-text">Pilih Segmen</span>
                        <i class="fas fa-chevron-down small opacity-50"></i>
                    </button>
                    <div class="dana-dropdown-menu" data-dana-dropdown-menu="kategori"></div>
                    <select id="filterKategori" class="d-none">
                        <option value="all">SEMUA SEGMEN</option>
                        @foreach($categories as $c)
                            <option value="{{ $c }}" {{ $selectedCategory == $c ? 'selected' : '' }}>{{ strtoupper($c) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button id="btnRefresh" class="btn-dana-refresh">
                <i class="fas fa-sync-alt"></i> Tampilkan
            </button>
        </div>

        <div class="dana-card">
            <div id="loader" class="loading-overlay" style="display: none;">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="font-weight-bold text-primary">Mengolah Data...</div>
                </div>
            </div>

            <div class="dana-table-container">
                <div class="table-responsive">
                    <table class="dana-table">
                        <thead>
                            <tr class="group-row">
                                <th rowspan="2" class="sticky-col" width="135" style="left: 0; z-index: 21;">Kantor Cabang</th>
                                <th rowspan="2" class="text-center" width="140">Kategori</th>
                                <th colspan="3" class="text-center border-bottom group-position">Posisi Saldo (Rp)</th>
                                <th colspan="2" class="text-center border-bottom border-left group-delta">Delta Posisi</th>
                                <th colspan="2" class="text-center border-bottom border-left group-rka">Performa RKA</th>
                            </tr>
                            <tr>
                                <th class="text-right group-position" id="headerYtd">YTD</th>
                                <th class="text-right group-position" id="headerMtd">MTD</th>
                                <th class="text-right group-position" id="headerSelectedDate">Posisi</th>
                                <th class="text-right border-left group-delta">YTD</th>
                                <th class="text-right group-delta">MTD</th>
                                <th class="text-right border-left group-rka">Rp</th>
                                <th class="text-center group-rka">%</th>
                            </tr>
                        </thead>
                        <tbody id="danaContent">
                            <!-- JS Driven -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
        const selects = {
            periode: document.getElementById('filterPeriode'),
            rka: document.getElementById('filterRka'),
            kategori: document.getElementById('filterKategori')
        };

        const dropdowns = {
            periode: {
                root: document.querySelector('[data-dana-dropdown="periode"]'),
                toggle: document.querySelector('[data-dana-dropdown-toggle="periode"]'),
                menu: document.querySelector('[data-dana-dropdown-menu="periode"]')
            },
            rka: {
                root: document.querySelector('[data-dana-dropdown="rka"]'),
                toggle: document.querySelector('[data-dana-dropdown-toggle="rka"]'),
                menu: document.querySelector('[data-dana-dropdown-menu="rka"]')
            },
            kategori: {
                root: document.querySelector('[data-dana-dropdown="kategori"]'),
                toggle: document.querySelector('[data-dana-dropdown-toggle="kategori"]'),
                menu: document.querySelector('[data-dana-dropdown-menu="kategori"]')
            }
        };

        const initDanaDropdowns = () => {
            Object.keys(dropdowns).forEach(key => {
                const d = dropdowns[key];
                const select = selects[key];
                if (!d.root || !select) return;

                const updateMenu = () => {
                    const options = Array.from(select.options);
                    d.menu.innerHTML = options.map(opt => {
                        const active = opt.selected;
                        return `
                            <button type="button" class="dana-dropdown-option ${active ? 'is-active' : ''}" data-value="${opt.value}">
                                <span class="dana-dropdown-check"><i class="fas fa-check"></i></span>
                                <span>${opt.text}</span>
                            </button>
                        `;
                    }).join('');
                    
                    const activeOpt = options.find(o => o.selected);
                    d.toggle.querySelector('.dana-dropdown-text').textContent = activeOpt ? activeOpt.text : 'Pilih...';
                };

                updateMenu();

                d.toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = d.root.classList.contains('is-open');
                    Object.values(dropdowns).forEach(dd => dd.root.classList.remove('is-open'));
                    if (!isOpen) d.root.classList.add('is-open');
                });

                d.menu.addEventListener('click', (e) => {
                    const btn = e.target.closest('.dana-dropdown-option');
                    if (!btn) return;
                    
                    select.value = btn.dataset.value;
                    updateMenu();
                    d.root.classList.remove('is-open');
                });
            });

            document.addEventListener('click', () => {
                Object.values(dropdowns).forEach(dd => dd.root.classList.remove('is-open'));
            });
        };

        initDanaDropdowns();

        const formatMoney = (val) => {
            const num = parseFloat(val) || 0;
            const formatted = new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.abs(num));
            
            return num < 0 ? `(${formatted})` : formatted;
        };

        const formatPercent = (val) => {
            return (parseFloat(val) || 0).toFixed(2) + '%';
        };

        const getColorClass = (val) => {
            const num = parseFloat(val) || 0;
            return num > 0 ? 'text-pos' : (num < 0 ? 'text-neg' : '');
        };

        const getShortBranch = (name) => {
            const map = {
                'KC MADIUN': 'KC MDN',
                'KC MAGETAN': 'KC MGT',
                'KC NGAWI': 'KC NGWI',
                'KC PONOROGO': 'KC PNRG'
            };
            return map[name.toUpperCase()] || name;
        };

        const getCellBg = (pct) => {
            const num = parseFloat(pct) || 0;
            if (num >= 100) return '#d1fae5'; // Soft highly premium Excel green
            if (num < 90) return '#fee2e2'; // Soft highly premium Excel red
            return '#fef3c7'; // Soft highly premium Excel yellow
        };

        const getCellColor = (pct) => {
            const num = parseFloat(pct) || 0;
            if (num >= 100) return '#065f46'; // Premium dark green text
            if (num < 90) return '#991b1b'; // Premium dark red text
            return '#92400e'; // Premium dark yellow/brown text
        };

        const loadData = () => {
            $('#loader').fadeIn(200);
            
            const params = {
                periode: selects.periode.value,
                rka_periode: selects.rka.value,
                kategori: selects.kategori.value
            };

            $.get("{{ route('report.dashboard-dana.data') }}", params, function(res) {
                $('#loader').fadeOut(200);
                $('#headerSelectedDate').text(res.header_dates.selected);
                $('#headerYtd').text(res.header_dates.ytd);
                $('#headerMtd').text(res.header_dates.mtd);
                
                let html = '';
                res.rows.forEach((row, index) => {
                    const isTotal = row.is_total;
                    const isStartOfBranch = isTotal;
                    
                    html += `
                        <tr class="${isTotal ? 'subtotal-row' : ''}">
                            ${isStartOfBranch ? `
                            <td class="sticky-col branch-cell" rowspan="5" style="left: 0;">
                                <div class="branch-name">${row.nama_cabang}</div>
                            </td>
                            ` : ''}
                            <td class="text-left cat-cell ${isTotal ? 'font-weight-bold' : 'pl-3'}">
                                ${isTotal ? `TOTAL ${getShortBranch(row.nama_cabang)}` : row.kategori}
                            </td>
                            <td class="val-cell ${isTotal ? '' : 'text-muted'}">${formatMoney(row.ytd)}</td>
                            <td class="val-cell ${isTotal ? '' : 'text-muted'}">${formatMoney(row.mtd)}</td>
                            <td class="val-cell ${isTotal ? 'font-weight-bold' : ''}">${formatMoney(row.selected)}</td>
                            <td class="delta-cell ${getColorClass(row.delta_ytd)} ${isTotal ? 'font-weight-bold' : ''}">${formatMoney(row.delta_ytd)}</td>
                            <td class="delta-cell ${getColorClass(row.delta_mtd)} ${isTotal ? 'font-weight-bold' : ''}">${formatMoney(row.delta_mtd)}</td>
                            <td class="val-cell ${getColorClass(row.rka_rp)} ${isTotal ? 'font-weight-bold' : ''}" style="background: ${getCellBg(row.rka_pct)} !important; color: ${getCellColor(row.rka_pct)} !important;">${formatMoney(row.rka_rp)}</td>
                            <td class="perf-cell val-cell" style="background: ${getCellBg(row.rka_pct)} !important; color: ${getCellColor(row.rka_pct)} !important; font-weight: bold; text-align: center;">
                                ${formatPercent(row.rka_pct)}
                            </td>
                        </tr>
                    `;
                });

                const gt = res.total;
                html += `
                    <tr class="grandtotal-row">
                        <td colspan="2" class="text-center">TOTAL AREA 6</td>
                        <td class="val-cell">${formatMoney(gt.ytd)}</td>
                        <td class="val-cell">${formatMoney(gt.mtd)}</td>
                        <td class="val-cell">${formatMoney(gt.selected)}</td>
                        <td class="delta-cell ${getColorClass(gt.delta_ytd)}">${formatMoney(gt.delta_ytd)}</td>
                        <td class="delta-cell ${getColorClass(gt.delta_mtd)}">${formatMoney(gt.delta_mtd)}</td>
                        <td class="val-cell" style="background: ${getCellBg(gt.rka_pct)} !important; color: ${getCellColor(gt.rka_pct)} !important; font-weight: bold;">${formatMoney(gt.rka_rp)}</td>
                        <td class="perf-cell val-cell" style="background: ${getCellBg(gt.rka_pct)} !important; color: ${getCellColor(gt.rka_pct)} !important; font-weight: bold; text-align: center;">
                            ${formatPercent(gt.rka_pct)}
                        </td>
                    </tr>
                `;

                $('#danaContent').html(html);
            }).fail(function() {
                $('#loader').fadeOut(200);
                alert('Terjadi kesalahan saat memuat data.');
            });
        };

        $('#btnRefresh').click(loadData);
        loadData();

        // --- Capture All Logic (4K A4 Portrait) ---
        const captureBtn = document.getElementById('captureAllBtn');
        const captureModal = document.getElementById('captureStatusModal');
        
        const A4_EXPORT = {
            width: 3508, // 4K-ish
            height: 4961,
            marginX: 180,
            marginY: 160,
            headerHeight: 380,
            footerHeight: 120,
        };

        async function captureAllDanaDashboard() {
            $(captureModal).modal('show');
            $('#captureProgressUI').removeClass('d-none');
            $('#captureErrorUI, #captureSuccessUI').addClass('d-none');
            
            const originalBtnHtml = captureBtn.innerHTML;
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> CAPTURING...';

            try {
                const canvas = document.createElement('canvas');
                canvas.width = A4_EXPORT.width;
                canvas.height = A4_EXPORT.height;
                const ctx = canvas.getContext('2d');

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                ctx.fillStyle = '#0f4c81';
                ctx.fillRect(0, 0, A4_EXPORT.width, 40);
                
                ctx.fillStyle = '#0f172a';
                ctx.font = 'bold 88px "Plus Jakarta Sans", "Inter", sans-serif';
                ctx.fillText('Dashboard Dana Simpanan (SSA)', A4_EXPORT.marginX, A4_EXPORT.marginY + 80);
                
                ctx.fillStyle = '#64748b';
                ctx.font = '600 42px "Plus Jakarta Sans", "Inter", sans-serif';
                const periode = selects.periode.value;
                const kategori = selects.kategori.options[selects.kategori.selectedIndex].text;
                ctx.fillText(`Periode: ${periode} | Segmen: ${kategori}`, A4_EXPORT.marginX, A4_EXPORT.marginY + 160);

                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 6;
                ctx.beginPath();
                ctx.moveTo(A4_EXPORT.marginX, A4_EXPORT.marginY + 230);
                ctx.lineTo(A4_EXPORT.width - A4_EXPORT.marginX, A4_EXPORT.marginY + 230);
                ctx.stroke();

                const tableWrap = document.querySelector('.table-responsive');
                const capturedTable = await html2canvas(tableWrap, {
                    scale: 3, // Ultra HD
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });

                const targetWidth = A4_EXPORT.width - (A4_EXPORT.marginX * 2);
                const targetHeight = (capturedTable.height * targetWidth) / capturedTable.width;
                
                ctx.drawImage(capturedTable, A4_EXPORT.marginX, A4_EXPORT.marginY + 320, targetWidth, targetHeight);

                ctx.fillStyle = '#94a3b8';
                ctx.font = '600 32px "Plus Jakarta Sans", "Inter", sans-serif';
                const now = new Date().toLocaleString('id-ID');
                ctx.fillText(`Generated by A-Six Dashboard: ${now}`, A4_EXPORT.marginX, A4_EXPORT.height - 80);

                const link = document.createElement('a');
                link.download = `Dashboard-Dana-${periode.replace(/-/g, '')}-4K.jpg`;
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();

                $('#captureProgressUI').addClass('d-none');
                $('#captureSuccessUI').removeClass('d-none');
            } catch (err) {
                console.error(err);
                $('#captureProgressUI').addClass('d-none');
                $('#captureErrorUI').removeClass('d-none');
                $('#captureErrorMessage').text(err.message);
            } finally {
                captureBtn.disabled = false;
                captureBtn.innerHTML = originalBtnHtml;
            }
        }

        captureBtn.addEventListener('click', captureAllDanaDashboard);
    });
</script>
@endpush

<!-- Modal Capture Status -->
<div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center">
                <!-- Progress State -->
                <div id="captureProgressUI">
                    <div class="capture-status-modal-icon icon-loading">
                        <i class="fas fa-camera fa-spin"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Memproses Gambar...</h4>
                    <p class="text-muted mb-0">Sedang menyusun layout A4 Portrait kualitas tinggi. Mohon tunggu sejenak.</p>
                </div>

                <!-- Success State -->
                <div id="captureSuccessUI" class="d-none">
                    <div class="capture-status-modal-icon icon-success">
                        <i class="fas fa-check"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Berhasil!</h4>
                    <p class="text-muted mb-4">Gambar dashboard telah berhasil diunduh.</p>
                    <button type="button" class="btn btn-primary px-5" data-dismiss="modal">Tutup</button>
                </div>

                <!-- Error State -->
                <div id="captureErrorUI" class="d-none">
                    <div class="capture-status-modal-icon icon-error">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Oops! Gagal</h4>
                    <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kesalahan teknis saat memproses gambar.</p>
                    <button type="button" class="btn btn-secondary px-5" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>
