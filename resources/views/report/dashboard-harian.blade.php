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
        
        --daily-label-width: 230px;
        --daily-position-width: 86px;
        --daily-delta-width: 86px;
        --daily-rka-width: 86px;
        --daily-header-group-height: 30px;
        --daily-header-column-height: 48px;
        --daily-header-rka-height: 28px;
        --daily-header-column-top: var(--daily-header-group-height);
        --daily-header-rka-top: calc(var(--daily-header-group-height) + var(--daily-header-column-height));
        --daily-table-blue: #0070c0;
        --daily-table-blue-dark: #003b70;
        --daily-table-blue-mid: #005b9f;
        --daily-table-cyan: #00a6d6;
        --daily-table-rka: #002060;
        --daily-table-rka-sub: #7f7f7f;
    }

    .daily-dashboard {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
        padding-left: clamp(0.5rem, 1.2vw, 1rem);
        padding-right: clamp(0.5rem, 1.2vw, 1rem);
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: hidden;
        overflow-x: clip;
    }

    .daily-dashboard,
    .daily-dashboard * {
        box-sizing: border-box;
    }

    .daily-dashboard.pt-4 {
        padding-top: clamp(0.55rem, 1vw, 1rem) !important;
    }

    /* Surface & Cards */
    .daily-surface {
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        box-shadow: 0 12px 26px -24px rgba(15, 23, 42, 0.22);
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
        max-width: 100%;
        min-width: 0;
        overflow: visible;
        z-index: 1;
    }

    .daily-table-panel {
        padding: 0.72rem 1.05rem 1rem;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    .daily-panel-head {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        background: linear-gradient(135deg, #003b75 0%, #00529c 100%);
        border-bottom: 1px solid rgba(219, 229, 239, 0.92);
        color: #ffffff;
        min-height: 58px;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        flex-wrap: wrap;
        gap: 0.65rem;
    }

    .daily-panel-head::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(120deg, rgba(255, 255, 255, 0.05), transparent 40%);
        opacity: 0.72;
    }

    .daily-title-wrap {
        width: min(100%, 860px);
        text-align: center;
        padding: 0.05rem 1rem;
    }

    .daily-title-badge {
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
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }

    .daily-title-badge i {
        color: #ffb15c;
    }

    .daily-panel-title {
        margin: 0;
        font-size: clamp(1.15rem, 1.7vw, 1.5rem);
        font-weight: 800;
        color: #ffffff;
        letter-spacing: 0.02em;
        line-height: 1.1;
        text-transform: uppercase;
    }

    .daily-panel-title-group {
        min-width: 0;
        flex: 1 1 280px;
    }

    .daily-panel-actions {
        min-width: 0;
        flex: 0 1 auto;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    /* Removed title underline decor */

    .daily-panel-desc {
        margin: 0.65rem auto 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.78rem;
        line-height: 1.6;
        max-width: 660px;
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

    /* Capture Button Style */
    .btn-export-all {
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
        transition: all 0.2s ease;
        max-width: 100%;
        white-space: nowrap;
    }

    .daily-panel-actions .btn-export-all {
        margin-right: 0 !important;
    }

    .btn-export-all:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.68);
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
        z-index: 80;
        margin-bottom: 0;
        padding: 0.62rem 0.75rem;
        background: #ffffff;
        border-bottom: 1px solid rgba(219, 229, 239, 0.9);
        overflow: visible;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    .daily-filter-shell::before {
        content: none;
    }

    .daily-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr)) minmax(132px, 0.72fr);
        gap: 0.55rem;
        position: relative;
        z-index: 1;
        overflow: visible;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    .daily-filter-item {
        min-width: 0;
    }

    .daily-filter-grid > div:has(.daily-dropdown.is-open), .daily-filter-card:has(.daily-dropdown.is-open) {
        z-index: 9999 !important;
        position: relative;
    }

    .daily-filter-card {
        --daily-filter-accent: var(--bri-blue-main);
        --daily-filter-tint: rgba(0, 82, 156, 0.03);
        --daily-filter-badge-bg: var(--loan-blue-soft);
        --daily-filter-badge-border: #d7e6fb;
        height: auto;
        min-height: 0;
        padding: 0.48rem 0.52rem;
        border: 1px solid rgba(219, 229, 239, 0.95);
        border-radius: 8px;
        background: #ffffff;
        box-shadow: none;
        backdrop-filter: none;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        overflow: visible;
        position: relative;
    }

    .daily-filter-card::before {
        content: none;
    }

    .daily-filter-card:hover {
        transform: none;
        border-color: rgba(0, 82, 156, 0.18);
        box-shadow: 0 10px 22px -22px rgba(0, 82, 156, 0.22);
        z-index: 5;
    }

    .daily-filter-card--action {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: stretch;
        background: #ffffff;
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

    .daily-filter-card--mtm {
        --daily-filter-accent: #7c3aed;
        --daily-filter-tint: rgba(124, 58, 237, 0.04);
        --daily-filter-badge-bg: #f3edff;
        --daily-filter-badge-border: #ddd0ff;
    }

    .daily-mtm-override-panel {
        display: none;
    }

    .daily-mtm-override-panel.is-visible {
        display: block;
    }

    .daily-mtm-override-popover {
        position: fixed;
        z-index: 1080;
        top: 16px;
        left: 16px;
        width: min(280px, calc(100vw - 32px));
        padding: 0.85rem;
        background: #ffffff;
        border: 1px solid #ddd0ff;
        border-radius: 14px;
        box-shadow: 0 18px 36px -22px rgba(15, 23, 42, 0.5);
    }

    .daily-mtm-reset {
        border: 0;
        background: transparent;
        color: #7c3aed;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 0.32rem 0.15rem 0 0;
    }

    .daily-mtm-reset:hover {
        color: #5b21b6;
        text-decoration: underline;
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
        margin-top: 0;
        z-index: 6;
        min-width: 0;
    }

    .daily-filter-control-icon {
        position: absolute;
        left: 0.55rem;
        top: 50%;
        transform: translateY(-50%);
        text-align: center;
        color: #5b78a8;
        font-size: 0.72rem;
        pointer-events: none;
        z-index: 90;
        transition: color 0.1s ease;
        width: 1.35rem;
        height: 1.35rem;
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

    .daily-dropdown-toggle:disabled {
        cursor: not-allowed;
        color: #7b8da6;
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
        min-width: 0;
        max-width: 100%;
    }

    .daily-dropdown-toggle {
        width: 100%;
        min-height: 34px;
        border: 1px solid rgba(203, 213, 225, 0.9);
        border-radius: 8px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        color: #3d4c63;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.42rem 0.72rem 0.42rem 2.05rem;
        font-weight: 600;
        font-size: 0.8rem;
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
        top: calc(100% + 0.25rem);
        left: 0;
        right: 0;
        z-index: 10050;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid rgba(219, 229, 239, 0.95);
        border-radius: 8px;
        box-shadow: 0 20px 34px -28px rgba(0, 70, 133, 0.22);
        padding: 0.45rem;
        display: none;
        max-height: min(50vh, 300px);
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
        gap: 0.55rem;
        padding: 0.52rem 0.58rem;
        border-radius: 8px;
        color: var(--text-main);
        text-align: left;
        font-size: 0.8rem;
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
        min-height: 34px;
        padding: 0.42rem 0.8rem;
        border: none;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--bri-blue-dark) 0%, var(--bri-blue-main) 58%, #1d4ed8 100%);
        color: #ffffff;
        font-size: 0.8rem;
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
        justify-content: flex-end;
    }

    .daily-filter-card .daily-filter-label {
        margin-bottom: 0.24rem;
        min-height: 0;
        font-size: 0.62rem;
        letter-spacing: 0.06em;
        color: #4b6285;
    }

    .daily-filter-card--action .daily-filter-label {
        display: none;
    }

    .daily-filter-card--action .daily-apply-button {
        margin-top: 0;
    }

    .daily-filter-action-button-wrap {
        margin-top: 0;
    }

    .daily-dashboard .daily-filter-shell {
        padding: 0.5rem 0.65rem !important;
        min-height: 0 !important;
        height: auto !important;
        background: #ffffff !important;
    }

    .daily-dashboard .daily-filter-grid {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(118px, 0.55fr) !important;
        align-items: end !important;
        gap: 0.45rem !important;
        margin: 0 !important;
    }

    .daily-dashboard .daily-filter-item {
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .daily-dashboard .daily-filter-card {
        min-height: 0 !important;
        height: 54px !important;
        max-height: 54px !important;
        padding: 0.38rem 0.42rem !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        box-shadow: none !important;
        transform: none !important;
        display: block !important;
        overflow: visible !important;
    }

    .daily-dashboard .daily-filter-content,
    .daily-dashboard .daily-filter-action-body {
        display: block !important;
        height: auto !important;
        min-height: 0 !important;
    }

    .daily-dashboard .daily-filter-card .daily-filter-label {
        display: block !important;
        min-height: 0 !important;
        margin: 0 0 0.18rem !important;
        font-size: 0.58rem !important;
        line-height: 1 !important;
    }

    .daily-dashboard .daily-filter-control,
    .daily-dashboard .daily-filter-action-button-wrap {
        height: 30px !important;
        min-height: 30px !important;
        margin: 0 !important;
    }

    .daily-dashboard .daily-dropdown-toggle,
    .daily-dashboard .daily-apply-button {
        height: 30px !important;
        min-height: 30px !important;
        max-height: 30px !important;
        border-radius: 8px !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        font-size: 0.76rem !important;
        line-height: 30px !important;
    }

    .daily-dashboard .daily-dropdown-toggle {
        padding-left: 1.95rem !important;
        padding-right: 0.62rem !important;
    }

    .daily-dashboard .daily-apply-button {
        padding-left: 0.56rem !important;
        padding-right: 0.56rem !important;
        white-space: nowrap !important;
    }

    .daily-dashboard .daily-filter-control-icon {
        left: 0.5rem !important;
        width: 1.2rem !important;
        height: 1.2rem !important;
        font-size: 0.68rem !important;
    }

    .daily-dashboard .daily-filter-card--action .daily-filter-label {
        display: none !important;
    }

    .daily-dashboard .daily-filter-card--action,
    .daily-dashboard .daily-filter-card--action .daily-filter-action-body,
    .daily-dashboard .daily-filter-card--action .daily-filter-action-button-wrap {
        height: 30px !important;
        max-height: 30px !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
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
        max-width: 100%;
        min-width: 0;
        max-height: none;
        height: auto;
        overflow-x: auto; 
        overflow-y: auto; /* Enable native vertical scrolling inside the box */
        border: 1px solid #b7c3d0;
        border-radius: 0;
        box-shadow: none;
        position: relative;
        top: auto;
        background: #ffffff;
        z-index: 1;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    .daily-table-wrap::-webkit-scrollbar {
        height: 10px;
    }
    .daily-table-wrap::-webkit-scrollbar-track {
        background: #f8fafc;
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
        color: #002060;
        font-size: 0.74rem;
    }

    .daily-table th, .daily-table td {
        padding: 0.28rem 0.4rem;
        vertical-align: middle;
        white-space: nowrap;
        border: 1px solid #d9e1ec;
    }

    /* Table Headers — thead sticks as ONE UNIT so all row gaps are covered */
    .daily-table thead {
        position: sticky;
        top: 0;
        z-index: 100;  /* definitively above all tbody content (max tbody z-index is 10) */
    }
    /* Solid backgrounds on tr elements prevent any bleed-through in cell gaps */
    .daily-table thead tr.group-row  { background-color: var(--daily-table-blue); }
    .daily-table thead tr.column-row { background-color: var(--daily-table-blue-mid); }
    .daily-table thead tr.rka-sub-row { background-color: var(--daily-table-rka-sub); }

    .daily-table thead th {
        position: sticky;
        top: 0;
        z-index: 120;
        background: var(--daily-table-blue);
        color: var(--table-header-text);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0;
        text-align: center;
        border: 1px solid #ffffff;
    }
    .daily-table thead tr.group-row th {
        top: 0;
        height: var(--daily-header-group-height);
        z-index: 140;
    }
    .daily-table thead tr.column-row th {
        top: var(--daily-header-column-top);
        height: var(--daily-header-column-height);
        z-index: 130;
        background: var(--daily-table-blue-mid);
        color: #ffffff;
        font-size: 0.68rem;
        border-color: #ffffff;
    }
    .daily-table thead tr.column-row th.delta-col {
        background: var(--daily-table-cyan);
    }
    .daily-table thead tr.rka-sub-row th {
        top: var(--daily-header-rka-top);
        height: var(--daily-header-rka-height);
        z-index: 125;
    }
    .daily-table thead th[rowspan="3"] {
        top: 0;
        z-index: 150 !important;
    }
    .daily-table thead th[rowspan="2"] {
        top: var(--daily-header-column-top);
        z-index: 135;
    }
    .daily-table thead th.rka-period-cell {
        background: var(--daily-table-rka);
        color: #ffffff;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0;
        border: 1px solid #ffffff;
    }
    .daily-table thead tr.rka-sub-row th {
        background: var(--daily-table-rka-sub);
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0;
        border-color: #ffffff;
    }
    .daily-table thead .group-position {
        background: var(--daily-table-blue) !important;
        color: #ffffff;
    }
    .daily-table thead .group-delta {
        background: var(--daily-table-cyan) !important;
        color: #ffffff;
    }
    .daily-table thead .group-rka {
        background: var(--daily-table-rka-sub) !important;
        color: #ffffff;
    }
    .daily-table thead .rka-period-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 100%;
        min-height: 100%;
    }
    .daily-table thead .rka-sub-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 100%;
        min-height: 100%;
    }
    .daily-table thead .header-center {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-width: 100%;
        height: 100%;
        min-height: var(--daily-header-group-height);
        text-align: center;
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
    .daily-table thead th[rowspan="3"] .header-center {
        min-height: calc(var(--daily-header-group-height) + var(--daily-header-column-height) + var(--daily-header-rka-height));
    }
    .daily-table thead th[rowspan="2"] .column-heading {
        min-height: calc(var(--daily-header-column-height) + var(--daily-header-rka-height));
    }

    .daily-table thead .column-heading .main {
        line-height: 1.15;
    }

    /* Table Cells */
    .daily-table tbody td {
        font-size: 0.72rem;
        line-height: 1.18;
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
        color: #002060;
        text-align: right; /* Numeric columns usually right aligned */
        font-variant-numeric: tabular-nums;
    }

    /* Specific Column Alignments */
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
        background-color: #f7fbff;
    }
    .daily-table .delta-col {
        width: var(--daily-delta-width);
        min-width: var(--daily-delta-width);
        background-color: #f7fcff;
        border-left: 1px solid #c9d7e7;
    }
    .daily-table .rka-col {
        width: var(--daily-rka-width);
        min-width: var(--daily-rka-width);
        background-color: #f8f8f8;
        border-left: 1px solid #d0d0d0;
    }
    /* Current/posisi column data cells: light blue like Image 1 */
    .daily-table tbody td.metric-value {
        background-color: #eaf4ff !important;
        color: #0070c0 !important;
    }
    
    /* Stronger group dividers */
    .daily-table .group-delta, .daily-table .delta-col:first-of-type {
        border-left: 2px solid #8fa3b8 !important;
    }
    .daily-table .group-rka, .daily-table .rka-col:first-of-type {
        border-left: 2px solid #8fa3b8 !important;
    }

    .daily-rka-subnote {
        display: block;
        margin-top: 0.05rem;
        font-size: 0.62rem;
        line-height: 1.05;
        color: var(--text-muted);
        font-weight: 600;
    }

    /* Sticky Columns */
    .daily-table .sticky-label {
        position: sticky;
        left: 0;
        z-index: 10;
        background: #ffffff;
        box-shadow: none;
    }
    
    .daily-table thead .sticky-label,
    .daily-table thead .group-label {
        z-index: 160 !important; /* Corner cells: both h & v sticky — must be above regular header cells */
        background: var(--daily-table-blue);
        box-shadow: none;
        text-align: center !important;
    }

    /* Row Hover and Striping */
    .daily-table tbody tr {
        transition: none;
    }
    .daily-table tbody tr:nth-child(even) > td {
        background-color: #f5f7fa;
    }
    .daily-table tbody tr:nth-child(even) > .sticky-label {
        background-color: #fbfdff;
    }
    .daily-table tbody tr:hover {
        background-color: #eaf5ff;
    }
    .daily-table tbody tr:hover .sticky-label {
        background-color: #fff7cc !important;
    }

    /* Hierarchical Rows Styling */
    .daily-table .metric-block-simpanan td,
    .daily-table .metric-block-os td,
    .daily-table .metric-block-sml td,
    .daily-table .metric-block-npl td,
    .daily-table .metric-block-casa td,
    .daily-table .metric-block-ldr td,
    .daily-table .metric-block-recdh td {
        background-color: #f2f2f2; /* flatter total rows like the reference table */
        font-weight: 700;
        color: #002060;
    }
    .daily-table .metric-block-simpanan .sticky-label,
    .daily-table .metric-block-os .sticky-label,
    .daily-table .metric-block-sml .sticky-label,
    .daily-table .metric-block-npl .sticky-label,
    .daily-table .metric-block-casa .sticky-label,
    .daily-table .metric-block-ldr .sticky-label,
    .daily-table .metric-block-recdh .sticky-label {
        background-color: #f2f2f2;
    }

    /* Conditional format for % achievement cells */
    .pct-achieve-good {
        background-color: #c6efce !important;
        color: #276221 !important;
        font-weight: 700;
    }
    .pct-achieve-warn {
        background-color: #ffeb9c !important;
        color: #7d5a00 !important;
        font-weight: 700;
    }
    .pct-achieve-bad {
        background-color: #ffc7ce !important;
        color: #9c0006 !important;
        font-weight: 700;
    }

    .daily-table tbody tr.metric-block-simpanan:hover td,
    .daily-table tbody tr.metric-block-os:hover td,
    .daily-table tbody tr.metric-block-sml:hover td,
    .daily-table tbody tr.metric-block-npl:hover td,
    .daily-table tbody tr.metric-block-casa:hover td,
    .daily-table tbody tr.metric-block-ldr:hover td,
    .daily-table tbody tr.metric-block-recdh:hover td {
        background-color: #e3f1ff;
    }
    .daily-table tbody tr.metric-block-simpanan:hover .sticky-label,
    .daily-table tbody tr.metric-block-os:hover .sticky-label,
    .daily-table tbody tr.metric-block-sml:hover .sticky-label,
    .daily-table tbody tr.metric-block-npl:hover .sticky-label,
    .daily-table tbody tr.metric-block-casa:hover .sticky-label,
    .daily-table tbody tr.metric-block-ldr:hover .sticky-label,
    .daily-table tbody tr.metric-block-recdh:hover .sticky-label {
        background-color: #e3f1ff;
    }

    .daily-table .metric-label,
    .daily-table .cell-text {
        line-height: 1.15;
    }

    .daily-table .row-depth-1 .metric-label { padding-left: 0.2rem; font-weight: 700; color: #002060; }
    .daily-table .row-depth-2 .metric-label { padding-left: 0.75rem; color: #002060; font-weight: 500;}
    .daily-table .row-depth-3 .metric-label { padding-left: 1.3rem; color: #5f6f85; font-size: 0.68rem; }

    /* Sub-Segment Highlights (Contrast but pleasant) */
    .section-ritel td, 
    .section-mikro td, 
    .section-wholesale td, 
    .section-consumer td, 
    .section-commercial td {
        background: #eaf6ff !important;
        border-top: 1px solid #cbd5e1 !important;
        border-bottom: 1px solid #cbd5e1 !important;
    }

    .section-ritel .sticky-label, 
    .section-mikro .sticky-label, 
    .section-wholesale .sticky-label, 
    .section-consumer .sticky-label, 
    .section-commercial .sticky-label {
        background: #eaf6ff !important;
        border-left: 4px solid var(--daily-table-blue) !important;
        color: #002060 !important;
        font-weight: 800 !important;
        text-transform: uppercase;
    }

    .section-ritel .metric-label, 
    .section-mikro .metric-label, 
    .section-wholesale .metric-label, 
    .section-consumer .metric-label, 
    .section-commercial .metric-label {
        font-weight: 800 !important;
        color: #002060 !important;
        letter-spacing: 0.01em;
    }

    /* Row Hover and Striping */
    .delta-positive { color: #0f172a !important; font-weight: 600; } /* dark/black like Image 1 */
    .delta-negative { color: #dc2626 !important; font-weight: 700; } /* red-600 for negatives in parentheses */
    /* Quality metrics (SML/NPL): reversed — negative = good (green), positive = bad (red) */
    .delta-quality-good { color: #16a34a !important; font-weight: 700; } /* green: quality improved (value went down) */
    .delta-quality-bad  { color: #dc2626 !important; font-weight: 700; } /* red: quality worsened (value went up) */

    /* Keep achievement heatmap visible on sub-segment/total rows without coloring non-gap columns. */
    .daily-table tbody td.rka-col.pct-achieve-good,
    .daily-table tbody tr:hover td.rka-col.pct-achieve-good,
    .daily-table tbody tr.metric-block-simpanan:hover td.rka-col.pct-achieve-good,
    .daily-table tbody tr.metric-block-os:hover td.rka-col.pct-achieve-good,
    .daily-table tbody tr.metric-block-sml:hover td.rka-col.pct-achieve-good,
    .daily-table tbody tr.metric-block-npl:hover td.rka-col.pct-achieve-good,
    .daily-table tbody tr.metric-block-casa:hover td.rka-col.pct-achieve-good,
    .daily-table tbody tr.metric-block-ldr:hover td.rka-col.pct-achieve-good,
    .daily-table tbody tr.metric-block-recdh:hover td.rka-col.pct-achieve-good {
        background-color: #c6efce !important;
        color: #276221 !important;
        font-weight: 800 !important;
    }

    .daily-table tbody td.rka-col.pct-achieve-warn,
    .daily-table tbody tr:hover td.rka-col.pct-achieve-warn,
    .daily-table tbody tr.metric-block-simpanan:hover td.rka-col.pct-achieve-warn,
    .daily-table tbody tr.metric-block-os:hover td.rka-col.pct-achieve-warn,
    .daily-table tbody tr.metric-block-sml:hover td.rka-col.pct-achieve-warn,
    .daily-table tbody tr.metric-block-npl:hover td.rka-col.pct-achieve-warn,
    .daily-table tbody tr.metric-block-casa:hover td.rka-col.pct-achieve-warn,
    .daily-table tbody tr.metric-block-ldr:hover td.rka-col.pct-achieve-warn,
    .daily-table tbody tr.metric-block-recdh:hover td.rka-col.pct-achieve-warn {
        background-color: #ffeb9c !important;
        color: #7d5a00 !important;
        font-weight: 800 !important;
    }

    .daily-table tbody td.rka-col.pct-achieve-bad,
    .daily-table tbody tr:hover td.rka-col.pct-achieve-bad,
    .daily-table tbody tr.metric-block-simpanan:hover td.rka-col.pct-achieve-bad,
    .daily-table tbody tr.metric-block-os:hover td.rka-col.pct-achieve-bad,
    .daily-table tbody tr.metric-block-sml:hover td.rka-col.pct-achieve-bad,
    .daily-table tbody tr.metric-block-npl:hover td.rka-col.pct-achieve-bad,
    .daily-table tbody tr.metric-block-casa:hover td.rka-col.pct-achieve-bad,
    .daily-table tbody tr.metric-block-ldr:hover td.rka-col.pct-achieve-bad,
    .daily-table tbody tr.metric-block-recdh:hover td.rka-col.pct-achieve-bad {
        background-color: #ffc7ce !important;
        color: #9c0006 !important;
        font-weight: 800 !important;
    }
    
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
        font-size: 0.68rem;
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

    @media (max-width: 1199.98px) {
        .daily-panel-head {
            align-items: stretch !important;
        }

        .daily-panel-title-group,
        .daily-panel-actions {
            flex-basis: 100%;
            width: 100%;
        }

        .daily-panel-actions {
            justify-content: flex-start;
        }

        .daily-panel-actions .btn-export-all {
            flex: 1 1 min(180px, calc(50% - 0.25rem));
            text-align: center;
        }

        .daily-dashboard .daily-filter-grid {
            grid-template-columns: repeat(2, minmax(min(100%, 220px), 1fr)) !important;
        }

        .daily-dashboard .daily-filter-item--action {
            grid-column: 1 / -1 !important;
        }

        .daily-table-panel {
            padding-left: clamp(0.45rem, 1.4vw, 0.85rem);
            padding-right: clamp(0.45rem, 1.4vw, 0.85rem);
        }
    }

    @media (max-width: 991.98px) {
        .daily-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .daily-dashboard {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .daily-surface {
            border-radius: 8px;
        }

        .daily-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.45rem;
        }

        .daily-dashboard .daily-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 0.42rem !important;
        }

        .daily-filter-item--action {
            grid-column: span 2;
        }

        .daily-dashboard .daily-filter-item--action {
            grid-column: span 2 !important;
        }

        .daily-filter-card {
            padding: 0.42rem;
        }
    }

    @media (max-width: 575.98px) {
        .daily-dashboard {
            --daily-label-width: 164px;
            --daily-position-width: 80px;
            --daily-delta-width: 78px;
            --daily-rka-width: 78px;
        }

        .daily-panel-head {
            padding-left: 0.8rem !important;
            padding-right: 0.8rem !important;
            flex-direction: column;
            align-items: flex-start !important;
            gap: 0.55rem;
        }

        .daily-panel-title {
            font-size: 1.15rem;
            overflow-wrap: anywhere;
        }

        .daily-panel-title-group,
        .daily-panel-actions {
            flex-basis: 100%;
            width: 100%;
        }

        .daily-panel-actions {
            justify-content: stretch;
        }

        .daily-panel-actions .btn-export-all {
            flex: 1 1 min(150px, calc(50% - 0.25rem));
            text-align: center;
        }

        .daily-filter-shell {
            padding: 0.55rem;
        }

        .daily-filter-grid {
            grid-template-columns: 1fr;
            gap: 0.42rem;
        }

        .daily-dashboard .daily-filter-grid {
            grid-template-columns: 1fr !important;
        }

        .daily-filter-item--action {
            grid-column: auto;
        }

        .daily-dashboard .daily-filter-item--action {
            grid-column: auto !important;
        }

        .daily-filter-card {
            min-height: 0;
            padding: 0.42rem;
        }

        .daily-filter-select,
        .daily-dropdown-toggle,
        .daily-apply-button {
            min-height: 38px;
            font-size: 0.8rem;
        }

        .daily-table-region {
            padding-bottom: 1rem;
            margin-left: 0;
            margin-right: 0;
        }

        .daily-table-panel {
            padding: 0.55rem 0.5rem 0.75rem;
        }

        .daily-table-region::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 1rem;
            width: 22px;
            pointer-events: none;
            z-index: 30;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(15, 23, 42, 0.08));
            border-radius: 0;
        }

        .daily-table-wrap {
            max-height: min(72vh, 640px);
            border-radius: 0;
            box-shadow: none;
            width: 100%;
            max-width: 100%;
            overflow-x: auto !important;
        }

        .daily-table-wrap::-webkit-scrollbar {
            height: 7px;
            width: 7px;
        }

        .daily-table thead th {
            font-size: 0.62rem;
            padding: 0.42rem 0.18rem;
            letter-spacing: 0;
            line-height: 1.15;
        }

        .daily-table thead .group-row th {
            font-size: 0.64rem;
            padding-top: 0.46rem;
            padding-bottom: 0.46rem;
        }

        .daily-table thead .column-heading {
            min-height: 38px;
            padding: 2px 0;
        }

        .daily-table thead .column-heading .main {
            font-size: 0.66rem;
            font-weight: 800;
        }

        .daily-table tbody td {
            font-size: 0.7rem;
            padding: 0.28rem 0.24rem;
            line-height: 1.15;
        }

        .daily-table .sticky-label,
        .daily-table .group-label {
            padding-left: 0.42rem;
            padding-right: 0.42rem;
            white-space: normal;
            line-height: 1.18;
        }

        .daily-table .metric-label {
            display: block;
            max-width: calc(var(--daily-label-width) - 0.9rem);
        }

        .daily-table .cell-text {
            display: inline-block;
            max-width: 100%;
        }

        .daily-table .metric-value .cell-text {
            font-weight: 800;
        }

        /* Compact Indentation for hierarchy on mobile */
        .daily-table .row-depth-1 .metric-label { padding-left: 0.2rem; }
        .daily-table .row-depth-2 .metric-label { padding-left: 0.58rem; }
        .daily-table .row-depth-3 .metric-label { padding-left: 0.95rem; font-size: 0.66rem; }

        .daily-table col.numeric-col {
            width: 80px !important;
        }

        .header-subnote {
            margin-top: 3px;
            padding-top: 3px;
            font-size: 0.56rem;
            line-height: 1.05;
            letter-spacing: 0;
        }

        .section-ritel td,
        .section-mikro td,
        .section-wholesale td,
        .section-consumer td,
        .section-commercial td {
            background: #eef5ff !important;
            border-top-color: #b9cbe3 !important;
            border-bottom-color: #b9cbe3 !important;
        }

        .section-ritel .sticky-label,
        .section-mikro .sticky-label,
        .section-wholesale .sticky-label,
        .section-consumer .sticky-label,
        .section-commercial .sticky-label {
            border-left: 3px solid var(--primary-blue) !important;
            background: #eaf2ff !important;
        }

        .daily-table .metric-block-simpanan td,
        .daily-table .metric-block-os td,
        .daily-table .metric-block-sml td,
        .daily-table .metric-block-npl td,
        .daily-table .metric-block-casa td,
        .daily-table .metric-block-ldr td,
        .daily-table .metric-block-recdh td,
        .daily-table .metric-block-simpanan .sticky-label,
        .daily-table .metric-block-os .sticky-label,
        .daily-table .metric-block-sml .sticky-label,
        .daily-table .metric-block-npl .sticky-label,
        .daily-table .metric-block-casa .sticky-label,
        .daily-table .metric-block-ldr .sticky-label,
        .daily-table .metric-block-recdh .sticky-label {
            background-color: #f2f2f2 !important;
        }

        .daily-kpi-grid {
            grid-template-columns: 1fr;
        }

        .daily-kpi {
            padding: 1.15rem;
        }

        .daily-kpi .value {
            font-size: 1.5rem;
        }
    }

    @media (max-width: 359.98px) {
        .daily-panel-actions .btn-export-all {
            flex-basis: 100%;
        }
    }

    @media (max-width: 991.98px), (max-height: 760px) {
        .daily-panel-head {
            gap: 0.65rem;
        }

        .daily-panel-head .d-flex {
            flex-wrap: wrap;
            justify-content: flex-start !important;
        }

        .daily-filter-shell {
            margin-bottom: 0;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .daily-filter-card {
            min-height: 0;
            padding: 0.42rem 0.48rem;
            border-radius: 8px;
        }

        .daily-filter-card .daily-filter-label {
            min-height: 0;
            margin-bottom: 0.22rem;
            font-size: 0.64rem;
        }

        .daily-filter-control,
        .daily-filter-select,
        .daily-dropdown-toggle,
        .daily-apply-button {
            min-height: 34px;
            font-size: 0.78rem;
        }

        .daily-filter-note {
            display: none;
        }

        .daily-table-wrap {
            position: relative !important;
            top: auto !important;
            max-height: max(240px, min(68dvh, 640px)) !important;
            min-height: min(320px, 54dvh);
            overflow: auto !important;
        }
    }

    @media (orientation: landscape) and (max-height: 640px) {
        .daily-panel-head {
            padding-top: 0.6rem !important;
            padding-bottom: 0.6rem !important;
        }

        .daily-title-badge,
        .daily-panel-desc {
            display: none !important;
        }

        .daily-filter-shell {
            padding: 0.42rem 0.52rem !important;
        }

        .daily-filter-grid {
            grid-template-columns: repeat(4, minmax(120px, 1fr)) minmax(116px, 0.72fr);
            gap: 0.38rem;
        }

        .daily-dashboard .daily-filter-grid {
            grid-template-columns: repeat(4, minmax(120px, 1fr)) minmax(104px, 0.5fr) !important;
            gap: 0.36rem !important;
        }

        .daily-dashboard .daily-filter-item--action {
            grid-column: auto !important;
        }

        .daily-filter-card {
            min-height: 0;
        }

        .daily-table-wrap {
            max-height: max(220px, min(60dvh, 520px)) !important;
            min-height: min(220px, 48dvh);
            overflow: auto !important;
        }

        .daily-table-panel {
            padding-top: 0.42rem;
            padding-bottom: 0.52rem;
        }

        .daily-table th,
        .daily-table td {
            padding-top: 0.16rem !important;
            padding-bottom: 0.16rem !important;
        }
    }

    @media (orientation: portrait) and (max-width: 1199.98px) {
        #daily-dashboard-root .daily-filter-shell {
            padding: 0.72rem !important;
            overflow: visible !important;
        }

        #daily-dashboard-root .daily-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            align-items: stretch !important;
            gap: 0.72rem !important;
            overflow: visible !important;
        }

        #daily-dashboard-root .daily-filter-item,
        #daily-dashboard-root .daily-filter-card,
        #daily-dashboard-root .daily-filter-content,
        #daily-dashboard-root .daily-filter-action-body {
            min-width: 0 !important;
            max-width: 100% !important;
        }

        #daily-dashboard-root .daily-filter-card {
            display: flex !important;
            height: auto !important;
            min-height: 74px !important;
            max-height: none !important;
            padding: 0.62rem 0.68rem !important;
            overflow: visible !important;
        }

        #daily-dashboard-root .daily-filter-content,
        #daily-dashboard-root .daily-filter-action-body {
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-end !important;
            height: 100% !important;
            min-height: 0 !important;
        }

        #daily-dashboard-root .daily-filter-card .daily-filter-label {
            display: block !important;
            margin: 0 0 0.38rem !important;
            font-size: 0.62rem !important;
            line-height: 1.15 !important;
        }

        #daily-dashboard-root .daily-filter-control,
        #daily-dashboard-root .daily-filter-action-button-wrap {
            height: auto !important;
            min-height: 40px !important;
            margin: 0 !important;
        }

        #daily-dashboard-root .daily-dropdown,
        #daily-dashboard-root .daily-dropdown-toggle {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
        }

        #daily-dashboard-root .daily-dropdown-toggle,
        #daily-dashboard-root .daily-apply-button {
            display: flex !important;
            align-items: center !important;
            height: 40px !important;
            min-height: 40px !important;
            max-height: none !important;
            line-height: 1.15 !important;
            font-size: 0.8rem !important;
        }

        #daily-dashboard-root .daily-dropdown-toggle {
            padding-left: 2.15rem !important;
            padding-right: 0.78rem !important;
        }

        #daily-dashboard-root .daily-filter-item--action {
            grid-column: 1 / -1 !important;
        }

        #daily-dashboard-root .daily-filter-card--action {
            width: 100% !important;
            min-height: 48px !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
        }

        #daily-dashboard-root .daily-filter-card--action .daily-filter-label {
            display: none !important;
        }

        #daily-dashboard-root .daily-filter-card--action,
        #daily-dashboard-root .daily-filter-card--action .daily-filter-action-body,
        #daily-dashboard-root .daily-filter-card--action .daily-filter-action-button-wrap {
            width: 100% !important;
            height: auto !important;
            max-height: none !important;
        }

        #daily-dashboard-root .daily-apply-button {
            justify-content: center !important;
            width: 100% !important;
            white-space: normal !important;
        }
    }

    @media (max-width: 575.98px) {
        #daily-dashboard-root .daily-filter-grid {
            grid-template-columns: 1fr !important;
        }

        #daily-dashboard-root .daily-filter-item--action {
            grid-column: auto !important;
        }
    }

    /* Responsive hardening: keeps Keragaan Harian controls predictable on desktop, tablet portrait, and phone. */
    #daily-dashboard-root.daily-dashboard {
        container-type: inline-size;
        overflow-x: clip;
    }

    #daily-dashboard-root .daily-panel-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.6rem 0.85rem;
    }

    #daily-dashboard-root .daily-panel-title-group {
        flex: 1 1 320px;
        min-width: 0;
    }

    #daily-dashboard-root .daily-panel-actions {
        flex: 0 1 auto;
        min-width: min(100%, 260px);
        gap: 0.45rem;
    }

    #daily-dashboard-root .daily-panel-actions .btn-export-all {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 126px;
        min-height: 34px;
        margin: 0 !important;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #daily-dashboard-root .daily-filter-shell {
        overflow: visible !important;
        padding: clamp(0.62rem, 1.1vw, 0.85rem) !important;
    }

    #daily-dashboard-root .daily-filter-grid {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(126px, 0.58fr) !important;
        grid-template-areas: "kanca unit posisi rka action";
        align-items: stretch !important;
        gap: 0.58rem !important;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: visible !important;
    }

    #daily-dashboard-root .daily-filter-grid > .daily-filter-item:nth-child(1) { grid-area: kanca; }
    #daily-dashboard-root .daily-filter-grid > .daily-filter-item:nth-child(2) { grid-area: unit; }
    #daily-dashboard-root .daily-filter-grid > .daily-filter-item:nth-child(3) { grid-area: posisi; }
    #daily-dashboard-root .daily-filter-grid > .daily-filter-item:nth-child(4) { grid-area: rka; }
    #daily-dashboard-root .daily-filter-grid > .daily-filter-item:nth-child(5) { grid-area: action; }

    #daily-dashboard-root .daily-filter-item,
    #daily-dashboard-root .daily-filter-card,
    #daily-dashboard-root .daily-filter-content,
    #daily-dashboard-root .daily-filter-action-body {
        min-width: 0 !important;
        max-width: 100% !important;
    }

    #daily-dashboard-root .daily-filter-card {
        display: flex !important;
        height: auto !important;
        min-height: 66px !important;
        max-height: none !important;
        padding: 0.54rem 0.6rem !important;
        overflow: visible !important;
    }

    #daily-dashboard-root .daily-filter-content,
    #daily-dashboard-root .daily-filter-action-body {
        display: flex !important;
        flex-direction: column !important;
        justify-content: flex-end !important;
        min-height: 0 !important;
        width: 100%;
    }

    #daily-dashboard-root .daily-filter-card .daily-filter-label {
        display: block !important;
        margin: 0 0 0.3rem !important;
        min-height: auto !important;
        line-height: 1.12 !important;
        white-space: nowrap;
    }

    #daily-dashboard-root .daily-filter-control,
    #daily-dashboard-root .daily-filter-action-button-wrap {
        height: auto !important;
        min-height: 38px !important;
        margin: 0 !important;
    }

    #daily-dashboard-root .daily-dropdown,
    #daily-dashboard-root .daily-dropdown-toggle {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }

    #daily-dashboard-root .daily-dropdown-toggle,
    #daily-dashboard-root .daily-apply-button {
        display: flex !important;
        align-items: center !important;
        min-height: 38px !important;
        height: auto !important;
        max-height: none !important;
        line-height: 1.15 !important;
        font-size: 0.8rem !important;
    }

    #daily-dashboard-root .daily-dropdown-toggle {
        padding: 0.48rem 0.76rem 0.48rem 2.12rem !important;
    }

    #daily-dashboard-root .daily-dropdown-toggle-text {
        min-width: 0;
    }

    #daily-dashboard-root .daily-dropdown.is-open {
        z-index: 10060 !important;
    }

    #daily-dashboard-root .daily-dropdown-menu {
        z-index: 10070 !important;
        max-height: min(46vh, 320px);
    }

    #daily-dashboard-root .daily-filter-card--action {
        justify-content: flex-end !important;
    }

    #daily-dashboard-root .daily-filter-card--action .daily-filter-label {
        display: none !important;
    }

    #daily-dashboard-root .daily-filter-card--action,
    #daily-dashboard-root .daily-filter-card--action .daily-filter-action-body,
    #daily-dashboard-root .daily-filter-card--action .daily-filter-action-button-wrap {
        height: auto !important;
        max-height: none !important;
    }

    #daily-dashboard-root .daily-apply-button {
        justify-content: center !important;
        width: 100% !important;
        padding-left: 0.72rem !important;
        padding-right: 0.72rem !important;
        white-space: nowrap !important;
    }

    #daily-dashboard-root .daily-table-panel {
        overflow: hidden;
    }

    #daily-dashboard-root .daily-table-region,
    #daily-dashboard-root .daily-table-wrap {
        max-width: 100%;
        min-width: 0;
    }

    #daily-dashboard-root .daily-table-wrap {
        overflow: auto !important;
        -webkit-overflow-scrolling: touch;
        touch-action: pan-x pan-y;
    }

    .daily-filter-mobile-toggle {
        display: none;
        width: 100%;
        background: #ffffff;
        border: 1px solid rgba(219, 229, 239, 0.9);
        border-radius: 10px;
        padding: 0.35rem 0.5rem;
        margin-bottom: 0.25rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    }

    .btn-filter-toggle {
        background: transparent;
        border: none;
        padding: 0.42rem 0.62rem;
        width: 100%;
        text-align: left;
        color: #00529C;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
    }

    .btn-filter-toggle:focus {
        outline: none;
        box-shadow: none;
    }

    .btn-filter-toggle .toggle-arrow-icon {
        transition: transform 0.2s ease;
        color: #8b9eb7;
    }

    .daily-filter-shell.is-open .toggle-arrow-icon {
        transform: rotate(180deg);
    }

    @media (max-width: 1399.98px) {
        #daily-dashboard-root .daily-panel-head {
            padding: 0.35rem 0.75rem !important;
            min-height: 40px !important;
            flex-wrap: nowrap !important;
        }

        #daily-dashboard-root .daily-panel-title-group {
            flex: 0 1 auto !important;
            width: auto !important;
            min-width: 0 !important;
        }

        #daily-dashboard-root .daily-panel-title {
            font-size: 0.95rem !important;
            font-weight: 800 !important;
            white-space: nowrap;
        }

        #daily-dashboard-root .daily-panel-actions {
            flex: 0 1 auto !important;
            width: auto !important;
            min-width: 0 !important;
            justify-content: flex-end !important;
            gap: 0.35rem !important;
        }

        #daily-dashboard-root .daily-panel-actions .btn-export-all {
            min-width: auto !important;
            min-height: 36px !important;
            height: 36px !important;
            font-size: 0.72rem !important;
            padding: 0.2rem 0.5rem !important;
        }

        .daily-filter-mobile-toggle {
            display: flex !important;
        }

        #daily-dashboard-root .daily-filter-grid {
            display: none !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            grid-template-areas:
                "kanca unit"
                "posisi rka"
                "action action" !important;
            gap: 0.5rem !important;
            margin-top: 0.5rem;
        }

        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-grid {
            display: grid !important;
        }

        #daily-dashboard-root .daily-filter-card {
            min-height: 52px !important;
            padding: 0.35rem 0.45rem !important;
        }

        #daily-dashboard-root .daily-filter-label {
            font-size: 0.68rem !important;
            margin-bottom: 0.15rem !important;
        }

        #daily-dashboard-root .daily-filter-control,
        #daily-dashboard-root .daily-filter-control-icon,
        #daily-dashboard-root .daily-dropdown-toggle,
        #daily-dashboard-root .daily-apply-button {
            min-height: 32px !important;
            height: 32px !important;
            font-size: 0.76rem !important;
        }

        #daily-dashboard-root .daily-dropdown-toggle {
            padding: 0.2rem 0.62rem 0.2rem 1.62rem !important;
        }

        #daily-dashboard-root .daily-filter-control-icon {
            left: 0.45rem !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            font-size: 0.72rem !important;
        }

        #daily-dashboard-root .daily-filter-card--action {
            min-height: 36px !important;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
        }

        #daily-dashboard-root .daily-apply-button {
            min-height: 32px !important;
            white-space: normal !important;
        }

        #daily-dashboard-root .daily-table-panel {
            padding: 0.4rem 0.5rem !important;
        }
    }

    @media (max-width: 640px) {
        #daily-dashboard-root .daily-panel-head {
            flex-wrap: wrap !important;
            padding: 0.5rem !important;
            min-height: auto !important;
        }

        #daily-dashboard-root .daily-panel-title-group,
        #daily-dashboard-root .daily-panel-actions {
            flex: 1 1 100% !important;
            width: 100% !important;
        }

        #daily-dashboard-root .daily-panel-title {
            max-width: 100%;
            font-size: clamp(0.82rem, 5.2vw, 0.95rem) !important;
            line-height: 1.25 !important;
            white-space: normal !important;
            overflow-wrap: anywhere;
        }

        #daily-dashboard-root .daily-panel-actions {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            justify-content: stretch !important;
            margin-top: 0.4rem;
        }

        #daily-dashboard-root .daily-panel-actions .btn-export-all {
            width: 100% !important;
            min-height: 36px !important;
            height: 36px !important;
        }

        #daily-dashboard-root .daily-filter-grid {
            grid-template-columns: 1fr !important;
            grid-template-areas:
                "kanca"
                "unit"
                "posisi"
                "rka"
                "action" !important;
            gap: 0.45rem !important;
        }

        #daily-dashboard-root .daily-filter-card {
            min-height: 52px !important;
            padding: 0.35rem 0.45rem !important;
        }
    }

    /* Use the compact filter summary on every viewport. Desktop expands only on demand. */
    #daily-dashboard-root .daily-filter-mobile-toggle {
        display: flex !important;
        margin-bottom: 0;
    }

    #daily-dashboard-root .btn-filter-toggle {
        min-height: 42px;
        padding: 0.45rem 0.68rem;
        border-radius: 8px;
    }

    #daily-dashboard-root .active-filters-badge {
        min-width: 0;
        max-width: min(58vw, 720px);
        margin-left: auto;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #daily-dashboard-root .daily-filter-grid {
        display: none !important;
    }

    #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-grid {
        display: grid !important;
        align-items: start !important;
    }

    @media (min-width: 1400px) {
        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(164px, 0.72fr) !important;
            grid-template-areas: "kanca unit posisi rka action" !important;
            margin-top: 0.58rem;
        }

        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-card {
            height: 58px !important;
            min-height: 58px !important;
            max-height: 58px !important;
            padding: 0.36rem 0.48rem !important;
        }

        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-content,
        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-action-body {
            display: block !important;
            height: auto !important;
        }

        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-card .daily-filter-label {
            margin: 0 0 0.16rem !important;
            font-size: 0.58rem !important;
            line-height: 1 !important;
        }

        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-control,
        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-action-button-wrap,
        #daily-dashboard-root .daily-filter-shell.is-open .daily-dropdown-toggle,
        #daily-dashboard-root .daily-filter-shell.is-open .daily-apply-button {
            height: 30px !important;
            min-height: 30px !important;
            max-height: 30px !important;
        }

        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-item--action {
            min-width: 164px !important;
        }

        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-card--action,
        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-card--action .daily-filter-action-body,
        #daily-dashboard-root .daily-filter-shell.is-open .daily-filter-card--action .daily-filter-action-button-wrap,
        #daily-dashboard-root .daily-filter-shell.is-open .daily-apply-button {
            width: 100% !important;
        }
    }
</style>
@endsection

@section('content')
<div class="daily-dashboard pt-4" id="daily-dashboard-root">
    <div class="daily-surface" id="daily-surface">
        <div class="daily-panel-head px-4 py-3 d-flex justify-content-between align-items-center">
            <div class="daily-panel-title-group d-flex align-items-center">
                <h1 class="daily-panel-title">DASHBOARD KERAGAAN HARIAN</h1>
            </div>
            <div class="daily-panel-actions d-flex align-items-center">
                <a id="exportExcelBtn" href="{{ route('dashboard.harian.export') }}" class="btn btn-sm btn-export-all mr-2">
                    <i class="fas fa-file-excel mr-1"></i> EXPORT EXCEL
                </a>
                <button id="captureAllBtn" class="btn btn-sm btn-export-all">
                    <i class="fas fa-file-image mr-1"></i> EXPORT A4
                </button>
            </div>

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
            <div class="daily-filter-mobile-toggle">
                <button type="button" class="btn btn-filter-toggle d-flex align-items-center justify-content-between w-100" id="btn-toggle-filters" aria-expanded="false" aria-controls="daily-filter-grid">
                    <span class="btn-toggle-text text-truncate font-weight-bold"><i class="fas fa-sliders-h mr-2"></i> FILTER DATA</span>
                    <span class="active-filters-badge text-truncate text-muted small" id="filter-summary-badge">Area 6</span>
                    <i class="fas fa-chevron-down toggle-arrow-icon ml-2"></i>
                </button>
            </div>
            <div class="daily-filter-grid" id="daily-filter-grid">
                <div class="daily-filter-item">
                    <div class="daily-filter-card daily-filter-card--kanca">
                        <div class="daily-filter-content">
                            <label class="daily-filter-label" for="filter-kanca">Kanca</label>
                            <div class="daily-filter-control">
                                <span class="daily-filter-control-icon"><i class="fas fa-building"></i></span>
                                <div class="daily-dropdown" data-daily-dropdown="kanca">
                                    <button type="button" class="daily-dropdown-toggle" data-daily-dropdown-toggle="kanca" aria-haspopup="listbox" aria-expanded="false">
                                        <span class="daily-dropdown-toggle-text text-truncate">Area 6</span>
                                        <i class="fas fa-chevron-down daily-dropdown-toggle-icon"></i>
                                    </button>
                                    <div class="daily-dropdown-menu" data-daily-dropdown-menu="kanca"></div>
                                    <select id="filter-kanca" name="kanca" class="form-control daily-filter-native" multiple></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="daily-filter-item">
                    <div class="daily-filter-card daily-filter-card--unit">
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
                <div class="daily-filter-item">
                    <div class="daily-filter-card daily-filter-card--posisi">
                        <div class="daily-filter-content">
                            <label class="daily-filter-label" for="filter-posisi-terakhir">Posisi Terakhir</label>
                            <div class="daily-filter-control">
                                <span class="daily-filter-control-icon"><i class="fas fa-calendar-day"></i></span>
                                <div class="daily-dropdown" data-daily-dropdown="posisi">
                                    <button type="button" class="daily-dropdown-toggle" data-daily-dropdown-toggle="posisi" aria-haspopup="listbox" aria-expanded="false">
                                        <span class="daily-dropdown-toggle-text text-truncate">Belum ada data</span>
                                        <i class="fas fa-chevron-down daily-dropdown-toggle-icon"></i>
                                    </button>
                                    <div class="daily-dropdown-menu" data-daily-dropdown-menu="posisi"></div>
                                    <select id="filter-posisi-terakhir" class="form-control daily-filter-native"></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="daily-filter-item">
                    <div class="daily-filter-card daily-filter-card--rka">
                        <div class="daily-filter-content">
                            <label class="daily-filter-label" for="filter-posisi-rka">Posisi RKA</label>
                            <div class="daily-filter-control">
                                <span class="daily-filter-control-icon"><i class="fas fa-bullseye"></i></span>
                                <div class="daily-dropdown" data-daily-dropdown="rka">
                                    <button type="button" class="daily-dropdown-toggle" data-daily-dropdown-toggle="rka" aria-haspopup="listbox" aria-expanded="false">
                                        <span class="daily-dropdown-toggle-text text-truncate">Belum ada data</span>
                                        <i class="fas fa-chevron-down daily-dropdown-toggle-icon"></i>
                                    </button>
                                    <div class="daily-dropdown-menu" data-daily-dropdown-menu="rka"></div>
                                    <select id="filter-posisi-rka" class="form-control daily-filter-native"></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="daily-filter-item daily-filter-item--action">
                    <div class="daily-filter-card daily-filter-card--action">
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

        <div class="daily-table-panel bg-white">
        <div class="daily-table-region" data-table-region>
            <div class="daily-table-wrap">
                    <table class="table table-hover daily-table">
                        <colgroup>
                            <col style="width: var(--daily-label-width);">
                            <col style="width: var(--daily-position-width);" class="numeric-col">
                            <col style="width: var(--daily-position-width);" class="numeric-col">
                            <col style="width: var(--daily-position-width);" class="numeric-col">
                            <col style="width: var(--daily-position-width);" class="numeric-col">
                            <col style="width: var(--daily-position-width);" class="numeric-col">
                            <col style="width: var(--daily-position-width);" class="numeric-col">
                            <col style="width: var(--daily-position-width);" class="numeric-col position-col-h1">
                            <col style="width: var(--daily-position-width);" class="numeric-col">
                            <col style="width: var(--daily-delta-width);" class="numeric-col">
                            <col style="width: var(--daily-delta-width);" class="numeric-col">
                            <col style="width: var(--daily-delta-width);" class="numeric-col">
                            <col style="width: var(--daily-delta-width);" class="numeric-col">
                            <col style="width: var(--daily-delta-width);" class="numeric-col">
                            <col style="width: var(--daily-rka-width);" class="numeric-col">
                            <col style="width: var(--daily-rka-width);" class="numeric-col">
                            <col style="width: var(--daily-rka-width);" class="numeric-col">
                            <col style="width: var(--daily-rka-width);" class="numeric-col">
                            <col style="width: var(--daily-rka-width);" class="numeric-col">
                            <col style="width: var(--daily-rka-width);" class="numeric-col">
                        </colgroup>
                        <thead>
                            <tr class="group-row text-center">
                                <th class="sticky-label group-label" rowspan="3"><span class="header-center">Keterangan</span></th>
                                <th class="group-position" colspan="7" data-position-group-colspan><span class="header-center">Posisi</span></th>
                                <th class="group-delta" colspan="5"><span class="header-center">Delta Terhadap</span></th>
                                <th class="group-rka" colspan="6"><span class="header-center">Perbandingan RKA</span></th>
                            </tr>
                            <tr class="column-row text-center">
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-yoy>-</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-ytd>-</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-m2>-</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2" data-mtm-toggle>
                                    <span class="column-heading"><span class="main" data-label-mtm>-</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-mtd>-</span></span>
                                </th>
                                <th class="value-col position-col position-col-h1" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-h1>-</span></span>
                                </th>
                                <th class="value-col position-col" rowspan="2" style="background-color: var(--primary-blue-light);">
                                    <span class="column-heading"><span class="main text-white" data-label-current>-</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-yoy>-</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-ytd>-</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2" data-mtm-toggle>
                                    <span class="column-heading"><span class="main" data-label-delta-mtm>-</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-mtd>-</span></span>
                                </th>
                                <th class="value-col delta-col" rowspan="2">
                                    <span class="column-heading"><span class="main" data-label-delta-dtd>-</span></span>
                                </th>
                                <th class="value-col rka-col rka-period-cell" colspan="3">
                                    <span class="rka-period-label" data-label-rka-period>RKA</span>
                                </th>
                                <th class="value-col rka-col rka-period-cell" colspan="3">
                                    <span class="rka-period-label" data-label-rka-dec-period>RKA Des</span>
                                </th>
                            </tr>
                            <tr class="rka-sub-row text-center">
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">Rp</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">GAP</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">%</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">Rp</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">GAP</span>
                                </th>
                                <th class="value-col rka-col">
                                    <span class="rka-sub-label">%</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="daily-dashboard-body">
                            <tr><td colspan="20" class="daily-empty"><i class="fas fa-spinner fa-spin mr-2"></i> Memuat data dashboard harian...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="daily-mtm-override-panel daily-mtm-override-popover" id="mtmOverridePanel">
        <label class="daily-filter-label" for="filter-mtm-period">Tanggal MtM</label>
        <div class="daily-filter-control">
            <span class="daily-filter-control-icon"><i class="fas fa-calendar-alt"></i></span>
            <select id="filter-mtm-period" class="form-control daily-filter-select"></select>
        </div>
        <button type="button" class="daily-mtm-reset" id="btn-reset-mtm-period">
            <i class="fas fa-undo-alt mr-1"></i>Default MtM
        </button>
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
                        <h4 class="font-weight-bold mb-2">Menyusun Laporan A4</h4>
                        <p class="text-muted mb-0">Sedang menyusun data dashboard ke dalam format gambar A4 portrait dengan header per segmen. Mohon tunggu sebentar...</p>
                    </div>

                    <!-- Error State -->
                    <div id="captureErrorUI" class="d-none">
                        <div class="capture-status-modal-icon icon-error">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Gagal Mengambil Snapshot</h4>
                        <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kendala saat menyusun laporan A4.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Tutup & Coba Lagi
                        </button>
                    </div>

                    <!-- Success State -->
                    <div id="captureSuccessUI" class="d-none">
                        <div class="capture-status-modal-icon icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Snapshot Berhasil!</h4>
                        <p class="text-muted mb-4">Laporan A4 dalam file PNG resolusi tinggi telah berhasil diunduh ke perangkat Anda.</p>
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
<script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
<script>
    window.dailyDashboardPage = @json($dashboardPage ?? []);
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const page = window.dailyDashboardPage || {};
        const currentPath = window.location.pathname.replace(/\/$/, '');
        const dataUrl = currentPath ? currentPath + '/data' : (page.routes ? page.routes.data : '');
        const exportUrl = (page.routes && page.routes.export) ? page.routes.export : (currentPath ? currentPath + '/export' : '');
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
            deltaMtm: document.querySelector('[data-label-delta-mtm]'),
            deltaMtd: document.querySelector('[data-label-delta-mtd]'),
            deltaDtd: document.querySelector('[data-label-delta-dtd]'),
        };
        const positionGroupColspan = document.querySelector('[data-position-group-colspan]');
        const positionH1Header = document.querySelector('[data-label-h1]').closest('th');
        const tableRegion = document.querySelector('[data-table-region]');
        const tableWrap = document.querySelector('.daily-table-wrap');
        const mainHeader = document.querySelector('.main-header');
        const applyButton = document.getElementById('btn-apply-daily-filter');
        const exportExcelButton = document.getElementById('exportExcelBtn');
        const mtmOverridePanel = document.getElementById('mtmOverridePanel');
        const resetMtmButton = document.getElementById('btn-reset-mtm-period');
        const selects = {
            kanca: document.getElementById('filter-kanca'),
            unit_kerja: document.getElementById('filter-unit'),
            posisi_terakhir: document.getElementById('filter-posisi-terakhir'),
            posisi_rka: document.getElementById('filter-posisi-rka'),
            mtm_period: document.getElementById('filter-mtm-period'),
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
            posisi: {
                root: document.querySelector('[data-daily-dropdown="posisi"]'),
                toggle: document.querySelector('[data-daily-dropdown-toggle="posisi"]'),
                menu: document.querySelector('[data-daily-dropdown-menu="posisi"]'),
            },
            rka: {
                root: document.querySelector('[data-daily-dropdown="rka"]'),
                toggle: document.querySelector('[data-daily-dropdown-toggle="rka"]'),
                menu: document.querySelector('[data-daily-dropdown-menu="rka"]'),
            },
        };
        let latestFilters = initialFilters;
        let mtmOverrideVisible = false;
        const MILLION_UNIT = 1000000;
        const BILLION_UNIT = 1000000000;
        const TABLE_MONEY_UNIT = MILLION_UNIT;
        const TABLE_MONEY_LABEL = '';
        const TABLE_VISIBLE_ROW_LIMIT = 25;
        const TABLE_STICKY_TOP_TRIM = 24;
        const currencyFormatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
        const percentFormatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
        const rkaPercentFormatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const formatCurrency = function (value) {
            return currencyFormatter.format(Number(value || 0) / TABLE_MONEY_UNIT) + ' ' + TABLE_MONEY_LABEL;
        };

        const formatJuta = function (value) {
            return currencyFormatter.format(Number(value || 0) / MILLION_UNIT) + ' J';
        };

        const formatPercent = function (value) {
            return percentFormatter.format(Number(value || 0)) + '%';
        };

        const formatDateShort = function (value) {
            if (!value) {
                return '-';
            }

            const parts = String(value).slice(0, 10).split('-');
            if (parts.length !== 3) {
                return String(value);
            }

            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            const day = parts[2];
            const month = months[parseInt(parts[1], 10) - 1];
            const year = parts[0].slice(2);

            return day + ' ' + month + ' ' + year;
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

        const formatValue = function (value, type, key) {
            if (type === 'percent') {
                if (key === 'total_sml_pct_non_commercial' || key === 'total_npl_pct_non_commercial') {
                    return rkaPercentFormatter.format(Number(value || 0)) + '%';
                }
                return formatPercent(value);
            }
            return formatCurrency(value);
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

        const syncStickyHeaderOffsets = function () {
            if (!tableWrap) {
                return [];
            }

            const headerRows = Array.from(tableWrap.querySelectorAll('.daily-table thead tr'));
            const groupRow = headerRows.find(function (row) { return row.classList.contains('group-row'); });
            const columnRow = headerRows.find(function (row) { return row.classList.contains('column-row'); });
            const groupHeight = groupRow ? Math.ceil(groupRow.getBoundingClientRect().height || 0) : 0;
            const columnHeight = columnRow ? Math.ceil(columnRow.getBoundingClientRect().height || 0) : 0;

            if (groupHeight > 0) {
                tableWrap.style.setProperty('--daily-header-column-top', groupHeight + 'px');
            }

            if (groupHeight > 0 && columnHeight > 0) {
                tableWrap.style.setProperty('--daily-header-rka-top', (groupHeight + columnHeight) + 'px');
            }

            return headerRows;
        };

        const syncTableViewport = function () {
            if (!tableWrap || !tableRegion || !body) {
                return;
            }

            const stickyTop = getStickyTopOffset();
            tableRegion.style.setProperty('--daily-table-sticky-top', stickyTop + 'px');

            const headerRows = syncStickyHeaderOffsets();
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

            const viewportHeight = window.visualViewport && window.visualViewport.height
                ? window.visualViewport.height
                : window.innerHeight;
            const tableTop = Math.max(0, tableWrap.getBoundingClientRect().top);

            // Force the table to occupy at least 68% of the viewport height on tablet & mobile screens
            const isSmallScreen = window.innerWidth < 1399.98;
            const minHeightRatio = isSmallScreen ? 0.68 : 0.45;
            const minTableHeight = Math.ceil(viewportHeight * minHeightRatio);

            const availableHeight = Math.max(minTableHeight, viewportHeight - tableTop - 16);
            const contentHeight = headerHeight + bodyHeight + 2;
            const desiredHeight = Math.min(availableHeight, contentHeight);

            tableWrap.style.height = desiredHeight + 'px';
            tableWrap.style.maxHeight = desiredHeight + 'px';
        };

        const scheduleTableViewportSync = function () {
            requestAnimationFrame(syncTableViewport);
        };

        if (window.ResizeObserver && tableWrap) {
            const stickyHeaderResizeObserver = new ResizeObserver(scheduleTableViewportSync);
            tableWrap.querySelectorAll('.daily-table thead tr').forEach(function (row) {
                stickyHeaderResizeObserver.observe(row);
            });
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(scheduleTableViewportSync);
        }

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

        let responsiveViewportFrame = null;
        const closeAllDropdowns = function () {
            Object.keys(dropdowns).forEach(closeDropdown);
        };

        const handleResponsiveViewportChange = function () {
            closeAllDropdowns();

            if (responsiveViewportFrame) {
                cancelAnimationFrame(responsiveViewportFrame);
            }

            responsiveViewportFrame = requestAnimationFrame(function () {
                responsiveViewportFrame = null;
                syncTableViewport();
            });
        };

        const updateFilterSummary = function () {
            const kancaText = document.querySelector('[data-daily-dropdown-toggle="kanca"] .daily-dropdown-toggle-text')?.innerText || 'Area 6';
            const unitText = document.querySelector('[data-daily-dropdown-toggle="unit"] .daily-dropdown-toggle-text')?.innerText || 'Semua Unit';
            const posisiText = document.querySelector('[data-daily-dropdown-toggle="posisi"] .daily-dropdown-toggle-text')?.innerText || '';
            const rkaText = document.querySelector('[data-daily-dropdown-toggle="rka"] .daily-dropdown-toggle-text')?.innerText || '';

            const summarySpan = document.getElementById('filter-summary-badge');
            if (summarySpan) {
                let summaryParts = [];
                if (kancaText) summaryParts.push(kancaText);
                if (unitText && unitText !== 'Semua Unit Kerja' && unitText !== 'Tidak tersedia untuk Area 6') {
                    summaryParts.push(unitText);
                }
                if (posisiText && posisiText !== 'Belum ada data') {
                    summaryParts.push('Posisi: ' + posisiText.split(' ')[0]);
                }
                if (rkaText && rkaText !== 'Belum ada data') {
                    summaryParts.push('RKA: ' + rkaText.split(' ')[0]);
                }
                summarySpan.innerText = summaryParts.join(' | ');
            }
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
            updateFilterSummary();
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
            const kancaOptions = (options || []).filter(function (opt) {
                return opt.value && opt.value !== 'all';
            });
            const totalKancaCount = kancaOptions.length;

            if (!normalized.length) {
                return 'Area 6';
            }

            if (totalKancaCount > 0 && normalized.length === totalKancaCount) {
                return 'Area 6';
            }

            const labels = (options || [])
                .filter(function (option) {
                    return normalized.includes(String(option.value || ''));
                })
                .map(function (option) {
                    return option.label || option.value || '';
                })
                .filter(Boolean);

            if (labels.length === 1) {
                return labels[0];
            }

            return labels[0] + ', +' + (labels.length - 1) + ' KC';
        };

        const isArea6KancaSelection = function (options, selectedValues) {
            const normalized = normalizeArraySelection(selectedValues);
            const kancaValues = (options || [])
                .map(function (option) { return String(option.value || ''); })
                .filter(function (value) { return value && value !== 'all'; });

            if (!normalized.length) {
                return true;
            }

            return kancaValues.length > 0
                && normalized.length === kancaValues.length
                && kancaValues.every(function (value) { return normalized.includes(value); });
        };

        const renderKancaDropdown = function (options, selectedValues) {
            const dropdown = dropdowns.kanca;
            if (!dropdown || !dropdown.menu || !selects.kanca) {
                return;
            }

            const normalized = normalizeArraySelection(selectedValues);
            setNativeSelectOptions(selects.kanca, options, normalized, true);
            const area6Active = isArea6KancaSelection(options, normalized);

            dropdown.menu.innerHTML = (options || []).map(function (option) {
                const value = String(option.value || 'all');
                const isAll = value === 'all';
                const isActive = isAll ? area6Active : (!area6Active && normalized.includes(value));

                return '<button type="button" class="daily-dropdown-option ' + (isActive ? 'is-active' : '') + '" data-kanca-option="' + escapeHtml(value) + '">' +
                    '<span class="daily-dropdown-check"><i class="fas fa-check"></i></span>' +
                    '<span class="daily-dropdown-label">' + escapeHtml(option.label || value) + '</span>' +
                '</button>';
            }).join('');

            updateDropdownToggleText('kanca', buildKancaToggleLabel(options, normalized));
        };

        const scopedUnitOptions = function (filters, kancaValue) {
            const allUnitOptions = (filters.unit_kerja || []).filter(function (option) {
                return (option.value || 'all') === 'all';
            });

            if (isArea6KancaSelection(filters.kanca || [], kancaValue)) {
                return allUnitOptions;
            }

            return (filters.unit_kerja || []).filter(function (option) {
                if ((option.value || 'all') === 'all') {
                    return true;
                }

                if (!Array.isArray(kancaValue) || !kancaValue.length) {
                    return false;
                }

                return kancaValue.includes(String(option.kanca_value || ''));
            });
        };

        const syncUnitSelect = function (filters, preferredUnit) {
            const selectedKancaValues = getSelectedKancaValues();
            const area6Scope = isArea6KancaSelection(filters.kanca || [], selectedKancaValues);
            const unitOptions = scopedUnitOptions(filters, selectedKancaValues);
            const selectedUnit = unitOptions.some(function (option) {
                return String(option.value || '') === String(preferredUnit || 'all');
            }) ? (preferredUnit || 'all') : 'all';

            setNativeSelectOptions(selects.unit_kerja, unitOptions, selectedUnit, false);
            if (selects.unit_kerja) {
                selects.unit_kerja.disabled = area6Scope;
            }

            const dropdown = dropdowns.unit;
            if (dropdown && dropdown.toggle) {
                dropdown.toggle.disabled = area6Scope;
                dropdown.toggle.setAttribute('aria-disabled', area6Scope ? 'true' : 'false');
            }

            if (dropdown && dropdown.menu) {
                dropdown.menu.innerHTML = area6Scope
                    ? '<div class="daily-dropdown-empty">Tidak tersedia untuk Area 6.</div>'
                    : unitOptions.length
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

        updateDropdownToggleText('unit', area6Scope ? 'Tidak tersedia untuk Area 6' : (selectedOption ? (selectedOption.label || selectedOption.value || 'Semua Unit Kerja') : 'Semua Unit Kerja'));
    };

    const syncPosisiSelect = function (options, selectedValue) {
        const dropdown = dropdowns.posisi;
        if (!dropdown || !dropdown.menu || !selects.posisi_terakhir) return;

        const normalized = selectedValue || '';
        setNativeSelectOptions(selects.posisi_terakhir, options, normalized, false);

        dropdown.menu.innerHTML = options.length
            ? options.map(function (option) {
                const value = String(option.value || '');
                const active = value === String(normalized);
                return '<button type="button" class="daily-dropdown-option ' + (active ? 'is-active' : '') + '" data-posisi-option="' + escapeHtml(value) + '">' +
                    '<span class="daily-dropdown-label">' + escapeHtml(option.label || value) + '</span>' +
                '</button>';
            }).join('')
            : '<div class="daily-dropdown-empty">Tidak ada data posisi.</div>';

        const selectedOption = options.find(function (option) { return String(option.value || '') === String(normalized); });
        updateDropdownToggleText('posisi', selectedOption ? (selectedOption.label || selectedOption.value) : 'Belum ada data');
    };

        const syncRkaSelect = function (options, selectedValue) {
        const dropdown = dropdowns.rka;
        if (!dropdown || !dropdown.menu || !selects.posisi_rka) return;

        const normalized = selectedValue || '';
        setNativeSelectOptions(selects.posisi_rka, options, normalized, false);

        dropdown.menu.innerHTML = options.length
            ? options.map(function (option) {
                const value = String(option.value || '');
                const active = value === String(normalized);
                return '<button type="button" class="daily-dropdown-option ' + (active ? 'is-active' : '') + '" data-rka-option="' + escapeHtml(value) + '">' +
                    '<span class="daily-dropdown-label">' + escapeHtml(option.label || value) + '</span>' +
                '</button>';
            }).join('')
            : '<div class="daily-dropdown-empty">Tidak ada data RKA.</div>';

        const selectedOption = options.find(function (option) { return String(option.value || '') === String(normalized); });
        updateDropdownToggleText('rka', selectedOption ? (selectedOption.label || selectedOption.value) : 'Belum ada data');
    };

        const positionMtmOverridePanel = function (event) {
            if (!mtmOverridePanel) {
                return;
            }

            const rect = mtmOverridePanel.getBoundingClientRect();
            const margin = 16;
            const width = rect.width || 280;
            const height = rect.height || 120;
            let anchorX = event && Number.isFinite(event.clientX) ? event.clientX : null;
            let anchorY = event && Number.isFinite(event.clientY) ? event.clientY : null;

            if (anchorX === null || anchorY === null) {
                const mtmHeader = document.querySelector('[data-mtm-toggle]');
                if (mtmHeader) {
                    const headerRect = mtmHeader.getBoundingClientRect();
                    anchorX = headerRect.left + (headerRect.width / 2);
                    anchorY = headerRect.bottom;
                }
            }

            if (anchorX === null || anchorY === null) {
                anchorX = window.innerWidth / 2;
                anchorY = Math.min(140, window.innerHeight / 3);
            }

            const left = Math.min(Math.max(margin, anchorX - (width / 2)), window.innerWidth - width - margin);
            const top = Math.min(Math.max(margin, anchorY + 12), window.innerHeight - height - margin);

            mtmOverridePanel.style.left = left + 'px';
            mtmOverridePanel.style.top = top + 'px';
        };

        const showMtmOverridePanel = function (event) {
            mtmOverrideVisible = true;
            if (mtmOverridePanel) {
                mtmOverridePanel.classList.add('is-visible');
                mtmOverridePanel.classList.remove('d-none');
                positionMtmOverridePanel(event);
            }
        };

        const hideMtmOverridePanel = function () {
            mtmOverrideVisible = false;
            if (mtmOverridePanel) {
                mtmOverridePanel.classList.remove('is-visible');
                mtmOverridePanel.style.left = '';
                mtmOverridePanel.style.top = '';
            }
        };

        const hideMtmOverridePanelIfDefault = function () {
            if (selects.mtm_period && selects.mtm_period.value) {
                return;
            }

            hideMtmOverridePanel();
        };

        const syncMtmSelect = function (options, selectedValue, defaultPeriod) {
            if (!selects.mtm_period) {
                return;
            }

            const normalized = selectedValue || '';
            const normalizedText = String(normalized);
            const defaultText = String(defaultPeriod || '');
            const optionList = [{ value: '', label: 'Default MtM' }].concat((options || []).filter(function (option) {
                const value = String(option.value || '');

                return value !== defaultText || (normalizedText && value === normalizedText);
            }));

            if (normalizedText && !optionList.some(function (option) { return String(option.value || '') === normalizedText; })) {
                optionList.splice(1, 0, {
                    value: normalizedText,
                    label: formatDateShort(normalizedText)
                });
            }

            setNativeSelectOptions(selects.mtm_period, optionList, normalized, false);

            if (mtmOverrideVisible) {
                showMtmOverridePanel();
            } else {
                hideMtmOverridePanel();
            }
        };

        const currentState = function () {
            return {
                kanca: getSelectedKancaValues(),
                unit_kerja: selects.unit_kerja.value || 'all',
                posisi_terakhir: selects.posisi_terakhir.value || '',
                posisi_rka: selects.posisi_rka.value || '',
                mtm_period: selects.mtm_period ? (selects.mtm_period.value || '') : '',
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

            if (state.mtm_period) {
                params.set('mtm_period', state.mtm_period);
            }

            return params;
        };

        if (exportExcelButton && exportUrl) {
            exportExcelButton.addEventListener('click', function (event) {
                event.preventDefault();
                const params = buildQueryParams();
                const separator = exportUrl.includes('?') ? '&' : '?';
                window.location.href = exportUrl + (params.toString() ? separator + params.toString() : '');
            });
        }

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

        const isLdrTargetMetric = function (rowKey) {
            return String(rowKey || '').startsWith('ldr_');
        };

        const isLowerBetterTargetMetric = function (rowKey) {
            return isQualityTargetMetric(rowKey) || isLdrTargetMetric(rowKey);
        };

        const formatAchievement = function (value) {
            return rkaPercentFormatter.format(Number(value || 0)) + '%';
        };

        const computeRkaComparison = function (row) {
            const currentValue = Number(row?.values?.current || 0);
            const rkaValue = Number(row?.values?.rka || 0);
            const rkaDecValue = Number(row?.values?.rka_dec || 0);
            const reverse = isLowerBetterTargetMetric(row?.key);
            const compare = function (targetValue) {
                const delta = currentValue - targetValue;
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
                'casa_ritel', 'ldr_ritel_non_commercial', 'rec_dh_small'
            ];

            let mikroKeys = [
                'simpanan_mikro', 'giro_mikro', 'deposito_mikro', 'tabungan_mikro',
                'micro_os', 'briguna_mikro_os', 'kupedes_os', 'kur_mikro_os', 'kur_kecil_os', 'kur_kpp_os',
                'micro_sml', 'briguna_mikro_sml', 'kupedes_sml', 'kur_mikro_sml', 'kur_kecil_sml', 'kur_kpp_sml',
                'micro_npl', 'briguna_mikro_npl', 'kupedes_npl', 'kur_mikro_npl', 'kur_kecil_npl', 'kur_kpp_npl',
                'casa_mikro', 'ldr_mikro_non_commercial', 'rec_dh_micro'
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
                'simpanan_wholesale', 'giro_wholesale', 'deposito_wholesale', 'tabungan_wholesale', 'casa_wholesale'
            ];

            if (scopeMode === 'unit') {
                hiddenKeys = hiddenKeys.concat(ritelKeys, commercialKeys, wholesaleKeys);
            } else if (scopeMode === 'kcp') {
                hiddenKeys = hiddenKeys.concat(mikroKeys, commercialKeys);
            } else if (scopeMode === 'kc') {
                hiddenKeys = hiddenKeys.concat(mikroKeys, commercialKeys);
            } else if (scopeMode === 'all') {
                hiddenKeys = hiddenKeys.concat(commercialKeys);
            } else {
                hiddenKeys = hiddenKeys.concat(commercialKeys);
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
                    if (smpanMetricName !== 'rka' && smpanMetricName !== 'rka_dec') {
                        rowsByKey.total_simpanan[group][smpanMetricName] = sumMetric(rowsByKey, ['simpanan_ritel', 'simpanan_mikro', 'simpanan_wholesale'], group, smpanMetricName);
                    }
                    rowsByKey.casa_ritel[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_ritel', 'tabungan_ritel'], group, smpanMetricName);
                    rowsByKey.casa_mikro[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_mikro', 'tabungan_mikro'], group, smpanMetricName);
                    rowsByKey.casa_non_wholesale[group][smpanMetricName] = sumMetric(rowsByKey, ['casa_ritel', 'casa_mikro'], group, smpanMetricName);
                    rowsByKey.casa_wholesale[group][smpanMetricName] = sumMetric(rowsByKey, ['giro_wholesale', 'tabungan_wholesale'], group, smpanMetricName);
                    rowsByKey.total_casa[group][smpanMetricName] = sumMetric(rowsByKey, ['casa_non_wholesale', 'casa_wholesale'], group, smpanMetricName);
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
                    const microNplChildren = ['briguna_mikro_npl', 'kupedes_npl', 'kur_mikro_npl', 'kur_kecil_npl', 'kur_kpp_npl'];
                    rowsByKey.sme_npl[group][smpanMetricName] = sumMetric(rowsByKey, smeNplChildren, group, smpanMetricName);
                    rowsByKey.consumer_npl[group][smpanMetricName] = sumMetric(rowsByKey, ['briguna_konsumer_npl', 'kpr_npl', 'kkb_npl'], group, smpanMetricName);
                    rowsByKey.micro_npl[group][smpanMetricName] = sumMetric(rowsByKey, microNplChildren, group, smpanMetricName);
                    
                    const totalNplNonCommercialChildren = (scopeMode === 'kc' || scopeMode === 'kcp') ? ['sme_npl', 'consumer_npl'] : ['sme_npl', 'consumer_npl', 'micro_npl'];
                    rowsByKey.total_npl_abs_non_commercial[group][smpanMetricName] = sumMetric(rowsByKey, totalNplNonCommercialChildren, group, smpanMetricName);

                    if (rowsByKey.rec_dh_total) {
                        rowsByKey.rec_dh_total[group][smpanMetricName] = sumMetric(rowsByKey, ['rec_dh_small', 'rec_dh_micro'], group, smpanMetricName);
                    }
                });
            });

            ['values', 'deltas'].forEach(function (group) {
                const metricNames = group === 'values'
                    ? ['yoy', 'ytd', 'm2', 'mtm', 'mtd', 'h1', 'current', 'rka', 'rka_dec']
                    : ['yoy', 'ytd', 'mtd', 'dtd'];

                metricNames.forEach(function (metricName) {
                    rowsByKey.casa_pct[group][metricName] = safePercent(rowsByKey.total_casa[group][metricName], rowsByKey.total_simpanan[group][metricName]);
                    rowsByKey.ldr_non_commercial[group][metricName] = safePercent(rowsByKey.total_os_non_commercial[group][metricName], rowsByKey.total_simpanan[group][metricName]);
                    rowsByKey.ldr_ritel_non_commercial[group][metricName] = safePercent(rowsByKey.sme_os[group][metricName] + rowsByKey.consumer_os[group][metricName], rowsByKey.simpanan_ritel[group][metricName]);
                    rowsByKey.ldr_mikro_non_commercial[group][metricName] = safePercent(rowsByKey.micro_os[group][metricName], rowsByKey.simpanan_mikro[group][metricName]);
                    if (rowsByKey.total_sml_pct_non_commercial) {
                        rowsByKey.total_sml_pct_non_commercial[group][metricName] = safePercent(rowsByKey.total_sml_abs_non_commercial[group][metricName], rowsByKey.total_os_non_commercial[group][metricName]);
                    }
                    if (rowsByKey.total_npl_pct_non_commercial) {
                        rowsByKey.total_npl_pct_non_commercial[group][metricName] = safePercent(rowsByKey.total_npl_abs_non_commercial[group][metricName], rowsByKey.total_os_non_commercial[group][metricName]);
                    }
                });
            });

            scopedPayload.summary = scopedPayload.summary || {};
            scopedPayload.summary.current_total_simpanan = Number(rowsByKey.total_simpanan?.values?.current || 0);
            scopedPayload.summary.current_total_os_non_commercial = Number(rowsByKey.total_os_non_commercial?.values?.current || 0);
            scopedPayload.summary.current_casa_pct = Number(rowsByKey.casa_pct?.values?.current || 0);

            return scopedPayload;
        };

        const renderTable = function (payload) {
            const rows = payload.rows || [];
            const periods = payload.comparison_periods || {};
            const hasH1 = Boolean(periods.h1 && periods.h1.period);
            const emptyColspan = hasH1 ? 20 : 19;
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
            const scopeMode = 'all';

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
                const rkaComparison = computeRkaComparison(row);
                const isReverse = isLowerBetterTargetMetric(row.key);
                const isLdrMetric = isLdrTargetMetric(row.key);

                /* Display-only: format negative values as (X) for normal metrics.
                   For quality (SML/NPL): positive shows normally; negative is green and wrapped. */
                const formatParens = function (val, type, key) {
                    const num = Number(val || 0);
                    if (num < 0) {
                        return '(' + formatValue(Math.abs(num), type, key).trim() + ')';
                    }
                    return formatValue(val, type, key);
                };

                /* For quality metrics: keep positive plain; wrap negative values in parentheses. */
                const formatParensQuality = function (val, type, key) {
                    const num = Number(val || 0);
                    if (num < 0) {
                        return '(' + formatValue(Math.abs(num), type, key).trim() + ')';
                    }
                    return formatValue(num, type, key);
                };

                /* deltaClass for normal metrics: positive = black, negative = red */
                const deltaClass = function (amount) {
                    if (Number(amount) > 0) { return 'delta-positive'; }
                    if (Number(amount) < 0) { return 'delta-negative'; }
                    return 'text-muted';
                };

                /* deltaClassQuality for SML/NPL: negative = green (good), positive = red (bad) */
                const deltaClassQuality = function (amount) {
                    if (Number(amount) < 0) { return 'delta-quality-good'; }
                    if (Number(amount) > 0) { return 'delta-quality-bad';  }
                    return 'text-muted';
                };

                const dcFn    = isReverse ? deltaClassQuality : deltaClass;
                const fmtDelta = isReverse ? formatParensQuality : formatParens;

                const pctAchieveClass = function (achievement, deltaAmount) {
                    if (isLdrMetric) {
                        const deltaValue = Number(deltaAmount || 0);

                        if (deltaValue < 0) { return 'pct-achieve-good'; }
                        if (deltaValue === 0) { return 'pct-achieve-warn'; }

                        return 'pct-achieve-bad';
                    }

                    const pct = Number(achievement || 0);
                    return pct >= 100 ? 'pct-achieve-good' : pct >= 90 ? 'pct-achieve-warn' : 'pct-achieve-bad';
                };

                rowCells.push('<td class="sticky-label"><span class="metric-label" title="' + escapeHtml(row.label) + '">' + escapeHtml(row.label) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.yoy, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.ytd, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.m2, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtm, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtd, row.type, row.key) + '</span></td>');

                if (hasH1) {
                    rowCells.push('<td class="value-col position-col position-col-h1" data-position-col="h1"><span class="cell-text">' + formatValue(value.h1, row.type, row.key) + '</span></td>');
                }

                rowCells.push('<td class="value-col position-col metric-value font-weight-bold bg-light"><span class="cell-text text-primary">' + formatValue(value.current, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + dcFn(delta.yoy) + '"><span class="cell-text">' + fmtDelta(delta.yoy, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + dcFn(delta.ytd) + '"><span class="cell-text">' + fmtDelta(delta.ytd, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + dcFn(delta.mtm) + '"><span class="cell-text">' + fmtDelta(delta.mtm, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + dcFn(delta.mtd) + '"><span class="cell-text">' + fmtDelta(delta.mtd, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + dcFn(delta.dtd) + '"><span class="cell-text">' + fmtDelta(delta.dtd, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col rka-col ' + dcFn(rkaComparison.rka.delta) + '"><span class="cell-text">' + fmtDelta(rkaComparison.rka.delta, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col rka-col ' + pctAchieveClass(rkaComparison.rka.achievement, rkaComparison.rka.delta) + '"><span class="cell-text">' + formatAchievement(rkaComparison.rka.achievement) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka_dec, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col rka-col ' + dcFn(rkaComparison.rkaDec.delta) + '"><span class="cell-text">' + fmtDelta(rkaComparison.rkaDec.delta, row.type, row.key) + '</span></td>');
                rowCells.push('<td class="value-col rka-col ' + pctAchieveClass(rkaComparison.rkaDec.achievement, rkaComparison.rkaDec.delta) + '"><span class="cell-text">' + formatAchievement(rkaComparison.rkaDec.achievement) + '</span></td>');

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
            const summary = payload.summary || {};
            const periods = payload.comparison_periods || {};
            const filters = payload.available_filters || initialFilters;
            const hasH1 = Boolean(periods.h1 && periods.h1.period);
            latestFilters = filters;
            const current = currentState();

            renderKancaDropdown(filters.kanca || [], current.kanca);
            syncUnitSelect(filters, current.unit_kerja);
            syncPosisiSelect(filters.posisi_terakhir || [], payload.selected_period || current.posisi_terakhir);
            syncRkaSelect(filters.posisi_rka || [], payload.selected_rka_period ? payload.selected_rka_period.slice(0, 7) : current.posisi_rka);
            syncMtmSelect(filters.mtm_period || filters.posisi_terakhir || [], current.mtm_period, periods.mtm ? periods.mtm.period : null);

            setTextContent(scopeKanca, summary.kanca_label || 'Area 6');
            setTextContent(scopeUnit, summary.unit_label || 'Semua Unit Kerja');
            setTextContent(scopePosisi, payload.selected_period_label || 'Belum ada data');
            setTextContent(scopeRka, periods.rka ? formatMonthYear(periods.rka.period) : 'Belum ada data');
            if (scopeSummary) {
                scopeSummary.innerHTML = '<i class="fas fa-list mr-1"></i> Baris tampil: ' + (summary.row_count || 0).toLocaleString('id-ID') + '. <br><small class="text-muted font-weight-normal mt-1 d-block">Data dari: ' + (summary.source || 'source_fallback') + '</small>';
            }
            setTextContent(sourceLabel, summary.source || 'source_fallback');
            setTextContent(kpiSimpanan, formatJuta(summary.current_total_simpanan || 0));
            setTextContent(kpiOs, formatJuta(summary.current_total_os_non_commercial || 0));
            setTextContent(kpiCasa, formatPercent(summary.current_casa_pct || 0));

            setTextContent(headerLabels.yoy, periods.yoy ? formatDateShort(periods.yoy.period) : '-');
            setTextContent(headerLabels.ytd, periods.ytd ? formatDateShort(periods.ytd.period) : '-');
            setTextContent(headerLabels.m2, periods.m2 ? formatDateShort(periods.m2.period) : '-');
            setTextContent(headerLabels.mtm, periods.mtm ? formatDateShort(periods.mtm.period) : '-');
            setTextContent(headerLabels.mtd, periods.mtd ? formatDateShort(periods.mtd.period) : '-');
            setTextContent(headerLabels.h1, hasH1 ? formatDateShort(periods.h1.period) : '-');
            setTextContent(headerLabels.current, payload.selected_period ? formatDateShort(payload.selected_period) : '-');
            setTextContent(headerLabels.rka, periods.rka ? 'RKA ' + String(formatMonthYear(periods.rka.period)).toUpperCase() : 'RKA');
            setTextContent(headerLabels.rkaDec, periods.rka_dec ? 'RKA ' + String(formatMonthYear(periods.rka_dec.period)).toUpperCase() : 'RKA Des');
            setTextContent(headerLabels.deltaYoy, periods.yoy ? formatDateShort(periods.yoy.period) : '-');
            setTextContent(headerLabels.deltaYtd, periods.ytd ? formatDateShort(periods.ytd.period) : '-');
            setTextContent(headerLabels.deltaMtm, periods.mtm ? formatDateShort(periods.mtm.period) : '-');
            setTextContent(headerLabels.deltaMtd, periods.mtd ? formatDateShort(periods.mtd.period) : '-');
            setTextContent(headerLabels.deltaDtd, hasH1 ? formatDateShort(periods.h1.period) : '-');

            togglePositionColumns(hasH1);
            renderTable(payload);
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
                    body.innerHTML = '<tr><td colspan="' + (hidden ? 19 : 20) + '" class="daily-empty text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Gagal memuat data dashboard harian.</td></tr>';
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
            syncUnitSelect(latestFilters, selects.unit_kerja.value || 'all');
        };

        // --- Capture All Logic (A4 Portrait Composer) ---
        const captureBtn = document.getElementById('captureAllBtn');
        const captureModal = document.getElementById('captureStatusModal');
        const progressUI = document.getElementById('captureProgressUI');
        const errorUI = document.getElementById('captureErrorUI');
        const successUI = document.getElementById('captureSuccessUI');
        const errorMessageUI = document.getElementById('captureErrorMessage');

        const A4_EXPORT = {
            width: 4960,
            height: 7016,
            marginX: 150,
            marginY: 118,
            headerHeight: 328,
            blockTitleHeight: 76,
            footerHeight: 128,
            sectionGap: 34,
        };

        function waitFrame() {
            return new Promise(resolve => requestAnimationFrame(() => resolve()));
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

        function drawExportHeader(ctx, pageNum = 1, groupLabel = '') {
            const { width, marginX, marginY } = A4_EXPORT;
            const kancaText = scopeKanca?.textContent?.trim() || 'Area 6';
            const unitText = scopeUnit?.textContent?.trim() || 'Semua Unit';
            const posisiText = scopePosisi?.textContent?.trim() || 'Belum Ada Data';
            const rkaText = scopeRka?.textContent?.trim() || 'Belum Ada Data';

            // Top accent bar
            ctx.fillStyle = '#004685'; 
            ctx.fillRect(0, 0, width, 36);

            // Title
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 88px "Inter", "Segoe UI", Arial, sans-serif';
            ctx.fillText('Daily Dashboard Performance', marginX, marginY + 60);

            // Metadata info
            ctx.fillStyle = '#475569';
            ctx.font = '600 42px "Inter", "Segoe UI", Arial, sans-serif';
            
            const metaLine1 = `Kanca: ${kancaText}   |   Unit: ${unitText}`;
            const metaLine2 = `Periode: ${posisiText}   |   RKA: ${rkaText}`;
            
            drawTextEllipsis(ctx, metaLine1, marginX, marginY + 135, width - (marginX * 2));
            drawTextEllipsis(ctx, metaLine2, marginX, marginY + 195, width - (marginX * 2));

            // Decorative line
            ctx.strokeStyle = '#e2e8f0';
            ctx.lineWidth = 5;
            ctx.beginPath();
            ctx.moveTo(marginX, marginY + 265);
            ctx.lineTo(width - marginX, marginY + 265);
            ctx.stroke();
            
            // Group Label & Page Indicator (Subtle top right)
            ctx.fillStyle = '#94a3b8';
            ctx.font = 'italic 34px "Inter", sans-serif';
            ctx.textAlign = 'right';
            const indicatorText = groupLabel ? `${groupLabel}${pageNum > 1 ? ` (Hal ${pageNum})` : ''}` : (pageNum > 1 ? `Sambungan Halaman ${pageNum}` : '');
            if (indicatorText) {
                ctx.fillText(indicatorText, width - marginX, marginY + 60);
            }
            ctx.textAlign = 'left';
        }

        function drawExportFooter(ctx, pageNum = 1, totalPages = 1) {
            const { width, height, marginX } = A4_EXPORT;
            
            // Separator line
            ctx.strokeStyle = '#e2e8f0';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(marginX, height - 120);
            ctx.lineTo(width - marginX, height - 120);
            ctx.stroke();

            // Generation timestamp
            ctx.fillStyle = '#94a3b8';
            ctx.font = '600 32px "Inter", "Segoe UI", Arial, sans-serif';
            const dateStr = new Date().toLocaleString('id-ID', { 
                day: '2-digit', month: '2-digit', year: 'numeric', 
                hour: '2-digit', minute: '2-digit', second: '2-digit' 
            }).replace(/\//g, '-');
            ctx.fillText(`Dashboard A-Six Generated: ${dateStr}`, marginX, height - 60);
            
            // Page numbering
            ctx.textAlign = 'right';
            ctx.font = 'bold 36px "Inter", "Segoe UI", Arial, sans-serif';
            ctx.fillStyle = '#64748b';
            ctx.fillText(`Halaman ${pageNum} dari ${totalPages}`, width - marginX, height - 60);
            ctx.textAlign = 'left';
        }

        function drawBlockSectionTitle(ctx, title, x, y, width) {
            const titleHeight = A4_EXPORT.blockTitleHeight;

            ctx.fillStyle = '#eff6ff';
            ctx.fillRect(x, y, width, titleHeight - 12);
            ctx.fillStyle = '#00529c';
            ctx.fillRect(x, y, 14, titleHeight - 12);
            ctx.strokeStyle = '#bfdbfe';
            ctx.lineWidth = 3;
            ctx.strokeRect(x, y, width, titleHeight - 12);
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 34px "Inter", "Segoe UI", Arial, sans-serif';
            drawTextEllipsis(ctx, title, x + 30, y + 40, width - 60);
        }

        const captureAllDailyDashboard = async function() {
            if (window.jQuery) {
                window.jQuery(captureModal).modal('show');
                progressUI.classList.remove('d-none');
                errorUI.classList.add('d-none');
                successUI.classList.add('d-none');
            }

            const originalBtnHtml = captureBtn.innerHTML;
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> CAPTURING...';

            const cleanupModal = () => {
                if (window.jQuery) {
                    window.jQuery(captureModal).modal('hide');
                    window.jQuery('.modal-backdrop').remove();
                    window.jQuery('body').removeClass('modal-open').css('padding-right', '');
                }
            };

            try {
                // 1. Group rows by segments (blocks)
                const tbodyRows = Array.from(document.querySelectorAll('#daily-dashboard-body tr'));
                const blockDefinitions = [
                    { className: 'metric-block-simpanan', label: '1. Simpanan' },
                    { className: 'metric-block-os', label: '2. Pinjaman - Outstanding' },
                    { className: 'metric-block-sml', label: '3. Pinjaman - SML' },
                    { className: 'metric-block-npl', label: '4. Pinjaman - NPL' },
                    { className: 'metric-block-casa', label: '5. Rasio CASA' },
                    { className: 'metric-block-ldr', label: '6. Rasio LDR' },
                    { className: 'metric-block-recdh', label: '7. Recovery DH' }
                ];
                const blockClasses = blockDefinitions.map(block => block.className);

                const blockSegments = [];
                let currentBlock = null;

                tbodyRows.forEach(row => {
                    if (row.classList.contains('row-hidden-by-scope')) return;
                    
                    const blockIndex = blockClasses.findIndex(cls => row.classList.contains(cls));
                    const isNewBlock = blockIndex !== -1;
                    
                    if (isNewBlock || !currentBlock) {
                        const definition = blockDefinitions[blockIndex] || { label: 'Keragaan Harian' };
                        currentBlock = { 
                            blockId: isNewBlock ? blockIndex + 1 : (currentBlock ? currentBlock.blockId : 1),
                            label: definition.label,
                            rows: [row],
                            canvases: [] 
                        };
                        blockSegments.push(currentBlock);
                    } else {
                        currentBlock.rows.push(row);
                    }
                });

                if (blockSegments.length === 0) throw new Error('Tidak ada data untuk dicapture.');

                // 2. Pre-render each block's segments
                const originalTable = document.querySelector('.daily-table');
                
                // Get precise computed widths of all columns from a fully-rendered row in the original table
                let originalColWidths = [];
                const expectedColCount = positionH1Header && positionH1Header.classList.contains('position-col-hidden') ? 18 : 19;
                for (const row of tbodyRows) {
                    if (row.cells.length === expectedColCount && !row.classList.contains('row-hidden-by-scope')) {
                        originalColWidths = Array.from(row.cells).map(cell => cell.getBoundingClientRect().width);
                        break;
                    }
                }
                if (originalColWidths.length === 0) {
                    // Fallback to the first row of tbody if no matching non-hidden rows found with the visible column count
                    const firstRow = document.querySelector('#daily-dashboard-body tr');
                    if (firstRow && firstRow.cells.length === expectedColCount) {
                        originalColWidths = Array.from(firstRow.cells).map(cell => cell.getBoundingClientRect().width);
                    }
                }

                const theadHtml = originalTable.querySelector('thead').outerHTML;
                let colgroupHtml = '';
                let tableRealWidth = 0;
                if (originalColWidths.length === expectedColCount) {
                    colgroupHtml = '<colgroup>' + originalColWidths.map(w => `<col style="width: ${w}px; min-width: ${w}px; max-width: ${w}px;">`).join('') + '</colgroup>';
                    tableRealWidth = originalColWidths.reduce((a, b) => a + b, 0);
                } else {
                    colgroupHtml = originalTable.querySelector('colgroup').outerHTML;
                    tableRealWidth = Math.ceil(Math.max(originalTable.scrollWidth, originalTable.getBoundingClientRect().width));
                }

                for (let b = 0; b < blockSegments.length; b++) {
                    const block = blockSegments[b];
                    const subGroups = [];
                    const MAX_ROWS_PER_SUB_SEGMENT = 22;
                    for (let i = 0; i < block.rows.length; i += MAX_ROWS_PER_SUB_SEGMENT) {
                        subGroups.push(block.rows.slice(i, i + MAX_ROWS_PER_SUB_SEGMENT));
                    }
                    if (subGroups.length > 1 && subGroups[subGroups.length - 1].length <= 2) {
                        subGroups[subGroups.length - 2].push(...subGroups.pop());
                    }

                    for (let s = 0; s < subGroups.length; s++) {
                        const rows = subGroups[s];
                        const tempWrap = document.createElement('div');
                        tempWrap.className = 'daily-capture-temp-wrap';
                        tempWrap.style.cssText = `position: absolute; left: -9999px; top: 0; width: ${tableRealWidth + 40}px; background: #ffffff; padding: 0; margin: 0; box-sizing: border-box; overflow: visible;`;
                        
                        const tempStyle = document.createElement('style');
                        tempStyle.textContent = `
                            .daily-table {
                                width: ${tableRealWidth}px !important;
                                min-width: ${tableRealWidth}px !important;
                                border-collapse: separate !important;
                                border-spacing: 0 !important;
                                table-layout: fixed !important;
                                margin: 0 !important;
                                background: #ffffff !important;
                            }
                            .daily-table th,
                            .daily-table td {
                                border: 1px solid #dbe5ef !important;
                                box-sizing: border-box !important;
                                overflow: visible !important;
                                padding: 4px 7px !important;
                                white-space: nowrap !important;
                            }
                            .daily-table tbody td {
                                font-size: 12px !important;
                                line-height: 1.2 !important;
                            }
                            .daily-table .metric-label,
                            .daily-table .cell-text {
                                display: inline-block !important;
                                max-width: none !important;
                                overflow: visible !important;
                                text-overflow: clip !important;
                                white-space: nowrap !important;
                            }
                            .daily-table .sticky-label,
                            .daily-table .group-label {
                                position: static !important;
                                left: auto !important;
                                z-index: auto !important;
                                box-shadow: none !important;
                            }
                            .daily-table thead th { background: #004685 !important; color: #ffffff !important; font-weight: bold !important; font-size: 11px !important; }
                            .daily-table thead .column-heading { padding: 4px 0 !important; line-height: 1.12 !important; }
                            .daily-table thead .header-subnote { margin-top: 3px !important; padding-top: 3px !important; font-size: 9px !important; }
                            .daily-table .group-position { background: #004685 !important; }
                            .daily-table .group-delta { background: #334155 !important; }
                            .daily-table .group-rka { background: #15803d !important; }
                            .daily-table .delta-positive { color: #16a34a !important; font-weight: bold !important; }
                            .daily-table .delta-negative { color: #dc2626 !important; font-weight: bold !important; }
                        `;
                        tempWrap.appendChild(tempStyle);

                        // Capture wrapper container to add safe padding and prevent outer border cutoffs
                        const captureContainer = document.createElement('div');
                        captureContainer.className = 'capture-container';
                        captureContainer.style.cssText = `background: #ffffff; padding: 16px; box-sizing: border-box; display: inline-block; width: ${tableRealWidth + 32}px; overflow: visible;`;

                        const tableHtml = `<table class="daily-table" style="width: ${tableRealWidth}px; table-layout: fixed;">${colgroupHtml}${theadHtml}<tbody>${rows.map(r => {
                            const clone = r.cloneNode(true);
                            clone.querySelectorAll('.sticky-label, td, th').forEach(cell => {
                                cell.style.position = 'static';
                                cell.style.backgroundColor = window.getComputedStyle(cell).backgroundColor;
                                cell.style.color = window.getComputedStyle(cell).color;
                                cell.style.visibility = 'visible';
                                cell.style.opacity = '1';
                            });
                            return clone.outerHTML;
                        }).join('')}</tbody></table>`;
                        
                        captureContainer.innerHTML = tableHtml;
                        tempWrap.appendChild(captureContainer);
                        document.body.appendChild(tempWrap);
                        
                        await waitFrame();
                        const captureWidth = Math.ceil(captureContainer.getBoundingClientRect().width);
                        const captureHeight = Math.ceil(captureContainer.getBoundingClientRect().height);
                        
                        const segmentCanvas = await html2canvas(captureContainer, {
                            scale: 4.0,
                            useCORS: true,
                            backgroundColor: '#ffffff',
                            logging: false,
                            width: captureWidth,
                            height: captureHeight,
                            windowWidth: captureWidth,
                            windowHeight: captureHeight
                        });
                        document.body.removeChild(tempWrap);
                        
                        const targetWidth = A4_EXPORT.width - (A4_EXPORT.marginX * 2);
                        const scaleFactor = targetWidth / segmentCanvas.width;
                        block.canvases.push({
                            canvas: segmentCanvas,
                            title: subGroups.length > 1 ? `${block.label} (${s + 1}/${subGroups.length})` : block.label,
                            drawWidth: targetWidth,
                            drawHeight: segmentCanvas.height * scaleFactor,
                        });
                    }
                }

                // 3. Compact Grouping into two A4 parts
                const masterGroups = [
                    { label: 'SIMPANAN, RASIO & RECOVERY', blocks: blockSegments.filter(b => b.blockId === 1 || b.blockId >= 5), filename: 'Part-1_Simpanan-Rasio-Recovery' },
                    { label: 'PINJAMAN: OS, SML & NPL', blocks: blockSegments.filter(b => b.blockId >= 2 && b.blockId <= 4), filename: 'Part-2_Pinjaman' }
                ];

                for (let g = 0; g < masterGroups.length; g++) {
                    const group = masterGroups[g];
                    if (group.blocks.length === 0) continue;

                    const groupCanvases = group.blocks.flatMap(b => b.canvases);
                    const pages = [];
                    let currentPage = { items: [], totalHeight: 0 };
                    // Extra buffer for compact content
                    const maxContentHeight = A4_EXPORT.height - (A4_EXPORT.marginY + A4_EXPORT.headerHeight + A4_EXPORT.footerHeight + 80);

                    groupCanvases.forEach(item => {
                        item.totalHeight = item.drawHeight + A4_EXPORT.blockTitleHeight;
                        if (item.totalHeight > maxContentHeight) {
                            const availableTableHeight = Math.max(1, maxContentHeight - A4_EXPORT.blockTitleHeight);
                            const fitScale = availableTableHeight / item.drawHeight;
                            item.drawWidth *= fitScale;
                            item.drawHeight *= fitScale;
                            item.totalHeight = item.drawHeight + A4_EXPORT.blockTitleHeight;
                        }

                        const estimatedNewHeight = currentPage.totalHeight + (currentPage.items.length > 0 ? A4_EXPORT.sectionGap : 0) + item.totalHeight;
                        if (estimatedNewHeight > maxContentHeight && currentPage.items.length > 0) {
                            pages.push(currentPage);
                            currentPage = { items: [item], totalHeight: item.totalHeight };
                        } else {
                            if (currentPage.items.length > 0) currentPage.totalHeight += A4_EXPORT.sectionGap;
                            currentPage.items.push(item);
                            currentPage.totalHeight += item.totalHeight;
                        }
                    });
                    if (currentPage.items.length > 0) pages.push(currentPage);

                    for (let p = 0; p < pages.length; p++) {
                        const pageData = pages[p];
                        const pageCanvas = document.createElement('canvas');
                        pageCanvas.width = A4_EXPORT.width;
                        pageCanvas.height = A4_EXPORT.height;
                        const ctx = pageCanvas.getContext('2d');
                        
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, pageCanvas.width, pageCanvas.height);
                        drawExportHeader(ctx, p + 1, group.label);

                        let currentY = A4_EXPORT.marginY + A4_EXPORT.headerHeight;
                        pageData.items.forEach(item => {
                            drawBlockSectionTitle(ctx, item.title || group.label, A4_EXPORT.marginX, currentY, item.drawWidth);
                            currentY += A4_EXPORT.blockTitleHeight;
                            ctx.drawImage(item.canvas, A4_EXPORT.marginX, currentY, item.drawWidth, item.drawHeight);
                            currentY += item.drawHeight + A4_EXPORT.sectionGap;
                        });

                        drawExportFooter(ctx, p + 1, pages.length);

                        const timestamp = new Date().toISOString().slice(0, 10);
                        const link = document.createElement('a');
                        const pageSuffix = pages.length > 1 ? `_Hal-${p + 1}` : '';
                        link.download = `Daily-Dashboard_${group.filename}_${timestamp}${pageSuffix}.png`;
                        link.href = pageCanvas.toDataURL('image/png');
                        link.click();
                        await waitFrame();
                    }
                }

                progressUI.classList.add('d-none');
                successUI.classList.remove('d-none');
                setTimeout(() => cleanupModal(), 1500);
            } catch (err) {
                console.error('Capture failed:', err);
                progressUI.classList.add('d-none');
                errorUI.classList.remove('d-none');
                errorMessageUI.textContent = 'Gagal menyusun laporan A4. Silakan coba lagi.';
            } finally {
                captureBtn.disabled = false;
                captureBtn.innerHTML = originalBtnHtml;
            }
};

// Ensure modal cleanup on close to fix black overlay bug
if (window.jQuery && captureModal) {
    window.jQuery(captureModal).on('hidden.bs.modal', function() {
        window.jQuery('.modal-backdrop').remove();
        window.jQuery('body').removeClass('modal-open').css('padding-right', '');
    });
}

        if (captureBtn) {
            captureBtn.addEventListener('click', captureAllDailyDashboard);
        }

        renderKancaDropdown(initialFilters.kanca || [], initialSelected.kanca || []);
        syncUnitSelect(initialFilters, initialSelected.unit_kerja || 'all');
        syncPosisiSelect(initialFilters.posisi_terakhir || [], initialSelected.posisi_terakhir || '');
        syncRkaSelect(initialFilters.posisi_rka || [], initialSelected.posisi_rka || '');
        syncMtmSelect(initialFilters.mtm_period || initialFilters.posisi_terakhir || [], initialSelected.mtm_period || '', null);

        body.innerHTML = '<tr><td colspan="20" class="daily-empty"><i class="fas fa-filter mr-2 text-muted"></i>Filter belum dijalankan. Pilih parameter lalu klik Terapkan Filter.</td></tr>';

        if (initialData && Object.keys(initialData).length) {
            applyPayload(initialData);
        } else {
            sourceLabel.textContent = '-';
            scheduleTableViewportSync();
        }

        const toggleBtn = document.getElementById('btn-toggle-filters');
        const filterShell = document.querySelector('.daily-filter-shell');
        const setFilterPanelOpen = function (isOpen) {
            if (!toggleBtn || !filterShell) {
                return;
            }

            filterShell.classList.toggle('is-open', isOpen);
            toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            const toggleText = toggleBtn.querySelector('.btn-toggle-text');
            if (toggleText) {
                toggleText.innerHTML = isOpen
                    ? '<i class="fas fa-times mr-2"></i> SEMBUNYIKAN'
                    : '<i class="fas fa-sliders-h mr-2"></i> FILTER DATA';
            }
        };

        if (toggleBtn && filterShell) {
            toggleBtn.addEventListener('click', function () {
                setFilterPanelOpen(!filterShell.classList.contains('is-open'));
            });

            setFilterPanelOpen(false);
        }

        if (applyButton) {
            applyButton.addEventListener('click', function () {
                setFilterPanelOpen(false);
                fetchData();
            });
        }
        updateFilterSummary();

        document.querySelectorAll('[data-mtm-toggle]').forEach(function (node) {
            node.addEventListener('dblclick', function (event) {
                event.stopPropagation();
                showMtmOverridePanel(event);
                if (selects.mtm_period) {
                    selects.mtm_period.focus();
                }
            });
        });

        if (selects.mtm_period) {
            selects.mtm_period.addEventListener('change', function () {
                if (selects.mtm_period.value) {
                    showMtmOverridePanel();
                } else {
                    hideMtmOverridePanel();
                }
                fetchData();
            });
        }

        if (resetMtmButton) {
            resetMtmButton.addEventListener('click', function () {
                if (selects.mtm_period) {
                    selects.mtm_period.value = '';
                }
                hideMtmOverridePanel();
                fetchData();
            });
        }

        // --- Event Listeners for Dropdowns ---
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

        if (dropdowns.posisi && dropdowns.posisi.toggle) {
            dropdowns.posisi.toggle.addEventListener('click', function () {
                if (dropdowns.posisi.root.classList.contains('is-open')) {
                    closeDropdown('posisi');
                    return;
                }
                openDropdown('posisi');
            });
        }

        if (dropdowns.rka && dropdowns.rka.toggle) {
            dropdowns.rka.toggle.addEventListener('click', function () {
                if (dropdowns.rka.root.classList.contains('is-open')) {
                    closeDropdown('rka');
                    return;
                }
                openDropdown('rka');
            });
        }

        if (dropdowns.kanca && dropdowns.kanca.menu) {
            dropdowns.kanca.menu.addEventListener('click', function (event) {
                const option = event.target.closest('[data-kanca-option]');
                if (!option || !selects.kanca) return;

                const value = String(option.getAttribute('data-kanca-option') || 'all');
                let nextValues = getSelectedKancaValues();
                const area6Active = isArea6KancaSelection(latestFilters.kanca || [], nextValues);

                if (value === 'all') {
                    nextValues = [];
                } else if (area6Active) {
                    nextValues = [value];
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
                if (!option || !selects.unit_kerja) return;

                const value = String(option.getAttribute('data-unit-option') || 'all');
                selects.unit_kerja.value = value;
                syncUnitSelect(latestFilters, value);
                closeDropdown('unit');
            });
        }

        if (dropdowns.posisi && dropdowns.posisi.menu) {
            dropdowns.posisi.menu.addEventListener('click', function (event) {
                const option = event.target.closest('[data-posisi-option]');
                if (!option || !selects.posisi_terakhir) return;

                const value = String(option.getAttribute('data-posisi-option') || '');
                selects.posisi_terakhir.value = value;
                syncPosisiSelect(latestFilters.posisi_terakhir || [], value);
                closeDropdown('posisi');
            });
        }

        if (dropdowns.rka && dropdowns.rka.menu) {
            dropdowns.rka.menu.addEventListener('click', function (event) {
                const option = event.target.closest('[data-rka-option]');
                if (!option || !selects.posisi_rka) return;

                const value = String(option.getAttribute('data-rka-option') || '');
                selects.posisi_rka.value = value;
                syncRkaSelect(latestFilters.posisi_rka || [], value);
                closeDropdown('rka');
            });
        }

        document.addEventListener('click', function (event) {
            Object.keys(dropdowns).forEach(function (key) {
                const dropdown = dropdowns[key];
                if (dropdown && dropdown.root && !dropdown.root.contains(event.target)) {
                    closeDropdown(key);
                }
            });

            if (
                mtmOverridePanel
                && mtmOverridePanel.classList.contains('is-visible')
                && !mtmOverridePanel.contains(event.target)
                && !event.target.closest('[data-mtm-toggle]')
                && !(selects.mtm_period && selects.mtm_period.value)
            ) {
                hideMtmOverridePanelIfDefault();
            }
        });

        document.addEventListener('dblclick', function (event) {
            if (
                mtmOverridePanel
                && mtmOverridePanel.classList.contains('is-visible')
                && !mtmOverridePanel.contains(event.target)
                && !event.target.closest('[data-mtm-toggle]')
            ) {
                hideMtmOverridePanel();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideMtmOverridePanel();
            }
        });

        window.addEventListener('resize', handleResponsiveViewportChange);
        window.addEventListener('orientationchange', handleResponsiveViewportChange);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', handleResponsiveViewportChange);
            window.visualViewport.addEventListener('scroll', scheduleTableViewportSync);
        }
        scheduleTableViewportSync();
    });
</script>
@endsection
