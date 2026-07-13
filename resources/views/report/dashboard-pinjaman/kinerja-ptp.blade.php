@extends('layouts.admin')

@section('title', 'Kinerja PTP')

@section('content')
<!-- Premium Fonts Integration -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    /* Premium Design System Variables aligned with A-SIX Dashboard */
    :root {
        --ptp-font-primary: 'Inter', 'Plus Jakarta Sans', sans-serif;
        --ptp-font-display: 'Inter', 'Outfit', sans-serif;
        --ptp-r-xl: 18px;
        --ptp-r-lg: 14px;
        --ptp-r-md: 8px;
        
        /* Cohesive Brand Theme Colors */
        --ptp-c-dark: #0f172a;
        --ptp-c-muted: #64748b;
        --ptp-c-border: #e2e8f0;
        --ptp-c-bg-light: #f8fafc;
        
        --ptp-c-blue: #0857c3;        /* A-SIX Brand Primary Blue */
        --ptp-c-blue-sub: #053b82;    /* A-SIX Navy Blue */
        --ptp-c-orange: #d97706;      /* Dashboard Amber */
        --ptp-c-orange-sub: #b45309;  /* Dark Amber */
        --ptp-c-yellow: #ca8a04;
        
        /* Premium Shadows */
        --ptp-shadow-sm: 0 1px 3px rgba(15,23,42,0.03), 0 1px 2px rgba(15,23,42,0.02);
        --ptp-shadow-md: 0 4px 12px -2px rgba(15,23,42,0.06), 0 2px 6px -1px rgba(15,23,42,0.03);
        --ptp-shadow-lg: 0 12px 24px -4px rgba(15,23,42,0.08), 0 4px 12px -2px rgba(15,23,42,0.04);
        --ptp-shadow-premium: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 10px 10px -5px rgba(15, 23, 42, 0.04);
    }

    .ptp-page {
        padding: 1.5rem 1.25rem 2rem;
        color: var(--ptp-c-dark);
        font-family: var(--ptp-font-primary);
        background: #f8fafc; /* Premium dashboard shell background */
        min-height: calc(100vh - 60px);
    }

    /* Premium Header Card matching .db-header */
    .ptp-header {
        margin-bottom: 1.25rem;
        padding: 1rem 1.5rem;
        border: 1px solid var(--ptp-c-border);
        border-top: 3px solid var(--ptp-c-blue);
        border-radius: var(--ptp-r-lg);
        background: #ffffff;
        box-shadow: var(--ptp-shadow-sm);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .ptp-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .ptp-logo {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--ptp-c-blue), #307fe2);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--ptp-r-md);
        font-size: 1.2rem;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(8, 87, 195, 0.15);
    }

    .ptp-title {
        margin: 0;
        font-family: var(--ptp-font-display);
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--ptp-c-dark);
        line-height: 1.2;
    }

    .ptp-subtitle {
        margin-top: 0.15rem;
        color: var(--ptp-c-muted);
        font-size: 0.72rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    
    .ptp-subtitle-badge {
        background: #e2e8f0;
        color: var(--ptp-c-dark);
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    /* Premium Control Cards matching .dana-filter-bar */
    .ptp-panel {
        border: 1px solid var(--ptp-c-border);
        border-radius: var(--ptp-r-lg);
        background: #ffffff;
        box-shadow: var(--ptp-shadow-sm);
        transition: all 0.2s ease;
        margin-bottom: 1.25rem;
    }
    
    .ptp-panel:hover {
        box-shadow: var(--ptp-shadow-md);
    }

    .ptp-panel-body {
        padding: 1.25rem 1.5rem;
    }

    .ptp-filter-label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--ptp-c-muted);
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .ptp-filter-control {
        min-height: 42px;
        border: 1.5px solid var(--ptp-c-border);
        border-radius: var(--ptp-r-md);
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--ptp-c-dark);
        background-color: #ffffff;
        transition: all 0.2s ease;
        padding-inline: 0.75rem;
    }
    
    .ptp-filter-control:focus {
        border-color: var(--ptp-c-blue);
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.15);
        background-color: #ffffff;
        outline: none;
    }

    .ptp-action {
        min-height: 42px;
        border-radius: var(--ptp-r-md);
        font-weight: 700;
        font-size: 0.88rem;
        letter-spacing: 0.025em;
        text-transform: uppercase;
        background: linear-gradient(135deg, var(--ptp-c-blue) 0%, var(--ptp-c-blue-sub) 100%);
        border: none;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(8, 87, 195, 0.15);
        transition: all 0.2s ease;
    }
    
    .ptp-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(8, 87, 195, 0.25);
        background: linear-gradient(135deg, var(--ptp-c-blue-sub) 0%, #032d66 100%);
    }

    .ptp-insight-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }

    .ptp-insight-card,
    .ptp-reading-panel {
        border: 1px solid var(--ptp-c-border);
        border-radius: var(--ptp-r-lg);
        background: #ffffff;
        box-shadow: var(--ptp-shadow-sm);
    }

    .ptp-insight-card {
        position: relative;
        overflow: hidden;
        padding: 1rem;
        border-top: 3px solid var(--accent, var(--ptp-c-blue));
    }

    .ptp-insight-card::after {
        content: "";
        position: absolute;
        right: -2.8rem;
        top: -3rem;
        width: 7rem;
        height: 7rem;
        border-radius: 50%;
        background: rgba(8, 87, 195, 0.08);
    }

    .ptp-insight-label {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        color: var(--ptp-c-muted);
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .ptp-insight-value {
        position: relative;
        z-index: 1;
        margin-top: 0.55rem;
        color: var(--ptp-c-dark);
        font-size: 1.25rem;
        font-weight: 900;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }

    .ptp-insight-note {
        position: relative;
        z-index: 1;
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.74rem;
        font-weight: 700;
    }

    .ptp-reading-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .ptp-reading-panel {
        padding: 1rem;
    }

    .ptp-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.85rem;
        color: var(--ptp-c-dark);
        font-size: 0.84rem;
        font-weight: 900;
        letter-spacing: -0.01em;
    }

    .ptp-section-title span {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .ptp-rank-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: center;
        padding: 0.72rem 0;
        border-top: 1px solid #edf2f7;
    }

    .ptp-rank-row:first-of-type {
        border-top: none;
        padding-top: 0;
    }

    .ptp-rank-name {
        color: #1e293b;
        font-size: 0.8rem;
        font-weight: 850;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ptp-rank-meta {
        margin-top: 0.18rem;
        color: #64748b;
        font-size: 0.69rem;
        font-weight: 700;
    }

    .ptp-rank-value {
        color: #0f172a;
        font-size: 0.8rem;
        font-weight: 900;
        text-align: right;
        white-space: nowrap;
    }

    .ptp-meter {
        height: 0.42rem;
        margin-top: 0.48rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e5edf6;
    }

    .ptp-meter-fill {
        height: 100%;
        width: var(--bar-width, 0%);
        border-radius: inherit;
        background: linear-gradient(90deg, var(--accent, var(--ptp-c-blue)), #7fb7f2);
    }

    .ptp-split-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .ptp-list-title {
        margin-bottom: 0.55rem;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    /* Clean Excel-Style Table Wrapper matching .dana-card */
    /* Clean Excel-Style Table Wrapper matching .dana-card */
    .ptp-table-wrap {
        width: 100%;
        max-height: calc(100vh - 230px);
        overflow: auto;
        border: 1px solid #b7c3d0;
        border-radius: 0;
        background: #ffffff;
        scrollbar-gutter: stable;
    }

    /* Clean Excel-Style Table Layout */
    .ptp-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        font-size: 0.74rem;
        white-space: nowrap;
        background: #ffffff;
        color: #002060;
    }

    .ptp-table th,
    .ptp-table td {
        border: 1px solid #d9e1ec;
        padding: 0.28rem 0.4rem;
        vertical-align: middle;
        font-family: var(--ptp-font-primary);
    }

    /* Professional Sticky Headers with Solid Colors matching Harian Dashboard */
    .ptp-table th {
        position: sticky;
        text-align: center;
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
        z-index: 4;
        border: 1px solid #ffffff !important;
    }

    .ptp-table thead tr:nth-child(1) th {
        top: 0;
        height: 28px;
    }
    .ptp-table thead tr:nth-child(2) th {
        top: 28px;
        height: 26px;
    }
    .ptp-table thead tr:nth-child(3) th {
        top: 54px;
        height: 26px;
    }
    .ptp-table thead th[rowspan="3"] {
        top: 0 !important;
        height: 80px !important;
        z-index: 5 !important;
    }

    .ptp-table td {
        background: #ffffff;
        color: #002060;
        font-variant-numeric: tabular-nums;
        font-weight: 500;
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
    }

    .ptp-table tbody tr:nth-child(even) td {
        background: #f5f7fa;
    }

    .ptp-table tbody tr:hover td {
        background: #eaf5ff;
    }

    .ptp-table tbody tr.ptp-drill-row {
        cursor: default;
    }

    .ptp-table tbody td.ptp-drill-cell {
        cursor: zoom-in;
    }

    /* Drill Down Row Selection Style */
    .ptp-table tbody tr.ptp-drill-row.is-selected td {
        background: #dbeafe !important;
        font-weight: 600;
    }

    .ptp-table tbody td.ptp-drill-cell.is-selected {
        background: #bfdbfe !important;
        outline: 2px solid var(--ptp-c-blue);
        outline-offset: -2px;
    }

    /* Solid professional colors matching daily harian table */
    .ptp-head-blue {
        background-color: #0070c0 !important;
        background: #0070c0 !important;
    }

    .ptp-head-blue-sub {
        background-color: #005b9f !important;
        background: #005b9f !important;
    }

    .ptp-head-orange {
        background-color: #d97706 !important;
        background: #d97706 !important;
    }

    .ptp-head-orange-sub {
        background-color: #b45309 !important;
        background: #b45309 !important;
    }

    .ptp-head-yellow {
        background-color: #ca8a04 !important;
        background: #ca8a04 !important;
        color: #ffffff !important;
    }

    .ptp-head-success {
        background-color: #0f766e !important;
        background: #0f766e !important;
    }

    .ptp-left {
        text-align: left;
    }

    .ptp-right {
        text-align: right;
    }

    .ptp-center {
        text-align: center;
    }

    /* Excel Summary Row Styling matching daily harian total style */
    .ptp-total-row td {
        background-color: #f2f2f2 !important;
        color: #002060 !important;
        font-weight: 800 !important;
        border-top: 2px solid #8fa3b8 !important;
        border-bottom: 2px double #8fa3b8 !important;
    }

    /* Clean Solid Conditional Formatting (Plain Text, Muted Solid Colors, No Pill Shapes) */
    .ptp-success-rate-cell {
        text-align: center !important;
        font-weight: 700 !important;
    }

    .ptp-empty {
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--ptp-c-muted);
        font-weight: 600;
    }

    /* Drill down modal premium styles */
    .ptp-drill-modal .modal-content {
        border: none;
        border-radius: var(--ptp-r-xl);
        box-shadow: var(--ptp-shadow-premium);
        overflow: hidden;
    }

    .ptp-drill-modal .modal-header {
        border-bottom: 1px solid var(--ptp-c-border);
        background: var(--ptp-c-bg-light);
        padding: 1.25rem 1.5rem;
    }

    .ptp-drill-modal .modal-body {
        background: #ffffff;
        padding: 1.5rem;
    }

    .ptp-drill-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .ptp-drill-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        color: var(--ptp-c-dark);
        font-size: 0.76rem;
        font-weight: 700;
    }

    .ptp-drill-meta span {
        padding: 0.25rem 0.6rem;
        border: 1px solid var(--ptp-c-border);
        border-radius: 6px;
        background: var(--ptp-c-bg-light);
        box-shadow: var(--ptp-shadow-sm);
    }

    .ptp-drill-state {
        padding: 2.5rem;
        border: 2px dashed var(--ptp-c-border);
        border-radius: var(--ptp-r-lg);
        color: var(--ptp-c-muted);
        text-align: center;
        font-weight: 600;
        font-size: 0.88rem;
    }

    .ptp-drill-table-wrap {
        max-height: 60vh;
        overflow: auto;
        border: 1px solid #b7c3d0;
        border-radius: 0;
        background: #ffffff;
        scrollbar-gutter: stable;
    }

    .ptp-drill-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: collapse;
        font-size: 0.74rem;
        white-space: nowrap;
        background: #ffffff;
        color: #002060;
    }

    .ptp-drill-table th,
    .ptp-drill-table td {
        border: 1px solid #d9e1ec;
        padding: 0.28rem 0.4rem;
        vertical-align: middle;
    }

    .ptp-drill-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        background-color: #005b9f !important;
        background: #005b9f !important;
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        border: 1px solid #ffffff !important;
    }

    .ptp-drill-table td {
        background: #ffffff;
        color: #002060;
        font-variant-numeric: tabular-nums;
        font-weight: 500;
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
    }

    .ptp-drill-table tbody tr:nth-child(even) td {
        background: #f5f7fa;
    }

    .ptp-drill-table tbody tr:hover td {
        background: #eaf5ff;
    }

    .ptp-drill-footer-note {
        margin-top: 0.75rem;
        color: var(--ptp-c-muted);
        font-size: 0.76rem;
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .ptp-page {
            padding: 1rem 0.75rem;
        }

        .ptp-title {
            font-size: 1.1rem;
        }

        .ptp-insight-grid,
        .ptp-reading-grid,
        .ptp-split-list {
            grid-template-columns: 1fr;
        }
    }

    /* Premium PDF Export button */
    .btn-export-pdf {
        min-height: 40px;
        border-radius: var(--ptp-r-md);
        border: 1.5px solid var(--ptp-c-border);
        background: #ffffff;
        color: var(--ptp-c-dark);
        font-weight: 800;
        letter-spacing: 0.025em;
        font-size: 0.76rem;
        padding: 0.5rem 1.25rem !important;
        box-shadow: var(--ptp-shadow-sm);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-export-pdf:hover {
        background: #fef2f2;
        border-color: #f87171;
        color: #ef4444;
        transform: translateY(-1px);
        box-shadow: var(--ptp-shadow-md);
    }

    /* Capture Status Modal Premium Styles */
    .capture-status-modal .modal-content {
        border-radius: var(--ptp-r-xl);
        border: none;
        box-shadow: var(--ptp-shadow-premium);
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

    .icon-loading { background: rgba(8, 87, 195, 0.1); color: var(--ptp-c-blue); }
    .icon-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

    .capture-status-modal .btn-primary {
        border-radius: var(--ptp-r-md);
        padding: 0.6rem 1.5rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
</style>

@php
    $dimensionHeaders = match ($selectedLevel) {
        'per_uker' => ['bo' => 'BO', 'bc' => 'BC', 'mbm' => 'MBM', 'uker' => 'UKER'],
        'per_mantri' => ['bo' => 'BO', 'mbm' => 'MBM', 'bc' => 'BC', 'uker' => 'UKER', 'mantri' => 'MANTRI'],
        default => ['bo' => 'BO', 'mbm' => 'MBM'],
    };

    // Excel Style Solid Conditional Formatting Helper
    $successRateStyle = static function ($value): string {
        $rate = (float) $value;
        if ($rate < 50.0) {
            // Soft red background, deep red text
            return 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: 700;';
        } elseif ($rate <= 60.0) {
            // Soft yellow/amber background, deep amber text
            return 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: 700;';
        } else {
            // Soft green background, deep green text
            return 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: 700;';
        }
    };

    $rowsCollection = collect($rows);
    $rowLabel = static function (array $row) use ($dimensionHeaders): string {
        foreach (array_reverse(array_keys($dimensionHeaders)) as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return '-';
    };
    $rowMeta = static function (array $row): string {
        $parts = array_filter([
            trim((string) ($row['bo'] ?? '')),
            trim((string) ($row['uker'] ?? '')),
            trim((string) ($row['mbm'] ?? '')),
        ], static fn (string $value): bool => $value !== '' && $value !== '-');

        return implode(' | ', array_slice(array_unique($parts), 0, 2)) ?: '-';
    };
    $barWidth = static fn (mixed $value): string => number_format(max(0, min(100, (float) $value)), 2, '.', '') . '%';
    $totalRek = (float) ($total['total_rek'] ?? 0);
    $sudahBillingRek = (float) ($total['sudah_billing_rek'] ?? 0);
    $belumMunculRek = (float) ($total['belum_muncul_rek'] ?? 0);
    $belumBayarRek = (float) ($total['belum_bayar_rek'] ?? 0);
    $billingCoverageRate = $totalRek > 0 ? ($sudahBillingRek / $totalRek) * 100 : 0.0;
    $belumMunculRate = $totalRek > 0 ? ($belumMunculRek / $totalRek) * 100 : 0.0;
    $belumBayarRate = $sudahBillingRek > 0 ? ($belumBayarRek / $sudahBillingRek) * 100 : 0.0;
    $successRate = (float) ($total['success_rate'] ?? 0);
    $topSuccessRows = $rowsCollection
        ->sortByDesc(fn (array $row): float => (float) ($row['success_rate'] ?? 0))
        ->take(3)
        ->values();
    $riskRows = $rowsCollection
        ->sortBy(fn (array $row): float => (float) ($row['success_rate'] ?? 0))
        ->take(3)
        ->values();
    $focusRows = $rowsCollection
        ->sortByDesc(fn (array $row): float => (float) ($row['belum_bayar_rupiah'] ?? 0))
        ->take(4)
        ->values();
@endphp

<div class="ptp-page">
    <!-- Premium Header -->
    <div class="ptp-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="ptp-brand d-flex align-items-center gap-3">
            <div class="ptp-logo">
                <i class="fas fa-chart-line text-white"></i>
            </div>
            <div>
                <h1 class="ptp-title">Kinerja PTP</h1>
                <div class="ptp-subtitle">
                    <span class="ptp-subtitle-badge">{{ $reportConfig['label'] }}</span>
                    <span>{{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }}</span>
                    <span class="mx-1 opacity-50">|</span>
                    <span class="font-semibold text-slate-700">Posisi {{ $selectedPeriodLabel }}</span>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button id="exportPdfBtn" class="btn btn-export-pdf">
                <i class="fas fa-file-pdf text-danger"></i>EXPORT PDF
            </button>
        </div>
    </div>


    <!-- Premium Filters Card -->
    <div class="ptp-panel mb-3">
        <div class="ptp-panel-body">
            <form method="GET" action="{{ route('report.dashboard-pinjaman.kinerja-ptp') }}">
                <div class="row align-items-end">
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="jenis">Jenis PTP</label>
                        <select id="jenis" name="jenis" class="form-control ptp-filter-control">
                            @foreach ($reportTypes as $key => $label)
                                <option value="{{ $key }}" @selected($key === $selectedReportType)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="level">Level Kinerja</label>
                        <select id="level" name="level" class="form-control ptp-filter-control">
                            @foreach ($levels as $key => $label)
                                <option value="{{ $key }}" @selected($key === $selectedLevel)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 mb-md-0">
                        <label class="ptp-filter-label" for="periode">Periode</label>
                        <select id="periode" name="periode" class="form-control ptp-filter-control">
                            @forelse ($availablePeriods as $period)
                                <option value="{{ $period }}" @selected($period === $selectedPeriod)>{{ \Carbon\Carbon::parse($period)->locale('id')->translatedFormat('d F Y') }}</option>
                            @empty
                                <option value="">Tidak ada data</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <button type="submit" class="btn btn-primary ptp-action w-100">
                            <i class="fas fa-filter mr-2"></i>Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($rows->isNotEmpty())
        <div class="ptp-insight-grid">
            <div class="ptp-insight-card" style="--accent: #0857c3;">
                <div class="ptp-insight-label"><i class="fas fa-layer-group"></i>Total Kelolaan</div>
                <div class="ptp-insight-value">{{ $formatCount($total['total_rek'] ?? 0) }} rek</div>
                <div class="ptp-insight-note">Rp {{ $formatJuta($total['total_rupiah'] ?? 0) }} Jt posisi sumber</div>
                <div class="ptp-meter" style="--accent: #0857c3;">
                    <div class="ptp-meter-fill" style="--bar-width: 100%;"></div>
                </div>
            </div>
            <div class="ptp-insight-card" style="--accent: #0f766e;">
                <div class="ptp-insight-label"><i class="fas fa-check-circle"></i>Sudah Billing</div>
                <div class="ptp-insight-value">{{ $formatPercent($billingCoverageRate) }}</div>
                <div class="ptp-insight-note">{{ $formatCount($total['sudah_billing_rek'] ?? 0) }} rek | Rp {{ $formatJuta($total['sudah_billing_rupiah'] ?? 0) }} Jt</div>
                <div class="ptp-meter" style="--accent: #0f766e;">
                    <div class="ptp-meter-fill" style="--bar-width: {{ $barWidth($billingCoverageRate) }};"></div>
                </div>
            </div>
            <div class="ptp-insight-card" style="--accent: #d97706;">
                <div class="ptp-insight-label"><i class="fas fa-hourglass-half"></i>Belum Muncul</div>
                <div class="ptp-insight-value">{{ $formatPercent($belumMunculRate) }}</div>
                <div class="ptp-insight-note">{{ $formatCount($total['belum_muncul_rek'] ?? 0) }} rek | Rp {{ $formatJuta($total['belum_muncul_rupiah'] ?? 0) }} Jt</div>
                <div class="ptp-meter" style="--accent: #d97706;">
                    <div class="ptp-meter-fill" style="--bar-width: {{ $barWidth($belumMunculRate) }};"></div>
                </div>
            </div>
            <div class="ptp-insight-card" style="--accent: #dc2626;">
                <div class="ptp-insight-label"><i class="fas fa-bullseye"></i>Success Rate</div>
                <div class="ptp-insight-value">{{ $formatPercent($successRate) }}</div>
                <div class="ptp-insight-note">Belum bayar {{ $formatPercent($belumBayarRate) }} dari billing</div>
                <div class="ptp-meter" style="--accent: #dc2626;">
                    <div class="ptp-meter-fill" style="--bar-width: {{ $barWidth($successRate) }};"></div>
                </div>
            </div>
        </div>

        <div class="ptp-reading-grid">
            <div class="ptp-reading-panel">
                <div class="ptp-section-title">
                    <span><i class="fas fa-exclamation-triangle text-warning"></i> Fokus Belum Bayar Terbesar</span>
                    <small class="text-muted font-weight-bold">Rp Juta</small>
                </div>
                @foreach ($focusRows as $row)
                    @php
                        $rowBillingRupiah = (float) ($row['sudah_billing_rupiah'] ?? 0);
                        $rowUnpaidRupiah = (float) ($row['belum_bayar_rupiah'] ?? 0);
                        $rowUnpaidRate = $rowBillingRupiah > 0 ? ($rowUnpaidRupiah / $rowBillingRupiah) * 100 : 0.0;
                    @endphp
                    <div class="ptp-rank-row">
                        <div>
                            <div class="ptp-rank-name">{{ $rowLabel($row) }}</div>
                            <div class="ptp-rank-meta">{{ $rowMeta($row) }} | {{ $formatCount($row['belum_bayar_rek'] ?? 0) }} rek belum bayar</div>
                            <div class="ptp-meter" style="--accent: #dc2626;">
                                <div class="ptp-meter-fill" style="--bar-width: {{ $barWidth($rowUnpaidRate) }};"></div>
                            </div>
                        </div>
                        <div class="ptp-rank-value">{{ $formatJuta($row['belum_bayar_rupiah'] ?? 0) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="ptp-reading-panel">
                <div class="ptp-section-title">
                    <span><i class="fas fa-tachometer-alt text-primary"></i> Pembacaan Cepat</span>
                    <small class="text-muted font-weight-bold">{{ $formatCount($rows->count()) }} baris</small>
                </div>
                <div class="ptp-split-list">
                    <div>
                        <div class="ptp-list-title">Tertinggi</div>
                        @foreach ($topSuccessRows as $row)
                            <div class="ptp-rank-row">
                                <div>
                                    <div class="ptp-rank-name">{{ $rowLabel($row) }}</div>
                                    <div class="ptp-rank-meta">{{ $rowMeta($row) }}</div>
                                </div>
                                <div class="ptp-rank-value text-success">{{ $formatPercent($row['success_rate'] ?? 0) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <div class="ptp-list-title">Perlu Dorongan</div>
                        @foreach ($riskRows as $row)
                            <div class="ptp-rank-row">
                                <div>
                                    <div class="ptp-rank-name">{{ $rowLabel($row) }}</div>
                                    <div class="ptp-rank-meta">{{ $rowMeta($row) }}</div>
                                </div>
                                <div class="ptp-rank-value text-danger">{{ $formatPercent($row['success_rate'] ?? 0) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Premium Table Card -->
    <div class="ptp-panel">
        <div class="ptp-panel-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="h6 font-weight-bold mb-0 text-slate-800">{{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }}</div>
                <div class="text-slate-500 small font-semibold">
                    <i class="fas fa-info-circle mr-1"></i> {{ $formatCount($rows->count()) }} baris | Double click angka untuk nominatif
                </div>
            </div>

            @if ($rows->isEmpty())
                <div class="ptp-empty">
                    <i class="fas fa-folder-open d-block mb-2 font-size-lg text-slate-400" style="font-size: 2rem;"></i>
                    Data belum tersedia untuk pilihan ini.
                </div>
            @else
                <div class="ptp-table-wrap" id="ptp-capture-area">
                    <table class="ptp-table">
                        <thead>
                            <tr>
                                @foreach ($dimensionHeaders as $label)
                                    <th rowspan="3" class="ptp-head-blue">{{ $label }}</th>
                                @endforeach
                                <th colspan="9" class="ptp-head-blue">{{ $reportConfig['total_heading'] }}</th>
                                <th colspan="4" class="ptp-head-orange">NPD Billing Sudah Muncul</th>
                                <th rowspan="3" class="ptp-head-success">Success Rate</th>
                                <th colspan="2" class="ptp-head-yellow">Today</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="ptp-head-blue-sub">Total</th>
                                <th colspan="3" class="ptp-head-blue-sub">Sudah Muncul Billing</th>
                                <th colspan="3" class="ptp-head-blue-sub">Belum Muncul</th>
                                <th colspan="2" class="ptp-head-orange-sub">Sudah Bayar</th>
                                <th colspan="2" class="ptp-head-orange-sub">Belum Bayar</th>
                                <th colspan="2" class="ptp-head-yellow">&nbsp;</th>
                            </tr>
                            <tr>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-orange-sub">Rek</th>
                                <th class="ptp-head-orange-sub">Rupiah</th>
                                <th class="ptp-head-orange-sub">Rek</th>
                                <th class="ptp-head-orange-sub">Rupiah</th>
                                <th class="ptp-head-yellow">Rek</th>
                                <th class="ptp-head-yellow">Rupiah</th>
                            </tr>
                        </thead>
                        <tbody id="ptpTableBody">
                            @foreach ($rows as $row)
                                <tr class="ptp-drill-row"
                                    @foreach (array_keys($dimensionHeaders) as $key)
                                        data-ptp-{{ $key }}="{{ e($row[$key] ?? '-') }}"
                                    @endforeach
                                >
                                    @foreach (array_keys($dimensionHeaders) as $key)
                                        <td class="ptp-left">{{ $row[$key] ?? '-' }}</td>
                                    @endforeach
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="total_rek">{{ $formatCount($row['total_rek'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="total_rupiah">{{ $formatJuta($row['total_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="total_runoff">{{ $formatJuta($row['total_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="sudah_billing_rek">{{ $formatCount($row['sudah_billing_rek'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="sudah_billing_rupiah">{{ $formatJuta($row['sudah_billing_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="sudah_billing_runoff">{{ $formatJuta($row['sudah_billing_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="belum_muncul_rek">{{ $formatCount($row['belum_muncul_rek'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="belum_muncul_rupiah">{{ $formatJuta($row['belum_muncul_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="belum_muncul_runoff">{{ $formatJuta($row['belum_muncul_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="sudah_bayar_rek">{{ $formatCount($row['sudah_bayar_rek'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="sudah_bayar_rupiah">{{ $formatJuta($row['sudah_bayar_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="belum_bayar_rek">{{ $formatCount($row['belum_bayar_rek'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="belum_bayar_rupiah">{{ $formatJuta($row['belum_bayar_rupiah'] ?? 0) }}</td>
                                    <!-- Clean Solid Conditional Formatting without inner shapes -->
                                    <td class="ptp-center ptp-success-rate-cell ptp-drill-cell" data-ptp-metric="success_rate" style="{{ $successRateStyle($row['success_rate'] ?? 0) }}">
                                        {{ $formatPercent($row['success_rate'] ?? 0) }}
                                    </td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="today_rek">{{ $formatCount($row['today_rek'] ?? 0) }}</td>
                                    <td class="ptp-right ptp-drill-cell" data-ptp-metric="today_rupiah">{{ $formatJuta($row['today_rupiah'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            <tr class="ptp-total-row">
                                <td colspan="{{ count($dimensionHeaders) }}" class="ptp-left">TOTAL</td>
                                <td class="ptp-right">{{ $formatCount($total['total_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['total_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['total_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['sudah_billing_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_billing_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_billing_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['belum_muncul_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_muncul_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_muncul_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['sudah_bayar_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_bayar_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['belum_bayar_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_bayar_rupiah'] ?? 0) }}</td>
                                <td class="ptp-center ptp-success-rate-cell" style="{{ $successRateStyle($total['success_rate'] ?? 0) }}">
                                    {{ $formatPercent($total['success_rate'] ?? 0) }}
                                </td>
                                <td class="ptp-right">{{ $formatCount($total['today_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['today_rupiah'] ?? 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Nominatif Modal -->
    <div class="modal fade ptp-drill-modal" id="ptpDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold mb-1" style="font-family: var(--ptp-font-display);">Nominatif Kinerja PTP</h5>
                        <div id="ptpDrillSubtitle" class="text-muted small font-semibold">-</div>
                    </div>
                    <button type="button" class="close text-slate-400" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true" style="font-size: 1.5rem;">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="ptp-drill-toolbar">
                        <div id="ptpDrillMeta" class="ptp-drill-meta"></div>
                        <button id="ptpDrillLoadMoreButton" type="button" class="btn btn-sm btn-outline-primary d-none font-bold">
                            <i class="fas fa-plus mr-1"></i> Muat Lagi
                        </button>
                    </div>
                    <div id="ptpDrillState" class="ptp-drill-state">Double click baris untuk melihat nominatif sumber.</div>
                    <div id="ptpDrillTableWrap" class="ptp-drill-table-wrap d-none">
                        <table class="ptp-drill-table">
                            <thead id="ptpDrillHead"></thead>
                            <tbody id="ptpDrillBody"></tbody>
                        </table>
                    </div>
                    <div id="ptpDrillFooterNote" class="ptp-drill-footer-note d-none"></div>
                </div>
            </div>
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
                        <h4 class="font-weight-bold mb-2" style="font-family: var(--ptp-font-display);">Menyusun Laporan PDF</h4>
                        <p class="text-muted small mb-0 font-medium">Sedang mengolah tabel kinerja PTP ke dalam format PDF A4 Landscape. Mohon tunggu sebentar...</p>
                    </div>

                    <!-- Error State -->
                    <div id="captureErrorUI" class="d-none">
                        <div class="capture-status-modal-icon icon-error">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2" style="font-family: var(--ptp-font-display);">Gagal Ekspor PDF</h4>
                        <p id="captureErrorMessage" class="text-muted mb-4 small font-semibold">Terjadi kendala saat menyusun file PDF.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Tutup & Coba Lagi
                        </button>
                    </div>

                    <!-- Success State -->
                    <div id="captureSuccessUI" class="d-none">
                        <div class="capture-status-modal-icon icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2" style="font-family: var(--ptp-font-display);">Ekspor Berhasil!</h4>
                        <p class="text-muted mb-4 small font-semibold">Laporan PDF Kinerja PTP telah berhasil diunduh ke perangkat Anda.</p>
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
<script src="{{ asset('vendor/html2pdf.bundle.min.js') }}"></script>
<script>
    $(function() {
        const exportBtn = document.getElementById('exportPdfBtn');
        const captureModal = document.getElementById('captureStatusModal');
        const progressUI = document.getElementById('captureProgressUI');
        const errorUI = document.getElementById('captureErrorUI');
        const successUI = document.getElementById('captureSuccessUI');
        const errorMessageUI = document.getElementById('captureErrorMessage');
        const captureArea = document.getElementById('ptp-capture-area');
        const detailUrl = @json(route('report.dashboard-pinjaman.kinerja-ptp.detail'));
        const dimensionKeys = @json(array_keys($dimensionHeaders));
        const tableBody = document.getElementById('ptpTableBody');
        const detailModal = document.getElementById('ptpDetailModal');
        const drillSubtitle = document.getElementById('ptpDrillSubtitle');
        const drillMeta = document.getElementById('ptpDrillMeta');
        const drillState = document.getElementById('ptpDrillState');
        const drillTableWrap = document.getElementById('ptpDrillTableWrap');
        const drillHead = document.getElementById('ptpDrillHead');
        const drillBody = document.getElementById('ptpDrillBody');
        const drillLoadMoreButton = document.getElementById('ptpDrillLoadMoreButton');
        const drillFooterNote = document.getElementById('ptpDrillFooterNote');
        let activeDrillController = null;
        let activeDrillRequestId = 0;
        let activeDrillParams = null;
        let activeDrillRenderedCount = 0;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function formatPeriodDate(value) {
            if (!value) return '-';
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) return value;

            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: '2-digit',
            });
        }

        function buildDetailParams(row, metricCell, offset = 0) {
            const params = new URLSearchParams();
            params.set('jenis', document.getElementById('jenis')?.value || @json($selectedReportType));
            params.set('level', document.getElementById('level')?.value || @json($selectedLevel));
            params.set('periode', document.getElementById('periode')?.value || @json($selectedPeriod));
            params.set('metric', metricCell?.dataset.ptpMetric || 'total_rek');
            params.set('offset', offset);
            params.set('limit', 0);

            dimensionKeys.forEach(key => {
                params.set(key, row.dataset[`ptp${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '-');
            });

            return params;
        }

        function showDetailModal() {
            if (!detailModal) return;

            if (detailModal.parentElement !== document.body) {
                document.body.appendChild(detailModal);
            }

            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery(detailModal).modal({
                    backdrop: true,
                    keyboard: true,
                    show: true,
                });
                return;
            }

            detailModal.classList.add('show');
            detailModal.style.display = 'block';
            detailModal.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        function setSelectedTarget(row, metricCell) {
            tableBody?.querySelectorAll('tr.ptp-drill-row').forEach(item => item.classList.remove('is-selected'));
            tableBody?.querySelectorAll('td.ptp-drill-cell').forEach(item => item.classList.remove('is-selected'));
            row?.classList.add('is-selected');
            metricCell?.classList.add('is-selected');
        }

        async function openDetail(row, metricCell, offset = 0, append = false) {
            if (!row || !detailModal) return;
            if (activeDrillController) activeDrillController.abort();
            activeDrillController = new AbortController();
            const requestId = ++activeDrillRequestId;
            const params = append && activeDrillParams ? new URLSearchParams(activeDrillParams) : buildDetailParams(row, metricCell, offset);
            params.set('offset', offset);
            activeDrillParams = new URLSearchParams(params);
            setSelectedTarget(row, metricCell);

            if (!append) {
                const label = dimensionKeys
                    .map(key => row.dataset[`ptp${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '-')
                    .join(' | ');
                drillSubtitle.textContent = label;
                drillMeta.innerHTML = '';
                drillHead.innerHTML = '';
                drillBody.innerHTML = '';
                activeDrillRenderedCount = 0;
                drillTableWrap.classList.add('d-none');
                drillFooterNote.classList.add('d-none');
                drillLoadMoreButton.classList.add('d-none');
                drillState.classList.remove('d-none');
                drillState.textContent = 'Memuat nominatif sumber...';
                showDetailModal();
            }

            drillLoadMoreButton.disabled = true;

            try {
                const response = await fetch(`${detailUrl}?${params.toString()}`, { signal: activeDrillController.signal });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                if (requestId !== activeDrillRequestId) return;

                renderDetailRows(payload, append);
                drillLoadMoreButton.classList.toggle('d-none', !payload.has_more);
                drillLoadMoreButton.dataset.nextOffset = payload.next_offset ?? '';
            } catch (error) {
                if (error.name !== 'AbortError') {
                    drillTableWrap.classList.add('d-none');
                    drillLoadMoreButton.classList.add('d-none');
                    drillState.classList.remove('d-none');
                    drillState.textContent = 'Nominatif gagal dimuat dari sumber.';
                }
            } finally {
                drillLoadMoreButton.disabled = false;
            }
        }

        function renderDetailRows(payload, append) {
            const columns = payload.columns || [];
            const rows = payload.rows || [];
            const dimensionText = Object.values(payload.dimensions || {}).filter(Boolean).join(' | ');

            drillMeta.innerHTML = `
                <span>Posisi: ${escapeHtml(formatPeriodDate(payload.selected_period))}</span>
                <span>Metrik: ${escapeHtml(payload.metric_label || payload.metric || '-')}</span>
                <span>Filter: ${escapeHtml(dimensionText || '-')}</span>
                <span>Ditampilkan: ${activeDrillRenderedCount + rows.length}</span>
            `;

            if (!append) {
                drillHead.innerHTML = `<tr>${columns.map(column => `<th>${escapeHtml(column)}</th>`).join('')}</tr>`;
                drillBody.innerHTML = '';
            }

            if (!rows.length && !append) {
                drillTableWrap.classList.add('d-none');
                drillState.classList.remove('d-none');
                drillState.textContent = 'Tidak ada nominatif untuk baris ini.';
                return;
            }

            const fragment = document.createDocumentFragment();
            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = columns.map(column => `<td>${escapeHtml(row[column] ?? '')}</td>`).join('');
                fragment.appendChild(tr);
            });

            drillBody.appendChild(fragment);
            activeDrillRenderedCount += rows.length;
            const displayedMeta = drillMeta.querySelector('span:last-child');
            if (displayedMeta) {
                displayedMeta.textContent = `Ditampilkan: ${activeDrillRenderedCount}`;
            }
            drillState.classList.add('d-none');
            drillTableWrap.classList.remove('d-none');
            drillFooterNote.classList.remove('d-none');
            drillFooterNote.textContent = 'Nominatif dimuat langsung dari tabel sumber untuk metrik yang dipilih.';
        }

        if (tableBody && detailModal) {
            tableBody.addEventListener('dblclick', event => {
                const row = event.target.closest('tr.ptp-drill-row');
                const metricCell = event.target.closest('td.ptp-drill-cell');
                if (!row || !metricCell) return;
                openDetail(row, metricCell);
            });

            drillLoadMoreButton?.addEventListener('click', () => {
                const nextOffset = Number.parseInt(drillLoadMoreButton.dataset.nextOffset || '', 10);
                const selectedRow = tableBody.querySelector('tr.ptp-drill-row.is-selected');
                const selectedCell = tableBody.querySelector('td.ptp-drill-cell.is-selected');
                if (selectedRow && Number.isFinite(nextOffset)) {
                    openDetail(selectedRow, selectedCell, nextOffset, true);
                }
            });
        }

        if (!exportBtn || !captureArea) return;

        exportBtn.addEventListener('click', async function() {
            if (window.jQuery) {
                window.jQuery(captureModal).modal('show');
            }
            
            progressUI.classList.remove('d-none');
            errorUI.classList.add('d-none');
            successUI.classList.add('d-none');

            if (typeof html2pdf === 'undefined') {
                alert('Library html2pdf belum dimuat. Mohon tunggu sebentar atau muat ulang halaman.');
                return;
            }

            const originalBtnHtml = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> EXPORTING...';

            try {
                const originalTable = captureArea.querySelector('table');
                if (!originalTable) throw new Error('Tabel data tidak ditemukan.');

                const tableRealWidth = originalTable.scrollWidth || 1600;

                const tempWrap = document.createElement('div');
                tempWrap.id = 'pdf-isolation-wrap';
                tempWrap.style.cssText = `position: absolute; left: 0; top: 0; width: ${tableRealWidth + 40}px; background: #ffffff; padding: 20px; z-index: 1030;`;

                const tempStyle = document.createElement('style');
                tempStyle.textContent = `
                    #pdf-isolation-wrap { font-family: "Inter", "Plus Jakarta Sans", sans-serif; background: #ffffff; }
                    .pdf-header { margin-bottom: 20px; border-bottom: 3px solid #0857c3; padding-bottom: 15px; width: 100%; background: #ffffff; }
                    .pdf-title { font-size: 24px; font-weight: bold; color: #0f172a; margin: 0 0 5px 0; }
                    .pdf-subtitle { font-size: 14px; color: #64748b; }
                    .ptp-table-clone { width: ${tableRealWidth}px; border-collapse: collapse; margin: 0; font-size: 12px; white-space: nowrap; background: #ffffff; }
                    .ptp-table-clone th, .ptp-table-clone td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: middle; }
                    .ptp-table-clone th { text-align: center; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; }
                    .ptp-table-clone td { background: #ffffff; color: #111827; }
                    .ptp-table-clone tbody tr:nth-child(even) td { background: #f8fafc; }
                    .ptp-head-blue { background-color: #0857c3 !important; color: #ffffff !important; }
                    .ptp-head-blue-sub { background-color: #053b82 !important; color: #ffffff !important; }
                    .ptp-head-orange { background-color: #d97706 !important; color: #ffffff !important; }
                    .ptp-head-orange-sub { background-color: #b45309 !important; color: #ffffff !important; }
                    .ptp-head-yellow { background-color: #ca8a04 !important; color: #ffffff !important; }
                    .ptp-head-success { background-color: #0f766e !important; color: #ffffff !important; }
                    .ptp-left { text-align: left !important; }
                    .ptp-right { text-align: right !important; }
                    .ptp-center { text-align: center !important; }
                    .ptp-total-row td { background-color: #fef08a !important; font-weight: bold !important; }
                `;
                tempWrap.appendChild(tempStyle);

                let rawTableHtml = originalTable.innerHTML;
                rawTableHtml = rawTableHtml.replace(/sticky-col/g, '');

                const contentHtml = `
                    <div class="pdf-header">
                        <h2 class="pdf-title">LAPORAN KINERJA PTP</h2>
                        <div class="pdf-subtitle">{{ $reportConfig['label'] }} | {{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }} - Posisi {{ $selectedPeriodLabel }}</div>
                    </div>
                    <table class="ptp-table-clone">
                        ${rawTableHtml}
                    </table>
                `;
                
                const contentContainer = document.createElement('div');
                contentContainer.innerHTML = contentHtml;
                tempWrap.appendChild(contentContainer);
                document.body.appendChild(tempWrap);

                await new Promise(r => setTimeout(r, 800));

                const opt = {
                    margin:       [10, 10, 10, 10],
                    filename:     `Kinerja-PTP-{{ \Illuminate\Support\Str::slug($reportConfig['label']) }}-${new Date().toISOString().slice(0, 10)}.pdf`,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { 
                        scale: 1.5, 
                        useCORS: true, 
                        logging: false,
                        backgroundColor: '#ffffff',
                        windowWidth: tableRealWidth + 100,
                        x: 0,
                        y: 0
                    },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' },
                    pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
                };

                await html2pdf().set(opt).from(tempWrap).save();

                document.body.removeChild(tempWrap);

                progressUI.classList.add('d-none');
                successUI.classList.remove('d-none');
                
                if (window.jQuery) {
                    setTimeout(() => {
                        window.jQuery(captureModal).modal('hide');
                    }, 2000);
                }
            } catch (err) {
                console.error('PDF Export failed:', err);
                
                const existingWrap = document.getElementById('pdf-isolation-wrap');
                if (existingWrap) document.body.removeChild(existingWrap);

                progressUI.classList.add('d-none');
                errorUI.classList.remove('d-none');
                errorMessageUI.textContent = 'Gagal menyusun laporan PDF. Error: ' + err.message;
            } finally {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalBtnHtml;
            }
        });
    });
</script>
@endsection
