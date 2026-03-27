@extends('layouts.admin')

@section('title', 'Performance EDC')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet">

<style>
    .select2-container { width: 100% !important; }
    .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 38px !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
        background-color: #e9ecef !important; /* Warna abu-abu (disabled) */
    }
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
        background-color: #6c757d; /* Warna badge disabled */
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        margin-top: 5px;
    }
    
    .table-report { border-collapse: collapse; width: 100%; }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        white-space: nowrap; 
        border: 1px solid #dee2e6;
    }
    .table-report th { font-size: 0.85rem; padding: 12px 10px; text-align: center; }
    .table-report td { font-size: 0.85rem; padding: 8px 10px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    .bg-primary-dark { background-color: #003366 !important; color: #ffffff !important; }
    .bg-primary-mid { background-color: #00509E !important; color: #ffffff !important; }
    .bg-primary-light { background-color: #0073CF !important; color: #ffffff !important; }
    .bg-header-sub { background-color: #e9ecef !important; color: #495057 !important; font-weight: bold; }
    
    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #003366 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; }
    .val-up { color: #28a745; font-weight: bold; margin-left: 4px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 4px; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; }
    .row-total .rka-col { background-color: #ffe8a1 !important; color: #856404 !important; }
</style>

<div class="card card-outline card-primary shadow-sm mb-4">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
        <h3 class="card-title font-weight-bold text-primary" id="judulReportLabel">
            <i class="fas fa-chart-line mr-1"></i> Laporan: Performance EDC
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            
            <input type="hidden" id="filter_id_report" value="{{ $id_report }}">

            <div class="col-md-3">
                <div class="form-group">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control" value="Performance EDC" disabled>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                    <!-- Tampilan UI yang rapi untuk menggantikan Select2 yang berantakan -->
                    <input type="text" class="form-control font-weight-bold" value="Area 6 - All" disabled>
                    
                    <!-- Form Select Asli disembunyikan (d-none) agar JS tetap bisa mengambil nilainya -->
                    <select id="filter_branch" class="d-none" multiple="multiple">
                        @foreach($branches as $b)
                            <option value="{{ $b }}" selected>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="form-group">
                    <label class="text-muted text-sm mb-1">Nama Uker</label>
                    <input type="text" class="form-control" value="---" disabled>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="form-group">
                    <label class="text-dark text-sm font-weight-bold mb-1">Posisi Terakhir <i class="fas fa-edit text-primary ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="form-group">
                    <label class="text-muted text-sm mb-1">Posisi RKA</label>
                    <input type="text" id="filter_rka" class="form-control" disabled value="--------">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-info shadow-sm">
    <div class="card-header bg-white pb-2 pt-3">
        <h3 class="card-title font-weight-bold text-dark">
            <i class="fas fa-table text-info mr-1"></i> Tabel Hasil Evaluasi
        </h3>
        <div class="card-tools">
            <span id="loadingIndicator" class="badge badge-warning px-3 py-2" style="display: none; font-size: 0.85rem;">
                <i class="fas fa-spinner fa-spin mr-1"></i> Memuat Data...
            </span>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 650px; overflow-y: auto; overflow-x: auto;">
            <table class="table table-hover table-report m-0" id="reportTable">
                <thead class="sticky-top" style="z-index: 2;">
                    <tr>
                        <th rowspan="2" class="bg-primary-dark" style="min-width: 180px;">BRANCH OFFICE</th>
                        <th colspan="8" class="bg-primary-mid">Jumlah MID</th>
                        <th colspan="10" class="bg-primary-light">Jumlah TID</th>
                    </tr>
                    <tr class="bg-header-sub">
                        <th class="lbl-yoy">YoY</th>
                        <th class="lbl-ytd">YtD</th>
                        <th class="lbl-mtd">MtD</th>
                        <th class="lbl-curr">Curr</th>
                        <th>MtD</th>
                        <th>MtD(%)</th>
                        <th>YtD</th>
                        <th>YoY</th>
                        
                        <th class="lbl-yoy">YoY</th>
                        <th class="lbl-ytd">YtD</th>
                        <th class="lbl-mtd">MtD</th>
                        <th class="lbl-curr">Curr</th>
                        <th>MtD</th>
                        <th>MtD(%)</th>
                        <th>YtD</th>
                        <th>YoY</th>
                        <th>RKA</th>
                        <th>Penc(%)</th>
                    </tr>
                </thead>
                <tbody id="reportTbody">
                    </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    // Select2 hanya akan diaplikasikan ke class .select2 yang tidak d-none
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    function formatNum(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    function formatGrowth(val, isPct = false) {
        let num = parseFloat(val);
        let text = isPct ? formatNum(num) + '%' : formatNum(num);
        if (num > 0) return `${text} <i class="fas fa-arrow-up val-up"></i>`;
        if (num < 0) return `${text} <i class="fas fa-arrow-down val-down"></i>`;
        return `${text} -`;
    }

    function loadData() {
        $('#loadingIndicator').fadeIn('fast');
        $('#reportTbody').html('<tr><td colspan="19" class="text-center py-5"><i class="fas fa-circle-notch fa-spin fa-3x text-info mb-3"></i><br><span class="text-muted">Mengalkulasi Data...</span></td></tr>');

        let payload = {
            id_report: $('#filter_id_report').val(),
            
            // Ambil array Cabang dari select box hidden
            branches: Array.from(document.getElementById('filter_branch').options).map(opt => opt.value),
            
            ukers: [], // Kosong = ALL
            posisi: $('#filter_posisi').val(),
            _token: '{{ csrf_token() }}'
        };

        $.ajax({
            url: "{{ route('report.data') }}",
            type: "POST",
            data: payload,
            success: function(res) {
                if(res.status === 'success') {
                    
                    $('.lbl-yoy').text(res.labels.yoy);
                    $('.lbl-ytd').text(res.labels.ytd);
                    $('.lbl-mtd').text(res.labels.mtd);
                    $('.lbl-curr').text(res.labels.curr);

                    let html = '';

                    if (res.data.length === 0) {
                        html = '<tr><td colspan="19" class="text-center py-4 text-muted">Tidak ada data untuk tanggal yang dipilih.</td></tr>';
                    } else {
                        res.data.forEach((row) => {
                            html += `
                                <tr>
                                    <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                    
                                    <td>${formatNum(row.mid.yoy)}</td>
                                    <td>${formatNum(row.mid.ytd)}</td>
                                    <td>${formatNum(row.mid.mtd)}</td>
                                    <td class="font-weight-bold">${formatNum(row.mid.curr)}</td>
                                    <td>${formatGrowth(row.mid.mtd_val)}</td>
                                    <td>${formatGrowth(row.mid.mtd_pct, true)}</td>
                                    <td>${formatGrowth(row.mid.ytd_val)}</td>
                                    <td>${formatGrowth(row.mid.yoy_val)}</td>
                                    
                                    <td>${formatNum(row.tid.yoy)}</td>
                                    <td>${formatNum(row.tid.ytd)}</td>
                                    <td>${formatNum(row.tid.mtd)}</td>
                                    <td class="font-weight-bold">${formatNum(row.tid.curr)}</td>
                                    <td>${formatGrowth(row.tid.mtd_val)}</td>
                                    <td>${formatGrowth(row.tid.mtd_pct, true)}</td>
                                    <td>${formatGrowth(row.tid.ytd_val)}</td>
                                    <td>${formatGrowth(row.tid.yoy_val)}</td>
                                    <td class="rka-col text-muted">${formatNum(row.tid.rka)}</td>
                                    <td class="rka-col text-muted">${formatNum(row.tid.penc_pct)}%</td>
                                </tr>
                            `;
                        });

                        let total = res.total;
                        html += `
                            <tr class="row-total">
                                <td class="text-left">${total.branch}</td>
                                
                                <td>${formatNum(total.mid.yoy)}</td>
                                <td>${formatNum(total.mid.ytd)}</td>
                                <td>${formatNum(total.mid.mtd)}</td>
                                <td>${formatNum(total.mid.curr)}</td>
                                <td>${formatGrowth(total.mid.mtd_val)}</td>
                                <td>${formatGrowth(total.mid.mtd_pct, true)}</td>
                                <td>${formatGrowth(total.mid.ytd_val)}</td>
                                <td>${formatGrowth(total.mid.yoy_val)}</td>
                                
                                <td>${formatNum(total.tid.yoy)}</td>
                                <td>${formatNum(total.tid.ytd)}</td>
                                <td>${formatNum(total.tid.mtd)}</td>
                                <td>${formatNum(total.tid.curr)}</td>
                                <td>${formatGrowth(total.tid.mtd_val)}</td>
                                <td>${formatGrowth(total.tid.mtd_pct, true)}</td>
                                <td>${formatGrowth(total.tid.ytd_val)}</td>
                                <td>${formatGrowth(total.tid.yoy_val)}</td>
                                <td class="rka-col text-dark">${formatNum(total.tid.rka)}</td>
                                <td class="rka-col text-dark">${formatNum(total.tid.penc_pct)}%</td>
                            </tr>
                        `;
                    }

                    $('#reportTbody').html(html);
                }
                $('#loadingIndicator').fadeOut('fast');
            },
            error: function(err) {
                $('#reportTbody').html('<tr><td colspan="19" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Gagal memuat data dari server.</td></tr>');
                $('#loadingIndicator').fadeOut('fast');
            }
        });
    }

    // Trigger update data otomatis SAAT TANGGAL SAJA YANG BERUBAH
    $('.filter-trigger').on('change', function() {
        loadData();
    });

    // Initial Load
    loadData();
});
</script>
@endsection