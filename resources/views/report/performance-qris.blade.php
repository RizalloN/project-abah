@extends('layouts.admin')

@section('title', 'Performance QRIS')

@section('content')

<style>
    :root {
        --report-sticky-first-col: 220px;
        --qris-blue-deep: #2f5b9a;
        --qris-blue-mid: #5f97cd;
        --qris-slate-mid: #8f9baa;
        --qris-subhead-bg: #edf3fb;
        --qris-subhead-text: #365b8c;
        --qris-body-even: #f8fbff;
        --qris-body-hover: #eef6ff;
        --qris-sticky-hover: #dff0ff;
        --qris-total-bg: #274d86;
        --qris-total-border: #1f3f6d;
        --qris-rka-bg: #fff4cc;
        --qris-rka-border: #f2df96;
        --qris-rka-text: #8b6a12;
    }
    /* 🔥 UI Seragam yang Elastis dan Fit Screen */
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
    .report-data-card .card-header {
        padding-top: 1.7rem !important;
        padding-bottom: 0.1rem !important;
    }
    .report-data-card .card-body {
        padding-top: 0.75rem !important;
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
    .table-container {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #b9c9dd #eef3f9;
    }
    .table-container::-webkit-scrollbar {
        height: 12px;
    }
    .table-container::-webkit-scrollbar-track {
        background: linear-gradient(180deg, #eff4fa 0%, #e4ecf6 100%);
        border-radius: 999px;
    }
    .table-container::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #c1d1e4 0%, #a9bfd7 100%);
        border-radius: 999px;
        border: 2px solid #eff4fa;
    }
    .table-container::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #aec4dc 0%, #94b0ce 100%);
    }
    .table-report { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; table-layout: auto; }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        border: 1px solid #dee2e6;
        white-space: nowrap;
        overflow: hidden;
        background-clip: padding-box;
    }
    .table-report th { font-size: 0.65rem; padding: 11px 8px; text-align: center; letter-spacing: 0.03em; }
    .table-report td { font-size: 0.72rem; padding: 8px 10px; text-align: right; position: relative; z-index: 1; background: #ffffff; color: #334155; font-variant-numeric: tabular-nums; }
    .table-report td.text-left { text-align: left; }
    .table-report tbody tr:nth-child(even):not(.row-total) > td {
        background: var(--qris-body-even);
    }
    .content-wrapper .table-container .table-report .sticky-col {
        position: sticky;
        left: 0;
        z-index: 20 !important;
        min-width: var(--report-sticky-first-col);
        max-width: var(--report-sticky-first-col);
        background: #ffffff;
        background-clip: padding-box;
        overflow: hidden;
        text-overflow: ellipsis;
        isolation: isolate;
        contain: paint;
        box-shadow: 10px 0 14px -14px rgba(15, 23, 42, 0.38);
    }
    .content-wrapper .table-container .table-report .sticky-col::before {
        content: "";
        position: absolute;
        inset: -1px -16px -1px -1px;
        background: inherit;
        border-right: 1px solid #dee2e6;
        z-index: -1;
    }
    .content-wrapper .table-container .table-report tbody .sticky-col::after {
        content: "";
        position: absolute;
        top: -1px;
        right: -1px;
        bottom: -1px;
        width: 12px;
        pointer-events: none;
        background: linear-gradient(to right, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.96) 70%, rgba(255, 255, 255, 1));
    }
    .content-wrapper .table-container .table-report thead .sticky-col {
        z-index: 24 !important;
        box-shadow: none;
    }
    .content-wrapper .table-container .table-report tbody tr:nth-child(even):not(.row-total) > .sticky-col {
        background-color: var(--qris-body-even) !important;
    }
    
    /* Pewarnaan Header Khas QRIS (TAB 1) */
    .bg-qris-jml { background: linear-gradient(180deg, #3a629f 0%, var(--qris-blue-deep) 100%) !important; color: #ffffff !important; border-color: #25497b !important; }
    .bg-qris-prod { background: linear-gradient(180deg, #6ca6da 0%, var(--qris-blue-mid) 100%) !important; color: #ffffff !important; border-color: #4b81b4 !important; }
    .bg-qris-vol { background: linear-gradient(180deg, #a3adba 0%, var(--qris-slate-mid) 100%) !important; color: #ffffff !important; border-color: #788391 !important; }
    .bg-header-sub { background: var(--qris-subhead-bg) !important; color: var(--qris-subhead-text) !important; font-weight: 700; }

    /* Pewarnaan Header QRIS MoM (TAB 2) Sesuai Screenshot */
    .bg-mom-blue { background: linear-gradient(180deg, #3a629f 0%, var(--qris-blue-deep) 100%) !important; color: #ffffff !important; border-color: #25497b !important; }

    /* Conditional Formatting Latar Belakang Sel (%) */
    .bg-good { background-color: #dcf5e5 !important; color: #166534 !important; font-weight: 700;}
    .bg-bad { background-color: #fde2e4 !important; color: #b42318 !important; font-weight: 700;}

    .content-wrapper .table-container .table-report tbody tr:not(.row-total):hover > td { background-color: var(--qris-body-hover); }
    .content-wrapper .table-container .table-report tbody tr:not(.row-total):hover > .sticky-col { background-color: var(--qris-sticky-hover) !important; }
    .content-wrapper .table-container .table-report tbody tr:not(.row-total):hover > .sticky-col::after {
        background: linear-gradient(to right, rgba(223, 240, 255, 0), rgba(223, 240, 255, 0.96) 70%, rgba(223, 240, 255, 1));
    }
    .row-total { --row-total-bg: var(--qris-total-bg); --row-total-color: #ffffff; --row-total-border: var(--qris-total-border); background: linear-gradient(180deg, #315992 0%, var(--qris-total-bg) 100%) !important; color: white !important; font-weight: 700; }
    .row-total td,
    .row-total th,
    .row-total td *,
    .row-total th * {
        color: #ffffff !important;
        border-color: var(--qris-total-border) !important;
    }
    .content-wrapper .table-container .table-report tbody tr.row-total:hover > td,
    .content-wrapper .table-container .table-report tbody tr.row-total:hover > th {
        background: linear-gradient(180deg, #315992 0%, var(--qris-total-bg) 100%) !important;
    }
    .content-wrapper .table-container .table-report .row-total .sticky-col { background-color: var(--qris-total-bg) !important; }
    .content-wrapper .table-container .table-report .row-total .sticky-col::after {
        background: linear-gradient(to right, rgba(39, 77, 134, 0), rgba(39, 77, 134, 0.96) 70%, rgba(39, 77, 134, 1));
    }
    .val-up { color: #28a745; font-weight: bold; margin-left: 2px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 2px; }
    
    .rka-col { background-color: var(--qris-rka-bg) !important; color: var(--qris-rka-text) !important; font-weight: 700; border-color: var(--qris-rka-border) !important; }
    .row-total .rka-col { background-color: #355b91 !important; color: #ffffff !important; border-color: var(--qris-total-border) !important; }
    
    /* Nav Tabs Styling */
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; align-items: flex-end; min-height: 58px; margin-top: 0.2rem; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 13px 18px 12px; font-size: 0.95rem; line-height: 1.2; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
    @media (max-width: 767.98px) {
        :root {
            --report-sticky-first-col: 180px;
        }
        .report-data-card .card-header {
            padding-top: 1rem !important;
        }
        .report-data-card .card-body {
            padding-top: 0.55rem !important;
        }
        .nav-tabs.report-tabs {
            min-height: 52px;
            margin-top: 0.1rem;
        }
        .nav-tabs.report-tabs .nav-link {
            padding: 11px 14px 10px;
            font-size: 0.9rem;
        }
    }
    @include('report.partials.sticky-table-viewport-style')
</style>
@include('report._bri-report-ui')

<div class="pt-4">
    <div class="card card-outline card-success shadow-sm mb-4 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="d-none">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Report</label>
                    <input type="text" class="form-control font-weight-bold" value="Performance QRIS" disabled>
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
                    <label class="text-dark text-sm font-weight-bold mb-1">Posisi Terakhir <i class="fas fa-edit text-success ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-success shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
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
                <a class="nav-link active" data-toggle="tab" href="#tab-qris" role="tab" data-tab="qris">
                    <i class="fas fa-qrcode mr-1"></i> Performance QRIS
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-qris-mom" role="tab" data-tab="qris_mom">
                    <i class="fas fa-chart-bar mr-1"></i> Performance QRIS MoM
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
            
            <!-- 🔥 TAB 1: PERFORMANCE QRIS UTAMA -->
            <div class="tab-pane fade show active" id="tab-qris" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-qris-jml align-middle col-group-label sticky-col" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="7" class="bg-qris-jml">Jumlah QRIS</th>
                                <th colspan="8" class="bg-qris-prod">QRIS Produktif <br><small>(SV >= 50 Ribu/Bulan)</small></th>
                                <th colspan="6" class="bg-qris-vol">Sales Volume QRIS Akumulasi <br><small>(Rp Milyar)</small></th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="lbl-curr">Hari Berjalan</th>
                                <th>MtD</th> <th>MtD(%)</th> <th>YtD</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                
                                <th class="lbl-curr">Hari Berjalan</th> <th>% QRIS Prod.</th>
                                <th>MtD</th> <th>MtD(%)</th> <th>YtD</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                                
                                <th class="lbl-curr">Hari Berjalan</th>
                                <th>MtD</th> <th>MtD(%)</th> <th>YoY</th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Penc(%)</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-qris"></tbody>
                    </table>
                </div>
            </div>

            <!-- 🔥 TAB 2: PERFORMANCE QRIS MoM -->
            <div class="tab-pane fade" id="tab-qris-mom" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-mom-blue align-middle col-group-label sticky-col" data-default-label="BRANCH OFFICE" data-filtered-label="UKER">BRANCH OFFICE</th>
                                <th colspan="4" class="bg-mom-blue">SV 0</th>
                                <th colspan="7" class="bg-mom-blue">Produktif (>=50 Ribu)</th>
                                <th colspan="4" class="bg-mom-blue">Total Store ID</th>
                                <th colspan="4" class="bg-mom-blue">SV Bulan Berjalan (Rp Milyar)</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <!-- SV 0 -->
                                <th class="lbl-prev-mom">Prev Month</th> <th class="lbl-curr">Curr Month</th> <th>MoM</th> <th>% MoM</th>
                                
                                <!-- Produktif -->
                                <th class="lbl-prev-mom">Prev Month</th> <th class="lbl-curr">Curr Month</th> <th>MoM</th> <th>% MoM</th> <th class="rka-col text-dark">RKA</th> <th class="rka-col text-dark">Gap</th> <th class="rka-col text-dark">% Penc</th>
                                
                                <!-- Total Store ID -->
                                <th class="lbl-prev-mom">Prev Month</th> <th class="lbl-curr">Curr Month</th> <th>MoM</th> <th>% MoM</th>
                                
                                <!-- SV Berjalan -->
                                <th class="lbl-prev-mom">Prev Month</th> <th class="lbl-curr">Curr Month</th> <th>MoM</th> <th>% MoM</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-qris-mom"></tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

</div>
@endsection

@section('scripts')
@include('report.partials.sticky-table-viewport-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    let activeTab = 'qris';
    const filterPosisiRka = document.getElementById('filter_posisi_rka');
    const numberFormatter = new Intl.NumberFormat('id-ID');
    const percentFormatter = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    });
    const milyarFormatter = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    const htmlEscapeNode = document.createElement('div');
    const availableUkerCache = new Map();
    let activeRequest = null;
    let loadTimer = null;
    let requestSeq = 0;
    let currentAvailableUkers = [];

    function escapeHtml(value) {
        htmlEscapeNode.textContent = value ?? '';
        return htmlEscapeNode.innerHTML;
    }

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

    function getBranchCacheKey(selectedBranches) {
        return selectedBranches
            .slice()
            .sort(function (a, b) {
                return a.localeCompare(b, 'id');
            })
            .join('||');
    }

    function renderNamaUkerOptions(availableUkers) {
        const selectedUkers = getSelectedUkers();
        const $ukerMenu = $('#filterUkerMenu');
        $ukerMenu.empty();

        availableUkers.forEach(function (uker) {
            const slug = uker.toLowerCase().replace(/[^a-z0-9]+/g, '_');
            const escapedUker = escapeHtml(uker).replace(/"/g, '&quot;');
            const isChecked = selectedUkers.includes(uker) ? 'checked' : '';
            $ukerMenu.append(`
                <label class="dropdown-item" for="uker_${slug}">
                    <div class="form-check">
                        <input class="form-check-input filter-uker-checkbox" type="checkbox" value="${escapedUker}" id="uker_${slug}" ${isChecked}>
                        <span class="form-check-label">${escapedUker}</span>
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

    function syncNamaUkerOptions() {
        renderNamaUkerOptions(currentAvailableUkers);
    }

    function loadNamaUkerOptions() {
        const selectedBranches = getSelectedBranches();

        if (!selectedBranches.length) {
            currentAvailableUkers = [];
            renderNamaUkerOptions(currentAvailableUkers);
            return $.Deferred().resolve().promise();
        }

        const cacheKey = getBranchCacheKey(selectedBranches);
        if (availableUkerCache.has(cacheKey)) {
            currentAvailableUkers = availableUkerCache.get(cacheKey) || [];
            renderNamaUkerOptions(currentAvailableUkers);
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: "{{ route('report.qris.ukers') }}",
            type: "POST",
            dataType: "json",
            data: {
                branch_office: selectedBranches,
                _token: '{{ csrf_token() }}'
            }
        }).then(function (res) {
            currentAvailableUkers = res.status === 'success' && Array.isArray(res.ukers) ? res.ukers : [];
            availableUkerCache.set(cacheKey, currentAvailableUkers);
            renderNamaUkerOptions(currentAvailableUkers);
            return currentAvailableUkers;
        }, function () {
            currentAvailableUkers = [];
            renderNamaUkerOptions(currentAvailableUkers);
            return currentAvailableUkers;
        });
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

    function safeNumber(num, fallback = 0) {
        const parsed = Number(num);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatNum(num) { return numberFormatter.format(safeNumber(num)); }

    function formatPercent(num, digits = 1) {
        if (digits === 1) {
            return percentFormatter.format(safeNumber(num)) + '%';
        }

        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        }).format(safeNumber(num)) + '%';
    }
    
    function formatMilyar(num) {
        return milyarFormatter.format(safeNumber(num));
    }
    
    function formatGrowth(val, isMilyar = false) {
        let num = safeNumber(val);
        let text = isMilyar ? formatMilyar(num) : formatNum(num);
        // Tanda panah warna netral (merah/hijau ada di angka)
        let colorClass = num > 0 ? 'text-success' : (num < 0 ? 'text-danger' : '');
        return `<span class="${colorClass}">${text}</span>`;
    }

    // Conditional Formatting Normal (Bagus = Plus = Hijau, Jelek = Minus = Merah)
    function formatCellPct(val) {
        let num = safeNumber(val);
        let text = formatPercent(num, 1);
        if (num === 0) return `<td class="text-center">-</td>`;

        let isGood = (num > 0); 
        let bgClass = isGood ? 'bg-good' : 'bg-bad';
        let arrow = num > 0 ? '<i class="fas fa-caret-up val-up"></i>' : '<i class="fas fa-caret-down val-down"></i>';

        return `<td class="${bgClass} text-center">${text} ${arrow}</td>`;
    }

    // Conditional Formatting INVERSE KHUSUS SV 0 (Bagus = Minus = Hijau, Jelek = Plus = Merah)
    function formatCellPctInverse(val) {
        let num = safeNumber(val);
        let text = formatPercent(num, 1);
        if (num === 0) return `<td class="text-center">-</td>`;

        let isGood = (num < 0); // Minus itu berarti SV 0 berkurang, jadi Bagus (Hijau)
        let bgClass = isGood ? 'bg-good' : 'bg-bad';
        let arrow = num > 0 ? '<i class="fas fa-caret-up val-down"></i>' : '<i class="fas fa-caret-down val-up"></i>'; // Arrow disesuaikan warnanya

        return `<td class="${bgClass} text-center">${text} ${arrow}</td>`;
    }

    function loadData() {
        const seq = ++requestSeq;

        if (activeRequest && activeRequest.readyState !== 4) {
            activeRequest.abort();
        }

        $('#loadingIndicator').stop(true, true).show();

        let payload = {
            posisi: $('#filter_posisi').val(),
            tab: activeTab,
            branch_office: getSelectedBranches(),
            nama_uker: getSelectedUkers(),
            _token: '{{ csrf_token() }}'
        };

        activeRequest = $.ajax({
            url: "{{ route('report.data') }}", 
            type: "POST",
            data: payload,
            success: function(res) {
                if (seq !== requestSeq) {
                    return;
                }

                if(res.status === 'success') {
                    updateGroupLabel(res.group_label);
                    
                    $('.lbl-curr').text(res.labels.curr);
                    if(res.labels.prev_mom) { $('.lbl-prev-mom').text(res.labels.prev_mom); }
                    if (filterPosisiRka) {
                        filterPosisiRka.value = res.labels.rka || '--------';
                    }

                    const rows = [];

                    // ============================================
                    // RENDER TAB 1: QRIS UTAMA
                    // ============================================
                    if (activeTab === 'qris') {
                        res.data.forEach((row) => {
                            rows.push(`<tr>
                                <td class="text-left font-weight-bold text-dark sticky-col">${escapeHtml(row.branch)}</td>
                                
                                <td class="font-weight-bold">${formatNum(row.jml.curr)}</td>
                                <td>${formatGrowth(row.jml.mtd_val)}</td> ${formatCellPct(row.jml.mtd_pct)} 
                                <td>${formatGrowth(row.jml.ytd_val)}</td> <td>${formatGrowth(row.jml.yoy_val)}</td>
                                <td class="rka-col">${formatNum(row.jml.rka)}</td> <td class="rka-col">${formatNum(row.jml.penc_pct)}%</td>
                                
                                <td class="font-weight-bold">${formatNum(row.prod.curr)}</td> <td class="font-weight-bold text-dark">${formatPercent(row.prod.pct_jml, 1)}</td>
                                <td>${formatGrowth(row.prod.mtd_val)}</td> ${formatCellPct(row.prod.mtd_pct)} 
                                <td>${formatGrowth(row.prod.ytd_val)}</td> <td>${formatGrowth(row.prod.yoy_val)}</td>
                                <td class="rka-col">${formatNum(row.prod.rka)}</td> <td class="rka-col">${formatPercent(row.prod.penc_pct, 1)}</td>

                                <td class="font-weight-bold">${formatMilyar(row.vol.curr)}</td>
                                <td>${formatGrowth(row.vol.mtd_val, true)}</td> ${formatCellPct(row.vol.mtd_pct)} 
                                <td>${formatGrowth(row.vol.yoy_val, true)}</td>
                                <td class="rka-col">${formatMilyar(row.vol.rka)}</td> <td class="rka-col">${formatNum(row.vol.penc_pct)}%</td>
                            </tr>`);
                        });

                        let total = res.total;
                        rows.push(`<tr class="row-total">
                            <td class="text-left sticky-col">${escapeHtml(total.branch)}</td>
                            
                            <td>${formatNum(total.jml.curr)}</td>
                            <td>${formatGrowth(total.jml.mtd_val)}</td> ${formatCellPct(total.jml.mtd_pct).replace(/bg-(good|bad)/, '')} 
                            <td>${formatGrowth(total.jml.ytd_val)}</td> <td>${formatGrowth(total.jml.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatNum(total.jml.rka)}</td> <td class="rka-col text-dark">${formatNum(total.jml.penc_pct)}%</td>
                            
                            <td>${formatNum(total.prod.curr)}</td> <td>${formatPercent(total.prod.pct_jml, 1)}</td>
                            <td>${formatGrowth(total.prod.mtd_val)}</td> ${formatCellPct(total.prod.mtd_pct).replace(/bg-(good|bad)/, '')} 
                            <td>${formatGrowth(total.prod.ytd_val)}</td> <td>${formatGrowth(total.prod.yoy_val)}</td>
                            <td class="rka-col text-dark">${formatNum(total.prod.rka)}</td> <td class="rka-col text-dark">${formatPercent(total.prod.penc_pct, 1)}</td>

                            <td>${formatMilyar(total.vol.curr)}</td>
                            <td>${formatGrowth(total.vol.mtd_val, true)}</td> ${formatCellPct(total.vol.mtd_pct).replace(/bg-(good|bad)/, '')} 
                            <td>${formatGrowth(total.vol.yoy_val, true)}</td>
                            <td class="rka-col text-dark">${formatMilyar(total.vol.rka)}</td> <td class="rka-col text-dark">${formatNum(total.vol.penc_pct)}%</td>
                        </tr>`);

                        $('#tbody-qris').html(rows.join(''));
                    }
                    
                    // ============================================
                    // RENDER TAB 2: QRIS MoM
                    // ============================================
                    else if (activeTab === 'qris_mom') {
                        res.data.forEach((row) => {
                            rows.push(`<tr>
                                <td class="text-left font-weight-bold text-dark sticky-col">${escapeHtml(row.branch)}</td>
                                
                                <td>${formatNum(row.sv0.prev)}</td> <td>${formatNum(row.sv0.curr)}</td>
                                <td>${formatGrowth(row.sv0.mom)}</td> ${formatCellPctInverse(row.sv0.pct)} 
                                
                                <td>${formatNum(row.prod.prev)}</td> <td>${formatNum(row.prod.curr)}</td>
                                <td>${formatGrowth(row.prod.mom)}</td> ${formatCellPct(row.prod.pct)} 
                                <td class="rka-col">${formatNum(row.prod.rka)}</td> <td class="rka-col">${formatNum(row.prod.gap)}</td> <td class="rka-col">${formatPercent(row.prod.penc, 1)}</td>
                                
                                <td>${formatNum(row.store.prev)}</td> <td>${formatNum(row.store.curr)}</td>
                                <td>${formatGrowth(row.store.mom)}</td> ${formatCellPct(row.store.pct)} 
                                
                                <td>${formatMilyar(row.vol.prev)}</td> <td>${formatMilyar(row.vol.curr)}</td>
                                <td>${formatGrowth(row.vol.mom, true)}</td> ${formatCellPct(row.vol.pct)} 
                            </tr>`);
                        });
                        
                        let total = res.total;
                        rows.push(`<tr class="row-total">
                            <td class="text-left sticky-col">${escapeHtml(total.branch)}</td>
                            
                            <td>${formatNum(total.sv0.prev)}</td> <td>${formatNum(total.sv0.curr)}</td>
                            <td>${formatGrowth(total.sv0.mom)}</td> ${formatCellPctInverse(total.sv0.pct).replace(/bg-(good|bad)/, '')}
                            
                            <td>${formatNum(total.prod.prev)}</td> <td>${formatNum(total.prod.curr)}</td>
                            <td>${formatGrowth(total.prod.mom)}</td> ${formatCellPct(total.prod.pct).replace(/bg-(good|bad)/, '')}
                            <td class="rka-col text-dark">${formatNum(total.prod.rka)}</td> <td class="rka-col text-dark">${formatNum(total.prod.gap)}</td> <td class="rka-col text-dark">${formatPercent(total.prod.penc, 1)}</td>
                            
                            <td>${formatNum(total.store.prev)}</td> <td>${formatNum(total.store.curr)}</td>
                            <td>${formatGrowth(total.store.mom)}</td> ${formatCellPct(total.store.pct).replace(/bg-(good|bad)/, '')}

                            <td>${formatMilyar(total.vol.prev)}</td> <td>${formatMilyar(total.vol.curr)}</td>
                            <td>${formatGrowth(total.vol.mom, true)}</td> ${formatCellPct(total.vol.pct).replace(/bg-(good|bad)/, '')}
                        </tr>`);

                        $('#tbody-qris-mom').html(rows.join(''));
                    }
                }
                $('#loadingIndicator').stop(true, true).fadeOut('fast');
            },
            error: function(xhr, status) {
                if (status === 'abort') {
                    return;
                }
                $('#loadingIndicator').stop(true, true).fadeOut('fast');
            }
        });
    }

    function scheduleLoadData(delayMs = 180) {
        if (loadTimer) {
            clearTimeout(loadTimer);
        }
        loadTimer = setTimeout(function () {
            loadData();
        }, delayMs);
    }

    $('.filter-trigger').on('change', function() { scheduleLoadData(); });
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
        loadNamaUkerOptions().always(function () {
            scheduleLoadData();
        });
    });
    $(document).on('change', '.filter-uker-checkbox', function () {
        updateUkerLabel();
        scheduleLoadData();
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.branch-filter-dropdown').length) {
            closeBranchDropdown();
        }
        if (!$(e.target).closest('.uker-filter-dropdown').length) {
            closeUkerDropdown();
        }
    });
    
    // Trigger load data when tab is changed
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) { 
        activeTab = $(e.target).data('tab'); 
        scheduleLoadData(50); 
    });
    
    // Initial Load
    loadNamaUkerOptions();
    updateBranchLabel();
    loadData();
});
</script>
@endsection
