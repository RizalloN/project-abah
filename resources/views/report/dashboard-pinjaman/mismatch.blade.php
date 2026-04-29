@extends('layouts.admin')

@section('title', 'Kolek Tidak Sesuai')

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

    .loan-shell, .loan-shell .card-body {
        overflow: visible !important;
    }

    .loan-filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        position: relative;
    }

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

    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 12px);
        left: 0;
        width: 100%;
        min-width: 320px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(25px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 1.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2);
        z-index: 3000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(15px);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        max-height: 480px;
        overflow-y: auto;
        padding: 0.75rem;
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

    .loan-dropdown-option:hover { background: #f1f5f9; color: var(--loan-blue); }
    .loan-dropdown-option.is-active { background: rgba(8, 87, 195, 0.08); color: var(--loan-blue); }

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

    .btn-loan-modern-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(8, 87, 195, 0.4); }

    .select2-container--bootstrap4, .loan-filter-control { display: none !important; }
</style>

<div class="loan-dashboard pt-4">
    <div class="loan-title-hero d-flex flex-wrap justify-content-center align-items-center">
        <div class="loan-title-hero__wrap">
            <div class="loan-title-hero__badge">
                <i class="fas fa-university"></i>
                <span>BRI Loan Audit</span>
            </div>
            <h1 class="loan-title-hero__title">KOLEK TIDAK SESUAI</h1>
            <p class="loan-title-hero__desc">Verifikasi konsistensi bucket kualitas pinjaman berdasarkan rule audit agar anomali kolektibilitas lebih cepat terbaca.</p>
        </div>
    </div>

    <div id="loanMismatchPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <form id="loanMismatchForm" method="GET" action="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                        <div>
                            <h5 class="mb-1 font-weight-bold text-dark">Filter Audit</h5>
                        </div>
                    </div>

                    <div class="loan-filter-modern animate-reveal">
                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Periode Audit</label>
                            <div class="loan-dropdown" data-loan-dropdown="periode">
                                <i class="fas fa-calendar-check loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" onclick="document.getElementById('loanMismatchPeriodeInput').showPicker()">
                                    <span class="loan-dropdown-text" id="loanMismatchPeriodeDisplay">{{ $mismatchRequestedPeriod ?: $mismatchSelectedPeriod }}</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <input id="loanMismatchPeriodeInput" type="date" name="mismatch_periode" 
                                    style="opacity: 0; position: absolute; width: 100%; height: 100%; top: 0; left: 0; pointer-events: none;" 
                                    value="{{ $mismatchRequestedPeriod ?: $mismatchSelectedPeriod }}" max="{{ $periods->first() }}">
                            </div>
                        </div>

                        <div class="loan-filter-item">
                            <label class="loan-filter-label">Kantor Cabang</label>
                            <div class="loan-dropdown" data-loan-dropdown="cabang">
                                <i class="fas fa-university loan-dropdown-icon"></i>
                                <button type="button" class="loan-dropdown-toggle" data-loan-dropdown-toggle="cabang">
                                    <span class="loan-dropdown-text">Pilih Kantor Cabang</span>
                                    <i class="fas fa-chevron-down small opacity-50"></i>
                                </button>
                                <div class="loan-dropdown-menu" data-loan-dropdown-menu="cabang">
                                    <div class="px-3 py-3 text-center text-muted small">Pilih periode dulu</div>
                                </div>
                                <select id="loanMismatchCabangSelect" name="mismatch_cabang1" class="d-none" data-selected="{{ $mismatchSelectedBranch }}"></select>
                            </div>
                        </div>

                        <div>
                            <button id="loanMismatchSubmitButton" type="submit" class="btn-loan-modern-submit w-100">
                                <i class="fas fa-search"></i> PROSES AUDIT
                            </button>
                            <div id="loanMismatchLoadingChip" class="loan-loading-chip d-none mt-2 justify-content-center">
                                <span class="loan-loading-dot"></span> AUDIT BERJALAN
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell loan-mismatch-table-shell animate-reveal">
            <div class="card-body p-4">
                <div class="loan-table-heading">
                    <div><h5>Hasil Audit Mismatch</h5></div>
                    <div class="loan-table-badge">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span id="loanMismatchPeriodBadge">
                            {{ $mismatchSelectedPeriod ? \Carbon\Carbon::parse($mismatchSelectedPeriod)->format('d/m/Y') : '-' }} | {{ $mismatchSelectedBranch ?: 'Belum pilih cabang' }}
                        </span>
                    </div>
                </div>

                <div class="loan-mismatch-summary">
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Baris Discanning</div>
                        <div id="loanMismatchScanned" class="loan-audit-value">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Mismatch</div>
                        <div id="loanMismatchTotal" class="loan-audit-value text-danger">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Sesuai</div>
                        <div id="loanMismatchMatched" class="loan-audit-value text-success">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Unit Bermasalah</div>
                        <div id="loanMismatchUnits" class="loan-audit-value">0</div>
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <div class="loan-mismatch-table-wrap">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 72px; text-align: center;">No</th>
                                    <th style="text-align: left;">Unit Kerja</th>
                                    <th style="width: 220px; text-align: center;">Jumlah Kolek Tidak Sesuai</th>
                                    <th style="width: 180px; text-align: center;">Export Detail</th>
                                </tr>
                            </thead>
                            <tbody id="loanMismatchBody">
                                <tr>
                                    <td colspan="4" class="loan-empty-state">
                                        <strong>Audit belum dijalankan</strong>
                                        Pilih periode dan cabang lalu klik <strong>Proses</strong>.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanMismatchForm');
        const periodInput = document.getElementById('loanMismatchPeriodeInput');
        const branchSelect = document.getElementById('loanMismatchCabangSelect');
        const body = document.getElementById('loanMismatchBody');
        const chip = document.getElementById('loanMismatchLoadingChip');
        const submitButton = document.getElementById('loanMismatchSubmitButton');
        const badge = document.getElementById('loanMismatchPeriodBadge');
        
        const filterUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.data'));
        const exportUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.export'));

        initMultiSelect(branchSelect, 'Pilih Kantor Cabang');

        async function loadBranches() {
            if (!periodInput.value) return;
            const res = await fetch(`${filterUrl}?periode=${periodInput.value}`);
            const payload = await res.json();
            branchSelect.innerHTML = '<option value="">Pilih kantor cabang</option>';
            payload.branches.forEach(b => {
                branchSelect.add(new Option(b, b, false, b === branchSelect.dataset.selected));
            });
            branchSelect.disabled = false;
            window.jQuery(branchSelect).trigger('change');
        }

        async function loadData() {
            if (!periodInput.value || !branchSelect.value) return;
            chip.classList.remove('d-none');
            submitButton.disabled = true;
            
            try {
                const res = await fetch(`${dataUrl}?periode=${periodInput.value}&cabang1=${branchSelect.value}`);
                const payload = await res.json();
                renderTable(payload.summary_rows, payload.selected_period, payload.selected_branch);
                document.getElementById('loanMismatchScanned').textContent = formatNumber(payload.audit.scanned_rows);
                document.getElementById('loanMismatchTotal').textContent = formatNumber(payload.audit.mismatch_rows);
                document.getElementById('loanMismatchMatched').textContent = formatNumber(payload.audit.matched_rows);
                document.getElementById('loanMismatchUnits').textContent = formatNumber(payload.audit.units_with_mismatch);
            } finally {
                chip.classList.add('d-none');
                submitButton.disabled = false;
            }
        }

        function renderTable(rows, period, branch) {
            body.innerHTML = rows.map((row, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td>${row.unit}</td>
                    <td class="text-danger font-weight-bold">${formatNumber(row.mismatch_count)}</td>
                    <td><a href="${exportUrl}?periode=${period}&cabang1=${branch}&unit1=${row.unit}" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel mr-1"></i> Excel</a></td>
                </tr>
            `).join('');
            badge.textContent = `${formatDate(period)} | ${branch}`;
        }

        form.addEventListener('submit', e => { e.preventDefault(); loadData(); });
        // --- Modern Selector Sync ---
        function initModernSelectors() {
            const cabangMenu = document.querySelector('[data-loan-dropdown-menu="cabang"]');
            const cabangToggle = document.querySelector('[data-loan-dropdown-toggle="cabang"]');
            const cabangText = cabangToggle.querySelector('.loan-dropdown-text');
            const periodeDisplay = document.getElementById('loanMismatchPeriodeDisplay');

            // Periode Sync
            periodInput.addEventListener('change', () => {
                periodeDisplay.textContent = periodInput.value;
            });

            // Dropdown Toggle
            cabangToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                cabangToggle.closest('.loan-dropdown').classList.toggle('is-open');
            });

            document.addEventListener('click', () => {
                document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
            });

            // Mutation Observer to watch branch select options
            const observer = new MutationObserver(() => {
                const options = Array.from(branchSelect.options);
                cabangMenu.innerHTML = '';
                
                if (options.length <= 1) {
                    cabangMenu.innerHTML = '<div class="px-3 py-3 text-center text-muted small">Pilih periode dulu</div>';
                    return;
                }

                options.forEach(opt => {
                    if (!opt.value) return;
                    const item = document.createElement('div');
                    item.className = `loan-dropdown-option ${opt.selected ? 'is-active' : ''}`;
                    item.innerHTML = `<div class="loan-dropdown-check"><i class="fas fa-check"></i></div><span>${opt.text}</span>`;
                    item.addEventListener('click', (e) => {
                        e.stopPropagation();
                        branchSelect.value = opt.value;
                        cabangText.textContent = opt.text;
                        window.jQuery(branchSelect).trigger('change');
                        document.querySelectorAll('.loan-dropdown').forEach(d => d.classList.remove('is-open'));
                    });
                    cabangMenu.appendChild(item);
                });

                if (branchSelect.selectedIndex >= 0 && branchSelect.value) {
                    cabangText.textContent = branchSelect.options[branchSelect.selectedIndex].text;
                } else {
                    cabangText.textContent = 'Pilih Kantor Cabang';
                }
            });

            observer.observe(branchSelect, { childList: true });
        }

        initModernSelectors();

        if (periodInput.value) loadBranches();
    });
</script>
@endpush

@endsection
