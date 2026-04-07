@extends('layouts.admin')

@section('title', 'Rasio CASA Debitur')

@section('content')
<style>
    .casa-dashboard {
        padding-bottom: 1.5rem;
    }

    .casa-shell,
    .casa-table-shell {
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.22);
    }

    .casa-shell .card-body,
    .casa-table-shell .card-header,
    .casa-table-shell .card-body {
        background: #ffffff;
    }

    .casa-page-title {
        font-size: clamp(1.7rem, 2.7vw, 2.4rem);
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.35rem;
    }

    .casa-page-copy {
        color: #64748b;
        font-size: 0.92rem;
        margin-bottom: 0;
    }

    .casa-filter-label {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.45rem;
    }

    .casa-filter-control {
        border-radius: 14px !important;
        min-height: 44px !important;
        height: 44px !important;
        border-color: #cfdae6 !important;
        background: #ffffff !important;
        font-size: 0.94rem;
        display: flex;
        align-items: center;
    }

    .casa-filter-control:disabled {
        background: #edf2f7 !important;
        color: #64748b !important;
    }

    .casa-filter-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        color: #64748b;
        font-size: 0.84rem;
        margin-top: 0.85rem;
    }

    .casa-action {
        min-width: 150px;
        min-height: 44px;
        border-radius: 14px;
        font-weight: 700;
        box-shadow: 0 12px 24px -18px rgba(37, 99, 235, 0.75);
    }

    .casa-loading-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        border-radius: 999px;
        padding: 0.55rem 0.9rem;
        background: linear-gradient(135deg, #eff6ff, #ecfeff);
        color: #0f766e;
        font-size: 0.8rem;
        font-weight: 800;
    }

    .casa-loading-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #14b8a6;
        box-shadow: 0 0 0 rgba(20, 184, 166, 0.45);
        animation: casaPulse 1.6s infinite;
    }

    @keyframes casaPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.45); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(20, 184, 166, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(20, 184, 166, 0); }
    }

    .casa-table-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .casa-table-heading h5 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .casa-table-heading p {
        margin: 0.25rem 0 0;
        color: #64748b;
        font-size: 0.88rem;
    }

    .casa-table-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 999px;
        padding: 0.45rem 0.7rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.79rem;
        font-weight: 700;
    }

    .casa-table-stage {
        position: relative;
        min-height: 520px;
    }

    .casa-loading-overlay {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        justify-content: center;
        align-items: center;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.97));
        backdrop-filter: blur(4px);
        transition: opacity 0.28s ease, visibility 0.28s ease;
    }

    .casa-loading-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .casa-loading-title {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }

    .casa-loading-copy {
        max-width: 520px;
        text-align: center;
        color: #64748b;
        font-size: 0.9rem;
        margin: 0;
    }

    .casa-skeleton-grid {
        width: min(860px, 100%);
        display: grid;
        grid-template-columns: 220px repeat(7, 1fr);
        gap: 0.75rem;
    }

    .casa-skeleton-cell {
        height: 16px;
        border-radius: 999px;
        background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
        background-size: 220% 100%;
        animation: casaShimmer 1.3s infinite linear;
    }

    .casa-skeleton-cell.is-wide {
        height: 18px;
    }

    @keyframes casaShimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table-report {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        table-layout: auto;
        white-space: nowrap;
        min-width: 920px;
    }

    .table-report th,
    .table-report td {
        vertical-align: middle !important;
        border-right: 1px solid rgba(255, 255, 255, 0.28);
        border-bottom: 1px solid #e2e8f0;
    }

    .table-report th {
        font-size: 0.72rem;
        padding: 12px 8px;
        text-align: center;
        font-weight: 800;
    }

    .table-report td {
        font-size: 0.76rem;
        padding: 11px 10px;
        text-align: right;
        background: #ffffff;
    }

    .table-report td.text-left {
        text-align: left;
    }

    .bg-header-main {
        background: linear-gradient(135deg, #1d4ed8, #1e3a8a) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.22) !important;
    }

    .bg-header-sub {
        background: #64748b !important;
        color: #ffffff !important;
        font-weight: bold;
        border-color: rgba(255, 255, 255, 0.22) !important;
    }

    .bg-header-sub-light {
        background: #e2e8f0 !important;
        color: #334155 !important;
        font-weight: bold;
        border-color: #cbd5e1 !important;
    }

    .table-hover tbody tr:hover { background-color: #f1f7ff; }

    .row-total {
        background-color: #0f172a !important;
        color: #ffffff !important;
        font-weight: bold;
    }

    .row-total td {
        background-color: #0f172a !important;
        color: #ffffff !important;
    }

    .row-total .ratio-negative,
    .row-total .ratio-positive,
    .row-total .ratio-neutral {
        background-color: #0f172a !important;
        color: #ffffff !important;
    }

    .loading-row td {
        text-align: center !important;
        color: #6b7280;
        font-style: italic;
        padding: 18px 10px !important;
    }

    .val-up { color: #111111; font-weight: bold; }
    .val-down { color: #198754; font-weight: bold; }

    .ratio-positive {
        background-color: #dcfce7 !important;
        color: #111111 !important;
        font-weight: bold;
    }

    .ratio-negative {
        background-color: #fee2e2 !important;
        color: #198754 !important;
        font-weight: bold;
    }

    .ratio-neutral {
        background-color: #f8fafc !important;
        color: #111111 !important;
        font-weight: bold;
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
        font-weight: 700;
        color: #64748b;
        padding: 12px 18px;
        font-size: 0.95rem;
        background: transparent;
    }

    .nav-tabs.report-tabs .nav-link.active {
        border-bottom: 3px solid #2563eb;
        color: #2563eb;
        background: transparent;
    }

    .nav-tabs.report-tabs .nav-link:hover {
        border-bottom: 3px solid #93c5fd;
        color: #2563eb;
        background: transparent;
    }

    @media (max-width: 767.98px) {
        .casa-action {
            width: 100%;
        }

        .casa-filter-meta {
            gap: 0.45rem 0.9rem;
        }
    }
</style>

<div class="casa-dashboard">
    <div class="card card-outline card-primary shadow-sm mb-3 casa-shell">
        <div class="card-body py-4 px-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
                <div>
                    <h1 class="casa-page-title">Rasio CASA Debitur</h1>
                    <p class="casa-page-copy">Pilih periode akhir lalu klik <strong>Tampilkan</strong> untuk menjalankan query dan memuat ringkasan rasio CASA per branch.</p>
                </div>
            </div>

            <form id="filterForm">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <div class="form-group mb-3 mb-md-0">
                            <label class="casa-filter-label">Periode Akhir</label>
                            <input type="date" id="filter_posisi" name="posisi" class="form-control casa-filter-control" value="{{ $defaultPeriod ?? date('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3 mb-md-0">
                            <label class="casa-filter-label">Branch Office (Kanca)</label>
                            <input type="text" class="form-control casa-filter-control font-weight-bold" value="Area 6 - All" disabled>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3 mb-md-0">
                            <label class="casa-filter-label">Nama Uker</label>
                            <input type="text" class="form-control casa-filter-control" value="ALL UKER" disabled>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" id="submitButton" class="btn btn-primary btn-block casa-action">
                            <i class="fas fa-play mr-1"></i> Tampilkan
                        </button>
                    </div>
                </div>
            </form>

            <div class="casa-filter-meta">
                <span><strong>Mode:</strong> manual query</span>
                <span><strong>Area:</strong> KC Madiun, Magetan, Ngawi, Ponorogo</span>
                <span id="filterMetaPeriod"><strong>Periode aktif:</strong> belum dijalankan</span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4 casa-table-shell">
        <div class="card-body p-3 p-lg-4">
            <div class="casa-table-heading">
                <div>
                    <h5>Ringkasan Rasio CASA</h5>
                    <p>Query akan berjalan setelah filter dikirim. Data ditampilkan per branch dan dikelompokkan ke tab sesuai segmen.</p>
                </div>
                <div class="d-flex align-items-center flex-wrap justify-content-end" style="gap: 0.75rem;">
                    <span id="loadingChip" class="casa-loading-chip d-none">
                        <span class="casa-loading-dot"></span>
                        Memproses query...
                    </span>
                    <span id="summaryBadge" class="casa-table-badge">Belum ada data</span>
                </div>
            </div>

            <div class="casa-table-stage">
                <div id="tableOverlay" class="casa-loading-overlay">
                    <div class="casa-loading-title" id="overlayTitle">Siap Memuat Data</div>
                    <p class="casa-loading-copy" id="overlayCopy">Pilih periode akhir lalu klik <strong>Tampilkan</strong> untuk menjalankan query rasio CASA debitur.</p>
                    <div class="casa-skeleton-grid" aria-hidden="true">
                        <span class="casa-skeleton-cell is-wide"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell is-wide"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                        <span class="casa-skeleton-cell"></span>
                    </div>
                </div>

                <div class="card-header bg-white p-0 border-bottom-0">
                    <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab">
                                <i class="fas fa-chart-pie mr-1"></i> Total
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-briguna-kpr" role="tab">
                                <i class="fas fa-home mr-1"></i> BRIGUNA & KPR
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-mikro-smc" role="tab">
                                <i class="fas fa-store mr-1"></i> MIKRO & SMC
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-total" role="tabpanel">
                        <div class="table-container">
                            <table class="table table-hover table-report m-0">
                                <thead class="sticky-top" style="z-index: 2;">
                                    <tr>
                                        <th rowspan="3" class="bg-header-main align-middle" style="min-width: 170px;">BRANCH OFFICE</th>
                                        <th colspan="7" class="bg-header-main">TOTAL</th>
                                    </tr>
                                    <tr class="bg-header-sub">
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                    </tr>
                                    <tr class="bg-header-sub-light">
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-total"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-briguna-kpr" role="tabpanel">
                        <div class="table-container">
                            <table class="table table-hover table-report m-0">
                                <thead class="sticky-top" style="z-index: 2;">
                                    <tr>
                                        <th rowspan="3" class="bg-header-main align-middle" style="min-width: 170px;">BRANCH OFFICE</th>
                                        <th colspan="7" class="bg-header-main">BRIGUNA</th>
                                        <th colspan="7" class="bg-header-main">KPR</th>
                                    </tr>
                                    <tr class="bg-header-sub">
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                    </tr>
                                    <tr class="bg-header-sub-light">
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-briguna-kpr"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-mikro-smc" role="tabpanel">
                        <div class="table-container">
                            <table class="table table-hover table-report m-0">
                                <thead class="sticky-top" style="z-index: 2;">
                                    <tr>
                                        <th rowspan="3" class="bg-header-main align-middle" style="min-width: 170px;">BRANCH OFFICE</th>
                                        <th colspan="7" class="bg-header-main">MIKRO</th>
                                        <th colspan="7" class="bg-header-main">SMC</th>
                                    </tr>
                                    <tr class="bg-header-sub">
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                        <th colspan="2">Total OS</th>
                                        <th colspan="2">Total CASA</th>
                                        <th colspan="3">Rasio CASA/OS</th>
                                    </tr>
                                    <tr class="bg-header-sub-light">
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th class="lbl-prev-th">-</th>
                                        <th class="lbl-curr-th">-</th>
                                        <th>MtD</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-mikro-smc"></tbody>
                            </table>
                        </div>
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
    const form = document.getElementById('filterForm');
    const submitButton = document.getElementById('submitButton');
    const loadingChip = document.getElementById('loadingChip');
    const summaryBadge = document.getElementById('summaryBadge');
    const filterMetaPeriod = document.getElementById('filterMetaPeriod');
    const overlay = document.getElementById('tableOverlay');
    const overlayTitle = document.getElementById('overlayTitle');
    const overlayCopy = document.getElementById('overlayCopy');
    const filterPosisi = document.getElementById('filter_posisi');
    let activeRequest = null;

    function formatNum(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(parseFloat(num));
    }

    function formatPct(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(num)) + '%';
    }

    function getRatioClass(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '';
        const val = parseFloat(num);
        if (val < 0) return 'ratio-negative';
        if (val > 0) return 'ratio-positive';
        return 'ratio-neutral';
    }

    function formatMtd(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        const val = parseFloat(num);
        const text = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val) + '%';
        if (val > 0) return `<span class="val-up">+${text}</span>`;
        if (val < 0) return `<span class="val-down">${text}</span>`;
        return text;
    }

    function createDataCells(dt) {
        dt = dt || {};
        const rasioPrevClass = getRatioClass(dt.rasio_prev);
        const rasioCurrClass = getRatioClass(dt.rasio_curr);
        const mtdClass = getRatioClass(dt.mtd);

        return `
            <td>${formatNum(dt.os_prev)}</td>
            <td style="background-color: #f8fafc;">${formatNum(dt.os_curr)}</td>
            <td>${formatNum(dt.casa_prev)}</td>
            <td style="background-color: #f8fafc;">${formatNum(dt.casa_curr)}</td>
            <td class="${rasioPrevClass}">${formatPct(dt.rasio_prev)}</td>
            <td class="font-weight-bold ${rasioCurrClass}">${formatPct(dt.rasio_curr)}</td>
            <td class="${mtdClass}">${formatMtd(dt.mtd)}</td>
        `;
    }

    function updateTableLabels(prev, curr) {
        $('.lbl-prev-th').text(prev || '-');
        $('.lbl-curr-th').text(curr || '-');
    }

    function setOverlay(title, copy, visible = true) {
        overlayTitle.textContent = title;
        overlayCopy.innerHTML = copy;
        overlay.classList.toggle('is-hidden', !visible);
    }

    function renderMessage(message) {
        const html = `
            <tr class="loading-row">
                <td colspan="15" class="text-center">${message}</td>
            </tr>`;
        $('#tbody-total').html(html.replace('15', '8'));
        $('#tbody-briguna-kpr').html(html);
        $('#tbody-mikro-smc').html(html);
    }

    function renderRows(dataList, totalData) {
        let htmlTotal = '';
        let htmlBrigunaKpr = '';
        let htmlMikroSmc = '';

        dataList.forEach(function(row) {
            const branchCell = `<td class="text-left font-weight-bold">${row.branch || '-'}</td>`;
            htmlTotal += `<tr>${branchCell}${createDataCells(row.total)}</tr>`;
            htmlBrigunaKpr += `<tr>${branchCell}${createDataCells(row.briguna)}${createDataCells(row.kpr)}</tr>`;
            htmlMikroSmc += `<tr>${branchCell}${createDataCells(row.mikro)}${createDataCells(row.smc)}</tr>`;
        });

        const totalBranchCell = '<td class="text-left">TOTAL AREA 6</td>';
        htmlTotal += `<tr class="row-total">${totalBranchCell}${createDataCells(totalData.total)}</tr>`;
        htmlBrigunaKpr += `<tr class="row-total">${totalBranchCell}${createDataCells(totalData.briguna)}${createDataCells(totalData.kpr)}</tr>`;
        htmlMikroSmc += `<tr class="row-total">${totalBranchCell}${createDataCells(totalData.mikro)}${createDataCells(totalData.smc)}</tr>`;

        $('#tbody-total').html(htmlTotal);
        $('#tbody-briguna-kpr').html(htmlBrigunaKpr);
        $('#tbody-mikro-smc').html(htmlMikroSmc);
    }

    function resetTableState() {
        updateTableLabels('-', '-');
        summaryBadge.textContent = 'Belum ada data';
        filterMetaPeriod.innerHTML = '<strong>Periode aktif:</strong> belum dijalankan';
        renderMessage('Belum ada data. Klik <strong>Tampilkan</strong>.');
        setOverlay('Siap Memuat Data', 'Pilih filter lalu klik Tampilkan.', true);
    }

    async function loadData() {
        if (activeRequest && typeof activeRequest.abort === 'function') {
            activeRequest.abort();
        }

        loadingChip.classList.remove('d-none');
        submitButton.disabled = true;
        renderMessage('Memproses rasio CASA debitur...');
        setOverlay('Sedang Mengolah', 'Memproses data rasio CASA debitur.', true);

        activeRequest = $.ajax({
            url: "{{ route('report.data.rasiocasa') }}",
            type: 'POST',
            data: {
                posisi: filterPosisi.value,
                _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
        });

        try {
            const res = await activeRequest;

            if (res.status !== 'success') {
                renderMessage(res.message || 'Data tidak berhasil dimuat dari server.');
                summaryBadge.textContent = 'Gagal memuat data';
                setOverlay('Gagal Memuat Data', res.message || 'Terjadi kendala saat memproses query. Silakan coba lagi.', true);
                return;
            }

            const labels = res.labels || {};
            const effectiveDates = res.effective_dates || {};
            const dataList = res.data || [];
            const totalData = res.total || {};
            const meta = res.meta || {};
            const currentDate = effectiveDates.curr || filterPosisi.value;
            const hasAnyData = meta.has_rows === true || dataList.length > 0;

            updateTableLabels(labels.prev || '-', labels.curr || '-');

            if (currentDate) {
                filterPosisi.value = currentDate;
            }

            filterMetaPeriod.innerHTML = `<strong>Periode aktif:</strong> ${labels.curr || '-'} | <strong>Perbandingan:</strong> ${labels.prev || '-'}`;

            if (!hasAnyData) {
                renderMessage(`Tidak ada data untuk tanggal ${currentDate}. Coba pilih tanggal lain.`);
                summaryBadge.textContent = 'Data kosong';
                setOverlay('Tidak Ada Data', `Tidak ada data untuk periode <strong>${currentDate}</strong>.`, true);
                return;
            }

            renderRows(dataList, totalData);
            summaryBadge.textContent = `${dataList.length} branch | ${labels.curr || currentDate}`;
            setOverlay('Data Siap Ditampilkan', 'Data siap ditampilkan.', false);
        } catch (xhr) {
            if (xhr && xhr.statusText === 'abort') {
                return;
            }

            let errorMsg = 'Gagal memuat data. ';
            if (xhr && xhr.status === 500) errorMsg += 'Server error. Periksa `storage/logs/laravel.log`.';
            else errorMsg += 'Silakan coba lagi.';

            renderMessage(errorMsg);
            summaryBadge.textContent = 'Gagal memuat data';
            setOverlay('Gagal Memuat Data', errorMsg, true);
        } finally {
            loadingChip.classList.add('d-none');
            submitButton.disabled = false;
            activeRequest = null;
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadData();
    });

    resetTableState();
});
</script>
@endsection
