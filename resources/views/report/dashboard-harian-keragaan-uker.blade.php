@extends('layouts.admin')

@section('title', 'Keragaan per Uker')

@section('styles')
<style>
    .uker-page {
        color: #111827;
        padding-bottom: 1.5rem;
    }

    .uker-shell {
        background: #ffffff;
        border: 1px solid #dbe4ef;
        border-radius: 12px;
        box-shadow: 0 12px 28px -24px rgba(15, 23, 42, 0.25);
        overflow: visible !important;
        position: relative;
    }

    .uker-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.25rem;
        padding: 1.1rem 1.5rem;
        background: linear-gradient(135deg, #071936 0%, #0d2b5c 50%, #1e40af 100%);
        color: #ffffff;
        border-top-left-radius: 11px;
        border-top-right-radius: 11px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 4px 16px rgba(11, 34, 71, 0.15);
    }

    .uker-header-main {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        min-width: 0;
    }

    .uker-title-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 0;
    }

    .uker-title-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.18);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #38bdf8;
        font-size: 1.15rem;
        box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.2), 0 4px 12px rgba(0, 0, 0, 0.2);
        flex-shrink: 0;
    }

    .uker-title-content {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .uker-title {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.015em;
        line-height: 1.25;
    }

    /* Redesigned Context Bar: Sleek and structured, no more messy overlapping pills! */
    .uker-context-bar {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.35rem;
        background: rgba(7, 23, 49, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.14);
        padding: 0.22rem 0.65rem;
        border-radius: 6px;
        backdrop-filter: blur(8px);
    }

    .context-item {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.74rem;
        line-height: 1;
    }

    .context-icon {
        color: #38bdf8;
        font-size: 0.72rem;
    }

    .context-title {
        color: #94a3b8;
        font-weight: 700;
        font-size: 0.65rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .context-value {
        color: #ffffff;
        font-weight: 800;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .context-separator {
        color: rgba(255, 255, 255, 0.25);
        font-size: 0.75rem;
        user-select: none;
    }

    /* Redesigned Period Card on Header Right */
    .uker-period-card {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.18);
        padding: 0.4rem 0.85rem;
        border-radius: 8px;
        backdrop-filter: blur(8px);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        min-width: 140px;
        flex-shrink: 0;
    }

    .period-card-sub {
        font-size: 0.6rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #93c5fd;
    }

    .period-card-date {
        font-size: 0.95rem;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.01em;
        margin-top: 0.1rem;
    }

    /* Filter Shell & Grid */
    .uker-filter-shell {
        position: relative;
        z-index: 100;
        margin: 1rem 1.25rem 0.65rem;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.03);
        padding: 0.85rem 1.15rem;
    }

    .uker-filters-bar {
        display: grid;
        grid-template-columns: repeat(5, minmax(140px, 1fr));
        gap: 0.85rem;
        align-items: flex-end;
    }

    .uker-filter-item {
        display: flex;
        flex-direction: column;
        min-width: 0;
        position: relative;
        z-index: 1;
    }

    .uker-filter-item.has-open-dropdown {
        z-index: 1100 !important;
    }

    .uker-filter-label {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-bottom: 0.35rem;
        color: #334155;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .uker-filter-label i {
        color: #0b57d0;
        font-size: 0.72rem;
    }

    /* Custom Dropdown Styling */
    .custom-select-wrap {
        position: relative;
        width: 100%;
    }

    .custom-select-wrap.is-open {
        z-index: 1100 !important;
    }

    .custom-select-native-hidden {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .custom-select-trigger {
        width: 100%;
        height: 38px;
        border-radius: 8px;
        border: 1.5px solid #cbd5e1;
        background: #ffffff;
        color: #1e293b;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 0 0.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
        cursor: pointer;
        outline: none;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
    }

    .custom-select-trigger:hover:not(:disabled) {
        border-color: #0b57d0;
        background-color: #f8fafc;
    }

    .custom-select-wrap.is-open .custom-select-trigger {
        border-color: #0b57d0;
        box-shadow: 0 0 0 3px rgba(11, 87, 208, 0.14);
        background: #ffffff;
    }

    .custom-select-trigger:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        border-color: #e2e8f0;
        cursor: not-allowed;
    }

    .custom-select-label {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #1e293b;
    }

    .custom-select-trigger:disabled .custom-select-label {
        color: #94a3b8;
    }

    .custom-select-arrow {
        color: #64748b;
        font-size: 0.72rem;
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }

    .custom-select-wrap.is-open .custom-select-arrow {
        transform: rotate(180deg);
        color: #0b57d0;
    }

    .custom-select-panel {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        min-width: max(100%, 250px);
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        box-shadow: 0 20px 35px -8px rgba(15, 23, 42, 0.22), 0 10px 18px -4px rgba(15, 23, 42, 0.12);
        z-index: 1150 !important;
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: customSelectFadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes customSelectFadeIn {
        from {
            opacity: 0;
            transform: translateY(-4px) scale(0.99);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .custom-select-wrap.is-open .custom-select-panel {
        display: flex;
    }

    .custom-select-search-box {
        position: relative;
        padding: 0.45rem;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .custom-select-search-box .search-icon {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.74rem;
    }

    .custom-select-search-input {
        width: 100%;
        height: 30px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding-left: 1.8rem;
        padding-right: 0.5rem;
        font-size: 0.78rem;
        outline: none;
        transition: border-color 0.15s ease;
    }

    .custom-select-search-input:focus {
        border-color: #0b57d0;
        box-shadow: 0 0 0 2px rgba(11, 87, 208, 0.1);
    }

    .custom-select-options {
        max-height: 220px;
        overflow-y: auto;
        padding: 0.3rem 0;
        margin: 0;
        list-style: none;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .custom-select-options::-webkit-scrollbar {
        width: 6px;
    }

    .custom-select-options::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 999px;
    }

    .custom-select-option {
        padding: 0.48rem 0.75rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        transition: all 0.12s ease;
    }

    .custom-select-option:hover {
        background-color: #f1f5f9;
        color: #0b57d0;
    }

    .custom-select-option.is-selected {
        background-color: #eff6ff;
        color: #0b57d0;
        font-weight: 800;
    }

    .custom-select-option.is-selected .opt-check {
        display: inline-block;
        color: #0b57d0;
        font-size: 0.74rem;
    }

    .custom-select-option .opt-check {
        display: none;
    }

    .custom-select-empty {
        padding: 0.85rem;
        font-size: 0.76rem;
        text-align: center;
        color: #94a3b8;
        font-weight: 600;
    }

    /* Date Picker Input Custom Styling */
    .custom-date-wrap {
        position: relative;
        width: 100%;
        display: flex;
        align-items: center;
    }

    .custom-date-control {
        width: 100%;
        height: 38px;
        border-radius: 8px;
        border: 1.5px solid #cbd5e1;
        background: #ffffff;
        color: #1e293b;
        font-size: 0.82rem;
        font-weight: 700;
        padding-left: 0.75rem;
        padding-right: 0.65rem;
        outline: none;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .custom-date-control:hover {
        border-color: #0b57d0;
        background-color: #f8fafc;
    }

    .custom-date-control:focus {
        border-color: #0b57d0;
        box-shadow: 0 0 0 3px rgba(11, 87, 208, 0.14);
    }

    .uker-filter-mobile-toggle {
        display: none;
    }

    .btn-filter-toggle {
        width: 100%;
        padding: 0.65rem 0.85rem;
        border: none;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: #0b2247;
        font-size: 0.84rem;
        font-weight: 800;
        cursor: pointer;
    }

    .active-filters-badge {
        font-size: 0.72rem;
        color: #475569;
        font-weight: 700;
        margin-left: auto;
        margin-right: 0.5rem;
        background: #f1f5f9;
        padding: 0.2rem 0.55rem;
        border-radius: 6px;
    }

    .toggle-arrow-icon {
        transition: transform 0.2s ease;
        color: #64748b;
    }

    .uker-filter-shell.is-open .toggle-arrow-icon {
        transform: rotate(180deg);
    }

    .uker-table-wrap {
        position: relative;
        width: 100%;
        max-height: min(680px, calc(100dvh - 280px));
        overflow: auto;
        isolation: isolate;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        scrollbar-color: #94a3b8 #eef2f7;
        scrollbar-width: thin;
    }

    .uker-tables {
        display: grid;
        gap: 1rem;
        padding: 0.5rem 1.25rem 1.25rem;
    }

    .uker-table-card {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
    }

    .uker-table-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 0.9rem;
        border-bottom: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #0f172a;
        font-size: 0.9rem;
        font-weight: 800;
    }

    .uker-table-wrap::-webkit-scrollbar {
        width: 11px;
        height: 11px;
    }

    .uker-table-wrap::-webkit-scrollbar-track {
        background: #eef2f7;
    }

    .uker-table-wrap::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border: 2px solid #eef2f7;
        border-radius: 999px;
    }

    .uker-table {
        --uker-code-width: 85px;
        --uker-name-width: 220px;
        width: 100% !important;
        min-width: 1475px !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        font-size: 0.82rem !important;
        table-layout: fixed !important;
    }

    .uker-table th,
    .uker-table td {
        box-sizing: border-box !important;
        border-right: 1px solid #d7e1ed !important;
        border-bottom: 1px solid #d7e1ed !important;
        padding: 0.48rem 0.55rem !important;
        vertical-align: middle !important;
        white-space: nowrap !important;
        position: relative;
    }

    .uker-table tbody td {
        z-index: 1 !important;
        background-color: #ffffff !important;
    }

    .uker-table tbody tr:nth-child(even) td {
        background-color: #f9fbfd !important;
    }

    .uker-table thead tr.uker-table-header-row-group th {
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        height: 26px !important;
        padding: 0.25rem 0.4rem !important;
        font-size: 0.72rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        background-color: #0b2247 !important;
        color: #ffffff !important;
        text-align: center !important;
        border-right: 1px solid #1e40af !important;
        border-bottom: 1px solid #1e40af !important;
    }

    .uker-table thead tr.uker-table-header-row-group th.uker-group-unit {
        position: sticky !important;
        top: 0 !important;
        left: 0 !important;
        z-index: 30 !important;
        width: calc(var(--uker-code-width) + var(--uker-name-width)) !important;
        min-width: calc(var(--uker-code-width) + var(--uker-name-width)) !important;
        max-width: calc(var(--uker-code-width) + var(--uker-name-width)) !important;
        background-color: #0b2247 !important;
        border-right: 2px solid #b9c8da !important;
    }

    .uker-table thead tr.uker-table-header-row-sub th {
        position: sticky !important;
        top: 26px !important;
        z-index: 10 !important;
        height: 28px !important;
        padding: 0.35rem 0.45rem !important;
        font-size: 0.74rem !important;
        font-weight: 800 !important;
        background-color: #102f5f !important;
        color: #ffffff !important;
        text-align: center !important;
        border-right: 1px solid #d7e1ed !important;
        border-bottom: 1px solid #d7e1ed !important;
    }

    .uker-table thead tr.uker-table-header-row-sub th.uker-code {
        position: sticky !important;
        top: 26px !important;
        left: 0 !important;
        z-index: 30 !important;
        background-color: #102f5f !important;
        width: var(--uker-code-width) !important;
        min-width: var(--uker-code-width) !important;
        max-width: var(--uker-code-width) !important;
        box-sizing: border-box !important;
    }

    .uker-table thead tr.uker-table-header-row-sub th.uker-name {
        position: sticky !important;
        top: 26px !important;
        left: var(--uker-code-width) !important;
        z-index: 30 !important;
        background-color: #102f5f !important;
        width: var(--uker-name-width) !important;
        min-width: var(--uker-name-width) !important;
        max-width: var(--uker-name-width) !important;
        box-sizing: border-box !important;
        border-right: 2px solid #b9c8da !important;
        box-shadow: 4px 0 8px -2px rgba(15, 23, 42, 0.25) !important;
    }

    .uker-table .uker-code {
        width: var(--uker-code-width) !important;
        min-width: var(--uker-code-width) !important;
        max-width: var(--uker-code-width) !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        color: #334155 !important;
        font-weight: 800 !important;
        text-align: center !important;
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
    }

    .uker-table .uker-name {
        width: var(--uker-name-width) !important;
        min-width: var(--uker-name-width) !important;
        max-width: var(--uker-name-width) !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        color: #173a66 !important;
        font-weight: 800 !important;
    }

    .uker-table tbody tr > td.uker-code {
        position: sticky !important;
        left: 0 !important;
        z-index: 20 !important;
        background-color: #ffffff !important;
    }

    .uker-table tbody tr > td.uker-name {
        position: sticky !important;
        left: var(--uker-code-width) !important;
        z-index: 20 !important;
        background-color: #ffffff !important;
        border-right: 2px solid #b9c8da !important;
        box-shadow: 4px 0 8px -2px rgba(15, 23, 42, 0.25) !important;
    }

    .uker-table tbody tr:nth-child(even) > td.uker-code,
    .uker-table tbody tr:nth-child(even) > td.uker-name {
        z-index: 20 !important;
        background-color: #f9fbfd !important;
    }

    .uker-table tbody tr.total-row > td.uker-code,
    .uker-table tbody tr.total-row > td.uker-name {
        z-index: 21 !important;
        background-color: #eef5ff !important;
    }

    .uker-table .number {
        text-align: right !important;
        font-variant-numeric: tabular-nums !important;
    }

    .tone-pill {
        display: inline-flex;
        justify-content: flex-end;
        min-width: 96px;
        padding: 0.2rem 0.45rem;
        border-radius: 5px;
        font-weight: 900;
    }

    .tone-good {
        background: #dcfce7;
        color: #166534;
    }

    .tone-flat {
        background: #fef3c7;
        color: #92400e;
    }

    .tone-bad {
        background: #fee2e2;
        color: #991b1b;
    }

    .uker-empty,
    .uker-loading {
        display: none;
        padding: 4rem 1.5rem;
        text-align: center;
        color: #64748b;
        font-weight: 800;
    }

    .uker-shell.is-loading,
    .uker-shell.is-empty {
        min-height: 480px;
    }

    .uker-shell.is-loading .uker-loading {
        display: block;
    }

    .uker-shell.is-loading .uker-tables,
    .uker-shell.is-empty .uker-tables {
        display: none;
    }

    .uker-shell.is-empty .uker-empty {
        display: block;
    }

    @media (max-width: 1200px) {
        .uker-filters {
            grid-template-columns: repeat(3, minmax(150px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .content-wrapper .content {
            padding-left: 0.55rem;
            padding-right: 0.55rem;
        }

        .uker-header {
            align-items: flex-start;
            flex-direction: column;
            padding: 0.8rem;
        }

        .uker-filters {
            grid-template-columns: 1fr;
            gap: 0.6rem;
            padding: 0.8rem;
        }

        .uker-table-wrap {
            max-height: min(62dvh, 520px);
        }

        .uker-table {
            --uker-code-width: 96px;
            --uker-name-width: 210px;
            min-width: 1380px !important;
            font-size: 0.78rem !important;
        }

        .uker-table th,
        .uker-table td {
            padding: 0.46rem 0.5rem !important;
        }

        .uker-table > thead > tr > th:nth-child(1),
        .uker-table > tbody > tr > td:nth-child(1) {
            left: 0 !important;
            width: 96px !important;
            min-width: 96px !important;
            max-width: 96px !important;
        }

        .uker-table > thead > tr > th:nth-child(2),
        .uker-table > tbody > tr > td:nth-child(2) {
            left: 96px !important;
            width: 210px !important;
            min-width: 210px !important;
            max-width: 210px !important;
        }
    }

    .tone-pill {
        display: inline-flex;
        justify-content: flex-end;
        min-width: 96px;
        padding: 0.2rem 0.45rem;
        border-radius: 5px;
        font-weight: 900;
    }

    .tone-good {
        background: #dcfce7;
        color: #166534;
    }

    .tone-flat {
        background: #fef3c7;
        color: #92400e;
    }

    .tone-bad {
        background: #fee2e2;
        color: #991b1b;
    }

    .uker-empty,
    .uker-loading {
        display: none;
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
        font-weight: 800;
    }

    .uker-shell.is-loading .uker-loading {
        display: block;
    }

    .uker-shell.is-loading .uker-tables,
    .uker-shell.is-empty .uker-tables {
        display: none;
    }

    .uker-shell.is-empty .uker-empty {
        display: block;
    }

    @media (max-width: 1200px) {
        .uker-filters-bar {
            grid-template-columns: repeat(3, minmax(140px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .content-wrapper .content {
            padding-left: 0.55rem;
            padding-right: 0.55rem;
        }

        .uker-header {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.85rem;
            padding: 0.9rem;
        }

        .uker-title-row {
            align-items: flex-start;
        }

        .uker-period-card {
            align-items: flex-start;
            width: 100%;
        }

        .uker-filter-mobile-toggle {
            display: block;
        }

        .uker-filters-bar {
            display: none;
            grid-template-columns: 1fr;
            gap: 0.65rem;
            margin-top: 0.65rem;
            padding-top: 0.65rem;
            border-top: 1px solid #e2e8f0;
        }

        .uker-filter-shell.is-open .uker-filters-bar {
            display: grid;
        }

        .uker-table-wrap {
            max-height: min(62dvh, 520px);
        }

        .uker-table {
            --uker-code-width: 96px;
            --uker-name-width: 210px;
            min-width: 1380px;
            font-size: 0.78rem;
        }

        .uker-table th,
        .uker-table td {
            padding: 0.46rem 0.5rem;
        }
    }

    /* Sorting Header & Badge Styles */
    .uker-th-sortable {
        cursor: pointer !important;
        user-select: none !important;
        transition: background-color 0.15s ease, color 0.15s ease !important;
    }

    .uker-th-sortable:hover {
        background-color: #163d75 !important;
        color: #e0f2fe !important;
    }

    .uker-th-sortable.is-sorted {
        background-color: #133668 !important;
        color: #38bdf8 !important;
        box-shadow: inset 0 -2px 0 0 #38bdf8 !important;
    }

    .th-sort-inner {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        width: 100%;
        pointer-events: none;
    }

    .th-sort-label {
        white-space: nowrap;
    }

    .th-sort-icon {
        font-size: 0.72rem;
        opacity: 0.45;
        transition: opacity 0.15s ease, color 0.15s ease;
        display: inline-block;
        width: 10px;
        text-align: center;
    }

    .uker-th-sortable:hover .th-sort-icon {
        opacity: 0.9;
    }

    .uker-th-sortable.is-sorted .th-sort-icon {
        opacity: 1;
        color: #38bdf8;
    }

    .uker-sort-reset-btn {
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        padding: 0.2rem 0.5rem;
        font-size: 0.7rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: all 0.15s ease;
    }

    .uker-sort-reset-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
        border-color: #94a3b8;
    }

    .uker-sort-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        background: #e0f2fe;
        color: #0369a1;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 0.15rem 0.45rem;
        border-radius: 4px;
        border: 1px solid #bae6fd;
    }
</style>
@endsection

@section('content')


<section class="content uker-page">
    <div class="container-fluid">
        <div class="uker-shell" id="ukerShell">
            <div class="uker-header">
                <div class="uker-header-main">
                    <div class="uker-title-row">
                        <div class="uker-title-icon-box">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="uker-title-content">
                            <h2 class="uker-title" id="ukerTitle">Keragaan per Unit Kerja</h2>
                            <div class="uker-context-bar">
                                <div class="context-item">
                                    <i class="fas fa-building context-icon"></i>
                                    <span class="context-title">Cabang:</span>
                                    <span class="context-value" id="scopeLabel">Area 6</span>
                                </div>
                                <span class="context-separator">•</span>
                                <div class="context-item">
                                    <i class="fas fa-sitemap context-icon"></i>
                                    <span class="context-title">Unit:</span>
                                    <span class="context-value" id="unitLabel">Semua Unit Kerja</span>
                                </div>
                                <span class="context-separator">•</span>
                                <div class="context-item">
                                    <i class="fas fa-database context-icon"></i>
                                    <span class="context-title">Data:</span>
                                    <span class="context-value" id="sourceLabel">Pinjaman</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="uker-period-card">
                    <span class="period-card-sub">POSISI LAPORAN</span>
                    <span class="period-card-date" id="periodLabel">-</span>
                </div>
            </div>

            <!-- Single-Row Filter Bar (Collapsible for Mobile/Tablet) -->
            <div class="uker-filter-shell" id="filterShell">
                <div class="uker-filter-mobile-toggle">
                    <button type="button" class="btn-filter-toggle" id="btnFilterToggle" aria-expanded="false">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-sliders-h"></i>
                            <span>Filter Data</span>
                        </div>
                        <span class="active-filters-badge" id="activeFiltersBadge">5 Filter Aktif</span>
                        <i class="fas fa-chevron-down toggle-arrow-icon"></i>
                    </button>
                </div>

                <div class="uker-filters-bar" id="ukerFiltersBar">
                    <!-- Cabang -->
                    <div class="uker-filter-item">
                        <label for="kancaFilter" class="uker-filter-label">
                            <i class="fas fa-building"></i>
                            <span>Cabang</span>
                        </label>
                        <div class="custom-select-wrap" id="wrap_kancaFilter">
                            <button type="button" class="custom-select-trigger" id="trigger_kancaFilter" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-select-label" id="label_kancaFilter">Pilih cabang</span>
                                <span class="custom-select-arrow"><i class="fas fa-chevron-down"></i></span>
                            </button>
                            <div class="custom-select-panel" id="panel_kancaFilter">
                                <div class="custom-select-search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="custom-select-search-input" placeholder="Cari cabang..." autocomplete="off">
                                </div>
                                <ul class="custom-select-options" role="listbox"></ul>
                                <div class="custom-select-empty" style="display:none;">Tidak ada pilihan ditemukan</div>
                            </div>
                            <select id="kancaFilter" name="kanca" class="custom-select-native-hidden" tabindex="-1" aria-hidden="true"></select>
                        </div>
                    </div>

                    <!-- Unit Kerja -->
                    <div class="uker-filter-item">
                        <label for="unitFilter" class="uker-filter-label">
                            <i class="fas fa-sitemap"></i>
                            <span>Unit Kerja</span>
                        </label>
                        <div class="custom-select-wrap" id="wrap_unitFilter">
                            <button type="button" class="custom-select-trigger" id="trigger_unitFilter" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-select-label" id="label_unitFilter">Semua Unit Kerja</span>
                                <span class="custom-select-arrow"><i class="fas fa-chevron-down"></i></span>
                            </button>
                            <div class="custom-select-panel" id="panel_unitFilter">
                                <div class="custom-select-search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="custom-select-search-input" placeholder="Cari unit kerja..." autocomplete="off">
                                </div>
                                <ul class="custom-select-options" role="listbox"></ul>
                                <div class="custom-select-empty" style="display:none;">Tidak ada pilihan ditemukan</div>
                            </div>
                            <select id="unitFilter" class="custom-select-native-hidden" tabindex="-1" aria-hidden="true"></select>
                        </div>
                    </div>

                    <!-- Jenis Data -->
                    <div class="uker-filter-item">
                        <label for="dataTypeFilter" class="uker-filter-label">
                            <i class="fas fa-database"></i>
                            <span>Jenis Data</span>
                        </label>
                        <div class="custom-select-wrap" id="wrap_dataTypeFilter">
                            <button type="button" class="custom-select-trigger" id="trigger_dataTypeFilter" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-select-label" id="label_dataTypeFilter">Pilih data</span>
                                <span class="custom-select-arrow"><i class="fas fa-chevron-down"></i></span>
                            </button>
                            <div class="custom-select-panel" id="panel_dataTypeFilter">
                                <ul class="custom-select-options" role="listbox"></ul>
                                <div class="custom-select-empty" style="display:none;">Tidak ada pilihan ditemukan</div>
                            </div>
                            <select id="dataTypeFilter" class="custom-select-native-hidden" tabindex="-1" aria-hidden="true"></select>
                        </div>
                    </div>

                    <!-- Periode Tanggal -->
                    <div class="uker-filter-item">
                        <label for="periodFilter" class="uker-filter-label">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Periode Posisi</span>
                        </label>
                        <div class="custom-date-wrap">
                            <input type="date" id="periodFilter" class="custom-date-control" aria-label="Pilih tanggal posisi">
                        </div>
                    </div>

                    <!-- Target RKA -->
                    <div class="uker-filter-item">
                        <label for="rkaFilter" class="uker-filter-label">
                            <i class="fas fa-bullseye"></i>
                            <span>Posisi RKA</span>
                        </label>
                        <div class="custom-select-wrap" id="wrap_rkaFilter">
                            <button type="button" class="custom-select-trigger" id="trigger_rkaFilter" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-select-label" id="label_rkaFilter">Pilih RKA</span>
                                <span class="custom-select-arrow"><i class="fas fa-chevron-down"></i></span>
                            </button>
                            <div class="custom-select-panel" id="panel_rkaFilter">
                                <ul class="custom-select-options" role="listbox"></ul>
                                <div class="custom-select-empty" style="display:none;">Tidak ada pilihan ditemukan</div>
                            </div>
                            <select id="rkaFilter" class="custom-select-native-hidden" tabindex="-1" aria-hidden="true"></select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="uker-loading">Memuat data...</div>
            <div class="uker-empty">Data tidak tersedia untuk filter ini.</div>

            <div class="uker-tables" id="ukerTables"></div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
    (function () {
        const page = @json($dashboardPage);
        const shell = document.getElementById('ukerShell');
        const filterShell = document.getElementById('filterShell');
        const btnFilterToggle = document.getElementById('btnFilterToggle');
        if (btnFilterToggle && filterShell) {
            btnFilterToggle.addEventListener('click', function () {
                const isOpen = filterShell.classList.toggle('is-open');
                btnFilterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }
        const els = {
            kanca: document.getElementById('kancaFilter'),
            unit: document.getElementById('unitFilter'),
            dataType: document.getElementById('dataTypeFilter'),
            period: document.getElementById('periodFilter'),
            rka: document.getElementById('rkaFilter'),
            tables: document.getElementById('ukerTables'),
            empty: document.querySelector('.uker-empty'),
            scope: document.getElementById('scopeLabel'),
            unitLabel: document.getElementById('unitLabel'),
            source: document.getElementById('sourceLabel'),
            periodLabel: document.getElementById('periodLabel'),
        };
        const dataTypeLabels = Object.fromEntries((page.dataTypes || []).map(item => [item.value, item.label]));
        let currentPayload = page.initialData || null;
        let selectedKancaValue = selectedScalar(page.selected?.kanca, '');
        let latestRequestId = 0;
        const tableSortState = {};

        function selectedScalar(value, fallback = '') {
            if (Array.isArray(value)) {
                return value.length === 1 ? value[0] : fallback;
            }

            return value || fallback;
        }

        function optionHtml(options, selectedValue) {
            return (options || []).map(option => {
                const value = String(option.value ?? '');
                const label = String(option.label ?? value);
                const selected = value === String(selectedValue ?? '') ? 'selected' : '';
                return `<option value="${escapeHtml(value)}" ${selected}>${escapeHtml(label)}</option>`;
            }).join('');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString('id-ID', {
                maximumFractionDigits: 0,
            });
        }

        function formatDelta(value) {
            const numeric = Number(value || 0) / 1000000;
            const prefix = numeric > 0 ? '+' : '';
            return `${prefix}${formatMoney(numeric)}`;
        }

        function formatPosition(value) {
            return formatMoney(Number(value || 0) / 1000000);
        }

        function formatPct(value) {
            return `${Number(value || 0).toLocaleString('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}%`;
        }

        function withSelectedOption(options, selectedValue) {
            const value = String(selectedValue || '');
            if (!value || (options || []).some(option => String(option.value ?? '') === value)) {
                return options || [];
            }

            return [...(options || []), { value, label: value }];
        }

        function syncPeriodDatePicker(options, selectedValue) {
            const values = (options || []).map(option => String(option.value || '').slice(0, 10))
                .filter(value => /^\d{4}-\d{2}-\d{2}$/.test(value))
                .sort();

            els.period.min = values.length ? values[0] : '';
            els.period.max = values.length ? values[values.length - 1] : '';
            els.period.value = selectedValue || (values.length ? values[values.length - 1] : '');
        }

        const customDropdowns = {};

        function setupCustomSelect(selectId, placeholder, hasSearch = true) {
            const nativeSelect = document.getElementById(selectId);
            if (!nativeSelect) return;

            const wrap = document.getElementById('wrap_' + selectId);
            if (!wrap) return;

            const trigger = document.getElementById('trigger_' + selectId);
            const label = document.getElementById('label_' + selectId);
            const panel = document.getElementById('panel_' + selectId);
            const searchInput = panel ? panel.querySelector('.custom-select-search-input') : null;
            const optionsContainer = panel ? panel.querySelector('.custom-select-options') : null;
            const emptyNotice = panel ? panel.querySelector('.custom-select-empty') : null;

            function renderOptions(filterText = '') {
                if (!optionsContainer) return;
                const options = Array.from(nativeSelect.options);
                const selectedVal = nativeSelect.value;
                const query = filterText.toLowerCase().trim();

                let visibleCount = 0;
                let html = '';

                options.forEach((opt) => {
                    const val = opt.value;
                    const text = opt.text;
                    if (query && !text.toLowerCase().includes(query)) {
                        return;
                    }

                    visibleCount++;
                    const isSelected = String(val) === String(selectedVal);
                    html += `
                        <li class="custom-select-option ${isSelected ? 'is-selected' : ''}" 
                            data-value="${escapeHtml(val)}" 
                            role="option" 
                            aria-selected="${isSelected ? 'true' : 'false'}">
                            <span class="opt-text">${escapeHtml(text)}</span>
                            <i class="fas fa-check opt-check"></i>
                        </li>
                    `;
                });

                optionsContainer.innerHTML = html;
                if (emptyNotice) {
                    emptyNotice.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            }

            function syncState() {
                const selectedOpt = nativeSelect.options[nativeSelect.selectedIndex];
                if (selectedOpt && selectedOpt.value !== '') {
                    label.textContent = selectedOpt.text;
                    label.title = selectedOpt.text;
                } else if (selectedOpt && selectedOpt.value === '') {
                    label.textContent = selectedOpt.text || placeholder;
                    label.title = '';
                } else {
                    label.textContent = placeholder;
                    label.title = '';
                }

                trigger.disabled = Boolean(nativeSelect.disabled);
                if (nativeSelect.disabled) {
                    wrap.classList.remove('is-open');
                    wrap.closest('.uker-filter-item')?.classList.remove('has-open-dropdown');
                }
                renderOptions(searchInput ? searchInput.value : '');
            }

            function open() {
                if (nativeSelect.disabled) return;
                document.querySelectorAll('.custom-select-wrap.is-open').forEach(el => {
                    if (el !== wrap) {
                        el.classList.remove('is-open');
                        el.closest('.uker-filter-item')?.classList.remove('has-open-dropdown');
                    }
                });
                wrap.classList.add('is-open');
                wrap.closest('.uker-filter-item')?.classList.add('has-open-dropdown');
                trigger.setAttribute('aria-expanded', 'true');
                renderOptions('');
                if (searchInput) {
                    searchInput.value = '';
                    setTimeout(() => searchInput.focus(), 60);
                }
            }

            function close() {
                wrap.classList.remove('is-open');
                wrap.closest('.uker-filter-item')?.classList.remove('has-open-dropdown');
                trigger.setAttribute('aria-expanded', 'false');
            }

            function toggle() {
                if (wrap.classList.contains('is-open')) {
                    close();
                } else {
                    open();
                }
            }

            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggle();
            });

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    renderOptions(searchInput.value);
                });
                searchInput.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        close();
                        trigger.focus();
                    }
                });
            }

            if (optionsContainer) {
                optionsContainer.addEventListener('click', (e) => {
                    const optEl = e.target.closest('.custom-select-option');
                    if (!optEl) return;
                    const val = optEl.getAttribute('data-value');
                    if (nativeSelect.value !== val) {
                        nativeSelect.value = val;
                        syncState();
                        nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    close();
                    trigger.focus();
                });
            }

            customDropdowns[selectId] = { syncState, close, open };
            syncState();
        }

        function syncAllCustomSelects() {
            Object.values(customDropdowns).forEach(dropdown => dropdown.syncState());
        }

        function buildSelects(payload, requestedSelection = {}) {
            const filters = (payload && payload.available_filters) || page.filters || {};
            const selected = payload?.selected || page.selected || {};
            const selectedKanca = requestedSelection.kanca || els.kanca.value || selectedScalar(selected.kanca, '') || selectedKancaValue;
            const selectedUnit = requestedSelection.unit || els.unit.value || selectedScalar(selected.unit_kerja, 'all');
            const selectedDataType = requestedSelection.dataType || els.dataType.value || selected.data_type || 'pinjaman';

            selectedKancaValue = selectedKanca;

            els.kanca.innerHTML = `<option value="">Pilih cabang</option>${optionHtml(withSelectedOption(filters.kanca, selectedKanca), selectedKanca)}`;
            els.unit.innerHTML = optionHtml(filters.unit_kerja || [], selectedUnit);
            els.unit.disabled = els.kanca.value === '';
            els.dataType.innerHTML = optionHtml(page.dataTypes || [], selectedDataType);
            syncPeriodDatePicker(
                filters.posisi_terakhir || [],
                payload?.selected?.posisi_terakhir || els.period.value || selected.posisi_terakhir
            );
            els.rka.innerHTML = optionHtml(filters.posisi_rka || [], els.rka.value || selected.posisi_rka);

            syncAllCustomSelects();
        }

        function formatHeaderDate(periodStr, fallback) {
            if (!periodStr) return fallback || '-';
            try {
                const parts = periodStr.split('-');
                if (parts.length === 3) {
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    const day = parts[2];
                    const monthIdx = parseInt(parts[1], 10) - 1;
                    const year = parts[0].slice(-2);
                    if (monthIdx >= 0 && monthIdx < 12) {
                        return `${day} ${months[monthIdx]} ${year}`;
                    }
                }
                return fallback || '-';
            } catch (e) {
                return fallback || '-';
            }
        }

        const deltaLabelMap = { yoy: 'YoY', ytd: 'YtD', mtm: 'MtM', mtd: 'MtD', h1: 'DtD' };
        function formatDeltaLabel(col) {
            if (col?.delta_label) return col.delta_label;
            const keyLower = String(col?.key || '').toLowerCase();
            return deltaLabelMap[keyLower] || col?.label || col?.key?.toUpperCase() || '-';
        }

        function renderHeader(payload, metricKey) {
            const positions = payload?.columns?.positions || [];
            const deltas = payload?.columns?.deltas || [];
            const currentSort = metricKey ? tableSortState[metricKey] : null;

            function renderSortableTh(sortType, sortKey, labelHtml, extraClass = '') {
                const isSorted = Boolean(currentSort && currentSort.type === sortType && currentSort.key === sortKey);
                const direction = isSorted ? currentSort.direction : null;

                let iconClass = 'fa-sort';
                if (direction === 'desc') {
                    iconClass = 'fa-sort-down';
                } else if (direction === 'asc') {
                    iconClass = 'fa-sort-up';
                }

                const titleText = isSorted
                    ? (direction === 'desc' ? 'Klik untuk urutkan terendah (Ascending)' : 'Klik untuk kembalikan urutan semula')
                    : 'Klik untuk urutkan tertinggi (Descending)';

                return `
                    <th class="uker-th-sortable ${isSorted ? 'is-sorted' : ''} ${extraClass}"
                        data-sort-metric="${escapeHtml(metricKey)}"
                        data-sort-type="${escapeHtml(sortType)}"
                        data-sort-key="${escapeHtml(sortKey)}"
                        title="${titleText}">
                        <div class="th-sort-inner">
                            <span class="th-sort-label">${labelHtml}</span>
                            <span class="th-sort-icon"><i class="fas ${iconClass}"></i></span>
                        </div>
                    </th>
                `;
            }

            const posColsHtml = positions.map(col => {
                const label = escapeHtml(formatHeaderDate(col.period, col.label));
                return renderSortableTh('position', col.key, label);
            }).join('');

            const deltaColsHtml = deltas.map(col => {
                const label = escapeHtml(formatDeltaLabel(col));
                return renderSortableTh('delta', col.key, label);
            }).join('');

            const rkaTh = renderSortableTh('rka', 'rka', 'RKA');
            const achTh = renderSortableTh('achievement', 'achievement', 'Penc. RKA');

            return `
                <tr class="uker-table-header-row-group">
                    <th colspan="2" class="uker-group-unit">Unit Kerja</th>
                    <th colspan="${positions.length || 1}" class="uker-group-posisi">Posisi</th>
                    <th colspan="${deltas.length || 1}" class="uker-group-delta">Delta</th>
                    <th colspan="2" class="uker-group-target">Target</th>
                </tr>
                <tr class="uker-table-header-row-sub">
                    <th class="uker-code">Kode</th>
                    <th class="uker-name">Nama</th>
                    ${posColsHtml}
                    ${deltaColsHtml}
                    ${rkaTh}
                    ${achTh}
                </tr>
            `;
        }

        function getRowSortValue(row, metricKey, sortType, sortKey) {
            const metric = metricForRow(row, metricKey);
            if (!metric) return null;

            if (sortType === 'position') {
                const val = metric.values?.[sortKey];
                return (val !== null && val !== undefined && val !== '') ? Number(val) : null;
            }

            if (sortType === 'delta') {
                const val = metric.deltas?.[sortKey]?.value;
                return (val !== null && val !== undefined && val !== '') ? Number(val) : null;
            }

            if (sortType === 'rka') {
                const val = metric.rka;
                return (val !== null && val !== undefined && val !== '') ? Number(val) : null;
            }

            if (sortType === 'achievement') {
                const val = metric.achievement;
                return (val !== null && val !== undefined && val !== '') ? Number(val) : null;
            }

            return null;
        }

        function compareRows(rowA, rowB, metricKey, sortState) {
            const valA = getRowSortValue(rowA, metricKey, sortState.type, sortState.key);
            const valB = getRowSortValue(rowB, metricKey, sortState.type, sortState.key);

            const isAValid = typeof valA === 'number' && !isNaN(valA);
            const isBValid = typeof valB === 'number' && !isNaN(valB);

            if (!isAValid && !isBValid) return 0;
            if (!isAValid) return 1;
            if (!isBValid) return -1;

            if (sortState.direction === 'asc') {
                return valA - valB;
            } else {
                return valB - valA;
            }
        }

        function getSortSummaryLabel(payload, sortState) {
            if (!sortState || !sortState.direction) return '';
            const dirText = sortState.direction === 'desc' ? 'Tertinggi' : 'Terendah';
            let colName = '';

            if (sortState.type === 'position') {
                const col = (payload?.columns?.positions || []).find(c => c.key === sortState.key);
                colName = `Posisi ${formatHeaderDate(col?.period, col?.label || sortState.key)}`;
            } else if (sortState.type === 'delta') {
                const col = (payload?.columns?.deltas || []).find(c => c.key === sortState.key);
                colName = `Delta ${formatDeltaLabel(col) || sortState.key.toUpperCase()}`;
            } else if (sortState.type === 'rka') {
                colName = 'Target RKA';
            } else if (sortState.type === 'achievement') {
                colName = 'Penc. RKA';
            }

            return `${colName} (${dirText})`;
        }

        function metricCells(metric, positions, deltas) {
            return `
                ${positions.map(col => `<td class="number">${formatPosition(metric.values?.[col.key])}</td>`).join('')}
                ${deltas.map(col => {
                    const delta = metric.deltas?.[col.key] || {};
                    return `<td class="number"><span class="tone-pill tone-${escapeHtml(delta.tone || 'flat')}">${formatDelta(delta.value)}</span></td>`;
                }).join('')}
                <td class="number">${formatPosition(metric.rka)}</td>
                <td class="number"><span class="tone-pill tone-${escapeHtml(metric.achievement_tone || 'flat')}">${formatPct(metric.achievement)}</span></td>
            `;
        }

        function metricForRow(row, metricKey) {
            return (row.metrics || []).find(metric => metric.key === metricKey) || null;
        }

        function formatUkerCode(code) {
            if (!code) return '-';
            const str = String(code).trim();
            if (/^\d+$/.test(str)) {
                const unpadded = str.replace(/^0+/, '');
                return (unpadded || '0').padStart(4, '0');
            }
            return str;
        }

        function rowHtml(row, metricKey, positions, deltas, isTotal = false) {
            const metric = metricForRow(row, metricKey);
            if (!metric) {
                return '';
            }

            const rawCode = row.unit_code || '-';
            const codeText = isTotal ? rawCode : formatUkerCode(rawCode);
            const nameText = row.unit_name || '-';

            return `
                <tr class="${isTotal ? 'total-row' : ''}">
                    <td class="uker-code" title="${escapeHtml(codeText)}">${escapeHtml(codeText)}</td>
                    <td class="uker-name" title="${escapeHtml(nameText)}">${escapeHtml(nameText)}</td>
                    ${metricCells(metric, positions, deltas)}
                </tr>
            `;
        }

        function renderColgroup(positions, deltas) {
            const posCols = (positions || []).map(() => '<col style="width: 110px;">').join('');
            const deltaCols = (deltas || []).map(() => '<col style="width: 105px;">').join('');

            return `
                <colgroup>
                    <col style="width: 85px;">
                    <col style="width: 220px;">
                    ${posCols}
                    ${deltaCols}
                    <col style="width: 100px;">
                    <col style="width: 105px;">
                </colgroup>
            `;
        }

        function tableHtml(payload, metric) {
            const positions = payload?.columns?.positions || [];
            const deltas = payload?.columns?.deltas || [];
            const sortState = tableSortState[metric.key];

            let rows = [...(payload?.rows || [])];
            if (sortState && sortState.direction) {
                rows.sort((a, b) => compareRows(a, b, metric.key, sortState));
            }

            const bodyRows = rows.map(row => rowHtml(row, metric.key, positions, deltas)).join('');
            const totalRow = payload?.totals ? rowHtml(payload.totals, metric.key, positions, deltas, true) : '';

            const sortBadge = (sortState && sortState.direction)
                ? `<span class="uker-sort-badge"><i class="fas ${sortState.direction === 'desc' ? 'fa-arrow-down' : 'fa-arrow-up'}"></i> ${escapeHtml(getSortSummaryLabel(payload, sortState))}</span>`
                : '';

            const resetBtn = (sortState && sortState.direction)
                ? `<button type="button" class="uker-sort-reset-btn" data-reset-metric="${escapeHtml(metric.key)}" title="Kembalikan ke urutan default"><i class="fas fa-undo"></i> Reset Urutan</button>`
                : '';

            return `
                <section class="uker-table-card" data-metric-card="${escapeHtml(metric.key)}">
                    <div class="uker-table-title">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span>${escapeHtml(metric.label || '')}</span>
                            ${sortBadge}
                            ${resetBtn}
                        </div>
                        <span>Rp Juta</span>
                    </div>
                    <div class="uker-table-wrap">
                        <table class="uker-table">
                            ${renderColgroup(positions, deltas)}
                            <thead>${renderHeader(payload, metric.key)}</thead>
                            <tbody>${bodyRows}${totalRow}</tbody>
                        </table>
                    </div>
                </section>
            `;
        }

        function render(payload) {
            currentPayload = payload || null;
            const hasRows = Array.isArray(payload?.rows) && payload.rows.length > 0;
            shell.classList.toggle('is-empty', !hasRows);
            shell.classList.remove('is-loading');
            els.empty.textContent = !(selectedKancaValue || els.kanca.value)
                ? 'Pilih cabang terlebih dahulu untuk menampilkan data.'
                : 'Data tidak tersedia untuk filter ini.';

            els.scope.textContent = payload?.summary?.scope_label || 'Pilih cabang';
            els.unitLabel.textContent = payload?.summary?.unit_label || 'Semua Unit Kerja';
            els.source.textContent = dataTypeLabels[payload?.summary?.data_type] || 'Pinjaman';
            els.periodLabel.textContent = payload?.summary?.period || '-';

            els.tables.innerHTML = hasRows
                ? (payload.metrics || []).map(metric => tableHtml(payload, metric)).join('')
                : '';
        }

        async function fetchData(resetUnit = false) {
            const selection = {
                kanca: els.kanca.value || '',
                unit: resetUnit ? 'all' : (els.unit.value || 'all'),
                dataType: els.dataType.value || 'pinjaman',
            };

            selectedKancaValue = selection.kanca;
            if (!selection.kanca) {
                if (resetUnit) {
                    els.unit.value = 'all';
                }
                els.unit.disabled = true;
                render(null);
                return;
            }

            shell.classList.add('is-loading');
            shell.classList.remove('is-empty');

            if (resetUnit) {
                els.unit.value = 'all';
            }

            const requestId = ++latestRequestId;

            const params = new URLSearchParams({
                kanca: selection.kanca,
                unit_kerja: selection.unit,
                data_type: selection.dataType,
                posisi_terakhir: els.period.value || '',
                posisi_rka: els.rka.value || '',
            });

            try {
                const response = await fetch(`${page.routes.data}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat data.');
                }

                const payload = await response.json();
                if (requestId !== latestRequestId) {
                    return;
                }

                buildSelects(payload, selection);
                render(payload);
            } catch (error) {
                if (requestId !== latestRequestId) {
                    return;
                }

                console.error(error);
                shell.classList.remove('is-loading');
                shell.classList.add('is-empty');
                els.empty.textContent = 'Data gagal dimuat. Silakan coba pilih cabang kembali.';
            }
        }

        function renderCurrentWithScrollPreserved() {
            if (!currentPayload) return;

            const scrollMap = new Map();
            const cards = els.tables.querySelectorAll('.uker-table-card');
            cards.forEach(card => {
                const key = card.getAttribute('data-metric-card');
                const wrap = card.querySelector('.uker-table-wrap');
                if (key && wrap) {
                    scrollMap.set(key, { left: wrap.scrollLeft, top: wrap.scrollTop });
                }
            });

            render(currentPayload);

            const newCards = els.tables.querySelectorAll('.uker-table-card');
            newCards.forEach(card => {
                const key = card.getAttribute('data-metric-card');
                const wrap = card.querySelector('.uker-table-wrap');
                if (key && wrap && scrollMap.has(key)) {
                    const pos = scrollMap.get(key);
                    wrap.scrollLeft = pos.left;
                    wrap.scrollTop = pos.top;
                }
            });
        }

        function handleSortClick(metricKey, sortType, sortKey) {
            const current = tableSortState[metricKey];

            if (current && current.type === sortType && current.key === sortKey) {
                if (current.direction === 'desc') {
                    tableSortState[metricKey] = { type: sortType, key: sortKey, direction: 'asc' };
                } else if (current.direction === 'asc') {
                    delete tableSortState[metricKey];
                }
            } else {
                tableSortState[metricKey] = { type: sortType, key: sortKey, direction: 'desc' };
            }

            renderCurrentWithScrollPreserved();
        }

        function handleResetSort(metricKey) {
            if (tableSortState[metricKey]) {
                delete tableSortState[metricKey];
                renderCurrentWithScrollPreserved();
            }
        }

        function clearAllSorts() {
            for (const k in tableSortState) {
                delete tableSortState[k];
            }
        }

        function bindEvents() {
            els.kanca.addEventListener('change', () => {
                clearAllSorts();
                fetchData(true);
            });
            els.unit.addEventListener('change', () => fetchData(false));
            els.dataType.addEventListener('change', () => {
                clearAllSorts();
                fetchData(false);
            });
            els.period.addEventListener('change', () => fetchData(false));
            els.rka.addEventListener('change', () => fetchData(false));

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.custom-select-wrap')) {
                    document.querySelectorAll('.custom-select-wrap.is-open').forEach(el => {
                        el.classList.remove('is-open');
                        el.closest('.uker-filter-item')?.classList.remove('has-open-dropdown');
                    });
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.custom-select-wrap.is-open').forEach(el => {
                        el.classList.remove('is-open');
                        el.closest('.uker-filter-item')?.classList.remove('has-open-dropdown');
                    });
                }
            });

            els.tables.addEventListener('click', function (e) {
                const sortTh = e.target.closest('.uker-th-sortable');
                if (sortTh) {
                    const metricKey = sortTh.getAttribute('data-sort-metric');
                    const sortType = sortTh.getAttribute('data-sort-type');
                    const sortKey = sortTh.getAttribute('data-sort-key');
                    if (metricKey && sortType && sortKey) {
                        handleSortClick(metricKey, sortType, sortKey);
                    }
                    return;
                }

                const resetBtn = e.target.closest('.uker-sort-reset-btn');
                if (resetBtn) {
                    const metricKey = resetBtn.getAttribute('data-reset-metric');
                    if (metricKey) {
                        handleResetSort(metricKey);
                    }
                    return;
                }
            });
        }

        setupCustomSelect('kancaFilter', 'Pilih cabang', true);
        setupCustomSelect('unitFilter', 'Semua Unit Kerja', true);
        setupCustomSelect('dataTypeFilter', 'Pilih data', false);
        setupCustomSelect('rkaFilter', 'Pilih RKA', false);

        buildSelects(currentPayload);
        bindEvents();

        if (currentPayload) {
            render(currentPayload);
        } else {
            render(null);
        }
    })();
</script>
@endsection
