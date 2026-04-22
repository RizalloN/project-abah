@extends('layouts.admin')

@section('title', 'Dashboard Pinjaman Kredit SME')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .loan-credit-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        margin-bottom: 1rem;
        padding: 1.45rem 1.25rem;
        border-radius: 0 0 1.4rem 1.4rem;
        background:
            radial-gradient(circle at 12% 18%, rgba(255, 103, 31, 0.16), transparent 26%),
            radial-gradient(circle at 88% 10%, rgba(59, 130, 246, 0.22), transparent 28%),
            linear-gradient(135deg, #003b75 0%, #00529c 48%, #0f4c97 100%);
        color: #ffffff;
        box-shadow: 0 18px 40px -30px rgba(0, 55, 116, 0.55);
    }

    .loan-credit-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(120deg, rgba(255, 255, 255, 0.12), transparent 35%),
            repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 18px);
        opacity: 0.72;
    }

    .loan-credit-title-wrap {
        width: min(100%, 860px);
        text-align: center;
        padding: 0.05rem 1rem;
    }

    .loan-credit-title-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.6rem;
        padding: 0.32rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.24);
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.64rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }

    .loan-credit-title-badge i {
        color: #ffb15c;
    }

    .loan-credit-title {
        margin: 0;
        font-size: clamp(1.18rem, 2.05vw, 2rem);
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 0.035em;
        line-height: 1.08;
        text-transform: uppercase;
        text-shadow: 0 10px 26px rgba(0, 18, 50, 0.28);
    }

    .loan-credit-title::after {
        content: '';
        display: block;
        width: min(130px, 38vw);
        height: 3px;
        margin: 0.7rem auto 0;
        border-radius: 999px;
        background: linear-gradient(90deg, #ff671f, #f9b233, rgba(255, 255, 255, 0.9));
        box-shadow: 0 8px 18px rgba(255, 103, 31, 0.28);
    }

    .loan-credit-desc {
        margin: 0.65rem auto 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.78rem;
        line-height: 1.6;
        max-width: 660px;
    }

    @media (max-width: 575.98px) {
        .loan-credit-hero {
            padding: 1.15rem 0.85rem;
        }
    }
</style>

<div class="loan-dashboard pt-4 px-3">
    <div class="loan-credit-hero d-flex flex-wrap justify-content-center align-items-center">
        <div class="loan-credit-title-wrap">
            <div class="loan-credit-title-badge">
                <i class="fas fa-university"></i>
                <span>BRI Credit Performance</span>
            </div>
            <h1 class="loan-credit-title">DASHBOARD PINJAMAN KREDIT</h1>
            <p class="loan-credit-desc">Analisis performa portofolio berdasarkan segmen dan kategori untuk memantau kualitas pinjaman harian secara ringkas.</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card loan-shell mb-4 animate-reveal">
        <div class="card-body p-4">
            <div class="row align-items-end g-3">
                <div class="col-md-4 text-start">
                    <label for="periodeSelector" class="loan-filter-label">Periode Terakhir</label>
                    <select id="periodeSelector" class="form-control loan-filter-control select2">
                        @foreach($periods as $periode)
                            <option value="{{ $periode }}" @selected($periode === $selectedPeriod)>
                                {{ \Carbon\Carbon::parse($periode)->format('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 text-start">
                    <label for="kategoriSelector" class="loan-filter-label">Kategori</label>
                    <select id="kategoriSelector" class="form-control loan-filter-control select2">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected($cat === $selectedCategory)>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn btn-primary w-100" id="btnLoadData" style="height: 40px; border-radius: 11px; font-weight: 700;">
                        <i class="fas fa-sync-alt mr-2"></i> PERBARUI DASHBOARD
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Meta Information -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div class="loan-loading-chip">
            <span class="loan-loading-dot"></span>
            <span id="dashboardMeta">Menyiapkan dashboard kredit harian.</span>
        </div>
        <div class="text-muted small">Data diambil dari snapshot harian per cabang.</div>
    </div>

    <!-- Dashboard Content -->
    <div id="dashboardContent" class="animate-reveal">
        
        <!-- OS Section -->
        <div class="loan-section-block mb-4">
            <div class="loan-section-header">
                <h3 id="osTitle">A. OUTSTANDING (OS)</h3>
                <div class="legend-box ml-auto">
                    <i class="fas fa-info-circle"></i>
                    <span>Angka dalam <strong>Rp, Juta</strong></span>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="osTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'Outstanding'])
            </div>
        </div>

        <!-- SML Section -->
        <div class="loan-section-block mb-4">
            <div class="loan-section-header">
                <h3 id="smlTitle">B. SPECIAL MENTION LOAN (SML)</h3>
                <div class="legend-box ml-auto">
                    <i class="fas fa-info-circle"></i>
                    <span>Angka dalam <strong>Rp, Juta</strong></span>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="smlTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'SML'])
            </div>
        </div>

        <!-- NPL Section -->
        <div class="loan-section-block mb-4">
            <div class="loan-section-header">
                <h3 id="nplTitle">C. NON-PERFORMING LOAN (NPL)</h3>
                <div class="legend-box ml-auto">
                    <i class="fas fa-info-circle"></i>
                    <span>Angka dalam <strong>Rp, Juta</strong></span>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="nplTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'NPL'])
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const osTableContainer = document.getElementById('osTableContainer');
    const smlTableContainer = document.getElementById('smlTableContainer');
    const nplTableContainer = document.getElementById('nplTableContainer');
    const btnLoadData = document.getElementById('btnLoadData');
    const dashboardMeta = document.getElementById('dashboardMeta');

    // Request timeout (in milliseconds)
    const REQUEST_TIMEOUT = 45000; // 45 seconds
    let requestAbortController = null;

    // Select2 elements
    const $periodeSel = $('#periodeSelector');
    const $kategoriSel = $('#kategoriSelector');

    if (window.jQuery && window.jQuery.fn.select2) {
        window.jQuery('.select2').each(function () {
            const $element = window.jQuery(this);
            if ($element.hasClass('select2-hidden-accessible')) {
                return;
            }

            $element.select2({
                theme: 'bootstrap4',
                width: '100%'
            });
        });
    }

    function formatCurrency(value) {
        if (value === null || value === undefined || value === '') return '-';
        const num = parseFloat(value) / 1000000; // Konversi ke Juta
        return new Intl.NumberFormat('id-ID', { 
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        }).format(num);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        } catch(e) { return dateStr; }
    }

    function formatPctBadge(value) {
        const num = parseFloat(value) || 0;
        let badgeClass = '';
        if (num >= 100) {
            badgeClass = 'pct-good';
        } else if (num >= 95) {
            badgeClass = 'pct-mid';
        } else {
            badgeClass = 'pct-bad';
        }
        return `<span class="pct-badge ${badgeClass}">${num.toFixed(1)}%</span>`;
    }

    function buildTable(data, headerDates, typeLabel, segmentName, rkaLabels) {
        if (!data || data.length === 0 || (data.length === 1 && data[0].is_total && data[0].selected == 0)) {
            return '<div class="text-center py-5 text-muted">Tidak ada data untuk filter ini.</div>';
        }

        const dates = {
            ytd: formatDate(headerDates.ytd),
            m2: formatDate(headerDates.m2),
            mtm: formatDate(headerDates.mtm),
            selected: formatDate(headerDates.selected)
        };

        const typePrefix = typeLabel.toUpperCase();

        let html = `
            <table class="loan-summary-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 40px;">NO</th>
                        <th rowspan="2" style="width: 120px;">KANTOR CABANG</th>
                        <th rowspan="2" style="width: 150px;">KATEGORI ${segmentName}</th>
                        <th colspan="4" class="sub-head">${typePrefix} PERIODE</th>
                        <th colspan="3" class="accent-head">DELTA (Δ) PERIODE</th>
                        <th colspan="2" class="sub-head">RKA-KP</th>
                        <th colspan="4" class="accent-head">PENCAPAIAN RKA</th>
                    </tr>
                    <tr>
                        <th class="sub-head" style="width: 85px;">${dates.ytd}<br><small>(YtD)</small></th>
                        <th class="sub-head" style="width: 85px;">${dates.m2}<br><small>(M-2)</small></th>
                        <th class="sub-head" style="width: 85px;">${dates.mtm}<br><small>(MtM)</small></th>
                        <th class="sub-head" style="background: #004280; width: 90px;">${dates.selected}<br><small>(HARI INI)</small></th>
                        <th class="accent-head" style="width: 80px;">YtD</th>
                        <th class="accent-head" style="width: 80px;">MtD</th>
                        <th class="accent-head" style="width: 80px;">DtD</th>
                        <th class="sub-head" style="width: 85px;">${rkaLabels?.m1 || ''}</th>
                        <th class="sub-head" style="width: 85px;">${rkaLabels?.current || ''}</th>
                        <th class="accent-head" style="width: 90px;">${rkaLabels?.m1 || ''} Δ</th>
                        <th class="accent-head" style="width: 70px;">%</th>
                        <th class="accent-head" style="width: 90px;">${rkaLabels?.current || ''} Δ</th>
                        <th class="accent-head" style="width: 70px;">%</th>
                    </tr>
                </thead>
                <tbody>
        `;

        const totalRow = data.find(row => row.is_total);
        const dataRows = data.filter(row => !row.is_total);

        // Group rows by branch
        const groups = {};
        dataRows.forEach(row => {
            const branch = row.branch || 'Unknown';
            if (!groups[branch]) groups[branch] = [];
            groups[branch].push(row);
        });

        let rowIndex = 1;
        Object.keys(groups).forEach(branchName => {
            const groupRows = groups[branchName];
            const groupSize = groupRows.length;

            // Subtotal accumulator
            const subtotal = {
                ytd: 0, m2: 0, mtm: 0, selected: 0, d_ytd: 0, d_mtd: 0, d_dtd: 0,
                rka_m1: 0, rka_current: 0, penc_m1_rp: 0, penc_cur_rp: 0
            };

            // Pre-calculate subtotals
            groupRows.forEach(row => {
                subtotal.ytd += parseFloat(row.ytd || 0);
                subtotal.m2 += parseFloat(row.m2 || 0);
                subtotal.mtm += parseFloat(row.mtm || 0);
                subtotal.selected += parseFloat(row.selected || 0);
                subtotal.d_ytd += parseFloat(row.delta_ytd || 0);
                subtotal.d_mtd += parseFloat(row.delta_mtd || 0);
                subtotal.d_dtd += parseFloat(row.delta_dtd || 0);
                subtotal.rka_m1 += parseFloat(row.rka_m1 || 0);
                subtotal.rka_current += parseFloat(row.rka_current || 0);
                subtotal.penc_m1_rp += parseFloat(row.penc_m1_rp || 0);
                subtotal.penc_cur_rp += parseFloat(row.penc_cur_rp || 0);
            });

            // Shorten Branch Name for Total Row
            const shortBranchName = branchName
                .replace(/KC Madiun/gi, 'KC MDN')
                .replace(/KC Magetan/gi, 'KC MGT')
                .replace(/KC Ngawi/gi, 'KC NGWI')
                .replace(/KC Ponorogo/gi, 'KC PNRG');

            // Calculate branch subtotal RKA percentages
            const subtotal_penc_m1_pct = subtotal.rka_m1 > 0 ? (subtotal.selected / subtotal.rka_m1) * 100 : 0;
            const subtotal_penc_cur_pct = subtotal.rka_current > 0 ? (subtotal.selected / subtotal.rka_current) * 100 : 0;
            const subtotal_pct_m1_badge = formatPctBadge(subtotal_penc_m1_pct);
            const subtotal_pct_cur_badge = formatPctBadge(subtotal_penc_cur_pct);

            // 1. Branch Subtotal Row FIRST
            html += `
                <tr class="loan-branch-subtotal">
                    <td rowspan="${groupSize + 1}" class="text-center-v" style="background: #f8fbff; font-weight: 700; border-bottom: 2px solid #cbd5e1; color: #1e293b !important;">${rowIndex++}</td>
                    <td rowspan="${groupSize + 1}" class="text-center-v text-start-important merged-branch-cell" style="border-bottom: 2px solid #cbd5e1;">${branchName}</td>
                    <td class="text-center-v" style="font-size: 0.68rem; letter-spacing: 0.05em; background: rgba(255,255,255,0.05); text-align: center !important; font-weight: 900; border-right: 1px solid rgba(255,255,255,0.1);">
                         TOTAL ${shortBranchName.toUpperCase()}
                    </td>
                    <td>${formatCurrency(subtotal.ytd)}</td>
                    <td>${formatCurrency(subtotal.m2)}</td>
                    <td>${formatCurrency(subtotal.mtm)}</td>
                    <td style="background: rgba(224, 242, 254, 0.15); color: #7dd3fc;">${formatCurrency(subtotal.selected)}</td>
                    <td style="color: ${subtotal.d_ytd < 0 ? '#fca5a5' : (subtotal.d_ytd > 0 ? '#86efac' : '#ffffff')}">${formatCurrency(subtotal.d_ytd)}</td>
                    <td style="color: ${subtotal.d_mtd < 0 ? '#fca5a5' : (subtotal.d_mtd > 0 ? '#86efac' : '#ffffff')}">${formatCurrency(subtotal.d_mtd)}</td>
                    <td style="color: ${subtotal.d_dtd < 0 ? '#fca5a5' : (subtotal.d_dtd > 0 ? '#86efac' : '#ffffff')}">${formatCurrency(subtotal.d_dtd)}</td>
                    <td>${formatCurrency(subtotal.rka_m1)}</td>
                    <td>${formatCurrency(subtotal.rka_current)}</td>
                    <td>${formatCurrency(subtotal.penc_m1_rp)}</td>
                    <td>${subtotal_pct_m1_badge}</td>
                    <td>${formatCurrency(subtotal.penc_cur_rp)}</td>
                    <td>${subtotal_pct_cur_badge}</td>
                </tr>
            `;

            // 2. Individual Category Rows
            groupRows.forEach((row, i) => {
                const penc_m1_pct = parseFloat(row.penc_m1_pct || 0);
                const penc_cur_pct = parseFloat(row.penc_cur_pct || 0);
                const pct_m1_badge = formatPctBadge(penc_m1_pct);
                const pct_cur_badge = formatPctBadge(penc_cur_pct);

                html += `
                    <tr>
                        <td class="text-start-important text-muted" style="font-size: 0.75rem;">${row.category || ''}</td>
                        <td>${formatCurrency(row.ytd)}</td>
                        <td>${formatCurrency(row.m2)}</td>
                        <td>${formatCurrency(row.mtm)}</td>
                        <td style="background: #f0f7ff; color: #003d7c; font-weight: 800;">${formatCurrency(row.selected)}</td>
                        <td class="${row.delta_ytd < 0 ? 'achieve-negative' : (row.delta_ytd > 0 ? 'achieve-positive' : '')}">${formatCurrency(row.delta_ytd)}</td>
                        <td class="${row.delta_mtd < 0 ? 'achieve-negative' : (row.delta_mtd > 0 ? 'achieve-positive' : '')}">${formatCurrency(row.delta_mtd)}</td>
                        <td class="${row.delta_dtd < 0 ? 'achieve-negative' : (row.delta_dtd > 0 ? 'achieve-positive' : '')}">${formatCurrency(row.delta_dtd)}</td>
                        <td>${formatCurrency(row.rka_m1)}</td>
                        <td>${formatCurrency(row.rka_current)}</td>
                        <td>${formatCurrency(row.penc_m1_rp)}</td>
                        <td>${pct_m1_badge}</td>
                        <td>${formatCurrency(row.penc_cur_rp)}</td>
                        <td>${pct_cur_badge}</td>
                    </tr>`;
            });
        });

        if (totalRow) {
            const total_penc_m1_pct = parseFloat(totalRow.penc_m1_pct || 0);
            const total_penc_cur_pct = parseFloat(totalRow.penc_cur_pct || 0);
            const total_pct_m1_badge = formatPctBadge(total_penc_m1_pct);
            const total_pct_cur_badge = formatPctBadge(total_penc_cur_pct);

            html += `
                <tr style="background: #1e293b; color: #ffffff; font-weight: 900;">
                    <td colspan="3" class="text-center" style="letter-spacing: 0.1em; color: #ffffff; border-right: 1px solid rgba(255,255,255,0.2);">GRAND TOTAL</td>
                    <td style="color: #ffffff;">${formatCurrency(totalRow.ytd)}</td>
                    <td style="color: #ffffff;">${formatCurrency(totalRow.m2)}</td>
                    <td style="color: #ffffff;">${formatCurrency(totalRow.mtm)}</td>
                    <td style="background: #0f172a; color: #ffffff;">${formatCurrency(totalRow.selected)}</td>
                    <td class="${totalRow.delta_ytd < 0 ? 'text-danger' : (totalRow.delta_ytd > 0 ? 'text-success' : '')}">${formatCurrency(totalRow.delta_ytd)}</td>
                    <td class="${totalRow.delta_mtd < 0 ? 'text-danger' : (totalRow.delta_mtd > 0 ? 'text-success' : '')}">${formatCurrency(totalRow.delta_mtd)}</td>
                    <td class="${totalRow.delta_dtd < 0 ? 'text-danger' : (totalRow.delta_dtd > 0 ? 'text-success' : '')}">${formatCurrency(totalRow.delta_dtd)}</td>
                    <td style="color: #ffffff;">${formatCurrency(totalRow.rka_m1)}</td>
                    <td style="color: #ffffff;">${formatCurrency(totalRow.rka_current)}</td>
                    <td style="color: #ffffff;">${formatCurrency(totalRow.penc_m1_rp)}</td>
                    <td>${total_pct_m1_badge}</td>
                    <td style="color: #ffffff;">${formatCurrency(totalRow.penc_cur_rp)}</td>
                    <td>${total_pct_cur_badge}</td>
                </tr>
            `;
        }

        html += `
                </tbody>
            </table>
        `;

        return '<div class="loan-table-container">' + html + '</div>';
    }

    function showSpinners(kategori) {
        const stub = (label) => `
            <div class="text-center text-muted py-5">
                <div class="spinner-border spinner-border-sm text-primary mb-3" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p><strong>Memproses data ${label}</strong></p>
                <p style="font-size: 0.85rem;">Untuk Segmen ${kategori}...</p>
            </div>`;
        osTableContainer.innerHTML = stub('Outstanding');
        smlTableContainer.innerHTML = stub('SML');
        nplTableContainer.innerHTML = stub('NPL');
    }

    function showErrorMessage(message) {
        const errorHtml = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Gagal memuat data</strong><br>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        osTableContainer.innerHTML = errorHtml;
        smlTableContainer.innerHTML = errorHtml;
        nplTableContainer.innerHTML = errorHtml;
    }

    function loadDashboardData() {
        const periode = $periodeSel.val();
        const kategori = $kategoriSel.val();

        if (!periode) return;

        showSpinners(kategori);
        dashboardMeta.textContent = `Memuat data dashboard untuk periode ${formatDate(periode)}...`;
        btnLoadData.disabled = true;

        // Cancel previous request if still pending
        if (requestAbortController) {
            requestAbortController.abort();
        }
        requestAbortController = new AbortController();

        const controller = requestAbortController;
        const timeoutId = setTimeout(() => {
            if (controller === requestAbortController) {
                controller.abort();
            }
        }, REQUEST_TIMEOUT);

        fetch('{{ route("report.dashboard-pinjaman.kredit.data") }}?' + new URLSearchParams({
            periode: periode,
            kategori: kategori
        }), {
            signal: controller.signal
        })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                dashboardMeta.textContent = `Menampilkan dashboard kredit ${kategori} per ${formatDate(periode)}.`;

                document.getElementById('osTitle').innerText = `A. OUTSTANDING (OS) - ${kategori}`;
                osTableContainer.innerHTML = buildTable(data.os, data.header_dates, 'Outstanding', kategori, data.rka_labels);

                document.getElementById('smlTitle').innerText = `B. SPECIAL MENTION LOAN (SML) - ${kategori}`;
                smlTableContainer.innerHTML = buildTable(data.sml, data.header_dates, 'SML', kategori, data.rka_labels);

                document.getElementById('nplTitle').innerText = `C. NON-PERFORMING LOAN (NPL) - ${kategori}`;
                nplTableContainer.innerHTML = buildTable(data.npl, data.header_dates, 'NPL', kategori, data.rka_labels);
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Error:', error);
                
                let errorMsg = 'Periksa koneksi atau snapshot data.';
                if (error.name === 'AbortError') {
                    errorMsg = 'Permintaan timeout setelah ' + (REQUEST_TIMEOUT / 1000) + ' detik. Coba lagi atau pilih periode lain.';
                } else if (error.message.includes('HTTP')) {
                    errorMsg = error.message;
                }
                
                showErrorMessage(errorMsg);
            })
            .finally(() => {
                btnLoadData.disabled = false;
            });
    }

    btnLoadData.addEventListener('click', loadDashboardData);

    // Auto-load on page load if periode is set
    window.setTimeout(() => {
        if ($periodeSel.val()) {
            loadDashboardData();
        }
    }, 100);
});
</script>
@endpush

@endsection
