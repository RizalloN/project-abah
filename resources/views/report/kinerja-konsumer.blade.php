@extends('layouts.admin')

@section('title', 'Kinerja Konsumer')

@section('content')
<style>
    :root {
        --loan-surface: #ffffff;
        --loan-surface-soft: #f8fbff;
        --loan-border: #e2e8f0;
        --loan-border-strong: #cbd5e1;
        --loan-text: #1e293b;
        --loan-muted: #64748b;
        --loan-blue: #2563eb;
        --loan-blue-deep: #1e40af;
        --loan-blue-ink: #0f172a;
        --loan-blue-soft: #f0f7ff;
        --loan-red: #ef4444;
        --loan-green: #10b981;
        --loan-cyan: #06b6d4;
        --loan-radius: 20px;
        --loan-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    }

    .kinerja-konsumer-shell {
        position: relative;
        border: 1px solid var(--loan-border);
        border-radius: var(--loan-radius);
        background: #ffffff;
        box-shadow: var(--loan-shadow);
        overflow: hidden;
        margin-bottom: 2.5rem;
        transition: transform 0.3s ease;
    }

    .kinerja-konsumer-shell::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 6px;
        background: linear-gradient(90deg, #1e3a8a, #3b82f6, #06b6d4);
        z-index: 5;
    }

    .kinerja-konsumer-header {
        padding: 2.5rem 2.25rem 2rem;
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.5) 0%, #ffffff 100%);
        border-bottom: 1px solid var(--loan-border);
        text-align: center;
    }

    .kinerja-konsumer-title {
        margin: 0;
        font-size: 2.25rem;
        font-weight: 800;
        color: var(--loan-blue-ink);
        letter-spacing: -0.02em;
    }

    .kinerja-konsumer-badges {
        display: none;
    }

    .kinerja-konsumer-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 1rem;
        border-radius: 12px;
        border: 1px solid var(--loan-border);
        background: #ffffff;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 700;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease;
    }

    .kinerja-konsumer-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-color: var(--loan-blue);
    }

    .kinerja-konsumer-filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
        margin-top: 1.5rem;
        padding: 1.5rem;
        border-radius: 16px;
        background: rgba(248, 250, 252, 0.8);
        border: 1px solid var(--loan-border);
        backdrop-filter: blur(8px);
    }

    .kinerja-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }

    .kinerja-filter-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding-left: 0.2rem;
    }

    .kinerja-filter-control {
        width: 100%;
        border: 1px solid var(--loan-border);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        background: #ffffff;
        color: var(--loan-text);
        font-size: 0.9rem;
        font-weight: 700;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
    }

    .kinerja-filter-control:hover {
        border-color: var(--loan-border-strong);
        background-color: #fcfdfe;
    }

    .kinerja-filter-control:focus {
        border-color: var(--loan-blue);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        outline: none;
    }

    .kinerja-table-shell {
        padding: 0.75rem;
        background: #ffffff;
    }

    .kinerja-table-container {
        position: relative;
        max-height: 70vh;
        overflow: auto;
        border-radius: 12px;
        border: 1px solid var(--loan-border);
    }

    .kinerja-konsumer-table {
        width: 100%;
        min-width: 1400px;
        border-collapse: separate;
        border-spacing: 0;
    }

    /* Modern Sticky Header with Glass Effect */
    .kinerja-konsumer-table thead th {
        position: sticky;
        top: 0;
        z-index: 50;
        background: rgba(15, 23, 42, 0.95) !important;
        backdrop-filter: blur(8px);
        color: #f8fafc;
        padding: 0.4rem 0.3rem !important;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04rem;
        border-bottom: 2px solid #334155;
        text-align: center !important;
        vertical-align: middle !important;
        white-space: nowrap;
        height: 38px;
    }

    .kinerja-konsumer-table thead tr:nth-child(2) th {
        top: 38px; /* Must match Row 1 height */
        height: 34px;
    }

    .kinerja-konsumer-table th.sub-head {
        background: rgba(30, 41, 59, 1) !important;
        color: #e2e8f0;
    }

    .kinerja-konsumer-table th.accent-head {
        background: rgba(30, 58, 138, 1) !important;
        color: #f0f7ff;
    }

    .kinerja-konsumer-table td {
        padding: 0.4rem 0.5rem;
        font-size: 0.74rem;
        font-weight: 700;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        text-align: right;
    }

    .kinerja-konsumer-table td.merged-branch-cell {
        background: #ffffff !important;
        border-left: 5px solid var(--loan-blue) !important;
        color: var(--loan-blue-ink) !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        text-align: center !important;
        font-size: 0.62rem !important;
        padding: 0.5rem 0.6rem !important;
        position: sticky !important;
        left: 0;
        z-index: 20;
    }

    .kinerja-konsumer-table td.merged-rm-cell {
        background: #ffffff !important;
        color: #475569 !important;
        text-align: left !important;
        font-size: 0.72rem !important;
        font-weight: 800 !important;
        padding: 0.5rem 0.75rem !important;
        position: sticky !important;
        left: 120px; /* Aligned with Branch width */
        z-index: 10;
        border-right: 1px solid #f1f5f9 !important;
    }

    /* Current Position Highlight Class */
    .highlight-curr {
        background: #f0f7ff !important;
        color: #2563eb !important;
        font-weight: 800 !important;
    }

    .loan-branch-subtotal .highlight-curr {
        background: #38bdf8 !important;
        color: #ffffff !important;
    }

    .loan-branch-subtotal {
        background: #1e293b !important;
    }

    .loan-branch-subtotal td {
        color: #ffffff !important;
        font-weight: 800 !important;
        border-top: 1.5px solid #334155 !important;
        border-bottom: 1.5px solid #334155 !important;
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }

    .row-grand-total {
        background: #0f172a !important;
        position: sticky;
        bottom: 0;
        z-index: 40;
    }

    .row-grand-total td {
        color: #ffffff !important;
        font-weight: 900 !important;
        font-size: 0.8rem !important;
        border: none !important;
        padding: 0.8rem 0.5rem !important;
    }

    .legend-box {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 1.15rem;
        background: #f8fafc;
        border: 1px solid var(--loan-border);
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
    }

    /* Sophisticated Badges */
    .pct-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 8px;
        font-weight: 800;
        font-size: 0.65rem;
        min-width: 65px;
        text-align: center;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .pct-good { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .pct-mid { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .pct-bad { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    .delta-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 800;
    }
    .delta-indicator.pos { color: #10b981; }
    .delta-indicator.neg { color: #ef4444; }

    .tampilkan-button {
        background: #0f172a;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        height: 48px;
        width: 100%;
    }

    .tampilkan-button:hover {
        background: #1e293b;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.2);
    }

    .tampilkan-button:active {
        transform: translateY(0);
        box-shadow: 0 4px 8px rgba(15, 23, 42, 0.1);
    }

    /* AJAX Loading Styles */
    .kinerja-ajax-wrapper {
        position: relative;
        min-height: 400px;
    }

    .kinerja-loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 100;
    }

    .loading-active .kinerja-loading-overlay {
        display: flex;
    }

    .premium-loader {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.25rem;
    }

    .premium-loader-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid #f1f5f9;
        border-top-color: #2563eb;
        border-radius: 50%;
        animation: premium-spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    }

    @keyframes premium-spin {
        to { transform: rotate(360deg); }
    }

    .premium-loader-text {
        font-weight: 800;
        font-size: 0.85rem;
        color: #1e293b;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
</style>

@php
    $normalize = fn($v) => (float)$v / 1000000;
    $fmt = fn($v) => number_format($normalize($v), 1, ',', '.');
    
    $formatSigned = function($v, $showArrow = true) use ($normalize) {
        $val = $normalize($v);
        $cls = $val > 0 ? 'pos' : ($val < 0 ? 'neg' : '');
        $icon = '';
        
        if ($showArrow) {
            if ($val > 0) $icon = '<i class="fas fa-caret-up me-1"></i>';
            elseif ($val < 0) $icon = '<i class="fas fa-caret-down me-1"></i>';
        }
        
        $prefix = ($val > 0 && !$showArrow) ? '+' : '';
        $display = number_format(abs($val), 1, ',', '.');
        if ($val < 0 && !$showArrow) $display = '-' . $display;
        
        return "<span class='delta-indicator $cls'>$icon$prefix$display</span>";
    };

    $formatPct = function($v) {
        $num = (float)$v;
        $cls = $num >= 100 ? 'pct-good' : ($num >= 95 ? 'pct-mid' : 'pct-bad');
        $icon = $num >= 100 ? '<i class="fas fa-check-circle me-1" style="font-size: 0.6rem;"></i>' : '';
        return "<span class='pct-badge $cls'>$icon" . number_format($num, 1, ',', '.') . "%</span>";
    };
@endphp

<div class="pt-4 px-3">
    <div class="kinerja-konsumer-shell animate-reveal">
        <div class="kinerja-konsumer-header">
            <h1 class="kinerja-konsumer-title">{{ $title }}</h1>
            
            <form id="kinerjaFilterForm" method="GET" action="{{ route('report.dashboard-pinjaman.kinerja-konsumer') }}" class="kinerja-konsumer-filters">
                <div class="kinerja-filter-group">
                    <label for="kinerjaPeriode" class="kinerja-filter-label">Periode Laporan</label>
                    <select id="kinerjaPeriode" name="periode" class="kinerja-filter-control">
                        @foreach($availablePeriods as $period)
                            <option value="{{ $period }}" @selected($selectedPeriod === $period)>
                                {{ \Carbon\Carbon::parse($period)->translatedFormat('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="kinerja-filter-group">
                    <label for="kinerjaCabang" class="kinerja-filter-label">Filter Unit Kerja</label>
                    <select id="kinerjaCabang" name="cabang1" class="kinerja-filter-control">
                        <option value="" @selected($selectedCabang === null)>SEMUA CABANG</option>
                        @foreach($availableCabangs as $cabang)
                            <option value="{{ $cabang }}" @selected($selectedCabang === $cabang)>{{ $cabang }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="kinerja-filter-group">
                    <label for="kinerjaProduk" class="kinerja-filter-label">Jenis Produk</label>
                    <select id="kinerjaProduk" name="produk" class="kinerja-filter-control">
                        <option value="" @selected($selectedProduct === null)>SEMUA PRODUK</option>
                        @foreach($availableProducts as $product)
                            <option value="{{ $product }}" @selected($selectedProduct === $product)>{{ $product }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="kinerja-filter-group d-flex align-items-end justify-content-end">
                    <button type="submit" class="tampilkan-button">
                        <i class="fas fa-search me-2"></i> TAMPILKAN
                    </button>
                </div>
            </form>
        </div>

        <div class="kinerja-ajax-wrapper" id="kinerjaAjaxWrapper">
            <div class="kinerja-loading-overlay">
                <div class="premium-loader">
                    <div class="premium-loader-spinner"></div>
                    <div class="premium-loader-text">Mengolah Data Konsumer...</div>
                </div>
            </div>
            <div id="kinerjaAjaxContainer">
                @include('report.kinerja-konsumer-table')
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('kinerjaFilterForm');
    const ajaxWrapper = document.getElementById('kinerjaAjaxWrapper');
    const ajaxContainer = document.getElementById('kinerjaAjaxContainer');
    const submitButton = filterForm?.querySelector('.tampilkan-button');

    // Request timeout configuration (in milliseconds)
    const REQUEST_TIMEOUT = 45000; // 45 seconds
    let requestAbortController = null;

    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            loadKinerjaData();
        });
    }

    function loadKinerjaData() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = `${filterForm.action}?${params.toString()}`;

        // Show loading state
        ajaxWrapper.classList.add('loading-active');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<div class="spinner-border spinner-border-sm me-2" style="width: 1rem; height: 1rem;"></div> Memproses...';
        }

        // Cancel previous request if still pending
        if (requestAbortController) {
            requestAbortController.abort();
        }
        requestAbortController = new AbortController();
        const controller = requestAbortController;

        // Set timeout
        const timeoutId = setTimeout(() => {
            if (controller === requestAbortController) {
                controller.abort();
            }
        }, REQUEST_TIMEOUT);

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            ajaxContainer.innerHTML = html;
            ajaxWrapper.classList.remove('loading-active');
            
            // Update URL and history without reload
            window.history.pushState({ path: url }, '', url);
            
            // Re-trigger reveal animation if any
            const contentArea = document.getElementById('kinerjaContentArea');
            if (contentArea) {
                contentArea.classList.add('animate-reveal');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            ajaxWrapper.classList.remove('loading-active');
            console.error('Error fetching kinerja data:', error);
            
            let errorMsg = 'Gagal memperbarui data. Silakan coba lagi atau Refresh halaman.';
            if (error.name === 'AbortError') {
                errorMsg = `Permintaan timeout setelah ${REQUEST_TIMEOUT / 1000} detik. Data terlalu besar atau koneksi lambat. Coba gunakan filter lebih spesifik atau lagi nanti.`;
            } else if (error.message.includes('HTTP')) {
                errorMsg = `Server error: ${error.message}`;
            }
            
            showErrorAlert(errorMsg);
        })
        .finally(() => {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-search me-2"></i> TAMPILKAN';
            }
        });
    }

    function showErrorAlert(message) {
        const alertHtml = `<div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin: 1rem;">
            <strong>Error</strong><br>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        ajaxContainer.innerHTML = alertHtml;
    }
});
</script>
@endpush
@endsection
