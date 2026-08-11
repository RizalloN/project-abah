@extends('layouts.admin')

@section('title', 'Dashboard Pinjaman Kredit SME')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .loan-filter-modern {
        display: grid;
        grid-template-columns: repeat(3, 1fr) auto;
        gap: 1.5rem;
        background: rgba(255, 255, 255, 0.85);
        -webkit-backdrop-filter: blur(25px);
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

    /* Prevent any clipping from parents */
    .loan-shell, .loan-shell .card-body {
        overflow: visible !important;
    }

    .loan-filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        position: relative;
    }

    /* Descending z-index for items */
    .loan-filter-item:nth-child(1) { z-index: 30; }
    .loan-filter-item:nth-child(2) { z-index: 20; }
    .loan-filter-item:nth-child(3) { z-index: 10; }

    .loan-filter-modern .loan-filter-label {
        font-size: 0.75rem;
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
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: var(--loan-blue);
        font-size: 1.1rem;
        pointer-events: none;
        opacity: 0.8;
    }

    .loan-dropdown-toggle {
        width: 100%;
        height: 60px;
        background: #ffffff;
        border: 2px solid #e2e8f0;
        border-radius: 18px;
        padding: 0 1.5rem 0 3.5rem;
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

    .loan-dropdown-toggle:hover {
        border-color: var(--loan-blue);
        box-shadow: 0 10px 25px rgba(8, 87, 195, 0.12);
        transform: translateY(-2px);
    }

    .loan-dropdown.is-open { z-index: 3100 !important; }
    .loan-dropdown.is-open .loan-dropdown-toggle {
        border-color: var(--loan-blue);
        box-shadow: 0 0 0 5px rgba(8, 87, 195, 0.1);
    }

    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 12px);
        left: 0;
        width: 100%;
        min-width: 340px;
        background: rgba(255, 255, 255, 0.98);
        -webkit-backdrop-filter: blur(25px);
        backdrop-filter: blur(25px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 1.75rem;
        box-shadow: 
            0 25px 50px -12px rgba(0, 0, 0, 0.2),
            0 15px 30px -10px rgba(0, 0, 0, 0.1);
        z-index: 3000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(15px);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 500px;
        overflow-y: auto;
        padding: 0.85rem;
    }

    .loan-dropdown.is-open .loan-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .loan-dropdown-option {
        width: 100%;
        padding: 0.85rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        font-weight: 700;
        font-size: 0.9rem;
        color: #475569;
        transition: all 0.2s;
        text-align: left;
        margin-bottom: 4px;
    }

    .loan-dropdown-option:hover {
        background: #f1f5f9;
        color: var(--loan-blue);
    }

    .loan-dropdown-option.is-active {
        background: rgba(8, 87, 195, 0.08);
        color: var(--loan-blue);
    }

    .loan-dropdown-check {
        width: 1.4rem;
        height: 1.4rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s;
        font-size: 0.8rem;
        color: white;
    }

    .loan-dropdown-option.is-active .loan-dropdown-check {
        background: var(--loan-blue);
        border-color: var(--loan-blue);
    }

    .rka-gap-stack {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        line-height: 1.15;
    }

    .rka-gap-stack__amount {
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }

    .rka-gap-stack .pct-data-bar-wrap {
        min-width: 58px;
        max-width: 70px;
        height: 14px;
        margin: 0 auto;
    }

    .rka-gap-stack .pct-data-label {
        font-size: 0.62rem;
    }

    .btn-loan-modern-submit {
        height: 60px;
        min-width: 220px;
        padding: 0 2rem;
        border-radius: 18px;
        background: linear-gradient(135deg, var(--loan-blue) 0%, #1e40af 100%);
        color: white;
        border: none;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 20px rgba(8, 87, 195, 0.3);
    }

    .btn-loan-modern-submit:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(8, 87, 195, 0.4);
    }

    /* Hide Original */
    .select2-container--bootstrap4, .loan-filter-control {
        display: none !important;
    }

    #loanDashboardCaptureArea .loan-summary-table-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    #loanDashboardCaptureArea .loan-table-container {
        width: max-content;
        min-width: 1680px;
    }

    @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
        .loan-filter-modern,
        .loan-dropdown-menu {
            background: #ffffff;
        }
    }

    @media (max-width: 991.98px) {
        .loan-filter-modern {
            grid-template-columns: 1fr;
            gap: 1rem;
            padding: 1rem;
            border-radius: 14px;
        }

        .btn-loan-modern-submit {
            min-width: 0;
        }
    }

    @media (max-width: 1180px), (max-height: 760px) {
        .loan-dashboard {
            padding-top: .75rem !important;
        }

        .loan-title-hero {
            margin-bottom: .5rem;
            padding: .75rem .25rem;
        }

        .loan-title-hero__title {
            font-size: 1.25rem;
            line-height: 1.12;
        }

        .loan-title-hero__desc {
            margin-top: .25rem;
            font-size: .75rem;
            line-height: 1.35;
        }

        .loan-shell {
            margin-bottom: 1rem !important;
        }

        .loan-shell .card-body {
            padding: .85rem !important;
        }

        .loan-filter-modern {
            gap: .75rem;
            margin-bottom: 1rem;
            padding: .85rem;
            border-radius: 12px;
        }

        .loan-filter-modern .loan-filter-label {
            margin-left: .25rem;
            font-size: .65rem;
            letter-spacing: .06em;
        }

        .loan-dropdown-toggle,
        .btn-loan-modern-submit {
            height: 44px;
            border-radius: 10px;
            font-size: .78rem;
        }

        .loan-dropdown-toggle {
            padding-left: 2.45rem;
            padding-right: .85rem;
        }

        .loan-dropdown-icon {
            left: .85rem;
            font-size: .9rem;
        }

        .loan-section-block {
            margin-bottom: 1rem !important;
        }
    }

    @media (orientation: landscape) and (max-height: 540px) and (min-width: 760px) {
        .loan-filter-modern {
            grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(160px, auto);
            align-items: end;
        }

        .loan-title-hero__desc {
            display: none;
        }
    }

    @media (orientation: landscape) and (max-height: 540px) and (max-width: 759.98px) {
        .loan-filter-modern {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .loan-filter-modern > div:last-child {
            grid-column: 1 / -1;
        }

        .loan-title-hero__desc {
            display: none;
        }
    }

    @media (max-width: 575.98px) {
        .loan-dashboard {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        .loan-dropdown-toggle {
            height: 52px;
            padding-right: 1rem;
            font-size: 0.85rem;
        }

        .loan-dropdown-menu {
            min-width: 0;
            border-radius: 14px;
        }

        .loan-section-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .loan-section-header .legend-box {
            margin-left: 0 !important;
            flex-wrap: wrap;
        }
    }

    /* ==========================================
       EXCEL TABLE AESTHETICS OVERRIDES
       ========================================== */

    /* Force Zero Border Radius & Remove shadows globally for an authentic Excel sheet look */
    .loan-shell, 
    .loan-table-shell, 
    .loan-summary-table-wrap, 
    .loan-summary-table, 
    .loan-summary-table th, 
    .loan-summary-table td,
    .loan-filter-modern,
    .loan-dropdown-toggle,
    .loan-dropdown-menu,
    .loan-dropdown-option,
    .btn-loan-modern-submit,
    .pct-data-bar-wrap,
    .pct-data-bar,
    .btn-capture-all,
    .btn-snapshot,
    .loan-section-header {
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    /* Remove top decorative linear gradient line for flat sheet look */
    .loan-shell::before, 
    .loan-table-shell::before {
        display: none !important;
    }

    /* Crisp Grid Container & Excel Tables */
    .loan-summary-table-wrap {
        --loan-sticky-cabang-width: 150px;
        --loan-sticky-kategori-width: 190px;
        --loan-sticky-kategori-left: 150px;
        --loan-sticky-total-width: 340px;
        border: 1px solid #94a3b8 !important;
        margin-bottom: 2rem !important;
        background-color: #ffffff !important;
        overflow: auto !important;
        max-height: min(72vh, 720px) !important;
        position: relative !important;
        isolation: isolate !important;
        overscroll-behavior-x: contain;
    }

    .loan-summary-table {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        border: 1px solid #94a3b8 !important;
        width: max-content !important;
        min-width: 1680px !important;
        table-layout: fixed !important;
    }

    /* Excel Corporate Header Styling */
    .loan-summary-table thead th {
        position: sticky !important;
        background-color: #475569 !important; /* Premium corporate slate gray */
        color: #ffffff !important;
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif !important;
        font-weight: 700 !important;
        font-size: 0.76rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 8px 10px !important;
        border-right: 1px solid #334155 !important;
        border-bottom: 1px solid #334155 !important;
        text-align: center !important;
        vertical-align: middle !important;
        line-height: 1.2 !important;
        white-space: normal !important;
        min-width: 92px;
        box-sizing: border-box !important;
    }

    /* Header Row 1 Vertical Sticky */
    .loan-summary-table thead tr:first-child > th {
        top: 0 !important;
        z-index: 30 !important;
    }

    /* Header Row 2 Vertical Sticky */
    .loan-summary-table thead tr:nth-child(2) > th {
        top: var(--loan-summary-header-row-height, 34px) !important;
        z-index: 20 !important;
    }

    .loan-summary-table thead th.sub-head {
        background-color: #334155 !important; /* Deep corporate dark slate */
        border-right: 1px solid #1e293b !important;
        border-bottom: 1px solid #1e293b !important;
    }

    .loan-summary-table thead th.accent-head {
        background-color: #1e293b !important; /* Accent dark slate for delta metrics */
        border-right: 1px solid #0f172a !important;
        border-bottom: 1px solid #0f172a !important;
    }

    /* Grid Cells with Excel Grid Lines */
    .loan-summary-table td {
        border-right: 1px solid #cbd5e1 !important; /* Muted crisp excel inner gridlines */
        border-bottom: 1px solid #cbd5e1 !important;
        padding: 6px 12px !important; /* Perfect accounting cell padding */
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif !important;
        font-size: 0.82rem !important;
        font-weight: 500 !important;
        color: #1e293b !important;
        vertical-align: middle !important;
        text-align: right !important; /* Right aligned numbers */
        font-variant-numeric: tabular-nums !important; /* Perfect alignment of digits */
        line-height: 1.22 !important;
        white-space: nowrap !important;
        min-width: 92px;
        box-sizing: border-box !important;
    }

    /* Text & Label Alignments */
    .loan-summary-table td.text-start-important,
    .loan-summary-table td.merged-branch-cell {
        text-align: left !important;
        font-weight: 600 !important;
        color: #334155 !important;
        min-width: 160px;
        max-width: 280px;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }

    .loan-summary-table td.text-center-important {
        text-align: center !important;
        font-weight: 600 !important;
    }

    /* Excel-Style Soft Subtotal Highlight (Removes harsh solid dark navy bg) */
    .loan-branch-subtotal,
    .loan-branch-subtotal td {
        background-color: #e2e8f0 !important; /* Soft, readable gray-blue subtotal row */
        color: #0f172a !important;
        font-weight: 700 !important;
        border-top: 1px solid #64748b !important;
        border-bottom: 2px solid #64748b !important;
    }

    /* Table Column Rowspan Side-Labels remain crisp and white */
    .loan-summary-table td[rowspan] {
        background-color: #ffffff !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        border-bottom: 2px solid #94a3b8 !important;
        border-right: 1px solid #cbd5e1 !important;
    }

    /* Subtle Zebra Striping for detail data rows only (Subtotals and totals are excluded) */
    .loan-summary-table tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }

    .loan-summary-table tbody tr.loan-branch-subtotal,
    .loan-summary-table tbody tr.loan-grand-total {
        background-color: transparent;
    }

    /* Accounting Double-Underline Excel Grand Total Styling (Removes dark blackout bg) */
    .loan-summary-table tbody tr.loan-grand-total,
    .loan-summary-table tbody tr.loan-grand-total td {
        background-color: #cbd5e1 !important; /* Excel total row solid steel color */
        color: #0f172a !important;
        font-weight: 800 !important;
        font-size: 0.85rem !important;
        border-top: 2px solid #334155 !important;
        border-bottom: 4px double #000000 !important; /* Classic Excel double underline */
    }

    /* Excel Conditional Formatting Color Palette (Soft & Premium Contrast) */
    .achieve-positive {
        color: #16a34a !important; /* Deep premium green */
        font-weight: 700 !important;
    }
    
    .achieve-negative {
        color: #dc2626 !important; /* Rich premium red */
        font-weight: 700 !important;
    }
    
    .achieve-neutral {
        color: #d97706 !important; /* Distinct premium amber */
        font-weight: 700 !important;
    }

    .loan-summary-table td.loan-delta-cell {
        font-weight: 800 !important;
        border-left-color: rgba(15, 23, 42, 0.18) !important;
        border-right-color: rgba(15, 23, 42, 0.18) !important;
    }

    .loan-summary-table td.loan-delta-cell.delta-positive {
        background-color: #dcfce7 !important;
        color: #15803d !important;
    }

    .loan-summary-table td.loan-delta-cell.delta-negative {
        background-color: #fee2e2 !important;
        color: #b91c1c !important;
    }

    .loan-summary-table td.loan-delta-cell.delta-neutral {
        background-color: #fef3c7 !important;
        color: #b45309 !important;
    }

    /* Excel-Style Conditional formatting pastel data bars */
    .pct-data-bar-wrap {
        height: 18px !important;
        background-color: #f1f5f9 !important;
        border: 1px solid #cbd5e1 !important;
    }

    .pct-data-bar {
        opacity: 0.45 !important; /* Soft Excel color overlay */
    }

    .pct-data-bar.bar-success {
        background-color: #86efac !important; /* Pastel Green */
    }

    .pct-data-bar.bar-warning {
        background-color: #fef08a !important; /* Pastel Yellow */
    }

    .pct-data-bar.bar-danger {
        background-color: #fca5a5 !important; /* Pastel Red */
    }

    .pct-data-label {
        color: #1e293b !important;
        font-weight: 800 !important;
        font-size: 0.72rem !important;
        line-height: 16px !important;
    }

    /* Premium Modernized Filters without rounded corners */
    .loan-filter-modern {
        background-color: #ffffff !important;
        border: 2px solid #e2e8f0 !important;
        padding: 1.25rem !important;
        margin-bottom: 2rem !important;
    }

    .loan-dropdown-toggle {
        height: 48px !important;
        border: 2px solid #cbd5e1 !important;
        padding: 0 1.25rem 0 2.5rem !important;
        font-size: 0.9rem !important;
        font-weight: 600 !important;
        color: #1e293b !important;
    }

    .loan-dropdown-toggle:hover {
        border-color: #475569 !important;
        transform: none !important;
    }

    .loan-dropdown-icon {
        left: 1rem !important;
        color: #475569 !important;
    }

    .loan-dropdown-menu {
        border: 2px solid #94a3b8 !important;
        padding: 0.5rem !important;
        margin-top: 2px !important;
    }

    .loan-dropdown-option {
        padding: 0.6rem 1rem !important;
        font-weight: 600 !important;
    }

    .btn-loan-modern-submit {
        height: 48px !important;
        background: #334155 !important; /* Dark corporate steel button */
        border: none !important;
        font-weight: 700 !important;
        transition: background 0.15s ease !important;
    }

    .btn-loan-modern-submit:hover {
        background: #1e293b !important;
        transform: none !important;
    }

    .loan-page-title {
        font-weight: 800 !important;
        letter-spacing: -0.02em !important;
        color: #0f172a !important;
        text-transform: uppercase !important;
    }

    /* Sticky / Frozen Column 1 for Kantor Cabang / Uker */
    .loan-summary-table thead th.sticky-cabang-header,
    .loan-summary-table td.sticky-cabang-cell {
        position: sticky !important;
        left: 0 !important;
        width: var(--loan-sticky-cabang-width) !important;
        min-width: var(--loan-sticky-cabang-width) !important;
        max-width: var(--loan-sticky-cabang-width) !important;
        box-sizing: border-box !important;
        background-clip: padding-box !important;
    }

    .loan-summary-table thead th.sticky-cabang-header {
        top: 0 !important;
        z-index: 60 !important;
        background-color: #475569 !important;
        border-right: 1px solid #334155 !important;
        border-bottom: 2px solid #1e293b !important;
        box-shadow: 2px 0 5px rgba(15, 23, 42, 0.12) !important;
    }

    .loan-summary-table td.sticky-cabang-cell {
        z-index: 15 !important;
        background-color: #ffffff !important;
        border-right: 1px solid #cbd5e1 !important;
        box-shadow: 2px 0 5px rgba(15, 23, 42, 0.06) !important;
    }

    /* Consolidation Table Single Column Override (Width 250px) */
    .loan-summary-table thead th.sticky-consolidation-header,
    .loan-summary-table td.sticky-consolidation-cell {
        width: 250px !important;
        min-width: 250px !important;
        max-width: 250px !important;
        border-right: 2px solid #94a3b8 !important;
        box-shadow: 4px 0 8px -2px rgba(15, 23, 42, 0.15) !important;
    }

    /* Sticky / Frozen Column 2 for Kategori */
    .loan-summary-table thead th.sticky-kategori-header,
    .loan-summary-table td.sticky-kategori-cell {
        position: sticky !important;
        left: var(--loan-sticky-kategori-left) !important;
        width: var(--loan-sticky-kategori-width) !important;
        min-width: var(--loan-sticky-kategori-width) !important;
        max-width: var(--loan-sticky-kategori-width) !important;
        box-sizing: border-box !important;
        white-space: nowrap !important;
        background-clip: padding-box !important;
    }

    .loan-summary-table thead th.sticky-kategori-header {
        top: 0 !important;
        z-index: 61 !important;
        background-color: #475569 !important;
        border-right: 2px solid #1e293b !important;
        border-bottom: 2px solid #1e293b !important;
        box-shadow: 4px 0 8px -2px rgba(15, 23, 42, 0.18) !important;
    }

    .loan-summary-table td.sticky-kategori-cell {
        z-index: 16 !important;
        background-color: #ffffff !important;
        border-right: 2px solid #94a3b8 !important;
        box-shadow: 4px 0 8px -2px rgba(15, 23, 42, 0.08) !important;
    }

    .loan-summary-table tr.loan-branch-subtotal td.sticky-cabang-cell,
    .loan-summary-table tr.loan-branch-subtotal td.sticky-kategori-cell {
        background-color: #e2e8f0 !important;
    }

    .loan-summary-table tbody tr.loan-grand-total td.sticky-cabang-cell,
    .loan-summary-table tbody tr.loan-grand-total td.sticky-kategori-cell {
        background-color: #cbd5e1 !important;
    }

    .loan-summary-table tbody tr.loan-grand-total td.sticky-cabang-cell[colspan="2"] {
        left: 0 !important;
        width: var(--loan-sticky-total-width) !important;
        min-width: var(--loan-sticky-total-width) !important;
        max-width: var(--loan-sticky-total-width) !important;
        border-right: 2px solid #94a3b8 !important;
        box-shadow: 4px 0 8px -2px rgba(15, 23, 42, 0.18) !important;
    }

    @media (max-width: 991.98px), (max-height: 760px) {
        #loanDashboardCaptureArea > .d-flex:first-of-type {
            gap: 0.65rem;
            margin-bottom: 0.85rem !important;
        }

        .loan-page-title {
            margin-bottom: 0.15rem !important;
            font-size: clamp(1.15rem, 4vw, 1.45rem) !important;
            line-height: 1.12 !important;
        }

        #loanDashboardCaptureArea > .d-flex:first-of-type p {
            display: -webkit-box;
            overflow: hidden;
            max-width: 68ch;
            font-size: 0.74rem;
            line-height: 1.35;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 1;
        }

        .btn-capture-all {
            min-height: 34px;
            padding: 0.4rem 0.65rem !important;
            font-size: 0.7rem !important;
        }

        .loan-filter-modern {
            gap: 0.6rem !important;
            padding: 0.7rem !important;
            margin-bottom: 0.85rem !important;
        }

        .loan-filter-modern .loan-filter-label {
            margin-left: 0 !important;
            font-size: 0.62rem !important;
        }

        .loan-dropdown-toggle,
        .btn-loan-modern-submit {
            height: 38px !important;
            min-height: 38px !important;
            font-size: 0.72rem !important;
        }

        .loan-dropdown-menu {
            max-height: min(48vh, 320px);
        }

        .loan-summary-table-wrap {
            max-height: calc(100vh - 235px) !important;
            overflow-y: auto !important;
        }
    }

    @media (orientation: landscape) and (max-height: 640px) {
        #loanDashboardCaptureArea > .d-flex:first-of-type p,
        #loanDashboardCaptureArea .legend-box span {
            display: none !important;
        }

        .loan-filter-modern {
            grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(150px, auto) !important;
        }

        .loan-summary-table-wrap {
            max-height: calc(100vh - 170px) !important;
        }
    }

    #loanDashboardCaptureArea.loan-credit-dashboard {
        max-width: 100%;
        padding-top: 1rem !important;
    }

    #loanDashboardCaptureArea .loan-credit-header {
        gap: 0.75rem;
        margin-bottom: 1rem !important;
    }

    #loanDashboardCaptureArea .loan-credit-header > div {
        min-width: 0;
        max-width: 100%;
    }

    #loanDashboardCaptureArea .loan-credit-header .loan-page-title {
        margin-bottom: 0.2rem;
        font-size: clamp(1.35rem, 2.4vw, 2rem);
        line-height: 1.1;
        max-width: 100%;
        white-space: normal !important;
        overflow-wrap: break-word;
    }

    #loanDashboardCaptureArea .loan-credit-header p {
        max-width: 54rem;
        font-size: 0.86rem;
        line-height: 1.35;
        overflow-wrap: break-word;
    }

    #loanDashboardCaptureArea .loan-shell {
        border-radius: 8px !important;
        margin-bottom: 1rem !important;
    }

    #loanDashboardCaptureArea .loan-shell::before {
        height: 4px;
    }

    #loanDashboardCaptureArea .loan-shell .card-body {
        padding: 1rem !important;
    }

    #loanDashboardCaptureArea .loan-filter-modern {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(160px, 1fr)) minmax(188px, 0.82fr) !important;
        align-items: end !important;
        gap: 0.85rem !important;
        margin-bottom: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    #loanDashboardCaptureArea .loan-filter-item {
        gap: 0.42rem !important;
        min-width: 0;
        max-width: 100%;
    }

    #loanDashboardCaptureArea .loan-filter-modern .loan-filter-label {
        margin: 0 !important;
        color: #475569 !important;
        font-size: 0.66rem !important;
        letter-spacing: 0.075em !important;
        line-height: 1.2;
    }

    #loanDashboardCaptureArea .loan-dropdown-toggle,
    #loanDashboardCaptureArea .btn-loan-modern-submit {
        height: 42px !important;
        min-height: 42px !important;
        border-radius: 8px !important;
        font-size: 0.78rem !important;
        line-height: 1.15 !important;
    }

    #loanDashboardCaptureArea .loan-dropdown,
    #loanDashboardCaptureArea .loan-dropdown-toggle {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    #loanDashboardCaptureArea .loan-dropdown-toggle {
        padding: 0 0.75rem 0 2.45rem !important;
        overflow: hidden;
    }

    #loanDashboardCaptureArea .loan-dropdown-text {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #loanDashboardCaptureArea .loan-dropdown-icon {
        left: 0.85rem !important;
        font-size: 0.86rem !important;
    }

    #loanDashboardCaptureArea .loan-dropdown-menu {
        top: calc(100% + 6px) !important;
        min-width: min(320px, calc(100vw - 2rem)) !important;
        max-height: min(52vh, 340px) !important;
        border-radius: 8px !important;
        padding: 0.35rem !important;
    }

    #loanDashboardCaptureArea .loan-dropdown-option {
        min-height: 34px;
        padding: 0.48rem 0.65rem !important;
        border-radius: 6px !important;
        gap: 0.55rem !important;
    }

    #loanDashboardCaptureArea .loan-dropdown-check {
        width: 1.05rem;
        height: 1.05rem;
        border-radius: 4px;
        flex: 0 0 auto;
    }

    #loanDashboardCaptureArea .loan-filter-action {
        align-self: end;
        min-width: 0;
    }

    #loanDashboardCaptureArea .btn-loan-modern-submit {
        min-width: 0 !important;
        padding: 0 0.85rem !important;
        white-space: nowrap;
    }

    @media (max-width: 1199.98px) {
        #loanDashboardCaptureArea.loan-credit-dashboard {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }

        #loanDashboardCaptureArea .loan-filter-modern {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }

        #loanDashboardCaptureArea .loan-filter-action {
            grid-column: auto;
        }

        #loanDashboardCaptureArea .btn-loan-modern-submit {
            width: 100%;
        }
    }

    @media (max-width: 767.98px) {
        #loanDashboardCaptureArea .loan-credit-header {
            align-items: stretch !important;
        }

        #loanDashboardCaptureArea .loan-credit-header > div {
            width: 100%;
        }

        #loanDashboardCaptureArea .loan-credit-header .loan-page-title {
            font-size: 1.2rem;
            line-height: 1.18;
        }

        #loanDashboardCaptureArea .loan-credit-header p {
            max-width: 100%;
        }

        #loanDashboardCaptureArea .loan-credit-header .btn-capture-all {
            width: 100%;
            justify-content: center;
            min-width: 0;
            white-space: normal;
        }

        #loanDashboardCaptureArea .loan-filter-modern {
            grid-template-columns: 1fr !important;
        }

        #loanDashboardCaptureArea .loan-dropdown-menu {
            width: 100%;
            min-width: 0 !important;
        }

        #loanDashboardCaptureArea .btn-loan-modern-submit {
            white-space: normal;
            line-height: 1.18 !important;
        }
    }

    @media (max-height: 700px) and (min-width: 768px) {
        #loanDashboardCaptureArea.loan-credit-dashboard {
            padding-top: 0.6rem !important;
        }

        #loanDashboardCaptureArea .loan-credit-header {
            margin-bottom: 0.65rem !important;
        }

        #loanDashboardCaptureArea .loan-credit-header p {
            display: none;
        }

        #loanDashboardCaptureArea .loan-shell .card-body {
            padding: 0.75rem !important;
        }

        #loanDashboardCaptureArea .loan-filter-modern {
            gap: 0.65rem !important;
        }
    }
</style>

<div class="loan-dashboard loan-credit-dashboard pt-4 px-3" id="loanDashboardCaptureArea">
    <div class="loan-credit-header d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="loan-page-title">Dashboard Pinjaman Kredit</h1>
            <p class="text-muted mb-0">Analisis performa portofolio berdasarkan segmen dan kategori.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex align-items-center gap-2">
            <button id="captureAllBtn" class="btn btn-outline-primary btn-capture-all">
                <i class="fas fa-file-image"></i> EXPORT A4 PORTRAIT
            </button>
        </div>
    </div>

    <div class="card loan-shell mb-4 animate-reveal">
        <div class="card-body p-4">
            <div class="loan-filter-modern">
                <div class="loan-filter-item">
                    <label class="loan-filter-label">Kanca</label>
                    <div class="loan-dropdown" data-loan-dropdown="kanca">
                        <i class="fas fa-building loan-dropdown-icon"></i>
                        <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="kanca">
                            <span class="loan-dropdown-text">{{ $selectedKanca === 'all' ? 'Area 6' : $selectedKanca }}</span>
                            <i class="fas fa-chevron-down small opacity-50"></i>
                        </button>
                        <div class="loan-dropdown-menu" data-loan-dropdown-menu="kanca">
                            @foreach($kancaOptions as $option)
                                <div class="loan-dropdown-option {{ $option['value'] === $selectedKanca ? 'is-active' : '' }}" data-value="{{ $option['value'] }}">
                                    <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                    <span>{{ $option['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <select id="kancaSelector" name="kanca" class="d-none">
                            @foreach($kancaOptions as $option)
                                <option value="{{ $option['value'] }}" @selected($option['value'] === $selectedKanca)>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="loan-filter-item">
                    <label class="loan-filter-label">Periode Terakhir</label>
                    <div class="loan-dropdown" data-loan-dropdown="periode">
                        <i class="fas fa-calendar-day loan-dropdown-icon"></i>
                        <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="periode">
                            <span class="loan-dropdown-text">Pilih Periode</span>
                            <i class="fas fa-chevron-down small opacity-50"></i>
                        </button>
                        <div class="loan-dropdown-menu" data-loan-dropdown-menu="periode">
                            @foreach($periods as $periode)
                                <div class="loan-dropdown-option {{ $periode === $selectedPeriod ? 'is-active' : '' }}" data-value="{{ $periode }}">
                                    <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                    <span>{{ \Carbon\Carbon::parse($periode)->format('d M Y') }}</span>
                                </div>
                            @endforeach
                        </div>
                        <select id="periodeSelector" class="d-none">
                            @foreach($periods as $periode)
                                <option value="{{ $periode }}" @selected($periode === $selectedPeriod)>{{ $periode }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="loan-filter-item">
                    <label class="loan-filter-label">Kategori Portofolio</label>
                    <div class="loan-dropdown" data-loan-dropdown="kategori">
                        <i class="fas fa-tags loan-dropdown-icon"></i>
                        <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="kategori">
                            <span class="loan-dropdown-text">{{ $selectedCategory }}</span>
                            <i class="fas fa-chevron-down small opacity-50"></i>
                        </button>
                        <div class="loan-dropdown-menu" data-loan-dropdown-menu="kategori">
                            @foreach($categories as $cat)
                                <div class="loan-dropdown-option {{ $cat === $selectedCategory ? 'is-active' : '' }}" data-value="{{ $cat }}">
                                    <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                    <span>{{ $cat }}</span>
                                </div>
                            @endforeach
                        </div>
                        <select id="kategoriSelector" class="d-none">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" @selected($cat === $selectedCategory)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="loan-filter-action">
                    <button type="button" class="btn-loan-modern-submit w-100" id="btnLoadData">
                        <i class="fas fa-sync-alt"></i> PERBARUI DASHBOARD
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Meta Information -->
    <div class="d-none">
        <span id="dashboardMeta">Menyiapkan dashboard kredit harian.</span>
    </div>

    <!-- Dashboard Content -->
    <div id="dashboardContent" class="animate-reveal">
        
        <!-- Consolidation Area 6 Section -->
        <div class="loan-section-block mb-4 d-none" id="consolidationSection">
            <div class="loan-section-header">
                <h3 id="consolidationTitle">KONSOLIDASI AREA 6</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('consolidationSection', 'Snapshot-Konsolidasi')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="consolidationTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'Konsolidasi'])
            </div>
        </div>

        <!-- OS Section -->
        <div class="loan-section-block mb-4" id="osSection">
            <div class="loan-section-header">
                <h3 id="osTitle">A. OUTSTANDING (OS)</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('osSection', 'Snapshot-OS')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="osTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'Outstanding'])
            </div>
        </div>

        <!-- SML Section -->
        <div class="loan-section-block mb-4" id="smlSection">
            <div class="loan-section-header">
                <h3 id="smlTitle">B. SPECIAL MENTION LOAN (SML)</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('smlSection', 'Snapshot-SML')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="smlTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'SML'])
            </div>
        </div>

        <!-- NPL Section -->
        <div class="loan-section-block mb-4" id="nplSection">
            <div class="loan-section-header">
                <h3 id="nplTitle">C. NON-PERFORMING LOAN (NPL)</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('nplSection', 'Snapshot-NPL')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="nplTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'NPL'])
            </div>
        </div>

    </div>
</div>

<!-- Capture Status Modal -->
<div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div id="captureProgressUI">
                    <div class="capture-status-modal-icon icon-loading">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Menyusun Laporan A4</h4>
                    <p class="text-muted mb-0">Sedang menyusun tabel ringkasan ke dalam beberapa file gambar. Mohon tunggu sebentar...</p>
                </div>

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

                <div id="captureSuccessUI" class="d-none">
                    <div class="capture-status-modal-icon icon-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Snapshot Berhasil!</h4>
                    <p class="text-muted mb-4">Semua file snapshot telah berhasil diunduh ke perangkat Anda.</p>
                    <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const osTableContainer = document.getElementById('osTableContainer');
    const consolidationTableContainer = document.getElementById('consolidationTableContainer');
    const consolidationSection = document.getElementById('consolidationSection');
    const smlTableContainer = document.getElementById('smlTableContainer');
    const nplTableContainer = document.getElementById('nplTableContainer');
    const btnLoadData = document.getElementById('btnLoadData');
    const dashboardMeta = document.getElementById('dashboardMeta');
    const captureAllBtn = document.getElementById('captureAllBtn');
    const captureModal = document.getElementById('captureStatusModal');
    
    // Request timeout (in milliseconds)
    const REQUEST_TIMEOUT = 60000;
    let requestAbortController = null;

    // Select2 elements
    const $kancaSel = $('#kancaSelector');
    const $periodeSel = $('#periodeSelector');
    const $kategoriSel = $('#kategoriSelector');

    function formatCurrency(value) {
        if (value === null || value === undefined || value === '') return '-';
        let num = Math.round(parseFloat(value) / 1000000);
        const isNeg = num < 0;
        if (isNeg) num = Math.abs(num);
        let formatted = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num);
        return isNeg ? `(${formatted})` : formatted;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch(e) { return dateStr; }
    }

    function formatPctBadge(value, type) {
        const num = parseFloat(value) || 0;
        let barClass = '';
        let textClass = '';
        
        if (num > 100) {
            barClass = 'bar-success';
            textClass = 'achieve-positive';
        } else if (num >= 90) {
            barClass = 'bar-warning';
            textClass = 'achieve-neutral';
        } else {
            barClass = 'bar-danger';
            textClass = 'achieve-negative';
        }
        
        const clampedPct = Math.min(100, Math.max(0, Math.abs(num)));
        const decimals = isQualityType(type) ? 2 : 1;
        return `
            <div class="pct-data-bar-wrap">
                <div class="pct-data-bar ${barClass}" style="width: ${clampedPct}%"></div>
                <span class="pct-data-label ${textClass}">${num.toFixed(decimals)}%</span>
            </div>
        `;
    }

    function isQualityType(type) {
        const typeUpper = (type || '').toUpperCase();
        return typeUpper.includes('SML')
            || typeUpper.includes('NPL')
            || typeUpper.includes('LAR')
            || typeUpper.includes('LR');
    }

    function getPositionDeltaClass(value, type) {
        const num = parseFloat(value) || 0;

        if (num === 0) {
            return 'loan-delta-cell delta-neutral achieve-neutral';
        }

        if (isQualityType(type)) {
            return num < 0
                ? 'loan-delta-cell delta-positive achieve-positive'
                : 'loan-delta-cell delta-negative achieve-negative';
        }

        return num > 0
            ? 'loan-delta-cell delta-positive achieve-positive'
            : 'loan-delta-cell delta-negative achieve-negative';
    }

    function getConditionalClass(value, type, isRka = false) {
        const num = parseFloat(value) || 0;

        if (num === 0) return 'achieve-neutral';

        if (isRka) {
            if (isQualityType(type)) {
                return num < 0 ? 'achieve-positive' : 'achieve-negative';
            }

            return num > 0 ? 'achieve-positive' : 'achieve-negative';
        }

        const isReversed = isQualityType(type);

        if (num > 0) {
            return isReversed ? 'achieve-negative' : 'achieve-positive';
        }
        return isReversed ? 'achieve-positive' : 'achieve-negative';
    }

    function calculateRkaPercentage(selected, rka, type) {
        const selectedNum = parseFloat(selected) || 0;
        const rkaNum = parseFloat(rka) || 0;

        if (isQualityType(type)) {
            return selectedNum > 0 ? (rkaNum / selectedNum) * 100 : 100;
        }

        return rkaNum > 0 ? (selectedNum / rkaNum) * 100 : 0;
    }

    function getCaptureScale(width = 0, height = 0) {
        const preferredScale = Math.min(4, Math.max(3, Math.ceil((window.devicePixelRatio || 1) * 2)));
        const maxEdge = Math.max(width, height, 1);
        const browserSafeScale = Math.max(2, Math.min(preferredScale, 16000 / maxEdge));

        return Number(browserSafeScale.toFixed(2));
    }

    function getFullCaptureWidth(section) {
        const widths = [
            section.scrollWidth || 0,
            section.offsetWidth || 0,
            section.getBoundingClientRect().width || 0
        ];

        section.querySelectorAll('.loan-summary-table-wrap, .loan-summary-table, table').forEach(el => {
            widths.push(el.scrollWidth || 0, el.offsetWidth || 0, el.getBoundingClientRect().width || 0);
        });

        return Math.ceil(Math.max(...widths, 1680));
    }

    function setCaptureStyle(el, property, value) {
        el.style.setProperty(property, value, 'important');
    }

    function getFullCaptureHeight(clone) {
        const heights = [
            clone.scrollHeight || 0,
            clone.offsetHeight || 0,
            clone.getBoundingClientRect().height || 0
        ];

        clone.querySelectorAll('.loan-summary-table-wrap, .loan-summary-table, table').forEach(el => {
            const rect = el.getBoundingClientRect();
            const cloneRect = clone.getBoundingClientRect();
            heights.push(
                el.scrollHeight || 0,
                el.offsetHeight || 0,
                rect.height || 0,
                (rect.bottom - cloneRect.top) || 0
            );
        });

        return Math.ceil(Math.max(...heights, 1));
    }

    function prepareLoanCaptureElement(section) {
        const fullWidth = getFullCaptureWidth(section);
        const captureHost = document.createElement('div');
        const clone = section.cloneNode(true);

        captureHost.className = 'loan-capture-host';
        setCaptureStyle(captureHost, 'position', 'fixed');
        setCaptureStyle(captureHost, 'left', '0');
        setCaptureStyle(captureHost, 'top', '0');
        setCaptureStyle(captureHost, 'width', `${fullWidth}px`);
        setCaptureStyle(captureHost, 'max-width', 'none');
        setCaptureStyle(captureHost, 'height', 'auto');
        setCaptureStyle(captureHost, 'max-height', 'none');
        setCaptureStyle(captureHost, 'overflow', 'visible');
        setCaptureStyle(captureHost, 'pointer-events', 'none');
        setCaptureStyle(captureHost, 'background', '#ffffff');
        setCaptureStyle(captureHost, 'z-index', '0');

        clone.id = `${section.id || 'loan-section'}CaptureClone`;
        clone.classList.add('loan-capture-clone');
        setCaptureStyle(clone, 'position', 'relative');
        setCaptureStyle(clone, 'left', '0');
        setCaptureStyle(clone, 'top', '0');
        setCaptureStyle(clone, 'width', `${fullWidth}px`);
        setCaptureStyle(clone, 'max-width', 'none');
        setCaptureStyle(clone, 'min-width', `${fullWidth}px`);
        setCaptureStyle(clone, 'height', 'auto');
        setCaptureStyle(clone, 'max-height', 'none');
        setCaptureStyle(clone, 'overflow', 'visible');
        setCaptureStyle(clone, 'overflow-x', 'visible');
        setCaptureStyle(clone, 'overflow-y', 'visible');
        setCaptureStyle(clone, 'background', '#ffffff');
        setCaptureStyle(clone, 'z-index', '0');

        clone.querySelectorAll('.btn-snapshot, .btn-capture-all').forEach(btn => {
            btn.remove();
        });

        clone.querySelectorAll('.loan-section-header').forEach(header => {
            setCaptureStyle(header, 'width', `${fullWidth}px`);
            setCaptureStyle(header, 'max-width', 'none');
            setCaptureStyle(header, 'height', 'auto');
            setCaptureStyle(header, 'max-height', 'none');
            setCaptureStyle(header, 'overflow', 'visible');
        });

        clone.querySelectorAll('.loan-summary-table-wrap').forEach(wrap => {
            const table = wrap.querySelector('.loan-summary-table, table');
            const tableWidth = Math.ceil(Math.max(fullWidth, wrap.scrollWidth || 0, table?.scrollWidth || 0));

            wrap.classList.remove('table-responsive');
            setCaptureStyle(wrap, 'overflow', 'visible');
            setCaptureStyle(wrap, 'overflow-x', 'visible');
            setCaptureStyle(wrap, 'overflow-y', 'visible');
            setCaptureStyle(wrap, 'width', `${tableWidth}px`);
            setCaptureStyle(wrap, 'max-width', 'none');
            setCaptureStyle(wrap, 'height', 'auto');
            setCaptureStyle(wrap, 'max-height', 'none');

            if (table) {
                setCaptureStyle(table, 'width', `${tableWidth}px`);
                setCaptureStyle(table, 'min-width', `${tableWidth}px`);
                setCaptureStyle(table, 'max-width', 'none');
                setCaptureStyle(table, 'height', 'auto');
                setCaptureStyle(table, 'max-height', 'none');
                setCaptureStyle(table, 'overflow', 'visible');
            }
        });

        captureHost.appendChild(clone);
        document.body.appendChild(captureHost);

        const captureWidth = Math.ceil(Math.max(fullWidth, clone.scrollWidth || 0));
        const captureHeight = getFullCaptureHeight(clone);

        return { clone, host: captureHost, width: captureWidth, height: captureHeight };
    }

    async function renderLoanSectionCanvas(section) {
        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }

        const prepared = prepareLoanCaptureElement(section);

        try {
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

            return await html2canvas(prepared.clone, {
                scale: getCaptureScale(prepared.width, prepared.height),
                backgroundColor: '#ffffff',
                logging: false,
                useCORS: true,
                width: prepared.width,
                height: prepared.height,
                windowWidth: prepared.width,
                windowHeight: prepared.height,
                scrollX: 0,
                scrollY: 0
            });
        } finally {
            prepared.host.remove();
        }
    }

    function downloadCanvasAsPng(canvas, filename) {
        return new Promise(resolve => {
            canvas.toBlob(blob => {
                if (!blob) {
                    const fallback = document.createElement('a');
                    fallback.download = filename;
                    fallback.href = canvas.toDataURL('image/png');
                    fallback.click();
                    resolve();
                    return;
                }

                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.download = filename;
                link.href = url;
                link.click();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
                resolve();
            }, 'image/png');
        });
    }

    function resetCaptureBackdrop() {
        if (!window.jQuery) return;

        window.jQuery(captureModal).modal('hide');
        window.jQuery('.modal-backdrop').remove();
        window.jQuery('body').removeClass('modal-open').css('padding-right', '');
    }

    function closeCaptureModalSoon(delay = 900) {
        window.setTimeout(resetCaptureBackdrop, delay);
    }

    function formatRkaGapCell(gapValue, pctValue, type) {
        return `
            <div class="rka-gap-stack">
                <div class="rka-gap-stack__amount ${getConditionalClass(gapValue, type, true)}">${formatCurrency(gapValue)}</div>
                ${formatPctBadge(pctValue, type)}
            </div>
        `;
    }

    // --- Select2 Initialization ---
    function initSelect2() {
        if (window.jQuery && window.jQuery.fn.select2) {
            window.jQuery('.select2').each(function () {
                const $el = window.jQuery(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
                $el.select2({ 
                    theme: 'bootstrap4', 
                    width: '100%',
                    dropdownAutoWidth: true
                });
            });
        }
    }
    initSelect2();

    // --- Capture & Export Logic ---
    if (captureAllBtn) {
        captureAllBtn.addEventListener('click', async function() {
            if (typeof html2canvas === 'undefined') {
                alert('Library html2canvas belum dimuat.');
                return;
            }

            const progressText = document.querySelector('#captureProgressUI p');
            captureAllBtn.disabled = true;
            captureAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> EXPORTING...';

            if (window.jQuery) {
                window.jQuery(captureModal).modal({ backdrop: 'static', keyboard: false, show: true });
                document.getElementById('captureProgressUI').classList.remove('d-none');
                document.getElementById('captureErrorUI').classList.add('d-none');
                document.getElementById('captureSuccessUI').classList.add('d-none');
            }

            try {
                const sections = [];
                if (!consolidationSection.classList.contains('d-none')) {
                    sections.push({ id: 'consolidationSection', code: 'CONS', label: 'Konsolidasi Area 6' });
                }
                sections.push(
                    { id: 'osSection', code: 'OS', label: 'Outstanding' },
                    { id: 'smlSection', code: 'SML', label: 'Special Mention' },
                    { id: 'nplSection', code: 'NPL', label: 'Non-Performing' }
                );
                
                const dateStr = $periodeSel.find('option:selected').text().trim().replace(/ /g, '-');
                const kategoriStr = $kategoriSel.val().trim().toUpperCase();

                for (const [index, sec] of sections.entries()) {
                    if (progressText) progressText.innerText = `Memproses ${sec.label} (${index + 1}/${sections.length})...`;
                    await new Promise(r => setTimeout(r, 600));

                    const el = document.getElementById(sec.id);
                    if (!el) continue;

                    const tableCanvas = await renderLoanSectionCanvas(el);

                    const finalCanvas = document.createElement('canvas');
                    const headerHeight = Math.round(220 * getCaptureScale(tableCanvas.width, tableCanvas.height));
                    finalCanvas.width = tableCanvas.width;
                    finalCanvas.height = tableCanvas.height + headerHeight;
                    const ctx = finalCanvas.getContext('2d');

                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);

                    const scaleFactor = tableCanvas.width / 2400;
                    ctx.fillStyle = '#0857c3';
                    ctx.fillRect(0, 0, finalCanvas.width, 15 * scaleFactor);

                    ctx.fillStyle = '#0f172a';
                    ctx.font = `bold ${56 * scaleFactor}px "Inter", sans-serif`;
                    ctx.fillText('Dashboard Pinjaman Kredit', 40 * scaleFactor, 80 * scaleFactor);

                    ctx.fillStyle = '#64748b';
                    ctx.font = `600 ${28 * scaleFactor}px "Inter", sans-serif`;
                    const headerInfo = `Periode: ${$periodeSel.find('option:selected').text()} | Kategori: ${$kategoriSel.val()} | ${sec.label}`;
                    ctx.fillText(headerInfo, 40 * scaleFactor, 130 * scaleFactor);

                    ctx.strokeStyle = '#e2e8f0';
                    ctx.lineWidth = 2 * scaleFactor;
                    ctx.beginPath();
                    ctx.moveTo(40 * scaleFactor, 170 * scaleFactor);
                    ctx.lineTo(finalCanvas.width - (40 * scaleFactor), 170 * scaleFactor);
                    ctx.stroke();

                    ctx.drawImage(tableCanvas, 0, headerHeight);

                    await downloadCanvasAsPng(finalCanvas, `Capture_DashboardKredit_${sec.code}_${kategoriStr}-${dateStr}.png`);
                    
                    await new Promise(r => setTimeout(r, 300));
                }

                document.getElementById('captureProgressUI').classList.add('d-none');
                document.getElementById('captureSuccessUI').classList.remove('d-none');
                closeCaptureModalSoon();
            } catch (err) {
                console.error('Capture process failed:', err);
                document.getElementById('captureProgressUI').classList.add('d-none');
                document.getElementById('captureErrorUI').classList.remove('d-none');
                closeCaptureModalSoon(1600);
            } finally {
                captureAllBtn.disabled = false;
                captureAllBtn.innerHTML = '<i class="fas fa-file-image"></i> EXPORT A4 PORTRAIT';
            }
        });
    }

    window.captureLoanSection = async function(sectionId, title) {
        if (typeof html2canvas === 'undefined') return;
        
        const el = document.getElementById(sectionId);
        if (!el) return;

        const dateStr = $periodeSel.find('option:selected').text().trim().replace(/ /g, '-');
        const kategoriStr = $kategoriSel.val().trim().toUpperCase();
        const sectionCode = sectionId.replace('Section', '').toUpperCase();

        if (window.jQuery) {
            window.jQuery(captureModal).modal({ backdrop: 'static', show: true });
            document.getElementById('captureProgressUI').classList.remove('d-none');
            document.getElementById('captureErrorUI').classList.add('d-none');
            document.getElementById('captureSuccessUI').classList.add('d-none');
        }

        try {
            const canvas = await renderLoanSectionCanvas(el);
            await downloadCanvasAsPng(canvas, `Capture_DashboardKredit_${sectionCode}_${kategoriStr}-${dateStr}.png`);

            document.getElementById('captureProgressUI').classList.add('d-none');
            document.getElementById('captureSuccessUI').classList.remove('d-none');
            closeCaptureModalSoon();
        } catch (err) {
            document.getElementById('captureProgressUI').classList.add('d-none');
            document.getElementById('captureErrorUI').classList.remove('d-none');
            closeCaptureModalSoon(1600);
        }
    };

    // Fix for "blackout"
    document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (window.jQuery) {
                resetCaptureBackdrop();
            }
        });
    });

    // --- Table Building Logic ---
    function buildConsolidationTable(osData, smlData, nplData, headerDates, segmentName, rkaLabels) {
        if (!osData || !smlData || !nplData) return '';

        const dates = {
            ytd: formatDate(headerDates.ytd),
            m2: formatDate(headerDates.m2),
            mtm: formatDate(headerDates.mtm),
            mtd: formatDate(headerDates.mtd),
            selected: formatDate(headerDates.selected)
        };

        const osTotal = osData.find(r => r.is_total);
        const smlTotal = smlData.find(r => r.is_total);
        const nplTotal = nplData.find(r => r.is_total);

        // Helper to consolidate categories
        const getCategoryConsolidation = (rows, type) => {
            const categories = {};
            rows.filter(r => !r.is_total).forEach(r => {
                if (!categories[r.category]) {
                    categories[r.category] = {
                        label: r.category, ytd: 0, m2: 0, mtm: 0, mtd: 0, selected: 0,
                        delta_ytd: 0, delta_mom: 0, delta_mtd: 0,
                        rka_m1: 0, rka_current: 0, penc_m1_rp: 0, penc_cur_rp: 0,
                        penc_m1_pct: 0, penc_cur_pct: 0
                    };
                }
                const c = categories[r.category];
                c.ytd += parseFloat(r.ytd || 0);
                c.m2 += parseFloat(r.m2 || 0);
                c.mtm += parseFloat(r.mtm || 0);
                c.mtd += parseFloat(r.mtd || 0);
                c.selected += parseFloat(r.selected || 0);
                c.delta_ytd += parseFloat(r.delta_ytd || 0);
                c.delta_mom += parseFloat(r.delta_mom || 0);
                c.delta_mtd += parseFloat(r.delta_mtd || 0);
                c.rka_m1 += parseFloat(r.rka_m1 || 0);
                c.rka_current += parseFloat(r.rka_current || 0);
                c.penc_m1_rp += parseFloat(r.penc_m1_rp || 0);
                c.penc_cur_rp += parseFloat(r.penc_cur_rp || 0);
            });
            // Recalculate percentages
            Object.values(categories).forEach(c => {
                c.penc_m1_pct = calculateRkaPercentage(c.selected, c.rka_m1, type);
                c.penc_cur_pct = calculateRkaPercentage(c.selected, c.rka_current, type);
            });
            return Object.values(categories);
        };

        const renderRow = (label, d, type, isBold = false, isSubRow = false) => {
            if (!d) return '';
            const labelStyle = isBold ? 'font-weight: 800; color: #0857c3;' : (isSubRow ? 'font-weight: 600; padding-left: 2rem; font-style: italic; color: #64748b; font-size: 0.75rem;' : 'font-weight: 600;');
            const rowStyle = isBold ? 'background: rgba(8, 87, 195, 0.03);' : '';
            return `<tr style="${rowStyle}">
                <td class="text-start-important sticky-cabang-cell sticky-consolidation-cell" style="${labelStyle}">${escapeHtml(label)}</td>
                <td>${formatCurrency(d.ytd)}</td>
                <td>${formatCurrency(d.m2)}</td>
                <td>${formatCurrency(d.mtm)}</td>
                <td>${formatCurrency(d.mtd)}</td>
                <td style="background: ${isBold ? 'rgba(224, 242, 254, 0.3)' : '#f0f7ff'}; color: #003d7c; font-weight: 800;">${formatCurrency(d.selected)}</td>
                <td class="${getPositionDeltaClass(d.delta_ytd, type)}">${formatCurrency(d.delta_ytd)}</td>
                <td class="${getPositionDeltaClass(d.delta_mom, type)}">${formatCurrency(d.delta_mom)}</td>
                <td class="${getPositionDeltaClass(d.delta_mtd, type)}">${formatCurrency(d.delta_mtd)}</td>
                <td>${formatCurrency(d.rka_current)}</td>
                <td>${formatCurrency(d.rka_m1)}</td>
                <td class="${getConditionalClass(d.penc_cur_rp, type, true)}">${formatCurrency(d.penc_cur_rp)}</td>
                <td>${formatPctBadge(d.penc_cur_pct, type)}</td>
                <td class="${getConditionalClass(d.penc_m1_rp, type, true)}">${formatCurrency(d.penc_m1_rp)}</td>
                <td>${formatPctBadge(d.penc_m1_pct, type)}</td>
            </tr>`;
        };

        const rkaM1Label = rkaLabels && rkaLabels.m1 ? rkaLabels.m1 : '';
        const rkaCurrentLabel = rkaLabels && rkaLabels.current ? rkaLabels.current : '';

        let html = `<table class="loan-summary-table">
            <thead>
                <tr>
                    <th rowspan="2" class="sticky-cabang-header sticky-consolidation-header" style="width: 250px;">URAIAN KONSOLIDASI AREA 6</th>
                    <th colspan="5" class="sub-head">PERIODE</th>
                    <th colspan="3" class="accent-head">DELTA (Δ) PERIODE</th>
                    <th colspan="2" class="sub-head">RKAP</th>
                    <th colspan="4" class="accent-head">PENCAPAIAN RKA</th>
                </tr>
                <tr>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(dates.ytd)}<br><small>(YtD)</small></th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(dates.m2)}<br><small>(M-2)</small></th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(dates.mtm)}<br><small>MoM</small></th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(dates.mtd)}<br><small>MTD</small></th>
                    <th class="sub-head" style="background: #004280; width: 90px;">${escapeHtml(dates.selected)}<br><small>(HARI INI)</small></th>
                    <th class="accent-head" style="width: 80px;">YtD</th>
                    <th class="accent-head" style="width: 80px;">MoM</th>
                    <th class="accent-head" style="width: 80px;">MtD</th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(rkaCurrentLabel)}</th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(rkaM1Label)}</th>
                    <th class="accent-head" style="width: 90px;">${escapeHtml(rkaCurrentLabel)} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                    <th class="accent-head" style="width: 90px;">${escapeHtml(rkaM1Label)} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                </tr>
            </thead>
            <tbody>`;

        // A. Outstanding
        html += renderRow('A. OUTSTANDING (OS)', osTotal, 'Outstanding', true);
        if (segmentName === 'Mikro') {
            const osCats = getCategoryConsolidation(osData, 'Outstanding').filter(c => c.label !== 'Micro');
            osCats.forEach(c => {
                html += renderRow(c.label, c, 'Outstanding', false, true);
            });
        }

        // B. SML
        html += renderRow('B. SPECIAL MENTION LOAN (SML)', smlTotal, 'SML', true);
        if (segmentName === 'Mikro') {
            const smlCats = getCategoryConsolidation(smlData, 'SML').filter(c => c.label !== 'Micro');
            smlCats.forEach(c => {
                html += renderRow(c.label, c, 'SML', false, true);
            });
        }

        // C. NPL
        html += renderRow('C. NON-PERFORMING LOAN (NPL)', nplTotal, 'NPL', true);
        if (segmentName === 'Mikro') {
            const nplCats = getCategoryConsolidation(nplData, 'NPL').filter(c => c.label !== 'Micro');
            nplCats.forEach(c => {
                html += renderRow(c.label, c, 'NPL', false, true);
            });
        }

        html += '</tbody></table>';
        return '<div class="loan-table-container">' + html + '</div>';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildTable(data, headerDates, typeLabel, segmentName, rkaLabels, displayOptions = {}) {
        if (!data || data.length === 0 || (data.length === 1 && data[0].is_total && data[0].selected == 0)) {
            return '<div class="text-center py-5 text-muted">Tidak ada data untuk filter ini.</div>';
        }

        const showMom = displayOptions.show_mom !== false;

        const dates = {
            ytd: formatDate(headerDates.ytd),
            m2: formatDate(headerDates.m2),
            mtm: formatDate(headerDates.mtm),
            mtd: formatDate(headerDates.mtd),
            selected: formatDate(headerDates.selected)
        };
        const typePrefix = typeLabel.toUpperCase();
        const rkaM1Label = rkaLabels && rkaLabels.m1 ? rkaLabels.m1 : '';
        const rkaCurrentLabel = rkaLabels && rkaLabels.current ? rkaLabels.current : '';
        const totalRow = data.find(row => row.is_total);
        const dataRows = data.filter(row => !row.is_total);
        const scopeHeaderLabel = dataRows.some(row => row.scope_level === 'unit') ? 'KCP / UNIT KERJA' : 'KANTOR CABANG';

        let html = `<table class="loan-summary-table">
            <thead>
                <tr>
                    <th rowspan="2" class="sticky-cabang-header" style="width: 160px;">${escapeHtml(scopeHeaderLabel)}</th>
                    <th rowspan="2" class="sticky-kategori-header" style="width: 150px;">KATEGORI ${escapeHtml(segmentName)}</th>
                    <th colspan="${showMom ? 5 : 4}" class="sub-head">${escapeHtml(typePrefix)} PERIODE</th>
                    <th colspan="${showMom ? 3 : 2}" class="accent-head">DELTA (Δ) PERIODE</th>
                    <th colspan="2" class="sub-head">RKAP</th>
                    <th colspan="4" class="accent-head">PENCAPAIAN RKA</th>
                </tr>
                <tr>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(dates.ytd)}<br><small>(YtD)</small></th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(dates.m2)}<br><small>(M-2)</small></th>
                    ${showMom ? `<th class="sub-head" style="width: 85px;">${escapeHtml(dates.mtm)}<br><small>MoM</small></th>` : ''}
                    <th class="sub-head" style="width: 85px;">${escapeHtml(dates.mtd)}<br><small>MTD</small></th>
                    <th class="sub-head" style="background: #004280; width: 90px;">${escapeHtml(dates.selected)}<br><small>(HARI INI)</small></th>
                    <th class="accent-head" style="width: 80px;">YtD</th>
                    ${showMom ? '<th class="accent-head" style="width: 80px;">MoM</th>' : ''}
                    <th class="accent-head" style="width: 80px;">MtD</th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(rkaCurrentLabel)}</th>
                    <th class="sub-head" style="width: 85px;">${escapeHtml(rkaM1Label)}</th>
                    <th class="accent-head" style="width: 90px;">${escapeHtml(rkaCurrentLabel)} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                    <th class="accent-head" style="width: 90px;">${escapeHtml(rkaM1Label)} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                </tr>
            </thead>
            <tbody>`;

        const groups = {};
        dataRows.forEach(row => {
            const branch = row.branch || 'Unknown';
            if (!groups[branch]) groups[branch] = [];
            groups[branch].push(row);
        });

        let rowIndex = 1;
        Object.keys(groups).forEach(branchName => {
            const groupRows = groups[branchName];
            const subtotal = {
                ytd: 0, m2: 0, mtm: 0, mtd: 0, selected: 0,
                d_ytd: 0, d_mom: 0, d_mtd: 0,
                rka_m1: 0, rka_current: 0,
                penc_m1_rp: 0, penc_cur_rp: 0
            };
            const hasMicroCategory = groupRows.some(row => row.category === 'Micro');
            groupRows.forEach(row => {
                let shouldAdd = true;
                if (segmentName === 'Mikro') {
                    if (hasMicroCategory) {
                        shouldAdd = (row.category !== 'Micro');
                    }
                }
                
                if (shouldAdd) {
                    subtotal.ytd += parseFloat(row.ytd || 0);
                    subtotal.m2 += parseFloat(row.m2 || 0);
                    subtotal.mtm += parseFloat(row.mtm || 0);
                    subtotal.mtd += parseFloat(row.mtd || 0);
                    subtotal.selected += parseFloat(row.selected || 0);
                    subtotal.d_ytd += parseFloat(row.delta_ytd || 0);
                    subtotal.d_mom += parseFloat(row.delta_mom || 0);
                    subtotal.d_mtd += parseFloat(row.delta_mtd || 0);
                    subtotal.rka_m1 += parseFloat(row.rka_m1 || 0);
                    subtotal.rka_current += parseFloat(row.rka_current || 0);
                    subtotal.penc_m1_rp += parseFloat(row.penc_m1_rp || 0);
                    subtotal.penc_cur_rp += parseFloat(row.penc_cur_rp || 0);
                }
            });
            
            const shortBranchName = branchName.replace(/KC Madiun/gi, 'KC MDN').replace(/KC Magetan/gi, 'KC MGT').replace(/KC Ngawi/gi, 'KC NGWI').replace(/KC Ponorogo/gi, 'KC PNRG');
            const sub_m1_pct = calculateRkaPercentage(subtotal.selected, subtotal.rka_m1, typeLabel);
            const sub_cur_pct = calculateRkaPercentage(subtotal.selected, subtotal.rka_current, typeLabel);
            const showBranchSubtotal = segmentName !== 'Mikro';

            if (showBranchSubtotal) {
                html += `<tr class="loan-branch-subtotal">
                    <td rowspan="${groupRows.length + 1}" class="text-center-v text-start-important merged-branch-cell sticky-cabang-cell" style="border-bottom: 2px solid #cbd5e1;">${escapeHtml(branchName)}</td>
                    <td class="text-center-v text-center-important sticky-kategori-cell" style="font-size: 0.68rem; letter-spacing: 0.05em; background: rgba(255,255,255,0.05); font-weight: 900; border-right: 1px solid rgba(255,255,255,0.1);">TOTAL ${escapeHtml(shortBranchName.toUpperCase())}</td>
                    <td>${formatCurrency(subtotal.ytd)}</td>
                    <td>${formatCurrency(subtotal.m2)}</td>
                    ${showMom ? `<td>${formatCurrency(subtotal.mtm)}</td>` : ''}
                    <td>${formatCurrency(subtotal.mtd)}</td>
                    <td style="background: rgba(224, 242, 254, 0.15); color: #7dd3fc;">${formatCurrency(subtotal.selected)}</td>
                    <td class="${getPositionDeltaClass(subtotal.d_ytd, typeLabel)}">${formatCurrency(subtotal.d_ytd)}</td>
                    ${showMom ? `<td class="${getPositionDeltaClass(subtotal.d_mom, typeLabel)}">${formatCurrency(subtotal.d_mom)}</td>` : ''}
                    <td class="${getPositionDeltaClass(subtotal.d_mtd, typeLabel)}">${formatCurrency(subtotal.d_mtd)}</td>
                    <td>${formatCurrency(subtotal.rka_current)}</td>
                    <td>${formatCurrency(subtotal.rka_m1)}</td>
                    <td class="${getConditionalClass(subtotal.penc_cur_rp, typeLabel, true)}">${formatCurrency(subtotal.penc_cur_rp)}</td>
                    <td>${formatPctBadge(sub_cur_pct, typeLabel)}</td>
                    <td class="${getConditionalClass(subtotal.penc_m1_rp, typeLabel, true)}">${formatCurrency(subtotal.penc_m1_rp)}</td>
                    <td>${formatPctBadge(sub_m1_pct, typeLabel)}</td>
                </tr>`;
            }

            groupRows.forEach((row, index) => {
                const isMikroTotalRow = segmentName === 'Mikro' && row.category === 'Micro';
                const categoryLabel = isMikroTotalRow
                    ? 'TOTAL MICRO'
                    : (row.category || '');
                const categoryCellClass = isMikroTotalRow
                    ? 'text-start-important loan-mikro-total-label'
                    : 'text-start-important text-muted';
                const categoryCellStyle = isMikroTotalRow
                    ? 'font-size: 0.82rem; font-weight: 900; letter-spacing: 0.04em; color: #003d7c; background: #dbeafe;'
                    : 'font-size: 0.75rem;';
                const branchCell = !showBranchSubtotal && index === 0
                    ? `<td rowspan="${groupRows.length}" class="text-center-v text-start-important merged-branch-cell sticky-cabang-cell" style="border-bottom: 2px solid #cbd5e1;">${escapeHtml(branchName)}</td>`
                    : '';

                html += `<tr>
                    ${branchCell}
                    <td class="${categoryCellClass} sticky-kategori-cell" style="${categoryCellStyle}">${escapeHtml(categoryLabel)}</td>
                    <td>${formatCurrency(row.ytd)}</td>
                    <td>${formatCurrency(row.m2)}</td>
                    ${showMom ? `<td>${formatCurrency(row.mtm)}</td>` : ''}
                    <td>${formatCurrency(row.mtd)}</td>
                    <td style="background: #f0f7ff; color: #003d7c; font-weight: 800;">${formatCurrency(row.selected)}</td>
                    <td class="${getPositionDeltaClass(row.delta_ytd, typeLabel)}">${formatCurrency(row.delta_ytd)}</td>
                    ${showMom ? `<td class="${getPositionDeltaClass(row.delta_mom, typeLabel)}">${formatCurrency(row.delta_mom)}</td>` : ''}
                    <td class="${getPositionDeltaClass(row.delta_mtd, typeLabel)}">${formatCurrency(row.delta_mtd)}</td>
                    <td>${formatCurrency(row.rka_current)}</td>
                    <td>${formatCurrency(row.rka_m1)}</td>
                    <td class="${getConditionalClass(row.penc_cur_rp, typeLabel, true)}">${formatCurrency(row.penc_cur_rp)}</td>
                    <td>${formatPctBadge(row.penc_cur_pct, typeLabel)}</td>
                    <td class="${getConditionalClass(row.penc_m1_rp, typeLabel, true)}">${formatCurrency(row.penc_m1_rp)}</td>
                    <td>${formatPctBadge(row.penc_m1_pct, typeLabel)}</td>
                </tr>`; 
            });
        });

        if (totalRow) {
            html += `<tr class="loan-grand-total" style="background: #1e293b; color: #ffffff; font-weight: 900;">
                <td colspan="2" class="text-center-important sticky-cabang-cell" style="letter-spacing: 0.1em; color: #ffffff; border-right: 1px solid rgba(255,255,255,0.2); font-weight: 800;">GRAND TOTAL</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.ytd)}</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.m2)}</td>
                ${showMom ? `<td style="color: #ffffff;">${formatCurrency(totalRow.mtm)}</td>` : ''}
                <td style="color: #ffffff;">${formatCurrency(totalRow.mtd)}</td>
                <td style="background: #0f172a; color: #ffffff;">${formatCurrency(totalRow.selected)}</td>
                <td class="${getPositionDeltaClass(totalRow.delta_ytd, typeLabel)}">${formatCurrency(totalRow.delta_ytd)}</td>
                ${showMom ? `<td class="${getPositionDeltaClass(totalRow.delta_mom, typeLabel)}">${formatCurrency(totalRow.delta_mom)}</td>` : ''}
                <td class="${getPositionDeltaClass(totalRow.delta_mtd, typeLabel)}">${formatCurrency(totalRow.delta_mtd)}</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.rka_current)}</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.rka_m1)}</td>
                <td class="${getConditionalClass(totalRow.penc_cur_rp, typeLabel, true)}">${formatCurrency(totalRow.penc_cur_rp)}</td>
                <td>${formatPctBadge(totalRow.penc_cur_pct, typeLabel)}</td>
                <td class="${getConditionalClass(totalRow.penc_m1_rp, typeLabel, true)}">${formatCurrency(totalRow.penc_m1_rp)}</td>
                <td>${formatPctBadge(totalRow.penc_m1_pct, typeLabel)}</td>
            </tr>`;
        }

        return '<div class="loan-table-container">' + html + '</tbody></table></div>';
    }

    function showSpinners(kategori) {
        const stub = (label) => `<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm text-primary mb-3" role="status"><span class="sr-only">Loading...</span></div><p><strong>Memproses data ${escapeHtml(label)}</strong></p><p style="font-size: 0.85rem;">Untuk Segmen ${escapeHtml(kategori)}...</p></div>`;
        osTableContainer.innerHTML = stub('Outstanding');
        consolidationSection.classList.add('d-none');
        consolidationTableContainer.innerHTML = '';
        smlTableContainer.innerHTML = stub('SML');
        nplTableContainer.innerHTML = stub('NPL');
    }

    function showErrorMessage(message) {
        const errorHtml = `<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Gagal memuat data</strong><br>${escapeHtml(message)}<button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>`;
        osTableContainer.innerHTML = errorHtml; 
        smlTableContainer.innerHTML = errorHtml; 
        nplTableContainer.innerHTML = errorHtml;
    }

    // --- Data Loading Logic ---
    function loadDashboardData() {
        const periode = $periodeSel.val();
        const kategori = $kategoriSel.val();
        const kanca = $kancaSel.val();
        const kancaLabel = $kancaSel.find('option:selected').text();
        if (!periode) return;

        showSpinners(kategori);
        dashboardMeta.textContent = `Memuat data dashboard ${kancaLabel} untuk periode ${formatDate(periode)}...`;
        btnLoadData.disabled = true;

        const canAbortRequest = typeof window.AbortController === 'function';
        if (requestAbortController && canAbortRequest) requestAbortController.abort();
        requestAbortController = canAbortRequest ? new AbortController() : null;
        const controller = requestAbortController;
        const timeoutId = canAbortRequest
            ? setTimeout(() => { if (controller === requestAbortController) controller.abort(); }, REQUEST_TIMEOUT)
            : null;

        const url = '{{ route("report.dashboard-pinjaman.kredit.data") }}?periode=' + encodeURIComponent(periode) + '&kategori=' + encodeURIComponent(kategori) + '&kanca=' + encodeURIComponent(kanca);
        const fetchOptions = controller ? { signal: controller.signal } : {};
        
        fetch(url, fetchOptions)
            .then(response => {
                if (timeoutId) clearTimeout(timeoutId);
                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                const payloadKancaLabel = data.kanca_label || kancaLabel;
                dashboardMeta.textContent = `Menampilkan dashboard kredit ${kategori} - ${payloadKancaLabel} per ${formatDate(periode)}.`;
                
                consolidationSection.classList.add('d-none');
                consolidationTableContainer.innerHTML = '';

                document.getElementById('osTitle').innerText = `A. OUTSTANDING (OS) - ${kategori}`;
                osTableContainer.innerHTML = buildTable(data.os, data.header_dates, 'Outstanding', kategori, data.rka_labels, data.display_options);
                document.getElementById('smlTitle').innerText = `B. SPECIAL MENTION LOAN (SML) - ${kategori}`;
                smlTableContainer.innerHTML = buildTable(data.sml, data.header_dates, 'SML', kategori, data.rka_labels, data.display_options);
                document.getElementById('nplTitle').innerText = `C. NON-PERFORMING LOAN (NPL) - ${kategori}`;
                nplTableContainer.innerHTML = buildTable(data.npl, data.header_dates, 'NPL', kategori, data.rka_labels, data.display_options);
                syncSummaryTableHeaders();
                scheduleSummaryTableSync();
            })
            .catch(error => {
                if (timeoutId) clearTimeout(timeoutId);
                console.error('Error:', error);
                let errorMsg = 'Periksa koneksi atau snapshot data.';
                if (error.name === 'AbortError') errorMsg = 'Permintaan timeout. Coba lagi.';
                showErrorMessage(errorMsg);
            })
            .finally(() => {
                btnLoadData.disabled = false;
            });
    }

    function syncSummaryTableHeaders() {
        document.querySelectorAll('.loan-summary-table-wrap').forEach(wrap => {
            const table = wrap.querySelector('.loan-summary-table');
            const row1Cell = table?.querySelector('thead tr:first-child th:not([rowspan])');
            if (row1Cell) {
                const h = row1Cell.offsetHeight;
                if (h > 0) wrap.style.setProperty('--loan-summary-header-row-height', h + 'px');
            }

            const cabangHeader = table?.querySelector('thead th.sticky-cabang-header');
            const kategoriHeader = table?.querySelector('thead th.sticky-kategori-header');
            if (!cabangHeader || !kategoriHeader) return;

            // The second frozen column must use the rendered width of the first one.
            // This prevents overlap when labels, zoom, or device width change the table layout.
            const cabangWidth = Math.ceil(cabangHeader.getBoundingClientRect().width);
            const kategoriWidth = Math.ceil(kategoriHeader.getBoundingClientRect().width);

            if (cabangWidth > 0) {
                table.style.setProperty('--loan-sticky-cabang-width', `${cabangWidth}px`);
            }

            if (kategoriWidth > 0) {
                table.style.setProperty('--loan-sticky-kategori-width', `${kategoriWidth}px`);
            }

            const wrapLeft = wrap.getBoundingClientRect().left;
            const renderedCabang = cabangHeader.getBoundingClientRect();
            const renderedKategori = kategoriHeader.getBoundingClientRect();
            const kategoriLeft = renderedCabang.right - wrapLeft;
            const frozenTotalWidth = renderedKategori.right - wrapLeft;

            if (kategoriLeft > 0) {
                table.style.setProperty('--loan-sticky-kategori-left', `${kategoriLeft}px`);
            }

            if (frozenTotalWidth > 0) {
                table.style.setProperty('--loan-sticky-total-width', `${frozenTotalWidth}px`);
            }
        });
    }

    let summaryTableSyncFrame = null;
    function scheduleSummaryTableSync() {
        if (summaryTableSyncFrame) cancelAnimationFrame(summaryTableSyncFrame);
        summaryTableSyncFrame = requestAnimationFrame(() => {
            summaryTableSyncFrame = null;
            syncSummaryTableHeaders();
        });
    }
    window.addEventListener('resize', scheduleSummaryTableSync);
    window.addEventListener('orientationchange', scheduleSummaryTableSync);

    btnLoadData.addEventListener('click', loadDashboardData);
    
    // --- Modern Dropdown Logic ---
    function initModernSelectors() {
        document.querySelectorAll('.loan-dropdown-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const parent = btn.closest('.loan-dropdown');
                const isOpen = parent.classList.contains('is-open');
                document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                if (!isOpen) parent.classList.add('is-open');
            });
        });

        document.querySelectorAll('.loan-dropdown-option').forEach(opt => {
            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                const parent = opt.closest('.loan-dropdown');
                const select = parent.querySelector('select');
                const textSpan = parent.querySelector('.loan-dropdown-text');
                const val = opt.dataset.value;

                select.value = val;
                textSpan.textContent = opt.querySelector('span').textContent;
                
                parent.querySelectorAll('.loan-dropdown-option').forEach(o => o.classList.remove('is-active'));
                opt.classList.add('is-active');
                parent.classList.remove('is-open');
                
                $(select).trigger('change');
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
        });

        // Sync initial text
        document.querySelectorAll('.loan-dropdown').forEach(d => {
            const select = d.querySelector('select');
            const textSpan = d.querySelector('.loan-dropdown-text');
            if (select && select.selectedIndex >= 0) {
                textSpan.textContent = select.options[select.selectedIndex].text;
            }
        });
    }

    initModernSelectors();

    // Initial load
    if ($periodeSel.val()) {
        loadDashboardData();
    }
});
</script>
@endpush

@endsection
