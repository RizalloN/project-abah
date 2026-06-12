@extends('layouts.admin')

@section('title', 'Kinerja RM Mikro')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');

    /* Font override for clean look */
    .loan-dashboard,
    .loan-dashboard select,
    .loan-dashboard button,
    .loan-dashboard table,
    .loan-dashboard input {
        font-family: 'Plus Jakarta Sans', -apple-system, sans-serif !important;
    }

    .rm-mikro-hero {
        position: relative;
        margin-bottom: 1.5rem;
        padding: 2.25rem 2rem;
        border-radius: 16px;
        background: linear-gradient(135deg, #042a5f 0%, #0857c3 100%);
        color: #ffffff;
        box-shadow: 0 12px 30px -15px rgba(4, 42, 95, 0.45);
        overflow: hidden;
    }

    .rm-mikro-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -20%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, rgba(113, 197, 232, 0.15) 0%, transparent 60%);
        pointer-events: none;
    }

    .rm-mikro-hero h1 {
        margin: 0;
        font-size: 2.20rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        text-transform: uppercase;
        background: linear-gradient(to bottom, #ffffff, #e2e8f0);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    .rm-mikro-hero p {
        margin: 0.5rem 0 0 0;
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.88);
        font-weight: 500;
        letter-spacing: 0.02em;
    }

    .rm-mikro-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }

    .rm-mikro-tab {
        border: 1px solid #cbd5e1 !important;
        border-radius: 999px !important;
        padding: 0.5rem 1.25rem !important;
        background: #ffffff !important;
        color: #475569 !important;
        font-size: 0.8rem !important;
        font-weight: 700 !important;
        line-height: 1.2;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        outline: none !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .rm-mikro-tab:hover {
        background: #f1f5f9 !important;
        border-color: #0857c3 !important;
        color: #0857c3 !important;
    }

    .rm-mikro-tab.active {
        background: linear-gradient(135deg, #0857c3 0%, #004b87 100%) !important;
        color: #ffffff !important;
        border-color: #0857c3 !important;
        box-shadow: 0 4px 12px rgba(8, 87, 195, 0.35) !important;
    }

    .loan-filter-control {
        border-radius: 10px !important;
        border: 1px solid #cbd5e1 !important;
        background-color: #ffffff !important;
        color: #1e293b !important;
        font-weight: 700 !important;
        min-height: 42px !important;
        padding: 0.375rem 0.75rem !important;
        transition: all 0.2s ease;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.25rem !important;
        cursor: pointer;
    }

    .loan-filter-control:focus {
        border-color: #0857c3 !important;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.15) !important;
        outline: none !important;
    }

    .loan-shell .btn-primary {
        background: linear-gradient(135deg, #0857c3 0%, #002d62 100%) !important;
        border: none !important;
        border-radius: 10px !important;
        font-weight: 700 !important;
        min-height: 42px !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(8, 87, 195, 0.25) !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .loan-shell .btn-primary:hover {
        box-shadow: 0 6px 20px rgba(8, 87, 195, 0.4) !important;
        transform: translateY(-1px);
        filter: brightness(1.05);
    }

    .rm-mikro-table-wrap {
        border: 1px solid #cbd5e1 !important;
        border-radius: 12px !important;
        box-shadow: 0 6px 24px -10px rgba(0, 45, 98, 0.12) !important;
        background: #ffffff !important;
        overflow-x: auto;
        overflow-y: auto;
    }

    .rm-mikro-table,
    .mantri-monitoring-table {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        font-size: 0.8rem !important;
        color: #334155 !important;
        background: #ffffff !important;
        width: 100%;
    }

    .rm-mikro-table th,
    .mantri-monitoring-table th {
        background: #002d62 !important; /* Nusantara Deep Navy */
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 0.72rem !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        padding: 0.75rem 0.85rem !important;
        border-right: 1px solid #0857c3 !important;
        border-bottom: 2px solid #0857c3 !important;
        text-align: center !important;
        vertical-align: middle !important;
        white-space: nowrap !important;
    }

    .rm-mikro-table td,
    .mantri-monitoring-table td {
        padding: 0.6rem 0.8rem !important;
        border-right: 1px solid #cbd5e1 !important;
        border-bottom: 1px solid #cbd5e1 !important;
        background: #ffffff;
        color: #334155 !important;
        vertical-align: middle !important;
        white-space: nowrap !important;
    }

    .rm-mikro-table th:first-child,
    .mantri-monitoring-table th:first-child,
    .rm-mikro-table td:first-child,
    .mantri-monitoring-table td:first-child {
        border-left: 1px solid #cbd5e1 !important;
    }

    .rm-mikro-table thead tr:first-child th,
    .mantri-monitoring-table thead tr:first-child th {
        border-top: 1px solid #cbd5e1 !important;
    }

    .rm-mikro-table tbody tr:nth-child(even) td,
    .mantri-monitoring-table tbody tr:nth-child(even) td {
        background: #f8fbff !important; /* Modern soft blue spreadsheet alternate */
    }

    .rm-mikro-table tbody tr:hover td,
    .mantri-monitoring-table tbody tr:hover td {
        background: #eef6ff !important;
        color: #0857c3 !important;
    }

    .rm-mikro-table .group-head,
    .mantri-monitoring-table .group-head {
        background: #042a5f !important; /* Deepest Nusantara Blue */
        color: #ffffff !important;
        font-weight: 800 !important;
    }

    .rm-mikro-table .text-right,
    .mantri-monitoring-table .text-right {
        text-align: right !important;
    }

    .rm-mikro-table .text-center,
    .mantri-monitoring-table .text-center {
        text-align: center !important;
    }

    .rm-mikro-table .strong,
    .mantri-monitoring-table .strong {
        font-weight: 800 !important;
        color: #0f172a !important;
    }

    /* Elegant Grand Total row like Excel styling but modern */
    .loan-dashboard .rm-mikro-table tbody tr.rm-mikro-total td,
    .loan-dashboard .rm-mikro-table tbody tr.rm-mikro-total th,
    .loan-dashboard .rm-mikro-table tfoot tr.rm-mikro-total td,
    .loan-dashboard .rm-mikro-table tfoot tr.rm-mikro-total th,
    .loan-dashboard .mantri-monitoring-table tbody tr.rm-mikro-total td,
    .loan-dashboard .mantri-monitoring-table tbody tr.rm-mikro-total th,
    .loan-dashboard .mantri-monitoring-table tfoot tr.rm-mikro-total td,
    .loan-dashboard .mantri-monitoring-table tfoot tr.rm-mikro-total th,
    .loan-dashboard .rm-mikro-table tbody tr.row-total td,
    .loan-dashboard .rm-mikro-table tbody tr.row-total th,
    .loan-dashboard .rm-mikro-table tfoot tr.row-total td,
    .loan-dashboard .rm-mikro-table tfoot tr.row-total th,
    .loan-dashboard .mantri-monitoring-table tbody tr.row-total td,
    .loan-dashboard .mantri-monitoring-table tbody tr.row-total th,
    .loan-dashboard .mantri-monitoring-table tfoot tr.row-total td,
    .loan-dashboard .mantri-monitoring-table tfoot tr.row-total th {
        background: #ffeb3b !important; /* Bright yellow spreadsheet grand total */
        color: #1e293b !important;
        font-weight: 900 !important;
        border-top: 2px solid #a16207 !important;
        border-bottom: 4px double #a16207 !important; /* Double lines for accounting totals */
    }

    /* HSL conditional formatting palettes (Soft, readable pastel style) */
    .heat-red { background: #fee2e2 !important; color: #b91c1c !important; font-weight: 700 !important; border-color: #fca5a5 !important; }
    .heat-orange { background: #ffedd5 !important; color: #ea580c !important; font-weight: 700 !important; border-color: #fdba74 !important; }
    .heat-yellow { background: #fef9c3 !important; color: #a16207 !important; font-weight: 700 !important; border-color: #fde047 !important; }
    .heat-lime { background: #dcfce7 !important; color: #15803d !important; font-weight: 700 !important; border-color: #86efac !important; }
    .heat-green { background: #bbf7d0 !important; color: #166534 !important; font-weight: 800 !important; border-color: #4ade80 !important; }
    .heat-muted { background: #f1f5f9 !important; color: #64748b !important; border-color: #cbd5e1 !important; }

    .mantri-monitoring-table {
        min-width: 1780px;
    }

    .mantri-monitoring-table thead th {
        border-right: 1px solid #0857c3 !important;
        border-bottom: 2px solid #0857c3 !important;
    }

    .mantri-monitoring-table .cell-extreme { background: transparent !important; color: #334155 !important; }
    .mantri-monitoring-table .cell-low { background: transparent !important; color: #334155 !important; }
    .mantri-monitoring-table .cell-under { background: #ffe4e6 !important; color: #9f1239 !important; font-weight: 800 !important; border-color: #fecaca !important; }
    .mantri-monitoring-table .cell-mid { background: #eff6ff !important; color: #1e40af !important; font-weight: 800 !important; border-color: #bfdbfe !important; }
    .mantri-monitoring-table .cell-high { background: #ecfdf5 !important; color: #065f46 !important; font-weight: 800 !important; border-color: #a7f3d0 !important; }

    .target-bar {
        min-width: 96px;
    }

    .target-bar__track {
        height: 9px;
        overflow: hidden;
        border-radius: 999px;
        background: #e2e8f0;
    }

    .target-bar__fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #ef4444, #facc15, #22c55e);
    }

    .rm-mikro-empty {
        padding: 2.6rem 1rem;
        text-align: center;
        color: #94a3b8;
        font-weight: 600;
    }

    .rm-mikro-info-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.6rem 1.15rem;
        background-color: #f8fbff;
        border: 1px solid rgba(8, 87, 195, 0.12);
        border-radius: 999px;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease;
    }

    .rm-mikro-info-badge:hover {
        background-color: #f0f7ff;
        border-color: rgba(8, 87, 195, 0.25);
    }
    
    .rm-mikro-info-badge strong {
        color: #0857c3;
        margin-left: 0.25rem;
        font-weight: 800;
    }

    /* Sticky vertical viewport styles dynamically compiled */
    @include('report.partials.sticky-table-viewport-style', [
        'wrapperSelector' => '.rm-mikro-table-wrap',
        'tableSelector' => '.rm-mikro-table'
    ])

    @include('report.partials.sticky-table-viewport-style', [
        'wrapperSelector' => '.rm-mikro-table-wrap',
        'tableSelector' => '.mantri-monitoring-table'
    ])
</style>

@php
    $rows = collect($payload['rows'] ?? []);
    $total = $payload['total'] ?? [];
@endphp

<div class="loan-dashboard pt-4 px-3">
    <div class="rm-mikro-hero animate-reveal">
        <h1>Kinerja RM Mikro</h1>
        <p>Analisis & Produktivitas RM Mikro</p>
    </div>

    <div class="card loan-shell mb-4 animate-reveal">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('report.dashboard-pinjaman.kinerjarmmikro') }}">
                <div class="row loan-filter-grid">
                    <div class="col-xl-3 col-lg-6">
                        <label class="loan-filter-label" for="periode">PERIODE</label>
                        <select id="periode" name="periode" class="form-control loan-filter-control">
                            @foreach ($availablePeriods as $period)
                                <option value="{{ $period }}" @selected($period === $selectedPeriod)>{{ \Carbon\Carbon::parse($period)->locale('id')->translatedFormat('d F Y') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <label class="loan-filter-label" for="kategori_rm">KATEGORI RM</label>
                        <select id="kategori_rm" name="kategori_rm" class="form-control loan-filter-control">
                            @foreach ($rmCategories as $key => $label)
                                <option value="{{ $key }}" @selected($key === $selectedRmCategory)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-4 col-lg-8">
                        <label class="loan-filter-label">KATEGORI REPORT</label>
                        <div class="rm-mikro-tabs">
                            @foreach ($reportCategories as $key => $label)
                                <button class="rm-mikro-tab {{ $key === $selectedReportCategory ? 'active' : '' }}" type="submit" name="kategori_report" value="{{ $key }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary font-weight-bold w-100" style="border-radius: 12px; min-height: 44px;">
                            <i class="fas fa-filter mr-2"></i>Tampilkan
                        </button>
                    </div>
                </div>
            </form>
            <div class="d-flex flex-wrap align-items-center justify-content-between mt-3" style="gap: 0.75rem;">
                <div class="rm-mikro-info-badge">
                    <i class="far fa-calendar-alt mr-2 text-primary"></i>
                    <span>Periode Posisi: <strong>{{ $selectedPeriodLabel }}</strong></span>
                </div>
                <div class="rm-mikro-info-badge">
                    <i class="fas fa-bullseye mr-2 text-warning"></i>
                    <span>Target Realisasi RM Bulanan: <strong>{{ $formatAmount($targetMonthlyJuta) }} Rp.Juta</strong></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card loan-table-shell animate-reveal">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark">{{ $reportCategories[$selectedReportCategory] ?? 'Per UKER' }}</h5>
                </div>
                <div class="loan-table-badge">
                    <i class="fas fa-table"></i>
                    <span>{{ number_format($rows->count(), 0, ',', '.') }} baris</span>
                </div>
            </div>

            @if ($selectedRmCategory === 'mantri')
                @if ($selectedReportCategory === 'unit_pemutus')
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_unit_pemutus', ['rows' => $rows])
                @elseif ($selectedReportCategory === 'kuadran')
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_kuadran', ['rows' => $rows])
                @elseif ($selectedReportCategory === 'produktivitas_mantri')
                    @include('report.dashboard-pinjaman._partials._styles')
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_produktivitas', ['rows' => $rows])
                @elseif ($selectedReportCategory === 'pdwk_override')
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_pdwk', ['rows' => $rows])
                @elseif ($selectedReportCategory === 'extreme_low_mantri')
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_extreme_low', ['rows' => $rows, 'total' => $total])
                @else
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_rekap', ['rows' => $rows])
                @endif
            @elseif ($selectedReportCategory === 'per_uker')
                @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_per_uker', ['rows' => $rows, 'total' => $total])
            @elseif ($selectedReportCategory === 'per_rm')
                @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_per_rm', ['rows' => $rows])
            @elseif ($selectedReportCategory === 'series_bulanan')
                @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_series_bulanan', ['rows' => $rows])
            @elseif ($selectedReportCategory === 'series_harian')
                @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_series_harian', ['rows' => $rows])
            @elseif ($selectedReportCategory === 'rekap')
                @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_rekap', ['rows' => $rows])
            @else
                @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_tiering', ['rows' => $rows])
            @endif
        </div>
    </div>
</div>

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.rm-mikro-table-wrap').forEach(el => {
            el.classList.add('table-container');
        });
    });
</script>

@include('report.partials.sticky-table-viewport-script', [
    'wrapperSelector' => '.rm-mikro-table-wrap',
    'tableSelector' => '.rm-mikro-table',
    'visibleRowLimit' => 100
])

@include('report.partials.sticky-table-viewport-script', [
    'wrapperSelector' => '.rm-mikro-table-wrap',
    'tableSelector' => '.mantri-monitoring-table',
    'visibleRowLimit' => 100
])

@include('report.partials.floating-scrollbar')
@endsection
@endsection
