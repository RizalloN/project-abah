@extends('layouts.admin')

@section('title', 'Analisa UG NPL')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

@php
    $selectedBranchValue = $selectedBranches[0] ?? 'AREA_6_ALL';
    $selectedUnitValue = $selectedUnits[0] ?? 'ALL_UKER';
@endphp

<style>
    .ug-npl-page {
        color: #0f172a;
    }

    .ug-npl-panel {
        border: 1px solid #cbd5e1;
        border-top: 3px solid var(--loan-blue);
        background: #ffffff;
        margin-bottom: 1rem;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.03);
    }

    .ug-npl-panel-body {
        padding: 1rem;
    }

    .ug-npl-filter-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(150px, 1fr)) auto;
        gap: 0.75rem;
        align-items: end;
    }

    .ug-npl-field label {
        display: block;
        margin-bottom: 0.35rem;
        color: #475569;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .ug-npl-control {
        width: 100%;
        height: 40px;
        border: 1px solid #cbd5e1;
        border-radius: 0;
        background: #ffffff;
        color: #1e293b;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 0.35rem 0.65rem;
    }

    .ug-npl-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .ug-npl-btn {
        height: 40px;
        border-radius: 0;
        border: 1px solid #cbd5e1;
        padding: 0 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ug-npl-btn-primary {
        background: var(--loan-blue);
        border-color: var(--loan-blue);
        color: #ffffff;
    }

    .ug-npl-btn-light {
        background: #ffffff;
        color: #475569;
    }

    .ug-npl-btn:disabled {
        opacity: 0.65;
        cursor: wait;
    }

    .ug-npl-hero {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid #cbd5e1;
        border-left: 4px solid var(--loan-blue);
        background: #ffffff;
    }

    .ug-npl-title {
        margin: 0;
        color: var(--loan-blue-ink);
        font-size: 1.35rem;
        font-weight: 900;
        text-transform: uppercase;
    }

    .ug-npl-meta {
        margin: 0.3rem 0 0;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .ug-npl-status {
        align-self: center;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #475569;
        padding: 0.45rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .ug-npl-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .ug-npl-metric {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        padding: 0.9rem 1rem;
        min-height: 86px;
    }

    .ug-npl-metric span {
        display: block;
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 0.3rem;
    }

    .ug-npl-metric strong {
        display: block;
        color: #0f172a;
        font-size: 1.2rem;
        font-weight: 900;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }

    .ug-npl-table-wrap {
        overflow: auto;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        max-height: 64vh;
    }

    .ug-npl-table {
        width: 100%;
        min-width: 1320px;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
    }

    .ug-npl-table th,
    .ug-npl-table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.5rem 0.65rem;
        font-size: 0.78rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .ug-npl-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--loan-blue-ink);
        color: #ffffff;
        text-align: center;
        font-weight: 850;
        text-transform: uppercase;
    }

    .ug-npl-table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }

    .ug-npl-table .text-right {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .ug-npl-chip {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0.15rem 0.45rem;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #334155;
        font-size: 0.72rem;
        font-weight: 800;
    }

    @media (max-width: 991.98px) {
        .ug-npl-filter-grid,
        .ug-npl-metrics {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 575.98px) {
        .ug-npl-filter-grid,
        .ug-npl-metrics {
            grid-template-columns: 1fr;
        }

        .ug-npl-hero {
            flex-direction: column;
        }
    }
</style>

<div class="loan-dashboard ug-npl-page">
    <div class="ug-npl-hero">
        <div>
            <h1 class="ug-npl-title">Analisa UG NPL</h1>
            <p class="ug-npl-meta">Estimasi siklus bayar anuitas berdasarkan umur tunggakan dan NPB LA.</p>
        </div>
        <div class="ug-npl-status" id="ugNplStatus">Menyiapkan data</div>
    </div>

    <div class="ug-npl-panel">
        <div class="ug-npl-panel-body">
            <form id="ugNplForm" method="GET" action="{{ route('report.dashboard-pinjaman.analisa-ug-npl') }}">
                <div class="ug-npl-filter-grid">
                    <div class="ug-npl-field">
                        <label for="ugNplPeriod">Periode</label>
                        <select id="ugNplPeriod" name="periode" class="ug-npl-control">
                            @foreach($availablePeriods as $period)
                                <option value="{{ $period }}" {{ $period === $selectedPeriod ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($period)->format('d M Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ug-npl-field">
                        <label for="ugNplBranch">Cabang</label>
                        <select id="ugNplBranch" name="cabang1" class="ug-npl-control">
                            @foreach($branchOptions as $branch)
                                <option value="{{ $branch }}" {{ $branch === $selectedBranchValue ? 'selected' : '' }}>
                                    {{ $branch === 'AREA_6_ALL' ? 'Area 6 - All' : $branch }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ug-npl-field">
                        <label for="ugNplUnit">Unit</label>
                        <select id="ugNplUnit" name="unit1" class="ug-npl-control" {{ $isAreaAllSelected ? 'disabled' : '' }}>
                            @if($isAreaAllSelected)
                                <option value="ALL_UKER">ALL UKER</option>
                            @else
                                @foreach($unitOptions as $unit)
                                    <option value="{{ $unit }}" {{ $unit === $selectedUnitValue ? 'selected' : '' }}>
                                        {{ $unit }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="ug-npl-field">
                        <label for="ugNplHorizon">Horizon</label>
                        <select id="ugNplHorizon" name="horizon_days" class="ug-npl-control">
                            @foreach($horizonOptions as $days => $label)
                                <option value="{{ $days }}" {{ (int) $days === (int) $selectedHorizonDays ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ug-npl-field">
                        <label for="ugNplAction">Analisa</label>
                        <select id="ugNplAction" name="action" class="ug-npl-control">
                            @foreach($actionOptions as $value => $label)
                                <option value="{{ $value }}" {{ $value === $selectedAction ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ug-npl-actions">
                        <button type="submit" class="ug-npl-btn ug-npl-btn-primary">
                            <i class="fas fa-filter"></i>
                            <span>Tampilkan</span>
                        </button>
                        <button type="button" id="ugNplRefresh" class="ug-npl-btn ug-npl-btn-light">
                            <i class="fas fa-sync-alt" id="ugNplRefreshIcon"></i>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="ug-npl-metrics">
        <div class="ug-npl-metric">
            <span>Rekening</span>
            <strong id="ugNplAccounts">0</strong>
        </div>
        <div class="ug-npl-metric">
            <span>Outstanding</span>
            <strong id="ugNplOutstanding">Rp 0</strong>
        </div>
        <div class="ug-npl-metric">
            <span>Estimasi Bayar</span>
            <strong id="ugNplPayment">Rp 0</strong>
        </div>
        <div class="ug-npl-metric">
            <span>Total Siklus</span>
            <strong id="ugNplCycles">0</strong>
        </div>
    </div>

    <div class="ug-npl-panel">
        <div class="ug-npl-panel-body">
            <h5 class="mb-3 font-weight-bold text-uppercase">Ringkasan Action</h5>
            <div class="ug-npl-table-wrap" style="max-height: 280px;">
                <table class="ug-npl-table" style="min-width: 980px;">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Rekening</th>
                            <th>Outstanding</th>
                            <th>Tunggakan Saat Ini</th>
                            <th>Estimasi Bayar</th>
                            <th>Pokok</th>
                            <th>Bunga</th>
                            <th>Penalti</th>
                            <th>Siklus</th>
                        </tr>
                    </thead>
                    <tbody id="ugNplActionBody">
                        <tr><td colspan="9" class="text-center text-muted">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ug-npl-panel">
        <div class="ug-npl-panel-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 font-weight-bold text-uppercase">Nominatif Prioritas</h5>
                <span class="ug-npl-chip" id="ugNplRowInfo">0 row</span>
            </div>
            <div class="ug-npl-table-wrap">
                <table class="ug-npl-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Cabang</th>
                            <th>Unit</th>
                            <th>No Rekening</th>
                            <th>Nama Debitur</th>
                            <th>Kolek</th>
                            <th>Target</th>
                            <th>Loan Type</th>
                            <th>Rule</th>
                            <th>Umur</th>
                            <th>Bulan Efektif</th>
                            <th>Siklus</th>
                            <th>Estimasi / Bayar</th>
                            <th>Estimasi Bayar</th>
                            <th>Pokok</th>
                            <th>Bunga</th>
                            <th>Penalti</th>
                            <th>Outstanding</th>
                        </tr>
                    </thead>
                    <tbody id="ugNplRowsBody">
                        <tr><td colspan="18" class="text-center text-muted">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('ugNplForm');
        const refreshButton = document.getElementById('ugNplRefresh');
        const refreshIcon = document.getElementById('ugNplRefreshIcon');
        const statusEl = document.getElementById('ugNplStatus');
        const actionBody = document.getElementById('ugNplActionBody');
        const rowsBody = document.getElementById('ugNplRowsBody');
        const rowInfo = document.getElementById('ugNplRowInfo');
        const dataUrl = @json(route('report.dashboard-pinjaman.analisa-ug-npl.data'));

        function currency(value) {
            return `Rp ${formatNumber(Math.round(Number(value || 0)))}`;
        }

        function setLoading(isLoading, message) {
            statusEl.textContent = message;
            refreshButton.disabled = isLoading;
            refreshIcon.className = isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt';
        }

        function paramsFromForm(forceRefresh = false) {
            const params = new URLSearchParams(new FormData(form));
            if (forceRefresh) params.set('refresh', '1');
            return params;
        }

        function renderSummary(summary) {
            document.getElementById('ugNplAccounts').textContent = formatNumber(summary.accounts || 0);
            document.getElementById('ugNplOutstanding').textContent = currency(summary.outstanding);
            document.getElementById('ugNplPayment').textContent = currency(summary.estimated_payment);
            document.getElementById('ugNplCycles').textContent = formatNumber(summary.cycles || 0);
        }

        function renderActions(actions) {
            if (!actions || actions.length === 0) {
                actionBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Tidak ada action.</td></tr>';
                return;
            }

            actionBody.innerHTML = actions.map((item) => `
                <tr>
                    <td><span class="ug-npl-chip">${item.label}</span></td>
                    <td class="text-right">${formatNumber(item.accounts || 0)}</td>
                    <td class="text-right">${currency(item.outstanding)}</td>
                    <td class="text-right">${currency(item.current_arrears)}</td>
                    <td class="text-right font-weight-bold">${currency(item.estimated_payment)}</td>
                    <td class="text-right">${currency(item.estimated_principal)}</td>
                    <td class="text-right">${currency(item.estimated_interest)}</td>
                    <td class="text-right">${currency(item.estimated_penalty)}</td>
                    <td class="text-right">${formatNumber(item.cycles || 0)}</td>
                </tr>
            `).join('');
        }

        function renderRows(rows, payload) {
            rowInfo.textContent = `${formatNumber(payload.row_count || 0)} row ditampilkan`;
            if (!rows || rows.length === 0) {
                rowsBody.innerHTML = '<tr><td colspan="18" class="text-center text-muted">Tidak ada nominatif.</td></tr>';
                return;
            }

            rowsBody.innerHTML = rows.map((row) => `
                <tr>
                    <td><span class="ug-npl-chip">${row.action_label}</span></td>
                    <td>${row.cabang1 || '-'}</td>
                    <td>${row.unit1 || '-'}</td>
                    <td>${row.nomor_rekening1 || '-'}</td>
                    <td>${row.nama_debitur1 || '-'}</td>
                    <td class="text-center">${row.current_bucket || '-'}</td>
                    <td class="text-center">${row.target_bucket || '-'}</td>
                    <td class="text-center">${row.loan_type || '-'}</td>
                    <td>${row.payment_rule || '-'}</td>
                    <td class="text-right">${formatNumber(row.umur_tunggakan || 0)}</td>
                    <td class="text-right">${formatNumber(row.effective_months || 0)}</td>
                    <td class="text-right">${formatNumber(row.cycles || 0)}</td>
                    <td class="text-right">${currency(row.installment)}</td>
                    <td class="text-right font-weight-bold">${currency(row.estimated_payment)}</td>
                    <td class="text-right">${currency(row.estimated_principal)}</td>
                    <td class="text-right">${currency(row.estimated_interest)}</td>
                    <td class="text-right">${currency(row.estimated_penalty)}</td>
                    <td class="text-right">${currency(row.outstanding)}</td>
                </tr>
            `).join('');
        }

        async function loadData(forceRefresh = false) {
            setLoading(true, 'Memuat data');
            try {
                const response = await fetch(`${dataUrl}?${paramsFromForm(forceRefresh).toString()}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Gagal memuat data');
                const payload = await response.json();
                renderSummary(payload.summary || {});
                renderActions(payload.actions || []);
                renderRows(payload.rows || [], payload);
                statusEl.textContent = `Periode ${formatDate(payload.selected_period)} - horizon ${payload.horizon_days} hari`;
            } catch (error) {
                statusEl.textContent = 'Gagal memuat data';
                actionBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Gagal memuat data.</td></tr>';
                rowsBody.innerHTML = '<tr><td colspan="18" class="text-center text-danger">Gagal memuat data.</td></tr>';
            } finally {
                setLoading(false, statusEl.textContent);
            }
        }

        refreshButton.addEventListener('click', () => loadData(true));
        loadData(false);
    });
</script>
@endpush
@endsection
