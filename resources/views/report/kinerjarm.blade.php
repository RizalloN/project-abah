@extends('layouts.admin')

@section('title', 'Kinerja RM')

@section('content')
<style>
    /* LUXURY REDESIGN SYSTEM */
    :root {
        --lux-primary: #0f172a;
        --lux-accent: #2563eb;
        --lux-accent-soft: rgba(37, 99, 235, 0.08);
        --lux-border: #e2e8f0;
        --lux-bg-soft: #f8fafc;
        --lux-text-main: #1e293b;
        --lux-text-muted: #64748b;
        --lux-radius: 16px;
    }

    .kinerja-konsumer-shell {
        background: #f8fafc !important;
        border: none !important;
        box-shadow: none !important;
    }

    /* Refined Luxury Header */
    .lux-header-section {
        padding: 3rem 0 2rem;
        border-bottom: 1px solid var(--lux-border);
        margin-bottom: 2.5rem;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 2rem;
    }

    .lux-header-info h1 {
        font-size: 2.5rem;
        font-weight: 950;
        color: var(--lux-primary);
        letter-spacing: -0.05em;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .lux-header-info h1 i {
        background: var(--lux-accent-soft);
        color: var(--lux-accent);
        padding: 0.8rem;
        border-radius: 20px;
        font-size: 1.5rem;
    }

    .lux-header-subtitle {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--lux-text-muted);
        max-width: 600px;
    }

    /* Luxury Filter Grid */
    .lux-filter-card {
        background: white;
        border-radius: 28px;
        padding: 2rem;
        border: 1px solid var(--lux-border);
        box-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.05);
        margin-bottom: 3rem;
    }

    .lux-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr) auto;
        gap: 1.75rem;
        align-items: flex-end;
    }

    .lux-field {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .lux-label {
        font-size: 0.72rem;
        font-weight: 900;
        color: var(--lux-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.12em;
        padding-left: 0.4rem;
    }

    .lux-select-wrap {
        position: relative;
    }

    .lux-select {
        width: 100%;
        height: 56px;
        background: #f1f5f9;
        border: 1.5px solid transparent;
        border-radius: 18px;
        padding: 0 1.5rem;
        font-size: 1rem;
        font-weight: 700;
        color: var(--lux-primary);
        appearance: none;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .lux-select:hover {
        background: #e2e8f0;
    }

    .lux-select:focus {
        background: white;
        border-color: var(--lux-accent);
        box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.08);
        outline: none;
    }

    .lux-select-wrap i {
        position: absolute;
        right: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--lux-text-muted);
        pointer-events: none;
    }

    /* Premium Action Buttons */
    .lux-actions {
        display: flex;
        gap: 1rem;
    }

    .lux-btn {
        height: 56px;
        border-radius: 18px;
        font-size: 0.9rem;
        font-weight: 850;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        padding: 0 2rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        white-space: nowrap;
    }

    .lux-btn-primary {
        background: var(--lux-primary);
        color: white;
        box-shadow: 0 10px 25px -8px rgba(15, 23, 42, 0.35);
    }

    .lux-btn-primary:hover {
        background: #000000;
        transform: translateY(-2px);
        box-shadow: 0 15px 30px -8px rgba(15, 23, 42, 0.45);
    }

    .lux-btn-capture {
        background: white;
        color: var(--lux-primary);
        border: 2px solid var(--lux-border);
    }

    .lux-btn-capture:hover {
        border-color: var(--lux-primary);
        transform: translateY(-2px);
        background: #f8fafc;
    }

    /* Deprecating old layout elements */
    .kinerja-konsumer-header, .kinerja-konsumer-hero, .kinerja-konsumer-filters {
        display: none !important;
    }
    }

    .kinerja-konsumer-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-color: var(--loan-blue);
    }

    .kinerja-konsumer-filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
        margin-top: 1.5rem;
        padding: 1.5rem;
        border-radius: 16px;
        background: rgba(248, 250, 252, 0.8);
        border: 1px solid var(--loan-border);
        backdrop-filter: blur(8px);
    }

    .kinerja-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }

    .kinerja-filter-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding-left: 0.2rem;
    }

    .kinerja-filter-control {
        width: 100%;
        border: 1px solid var(--loan-border);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        background: #ffffff;
        color: var(--loan-text);
        font-size: 0.9rem;
        font-weight: 700;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
    }

    .kinerja-filter-control:hover {
        border-color: var(--loan-border-strong);
        background-color: #fcfdfe;
    }

    .kinerja-filter-control:focus {
        border-color: var(--loan-blue);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        outline: none;
    }

    .kinerja-table-shell {
        padding: 0.75rem;
        background: #ffffff;
    }

    .kinerja-tabs-shell {
        border: 1px solid var(--loan-border);
        border-radius: 20px;
        background: #ffffff;
        overflow: hidden;
        box-shadow: 0 10px 22px -16px rgba(15, 23, 42, 0.22);
    }

    .kinerja-tabs-header {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.25rem 1.35rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-bottom: 1px solid var(--loan-border);
        flex-wrap: wrap;
    }

    .kinerja-tabs-heading {
        min-width: min(100%, 380px);
        flex: 1 1 380px;
    }

    .kinerja-tabs-kicker {
        margin: 0 0 0.35rem;
        color: var(--loan-blue);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }

    .kinerja-tabs-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 900;
        color: var(--loan-blue-ink);
        letter-spacing: -0.02em;
    }

    .kinerja-tabs-subtitle {
        margin: 0.35rem 0 0;
        color: var(--loan-muted);
        font-size: 0.82rem;
        font-weight: 600;
        max-width: 58ch;
    }

    .kinerja-tabs-nav {
        display: inline-flex;
        gap: 0.65rem;
        padding: 0.3rem;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid var(--loan-border);
        flex-wrap: wrap;
    }

    .kinerja-tab-btn {
        border: 1px solid transparent;
        border-radius: 12px;
        background: transparent;
        color: #475569;
        padding: 0.75rem 1rem;
        min-width: 180px;
        text-align: left;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .kinerja-tab-btn:hover {
        background: #ffffff;
        border-color: var(--loan-border-strong);
        transform: translateY(-1px);
    }

    .kinerja-tab-btn.is-active,
    .kinerja-tab-btn.active {
        background: var(--loan-blue-ink);
        border-color: var(--loan-blue-ink);
        color: #ffffff;
        box-shadow: 0 10px 20px -16px rgba(15, 23, 42, 0.65);
    }

    .kinerja-tab-btn__label {
        font-size: 0.85rem;
        font-weight: 900;
        letter-spacing: -0.01em;
    }

    .kinerja-tab-btn__meta {
        font-size: 0.7rem;
        font-weight: 700;
        opacity: 0.82;
    }

    .kinerja-tabs-body {
        padding: 1rem;
        background: #ffffff;
    }

    .kinerja-tab-panel {
        display: none;
    }

    .kinerja-tab-panel.is-active {
        display: block;
        animation: panel-fade-in 0.22s ease;
    }

    @keyframes panel-fade-in {
        from {
            opacity: 0;
            transform: translateY(6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .kinerja-report-card__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--loan-border);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .kinerja-report-card__title-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .kinerja-report-card__title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 900;
        color: var(--loan-blue-ink);
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .kinerja-report-card__subtitle {
        margin: 0;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--loan-muted);
    }

    .kinerja-report-card__meta {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.8rem;
        border: 1px solid var(--loan-border);
        border-radius: 999px;
        background: #ffffff;
        color: #334155;
        font-size: 0.72rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .kinerja-report-section-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.95rem 1.1rem;
        border: 1px solid var(--loan-border);
        border-radius: 16px;
        background: linear-gradient(90deg, rgba(37, 99, 235, 0.08), rgba(6, 182, 212, 0.08));
    }

    .kinerja-report-section-label__title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--loan-blue-ink);
    }

    .kinerja-report-section-label__desc {
        margin: 0.2rem 0 0;
        font-size: 0.78rem;
        color: var(--loan-muted);
        font-weight: 600;
    }

    .kinerja-report-section-label__chips {
        display: inline-flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .kinerja-report-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid var(--loan-border);
        color: #475569;
        font-size: 0.7rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .kinerja-table-container {
        position: relative;
        max-height: 70vh;
        overflow: auto;
        border-radius: 12px;
        border: 1px solid var(--loan-border);
    }

    .kinerja-quality-intro {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0 0.25rem 1rem;
        margin-bottom: 0.25rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        flex-wrap: wrap;
    }

    .kinerja-quality-intro__title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--loan-blue-ink);
    }

    .kinerja-quality-intro__desc {
        margin: 0.25rem 0 0;
        font-size: 0.78rem;
        color: var(--loan-muted);
        font-weight: 600;
    }

    .kinerja-quality-intro__chips {
        display: inline-flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .kinerja-quality-stack {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .kinerja-konsumer-table {
        width: 100%;
        min-width: 1400px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .kinerja-konsumer-table--compact {
        min-width: 1180px;
    }

    .kinerja-konsumer-table thead th {
        position: sticky;
        top: 0;
        z-index: 50;
        background: var(--loan-blue-ink) !important;
        backdrop-filter: blur(8px);
        color: #ffffff;
        padding: 0.2rem 0.15rem !important;
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.02rem;
        border-bottom: 2px solid rgba(255, 255, 255, 0.12);
        text-align: center !important;
        vertical-align: middle !important;
        white-space: nowrap;
        height: 28px;
    }

    .kinerja-konsumer-table thead th.sticky-col {
        z-index: 60;
    }

    .kinerja-konsumer-table td.sticky-col {
        position: sticky;
        left: 0;
        background: #ffffff !important;
        z-index: 10;
        border-right: 1px solid #f1f5f9 !important;
    }

    .kinerja-konsumer-table thead tr:nth-child(2) th {
        top: 28px; /* Must match Row 1 height */
        height: 26px;
    }

    .kinerja-konsumer-table th.sub-head {
        background: var(--loan-blue-deep) !important;
        color: rgba(255, 255, 255, 0.9);
    }

    .kinerja-konsumer-table th.accent-head {
        background: var(--loan-blue) !important;
        color: #ffffff;
    }

    .kinerja-konsumer-table td {
        padding: 0.1rem 0.2rem;
        font-size: 0.68rem;
        font-weight: 700;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        text-align: right;
    }

    .kinerja-konsumer-table td.merged-branch-cell {
        background: #ffffff !important;
        border-left: 3px solid var(--loan-blue) !important;
        color: var(--loan-blue-ink) !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        text-align: center !important;
        font-size: 0.6rem !important;
        padding: 0.2rem 0.3rem !important;
        position: sticky !important;
        left: 32px; /* After No column */
        z-index: 20;
        border-right: 1px solid #f1f5f9 !important;
    }

    .kinerja-konsumer-table td.merged-rm-cell {
        background: #ffffff !important;
        color: #475569 !important;
        text-align: left !important;
        font-size: 0.68rem !important;
        font-weight: 800 !important;
        padding: 0.2rem 0.4rem !important;
        position: sticky !important;
        left: 132px; /* 32 + 100 */
        z-index: 20;
        border-right: 1px solid #f1f5f9 !important;
        box-shadow: 5px 0 10px -5px rgba(15, 23, 42, 0.15);
    }

    /* Current Position Highlight Class */
    .highlight-curr {
        background: var(--loan-blue-soft) !important;
        color: var(--loan-blue) !important;
        font-weight: 800 !important;
    }

    .loan-branch-subtotal .highlight-curr {
        background: #38bdf8 !important;
        color: #ffffff !important;
    }

    .loan-branch-subtotal {
        background: var(--loan-blue-ink) !important;
    }

    .loan-branch-subtotal td {
        color: #ffffff !important;
        font-weight: 800 !important;
        border-top: 1px solid #334155 !important;
        border-bottom: 1px solid #334155 !important;
        padding-top: 0.3rem !important;
        padding-bottom: 0.3rem !important;
    }

    .row-grand-total {
        background: var(--loan-blue-ink) !important;
        border-top: 2px solid var(--loan-blue) !important;
        position: sticky;
        bottom: 0;
        z-index: 40;
    }

    .row-grand-total td {
        color: #ffffff !important;
        font-weight: 900 !important;
        font-size: 0.72rem !important;
        border: none !important;
        padding: 0.4rem 0.3rem !important;
    }

    .row-grand-total td.highlight-curr {
        background: transparent !important;
        color: #ffffff !important;
    }

    .row-grand-total .delta-indicator.pos { color: #4ade80 !important; }
    .row-grand-total .delta-indicator.neg { color: #fca5a5 !important; }

    .legend-box {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 1.15rem;
        background: #f8fafc;
        border: 1px solid var(--loan-border);
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
    }

    /* Sophisticated Badges */
    .pct-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 8px;
        font-weight: 800;
        font-size: 0.65rem;
        min-width: 65px;
        text-align: center;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .pct-good { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .pct-mid { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .pct-bad { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    .quadrant-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        font-weight: 900;
        font-size: 0.75rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1.5px solid transparent;
    }
    .quadrant-badge.q1 { background: #dcfce7; color: #15803d; border-color: #10b981; }
    .quadrant-badge.q2 { background: #e0f2fe; color: #0369a1; border-color: #0ea5e9; }
    .quadrant-badge.q3 { background: #fff7ed; color: #c2410c; border-color: #f97316; }
    .quadrant-badge.q4 { background: #fef2f2; color: #b91c1c; border-color: #ef4444; }

    .delta-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 800;
    }
    .delta-indicator.pos { color: #10b981; }
    .delta-indicator.neg { color: #ef4444; }

    .tampilkan-button {
        background: linear-gradient(135deg, var(--loan-blue), #307fe2);
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        height: 48px;
        width: 100%;
    }

    .tampilkan-button:hover {
        background: #1e293b;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.2);
    }

    .tampilkan-button:active {
        transform: translateY(0);
        box-shadow: 0 4px 8px rgba(15, 23, 42, 0.1);
    }

    /* AJAX Loading Styles */
    .kinerja-ajax-wrapper {
        position: relative;
        min-height: 400px;
    }

    .kinerja-loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 100;
    }

    .loading-active .kinerja-loading-overlay {
        display: flex;
    }

    .premium-loader {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.25rem;
    }

    .premium-loader-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid #f1f5f9;
        border-top-color: var(--loan-blue);
        border-radius: 50%;
        animation: premium-spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    }

    @keyframes premium-spin {
        to { transform: rotate(360deg); }
    }

    .premium-loader-text {
        font-weight: 800;
        font-size: 0.85rem;
        color: #1e293b;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    @media (max-width: 768px) {
        .kinerja-tabs-header,
        .kinerja-quality-intro,
        .kinerja-report-card__header {
            align-items: flex-start;
            flex-direction: column;
        }

        .kinerja-konsumer-hero {
            margin: -2.5rem -2.25rem 1.25rem;
            padding: 1.15rem 0.85rem;
        }

        .kinerja-tabs-nav {
            width: 100%;
        }

        .kinerja-tab-btn {
            min-width: 0;
            flex: 1 1 100%;
        }

        .kinerja-report-card__meta,
        .kinerja-quality-intro__chips {
            white-space: normal;
        }

        .kinerja-table-container {
            max-height: 62vh;
        }
    }
</style>

@php
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.');
    $formatSignedAmount = $formatSignedAmount ?? function ($value, bool $showArrow = true, int $decimals = 1) {
        $amount = ((float) $value) / 1000000;
        $cls = $amount > 0 ? 'pos' : ($amount < 0 ? 'neg' : '');
        $icon = '';

        if ($showArrow) {
            if ($amount > 0) {
                $icon = '<i class="fas fa-caret-up me-1"></i>';
            } elseif ($amount < 0) {
                $icon = '<i class="fas fa-caret-down me-1"></i>';
            }
        }

        $prefix = ($amount > 0 && ! $showArrow) ? '+' : '';
        $display = number_format(abs($amount), $decimals, ',', '.');
        if ($amount < 0 && ! $showArrow) {
            $display = '-' . $display;
        }

        return "<span class='delta-indicator $cls'>$icon$prefix$display</span>";
    };
    $formatCount = $formatCount ?? fn ($value) => number_format((int) round((float) $value), 0, ',', '.');
    $formatPercent = $formatPercent ?? fn ($value, int $decimals = 1) => number_format((float) $value, $decimals, ',', '.');
@endphp

<div class="pt-4 px-4">
    <div class="kinerja-konsumer-shell animate-reveal">
        <!-- New Luxury Header Section -->
        <header class="lux-header-section">
            <div class="lux-header-info">
                <h1><i class="fas fa-chart-line"></i> KINERJA RM</h1>
                <p class="lux-header-subtitle">{{ $title }}</p>
            </div>
        </header>
        
        <!-- New Luxury Filter Section -->
        <div class="lux-filter-card">
            <form id="kinerjaFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman.kinerjarm') }}" class="lux-filter-grid">
                <div class="lux-field">
                    <label for="kinerjaSegmen" class="lux-label">Segmen RM</label>
                    <div class="lux-select-wrap">
                        <select id="kinerjaSegmen" name="segmen" class="lux-select" required onchange="this.form.submit();">
                            @foreach($availableSegmens as $segmen)
                                <option value="{{ $segmen }}" @selected($selectedSegmen === $segmen)>
                                    {{ ucfirst(strtolower($segmen)) }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <div class="lux-field">
                    <label for="kinerjaPeriode" class="lux-label">Periode Laporan</label>
                    <div class="lux-select-wrap">
                        <select id="kinerjaPeriode" name="periode" class="lux-select">
                            @foreach($availablePeriods as $period)
                                <option value="{{ $period }}" @selected($selectedPeriod === $period)>
                                    {{ \Carbon\Carbon::parse($period)->translatedFormat('d M Y') }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <div class="lux-field">
                    <label for="kinerjaCabang" class="lux-label">Unit Kerja</label>
                    <div class="lux-select-wrap">
                        <select id="kinerjaCabang" name="cabang1" class="lux-select" @if($selectedSegmen === 'MICRO') required @endif>
                            <option value="" @selected($selectedCabang === null) @if($selectedSegmen === 'MICRO') disabled style="display:none;" @endif>SEMUA CABANG</option>
                            @foreach($availableCabangs as $cabang)
                                <option value="{{ $cabang }}" @selected($selectedCabang === $cabang)>{{ $cabang }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <div class="lux-field">
                    <label for="kinerjaProduk" class="lux-label">Jenis Produk</label>
                    <div class="lux-select-wrap">
                        <select id="kinerjaProduk" name="produk" class="lux-select">
                            <option value="" @selected($selectedProduct === null)>SEMUA PRODUK</option>
                            @foreach($availableProducts as $product)
                                <option value="{{ $product }}" @selected($selectedProduct === $product)>{{ $product }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <div class="lux-actions">
                    <button type="submit" class="lux-btn lux-btn-primary">
                        <i class="fas fa-search"></i> TAMPILKAN
                    </button>
                    <button type="button" id="captureAllBtn" class="lux-btn lux-btn-capture">
                        <i class="fas fa-camera"></i> CAPTURE ALL
                    </button>
                </div>
            </form>
        </div>

        <div class="kinerja-ajax-wrapper" id="kinerjaAjaxWrapper">
            <div class="kinerja-loading-overlay">
                <div class="premium-loader">
                    <div class="premium-loader-spinner"></div>
                    <div class="premium-loader-text">Mengolah Data RM...</div>
                </div>
            </div>
            <div id="kinerjaAjaxContainer">
                @include('report.kinerjarm-table')
            </div>
        </div>
    </div>
</div>



@push('scripts')
<script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('kinerjaFilterForm');
    const ajaxWrapper = document.getElementById('kinerjaAjaxWrapper');
    const ajaxContainer = document.getElementById('kinerjaAjaxContainer');
    const submitButton = filterForm?.querySelector('.lux-btn-primary');
    const tabStorageKey = 'kinerja-konsumer-active-tab';

    // Request timeout configuration (in milliseconds)
    const REQUEST_TIMEOUT = 45000; // 45 seconds
    let requestAbortController = null;

    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            loadKinerjaData();
        });
    }

    document.addEventListener('click', function(event) {
        const tabButton = event.target.closest('[data-kinerja-tab]');
        if (!tabButton) return;

        const nextTab = tabButton.dataset.kinerjaTab || 'os';
        setActiveKinerjaTab(nextTab);

        try {
            window.localStorage.setItem(tabStorageKey, nextTab);
        } catch (error) {
            // Ignore storage failures and keep the UI functional.
        }
    });

    function loadKinerjaData() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = `${filterForm.action}?${params.toString()}`;

        // Show loading state
        ajaxWrapper.classList.add('loading-active');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<div class="spinner-border spinner-border-sm me-2" style="width: 1rem; height: 1rem;"></div> Memproses...';
        }

        // Cancel previous request if still pending
        if (requestAbortController) {
            requestAbortController.abort();
        }
        requestAbortController = new AbortController();
        const controller = requestAbortController;

        // Set timeout
        const timeoutId = setTimeout(() => {
            if (controller === requestAbortController) {
                controller.abort();
            }
        }, REQUEST_TIMEOUT);

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            ajaxContainer.innerHTML = html;
            restoreKinerjaTabState();
            ajaxWrapper.classList.remove('loading-active');
            
            // Update URL and history without reload
            window.history.pushState({ path: url }, '', url);
            
            // Re-trigger reveal animation if any
            const contentArea = document.getElementById('kinerjaContentArea');
            if (contentArea) {
                contentArea.classList.add('animate-reveal');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            ajaxWrapper.classList.remove('loading-active');
            console.error('Error fetching kinerja data:', error);
            
            let errorMsg = 'Gagal memperbarui data. Silakan coba lagi atau Refresh halaman.';
            if (error.name === 'AbortError') {
                errorMsg = `Permintaan timeout setelah ${REQUEST_TIMEOUT / 1000} detik. Data terlalu besar atau koneksi lambat. Coba gunakan filter lebih spesifik atau lagi nanti.`;
            } else if (error.message.includes('HTTP')) {
                errorMsg = `Server error: ${error.message}`;
            }
            
            showErrorAlert(errorMsg);
        })
        .finally(() => {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-search"></i> TAMPILKAN';
            }
        });
    }

    // RM Detail Click Handler
    $(document).on('click', '.clickable-rm-row', function() {
        const rm = $(this).data('rm-name');
        const segmen = $(this).data('segment');
        const periode = $(this).data('period');
        
        const modal = new bootstrap.Modal(document.getElementById('rmDetailModal'));
        const content = $('#rmDetailModalContent');
        
        content.html(`
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Mengambil data rincian RM: ${rm}...</p>
            </div>
        `);
        
        modal.show();
        
        $.get("{{ route('report.dashboard-pinjaman.kinerjarm.history') }}", {
            rm: rm,
            segmen: segmen,
            periode: periode
        })
        .done(function(html) {
            content.html(html);
        })
        .fail(function(err) {
            content.html(`
                <div class="modal-header">
                    <h5 class="modal-title">Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <p>Gagal mengambil data rincian. Silakan coba lagi.</p>
                </div>
            `);
        });
    });

    restoreKinerjaTabState();

    function restoreKinerjaTabState() {
        let savedTab = 'os';

        try {
            savedTab = window.localStorage.getItem(tabStorageKey) || 'os';
        } catch (error) {
            savedTab = 'os';
        }

        setActiveKinerjaTab(savedTab);
    }

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
        marginX: 120,
        marginY: 120,
        headerHeight: 280,
        footerHeight: 80,
        sectionGap: 60,
    };

    function waitFrame() {
        return new Promise(resolve => requestAnimationFrame(() => resolve()));
    }

    function drawExportHeader(ctx, segmen, periode) {
        const { width, marginX, marginY } = A4_EXPORT;
        
        ctx.fillStyle = '#004685'; // BRI Blue Dark
        ctx.fillRect(0, 0, width, 24);

        ctx.fillStyle = '#0f172a';
        ctx.font = 'bold 62px "Inter", "Segoe UI", Arial, sans-serif';
        ctx.fillText('Kinerja RM Performance Report', marginX, marginY + 45);

        ctx.fillStyle = '#475569';
        ctx.font = '600 30px "Inter", "Segoe UI", Arial, sans-serif';
        ctx.fillText(`Segmen: ${segmen}   |   Periode: ${periode}`, marginX, marginY + 105);

        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(marginX, marginY + 160);
        ctx.lineTo(width - marginX, marginY + 160);
        ctx.stroke();
    }

    function drawExportFooter(ctx) {
        const { width, height, marginX } = A4_EXPORT;
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(marginX, height - 85);
        ctx.lineTo(width - marginX, height - 85);
        ctx.stroke();

        ctx.fillStyle = '#94a3b8';
        ctx.font = '600 22px "Inter", "Segoe UI", Arial, sans-serif';
        ctx.fillText(`Generated: ${new Date().toLocaleString('id-ID')}`, marginX, height - 45);
        
        ctx.textAlign = 'right';
        ctx.fillText('Report RM Performance', width - marginX, height - 45);
        ctx.textAlign = 'left';
    }

    const captureAllKinerjaRm = async function() {
        // Find the active panel (usually OS)
        const activePanel = document.querySelector('.kinerja-tab-panel.is-active');
        if (!activePanel) {
            alert('Tidak ada panel aktif untuk dicapture.');
            return;
        }

        const table = activePanel.querySelector('table');
        if (!table) {
            alert('Tabel tidak ditemukan.');
            return;
        }

        if (window.bootstrap) {
            const modal = new bootstrap.Modal(captureModal);
            modal.show();
        } else if (window.jQuery) {
            window.jQuery(captureModal).modal('show');
        }

        progressUI.classList.remove('d-none');
        errorUI.classList.add('d-none');
        successUI.classList.add('d-none');

        try {
            const tbodyRows = Array.from(table.querySelectorAll('tbody tr'));
            const segments = [];
            let currentSegment = null;

            // Group by branch rows
            tbodyRows.forEach(row => {
                if (row.classList.contains('loan-branch-subtotal')) {
                    currentSegment = {
                        rows: [row]
                    };
                    segments.push(currentSegment);
                } else if (currentSegment) {
                    currentSegment.rows.push(row);
                }
            });

            if (segments.length === 0) throw new Error('Tidak ada data untuk dicapture.');

            const reportCanvas = document.createElement('canvas');
            reportCanvas.width = A4_EXPORT.width;
            reportCanvas.height = A4_EXPORT.height;
            const ctx = reportCanvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, reportCanvas.width, reportCanvas.height);

            const segmenLabel = document.getElementById('kinerjaSegmen')?.options[document.getElementById('kinerjaSegmen').selectedIndex].text || 'RM';
            const periodeLabel = document.getElementById('kinerjaPeriode')?.options[document.getElementById('kinerjaPeriode').selectedIndex].text || '-';
            
            drawExportHeader(ctx, segmenLabel, periodeLabel);

            let currentY = A4_EXPORT.marginY + 220;
            const theadHtml = table.querySelector('thead').outerHTML;
            const tableWidth = table.offsetWidth;

            for (let i = 0; i < segments.length; i++) {
                const segment = segments[i];
                
                const tempWrap = document.createElement('div');
                tempWrap.style.position = 'absolute';
                tempWrap.style.left = '-9999px';
                tempWrap.style.width = tableWidth + 'px';
                
                tempWrap.innerHTML = `
                    <table class="${table.className}" style="width: ${tableWidth}px; border-collapse: separate; border-spacing: 0; background: #ffffff;">
                        ${theadHtml}
                        <tbody>
                            ${segment.rows.map(r => {
                                const clone = r.cloneNode(true);
                                clone.style.background = '#ffffff';
                                // Remove sticky positioning for capture
                                clone.querySelectorAll('td, th').forEach(cell => {
                                    cell.style.position = 'static';
                                    cell.style.backgroundColor = window.getComputedStyle(cell).backgroundColor;
                                });
                                return clone.outerHTML;
                            }).join('')}
                        </tbody>
                    </table>
                `;
                document.body.appendChild(tempWrap);

                await waitFrame();

                const segmentCanvas = await html2canvas(tempWrap.querySelector('table'), {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false
                });

                document.body.removeChild(tempWrap);

                const targetWidth = A4_EXPORT.width - (A4_EXPORT.marginX * 2);
                const targetHeight = (segmentCanvas.height * targetWidth) / segmentCanvas.width;

                // Check for page overflow (simple implementation: if it exceeds A4 height, we might need multiple pages)
                // For now, we'll just draw. If it's too many branches, it will overflow the single A4.
                // Professional approach: multiple canvases if currentY + targetHeight > A4_EXPORT.height
                
                ctx.drawImage(segmentCanvas, A4_EXPORT.marginX, currentY, targetWidth, targetHeight);
                currentY += targetHeight + A4_EXPORT.sectionGap;
                
                await waitFrame();
            }

            drawExportFooter(ctx);

            const timestamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            const link = document.createElement('a');
            link.download = `Kinerja-RM-A4-${timestamp}.jpg`;
            link.href = reportCanvas.toDataURL('image/jpeg', 0.95);
            link.click();

            progressUI.classList.add('d-none');
            successUI.classList.remove('d-none');
        } catch (err) {
            console.error('Capture failed:', err);
            progressUI.classList.add('d-none');
            errorUI.classList.remove('d-none');
            errorMessageUI.textContent = 'Gagal menyusun laporan. ' + err.message;
        }
    };

    if (captureBtn) {
        captureBtn.addEventListener('click', captureAllKinerjaRm);
    }

    function setActiveKinerjaTab(tabKey) {
        const normalizedTab = tabKey === 'kualitas' ? 'kualitas' : 'os';
        const tabButtons = document.querySelectorAll('[data-kinerja-tab]');
        const tabPanels = document.querySelectorAll('[data-kinerja-panel]');

        tabButtons.forEach((button) => {
            const isActive = (button.dataset.kinerjaTab || 'os') === normalizedTab;
            button.classList.toggle('active', isActive);
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        tabPanels.forEach((panel) => {
            const isActive = (panel.dataset.kinerjaPanel || 'os') === normalizedTab;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });
    }

    function showErrorAlert(message) {
        const alertHtml = `<div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin: 1rem;">
            <strong>Error</strong><br>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        ajaxContainer.innerHTML = alertHtml;
    }
});
</script>
@endpush
@endsection

@push('modals')
<!-- RM Detail Modal -->
<div class="modal fade" id="rmDetailModal" tabindex="-1" aria-labelledby="rmDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" id="rmDetailModalContent">
            <!-- Content loaded via AJAX -->
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Mengambil data historis...</p>
            </div>
        </div>
    </div>
</div>

<!-- Capture Status Modal -->
<div class="modal fade" id="captureStatusModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
            <div class="modal-body p-0">
                <!-- Progress UI -->
                <div id="captureProgressUI" class="text-center p-5">
                    <div class="premium-loader mb-4">
                        <div class="premium-loader-spinner" style="width: 70px; height: 70px; border-width: 5px;"></div>
                    </div>
                    <h4 class="font-weight-black text-dark mb-2" style="letter-spacing: -0.02em;">Menyusun Laporan A4</h4>
                    <p class="text-muted mb-0">Mohon tunggu, sedang memproses segmentasi data per cabang...</p>
                    <div class="progress mt-4" style="height: 6px; border-radius: 3px; background: #f1f5f9;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%; background: var(--loan-blue);"></div>
                    </div>
                </div>

                <!-- Error UI -->
                <div id="captureErrorUI" class="text-center p-5 d-none">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-circle text-danger" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="font-weight-black text-dark mb-2">Capture Gagal</h4>
                    <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kesalahan saat memproses gambar.</p>
                    <button type="button" class="btn btn-secondary px-4 py-2" style="border-radius: 12px; font-weight: 700;" data-bs-dismiss="modal">Tutup</button>
                </div>

                <!-- Success UI -->
                <div id="captureSuccessUI" class="text-center p-5 d-none">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; background: #dcfce7; color: #10b981; border-radius: 50%; font-size: 2.5rem;">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <h4 class="font-weight-black text-dark mb-2">Laporan Siap!</h4>
                    <p class="text-muted mb-4">File laporan A4 telah berhasil diunduh ke perangkat Anda.</p>
                    <button type="button" class="btn btn-primary px-5 py-2" style="border-radius: 12px; font-weight: 700; background: var(--loan-blue);" data-bs-dismiss="modal">Selesai</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endpush
