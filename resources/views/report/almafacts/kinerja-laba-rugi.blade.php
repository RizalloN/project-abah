@extends('layouts.admin')

@section('title', 'Kinerja Laba Rugi Almafacts')

@php
    $formatRp = static function ($value): string {
        if ($value === null) {
            return '-';
        }

        return number_format((float) $value, 0, ',', '.');
    };
    $tone = static function ($value): string {
        if ($value === null || abs((float) $value) < 0.01) {
            return 'neutral';
        }

        return (float) $value >= 0 ? 'positive' : 'negative';
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
                        <option value="{{ $period }}" @selected($period === $selectedPeriod)>{{ \Carbon\Carbon::parse($period)->translatedFormat('d M Y') }}</option>
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
                        <option value="{{ $period }}" @selected($period === $selectedRkaPeriod)>{{ \Carbon\Carbon::parse($period)->translatedFormat('M Y') }}</option>
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
                <span>{{ $selectedPeriodLabel }} dibandingkan YoY, YTD, M-2, dan M-1.</span>
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
                        <th colspan="5">Posisi Laba Rugi</th>
                        <th colspan="4">Delta Posisi Terhadap</th>
                        <th colspan="4">RKA</th>
                    </tr>
                    <tr>
                        <th>{{ $comparisonLabels['yoy'] }}</th>
                        <th>{{ $comparisonLabels['ytd'] }}</th>
                        <th>{{ $comparisonLabels['m2'] }}</th>
                        <th>{{ $comparisonLabels['m1'] }}</th>
                        <th>{{ $comparisonLabels['current'] }}</th>
                        <th>YoY</th>
                        <th>YtD</th>
                        <th>M-2</th>
                        <th>MtD</th>
                        <th>RP {{ $selectedRkaLabel }}</th>
                        <th>GAP {{ $selectedRkaLabel }}</th>
                        <th>RP {{ $rkaDecLabel }}</th>
                        <th>GAP {{ $rkaDecLabel }}</th>
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
                            <td class="num {{ $tone($row['deltas']['yoy']) }}">{{ $formatRp($row['deltas']['yoy']) }}</td>
                            <td class="num {{ $tone($row['deltas']['ytd']) }}">{{ $formatRp($row['deltas']['ytd']) }}</td>
                            <td class="num {{ $tone($row['deltas']['m2']) }}">{{ $formatRp($row['deltas']['m2']) }}</td>
                            <td class="num {{ $tone($row['deltas']['m1']) }}">{{ $formatRp($row['deltas']['m1']) }}</td>
                            <td class="num">{{ $formatRp($row['rka']['current']) }}</td>
                            <td class="num {{ $tone($row['rka']['current_gap']) }}">{{ $formatRp($row['rka']['current_gap']) }}</td>
                            <td class="num">{{ $formatRp($row['rka']['dec']) }}</td>
                            <td class="num {{ $tone($row['rka']['dec_gap']) }}">{{ $formatRp($row['rka']['dec_gap']) }}</td>
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
                            <td class="num {{ $tone($summary['deltas']['yoy']) }}">{{ $formatRp($summary['deltas']['yoy']) }}</td>
                            <td class="num {{ $tone($summary['deltas']['ytd']) }}">{{ $formatRp($summary['deltas']['ytd']) }}</td>
                            <td class="num {{ $tone($summary['deltas']['m2']) }}">{{ $formatRp($summary['deltas']['m2']) }}</td>
                            <td class="num {{ $tone($summary['deltas']['m1']) }}">{{ $formatRp($summary['deltas']['m1']) }}</td>
                            <td class="num">{{ $formatRp($summary['rka']['current']) }}</td>
                            <td class="num {{ $tone($summary['rka']['current_gap']) }}">{{ $formatRp($summary['rka']['current_gap']) }}</td>
                            <td class="num">{{ $formatRp($summary['rka']['dec']) }}</td>
                            <td class="num {{ $tone($summary['rka']['dec_gap']) }}">{{ $formatRp($summary['rka']['dec_gap']) }}</td>
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
    }

    .alma-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 1.75rem 2rem;
        margin-bottom: 1.5rem;
        background: var(--alma-gradient-primary);
        border: none;
        border-radius: var(--alma-radius);
        box-shadow: var(--alma-shadow-lg);
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }

    .alma-hero::before {
        content: "";
        position: absolute;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
        top: -100px;
        right: -50px;
        border-radius: 50%;
        pointer-events: none;
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
        font-size: 1.85rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .alma-hero p {
        margin: 0.4rem 0 0;
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.92rem;
        font-weight: 500;
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
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.25s ease;
    }

    .alma-hero-mark:hover {
        background: rgba(255, 255, 255, 0.18);
        transform: translateY(-2px);
    }

    .alma-filter {
        display: grid;
        grid-template-columns: repeat(3, minmax(200px, 1fr)) auto;
        gap: 1.25rem;
        align-items: end;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
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
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .alma-metric {
        min-height: 96px;
        padding: 1.15rem 1.25rem;
        background: #ffffff;
        border: 1px solid var(--alma-line);
        border-radius: var(--alma-radius);
        box-shadow: var(--alma-shadow-sm);
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        position: relative;
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
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--alma-line);
        background: #fafbfe;
    }

    .alma-table-head strong {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--alma-ink);
    }

    .alma-table-head span {
        margin-top: 0.25rem;
        color: var(--alma-muted);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .alma-chip {
        padding: 0.5rem 0.85rem;
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
    }

    .alma-table {
        width: 100%;
        min-width: 1500px;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        margin: 0;
        background: #ffffff;
    }

    .alma-table th,
    .alma-table td {
        border-right: 1px solid #e2e8f0 !important;
        border-bottom: 1px solid #e2e8f0 !important;
        padding: 0.7rem 0.8rem !important;
        font-size: 0.8rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .alma-table thead tr:first-child th {
        background-color: var(--alma-blue) !important;
        color: #ffffff !important;
        font-weight: 800;
        font-size: 0.78rem;
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
        font-size: 0.72rem;
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
        font-size: 0.82rem;
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

    /* Excel-style conditional formatting colors */
    .alma-table td.positive {
        background-color: #f0fdf4 !important;
        color: #166534 !important;
    }

    .alma-table td.negative {
        background-color: #fef2f2 !important;
        color: #991b1b !important;
    }

    .alma-table tbody tr:hover td.positive {
        background-color: #dcfce7 !important;
    }

    .alma-table tbody tr:hover td.negative {
        background-color: #fee2e2 !important;
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
            padding: 1.25rem;
        }

        .alma-summary {
            grid-template-columns: 1fr;
        }

        .alma-actions {
            justify-content: stretch;
            width: 100%;
        }

        .alma-actions .alma-btn {
            flex: 1;
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
