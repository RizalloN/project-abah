@extends('layouts.admin')

@section('title', 'Kelola Job')

@section('content')
<div class="container-fluid pt-2 pb-4">
    <!-- Hero Header -->
    <div class="job-management-hero mb-4">
        <div class="job-management-hero__glow"></div>
        <div class="job-page-heading d-flex align-items-center justify-content-between flex-wrap position-relative">
            <div class="pr-3">
                <span class="job-management-hero__eyebrow"><i class="fas fa-microchip mr-1"></i> Queue &amp; Worker Control</span>
                <h2 class="job-management-hero__title h4 font-weight-bold text-white mb-0"><i class="fas fa-tasks mr-2"></i> Manajemen Job &amp; Antrian</h2>
                <p class="job-management-hero__text mb-0">Pemantauan realtime status antrean import, snapshot rebuild, dan worker execution.</p>
            </div>
            <div class="job-management-hero__badge mt-3 mt-md-0" id="worker-hero-badge">
                <i class="fas fa-circle text-secondary mr-2 status-pulse-dot"></i> Memeriksa Worker
            </div>
        </div>
    </div>

    @include('import.partials.control-center')

    <div class="cc-panel" id="cc-panel-operations" role="tabpanel" aria-labelledby="cc-tab-operations" hidden>
    <!-- Keep main wrapper and data attributes intact -->
    <div class="card border-0 shadow-sm job-management-main-card" id="job-management-card"
        data-fetch-url="{{ route('job-management.data') }}"
        data-worker-start-url="{{ route('job-management.worker.start') }}"
        data-worker-stop-url="{{ route('job-management.worker.stop') }}"
        data-worker-restart-url="{{ route('job-management.worker.restart') }}"
        data-worker-restart-cancel-url="{{ route('job-management.worker.restart.cancel') }}"
        data-clear-url="{{ route('job-management.clear') }}"
        data-bulk-delete-url="{{ route('job-management.bulk-destroy') }}"
        data-destroy-url-template="{{ route('job-management.destroy', ['jobId' => '__JOB_ID__']) }}"
        data-force-start-url-template="{{ route('job-management.force-start', ['jobId' => '__JOB_ID__']) }}"
        data-force-start-snapshot-url-template="{{ route('job-management.snapshot.force-start', ['rebuildId' => '__REBUILD_ID__']) }}"
        data-terminate-url-template="{{ route('job-management.terminate', ['jobId' => '__JOB_ID__']) }}"
        data-force-stop-delete-url-template="{{ route('import.report-management.delete.force-stop', ['deleteId' => '__DELETE_ID__']) }}"
        data-cancel-delete-url-template="{{ route('import.report-management.delete.cancel', ['deleteId' => '__DELETE_ID__']) }}"
        data-destroy-queue-job-url-template="{{ route('job-management.queue.destroy', ['queueJobId' => '__QUEUE_JOB_ID__']) }}"
        data-force-run-queue-job-url-template="{{ route('job-management.queue.force-run', ['queueJobId' => '__QUEUE_JOB_ID__']) }}"
        data-purge-queue-jobs-url="{{ route('job-management.queue.purge') }}">

        <!-- Toolbar & Stats -->
        <div class="card-header bg-white border-bottom py-3 px-4 job-management-toolbar">
            <div class="row align-items-center">
                <!-- Telemetry Stats Strip -->
                <div class="col-xl-4 col-lg-5 d-flex justify-content-between pr-lg-4 border-right job-summary-grid">
                    <div class="job-summary-item text-center">
                        <div class="job-summary-label">Aktif</div>
                        <div class="job-summary-val text-primary" id="summary-active">0</div>
                    </div>
                    <div class="job-summary-item text-center">
                        <div class="job-summary-label">Queued</div>
                        <div class="job-summary-val text-warning" id="summary-queued">0</div>
                    </div>
                    <div class="job-summary-item text-center">
                        <div class="job-summary-label">Processing</div>
                        <div class="job-summary-val text-success" id="summary-processing">0</div>
                    </div>
                    <div class="job-summary-item text-center">
                        <div class="job-summary-label">Hari Ini</div>
                        <div class="job-summary-val text-info" id="summary-today">0</div>
                    </div>
                </div>

                <!-- Filters & Action Panel -->
                <div class="col-xl-8 col-lg-7 pl-lg-4 job-filter-panel mt-3 mt-lg-0">
                    <div class="d-flex align-items-center justify-content-lg-end flex-wrap job-filter-controls" style="gap: 10px;">
                        <select id="job-filter-status" class="form-control form-control-sm custom-select job-status-select">
                            <option value="all">Semua Status</option>
                            <option value="queued">Queued</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="terminated">Terminated</option>
                            <option value="failed">Failed</option>
                            <option value="failed_partial">Gagal Sebagian</option>
                        </select>

                        <div class="input-group input-group-sm job-filter-search-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0 text-muted"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="job-filter-search" class="form-control border-left-0 pl-0" placeholder="Cari job...">
                        </div>
                        
                        <div class="job-filter-switch-wrap d-inline-flex align-items-center">
                            <div class="custom-control custom-switch d-inline-flex align-items-center">
                                <input type="checkbox" class="custom-control-input" id="job-filter-active-only">
                                <label class="custom-control-label font-weight-bold text-muted" for="job-filter-active-only">Aktif</label>
                            </div>
                            <div class="custom-control custom-switch d-inline-flex align-items-center">
                                <input type="checkbox" class="custom-control-input" id="job-auto-refresh" checked>
                                <label class="custom-control-label font-weight-bold text-muted" for="job-auto-refresh">Auto</label>
                            </div>
                        </div>

                        <div class="job-action-btn-group d-inline-flex align-items-center" style="gap: 6px;">
                            <button type="button" id="btn-job-refresh" class="btn btn-sm job-btn-primary" title="Refresh data"><i class="fas fa-sync-alt"></i></button>
                            <button type="button" id="btn-job-clear" class="btn btn-sm job-btn-outline-danger" title="Bersihkan riwayat job"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4 bg-light">
            <div id="job-management-notice" class="alert alert-info d-none mb-4 shadow-sm border-0" style="border-radius: 10px;"></div>
            <div id="job-management-queue-health" class="alert alert-warning d-none mb-4 shadow-sm border-0" style="border-radius: 10px;"></div>

            <section class="server-runtime mb-4" id="server-runtime-card" aria-labelledby="server-runtime-title"
                data-status-url="{{ route('job-management.runtime.status') }}"
                data-control-url="{{ route('job-management.runtime.control') }}">
                <div class="server-runtime__header">
                    <div>
                        <h3 class="server-runtime__title" id="server-runtime-title"><i class="fas fa-server mr-2" aria-hidden="true"></i>Layanan Server</h3>
                        <p class="server-runtime__intro">Aktifkan layanan pendukung dan pantau scheduler otomatis dari satu tempat.</p>
                    </div>
                    <div class="server-runtime__header-actions">
                        <span class="server-runtime__state server-runtime__state--secondary" id="runtime-health-badge" role="status">Memeriksa layanan</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-runtime-refresh" aria-label="Perbarui status layanan server"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-sm job-btn-primary" data-runtime-service="all" data-runtime-action="start" disabled><i class="fas fa-play mr-1" aria-hidden="true"></i>Aktifkan Semua Pendukung</button>
                    </div>
                </div>
                <p class="server-runtime__access-note"><i class="fas fa-info-circle mr-1" aria-hidden="true"></i>Apache, MySQL, dan Cloudflare membuat situs ini dapat diakses. Status startup otomatis Windows ditampilkan pada tiap layanan. Setelah halaman terbuka, scheduler dan worker dapat dikelola dari sini.</p>
                <div class="server-runtime__notice d-none" id="runtime-notice" role="status" aria-live="polite" aria-atomic="true"></div>

                <div class="server-runtime__grid">
                    <div class="server-runtime__scheduler">
                        <div class="server-runtime__section-head">
                            <h4 class="server-runtime__section-title">Scheduler Laravel</h4>
                            <span class="server-runtime__state server-runtime__state--secondary" id="runtime-scheduler-state" role="status">Memeriksa</span>
                        </div>
                        <p class="server-runtime__message" id="runtime-scheduler-message">Menunggu status scheduler dari server.</p>
                        <dl class="server-runtime__metrics">
                            <div><dt>Heartbeat terakhir</dt><dd id="runtime-scheduler-heartbeat">—</dd></div>
                            <div><dt>Eksekusi terakhir</dt><dd id="runtime-scheduler-last-run">—</dd></div>
                            <div><dt>Jadwal berikutnya</dt><dd id="runtime-scheduler-next-run">—</dd></div>
                            <div><dt>Startup scheduler</dt><dd id="runtime-scheduler-startup">Memeriksa</dd></div>
                        </dl>
                        <div class="server-runtime__scheduler-actions">
                            <button type="button" class="btn btn-sm btn-success" data-runtime-service="scheduler" data-runtime-action="start" disabled><i class="fas fa-play mr-1" aria-hidden="true"></i>Aktifkan Scheduler</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-runtime-service="scheduler" data-runtime-action="run" disabled><i class="fas fa-calendar-check mr-1" aria-hidden="true"></i>Jalankan Jadwal Jatuh Tempo</button>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-runtime-service="scheduler" data-runtime-action="restart" disabled><i class="fas fa-redo-alt mr-1" aria-hidden="true"></i>Restart Scheduler</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-runtime-service="scheduler" data-runtime-action="stop" disabled><i class="fas fa-stop mr-1" aria-hidden="true"></i>Matikan Scheduler</button>
                        </div>
                        <p class="server-runtime__hint mt-2 mb-0">Scheduler menjalankan jadwal yang jatuh tempo secara otomatis. Kontrol worker dan antreannya tetap tersedia di bawah.</p>
                    </div>

                    <div class="server-runtime__infrastructure" aria-label="Layanan infrastruktur Windows">
                        @foreach(['apache' => ['Apache', 'fa-globe'], 'mysql' => ['MySQL', 'fa-database'], 'tunnel' => ['Cloudflare Tunnel', 'fa-cloud']] as $serviceKey => [$serviceLabel, $serviceIcon])
                            <div class="server-runtime__service" data-runtime-infrastructure="{{ $serviceKey }}">
                                <i class="fas {{ $serviceIcon }} server-runtime__service-icon" aria-hidden="true"></i>
                                <div class="server-runtime__service-copy">
                                    <div class="server-runtime__section-head"><strong data-runtime-service-label>{{ $serviceLabel }}</strong><span class="server-runtime__state server-runtime__state--secondary" data-runtime-service-state>Memeriksa</span></div>
                                    <div class="server-runtime__hint" data-runtime-service-startup>Memeriksa startup Windows</div>
                                    <div class="server-runtime__hint mt-1" data-runtime-service-message>Menunggu informasi layanan.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-runtime-service="{{ $serviceKey }}" data-runtime-action="start" aria-label="Aktifkan {{ $serviceLabel }}" disabled>Aktifkan</button>
                            </div>
                        @endforeach
                        <div class="server-runtime__service" id="runtime-backup-row">
                            <i class="fas fa-archive server-runtime__service-icon" aria-hidden="true"></i>
                            <div class="server-runtime__service-copy">
                                <div class="server-runtime__section-head"><strong>Backup database harian</strong><span class="server-runtime__state server-runtime__state--secondary" id="runtime-backup-state">Memeriksa</span></div>
                                <div class="server-runtime__hint" id="runtime-backup-schedule">Memeriksa task backup Windows</div>
                                <div class="server-runtime__hint mt-1" id="runtime-backup-message">Menunggu informasi backup.</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-runtime-service="backup" data-runtime-action="run" aria-label="Jalankan backup database sekarang" disabled>Jalankan</button>
                        </div>
                    </div>
                </div>

                <div class="server-runtime__readiness" aria-labelledby="runtime-readiness-title">
                    <div class="server-runtime__section-head">
                        <h4 class="server-runtime__section-title" id="runtime-readiness-title">Kesiapan setelah server restart</h4>
                        <span class="server-runtime__state server-runtime__state--secondary" id="runtime-readiness-badge" role="status">Memeriksa</span>
                    </div>
                    <p class="server-runtime__hint mb-2">Semua butir berikut harus terpenuhi agar aplikasi menyala sendiri setelah PC server dinyalakan ulang. Bila ada yang belum, klik <strong>Aktifkan Semua Pendukung</strong>.</p>
                    <ul class="server-runtime__checklist" id="runtime-readiness-list"><li class="server-runtime__check">Memeriksa kesiapan…</li></ul>
                </div>

                <div class="server-runtime__details-grid">
                    <details class="server-runtime__details" id="runtime-schedules-details">
                        <summary>Jadwal otomatis <span id="runtime-schedule-count"></span></summary>
                        <p class="server-runtime__hint">Daftar ini untuk pemantauan. Setiap jadwal berjalan melalui scheduler sesuai waktu yang ditetapkan.</p>
                        <div class="server-runtime__schedule-wrap" data-abah-no-table-guard tabindex="0" role="region" aria-label="Daftar jadwal otomatis">
                            <table class="table table-sm server-runtime__schedule-table" data-abah-no-table-guard>
                                <thead><tr><th scope="col">Jadwal</th><th scope="col">Frekuensi</th><th scope="col">Eksekusi berikutnya</th></tr></thead>
                                <tbody id="runtime-schedule-body"><tr><td colspan="3">Memuat jadwal otomatis…</td></tr></tbody>
                            </table>
                        </div>
                    </details>
                    <details class="server-runtime__details">
                        <summary>Log scheduler terbaru</summary>
                        <p class="server-runtime__hint" id="runtime-scheduler-task-detail">Memeriksa tugas Windows scheduler.</p>
                        <pre class="server-runtime__log" id="runtime-scheduler-log" tabindex="0" aria-label="Log scheduler terbaru">Menunggu log scheduler.</pre>
                    </details>
                </div>
                <div class="server-runtime__footer"><span id="runtime-updated">Status layanan belum dimuat.</span><span id="runtime-refresh-mode">Pembaruan mengikuti sakelar Auto.</span></div>
            </section>

            <div class="card border-0 shadow-sm mb-4" id="worker-control-card" style="border-radius: 14px;">
                <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between">
                    <div class="pr-lg-4 mb-3 mb-lg-0">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge badge-secondary mr-2" id="worker-status-badge">Memeriksa</span>
                            <strong id="worker-status-label">Status worker sedang diperiksa</strong>
                        </div>
                        <div class="text-muted small" id="worker-status-message">Menunggu heartbeat monitor dari server.</div>
                        <code class="d-block mt-2 small" id="worker-command">php artisan queue:ensure-running --timeout=0 --memory=512 --max-jobs=25 --max-time=3600 --check-interval=30</code>
                    </div>
                    <div class="d-flex flex-wrap" style="gap: 8px;">
                        <button type="button" class="btn btn-sm btn-success" id="btn-worker-start"><i class="fas fa-play mr-1"></i>Aktifkan Worker</button>
                        <button type="button" class="btn btn-sm btn-warning" id="btn-worker-restart" title="Restart worker setelah semua import, snapshot, dan job selesai"><i class="fas fa-redo-alt mr-1"></i>Restart Aman</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-worker-stop"><i class="fas fa-stop mr-1"></i>Matikan Worker</button>
                    </div>
                </div>
                <div class="worker-monitor border-top" id="worker-monitor">
                    <div class="worker-restart-banner d-none" id="worker-safe-restart-banner"></div>
                    <div class="worker-restart-banner d-none" id="worker-restart-banner"></div>
                    <div class="worker-metrics" id="worker-metrics">
                        <div class="worker-metric"><div class="worker-metric__label">Monitor</div><div class="worker-metric__value">-</div></div>
                    </div>
                    <div class="worker-monitor__section-title">Pool Worker</div>
                    <div class="raw-queue-table-wrap">
                        <table class="table mb-0 job-management-table worker-pool-table">
                            <thead>
                                <tr class="job-management-table__header">
                                    <th>Pool</th><th>Antrean</th><th class="text-center">Worker</th><th class="text-center">Siap</th><th class="text-center">Diproses</th><th class="text-center">Tertunda</th><th>Tunggu Tertua</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="worker-pool-body"><tr><td colspan="8" class="text-center text-muted py-3">Memuat telemetri worker...</td></tr></tbody>
                        </table>
                    </div>
                    <div class="worker-monitor__section-title">Job Gagal Terbaru</div>
                    <div id="worker-failed-jobs" class="worker-failed-jobs"><div class="text-muted small">Memuat...</div></div>
                </div>
            </div>

            <!-- Active & Snapshot Grids -->
            <div class="row">
                <!-- Import Jobs -->
                <div class="col-12 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold mb-0 text-dark d-flex align-items-center" style="font-size: 0.95rem;">
                            <span class="job-section-icon mr-2 text-success"><i class="fas fa-hourglass-half"></i></span>
                            Import Jobs Aktif
                        </h6>
                        <span class="badge badge-success badge-pill px-3 py-1 font-weight-bold" id="active-job-count-label">0</span>
                    </div>
                    <div id="active-jobs-grid" class="row"></div>
                </div>
            </div>
            
            <div class="row">
                <!-- Snapshot Jobs -->
                <div class="col-lg-6 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold mb-0 text-dark d-flex align-items-center" style="font-size: 0.95rem;">
                            <span class="job-section-icon mr-2 text-primary"><i class="fas fa-clone"></i></span>
                            Snapshot Jobs Aktif
                        </h6>
                        <span class="badge badge-primary badge-pill px-3 py-1 font-weight-bold" id="snapshot-job-count-label">0</span>
                    </div>
                    <div id="snapshot-jobs-grid" class="row"></div>
                </div>

                <!-- Managed Delete Jobs -->
                <div class="col-lg-6 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold mb-0 text-dark d-flex align-items-center" style="font-size: 0.95rem;">
                            <span class="job-section-icon mr-2 text-danger"><i class="fas fa-trash-alt"></i></span>
                            Delete Jobs Aktif
                        </h6>
                        <span class="badge badge-danger badge-pill px-3 py-1 font-weight-bold" id="managed-delete-job-count-label">0</span>
                    </div>
                    <div id="managed-delete-jobs-grid" class="row"></div>
                </div>
            </div>

            <!-- Raw Queue -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold mb-0 text-dark d-flex align-items-center" style="font-size: 0.95rem;">
                        <span class="job-section-icon mr-2 text-warning"><i class="fas fa-database"></i></span>
                        Antrian Raw Database
                    </h6>
                    <div>
                        <span class="badge badge-warning badge-pill px-3 py-1 mr-2 font-weight-bold" id="raw-queue-job-count-label">0</span>
                        <button type="button" id="btn-purge-queue-jobs" class="btn btn-sm py-1 btn-outline-danger" style="border-radius: 8px;"><i class="fas fa-broom mr-1"></i>Purge Pending</button>
                    </div>
                </div>
                <div id="raw-queue-jobs-grid" class="row"></div>
            </div>

            <!-- History Table -->
            <div class="card border-0 shadow-sm job-history-card" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap" style="border-bottom: 1px solid #e2e8f0;">
                    <h6 class="font-weight-bold mb-0 text-dark d-flex align-items-center" style="font-size: 0.95rem;">
                        <span class="job-section-icon mr-2 text-secondary"><i class="fas fa-history"></i></span>
                        Riwayat Eksekusi Job
                    </h6>
                    <div class="job-history-tabs" role="tablist" aria-label="Jenis riwayat job">
                        <button type="button" class="job-history-tab is-active" role="tab" aria-selected="true" data-history-view="import">
                            <i class="fas fa-file-import" aria-hidden="true"></i> Import <span class="job-history-tab__count" id="history-import-count">0</span>
                        </button>
                        <button type="button" class="job-history-tab" role="tab" aria-selected="false" data-history-view="delete">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i> Delete Report <span class="job-history-tab__count" id="history-delete-count">0</span>
                        </button>
                    </div>
                    <div class="d-flex align-items-center" id="job-history-import-tools">
                        <span class="text-muted small mr-3 font-weight-bold" id="job-pagination-meta">0 job</span>
                        <div class="custom-control custom-checkbox d-inline-block">
                            <input type="checkbox" class="custom-control-input" id="job-select-all">
                            <label class="custom-control-label small text-muted font-weight-bold mt-1" style="cursor: pointer;" for="job-select-all">Pilih Semua</label>
                        </div>
                        <span class="badge badge-light border mx-3 px-2 py-1 font-weight-bold" id="job-selected-count">0 terpilih</span>
                        <button type="button" id="btn-job-delete-selected" class="btn btn-sm job-btn-outline-danger" style="border-radius: 8px;" disabled><i class="fas fa-trash-alt mr-1"></i> Hapus Terpilih</button>
                    </div>
                </div>
                <div class="table-responsive bg-white">
                    <table class="table table-hover mb-0 job-management-table" style="font-size: 0.88rem;">
                        <thead>
                            <tr>
                                <th class="text-center border-top-0 border-bottom-0" style="width: 50px;"><i class="far fa-check-square"></i></th>
                                <th class="border-top-0 border-bottom-0">ID</th>
                                <th class="border-top-0 border-bottom-0">Report</th>
                                <th class="border-top-0 border-bottom-0">File</th>
                                <th class="border-top-0 border-bottom-0">Status</th>
                                <th class="border-top-0 border-bottom-0" style="width: 220px;">Progress</th>
                                <th class="border-top-0 border-bottom-0">Updated</th>
                                <th class="text-center border-top-0 border-bottom-0">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="job-table-body">
                            <tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="job-pagination" class="card-footer bg-white d-none py-3 px-4" style="border-top: 1px solid #e2e8f0;"></div>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const card = document.getElementById('job-management-card');
    const filterStatus = document.getElementById('job-filter-status');
    const filterSearch = document.getElementById('job-filter-search');
    const btnRefresh = document.getElementById('btn-job-refresh');
    const btnClear = document.getElementById('btn-job-clear');
    const btnDeleteSelected = document.getElementById('btn-job-delete-selected');
    const autoRefresh = document.getElementById('job-auto-refresh');
    const activeOnly = document.getElementById('job-filter-active-only');
    const selectAll = document.getElementById('job-select-all');
    const selectedCount = document.getElementById('job-selected-count');
    const snapshotGrid = document.getElementById('snapshot-jobs-grid');
    const snapshotJobCountLabel = document.getElementById('snapshot-job-count-label');
    const activeGrid = document.getElementById('active-jobs-grid');
    const rawQueueGrid = document.getElementById('raw-queue-jobs-grid');
    const rawQueueJobCountLabel = document.getElementById('raw-queue-job-count-label');
    const btnPurgeQueueJobs = document.getElementById('btn-purge-queue-jobs');
    const managedDeleteGrid = document.getElementById('managed-delete-jobs-grid');
    const managedDeleteJobCountLabel = document.getElementById('managed-delete-job-count-label');
    const tableBody = document.getElementById('job-table-body');
    const pagination = document.getElementById('job-pagination');
    const paginationMeta = document.getElementById('job-pagination-meta');
    const activeJobCountLabel = document.getElementById('active-job-count-label');
    const notice = document.getElementById('job-management-notice');
    const queueHealth = document.getElementById('job-management-queue-health');
    const workerHeroBadge = document.getElementById('worker-hero-badge');
    const workerStatusBadge = document.getElementById('worker-status-badge');
    const workerStatusLabel = document.getElementById('worker-status-label');
    const workerStatusMessage = document.getElementById('worker-status-message');
    const workerCommand = document.getElementById('worker-command');
    const btnWorkerStart = document.getElementById('btn-worker-start');
    const btnWorkerStop = document.getElementById('btn-worker-stop');
    const btnWorkerRestart = document.getElementById('btn-worker-restart');
    const workerRestartBanner = document.getElementById('worker-restart-banner');
    const workerMetrics = document.getElementById('worker-metrics');
    const workerPoolBody = document.getElementById('worker-pool-body');
    const workerFailedJobs = document.getElementById('worker-failed-jobs');
    let workerRestartPollTimers = [];
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    let currentPage = 1;
    let refreshTimer = null;
    let searchTimer = null;
    let loading = false;
    let refreshRequestedWhileLoading = false;
    let currentJobs = [];
    const selectedJobIds = new Set();
    const historyTabs = Array.from(document.querySelectorAll('[data-history-view]'));
    const historyImportTools = document.getElementById('job-history-import-tools');
    const historyImportCount = document.getElementById('history-import-count');
    const historyDeleteCount = document.getElementById('history-delete-count');
    let historyView = 'import';
    let deleteHistory = [];
    let lastPaginationMeta = {};

    function templateUrl(template, value) { return String(template || '').replace('__JOB_ID__', encodeURIComponent(value || '')); }
    function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
    function showNotice(message, tone = 'info') { notice.className = `job-management-notice job-management-notice--${tone}`; notice.textContent = message; notice.classList.remove('d-none'); }
    function hideNotice() { notice.classList.add('d-none'); notice.textContent = ''; }
    function renderQueueHealth(payload) { if (!payload || !payload.message) { queueHealth.classList.add('d-none'); queueHealth.textContent = ''; return; } queueHealth.className = `job-management-notice job-management-notice--${escapeHtml(payload.tone || 'info')}`; queueHealth.textContent = payload.message; queueHealth.classList.remove('d-none'); if ((payload.status || '') === 'ok') { queueHealth.classList.add('job-management-notice--subtle'); } else { queueHealth.classList.remove('job-management-notice--subtle'); } }
    function renderWorkerStatus(payload) {
        if (!payload) return;
        const tone = ['success', 'info', 'warning', 'secondary', 'danger'].includes(payload.tone) ? payload.tone : 'secondary';
        const active = payload.state === 'active';
        workerStatusBadge.className = `badge badge-${tone} mr-2`;
        workerStatusBadge.textContent = payload.state_label || 'Status tidak diketahui';
        const workerCount = Number(payload.worker_count || 0);
        workerStatusLabel.textContent = active
            ? (workerCount > 0
                ? `${workerCount.toLocaleString('id-ID')} worker sedang berjalan`
                : 'Monitor berjalan • antrean sedang idle')
            : `${workerCount.toLocaleString('id-ID')} worker terdeteksi`;
        workerStatusMessage.textContent = payload.message || '-';
        workerCommand.textContent = payload.command || '';
        workerHeroBadge.innerHTML = `<i class="fas fa-circle text-${tone} mr-2 status-pulse-dot"></i>${escapeHtml(payload.state_label || 'Status Worker')}`;
        btnWorkerStart.disabled = active || ['starting', 'stopping', 'restarting'].includes(payload.state);
        btnWorkerStop.disabled = payload.state === 'stopped' || payload.state === 'stopping';
        btnWorkerRestart.disabled = !payload.enabled || ['stopping', 'restarting'].includes(payload.state);
    }
    function formatAge(seconds) {
        if (seconds === null || seconds === undefined || Number.isNaN(Number(seconds))) return '-';
        const value = Math.max(0, Math.round(Number(seconds)));
        if (value < 60) return `${value} dtk`;
        if (value < 3600) return `${Math.floor(value / 60)} mnt`;
        const hours = Math.floor(value / 3600);
        const minutes = Math.floor((value % 3600) / 60);
        return minutes > 0 ? `${hours} j ${minutes} mnt` : `${hours} j`;
    }
    function formatClock(iso) { if (!iso) return '-'; const date = new Date(iso); return Number.isNaN(date.getTime()) ? '-' : date.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' }); }
    function workerMetric(label, value, hint = '', tone = '') { return `<div class="worker-metric${tone ? ` worker-metric--${tone}` : ''}"><div class="worker-metric__label">${escapeHtml(label)}</div><div class="worker-metric__value">${value}</div>${hint ? `<div class="worker-metric__hint">${hint}</div>` : ''}</div>`; }
    function workerChip(worker) {
        const busy = worker.activity === 'busy';
        const title = `PID ${worker.pid} • mulai ${formatClock(worker.started_at)} • heartbeat ${formatAge(worker.last_heartbeat_age_seconds)} lalu`;
        return `<span class="worker-chip ${busy ? 'worker-chip--busy' : ''} ${worker.outdated ? 'worker-chip--outdated' : ''}" title="${escapeHtml(title)}">`
            + `<i class="fas ${busy ? 'fa-cog fa-spin' : 'fa-pause-circle'} mr-1"></i>PID ${escapeHtml(worker.pid)}`
            + ` · ${busy ? 'memproses' : 'idle'} · ${formatAge(worker.uptime_seconds)}`
            + (worker.memory_mb !== null && worker.memory_mb !== undefined ? ` · ${Number(worker.memory_mb).toLocaleString('id-ID')} MB` : '')
            + (worker.outdated ? ' · <strong>kode lama</strong>' : '')
            + '</span>';
    }
    function renderWorkerMonitor(payload) {
        if (!payload) {
            workerMetrics.innerHTML = workerMetric('Telemetri', 'Tidak tersedia', 'Periksa log server.', 'warning');
            workerPoolBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Telemetri worker belum dapat dibaca.</td></tr>';
            workerFailedJobs.innerHTML = '';
            workerRestartBanner.classList.add('d-none');
            return;
        }
        const monitor = payload.monitor || {};
        const totals = payload.totals || {};
        const failed = payload.failed_jobs || {};
        const heartbeatAge = monitor.heartbeat_age_seconds;
        const heartbeatTone = heartbeatAge === null || heartbeatAge === undefined ? 'danger' : (heartbeatAge > 60 ? 'warning' : '');
        const recovery = monitor.recovery || {};
        const recoveryHint = recovery.state === 'backoff' && recovery.next_retry_at
            ? `Pemulihan otomatis ditunda (gagal ${Number(recovery.consecutive_failures || 0)}×).`
            : '';
        workerMetrics.innerHTML = [
            workerMetric('Monitor', monitor.pid ? `PID ${escapeHtml(monitor.pid)}` : 'Tidak aktif', monitor.started_at ? `Aktif ${formatAge(monitor.uptime_seconds)}${monitor.memory_mb ? ` · ${Number(monitor.memory_mb).toLocaleString('id-ID')} MB` : ''}` : recoveryHint, monitor.active ? '' : 'danger'),
            workerMetric('Heartbeat', heartbeatAge === null || heartbeatAge === undefined ? '-' : `${formatAge(heartbeatAge)} lalu`, 'Normal ≤ 60 dtk', heartbeatTone),
            workerMetric('Worker Aktif', `${Number(totals.live_workers || 0)} / ${Number(totals.capacity || 0)}`, `${Number(totals.busy_workers || 0)} sedang memproses job`),
            workerMetric('Job Siap', Number(totals.ready_jobs || 0).toLocaleString('id-ID'), `${Number(totals.reserved_jobs || 0)} diproses · ${Number(totals.delayed_jobs || 0)} tertunda`, Number(totals.stalled_pools || 0) > 0 ? 'danger' : ''),
            workerMetric('Versi Kode', Number(totals.outdated_workers || 0) > 0 || monitor.outdated ? 'Perlu restart' : 'Terbaru', Number(totals.outdated_workers || 0) > 0 ? `${Number(totals.outdated_workers)} worker memakai kode lama` : 'Semua proses dimulai setelah restart terakhir', Number(totals.outdated_workers || 0) > 0 || monitor.outdated ? 'warning' : ''),
            workerMetric('Job Gagal 24 Jam', failed.available === false ? '-' : `${Number(failed.last_24h || 0)}${failed.last_24h_capped ? '+' : ''}`, `${Number(failed.last_hour || 0)} dalam 1 jam terakhir`, Number(failed.last_hour || 0) > 0 ? 'danger' : ''),
        ].join('');
        if (payload.process_details_available === false) {
            workerMetrics.insertAdjacentHTML('beforeend', workerMetric('Detail Proses', 'Terbatas', 'Daftar proses OS tidak terbaca; status memakai heartbeat.', 'warning'));
        }

        const restart = payload.restart || {};
        if (restart.state && restart.state !== 'idle' && restart.message) {
            const tone = restart.state === 'completed' ? 'success' : (restart.state === 'waiting_long_job' ? 'warning' : 'info');
            const icon = restart.state === 'completed' ? 'fa-check-circle' : 'fa-sync-alt fa-spin';
            const pending = (restart.pending_workers || []).length ? ` <span class="text-muted">(PID ${restart.pending_workers.map((pid) => escapeHtml(pid)).join(', ')})</span>` : '';
            workerRestartBanner.className = `worker-restart-banner worker-restart-banner--${tone}`;
            workerRestartBanner.innerHTML = `<i class="fas ${icon} mr-2"></i>${escapeHtml(restart.message)}${pending}<div class="worker-restart-banner__meta">Diminta ${formatClock(restart.requested_at)}${restart.requested_by ? ` oleh ${escapeHtml(restart.requested_by)}` : ''}</div>`;
        } else {
            workerRestartBanner.classList.add('d-none');
        }

        const pools = payload.pools || [];
        workerPoolBody.innerHTML = pools.length === 0
            ? '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada pool worker yang dikonfigurasi.</td></tr>'
            : pools.map((pool) => {
                const running = (pool.running_jobs || []).map((job) => `<span class="worker-running-job"><i class="fas fa-play-circle mr-1"></i>${escapeHtml(job.job)} · ${formatAge(job.running_seconds)}${Number(job.attempts || 0) > 1 ? ` · percobaan ${Number(job.attempts)}` : ''}</span>`).join('');
                const workers = (pool.workers || []).map(workerChip).join('');
                const backoff = pool.backoff ? `<div class="job-table-secondary">Coba lagi ${formatClock(pool.backoff.next_retry_at)} (gagal ${Number(pool.backoff.failures || 0)}×)</div>` : '';
                const staleReserved = Number(pool.stale_reserved_jobs || 0) > 0 ? `<div class="job-table-secondary text-danger">${Number(pool.stale_reserved_jobs)} reservasi melewati batas retry</div>` : '';
                const detail = workers || running ? `<tr class="worker-pool-detail"><td colspan="8">${workers}${running}</td></tr>` : '';
                return `<tr><td class="font-weight-bold">${escapeHtml(pool.name)}</td>`
                    + `<td>${(pool.queues || []).map((queue) => `<span class="badge badge-light border mr-1">${escapeHtml(queue)}</span>`).join('')}</td>`
                    + `<td class="text-center font-weight-bold">${Number(pool.live_workers || 0)} / ${Number(pool.capacity || 0)}</td>`
                    + `<td class="text-center">${Number(pool.ready_jobs || 0).toLocaleString('id-ID')}</td>`
                    + `<td class="text-center">${Number(pool.reserved_jobs || 0).toLocaleString('id-ID')}</td>`
                    + `<td class="text-center">${Number(pool.delayed_jobs || 0).toLocaleString('id-ID')}</td>`
                    + `<td>${pool.oldest_ready_age_seconds === null || pool.oldest_ready_age_seconds === undefined ? '-' : formatAge(pool.oldest_ready_age_seconds)}</td>`
                    + `<td><span class="job-status-badge job-status-badge--${escapeHtml(pool.tone || 'muted')}">${escapeHtml(pool.state_label || pool.state)}</span>${backoff}${staleReserved}</td></tr>${detail}`;
            }).join('');

        const groups = failed.groups || [];
        const windowDays = Number(failed.window_days || 7);
        workerFailedJobs.innerHTML = groups.length === 0
            ? `<div class="text-muted small">Tidak ada job gagal dalam ${windowDays} hari terakhir.${failed.last_failed_at ? ` Kegagalan terakhir: ${formatClock(failed.last_failed_at)}.` : ''}</div>`
            : groups.map((group) => `<div class="worker-failed-job"><div class="worker-failed-job__head"><strong>${escapeHtml(group.job)}</strong><span class="badge badge-light border ml-2">${escapeHtml(group.queue)}</span><span class="badge badge-danger ml-2">${Number(group.count || 0)}×</span><span class="ml-auto text-muted">terakhir ${formatClock(group.last_failed_at)}</span></div><div class="worker-failed-job__error">${escapeHtml(group.error || '-')}</div></div>`).join('');
    }
    const workerSafeRestartBanner = document.getElementById('worker-safe-restart-banner');
    function renderSafeRestart(payload) {
        if (!payload) { workerSafeRestartBanner.classList.add('d-none'); return; }
        if (payload.code_outdated) {
            const codeMetric = Array.from(workerMetrics.querySelectorAll('.worker-metric')).find((metric) => metric.querySelector('.worker-metric__label')?.textContent === 'Versi Kode');
            if (codeMetric) codeMetric.outerHTML = workerMetric('Versi Kode', 'Kode baru tersedia', `Diubah ${formatClock(payload.code_changed_at)}`, 'warning');
        }
        const pending = payload.pending;
        const busy = payload.busy || [];
        const busyList = busy.length ? `<ul class="worker-busy-list">${busy.map((item) => `<li>${escapeHtml(item.label)}</li>`).join('')}</ul>` : '';
        if (pending && (pending.blocked_by || []).length && !busy.length) {
            workerSafeRestartBanner.className = 'worker-restart-banner worker-restart-banner--warning';
            workerSafeRestartBanner.innerHTML = `<div class="d-flex align-items-start flex-wrap" style="gap:8px;"><div class="flex-grow-1"><i class="fas fa-exclamation-triangle mr-2"></i>Restart ditahan: ada file PHP yang tidak valid. Worker tetap berjalan dengan kode lama sampai file diperbaiki.<ul class="worker-busy-list">${pending.blocked_by.map((error) => `<li>${escapeHtml(error)}</li>`).join('')}</ul></div><button type="button" class="btn btn-sm btn-outline-secondary" data-action="cancel-worker-restart"><i class="fas fa-times mr-1"></i>Batalkan</button></div>`;
            return;
        }
        if (pending) {
            const who = pending.reason === 'code_change' ? 'otomatis karena kode aplikasi berubah' : (pending.requested_by ? `oleh ${escapeHtml(pending.requested_by)}` : 'manual');
            const headline = busy.length
                ? `Restart dijadwalkan ${who}. Menunggu ${busy.length} proses selesai agar import dan snapshot tidak terganggu:`
                : (pending.idle_since
                    ? `Semua proses sudah selesai. Restart dijalankan setelah sistem tenang ±${Number(payload.quiet_seconds || 60)} detik.`
                    : 'Semua proses sudah selesai. Restart dijalankan pada pemeriksaan watchdog berikutnya (≤ 1 menit).');
            workerSafeRestartBanner.className = `worker-restart-banner worker-restart-banner--${busy.length ? 'warning' : 'info'}`;
            workerSafeRestartBanner.innerHTML = `<div class="d-flex align-items-start flex-wrap" style="gap:8px;"><div class="flex-grow-1"><i class="fas fa-hourglass-half mr-2"></i>${headline}${busyList}<div class="worker-restart-banner__meta">Dijadwalkan ${formatClock(pending.requested_at)}</div></div><button type="button" class="btn btn-sm btn-outline-secondary" data-action="cancel-worker-restart"><i class="fas fa-times mr-1"></i>Batalkan</button></div>`;
            return;
        }
        if (payload.code_outdated) {
            workerSafeRestartBanner.className = 'worker-restart-banner worker-restart-banner--info';
            workerSafeRestartBanner.innerHTML = `<i class="fas fa-code-branch mr-2"></i>Kode aplikasi diperbarui ${formatClock(payload.code_changed_at)} setelah worker dimulai. ${payload.auto_restart_on_code_change ? 'Restart aman akan dijadwalkan otomatis.' : 'Klik Restart Aman untuk memuat kode terbaru.'}`;
            return;
        }
        workerSafeRestartBanner.classList.add('d-none');
    }
    async function cancelWorkerRestart() {
        const response = await fetch(card.dataset.workerRestartCancelUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({})
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal membatalkan restart.');
        renderSafeRestart(payload.safe_restart || null);
        showNotice(payload.message || 'Restart terjadwal dibatalkan.', 'info');
    }
    async function restartWorkers() {
        const confirmation = await Swal.fire({
            icon: 'question',
            title: 'Restart worker secara aman?',
            html: 'Worker dimuat ulang dengan kode terbaru <strong>hanya setelah semua import, snapshot, delete, dan job selesai</strong>.<br><small class="text-muted">Jika masih ada proses berjalan, restart dijadwalkan otomatis dan bisa dibatalkan. Tidak ada proses yang diputus paksa.</small>',
            showCancelButton: true,
            confirmButtonText: 'Restart',
            cancelButtonText: 'Batal'
        });
        if (!confirmation.isConfirmed) return;
        btnWorkerRestart.disabled = true;
        btnWorkerRestart.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i>Merestart...';
        try {
            const response = await fetch(card.dataset.workerRestartUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({})
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal merestart worker.');
            renderWorkerStatus(payload.worker_status || null);
            if (payload.worker_monitor) renderWorkerMonitor(payload.worker_monitor);
            renderSafeRestart(payload.safe_restart || null);
            showNotice(payload.message || 'Restart worker dikirim.', payload.scheduled ? 'warning' : 'info');
            // Follow the hand-over closely; the regular 10s refresh resumes after.
            workerRestartPollTimers.forEach((timer) => clearTimeout(timer));
            workerRestartPollTimers = [3000, 8000, 15000, 30000, 60000].map((delay) => window.setTimeout(() => fetchData(currentPage), delay));
        } finally {
            btnWorkerRestart.innerHTML = '<i class="fas fa-redo-alt mr-1"></i>Restart Worker';
        }
    }
    function statusBadge(job) { return `<span class="job-status-badge job-status-badge--${escapeHtml(job.status_tone || 'muted')}">${escapeHtml(job.status_label || job.status)}</span>`; }
    function progressMarkup(job) { const percent = Math.max(0, Math.min(100, Number(job.percent || 0))); return `<div class="job-progress"><div class="job-progress__bar" style="width:${percent}%"></div></div><div class="job-progress__meta"><span class="font-weight-bold">${percent}%</span><span>${Number(job.processed_rows || 0).toLocaleString('id-ID')} / ${Number(job.total_rows || 0).toLocaleString('id-ID')} baris</span></div>`; }
    function snapshotDetailMarkup(job) { const items = []; if (job.stage_label) items.push(`<span><i class="fas fa-layer-group mr-1"></i>${escapeHtml(job.stage_label)}</span>`); if (job.current_report_label) items.push(`<span><i class="far fa-chart-bar mr-1"></i>${escapeHtml(job.current_report_label)}</span>`); if (job.current_period) items.push(`<span><i class="far fa-calendar-alt mr-1"></i>${escapeHtml(job.current_period)}</span>`); if (Number(job.report_total_units || 0) > 0) items.push(`<span><i class="fas fa-stream mr-1"></i>${Number(job.report_completed_units || 0).toLocaleString('id-ID')} / ${Number(job.report_total_units || 0).toLocaleString('id-ID')} periode</span>`); return items.join(''); }
    function terminateButton(job, compact = false) { if (!job.can_terminate) return '<span class="text-muted small">-</span>'; const disabled = job.termination_requested ? 'disabled' : ''; const label = job.termination_requested ? 'Menunggu Stop' : 'Terminate'; const classes = compact ? 'btn btn-sm btn-outline-danger job-table-action' : 'btn btn-outline-danger job-active-card__action'; return `<button type="button" class="${classes}" data-action="terminate" data-job-id="${job.id}" ${disabled}><i class="fas fa-stop-circle mr-1"></i>${label}</button>`; }
    function forceStartButton(job, compact = false, snapshot = false) { if (!job.can_force_start) return ''; const classes = compact ? 'btn btn-sm btn-outline-primary job-table-action' : 'btn btn-outline-primary job-active-card__action'; const action = snapshot ? 'force-start-snapshot' : 'force-start'; const identity = snapshot ? `data-rebuild-id="${job.id}"` : `data-job-id="${job.id}"`; return `<button type="button" class="${classes}" data-action="${action}" ${identity}><i class="fas fa-play mr-1"></i>Force Start</button>`; }
    function deleteButton(job, compact = false) { if (!job.can_delete) return ''; const classes = compact ? 'btn btn-sm btn-outline-secondary job-table-action' : 'btn btn-outline-secondary job-active-card__action'; return `<button type="button" class="${classes}" data-action="delete" data-job-id="${job.id}"><i class="fas fa-times-circle mr-1"></i>Delete</button>`; }
    function cancelDeleteButton(job, compact = false) { if (!job.can_cancel) return ''; const classes = compact ? 'btn btn-sm btn-outline-danger job-table-action' : 'btn btn-outline-danger job-active-card__action'; const label = job.termination_requested ? 'Menunggu Cancel' : 'Cancel'; return `<button type="button" class="${classes}" data-action="cancel-delete" data-delete-id="${job.id}" ${job.termination_requested ? 'disabled' : ''}><i class="fas fa-ban mr-1"></i>${label}</button>`; }
    function forceStopDeleteButton(job, compact = false) { if (!job.can_cancel) return ''; const classes = compact ? 'btn btn-sm btn-danger job-table-action' : 'btn btn-danger job-active-card__action'; const label = job.termination_requested ? 'Menunggu Stop' : 'Force Stop'; return `<button type="button" class="${classes}" data-action="force-stop-delete" data-delete-id="${job.id}" ${job.termination_requested ? 'disabled' : ''}><i class="fas fa-stop-circle mr-1"></i>${label}</button>`; }
    function deleteQueueJobButton(job) { if (!job.can_delete) return '<span class="badge badge-secondary" style="font-size:.75rem;">Reserved</span>'; return `<button type="button" class="btn btn-sm btn-outline-danger job-table-action" data-action="delete-queue-job" data-queue-job-id="${job.id}" title="Hapus dari antrian"><i class="fas fa-times-circle mr-1"></i>Hapus</button>`; }
    function forceRunQueueJobButton(job) { if (!job.can_force_run) return ''; return `<button type="button" class="btn btn-sm btn-outline-primary job-table-action" data-action="force-run-queue-job" data-queue-job-id="${job.id}" title="Jalankan langsung tanpa menunggu worker"><i class="fas fa-play mr-1"></i>Force Run</button>`; }
    function renderRawQueueJobs(items, summary = {}) {
        const total = Number(summary.total || 0);
        const pending = Number(summary.pending || 0);
        const reserved = Number(summary.reserved || 0);
        rawQueueJobCountLabel.textContent = `${total.toLocaleString('id-ID')} job (${pending} pending, ${reserved} reserved)`;
        if (!Array.isArray(items) || items.length === 0) {
            rawQueueGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-database fa-2x mb-2 d-block text-muted opacity-50"></i>Tidak ada queue job yang terdeteksi di tabel <code>jobs</code>.</div></div>`;
            return;
        }
        rawQueueGrid.innerHTML = `<div class="col-12"><div class="raw-queue-table-wrap"><table class="table table-hover mb-0 job-management-table"><thead><tr class="job-management-table__header"><th>ID</th><th>Queue</th><th>Job Class</th><th>Parameter</th><th>Status</th><th>Usia</th><th>Dibuat</th><th class="text-center">Aksi</th></tr></thead><tbody>${items.map((job) => `<tr><td class="font-weight-bold" style="color:#b45309;">#${job.id}</td><td><span class="badge badge-light border" style="font-size:.78rem;font-weight:700;">${escapeHtml(job.queue)}</span></td><td><div class="job-table-primary" style="font-size:.88rem;">${escapeHtml(job.class_name)}</div></td><td><div class="job-table-secondary" style="font-size:.8rem;">${escapeHtml(job.job_data_label || '-')}</div></td><td><span class="job-status-badge job-status-badge--${escapeHtml(job.status_tone)}">${escapeHtml(job.status_label)}</span></td><td><div class="job-table-secondary">${escapeHtml(job.age_label || '-')}</div></td><td><div class="job-table-secondary">${escapeHtml(job.created_at_label || '-')}</div></td><td class="text-center"><div class="job-table-actions">${forceRunQueueJobButton(job)}${deleteQueueJobButton(job)}</div></td></tr>`).join('')}</tbody></table></div></div>`;
    }
    function rowCheckbox(job) { if (!job.can_delete) return '<span class="text-muted small">-</span>'; const checked = selectedJobIds.has(String(job.id)) ? 'checked' : ''; return `<input type="checkbox" class="job-row-check" data-job-id="${job.id}" aria-label="Pilih job ${job.id}" ${checked}>`; }
    function syncSelectionState() { const selectableIds = currentJobs.filter((job) => job.can_delete).map((job) => String(job.id)); const selectedOnPage = selectableIds.filter((id) => selectedJobIds.has(id)); selectAll.checked = selectableIds.length > 0 && selectedOnPage.length === selectableIds.length; selectAll.indeterminate = selectedOnPage.length > 0 && selectedOnPage.length < selectableIds.length; selectedCount.textContent = `${selectedJobIds.size} job dipilih`; btnDeleteSelected.disabled = selectedJobIds.size === 0; }
    function renderSummary(summary) { document.getElementById('summary-active').textContent = Number(summary.active_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-queued').textContent = Number(summary.queued_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-processing').textContent = Number(summary.processing_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-today').textContent = Number(summary.today_jobs || 0).toLocaleString('id-ID'); }
    function renderSnapshotJobs(items, summary = {}) { const activeCount = Number(summary.active_jobs || 0); snapshotJobCountLabel.textContent = `${activeCount.toLocaleString('id-ID')} job snapshot aktif`; if (!Array.isArray(items) || items.length === 0) { snapshotGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-clone fa-2x mb-2 d-block text-muted opacity-50"></i>Belum ada rebuild snapshot yang sedang antre atau berjalan.</div></div>`; return; } snapshotGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card job-active-card--snapshot"><div class="job-active-card__header"><div><div class="job-active-card__title">${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)} • ${escapeHtml(job.id)}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta">${snapshotDetailMarkup(job)}<span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span>${job.queue_name ? `<span><i class="fas fa-server mr-1"></i>${escapeHtml(job.queue_name)}</span>` : ''}</div></div><div class="job-active-card__footer"><span class="job-active-card__hint">${job.status === 'failed' ? 'Snapshot stale ditandai gagal otomatis.' : 'Rebuild dipantau dari cache report management.'}</span><div class="job-active-card__actions">${forceStartButton(job, false, true)}</div></div></div></div>`).join(''); }
    function renderActiveJobs(items) { activeJobCountLabel.textContent = `${Number(items.length || 0).toLocaleString('id-ID')} job aktif`; if (!Array.isArray(items) || items.length === 0) { activeGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-hourglass-half fa-2x mb-2 d-block text-muted opacity-50"></i>Belum ada job dengan status queued atau processing.</div></div>`; return; } activeGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card"><div class="job-active-card__header"><div><div class="job-active-card__title">#${job.id} • ${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)}${job.table_name ? ' • ' + escapeHtml(job.table_name) : ''}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta"><span><i class="far fa-user mr-1"></i>${escapeHtml(job.created_by_name || 'System')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span><span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span></div></div><div class="job-active-card__footer">${job.termination_requested ? '<span class="job-active-card__hint">Permintaan terminate dikirim.</span>' : '<span class="job-active-card__hint">Job aktif dapat dihentikan kapan saja.</span>'}<div class="job-active-card__actions">${forceStartButton(job)}${terminateButton(job)}</div></div></div></div>`).join(''); }
    function renderManagedDeleteJobs(items, summary = {}) { const activeCount = Number(summary.active_jobs || 0); managedDeleteJobCountLabel.textContent = `${activeCount.toLocaleString('id-ID')} job delete aktif`; if (!Array.isArray(items) || items.length === 0) { managedDeleteGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-trash-alt fa-2x mb-2 d-block text-muted opacity-50"></i>Belum ada progress delete yang sedang antre atau berjalan.</div></div>`; return; } managedDeleteGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card job-active-card--delete"><div class="job-active-card__header"><div><div class="job-active-card__title"><span title="${escapeHtml(job.id)}">#${escapeHtml(shortId(job.id))}</span> • ${escapeHtml(job.report_name || 'Managed Delete')}</div><div class="job-active-card__sub">${escapeHtml(job.table_name || '-')} • ${escapeHtml(job.file_name || '-')}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta"><span><i class="fas fa-layer-group mr-1"></i>${escapeHtml(job.stage_label || job.phase || '-')}</span><span><i class="fas fa-stream mr-1"></i>${Number(job.scope_count || 0).toLocaleString('id-ID')} scope</span><span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span>${job.queue_name ? `<span><i class="fas fa-server mr-1"></i>${escapeHtml(job.queue_name)}${job.queue_job_id ? ' #' + escapeHtml(job.queue_job_id) : ''}</span>` : ''}</div></div><div class="job-active-card__footer">${job.can_cancel ? (job.termination_requested ? '<span class="job-active-card__hint">Force stop sudah dikirim.</span>' : '<span class="job-active-card__hint">Delete aktif dapat dibatalkan dengan aman.</span>') : '<span class="job-active-card__hint">Delete ini sudah selesai atau tidak aktif.</span>'}<div class="job-active-card__actions">${forceStopDeleteButton(job)}${cancelDeleteButton(job)}</div></div></div></div>`).join(''); }
    function renderTable(items) { currentJobs = Array.isArray(items) ? items : []; if (currentJobs.length === 0) { tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2 d-block text-muted opacity-50"></i>Tidak ada data job untuk filter ini.</td></tr>'; syncSelectionState(); return; } tableBody.innerHTML = currentJobs.map((job) => `<tr><td class="text-center job-col-check">${rowCheckbox(job)}</td><td class="font-weight-bold" style="color: #0857c3;">#${job.id}</td><td><div class="job-table-primary">${escapeHtml(job.report_name)}</div><div class="job-table-secondary">${escapeHtml(job.table_name || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.file_name)}</div><div class="job-table-secondary">By ${escapeHtml(job.created_by_name || 'System')}</div></td><td>${statusBadge(job)}</td><td>${progressMarkup(job)}<div class="job-table-secondary mt-1">${escapeHtml(job.message || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.updated_at_label || '-')}</div><div class="job-table-secondary">Durasi ${escapeHtml(job.duration_label || '-')}</div></td><td class="text-center"><div class="job-table-actions">${forceStartButton(job, true)}${terminateButton(job, true)}${deleteButton(job, true)}</div></td></tr>`).join(''); syncSelectionState(); }
    function shortId(value) { const id = String(value ?? ''); return id.length > 12 ? `${id.slice(0, 8)}…` : id; }
    function renderDeleteHistoryTable() {
        if (deleteHistory.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-trash-alt fa-2x mb-2 d-block text-muted opacity-50"></i>Belum ada riwayat delete report dalam 14 hari terakhir.</td></tr>';
            return;
        }
        tableBody.innerHTML = deleteHistory.map((job) => `<tr><td class="text-center job-col-check"><span class="text-muted small">-</span></td><td class="font-weight-bold" style="color: #0857c3;" title="${escapeHtml(job.id)}">#${escapeHtml(shortId(job.id))}</td><td><div class="job-table-primary">Delete Report Rows</div><div class="job-table-secondary">${escapeHtml(job.table_name || '-')}</div></td><td><div class="job-table-primary">${Number(job.scope_count || 0).toLocaleString('id-ID')} scope</div><div class="job-table-secondary">By ${escapeHtml(job.created_by_name || 'System')}</div></td><td>${statusBadge(job)}</td><td>${progressMarkup(job)}<div class="job-table-secondary mt-1">${escapeHtml(job.message || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.updated_at_label || '-')}</div><div class="job-table-secondary">Durasi ${escapeHtml(job.duration_label || '-')}</div></td><td class="text-center"><span class="text-muted small">-</span></td></tr>`).join('');
    }
    function renderDeleteHistory(items) {
        deleteHistory = Array.isArray(items) ? items : [];
        historyDeleteCount.textContent = deleteHistory.length.toLocaleString('id-ID');
    }
    function applyHistoryView() {
        historyTabs.forEach((tab) => {
            const active = tab.dataset.historyView === historyView;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        historyImportTools.classList.toggle('d-none', historyView !== 'import');
        if (historyView === 'delete') {
            pagination.classList.add('d-none');
            paginationMeta.textContent = `${deleteHistory.length.toLocaleString('id-ID')} delete`;
            renderDeleteHistoryTable();
            return;
        }
        renderTable(currentJobs);
        renderPagination(lastPaginationMeta);
    }
    historyTabs.forEach((tab) => tab.addEventListener('click', () => {
        if (historyView === tab.dataset.historyView) return;
        historyView = tab.dataset.historyView;
        applyHistoryView();
    }));
    function renderPagination(meta) { const total = Number(meta.total || 0); paginationMeta.textContent = `${total.toLocaleString('id-ID')} job`; if (!meta.last_page || meta.last_page <= 1) { pagination.classList.add('d-none'); pagination.innerHTML = ''; return; } const buttons = []; for (let page = 1; page <= meta.last_page; page++) { buttons.push(`<button type="button" class="job-page-btn ${page === meta.current_page ? 'is-active' : ''}" data-page="${page}">${page}</button>`); } pagination.innerHTML = `<div class="job-management-pagination__meta">Menampilkan ${meta.from || 0}-${meta.to || 0} dari ${total.toLocaleString('id-ID')} job</div><div class="job-management-pagination__actions">${buttons.join('')}</div>`; pagination.classList.remove('d-none'); }
    async function fetchData(page = 1) { if (loading) { refreshRequestedWhileLoading = true; return; } loading = true; currentPage = page; try { const params = new URLSearchParams({ page: String(page), status: filterStatus.value || 'all', search: filterSearch.value || '', active_only: activeOnly.checked ? '1' : '0' }); const response = await fetch(`${card.dataset.fetchUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal memuat data job.'); hideNotice(); renderWorkerStatus(payload.worker_status || null); renderWorkerMonitor(payload.worker_monitor || null); renderSafeRestart(payload.safe_restart || null); renderQueueHealth(payload.queue_health || null); renderSummary(payload.summary || {}); renderSnapshotJobs(payload.snapshot_jobs || [], payload.snapshot_summary || {}); renderRawQueueJobs(payload.raw_queue_jobs || [], payload.raw_queue_summary || {}); renderManagedDeleteJobs(payload.managed_delete_jobs || [], payload.managed_delete_summary || {}); renderActiveJobs(payload.active_jobs || []); renderTable(payload.jobs || []); renderPagination(payload.pagination || {}); lastPaginationMeta = payload.pagination || {}; historyImportCount.textContent = Number(lastPaginationMeta.total || 0).toLocaleString('id-ID'); renderDeleteHistory(payload.managed_delete_history || []); if (historyView !== 'import') applyHistoryView(); } catch (error) { showNotice(error.message || 'Gagal memuat data job.', 'warning'); } finally { loading = false; if (refreshRequestedWhileLoading && !document.hidden) { refreshRequestedWhileLoading = false; fetchData(currentPage); } } }
    async function setWorkerState(action) {
        const isStart = action === 'start';
        const confirmation = await Swal.fire({
            icon: isStart ? 'question' : 'warning',
            title: isStart ? 'Aktifkan worker monitor?' : 'Matikan worker monitor?',
            text: isStart
                ? 'Server akan menjalankan monitor worker di background dengan konfigurasi yang ditampilkan.'
                : 'Job yang sedang diproses diselesaikan dahulu. Worker tidak akan dimatikan paksa.',
            showCancelButton: true,
            confirmButtonText: isStart ? 'Aktifkan' : 'Matikan',
            cancelButtonText: 'Batal'
        });
        if (!confirmation.isConfirmed) return;
        btnWorkerStart.disabled = true;
        btnWorkerStop.disabled = true;
        const response = await fetch(isStart ? card.dataset.workerStartUrl : card.dataset.workerStopUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({})
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal mengubah status worker.');
        renderWorkerStatus(payload.worker_status || null);
        showNotice(payload.message || 'Status worker diperbarui.', 'info');
        window.setTimeout(() => fetchData(currentPage), 1500);
    }
    async function forceStartJob(jobId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Force start job ini?', text: 'Job queued akan diproses langsung tanpa menunggu worker queue.', showCancelButton: true, confirmButtonText: 'Force Start', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.forceStartUrlTemplate, jobId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal force start job.'); showNotice(payload.message || 'Force start dijalankan.', 'info'); await fetchData(currentPage); }
    async function forceStartSnapshot(rebuildId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Force start snapshot ini?', text: 'Snapshot rebuild queued akan diproses langsung tanpa menunggu worker queue.', showCancelButton: true, confirmButtonText: 'Force Start', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.forceStartSnapshotUrlTemplate, rebuildId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal force start snapshot.'); showNotice(payload.message || 'Force start snapshot dijalankan.', 'info'); await fetchData(currentPage); }
    async function terminateJob(jobId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Terminate job ini?', text: 'Jika job sedang processing, worker akan menghentikan proses pada checkpoint berikutnya.', showCancelButton: true, confirmButtonText: 'Terminate', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.terminateUrlTemplate, jobId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal terminate job.'); showNotice(payload.message || 'Permintaan terminate dikirim.', 'info'); await fetchData(currentPage); }
    async function forceStopManagedDelete(deleteId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Force stop delete ini?', text: 'Aksi ini akan menghentikan delete seaman mungkin dan menunggu checkpoint berikutnya.', showCancelButton: true, confirmButtonText: 'Force Stop', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.forceStopDeleteUrlTemplate, deleteId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal force stop delete.'); showNotice(payload.message || 'Delete dihentikan paksa.', 'info'); await fetchData(currentPage); }
    async function cancelManagedDelete(deleteId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Cancel delete ini?', text: 'Delete aktif akan dibatalkan dengan aman dari halaman ini.', showCancelButton: true, confirmButtonText: 'Cancel', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.cancelDeleteUrlTemplate, deleteId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal cancel delete.'); showNotice(payload.message || 'Delete dibatalkan.', 'info'); await fetchData(currentPage); }
    async function deleteJob(jobId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Hapus job ini?', text: 'Record job akan dihapus dari database dan cache progress terkait juga dibersihkan.', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.destroyUrlTemplate, jobId), { method: 'DELETE', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus job.'); selectedJobIds.delete(String(jobId)); showNotice(payload.message || 'Job berhasil dihapus.', 'info'); await fetchData(currentPage); }
    async function forceRunQueueJob(queueJobId, className) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Force run queue job ini?', html: `Job <strong>${escapeHtml(className || '#' + queueJobId)}</strong> akan dihapus dari queue dan dijalankan <strong>langsung di request ini</strong> tanpa menunggu worker.<br><small class="text-muted">Untuk job berat (snapshot rebuild, import), proses ini bisa memakan waktu cukup lama.</small>`, showCancelButton: true, confirmButtonText: 'Force Run', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const url = String(card.dataset.forceRunQueueJobUrlTemplate || '').replace('__QUEUE_JOB_ID__', encodeURIComponent(queueJobId || '')); const response = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal force run queue job.'); showNotice(payload.message || 'Force run dijalankan.', payload.status === 'warning' ? 'warning' : 'info'); await fetchData(currentPage); }
    async function deleteQueueJob(queueJobId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Hapus queue job ini?', text: 'Job akan dihapus dari tabel antrian database. Jika worker belum mengambilnya, job ini tidak akan dijalankan.', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const url = String(card.dataset.destroyQueueJobUrlTemplate || '').replace('__QUEUE_JOB_ID__', encodeURIComponent(queueJobId || '')); const response = await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus queue job.'); showNotice(payload.message || 'Queue job berhasil dihapus.', 'info'); await fetchData(currentPage); }
    async function purgeQueueJobs() { const confirmation = await Swal.fire({ icon: 'warning', title: 'Purge semua pending queue jobs?', text: 'Semua job Pending (belum diambil worker) yang diketahui sistem akan dihapus dari antrian. Job yang sedang Reserved/diproses tidak terpengaruh.', showCancelButton: true, confirmButtonText: 'Purge', cancelButtonText: 'Batal', confirmButtonColor: '#dc3545' }); if (!confirmation.isConfirmed) return; const response = await fetch(card.dataset.purgeQueueJobsUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal purge queue jobs.'); showNotice(payload.message || 'Queue jobs berhasil di-purge.', payload.status === 'warning' ? 'warning' : 'info'); await fetchData(currentPage); }
    async function clearJobs() { const confirmation = await Swal.fire({ icon: 'warning', title: 'Clear jobs?', text: 'Aksi ini akan menghapus riwayat job terminal yang sesuai filter saat ini dari database.', showCancelButton: true, confirmButtonText: 'Clear', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(card.dataset.clearUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ status: filterStatus.value || 'all', search: filterSearch.value || '' }) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal clear jobs.'); selectedJobIds.clear(); showNotice(payload.message || 'Riwayat job berhasil dibersihkan.', payload.status === 'warning' ? 'warning' : 'info'); await fetchData(1); }
    async function bulkDeleteJobs() { const jobIds = Array.from(selectedJobIds.values()); if (jobIds.length === 0) return; const confirmation = await Swal.fire({ icon: 'warning', title: 'Delete selected jobs?', text: `${jobIds.length} job terminal akan dihapus dari database.`, showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(card.dataset.bulkDeleteUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ job_ids: jobIds }) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus job terpilih.'); selectedJobIds.clear(); showNotice(payload.message || 'Job terpilih berhasil dihapus.', payload.status === 'warning' ? 'warning' : 'info'); await fetchData(currentPage); }
    function stopAutoRefresh() { if (refreshTimer) clearTimeout(refreshTimer); refreshTimer = null; }
    function scheduleAutoRefresh() { stopAutoRefresh(); if (!autoRefresh.checked || document.hidden) return; refreshTimer = setTimeout(async () => { await fetchData(currentPage); scheduleAutoRefresh(); }, 10000); }
    function startAutoRefresh() { scheduleAutoRefresh(); }

    btnRefresh.addEventListener('click', () => fetchData(currentPage));
    btnWorkerStart.addEventListener('click', async () => { try { await setWorkerState('start'); } catch (error) { Swal.fire({ icon: 'error', title: 'Aktivasi Worker Gagal', text: error.message || 'Gagal mengaktifkan worker.' }); await fetchData(currentPage); } });
    btnWorkerStop.addEventListener('click', async () => { try { await setWorkerState('stop'); } catch (error) { Swal.fire({ icon: 'error', title: 'Stop Worker Gagal', text: error.message || 'Gagal mematikan worker.' }); await fetchData(currentPage); } });
    btnWorkerRestart.addEventListener('click', async () => { try { await restartWorkers(); } catch (error) { Swal.fire({ icon: 'error', title: 'Restart Worker Gagal', text: error.message || 'Gagal merestart worker.' }); await fetchData(currentPage); } });
    btnPurgeQueueJobs.addEventListener('click', async () => { try { await purgeQueueJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Purge Gagal', text: error.message || 'Gagal purge queue jobs.' }); } });
    btnClear.addEventListener('click', async () => { try { await clearJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Clear Gagal', text: error.message || 'Gagal clear jobs.' }); } });
    btnDeleteSelected.addEventListener('click', async () => { try { await bulkDeleteJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Bulk Delete Gagal', text: error.message || 'Gagal menghapus job terpilih.' }); } });
    filterStatus.addEventListener('change', () => fetchData(1));
    activeOnly.addEventListener('change', () => fetchData(1));
    filterSearch.addEventListener('input', function () { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(() => fetchData(1), 350); });
    autoRefresh.addEventListener('change', startAutoRefresh);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stopAutoRefresh(); else { fetchData(currentPage); startAutoRefresh(); } });
    selectAll.addEventListener('change', function () { currentJobs.forEach((job) => { if (!job.can_delete) return; if (selectAll.checked) selectedJobIds.add(String(job.id)); else selectedJobIds.delete(String(job.id)); }); syncSelectionState(); tableBody.querySelectorAll('.job-row-check').forEach((checkbox) => { checkbox.checked = selectAll.checked; }); });
    document.addEventListener('change', function (event) { const checkbox = event.target.closest('.job-row-check'); if (!checkbox) return; const jobId = String(checkbox.dataset.jobId || ''); if (jobId === '') return; if (checkbox.checked) selectedJobIds.add(jobId); else selectedJobIds.delete(jobId); syncSelectionState(); });
    document.addEventListener('click', async function (event) { const cancelRestartTarget = event.target.closest('[data-action="cancel-worker-restart"]'); if (cancelRestartTarget) { try { await cancelWorkerRestart(); } catch (error) { Swal.fire({ icon: 'error', title: 'Batal Restart Gagal', text: error.message || 'Gagal membatalkan restart.' }); } return; } const forceStartTarget = event.target.closest('[data-action="force-start"]'); if (forceStartTarget) { try { await forceStartJob(forceStartTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Start Gagal', text: error.message || 'Gagal force start job.' }); } return; } const forceStartSnapshotTarget = event.target.closest('[data-action="force-start-snapshot"]'); if (forceStartSnapshotTarget) { try { await forceStartSnapshot(forceStartSnapshotTarget.dataset.rebuildId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Start Gagal', text: error.message || 'Gagal force start snapshot.' }); } return; } const terminateTarget = event.target.closest('[data-action="terminate"]'); if (terminateTarget) { try { await terminateJob(terminateTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Terminate Gagal', text: error.message || 'Gagal terminate job.' }); } return; } const forceStopDeleteTarget = event.target.closest('[data-action="force-stop-delete"]'); if (forceStopDeleteTarget) { try { await forceStopManagedDelete(forceStopDeleteTarget.dataset.deleteId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Stop Gagal', text: error.message || 'Gagal force stop delete.' }); } return; } const cancelDeleteTarget = event.target.closest('[data-action="cancel-delete"]'); if (cancelDeleteTarget) { try { await cancelManagedDelete(cancelDeleteTarget.dataset.deleteId); } catch (error) { Swal.fire({ icon: 'error', title: 'Cancel Delete Gagal', text: error.message || 'Gagal cancel delete.' }); } return; } const deleteTarget = event.target.closest('[data-action="delete"]'); if (deleteTarget) { try { await deleteJob(deleteTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Delete Gagal', text: error.message || 'Gagal menghapus job.' }); } return; } const forceRunQueueJobTarget = event.target.closest('[data-action="force-run-queue-job"]'); if (forceRunQueueJobTarget) { try { await forceRunQueueJob(forceRunQueueJobTarget.dataset.queueJobId, forceRunQueueJobTarget.closest('tr')?.querySelector('.job-table-primary')?.textContent?.trim()); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Run Gagal', text: error.message || 'Gagal force run queue job.' }); } return; } const deleteQueueJobTarget = event.target.closest('[data-action="delete-queue-job"]'); if (deleteQueueJobTarget) { try { await deleteQueueJob(deleteQueueJobTarget.dataset.queueJobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Delete Queue Job Gagal', text: error.message || 'Gagal menghapus queue job.' }); } return; } const pageTarget = event.target.closest('[data-page]'); if (pageTarget) fetchData(Number(pageTarget.dataset.page || 1)); });

    fetchData();
    startAutoRefresh();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const runtimeCard = document.getElementById('server-runtime-card');
    if (!runtimeCard) return;

    const autoRefresh = document.getElementById('job-auto-refresh');
    const refreshButton = document.getElementById('btn-runtime-refresh');
    const runtimeNotice = document.getElementById('runtime-notice');
    const actionButtons = Array.from(runtimeCard.querySelectorAll('[data-runtime-service][data-runtime-action]'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
    const runtimeState = { lastValid: null, readId: 0, readController: null, timer: null, actionPending: false, confirmPending: false, refreshAfterAction: false, refreshWorkerAfterAction: false, noticeKind: null, closed: false, schedulesSignature: null };

    function setRuntimeText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = String(value ?? '—');
    }

    function runtimeTime(value, timezone = 'Asia/Jakarta') {
        if (!value) return '—';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        try {
            return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'medium', timeZone: timezone }).format(date);
        } catch (_) {
            return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'medium', timeZone: 'Asia/Jakarta' }).format(date);
        }
    }

    function setRuntimeBadge(element, label, tone) {
        if (!element) return;
        const safeTone = ['success', 'warning', 'danger', 'info', 'secondary'].includes(tone) ? tone : 'secondary';
        element.className = 'server-runtime__state server-runtime__state--' + safeTone;
        element.textContent = String(label || 'Tidak diketahui');
    }

    function showRuntimeNotice(message, tone = 'info', kind = 'action') {
        const safeTone = ['success', 'warning', 'danger', 'info'].includes(tone) ? tone : 'info';
        runtimeNotice.className = 'server-runtime__notice server-runtime__notice--' + safeTone;
        runtimeNotice.textContent = String(message || 'Status layanan diperbarui.');
        runtimeState.noticeKind = kind;
    }

    function syncRuntimeButtons() {
        const busy = runtimeState.actionPending || runtimeState.confirmPending;
        const scheduler = runtimeState.lastValid?.scheduler;
        refreshButton.disabled = busy || runtimeState.readController !== null;
        refreshButton.setAttribute('aria-busy', runtimeState.readController !== null ? 'true' : 'false');
        refreshButton.querySelector('i')?.classList.toggle('fa-spin', runtimeState.readController !== null);
        runtimeCard.setAttribute('aria-busy', runtimeState.actionPending ? 'true' : 'false');
        actionButtons.forEach(function (button) {
            const service = button.dataset.runtimeService;
            const action = button.dataset.runtimeAction;
            let disabled = busy || !runtimeState.lastValid;
            if (service === 'scheduler') {
                disabled = disabled || scheduler?.available === false;
                if (action === 'start') disabled = disabled || ['active', 'starting', 'external'].includes(scheduler?.state);
                if (action === 'stop') disabled = disabled || (scheduler?.enabled === false && ['stopped', 'inactive'].includes(scheduler?.state));
                if (action === 'run') disabled = disabled || scheduler?.enabled !== true || scheduler?.task_registered !== true;
                if (action === 'restart') disabled = disabled || scheduler?.state === 'starting';
            } else if (service === 'backup') {
                disabled = disabled || runtimeState.lastValid?.backup?.runnable !== true;
            } else if (service !== 'all') {
                const entry = runtimeState.lastValid?.infrastructure?.find(item => item.key === service);
                // Running but manual startup still needs one click to survive a reboot.
                disabled = disabled || !((entry?.startable && entry?.state !== 'running') || (entry?.state === 'running' && entry?.automatic === false));
            }
            button.disabled = disabled;
        });
        setRuntimeText('runtime-refresh-mode', autoRefresh?.checked ? 'Pembaruan otomatis aktif saat halaman terlihat.' : 'Pembaruan otomatis dijeda.');
    }

    function renderRuntimeSchedules(schedules) {
        const entries = Array.isArray(schedules) ? schedules : [];
        const signature = JSON.stringify(entries);
        if (signature === runtimeState.schedulesSignature) return;
        runtimeState.schedulesSignature = signature;
        const body = document.getElementById('runtime-schedule-body');
        body.replaceChildren();
        setRuntimeText('runtime-schedule-count', '(' + entries.length.toLocaleString('id-ID') + ')');
        if (entries.length === 0) {
            const row = body.insertRow();
            const cell = row.insertCell();
            cell.colSpan = 3;
            cell.textContent = 'Tidak ada informasi jadwal yang tersedia.';
            return;
        }
        entries.forEach(function (entry) {
            const row = body.insertRow();
            const titleCell = row.insertCell();
            const title = document.createElement('strong');
            title.textContent = String(entry.label || 'Jadwal otomatis');
            titleCell.appendChild(title);
            if (entry.command) {
                const command = document.createElement('code');
                command.className = 'server-runtime__schedule-command';
                command.textContent = String(entry.command);
                titleCell.appendChild(command);
            }
            row.insertCell().textContent = String(entry.expression || '—');
            const nextCell = row.insertCell();
            nextCell.textContent = runtimeTime(entry.next_run_at, entry.timezone || 'Asia/Jakarta');
            const timezone = document.createElement('small');
            timezone.className = 'server-runtime__schedule-zone';
            timezone.textContent = String(entry.timezone || 'Asia/Jakarta');
            nextCell.appendChild(timezone);
        });
    }

    function renderRuntimeBackup(backup) {
        const state = backup?.state || 'unknown';
        const label = backup?.available === false ? 'Belum dapat diverifikasi'
            : (state === 'running' ? 'Sedang berjalan' : (backup?.failed ? 'Terakhir gagal' : (state === 'unregistered' ? 'Lewat scheduler Laravel' : (backup?.enabled ? 'Terjadwal' : 'Nonaktif'))));
        const tone = backup?.available === false ? 'secondary' : (state === 'running' ? 'info' : (backup?.failed || (backup?.registered && !backup?.enabled) ? 'warning' : 'success'));
        setRuntimeBadge(document.getElementById('runtime-backup-state'), label, tone);
        setRuntimeText('runtime-backup-schedule', backup?.registered
            ? 'Terakhir: ' + runtimeTime(backup.last_run_at) + '; berikutnya: ' + runtimeTime(backup.next_run_at)
            : 'Setiap hari pukul 00:00 WIB, dengan catch-up tiap jam bila server mati tengah malam.');
        setRuntimeText('runtime-backup-message', backup?.message || 'Backup hari ini yang sudah terverifikasi tidak dibuat ulang.');
    }

    function renderRuntimeReadiness(items, ready) {
        const entries = Array.isArray(items) ? items : [];
        const list = document.getElementById('runtime-readiness-list');
        const failing = entries.filter(item => item.ok === false).length;
        const unknown = entries.filter(item => item.ok !== true && item.ok !== false).length;
        setRuntimeBadge(document.getElementById('runtime-readiness-badge'),
            failing > 0 ? failing.toLocaleString('id-ID') + ' butir belum siap' : (unknown > 0 ? 'Siap, ' + unknown.toLocaleString('id-ID') + ' butir belum terverifikasi' : 'Siap untuk restart'),
            failing > 0 ? 'warning' : (unknown > 0 ? 'info' : 'success'));
        list.replaceChildren();
        entries.forEach(function (item) {
            const kind = item.ok === true ? 'ok' : (item.ok === false ? 'fail' : 'unknown');
            const li = document.createElement('li');
            li.className = 'server-runtime__check server-runtime__check--' + kind;
            const icon = document.createElement('i');
            icon.className = 'fas ' + (kind === 'ok' ? 'fa-check-circle' : (kind === 'fail' ? 'fa-times-circle' : 'fa-question-circle'));
            icon.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('span');
            copy.textContent = String(item.label || 'Pemeriksaan');
            const status = document.createElement('span');
            status.className = 'sr-only';
            status.textContent = kind === 'ok' ? ' (terpenuhi)' : (kind === 'fail' ? ' (belum terpenuhi)' : ' (belum terverifikasi)');
            copy.appendChild(status);
            if (item.hint && kind !== 'ok') {
                const hint = document.createElement('small');
                hint.textContent = String(item.hint);
                copy.appendChild(hint);
            }
            li.append(icon, copy);
            list.appendChild(li);
        });
        if (entries.length === 0) {
            const li = document.createElement('li');
            li.className = 'server-runtime__check';
            li.textContent = 'Informasi kesiapan belum tersedia.';
            list.appendChild(li);
        }
    }

    function renderRuntime(runtime) {
        if (!runtime || typeof runtime !== 'object' || !runtime.scheduler || typeof runtime.scheduler !== 'object') return false;
        runtimeState.lastValid = runtime;
        const scheduler = runtime.scheduler;
        setRuntimeBadge(document.getElementById('runtime-health-badge'), runtime.healthy ? 'Layanan pendukung siap' : 'Perlu perhatian', runtime.healthy ? 'success' : 'warning');
        setRuntimeBadge(document.getElementById('runtime-scheduler-state'), scheduler.state_label, scheduler.tone);
        setRuntimeText('runtime-scheduler-message', scheduler.message || 'Status scheduler tersedia.');
        setRuntimeText('runtime-scheduler-heartbeat', runtimeTime(scheduler.heartbeat_at));
        setRuntimeText('runtime-scheduler-last-run', runtimeTime(scheduler.last_run_at));
        setRuntimeText('runtime-scheduler-next-run', runtimeTime(scheduler.next_run_at));
        const startupLabel = scheduler.available === false ? 'Belum dapat diverifikasi' : (scheduler.startup_mode === 'system' ? 'Saat Windows menyala' : (scheduler.startup_mode === 'login' ? 'Saat akun Windows login' : 'Belum terpasang'));
        setRuntimeText('runtime-scheduler-startup', scheduler.task_registered && scheduler.task_enabled === false ? startupLabel + ' (tugas nonaktif)' : startupLabel);
        const lastResult = scheduler.last_result === null || scheduler.last_result === undefined ? 'Belum tersedia' : String(scheduler.last_result);
        const legacyCount = Number(scheduler.legacy_count || 0);
        setRuntimeText('runtime-scheduler-task-detail', 'Tugas Windows: ' + (scheduler.available === false || scheduler.task_registered == null ? 'belum dapat diverifikasi' : (scheduler.task_registered ? (scheduler.task_enabled ? 'aktif' : 'nonaktif') : 'belum terdaftar')) + '. Hasil eksekusi terakhir: ' + lastResult + (legacyCount > 0 ? '. Scheduler lama terdeteksi: ' + legacyCount.toLocaleString('id-ID') : '') + '.');
        const logValue = Array.isArray(scheduler.log_tail) ? scheduler.log_tail.join('\n') : String(scheduler.log_tail || 'Belum ada log scheduler.');
        const boundedLog = logValue.split(/\r?\n/).slice(-120).join('\n').slice(-20000);
        const log = document.getElementById('runtime-scheduler-log');
        if (log.textContent !== boundedLog) log.textContent = boundedLog;

        const infrastructure = Array.isArray(runtime.infrastructure) ? runtime.infrastructure : [];
        runtimeCard.querySelectorAll('[data-runtime-infrastructure]').forEach(function (row) {
            const entry = infrastructure.find(item => item.key === row.dataset.runtimeInfrastructure);
            const state = entry?.state || 'unknown';
            if (entry?.label) row.querySelector('[data-runtime-service-label]').textContent = String(entry.label);
            setRuntimeBadge(row.querySelector('[data-runtime-service-state]'), state === 'running' ? 'Berjalan' : (state === 'stopped' ? 'Berhenti' : 'Tidak diketahui'), state === 'running' ? 'success' : (state === 'stopped' ? 'warning' : 'secondary'));
            row.querySelector('[data-runtime-service-startup]').textContent = entry?.automatic === true ? 'Startup Windows otomatis' : (entry?.automatic === false ? 'Startup Windows manual' : 'Startup Windows belum diketahui');
            row.querySelector('[data-runtime-service-message]').textContent = String(entry?.message || 'Informasi layanan belum tersedia.');
        });
        renderRuntimeBackup(runtime.backup);
        renderRuntimeReadiness(runtime.readiness, runtime.restart_ready);
        renderRuntimeSchedules(runtime.schedules);
        setRuntimeText('runtime-updated', 'Status terakhir: ' + runtimeTime(runtime.generated_at) + ' WIB');
        syncRuntimeButtons();
        return true;
    }

    function stopRuntimePolling() {
        if (runtimeState.timer !== null) window.clearTimeout(runtimeState.timer);
        runtimeState.timer = null;
    }

    function cancelRuntimeRead() {
        runtimeState.readId += 1;
        if (runtimeState.readController) runtimeState.readController.abort();
        runtimeState.readController = null;
    }

    function scheduleRuntimePolling() {
        stopRuntimePolling();
        if (runtimeState.closed || !autoRefresh?.checked || document.hidden || runtimeState.actionPending || runtimeState.confirmPending) return;
        runtimeState.timer = window.setTimeout(refreshRuntime, runtimeState.lastValid?.healthy ? 15000 : 10000);
    }

    async function refreshRuntime() {
        if (runtimeState.closed || document.hidden || runtimeState.actionPending || runtimeState.confirmPending) return;
        stopRuntimePolling();
        cancelRuntimeRead();
        const readId = runtimeState.readId;
        const controller = new AbortController();
        runtimeState.readController = controller;
        const timeout = window.setTimeout(() => controller.abort(), 20000);
        syncRuntimeButtons();
        try {
            const response = await fetch(runtimeCard.dataset.statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, cache: 'no-store' });
            const payload = await response.json().catch(() => ({}));
            if (readId !== runtimeState.readId || runtimeState.actionPending) return;
            if (!response.ok || payload.status === 'error' || !renderRuntime(payload.runtime)) throw new Error(payload.message || 'Status layanan tidak dapat dimuat.');
            if (runtimeState.noticeKind === 'stale') {
                runtimeNotice.classList.add('d-none');
                runtimeState.noticeKind = null;
            }
        } catch (error) {
            if (readId !== runtimeState.readId) return;
            const message = error.name === 'AbortError' ? 'Pembaruan status melewati batas waktu.' : (error.message || 'Status layanan gagal diperbarui.');
            const retained = runtimeState.lastValid ? ' Status terakhir tetap ditampilkan; data belum terbarui.' : ' Status layanan belum dapat dipastikan. Gunakan tombol perbarui untuk mencoba kembali.';
            showRuntimeNotice(message + retained, 'warning', 'stale');
            setRuntimeBadge(document.getElementById('runtime-health-badge'), runtimeState.lastValid ? 'Status terakhir · belum terbarui' : 'Status belum tersedia', 'warning');
        } finally {
            window.clearTimeout(timeout);
            if (readId === runtimeState.readId) {
                runtimeState.readController = null;
                syncRuntimeButtons();
                scheduleRuntimePolling();
            }
        }
    }

    async function controlRuntime(button) {
        if (button.disabled || runtimeState.actionPending || runtimeState.confirmPending) return;
        const service = button.dataset.runtimeService;
        const action = button.dataset.runtimeAction;
        if (!['all', 'scheduler', 'apache', 'mysql', 'tunnel', 'backup'].includes(service) || !['start', 'stop', 'restart', 'run'].includes(action)) return;
        if (service === 'backup' ? action !== 'run' : (service !== 'scheduler' && action !== 'start')) return;
        stopRuntimePolling();
        cancelRuntimeRead();
        let spinner = null;
        runtimeState.confirmPending = true;
        syncRuntimeButtons();
        try {
            if (service === 'backup') {
                const title = 'Jalankan backup database sekarang?';
                const message = 'Backup berjalan di latar belakang dan dapat memakan waktu lama. Bila backup hari ini sudah ada dan lolos verifikasi, dump ulang dilewati.';
                const confirmed = window.Swal ? (await window.Swal.fire({ icon: 'question', title, text: message, showCancelButton: true, confirmButtonText: 'Jalankan Backup', cancelButtonText: 'Batal', focusCancel: true, reverseButtons: true })).isConfirmed : window.confirm(title + '\n' + message);
                if (!confirmed) return;
            }
            if (service === 'scheduler' && ['stop', 'restart'].includes(action)) {
                const title = action === 'stop' ? 'Matikan scheduler?' : 'Restart scheduler?';
                const message = action === 'stop' ? 'Penjadwalan otomatis akan dihentikan sampai scheduler diaktifkan kembali.' : 'Scheduler akan dihentikan lalu diaktifkan kembali. Penjadwalan otomatis dapat terjeda sementara.';
                const confirmed = window.Swal ? (await window.Swal.fire({ icon: 'warning', title, text: message, showCancelButton: true, confirmButtonText: action === 'stop' ? 'Matikan Scheduler' : 'Restart Scheduler', cancelButtonText: 'Batal', focusCancel: true, reverseButtons: true })).isConfirmed : window.confirm(title + '\n' + message);
                if (!confirmed) return;
            }
            runtimeState.confirmPending = false;
            runtimeState.actionPending = true;
            spinner = document.createElement('i');
            spinner.className = 'fas fa-spinner fa-spin mr-1';
            spinner.setAttribute('aria-hidden', 'true');
            button.prepend(spinner);
            syncRuntimeButtons();
            showRuntimeNotice('Permintaan layanan sedang diproses…', 'info');
            const response = await fetch(runtimeCard.dataset.controlUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ service, action }) });
            const payload = await response.json().catch(() => ({}));
            if (payload.runtime) renderRuntime(payload.runtime);
            const failed = !response.ok || payload.status === 'error';
            runtimeState.refreshWorkerAfterAction = service === 'all' && !failed;
            showRuntimeNotice(payload.message || (failed ? 'Permintaan layanan gagal diproses. Status layanan akan diperiksa kembali.' : 'Permintaan layanan selesai diproses.'), failed ? 'danger' : (payload.status === 'warning' ? 'warning' : 'success'));
        } catch (error) {
            showRuntimeNotice((error.message || 'Permintaan layanan gagal dikirim.') + ' Hasil tindakan belum dapat dipastikan; status akan diperiksa kembali.', 'warning');
        } finally {
            spinner?.remove();
            const acted = runtimeState.actionPending;
            runtimeState.actionPending = false;
            runtimeState.confirmPending = false;
            syncRuntimeButtons();
            if (acted && document.hidden) runtimeState.refreshAfterAction = true;
            else if (acted && runtimeState.refreshWorkerAfterAction && document.getElementById('btn-job-refresh')) {
                runtimeState.refreshWorkerAfterAction = false;
                document.getElementById('btn-job-refresh').click();
            } else if (acted) await refreshRuntime();
            else scheduleRuntimePolling();
        }
    }

    runtimeCard.addEventListener('click', function (event) {
        const button = event.target.closest('[data-runtime-service][data-runtime-action]');
        if (button && runtimeCard.contains(button)) controlRuntime(button);
    });
    refreshButton.addEventListener('click', refreshRuntime);
    document.getElementById('btn-job-refresh')?.addEventListener('click', refreshRuntime);
    autoRefresh?.addEventListener('change', function () {
        syncRuntimeButtons();
        if (autoRefresh.checked) refreshRuntime();
        else stopRuntimePolling();
    });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopRuntimePolling();
            cancelRuntimeRead();
            syncRuntimeButtons();
        } else if (runtimeState.refreshAfterAction || autoRefresh?.checked) {
            runtimeState.refreshAfterAction = false;
            if (runtimeState.refreshWorkerAfterAction && document.getElementById('btn-job-refresh')) {
                runtimeState.refreshWorkerAfterAction = false;
                document.getElementById('btn-job-refresh').click();
            } else refreshRuntime();
        }
    });
    window.addEventListener('pagehide', function () { runtimeState.closed = true; stopRuntimePolling(); cancelRuntimeRead(); });
    window.addEventListener('pageshow', function (event) { if (event.persisted) { runtimeState.closed = false; syncRuntimeButtons(); if (autoRefresh?.checked) refreshRuntime(); } });
    refreshRuntime();
});
</script>
@endsection

@section('styles')
<style>
.server-runtime { border: 1px solid var(--app-line, #d8e5f7); border-radius: 14px; background: var(--app-surface, #fff); color: var(--app-ink, #0b1f3a); padding: 1.25rem; min-width: 0; }
.server-runtime__header, .server-runtime__header-actions, .server-runtime__section-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.65rem; }
.server-runtime__header > div, .server-runtime__service-copy { min-width: 0; }
.server-runtime__header-actions { justify-content: flex-start; }
.server-runtime__title { font-size: 1.1rem; font-weight: 800; margin: 0; color: inherit; }
.server-runtime__intro { color: var(--app-muted, #526987); font-size: 0.84rem; margin: 0.35rem 0 0; }
.server-runtime__access-note { margin: 1rem 0; padding: 0.7rem 0.85rem; border-radius: 8px; background: var(--app-surface-soft, #f7faff); color: var(--app-muted, #526987); font-size: 0.79rem; line-height: 1.6; }
.server-runtime__grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 1.25rem; }
.server-runtime__scheduler { min-width: 0; }
.server-runtime__section-title { margin: 0; font-size: 0.95rem; font-weight: 800; color: inherit; }
.server-runtime__message { font-size: 0.84rem; margin: 0.65rem 0 0.9rem; color: var(--app-muted, #526987); overflow-wrap: anywhere; }
.server-runtime__metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.8rem 1rem; margin: 0 0 1rem; }
.server-runtime__metrics > div { min-width: 0; }
.server-runtime__metrics dt { color: var(--app-muted, #526987); font-size: 0.73rem; font-weight: 600; margin-bottom: 0.2rem; }
.server-runtime__metrics dd { color: inherit; font-size: 0.8rem; font-weight: 700; margin: 0; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.server-runtime__scheduler-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.server-runtime .btn { min-height: 38px; max-width: 100%; white-space: normal; font-size: 0.78rem; font-weight: 700; touch-action: manipulation; }
.server-runtime :where(button, summary, [tabindex]):focus-visible { outline: 2px solid #3b82f6; outline-offset: 3px; }
.server-runtime__state { display: inline-flex; align-items: center; width: fit-content; max-width: 100%; border: 1px solid currentColor; border-radius: 999px; padding: 0.22rem 0.6rem; font-size: 0.7rem; font-weight: 700; line-height: 1.3; overflow-wrap: anywhere; }
.server-runtime__state--secondary { color: var(--app-muted, #526987); }
.server-runtime__state--success { color: #166534; background: #f0fdf4; }
.server-runtime__state--warning { color: #92400e; background: #fffbeb; }
.server-runtime__state--danger { color: #991b1b; background: #fef2f2; }
.server-runtime__state--info { color: #1e40af; background: #eff6ff; }
.server-runtime__infrastructure { display: grid; align-content: start; gap: 0.65rem; min-width: 0; }
.server-runtime__readiness { margin-top: 1.15rem; border-top: 1px solid var(--app-line, #d8e5f7); padding-top: 0.9rem; }
.server-runtime__checklist { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 0.5rem 1rem; }
.server-runtime__check { display: grid; grid-template-columns: 1.1rem minmax(0, 1fr); gap: 0.5rem; align-items: start; font-size: 0.8rem; line-height: 1.5; min-width: 0; }
.server-runtime__check > i { margin-top: 0.2rem; }
.server-runtime__check--ok > i { color: #16a34a; }
.server-runtime__check--fail > i { color: #dc2626; }
.server-runtime__check--unknown > i { color: #d97706; }
.server-runtime__check small { display: block; color: var(--app-muted, #526987); font-size: 0.73rem; overflow-wrap: anywhere; }
:root[data-theme="dark"] .server-runtime__check--ok > i { color: #86efac; }
:root[data-theme="dark"] .server-runtime__check--fail > i { color: #fca5a5; }
:root[data-theme="dark"] .server-runtime__check--unknown > i { color: #fcd34d; }
.server-runtime__service { display: grid; grid-template-columns: 1.25rem minmax(0, 1fr) auto; gap: 0.65rem; align-items: center; border: 1px solid var(--app-line, #d8e5f7); border-radius: 10px; padding: 0.85rem; min-width: 0; }
.server-runtime__service-icon { color: var(--app-muted, #526987); font-size: 1rem; }
.server-runtime__service .server-runtime__section-head { gap: 0.4rem; align-items: flex-start; }
.server-runtime__service strong { font-size: 0.82rem; overflow-wrap: anywhere; }
.server-runtime__hint { color: var(--app-muted, #526987); font-size: 0.74rem; line-height: 1.6; overflow-wrap: anywhere; }
.server-runtime__details-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 0.75rem; margin-top: 1.15rem; align-items: start; }
.server-runtime__details { min-width: 0; border-top: 1px solid var(--app-line, #d8e5f7); padding-top: 0.8rem; }
.server-runtime__details summary { width: fit-content; max-width: 100%; cursor: pointer; font-size: 0.82rem; font-weight: 700; min-height: 28px; color: inherit; }
.server-runtime__details > p { margin: 0.6rem 0; }
.server-runtime__schedule-wrap { overflow: auto; max-width: 100%; max-height: 330px; overscroll-behavior: contain; }
.server-runtime__schedule-table { min-width: 480px; margin: 0; color: inherit; font-size: 0.75rem; }
.server-runtime__schedule-table th { background: var(--app-surface-soft, #f7faff); color: var(--app-muted, #526987); border-top: 0; position: sticky; top: 0; z-index: 1; }
.server-runtime__schedule-table td { border-color: var(--app-line, #d8e5f7); vertical-align: top; font-variant-numeric: tabular-nums; }
.server-runtime__schedule-command, .server-runtime__schedule-zone { display: block; margin-top: 0.2rem; color: var(--app-muted, #526987); font-size: 0.7rem; overflow-wrap: anywhere; white-space: normal; }
.server-runtime__log { margin: 0; padding: 0.8rem; background: var(--app-surface-soft, #f7faff); color: var(--app-ink, #0b1f3a); border: 1px solid var(--app-line, #d8e5f7); border-radius: 8px; max-height: 260px; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 0.72rem; line-height: 1.6; overscroll-behavior: contain; }
.server-runtime__footer { display: flex; flex-wrap: wrap; gap: 0.45rem 1rem; justify-content: space-between; margin-top: 1rem; color: var(--app-muted, #526987); font-size: 0.72rem; font-variant-numeric: tabular-nums; }
.server-runtime__notice { margin: 0 0 1rem; padding: 0.75rem 0.9rem; border: 1px solid var(--app-line, #d8e5f7); border-left-width: 3px; border-radius: 8px; font-size: 0.82rem; background: var(--app-surface-soft, #f7faff); color: inherit; overflow-wrap: anywhere; }
.server-runtime__notice--info { border-left-color: #3b82f6; }
.server-runtime__notice--success { border-left-color: #16a34a; }
.server-runtime__notice--warning { border-left-color: #d97706; }
.server-runtime__notice--danger { border-left-color: #dc2626; }
:root[data-theme="dark"] .server-runtime__state--success { color: #86efac; background: #12352a; }
:root[data-theme="dark"] .server-runtime__state--warning { color: #fcd34d; background: #3b2c18; }
:root[data-theme="dark"] .server-runtime__state--danger { color: #fca5a5; background: #3b222b; }
:root[data-theme="dark"] .server-runtime__state--info { color: #93c5fd; background: #18345a; }
@media (max-width: 1199.98px) { .server-runtime__grid { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 767.98px) {
    .server-runtime { padding: 1rem; }
    .server-runtime__details-grid { grid-template-columns: minmax(0, 1fr); }
    .server-runtime .btn, .server-runtime__details summary { min-height: 44px; }
    .server-runtime__details summary { display: list-item; padding-block: 0.6rem; }
    .server-runtime__header-actions { width: 100%; }
    .server-runtime__header-actions [data-runtime-service="all"] { flex: 1 1 100%; }
    .server-runtime__scheduler-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .server-runtime__service { padding: 0.7rem; gap: 0.5rem; }
}
@media (max-width: 399.98px) {
    .server-runtime__metrics { grid-template-columns: minmax(0, 1fr); }
    .server-runtime__scheduler-actions { grid-template-columns: minmax(0, 1fr); }
    .server-runtime__service { grid-template-columns: 1rem minmax(0, 1fr); }
    .server-runtime__service > button { grid-column: 2; justify-self: start; }
}
@media (prefers-reduced-motion: reduce) { .server-runtime .fa-spin { animation: none; } }
/* ==========================================================================
   BRI Nusantara - Modern Luxury Theme for Job Management
   ========================================================================== */

/* 1. Hero Header */
.job-management-hero {
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
.job-management-hero__glow {
    position: absolute;
    top: -50%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(113, 197, 232, 0.2) 0%, rgba(8, 87, 195, 0) 70%);
    pointer-events: none;
}
.job-management-hero__eyebrow {
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
.job-management-hero__title {
    color: #ffffff;
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    margin-bottom: 0.3rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}
.job-management-hero__text {
    color: #e2e8f0;
    font-size: 0.9rem;
    max-width: 680px;
    line-height: 1.5;
}
.job-management-hero__badge {
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
.status-pulse-dot {
    font-size: 0.65rem;
    animation: pulseGlow 2s infinite ease-in-out;
}
@keyframes pulseGlow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

/* 2. Main Executive Card */
.job-management-main-card {
    border-radius: 16px !important;
    overflow: hidden;
    border: 1px solid rgba(8, 87, 195, 0.1) !important;
    box-shadow: 0 10px 30px rgba(8, 87, 195, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03) !important;
    background: #ffffff;
}

/* 3. Toolbar & Telemetry */
.job-management-toolbar {
    display: block !important;
    width: 100% !important;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 1rem 1.5rem !important;
}
.job-management-toolbar > .row {
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}
.job-summary-grid {
    min-width: 0;
}
.job-summary-item {
    flex: 1 1 0;
    min-width: 0;
    padding: 0 0.5rem;
}
.job-summary-label {
    font-size: 0.68rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.07em !important;
    text-transform: uppercase !important;
    color: #64748b !important;
    white-space: nowrap !important;
    margin-bottom: 0.2rem !important;
}
.job-summary-val {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.01em;
}

.job-filter-controls {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    flex-wrap: nowrap !important;
    gap: 10px !important;
}

.job-status-select {
    width: 155px !important;
    min-width: 140px;
    height: 38px !important;
    min-height: 38px !important;
    max-height: 38px !important;
    border-radius: 9px !important;
    border: 1px solid #cbd5e1 !important;
    font-size: 0.82rem !important;
    font-weight: 600 !important;
    color: #1e293b !important;
    background-color: #ffffff !important;
    box-shadow: none !important;
    padding: 0.25rem 1.75rem 0.25rem 0.75rem !important;
    cursor: pointer;
    line-height: 1.4 !important;
}
.job-status-select:focus {
    border-color: #0857c3 !important;
    box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.15) !important;
}

.job-filter-search-group {
    width: 200px !important;
    height: 38px !important;
    min-height: 38px !important;
    margin-bottom: 0 !important;
}
.job-filter-search-group .input-group-prepend {
    height: 38px !important;
}
.job-filter-search-group .input-group-text {
    border-radius: 9px 0 0 9px !important;
    border-color: #cbd5e1 !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.82rem;
    padding: 0 0.75rem;
    background-color: #f8fafc !important;
    color: #94a3b8 !important;
}
.job-filter-search-group .form-control {
    border-radius: 0 9px 9px 0 !important;
    border-color: #cbd5e1 !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.82rem !important;
    padding: 0.25rem 0.75rem !important;
    color: #1e293b !important;
}
.job-filter-search-group .form-control:focus {
    border-color: #0857c3 !important;
    box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.15) !important;
}

.job-filter-switch-wrap {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    padding: 0 0.75rem;
    height: 38px;
    min-height: 38px;
    box-sizing: border-box;
}
.job-filter-switch-wrap .custom-switch {
    margin: 0 !important;
    padding-left: 2rem !important;
}
.job-filter-switch-wrap .custom-control-label {
    font-size: 0.78rem !important;
    font-weight: 700 !important;
    color: #475569 !important;
    user-select: none;
    cursor: pointer;
    line-height: 1.4 !important;
}

.custom-switch .custom-control-label::before {
    height: 1.15rem;
    width: 2rem;
    border-radius: 1rem;
}
.custom-switch .custom-control-label::after {
    width: calc(1.15rem - 4px);
    height: calc(1.15rem - 4px);
    border-radius: calc(1.15rem / 2);
}
.custom-switch .custom-control-input:checked ~ .custom-control-label::before {
    background-color: #0857c3;
    border-color: #0857c3;
}

.job-action-btn-group {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Buttons */
.job-btn-primary {
    border-radius: 9px !important;
    font-weight: 700;
    height: 38px;
    min-height: 38px;
    min-width: 38px;
    padding: 0 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0857c3 0%, #0284c7 100%);
    color: #ffffff;
    border: 0;
    box-shadow: 0 2px 8px rgba(8, 87, 195, 0.25);
    transition: all 0.2s ease;
}
.job-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(8, 87, 195, 0.35);
    color: #ffffff;
}
.job-btn-outline-danger {
    border-radius: 9px !important;
    font-weight: 700;
    height: 38px;
    min-height: 38px;
    min-width: 38px;
    padding: 0 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #fca5a5;
    color: #dc2626;
    background: #ffffff;
    transition: all 0.2s ease;
}
.job-btn-outline-danger:hover:not(:disabled) {
    background: #fef2f2;
    border-color: #dc2626;
    color: #b91c1c;
}
.job-btn-outline-danger:disabled {
    opacity: 0.45;
    border-color: #e2e8f0;
    color: #94a3b8;
}

#btn-job-refresh.job-btn-primary,
#btn-job-clear.job-btn-outline-danger {
    height: 38px !important;
    min-height: 38px !important;
    width: 38px !important;
    min-width: 38px !important;
    padding: 0 !important;
    border-radius: 9px !important;
}

#btn-job-delete-selected {
    height: 36px !important;
    min-height: 36px !important;
    white-space: nowrap !important;
    padding: 0 0.85rem !important;
}

/* Worker monitor */
.worker-monitor { padding: 1rem 1.25rem 1.25rem; }
.worker-monitor__section-title { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; margin: 1rem 0 0.5rem; }
.worker-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(125px, 1fr)); gap: 10px; }
.worker-metric { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.6rem 0.75rem; min-width: 0; }
.worker-metric__label { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
.worker-metric__value { font-size: 1.05rem; font-weight: 800; color: #0f172a; overflow-wrap: anywhere; }
.worker-metric__hint { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
.worker-metric--warning { background: #fffbeb; border-color: #fcd34d; }
.worker-metric--warning .worker-metric__value { color: #b45309; }
.worker-metric--danger { background: #fef2f2; border-color: #fca5a5; }
.worker-metric--danger .worker-metric__value { color: #b91c1c; }
.worker-restart-banner { border-radius: 10px; padding: 0.65rem 0.9rem; margin-bottom: 0.85rem; font-size: 0.86rem; font-weight: 600; border: 1px solid transparent; }
.worker-restart-banner--info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.worker-restart-banner--success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.worker-restart-banner--warning { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.worker-busy-list { margin: 0.35rem 0 0 1.4rem; padding: 0; font-weight: 500; font-size: 0.8rem; }
.worker-restart-banner__meta { font-size: 0.74rem; font-weight: 500; opacity: 0.8; margin-top: 2px; }
.worker-pool-table td { vertical-align: middle; }
.worker-pool-detail td { background: #f8fafc; border-top: 0 !important; padding-top: 0.25rem !important; }
.worker-chip, .worker-running-job { display: inline-flex; align-items: center; font-size: 0.74rem; border-radius: 999px; padding: 2px 9px; margin: 2px 6px 2px 0; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; white-space: nowrap; }
.worker-chip--busy { border-color: #86efac; background: #f0fdf4; color: #166534; }
.worker-chip--outdated { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.worker-running-job { border-color: #bfdbfe; background: #eff6ff; color: #1e40af; }
.worker-failed-jobs { display: grid; gap: 8px; }
.worker-failed-job { background: #ffffff; border: 1px solid #fee2e2; border-left: 3px solid #ef4444; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.8rem; min-width: 0; }
.worker-failed-job__head { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
.worker-failed-job__error { color: #7f1d1d; font-family: SFMono-Regular, Consolas, monospace; font-size: 0.74rem; margin-top: 3px; overflow-wrap: anywhere; }

/* 4. Active Cards */
.job-section-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
}
.job-active-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.4rem;
    height: 100%;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.job-active-card:hover {
    border-color: #93c5fd;
    box-shadow: 0 6px 20px rgba(8, 87, 195, 0.08);
    transform: translateY(-2px);
}
.job-active-card--snapshot {
    border-left: 4px solid #0857c3;
}
.job-active-card--delete {
    border-left: 4px solid #dc2626;
}
.job-active-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.85rem;
}
.job-active-card__title {
    font-weight: 800;
    color: #0f172a;
    font-size: 1rem;
    letter-spacing: -0.01em;
}
.job-active-card__sub {
    font-size: 0.82rem;
    color: #64748b;
    font-weight: 500;
}
.job-active-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.job-active-card__message {
    font-size: 0.88rem;
    color: #0857c3;
    font-weight: 600;
}
.job-active-card__meta {
    display: flex;
    gap: 1rem;
    font-size: 0.78rem;
    color: #64748b;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}
.job-active-card__footer {
    margin-top: 1.15rem;
    padding-top: 0.95rem;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.job-active-card__hint {
    font-size: 0.78rem;
    color: #94a3b8;
    font-weight: 500;
}
.job-active-card__actions {
    display: flex;
    gap: 0.5rem;
}
.job-active-card__action {
    border-radius: 8px !important;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0.35rem 0.85rem;
}

/* Status Badges */
.job-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.85rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.job-status-badge--info {
    background: #eff6ff;
    color: #0857c3;
    border: 1px solid #bfdbfe;
}
.job-status-badge--warning {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
}
.job-status-badge--success {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.job-status-badge--danger {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fca5a5;
}
.job-status-badge--dark {
    background: #f1f5f9;
    color: #0f172a;
    border: 1px solid #cbd5e1;
}
.job-status-badge--muted {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

/* Progress */
.job-progress {
    height: 7px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
    margin-top: 0.4rem;
}
.job-progress__bar {
    height: 100%;
    background: linear-gradient(90deg, #0857c3, #0284c7);
    transition: width 0.3s ease;
    border-radius: 999px;
}
.job-progress__meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.78rem;
    color: #64748b;
    margin-top: 0.25rem;
    font-weight: 600;
}

/* Tables & Controls */
.job-table-primary {
    font-weight: 700;
    color: #0f172a;
}
.job-table-secondary {
    font-size: 0.8rem;
    color: #64748b;
}
.job-table-actions {
    display: flex;
    gap: 0.4rem;
    justify-content: center;
}
.job-table-action {
    border-radius: 8px !important;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.3rem 0.75rem;
}

#btn-purge-queue-jobs { min-height: 32px; }
.job-row-check {
    width: 32px;
    height: 32px;
    margin: 0;
    vertical-align: middle;
    cursor: pointer;
}

.job-management-empty {
    padding: 2rem;
    border-radius: 14px;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    text-align: center;
    color: #64748b;
    font-size: 0.88rem;
    font-weight: 600;
}

.job-management-pagination__meta {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: none;
}
.job-management-pagination__actions {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.job-page-btn {
    min-width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-weight: 700;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.job-page-btn:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #93c5fd;
    color: #0857c3;
}
.job-page-btn.is-active {
    background: #0857c3;
    color: #ffffff;
    border-color: #0857c3;
    box-shadow: 0 2px 8px rgba(8, 87, 195, 0.25);
}

.raw-queue-table-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);
}
.job-history-tabs { display: inline-flex; gap: 0.25rem; padding: 0.25rem; border-radius: 10px; background: var(--app-surface-soft, #f1f5f9); border: 1px solid var(--app-line, #e2e8f0); margin: 0.35rem 0; }
.job-history-tab { display: inline-flex; align-items: center; gap: 0.4rem; border: 0; background: transparent; color: var(--app-muted, #526987); font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 8px; cursor: pointer; transition: background-color .15s ease, color .15s ease; }
.job-history-tab:hover { color: var(--app-ink, #0b1f3a); }
.job-history-tab:focus-visible { outline: 2px solid #0857c3; outline-offset: 2px; }
.job-history-tab.is-active { background: var(--app-surface, #fff); color: #0857c3; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12); }
.job-history-tab__count { min-width: 1.6rem; padding: 0.05rem 0.45rem; border-radius: 999px; background: var(--app-line, #e2e8f0); color: inherit; font-size: 0.72rem; font-variant-numeric: tabular-nums; text-align: center; }
.job-history-tab.is-active .job-history-tab__count { background: rgba(8, 87, 195, 0.12); }
:root[data-theme="dark"] .job-history-tab.is-active { color: #93c5fd; }
:root[data-theme="dark"] .job-history-tab.is-active .job-history-tab__count { background: rgba(147, 197, 253, 0.16); }
.job-active-card__title { overflow-wrap: anywhere; }
@media (prefers-reduced-motion: reduce) { .job-history-tab { transition: none; } }
.job-management-table thead th {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 0.95rem 1.25rem;
}
.job-management-table tbody td {
    padding: 0.85rem 1.25rem;
    border-top: 1px solid #f1f5f9;
    vertical-align: middle;
}

/* Notices */
.job-management-notice {
    padding: 1rem 1.35rem;
    border-radius: 12px;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.5;
    margin-bottom: 1.25rem;
}
.job-management-notice--info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #0857c3;
}
.job-management-notice--warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #d97706;
}
.job-management-notice--subtle {
    opacity: 0.85;
}

/* Mobile Responsive */
@media (max-width: 767.98px) {
    .job-page-heading {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.25rem;
        align-items: start !important;
    }

    .job-page-heading h2,
    .job-page-heading > span {
        min-width: 0;
        max-width: 100%;
    }

    .job-management-toolbar {
        padding: 0.85rem !important;
    }

    .job-summary-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
        padding-right: 0 !important;
        border-right: 0 !important;
    }

    .job-summary-grid > div {
        min-width: 0;
        padding: 0.45rem 0.35rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }

    .job-filter-panel {
        padding-left: 0 !important;
        margin-top: 0.75rem;
    }

    .job-filter-controls {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
        justify-content: stretch !important;
    }

    #job-filter-status,
    #job-filter-search {
        grid-column: 1 / -1;
        width: 100% !important;
        min-width: 0;
        margin: 0 !important;
    }

    .job-filter-search-group {
        grid-column: 1 / -1;
        width: 100% !important;
    }

    .job-filter-switch-wrap {
        grid-column: 1 / -1;
        display: flex;
        justify-content: space-around;
        width: 100%;
    }

    .job-action-btn-group {
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
        width: 100%;
    }

    .job-filter-controls .custom-control {
        min-width: 0;
        margin: 0 !important;
    }

    #btn-job-refresh,
    #btn-job-clear {
        width: 100% !important;
        min-height: 38px;
        margin: 0 !important;
    }

    #job-management-card > .card-body {
        padding: 0.85rem !important;
    }
}
</style>
@endsection
