@extends('layouts.admin')

@section('title', 'Link Management')

@section('content')
<style>
    .link-management-page {
        padding: 1.5rem;
    }

    .link-management-hero,
    .link-management-card {
        border: 1px solid #dbe7f3;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 18px 42px -30px rgba(15, 23, 42, 0.35);
    }

    .link-management-hero {
        padding: 1.35rem 1.5rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
    }

    .link-management-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .link-management-subtitle {
        margin: 0.3rem 0 0;
        color: #64748b;
        font-weight: 600;
        font-size: 0.88rem;
    }

    .link-management-card {
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .link-management-card-header {
        padding: 1rem 1.25rem;
        background: #0f5fb8;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .link-management-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
    }

    .link-management-table th,
    .link-management-table td {
        padding: 0.8rem;
        border-bottom: 1px solid #e6eef7;
        vertical-align: middle;
    }

    .link-management-table th {
        color: #475569;
        background: #f8fafc;
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        white-space: nowrap;
    }

    .link-field {
        border: 1px solid #cbd8e8;
        border-radius: 12px;
        color: #0f172a;
        font-weight: 600;
        min-height: 42px;
    }

    .link-field:focus {
        border-color: #0f5fb8;
        box-shadow: 0 0 0 0.18rem rgba(15, 95, 184, 0.12);
    }

    .link-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.28rem 0.62rem;
        background: #eaf3ff;
        color: #075aaf;
        font-weight: 800;
        font-size: 0.75rem;
        white-space: nowrap;
    }

    .link-action-btn {
        border: 0;
        border-radius: 12px;
        padding: 0.65rem 1rem;
        background: #0f5fb8;
        color: #fff;
        font-weight: 800;
        box-shadow: 0 14px 26px -18px rgba(15, 95, 184, 0.8);
    }

    .link-open-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 11px;
        border: 1px solid #cbd8e8;
        color: #0f5fb8;
        background: #fff;
    }

    @media (max-width: 900px) {
        .link-management-page {
            padding: 1rem;
        }

        .link-management-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .link-management-table {
            min-width: 900px;
        }

        .link-management-card {
            overflow-x: auto;
        }
    }
</style>

<div class="link-management-page">
    <div class="link-management-hero">
        <div>
            <h1 class="link-management-title">Link Management</h1>
            <p class="link-management-subtitle">Kelola link Google Sheet yang dipakai dashboard tanpa mengubah kode aplikasi.</p>
        </div>
        <span class="link-chip">
            <i class="fas fa-link"></i>
            Google Sheet Links
        </span>
    </div>

    @if(!$linkTableReady)
        <div class="alert alert-warning">
            Tabel <strong>external_report_links</strong> belum tersedia. Jalankan migration agar link dashboard dapat disimpan.
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
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

        <div class="link-management-card">
            <div class="link-management-card-header">
                <i class="fas fa-chart-line"></i>
                KPI Almafacts
            </div>
            <table class="link-management-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Dashboard</th>
                        <th style="width: 190px;">Nama Sheet</th>
                        <th>Link Spreadsheet</th>
                        <th style="width: 70px;">Buka</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($kpiLinks as $key => $link)
                        <tr>
                            <td>
                                <strong>{{ $link['label'] }}</strong>
                                <div class="text-muted small">{{ $link['spreadsheet_id'] }}</div>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="kpi[{{ $key }}][sheet_name]"
                                    value="{{ old("kpi.$key.sheet_name", $link['sheet_name']) }}"
                                    class="form-control link-field"
                                    required
                                >
                            </td>
                            <td>
                                <input
                                    type="url"
                                    name="kpi[{{ $key }}][link_url]"
                                    value="{{ old("kpi.$key.link_url", $link['link_url']) }}"
                                    class="form-control link-field"
                                    placeholder="https://docs.google.com/spreadsheets/d/..."
                                    required
                                >
                            </td>
                            <td>
                                <a href="{{ $link['link_url'] }}" target="_blank" rel="noopener" class="link-open-btn" title="Buka spreadsheet">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="link-management-card">
            <div class="link-management-card-header">
                <i class="fas fa-seedling"></i>
                SPPG
            </div>
            <table class="link-management-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Dashboard</th>
                        <th style="width: 190px;">Nama Sheet</th>
                        <th>Link Spreadsheet</th>
                        <th style="width: 70px;">Buka</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>{{ $sppgLink['label'] }}</strong>
                            <div class="text-muted small">{{ $sppgLink['spreadsheet_id'] ?: 'Google Sheet Area 6' }}</div>
                        </td>
                        <td>
                            <input
                                type="text"
                                name="sppg[sheet_name]"
                                value="{{ old('sppg.sheet_name', $sppgLink['sheet_name']) }}"
                                class="form-control link-field"
                                placeholder="Area 6"
                            >
                        </td>
                        <td>
                            <input
                                type="url"
                                name="sppg[link_url]"
                                value="{{ old('sppg.link_url', $sppgLink['link_url']) }}"
                                class="form-control link-field"
                                placeholder="https://docs.google.com/spreadsheets/d/..."
                            >
                        </td>
                        <td>
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

        <div class="link-management-card">
            <div class="link-management-card-header">
                <i class="fas fa-chart-pie"></i>
                Market Share
            </div>
            <table class="link-management-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Dashboard</th>
                        <th style="width: 190px;">Sheet Awal</th>
                        <th>Link Spreadsheet</th>
                        <th style="width: 70px;">Buka</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($marketShareLinks ?? []) as $key => $link)
                        <tr>
                            <td>
                                <strong>{{ $link['label'] }}</strong>
                                <div class="text-muted small">{{ $link['spreadsheet_id'] ?: 'Google Spreadsheet' }}</div>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="market_share[{{ $key }}][sheet_name]"
                                    value="{{ old("market_share.$key.sheet_name", $link['sheet_name']) }}"
                                    class="form-control link-field"
                                    required
                                >
                            </td>
                            <td>
                                <input
                                    type="url"
                                    name="market_share[{{ $key }}][link_url]"
                                    value="{{ old("market_share.$key.link_url", $link['link_url']) }}"
                                    class="form-control link-field"
                                    placeholder="https://docs.google.com/spreadsheets/d/..."
                                    required
                                >
                            </td>
                            <td>
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

        <div class="link-management-card">
            <div class="link-management-card-header">
                <i class="fas fa-building"></i>
                Business Cluster
            </div>
            @if(!$businessClusterTableReady)
                <div class="p-3 text-muted">Tabel business_cluster belum tersedia.</div>
            @else
                <table class="link-management-table">
                    <thead>
                        <tr>
                            <th style="width: 190px;">Kanca</th>
                            <th>Link Spreadsheet</th>
                            <th style="width: 70px;">Buka</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($businessClusterLinks as $branch => $link)
                            <tr>
                                <td><strong>{{ $link['label'] }}</strong></td>
                                <td>
                                    <input
                                        type="url"
                                        name="business_cluster[{{ $branch }}][link_url]"
                                        value="{{ old("business_cluster.$branch.link_url", $link['link_url']) }}"
                                        class="form-control link-field"
                                        placeholder="https://docs.google.com/spreadsheets/d/..."
                                    >
                                </td>
                                <td>
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
            @endif
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="link-action-btn">
                <i class="fas fa-save mr-1"></i>
                Simpan Link
            </button>
        </div>
    </form>
</div>
@endsection
