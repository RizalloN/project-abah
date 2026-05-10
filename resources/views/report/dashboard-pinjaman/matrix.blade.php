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
            return \Carbon\Carbon::parse($period)->format('d M y');
        } catch (\Throwable) {
            return $period;
        }
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
        min-width: 320px;
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
</style>

<div class="loan-dashboard pt-4 px-3">
    <div class="loan-recovery-header">
        <h1>Report Recovery</h1>
        <p>Matrix kolektibilitas dengan recovery dari LW325 PH: turunan pokok dan lunas.</p>
    </div>

    <div id="loanMatrixPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <form id="loanFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman.matrix') }}">
                    <div class="loan-filter-modern animate-reveal stagger-1">
                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Posisi Laporan</label>
                            <div class="loan-dropdown" data-loan-dropdown="periode">
                                <i class="fas fa-calendar-alt loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" onclick="document.getElementById('loanPeriodeInput').showPicker()">
                                    <span class="loan-dropdown-text" id="loanPeriodeDisplay">{{ $formatMatrixPeriod($requestedPeriod ?: $selectedPeriod) }}</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <input id="loanPeriodeInput" type="date" name="periode" 
                                    style="opacity: 0; position: absolute; width: 100%; height: 100%; top: 0; left: 0; pointer-events: none;" 
                                    value="{{ $requestedPeriod ?: $selectedPeriod }}" max="{{ $periods->first() }}">
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
                                <i class="fas fa-search"></i> FILTER
                            </button>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 pb-3 border-bottom">
                        <div class="loan-filter-meta">
                            <span>Posisi Data: <strong id="loanActivePeriodMeta">{{ $formatMatrixPeriod($selectedPeriod) }}</strong></span>
                            <span>Delta MTD: <strong id="loanComparisonPeriodMeta">{{ $formatMatrixPeriod($comparisonPeriod) }}</strong></span>
                        </div>
                        <div id="loanLoadingChip" class="loan-loading-chip d-none">
                            <span class="loan-loading-dot"></span>
                            MENYIAPKAN DATA...
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
                        <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-th-large mr-2 text-muted"></i> Data Matriks Pergeseran <span id="loanMatrixPeriodBadge" class="text-primary ml-1" style="font-size: 0.9rem;">Posisi {{ $formatMatrixPeriod($selectedPeriod) }} | Delta MTD {{ $formatMatrixPeriod($comparisonPeriod) }}</span></h5>
                        <div class="text-right">
                             <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.7rem;">UNIT: IDR JUTA</span>
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
                                    <th class="matrix-before">Kualitas Delta MTD<br><span id="loanComparisonHeadLabel">{{ $formatMatrixPeriod($comparisonPeriod) }}</span></th>
                                    <th colspan="{{ count($matrixColumns) }}" class="matrix-after-group">Kualitas Posisi <span id="loanCurrentHeadLabel">{{ $formatMatrixPeriod($selectedPeriod) }}</span></th>
                                    <th rowspan="2" class="matrix-total-head">Delta MTD<br><span id="loanTotalValueHeader">per Baris</span></th>
                                    <th colspan="4" class="matrix-subhead">Data Output (IDR)</th>
                                </tr>
                                <tr>
                                    <th class="matrix-before">Bucket</th>
                                    @foreach($matrixColumns as $col)
                                        <th class="matrix-subhead">{{ $col }}</th>
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
        let activeFilterRequestId = 0;
        let activeDrillController = null;
        let activeDrillRequestId = 0;
        let activeDrillBucket = null;
        let activeDrillNextOffset = null;
        let activeDrillRenderedCount = 0;
        let rowClickTimer = null;
        let isNavigatingAway = false;
        let filterReloadTimer = null;
        let isRefreshingFilters = false;

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
            return `Posisi ${formatMatrixPeriodDate(selectedPeriod)} | Delta MTD ${formatMatrixPeriodDate(comparisonPeriod)}`;
        }

        function abortInFlightRequests() {
            if (activeController) activeController.abort();
            if (activeFilterController) activeFilterController.abort();
            if (activeDrillController) activeDrillController.abort();
            window.clearTimeout(filterReloadTimer);
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

        function buildDrillParams(beforeBucket, offset = 0) {
            const params = new URLSearchParams(new FormData(form));
            params.set('before_bucket', beforeBucket);
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

        function setSelectedDrillRow(beforeBucket) {
            body.querySelectorAll('tr.loan-drill-row').forEach(row => {
                row.classList.toggle('is-selected', row.dataset.beforeBucket === beforeBucket);
            });
        }

        async function openDrilldown(beforeBucket, offset = 0, append = false) {
            if (activeDrillController) activeDrillController.abort();
            activeDrillController = new AbortController();
            const requestId = ++activeDrillRequestId;
            activeDrillBucket = beforeBucket;
            setSelectedDrillRow(beforeBucket);

            if (!append) {
                drillSubtitle.textContent = `Bucket pivot: ${beforeBucket}`;
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
            const params = buildDrillParams(beforeBucket, offset);

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
                <span>Delta MTD: ${escapeHtml(formatMatrixPeriodDate(payload.comparison_period))}</span>
                <span>Bucket: ${escapeHtml(payload.before_bucket)}</span>
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
            drillFooterNote.textContent = 'Detail dimuat bertahap per 25 baris agar halaman tetap ringan. Gunakan Excel untuk mengambil seluruh hasil filter pivot.';
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
                    tr.innerHTML = columns.map(column => `<td>${escapeHtml(rows[i][column] ?? '')}</td>`).join('');
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

        function exportDrilldown(beforeBucket) {
            if (!beforeBucket) return;
            const params = buildDrillParams(beforeBucket, 0);
            params.delete('offset');
            params.delete('limit');
            window.location.href = `${exportUrl}?${params.toString()}`;
        }

        async function loadFilterOptions() {
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

            activeFilterController = new AbortController();
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

            try {
                console.log('Fetching filter options with params:', params.toString());
                const response = await fetch(`${filtersUrl}?${params.toString()}`, { signal: activeFilterController.signal });
                const payload = await response.json();
                if (requestId !== activeFilterRequestId) return;

                console.log('Filter options response:', payload);

                activePeriodMeta.textContent = formatMatrixPeriodDate(payload.selected_period);
                comparisonPeriodMeta.textContent = formatMatrixPeriodDate(payload.comparison_period);
                if (currentHeadLabel) currentHeadLabel.textContent = formatMatrixPeriodDate(payload.selected_period);
                if (comparisonHeadLabel) comparisonHeadLabel.textContent = formatMatrixPeriodDate(payload.comparison_period);

                isRefreshingFilters = true;
                setSelectOptions(segmenSelect, payload.segments || [], 'Semua Segmen');
                setSelectOptions(produkSelect, payload.products || [], 'Semua Produk');
                setSelectOptions(cabangSelect, payload.branches || [], 'Semua Kantor Cabang');
                console.log(`Setting unit options. Backend returned ${(payload.units || []).length} units`);
                setSelectOptions(unitSelect, payload.units || [], 'Semua Unit Kerja');
                isRefreshingFilters = false;
            } catch (e) {
                console.error('Filter load error:', e);
            }
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

        async function loadMatrix(pushHistory = false) {
            if (activeController) activeController.abort();
            activeController = new AbortController();
            const requestId = ++activeMatrixRequestId;
            const params = new URLSearchParams(new FormData(form));
            params.set('_ts', Date.now());

            startLoadingProgress();
            try {
                const response = await fetch(`${dataUrl}?${params.toString()}`, { signal: activeController.signal });
                const payload = await response.json();
                if (requestId !== activeMatrixRequestId) return;

                renderRows(payload.matrix_rows);
                renderFoot(payload.grand_totals, payload.grand_total_value);
                periodBadge.textContent = renderPositionDeltaLabel(payload.selected_period, payload.comparison_period);
                activePeriodMeta.textContent = formatMatrixPeriodDate(payload.selected_period);
                comparisonPeriodMeta.textContent = formatMatrixPeriodDate(payload.comparison_period);
                if (currentHeadLabel) currentHeadLabel.textContent = formatMatrixPeriodDate(payload.selected_period);
                if (comparisonHeadLabel) comparisonHeadLabel.textContent = formatMatrixPeriodDate(payload.comparison_period);
                
                if (pushHistory) window.history.replaceState({}, '', `?${params.toString()}`);
                updateLoadingProgress(100, 'Selesai', 'Data dimuat.');
                setTimeout(releaseLoadingUi, 300);
            } catch (e) {
                if (e.name !== 'AbortError') releaseLoadingUi();
            }
        }

        function buildRowHtml(row) {
            const rowRank = qualityRanks[row.label];
            const isNewAccount = row.label === 'New Account';
            let html = `<tr class="loan-drill-row" data-before-bucket="${escapeHtml(row.label)}"><th>${escapeHtml(row.label)}</th>`;
            
            for (let idx = 0; idx < row.values.length; idx++) {
                const val = row.values[idx];
                const cls = isNewAccount ? 'matrix-new-account' : (rowRank === idx ? 'matrix-stagnant' : (rowRank > idx ? 'matrix-up' : 'matrix-down'));
                html += `<td class="${val ? cls : 'matrix-empty'}">${formatNumber(val)}</td>`;
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

        form.addEventListener('submit', e => { e.preventDefault(); loadMatrix(true); });
        periodInput.addEventListener('change', () => {
            loadFilterOptions();
        });
        periodInput.addEventListener('input', () => {
            window.clearTimeout(filterReloadTimer);
            filterReloadTimer = window.setTimeout(() => {
                if (periodInput.value) {
                    loadFilterOptions();
                }
            }, 300);
        });
        body.addEventListener('click', event => {
            const row = event.target.closest('tr.loan-drill-row');
            if (!row) return;
            window.clearTimeout(rowClickTimer);
            rowClickTimer = window.setTimeout(() => openDrilldown(row.dataset.beforeBucket), 220);
        });
        body.addEventListener('dblclick', event => {
            const row = event.target.closest('tr.loan-drill-row');
            if (!row) return;
            window.clearTimeout(rowClickTimer);
            activeDrillBucket = row.dataset.beforeBucket;
            setSelectedDrillRow(activeDrillBucket);
            exportDrilldown(activeDrillBucket);
        });
        drillLoadMoreButton.addEventListener('click', () => {
            if (activeDrillBucket && activeDrillNextOffset !== null) {
                openDrilldown(activeDrillBucket, activeDrillNextOffset, true);
            }
        });
        drillExportButton.addEventListener('click', () => exportDrilldown(activeDrillBucket));
        drillModal.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', () => cleanupDrillModalBackdrop());
        });
        
        filterSelects.forEach(({element, placeholder}) => {
            initMultiSelect(element, placeholder);
            window.jQuery(element).on('change', () => {
                syncSelectedDataset(element);
                const selectedValue = window.jQuery(element).val();
                const elementId = element.id;
                console.log(`Filter changed: ${elementId} =`, selectedValue);
                if (!isRefreshingFilters) {
                    console.log(`Loading filter options due to ${elementId} change...`);
                    loadFilterOptions();
                }
            });
        });

        // ── Modern Dropdown Sync Logic ──
        function initModernDropdowns() {
            const dropdownConfigs = [
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
                item.innerHTML = `
                    <div class="loan-dropdown-check">
                        <i class="fas fa-check"></i>
                    </div>
                    <span>${opt.text}</span>
                `;
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
        }

        const periodDisplay = document.getElementById('loanPeriodeDisplay');
        initModernDropdowns();

        if (periodInput.value) loadFilterOptions();
    });
</script>
@endpush

@endsection
