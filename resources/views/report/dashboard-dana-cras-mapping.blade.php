@extends('layouts.admin')

@section('title', $pageTitle ?? 'Mapping CRAS')

@section('styles')
<link rel="stylesheet" href="{{ asset('vendor/leaflet-1.9.4/leaflet.css') }}">
@endsection

@section('content')
@php
    $payload = $crasMapping ?? ['ready' => false];
    $filterOptions = data_get($payload, 'filters.options', []);
    $selectedFilters = data_get($payload, 'filters.selected', []);
    $filterLabels = [
        'periode' => 'Posisi Data',
        'wilayah' => 'Wilayah',
        'sektor' => 'Sektor Ekonomi',
        'sub_sektor' => 'Sub Sektor Ekonomi',
        'loan_type' => 'Loan Type',
        'segmen' => 'Segmen',
        'produk_tiering' => 'Produk Tiering',
        'kualitas' => 'Kualitas',
    ];
@endphp

<style>
    :root {
        --cras-blue: #0b5cab;
        --cras-blue-dark: #08467f;
        --cras-ink: #172033;
        --cras-muted: #64748b;
        --cras-border: #dbe3ec;
        --cras-soft: #f5f8fc;
        --cras-white: #ffffff;
    }

    .cras-map-page {
        min-height: calc(100vh - 60px);
        padding: 1rem;
        background: var(--cras-soft);
        color: var(--cras-ink);
    }

    .cras-map-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.8rem;
        padding: 0.9rem 1rem;
        border: 1px solid var(--cras-border);
        border-left: 4px solid var(--cras-blue);
        border-radius: 6px;
        background: var(--cras-white);
    }

    .cras-map-header h1 {
        margin: 0;
        font-size: 1.18rem;
        font-weight: 800;
        line-height: 1.35;
        letter-spacing: 0;
    }

    .cras-map-header p {
        margin: 0.2rem 0 0;
        color: var(--cras-muted);
        font-size: 0.8rem;
    }

    .cras-map-period {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex: 0 0 auto;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .cras-map-period i { color: var(--cras-blue); }

    .cras-filter-panel,
    .cras-kpi-strip,
    .cras-workspace,
    .cras-detail-band,
    .cras-unit-section {
        border: 1px solid var(--cras-border);
        border-radius: 6px;
        background: var(--cras-white);
    }

    .cras-filter-panel {
        margin-bottom: 0.8rem;
        padding: 0.8rem;
    }

    .cras-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.65rem;
    }

    .cras-filter-field {
        min-width: 0;
        margin: 0;
    }

    .cras-filter-field span {
        display: block;
        margin-bottom: 0.28rem;
        color: #526174;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .cras-filter-field select {
        width: 100%;
        height: 38px;
        padding: 0 2rem 0 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: #ffffff;
        color: #243247;
        font-size: 0.78rem;
        box-shadow: none;
        text-overflow: ellipsis;
    }

    .cras-filter-field select:focus {
        border-color: var(--cras-blue);
        outline: 0;
        box-shadow: 0 0 0 3px rgba(11, 92, 171, 0.12);
    }

    .cras-filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        margin-top: 0.7rem;
        padding-top: 0.7rem;
        border-top: 1px solid #edf1f5;
    }

    .cras-heat-field {
        width: min(280px, 100%);
        margin-right: auto;
    }

    .cras-filter-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        min-height: 38px;
        padding: 0.5rem 0.85rem;
        border: 1px solid var(--cras-blue);
        border-radius: 5px;
        background: var(--cras-blue);
        color: #ffffff;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .cras-filter-button:hover,
    .cras-filter-button:focus {
        background: var(--cras-blue-dark);
        color: #ffffff;
    }

    .cras-filter-button--secondary {
        border-color: #cbd5e1;
        background: #ffffff;
        color: #475569;
    }

    .cras-filter-button--secondary:hover,
    .cras-filter-button--secondary:focus {
        background: #f8fafc;
        color: #1e293b;
    }

    .cras-kpi-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 0.8rem;
        overflow: hidden;
    }

    .cras-kpi-item {
        min-width: 0;
        padding: 0.75rem 0.9rem;
        border-right: 1px solid var(--cras-border);
    }

    .cras-kpi-item:last-child { border-right: 0; }

    .cras-kpi-label {
        display: flex;
        align-items: center;
        gap: 0.38rem;
        color: var(--cras-muted);
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .cras-kpi-label i { color: var(--cras-blue); }

    .cras-kpi-value {
        display: block;
        margin-top: 0.28rem;
        color: var(--cras-ink);
        font-size: 1.08rem;
        font-weight: 800;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .cras-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 350px;
        min-height: 610px;
        margin-bottom: 0.8rem;
        overflow: hidden;
    }

    .cras-map-shell {
        position: relative;
        min-width: 0;
        min-height: 610px;
        background: #e8eff6;
    }

    #crasPortfolioMap {
        width: 100%;
        height: 610px;
        background: #e8eff6;
    }

    .cras-map-loading {
        position: absolute;
        inset: 0;
        z-index: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(245, 248, 252, 0.88);
        color: #475569;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .cras-map-loading.is-hidden { display: none; }

    .cras-map-legend {
        position: absolute;
        right: 12px;
        bottom: 12px;
        z-index: 450;
        width: 190px;
        padding: 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 8px 24px -20px rgba(15, 23, 42, 0.5);
    }

    .cras-map-legend strong {
        display: block;
        margin-bottom: 0.4rem;
        color: #334155;
        font-size: 0.72rem;
    }

    .cras-map-legend-scale {
        height: 8px;
        border-radius: 2px;
        background: linear-gradient(90deg, #e8eef5, #b7d0e8, #6fa5d2, #2676b8, #084b87);
    }

    .cras-map-legend-labels {
        display: flex;
        justify-content: space-between;
        margin-top: 0.28rem;
        color: #64748b;
        font-size: 0.64rem;
    }

    .cras-side-panel {
        min-width: 0;
        border-left: 1px solid var(--cras-border);
        background: #ffffff;
    }

    .cras-side-head {
        padding: 0.85rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-side-eyebrow {
        display: block;
        margin-bottom: 0.25rem;
        color: var(--cras-blue);
        font-size: 0.66rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .cras-side-head h2 {
        margin: 0;
        color: var(--cras-ink);
        font-size: 0.98rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .cras-side-head p {
        margin: 0.28rem 0 0;
        color: var(--cras-muted);
        font-size: 0.72rem;
        line-height: 1.45;
    }

    .cras-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 0.75rem;
        gap: 0.45rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-detail-item {
        min-width: 0;
        padding: 0.55rem;
        border: 1px solid #e5eaf0;
        border-radius: 5px;
        background: #f8fafc;
    }

    .cras-detail-item span {
        display: block;
        color: #64748b;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .cras-detail-item strong {
        display: block;
        margin-top: 0.2rem;
        color: #243247;
        font-size: 0.76rem;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .cras-ranking-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.7rem 0.85rem;
        border-bottom: 1px solid var(--cras-border);
        color: #334155;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .cras-ranking-list {
        max-height: 250px;
        overflow-y: auto;
    }

    .cras-ranking-row {
        display: grid;
        grid-template-columns: 26px minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.45rem;
        width: 100%;
        min-height: 38px;
        padding: 0.45rem 0.75rem;
        border: 0;
        border-bottom: 1px solid #edf1f5;
        background: #ffffff;
        color: #334155;
        text-align: left;
        font-size: 0.68rem;
    }

    .cras-ranking-row:hover { background: #f5f8fc; }

    .cras-ranking-order {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 4px;
        background: #e7eef6;
        color: #475569;
        font-weight: 800;
    }

    .cras-ranking-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cras-ranking-row strong {
        color: var(--cras-blue);
        font-size: 0.68rem;
    }

    .cras-detail-band {
        margin-bottom: 0.8rem;
        overflow: hidden;
    }

    .cras-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.72rem 0.85rem;
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-section-head h2 {
        margin: 0;
        color: var(--cras-ink);
        font-size: 0.88rem;
        font-weight: 800;
    }

    .cras-section-head span {
        color: var(--cras-muted);
        font-size: 0.68rem;
    }

    .cras-secondary-metrics {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .cras-secondary-item {
        min-width: 0;
        padding: 0.7rem 0.75rem;
        border-right: 1px solid var(--cras-border);
        border-bottom: 1px solid var(--cras-border);
    }

    .cras-secondary-item:nth-child(6n) { border-right: 0; }
    .cras-secondary-item:nth-last-child(-n + 6) { border-bottom: 0; }

    .cras-secondary-item span {
        display: block;
        color: #64748b;
        font-size: 0.63rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .cras-secondary-item strong {
        display: block;
        margin-top: 0.24rem;
        color: #243247;
        font-size: 0.78rem;
        overflow-wrap: anywhere;
    }

    .cras-unit-section { overflow: hidden; }

    .cras-table-wrap {
        max-height: 430px;
        overflow: auto;
    }

    .cras-unit-table {
        width: 100%;
        min-width: 850px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.72rem;
    }

    .cras-unit-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 0.58rem 0.65rem;
        border-bottom: 1px solid #cbd5e1;
        background: #eef3f8;
        color: #475569;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .cras-unit-table td {
        padding: 0.55rem 0.65rem;
        border-bottom: 1px solid #edf1f5;
        color: #334155;
        white-space: nowrap;
    }

    .cras-unit-table tbody tr:hover td { background: #f8fafc; }
    .cras-unit-table .text-right { text-align: right; }

    .cras-empty {
        padding: 4rem 1rem;
        text-align: center;
        color: #64748b;
    }

    .cras-empty i {
        display: block;
        margin-bottom: 0.7rem;
        color: #94a3b8;
        font-size: 2rem;
    }

    .cras-tooltip {
        display: grid;
        min-width: 190px;
        gap: 0.2rem;
        color: #334155;
        font-size: 0.7rem;
    }

    .cras-tooltip strong {
        color: #172033;
        font-size: 0.8rem;
    }

    .cras-tooltip small { color: #64748b; }

    @media (max-width: 1199.98px) {
        .cras-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-workspace { grid-template-columns: minmax(0, 1fr) 320px; min-height: 540px; }
        .cras-map-shell, #crasPortfolioMap { min-height: 540px; height: 540px; }
        .cras-secondary-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .cras-secondary-item:nth-child(3n) { border-right: 0; }
        .cras-secondary-item:nth-last-child(-n + 6) { border-bottom: 1px solid var(--cras-border); }
        .cras-secondary-item:nth-last-child(-n + 3) { border-bottom: 0; }
    }

    @media (max-width: 991.98px) {
        .cras-workspace { grid-template-columns: minmax(0, 1fr); }
        .cras-side-panel { border-top: 1px solid var(--cras-border); border-left: 0; }
        .cras-map-shell, #crasPortfolioMap { min-height: 500px; height: 500px; }
        .cras-ranking-list { max-height: 220px; }
    }

    @media (max-width: 767.98px) {
        .cras-map-page { padding: 0.65rem; }
        .cras-map-header { align-items: flex-start; flex-direction: column; }
        .cras-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-filter-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-heat-field { grid-column: 1 / -1; width: 100%; }
        .cras-kpi-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-kpi-item:nth-child(2) { border-right: 0; }
        .cras-kpi-item:nth-child(-n + 2) { border-bottom: 1px solid var(--cras-border); }
        .cras-map-shell, #crasPortfolioMap { min-height: 420px; height: 420px; }
        .cras-map-legend { width: 155px; }
        .cras-secondary-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cras-secondary-item:nth-child(3n) { border-right: 1px solid var(--cras-border); }
        .cras-secondary-item:nth-child(2n) { border-right: 0; }
        .cras-secondary-item:nth-last-child(-n + 3) { border-bottom: 1px solid var(--cras-border); }
        .cras-secondary-item:nth-last-child(-n + 2) { border-bottom: 0; }
    }

    @media (max-width: 340px) {
        .cras-filter-grid { grid-template-columns: minmax(0, 1fr); }
        .cras-kpi-strip { grid-template-columns: minmax(0, 1fr); }
        .cras-kpi-item { border-right: 0; border-bottom: 1px solid var(--cras-border); }
        .cras-kpi-item:last-child { border-bottom: 0; }
        .cras-detail-grid { grid-template-columns: minmax(0, 1fr); }
        .cras-filter-actions { grid-template-columns: minmax(0, 1fr); }
    }
</style>

<main class="cras-map-page" data-cras-map-app data-data-url="{{ $crasMappingDataUrl }}">
    <header class="cras-map-header">
        <div>
            <h1><i class="fas fa-map-marked-alt text-primary mr-2"></i>{{ $payload['title'] ?? 'Mapping Portofolio SSA CRAS' }}</h1>
            <p>{{ $payload['subtitle'] ?? 'Sebaran portofolio kredit per wilayah layanan.' }}</p>
        </div>
        <div class="cras-map-period">
            <i class="fas fa-calendar-alt"></i>
            <span data-cras-updated>{{ $payload['updated_at'] ?? '-' }}</span>
        </div>
    </header>

    @if(!empty($payload['ready']))
        <section class="cras-filter-panel" aria-label="Filter Mapping CRAS">
            <div class="cras-filter-grid">
                @foreach($filterLabels as $key => $label)
                    <label class="cras-filter-field" for="crasFilter{{ Illuminate\Support\Str::studly($key) }}">
                        <span>{{ $label }}</span>
                        <select id="crasFilter{{ Illuminate\Support\Str::studly($key) }}" data-cras-filter="{{ $key }}">
                            @foreach(($filterOptions[$key] ?? []) as $option)
                                <option value="{{ $option['value'] ?? '' }}" @selected(($selectedFilters[$key] ?? 'all') === ($option['value'] ?? ''))>
                                    {{ $option['label'] ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
            <div class="cras-filter-actions">
                <label class="cras-filter-field cras-heat-field" for="crasHeatMetric">
                    <span>Pewarnaan Peta</span>
                    <select id="crasHeatMetric" data-cras-heat-metric>
                        @foreach(data_get($payload, 'heatmap.options', []) as $metric)
                            <option value="{{ $metric['key'] ?? '' }}" @selected(data_get($payload, 'heatmap.selected') === ($metric['key'] ?? ''))>
                                {{ $metric['label'] ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="cras-filter-button cras-filter-button--secondary" data-cras-reset title="Reset filter">
                    <i class="fas fa-undo-alt"></i><span>Reset</span>
                </button>
                <button type="button" class="cras-filter-button" data-cras-apply>
                    <i class="fas fa-filter"></i><span>Terapkan</span>
                </button>
            </div>
        </section>

        <section class="cras-kpi-strip" aria-live="polite">
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-file-invoice-dollar"></i> Plafon</span>
                <strong class="cras-kpi-value" data-cras-kpi="plafond">-</strong>
            </div>
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-wallet"></i> Baki Debet</span>
                <strong class="cras-kpi-value" data-cras-kpi="baki_debet">-</strong>
            </div>
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-users"></i> Debitur</span>
                <strong class="cras-kpi-value" data-cras-kpi="jumlah_debitur">-</strong>
            </div>
            <div class="cras-kpi-item">
                <span class="cras-kpi-label"><i class="fas fa-shield-alt"></i> CKPN MO</span>
                <strong class="cras-kpi-value" data-cras-kpi="ckpn_mo">-</strong>
            </div>
        </section>

        <section class="cras-workspace">
            <div class="cras-map-shell">
                <div id="crasPortfolioMap" role="application" aria-label="Peta polygon portofolio SSA CRAS"></div>
                <div class="cras-map-loading" data-cras-loading>Memuat polygon dan portofolio CRAS...</div>
                <div class="cras-map-legend">
                    <strong data-cras-legend-title>Baki Debet</strong>
                    <div class="cras-map-legend-scale" aria-hidden="true"></div>
                    <div class="cras-map-legend-labels"><span>Rendah</span><span>Tinggi</span></div>
                </div>
            </div>

            <aside class="cras-side-panel">
                <div class="cras-side-head">
                    <span class="cras-side-eyebrow">Wilayah Aktif</span>
                    <h2 data-cras-detail-title>Seluruh Area 6</h2>
                    <p data-cras-detail-subtitle>Ringkasan seluruh unit kerja yang sesuai filter.</p>
                </div>
                <div class="cras-detail-grid" data-cras-detail-grid></div>
                <div class="cras-ranking-head">
                    <span data-cras-ranking-title>Peringkat Baki Debet</span>
                    <span data-cras-ranking-count>-</span>
                </div>
                <div class="cras-ranking-list" data-cras-ranking></div>
            </aside>
        </section>

        <section class="cras-detail-band">
            <div class="cras-section-head">
                <h2>CKPN, Penanganan Kredit, dan Tunggakan</h2>
                <span data-cras-source-count>-</span>
            </div>
            <div class="cras-secondary-metrics" data-cras-secondary-metrics></div>
        </section>

        <section class="cras-unit-section">
            <div class="cras-section-head">
                <h2>Rincian Unit Kerja</h2>
                <span data-cras-coverage>-</span>
            </div>
            <div class="cras-table-wrap">
                <table class="cras-unit-table">
                    <thead>
                        <tr>
                            <th>Unit Kerja</th>
                            <th>Wilayah</th>
                            <th class="text-right">Plafon</th>
                            <th class="text-right">Baki Debet</th>
                            <th class="text-right">Debitur</th>
                            <th class="text-right" data-cras-table-metric-head>Metrik Peta</th>
                        </tr>
                    </thead>
                    <tbody data-cras-unit-table></tbody>
                </table>
            </div>
        </section>
    @else
        <section class="cras-filter-panel cras-empty">
            <i class="fas fa-map"></i>
            <strong>{{ $payload['message'] ?? 'Data SSA CRAS belum tersedia.' }}</strong>
        </section>
    @endif
</main>

<script type="application/json" id="crasMappingPayload">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet-1.9.4/leaflet.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const app = document.querySelector('[data-cras-map-app]');
    const payloadElement = document.getElementById('crasMappingPayload');
    const mapElement = document.getElementById('crasPortfolioMap');
    if (!app || !payloadElement || !mapElement || typeof window.L === 'undefined') {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(payloadElement.textContent || '{}');
    } catch (_) {
        return;
    }
    if (!payload.ready) {
        return;
    }

    const dataUrl = app.dataset.dataUrl || '';
    const filterElements = Array.from(app.querySelectorAll('[data-cras-filter]'));
    const heatMetric = app.querySelector('[data-cras-heat-metric]');
    const applyButton = app.querySelector('[data-cras-apply]');
    const resetButton = app.querySelector('[data-cras-reset]');
    const loading = app.querySelector('[data-cras-loading]');
    const detailTitle = app.querySelector('[data-cras-detail-title]');
    const detailSubtitle = app.querySelector('[data-cras-detail-subtitle]');
    const detailGrid = app.querySelector('[data-cras-detail-grid]');
    const ranking = app.querySelector('[data-cras-ranking]');
    const rankingTitle = app.querySelector('[data-cras-ranking-title]');
    const rankingCount = app.querySelector('[data-cras-ranking-count]');
    const tableBody = app.querySelector('[data-cras-unit-table]');
    const tableMetricHead = app.querySelector('[data-cras-table-metric-head]');
    const metricDefinitions = () => Array.isArray(payload.metric_definitions) ? payload.metric_definitions : [];
    const metricByKey = () => new Map(metricDefinitions().map(function (metric) { return [String(metric.key), metric]; }));
    const blueScale = ['#e8eef5', '#c8daeb', '#91b8da', '#548fc3', '#1e6bab', '#084b87'];
    const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const compactFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    let geoData = null;
    let geoLayer = null;
    let fullBounds = null;

    const map = window.L.map(mapElement, {
        zoomControl: true,
        attributionControl: true,
        minZoom: 8,
        maxZoom: 13,
        zoomSnap: 0.25,
        preferCanvas: false,
    });
    const renderer = window.L.svg({ padding: 0.5 });
    map.attributionControl.setPrefix(false);
    map.attributionControl.addAttribution('Polygon: Badan Informasi Geospasial');

    function currentMetric() {
        return String(heatMetric?.value || payload.heatmap?.selected || 'baki_debet');
    }

    function metricDefinition(key) {
        return metricByKey().get(String(key)) || { key: key, label: key, format: 'currency' };
    }

    function formatMetric(value, key, compact) {
        const numeric = Number(value || 0);
        const definition = metricDefinition(key);
        if (definition.format === 'count') {
            return numberFormat.format(Math.round(numeric));
        }
        if (!compact) {
            return 'Rp ' + numberFormat.format(Math.round(numeric));
        }

        const absolute = Math.abs(numeric);
        if (absolute >= 1e12) return 'Rp ' + compactFormat.format(numeric / 1e12) + ' T';
        if (absolute >= 1e9) return 'Rp ' + compactFormat.format(numeric / 1e9) + ' M';
        if (absolute >= 1e6) return 'Rp ' + compactFormat.format(numeric / 1e6) + ' Jt';
        return 'Rp ' + numberFormat.format(Math.round(numeric));
    }

    function districtMetrics(code) {
        const totals = {};
        metricDefinitions().forEach(function (metric) { totals[metric.key] = 0; });
        (payload.units || []).forEach(function (unit) {
            const codes = Array.isArray(unit.district_codes) ? unit.district_codes.map(String) : [];
            if (!codes.includes(String(code))) return;
            const divisor = Math.max(1, codes.length);
            metricDefinitions().forEach(function (metric) {
                totals[metric.key] += Number(unit.values?.[metric.key] || 0) / divisor;
            });
        });
        return totals;
    }

    function visibleFeatures() {
        const codes = new Set((payload.units || []).flatMap(function (unit) {
            return Array.isArray(unit.district_codes) ? unit.district_codes.map(String) : [];
        }));
        return (geoData?.features || []).filter(function (feature) {
            return codes.has(String(feature.properties?.KDCPUM || ''));
        });
    }

    function heatMaximum(features) {
        return Math.max(0, ...features.map(function (feature) {
            return Number(districtMetrics(String(feature.properties?.KDCPUM || ''))[currentMetric()] || 0);
        }));
    }

    function heatColor(value, maximum) {
        if (!(value > 0) || !(maximum > 0)) return blueScale[0];
        const ratio = Math.sqrt(Math.min(1, value / maximum));
        return blueScale[Math.min(blueScale.length - 1, Math.floor(ratio * blueScale.length))];
    }

    function districtUnits(code) {
        return (payload.units || []).filter(function (unit) {
            return (unit.district_codes || []).map(String).includes(String(code));
        });
    }

    function tooltipElement(feature, metrics, units) {
        const node = document.createElement('div');
        node.className = 'cras-tooltip';
        const title = document.createElement('strong');
        const region = document.createElement('small');
        title.textContent = String(feature.properties?.WADMKC || 'Kecamatan');
        region.textContent = String(feature.properties?.WADMKK || '-') + ' | ' + units.length + ' unit kerja';
        node.append(title, region);
        ['plafond', 'baki_debet', 'jumlah_debitur', currentMetric()].filter(function (key, index, values) {
            return values.indexOf(key) === index;
        }).forEach(function (key) {
            const row = document.createElement('span');
            row.textContent = metricDefinition(key).label + ': ' + formatMetric(metrics[key], key, true);
            node.appendChild(row);
        });
        return node;
    }

    function renderMap(fit) {
        if (!geoData) return;
        const features = visibleFeatures();
        const maximum = heatMaximum(features);
        if (geoLayer) map.removeLayer(geoLayer);

        geoLayer = window.L.geoJSON({ type: 'FeatureCollection', features: features }, {
            renderer: renderer,
            style: function (feature) {
                const value = Number(districtMetrics(String(feature.properties?.KDCPUM || ''))[currentMetric()] || 0);
                return {
                    color: '#ffffff',
                    weight: 1.2,
                    opacity: 1,
                    fillColor: heatColor(value, maximum),
                    fillOpacity: value > 0 ? 0.93 : 0.58,
                };
            },
            onEachFeature: function (feature, layer) {
                const code = String(feature.properties?.KDCPUM || '');
                const metrics = districtMetrics(code);
                const units = districtUnits(code);
                layer.bindTooltip(tooltipElement(feature, metrics, units), { sticky: true, direction: 'top', opacity: 0.98 });
                layer.on({
                    mouseover: function () {
                        layer.setStyle({ weight: 2.4, color: '#12395f', fillOpacity: 1 });
                        layer.bringToFront();
                    },
                    mouseout: function () { geoLayer.resetStyle(layer); },
                    click: function () {
                        renderDetail(String(feature.properties?.WADMKC || 'Kecamatan'), String(feature.properties?.WADMKK || '-'), metrics);
                        map.fitBounds(layer.getBounds(), { padding: [24, 24], maxZoom: 11.5 });
                    },
                });
            },
        }).addTo(map);

        const bounds = geoLayer.getBounds();
        if (geoLayer.getLayers().length && bounds.isValid()) {
            map.invalidateSize({ pan: false });
            if (fit !== false) map.fitBounds(bounds, { padding: [18, 18], maxZoom: 10.5 });
            if (!fullBounds) {
                fullBounds = bounds.pad(0.12);
                map.setMaxBounds(fullBounds);
            }
            loading?.classList.add('is-hidden');
        } else if (loading) {
            loading.textContent = 'Tidak ada polygon yang sesuai dengan kombinasi filter ini.';
            loading.classList.remove('is-hidden');
        }
    }

    function renderDetail(title, subtitle, metrics) {
        if (detailTitle) detailTitle.textContent = title;
        if (detailSubtitle) detailSubtitle.textContent = subtitle;
        if (!detailGrid) return;
        detailGrid.innerHTML = '';
        ['plafond', 'baki_debet', 'jumlah_debitur', 'ckpn_mo', 'realisasi_ph', 'recovery_total', 'saldo_ph', 'total_tunggakan'].forEach(function (key) {
            const item = document.createElement('div');
            const label = document.createElement('span');
            const value = document.createElement('strong');
            item.className = 'cras-detail-item';
            label.textContent = metricDefinition(key).label;
            value.textContent = formatMetric(metrics?.[key], key, true);
            value.title = formatMetric(metrics?.[key], key, false);
            item.append(label, value);
            detailGrid.appendChild(item);
        });
    }

    function renderKpis() {
        ['plafond', 'baki_debet', 'jumlah_debitur', 'ckpn_mo'].forEach(function (key) {
            const node = app.querySelector('[data-cras-kpi="' + key + '"]');
            if (!node) return;
            node.textContent = formatMetric(payload.metrics?.[key], key, true);
            node.title = formatMetric(payload.metrics?.[key], key, false);
        });
    }

    function renderSecondaryMetrics() {
        const container = app.querySelector('[data-cras-secondary-metrics]');
        if (!container) return;
        container.innerHTML = '';
        ['biaya_ckpn', 'ckpn_mo', 'realisasi_ph', 'recovery_total', 'saldo_ph', 'tunggakan_bunga', 'tunggakan_kecil', 'tunggakan_pokok', 'total_tunggakan', 'jumlah_rekening'].forEach(function (key) {
            const item = document.createElement('div');
            const label = document.createElement('span');
            const value = document.createElement('strong');
            item.className = 'cras-secondary-item';
            label.textContent = metricDefinition(key).label;
            value.textContent = formatMetric(payload.metrics?.[key], key, true);
            value.title = formatMetric(payload.metrics?.[key], key, false);
            item.append(label, value);
            container.appendChild(item);
        });
    }

    function sortedUnits() {
        const key = currentMetric();
        return (payload.units || []).slice().sort(function (left, right) {
            return Number(right.values?.[key] || 0) - Number(left.values?.[key] || 0);
        });
    }

    function focusUnit(unit) {
        const codes = new Set((unit.district_codes || []).map(String));
        const layers = [];
        geoLayer?.eachLayer(function (layer) {
            if (codes.has(String(layer.feature?.properties?.KDCPUM || ''))) layers.push(layer);
        });
        if (!layers.length) return;
        const group = window.L.featureGroup(layers);
        map.fitBounds(group.getBounds(), { padding: [26, 26], maxZoom: 11.5 });
        renderDetail(unit.name, unit.branch, unit.values || {});
    }

    function renderRanking() {
        if (!ranking) return;
        const key = currentMetric();
        const units = sortedUnits().slice(0, 12);
        ranking.innerHTML = '';
        units.forEach(function (unit, index) {
            const row = document.createElement('button');
            const order = document.createElement('span');
            const name = document.createElement('span');
            const value = document.createElement('strong');
            row.type = 'button';
            row.className = 'cras-ranking-row';
            order.className = 'cras-ranking-order';
            name.className = 'cras-ranking-name';
            order.textContent = String(index + 1);
            name.textContent = unit.name;
            value.textContent = formatMetric(unit.values?.[key], key, true);
            row.title = unit.name;
            row.append(order, name, value);
            row.addEventListener('click', function () { focusUnit(unit); });
            ranking.appendChild(row);
        });
        if (rankingTitle) rankingTitle.textContent = 'Peringkat ' + metricDefinition(key).label;
        if (rankingCount) rankingCount.textContent = units.length + ' unit';
    }

    function renderTable() {
        if (!tableBody) return;
        const key = currentMetric();
        if (tableMetricHead) tableMetricHead.textContent = metricDefinition(key).label;
        tableBody.innerHTML = '';
        sortedUnits().forEach(function (unit) {
            const row = document.createElement('tr');
            [
                unit.name,
                unit.branch,
                formatMetric(unit.values?.plafond, 'plafond', true),
                formatMetric(unit.values?.baki_debet, 'baki_debet', true),
                formatMetric(unit.values?.jumlah_debitur, 'jumlah_debitur', true),
                formatMetric(unit.values?.[key], key, true),
            ].forEach(function (text, index) {
                const cell = document.createElement('td');
                cell.textContent = text;
                if (index >= 2) cell.className = 'text-right';
                row.appendChild(cell);
            });
            row.addEventListener('click', function () { focusUnit(unit); });
            tableBody.appendChild(row);
        });
    }

    function renderMeta() {
        const coverage = payload.coverage || {};
        const source = app.querySelector('[data-cras-source-count]');
        const coverageNode = app.querySelector('[data-cras-coverage]');
        const updated = app.querySelector('[data-cras-updated]');
        const legend = app.querySelector('[data-cras-legend-title]');
        if (source) source.textContent = numberFormat.format(Number(coverage.source_row_count || 0)) + ' baris sumber';
        if (coverageNode) coverageNode.textContent = Number(coverage.mapped_unit_count || 0) + '/' + Number(coverage.total_unit_count || 0) + ' unit terpetakan | ' + Number(coverage.mapped_district_count || 0) + ' kecamatan';
        if (updated) updated.textContent = payload.updated_at || '-';
        if (legend) legend.textContent = metricDefinition(currentMetric()).label;
    }

    function renderOverview() {
        const coverage = payload.coverage || {};
        renderDetail(
            'Seluruh Wilayah Terpilih',
            Number(coverage.total_unit_count || 0) + ' unit kerja pada ' + Number(coverage.mapped_district_count || 0) + ' kecamatan',
            payload.metrics || {}
        );
    }

    function renderAll(fit) {
        renderKpis();
        renderSecondaryMetrics();
        renderOverview();
        renderRanking();
        renderTable();
        renderMeta();
        renderMap(fit);
    }

    function setSelectOptions(key, options, selected) {
        const select = filterElements.find(function (element) { return element.dataset.crasFilter === key; });
        if (!select) return;
        select.innerHTML = '';
        (options || []).forEach(function (option) {
            const node = document.createElement('option');
            node.value = String(option.value ?? '');
            node.textContent = String(option.label ?? '-');
            node.selected = node.value === String(selected ?? '');
            select.appendChild(node);
        });
    }

    function syncFilters() {
        const options = payload.filters?.options || {};
        const selected = payload.filters?.selected || {};
        Object.keys(options).forEach(function (key) { setSelectOptions(key, options[key], selected[key]); });
    }

    async function loadData() {
        if (!dataUrl) return;
        loading.textContent = 'Mengagregasi portofolio CRAS...';
        loading.classList.remove('is-hidden');
        applyButton.disabled = true;
        const params = new URLSearchParams();
        filterElements.forEach(function (select) { params.set(select.dataset.crasFilter, select.value); });
        params.set('metric', currentMetric());

        try {
            const response = await fetch(dataUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            const result = await response.json().catch(function () { return {}; });
            if (!response.ok || !result.ready) throw new Error(result.message || 'Data Mapping CRAS gagal dimuat.');
            payload = result;
            syncFilters();
            if (heatMetric) heatMetric.value = result.heatmap?.selected || currentMetric();
            window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
            renderAll(true);
        } catch (error) {
            loading.textContent = error.message || 'Data Mapping CRAS gagal dimuat.';
            loading.classList.remove('is-hidden');
        } finally {
            applyButton.disabled = false;
        }
    }

    applyButton?.addEventListener('click', loadData);
    resetButton?.addEventListener('click', function () {
        filterElements.forEach(function (select) {
            select.value = select.dataset.crasFilter === 'periode'
                ? String(payload.filters?.options?.periode?.[0]?.value || '')
                : 'all';
        });
        if (heatMetric) heatMetric.value = 'baki_debet';
        loadData();
    });
    heatMetric?.addEventListener('change', function () { renderAll(false); });

    fetch(payload.source?.geojson_url || '')
        .then(function (response) {
            if (!response.ok) throw new Error('Polygon wilayah gagal dimuat.');
            return response.json();
        })
        .then(function (data) {
            geoData = data;
            renderAll(true);
        })
        .catch(function (error) {
            loading.textContent = error.message;
            loading.classList.remove('is-hidden');
        });
});
</script>
@endpush
