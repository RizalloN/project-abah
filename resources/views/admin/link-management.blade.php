@extends('layouts.admin')

@section('title', 'Link Management')

@section('content')
<div class="container-fluid pt-2 pb-4 link-management-page">
    <!-- Hero Header -->
    <div class="link-hero mb-4">
        <div class="link-hero__glow"></div>
        <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
            <div class="pr-3">
                <span class="link-hero__eyebrow"><i class="fas fa-external-link-alt mr-1"></i> Cloud Integration &amp; Spreadsheets</span>
                <h2 class="link-hero__title h4 font-weight-bold text-white mb-0"><i class="fas fa-link mr-2"></i> Manajemen Link Spreadsheet</h2>
                <p class="link-hero__text mb-0">Konfigurasi integrasi Google Sheet dan spreadsheet dinamis untuk sinkronisasi data visualisasi dashboard.</p>
            </div>
            <div class="link-hero__badge mt-3 mt-md-0">
                <i class="fas fa-user-shield mr-2"></i> Akses Administrator
            </div>
        </div>
    </div>

    @if(!$linkTableReady)
        <div class="alert alert-warning border-0 shadow-sm mb-4" style="border-radius: 10px;">
            <i class="fas fa-exclamation-triangle mr-2"></i>Tabel <strong>external_report_links</strong> belum tersedia. Jalankan migration agar link dashboard dapat disimpan.
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 10px;">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 10px;">
            <strong>Data belum bisa disimpan.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('link-management.update') }}">
        @csrf

        <!-- 1. KPI Almafacts -->
        <div class="card shadow-sm border-0 mb-4 link-management-card">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <span class="link-card-icon link-card-icon--primary mr-2"><i class="fas fa-chart-line"></i></span>
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0">KPI Almafacts</h6>
                        <span class="text-muted small">Link spreadsheet untuk laporan kinerja internal</span>
                    </div>
                </div>
                <span class="badge badge-primary px-3 py-1 font-weight-bold" style="border-radius: 999px;">{{ count($kpiLinks) }} Dashboard</span>
            </div>
            <div class="table-responsive bg-white">
                <table class="table table-hover mb-0 link-management-table">
                    <thead>
                        <tr>
                            <th style="width: 200px;" class="pl-4">Dashboard</th>
                            <th style="width: 210px;">Nama Sheet</th>
                            <th>Link Spreadsheet</th>
                            <th style="width: 80px;" class="text-center pr-4">Buka</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kpiLinks as $key => $link)
                            <tr>
                                <td class="pl-4 align-middle">
                                    <div class="font-weight-bold text-dark" style="font-size: 0.9rem;">{{ $link['label'] }}</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">{{ $link['spreadsheet_id'] }}</div>
                                </td>
                                <td class="align-middle">
                                    <input
                                        type="text"
                                        name="kpi[{{ $key }}][sheet_name]"
                                        value="{{ old("kpi.$key.sheet_name", $link['sheet_name']) }}"
                                        class="form-control link-field"
                                        required
                                    >
                                </td>
                                <td class="align-middle">
                                    <input
                                        type="url"
                                        name="kpi[{{ $key }}][link_url]"
                                        value="{{ old("kpi.$key.link_url", $link['link_url']) }}"
                                        class="form-control link-field"
                                        placeholder="https://docs.google.com/spreadsheets/d/..."
                                        required
                                    >
                                </td>
                                <td class="text-center align-middle pr-4">
                                    <a href="{{ $link['link_url'] }}" target="_blank" rel="noopener" class="link-open-btn" title="Buka spreadsheet">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. SPPG -->
        <div class="card shadow-sm border-0 mb-4 link-management-card">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <span class="link-card-icon link-card-icon--emerald mr-2"><i class="fas fa-seedling"></i></span>
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0">SPPG Monitoring</h6>
                        <span class="text-muted small">Link spreadsheet evaluasi SPPG</span>
                    </div>
                </div>
            </div>
            <div class="table-responsive bg-white">
                <table class="table table-hover mb-0 link-management-table">
                    <thead>
                        <tr>
                            <th style="width: 200px;" class="pl-4">Dashboard</th>
                            <th style="width: 210px;">Nama Sheet</th>
                            <th>Link Spreadsheet</th>
                            <th style="width: 80px;" class="text-center pr-4">Buka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="pl-4 align-middle">
                                <div class="font-weight-bold text-dark" style="font-size: 0.9rem;">{{ $sppgLink['label'] }}</div>
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ $sppgLink['spreadsheet_id'] ?: 'Google Sheet Area 6' }}</div>
                            </td>
                            <td class="align-middle">
                                <input
                                    type="text"
                                    name="sppg[sheet_name]"
                                    value="{{ old('sppg.sheet_name', $sppgLink['sheet_name']) }}"
                                    class="form-control link-field"
                                    placeholder="Area 6"
                                >
                            </td>
                            <td class="align-middle">
                                <input
                                    type="url"
                                    name="sppg[link_url]"
                                    value="{{ old('sppg.link_url', $sppgLink['link_url']) }}"
                                    class="form-control link-field"
                                    placeholder="https://docs.google.com/spreadsheets/d/..."
                                >
                            </td>
                            <td class="text-center align-middle pr-4">
                                @if($sppgLink['link_url'])
                                    <a href="{{ $sppgLink['link_url'] }}" target="_blank" rel="noopener" class="link-open-btn" title="Buka spreadsheet">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Market Share -->
        <div class="card shadow-sm border-0 mb-4 link-management-card">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <span class="link-card-icon link-card-icon--amber mr-2"><i class="fas fa-chart-pie"></i></span>
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0">Market Share</h6>
                        <span class="text-muted small">Mapping sumber data market share eksternal</span>
                    </div>
                </div>
            </div>
            <div class="table-responsive bg-white">
                <table class="table table-hover mb-0 link-management-table">
                    <thead>
                        <tr>
                            <th style="width: 200px;" class="pl-4">Dashboard</th>
                            <th style="width: 210px;">Sheet Awal</th>
                            <th>Link Spreadsheet</th>
                            <th style="width: 80px;" class="text-center pr-4">Buka</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($marketShareLinks ?? []) as $key => $link)
                            <tr>
                                <td class="pl-4 align-middle">
                                    <div class="font-weight-bold text-dark" style="font-size: 0.9rem;">{{ $link['label'] }}</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">{{ $link['spreadsheet_id'] ?: 'Google Spreadsheet' }}</div>
                                </td>
                                <td class="align-middle">
                                    <input
                                        type="text"
                                        name="market_share[{{ $key }}][sheet_name]"
                                        value="{{ old("market_share.$key.sheet_name", $link['sheet_name']) }}"
                                        class="form-control link-field"
                                        required
                                    >
                                </td>
                                <td class="align-middle">
                                    <input
                                        type="url"
                                        name="market_share[{{ $key }}][link_url]"
                                        value="{{ old("market_share.$key.link_url", $link['link_url']) }}"
                                        class="form-control link-field"
                                        placeholder="https://docs.google.com/spreadsheets/d/..."
                                        required
                                    >
                                </td>
                                <td class="text-center align-middle pr-4">
                                    @if($link['link_url'])
                                        <a href="{{ $link['link_url'] }}" target="_blank" rel="noopener" class="link-open-btn" title="Buka spreadsheet">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Pipeline Mikro -->
        <div class="card shadow-sm border-0 mb-4 link-management-card">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <span class="link-card-icon link-card-icon--primary mr-2"><i class="fas fa-project-diagram"></i></span>
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0">Pipeline Mikro</h6>
                        <span class="text-muted small">Prewash dan SLIK Hijau landing page Micro</span>
                    </div>
                </div>
            </div>
            <div class="table-responsive bg-white">
                <table class="table table-hover mb-0 link-management-table">
                    <thead><tr><th style="width: 240px;" class="pl-4">Dataset</th><th style="width: 210px;">Sheet</th><th>Link Spreadsheet</th><th style="width: 80px;" class="text-center pr-4">Buka</th></tr></thead>
                    <tbody>
                        @foreach(($microPipelineLinks ?? []) as $key => $link)
                            <tr>
                                <td class="pl-4 align-middle"><div class="font-weight-bold text-dark" style="font-size: .9rem;">{{ $link['label'] }}</div><div class="text-muted small" style="font-size: .75rem;">{{ $link['spreadsheet_id'] }}</div></td>
                                <td class="align-middle"><input type="text" name="micro_pipeline[{{ $key }}][sheet_name]" value="{{ old("micro_pipeline.$key.sheet_name", $link['sheet_name']) }}" class="form-control link-field" required></td>
                                <td class="align-middle"><input type="url" name="micro_pipeline[{{ $key }}][link_url]" value="{{ old("micro_pipeline.$key.link_url", $link['link_url']) }}" class="form-control link-field" placeholder="https://docs.google.com/spreadsheets/d/..." required></td>
                                <td class="text-center align-middle pr-4"><a href="{{ $link['link_url'] }}" target="_blank" rel="noopener" class="link-open-btn" title="Buka spreadsheet"><i class="fas fa-external-link-alt"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. Business Cluster -->
        <div class="card shadow-sm border-0 mb-4 link-management-card">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <span class="link-card-icon link-card-icon--indigo mr-2"><i class="fas fa-building"></i></span>
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0">Business Cluster</h6>
                        <span class="text-muted small">Link spreadsheet per kantor cabang</span>
                    </div>
                </div>
            </div>
            @if(!$businessClusterTableReady)
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-info-circle mr-1"></i>Tabel business_cluster belum tersedia.
                </div>
            @else
                <div class="table-responsive bg-white">
                    <table class="table table-hover mb-0 link-management-table">
                        <thead>
                            <tr>
                                <th style="width: 200px;" class="pl-4">Kantor Cabang (Kanca)</th>
                                <th>Link Spreadsheet</th>
                                <th style="width: 80px;" class="text-center pr-4">Buka</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($businessClusterLinks as $branch => $link)
                                <tr>
                                    <td class="pl-4 align-middle">
                                        <strong class="text-dark" style="font-size: 0.9rem;">{{ $link['label'] }}</strong>
                                    </td>
                                    <td class="align-middle">
                                        <input
                                            type="url"
                                            name="business_cluster[{{ $branch }}][link_url]"
                                            value="{{ old("business_cluster.$branch.link_url", $link['link_url']) }}"
                                            class="form-control link-field"
                                            placeholder="https://docs.google.com/spreadsheets/d/..."
                                        >
                                    </td>
                                    <td class="text-center align-middle pr-4">
                                        @if($link['link_url'])
                                            <a href="{{ $link['link_url'] }}" target="_blank" rel="noopener" class="link-open-btn" title="Buka spreadsheet">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Submit Toolbar -->
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn link-action-btn">
                <i class="fas fa-save mr-2"></i>
                Simpan Perubahan Link
            </button>
        </div>
    </form>
</div>
@endsection

@section('styles')
<style>
    /* ==========================================================================
       BRI Nusantara - Modern Luxury Theme for Link Management
       ========================================================================== */

    .link-management-page {
        color: #0f172a;
    }

    /* 1. Hero Header */
    .link-hero {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 1.5rem 2rem;
        background: linear-gradient(135deg, #071d41 0%, #0857c3 55%, #0284c7 100%);
        color: #ffffff;
        box-shadow: 0 10px 25px -5px rgba(8, 87, 195, 0.25), 0 8px 10px -6px rgba(8, 87, 195, 0.2);
        background-image:
            radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
            linear-gradient(135deg, #071d41 0%, #0857c3 55%, #0284c7 100%);
        background-size: 20px 20px, 100% 100%;
    }
    .link-hero__glow {
        position: absolute;
        top: -50%;
        right: -10%;
        width: 380px;
        height: 380px;
        background: radial-gradient(circle, rgba(113, 197, 232, 0.2) 0%, rgba(8, 87, 195, 0) 70%);
        pointer-events: none;
    }
    .link-hero__eyebrow {
        display: inline-flex;
        align-items: center;
        margin-bottom: 0.5rem;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #e0f2fe;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .link-hero__title {
        color: #ffffff;
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 0.3rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }
    .link-hero__text {
        color: #e2e8f0;
        font-size: 0.9rem;
        max-width: 680px;
        line-height: 1.5;
    }
    .link-hero__badge {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1.1rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #ffffff;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    /* 2. Cards */
    .link-management-card {
        border-radius: 16px !important;
        overflow: hidden;
        border: 1px solid rgba(8, 87, 195, 0.1) !important;
        box-shadow: 0 10px 30px rgba(8, 87, 195, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03) !important;
        background: #ffffff;
    }

    .link-card-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
    .link-card-icon--primary {
        background: #eff6ff;
        color: #0857c3;
    }
    .link-card-icon--emerald {
        background: #ecfdf5;
        color: #059669;
    }
    .link-card-icon--amber {
        background: #fffbeb;
        color: #d97706;
    }
    .link-card-icon--indigo {
        background: #eef2ff;
        color: #4f46e5;
    }

    /* 3. Table */
    .link-management-table thead th {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 0.95rem 1.25rem;
        white-space: nowrap;
    }
    .link-management-table tbody td {
        padding: 0.85rem 1.25rem;
        border-top: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    /* 4. Form Controls */
    .link-field {
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        color: #0f172a;
        font-weight: 600;
        height: 38px;
        font-size: 0.86rem;
        transition: all 0.2s ease;
    }
    .link-field:focus {
        border-color: #0857c3 !important;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.15) !important;
    }

    /* 5. Buttons */
    .link-open-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        color: #0857c3;
        background: #ffffff;
        transition: all 0.15s ease;
    }
    .link-open-btn:hover {
        background: #eff6ff;
        border-color: #0857c3;
        color: #0857c3;
        transform: translateY(-1px);
    }

    .link-action-btn {
        border: 0;
        border-radius: 10px;
        padding: 0.7rem 1.5rem;
        background: linear-gradient(135deg, #0857c3 0%, #0284c7 100%);
        color: #ffffff;
        font-weight: 800;
        font-size: 0.92rem;
        box-shadow: 0 4px 14px rgba(8, 87, 195, 0.28);
        transition: all 0.2s ease;
    }
    .link-action-btn:hover {
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(8, 87, 195, 0.38);
    }

    @media (max-width: 900px) {
        .link-management-table {
            min-width: 800px;
        }
    }
</style>
@endsection
