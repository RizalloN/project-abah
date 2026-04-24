@extends('layouts.admin')

@section('title', 'Kinerja per RM Kur Mikro')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .loan-kurmikro-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        margin-bottom: 1rem;
        padding: 1.35rem 1.2rem;
        border-radius: 0 0 1.4rem 1.4rem;
        background:
            radial-gradient(circle at 12% 18%, rgba(255, 103, 31, 0.16), transparent 26%),
            radial-gradient(circle at 88% 10%, rgba(59, 130, 246, 0.22), transparent 28%),
            linear-gradient(135deg, #003b75 0%, #00529c 48%, #0f4c97 100%);
        color: #ffffff;
        box-shadow: 0 18px 40px -30px rgba(0, 55, 116, 0.55);
    }

    .loan-kurmikro-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(120deg, rgba(255, 255, 255, 0.12), transparent 35%),
            repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 18px);
        opacity: 0.72;
    }

    .loan-kurmikro-title-wrap {
        width: min(100%, 900px);
        text-align: center;
        padding: 0.05rem 1rem;
        margin: 0 auto;
    }

    .loan-kurmikro-title-badge {
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

    .loan-kurmikro-title-badge i {
        color: #ffb15c;
    }

    .loan-kurmikro-title {
        margin: 0;
        font-size: clamp(1.18rem, 2.05vw, 2rem);
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 0.035em;
        line-height: 1.08;
        text-transform: uppercase;
        text-shadow: 0 10px 26px rgba(0, 18, 50, 0.28);
    }

    .loan-kurmikro-title::after {
        content: '';
        display: block;
        width: min(150px, 42vw);
        height: 3px;
        margin: 0.7rem auto 0;
        border-radius: 999px;
        background: linear-gradient(90deg, #ff671f, #f9b233, rgba(255, 255, 255, 0.9));
        box-shadow: 0 8px 18px rgba(255, 103, 31, 0.28);
    }

    .loan-kurmikro-desc {
        margin: 0.65rem auto 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.8rem;
        line-height: 1.6;
        max-width: 780px;
    }

    .kurmikro-table-wrap {
        overflow: auto;
        border: 1px solid rgba(8, 87, 195, 0.14);
        border-radius: 14px;
        background: #f8fbff;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }

    .kurmikro-table {
        width: 100%;
        min-width: 1720px;
        border-collapse: collapse;
        border-spacing: 0;
        font-size: 0.82rem;
    }

    .kurmikro-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #2f74b6 !important;
        color: #ffffff !important;
        text-align: center;
        font-weight: 800;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-right: 1px solid rgba(255, 255, 255, 0.14);
        border-bottom: 1px solid #2f74b6;
        vertical-align: middle !important;
        padding: 0.35rem 0.55rem !important;
        line-height: 1;
    }

    .kurmikro-table thead {
        background: #2f74b6;
    }

    .kurmikro-table thead tr:first-child th {
        height: 32px;
        padding-top: 0.2rem !important;
        padding-bottom: 0.2rem !important;
        border-bottom-color: #2f74b6 !important;
    }

    .kurmikro-table thead tr:nth-child(2) th {
        top: 32px;
        height: 30px;
        background: #2f74b6 !important;
        font-size: 0.67rem;
        padding-top: 0.2rem !important;
        padding-bottom: 0.2rem !important;
        border-top: 1px solid #2f74b6 !important;
    }

    .kurmikro-table th,
    .kurmikro-table td {
        padding: 0.6rem 0.7rem;
        border-right: 1px solid rgba(8, 87, 195, 0.09);
        border-bottom: 1px solid rgba(8, 87, 195, 0.09);
        white-space: nowrap;
        vertical-align: middle;
    }

    .kurmikro-table tbody td {
        background: #ffffff;
        color: #1f2937;
        line-height: 1.2;
    }

    .kurmikro-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .kurmikro-table tbody tr:hover td {
        background: #eef6ff;
    }

    .kurmikro-table .text-center {
        text-align: center !important;
    }

    .kurmikro-table .text-right {
        text-align: right !important;
    }

    .kurmikro-table .sticky-head {
        z-index: 12 !important;
    }

    .kurmikro-table .group-head {
        background: #245d9f !important;
    }

    .kurmikro-table .sub-head {
        font-size: 0.66rem;
        line-height: 1;
    }

    .kurmikro-table .metric-positive {
        font-weight: 800;
    }

    .kurmikro-table .metric-warning {
        font-weight: 800;
    }

    .kurmikro-table .metric-total {
        font-weight: 800;
    }

    .kurmikro-table .metric-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 72px;
        padding: 0.28rem 0.6rem;
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.74rem;
        line-height: 1;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }

    .kurmikro-table .metric-pill--good {
        background: linear-gradient(180deg, #e8f7ee 0%, #d2f0dd 100%);
        color: #166534;
        border: 1px solid #b7e2c2;
    }

    .kurmikro-table .metric-pill--muted {
        background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    .kurmikro-table .metric-pill--pct-dark {
        background: linear-gradient(180deg, #0f766e 0%, #115e59 100%);
        color: #ffffff;
        border: 1px solid #0f766e;
    }

    .kurmikro-table .metric-pill--pct-light {
        background: linear-gradient(180deg, #dcfce7 0%, #bbf7d0 100%);
        color: #166534;
        border: 1px solid #86efac;
    }

    .kurmikro-table .metric-pill--pct-yellow {
        background: linear-gradient(180deg, #fef9c3 0%, #fde68a 100%);
        color: #854d0e;
        border: 1px solid #facc15;
    }

    .kurmikro-table .metric-pill--pct-orange {
        background: linear-gradient(180deg, #ffedd5 0%, #fed7aa 100%);
        color: #9a3412;
        border: 1px solid #fdba74;
    }

    .kurmikro-table .metric-pill--pct-red {
        background: linear-gradient(180deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border: 1px solid #f87171;
    }

    .kurmikro-table .metric-pill--warn {
        background: linear-gradient(180deg, #fff0da 0%, #ffe0b3 100%);
        color: #9a3412;
        border: 1px solid #f9c98b;
    }

    .kurmikro-table .metric-pill--total {
        background: linear-gradient(180deg, #fff4c7 0%, #ffe07f 100%);
        color: #7c2d12;
        border: 1px solid #f4c94d;
        min-width: 84px;
    }

    .kurmikro-table .text-strong {
        font-weight: 800;
        color: #0f172a;
    }

    .kurmikro-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--loan-muted);
        background: var(--loan-surface-soft);
    }

    .kurmikro-empty strong {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--loan-blue-ink);
    }

    .kurmikro-note {
        font-size: 0.8rem;
        color: var(--loan-muted);
    }
</style>

<div class="loan-dashboard pt-4 px-3">
    <div class="loan-kurmikro-hero animate-reveal">
        <div class="loan-kurmikro-title-wrap">
            <div class="loan-kurmikro-title-badge">
                <i class="fas fa-chart-line"></i>
                <span>BRI Loan Intelligence</span>
            </div>
            <h1 class="loan-kurmikro-title">Kinerja per RM Kur Mikro</h1>
            <p class="loan-kurmikro-desc">
                Report ini menampilkan data RM Kur Mikro berdasarkan <strong>KANTOR CABANG</strong> dan <strong>NAMA UKER</strong>.
                Pilih filter lalu tekan <strong>Tampilkan Data</strong> untuk menyegarkan selector dan tabel.
            </p>
        </div>
    </div>

    <div class="card loan-shell mb-4 animate-reveal">
        <div class="card-body p-4">
            <form id="kurMikroForm" method="GET" action="{{ route('report.dashboard-pinjaman.kurmikro') }}">
                <div class="row loan-filter-grid">
                    <div class="col-xl-4 col-lg-6 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label" for="kurMikroKancaSelect">KANTOR CABANG</label>
                            <select
                                id="kurMikroKancaSelect"
                                name="kanca[]"
                                class="form-control select2 loan-filter-control loan-filter-multiselect"
                                multiple
                                data-placeholder="Semua Kantor Cabang"
                                data-selected='@json($selectedKanca ?? [])'
                            >
                                @foreach ($kancaOptions as $option)
                                    <option value="{{ $option }}" @selected(in_array($option, $selectedKanca ?? [], true))>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-6 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label" for="kurMikroUkerSelect">NAMA UKER</label>
                            <select
                                id="kurMikroUkerSelect"
                                name="uker[]"
                                class="form-control select2 loan-filter-control loan-filter-multiselect"
                                multiple
                                data-placeholder="Semua Nama UKER"
                                data-selected='@json($selectedUker ?? [])'
                            >
                                @foreach ($ukerOptions as $option)
                                    <option value="{{ $option }}" @selected(in_array($option, $selectedUker ?? [], true))>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-12 col-md-12">
                        <div class="form-group d-flex align-items-end h-100">
                            <div>
                                <div class="loan-kurmikro-note mb-2">
                                    Total baris hasil filter: <strong>{{ number_format($rowCount ?? 0, 0, ',', '.') }}</strong>
                                </div>
                                <button type="submit" class="btn btn-primary font-weight-bold" style="border-radius: 12px; min-height: 44px; padding: 0 1.4rem;">
                                    <i class="fas fa-filter mr-2"></i>Tampilkan Data
                                </button>
                                <a href="{{ route('report.dashboard-pinjaman.kurmikro') }}" class="btn btn-light font-weight-bold ml-2" style="border-radius: 12px; min-height: 44px; padding: 0 1.4rem;">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card loan-table-shell animate-reveal">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark">Tabel Kinerja RM Kur Mikro</h5>
                    <div class="kurmikro-note">Data diambil dari tabel <strong>performance_kurkecil_mikro</strong> dan mengikuti filter yang dipilih.</div>
                </div>
                <div class="loan-table-badge">
                    <i class="fas fa-table"></i>
                    <span>{{ number_format($rowCount ?? 0, 0, ',', '.') }} baris</span>
                </div>
            </div>

            <div class="kurmikro-table-wrap">
                <table class="kurmikro-table">
                    <colgroup>
                        <col style="width: 70px;">
                        <col style="width: 210px;">
                        <col style="width: 110px;">
                        <col style="width: 210px;">
                        <col style="width: 110px;">
                        <col style="width: 230px;">
                        <col style="width: 120px;">
                        <col style="width: 100px;">
                        <col style="width: 110px;">
                        <col style="width: 110px;">
                        <col style="width: 120px;">
                        <col style="width: 110px;">
                        <col style="width: 120px;">
                        <col style="width: 110px;">
                        <col style="width: 110px;">
                        <col style="width: 130px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowspan="2" class="sticky-head">NO</th>
                            <th rowspan="2" class="sticky-head">KANCA</th>
                            <th rowspan="2" class="sticky-head">PN</th>
                            <th rowspan="2" class="sticky-head">NAMA</th>
                            <th rowspan="2" class="sticky-head">BC UKER</th>
                            <th rowspan="2" class="sticky-head">UKER</th>
                            <th rowspan="2" class="sticky-head">TANGGAL BL</th>
                            <th rowspan="2" class="sticky-head">KET</th>
                            <th colspan="3" class="group-head">&le;250 Juta</th>
                            <th colspan="3" class="group-head">&gt;250 Juta</th>
                            <th colspan="2" class="group-head">TOTAL</th>
                        </tr>
                        <tr>
                            <th class="sub-head">Deb</th>
                            <th class="sub-head">%</th>
                            <th class="sub-head">Rp.Juta</th>
                            <th class="sub-head">Deb</th>
                            <th class="sub-head">%</th>
                            <th class="sub-head">Rp.Juta</th>
                            <th class="sub-head">Deb</th>
                            <th class="sub-head">Rp.Juta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $index => $row)
                            @php
                                $formatAmount = function ($value) {
                                    if ($value === null || $value === '') {
                                        return '-';
                                    }

                                    if (!is_numeric($value)) {
                                        return '-';
                                    }

                                    return number_format((float) $value, 0, ',', '.');
                                };
                                $resolvePctClass = function ($value) {
                                    if ($value === null || $value === '' || !is_numeric($value)) {
                                        return 'metric-pill metric-pill--muted';
                                    }

                                    $pct = round((float) $value, 2);

                                    if ($pct == 0.00) {
                                        return 'metric-pill metric-pill--pct-dark';
                                    }

                                    if ($pct > 0.00 && $pct <= 20.00) {
                                        return 'metric-pill metric-pill--pct-light';
                                    }

                                    if ($pct > 20.00 && $pct <= 40.00) {
                                        return 'metric-pill metric-pill--pct-yellow';
                                    }

                                    if ($pct > 40.00 && $pct < 100.00) {
                                        return 'metric-pill metric-pill--pct-orange';
                                    }

                                    return 'metric-pill metric-pill--pct-red';
                                };
                                $ltPct = isset($row['lt_250_juta_pct']) ? (float) $row['lt_250_juta_pct'] : null;
                                $gtPct = isset($row['gt_250_juta_pct']) ? (float) $row['gt_250_juta_pct'] : null;
                                $tanggal = $row['tanggal_bl'] ?? null;
                                try {
                                    $tanggalText = $tanggal ? \Carbon\Carbon::parse($tanggal)->format('d/m/Y') : '-';
                                } catch (Throwable $e) {
                                    $tanggalText = $tanggal ?: '-';
                                }
                            @endphp
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td class="text-strong">{{ $row['kanca'] ?? '-' }}</td>
                                <td class="text-center text-strong">{{ $row['pn'] ?? '-' }}</td>
                                <td class="text-strong">{{ $row['nama'] ?? '-' }}</td>
                                <td class="text-center">{{ $row['bc_uker'] ?? '-' }}</td>
                                <td>{{ $row['uker'] ?? '-' }}</td>
                                <td class="text-center">{{ $tanggalText }}</td>
                                <td class="text-center text-strong">{{ $row['ket'] ?? '-' }}</td>
                                <td class="text-right">{{ $formatAmount($row['lt_250_juta_deb'] ?? null) }}</td>
                                <td class="text-center">
                                    <span class="{{ $resolvePctClass($ltPct) }}">
                                        {{ $ltPct !== null ? number_format($ltPct, 2, ',', '.') . '%' : '-' }}
                                    </span>
                                </td>
                                <td class="text-right">{{ $formatAmount($row['lt_250_juta_rp_juta'] ?? null) }}</td>
                                <td class="text-right">{{ $formatAmount($row['gt_250_juta_deb'] ?? null) }}</td>
                                <td class="text-center">
                                    <span class="{{ $resolvePctClass($gtPct) }}">
                                        {{ $gtPct !== null ? number_format($gtPct, 2, ',', '.') . '%' : '-' }}
                                    </span>
                                </td>
                                <td class="text-right">{{ $formatAmount($row['gt_250_juta_rp_juta'] ?? null) }}</td>
                                <td class="text-right">
                                    <span class="metric-pill metric-pill--total">{{ $formatAmount($row['total_deb'] ?? null) }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="metric-pill metric-pill--total">{{ $formatAmount($row['total_rp_juta'] ?? null) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="kurmikro-empty">
                                    <strong>Data tidak ditemukan</strong>
                                    Coba ubah selector KANTOR CABANG atau NAMA UKER.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('kurMikroForm');
        const kancaSelect = document.getElementById('kurMikroKancaSelect');
        const ukerSelect = document.getElementById('kurMikroUkerSelect');
        const filtersUrl = @json(route('report.dashboard-pinjaman.kurmikro.filters'));
        let ukerFilterController = null;

        if (!form || !kancaSelect || !ukerSelect) {
            return;
        }

        function getSelectedValues(select) {
            if (!window.jQuery) {
                return Array.from(select.selectedOptions || []).map((option) => option.value).filter(Boolean);
            }

            return (window.jQuery(select).val() || []).map(String).filter(Boolean);
        }

        function setSelectOptions(select, options, selectedValues) {
            const normalizedSelected = Array.isArray(selectedValues)
                ? selectedValues.map(String).filter(Boolean)
                : [];

            select.innerHTML = '';
            options.forEach((optionValue) => {
                const option = document.createElement('option');
                option.value = optionValue;
                option.textContent = optionValue;
                option.selected = normalizedSelected.includes(String(optionValue));
                select.appendChild(option);
            });

            select.dataset.selected = JSON.stringify(
                normalizedSelected.filter((value) => options.map(String).includes(value))
            );

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(select).val(JSON.parse(select.dataset.selected || '[]')).trigger('change.select2');
            }
        }

        function refreshSelect2(select) {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(select).trigger('change.select2');
            }
        }

        async function reloadUkerOptions() {
            if (ukerFilterController) {
                ukerFilterController.abort();
            }

            const selectedKanca = getSelectedValues(kancaSelect);
            const currentSelectedUker = getSelectedValues(ukerSelect);

            ukerFilterController = new AbortController();

            try {
                const params = new URLSearchParams();
                selectedKanca.forEach((value) => params.append('kanca[]', value));

                const response = await fetch(`${filtersUrl}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: ukerFilterController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat daftar UKER.');
                }

                const payload = await response.json();
                const options = Array.isArray(payload.uker_options) ? payload.uker_options : [];
                const preservedSelected = currentSelectedUker.filter((value) => options.map(String).includes(String(value)));

                setSelectOptions(ukerSelect, options, preservedSelected);
                refreshSelect2(ukerSelect);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    refreshSelect2(ukerSelect);
                }
            } finally {
                ukerFilterController = null;
            }
        }

        initMultiSelect(kancaSelect, kancaSelect.dataset.placeholder || 'Semua Kantor Cabang');
        initMultiSelect(ukerSelect, ukerSelect.dataset.placeholder || 'Semua Nama UKER');

        if (window.jQuery) {
            window.jQuery(kancaSelect).on('change', function () {
                syncSelectedDataset(kancaSelect);
                reloadUkerOptions();
            });

            window.jQuery(ukerSelect).on('change', function () {
                syncSelectedDataset(ukerSelect);
            });
        }

        reloadUkerOptions();
    });
</script>
@endpush
