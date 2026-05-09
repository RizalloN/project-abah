@extends('layouts.admin')

@section('title', 'Kinerja RM Mikro')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .rm-mikro-hero {
        margin-bottom: 1rem;
        padding: 1.35rem 1.2rem;
        border-radius: 0 0 1.4rem 1.4rem;
        background:
            radial-gradient(circle at 14% 18%, rgba(255, 103, 31, 0.18), transparent 26%),
            radial-gradient(circle at 88% 10%, rgba(59, 130, 246, 0.22), transparent 28%),
            linear-gradient(135deg, #063c78 0%, #075aa9 48%, #0f4c97 100%);
        color: #fff;
        box-shadow: 0 18px 40px -30px rgba(0, 55, 116, 0.55);
    }

    .rm-mikro-hero h1 {
        margin: 0;
        font-size: clamp(1.18rem, 2.05vw, 2rem);
        font-weight: 900;
        letter-spacing: .035em;
        text-align: center;
        text-transform: uppercase;
    }

    .rm-mikro-hero p {
        max-width: 850px;
        margin: .65rem auto 0;
        color: rgba(255,255,255,.8);
        font-size: .82rem;
        line-height: 1.6;
        text-align: center;
    }

    .rm-mikro-note {
        font-size: .8rem;
        color: var(--loan-muted);
    }

    .rm-mikro-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .rm-mikro-tab {
        border: 1px solid rgba(8, 87, 195, .16);
        border-radius: 999px;
        padding: .52rem .88rem;
        background: #fff;
        color: #174e92;
        font-size: .78rem;
        font-weight: 800;
        line-height: 1;
    }

    .rm-mikro-tab.active {
        background: linear-gradient(125deg, #0857c3 0%, #307fe2 100%);
        color: #fff;
        box-shadow: 0 12px 20px -16px rgba(4, 42, 95, .72);
    }

    .rm-mikro-table-wrap {
        overflow: auto;
        border: 1px solid rgba(8, 87, 195, .14);
        border-radius: 14px;
        background: #f8fbff;
    }

    .rm-mikro-table {
        width: 100%;
        min-width: 1500px;
        border-collapse: collapse;
        font-size: .8rem;
    }

    .rm-mikro-table th {
        position: sticky;
        top: 0;
        z-index: 10;
        padding: .5rem .58rem;
        background: #2f74b6;
        color: #fff;
        border-right: 1px solid rgba(255,255,255,.14);
        border-bottom: 1px solid rgba(255,255,255,.12);
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .rm-mikro-table td {
        padding: .6rem .66rem;
        border-right: 1px solid rgba(8, 87, 195, .09);
        border-bottom: 1px solid rgba(8, 87, 195, .09);
        background: #fff;
        color: #1f2937;
        white-space: nowrap;
        vertical-align: middle;
    }

    .rm-mikro-table tbody tr:nth-child(even) td {
        background: #fbfdff;
    }

    .rm-mikro-table tbody tr:hover td {
        background: #eef6ff;
    }

    .rm-mikro-table .group-head {
        background: #245d9f;
    }

    .rm-mikro-table .text-right {
        text-align: right;
    }

    .rm-mikro-table .text-center {
        text-align: center;
    }

    .rm-mikro-table .strong {
        font-weight: 800;
        color: #0f172a;
    }

    .rm-mikro-total td {
        background: #fff7d6 !important;
        font-weight: 900;
    }

    .heat-red { background: #fee2e2 !important; color: #991b1b !important; font-weight: 800; }
    .heat-orange { background: #ffedd5 !important; color: #9a3412 !important; font-weight: 800; }
    .heat-yellow { background: #fef9c3 !important; color: #854d0e !important; font-weight: 800; }
    .heat-lime { background: #dcfce7 !important; color: #166534 !important; font-weight: 800; }
    .heat-green { background: #bbf7d0 !important; color: #14532d !important; font-weight: 900; }
    .heat-muted { background: #f1f5f9 !important; color: #64748b !important; }

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
        color: var(--loan-muted);
    }
</style>

@php
    $rows = collect($payload['rows'] ?? []);
    $total = $payload['total'] ?? [];
@endphp

<div class="loan-dashboard pt-4 px-3">
    <div class="rm-mikro-hero animate-reveal">
        <h1>Kinerja RM Mikro</h1>
        <p>Monitoring RM Mikro KUR berdasarkan posisi OS, SML, NPL, realisasi bulanan, series harian, rekap produktivitas, dan tiering plafon dari report Daily Loan Dinamis periode terbaru.</p>
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
            <div class="rm-mikro-note mt-3">
                Periode posisi: <strong>{{ $selectedPeriodLabel }}</strong>. Target realisasi RM bulanan: <strong>{{ $formatAmount($targetMonthlyJuta) }} Rp.Juta</strong>.
            </div>
        </div>
    </div>

    <div class="card loan-table-shell animate-reveal">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark">{{ $reportCategories[$selectedReportCategory] ?? 'Per UKER' }}</h5>
                    <div class="rm-mikro-note">{{ $payload['message'] ?? 'Data RM Mikro KUR dari daily_loan_dinamis dengan filter KUR-Mikro khusus Kredit Mikro - KUR Ritel 2015.' }}</div>
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
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_produktivitas', ['rows' => $rows])
                @elseif ($selectedReportCategory === 'pdwk_override')
                    @include('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_pdwk', ['rows' => $rows])
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
@endsection
