@extends('layouts.admin')

@section('title', 'Rasio CASA Debitur')

@section('content')

<style>
    /* KONSISTENSI UI: Tabel elastis dan cerdas menyesuaikan ukuran layar */
    .report-filter-card,
    .report-data-card {
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08) !important;
    }
    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: #ffffff;
    }
    .report-filter-card .form-control {
        border-radius: 10px;
        min-height: 40px;
    }
    .table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table-report {
        border-collapse: collapse;
        width: 100%;
        table-layout: auto;
        white-space: nowrap;
    }
    .table-report th, .table-report td {
        vertical-align: middle !important;
        border: 1px solid #dee2e6;
    }
    .table-report th { font-size: 0.65rem; padding: 10px 4px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 6px; text-align: right; }
    .table-report td.text-left { text-align: left; }

    /* Pewarnaan Header Khas */
    .bg-header-main { background-color: #0056b3 !important; color: #ffffff !important; border-color: #004085 !important; }
    .bg-header-sub { background-color: #a6a6a6 !important; color: #ffffff !important; font-weight: bold; border-color: #808080 !important; }
    .bg-header-sub-light { background-color: #d9d9d9 !important; color: #333 !important; font-weight: bold; border-color: #bfbfbf !important; }

    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total {
        --row-total-bg: #0056b3;
        --row-total-color: #ffffff;
        background-color: #0056b3 !important;
        color: #ffffff !important;
        font-weight: bold;
    }
    .row-total td {
        background-color: #0056b3 !important;
        color: #ffffff !important;
    }
    .row-total .ratio-negative,
    .row-total .ratio-positive,
    .row-total .ratio-neutral {
        background-color: #0056b3 !important;
        color: #ffffff !important;
    }
    .loading-row td { text-align: center !important; color: #6b7280; font-style: italic; padding: 18px 10px !important; }
    .loading-shimmer {
        display: inline-block;
        width: 100%;
        height: 14px;
        border-radius: 999px;
        background: linear-gradient(90deg, #e5e7eb 25%, #f8fafc 50%, #e5e7eb 75%);
        background-size: 200% 100%;
        animation: rasio-shimmer 1.25s linear infinite;
    }
    @keyframes rasio-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Warna teks khusus untuk Mtd */
    .val-up { color: #111111; font-weight: bold; }
    .val-down { color: #198754; font-weight: bold; }
    .ratio-positive {
        background-color: #d4edda !important;
        color: #111111 !important;
        font-weight: bold;
    }
    .ratio-negative {
        background-color: #f8d7da !important;
        color: #198754 !important;
        font-weight: bold;
    }
    .ratio-neutral {
        background-color: #f8f9fa !important;
        color: #111111 !important;
        font-weight: bold;
    }

    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>

<div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-dark text-sm font-weight-bold mb-1">Periode Akhir <i class="fas fa-edit text-primary ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                    <input type="text" class="form-control font-weight-bold" value="Area 6 - All" disabled>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Uker</label>
                    <input type="text" class="form-control" value="ALL UKER" disabled>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 report-data-card">
    <div class="card-header bg-white p-0 border-bottom-0">
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab">
                    <i class="fas fa-chart-pie mr-1"></i> Total
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-briguna-kpr" role="tab">
                    <i class="fas fa-home mr-1"></i> BRIGUNA & KPR
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-mikro-smc" role="tab">
                    <i class="fas fa-store mr-1"></i> MIKRO & SMC
                </a>
            </li>
            <li class="nav-item ml-auto d-flex align-items-center pr-2">
                <span id="loadingIndicator" class="text-primary font-weight-bold" style="display: none; font-size: 0.9rem;">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Memuat Data...
                </span>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-total" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">BRANCH OFFICE</th>
                                <th colspan="7" class="bg-header-main">TOTAL</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th colspan="2">Total OS</th>
                                <th colspan="2">Total CASA</th>
                                <th colspan="3">Rasio CASA/OS</th>
                            </tr>
                            <tr class="bg-header-sub-light">
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th>MtD</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-total"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-briguna-kpr" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">BRANCH OFFICE</th>
                                <th colspan="7" class="bg-header-main">BRIGUNA</th>
                                <th colspan="7" class="bg-header-main">KPR</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th colspan="2">Total OS</th>
                                <th colspan="2">Total CASA</th>
                                <th colspan="3">Rasio CASA/OS</th>
                                <th colspan="2">Total OS</th>
                                <th colspan="2">Total CASA</th>
                                <th colspan="3">Rasio CASA/OS</th>
                            </tr>
                            <tr class="bg-header-sub-light">
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th>MtD</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th>MtD</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-briguna-kpr"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-mikro-smc" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">BRANCH OFFICE</th>
                                <th colspan="7" class="bg-header-main">MIKRO</th>
                                <th colspan="7" class="bg-header-main">SMC</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th colspan="2">Total OS</th>
                                <th colspan="2">Total CASA</th>
                                <th colspan="3">Rasio CASA/OS</th>
                                <th colspan="2">Total OS</th>
                                <th colspan="2">Total CASA</th>
                                <th colspan="3">Rasio CASA/OS</th>
                            </tr>
                            <tr class="bg-header-sub-light">
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th>MtD</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th>MtD</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-mikro-smc"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function formatNum(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(parseFloat(num));
    }

    function formatPct(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(num)) + '%';
    }

    function getRatioClass(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '';
        const val = parseFloat(num);
        if (val < 0) return 'ratio-negative';
        if (val > 0) return 'ratio-positive';
        return 'ratio-neutral';
    }

    function formatMtd(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        const val = parseFloat(num);
        const text = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val) + '%';

        if (val > 0) return `<span class="val-up">+${text}</span>`;
        if (val < 0) return `<span class="val-down">${text}</span>`;
        return text;
    }

    function createDataCells(dt) {
        dt = dt || {};
        const rasioPrevClass = getRatioClass(dt.rasio_prev);
        const rasioCurrClass = getRatioClass(dt.rasio_curr);
        const mtdClass = getRatioClass(dt.mtd);
        return `
            <td>${formatNum(dt.os_prev)}</td>
            <td style="background-color: #f6f9fc;">${formatNum(dt.os_curr)}</td>
            <td>${formatNum(dt.casa_prev)}</td>
            <td style="background-color: #f6f9fc;">${formatNum(dt.casa_curr)}</td>
            <td class="${rasioPrevClass}">${formatPct(dt.rasio_prev)}</td>
            <td class="font-weight-bold ${rasioCurrClass}">${formatPct(dt.rasio_curr)}</td>
            <td class="${mtdClass}">${formatMtd(dt.mtd)}</td>
        `;
    }

    function renderLoadingState(message) {
        const loadingHtml = `
            <tr class="loading-row">
                <td colspan="15" class="text-center">${message || 'Mengambil data periode terakhir...'}</td>
            </tr>`;
        $('#tbody-total').html(loadingHtml.replace('15', '8'));
        $('#tbody-briguna-kpr, #tbody-mikro-smc').html(loadingHtml);
    }

    function renderEmptyState(message) {
        const emptyHtml = `
            <tr class="table-warning">
                <td colspan="15" class="text-center text-warning font-weight-bold">
                    <i class="fas fa-exclamation-triangle"></i> ${message}
                </td>
            </tr>`;
        $('#tbody-total').html(emptyHtml.replace('15', '8'));
        $('#tbody-briguna-kpr, #tbody-mikro-smc').html(emptyHtml);
    }

    window.loadData = function() {
        $('#loadingIndicator').fadeIn('fast');
        renderLoadingState('Menghitung rasio CASA debitur berdasarkan periode yang dipilih...');

        const payload = {
            posisi: $('#filter_posisi').val(),
            _token: '{{ csrf_token() }}'
        };

        console.log('%c[RasioCasa] Sending request with date:', 'color: blue; font-weight: bold', payload.posisi);

        $.ajax({
            url: "{{ route('report.data.rasiocasa') }}",
            type: 'POST',
            data: payload,
            dataType: 'json',
            timeout: 120000,
            success: function(res) {
                console.log('%c[RasioCasa] Response received:', 'color: green; font-weight: bold', res);

                if (res.status === 'success') {
                    const labels = res.labels || {};
                    const effectiveDates = res.effective_dates || {};
                    const dataList = res.data || [];
                    const totalData = res.total || {};
                    const meta = res.meta || {};
                    const currentDate = effectiveDates.curr || payload.posisi;
                    const hasAnyData = meta.has_rows === true || dataList.length > 0;

                    $('.lbl-prev-th').text(labels.prev || '-');
                    $('.lbl-curr-th').text(labels.curr || '-');

                    if (currentDate) {
                        $('#filter_posisi').val(currentDate);
                    }

                    if (!hasAnyData) {
                        renderEmptyState(`Tidak ada data untuk tanggal ${currentDate}. Coba pilih tanggal lain.`);
                        $('#loadingIndicator').fadeOut('fast');
                        return;
                    }

                    let htmlTotal = '';
                    let htmlBrigunaKpr = '';
                    let htmlMikroSmc = '';

                    dataList.forEach(function(row) {
                        const branchCell = `<td class="text-left font-weight-bold">${row.branch || '-'}</td>`;
                        htmlTotal += `<tr>${branchCell}${createDataCells(row.total)}</tr>`;
                        htmlBrigunaKpr += `<tr>${branchCell}${createDataCells(row.briguna)}${createDataCells(row.kpr)}</tr>`;
                        htmlMikroSmc += `<tr>${branchCell}${createDataCells(row.mikro)}${createDataCells(row.smc)}</tr>`;
                    });

                    const totalBranchCell = '<td class="text-left">TOTAL AREA 6</td>';
                    htmlTotal += `<tr class="row-total">${totalBranchCell}${createDataCells(totalData.total)}</tr>`;
                    htmlBrigunaKpr += `<tr class="row-total">${totalBranchCell}${createDataCells(totalData.briguna)}${createDataCells(totalData.kpr)}</tr>`;
                    htmlMikroSmc += `<tr class="row-total">${totalBranchCell}${createDataCells(totalData.mikro)}${createDataCells(totalData.smc)}</tr>`;

                    $('#tbody-total').html(htmlTotal);
                    $('#tbody-briguna-kpr').html(htmlBrigunaKpr);
                    $('#tbody-mikro-smc').html(htmlMikroSmc);
                } else {
                    renderEmptyState(res.message || 'Data tidak berhasil dimuat dari server.');
                    alert('Error loading data: ' + (res.message || 'Unknown error'));
                }

                $('#loadingIndicator').fadeOut('fast');
            },
            error: function(xhr, status, error) {
                $('#loadingIndicator').fadeOut('fast');
                let errorMsg = 'Gagal memuat data. ';
                if (xhr.status === 500) errorMsg += 'Server error. Periksa `storage/logs/laravel.log`';
                else if (status === 'timeout') errorMsg += 'Waktu tunggu habis. Kueri mungkin terlalu berat.';
                else errorMsg += `Error: ${error}.`;
                renderEmptyState(errorMsg);
                alert(errorMsg);
            }
        });
    };

    $('#filter_posisi').on('change', loadData);

    console.log('%c[RasioCasa] Initial load starting...', 'color: blue; font-weight: bold');
    loadData();
});
</script>
@endsection
