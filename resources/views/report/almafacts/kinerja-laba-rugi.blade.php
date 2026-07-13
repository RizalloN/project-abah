@extends('layouts.admin')

@section('title', 'Kinerja Laba Rugi Almafacts')

@php
    $formatRp = static function ($value): string {
        if ($value === null) {
            return '-';
        }

        return number_format((float) $value, 0, ',', '.');
    };
    $formatDeltaRp = static function ($value) use ($formatRp): string {
        if ($value === null) {
            return '-';
        }

        if (abs((float) $value) < 0.01) {
            return '0';
        }

        return (float) $value > 0 ? '+' . $formatRp($value) : $formatRp($value);
    };
    $tone = static function ($value, bool $qualityMetric = false): string {
        if ($value === null) {
            return 'empty';
        }

        if (abs((float) $value) < 0.01) {
            return 'zero';
        }

        if ($qualityMetric) {
            return (float) $value > 0 ? 'negative' : 'positive';
        }

        return (float) $value > 0 ? 'positive' : 'negative';
    };
@endphp

@section('content')
<div class="alma-page">
    <div class="alma-hero">
        <div>
            <div class="alma-eyebrow">Dashboard Almafacts</div>
            <h1>Kinerja Laba Rugi</h1>
            <p>Posisi laba rugi bersumber dari SSA Almafacts, mata anggaran <strong>15. Laba Setelah Pajak</strong>.</p>
        </div>
        <div class="alma-hero-mark">
            <i class="fas fa-balance-scale"></i>
            <span>{{ $selectedBranchLabel }}</span>
        </div>
    </div>

    <form method="GET" action="{{ route('report.dashboard-almafacts.kinerja-laba-rugi') }}" class="alma-filter" id="alma-filter-form">
        <div class="alma-field">
            <label for="alma-periode">Periode</label>
            <div class="alma-select-wrap">
                <i class="fas fa-calendar-alt"></i>
                <select id="alma-periode" name="periode" class="alma-select">
                    @foreach($periodOptions as $period)
                        <option value="{{ $period }}" @selected($period === $selectedPeriod)>{{ \Carbon\Carbon::parse($period)->translatedFormat('F y') }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="alma-field">
            <label for="alma-cabang">Nama Cabang</label>
            <div class="alma-select-wrap">
                <i class="fas fa-building"></i>
                <select id="alma-cabang" name="cabang" class="alma-select">
                    @foreach($branchOptions as $value => $label)
                        <option value="{{ $value }}" @selected($value === $selectedBranch)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="alma-field">
            <label for="alma-rka">RKA</label>
            <div class="alma-select-wrap">
                <i class="fas fa-bullseye"></i>
                <select id="alma-rka" name="rka_periode" class="alma-select">
                    @foreach($rkaPeriodOptions as $period)
                        <option value="{{ $period }}" @selected($period === $selectedRkaPeriod)>{{ \Carbon\Carbon::parse($period)->translatedFormat('F y') }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="alma-actions">
            <button type="submit" class="alma-btn alma-btn-primary">
                <i class="fas fa-filter"></i>
                Terapkan
            </button>
            <a href="{{ route('report.dashboard-almafacts.kinerja-laba-rugi') }}" class="alma-btn alma-btn-light">
                <i class="fas fa-undo"></i>
                Reset
            </a>
        </div>
    </form>

    <div class="alma-summary">
        <div class="alma-metric">
            <span>Baris Tampilan</span>
            <strong>{{ number_format($summary['row_count'] ?? 0, 0, ',', '.') }}</strong>
        </div>
        <div class="alma-metric">
            <span>Posisi {{ $selectedPeriodLabel }}</span>
            <strong>{{ $formatRp($summary['current'] ?? 0) }}</strong>
        </div>
        <div class="alma-metric">
            <span>RKA {{ $selectedRkaLabel }}</span>
            <strong>{{ $formatRp($summary['rka_current'] ?? 0) }}</strong>
        </div>
        <div class="alma-metric {{ $tone($summary['rka_current_gap'] ?? null) }}">
            <span>Gap RKA {{ $selectedRkaLabel }}</span>
            <strong>{{ $formatRp($summary['rka_current_gap'] ?? null) }}</strong>
        </div>
    </div>

    <div class="alma-table-panel">
        <div class="alma-table-head">
            <div>
                <strong>{{ $selectedBranchLabel }}</strong>
                <span>Nominal laba setelah pajak dalam Rupiah.</span>
            </div>
            <div class="alma-chip">RKA {{ $selectedRkaLabel }} & {{ $rkaDecLabel }}</div>
        </div>
        <div class="alma-table-wrap">
            <table class="alma-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky-col number">No</th>
                        <th rowspan="2" class="sticky-col branch">Cabang</th>
                        @if($showUnitColumn)
                            <th rowspan="2" class="sticky-col unit">Unit Kerja</th>
                        @endif
                        <th colspan="5">Posisi</th>
                        <th colspan="4">Delta Posisi</th>
                        <th colspan="4">RKA</th>
                    </tr>
                    <tr>
                        <th>{{ $comparisonLabels['yoy'] }}</th>
                        <th>{{ $comparisonLabels['ytd'] }}</th>
                        <th>{{ $comparisonLabels['m2'] }}</th>
                        <th>{{ $comparisonLabels['m1'] }}</th>
                        <th>{{ $comparisonLabels['current'] }}</th>
                        <th>{{ $comparisonLabels['yoy'] }}</th>
                        <th>{{ $comparisonLabels['ytd'] }}</th>
                        <th>{{ $comparisonLabels['m2'] }}</th>
                        <th>{{ $comparisonLabels['m1'] }}</th>
                        <th>RKA {{ $selectedRkaLabel }}</th>
                        <th>Delta {{ $selectedRkaLabel }}</th>
                        <th>RKA {{ $rkaDecLabel }}</th>
                        <th>Delta {{ $rkaDecLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $index => $row)
                        <tr>
                            <td class="sticky-col number">{{ $index + 1 }}</td>
                            <td class="sticky-col branch">{{ $row['branch'] }}</td>
                            @if($showUnitColumn)
                                <td class="sticky-col unit">
                                    <div class="alma-unit-name">{{ $row['unit_name'] }}</div>
                                    <div class="alma-unit-meta">{{ $row['unit_type'] }}{{ $row['unit_code'] ? ' - ' . $row['unit_code'] : '' }}</div>
                                </td>
                            @endif
                            <td class="num">{{ $formatRp($row['values']['yoy']) }}</td>
                            <td class="num">{{ $formatRp($row['values']['ytd']) }}</td>
                            <td class="num">{{ $formatRp($row['values']['m2']) }}</td>
                            <td class="num">{{ $formatRp($row['values']['m1']) }}</td>
                            <td class="num strong">{{ $formatRp($row['values']['current']) }}</td>
                            <td class="num delta {{ $tone($row['deltas']['yoy']) }}">{{ $formatDeltaRp($row['deltas']['yoy']) }}</td>
                            <td class="num delta {{ $tone($row['deltas']['ytd']) }}">{{ $formatDeltaRp($row['deltas']['ytd']) }}</td>
                            <td class="num delta {{ $tone($row['deltas']['m2']) }}">{{ $formatDeltaRp($row['deltas']['m2']) }}</td>
                            <td class="num delta {{ $tone($row['deltas']['m1']) }}">{{ $formatDeltaRp($row['deltas']['m1']) }}</td>
                            <td class="num">{{ $formatRp($row['rka']['current']) }}</td>
                            <td class="num delta {{ $tone($row['rka']['current_gap']) }}">{{ $formatDeltaRp($row['rka']['current_gap']) }}</td>
                            <td class="num">{{ $formatRp($row['rka']['dec']) }}</td>
                            <td class="num delta {{ $tone($row['rka']['dec_gap']) }}">{{ $formatDeltaRp($row['rka']['dec_gap']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showUnitColumn ? 16 : 15 }}" class="alma-empty">
                                Data laba setelah pajak belum tersedia untuk filter ini.
                            </td>
                        </tr>
                    @endforelse

                    @if(count($rows) > 0)
                        <tr class="alma-table-total">
                            <td class="sticky-col number"></td>
                            <td class="sticky-col branch">TOTAL</td>
                            @if($showUnitColumn)
                                <td class="sticky-col unit"></td>
                            @endif
                            <td class="num">{{ $formatRp($summary['values']['yoy']) }}</td>
                            <td class="num">{{ $formatRp($summary['values']['ytd']) }}</td>
                            <td class="num">{{ $formatRp($summary['values']['m2']) }}</td>
                            <td class="num">{{ $formatRp($summary['values']['m1']) }}</td>
                            <td class="num strong">{{ $formatRp($summary['values']['current']) }}</td>
                            <td class="num delta {{ $tone($summary['deltas']['yoy']) }}">{{ $formatDeltaRp($summary['deltas']['yoy']) }}</td>
                            <td class="num delta {{ $tone($summary['deltas']['ytd']) }}">{{ $formatDeltaRp($summary['deltas']['ytd']) }}</td>
                            <td class="num delta {{ $tone($summary['deltas']['m2']) }}">{{ $formatDeltaRp($summary['deltas']['m2']) }}</td>
                            <td class="num delta {{ $tone($summary['deltas']['m1']) }}">{{ $formatDeltaRp($summary['deltas']['m1']) }}</td>
                            <td class="num">{{ $formatRp($summary['rka']['current']) }}</td>
                            <td class="num delta {{ $tone($summary['rka']['current_gap']) }}">{{ $formatDeltaRp($summary['rka']['current_gap']) }}</td>
                            <td class="num">{{ $formatRp($summary['rka']['dec']) }}</td>
                            <td class="num delta {{ $tone($summary['rka']['dec_gap']) }}">{{ $formatDeltaRp($summary['rka']['dec_gap']) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .alma-page {
        --alma-blue: #0857c3;
        --alma-blue-hover: #0649a3;
        --alma-blue-2: #307fe2;
        --alma-blue-light: #f0f6fc;
        --alma-ink: #053b82;
        --alma-muted: #56708f;
        --alma-line: #cbddeb;
        --alma-soft: #f2f7ff;
        --alma-gradient-primary: linear-gradient(135deg, #053b82 0%, #0857c3 50%, #307fe2 100%);
        --alma-shadow-sm: 0 2px 8px rgba(8, 87, 195, 0.04);
        --alma-shadow-md: 0 10px 30px rgba(8, 87, 195, 0.06);
        --alma-shadow-lg: 0 16px 36px rgba(8, 87, 195, 0.1);
        --alma-radius: 16px;
        --alma-radius-sm: 10px;
        color: #0f172a;
        min-width: 0;
        overflow-x: clip;
    }

    .alma-page *,
    .alma-page *::before,
    .alma-page *::after {
        box-sizing: border-box;
    }

    .alma-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 1.2rem 1.35rem;
        margin-bottom: 1rem;
        background: var(--alma-gradient-primary);
        border: none;
        border-radius: var(--alma-radius);
        box-shadow: var(--alma-shadow-lg);
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }

    .alma-hero > div:first-child {
        min-width: 0;
    }

    .alma-eyebrow {
        margin-bottom: 0.35rem;
        color: #71c5e8;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.15em;
        text-transform: uppercase;
    }

    .alma-hero h1 {
        margin: 0;
        color: #ffffff;
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .alma-hero p {
        margin: 0.4rem 0 0;
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.92rem;
        font-weight: 500;
        max-width: 760px;
    }

    .alma-hero p strong {
        color: #ffffff;
        font-weight: 700;
    }

    .alma-hero-mark {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.75rem 1.15rem;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: var(--alma-radius-sm);
        color: #ffffff;
        font-weight: 800;
        font-size: 0.9rem;
        white-space: nowrap;
        max-width: min(360px, 36vw);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.25s ease;
    }

    .alma-hero-mark span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .alma-hero-mark:hover {
        background: rgba(255, 255, 255, 0.18);
        transform: translateY(-2px);
    }

    .alma-filter {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr)) auto;
        gap: .9rem;
        align-items: end;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #ffffff;
        border: 1px solid var(--alma-line);
        border-radius: var(--alma-radius);
        box-shadow: var(--alma-shadow-md);
        transition: box-shadow 0.3s ease;
    }

    .alma-filter:hover {
        box-shadow: 0 14px 36px rgba(5, 59, 130, 0.08);
    }

    .alma-field label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--alma-ink);
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .alma-field,
    .alma-actions {
        min-width: 0;
    }

    .alma-select-wrap {
        position: relative;
        display: flex;
        align-items: center;
        min-height: 44px;
        background: var(--alma-soft);
        border: 1px solid #c9d8eb;
        border-radius: var(--alma-radius-sm);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .alma-select-wrap:focus-within {
        background: #ffffff;
        border-color: var(--alma-blue);
        box-shadow: 0 0 0 4px rgba(8, 87, 195, 0.12);
    }

    .alma-select-wrap i {
        width: 44px;
        color: var(--alma-blue);
        text-align: center;
        flex: 0 0 44px;
        font-size: 0.95rem;
    }

    .alma-select {
        flex: 1;
        min-width: 0;
        min-height: 42px;
        border: 0;
        background: transparent;
        color: var(--alma-ink);
        font-size: 0.88rem;
        font-weight: 700;
        outline: none;
        padding-right: 0.8rem;
        cursor: pointer;
    }

    .alma-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .alma-btn {
        min-height: 44px;
        padding: 0 1.25rem;
        border: 1px solid transparent;
        border-radius: var(--alma-radius-sm);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.88rem;
        font-weight: 800;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .alma-btn-primary {
        background: var(--alma-blue);
        border-color: var(--alma-blue);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(8, 87, 195, 0.2);
    }

    .alma-btn-primary:hover {
        background: var(--alma-blue-hover);
        border-color: var(--alma-blue-hover);
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(8, 87, 195, 0.3);
    }

    .alma-btn-light {
        background: #ffffff;
        border-color: #cbd9ec;
        color: #334155;
    }

    .alma-btn-light:hover {
        background: #f8fbff;
        border-color: var(--alma-blue-2);
        color: var(--alma-blue);
        transform: translateY(-1px);
    }

    .alma-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: .85rem;
        margin-bottom: 1rem;
    }

    .alma-metric {
        min-height: 82px;
        padding: .9rem 1rem;
        background: #ffffff;
        border: 1px solid var(--alma-line);
        border-radius: var(--alma-radius);
        box-shadow: var(--alma-shadow-sm);
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        position: relative;
        min-width: 0;
    }

    .alma-metric:hover {
        transform: translateY(-2px);
        box-shadow: var(--alma-shadow-md);
        border-color: var(--alma-blue-2);
    }

    .alma-metric span {
        display: block;
        color: var(--alma-muted);
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .alma-metric strong {
        display: block;
        margin-top: 0.5rem;
        color: var(--alma-ink);
        font-size: 1.3rem;
        font-weight: 800;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .alma-metric.positive {
        border-left: 4px solid #10b981;
    }
    .alma-metric.positive strong {
        color: #047857;
    }

    .alma-metric.negative {
        border-left: 4px solid #ef4444;
    }
    .alma-metric.negative strong {
        color: #b91c1c;
    }

    .alma-metric.zero {
        border-left: 4px solid #eab308;
    }
    .alma-metric.zero strong {
        color: #92400e;
    }

    .alma-table-panel {
        background: #ffffff;
        border: 1px solid var(--alma-line);
        border-radius: var(--alma-radius);
        box-shadow: var(--alma-shadow-md);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .alma-table-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        padding: .9rem 1rem;
        border-bottom: 1px solid var(--alma-line);
        background: #fafbfe;
    }

    .alma-table-head strong {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--alma-ink);
    }

    .alma-table-head span {
        display: block;
        margin-top: 0.25rem;
        color: var(--alma-muted);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .alma-chip {
        padding: .45rem .75rem;
        border: 1px solid rgba(8, 87, 195, 0.12);
        border-radius: var(--alma-radius-sm);
        background: var(--alma-blue-light);
        color: var(--alma-blue);
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
        box-shadow: var(--alma-shadow-sm);
    }

    /* Table Wrapper and Sticky Viewport styling integration */
    @include('report.partials.sticky-table-viewport-style', [
        'wrapperSelector' => '.alma-table-wrap',
        'tableSelector' => '.alma-table'
    ])

    .alma-table-wrap {
        border-radius: var(--alma-radius-sm) !important;
        border: 1px solid var(--alma-line) !important;
        height: auto !important;
        max-height: var(--alma-table-max-height, calc(100dvh - 300px)) !important;
        overflow: auto !important;
        -webkit-overflow-scrolling: touch;
        touch-action: pan-x pan-y;
    }

    .alma-table {
        width: 100%;
        min-width: 1360px;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        margin: 0;
        background: #ffffff;
    }

    .alma-table th,
    .alma-table td {
        border-right: 1px solid #e2e8f0 !important;
        border-bottom: 1px solid #e2e8f0 !important;
        padding: .52rem .62rem !important;
        font-size: .76rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .alma-table thead tr:first-child th {
        background-color: var(--alma-blue) !important;
        color: #ffffff !important;
        font-weight: 800;
        font-size: .72rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        text-align: center;
        border-right: 1px solid rgba(255, 255, 255, 0.15) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    }

    .alma-table thead tr:nth-child(2) th {
        background-color: #f0f6fc !important;
        color: var(--alma-ink) !important;
        font-weight: 750;
        font-size: .68rem;
        text-transform: uppercase;
        text-align: center;
        border-right: 1px solid #cbddeb !important;
        border-bottom: 1px solid #cbddeb !important;
    }

    .alma-table th span {
        color: var(--alma-muted);
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: none;
        display: block;
        margin-top: 0.1rem;
    }

    .alma-table tbody td {
        font-size: .76rem;
        color: var(--alma-ink);
        background: #ffffff;
    }

    /* Hover effect */
    .alma-table tbody tr:hover td {
        background-color: var(--alma-blue-light) !important;
    }

    .alma-table tbody tr:hover td.sticky-col {
        background-color: var(--alma-blue-light) !important;
    }

    .alma-table .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    .alma-table .strong {
        color: var(--alma-ink) !important;
        font-weight: 800 !important;
    }

    .alma-table .sticky-col {
        position: sticky !important;
        z-index: 5 !important;
        background-color: #ffffff !important;
        box-shadow: 2px 0 8px rgba(5, 59, 130, 0.05);
    }

    .alma-table th.sticky-col {
        z-index: 15 !important;
        background-color: var(--alma-blue) !important;
        color: #ffffff !important;
    }

    /* Thicker border separating frozen pane */
    @if($showUnitColumn)
        .alma-table th.sticky-col.unit,
        .alma-table td.sticky-col.unit {
            border-right: 2px solid var(--alma-blue) !important;
        }
    @else
        .alma-table th.sticky-col.branch,
        .alma-table td.sticky-col.branch {
            border-right: 2px solid var(--alma-blue) !important;
        }
    @endif

    .alma-table .number {
        left: 0px !important;
        width: 54px;
        min-width: 54px;
        text-align: center;
    }

    .alma-table .branch {
        left: 54px !important;
        width: 150px;
        min-width: 150px;
        font-weight: 800;
    }

    .alma-table .unit {
        left: 204px !important;
        width: 250px;
        min-width: 250px;
        max-width: 280px;
    }

    .alma-unit-name {
        font-weight: 800;
        color: var(--alma-ink);
    }

    .alma-unit-meta {
        margin-top: 0.15rem;
        color: var(--alma-muted);
        font-size: 0.7rem;
        font-weight: 700;
    }

    .alma-table td.delta {
        font-weight: 850;
    }

    .alma-table td.positive {
        background-color: #dcfce7 !important;
        color: #166534 !important;
    }

    .alma-table td.negative {
        background-color: #fee2e2 !important;
        color: #991b1b !important;
    }

    .alma-table td.zero {
        background-color: #fef9c3 !important;
        color: #92400e !important;
    }

    .alma-table td.empty {
        background-color: #f8fafc !important;
        color: #94a3b8 !important;
    }

    .alma-table tbody tr:hover td.positive {
        background-color: #bbf7d0 !important;
    }

    .alma-table tbody tr:hover td.negative {
        background-color: #fecaca !important;
    }

    .alma-table tbody tr:hover td.zero {
        background-color: #fef08a !important;
    }

    /* Grand Total Row styling (Excel-Modern style with soft yellow fill) */
    .alma-table-total td {
        background-color: #fef9c3 !important; /* soft yellow */
        color: #713f12 !important; /* dark gold/yellow-brown text */
        font-weight: 800 !important;
        border-top: 2px solid #eab308 !important; /* yellow border */
        border-bottom: 3px double #eab308 !important;
    }

    .alma-table-total td.sticky-col {
        background-color: #fef9c3 !important;
        color: #713f12 !important;
        font-weight: 800 !important;
    }

    .alma-table-total td.positive {
        background-color: #fef9c3 !important;
        color: #166534 !important; /* green text */
    }

    .alma-table-total td.negative {
        background-color: #fef9c3 !important;
        color: #991b1b !important; /* red text */
    }

    .alma-table-total td.zero {
        background-color: #fef9c3 !important;
        color: #92400e !important;
    }

    .alma-table tbody tr.alma-table-total:hover td {
        background-color: #fef08a !important; /* slightly darker yellow on hover */
    }

    .alma-table tbody tr.alma-table-total:hover td.sticky-col {
        background-color: #fef08a !important;
    }

    .alma-table tbody tr.alma-table-total:hover td.positive {
        background-color: #fef08a !important;
        color: #166534 !important;
    }

    .alma-table tbody tr.alma-table-total:hover td.negative {
        background-color: #fef08a !important;
        color: #991b1b !important;
    }

    .alma-table tbody tr.alma-table-total:hover td.zero {
        background-color: #fef08a !important;
        color: #92400e !important;
    }

    .alma-empty {
        padding: 3rem !important;
        text-align: center;
        color: var(--alma-muted);
        font-weight: 700;
        font-size: 0.9rem;
        background: #ffffff !important;
    }

    @media (max-width: 991.98px) {
        .alma-hero,
        .alma-table-head {
            align-items: flex-start;
            flex-direction: column;
            gap: 1rem;
        }

        .alma-filter {
            grid-template-columns: 1fr;
            padding: 0.9rem;
        }

        .alma-summary {
            grid-template-columns: 1fr;
        }

        .alma-hero {
            padding: 0.95rem 1rem;
            border-radius: 12px;
        }

        .alma-hero h1 {
            font-size: 1.28rem;
            line-height: 1.15;
        }

        .alma-hero p {
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .alma-hero-mark {
            max-width: 100%;
            justify-content: center;
        }

        .alma-actions {
            justify-content: stretch;
            width: 100%;
        }

        .alma-actions .alma-btn {
            flex: 1;
        }

        .alma-table-head {
            padding: 0.75rem;
        }

        .alma-table th,
        .alma-table td {
            padding: 0.45rem 0.52rem !important;
            font-size: 0.72rem;
        }

        .alma-table-wrap {
            max-height: var(--alma-table-max-height, calc(100dvh - 260px)) !important;
        }
    }

    @media (min-width: 768px) and (max-width: 1180px) {
        .alma-filter {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .alma-actions {
            justify-content: flex-end;
        }

        .alma-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .alma-table {
            min-width: 1260px;
        }

        .alma-table .branch {
            width: 136px;
            min-width: 136px;
        }

        .alma-table .unit {
            left: 190px !important;
            width: 220px;
            min-width: 220px;
        }
    }

    @media (max-width: 767.98px) {
        .alma-hero,
        .alma-filter,
        .alma-table-panel,
        .alma-metric {
            border-radius: 12px;
        }

        .alma-summary {
            gap: 0.65rem;
        }

        .alma-metric {
            min-height: 72px;
            padding: 0.75rem 0.85rem;
        }

        .alma-metric span {
            font-size: 0.68rem;
        }

        .alma-metric strong {
            font-size: 1.05rem;
        }

        .alma-chip {
            width: 100%;
            text-align: center;
            white-space: normal;
        }

        .alma-btn {
            min-height: 40px;
            padding-inline: 0.85rem;
        }

        .alma-table .sticky-col {
            position: static !important;
            left: auto !important;
            width: auto;
            min-width: auto;
            max-width: none;
            box-shadow: none;
        }

        .alma-table {
            min-width: 1120px;
        }
    }

    @media (max-width: 575.98px) {
        .alma-hero {
            padding: 0.8rem;
        }

        .alma-hero h1 {
            font-size: 1.12rem;
        }

        .alma-hero p {
            font-size: 0.76rem;
        }

        .alma-filter {
            gap: 0.6rem;
            padding: 0.65rem;
        }

        .alma-select-wrap,
        .alma-btn {
            min-height: 38px;
        }

        .alma-select-wrap i {
            width: 36px;
            flex-basis: 36px;
        }

        .alma-select {
            min-height: 36px;
            font-size: 0.8rem;
        }

        .alma-actions {
            gap: 0.5rem;
        }

        .alma-actions .alma-btn {
            flex-basis: 100%;
        }

        .alma-table-head strong {
            font-size: 0.98rem;
        }

        .alma-table-head span {
            font-size: 0.76rem;
        }

        .alma-table {
            min-width: 980px;
        }

        .alma-table th,
        .alma-table td {
            padding: 0.36rem 0.42rem !important;
            font-size: 0.68rem;
        }

        .alma-table thead tr:first-child th {
            font-size: 0.66rem;
        }

        .alma-table thead tr:nth-child(2) th {
            font-size: 0.64rem;
        }
    }
</style>
@endsection

@section('scripts')
@include('report.partials.sticky-table-viewport-script', [
    'wrapperSelector' => '.alma-table-wrap',
    'tableSelector' => '.alma-table',
    'visibleRowLimit' => 30
])
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('alma-filter-form');
    const tableWrap = document.querySelector('.alma-table-wrap');
    let resizeFrame = null;

    function syncTableHeight() {
        resizeFrame = null;

        if (!tableWrap) {
            return;
        }

        const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
        const rect = tableWrap.getBoundingClientRect();
        const bottomGap = window.matchMedia('(max-width: 767.98px)').matches ? 18 : 24;
        const minHeight = window.matchMedia('(max-width: 767.98px)').matches ? 260 : 320;
        const availableHeight = Math.floor(viewportHeight - rect.top - bottomGap);
        const maxHeight = Math.max(minHeight, availableHeight);

        tableWrap.style.setProperty('--alma-table-max-height', maxHeight + 'px');
        tableWrap.style.maxHeight = maxHeight + 'px';
        tableWrap.style.overflowY = 'auto';
        tableWrap.style.overflowX = 'auto';
    }

    function scheduleTableHeight() {
        if (resizeFrame !== null) {
            return;
        }

        resizeFrame = window.requestAnimationFrame(syncTableHeight);
    }

    scheduleTableHeight();
    window.addEventListener('resize', scheduleTableHeight);
    window.addEventListener('orientationchange', scheduleTableHeight);
    window.addEventListener('load', scheduleTableHeight);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', scheduleTableHeight);
    }

    if (!form) {
        return;
    }

    form.querySelectorAll('.alma-select').forEach(function (select) {
        select.addEventListener('change', function () {
            if (window.showRouteLoading) {
                window.showRouteLoading('Memuat Almafacts', 'Mengambil posisi laba rugi dan target RKA terbaru.', 'page-data-loading');
            }
            form.submit();
        });
    });
});
</script>
@endsection
