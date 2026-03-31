@extends('layouts.admin')

@section('title', 'Performance BRImo')

@section('content')

<style>
    /* 🔥 PERBAIKAN UI: Tabel elastis dan cerdas menyesuaikan ukuran layar */
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
    
    /* Pewarnaan Header Khas BRImo */
    .bg-brimo-ureg { background-color: #2F5597 !important; color: #ffffff !important; border-color: #203b6b !important; }
    .bg-brimo-ureg-fin { background-color: #5B9BD5 !important; color: #ffffff !important; border-color: #3f7bb5 !important; }
    .bg-brimo-usak { background-color: #A5A5A5 !important; color: #ffffff !important; border-color: #7b7b7b !important; }
    .bg-brimo-vol { background-color: #7030A0 !important; color: #ffffff !important; border-color: #5a2580 !important; }
    .bg-header-sub { background-color: #f1f5fa !important; color: #333 !important; font-weight: bold; }

    /* Conditional Formatting Latar Belakang Sel (%) */
    .bg-good { background-color: #d4edda !important; color: #155724 !important; font-weight: bold;}
    .bg-bad { background-color: #f8d7da !important; color: #721c24 !important; font-weight: bold;}

    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #003366 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; }
    .val-up { color: #28a745; font-weight: bold; margin-left: 2px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 2px; }
    
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 20px; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #2F5597; color: #2F5597; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #a6cbf3; }
    
    .label-date { display: block; font-size: 0.55rem; font-weight: normal; margin-top: 3px; color: #666; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Performance BRImo</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Performance BRImo</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- 🔥 CARD HEADER FILTER -->
            <div class="card card-outline card-primary shadow-sm mb-3">
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

            <!-- 🔥 TABEL DATA PERFORMANCE -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white p-0 border-bottom-0">
                    <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-brimo" role="tab" data-tab="brimo">
                                <i class="fas fa-mobile-alt mr-1"></i> Data Performance
                            </a>
                        </li>
                        <li class="nav-item ml-auto d-flex align-items-center pr-2">
                            <!-- Indikator Loading -->
                            <span id="loadingIndicator" class="text-primary font-weight-bold" style="display: none; font-size: 0.9rem;">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Memuat Data...
                            </span>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-brimo" role="tabpanel">
                            <div class="table-container">
                                <table class="table table-hover table-report m-0">
                                    <thead class="sticky-top" style="z-index: 2;">
                                        <!-- Header Utama Sesuai Gambar -->
                                        <tr>
                                            <th rowspan="2" class="bg-brimo-ureg align-middle" style="min-width: 150px;">BRANCH OFFICE</th>
                                            <th colspan="5" class="bg-brimo-ureg">Ureg BRImo (by Rekening)</th>
                                            <th colspan="5" class="bg-brimo-ureg-fin">Ureg BRImo (by Rk. Finansial)</th>
                                            <th colspan="5" class="bg-brimo-usak">Usak (User Aktif) BRImo<br><small>Trx Finansial > 3x / bulan</small></th>
                                            <th colspan="5" class="bg-brimo-vol">Volume Trx Fin BRImo<br><small>Akumulasi (Rp Milyar)</small></th>
                                        </tr>
                                        <!-- Sub-Header Metrik Dinamis -->
                                        <tr class="bg-header-sub">
                                            <!-- Sub Ureg by Rekening -->
                                            <th class="lbl-curr-th">-</th>
                                            <th>MtD</th>
                                            <th>YtD</th>
                                            <th>YoY</th>
                                            <th>YoY (%)</th>

                                            <!-- Sub Ureg by Rek Finansial -->
                                            <th class="lbl-curr-th">-</th>
                                            <th>MtD</th>
                                            <th>YtD</th>
                                            <th>YoY</th>
                                            <th>YoY (%)</th>

                                            <!-- Sub Usak -->
                                            <th class="lbl-curr-th">-</th>
                                            <th>MtD</th>
                                            <th>YtD</th>
                                            <th>YoY</th>
                                            <th>YoY (%)</th>

                                            <!-- Sub Volume -->
                                            <th class="lbl-curr-th">-</th>
                                            <th>MtD</th>
                                            <th>YtD</th>
                                            <th>YoY</th>
                                            <th>YoY (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-brimo">
                                        <!-- Data akan diisi oleh JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    let activeTab = 'brimo';
    let branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    // 🔥 FORMAT FUNCTIONS
    function formatNum(num) { 
        if (num === '-' || num === null || num === undefined) return '-';
        return new Intl.NumberFormat('id-ID').format(Math.round(num)); 
    }
    
    function formatGrowth(val, isPct = false) {
        if (val === '-' || val === null || val === undefined) return '-';
        let num = parseFloat(val);
        if (isNaN(num)) return '-';
        let text = isPct ? formatNum(num) + '%' : formatNum(num);
        if (num > 0) return `${text} <i class="fas fa-arrow-up val-up"></i>`;
        if (num < 0) return `${text} <i class="fas fa-arrow-down val-down"></i>`;
        return `${text} -`;
    }

    // Fungsi Khusus Formatting Cell % (Good = Hijau, Bad = Merah)
    function formatCellPct(val, isInverse = false) {
        if (val === '-' || val === null || val === undefined) return '<td>-</td>';
        let num = parseFloat(val);
        if (isNaN(num)) return '<td>-</td>';
        let text = formatNum(num) + '%';
        if (num === 0) return `<td>${text} -</td>`;

        let isGood = isInverse ? (num < 0) : (num > 0); // Jika inverse, Minus itu Bagus
        let bgClass = isGood ? 'bg-good' : 'bg-bad';
        let arrow = num > 0 ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>';

        return `<td class="${bgClass}">${text} ${arrow}</td>`;
    }

    // 🔥 LOAD DATA FUNCTION
    window.loadData = function() {
        $('#loadingIndicator').fadeIn('fast');
        
        let payload = {
            posisi: $('#filter_posisi').val(),
            tab: activeTab,
            branches: branches, // Pastikan branches terkirim agar controller tidak bingung
            id_report: 4,
            _token: '{{ csrf_token() }}'
        };

        // Ganti URL ke route report.data (sama seperti EDC) karena mengarah ke DataReportController
        $.ajax({
            url: "{{ route('report.data') }}",
            type: "POST",
            data: payload,
            success: function(res) {
                if(res.status === 'success') {
                    
                    // Update Teks Periode pada Sub Header (Label Bulan/Tahun dinamis)
                    $('.lbl-curr-th').text(res.labels.curr);
                    
                    // Render Data Tab
                    let html = '';
                    if (activeTab === 'brimo') {
                        res.data.forEach((row) => {
                            let rek = row.ureg_rekening || {};
                            let fin = row.ureg_finansial || {};

                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                
                                <!-- UREG REKENING -->
                                <td class="font-weight-bold">${formatNum(rek.curr)}</td>
                                <td>${formatGrowth(rek.mtd)}</td>
                                <td>${formatGrowth(rek.ytd)}</td>
                                <td>${formatGrowth(rek.yoy)}</td>
                                ${formatCellPct(rek.yoy_pct)}
                                
                                <!-- UREG FINANSIAL -->
                                <td class="font-weight-bold" style="background: #f4f8ff;">${formatNum(fin.curr)}</td>
                                <td>${formatGrowth(fin.mtd)}</td>
                                <td>${formatGrowth(fin.ytd)}</td>
                                <td>${formatGrowth(fin.yoy)}</td>
                                ${formatCellPct(fin.yoy_pct)}
                                
                                <!-- USAK (Dikosongkan sesuai permintaan) -->
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                
                                <!-- VOLUME TRX (Dikosongkan sesuai permintaan) -->
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                            </tr>`;
                        });
                        
                        // Add Total Row
                        let total = res.total || {};
                        let t_rek = total.ureg_rekening || {};
                        let t_fin = total.ureg_finansial || {};

                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch || 'TOTAL AREA 6'}</td>
                            
                            <!-- UREG REKENING TOTAL -->
                            <td>${formatNum(t_rek.curr)}</td>
                            <td>${formatGrowth(t_rek.mtd)}</td>
                            <td>${formatGrowth(t_rek.ytd)}</td>
                            <td>${formatGrowth(t_rek.yoy)}</td>
                            <td class="text-white">${formatGrowth(t_rek.yoy_pct, true)}</td>
                            
                            <!-- UREG FINANSIAL TOTAL -->
                            <td>${formatNum(t_fin.curr)}</td>
                            <td>${formatGrowth(t_fin.mtd)}</td>
                            <td>${formatGrowth(t_fin.ytd)}</td>
                            <td>${formatGrowth(t_fin.yoy)}</td>
                            <td class="text-white">${formatGrowth(t_fin.yoy_pct, true)}</td>
                            
                            <!-- USAK TOTAL -->
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            
                            <!-- VOLUME TRX TOTAL -->
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                        </tr>`;
                        
                        $('#tbody-brimo').html(html);
                    }
                } else {
                    alert('Respons server tidak valid. Harap cek format data.');
                }
                $('#loadingIndicator').fadeOut('fast');
            },
            error: function(xhr, status, error) {
                console.error('Error Output:', xhr.responseText);
                $('#loadingIndicator').fadeOut('fast');
                alert('Gagal mengambil data. Error: ' + error + '. Coba Refresh atau periksa koneksi.');
            }
        });
    }

    // Trigger otomatis saat tanggal diganti atau tab ditekan
    $('.filter-trigger').on('change', function() { loadData(); });
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) { activeTab = $(e.target).data('tab'); loadData(); });
    
    // Load pertama kali halaman dibuka
    loadData();
});
</script>
@endsection