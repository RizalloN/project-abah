@extends('layouts.admin')

@section('title', 'Performance Qlola')

@section('content')
<style>
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
    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-report {
        border-collapse: collapse;
        width: 100%;
        min-width: 720px;
        table-layout: auto;
    }
    .table-report th,
    .table-report td {
        vertical-align: middle !important;
        border: 1px solid #dee2e6;
        white-space: normal;
    }
    .table-report th {
        font-size: 0.72rem;
        padding: 12px 8px;
        text-align: center;
    }
    .table-report td {
        font-size: 0.78rem;
        padding: 8px 10px;
        text-align: right;
    }
    .table-report td.text-left {
        text-align: left;
    }
    .bg-qlola-main {
        background-color: #003366 !important;
        color: #ffffff !important;
        border-color: #002244 !important;
        font-weight: 800;
    }
    .bg-qlola-mid {
        background-color: #00509e !important;
        color: #ffffff !important;
        border-color: #003c7a !important;
        font-weight: 800;
    }
    .bg-header-sub {
        background-color: #f4f6fa !important;
        color: #333 !important;
        font-weight: 700;
    }
    .row-total td {
        background-color: #003366 !important;
        color: #ffffff !important;
        font-weight: 800;
    }
    .content-wrapper .table-container table.table-report.qlola-no-hover tbody tr:hover > td {
        background-color: #ffffff !important;
        background-image: none !important;
    }
    .content-wrapper .table-container table.table-report.qlola-no-hover tbody tr:nth-child(even):not(.row-total):hover > td {
        background-color: #fafcff !important;
    }
    .content-wrapper .table-container table.table-report.qlola-no-hover tbody tr.row-total:hover > td {
        background-color: #003366 !important;
        color: #ffffff !important;
    }
    .nav-tabs.report-tabs {
        border-bottom: 2px solid #dee2e6;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        scrollbar-width: thin;
    }
    .nav-tabs.report-tabs .nav-link {
        border: none;
        font-weight: 600;
        color: #6c757d;
        padding: 12px 18px;
        font-size: 0.95rem;
        background: transparent;
    }
    .nav-tabs.report-tabs .nav-link.active {
        border-bottom: 3px solid #007bff;
        color: #007bff;
        background: transparent;
    }
</style>
@include('report._bri-report-ui')

<div class="pt-4">
    <div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
        <div class="card-body py-3">
            <div class="row align-items-end">
                <div class="d-none">
                    <div class="form-group mb-0">
                        <label class="text-muted text-sm mb-1">Nama Report</label>
                        <input type="text" class="form-control font-weight-bold" value="Performance Qlola" disabled>
                    </div>
                </div>
                <div class="col-md-5">
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
                <div class="col-md-5">
                    <div class="form-group mb-0">
                        <label class="text-muted text-sm mb-1">Unit Kerja</label>
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
                        <label class="text-muted text-sm mb-1">Sumber Data</label>
                        <input type="text" id="filter_source_period" class="form-control" disabled value="IB Bisnis">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4 report-data-card">
        <div class="card-header bg-white p-0 border-bottom-0">
            <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#tab-qlola" role="tab">
                        <i class="fas fa-building mr-1"></i> Performance Qlola
                    </a>
                </li>
                <li class="nav-item ml-auto d-flex align-items-center pr-2">
                    <span id="loadingIndicator" class="text-primary font-weight-bold" style="display: none; font-size: 0.9rem;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat Data...
                    </span>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-qlola" role="tabpanel">
                    <div class="table-container">
                        <table class="table table-report qlola-no-hover m-0">
                            <thead class="sticky-top" style="z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="bg-qlola-main align-middle col-group-label" data-default-label="BRANCH OFFICE" data-filtered-label="UNIT KERJA" style="min-width: 180px;">BRANCH OFFICE</th>
                                    <th colspan="3" class="bg-qlola-mid">Performance Qlola</th>
                                </tr>
                                <tr class="bg-header-sub">
                                    <th>Jumlah User Aktif</th>
                                    <th>Jumlah Transaksi</th>
                                    <th>Nominal Transaksi</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-qlola">
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-3"></i></td>
                                </tr>
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
    const branchUkerMap = @json($branchUkerMap ?? []);
    let qlolaXhr = null;

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function formatNum(num, decimals = 0) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(parseFloat(num));
    }

    function formatCurrency(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return formatNum(num, 0);
    }

    function formatPeriod(value) {
        if (!value) return 'IB Bisnis';
        const parts = String(value).split('-');
        if (parts.length < 2) return 'IB Bisnis';
        const monthList = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const monthIndex = parseInt(parts[1], 10) - 1;
        const year = parts[0].slice(-2);
        return `IB Bisnis ${monthList[monthIndex] || parts[1]}'${year}`;
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
            const nextText = normalizedLabel === 'UNIT KERJA'
                ? ($label.data('filtered-label') || 'UNIT KERJA')
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
            const escapedUker = escapeHtml(uker);
            const isChecked = selectedUkers.includes(uker) ? 'checked' : '';
            $ukerMenu.append(`
                <label class="dropdown-item" for="uker_${safeId}">
                    <div class="form-check">
                        <input class="form-check-input filter-uker-checkbox" type="checkbox" value="${escapedUker}" id="uker_${safeId}" ${isChecked}>
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

    function renderRows(payload) {
        const rows = [];
        (payload.data || []).forEach(function (row) {
            rows.push(`<tr>
                <td class="text-left font-weight-bold text-dark">${escapeHtml(row.branch || '-')}</td>
                <td>${formatNum(row.jumlah_user_aktif)}</td>
                <td>${formatNum(row.jumlah_transaksi)}</td>
                <td>${formatCurrency(row.nominal_transaksi)}</td>
            </tr>`);
        });

        if (payload.total) {
            rows.push(`<tr class="row-total">
                <td class="text-left">${escapeHtml(payload.total.branch || 'TOTAL AREA 6')}</td>
                <td>${formatNum(payload.total.jumlah_user_aktif)}</td>
                <td>${formatNum(payload.total.jumlah_transaksi)}</td>
                <td>${formatCurrency(payload.total.nominal_transaksi)}</td>
            </tr>`);
        }

        $('#tbody-qlola').html(rows.length
            ? rows.join('')
            : '<tr><td colspan="4" class="text-center py-5 text-muted">Data tidak tersedia.</td></tr>');
    }

    function loadDataQlola() {
        if (qlolaXhr && qlolaXhr.readyState !== 4) {
            qlolaXhr.abort();
        }

        $('#loadingIndicator').fadeIn('fast');

        qlolaXhr = $.ajax({
            url: "{{ route('report.data') }}",
            type: "POST",
            dataType: "json",
            cache: false,
            data: {
                tab: 'qlola',
                branch_office: getSelectedBranches(),
                nama_uker: getSelectedUkers(),
                _token: '{{ csrf_token() }}'
            },
            success: function (res) {
                if (res.status === 'success') {
                    updateGroupLabel(res.group_label);
                    $('#filter_source_period').val(formatPeriod(res.labels?.corp_period || res.labels?.user_period));
                    renderRows(res);
                    return;
                }

                $('#tbody-qlola').html(`<tr><td colspan="4" class="text-center text-danger py-5">${escapeHtml(res.message || res.msg || 'Gagal memuat data.')}</td></tr>`);
            },
            error: function (err) {
                if (err.statusText === 'abort') return;
                $('#tbody-qlola').html('<tr><td colspan="4" class="text-center text-danger py-5">Gagal memuat data dari server.</td></tr>');
            },
            complete: function () {
                $('#loadingIndicator').fadeOut('fast');
            }
        });
    }

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
        loadDataQlola();
    });

    $(document).on('change', '.filter-uker-checkbox', function () {
        updateUkerLabel();
        loadDataQlola();
    });

    syncNamaUkerOptions();
    updateBranchLabel();
    loadDataQlola();
});
</script>
@endsection
