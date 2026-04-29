@extends('layouts.admin')

@section('title', 'Dashboard Pinjaman Kredit SME')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<style>
    .loan-filter-modern {
        display: grid;
        grid-template-columns: repeat(2, 1fr) auto;
        gap: 1.5rem;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(25px);
        padding: 1.5rem;
        border-radius: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 
            0 10px 15px -3px rgba(0, 0, 0, 0.05),
            0 30px 60px -20px rgba(8, 87, 195, 0.2);
        margin-bottom: 2.5rem;
        position: relative;
        z-index: 1000;
        align-items: flex-end;
    }

    /* Prevent any clipping from parents */
    .loan-shell, .loan-shell .card-body {
        overflow: visible !important;
    }

    .loan-filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        position: relative;
    }

    /* Descending z-index for items */
    .loan-filter-item:nth-child(1) { z-index: 20; }
    .loan-filter-item:nth-child(2) { z-index: 10; }

    .loan-filter-modern .loan-filter-label {
        font-size: 0.75rem;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-left: 0.65rem;
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
        font-size: 1.1rem;
        pointer-events: none;
        opacity: 0.8;
    }

    .loan-dropdown-toggle {
        width: 100%;
        height: 60px;
        background: #ffffff;
        border: 2px solid #e2e8f0;
        border-radius: 18px;
        padding: 0 1.5rem 0 3.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        font-size: 0.95rem;
        color: #1e293b;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .loan-dropdown-toggle:hover {
        border-color: var(--loan-blue);
        box-shadow: 0 10px 25px rgba(8, 87, 195, 0.12);
        transform: translateY(-2px);
    }

    .loan-dropdown.is-open { z-index: 3100 !important; }
    .loan-dropdown.is-open .loan-dropdown-toggle {
        border-color: var(--loan-blue);
        box-shadow: 0 0 0 5px rgba(8, 87, 195, 0.1);
    }

    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 12px);
        left: 0;
        width: 100%;
        min-width: 340px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(25px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 1.75rem;
        box-shadow: 
            0 25px 50px -12px rgba(0, 0, 0, 0.2),
            0 15px 30px -10px rgba(0, 0, 0, 0.1);
        z-index: 3000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(15px);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 500px;
        overflow-y: auto;
        padding: 0.85rem;
    }

    .loan-dropdown.is-open .loan-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .loan-dropdown-option {
        width: 100%;
        padding: 0.85rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        font-weight: 700;
        font-size: 0.9rem;
        color: #475569;
        transition: all 0.2s;
        text-align: left;
        margin-bottom: 4px;
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
        width: 1.4rem;
        height: 1.4rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s;
        font-size: 0.8rem;
        color: white;
    }

    .loan-dropdown-option.is-active .loan-dropdown-check {
        background: var(--loan-blue);
        border-color: var(--loan-blue);
    }

    .btn-loan-modern-submit {
        height: 60px;
        min-width: 220px;
        padding: 0 2rem;
        border-radius: 18px;
        background: linear-gradient(135deg, var(--loan-blue) 0%, #1e40af 100%);
        color: white;
        border: none;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 20px rgba(8, 87, 195, 0.3);
    }

    .btn-loan-modern-submit:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(8, 87, 195, 0.4);
    }

    /* Hide Original */
    .select2-container--bootstrap4, .loan-filter-control {
        display: none !important;
    }
</style>
</style>

<div class="loan-dashboard pt-4 px-3" id="loanDashboardCaptureArea">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="loan-page-title">Dashboard Pinjaman Kredit</h1>
            <p class="text-muted mb-0">Analisis performa portofolio berdasarkan segmen dan kategori.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex align-items-center gap-2">
            <button id="captureAllBtn" class="btn btn-outline-primary btn-capture-all">
                <i class="fas fa-file-image"></i> EXPORT A4 PORTRAIT
            </button>
        </div>
    </div>

    <div class="card loan-shell mb-4 animate-reveal">
        <div class="card-body p-4">
            <div class="loan-filter-modern">
                <div class="loan-filter-item">
                    <label class="loan-filter-label">Periode Terakhir</label>
                    <div class="loan-dropdown" data-loan-dropdown="periode">
                        <i class="fas fa-calendar-day loan-dropdown-icon"></i>
                        <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="periode">
                            <span class="loan-dropdown-text">Pilih Periode</span>
                            <i class="fas fa-chevron-down small opacity-50"></i>
                        </button>
                        <div class="loan-dropdown-menu" data-loan-dropdown-menu="periode">
                            @foreach($periods as $periode)
                                <div class="loan-dropdown-option {{ $periode === $selectedPeriod ? 'is-active' : '' }}" data-value="{{ $periode }}">
                                    <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                    <span>{{ \Carbon\Carbon::parse($periode)->format('d M Y') }}</span>
                                </div>
                            @endforeach
                        </div>
                        <select id="periodeSelector" class="d-none">
                            @foreach($periods as $periode)
                                <option value="{{ $periode }}" @selected($periode === $selectedPeriod)>{{ $periode }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="loan-filter-item">
                    <label class="loan-filter-label">Kategori Portofolio</label>
                    <div class="loan-dropdown" data-loan-dropdown="kategori">
                        <i class="fas fa-tags loan-dropdown-icon"></i>
                        <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="kategori">
                            <span class="loan-dropdown-text">{{ $selectedCategory }}</span>
                            <i class="fas fa-chevron-down small opacity-50"></i>
                        </button>
                        <div class="loan-dropdown-menu" data-loan-dropdown-menu="kategori">
                            @foreach($categories as $cat)
                                <div class="loan-dropdown-option {{ $cat === $selectedCategory ? 'is-active' : '' }}" data-value="{{ $cat }}">
                                    <div class="loan-dropdown-check"><i class="fas fa-check"></i></div>
                                    <span>{{ $cat }}</span>
                                </div>
                            @endforeach
                        </div>
                        <select id="kategoriSelector" class="d-none">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" @selected($cat === $selectedCategory)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <button type="button" class="btn-loan-modern-submit w-100" id="btnLoadData">
                        <i class="fas fa-sync-alt"></i> PERBARUI DASHBOARD
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Meta Information -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div class="loan-loading-chip">
            <span class="loan-loading-dot"></span>
            <span id="dashboardMeta">Menyiapkan dashboard kredit harian.</span>
        </div>
        <div class="text-muted small">Data diambil dari snapshot harian per cabang.</div>
    </div>

    <!-- Dashboard Content -->
    <div id="dashboardContent" class="animate-reveal">
        
        <!-- Consolidation Area 6 Section -->
        <div class="loan-section-block mb-4 d-none" id="consolidationSection">
            <div class="loan-section-header">
                <h3 id="consolidationTitle">KONSOLIDASI AREA 6</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('consolidationSection', 'Snapshot-Konsolidasi')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="consolidationTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'Konsolidasi'])
            </div>
        </div>

        <!-- OS Section -->
        <div class="loan-section-block mb-4" id="osSection">
            <div class="loan-section-header">
                <h3 id="osTitle">A. OUTSTANDING (OS)</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('osSection', 'Snapshot-OS')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="osTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'Outstanding'])
            </div>
        </div>

        <!-- SML Section -->
        <div class="loan-section-block mb-4" id="smlSection">
            <div class="loan-section-header">
                <h3 id="smlTitle">B. SPECIAL MENTION LOAN (SML)</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('smlSection', 'Snapshot-SML')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="smlTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'SML'])
            </div>
        </div>

        <!-- NPL Section -->
        <div class="loan-section-block mb-4" id="nplSection">
            <div class="loan-section-header">
                <h3 id="nplTitle">C. NON-PERFORMING LOAN (NPL)</h3>
                <div class="legend-box ml-auto d-flex align-items-center" style="gap: 1rem;">
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span class="text-muted" style="font-size: 0.75rem;">Dalam <strong>Rp, Juta</strong></span>
                    </div>
                    <button class="btn-snapshot" onclick="window.captureLoanSection('nplSection', 'Snapshot-NPL')" title="Ambil Snapshot">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive loan-summary-table-wrap" id="nplTableContainer">
                @include('report.dashboard-pinjaman._partials._loading_stub', ['label' => 'NPL'])
            </div>
        </div>

    </div>
</div>

<!-- Capture Status Modal -->
<div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div id="captureProgressUI">
                    <div class="capture-status-modal-icon icon-loading">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Menyusun Laporan A4</h4>
                    <p class="text-muted mb-0">Sedang menyusun tabel ringkasan ke dalam beberapa file gambar. Mohon tunggu sebentar...</p>
                </div>

                <div id="captureErrorUI" class="d-none">
                    <div class="capture-status-modal-icon icon-error">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Gagal Mengambil Snapshot</h4>
                    <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kendala saat menyusun snapshot A4.</p>
                    <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                        Tutup & Coba Lagi
                    </button>
                </div>

                <div id="captureSuccessUI" class="d-none">
                    <div class="capture-status-modal-icon icon-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Snapshot Berhasil!</h4>
                    <p class="text-muted mb-4">Semua file snapshot telah berhasil diunduh ke perangkat Anda.</p>
                    <button type="button" class="btn btn-primary w-100" data-dismiss="modal">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const osTableContainer = document.getElementById('osTableContainer');
    const consolidationTableContainer = document.getElementById('consolidationTableContainer');
    const consolidationSection = document.getElementById('consolidationSection');
    const smlTableContainer = document.getElementById('smlTableContainer');
    const nplTableContainer = document.getElementById('nplTableContainer');
    const btnLoadData = document.getElementById('btnLoadData');
    const dashboardMeta = document.getElementById('dashboardMeta');
    const captureAllBtn = document.getElementById('captureAllBtn');
    const captureModal = document.getElementById('captureStatusModal');
    
    // Request timeout (in milliseconds)
    const REQUEST_TIMEOUT = 60000;
    let requestAbortController = null;

    // Select2 elements
    const $periodeSel = $('#periodeSelector');
    const $kategoriSel = $('#kategoriSelector');

    function formatCurrency(value) {
        if (value === null || value === undefined || value === '') return '-';
        let num = Math.round(parseFloat(value) / 1000000);
        const isNeg = num < 0;
        if (isNeg) num = Math.abs(num);
        let formatted = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num);
        return isNeg ? `(${formatted})` : formatted;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch(e) { return dateStr; }
    }

    function formatPctBadge(value, type) {
        const num = parseFloat(value) || 0;
        const typeUpper = (type || '').toUpperCase();
        const isReversed = typeUpper.includes('SML') || typeUpper.includes('NPL');
        let barClass = '';
        let textClass = '';
        
        if (num > 100) {
            barClass = isReversed ? 'bar-danger' : 'bar-success';
            textClass = isReversed ? 'achieve-negative' : 'achieve-positive';
        } else if (num >= 90) {
            barClass = 'bar-warning';
            textClass = 'achieve-neutral';
        } else {
            barClass = isReversed ? 'bar-success' : 'bar-danger';
            textClass = isReversed ? 'achieve-positive' : 'achieve-negative';
        }
        
        const clampedPct = Math.min(100, Math.max(0, Math.abs(num)));
        return `
            <div class="pct-data-bar-wrap">
                <div class="pct-data-bar ${barClass}" style="width: ${clampedPct}%"></div>
                <span class="pct-data-label ${textClass}">${num.toFixed(1)}%</span>
            </div>
        `;
    }

    function getConditionalClass(value, type) {
        const num = parseFloat(value) || 0;
        const typeUpper = (type || '').toUpperCase();
        const isReversed = typeUpper.includes('SML') || typeUpper.includes('NPL');
        
        if (num === 0) return 'achieve-neutral';
        
        // For OS: > 0 is good (green), < 0 is bad (red)
        // For SML/NPL: > 0 is bad (red), < 0 is good (green)
        if (num > 0) {
            return isReversed ? 'achieve-negative' : 'achieve-positive';
        }
        return isReversed ? 'achieve-positive' : 'achieve-negative';
    }

    // --- Select2 Initialization ---
    function initSelect2() {
        if (window.jQuery && window.jQuery.fn.select2) {
            window.jQuery('.select2').each(function () {
                const $el = window.jQuery(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
                $el.select2({ 
                    theme: 'bootstrap4', 
                    width: '100%',
                    dropdownAutoWidth: true
                });
            });
        }
    }
    initSelect2();

    // --- Capture & Export Logic ---
    if (captureAllBtn) {
        captureAllBtn.addEventListener('click', async function() {
            if (typeof html2canvas === 'undefined') {
                alert('Library html2canvas belum dimuat.');
                return;
            }

            const progressText = document.querySelector('#captureProgressUI p');
            captureAllBtn.disabled = true;
            captureAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> EXPORTING...';

            if (window.jQuery) {
                window.jQuery(captureModal).modal({ backdrop: 'static', keyboard: false, show: true });
                document.getElementById('captureProgressUI').classList.remove('d-none');
                document.getElementById('captureErrorUI').classList.add('d-none');
                document.getElementById('captureSuccessUI').classList.add('d-none');
            }

            try {
                const sections = [];
                if (!consolidationSection.classList.contains('d-none')) {
                    sections.push({ id: 'consolidationSection', code: 'CONS', label: 'Konsolidasi Area 6' });
                }
                sections.push(
                    { id: 'osSection', code: 'OS', label: 'Outstanding' },
                    { id: 'smlSection', code: 'SML', label: 'Special Mention' },
                    { id: 'nplSection', code: 'NPL', label: 'Non-Performing' }
                );
                
                const dateStr = $periodeSel.find('option:selected').text().trim().replace(/ /g, '-');
                const kategoriStr = $kategoriSel.val().trim().toUpperCase();

                for (const [index, sec] of sections.entries()) {
                    if (progressText) progressText.innerText = `Memproses ${sec.label} (${index + 1}/3)...`;
                    await new Promise(r => setTimeout(r, 600));

                    const el = document.getElementById(sec.id);
                    if (!el) continue;

                    const snapBtn = el.querySelector('.btn-snapshot');
                    if (snapBtn) snapBtn.style.visibility = 'hidden';

                    const tableCanvas = await html2canvas(el, { 
                        scale: 2, 
                        backgroundColor: '#ffffff',
                        logging: false,
                        useCORS: true
                    });

                    if (snapBtn) snapBtn.style.visibility = 'visible';

                    const finalCanvas = document.createElement('canvas');
                    const headerHeight = 220;
                    finalCanvas.width = tableCanvas.width;
                    finalCanvas.height = tableCanvas.height + headerHeight;
                    const ctx = finalCanvas.getContext('2d');

                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);

                    const scaleFactor = tableCanvas.width / 2400;
                    ctx.fillStyle = '#0857c3';
                    ctx.fillRect(0, 0, finalCanvas.width, 15 * scaleFactor);

                    ctx.fillStyle = '#0f172a';
                    ctx.font = `bold ${56 * scaleFactor}px "Inter", sans-serif`;
                    ctx.fillText('Dashboard Pinjaman Kredit', 40 * scaleFactor, 80 * scaleFactor);

                    ctx.fillStyle = '#64748b';
                    ctx.font = `600 ${28 * scaleFactor}px "Inter", sans-serif`;
                    const headerInfo = `Periode: ${$periodeSel.find('option:selected').text()} | Kategori: ${$kategoriSel.val()} | ${sec.label}`;
                    ctx.fillText(headerInfo, 40 * scaleFactor, 130 * scaleFactor);

                    ctx.strokeStyle = '#e2e8f0';
                    ctx.lineWidth = 2 * scaleFactor;
                    ctx.beginPath();
                    ctx.moveTo(40 * scaleFactor, 170 * scaleFactor);
                    ctx.lineTo(finalCanvas.width - (40 * scaleFactor), 170 * scaleFactor);
                    ctx.stroke();

                    ctx.drawImage(tableCanvas, 0, headerHeight);

                    const link = document.createElement('a');
                    link.download = `Capture_DashboardKredit_${sec.code}_${kategoriStr}-${dateStr}.jpg`;
                    link.href = finalCanvas.toDataURL('image/jpeg', 0.9);
                    link.click();
                    
                    await new Promise(r => setTimeout(r, 300));
                }

                document.getElementById('captureProgressUI').classList.add('d-none');
                document.getElementById('captureSuccessUI').classList.remove('d-none');
            } catch (err) {
                console.error('Capture process failed:', err);
                document.getElementById('captureProgressUI').classList.add('d-none');
                document.getElementById('captureErrorUI').classList.remove('d-none');
            } finally {
                captureAllBtn.disabled = false;
                captureAllBtn.innerHTML = '<i class="fas fa-file-image"></i> EXPORT A4 PORTRAIT';
            }
        });
    }

    window.captureLoanSection = async function(sectionId, title) {
        if (typeof html2canvas === 'undefined') return;
        
        const el = document.getElementById(sectionId);
        if (!el) return;

        const dateStr = $periodeSel.find('option:selected').text().trim().replace(/ /g, '-');
        const kategoriStr = $kategoriSel.val().trim().toUpperCase();
        const sectionCode = sectionId.replace('Section', '').toUpperCase();

        if (window.jQuery) {
            window.jQuery(captureModal).modal({ backdrop: 'static', show: true });
            document.getElementById('captureProgressUI').classList.remove('d-none');
            document.getElementById('captureErrorUI').classList.add('d-none');
            document.getElementById('captureSuccessUI').classList.add('d-none');
        }

        try {
            const snapBtn = el.querySelector('.btn-snapshot');
            if (snapBtn) snapBtn.style.visibility = 'hidden';

            const canvas = await html2canvas(el, { scale: 2, backgroundColor: '#ffffff', logging: false });
            
            if (snapBtn) snapBtn.style.visibility = 'visible';

            const link = document.createElement('a');
            link.download = `Capture_DashboardKredit_${sectionCode}_${kategoriStr}-${dateStr}.jpg`;
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            link.click();

            document.getElementById('captureProgressUI').classList.add('d-none');
            document.getElementById('captureSuccessUI').classList.remove('d-none');
        } catch (err) {
            document.getElementById('captureProgressUI').classList.add('d-none');
            document.getElementById('captureErrorUI').classList.remove('d-none');
        }
    };

    // Fix for "blackout"
    document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (window.jQuery) {
                window.jQuery(captureModal).modal('hide');
                window.jQuery('.modal-backdrop').remove();
                window.jQuery('body').removeClass('modal-open').css('padding-right', '');
            }
        });
    });

    // --- Table Building Logic ---
    function buildConsolidationTable(osData, smlData, nplData, headerDates, segmentName, rkaLabels) {
        if (!osData || !smlData || !nplData) return '';

        const dates = { 
            ytd: formatDate(headerDates.ytd), 
            m2: formatDate(headerDates.m2), 
            mtm: formatDate(headerDates.mtm), 
            selected: formatDate(headerDates.selected) 
        };

        const osTotal = osData.find(r => r.is_total);
        const smlTotal = smlData.find(r => r.is_total);
        const nplTotal = nplData.find(r => r.is_total);

        // Helper to consolidate categories
        const getCategoryConsolidation = (rows) => {
            const categories = {};
            rows.filter(r => !r.is_total).forEach(r => {
                if (!categories[r.category]) {
                    categories[r.category] = { 
                        label: r.category, ytd: 0, m2: 0, mtm: 0, selected: 0, 
                        delta_ytd: 0, delta_mtd: 0, delta_dtd: 0,
                        rka_m1: 0, rka_current: 0, penc_m1_rp: 0, penc_cur_rp: 0,
                        penc_m1_pct: 0, penc_cur_pct: 0
                    };
                }
                const c = categories[r.category];
                c.ytd += parseFloat(r.ytd || 0);
                c.m2 += parseFloat(r.m2 || 0);
                c.mtm += parseFloat(r.mtm || 0);
                c.selected += parseFloat(r.selected || 0);
                c.delta_ytd += parseFloat(r.delta_ytd || 0);
                c.delta_mtd += parseFloat(r.delta_mtd || 0);
                c.delta_dtd += parseFloat(r.delta_dtd || 0);
                c.rka_m1 += parseFloat(r.rka_m1 || 0);
                c.rka_current += parseFloat(r.rka_current || 0);
                c.penc_m1_rp += parseFloat(r.penc_m1_rp || 0);
                c.penc_cur_rp += parseFloat(r.penc_cur_rp || 0);
            });
            // Recalculate percentages
            Object.values(categories).forEach(c => {
                c.penc_m1_pct = c.rka_m1 > 0 ? (c.selected / c.rka_m1) * 100 : 0;
                c.penc_cur_pct = c.rka_current > 0 ? (c.selected / c.rka_current) * 100 : 0;
            });
            return Object.values(categories);
        };

        const renderRow = (label, d, type, no, isBold = false, isSubRow = false) => {
            if (!d) return '';
            const labelStyle = isBold ? 'font-weight: 800; color: #0857c3;' : (isSubRow ? 'font-weight: 600; padding-left: 2rem; font-style: italic; color: #64748b; font-size: 0.75rem;' : 'font-weight: 600;');
            const rowStyle = isBold ? 'background: rgba(8, 87, 195, 0.03);' : '';
            return `<tr style="${rowStyle}">
                <td class="text-center-important">${no}</td>
                <td class="text-start-important" style="${labelStyle}">${label}</td>
                <td>${formatCurrency(d.ytd)}</td>
                <td>${formatCurrency(d.m2)}</td>
                <td>${formatCurrency(d.mtm)}</td>
                <td style="background: ${isBold ? 'rgba(224, 242, 254, 0.3)' : '#f0f7ff'}; color: #003d7c; font-weight: 800;">${formatCurrency(d.selected)}</td>
                <td class="${getConditionalClass(d.delta_ytd, type)}">${formatCurrency(d.delta_ytd)}</td>
                <td class="${getConditionalClass(d.delta_mtd, type)}">${formatCurrency(d.delta_mtd)}</td>
                <td class="${getConditionalClass(d.delta_dtd, type)}">${formatCurrency(d.delta_dtd)}</td>
                <td>${formatCurrency(d.rka_m1)}</td>
                <td>${formatCurrency(d.rka_current)}</td>
                <td class="${getConditionalClass(d.penc_m1_rp, type)}">${formatCurrency(d.penc_m1_rp)}</td>
                <td>${formatPctBadge(d.penc_m1_pct, type)}</td>
                <td class="${getConditionalClass(d.penc_cur_rp, type)}">${formatCurrency(d.penc_cur_rp)}</td>
                <td>${formatPctBadge(d.penc_cur_pct, type)}</td>
            </tr>`;
        };

        let html = `<table class="loan-summary-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 40px;">NO</th>
                    <th rowspan="2" style="width: 250px;">URAIAN KONSOLIDASI AREA 6</th>
                    <th colspan="4" class="sub-head">PERIODE</th>
                    <th colspan="3" class="accent-head">DELTA (Δ) PERIODE</th>
                    <th colspan="2" class="sub-head">RKA-KP</th>
                    <th colspan="4" class="accent-head">PENCAPAIAN RKA</th>
                </tr>
                <tr>
                    <th class="sub-head" style="width: 85px;">${dates.ytd}<br><small>(YtD)</small></th>
                    <th class="sub-head" style="width: 85px;">${dates.m2}<br><small>(M-2)</small></th>
                    <th class="sub-head" style="width: 85px;">${dates.mtm}<br><small>(MtM)</small></th>
                    <th class="sub-head" style="background: #004280; width: 90px;">${dates.selected}<br><small>(HARI INI)</small></th>
                    <th class="accent-head" style="width: 80px;">YtD</th>
                    <th class="accent-head" style="width: 80px;">MtD</th>
                    <th class="accent-head" style="width: 80px;">DtD</th>
                    <th class="sub-head" style="width: 85px;">${rkaLabels?.m1 || ''}</th>
                    <th class="sub-head" style="width: 85px;">${rkaLabels?.current || ''}</th>
                    <th class="accent-head" style="width: 90px;">${rkaLabels?.m1 || ''} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                    <th class="accent-head" style="width: 90px;">${rkaLabels?.current || ''} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                </tr>
            </thead>
            <tbody>`;

        // A. Outstanding
        html += renderRow('A. OUTSTANDING (OS)', osTotal, 'Outstanding', '1', true);
        if (segmentName === 'Mikro') {
            const osCats = getCategoryConsolidation(osData);
            osCats.forEach(c => {
                html += renderRow(c.label, c, 'Outstanding', '', false, true);
            });
        }

        // B. SML
        html += renderRow('B. SPECIAL MENTION LOAN (SML)', smlTotal, 'SML', '2', true);
        if (segmentName === 'Mikro') {
            const smlCats = getCategoryConsolidation(smlData);
            smlCats.forEach(c => {
                html += renderRow(c.label, c, 'SML', '', false, true);
            });
        }

        // C. NPL
        html += renderRow('C. NON-PERFORMING LOAN (NPL)', nplTotal, 'NPL', '3', true);
        if (segmentName === 'Mikro') {
            const nplCats = getCategoryConsolidation(nplData);
            nplCats.forEach(c => {
                html += renderRow(c.label, c, 'NPL', '', false, true);
            });
        }

        html += '</tbody></table>';
        return '<div class="loan-table-container">' + html + '</div>';
    }

    function buildTable(data, headerDates, typeLabel, segmentName, rkaLabels) {
        if (!data || data.length === 0 || (data.length === 1 && data[0].is_total && data[0].selected == 0)) {
            return '<div class="text-center py-5 text-muted">Tidak ada data untuk filter ini.</div>';
        }

        const dates = { 
            ytd: formatDate(headerDates.ytd), 
            m2: formatDate(headerDates.m2), 
            mtm: formatDate(headerDates.mtm), 
            selected: formatDate(headerDates.selected) 
        };
        const typePrefix = typeLabel.toUpperCase();
        let html = `<table class="loan-summary-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 40px;">NO</th>
                    <th rowspan="2" style="width: 120px;">KANTOR CABANG</th>
                    <th rowspan="2" style="width: 150px;">KATEGORI ${segmentName}</th>
                    <th colspan="4" class="sub-head">${typePrefix} PERIODE</th>
                    <th colspan="3" class="accent-head">DELTA (Δ) PERIODE</th>
                    <th colspan="2" class="sub-head">RKA-KP</th>
                    <th colspan="4" class="accent-head">PENCAPAIAN RKA</th>
                </tr>
                <tr>
                    <th class="sub-head" style="width: 85px;">${dates.ytd}<br><small>(YtD)</small></th>
                    <th class="sub-head" style="width: 85px;">${dates.m2}<br><small>(M-2)</small></th>
                    <th class="sub-head" style="width: 85px;">${dates.mtm}<br><small>(MtM)</small></th>
                    <th class="sub-head" style="background: #004280; width: 90px;">${dates.selected}<br><small>(HARI INI)</small></th>
                    <th class="accent-head" style="width: 80px;">YtD</th>
                    <th class="accent-head" style="width: 80px;">MtD</th>
                    <th class="accent-head" style="width: 80px;">DtD</th>
                    <th class="sub-head" style="width: 85px;">${rkaLabels?.m1 || ''}</th>
                    <th class="sub-head" style="width: 85px;">${rkaLabels?.current || ''}</th>
                    <th class="accent-head" style="width: 90px;">${rkaLabels?.m1 || ''} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                    <th class="accent-head" style="width: 90px;">${rkaLabels?.current || ''} Δ</th>
                    <th class="accent-head" style="width: 70px;">%</th>
                </tr>
            </thead>
            <tbody>`;

        const totalRow = data.find(row => row.is_total);
        const dataRows = data.filter(row => !row.is_total);
        const groups = {};
        dataRows.forEach(row => { 
            const branch = row.branch || 'Unknown'; 
            if (!groups[branch]) groups[branch] = []; 
            groups[branch].push(row); 
        });

        let rowIndex = 1;
        Object.keys(groups).forEach(branchName => {
            const groupRows = groups[branchName];
            const subtotal = { 
                ytd: 0, m2: 0, mtm: 0, selected: 0, 
                d_ytd: 0, d_mtd: 0, d_dtd: 0, 
                rka_m1: 0, rka_current: 0, 
                penc_m1_rp: 0, penc_cur_rp: 0 
            };
            groupRows.forEach(row => { 
                subtotal.ytd += parseFloat(row.ytd || 0); 
                subtotal.m2 += parseFloat(row.m2 || 0); 
                subtotal.mtm += parseFloat(row.mtm || 0); 
                subtotal.selected += parseFloat(row.selected || 0); 
                subtotal.d_ytd += parseFloat(row.delta_ytd || 0); 
                subtotal.d_mtd += parseFloat(row.delta_mtd || 0); 
                subtotal.d_dtd += parseFloat(row.delta_dtd || 0); 
                subtotal.rka_m1 += parseFloat(row.rka_m1 || 0); 
                subtotal.rka_current += parseFloat(row.rka_current || 0); 
                subtotal.penc_m1_rp += parseFloat(row.penc_m1_rp || 0); 
                subtotal.penc_cur_rp += parseFloat(row.penc_cur_rp || 0); 
            });
            
            const shortBranchName = branchName.replace(/KC Madiun/gi, 'KC MDN').replace(/KC Magetan/gi, 'KC MGT').replace(/KC Ngawi/gi, 'KC NGWI').replace(/KC Ponorogo/gi, 'KC PNRG');
            const sub_m1_pct = subtotal.rka_m1 > 0 ? (subtotal.selected / subtotal.rka_m1) * 100 : 0;
            const sub_cur_pct = subtotal.rka_current > 0 ? (subtotal.selected / subtotal.rka_current) * 100 : 0;
            
            html += `<tr class="loan-branch-subtotal">
                <td rowspan="${groupRows.length + 1}" class="text-center-v text-center-important" style="background: #f8fbff; font-weight: 700; border-bottom: 2px solid #cbd5e1; color: #1e293b !important;">${rowIndex++}</td>
                <td rowspan="${groupRows.length + 1}" class="text-center-v text-start-important merged-branch-cell" style="border-bottom: 2px solid #cbd5e1;">${branchName}</td>
                <td class="text-center-v text-center-important" style="font-size: 0.68rem; letter-spacing: 0.05em; background: rgba(255,255,255,0.05); font-weight: 900; border-right: 1px solid rgba(255,255,255,0.1);">TOTAL ${shortBranchName.toUpperCase()}</td>
                <td>${formatCurrency(subtotal.ytd)}</td>
                <td>${formatCurrency(subtotal.m2)}</td>
                <td>${formatCurrency(subtotal.mtm)}</td>
                <td style="background: rgba(224, 242, 254, 0.15); color: #7dd3fc;">${formatCurrency(subtotal.selected)}</td>
                <td class="${getConditionalClass(subtotal.d_ytd, typeLabel)}">${formatCurrency(subtotal.d_ytd)}</td>
                <td class="${getConditionalClass(subtotal.d_mtd, typeLabel)}">${formatCurrency(subtotal.d_mtd)}</td>
                <td class="${getConditionalClass(subtotal.d_dtd, typeLabel)}">${formatCurrency(subtotal.d_dtd)}</td>
                <td>${formatCurrency(subtotal.rka_m1)}</td>
                <td>${formatCurrency(subtotal.rka_current)}</td>
                <td class="${getConditionalClass(subtotal.penc_m1_rp, typeLabel)}">${formatCurrency(subtotal.penc_m1_rp)}</td>
                <td>${formatPctBadge(sub_m1_pct, typeLabel)}</td>
                <td class="${getConditionalClass(subtotal.penc_cur_rp, typeLabel)}">${formatCurrency(subtotal.penc_cur_rp)}</td>
                <td>${formatPctBadge(sub_cur_pct, typeLabel)}</td>
            </tr>`;
            
            groupRows.forEach(row => { 
                html += `<tr>
                    <td class="text-start-important text-muted" style="font-size: 0.75rem;">${row.category || ''}</td>
                    <td>${formatCurrency(row.ytd)}</td>
                    <td>${formatCurrency(row.m2)}</td>
                    <td>${formatCurrency(row.mtm)}</td>
                    <td style="background: #f0f7ff; color: #003d7c; font-weight: 800;">${formatCurrency(row.selected)}</td>
                    <td class="${getConditionalClass(row.delta_ytd, typeLabel)}">${formatCurrency(row.delta_ytd)}</td>
                    <td class="${getConditionalClass(row.delta_mtd, typeLabel)}">${formatCurrency(row.delta_mtd)}</td>
                    <td class="${getConditionalClass(row.delta_dtd, typeLabel)}">${formatCurrency(row.delta_dtd)}</td>
                    <td>${formatCurrency(row.rka_m1)}</td>
                    <td>${formatCurrency(row.rka_current)}</td>
                    <td class="${getConditionalClass(row.penc_m1_rp, typeLabel)}">${formatCurrency(row.penc_m1_rp)}</td>
                    <td>${formatPctBadge(row.penc_m1_pct, typeLabel)}</td>
                    <td class="${getConditionalClass(row.penc_cur_rp, typeLabel)}">${formatCurrency(row.penc_cur_rp)}</td>
                    <td>${formatPctBadge(row.penc_cur_pct, typeLabel)}</td>
                </tr>`; 
            });
        });

        if (totalRow) {
            html += `<tr style="background: #1e293b; color: #ffffff; font-weight: 900;">
                <td colspan="3" class="text-center-important" style="letter-spacing: 0.1em; color: #ffffff; border-right: 1px solid rgba(255,255,255,0.2);">GRAND TOTAL</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.ytd)}</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.m2)}</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.mtm)}</td>
                <td style="background: #0f172a; color: #ffffff;">${formatCurrency(totalRow.selected)}</td>
                <td class="${getConditionalClass(totalRow.delta_ytd, typeLabel)}">${formatCurrency(totalRow.delta_ytd)}</td>
                <td class="${getConditionalClass(totalRow.delta_mtd, typeLabel)}">${formatCurrency(totalRow.delta_mtd)}</td>
                <td class="${getConditionalClass(totalRow.delta_dtd, typeLabel)}">${formatCurrency(totalRow.delta_dtd)}</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.rka_m1)}</td>
                <td style="color: #ffffff;">${formatCurrency(totalRow.rka_current)}</td>
                <td class="${getConditionalClass(totalRow.penc_m1_rp, typeLabel)}">${formatCurrency(totalRow.penc_m1_rp)}</td>
                <td>${formatPctBadge(totalRow.penc_m1_pct, typeLabel)}</td>
                <td class="${getConditionalClass(totalRow.penc_cur_rp, typeLabel)}">${formatCurrency(totalRow.penc_cur_rp)}</td>
                <td>${formatPctBadge(totalRow.penc_cur_pct, typeLabel)}</td>
            </tr>`;
        }

        return '<div class="loan-table-container">' + html + '</tbody></table></div>';
    }

    function showSpinners(kategori) {
        const stub = (label) => `<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm text-primary mb-3" role="status"><span class="sr-only">Loading...</span></div><p><strong>Memproses data ${label}</strong></p><p style="font-size: 0.85rem;">Untuk Segmen ${kategori}...</p></div>`;
        osTableContainer.innerHTML = stub('Outstanding');
        consolidationTableContainer.innerHTML = stub('Konsolidasi');
        smlTableContainer.innerHTML = stub('SML');
        nplTableContainer.innerHTML = stub('NPL');
    }

    function showErrorMessage(message) {
        const errorHtml = `<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Gagal memuat data</strong><br>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
        osTableContainer.innerHTML = errorHtml; 
        smlTableContainer.innerHTML = errorHtml; 
        nplTableContainer.innerHTML = errorHtml;
    }

    // --- Data Loading Logic ---
    function loadDashboardData() {
        const periode = $periodeSel.val();
        const kategori = $kategoriSel.val();
        if (!periode) return;

        showSpinners(kategori);
        dashboardMeta.textContent = `Memuat data dashboard untuk periode ${formatDate(periode)}...`;
        btnLoadData.disabled = true;

        if (requestAbortController) requestAbortController.abort();
        requestAbortController = new AbortController();
        const controller = requestAbortController;
        const timeoutId = setTimeout(() => { if (controller === requestAbortController) controller.abort(); }, REQUEST_TIMEOUT);

        const url = '{{ route("report.dashboard-pinjaman.kredit.data") }}?' + new URLSearchParams({ periode: periode, kategori: kategori });
        
        fetch(url, { signal: controller.signal })
            .then(response => { 
                clearTimeout(timeoutId); 
                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`); 
                return response.json(); 
            })
            .then(data => {
                dashboardMeta.textContent = `Menampilkan dashboard kredit ${kategori} per ${formatDate(periode)}.`;
                
                // Consolidation Table (Shown for all, but specifically requested for Mikro)
                const osTotal = data.os.find(r => r.is_total);
                const smlTotal = data.sml.find(r => r.is_total);
                const nplTotal = data.npl.find(r => r.is_total);
                
                if (kategori === 'Mikro' && (osTotal || smlTotal || nplTotal)) {
                    consolidationSection.classList.remove('d-none');
                    consolidationTableContainer.innerHTML = buildConsolidationTable(data.os, data.sml, data.npl, data.header_dates, kategori, data.rka_labels);
                } else {
                    consolidationSection.classList.add('d-none');
                }

                document.getElementById('osTitle').innerText = `A. OUTSTANDING (OS) - ${kategori}`;
                osTableContainer.innerHTML = buildTable(data.os, data.header_dates, 'Outstanding', kategori, data.rka_labels);
                document.getElementById('smlTitle').innerText = `B. SPECIAL MENTION LOAN (SML) - ${kategori}`;
                smlTableContainer.innerHTML = buildTable(data.sml, data.header_dates, 'SML', kategori, data.rka_labels);
                document.getElementById('nplTitle').innerText = `C. NON-PERFORMING LOAN (NPL) - ${kategori}`;
                nplTableContainer.innerHTML = buildTable(data.npl, data.header_dates, 'NPL', kategori, data.rka_labels);
            })
            .catch(error => { 
                clearTimeout(timeoutId); 
                console.error('Error:', error); 
                let errorMsg = 'Periksa koneksi atau snapshot data.'; 
                if (error.name === 'AbortError') errorMsg = 'Permintaan timeout. Coba lagi.'; 
                else if (error.message.includes('HTTP')) errorMsg = error.message; 
                showErrorMessage(errorMsg); 
            })
            .finally(() => { 
                btnLoadData.disabled = false; 
            });
    }

    btnLoadData.addEventListener('click', loadDashboardData);
    
    // --- Modern Dropdown Logic ---
    function initModernSelectors() {
        document.querySelectorAll('.loan-dropdown-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const parent = btn.closest('.loan-dropdown');
                const isOpen = parent.classList.contains('is-open');
                document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                if (!isOpen) parent.classList.add('is-open');
            });
        });

        document.querySelectorAll('.loan-dropdown-option').forEach(opt => {
            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                const parent = opt.closest('.loan-dropdown');
                const select = parent.querySelector('select');
                const textSpan = parent.querySelector('.loan-dropdown-text');
                const val = opt.dataset.value;

                select.value = val;
                textSpan.textContent = opt.querySelector('span').textContent;
                
                parent.querySelectorAll('.loan-dropdown-option').forEach(o => o.classList.remove('is-active'));
                opt.classList.add('is-active');
                parent.classList.remove('is-open');
                
                $(select).trigger('change');
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
        });

        // Sync initial text
        document.querySelectorAll('.loan-dropdown').forEach(d => {
            const select = d.querySelector('select');
            const textSpan = d.querySelector('.loan-dropdown-text');
            if (select && select.selectedIndex >= 0) {
                textSpan.textContent = select.options[select.selectedIndex].text;
            }
        });
    }

    initModernSelectors();

    // Initial load
    if ($periodeSel.val()) {
        loadDashboardData();
    }
});
</script>
@endpush

@endsection
