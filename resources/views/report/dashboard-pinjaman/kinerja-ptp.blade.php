@extends('layouts.admin')

@section('title', 'Kinerja PTP')

@section('content')
<style>
    .ptp-page {
        padding: 1.25rem 1rem 1.75rem;
        color: #0f172a;
    }

    .ptp-header {
        margin-bottom: 1rem;
        padding: 1.1rem 1.25rem;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 22px -18px rgba(15, 23, 42, .3);
    }

    .ptp-title {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .ptp-subtitle {
        margin-top: .25rem;
        color: #64748b;
        font-size: .85rem;
    }

    .ptp-panel {
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 22px -18px rgba(15, 23, 42, .26);
    }

    .ptp-panel-body {
        padding: 1rem;
    }

    .ptp-filter-label {
        display: block;
        margin-bottom: .35rem;
        color: #475569;
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .ptp-filter-control {
        min-height: 42px;
        border-color: #cbd5e1;
        border-radius: 7px;
        font-size: .88rem;
    }

    .ptp-action {
        min-height: 42px;
        border-radius: 7px;
        font-weight: 800;
    }

    .ptp-table-wrap {
        width: 100%;
        overflow: auto;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #f8fafc;
    }

    .ptp-table {
        width: 100%;
        min-width: 1500px;
        border-collapse: collapse;
        margin: 0;
        font-size: .78rem;
        white-space: nowrap;
    }

    .ptp-table th,
    .ptp-table td {
        border-right: 1px solid #d8dee8;
        border-bottom: 1px solid #d8dee8;
        padding: .38rem .5rem;
        vertical-align: middle;
    }

    .ptp-table th {
        text-align: center;
        color: #ffffff;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .ptp-table td {
        background: #ffffff;
        color: #111827;
        font-variant-numeric: tabular-nums;
    }

    .ptp-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .ptp-table tbody tr:hover td {
        background: #eef6ff;
    }

    .ptp-head-blue {
        background: #082c6c;
    }

    .ptp-head-blue-sub {
        background: #0c3478;
    }

    .ptp-head-orange {
        background: #d85a08;
    }

    .ptp-head-orange-sub {
        background: #c94f06;
    }

    .ptp-head-yellow {
        background: #fff200;
        color: #111827 !important;
    }

    .ptp-head-success {
        background: #c75308;
    }

    .ptp-left {
        text-align: left;
    }

    .ptp-right {
        text-align: right;
    }

    .ptp-center {
        text-align: center;
    }

    .ptp-total-row td {
        background: #fff7d6 !important;
        font-weight: 900;
    }

    .ptp-success-rate-cell {
        background:
            linear-gradient(90deg, rgba(var(--success-rgb, 250, 204, 21), .34) 0 var(--success-rate, 0%), rgba(var(--success-rgb, 250, 204, 21), .10) var(--success-rate, 0%) 100%) !important;
        color: #0f172a !important;
        font-weight: 900;
        box-shadow: inset 0 0 0 1px rgba(var(--success-rgb, 250, 204, 21), .24);
    }

    .ptp-success-rate-cell > span {
        display: inline-block;
        min-width: 58px;
        padding: .08rem .35rem;
        border-radius: 6px;
        background: rgba(255, 255, 255, .84);
        box-shadow: inset 0 0 0 1px rgba(var(--success-rgb, 250, 204, 21), .20);
    }

    .ptp-table tbody tr:hover td.ptp-success-rate-cell,
    .ptp-total-row td.ptp-success-rate-cell {
        background:
            linear-gradient(90deg, rgba(var(--success-rgb, 250, 204, 21), .40) 0 var(--success-rate, 0%), rgba(var(--success-rgb, 250, 204, 21), .12) var(--success-rate, 0%) 100%) !important;
    }

    .ptp-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 768px) {
        .ptp-page {
            padding-inline: .75rem;
        }

        .ptp-title {
            font-size: 1.2rem;
        }
    }

    /* Export Styles */
    .btn-export-pdf {
        min-height: 36px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #0f172a;
        font-weight: 800;
        letter-spacing: 0.025em;
        font-size: 0.72rem;
        padding: 0.45rem 1rem !important;
        box-shadow: 0 10px 20px -18px rgba(15, 23, 42, 0.3);
        transition: all 0.2s ease;
    }

    .btn-export-pdf:hover {
        background: #f8fbff;
        border-color: #0857c3;
        color: #0857c3;
        transform: translateY(-1px);
        box-shadow: 0 14px 28px -20px rgba(8, 87, 195, 0.4);
    }

    /* Capture Status Modal Premium Styles */
    .capture-status-modal .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    .capture-status-modal .modal-body {
        padding: 3rem 2rem;
    }

    .capture-status-modal-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
    }

    .icon-loading { background: rgba(8, 87, 195, 0.1); color: #0857c3; }
    .icon-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

    .capture-status-modal .btn-primary {
        border-radius: 12px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
</style>

@php
    $dimensionHeaders = match ($selectedLevel) {
        'per_uker' => ['bo' => 'BO', 'bc' => 'BC', 'mbm' => 'MBM', 'uker' => 'UKER'],
        'per_mantri' => ['bo' => 'BO', 'mbm' => 'MBM', 'bc' => 'BC', 'uker' => 'UKER', 'mantri' => 'MANTRI'],
        default => ['bo' => 'BO', 'mbm' => 'MBM'],
    };

    $successRateStyle = static function ($value): string {
        $rate = max(0, min(100, (float) $value));
        $stops = [
            ['rate' => 0, 'rgb' => [239, 68, 68]],
            ['rate' => 50, 'rgb' => [250, 204, 21]],
            ['rate' => 100, 'rgb' => [34, 197, 94]],
        ];

        $from = $stops[0];
        $to = $stops[1];
        if ($rate > 50) {
            $from = $stops[1];
            $to = $stops[2];
        }

        $span = max(1, $to['rate'] - $from['rate']);
        $mix = ($rate - $from['rate']) / $span;
        $rgb = array_map(
            static fn ($start, $end): int => (int) round($start + (($end - $start) * $mix)),
            $from['rgb'],
            $to['rgb']
        );

        return '--success-rate: ' . number_format($rate, 2, '.', '') . '%; --success-rgb: ' . implode(', ', $rgb) . ';';
    };
@endphp

<div class="ptp-page">
    <div class="ptp-header d-flex align-items-center justify-content-between">
        <div>
            <h1 class="ptp-title">Kinerja PTP</h1>
            <div class="ptp-subtitle">{{ $reportConfig['label'] }} | {{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }} | Posisi {{ $selectedPeriodLabel }}</div>
        </div>
        <button id="exportPdfBtn" class="btn btn-export-pdf">
            <i class="fas fa-file-pdf mr-2 text-danger"></i>EXPORT PDF
        </button>
    </div>

    <div class="ptp-panel mb-3">
        <div class="ptp-panel-body">
            <form method="GET" action="{{ route('report.dashboard-pinjaman.kinerja-ptp') }}">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="jenis">Jenis PTP</label>
                        <select id="jenis" name="jenis" class="form-control ptp-filter-control">
                            @foreach ($reportTypes as $key => $label)
                                <option value="{{ $key }}" @selected($key === $selectedReportType)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="level">Level Kinerja</label>
                        <select id="level" name="level" class="form-control ptp-filter-control">
                            @foreach ($levels as $key => $label)
                                <option value="{{ $key }}" @selected($key === $selectedLevel)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="periode">Periode</label>
                        <select id="periode" name="periode" class="form-control ptp-filter-control">
                            @forelse ($availablePeriods as $period)
                                <option value="{{ $period }}" @selected($period === $selectedPeriod)>{{ \Carbon\Carbon::parse($period)->locale('id')->translatedFormat('d F Y') }}</option>
                            @empty
                                <option value="">Tidak ada data</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary ptp-action w-100">
                            <i class="fas fa-filter mr-2"></i>Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="ptp-panel">
        <div class="ptp-panel-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div class="font-weight-bold">{{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }}</div>
                <div class="text-muted small">{{ $formatCount($rows->count()) }} baris</div>
            </div>

            @if ($rows->isEmpty())
                <div class="ptp-empty">Data belum tersedia untuk pilihan ini.</div>
            @else
                <div class="ptp-table-wrap" id="ptp-capture-area">
                    <table class="ptp-table">
                        <thead>
                            <tr>
                                @foreach ($dimensionHeaders as $label)
                                    <th rowspan="3" class="ptp-head-blue">{{ $label }}</th>
                                @endforeach
                                <th colspan="9" class="ptp-head-blue">{{ $reportConfig['total_heading'] }}</th>
                                <th colspan="4" class="ptp-head-orange">NPD Billing Sudah Muncul</th>
                                <th rowspan="3" class="ptp-head-success">Success<br>Rate</th>
                                <th colspan="2" class="ptp-head-yellow">Today</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="ptp-head-blue-sub">Total</th>
                                <th colspan="3" class="ptp-head-blue-sub">Sudah Muncul Billing</th>
                                <th colspan="3" class="ptp-head-blue-sub">Belum Muncul</th>
                                <th colspan="2" class="ptp-head-orange-sub">Sudah Bayar</th>
                                <th colspan="2" class="ptp-head-orange-sub">Belum Bayar</th>
                                <th colspan="2" class="ptp-head-yellow">&nbsp;</th>
                            </tr>
                            <tr>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-orange-sub">Rek</th>
                                <th class="ptp-head-orange-sub">Rupiah</th>
                                <th class="ptp-head-orange-sub">Rek</th>
                                <th class="ptp-head-orange-sub">Rupiah</th>
                                <th class="ptp-head-yellow">Rek</th>
                                <th class="ptp-head-yellow">Rupiah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach (array_keys($dimensionHeaders) as $key)
                                        <td class="ptp-left">{{ $row[$key] ?? '-' }}</td>
                                    @endforeach
                                    <td class="ptp-right">{{ $formatCount($row['total_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['total_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['total_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['sudah_billing_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['sudah_billing_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['sudah_billing_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['belum_muncul_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['belum_muncul_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['belum_muncul_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['sudah_bayar_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['sudah_bayar_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['belum_bayar_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['belum_bayar_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-center ptp-success-rate-cell" style="{{ $successRateStyle($row['success_rate'] ?? 0) }}">
                                        <span>{{ $formatPercent($row['success_rate'] ?? 0) }}</span>
                                    </td>
                                    <td class="ptp-right">{{ $formatCount($row['today_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['today_rupiah'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            <tr class="ptp-total-row">
                                <td colspan="{{ count($dimensionHeaders) }}" class="ptp-left">TOTAL</td>
                                <td class="ptp-right">{{ $formatCount($total['total_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['total_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['total_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['sudah_billing_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_billing_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_billing_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['belum_muncul_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_muncul_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_muncul_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['sudah_bayar_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_bayar_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['belum_bayar_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_bayar_rupiah'] ?? 0) }}</td>
                                <td class="ptp-center ptp-success-rate-cell" style="{{ $successRateStyle($total['success_rate'] ?? 0) }}">
                                    <span>{{ $formatPercent($total['success_rate'] ?? 0) }}</span>
                                </td>
                                <td class="ptp-right">{{ $formatCount($total['today_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['today_rupiah'] ?? 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Capture Status Modal -->
    <div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <!-- Loading State -->
                    <div id="captureProgressUI">
                        <div class="capture-status-modal-icon icon-loading">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Menyusun Laporan PDF</h4>
                        <p class="text-muted mb-0">Sedang mengolah tabel kinerja PTP ke dalam format PDF A4 Landscape. Mohon tunggu sebentar...</p>
                    </div>

                    <!-- Error State -->
                    <div id="captureErrorUI" class="d-none">
                        <div class="capture-status-modal-icon icon-error">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Gagal Ekspor PDF</h4>
                        <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kendala saat menyusun file PDF.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Tutup & Coba Lagi
                        </button>
                    </div>

                    <!-- Success State -->
                    <div id="captureSuccessUI" class="d-none">
                        <div class="capture-status-modal-icon icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="font-weight-bold mb-2">Ekspor Berhasil!</h4>
                        <p class="text-muted mb-4">Laporan PDF Kinerja PTP telah berhasil diunduh ke perangkat Anda.</p>
                        <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                            Selesai
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('vendor/html2pdf.bundle.min.js') }}"></script>
<script>
    $(function() {
        const exportBtn = document.getElementById('exportPdfBtn');
        const captureModal = document.getElementById('captureStatusModal');
        const progressUI = document.getElementById('captureProgressUI');
        const errorUI = document.getElementById('captureErrorUI');
        const successUI = document.getElementById('captureSuccessUI');
        const errorMessageUI = document.getElementById('captureErrorMessage');
        const captureArea = document.getElementById('ptp-capture-area');

        if (!exportBtn || !captureArea) return;

        exportBtn.addEventListener('click', async function() {
            if (window.jQuery) {
                window.jQuery(captureModal).modal('show');
            }
            
            progressUI.classList.remove('d-none');
            errorUI.classList.add('d-none');
            successUI.classList.add('d-none');

            if (typeof html2pdf === 'undefined') {
                alert('Library html2pdf belum dimuat. Mohon tunggu sebentar atau muat ulang halaman.');
                return;
            }

            const originalBtnHtml = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> EXPORTING...';

            try {
                const originalTable = captureArea.querySelector('table');
                if (!originalTable) throw new Error('Tabel data tidak ditemukan.');

                // 1. Ambil lebar asli tabel
                const tableRealWidth = originalTable.scrollWidth || 1500;

                // 2. Buat container isolasi
                const tempWrap = document.createElement('div');
                tempWrap.id = 'pdf-isolation-wrap';
                // Gunakan koordinat normal di dalam viewport (karena tertutup modal backdrop) agar tidak dibuang oleh html2canvas
                tempWrap.style.cssText = `position: absolute; left: 0; top: 0; width: ${tableRealWidth + 40}px; background: #ffffff; padding: 20px; z-index: 1030;`;

                // 3. Inject CSS secara eksplisit agar html2canvas tidak kehilangan styling
                const tempStyle = document.createElement('style');
                tempStyle.textContent = `
                    #pdf-isolation-wrap { font-family: "Inter", "Helvetica Neue", Helvetica, Arial, sans-serif; background: #ffffff; }
                    .pdf-header { margin-bottom: 20px; border-bottom: 3px solid #0857c3; padding-bottom: 15px; width: 100%; background: #ffffff; }
                    .pdf-title { font-size: 24px; font-weight: bold; color: #082c6c; margin: 0 0 5px 0; }
                    .pdf-subtitle { font-size: 14px; color: #475569; }
                    .ptp-table-clone { width: ${tableRealWidth}px; border-collapse: collapse; margin: 0; font-size: 12px; white-space: nowrap; background: #ffffff; }
                    .ptp-table-clone th, .ptp-table-clone td { border: 1px solid #d8dee8; padding: 6px 8px; vertical-align: middle; }
                    .ptp-table-clone th { text-align: center; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; }
                    .ptp-table-clone td { background: #ffffff; color: #111827; }
                    .ptp-table-clone tbody tr:nth-child(even) td { background: #fbfdff; }
                    .ptp-head-blue { background-color: #082c6c !important; color: #ffffff !important; }
                    .ptp-head-blue-sub { background-color: #0c3478 !important; color: #ffffff !important; }
                    .ptp-head-orange { background-color: #d85a08 !important; color: #ffffff !important; }
                    .ptp-head-orange-sub { background-color: #c94f06 !important; color: #ffffff !important; }
                    .ptp-head-yellow { background-color: #fff200 !important; color: #111827 !important; }
                    .ptp-head-success { background-color: #c75308 !important; color: #ffffff !important; }
                    .ptp-left { text-align: left !important; }
                    .ptp-right { text-align: right !important; }
                    .ptp-center { text-align: center !important; }
                    .ptp-total-row td { background-color: #fff7d6 !important; font-weight: bold !important; }
                    .ptp-success-rate-cell {
                        background:
                            linear-gradient(90deg, rgba(var(--success-rgb, 250, 204, 21), .34) 0 var(--success-rate, 0%), rgba(var(--success-rgb, 250, 204, 21), .10) var(--success-rate, 0%) 100%) !important;
                        color: #0f172a !important;
                        font-weight: bold !important;
                        box-shadow: inset 0 0 0 1px rgba(var(--success-rgb, 250, 204, 21), .24);
                    }
                    .ptp-success-rate-cell > span {
                        display: inline-block;
                        min-width: 58px;
                        padding: 2px 6px;
                        border-radius: 6px;
                        background: rgba(255, 255, 255, .84);
                        box-shadow: inset 0 0 0 1px rgba(var(--success-rgb, 250, 204, 21), .20);
                    }
                    .ptp-total-row td.ptp-success-rate-cell {
                        background:
                            linear-gradient(90deg, rgba(var(--success-rgb, 250, 204, 21), .40) 0 var(--success-rate, 0%), rgba(var(--success-rgb, 250, 204, 21), .12) var(--success-rate, 0%) 100%) !important;
                    }
                `;
                tempWrap.appendChild(tempStyle);

                // 4. Rekonstruksi HTML Murni (Membersihkan class sticky/absolute)
                let rawTableHtml = originalTable.innerHTML;
                // Buang class sticky-col jika ada di string HTML
                rawTableHtml = rawTableHtml.replace(/sticky-col/g, '');

                const contentHtml = `
                    <div class="pdf-header">
                        <h2 class="pdf-title">LAPORAN KINERJA PTP</h2>
                        <div class="pdf-subtitle">{{ $reportConfig['label'] }} | {{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }} - Posisi {{ $selectedPeriodLabel }}</div>
                    </div>
                    <table class="ptp-table-clone">
                        ${rawTableHtml}
                    </table>
                `;
                
                const contentContainer = document.createElement('div');
                contentContainer.innerHTML = contentHtml;
                tempWrap.appendChild(contentContainer);
                document.body.appendChild(tempWrap);

                // Tunggu font dan layout selesai di-render
                await new Promise(r => setTimeout(r, 800));

                const opt = {
                    margin:       [10, 10, 10, 10],
                    filename:     `Kinerja-PTP-{{ \Illuminate\Support\Str::slug($reportConfig['label']) }}-${new Date().toISOString().slice(0, 10)}.pdf`,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { 
                        scale: 1.5, 
                        useCORS: true, 
                        logging: false,
                        backgroundColor: '#ffffff',
                        windowWidth: tableRealWidth + 100, // Pastikan html2canvas melihat keseluruhan lebar
                        x: 0,
                        y: 0
                    },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' },
                    pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
                };

                // Generate PDF dari tempWrap (html2pdf akan memproses konten secara terisolasi)
                await html2pdf().set(opt).from(tempWrap).save();

                // Bersihkan DOM
                document.body.removeChild(tempWrap);

                progressUI.classList.add('d-none');
                successUI.classList.remove('d-none');
                
                if (window.jQuery) {
                    setTimeout(() => {
                        window.jQuery(captureModal).modal('hide');
                    }, 2000);
                }
            } catch (err) {
                console.error('PDF Export failed:', err);
                
                const existingWrap = document.getElementById('pdf-isolation-wrap');
                if (existingWrap) document.body.removeChild(existingWrap);

                progressUI.classList.add('d-none');
                errorUI.classList.remove('d-none');
                errorMessageUI.textContent = 'Gagal menyusun laporan PDF. Error: ' + err.message;
            } finally {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalBtnHtml;
            }
        });
    });
</script>
@endsection
