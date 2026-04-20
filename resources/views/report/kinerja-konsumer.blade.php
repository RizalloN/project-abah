@extends('layouts.admin')

@section('title', 'Kinerja Konsumer')

@section('content')
<style>
    .kinerja-konsumer-shell {
        border: 1px solid #cfe0f4;
        border-radius: 18px;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(47, 111, 216, 0.07), transparent 24%),
            linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 20px 36px -28px rgba(15, 23, 42, 0.26);
    }

    .kinerja-konsumer-header {
        padding: 1rem 1.1rem 0.6rem;
        border-bottom: 1px solid #e4edf7;
        background:
            radial-gradient(circle at top right, rgba(25, 103, 210, 0.08), transparent 28%),
            linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    }

    .kinerja-konsumer-title {
        margin: 0;
        font-size: 1.55rem;
        font-weight: 900;
        color: #09386f;
        letter-spacing: 0.01em;
    }

    .kinerja-konsumer-subtitle {
        margin-top: 0.3rem;
        color: #50647f;
        font-size: 0.92rem;
    }

    .kinerja-konsumer-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.8rem;
    }

    .kinerja-konsumer-filters {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
        margin-top: 1rem;
        padding: 0.95rem;
        border: 1px solid #d8e7f7;
        border-radius: 14px;
        background: linear-gradient(135deg, #ffffff, #f3f8ff);
    }

    .kinerja-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .kinerja-filter-label {
        font-size: 0.75rem;
        font-weight: 800;
        color: #36567f;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .kinerja-filter-control {
        width: 100%;
        border: 1px solid #bfd2ea;
        border-radius: 10px;
        padding: 0.6rem 0.75rem;
        background: #ffffff;
        color: #16355c;
        font-size: 0.86rem;
        font-weight: 700;
        outline: none;
    }

    .kinerja-filter-control:focus {
        border-color: #4f98eb;
        box-shadow: 0 0 0 3px rgba(79, 152, 235, 0.14);
    }

    .kinerja-filter-meta {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.15rem;
        font-size: 0.78rem;
        color: #5f7189;
    }

    .kinerja-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.55rem;
        border-radius: 999px;
        background: #eef5ff;
        color: #2e4f7f;
        font-weight: 700;
    }

    .kinerja-konsumer-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.7rem;
        border-radius: 999px;
        border: 1px solid #d8e7f7;
        background: linear-gradient(135deg, #ffffff, #eef5ff);
        color: #36567f;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .kinerja-table-shell {
        overflow-x: auto;
        overflow-y: hidden;
        background: #ffffff;
    }

    .kinerja-konsumer-table {
        width: 100%;
        min-width: 1320px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .kinerja-konsumer-table th,
    .kinerja-konsumer-table td {
        border: 1px solid #d9e4f1;
        vertical-align: middle !important;
        white-space: nowrap;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    .kinerja-konsumer-table thead th {
        padding: 0.42rem 0.5rem;
        font-size: 0.68rem;
        font-weight: 800;
        line-height: 1.1;
    }

    .kinerja-konsumer-table tbody td {
        padding: 0.42rem 0.5rem;
        font-size: 0.74rem;
        font-weight: 700;
        color: #20324a;
        line-height: 1.12;
    }

    .kinerja-konsumer-table tbody tr:nth-child(even) td {
        background: #f6faff;
    }

    .kinerja-konsumer-table tbody tr:hover td {
        background: #eef5ff !important;
    }

    .bg-kpr-main {
        background: linear-gradient(180deg, #2b69cf 0%, #0d56b8 100%) !important;
        color: #ffffff !important;
        border-color: #0f4da1 !important;
    }

    .bg-kpr-delta {
        background: linear-gradient(180deg, #7bd4f2 0%, #56c0e4 100%) !important;
        color: #0f223f !important;
        border-color: #41afd6 !important;
    }

    .bg-kpr-rka {
        background: linear-gradient(180deg, #4f98eb 0%, #2677d8 100%) !important;
        color: #ffffff !important;
        border-color: #1f63ba !important;
    }

    .bg-kpr-ach {
        background: linear-gradient(180deg, #0f766e 0%, #096b63 100%) !important;
        color: #ffffff !important;
        border-color: #08564f !important;
    }

    .bg-kpr-subhead {
        background: #edf5ff !important;
        color: #264b7b !important;
        font-weight: 800 !important;
    }

    .row-total td {
        background: linear-gradient(180deg, #0f5ec7 0%, #0c4fa8 100%) !important;
        color: #ffffff !important;
        border-color: #0f4da1 !important;
        font-weight: 800 !important;
    }

    .row-total td:first-child {
        border-radius: 0 0 0 12px;
    }

    .row-total td:last-child {
        border-radius: 0 0 12px 0;
    }

    .row-total .cell-rka,
    .row-total .cell-delta,
    .row-total .cell-pct {
        color: #ffffff !important;
    }

    .cell-left {
        text-align: left !important;
    }

    .cell-no {
        width: 48px;
    }

    .cell-segmen {
        width: 92px;
    }

    .cell-cabang {
        min-width: 190px;
    }

    .cell-product {
        min-width: 152px;
    }

    .cell-branch {
        min-width: 240px;
    }

    .cell-os {
        min-width: 112px;
    }

    .cell-delta {
        min-width: 92px;
    }

    .cell-rka {
        min-width: 104px;
    }

    .cell-pct {
        min-width: 88px;
    }

    .delta-pos {
        color: #11843d;
        font-weight: 800;
    }

    .delta-neg {
        color: #e11d48;
        font-weight: 800;
    }

    .pct-good {
        background: #10b981 !important;
        color: #ffffff !important;
        font-style: italic;
        font-weight: 800;
    }

    .pct-mid {
        background: #fff200 !important;
        color: #0f172a !important;
        font-style: italic;
        font-weight: 800;
    }

    .pct-bad {
        background: #ff2020 !important;
        color: #ffffff !important;
        font-style: italic;
        font-weight: 800;
    }
</style>

@php
    $fmt = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatSigned = function ($value) {
        $numeric = (float) $value;
        $abs = number_format(abs($numeric), 0, ',', '.');
        if ($numeric < 0) {
            return '<span class="delta-neg">(' . $abs . ')</span>';
        }
        if ($numeric > 0) {
            return '<span class="delta-pos">' . $abs . '</span>';
        }
        return '<span>0</span>';
    };
    $formatPct = function ($value) {
        $numeric = (float) $value;
        $label = number_format($numeric, 2, ',', '.') . '%';
        $class = $numeric >= 100 ? 'pct-good' : ($numeric >= 95 ? 'pct-mid' : 'pct-bad');
        return '<span class="' . $class . '">' . $label . '</span>';
    };
@endphp

<div class="pt-4">
    <div class="kinerja-konsumer-shell">
        <div class="kinerja-konsumer-header">
            <h1 class="kinerja-konsumer-title">{{ $title }}</h1>
            <div class="kinerja-konsumer-subtitle">
                Periode posisi {{ $selectedPeriodLabel }}. Bandingkan OS Konsumer per RM.
            </div>
            <div class="kinerja-konsumer-badges">
                <span class="kinerja-konsumer-badge"><i class="fas fa-bolt"></i> Periode terakhir {{ $latestPeriodLabel }}</span>
                <span class="kinerja-konsumer-badge"><i class="fas fa-calendar-alt"></i> Posisi {{ $selectedPeriodLabel }}</span>
                <span class="kinerja-konsumer-badge"><i class="fas fa-building"></i> Cabang {{ $selectedCabangLabel }}</span>
                <span class="kinerja-konsumer-badge"><i class="fas fa-layer-group"></i> Segmen {{ $selectedSegmenLabel }}</span>
                <span class="kinerja-konsumer-badge"><i class="fas fa-history"></i> MtD {{ $mtdLabel }}</span>
                <span class="kinerja-konsumer-badge"><i class="fas fa-undo-alt"></i> YtD {{ $ytdLabel }}</span>
                <span class="kinerja-konsumer-badge"><i class="fas fa-flag-checkered"></i> RKA {{ $currentMonthLabel }} / {{ $nextMonthLabel }}</span>
            </div>

            <form method="GET" action="{{ route('report.dashboard-pinjaman.kinerja-konsumer') }}" class="kinerja-konsumer-filters">
                <div class="kinerja-filter-group">
                    <label for="kinerjaPeriode" class="kinerja-filter-label">Periode</label>
                    <select id="kinerjaPeriode" name="periode" class="kinerja-filter-control" onchange="this.form.submit()">
                        @foreach($availablePeriods as $period)
                            <option value="{{ $period }}" @selected($selectedPeriod === $period)>
                                {{ \Carbon\Carbon::parse($period)->translatedFormat('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="kinerja-filter-group">
                    <label for="kinerjaCabang" class="kinerja-filter-label">Nama Cabang</label>
                    <select id="kinerjaCabang" name="cabang1" class="kinerja-filter-control" onchange="this.form.submit()">
                        <option value="" @selected($selectedCabang === null)>Semua Cabang</option>
                        @foreach($availableCabangs as $cabang)
                            <option value="{{ $cabang }}" @selected($selectedCabang === $cabang)>{{ $cabang }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="kinerja-filter-group">
                    <label for="kinerjaSegmen" class="kinerja-filter-label">Segmen</label>
                    <select id="kinerjaSegmen" name="segmen" class="kinerja-filter-control" onchange="this.form.submit()">
                        @foreach($availableSegments as $value => $label)
                            <option value="{{ $value }}" @selected($selectedSegmen === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="kinerja-filter-meta">
                    <span class="kinerja-filter-chip"><i class="fas fa-filter"></i> Filter aktif: periode {{ $selectedPeriodLabel }}</span>
                    <span class="kinerja-filter-chip"><i class="fas fa-sitemap"></i> {{ $selectedCabangLabel }}</span>
                    <span class="kinerja-filter-chip"><i class="fas fa-layer-group"></i> {{ $selectedSegmenLabel }}</span>
                </div>
            </form>
        </div>

        <div class="kinerja-table-shell">
            <table class="kinerja-konsumer-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="bg-kpr-main cell-no">No.</th>
                        <th rowspan="2" class="bg-kpr-main cell-cabang">Kantor Cabang</th>
                        <th rowspan="2" class="bg-kpr-main cell-branch">Nama RM</th>
                        <th rowspan="2" class="bg-kpr-main cell-segmen">Segmen</th>
                        <th colspan="3" class="bg-kpr-main">OS KONSUMER</th>
                        <th colspan="3" class="bg-kpr-delta">Δ {{ $selectedPeriodShortLabel }} Thd</th>
                        <th colspan="2" class="bg-kpr-rka">RKA KP</th>
                        <th colspan="4" class="bg-kpr-ach">PENCAPAIAN RKA</th>
                    </tr>
                    <tr>
                        <th class="bg-kpr-subhead cell-os">{{ $mtdLabel }}<br><small>MtD</small></th>
                        <th class="bg-kpr-subhead cell-os">{{ $previousDayLabel }}<br><small>YtD</small></th>
                        <th class="bg-kpr-subhead cell-os">{{ $selectedPeriodLabel }}<br><small>OS</small></th>

                        <th class="bg-kpr-delta cell-delta">YtD<br><small>OS</small></th>
                        <th class="bg-kpr-delta cell-delta">MtD<br><small>OS</small></th>
                        <th class="bg-kpr-delta cell-delta">DtD<br><small>OS</small></th>

                        <th class="bg-kpr-rka cell-rka">{{ $currentMonthLabel }}<br><small>OS</small></th>
                        <th class="bg-kpr-rka cell-rka">{{ $nextMonthLabel }}<br><small>OS</small></th>

                        <th class="bg-kpr-ach cell-rka">{{ $currentMonthLabel }}<br><small>Δ</small></th>
                        <th class="bg-kpr-ach cell-pct">{{ $currentMonthLabel }}<br><small>%</small></th>
                        <th class="bg-kpr-ach cell-rka">{{ $nextMonthLabel }}<br><small>Δ</small></th>
                        <th class="bg-kpr-ach cell-pct">{{ $nextMonthLabel }}<br><small>%</small></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $groupIndex => $group)
                        @foreach($group['items'] as $itemIndex => $row)
                            <tr>
                                <td class="cell-no">{{ $itemIndex === 0 ? $groupIndex + 1 : '' }}</td>
                                @if($itemIndex === 0)
                                    <td class="cell-left cell-cabang" rowspan="{{ $group['rowspan'] }}">
                                        {{ $group['cabang'] }}
                                    </td>
                                    <td class="cell-left cell-branch" rowspan="{{ $group['rowspan'] }}">
                                        {{ $group['rm'] }}
                                    </td>
                                @endif
                                <td class="cell-segmen">{{ $row['product'] }}</td>
                                <td class="cell-os">{{ $fmt($row['mtd']) }}</td>
                                <td class="cell-os">{{ $fmt($row['prev_day']) }}</td>
                                <td class="cell-os">{{ $fmt($row['curr']) }}</td>
                                <td class="cell-delta">{!! $formatSigned($row['delta_ytd']) !!}</td>
                                <td class="cell-delta">{!! $formatSigned($row['delta_mtd']) !!}</td>
                                <td class="cell-delta">{!! $formatSigned($row['delta_dtd']) !!}</td>
                                <td class="cell-rka">{{ $fmt($row['rka_current']) }}</td>
                                <td class="cell-rka">{{ $fmt($row['rka_next']) }}</td>
                                <td class="cell-rka">{!! $formatSigned($row['curr'] - $row['rka_current']) !!}</td>
                                <td class="cell-pct">{!! $formatPct($row['penc_current']) !!}</td>
                                <td class="cell-rka">{!! $formatSigned($row['curr'] - $row['rka_next']) !!}</td>
                                <td class="cell-pct">{!! $formatPct($row['penc_next']) !!}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="16" class="py-4 text-center text-muted">Belum ada data Briguna-Konsumer/KPR untuk periode ini.</td>
                        </tr>
                    @endforelse

                    @if(!empty($rows))
                        <tr class="row-total">
                            <td class="cell-no"></td>
                            <td class="cell-left cell-cabang">{{ $total['cabang'] }}</td>
                            <td class="cell-left cell-branch">{{ $total['rm'] }}</td>
                            <td class="cell-segmen">{{ $total['segmen'] }}</td>
                            <td class="cell-os">{{ $fmt($total['mtd']) }}</td>
                            <td class="cell-os">{{ $fmt($total['prev_day']) }}</td>
                            <td class="cell-os">{{ $fmt($total['curr']) }}</td>
                            <td class="cell-delta">{!! $formatSigned($total['delta_ytd']) !!}</td>
                            <td class="cell-delta">{!! $formatSigned($total['delta_mtd']) !!}</td>
                            <td class="cell-delta">{!! $formatSigned($total['delta_dtd']) !!}</td>
                            <td class="cell-rka">{{ $fmt($total['rka_current']) }}</td>
                            <td class="cell-rka">{{ $fmt($total['rka_next']) }}</td>
                            <td class="cell-rka">{!! $formatSigned($total['curr'] - $total['rka_current']) !!}</td>
                            <td class="cell-pct">{!! $formatPct($total['penc_current']) !!}</td>
                            <td class="cell-rka">{!! $formatSigned($total['curr'] - $total['rka_next']) !!}</td>
                            <td class="cell-pct">{!! $formatPct($total['penc_next']) !!}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
