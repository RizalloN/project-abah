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
        overflow: hidden;
    }

    .uker-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, #0b2247 0%, #1e40af 100%);
        color: #ffffff;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .uker-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.01em;
    }

    .uker-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin-top: 0.35rem;
    }

    .uker-pill {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0.22rem 0.65rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 700;
        backdrop-filter: blur(4px);
    }

    .uker-pill-period {
        background: #ffffff !important;
        color: #0f2942 !important;
        border: 1px solid #ffffff !important;
        font-weight: 800 !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Single-Row Filter Shell & Collapsible Toggle */
    .uker-filter-shell {
        position: relative;
        z-index: 100;
        margin: 1rem 1.25rem 0.5rem;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.03);
        padding: 0.75rem 1rem;
    }

    .uker-filters-bar {
        display: grid;
        grid-template-columns: repeat(5, minmax(130px, 1fr));
        gap: 0.65rem;
        align-items: flex-end;
    }

    .uker-filter-item {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .uker-filter-label {
        display: block;
        margin-bottom: 0.3rem;
        color: #475569;
        font-size: 0.66rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .uker-select-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .uker-select-icon {
        position: absolute;
        left: 0.65rem;
        color: #64748b;
        font-size: 0.78rem;
        pointer-events: none;
        z-index: 2;
    }

    .uker-control {
        width: 100%;
        height: 38px;
        border-radius: 8px;
        border: 1.5px solid #cbd5e1;
        background: #ffffff;
        color: #1e293b;
        font-size: 0.82rem;
        font-weight: 700;
        padding-left: 2rem;
        padding-right: 1.6rem;
        outline: none;
        transition: all 0.2s ease;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.5rem center;
        background-size: 1rem;
        cursor: pointer;
    }

    .uker-control:hover {
        border-color: #0b57d0;
        background-color: #f8fafc;
    }

    .uker-control:focus {
        border-color: #0b57d0;
        box-shadow: 0 0 0 3px rgba(11, 87, 208, 0.14);
    }

    .uker-control:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
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
            min-width: 1380px;
            font-size: 0.78rem;
        }

        .uker-table th,
        .uker-table td {
            padding: 0.46rem 0.5rem;
        }
    }
</style>
@endsection

@section('content')


<section class="content uker-page">
    <div class="container-fluid">
        <div class="uker-shell" id="ukerShell">
            <div class="uker-header">
                <div>
                    <h2 class="uker-title" id="ukerTitle">Keragaan per Uker</h2>
                    <div class="uker-meta">
                        <span class="uker-pill" id="scopeLabel">Area 6</span>
                        <span class="uker-pill" id="unitLabel">Semua Unit Kerja</span>
                        <span class="uker-pill" id="sourceLabel">Pinjaman</span>
                    </div>
                </div>
                <div class="uker-pill" id="periodLabel">-</div>
            </div>

            <!-- Single-Row Filter Bar (Collapsible for Mobile/Tablet) -->
            <div class="uker-filter-shell" id="filterShell">
                <div class="uker-filter-mobile-toggle">
                    <button type="button" class="btn-filter-toggle" id="btnFilterToggle" aria-expanded="false">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-filter"></i>
                            <span>Filter Data</span>
                        </div>
                        <span class="active-filters-badge" id="activeFiltersBadge">5 Filter Aktif</span>
                        <i class="fas fa-chevron-down toggle-arrow-icon"></i>
                    </button>
                </div>

                <div class="uker-filters-bar" id="ukerFiltersBar">
                    <div class="uker-filter-item">
                        <label for="kancaFilter" class="uker-filter-label">Cabang</label>
                        <div class="uker-select-wrap">
                            <i class="fas fa-building uker-select-icon"></i>
                            <select id="kancaFilter" name="kanca" class="uker-control"></select>
                        </div>
                    </div>
                    <div class="uker-filter-item">
                        <label for="unitFilter" class="uker-filter-label">Unit Kerja</label>
                        <div class="uker-select-wrap">
                            <i class="fas fa-sitemap uker-select-icon"></i>
                            <select id="unitFilter" class="uker-control"></select>
                        </div>
                    </div>
                    <div class="uker-filter-item">
                        <label for="dataTypeFilter" class="uker-filter-label">Data</label>
                        <div class="uker-select-wrap">
                            <i class="fas fa-database uker-select-icon"></i>
                            <select id="dataTypeFilter" class="uker-control"></select>
                        </div>
                    </div>
                    <div class="uker-filter-item">
                        <label for="periodFilter" class="uker-filter-label">Periode</label>
                        <div class="uker-select-wrap">
                            <i class="fas fa-calendar-alt uker-select-icon"></i>
                            <select id="periodFilter" class="uker-control"></select>
                        </div>
                    </div>
                    <div class="uker-filter-item">
                        <label for="rkaFilter" class="uker-filter-label">RKA</label>
                        <div class="uker-select-wrap">
                            <i class="fas fa-bullseye uker-select-icon"></i>
                            <select id="rkaFilter" class="uker-control"></select>
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
            els.period.innerHTML = optionHtml(filters.posisi_terakhir || [], els.period.value || selected.posisi_terakhir);
            els.rka.innerHTML = optionHtml(filters.posisi_rka || [], els.rka.value || selected.posisi_rka);
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

        function renderHeader(payload) {
            const positions = payload?.columns?.positions || [];
            const deltas = payload?.columns?.deltas || [];

            const posColsHtml = positions.map(col => `<th>${escapeHtml(formatHeaderDate(col.period, col.label))}</th>`).join('');
            const deltaColsHtml = deltas.map(col => `<th>${escapeHtml(formatDeltaLabel(col))}</th>`).join('');

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
                    <th>RKA</th>
                    <th>Penc. RKA</th>
                </tr>
            `;
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
            const bodyRows = (payload?.rows || []).map(row => rowHtml(row, metric.key, positions, deltas)).join('');
            const totalRow = payload?.totals ? rowHtml(payload.totals, metric.key, positions, deltas, true) : '';

            return `
                <section class="uker-table-card">
                    <div class="uker-table-title">
                        <span>${escapeHtml(metric.label || '')}</span>
                        <span>Rp Juta</span>
                    </div>
                    <div class="uker-table-wrap">
                        <table class="uker-table">
                            ${renderColgroup(positions, deltas)}
                            <thead>${renderHeader(payload)}</thead>
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

        function bindEvents() {
            els.kanca.addEventListener('change', () => fetchData(true));
            els.unit.addEventListener('change', () => fetchData(false));
            els.dataType.addEventListener('change', () => fetchData(false));
            els.period.addEventListener('change', () => fetchData(false));
            els.rka.addEventListener('change', () => fetchData(false));
        }

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
