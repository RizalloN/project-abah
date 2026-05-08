@extends('layouts.admin')

@section('title', 'Kinerja PTP')

@section('content')
<style>
    .ptp-page {
        padding: 1.25rem 1rem 1.75rem;
        color: #0f172a;
    }

    .ptp-header {
        margin-bottom: 1rem;
        padding: 1.1rem 1.25rem;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 22px -18px rgba(15, 23, 42, .3);
    }

    .ptp-title {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .ptp-subtitle {
        margin-top: .25rem;
        color: #64748b;
        font-size: .85rem;
    }

    .ptp-panel {
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 22px -18px rgba(15, 23, 42, .26);
    }

    .ptp-panel-body {
        padding: 1rem;
    }

    .ptp-filter-label {
        display: block;
        margin-bottom: .35rem;
        color: #475569;
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .ptp-filter-control {
        min-height: 42px;
        border-color: #cbd5e1;
        border-radius: 7px;
        font-size: .88rem;
    }

    .ptp-action {
        min-height: 42px;
        border-radius: 7px;
        font-weight: 800;
    }

    .ptp-table-wrap {
        width: 100%;
        overflow: auto;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #f8fafc;
    }

    .ptp-table {
        width: 100%;
        min-width: 1500px;
        border-collapse: collapse;
        margin: 0;
        font-size: .78rem;
        white-space: nowrap;
    }

    .ptp-table th,
    .ptp-table td {
        border-right: 1px solid #d8dee8;
        border-bottom: 1px solid #d8dee8;
        padding: .38rem .5rem;
        vertical-align: middle;
    }

    .ptp-table th {
        text-align: center;
        color: #ffffff;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .ptp-table td {
        background: #ffffff;
        color: #111827;
        font-variant-numeric: tabular-nums;
    }

    .ptp-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .ptp-table tbody tr:hover td {
        background: #eef6ff;
    }

    .ptp-head-blue {
        background: #082c6c;
    }

    .ptp-head-blue-sub {
        background: #0c3478;
    }

    .ptp-head-orange {
        background: #d85a08;
    }

    .ptp-head-orange-sub {
        background: #c94f06;
    }

    .ptp-head-yellow {
        background: #fff200;
        color: #111827 !important;
    }

    .ptp-head-success {
        background: #c75308;
    }

    .ptp-left {
        text-align: left;
    }

    .ptp-right {
        text-align: right;
    }

    .ptp-center {
        text-align: center;
    }

    .ptp-total-row td {
        background: #fff7d6 !important;
        font-weight: 900;
    }

    .ptp-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 768px) {
        .ptp-page {
            padding-inline: .75rem;
        }

        .ptp-title {
            font-size: 1.2rem;
        }
    }
</style>

@php
    $dimensionHeaders = match ($selectedLevel) {
        'per_uker' => ['bo' => 'BO', 'bc' => 'BC', 'mbm' => 'MBM', 'uker' => 'UKER'],
        'per_mantri' => ['bo' => 'BO', 'mbm' => 'MBM', 'bc' => 'BC', 'uker' => 'UKER', 'mantri' => 'MANTRI'],
        default => ['bo' => 'BO', 'mbm' => 'MBM'],
    };
@endphp

<div class="ptp-page">
    <div class="ptp-header">
        <h1 class="ptp-title">Kinerja PTP</h1>
        <div class="ptp-subtitle">{{ $reportConfig['label'] }} | {{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }} | Posisi {{ $selectedPeriodLabel }}</div>
    </div>

    <div class="ptp-panel mb-3">
        <div class="ptp-panel-body">
            <form method="GET" action="{{ route('report.dashboard-pinjaman.kinerja-ptp') }}">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="jenis">Jenis PTP</label>
                        <select id="jenis" name="jenis" class="form-control ptp-filter-control">
                            @foreach ($reportTypes as $key => $label)
                                <option value="{{ $key }}" @selected($key === $selectedReportType)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="level">Level Kinerja</label>
                        <select id="level" name="level" class="form-control ptp-filter-control">
                            @foreach ($levels as $key => $label)
                                <option value="{{ $key }}" @selected($key === $selectedLevel)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                        <label class="ptp-filter-label" for="periode">Periode</label>
                        <select id="periode" name="periode" class="form-control ptp-filter-control">
                            @forelse ($availablePeriods as $period)
                                <option value="{{ $period }}" @selected($period === $selectedPeriod)>{{ \Carbon\Carbon::parse($period)->locale('id')->translatedFormat('d F Y') }}</option>
                            @empty
                                <option value="">Tidak ada data</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary ptp-action w-100">
                            <i class="fas fa-filter mr-2"></i>Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="ptp-panel">
        <div class="ptp-panel-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div class="font-weight-bold">{{ $levels[$selectedLevel] ?? 'Kinerja per MBM' }}</div>
                <div class="text-muted small">{{ $formatCount($rows->count()) }} baris</div>
            </div>

            @if ($rows->isEmpty())
                <div class="ptp-empty">Data belum tersedia untuk pilihan ini.</div>
            @else
                <div class="ptp-table-wrap">
                    <table class="ptp-table">
                        <thead>
                            <tr>
                                @foreach ($dimensionHeaders as $label)
                                    <th rowspan="3" class="ptp-head-blue">{{ $label }}</th>
                                @endforeach
                                <th colspan="9" class="ptp-head-blue">{{ $reportConfig['total_heading'] }}</th>
                                <th colspan="4" class="ptp-head-orange">NPD Billing Sudah Muncul</th>
                                <th rowspan="3" class="ptp-head-success">Success<br>Rate</th>
                                <th colspan="2" class="ptp-head-yellow">Today</th>
                            </tr>
                            <tr>
                                <th colspan="3" class="ptp-head-blue-sub">Total</th>
                                <th colspan="3" class="ptp-head-blue-sub">Sudah Muncul Billing</th>
                                <th colspan="3" class="ptp-head-blue-sub">Belum Muncul</th>
                                <th colspan="2" class="ptp-head-orange-sub">Sudah Bayar</th>
                                <th colspan="2" class="ptp-head-orange-sub">Belum Bayar</th>
                                <th colspan="2" class="ptp-head-yellow">&nbsp;</th>
                            </tr>
                            <tr>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-blue-sub">Rek</th>
                                <th class="ptp-head-blue-sub">Rupiah</th>
                                <th class="ptp-head-blue-sub">Run Off</th>
                                <th class="ptp-head-orange-sub">Rek</th>
                                <th class="ptp-head-orange-sub">Rupiah</th>
                                <th class="ptp-head-orange-sub">Rek</th>
                                <th class="ptp-head-orange-sub">Rupiah</th>
                                <th class="ptp-head-yellow">Rek</th>
                                <th class="ptp-head-yellow">Rupiah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach (array_keys($dimensionHeaders) as $key)
                                        <td class="ptp-left">{{ $row[$key] ?? '-' }}</td>
                                    @endforeach
                                    <td class="ptp-right">{{ $formatCount($row['total_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['total_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['total_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['sudah_billing_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['sudah_billing_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['sudah_billing_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['belum_muncul_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['belum_muncul_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['belum_muncul_runoff'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['sudah_bayar_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['sudah_bayar_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['belum_bayar_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['belum_bayar_rupiah'] ?? 0) }}</td>
                                    <td class="ptp-center">{{ $formatPercent($row['success_rate'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatCount($row['today_rek'] ?? 0) }}</td>
                                    <td class="ptp-right">{{ $formatJuta($row['today_rupiah'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            <tr class="ptp-total-row">
                                <td colspan="{{ count($dimensionHeaders) }}" class="ptp-left">TOTAL</td>
                                <td class="ptp-right">{{ $formatCount($total['total_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['total_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['total_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['sudah_billing_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_billing_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_billing_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['belum_muncul_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_muncul_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_muncul_runoff'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['sudah_bayar_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['sudah_bayar_rupiah'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['belum_bayar_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['belum_bayar_rupiah'] ?? 0) }}</td>
                                <td class="ptp-center">{{ $formatPercent($total['success_rate'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatCount($total['today_rek'] ?? 0) }}</td>
                                <td class="ptp-right">{{ $formatJuta($total['today_rupiah'] ?? 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
