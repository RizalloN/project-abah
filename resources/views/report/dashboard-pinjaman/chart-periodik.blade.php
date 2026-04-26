@extends('layouts.admin')

@section('title', 'Chart Periodik')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

@php
    $selectedUnitValues = collect($selected_units ?? [])
        ->map(fn (array $unit) => (string) ($unit['value'] ?? ''))
        ->filter()
        ->values()
        ->all();
@endphp

<style>
    /* Premium Glassmorphism & Modern UI */
    .loan-shell {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.1);
        overflow: visible !important;
        position: relative;
        z-index: 5;
    }

    .chart-periodik-shell::after {
        content: '';
        position: absolute;
        inset: auto -10% -20% auto;
        width: 400px;
        height: 400px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(8, 87, 195, 0.08) 0%, rgba(8, 87, 195, 0.02) 50%, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }

    .loan-filter-grid {
        position: relative;
        z-index: 1;
    }

    .loan-filter-label {
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #536c8b;
        margin-bottom: 0.6rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .loan-filter-label i {
        color: var(--loan-blue);
        font-size: 0.8rem;
    }

    .loan-filter-control {
        border-radius: 14px !important;
        border: 1.5px solid #e2eaf3 !important;
        background: #ffffff !important;
        height: 48px !important;
        font-weight: 600 !important;
        color: var(--loan-blue-ink) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .loan-filter-control:focus {
        border-color: var(--loan-blue) !important;
        box-shadow: 0 0 0 4px rgba(8, 87, 195, 0.1) !important;
    }

    /* Select2 Premium Override */
    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
        border: 1.5px solid #e2eaf3 !important;
        border-radius: 14px !important;
        min-height: 48px !important;
        background: #ffffff !important;
        transition: all 0.3s ease !important;
    }

    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--loan-blue) !important;
        box-shadow: 0 0 0 4px rgba(8, 87, 195, 0.1) !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: linear-gradient(135deg, var(--loan-blue), #307fe2) !important;
        border: none !important;
        color: #ffffff !important;
        border-radius: 8px !important;
        padding: 4px 10px !important;
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        margin-top: 8px !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: rgba(255, 255, 255, 0.8) !important;
        margin-right: 5px !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ffffff !important;
    }

    .chart-periodik-kpi {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 24px;
        padding: 1.5rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 140px;
        box-shadow: 0 4px 20px rgba(8, 87, 195, 0.05);
        position: relative;
        overflow: hidden;
    }

    .chart-periodik-kpi:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 35px rgba(8, 87, 195, 0.12);
        background: #ffffff;
        border-color: var(--loan-blue);
    }

    .chart-periodik-kpi__label {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--loan-muted);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 0.5rem;
    }

    .chart-periodik-kpi__value {
        font-size: 1.85rem;
        font-weight: 900;
        color: var(--loan-blue-ink);
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .chart-periodik-kpi__hint {
        font-size: 0.72rem;
        color: var(--loan-muted);
        line-height: 1.4;
        font-weight: 600;
    }

    .chart-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid #eef2f7;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .chart-card:hover {
        box-shadow: 0 8px 30px rgba(8, 87, 195, 0.06);
    }

    .chart-card__header {
        padding: 1.5rem;
        border-bottom: 1px solid #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .chart-card__title {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--loan-blue-ink);
        margin-bottom: 0.25rem;
    }

    .chart-card__desc {
        font-size: 0.78rem;
        color: var(--loan-muted);
        margin-bottom: 0;
        line-height: 1.5;
    }

    .chart-card__badge {
        background: var(--loan-blue-soft);
        color: var(--loan-blue);
        padding: 0.5rem 0.8rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
    }

    .chart-card__body {
        padding: 1.5rem;
        flex: 1;
        position: relative;
    }

    .chart-canvas-wrap {
        position: relative;
        height: 320px;
        width: 100%;
    }

    .chart-empty-state {
        height: 320px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--loan-muted);
        background: #f8fafc;
        border-radius: 16px;
    }

    .chart-periodik-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .btn-premium {
        background: linear-gradient(135deg, var(--loan-blue), #307fe2);
        color: #ffffff;
        border: none;
        border-radius: 14px;
        font-weight: 800;
        letter-spacing: 0.02em;
        padding: 0 1.5rem;
        height: 52px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(8, 87, 195, 0.25);
    }

    .btn-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(8, 87, 195, 0.35);
        color: #ffffff;
    }

    .btn-premium:active {
        transform: translateY(0);
    }

    .btn-reset {
        background: #ffffff;
        color: var(--loan-blue-ink);
        border: 1.5px solid #e2eaf3;
        border-radius: 14px;
        font-weight: 700;
        height: 52px;
        padding: 0 1.5rem;
        transition: all 0.3s ease;
    }

    .btn-reset:hover {
        background: #f8fbff;
        border-color: var(--loan-blue);
        color: var(--loan-blue);
    }

    .chart-note-panel {
        background: #ffffff;
        border: 1px solid #eef2f7;
        padding: 1.5rem;
        border-radius: 20px;
        flex: 1;
        min-width: 300px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    }

    .chart-note-panel h6 {
        color: var(--loan-blue);
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-note-panel h6::before {
        content: '';
        width: 8px;
        height: 8px;
        background: var(--loan-blue);
        border-radius: 50%;
    }

    .loan-loading-chip {
        background: rgba(8, 87, 195, 0.05);
        border: 1px solid rgba(8, 87, 195, 0.1);
        padding: 0.5rem 1rem;
        border-radius: 999px;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--loan-blue-ink);
    }

    .loan-loading-dot {
        width: 8px;
        height: 8px;
        background: var(--loan-blue);
        border-radius: 50%;
        animation: pulse 1.5s infinite;
    }
</style>
@include('report.dashboard-pinjaman._partials._styles_dropdown')
<style>

    @media (max-width: 1199.98px) {
        .chart-periodik-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .chart-periodik-note {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 575.98px) {
        .chart-periodik-summary {
            grid-template-columns: 1fr;
        }

        .chart-card__header {
            flex-direction: column;
        }
    }
</style>

<div class="loan-dashboard pt-4 px-3">
    <div class="loan-title-hero d-flex flex-wrap justify-content-center align-items-center animate-reveal">
            <div class="loan-title-hero__wrap">
                <div class="loan-title-hero__badge">
                    <i class="fas fa-chart-pie"></i>
                    <span>Loan Pattern Trend</span>
                </div>
                <h1 class="loan-title-hero__title">CHART PERIODIK</h1>
                <p class="loan-title-hero__desc">
                Menampilkan tren pola pembayaran dari <strong>snapshot chart periodik</strong> yang dibentuk dari <strong>daily_loan_dinamis</strong> dan <strong>loan_type</strong> untuk melihat distribusi bulanan, musiman, dan kategori lain per kanca serta kode uker.
                </p>
            </div>
        </div>

    <div class="card loan-shell mb-4 animate-reveal chart-periodik-shell">
        <div class="card-body p-4">
            <form id="chartPeriodikForm" method="GET" action="{{ route('report.dashboard-pinjaman.chart-periodik') }}">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                    <div>
                        <h5 class="mb-1 font-weight-bold text-dark">Filter Periodik</h5>
                        <div class="text-muted" style="font-size: 0.8rem;">Default scope mengikuti Area 6. Kode uker akan menyesuaikan kanca yang dipilih.</div>
                    </div>
                    <div class="mt-3 mt-lg-0 loan-loading-chip">
                        <span class="loan-loading-dot"></span>
                        <span id="chartPeriodikStatus">Siap menampilkan data.</span>
                    </div>
                </div>

                <div class="row loan-filter-grid align-items-end">
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="form-group mb-0">
                            <label class="loan-filter-label" for="chartPeriodikPeriode">
                                <i class="fas fa-calendar-alt"></i> Periode Terakhir
                            </label>
                            <div class="loan-dropdown-shell" id="periodeDropdownShell">
                                <input type="hidden" name="periode" id="chartPeriodikPeriode" value="{{ $selected_period }}">
                                <button type="button" class="loan-dropdown-toggle" id="periodeDropdownToggle" aria-haspopup="true" aria-expanded="false">
                                    <span class="loan-dropdown-label" id="periodeDropdownLabel">
                                        {{ $selected_period ? \Carbon\Carbon::parse($selected_period)->format('d M Y') : 'Pilih Periode' }}
                                    </span>
                                    <i class="fas fa-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                                </button>
                                <div class="loan-dropdown-menu" id="periodeDropdownMenu">
                                    <div id="periodeOptions">
                                        @foreach($periods as $periode)
                                            <div class="loan-dropdown-item filter-single-option {{ $periode === $selected_period ? 'active' : '' }}" 
                                                data-value="{{ $periode }}" 
                                                data-label="{{ \Carbon\Carbon::parse($periode)->format('d M Y') }}">
                                                <span class="form-check-label">{{ \Carbon\Carbon::parse($periode)->format('d M Y') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="form-group mb-0">
                            <label class="loan-filter-label" for="chartPeriodikCabang">
                                <i class="fas fa-university"></i> Kantor Cabang
                            </label>
                            <div class="loan-dropdown-shell" id="cabangDropdownShell">
                                <input type="hidden" name="cabang1" id="chartPeriodikCabang" value="{{ $selected_branch }}">
                                <button type="button" class="loan-dropdown-toggle" id="cabangDropdownToggle" aria-haspopup="true" aria-expanded="false">
                                    <span class="loan-dropdown-label" id="cabangDropdownLabel">
                                        {{ collect($branch_options)->firstWhere('value', $selected_branch)['label'] ?? 'Pilih Cabang' }}
                                    </span>
                                    <i class="fas fa-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                                </button>
                                <div class="loan-dropdown-menu" id="cabangDropdownMenu">
                                    <div id="cabangOptions">
                                        @foreach($branch_options as $branchOption)
                                            <div class="loan-dropdown-item filter-single-option {{ $branchOption['value'] === $selected_branch ? 'active' : '' }}" 
                                                data-value="{{ $branchOption['value'] }}" 
                                                data-label="{{ $branchOption['label'] }}">
                                                <span class="form-check-label">{{ $branchOption['label'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-5 col-md-8">
                        <div class="form-group mb-0">
                            <label class="loan-filter-label" for="chartPeriodikUnit">
                                <i class="fas fa-store"></i> Pilih Unit Kerja
                            </label>
                            <div class="loan-dropdown-shell" id="unitDropdownShell">
                                <button type="button" class="loan-dropdown-toggle" id="unitDropdownToggle" aria-haspopup="true" aria-expanded="false">
                                    <span class="loan-dropdown-label" id="unitDropdownLabel">Pilih Unit Kerja</span>
                                    <i class="fas fa-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                                </button>
                                <div class="loan-dropdown-menu" id="unitDropdownMenu">
                                    <div id="unitCheckboxes">
                                        @forelse($unit_options as $unitOption)
                                            <div class="loan-dropdown-item">
                                                <div class="form-check">
                                                    <input class="form-check-input filter-unit-checkbox" type="checkbox" 
                                                        name="unit1[]" 
                                                        value="{{ $unitOption['value'] }}" 
                                                        id="unit_{{ $unitOption['value'] }}"
                                                        @checked(in_array($unitOption['value'], $selectedUnitValues, true))>
                                                    <label class="form-check-label" for="unit_{{ $unitOption['value'] }}">
                                                        {{ $unitOption['label'] }}
                                                    </label>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="p-3 text-center text-muted" style="font-size: 0.85rem;">
                                                Pilih kanca untuk memuat unit
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-4">
                        <div class="form-group mb-0">
                            <label class="loan-filter-label" style="visibility: hidden;">Aksi</label>
                            <div class="chart-periodik-actions d-flex align-items-center" style="height: 52px; margin-bottom: 0;">
                                <button type="submit" class="btn btn-premium flex-grow-1 h-100" id="chartPeriodikRefreshButton">
                                    <i class="fas fa-sync-alt"></i> MUAT
                                </button>
                                <a href="{{ route('report.dashboard-pinjaman.chart-periodik') }}" class="btn btn-reset ml-2 h-100 d-flex align-items-center justify-content-center" style="width: 52px; padding: 0;" title="Reset Filter">
                                    <i class="fas fa-undo"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="chart-periodik-summary animate-reveal mb-4">
        <div class="chart-periodik-kpi">
            <div>
                <div class="chart-periodik-kpi__label">Total Rekening</div>
                <div class="chart-periodik-kpi__value" id="chartPeriodikTotalRekening">{{ number_format((int) ($chart['summary']['total_rekening'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="chart-periodik-kpi__hint"><i class="fas fa-info-circle mr-1"></i> Data scope aktif.</div>
        </div>
        <div class="chart-periodik-kpi">
            <div>
                <div class="chart-periodik-kpi__label">Jumlah Pola</div>
                <div class="chart-periodik-kpi__value" id="chartPeriodikPatternCount">{{ number_format((int) ($chart['summary']['pattern_count'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="chart-periodik-kpi__hint"><i class="fas fa-tags mr-1"></i> Pola unik terdeteksi.</div>
        </div>
        <div class="chart-periodik-kpi">
            <div>
                <div class="chart-periodik-kpi__label">Pola Dominan</div>
                <div class="chart-periodik-kpi__value" style="font-size: 1.4rem;" id="chartPeriodikTopPattern">{{ $chart['summary']['top_pattern'] ?? '-' }}</div>
            </div>
            <div class="chart-periodik-kpi__hint"><i class="fas fa-crown mr-1"></i> Frekuensi tertinggi.</div>
        </div>
        <div class="chart-periodik-kpi" style="background: linear-gradient(135deg, var(--loan-blue-ink), var(--loan-blue)); border: none;">
            <div>
                <div class="chart-periodik-kpi__label" style="color: rgba(255,255,255,0.7);">Scope & Periode</div>
                <div class="chart-periodik-kpi__value" style="color: #ffffff; font-size: 1.3rem;" id="chartPeriodikScopeLabel">{{ $chart['scope_label'] ?? 'Area 6 - All' }}</div>
            </div>
            <div class="chart-periodik-kpi__hint" style="color: rgba(255,255,255,0.8);" id="chartPeriodikPeriodLabel">
                <i class="fas fa-calendar-alt mr-1"></i> {{ $selected_period_label ?? '-' }}
            </div>
        </div>
    </div>

    <div class="row animate-reveal" style="row-gap: 1rem;">
        <div class="col-lg-7">
            <div class="chart-card">
                <div class="chart-card__header">
                    <div>
                        <h5 class="chart-card__title">Trend Pola Pembayaran</h5>
                        <p class="chart-card__desc">Perbandingan distribusi pola pembayaran untuk enam periode terakhir hingga periode aktif.</p>
                    </div>
                    <div class="chart-card__badge">
                        <i class="fas fa-chart-line"></i>
                        <span id="chartPeriodikTrendBadge">{{ $selected_period_label ?? '-' }}</span>
                    </div>
                </div>
                <div class="chart-card__body">
                    <div id="chartPeriodikTrendEmpty" class="chart-empty-state d-none">
                        <div>
                            <strong>Belum ada data trend</strong>
                            <span>Pilih periode atau cabang untuk memunculkan garis trend pola pembayaran.</span>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartPeriodikTrendCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-card">
                <div class="chart-card__header">
                    <div>
                        <h5 class="chart-card__title">Komposisi Periode Aktif</h5>
                        <p class="chart-card__desc">Pie chart untuk melihat porsi pola pembayaran pada periode yang sedang dipilih.</p>
                    </div>
                    <div class="chart-card__badge">
                        <i class="fas fa-chart-pie"></i>
                        <span id="chartPeriodikPieBadge">{{ $selected_period_label ?? '-' }}</span>
                    </div>
                </div>
                <div class="chart-card__body">
                    <div id="chartPeriodikPieEmpty" class="chart-empty-state d-none">
                        <div>
                            <strong>Belum ada komposisi data</strong>
                            <span>Distribusi pola pembayaran akan tampil setelah data tersedia.</span>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartPeriodikPieCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="chart-periodik-note mt-4 animate-reveal" style="display: flex; flex-wrap: wrap; gap: 1.25rem;">
        <div class="chart-note-panel">
            <h6><i class="fas fa-database"></i> Catatan logika data</h6>
            <p class="mb-0" style="font-size: 0.85rem; line-height: 1.6; color: #475569;">
                Data diturunkan dulu ke <strong>dashboard_pinjaman_chart_periodik_snapshots</strong> dari <strong>daily_loan_dinamis</strong> dan <strong>loan_type</strong>, lalu selector serta chart membaca hasil snapshot itu agar filter periode, kanca, dan kode uker tetap cepat dan konsisten.
            </p>
        </div>
        <div class="chart-note-panel">
            <h6><i class="fas fa-lightbulb"></i> Contoh scope</h6>
            <ul style="font-size: 0.85rem; line-height: 1.6; color: #475569; padding-left: 1.2rem; margin-bottom: 0;">
                <li>Area 6 default menampilkan KC Madiun, KC Magetan, KC Ngawi, dan KC Ponorogo.</li>
                <li>Jika memilih KC Ponorogo, kode uker yang tampil akan mengikuti unit pada cabang tersebut.</li>
                <li>Contoh scope spesifik: KC Ponorogo - 3887 - Ngrayun.</li>
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
@include('report.dashboard-pinjaman._partials._scripts_shared')
@include('report.dashboard-pinjaman._partials._scripts_dropdown')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const periodSelect = document.getElementById('chartPeriodikPeriode');
        const branchSelect = document.getElementById('chartPeriodikCabang');
        const form = document.getElementById('chartPeriodikForm');
        const statusChip = document.getElementById('chartPeriodikStatus');
        const refreshButton = document.getElementById('chartPeriodikRefreshButton');
        const periodDropdownToggle = document.getElementById('periodeDropdownToggle');
        const periodDropdownMenu = document.getElementById('periodeDropdownMenu');
        const periodDropdownLabel = document.getElementById('periodeDropdownLabel');
        const periodOptionsContainer = document.getElementById('periodeOptions');
        const periodDropdownShell = document.getElementById('periodeDropdownShell');

        const cabangDropdownToggle = document.getElementById('cabangDropdownToggle');
        const cabangDropdownMenu = document.getElementById('cabangDropdownMenu');
        const cabangDropdownLabel = document.getElementById('cabangDropdownLabel');
        const cabangOptionsContainer = document.getElementById('cabangOptions');
        const cabangDropdownShell = document.getElementById('cabangDropdownShell');

        const unitDropdownToggle = document.getElementById('unitDropdownToggle');
        const unitDropdownMenu = document.getElementById('unitDropdownMenu');
        const unitDropdownLabel = document.getElementById('unitDropdownLabel');
        const unitCheckboxesContainer = document.getElementById('unitCheckboxes');
        const unitDropdownShell = document.getElementById('unitDropdownShell');
        const totalRekeningEl = document.getElementById('chartPeriodikTotalRekening');
        const patternCountEl = document.getElementById('chartPeriodikPatternCount');
        const topPatternEl = document.getElementById('chartPeriodikTopPattern');
        const scopeLabelEl = document.getElementById('chartPeriodikScopeLabel');
        const periodLabelEl = document.getElementById('chartPeriodikPeriodLabel');
        const trendBadgeEl = document.getElementById('chartPeriodikTrendBadge');
        const pieBadgeEl = document.getElementById('chartPeriodikPieBadge');
        const trendCanvas = document.getElementById('chartPeriodikTrendCanvas');
        const pieCanvas = document.getElementById('chartPeriodikPieCanvas');
        const trendEmpty = document.getElementById('chartPeriodikTrendEmpty');
        const pieEmpty = document.getElementById('chartPeriodikPieEmpty');

        const filtersUrl = @json(route('report.dashboard-pinjaman.chart-periodik.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.chart-periodik.data'));
        const initialChartPayload = @json($chart);
        const initialPeriodOptions = @json(collect($periods ?? [])->map(fn ($period) => [
            'value' => (string) $period,
            'label' => \Carbon\Carbon::parse($period)->format('d M Y'),
        ])->values()->all());
        const initialBranchOptions = @json($branch_options ?? []);
        const initialSelectedPeriod = @json($selected_period ?? null);
        const initialSelectedBranch = @json($selected_branch ?? 'all');
        const initialUnitOptions = @json($unit_options ?? []);
        const initialSelectedUnits = @json($selectedUnitValues);

        let trendChart = null;
        let pieChart = null;
        let filtersController = null;
        let dataController = null;
        let suppressFilterReload = false;
        let suppressUnitReload = false;

        function initSelects() {
            // Standard selects are removed
        }

        function bindFilterEvents() {
            if (window.jQuery) {
                $(periodOptionsContainer).off('click', '.filter-single-option').on('click', '.filter-single-option', function() {
                    const val = $(this).data('value');
                    const label = $(this).data('label');
                    periodSelect.value = val;
                    periodDropdownLabel.textContent = label;
                    $(periodOptionsContainer).find('.filter-single-option').removeClass('active');
                    $(this).addClass('active');
                    periodDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                });

                $(cabangOptionsContainer).off('click', '.filter-single-option').on('click', '.filter-single-option', function() {
                    const val = $(this).data('value');
                    const label = $(this).data('label');
                    branchSelect.value = val;
                    cabangDropdownLabel.textContent = label;
                    $(cabangOptionsContainer).find('.filter-single-option').removeClass('active');
                    $(this).addClass('active');
                    cabangDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                });

                $(unitCheckboxesContainer).off('change', '.filter-unit-checkbox').on('change', '.filter-unit-checkbox', function () {
                    if (suppressUnitReload) return;
                    updateUnitLabel();
                    reloadData();
                });
                return;
            }

            // Vanilla Fallback
            periodOptionsContainer.addEventListener('click', function(e) {
                const item = e.target.closest('.filter-single-option');
                if (item) {
                    periodSelect.value = item.dataset.value;
                    periodDropdownLabel.textContent = item.dataset.label;
                    periodOptionsContainer.querySelectorAll('.filter-single-option').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    periodDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                }
            });

            cabangOptionsContainer.addEventListener('click', function(e) {
                const item = e.target.closest('.filter-single-option');
                if (item) {
                    branchSelect.value = item.dataset.value;
                    cabangDropdownLabel.textContent = item.dataset.label;
                    cabangOptionsContainer.querySelectorAll('.filter-single-option').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    cabangDropdownMenu.classList.remove('show');
                    if (!suppressFilterReload) reloadAll();
                }
            });
            
            unitCheckboxesContainer.addEventListener('change', function (e) {
                if (e.target.classList.contains('filter-unit-checkbox')) {
                    if (suppressUnitReload) return;
                    updateUnitLabel();
                    reloadData();
                }
            });
        }

        function updateUnitLabel() {
            updateMultiDropdownLabel(unitCheckboxesContainer, unitDropdownLabel, 'Pilih Unit Kerja');
        }

        function setStatus(message) {
            statusChip.textContent = message;
        }

        function setLoading(isLoading, message) {
            refreshButton.disabled = isLoading;
            periodSelect.disabled = isLoading;
            branchSelect.disabled = isLoading;
            unitDropdownToggle.disabled = isLoading;
            setStatus(message || (isLoading ? 'Mengambil data...' : 'Siap menampilkan data.'));
        }

        function selectedUnitValues() {
            return Array.from(unitCheckboxesContainer.querySelectorAll('.filter-unit-checkbox:checked')).map(cb => cb.value);
        }

        function rebuildSingleSelectOptions(hiddenInput, container, labelEl, options, selectedValue) {
            rebuildSingleDropdownOptions(hiddenInput, container, labelEl, options, selectedValue);
        }

        function hydrateBaseFilterOptions() {
            suppressFilterReload = true;
            rebuildSingleSelectOptions(
                periodSelect,
                periodOptionsContainer,
                periodDropdownLabel,
                initialPeriodOptions,
                initialSelectedPeriod || initialChartPayload?.selected_period || ''
            );
            rebuildSingleSelectOptions(
                branchSelect,
                cabangOptionsContainer,
                cabangDropdownLabel,
                initialBranchOptions,
                initialSelectedBranch || initialChartPayload?.selected_branch || 'all'
            );
            suppressFilterReload = false;
        }

        function rebuildUnitOptions(options, preservedValues = []) {
            suppressUnitReload = true;
            rebuildMultiDropdownOptions(unitCheckboxesContainer, unitDropdownToggle, unitDropdownLabel, options, preservedValues, 'Tidak ada unit scope ini');
            suppressUnitReload = false;
        }

        function updateSummary(payload) {
            totalRekeningEl.textContent = formatNumber(payload.summary?.total_rekening ?? 0);
            patternCountEl.textContent = formatNumber(payload.summary?.pattern_count ?? 0);
            topPatternEl.textContent = payload.summary?.top_pattern || '-';
            scopeLabelEl.textContent = payload.scope_label || 'Area 6 - All';
            periodLabelEl.textContent = payload.selected_period_label || '-';
            trendBadgeEl.textContent = payload.selected_period_label || '-';
            pieBadgeEl.textContent = payload.selected_period_label || '-';
        }

        function destroyCharts() {
            if (trendChart) {
                trendChart.destroy();
                trendChart = null;
            }

            if (pieChart) {
                pieChart.destroy();
                pieChart = null;
            }
        }

        function toggleEmptyState(canvas, emptyState, hasData) {
            if (hasData) {
                canvas.classList.remove('d-none');
                emptyState.classList.add('d-none');
                return;
            }

            canvas.classList.add('d-none');
            emptyState.classList.remove('d-none');
        }

        function renderCharts(payload) {
            updateSummary(payload);
            destroyCharts();

            const trendLabels = payload.trend?.labels || [];
            const trendDatasets = payload.trend?.datasets || [];
            const pieLabels = payload.pie?.labels || [];
            const pieValues = payload.pie?.values || [];
            const palette = ['#0857c3', '#ff671f', '#14b8a6', '#7c3aed', '#0f9d58', '#f59e0b', '#64748b', '#ec4899'];

            toggleEmptyState(trendCanvas, trendEmpty, trendLabels.length > 0 && trendDatasets.length > 0);
            toggleEmptyState(pieCanvas, pieEmpty, pieLabels.length > 0 && pieValues.length > 0);

            if (trendLabels.length > 0 && trendDatasets.length > 0) {
                trendChart = new Chart(trendCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: trendDatasets.map((dataset, index) => ({
                            label: dataset.label,
                            data: dataset.data,
                            borderColor: palette[index % palette.length],
                            backgroundColor: palette[index % palette.length] + '26',
                            borderWidth: 3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.28,
                            fill: false,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8,
                                    color: '#42526b',
                                    font: {
                                        family: 'inherit',
                                        weight: '600',
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return `${context.dataset.label}: ${formatNumber(context.raw)}`;
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: 'rgba(136, 156, 180, 0.12)',
                                },
                                ticks: {
                                    color: '#6c7f99',
                                    maxRotation: 0,
                                    font: {
                                        family: 'inherit',
                                    },
                                },
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(136, 156, 180, 0.12)',
                                },
                                ticks: {
                                    color: '#6c7f99',
                                    callback: function (value) {
                                        return formatNumber(value);
                                    },
                                    font: {
                                        family: 'inherit',
                                    },
                                },
                            },
                        },
                    },
                });
            }

            if (pieLabels.length > 0 && pieValues.length > 0) {
                pieChart = new Chart(pieCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: pieLabels,
                        datasets: [{
                            data: pieValues,
                            backgroundColor: pieLabels.map((_, index) => palette[index % palette.length]),
                            borderColor: '#ffffff',
                            borderWidth: 2,
                            hoverOffset: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    color: '#42526b',
                                    font: {
                                        family: 'inherit',
                                        weight: '600',
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const total = context.dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                                        const value = Number(context.raw || 0);
                                        const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                        return `${context.label}: ${formatNumber(value)} (${pct}%)`;
                                    },
                                },
                            },
                        },
                    },
                });
            }
        }

        function updateUrl() {
            const params = new URLSearchParams();
            if (periodSelect.value) {
                params.set('periode', periodSelect.value);
            }
            if (branchSelect.value) {
                params.set('cabang1', branchSelect.value);
            }
            selectedUnitValues().forEach((value) => params.append('unit1[]', value));

            const query = params.toString();
            const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
            window.history.replaceState({}, '', nextUrl);
        }

        async function reloadFilters() {
            if (!periodSelect.value) {
                return;
            }

            if (filtersController) {
                filtersController.abort();
            }

            filtersController = new AbortController();
            const query = new URLSearchParams({
                periode: periodSelect.value,
                cabang1: branchSelect.value,
            });

            setLoading(true, 'Menyusun opsi unit...');

            try {
                const response = await fetch(`${filtersUrl}?${query.toString()}`, {
                    signal: filtersController.signal,
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Filter request failed: ${response.status}`);
                }

                const payload = await response.json();
                const preserved = selectedUnitValues();
                if (payload.branch_options && payload.selected_branch) {
                    suppressFilterReload = true;
                    rebuildSingleSelectOptions(branchSelect, cabangOptionsContainer, cabangDropdownLabel, payload.branch_options, payload.selected_branch);
                    suppressFilterReload = false;
                }
                rebuildUnitOptions(payload.unit_options || [], preserved);
                updateUrl();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                    setStatus('Gagal memuat opsi unit.');
                }
                throw error;
            } finally {
                setLoading(false, 'Siap menampilkan data.');
            }
        }

        async function reloadData() {
            if (!periodSelect.value) {
                destroyCharts();
                return;
            }

            if (dataController) {
                dataController.abort();
            }

            dataController = new AbortController();
            const query = new URLSearchParams();
            query.set('periode', periodSelect.value);
            query.set('cabang1', branchSelect.value);
            selectedUnitValues().forEach((value) => query.append('unit1[]', value));

            setLoading(true, 'Mengambil trend pola pembayaran...');

            try {
                const response = await fetch(`${dataUrl}?${query.toString()}`, {
                    signal: dataController.signal,
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Data request failed: ${response.status}`);
                }

                const payload = await response.json();

                if (payload.selected_period) {
                    periodSelect.value = payload.selected_period;
                    if (window.jQuery && window.jQuery.fn.select2) {
                        window.jQuery(periodSelect).trigger('change.select2');
                    }
                }

                updateUrl();
                renderCharts(payload);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                    setStatus('Gagal memuat chart.');
                }
            } finally {
                setLoading(false, 'Siap menampilkan data.');
            }
        }

        async function reloadAll() {
            try {
                await reloadFilters();
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
            }

            await reloadData();
        }

        hydrateBaseFilterOptions();
        initSelects();
        rebuildUnitOptions(initialUnitOptions, initialSelectedUnits);
        renderCharts(initialChartPayload);
        updateUrl();
        bindFilterEvents();

        // Custom Dropdown Handlers
        initDropdownHandlers(periodDropdownShell, periodDropdownToggle, periodDropdownMenu);
        initDropdownHandlers(cabangDropdownShell, cabangDropdownToggle, cabangDropdownMenu);
        initDropdownHandlers(unitDropdownShell, unitDropdownToggle, unitDropdownMenu);

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                reloadAll();
            });
        }

        if (!periodSelect.value && periodSelect.options.length > 0) {
            periodSelect.value = periodSelect.options[0].value;
            reloadAll();
        } else if (periodSelect.value) {
            setStatus('Siap menampilkan data.');
        } else {
            setStatus('Periode tidak tersedia.');
        }
    });
</script>
@endpush
@endsection
