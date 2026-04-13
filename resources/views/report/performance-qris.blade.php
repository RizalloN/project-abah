@extends('layouts.admin')

@section('title', 'Performance QRIS')

@section('content')

<style>
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
    .table-report th { font-size: 0.65rem; padding: 10px 4px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 4px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    /* Pewarnaan Header Khas QRIS (TAB 1) */
    .bg-qris-jml { background-color: #2F5597 !important; color: #ffffff !important; border-color: #203b6b !important; }
    .bg-qris-prod { background-color: #5B9BD5 !important; color: #ffffff !important; border-color: #3f7bb5 !important; }
    .bg-qris-vol { background-color: #A5A5A5 !important; color: #ffffff !important; border-color: #7b7b7b !important; }
    .bg-header-sub { background-color: #E9EEF4 !important; color: #2F5597 !important; font-weight: bold; }

    /* Pewarnaan Header QRIS MoM (TAB 2) Sesuai Screenshot */
    .bg-mom-blue { background-color: #2F5597 !important; color: #ffffff !important; border-color: #203b6b !important; }

    /* Conditional Formatting Latar Belakang Sel (%) */
    .bg-good { background-color: #d4edda !important; color: #155724 !important; font-weight: bold;}
    .bg-bad { background-color: #f8d7da !important; color: #721c24 !important; font-weight: bold;}

    .table-hover tbody tr:hover { background-color: #f0f4fa; }
    .row-total { --row-total-bg: #2F5597; --row-total-color: #ffffff; --row-total-border: #203b6b; background-color: #2F5597 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; border-color: #203b6b !important; }
    .val-up { color: #28a745; font-weight: bold; margin-left: 2px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 2px; }
    
    .rka-col { background-color: #fff3cd !important; color: #856404 !important; font-weight: 600; border-color: #f6e3a6 !important; }
    .row-total .rka-col { background-color: #ffe8a1 !important; color: #856404 !important; }
    
    /* Nav Tabs Styling */
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>

<div class="card card-outline card-success shadow-sm mb-4 report-filter-card">
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
            <div class="col-md-2">
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
                                <th rowspan="2" class="bg-qris-jml align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 140px;">BRANCH OFFICE</th>
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
                                <th rowspan="2" class="bg-mom-blue align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UKER" style="min-width: 140px;">BRANCH OFFICE</th>
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

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    let activeTab = 'qris';
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

    function syncNamaUkerOptions() {
        const availableUkers = getAvailableUkers();
        const selectedUkers = getSelectedUkers();
        const $ukerMenu = $('#filterUkerMenu');
        $ukerMenu.empty();

        availableUkers.forEach(function (uker) {
            const slug = uker.toLowerCase().replace(/[^a-z0-9]+/g, '_');
            const escapedUker = $('<div>').text(uker).html();
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

    function formatNum(num) { return new Intl.NumberFormat('id-ID').format(safeNumber(num)); }

    function formatPercent(num, digits = 1) {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        }).format(safeNumber(num)) + '%';
    }
    
    function formatMilyar(num) {
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
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
                    
                    $('.lbl-curr').text(res.labels.curr);
                    if(res.labels.prev_mom) { $('.lbl-prev-mom').text(res.labels.prev_mom); }

                    let html = '';

                    // ============================================
                    // RENDER TAB 1: QRIS UTAMA
                    // ============================================
                    if (activeTab === 'qris') {
                        res.data.forEach((row) => {
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                
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
                            </tr>`;
                        });

                        let total = res.total;
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            
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
                        </tr>`;

                        $('#tbody-qris').html(html);
                    }
                    
                    // ============================================
                    // RENDER TAB 2: QRIS MoM
                    // ============================================
                    else if (activeTab === 'qris_mom') {
                        res.data.forEach((row) => {
                            html += `<tr>
                                <td class="text-left font-weight-bold text-dark">${row.branch}</td>
                                
                                <td>${formatNum(row.sv0.prev)}</td> <td>${formatNum(row.sv0.curr)}</td>
                                <td>${formatGrowth(row.sv0.mom)}</td> ${formatCellPctInverse(row.sv0.pct)} 
                                
                                <td>${formatNum(row.prod.prev)}</td> <td>${formatNum(row.prod.curr)}</td>
                                <td>${formatGrowth(row.prod.mom)}</td> ${formatCellPct(row.prod.pct)} 
                                <td class="rka-col">${row.prod.rka}</td> <td class="rka-col">${row.prod.gap}</td> <td class="rka-col">${row.prod.penc}</td>
                                
                                <td>${formatNum(row.store.prev)}</td> <td>${formatNum(row.store.curr)}</td>
                                <td>${formatGrowth(row.store.mom)}</td> ${formatCellPct(row.store.pct)} 
                                
                                <td>${formatMilyar(row.vol.prev)}</td> <td>${formatMilyar(row.vol.curr)}</td>
                                <td>${formatGrowth(row.vol.mom, true)}</td> ${formatCellPct(row.vol.pct)} 
                            </tr>`;
                        });
                        
                        let total = res.total;
                        html += `<tr class="row-total">
                            <td class="text-left">${total.branch}</td>
                            
                            <td>${formatNum(total.sv0.prev)}</td> <td>${formatNum(total.sv0.curr)}</td>
                            <td>${formatGrowth(total.sv0.mom)}</td> ${formatCellPctInverse(total.sv0.pct).replace(/bg-(good|bad)/, '')}
                            
                            <td>${formatNum(total.prod.prev)}</td> <td>${formatNum(total.prod.curr)}</td>
                            <td>${formatGrowth(total.prod.mom)}</td> ${formatCellPct(total.prod.pct).replace(/bg-(good|bad)/, '')}
                            <td class="rka-col text-dark">${total.prod.rka}</td> <td class="rka-col text-dark">${total.prod.gap}</td> <td class="rka-col text-dark">${total.prod.penc}</td>
                            
                            <td>${formatNum(total.store.prev)}</td> <td>${formatNum(total.store.curr)}</td>
                            <td>${formatGrowth(total.store.mom)}</td> ${formatCellPct(total.store.pct).replace(/bg-(good|bad)/, '')}
                            
                            <td>${formatMilyar(total.vol.prev)}</td> <td>${formatMilyar(total.vol.curr)}</td>
                            <td>${formatGrowth(total.vol.mom, true)}</td> ${formatCellPct(total.vol.pct).replace(/bg-(good|bad)/, '')}
                        </tr>`;
                        
                        $('#tbody-qris-mom').html(html);
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
    
    // Trigger load data when tab is changed
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) { 
        activeTab = $(e.target).data('tab'); 
        loadData(); 
    });

    // Initial Load
    syncNamaUkerOptions();
    updateBranchLabel();
    loadData();
});
</script>
@endsection
