@extends('layouts.admin')

@section('title', 'Monitoring Realisasi yang Menunggak')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

@php
    $selectedBranchValue = $selectedBranches[0] ?? 'AREA_6_ALL';
    $selectedUnitValue = $selectedUnits[0] ?? 'ALL_UKER';
@endphp

<style>
    .six-arrears-page {
        --six-blue: #0857c3; /* BRI Nusantara */
        --six-blue-deep: #053b82; /* BRI Ink */
        --six-blue-ink: #042a5f; /* BRI Night */
        --six-blue-soft: #f2f7ff; /* BRI Mist */
        --six-cyan: #71c5e8; /* BRI Mentari */
        --six-text: #0f172a;
        --six-muted: #5b7da7;
        --six-line: #cbd5e1;
        --six-soft: #f8fbff;
        color: var(--six-text);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .six-arrears-hero {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
        padding: 1.5rem 2rem;
        border-radius: 0px;
        background: linear-gradient(135deg, var(--six-blue-ink) 0%, var(--six-blue-deep) 50%, var(--six-blue) 100%);
        box-shadow: 0 10px 25px rgba(4, 42, 95, 0.12);
        color: #ffffff;
        overflow: hidden;
    }

    .six-arrears-hero::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(113, 197, 232, 0.15) 0%, transparent 70%);
        pointer-events: none;
    }

    .six-arrears-title {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 900;
        color: #ffffff;
        letter-spacing: -0.02em;
        text-transform: uppercase;
        line-height: 1.2;
    }

    .six-arrears-subtitle {
        margin: 0.4rem 0 0;
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.85rem;
        font-weight: 500;
        line-height: 1.4;
    }

    .six-arrears-badge {
        flex: 0 0 auto;
        padding: 0.6rem 1rem;
        border: 1px solid rgba(255, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-size: 0.82rem;
        font-weight: 700;
        border-radius: 0px;
        backdrop-filter: blur(4px);
    }

    .six-arrears-panel {
        border: 1px solid var(--six-line);
        background: #ffffff;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        margin-bottom: 1.5rem;
        border-top: 3px solid var(--six-blue);
        border-radius: 0px;
    }

    .six-arrears-panel-body {
        padding: 1.25rem;
    }

    .six-arrears-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(140px, 1fr)) auto;
        gap: 0.85rem;
        align-items: end;
    }

    .six-arrears-field label {
        display: block;
        margin-bottom: 0.45rem;
        color: #475569;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .six-arrears-control {
        width: 100%;
        min-height: 40px;
        border: 1px solid var(--six-line);
        background: #ffffff;
        color: #1e293b;
        padding: 0.45rem 0.75rem;
        font-size: 0.82rem;
        font-weight: 700;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        border-radius: 0px !important;
    }

    .six-arrears-control:focus {
        border-color: var(--six-blue);
        box-shadow: none;
    }

    .six-arrears-control:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .six-arrears-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        justify-content: flex-end;
        white-space: nowrap;
    }

    .six-arrears-btn {
        min-height: 40px;
        border: 1px solid transparent;
        padding: 0 1.15rem;
        font-size: 0.82rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.15s ease;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        border-radius: 0px !important;
        transform: translateY(0);
    }

    .six-arrears-btn:hover {
        transform: translateY(-1px);
    }

    .six-arrears-btn:active {
        transform: translateY(0);
    }

    .six-arrears-btn-primary {
        background: var(--six-blue);
        border-color: var(--six-blue);
        color: #ffffff;
    }

    .six-arrears-btn-primary:hover {
        background: var(--six-blue-deep);
        border-color: var(--six-blue-deep);
        color: #ffffff;
    }

    .six-arrears-btn-light {
        background: #ffffff;
        border-color: var(--six-line);
        color: #475569;
    }

    .six-arrears-btn-light:hover {
        background: #f8fafc;
        color: #1e293b;
        border-color: #94a3b8;
    }

    .six-arrears-btn-success {
        background: #107c41;
        border-color: #107c41;
        color: #ffffff;
    }

    .six-arrears-btn-success:hover {
        background: #0b6333;
        border-color: #0b6333;
        color: #ffffff;
    }

    .six-arrears-btn:disabled {
        opacity: 0.6;
        cursor: wait;
        transform: none !important;
    }

    .six-arrears-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .six-arrears-metric {
        border: 1px solid var(--six-line);
        background: #ffffff;
        padding: 1rem 1.25rem;
        min-height: 90px;
        border-radius: 0px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.01);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .six-arrears-metric:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.04);
    }

    .six-arrears-metric span {
        display: block;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.35rem;
    }

    .six-arrears-metric strong {
        display: block;
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 900;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }

    .six-arrears-table-wrap {
        overflow: auto;
        border: 1px solid var(--six-line);
        background: #ffffff;
        max-height: 68vh;
    }

    .six-arrears-table {
        width: 100%;
        min-width: 1280px;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
    }

    .six-arrears-table th,
    .six-arrears-table td {
        border-right: 1px solid var(--six-line);
        border-bottom: 1px solid var(--six-line);
        padding: 0.55rem 0.75rem;
        font-size: 0.8rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .six-arrears-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--six-blue-ink) !important;
        color: #ffffff !important;
        font-weight: 850;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        text-align: center;
        border-right: 1px solid rgba(255, 255, 255, 0.15) !important;
        border-bottom: 2px solid rgba(255, 255, 255, 0.2) !important;
    }

    .six-arrears-table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }

    .six-arrears-table tbody tr:nth-child(odd) td {
        background: #ffffff;
    }

    .six-arrears-table tbody tr:hover td {
        background: #eff6ff !important;
    }

    .six-arrears-table td.text-right {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        color: #0f172a;
    }

    .six-arrears-table td.text-center {
        text-align: center;
    }

    /* Conditional Formatting for Kolek Detail */
    .kolek-badge-sml {
        background-color: #fef3c7 !important;
        color: #d97706 !important;
        font-weight: 800 !important;
        text-align: center !important;
    }

    .kolek-badge-kl {
        background-color: #ffedd5 !important;
        color: #ea580c !important;
        font-weight: 800 !important;
        text-align: center !important;
    }

    .kolek-badge-d {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
        font-weight: 800 !important;
        text-align: center !important;
    }

    .kolek-badge-m {
        background-color: #fecdd3 !important;
        color: #be123c !important;
        font-weight: 800 !important;
        text-align: center !important;
    }

    .six-arrears-empty,
    .six-arrears-loading {
        padding: 3.5rem 1rem;
        text-align: center;
        color: #64748b;
        font-weight: 700;
        background: #f8fafc;
    }

    .six-arrears-empty i,
    .six-arrears-loading i {
        display: block;
        font-size: 2rem;
        margin-bottom: 0.75rem;
        color: var(--six-blue);
    }

    .six-arrears-status {
        margin-top: 0.75rem;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .six-arrears-status::before {
        content: '';
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #10b981;
    }

    @media (max-width: 991.98px) {
        .six-arrears-filter-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .six-arrears-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .six-arrears-actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
    }

    @media (max-width: 575.98px) {
        .six-arrears-hero {
            align-items: flex-start;
            flex-direction: column;
            padding: 1.25rem 1.5rem;
        }

        .six-arrears-filter-grid {
            grid-template-columns: 1fr;
        }

        .six-arrears-summary {
            grid-template-columns: 1fr;
        }

        .six-arrears-btn {
            width: 100%;
        }
    }
</style>

<div class="six-arrears-page pt-4">
    <div class="container-fluid">
        <div class="six-arrears-hero">
            <div>
                <h1 class="six-arrears-title" id="sixArrearsPageTitle">Monitoring Realisasi yang Menunggak</h1>
                <p class="six-arrears-subtitle" id="sixArrearsPageSubtitle">Debitur dengan tanggal realisasi M-{{ $rangeMonths ?? 6 }} s/d periode terpilih, kolek minimal 2, dan total tunggakan di atas 0.</p>
            </div>
            <div class="six-arrears-badge" id="sixArrearsTargetBadge">Bulan realisasi: {{ $targetMonthLabel }}</div>
        </div>

        <div class="six-arrears-panel">
            <div class="six-arrears-panel-body">
                <form id="sixArrearsForm" method="GET" action="{{ route('report.dashboard-pinjaman.realisasi-6-bulan-menunggak') }}">
                    <div class="six-arrears-filter-grid">
                        <div class="six-arrears-field">
                            <label for="sixArrearsBranch">Cabang</label>
                            <select id="sixArrearsBranch" name="cabang1" class="six-arrears-control">
                                @foreach ($branchOptions as $branchOption)
                                    <option value="{{ $branchOption }}" {{ $selectedBranchValue === $branchOption ? 'selected' : '' }}>
                                        {{ $branchOption === 'AREA_6_ALL' ? 'Area 6 - All' : $branchOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="six-arrears-field">
                            <label for="sixArrearsUnit">Unit</label>
                            <select id="sixArrearsUnit" name="unit1" class="six-arrears-control" {{ $isAreaAllSelected ? 'disabled' : '' }}>
                                @if ($isAreaAllSelected)
                                    <option value="ALL_UKER" selected>ALL UKER</option>
                                @else
                                    @foreach ($unitOptions as $unitOption)
                                        <option value="{{ $unitOption }}" {{ $selectedUnitValue === $unitOption ? 'selected' : '' }}>
                                            {{ $unitOption === 'ALL_UKER' ? 'ALL UKER' : $unitOption }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="six-arrears-field">
                            <label for="sixArrearsRangeMonths">Jangka Waktu</label>
                            <select id="sixArrearsRangeMonths" name="range_months" class="six-arrears-control">
                                <option value="4" {{ ($rangeMonths ?? 6) == 4 ? 'selected' : '' }}>4 Bulan Realisasi</option>
                                <option value="6" {{ ($rangeMonths ?? 6) == 6 ? 'selected' : '' }}>6 Bulan Realisasi</option>
                            </select>
                        </div>

                        <div class="six-arrears-field">
                            <label for="sixArrearsPeriod">Periode Data</label>
                            <select id="sixArrearsPeriod" name="periode" class="six-arrears-control">
                                @forelse ($availablePeriods as $periodOption)
                                    <option value="{{ $periodOption }}" {{ $selectedPeriod === $periodOption ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::parse($periodOption)->translatedFormat('d F Y') }}
                                    </option>
                                @empty
                                    <option value="">Tidak ada periode</option>
                                @endforelse
                            </select>
                        </div>

                        <div class="six-arrears-actions">
                            <button type="submit" class="six-arrears-btn six-arrears-btn-primary" id="sixArrearsShowBtn">
                                <i class="fas fa-search"></i>
                                Tampilkan
                            </button>
                            <button type="button" class="six-arrears-btn six-arrears-btn-light" id="sixArrearsResetBtn">
                                <i class="fas fa-undo"></i>
                                Reset
                            </button>
                            <button type="button" class="six-arrears-btn six-arrears-btn-success" id="sixArrearsExportBtn">
                                <i class="fas fa-file-excel"></i>
                                Excel
                            </button>
                        </div>
                    </div>
                </form>
                <div class="six-arrears-status" id="sixArrearsStatus">Menyiapkan data...</div>
            </div>
        </div>

        <div class="six-arrears-summary">
            <div class="six-arrears-metric" style="border-left: 4px solid var(--six-blue);">
                <span>Debitur</span>
                <strong id="sixArrearsDebitur">0</strong>
            </div>
            <div class="six-arrears-metric" style="border-left: 4px solid #0d9488;">
                <span>Outstanding</span>
                <strong id="sixArrearsOutstanding">Rp 0</strong>
            </div>
            <div class="six-arrears-metric" style="border-left: 4px solid #e11d48;">
                <span>Total Tunggakan</span>
                <strong id="sixArrearsTunggakan">Rp 0</strong>
            </div>
            <div class="six-arrears-metric" style="border-left: 4px solid #d97706;">
                <span id="sixArrearsLabelTargetMonth">Bulan Realisasi (M-{{ $rangeMonths ?? 6 }})</span>
                <strong id="sixArrearsTargetMonth">{{ $targetMonthLabel }}</strong>
            </div>
        </div>

        <div class="six-arrears-panel">
            <div class="six-arrears-table-wrap">
                <table class="six-arrears-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Cabang</th>
                            <th>Unit</th>
                            <th>No Rekening</th>
                            <th>Nama Debitur</th>
                            <th>Tgl Realisasi</th>
                            <th class="text-right">Plafon</th>
                            <th class="text-right">OS</th>
                            <th class="text-right">Total Tunggakan</th>
                            <th class="text-right">Umur Tunggakan</th>
                            <th class="text-right" style="width: 70px;">Kolek</th>
                            <th>Kolek Detail</th>
                        </tr>
                    </thead>
                    <tbody id="sixArrearsBody">
                        <tr>
                            <td colspan="12" class="six-arrears-loading">
                                <i class="fas fa-spinner fa-spin"></i>
                                Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('sixArrearsForm');
        const branchInput = document.getElementById('sixArrearsBranch');
        const unitInput = document.getElementById('sixArrearsUnit');
        const rangeInput = document.getElementById('sixArrearsRangeMonths');
        const periodInput = document.getElementById('sixArrearsPeriod');
        const showButton = document.getElementById('sixArrearsShowBtn');
        const resetButton = document.getElementById('sixArrearsResetBtn');
        const exportButton = document.getElementById('sixArrearsExportBtn');
        const statusEl = document.getElementById('sixArrearsStatus');
        const bodyEl = document.getElementById('sixArrearsBody');
        const targetBadgeEl = document.getElementById('sixArrearsTargetBadge');
        const targetMonthEl = document.getElementById('sixArrearsTargetMonth');
        const debiturEl = document.getElementById('sixArrearsDebitur');
        const outstandingEl = document.getElementById('sixArrearsOutstanding');
        const tunggakanEl = document.getElementById('sixArrearsTunggakan');

        const filtersUrl = @json(route('report.dashboard-pinjaman.realisasi-6-bulan-menunggak.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.realisasi-6-bulan-menunggak.data'));
        const exportUrl = @json(route('report.dashboard-pinjaman.realisasi-6-bulan-menunggak.export'));
        const pageUrl = @json(route('report.dashboard-pinjaman.realisasi-6-bulan-menunggak'));
        const defaultPeriod = @json($selectedPeriod);

        const numberFormatter = new Intl.NumberFormat('id-ID');
        const currencyFormatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        });
        const dateFormatter = new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });

        const labelBranch = (value) => value === 'AREA_6_ALL' ? 'Area 6 - All' : value;
        const labelUnit = (value) => value === 'ALL_UKER' ? 'ALL UKER' : value;
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const buildParams = () => {
            const params = new URLSearchParams();
            if (periodInput.value) {
                params.set('periode', periodInput.value);
            }
            if (branchInput.value) {
                params.set('cabang1', branchInput.value);
            }
            if (!unitInput.disabled && unitInput.value) {
                params.set('unit1', unitInput.value);
            }
            if (rangeInput && rangeInput.value) {
                params.set('range_months', rangeInput.value);
            }
            return params;
        };

        const formatDate = (value) => {
            if (!value) {
                return '-';
            }
            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) {
                return value;
            }
            return dateFormatter.format(parsed);
        };

        const renderOptions = (select, options, selectedValue, labelResolver) => {
            select.innerHTML = '';
            options.forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = labelResolver(value);
                option.selected = value === selectedValue;
                select.appendChild(option);
            });
        };

        const setLoading = (isLoading) => {
            showButton.disabled = isLoading;
            exportButton.disabled = isLoading || !periodInput.value;
            statusEl.textContent = isLoading ? 'Memuat data...' : statusEl.textContent;
        };

        const renderRows = (rows) => {
            const rangeVal = rangeInput ? rangeInput.value : '6';
            if (!rows.length) {
                bodyEl.innerHTML = `<tr><td colspan="12" class="six-arrears-empty">
                    <i class="fas fa-info-circle"></i>
                    Tidak ada debitur yang memenuhi filter realisasi M-${rangeVal} s/d periode terpilih, kolek minimal 2, dan total tunggakan > 0.
                </td></tr>`;
                return;
            }

            bodyEl.innerHTML = rows.map((row, index) => {
                const kolekDetail = String(row.kolek_detail || '-').toUpperCase();
                let badgeClass = '';
                if (kolekDetail.includes('SML')) {
                    badgeClass = 'kolek-badge-sml';
                } else if (kolekDetail.includes('KL') || kolekDetail.includes('KURANG LANCAR')) {
                    badgeClass = 'kolek-badge-kl';
                } else if (kolekDetail.includes('D') || kolekDetail.includes('DIRAGUKAN')) {
                    badgeClass = 'kolek-badge-d';
                } else if (kolekDetail.includes('M') || kolekDetail.includes('MACET')) {
                    badgeClass = 'kolek-badge-m';
                }

                return `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${escapeHtml(row.cabang1 || '-')}</td>
                        <td>${escapeHtml(row.unit1 || '-')}</td>
                        <td>${escapeHtml(row.nomor_rekening1 || '-')}</td>
                        <td>${escapeHtml(row.nama_debitur1 || '-')}</td>
                        <td class="text-center">${escapeHtml(formatDate(row.tgl_realisasi))}</td>
                        <td class="text-right">${currencyFormatter.format(Number(row.plafon || 0))}</td>
                        <td class="text-right">${currencyFormatter.format(Number(row.baki_debet1 || 0))}</td>
                        <td class="text-right">${currencyFormatter.format(Number(row.total_tunggakan || row.tunggakan_pokok || 0))}</td>
                        <td class="text-right">${numberFormatter.format(Number(row.umur_tunggakan || 0))}</td>
                        <td class="text-center" style="font-weight: 850; color: #0f172a;">${escapeHtml(row.kolek || '-')}</td>
                        <td class="text-center ${badgeClass}">${escapeHtml(row.kolek_detail || '-')}</td>
                    </tr>
                `;
            }).join('');
        };

        const updateLabels = () => {
            const rangeVal = rangeInput ? rangeInput.value : '6';
            const pageSubtitle = document.getElementById('sixArrearsPageSubtitle');
            const labelTargetMonth = document.getElementById('sixArrearsLabelTargetMonth');

            if (pageSubtitle) {
                pageSubtitle.textContent = `Debitur dengan tanggal realisasi M-${rangeVal} s/d periode terpilih, kolek minimal 2, dan total tunggakan di atas 0.`;
            }
            if (labelTargetMonth) {
                labelTargetMonth.textContent = `Bulan Realisasi (M-${rangeVal})`;
            }
        };

        const updateSummary = (payload) => {
            const summary = payload.summary || {};
            debiturEl.textContent = numberFormatter.format(Number(summary.debitur || 0));
            outstandingEl.textContent = currencyFormatter.format(Number(summary.outstanding || 0));
            tunggakanEl.textContent = currencyFormatter.format(Number(summary.total_tunggakan || summary.tunggakan_pokok || 0));
            targetMonthEl.textContent = summary.target_month || payload.target_month_label || '-';
            targetBadgeEl.textContent = `Bulan realisasi: ${payload.target_month_label || '-'}`;
            statusEl.textContent = `${payload.scope_label || 'Area 6 - All'} | ${payload.unit_label || 'ALL UKER'} | ${payload.selected_period || '-'}`;
        };

        const loadFilters = async () => {
            const params = buildParams();
            const response = await fetch(`${filtersUrl}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Gagal memuat filter.');
            }

            const payload = await response.json();
            const currentUnit = unitInput.value || 'ALL_UKER';
            const unitOptions = payload.unit_options || [];

            if (payload.is_area_all || unitOptions.length === 0) {
                unitInput.disabled = true;
                renderOptions(unitInput, ['ALL_UKER'], 'ALL_UKER', labelUnit);
            } else {
                unitInput.disabled = false;
                renderOptions(unitInput, unitOptions, unitOptions.includes(currentUnit) ? currentUnit : 'ALL_UKER', labelUnit);
            }

            targetBadgeEl.textContent = `Bulan realisasi: ${payload.target_month_label || '-'}`;
            targetMonthEl.textContent = payload.target_month_label || '-';
        };

        const loadData = async (updateUrl = true) => {
            setLoading(true);
            bodyEl.innerHTML = `<tr><td colspan="12" class="six-arrears-loading">
                <i class="fas fa-spinner fa-spin"></i>
                Memuat data...
            </td></tr>`;

            try {
                await loadFilters();
                const params = buildParams();
                const response = await fetch(`${dataUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat data.');
                }

                const payload = await response.json();
                renderRows(payload.rows || []);
                updateSummary(payload);

                if (updateUrl) {
                    window.history.replaceState({}, '', `${pageUrl}?${params.toString()}`);
                }
            } catch (error) {
                statusEl.textContent = error.message || 'Gagal memuat data.';
                bodyEl.innerHTML = `<tr><td colspan="12" class="six-arrears-empty">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                    Data belum bisa dimuat. Silakan coba lagi.
                </td></tr>`;
            } finally {
                setLoading(false);
            }
        };

        branchInput.addEventListener('change', () => {
            loadData();
        });

        periodInput.addEventListener('change', () => {
            loadData();
        });

        unitInput.addEventListener('change', () => {
            loadData();
        });

        if (rangeInput) {
            rangeInput.addEventListener('change', () => {
                updateLabels();
                loadData();
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            loadData();
        });

        resetButton.addEventListener('click', function () {
            branchInput.value = 'AREA_6_ALL';
            periodInput.value = defaultPeriod || periodInput.value;
            if (rangeInput) {
                rangeInput.value = '6';
            }
            unitInput.disabled = true;
            renderOptions(unitInput, ['ALL_UKER'], 'ALL_UKER', labelUnit);
            updateLabels();
            loadData();
        });

        exportButton.addEventListener('click', function () {
            const params = buildParams();
            window.location.href = `${exportUrl}?${params.toString()}`;
        });

        updateLabels();
        loadData(false);
    });
</script>
@endpush

