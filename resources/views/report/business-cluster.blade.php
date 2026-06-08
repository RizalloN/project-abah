@extends('layouts.admin')

@section('title', 'Business Cluster')

@section('content')
@include('report._bri-report-ui')

<style>
    .business-cluster-page .report-filter-card,
    .business-cluster-page .report-data-card {
        border-radius: 20px !important;
        border: 1px solid rgba(219, 229, 239, 0.7) !important;
        background: #ffffff !important;
        box-shadow: 0 16px 36px -24px rgba(0, 82, 156, 0.14), 0 2px 8px rgba(0, 0, 0, 0.01) !important;
        transition: all 0.3s ease;
    }

    .business-cluster-page .report-filter-card {
        overflow: visible !important;
        position: relative;
        z-index: 100;
    }

    .business-cluster-page .report-data-card {
        overflow: hidden;
        border-left: 5px solid #00529c !important;
        position: relative;
        z-index: 1;
    }

    .business-cluster-page .report-data-card .card-header {
        border-bottom: 1px solid rgba(219, 229, 239, 0.7);
        background: linear-gradient(90deg, #ffffff 0%, #f4f8fc 100%) !important;
        padding: 1.25rem 1.5rem;
    }

    .business-cluster-page .report-title {
        margin: 0;
        color: #004685;
        font-size: 1.3rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .business-cluster-page .report-title i {
        background: linear-gradient(135deg, #00529c, #0071e3);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        filter: drop-shadow(0 2px 4px rgba(0, 82, 156, 0.15));
    }

    .business-cluster-page .report-filter-label {
        color: #475569 !important;
        font-size: 0.78rem !important;
        font-weight: 700 !important;
        margin-bottom: 0.5rem !important;
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .business-cluster-page .report-filter-label i {
        color: #00529c;
        opacity: 0.8;
    }

    .business-cluster-page .report-input,
    .business-cluster-page .branch-dropdown-toggle {
        border-radius: 12px !important;
        border: 1.5px solid #cbd8e8 !important;
        background: #ffffff !important;
        font-size: 0.88rem !important;
        font-weight: 600 !important;
        color: #1e293b !important;
        padding: 0.65rem 1rem !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02) !important;
        transition: all 0.2s ease !important;
        min-height: 42px !important;
        height: 42px !important;
    }

    .business-cluster-page .report-input:disabled {
        background: #f1f5f9 !important;
        border-color: #e2e8f0 !important;
        color: #64748b !important;
        cursor: not-allowed;
        opacity: 1;
        box-shadow: none !important;
    }

    .business-cluster-page .table-container {
        border: 1px solid rgba(219, 229, 239, 0.6) !important;
        border-radius: 16px !important;
        overflow: hidden !important;
        box-shadow: 0 8px 24px -12px rgba(0, 82, 156, 0.1) !important;
    }

    .business-cluster-page .table-report {
        border-collapse: separate;
        border-spacing: 0;
        width: 100% !important;
    }

    .business-cluster-page .table-report thead th {
        background: linear-gradient(135deg, #004685 0%, #0066c2 100%) !important;
        color: #ffffff !important;
        font-size: 0.78rem !important;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 14px 18px !important;
        border: none !important;
        text-align: left;
    }

    .business-cluster-page .table-report thead th:last-child {
        text-align: right;
    }

    .business-cluster-page .table-report tbody td {
        font-size: 0.85rem !important;
        padding: 12px 18px !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.7) !important;
        border-left: none !important;
        border-right: none !important;
        border-top: none !important;
        color: #334155;
        vertical-align: middle !important;
        font-variant-numeric: tabular-nums;
    }

    .business-cluster-page .table-report tbody tr {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .business-cluster-page .table-report tbody tr:hover {
        background-color: rgba(0, 82, 156, 0.04) !important;
        transform: scale(1.001) translateY(-0.5px);
        box-shadow: inset 3px 0 0 #00529c, 0 4px 12px rgba(0, 70, 133, 0.04);
    }

    .business-cluster-page .table-report tbody tr:nth-child(even) {
        background: #fafcff;
    }

    .business-cluster-page .empty-state {
        text-align: center !important;
        color: #64748b;
        background: #ffffff;
        padding: 4rem 1rem !important;
        border: none !important;
    }

    .business-cluster-page .empty-state i {
        color: #cbd5e1;
        display: block;
        margin-bottom: 1rem;
    }

    .business-cluster-page .report-meta {
        background: linear-gradient(135deg, #004685, #006ec7);
        color: #ffffff !important;
        font-size: 0.85rem;
        font-weight: 700;
        padding: 6px 16px;
        border-radius: 30px;
        box-shadow: 0 4px 12px rgba(0, 70, 133, 0.15);
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.02em;
    }

    .business-cluster-page .badge-category {
        background: rgba(0, 82, 156, 0.06);
        color: #00529c;
        border: 1px solid rgba(0, 82, 156, 0.12);
        font-size: 0.75rem;
        font-weight: 700;
        padding: 5px 12px;
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.01em;
        box-shadow: 0 2px 4px rgba(0, 82, 156, 0.02);
        transition: all 0.2s ease;
    }

    .business-cluster-page .table-report tbody tr:hover .badge-category {
        background: rgba(0, 82, 156, 0.1);
        border-color: rgba(0, 82, 156, 0.2);
        transform: translateY(-0.5px);
    }

    .business-cluster-page .badge-jumlah {
        background: rgba(2, 132, 199, 0.08);
        color: #0284c7;
        border: 1px solid rgba(2, 132, 199, 0.15);
        font-size: 0.82rem;
        font-weight: 750;
        padding: 5px 14px;
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 50px;
        font-variant-numeric: tabular-nums;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .business-cluster-page .badge-jumlah--success {
        background: rgba(22, 163, 74, 0.08);
        color: #15803d;
        border-color: rgba(22, 163, 74, 0.16);
    }

    .business-cluster-page .badge-jumlah--warning {
        background: rgba(245, 158, 11, 0.1);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.2);
    }

    .business-cluster-page .badge-jumlah--filter {
        cursor: pointer;
    }

    .business-cluster-page .table-report tbody tr:hover .badge-jumlah {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
        transform: scale(1.05);
        box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25);
    }

    .business-cluster-page .table-report tbody tr:hover .badge-jumlah--success {
        background: #15803d;
        border-color: #15803d;
        box-shadow: 0 4px 10px rgba(21, 128, 61, 0.22);
    }

    .business-cluster-page .table-report tbody tr:hover .badge-jumlah--warning {
        background: #b45309;
        border-color: #b45309;
        box-shadow: 0 4px 10px rgba(180, 83, 9, 0.22);
    }

    .business-cluster-page .business-cluster-detail-row {
        cursor: pointer;
    }

    .business-cluster-page .business-cluster-detail-row:focus {
        outline: 3px solid rgba(0, 82, 156, 0.22);
        outline-offset: -3px;
    }

    .business-cluster-page .branch-name {
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .business-cluster-page .branch-name i {
        color: #64748b;
        font-size: 0.85rem;
        transition: color 0.2s ease;
    }

    .business-cluster-page .table-report tbody tr:hover .branch-name i {
        color: #00529c;
    }

    .business-cluster-page .branch-filter-dropdown {
        position: relative;
    }

    .business-cluster-page .branch-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
        background: #ffffff;
        border: 1.5px solid #bfd1e5;
    }

    .business-cluster-page .branch-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .business-cluster-page .branch-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        z-index: 1050;
        display: none;
        width: 100%;
        min-width: 240px;
        max-height: 280px;
        overflow-y: auto;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid rgba(191, 209, 229, 0.8);
        border-radius: 14px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        padding: 8px;
        backdrop-filter: blur(8px);
    }

    .business-cluster-page .branch-dropdown-menu.show {
        display: block;
        animation: slideDownFade 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .business-cluster-page .branch-dropdown-menu .dropdown-item {
        margin-bottom: 1px;
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        border-radius: 10px;
        transition: background 0.2s ease;
    }

    .business-cluster-page .branch-dropdown-menu .dropdown-item:hover {
        background: rgba(0, 82, 156, 0.05);
    }

    .business-cluster-page .branch-dropdown-menu .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .business-cluster-page .branch-dropdown-menu .form-check-input {
        position: static;
        margin: 0;
        width: 1.05rem;
        height: 1.05rem;
        border: 1.5px solid #cbd8e8;
        border-radius: 4px;
        cursor: pointer;
    }

    .business-cluster-page .branch-dropdown-menu .form-check-input:checked {
        background-color: #00529c;
        border-color: #00529c;
    }

    .business-cluster-page .branch-dropdown-menu .form-check-label {
        margin: 0;
        color: #334155;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
    }

    @keyframes slideDownFade {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Premium Modal Style with Zoom Spring Entrance */
    .business-cluster-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: rgba(15, 23, 42, 0);
        backdrop-filter: blur(0px);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease, backdrop-filter 0.3s ease, background-color 0.3s ease;
    }

    .business-cluster-modal.show {
        pointer-events: auto;
        opacity: 1;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px);
    }

    .business-cluster-modal__dialog {
        width: 100%;
        max-width: 950px;
        max-height: 85vh;
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35);
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
        transform: scale(0.92) translateY(20px);
        opacity: 0;
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
    }

    .business-cluster-modal.show .business-cluster-modal__dialog {
        transform: scale(1) translateY(0);
        opacity: 1;
    }

    .business-cluster-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.25rem 1.75rem;
        background: linear-gradient(135deg, #004685 0%, #006ec7 100%);
        color: #ffffff;
        border-top-left-radius: 23px;
        border-top-right-radius: 23px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .business-cluster-modal__title {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    .business-cluster-modal__subtitle {
        margin-top: 2px;
        font-size: 0.85rem;
        font-weight: 600;
        opacity: 0.9;
    }

    .business-cluster-modal__close {
        border: 0;
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .business-cluster-modal__close:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: rotate(90deg) scale(1.05);
    }

    .business-cluster-modal__body {
        padding: 1.5rem 1.75rem;
        overflow-y: auto;
        background: #ffffff;
        scrollbar-width: thin;
    }

    .business-cluster-modal__table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .business-cluster-modal__table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8fafc;
        color: #475569;
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 12px 16px;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
    }

    .business-cluster-modal__table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.85rem;
        vertical-align: middle;
        line-height: 1.5;
        word-break: break-word;
        white-space: normal;
    }

    .business-cluster-modal__table tbody tr {
        transition: all 0.2s ease;
    }

    .business-cluster-modal__table tbody tr:hover {
        background-color: #fafcff;
    }

    /* Proportional flexible columns */
    .business-cluster-modal__table th:nth-child(1),
    .business-cluster-modal__table td:nth-child(1) {
        width: 28%;
        font-weight: 700;
        color: #004685;
    }

    .business-cluster-modal__table th:nth-child(2),
    .business-cluster-modal__table td:nth-child(2) {
        width: 42%;
    }

    .business-cluster-modal__table th:nth-child(3),
    .business-cluster-modal__table td:nth-child(3) {
        width: 16%;
        font-weight: 600;
        color: #475569;
    }

    .business-cluster-modal__table th:nth-child(4),
    .business-cluster-modal__table td:nth-child(4) {
        width: 14%;
        font-weight: 700;
        color: #475569;
    }

    .business-cluster-modal__empty {
        padding: 3rem 1rem;
        text-align: center;
        color: #64748b;
        font-weight: 600;
    }
</style>

@php
    $rows = collect($rows ?? []);
    $sources = collect($sources ?? []);
    $errors = collect($errors ?? []);
    $branchOptions = collect($branchOptions ?? ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo']);
    $selectedBranchOffices = collect($selectedBranchOffices ?? []);
    $branchScopeLabel = $branchScopeLabel ?? ($selectedBranchOffices->isNotEmpty() ? $selectedBranchOffices->join(', ') : 'Area 6 - All');
    $latestPosition = $latestPosition ?? now()->format('d/m/Y');
    $totalJumlah = (int) ($totalJumlah ?? $rows->sum('jumlah'));
    $totalSudahBri = (int) ($totalSudahBri ?? $rows->sum('sudah_bri'));
    $totalBelumBri = (int) ($totalBelumBri ?? $rows->sum('belum_bri'));
    $detailRowsByKey = $rows->mapWithKeys(fn ($row) => [
        $row['detail_key'] => [
            'kategori' => $row['kategori'],
            'rows' => $row['details'] ?? [],
        ],
    ]);
@endphp

<div class="report-wrapper business-cluster-page">
    <div class="report-filter-card card mb-4">
        <div class="card-body">
            <form id="businessClusterFilterForm" method="GET" action="{{ route('report.kolaborasi.business-cluster') }}">
            <div class="row align-items-end">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="report-filter-label"><i class="far fa-building"></i> BRANCH OFFICE (Kanca)</label>
                        <div class="branch-filter-dropdown">
                            <button type="button" class="form-control font-weight-bold branch-dropdown-toggle" id="businessClusterBranchDropdown" aria-haspopup="true" aria-expanded="false">
                                <span id="businessClusterBranchLabel" class="branch-dropdown-label">{{ $branchScopeLabel }}</span>
                                <i class="fas fa-chevron-down text-muted"></i>
                            </button>
                            <div class="branch-dropdown-menu" id="businessClusterBranchMenu" aria-labelledby="businessClusterBranchDropdown">
                                @foreach($branchOptions as $branchOption)
                                    @php $branchId = 'business_cluster_branch_' . \Illuminate\Support\Str::slug($branchOption, '_'); @endphp
                                    <label class="dropdown-item" for="{{ $branchId }}">
                                        <div class="form-check">
                                            <input class="form-check-input business-cluster-branch-checkbox" type="checkbox" name="branch_office[]" value="{{ $branchOption }}" id="{{ $branchId }}" {{ $selectedBranchOffices->contains($branchOption) ? 'checked' : '' }}>
                                            <span class="form-check-label">{{ $branchOption }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="form-group mb-0">
                        <label class="report-filter-label"><i class="far fa-file-alt"></i> Nama Report</label>
                        <input type="text" class="form-control report-input font-weight-bold" value="Business Cluster" disabled>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="report-filter-label"><i class="far fa-calendar-alt"></i> Posisi Terakhir <i class="fas fa-edit text-success ml-1" style="font-size: 0.75rem;"></i></label>
                        <input type="text" class="form-control report-input font-weight-bold" value="{{ $latestPosition }}" disabled>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>

    <div class="report-data-card card mb-4">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 0.75rem;">
                <h3 class="report-title">
                    <i class="fas fa-layer-group"></i>
                    Business Cluster
                </h3>
                <div class="report-meta">
                    <i class="fas fa-calculator mr-1"></i>
                    <span>Total: {{ number_format($totalJumlah, 0, ',', '.') }} | Sudah BRI: {{ number_format($totalSudahBri, 0, ',', '.') }} | Belum BRI: {{ number_format($totalBelumBri, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="card-body p-4 bg-white">
            @if($errors->isNotEmpty())
                <div class="alert alert-warning mb-3">
                    @foreach($errors as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="table-container">
                <table class="table table-report m-0">
                    <thead>
                        <tr>
                            <th class="align-middle text-left">BRANCH OFFICE</th>
                            <th class="align-middle text-left">Kategori</th>
                            <th class="align-middle text-right">Sudah di BRI</th>
                            <th class="align-middle text-right">Belum di BRI</th>
                            <th class="align-middle text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="business-cluster-detail-row" tabindex="0" role="button" data-detail-key="{{ $row['detail_key'] }}" aria-label="Lihat detail kategori {{ $row['kategori'] }}">
                                <td class="text-left">
                                    <div class="branch-name">
                                        <i class="far fa-building text-muted"></i>
                                        <span>{{ $row['branch_office'] }}</span>
                                    </div>
                                </td>
                                <td class="text-left">
                                    <span class="badge-category">
                                        <i class="fas fa-tag opacity-70"></i>
                                        {{ ucwords(strtolower($row['kategori'])) }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="badge-jumlah badge-jumlah--success badge-jumlah--filter" data-status-filter="sudah" title="Lihat detail sudah di BRI">
                                        {{ number_format((int) ($row['sudah_bri'] ?? 0), 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="badge-jumlah badge-jumlah--warning badge-jumlah--filter" data-status-filter="belum" title="Lihat detail belum di BRI">
                                        {{ number_format((int) ($row['belum_bri'] ?? 0), 0, ',', '.') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-jumlah">
                                        {{ number_format((int) $row['jumlah'], 0, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-inbox fa-3x"></i>
                                    Belum ada data Business Cluster yang bisa ditampilkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="business-cluster-modal" id="businessClusterDetailModal" aria-hidden="true">
    <div class="business-cluster-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="businessClusterDetailTitle">
        <div class="business-cluster-modal__header">
            <div>
                <h4 class="business-cluster-modal__title" id="businessClusterDetailTitle">Detail Business Cluster</h4>
                <div class="business-cluster-modal__subtitle" id="businessClusterDetailSubtitle">-</div>
            </div>
            <button type="button" class="business-cluster-modal__close" id="businessClusterDetailClose" aria-label="Tutup">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="business-cluster-modal__body">
            <table class="business-cluster-modal__table">
                <thead>
                    <tr>
                        <th>Nama Usaha</th>
                        <th>Alamat Lengkap</th>
                        <th>Kota/Kabupaten</th>
                        <th>Sudah/Blm BRI</th>
                    </tr>
                </thead>
                <tbody id="businessClusterDetailBody">
                    <tr>
                        <td colspan="4" class="business-cluster-modal__empty">Belum ada detail.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropdown = document.getElementById('businessClusterBranchDropdown');
    const menu = document.getElementById('businessClusterBranchMenu');
    const label = document.getElementById('businessClusterBranchLabel');
    const form = document.getElementById('businessClusterFilterForm');
    const checkboxes = Array.from(document.querySelectorAll('.business-cluster-branch-checkbox'));
    const detailData = {{ \Illuminate\Support\Js::from($detailRowsByKey) }};
    const detailRows = Array.from(document.querySelectorAll('.business-cluster-detail-row'));
    const detailModal = document.getElementById('businessClusterDetailModal');
    const detailTitle = document.getElementById('businessClusterDetailTitle');
    const detailSubtitle = document.getElementById('businessClusterDetailSubtitle');
    const detailBody = document.getElementById('businessClusterDetailBody');
    const detailClose = document.getElementById('businessClusterDetailClose');

    function selectedBranches() {
        return checkboxes
            .filter(function (checkbox) {
                return checkbox.checked;
            })
            .map(function (checkbox) {
                return checkbox.value;
            });
    }

    function updateBranchLabel() {
        const selected = selectedBranches();
        label.textContent = selected.length ? selected.join(', ') : 'Area 6 - All';
    }

    dropdown?.addEventListener('click', function (event) {
        event.preventDefault();
        menu?.classList.toggle('show');
        dropdown.setAttribute('aria-expanded', menu?.classList.contains('show') ? 'true' : 'false');
    });

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            updateBranchLabel();
            form?.submit();
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.branch-filter-dropdown')) {
            menu?.classList.remove('show');
            dropdown?.setAttribute('aria-expanded', 'false');
        }
    });

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value || '-';
        return div.innerHTML;
    }

    function statusFilterLabel(statusFilter) {
        if (statusFilter === 'sudah') {
            return 'Sudah di BRI';
        }

        if (statusFilter === 'belum') {
            return 'Belum di BRI';
        }

        return '';
    }

    function openDetailModal(detailKey, statusFilter) {
        const payload = detailData[detailKey] || { kategori: '-', rows: [] };
        const allRows = Array.isArray(payload.rows) ? payload.rows : [];
        const rows = statusFilter
            ? allRows.filter(function (row) {
                return row.status_bri_key === statusFilter;
            })
            : allRows;
        const filterLabel = statusFilterLabel(statusFilter);

        if (detailTitle) {
            detailTitle.textContent = `Detail ${payload.kategori || 'Business Cluster'}`;
        }

        if (detailSubtitle) {
            detailSubtitle.textContent = `${rows.length.toLocaleString('id-ID')} data usaha${filterLabel ? ' - ' + filterLabel : ''}`;
        }

        if (detailBody) {
            detailBody.innerHTML = rows.length
                ? rows.map(function (row) {
                    return `
                        <tr>
                            <td>${escapeHtml(row.nama_usaha)}</td>
                            <td>${escapeHtml(row.alamat_lengkap)}</td>
                            <td>${escapeHtml(row.kota_kabupaten)}</td>
                            <td>${escapeHtml(row.status_bri)}</td>
                        </tr>
                    `;
                }).join('')
                : '<tr><td colspan="4" class="business-cluster-modal__empty">Belum ada detail untuk kategori ini.</td></tr>';
        }

        detailModal?.classList.add('show');
        detailModal?.setAttribute('aria-hidden', 'false');
    }

    function closeDetailModal() {
        detailModal?.classList.remove('show');
        detailModal?.setAttribute('aria-hidden', 'true');
    }

    detailRows.forEach(function (row) {
        row.addEventListener('click', function () {
            openDetailModal(row.getAttribute('data-detail-key'));
        });
        row.querySelectorAll('[data-status-filter]').forEach(function (badge) {
            badge.addEventListener('click', function (event) {
                event.stopPropagation();
                openDetailModal(row.getAttribute('data-detail-key'), badge.getAttribute('data-status-filter'));
            });
        });
        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDetailModal(row.getAttribute('data-detail-key'));
            }
        });
    });

    detailClose?.addEventListener('click', closeDetailModal);
    detailModal?.addEventListener('click', function (event) {
        if (event.target === detailModal) {
            closeDetailModal();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDetailModal();
        }
    });
});
</script>
@endsection
