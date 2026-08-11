@extends('layouts.admin')

@section('title', $pageTitle ?? 'Marketshare Area 6')

@php
    $payload = $marketShareArea6 ?? [];
    $segments = $payload['segments'] ?? [];
    $selected = $payload['selected'] ?? [];
    $headers = $payload['headers'] ?? ['groups' => [], 'columns' => []];
    $rows = $payload['rows'] ?? [];
    $insights = $payload['insights'] ?? ['cards' => [], 'chart' => ['rows' => []]];
    $selectedKey = $selected['key'] ?? 'dpk';
    $segmentsByGroup = collect($segments)->groupBy('group');
    $toneClass = static function (string $value): string {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-' || in_array($trimmed, ['0', '0.00%', '0.0%', '0.00'], true)) {
            return 'is-neutral';
        }

        return str_contains($trimmed, '(') || str_starts_with($trimmed, '-') ? 'is-negative' : 'is-positive';
    };
@endphp

@section('content')
<main class="msa-page">
    <section class="msa-hero" aria-labelledby="msa-title">
        <div class="msa-hero-copy">
            <span class="msa-kicker">Market intelligence</span>
            <h1 id="msa-title">{{ $payload['title'] ?? 'Marketshare Area 6' }}</h1>
            <p>{{ $payload['subtitle'] ?? '' }}</p>
        </div>
        <div class="msa-context" aria-label="Informasi laporan">
            <span><i class="far fa-calendar-alt"></i>{{ $payload['period'] ?? 'Mei 2026' }}</span>
            <span><i class="fas fa-layer-group"></i>{{ $selected['label'] ?? '-' }}</span>
            <span><i class="fas fa-coins"></i>{{ $payload['unit'] ?? 'Rp dalam Miliar' }}</span>
        </div>
    </section>

    <section class="msa-control-card">
        <div class="msa-control-copy">
            <span class="msa-section-label">Tampilan data</span>
            <strong>Pilih indikator market share</strong>
            <small>Tabel, ringkasan, dan grafik akan menyesuaikan pilihan.</small>
        </div>
        <form method="GET" action="{{ route('report.dashboard-dana.market-share.area6') }}" class="msa-filter">
            <label for="marketshare-segmen">Segmen / indikator</label>
            <div class="msa-select-wrap">
                <i class="fas fa-filter" aria-hidden="true"></i>
                <select id="marketshare-segmen" name="segmen" onchange="this.form.submit()">
                    @foreach($segmentsByGroup as $group => $items)
                        <optgroup label="{{ $group }}">
                            @foreach($items as $key => $segment)
                                <option value="{{ $key }}" @selected($selectedKey === $key)>{{ $segment['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit">Terapkan</button></noscript>
        </form>
    </section>

    <section class="msa-summary-grid" aria-label="Ringkasan market share">
        @foreach($insights['cards'] ?? [] as $index => $card)
            <article class="msa-stat-card tone-{{ ($index % 4) + 1 }}">
                <span class="msa-stat-icon"><i class="{{ $card['icon'] ?? 'fas fa-chart-bar' }}"></i></span>
                <div>
                    <small>{{ $card['label'] ?? '-' }}</small>
                    <strong>{{ $card['value'] ?? '-' }}</strong>
                </div>
            </article>
        @endforeach
    </section>

    <section class="msa-visual-grid">
        <article class="msa-panel msa-chart-panel">
            <header class="msa-panel-head">
                <div>
                    <span class="msa-section-label">Perbandingan cabang</span>
                    <h2>{{ data_get($insights, 'chart.title', 'Market share per cabang') }}</h2>
                </div>
                <span class="msa-period-badge">{{ $payload['period'] ?? 'Mei 2026' }}</span>
            </header>
            <div class="msa-chart-wrap">
                @if(!empty(data_get($insights, 'chart.rows', [])))
                    <canvas id="marketShareAreaChart" aria-label="Grafik market share per cabang" role="img"></canvas>
                @else
                    <div class="msa-empty"><i class="fas fa-chart-bar"></i>Data grafik tidak tersedia.</div>
                @endif
            </div>
        </article>

        <aside class="msa-panel msa-guide">
            <span class="msa-section-label">Cara membaca</span>
            <h2>Fokus pada perubahan, bukan angka tunggal</h2>
            <div class="msa-guide-list">
                <div><i class="fas fa-bullseye"></i><span><strong>Market share</strong> menunjukkan porsi BRI terhadap total industri.</span></div>
                <div><i class="fas fa-arrow-up"></i><span><strong>Hijau</strong> menandai nilai atau perubahan positif.</span></div>
                <div><i class="fas fa-arrow-down"></i><span><strong>Merah</strong> menandai nilai atau perubahan negatif.</span></div>
            </div>
            <div class="msa-source"><i class="far fa-file-alt"></i><span>{{ $payload['source'] ?? '-' }}</span></div>
        </aside>
    </section>

    <section class="msa-panel msa-table-panel">
        <header class="msa-panel-head msa-table-heading">
            <div>
                <span class="msa-section-label">Detail lengkap</span>
                <h2>{{ $selected['label'] ?? 'Total DPK' }}</h2>
            </div>
            <span class="msa-scroll-hint"><i class="fas fa-arrows-alt-h"></i>Geser tabel untuk melihat seluruh kolom</span>
        </header>
        <div class="msa-table-wrap" tabindex="0" aria-label="Tabel detail market share Area 6">
            <table class="msa-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="branch-head">Branch Office</th>
                        @foreach($headers['groups'] ?? [] as $group)
                            <th colspan="{{ $group['span'] }}" class="{{ ($group['label'] ?? '') === 'Market Share' ? 'share-head' : 'group-head' }}">
                                {{ $group['label'] }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($headers['columns'] ?? [] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="{{ !empty($row['total']) ? 'area-total-row' : '' }}">
                            <th class="branch-cell">{{ $row['branch'] ?? '-' }}</th>
                            @foreach(($row['values'] ?? []) as $value)
                                <td class="{{ $toneClass((string) $value) }}">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($headers['columns'] ?? []) + 1 }}" class="msa-empty-cell">Data segmen tidak tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection

@section('styles')
<style>
    .msa-page {
        --msa-blue: #0759b8;
        --msa-navy: #123766;
        --msa-line: #d9e4f2;
        --msa-muted: #60738f;
        background: linear-gradient(145deg, #f3f8ff 0%, #f8fafc 46%, #f4f7fb 100%);
        color: #16243a;
        min-height: calc(100vh - 74px);
        padding: 1rem;
    }
    .msa-hero, .msa-control-card, .msa-panel, .msa-stat-card {
        background: rgba(255, 255, 255, .96);
        border: 1px solid var(--msa-line);
        border-radius: 16px;
        box-shadow: 0 18px 45px -38px rgba(20, 51, 91, .62);
    }
    .msa-hero { align-items: center; display: flex; gap: 1rem; justify-content: space-between; margin-bottom: .85rem; overflow: hidden; padding: 1.1rem 1.2rem; position: relative; }
    .msa-hero::before { background: linear-gradient(180deg, #0575e6, #0754a8); content: ''; inset: 0 auto 0 0; position: absolute; width: 5px; }
    .msa-kicker, .msa-section-label { color: var(--msa-blue); display: block; font-size: .7rem; font-weight: 900; letter-spacing: .09em; text-transform: uppercase; }
    .msa-hero h1 { font-size: clamp(1.45rem, 2.3vw, 2rem); font-weight: 900; letter-spacing: -.02em; line-height: 1.1; margin: .2rem 0 0; }
    .msa-hero p { color: var(--msa-muted); font-size: .86rem; margin: .3rem 0 0; max-width: 820px; }
    .msa-context { display: flex; flex-wrap: wrap; gap: .45rem; justify-content: flex-end; }
    .msa-context span, .msa-period-badge { align-items: center; background: #f4f8fd; border: 1px solid #d7e4f3; border-radius: 999px; color: #3f5776; display: inline-flex; font-size: .75rem; font-weight: 800; gap: .4rem; padding: .4rem .65rem; white-space: nowrap; }
    .msa-context i { color: var(--msa-blue); }
    .msa-control-card { align-items: center; display: flex; gap: 1.2rem; justify-content: space-between; margin-bottom: .85rem; padding: .85rem 1rem; }
    .msa-control-copy strong { display: block; font-size: .94rem; margin-top: .1rem; }
    .msa-control-copy small { color: var(--msa-muted); display: block; margin-top: .1rem; }
    .msa-filter { min-width: min(100%, 360px); }
    .msa-filter label { color: #435a78; display: block; font-size: .7rem; font-weight: 900; margin-bottom: .3rem; text-transform: uppercase; }
    .msa-select-wrap { position: relative; }
    .msa-select-wrap i { color: var(--msa-blue); left: .8rem; pointer-events: none; position: absolute; top: 50%; transform: translateY(-50%); }
    .msa-select-wrap select { appearance: none; background: #fff; border: 1px solid #b9cee6; border-radius: 11px; color: #18385e; font-size: .86rem; font-weight: 800; height: 42px; padding: 0 2rem 0 2.35rem; width: 100%; }
    .msa-select-wrap::after { border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 5px solid #436181; content: ''; pointer-events: none; position: absolute; right: .9rem; top: 50%; transform: translateY(-50%); }
    .msa-summary-grid { display: grid; gap: .75rem; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: .85rem; }
    .msa-stat-card { align-items: center; display: flex; gap: .8rem; min-height: 94px; padding: .85rem; }
    .msa-stat-icon { align-items: center; background: #eaf3ff; border-radius: 13px; color: var(--msa-blue); display: inline-flex; flex: 0 0 42px; height: 42px; justify-content: center; }
    .msa-stat-card small { color: var(--msa-muted); display: block; font-size: .72rem; font-weight: 800; }
    .msa-stat-card strong { color: #15365e; display: block; font-size: clamp(1.15rem, 2vw, 1.55rem); font-weight: 900; line-height: 1.1; margin-top: .22rem; }
    .msa-stat-card.tone-2 .msa-stat-icon { background: #eafaf3; color: #087a51; }
    .msa-stat-card.tone-3 .msa-stat-icon { background: #fff6dc; color: #ad6b00; }
    .msa-stat-card.tone-4 .msa-stat-icon { background: #f2edff; color: #6c45bd; }
    .msa-visual-grid { display: grid; gap: .85rem; grid-template-columns: minmax(0, 2fr) minmax(260px, .8fr); margin-bottom: .85rem; }
    .msa-panel { overflow: hidden; }
    .msa-panel-head { align-items: center; display: flex; gap: .8rem; justify-content: space-between; padding: .9rem 1rem; }
    .msa-panel-head h2 { font-size: 1rem; font-weight: 900; margin: .15rem 0 0; }
    .msa-chart-wrap { height: 290px; padding: 0 .8rem .8rem; position: relative; }
    .msa-guide { padding: 1rem; }
    .msa-guide h2 { font-size: 1rem; font-weight: 900; line-height: 1.25; margin: .25rem 0 .8rem; }
    .msa-guide-list { display: grid; gap: .65rem; }
    .msa-guide-list > div { align-items: flex-start; color: #506680; display: flex; font-size: .78rem; gap: .6rem; line-height: 1.35; }
    .msa-guide-list i { align-items: center; background: #edf5ff; border-radius: 9px; color: var(--msa-blue); display: inline-flex; flex: 0 0 30px; height: 30px; justify-content: center; }
    .msa-guide-list strong { color: #254465; }
    .msa-source { align-items: center; background: #f6f9fd; border-radius: 10px; color: #61748e; display: flex; font-size: .72rem; gap: .5rem; margin-top: .9rem; overflow-wrap: anywhere; padding: .65rem; }
    .msa-table-heading { border-bottom: 1px solid var(--msa-line); }
    .msa-scroll-hint { color: #62758e; font-size: .72rem; font-weight: 700; }
    .msa-scroll-hint i { color: var(--msa-blue); margin-right: .35rem; }
    .msa-table-wrap { max-height: calc(100vh - 270px); min-height: 300px; overflow: auto; scrollbar-color: #9bb8da #eef4fa; scrollbar-width: thin; }
    .msa-table-wrap::-webkit-scrollbar { height: 10px; width: 10px; }
    .msa-table-wrap::-webkit-scrollbar-track { background: #eef4fa; }
    .msa-table-wrap::-webkit-scrollbar-thumb { background: #9bb8da; border-radius: 10px; }
    .msa-table { border-collapse: separate; border-spacing: 0; font-size: .78rem; min-width: 1420px; width: 100%; }
    .msa-table th, .msa-table td { border-bottom: 1px solid #dbe5f1; border-right: 1px solid #dbe5f1; padding: .58rem .62rem; text-align: right; white-space: nowrap; }
    .msa-table thead th { background: #124c91; color: #fff; font-weight: 900; height: 42px; position: sticky; text-align: center; top: 0; z-index: 5; }
    .msa-table thead tr:nth-child(2) th { background: #173f72; top: 42px; z-index: 6; }
    .msa-table .share-head { background: #0874c9; }
    .msa-table .branch-head, .msa-table .branch-cell { left: 0; min-width: 170px; position: sticky; text-align: left; }
    .msa-table .branch-head { background: #103760; z-index: 8; }
    .msa-table .branch-cell { background: #fff; color: #173e6c; font-weight: 900; z-index: 2; }
    .msa-table tbody tr:nth-child(even) td, .msa-table tbody tr:nth-child(even) .branch-cell { background: #f8fbff; }
    .msa-table tbody tr:hover td, .msa-table tbody tr:hover .branch-cell { background: #edf5ff; }
    .msa-table .area-total-row td, .msa-table .area-total-row .branch-cell { background: #e4f0ff !important; border-bottom-color: #a8c7ed; border-top: 1px solid #a8c7ed; color: #103f76; font-weight: 900; }
    .msa-table .is-negative { color: #c33131; font-weight: 800; }
    .msa-table .is-positive { color: #08734e; font-weight: 800; }
    .msa-table .is-neutral { color: #5f7087; }
    .msa-empty, .msa-empty-cell { color: #73849a; padding: 2rem !important; text-align: center !important; }
    .msa-empty { align-items: center; display: flex; flex-direction: column; gap: .5rem; height: 100%; justify-content: center; }
    @media (max-width: 1100px) {
        .msa-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .msa-visual-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .msa-page { padding: .7rem; }
        .msa-hero, .msa-control-card, .msa-panel-head { align-items: stretch; flex-direction: column; }
        .msa-context { justify-content: flex-start; }
        .msa-filter { min-width: 0; width: 100%; }
        .msa-scroll-hint { display: block; }
        .msa-table-wrap { max-height: 66vh; }
    }
    @media (max-width: 520px) {
        .msa-summary-grid { grid-template-columns: 1fr; }
        .msa-stat-card { min-height: 82px; }
        .msa-chart-wrap { height: 260px; }
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('marketShareAreaChart');
    const rows = @json(data_get($insights, 'chart.rows', []));
    if (!canvas || !rows.length || typeof Chart === 'undefined') return;

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: rows.map(row => row.branch.replace(/^KC\s+/i, '')),
            datasets: [
                {
                    label: @json(data_get($insights, 'chart.primary_label', 'Periode berjalan')),
                    data: rows.map(row => row.primary),
                    backgroundColor: '#0b6fca',
                    borderRadius: 7,
                    maxBarThickness: 38
                },
                {
                    label: @json(data_get($insights, 'chart.secondary_label', 'Periode pembanding')),
                    data: rows.map(row => row.secondary),
                    backgroundColor: '#a8c8ea',
                    borderRadius: 7,
                    maxBarThickness: 38
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { weight: '600' } } },
                tooltip: { callbacks: { label: context => `${context.dataset.label}: ${context.parsed.y == null ? '-' : context.parsed.y.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%'}` } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#506680', font: { weight: '700' } } },
                y: { beginAtZero: true, grid: { color: '#e4ebf4' }, ticks: { color: '#60738f', callback: value => value + '%' } }
            }
        }
    });
});
</script>
@endsection
