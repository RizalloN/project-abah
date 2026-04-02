@extends('layouts.admin')

@section('title', 'Performance Brilink')

@section('content')

<style>
    /* 🔥 UI Seragam Elastis */
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
    .table-container { width: 100%; overflow-x: hidden; }
    .table-report { border-collapse: collapse; width: 100%; table-layout: auto; }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        border: 1px solid #dee2e6;
        word-wrap: break-word;
        white-space: normal; 
    }
    
    /* Pewarnaan Header Custom Brilink */
    .bg-brilink-dark { background-color: #003366 !important; color: #ffffff !important; border-color: #002244 !important; }
    .bg-brilink-mid { background-color: #00509E !important; color: #ffffff !important; border-color: #003c7a !important; }
    .bg-brilink-light { background-color: #0073CF !important; color: #ffffff !important; border-color: #005aa3 !important; }
    .bg-header-sub { background-color: #f4f6fa !important; color: #333 !important; font-weight: bold; }
    
    .table-report th { font-size: 0.70rem; padding: 12px 6px; text-align: center; }
    .table-report td { font-size: 0.75rem; padding: 6px 8px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
    .row-total { --row-total-bg: #003366; --row-total-color: #ffffff; }
    .row-total td { background-color: #003366 !important; color: white !important; font-weight: bold; }
    
    .val-up { color: #28a745; margin-left: 3px; font-weight: bold; }
    .val-down { color: #dc3545; margin-left: 3px; font-weight: bold; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; border-color: #f6e3a6 !important; }
    .row-total .rka-col { background-color: #ffe8a1 !important; color: #856404 !important; }
    
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>

<div class="card card-outline card-warning shadow-sm mb-4 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control font-weight-bold" value="Performance Brilink" disabled>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                    <input type="text" class="form-control font-weight-bold" value="Area 6 - All" disabled>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Uker</label>
                    <input type="text" class="form-control" value="ALL UKER" disabled>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="text-dark text-sm font-weight-bold mb-1">Periode Bulan <i class="fas fa-edit text-warning ml-1"></i></label>
                    <!-- 🔥 FIX 1 FRONTEND: MENGGUNAKAN INPUT BULAN -->
                    <input type="month" id="filter_bulan" class="form-control border-warning shadow-sm filter-trigger" value="{{ date('Y-m') }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Posisi RKA</label>
                    <input type="text" class="form-control" disabled value="--------">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 report-data-card">
    <div class="card-header bg-white p-0 border-bottom-0">
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-brilink" role="tab">
                    <i class="fas fa-store mr-1"></i> Performance Brilink
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-agen-user" role="tab">
                    <i class="fas fa-users mr-1"></i> Agen Brilink (User)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-juragan" role="tab">
                    <i class="fas fa-user-tie mr-1"></i> Agen Juragan + Jawara
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-bep" role="tab">
                    <i class="fas fa-award mr-1"></i> Agen BEP
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-transaksi" role="tab">
                    <i class="fas fa-exchange-alt mr-1"></i> Transaksi Agen Brilink
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-casa" role="tab">
                    <i class="fas fa-wallet mr-1"></i> CASA Brilink
                </a>
            </li>
            <li class="nav-item ml-auto d-flex align-items-center pr-2">
                <span id="loadingIndicator" class="text-warning font-weight-bold" style="display: none; font-size: 0.9rem;">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Memuat Data...
                </span>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-0">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-brilink" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle">BRANCH OFFICE</th>
                                <th colspan="6" class="bg-brilink-mid">Agen Brilink</th>
                                <th colspan="6" class="bg-brilink-light">Agen Juragan/Jawara</th>
                                <th colspan="6" class="bg-brilink-mid">Agen BEP</th>
                                <th colspan="4" class="bg-brilink-light">Transaksi</th>
                                <th colspan="4" class="bg-brilink-mid">Volume <br><small>(Rp Milyar)</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <!-- AGEN BRILINK -->
                                <th><span class="lbl-curr text-primary ml-1"></span></th> <th>MtD</th> <th>YtD</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                <!-- AGEN JURAGAN -->
                                <th>Curr</th> <th>MtD</th> <th>YtD</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                <!-- AGEN BEP -->
                                <th>Curr</th> <th>MtD</th> <th>YtD</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                <!-- TRANSAKSI -->
                                <th>Curr</th> <th>MtD</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th>
                                <!-- VOLUME -->
                                <th>Curr</th> <th>MtD</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-brilink">
                            <tr><td colspan="27" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-3"></i></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-agen-user" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle">BRANCH OFFICE</th>
                                <th colspan="10" class="bg-brilink-mid">Jumlah Agen Brilink</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr text-primary">Feb-26</th>
                                <th>Des'25</th>
                                <th>31-Jan</th>
                                <th>28-Feb</th>
                                <th>MtD</th>
                                <th>MtD (%)</th>
                                <th>YtD</th>
                                <th>YtD (%)</th>
                                <th>YoY</th>
                                <th>YoY(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-agen-user"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-juragan" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle">BRANCH OFFICE</th>
                                <th colspan="10" class="bg-brilink-mid">Agen Juragan+Jawara</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr text-primary">Feb-26</th>
                                <th>Des'25</th>
                                <th>31-Jan</th>
                                <th>28-Feb</th>
                                <th>MtD</th>
                                <th>MtD (%)</th>
                                <th>YtD</th>
                                <th>YtD (%)</th>
                                <th>YoY</th>
                                <th>YoY(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-juragan"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-bep" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle">BRANCH OFFICE</th>
                                <th colspan="10" class="bg-brilink-mid">Agen BEP</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr text-primary">Feb-26</th>
                                <th>Des'25</th>
                                <th>31-Jan</th>
                                <th>28-Feb</th>
                                <th>MtD</th>
                                <th>MtD (%)</th>
                                <th>YtD</th>
                                <th>YtD (%)</th>
                                <th>YoY</th>
                                <th>YoY(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-bep-detail"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-transaksi" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle">BRANCH OFFICE</th>
                                <th colspan="5" class="bg-brilink-mid">Transaksi Agen Brilink</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr text-primary">Feb-26</th>
                                <th>Des'25</th>
                                <th>28-Feb</th>
                                <th>YoY</th>
                                <th>YoY(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-transaksi"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-casa" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle">BRANCH OFFICE</th>
                                <th colspan="10" class="bg-brilink-mid">CASA Agen Brilink</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr text-primary">Feb-26</th>
                                <th>Des'25</th>
                                <th>31-Jan</th>
                                <th>28-Feb</th>
                                <th>MtD</th>
                                <th>MtD (%)</th>
                                <th>YtD</th>
                                <th>YtD (%)</th>
                                <th>YoY</th>
                                <th>YoY(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-casa">
                            <tr><td colspan="11" class="text-center py-5 text-muted">Data CASA Brilink belum tersedia.</td></tr>
                        </tbody>
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
        return (num === null || num === undefined || isNaN(num)) ? '-' : new Intl.NumberFormat('id-ID').format(num); 
    }
    
    function formatMilyar(num) { 
        if(num === null || num === undefined || isNaN(num)) return '-';
        let val = num / 1000000000;
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val); 
    }
    
    function formatGrowth(val, isFloat = false) {
        let num = parseFloat(val);
        if (isNaN(num) || num === 0) return isFloat ? formatMilyar(num) : formatNum(num);
        
        let text = isFloat ? formatMilyar(num) : formatNum(num);
        if (num > 0) return `${text} <i class="fas fa-arrow-up val-up"></i>`;
        if (num < 0) return `${text} <i class="fas fa-arrow-down val-down"></i>`;
        return `${text}`;
    }

    function safeNum(num) {
        let val = parseFloat(num);
        return isNaN(val) ? 0 : val;
    }

    function calcPrev(curr, diff) {
        return safeNum(curr) - safeNum(diff);
    }

    function calcPct(diff, base) {
        base = safeNum(base);
        if (base === 0) return null;
        return (safeNum(diff) / base) * 100;
    }

    function renderMetricRow(label, metric, isMilyar = false) {
        const curr = safeNum(metric.curr);
        const prev = calcPrev(metric.curr, metric.mtd);
        const dec = calcPrev(metric.curr, metric.ytd);
        const yoyPrev = calcPrev(metric.curr, metric.yoy);
        const mtdPct = calcPct(metric.mtd, prev);
        const ytdPct = calcPct(metric.ytd, dec);
        const yoyPct = calcPct(metric.yoy, yoyPrev);
        const formatter = isMilyar ? formatMilyar : formatNum;

        return `<tr>
            <td class="text-left font-weight-bold text-dark">${label}</td>
            <td>${formatter(curr)}</td>
            <td>${formatter(dec)}</td>
            <td>${formatter(prev)}</td>
            <td>${formatter(curr)}</td>
            <td>${formatGrowth(metric.mtd, isMilyar)}</td>
            <td>${mtdPct === null ? '-' : formatGrowth(mtdPct)}</td>
            <td>${formatGrowth(metric.ytd, isMilyar)}</td>
            <td>${ytdPct === null ? '-' : formatGrowth(ytdPct)}</td>
            <td>${formatGrowth(metric.yoy, isMilyar)}</td>
            <td>${yoyPct === null ? '-' : formatGrowth(yoyPct)}</td>
        </tr>`;
    }

    function renderMetricTotalRow(label, metric, isMilyar = false) {
        const curr = safeNum(metric.curr);
        const prev = calcPrev(metric.curr, metric.mtd);
        const dec = calcPrev(metric.curr, metric.ytd);
        const mtdPct = calcPct(metric.mtd, prev);
        const ytdPct = calcPct(metric.ytd, dec);
        const yoyPrev = calcPrev(metric.curr, metric.yoy);
        const yoyPct = calcPct(metric.yoy, yoyPrev);
        const formatter = isMilyar ? formatMilyar : formatNum;

        return `<tr class="row-total">
            <td class="text-left">${label}</td>
            <td>${formatter(curr)}</td>
            <td>${formatter(dec)}</td>
            <td>${formatter(prev)}</td>
            <td>${formatter(curr)}</td>
            <td>${formatGrowth(metric.mtd, isMilyar)}</td>
            <td>${mtdPct === null ? '-' : formatGrowth(mtdPct)}</td>
            <td>${formatGrowth(metric.ytd, isMilyar)}</td>
            <td>${ytdPct === null ? '-' : formatGrowth(ytdPct)}</td>
            <td>${formatGrowth(metric.yoy, isMilyar)}</td>
            <td>${yoyPct === null ? '-' : formatGrowth(yoyPct)}</td>
        </tr>`;
    }

    // 🔥 FIX FINAL: Variabel global untuk menampung request AJAX (Mencegah Race Condition)
    let brilinkXhr = null;

    function loadDataBrilink() {
        const bulanAktif = $('#filter_bulan').val();

        // 🔥 Batalkan request sebelumnya jika belum selesai
        if (brilinkXhr && brilinkXhr.readyState !== 4) {
            brilinkXhr.abort();
        }

        $('#loadingIndicator').fadeIn('fast');
        
        brilinkXhr = $.ajax({
            url: "{{ route('report.data') }}",
            type: "POST",
            dataType: "json",
            cache: false,
            data: {
                periode_bulan : bulanAktif,
                tab: 'brilink',                  
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                
                // 🔥 STATE GUARD: Kalau user sudah ganti bulan lagi saat loading, abaikan response lama ini
                if (bulanAktif !== $('#filter_bulan').val()) return;

                if(res.status === 'success') {
                    
                    if (res.labels) {
                        $('.lbl-curr').text('Bulan Berjalan (' + res.labels.curr + ')');
                    }

                    let html = '';
                    let htmlAgenUser = '';
                    let htmlJuragan = '';
                    let htmlBep = '';
                    let htmlTrx = '';

                    res.data.forEach((row) => {
                        htmlAgenUser += renderMetricRow(row.branch, row.agen);
                        htmlJuragan += renderMetricRow(row.branch, row.juragan);
                        htmlBep += renderMetricRow(row.branch, row.bep);

                        const trxDec = calcPrev(row.trx.curr, row.trx.ytd);
                        const trxPrev = calcPrev(row.trx.curr, row.trx.yoy);
                        const trxYoyPct = calcPct(row.trx.yoy, trxPrev);
                        htmlTrx += `<tr>
                            <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                            <td class="font-weight-bold">${formatNum(row.trx.curr)}</td>
                            <td>${formatNum(trxDec)}</td>
                            <td>${formatNum(trxPrev)}</td>
                            <td>${formatGrowth(row.trx.yoy)}</td>
                            <td>${trxYoyPct === null ? '-' : formatGrowth(trxYoyPct)}</td>
                        </tr>`;

                        html += `<tr>
                            <td class="text-left font-weight-bold text-dark">${row.branch}</td>

                            <td class="font-weight-bold">${formatNum(row.agen.curr)}</td>
                            <td>${formatGrowth(row.agen.mtd)}</td>
                            <td>${formatGrowth(row.agen.ytd)}</td>
                            <td>${formatGrowth(row.agen.yoy)}</td>
                            <td class="rka-col text-muted">-</td>
                            <td class="rka-col text-muted">-</td>

                            <td class="font-weight-bold">${formatNum(row.juragan.curr)}</td>
                            <td>${formatGrowth(row.juragan.mtd)}</td>
                            <td>${formatGrowth(row.juragan.ytd)}</td>
                            <td>${formatGrowth(row.juragan.yoy)}</td>
                            <td class="rka-col text-muted">-</td>
                            <td class="rka-col text-muted">-</td>

                            <td class="font-weight-bold">${formatNum(row.bep.curr)}</td>
                            <td>${formatGrowth(row.bep.mtd)}</td>
                            <td>${formatGrowth(row.bep.ytd)}</td>
                            <td>${formatGrowth(row.bep.yoy)}</td>
                            <td class="rka-col text-muted">-</td>
                            <td class="rka-col text-muted">-</td>

                            <td class="font-weight-bold">${formatNum(row.trx.curr)}</td>
                            <td>${formatGrowth(row.trx.mtd)}</td>
                            <td>${formatGrowth(row.trx.yoy)}</td>
                            <td class="rka-col text-muted">-</td>

                            <td class="font-weight-bold">${formatMilyar(row.volume.curr)}</td>
                            <td>${formatGrowth(row.volume.mtd, true)}</td>
                            <td>${formatGrowth(row.volume.yoy, true)}</td>
                            <td class="rka-col text-muted">-</td>
                        </tr>`;
                    });

                    let total = res.total;
                    if (total) {
                        htmlAgenUser += renderMetricTotalRow(total.branch, total.agen);
                        htmlJuragan += renderMetricTotalRow(total.branch, total.juragan);
                        htmlBep += renderMetricTotalRow(total.branch, total.bep);

                        const totalTrxDec = calcPrev(total.trx.curr, total.trx.ytd);
                        const totalTrxPrev = calcPrev(total.trx.curr, total.trx.yoy);
                        const totalTrxYoyPct = calcPct(total.trx.yoy, totalTrxPrev);
                        htmlTrx += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            <td>${formatNum(total.trx.curr)}</td>
                            <td>${formatNum(totalTrxDec)}</td>
                            <td>${formatNum(totalTrxPrev)}</td>
                            <td>${formatGrowth(total.trx.yoy)}</td>
                            <td>${totalTrxYoyPct === null ? '-' : formatGrowth(totalTrxYoyPct)}</td>
                        </tr>`;

                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>

                            <td>${formatNum(total.agen.curr)}</td>
                            <td>${formatGrowth(total.agen.mtd)}</td>
                            <td>${formatGrowth(total.agen.ytd)}</td>
                            <td>${formatGrowth(total.agen.yoy)}</td>
                            <td class="rka-col text-dark">-</td>
                            <td class="rka-col text-dark">-</td>

                            <td>${formatNum(total.juragan.curr)}</td>
                            <td>${formatGrowth(total.juragan.mtd)}</td>
                            <td>${formatGrowth(total.juragan.ytd)}</td>
                            <td>${formatGrowth(total.juragan.yoy)}</td>
                            <td class="rka-col text-dark">-</td>
                            <td class="rka-col text-dark">-</td>

                            <td>${formatNum(total.bep.curr)}</td>
                            <td>${formatGrowth(total.bep.mtd)}</td>
                            <td>${formatGrowth(total.bep.ytd)}</td>
                            <td>${formatGrowth(total.bep.yoy)}</td>
                            <td class="rka-col text-dark">-</td>
                            <td class="rka-col text-dark">-</td>

                            <td>${formatNum(total.trx.curr)}</td>
                            <td>${formatGrowth(total.trx.mtd)}</td>
                            <td>${formatGrowth(total.trx.yoy)}</td>
                            <td class="rka-col text-dark">-</td>

                            <td>${formatMilyar(total.volume.curr)}</td>
                            <td>${formatGrowth(total.volume.mtd, true)}</td>
                            <td>${formatGrowth(total.volume.yoy, true)}</td>
                            <td class="rka-col text-dark">-</td>
                        </tr>`;
                    }

                    $('#tbody-brilink').html(html);
                    $('#tbody-agen-user').html(htmlAgenUser);
                    $('#tbody-juragan').html(htmlJuragan);
                    $('#tbody-bep-detail').html(htmlBep);
                    $('#tbody-transaksi').html(htmlTrx);
                } else if(res.status === 'error') {
                    $('#tbody-brilink').html(`<tr><td colspan="27" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>${res.msg}</td></tr>`);
                    $('#tbody-agen-user').html(`<tr><td colspan="11" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                    $('#tbody-juragan').html(`<tr><td colspan="11" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                    $('#tbody-bep-detail').html(`<tr><td colspan="11" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                    $('#tbody-transaksi').html(`<tr><td colspan="6" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                }
            },
            error: function(err) {
                // Abaikan error jika itu sengaja kita abort
                if (err.statusText === 'abort') return;

                $('#tbody-brilink').html('<tr><td colspan="27" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Gagal memuat data dari server.</td></tr>');
                $('#tbody-agen-user').html('<tr><td colspan="11" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
                $('#tbody-juragan').html('<tr><td colspan="11" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
                $('#tbody-bep-detail').html('<tr><td colspan="11" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
                $('#tbody-transaksi').html('<tr><td colspan="6" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
            },
            complete: function() {
                // Memindahkan fadeOut ke blok complete agar tetap tereksekusi baik sukses maupun gagal
                $('#loadingIndicator').fadeOut('fast');
            }
        });
    }

    // 🔥 Stabilkan month picker: cukup trigger saat nilai final berubah
    // Event "input" pada type="month" bisa menembak saat user sedang scroll/pindah nilai,
    // terutama memicu perilaku tidak stabil di Feb/Mar pada beberapa browser.
    $('#filter_bulan').off('change').on('change', function () {
        loadDataBrilink();
    });

    // Initial Load
    loadDataBrilink();
});
</script>
@endsection
