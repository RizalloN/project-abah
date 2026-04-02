@extends('layouts.admin')

@section('title', 'Performance BRImo')

@section('content')

<style>
    /* 🔥 KONSISTENSI UI: Sesuai Gambar Report */
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
    .table-report { border-collapse: collapse; width: 100%; table-layout: auto; }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        border: 1px solid #dee2e6;
        word-wrap: break-word;
        white-space: normal;
    }
    .table-report th { font-size: 0.65rem; padding: 10px 4px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 4px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    /* Pewarnaan Header Persis Gambar */
    .bg-brimo-main,
    .bg-header-branch { background-color: #003366 !important; color: #ffffff !important; font-weight: bold; }
    .bg-brimo-rek { background-color: #2F5597 !important; color: #ffffff !important; border-color: #203b6b !important; }
    .bg-brimo-fin { background-color: #5B9BD5 !important; color: #ffffff !important; border-color: #3f7bb5 !important; }
    .bg-brimo-usak { background-color: #A5A5A5 !important; color: #ffffff !important; border-color: #7b7b7b !important; }
    .bg-brimo-vol { background-color: #7030A0 !important; color: #ffffff !important; border-color: #5a2580 !important; }
    
    .bg-header-sub { background-color: #f1f5fa !important; color: #333 !important; font-weight: bold; }
    .bg-header-light-blue { background-color: #f1f5fa !important; color: #333 !important; font-weight: bold; }

    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    
    /* Baris Total */
    .bg-good { background-color: #d4edda !important; color: #155724 !important; font-weight: bold; }
    .bg-bad { background-color: #f8d7da !important; color: #721c24 !important; font-weight: bold; }
    .row-total { --row-total-bg: #003366; --row-total-color: #ffffff; background-color: #003366 !important; color: #ffffff !important; font-weight: bold; }
    .row-total td { color: #ffffff !important; }
    .row-total-blue { --row-total-bg: #003366; --row-total-color: #ffffff; background-color: #003366 !important; font-weight: bold; color: #ffffff !important; }
    .row-total-blue td { background-color: #003366 !important; color: #ffffff !important; }
    
    /* Indikator Panah */
    .text-success,
    .val-up { color: #28a745 !important; font-weight: bold; margin-left: 2px; }
    .text-danger,
    .val-down { color: #dc3545 !important; font-weight: bold; margin-left: 2px; }
    
    /* Style Tabs */
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active:hover { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:not(.active):hover { border-bottom: 3px solid transparent; color: #6c757d; background: transparent; }
</style>

<div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control font-weight-bold" value="Performance BRImo" disabled>
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
                    <label class="text-dark text-sm font-weight-bold mb-1">Posisi Terakhir <i class="fas fa-edit text-primary ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
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
        <!-- 🔥 TABS HEADER -->
        <!-- ðŸ”¥ TABS HEADER -->
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-brimo" role="tab">
                    <i class="fas fa-mobile-alt mr-1"></i> Performance Brimo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-ureg-rek" role="tab">
                    <i class="fas fa-user-plus mr-1"></i> Ureg BRImo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-ureg-fin" role="tab">
                    <i class="fas fa-university mr-1"></i> Ureg Rek Finansial
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-mau" role="tab">
                    <i class="fas fa-users mr-1"></i> MAU Brimo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-trx" role="tab">
                    <i class="fas fa-exchange-alt mr-1"></i> Transaksi Finansial
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
            
            <!-- 📊 TAB 1: OVERVIEW PERFORMANCE BRIMO -->
            <div class="tab-pane fade show active" id="tab-brimo" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brimo-main align-middle" style="min-width: 150px;">BRANCH OFFICE</th>
                                <th colspan="5" class="bg-brimo-rek">Ureg BRImo (by Rekening)</th>
                                <th colspan="5" class="bg-brimo-fin">Ureg BRImo (by Rk. Finansial)</th>
                                <th colspan="5" class="bg-brimo-usak">Usak (User Aktif) BRImo<br><small>Trx Finansial > 3x / bulan</small></th>
                                <th colspan="5" class="bg-brimo-vol">Volume Trx Fin BRImo<br><small>Akumulasi (Rp Milyar)</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr-th">-</th> <th>MtD</th> <th>YtD</th> <th>YoY</th> <th>YoY (%)</th>
                                <th class="lbl-curr-th">-</th> <th>MtD</th> <th>YtD</th> <th>YoY</th> <th>YoY (%)</th>
                                <th class="lbl-curr-th">-</th> <th>MtD</th> <th>YtD</th> <th>YoY</th> <th>YoY (%)</th>
                                <th class="lbl-curr-th">-</th> <th>MtD</th> <th>YtD</th> <th>YoY</th> <th>YoY (%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-brimo"></tbody>
                    </table>
                </div>
            </div>

            <!-- 📊 TAB 2: UREG BRIMO DETAIL (REKENING) -->
            <div class="tab-pane fade" id="tab-ureg-rek" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brimo-rek align-middle" style="min-width:150px;">BRANCH OFFICE</th>
                                <th colspan="8" class="bg-brimo-rek">User Reg BRImo (by Rekening)</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy-th">YoY Prev</th>
                                <th class="lbl-dec-th">Des</th>
                                <th class="lbl-prev-th">Prev</th>
                                <th class="lbl-curr-th text-primary">Hari Berjalan</th>
                                <th>MtD</th>
                                <th>MtD(%)</th>
                                <th>YtD</th>
                                <th>YoY</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-ureg-rek"></tbody>
                    </table>
                </div>
            </div>

            <!-- 📊 TAB 3: UREG FINANSIAL DETAIL -->
            <div class="tab-pane fade" id="tab-ureg-fin" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brimo-fin align-middle" style="min-width:150px;">BRANCH OFFICE</th>
                                <th colspan="8" class="bg-brimo-fin">User Rek Financial BRImo</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy-th">YoY Prev</th>
                                <th class="lbl-dec-th">Des</th>
                                <th class="lbl-prev-th">Prev</th>
                                <th class="lbl-curr-th text-primary">Hari Berjalan</th>
                                <th>MtD</th>
                                <th>MtD(%)</th>
                                <th>YtD</th>
                                <th>YoY</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-ureg-fin"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-mau" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <tbody id="tbody-mau">
                            <tr>
                                <td class="text-center py-5 text-muted"><strong>-</strong><br><small>Data MAU BRImo belum tersedia.</small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-trx" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <tbody id="tbody-trx">
                            <tr>
                                <td class="text-center py-5 text-muted"><strong>-</strong><br><small>Data Transaksi belum tersedia.</small></td>
                            </tr>
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

    // 🚀 FORMAT FUNCTIONS
    function formatNum(num, decimals = 0) { 
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        let val = parseFloat(num);
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(val); 
    }
    
    function formatGrowth(val, isPct = false) {
        if (val === null || val === undefined || isNaN(parseFloat(val))) return '-';
        let num = parseFloat(val);
        
        let text = isPct ? formatNum(Math.abs(num), 1) + '%' : formatNum(Math.abs(num), 0);
        
        if (num > 0) return `${text} <i class="fas fa-arrow-up text-success"></i>`;
        if (num < 0) return `${text} <i class="fas fa-arrow-down text-danger"></i>`;
        return `${text} -`;
    }

    // 🚀 LOAD DATA FUNCTION
    window.loadData = function() {
        $('#loadingIndicator').fadeIn('fast');
        
        let payload = {
            posisi: $('#filter_posisi').val(),
            id_report: 4,
            _token: '{{ csrf_token() }}' 
        };

        $.ajax({
            url: "{{ route('report.data.brimo') }}", 

            type: "POST",
            data: payload,
            success: function(res) {
                if(res.status === 'success') {
                    
                    let selectedDate = new Date($('#filter_posisi').val());
                    let monthList = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    let d = selectedDate.getDate();
                    let m = monthList[selectedDate.getMonth()];
                    let y = selectedDate.getFullYear().toString().substr(-2);
                    
                    let autoDateLabel = d + ' ' + m + " " + y; 
                    let autoMonthLabel = m + "'" + y;          
                    
                    let currDateLabel = (res.labels && res.labels.curr_date) ? res.labels.curr_date : autoDateLabel;   
                    let currMonthLabel = (res.labels && res.labels.curr_month) ? res.labels.curr_month : autoMonthLabel; 
                    
                    let yoyLabel = (res.labels && res.labels.yoy) ? res.labels.yoy : '-';
                    let decLabel = (res.labels && res.labels.ytd) ? res.labels.ytd : '-';
                    let prevLabel = (res.labels && res.labels.mtd) ? res.labels.mtd : '-';

                    // Update Semua Label Header
                    $('.lbl-curr-th').text(currDateLabel);
                    $('.lbl-rka-th').text('RKA ' + currMonthLabel); 
                    $('.lbl-yoy-th').text(yoyLabel);
                    $('.lbl-dec-th').text(decLabel);
                    $('.lbl-prev-th').text(prevLabel);
                    
                    // 🔥 PERBAIKAN: Persiapkan HTML untuk KETIGA TAB sekaligus
                    let htmlBrimo = '';
                    let htmlUregRek = '';
                    let htmlUregFin = '';

                    let dataList = res.data || [];
                    dataList.forEach((row) => {
                        let rek = row.ureg_rekening || {};
                        let fin = row.ureg_finansial || {};

                        // RENDER HTML TAB 1
                        htmlBrimo += `<tr>
                            <td class="text-left font-weight-bold">${row.branch || '-'}</td>
                            <td>${formatNum(rek.curr)}</td>
                            <td>${formatGrowth(rek.mtd)}</td> 
                            <td>${formatGrowth(rek.ytd)}</td> 
                            <td>${formatGrowth(rek.yoy)}</td>
                            <td>${formatGrowth(rek.yoy_pct, true)}</td>
                            <td style="background-color: #f6f9fc;">${formatNum(fin.curr)}</td>
                            <td>${formatGrowth(fin.mtd)}</td> 
                            <td>${formatGrowth(fin.ytd)}</td> 
                            <td>${formatGrowth(fin.yoy)}</td>
                            <td>${formatGrowth(fin.yoy_pct, true)}</td>
                            <td class="text-center">-</td> <td class="text-center">-</td> <td class="text-center">-</td> <td class="text-center">-</td> <td class="text-center">-</td>
                            <td class="text-center">-</td> <td class="text-center">-</td> <td class="text-center">-</td> <td class="text-center">-</td> <td class="text-center">-</td>
                        </tr>`;

                        // RENDER HTML TAB 2
                        htmlUregRek += `<tr>
                            <td class="text-left font-weight-bold">${row.branch || '-'}</td>
                            <td>${formatNum(rek.yoy_prev)}</td>
                            <td>${formatNum(rek.dec)}</td>
                            <td>${formatNum(rek.prev)}</td>
                            <td class="font-weight-bold" style="background-color: #f6f9fc;">${formatNum(rek.curr)}</td>
                            <td>${formatGrowth(rek.mtd)}</td>
                            <td>${formatGrowth(rek.mtd_pct, true)}</td>
                            <td>${formatGrowth(rek.ytd)}</td>
                            <td>${formatGrowth(rek.yoy)}</td>
                        </tr>`;

                        // RENDER HTML TAB 3
                        htmlUregFin += `<tr>
                            <td class="text-left font-weight-bold">${row.branch || '-'}</td>
                            <td>${formatNum(fin.yoy_prev)}</td>
                            <td>${formatNum(fin.dec)}</td>
                            <td>${formatNum(fin.prev)}</td>
                            <td class="font-weight-bold" style="background-color: #f6f9fc;">${formatNum(fin.curr)}</td>
                            <td>${formatGrowth(fin.mtd)}</td>
                            <td>${formatGrowth(fin.mtd_pct, true)}</td>
                            <td>${formatGrowth(fin.ytd)}</td>
                            <td>${formatGrowth(fin.yoy)}</td>
                        </tr>`;
                    });
                    
                    let totalData = res.total || {};
                    let t_rek = totalData.ureg_rekening || {};
                    let t_fin = totalData.ureg_finansial || {};

                    // TOTAL TAB 1
                    htmlBrimo += `<tr class="row-total">
                        <td class="text-left">${totalData.branch || 'TOTAL AREA 6'}</td>
                        <td>${formatNum(t_rek.curr)}</td> 
                        <td>${formatGrowth(t_rek.mtd)}</td> 
                        <td>${formatGrowth(t_rek.ytd)}</td> 
                        <td>${formatGrowth(t_rek.yoy)}</td>
                        <td>${formatGrowth(t_rek.yoy_pct, true)}</td>
                        <td style="background-color: #e2e8f0;">${formatNum(t_fin.curr)}</td> 
                        <td>${formatGrowth(t_fin.mtd)}</td> 
                        <td>${formatGrowth(t_fin.ytd)}</td> 
                        <td>${formatGrowth(t_fin.yoy)}</td>
                        <td>${formatGrowth(t_fin.yoy_pct, true)}</td>
                        <td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        <td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                    </tr>`;

                    // TOTAL TAB 2
                    htmlUregRek += `<tr class="row-total-blue">
                        <td class="text-left">TOTAL AREA 6</td>
                        <td>${formatNum(t_rek.yoy_prev)}</td>
                        <td>${formatNum(t_rek.dec)}</td>
                        <td>${formatNum(t_rek.prev)}</td>
                        <td class="font-weight-bold">${formatNum(t_rek.curr)}</td>
                        <td>${formatGrowth(t_rek.mtd)}</td>
                        <td>${formatGrowth(t_rek.mtd_pct, true)}</td>
                        <td>${formatGrowth(t_rek.ytd)}</td>
                        <td>${formatGrowth(t_rek.yoy)}</td>
                    </tr>`;

                    // TOTAL TAB 3
                    htmlUregFin += `<tr class="row-total-blue">
                        <td class="text-left">TOTAL AREA 6</td>
                        <td>${formatNum(t_fin.yoy_prev)}</td>
                        <td>${formatNum(t_fin.dec)}</td>
                        <td>${formatNum(t_fin.prev)}</td>
                        <td class="font-weight-bold">${formatNum(t_fin.curr)}</td>
                        <td>${formatGrowth(t_fin.mtd)}</td>
                        <td>${formatGrowth(t_fin.mtd_pct, true)}</td>
                        <td>${formatGrowth(t_fin.ytd)}</td>
                        <td>${formatGrowth(t_fin.yoy)}</td>
                    </tr>`;

                    // 🔥 TEMPELKAN KE KETIGA TAB SECARA BERSAMAAN
                    $('#tbody-brimo').html(htmlBrimo);
                    $('#tbody-ureg-rek').html(htmlUregRek);
                    $('#tbody-ureg-fin').html(htmlUregFin);
                }
                $('#loadingIndicator').fadeOut('fast');
            },
            error: function(xhr, status, error) {
                $('#loadingIndicator').fadeOut('fast');
                console.error(xhr.responseText);
                alert('Gagal mengambil data. Cek console log.');
            }
        });
    }

    // Hanya memuat ulang data bila form tanggal dirubah
    $('.filter-trigger').on('change', function() { loadData(); });
    
    // Auto Load saat halaman pertama dibuka
    loadData();
});
</script>
@endsection
