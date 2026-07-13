@php
    $periodKeys = ['yoy', 'ytd', 'mtm', 'mtd', 'h2', 'h1'];
    $deltaLabels = ['ytd' => 'YTD', 'yoy' => 'YoY', 'mtm' => 'MtM', 'mtd' => 'MtD', 'dtd' => 'DtD'];
    $formatJuta = static function ($value): string {
        $number = (float) $value / 1000000;

        if (abs($number) >= 1000) {
            return number_format($number, 0, ',', '.');
        }

        return number_format($number, 1, ',', '.');
    };
    $formatDeltaJuta = static function ($value) use ($formatJuta): string {
        $number = (float) $value;

        if (abs($number) < 0.5) {
            return '0,0';
        }

        return ($number > 0 ? '+' : '') . $formatJuta($number);
    };
    $deltaClass = static function ($value): string {
        $number = (float) $value;

        if ($number > 0) {
            return 'positive';
        }

        if ($number < 0) {
            return 'negative';
        }

        return 'flat';
    };
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Export PDF Hourly DPK - {{ $export['scopeLabel'] ?? 'Area 6' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef5ff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 18px;
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 10px;
        }

        .toolbar button,
        .toolbar a {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #075aa6;
            padding: 8px 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button {
            background: #075aa6;
            color: #fff;
            border-color: #075aa6;
        }

        .report-header {
            border: 1px solid #b9d0ea;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            margin-bottom: 12px;
        }

        .report-header-top {
            padding: 14px 16px;
            background: linear-gradient(135deg, #073b78, #0b72d9);
            color: #fff;
        }

        .eyebrow {
            margin-bottom: 4px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.9;
        }

        h1 {
            margin: 0;
            font-size: 24px;
            line-height: 1.1;
        }

        .report-meta {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0;
            border-top: 1px solid #cfe1f6;
        }

        .meta-cell {
            padding: 9px 11px;
            border-right: 1px solid #dbe8f6;
            background: #f8fbff;
        }

        .meta-cell:last-child {
            border-right: 0;
        }

        .meta-label {
            display: block;
            color: #64748b;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .meta-value {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            font-weight: 800;
        }

        .section {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 13px;
            border: 1px solid #b9d0ea;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            padding: 10px 12px;
            border-bottom: 1px solid #dbe8f6;
            background: #f8fbff;
        }

        .section-title h2 {
            margin: 0;
            color: #073b78;
            font-size: 15px;
        }

        .section-note {
            max-width: 620px;
            margin: 0;
            color: #475569;
            font-size: 9px;
            line-height: 1.45;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        th,
        td {
            border-right: 1px solid #d8e5f3;
            border-bottom: 1px solid #d8e5f3;
            padding: 5px 6px;
            white-space: nowrap;
            text-align: right;
            vertical-align: middle;
        }

        th {
            background: #073b78;
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }

        thead tr:nth-child(2) th {
            background: #0b519d;
        }

        td {
            font-size: 8.5px;
            font-weight: 600;
        }

        tbody tr:nth-child(even) td {
            background: #f8fbff;
        }

        .left {
            text-align: left;
        }

        .no {
            width: 28px;
            text-align: center;
        }

        .branch {
            width: 72px;
        }

        .unit {
            width: 156px;
            white-space: normal;
            word-break: keep-all;
        }

        .positive {
            background: #dcfce7 !important;
            color: #047857;
            font-weight: 800;
        }

        .negative {
            background: #fee2e2 !important;
            color: #dc2626;
            font-weight: 800;
        }

        .flat {
            background: #fef3c7 !important;
            color: #a16207;
            font-weight: 800;
        }

        .total td {
            background: #fef08a !important;
            color: #10213a;
            font-weight: 900;
        }

        .empty {
            padding: 14px;
            color: #64748b;
            font-weight: 700;
            text-align: center;
        }

        .footer-note {
            margin-top: 8px;
            color: #64748b;
            font-size: 8.5px;
            line-height: 1.45;
        }

        @media print {
            html,
            body {
                width: 287mm;
                min-width: 287mm;
                margin: 0;
            }

            body {
                background: #fff;
                color: #06152b;
                font-size: 6.8px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page {
                width: 287mm;
                max-width: none;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .report-header {
                margin-bottom: 3mm;
                border-radius: 0;
            }

            .report-header-top {
                padding: 4mm 5mm;
            }

            .eyebrow {
                margin-bottom: 1mm;
                font-size: 6.5px;
            }

            h1 {
                font-size: 14px;
                line-height: 1.05;
            }

            .meta-cell {
                padding: 2mm 2.5mm;
            }

            .meta-label {
                font-size: 5.8px;
            }

            .meta-value {
                margin-top: 0.8mm;
                font-size: 7.2px;
            }

            .section {
                width: 287mm;
                min-height: 0;
                margin: 0 0 3mm;
                border-radius: 0;
                overflow: hidden;
                break-inside: avoid;
                page-break-inside: avoid;
                page-break-after: always;
            }

            .section:last-of-type {
                page-break-after: auto;
            }

            .section-title {
                min-height: 8mm;
                padding: 1.7mm 2.2mm;
                gap: 2mm;
            }

            .section-title h2 {
                font-size: 9px;
                line-height: 1.1;
            }

            .section-note {
                max-width: 152mm;
                font-size: 5.8px;
                line-height: 1.18;
            }

            table {
                width: 100%;
                table-layout: fixed;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            thead {
                display: table-header-group;
            }

            tr,
            th,
            td {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            th,
            td {
                padding: 1.05mm 1.15mm;
                font-size: 5.2px;
                line-height: 1.05;
                border-right-width: 0.35pt;
                border-bottom-width: 0.35pt;
            }

            th {
                font-size: 5px;
                line-height: 1.05;
            }

            td {
                font-size: 5.25px;
                font-weight: 700;
            }

            .no {
                width: 7mm;
            }

            .branch {
                width: 18mm;
            }

            .unit {
                width: 38mm;
                white-space: normal;
                line-height: 1.05;
            }

            .footer-note {
                margin-top: 2mm;
                font-size: 5.8px;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="toolbar">
            <a href="{{ route('report.dashboard-dana.hourly-dpk', ['cabang' => $selectedBranch ?? 'all', 'segmen' => $selectedSegment ?? 'all']) }}">Kembali</a>
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>

        <header class="report-header">
            <div class="report-header-top">
                <div class="eyebrow">Export PDF Hourly DPK</div>
                <h1>Hourly DPK - {{ $export['scopeLabel'] ?? 'Area 6' }}</h1>
            </div>
            <div class="report-meta">
                <div class="meta-cell">
                    <span class="meta-label">Posisi</span>
                    <span class="meta-value">{{ $export['selectedDateLabel'] ?? '-' }}</span>
                </div>
                <div class="meta-cell">
                    <span class="meta-label">Cabang</span>
                    <span class="meta-value">{{ $export['scopeLabel'] ?? 'Area 6' }}</span>
                </div>
                <div class="meta-cell">
                    <span class="meta-label">Segmen</span>
                    <span class="meta-value">{{ $filters['segments'][$selectedSegment ?? 'all'] ?? ($export['segmentLabel'] ?? 'Semua Segmen') }}</span>
                </div>
                <div class="meta-cell">
                    <span class="meta-label">Jam Terakhir</span>
                    <span class="meta-value">
                        @php
                            $latestHour = collect($export['hours'] ?? [])->firstWhere('key', $export['latestHour'] ?? '');
                        @endphp
                        {{ is_array($latestHour) ? ($latestHour['label'] ?? '-') : '-' }}
                    </span>
                </div>
                <div class="meta-cell">
                    <span class="meta-label">Dibuat</span>
                    <span class="meta-value">{{ $export['generatedAt'] ?? '-' }}</span>
                </div>
            </div>
        </header>

        @foreach (($export['tables'] ?? []) as $index => $table)
            @php
                $payload = (array) ($table['payload'] ?? []);
                $hours = (array) ($payload['hours'] ?? []);
                $periods = (array) ($payload['periods'] ?? []);
                $rows = (array) ($payload['rows'] ?? []);
                $total = (array) ($payload['total'] ?? []);
            @endphp
            <section class="section">
                <div class="section-title">
                    <h2>Tabel {{ $index + 1 }}. {{ $table['label'] ?? '-' }}</h2>
                    <p class="section-note"><strong>Keterangan:</strong> {{ $table['description'] ?? '-' }}</p>
                </div>

                @if (!($payload['ready'] ?? false))
                    <div class="empty">{{ $payload['message'] ?? 'Data belum tersedia.' }}</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2" class="no">No</th>
                                <th rowspan="2" class="left branch">Cabang</th>
                                <th rowspan="2" class="left unit">Unit Kerja</th>
                                <th colspan="6">Posisi Historis SSA Simpanan</th>
                                <th colspan="{{ max(1, count($hours)) }}">Posisi Hari Ini {{ $payload['selectedDateLabel'] ?? '-' }}</th>
                                <th colspan="{{ count($deltaLabels) }}">Delta thd Jam Terakhir</th>
                            </tr>
                            <tr>
                                @foreach ($periodKeys as $key)
                                    <th>{{ $dateFormatter($periods[$key] ?? null) }}</th>
                                @endforeach
                                @forelse ($hours as $hour)
                                    <th>{{ $hour['label'] ?? '-' }}</th>
                                @empty
                                    <th>-</th>
                                @endforelse
                                @foreach ($deltaLabels as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="no">{{ $row['no'] ?? '-' }}</td>
                                    <td class="left branch">{{ $row['branch'] ?? '-' }}</td>
                                    <td class="left unit">{{ $row['unit'] ?? '-' }}</td>
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($row['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse ($hours as $hour)
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
                                    <td colspan="{{ 9 + max(1, count($hours)) + count($deltaLabels) }}" class="empty">Data tidak ditemukan untuk filter ini.</td>
                                </tr>
                            @endforelse

                            @if ($rows !== [])
                                <tr class="total">
                                    <td class="no">#</td>
                                    <td class="left branch">{{ $payload['scopeLabel'] ?? '-' }}</td>
                                    <td class="left unit">GRAND TOTAL</td>
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($total['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse ($hours as $hour)
                                        <td>{{ $formatJuta($total['hour_values'][$hour['key']] ?? 0) }}</td>
                                    @empty
                                        <td>0,0</td>
                                    @endforelse
                                    @foreach ($deltaLabels as $key => $label)
                                        @php $deltaValue = $total['delta_values'][$key] ?? 0; @endphp
                                        <td class="{{ $deltaClass($deltaValue) }}">{{ $formatDeltaJuta($deltaValue) }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach

        <p class="footer-note">
            Sumber data: tabel hourly_dpk untuk posisi intraday dan SSA Simpanan untuk pembanding historis. Seluruh nominal ditampilkan dalam satuan Rp juta.
        </p>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 450);
        });
    </script>
</body>
</html>
