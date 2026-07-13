@extends('layouts.admin')

@section('title', $pageTitle ?? 'Market Share')

@section('content')
<style>
    :root {
        --market-blue: #0857c3;
        --market-blue-deep: #053b82;
        --market-border: #dbe5ef;
        --market-muted: #64748b;
        --market-surface: #ffffff;
        --market-shell: #f8fafc;
    }

    .market-workbook-page {
        min-height: calc(100vh - 60px);
        padding: 1.25rem;
        background: var(--market-shell);
        color: #0f172a;
    }

    .market-workbook-header,
    .market-workbook-frame-shell {
        border: 1px solid var(--market-border);
        background: var(--market-surface);
        box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.28);
    }

    .market-workbook-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 1rem 1.25rem;
        border-top: 3px solid var(--market-blue);
        border-radius: 14px;
    }

    .market-workbook-brand {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 0.75rem;
    }

    .market-workbook-icon {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--market-blue), #307fe2);
        color: #ffffff;
        box-shadow: 0 10px 20px -16px rgba(8, 87, 195, 0.55);
    }

    .market-workbook-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.15rem;
        font-weight: 850;
        line-height: 1.15;
    }

    .market-workbook-subtitle {
        margin-top: 0.25rem;
        color: var(--market-muted);
        font-size: 0.78rem;
        font-weight: 650;
    }

    .market-workbook-frame-shell {
        height: calc(100vh - 188px);
        min-height: 560px;
        overflow: hidden;
        border-radius: 12px;
    }

    .market-workbook-frame {
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
        background: #ffffff;
    }

    .market-workbook-state {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        padding: 2rem;
        color: var(--market-muted);
        text-align: center;
        font-weight: 700;
    }

    .market-workbook-warning {
        margin-bottom: 1rem;
        padding: 0.85rem 1rem;
        border: 1px solid #fde68a;
        border-radius: 10px;
        background: #fffbeb;
        color: #92400e;
        font-size: 0.84rem;
        font-weight: 700;
    }

    .market-instansi-control {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding: 0.75rem;
        border: 1px solid var(--market-border);
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 14px 28px -26px rgba(15, 23, 42, 0.28);
    }

    .market-instansi-tabs {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .market-instansi-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 0.45rem 0.7rem;
        border: 1px solid #dbe5ef;
        border-radius: 9px;
        background: #f8fafc;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 800;
        text-decoration: none;
    }

    .market-instansi-tab.active {
        border-color: rgba(8, 87, 195, 0.28);
        background: #eff6ff;
        color: var(--market-blue-deep);
    }

    .market-instansi-meta {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: var(--market-muted);
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .market-instansi-table-shell {
        height: calc(100vh - 258px);
        min-height: 560px;
        overflow: auto;
        border: 1px solid #b9d6ff;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.3);
    }

    .market-instansi-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        color: #111827;
        font-size: 0.82rem;
    }

    .market-instansi-table .is-sticky-col {
        position: sticky;
        z-index: 6;
        box-shadow: 10px 0 18px -18px rgba(15, 23, 42, 0.72);
    }

    .market-instansi-table th.is-sticky-col {
        z-index: 8;
    }

    .market-instansi-table thead tr:nth-child(2) th.is-sticky-col {
        z-index: 9;
    }

    .market-instansi-table tbody td.is-sticky-col {
        background: #ffffff;
    }

    .market-instansi-table tbody tr:nth-child(even) td.is-sticky-col {
        background: #f6faff;
    }

    .market-instansi-table tbody tr:hover td.is-sticky-col {
        background: #eaf3ff;
    }

    .market-instansi-table th {
        position: sticky;
        top: 0;
        z-index: 3;
        min-width: 118px;
        padding: 0.7rem 0.65rem;
        border-right: 1px solid rgba(255, 255, 255, 0.18);
        border-bottom: 1px solid #064aa8;
        background: linear-gradient(180deg, #0a67d8 0%, #0757b7 48%, #04458f 100%);
        color: #ffffff;
        font-weight: 850;
        line-height: 1.2;
        text-align: left;
        white-space: normal;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
        cursor: context-menu;
    }

    .market-instansi-table thead tr:nth-child(2) th {
        top: 42px;
        z-index: 4;
        padding-top: 0.45rem;
        padding-bottom: 0.45rem;
        background: linear-gradient(180deg, #0d73e9 0%, #075fca 100%);
        text-align: center;
    }

    .market-instansi-table th[data-rowspan="2"] {
        z-index: 5;
        vertical-align: middle;
    }

    .market-instansi-table td {
        min-width: 118px;
        padding: 0.5rem 0.65rem;
        border-right: 1px solid #d7e4f6;
        border-bottom: 1px solid #d7e4f6;
        background: #ffffff;
        vertical-align: top;
        white-space: normal;
    }

    .market-instansi-table tbody tr:nth-child(even) td {
        background: #f6faff;
    }

    .market-instansi-table tbody tr:hover td {
        background: #eaf3ff;
    }

    .market-instansi-table .is-numeric {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .market-instansi-table tbody td.market-instansi-tone-good {
        background: #dcfce7 !important;
        color: #166534;
        font-weight: 850;
    }

    .market-instansi-table tbody td.market-instansi-tone-flat {
        background: #fef3c7 !important;
        color: #92400e;
        font-weight: 850;
    }

    .market-instansi-table tbody td.market-instansi-tone-bad {
        background: #fee2e2 !important;
        color: #991b1b;
        font-weight: 850;
    }

    .market-instansi-state {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 360px;
        padding: 2rem;
        color: #48617f;
        font-weight: 800;
        text-align: center;
    }

    .market-instansi-menu {
        position: fixed;
        z-index: 2147483000;
        display: none;
        min-width: 220px;
        max-width: min(320px, calc(100vw - 24px));
        padding: 0.35rem;
        border: 1px solid #bdd4f5;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 24px 52px -28px rgba(15, 23, 42, 0.52);
    }

    .market-instansi-menu.show {
        display: block;
    }

    .market-instansi-menu button {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
        padding: 0.55rem 0.65rem;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #1e293b;
        font-size: 0.8rem;
        font-weight: 800;
        text-align: left;
    }

    .market-instansi-menu button:hover {
        background: #eff6ff;
        color: #064aa8;
    }

    .market-instansi-menu-divider {
        height: 1px;
        margin: 0.3rem 0.15rem;
        background: #dbeafe;
    }

    .market-instansi-hidden-list {
        max-height: 220px;
        overflow: auto;
    }

    .market-workbook-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.55rem;
    }

    .market-workbook-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 40px;
        padding: 0.5rem 0.9rem;
        border-radius: 10px;
        border: 1px solid rgba(8, 87, 195, 0.2);
        background: #eff6ff;
        color: var(--market-blue-deep);
        font-size: 0.78rem;
        font-weight: 800;
        text-decoration: none;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .market-workbook-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 24px -20px rgba(8, 87, 195, 0.45);
        background: #dbeafe;
        color: var(--market-blue-deep);
        text-decoration: none;
    }

    .market-native-shell {
        display: grid;
        gap: 1rem;
    }

    .market-native-switch {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        gap: 0.35rem;
        padding: 0.35rem;
        border: 1px solid #d6e3f3;
        border-radius: 999px;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        box-shadow: 0 14px 26px -24px rgba(15, 23, 42, 0.3);
    }

    .market-native-switch-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 36px;
        padding: 0.45rem 0.95rem;
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 900;
        cursor: pointer;
        transition: color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .market-native-switch-button:hover {
        color: var(--market-blue-deep);
        transform: translateY(-1px);
    }

    .market-native-switch-button.active {
        background: linear-gradient(135deg, var(--market-blue), #2d7ddd);
        color: #ffffff;
        box-shadow: 0 12px 22px -18px rgba(8, 87, 195, 0.75);
    }

    .market-native-mode {
        display: none;
    }

    .market-native-mode.active {
        display: grid;
        gap: 1rem;
    }

    .market-native-hero {
        display: grid;
        grid-template-columns: minmax(260px, 1.05fr) minmax(240px, 0.95fr);
        gap: 1rem;
    }

    .market-native-card,
    .market-native-panel {
        border: 1px solid var(--market-border);
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.28);
        overflow: hidden;
    }

    .market-native-card {
        padding: 1.15rem;
        border-top: 4px solid var(--market-blue);
    }

    .market-native-kicker {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        color: var(--market-muted);
        font-size: 0.72rem;
        font-weight: 850;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .market-native-period {
        padding: 0.26rem 0.55rem;
        border-radius: 999px;
        background: #eff6ff;
        color: var(--market-blue-deep);
        letter-spacing: 0;
        text-transform: none;
    }

    .market-native-main-value {
        margin-top: 0.9rem;
        color: #0f172a;
        font-size: 2.15rem;
        font-weight: 900;
        line-height: 1;
    }

    .market-native-subgrid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .market-native-stat {
        border-radius: 12px;
        border: 1px solid #e5edf7;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        padding: 0.75rem;
    }

    .market-native-stat-label {
        color: var(--market-muted);
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .market-native-stat-value {
        margin-top: 0.35rem;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 900;
    }

    .market-native-positive {
        color: #059669 !important;
    }

    .market-native-negative {
        color: #dc2626 !important;
    }

    .market-native-composition {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .market-component-card {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: 0.75rem;
        align-items: center;
        padding: 0.85rem;
        border: 1px solid #e5edf7;
        border-radius: 13px;
        background: #ffffff;
    }

    .market-component-icon {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: linear-gradient(135deg, #0ea5e9, #0857c3);
        color: #ffffff;
    }

    .market-component-title {
        color: #0f172a;
        font-size: 0.82rem;
        font-weight: 900;
    }

    .market-component-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.35rem;
        color: var(--market-muted);
        font-size: 0.72rem;
        font-weight: 750;
    }

    .market-component-bar {
        grid-column: 1 / -1;
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e5edf7;
    }

    .market-component-bar > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--market-blue), #22c55e);
    }

    .market-native-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 1rem;
        border-bottom: 1px solid var(--market-border);
        background: linear-gradient(135deg, var(--market-blue), #2d7ddd);
        color: #ffffff;
    }

    .market-native-panel-title {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 900;
    }

    .market-native-table-wrap {
        overflow-x: auto;
    }

    .market-native-table {
        width: 100%;
        min-width: 880px;
        border-collapse: collapse;
        font-size: 0.78rem;
    }

    .market-native-table th,
    .market-native-table td {
        padding: 0.72rem 0.8rem;
        border-bottom: 1px solid #e5edf7;
        text-align: right;
        white-space: nowrap;
    }

    .market-native-table th {
        background: #f8fbff;
        color: #475569;
        font-size: 0.68rem;
        font-weight: 900;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .market-native-table td:first-child,
    .market-native-table th:first-child {
        text-align: left;
    }

    .market-native-table tbody tr:hover td {
        background: #f4f8ff;
    }

    .market-mapping-workspace {
        display: grid;
        gap: 1rem;
    }

    .market-mapping-mode {
        display: none;
    }

    .market-mapping-mode.active {
        display: block;
    }

    .market-mapping-summary {
        display: grid;
        gap: 1rem;
    }

    .market-mapping-summary-hero {
        display: grid;
        grid-template-columns: minmax(280px, 0.88fr) minmax(320px, 1.12fr);
        gap: 1rem;
    }

    .market-mapping-summary-card,
    .market-mapping-summary-panel {
        border: 1px solid var(--market-border);
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.28);
        overflow: hidden;
    }

    .market-mapping-summary-card {
        position: relative;
        min-height: 260px;
        padding: 1.25rem;
        color: #ffffff;
        background:
            linear-gradient(135deg, rgba(3, 55, 123, 0.95), rgba(8, 87, 195, 0.86)),
            radial-gradient(circle at 90% 15%, rgba(255, 170, 0, 0.28), transparent 34%),
            #0857c3;
    }

    .market-mapping-summary-card::after {
        content: "";
        position: absolute;
        right: -72px;
        bottom: -96px;
        width: 220px;
        height: 220px;
        border: 34px solid rgba(255, 255, 255, 0.12);
        border-radius: 999px;
        pointer-events: none;
    }

    .market-mapping-summary-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.34rem 0.62rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        font-size: 0.7rem;
        font-weight: 900;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .market-mapping-summary-title {
        position: relative;
        z-index: 1;
        max-width: 560px;
        margin: 1.1rem 0 0;
        font-size: 1.85rem;
        font-weight: 950;
        line-height: 1.08;
    }

    .market-mapping-summary-subtitle {
        position: relative;
        z-index: 1;
        max-width: 640px;
        margin-top: 0.7rem;
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.86rem;
        font-weight: 650;
        line-height: 1.45;
    }

    .market-mapping-summary-selected {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        margin-top: 1.1rem;
        padding: 0.58rem 0.75rem;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.16);
        font-size: 0.78rem;
        font-weight: 900;
    }

    .market-mapping-sector-control {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 0.4rem;
        max-width: 360px;
        margin-top: 1rem;
    }

    .market-mapping-sector-control label {
        margin: 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.68rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .market-mapping-sector-control select {
        width: 100%;
        min-height: 42px;
        border: 1px solid rgba(255, 255, 255, 0.45);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.94);
        color: #0f172a;
        font-size: 0.88rem;
        font-weight: 900;
        padding: 0 0.8rem;
        outline: none;
    }

    .market-mapping-total-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .market-mapping-total-card {
        min-height: 120px;
        padding: 1rem;
        border: 1px solid #dbeafe;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .market-mapping-total-top {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .market-mapping-total-icon {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        background: #eff6ff;
        color: var(--market-blue);
    }

    .market-mapping-total-value {
        margin-top: 0.8rem;
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 950;
        line-height: 1.05;
    }

    .market-mapping-summary-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.95rem 1.05rem;
        border-bottom: 1px solid var(--market-border);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .market-mapping-summary-panel-title {
        margin: 0;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 950;
    }

    .market-mapping-kpi-grid,
    .market-mapping-sector-grid {
        display: grid;
        gap: 0.75rem;
        padding: 1rem;
    }

    .market-mapping-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .market-mapping-kpi-card {
        padding: 0.9rem;
        border: 1px solid #e5edf7;
        border-radius: 13px;
        background: #ffffff;
    }

    .market-mapping-kpi-label {
        display: flex;
        align-items: center;
        gap: 0.48rem;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .market-mapping-kpi-label i {
        color: var(--market-blue);
    }

    .market-mapping-kpi-value {
        margin-top: 0.55rem;
        color: #0f172a;
        font-size: 1.08rem;
        font-weight: 950;
    }

    .market-mapping-sector-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .market-mapping-sector-card {
        display: grid;
        grid-template-columns: 46px minmax(0, 1fr);
        gap: 0.75rem;
        align-items: center;
        min-height: 96px;
        padding: 0.85rem;
        border: 1px solid #e5edf7;
        border-radius: 14px;
        background: #ffffff;
        color: inherit;
        cursor: pointer;
        font: inherit;
        text-align: left;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .market-mapping-sector-card:hover,
    .market-mapping-sector-card.is-active {
        border-color: #7db7ff;
        box-shadow: 0 18px 32px -26px rgba(8, 87, 195, 0.42);
        transform: translateY(-1px);
    }

    .market-mapping-sector-icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        color: #ffffff;
        box-shadow: 0 12px 20px -18px rgba(15, 23, 42, 0.45);
    }

    .market-mapping-sector-card.tone-emerald .market-mapping-sector-icon { background: linear-gradient(135deg, #10b981, #047857); }
    .market-mapping-sector-card.tone-blue .market-mapping-sector-icon { background: linear-gradient(135deg, #38bdf8, #0857c3); }
    .market-mapping-sector-card.tone-green .market-mapping-sector-icon { background: linear-gradient(135deg, #84cc16, #15803d); }
    .market-mapping-sector-card.tone-cyan .market-mapping-sector-icon { background: linear-gradient(135deg, #22d3ee, #0e7490); }
    .market-mapping-sector-card.tone-orange .market-mapping-sector-icon { background: linear-gradient(135deg, #fb923c, #c2410c); }
    .market-mapping-sector-card.tone-violet .market-mapping-sector-icon { background: linear-gradient(135deg, #a78bfa, #6d28d9); }
    .market-mapping-sector-card.tone-slate .market-mapping-sector-icon { background: linear-gradient(135deg, #64748b, #1e293b); }
    .market-mapping-sector-card.tone-amber .market-mapping-sector-icon { background: linear-gradient(135deg, #fbbf24, #b45309); }
    .market-mapping-sector-card.tone-gray .market-mapping-sector-icon { background: linear-gradient(135deg, #94a3b8, #475569); }

    .market-mapping-sector-label {
        color: #0f172a;
        font-size: 0.84rem;
        font-weight: 950;
    }

    .market-mapping-sector-meta {
        margin-top: 0.4rem;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 800;
    }

    .market-mapping-sector-value {
        margin-top: 0.15rem;
        color: var(--market-blue-deep);
        font-size: 1.08rem;
        font-weight: 950;
    }

    .market-mapping-chart-grid {
        display: grid;
        grid-template-columns: minmax(360px, 1.35fr) minmax(260px, 0.85fr) minmax(320px, 1fr);
        gap: 1rem;
        padding: 1rem;
    }

    .market-chart-card {
        min-width: 0;
        padding: 1rem;
        border: 1px solid #e5edf7;
        border-radius: 14px;
        background: #ffffff;
    }

    .market-chart-title {
        margin: 0 0 0.85rem;
        color: #334155;
        font-size: 0.9rem;
        font-weight: 900;
        text-align: center;
    }

    .market-chart-column-plot {
        display: grid;
        grid-template-columns: repeat(var(--chart-count), minmax(40px, 1fr));
        align-items: end;
        gap: 0.5rem;
        min-height: 230px;
        padding: 0.5rem 0.4rem 0;
        border-bottom: 1px solid #d7dee8;
        background: repeating-linear-gradient(to top, transparent 0, transparent 39px, #e5edf7 40px);
    }

    .market-chart-column-item {
        display: grid;
        align-items: end;
        gap: 0.35rem;
        min-width: 0;
        height: 100%;
    }

    .market-chart-column-bars {
        display: flex;
        align-items: end;
        justify-content: center;
        gap: 0.22rem;
        height: 176px;
    }

    .market-chart-column-bar {
        width: 13px;
        min-height: 3px;
        border-radius: 4px 4px 0 0;
        background: var(--chart-color);
        box-shadow: 0 8px 14px -12px rgba(15, 23, 42, 0.5);
    }

    .market-chart-axis-label {
        min-height: 34px;
        color: #64748b;
        font-size: 0.58rem;
        font-weight: 800;
        line-height: 1.15;
        text-align: center;
        text-transform: uppercase;
        word-break: break-word;
    }

    .market-chart-legend {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.45rem 0.8rem;
        margin-top: 0.8rem;
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 800;
    }

    .market-chart-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .market-chart-swatch {
        width: 9px;
        height: 9px;
        border-radius: 2px;
        background: var(--chart-color);
    }

    .market-chart-doughnut-layout {
        display: grid;
        grid-template-columns: minmax(150px, 0.9fr) minmax(120px, 1fr);
        gap: 0.8rem;
        align-items: center;
        min-height: 230px;
    }

    .market-chart-doughnut {
        width: min(170px, 100%);
        aspect-ratio: 1;
        margin: 0 auto;
        border-radius: 999px;
        background: var(--chart-conic);
        box-shadow: inset 0 0 0 34px #ffffff, 0 16px 24px -22px rgba(15, 23, 42, 0.5);
    }

    .market-chart-doughnut-legend {
        display: grid;
        gap: 0.38rem;
        color: #64748b;
        font-size: 0.65rem;
        font-weight: 800;
    }

    .market-chart-horizontal-list {
        display: grid;
        gap: 0.42rem;
        min-height: 230px;
        padding-top: 0.15rem;
    }

    .market-chart-horizontal-row {
        display: grid;
        grid-template-columns: minmax(88px, 0.8fr) minmax(0, 1.35fr);
        align-items: center;
        gap: 0.5rem;
    }

    .market-chart-horizontal-label {
        color: #64748b;
        font-size: 0.62rem;
        font-weight: 850;
        text-align: right;
        text-transform: uppercase;
    }

    .market-chart-horizontal-bars {
        display: grid;
        gap: 0.18rem;
    }

    .market-chart-horizontal-bar {
        height: 7px;
        min-width: 3px;
        border-radius: 999px;
        background: var(--chart-color);
    }

    .market-excel-shell {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        height: calc(100vh - 188px);
        min-height: 560px;
        overflow: hidden;
        border: 1px solid var(--market-border);
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.28);
    }

    .market-excel-shell .market-workbook-frame {
        min-height: 0;
    }

    .market-excel-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #d8e4f2;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .market-excel-heading {
        min-width: 0;
    }

    .market-excel-title {
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 900;
        line-height: 1.2;
    }

    .market-excel-meta {
        margin-top: 0.2rem;
        color: var(--market-muted);
        font-size: 0.72rem;
        font-weight: 750;
    }

    .market-native-workbook-render {
        display: grid;
        grid-template-rows: auto auto minmax(0, 1fr);
        min-height: 0;
        overflow: hidden;
        background: #eef2f7;
    }

    .market-native-workbook-tools {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        padding: 0.6rem 0.8rem;
        border-bottom: 1px solid #d8e4f2;
        background: #f8fbff;
    }

    .market-sheet-tabs {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .market-sheet-tab {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 0.42rem 0.78rem;
        border: 1px solid #cbd8e8;
        border-radius: 10px;
        background: #ffffff;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 900;
        white-space: nowrap;
        text-decoration: none;
    }

    .market-sheet-tab.active {
        border-color: var(--market-blue);
        background: linear-gradient(135deg, var(--market-blue), #2d7ddd);
        color: #ffffff;
        box-shadow: 0 12px 20px -18px rgba(8, 87, 195, 0.65);
    }

    .market-sheet-tab:hover {
        color: var(--market-blue-deep);
        text-decoration: none;
    }

    .market-sheet-tab.active:hover {
        color: #ffffff;
    }

    .market-native-filter-wrap {
        position: relative;
        width: min(340px, 100%);
        flex: 0 0 min(340px, 38vw);
    }

    .market-native-filter-wrap i {
        position: absolute;
        top: 50%;
        left: 0.78rem;
        transform: translateY(-50%);
        color: #64748b;
        font-size: 0.78rem;
    }

    .market-native-filter-input {
        width: 100%;
        min-height: 38px;
        padding: 0.45rem 0.8rem 0.45rem 2.15rem;
        border: 1px solid #c8d7ea;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font-size: 0.8rem;
        font-weight: 800;
        outline: none;
    }

    .market-native-filter-input:focus {
        border-color: var(--market-blue);
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.13);
    }

    .market-native-render-note {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.45rem;
        padding: 0.55rem 0.8rem;
        border-bottom: 1px solid #d8e4f2;
        background: #ffffff;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .market-native-render-note strong,
    .market-native-render-note span:first-child {
        color: #0f172a;
    }

    .market-native-render-note .is-warning {
        color: #92400e;
    }

    .market-office-frame-stage {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        min-height: 0;
        overflow: hidden;
    }

    .market-office-sheet-tabs {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        overflow-x: auto;
        padding: 0.58rem 0.8rem;
        border-bottom: 1px solid #d8e4f2;
        background: #eef5ff;
        scrollbar-width: thin;
    }

    .market-office-sheet-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0.42rem 0.78rem;
        border: 1px solid #cbd8e8;
        border-radius: 10px;
        background: #ffffff;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 900;
        white-space: nowrap;
        cursor: pointer;
        transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    }

    .market-office-sheet-tab.active {
        border-color: var(--market-blue);
        background: linear-gradient(135deg, var(--market-blue), #2d7ddd);
        color: #ffffff;
        box-shadow: 0 12px 20px -18px rgba(8, 87, 195, 0.65);
    }

    .market-office-sheet-tab:hover {
        border-color: var(--market-blue);
        color: var(--market-blue-deep);
    }

    .market-office-sheet-tab.active:hover {
        color: #ffffff;
    }

    .market-excel-filter {
        position: relative;
        width: min(340px, 100%);
        flex: 0 1 340px;
    }

    .market-excel-filter i {
        position: absolute;
        top: 50%;
        left: 0.8rem;
        transform: translateY(-50%);
        color: #64748b;
        font-size: 0.78rem;
    }

    .market-excel-filter-input {
        width: 100%;
        min-height: 38px;
        padding: 0.45rem 0.8rem 0.45rem 2.2rem;
        border: 1px solid #c8d7ea;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font-size: 0.8rem;
        font-weight: 750;
        outline: none;
    }

    .market-excel-filter-input:focus {
        border-color: var(--market-blue);
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.13);
    }

    .market-excel-tabs {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        overflow-x: auto;
        padding: 0.55rem 0.85rem;
        border-bottom: 1px solid #d8e4f2;
        background: #f1f5f9;
        scrollbar-width: thin;
    }

    .market-excel-tab {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 0.4rem 0.85rem;
        border: 1px solid #cbd8e8;
        border-radius: 8px 8px 0 0;
        background: #ffffff;
        color: #475569;
        font-size: 0.76rem;
        font-weight: 850;
        white-space: nowrap;
        text-decoration: none;
    }

    .market-excel-tab.active {
        border-color: var(--market-blue);
        background: linear-gradient(135deg, var(--market-blue), #2d7ddd);
        color: #ffffff;
        box-shadow: 0 12px 20px -18px rgba(8, 87, 195, 0.65);
    }

    .market-excel-tab:hover {
        color: var(--market-blue-deep);
        text-decoration: none;
    }

    .market-excel-tab.active:hover {
        color: #ffffff;
    }

    .market-excel-table-shell {
        min-height: 0;
        overflow: auto;
        background: #eef2f7;
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #e2e8f0;
    }

    .market-excel-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #ffffff;
        color: #0f172a;
        font-size: 0.78rem;
    }

    .market-excel-table th,
    .market-excel-table td {
        min-width: 92px;
        max-width: 260px;
        height: 28px;
        padding: 0.25rem 0.45rem;
        border-right: 1px solid #d7dee8;
        border-bottom: 1px solid #d7dee8;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .market-excel-table col {
        width: 96px;
        min-width: 96px;
    }

    .market-excel-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #e8eff8;
        color: #334155;
        font-size: 0.7rem;
        font-weight: 900;
        text-align: center;
    }

    .market-excel-row-number,
    .market-excel-corner {
        position: sticky;
        left: 0;
        z-index: 2;
        min-width: 46px !important;
        width: 46px;
        max-width: 46px !important;
        background: #e8eff8;
        color: #475569;
        font-size: 0.7rem;
        font-weight: 850;
        text-align: right;
    }

    .market-excel-corner {
        top: 0;
        z-index: 4 !important;
    }

    .market-excel-table td {
        background: #ffffff;
    }

    .market-excel-table td.is-empty {
        color: inherit;
    }

    .market-excel-table tbody tr:hover td,
    .market-excel-table tbody tr:hover .market-excel-row-number {
        background: #eaf4ff;
    }

    .market-excel-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 240px;
        color: var(--market-muted);
        font-size: 0.85rem;
        font-weight: 800;
    }

    .market-excel-note {
        padding: 0.6rem 1rem;
        border-top: 1px solid #d8e4f2;
        background: #fffbeb;
        color: #92400e;
        font-size: 0.76rem;
        font-weight: 800;
    }

    @media (max-width: 1024px) {
        .market-workbook-page {
            padding: 1rem;
        }

        .market-workbook-frame-shell,
        .market-excel-shell {
            height: 760px;
            height: max(760px, calc(100svh - 132px));
            min-height: 760px;
        }

        @supports (height: 100dvh) {
            .market-workbook-frame-shell,
            .market-excel-shell {
                height: max(760px, calc(100dvh - 132px));
            }
        }

        .market-excel-table-shell {
            min-height: 520px;
        }
    }

    @media (max-width: 768px) {
        .market-workbook-page {
            padding: 0.85rem;
        }

        .market-workbook-frame-shell {
            height: 700px;
            height: max(700px, calc(100svh - 116px));
            min-height: 700px;
        }

        @supports (height: 100dvh) {
            .market-workbook-frame-shell {
                height: max(700px, calc(100dvh - 116px));
            }
        }

        .market-native-hero,
        .market-native-composition,
        .market-native-subgrid,
        .market-mapping-summary-hero,
        .market-mapping-total-grid,
        .market-mapping-kpi-grid,
        .market-mapping-sector-grid,
        .market-mapping-chart-grid,
        .market-chart-doughnut-layout {
            grid-template-columns: 1fr;
        }

        .market-excel-shell {
            height: 700px;
            height: max(700px, calc(100svh - 116px));
            min-height: 700px;
        }

        @supports (height: 100dvh) {
            .market-excel-shell {
                height: max(700px, calc(100dvh - 116px));
            }
        }

        .market-excel-toolbar {
            align-items: stretch;
            flex-direction: column;
            gap: 0.55rem;
            padding: 0.7rem 0.8rem;
        }

        .market-excel-meta {
            display: none;
        }

        .market-native-workbook-tools {
            align-items: stretch;
            flex-direction: column;
            gap: 0.55rem;
            padding: 0.65rem 0.75rem;
        }

        .market-excel-table-shell {
            min-height: 500px;
        }

        .market-native-filter-wrap {
            width: 100%;
            flex-basis: auto;
        }

        .market-excel-filter {
            width: 100%;
            flex-basis: auto;
        }

        .market-instansi-control {
            align-items: stretch;
            flex-direction: column;
        }

        .market-instansi-meta {
            white-space: normal;
        }

        .market-instansi-table-shell {
            height: max(620px, calc(100svh - 210px));
            min-height: 620px;
        }
    }
</style>

<div class="market-workbook-page">
    @php
        $workbookProviderLabel = (string) ($workbookProvider ?? ((string) ($pageTitle ?? '') === 'Mapping' ? 'Google Sheets' : 'Office 365'));
        $downloadActionLabel = $workbookProviderLabel === 'Google Sheets'
            ? 'Buka Google Sheet'
            : 'Unduh Workbook (Bypass Office 365)';
        $displayBlockedMessage = $workbookProviderLabel === 'Google Sheets'
            ? 'Workbook Google Sheets belum bisa dimuat langsung di browser. Buka link Google Sheet untuk melihat data sumber.'
            : 'Workbook ini memerlukan autentikasi Office 365 atau berukuran terlalu besar untuk dimuat secara langsung di browser.';
    @endphp
    <div class="market-workbook-header">
        <div class="market-workbook-brand">
            <div class="market-workbook-icon">
                <i class="{{ $pageIcon ?? 'fas fa-chart-pie' }}"></i>
            </div>
            <div>
                <h1 class="market-workbook-title">{{ $pageTitle ?? 'Market Share' }}</h1>
                <div class="market-workbook-subtitle">{{ $workbookTitle }}</div>
            </div>
        </div>
        @if(!empty($downloadUrl) && !empty($showDownloadAction ?? true) && empty($showDownloadPanel))
            <div class="market-workbook-actions">
                <a href="{{ $downloadUrl }}" class="market-workbook-button" download>
                    <i class="fas fa-download"></i>
                    {{ $downloadActionLabel }}
                </a>
            </div>
        @endif
    </div>

    @if(!$workbookUrlIsComplete)
        <div class="market-workbook-warning">
            {{ $warningMessage ?? 'Link workbook belum terlihat lengkap. Isi link SharePoint penuh agar workbook bisa tampil langsung.' }}
        </div>
    @endif

    @if(!empty($instansiBranchOptions))
        <div class="market-instansi-control">
            <div class="market-instansi-tabs" aria-label="Pilih cabang Marketshare Instansi">
                @foreach($instansiBranchOptions as $branchKey => $branchOption)
                    <a
                        href="{{ route('report.dashboard-dana.market-share.instansi', ['cabang' => $branchKey]) }}"
                        class="market-instansi-tab {{ (string) $branchKey === (string) ($selectedInstansiBranch ?? '') ? 'active' : '' }}"
                    >
                        {{ $branchOption['label'] ?? $branchKey }}
                    </a>
                @endforeach
            </div>
            <div class="market-instansi-meta">
                <i class="fas fa-table"></i>
                <span>{{ $selectedInstansiBranchLabel ?? '-' }} · {{ $selectedInstansiSheetName ?? 'DATA INSTANSI' }}</span>
                @if(!empty($selectedInstansiSourceUrl))
                    <a href="{{ $selectedInstansiSourceUrl }}" target="_blank" rel="noopener" class="market-workbook-button">
                        <i class="fas fa-external-link-alt"></i>
                        Buka Sheet
                    </a>
                @endif
            </div>
        </div>
    @endif

    @php
        $nativeWorkbook = $nativeWorkbook ?? ['ready' => false];
    @endphp

    @if(!empty($instansiNativeTable))
        <div
            class="market-instansi-table-shell"
            data-market-instansi-table-shell
            data-source-url="{{ $instansiDataUrl ?? '' }}"
            data-storage-key="marketshare-instansi-hidden-columns-v2-{{ $selectedInstansiBranch ?? 'default' }}"
        >
            <div class="market-instansi-state" data-market-instansi-state>
                Memuat data DATA INSTANSI...
            </div>
            <table class="market-instansi-table d-none" data-market-instansi-table>
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="market-instansi-menu" data-market-instansi-menu>
            <button type="button" data-market-hide-column>
                <span>Hide kolom ini</span>
                <i class="fas fa-eye-slash"></i>
            </button>
            <div class="market-instansi-menu-divider"></div>
            <div class="market-instansi-hidden-list" data-market-hidden-columns>
                <button type="button" disabled>
                    <span>Tidak ada kolom tersembunyi</span>
                </button>
            </div>
        </div>
    @elseif(!empty($nativeWorkbook['ready']))
        @php
            $mappingSummary = $nativeWorkbook['summary'] ?? ['ready' => false];
            $hasMappingSummary = !empty($mappingSummary['ready']);
            $defaultWorkbookMode = $hasMappingSummary ? 'summary' : 'excel';
            $excelWorkbookUrl = $excelWorkbookUrl ?? $workbookUrl ?? '';
            $workbookProvider = $workbookProvider ?? ((string) ($pageTitle ?? '') === 'Mapping' ? 'Google Sheets' : 'Excel 365');
            $excelModeTitle = $workbookProvider === 'Google Sheets' ? 'Google Spreadsheet' : 'Excel Workbook';
            $mappingCharts = $mappingSummary['charts'] ?? [];
            $mappingSectors = $mappingSummary['sectors'] ?? [];
            $mappingSectorDetails = $mappingSummary['sectorDetails'] ?? [];
            $mappingSelectedSector = (string) ($mappingSummary['selectedSector'] ?? ($mappingSectors[0]['label'] ?? '-'));
            $chartPalette = ['#4472C4', '#ED7D31', '#A5A5A5', '#FFC000', '#5B9BD5', '#70AD47', '#264478', '#9E480E', '#636363'];
            $chartValueLabel = function (float|int|null $value, string $title = ''): string {
                $value = (float) ($value ?? 0);
                $upperTitle = strtoupper($title);
                if ((str_contains($upperTitle, 'RATIO') || str_contains($upperTitle, 'SHARE')) && abs($value) <= 1) {
                    return number_format($value * 100, 1, ',', '.') . '%';
                }

                if (abs($value) >= 1000000) {
                    return number_format($value / 1000000, 1, ',', '.') . 'M';
                }

                return number_format($value, 0, ',', '.');
            };
        @endphp

        <div class="market-mapping-workspace">
            @if($hasMappingSummary)
                <div class="market-native-switch" role="tablist" aria-label="Pilih tampilan workbook mapping">
                    <button
                        type="button"
                        class="market-native-switch-button {{ $defaultWorkbookMode === 'summary' ? 'active' : '' }}"
                        data-market-workbook-mode-trigger="summary"
                        role="tab"
                        aria-selected="{{ $defaultWorkbookMode === 'summary' ? 'true' : 'false' }}"
                    >
                        <i class="fas fa-layer-group"></i>
                        Summary
                    </button>
                    <button
                        type="button"
                        class="market-native-switch-button {{ $defaultWorkbookMode === 'excel' ? 'active' : '' }}"
                        data-market-workbook-mode-trigger="excel"
                        role="tab"
                        aria-selected="{{ $defaultWorkbookMode === 'excel' ? 'true' : 'false' }}"
                    >
                        <i class="fas fa-file-excel"></i>
                        {{ $workbookProvider === 'Google Sheets' ? 'Google Sheet' : 'Excel' }}
                    </button>
                </div>
            @endif

            <div class="market-mapping-mode {{ $defaultWorkbookMode === 'excel' ? 'active' : '' }}" data-market-workbook-mode-panel="excel">
                <div class="market-excel-shell">
                    <div class="market-excel-toolbar">
                        <div class="market-excel-heading">
                            <div class="market-excel-title">{{ $excelModeTitle }}</div>
                            <div class="market-excel-meta">
                                @if(!empty($showDownloadPanel))
                                    Mode native dashboard; sheet dan filter tetap bisa digunakan tanpa membuka {{ $workbookProvider }}.
                                @else
                                    Gunakan dropdown, filter, dan sheet asli dari {{ $workbookProvider }}.
                                @endif
                            </div>
                        </div>
                        @if(!empty($downloadUrl) && !empty($showDownloadAction ?? true) && empty($showDownloadPanel))
                            <a href="{{ $downloadUrl }}" class="market-workbook-button" download>
                                <i class="fas fa-download"></i>
                                Unduh Workbook
                            </a>
                        @endif
                    </div>

                    @if(!empty($showDownloadPanel))
                        @php
                            $nativeSheetNames = $nativeWorkbook['sheetNames'] ?? [];
                            $nativeSelectedSheet = (string) ($nativeWorkbook['selectedSheet'] ?? ($nativeSheetNames[0] ?? 'Workbook'));
                            $nativeColumnLabels = $nativeWorkbook['columnLabels'] ?? [];
                            $nativeColumnWidths = $nativeWorkbook['columnWidths'] ?? [];
                            $nativeRows = $nativeWorkbook['rows'] ?? [];
                            $nativeRowCount = (int) ($nativeWorkbook['rowCount'] ?? count($nativeRows));
                            $nativeColumnCount = (int) ($nativeWorkbook['columnCount'] ?? count($nativeColumnLabels));
                            $nativeShownRows = count($nativeRows);
                            $nativeSheetUrl = function (string $sheetName): string {
                                return request()->fullUrlWithQuery(['sheet' => $sheetName]);
                            };
                        @endphp
                        <div class="market-native-workbook-render">
                            <div class="market-native-workbook-tools">
                                <div class="market-sheet-tabs" aria-label="Sheet workbook mapping">
                                    @foreach($nativeSheetNames as $sheetName)
                                        <a
                                            href="{{ $nativeSheetUrl((string) $sheetName) }}"
                                            class="market-sheet-tab {{ (string) $sheetName === $nativeSelectedSheet ? 'active' : '' }}"
                                        >
                                            {{ $sheetName }}
                                        </a>
                                    @endforeach
                                </div>
                                <label class="market-native-filter-wrap" for="marketNativeWorkbookFilter">
                                    <i class="fas fa-search"></i>
                                    <input
                                        id="marketNativeWorkbookFilter"
                                        type="search"
                                        class="market-native-filter-input"
                                        placeholder="Filter baris workbook..."
                                        data-market-native-filter="#marketNativeWorkbookTable"
                                    >
                                </label>
                            </div>
                            <div class="market-native-render-note">
                                <span><strong>{{ $nativeSelectedSheet }}</strong></span>
                                <span>{{ number_format($nativeShownRows, 0, ',', '.') }} dari {{ number_format($nativeRowCount, 0, ',', '.') }} baris</span>
                                <span>{{ number_format(min($nativeColumnCount, count($nativeColumnLabels)), 0, ',', '.') }} dari {{ number_format($nativeColumnCount, 0, ',', '.') }} kolom</span>
                                @if(!empty($nativeWorkbook['truncated']))
                                    <span class="is-warning">Ditampilkan sebagian agar dashboard tetap ringan.</span>
                                @endif
                            </div>
                            @if($nativeRows === [] || $nativeColumnLabels === [])
                                <div class="market-workbook-state">
                                    Data workbook native belum bisa dibaca dari cache.
                                </div>
                            @else
                                <div class="market-excel-table-shell">
                                    <table id="marketNativeWorkbookTable" class="market-excel-table">
                                        <colgroup>
                                            <col style="width:46px;min-width:46px;">
                                            @foreach($nativeColumnWidths as $columnStyle)
                                                <col style="{{ $columnStyle }}">
                                            @endforeach
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th class="market-excel-corner"></th>
                                                @foreach($nativeColumnLabels as $columnLabel)
                                                    <th>{{ $columnLabel }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($nativeRows as $row)
                                                <tr data-market-native-row style="{{ $row['style'] ?? '' }}">
                                                    <th class="market-excel-row-number">{{ $row['number'] ?? $loop->iteration }}</th>
                                                    @foreach(($row['cells'] ?? []) as $cell)
                                                        @if(!empty($cell['skip']))
                                                            @continue
                                                        @endif
                                                        @php
                                                            $cellRowspan = max(1, (int) ($cell['rowspan'] ?? 1));
                                                            $cellColspan = max(1, (int) ($cell['colspan'] ?? 1));
                                                        @endphp
                                                        <td
                                                            class="{{ !empty($cell['empty']) ? 'is-empty' : '' }}"
                                                            style="{{ $cell['style'] ?? '' }}"
                                                            @if($cellRowspan > 1) rowspan="{{ $cellRowspan }}" @endif
                                                            @if($cellColspan > 1) colspan="{{ $cellColspan }}" @endif
                                                        >{{ $cell['value'] ?? '' }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @elseif($excelWorkbookUrl !== '')
                        @php
                            $officeSheetUrls = $excelWorkbookSheetUrls ?? [];
                            $officeSelectedSheet = (string) ($excelWorkbookSelectedSheet ?? '');
                        @endphp
                        <div class="market-office-frame-stage">
                            @if(!empty($officeSheetUrls))
                                <div class="market-office-sheet-tabs" aria-label="Pilih sheet workbook Excel">
                                    @foreach($officeSheetUrls as $sheetName => $sheetUrl)
                                        <button
                                            type="button"
                                            class="market-office-sheet-tab {{ (string) $sheetName === $officeSelectedSheet ? 'active' : '' }}"
                                            data-market-office-sheet-url="{{ $sheetUrl }}"
                                            data-market-office-sheet-name="{{ $sheetName }}"
                                        >
                                            {{ $sheetName }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            <iframe
                                id="marketOfficeWorkbookFrame"
                                class="market-workbook-frame"
                                src="{{ $excelWorkbookUrl }}"
                                title="{{ $frameTitle ?? ('Workbook ' . ($pageTitle ?? 'Market Share') . ' ' . $workbookProviderLabel) }}"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allow="fullscreen"
                            ></iframe>
                        </div>
                    @else
                        <div class="market-workbook-state">
                            Workbook Excel belum dikonfigurasi.
                        </div>
                    @endif
                </div>
            </div>

            @if($hasMappingSummary)
                <div class="market-mapping-mode {{ $defaultWorkbookMode === 'summary' ? 'active' : '' }}" data-market-workbook-mode-panel="summary">
                    <div class="market-mapping-summary">
                        <div class="market-mapping-summary-hero">
                            <div class="market-mapping-summary-card">
                                <div class="market-mapping-summary-kicker">
                                    <i class="fas fa-chart-pie"></i>
                                    Market Share Mapping
                                </div>
                                <h2 class="market-mapping-summary-title">{{ $mappingSummary['title'] ?? 'Dashboard Sektor Potensi & Debitur' }}</h2>
                                <div class="market-mapping-summary-subtitle">
                                    {{ $mappingSummary['subtitle'] ?? 'Ringkasan sektor utama dari workbook Mapping Market Share.' }}
                                </div>
                                <div class="market-mapping-summary-selected">
                                    <i class="fas fa-crosshairs"></i>
                                    Sektor terpilih: <span data-market-summary-selected>{{ $mappingSelectedSector }}</span>
                                </div>
                                @if(!empty($mappingSectors))
                                    <div class="market-mapping-sector-control">
                                        <label for="marketMappingSectorSelect">Ganti sektor</label>
                                        <select id="marketMappingSectorSelect" data-market-summary-sector-select>
                                            @foreach($mappingSectors as $sector)
                                                @php
                                                    $sectorLabel = (string) ($sector['label'] ?? '-');
                                                @endphp
                                                <option
                                                    value="{{ $sectorLabel }}"
                                                    data-conversion="{{ $sector['conversion'] ?? '-' }}"
                                                    {{ strtoupper($sectorLabel) === strtoupper($mappingSelectedSector) ? 'selected' : '' }}
                                                >
                                                    {{ $sectorLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>

                            <div class="market-mapping-total-grid">
                                @foreach(($mappingSummary['totalMetrics'] ?? []) as $metric)
                                    <div class="market-mapping-total-card">
                                        <div class="market-mapping-total-top">
                                            <span class="market-mapping-total-icon">
                                                <i class="{{ $metric['icon'] ?? 'fas fa-chart-pie' }}"></i>
                                            </span>
                                            <span>{{ $metric['label'] ?? 'Total' }}</span>
                                        </div>
                                        <div class="market-mapping-total-value">{{ $metric['value'] ?? '-' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if(!empty($mappingCharts))
                            <div class="market-mapping-summary-panel">
                                <div class="market-mapping-summary-panel-header">
                                    <h3 class="market-mapping-summary-panel-title">Grafik Dashboard</h3>
                                    <span>Diambil dari chart workbook</span>
                                </div>
                                <div class="market-mapping-chart-grid">
                                    @foreach($mappingCharts as $chart)
                                        @php
                                            $chartTitle = (string) ($chart['title'] ?? 'Chart');
                                            $chartType = (string) ($chart['type'] ?? 'bar-column');
                                            $chartCategories = $chart['categories'] ?? [];
                                            $chartSeries = $chart['series'] ?? [];
                                            $chartMax = max(0.000001, (float) ($chart['maxValue'] ?? 1));
                                        @endphp
                                        <div class="market-chart-card">
                                            <h4 class="market-chart-title">{{ $chartTitle }}</h4>

                                            @if($chartType === 'doughnut' && !empty($chartSeries[0]['values']))
                                                @php
                                                    $values = $chartSeries[0]['values'] ?? [];
                                                    $total = max(0.000001, array_sum(array_map('abs', $values)));
                                                    $cursor = 0.0;
                                                    $segments = [];
                                                    foreach ($values as $index => $value) {
                                                        $start = $cursor;
                                                        $cursor += (abs((float) $value) / $total) * 360;
                                                        $color = $chartPalette[$index % count($chartPalette)];
                                                        $segments[] = "{$color} {$start}deg {$cursor}deg";
                                                    }
                                                @endphp
                                                <div class="market-chart-doughnut-layout">
                                                    <div class="market-chart-doughnut" style="--chart-conic: conic-gradient({{ implode(', ', $segments) }});"></div>
                                                    <div class="market-chart-doughnut-legend">
                                                        @foreach($values as $index => $value)
                                                            <div class="market-chart-legend-item">
                                                                <span class="market-chart-swatch" style="--chart-color: {{ $chartPalette[$index % count($chartPalette)] }};"></span>
                                                                <span>{{ $chartCategories[$index] ?? 'Item' }} &middot; {{ $chartValueLabel($value, $chartTitle) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @elseif($chartType === 'bar-horizontal')
                                                <div class="market-chart-horizontal-list">
                                                    @foreach($chartCategories as $categoryIndex => $category)
                                                        <div class="market-chart-horizontal-row">
                                                            <div class="market-chart-horizontal-label">{{ $category }}</div>
                                                            <div class="market-chart-horizontal-bars">
                                                                @foreach($chartSeries as $seriesIndex => $series)
                                                                    @php
                                                                        $value = (float) (($series['values'][$categoryIndex] ?? 0));
                                                                        $width = min(100, max(2, abs($value) / $chartMax * 100));
                                                                    @endphp
                                                                    <div
                                                                        class="market-chart-horizontal-bar"
                                                                        title="{{ $series['name'] ?? 'Series' }}: {{ $chartValueLabel($value, $chartTitle) }}"
                                                                        style="width: {{ $width }}%; --chart-color: {{ $chartPalette[$seriesIndex % count($chartPalette)] }};"
                                                                    ></div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="market-chart-legend">
                                                    @foreach($chartSeries as $seriesIndex => $series)
                                                        <span class="market-chart-legend-item">
                                                            <span class="market-chart-swatch" style="--chart-color: {{ $chartPalette[$seriesIndex % count($chartPalette)] }};"></span>
                                                            {{ $series['name'] ?? 'Series' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="market-chart-column-plot" style="--chart-count: {{ max(1, count($chartCategories)) }};">
                                                    @foreach($chartCategories as $categoryIndex => $category)
                                                        <div class="market-chart-column-item">
                                                            <div class="market-chart-column-bars">
                                                                @foreach($chartSeries as $seriesIndex => $series)
                                                                    @php
                                                                        $value = (float) (($series['values'][$categoryIndex] ?? 0));
                                                                        $height = min(100, max(2, abs($value) / $chartMax * 100));
                                                                    @endphp
                                                                    <div
                                                                        class="market-chart-column-bar"
                                                                        title="{{ $series['name'] ?? 'Series' }}: {{ $chartValueLabel($value, $chartTitle) }}"
                                                                        style="height: {{ $height }}%; --chart-color: {{ $chartPalette[$seriesIndex % count($chartPalette)] }};"
                                                                    ></div>
                                                                @endforeach
                                                            </div>
                                                            <div class="market-chart-axis-label">{{ $category }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="market-chart-legend">
                                                    @foreach($chartSeries as $seriesIndex => $series)
                                                        <span class="market-chart-legend-item">
                                                            <span class="market-chart-swatch" style="--chart-color: {{ $chartPalette[$seriesIndex % count($chartPalette)] }};"></span>
                                                            {{ $series['name'] ?? 'Series' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($mappingSummary['headlineMetrics']))
                            <div class="market-mapping-summary-panel">
                                <div class="market-mapping-summary-panel-header">
                                    <h3 class="market-mapping-summary-panel-title">KPI Sektor Terpilih</h3>
                                    <span data-market-summary-selected>{{ $mappingSelectedSector }}</span>
                                </div>
                                <div class="market-mapping-kpi-grid">
                                    @foreach($mappingSummary['headlineMetrics'] as $metric)
                                        @php
                                            $metricLabel = (string) ($metric['label'] ?? 'KPI');
                                            $metricKey = preg_replace('/[^A-Z0-9]+/', '', strtoupper($metricLabel));
                                            $isConversionMetric = str_contains(strtoupper($metricLabel), 'KONVERSI');
                                        @endphp
                                        <div class="market-mapping-kpi-card">
                                            <div class="market-mapping-kpi-label">
                                                <i class="{{ $metric['icon'] ?? 'fas fa-chart-pie' }}"></i>
                                                {{ $metricLabel }}
                                            </div>
                                            <div
                                                class="market-mapping-kpi-value"
                                                data-market-summary-metric-value="{{ $metricKey }}"
                                                @if($isConversionMetric) data-market-summary-conversion-value @endif
                                            >{{ $metric['value'] ?? '-' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($mappingSummary['sectors']))
                            <div class="market-mapping-summary-panel">
                                <div class="market-mapping-summary-panel-header">
                                    <h3 class="market-mapping-summary-panel-title">Kartu Per Sektor Ekonomi</h3>
                                    <span>Konversi</span>
                                </div>
                                <div class="market-mapping-sector-grid">
                                    @foreach($mappingSummary['sectors'] as $sector)
                                        @php
                                            $sectorLabel = (string) ($sector['label'] ?? '-');
                                        @endphp
                                        <button
                                            type="button"
                                            class="market-mapping-sector-card tone-{{ $sector['tone'] ?? 'blue' }} {{ strtoupper($sectorLabel) === strtoupper($mappingSelectedSector) ? 'is-active' : '' }}"
                                            data-market-summary-sector-card
                                            data-sector-label="{{ $sectorLabel }}"
                                            data-sector-conversion="{{ $sector['conversion'] ?? '-' }}"
                                        >
                                            <div class="market-mapping-sector-icon">
                                                <i class="{{ $sector['icon'] ?? 'fas fa-cubes' }}"></i>
                                            </div>
                                            <div>
                                                <div class="market-mapping-sector-label">{{ $sectorLabel }}</div>
                                                <div class="market-mapping-sector-meta">Konversi</div>
                                                <div class="market-mapping-sector-value">{{ $sector['conversion'] ?? '-' }}</div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <script type="application/json" id="marketMappingSectorPayload">{!! json_encode($mappingSectorDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
            @endif
        </div>
    @elseif(!empty($nativeMarketShare['ready']))
        @php
            $fallbackMode = [
                'key' => 'simpanan',
                'label' => 'Simpanan',
                'total_label' => 'Total Simpanan Area 6',
                'panel_label' => 'Market Share Simpanan Per Cabang',
                'periods' => $nativeMarketShare['periods'] ?? [],
                'sections' => $nativeMarketShare['sections'] ?? [],
                'branchRows' => $nativeMarketShare['branchRows'] ?? [],
                'components' => ['giro', 'tabungan', 'deposito', 'casa'],
            ];
            $modes = $nativeMarketShare['modes'] ?? ['simpanan' => $fallbackMode];
            $activeMode = array_key_first($modes) ?: 'simpanan';
            $formatMoney = function (float|int|null $value): string {
                $value = (float) ($value ?? 0);
                if (abs($value) >= 1000) {
                    return 'Rp' . number_format($value / 1000, 2, ',', '.') . ' T';
                }

                return 'Rp' . number_format($value, 2, ',', '.') . ' M';
            };
            $formatPct = fn (float|int|null $value): string => number_format(((float) ($value ?? 0)) * 100, 2, ',', '.') . '%';
            $formatPp = function (float|int|null $value): string {
                $value = ((float) ($value ?? 0)) * 100;
                return ($value >= 0 ? '+' : '') . number_format($value, 2, ',', '.') . ' pp';
            };
            $toneClass = fn (float|int|null $value): string => ((float) ($value ?? 0)) >= 0 ? 'market-native-positive' : 'market-native-negative';
        @endphp

        <div class="market-native-shell">
            @if(count($modes) > 1)
                <div class="market-native-switch" role="tablist" aria-label="Pilih jenis market share">
                    @foreach($modes as $modeKey => $mode)
                        <button
                            type="button"
                            class="market-native-switch-button {{ $modeKey === $activeMode ? 'active' : '' }}"
                            data-market-mode-trigger="{{ $modeKey }}"
                            role="tab"
                            aria-selected="{{ $modeKey === $activeMode ? 'true' : 'false' }}"
                        >
                            <i class="{{ $modeKey === 'pinjaman' ? 'fas fa-hand-holding-usd' : 'fas fa-landmark' }}"></i>
                            {{ $mode['label'] ?? ucfirst((string) $modeKey) }}
                        </button>
                    @endforeach
                </div>
            @endif

            @foreach($modes as $modeKey => $mode)
                @php
                    $sections = $mode['sections'] ?? [];
                    $periods = $mode['periods'] ?? [];
                    $total = $sections['total']['summary'] ?? [];
                    $branchRows = $mode['branchRows'] ?? [];
                    $components = $mode['components'] ?? [];
                @endphp
                <div class="market-native-mode {{ $modeKey === $activeMode ? 'active' : '' }}" data-market-mode-panel="{{ $modeKey }}">
                    <div class="market-native-hero">
                        <div class="market-native-card">
                            <div class="market-native-kicker">
                                <span>{{ $mode['total_label'] ?? 'Total Area 6' }}</span>
                                <span class="market-native-period">{{ $periods['current'] ?? '-' }}</span>
                            </div>
                            <div class="market-native-main-value">{{ $formatMoney($total['bri_current'] ?? 0) }}</div>
                            <div class="market-native-subgrid">
                                <div class="market-native-stat">
                                    <div class="market-native-stat-label">Market Share</div>
                                    <div class="market-native-stat-value">{{ $formatPct($total['share_current'] ?? 0) }}</div>
                                </div>
                                <div class="market-native-stat">
                                    <div class="market-native-stat-label">YtD Share</div>
                                    <div class="market-native-stat-value {{ $toneClass($total['share_delta_ytd'] ?? 0) }}">{{ $formatPp($total['share_delta_ytd'] ?? 0) }}</div>
                                </div>
                                <div class="market-native-stat">
                                    <div class="market-native-stat-label">Industri</div>
                                    <div class="market-native-stat-value">{{ $formatMoney($total['industry_current'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="market-native-composition">
                            @foreach($components as $key)
                                @php
                                    $item = $sections[$key] ?? null;
                                @endphp
                                @if($item)
                                    <div class="market-component-card">
                                        <div class="market-component-icon">
                                            <i class="{{ $item['icon'] }}"></i>
                                        </div>
                                        <div>
                                            <div class="market-component-title">{{ $item['label'] }}</div>
                                            <div class="market-component-meta">
                                                <span>{{ $formatMoney($item['summary']['bri_current'] ?? 0) }}</span>
                                                <strong>{{ $formatPct($item['composition_pct'] ?? 0) }}</strong>
                                            </div>
                                        </div>
                                        <div class="market-component-bar">
                                            <span style="width: {{ min(100, max(0, (float) ($item['composition_pct'] ?? 0) * 100)) }}%"></span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="market-native-panel">
                        <div class="market-native-panel-header">
                            <h2 class="market-native-panel-title">{{ $mode['panel_label'] ?? 'Market Share Per Cabang' }}</h2>
                            <span>{{ $periods['current'] ?? '-' }}</span>
                        </div>
                        <div class="market-native-table-wrap">
                            <table class="market-native-table">
                                <thead>
                                    <tr>
                                        <th>Cabang</th>
                                        <th>BRI</th>
                                        <th>Industri</th>
                                        <th>Luar BRI</th>
                                        <th>Market Share</th>
                                        <th>YoY</th>
                                        <th>YtD</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($branchRows as $row)
                                        <tr>
                                            <td><strong>{{ $row['branch'] }}</strong></td>
                                            <td>{{ $formatMoney($row['bri_current'] ?? 0) }}</td>
                                            <td>{{ $formatMoney($row['industry_current'] ?? 0) }}</td>
                                            <td>{{ $formatMoney($row['outside_current'] ?? 0) }}</td>
                                            <td><strong>{{ $formatPct($row['share_current'] ?? 0) }}</strong></td>
                                            <td class="{{ $toneClass($row['share_delta_yoy'] ?? 0) }}">{{ $formatPp($row['share_delta_yoy'] ?? 0) }}</td>
                                            <td class="{{ $toneClass($row['share_delta_ytd'] ?? 0) }}">{{ $formatPp($row['share_delta_ytd'] ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="market-workbook-frame-shell">
        @if(!empty($showDownloadPanel))
            <div class="market-workbook-state" style="flex-direction: column; gap: 1rem; justify-content: center; align-items: center; height: 100%;">
                <div style="font-size: 3rem; color: var(--market-blue); margin-bottom: 0.5rem;">
                    <i class="fas fa-file-download"></i>
                </div>
                <div style="font-size: 1.1rem; font-weight: 800; color: #0f172a;">Workbook Tidak Dapat Ditampilkan di Browser</div>
                <p style="max-width: 450px; font-size: 0.82rem; color: var(--market-muted); margin: 0 auto 1rem; line-height: 1.5; font-weight: 500;">
                    {{ $displayBlockedMessage }}
                </p>
                @if(!empty($downloadUrl))
                    <a href="{{ $downloadUrl }}" class="market-workbook-button" style="background: var(--market-blue); color: #ffffff; padding: 0.65rem 1.2rem; font-size: 0.85rem;" download>
                        <i class="fas fa-download"></i> {{ $downloadActionLabel }}
                    </a>
                @endif
            </div>
        @elseif($workbookUrl !== '')
            <iframe
                class="market-workbook-frame"
                src="{{ $workbookUrl }}"
                title="{{ $frameTitle ?? ('Workbook ' . ($pageTitle ?? 'Market Share') . ' ' . $workbookProviderLabel) }}"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allow="fullscreen"
            ></iframe>
        @else
            <div class="market-workbook-state">
                {{ $emptyMessage ?? 'Workbook belum dikonfigurasi.' }}
            </div>
        @endif
        </div>
    @endif
</div>
@if(!empty($nativeMarketShare['ready']))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const triggers = document.querySelectorAll('[data-market-mode-trigger]');
            const panels = document.querySelectorAll('[data-market-mode-panel]');

            triggers.forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    const mode = trigger.getAttribute('data-market-mode-trigger');

                    triggers.forEach(function (item) {
                        const active = item === trigger;
                        item.classList.toggle('active', active);
                        item.setAttribute('aria-selected', active ? 'true' : 'false');
                    });

                    panels.forEach(function (panel) {
                        panel.classList.toggle('active', panel.getAttribute('data-market-mode-panel') === mode);
                    });
                });
            });
        });
    </script>
@endif
@if(!empty($nativeWorkbook['ready']))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modeTriggers = document.querySelectorAll('[data-market-workbook-mode-trigger]');
            const modePanels = document.querySelectorAll('[data-market-workbook-mode-panel]');

            modeTriggers.forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    const mode = trigger.getAttribute('data-market-workbook-mode-trigger');

                    modeTriggers.forEach(function (item) {
                        const active = item === trigger;
                        item.classList.toggle('active', active);
                        item.setAttribute('aria-selected', active ? 'true' : 'false');
                    });

                    modePanels.forEach(function (panel) {
                        panel.classList.toggle('active', panel.getAttribute('data-market-workbook-mode-panel') === mode);
                    });
                });
            });

            const sectorSelect = document.querySelector('[data-market-summary-sector-select]');
            const selectedSectorLabels = document.querySelectorAll('[data-market-summary-selected]');
            const conversionTargets = document.querySelectorAll('[data-market-summary-conversion-value]');
            const metricTargets = document.querySelectorAll('[data-market-summary-metric-value]');
            const sectorCards = document.querySelectorAll('[data-market-summary-sector-card]');
            const sectorPayloadElement = document.getElementById('marketMappingSectorPayload');
            let sectorPayload = {};

            if (sectorPayloadElement) {
                try {
                    sectorPayload = JSON.parse(sectorPayloadElement.textContent || '{}');
                } catch (error) {
                    sectorPayload = {};
                }
            }

            function sectorKey(value) {
                return (value || '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '');
            }

            function setSummarySector(label, conversion) {
                const normalizedLabel = sectorKey(label);
                const sectorDetail = sectorPayload[normalizedLabel] || {};
                const sectorMetrics = sectorDetail.metrics || {};
                const sectorConversion = sectorDetail.conversion || conversion;

                selectedSectorLabels.forEach(function (item) {
                    item.textContent = label || '-';
                });

                if (sectorConversion) {
                    conversionTargets.forEach(function (item) {
                        item.textContent = sectorConversion;
                    });
                }

                metricTargets.forEach(function (item) {
                    const metricKey = item.getAttribute('data-market-summary-metric-value') || '';
                    if (!Object.prototype.hasOwnProperty.call(sectorMetrics, metricKey)) {
                        return;
                    }

                    item.textContent = sectorMetrics[metricKey] || '-';
                });

                if (sectorSelect) {
                    Array.from(sectorSelect.options).forEach(function (option) {
                        option.selected = sectorKey(option.value) === normalizedLabel;
                    });
                }

                sectorCards.forEach(function (card) {
                    const cardLabel = sectorKey(card.getAttribute('data-sector-label') || '');
                    card.classList.toggle('is-active', cardLabel === normalizedLabel);
                });
            }

            if (sectorSelect) {
                sectorSelect.addEventListener('change', function () {
                    const option = sectorSelect.options[sectorSelect.selectedIndex];
                    setSummarySector(sectorSelect.value, option ? option.getAttribute('data-conversion') : '');
                });
            }

            sectorCards.forEach(function (card) {
                card.addEventListener('click', function () {
                    const label = card.getAttribute('data-sector-label') || '';
                    const conversion = card.getAttribute('data-sector-conversion') || '';

                    if (sectorSelect) {
                        sectorSelect.value = label;
                    }

                    setSummarySector(label, conversion);
                });
            });

            document.querySelectorAll('[data-market-native-filter]').forEach(function (input) {
                const table = document.querySelector(input.getAttribute('data-market-native-filter'));
                if (!table) {
                    return;
                }

                const rows = table.querySelectorAll('[data-market-native-row]');
                input.addEventListener('input', function () {
                    const query = input.value.trim().toLowerCase();

                    rows.forEach(function (row) {
                        row.style.display = query === '' || row.textContent.toLowerCase().includes(query) ? '' : 'none';
                    });
                });
            });

            const officeFrame = document.getElementById('marketOfficeWorkbookFrame');
            document.querySelectorAll('[data-market-office-sheet-url]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const url = button.getAttribute('data-market-office-sheet-url');
                    if (!officeFrame || !url || officeFrame.getAttribute('src') === url) {
                        return;
                    }

                    document.querySelectorAll('[data-market-office-sheet-url]').forEach(function (item) {
                        item.classList.toggle('active', item === button);
                    });
                    officeFrame.setAttribute('src', url);
                });
            });
        });
    </script>
@endif
@if(!empty($instansiNativeTable))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shell = document.querySelector('[data-market-instansi-table-shell]');
            const table = document.querySelector('[data-market-instansi-table]');
            const state = document.querySelector('[data-market-instansi-state]');
            const menu = document.querySelector('[data-market-instansi-menu]');
            const hideButton = document.querySelector('[data-market-hide-column]');
            const hiddenList = document.querySelector('[data-market-hidden-columns]');

            if (!shell || !table || !state || !menu || !hideButton || !hiddenList) {
                return;
            }

            const sourceUrl = shell.getAttribute('data-source-url') || '';
            const storageKey = shell.getAttribute('data-storage-key') || 'marketshare-instansi-hidden-columns';
            let columns = [];
            let rows = [];
            let activeTarget = null;
            let hiddenColumns = new Set();
            let columnGroups = [];
            let groupedColumnRanges = new Map();
            let stickyColumnIndexes = [];
            let conditionalFormatColumnIndexes = new Set();
            let hasStoredHiddenColumns = false;
            let holdTimer = null;
            let holdStart = null;
            let suppressNextClick = false;

            try {
                const storedHiddenColumns = localStorage.getItem(storageKey);
                hasStoredHiddenColumns = storedHiddenColumns !== null;
                hiddenColumns = new Set(JSON.parse(storedHiddenColumns || '[]'));
            } catch (error) {
                hiddenColumns = new Set();
            }

            function isNumericValue(value) {
                const normalized = String(value || '').trim().replace(/\./g, '').replace(/,/g, '.');
                return normalized !== '' && !Number.isNaN(Number(normalized));
            }

            function saveHiddenColumns() {
                localStorage.setItem(storageKey, JSON.stringify(Array.from(hiddenColumns)));
            }

            function closeMenu() {
                menu.classList.remove('show');
                activeTarget = null;
            }

            function positionMenu(point) {
                const padding = 10;
                menu.classList.add('show');
                const rect = menu.getBoundingClientRect();
                const left = Math.min(point.clientX, window.innerWidth - rect.width - padding);
                const top = Math.min(point.clientY, window.innerHeight - rect.height - padding);
                menu.style.left = Math.max(padding, left) + 'px';
                menu.style.top = Math.max(padding, top) + 'px';
            }

            function clearHoldTimer() {
                if (holdTimer !== null) {
                    window.clearTimeout(holdTimer);
                    holdTimer = null;
                }
                holdStart = null;
            }

            function setActiveTargetFromCell(cell) {
                const groupStart = cell.getAttribute('data-group-start');
                const groupEnd = cell.getAttribute('data-group-end');
                if (groupStart !== null && groupEnd !== null) {
                    activeTarget = {
                        indexes: Array.from(
                            { length: Number(groupEnd) - Number(groupStart) + 1 },
                            function (_, offset) {
                                return Number(groupStart) + offset;
                            }
                        ),
                    };
                    return;
                }

                activeTarget = {
                    indexes: [Number(cell.getAttribute('data-column-index'))],
                };
            }

            function renderHiddenList() {
                hiddenList.innerHTML = '';
                if (hiddenColumns.size === 0) {
                    const emptyButton = document.createElement('button');
                    emptyButton.type = 'button';
                    emptyButton.disabled = true;
                    const emptyLabel = document.createElement('span');
                    emptyLabel.textContent = 'Tidak ada kolom tersembunyi';
                    emptyButton.appendChild(emptyLabel);
                    hiddenList.appendChild(emptyButton);
                    return;
                }

                const hiddenItems = [];
                const consumedColumns = new Set();
                columnGroups.forEach(function (group) {
                    if (!group.grouped) {
                        return;
                    }

                    const allHidden = group.indexes.every(function (columnIndex) {
                        return hiddenColumns.has(columnIndex);
                    });
                    if (!allHidden) {
                        return;
                    }

                    group.indexes.forEach(function (columnIndex) {
                        consumedColumns.add(columnIndex);
                    });
                    hiddenItems.push({
                        label: group.label,
                        indexes: group.indexes,
                    });
                });

                Array.from(hiddenColumns).sort((a, b) => a - b).forEach(function (columnIndex) {
                    if (consumedColumns.has(columnIndex)) {
                        return;
                    }

                    hiddenItems.push({
                        label: columns[columnIndex] || ('Kolom ' + (columnIndex + 1)),
                        indexes: [columnIndex],
                    });
                });

                hiddenItems.forEach(function (item) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.setAttribute('data-market-unhide-column', item.indexes.join(','));
                    const label = document.createElement('span');
                    const icon = document.createElement('i');
                    label.textContent = 'Unhide ' + item.label;
                    icon.className = 'fas fa-eye';
                    button.appendChild(label);
                    button.appendChild(icon);
                    hiddenList.appendChild(button);
                });
            }

            function applyColumnVisibility() {
                const tableRows = table.querySelectorAll('tr');
                tableRows.forEach(function (row) {
                    Array.from(row.children).forEach(function (cell, index) {
                        const groupStart = cell.getAttribute('data-group-start');
                        const groupEnd = cell.getAttribute('data-group-end');
                        if (groupStart !== null && groupEnd !== null) {
                            let visibleCount = 0;
                            for (let i = Number(groupStart); i <= Number(groupEnd); i++) {
                                if (!hiddenColumns.has(i)) {
                                    visibleCount++;
                                }
                            }

                            cell.style.display = visibleCount === 0 ? 'none' : '';
                            cell.colSpan = Math.max(1, visibleCount);
                            return;
                        }

                        const columnIndex = Number(cell.getAttribute('data-column-index'));
                        cell.style.display = hiddenColumns.has(columnIndex) ? 'none' : '';
                    });
                });
                applyStickyColumns();
                renderHiddenList();
                saveHiddenColumns();
            }

            function applyStickyColumns() {
                table.querySelectorAll('.is-sticky-col').forEach(function (cell) {
                    cell.classList.remove('is-sticky-col');
                    cell.style.left = '';
                });

                let left = 0;
                stickyColumnIndexes.forEach(function (columnIndex) {
                    if (hiddenColumns.has(columnIndex)) {
                        return;
                    }

                    const cells = table.querySelectorAll('[data-column-index="' + columnIndex + '"]');
                    let width = 0;
                    cells.forEach(function (cell) {
                        if (cell.style.display === 'none') {
                            return;
                        }

                        cell.classList.add('is-sticky-col');
                        cell.style.left = left + 'px';
                        width = Math.max(width, cell.getBoundingClientRect().width || cell.offsetWidth || 118);
                    });
                    left += width;
                });
            }

            function normalizeColumnLabel(label) {
                return String(label || '').trim().toUpperCase().replace(/\s+/g, ' ');
            }

            function isConditionalFormatColumn(label) {
                const normalized = normalizeColumnLabel(label);
                return normalized === 'YTD'
                    || normalized === 'MTD'
                    || normalized.includes('SUDAH TERLAYANI');
            }

            function parseConditionalNumber(value) {
                let normalized = String(value ?? '').trim();
                if (normalized === '' || normalized === '-') {
                    return 0;
                }

                let multiplier = 1;
                if (/^\(.*\)$/.test(normalized)) {
                    multiplier = -1;
                    normalized = normalized.slice(1, -1);
                }

                normalized = normalized
                    .replace(/%/g, '')
                    .replace(/\s+/g, '')
                    .replace(/[^\d,.\-]/g, '');

                if (normalized.startsWith('-')) {
                    multiplier = -1;
                    normalized = normalized.slice(1);
                }

                if (normalized.includes(',') && normalized.includes('.')) {
                    normalized = normalized.replace(/\./g, '').replace(',', '.');
                } else if (normalized.includes(',')) {
                    normalized = normalized.replace(',', '.');
                }

                const numeric = Number(normalized);
                return Number.isFinite(numeric) ? numeric * multiplier : 0;
            }

            function conditionalToneClass(value) {
                const numeric = parseConditionalNumber(value);
                if (numeric > 0) {
                    return 'market-instansi-tone-good';
                }
                if (numeric < 0) {
                    return 'market-instansi-tone-bad';
                }
                return 'market-instansi-tone-flat';
            }

            function isDefaultHiddenColumn(label) {
                return normalizeColumnLabel(label) === 'KEPALA INSTANSI';
            }

            function resolveStickyColumnIndexes() {
                return columns
                    .map(function (column, index) {
                        return {
                            label: normalizeColumnLabel(column),
                            index,
                        };
                    })
                    .filter(function (column) {
                        return column.label === 'CABANG' || column.label === 'NAMA INSTANSI';
                    })
                    .map(function (column) {
                        return column.index;
                    });
            }

            function headerGroupForColumn(label, nextLabel) {
                const trimmed = String(label || '').trim();
                const upper = trimmed.toUpperCase().replace(/\s+/g, ' ');
                const nextUpper = String(nextLabel || '').trim().toUpperCase().replace(/\s+/g, ' ');

                if (upper.endsWith(' DEB') && nextUpper === 'OUTSTANDING') {
                    return {
                        grouped: true,
                        label: trimmed.replace(/\s+Deb$/i, '').trim(),
                    };
                }

                return {
                    grouped: false,
                    label: trimmed,
                };
            }

            function renderTable() {
                const thead = table.querySelector('thead');
                const tbody = table.querySelector('tbody');
                thead.innerHTML = '';
                tbody.innerHTML = '';
                columnGroups = [];
                groupedColumnRanges = new Map();
                stickyColumnIndexes = resolveStickyColumnIndexes();
                conditionalFormatColumnIndexes = new Set();

                if (!hasStoredHiddenColumns) {
                    columns.forEach(function (column, columnIndex) {
                        if (isDefaultHiddenColumn(column)) {
                            hiddenColumns.add(columnIndex);
                        }
                    });
                }

                const groupRow = document.createElement('tr');
                const subRow = document.createElement('tr');

                for (let columnIndex = 0; columnIndex < columns.length; columnIndex++) {
                    const group = headerGroupForColumn(columns[columnIndex], columns[columnIndex + 1]);
                    const th = document.createElement('th');
                    th.textContent = group.label || ('Kolom ' + (columnIndex + 1));
                    th.setAttribute('data-column-index', String(columnIndex));
                    if (group.grouped) {
                        const startIndex = columnIndex;
                        const endIndex = columnIndex + 1;
                        const indexes = [startIndex, endIndex];
                        if (isConditionalFormatColumn(group.label) || isConditionalFormatColumn(columns[startIndex]) || isConditionalFormatColumn(columns[endIndex])) {
                            indexes.forEach(function (index) {
                                conditionalFormatColumnIndexes.add(index);
                            });
                        }
                        th.colSpan = 2;
                        th.setAttribute('data-group-start', String(startIndex));
                        th.setAttribute('data-group-end', String(endIndex));
                        columnGroups.push({
                            grouped: true,
                            label: group.label,
                            indexes,
                        });
                        groupedColumnRanges.set(String(startIndex), indexes);
                        groupedColumnRanges.set(String(endIndex), indexes);
                        const debTh = document.createElement('th');
                        const osTh = document.createElement('th');
                        debTh.textContent = 'Deb';
                        osTh.textContent = 'OS';
                        debTh.setAttribute('data-column-index', String(columnIndex));
                        osTh.setAttribute('data-column-index', String(columnIndex + 1));
                        subRow.appendChild(debTh);
                        subRow.appendChild(osTh);
                        groupRow.appendChild(th);
                        columnIndex++;
                    } else {
                        if (isConditionalFormatColumn(group.label)) {
                            conditionalFormatColumnIndexes.add(columnIndex);
                        }
                        th.rowSpan = 2;
                        th.setAttribute('data-rowspan', '2');
                        groupRow.appendChild(th);
                        columnGroups.push({
                            grouped: false,
                            label: group.label,
                            indexes: [columnIndex],
                        });
                    }
                }

                thead.appendChild(groupRow);
                if (subRow.children.length > 0) {
                    thead.appendChild(subRow);
                }

                rows.forEach(function (row) {
                    const tr = document.createElement('tr');
                    columns.forEach(function (_, columnIndex) {
                        const td = document.createElement('td');
                        const value = row[columnIndex] ?? '';
                        td.textContent = value;
                        td.setAttribute('data-column-index', String(columnIndex));
                        if (isNumericValue(value)) {
                            td.classList.add('is-numeric');
                        }
                        if (conditionalFormatColumnIndexes.has(columnIndex)) {
                            td.classList.add(conditionalToneClass(value));
                        }
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });

                table.classList.remove('d-none');
                state.classList.add('d-none');
                applyColumnVisibility();
            }

            shell.addEventListener('contextmenu', function (event) {
                const cell = event.target.closest('[data-column-index]');
                if (!cell) {
                    return;
                }

                event.preventDefault();
                clearHoldTimer();
                setActiveTargetFromCell(cell);
                positionMenu(event);
            });

            shell.addEventListener('pointerdown', function (event) {
                if (event.button !== undefined && event.button !== 0) {
                    return;
                }

                const cell = event.target.closest('[data-column-index]');
                if (!cell) {
                    return;
                }

                clearHoldTimer();
                holdStart = {
                    x: event.clientX,
                    y: event.clientY,
                    pointerId: event.pointerId,
                    cell,
                };

                holdTimer = window.setTimeout(function () {
                    if (!holdStart) {
                        return;
                    }

                    setActiveTargetFromCell(holdStart.cell);
                    positionMenu({
                        clientX: holdStart.x,
                        clientY: holdStart.y,
                    });
                    suppressNextClick = true;
                    holdTimer = null;
                }, 620);
            });

            shell.addEventListener('pointermove', function (event) {
                if (!holdStart || holdStart.pointerId !== event.pointerId) {
                    return;
                }

                const deltaX = Math.abs(event.clientX - holdStart.x);
                const deltaY = Math.abs(event.clientY - holdStart.y);
                if (deltaX > 10 || deltaY > 10) {
                    clearHoldTimer();
                }
            });

            ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (eventName) {
                shell.addEventListener(eventName, clearHoldTimer);
            });

            hideButton.addEventListener('click', function () {
                if (!activeTarget || !Array.isArray(activeTarget.indexes)) {
                    return;
                }

                activeTarget.indexes.forEach(function (columnIndex) {
                    if (!Number.isNaN(columnIndex)) {
                        hiddenColumns.add(columnIndex);
                    }
                });
                applyColumnVisibility();
                closeMenu();
            });

            hiddenList.addEventListener('click', function (event) {
                const button = event.target.closest('[data-market-unhide-column]');
                if (!button) {
                    return;
                }

                String(button.getAttribute('data-market-unhide-column') || '')
                    .split(',')
                    .map(function (value) {
                        return Number(value);
                    })
                    .forEach(function (columnIndex) {
                        hiddenColumns.delete(columnIndex);
                    });
                applyColumnVisibility();
                closeMenu();
            });

            document.addEventListener('click', function (event) {
                if (suppressNextClick) {
                    suppressNextClick = false;
                    return;
                }

                if (!menu.contains(event.target)) {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeMenu();
                }
            });

            if (sourceUrl === '') {
                state.textContent = 'Endpoint data Marketshare Instansi belum tersedia.';
                return;
            }

            fetch(sourceUrl, {
                headers: {
                    'Accept': 'application/json',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (!payload.ready) {
                        throw new Error(payload.message || 'Data belum siap.');
                    }

                    columns = Array.isArray(payload.columns) ? payload.columns : [];
                    rows = Array.isArray(payload.rows) ? payload.rows : [];
                    if (columns.length === 0) {
                        throw new Error('Header DATA INSTANSI tidak terbaca.');
                    }

                    renderTable();
                })
                .catch(function (error) {
                    table.classList.add('d-none');
                    state.classList.remove('d-none');
                    state.textContent = 'Data DATA INSTANSI belum bisa dimuat. ' + error.message;
                });
        });
    </script>
@endif
@endsection
