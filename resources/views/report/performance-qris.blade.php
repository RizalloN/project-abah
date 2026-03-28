@extends('layouts.admin')

@section('title', 'Performance QRIS')

@section('content')

<style>
    /* UI Seragam dengan Performance EDC */
    .table-container { width: 100%; overflow-x: hidden; }
    .table-report { border-collapse: collapse; width: 100%; table-layout: auto; }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        border: 1px solid #dee2e6;
        word-wrap: break-word;
        white-space: normal; 
    }
    .table-report th { font-size: 0.70rem; padding: 10px 4px; text-align: center; }
    .table-report td { font-size: 0.75rem; padding: 6px 6px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    /* Pewarnaan Khas QRIS (Tema Hijau/Tosca) */
    .bg-qris-dark { background-color: #0f6153 !important; color: #ffffff !important; border-color: #0a453b !important; }
    .bg-qris-mid { background-color: #178f7a !important; color: #ffffff !important; border-color: #116e5e !important; }
    .bg-qris-light { background-color: #23b59b !important; color: #ffffff !important; border-color: #1b8a76 !important; }
    .bg-header-sub { background-color: #effcf9 !important; color: #178f7a !important; font-weight: bold; }
    
    .table-hover tbody tr:hover { background-color: #f2fbf9; }
    .row-total { background-color: #0f6153 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; }
    .val-up { color: #28a745; font-weight: bold; margin-left: 2px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 2px; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; border-color: #f6e3a6 !important; }
    .row-total .rka-col { background-color: #ffe8a1 !important; color: #856404 !important; }
    
</style>

<div class="card card-outline card-success shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control font-weight-bold" value="Performance QRIS" disabled>
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
                    <label class="text-dark text-sm font-weight-bold mb-1">Posisi Terakhir <i class="fas fa-edit text-success ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-success shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
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
    <div class="card-header bg-white border-bottom-0 pb-3 pt-3">
        <h3 class="card-title font-weight-bold text-dark">
            <i class="fas fa-qrcode text-success mr-2"></i> Report: Laporan Akuisisi Merchant QRIS
        </h3>
    </div>
    
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table table-hover table-report m-0">
                <thead class="sticky-top" style="z-index: 2;">
                    <tr>
                        <th rowspan="2" class="bg-qris-dark align-middle">REGIONAL / BRANCH OFFICE</th>
                        <th colspan="8" class="bg-qris-mid">Akusisi QRIS Merchant (MtD / YtD)</th>
                        <th colspan="5" class="bg-qris-light">Total Sales Volume (Rp Juta)</th>
                    </tr>
                    <tr class="bg-header-sub">
                        <!-- Volume Akusisi -->
                        <th class="lbl-yoy">YoY</th> <th class="lbl-ytd">YtD</th> <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th>
                        <th>MoM</th> <th>MoM(%)</th> <th>YtD</th> <th>YoY</th>
                        
                        <!-- Sales Volume -->
                        <th class="lbl-mtd">Bulan Lalu</th> <th class="lbl-curr">Bulan Ini</th> 
                        <th>MoM</th> <th>MoM (%)</th>
                        <th class="rka-col text-dark">RKA</th>
                    </tr>
                </thead>
                <tbody id="tbody-qris">
                    <!-- Placeholder UI Sementara (Belum disambung ke AJAX DataReportController) -->
                    <tr><td colspan="15" class="text-center py-5 text-muted"><i class="fas fa-hammer fa-3x mb-3 text-secondary"></i><br>Tabel siap. Menunggu rumus kalkulasi Query Database QRIS dari Anda.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    // Fungsi ini siap disambungkan dengan DataReportController ketika rumusnya sudah ada.
    // Untuk saat ini, UI-nya sudah aktif dan selaras dengan EDC.
    
    $('.filter-trigger').on('change', function() {
        // loadData(); // Aktifkan nanti
    });

});
</script>
@endsection