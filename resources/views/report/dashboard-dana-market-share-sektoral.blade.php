@extends('layouts.admin')

@section('title', $pageTitle ?? 'Marketshare Sektoral')

@php
    $payload = $marketShareSektoral ?? [];
    $rows = $payload['rows'] ?? [];
    $total = $payload['total'] ?? [];
    $scopes = $payload['scopes'] ?? [];
    $selectedScope = $payload['selected_scope'] ?? 'area6';
    $scopeLocked = (bool) ($payload['scope_locked'] ?? false);
    $money = static fn ($value, int $precision = 1): string => is_numeric($value)
        ? number_format((float) $value, $precision, ',', '.')
        : '-';
    $percent = static fn ($value, int $precision = 2): string => is_numeric($value)
        ? number_format((float) $value * 100, $precision, ',', '.') . '%'
        : '-';
    $growthTone = static function ($value): string {
        if (!is_numeric($value) || abs((float) $value) < 0.0000001) return 'is-neutral';
        return (float) $value < 0 ? 'is-negative' : 'is-positive';
    };
@endphp

@section('content')
<main class="mss-page">
    <section class="mss-hero" aria-labelledby="mss-title">
        <div>
            <span class="mss-kicker">Sector intelligence</span>
            <h1 id="mss-title">{{ $payload['title'] ?? 'Marketshare Sektoral' }}</h1>
            <p>{{ $payload['subtitle'] ?? '' }}</p>
        </div>
        <div class="mss-hero-meta">
            <span><i class="far fa-calendar-alt"></i>{{ $payload['period'] ?? 'Maret 2026' }}</span>
            <span><i class="fas fa-map-marker-alt"></i>{{ $payload['selected_scope_label'] ?? '-' }}</span>
            <span><i class="fas fa-coins"></i>{{ $payload['unit'] ?? 'Rp dalam Miliar' }}</span>
        </div>
    </section>

    <section class="mss-filter-card">
        <div class="mss-filter-intro">
            <span class="mss-kicker">Cakupan laporan</span>
            <strong>{{ $scopeLocked ? 'Cabang mengikuti wilayah user' : 'Pilih wilayah analisis' }}</strong>
            <small>{{ $scopeLocked ? 'Pilihan dikunci sesuai otorisasi akun.' : 'Area 6 menampilkan konsolidasi empat cabang.' }}</small>
        </div>
        <form method="GET" action="{{ route('report.dashboard-dana.market-share.sektoral') }}" class="mss-scope-form">
            <label for="mss-cabang">Wilayah</label>
            <div class="mss-select-wrap {{ $scopeLocked ? 'is-locked' : '' }}">
                <i class="fas {{ $scopeLocked ? 'fa-lock' : 'fa-map-marked-alt' }}"></i>
                <select id="mss-cabang" name="cabang" @disabled($scopeLocked) onchange="this.form.submit()">
                    @foreach($scopes as $key => $label)
                        <option value="{{ $key }}" @selected($selectedScope === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit">Terapkan</button></noscript>
        </form>
    </section>

    <section class="mss-kpi-grid" aria-label="Ringkasan posisi sektoral">
        <article class="mss-kpi-card">
            <span class="mss-kpi-icon"><i class="fas fa-landmark"></i></span>
            <div><small>Sudah BRI</small><strong>{{ $money($total['bri_os'] ?? null) }}</strong><em>OS BRI, Rp miliar</em></div>
        </article>
        <article class="mss-kpi-card is-potential">
            <span class="mss-kpi-icon"><i class="fas fa-chart-line"></i></span>
            <div><small>Potensi Belum BRI</small><strong>{{ $money($total['potential_os'] ?? null) }}</strong><em>Rp miliar</em></div>
        </article>
        <article class="mss-kpi-card is-industry">
            <span class="mss-kpi-icon"><i class="fas fa-industry"></i></span>
            <div><small>Total Industri</small><strong>{{ $money($total['industry_os'] ?? null) }}</strong><em>Rp miliar</em></div>
        </article>
        <article class="mss-kpi-card is-share">
            <span class="mss-kpi-icon"><i class="fas fa-percentage"></i></span>
            <div><small>Market Share BRI</small><strong>{{ $percent($total['market_share_os'] ?? null) }}</strong><em>dari total industri</em></div>
        </article>
    </section>

    <section class="mss-panel mss-comparison-panel" aria-labelledby="mss-comparison-title">
        <header class="mss-panel-head mss-comparison-head">
            <div>
                <span class="mss-kicker">Peluang sektoral</span>
                <h2 id="mss-comparison-title">Penetrasi BRI terhadap industri per sektor</h2>
            </div>
            <div class="mss-chart-controls">
                <div class="mss-chart-legend" aria-label="Legenda grafik">
                    <span><i class="is-bri"></i>Penetrasi BRI</span>
                    <span><i class="is-potential"></i>Total industri (100%)</span>
                </div>
                <label class="mss-sort-filter" for="mss-chart-sort">
                    <span>Urutkan</span>
                    <select id="mss-chart-sort">
                        <option value="market_share_os">Market share tertinggi</option>
                        <option value="potential_os">Potensi terbesar</option>
                        <option value="industry_os">Industri terbesar</option>
                        <option value="bri_os">OS BRI terbesar</option>
                    </select>
                </label>
            </div>
        </header>
        <div class="mss-bar-scroller">
            <div class="mss-bar-wrap"><canvas id="mssSectorBar" aria-label="Persentase penetrasi BRI dan total industri per sektor" role="img"></canvas></div>
        </div>
        <footer class="mss-chart-footer">
            <span>Bar biru menunjukkan penetrasi BRI; angka di sisi kanan menunjukkan total industri dalam Rp miliar.</span>
            <strong>{{ count($rows) }} sektor ditampilkan</strong>
        </footer>
    </section>

    <section class="mss-panel mss-table-panel">
        <header class="mss-panel-head mss-table-head">
            <div><span class="mss-kicker">Detail sektoral</span><h2>Seluruh sektor lapangan usaha</h2></div>
            <label class="mss-search" for="mss-sector-search">
                <i class="fas fa-search"></i>
                <input id="mss-sector-search" type="search" placeholder="Cari sektor..." autocomplete="off">
            </label>
        </header>
        <div class="mss-table-wrap" tabindex="0" aria-label="Tabel marketshare sektoral">
            <table class="mss-table" id="mss-sector-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="sector-head">Sektor Lapangan Usaha</th>
                        <th colspan="4" class="opportunity-head">Peluang Pasar</th>
                        <th colspan="4">Kinerja BRI</th>
                    </tr>
                    <tr>
                        <th>Sudah BRI</th><th>Potensi</th><th>Total Industri</th><th>Market Share</th>
                        <th>YoY OS</th><th>YTD OS</th><th>Rasio SML</th><th>Rasio NPL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr data-sector="{{ mb_strtolower((string) $row['sector'], 'UTF-8') }}">
                            <th class="sector-cell">{{ $row['sector'] }}</th>
                            <td>{{ $money($row['bri_os']) }}</td>
                            <td class="potential-cell">{{ $money($row['potential_os']) }}</td>
                            <td>{{ $money($row['industry_os']) }}</td>
                            <td class="mss-share-cell"><strong>{{ $percent($row['market_share_os']) }}</strong><span><i style="width: {{ min(100, max(0, (float) ($row['market_share_os'] ?? 0) * 100)) }}%"></i></span></td>
                            <td class="{{ $growthTone($row['yoy_os']) }}">{{ $percent($row['yoy_os']) }}</td>
                            <td class="{{ $growthTone($row['ytd_os']) }}">{{ $percent($row['ytd_os']) }}</td>
                            <td>{{ $percent($row['bri_sml_ratio']) }}</td>
                            <td>{{ $percent($row['bri_npl_ratio']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="mss-total-row">
                        <th class="sector-cell">Grand Total</th>
                        <td>{{ $money($total['bri_os'] ?? null) }}</td>
                        <td>{{ $money($total['potential_os'] ?? null) }}</td>
                        <td>{{ $money($total['industry_os'] ?? null) }}</td>
                        <td>{{ $percent($total['market_share_os'] ?? null) }}</td>
                        <td class="{{ $growthTone($total['yoy_os'] ?? null) }}">{{ $percent($total['yoy_os'] ?? null) }}</td>
                        <td class="{{ $growthTone($total['ytd_os'] ?? null) }}">{{ $percent($total['ytd_os'] ?? null) }}</td>
                        <td>{{ $percent($total['bri_sml_ratio'] ?? null) }}</td>
                        <td>{{ $percent($total['bri_npl_ratio'] ?? null) }}</td>
                    </tr>
                    <tr class="mss-no-result" hidden><td colspan="9">Sektor tidak ditemukan.</td></tr>
                </tbody>
            </table>
        </div>
        <footer class="mss-table-footer">
            <span><i class="fas fa-info-circle"></i>SML/NPL BRI dihitung terhadap OS BRI; market share dihitung terhadap industri.</span>
            <span><i class="far fa-file-excel"></i>{{ $payload['source'] ?? '-' }}</span>
        </footer>
    </section>
</main>
@endsection

@section('styles')
<style>
    .mss-page { --blue:#075da8; --blue-soft:#dcecff; --ink:#17283d; --line:#d8e1eb; --muted:#64748b; background:#f4f7fa; color:var(--ink); min-height:calc(100vh - 74px); padding:1rem; }
    .mss-hero,.mss-filter-card,.mss-panel,.mss-kpi-card { background:#fff; border:1px solid var(--line); border-radius:8px; box-shadow:0 10px 24px -22px rgba(15,38,67,.48); }
    .mss-hero { align-items:center; border-left:4px solid var(--blue); display:flex; gap:1rem; justify-content:space-between; margin-bottom:.75rem; padding:1rem 1.1rem; }
    .mss-kicker { color:var(--blue); display:block; font-size:.68rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .mss-hero h1 { font-size:1.55rem; font-weight:900; letter-spacing:0; line-height:1.15; margin:.2rem 0 0; }
    .mss-hero p { color:var(--muted); font-size:.84rem; margin:.28rem 0 0; }
    .mss-hero-meta { display:flex; flex-wrap:wrap; gap:.4rem; justify-content:flex-end; }
    .mss-hero-meta span { align-items:center; background:#f7f9fc; border:1px solid #dce4ed; border-radius:6px; color:#465d75; display:inline-flex; font-size:.72rem; font-weight:800; gap:.38rem; padding:.4rem .58rem; white-space:nowrap; }
    .mss-hero-meta i { color:var(--blue); }
    .mss-filter-card { align-items:center; display:flex; gap:1rem; justify-content:space-between; margin-bottom:.75rem; padding:.78rem .9rem; }
    .mss-filter-intro strong { display:block; font-size:.9rem; margin-top:.1rem; }
    .mss-filter-intro small { color:var(--muted); display:block; margin-top:.1rem; }
    .mss-scope-form { min-width:min(100%,330px); }
    .mss-scope-form label,.mss-sort-filter span { color:#50657d; display:block; font-size:.65rem; font-weight:900; margin-bottom:.25rem; text-transform:uppercase; }
    .mss-select-wrap { position:relative; }
    .mss-select-wrap i { color:var(--blue); left:.75rem; pointer-events:none; position:absolute; top:50%; transform:translateY(-50%); z-index:1; }
    .mss-select-wrap select { appearance:none; background:#fff; border:1px solid #b8c7d7; border-radius:6px; color:#1d3f63; font-size:.82rem; font-weight:800; height:40px; padding:0 2rem 0 2.15rem; width:100%; }
    .mss-select-wrap::after { border-left:4px solid transparent; border-right:4px solid transparent; border-top:5px solid #52677e; content:''; pointer-events:none; position:absolute; right:.85rem; top:50%; transform:translateY(-50%); }
    .mss-select-wrap.is-locked select { background:#f1f4f7; color:#53667d; cursor:not-allowed; }
    .mss-kpi-grid { display:grid; gap:.65rem; grid-template-columns:repeat(4,minmax(0,1fr)); margin-bottom:.75rem; }
    .mss-kpi-card { align-items:center; display:flex; gap:.72rem; min-height:88px; min-width:0; padding:.78rem .85rem; }
    .mss-kpi-icon { align-items:center; background:var(--blue-soft); border-radius:6px; color:var(--blue); display:inline-flex; flex:0 0 38px; height:38px; justify-content:center; }
    .mss-kpi-card.is-potential .mss-kpi-icon { background:#eef1f5; color:#50657d; }
    .mss-kpi-card.is-industry .mss-kpi-icon { background:#e9eef3; color:#354b62; }
    .mss-kpi-card.is-share .mss-kpi-icon { background:#e9f4ef; color:#187052; }
    .mss-kpi-card div { min-width:0; }
    .mss-kpi-card small { color:var(--muted); display:block; font-size:.67rem; font-weight:800; }
    .mss-kpi-card strong { color:#173b62; display:block; font-size:1.28rem; font-weight:900; line-height:1.1; margin-top:.14rem; overflow-wrap:anywhere; }
    .mss-kpi-card em { color:#748396; display:block; font-size:.64rem; font-style:normal; margin-top:.18rem; }
    .mss-panel { margin-bottom:.75rem; overflow:hidden; }
    .mss-panel-head { align-items:center; display:flex; gap:1rem; justify-content:space-between; padding:.85rem .95rem; }
    .mss-panel-head h2 { font-size:1rem; font-weight:900; letter-spacing:0; margin:.12rem 0 0; }
    .mss-comparison-head { border-bottom:1px solid var(--line); }
    .mss-chart-controls { align-items:flex-end; display:flex; flex-wrap:wrap; gap:1rem; justify-content:flex-end; }
    .mss-chart-legend { display:flex; flex-wrap:wrap; gap:.75rem; padding-bottom:.45rem; }
    .mss-chart-legend span { align-items:center; color:#4d6176; display:inline-flex; font-size:.72rem; font-weight:800; gap:.35rem; white-space:nowrap; }
    .mss-chart-legend i { background:#0d67b5; border-radius:2px; display:inline-block; height:9px; width:18px; }
    .mss-chart-legend i.is-potential { background:#c4ced9; }
    .mss-sort-filter { margin:0; min-width:190px; }
    .mss-sort-filter select { background:#fff; border:1px solid #b8c7d7; border-radius:6px; color:#294764; font-size:.75rem; font-weight:800; height:36px; padding:0 .55rem; width:100%; }
    .mss-bar-scroller { max-height:690px; overflow-y:auto; scrollbar-color:#aebdcd #eef2f6; scrollbar-width:thin; }
    .mss-bar-wrap { min-height:620px; padding:.75rem .85rem .45rem; position:relative; }
    .mss-chart-footer { align-items:center; border-top:1px solid var(--line); color:#6a7b8e; display:flex; flex-wrap:wrap; font-size:.68rem; gap:.7rem; justify-content:space-between; padding:.62rem .9rem; }
    .mss-chart-footer strong { color:#40566e; }
    .mss-table-head { border-bottom:1px solid var(--line); }
    .mss-search { margin:0; max-width:320px; position:relative; width:100%; }
    .mss-search i { color:#6b7d93; left:.75rem; position:absolute; top:50%; transform:translateY(-50%); }
    .mss-search input { border:1px solid #b8c7d7; border-radius:6px; font-size:.8rem; height:38px; padding:0 .7rem 0 2.15rem; width:100%; }
    .mss-table-wrap { max-height:62vh; min-height:320px; overflow:auto; scrollbar-color:#9db1c6 #edf2f6; scrollbar-width:thin; }
    .mss-table-wrap::-webkit-scrollbar { height:10px; width:10px; }
    .mss-table-wrap::-webkit-scrollbar-track { background:#edf2f6; }
    .mss-table-wrap::-webkit-scrollbar-thumb { background:#9db1c6; border-radius:6px; }
    .mss-table { border-collapse:separate; border-spacing:0; font-size:.75rem; min-width:1180px; width:100%; }
    .mss-table th,.mss-table td { border-bottom:1px solid #dfe6ee; border-right:1px solid #e2e8ef; padding:.55rem .6rem; text-align:right; white-space:nowrap; }
    .mss-table thead th { background:#244b73; color:#fff; font-weight:900; height:40px; position:sticky; text-align:center; top:0; z-index:5; }
    .mss-table thead tr:nth-child(2) th { background:#315d88; top:40px; z-index:6; }
    .mss-table thead .opportunity-head { background:#0b62aa; }
    .mss-table .sector-head,.mss-table .sector-cell { left:0; max-width:320px; min-width:280px; position:sticky; text-align:left; white-space:normal; }
    .mss-table .sector-head { background:#183b5e; z-index:8; }
    .mss-table .sector-cell { background:#fff; color:#1d4167; font-weight:800; line-height:1.3; z-index:2; }
    .mss-table tbody tr:nth-child(even) td,.mss-table tbody tr:nth-child(even) .sector-cell { background:#f8fafc; }
    .mss-table tbody tr:hover td,.mss-table tbody tr:hover .sector-cell { background:#eef5fb; }
    .mss-table .potential-cell { color:#53677c; font-weight:800; }
    .mss-share-cell strong { color:#0a5e9f; display:block; font-size:.72rem; }
    .mss-share-cell span { background:#e5ebf1; border-radius:2px; display:block; height:3px; margin-top:.28rem; min-width:70px; overflow:hidden; }
    .mss-share-cell span i { background:#0d67b5; display:block; height:100%; }
    .mss-table .is-positive { color:#167052; font-weight:800; }
    .mss-table .is-negative { color:#b53a3a; font-weight:800; }
    .mss-table .is-neutral { color:#60738b; }
    .mss-total-row td,.mss-total-row .sector-cell { background:#e6f0fa !important; border-top:1px solid #9db9d5; color:#153f69; font-weight:900; }
    .mss-no-result td { color:#718399; padding:2rem !important; text-align:center; }
    .mss-table-footer { align-items:center; border-top:1px solid var(--line); color:#6b7c92; display:flex; flex-wrap:wrap; font-size:.68rem; gap:.7rem; justify-content:space-between; padding:.65rem .9rem; }
    .mss-table-footer i { color:var(--blue); margin-right:.3rem; }
    @media (max-width:1199.98px) { .mss-kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:767.98px) {
        .mss-page { padding:.65rem; }
        .mss-hero,.mss-filter-card,.mss-panel-head { align-items:stretch; flex-direction:column; }
        .mss-hero-meta { justify-content:flex-start; }
        .mss-scope-form,.mss-sort-filter { min-width:0; width:100%; }
        .mss-chart-controls { align-items:stretch; flex-direction:column; }
        .mss-chart-legend { padding-bottom:0; }
        .mss-bar-scroller { max-height:620px; }
        .mss-bar-wrap { min-height:590px; min-width:660px; }
        .mss-bar-scroller { overflow:auto; }
        .mss-search { max-width:none; }
        .mss-table .sector-head,.mss-table .sector-cell { max-width:230px; min-width:210px; }
    }
    @media (max-width:479.98px) {
        .mss-kpi-grid { grid-template-columns:1fr; }
        .mss-kpi-card { min-height:76px; }
        .mss-hero h1 { font-size:1.35rem; }
        .mss-hero-meta span { white-space:normal; }
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = @json($rows);
    const barCanvas = document.getElementById('mssSectorBar');
    const sortSelect = document.getElementById('mss-chart-sort');
    let barChart = null;

    const formatMoney = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 });
    const formatPercent = value => (Number(value || 0) * 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%';
    const shortLabel = label => label.length > 42 ? label.slice(0, 40) + '...' : label;
    const marketShareLabelsPlugin = {
        id: 'marketShareLabels',
        afterDatasetsDraw(chart) {
            const ranked = chart.$marketShareRows || [];
            const industryBars = chart.getDatasetMeta(0).data || [];
            const briBars = chart.getDatasetMeta(1).data || [];
            const context = chart.ctx;

            context.save();
            context.font = '700 10px Inter, Segoe UI, sans-serif';
            context.textBaseline = 'middle';

            ranked.forEach((row, index) => {
                const industryBar = industryBars[index];
                const briBar = briBars[index];
                if (!industryBar || !briBar) return;

                const percentageText = formatPercent(row.market_share_os);
                const percentageWidth = context.measureText(percentageText).width;
                const briWidth = Math.abs(briBar.x - briBar.base);
                const fitsInsideBri = briWidth >= percentageWidth + 14;

                context.fillStyle = fitsInsideBri ? '#ffffff' : '#173b62';
                context.textAlign = fitsInsideBri ? 'right' : 'left';
                context.fillText(
                    percentageText,
                    fitsInsideBri ? briBar.x - 6 : briBar.x + 6,
                    briBar.y
                );

                context.fillStyle = '#53677c';
                context.textAlign = 'left';
                context.fillText(`${formatMoney(row.industry_os)} M`, Math.max(industryBar.x, briBar.x) + 8, industryBar.y);
            });

            context.restore();
        }
    };

    function renderBar(sortKey) {
        if (!barCanvas || typeof Chart === 'undefined') return;
        const ranked = rows.slice().sort((a, b) => Number(b[sortKey] || 0) - Number(a[sortKey] || 0));
        const maximumPenetration = Math.max(100, ...ranked.map(row => Math.max(0, Number(row.market_share_os || 0) * 100)));
        const axisMaximum = Math.ceil(maximumPenetration / 20) * 20;
        barCanvas.parentElement.style.height = Math.max(620, ranked.length * 34) + 'px';

        if (barChart) barChart.destroy();
        barChart = new Chart(barCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ranked.map(row => shortLabel(row.sector)),
                datasets: [
                    {
                        label: 'Total industri',
                        data: ranked.map(() => 100),
                        backgroundColor: '#c4ced9',
                        borderColor: '#c4ced9',
                        borderWidth: 0,
                        borderRadius: 2,
                        maxBarThickness: 20,
                        grouped: false,
                        order: 2,
                    },
                    {
                        label: 'Penetrasi BRI',
                        data: ranked.map(row => Math.max(0, Number(row.market_share_os || 0) * 100)),
                        backgroundColor: '#0d67b5',
                        borderColor: '#0d67b5',
                        borderWidth: 0,
                        borderRadius: 2,
                        maxBarThickness: 12,
                        grouped: false,
                        order: 1,
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 240 },
                interaction: { intersect: false, mode: 'index' },
                layout: { padding: { right: 92 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: items => ranked[items[0].dataIndex].sector,
                            label: tooltipContext => {
                                const row = ranked[tooltipContext.dataIndex];
                                return tooltipContext.datasetIndex === 1
                                    ? `Penetrasi BRI: ${formatPercent(row.market_share_os)} (${formatMoney(row.bri_os)} miliar)`
                                    : `Total industri: ${formatMoney(row.industry_os)} miliar`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: axisMaximum,
                        grid: { color: '#e7edf3' },
                        ticks: { color: '#687b90', callback: value => value + '%' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#334e6a', font: { size: 10, weight: '600' } }
                    }
                }
            },
            plugins: [marketShareLabelsPlugin]
        });
        barChart.$marketShareRows = ranked;
        barChart.update('none');
    }

    renderBar(sortSelect ? sortSelect.value : 'market_share_os');
    if (sortSelect) sortSelect.addEventListener('change', event => renderBar(event.target.value));

    const search = document.getElementById('mss-sector-search');
    const table = document.getElementById('mss-sector-table');
    if (search && table) {
        search.addEventListener('input', function () {
            const query = this.value.trim().toLocaleLowerCase('id-ID');
            let visible = 0;
            table.querySelectorAll('tbody tr[data-sector]').forEach(row => {
                const match = !query || (row.dataset.sector || '').includes(query);
                row.hidden = !match;
                if (match) visible++;
            });
            const empty = table.querySelector('.mss-no-result');
            if (empty) empty.hidden = visible !== 0;
        });
    }
});
</script>
@endsection
