@extends('layouts.admin')

@section('title', 'Dashboard Harian')

@section('styles')
<style>
    .daily-dashboard {
        --daily-no-width: 64px;
        --daily-label-width: 280px;
        --daily-position-width: 142px;
        --daily-delta-width: 122px;
        --daily-rka-width: 142px;
        --daily-border: rgba(8, 87, 195, 0.14);
        --daily-muted: #5d7b9d;
        --daily-nusantara: #0857c3;
        --daily-nusantara-dark: #053b82;
        --daily-nusantara-light: #307fe2;
    }

    .daily-panel-head {
        background: linear-gradient(180deg, #fbfdff 0%, #f6f9ff 100%);
    }

    .daily-panel-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        line-height: 1.2;
        color: #07356d;
    }

    .daily-panel-desc {
        margin: 0.4rem 0 0;
        color: #5d7b9d;
        font-size: 0.9rem;
        line-height: 1.5;
        max-width: 760px;
    }

    .daily-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        background: #f7fbff;
        border: 1px solid rgba(8, 87, 195, 0.14);
        color: #0b3b80;
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .daily-surface {
        border: 1px solid var(--daily-border);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 18px 36px -28px rgba(4, 42, 95, 0.32);
        overflow: visible;
    }

    .daily-filter-label {
        font-size: 0.68rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--daily-muted);
        font-weight: 800;
        margin-bottom: 0.35rem;
    }

    .daily-scope {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .daily-scope-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        border: 1px solid rgba(8, 87, 195, 0.14);
        background: #f7fbff;
        color: #0b3b80;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .daily-kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .daily-kpi {
        border-radius: 0.95rem;
        background: linear-gradient(180deg, #ffffff 0%, #f4f9ff 100%);
        border: 1px solid rgba(8, 87, 195, 0.12);
        padding: 0.9rem 1rem;
    }

    .daily-kpi .label {
        font-size: 0.7rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--daily-muted);
        font-weight: 800;
        margin-bottom: 0.35rem;
    }

    .daily-kpi .value {
        font-size: 1.05rem;
        font-weight: 800;
        color: #083974;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .daily-table-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        border-top: 1px solid rgba(8, 87, 195, 0.08);
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .daily-table-wrap::-webkit-scrollbar {
        width: 0;
        height: 0;
    }

    .daily-table {
        min-width: 1846px;
        width: max-content;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
        table-layout: fixed;
    }

    .daily-table th,
    .daily-table td {
        white-space: nowrap;
        vertical-align: middle;
        box-sizing: border-box;
        background-clip: padding-box;
    }

    .daily-table thead th {
        border-bottom: 1px solid rgba(8, 87, 195, 0.18);
        background: linear-gradient(180deg, var(--daily-nusantara-dark), var(--daily-nusantara));
        color: #ffffff;
    }

    .daily-table thead tr.group-row th {
        font-size: 0.66rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-weight: 800;
        background: linear-gradient(180deg, #0a61db 0%, var(--daily-nusantara) 100%);
        color: #ffffff;
        padding-top: 0.8rem;
        padding-bottom: 0.8rem;
        vertical-align: middle;
        border-right: 1px solid rgba(255, 255, 255, 0.14);
    }

    .daily-table thead tr.column-row th {
        font-size: 0.69rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-weight: 800;
        background: linear-gradient(180deg, #0d68e5 0%, #0b5fd0 100%);
        color: #ffffff;
        padding-top: 0.72rem;
        padding-bottom: 0.72rem;
        vertical-align: middle;
        border-right: 1px solid rgba(255, 255, 255, 0.12);
    }

    .daily-table thead tr.group-row th.group-no {
        width: var(--daily-no-width);
        min-width: var(--daily-no-width);
        max-width: var(--daily-no-width);
    }

    .daily-table thead tr.group-row th.group-label {
        width: var(--daily-label-width);
        min-width: var(--daily-label-width);
        max-width: var(--daily-label-width);
    }

    .daily-table thead tr.group-row th.group-position {
        width: calc(var(--daily-position-width) * 6);
        min-width: calc(var(--daily-position-width) * 6);
    }

    .daily-table thead tr.group-row th.group-delta {
        width: calc(var(--daily-delta-width) * 3);
        min-width: calc(var(--daily-delta-width) * 3);
    }

    .daily-table thead tr.group-row th.group-rka {
        width: calc(var(--daily-rka-width) * 2);
        min-width: calc(var(--daily-rka-width) * 2);
    }

    .daily-table tbody td {
        font-size: 0.72rem;
        color: #17385c;
        padding-top: 0.68rem;
        padding-bottom: 0.68rem;
        border-top: 1px solid rgba(8, 87, 195, 0.08);
    }

    .daily-table tbody tr:hover {
        background: rgba(113, 197, 232, 0.11);
    }

    .daily-table tbody tr.metric-block-simpanan td,
    .daily-table tbody tr.metric-block-os td,
    .daily-table tbody tr.metric-block-sml td,
    .daily-table tbody tr.metric-block-npl td,
    .daily-table tbody tr.metric-block-casa td,
    .daily-table tbody tr.metric-block-ldr td {
        background: linear-gradient(90deg, rgba(5, 150, 105, 0.07), rgba(5, 150, 105, 0.03));
    }

    .daily-table tbody tr.metric-block-simpanan .sticky-no,
    .daily-table tbody tr.metric-block-simpanan .sticky-label,
    .daily-table tbody tr.metric-block-os .sticky-no,
    .daily-table tbody tr.metric-block-os .sticky-label,
    .daily-table tbody tr.metric-block-sml .sticky-no,
    .daily-table tbody tr.metric-block-sml .sticky-label,
    .daily-table tbody tr.metric-block-npl .sticky-no,
    .daily-table tbody tr.metric-block-npl .sticky-label,
    .daily-table tbody tr.metric-block-casa .sticky-no,
    .daily-table tbody tr.metric-block-casa .sticky-label,
    .daily-table tbody tr.metric-block-ldr .sticky-no,
    .daily-table tbody tr.metric-block-ldr .sticky-label {
        background-color: #ffffff;
        background-image: linear-gradient(90deg, rgba(5, 150, 105, 0.12), rgba(5, 150, 105, 0.05));
    }

    .daily-table tbody tr.metric-block-simpanan:hover td,
    .daily-table tbody tr.metric-block-os:hover td,
    .daily-table tbody tr.metric-block-sml:hover td,
    .daily-table tbody tr.metric-block-npl:hover td,
    .daily-table tbody tr.metric-block-casa:hover td,
    .daily-table tbody tr.metric-block-ldr:hover td {
        filter: saturate(1.03);
    }

    .daily-table .sticky-no {
        position: sticky;
        left: 0;
        width: var(--daily-no-width);
        min-width: var(--daily-no-width);
        z-index: 10;
        background: #ffffff;
        box-shadow: 8px 0 16px -16px rgba(4, 42, 95, 0.35);
        border-right: 1px solid rgba(8, 87, 195, 0.12);
        text-align: center;
    }

    .daily-table .sticky-label {
        position: sticky;
        left: var(--daily-no-width);
        width: var(--daily-label-width);
        min-width: var(--daily-label-width);
        z-index: 9;
        background: #ffffff;
        box-shadow: 8px 0 16px -16px rgba(4, 42, 95, 0.28);
        border-right: 1px solid rgba(8, 87, 195, 0.12);
        overflow: hidden;
    }

    .daily-table thead .sticky-no {
        z-index: 15;
    }

    .daily-table thead .sticky-label {
        z-index: 14;
    }

    .daily-table .header-subnote {
        display: block;
        margin-top: 0.2rem;
        font-size: 0.65rem;
        letter-spacing: 0;
        text-transform: none;
        color: rgba(255, 255, 255, 0.82);
        font-weight: 700;
    }

    .daily-table th.value-col,
    .daily-table td.value-col {
        width: var(--daily-position-width);
        min-width: var(--daily-position-width);
        max-width: var(--daily-position-width);
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
        padding-left: 0.4rem;
        padding-right: 0.4rem;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: right;
        white-space: nowrap;
    }

    .daily-table td.value-col {
        position: relative;
        z-index: 1;
    }

    .daily-table .cell-text {
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }

    .daily-table th.value-col.delta-col,
    .daily-table td.value-col.delta-col {
        width: var(--daily-delta-width);
        min-width: var(--daily-delta-width);
        max-width: var(--daily-delta-width);
    }

    .daily-table th.value-col.rka-col,
    .daily-table td.value-col.rka-col {
        width: var(--daily-rka-width);
        min-width: var(--daily-rka-width);
        max-width: var(--daily-rka-width);
    }

    .daily-table .position-col-hidden {
        display: none !important;
    }

    .daily-table .column-heading {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 0.12rem;
        line-height: 1.05;
    }

    .daily-table .column-heading .main {
        font-size: 0.74rem;
        font-weight: 800;
        text-transform: none;
        letter-spacing: 0.01em;
    }

    .daily-table .row-depth-0 .metric-label {
        font-weight: 800;
        color: #083974;
    }

    .daily-table .row-depth-1 .metric-label {
        padding-left: 0.9rem;
        font-weight: 700;
    }

    .daily-table .row-depth-2 .metric-label {
        padding-left: 1.7rem;
    }

    .daily-table .row-depth-3 .metric-label {
        padding-left: 2.5rem;
        color: #537293;
    }

    .daily-table .metric-value {
        font-weight: 800;
        color: #0b3b80;
    }

    .daily-table tbody tr.metric-block-simpanan td.value-col .cell-text,
    .daily-table tbody tr.metric-block-os td.value-col .cell-text,
    .daily-table tbody tr.metric-block-sml td.value-col .cell-text,
    .daily-table tbody tr.metric-block-npl td.value-col .cell-text,
    .daily-table tbody tr.metric-block-casa td.value-col .cell-text,
    .daily-table tbody tr.metric-block-ldr td.value-col .cell-text {
        font-size: 0.66rem;
    }

    .daily-table .metric-label {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.18;
        padding-right: 0.35rem;
    }

    .daily-table .delta-positive {
        color: #0b7f40;
        font-weight: 700;
    }

    .daily-table .delta-negative {
        color: #a11d1d;
        font-weight: 800;
    }

    .daily-table tbody tr:hover .sticky-no,
    .daily-table tbody tr:hover .sticky-label {
        background: #f4f9ff;
    }

    .daily-empty {
        padding: 2rem 1.25rem;
        text-align: center;
        color: #6782a4;
    }

    .daily-loading {
        opacity: 0.64;
        pointer-events: none;
    }

    .daily-table-sticky-footer {
        position: sticky;
        bottom: 0;
        z-index: 16;
        margin-top: -1px;
        padding: 0.55rem 0.75rem 0.8rem;
        background: linear-gradient(180deg, rgba(247, 251, 255, 0.12), rgba(247, 251, 255, 0.95));
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(8, 87, 195, 0.12);
    }

    .daily-table-sticky-track {
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid rgba(8, 87, 195, 0.12);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 12px 24px -20px rgba(4, 42, 95, 0.25);
    }

    .daily-table-sticky-spacer {
        min-width: 1846px;
        height: 1px;
    }

    @media (max-width: 991.98px) {
        .daily-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .daily-dashboard {
            --daily-label-width: 220px;
            --daily-position-width: 114px;
            --daily-delta-width: 110px;
            --daily-rka-width: 114px;
        }

        .daily-table col.numeric-col {
            width: 114px !important;
        }

        .daily-kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="daily-dashboard" id="daily-dashboard-root">
    <div class="daily-surface mb-4" id="daily-surface">
        <div class="daily-panel-head p-4 border-bottom">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center">
                <div class="mb-3 mb-lg-0 pr-lg-4">
                    <span class="daily-meta-chip mb-2">
                        <i class="fas fa-calendar-day"></i>
                        Dashboard Harian Snapshot
                    </span>
                    <h1 class="daily-panel-title">Perbandingan posisi, delta, dan RKA harian.</h1>
                    <p class="daily-panel-desc">
                        Data dibangun dari snapshot agregat `simpanan_multipn` dan `daily_loan_dinamis`.
                    </p>
                </div>

                <div class="daily-meta-chip">
                    <i class="fas fa-database"></i>
                    <span data-source-label>{{ data_get($dashboardPage, 'initialData.summary.source', 'dashboard_harian_snapshots') }}</span>
                </div>
            </div>
        </div>

        <div class="p-4 border-bottom">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-3">
                <div class="mb-3 mb-lg-0">
                    <div class="daily-filter-label">Scope aktif</div>
                    <div class="daily-scope">
                        <span class="daily-scope-chip"><i class="fas fa-map-marker-alt"></i> <span data-scope-kanca>{{ data_get($dashboardPage, 'initialData.summary.kanca_label', 'Semua Kanca') }}</span></span>
                        <span class="daily-scope-chip"><i class="fas fa-sitemap"></i> <span data-scope-unit>{{ data_get($dashboardPage, 'initialData.summary.unit_label', 'Semua Unit Kerja') }}</span></span>
                        <span class="daily-scope-chip"><i class="fas fa-clock"></i> <span data-scope-posisi>{{ data_get($dashboardPage, 'initialData.selected_period_label', 'Belum ada data') }}</span></span>
                        <span class="daily-scope-chip"><i class="fas fa-bullseye"></i> <span data-scope-rka>{{ data_get($dashboardPage, 'initialData.selected_rka_label', 'Belum ada data') }}</span></span>
                    </div>
                </div>

                <div class="text-lg-right">
                    <div class="daily-filter-label">Status data</div>
                    <div class="text-dark font-weight-bold" data-scope-summary>Filter belum dijalankan.</div>
                </div>
            </div>

            <div class="daily-kpi-grid mb-4">
                <div class="daily-kpi">
                    <div class="label">Total Simpanan</div>
                    <div class="value" data-kpi-simpanan>-</div>
                </div>
                <div class="daily-kpi">
                    <div class="label">OS Non Commercial</div>
                    <div class="value" data-kpi-os>-</div>
                </div>
                <div class="daily-kpi">
                    <div class="label">% CASA</div>
                    <div class="value" data-kpi-casa>-</div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="daily-filter-label">Kanca</div>
                    <select id="filter-kanca" class="form-control select2"></select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="daily-filter-label">Unit Kerja</div>
                    <select id="filter-unit" class="form-control select2"></select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="daily-filter-label">Posisi Terakhir</div>
                    <select id="filter-posisi-terakhir" class="form-control select2"></select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="daily-filter-label">Posisi RKA</div>
                    <select id="filter-posisi-rka" class="form-control select2"></select>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mt-2">
                <div class="text-muted small mb-2 mb-lg-0">
                    Pilih filter lalu klik <strong>Terapkan Filter</strong> untuk menghitung snapshot.
                </div>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-primary px-4" id="btn-apply-daily-filter">
                        <i class="fas fa-filter mr-1"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </div>

        <div class="p-4">
        <div class="daily-table-wrap">
            <table class="table table-bordered daily-table">
                <colgroup>
                    <col style="width: 64px;">
                    <col style="width: 280px;">
                    <col style="width: 142px;" class="numeric-col">
                    <col style="width: 142px;" class="numeric-col">
                    <col style="width: 142px;" class="numeric-col">
                    <col style="width: 142px;" class="numeric-col">
                    <col style="width: 142px;" class="numeric-col position-col-h1">
                    <col style="width: 142px;" class="numeric-col">
                    <col style="width: 122px;" class="numeric-col">
                    <col style="width: 122px;" class="numeric-col">
                    <col style="width: 122px;" class="numeric-col">
                    <col style="width: 142px;" class="numeric-col">
                    <col style="width: 142px;" class="numeric-col">
                </colgroup>
                <thead>
                    <tr class="group-row text-center">
                            <th class="sticky-no group-no" rowspan="2">No</th>
                            <th class="sticky-label group-label text-left" rowspan="2">Keterangan</th>
                            <th class="group-position" colspan="6" data-position-group-colspan>Perbandingan Posisi</th>
                            <th class="group-delta" colspan="3">Delta Terhadap</th>
                            <th class="group-rka" colspan="2">Perbandingan RKA</th>
                        </tr>
                        <tr class="column-row text-center">
                            <th class="value-col position-col"><span class="column-heading"><span class="main" data-label-yoy>-</span></span></th>
                            <th class="value-col position-col"><span class="column-heading"><span class="main" data-label-ytd>-</span></span></th>
                            <th class="value-col position-col"><span class="column-heading"><span class="main" data-label-mtm>-</span></span></th>
                            <th class="value-col position-col"><span class="column-heading"><span class="main" data-label-mtd>-</span></span></th>
                            <th class="value-col position-col position-col-h1"><span class="column-heading"><span class="main" data-label-h1>-</span></span></th>
                            <th class="value-col position-col"><span class="column-heading"><span class="main" data-label-current>-</span></span></th>
                            <th class="value-col delta-col"><span class="column-heading"><span class="main" data-label-delta-yoy>Selisih</span><span class="header-subnote">YoY</span></span></th>
                            <th class="value-col delta-col"><span class="column-heading"><span class="main" data-label-delta-ytd>Selisih</span><span class="header-subnote">YtD</span></span></th>
                            <th class="value-col delta-col"><span class="column-heading"><span class="main" data-label-delta-dtd>Selisih</span><span class="header-subnote">DtD</span></span></th>
                            <th class="value-col rka-col"><span class="column-heading"><span class="main" data-label-rka>-</span></span></th>
                            <th class="value-col rka-col"><span class="column-heading"><span class="main" data-label-rka-dec>-</span></span></th>
                        </tr>
                    </thead>
                    <tbody id="daily-dashboard-body">
                        <tr><td colspan="13" class="daily-empty">Memuat data dashboard harian...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="daily-table-sticky-footer">
                <div class="daily-table-sticky-track" data-sticky-scrollbar>
                    <div class="daily-table-sticky-spacer" aria-hidden="true"></div>
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
        const dataUrl = page.routes ? page.routes.data : '';
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
            mtm: document.querySelector('[data-label-mtm]'),
            mtd: document.querySelector('[data-label-mtd]'),
            h1: document.querySelector('[data-label-h1]'),
            current: document.querySelector('[data-label-current]'),
            rka: document.querySelector('[data-label-rka]'),
            rkaDec: document.querySelector('[data-label-rka-dec]'),
        };
        const positionGroupColspan = document.querySelector('[data-position-group-colspan]');
        const positionH1Header = document.querySelector('[data-label-h1]').closest('th');
        const tableWrap = document.querySelector('.daily-table-wrap');
        const stickyScrollbar = document.querySelector('[data-sticky-scrollbar]');
        const stickySpacer = document.querySelector('.daily-table-sticky-spacer');
        const applyButton = document.getElementById('btn-apply-daily-filter');
        const selects = {
            kanca: document.getElementById('filter-kanca'),
            unit_kerja: document.getElementById('filter-unit'),
            posisi_terakhir: document.getElementById('filter-posisi-terakhir'),
            posisi_rka: document.getElementById('filter-posisi-rka'),
        };
        const MILLION_UNIT = 1000000;
        const BILLION_UNIT = 1000000000;
        const TABLE_MONEY_UNIT = BILLION_UNIT;
        const TABLE_MONEY_LABEL = 'M';
        const currencyFormatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const formatCurrency = function (value) {
            return 'Rp ' + currencyFormatter.format(Number(value || 0) / TABLE_MONEY_UNIT) + ' ' + TABLE_MONEY_LABEL;
        };

        const formatMiliar = function (value) {
            return 'Rp ' + currencyFormatter.format(Number(value || 0) / BILLION_UNIT) + ' M';
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

        const formatValue = function (value, type) {
            return type === 'percent' ? formatPercent(value) : formatCurrency(value);
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
                positionGroupColspan.setAttribute('colspan', show ? '6' : '5');
            }
        };

        const syncStickyScrollbarWidth = function () {
            if (!stickySpacer || !tableWrap) {
                return;
            }

            const table = tableWrap.querySelector('.daily-table');
            if (!table) {
                return;
            }

            stickySpacer.style.minWidth = table.scrollWidth + 'px';
        };

        const syncScrollLeft = function (source, target) {
            if (!source || !target) {
                return;
            }

            target.scrollLeft = source.scrollLeft;
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

        const currentState = function () {
            return {
                kanca: selects.kanca.value || 'all',
                unit_kerja: selects.unit_kerja.value || 'all',
                posisi_terakhir: selects.posisi_terakhir.value || '',
                posisi_rka: selects.posisi_rka.value || '',
            };
        };

        const renderTable = function (payload) {
            const rows = payload.rows || [];
            const periods = payload.comparison_periods || {};
            const hasH1 = Boolean(periods.h1 && periods.h1.period);
            const emptyColspan = hasH1 ? 13 : 12;
            const blockClassMap = {
                total_simpanan: 'metric-block-simpanan',
                total_os: 'metric-block-os',
                total_sml_pct_non_commercial: 'metric-block-sml',
                total_npl_pct_non_commercial: 'metric-block-npl',
                casa_pct: 'metric-block-casa',
                ldr_non_commercial: 'metric-block-ldr',
            };

            togglePositionColumns(hasH1);
            syncStickyScrollbarWidth();

            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="' + emptyColspan + '" class="daily-empty">Tidak ada data untuk filter terpilih.</td></tr>';
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

                rowCells.push('<td class="sticky-no text-center font-weight-bold">' + (index + 1) + '</td>');
                rowCells.push('<td class="sticky-label text-left"><span class="metric-label" title="' + escapeHtml(row.label) + '">' + escapeHtml(row.label) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.yoy, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.ytd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtm, row.type) + '</span></td>');
                rowCells.push('<td class="value-col position-col"><span class="cell-text">' + formatValue(value.mtd, row.type) + '</span></td>');

                if (hasH1) {
                    rowCells.push('<td class="value-col position-col position-col-h1" data-position-col="h1"><span class="cell-text">' + formatValue(value.h1, row.type) + '</span></td>');
                }

                rowCells.push('<td class="value-col position-col metric-value"><span class="cell-text">' + formatValue(value.current, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.yoy) + '"><span class="cell-text">' + formatValue(delta.yoy, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.ytd) + '"><span class="cell-text">' + formatValue(delta.ytd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col delta-col ' + deltaClass(delta.dtd) + '"><span class="cell-text">' + formatValue(delta.dtd, row.type) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka, row.type) + '</span></td>');
                rowCells.push('<td class="value-col rka-col"><span class="cell-text">' + formatValue(value.rka_dec, row.type) + '</span></td>');

                const rowClasses = ['row-depth-' + row.depth];
                if (blockClassMap[row.key]) {
                    rowClasses.push(blockClassMap[row.key]);
                }

                return '<tr class="' + rowClasses.join(' ') + '">' + rowCells.join('') + '</tr>';
            }).join('');
        };

        const applyPayload = function (payload) {
            const summary = payload.summary || {};
            const periods = payload.comparison_periods || {};
            const filters = payload.available_filters || initialFilters;
            const hasH1 = Boolean(periods.h1 && periods.h1.period);

            populateSelect(selects.kanca, filters.kanca || [], currentState().kanca);
            populateSelect(selects.unit_kerja, filters.unit_kerja || [], currentState().unit_kerja);
            populateSelect(selects.posisi_terakhir, filters.posisi_terakhir || [], payload.selected_period || currentState().posisi_terakhir);
            populateSelect(selects.posisi_rka, filters.posisi_rka || [], payload.selected_rka_period || currentState().posisi_rka);

            scopeKanca.textContent = summary.kanca_label || 'Semua Kanca';
            scopeUnit.textContent = summary.unit_label || 'Semua Unit Kerja';
            scopePosisi.textContent = payload.selected_period_label || 'Belum ada data';
            scopeRka.textContent = payload.selected_rka_label || 'Belum ada data';
            scopeSummary.textContent = 'Baris tampil: ' + (summary.row_count || 0).toLocaleString('id-ID') + '. Data aktif berasal dari ' + (summary.source || 'source_fallback') + '.';
            sourceLabel.textContent = summary.source || 'source_fallback';
            kpiSimpanan.textContent = formatMiliar(summary.current_total_simpanan || 0);
            kpiOs.textContent = formatMiliar(summary.current_total_os_non_commercial || 0);
            kpiCasa.textContent = formatPercent(summary.current_casa_pct || 0);

            headerLabels.yoy.textContent = periods.yoy ? formatDateSlash(periods.yoy.period) : '-';
            headerLabels.ytd.textContent = periods.ytd ? formatDateSlash(periods.ytd.period) : '-';
            headerLabels.mtm.textContent = periods.mtm ? formatDateSlash(periods.mtm.period) : '-';
            headerLabels.mtd.textContent = periods.mtd ? formatDateSlash(periods.mtd.period) : '-';
            headerLabels.h1.textContent = hasH1 ? formatDateSlash(periods.h1.period) : '-';
            headerLabels.current.textContent = payload.selected_period ? formatDateSlash(payload.selected_period) : '-';
            headerLabels.rka.textContent = periods.rka ? formatDateSlash(periods.rka.period) : '-';
            headerLabels.rkaDec.textContent = periods.rka_dec ? formatDateSlash(periods.rka_dec.period) : '-';

            togglePositionColumns(hasH1);
            syncStickyScrollbarWidth();

            renderTable(payload);
        };

        const fetchData = function () {
            if (!dataUrl) {
                return;
            }

            const params = new URLSearchParams(currentState());
            surface.classList.add('daily-loading');
            if (applyButton) {
                applyButton.disabled = true;
                applyButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat...';
            }

            fetch(dataUrl + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    applyPayload(payload);
                })
                .catch(function () {
                    const hidden = positionH1Header && positionH1Header.classList.contains('position-col-hidden');
                    body.innerHTML = '<tr><td colspan="' + (hidden ? 12 : 13) + '" class="daily-empty">Gagal memuat data dashboard harian.</td></tr>';
                })
                .finally(function () {
                    surface.classList.remove('daily-loading');
                    if (applyButton) {
                        applyButton.disabled = false;
                        applyButton.innerHTML = '<i class="fas fa-filter mr-1"></i> Terapkan Filter';
                    }
                });
        };

        populateSelect(selects.kanca, initialFilters.kanca || [], initialSelected.kanca || 'all');
        populateSelect(selects.unit_kerja, initialFilters.unit_kerja || [], initialSelected.unit_kerja || 'all');
        populateSelect(selects.posisi_terakhir, initialFilters.posisi_terakhir || [], initialSelected.posisi_terakhir || '');
        populateSelect(selects.posisi_rka, initialFilters.posisi_rka || [], initialSelected.posisi_rka || '');
        body.innerHTML = '<tr><td colspan="13" class="daily-empty">Filter belum dijalankan. Pilih parameter lalu klik Terapkan Filter.</td></tr>';

        if (initialData && Object.keys(initialData).length) {
            applyPayload(initialData);
        } else {
            sourceLabel.textContent = '-';
        }

        if (applyButton) {
            applyButton.addEventListener('click', fetchData);
        }

        if (tableWrap && stickyScrollbar) {
            tableWrap.addEventListener('scroll', function () {
                syncScrollLeft(tableWrap, stickyScrollbar);
            });

            stickyScrollbar.addEventListener('scroll', function () {
                syncScrollLeft(stickyScrollbar, tableWrap);
            });
        }

        window.addEventListener('resize', syncStickyScrollbarWidth);
        syncStickyScrollbarWidth();
    });
</script>
@endsection
