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

    /* Integrated Header Styling */
    .loan-header-banner {
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 1rem;
    }
    
    .loan-header-icon-wrap {
        background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(15, 118, 110, 0.15);
    }
    
    .loan-header-badge {
        font-size: 0.65rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        line-height: 1.2;
    }
    
    .loan-header-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .loan-filter-modern {
        grid-template-columns: minmax(180px, 240px) minmax(240px, 1fr) auto;
        gap: 1rem;
        background: #ffffff;
        backdrop-filter: none;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        box-shadow: none;
        margin-bottom: 0;
    }

    .loan-dropdown-toggle,
    .btn-loan-modern-submit {
        height: 46px;
        border-radius: 8px;
        transform: none !important;
    }

    .btn-loan-modern-submit {
        min-width: 150px;
        background: #0f766e;
        box-shadow: none;
        letter-spacing: 0;
        text-transform: none;
        font-weight: 700;
    }

    .btn-loan-modern-submit:hover {
        background: #115e59;
        box-shadow: none;
    }

    .loan-mismatch-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin: 1rem 0;
    }

    .loan-mismatch-card {
        border-radius: 8px;
        padding: 1rem;
        background: #ffffff;
        box-shadow: none;
        transition: none;
        border: 1px solid #cbd5e1;
    }

    .loan-mismatch-card:hover {
        transform: none;
        box-shadow: none;
    }

    .loan-mismatch-card::before {
        display: none;
    }

    .loan-audit-label {
        letter-spacing: 0;
        text-transform: none;
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.25rem;
    }

    .loan-audit-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
    }

    /* Crisp spreadsheet Excel-style container & inner tables */
    .loan-mismatch-table-wrap {
        border-radius: 0 !important;
        overflow: auto;
        border: 1px solid #94a3b8 !important;
        background: #ffffff;
        box-shadow: none !important;
    }

    .loan-mismatch-table {
        border-collapse: collapse !important;
        border: 1px solid #94a3b8 !important;
        table-layout: fixed;
        min-width: 1280px;
        font-size: 0.78rem;
    }

    .loan-mismatch-table th,
    .loan-mismatch-table td {
        border: 1px solid #cbd5e1 !important; /* Muted crisp excel inner gridlines */
        line-height: 1.25;
        padding: 6px 8px !important;
        vertical-align: middle !important;
        white-space: normal;
        border-radius: 0 !important;
    }

    /* Excel Corporate Header Styling */
    .loan-mismatch-table thead th {
        background-color: #0f5eb8 !important;
        color: #ffffff !important;
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif !important;
        font-weight: 700 !important;
        font-size: 0.74rem !important;
        letter-spacing: 0.02em !important;
        padding: 8px !important;
        border: 1px solid #0a4b94 !important;
        text-align: center !important;
        vertical-align: middle !important;
        white-space: nowrap;
        border-radius: 0 !important;
    }

    .loan-mismatch-table thead tr:first-child th {
        background-color: #053b82 !important;
        border: 1px solid #042a5f !important;
    }

    .loan-mismatch-table thead tr:nth-child(2) th {
        background-color: #0857c3 !important;
        border: 1px solid #053b82 !important;
    }

    /* Grid Cells with Excel Grid Lines */
    .loan-mismatch-table tbody td {
        background: #ffffff !important;
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif !important;
        font-size: 0.82rem !important;
        font-weight: 600 !important;
        color: #1e293b !important;
        height: 34px;
        border-radius: 0 !important;
    }

    /* Subtle Zebra Striping for detail data rows only */
    .loan-mismatch-table tbody tr:nth-child(even) td {
        background: #f8fafc !important;
    }

    .loan-mismatch-table tbody tr:hover td {
        background: #f1f5f9 !important;
    }

    .loan-mismatch-table tbody td:first-child {
        color: #64748b !important;
        text-align: center !important;
        font-weight: 700 !important;
    }

    .loan-mismatch-table tbody td:nth-child(2) {
        color: #0f172a !important;
        font-weight: 800 !important;
        word-break: break-word;
        text-align: left !important;
    }

    .loan-mismatch-table tbody td:nth-child(3),
    .loan-mismatch-table tbody td:nth-child(5),
    .loan-mismatch-table tbody td:nth-child(7),
    .loan-mismatch-table tbody td:nth-child(9) {
        text-align: center !important;
        font-variant-numeric: tabular-nums !important;
    }

    .loan-mismatch-table tbody td:nth-child(4),
    .loan-mismatch-table tbody td:nth-child(6),
    .loan-mismatch-table tbody td:nth-child(8),
    .loan-mismatch-table tbody td:nth-child(10) {
        text-align: right !important;
        font-variant-numeric: tabular-nums !important;
        white-space: nowrap !important;
    }

    .loan-mismatch-table tbody td:nth-child(11) {
        text-align: center !important;
    }

    /* Accounting Double-Underline Excel Grand Total Styling */
    .loan-mismatch-table tfoot th,
    .loan-mismatch-table tfoot td {
        background-color: #cbd5e1 !important; /* Excel total row solid steel color */
        color: #0f172a !important;
        font-weight: 800 !important;
        font-size: 0.85rem !important;
        border: 1px solid #94a3b8 !important;
        border-top: 2px solid #334155 !important;
        border-bottom: 4px double #000000 !important; /* Classic Excel double underline */
        border-radius: 0 !important;
    }

    .loan-mismatch-table tfoot td {
        text-align: center !important;
        font-variant-numeric: tabular-nums !important;
        white-space: nowrap !important;
    }

    .loan-mismatch-table tfoot td.text-right {
        text-align: right !important;
    }

    .loan-mismatch-table .btn {
        min-height: 28px;
        padding: 0.22rem 0.5rem;
        border-radius: 4px;
        font-size: 0.72rem;
        line-height: 1.2;
        font-weight: 700;
    }

    .loan-mismatch-trends { margin: 1.5rem 0 0; }
    .loan-mismatch-trends__heading { display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:.5rem 1rem; margin-bottom:.75rem; }
    .loan-mismatch-trends__heading h6 { margin:0; color:#0f172a; font-weight:750; font-size:1rem; }
    .loan-mismatch-trends__heading p { margin:0; color:#475569; font-size:.8rem; }
    .loan-mismatch-trends__grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    .loan-mismatch-trend { min-width:0; padding:1rem; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
    .loan-mismatch-trend h6 { color:#334155; font-size:.85rem; font-weight:700; margin:0 0 .4rem; }
    .loan-mismatch-trend__value { color:#0f172a; font-size:1.35rem; font-weight:750; font-variant-numeric:tabular-nums; margin-bottom:1rem; }
    .loan-mismatch-trend__plot { height:280px; position:relative; }
    .loan-mismatch-trend__progress { color:#475569; font-size:.8rem; min-height:1.2rem; margin-bottom:.5rem; }
    .loan-mismatch-trends__status { color:#475569; font-size:.8rem; margin:.75rem 0 0; }
    .loan-mismatch-trends details { margin-top:.5rem; color:#475569; font-size:.8rem; }
    .loan-mismatch-trends summary { cursor:pointer; width:fit-content; padding:.5rem 0; }
    .loan-mismatch-trends summary:focus-visible, #loanMismatchTrendRetry:focus-visible { outline:3px solid #0857c3; outline-offset:3px; }
    .loan-mismatch-trends [hidden] { display:none !important; }
    .loan-mismatch-trends__data { max-height:240px; overflow:auto; }
    .loan-mismatch-trends__data table { width:100%; font-variant-numeric:tabular-nums; }
    .loan-mismatch-trends__data th, .loan-mismatch-trends__data td { padding:.45rem; border-bottom:1px solid #e2e8f0; }
    .loan-mismatch-trends__data th:not(:first-child), .loan-mismatch-trends__data td:not(:first-child) { text-align:right; }
    @media (max-width: 767.98px) {
        .loan-mismatch-trends__grid { grid-template-columns:minmax(0,1fr); }
        .loan-mismatch-trend__plot { height:230px; }
    }

    @media (max-width: 991.98px) {
        .loan-filter-modern,
        .loan-mismatch-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="loan-dashboard pt-4">
    <div id="loanMismatchPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <!-- Integrated Page Header inside Card -->
                <div class="d-flex align-items-center justify-content-between mb-4 pb-3 loan-header-banner">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 text-white d-flex align-items-center justify-content-center loan-header-icon-wrap">
                            <i class="fas fa-clipboard-check fa-lg"></i>
                        </div>
                        <div>
                            <span class="loan-header-badge">Dashboard Pinjaman</span>
                            <h3 class="loan-header-title">Kolek Tidak Sesuai</h3>
                        </div>
                    </div>
                </div>

                <form id="loanMismatchForm" method="GET" action="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                        <div>
                            <h6 class="mb-0 text-uppercase text-muted font-weight-bold" style="font-size: 0.72rem; letter-spacing: 0.08em;"><i class="fas fa-filter mr-1 text-teal"></i> Parameter Pencarian</h6>
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
                                <select id="loanMismatchCabangSelect" name="mismatch_cabang1" class="d-none" data-selected="{{ ($mismatchSelectedBranches ?? ['AREA_6_ALL'])[0] ?? 'AREA_6_ALL' }}"></select>
                            </div>
                        </div>

                        <div>
                            <button id="loanMismatchSubmitButton" type="submit" class="btn-loan-modern-submit w-100">
                                <i class="fas fa-search mr-1"></i> Proses
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell loan-mismatch-table-shell animate-reveal">
            <div class="card-body p-4">
                <div class="loan-table-heading">
                    <div><h5>Kolek Tidak Sesuai</h5></div>
                    <div class="loan-table-badge">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span id="loanMismatchPeriodBadge">
                            {{ $mismatchSelectedPeriod ? \Carbon\Carbon::parse($mismatchSelectedPeriod)->format('d/m/Y') : '-' }} | Area 6 - All
                        </span>
                    </div>
                </div>

                <div class="loan-mismatch-summary">
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Total Rekening KTS</div>
                        <div id="loanMismatchTotal" class="loan-audit-value text-danger">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Unit Terdampak</div>
                        <div id="loanMismatchUnits" class="loan-audit-value">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Total OS</div>
                        <div id="loanMismatchOutstanding" class="loan-audit-value">0</div>
                    </div>
                </div>

                <section class="loan-mismatch-trends" id="loanMismatchTrends" aria-labelledby="loanMismatchTrendsTitle" aria-busy="false">
                    <div class="loan-mismatch-trends__heading">
                        <h6 id="loanMismatchTrendsTitle">Tren bulanan kolek Memburuk</h6>
                        <p id="loanMismatchTrendContext">Januari sampai bulan periode audit yang diproses.</p>
                    </div>
                    <div class="loan-mismatch-trends__grid">
                        <div class="loan-mismatch-trend">
                            <h6 id="loanMismatchCountTitle">Jumlah rekening Memburuk</h6>
                            <div class="loan-mismatch-trend__value" id="loanMismatchCountValue">—</div>
                            <div class="loan-mismatch-trend__progress" data-trend-progress aria-hidden="true"></div>
                            <div class="loan-mismatch-trend__plot"><canvas id="loanMismatchCountChart" role="img" aria-labelledby="loanMismatchCountTitle" aria-describedby="loanMismatchTrendStatus">Data bulanan tersedia melalui Buka data grafik.</canvas></div>
                        </div>
                        <div class="loan-mismatch-trend">
                            <h6 id="loanMismatchBalanceTitle">Nominal OS Memburuk</h6>
                            <div class="loan-mismatch-trend__value" id="loanMismatchBalanceValue">—</div>
                            <div class="loan-mismatch-trend__progress" data-trend-progress aria-hidden="true"></div>
                            <div class="loan-mismatch-trend__plot"><canvas id="loanMismatchBalanceChart" role="img" aria-labelledby="loanMismatchBalanceTitle" aria-describedby="loanMismatchTrendStatus">Data bulanan tersedia melalui Buka data grafik.</canvas></div>
                        </div>
                    </div>
                    <p class="loan-mismatch-trends__status" id="loanMismatchTrendStatus" role="status" aria-live="polite">Grafik akan tampil setelah klik Proses. Hanya kategori Memburuk; Belum Waktunya Penyesuaian tidak dihitung.</p>
                    <button type="button" id="loanMismatchTrendRetry" class="btn btn-sm btn-outline-primary mt-2" hidden>Coba muat grafik lagi</button>
                    <details id="loanMismatchTrendDetails" hidden>
                        <summary>Buka data grafik</summary>
                        <div class="loan-mismatch-trends__data" tabindex="0" aria-label="Data bulanan grafik kolek Memburuk">
                            <table data-abah-no-table-guard><caption class="sr-only">Jumlah rekening dan nominal OS Memburuk per posisi bulanan</caption><thead><tr><th scope="col">Bulan / posisi</th><th scope="col">Rekening</th><th scope="col">OS (Rp)</th></tr></thead><tbody id="loanMismatchTrendData"></tbody></table>
                        </div>
                    </details>
                </section>

                <div class="table-responsive mt-4">
                    <div class="loan-mismatch-table-wrap">
                        <table class="table table-hover loan-mismatch-table mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width: 54px; text-align: center;">No</th>
                                    <th rowspan="2" style="width: 230px; text-align: left;">Unit Kerja</th>
                                    <th colspan="2" style="text-align: center;">Memburuk</th>
                                    <th colspan="2" style="text-align: center;">Membaik</th>
                                    <th colspan="2" style="text-align: center;">Belum Waktunya Penyesuaian</th>
                                    <th colspan="2" style="text-align: center;">Total</th>
                                    <th rowspan="2" style="width: 105px; text-align: center;">Export</th>
                                </tr>
                                <tr>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                    <th style="width: 72px; text-align: center;">Rek</th>
                                    <th style="width: 130px; text-align: right;">Rp</th>
                                </tr>
                            </thead>
                            <tbody id="loanMismatchBody">
                                <tr>
                                    <td colspan="11" class="loan-empty-state">
                                        <strong>Audit belum dijalankan</strong>
                                        Pilih periode dan cabang lalu klik <strong>Proses</strong>.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr id="loanMismatchFoot">
                                    <th colspan="2">Grand Total</th>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>0</td>
                                    <td class="text-right">0</td>
                                    <td>-</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanMismatchForm');
        const periodInput = document.getElementById('loanMismatchPeriodeInput');
        const branchSelect = document.getElementById('loanMismatchCabangSelect');
        const body = document.getElementById('loanMismatchBody');
        const foot = document.getElementById('loanMismatchFoot');
        const submitButton = document.getElementById('loanMismatchSubmitButton');
        const badge = document.getElementById('loanMismatchPeriodBadge');
        
        const filterUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.data'));
        const exportUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.export'));
        const timeseriesUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.timeseries'));
        const areaAllValue = 'AREA_6_ALL';
        const areaBranches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
        const initialSelectedBranch = @json(($mismatchSelectedBranches ?? ['AREA_6_ALL'])[0] ?? 'AREA_6_ALL');
        const trendPanel = document.getElementById('loanMismatchTrends');
        const trendStatus = document.getElementById('loanMismatchTrendStatus');
        const trendRetry = document.getElementById('loanMismatchTrendRetry');
        const trendDetails = document.getElementById('loanMismatchTrendDetails');
        const trendCharts = [];
        const trendNumber = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
        const trendCompact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
        let trendRequest = null;
        let appliedTrendParams = null;
        let tableRequest = null;
        let branchRequest = null;
        const trendMonth = new Intl.DateTimeFormat('id-ID', { month: 'short' });

        function clearTrendCharts() {
            trendCharts.splice(0).forEach(chart => chart.destroy());
            trendDetails.hidden = true;
            document.getElementById('loanMismatchCountValue').textContent = '—';
            document.getElementById('loanMismatchBalanceValue').textContent = '—';
            trendPanel.querySelectorAll('[data-trend-progress]').forEach(node => node.textContent = 'Memuat riwayat bulanan...');
        }

        function renderTrends(points, branch, period) {
            const available = points.filter(point => point.available);
            const pending = points.filter(point => point.pending).length;
            const failed = points.filter(point => point.failed).length;
            const latest = points[points.length - 1];
            trendPanel.querySelectorAll('[data-trend-progress]').forEach(node => {
                node.textContent = pending ? `Menghitung riwayat: ${points.length - pending}/${points.length} bulan selesai` : `${available.length}/${points.length} posisi bulanan tersedia`;
            });
            document.getElementById('loanMismatchTrendContext').textContent = `Jan-${trendMonth.format(new Date(`${period}T12:00:00`))} ${period.slice(0, 4)} | ${branch} | posisi terakhir ${formatDate(period)}`;
            document.getElementById('loanMismatchTrendData').innerHTML = points.map(point => {
                const label = point.period ? formatDate(point.period) : point.month;
                const unavailable = point.pending ? 'Memuat...' : point.failed ? 'Gagal dimuat' : 'Tidak tersedia';
                return `<tr><th scope="row">${escapeHtml(label)}</th><td>${point.available ? trendNumber.format(point.memburuk_count) : unavailable}</td><td>${point.available ? trendNumber.format(point.memburuk_balance) : unavailable}</td></tr>`;
            }).join('');
            trendDetails.hidden = false;
            document.getElementById('loanMismatchCountValue').textContent = latest?.available ? `${trendNumber.format(latest.memburuk_count)} rekening` : '—';
            document.getElementById('loanMismatchBalanceValue').textContent = latest?.available ? `Rp ${trendNumber.format(latest.memburuk_balance)}` : '—';
            const basis = 'Bulan sebelumnya: posisi akhir bulan. Bulan terakhir: posisi audit. Hanya Memburuk; Belum Waktunya Penyesuaian tidak dihitung.';
            trendStatus.textContent = pending ? `Memuat tren bulanan: ${points.length - pending} dari ${points.length} bulan selesai. ${basis}` : `${available.length} dari ${points.length} bulan tersedia. ${basis}${failed ? ` ${failed} bulan gagal dimuat; klik Coba muat grafik lagi.` : ''}${available.length < points.length - failed ? ' Jeda garis berarti data Daily Loan Dinamis asli tidak tersedia pada posisi tersebut.' : ''}`;
            trendRetry.hidden = pending > 0 || failed === 0;
            if (typeof window.Chart !== 'function') {
                trendStatus.textContent += ' Grafik tidak dapat dimuat. Buka data grafik untuk melihat angka bulanan.';
                return;
            }
            if (trendCharts.length) {
                trendCharts.forEach((chart, i) => {
                    const key = i === 0 ? 'memburuk_count' : 'memburuk_balance';
                    chart.data.datasets[0].data = points.map(point => point.available ? point[key] : null);
                    chart.update('none');
                });
                return;
            }
            [
                ['loanMismatchCountChart', 'memburuk_count', 'Rekening', '#b45309'],
                ['loanMismatchBalanceChart', 'memburuk_balance', 'OS (Rp)', '#0857c3'],
            ].forEach(([id, key, label, color]) => {
                trendCharts.push(new Chart(document.getElementById(id), {
                    type: 'line',
                    data: {
                        labels: points.map(point => point.month),
                        datasets: [{ label, data: points.map(point => point.available ? point[key] : null), borderColor: color, backgroundColor: `${color}12`, fill: true, borderWidth: 3, pointBackgroundColor: '#fff', pointBorderWidth: 2, pointRadius: 3.5, pointHoverRadius: 6, pointHitRadius: 14, spanGaps: false, tension: 0.2 }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 250 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: {
                                title: items => items.length && points[items[0].dataIndex].period ? `Posisi ${formatDate(points[items[0].dataIndex].period)}` : '',
                                label: item => `${label}: ${trendNumber.format(item.parsed.y)}`,
                            } },
                        },
                        scales: {
                            x: { grid: { display: false }, title: { display: true, text: `Bulan (${period.slice(0, 4)})` }, ticks: { autoSkip: false, maxRotation: 0, font: { size: 11 }, callback: function (value) { return trendMonth.format(new Date(`${this.getLabelForValue(value)}-01T12:00:00`)); } } },
                            y: { beginAtZero: true, title: { display: true, text: label }, ticks: { precision: key === 'memburuk_count' ? 0 : undefined, maxTicksLimit: 6, callback: value => key === 'memburuk_count' ? trendNumber.format(value) : trendCompact.format(value) }, grid: { color: '#e2e8f0' } },
                        },
                    },
                }));
            });
        }

        async function loadTrends(params) {
            trendRequest?.abort();
            const request = trendRequest = new AbortController();
            appliedTrendParams = new URLSearchParams(params);
            clearTrendCharts();
            trendPanel.setAttribute('aria-busy', 'true');
            trendRetry.hidden = true;
            trendStatus.textContent = 'Memuat riwayat bulanan untuk periode dan cabang yang diproses...';
            try {
                const cachedQuery = new URLSearchParams(params);
                cachedQuery.set('cached', '1');
                const res = await fetch(`${timeseriesUrl}?${cachedQuery}`, { signal: request.signal });
                if (!res.ok) throw new Error('Riwayat bulanan gagal dimuat.');
                const cached = await res.json();
                if (request !== trendRequest || request.signal.aborted) return;
                const period = cached.selected_period;
                const points = (cached.timeseries || []).map(point => ({ ...point, pending: Boolean(point.pending) }));
                if (!period || !points.length) {
                    trendStatus.textContent = 'Daily Loan Dinamis asli belum tersedia untuk periode dan cabang ini.';
                    trendPanel.querySelectorAll('[data-trend-progress]').forEach(node => node.textContent = 'Posisi bulanan tidak tersedia');
                    return;
                }
                // All historical months come from the applied filters, independently
                // of the slower nominative/table request.
                params = new URLSearchParams(params);
                params.set('periode', period);
                const paint = () => renderTrends(points, cached.selected_branch, period);
                paint();
                const queue = points.filter(point => point.pending);
                async function worker() {
                    while (queue.length && !request.signal.aborted) {
                        const point = queue.shift();
                        const query = new URLSearchParams(params);
                        query.set('point', point.month);
                        try {
                            let payload;
                            for (let attempt = 0; attempt < 40; attempt++) {
                                const res = await fetch(`${timeseriesUrl}?${query}`, { signal: request.signal });
                                if (!res.ok) throw new Error('Grafik gagal dimuat.');
                                payload = await res.json();
                                if (payload.status !== 'warming') break;
                                await new Promise((resolve, reject) => {
                                    const stop = () => { clearTimeout(timer); reject(new DOMException('Aborted', 'AbortError')); };
                                    const timer = setTimeout(() => { request.signal.removeEventListener('abort', stop); resolve(); }, 3000);
                                    request.signal.addEventListener('abort', stop, { once: true });
                                });
                            }
                            if (request.signal.aborted || request !== trendRequest) return;
                            const result = payload?.timeseries?.find(item => item.month === point.month);
                            if (payload?.status === 'warming' || !result) throw new Error('Perhitungan grafik belum selesai.');
                            Object.assign(point, result, { pending: false, failed: false });
                        } catch (error) {
                            if (error.name === 'AbortError' || request !== trendRequest) return;
                            Object.assign(point, { pending: false, failed: true });
                        }
                        paint();
                    }
                }
                await Promise.all([worker(), worker()]);
            } catch (error) {
                if (error.name === 'AbortError' || request !== trendRequest) return;
                trendStatus.textContent = 'Riwayat bulanan gagal dimuat. Klik Coba muat grafik lagi.';
                trendPanel.querySelectorAll('[data-trend-progress]').forEach(node => node.textContent = 'Riwayat belum dapat dimuat');
                trendRetry.hidden = false;
            } finally {
                if (request === trendRequest) trendPanel.setAttribute('aria-busy', 'false');
            }
        }

        trendRetry.addEventListener('click', () => {
            if (appliedTrendParams) loadTrends(new URLSearchParams(appliedTrendParams));
        });

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        initMultiSelect(branchSelect, 'Pilih Kantor Cabang');

        function getSelectedBranch() {
            return normalizeBranchSelection([branchSelect.value])[0];
        }

        function normalizeBranchSelection(values) {
            const selected = (values || []).filter(Boolean);
            if (selected.length === 0 || selected.includes(areaAllValue)) {
                return [areaAllValue];
            }

            const selectedBranch = selected.find(value => areaBranches.includes(value));
            return selectedBranch ? [selectedBranch] : [areaAllValue];
        }

        function getBranchLabel(value = getSelectedBranch()) {
            if (value === areaAllValue) {
                return 'Area 6 - All';
            }

            return value || 'Area 6 - All';
        }

        function syncHiddenBranchSelect(options, selectedValue) {
            const selected = normalizeBranchSelection([selectedValue])[0];
            branchSelect.innerHTML = '';
            (options || []).forEach(branch => {
                const label = branch === areaAllValue ? 'Area 6 - All' : branch;
                branchSelect.add(new Option(label, branch, false, selected === branch));
            });
        }

        async function loadBranches() {
            if (!periodInput.value) return;
            branchRequest?.abort();
            const request = branchRequest = new AbortController();
            const period = periodInput.value;
            try {
                const res = await fetch(`${filterUrl}?periode=${period}`, { signal: request.signal });
                if (!res.ok) throw new Error('Daftar cabang gagal dimuat.');
                const payload = await res.json();
                if (request !== branchRequest || period !== periodInput.value) return;
                syncHiddenBranchSelect(payload.branches || [], branchSelect.dataset.selected || initialSelectedBranch);
                branchSelect.disabled = false;
                window.jQuery(branchSelect).trigger('change');
            } catch (error) {
                if (error.name !== 'AbortError') throw error;
            }
        }

        async function loadData() {
            const selectedBranch = getSelectedBranch();

            if (!periodInput.value || !selectedBranch) return;
            
            tableRequest?.abort();
            const request = tableRequest = new AbortController();
            const originalBtnHtml = submitButton.dataset.originalHtml || submitButton.innerHTML;
            submitButton.dataset.originalHtml = originalBtnHtml;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Memuat...`;
            submitButton.disabled = true;
            
            try {
                const params = new URLSearchParams();
                params.set('periode', periodInput.value);
                params.set('cabang1', selectedBranch);

                loadTrends(new URLSearchParams(params));
                const res = await fetch(`${dataUrl}?${params.toString()}`, { signal: request.signal });
                if (!res.ok) throw new Error('Data audit gagal dimuat.');
                const payload = await res.json();
                if (request !== tableRequest || request.signal.aborted) return;
                renderTable(payload.summary_rows, payload.selected_period, payload.selected_branch);
                document.getElementById('loanMismatchTotal').textContent = formatNumber(payload.audit.mismatch_rows);
                document.getElementById('loanMismatchUnits').textContent = formatNumber(payload.audit.units_with_mismatch);
                document.getElementById('loanMismatchOutstanding').textContent = formatCurrency(payload.audit.total_outstanding_balance);
            } catch (error) {
                if (error.name !== 'AbortError' && request === tableRequest) {
                    badge.textContent = 'Data audit gagal dimuat. Klik Proses untuk mencoba lagi.';
                }
            } finally {
                if (request === tableRequest) {
                    submitButton.innerHTML = originalBtnHtml;
                    submitButton.disabled = false;
                }
            }
        }

        function formatCurrency(value) {
            const amount = Number(value || 0);
            return formatNumber(amount);
        }

        function sumRows(rows, key) {
            return rows.reduce((total, row) => total + Number(row[key] || 0), 0);
        }

        function renderFoot(rows = []) {
            const tableRows = Array.isArray(rows) ? rows : [];
            foot.innerHTML = `
                <th colspan="2">Grand Total</th>
                <td>${formatNumber(sumRows(tableRows, 'memburuk_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'memburuk_os'))}</td>
                <td>${formatNumber(sumRows(tableRows, 'kolek_membaik_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'kolek_membaik_os'))}</td>
                <td>${formatNumber(sumRows(tableRows, 'belum_waktunya_penyesuaian_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'belum_waktunya_penyesuaian_os'))}</td>
                <td>${formatNumber(sumRows(tableRows, 'mismatch_count'))}</td>
                <td class="text-right">${formatCurrency(sumRows(tableRows, 'outstanding_balance'))}</td>
                <td>-</td>
            `;
        }

        function buildExportUrl(period, branch, row) {
            const params = new URLSearchParams();
            params.set('periode', period);
            params.set('cabang1', row.branch || branch);

            if (!row.is_branch_summary && row.unit) {
                params.set('unit1', row.unit);
            }

            return `${exportUrl}?${params.toString()}`;
        }

        function renderTable(rows, period, branch) {
            const tableRows = Array.isArray(rows) ? rows : [];

            if (!tableRows.length) {
                body.innerHTML = `
                    <tr>
                        <td colspan="11" class="loan-empty-state">
                            <strong>Tidak ada mismatch</strong>
                            Data pada periode dan cabang ini tidak memiliki kolek tidak sesuai.
                        </td>
                    </tr>
                `;
                renderFoot([]);
                badge.textContent = `${formatDate(period)} | ${branch}`;
                return;
            }

            body.innerHTML = tableRows.map((row, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td>${escapeHtml(row.label || row.unit)}</td>
                    <td>${formatNumber(row.memburuk_count)}</td>
                    <td class="text-right">${formatCurrency(row.memburuk_os)}</td>
                    <td>${formatNumber(row.kolek_membaik_count)}</td>
                    <td class="text-right">${formatCurrency(row.kolek_membaik_os)}</td>
                    <td>${formatNumber(row.belum_waktunya_penyesuaian_count)}</td>
                    <td class="text-right">${formatCurrency(row.belum_waktunya_penyesuaian_os)}</td>
                    <td class="text-danger font-weight-bold">${formatNumber(row.mismatch_count)}</td>
                    <td class="text-right">${formatCurrency(row.outstanding_balance)}</td>
                    <td><a href="${buildExportUrl(period, branch, row)}" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel mr-1"></i> Excel</a></td>
                </tr>
            `).join('');
            renderFoot(tableRows);
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
                branchSelect.dataset.selected = areaAllValue;
                loadBranches().catch(() => null);
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
                
                if (options.length === 0) {
                    cabangMenu.innerHTML = '<div class="px-3 py-3 text-center text-muted small">Pilih periode dulu</div>';
                    return;
                }

                options.forEach(opt => {
                    const item = document.createElement('div');
                    item.className = `loan-dropdown-option ${opt.selected ? 'is-active' : ''}`;
                    item.innerHTML = '<div class="loan-dropdown-check"><i class="fas fa-check"></i></div><span></span>';
                    item.querySelector('span').textContent = opt.text;
                    item.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (opt.value === areaAllValue) {
                            options.forEach(option => option.selected = option.value === areaAllValue);
                        } else {
                            options.forEach(option => option.selected = option === opt);
                        }

                        branchSelect.value = opt.value;
                        branchSelect.dataset.selected = getSelectedBranch();
                        cabangText.textContent = getBranchLabel();
                        options.forEach((option, index) => {
                            if (cabangMenu.children[index]) {
                                cabangMenu.children[index].classList.toggle('is-active', option.selected);
                            }
                        });
                        window.jQuery(branchSelect).trigger('change');
                    });
                    cabangMenu.appendChild(item);
                });

                cabangText.textContent = getBranchLabel();
            });

            observer.observe(branchSelect, { childList: true });
        }

        initModernSelectors();

        if (periodInput.value) loadBranches().catch(() => null);
    });
</script>
@endpush

@endsection
