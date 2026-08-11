@extends('layouts.admin')

@section('title', 'Matrix Pergeseran Kolek')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

@php
    $formatMatrixPeriod = function (?string $period): string {
        if (!$period) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($period)->locale('id')->translatedFormat('d M y');
        } catch (\Throwable) {
            return $period;
        }
    };

    $formatMatrixBucket = function (?string $bucket): string {
        return match ($bucket) {
            'DPK 1' => 'SML 1',
            'DPK 2' => 'SML 2',
            'DPK 3' => 'SML 3',
            default => $bucket ?: '-',
        };
    };
@endphp

<style>
    /* ── Modern Premium Selectors ── */
    .loan-filter-modern {
        display: grid;
        grid-template-columns: repeat(5, 1fr) auto;
        gap: 1rem;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(20px);
        padding: 1.5rem;
        border-radius: 1.75rem;
        border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 
            0 10px 15px -3px rgba(0, 0, 0, 0.05),
            0 20px 40px -20px rgba(8, 87, 195, 0.15);
        margin-bottom: 2.5rem;
        position: relative;
        z-index: 1000; /* Elevated base */
        align-items: flex-end;
    }

    /* Prevent any clipping from parents */
    .loan-shell, .loan-shell .card-body {
        overflow: visible !important;
    }

    @media (max-width: 1400px) {
        .loan-filter-modern {
            grid-template-columns: repeat(3, 1fr); /* Wrap to 3 cols on smaller screens */
        }
    }

    @media (max-width: 991px) {
        .loan-filter-modern {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 575px) {
        .loan-filter-modern {
            grid-template-columns: 1fr;
        }
    }

    .loan-filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        position: relative;
    }

    /* Descending z-index for items (left to right) */
    .loan-filter-item:nth-child(1) { z-index: 50; }
    .loan-filter-item:nth-child(2) { z-index: 40; }
    .loan-filter-item:nth-child(3) { z-index: 30; }
    .loan-filter-item:nth-child(4) { z-index: 20; }
    .loan-filter-item:nth-child(5) { z-index: 10; }

    .loan-filter-modern .loan-filter-label {
        font-size: 0.72rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-left: 0.5rem;
    }

    .loan-dropdown {
        position: relative;
        width: 100%;
    }

    .loan-dropdown-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: var(--loan-blue);
        font-size: 1rem;
        pointer-events: none;
        opacity: 0.8;
    }

    .loan-dropdown-toggle {
        width: 100%;
        height: 54px;
        background: #ffffff;
        border: 2px solid #eef2f6;
        border-radius: 16px;
        padding: 0 1.25rem 0 3rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        font-size: 0.9rem;
        color: #1e293b;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .loan-dropdown-toggle:hover {
        border-color: var(--loan-blue);
        box-shadow: 0 8px 20px rgba(8, 87, 195, 0.08);
        transform: translateY(-1px);
    }

    .loan-dropdown.is-open { z-index: 3100 !important; }
    
    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 100%;
        min-width: min(320px, calc(100vw - 2rem));
        max-width: calc(100vw - 2rem);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(25px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 1.5rem;
        box-shadow: 
            0 20px 40px -5px rgba(0, 0, 0, 0.15),
            0 40px 80px -20px rgba(8, 87, 195, 0.3);
        z-index: 3000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(12px);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 480px;
        overflow-y: auto;
        padding: 0.75rem;
    }

    /* Logic to prevent menu from going off-screen on the right */
    .loan-filter-item:nth-last-child(-n+2) .loan-dropdown-menu {
        left: auto;
        right: 0;
    }

    .loan-dropdown.is-open .loan-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .loan-dropdown-option {
        width: 100%;
        padding: 0.75rem 1rem;
        border: none;
        background: transparent;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 700;
        font-size: 0.85rem;
        color: #475569;
        transition: all 0.2s;
        text-align: left;
        margin-bottom: 2px;
    }

    .loan-dropdown-option:hover {
        background: #f1f5f9;
        color: var(--loan-blue);
    }

    .loan-dropdown-option.is-active {
        background: rgba(8, 87, 195, 0.08);
        color: var(--loan-blue);
    }

    .loan-dropdown-check {
        width: 1.25rem;
        height: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 0.7rem;
        color: white;
    }

    .loan-dropdown-option.is-active .loan-dropdown-check {
        background: var(--loan-blue);
        border-color: var(--loan-blue);
    }

    /* Scrollbar */
    .loan-dropdown-menu::-webkit-scrollbar { width: 5px; }
    .loan-dropdown-menu::-webkit-scrollbar-track { background: transparent; }
    .loan-dropdown-menu::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

    .btn-loan-modern-submit {
        height: 54px;
        min-width: 140px;
        padding: 0 1.75rem;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--loan-blue) 0%, #1e40af 100%);
        color: white;
        border: none;
        font-weight: 800;
        font-size: 0.9rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 8px 15px rgba(8, 87, 195, 0.25);
    }

    .btn-loan-modern-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 25px -5px rgba(8, 87, 195, 0.4);
    }

    /* Hide Original Select2 & Native Selects */
    .select2-container--bootstrap4, .loan-filter-control {
        display: none !important;
    }

    .loan-recovery-header {
        padding: 0.75rem 0 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .loan-recovery-header h1 {
        margin: 0;
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1.2;
        letter-spacing: 0;
    }

    .loan-recovery-header p {
        margin: 0.3rem 0 0;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.45;
    }

    .loan-filter-shell {
        position: relative;
        z-index: 1000;
        margin-bottom: 0.75rem;
        overflow: visible;
        border: 1px solid #dbe5ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
    }

    .loan-filter-summary-bar {
        width: 100%;
        min-height: 48px;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto auto;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem;
        border: 0;
        border-radius: 8px;
        background: #ffffff;
        color: #12345b;
        text-align: left;
    }

    .loan-filter-summary-bar:focus-visible {
        outline: 2px solid #0b67b2;
        outline-offset: 2px;
    }

    .loan-filter-summary-title {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        white-space: nowrap;
        color: #075a9c;
        font-size: 0.78rem;
        font-weight: 800;
    }

    .loan-filter-summary-copy {
        min-width: 0;
        overflow: hidden;
        color: #52657b;
        font-size: 0.78rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .loan-filter-summary-copy strong {
        color: #162b45;
        font-weight: 800;
    }

    .loan-filter-summary-separator {
        margin: 0 0.35rem;
        color: #a2b1c1;
    }

    .loan-filter-summary-arrow {
        color: #7d91a8;
        transition: transform 0.2s ease;
    }

    .loan-filter-shell.is-open .loan-filter-summary-arrow {
        transform: rotate(180deg);
    }

    .loan-filter-panel {
        display: none;
        padding: 0.75rem;
        border-top: 1px solid #e5edf5;
        overflow: visible;
    }

    .loan-filter-shell.is-open .loan-filter-panel {
        display: block;
    }

    .loan-filter-shell .loan-filter-modern {
        grid-template-columns: repeat(5, minmax(0, 1fr)) minmax(120px, auto);
        gap: 0.65rem;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
        backdrop-filter: none;
    }

    .loan-filter-shell .loan-filter-label {
        margin-left: 0;
        letter-spacing: 0;
    }

    .loan-filter-shell .loan-dropdown-toggle,
    .loan-filter-shell .btn-loan-modern-submit {
        height: 44px;
        border-radius: 8px;
    }

    .loan-filter-shell .loan-dropdown-toggle {
        padding-right: 0.8rem;
        padding-left: 2.7rem;
        font-size: 0.82rem;
    }

    .loan-filter-shell .loan-dropdown-icon {
        left: 1rem;
    }

    .loan-filter-shell .loan-dropdown-menu {
        border-radius: 8px;
    }

    .loan-table-heading > .d-flex {
        flex-wrap: wrap;
        gap: 0.5rem 1rem;
    }

    .loan-table-heading h5 {
        min-width: 0;
        flex: 1 1 340px;
        overflow-wrap: anywhere;
    }

    .loan-table-heading .text-right {
        display: flex;
        flex: 0 1 auto;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.35rem;
    }

    .loan-table-heading .text-right .badge {
        margin: 0 !important;
    }

    @media (max-width: 1399.98px) {
        .loan-filter-shell .loan-filter-modern {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .loan-filter-summary-bar {
            grid-template-columns: auto minmax(0, 1fr) auto;
        }

        .loan-filter-summary-title span {
            display: none;
        }

        .loan-filter-summary-bar .loan-loading-chip {
            display: none !important;
        }

        .loan-filter-shell .loan-filter-modern {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .loan-filter-shell .loan-filter-modern {
            grid-template-columns: minmax(0, 1fr);
        }

        .loan-dropdown-menu {
            right: auto !important;
            left: 0 !important;
            width: min(100%, calc(100vw - 2rem));
        }

        .loan-table-heading h5,
        .loan-table-heading .text-right {
            flex-basis: 100%;
        }

        .loan-table-heading .text-right {
            justify-content: flex-start;
        }
    }
</style>

<div class="loan-dashboard pt-4 px-3">
    <div class="loan-recovery-header">
        <h1>Report Recovery</h1>
        <p>Matrix pergeseran kolektibilitas berbasis Daily Loan dan nominatif PH periode berjalan.</p>
    </div>

    <div id="loanMatrixPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <form id="loanFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman.matrix') }}">
                    <div id="loanFilterShell" class="loan-filter-shell animate-reveal stagger-1">
                        <button id="loanFilterToggle" type="button" class="loan-filter-summary-bar" aria-expanded="false" aria-controls="loanFilterPanel">
                            <span class="loan-filter-summary-title"><i class="fas fa-sliders-h"></i><span>FILTER DATA</span></span>
                            <span class="loan-filter-summary-copy">
                                Posisi <strong id="loanActivePeriodMeta">{{ $formatMatrixPeriod($selectedPeriod) }}</strong>
                                <span class="loan-filter-summary-separator">|</span>
                                Pembanding <strong id="loanComparisonPeriodMeta">{{ $formatMatrixPeriod($comparisonPeriod) }}</strong>
                                <span class="loan-filter-summary-separator">|</span>
                                <span id="loanFilterSelectionSummary">Semua portofolio</span>
                            </span>
                            <span id="loanLoadingChip" class="loan-loading-chip d-none">
                                <span class="loan-loading-dot"></span>
                                MEMUAT...
                            </span>
                            <i class="fas fa-chevron-down loan-filter-summary-arrow"></i>
                        </button>
                        <div id="loanFilterPanel" class="loan-filter-panel">
                            <div class="loan-filter-modern">
                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Posisi Laporan</label>
                            <div class="loan-dropdown" data-loan-dropdown="periode">
                                <i class="fas fa-calendar-alt loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="periode">
                                    <span class="loan-dropdown-text" id="loanPeriodeDisplay">{{ $formatMatrixPeriod($requestedPeriod ?: $selectedPeriod) }}</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <div class="loan-dropdown-menu" data-loan-dropdown-menu="periode"></div>
                                <select id="loanPeriodeInput" name="periode" class="d-none loan-filter-control" data-placeholder="Pilih Periode">
                                    @foreach($periods as $period)
                                        <option value="{{ $period }}" @selected(($requestedPeriod ?: $selectedPeriod) === $period)>
                                            {{ $formatMatrixPeriod($period) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Segmen</label>
                            <div class="loan-dropdown" data-loan-dropdown="segmen">
                                <i class="fas fa-layer-group loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="segmen">
                                    <span class="loan-dropdown-text">Semua Segmen</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <div class="loan-dropdown-menu" data-loan-dropdown-menu="segmen"></div>
                                <select id="loanSegmenSelect" name="segmen_dashboard[]" class="d-none" multiple data-placeholder="Semua Segmen" data-selected='@json($filters["segmen"] ?? [])'></select>
                            </div>
                        </div>

                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Produk</label>
                            <div class="loan-dropdown" data-loan-dropdown="produk">
                                <i class="fas fa-box loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="produk">
                                    <span class="loan-dropdown-text">Semua Produk</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <div class="loan-dropdown-menu" data-loan-dropdown-menu="produk"></div>
                                <select id="loanProdukSelect" name="produk_dashboard[]" class="d-none" multiple data-placeholder="Semua Produk" data-selected='@json($filters["produk"] ?? [])'></select>
                            </div>
                        </div>

                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Kantor Cabang</label>
                            <div class="loan-dropdown" data-loan-dropdown="cabang">
                                <i class="fas fa-university loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="cabang">
                                    <span class="loan-dropdown-text">Semua Cabang</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <div class="loan-dropdown-menu" data-loan-dropdown-menu="cabang"></div>
                                <select id="loanCabangSelect" name="cabang1[]" class="d-none" multiple data-placeholder="Semua Kantor Cabang" data-selected='@json($filters["cabang"] ?? [])'></select>
                            </div>
                        </div>

                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Unit Kerja</label>
                            <div class="loan-dropdown" data-loan-dropdown="unit">
                                <i class="fas fa-store loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="unit">
                                    <span class="loan-dropdown-text">Semua Unit</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <div class="loan-dropdown-menu" data-loan-dropdown-menu="unit"></div>
                                <select id="loanUnitSelect" name="unit1[]" class="d-none" multiple data-placeholder="Semua Unit Kerja" data-selected='@json($filters["unit"] ?? [])'></select>
                            </div>
                        </div>

                                <div class="d-flex align-items-end" style="margin-bottom: 2px;">
                                    <button id="loanSubmitButton" type="submit" class="btn-loan-modern-submit w-100">
                                        <i class="fas fa-filter"></i> TERAPKAN
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Original grid kept but hidden to avoid breaking existing script references if any --}}
                    <div class="row loan-filter-grid d-none">
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label class="loan-filter-label">Periode Laporan</label>
                                {{-- Input is already moved to modern section but kept here for fallback or ID reference if needed --}}
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell animate-reveal">
            <div class="card-body p-4">
                <div class="loan-table-heading mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-th-large mr-2 text-muted"></i> Data Matriks Pergeseran <span id="loanMatrixPeriodBadge" class="text-primary ml-1" style="font-size: 0.9rem;">Posisi {{ $formatMatrixPeriod($selectedPeriod) }} | Pembanding {{ $formatMatrixPeriod($comparisonPeriod) }}</span></h5>
                        <div class="text-right">
                             <span id="loanReconciliationBadge" class="badge badge-light border text-muted px-2 py-1 mr-1" style="font-size: 0.7rem;">BELUM DIHITUNG</span>
                             <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.7rem;">UNIT: RUPIAH</span>
                        </div>
                    </div>
                </div>

                <div class="loan-table-stage">
                    <div id="loanLoadingOverlay" class="loan-loading-overlay is-hidden">
                        <div class="loan-loading-dot"></div>
                        <span id="loanLoadingPhase" class="loan-loading-title">Menyiapkan Data</span>
                        <p id="loanLoadingCopy" class="loan-loading-copy">Mohon tunggu sebentar...</p>
                        <div class="loan-loading-progress">
                            <div class="loan-loading-progress-meta">
                                <span>Progress</span>
                                <span id="loanLoadingPercent">0%</span>
                            </div>
                            <div class="loan-loading-progress-track">
                                <div id="loanLoadingProgressBar" class="loan-loading-progress-bar"></div>
                            </div>
                        </div>
                    </div>

                    <div class="loan-matrix-wrap">
                        <table class="loan-matrix">
                            <thead>
                                <tr>
                                    <th class="matrix-before">Kualitas Posisi Awal<br><span id="loanComparisonHeadLabel">{{ $formatMatrixPeriod($comparisonPeriod) }}</span></th>
                                    <th colspan="{{ count($matrixColumns) }}" class="matrix-after-group">Kualitas Posisi <span id="loanCurrentHeadLabel">{{ $formatMatrixPeriod($selectedPeriod) }}</span></th>
                                    <th rowspan="2" class="matrix-total-head">Total Posisi<br><span id="loanTotalValueHeader">Berjalan</span></th>
                                    <th colspan="4" class="matrix-subhead">Data Output (IDR)</th>
                                </tr>
                                <tr>
                                    <th class="matrix-before">Bucket</th>
                                    @foreach($matrixColumns as $col)
                                        <th class="matrix-subhead">{{ $formatMatrixBucket($col) }}</th>
                                    @endforeach
                                    <th>Turunan Pokok</th>
                                    <th>Suplesi</th>
                                    <th>PH</th>
                                    <th>Lunas</th>
                                </tr>
                            </thead>
                            <tbody id="loanMatrixBody">
                                <tr>
                                    <td colspan="{{ count($matrixColumns) + 6 }}" class="loan-empty-state">
                                        <div class="py-4">
                                            <i class="fas fa-search-plus fa-3x mb-3 text-muted" style="opacity: 0.3;"></i>
                                            <strong class="d-block mb-1">Filter belum dijalankan</strong>
                                            <p class="mb-0 text-muted">Pilih periode atau filter lain lalu klik <strong>Tampilkan Data</strong> untuk menganalisis pergeseran kolek.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="loanMatrixFoot"></tfoot>
                        </table>
                    </div>
                </div>

                <div class="loan-legend">
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-stagnant"></span> Stagnan</div>
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-up"></span> Membaik (Upgrade)</div>
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-down"></span> Memburuk (Downgrade)</div>
                    <div class="loan-legend-item"><span class="loan-legend-swatch matrix-new-account"></span> New Account</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade loan-drill-modal" id="loanMatrixDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title font-weight-bold mb-1">Detail Daily Loan Dinamis</h5>
                    <div id="loanDrillSubtitle" class="text-muted" style="font-size: 0.8rem; font-weight: 700;">-</div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="loan-drill-toolbar">
                    <div id="loanDrillMeta" class="loan-drill-meta"></div>
                    <div class="d-flex flex-wrap" style="gap: 0.5rem;">
                        <button id="loanDrillLoadMoreButton" type="button" class="btn btn-sm btn-outline-primary d-none">
                            <i class="fas fa-plus mr-1"></i> Muat Lagi
                        </button>
                        <button id="loanDrillExportButton" type="button" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel mr-1"></i> Excel
                        </button>
                    </div>
                </div>
                <div id="loanDrillState" class="loan-drill-state">Memuat data...</div>
                <div id="loanDrillTableWrap" class="loan-drill-table-wrap d-none">
                    <table class="loan-drill-table">
                        <thead id="loanDrillHead"></thead>
                        <tbody id="loanDrillBody"></tbody>
                    </table>
                </div>
                <div id="loanDrillFooterNote" class="loan-drill-footer-note d-none"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanFilterForm');
        const periodInput = document.getElementById('loanPeriodeInput');
        const segmenSelect = document.getElementById('loanSegmenSelect');
        const produkSelect = document.getElementById('loanProdukSelect');
        const cabangSelect = document.getElementById('loanCabangSelect');
        const unitSelect = document.getElementById('loanUnitSelect');
        const body = document.getElementById('loanMatrixBody');
        const foot = document.getElementById('loanMatrixFoot');
        const overlay = document.getElementById('loanLoadingOverlay');
        const loadingCopy = document.getElementById('loanLoadingCopy');
        const loadingPhase = document.getElementById('loanLoadingPhase');
        const loadingPercent = document.getElementById('loanLoadingPercent');
        const loadingProgressBar = document.getElementById('loanLoadingProgressBar');
        const chip = document.getElementById('loanLoadingChip');
        const submitButton = document.getElementById('loanSubmitButton');
        const periodBadge = document.getElementById('loanMatrixPeriodBadge');
        const reconciliationBadge = document.getElementById('loanReconciliationBadge');
        const activePeriodMeta = document.getElementById('loanActivePeriodMeta');
        const comparisonPeriodMeta = document.getElementById('loanComparisonPeriodMeta');
        const currentHeadLabel = document.getElementById('loanCurrentHeadLabel');
        const comparisonHeadLabel = document.getElementById('loanComparisonHeadLabel');
        const totalValueHeader = document.getElementById('loanTotalValueHeader');
        const drillModal = document.getElementById('loanMatrixDetailModal');
        const drillSubtitle = document.getElementById('loanDrillSubtitle');
        const drillMeta = document.getElementById('loanDrillMeta');
        const drillState = document.getElementById('loanDrillState');
        const drillTableWrap = document.getElementById('loanDrillTableWrap');
        const drillHead = document.getElementById('loanDrillHead');
        const drillBody = document.getElementById('loanDrillBody');
        const drillLoadMoreButton = document.getElementById('loanDrillLoadMoreButton');
        const drillExportButton = document.getElementById('loanDrillExportButton');
        const drillFooterNote = document.getElementById('loanDrillFooterNote');
        const filterShell = document.getElementById('loanFilterShell');
        const filterToggle = document.getElementById('loanFilterToggle');
        const filterSelectionSummary = document.getElementById('loanFilterSelectionSummary');

        const filtersUrl = @json(route('report.dashboard-pinjaman.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.data'));
        const detailUrl = @json(route('report.dashboard-pinjaman.matrix.detail'));
        const exportUrl = @json(route('report.dashboard-pinjaman.matrix.export'));
        const qualityColumns = @json($matrixColumns);
        const outputColumns = ['principal_reduction', 'suplesi', 'ph', 'lunas'];
        const qualityRanks = @json(array_flip($matrixColumns));

        let activeFilterController = null;
        let activeController = null;
        let activeMatrixRequestId = 0;
        let snapshotWarmTimer = null;
        let snapshotWarmAttempts = 0;
        let activeFilterRequestId = 0;
        let activeDrillController = null;
        let activeDrillRequestId = 0;
        let activeDrillBucket = null;
        let activeDrillAfterBucket = null;
        let activeDrillNextOffset = null;
        let activeDrillRenderedCount = 0;
        let activeMatrixParams = null;
        let rowClickTimer = null;
        let isNavigatingAway = false;
        let filterReloadTimer = null;
        let filterCascadeTimer = null;
        let isRefreshingFilters = false;
        const filterOptionsCache = new Map();

        const filterSelects = [
            { element: segmenSelect, placeholder: 'Semua Segmen' },
            { element: produkSelect, placeholder: 'Semua Produk' },
            { element: cabangSelect, placeholder: 'Semua Kantor Cabang' },
            { element: unitSelect, placeholder: 'Semua Unit Kerja' }
        ];

        const selectKeyMap = {
            'segmen_dashboard': segmenSelect,
            'produk_dashboard': produkSelect,
            'cabang1': cabangSelect,
            'unit1': unitSelect,
        };

        function formatMatrixPeriodDate(value) {
            if (!value) return '-';
            const date = new Date(`${value}T00:00:00`);
            if (Number.isNaN(date.getTime())) return value;

            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: '2-digit',
            });
        }

        function renderPositionDeltaLabel(selectedPeriod, comparisonPeriod) {
            return `Posisi ${formatMatrixPeriodDate(selectedPeriod)} | Pembanding ${formatMatrixPeriodDate(comparisonPeriod)}`;
        }

        function renderMatrixContextLabel(payload) {
            const positionLabel = renderPositionDeltaLabel(payload?.selected_period, payload?.comparison_period);
            if (!payload?.ph_period) return positionLabel;

            const fallbackLabel = payload.ph_period_relation === 'fallback' ? ' (terakhir tersedia)' : '';
            return `${positionLabel} | PH ${formatMatrixPeriodDate(payload.ph_period)}${fallbackLabel}`;
        }

        function syncMatrixContext(payload) {
            if (!payload) return;
            periodBadge.textContent = renderMatrixContextLabel(payload);
            activePeriodMeta.textContent = formatMatrixPeriodDate(payload.selected_period);
            comparisonPeriodMeta.textContent = formatMatrixPeriodDate(payload.comparison_period);
            if (currentHeadLabel) currentHeadLabel.textContent = formatMatrixPeriodDate(payload.selected_period);
            if (comparisonHeadLabel) comparisonHeadLabel.textContent = formatMatrixPeriodDate(payload.comparison_period);
        }

        function setFilterPanelOpen(open) {
            if (!filterShell || !filterToggle) return;
            filterShell.classList.toggle('is-open', open);
            filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open) {
                document.querySelectorAll('.loan-dropdown').forEach(dropdown => dropdown.classList.remove('is-open'));
            }
        }

        function summarizeFilterSelection(select, singularLabel) {
            const selected = Array.from(select?.selectedOptions || []).filter(option => option.value !== '');
            if (selected.length === 0) return null;
            if (selected.length === 1) return selected[0].text;
            return `${selected.length} ${singularLabel}`;
        }

        function updateFilterSummary() {
            if (!filterSelectionSummary) return;
            const selectedFilters = [
                summarizeFilterSelection(segmenSelect, 'segmen'),
                summarizeFilterSelection(produkSelect, 'produk'),
                summarizeFilterSelection(cabangSelect, 'cabang'),
                summarizeFilterSelection(unitSelect, 'unit'),
            ].filter(Boolean);

            filterSelectionSummary.textContent = selectedFilters.length > 0
                ? selectedFilters.join(' · ')
                : 'Semua portofolio';
            filterToggle?.setAttribute('title', filterSelectionSummary.textContent);
        }

        function abortInFlightRequests() {
            if (activeController) activeController.abort();
            if (activeFilterController) activeFilterController.abort();
            if (activeDrillController) activeDrillController.abort();
            window.clearTimeout(filterReloadTimer);
            window.clearTimeout(filterCascadeTimer);
            window.clearTimeout(snapshotWarmTimer);
        }

        function markMatrixDirty() {
            if (isRefreshingFilters) return;
            if (activeController) activeController.abort();
            if (activeDrillController) activeDrillController.abort();
            window.clearTimeout(snapshotWarmTimer);
            activeMatrixRequestId += 1;
            activeDrillRequestId += 1;
            activeMatrixParams = null;
            activeDrillBucket = null;
            activeDrillAfterBucket = null;
            renderMatrixState('Filter berubah', 'Klik Terapkan untuk memuat Matrix sesuai pilihan terbaru.');
            renderReconciliation(null);
            periodBadge.textContent = 'Perubahan filter belum diterapkan';
            releaseLoadingUi();
        }

        function releaseLoadingUi() {
            overlay.classList.add('is-hidden');
            chip.classList.add('d-none');
            submitButton.disabled = false;
        }

        function startLoadingProgress() {
            updateLoadingProgress(8, 'Mengambil Data', 'Menghubungi server...');
            overlay.classList.remove('is-hidden');
            chip.classList.remove('d-none');
            submitButton.disabled = true;
        }

        function updateLoadingProgress(value, phase, copy) {
            const progress = Math.max(0, Math.min(100, Math.round(value)));
            if (loadingPhase) loadingPhase.textContent = phase;
            if (loadingCopy) loadingCopy.textContent = copy;
            if (loadingPercent) loadingPercent.textContent = `${progress}%`;
            if (loadingProgressBar) loadingProgressBar.style.width = `${progress}%`;
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function displayBucketLabel(bucket) {
            return {
                'DPK 1': 'SML 1',
                'DPK 2': 'SML 2',
                'DPK 3': 'SML 3',
            }[bucket] || bucket || '-';
        }

        function buildDrillParams(beforeBucket, afterBucket, offset = 0) {
            if (!activeMatrixParams) return null;
            const params = new URLSearchParams(activeMatrixParams.toString());
            params.set('before_bucket', beforeBucket);
            if (afterBucket) params.set('after_bucket', afterBucket);
            params.set('offset', offset);
            params.set('limit', 25);
            return params;
        }

        function showDrillModal() {
            if (drillModal.parentElement !== document.body) {
                document.body.appendChild(drillModal);
            }

            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery(drillModal)
                    .off('shown.bs.modal.loanDrill hidden.bs.modal.loanDrill')
                    .on('shown.bs.modal.loanDrill', () => {
                        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.classList.add('loan-drill-backdrop'));
                    })
                    .on('hidden.bs.modal.loanDrill', () => {
                        cleanupDrillModalBackdrop();
                    })
                    .modal({
                        backdrop: true,
                        keyboard: true,
                        show: true,
                    });
                window.setTimeout(() => {
                    if (!drillModal.classList.contains('show')) {
                        cleanupDrillModalBackdrop();
                    }
                }, 1200);
            } else {
                drillModal.classList.add('show');
                drillModal.style.display = 'block';
                drillModal.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');
            }
        }

        function cleanupDrillModalBackdrop() {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
        }

        function setSelectedDrillCell(beforeBucket, afterBucket) {
            body.querySelectorAll('tr.loan-drill-row').forEach(row => {
                row.classList.toggle('is-selected', row.dataset.beforeBucket === beforeBucket);
            });
            body.querySelectorAll('td.loan-drill-cell').forEach(cell => {
                cell.classList.toggle(
                    'is-selected',
                    cell.dataset.beforeBucket === beforeBucket && cell.dataset.afterBucket === afterBucket
                );
            });
        }

        async function openDrilldown(beforeBucket, afterBucket, offset = 0, append = false) {
            if (!beforeBucket || !afterBucket || !activeMatrixParams) return;
            if (activeDrillController) activeDrillController.abort();
            activeDrillController = new AbortController();
            const requestId = ++activeDrillRequestId;
            activeDrillBucket = beforeBucket;
            activeDrillAfterBucket = afterBucket;
            setSelectedDrillCell(beforeBucket, afterBucket);

            if (!append) {
                drillSubtitle.textContent = `Pergeseran: ${displayBucketLabel(beforeBucket)} -> ${displayBucketLabel(afterBucket)}`;
                drillMeta.innerHTML = '';
                drillHead.innerHTML = '';
                drillBody.innerHTML = '';
                activeDrillRenderedCount = 0;
                drillTableWrap.classList.add('d-none');
                drillState.classList.remove('d-none');
                drillState.textContent = 'Memuat data...';
                drillLoadMoreButton.classList.add('d-none');
                drillFooterNote.classList.add('d-none');
                drillFooterNote.textContent = '';
                showDrillModal();
            }

            drillLoadMoreButton.disabled = true;
            const params = buildDrillParams(beforeBucket, afterBucket, offset);
            if (!params) return;

            try {
                const response = await fetch(`${detailUrl}?${params.toString()}`, { signal: activeDrillController.signal });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const payload = await response.json();
                if (requestId !== activeDrillRequestId) return;

                renderDrillRows(payload, append);
                activeDrillNextOffset = payload.next_offset;
                drillLoadMoreButton.classList.toggle('d-none', !payload.has_more);
            } catch (e) {
                if (e.name !== 'AbortError') {
                    drillState.classList.remove('d-none');
                    drillState.textContent = 'Data detail gagal dimuat.';
                    drillTableWrap.classList.add('d-none');
                    drillLoadMoreButton.classList.add('d-none');
                }
            } finally {
                drillLoadMoreButton.disabled = false;
            }
        }

        function renderDrillRows(payload, append) {
            const columns = payload.columns || [];
            const rows = payload.rows || [];

            drillMeta.innerHTML = `
                <span>Posisi: ${escapeHtml(formatMatrixPeriodDate(payload.selected_period))}</span>
                <span>Pembanding: ${escapeHtml(formatMatrixPeriodDate(payload.comparison_period))}</span>
                <span>Dari: ${escapeHtml(displayBucketLabel(payload.before_bucket))}</span>
                <span>Ke: ${escapeHtml(displayBucketLabel(payload.after_bucket))}</span>
                <span>Ditampilkan: ${formatNumber(activeDrillRenderedCount + rows.length)}</span>
            `;

            if (!append) {
                drillHead.innerHTML = `<tr>${columns.map(column => `<th>${escapeHtml(column)}</th>`).join('')}</tr>`;
            }

            if (!rows.length && !append) {
                drillTableWrap.classList.add('d-none');
                drillState.classList.remove('d-none');
                drillState.textContent = 'Tidak ada detail untuk baris pivot ini.';
                return;
            }

                drillState.classList.add('d-none');
                drillTableWrap.classList.remove('d-none');
                drillFooterNote.classList.remove('d-none');
            drillFooterNote.textContent = 'Detail nominatif mengikuti tepat sel pergeseran yang dipilih. Gunakan Excel untuk mengambil seluruh hasil sel ini.';
            renderDrillRowsChunked(columns, rows, append);
        }

        function renderDrillRowsChunked(columns, rows, append) {
            if (!append) {
                drillBody.innerHTML = '';
            }

            let index = 0;
            const chunkSize = 5;

            function renderChunk() {
                const fragment = document.createDocumentFragment();
                const end = Math.min(index + chunkSize, rows.length);

                for (let i = index; i < end; i++) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = columns.map(column => `<td>${escapeHtml(formatDrillCellValue(column, rows[i][column]))}</td>`).join('');
                    fragment.appendChild(tr);
                }

                drillBody.appendChild(fragment);
                index = end;

                if (index < rows.length) {
                    requestAnimationFrame(renderChunk);
                    return;
                }

                activeDrillRenderedCount += rows.length;
                drillMeta.querySelector('span:last-child').textContent = `Ditampilkan: ${formatNumber(activeDrillRenderedCount)}`;
            }

            requestAnimationFrame(renderChunk);
        }

        function formatDrillCellValue(column, value) {
            if (column === 'pivot_before_bucket' || column === 'pivot_after_bucket') {
                return displayBucketLabel(value);
            }

            return value ?? '';
        }

        function exportDrilldown(beforeBucket, afterBucket) {
            if (!beforeBucket || !activeMatrixParams) return;
            const params = buildDrillParams(beforeBucket, afterBucket, 0);
            if (!params) return;
            params.delete('offset');
            params.delete('limit');
            window.location.href = `${exportUrl}?${params.toString()}`;
        }

        async function loadFilterOptions(forceRefresh = false) {
            if (activeFilterController) activeFilterController.abort();
            const requestId = ++activeFilterRequestId;
            if (!periodInput.value) {
                activePeriodMeta.textContent = '-';
                comparisonPeriodMeta.textContent = '-';
                isRefreshingFilters = true;
                filterSelects.forEach(({element}) => {
                    element.innerHTML = '<option value="">Pilih periode dulu</option>';
                    window.jQuery(element).val(null).trigger('change');
                });
                isRefreshingFilters = false;
                return;
            }

            const filterController = new AbortController();
            activeFilterController = filterController;
            const params = new URLSearchParams();
            params.set('periode', periodInput.value);

            Object.keys(selectKeyMap).forEach(key => {
                const select = selectKeyMap[key];
                if (select) {
                    const val = window.jQuery(select).val() || [];
                    val.forEach(v => params.append(key + '[]', v));
                    if (val.length > 0) {
                        console.log(`Including ${key} in filter params:`, val);
                    }
                }
            });

            const cacheKey = params.toString();
            if (!forceRefresh && filterOptionsCache.has(cacheKey)) {
                applyFilterOptions(filterOptionsCache.get(cacheKey));
                activeFilterController = null;
                return;
            }

            let timeoutId = null;
            let didTimeout = false;
            try {
                if (forceRefresh) params.set('refresh', '1');
                timeoutId = window.setTimeout(() => {
                    didTimeout = true;
                    filterController.abort();
                }, 15000);
                const response = await fetch(`${filtersUrl}?${params.toString()}`, { signal: filterController.signal });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const payload = await response.json();
                if (requestId !== activeFilterRequestId) return;

                filterOptionsCache.set(cacheKey, payload);
                applyFilterOptions(payload);
            } catch (e) {
                if (requestId === activeFilterRequestId && (e.name !== 'AbortError' || didTimeout)) {
                    renderFilterLoadError();
                    console.error('Filter load error:', e);
                }
            } finally {
                if (timeoutId) window.clearTimeout(timeoutId);
                if (requestId === activeFilterRequestId) {
                    activeFilterController = null;
                    isRefreshingFilters = false;
                }
            }
        }

        function applyFilterOptions(payload) {
            isRefreshingFilters = true;
            setSelectOptions(segmenSelect, payload.segments || [], 'Semua Segmen');
            setSelectOptions(produkSelect, payload.products || [], 'Semua Produk');
            setSelectOptions(cabangSelect, payload.branches || [], 'Semua Kantor Cabang');
            setSelectOptions(unitSelect, payload.units || [], 'Semua Unit Kerja');
            isRefreshingFilters = false;
        }

        function renderFilterLoadError() {
            document.querySelectorAll('[data-loan-dropdown-menu]:not([data-loan-dropdown-menu="periode"])').forEach(menu => {
                if (menu.children.length > 0 && !menu.querySelector('.fa-spinner')) return;
                menu.innerHTML = '<button type="button" class="btn btn-link btn-sm btn-block text-danger" data-loan-filter-retry><i class="fas fa-redo mr-2"></i>Gagal memuat. Coba lagi</button>';
            });
        }

        function scheduleFilterOptionsReload() {
            window.clearTimeout(filterCascadeTimer);
            filterCascadeTimer = window.setTimeout(() => loadFilterOptions(), 180);
        }

        function setSelectOptions(select, items, placeholder) {
            const selected = parseSelectedDataset(select);
            const $select = window.jQuery(select);

            select.innerHTML = '';
            items.forEach(item => {
                const opt = new Option(item, item, false, selected.includes(String(item)));
                select.add(opt);
            });

            // Refresh Select2 display
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
                initMultiSelect(select, placeholder);
            }

            $select.val(selected).trigger('change');
        }

        function renderMatrixState(title, message, canRetry = false) {
            const retry = canRetry
                ? '<button type="button" class="btn btn-sm btn-outline-primary mt-3" data-matrix-retry><i class="fas fa-redo mr-2"></i>Coba Lagi</button>'
                : '';
            body.innerHTML = `
                <tr>
                    <td colspan="${qualityColumns.length + outputColumns.length + 2}" class="loan-empty-state">
                        <div class="py-4">
                            <strong class="d-block mb-1">${escapeHtml(title)}</strong>
                            <p class="mb-0 text-muted">${escapeHtml(message || '')}</p>
                            ${retry}
                        </div>
                    </td>
                </tr>
            `;
            foot.innerHTML = '';
        }

        function renderReconciliation(reconciliation) {
            if (!reconciliationBadge) return;
            reconciliationBadge.classList.remove('badge-light', 'badge-success', 'badge-danger', 'text-muted');

            if (reconciliation?.status === 'balanced') {
                reconciliationBadge.classList.add('badge-success');
                reconciliationBadge.textContent = 'REKONSILIASI SESUAI';
            } else if (reconciliation?.status === 'mismatch') {
                reconciliationBadge.classList.add('badge-danger');
                reconciliationBadge.textContent = 'SELISIH REKONSILIASI';
            } else if (reconciliation?.status === 'error') {
                reconciliationBadge.classList.add('badge-danger');
                reconciliationBadge.textContent = 'DATA GAGAL DIMUAT';
            } else {
                reconciliationBadge.classList.add('badge-light', 'text-muted');
                reconciliationBadge.textContent = 'BELUM DIHITUNG';
            }

            reconciliationBadge.title = reconciliation
                ? `Selisih: ${formatNumber(reconciliation.difference)} | Rekening berjalan: ${formatNumber(reconciliation.matrix_accounts)}`
                : '';
        }

        async function loadMatrix(pushHistory = false, isSnapshotRetry = false, forceRefresh = false) {
            if (activeController) activeController.abort();
            activeController = new AbortController();
            const requestId = ++activeMatrixRequestId;
            const params = new URLSearchParams(new FormData(form));
            const requestParams = new URLSearchParams(params.toString());
            requestParams.set('_ts', Date.now());
            if (forceRefresh) requestParams.set('refresh', '1');

            if (pushHistory) {
                window.history.pushState({}, '', `?${params.toString()}`);
            }

            if (!isSnapshotRetry) {
                window.clearTimeout(snapshotWarmTimer);
                snapshotWarmAttempts = 0;
                startLoadingProgress();
            }
            try {
                const response = await fetch(`${dataUrl}?${requestParams.toString()}`, { signal: activeController.signal });
                const payload = await response.json();
                if (requestId !== activeMatrixRequestId) return;
                syncMatrixContext(payload);

                if (payload.status === 'warming' || payload.status === 'computing') {
                    snapshotWarmAttempts += 1;
                    const periodLabel = [
                        formatMatrixPeriodDate(payload.comparison_period),
                        formatMatrixPeriodDate(payload.selected_period),
                    ].filter(value => value !== '-').join(' vs ');

                    if (snapshotWarmAttempts >= 40) {
                        renderMatrixState('Perhitungan belum selesai', `Data ${periodLabel} masih dihitung.`, true);
                        releaseLoadingUi();
                        return;
                    }

                    updateLoadingProgress(45, 'Menghitung Matrix', `Merekonsiliasi data ${periodLabel}. Memuat ulang otomatis...`);
                    snapshotWarmTimer = window.setTimeout(() => {
                        if (requestId === activeMatrixRequestId) loadMatrix(false, true);
                    }, Number(payload.retry_after_ms) || 3000);
                    return;
                }

                if (!response.ok || payload.status === 'error') {
                    throw new Error(payload.message || `HTTP ${response.status}`);
                }
                if (payload.status === 'empty') {
                    activeMatrixParams = null;
                    renderMatrixState('Data pembanding belum tersedia', payload.message || 'Pilih periode lain.');
                    renderReconciliation(null);
                    releaseLoadingUi();
                    return;
                }

                renderRows(payload.matrix_rows || []);
                renderFoot(payload.grand_totals, payload.grand_total_value);
                renderReconciliation(payload.reconciliation);
                activeMatrixParams = new URLSearchParams(params.toString());
                updateLoadingProgress(100, 'Selesai', 'Data dimuat.');
                setTimeout(releaseLoadingUi, 300);
            } catch (e) {
                if (e.name !== 'AbortError') {
                    activeMatrixParams = null;
                    renderMatrixState('Data gagal dimuat', e.message || 'Terjadi kesalahan saat menghitung matrix.', true);
                    renderReconciliation({ status: 'error', difference: null, matrix_accounts: null });
                    releaseLoadingUi();
                    console.error('Matrix load error:', e);
                }
            }
        }

        function buildRowHtml(row) {
            const rowRank = qualityRanks[row.label];
            const isNewAccount = row.label === 'New Account';
            let html = `<tr class="loan-drill-row" data-before-bucket="${escapeHtml(row.label)}"><th>${escapeHtml(displayBucketLabel(row.label))}</th>`;
            
            for (let idx = 0; idx < row.values.length; idx++) {
                const val = row.values[idx];
                const afterBucket = qualityColumns[idx];
                const cls = isNewAccount ? 'matrix-new-account' : (rowRank === idx ? 'matrix-stagnant' : (rowRank > idx ? 'matrix-up' : 'matrix-down'));
                const hasValue = val !== null && val !== undefined && Number(val) !== 0;
                const cellClass = hasValue ? `${cls} loan-drill-cell` : 'matrix-empty';
                const cellAttrs = hasValue ? ` data-before-bucket="${escapeHtml(row.label)}" data-after-bucket="${escapeHtml(afterBucket)}"` : '';
                html += `<td class="${cellClass}"${cellAttrs}>${formatNumber(val)}</td>`;
            }
            
            html += `<td class="matrix-total-col">${formatNumber(row.total)}</td>`;
            
            for (let i = 0; i < outputColumns.length; i++) {
                const col = outputColumns[i];
                html += `<td>${formatNumber(row.metrics[col])}</td>`;
            }
            
            html += '</tr>';
            return html;
        }

        function renderRows(rows) {
            if (!Array.isArray(rows) || rows.length === 0) {
                renderMatrixState('Tidak ada data', 'Tidak ditemukan rekening untuk kombinasi filter ini.');
                return;
            }

            const chunkSize = Math.max(12, Math.ceil(rows.length / 8));
            
            if (rows.length <= 15) {
                // Small dataset: render directly
                body.innerHTML = rows.map(buildRowHtml).join('');
                updateLoadingProgress(95, 'Memformat Data', 'Hampir selesai...');
            } else {
                // Large dataset: progressive rendering with DocumentFragment
                body.innerHTML = '';
                let index = 0;
                
                function renderChunk() {
                    const fragment = document.createDocumentFragment();
                    const endIndex = Math.min(index + chunkSize, rows.length);
                    
                    for (let i = index; i < endIndex; i++) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = buildRowHtml(rows[i]).slice(4, -5); // Extract inner HTML
                        fragment.appendChild(tr);
                    }
                    
                    body.appendChild(fragment);
                    
                    const progress = Math.round((endIndex / rows.length) * 90) + 5;
                    updateLoadingProgress(progress, 'Memformat Data', `${endIndex} dari ${rows.length} baris...`);
                    
                    index = endIndex;
                    if (index < rows.length) {
                        requestAnimationFrame(renderChunk);
                    }
                }
                
                renderChunk();
            }
        }

        function renderFoot(totals, totalVal) {
            let html = '<tr><th>Grand Total</th>';
            
            for (let i = 0; i < qualityColumns.length; i++) {
                html += `<td>${formatNumber(totals.matrix[i])}</td>`;
            }
            
            html += `<td class="matrix-total-col">${formatNumber(totalVal)}</td>`;
            
            for (let i = 0; i < outputColumns.length; i++) {
                const col = outputColumns[i];
                html += `<td>${formatNumber(totals.metrics[col])}</td>`;
            }
            
            html += '</tr>';
            foot.innerHTML = html;
        }

        form.addEventListener('submit', e => {
            e.preventDefault();
            updateFilterSummary();
            setFilterPanelOpen(false);
            loadMatrix(true);
        });
        periodInput.addEventListener('change', () => {
            markMatrixDirty();
            updateFilterSummary();
            loadFilterOptions();
        });
        periodInput.addEventListener('input', () => {
            markMatrixDirty();
            window.clearTimeout(filterReloadTimer);
            filterReloadTimer = window.setTimeout(() => {
                if (periodInput.value) {
                    loadFilterOptions();
                }
            }, 300);
        });
        body.addEventListener('click', event => {
            if (event.target.closest('[data-matrix-retry]')) {
                loadMatrix(false, false, true);
            }
        });
        body.addEventListener('dblclick', event => {
            const cell = event.target.closest('td.loan-drill-cell');
            if (!cell) return;
            window.clearTimeout(rowClickTimer);
            openDrilldown(cell.dataset.beforeBucket, cell.dataset.afterBucket);
        });
        drillLoadMoreButton.addEventListener('click', () => {
            if (activeDrillBucket && activeDrillNextOffset !== null) {
                openDrilldown(activeDrillBucket, activeDrillAfterBucket, activeDrillNextOffset, true);
            }
        });
        drillExportButton.addEventListener('click', () => exportDrilldown(activeDrillBucket, activeDrillAfterBucket));
        drillModal.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', () => cleanupDrillModalBackdrop());
        });
        
        filterSelects.forEach(({element, placeholder}) => {
            initMultiSelect(element, placeholder);
            window.jQuery(element).on('change', () => {
                syncSelectedDataset(element);
                if (!isRefreshingFilters) markMatrixDirty();
                if (!isRefreshingFilters && (element === segmenSelect || element === cabangSelect)) {
                    scheduleFilterOptionsReload();
                }
            });
        });

        // ── Modern Dropdown Sync Logic ──
        function initModernDropdowns() {
            const dropdownConfigs = [
                { id: 'loanPeriodeInput', key: 'periode' },
                { id: 'loanSegmenSelect', key: 'segmen' },
                { id: 'loanProdukSelect', key: 'produk' },
                { id: 'loanCabangSelect', key: 'cabang' },
                { id: 'loanUnitSelect', key: 'unit' }
            ];

            dropdownConfigs.forEach(conf => {
                const select = document.getElementById(conf.id);
                const menu = document.querySelector(`[data-loan-dropdown-menu="${conf.key}"]`);
                const toggle = document.querySelector(`[data-loan-dropdown-toggle="${conf.key}"]`);
                if (!select || !menu || !toggle) return;
                
                const textSpan = toggle.querySelector('.loan-dropdown-text');

                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const parent = toggle.closest('.loan-dropdown');
                    const isOpen = parent.classList.contains('is-open');
                    document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                    if (!isOpen) parent.classList.add('is-open');
                    if (conf.key !== 'periode' && select.options.length === 0 && !activeFilterController) {
                        loadFilterOptions();
                    }
                });

                // Update UI when select changes (manual or from original script)
                window.jQuery(select).on('change.modern', () => syncCustomMenu(select, menu, textSpan));
                
                // Watch for DOM changes (when loadFilterOptions repopulates options)
                const observer = new MutationObserver(() => syncCustomMenu(select, menu, textSpan));
                observer.observe(select, { childList: true });

                syncCustomMenu(select, menu, textSpan);
            });

            document.addEventListener('click', () => {
                document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
            });

            // Date Input Sync
            if (periodInput && periodDisplay) {
                periodInput.addEventListener('change', () => {
                    periodDisplay.textContent = formatMatrixPeriodDate(periodInput.value);
                });
            }
        }

        function syncCustomMenu(select, menu, textSpan) {
            const options = Array.from(select.options);
            const selectedValues = Array.from(select.selectedOptions).map(o => o.value);
            const placeholder = select.dataset.placeholder || 'Semua';

            menu.innerHTML = '';
            
            if (options.length === 0 || (options.length === 1 && options[0].value === "")) {
                menu.innerHTML = '<div class="px-3 py-3 text-center text-muted small"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat...</div>';
                return;
            }

            options.forEach(opt => {
                if (opt.value === "") return;
                const item = document.createElement('div');
                const isActive = selectedValues.includes(opt.value);
                item.className = `loan-dropdown-option ${isActive ? 'is-active' : ''}`;
                item.innerHTML = '<div class="loan-dropdown-check"><i class="fas fa-check"></i></div><span></span>';
                item.querySelector('span').textContent = opt.text;
                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (select.hasAttribute('multiple')) {
                        opt.selected = !opt.selected;
                    } else {
                        select.value = opt.value;
                        document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                    }
                    window.jQuery(select).trigger('change');
                });
                menu.appendChild(item);
            });

            if (selectedValues.length === 0) {
                textSpan.textContent = placeholder;
            } else if (selectedValues.length === 1) {
                const text = select.selectedOptions[0].text;
                textSpan.textContent = text.length > 22 ? text.substring(0, 19) + '...' : text;
            } else {
                textSpan.textContent = `${selectedValues.length} Terpilih`;
            }

            updateFilterSummary();
        }

        const periodDisplay = document.getElementById('loanPeriodeDisplay');
        document.addEventListener('click', event => {
            if (event.target.closest('[data-loan-filter-retry]')) {
                event.stopPropagation();
                loadFilterOptions(true);
            }
        });
        initModernDropdowns();

        if (filterToggle) {
            filterToggle.addEventListener('click', event => {
                event.stopPropagation();
                setFilterPanelOpen(!filterShell.classList.contains('is-open'));
            });
        }

        updateFilterSummary();
        setFilterPanelOpen(false);

        const initialFilterLoad = periodInput.value ? loadFilterOptions() : Promise.resolve();
        if (new URLSearchParams(window.location.search).has('periode')) {
            initialFilterLoad.then(() => loadMatrix(false));
        }
        window.addEventListener('popstate', () => window.location.reload());
    });
</script>
@endpush

@endsection
