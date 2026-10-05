<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performance Report {{ $report['scope_label'] }} {{ $report['period'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf2f7; color: #162a43; font: 12px Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 1; background: #fff; border-bottom: 1px solid #ccd8e5; padding: 14px 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        button { border: 0; border-radius: 5px; padding: 10px 18px; background: #00549f; color: #fff; font: bold 13px Arial, sans-serif; cursor: pointer; }
        button:disabled { opacity: .6; cursor: wait; }
        button:focus-visible { outline: 3px solid #f9aa45; outline-offset: 2px; }
        #status { color: #526980; }
        main { width: min(100% - 24px, 1000px); margin: 22px auto; padding: 28px; background: #fff; }
        .logos { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .logos img { width: 130px; height: 44px; object-fit: contain; }
        .logos img:last-child { width: 100px; }
        h1 { font-size: 19px; margin: 0 0 8px; color: #004886; }
        h2 { font-size: 14px; margin: 22px 0 12px; }
        .report-header { border-bottom: 2px solid #00549f; padding-bottom: 14px; }
        .report-header p { margin: 5px 0; }
        .map-heading { background: #00549f; color: #fff; padding: 12px 16px; border-radius: 5px; }
        .map svg { width: 100%; height: auto; display: block; }
        .map [data-office-code] { cursor: pointer; }
        .map [data-office-code]:focus { outline: none; }
        .map .is-selected .office-highlight { fill: #e7f0fa; stroke: #00549f; stroke-width: 2; }
        .note { font-size: 10px; line-height: 1.5; color: #526980; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .kpi { border: 1px solid #dce5f0; border-left: 3px solid #00549f; padding: 12px; border-radius: 6px; }
        .kpi h3 { margin: 0 0 10px; font-size: 12px; }
        .kpi strong { display: block; font-size: 18px; margin-bottom: 12px; }
        .kpi p { margin: 5px 0; display: flex; justify-content: space-between; gap: 8px; font-size: 10px; }
        .table-wrap { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        caption { text-align: left; font-size: 13px; font-weight: bold; padding: 12px 0; }
        th { background: #00549f; color: #fff; text-align: right; font-size: 9px; padding: 9px 5px; }
        td { padding: 8px 5px; text-align: right; border-bottom: 1px solid #e5edf5; font-variant-numeric: tabular-nums; white-space: nowrap; }
        th:first-child, td:first-child { text-align: left; white-space: normal; min-width: 125px; }
        tr:nth-child(even) td { background: #f7faff; }
        tr.total td { background: #eaf3fe; font-weight: bold; }
        .good { color: #007a58; } .bad { color: #ba2b2b; }
        @media (max-width: 640px) { main { padding: 16px; } .kpis { grid-template-columns: 1fr 1fr; } }
        @page { size: A4 portrait; margin: 12mm; }
        @media print { body { background: #fff; } .toolbar { display: none; } main { width: 100%; margin: 0; padding: 0; } .table-wrap { overflow: visible; } thead { display: table-header-group; } tr, .kpi { break-inside: avoid; } h2, caption { break-after: avoid; } * { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button id="downloadPdf" type="button">Unduh PDF</button>
        <span id="status" role="status">Laporan lengkap seluruh indikator dan unit dalam cakupan terpilih.</span>
    </div>
    <main>
        <header class="report-header">
            <div class="logos"><img src="{{ $report['logos']['danantara'] }}" alt="Danantara"><img src="{{ $report['logos']['bri'] }}" alt="BRI"></div>
            <h1>PT Bank Rakyat Indonesia (PERSERO) Tbk</h1>
            <p><b>Performance Report {{ $report['scope_label'] }} - Region 13 Malang</b></p>
            <p>Performance Report data {{ \Carbon\Carbon::parse($report['period'])->locale('id')->translatedFormat('d M y') }}</p>
        </header>
        @if ($report['geography']['ready'])
            @foreach ($report['geography']['maps'] as $map)
                <section class="map">
                    <h2 class="map-heading">Sebaran Unit Kerja {{ $map['label'] }}</h2>
                    {!! $map['svg'] !!}
                </section>
            @endforeach
            <p class="note">Sumber: {{ $report['geography']['source'] }}.</p>
        @else
            <p>Peta wilayah belum tersedia untuk cakupan ini.</p>
        @endif
        <h2>Ringkasan KPI {{ $report['scope_label'] }}</h2>
        <div class="kpis" id="kpis"></div>
        <p class="note" id="dataNote"></p>
        <div id="reportTables"></div>
    </main>
    <noscript>Aktifkan JavaScript untuk menampilkan tabel dan mengunduh PDF.</noscript>
    <script>window.keragaanPdfReport = {{ Illuminate\Support\Js::from($report) }};</script>
    <script src="{{ asset('adminlte/plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('adminlte/plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('js/keragaan-pdf.js') }}?v={{ filemtime(public_path('js/keragaan-pdf.js')) }}"></script>
</body>
</html>
