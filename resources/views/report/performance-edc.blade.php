@extends('layouts.admin')

@section('title', 'Performance EDC')

@section('content')

<style>
    .table-report { border-collapse: collapse; width: 100%; }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        white-space: nowrap; 
        border: 1px solid #dee2e6;
    }
    .table-report th { font-size: 0.80rem; padding: 10px 8px; text-align: center; }
    .table-report td { font-size: 0.85rem; padding: 6px 10px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    /* Pewarnaan Header Sesuai SS3 */
    .bg-mid-dark { background-color: #2b5cb5 !important; color: #ffffff !important; }
    .bg-prod-dark { background-color: #6c9ce8 !important; color: #ffffff !important; }
    .bg-sv-dark { background-color: #9baab8 !important; color: #ffffff !important; }
    .bg-header-sub { background-color: #f1f5fa !important; color: #333 !important; font-weight: bold; }
    
    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #003366 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; }
    .val-up { color: #28a745; font-weight: bold; margin-left: 4px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 4px; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; }
    .row-total .rka-col { background-color: #ffe8a1 !important; color: #856404 !important; }
    
    /* Desain Nav Tabs Activity */
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
                <div class="table-responsive" style="max-height: 650px; overflow-y: auto; overflow-x: auto;">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mid-dark align-middle" style="min-width: 160px; border-color: #1f4282;">BRANCH OFFICE</th>
                                <th colspan="8" class="bg-mid-dark" style="border-color: #1f4282;">Jumlah MID</th>
                                <th colspan="8" class="bg-prod-dark" style="border-color: #4b7bc9;">EDC Merchant Produktif <br><small>SV >= 15 Juta/Bulan</small></th>
                                <th colspan="6" class="bg-sv-dark" style="border-color: #798691;">SV Merchant EDC Akumulasi <br><small>(Rp Milyar)</small></th>
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
                <div class="table-responsive" style="max-height: 650px; overflow-y: auto; overflow-x: auto;">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mid-dark align-middle" style="min-width: 180px; border-color: #1f4282;">BRANCH OFFICE</th>
                                <th colspan="8"  class="bg-mid-dark" style="border-color: #1f4282;">Jumlah MID</th>
                                <th colspan="10" class="bg-sv-dark" style="border-color: #798691;">Jumlah TID</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy">YoY</th> <th class="lbl-ytd">YtD</th> <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th>
                                <th>MtD</th> <th>MtD(%)</th> <th>YtD</th> <th>YoY</th>
                                
                                <th class="lbl-yoy">YoY</th> <th class="lbl-ytd">YtD</th> <th class="lbl-mtd">MtD</th> <th class="lbl-curr">Curr</th>
                                <th>MtD</th> <th>MtD(%)</th> <th>YtD</th> <th>YoY</th> <th>RKA</th> <th>Penc(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-mid"></tbody>
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
                        // Render Table Tab 1 (EDC)
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

                    } else {
                        // Render Table Tab 2 (MID_TID)
                        res.data.forEach((row) => {
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                <td>${formatNum(row.mid.yoy)}</td> <td>${formatNum(row.mid.ytd)}</td> <td>${formatNum(row.mid.mtd)}</td> <td class="font-weight-bold">${formatNum(row.mid.curr)}</td>
                                <td>${formatGrowth(row.mid.mtd_val)}</td> <td>${formatGrowth(row.mid.mtd_pct, true)}</td> <td>${formatGrowth(row.mid.ytd_val)}</td> <td>${formatGrowth(row.mid.yoy_val)}</td>
                                
                                <td>${formatNum(row.tid.yoy)}</td> <td>${formatNum(row.tid.ytd)}</td> <td>${formatNum(row.tid.mtd)}</td> <td class="font-weight-bold">${formatNum(row.tid.curr)}</td>
                                <td>${formatGrowth(row.tid.mtd_val)}</td> <td>${formatGrowth(row.tid.mtd_pct, true)}</td> <td>${formatGrowth(row.tid.ytd_val)}</td> <td>${formatGrowth(row.tid.yoy_val)}</td>
                                <td class="rka-col">${formatNum(row.tid.rka)}</td> <td class="rka-col">${formatNum(row.tid.penc_pct)}%</td>
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
                }
                $('#loadingIndicator').fadeOut('fast');
            }
        });
    }

    // Trigger saat Tanggal Berubah
    $('.filter-trigger').on('change', function() {
        loadData();
    });

    // Trigger saat ganti Tab Navigasi
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        activeTab = $(e.target).data('tab');
        loadData();
    });

    // Initial Load
    loadData();
});
</script>
@endsection