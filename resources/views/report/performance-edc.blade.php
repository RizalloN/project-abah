@extends('layouts.admin')

@section('title', 'Performance EDC')

@section('content')

<style>
    /* 🔥 PERBAIKAN UI: Tabel elastis dan cerdas menyesuaikan ukuran layar */
    .table-container { width: 100%; overflow-x: hidden; }
    .table-report { 
        border-collapse: collapse; 
        width: 100%; 
        table-layout: auto; 
    }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        border: 1px solid #dee2e6;
        word-wrap: break-word;
        white-space: normal; /* Mencabut aturan Anti-Wrap agar bisa menyusut */
    }
    .table-report th { font-size: 0.65rem; padding: 10px 4px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 4px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    /* Pewarnaan Header TAB 1 & 2 Sesuai Sebelumnya */
    .bg-mid-dark { background-color: #2b5cb5 !important; color: #ffffff !important; }
    .bg-prod-dark { background-color: #6c9ce8 !important; color: #ffffff !important; }
    .bg-sv-dark { background-color: #9baab8 !important; color: #ffffff !important; }
    .bg-header-sub { background-color: #f1f5fa !important; color: #333 !important; font-weight: bold; }
    
    .bg-tab2-dark { background-color: #2b5cb5 !important; color: #ffffff !important; border-color: #214b99 !important; }
    .bg-tab2-light { background-color: #92c0f0 !important; color: #000000 !important; border-color: #8eb7e3 !important; font-weight: bold; }
    .bg-tab2-sublight { background-color: #dae8f9 !important; color: #000000 !important; border-color: #c8d9ea !important; font-weight: bold; }
    
    /* 🔥 Pewarnaan Header TAB 3 (Produktivitas MoM) */
    .bg-mom-sv0 { background-color: #2956a8 !important; color: white !important; }
    .bg-mom-sv1 { background-color: #4b7bc9 !important; color: white !important; }
    .bg-mom-prod { background-color: #6c9ce8 !important; color: white !important; }
    .bg-mom-tid { background-color: #3b6bbd !important; color: white !important; }
    .bg-mom-svvol { background-color: #1f4282 !important; color: white !important; }

    /* Conditional Formatting Latar Belakang Sel (%) */
    .bg-good { background-color: #d4edda !important; color: #155724 !important; font-weight: bold;}
    .bg-bad { background-color: #f8d7da !important; color: #721c24 !important; font-weight: bold;}

    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #003366 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; }
    .val-up { color: #28a745; font-weight: bold; margin-left: 2px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 2px; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; border-color: #f6e3a6 !important; }
    .row-total .rka-col { background-color: #ffe8a1 !important; color: #856404 !important; }
    
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 20px; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #a6cbf3; }
</style>

<div class="card card-outline card-primary shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control font-weight-bold" value="Performance EDC" disabled>
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

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white p-0 border-bottom-0">
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-edc" role="tab" data-tab="edc">
                    <i class="fas fa-chart-line mr-1"></i> Performance EDC
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-mid" role="tab" data-tab="mid_tid">
                    <i class="fas fa-credit-card mr-1"></i> MID & TID
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-prod-mom" role="tab" data-tab="prod_mom">
                    <i class="fas fa-chart-bar mr-1"></i> Produktivitas EDC MoM
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
            
            <div class="tab-pane fade show active" id="tab-edc" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mid-dark align-middle">BRANCH OFFICE</th>
                                <th colspan="8" class="bg-mid-dark">Jumlah MID</th>
                                <th colspan="8" class="bg-prod-dark">EDC Merchant Produktif <br><small>SV >= 15 Juta/Bulan</small></th>
                                <th colspan="6" class="bg-sv-dark">SV Merchant EDC Akumulasi <br><small>(Rp Milyar)</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy">YoY</th> <th class="lbl-ytd">YtD</th> <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th>
                                <th>MtD</th> <th>MtD(%)</th> <th>YtD</th> <th>YoY</th>
                                <th class="lbl-curr">Curr</th> <th style="background: #e1e9f5;">% TID Prod.</th> <th>MtD</th> <th>MtD(%)</th>
                                <th>YtD</th> <th>YoY</th> <th>RKA</th> <th>Penc(%)</th>
                                <th class="lbl-curr">Curr</th> <th>MtD</th> <th>MtD(%)</th> <th>YoY</th> <th>RKA</th> <th>Penc(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-edc"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-mid" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-tab2-dark align-middle">REGIONAL / BRANCH OFFICE</th>
                                <th colspan="8" class="bg-tab2-dark">Jumlah MID</th>
                                <th colspan="10" class="bg-tab2-light">Jumlah TID</th>
                            </tr>
                            <tr>
                                <th class="bg-tab2-light lbl-yoy">YoY</th> <th class="bg-tab2-light lbl-ytd">YtD</th> <th class="bg-tab2-light lbl-mtd">MtD</th> <th class="bg-tab2-light lbl-curr">Curr</th>
                                <th class="bg-tab2-light">MtD</th> <th class="bg-tab2-light">MtD(%)</th> <th class="bg-tab2-light">YtD</th> <th class="bg-tab2-light">YoY</th>
                                
                                <th class="bg-tab2-sublight lbl-yoy">YoY</th> <th class="bg-tab2-sublight lbl-ytd">YtD</th> <th class="bg-tab2-sublight lbl-mtd">MtD</th> <th class="bg-tab2-sublight lbl-curr">Curr</th>
                                <th class="bg-tab2-sublight">MtD</th> <th class="bg-tab2-sublight">MtD(%)</th> <th class="bg-tab2-sublight">YtD</th> <th class="bg-tab2-sublight">YoY</th> 
                                <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc (%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-mid"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-prod-mom" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mom-sv0 align-middle">Regional Office</th>
                                <th colspan="4" class="bg-mom-sv0">SV 0</th>
                                <th colspan="4" class="bg-mom-sv1">SV 1 Juta - &lt;15 Juta</th>
                                <th colspan="7" class="bg-mom-prod">Produktif (&gt;= 15 Juta)</th>
                                <th colspan="4" class="bg-mom-tid">Total TID</th>
                                <th colspan="4" class="bg-mom-svvol">SV Bulan Berjalan (Rp Milyar)</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th> <th>MoM</th> <th>% MoM</th>
                                <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th> <th>MoM</th> <th>% MoM</th>
                                <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th> <th>MoM</th> <th>% MoM</th> <th class="rka-col">RKA</th> <th class="rka-col">Gap</th> <th class="rka-col">Penc(%)</th>
                                <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th> <th>MoM</th> <th>% MoM</th>
                                <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th> <th>MoM</th> <th>% MoM</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-prod-mom"></tbody>
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
    
    let activeTab = 'edc';

    function formatNum(num) { return new Intl.NumberFormat('id-ID').format(num); }
    
    function formatGrowth(val, isPct = false) {
        let num = parseFloat(val);
        let text = isPct ? formatNum(num) + '%' : formatNum(num);
        if (num > 0) return `${text} <i class="fas fa-arrow-up val-up"></i>`;
        if (num < 0) return `${text} <i class="fas fa-arrow-down val-down"></i>`;
        return `${text} -`;
    }

    // Fungsi Khusus Formatting Cell % MoM (Good = Hijau, Bad = Merah)
    function formatCellPct(val, isInverse = false) {
        let num = parseFloat(val);
        let text = formatNum(num) + '%';
        if (num === 0) return `<td>${text} -</td>`;

        let isGood = isInverse ? (num < 0) : (num > 0); // Jika inverse, Minus itu Bagus
        let bgClass = isGood ? 'bg-good' : 'bg-bad';
        let arrow = num > 0 ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>';

        return `<td class="${bgClass}">${text} ${arrow}</td>`;
    }

    function loadData() {
        $('#loadingIndicator').fadeIn('fast');
        
        let payload = {
            posisi: $('#filter_posisi').val(),
            tab: activeTab,
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

                    if (activeTab === 'edc') {
                        res.data.forEach((row) => {
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                <td>${formatNum(row.mid.yoy)}</td> <td>${formatNum(row.mid.ytd)}</td> <td>${formatNum(row.mid.mtd)}</td> <td class="font-weight-bold">${formatNum(row.mid.curr)}</td>
                                <td>${formatGrowth(row.mid.mtd_val)}</td> <td>${formatGrowth(row.mid.mtd_pct, true)}</td> <td>${formatGrowth(row.mid.ytd_val)}</td> <td>${formatGrowth(row.mid.yoy_val)}</td>
                                
                                <td class="font-weight-bold" style="background: #f4f8ff;">${formatNum(row.prod.curr)}</td> <td class="font-weight-bold text-primary" style="background: #e1e9f5;">${formatNum(row.prod.pct_tid)}%</td>
                                <td>${formatGrowth(row.prod.mtd_val)}</td> <td>${formatGrowth(row.prod.mtd_pct, true)}</td> <td>${formatGrowth(row.prod.ytd_val)}</td> <td>${formatGrowth(row.prod.yoy_val)}</td>
                                <td class="rka-col">${formatNum(row.prod.rka)}</td> <td class="rka-col">${formatNum(row.prod.penc_pct)}%</td>

                                <td class="font-weight-bold" style="background: #f8f9fa;">${formatNum(row.sv.curr)}</td>
                                <td>${formatGrowth(row.sv.mtd_val)}</td> <td>${formatGrowth(row.sv.mtd_pct, true)}</td> <td>${formatGrowth(row.sv.yoy_val)}</td>
                                <td class="rka-col">${formatNum(row.sv.rka)}</td> <td class="rka-col">${formatNum(row.sv.penc_pct)}%</td>
                            </tr>`;
                        });

                        let total = res.total;
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            <td>${formatNum(total.mid.yoy)}</td> <td>${formatNum(total.mid.ytd)}</td> <td>${formatNum(total.mid.mtd)}</td> <td>${formatNum(total.mid.curr)}</td>
                            <td>${formatGrowth(total.mid.mtd_val)}</td> <td>${formatGrowth(total.mid.mtd_pct, true)}</td> <td>${formatGrowth(total.mid.ytd_val)}</td> <td>${formatGrowth(total.mid.yoy_val)}</td>
                            
                            <td>${formatNum(total.prod.curr)}</td> <td>${formatNum(total.prod.pct_tid)}%</td>
                            <td>${formatGrowth(total.prod.mtd_val)}</td> <td>${formatGrowth(total.prod.mtd_pct, true)}</td> <td>${formatGrowth(total.prod.ytd_val)}</td> <td>${formatGrowth(total.prod.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatNum(total.prod.rka)}</td> <td class="rka-col text-dark">${formatNum(total.prod.penc_pct)}%</td>

                            <td>${formatNum(total.sv.curr)}</td>
                            <td>${formatGrowth(total.sv.mtd_val)}</td> <td>${formatGrowth(total.sv.mtd_pct, true)}</td> <td>${formatGrowth(total.sv.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatNum(total.sv.rka)}</td> <td class="rka-col text-dark">${formatNum(total.sv.penc_pct)}%</td>
                        </tr>`;
                        $('#tbody-edc').html(html);

                    } 
                    else if (activeTab === 'mid_tid') {
                        res.data.forEach((row) => {
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                <td>${formatNum(row.mid.yoy)}</td> <td>${formatNum(row.mid.ytd)}</td> <td>${formatNum(row.mid.mtd)}</td> <td class="font-weight-bold">${formatNum(row.mid.curr)}</td>
                                <td>${formatGrowth(row.mid.mtd_val)}</td> <td>${formatGrowth(row.mid.mtd_pct, true)}</td> <td>${formatGrowth(row.mid.ytd_val)}</td> <td>${formatGrowth(row.mid.yoy_val)}</td>
                                
                                <td>${formatNum(row.tid.yoy)}</td> <td>${formatNum(row.tid.ytd)}</td> <td>${formatNum(row.tid.mtd)}</td> <td class="font-weight-bold">${formatNum(row.tid.curr)}</td>
                                <td>${formatGrowth(row.tid.mtd_val)}</td> <td>${formatGrowth(row.tid.mtd_pct, true)}</td> <td>${formatGrowth(row.tid.ytd_val)}</td> <td>${formatGrowth(row.tid.yoy_val)}</td>
                                <td class="rka-col">${formatNum(row.tid.rka)}</td> <td class="rka-col font-weight-bold" style="color:#d99900;">${formatNum(row.tid.penc_pct)}%</td>
                            </tr>`;
                        });
                        
                        let total = res.total;
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            <td>${formatNum(total.mid.yoy)}</td> <td>${formatNum(total.mid.ytd)}</td> <td>${formatNum(total.mid.mtd)}</td> <td>${formatNum(total.mid.curr)}</td>
                            <td>${formatGrowth(total.mid.mtd_val)}</td> <td>${formatGrowth(total.mid.mtd_pct, true)}</td> <td>${formatGrowth(total.mid.ytd_val)}</td> <td>${formatGrowth(total.mid.yoy_val)}</td>
                            
                            <td>${formatNum(total.tid.yoy)}</td> <td>${formatNum(total.tid.ytd)}</td> <td>${formatNum(total.tid.mtd)}</td> <td>${formatNum(total.tid.curr)}</td>
                            <td>${formatGrowth(total.tid.mtd_val)}</td> <td>${formatGrowth(total.tid.mtd_pct, true)}</td> <td>${formatGrowth(total.tid.ytd_val)}</td> <td>${formatGrowth(total.tid.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatNum(total.tid.rka)}</td> <td class="rka-col text-dark">${formatNum(total.tid.penc_pct)}%</td>
                        </tr>`;
                        $('#tbody-mid').html(html);
                    }
                    
                    // TAB 3: PRODUKTIVITAS MoM
                    else if (activeTab === 'prod_mom') {
                        res.data.forEach((row) => {
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                
                                <td>${formatNum(row.sv0.mtd)}</td> <td>${formatNum(row.sv0.curr)}</td>
                                <td>${formatGrowth(row.sv0.mom)}</td> ${formatCellPct(row.sv0.pct, true)} 
                                
                                <td>${formatNum(row.sv1_15.mtd)}</td> <td>${formatNum(row.sv1_15.curr)}</td>
                                <td>${formatGrowth(row.sv1_15.mom)}</td> ${formatCellPct(row.sv1_15.pct, false)} 
                                
                                <td>${formatNum(row.prod.mtd)}</td> <td>${formatNum(row.prod.curr)}</td>
                                <td>${formatGrowth(row.prod.mom)}</td> ${formatCellPct(row.prod.pct, false)} 
                                <td class="rka-col">${formatNum(row.prod.rka)}</td> <td class="rka-col">${formatNum(row.prod.gap)}</td> <td class="rka-col">${formatNum(row.prod.penc)}%</td>
                                
                                <td>${formatNum(row.tid.mtd)}</td> <td>${formatNum(row.tid.curr)}</td>
                                <td>${formatGrowth(row.tid.mom)}</td> ${formatCellPct(row.tid.pct, false)} 
                                
                                <td>${formatNum(row.sv_vol.mtd)}</td> <td>${formatNum(row.sv_vol.curr)}</td>
                                <td>${formatGrowth(row.sv_vol.mom)}</td> ${formatCellPct(row.sv_vol.pct, false)} 
                            </tr>`;
                        });
                        
                        let total = res.total;
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            
                            <td>${formatNum(total.sv0.mtd)}</td> <td>${formatNum(total.sv0.curr)}</td>
                            <td>${formatGrowth(total.sv0.mom)}</td> <td class="text-white">${formatGrowth(total.sv0.pct, true)}</td>
                            
                            <td>${formatNum(total.sv1_15.mtd)}</td> <td>${formatNum(total.sv1_15.curr)}</td>
                            <td>${formatGrowth(total.sv1_15.mom)}</td> <td class="text-white">${formatGrowth(total.sv1_15.pct, true)}</td>
                            
                            <td>${formatNum(total.prod.mtd)}</td> <td>${formatNum(total.prod.curr)}</td>
                            <td>${formatGrowth(total.prod.mom)}</td> <td class="text-white">${formatGrowth(total.prod.pct, true)}</td>
                            <td class="rka-col text-dark">${formatNum(total.prod.rka)}</td> <td class="rka-col text-dark">${formatNum(total.prod.gap)}</td> <td class="rka-col text-dark">${formatNum(total.prod.penc)}%</td>
                            
                            <td>${formatNum(total.tid.mtd)}</td> <td>${formatNum(total.tid.curr)}</td>
                            <td>${formatGrowth(total.tid.mom)}</td> <td class="text-white">${formatGrowth(total.tid.pct, true)}</td>
                            
                            <td>${formatNum(total.sv_vol.mtd)}</td> <td>${formatNum(total.sv_vol.curr)}</td>
                            <td>${formatGrowth(total.sv_vol.mom)}</td> <td class="text-white">${formatGrowth(total.sv_vol.pct, true)}</td>
                        </tr>`;
                        $('#tbody-prod-mom').html(html);
                    }
                }
                $('#loadingIndicator').fadeOut('fast');
            }
        });
    }

    $('.filter-trigger').on('change', function() { loadData(); });
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) { activeTab = $(e.target).data('tab'); loadData(); });

    loadData();
});
</script>
@endsection