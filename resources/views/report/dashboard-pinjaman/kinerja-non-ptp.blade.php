@extends('layouts.admin')

@section('title', 'Histori PTP Deb')

@section('content')
@php
    $fmtInt = static fn ($value): string => number_format((int) $value, 0, ',', '.');
    $fmtMoney = static fn ($value): string => 'Rp' . number_format((float) $value, 0, ',', '.');
    $fmtCompact = static function ($value): string {
        $value = (float) $value;
        $abs = abs($value);
        if ($abs >= 1000000000000) {
            return 'Rp' . number_format($value / 1000000000000, 2, ',', '.') . ' T';
        }
        if ($abs >= 1000000000) {
            return 'Rp' . number_format($value / 1000000000, 2, ',', '.') . ' M';
        }
        if ($abs >= 1000000) {
            return 'Rp' . number_format($value / 1000000, 2, ',', '.') . ' Jt';
        }
        return 'Rp' . number_format($value, 0, ',', '.');
    };
    $fmtDate = static function ($value): string {
        if (!$value) {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return '-';
        }
    };
@endphp

<style>
    :root {
        --nonptp-blue: #0857c3;
        --nonptp-blue-dark: #053b82;
        --nonptp-sky: #2f80ed;
        --nonptp-cyan: #2fb8df;
        --nonptp-orange: #d97706;
        --nonptp-red: #dc2626;
        --nonptp-green: #059669;
        --nonptp-ink: #0f172a;
        --nonptp-muted: #64748b;
        --nonptp-border: #dbe4ef;
        --nonptp-bg: #f3f7fb;
    }

    .nonptp-page {
        min-height: calc(100vh - 60px);
        padding: 1.5rem 1.25rem 2rem;
        background: var(--nonptp-bg);
        color: var(--nonptp-ink);
        font-family: Inter, "Segoe UI", sans-serif;
    }

    .nonptp-shell {
        border: 1px solid var(--nonptp-border);
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
    }

    .nonptp-header {
        margin-bottom: 1rem;
        padding: 1.1rem 1.35rem;
        border-top: 4px solid var(--nonptp-blue);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .nonptp-brand {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }

    .nonptp-logo {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        color: #ffffff;
        background: linear-gradient(135deg, var(--nonptp-blue), var(--nonptp-sky));
        box-shadow: 0 10px 22px rgba(8, 87, 195, 0.22);
    }

    .nonptp-title {
        margin: 0;
        font-size: 1.25rem;
        line-height: 1.1;
        font-weight: 900;
        letter-spacing: -0.02em;
    }

    .nonptp-subtitle {
        margin-top: 0.25rem;
        color: var(--nonptp-muted);
        font-size: 0.78rem;
        font-weight: 700;
    }

    .nonptp-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border: 1px solid rgba(8, 87, 195, 0.18);
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        color: var(--nonptp-blue-dark);
        background: #eef6ff;
        font-size: 0.74rem;
        font-weight: 850;
    }

    .nonptp-panel {
        margin-bottom: 1rem;
        padding: 1rem 1.25rem;
    }

    .nonptp-label {
        display: block;
        margin-bottom: 0.4rem;
        color: var(--nonptp-muted);
        font-size: 0.68rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .nonptp-control {
        min-height: 42px;
        border: 1.5px solid var(--nonptp-border);
        border-radius: 8px;
        color: var(--nonptp-ink);
        font-size: 0.86rem;
        font-weight: 750;
        box-shadow: none;
    }

    .nonptp-control:focus {
        border-color: var(--nonptp-blue);
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.12);
    }

    .nonptp-action {
        min-height: 42px;
        border: 0;
        border-radius: 8px;
        color: #ffffff;
        background: linear-gradient(135deg, var(--nonptp-blue), var(--nonptp-blue-dark));
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .nonptp-view-tabs {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        padding: 0.35rem;
        border: 1px solid var(--nonptp-border);
        border-radius: 8px;
        background: #ffffff;
    }

    .nonptp-view-tab {
        min-height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        padding: 0.45rem 0.8rem;
        color: var(--nonptp-blue-dark);
        font-size: 0.78rem;
        font-weight: 900;
        text-decoration: none;
    }

    .nonptp-view-tab:hover {
        color: var(--nonptp-blue-dark);
        background: #eef6ff;
        text-decoration: none;
    }

    .nonptp-view-tab.is-active {
        color: #ffffff;
        background: var(--nonptp-blue);
        box-shadow: 0 8px 18px rgba(8, 87, 195, 0.18);
    }

    .nonptp-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }

    .nonptp-kpi {
        padding: 1rem;
        border: 1px solid var(--nonptp-border);
        border-radius: 10px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .nonptp-kpi .label {
        color: var(--nonptp-muted);
        font-size: 0.67rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .nonptp-kpi .value {
        margin-top: 0.35rem;
        color: var(--nonptp-ink);
        font-size: 1.25rem;
        font-weight: 950;
        line-height: 1.1;
    }

    .nonptp-kpi .hint {
        margin-top: 0.25rem;
        color: var(--nonptp-muted);
        font-size: 0.75rem;
        font-weight: 650;
    }

    .nonptp-section-title {
        margin: 0 0 0.75rem;
        color: var(--nonptp-blue-dark);
        font-size: 0.82rem;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .nonptp-table-wrap {
        width: 100%;
        overflow: auto;
        border: 1px solid #b9c7d8;
        background: #ffffff;
        scrollbar-gutter: stable;
    }

    .nonptp-table-wrap.is-tall {
        max-height: 68vh;
    }

    .nonptp-table {
        width: 100%;
        min-width: 1180px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        color: #002060;
        font-size: 0.75rem;
        white-space: nowrap;
    }

    .nonptp-table th,
    .nonptp-table td {
        border: 1px solid #d9e1ec;
        padding: 0.42rem 0.55rem;
        vertical-align: middle;
        font-variant-numeric: tabular-nums;
    }

    .nonptp-table th {
        position: sticky;
        top: 0;
        z-index: 5;
        color: #ffffff;
        background: #005b9f;
        border-color: #ffffff !important;
        font-size: 0.68rem;
        font-weight: 900;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .nonptp-table tbody tr:nth-child(even) td {
        background: #f7faff;
    }

    .nonptp-table tbody tr:hover td {
        background: #eaf5ff;
    }

    .nonptp-table .right { text-align: right; }
    .nonptp-table .center { text-align: center; }
    .nonptp-table .strong { font-weight: 850; color: var(--nonptp-ink); }

    .nonptp-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.25rem 0.55rem;
        color: #991b1b;
        background: #fee2e2;
        font-size: 0.68rem;
        font-weight: 900;
    }

    .nonptp-status.is-ptp {
        color: #065f46;
        background: #d1fae5;
    }

    .nonptp-status.is-neutral {
        color: #334155;
        background: #e2e8f0;
    }

    .nonptp-status.is-warning {
        color: #92400e;
        background: #fef3c7;
    }

    .nonptp-status.is-recovery {
        color: #075985;
        background: #dbeafe;
    }

    .nonptp-empty {
        padding: 2.5rem 1rem;
        color: var(--nonptp-muted);
        text-align: center;
        font-weight: 750;
    }

    .nonptp-pager {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.85rem;
        color: var(--nonptp-muted);
        font-size: 0.78rem;
        font-weight: 750;
    }

    .nonptp-pager a,
    .nonptp-pager span.page-current {
        display: inline-flex;
        min-width: 34px;
        min-height: 34px;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--nonptp-border);
        border-radius: 8px;
        padding: 0.35rem 0.55rem;
        background: #ffffff;
        color: var(--nonptp-blue-dark);
        font-weight: 850;
    }

    .nonptp-pager span.page-current {
        background: var(--nonptp-blue);
        color: #ffffff;
        border-color: var(--nonptp-blue);
    }

    @media (max-width: 992px) {
        .nonptp-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 576px) {
        .nonptp-page {
            padding: 1rem 0.75rem;
        }

        .nonptp-kpis {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="nonptp-page">
    <div class="nonptp-shell nonptp-header">
        <div class="nonptp-brand">
            <div class="nonptp-logo"><i class="fas fa-user-clock"></i></div>
            <div>
                <h1 class="nonptp-title">Histori PTP Deb</h1>
                <div class="nonptp-subtitle">
                    Daily Loan Dinamis | Pinjaman Bulanan | {{ $selectedSegmentLabel }} | {{ $selectedBranchLabel }} | Posisi {{ $selectedPeriodLabel }}
                </div>
            </div>
        </div>
        <div class="nonptp-badge">
            <i class="fas fa-calendar-check"></i>
            {{ $selectedPeriodLabel }} vs {{ $comparisonPeriodLabel }}
        </div>
    </div>

    <div class="nonptp-shell nonptp-panel">
        <form method="GET" action="{{ route('report.dashboard-pinjaman.kinerja-non-ptp') }}">
            <div class="row align-items-end">
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label for="periode" class="nonptp-label">Periode</label>
                    <input id="periode" type="date" name="periode" value="{{ $selectedPeriod }}" class="form-control nonptp-control">
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <label for="cabang" class="nonptp-label">Nama Cabang</label>
                    <select id="cabang" name="cabang" class="form-control nonptp-control">
                        @foreach ($branchOptions as $value => $label)
                            <option value="{{ $value }}" @selected($value === $selectedBranch)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                    <label for="segmen" class="nonptp-label">Segmen</label>
                    <select id="segmen" name="segmen" class="form-control nonptp-control">
                        @foreach ($segments as $value => $label)
                            <option value="{{ $value }}" @selected($value === $selectedSegment)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                    <label for="view" class="nonptp-label">Tampilan</label>
                    <select id="view" name="view" class="form-control nonptp-control">
                        @foreach ($viewOptions as $value => $label)
                            <option value="{{ $value }}" @selected($value === $selectedView)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-12">
                    <button type="submit" class="btn nonptp-action w-100">
                        <i class="fas fa-filter mr-1"></i> Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="nonptp-view-tabs mb-3">
        @foreach ($viewOptions as $value => $label)
            <a href="{{ route('report.dashboard-pinjaman.kinerja-non-ptp', array_merge(request()->except(['page', 'view']), ['view' => $value])) }}" class="nonptp-view-tab {{ $selectedView === $value ? 'is-active' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="nonptp-kpis">
        <div class="nonptp-kpi">
            <div class="label">PTP Periode Ini</div>
            <div class="value">{{ $fmtInt($totals['current_ptp_count'] ?? 0) }} rek</div>
            <div class="hint">{{ $fmtCompact($totals['current_ptp_baki'] ?? 0) }} | freq payment = 1</div>
        </div>
        <div class="nonptp-kpi">
            <div class="label">NON PTP Periode Ini</div>
            <div class="value">{{ $fmtInt($totals['current_non_ptp_count'] ?? 0) }} rek</div>
            <div class="hint">{{ $fmtCompact($totals['current_non_ptp_baki'] ?? 0) }} | freq payment = 1</div>
        </div>
        <div class="nonptp-kpi">
            <div class="label">PTP -> NON PTP</div>
            <div class="value">{{ $fmtInt($totals['ptp_to_non_count'] ?? 0) }} rek</div>
            <div class="hint">{{ $fmtCompact($totals['ptp_to_non_baki'] ?? 0) }}</div>
        </div>
        <div class="nonptp-kpi">
            <div class="label">NON PTP -> PTP</div>
            <div class="value">{{ $fmtInt($totals['non_to_ptp_count'] ?? 0) }} rek</div>
            <div class="hint">{{ $fmtCompact($totals['non_to_ptp_baki'] ?? 0) }}</div>
        </div>
    </div>

    @if ($selectedView === 'history')
    <div class="nonptp-shell nonptp-panel">
        <h2 class="nonptp-section-title">Rekap Bulanan Pergeseran PTP</h2>
        @if (!$isReady)
            <div class="nonptp-empty">Data belum siap. Pastikan tabel daily_loan_dinamis dan periode pembanding tersedia.</div>
        @elseif ($monthlyRecap->isEmpty())
            <div class="nonptp-empty">Tidak ada histori bulanan untuk filter ini.</div>
        @else
            <div class="nonptp-table-wrap">
                <table class="nonptp-table" style="min-width:1280px;">
                    <thead>
                        <tr>
                            <th rowspan="2">Kantor Cabang</th>
                            <th rowspan="2">Periode</th>
                            <th colspan="2">PTP Bln Sebelumnya</th>
                            <th colspan="2">PTP Periode</th>
                            <th colspan="2">PTP -> NON PTP</th>
                            <th colspan="2">NON PTP -> PTP</th>
                        </tr>
                        <tr>
                            <th>Debitur</th>
                            <th>Baki Debet</th>
                            <th>Debitur</th>
                            <th>Baki Debet</th>
                            <th>Debitur</th>
                            <th>Baki Debet</th>
                            <th>Debitur</th>
                            <th>Baki Debet</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthlyRecap as $row)
                            <tr>
                                <td class="strong">{{ $row->branch_label }}</td>
                                <td class="center">
                                    <div class="strong">{{ $fmtDate($row->period) }}</div>
                                    <div class="text-muted" style="font-size:0.68rem;">vs {{ $fmtDate($row->comparison_period) }}</div>
                                </td>
                                <td class="right strong">{{ $fmtInt($row->previous_ptp_count) }}</td>
                                <td class="right">{{ $fmtCompact($row->previous_ptp_baki) }}</td>
                                <td class="right strong">{{ $fmtInt($row->current_ptp_count) }}</td>
                                <td class="right">{{ $fmtCompact($row->current_ptp_baki) }}</td>
                                <td class="right strong text-danger">{{ $fmtInt($row->ptp_to_non_count) }}</td>
                                <td class="right text-danger font-weight-bold">{{ $fmtCompact($row->ptp_to_non_baki) }}</td>
                                <td class="right strong text-success">{{ $fmtInt($row->non_to_ptp_count) }}</td>
                                <td class="right text-success font-weight-bold">{{ $fmtCompact($row->non_to_ptp_baki) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endif

    @if ($selectedView === 'rekap')
    <div class="nonptp-shell nonptp-panel">
        <h2 class="nonptp-section-title">Rekap {{ $summaryDimensionLabel }}</h2>
        @if (!$isReady)
            <div class="nonptp-empty">Data belum siap. Pastikan tabel daily_loan_dinamis dan periode pembanding tersedia.</div>
        @elseif ($summary->isEmpty())
            <div class="nonptp-empty">Tidak ada histori PTP untuk filter ini.</div>
        @else
            <div class="nonptp-table-wrap">
                <table class="nonptp-table" style="min-width:1180px;">
                    <thead>
                        <tr>
                            <th>{{ $summaryDimensionLabel }}</th>
                            <th>Total Debitur</th>
                            <th>Total Baki Debet</th>
                            <th>PTP Bln Sebelumnya</th>
                            <th>PTP Periode Ini</th>
                            <th>NON PTP Periode Ini</th>
                            <th>PTP -> NON PTP</th>
                            <th>NON PTP -> PTP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            <tr>
                                <td class="strong">{{ $row->dimension_label }}</td>
                                <td class="right strong">{{ $fmtInt($row->rekening_count) }}</td>
                                <td class="right strong">{{ $fmtCompact($row->baki_debet_total) }}</td>
                                <td class="right">{{ $fmtInt($row->previous_ptp_count) }}<br><span class="text-muted">{{ $fmtCompact($row->previous_ptp_baki) }}</span></td>
                                <td class="right text-success font-weight-bold">{{ $fmtInt($row->current_ptp_count) }}<br><span>{{ $fmtCompact($row->current_ptp_baki) }}</span></td>
                                <td class="right text-danger font-weight-bold">{{ $fmtInt($row->current_non_ptp_count) }}<br><span>{{ $fmtCompact($row->current_non_ptp_baki) }}</span></td>
                                <td class="right text-danger font-weight-bold">{{ $fmtInt($row->ptp_to_non_count) }}<br><span>{{ $fmtCompact($row->ptp_to_non_baki) }}</span></td>
                                <td class="right text-success font-weight-bold">{{ $fmtInt($row->non_to_ptp_count) }}<br><span>{{ $fmtCompact($row->non_to_ptp_baki) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endif

    @if ($selectedView === 'nominatif')
    <div class="nonptp-shell nonptp-panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h2 class="nonptp-section-title mb-0">Nominatif Histori PTP Deb</h2>
            <div class="text-muted small font-weight-bold">
                {{ $fmtInt($totals['rekening_count'] ?? 0) }} baris | halaman {{ $rows->currentPage() }}
            </div>
        </div>

        @if (!$isReady)
            <div class="nonptp-empty">Data belum siap untuk ditampilkan.</div>
        @elseif ($rows->isEmpty())
            <div class="nonptp-empty">Tidak ada nominatif histori PTP untuk filter ini.</div>
        @else
            <div class="nonptp-table-wrap is-tall">
                <table class="nonptp-table" style="min-width:1280px;">
                    <thead>
                        <tr>
                            <th>Cabang / Unit</th>
                            <th>Rekening / Debitur</th>
                            <th>Baki Debet</th>
                            <th>Pola</th>
                            <th>Bayar vs Tagihan</th>
                            <th>Status Bln Sebelumnya</th>
                            <th>Status Periode Ini</th>
                            <th>Transisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <div class="strong">{{ $row->cabang1 }}</div>
                                    <div class="text-muted" style="font-size:0.68rem;">{{ $row->unit1 }}</div>
                                </td>
                                <td>
                                    <div class="strong">{{ $row->nomor_rekening }}</div>
                                    <div class="text-muted" style="font-size:0.68rem;">{{ $row->nama_debitur1 }}</div>
                                </td>
                                <td class="right strong">{{ $fmtMoney($row->baki_debet1) }}</td>
                                <td class="center">
                                    <div class="strong">{{ $row->pola_angsuran }}</div>
                                    <div class="text-muted" style="font-size:0.68rem;">Kolek {{ $row->kolek }}</div>
                                </td>
                                <td class="center">
                                    <div class="strong">Bayar {{ $fmtDate($row->tgl_bayar_terakhir) }}</div>
                                    <div class="text-muted" style="font-size:0.68rem;">Tagihan {{ $fmtDate($row->tanggal_bayar_seharusnya) }}</div>
                                </td>
                                <td class="center">
                                    <span class="nonptp-status {{ $row->status_bulan_sebelumnya === 'PTP' ? 'is-ptp' : ($row->status_bulan_sebelumnya === 'NON PTP' ? '' : 'is-neutral') }}">
                                        {{ $row->status_bulan_sebelumnya }}
                                    </span>
                                    <div class="text-muted" style="font-size:0.68rem;">
                                        Tgl bayar terakhir {{ $fmtDate($row->tgl_bayar_terakhir_sebelumnya) }}<br>
                                        NPD {{ $fmtDate($row->npd_npid_sebelumnya) }}
                                    </div>
                                </td>
                                <td class="center">
                                    <span class="nonptp-status {{ $row->status_periode_ini === 'PTP' ? 'is-ptp' : '' }}">
                                        {{ $row->status_periode_ini }}
                                    </span>
                                    <div class="text-muted" style="font-size:0.68rem;">{{ $row->keterangan_excel }}</div>
                                </td>
                                <td class="center">
                                    @php
                                        $transitionClass = match ($row->keterangan) {
                                            'PTP -> NON PTP' => 'is-warning',
                                            'NON PTP -> PTP' => 'is-recovery',
                                            'Tetap PTP' => 'is-ptp',
                                            default => 'is-neutral',
                                        };
                                    @endphp
                                    <span class="nonptp-status {{ $transitionClass }}">{{ $row->keterangan }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="nonptp-pager">
                <div>
                    Menampilkan {{ $fmtInt($rows->firstItem()) }} - {{ $fmtInt($rows->lastItem()) }} dari {{ $fmtInt($totals['rekening_count'] ?? 0) }} baris.
                </div>
                <div class="d-flex align-items-center gap-1">
                    @if ($rows->onFirstPage())
                        <span class="page-current" style="background:#f1f5f9;color:#94a3b8;border-color:#dbe4ef;">&lt;</span>
                    @else
                        <a href="{{ $rows->previousPageUrl() }}">&lt;</a>
                    @endif

                    <span class="page-current">{{ $rows->currentPage() }}</span>

                    @if ($rows->hasMorePages())
                        <a href="{{ $rows->nextPageUrl() }}">&gt;</a>
                    @else
                        <span class="page-current" style="background:#f1f5f9;color:#94a3b8;border-color:#dbe4ef;">&gt;</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
    @endif
</div>
@endsection
