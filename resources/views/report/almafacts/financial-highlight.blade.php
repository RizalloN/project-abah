@extends('layouts.admin')

@section('title', 'Financial Highlight Almafacts')

@php
    $formatMoney = static function ($value): string {
        if ($value === null) {
            return '-';
        }

        return number_format((float) $value / 1000000, 0, ',', '.');
    };
    $formatPercent = static function ($value): string {
        if ($value === null) {
            return '-';
        }

        return number_format((float) $value * 100, 2, ',', '.') . '%';
    };
    $formatValue = static function ($value, string $format) use ($formatMoney, $formatPercent): string {
        return $format === 'percent' ? $formatPercent($value) : $formatMoney($value);
    };
    $formatDeltaValue = static function ($value, string $format) use ($formatValue): string {
        if ($value === null) {
            return '-';
        }

        if (abs((float) $value) < 0.000001) {
            return '0';
        }

        return (float) $value > 0 ? '+' . $formatValue($value, $format) : $formatValue($value, $format);
    };
    $deltaTone = static function ($value, bool $qualityMetric = false): string {
        if ($value === null) {
            return 'empty';
        }

        if (abs((float) $value) < 0.000001) {
            return 'zero';
        }

        if ($qualityMetric) {
            return (float) $value > 0 ? 'negative' : 'positive';
        }

        return (float) $value > 0 ? 'positive' : 'negative';
    };
@endphp

@section('content')
<div class="fh-page">
    <div class="fh-hero">
        <div>
            <div class="fh-eyebrow">Dashboard Almafacts</div>
            <h1>Financial Highlight</h1>
            <p>Ringkasan posisi keuangan dari SSA Almafacts.</p>
        </div>
        <div class="fh-hero-badge">
            <i class="fas fa-chart-pie"></i>
            <span>{{ $selectedBranchLabel }}</span>
        </div>
    </div>

    <form method="GET" action="{{ route('report.dashboard-almafacts.financial-highlight') }}" class="fh-filter" id="fh-filter-form">
        <div class="fh-field">
            <label for="fh-periode">Periode</label>
            <div class="fh-select-shell">
                <i class="fas fa-calendar-alt"></i>
                <select id="fh-periode" name="periode" class="fh-select">
                    @foreach($periodOptions as $period)
                        <option value="{{ $period }}" @selected($period === $selectedPeriod)>
                            {{ \Carbon\Carbon::parse($period)->translatedFormat('F y') }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="fh-field">
            <label for="fh-cabang">Kantor Cabang</label>
            <div class="fh-select-shell">
                <i class="fas fa-building"></i>
                <select id="fh-cabang" name="cabang" class="fh-select">
                    @foreach($branchOptions as $value => $label)
                        <option value="{{ $value }}" @selected($value === $selectedBranch)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="fh-field fh-type-field">
            <label>Unit Kerja</label>
            <div class="fh-segmented" role="group" aria-label="Tipe unit kerja">
                @foreach(['ALL' => 'Semua', 'KC' => 'KC', 'KCP' => 'KCP', 'UNIT' => 'Unit'] as $value => $label)
                    <label class="fh-segment {{ ($unitFilter['type'] ?? 'ALL') === $value ? 'active' : '' }}">
                        <input type="radio" name="unit_type" value="{{ $value }}" @checked(($unitFilter['type'] ?? 'ALL') === $value)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="fh-field">
            <label for="fh-unit-values">Pilihan Unit</label>
            <div class="fh-select-shell">
                <i class="fas fa-sitemap"></i>
                <select id="fh-unit-values" name="unit_values[]" class="fh-select" data-placeholder="Pilih tipe unit dahulu"></select>
            </div>
            <div class="fh-hint" id="fh-unit-hint">KC dan KCP bisa dipilih lebih dari satu. Unit hanya satu pilihan.</div>
        </div>

        <div class="fh-actions">
            <button type="submit" class="fh-btn fh-btn-primary">
                <i class="fas fa-sync-alt"></i>
                Tampilkan
            </button>
            <a href="{{ route('report.dashboard-almafacts.financial-highlight') }}" class="fh-btn fh-btn-light">
                <i class="fas fa-undo"></i>
                Reset
            </a>
        </div>
    </form>

    <div class="fh-table-panel">
        <div class="fh-table-head">
            <div class="fh-table-title-row">
                <div class="fh-title-group">
                    <h2>{{ $selectedBranchLabel }} Financial Highlight</h2>
                    <span class="fh-subtitle">Nominal disajikan dalam Rp Juta. Rasio disajikan dalam persen.</span>
                </div>
                <div class="fh-meta-group">
                    <span class="fh-meta-item">
                        <span class="fh-meta-label">Periode:</span>
                        <span class="fh-meta-val">{{ $selectedPeriodLabel }}</span>
                    </span>
                    <span class="fh-meta-item">
                        <span class="fh-meta-label">SSA Almafacts:</span>
                        <span class="fh-meta-val">{{ isset($sourcePeriods['current']['ssa_almafacts']) && $sourcePeriods['current']['ssa_almafacts'] ? \Carbon\Carbon::parse($sourcePeriods['current']['ssa_almafacts'])->translatedFormat('d M Y') : '-' }}</span>
                    </span>
                </div>
            </div>
        </div>
        <div class="fh-table-wrap">
            <table class="fh-table">
                <thead>
                    <tr>
                        <th class="metric-col blue-header">Financial Highlight</th>
                        <th class="blue-header">{{ $comparisonLabels['yoy'] }}</th>
                        <th class="blue-header">{{ $comparisonLabels['ytd'] }}</th>
                        <th class="blue-header">{{ $comparisonLabels['m1'] }}</th>
                        <th class="blue-header current-header">{{ $comparisonLabels['current'] }}</th>
                        <th class="grey-header">YOY</th>
                        <th class="grey-header">YTD</th>
                        <th class="grey-header">MOM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sections as $section)
                        <tr class="fh-section-row">
                            <td colspan="8">
                                <i class="{{ $section['icon'] }}"></i>
                                {{ $section['title'] }}{{ in_array($section['title'], ['Liabilities', 'Profit & Loss']) ? ' (Rp. Juta)' : '' }}
                            </td>
                        </tr>
                        @foreach($section['rows'] as $row)
                            @php
                                $label = $row['label'];
                                $isIndent = false;
                                if (in_array($label, ['Giro', 'Tabungan', 'Deposito'])) {
                                    $isIndent = true;
                                    if ($label === 'Giro') $label = 'a. Giro';
                                    elseif ($label === 'Tabungan') $label = 'b. Tabungan';
                                    elseif ($label === 'Deposito') $label = 'c. Deposito';
                                }
                                $isSummaryRow = in_array($row['label'], [
                                    'Pinjaman', 'Simpanan', 'Overhead Cost', 'PPOP', 'Laba Sebelum Pajak', 'Laba Setelah Pajak'
                                ]);
                                if ($row['format'] === 'percent' && !str_ends_with($label, '(%)')) {
                                    $label .= ' (%)';
                                }
                            @endphp
                            <tr class="{{ $isSummaryRow ? 'summary-row' : '' }}">
                                <td class="metric-col {{ $isIndent ? 'indent-row' : '' }}">{{ $label }}</td>
                                <td class="num">{{ $formatValue($row['values']['yoy'], $row['format']) }}</td>
                                <td class="num">{{ $formatValue($row['values']['ytd'], $row['format']) }}</td>
                                <td class="num">{{ $formatValue($row['values']['m1'], $row['format']) }}</td>
                                <td class="num strong">{{ $formatValue($row['values']['current'], $row['format']) }}</td>
                                <td class="num delta {{ $deltaTone($row['deltas']['yoy'], $row['is_quality_metric'] ?? false) }}">{{ $formatDeltaValue($row['deltas']['yoy'], $row['format']) }}</td>
                                <td class="num delta {{ $deltaTone($row['deltas']['ytd'], $row['is_quality_metric'] ?? false) }}">{{ $formatDeltaValue($row['deltas']['ytd'], $row['format']) }}</td>
                                <td class="num delta {{ $deltaTone($row['deltas']['m1'], $row['is_quality_metric'] ?? false) }}">{{ $formatDeltaValue($row['deltas']['m1'], $row['format']) }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="8" class="fh-empty">Data belum tersedia untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .fh-page {
        --fh-blue: #0f3976;
        --fh-blue-dark: #082d60;
        --fh-blue-light: #1e293b;
        --fh-grey-header: #5a6a85;
        --fh-grey-section: #555555;
        --fh-line: #cbd5e1;
        --fh-ink: #0f172a;
        --fh-muted: #475569;
        --fh-green: #15803d;
        --fh-red: #b91c1c;
        --fh-yellow: #d97706;
        color: var(--fh-ink);
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        min-width: 0;
        overflow-x: clip;
    }

    .fh-page *,
    .fh-page *::before,
    .fh-page *::after {
        box-sizing: border-box;
    }

    /* Mature & Clean Hero Banner */
    .fh-hero {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.25rem;
        border-radius: 12px;
        background: #0f1e36;
        color: #fff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }

    .fh-hero > div:first-child {
        min-width: 0;
    }

    .fh-eyebrow {
        color: #94a3b8;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 0.15rem;
    }

    .fh-hero h1 {
        margin: 0 0 .15rem;
        color: #fff;
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .fh-hero p {
        margin: 0;
        color: #cbd5e1;
        font-size: 0.88rem;
        font-weight: 500;
        max-width: 720px;
    }

    .fh-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .5rem .85rem;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.06);
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
        color: #e2e8f0;
        max-width: min(320px, 38vw);
    }

    .fh-hero-badge span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fh-hero-badge i {
        font-size: 0.85rem;
    }

    /* Mature & Clean Filter Panel */
    .fh-filter {
        display: grid;
        grid-template-columns: 1fr 1fr 1.3fr 1.3fr auto;
        gap: 1rem;
        align-items: end;
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
        background: #fff;
        border: 1px solid var(--fh-line);
        border-radius: 12px;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.03);
    }

    .fh-field label {
        display: block;
        margin-bottom: .45rem;
        color: var(--fh-muted);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .fh-field,
    .fh-actions {
        min-width: 0;
    }

    .fh-select-shell {
        display: flex;
        align-items: center;
        min-height: 40px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #ffffff;
        transition: .15s ease;
    }

    .fh-select-shell:focus-within {
        border-color: #64748b;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(100, 116, 139, 0.12);
    }

    .fh-select-shell i {
        width: 38px;
        color: #64748b;
        text-align: center;
        flex: 0 0 38px;
        font-size: 0.9rem;
    }

    .fh-select {
        width: 100%;
        min-height: 38px;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--fh-ink);
        font-size: 0.88rem;
        font-weight: 600;
        padding-right: .7rem;
    }

    .fh-select[multiple] {
        min-height: 94px;
        padding: .35rem .7rem .35rem 0;
    }

    .fh-segmented {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .25rem;
        padding: .25rem;
        min-height: 40px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #f1f5f9;
    }

    .fh-segment input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .fh-segment span {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 30px;
        border-radius: 6px;
        color: #475569;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        transition: .15s ease;
    }

    .fh-segment span:hover {
        color: #0f172a;
        background: rgba(255, 255, 255, 0.5);
    }

    .fh-segment.active span,
    .fh-segment input:checked + span {
        background: #0f172a;
        color: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
    }

    .fh-hint {
        margin-top: .35rem;
        color: #64748b;
        font-size: .7rem;
        font-weight: 500;
        line-height: 1.3;
    }

    .fh-actions {
        display: flex;
        gap: .55rem;
        flex-wrap: wrap;
    }

    .fh-btn {
        min-height: 40px;
        padding: 0 1.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        border-radius: 8px;
        border: 1px solid transparent;
        font-size: .85rem;
        font-weight: 600;
        white-space: nowrap;
        transition: .15s ease;
    }

    .fh-btn-primary {
        background: #0f172a;
        color: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    }

    .fh-btn-primary:hover {
        background: #1e293b;
        color: #fff;
    }

    .fh-btn-light {
        background: #fff;
        border-color: #cbd5e1;
        color: #334155;
    }

    .fh-btn-light:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }

    /* Tableau Styled Table Container */
    .fh-table-panel {
        overflow: hidden;
        border: 1px solid var(--fh-line);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 4px 20px rgba(15, 48, 86, 0.04);
        margin-top: 1.5rem;
    }

    .fh-table-head {
        padding: 14px 20px;
        border-bottom: 1px solid var(--fh-line);
        background: #f8fafc;
    }

    .fh-table-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .fh-title-group h2 {
        margin: 0;
        color: var(--fh-blue);
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .fh-subtitle {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 600;
        margin-top: 2px;
        display: block;
    }

    .fh-meta-group {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .fh-meta-item {
        font-size: 0.72rem;
        background: #ffffff;
        border: 1px solid var(--fh-line);
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }

    .fh-meta-label {
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 0.02em;
    }

    .fh-meta-val {
        color: var(--fh-ink);
        font-weight: 800;
    }

    .fh-table-wrap {
        overflow: auto;
        max-height: var(--fh-table-max-height, calc(100dvh - 290px));
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        touch-action: pan-x pan-y;
    }

    .fh-table {
        width: 100%;
        min-width: 1080px;
        border-collapse: collapse;
        border-spacing: 0;
    }

    .fh-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 10px 14px;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        border-right: 1px solid rgba(255, 255, 255, 0.15);
        border-bottom: 2px solid var(--fh-line);
    }

    .fh-table th.blue-header {
        background-color: var(--fh-blue);
    }

    .fh-table th.grey-header {
        background-color: var(--fh-grey-header);
    }

    .fh-table th.metric-col {
        text-align: left;
        min-width: 230px;
    }

    /* Center comparison and delta column headers to replace right-alignment */
    .fh-table th:not(.metric-col) {
        text-align: center;
    }

    .fh-table td {
        padding: 9px 14px;
        border-bottom: 1px solid #cbd5e1;
        color: var(--fh-ink);
        font-size: 13px;
        font-weight: 500;
        background-color: #ffffff;
    }

    .fh-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }

    .fh-table tr:hover td {
        background-color: #f1f5f9 !important;
    }

    .fh-table td.metric-col {
        text-align: left;
        font-weight: 600;
    }

    .fh-table td.indent-row {
        padding-left: 28px !important;
    }

    .fh-table td.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .fh-table td.strong {
        font-weight: 800;
    }

    .fh-table tr.summary-row td {
        font-weight: 800 !important;
        background-color: #f1f5f9;
    }

    .fh-table tr.summary-row:hover td {
        background-color: #e2e8f0 !important;
    }

    .fh-table td.delta {
        text-align: center;
        font-weight: 800;
        color: #ffffff !important;
        text-shadow: 0 1px 1px rgba(0, 0, 0, 0.15);
        padding: 8px 12px;
        min-width: 90px;
    }

    .fh-table td.delta.positive {
        background-color: var(--fh-green) !important;
    }

    .fh-table td.delta.negative {
        background-color: var(--fh-red) !important;
    }

    .fh-table td.delta.zero {
        background-color: var(--fh-yellow) !important;
    }

    .fh-table td.delta.empty {
        background-color: #f1f5f9 !important;
        color: #94a3b8 !important;
        text-shadow: none;
    }

    .fh-table .fh-section-row td {
        position: sticky;
        top: 38px;
        z-index: 1;
        background-color: var(--fh-grey-section) !important;
        color: #ffffff !important;
        font-weight: 800;
        font-style: italic;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 10px 14px;
        border-bottom: 2px solid #334155;
    }

    .fh-table .fh-section-row i {
        margin-right: 6px;
    }

    .fh-empty {
        padding: 2rem !important;
        text-align: center;
        color: var(--fh-muted) !important;
        font-weight: 900 !important;
    }

    @media (max-width: 1200px) {
        .fh-filter {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fh-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }
    }

    @media (min-width: 769px) and (max-width: 1180px) {
        .fh-hero {
            align-items: flex-start;
        }

        .fh-filter {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }

        .fh-type-field,
        .fh-actions {
            grid-column: auto;
        }

        .fh-actions {
            align-self: end;
        }

        .fh-actions .fh-btn {
            flex: 1 1 140px;
        }

        .fh-table {
            min-width: 980px;
        }

        .fh-table th,
        .fh-table td {
            padding: 8px 10px;
            font-size: 12px;
        }

        .fh-table th.metric-col {
            min-width: 210px;
        }
    }

    @media (max-width: 768px) {
        .fh-hero,
        .fh-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .fh-hero-badge {
            max-width: 100%;
            justify-content: center;
        }

        .fh-filter {
            grid-template-columns: 1fr;
        }

        .fh-segmented {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fh-btn {
            width: 100%;
        }

        .fh-meta-group {
            width: 100%;
            gap: 6px;
        }

        .fh-meta-item {
            flex: 1 1 150px;
            justify-content: space-between;
        }
    }

    @media (max-width: 991.98px), (max-height: 760px) {
        .fh-hero {
            margin-bottom: 0.75rem;
            padding: 0.85rem 1rem;
            border-radius: 10px;
        }

        .fh-hero h1 {
            margin-bottom: 0.08rem;
            font-size: 1.28rem;
            line-height: 1.12;
        }

        .fh-hero p {
            font-size: 0.74rem;
            line-height: 1.35;
        }

        .fh-hero-badge {
            padding: 0.4rem 0.65rem;
            border-radius: 7px;
            font-size: 0.74rem;
        }

        .fh-filter {
            gap: 0.65rem;
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            border-radius: 10px;
        }

        .fh-field label {
            margin-bottom: 0.26rem;
            font-size: 0.64rem;
        }

        .fh-select-shell,
        .fh-btn,
        .fh-segmented {
            min-height: 34px;
        }

        .fh-segment span {
            height: 26px;
            font-size: 0.72rem;
        }

        .fh-hint {
            display: none;
        }

        .fh-table-panel {
            margin-top: 0.75rem;
        }

        .fh-table-head {
            padding: 0.65rem 0.8rem;
        }

        .fh-title-group h2 {
            font-size: 0.98rem;
        }

        .fh-table-wrap {
            max-height: var(--fh-table-max-height, calc(100dvh - 235px));
        }

        .fh-table {
            min-width: 920px;
        }

        .fh-table th,
        .fh-table td {
            padding: 7px 9px;
            font-size: 12px;
        }

        .fh-table td.delta {
            min-width: 78px;
            padding: 7px 8px;
        }
    }

    @media (max-width: 575.98px) {
        .fh-hero {
            padding: 0.8rem;
        }

        .fh-hero h1 {
            font-size: 1.12rem;
        }

        .fh-filter {
            padding: 0.65rem;
        }

        .fh-select {
            font-size: 0.82rem;
        }

        .fh-table-title-row,
        .fh-meta-group {
            flex-direction: column;
            align-items: stretch;
        }

        .fh-table {
            min-width: 820px;
        }

        .fh-table th,
        .fh-table td {
            padding: 6px 7px;
            font-size: 11px;
        }

        .fh-table th.metric-col {
            min-width: 170px;
        }

        .fh-table .fh-section-row td {
            top: 32px;
            padding: 7px 9px;
        }
    }

    @media (orientation: landscape) and (max-height: 640px) and (min-width: 900px) {
        .fh-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .fh-hero p,
        .fh-subtitle,
        .fh-meta-label {
            display: none !important;
        }

        .fh-filter {
            grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
        }

        .fh-actions {
            flex-direction: row;
        }

        .fh-table-wrap {
            max-height: var(--fh-table-max-height, calc(100dvh - 165px));
        }
    }

    @media (orientation: landscape) and (max-height: 640px) and (max-width: 899.98px) {
        .fh-filter {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fh-actions {
            grid-column: 1 / -1;
            flex-direction: row;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const unitOptions = @json($unitOptions);
        const selectedValues = new Set(@json($unitFilter['values'] ?? []));
        const typeRadios = Array.from(document.querySelectorAll('input[name="unit_type"]'));
        const unitSelect = document.getElementById('fh-unit-values');
        const hint = document.getElementById('fh-unit-hint');
        const form = document.getElementById('fh-filter-form');
        const branchSelect = document.getElementById('fh-cabang');
        const tableWrap = document.querySelector('.fh-table-wrap');
        let resizeFrame = null;

        function syncTableHeight() {
            resizeFrame = null;

            if (!tableWrap) {
                return;
            }

            const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            const rect = tableWrap.getBoundingClientRect();
            const bottomGap = window.matchMedia('(max-width: 768px)').matches ? 18 : 24;
            const minHeight = window.matchMedia('(max-width: 768px)').matches ? 260 : 320;
            const maxHeight = Math.max(minHeight, Math.floor(viewportHeight - rect.top - bottomGap));
            tableWrap.style.setProperty('--fh-table-max-height', maxHeight + 'px');
        }

        function scheduleTableHeight() {
            if (resizeFrame !== null) {
                return;
            }

            resizeFrame = window.requestAnimationFrame(syncTableHeight);
        }

        function activeType() {
            const active = typeRadios.find(function (radio) { return radio.checked; });
            return active ? active.value : 'ALL';
        }

        function renderUnitSelect() {
            const type = activeType();
            unitSelect.innerHTML = '';
            unitSelect.disabled = type === 'ALL';
            unitSelect.multiple = type === 'KC' || type === 'KCP';

            if (type === 'ALL') {
                const option = new Option('Semua unit kerja aktif', '');
                unitSelect.appendChild(option);
                hint.textContent = 'Filter memakai semua KC, KCP, dan Unit pada cabang terpilih.';
                return;
            }

            const options = unitOptions[type] || [];
            const placeholder = new Option(options.length ? 'Pilih ' + type : 'Tidak ada data ' + type, '');
            placeholder.disabled = true;
            placeholder.selected = selectedValues.size === 0;
            unitSelect.appendChild(placeholder);

            options.forEach(function (item) {
                const option = new Option(item.label, item.value);
                option.selected = selectedValues.has(item.value);
                unitSelect.appendChild(option);
            });

            hint.textContent = type === 'UNIT'
                ? 'Unit hanya bisa dipilih satu agar pembacaan lebih presisi.'
                : type + ' bisa dipilih lebih dari satu dengan Ctrl atau Shift.';
        }

        typeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                selectedValues.clear();
                document.querySelectorAll('.fh-segment').forEach(function (segment) {
                    segment.classList.toggle('active', segment.querySelector('input').checked);
                });
                renderUnitSelect();
            });
        });

        if (branchSelect && form) {
            branchSelect.addEventListener('change', function () {
                form.submit();
            });
        }

        renderUnitSelect();
        scheduleTableHeight();
        window.addEventListener('resize', scheduleTableHeight);
        window.addEventListener('orientationchange', scheduleTableHeight);
        window.addEventListener('load', scheduleTableHeight);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleTableHeight);
        }
    });
</script>
@endsection
