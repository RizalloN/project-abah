@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<style>
    .dashboard-hero {
        background: linear-gradient(135deg, #0f172a 0%, #164e63 48%, #0f766e 100%);
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
        position: relative;
        box-shadow: 0 20px 45px -25px rgba(15, 23, 42, 0.55);
    }

    .dashboard-hero::before,
    .dashboard-hero::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        opacity: 0.18;
        pointer-events: none;
    }

    .dashboard-hero::before {
        width: 280px;
        height: 280px;
        background: #67e8f9;
        top: -90px;
        right: -80px;
    }

    .dashboard-hero::after {
        width: 220px;
        height: 220px;
        background: #fbbf24;
        bottom: -110px;
        left: -70px;
    }

    .metric-card {
        border: 0;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 0 18px 35px -28px rgba(15, 23, 42, 0.45);
    }

    .metric-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .soft-panel {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 16px 34px -26px rgba(15, 23, 42, 0.35);
    }

    .progress-thin {
        height: 9px;
        border-radius: 999px;
        background-color: #e9ecef;
    }

    .progress-thin .progress-bar {
        border-radius: 999px;
    }

    .activity-item + .activity-item {
        border-top: 1px solid #eef2f7;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="dashboard-hero p-4 p-md-5 mb-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge badge-light px-3 py-2 text-uppercase" style="letter-spacing: 0.12em;">Area 6 Overview</span>
                    <h1 class="mt-3 mb-2 font-weight-bold" style="font-size: 2.35rem; line-height: 1.15;">
                        Dashboard Admin yang lebih rapi, modern, dan siap diisi data produksi.
                    </h1>
                    <p class="mb-4 text-white-50" style="max-width: 700px; font-size: 1rem;">
                        Tampilan dummy ini dirancang sebagai fondasi monitoring DigiBranch Area 6, sehingga saat data asli masuk nanti dashboard sudah terasa profesional, jelas, dan enak dibaca.
                    </p>
                    <div class="d-flex flex-wrap">
                        <div class="mr-4 mb-3">
                            <div class="text-white-50 text-uppercase small">User Aktif</div>
                            <div class="h3 mb-0 font-weight-bold">128</div>
                        </div>
                        <div class="mr-4 mb-3">
                            <div class="text-white-50 text-uppercase small">Report Monitoring</div>
                            <div class="h3 mb-0 font-weight-bold">6 Modul</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-white-50 text-uppercase small">Update Terakhir</div>
                            <div class="h3 mb-0 font-weight-bold">Hari Ini</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-4 mt-lg-0">
                    <div class="bg-white p-4" style="border-radius: 1rem; color: #0f172a;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-muted text-uppercase small">System Health</div>
                                <h4 class="font-weight-bold mb-0">Operasional Stabil</h4>
                            </div>
                            <span class="badge badge-success px-3 py-2">Healthy</span>
                        </div>
                        <div class="progress progress-thin mb-3">
                            <div class="progress-bar bg-success" style="width: 88%"></div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-muted small">EDC</div>
                                <div class="font-weight-bold">92%</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">QRIS</div>
                                <div class="font-weight-bold">84%</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">BRImo</div>
                                <div class="font-weight-bold">89%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="card metric-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted text-uppercase small mb-2">Total Cabang Terpantau</div>
                        <h3 class="font-weight-bold mb-1">47</h3>
                        <p class="mb-0 text-success small"><i class="fas fa-arrow-up mr-1"></i>Naik 8% dari minggu lalu</p>
                    </div>
                    <span class="metric-icon text-primary" style="background: rgba(13, 110, 253, 0.12);">
                        <i class="fas fa-building"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card metric-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted text-uppercase small mb-2">Produktivitas Harian</div>
                        <h3 class="font-weight-bold mb-1">81.4%</h3>
                        <p class="mb-0 text-primary small"><i class="fas fa-chart-line mr-1"></i>Kinerja di atas target dummy</p>
                    </div>
                    <span class="metric-icon text-info" style="background: rgba(23, 162, 184, 0.13);">
                        <i class="fas fa-signal"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card metric-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted text-uppercase small mb-2">Alert Perlu Follow Up</div>
                        <h3 class="font-weight-bold mb-1">12</h3>
                        <p class="mb-0 text-warning small"><i class="fas fa-exclamation-circle mr-1"></i>Prioritas menengah</p>
                    </div>
                    <span class="metric-icon text-warning" style="background: rgba(255, 193, 7, 0.16);">
                        <i class="fas fa-bell"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card metric-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted text-uppercase small mb-2">Progress Import Data</div>
                        <h3 class="font-weight-bold mb-1">94%</h3>
                        <p class="mb-0 text-success small"><i class="fas fa-check-circle mr-1"></i>Sinkronisasi berjalan baik</p>
                    </div>
                    <span class="metric-icon text-success" style="background: rgba(40, 167, 69, 0.14);">
                        <i class="fas fa-database"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="card-title font-weight-bold text-dark mb-1">Performa Channel Dummy</h3>
                        <p class="text-muted mb-0">Contoh ringkasan visual untuk modul monitoring utama.</p>
                    </div>
                    <span class="badge badge-light px-3 py-2">Updated 08:45 WIB</span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="font-weight-bold">EDC Performance</span>
                        <span class="text-muted">92%</span>
                    </div>
                    <div class="progress progress-thin">
                        <div class="progress-bar bg-primary" style="width: 92%"></div>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="font-weight-bold">QRIS Performance</span>
                        <span class="text-muted">84%</span>
                    </div>
                    <div class="progress progress-thin">
                        <div class="progress-bar bg-success" style="width: 84%"></div>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="font-weight-bold">BRImo Adoption</span>
                        <span class="text-muted">89%</span>
                    </div>
                    <div class="progress progress-thin">
                        <div class="progress-bar bg-info" style="width: 89%"></div>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="font-weight-bold">Brilink Coverage</span>
                        <span class="text-muted">76%</span>
                    </div>
                    <div class="progress progress-thin">
                        <div class="progress-bar bg-warning" style="width: 76%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Prioritas Hari Ini</h3>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="border rounded-lg p-3 h-100">
                            <span class="badge badge-primary mb-3">01</span>
                            <h5 class="font-weight-bold">Monitoring Data Masuk</h5>
                            <p class="text-muted mb-0">Pastikan file import harian sudah sinkron untuk seluruh cabang dummy.</p>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3 mt-md-0">
                        <div class="border rounded-lg p-3 h-100">
                            <span class="badge badge-warning mb-3">02</span>
                            <h5 class="font-weight-bold">Review Alert Anomali</h5>
                            <p class="text-muted mb-0">Tinjau indikator yang membutuhkan follow up cepat dari tim area.</p>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3 mt-md-0">
                        <div class="border rounded-lg p-3 h-100">
                            <span class="badge badge-success mb-3">03</span>
                            <h5 class="font-weight-bold">Prepare Weekly Summary</h5>
                            <p class="text-muted mb-0">Siapkan ringkasan performa mingguan untuk kebutuhan monitoring pimpinan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Aktivitas Terbaru</h3>
            </div>
            <div class="card-body px-0 py-0">
                <div class="activity-item px-4 py-3">
                    <div class="d-flex align-items-start">
                        <span class="badge badge-success mr-3 mt-1">&nbsp;</span>
                        <div>
                            <div class="font-weight-bold">Import data EDC selesai</div>
                            <div class="text-muted small">5 menit lalu</div>
                        </div>
                    </div>
                </div>
                <div class="activity-item px-4 py-3">
                    <div class="d-flex align-items-start">
                        <span class="badge badge-warning mr-3 mt-1">&nbsp;</span>
                        <div>
                            <div class="font-weight-bold">12 cabang perlu validasi QRIS</div>
                            <div class="text-muted small">20 menit lalu</div>
                        </div>
                    </div>
                </div>
                <div class="activity-item px-4 py-3">
                    <div class="d-flex align-items-start">
                        <span class="badge badge-primary mr-3 mt-1">&nbsp;</span>
                        <div>
                            <div class="font-weight-bold">Ringkasan BRImo diperbarui</div>
                            <div class="text-muted small">Hari ini, 08:10 WIB</div>
                        </div>
                    </div>
                </div>
                <div class="activity-item px-4 py-3">
                    <div class="d-flex align-items-start">
                        <span class="badge badge-info mr-3 mt-1">&nbsp;</span>
                        <div>
                            <div class="font-weight-bold">Dashboard dummy siap direview</div>
                            <div class="text-muted small">Hari ini, 07:45 WIB</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Agenda Tim</h3>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="font-weight-bold">Morning Brief</div>
                        <div class="text-muted small">09:00 - 09:30 WIB</div>
                    </div>
                    <span class="badge badge-light">Internal</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="font-weight-bold">Review Progress Import</div>
                        <div class="text-muted small">11:00 - 11:30 WIB</div>
                    </div>
                    <span class="badge badge-light">Ops</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="font-weight-bold">Weekly Highlight Draft</div>
                        <div class="text-muted small">15:30 - 16:00 WIB</div>
                    </div>
                    <span class="badge badge-light">Report</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
