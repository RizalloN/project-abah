@extends('layouts.admin')

@section('styles')
<style>
    :root {
        --bri-blue-dark: #004685;
        --bri-blue-main: #00529C;
        --primary-blue: #1e40af; /* blue-800 */
        --primary-blue-light: #3b82f6; /* blue-500 */
        --primary-blue-dark: #1e3a8a; /* blue-900 */
        --surface-color: #ffffff;
        --bg-color: #f8fafc; /* slate-50 */
        --border-color: #dbe5ef; /* harmonized with loan-dashboard */
        --text-main: #0f172a; /* slate-900 */
        --text-muted: #64748b; /* slate-500 */
        --loan-blue-soft: #eaf2ff;
        
        --table-header-bg: var(--bri-blue-dark);
        --table-header-text: #ffffff;
        
        --daily-no-width: 60px;
        --daily-label-width: 280px;
        --daily-position-width: 110px;
        --daily-delta-width: 100px;
        --daily-rka-width: 120px;
    }

    .daily-dashboard {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    /* Surface & Cards */
    .daily-surface {
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 18px; /* Formal rounded edge */
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22); /* Deeper, softer shadow */
        overflow: visible;
        transition: box-shadow 0.3s ease;
        width: 100%;
        max-width: 100%;
        position: relative;
    }

    /* Table Wrapper */
    .daily-table-region {
        --daily-table-sticky-top: 0px;
        position: relative;
        width: 100%;
        overflow: visible;
        z-index: 1;
    }

    .daily-panel-head {
        background: linear-gradient(to right, #f8fafc, #ffffff);
        border-bottom: 1px solid var(--border-color);
    }

    .daily-panel-title {
        margin: 0;
        font-size: clamp(1.7rem, 2.7vw, 2.5rem);
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.01em;
        margin-bottom: 0.45rem;
    }

    .daily-panel-desc {
        margin: 0.5rem 0 0;
        color: var(--text-muted);
        font-size: 0.9rem;
        line-height: 1.6;
    }

    /* Chips & Badges */
    .daily-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 9999px;
        padding: 0.35rem 0.85rem;
        background: linear-gradient(135deg, #eef5ff, #f8fbff);
        border: 1px solid #cfe0f8;
        color: var(--bri-blue-main);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .daily-scope-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 8px;
        padding: 0.4rem 0.75rem;
        background: #f1f5f9; /* slate-100 */
        border: 1px solid var(--border-color);
        color: var(--text-main);
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    .daily-scope-chip:hover {
        background: linear-gradient(135deg, #eaf2ff, #f5f9ff);
    }

    /* Typography */
    .daily-filter-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #4b6285;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .daily-filter-shell {
        position: relative;
        z-index: 25;
        margin-bottom: 0.75rem;
        padding: 1rem 1.1rem 0.95rem;
        background:
            linear-gradient(180deg, rgba(236, 243, 255, 0.98) 0%, rgba(255, 255, 255, 0.98) 72%),
            #ffffff;
        border-bottom: 1px solid rgba(219, 229, 239, 0.9);
    }

    .daily-filter-shell::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(0, 82, 156, 0.06), rgba(255, 255, 255, 0) 25%, rgba(255, 255, 255, 0) 75%, rgba(0, 82, 156, 0.06));
        pointer-events: none;
    }

    .daily-filter-grid {
        position: relative;
        z-index: 1;
    }

    .daily-filter-card {
        --daily-filter-accent: var(--bri-blue-main);
        --daily-filter-tint: rgba(0, 82, 156, 0.03);
        --daily-filter-badge-bg: var(--loan-blue-soft);
        --daily-filter-badge-border: #d7e6fb;
        height: 100%;
        min-height: 118px;
        padding: 0.75rem 0.8rem 0.72rem;
        border: 1px solid rgba(219, 229, 239, 0.95);
        border-radius: 14px;
        background:
            linear-gradient(180deg, rgba(235, 243, 255, 0.98) 0%, rgba(255, 255, 255, 0.98) 76%),
            rgba(255, 255, 255, 0.92);
        box-shadow: 0 14px 28px -26px rgba(15, 23, 42, 0.24);
        backdrop-filter: blur(8px);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        overflow: visible;
        position: relative;
    }

    .daily-filter-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--daily-filter-accent), rgba(143, 180, 255, 0.72), rgba(255, 255, 255, 0.95));
        opacity: 0.85;
    }

    .daily-filter-card:hover {
        transform: translateY(-1px);
        border-color: rgba(0, 82, 156, 0.18);
        box-shadow: 0 16px 30px -28px rgba(0, 82, 156, 0.22);
        z-index: 5;
    }

    .daily-filter-card--action {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: stretch;
        background:
            linear-gradient(180deg, rgba(234, 242, 255, 0.98), rgba(255, 255, 255, 0.98)),
            rgba(255, 255, 255, 0.96);
    }

    .daily-filter-card--kanca {
        --daily-filter-accent: #00529C;
        --daily-filter-tint: rgba(0, 82, 156, 0.03);
        --daily-filter-badge-bg: #edf5ff;
        --daily-filter-badge-border: #d6e5fb;
    }

    .daily-filter-card--unit {
        --daily-filter-accent: #004685;
        --daily-filter-tint: rgba(0, 70, 133, 0.03);
        --daily-filter-badge-bg: #eaf2ff;
        --daily-filter-badge-border: #d6e5fb;
    }

    .daily-filter-card--posisi {
        --daily-filter-accent: #1e40af;
        --daily-filter-tint: rgba(30, 64, 175, 0.03);
        --daily-filter-badge-bg: #eef4ff;
        --daily-filter-badge-border: #dbe5ff;
    }

    .daily-filter-card--rka {
        --daily-filter-accent: #0f4c97;
        --daily-filter-tint: rgba(15, 76, 151, 0.03);
        --daily-filter-badge-bg: #edf5ff;
        --daily-filter-badge-border: #d6e5fb;
    }

    .daily-filter-card--action {
        --daily-filter-accent: #0f4c97;
        --daily-filter-tint: rgba(15, 76, 151, 0.03);
        --daily-filter-badge-bg: #edf5ff;
        --daily-filter-badge-border: #d6e5fb;
    }

    .daily-filter-content {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        position: relative;
        z-index: 2;
    }

    .daily-filter-control {
        position: relative;
        margin-top: 0.25rem;
        z-index: 6;
    }

    .daily-filter-control-icon {
        position: absolute;
        left: 0.95rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        text-align: center;
        color: #5b78a8;
        font-size: 0.78rem;
        pointer-events: none;
        z-index: 2;
        transition: color 0.2s ease;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 999px;
        background: var(--daily-filter-badge-bg);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: inset 0 0 0 1px var(--daily-filter-badge-border);
    }

    .daily-filter-control:focus-within .daily-filter-control-icon,
    .daily-filter-card:hover .daily-filter-control-icon {
        color: var(--daily-filter-accent);
    }

    .daily-filter-select {
        width: 100%;
        min-height: 40px;
        border: 1px solid rgba(198, 214, 236, 0.95);
        border-radius: 12px;
        background:
            linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        color: #334155;
        font-size: 0.88rem;
        font-weight: 600;
        padding: 0.55rem 2.45rem 0.55rem 2.35rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.94), 0 8px 18px -20px rgba(15, 23, 42, 0.18);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        cursor: pointer;
    }

    .daily-filter-select:hover,
    .daily-dropdown-toggle:hover {
        border-color: rgba(0, 82, 156, 0.24);
        background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
        box-shadow: 0 12px 24px -24px rgba(0, 82, 156, 0.18);
    }

    .daily-filter-select:focus {
        outline: none;
        border-color: var(--bri-blue-main);
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14), 0 12px 22px -22px rgba(0, 70, 133, 0.16);
        background: #ffffff;
    }

    .daily-filter-select:disabled {
        cursor: not-allowed;
        color: var(--loan-muted);
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        box-shadow: none;
    }

    .daily-filter-chevron {
        position: absolute;
        right: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #7b93b6;
        font-size: 0.72rem;
        pointer-events: none;
        transition: transform 0.2s ease, color 0.2s ease;
    }

    .daily-filter-control:focus-within .daily-filter-chevron,
    .daily-filter-card:hover .daily-filter-chevron {
        color: var(--daily-filter-accent);
    }

    /* KPIs */
    .daily-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
    }

    .daily-kpi {
        background: linear-gradient(180deg, #ffffff, #fcfdfd);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px -8px rgba(15, 23, 42, 0.1);
        position: relative;
        overflow: hidden;
    }

    .daily-kpi::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        width: 4px;
        background: var(--primary-blue-light);
        border-radius: 4px 0 0 4px;
    }

    /* Removed hover style because we use .hover-lift class */

    .daily-kpi .label {
        font-size: 0.84rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.75rem;
    }

    .daily-kpi .value {
        font-size: clamp(1.6rem, 2vw, 1.8rem);
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.2;
    }

    /* Form Controls */
    .select2-container--default .select2-selection--single {
        border: 1px solid var(--border-color) !important;
        border-radius: 6px !important;
        height: 32px !important;
        display: flex;
        align-items: center;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background: #ffffff !important;
    }
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--bri-blue-main) !important;
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #475569 !important;
        font-size: 0.85rem;
        font-weight: 500;
        line-height: normal !important;
        padding-left: 8px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 32px !important;
    }

    .daily-filter-native {
        display: none !important;
    }

    .daily-dropdown {
        position: relative;
        z-index: 18;
    }

    .daily-dropdown-toggle {
        width: 100%;
        min-height: 40px;
        border: 1px solid rgba(203, 213, 225, 0.9);
        border-radius: 12px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        color: #3d4c63;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.55rem 0.85rem 0.55rem 2.35rem;
        font-weight: 600;
        font-size: 0.88rem;
        text-align: left;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.94), 0 8px 18px -20px rgba(15, 23, 42, 0.18);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
    }

    .daily-dropdown.is-open .daily-dropdown-toggle,
    .daily-dropdown-toggle:focus {
        outline: none;
        border-color: var(--bri-blue-main);
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14), 0 12px 22px -22px rgba(0, 70, 133, 0.18);
        background: #ffffff;
    }

    .daily-dropdown-toggle-text {
        flex: 1 1 auto;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .daily-dropdown-toggle-icon {
        color: #5b78a8;
        font-size: 0.72rem;
        transition: transform 0.2s ease, color 0.2s ease;
    }

    .daily-dropdown.is-open .daily-dropdown-toggle-icon {
        transform: rotate(180deg);
        color: var(--bri-blue-main);
    }

    .daily-dropdown-menu {
        position: absolute;
        top: calc(100% + 0.45rem);
        left: 0;
        right: 0;
        z-index: 60;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid rgba(219, 229, 239, 0.95);
        border-radius: 14px;
        box-shadow: 0 20px 34px -28px rgba(0, 70, 133, 0.22);
        padding: 0.45rem;
        display: none;
        max-height: 260px;
        overflow-y: auto;
        backdrop-filter: blur(10px);
    }

    .daily-dropdown.is-open .daily-dropdown-menu {
        display: block;
    }

    .daily-dropdown.is-open {
        z-index: 80;
    }

    .daily-dropdown-option {
        width: 100%;
        border: none;
        background: transparent;
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.62rem 0.72rem;
        border-radius: 10px;
        color: var(--text-main);
        text-align: left;
        font-size: 0.86rem;
        transition: background-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }

    .daily-dropdown-option:hover {
        background: linear-gradient(135deg, #edf5ff, #f8fbff);
        color: var(--bri-blue-dark);
        transform: translateX(2px);
    }

    .daily-dropdown-option.is-active {
        background: linear-gradient(135deg, #dbeafe, #edf5ff);
        color: var(--bri-blue-dark);
        font-weight: 600;
    }

    .daily-dropdown-check {
        width: 15px;
        height: 15px;
        border-radius: 4px;
        border: 1px solid #c8daf5;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: #ffffff;
        color: transparent;
        font-size: 0.72rem;
    }

    .daily-dropdown-option.is-active .daily-dropdown-check {
        background: var(--bri-blue-main);
        border-color: var(--bri-blue-main);
        color: #ffffff;
    }

    .daily-dropdown-label {
        flex: 1 1 auto;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .daily-dropdown-empty {
        padding: 0.8rem 0.9rem;
        color: var(--text-muted);
        font-size: 0.88rem;
        border-radius: 12px;
        background: linear-gradient(180deg, #f8fbff, #f5f9ff);
    }

    .daily-apply-button {
        width: 100%;
        min-height: 40px;
        padding: 0.58rem 0.95rem;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--bri-blue-dark) 0%, var(--bri-blue-main) 58%, #1d4ed8 100%);
        color: #ffffff;
        font-size: 0.88rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        box-shadow: 0 14px 24px -18px rgba(0, 82, 156, 0.48);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .daily-apply-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 28px -18px rgba(0, 82, 156, 0.54);
        filter: saturate(1.05);
        color: #ffffff;
    }

    .daily-apply-button:focus {
        outline: none;
        color: #ffffff;
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14), 0 14px 24px -18px rgba(0, 82, 156, 0.5);
    }

    .daily-apply-button:disabled {
        cursor: wait;
        opacity: 0.86;
        transform: none;
        box-shadow: 0 12px 18px -16px rgba(0, 82, 156, 0.45);
    }

    .daily-filter-note {
        margin-top: 0.2rem;
        color: var(--text-muted);
        font-size: 0.72rem;
        line-height: 1.4;
        min-height: 1.9rem;
        max-width: 28ch;
    }

    .daily-filter-action-body {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        position: relative;
        z-index: 1;
        justify-content: flex-start;
    }

    .daily-filter-card .daily-filter-label {
        margin-bottom: 0.35rem;
        min-height: 16px;
        font-size: 0.7rem;
        letter-spacing: 0.06em;
        color: #4b6285;
    }

    .daily-filter-card--action .daily-filter-label {
        margin-bottom: 0.55rem;
    }

    .daily-filter-card--action .daily-apply-button {
        margin-top: 0.25rem;
    }

    .daily-filter-action-button-wrap {
        margin-top: 0.2rem;
    }
    
    .btn-primary {
        background-color: var(--bri-blue-main);
        border-color: var(--bri-blue-main);
        border-radius: 8px;
        font-weight: 600;
        padding: 0.6rem 1.25rem;
        transition: all 0.2s ease;
    }
    .btn-primary:hover {
        background-color: var(--bri-blue-dark);
        border-color: var(--bri-blue-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 82, 156, 0.4);
    }
    .btn-primary:focus {
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.4) !important;
    }

    .daily-table-wrap {
        width: 100%;
        max-height: none;
        height: auto;
        overflow-x: auto; 
        overflow-y: auto; /* Enable native vertical scrolling inside the box */
        border: 1px solid var(--border-color);
        border-radius: 18px;
        box-shadow: 0 10px 24px -12px rgba(15, 23, 42, 0.08);
        position: sticky;
        top: var(--daily-table-sticky-top);
        background: #ffffff;
        z-index: 14;
    }

    .daily-table-wrap::-webkit-scrollbar {
        height: 10px;
    }
    .daily-table-wrap::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 0 0 18px 18px;
    }
    .daily-table-wrap::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 10px;
        border: 2px solid #f8fafc;
    }
    .daily-table-wrap::-webkit-scrollbar-thumb:hover {
        background-color: #94a3b8;
    }

    .daily-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        background: #ffffff;
    }

    .daily-table th, .daily-table td {
        padding: 0.75rem 1rem;
        vertical-align: middle;
        white-space: nowrap;
        border-bottom: 1px solid var(--border-color);
        border-right: 1px solid var(--border-color);
    }
    .daily-table th:last-child, .daily-table td:last-child {
        border-right: none;
    }
    .daily-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Table Headers */
    .daily-table thead {
        position: sticky;
        top: 0;
        z-index: 25;
    }
    .daily-table thead th {
        background: var(--table-header-bg);
        color: var(--table-header-text);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        text-align: center;
        border-bottom: 2px solid rgba(0,0,0,0.1);
        border-right: 1px solid rgba(255,255,255,0.1);
    }
    .daily-table thead tr.column-row th {
        background: #274bba; /* Slightly lighter for sub-headers */
        font-size: 0.65rem;
    }
    .daily-table thead th.rka-period-cell {
        background: var(--table-header-bg);
        color: #ffffff;
        font-size: 0.86rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        border-bottom: 2px solid rgba(0, 0, 0, 0.1);
    }
    .daily-table thead th.rka-period-cell:last-child {
        border-right: none;
    }
    .daily-table thead tr.rka-sub-row th {
        background: #274bba;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }
    .daily-table thead .group-rka {
        background: var(--table-header-bg);
        color: #ffffff;
    }
    .daily-table thead .rka-period-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 100%;
    }
    .daily-table thead .rka-sub-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 100%;
    }
    .daily-table thead .column-heading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 100%;
        text-align: center;
        height: 100%;
        padding: 4px 0;
    }

    /* Table Cells */
    .daily-table tbody td {
        font-size: 0.8rem;
        color: var(--text-main);
        text-align: right; /* Numeric columns usually right aligned */
        font-variant-numeric: tabular-nums;
    }

    /* Specific Column Alignments */
    .daily-table .sticky-no, .daily-table .group-no {
        text-align: center !important;
        width: var(--daily-no-width);
        min-width: var(--daily-no-width);
    }
    .daily-table .sticky-label, .daily-table .group-label {
        text-align: left !important;
        width: var(--daily-label-width);
        min-width: var(--daily-label-width);
        font-weight: 500;
    }

    /* Column Widths */
    .daily-table .position-col {
        width: var(--daily-position-width);
        min-width: var(--daily-position-width);
    }
    .daily-table .delta-col {
        width: var(--daily-delta-width);
        min-width: var(--daily-delta-width);
    }
    .daily-table .rka-col {
        width: var(--daily-rka-width);
        min-width: var(--daily-rka-width);
    }

    .daily-rka-subnote {
        display: block;
        margin-top: 0.2rem;
        font-size: 0.68rem;
        color: var(--text-muted);
        font-weight: 600;
    }

    /* Sticky Columns */
    .daily-table .sticky-no {
        position: sticky;
        left: 0;
        z-index: 10;
        background: #ffffff;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    }
    .daily-table .sticky-label {
        position: sticky;
        left: var(--daily-no-width);
        z-index: 10;
        background: #ffffff;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    }
    
    .daily-table thead .sticky-no, 
    .daily-table thead .sticky-label,
    .daily-table thead .group-label {
        z-index: 15;
        background: var(--table-header-bg);
        box-shadow: none;
        text-align: center !important;
    }

    /* Row Hover and Striping */
    .daily-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .daily-table tbody tr:nth-child(even) > td {
        background-color: #f8fafc; /* slate-50 */
    }
    .daily-table tbody tr:nth-child(even) > .sticky-no,
    .daily-table tbody tr:nth-child(even) > .sticky-label {
        background-color: #f8fafc;
    }
    .daily-table tbody tr:hover {
        background-color: #f1f5f9;
    }
    .daily-table tbody tr:hover .sticky-no,
    .daily-table tbody tr:hover .sticky-label {
        background-color: #f1f5f9 !important;
    }

    /* Hierarchical Rows Styling */
    .daily-table .metric-block-simpanan td,
    .daily-table .metric-block-os td,
    .daily-table .metric-block-sml td,
    .daily-table .metric-block-npl td,
    .daily-table .metric-block-casa td,
    .daily-table .metric-block-ldr td,
    .daily-table .metric-block-recdh td {
        background-color: #e0e7ff; /* blue-100 for parent rows */
        font-weight: 700;
        color: var(--primary-blue-dark);
    }
    .daily-table .metric-block-simpanan .sticky-no,
    .daily-table .metric-block-simpanan .sticky-label,
    .daily-table .metric-block-os .sticky-no,
    .daily-table .metric-block-os .sticky-label,
    .daily-table .metric-block-sml .sticky-no,
    .daily-table .metric-block-sml .sticky-label,
    .daily-table .metric-block-npl .sticky-no,
    .daily-table .metric-block-npl .sticky-label,
    .daily-table .metric-block-casa .sticky-no,
    .daily-table .metric-block-casa .sticky-label,
    .daily-table .metric-block-ldr .sticky-no,
    .daily-table .metric-block-ldr .sticky-label,
    .daily-table .metric-block-recdh .sticky-no,
    .daily-table .metric-block-recdh .sticky-label {
        background-color: #e0e7ff;
    }

    .daily-table tbody tr.metric-block-simpanan:hover td,
    .daily-table tbody tr.metric-block-os:hover td,
    .daily-table tbody tr.metric-block-sml:hover td,
    .daily-table tbody tr.metric-block-npl:hover td,
    .daily-table tbody tr.metric-block-casa:hover td,
    .daily-table tbody tr.metric-block-ldr:hover td,
    .daily-table tbody tr.metric-block-recdh:hover td {
        background-color: #dbeafe; /* blue-200 */
    }
    .daily-table tbody tr.metric-block-simpanan:hover .sticky-no,
    .daily-table tbody tr.metric-block-simpanan:hover .sticky-label,
    .daily-table tbody tr.metric-block-os:hover .sticky-no,
    .daily-table tbody tr.metric-block-os:hover .sticky-label,
    .daily-table tbody tr.metric-block-sml:hover .sticky-no,
    .daily-table tbody tr.metric-block-sml:hover .sticky-label,
    .daily-table tbody tr.metric-block-npl:hover .sticky-no,
    .daily-table tbody tr.metric-block-npl:hover .sticky-label,
    .daily-table tbody tr.metric-block-casa:hover .sticky-no,
    .daily-table tbody tr.metric-block-casa:hover .sticky-label,
    .daily-table tbody tr.metric-block-ldr:hover .sticky-no,
    .daily-table tbody tr.metric-block-ldr:hover .sticky-label,
    .daily-table tbody tr.metric-block-recdh:hover .sticky-no,
    .daily-table tbody tr.metric-block-recdh:hover .sticky-label {
        background-color: #dbeafe;
    }

    .daily-table .row-depth-1 .metric-label { padding-left: 1rem; font-weight: 600; color: var(--text-main); }
    .daily-table .row-depth-2 .metric-label { padding-left: 2rem; color: var(--text-muted); font-weight: 500;}
    .daily-table .row-depth-3 .metric-label { padding-left: 3rem; color: #94a3b8; font-size: 0.75rem; }

    /* Utilities */
    .delta-positive { color: #16a34a !important; font-weight: 700; } /* green-600 */
    .delta-negative { color: #dc2626 !important; font-weight: 700; } /* red-600 */
    
    .daily-empty {
        padding: 3rem;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.95rem;
    }

    .daily-loading {
        position: relative;
        pointer-events: none;
    }
    .daily-loading::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.6);
        backdrop-filter: blur(2px);
        z-index: 20;
    }

    .position-col-hidden {
        display: none !important;
    }

    .header-subnote {
        display: block;
        width: 100%;
        font-size: 0.63rem;
        opacity: 0.9;
        margin-top: 4px;
        padding-top: 4px;
        font-weight: 700;
        border-top: 1px solid rgba(255, 255, 255, 0.25);
        color: rgba(255, 255, 255, 0.8) !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .daily-table tbody tr.row-hidden-by-scope {
        display: none;
    }

    @media (max-width: 991.98px) {
        .daily-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .daily-dashboard {
            --daily-label-width: 220px;
            --daily-position-width: 94px;
            --daily-delta-width: 94px;
            --daily-rka-width: 94px;
        }

        .daily-filter-shell {
            padding: 0.85rem;
        }

        .daily-filter-card {
            min-height: 110px;
            padding: 0.72rem;
        }

        .daily-filter-select,
        .daily-dropdown-toggle,
        .daily-apply-button {
            min-height: 40px;
        }

        .daily-table-region {
            padding-bottom: 2rem;
        }

        .daily-table col.numeric-col {
            width: 94px !important;
        }

        .daily-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="daily-dashboard pt-4" id="daily-dashboard-root">
    <div class="daily-surface" id="daily-surface">
        <div class="daily-panel-head px-3 py-4 d-flex flex-wrap justify-content-center align-items-center">
                         <!-- Judul disembunyikan sesuai request user untuk minimalist design -->
            
            <!-- Hidden elements to preserve JS functionality -->
            <div class="d-none">
                <span data-source-label></span>
                <span data-scope-kanca></span>
                <span data-scope-unit></span>
                <span data-scope-posisi></span>
                <span data-scope-rka></span>
                <span data-scope-summary></span>
                <span data-kpi-simpanan></span>
                <span data-kpi-os></span>
                <span data-kpi-casa></span>
            </div>
        </div>

        <div class="daily-filter-shell">
            <div class="row align-items-stretch mx-n2 daily-filter-grid">
                <div class="col-12 col-md-6 col-xl px-2 mb-3 mb-xl-0">
                    <div class="daily-filter-card hover-lift hover-shine daily-filter-card--kanca animate-reveal stagger-1">
                        <div class="daily-filter-content">
                            <label class="daily-filter-label" for="filter-kanca">Kanca</label>
                            <div class="daily-filter-control">
                                <span class="daily-filter-control-icon"><i class="fas fa-building"></i></span>
                                <div class="daily-dropdown" data-daily-dropdown="kanca">
                                    <button type="button" class="daily-dropdown-toggle" data-daily-dropdown-toggle="kanca" aria-haspopup="listbox" aria-expanded="false">
                                        <span class="daily-dropdown-toggle-text text-truncate">Semua Kanca</span>
                                        <i class="fas fa-chevron-down daily-dropdown-toggle-icon"></i>
                                    </button>
                                    <div class="daily-dropdown-menu" data-daily-dropdown-menu="kanca"></div>
                                    <select id="filter-kanca" class="form-control daily-filter-native" multiple></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl px-2 mb-3 mb-xl-0">
                    <div class="daily-filter-card hover-lift hover-shine daily-filter-card--unit animate-reveal stagger-2">
                        <div class="daily-filter-content">
                            <label class="daily-filter-label" for="filter-unit">Unit Kerja</label>
                            <div class="daily-filter-control">
                                <span class="daily-filter-control-icon"><i class="fas fa-sitemap"></i></span>
                                <div class="daily-dropdown" data-daily-dropdown="unit">
                                    <button type="button" class="daily-dropdown-toggle" data-daily-dropdown-toggle="unit" aria-haspopup="listbox" aria-expanded="false">
                                        <span class="daily-dropdown-toggle-text text-truncate">Semua Unit Kerja</span>
                                        <i class="fas fa-chevron-down daily-dropdown-toggle-icon"></i>
                                    </button>
                                    <div class="daily-dropdown-menu" data-daily-dropdown-menu="unit"></div>
                                    <select id="filter-unit" class="form-control daily-filter-native"></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl px-2 mb-3 mb-xl-0">
                    <div class="daily-filter-card hover-lift hover-shine daily-filter-card--posisi animate-reveal stagger-3">
                        <div class="daily-filter-content">
                            <label class="daily-filter-label" for="filter-posisi-terakhir">Posisi Terakhir</label>
                            <div class="daily-filter-control">
                                <span class="daily-filter-control-icon"><i class="fas fa-calendar-day"></i></span>
                                <select id="filter-posisi-terakhir" class="form-control daily-filter-select"></select>
                                <span class="daily-filter-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl px-2 mb-3 mb-xl-0">
                    <div class="daily-filter-card hover-lift hover-shine daily-filter-card--rka animate-reveal stagger-4">
                        <div class="daily-filter-content">
                            <label class="daily-filter-label" for="filter-posisi-rka">Posisi RKA</label>
                            <div class="daily-filter-control">
                                <span class="daily-filter-control-icon"><i class="fas fa-bullseye"></i></span>
                                <select id="filter-posisi-rka" class="form-control daily-filter-select"></select>
                                <span class="daily-filter-chevron"><i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl px-2 mb-0">
                    <div class="daily-filter-card hover-lift hover-shine daily-filter-card--action animate-reveal stagger-5">
                        <div class="daily-filter-action-body">
                            <label class="daily-filter-label">Tindakan</label>
                            <!-- <div class="daily-filter-note">Pilih kombinasi filter lalu jalankan pembaruan tabel tanpa mengubah flow data yang ada.</div> -->
                            <div class="daily-filter-action-button-wrap">
                                <button type="button" class="btn daily-apply-button active-shrink d-flex justify-content-center align-items-center" id="btn-apply-daily-filter">
                                    <i class="fas fa-filter mr-2"></i> Terapkan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 bg-white">
            <div class="daily-table-region" data-table-region>
                <div class="daily-table-wrap">
                    <table class="table table-hover daily-table">
                        <colgroup>
                            <col style="width: 60px;">
                            <col style="width: 280px;">
                            <col style="width: 110px;" class="numeric-col">
                            <col style="width: 120px;" class="numeric-col">
                            <col style="width: 110px;" class="numeric-col">
                            <col style="width: 110px;" class="numeric-col">
                            <col style="width: 120px;" class="numeric-col">
                            <col style="width: 110px;" class="numeric-col">
                            <col style="width: 110px;" class="numeric-col position-col-h1">
                            <col style="width: 110px;" class="numeric-col">
                            <col style="width: 100px;" class="numeric-col">
                            <col style="width: 100px;" class="numeric-col">
                            <col style="width: 100px;" class="numeric-col">
                            <col style="width: 100px;" class="numeric-col">
                            <col style="width: 110px;" class="numeric-col">
                            <col style="width: 110px;" class="numeric-col">
                            <col style="width: 110px;" class="numeric-col">
                        </colgroup>
                        <thead>
                            <tr class="group-row text-center">
                                <th class="sticky-no group-no" rowspan="3">No</th>
                                <th class="sticky-label group-label" rowspan="3">Keterangan</th>
                                <th class="group-position" colspan="7" data-position-group-colspan>Perbandingan Posisi</th>
                                <th class="group-delta" colspan="4">Delta Terhadap</th>
                                <th class="group-rka" colspan="4">Perbandingan RKA</th>
                            </tr>
                            <tr class="column-row text-center">
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-yoy>-</span><span class="header-subnote text-white-50">M-12 (YoY)</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-ytd>-</span><span class="header-subnote text-white-50">Des (YtD)</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-m2>-</span><span class="header-subnote text-white-50">M-2</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-mtm>-</span><span class="header-subnote text-white-50">MtM</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-mtd>-</span><span class="header-subnote text-white-50">M-1 (MtD)</span></span>
                                </th>
                                <th class="value-col position-col position-col-h1" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-h1>-</span><span class="header-subnote text-white-50">h-1 (DtD)</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2" style="background-color: var(--primary-blue-light);">
                                    <span class="column-heading"><span class="main text-white" data-label-current>-</span><span class="header-subnote text-white-50">Posisi</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-yoy>-</span><span class="header-subnote text-white-50">YoY</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-ytd>-</span><span class="header-subnote text-white-50">YtD</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-mtd>-</span><span class="header-subnote text-white-50">MtD</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-dtd>-</span><span class="header-subnote text-white-50">DtD</span></span>
                                </th>
                                <th class="value-col rka-col rka-period-cell" colspan="2">
                                    <span class="rka-period-label" data-label-rka-period>RKA</span>
                                </th>
                                <th class="value-col rka-col rka-period-cell" colspan="2">
                                    <span class="rka-period-label" data-label-rka-dec-period>RKA Des</span>
                                </th>
                            </tr>
                            <tr class="rka-sub-row text-center">
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">Rp</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">%</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">Rp</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">%</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="daily-dashboard-body">
                            <tr><td colspan="16" class="daily-empty"><i class="fas fa-spinner fa-spin mr-2"></i> Memuat data dashboard harian...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    window.dailyDashboardPage = @json($dashboardPage ?? []);
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const page = window.dailyDashboardPage || {};
        const currentPath = window.location.pathname.replace(/\/$/, '');
        const dataUrl = currentPath ? currentPath + '/data' : (page.routes ? page.routes.data : '');
        const initialFilters = page.filters || {};
        const initialSelected = page.selected || {};
        const initialData = page.initialData || {};
        const surface = document.getElementById('daily-surface');
        const body = document.getElementById('daily-dashboard-body');
        const scopeKanca = document.querySelector('[data-scope-kanca]');
        const scopeUnit = document.querySelector('[data-scope-unit]');
        const scopePosisi = document.querySelector('[data-scope-posisi]');
        const scopeRka = document.querySelector('[data-scope-rka]');
        const scopeSummary = document.querySelector('[data-scope-summary]');
        const sourceLabel = document.querySelector('[data-source-label]');
        const kpiSimpanan = document.querySelector('[data-kpi-simpanan]');
        const kpiOs = document.querySelector('[data-kpi-os]');
        const kpiCasa = document.querySelector('[data-kpi-casa]');
        const headerLabels = {
            yoy: document.querySelector('[data-label-yoy]'),
            ytd: document.querySelector('[data-label-ytd]'),
            m2: document.querySelector('[data-label-m2]'),
            mtm: document.querySelector('[data-label-mtm]'),
            mtd: document.querySelector('[data-label-mtd]'),
            h1: document.querySelector('[data-label-h1]'),
            current: document.querySelector('[data-label-current]'),
            rka: document.querySelector('[data-label-rka-period]'),
            rkaDec: document.querySelector('[data-label-rka-dec-period]'),
            deltaYoy: document.querySelector('[data-label-delta-yoy]'),
            deltaYtd: document.querySelector('[data-label-delta-ytd]'),
            deltaMtd: document.querySelector('[data-label-delta-mtd]'),
            deltaDtd: document.querySelector('[data-label-delta-dtd]'),
        };
        const positionGroupColspan = document.querySelector('[data-position-group-colspan]');
        const positionH1Header = document.querySelector('[data-label-h1]').closest('th');
        const tableRegion = document.querySelector('[data-table-region]');
        const tableWrap = document.querySelector('.daily-table-wrap');
        const mainHeader = document.querySelector('.main-header');
        const applyButton = document.getElementById('btn-apply-daily-filter');
        const selects = {
            kanca: document.getElementById('filter-kanca'),
            unit_kerja: document.getElementById('filter-unit'),
            posisi_terakhir: document.getElementById('filter-posisi-terakhir'),
            posisi_rka: document.getElementById('filter-posisi-rka'),
        };
        const dropdowns = {
            kanca: {
                root: document.querySelector('[data-daily-dropdown="kanca"]'),
                toggle: document.querySelector('[data-daily-dropdown-toggle="kanca"]'),
                menu: document.querySelector('[data-daily-dropdown-menu="kanca"]'),
            },
            unit: {
                root: document.querySelector('[data-daily-dropdown="unit"]'),
                toggle: document.querySelector('[data-daily-dropdown-toggle="unit"]'),
                menu: document.querySelector('[data-daily-dropdown-menu="unit"]'),
            },
        };
        let latestFilters = initialFilters;
        const MILLION_UNIT = 1000000;
        const BILLION_UNIT = 1000000000;
        const TABLE_MONEY_UNIT = BILLION_UNIT;
        const TABLE_MONEY_LABEL = 'M';
        const TABLE_VISIBLE_ROW_LIMIT = 25;
        const TABLE_STICKY_TOP_TRIM = 24;
        const currencyFormatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const formatCurrency = function (value) {
            return currencyFormatter.format(Number(value || 0) / TABLE_MONEY_UNIT) + ' ' + TABLE_MONEY_LABEL;
        };

        const formatMiliar = function (value) {
            return currencyFormatter.format(Number(value || 0) / BILLION_UNIT) + ' M';
        };

        const formatPercent = function (value) {
            return Number(value || 0).toFixed(2).replace('.', ',') + '%';
        };

        const formatDateSlash = function (value) {
            if (!value) {
                return '-';
            }

            const parts = String(value).slice(0, 10).split('-');
            if (parts.length !== 3) {
                return String(value);
            }

            return parts[2] + '/' + parts[1] + '/' + parts[0];
        };

        const formatMonthYear = function (value) {
            if (!value) {
                return '-';
            }

            const raw = String(value).slice(0, 7);
       /*  */     if (!/^\d{4}-\d{2}$/.test(raw)) {
                return String(value);
            }

            const [year, month] = raw.split('-');
            const date = new Date(Number(year), Number(month) - 1, 1);

            return new Intl.DateTimeFormat('id-ID', { month: 'short', year: 'numeric' }).format(date);
        };

        const formatValue = function (value, type) {
            return type === 'percent' ? formatPercent(value) : formatCurrency(value);
        };

        const setTextContent = function (node, value) {
            if (node) {
                node.textContent = value;
            }
        };

        const normalizeArraySelection = function (value) {
            if (Array.isArray(value)) {
                return value.map(String).filter(Boolean);
            }

            if (value === null || value === undefined || value === '' || value === 'all') {
                return [];
            }

            return [String(value)];
        };

        const escapeHtml = function (value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        };

        const togglePositionColumns = function (visible) {
            const show = Boolean(visible);
            const hiddenClass = 'position-col-hidden';

            [positionH1Header].forEach(function (node) {
                if (!node) {
                    return;
                }

                node.classList.toggle(hiddenClass, !show);
            });

            document.querySelectorAll('[data-position-col="h1"]').forEach(function (cell) {
                cell.classList.toggle(hiddenClass, !show);
            });

            document.querySelectorAll('col.position-col-h1').forEach(function (cell) {
                cell.classList.toggle(hiddenClass, !show);
            });

            if (positionGroupColspan) {
                positionGroupColspan.setAttribute('colspan', show ? '7' : '6');
            }

            scheduleTableViewportSync();
        };

        const getStickyTopOffset = function () {
            const headerHeight = mainHeader ? Math.ceil(mainHeader.getBoundingClientRect().height || 0) : 0;
            return Math.max(0, headerHeight - TABLE_STICKY_TOP_TRIM);
        };

        const syncTableViewport = function () {
            if (!tableWrap || !tableRegion || !body) {
                return;
            }

            const stickyTop = getStickyTopOffset();
            tableRegion.style.setProperty('--daily-table-sticky-top', stickyTop + 'px');

            const headerRows = Array.from(tableWrap.querySelectorAll('.daily-table thead tr'));
            const visibleRows = Array.from(body.querySelectorAll('tr')).filter(function (row) {
                return !row.classList.contains('row-hidden-by-scope') && window.getComputedStyle(row).display !== 'none';
            });

            if (!visibleRows.length) {
                tableWrap.style.height = 'auto';
                tableWrap.style.maxHeight = 'none';
                return;
            }

            const headerHeight = headerRows.reduce(function (total, row) {
                return total + Math.ceil(row.getBoundingClientRect().height || 0);
            }, 0);

            const bodyHeight = visibleRows.slice(0, TABLE_VISIBLE_ROW_LIMIT).reduce(function (total, row) {
                return total + Math.ceil(row.getBoundingClientRect().height || 0);
            }, 0);

            const viewportLimit = Math.max(360, window.innerHeight - stickyTop - 20);
            const desiredHeight = Math.min(viewportLimit, headerHeight + bodyHeight + 2);

            tableWrap.style.height = desiredHeight + 'px';
            tableWrap.style.maxHeight = desiredHeight + 'px';
        };

        const scheduleTableViewportSync = function () {
            requestAnimationFrame(syncTableViewport);
        };

        const populateSelect = function (select, options, selectedValue) {
            if (!select) {
                return;
            }

            const normalizedSelected = selectedValue || 'all';
            const html = (options || []).map(function (option) {
                const value = option.value || 'all';
                const label = option.label || value;
                const selected = String(value) === String(normalizedSelected) ? 'selected' : '';

                return '<option value="' + value + '" ' + selected + '>' + label + '</option>';
            }).join('');

            select.innerHTML = html;
            $(select).trigger('change.select2');
        };

        const setNativeSelectOptions = function (select, options, selectedValues, multiple) {
            if (!select) {
                return;
            }

            const normalizedValues = multiple
                ? normalizeArraySelection(selectedValues)
                : [String(selectedValues || 'all')];

            select.innerHTML = (options || []).map(function (option) {
                const value = String(option.value || 'all');
                const label = option.label || value;
                const isSelected = multiple
                    ? normalizedValues.includes(value)
                    : value === normalizedValues[0];

                return '<option value="' + escapeHtml(value) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
            }).join('');
        };

        const closeDropdown = function (key) {
            const dropdown = dropdowns[key];
            if (!dropdown || !dropdown.root || !dropdown.toggle) {
                return;
            }

            dropdown.root.classList.remove('is-open');
            dropdown.toggle.setAttribute('aria-expanded', 'false');
        };

        const openDropdown = function (key) {
            Object.keys(dropdowns).forEach(function (name) {
                if (name !== key) {
                    closeDropdown(name);
                }
            });

            const dropdown = dropdowns[key];
            if (!dropdown || !dropdown.root || !dropdown.toggle) {
                return;
            }

            dropdown.root.classList.add('is-open');
            dropdown.toggle.setAttribute('aria-expanded', 'true');
        };

        const updateDropdownToggleText = function (key, text) {
            const dropdown = dropdowns[key];
            const textNode = dropdown && dropdown.toggle
                ? dropdown.toggle.querySelector('.daily-dropdown-toggle-text')
                : null;

            if (textNode) {
                textNode.textContent = text;
                dropdown.toggle.setAttribute('title', text);
            }
        };

        const getSelectedKancaValues = function () {
            if (!selects.kanca) {
                return [];
            }

            return Array.from(selects.kanca.selectedOptions || [])
                .map(function (option) { return String(option.value || ''); })
                .filter(function (value) { return value && value !== 'all'; });
        };

        const buildKancaToggleLabel = function (options, selectedValues) {
            const normalized = normalizeArraySelection(selectedValues);
            if (!normalized.length) {
                return 'Semua Kanca';
            }

            const labels = (options || [])
                .filter(function (option) {
                    return normalized.includes(String(option.value || ''));
                })
                .map(function (option) {
                    return option.label || option.value || '';
                })
                .filter(Boolean);

            if (labels.length <= 2) {
                return labels.join(', ');
            }

            return labels[0] + ' +' + (labels.length - 1) + ' lainnya';
        };

        const renderKancaDropdown = function (options, selectedValues) {
            const dropdown = dropdowns.kanca;
            if (!dropdown || !dropdown.menu || !selects.kanca) {
                return;
            }

            const normalized = normalizeArraySelection(selectedValues);
            setNativeSelectOptions(selects.kanca, options, normalized, true);

            dropdown.menu.innerHTML = (options || []).map(function (option) {
                const value = String(option.value || 'all');
                const isAll = value === 'all';
                const isActive = isAll ? normalized.length === 0 : normalized.includes(value);

                return '<button type="button" class="daily-dropdown-option ' + (isActive ? 'is-active' : '') + '" data-kanca-option="' + escapeHtml(value) + '">' +
                    '<span class="daily-dropdown-check"><i class="fas fa-check"></i></span>' +
                    '<span class="daily-dropdown-label">' + escapeHtml(option.label || value) + '</span>' +
                '</button>';
            }).join('');

            updateDropdownToggleText('kanca', buildKancaToggleLabel(options, normalized));
        };

        const scopedUnitOptions = function (filters, kancaValue) {
            return (filters.unit_kerja || []).filter(function (option) {
                if ((option.value || 'all') === 'all') {
                    return true;
                }

                if (!Array.isArray(kancaValue) || !kancaValue.length) {
                    return true;
                }

                return kancaValue.includes(String(option.kanca_value || ''));
            });
        };

        const syncUnitSelect = function (filters, preferredUnit) {
            const unitOptions = scopedUnitOptions(filters, getSelectedKancaValues());
            const selectedUnit = unitOptions.some(function (option) {
                return String(option.value || '') === String(preferredUnit || 'all');
            }) ? (preferredUnit || 'all') : 'all';

            setNativeSelectOptions(selects.unit_kerja, unitOptions, selectedUnit, false);

            const dropdown = dropdowns.unit;
            if (dropdown && dropdown.menu) {
                dropdown.menu.innerHTML = unitOptions.length
                    ? unitOptions.map(function (option) {
                        const value = String(option.value || 'all');
                        const active = value === String(selectedUnit);

                        return '<button type="button" class="daily-dropdown-option ' + (active ? 'is-active' : '') + '" data-unit-option="' + escapeHtml(value) + '">' +
                            '<span class="daily-dropdown-label">' + escapeHtml(option.label || value) + '</span>' +
                        '</button>';
                    }).join('')
                    : '<div class="daily-dropdown-empty">Tidak ada unit kerja.</div>';
            }

            const selectedOption = unitOptions.find(function (option) {
                return String(option.value || 'all') === String(selectedUnit);
            });

            updateDropdownToggleText('unit', selectedOption ? (selectedOption.label || selectedOption.value || 'Semua Unit Kerja') : 'Semua Unit Kerja');
        };

        const currentState = function () {
            return {
                kanca: getSelectedKancaValues(),
                unit_kerja: selects.unit_kerja.value || 'all',
                posisi_terakhir: selects.posisi_terakhir.value || '',
                posisi_rka: selects.posisi_rka.value || '',
            };
        };

        const buildQueryParams = function () {
            const state = currentState();
            const params = new URLSearchParams();

            (state.kanca || []).forEach(function (value) {
                if (value) {
                    params.append('kanca[]', value);
                }
            });

            if (state.unit_kerja && state.unit_kerja !== 'all') {
                params.set('unit_kerja', state.unit_kerja);
            }

            if (state.posisi_terakhir) {
                params.set('posisi_terakhir', state.posisi_terakhir);
            }

            if (state.posisi_rka) {
                params.set('posisi_rka', state.posisi_rka);
            }

            return params;
        };

        const zeroMetricGroup = function (target) {
            ['values', 'deltas'].forEach(function (group) {
                Object.keys(target[group] || {}).forEach(function (metricKey) {
                    target[group][metricKey] = 0;
                });
            });
        };

        const clonePayload = function (payload) {
            return JSON.parse(JSON.stringify(payload || {}));
        };

        const getUnitScopeMode = function () {
            if (!selects.unit_kerja || !selects.unit_kerja.value || selects.unit_kerja.value === 'all') {
                return 'all';
            }

            const selectedOption = selects.unit_kerja.options[selects.unit_kerja.selectedIndex];
            const label = selectedOption ? String(selectedOption.text || '').trim().toUpperCase() : '';

            if (/\bUNIT\b/.test(label)) {
                return 'unit';
            }

            if (/\bKCP\b/.test(label)) {
                return 'kcp';
            }

            if (/\bKC\b/.test(label)) {
                return 'kc';
            }

            return 'other';
        };

        const sumMetric = function (rowsByKey, keys, group, metricName) {
            return keys.reduce(function (total, key) {
                return total + Number((((rowsByKey[key] || {})[group] || {})[metricName]) || 0);
            }, 0);
        };

        const safePercent = function (value, base) {
            const numerator = Number(value || 0);
            const denominator = Number(base || 0);

            if (!denominator) {
                return 0;
            }

            return (numerator / denominator) * 100;
        };

        const isQualityTargetMetric = function (rowKey) {
            const key = String(rowKey || '');

            return key.includes('_sml')
                || key.includes('_npl')
                || key.startsWith('total_sml_')
                || key.startsWith('total_npl_');
        };

        const formatAchievement = function (value) {
            return Number(value || 0).toFixed(2).replace('.', ',') + '%';
        };

        const computeRkaComparison = function (row) {
            const currentValue = Number(row?.values?.current || 0);
            const rkaValue = Number(row?.values?.rka || 0);
            const rkaDecValue = Number(row?.values?.rka_dec || 0);
            const reverse = isQualityTargetMetric(row?.key);
            const compare = function (targetValue) {
                const delta = reverse ? (targetValue - currentValue) : (currentValue - targetValue);
                let achievement = 0;

                if (reverse) {
                    if (currentValue <= 0) {
                        achievement = 100;
                    } else {
                        achievement = (targetValue / currentValue) * 100;
                    }
                } else if (targetValue > 0) {
                    achievement = (currentValue / targetValue) * 100;
                }

                return {
                    delta: Number.isFinite(delta) ? delta : 0,
                    achievement: Number.isFinite(achievement) ? achievement : 0,
                };
            };

            return {
                rka: compare(rkaValue),
                rkaDec: compare(rkaDecValue),
            };
        };

        const applyScopeToPayload = function (payload) {
            const scopedPayload = clonePayload(payload);
            const rows = scopedPayload.rows || [];
            const scopeMode = getUnitScopeMode();

            if (scopeMode === 'all' || !rows.length) {
                return scopedPayload;
            }

            const rowsByKey = {};
            rows.forEach(function (row) {
                rowsByKey[row.key] = row;
            });

            let hiddenKeys = [];

            const ritelKeys = [
                'simpanan_ritel', 'giro_ritel', 'deposito_ritel', 'tabungan_ritel',
                'sme_os', 'kecil_os', 'kecil_non_cashcoll_os', 'cashcoll_os', 'medium_os',
                'consumer_os', 'briguna_konsumer_os', 'kpr_os', 'kkb_os',
                'sme_sml', 'kecil_sml', 'kecil_non_cashcoll_sml', 'cashcoll_sml', 'medium_sml',
                'consumer_sml', 'briguna_konsumer_sml', 'kpr_sml', 'kkb_sml',
                'sme_npl', 'kecil_npl', 'kecil_non_cashcoll_npl', 'cashcoll_npl', 'medium_npl',
                'consumer_npl', 'briguna_konsumer_npl', 'kpr_npl', 'kkb_npl',
                'casa_ritel', 'ldr_ritel_non_commercial'
            ];

            let mikroKeys = [
                'simpanan_mikro', 'giro_mikro', 'deposito_mikro', 'tabungan_mikro',
                'micro_os', 'briguna_mikro_os', 'kupedes_os', 'kur_mikro_os', 'kur_kecil_os', 'kur_kpp_os',
                'micro_sml', 'briguna_mikro_sml', 'kupedes_sml', 'kur_mikro_sml', 'kur_kecil_sml', 'kur_kpp_sml',
                'micro_npl', 'briguna_mikro_npl', 'kupedes_npl', 'kur_mikro_npl', 'kur_kecil_npl', 'kur_kpp_npl',
                'casa_mikro', 'ldr_mikro_non_commercial'
            ];

            if (scopeMode === 'kc' || scopeMode === 'kcp') {
                mikroKeys = mikroKeys.filter(function(k) {
                    return k !== 'kur_kecil_os' && k !== 'kur_kecil_sml' && k !== 'kur_kecil_npl' &&
                           k !== 'micro_os' && k !== 'micro_sml' && k !== 'micro_npl';
                });
            }

            const commercialKeys = [
                'commercial_os', 'commercial_sml', 'commercial_npl'
            ];

            const wholesaleKeys = [
                'simpanan_wholesale', 'giro_wholesale', 'deposito_wholesale', 'tabungan_wholesale'
            ];

            if (scopeMode === 'unit') {
                hiddenKeys = hiddenKeys.concat(ritelKeys, commercialKeys, wholesaleKeys);
            } else if (scopeMode === 'kcp') {
                hiddenKeys = hiddenKeys.concat(mikroKeys, commercialKeys);
            } else if (scopeMode === 'kc') {
                hiddenKeys = hiddenKeys.concat(mikroKeys, commercialKeys);
            } else {
                hiddenKeys = hiddenKeys.concat(mikroKeys, commercialKeys, wholesaleKeys);
            }

            hiddenKeys.forEach(function (key) {
                if (rowsByKey[key]) {
                    rowsByKey[key].hiddenByScope = true;
                    zeroMetricGroup(rowsByKey[key]);
                }
            });

            ['values', 'deltas'].forEach(function (group) {
                ['yoy', 'ytd', 'm2', 'mtm', 'mtd', 'h1', 'current', 'rka', 'rka_dec'].forEach(function (metricName) {
                    if (group === 'deltas' && metricName !== 'yoy' && metricName !== 'ytd' && metricName !== 'mtd' && metricName !== 'dtd') {
                        return;
                    }

                    const smpanMetricName = metricName;
                    rowsByKey.simpanan_ritel[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_ritel', 'deposito_ritel', 'tabungan_ritel'], group, smpanMetricName);
                    rowsByKey.simpanan_mikro[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_mikro', 'deposito_mikro', 'tabungan_mikro'], group, smpanMetricName);
                    rowsByKey.simpanan_wholesale[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_wholesale', 'deposito_wholesale', 'tabungan_wholesale'], group, smpanMetricName);
                    rowsByKey.total_simpanan[group][smpanMetricName] = sumMetric(rowsByKey, ['simpanan_ritel', 'simpanan_mikro', 'simpanan_wholesale'], group, smpanMetricName);
                    rowsByKey.casa_ritel[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_ritel', 'tabungan_ritel'], group, smpanMetricName);
                    rowsByKey.casa_mikro[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_mikro', 'tabungan_mikro'], group, smpanMetricName);
                    rowsByKey.total_casa[group][smpanMetricName] = sumMetric(rowsByKey, ['casa_ritel', 'casa_mikro'], group, smpanMetricName);
                    const microOsChildren = ['briguna_mikro_os', 'kupedes_os', 'kur_mikro_os', 'kur_kecil_os', 'kur_kpp_os'];
                    const smeOsChildren = (scopeMode === 'kc' || scopeMode === 'kcp') ? ['kecil_os'] : ['kecil_os', 'medium_os'];
                    rowsByKey.sme_os[group][smpanMetricName] = sumMetric(rowsByKey, smeOsChildren, group, smpanMetricName);
                    rowsByKey.consumer_os[group][smpanMetricName] = sumMetric(rowsByKey, ['briguna_konsumer_os', 'kpr_os', 'kkb_os'], group, smpanMetricName);
                    rowsByKey.micro_os[group][smpanMetricName] = sumMetric(rowsByKey, microOsChildren, group, smpanMetricName);
                    
                    const totalOsNonCommercialChildren = (scopeMode === 'kc' || scopeMode === 'kcp') ? ['sme_os', 'consumer_os'] : ['sme_os', 'consumer_os', 'micro_os'];
                    rowsByKey.total_os_non_commercial[group][smpanMetricName] = sumMetric(rowsByKey, totalOsNonCommercialChildren, group, smpanMetricName);
                    
                    const totalOsChildren = (scopeMode === 'kc' || scopeMode === 'kcp') ? ['commercial_os', 'sme_os', 'consumer_os'] : ['commercial_os', 'sme_os', 'consumer_os', 'micro_os'];
                    rowsByKey.total_os[group][smpanMetricName] = sumMetric(rowsByKey, totalOsChildren, group, smpanMetricName);
                    
                    const microSmlChildren = ['briguna_mikro_sml', 'kupedes_sml', 'kur_mikro_sml', 'kur_kecil_sml', 'kur_kpp_sml'];
                    const smeSmlChildren = (scopeMode === 'kc' || scopeMode === 'kcp') ? ['kecil_sml'] : ['kecil_sml', 'medium_sml'];
                    rowsByKey.sme_sml[group][smpanMetricName] = sumMetric(rowsByKey, smeSmlChildren, group, smpanMetricName);
                    rowsByKey.consumer_sml[group][smpanMetricName] = sumMetric(rowsByKey, ['briguna_konsumer_sml', 'kpr_sml', 'kkb_sml'], group, smpanMetricName);
                    rowsByKey.micro_sml[group][smpanMetricName] = sumMetric(rowsByKey, microSmlChildren, group, smpanMetricName);
                    
                    const totalSmlNonCommercialChildren = (scopeMode === 'kc' || scopeMode === 'kcp') ? ['sme_sml', 'consumer_sml'] : ['sme_sml', 'consumer_sml', 'micro_sml'];
                    rowsByKey.total_sml_abs_non_commercial[group][smpanMetricName] = sumMetric(rowsByKey, totalSmlNonCommercialChildren, group, smpanMetricName);
                    
                    const smeNplChildren = ['kecil_npl'];
                    rowsByKey.sme_npl[group][smpanMetricName] = sumMetric(rowsByKey, smeNplChildren, group, smpanMetricName);
                    rowsByKey.consumer_npl[group][smpanMetricName] = sumMetric(rowsByKey, ['briguna_konsumer_npl', 'kpr_npl', 'kkb_npl'], group, smpanMetricName);
                    
                    const totalNplNonCommercialChildren = (scopeMode === 'kc' || scopeMode === 'kcp') ? ['sme_npl', 'consumer_npl'] : ['sme_npl', 'consumer_npl', 'micro_npl'];
                    rowsByKey.total_npl_abs_non_commercial[group][smpanMetricName] = sumMetric(rowsByKey, totalNplNonCommercialChildren, group, smpanMetricName);
                });
            });

            ['values', 'deltas'].forEach(function (group) {
                const metricNames = group === 'values'
                    ? ['yoy', 'ytd', 'm2', 'mtm', 'mtd', 'h1', 'current', 'rka', 'rka_dec']
                    : ['yoy', 'ytd', 'mtd', 'dtd'];

                metricNames.forEach(function (metricName) {
                    rowsByKey.casa_pct[group][metricName] = safePercent(rowsByKey.total_casa[group][metricName], rowsByKey.total_simpanan[group][metricName]);
                    rowsByKey.ldr_non_commercial[group][metricName] = safePercent(rowsByKey.total_simpanan[group][metricName], rowsByKey.total_os_non_commercial[group][metricName]);
                    rowsByKey.ldr_ritel_non_commercial[group][metricName] = safePercent(rowsByKey.simpanan_ritel[group][metricName], rowsByKey.sme_os[group][metricName] + rowsByKey.consumer_os[group][metricName]);
                    rowsByKey.ldr_mikro_non_commercial[group][metricName] = safePercent(rowsByKey.simpanan_mikro[group][metricName], rowsByKey.micro_os[group][metricName]);
                });
            });

            scopedPayload.summary = scopedPayload.summary || {};
            scopedPayload.summary.current_total_simpanan = Number(rowsByKey.total_simpanan?.values?.current || 0);
            scopedPayload.summary.current_total_os_non_commercial = Number(rowsByKey.total_os_non_commercial?.values?.current || 0);
            scopedPayload.summary.current_casa_pct = Number(rowsByKey.casa_pct?.values?.current || 0);

            return scopedPayload;
        };

        const renderTable = function (payload) {
            const scopedPayload = applyScopeToPayload(payload);
            const rows = scopedPayload.rows || [];
            const periods = scopedPayload.comparison_periods || {};
            const hasH1 = Boolean(periods.h1 && periods.h1.period);
            const emptyColspan = hasH1 ? 16 : 15;
            const blockClassMap = {
                total_simpanan: 'metric-block-simpanan',
                total_os: 'metric-block-os',
                total_sml_pct_non_commercial: 'metric-block-sml',
                total_npl_pct_non_commercial: 'metric-block-npl',
                casa_pct: 'metric-block-casa',
                ldr_non_commercial: 'metric-block-ldr',
                rec_dh_total: 'metric-block-recdh',
            };
            const sectionClassMap = {
                simpanan_ritel: 'section-ritel',
                simpanan_mikro: 'section-mikro',
                simpanan_wholesale: 'section-wholesale',
                commercial_os: 'section-commercial',
                sme_os: 'section-ritel',
                consumer_os: 'section-consumer',
                micro_os: 'section-mikro',
                commercial_sml: 'section-commercial',
                sme_sml: 'section-ritel',
                consumer_sml: 'section-consumer',
                micro_sml: 'section-mikro',
                commercial_npl: 'section-commercial',
                sme_npl: 'section-ritel',
                consumer_npl: 'section-consumer',
                micro_npl: 'section-mikro',
                rec_dh_small: 'section-ritel',
                rec_dh_consumer: 'section-consumer',
                rec_dh_micro: 'section-mikro',
            };
            const scopeMode = getUnitScopeMode();

            togglePositionColumns(hasH1);

            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="' + emptyColspan + '" class="daily-empty"><i class="fas fa-box-open mr-2 text-muted"></i>Tidak ada data untuk filter terpilih.</td></tr>';
                scheduleTableViewportSync();
                return;
            }

            body.innerHTML = rows.map(function (row, index) {
                const value = row.values || {};
                const delta = row.deltas || {};
                const rowCells = [];
                const deltaClass = function (amount) {
                    if (Number(amount) > 0) {
                        return 'delta-positive';
                    }

                    if (Number(amount) < 0) {
                        return 'delta-negative';
                    }

                    return 'text-muted';
                };
                const rkaComparison = computeRkaComparison(row);

                rowCells.push('<td class="sticky-no font-weight-bold text-center">' + (index + 1) + '</td>');
                rowCells.push('<td class="sticky-label"><span class="metric-label" title="' + escapeHtml(row.label) + '">' + escapeHtml(row.label) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.yoy, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.ytd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.m2, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtm, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtd, row.type) + '</span></td>');

                if (hasH1) {
                    rowCells.push('<td class="value-col position-col position-col-h1" data-position-col="h1"><span class="cell-text">' + formatValue(value.h1, row.type) + '</span></td>');
                }

                rowCells.push('<td class="value-col position-col metric-value font-weight-bold bg-light"><span class="cell-text text-primary">' + formatValue(value.current, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.yoy) + '"><span class="cell-text">' + formatValue(delta.yoy, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.ytd) + '"><span class="cell-text">' + formatValue(delta.ytd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.mtd) + '"><span class="cell-text">' + formatValue(delta.mtd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.dtd) + '"><span class="cell-text">' + formatValue(delta.dtd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka, row.type) + '</span></td>');
                rowCells.push('<td class="value-col rka-col ' + deltaClass(rkaComparison.rka.delta) + '"><span class="cell-text">' + formatValue(rkaComparison.rka.delta, row.type) + '</span><span class="daily-rka-subnote">' + formatAchievement(rkaComparison.rka.achievement) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka_dec, row.type) + '</span></td>');
                rowCells.push('<td class="value-col rka-col ' + deltaClass(rkaComparison.rkaDec.delta) + '"><span class="cell-text">' + formatValue(rkaComparison.rkaDec.delta, row.type) + '</span><span class="daily-rka-subnote">' + formatAchievement(rkaComparison.rkaDec.achievement) + '</span></td>');

                const rowClasses = ['row-depth-' + row.depth];
                if (blockClassMap[row.key]) {
                    rowClasses.push(blockClassMap[row.key]);
                }
                if (sectionClassMap[row.key]) {
                    rowClasses.push(sectionClassMap[row.key]);
                }
                if (row.hiddenByScope || (scopeMode !== 'all' && row.hiddenByScope)) {
                    rowClasses.push('row-hidden-by-scope');
                }

                return '<tr class="' + rowClasses.join(' ') + '">' + rowCells.join('') + '</tr>';
            }).join('');

            scheduleTableViewportSync();
        };

        const applyPayload = function (payload) {
            const scopedPayload = applyScopeToPayload(payload);
            const summary = scopedPayload.summary || {};
            const periods = scopedPayload.comparison_periods || {};
            const filters = scopedPayload.available_filters || payload.available_filters || initialFilters;
            const hasH1 = Boolean(periods.h1 && periods.h1.period);
            latestFilters = filters;
            const current = currentState();

            renderKancaDropdown(filters.kanca || [], current.kanca);
            syncUnitSelect(filters, current.unit_kerja);
            populateSelect(selects.posisi_terakhir, filters.posisi_terakhir || [], scopedPayload.selected_period || current.posisi_terakhir);
            populateSelect(selects.posisi_rka, filters.posisi_rka || [], scopedPayload.selected_rka_period ? scopedPayload.selected_rka_period.slice(0, 7) : current.posisi_rka);

            setTextContent(scopeKanca, summary.kanca_label || 'Semua Kanca');
            setTextContent(scopeUnit, summary.unit_label || 'Semua Unit Kerja');
            setTextContent(scopePosisi, scopedPayload.selected_period_label || 'Belum ada data');
            setTextContent(scopeRka, periods.rka ? formatMonthYear(periods.rka.period) : 'Belum ada data');
            if (scopeSummary) {
                scopeSummary.innerHTML = '<i class="fas fa-list mr-1"></i> Baris tampil: ' + (summary.row_count || 0).toLocaleString('id-ID') + '. <br><small class="text-muted font-weight-normal mt-1 d-block">Data dari: ' + (summary.source || 'source_fallback') + '</small>';
            }
            setTextContent(sourceLabel, summary.source || 'source_fallback');
            setTextContent(kpiSimpanan, formatMiliar(summary.current_total_simpanan || 0));
            setTextContent(kpiOs, formatMiliar(summary.current_total_os_non_commercial || 0));
            setTextContent(kpiCasa, formatPercent(summary.current_casa_pct || 0));

            setTextContent(headerLabels.yoy, periods.yoy ? formatDateSlash(periods.yoy.period) : '-');
            setTextContent(headerLabels.ytd, periods.ytd ? formatDateSlash(periods.ytd.period) : '-');
            setTextContent(headerLabels.m2, periods.m2 ? formatDateSlash(periods.m2.period) : '-');
            setTextContent(headerLabels.mtm, periods.mtm ? formatDateSlash(periods.mtm.period) : '-');
            setTextContent(headerLabels.mtd, periods.mtd ? formatDateSlash(periods.mtd.period) : '-');
            setTextContent(headerLabels.h1, hasH1 ? formatDateSlash(periods.h1.period) : '-');
            setTextContent(headerLabels.current, scopedPayload.selected_period ? formatDateSlash(scopedPayload.selected_period) : '-');
            setTextContent(headerLabels.rka, periods.rka ? 'RKA ' + String(formatMonthYear(periods.rka.period)).toUpperCase() : 'RKA');
            setTextContent(headerLabels.rkaDec, periods.rka_dec ? 'RKA ' + String(formatMonthYear(periods.rka_dec.period)).toUpperCase() : 'RKA Des');
            setTextContent(headerLabels.deltaYoy, periods.yoy ? formatDateSlash(periods.yoy.period) : '-');
            setTextContent(headerLabels.deltaYtd, periods.ytd ? formatDateSlash(periods.ytd.period) : '-');
            setTextContent(headerLabels.deltaMtd, periods.mtd ? formatDateSlash(periods.mtd.period) : '-');
            setTextContent(headerLabels.deltaDtd, hasH1 ? formatDateSlash(periods.h1.period) : '-');

            togglePositionColumns(hasH1);

            renderTable(scopedPayload);
        };

        const fetchData = function () {
            if (!dataUrl) {
                return;
            }

            const params = buildQueryParams();
            tableRegion.classList.add('daily-loading');
            if (applyButton) {
                applyButton.disabled = true;
                applyButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Proses...';
            }

            fetch(dataUrl + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.text().then(function (text) {
                            throw new Error('HTTP ' + response.status + ': ' + text.slice(0, 180));
                        });
                    }

                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.indexOf('application/json') === -1) {
                        return response.text().then(function (text) {
                            throw new Error('Invalid content type: ' + contentType + ' :: ' + text.slice(0, 180));
                        });
                    }

                    return response.json();
                })
                .then(function (payload) {
                    applyPayload(payload);
                })
                .catch(function (error) {
                    console.error('Gagal memuat data dashboard harian.', error);
                    const hidden = positionH1Header && positionH1Header.classList.contains('position-col-hidden');
                    body.innerHTML = '<tr><td colspan="' + (hidden ? 15 : 16) + '" class="daily-empty text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Gagal memuat data dashboard harian.</td></tr>';
                })
                .finally(function () {
                    tableRegion.classList.remove('daily-loading');
                    if (applyButton) {
                        applyButton.disabled = false;
                        applyButton.innerHTML = '<i class="fas fa-filter mr-1"></i> Terapkan';
                    }
                });
        };

        const refreshUnitOptions = function () {
            if (!dataUrl) {
                syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
                return;
            }

            const params = buildQueryParams();

            fetch(dataUrl + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.text().then(function (text) {
                            throw new Error('HTTP ' + response.status + ': ' + text.slice(0, 180));
                        });
                    }

                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.indexOf('application/json') === -1) {
                        return response.text().then(function (text) {
                            throw new Error('Invalid content type: ' + contentType + ' :: ' + text.slice(0, 180));
                        });
                    }

                    return response.json();
                })
                .then(function (payload) {
                    latestFilters = payload.available_filters || initialFilters;
                    syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
                })
                .catch(function () {
                    syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
                });
        };

        renderKancaDropdown(initialFilters.kanca || [], initialSelected.kanca || []);
        syncUnitSelect(initialFilters, initialSelected.unit_kerja || 'all');
        populateSelect(selects.posisi_terakhir, initialFilters.posisi_terakhir || [], initialSelected.posisi_terakhir || '');
        populateSelect(selects.posisi_rka, initialFilters.posisi_rka || [], initialSelected.posisi_rka || '');
        body.innerHTML = '<tr><td colspan="15" class="daily-empty"><i class="fas fa-filter mr-2 text-muted"></i>Filter belum dijalankan. Pilih parameter lalu klik Terapkan Filter.</td></tr>';

        if (initialData && Object.keys(initialData).length) {
            applyPayload(initialData);
        } else {
            sourceLabel.textContent = '-';
            scheduleTableViewportSync();
        }

        if (applyButton) {
            applyButton.addEventListener('click', fetchData);
        }

        if (dropdowns.kanca && dropdowns.kanca.toggle) {
            dropdowns.kanca.toggle.addEventListener('click', function () {
                if (dropdowns.kanca.root.classList.contains('is-open')) {
                    closeDropdown('kanca');
                    return;
                }

                openDropdown('kanca');
            });
        }

        if (dropdowns.unit && dropdowns.unit.toggle) {
            dropdowns.unit.toggle.addEventListener('click', function () {
                if (dropdowns.unit.root.classList.contains('is-open')) {
                    closeDropdown('unit');
                    return;
                }

                openDropdown('unit');
            });
        }

        if (dropdowns.kanca && dropdowns.kanca.menu) {
            dropdowns.kanca.menu.addEventListener('click', function (event) {
                const option = event.target.closest('[data-kanca-option]');
                if (!option || !selects.kanca) {
                    return;
                }

                const value = String(option.getAttribute('data-kanca-option') || 'all');
                let nextValues = getSelectedKancaValues();

                if (value === 'all') {
                    nextValues = [];
                } else if (nextValues.includes(value)) {
                    nextValues = nextValues.filter(function (item) { return item !== value; });
                } else {
                    nextValues.push(value);
                }

                Array.from(selects.kanca.options).forEach(function (nativeOption) {
                    const nativeValue = String(nativeOption.value || '');
                    nativeOption.selected = nativeValue !== 'all' && nextValues.includes(nativeValue);
                });

                renderKancaDropdown(latestFilters.kanca || [], nextValues);
                syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
                refreshUnitOptions();
            });
        }

        if (dropdowns.unit && dropdowns.unit.menu) {
            dropdowns.unit.menu.addEventListener('click', function (event) {
                const option = event.target.closest('[data-unit-option]');
                if (!option || !selects.unit_kerja) {
                    return;
                }

                const value = String(option.getAttribute('data-unit-option') || 'all');
                selects.unit_kerja.value = value;
                syncUnitSelect(latestFilters, value);
                closeDropdown('unit');
            });
        }

        document.addEventListener('click', function (event) {
            Object.keys(dropdowns).forEach(function (key) {
                const dropdown = dropdowns[key];
                if (dropdown && dropdown.root && !dropdown.root.contains(event.target)) {
                    closeDropdown(key);
                }
            });
        });

        window.addEventListener('resize', scheduleTableViewportSync);
        scheduleTableViewportSync();
    });
</script>
@endsection
