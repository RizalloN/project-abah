@extends('layouts.admin')

@section('title', 'Realisasi 6 Bulan Menunggak')

@section('content')
@php
    $selectedBranchValue = $selectedBranches[0] ?? 'AREA_6_ALL';
    $selectedUnitValue = $selectedUnits[0] ?? 'ALL_UKER';
@endphp

<style>
    .six-arrears-page {
        --six-blue: #1557a6;
        --six-blue-deep: #0d3f82;
        --six-ink: #172033;
        --six-muted: #64748b;
        --six-line: #dbe5f1;
        --six-soft: #f6f9fd;
        color: var(--six-ink);
    }

    .six-arrears-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 1rem 1.15rem;
        border: 1px solid var(--six-line);
        background: linear-gradient(135deg, #ffffff 0%, #f4f8ff 100%);
        box-shadow: 0 16px 35px rgba(15, 23, 42, 0.06);
    }

    .six-arrears-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--six-ink);
        letter-spacing: 0;
    }

    .six-arrears-subtitle {
        margin: 0.25rem 0 0;
        color: var(--six-muted);
        font-size: 0.86rem;
        font-weight: 600;
    }

    .six-arrears-badge {
        flex: 0 0 auto;
        padding: 0.55rem 0.8rem;
        border: 1px solid rgba(21, 87, 166, 0.18);
        background: #ffffff;
        color: var(--six-blue);
        font-size: 0.82rem;
        font-weight: 800;
    }

    .six-arrears-panel {
        border: 1px solid var(--six-line);
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        margin-bottom: 1rem;
    }

    .six-arrears-panel-body {
        padding: 1rem;
    }

    .six-arrears-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr)) auto;
        gap: 0.85rem;
        align-items: end;
    }

    .six-arrears-field label {
        display: block;
        margin-bottom: 0.35rem;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .six-arrears-control {
        width: 100%;
        min-height: 40px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #172033;
        padding: 0.45rem 0.7rem;
        font-size: 0.86rem;
        font-weight: 700;
        outline: none;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }

    .six-arrears-control:focus {
        border-color: var(--six-blue);
        box-shadow: 0 0 0 0.16rem rgba(21, 87, 166, 0.1);
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
        padding: 0 0.9rem;
        font-size: 0.84rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        cursor: pointer;
        transition: background 0.16s ease, border-color 0.16s ease, color 0.16s ease;
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
        border-color: #cbd5e1;
        color: #334155;
    }

    .six-arrears-btn-light:hover {
        background: #f8fafc;
        color: #111827;
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
        opacity: 0.62;
        cursor: wait;
    }

    .six-arrears-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }

    .six-arrears-metric {
        border: 1px solid var(--six-line);
        background: #ffffff;
        padding: 0.85rem 1rem;
        min-height: 88px;
    }

    .six-arrears-metric span {
        display: block;
        color: var(--six-muted);
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .six-arrears-metric strong {
        display: block;
        margin-top: 0.35rem;
        color: var(--six-ink);
        font-size: 1.25rem;
        font-weight: 850;
        line-height: 1.15;
    }

    .six-arrears-table-wrap {
        overflow: auto;
        border: 1px solid var(--six-line);
        background: #ffffff;
        max-height: 68vh;
    }

    .six-arrears-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
    }

    .six-arrears-table th,
    .six-arrears-table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.58rem 0.65rem;
        font-size: 0.8rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .six-arrears-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #edf4ff;
        color: #1e3a5f;
        font-weight: 850;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .six-arrears-table tbody tr:hover td {
        background: #f8fbff;
    }

    .six-arrears-table .text-right {
        text-align: right;
    }

    .six-arrears-empty,
    .six-arrears-loading {
        padding: 2.25rem 1rem;
        text-align: center;
        color: var(--six-muted);
        font-weight: 700;
    }

    .six-arrears-status {
        margin-top: 0.75rem;
        color: var(--six-muted);
        font-size: 0.82rem;
        font-weight: 700;
    }

    @media (max-width: 991.98px) {
        .six-arrears-filter-grid,
        .six-arrears-summary {
            grid-template-columns: 1fr 1fr;
        }

        .six-arrears-actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
    }

    @media (max-width: 575.98px) {
        .six-arrears-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .six-arrears-filter-grid,
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
                <h1 class="six-arrears-title">Realisasi 6 Bulan Menunggak</h1>
                <p class="six-arrears-subtitle">Debitur dengan tanggal realisasi M-6 s/d periode terpilih, kolek minimal 2, dan total tunggakan di atas 0.</p>
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
            <div class="six-arrears-metric">
                <span>Debitur</span>
                <strong id="sixArrearsDebitur">0</strong>
            </div>
            <div class="six-arrears-metric">
                <span>Outstanding</span>
                <strong id="sixArrearsOutstanding">Rp 0</strong>
            </div>
            <div class="six-arrears-metric">
                <span>Total Tunggakan</span>
                <strong id="sixArrearsTunggakan">Rp 0</strong>
            </div>
            <div class="six-arrears-metric">
                <span>Bulan Realisasi</span>
                <strong id="sixArrearsTargetMonth">{{ $targetMonthLabel }}</strong>
            </div>
        </div>

        <div class="six-arrears-panel">
            <div class="six-arrears-table-wrap">
                <table class="six-arrears-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Cabang</th>
                            <th>Unit</th>
                            <th>No Rekening</th>
                            <th>Nama Debitur</th>
                            <th>Tgl Realisasi</th>
                            <th class="text-right">Plafon</th>
                            <th class="text-right">OS</th>
                            <th class="text-right">Total Tunggakan</th>
                            <th class="text-right">Umur Tunggakan</th>
                            <th class="text-right">Kolek</th>
                            <th>Kolek Detail</th>
                        </tr>
                    </thead>
                    <tbody id="sixArrearsBody">
                        <tr>
                            <td colspan="12" class="six-arrears-loading">Memuat data...</td>
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
            if (!rows.length) {
                bodyEl.innerHTML = '<tr><td colspan="12" class="six-arrears-empty">Tidak ada debitur yang memenuhi filter realisasi M-6 s/d periode terpilih, kolek minimal 2, dan total tunggakan > 0.</td></tr>';
                return;
            }

            bodyEl.innerHTML = rows.map((row, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(row.cabang1 || '-')}</td>
                    <td>${escapeHtml(row.unit1 || '-')}</td>
                    <td>${escapeHtml(row.nomor_rekening1 || '-')}</td>
                    <td>${escapeHtml(row.nama_debitur1 || '-')}</td>
                    <td>${escapeHtml(formatDate(row.tgl_realisasi))}</td>
                    <td class="text-right">${currencyFormatter.format(Number(row.plafon || 0))}</td>
                    <td class="text-right">${currencyFormatter.format(Number(row.baki_debet1 || 0))}</td>
                    <td class="text-right">${currencyFormatter.format(Number(row.total_tunggakan || row.tunggakan_pokok || 0))}</td>
                    <td class="text-right">${numberFormatter.format(Number(row.umur_tunggakan || 0))}</td>
                    <td class="text-right">${escapeHtml(row.kolek || '-')}</td>
                    <td>${escapeHtml(row.kolek_detail || '-')}</td>
                </tr>
            `).join('');
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
            bodyEl.innerHTML = '<tr><td colspan="12" class="six-arrears-loading">Memuat data...</td></tr>';

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
                bodyEl.innerHTML = '<tr><td colspan="12" class="six-arrears-empty">Data belum bisa dimuat. Silakan coba lagi.</td></tr>';
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

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            loadData();
        });

        resetButton.addEventListener('click', function () {
            branchInput.value = 'AREA_6_ALL';
            periodInput.value = defaultPeriod || periodInput.value;
            unitInput.disabled = true;
            renderOptions(unitInput, ['ALL_UKER'], 'ALL_UKER', labelUnit);
            loadData();
        });

        exportButton.addEventListener('click', function () {
            const params = buildParams();
            window.location.href = `${exportUrl}?${params.toString()}`;
        });

        loadData(false);
    });
</script>
@endpush
