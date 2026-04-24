@extends('layouts.admin')

@section('title', 'Kinerja Mantri')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .loan-mantri-hero {
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

    .loan-mantri-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(120deg, rgba(255, 255, 255, 0.12), transparent 35%),
            repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 18px);
        opacity: 0.72;
    }

    .loan-mantri-title-wrap {
        width: min(100%, 920px);
        text-align: center;
        padding: 0.05rem 1rem;
        margin: 0 auto;
    }

    .loan-mantri-title-badge {
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

    .loan-mantri-title-badge i {
        color: #ffb15c;
    }

    .loan-mantri-title {
        margin: 0;
        font-size: clamp(1.18rem, 2.05vw, 2rem);
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 0.035em;
        line-height: 1.08;
        text-transform: uppercase;
        text-shadow: 0 10px 26px rgba(0, 18, 50, 0.28);
    }

    .loan-mantri-title::after {
        content: '';
        display: block;
        width: min(150px, 42vw);
        height: 3px;
        margin: 0.7rem auto 0;
        border-radius: 999px;
        background: linear-gradient(90deg, #ff671f, #f9b233, rgba(255, 255, 255, 0.9));
        box-shadow: 0 8px 18px rgba(255, 103, 31, 0.28);
    }

    .loan-mantri-desc {
        margin: 0.65rem auto 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.8rem;
        line-height: 1.6;
        max-width: 800px;
    }

    .mantri-note {
        font-size: 0.8rem;
        color: var(--loan-muted);
    }

    .mantri-table-wrap {
        overflow: auto;
        border: 1px solid rgba(8, 87, 195, 0.14);
        border-radius: 14px;
        background: #f8fbff;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }

    .mantri-table {
        width: 100%;
        min-width: 2200px;
        border-collapse: collapse;
        font-size: 0.81rem;
    }

    .mantri-table thead {
        background: #2f74b6;
    }

    .mantri-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #2f74b6 !important;
        color: #ffffff !important;
        text-align: center;
        font-weight: 800;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-right: 1px solid rgba(255, 255, 255, 0.14);
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        padding: 0.58rem 0.6rem !important;
        white-space: nowrap;
        vertical-align: middle !important;
    }

    .mantri-table thead .group-head {
        background: #2f74b6 !important;
        font-size: 0.84rem;
        line-height: 1.3;
        white-space: normal;
    }

    .mantri-table thead .sub-head {
        background: #4c79c4 !important;
    }

    .mantri-table td {
        padding: 0.62rem 0.7rem;
        border-right: 1px solid rgba(8, 87, 195, 0.09);
        border-bottom: 1px solid rgba(8, 87, 195, 0.09);
        background: #ffffff;
        color: #1f2937;
        white-space: nowrap;
        vertical-align: middle;
    }

    .mantri-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .mantri-table tbody tr:hover td {
        background: #eef6ff;
    }

    .mantri-table .text-center {
        text-align: center !important;
    }

    .mantri-table .text-right {
        text-align: right !important;
    }

    .mantri-table .text-strong {
        font-weight: 800;
        color: #0f172a;
    }

    .mantri-table .badge-cell {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 26px;
        padding: 0.22rem 0.6rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        line-height: 1;
        border: 1px solid rgba(15, 23, 42, 0.08);
    }

    .mantri-table .badge-cell--blue {
        background: linear-gradient(180deg, #eaf3ff 0%, #dbeafe 100%);
        color: #1d4ed8;
    }

    .mantri-table .badge-cell--green {
        background: linear-gradient(180deg, #e8f7ee 0%, #d2f0dd 100%);
        color: #166534;
    }

    .mantri-table .badge-cell--amber {
        background: linear-gradient(180deg, #fff4d6 0%, #ffe7b8 100%);
        color: #9a3412;
    }

    .mantri-table .badge-cell--slate {
        background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
        color: #334155;
    }

    .mantri-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--loan-muted);
        background: var(--loan-surface-soft);
    }

    .mantri-empty strong {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--loan-blue-ink);
    }
</style>

<div class="loan-dashboard pt-4 px-3">
    <div class="loan-mantri-hero animate-reveal">
        <div class="loan-mantri-title-wrap">
            <div class="loan-mantri-title-badge">
                <i class="fas fa-briefcase"></i>
                <span>BRI Field Performance</span>
            </div>
            <h1 class="loan-mantri-title">Kinerja Mantri</h1>
            <p class="loan-mantri-desc">
                Monitoring performa Mantri berdasarkan <strong>KANTOR CABANG</strong> dan <strong>NAMA UKER</strong>.
                Selector membaca langsung dari tabel <strong>performance_mantri</strong> dan tabel akan menyesuaikan filter yang dipilih.
            </p>
        </div>
    </div>

    <div class="card loan-shell mb-4 animate-reveal">
        <div class="card-body p-4">
            <form id="mantriForm" method="GET" action="{{ route('report.dashboard-pinjaman.mantri') }}">
                <div class="row loan-filter-grid">
                    <div class="col-xl-3 col-lg-6 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label" for="mantriCabangSelect">KANTOR CABANG</label>
                            <select
                                id="mantriCabangSelect"
                                name="cabang[]"
                                class="form-control select2 loan-filter-control loan-filter-multiselect"
                                multiple
                                data-placeholder="Semua Kantor Cabang"
                                data-selected='@json($selectedCabang ?? [])'
                            >
                                @foreach ($cabangOptions as $option)
                                    <option value="{{ $option }}" @selected(in_array($option, $selectedCabang ?? [], true))>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-6 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label" for="mantriPeriodeInput">TANGGAL</label>
                            <input
                                id="mantriPeriodeInput"
                                type="date"
                                name="periode"
                                class="form-control loan-filter-control"
                                value="{{ $requestedPeriod ?: $selectedPeriod }}"
                                max="{{ $selectedPeriod }}"
                            >
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-12">
                        <div class="form-group">
                            <label class="loan-filter-label" for="mantriUnitSelect">NAMA UKER</label>
                            <select
                                id="mantriUnitSelect"
                                name="unit[]"
                                class="form-control select2 loan-filter-control loan-filter-multiselect"
                                multiple
                                data-placeholder="Semua Nama Uker"
                                data-selected='@json($selectedUnit ?? [])'
                            >
                                @foreach ($unitOptions as $option)
                                    <option value="{{ $option }}" @selected(in_array($option, $selectedUnit ?? [], true))>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-12 col-md-12">
                        <div class="form-group d-flex align-items-end h-100">
                            <div>
                                <div class="mantri-note mb-2">
                                    Total baris hasil filter: <strong>{{ number_format($rowCount ?? 0, 0, ',', '.') }}</strong>
                                </div>
                                <button type="submit" class="btn btn-primary font-weight-bold" style="border-radius: 12px; min-height: 44px; padding: 0 1.4rem;">
                                    <i class="fas fa-filter mr-2"></i>Tampilkan Data
                                </button>
                                <a href="{{ route('report.dashboard-pinjaman.mantri') }}" class="btn btn-light font-weight-bold ml-2" style="border-radius: 12px; min-height: 44px; padding: 0 1.4rem;">
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
                    <h5 class="mb-1 font-weight-bold text-dark">Tabel Kinerja Mantri</h5>
                    <div class="mantri-note">Kolom ditampilkan sesuai struktur tabel <strong>performance_mantri</strong>.</div>
                </div>
                <div class="loan-table-badge">
                    <i class="fas fa-table"></i>
                    <span>{{ number_format($rowCount ?? 0, 0, ',', '.') }} baris</span>
                </div>
            </div>

            <div class="mantri-table-wrap">
                <table class="mantri-table">
                    <thead>
                        <tr>
                            @php
                                try {
                                    $disbursementLabel = $selectedPeriod
                                        ? 'Disbursement sd ' . \Carbon\Carbon::parse($selectedPeriod)->locale('id')->translatedFormat('d F Y')
                                        : 'Disbursement sd -';
                                } catch (\Throwable $e) {
                                    $disbursementLabel = 'Disbursement sd ' . ($selectedPeriod ?: '-');
                                }
                            @endphp
                            <th rowspan="2">NO</th>
                            <th rowspan="2">PN</th>
                            <th rowspan="2">NAMA MANTRI</th>
                            <th rowspan="2">BC</th>
                            <th rowspan="2">UNIT</th>
                            <th rowspan="2">CABANG</th>
                            <th rowspan="2">KET</th>
                            <th rowspan="2">TMT JABATAN</th>
                            <th rowspan="2">KET KEHADIRAN MANTRI</th>
                            <th rowspan="2">TANGGAL MULAI BL</th>
                            <th colspan="2" class="group-head">{{ $disbursementLabel }}</th>
                            <th rowspan="2">KET</th>
                            <th rowspan="2">KATEGORI REALISASI</th>
                            <th rowspan="2">TIKET SIZE</th>
                            <th rowspan="2">RATAS/HK</th>
                            <th rowspan="2">KETERANGAN</th>
                        </tr>
                        <tr>
                            <th class="sub-head">DEB</th>
                            <th class="sub-head">RP.JUTA</th>
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

                                $formatDate = function ($value) {
                                    if (!$value) {
                                        return '-';
                                    }

                                    try {
                                        return \Carbon\Carbon::parse($value)->format('d/m/Y');
                                    } catch (\Throwable $e) {
                                        return (string) $value;
                                    }
                                };
                            @endphp
                            <tr>
                                <td class="text-center text-strong">{{ $index + 1 }}</td>
                                <td class="text-center text-strong">{{ $row['pn'] ?? '-' }}</td>
                                <td class="text-strong">{{ $row['nama'] ?? '-' }}</td>
                                <td class="text-center">{{ $row['bc'] ?? '-' }}</td>
                                <td>{{ $row['unit'] ?? '-' }}</td>
                                <td class="text-strong">{{ $row['cabang'] ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge-cell badge-cell--blue">{{ $row['ket'] ?? '-' }}</span>
                                </td>
                                <td class="text-center">{{ $formatDate($row['tmt_jabatan'] ?? null) }}</td>
                                <td>{{ $row['ket_kehadiran_mantri'] ?? '-' }}</td>
                                <td class="text-center">{{ $formatDate($row['tanggal_mulai_bl'] ?? null) }}</td>
                                <td class="text-right text-strong">{{ $formatAmount($row['disbursement_deb'] ?? null) }}</td>
                                <td class="text-right text-strong">{{ $formatAmount($row['disbursement_rp_juta'] ?? null) }}</td>
                                <td class="text-center">
                                    <span class="badge-cell badge-cell--green">{{ $row['ket_realisasi'] ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="badge-cell badge-cell--amber">{{ $row['kategori_realisasi'] ?? '-' }}</span>
                                </td>
                                <td class="text-center">{{ $row['tiket_size'] ?? '-' }}</td>
                                <td class="text-center">{{ $row['ratas_hk'] ?? '-' }}</td>
                                <td>{{ $row['keterangan'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="17" class="mantri-empty">
                                    <strong>Data tidak ditemukan</strong>
                                    Saat ini belum ada data di tabel <strong>performance_mantri</strong> atau filter yang dipilih belum menghasilkan baris.
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
        const form = document.getElementById('mantriForm');
        const cabangSelect = document.getElementById('mantriCabangSelect');
        const periodeInput = document.getElementById('mantriPeriodeInput');
        const unitSelect = document.getElementById('mantriUnitSelect');
        const filtersUrl = @json(route('report.dashboard-pinjaman.mantri.filters'));
        let unitFilterController = null;

        if (!form || !cabangSelect || !periodeInput || !unitSelect) {
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

        async function reloadUnitOptions() {
            if (unitFilterController) {
                unitFilterController.abort();
            }

            const selectedCabang = getSelectedValues(cabangSelect);
            const currentSelectedUnit = getSelectedValues(unitSelect);

            unitFilterController = new AbortController();

            try {
                const params = new URLSearchParams();
                if (periodeInput.value) {
                    params.set('periode', periodeInput.value);
                }
                selectedCabang.forEach((value) => params.append('cabang[]', value));

                const response = await fetch(`${filtersUrl}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: unitFilterController.signal,
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat daftar unit.');
                }

                const payload = await response.json();
                const options = Array.isArray(payload.unit_options) ? payload.unit_options : [];
                const preservedSelected = currentSelectedUnit.filter((value) => options.map(String).includes(String(value)));

                setSelectOptions(unitSelect, options, preservedSelected);
            } catch (error) {
                if (error.name !== 'AbortError' && window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    window.jQuery(unitSelect).trigger('change.select2');
                }
            } finally {
                unitFilterController = null;
            }
        }

        initMultiSelect(cabangSelect, cabangSelect.dataset.placeholder || 'Semua Kantor Cabang');
        initMultiSelect(unitSelect, unitSelect.dataset.placeholder || 'Semua Nama Uker');

        if (window.jQuery) {
            window.jQuery(cabangSelect).on('change', function () {
                syncSelectedDataset(cabangSelect);
                reloadUnitOptions();
            });

            window.jQuery(unitSelect).on('change', function () {
                syncSelectedDataset(unitSelect);
            });
        }

        periodeInput.addEventListener('change', function () {
            reloadUnitOptions();
        });

        reloadUnitOptions();
    });
</script>
@endpush
