@extends('layouts.admin')

@section('title', 'KPI RM SME')

@section('content')
@include('report._bri-report-ui')
@php
    $dashboard = $rmSmeDashboard ?? [];
    $stats = $dashboard['stats'] ?? [];
    $branchFilter = $dashboard['branch_filter'] ?? $kpiBranchFilter ?? [];
    $activeBranch = $branchFilter['selected'] ?? 'all';
@endphp

<style>
    .rmsme-page {
        --rmsme-blue: #00529c;
        --rmsme-blue-dark: #073b74;
        --rmsme-cyan: #009fdb;
        --rmsme-amber: #f59e0b;
        --rmsme-green: #07966f;
        --rmsme-red: #dc3545;
        --rmsme-ink: #10213d;
        --rmsme-muted: #607089;
        --rmsme-line: #d9e4f0;
        padding: 1rem;
        color: var(--rmsme-ink);
        min-width: 0;
    }

    .rmsme-page *,
    .rmsme-page *::before,
    .rmsme-page *::after { box-sizing: border-box; }

    .rmsme-hero {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(240px, 360px);
        gap: 1rem;
        overflow: hidden;
        margin-bottom: .85rem;
        padding: 1.2rem;
        border: 1px solid rgba(0, 82, 156, .22);
        border-radius: 16px;
        background: linear-gradient(118deg, #073b74 0%, #00529c 58%, #008cc9 100%);
        box-shadow: 0 18px 42px -30px rgba(7, 59, 116, .7);
        color: #fff;
    }

    .rmsme-hero::after {
        content: '';
        position: absolute;
        right: -72px;
        bottom: -108px;
        width: 300px;
        height: 300px;
        border: 38px solid rgba(255, 255, 255, .09);
        border-radius: 50%;
        pointer-events: none;
    }

    .rmsme-hero-copy { position: relative; z-index: 1; min-width: 0; }
    .rmsme-eyebrow {
        display: flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .35rem;
        color: #8ee4ff;
        font-size: .7rem;
        font-weight: 900;
        text-transform: uppercase;
    }
    .rmsme-hero h1 { margin: 0; font-size: clamp(1.45rem, 2vw, 2rem); font-weight: 900; letter-spacing: 0; }
    .rmsme-hero p { max-width: 760px; margin: .45rem 0 0; color: rgba(255,255,255,.82); font-size: .85rem; line-height: 1.5; }
    .rmsme-hero-facts {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 12px;
        background: rgba(255,255,255,.1);
        backdrop-filter: blur(10px);
    }
    .rmsme-hero-fact { min-width: 0; padding: .8rem; }
    .rmsme-hero-fact:nth-child(odd) { border-right: 1px solid rgba(255,255,255,.16); }
    .rmsme-hero-fact:nth-child(-n+2) { border-bottom: 1px solid rgba(255,255,255,.16); }
    .rmsme-hero-fact span { display: block; color: rgba(255,255,255,.7); font-size: .65rem; font-weight: 800; text-transform: uppercase; }
    .rmsme-hero-fact strong { display: block; overflow: hidden; margin-top: .2rem; font-size: 1rem; font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }

    .rmsme-toolbar,
    .rmsme-panel,
    .rmsme-stat,
    .rmsme-chart,
    .rmsme-branch-card {
        border: 1px solid var(--rmsme-line);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 14px 30px -26px rgba(15, 23, 42, .3);
    }

    .rmsme-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .7rem; margin-bottom: .85rem; padding: .7rem; }
    .rmsme-tabs, .rmsme-actions, .rmsme-mode-switch { display: flex; flex-wrap: wrap; gap: .4rem; }
    .rmsme-tab,
    .rmsme-action,
    .rmsme-mode-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        min-height: 44px;
        padding: .5rem .75rem;
        border: 1px solid #cddbea;
        border-radius: 8px;
        background: #f6f9fc;
        color: #315373;
        font-size: .76rem;
        font-weight: 850;
        text-decoration: none;
        cursor: pointer;
    }
    .rmsme-tab.active, .rmsme-mode-button.active, .rmsme-action.primary { border-color: var(--rmsme-blue); background: var(--rmsme-blue); color: #fff; }
    .rmsme-tab:hover, .rmsme-action:hover { color: #fff; background: var(--rmsme-blue-dark); text-decoration: none; }

    .rmsme-panel { margin-bottom: .85rem; overflow: hidden; }
    .rmsme-panel-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem .95rem; border-bottom: 1px solid var(--rmsme-line); background: #f6f9fc; }
    .rmsme-panel-title { display: flex; align-items: center; gap: .55rem; min-width: 0; }
    .rmsme-panel-icon { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border-radius: 8px; background: var(--rmsme-blue); color: #fff; }
    .rmsme-panel-title h2 { margin: 0; font-size: .92rem; font-weight: 900; }
    .rmsme-panel-title span { display: block; margin-top: .1rem; color: var(--rmsme-muted); font-size: .69rem; }
    .rmsme-panel-body { padding: .9rem; }

    .rmsme-filters { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: .7rem; }
    .rmsme-filter { min-width: 0; }
    .rmsme-filter label { display: block; margin: 0 0 .3rem; color: #52657c; font-size: .67rem; font-weight: 900; text-transform: uppercase; }
    .rmsme-filter select {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: .45rem .65rem;
        border: 1px solid #c8d7e8;
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--rmsme-ink);
        font-size: .8rem;
        font-weight: 750;
    }
    .rmsme-filter select:focus { border-color: var(--rmsme-blue); box-shadow: 0 0 0 3px rgba(0,82,156,.12); }
    .rmsme-periods { margin-top: .7rem; padding-top: .7rem; border-top: 1px dashed #cfdae7; }

    .rmsme-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .7rem; margin-bottom: .85rem; }
    .rmsme-stat { position: relative; min-width: 0; overflow: hidden; padding: .85rem .9rem .85rem 1rem; border-left: 4px solid var(--tone, var(--rmsme-blue)); }
    .rmsme-stat::after { content: ''; position: absolute; right: -24px; bottom: -36px; width: 92px; height: 92px; border: 15px solid var(--tone, var(--rmsme-blue)); border-radius: 50%; opacity: .1; }
    .rmsme-stat span { display: block; color: var(--rmsme-muted); font-size: .65rem; font-weight: 900; text-transform: uppercase; }
    .rmsme-stat strong { display: block; overflow-wrap: anywhere; margin-top: .28rem; font-size: 1.3rem; font-weight: 900; line-height: 1.15; }
    .rmsme-stat small { display: block; overflow-wrap: anywhere; margin-top: .3rem; color: #52657c; font-size: .69rem; line-height: 1.35; }

    .rmsme-table-shell { width: 100%; overflow: auto; border-top: 1px solid var(--rmsme-line); scrollbar-color: #88a8cb #edf3f8; }
    .rmsme-table { width: 100%; min-width: 1180px; border-collapse: separate; border-spacing: 0; font-size: .74rem; }
    .rmsme-table th { padding: .65rem .55rem; border-right: 1px solid rgba(255,255,255,.13); background: var(--rmsme-blue-dark); color: #fff; font-size: .65rem; font-weight: 900; text-align: center; text-transform: uppercase; white-space: nowrap; }
    .rmsme-table thead tr:nth-child(2) th { background: var(--rmsme-blue); }
    .rmsme-table td { padding: .58rem .55rem; border-right: 1px solid #e2eaf3; border-bottom: 1px solid #e2eaf3; color: #32465d; text-align: right; white-space: nowrap; }
    .rmsme-individual-table { min-width: 1840px; }
    .rmsme-individual-table td:first-child { text-align: center; }
    .rmsme-individual-table td:nth-child(2),
    .rmsme-summary-table td:nth-child(2),
    .rmsme-summary-table td:nth-child(3),
    .rmsme-summary-table td:nth-child(4) { text-align: left; }
    .rmsme-table tbody tr:nth-child(even) td { background: #f8fbfe; }
    .rmsme-table tbody tr:hover td { background: #edf6ff; }
    .rmsme-kpi-name { color: var(--rmsme-ink); font-weight: 850; }
    .rmsme-kpi-group { display: block; margin-top: .15rem; color: var(--rmsme-muted); font-size: .62rem; }
    .rmsme-value-good { color: var(--rmsme-green) !important; font-weight: 900; }
    .rmsme-value-bad { color: var(--rmsme-red) !important; font-weight: 900; }
    .rmsme-value-neutral { color: var(--rmsme-muted) !important; }

    .rmsme-charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin-bottom: .85rem; }
    .rmsme-chart { min-width: 0; overflow: hidden; }
    .rmsme-chart.wide { grid-column: 1 / -1; }
    .rmsme-chart-head { padding: .75rem .85rem; border-bottom: 1px solid var(--rmsme-line); }
    .rmsme-chart-head strong { display: block; font-size: .83rem; font-weight: 900; }
    .rmsme-chart-head span { color: var(--rmsme-muted); font-size: .66rem; }
    .rmsme-chart-canvas { position: relative; height: 280px; padding: .7rem; }

    .rmsme-branch-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; margin-bottom: .85rem; }
    .rmsme-branch-card { min-width: 0; overflow: hidden; }
    .rmsme-branch-head { padding: .75rem .8rem; background: var(--rmsme-blue-dark); color: #fff; }
    .rmsme-branch-head strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .8rem; }
    .rmsme-branch-score { display: flex; align-items: flex-end; justify-content: space-between; gap: .5rem; padding: .75rem .8rem; }
    .rmsme-branch-score strong { color: var(--rmsme-blue); font-size: 1.5rem; font-weight: 900; }
    .rmsme-branch-score span { color: var(--rmsme-muted); font-size: .64rem; }
    .rmsme-status-strip { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); border-top: 1px solid var(--rmsme-line); }
    .rmsme-status-strip div { padding: .55rem .25rem; text-align: center; }
    .rmsme-status-strip div + div { border-left: 1px solid var(--rmsme-line); }
    .rmsme-status-strip strong { display: block; font-size: .83rem; }
    .rmsme-status-strip span { color: var(--rmsme-muted); font-size: .56rem; text-transform: uppercase; }

    .rmsme-empty { padding: 3rem 1rem; color: var(--rmsme-muted); text-align: center; }
    .rmsme-empty i { display: block; margin-bottom: .6rem; color: #9eb2c8; font-size: 2rem; }
    .rmsme-view[hidden] { display: none !important; }

    @media (max-width: 1199.98px) {
        .rmsme-branch-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rmsme-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 991.98px) {
        .rmsme-hero { grid-template-columns: 1fr; }
        .rmsme-hero-facts { max-width: none; }
        .rmsme-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rmsme-charts { grid-template-columns: 1fr; }
        .rmsme-chart.wide { grid-column: auto; }
    }
    @media (max-width: 767.98px) {
        .rmsme-page { padding: .6rem; }
        .rmsme-hero { padding: .95rem; border-radius: 12px; }
        .rmsme-toolbar, .rmsme-panel-body { padding: .65rem; }
        .rmsme-tabs, .rmsme-actions, .rmsme-mode-switch { width: 100%; }
        .rmsme-tab, .rmsme-action, .rmsme-mode-button { flex: 1 1 auto; min-width: 0; }
        .rmsme-filters, .rmsme-stats, .rmsme-branch-grid { grid-template-columns: 1fr; }
        .rmsme-chart-canvas { height: 250px; }
        .rmsme-panel-head { align-items: flex-start; flex-direction: column; }
    }
    @media (max-width: 420px) {
        .rmsme-hero-facts { grid-template-columns: 1fr; }
        .rmsme-hero-fact:nth-child(n) { border-right: 0; border-bottom: 1px solid rgba(255,255,255,.16); }
        .rmsme-hero-fact:last-child { border-bottom: 0; }
        .rmsme-tab span { display: none; }
    }
</style>

<main class="rmsme-page" id="rmsme-dashboard">
    <section class="rmsme-hero">
        <div class="rmsme-hero-copy">
            <div class="rmsme-eyebrow"><i class="fas fa-chart-line"></i> Performance Management</div>
            <h1>Dashboard KPI RM SME</h1>
            <p>Monitoring tujuh indikator utama RM Small berbasis histori bulanan, target KPI, dan roster aktif dari Google Sheets.</p>
        </div>
        <div class="rmsme-hero-facts">
            <div class="rmsme-hero-fact"><span>Wilayah</span><strong>{{ $stats['scope_label'] ?? 'Area 6' }}</strong></div>
            <div class="rmsme-hero-fact"><span>Posisi Data</span><strong>{{ $stats['latest_period_label'] ?? '-' }}</strong></div>
            <div class="rmsme-hero-fact"><span>RM Aktif</span><strong>{{ number_format((int) ($stats['rm_count'] ?? 0), 0, ',', '.') }}</strong></div>
            <div class="rmsme-hero-fact"><span>Rata-rata Skor</span><strong>{{ number_format((float) ($stats['average_score'] ?? 0), 2, ',', '.') }}</strong></div>
        </div>
    </section>

    <nav class="rmsme-toolbar" aria-label="Navigasi KPI">
        <div class="rmsme-tabs">
            @foreach($sheetOptions as $key => $option)
                <a class="rmsme-tab {{ $selectedSheetKey === $key ? 'active' : '' }}" href="{{ route('report.dashboard-almafacts.kpi', array_filter(['sheet' => $key, 'periode' => $selectedPeriod, 'cabang' => $activeBranch !== 'all' ? $activeBranch : null])) }}" @if($selectedSheetKey === $key) aria-current="page" @endif>
                    <i class="{{ $option['icon'] ?? 'fas fa-table' }}"></i><span>{{ $option['label'] }}</span>
                </a>
            @endforeach
        </div>
        <div class="rmsme-actions">
            <a class="rmsme-action primary" href="{{ route('report.dashboard-almafacts.kpi', array_filter(['sheet' => 'rm-sme', 'periode' => $selectedPeriod, 'cabang' => $activeBranch !== 'all' ? $activeBranch : null, 'refresh' => 1])) }}"><i class="fas fa-sync-alt"></i> Perbarui</a>
            <a class="rmsme-action" href="{{ $spreadsheetUrl }}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Spreadsheet</a>
        </div>
    </nav>

    @if($error)
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>{{ $error }}</div>
    @endif

    <section class="rmsme-panel">
        <header class="rmsme-panel-head">
            <div class="rmsme-panel-title">
                <span class="rmsme-panel-icon"><i class="fas fa-sliders-h"></i></span>
                <div><h2>Ruang Analisis</h2><span>Pilih tampilan, wilayah, RM, dan periode pembanding.</span></div>
            </div>
            <div class="rmsme-mode-switch" role="tablist" aria-label="Mode analisis">
                <button type="button" class="rmsme-mode-button active" data-rmsme-mode="individual"><i class="fas fa-user-tie"></i> Dashboard Individu</button>
                <button type="button" class="rmsme-mode-button" data-rmsme-mode="summary"><i class="fas fa-users"></i> Summary Kinerja</button>
            </div>
        </header>
        <div class="rmsme-panel-body" id="rmsme-individual-filters">
            <div class="rmsme-filters">
                <div class="rmsme-filter">
                    <label for="rmsme-branch">Kantor Cabang</label>
                    <select id="rmsme-branch" @disabled(!empty($branchFilter['locked']))></select>
                </div>
                <div class="rmsme-filter"><label for="rmsme-unit">Unit Kerja</label><select id="rmsme-unit"></select></div>
                <div class="rmsme-filter"><label for="rmsme-person">Nama RM</label><select id="rmsme-person"></select></div>
            </div>
            <div class="rmsme-filters rmsme-periods">
                <div class="rmsme-filter"><label for="rmsme-period-base">Baseline</label><select id="rmsme-period-base"></select></div>
                <div class="rmsme-filter"><label for="rmsme-period-previous">Periode Sebelumnya</label><select id="rmsme-period-previous"></select></div>
                <div class="rmsme-filter"><label for="rmsme-period-current">Periode Berjalan</label><select id="rmsme-period-current"></select></div>
            </div>
        </div>
    </section>

    <section class="rmsme-view" data-rmsme-view="individual">
        <div class="rmsme-stats">
            <article class="rmsme-stat" style="--tone:#00529c"><span>Total Skor</span><strong id="rmsme-total-score">-</strong><small id="rmsme-score-caption">Akumulasi bobot KPI</small></article>
            <article class="rmsme-stat" style="--tone:#07966f"><span>Status KPI</span><strong id="rmsme-performance-status">-</strong><small id="rmsme-status-caption">Target posisi berjalan</small></article>
            <article class="rmsme-stat" style="--tone:#009fdb"><span>KPI Terkuat</span><strong id="rmsme-top-kpi">-</strong><small id="rmsme-top-caption">Pencapaian tertinggi</small></article>
            <article class="rmsme-stat" style="--tone:#f59e0b"><span>Prioritas Perbaikan</span><strong id="rmsme-priority-kpi">-</strong><small id="rmsme-priority-caption">Pencapaian terendah</small></article>
        </div>

        <section class="rmsme-panel">
            <header class="rmsme-panel-head">
                <div class="rmsme-panel-title"><span class="rmsme-panel-icon"><i class="fas fa-bullseye"></i></span><div><h2>Detail Pencapaian KPI</h2><span id="rmsme-profile-caption">Pilih RM untuk melihat perbandingan.</span></div></div>
            </header>
            <div class="rmsme-table-shell">
                <table class="rmsme-table rmsme-individual-table">
                    <thead>
                        <tr><th rowspan="2">No</th><th rowspan="2">KPI</th><th rowspan="2">Bobot</th><th colspan="3">Posisi</th><th colspan="2">Delta</th><th colspan="2">RKA</th><th colspan="2">Delta RKA</th><th colspan="2">Pencapaian</th><th rowspan="2">Skor</th></tr>
                        <tr><th>Baseline</th><th>Sebelumnya</th><th>Berjalan</th><th>YTD</th><th>MTD</th><th>Berjalan</th><th>Des 2026</th><th>Berjalan</th><th>Des 2026</th><th>Berjalan</th><th>Des 2026</th></tr>
                    </thead>
                    <tbody id="rmsme-kpi-body"></tbody>
                </table>
            </div>
        </section>

        <div class="rmsme-charts">
            <article class="rmsme-chart"><header class="rmsme-chart-head"><strong>Tren Pertumbuhan Kredit</strong><span>Avg Balance dan Posisi OS Small</span></header><div class="rmsme-chart-canvas"><canvas id="rmsme-growth-chart"></canvas></div></article>
            <article class="rmsme-chart"><header class="rmsme-chart-head"><strong>Komposisi Kualitas Kredit</strong><span>Posisi Lancar, SML, dan NPL</span></header><div class="rmsme-chart-canvas"><canvas id="rmsme-quality-chart"></canvas></div></article>
            <article class="rmsme-chart"><header class="rmsme-chart-head"><strong>Tren Rasio SML</strong><span>Persentase SML terhadap total portofolio</span></header><div class="rmsme-chart-canvas"><canvas id="rmsme-sml-chart"></canvas></div></article>
            <article class="rmsme-chart"><header class="rmsme-chart-head"><strong>Tren Rasio NPL</strong><span>Persentase NPL terhadap total portofolio</span></header><div class="rmsme-chart-canvas"><canvas id="rmsme-npl-chart"></canvas></div></article>
        </div>
    </section>

    <section class="rmsme-view" data-rmsme-view="summary" hidden>
        <div class="rmsme-branch-grid" id="rmsme-branch-grid"></div>
        <section class="rmsme-panel">
            <header class="rmsme-panel-head">
                <div class="rmsme-panel-title"><span class="rmsme-panel-icon"><i class="fas fa-award"></i></span><div><h2>Ranking RM Aktif</h2><span>Skor posisi terakhir untuk setiap RM dalam cakupan akses.</span></div></div>
            </header>
            <div class="rmsme-table-shell">
                <table class="rmsme-table rmsme-summary-table" style="min-width:1900px">
                    <thead>
                        <tr><th rowspan="2">No</th><th rowspan="2">Cabang</th><th rowspan="2">Unit Kerja</th><th rowspan="2">Nama RM</th><th rowspan="2">JG</th>@foreach(($dashboard['metrics'] ?? []) as $metric)<th colspan="2">{{ $metric['label'] }}</th>@endforeach<th rowspan="2">Total Skor</th></tr>
                        <tr>@foreach(($dashboard['metrics'] ?? []) as $metric)<th>% Capai</th><th>Skor</th>@endforeach</tr>
                    </thead>
                    <tbody id="rmsme-summary-body"></tbody>
                </table>
            </div>
        </section>
    </section>
</main>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dashboard = @json($dashboard);
    const profiles = dashboard.profiles || {};
    const metrics = dashboard.metrics || [];
    const periods = dashboard.periods || [];
    const filters = dashboard.filters || { branches: {} };
    const branchSelect = document.getElementById('rmsme-branch');
    const unitSelect = document.getElementById('rmsme-unit');
    const personSelect = document.getElementById('rmsme-person');
    const periodBase = document.getElementById('rmsme-period-base');
    const periodPrevious = document.getElementById('rmsme-period-previous');
    const periodCurrent = document.getElementById('rmsme-period-current');
    const charts = {};

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
        });
    }

    function setOptions(select, options, selected) {
        if (!select) return;
        select.innerHTML = options.map(function (option) {
            return '<option value="' + escapeHtml(option.value) + '"' + (String(option.value) === String(selected) ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
        }).join('');
    }

    function periodDefaults() {
        const keys = periods.map(function (period) { return period.key; });
        const december = keys.find(function (key) { return key === '2025-12-31'; }) || keys[0] || '';
        return {
            base: december,
            previous: keys[Math.max(0, keys.length - 2)] || december,
            current: dashboard.latest_period || keys[keys.length - 1] || december
        };
    }

    function initialisePeriods() {
        const options = periods.map(function (period) { return { value: period.key, label: period.label }; });
        const defaults = periodDefaults();
        setOptions(periodBase, options, defaults.base);
        setOptions(periodPrevious, options, defaults.previous);
        setOptions(periodCurrent, options, defaults.current);
    }

    function initialiseBranches() {
        const branches = Object.keys(filters.branches || {}).map(function (raw) {
            return { value: raw, label: filters.branches[raw].label || raw };
        });
        const selected = branches.some(function (branch) { return branch.value === @json($activeBranch); })
            ? @json($activeBranch)
            : (branches[0] ? branches[0].value : '');
        setOptions(branchSelect, branches, selected);
        refreshUnits();
    }

    function refreshUnits() {
        const branch = filters.branches[branchSelect.value] || { units: {} };
        const units = Object.keys(branch.units || {}).map(function (raw) {
            return { value: raw, label: branch.units[raw].label || raw };
        });
        setOptions(unitSelect, units, units[0] ? units[0].value : '');
        refreshPeople();
    }

    function refreshPeople() {
        const branch = filters.branches[branchSelect.value] || { units: {} };
        const unit = (branch.units || {})[unitSelect.value] || { profiles: [] };
        const people = (unit.profiles || []).map(function (id) {
            const profile = profiles[id] || {};
            return { value: id, label: profile.name ? profile.name + ' (' + (profile.jg || '-') + ')' : id };
        });
        const preferred = people.some(function (person) { return person.value === dashboard.initial_profile; }) ? dashboard.initial_profile : (people[0] ? people[0].value : '');
        setOptions(personSelect, people, preferred);
        renderIndividual();
    }

    function metricValue(profile, period, metricKey, field) {
        return profile && profile.history && profile.history[period] && profile.history[period].metrics && profile.history[period].metrics[metricKey]
            ? profile.history[period].metrics[metricKey][field]
            : null;
    }

    function formatMetric(value, metric) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
        if (metric && metric.percent) return (Number(value) * 100).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '%';
        return Number(value).toLocaleString('id-ID', {minimumFractionDigits: metric && metric.decimals ? metric.decimals : 0, maximumFractionDigits: metric && metric.decimals ? metric.decimals : 0});
    }

    function scoreMetric(value, target, metric) {
        if (value === null || target === null || value === undefined || target === undefined) return {achievement:null, score:0, achieved:null};
        let achievement = null;
        if (metric.reverse) {
            achievement = Number(value) !== 0 ? Math.min(Math.abs(Number(target) / Number(value)), 1.1) : (Number(target) !== 0 ? 1.1 : null);
        } else {
            achievement = Number(target) !== 0 ? Math.min(Number(value) / Number(target), 1.1) : null;
        }
        return {
            achievement: achievement,
            score: achievement === null ? 0 : Number(metric.weight) * achievement * 100,
            achieved: metric.reverse ? Number(value) <= Number(target) : Number(value) >= Number(target)
        };
    }

    function renderIndividual() {
        const profile = profiles[personSelect.value];
        const body = document.getElementById('rmsme-kpi-body');
        if (!profile) {
            body.innerHTML = '<tr><td colspan="15" class="rmsme-empty"><i class="fas fa-user-slash"></i>Tidak ada RM aktif dalam filter ini.</td></tr>';
            updateSummaryCards([], 0);
            renderCharts(null);
            return;
        }

        const rows = [];
        let total = 0;
        metrics.forEach(function (metric) {
            const base = metricValue(profile, periodBase.value, metric.key, 'value');
            const previous = metricValue(profile, periodPrevious.value, metric.key, 'value');
            const current = metricValue(profile, periodCurrent.value, metric.key, 'value');
            const currentTarget = metricValue(profile, periodCurrent.value, metric.key, 'target');
            const yearEndTarget = profile.year_end_targets && profile.year_end_targets[metric.key]
                ? profile.year_end_targets[metric.key].target
                : null;
            const scored = scoreMetric(current, currentTarget, metric);
            const yearEndScored = scoreMetric(current, yearEndTarget, metric);
            const ytd = current !== null && base !== null ? Number(current) - Number(base) : null;
            const mtd = current !== null && previous !== null ? Number(current) - Number(previous) : null;
            const currentGap = current !== null && currentTarget !== null ? Number(current) - Number(currentTarget) : null;
            const yearEndGap = current !== null && yearEndTarget !== null ? Number(current) - Number(yearEndTarget) : null;
            total += scored.score;
            rows.push({metric:metric, base:base, previous:previous, current:current, currentTarget:currentTarget, yearEndTarget:yearEndTarget, ytd:ytd, mtd:mtd, currentGap:currentGap, yearEndGap:yearEndGap, scored:scored, yearEndScored:yearEndScored});
        });

        body.innerHTML = rows.map(function (row, index) {
            const tone = row.scored.achieved === null ? 'rmsme-value-neutral' : (row.scored.achieved ? 'rmsme-value-good' : 'rmsme-value-bad');
            const yearEndTone = row.yearEndScored.achieved === null ? 'rmsme-value-neutral' : (row.yearEndScored.achieved ? 'rmsme-value-good' : 'rmsme-value-bad');
            const movementTone = function (value) {
                if (value === null) return 'rmsme-value-neutral';
                const favourable = row.metric.reverse ? value <= 0 : value >= 0;
                return favourable ? 'rmsme-value-good' : 'rmsme-value-bad';
            };
            return '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td><span class="rmsme-kpi-name">' + escapeHtml(row.metric.label) + '</span><span class="rmsme-kpi-group">' + escapeHtml(row.metric.group) + '</span></td>' +
                '<td>' + (Number(row.metric.weight) * 100).toLocaleString('id-ID') + '%</td>' +
                '<td>' + formatMetric(row.base, row.metric) + '</td>' +
                '<td>' + formatMetric(row.previous, row.metric) + '</td>' +
                '<td class="' + tone + '">' + formatMetric(row.current, row.metric) + '</td>' +
                '<td class="' + movementTone(row.ytd) + '">' + formatMetric(row.ytd, row.metric) + '</td>' +
                '<td class="' + movementTone(row.mtd) + '">' + formatMetric(row.mtd, row.metric) + '</td>' +
                '<td>' + formatMetric(row.currentTarget, row.metric) + '</td>' +
                '<td>' + formatMetric(row.yearEndTarget, row.metric) + '</td>' +
                '<td class="' + movementTone(row.currentGap) + '">' + formatMetric(row.currentGap, row.metric) + '</td>' +
                '<td class="' + movementTone(row.yearEndGap) + '">' + formatMetric(row.yearEndGap, row.metric) + '</td>' +
                '<td class="' + tone + '">' + (row.scored.achievement === null ? '-' : (row.scored.achievement * 100).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%') + '</td>' +
                '<td class="' + yearEndTone + '">' + (row.yearEndScored.achievement === null ? '-' : (row.yearEndScored.achievement * 100).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%') + '</td>' +
                '<td class="' + tone + '">' + row.scored.score.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td></tr>';
        }).join('');

        document.getElementById('rmsme-profile-caption').textContent = profile.name + ' | ' + profile.unit + ' | ' + profile.branch;
        updateSummaryCards(rows, total);
        renderCharts(profile);
    }

    function updateSummaryCards(rows, total) {
        const ranked = rows.filter(function (row) { return row.scored.achievement !== null; }).sort(function (a, b) { return b.scored.achievement - a.scored.achievement; });
        const achieved = rows.filter(function (row) { return row.scored.achieved === true; }).length;
        const notAchieved = rows.filter(function (row) { return row.scored.achieved === false; }).length;
        const priorities = ranked.filter(function (row) { return row.scored.achieved === false; }).sort(function (a, b) { return a.scored.achievement - b.scored.achievement; });
        document.getElementById('rmsme-total-score').textContent = total ? total.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) : '-';
        document.getElementById('rmsme-performance-status').textContent = rows.length ? achieved + ' Tercapai / ' + notAchieved + ' Belum' : '-';
        document.getElementById('rmsme-top-kpi').textContent = ranked[0] ? ranked[0].metric.label : '-';
        document.getElementById('rmsme-priority-kpi').textContent = priorities[0] ? priorities[0].metric.label : (rows.length ? 'Semua Target Tercapai' : '-');
        document.getElementById('rmsme-top-caption').textContent = ranked[0] ? (ranked[0].scored.achievement * 100).toLocaleString('id-ID', {maximumFractionDigits:2}) + '% dari target' : 'Belum ada data';
        document.getElementById('rmsme-priority-caption').textContent = priorities[0] ? (priorities[0].scored.achievement * 100).toLocaleString('id-ID', {maximumFractionDigits:2}) + '% dari target' : (rows.length ? 'Seluruh KPI memenuhi target posisi' : 'Belum ada data');
    }

    function chartConfig(type, labels, datasets, percent) {
        return {
            type:type, data: {labels:labels, datasets:datasets},
            options: {
                responsive:true, maintainAspectRatio:false, interaction:{mode:'index', intersect:false},
                plugins:{legend:{position:'bottom', labels:{usePointStyle:true, boxWidth:8, font:{size:10, weight:'bold'}}}},
                scales:{x:{grid:{display:false}, ticks:{font:{size:10}}}, y:{beginAtZero:false, grid:{color:'#e8eef5'}, ticks:{font:{size:10}, callback:function(value){return percent ? Number(value).toLocaleString('id-ID') + '%' : Number(value).toLocaleString('id-ID');}}}}
            }
        };
    }

    function drawChart(key, canvasId, config) {
        if (typeof Chart === 'undefined') return;
        if (charts[key]) charts[key].destroy();
        const canvas = document.getElementById(canvasId);
        if (canvas) charts[key] = new Chart(canvas.getContext('2d'), config);
    }

    function renderCharts(profile) {
        const labels = periods.map(function (period) { return period.short_label; });
        const values = function (resolver) { return periods.map(function (period) { return profile ? resolver(profile.history[period.key] || {}) : null; }); };
        drawChart('growth', 'rmsme-growth-chart', chartConfig('bar', labels, [
            {label:'Avg Balance', data:values(function (item) { return item.metrics && item.metrics.avg_balance ? item.metrics.avg_balance.value : null; }), borderColor:'#00529c', backgroundColor:'#00529c'},
            {label:'OS Small', data:values(function (item) { return item.metrics && item.metrics.os_small ? item.metrics.os_small.value : null; }), borderColor:'#00a0dc', backgroundColor:'#00a0dc'}
        ], false));
        drawChart('quality', 'rmsme-quality-chart', chartConfig('line', labels, [
            {label:'Lancar', data:values(function (item) { return item.quality ? item.quality.lancar : null; }), borderColor:'#07966f', pointBackgroundColor:'#07966f', tension:.25, spanGaps:true},
            {label:'SML', data:values(function (item) { return item.quality ? item.quality.sml : null; }), borderColor:'#f59e0b', pointBackgroundColor:'#f59e0b', tension:.25, spanGaps:true},
            {label:'NPL', data:values(function (item) { return item.quality ? item.quality.npl : null; }), borderColor:'#dc3545', pointBackgroundColor:'#dc3545', tension:.25, spanGaps:true}
        ], false));
        drawChart('sml', 'rmsme-sml-chart', chartConfig('line', labels, [
            {label:'Rasio SML', data:values(function (item) { const q=item.quality||{}; const total=Number(q.lancar||0)+Number(q.sml||0)+Number(q.npl||0); return total ? Number(q.sml||0)/total*100 : null; }), borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,.08)', pointBackgroundColor:'#f59e0b', tension:.25, spanGaps:true}
        ], true));
        drawChart('npl', 'rmsme-npl-chart', chartConfig('line', labels, [
            {label:'Rasio NPL', data:values(function (item) { const q=item.quality||{}; const total=Number(q.lancar||0)+Number(q.sml||0)+Number(q.npl||0); return total ? Number(q.npl||0)/total*100 : null; }), borderColor:'#dc3545', backgroundColor:'rgba(220,53,69,.08)', pointBackgroundColor:'#dc3545', tension:.25, spanGaps:true}
        ], true));
    }

    function renderSummary() {
        const branchGrid = document.getElementById('rmsme-branch-grid');
        branchGrid.innerHTML = (dashboard.branch_analysis || []).map(function (branch) {
            return '<article class="rmsme-branch-card"><header class="rmsme-branch-head"><strong>' + escapeHtml(branch.branch) + '</strong></header>' +
                '<div class="rmsme-branch-score"><div><strong>' + Number(branch.average_score).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</strong><span>Rata-rata skor</span></div><div><span>Top RM</span><br><b>' + escapeHtml(branch.top_rm) + '</b></div></div>' +
                '<div class="rmsme-status-strip"><div><strong>' + branch.excellent + '</strong><span>Sangat baik</span></div><div><strong>' + branch.good + '</strong><span>Baik</span></div><div><strong>' + branch.fair + '</strong><span>Cukup</span></div><div><strong>' + branch.attention + '</strong><span>Perhatian</span></div></div></article>';
        }).join('') || '<div class="rmsme-empty"><i class="fas fa-chart-bar"></i>Belum ada ringkasan cabang.</div>';

        const body = document.getElementById('rmsme-summary-body');
        body.innerHTML = (dashboard.summary_rows || []).map(function (row, index) {
            const scores = metrics.map(function (metric) {
                const score = row.scores && row.scores[metric.key] ? row.scores[metric.key] : {};
                const tone = score.achieved === null || score.achieved === undefined ? 'rmsme-value-neutral' : (score.achieved ? 'rmsme-value-good' : 'rmsme-value-bad');
                return '<td class="' + tone + '">' + (score.achievement === null || score.achievement === undefined ? '-' : (Number(score.achievement) * 100).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%') + '</td>' +
                    '<td class="' + tone + '">' + Number(score.score || 0).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>';
            }).join('');
            return '<tr><td>' + (index + 1) + '</td><td>' + escapeHtml(row.branch) + '</td><td>' + escapeHtml(row.unit) + '</td><td>' + escapeHtml(row.name) + '</td><td>' + escapeHtml(row.jg) + '</td>' + scores + '<td class="rmsme-value-good">' + Number(row.total_score).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td></tr>';
        }).join('') || '<tr><td colspan="20" class="rmsme-empty"><i class="fas fa-users-slash"></i>Belum ada data RM aktif.</td></tr>';
    }

    document.querySelectorAll('[data-rmsme-mode]').forEach(function (button) {
        button.addEventListener('click', function () {
            const mode = button.dataset.rmsmeMode;
            document.querySelectorAll('[data-rmsme-mode]').forEach(function (item) { item.classList.toggle('active', item === button); });
            document.querySelectorAll('[data-rmsme-view]').forEach(function (view) { view.hidden = view.dataset.rmsmeView !== mode; });
            document.getElementById('rmsme-individual-filters').hidden = mode !== 'individual';
            if (mode === 'summary') renderSummary(); else renderIndividual();
        });
    });

    branchSelect.addEventListener('change', refreshUnits);
    unitSelect.addEventListener('change', refreshPeople);
    personSelect.addEventListener('change', renderIndividual);
    [periodBase, periodPrevious, periodCurrent].forEach(function (select) { select.addEventListener('change', renderIndividual); });

    initialisePeriods();
    initialiseBranches();
    renderSummary();
});
</script>
@endsection
