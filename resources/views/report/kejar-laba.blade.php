@extends('layouts.admin')

@section('title', 'Report Kejar Laba')

@section('content')
<style>
    :root {
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --surface-color: #ffffff;
        --bg-color: #f8fafc;
        --border-color: #e2e8f0;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --table-header-bg: var(--primary-blue-dark);
        --table-header-text: #ffffff;
        --accent-color: #f59e0b;
    }

    .kejar-laba-wrapper {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
        padding-top: 0.75rem;
        padding-bottom: 2rem;
    }

    .kejar-laba-card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        margin-bottom: 1.5rem;
    }

    .kejar-laba-card-header {
        padding: 1.5rem 1.75rem;
        background: linear-gradient(to right, #f8fafc, #ffffff);
        border-bottom: 1px solid var(--border-color);
    }

    .kejar-laba-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary-blue-dark);
        letter-spacing: -0.02em;
    }

    .kejar-laba-subtitle {
        margin-top: 0.35rem;
        color: var(--text-muted);
        font-size: 0.92rem;
    }

    .filter-section {
        padding: 1.75rem 1.5rem;
        background: #ffffff;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .filter-container {
        display: flex;
        align-items: flex-end;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .filter-label {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .select-custom {
        border-radius: 10px;
        border: 1px solid var(--border-color);
        padding: 0.6rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-main);
        background-color: #f9fafb;
        min-width: 220px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .select-custom:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1);
        outline: none;
    }

    .summary-badge {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        padding: 0.6rem 1.25rem;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 200px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .summary-value {
        font-size: 1.1rem;
        font-weight: 800;
        color: #166534;
        line-height: 1;
    }

    .summary-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: #15803d;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 0.2rem;
    }

    .btn-apply {
        background: var(--primary-blue);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.7rem 1.75rem;
        font-weight: 700;
        font-size: 0.95rem;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.2);
    }

    .btn-apply:hover {
        background: var(--primary-blue-dark);
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(30, 64, 175, 0.3);
    }

    .kejar-laba-table-shell {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .kejar-laba-table-shell::-webkit-scrollbar {
        height: 10px;
    }

    .kejar-laba-table-shell::-webkit-scrollbar-track {
        background: transparent;
    }

    .kejar-laba-table-shell::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }

    .kejar-laba-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 0;
        background: #ffffff;
    }

    .kejar-laba-table th,
    .kejar-laba-table td {
        padding: 0.85rem 1.1rem;
        white-space: nowrap;
        vertical-align: middle;
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
    }

    .kejar-laba-table thead th {
        background: var(--table-header-bg);
        color: var(--table-header-text);
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.06em;
        text-align: center;
        font-weight: 800;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .kejar-laba-table thead tr:nth-child(2) th {
        background: #274bba !important;
        padding: 0.55rem 0.75rem;
    }

    .kejar-laba-table tbody td {
        font-size: 0.82rem;
        background: #ffffff;
        font-variant-numeric: tabular-nums;
    }

    .kejar-laba-table tbody tr:nth-child(even) td {
        background: #fafbfd;
    }

    .kejar-laba-table tbody tr:hover td {
        background: #f1f5f9;
    }

    /* Fixed Headers and Columns Color Fix */
    .kejar-laba-table th.sticky-col {
        background: var(--table-header-bg) !important;
        z-index: 20;
    }
    
    .kejar-laba-table td.sticky-col {
        background: #ffffff !important;
        z-index: 5;
    }
    
    .kejar-laba-table tr:nth-child(even) td.sticky-col {
        background: #fafbfd !important;
    }

    .sticky-col {
        position: sticky;
        left: 0;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-weight-bold { font-weight: 700; }
    
    .negative-value { color: #dc2626; font-weight: 700; }
    .positive-value { color: #15803d; font-weight: 700; }
    .zero-value { color: var(--text-muted); opacity: 0.5; }

    .currency-symbol { font-size: 0.65rem; margin-right: 2px; color: var(--text-muted); font-weight: normal; }
</style>

<div class="kejar-laba-wrapper pt-4">
    <div class="kejar-laba-card">
        <div class="kejar-laba-card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-none">
                <h1 class="kejar-laba-title">Kejar Laba Report</h1>
                <div class="kejar-laba-subtitle">Monitoring real-time pencapaian Recovery vs Target RKA.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-info px-3 py-2" style="border-radius: 20px; font-weight: 800; background: #eff6ff; color: var(--primary-blue); border: 1px solid #dbeafe;">
                    <i class="fas fa-calendar-check mr-1"></i> Data per: {{ $selectedPeriodLabel }}
                </span>
            </div>
        </div>

        <div class="filter-section">
            <form action="{{ route('report.dashboard-pinjaman.kejar-laba') }}" method="GET" class="filter-container">
                <div class="filter-item">
                    <label class="filter-label">Periode Terakhir</label>
                    <select name="periode" class="select-custom">
                        @foreach($availablePeriods as $period)
                            <option value="{{ $period }}" {{ $selectedPeriod === $period ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($period)->translatedFormat('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item">
                    <label class="filter-label">Posisi RKA</label>
                    <select name="rka_period" class="select-custom">
                        @foreach($posisi_rka_options as $opt)
                            <option value="{{ $opt['value'] }}" {{ (isset($selectedRka) && $selectedRka === $opt['value']) ? 'selected' : '' }}>
                                {{ $opt['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item">
                    <button type="submit" class="btn-apply">
                        <i class="fas fa-sync-alt mr-2"></i> Tampilkan Data
                    </button>
                </div>
            </form>
        </div>

        <div class="kejar-laba-table-shell">
            <table class="kejar-laba-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky-col">No</th>
                        <th rowspan="2" class="sticky-col" style="left: 64px;">Kanca</th>
                        <th rowspan="2">BUC</th>
                        <th rowspan="2">Unit</th>
                        <th colspan="4">Recovery (M-1)</th>
                        <th colspan="4">Recovery ({{ \Carbon\Carbon::parse($selectedPeriod)->translatedFormat('d M Y') }})</th>
                        <th colspan="4">RKA (Target)</th>
                        <th colspan="4">Delta (MtD vs RKA)</th>
                    </tr>
                    <tr>
                        <!-- Recovery M-1 -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                        <!-- Recovery Curr -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                        <!-- RKA -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                        <!-- Delta -->
                        <th>Micro</th><th>Small</th><th>Consumer</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="text-center sticky-col">{{ $row['no'] }}</td>
                            <td class="sticky-col" style="left: 64px; font-weight: 700; color: var(--primary-blue-dark);">{{ $row['kanca'] }}</td>
                            <td class="text-center" style="font-weight: 600;">{{ $row['buc'] }}</td>
                            <td style="min-width: 210px; font-weight: 600;">{{ $row['unit'] }}</td>
                            
                            {{-- Recovery M-1 --}}
                            @include('report.partials.kejar-laba-metrics', ['metrics' => $row['recovery_m1']])
                            
                            {{-- Recovery Current --}}
                            @include('report.partials.kejar-laba-metrics', ['metrics' => $row['recovery_curr']])
                            
                            {{-- RKA --}}
                            @include('report.partials.kejar-laba-metrics', ['metrics' => $row['rka']])
                            
                            {{-- Delta --}}
                            @foreach(['micro', 'small', 'consumer', 'total'] as $seg)
                                <td class="text-right {{ $row['delta'][$seg] < 0 ? 'negative-value' : ($row['delta'][$seg] > 0 ? 'positive-value' : 'zero-value') }}">
                                    @if($row['delta'][$seg] != 0)
                                        <span class="currency-symbol">Rp</span>{{ number_format($row['delta'][$seg], 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="20" class="text-center py-5 text-muted">
                                <i class="fas fa-info-circle mr-2"></i> Tidak ada data untuk periode yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
