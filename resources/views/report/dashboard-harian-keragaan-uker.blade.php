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
        border-radius: 8px;
        box-shadow: 0 12px 28px -24px rgba(15, 23, 42, 0.35);
        overflow: hidden;
    }

    .uker-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 1rem;
        border-bottom: 1px solid #e5edf6;
        background: #ffffff;
    }

    .uker-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
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
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
        border: 1px solid #d8e4f0;
        background: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .uker-filters {
        display: grid;
        grid-template-columns: repeat(5, minmax(150px, 1fr));
        gap: 0.75rem;
        padding: 0.9rem 1rem;
        border-bottom: 1px solid #e5edf6;
        background: #fbfcfe;
    }

    .uker-filter label {
        display: block;
        margin-bottom: 0.28rem;
        color: #526173;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .uker-control {
        width: 100%;
        height: 38px;
        border: 1px solid #cbd7e6;
        border-radius: 6px;
        background: #ffffff;
        color: #111827;
        font-size: 0.86rem;
        font-weight: 700;
        padding: 0 0.7rem;
    }

    .uker-control:disabled {
        background: #eef2f7;
        color: #94a3b8;
    }

    .uker-table-wrap {
        width: 100%;
        max-height: calc(100vh - 300px);
        overflow: auto;
        scrollbar-color: #94a3b8 #eef2f7;
        scrollbar-width: thin;
    }

    .uker-tables {
        display: grid;
        gap: 1rem;
        padding: 1rem;
    }

    .uker-table-card {
        border: 1px solid #d7e1ed;
        border-radius: 8px;
        background: #ffffff;
        overflow: hidden;
    }

    .uker-table-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 0.8rem;
        border-bottom: 1px solid #d7e1ed;
        background: #f8fafc;
        color: #0f172a;
        font-size: 0.9rem;
        font-weight: 900;
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
        width: 100%;
        min-width: 1480px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }

    .uker-table th,
    .uker-table td {
        border-right: 1px solid #d7e1ed;
        border-bottom: 1px solid #d7e1ed;
        padding: 0.52rem 0.62rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .uker-table thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #102f5f;
        color: #ffffff;
        text-align: center;
        font-size: 0.75rem;
        font-weight: 800;
    }

    .uker-table thead tr:nth-child(2) th {
        top: 35px;
        background: #174477;
    }

    .uker-table tbody td {
        background: #ffffff;
    }

    .uker-table tbody tr:nth-child(even) td {
        background: #f9fbfd;
    }

    .uker-table .uker-name {
        min-width: 220px;
        color: #173a66;
        font-weight: 800;
    }

    .uker-table .uker-code {
        color: #334155;
        font-weight: 800;
        text-align: center;
    }

    .uker-table .number {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .uker-table .total-row td {
        background: #eef5ff !important;
        color: #0f3768;
        font-weight: 900;
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
            max-height: calc(100vh - 330px);
        }
    }
</style>
@endsection

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0">Keragaan per Uker</h1>
            </div>
        </div>
    </div>
</div>

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

            <div class="uker-filters">
                <div class="uker-filter">
                    <label for="kancaFilter">Cabang</label>
                    <select id="kancaFilter" class="uker-control"></select>
                </div>
                <div class="uker-filter">
                    <label for="unitFilter">Unit Kerja</label>
                    <select id="unitFilter" class="uker-control"></select>
                </div>
                <div class="uker-filter">
                    <label for="dataTypeFilter">Data</label>
                    <select id="dataTypeFilter" class="uker-control"></select>
                </div>
                <div class="uker-filter">
                    <label for="periodFilter">Periode</label>
                    <select id="periodFilter" class="uker-control"></select>
                </div>
                <div class="uker-filter">
                    <label for="rkaFilter">RKA</label>
                    <select id="rkaFilter" class="uker-control"></select>
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

        function buildSelects(payload) {
            const filters = (payload && payload.available_filters) || page.filters || {};
            const selected = page.selected || {};
            const selectedKanca = els.kanca.value || selectedScalar(selected.kanca, '');
            const selectedUnit = els.unit.value || selectedScalar(selected.unit_kerja, 'all');
            const selectedDataType = els.dataType.value || selected.data_type || 'pinjaman';

            els.kanca.innerHTML = `<option value="">Pilih cabang</option>${optionHtml(filters.kanca || [], selectedKanca)}`;
            els.unit.innerHTML = optionHtml(filters.unit_kerja || [], selectedUnit);
            els.unit.disabled = els.kanca.value === '';
            els.dataType.innerHTML = optionHtml(page.dataTypes || [], selectedDataType);
            els.period.innerHTML = optionHtml(filters.posisi_terakhir || [], els.period.value || selected.posisi_terakhir);
            els.rka.innerHTML = optionHtml(filters.posisi_rka || [], els.rka.value || selected.posisi_rka);
        }

        function renderHeader(payload, metricLabel) {
            const positions = payload?.columns?.positions || [];
            const deltas = payload?.columns?.deltas || [];

            return `
                <tr>
                    <th rowspan="2">Kode Unit Kerja</th>
                    <th rowspan="2">Nama Unit Kerja</th>
                    <th colspan="${positions.length}">Posisi</th>
                    <th colspan="${deltas.length}">Delta</th>
                    <th rowspan="2">RKA</th>
                    <th rowspan="2">Penc RKA</th>
                </tr>
                <tr>
                    ${positions.map(col => `<th>${escapeHtml(col.label || '-')}</th>`).join('')}
                    ${deltas.map(col => `<th>${escapeHtml(col.label || '-')}</th>`).join('')}
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

        function rowHtml(row, metricKey, positions, deltas, isTotal = false) {
            const metric = metricForRow(row, metricKey);
            if (!metric) {
                return '';
            }

            return `
                <tr class="${isTotal ? 'total-row' : ''}">
                    <td class="uker-code">${escapeHtml(row.unit_code || '-')}</td>
                    <td class="uker-name">${escapeHtml(row.unit_name || '-')}</td>
                    ${metricCells(metric, positions, deltas)}
                </tr>
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
                            <thead>${renderHeader(payload, metric.label)}</thead>
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
            els.empty.textContent = els.kanca.value === ''
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
            if (!els.kanca.value) {
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

            const params = new URLSearchParams({
                kanca: els.kanca.value || '',
                unit_kerja: els.unit.value || 'all',
                data_type: els.dataType.value || 'pinjaman',
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
                buildSelects(payload);
                render(payload);
            } catch (error) {
                console.error(error);
                shell.classList.remove('is-loading');
                shell.classList.add('is-empty');
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
