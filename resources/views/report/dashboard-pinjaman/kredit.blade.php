@extends('layouts.admin')

@section('title', 'Dashboard Pinjaman Kredit SME')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<div class="loan-dashboard pt-4 px-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="loan-page-title">Dashboard Pinjaman Kredit</h1>
            <p class="text-muted mb-0">Analisis performa portofolio berdasarkan segmen dan kategori.</p>
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

    function buildTable(data, headerDates, typeLabel, segmentName) {
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
                        <th rowspan="2" style="width: 130px;">KATEGORI ${segmentName}</th>
                        <th colspan="4" class="sub-head">${typePrefix} PERIODE</th>
                        <th colspan="3" class="accent-head">DELTA (Δ) PERIODE</th>
                    </tr>
                    <tr>
                        <th class="sub-head" style="width: 85px;">${dates.ytd}<br><small>(YtD)</small></th>
                        <th class="sub-head" style="width: 85px;">${dates.m2}<br><small>(M-2)</small></th>
                        <th class="sub-head" style="width: 85px;">${dates.mtm}<br><small>(MtM)</small></th>
                        <th class="sub-head" style="background: #004280; width: 90px;">${dates.selected}<br><small>(HARI INI)</small></th>
                        <th class="accent-head" style="width: 80px;">YtD</th>
                        <th class="accent-head" style="width: 80px;">MtD</th>
                        <th class="accent-head" style="width: 80px;">DtD</th>
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
            const subtotal = { ytd: 0, m2: 0, mtm: 0, selected: 0, d_ytd: 0, d_mtd: 0, d_dtd: 0 };

            groupRows.forEach((row, i) => {
                // Sum metrics
                subtotal.ytd += parseFloat(row.ytd || 0);
                subtotal.m2 += parseFloat(row.m2 || 0);
                subtotal.mtm += parseFloat(row.mtm || 0);
                subtotal.selected += parseFloat(row.selected || 0);
                subtotal.d_ytd += parseFloat(row.delta_ytd || 0);
                subtotal.d_mtd += parseFloat(row.delta_mtd || 0);
                subtotal.d_dtd += parseFloat(row.delta_dtd || 0);

                html += `<tr>`;
                
                // Index No cell (Merged across categories and subtotal)
                if (i === 0) {
                    html += `<td rowspan="${groupSize + 1}" class="text-center-v" style="background: #f8fbff; font-weight: 700;">${rowIndex++}</td>`;
                    html += `<td rowspan="${groupSize + 1}" class="text-center-v text-start-important merged-branch-cell">${branchName}</td>`;
                }

                html += `
                    <td class="text-start-important text-muted" style="font-size: 0.75rem;">${row.category || ''}</td>
                    <td>${formatCurrency(row.ytd)}</td>
                    <td>${formatCurrency(row.m2)}</td>
                    <td>${formatCurrency(row.mtm)}</td>
                    <td style="background: #f0f7ff; color: #003d7c; font-weight: 800;">${formatCurrency(row.selected)}</td>
                    <td class="${row.delta_ytd < 0 ? 'achieve-negative' : (row.delta_ytd > 0 ? 'achieve-positive' : '')}">${formatCurrency(row.delta_ytd)}</td>
                    <td class="${row.delta_mtd < 0 ? 'achieve-negative' : (row.delta_mtd > 0 ? 'achieve-positive' : '')}">${formatCurrency(row.delta_mtd)}</td>
                    <td class="${row.delta_dtd < 0 ? 'achieve-negative' : (row.delta_dtd > 0 ? 'achieve-positive' : '')}">${formatCurrency(row.delta_dtd)}</td>
                </tr>`;
            });

            // Branch Subtotal Row
            html += `
                <tr class="loan-branch-subtotal">
                    <td class="text-center-v" style="font-size: 0.8rem; letter-spacing: 0.05em;">TOTAL ${branchName.toUpperCase()}</td>
                    <td>${formatCurrency(subtotal.ytd)}</td>
                    <td>${formatCurrency(subtotal.m2)}</td>
                    <td>${formatCurrency(subtotal.mtm)}</td>
                    <td style="background: #e0f2fe;">${formatCurrency(subtotal.selected)}</td>
                    <td class="${subtotal.d_ytd < 0 ? 'text-danger' : (subtotal.d_ytd > 0 ? 'text-success' : '')}">${formatCurrency(subtotal.d_ytd)}</td>
                    <td class="${subtotal.d_mtd < 0 ? 'text-danger' : (subtotal.d_mtd > 0 ? 'text-success' : '')}">${formatCurrency(subtotal.d_mtd)}</td>
                    <td class="${subtotal.d_dtd < 0 ? 'text-danger' : (subtotal.d_dtd > 0 ? 'text-success' : '')}">${formatCurrency(subtotal.d_dtd)}</td>
                </tr>
            `;
        });

        if (totalRow) {
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
                <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                <p>Memproses data ${label} untuk Segmen ${kategori}...</p>
            </div>`;
        osTableContainer.innerHTML = stub('Outstanding');
        smlTableContainer.innerHTML = stub('SML');
        nplTableContainer.innerHTML = stub('NPL');
    }

    function loadDashboardData() {
        const periode = $periodeSel.val();
        const kategori = $kategoriSel.val();

        if (!periode) return;

        showSpinners(kategori);
        dashboardMeta.textContent = `Memuat data dashboard untuk periode ${formatDate(periode)}...`;

        fetch('{{ route("report.dashboard-pinjaman.kredit.data") }}?' + new URLSearchParams({
            periode: periode,
            kategori: kategori
        }))
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                dashboardMeta.textContent = `Menampilkan dashboard kredit ${kategori} per ${formatDate(periode)}.`;
                
                document.getElementById('osTitle').innerText = `A. OUTSTANDING (OS) - ${kategori}`;
                osTableContainer.innerHTML = buildTable(data.os, data.header_dates, 'Outstanding', kategori);
                
                document.getElementById('smlTitle').innerText = `B. SPECIAL MENTION LOAN (SML) - ${kategori}`;
                smlTableContainer.innerHTML = buildTable(data.sml, data.header_dates, 'SML', kategori);
                
                document.getElementById('nplTitle').innerText = `C. NON-PERFORMING LOAN (NPL) - ${kategori}`;
                nplTableContainer.innerHTML = buildTable(data.npl, data.header_dates, 'NPL', kategori);
            })
            .catch(error => {
                console.error('Error:', error);
                const errorMsg = '<div class="alert alert-danger">Gagal memuat data. Periksa koneksi atau snapshot data.</div>';
                osTableContainer.innerHTML = errorMsg;
                smlTableContainer.innerHTML = errorMsg;
                nplTableContainer.innerHTML = errorMsg;
            });
    }

    btnLoadData.addEventListener('click', loadDashboardData);

    window.setTimeout(() => {
        if ($periodeSel.val()) {
            loadDashboardData();
        }
    }, 100);
});
</script>
@endpush

@endsection
