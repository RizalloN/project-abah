@extends('layouts.admin')

@section('title', 'Analisa UG NPL')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

@php
    $selectedBranchValue = $selectedBranches[0] ?? 'AREA_6_ALL';
    $selectedUnitValue = $selectedUnits[0] ?? 'ALL_UKER';
    $selectedSegmentValue = $selectedSegments[0] ?? 'ALL_SEGMEN';
@endphp

<style>
    .ug-npl-page,
    .ug-npl-page * {
        box-sizing: border-box;
    }

    .ug-npl-page {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: clip;
        color: #0f172a;
    }

    .ug-npl-panel {
        min-width: 0;
        max-width: 100%;
        border: 1px solid #cbd5e1;
        border-top: 3px solid var(--loan-blue);
        background: #ffffff;
        margin-bottom: 1rem;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.03);
    }

    .ug-npl-panel-body {
        min-width: 0;
        padding: 1rem;
    }

    .ug-npl-filter-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr)) auto;
        gap: 0.75rem;
        align-items: end;
    }

    .ug-npl-field {
        min-width: 0;
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
        min-width: 0;
        height: 40px;
        border: 1px solid #cbd5e1;
        border-radius: 0;
        background: #ffffff;
        color: #1e293b;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 0.35rem 0.65rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ug-npl-actions {
        display: flex;
        min-width: 0;
        gap: 0.5rem;
        flex-wrap: wrap;
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
        min-width: 0;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid #cbd5e1;
        border-left: 4px solid var(--loan-blue);
        background: #ffffff;
    }

    .ug-npl-hero-copy {
        min-width: 0;
        flex: 1 1 420px;
    }

    .ug-npl-title {
        margin: 0;
        color: var(--loan-blue-ink);
        font-size: 1.35rem;
        font-weight: 900;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }

    .ug-npl-meta {
        margin: 0.3rem 0 0;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .ug-npl-status {
        align-self: flex-start;
        max-width: min(100%, 430px);
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #475569;
        padding: 0.45rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 800;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .ug-npl-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .ug-npl-metric {
        min-width: 0;
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
        overflow-wrap: anywhere;
    }

    .ug-npl-table-wrap {
        position: relative;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        scrollbar-gutter: stable;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        max-height: var(--ug-npl-table-max-height, 64dvh);
    }

    .ug-npl-action-table-wrap {
        --ug-npl-table-max-height: 280px;
    }

    .ug-npl-table {
        width: max-content;
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
        white-space: normal;
        line-height: 1.2;
    }

    .ug-npl-table th:first-child,
    .ug-npl-table td:first-child:not([colspan]) {
        position: sticky;
        left: 0;
    }

    .ug-npl-table th:first-child {
        z-index: 3;
    }

    .ug-npl-table td:first-child:not([colspan]) {
        z-index: 1;
        background: #ffffff;
        box-shadow: 2px 0 0 #e2e8f0;
    }

    .ug-npl-table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }

    .ug-npl-table tbody tr:nth-child(even) td:first-child:not([colspan]) {
        background: #f8fafc;
    }

    .ug-npl-table .text-right {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .ug-npl-chip {
        max-width: 100%;
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0.15rem 0.45rem;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #334155;
        font-size: 0.72rem;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .ug-npl-cell-wrap {
        min-width: 180px;
        max-width: 280px;
        white-space: normal !important;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .ug-npl-section-head {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        justify-content: space-between;
    }

    .ug-npl-section-head h5 {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    @media (max-width: 1399.98px) {
        .ug-npl-filter-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .ug-npl-actions {
            grid-column: span 3;
        }
    }

    @media (max-width: 991.98px) {
        .ug-npl-metrics {
            grid-template-columns: 1fr 1fr;
        }

        .ug-npl-table-wrap {
            scrollbar-gutter: auto;
        }
    }

    @media (max-width: 767.98px) {
        .ug-npl-filter-grid {
            grid-template-columns: 1fr 1fr;
        }

        .ug-npl-actions {
            grid-column: span 2;
        }
    }

    @media (max-width: 575.98px) {
        .ug-npl-filter-grid,
        .ug-npl-metrics {
            grid-template-columns: 1fr;
        }

        .ug-npl-actions {
            grid-column: auto;
            justify-content: stretch;
        }

        .ug-npl-btn {
            flex: 1 1 0;
            justify-content: center;
            padding: 0 0.65rem;
        }

        .ug-npl-hero {
            flex-direction: column;
        }

        .ug-npl-status {
            max-width: 100%;
            text-align: left;
        }

        .ug-npl-panel-body {
            padding: 0.8rem;
        }

        .ug-npl-table th,
        .ug-npl-table td {
            padding: 0.45rem 0.55rem;
            font-size: 0.74rem;
        }
    }

    @media (max-height: 700px) and (min-width: 768px) {
        .ug-npl-table-wrap {
            max-height: min(var(--ug-npl-table-max-height, 64dvh), 52dvh);
        }
    }
</style>

<div class="loan-dashboard ug-npl-page">
    <div class="ug-npl-hero">
        <div class="ug-npl-hero-copy">
            <h1 class="ug-npl-title">Analisa UG NPL</h1>
            <p class="ug-npl-meta">Estimasi siklus bayar anuitas berdasarkan umur tunggakan dan NPB LA.</p>
        </div>
        <div class="ug-npl-status" id="ugNplStatus" aria-live="polite">Menyiapkan data</div>
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
                        <label for="ugNplSegment">Segmen</label>
                        <select id="ugNplSegment" name="segmen_dashboard" class="ug-npl-control">
                            @foreach($segmentOptions as $segment)
                                <option value="{{ $segment }}" {{ $segment === $selectedSegmentValue ? 'selected' : '' }}>
                                    {{ $segment === 'ALL_SEGMEN' ? 'Semua Segmen' : $segment }}
                                </option>
                            @endforeach
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
            <div class="ug-npl-table-wrap ug-npl-action-table-wrap" tabindex="0" aria-label="Tabel ringkasan action UG NPL">
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
            <div class="ug-npl-section-head mb-3">
                <h5 class="mb-0 font-weight-bold text-uppercase">Nominatif Prioritas</h5>
                <span class="ug-npl-chip" id="ugNplRowInfo">0 row</span>
            </div>
            <div class="ug-npl-table-wrap ug-npl-detail-table-wrap" tabindex="0" aria-label="Tabel nominatif prioritas UG NPL">
                <table class="ug-npl-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Cabang</th>
                            <th>Unit</th>
                            <th>Segmen</th>
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
                        <tr><td colspan="19" class="text-center text-muted">Memuat data...</td></tr>
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
        const tableWraps = Array.from(document.querySelectorAll('.ug-npl-table-wrap'));
        let activeRequest = null;
        let requestSequence = 0;
        let viewportFrame = 0;

        function currency(value) {
            return `Rp ${formatNumber(Math.round(Number(value || 0)))}`;
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'"]/g, (character) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;',
            }[character]));
        }

        function displayText(value, fallback = '-') {
            const text = String(value ?? '').trim();
            return escapeHtml(text || fallback);
        }

        function setLoading(isLoading, message = null) {
            if (message !== null) statusEl.textContent = message;
            refreshButton.disabled = isLoading;
            refreshIcon.className = isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt';
            form.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }

        function paramsFromForm(forceRefresh = false) {
            const params = new URLSearchParams(new FormData(form));
            const unitControl = document.getElementById('ugNplUnit');
            if (unitControl && unitControl.disabled) params.set('unit1', 'ALL_UKER');
            if (forceRefresh) params.set('refresh', '1');
            return params;
        }

        function scheduleViewportSync() {
            if (viewportFrame) cancelAnimationFrame(viewportFrame);
            viewportFrame = requestAnimationFrame(() => {
                viewportFrame = 0;
                const viewportHeight = Math.round(window.visualViewport?.height || window.innerHeight || 0);
                tableWraps.forEach((wrap) => {
                    const rect = wrap.getBoundingClientRect();
                    const isSummary = wrap.classList.contains('ug-npl-action-table-wrap');
                    const minimum = isSummary ? 180 : 260;
                    const maximum = isSummary ? 300 : 680;
                    const available = Math.floor(viewportHeight - Math.max(0, rect.top) - 20);
                    const height = Math.max(minimum, Math.min(maximum, available || minimum));
                    wrap.style.setProperty('--ug-npl-table-max-height', `${height}px`);
                });
            });
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
                    <td><span class="ug-npl-chip">${displayText(item.label)}</span></td>
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
                rowsBody.innerHTML = '<tr><td colspan="19" class="text-center text-muted">Tidak ada nominatif.</td></tr>';
                return;
            }

            rowsBody.innerHTML = rows.map((row) => `
                <tr>
                    <td><span class="ug-npl-chip">${displayText(row.action_label)}</span></td>
                    <td>${displayText(row.cabang1)}</td>
                    <td>${displayText(row.unit1)}</td>
                    <td><span class="ug-npl-chip">${displayText(row.segmen_dashboard)}</span></td>
                    <td>${displayText(row.nomor_rekening1)}</td>
                    <td class="ug-npl-cell-wrap">${displayText(row.nama_debitur1)}</td>
                    <td class="text-center">${displayText(row.current_bucket)}</td>
                    <td class="text-center">${displayText(row.target_bucket)}</td>
                    <td class="text-center">${displayText(row.loan_type)}</td>
                    <td class="ug-npl-cell-wrap">${displayText(row.payment_rule)}</td>
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
            const sequence = ++requestSequence;
            if (activeRequest) activeRequest.abort();
            activeRequest = new AbortController();
            setLoading(true, 'Memuat data');
            try {
                const response = await fetch(`${dataUrl}?${paramsFromForm(forceRefresh).toString()}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
                });
                if (!response.ok) throw new Error('Gagal memuat data');
                const payload = await response.json();
                if (sequence !== requestSequence) return;
                renderSummary(payload.summary || {});
                renderActions(payload.actions || []);
                renderRows(payload.rows || [], payload);
                statusEl.textContent = `Periode ${formatDate(payload.selected_period)} - ${payload.segment_label || 'Semua Segmen'} - horizon ${payload.horizon_days} hari`;
                scheduleViewportSync();
            } catch (error) {
                if (error.name === 'AbortError' || sequence !== requestSequence) return;
                statusEl.textContent = 'Gagal memuat data';
                actionBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Gagal memuat data.</td></tr>';
                rowsBody.innerHTML = '<tr><td colspan="19" class="text-center text-danger">Gagal memuat data.</td></tr>';
            } finally {
                if (sequence === requestSequence) {
                    activeRequest = null;
                    setLoading(false);
                    scheduleViewportSync();
                }
            }
        }

        refreshButton.addEventListener('click', () => loadData(true));
        window.addEventListener('resize', scheduleViewportSync, { passive: true });
        window.addEventListener('orientationchange', scheduleViewportSync, { passive: true });
        window.visualViewport?.addEventListener('resize', scheduleViewportSync, { passive: true });
        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(scheduleViewportSync);
            observer.observe(document.querySelector('.ug-npl-page'));
        }
        scheduleViewportSync();
        loadData(false);
    });
</script>
@endpush
@endsection
