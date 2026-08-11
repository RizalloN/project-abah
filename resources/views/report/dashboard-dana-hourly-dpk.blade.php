@extends('layouts.admin')

@section('title', 'Hourly DPK')

@php
    $formatJuta = static function ($value): string {
        $number = (float) $value / 1000000;
        if (abs($number) >= 1000) {
            return number_format($number, 0, ',', '.');
        }

        return number_format($number, 1, ',', '.');
    };

    $periodKeys = ['yoy', 'ytd', 'mtm', 'mtd', 'h2', 'h1'];
    $deltaLabels = ['dtd' => 'Hari Lalu', 'mtd' => 'Bulan Lalu', 'ytd' => 'Tahun Lalu'];
    $isAreaScope = ($selectedBranch ?? 'all') === 'all';
    $isBranchDetail = !$isAreaScope;
    $displayTables = (array) ($hourlyReport['tables'] ?? []);
    $deltaClass = static function ($value): string {
        $number = (float) $value;

        if ($number > 0) {
            return 'hourly-delta-positive';
        }

        if ($number < 0) {
            return 'hourly-delta-negative';
        }

        return 'hourly-delta-flat';
    };
    $formatDeltaJuta = static function ($value) use ($formatJuta): string {
        $number = (float) $value;
        if (abs($number) < 0.5) {
            return '0,0';
        }

        return ($number > 0 ? '+' : '') . $formatJuta($number);
    };
@endphp

@section('content')
<style>
    :root {
        --hourly-blue: #00529c;
        --hourly-blue-deep: #003b75;
        --hourly-blue-soft: #eaf2ff;
        --hourly-cyan: #31b7e9;
        --hourly-border: #dbe8f6;
        --hourly-muted: #64748b;
        --hourly-text: #0f172a;
    }

    .hourly-dpk-page {
        min-height: 100vh;
        background: linear-gradient(180deg, #eef7ff 0%, #f8fbff 48%, #ffffff 100%);
        padding-bottom: 2.5rem;
        font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
    }

    .hourly-dpk-hero {
        background: linear-gradient(135deg, var(--hourly-blue-deep), #086ed1);
        color: #fff;
        padding: 0.62rem 1.45rem;
        border-radius: 0 0 10px 10px;
        box-shadow: 0 10px 22px -20px rgba(0, 58, 117, 0.42);
    }

    .hourly-dpk-hero h1 {
        margin: 0;
        font-size: 1.25rem;
        line-height: 1.2;
        font-weight: 900;
        letter-spacing: 0;
    }

    .hourly-dpk-container {
        max-width: 1660px;
        margin: 0 auto;
        padding: 0.9rem 1.45rem 0;
    }

    .hourly-filter-card,
    .hourly-table-card {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid var(--hourly-border);
        border-radius: 18px;
        box-shadow: 0 18px 34px -28px rgba(15, 23, 42, 0.38);
    }

    .hourly-filter-card {
        position: relative;
        z-index: 1000;
        overflow: visible;
        padding: 1rem;
        margin-bottom: 1.15rem;
    }

    .hourly-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr) auto;
        gap: 0.9rem;
        align-items: end;
    }

    @media (max-width: 1200px) {
        .hourly-filter-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        .hourly-filter-actions {
            grid-column: span 3;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }
    }

    .hourly-filter-field {
        position: relative;
        min-width: 0;
    }

    .hourly-filter-label {
        display: block;
        margin-bottom: 0.42rem;
        color: #516b91;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .hourly-native-select {
        display: none;
    }

    .hourly-select {
        position: relative;
    }

    .hourly-select.is-open {
        z-index: 1010;
    }

    .hourly-select-toggle {
        width: 100%;
        min-height: 46px;
        border: 1px solid #cddcf0;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f4f9ff 100%);
        color: var(--hourly-text);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0 1rem;
        font-weight: 800;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 10px 20px -18px rgba(0, 82, 156, 0.45);
    }

    .hourly-select-toggle i {
        color: var(--hourly-blue);
    }

    .hourly-select-menu {
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        right: 0;
        z-index: 1020;
        display: none;
        max-height: 260px;
        overflow-y: auto;
        padding: 0.45rem;
        border: 1px solid var(--hourly-border);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 22px 34px -24px rgba(15, 23, 42, 0.45);
    }

    .hourly-select.is-open .hourly-select-menu {
        display: block;
    }

    .hourly-select-option {
        width: 100%;
        border: 0;
        background: transparent;
        padding: 0.68rem 0.75rem;
        border-radius: 11px;
        text-align: left;
        font-weight: 750;
        color: #334155;
    }

    .hourly-select-option:hover,
    .hourly-select-option.is-active {
        background: var(--hourly-blue-soft);
        color: var(--hourly-blue);
    }

    .hourly-submit {
        min-height: 46px;
        border: 0;
        border-radius: 14px;
        padding: 0 1.25rem;
        background: linear-gradient(135deg, var(--hourly-blue), #0b72d9);
        color: #fff;
        font-weight: 900;
        box-shadow: 0 16px 24px -18px rgba(0, 82, 156, 0.8);
    }

    .hourly-filter-actions {
        display: flex;
        gap: 0.65rem;
        align-items: center;
        align-self: end;
        white-space: nowrap;
    }

    .hourly-export-pdf {
        min-height: 46px;
        border: 1px solid #cddcf0;
        border-radius: 14px;
        padding: 0 1rem;
        background: #ffffff;
        color: var(--hourly-blue);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 14px 22px -20px rgba(0, 82, 156, 0.55);
    }

    .hourly-export-pdf:hover {
        color: #ffffff;
        background: var(--hourly-blue);
        text-decoration: none;
    }

    .hourly-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 0.95rem;
    }

    .hourly-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.52rem 0.78rem;
        border: 1px solid #cfe1f6;
        border-radius: 999px;
        background: #f8fbff;
        color: #37516f;
        font-size: 0.78rem;
        font-weight: 850;
    }

    .hourly-table-card {
        position: relative;
        z-index: 1;
        overflow: hidden;
        scroll-margin-top: 0.75rem;
    }

    .hourly-summary-card {
        margin-bottom: 1.15rem;
        overflow: hidden;
    }

    .hourly-summary-shell {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #9fb2ca #edf4fb;
    }

    .hourly-summary-card table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .hourly-summary-card th,
    .hourly-summary-card td {
        padding: 0.72rem 0.85rem;
        min-width: 112px;
        white-space: nowrap;
        border-right: 1px solid #dce7f3;
        border-bottom: 1px solid #dce7f3;
        text-align: right;
    }

    .hourly-summary-card th {
        color: #fff;
        background: #0b519d;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        text-align: center;
    }

    .hourly-summary-card .hourly-summary-label {
        min-width: 150px;
        text-align: left;
        font-weight: 950;
    }

    .hourly-summary-retail td {
        background: #eff6ff;
    }

    .hourly-summary-micro td {
        background: #ecfdf5;
    }

    .hourly-summary-total td {
        background: #ffeb3b !important;
        color: #10213a !important;
        font-weight: 950 !important;
    }

    .hourly-product-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin: 0 0 0.85rem;
    }

    .hourly-product-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 0.48rem 0.8rem;
        border: 1px solid #bfd5ee;
        border-radius: 8px;
        background: #ffffff;
        color: #164f8d;
        font-size: 0.78rem;
        font-weight: 900;
        text-decoration: none !important;
    }

    .hourly-product-link:hover,
    .hourly-product-link:focus-visible {
        border-color: #0b67be;
        background: #eaf4ff;
        color: #073b78;
    }

    .hourly-table-title {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--hourly-border);
    }

    .hourly-table-title h2 {
        margin: 0;
        color: var(--hourly-text);
        font-size: 1.15rem;
        font-weight: 900;
    }

    .hourly-table-shell {
        position: relative;
        z-index: 1;
        height: auto;
        max-height: none;
        min-height: 0;
        overflow-x: auto;
        overflow-y: visible;
        scrollbar-width: thin;
        scrollbar-color: #9fb2ca #edf4fb;
    }

    .hourly-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
    }

    .hourly-table th,
    .hourly-table td {
        border-right: 1px solid #dce7f3;
        border-bottom: 1px solid #dce7f3;
        padding: 0.72rem 0.7rem;
        white-space: nowrap;
        vertical-align: middle;
    }

    .hourly-table th:not(.hourly-sticky),
    .hourly-table td:not(.hourly-sticky) {
        min-width: 112px;
    }

    .hourly-table thead th {
        position: sticky;
        z-index: 3;
        top: 0;
        background: #073b78;
        color: #fff;
        text-align: center;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
    }

    .hourly-table thead tr:nth-child(2) th {
        top: 42px;
        background: #0b519d;
    }

    .hourly-table tbody td {
        color: #233044;
        font-size: 0.82rem;
        font-weight: 650;
        text-align: right;
        background: #fff;
    }

    .hourly-table tbody tr:nth-child(even) td {
        background: #f8fbff;
    }

    .hourly-table tbody tr:hover td {
        background: #eaf4ff !important;
    }

    .hourly-sticky {
        position: sticky;
        z-index: 4;
        left: 0;
        background: #ffffff !important;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hourly-sticky-no {
        min-width: 58px;
        width: 58px;
        max-width: 58px;
    }

    .hourly-sticky-branch {
        left: 58px;
        min-width: 160px;
        width: 160px;
        max-width: 160px;
        text-align: left !important;
    }

    .hourly-sticky-code {
        left: 218px;
        min-width: 110px;
        width: 110px;
        max-width: 110px;
        text-align: center !important;
    }

    .hourly-sticky-unit {
        left: 328px;
        min-width: 300px;
        width: 300px;
        max-width: 300px;
        text-align: left !important;
        border-right: 2px solid #b8cce4 !important;
        box-shadow: 12px 0 18px -18px rgba(15, 23, 42, 0.75);
    }

    .hourly-sticky-area-code {
        left: 58px;
        min-width: 112px;
        width: 112px;
        max-width: 112px;
        text-align: center !important;
    }

    .hourly-sticky-area-branch {
        left: 170px;
        min-width: 210px;
        width: 210px;
        max-width: 210px;
        text-align: left !important;
        border-right: 2px solid #b8cce4 !important;
        box-shadow: 12px 0 18px -18px rgba(15, 23, 42, 0.75);
    }

    .hourly-table tbody tr:nth-child(even) td.hourly-sticky {
        background: #f8fbff !important;
    }

    .hourly-table tbody tr:hover td.hourly-sticky {
        background: #eaf4ff !important;
    }

    .hourly-table thead .hourly-sticky {
        z-index: 5;
        background: #073b78 !important;
    }

    .hourly-total td {
        background: #ffeb3b !important;
        color: #10213a !important;
        font-weight: 950 !important;
    }

    .hourly-subtotal-retail td {
        background: #dbeafe !important;
        color: #052f63 !important;
        border-top: 2px solid #2563eb !important;
        border-bottom: 2px solid #2563eb !important;
        font-weight: 950 !important;
    }

    .hourly-subtotal-micro td {
        background: #d1fae5 !important;
        color: #065f46 !important;
        border-top: 2px solid #059669 !important;
        border-bottom: 2px solid #059669 !important;
        font-weight: 950 !important;
    }

    .hourly-delta-positive {
        background: #dcfce7 !important;
        color: #078246 !important;
        font-weight: 900 !important;
    }

    .hourly-delta-negative {
        background: #fee2e2 !important;
        color: #dc2626 !important;
        font-weight: 900 !important;
    }

    .hourly-delta-flat {
        background: #fef3c7 !important;
        color: #a16207 !important;
        font-weight: 900 !important;
    }

    .hourly-empty {
        padding: 2rem;
        color: var(--hourly-muted);
        font-weight: 800;
        text-align: center;
    }

    @media (max-width: 900px) {
        .hourly-filter-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .hourly-filter-actions {
            grid-column: span 1;
            justify-content: stretch;
            width: 100%;
            margin-top: 0.5rem;
        }
        .hourly-filter-actions .hourly-submit,
        .hourly-filter-actions .hourly-export-pdf {
            flex: 1;
            text-align: center;
            justify-content: center;
        }

    }

    @media (max-width: 575.98px) {
        .hourly-dpk-container {
            padding-right: 0.5rem;
            padding-left: 0.5rem;
        }

        .hourly-filter-grid,
        .hourly-filter-field,
        .hourly-select,
        .hourly-select-toggle,
        .hourly-filter-actions {
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        .hourly-filter-actions {
            flex-direction: column;
            white-space: normal;
        }

        .hourly-filter-actions .hourly-submit,
        .hourly-filter-actions .hourly-export-pdf {
            width: 100%;
            flex: 0 0 auto;
        }
    }
</style>

<div class="hourly-dpk-page">
    <div class="hourly-dpk-hero">
        <h1>Hourly DPK</h1>
    </div>

    <div class="hourly-dpk-container">
        <form method="GET" action="{{ route('report.dashboard-dana.hourly-dpk') }}" class="hourly-filter-card">
            <div class="hourly-filter-grid">
                <div class="hourly-filter-field">
                    <label class="hourly-filter-label" for="cabang">Cabang</label>
                    <select id="cabang" name="cabang" class="hourly-native-select" data-hourly-native-select>
                        <option value="all" {{ ($selectedBranch ?? 'all') === 'all' ? 'selected' : '' }}>Area 6</option>
                        @foreach (($filters['branches'] ?? []) as $branch)
                            <option value="{{ $branch }}" {{ ($selectedBranch ?? 'all') === $branch ? 'selected' : '' }}>{{ $branch }}</option>
                        @endforeach
                    </select>
                    <div class="hourly-select" data-hourly-select="cabang"></div>
                </div>

                @if ($isAreaScope)
                    <div class="hourly-filter-field">
                        <label class="hourly-filter-label" for="segmen">Segmen</label>
                        <select id="segmen" name="segmen" class="hourly-native-select" data-hourly-native-select>
                            @foreach (($filters['segments'] ?? ['all' => 'Semua Segmen']) as $value => $label)
                                <option value="{{ $value }}" {{ ($selectedSegment ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="hourly-select" data-hourly-select="segmen"></div>
                    </div>
                @endif

                <div class="hourly-filter-actions">
                    <button type="submit" class="hourly-submit">
                        <i class="fas fa-filter mr-2"></i>Tampilkan
                    </button>
                    <a
                        href="{{ route('report.dashboard-dana.hourly-dpk.export-pdf', ['cabang' => $selectedBranch ?? 'all', 'segmen' => $selectedSegment ?? 'all']) }}"
                        target="_blank"
                        rel="noopener"
                        data-hourly-export-pdf
                        data-export-url="{{ route('report.dashboard-dana.hourly-dpk.export-pdf') }}"
                        class="hourly-export-pdf"
                    >
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </a>
                </div>
            </div>

            <div class="hourly-meta-row">
                <span class="hourly-pill"><i class="fas fa-calendar-day"></i>Hari ini: {{ $payload['selectedDateLabel'] ?? '-' }}</span>
                <span class="hourly-pill"><i class="fas fa-map-marker-alt"></i>Scope: {{ $payload['scopeLabel'] ?? 'Area 6' }}</span>
                <span class="hourly-pill"><i class="fas fa-layer-group"></i>Segmen: {{ ($filters['segments'][$selectedSegment ?? 'all'] ?? 'Semua Segmen') }}</span>
                <span class="hourly-pill"><i class="fas fa-coins"></i>Satuan: Rp Juta</span>
            </div>
        </form>

        @if ($isBranchDetail)
            @php
                $summaryPayload = (array) (($hourlyReport['tables'][0]['payload'] ?? []));
                $summaryPeriods = (array) ($summaryPayload['periods'] ?? []);
                $summaryHours = (array) ($summaryPayload['hours'] ?? []);
                $summaryTotal = (array) ($hourlyReport['summaryTotal'] ?? []);
            @endphp
            <div class="hourly-table-card hourly-summary-card">
                <div class="hourly-table-title">
                    <h2>Summary Segmen {{ $hourlyReport['scopeLabel'] ?? ($payload['scopeLabel'] ?? '') }}</h2>
                    <span class="hourly-pill"><i class="fas fa-calendar-day"></i>Posisi {{ $hourlyReport['selectedDateLabel'] ?? '-' }}</span>
                </div>
                <div class="hourly-summary-shell">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">Segmen</th>
                                <th colspan="6">Posisi Historis SSA Simpanan</th>
                                <th colspan="{{ max(1, count($summaryHours)) }}">Posisi Hari Ini {{ $summaryPayload['selectedDateLabel'] ?? '-' }}</th>
                                <th colspan="{{ count($deltaLabels) }}">Delta thd Jam Terakhir</th>
                            </tr>
                            <tr>
                                @foreach ($periodKeys as $key)
                                    <th>{{ $dateFormatter($summaryPeriods[$key] ?? null) }}</th>
                                @endforeach
                                @forelse ($summaryHours as $hour)
                                    <th>{{ $hour['label'] ?? '' }}</th>
                                @empty
                                    <th></th>
                                @endforelse
                                @foreach ($deltaLabels as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($hourlyReport['summary'] ?? []) as $summary)
                                <tr class="hourly-summary-{{ strtolower($summary['segment'] ?? '') }}">
                                    <td class="text-center">{{ $summary['no'] ?? '' }}</td>
                                    <td class="hourly-summary-label">{{ $summary['segment'] ?? '' }}</td>
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($summary['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse ($summaryHours as $hour)
                                        <td>{{ $formatJuta($summary['hour_values'][$hour['key']] ?? 0) }}</td>
                                    @empty
                                        <td>0,0</td>
                                    @endforelse
                                    @foreach ($deltaLabels as $key => $label)
                                        @php $summaryDelta = $summary['delta_values'][$key] ?? 0; @endphp
                                        <td class="{{ $deltaClass($summaryDelta) }}">{{ $formatDeltaJuta($summaryDelta) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            @if ($summaryTotal !== [])
                                <tr class="hourly-summary-total">
                                    <td class="text-center">&Sigma;</td>
                                    <td class="hourly-summary-label">GRAND TOTAL</td>
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($summaryTotal['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse ($summaryHours as $hour)
                                        <td>{{ $formatJuta($summaryTotal['hour_values'][$hour['key']] ?? 0) }}</td>
                                    @empty
                                        <td>0,0</td>
                                    @endforelse
                                    @foreach ($deltaLabels as $key => $label)
                                        @php $summaryDelta = $summaryTotal['delta_values'][$key] ?? 0; @endphp
                                        <td class="{{ $deltaClass($summaryDelta) }}">{{ $formatDeltaJuta($summaryDelta) }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if (count($displayTables) > 1)
            <nav class="hourly-product-nav" aria-label="Navigasi jenis simpanan">
                @foreach ($displayTables as $table)
                    @php
                        $productAnchor = 'hourly-product-' . strtolower((string) ($table['key'] ?? 'all'));
                    @endphp
                    <a class="hourly-product-link" href="#{{ $productAnchor }}">{{ $table['label'] ?? 'Simpanan' }}</a>
                @endforeach
            </nav>
        @endif

        @foreach ($displayTables as $table)
            @php
                $tablePayload = (array) ($table['payload'] ?? []);
                $tableRows = (array) ($tablePayload['rows'] ?? []);
                $tableHours = (array) ($tablePayload['hours'] ?? []);
                $tablePeriods = (array) ($tablePayload['periods'] ?? []);
                $tableTotal = (array) ($tablePayload['total'] ?? []);
                $fixedColumnCount = $isAreaScope ? 3 : 4;
                $productAnchor = 'hourly-product-' . strtolower((string) ($table['key'] ?? 'all'));
            @endphp
            <div id="{{ $productAnchor }}" class="hourly-table-card {{ $loop->first ? '' : 'mt-3' }}">
                <div class="hourly-table-title">
                    <h2>{{ $table['label'] ?? 'Hourly DPK' }}</h2>
                    <span class="hourly-pill"><i class="fas fa-table"></i>{{ number_format($tablePayload['dataRowCount'] ?? count($tableRows), 0, ',', '.') }} baris data</span>
                </div>

                @if (!($tablePayload['ready'] ?? false))
                    <div class="hourly-empty">{{ $tablePayload['message'] ?? 'Data belum tersedia.' }}</div>
                @else
                    <div class="hourly-table-shell">
                        <table class="hourly-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="hourly-sticky hourly-sticky-no">No</th>
                                @if ($isAreaScope)
                                    <th rowspan="2" class="hourly-sticky hourly-sticky-area-code">Kode Cabang</th>
                                    <th rowspan="2" class="hourly-sticky hourly-sticky-area-branch">Nama Cabang</th>
                                @else
                                    <th rowspan="2" class="hourly-sticky hourly-sticky-branch">Nama Cabang</th>
                                    <th rowspan="2" class="hourly-sticky hourly-sticky-code">BC</th>
                                    <th rowspan="2" class="hourly-sticky hourly-sticky-unit">Nama Uker</th>
                                @endif
                                <th colspan="6">Posisi Historis SSA Simpanan</th>
                                <th colspan="{{ max(1, count($tableHours)) }}">Posisi Hari Ini {{ $tablePayload['selectedDateLabel'] ?? '-' }}</th>
                                <th colspan="{{ count($deltaLabels) }}">Delta thd {{ $tableHours ? (($tableHours[count($tableHours) - 1]['label'] ?? 'Jam Terbaru')) : 'Jam Terbaru' }}</th>
                            </tr>
                            <tr>
                                @foreach ($periodKeys as $key)
                                    <th>{{ $dateFormatter(($tablePeriods[$key] ?? null)) }}</th>
                                @endforeach
                                @forelse ($tableHours as $hour)
                                    <th>{{ $hour['label'] }}</th>
                                @empty
                                    <th>-</th>
                                @endforelse
                                @foreach ($deltaLabels as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tableRows as $row)
                                <tr class="hourly-{{ str_replace('_', '-', $row['row_type'] ?? 'detail') }}">
                                    <td class="hourly-sticky hourly-sticky-no text-center">{{ $row['no'] !== '' ? $row['no'] : 'Σ' }}</td>
                                    @if ($isAreaScope)
                                        <td class="hourly-sticky hourly-sticky-area-code">{{ $row['branch_code'] ?? '' }}</td>
                                        <td class="hourly-sticky hourly-sticky-area-branch">{{ $row['branch'] ?? '' }}</td>
                                    @else
                                        <td class="hourly-sticky hourly-sticky-branch">{{ $row['branch'] ?? '' }}</td>
                                        <td class="hourly-sticky hourly-sticky-code">{{ $row['unit_code'] ?? '' }}</td>
                                        <td class="hourly-sticky hourly-sticky-unit">{{ $row['unit'] ?? '' }}</td>
                                    @endif
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($row['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse ($tableHours as $hour)
                                        <td>{{ $formatJuta($row['hour_values'][$hour['key']] ?? 0) }}</td>
                                    @empty
                                        <td>0,0</td>
                                    @endforelse
                                    @foreach ($deltaLabels as $key => $label)
                                        @php $deltaValue = $row['delta_values'][$key] ?? 0; @endphp
                                        <td class="{{ $deltaClass($deltaValue) }}">{{ $formatDeltaJuta($deltaValue) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $fixedColumnCount + 6 + max(1, count($tableHours)) + count($deltaLabels) }}" class="hourly-empty">Data tidak ditemukan untuk filter ini.</td>
                                </tr>
                            @endforelse

                            @if ($tableRows !== [])
                                <tr class="hourly-total">
                                    <td class="hourly-sticky hourly-sticky-no text-center">Σ</td>
                                    @if ($isAreaScope)
                                        <td class="hourly-sticky hourly-sticky-area-code"></td>
                                        <td class="hourly-sticky hourly-sticky-area-branch">GRAND TOTAL AREA 6</td>
                                    @else
                                        <td class="hourly-sticky hourly-sticky-branch">{{ $tablePayload['scopeLabel'] ?? '' }}</td>
                                        <td class="hourly-sticky hourly-sticky-code">{{ $tablePayload['branchCode'] ?? '' }}</td>
                                        <td class="hourly-sticky hourly-sticky-unit">GRAND TOTAL</td>
                                    @endif
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($tableTotal['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse ($tableHours as $hour)
                                        <td>{{ $formatJuta($tableTotal['hour_values'][$hour['key']] ?? 0) }}</td>
                                    @empty
                                        <td>0,0</td>
                                    @endforelse
                                    @foreach ($deltaLabels as $key => $label)
                                        @php $deltaValue = $tableTotal['delta_values'][$key] ?? 0; @endphp
                                        <td class="{{ $deltaClass($deltaValue) }}">{{ $formatDeltaJuta($deltaValue) }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const exportLink = document.querySelector('[data-hourly-export-pdf]');
    const updateExportLink = function () {
        if (!exportLink) return;

        const form = exportLink.closest('form');
        const params = new URLSearchParams();
        params.set('cabang', form && form.elements.cabang ? form.elements.cabang.value : 'all');
        params.set('segmen', form && form.elements.segmen ? form.elements.segmen.value : 'all');
        exportLink.href = exportLink.dataset.exportUrl + '?' + params.toString();
    };
    const escapeHtml = function (value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    document.querySelectorAll('[data-hourly-native-select]').forEach(function (select) {
        const host = document.querySelector('[data-hourly-select="' + select.id + '"]');
        if (!host) return;

        const render = function () {
            const active = select.options[select.selectedIndex];
            let iconClass = 'fa-chevron-down';
            if (select.id === 'cabang') iconClass = 'fa-map-marker-alt';
            else if (select.id === 'jenis') iconClass = 'fa-wallet';
            else if (select.id === 'segmen') iconClass = 'fa-layer-group';

            host.innerHTML = [
                '<button type="button" class="hourly-select-toggle">',
                    '<span><i class="fas ' + iconClass + ' mr-2"></i>' + escapeHtml(active ? active.text : 'Pilih') + '</span>',
                    '<i class="fas fa-angle-down"></i>',
                '</button>',
                '<div class="hourly-select-menu">',
                    Array.from(select.options).map(function (option) {
                        return '<button type="button" class="hourly-select-option ' + (option.selected ? 'is-active' : '') + '" data-value="' + escapeHtml(option.value) + '">' + escapeHtml(option.text) + '</button>';
                    }).join(''),
                '</div>'
            ].join('');
        };

        render();

        host.addEventListener('click', function (event) {
            const toggle = event.target.closest('.hourly-select-toggle');
            const option = event.target.closest('.hourly-select-option');

            if (toggle) {
                event.preventDefault();
                document.querySelectorAll('.hourly-select.is-open').forEach(function (openSelect) {
                    if (openSelect !== host) openSelect.classList.remove('is-open');
                });
                host.classList.toggle('is-open');
            }

            if (option) {
                event.preventDefault();
                select.value = option.dataset.value;
                host.classList.remove('is-open');
                render();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        select.addEventListener('change', updateExportLink);
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('.hourly-select')) return;
        document.querySelectorAll('.hourly-select.is-open').forEach(function (host) {
            host.classList.remove('is-open');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('.hourly-select.is-open').forEach(function (host) {
            host.classList.remove('is-open');
        });
    });

    if (exportLink) {
        updateExportLink();
        exportLink.addEventListener('click', function () {
            updateExportLink();
            document.querySelectorAll('.hourly-select.is-open').forEach(function (host) {
                host.classList.remove('is-open');
            });
        });
    }
});
</script>
@endsection
