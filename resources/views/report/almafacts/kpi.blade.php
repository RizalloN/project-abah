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
@endphp

@section('content')
@include('report._bri-report-ui')
<style>
    .kpi-page {
        padding: 1.25rem;
        color: #0f172a;
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
        font-size: clamp(1.35rem, 2.4vw, 2.35rem);
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
        grid-template-columns: repeat(3, minmax(160px, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }

    .kpi-meta {
        padding: .85rem 1rem;
        border: 1px solid #dbe5ef;
        border-radius: 14px;
        background: linear-gradient(180deg, #fff, #f8fbff);
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
    }

    .kpi-table-panel {
        border: 1px solid var(--bri-ui-border);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, .22);
        overflow: hidden;
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

    .kpi-excel-wrap {
        width: 100%;
        max-height: calc(100vh - 300px);
        overflow: auto;
        background: #f8fafc;
        scrollbar-width: thin;
        scrollbar-color: #9aa8bd #eef3f9;
    }

    .kpi-excel-table {
        width: max-content;
        min-width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
    }

    .kpi-excel-table th,
    .kpi-excel-table td {
        min-width: 92px;
        max-width: 240px;
        padding: .48rem .55rem;
        border-right: 1px solid #dbe5ef;
        border-bottom: 1px solid #dbe5ef;
        color: #102a4c;
        font-size: .75rem;
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
        top: 31px; /* Height of row 1 th */
        z-index: 4;
        background: #004a8d;
        font-size: .65rem;
        box-shadow: inset 0 -1px 0 rgba(255,255,255,.15);
    }

    .kpi-excel-table th {
        color: #fff;
        font-size: .7rem;
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

    .kpi-sticky-col-0 {
        position: sticky !important;
        left: 0 !important;
        z-index: 5;
        min-width: 86px;
        text-align: center;
    }

    .kpi-sticky-col-1 {
        position: sticky !important;
        left: 86px !important;
        z-index: 5;
        min-width: 245px;
        max-width: 320px;
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

    .kpi-table-mantri .kpi-sticky-col-0 {
        width: 270px;
        min-width: 270px;
        max-width: 270px;
        padding-left: .65rem;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri .kpi-sticky-col-1 {
        left: 270px !important;
        width: 92px;
        min-width: 92px;
        max-width: 92px;
        text-align: center;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(3),
    .kpi-table-mantri tbody td:nth-child(3) {
        width: 170px;
        min-width: 170px;
        max-width: 190px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(4),
    .kpi-table-mantri tbody td:nth-child(4) {
        width: 250px;
        min-width: 250px;
        max-width: 290px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(7),
    .kpi-table-mantri tbody td:nth-child(7) {
        width: 280px;
        min-width: 280px;
        max-width: 320px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(8),
    .kpi-table-mantri tbody td:nth-child(8) {
        width: 180px;
        min-width: 180px;
        max-width: 220px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-mantri thead tr:first-child th:nth-child(10),
    .kpi-table-mantri tbody td:nth-child(10) {
        width: 118px;
        min-width: 118px;
        max-width: 130px;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro .kpi-sticky-col-0 {
        width: 122px;
        min-width: 122px;
        max-width: 122px;
        text-align: center;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro .kpi-sticky-col-1 {
        left: 122px !important;
        width: 260px;
        min-width: 260px;
        max-width: 300px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro thead tr:first-child th:nth-child(4),
    .kpi-table-rm-mikro tbody td:nth-child(4) {
        width: 240px;
        min-width: 240px;
        max-width: 280px;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .kpi-table-rm-mikro thead tr:first-child th:nth-child(6),
    .kpi-table-rm-mikro tbody td:nth-child(6) {
        width: 118px;
        min-width: 118px;
        max-width: 130px;
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
        .kpi-excel-wrap { max-height: calc(100vh - 260px); }
    }
</style>

<div class="kpi-page">
    <div class="kpi-hero">
        <div>
            <div class="kpi-eyebrow">Dashboard Almafacts</div>
            <h1>{{ $selectedSheet['title'] ?? 'KPI' }}</h1>
            <p>Dashboard KPI bersumber dari Google Spreadsheet dan ditampilkan ulang sebagai tabel internal dengan gaya Excel yang konsisten dengan tema ABAH.</p>
        </div>
        <div class="kpi-hero-card">
            <span>Sheet Aktif</span>
            <strong>{{ $summary['sheet_name'] ?? $selectedSheet['sheet'] ?? '-' }}</strong>
        </div>
    </div>

    <div class="kpi-toolbar">
        <div class="kpi-tabs">
            @foreach($sheetOptions as $key => $sheet)
                <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => $key]) }}" class="kpi-tab {{ $selectedSheetKey === $key ? 'active' : '' }}">
                    <i class="{{ $sheet['icon'] }}"></i>
                    {{ $sheet['label'] }}
                </a>
            @endforeach
        </div>
        <div class="kpi-actions">
            <a href="{{ route('report.dashboard-almafacts.kpi', ['sheet' => $selectedSheetKey, 'refresh' => 1]) }}" class="kpi-action primary">
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

    <div class="kpi-table-panel">
        <div class="kpi-table-title">
            <div>
                <strong>{{ $summary['sheet_title'] ?? $selectedSheet['sheet'] ?? '-' }}</strong>
                <span>{{ $summary['row_count'] ?? 0 }} baris dari spreadsheet sumber.</span>
            </div>
            <span class="kpi-action">
                <i class="fas fa-table"></i>
                Google Sheet
            </span>
        </div>

        @if($displayColumns === [] || $rows === [])
            <div class="kpi-empty">
                Data sheet belum tersedia.
            </div>
        @else
            <div class="kpi-excel-wrap">
                <table class="kpi-excel-table kpi-table-{{ $selectedSheetKey }}">
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
                        @foreach($rows as $row)
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
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.kpi-excel-table').forEach(function (table) {
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
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
});
</script>
@endsection
