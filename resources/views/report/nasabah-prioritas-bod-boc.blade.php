@extends('layouts.admin')

@section('title', 'Nasabah Prioritas BOD/BOC')

@php
    use Carbon\Carbon;

    $positionLabels = collect($positions)->mapWithKeys(function ($position) {
        return [$position => Carbon::parse($position)->translatedFormat('d F Y')];
    });
@endphp

@section('content')
<style>
    .report-filter-card,
    .report-data-card {
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08) !important;
    }

    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: #ffffff;
    }

    .report-filter-card .form-control {
        border-radius: 10px;
        min-height: 40px;
    }

    .report-filter-label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.6rem;
    }

    .report-filter-date {
        border: 1px solid #cbd5e1;
        border-radius: 18px;
        min-height: 54px;
        padding: 0.85rem 1rem;
        font-size: 1rem;
        color: #334155;
        box-shadow: none;
    }

    .table-container {
        width: 100%;
        overflow-x: auto;
    }

    .table-report {
        border-collapse: collapse;
        width: 100%;
        table-layout: auto;
        min-width: 1120px;
    }

    .table-report th,
    .table-report td {
        vertical-align: middle !important;
        border: 1px solid #1f2937;
        white-space: nowrap;
    }

    .table-report th {
        padding: 10px 6px;
        text-align: center;
        font-size: 0.8rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .table-report td {
        padding: 8px 6px;
        text-align: right;
        font-size: 0.8rem;
    }

    .table-report td.text-left {
        text-align: left;
    }

    .bg-header-main {
        background: #355fb3 !important;
        color: #ffffff !important;
        border-color: #27498a !important;
    }

    .bg-header-sub {
        background: #edf2fb !important;
        color: #334155 !important;
        border-color: #cbd5e1 !important;
    }

    .row-total {
        background: #123f73 !important;
        color: #ffffff !important;
        font-weight: 700;
    }

    .row-total td {
        color: #ffffff !important;
        border-color: #27498a !important;
    }

    .metric-positive {
        color: #166534;
        font-weight: 700;
    }

    .metric-negative {
        color: #b91c1c;
        font-weight: 700;
    }

    .metric-neutral {
        color: #1f2937;
        font-weight: 700;
    }
</style>

<div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('report.kolaborasi.bodboc') }}">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="report-filter-label">
                            Posisi Terakhir
                            <i class="fas fa-edit text-primary"></i>
                        </label>
                        <input
                            type="date"
                            name="posisi_terakhir"
                            class="form-control report-filter-date"
                            value="{{ $selectedDate }}"
                            onchange="this.form.submit()"
                        >
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="text-muted text-sm mb-1">Sumber Data</label>
                        <input type="text" class="form-control" value="{{ $sourceLabel }}" disabled>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="text-muted text-sm mb-1">Jumlah Data Match</label>
                        <input type="text" class="form-control font-weight-bold" value="{{ number_format($matchedCount, 0, ',', '.') }}" disabled>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 report-data-card">
    <div class="card-header bg-white border-bottom">
        <h3 class="card-title font-weight-bold text-primary mb-0">
            <i class="fas fa-user-tie mr-2"></i>{{ $pageTitle }}
        </h3>
    </div>

    <div class="card-body">
        <div class="table-container">
            <table class="table table-report table-hover m-0">
                <thead>
                    <tr>
                        <th rowspan="2" class="bg-header-main align-middle">Branch Office</th>
                        <th rowspan="2" class="bg-header-main align-middle">Total<br>Pipeline</th>
                        @foreach($positions as $position)
                            <th colspan="3" class="bg-header-main">{{ $positionLabels[$position] }}</th>
                        @endforeach
                        <th rowspan="2" class="bg-header-main align-middle">Akuisisi %</th>
                        <th rowspan="2" class="bg-header-main align-middle">% Growth Saldo</th>
                    </tr>
                    <tr>
                        @foreach($positions as $position)
                            <th class="bg-header-sub">Belum<br>Terakuisisi</th>
                            <th class="bg-header-sub">Sudah<br>Terakuisisi</th>
                            <th class="bg-header-sub">Saldo CIF</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($tableRows as $row)
                        <tr>
                            <td class="text-left">{{ $row['regional'] }}</td>
                            <td>{{ number_format($row['total_pipeline'], 0, ',', '.') }}</td>
                            @foreach($positions as $position)
                                <td>{{ number_format($row['positions'][$position]['belum_terakuisisi'] ?? 0, 0, ',', '.') }}</td>
                                <td>{{ number_format($row['positions'][$position]['sudah_terakuisisi'] ?? 0, 0, ',', '.') }}</td>
                                <td>{{ number_format((float) ($row['positions'][$position]['saldo_cif'] ?? 0), 0, ',', '.') }}</td>
                            @endforeach
                            <td class="{{ $row['akuisisi_pct'] > 0 ? 'metric-positive' : 'metric-neutral' }}">{{ number_format($row['akuisisi_pct'], 2, ',', '.') }}%</td>
                            <td class="{{ $row['growth_saldo_pct'] > 0 ? 'metric-positive' : ($row['growth_saldo_pct'] < 0 ? 'metric-negative' : 'metric-neutral') }}">{{ number_format($row['growth_saldo_pct'], 2, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + (count($positions) * 3) }}" class="text-center text-muted py-4">Belum ada baris regional yang bisa ditampilkan.</td>
                        </tr>
                    @endforelse

                    <tr class="row-total">
                        <td class="text-left">Total</td>
                        <td>{{ number_format($grandTotals['total_pipeline'], 0, ',', '.') }}</td>
                        @foreach($positions as $position)
                            <td>{{ number_format($grandTotals['positions'][$position]['belum_terakuisisi'] ?? 0, 0, ',', '.') }}</td>
                            <td>{{ number_format($grandTotals['positions'][$position]['sudah_terakuisisi'] ?? 0, 0, ',', '.') }}</td>
                            <td>{{ number_format((float) ($grandTotals['positions'][$position]['saldo_cif'] ?? 0), 0, ',', '.') }}</td>
                        @endforeach
                        <td>{{ number_format($grandTotals['akuisisi_pct'], 2, ',', '.') }}%</td>
                        <td>{{ number_format($grandTotals['growth_saldo_pct'], 2, ',', '.') }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
