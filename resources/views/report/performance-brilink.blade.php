@extends('layouts.admin')

@section('title', 'Performance Brilink')

@section('content')

<style>
    /* 🔥 UI Seragam Elastis */
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
    .row-total td { background-color: #003366 !important; color: white !important; font-weight: bold; }
    
    .val-up { color: #28a745; margin-left: 3px; font-weight: bold; }
    .val-down { color: #dc3545; margin-left: 3px; font-weight: bold; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; border-color: #f6e3a6 !important; }
    .row-total .rka-col { background-color: #ffe8a1 !important; color: #856404 !important; }
    
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 20px; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #ffc107; color: #ffc107; background: transparent; }
</style>

<div class="card card-outline card-warning shadow-sm mb-4">
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

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white p-0 border-bottom-0">
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-brilink" role="tab">
                    <i class="fas fa-store mr-1"></i> Laporan Transaksi Agen BRILink
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

                    res.data.forEach((row) => {
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
                } else if(res.status === 'error') {
                    $('#tbody-brilink').html(`<tr><td colspan="27" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>${res.msg}</td></tr>`);
                }
            },
            error: function(err) {
                // Abaikan error jika itu sengaja kita abort
                if (err.statusText === 'abort') return;

                $('#tbody-brilink').html('<tr><td colspan="27" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Gagal memuat data dari server.</td></tr>');
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