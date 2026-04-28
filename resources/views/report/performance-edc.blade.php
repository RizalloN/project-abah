@extends('layouts.admin')

@section('title', 'Performance EDC')

@section('content')

<style>
    /* 🔥 PERBAIKAN UI: Tabel elastis dan cerdas menyesuaikan ukuran layar */
    .report-filter-card,
    .report-data-card {
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        overflow: visible;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22) !important;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }
    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: #ffffff;
    }
    .report-filter-card .card-body {
        overflow: visible;
        padding: 1rem 1.1rem 1.05rem;
    }
    .report-filter-card .row {
        row-gap: 0.85rem;
    }
    .report-filter-card .form-group {
        position: relative;
        height: 100%;
        padding: 0.68rem 0.75rem 0.72rem;
        border: 1px solid #dbe5ef;
        border-radius: 14px;
        background: linear-gradient(180deg, rgba(236, 243, 255, 0.96) 0%, rgba(255, 255, 255, 0.98) 78%);
        box-shadow: 0 12px 24px -24px rgba(15, 23, 42, 0.24);
        overflow: hidden;
    }
    .report-filter-card .form-group::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, #004685, #00529c, #8fb4ff, #ffffff);
    }
    .report-filter-card .form-group > label {
        display: block;
        margin-bottom: 0.38rem !important;
        color: #516b91 !important;
        font-size: 0.72rem !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .report-filter-card .form-control {
        border-radius: 12px;
        min-height: 40px;
        border-color: #cbd8e8;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 12px 22px -20px rgba(15, 23, 42, 0.2);
    }
    .report-filter-card .form-control:focus {
        border-color: #00529c;
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14), 0 12px 22px -22px rgba(0, 70, 133, 0.18);
    }
    .branch-filter-dropdown {
        position: relative;
    }
    .uker-filter-dropdown {
        position: relative;
    }
    .branch-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        width: 100%;
        min-height: 40px;
        border: 1px solid #cbd8e8;
        border-radius: 12px;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%);
        color: #334155;
        padding: 0.55rem 0.85rem;
        font-weight: 700;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 12px 22px -20px rgba(15, 23, 42, 0.2);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
    }
    .branch-dropdown-toggle:hover:not(:disabled) {
        border-color: rgba(0, 82, 156, 0.24);
        background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    }
    .branch-dropdown-toggle:focus {
        outline: none;
        border-color: #00529c;
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14), 0 12px 22px -22px rgba(0, 70, 133, 0.18);
        background: #ffffff;
    }
    .branch-dropdown-toggle:disabled {
        background: linear-gradient(180deg, #edf4ff, #f8fbff);
        cursor: not-allowed;
        opacity: 1;
        color: #64748b;
        box-shadow: none;
    }
    .branch-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .branch-dropdown-menu {
        position: absolute;
        top: calc(100% + 0.45rem);
        left: 0;
        right: 0;
        z-index: 1050;
        display: none;
        width: 100%;
        max-height: 260px;
        overflow-y: auto;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid #dbe5ef;
        border-radius: 14px;
        box-shadow: 0 20px 34px -28px rgba(0, 70, 133, 0.22);
        padding: 0.45rem;
    }
    .branch-dropdown-menu.show {
        display: block;
    }
    .uker-dropdown-menu.show {
        display: block;
    }
    .branch-dropdown-menu .dropdown-item {
        padding: 0.62rem 0.72rem;
        cursor: pointer;
        margin-bottom: 0;
        border-radius: 10px;
    }
    .uker-dropdown-menu {
        position: absolute;
        top: calc(100% + 0.45rem);
        left: 0;
        right: 0;
        z-index: 1050;
        display: none;
        width: 100%;
        max-height: 260px;
        overflow-y: auto;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid #dbe5ef;
        border-radius: 14px;
        box-shadow: 0 20px 34px -28px rgba(0, 70, 133, 0.22);
        padding: 0.45rem;
    }
    .uker-dropdown-menu .dropdown-item {
        padding: 0.62rem 0.72rem;
        cursor: pointer;
        margin-bottom: 0;
        border-radius: 10px;
    }
    .branch-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .uker-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .branch-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
    }
    .uker-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
    }
    .branch-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
    }
    .uker-dropdown-menu .form-check-label {
        margin: 0;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
    }
    .branch-dropdown-menu .dropdown-item:hover,
    .uker-dropdown-menu .dropdown-item:hover {
        background: linear-gradient(135deg, #edf5ff, #f8fbff);
    }
    .branch-dropdown-menu .form-check-input,
    .uker-dropdown-menu .form-check-input {
        width: 1rem;
        height: 1rem;
        border-color: #b9cbe3;
    }
    .branch-dropdown-menu .form-check-input:checked,
    .uker-dropdown-menu .form-check-input:checked {
        background-color: #00529c;
        border-color: #00529c;
    }
    #filter_posisi,
    #filter_posisi_rka {
        min-height: 40px;
        border-radius: 12px;
        border-color: #cbd8e8;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 12px 22px -20px rgba(15, 23, 42, 0.2);
        color: #334155;
        font-weight: 700;
    }
    #filter_posisi:focus {
        border-color: #00529c;
        box-shadow: 0 0 0 3px rgba(0, 82, 156, 0.14), 0 12px 22px -22px rgba(0, 70, 133, 0.18);
    }
    #filter_posisi_rka:disabled {
        background: linear-gradient(180deg, #edf4ff, #f8fbff);
        color: #64748b;
        opacity: 1;
        box-shadow: none;
    }
    .table-container {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22);
        scrollbar-color: #9aa8bd #eef3f9;
    }
    .table-container::-webkit-scrollbar {
        height: 10px;
    }
    .table-container::-webkit-scrollbar-track {
        background: #eef3f9;
        border-radius: 999px;
    }
    .table-container::-webkit-scrollbar-thumb {
        background: #9aa8bd;
        border-radius: 999px;
    }
    .table-report {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        table-layout: auto;
        margin-bottom: 0;
    }
    .table-report th,
    .table-report td {
        vertical-align: middle !important;
        border: 1px solid #e4ebf3;
        word-wrap: break-word;
        white-space: normal;
    }
    .table-report thead th {
        font-size: 0.68rem;
        padding: 11px 6px;
        text-align: center;
        font-weight: 800;
        letter-spacing: 0.02em;
        border-color: rgba(255, 255, 255, 0.22);
    }
    .table-report thead tr:first-child th {
        padding-top: 12px;
        padding-bottom: 12px;
    }
    .table-report thead tr:last-child th {
        box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.2);
    }
    .table-report tbody td {
        font-size: 0.7rem;
        padding: 7px 6px;
        text-align: right;
        background: #ffffff;
    }
    .table-report tbody tr:nth-child(even) td {
        background: #fafcff;
    }
    /* .table-report tbody tr:hover td {
        background: #eef5ff !important;
    } */
    .table-report td.text-left {
        text-align: left;
    }
    .table-report tbody td:first-child,
    .table-report tbody td.text-left {
        font-weight: 700;
        color: #1f2937;
    }
    
    /* Pewarnaan Header TAB 1 & 2 Sesuai Sebelumnya */
    .bg-mid-dark { background-color: #2b5cb5 !important; color: #ffffff !important; }
    .bg-prod-dark { background-color: #6c9ce8 !important; color: #ffffff !important; }
    .bg-sv-dark { background-color: #9baab8 !important; color: #ffffff !important; }
    .bg-header-sub { background-color: #f1f5fa !important; color: #333 !important; font-weight: bold; }
    
    .bg-tab2-dark { background-color: #2b5cb5 !important; color: #ffffff !important; border-color: #214b99 !important; }
    .bg-tab2-light { background-color: #92c0f0 !important; color: #000000 !important; border-color: #8eb7e3 !important; font-weight: bold; }
    .bg-tab2-sublight { background-color: #dae8f9 !important; color: #000000 !important; border-color: #c8d9ea !important; font-weight: bold; }
    .bg-sv-accum-dark { background-color: #7d7d7d !important; color: #ffffff !important; border-color: #6c6c6c !important; }
    .bg-sv-accum-sub { background-color: #b6b6b6 !important; color: #ffffff !important; border-color: #a2a2a2 !important; font-weight: bold; }
    
    /* 🔥 Pewarnaan Header TAB 3 (Produktivitas MoM) */
    .bg-mom-sv0 { background-color: #2956a8 !important; color: white !important; }
    .bg-mom-sv1 { background-color: #4b7bc9 !important; color: white !important; }
    .bg-mom-prod { background-color: #6c9ce8 !important; color: white !important; }
    .bg-mom-tid { background-color: #3b6bbd !important; color: white !important; }
    .bg-mom-svvol { background-color: #1f4282 !important; color: white !important; }

    /* Conditional Formatting Latar Belakang Sel (%) */
    .bg-good { background-color: #d4edda !important; color: #155724 !important; font-weight: bold;}
    .bg-bad { background-color: #f8d7da !important; color: #721c24 !important; font-weight: bold;}

    /* .table-hover tbody tr:hover { background-color: transparent; } */
    .row-total {
        --row-total-bg: #003366;
        --row-total-color: #ffffff;
        background: #003366 !important;
        color: #ffffff !important;
        font-weight: bold;
    }
    .row-total td {
        background: #003366 !important;
        color: #ffffff !important;
    }
    .val-up { color: #28a745; font-weight: bold; margin-left: 2px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 2px; }
    
    .rka-col { background: linear-gradient(180deg, #fff3cd, #ffefba) !important; color: #856404 !important; font-weight: 600; border-color: #f6e3a6 !important; }
    .row-total .rka-col {
        background: #003366 !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }
    
    .nav-tabs.report-tabs {
        border-bottom: 1px solid #dbe5ef;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        scrollbar-width: thin;
    }
    .nav-tabs.report-tabs .nav-link {
        border: none;
        font-weight: 700;
        color: #6b7280;
        padding: 12px 18px;
        font-size: 0.95rem;
        background: transparent;
    }
    .nav-tabs.report-tabs .nav-link.active {
        border-bottom: 3px solid #00529c;
        color: #00529c;
        background: transparent;
    }
    .nav-tabs.report-tabs .nav-link:hover {
        border-bottom: 3px solid #9ec5fe;
        color: #00529c;
        background: transparent;
    }
        /* Ensure the branch/uker dropdown can float above the table and sticky headers */
        .report-filter-card {
            position: relative;
            z-index: 30;
            overflow: visible;
        }

        .report-filter-card .form-group,
        .branch-filter-dropdown,
        .uker-filter-dropdown {
            position: relative;
            overflow: visible;
            z-index: 40;
        }

        .branch-dropdown-menu,
        .uker-dropdown-menu {
            z-index: 2000;
        }

        .report-data-card {
            position: relative;
            z-index: 10;
        }
</style>

<div class="pt-4">
    <div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="d-none">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control font-weight-bold" value="Performance EDC" disabled>
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
                            @foreach(($branchOptions ?? collect()) as $branchOption)
                                <label class="dropdown-item" for="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                    <div class="form-check">
                                        <input class="form-check-input filter-branch-checkbox" type="checkbox" value="{{ $branchOption }}" id="branch_{{ \Illuminate\Support\Str::slug($branchOption, '_') }}">
                                        <span class="form-check-label">{{ $branchOption }}</span>
                                    </div>
                                </label>
                            @endforeach
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
                    <label class="text-dark text-sm font-weight-bold mb-1">Posisi Terakhir <i class="fas fa-edit text-primary ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
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
                <a class="nav-link" data-toggle="tab" href="#tab-merchant-prod" role="tab" data-tab="merchant_prod">
                    <i class="fas fa-store mr-1"></i> Performance EDC Merchant Produktif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-sv-merchant" role="tab" data-tab="sv_merchant_accum">
                    <i class="fas fa-chart-area mr-1"></i> Performance SV Merchant EDC Akumulasi
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
                    <table class="table table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mid-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
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
                    <table class="table table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-tab2-dark align-middle col-group-label" data-default-label="REGIONAL / BRANCH OFFICE" data-filtered-label="UKER">REGIONAL / BRANCH OFFICE</th>
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

            <div class="tab-pane fade" id="tab-merchant-prod" role="tabpanel">
                <div class="table-container">
                    <table class="table table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mid-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="11" class="bg-prod-dark">Performance EDC Merchant Produktif <br><small>SV &gt;= 15 Juta/Bulan</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="merchant-label" id="merchant_feb_prev">Feb'25</th>
                                <th class="merchant-label" id="merchant_dec_prev">Des'25</th>
                                <th class="merchant-label" id="merchant_jan_prev">Jan'26</th>
                                <th class="merchant-label" id="merchant_curr">28 Feb 26</th>
                                <th>% TID Produktif</th>
                                <th>MtD</th>
                                <th>MtD(%)</th>
                                <th>YtD</th>
                                <th>YoY</th>
                                <th class="rka-col text-dark merchant-label" id="merchant_rka">RKA Feb'26</th>
                                <th class="rka-col text-dark">Penc(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-merchant-prod"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-sv-merchant" role="tabpanel">
                <div class="table-container">
                    <table class="table table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mid-dark align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="9" class="bg-prod-dark">SV Merchant EDC Akumulasi <br><small>(Rp Milyar)</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="sv-label" id="sv_feb_prev">Feb'25</th>
                                <th class="sv-label" id="sv_dec_prev">Des'25</th>
                                <th class="sv-label" id="sv_jan_prev">Jan'26</th>
                                <th class="sv-label" id="sv_curr">28 Feb 26</th>
                                <th>MtD</th>
                                <th>MtD(%)</th>
                                <th>YoY</th>
                                <th class="rka-col text-dark sv-label" id="sv_rka">RKA Feb'26</th>
                                <th class="rka-col text-dark">Penc(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-sv-merchant"></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-prod-mom" role="tabpanel">
                <div class="table-container">
                    <table class="table table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mom-sv0 align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">Branch Office</th>
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

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    let activeTab = 'edc';
    const branchUkerMap = @json($branchUkerMap ?? []);
    const filterPosisiRka = document.getElementById('filter_posisi_rka');

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

    function syncNamaUkerOptions() {
        const availableUkers = getAvailableUkers();
        const selectedUkers = getSelectedUkers();
        const $ukerMenu = $('#filterUkerMenu');
        $ukerMenu.empty();

        availableUkers.forEach(function (uker) {
            const slug = uker.toLowerCase().replace(/[^a-z0-9]+/g, '_');
            const isChecked = selectedUkers.includes(uker) ? 'checked' : '';
            $ukerMenu.append(`
                <label class="dropdown-item" for="uker_${slug}">
                    <div class="form-check">
                        <input class="form-check-input filter-uker-checkbox" type="checkbox" value="${$('<div>').text(uker).html()}" id="uker_${slug}" ${isChecked}>
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

    function formatNum(num) { return new Intl.NumberFormat('id-ID').format(num); }
    function formatRka(num) { return formatNum(Math.round(parseFloat(num) || 0)); }
    
    function formatGrowth(val, isPct = false) {
        let num = parseFloat(val);
        let text = isPct ? formatNum(num) + '%' : formatNum(num);
        if (num > 0) return `${text} <i class="fas fa-arrow-up val-up"></i>`;
        if (num < 0) return `${text} <i class="fas fa-arrow-down val-down"></i>`;
        return `${text} -`;
    }

    function formatGrowthParen(val, isPct = false) {
        let num = parseFloat(val);
        if (isNaN(num)) return '-';

        let text = isPct ? formatNum(Math.abs(num)) + '%' : formatNum(Math.abs(num));
        if (num > 0) return `${text} <i class="fas fa-arrow-up val-up"></i>`;
        if (num < 0) return isPct
            ? `-${text} <i class="fas fa-arrow-down val-down"></i>`
            : `(${text}) <i class="fas fa-arrow-down val-down"></i>`;
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
            branch_office: getSelectedBranches(),
            nama_uker: getSelectedUkers(),
            _token: '{{ csrf_token() }}'
        };

        $.ajax({
            url: "{{ route('report.data') }}",
            type: "POST",
            data: payload,
            success: function(res) {
                if(res.status === 'success') {
                    updateGroupLabel(res.group_label);
                    
                    if (res.labels?.yoy) $('.lbl-yoy').text(res.labels.yoy);
                    if (res.labels?.ytd) $('.lbl-ytd').text(res.labels.ytd);
                    if (res.labels?.mtd) $('.lbl-mtd').text(res.labels.mtd);
                    if (res.labels?.curr) $('.lbl-curr').text(res.labels.curr);
                    if (res.labels?.merchant_feb_prev) $('#merchant_feb_prev').text(res.labels.merchant_feb_prev);
                    if (res.labels?.merchant_dec_prev) $('#merchant_dec_prev').text(res.labels.merchant_dec_prev);
                    if (res.labels?.merchant_jan_prev) $('#merchant_jan_prev').text(res.labels.merchant_jan_prev);
                    if (res.labels?.merchant_curr) $('#merchant_curr').text(res.labels.merchant_curr);
                    if (res.labels?.rka) $('#merchant_rka').text(res.labels.rka);
                    if (res.labels?.merchant_sv_feb_prev) $('#sv_feb_prev').text(res.labels.merchant_sv_feb_prev);
                    if (res.labels?.merchant_sv_dec_prev) $('#sv_dec_prev').text(res.labels.merchant_sv_dec_prev);
                    if (res.labels?.merchant_sv_jan_prev) $('#sv_jan_prev').text(res.labels.merchant_sv_jan_prev);
                    if (res.labels?.merchant_sv_curr) $('#sv_curr').text(res.labels.merchant_sv_curr);
                    if (res.labels?.rka) $('#sv_rka').text(res.labels.rka);
                    if (filterPosisiRka) {
                        filterPosisiRka.value = res.labels.rka || '--------';
                    }

                    let html = '';

                    if (activeTab === 'edc') {
                        res.data.forEach((row) => {
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                <td>${formatNum(row.mid.yoy)}</td> <td>${formatNum(row.mid.ytd)}</td> <td>${formatNum(row.mid.mtd)}</td> <td class="font-weight-bold">${formatNum(row.mid.curr)}</td>
                                <td>${formatGrowth(row.mid.mtd_val)}</td> <td>${formatGrowth(row.mid.mtd_pct, true)}</td> <td>${formatGrowth(row.mid.ytd_val)}</td> <td>${formatGrowth(row.mid.yoy_val)}</td>
                                
                                <td class="font-weight-bold" style="background: #f4f8ff;">${formatNum(row.prod.curr)}</td> <td class="font-weight-bold text-primary" style="background: #e1e9f5;">${formatNum(row.prod.pct_tid)}%</td>
                                <td>${formatGrowth(row.prod.mtd_val)}</td> <td>${formatGrowth(row.prod.mtd_pct, true)}</td> <td>${formatGrowth(row.prod.ytd_val)}</td> <td>${formatGrowth(row.prod.yoy_val)}</td>
                                <td class="rka-col">${formatRka(row.prod.rka)}</td> <td class="rka-col">${formatNum(row.prod.penc_pct)}%</td>

                                <td class="font-weight-bold" style="background: #f8f9fa;">${formatNum(row.sv.curr)}</td>
                                <td>${formatGrowth(row.sv.mtd_val)}</td> <td>${formatGrowth(row.sv.mtd_pct, true)}</td> <td>${formatGrowth(row.sv.yoy_val)}</td>
                                <td class="rka-col">${formatRka(row.sv.rka)}</td> <td class="rka-col">${formatNum(row.sv.penc_pct)}%</td>
                            </tr>`;
                        });

                        let total = res.total;
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            <td>${formatNum(total.mid.yoy)}</td> <td>${formatNum(total.mid.ytd)}</td> <td>${formatNum(total.mid.mtd)}</td> <td>${formatNum(total.mid.curr)}</td>
                            <td>${formatGrowth(total.mid.mtd_val)}</td> <td>${formatGrowth(total.mid.mtd_pct, true)}</td> <td>${formatGrowth(total.mid.ytd_val)}</td> <td>${formatGrowth(total.mid.yoy_val)}</td>
                            
                            <td>${formatNum(total.prod.curr)}</td> <td>${formatNum(total.prod.pct_tid)}%</td>
                            <td>${formatGrowth(total.prod.mtd_val)}</td> <td>${formatGrowth(total.prod.mtd_pct, true)}</td> <td>${formatGrowth(total.prod.ytd_val)}</td> <td>${formatGrowth(total.prod.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatRka(total.prod.rka)}</td> <td class="rka-col text-dark">${formatNum(total.prod.penc_pct)}%</td>

                            <td>${formatNum(total.sv.curr)}</td>
                            <td>${formatGrowth(total.sv.mtd_val)}</td> <td>${formatGrowth(total.sv.mtd_pct, true)}</td> <td>${formatGrowth(total.sv.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatRka(total.sv.rka)}</td> <td class="rka-col text-dark">${formatNum(total.sv.penc_pct)}%</td>
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
                                <td class="rka-col">${formatRka(row.tid.rka)}</td> <td class="rka-col font-weight-bold" style="color:#d99900;">${formatNum(row.tid.penc_pct)}%</td>
                            </tr>`;
                        });
                        
                        let total = res.total;
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            <td>${formatNum(total.mid.yoy)}</td> <td>${formatNum(total.mid.ytd)}</td> <td>${formatNum(total.mid.mtd)}</td> <td>${formatNum(total.mid.curr)}</td>
                            <td>${formatGrowth(total.mid.mtd_val)}</td> <td>${formatGrowth(total.mid.mtd_pct, true)}</td> <td>${formatGrowth(total.mid.ytd_val)}</td> <td>${formatGrowth(total.mid.yoy_val)}</td>
                            
                            <td>${formatNum(total.tid.yoy)}</td> <td>${formatNum(total.tid.ytd)}</td> <td>${formatNum(total.tid.mtd)}</td> <td>${formatNum(total.tid.curr)}</td>
                            <td>${formatGrowth(total.tid.mtd_val)}</td> <td>${formatGrowth(total.tid.mtd_pct, true)}</td> <td>${formatGrowth(total.tid.ytd_val)}</td> <td>${formatGrowth(total.tid.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatRka(total.tid.rka)}</td> <td class="rka-col text-dark">${formatNum(total.tid.penc_pct)}%</td>
                        </tr>`;
                        $('#tbody-mid').html(html);
                    }
                    else if (activeTab === 'merchant_prod') {
                        res.data.forEach((row) => {
                            const prod = row.prod || {};
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                <td>${formatNum(prod.feb_prev)}</td>
                                <td>${formatNum(prod.dec_prev)}</td>
                                <td>${formatNum(prod.jan_prev)}</td>
                                <td class="font-weight-bold">${formatNum(prod.curr)}</td>
                                <td class="font-weight-bold text-primary">${formatNum(prod.pct_tid)}%</td>
                                <td>${formatGrowthParen(prod.mtd_val)}</td>
                                <td>${formatGrowthParen(prod.mtd_pct, true)}</td>
                                <td>${formatGrowthParen(prod.ytd_val)}</td>
                                <td>${formatGrowthParen(prod.yoy_val)}</td>
                                <td class="rka-col">${formatRka(prod.rka)}</td>
                                <td class="rka-col">${formatNum(prod.penc_pct)}%</td>
                            </tr>`;
                        });

                        let total = res.total;
                        let prod = total.prod || {};
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            <td>${formatNum(prod.feb_prev)}</td>
                            <td>${formatNum(prod.dec_prev)}</td>
                            <td>${formatNum(prod.jan_prev)}</td>
                            <td>${formatNum(prod.curr)}</td>
                            <td>${formatNum(prod.pct_tid)}%</td>
                            <td>${formatGrowthParen(prod.mtd_val)}</td>
                            <td>${formatGrowthParen(prod.mtd_pct, true)}</td>
                            <td>${formatGrowthParen(prod.ytd_val)}</td>
                            <td>${formatGrowthParen(prod.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatRka(prod.rka)}</td>
                            <td class="rka-col text-dark">${formatNum(prod.penc_pct)}%</td>
                        </tr>`;
                        $('#tbody-merchant-prod').html(html);
                    }
                    else if (activeTab === 'sv_merchant_accum') {
                        res.data.forEach((row) => {
                            const sv = row.sv || {};
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                <td>${formatNum(sv.feb_prev)}</td>
                                <td>${formatNum(sv.dec_prev)}</td>
                                <td>${formatNum(sv.jan_prev)}</td>
                                <td class="font-weight-bold">${formatNum(sv.curr)}</td>
                                <td>${formatGrowthParen(sv.mtd_val)}</td>
                                <td>${formatGrowthParen(sv.mtd_pct, true)}</td>
                                <td>${formatGrowthParen(sv.yoy_val)}</td>
                                <td class="rka-col">${formatRka(sv.rka)}</td>
                                <td class="rka-col">${formatNum(sv.penc_pct)}%</td>
                            </tr>`;
                        });

                        let total = res.total;
                        let sv = total.sv || {};
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            <td>${formatNum(sv.feb_prev)}</td>
                            <td>${formatNum(sv.dec_prev)}</td>
                            <td>${formatNum(sv.jan_prev)}</td>
                            <td>${formatNum(sv.curr)}</td>
                            <td>${formatGrowthParen(sv.mtd_val)}</td>
                            <td>${formatGrowthParen(sv.mtd_pct, true)}</td>
                            <td>${formatGrowthParen(sv.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatRka(sv.rka)}</td>
                            <td class="rka-col text-dark">${formatNum(sv.penc_pct)}%</td>
                        </tr>`;
                        $('#tbody-sv-merchant').html(html);
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
                                <td class="rka-col">${formatRka(row.prod.rka)}</td> <td class="rka-col">${formatNum(row.prod.gap)}</td> <td class="rka-col">${formatNum(row.prod.penc)}%</td>
                                
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
                            <td class="rka-col text-dark">${formatRka(total.prod.rka)}</td> <td class="rka-col text-dark">${formatNum(total.prod.gap)}</td> <td class="rka-col text-dark">${formatNum(total.prod.penc)}%</td>
                            
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
    $('#filterBranchDropdown').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $('#filterBranchMenu').toggleClass('show');
        closeUkerDropdown();
        $(this).attr('aria-expanded', $('#filterBranchMenu').hasClass('show') ? 'true' : 'false');
    });
    $('#filterUkerDropdown').on('click', function (e) {
        if ($(this).prop('disabled')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        $('#filterUkerMenu').toggleClass('show');
        closeBranchDropdown();
        $(this).attr('aria-expanded', $('#filterUkerMenu').hasClass('show') ? 'true' : 'false');
    });
    $('.filter-branch-checkbox').on('change', function () {
        updateBranchLabel();
        syncNamaUkerOptions();
        loadData();
    });
    $(document).on('change', '.filter-uker-checkbox', function () {
        updateUkerLabel();
        loadData();
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.branch-filter-dropdown').length) {
            closeBranchDropdown();
        }
        if (!$(e.target).closest('.uker-filter-dropdown').length) {
            closeUkerDropdown();
        }
    });
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) { activeTab = $(e.target).data('tab'); loadData(); });

    syncNamaUkerOptions();
    updateBranchLabel();
    loadData();
});
</script>
@endsection
