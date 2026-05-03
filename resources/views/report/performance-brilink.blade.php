@extends('layouts.admin')

@section('title', 'Performance Brilink')

@section('content')

<style>
    /* 🔥 UI Seragam Elastis */
    .report-filter-card,
    .report-data-card {
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: visible;
        box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08) !important;
    }
    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: #ffffff;
    }
    .report-filter-card .card-body {
        overflow: visible;
    }
    .report-filter-card .form-control {
        border-radius: 10px;
        min-height: 40px;
    }
    .branch-filter-dropdown,
    .uker-filter-dropdown {
        position: relative;
    }
    .branch-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        background: #fff;
    }
    .branch-dropdown-toggle:disabled {
        background: #e9ecef;
        cursor: not-allowed;
        opacity: 1;
    }
    .branch-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .branch-dropdown-menu,
    .uker-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 1050;
        display: none;
        width: 100%;
        max-height: 260px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        padding: 8px 0;
    }
    .branch-dropdown-menu.show,
    .uker-dropdown-menu.show {
        display: block;
    }
    .branch-dropdown-menu .dropdown-item,
    .uker-dropdown-menu .dropdown-item {
        padding: 0.45rem 1rem;
        cursor: pointer;
        margin-bottom: 0;
    }
    .branch-dropdown-menu .form-check,
    .uker-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .branch-dropdown-menu .form-check-input,
    .uker-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
    }
    .branch-dropdown-menu .form-check-label,
    .uker-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 500;
        cursor: pointer;
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
    
    /* .table-hover tbody tr:hover { background-color: #f8f9fa; } */
    .row-total { --row-total-bg: #003366; --row-total-color: #ffffff; }
    .row-total td { background-color: #003366 !important; color: white !important; font-weight: bold; }
    
    .val-up { color: #28a745; margin-left: 3px; font-weight: bold; }
    .val-down { color: #dc3545; margin-left: 3px; font-weight: bold; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; border-color: #f6e3a6 !important; }
    .row-total .rka-col { background-color: #003366 !important; color: #ffffff !important; }
    
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>
@include('report._bri-report-ui')
<style>
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr.row-total > td,
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr.row-total > th,
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr.row-total > td.rka-col,
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr.row-total > th.rka-col {
        background-color: #003366 !important;
        background-image: none !important;
        color: #ffffff !important;
        font-weight: bold;
    }
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr:hover > td,
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr:hover > th {
        background-color: #ffffff !important;
        background-image: none !important;
    }
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr:nth-child(even):not(.row-total):hover > td,
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr:nth-child(even):not(.row-total):hover > th {
        background-color: #fafcff !important;
    }
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr:hover > td.rka-col {
        background-color: #fff3cd !important;
    }
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr.row-total:hover > td,
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr.row-total:hover > th {
        background-color: #003366 !important;
        background-image: none !important;
        color: #ffffff !important;
    }
    .content-wrapper .table-container table.table-report.brilink-no-hover tbody tr.row-total:hover > td.rka-col {
        background-color: #003366 !important;
        color: #ffffff !important;
    }
</style>

<div class="pt-4">
    <div class="card card-outline card-warning shadow-sm mb-4 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="d-none">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control font-weight-bold" value="Performance Brilink" disabled>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                    <div class="branch-filter-dropdown">
                        <button type="button" class="form-control font-weight-bold branch-dropdown-toggle" id="filterBranchDropdown" aria-haspopup="true" aria-expanded="false">
                            <span id="filter_branch_office_label" class="branch-dropdown-label">Area 6 - All</span>
                            <i class="fas fa-chevron-down text-muted"></i>
                        </button>
                        <div class="branch-dropdown-menu" id="filterBranchMenu" aria-labelledby="filterBranchDropdown">
                            @forelse(($branchOptions ?? collect()) as $branchOption)
                                <label class="dropdown-item" for="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                    <div class="form-check">
                                        <input class="form-check-input filter-branch-checkbox" type="checkbox" value="{{ $branchOption }}" id="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                        <span class="form-check-label">{{ $branchOption }}</span>
                                    </div>
                                </label>
                            @empty
                                <div class="dropdown-item text-muted small px-3">Data branch belum tersedia</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Uker</label>
                    <div class="uker-filter-dropdown">
                        <button type="button" class="form-control branch-dropdown-toggle" id="filterUkerDropdown" aria-haspopup="true" aria-expanded="false" disabled>
                            <span id="filter_nama_uker_label" class="branch-dropdown-label">ALL UKER</span>
                            <i class="fas fa-chevron-down text-muted"></i>
                        </button>
                        <div class="uker-dropdown-menu" id="filterUkerMenu" aria-labelledby="filterUkerDropdown"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    <label class="text-dark text-sm font-weight-bold mb-1">Periode Bulan <i class="fas fa-edit text-warning ml-1"></i></label>
                    <!-- 🔥 FIX 1 FRONTEND: MENGGUNAKAN INPUT BULAN -->
                    <input type="month" id="filter_bulan" class="form-control border-warning shadow-sm filter-trigger" value="{{ date('Y-m') }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Posisi RKA</label>
                    <input type="text" id="filter_posisi_rka" class="form-control" disabled value="--------">
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
                    <table class="table table-report brilink-no-hover m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="6" class="bg-brilink-mid">Agen Brilink</th>
                                <th colspan="6" class="bg-brilink-light">Agen Juragan/Jawara</th>
                                <th colspan="6" class="bg-brilink-mid">Agen BEP</th>
                                <th colspan="4" class="bg-brilink-light">Transaksi</th>
                                <th colspan="4" class="bg-brilink-mid">Volume <br><small>(Rp Milyar)</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <!-- AGEN BRILINK -->
                                <th><span class="lbl-curr-short text-primary ml-1">Curr</span></th> <th><span class="lbl-mtd-short">MtD</span></th> <th><span class="lbl-ytd-short">YtD</span></th> <th><span class="lbl-yoy-short">YoY</span></th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                <!-- AGEN JURAGAN -->
                                <th><span class="lbl-curr-short text-primary">Curr</span></th> <th><span class="lbl-mtd-short">MtD</span></th> <th><span class="lbl-ytd-short">YtD</span></th> <th><span class="lbl-yoy-short">YoY</span></th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                <!-- AGEN BEP -->
                                <th><span class="lbl-curr-short text-primary">Curr</span></th> <th><span class="lbl-mtd-short">MtD</span></th> <th><span class="lbl-ytd-short">YtD</span></th> <th><span class="lbl-yoy-short">YoY</span></th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                <!-- TRANSAKSI -->
                                <th><span class="lbl-curr-short text-primary">Curr</span></th> <th><span class="lbl-mtd-short">MtD</span></th> <th><span class="lbl-yoy-short">YoY</span></th> <th class="rka-col text-dark">RKA</th>
                                <!-- VOLUME -->
                                <th><span class="lbl-curr-short text-primary">Curr</span></th> <th><span class="lbl-mtd-short">MtD</span></th> <th><span class="lbl-yoy-short">YoY</span></th> <th class="rka-col text-dark">RKA</th>
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
                    <table class="table table-report brilink-no-hover m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="10" class="bg-brilink-mid">Jumlah Agen Brilink</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy-short">YoY</th>
                                <th class="lbl-ytd-short">YtD</th>
                                <th class="lbl-mtd-short">MtD</th>
                                <th class="lbl-curr-short text-primary">Curr</th>
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
                    <table class="table table-report brilink-no-hover m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="12" class="bg-brilink-mid">Agen Juragan+Jawara</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy-short">YoY</th>
                                <th class="lbl-ytd-short">YtD</th>
                                <th class="lbl-mtd-short">MtD</th>
                                <th class="lbl-curr-short text-primary">Curr</th>
                                <th>MtD</th>
                                <th>MtD (%)</th>
                                <th>YtD</th>
                                <th>YtD (%)</th>
                                <th>YoY</th>
                                <th>YoY(%)</th>
                                <th class="rka-col text-dark">RKA</th>
                                <th class="rka-col text-dark">Penc(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-juragan"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-bep" role="tabpanel">
                <div class="table-container">
                    <table class="table table-report brilink-no-hover m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="12" class="bg-brilink-mid">Agen BEP</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy-short">YoY</th>
                                <th class="lbl-ytd-short">YtD</th>
                                <th class="lbl-mtd-short">MtD</th>
                                <th class="lbl-curr-short text-primary">Curr</th>
                                <th>MtD</th>
                                <th>MtD (%)</th>
                                <th>YtD</th>
                                <th>YtD (%)</th>
                                <th>YoY</th>
                                <th>YoY(%)</th>
                                <th class="rka-col text-dark">RKA</th>
                                <th class="rka-col text-dark">Penc(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-bep-detail"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-transaksi" role="tabpanel">
                <div class="table-container">
                    <table class="table table-report brilink-no-hover m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="5" class="bg-brilink-mid">Transaksi Agen Brilink</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr-short text-primary">Feb-26</th>
                                <th class="lbl-yoy-short">YoY</th>
                                <th class="lbl-curr-short">Curr</th>
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
                    <table class="table table-report brilink-no-hover m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-brilink-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="10" class="bg-brilink-mid">CASA Agen Brilink <br><small>(Rp. Juta)</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-yoy-short">YoY</th>
                                <th class="lbl-ytd-short">YtD</th>
                                <th class="lbl-mtd-short">MtD</th>
                                <th class="lbl-curr-short text-primary">Curr</th>
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

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    function formatNum(num) { 
        return (num === null || num === undefined || isNaN(num)) ? '-' : new Intl.NumberFormat('id-ID').format(num); 
    }

    function formatRka(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(parseFloat(num)));
    }
    
    function formatMilyar(num) { 
        if(num === null || num === undefined || isNaN(num)) return '-';
        let val = num / 1000000000;
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val); 
    }

    function formatJuta(num) {
        if(num === null || num === undefined || isNaN(num)) return '-';
        let val = num / 1000000;
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

    function renderMetricRow(label, metric, isMilyar = false, includeRka = false) {
        const curr = safeNum(metric.curr);
        const prev = calcPrev(metric.curr, metric.mtd);
        const dec = calcPrev(metric.curr, metric.ytd);
        const yoyPrev = calcPrev(metric.curr, metric.yoy);
        const mtdPct = calcPct(metric.mtd, prev);
        const ytdPct = calcPct(metric.ytd, dec);
        const yoyPct = calcPct(metric.yoy, yoyPrev);
        const formatter = isMilyar ? formatMilyar : formatNum;
        const trailingColumns = includeRka
            ? `
            <td class="rka-col">${formatRka(metric.rka)}</td>
            <td class="rka-col">${formatNum(metric.penc_pct)}%</td>`
            : '';

        return `<tr>
            <td class="text-left font-weight-bold text-dark">${label}</td>
            <td>${formatter(yoyPrev)}</td>
            <td>${formatter(dec)}</td>
            <td>${formatter(prev)}</td>
            <td>${formatter(curr)}</td>
            <td>${formatGrowth(metric.mtd, isMilyar)}</td>
            <td>${mtdPct === null ? '-' : formatGrowth(mtdPct)}</td>
            <td>${formatGrowth(metric.ytd, isMilyar)}</td>
            <td>${ytdPct === null ? '-' : formatGrowth(ytdPct)}</td>
            <td>${formatGrowth(metric.yoy, isMilyar)}</td>
            <td>${yoyPct === null ? '-' : formatGrowth(yoyPct)}</td>
            ${trailingColumns}
        </tr>`;
    }

    function renderCasaRow(label, metric) {
        const curr = safeNum(metric.curr);
        const prev = calcPrev(metric.curr, metric.mtd);
        const dec = calcPrev(metric.curr, metric.ytd);
        const yoyPrev = calcPrev(metric.curr, metric.yoy);
        const mtdPct = calcPct(metric.mtd, prev);
        const ytdPct = calcPct(metric.ytd, dec);
        const yoyPct = calcPct(metric.yoy, yoyPrev);

        return `<tr>
            <td class="text-left font-weight-bold text-dark">${label}</td>
            <td>${formatJuta(yoyPrev)}</td>
            <td>${formatJuta(dec)}</td>
            <td>${formatJuta(prev)}</td>
            <td>${formatJuta(curr)}</td>
            <td>${formatGrowth(safeNum(metric.mtd) / 1000000)}</td>
            <td>${mtdPct === null ? '-' : formatGrowth(mtdPct)}</td>
            <td>${formatGrowth(safeNum(metric.ytd) / 1000000)}</td>
            <td>${ytdPct === null ? '-' : formatGrowth(ytdPct)}</td>
            <td>${formatGrowth(safeNum(metric.yoy) / 1000000)}</td>
            <td>${yoyPct === null ? '-' : formatGrowth(yoyPct)}</td>
        </tr>`;
    }

    function renderCasaTotalRow(label, metric) {
        const curr = safeNum(metric.curr);
        const prev = calcPrev(metric.curr, metric.mtd);
        const dec = calcPrev(metric.curr, metric.ytd);
        const yoyPrev = calcPrev(metric.curr, metric.yoy);
        const mtdPct = calcPct(metric.mtd, prev);
        const ytdPct = calcPct(metric.ytd, dec);
        const yoyPct = calcPct(metric.yoy, yoyPrev);

        return `<tr class="row-total">
            <td class="text-left">${label}</td>
            <td>${formatJuta(yoyPrev)}</td>
            <td>${formatJuta(dec)}</td>
            <td>${formatJuta(prev)}</td>
            <td>${formatJuta(curr)}</td>
            <td>${formatGrowth(safeNum(metric.mtd) / 1000000)}</td>
            <td>${mtdPct === null ? '-' : formatGrowth(mtdPct)}</td>
            <td>${formatGrowth(safeNum(metric.ytd) / 1000000)}</td>
            <td>${ytdPct === null ? '-' : formatGrowth(ytdPct)}</td>
            <td>${formatGrowth(safeNum(metric.yoy) / 1000000)}</td>
            <td>${yoyPct === null ? '-' : formatGrowth(yoyPct)}</td>
        </tr>`;
    }

    function renderMetricTotalRow(label, metric, isMilyar = false, includeRka = false) {
        const curr = safeNum(metric.curr);
        const prev = calcPrev(metric.curr, metric.mtd);
        const dec = calcPrev(metric.curr, metric.ytd);
        const mtdPct = calcPct(metric.mtd, prev);
        const ytdPct = calcPct(metric.ytd, dec);
        const yoyPrev = calcPrev(metric.curr, metric.yoy);
        const yoyPct = calcPct(metric.yoy, yoyPrev);
        const formatter = isMilyar ? formatMilyar : formatNum;
        const trailingColumns = includeRka
            ? `
            <td class="rka-col text-dark">${formatRka(metric.rka)}</td>
            <td class="rka-col text-dark">${formatNum(metric.penc_pct)}%</td>`
            : '';

        return `<tr class="row-total">
            <td class="text-left">${label}</td>
            <td>${formatter(yoyPrev)}</td>
            <td>${formatter(dec)}</td>
            <td>${formatter(prev)}</td>
            <td>${formatter(curr)}</td>
            <td>${formatGrowth(metric.mtd, isMilyar)}</td>
            <td>${mtdPct === null ? '-' : formatGrowth(mtdPct)}</td>
            <td>${formatGrowth(metric.ytd, isMilyar)}</td>
            <td>${ytdPct === null ? '-' : formatGrowth(ytdPct)}</td>
            <td>${formatGrowth(metric.yoy, isMilyar)}</td>
            <td>${yoyPct === null ? '-' : formatGrowth(yoyPct)}</td>
            ${trailingColumns}
        </tr>`;
    }

    const branchUkerMap = @json($branchUkerMap ?? []);

    function getSelectedBranches() {
        return $('.filter-branch-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function getSelectedUkers() {
        return $('.filter-uker-checkbox:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function getAvailableUkers() {
        const selectedBranches = getSelectedBranches();
        if (!selectedBranches.length) {
            return [];
        }

        const ukerSet = new Set();
        selectedBranches.forEach(function (branch) {
            (branchUkerMap[branch] || []).forEach(function (uker) {
                if (uker) {
                    ukerSet.add(uker);
                }
            });
        });

        return Array.from(ukerSet).sort(function (a, b) {
            return a.localeCompare(b, 'id');
        });
    }

    function updateBranchLabel() {
        const selectedBranches = getSelectedBranches();
        $('#filter_branch_office_label').text(selectedBranches.length ? selectedBranches.join(', ') : 'Area 6 - All');
    }

    function updateUkerLabel() {
        const selectedUkers = getSelectedUkers();
        $('#filter_nama_uker_label').text(selectedUkers.length ? selectedUkers.join(', ') : 'ALL UKER');
    }

    function closeBranchDropdown() {
        $('#filterBranchMenu').removeClass('show');
        $('#filterBranchDropdown').attr('aria-expanded', 'false');
    }

    function closeUkerDropdown() {
        $('#filterUkerMenu').removeClass('show');
        $('#filterUkerDropdown').attr('aria-expanded', 'false');
    }

    function updateGroupLabel(label) {
        const normalizedLabel = (label || 'BRANCH OFFICE').toUpperCase();
        $('.col-group-label').each(function () {
            const $label = $(this);
            const nextText = normalizedLabel === 'UKER'
                ? ($label.data('filtered-label') || 'UKER')
                : ($label.data('default-label') || 'BRANCH OFFICE');
            $label.text(nextText);
        });
    }

    function syncNamaUkerOptions() {
        const availableUkers = getAvailableUkers();
        const selectedUkers = getSelectedUkers();
        const $ukerMenu = $('#filterUkerMenu');
        $ukerMenu.empty();

        availableUkers.forEach(function (uker) {
            const safeId = uker.toLowerCase().replace(/[^a-z0-9]+/g, '_');
            const isChecked = selectedUkers.includes(uker) ? 'checked' : '';
            $ukerMenu.append(`
                <label class="dropdown-item" for="uker_${safeId}">
                    <div class="form-check">
                        <input class="form-check-input filter-uker-checkbox" type="checkbox" value="${$('<div>').text(uker).html()}" id="uker_${safeId}" ${isChecked}>
                        <span class="form-check-label">${$('<div>').text(uker).html()}</span>
                    </div>
                </label>
            `);
        });

        const shouldDisable = availableUkers.length === 0;
        if (shouldDisable) {
            closeUkerDropdown();
        }
        $('#filterUkerDropdown').prop('disabled', shouldDisable).attr('aria-expanded', 'false');
        updateUkerLabel();
    }

    // 🔥 FIX FINAL: Variabel global untuk menampung request AJAX (Mencegah Race Condition)
    let brilinkXhr = null;
    const filterPosisiRka = document.getElementById('filter_posisi_rka');

    function loadDataBrilink() {
        const bulanAktif = $('#filter_bulan').val();
        const selectedBranches = getSelectedBranches();
        const selectedUkers = getSelectedUkers();

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
                branch_office: selectedBranches,
                nama_uker: selectedUkers,
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                
                // 🔥 STATE GUARD: Kalau user sudah ganti bulan lagi saat loading, abaikan response lama ini
                if (bulanAktif !== $('#filter_bulan').val()) return;

                if(res.status === 'success') {
                    
                    if (res.labels) {
                        $('.lbl-curr').text('Bulan Berjalan (' + res.labels.curr + ')');
                        $('.lbl-curr-short').text(res.labels.curr_short);
                        $('.lbl-mtd-short').text(res.labels.prev_short);
                        $('.lbl-ytd-short').text(res.labels.ytd_short);
                        $('.lbl-yoy-short').text(res.labels.yoy_short);
                        
                        // CASA/Agen User specific
                        $('.lbl-casa-curr').text(res.labels.casa_curr);
                        $('.lbl-casa-dec').text(res.labels.casa_dec);
                        $('.lbl-casa-prev').text(res.labels.casa_prev);
                        $('.lbl-casa-end').text(res.labels.casa_end);

                        if (filterPosisiRka) {
                            filterPosisiRka.value = res.labels.rka || '--------';
                        }
                    }
                    updateGroupLabel(res.group_label);

                    let html = '';
                    let htmlAgenUser = '';
                    let htmlJuragan = '';
                    let htmlBep = '';
                    let htmlTrx = '';
                    let htmlCasa = '';

                    res.data.forEach((row) => {
                        htmlAgenUser += renderMetricRow(row.branch, row.agen);
                        htmlJuragan += renderMetricRow(row.branch, row.juragan, false, true);
                        htmlBep += renderMetricRow(row.branch, row.bep, false, true);
                        htmlCasa += renderCasaRow(row.branch, row.casa || { curr: 0, mtd: 0, ytd: 0, yoy: 0 });

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
                            <td class="rka-col">${formatRka(row.agen.rka)}</td>
                            <td class="rka-col">${formatNum(row.agen.penc_pct)}%</td>

                            <td class="font-weight-bold">${formatNum(row.juragan.curr)}</td>
                            <td>${formatGrowth(row.juragan.mtd)}</td>
                            <td>${formatGrowth(row.juragan.ytd)}</td>
                            <td>${formatGrowth(row.juragan.yoy)}</td>
                            <td class="rka-col">${formatRka(row.juragan.rka)}</td>
                            <td class="rka-col">${formatNum(row.juragan.penc_pct)}%</td>

                            <td class="font-weight-bold">${formatNum(row.bep.curr)}</td>
                            <td>${formatGrowth(row.bep.mtd)}</td>
                            <td>${formatGrowth(row.bep.ytd)}</td>
                            <td>${formatGrowth(row.bep.yoy)}</td>
                            <td class="rka-col">${formatRka(row.bep.rka)}</td>
                            <td class="rka-col">${formatNum(row.bep.penc_pct)}%</td>

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
                        htmlJuragan += renderMetricTotalRow(total.branch, total.juragan, false, true);
                        htmlBep += renderMetricTotalRow(total.branch, total.bep, false, true);
                        htmlCasa += renderCasaTotalRow(total.branch, total.casa || { curr: 0, mtd: 0, ytd: 0, yoy: 0 });

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
                            <td class="rka-col text-dark">${formatRka(total.agen.rka)}</td>
                            <td class="rka-col text-dark">${formatNum(total.agen.penc_pct)}%</td>

                            <td>${formatNum(total.juragan.curr)}</td>
                            <td>${formatGrowth(total.juragan.mtd)}</td>
                            <td>${formatGrowth(total.juragan.ytd)}</td>
                            <td>${formatGrowth(total.juragan.yoy)}</td>
                            <td class="rka-col text-dark">${formatRka(total.juragan.rka)}</td>
                            <td class="rka-col text-dark">${formatNum(total.juragan.penc_pct)}%</td>

                            <td>${formatNum(total.bep.curr)}</td>
                            <td>${formatGrowth(total.bep.mtd)}</td>
                            <td>${formatGrowth(total.bep.ytd)}</td>
                            <td>${formatGrowth(total.bep.yoy)}</td>
                            <td class="rka-col text-dark">${formatRka(total.bep.rka)}</td>
                            <td class="rka-col text-dark">${formatNum(total.bep.penc_pct)}%</td>

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
                    $('#tbody-casa').html(htmlCasa);
                    $('.lbl-casa-curr').text(res.labels?.casa_curr || res.labels?.curr || 'Curr');
                    $('.lbl-casa-dec').text(res.labels?.casa_dec || "Des'25");
                    $('.lbl-casa-prev').text(res.labels?.casa_prev || 'Prev');
                    $('.lbl-casa-end').text(res.labels?.casa_end || 'Curr End');
                } else if(res.status === 'error') {
                    $('#tbody-brilink').html(`<tr><td colspan="27" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>${res.msg}</td></tr>`);
                    $('#tbody-agen-user').html(`<tr><td colspan="11" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                    $('#tbody-juragan').html(`<tr><td colspan="13" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                    $('#tbody-bep-detail').html(`<tr><td colspan="13" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                    $('#tbody-transaksi').html(`<tr><td colspan="6" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                    $('#tbody-casa').html(`<tr><td colspan="11" class="text-center text-danger py-5">${res.msg}</td></tr>`);
                }
            },
            error: function(err) {
                // Abaikan error jika itu sengaja kita abort
                if (err.statusText === 'abort') return;

                $('#tbody-brilink').html('<tr><td colspan="27" class="text-center text-danger py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Gagal memuat data dari server.</td></tr>');
                $('#tbody-agen-user').html('<tr><td colspan="11" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
                $('#tbody-juragan').html('<tr><td colspan="13" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
                $('#tbody-bep-detail').html('<tr><td colspan="13" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
                $('#tbody-transaksi').html('<tr><td colspan="6" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
                $('#tbody-casa').html('<tr><td colspan="11" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
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

    $('#filterBranchDropdown').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeUkerDropdown();
        $('#filterBranchMenu').toggleClass('show');
        $(this).attr('aria-expanded', $('#filterBranchMenu').hasClass('show') ? 'true' : 'false');
    });

    $('#filterUkerDropdown').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled')) return;
        closeBranchDropdown();
        $('#filterUkerMenu').toggleClass('show');
        $(this).attr('aria-expanded', $('#filterUkerMenu').hasClass('show') ? 'true' : 'false');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.branch-filter-dropdown').length) {
            closeBranchDropdown();
        }
        if (!$(e.target).closest('.uker-filter-dropdown').length) {
            closeUkerDropdown();
        }
    });

    $(document).on('change', '.filter-branch-checkbox', function () {
        syncNamaUkerOptions();
        updateBranchLabel();
        loadDataBrilink();
    });

    $(document).on('change', '.filter-uker-checkbox', function () {
        updateUkerLabel();
        loadDataBrilink();
    });

    // Initial Load
    syncNamaUkerOptions();
    loadDataBrilink();
});
</script>
@endsection
