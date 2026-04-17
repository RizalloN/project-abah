@extends('layouts.admin')

@section('title', 'Program Referral Partner Perusahaan Anak')

@php
    use Carbon\Carbon;

    $positionLabels = collect($positions)->mapWithKeys(function ($position) {
        return [$position => Carbon::parse($position)->translatedFormat('d F Y')];
    });
@endphp

@section('content')
<style>
    :root {
        --primary-blue: #1e40af; /* blue-800 */
        --primary-blue-light: #3b82f6; /* blue-500 */
        --primary-blue-dark: #1e3a8a; /* blue-900 */
        --surface-color: #ffffff;
        --bg-color: #f8fafc; /* slate-50 */
        --border-color: #e2e8f0; /* slate-200 */
        --text-main: #0f172a; /* slate-900 */
        --text-muted: #64748b; /* slate-500 */
        --table-header-bg: var(--primary-blue-dark);
        --table-header-text: #ffffff;
    }

    .report-wrapper {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    /* Cards */
    .report-card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        overflow: hidden;
        transition: box-shadow 0.3s ease;
    }

    .report-card-header {
        background: linear-gradient(to right, #f8fafc, #ffffff);
        border-bottom: 1px solid var(--border-color);
        padding: 1.25rem 1.5rem;
    }

    .report-card-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Form Controls */
    .report-filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
        display: block;
    }

    .report-input {
        border-radius: 8px;
        border: 1px solid var(--border-color);
        min-height: 42px;
        padding: 0.5rem 1rem;
        font-weight: 500;
        color: var(--text-main);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background-color: var(--surface-color);
    }

    .report-input:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        outline: none;
    }

    .report-input:disabled {
        background-color: #f1f5f9;
        color: var(--text-muted);
    }

    .input-group-text {
        border-color: var(--border-color);
        background-color: #f8fafc;
        border-radius: 8px 0 0 8px;
    }
    
    .input-group > .report-input {
        border-radius: 0 8px 8px 0;
    }

    /* Table Wrapper */
    .table-container {
        width: 100%;
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .table-container::-webkit-scrollbar {
        height: 8px;
    }
    .table-container::-webkit-scrollbar-track {
        background: transparent;
    }
    .table-container::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }

    .table-report {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        background: #ffffff;
    }

    .table-report th, .table-report td {
        padding: 0.75rem 1rem;
        vertical-align: middle;
        white-space: nowrap;
        border-bottom: 1px solid var(--border-color);
        border-right: 1px solid var(--border-color);
    }

    .table-report th:last-child, .table-report td:last-child {
        border-right: none;
    }

    /* Table Headers */
    .bg-header-main {
        background: var(--table-header-bg) !important;
        color: var(--table-header-text) !important;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        text-align: center;
        border-bottom: 2px solid rgba(0,0,0,0.1) !important;
        border-right: 1px solid rgba(255,255,255,0.1) !important;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .bg-header-sub {
        background: #274bba !important; /* Lighter blue */
        color: var(--table-header-text) !important;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 0.05em;
        text-align: center;
        border-bottom: 2px solid rgba(0,0,0,0.1) !important;
        border-right: 1px solid rgba(255,255,255,0.1) !important;
        position: sticky;
        top: 41px; /* Adjust based on header height */
        z-index: 9;
    }

    /* Sticky First Column */
    .table-report .sticky-col {
        position: sticky;
        left: 0;
        background: #ffffff;
        z-index: 8;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        font-weight: 600;
        text-align: left;
    }

    .table-report thead .sticky-col {
        background: var(--table-header-bg) !important;
        z-index: 11;
        box-shadow: none;
    }

    /* Table Cells */
    .table-report tbody td {
        font-size: 0.8rem;
        color: var(--text-main);
        text-align: right;
        font-variant-numeric: tabular-nums;
        transition: background-color 0.15s ease;
    }

    .table-report tbody td.text-left {
        text-align: left;
    }

    /* Row Hover */
    .table-report tbody tr:hover td {
        background-color: #f1f5f9;
    }

    .table-report tbody tr:hover .sticky-col {
        background-color: #f1f5f9;
    }

    /* Total Row */
    .row-total td {
        background: #e0e7ff !important; /* blue-100 */
        color: var(--primary-blue-dark) !important;
        font-weight: 700;
        border-top: 2px solid var(--primary-blue-light) !important;
    }
    
    .table-report tbody tr.row-total:hover td {
        background: #dbeafe !important;
    }

    /* Metrics */
    .metric-positive { color: #16a34a !important; font-weight: 700; } /* green-600 */
    .metric-negative { color: #dc2626 !important; font-weight: 700; } /* red-600 */
    .metric-neutral { color: var(--text-muted) !important; font-weight: 600; }
</style>
@include('report._bri-report-ui')

<div class="report-wrapper">
    <div class="report-card mb-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('report.kolaborasi.referral') }}">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="form-group mb-0">
                            <label class="report-filter-label">
                                Posisi Terakhir
                                <i class="fas fa-calendar-alt text-primary ml-1"></i>
                            </label>
                            <input
                                type="date"
                                name="posisi_terakhir"
                                class="form-control report-input"
                                value="{{ $selectedDate }}"
                                onchange="this.form.submit()"
                            >
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="form-group mb-0">
                            <label class="report-filter-label">Sumber Data</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text border-right-0"><i class="fas fa-database text-muted"></i></span>
                                </div>
                                <input type="text" class="form-control report-input border-left-0 pl-0" value="input_rekanan + simpanan_multipn" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="report-filter-label">Jumlah Data Match</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text border-right-0"><i class="fas fa-check-circle text-success"></i></span>
                                </div>
                                <input type="text" class="form-control report-input font-weight-bold text-success border-left-0 pl-0" value="{{ number_format($matchedCount, 0, ',', '.') }}" disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="report-card mb-4">
        <div class="report-card-header">
            <h3 class="report-card-title">
                <i class="fas fa-handshake text-primary"></i>
                Kolaborasi Perusahaan Anak
            </h3>
        </div>

        <div class="card-body p-4 bg-white">
            <div class="table-container">
                <table class="table table-report m-0">
                    <thead>
                        <tr>
                            <th rowspan="2" class="bg-header-main sticky-col align-middle">Branch Office</th>
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
                                <td class="sticky-col text-left">{{ $row['regional'] }}</td>
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
                                <td colspan="{{ 4 + (count($positions) * 3) }}" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block text-black-50"></i>
                                    Belum ada baris regional yang bisa ditampilkan.
                                </td>
                            </tr>
                        @endforelse

                        @if(!empty($tableRows))
                        <tr class="row-total">
                            <td class="sticky-col text-left">Total</td>
                            <td>{{ number_format($grandTotals['total_pipeline'], 0, ',', '.') }}</td>
                            @foreach($positions as $position)
                                <td>{{ number_format($grandTotals['positions'][$position]['belum_terakuisisi'] ?? 0, 0, ',', '.') }}</td>
                                <td>{{ number_format($grandTotals['positions'][$position]['sudah_terakuisisi'] ?? 0, 0, ',', '.') }}</td>
                                <td>{{ number_format((float) ($grandTotals['positions'][$position]['saldo_cif'] ?? 0), 0, ',', '.') }}</td>
                            @endforeach
                            <td>{{ number_format($grandTotals['akuisisi_pct'], 2, ',', '.') }}%</td>
                            <td>{{ number_format($grandTotals['growth_saldo_pct'], 2, ',', '.') }}%</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
