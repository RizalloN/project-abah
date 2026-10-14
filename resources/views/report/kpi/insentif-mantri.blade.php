@extends('layouts.admin')

@section('title', 'Insentif Mantri')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

@php
    $juta = fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.');
    $rupiah = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    $pct = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.').'%';
    $months = $window['months'] ?? [];
    $mobMonths = $window['mob_months'] ?? [];
    $summaryCount = max(1, $summary['count']);
    $monthStatusLabel = ['final' => 'Final', 'berjalan' => 'Berjalan', 'belum' => 'Belum berjalan'];
@endphp

<style>
    .im-page {
        --im-ink: #082b59;
        --im-muted: #526987;
        --im-line: #dbe5f3;
        --im-blue: #0857c3;
        --im-blue-soft: #eef4ff;
        --im-green: #15803d;
        --im-green-soft: #dcfce7;
        --im-red: #b91c1c;
        --im-red-soft: #fee2e2;
        --im-amber: #a16207;
        --im-amber-soft: #fef3c7;
        color: var(--im-ink);
    }

    .im-hero {
        position: relative;
        margin-bottom: 1.25rem;
        padding: 1.75rem 2rem;
        border-radius: 16px;
        background: linear-gradient(135deg, #042a5f 0%, #0857c3 100%);
        color: #fff;
        overflow: hidden;
    }

    .im-hero h1 { margin: 0; font-size: 1.9rem; font-weight: 800; letter-spacing: -0.01em; }
    .im-hero p { margin: .4rem 0 0; color: rgba(255, 255, 255, .88); font-weight: 500; max-width: 62rem; }
    .im-hero .im-hero-tag {
        display: inline-flex; gap: .4rem; align-items: center; margin-bottom: .6rem;
        padding: .2rem .7rem; border-radius: 999px; background: rgba(255, 255, 255, .14);
        font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    }

    .im-badges { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }
    .im-badge {
        display: inline-flex; align-items: center; gap: .4rem; padding: .4rem .75rem;
        border: 1px solid var(--im-line); border-radius: 999px; background: #fff;
        font-size: .8rem; font-weight: 600; color: var(--im-muted);
    }
    .im-badge strong { color: var(--im-ink); font-weight: 800; }

    .im-alert {
        display: flex; gap: .75rem; align-items: flex-start; margin-bottom: 1rem; padding: .85rem 1rem;
        border-radius: 12px; border: 1px solid; font-size: .85rem; font-weight: 500;
    }
    .im-alert.warn { background: var(--im-amber-soft); border-color: #fcd34d; color: #78350f; }
    .im-alert.info { background: var(--im-blue-soft); border-color: #bfd4f5; color: #0b3a7a; }

    .im-tiles { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
    .im-tile {
        padding: 1rem 1.1rem; border: 1px solid var(--im-line); border-radius: 14px; background: #fff;
    }
    .im-tile-label { font-size: .72rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--im-muted); }
    .im-tile-value { margin-top: .35rem; font-size: 1.6rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .im-tile-sub { margin-top: .3rem; font-size: .78rem; color: var(--im-muted); font-weight: 500; }
    .im-tile.accent { background: linear-gradient(180deg, #f0fdf4 0%, #fff 100%); border-color: #bbf7d0; }
    .im-tile.accent .im-tile-value { color: var(--im-green); }

    .im-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.2fr); gap: 1rem; margin-bottom: 1.25rem; }
    .im-panel { padding: 1.1rem 1.2rem; border: 1px solid var(--im-line); border-radius: 14px; background: #fff; }
    .im-panel h3 { margin: 0 0 .75rem; font-size: .95rem; font-weight: 800; }
    .im-panel .im-panel-note { margin: .6rem 0 0; font-size: .75rem; color: var(--im-muted); }

    .im-quadrant { display: grid; grid-template-columns: auto 1fr 1fr; grid-template-rows: 1fr 1fr auto; gap: .4rem; }
    .im-quadrant .axis { font-size: .7rem; font-weight: 700; color: var(--im-muted); display: flex; align-items: center; justify-content: center; }
    .im-quadrant .axis.y { writing-mode: vertical-rl; transform: rotate(180deg); }
    .im-q { padding: .65rem .75rem; border-radius: 10px; border: 1px solid var(--im-line); }
    .im-q .roman { font-size: .7rem; font-weight: 800; color: var(--im-muted); }
    .im-q .num { font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .im-q .share { font-size: .75rem; color: var(--im-muted); font-weight: 600; }
    .im-q.good { background: var(--im-green-soft); border-color: #86efac; }
    .im-q.bad { background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 6px, #eef2f7 6px, #eef2f7 12px); }

    .im-mini-table { width: 100% !important; min-width: 0 !important; font-size: .8rem; border-collapse: collapse; }
    .im-panel .table-responsive, .im-panel { overflow: visible; }
    .im-mini-table th, .im-mini-table td { padding: .4rem .45rem; border-bottom: 1px solid #edf2f9; }
    .im-mini-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--im-muted); font-weight: 800; }
    .im-mini-table td.num, .im-mini-table th.num { text-align: right; font-variant-numeric: tabular-nums; }

    .im-pill { display: inline-flex; align-items: center; gap: .25rem; padding: .12rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 800; white-space: nowrap; }
    .im-pill.ok { background: var(--im-green-soft); color: var(--im-green); }
    .im-pill.no { background: var(--im-red-soft); color: var(--im-red); }
    .im-pill.run { background: var(--im-amber-soft); color: var(--im-amber); }
    .im-pill.idle { background: #f1f5f9; color: #64748b; }

    .im-toolbar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; justify-content: space-between; margin-bottom: .85rem; }
    .im-toolbar .im-toolbar-left, .im-toolbar .im-toolbar-right { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .im-search { min-width: 240px; min-height: 40px; border-radius: 10px; border: 1px solid #cbd5e1; padding: .4rem .75rem; font-weight: 600; }
    .im-search:focus, .im-view-filter:focus { outline: none; border-color: var(--im-blue); box-shadow: 0 0 0 3px rgba(8, 87, 195, .15); }
    .im-view-filter { min-height: 40px; border-radius: 10px; border: 1px solid #cbd5e1; padding: .4rem .6rem; font-weight: 600; background: #fff; }
    .im-btn-outline {
        display: inline-flex; align-items: center; gap: .4rem; min-height: 40px; padding: .4rem .85rem;
        border-radius: 10px; border: 1px solid #bfd4f5; background: #fff; color: var(--im-blue); font-weight: 700; font-size: .82rem;
    }
    .im-btn-outline:hover, .im-btn-outline:focus-visible { background: var(--im-blue-soft); color: var(--im-blue); text-decoration: none; }

    .im-table-wrap { overflow: auto; max-height: 72vh; border: 1px solid var(--im-line); border-radius: 12px; }
    .im-table { width: 100%; min-width: 1680px; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
    .im-table thead th {
        position: sticky; top: 0; z-index: 2; background: #0b3d84; color: #fff; font-weight: 700;
        font-size: .72rem; text-align: center; vertical-align: middle; padding: .5rem .45rem; border-right: 1px solid rgba(255, 255, 255, .15);
    }
    .im-table thead tr:nth-child(2) th { top: 33px; background: #1559b5; }
    .im-table tbody td { padding: .45rem .5rem; border-bottom: 1px solid #edf2f9; border-right: 1px solid #f1f5f9; vertical-align: middle; }
    .im-table tbody tr:hover td { background: #f8fbff; }
    .im-table td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .im-table td.center { text-align: center; }
    .im-table td.name { font-weight: 700; min-width: 180px; }
    .im-table td.name small { display: block; color: var(--im-muted); font-weight: 600; }
    .im-table td.cell-ok { background: #f0fdf4; color: var(--im-green); font-weight: 700; }
    .im-table td.cell-no { background: #fef2f2; color: var(--im-red); font-weight: 700; }
    .im-table td.cell-muted { color: #94a3b8; }
    .im-table tfoot td { position: sticky; bottom: 0; background: #eaf1fc; font-weight: 800; padding: .5rem; border-top: 2px solid #0b3d84; }
    .im-progress { position: relative; height: 6px; margin-top: .25rem; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
    .im-progress > span { position: absolute; inset: 0 auto 0 0; border-radius: 999px; background: var(--im-blue); }
    .im-progress.done > span { background: #16a34a; }
    .im-empty { padding: 2.5rem 1rem; text-align: center; color: var(--im-muted); font-weight: 600; }

    .im-rules { margin-top: 1.25rem; }
    .im-rules summary { cursor: pointer; font-weight: 800; font-size: .9rem; }
    .im-rules ol { margin: .75rem 0 0; padding-left: 1.2rem; font-size: .82rem; color: #334155; }
    .im-rules li { margin-bottom: .35rem; }

    @media (max-width: 1399.98px) {
        .im-tiles { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .im-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        .im-grid .im-panel:last-child { grid-column: 1 / -1; }
    }

    @media (max-width: 767.98px) {
        .im-hero { padding: 1.25rem 1rem; }
        .im-hero h1 { font-size: 1.45rem; }
        .im-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .im-grid { grid-template-columns: minmax(0, 1fr); }
        .im-search { min-width: 0; width: 100%; }
    }

    @media (prefers-reduced-motion: reduce) {
        .im-page * { transition: none !important; animation: none !important; }
    }
</style>

<div class="loan-dashboard im-page pt-4 px-3">
    <div class="im-hero">
        <span class="im-hero-tag"><i class="fas fa-award" aria-hidden="true"></i> KPI &middot; Segmen Mikro</span>
        <h1>Insentif Mantri {{ $window['label'] ?? '' }}</h1>
        <p>
            Monitoring syarat insentif Mantri pekerja tetap: rata-rata <em>Net Disbursement</em> 3 bulan
            &ge; Rp800 juta <strong>dan</strong> kualitas <em>vintage</em> MOB 6 &le; 1%.
        </p>
    </div>

    <div class="card loan-shell mb-3">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('report.kpi.insentif-mantri') }}" id="im-filter-form">
                <div class="row loan-filter-grid">
                    <div class="col-xl-3 col-lg-6">
                        <label class="loan-filter-label" for="im-periode">POSISI DATA</label>
                        <select id="im-periode" name="periode" class="form-control loan-filter-control">
                            @forelse ($periods as $period)
                                <option value="{{ $period }}" @selected($period === $selectedPeriod)>{{ \Carbon\Carbon::parse($period)->locale('id')->translatedFormat('d F Y') }}</option>
                            @empty
                                <option value="">Belum ada data</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-6">
                        <label class="loan-filter-label" for="im-cabang">KANTOR CABANG</label>
                        <select id="im-cabang" name="cabang" class="form-control loan-filter-control">
                            @if (count($branchOptions) > 1)
                                <option value="ALL" @selected($filters['cabang'] === 'ALL')>Semua Cabang</option>
                            @endif
                            @foreach ($branchOptions as $branch)
                                <option value="{{ $branch }}" @selected($filters['cabang'] === $branch)>{{ $branch }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <label class="loan-filter-label" for="im-unit">UNIT KERJA</label>
                        <select id="im-unit" name="unit" class="form-control loan-filter-control" data-selected="{{ $filters['unit'] }}">
                            <option value="ALL">Semua Unit</option>
                            @foreach (($filters['cabang'] === 'ALL' ? collect($unitsByBranch)->flatten()->unique()->sort() : collect($unitsByBranch[$filters['cabang']] ?? [])) as $unit)
                                <option value="{{ $unit }}" @selected($filters['unit'] === $unit)>{{ $unit }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-6">
                        <label class="loan-filter-label" for="im-status">STATUS PEKERJA</label>
                        <select id="im-status" name="status" class="form-control loan-filter-control">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary font-weight-bold w-100" style="border-radius: 12px; min-height: 44px;">
                            <i class="fas fa-filter mr-2" aria-hidden="true"></i>Tampilkan
                        </button>
                    </div>
                </div>
            </form>

            @if ($window)
                <div class="im-badges">
                    <span class="im-badge"><i class="far fa-calendar-alt text-primary" aria-hidden="true"></i> Periode penilaian: <strong>{{ $window['label'] }} ({{ \Carbon\Carbon::parse($window['start'])->locale('id')->translatedFormat('M') }} &ndash; {{ \Carbon\Carbon::parse($window['end'])->locale('id')->translatedFormat('M Y') }})</strong></span>
                    <span class="im-badge"><i class="fas fa-database text-primary" aria-hidden="true"></i> Posisi: <strong>{{ $selectedPeriodLabel }}</strong> &middot; Sumber <strong>{{ $positionSource }}</strong></span>
                    <span class="im-badge"><i class="fas fa-hourglass-half text-warning" aria-hidden="true"></i> Bulan berjalan: <strong>{{ $window['elapsed_months'] }} dari 3</strong></span>
                </div>
            @endif
        </div>
    </div>

    @if ($freshness['pending_lw321'])
        <div class="im-alert warn" role="status">
            <i class="fas fa-sync-alt mt-1" aria-hidden="true"></i>
            <div>LW321PN posisi <strong>{{ $freshness['latest_lw321'] }}</strong> lebih baru dari Daily Loan Dinamis ({{ $freshness['latest_daily'] ?? '-' }}) namun belum selesai dimaterialisasi. Data akan muncul otomatis setelah sinkronisasi import selesai.</div>
        </div>
    @endif

    @if ($outsideRoster && $outsideRoster['no_pn_deb'] > 0)
        <div class="im-alert info" role="status">
            <i class="fas fa-info-circle mt-1" aria-hidden="true"></i>
            <div><strong>{{ number_format($outsideRoster['no_pn_deb'], 0, ',', '.') }} rekening</strong> (ND Rp{{ $juta($outsideRoster['no_pn_nd']) }} Jt) di cakupan akses Anda belum memiliki PN prakarsa/pengelola pada posisi ini, sehingga belum dapat diatribusikan ke Mantri. Umumnya terisi pada posisi data berikutnya.</div>
        </div>
    @endif

    @if (! $window)
        <div class="card loan-table-shell"><div class="card-body im-empty">Data Daily Loan Dinamis belum tersedia.</div></div>
    @else
        <div class="im-tiles">
            <div class="im-tile">
                <div class="im-tile-label">Mantri dinilai</div>
                <div class="im-tile-value">{{ number_format($summary['count'], 0, ',', '.') }}</div>
                <div class="im-tile-sub">{{ $filters['status'] === 'tetap' ? 'Pekerja tetap' : 'Seluruh status' }}</div>
            </div>
            <div class="im-tile">
                <div class="im-tile-label">Avg ND &ge; 800 Jt</div>
                <div class="im-tile-value">{{ number_format($summary['nd_ok'], 0, ',', '.') }}</div>
                <div class="im-tile-sub">{{ $pct($summary['nd_ok'] / $summaryCount * 100, 1) }} mantri</div>
            </div>
            <div class="im-tile">
                <div class="im-tile-label">MOB 6 &le; 1%</div>
                <div class="im-tile-value">{{ number_format($summary['mob_ok'], 0, ',', '.') }}</div>
                <div class="im-tile-sub">Agregat {{ $pct($summary['mob_plafon'] > 0 ? $summary['mob_pemburukan'] / $summary['mob_plafon'] * 100 : 0) }}</div>
            </div>
            <div class="im-tile accent">
                <div class="im-tile-label">Berhak insentif</div>
                <div class="im-tile-value">{{ number_format($summary['eligible'], 0, ',', '.') }}</div>
                <div class="im-tile-sub">{{ $pct($summary['eligible'] / $summaryCount * 100, 1) }} mantri (posisi saat ini)</div>
            </div>
            <div class="im-tile">
                <div class="im-tile-label">Estimasi insentif</div>
                <div class="im-tile-value" style="font-size: 1.3rem;">{{ $rupiah($summary['nominal']) }}</div>
                <div class="im-tile-sub">Proyeksi run-rate: {{ $rupiah($summary['run_rate_nominal']) }}</div>
            </div>
        </div>

        <div class="im-grid">
            <div class="im-panel">
                <h3>Kuadran Produktivitas Mantri</h3>
                <div class="im-quadrant" role="table" aria-label="Kuadran avg Net Disbursement terhadap MOB 6">
                    <div class="axis y" role="rowheader">&ge; 800 Jt</div>
                    @foreach ([['I', 'good', 'Berhak (ND ok, MOB ok)'], ['II', 'bad', 'ND ok, MOB > 1%']] as [$roman, $class, $title])
                        <div class="im-q {{ $class }}" title="{{ $title }}">
                            <div class="roman">{{ $roman }}</div>
                            <div class="num">{{ number_format($summary['quadrants'][$roman], 0, ',', '.') }}</div>
                            <div class="share">{{ $pct($summary['quadrants'][$roman] / $summaryCount * 100) }}</div>
                        </div>
                    @endforeach
                    <div class="axis y" role="rowheader">&lt; 800 Jt</div>
                    @foreach ([['III', 'bad', 'ND kurang, MOB ok'], ['IV', 'bad', 'ND kurang, MOB > 1%']] as [$roman, $class, $title])
                        <div class="im-q {{ $class }}" title="{{ $title }}">
                            <div class="roman">{{ $roman }}</div>
                            <div class="num">{{ number_format($summary['quadrants'][$roman], 0, ',', '.') }}</div>
                            <div class="share">{{ $pct($summary['quadrants'][$roman] / $summaryCount * 100) }}</div>
                        </div>
                    @endforeach
                    <div></div>
                    <div class="axis">MOB 6 &le; 1%</div>
                    <div class="axis">MOB 6 &gt; 1%</div>
                </div>
                <p class="im-panel-note">Arsiran = belum memenuhi syarat. Avg ND = total ND {{ $window['label'] }} &divide; 3.</p>
            </div>

            <div class="im-panel">
                <h3>Tiering Insentif</h3>
                <table class="im-mini-table">
                    <thead><tr><th>Avg ND</th><th class="num">Tarif</th><th class="num">Mantri</th><th class="num">Nominal (Jt)</th></tr></thead>
                    <tbody>
                        @foreach (array_reverse($tiers) as $tier)
                            <tr>
                                <td>{{ $tier['label'] }}</td>
                                <td class="num">{{ number_format($tier['rate'] * 100, 1, ',', '.') }}%</td>
                                <td class="num">{{ number_format($summary['tiers'][$tier['key']]['count'], 0, ',', '.') }}</td>
                                <td class="num">{{ $juta($summary['tiers'][$tier['key']]['nominal']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="im-panel-note">Nominal = Avg ND &times; tarif tier (contoh: Rp1.012,8 Jt &times; 0,5% = Rp5,06 Jt). Dibayarkan setelah periode penilaian berakhir.</p>
            </div>

            <div class="im-panel">
                <h3>Jendela Penilaian</h3>
                <table class="im-mini-table">
                    <thead><tr><th>Net Disbursement</th><th>Posisi</th><th class="num">Status</th></tr></thead>
                    <tbody>
                        @foreach ($months as $month)
                            <tr>
                                <td>{{ $month['label'] }}</td>
                                <td>{{ $month['position'] ?? '-' }}</td>
                                <td class="num"><span class="im-pill {{ ['final' => 'ok', 'berjalan' => 'run', 'belum' => 'idle'][$month['status']] }}">{{ $monthStatusLabel[$month['status']] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <table class="im-mini-table mt-2">
                    <thead><tr><th>Booking (MOB 6)</th><th>Diukur</th><th class="num">MOB</th></tr></thead>
                    <tbody>
                        @foreach ($mobMonths as $mobMonth)
                            <tr>
                                <td>{{ $mobMonth['label'] }}</td>
                                <td>{{ $mobMonth['measure_label'] }} <small class="text-muted">({{ $mobMonth['measure_position'] ?? '-' }})</small></td>
                                <td class="num"><span class="im-pill {{ $mobMonth['final'] ? 'ok' : 'run' }}">{{ $mobMonth['final'] ? 'MOB 6' : 'MOB '.$mobMonth['mob'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card loan-table-shell">
            <div class="card-body p-4">
                <div class="im-toolbar">
                    <div class="im-toolbar-left">
                        <h5 class="mb-0 font-weight-bold">Simulasi Insentif per Mantri</h5>
                        <span class="loan-table-badge"><i class="fas fa-users" aria-hidden="true"></i> <span id="im-visible-count">{{ number_format($rows->count(), 0, ',', '.') }}</span> mantri</span>
                    </div>
                    <div class="im-toolbar-right">
                        <label class="sr-only" for="im-search">Cari mantri</label>
                        <input type="search" id="im-search" class="im-search" placeholder="Cari nama / PN / unit…" autocomplete="off">
                        <label class="sr-only" for="im-view">Saring status</label>
                        <select id="im-view" class="im-view-filter">
                            <option value="all">Semua</option>
                            <option value="eligible">Berhak</option>
                            <option value="not">Belum berhak</option>
                        </select>
                        @php $exportQuery = ['periode' => $selectedPeriod, 'cabang' => $filters['cabang'], 'unit' => $filters['unit'], 'status' => $filters['status']]; @endphp
                        <a class="im-btn-outline" href="{{ route('report.kpi.insentif-mantri.export', $exportQuery + ['jenis' => 'nd']) }}"><i class="fas fa-file-csv" aria-hidden="true"></i> Nominatif ND</a>
                        <a class="im-btn-outline" href="{{ route('report.kpi.insentif-mantri.export', $exportQuery + ['jenis' => 'mob6']) }}"><i class="fas fa-file-csv" aria-hidden="true"></i> Nominatif MOB 6</a>
                    </div>
                </div>

                @if ($rows->isEmpty())
                    <div class="im-empty">Tidak ada Mantri untuk filter ini.</div>
                @else
                    <div class="im-table-wrap">
                        <table class="im-table" id="im-table">
                            <thead>
                                <tr>
                                    <th rowspan="2">No</th>
                                    <th rowspan="2">Mantri</th>
                                    <th rowspan="2">Unit Kerja</th>
                                    <th colspan="{{ count($months) + 3 }}">Net Disbursement (Rp Juta)</th>
                                    <th colspan="3">Vintage MOB 6 (Rp Juta)</th>
                                    <th rowspan="2">Berhak</th>
                                    <th rowspan="2">% Insentif</th>
                                    <th rowspan="2">Estimasi Insentif</th>
                                    <th rowspan="2">Proyeksi Run-rate</th>
                                    <th rowspan="2">Keterangan</th>
                                </tr>
                                <tr>
                                    @foreach ($months as $month)
                                        <th>{{ $month['label'] }}</th>
                                    @endforeach
                                    <th>Rata-rata 3 Bln</th>
                                    <th>Capaian vs 800 Jt</th>
                                    <th>Kekurangan ND</th>
                                    <th>Plafond</th>
                                    <th>Pemburukan</th>
                                    <th>MOB 6</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $index => $row)
                                    <tr data-search="{{ strtolower($row['pn'].' '.$row['nama'].' '.$row['unit'].' '.$row['cabang']) }}" data-eligible="{{ $row['eligible'] ? '1' : '0' }}">
                                        <td class="center">{{ $index + 1 }}</td>
                                        <td class="name">{{ $row['nama'] !== '' ? $row['nama'] : '-' }}<small>PN {{ $row['pn'] }} &middot; {{ $row['status_pekerja'] }}</small></td>
                                        <td>{{ $row['unit'] }}<small class="d-block text-muted">{{ $row['cabang'] }}</small></td>
                                        @foreach ($months as $month)
                                            <td class="num {{ $month['status'] === 'belum' ? 'cell-muted' : '' }}" title="{{ $row['nd_deb'][$month['key']] }} rekening">{{ $month['status'] === 'belum' ? '-' : $juta($row['nd'][$month['key']]) }}</td>
                                        @endforeach
                                        <td class="num {{ $row['nd_ok'] ? 'cell-ok' : 'cell-no' }}">{{ $juta($row['nd_avg']) }}</td>
                                        <td class="num" style="min-width: 110px;">
                                            {{ $pct($row['nd_achievement'], 1) }}
                                            <div class="im-progress {{ $row['nd_ok'] ? 'done' : '' }}" aria-hidden="true"><span style="width: {{ min(100, max(0, $row['nd_achievement'])) }}%"></span></div>
                                        </td>
                                        <td class="num">{{ $row['nd_gap_total'] > 0 ? $juta($row['nd_gap_total']) : '-' }}</td>
                                        <td class="num">{{ $juta($row['mob_plafon']) }}</td>
                                        <td class="num">{{ $juta($row['mob_pemburukan']) }}</td>
                                        <td class="num {{ $row['mob_ok'] ? 'cell-ok' : 'cell-no' }}">{{ $pct($row['mob_ratio']) }}</td>
                                        <td class="center"><span class="im-pill {{ $row['eligible'] ? 'ok' : 'no' }}">{!! $row['eligible'] ? '&#10003; Ya' : '&times; Tidak' !!}</span></td>
                                        <td class="center">{{ $row['rate'] !== null ? number_format($row['rate'] * 100, 1, ',', '.').'%' : '-' }}</td>
                                        <td class="num" style="font-weight: 800;">{{ $row['nominal'] > 0 ? $rupiah($row['nominal']) : '-' }}</td>
                                        <td class="num">{{ $row['run_rate_nominal'] > 0 ? $rupiah($row['run_rate_nominal']) : '-' }}</td>
                                        <td style="min-width: 170px; font-size: .74rem;">
                                            {{ implode(', ', $row['reasons']) }}
                                            @if ($row['tenure_note'])
                                                <span class="d-block text-muted">{{ $row['tenure_note'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">Total ({{ number_format($rows->count(), 0, ',', '.') }} mantri)</td>
                                    @foreach ($months as $month)
                                        <td class="num">{{ $month['status'] === 'belum' ? '-' : $juta($rows->sum(fn ($row) => $row['nd'][$month['key']])) }}</td>
                                    @endforeach
                                    <td class="num">{{ $juta($rows->avg('nd_avg')) }}</td>
                                    <td class="num">-</td>
                                    <td class="num">-</td>
                                    <td class="num">{{ $juta($summary['mob_plafon']) }}</td>
                                    <td class="num">{{ $juta($summary['mob_pemburukan']) }}</td>
                                    <td class="num">{{ $pct($summary['mob_plafon'] > 0 ? $summary['mob_pemburukan'] / $summary['mob_plafon'] * 100 : 0) }}</td>
                                    <td class="center">{{ number_format($summary['eligible'], 0, ',', '.') }}</td>
                                    <td></td>
                                    <td class="num">{{ $rupiah($summary['nominal']) }}</td>
                                    <td class="num">{{ $rupiah($summary['run_rate_nominal']) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="mt-2 mb-0 text-muted" style="font-size: .75rem;">Baris total kolom Average menampilkan rata-rata per mantri. Proyeksi run-rate = total ND &divide; bulan berjalan, sebagai indikasi bila tren bulan berjalan dipertahankan.</p>
                @endif

                <details class="im-rules">
                    <summary>Ketentuan &amp; metodologi perhitungan</summary>
                    <ol>
                        <li><strong>Penerima:</strong> Mantri (bukan Mantri Briguna) segmen mikro, status pekerja tetap (BRIHC pemasar). Mantri kontrak ditampilkan hanya pada opsi "Semua Mantri" dan tidak berhak.</li>
                        <li><strong>Net Disbursement</strong> dihitung per bulan per CIF berdasarkan <strong>PN prakarsa</strong> (bila belum terisi: PN pengelola) untuk realisasi Kupedes, KUR Mikro, dan KPP. Mutasi tetap mengikuti PN pekerja.</li>
                        <li>Baru / lunas putus / pelunasan di bulan berbeda (termasuk musiman): seluruh plafond diakui. Suplesi / pelunasan di bulan yang sama: plafond dikurangi sisa OS saat pelunasan (OS pada posisi terakhir sebelum lunas), minimal Rp0.</li>
                        <li><strong>Average ND</strong> = total ND 3 bulan periode penilaian &divide; 3. Syarat &ge; Rp800 juta.</li>
                        <li><strong>Vintage MOB 6</strong> = plafond rekening yang memburuk (kolektibilitas &ge; 2) pada MOB 6 &divide; total plafond realisasi bulan booking {{ $mobMonths[0]['label'] ?? '' }} &ndash; {{ $mobMonths[count($mobMonths) - 1]['label'] ?? '' }}. Bersifat point-in-time, bukan ever default; booking yang belum mencapai MOB 6 memakai posisi terkini. Syarat &le; 1%.</li>
                        <li><strong>Tiering:</strong> &ge; 800 s.d. 900 Jt = 0,3%; &gt; 900 Jt s.d. 1 M = 0,4%; &gt; 1 M = 0,5% &times; Average ND.</li>
                        <li>Masa kerja &lt; 3 bulan diberikan proporsional (minimal 30 hari) bila TMT jabatan tersedia di BRIHC. Status Insus, hukuman disiplin, dan status aktif saat pembayaran tidak tersedia di data dan perlu verifikasi manual.</li>
                        <li>Sumber data: Daily Loan Dinamis; posisi LW321PN yang lebih baru otomatis dimaterialisasi ke tabel yang sama.</li>
                    </ol>
                </details>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var unitsByBranch = @json($unitsByBranch);
        var branchSelect = document.getElementById('im-cabang');
        var unitSelect = document.getElementById('im-unit');

        function renderUnits() {
            if (!branchSelect || !unitSelect) return;
            var branch = branchSelect.value;
            var selected = unitSelect.value || unitSelect.dataset.selected;
            var units = branch === 'ALL'
                ? Array.from(new Set(Object.values(unitsByBranch).flat())).sort()
                : (unitsByBranch[branch] || []);
            unitSelect.innerHTML = '';
            unitSelect.appendChild(new Option('Semua Unit', 'ALL'));
            units.forEach(function (unit) {
                unitSelect.appendChild(new Option(unit, unit, false, unit === selected));
            });
        }
        if (branchSelect) branchSelect.addEventListener('change', function () { unitSelect.value = 'ALL'; renderUnits(); });

        var table = document.getElementById('im-table');
        var search = document.getElementById('im-search');
        var view = document.getElementById('im-view');
        var counter = document.getElementById('im-visible-count');

        function applyTableFilter() {
            if (!table) return;
            var term = (search.value || '').trim().toLowerCase();
            var mode = view.value;
            var visible = 0;
            table.querySelectorAll('tbody tr').forEach(function (row) {
                var show = (!term || row.dataset.search.indexOf(term) !== -1)
                    && (mode === 'all' || (mode === 'eligible') === (row.dataset.eligible === '1'));
                row.hidden = !show;
                if (show) visible++;
            });
            if (counter) counter.textContent = visible.toLocaleString('id-ID');
        }
        if (search) search.addEventListener('input', applyTableFilter);
        if (view) view.addEventListener('change', applyTableFilter);
    })();
</script>
@endpush
