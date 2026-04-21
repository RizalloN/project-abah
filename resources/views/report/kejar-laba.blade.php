@extends('layouts.admin')

@section('title', 'Report Recovery')

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
        --loan-blue-soft: #eaf2ff;
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
        overflow: visible; /* Changed from hidden to allow dropdowns to pop out */
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
        position: relative;
        z-index: 50;
    }

    .filter-container {
        display: flex;
        align-items: flex-end;
        gap: 1.5rem;
        flex-wrap: wrap;
        width: 100%;
        justify-content: center;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        min-width: 200px;
        position: relative;
    }

    .filter-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .select-custom {
        border-radius: 10px;
        border: 1px solid var(--border-color);
        padding: 0.6rem 1rem;
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--text-main);
        background: #f9fafb;
        cursor: pointer;
        transition: all 0.2s ease;
        appearance: none;
        width: 100%;
    }

    .select-custom:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        outline: none;
    }

    /* Multi-select Dropdown Style (from Dashboard Harian) */
    .daily-dropdown {
        position: relative;
        width: 100%;
    }

    .daily-dropdown-toggle {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: #f9fafb;
        color: var(--text-main);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.6rem 1rem;
        font-weight: 700;
        font-size: 0.88rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .daily-dropdown-toggle:hover {
        border-color: var(--primary-blue-light);
        background: #ffffff;
    }

    .daily-dropdown-toggle-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 1.5rem;
    }

    .daily-dropdown-menu {
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 15px 30px -10px rgba(0,0,0,0.1);
        z-index: 100;
        padding: 0.5rem;
        display: none;
        max-height: 300px;
        overflow-y: auto;
    }

    .daily-dropdown.is-open .daily-dropdown-menu {
        display: block;
    }

    .daily-dropdown-option {
        display: flex;
        align-items: center;
        padding: 0.6rem 0.75rem;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s;
        gap: 0.75rem;
    }

    .daily-dropdown-option:hover {
        background: #f1f5f9;
    }

    .daily-dropdown-option.is-active {
        background: #eff6ff;
        color: var(--primary-blue);
    }

    .daily-dropdown-check {
        width: 18px;
        height: 18px;
        border: 2px solid #cbd5e1;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .is-active .daily-dropdown-check {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .daily-dropdown-check i {
        color: white;
        font-size: 10px;
        display: none;
    }

    .is-active .daily-dropdown-check i {
        display: block;
    }

    .btn-apply {
        background: var(--primary-blue);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.78rem 1.75rem;
        font-weight: 700;
        font-size: 0.95rem;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.2);
        cursor: pointer;
    }

    .btn-apply:hover {
        background: var(--primary-blue-dark);
        transform: translateY(-1px);
    }

    /* Searchable Dropdown Extensions */
    .daily-search-shell {
        padding: 0.5rem 0.75rem 0.45rem;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        position: sticky;
        top: 0;
        z-index: 10;
        backdrop-filter: blur(8px);
    }

    .daily-search-inner {
        position: relative;
    }

    .daily-search-inner i {
        position: absolute;
        left: 0.65rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    .daily-search-input {
        width: 100%;
        padding: 0.45rem 0.65rem 0.45rem 1.85rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 500;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .daily-search-input:focus {
        background: #ffffff;
        border-color: var(--primary-blue-light);
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .daily-dropdown-options-list {
        max-height: 240px;
        overflow-y: auto;
        padding: 0.25rem 0;
    }

    .daily-dropdown-options-list::-webkit-scrollbar {
        width: 5px;
    }

    .daily-dropdown-options-list::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }

    /* Table Wrapper with Sticky Viewport */
    .kejar-laba-table-shell {
        position: relative;
        width: 100%;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        z-index: 10;
    }

    /* Integration with report.partials.sticky-table-viewport-style */
    @include('report.partials.sticky-table-viewport-style', [
        'wrapperSelector' => '.kejar-laba-table-shell',
        'tableSelector' => '.kejar-laba-table'
    ])

    .kejar-laba-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: auto;
        background: #ffffff;
    }

    .kejar-laba-table thead th {
        background-color: var(--table-header-bg) !important;
        color: var(--table-header-text);
        text-transform: uppercase;
        font-size: 0.72rem;
        padding: 0.85rem 1.1rem;
        font-weight: 800;
        z-index: 30;
        border-right: 1px solid rgba(255,255,255,0.08);
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        white-space: nowrap;
    }

    .kejar-laba-table thead tr:nth-child(2) th {
        background: #274bba !important;
        padding: 0.55rem 0.75rem;
    }

    .kejar-laba-table tbody td {
        font-size: 0.82rem;
        background: #ffffff;
        font-variant-numeric: tabular-nums;
        padding: 0.85rem 1.1rem;
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
    }

    .kejar-laba-table tbody tr:nth-child(even) td {
        background: #fafbfd;
    }

    .kejar-laba-table tbody tr:hover td {
        background: #f1f5f9;
    }

    /* Fixed Headers and Columns Color Fix */
    .kejar-laba-table th.sticky-col {
        background-color: var(--table-header-bg) !important;
        z-index: 40 !important; /* Above regular sticky headers */
    }
    
    .kejar-laba-table td.sticky-col {
        background-color: #ffffff !important;
        z-index: 20; /* Above regular cells, below headers */
    }
    
    .kejar-laba-table tr:nth-child(even) td.sticky-col {
        background: #fafbfd !important;
    }

    .sticky-col {
        position: sticky;
        left: 0;
        box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.1);
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
            <div>
                <h1 class="kejar-laba-title">Report Recovery</h1>
                <div class="kejar-laba-subtitle">Monitoring pencapaian Recovery berdasarkan data Cognos.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-info px-3 py-2" style="border-radius: 20px; font-weight: 800; background: #eff6ff; color: var(--primary-blue); border: 1px solid #dbeafe;">
                    <i class="fas fa-calendar-check mr-1"></i> Data per: {{ $selectedPeriodLabel }}
                </span>
            </div>
        </div>

        <div class="filter-section">
            <form action="{{ route('report.dashboard-pinjaman.kejar-laba') }}" method="GET" class="filter-container" id="filterForm">
                {{-- Periode --}}
                <div class="filter-item">
                    <label class="filter-label">Periode</label>
                    <select name="periode" class="select-custom">
                        @foreach($availablePeriods as $period)
                            <option value="{{ $period }}" {{ $selectedPeriod === $period ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($period)->translatedFormat('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kantor Cabang (Multi Select) --}}
                <div class="filter-item" style="min-width: 250px;">
                    <label class="filter-label">Kantor Cabang</label>
                    <div class="daily-dropdown" id="kancaDropdown">
                        <input type="hidden" name="kanca" id="kancaInput" value="{{ is_array($selected['kanca']) ? implode(',', $selected['kanca']) : $selected['kanca'] }}">
                        <div class="daily-dropdown-toggle">
                            <span class="daily-dropdown-toggle-text" id="kancaLabel">Pilih Kantor Cabang...</span>
                            <i class="fas fa-chevron-down daily-dropdown-toggle-icon"></i>
                        </div>
                        <div class="daily-dropdown-menu">
                            <div class="daily-dropdown-option {{ empty($selected['kanca']) ? 'is-active' : '' }}" data-value="all">
                                <div class="daily-dropdown-check"><i class="fas fa-check"></i></div>
                                <span class="daily-dropdown-label">Semua Kantor Cabang</span>
                            </div>
                            @foreach($filters['kanca'] as $kc)
                                @if($kc['value'] !== 'all')
                                    @php $active = is_array($selected['kanca']) && in_array($kc['value'], $selected['kanca']); @endphp
                                    <div class="daily-dropdown-option {{ $active ? 'is-active' : '' }}" data-value="{{ $kc['value'] }}">
                                        <div class="daily-dropdown-check"><i class="fas fa-check"></i></div>
                                        <span class="daily-dropdown-label">{{ $kc['label'] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Unit Kerja (Searchable Dropdown) --}}
                <div class="filter-item" style="min-width: 300px;">
                    <label class="filter-label">Unit Kerja</label>
                    <div class="daily-dropdown" id="unitDropdown">
                        <input type="hidden" name="unit_kerja" id="unitInput" value="{{ $selected['unit_kerja'] }}">
                        <div class="daily-dropdown-toggle">
                            <span class="daily-dropdown-toggle-text" id="unitLabel">
                                {{ $selected['unit_kerja'] === 'all' ? 'Semua Unit Kerja' : $selected['unit_kerja'] }}
                            </span>
                            <i class="fas fa-chevron-down daily-dropdown-toggle-icon"></i>
                        </div>
                        <div class="daily-dropdown-menu" style="padding: 0;">
                            <div class="daily-search-shell">
                                <div class="daily-search-inner">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="daily-search-input" placeholder="Cari unit kerja..." id="unitSearch">
                                </div>
                            </div>
                            <div class="daily-dropdown-options-list" id="unitOptionsContainer">
                                <div class="daily-dropdown-option {{ $selected['unit_kerja'] === 'all' ? 'is-active' : '' }}" data-value="all">
                                    <div class="daily-dropdown-check"><i class="fas fa-check"></i></div>
                                    <span class="daily-dropdown-label">Semua Unit Kerja</span>
                                </div>
                                {{-- Options will be populated by JS --}}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RKA Month --}}
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

                <div class="filter-item" style="min-width: auto;">
                    <button type="submit" class="btn-apply">
                        <i class="fas fa-search mr-1"></i> Telusuri
                    </button>
                </div>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Constants from Backend
                const allUnitsData = @json($filters['unit_kerja']);
                
                // Elements - Kanca
                const kancaDropdown = document.getElementById('kancaDropdown');
                const kancaToggle = kancaDropdown.querySelector('.daily-dropdown-toggle');
                const kancaMenu = kancaDropdown.querySelector('.daily-dropdown-menu');
                const kancaOptions = kancaDropdown.querySelectorAll('.daily-dropdown-option');
                const kancaInput = document.getElementById('kancaInput');
                const kancaLabel = document.getElementById('kancaLabel');
                
                // Elements - Unit
                const unitDropdown = document.getElementById('unitDropdown');
                const unitToggle = unitDropdown.querySelector('.daily-dropdown-toggle');
                const unitMenu = unitDropdown.querySelector('.daily-dropdown-menu');
                const unitInput = document.getElementById('unitInput');
                const unitLabel = document.getElementById('unitLabel');
                const unitOptionsContainer = document.getElementById('unitOptionsContainer');
                const unitSearchInput = document.getElementById('unitSearch');

                // Toggle logic
                [kancaToggle, unitToggle].forEach(toggle => {
                    toggle.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const target = toggle.parentElement;
                        const wasOpen = target.classList.contains('is-open');
                        
                        // Close all
                        document.querySelectorAll('.daily-dropdown').forEach(d => d.classList.remove('is-open'));
                        
                        if (!wasOpen) target.classList.add('is-open');
                        
                        // Focus search if it exists
                        if (!wasOpen && target.id === 'unitDropdown') {
                            setTimeout(() => unitSearchInput.focus(), 50);
                        }
                    });
                });

                document.addEventListener('click', () => {
                    document.querySelectorAll('.daily-dropdown').forEach(d => d.classList.remove('is-open'));
                });

                [kancaMenu, unitMenu].forEach(menu => {
                    menu.addEventListener('click', (e) => e.stopPropagation());
                });

                // Unit Search Logic
                unitSearchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    const options = unitOptionsContainer.querySelectorAll('.daily-dropdown-option');
                    options.forEach(opt => {
                        if (opt.dataset.value === 'all') return;
                        const text = opt.querySelector('.daily-dropdown-label').textContent.toLowerCase();
                        opt.style.display = text.includes(term) ? 'flex' : 'none';
                    });
                });

                function rebuildUnitOptions() {
                    const selectedKancas = kancaInput.value ? kancaInput.value.split(',') : [];
                    const currentUnit = unitInput.value;
                    let foundCurrentUnit = currentUnit === 'all';

                    // Clear and add "All" option
                    unitOptionsContainer.innerHTML = `
                        <div class="daily-dropdown-option ${currentUnit === 'all' ? 'is-active' : ''}" data-value="all">
                            <div class="daily-dropdown-check"><i class="fas fa-check"></i></div>
                            <span class="daily-dropdown-label">Semua Unit Kerja</span>
                        </div>
                    `;

                    allUnitsData.forEach(unit => {
                        if (unit.value === 'all') return;

                        // Filter by Branch
                        if (selectedKancas.length === 0 || selectedKancas.includes(unit.kanca_value)) {
                            const opt = document.createElement('div');
                            opt.className = `daily-dropdown-option ${unit.value === currentUnit ? 'is-active' : ''}`;
                            opt.dataset.value = unit.value;
                            opt.innerHTML = `
                                <div class="daily-dropdown-check"><i class="fas fa-check"></i></div>
                                <span class="daily-dropdown-label">${unit.label}</span>
                            `;
                            
                            if (unit.value === currentUnit) foundCurrentUnit = true;

                            opt.addEventListener('click', function() {
                                selectUnit(unit.value, unit.label);
                            });
                            
                            unitOptionsContainer.appendChild(opt);
                        }
                    });

                    // Add "All" click listener
                    unitOptionsContainer.querySelector('[data-value="all"]').addEventListener('click', () => selectUnit('all', 'Semua Unit Kerja'));

                    // If previously selected unit is no longer in the list, reset to "All"
                    if (!foundCurrentUnit) {
                        selectUnit('all', 'Semua Unit Kerja');
                    }
                }

                function selectUnit(value, label) {
                    unitInput.value = value;
                    unitLabel.textContent = label;
                    unitDropdown.classList.remove('is-open');
                    
                    // Update active class
                    unitOptionsContainer.querySelectorAll('.daily-dropdown-option').forEach(o => {
                        o.classList.toggle('is-active', o.dataset.value === value);
                    });
                }

                function updateKancaUI() {
                    const selected = kancaInput.value ? kancaInput.value.split(',') : [];
                    
                    // Update UI labels
                    if (selected.length === 0) {
                        kancaLabel.textContent = 'Semua Kantor Cabang';
                        kancaOptions[0].classList.add('is-active');
                        kancaOptions.forEach((o, i) => i > 0 && o.classList.remove('is-active'));
                    } else if (selected.length === 1) {
                        kancaLabel.textContent = selected[0];
                        kancaOptions[0].classList.remove('is-active');
                    } else {
                        kancaLabel.textContent = `${selected.length} Cabang Dipilih`;
                        kancaOptions[0].classList.remove('is-active');
                    }

                    // Dynamic rebuild of unit options
                    rebuildUnitOptions();
                }

                // Initial UI state
                updateKancaUI();

                // Kanca option events
                kancaOptions.forEach(opt => {
                    opt.addEventListener('click', function() {
                        const val = this.dataset.value;
                        let selected = kancaInput.value ? kancaInput.value.split(',') : [];

                        if (val === 'all') {
                            selected = [];
                            kancaOptions.forEach(o => o.classList.toggle('is-active', o.dataset.value === 'all'));
                        } else {
                            if (selected.includes(val)) {
                                selected = selected.filter(v => v !== val);
                                this.classList.remove('is-active');
                            } else {
                                selected.push(val);
                                this.classList.add('is-active');
                            }
                            
                            if (selected.length === 0) {
                                kancaOptions[0].classList.add('is-active');
                            } else {
                                kancaOptions[0].classList.remove('is-active');
                            }
                        }

                        kancaInput.value = selected.join(',');
                        updateKancaUI();
                    });
                });
            });
        </script>

        <div class="kejar-laba-table-shell">
            <table class="kejar-laba-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky-col">No</th>
                        <th rowspan="2" class="sticky-col" style="left: 64px;">Kanca</th>
                        <th rowspan="2">BUC</th>
                        <th rowspan="2">Unit</th>
                        <th colspan="4" class="text-center">Recovery (M-1)</th>
                        <th colspan="4" class="text-center">Recovery ({{ \Carbon\Carbon::parse($selectedPeriod)->translatedFormat('d M Y') }})</th>
                        <th colspan="4" class="text-center">RKA (Target)</th>
                        <th colspan="4" class="text-center">Delta (MtD vs RKA)</th>
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
                            <td style="min-width: 250px; font-weight: 600;">{{ $row['unit'] }}</td>
                            
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

        @include('report.partials.sticky-table-viewport-script', [
            'wrapperSelector' => '.kejar-laba-table-shell',
            'tableSelector' => '.kejar-laba-table',
            'visibleRowLimit' => 30
        ])
    </div>
</div>
@endsection
