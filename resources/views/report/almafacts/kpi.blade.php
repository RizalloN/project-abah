@extends('layouts.admin')

@section('title', $selectedSheet['title'] ?? 'KPI Almafacts')

@php
    $toNumber = static function ($value): ?float {
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }

        $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
        $clean = trim($text, " ()\t\n\r\0\x0B%`");
        $clean = str_ireplace(['rp', 'jt', 'm', 't'], '', $clean);
        $clean = preg_replace('/\s+/', '', $clean) ?? '';

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        if (!is_numeric($clean)) {
            return null;
        }

        $number = (float) $clean;

        return $negative ? $number * -1 : $number;
    };

    $cellTone = static function ($value, string $header = '') use ($toNumber): string {
        $text = trim((string) $value);
        $number = $toNumber($text);
        if ($text === '') {
            return 'kpi-cell-empty';
        }

        $upperHeader = strtoupper($header);
        $isPercent = str_contains($text, '%')
            || str_contains($upperHeader, 'PENCA')
            || str_contains($upperHeader, 'SCORE')
            || str_contains($upperHeader, 'BOBOT');

        if ($number === null) {
            return 'kpi-cell-text';
        }

        if ($isPercent) {
            return match (true) {
                $number >= 100 => 'kpi-cell-green',
                $number >= 90 => 'kpi-cell-teal',
                $number >= 75 => 'kpi-cell-yellow',
                default => 'kpi-cell-red',
            };
        }

        if (str_contains($upperHeader, 'DELTA') || str_contains($upperHeader, 'GAP')) {
            return $number >= 0 ? 'kpi-cell-green' : 'kpi-cell-red';
        }

        return 'kpi-cell-number';
    };

    $displayColumns = $headerColumns ?: collect($header)->values()->map(
        static fn ($heading, $index): array => [
            'label' => (string) $heading,
            'group' => null,
            'sortable' => true,
            'index' => $index,
        ]
    )->all();
    $tableSections = $tableSections ?? [[
        'key' => 'all',
        'title' => $summary['sheet_title'] ?? $selectedSheet['sheet'] ?? '-',
        'rows' => $rows,
    ]];
@endphp

@section('content')
@include('report._bri-report-ui')
<style>
    .kpi-page {
        padding: 1.25rem;
        color: #0f172a;
        min-width: 0;
    }

    .kpi-page *,
    .kpi-page *::before,
    .kpi-page *::after {
        box-sizing: border-box;
    }

    .kpi-hero {
        display: flex;
        align-items: stretch;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 1.25rem;
        border: 1px solid rgba(0, 82, 156, .14);
        border-radius: 18px;
        background:
            linear-gradient(135deg, rgba(0, 82, 156, .97), rgba(0, 116, 184, .92)),
            #00529c;
        box-shadow: 0 18px 38px -26px rgba(15, 23, 42, .38);
        color: #fff;
    }

    .kpi-hero > div:first-child {
        min-width: 0;
    }

    .kpi-eyebrow {
        margin-bottom: .4rem;
        color: rgba(255,255,255,.78);
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .kpi-hero h1 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 900;
        letter-spacing: 0;
    }

    .kpi-hero p {
        max-width: 760px;
        margin: .55rem 0 0;
        color: rgba(255,255,255,.82);
        font-size: .9rem;
        line-height: 1.55;
    }

    .kpi-hero-card {
        min-width: 220px;
        max-width: min(340px, 38vw);
        padding: 1rem;
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 14px;
        background: rgba(255,255,255,.12);
        backdrop-filter: blur(8px);
    }

    .kpi-hero-card span {
        display: block;
        color: rgba(255,255,255,.72);
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .kpi-hero-card strong {
        display: block;
        margin-top: .25rem;
        font-size: 1.6rem;
        font-weight: 900;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .kpi-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: 1rem;
        padding: .9rem;
        border: 1px solid var(--bri-ui-border);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, .22);
    }

    .kpi-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        min-width: 0;
    }

    .kpi-tab,
    .kpi-action {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        min-height: 40px;
        padding: .55rem .85rem;
        border: 1px solid #cbd8e8;
        border-radius: 12px;
        background: linear-gradient(180deg, #eaf2ff 0%, #fff 78%);
        color: #174e92;
        font-size: .82rem;
        font-weight: 900;
        text-decoration: none;
        box-shadow: 0 12px 22px -22px rgba(15, 23, 42, .22);
        max-width: 100%;
    }

    .kpi-tab.active {
        border-color: #00529c;
        background: #00529c;
        color: #fff;
    }

    .kpi-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        min-width: 0;
    }

    .kpi-branch-filter,
    .kpi-period-filter {
        display: flex;
        align-items: center;
        gap: .5rem;
        min-width: min(100%, 230px);
        padding: .35rem .5rem;
        border: 1px solid #cbd8e8;
        border-radius: 12px;
        background: #f8fbff;
    }

    .kpi-branch-filter label,
    .kpi-period-filter label {
        margin: 0;
        color: #475569;
        font-size: .72rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .kpi-branch-filter select,
    .kpi-period-filter select {
        min-width: 150px;
        height: 32px;
        border: 0;
        outline: 0;
        background: transparent;
        color: #174e92;
        font-size: .78rem;
        font-weight: 800;
    }

    .kpi-branch-filter select:disabled,
    .kpi-period-filter select:disabled {
        color: #475569;
        cursor: not-allowed;
    }

    .kpi-action:hover,
    .kpi-tab:hover {
        text-decoration: none;
        transform: translateY(-1px);
    }

    .kpi-action.primary {
        border-color: rgba(0, 82, 156, .24);
        background: #00529c;
        color: #fff;
    }

    .kpi-meta-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(140px, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }

    .kpi-meta {
        padding: .85rem 1rem;
        border: 1px solid #dbe5ef;
        border-radius: 14px;
        background: linear-gradient(180deg, #fff, #f8fbff);
        min-width: 0;
    }

    .kpi-meta span {
        display: block;
        color: #64748b;
        font-size: .72rem;
        font-weight: 900;
        text-transform: uppercase;
    }

    .kpi-meta strong {
        display: block;
        margin-top: .25rem;
        color: #0f172a;
        font-size: 1.1rem;
        font-weight: 900;
        overflow-wrap: anywhere;
    }

    .kpi-table-panel {
        border: 1px solid var(--bri-ui-border);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, .22);
        overflow: hidden;
    }

    .kpi-table-panel + .kpi-table-panel {
        margin-top: 1rem;
    }

    .kpi-table-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .85rem 1rem;
        border-bottom: 1px solid #dbe5ef;
        background: linear-gradient(180deg, #ffffff, #f8fbff);
    }

    .kpi-table-title strong {
        display: block;
        color: #0f172a;
        font-size: .98rem;
        font-weight: 900;
    }

    .kpi-table-title span {
        display: block;
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
    }

    .kpi-excel-wrap.table-container {
        width: 100%;
        max-height: var(--kpi-table-max-height, calc(100dvh - 285px));
        overflow-x: auto !important;
        overflow-y: auto !important;
        background: #f8fafc;
        scrollbar-width: thin;
        scrollbar-color: #9aa8bd #eef3f9;
        -webkit-overflow-scrolling: touch;
    }

    .kpi-excel-table {
        --kpi-sticky-header-row-height: 28px;
        --kpi-sticky-first-column-width: 80px;
        width: max-content;
        min-width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
    }

    .kpi-excel-table th,
    .kpi-excel-table td {
        min-width: 80px;
        max-width: 240px;
        padding: .3rem .45rem;
        border-right: 1px solid #dbe5ef;
        border-bottom: 1px solid #dbe5ef;
        color: #102a4c;
        font-size: .72rem;
        line-height: 1.25;
        white-space: nowrap;
        text-align: right;
        vertical-align: middle;
    }

    .kpi-excel-table thead tr:first-child th {
        position: sticky;
        top: 0;
        z-index: 4;
        background: #00529c;
    }

    .kpi-excel-table thead tr:nth-child(2) th {
        position: sticky;
        top: var(--kpi-sticky-header-row-height);
        z-index: 4;
        background: #004a8d;
        font-size: .62rem;
        box-shadow: inset 0 -1px 0 rgba(255,255,255,.15);
    }

    .kpi-excel-table th {
        color: #fff;
        font-size: .68rem;
        font-weight: 900;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: .02em;
        white-space: normal;
        overflow-wrap: anywhere;
        box-shadow: inset 0 -1px 0 rgba(255,255,255,.22);
    }

    .kpi-excel-table th.kpi-group-head {
        background: #003f7a !important;
    }

    .kpi-excel-table th.kpi-sortable {
        cursor: pointer;
        user-select: none;
    }

    .kpi-excel-table th.kpi-sortable::after {
        content: "⇅";
        display: inline-block;
        margin-left: .35rem;
        color: rgba(255,255,255,.58);
        font-size: .62rem;
        line-height: 1;
        transform: translateY(-1px);
    }

    .kpi-excel-table th.kpi-sortable.sorted-asc::after {
        content: "↑";
        color: #fff;
    }

    .kpi-excel-table th.kpi-sortable.sorted-desc::after {
        content: "↓";
        color: #fff;
    }

    .kpi-excel-table th.kpi-sortable::after {
        content: "\21C5";
    }

    .kpi-excel-table th.kpi-sortable.sorted-asc::after {
        content: "\2191";
    }

    .kpi-excel-table th.kpi-sortable.sorted-desc::after {
        content: "\2193";
    }

    .kpi-sticky-col-0 {
        position: sticky !important;
        left: 0 !important;
        z-index: 5;
        width: 80px;
        min-width: 80px;
        max-width: 80px;
        text-align: center;
    }

    .kpi-sticky-col-1 {
        position: sticky !important;
        left: var(--kpi-sticky-first-column-width) !important;
        z-index: 5;
        min-width: 150px;
        max-width: 180px;
        text-align: left;
        white-space: normal;
    }

    .kpi-excel-table th.kpi-sticky-col-0,
    .kpi-excel-table th.kpi-sticky-col-1 {
        z-index: 7 !important;
        background: #004685 !important;
    }

    .kpi-excel-table td.kpi-sticky-col-0,
    .kpi-excel-table td.kpi-sticky-col-1 {
        background: #f8fbff !important;
        font-weight: 900;
    }

    /* Hide KEY column in Mantri table */
    .kpi-table-mantri th:first-child,
    .kpi-table-mantri td:first-child {
        display: none !important;
    }

    .kpi-table-mantri .kpi-sticky-col-1 {
        left: 0 !important;
        width: 80px;
        min-width: 80px;
        max-width: 80px;
        text-align: center;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(3),
    .kpi-table-mantri tbody td:nth-child(3) {
        width: 130px;
        min-width: 130px;
        max-width: 150px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(4),
    .kpi-table-mantri tbody td:nth-child(4) {
        width: 160px;
        min-width: 160px;
        max-width: 180px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(7),
    .kpi-table-mantri tbody td:nth-child(7) {
        width: 200px;
        min-width: 200px;
        max-width: 220px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(8),
    .kpi-table-mantri tbody td:nth-child(8) {
        width: 120px;
        min-width: 120px;
        max-width: 140px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(10),
    .kpi-table-mantri tbody td:nth-child(10) {
        width: 70px;
        min-width: 70px;
        max-width: 80px;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro .kpi-sticky-col-0 {
        width: 100px;
        min-width: 100px;
        max-width: 100px;
        text-align: center;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro .kpi-sticky-col-1 {
        left: var(--kpi-sticky-first-column-width) !important;
        width: 180px;
        min-width: 180px;
        max-width: 200px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro thead tr:first-child th:nth-child(4),
    .kpi-table-rm-mikro tbody td:nth-child(4) {
        width: 160px;
        min-width: 160px;
        max-width: 180px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro thead tr:first-child th:nth-child(6),
    .kpi-table-rm-mikro tbody td:nth-child(6) {
        width: 70px;
        min-width: 70px;
        max-width: 80px;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-ka-unit thead tr:first-child th:nth-child(4),
    .kpi-table-ka-unit tbody td:nth-child(4) {
        width: 160px;
        min-width: 160px;
        max-width: 180px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-excel-table tbody tr:nth-child(even) td {
        background-color: #fbfdff;
    }

    .kpi-excel-table tbody tr:hover td {
        background-color: #edf5ff !important;
    }

    .kpi-cell-text { text-align: left !important; color: #1e3a5f !important; font-weight: 800; }
    .kpi-cell-number { background: #ffffff; color: #102a4c; font-weight: 800; }
    .kpi-cell-empty { background: #f8fafc; color: #94a3b8; }
    .kpi-cell-green { background: #dcfce7 !important; color: #166534 !important; font-weight: 900; }
    .kpi-cell-teal { background: #ccfbf1 !important; color: #0f766e !important; font-weight: 900; }
    .kpi-cell-yellow { background: #fef9c3 !important; color: #854d0e !important; font-weight: 900; }
    .kpi-cell-red { background: #fee2e2 !important; color: #991b1b !important; font-weight: 900; }

    .kpi-empty {
        padding: 2rem;
        text-align: center;
        color: #64748b;
        font-weight: 800;
    }

    .kpi-alert {
        margin-bottom: 1rem;
        padding: .85rem 1rem;
        border: 1px solid #fecaca;
        border-radius: 14px;
        background: #fff1f2;
        color: #991b1b;
        font-weight: 800;
    }

    @media (max-width: 767px) {
        .kpi-page { padding: .85rem; }
        .kpi-hero { flex-direction: column; }
        .kpi-meta-grid { grid-template-columns: 1fr; }
        .kpi-excel-wrap.table-container { max-height: var(--kpi-table-max-height, calc(100dvh - 240px)); }
        .kpi-hero-card { max-width: 100%; width: 100%; }

        /* Disable sticky columns on mobile for better horizontal scrollability */
        .kpi-sticky-col-0,
        .kpi-sticky-col-1,
        .kpi-excel-table td.kpi-sticky-col-0,
        .kpi-excel-table td.kpi-sticky-col-1 {
            position: static !important;
            left: auto !important;
            z-index: auto !important;
            background: inherit !important;
            width: auto !important;
            min-width: auto !important;
            max-width: auto !important;
        }

        .kpi-excel-table thead th.kpi-sticky-col-0,
        .kpi-excel-table thead th.kpi-sticky-col-1 {
            position: sticky !important;
            left: auto !important;
            z-index: 4 !important;
            width: auto !important;
            min-width: auto !important;
            max-width: none !important;
        }

        .kpi-excel-table thead tr:first-child th.kpi-sticky-col-0,
        .kpi-excel-table thead tr:first-child th.kpi-sticky-col-1 {
            background: #004685 !important;
        }

        .kpi-excel-table thead tr:nth-child(2) th.kpi-sticky-col-0,
        .kpi-excel-table thead tr:nth-child(2) th.kpi-sticky-col-1 {
            background: #004a8d !important;
        }
    }

    @media (max-width: 991.98px), (max-height: 760px) {
        .kpi-page {
            padding: 0.85rem;
        }

        .kpi-hero {
            align-items: flex-start;
            gap: 0.65rem;
            margin-bottom: 0.75rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
        }

        .kpi-hero h1 {
            font-size: 1.32rem;
            line-height: 1.12;
        }

        .kpi-hero p {
            margin-top: 0.28rem;
            font-size: 0.76rem;
            line-height: 1.35;
            -webkit-line-clamp: 1;
        }

        .kpi-hero-card {
            min-width: 0;
            max-width: min(300px, 42vw);
            padding: 0.65rem 0.75rem;
            border-radius: 10px;
        }

        .kpi-hero-card strong {
            margin-top: 0.15rem;
            font-size: 1.05rem;
        }

        .kpi-toolbar,
        .kpi-meta-grid {
            margin-bottom: 0.75rem;
        }

        .kpi-toolbar {
            padding: 0.65rem;
            border-radius: 12px;
            align-items: stretch;
        }

        .kpi-tabs,
        .kpi-actions,
        .kpi-branch-filter,
        .kpi-period-filter {
            width: 100%;
        }

        .kpi-branch-filter select,
        .kpi-period-filter select {
            flex: 1 1 auto;
            min-width: 0;
        }

        .kpi-tab,
        .kpi-action {
            flex: 1 1 150px;
            justify-content: center;
        }

        .kpi-tab,
        .kpi-action {
            min-height: 34px;
            padding: 0.42rem 0.62rem;
            border-radius: 9px;
            font-size: 0.74rem;
        }

        .kpi-meta {
            padding: 0.58rem 0.75rem;
            border-radius: 11px;
        }

        .kpi-meta span {
            font-size: 0.64rem;
        }

        .kpi-meta strong {
            font-size: 0.92rem;
        }

        .kpi-table-title {
            padding: 0.6rem 0.75rem;
        }

        .kpi-excel-wrap.table-container {
            max-height: var(--kpi-table-max-height, calc(100dvh - 235px));
        }
    }

    @media (min-width: 768px) and (max-width: 1180px) {
        .kpi-hero {
            align-items: flex-start;
        }

        .kpi-meta-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .kpi-excel-table th,
        .kpi-excel-table td {
            min-width: 72px;
            padding: 0.28rem 0.38rem;
        }
    }

    @media (max-width: 767px) {
        .kpi-hero-card {
            max-width: 100%;
            width: 100%;
        }
    }

    @media (orientation: landscape) and (max-height: 640px) {
        .kpi-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .kpi-hero p,
        .kpi-meta-grid {
            display: none !important;
        }

        .kpi-excel-wrap.table-container {
            max-height: var(--kpi-table-max-height, calc(100dvh - 165px));
        }
    }
</style>

<div class="kpi-page">
    <div class="kpi-hero">
        <div>
            <div class="kpi-eyebrow">Dashboard Almafacts</div>
            <h1>{{ $selectedSheet['title'] ?? 'KPI' }}</h1>
            <p>Posisi {{ $selectedPeriodLabel }} dari Google Spreadsheet, ditampilkan sebagai tabel internal yang konsisten dan mudah dibandingkan antarperiode.</p>
        </div>
        <div class="kpi-hero-card">
            <span>Sheet Aktif</span>
            <strong>{{ $summary['sheet_name'] ?? $selectedSheet['sheet'] ?? '-' }}</strong>
        </div>
    </div>

    <div class="kpi-toolbar">
        <div class="kpi-tabs">
            @foreach($sheetOptions as $key => $sheet)
                <a href="{{ route('report.dashboard-almafacts.kpi', array_filter(['sheet' => $key, 'periode' => $selectedPeriod, 'cabang' => $kpiBranchFilter['selected'] !== 'all' ? $kpiBranchFilter['selected'] : null])) }}" class="kpi-tab {{ $selectedSheetKey === $key ? 'active' : '' }}">
                    <i class="{{ $sheet['icon'] }}"></i>
                    {{ $sheet['label'] }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('report.dashboard-almafacts.kpi') }}" class="kpi-period-filter">
            <input type="hidden" name="sheet" value="{{ $selectedSheetKey }}">
            @if(($kpiBranchFilter['selected'] ?? 'all') !== 'all')
                <input type="hidden" name="cabang" value="{{ $kpiBranchFilter['selected'] }}">
            @endif
            <label for="kpi-period-filter">Periode</label>
            <select id="kpi-period-filter" name="periode" onchange="this.form.submit()">
                @foreach($periodOptions as $periodValue => $periodLabel)
                    <option value="{{ $periodValue }}" @selected($selectedPeriod === $periodValue)>{{ $periodLabel }}</option>
                @endforeach
            </select>
        </form>
        @if($kpiBranchFilter['enabled'])
            <form method="GET" action="{{ route('report.dashboard-almafacts.kpi') }}" class="kpi-branch-filter">
                <input type="hidden" name="sheet" value="{{ $selectedSheetKey }}">
                <input type="hidden" name="periode" value="{{ $selectedPeriod }}">
                <label for="kpi-branch-filter">Cabang</label>
                <select id="kpi-branch-filter" name="cabang" {{ $kpiBranchFilter['locked'] ? 'disabled' : '' }} onchange="this.form.submit()">
                    @foreach($kpiBranchFilter['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected($kpiBranchFilter['selected'] === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @if($kpiBranchFilter['locked'])<input type="hidden" name="cabang" value="{{ $kpiBranchFilter['selected'] }}">@endif
            </form>
        @endif
        <div class="kpi-actions">
            <a href="{{ route('report.dashboard-almafacts.kpi', array_filter(['sheet' => $selectedSheetKey, 'periode' => $selectedPeriod, 'cabang' => $kpiBranchFilter['selected'] !== 'all' ? $kpiBranchFilter['selected'] : null, 'refresh' => 1])) }}" class="kpi-action primary">
                <i class="fas fa-sync-alt"></i>
                Refresh
            </a>
            <a href="{{ $spreadsheetUrl }}" target="_blank" rel="noopener" class="kpi-action">
                <i class="fas fa-external-link-alt"></i>
                Buka Spreadsheet
            </a>
        </div>
    </div>

    @if($error)
        <div class="kpi-alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ $error }}
        </div>
    @endif

    <div class="kpi-meta-grid">
        <div class="kpi-meta">
            <span>Periode</span>
            <strong>{{ $selectedPeriodLabel }}</strong>
        </div>
        <div class="kpi-meta">
            <span>Baris Data</span>
            <strong>{{ number_format((int) ($summary['row_count'] ?? 0), 0, ',', '.') }}</strong>
        </div>
        <div class="kpi-meta">
            <span>Kolom</span>
            <strong>{{ number_format((int) ($summary['column_count'] ?? 0), 0, ',', '.') }}</strong>
        </div>
        <div class="kpi-meta">
            <span>Update Cache</span>
            <strong>{{ $fetchedAt ? \Carbon\Carbon::parse($fetchedAt)->format('d M Y H:i') : '-' }}</strong>
        </div>
    </div>

    @foreach($tableSections as $section)
        @php
            $sectionRows = $section['rows'] ?? [];
            $sectionTitle = $section['title'] ?? ($summary['sheet_title'] ?? $selectedSheet['sheet'] ?? '-');
        @endphp
    <div class="kpi-table-panel" data-kpi-section="{{ $section['key'] ?? 'all' }}">
        <div class="kpi-table-title">
            <div>
                <strong>{{ $sectionTitle }}</strong>
                <span>{{ count($sectionRows) }} baris dari spreadsheet sumber.</span>
            </div>
            <span class="kpi-action">
                <i class="fas fa-table"></i>
                Google Sheet
            </span>
        </div>

        @if($displayColumns === [] || $sectionRows === [])
            <div class="kpi-empty">
                Data sheet belum tersedia.
            </div>
        @else
            <div class="kpi-excel-wrap table-container">
                <table class="kpi-excel-table kpi-table-{{ $selectedSheetKey }}-{{ $section['key'] ?? 'all' }}">
                    <thead>
                        <tr>
                            @forelse($headerGroups as $group)
                                @php
                                    $groupStart = (int) ($group['start'] ?? 0);
                                    $groupClass = ($groupStart === 0 ? 'kpi-sticky-col-0 ' : '')
                                        . ($groupStart === 1 ? 'kpi-sticky-col-1 ' : '')
                                        . ((int) ($group['colspan'] ?? 1) > 1 ? 'kpi-group-head ' : '');
                                @endphp
                                <th
                                    colspan="{{ (int) ($group['colspan'] ?? 1) }}"
                                    rowspan="{{ (int) ($group['rowspan'] ?? 1) }}"
                                    class="kpi-sortable {{ trim($groupClass) }}"
                                    data-sort-column="{{ $groupStart }}"
                                    title="{{ $group['label'] }}"
                                >
                                    {{ $group['label'] }}
                                </th>
                            @empty
                                @foreach($displayColumns as $column)
                                    @php
                                        $columnIndex = (int) ($column['index'] ?? $loop->index);
                                        $stickyClass = $columnIndex === 0 ? 'kpi-sticky-col-0' : ($columnIndex === 1 ? 'kpi-sticky-col-1' : '');
                                    @endphp
                                    <th class="kpi-sortable {{ $stickyClass }}" data-sort-column="{{ $columnIndex }}" title="{{ $column['label'] }}">
                                        {{ $column['label'] }}
                                    </th>
                                @endforeach
                            @endforelse
                        </tr>
                        @if($headerGroups !== [])
                            <tr>
                                @foreach($displayColumns as $column)
                                    @if(!empty($column['group']))
                                        <th class="kpi-sortable" data-sort-column="{{ (int) ($column['index'] ?? $loop->index) }}" title="{{ $column['label'] }}">
                                            {{ $column['label'] }}
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @foreach($sectionRows as $row)
                            <tr>
                                @foreach($displayColumns as $column)
                                    @php
                                        $columnIndex = (int) ($column['index'] ?? $loop->index);
                                        $value = $row[$columnIndex] ?? '';
                                        $class = $cellTone($value, (string) ($column['label'] ?? ''));
                                        $stickyClass = $columnIndex === 0 ? 'kpi-sticky-col-0' : ($columnIndex === 1 ? 'kpi-sticky-col-1' : '');
                                    @endphp
                                    <td class="{{ trim($class . ' ' . $stickyClass) }}" title="{{ $value }}">{{ $value === '' ? '-' : $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endforeach
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableWraps = Array.from(document.querySelectorAll('.kpi-excel-wrap'));
    let resizeFrame = null;

    const syncTableHeights = function () {
        resizeFrame = null;

        tableWraps.forEach(function (wrap) {
            const rect = wrap.getBoundingClientRect();
            const bottomGap = window.matchMedia('(max-width: 767px)').matches ? 18 : 24;
            const minHeight = window.matchMedia('(max-width: 767px)').matches ? 260 : 320;
            const availableHeight = Math.floor(window.innerHeight - rect.top - bottomGap);
            const maxHeight = Math.max(minHeight, availableHeight);
            wrap.style.setProperty('--kpi-table-max-height', maxHeight + 'px');
        });
    };

    const scheduleTableHeights = function () {
        if (resizeFrame !== null) {
            return;
        }

        resizeFrame = window.requestAnimationFrame(syncTableHeights);
    };

    scheduleTableHeights();
    window.addEventListener('resize', scheduleTableHeights);
    window.addEventListener('orientationchange', scheduleTableHeights);
    window.addEventListener('load', scheduleTableHeights);

    document.querySelectorAll('.kpi-excel-table').forEach(function (table) {
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }

        const adjustStickyHeaders = function () {
            scheduleTableHeights();

            const firstRow = table.querySelector('thead tr:first-child');
            const secondRow = table.querySelector('thead tr:nth-child(2)');
            if (firstRow && secondRow) {
                const firstRowHeight = Math.ceil(firstRow.getBoundingClientRect().height);
                table.style.setProperty('--kpi-sticky-header-row-height', firstRowHeight + 'px');
            }

            const firstStickyBodyCell = table.querySelector('tbody tr .kpi-sticky-col-0');
            const firstStickyHeaderCell = table.querySelector('thead .kpi-sticky-col-0');
            const firstStickyCell = firstStickyBodyCell || firstStickyHeaderCell;
            const firstStickyWidth = firstStickyCell
                ? firstStickyCell.getBoundingClientRect().width
                : 0;
            table.style.setProperty('--kpi-sticky-first-column-width', firstStickyWidth + 'px');
        };

        // Run adjustments
        adjustStickyHeaders();
        window.addEventListener('resize', adjustStickyHeaders);
        window.addEventListener('orientationchange', adjustStickyHeaders);

        // Also run once images/fonts are fully loaded
        window.addEventListener('load', adjustStickyHeaders);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(adjustStickyHeaders);
        }

        if ('ResizeObserver' in window) {
            const stickyGeometryObserver = new ResizeObserver(adjustStickyHeaders);
            const firstHeaderRow = table.querySelector('thead tr:first-child');
            if (firstHeaderRow) {
                stickyGeometryObserver.observe(firstHeaderRow);
            }
            stickyGeometryObserver.observe(table);
        }

        const parseValue = function (value) {
            const text = String(value || '').trim();
            if (text === '' || text === '-') {
                return { type: 'empty', value: '' };
            }

            const isNegative = text.startsWith('(') && text.endsWith(')');
            let clean = text.replace(/[()%\s]/g, '');
            clean = clean.replace(/rp|jt|m|t/gi, '');

            if (clean.includes(',') && clean.includes('.')) {
                clean = clean.replace(/\./g, '').replace(',', '.');
            } else if (clean.includes(',') && !clean.includes('.')) {
                clean = clean.replace(',', '.');
            } else {
                clean = clean.replace(/,/g, '');
            }

            const numeric = Number(clean);
            if (!Number.isNaN(numeric) && clean !== '') {
                return { type: 'number', value: isNegative ? numeric * -1 : numeric };
            }

            return { type: 'text', value: text.toLowerCase() };
        };

        table.querySelectorAll('th[data-sort-column]').forEach(function (header) {
            header.addEventListener('click', function () {
                const columnIndex = Number(header.dataset.sortColumn);
                const direction = header.classList.contains('sorted-asc') ? 'desc' : 'asc';
                const sortedRows = Array.from(tbody.querySelectorAll('tr')).sort(function (left, right) {
                    const leftValue = parseValue(left.cells[columnIndex] ? left.cells[columnIndex].textContent : '');
                    const rightValue = parseValue(right.cells[columnIndex] ? right.cells[columnIndex].textContent : '');

                    if (leftValue.type === 'empty' && rightValue.type !== 'empty') {
                        return 1;
                    }

                    if (rightValue.type === 'empty' && leftValue.type !== 'empty') {
                        return -1;
                    }

                    if (leftValue.type === 'number' && rightValue.type === 'number') {
                        return direction === 'asc'
                            ? leftValue.value - rightValue.value
                            : rightValue.value - leftValue.value;
                    }

                    return direction === 'asc'
                        ? String(leftValue.value).localeCompare(String(rightValue.value), 'id', { numeric: true })
                        : String(rightValue.value).localeCompare(String(leftValue.value), 'id', { numeric: true });
                });

                table.querySelectorAll('th.sorted-asc, th.sorted-desc').forEach(function (sortedHeader) {
                    sortedHeader.classList.remove('sorted-asc', 'sorted-desc');
                });
                header.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
                sortedRows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    });

    // Trigger floating scrollbar update on initialization
    setTimeout(function() {
        window.dispatchEvent(new Event('resize'));
        window.dispatchEvent(new Event('scroll'));
    }, 150);
    setTimeout(function() {
        window.dispatchEvent(new Event('resize'));
        window.dispatchEvent(new Event('scroll'));
    }, 600);
});
</script>
@endsection
