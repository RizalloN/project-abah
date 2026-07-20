@extends('layouts.admin')

@section('title', 'Kelola Job')

@section('content')
<div class="container-fluid pt-3 pb-4">
    <div class="job-page-heading d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 font-weight-bold text-dark mb-0"><i class="fas fa-tasks text-primary mr-2"></i> Job Management</h2>
        <span class="text-muted small">Monitor realtime queue import & snapshot.</span>
    </div>

    <!-- Keep main wrapper and data attributes intact -->
    <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;" id="job-management-card"
        data-fetch-url="{{ route('job-management.data') }}"
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
                <div class="col-lg-5 d-flex justify-content-between pr-4 border-right job-summary-grid">
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Aktif</div>
                        <div class="h5 mb-0 text-primary font-weight-bold" id="summary-active">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Queued</div>
                        <div class="h5 mb-0 text-warning font-weight-bold" id="summary-queued">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Processing</div>
                        <div class="h5 mb-0 text-success font-weight-bold" id="summary-processing">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Hari Ini</div>
                        <div class="h5 mb-0 text-info font-weight-bold" id="summary-today">0</div>
                    </div>
                </div>
                <div class="col-lg-7 pl-4 job-filter-panel">
                    <div class="d-flex align-items-center justify-content-end flex-wrap gap-2 job-filter-controls">
                        <select id="job-filter-status" class="form-control form-control-sm" style="width: auto; border-radius: 6px;">
                            <option value="all">Semua Status</option>
                            <option value="queued">Queued</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="terminated">Terminated</option>
                            <option value="failed">Failed</option>
                            <option value="failed_partial">Gagal Sebagian</option>
                        </select>
                        <input type="text" id="job-filter-search" class="form-control form-control-sm mx-2" placeholder="Cari job..." style="width: 180px; border-radius: 6px;">
                        
                        <div class="custom-control custom-checkbox mr-3 mt-1">
                            <input type="checkbox" class="custom-control-input" id="job-filter-active-only">
                            <label class="custom-control-label font-weight-bold text-muted" style="font-size: 0.8rem;" for="job-filter-active-only">Aktif</label>
                        </div>
                        <div class="custom-control custom-checkbox mr-3 mt-1">
                            <input type="checkbox" class="custom-control-input" id="job-auto-refresh" checked>
                            <label class="custom-control-label font-weight-bold text-muted" style="font-size: 0.8rem;" for="job-auto-refresh">Auto</label>
                        </div>

                        <button type="button" id="btn-job-refresh" class="btn btn-sm btn-primary" style="border-radius: 6px;"><i class="fas fa-sync-alt"></i></button>
                        <button type="button" id="btn-job-clear" class="btn btn-sm btn-outline-danger ml-1" style="border-radius: 6px;"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4 bg-light">
            <div id="job-management-notice" class="alert alert-info d-none mb-4 shadow-sm border-0" style="border-radius: 8px;"></div>
            <div id="job-management-queue-health" class="alert alert-warning d-none mb-4 shadow-sm border-0" style="border-radius: 8px;"></div>

            <!-- Active & Snapshot Grids -->
            <div class="row">
                <!-- Import Jobs -->
                <div class="col-12 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-hourglass-half text-success mr-2"></i> Import Jobs Aktif</h6>
                        <span class="badge badge-success badge-pill px-3 py-1" id="active-job-count-label">0</span>
                    </div>
                    <div id="active-jobs-grid" class="row"></div>
                </div>
            </div>
            
            <div class="row">
                <!-- Snapshot Jobs -->
                <div class="col-lg-6 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-clone text-primary mr-2"></i> Snapshot Jobs Aktif</h6>
                        <span class="badge badge-primary badge-pill px-3 py-1" id="snapshot-job-count-label">0</span>
                    </div>
                    <div id="snapshot-jobs-grid" class="row"></div>
                </div>

                <!-- Managed Delete Jobs -->
                <div class="col-lg-6 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-trash-alt text-danger mr-2"></i> Delete Jobs Aktif</h6>
                        <span class="badge badge-danger badge-pill px-3 py-1" id="managed-delete-job-count-label">0</span>
                    </div>
                    <div id="managed-delete-jobs-grid" class="row"></div>
                </div>
            </div>

            <!-- Raw Queue -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-database text-warning mr-2"></i> Queue Raw</h6>
                    <div>
                        <span class="badge badge-warning badge-pill px-3 py-1 mr-2" id="raw-queue-job-count-label">0</span>
                        <button type="button" id="btn-purge-queue-jobs" class="btn btn-sm py-1 btn-outline-danger" style="border-radius: 6px;"><i class="fas fa-broom mr-1"></i>Purge Pending</button>
                    </div>
                </div>
                <div id="raw-queue-jobs-grid" class="row"></div>
            </div>

            <!-- History Table -->
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap">
                    <h6 class="font-weight-bold mb-0"><i class="fas fa-history text-secondary mr-2"></i> Riwayat Jobs</h6>
                    <div class="d-flex align-items-center">
                        <span class="text-muted small mr-3" id="job-pagination-meta">0 job</span>
                        <div class="custom-control custom-checkbox d-inline-block">
                            <input type="checkbox" class="custom-control-input" id="job-select-all">
                            <label class="custom-control-label small text-muted font-weight-bold mt-1" for="job-select-all">Pilih Semua</label>
                        </div>
                        <span class="badge badge-secondary mx-3 px-2 py-1" id="job-selected-count">0 terpilih</span>
                        <button type="button" id="btn-job-delete-selected" class="btn btn-sm btn-outline-danger" style="border-radius: 6px;" disabled><i class="fas fa-trash-alt"></i> Hapus</button>
                    </div>
                </div>
                <div class="table-responsive bg-white">
                    <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="text-center border-top-0 border-bottom-0" style="width: 50px;"><i class="far fa-check-square"></i></th>
                                <th class="border-top-0 border-bottom-0">ID</th>
                                <th class="border-top-0 border-bottom-0">Report</th>
                                <th class="border-top-0 border-bottom-0">File</th>
                                <th class="border-top-0 border-bottom-0">Status</th>
                                <th class="border-top-0 border-bottom-0" style="width: 200px;">Progress</th>
                                <th class="border-top-0 border-bottom-0">Updated</th>
                                <th class="text-center border-top-0 border-bottom-0">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="job-table-body">
                            <tr><td colspan="8" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="job-pagination" class="card-footer bg-white d-none py-3 px-4"></div>
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
    const snapshotGrid = document.getElementById('snapshot-jobs-grid'); // Keep this ID
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
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    let currentPage = 1;
    let refreshTimer = null;
    let searchTimer = null;
    let loading = false;
    let currentJobs = [];
    const selectedJobIds = new Set();

    function templateUrl(template, value) { return String(template || '').replace('__JOB_ID__', encodeURIComponent(value || '')); }
    function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
    function showNotice(message, tone = 'info') { notice.className = `job-management-notice job-management-notice--${tone}`; notice.textContent = message; notice.classList.remove('d-none'); }
    function hideNotice() { notice.classList.add('d-none'); notice.textContent = ''; }
    function renderQueueHealth(payload) { if (!payload || !payload.message) { queueHealth.classList.add('d-none'); queueHealth.textContent = ''; return; } queueHealth.className = `job-management-notice job-management-notice--${escapeHtml(payload.tone || 'info')}`; queueHealth.textContent = payload.message; queueHealth.classList.remove('d-none'); if ((payload.status || '') === 'ok') { queueHealth.classList.add('job-management-notice--subtle'); } else { queueHealth.classList.remove('job-management-notice--subtle'); } }
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
            rawQueueGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-database"></i>Tidak ada queue job yang terdeteksi di tabel <code>jobs</code>.</div></div>`;
            return;
        }
        rawQueueGrid.innerHTML = `<div class="col-12"><div class="raw-queue-table-wrap"><table class="table table-hover mb-0 job-management-table"><thead><tr class="job-management-table__header"><th>ID</th><th>Queue</th><th>Job Class</th><th>Parameter</th><th>Status</th><th>Usia</th><th>Dibuat</th><th class="text-center">Aksi</th></tr></thead><tbody>${items.map((job) => `<tr><td class="font-weight-bold" style="color:#b45309;">#${job.id}</td><td><span class="badge badge-light" style="font-size:.78rem;font-weight:700;">${escapeHtml(job.queue)}</span></td><td><div class="job-table-primary" style="font-size:.88rem;">${escapeHtml(job.class_name)}</div></td><td><div class="job-table-secondary" style="font-size:.8rem;">${escapeHtml(job.job_data_label || '-')}</div></td><td><span class="job-status-badge job-status-badge--${escapeHtml(job.status_tone)}">${escapeHtml(job.status_label)}</span></td><td><div class="job-table-secondary">${escapeHtml(job.age_label || '-')}</div></td><td><div class="job-table-secondary">${escapeHtml(job.created_at_label || '-')}</div></td><td class="text-center"><div class="job-table-actions">${forceRunQueueJobButton(job)}${deleteQueueJobButton(job)}</div></td></tr>`).join('')}</tbody></table></div></div>`;
    }
    function rowCheckbox(job) { if (!job.can_delete) return '<span class="text-muted small">-</span>'; const checked = selectedJobIds.has(String(job.id)) ? 'checked' : ''; return `<input type="checkbox" class="job-row-check" data-job-id="${job.id}" ${checked}>`; }
    function syncSelectionState() { const selectableIds = currentJobs.filter((job) => job.can_delete).map((job) => String(job.id)); const selectedOnPage = selectableIds.filter((id) => selectedJobIds.has(id)); selectAll.checked = selectableIds.length > 0 && selectedOnPage.length === selectableIds.length; selectAll.indeterminate = selectedOnPage.length > 0 && selectedOnPage.length < selectableIds.length; selectedCount.textContent = `${selectedJobIds.size} job dipilih`; btnDeleteSelected.disabled = selectedJobIds.size === 0; }
    function renderSummary(summary) { document.getElementById('summary-active').textContent = Number(summary.active_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-queued').textContent = Number(summary.queued_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-processing').textContent = Number(summary.processing_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-today').textContent = Number(summary.today_jobs || 0).toLocaleString('id-ID'); }
    function renderSnapshotJobs(items, summary = {}) { const activeCount = Number(summary.active_jobs || 0); snapshotJobCountLabel.textContent = `${activeCount.toLocaleString('id-ID')} job snapshot aktif`; if (!Array.isArray(items) || items.length === 0) { snapshotGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-clone"></i>Belum ada rebuild snapshot yang sedang antre atau berjalan.</div></div>`; return; } snapshotGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card job-active-card--snapshot"><div class="job-active-card__header"><div><div class="job-active-card__title">${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)} • ${escapeHtml(job.id)}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta">${snapshotDetailMarkup(job)}<span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span>${job.queue_name ? `<span><i class="fas fa-server mr-1"></i>${escapeHtml(job.queue_name)}</span>` : ''}</div></div><div class="job-active-card__footer"><span class="job-active-card__hint">${job.status === 'failed' ? 'Snapshot stale ditandai gagal otomatis agar progress tidak menggantung.' : 'Snapshot rebuild dipantau dari cache state report management.'}</span><div class="job-active-card__actions">${forceStartButton(job, false, true)}</div></div></div></div>`).join(''); }
    function renderActiveJobs(items) { activeJobCountLabel.textContent = `${Number(items.length || 0).toLocaleString('id-ID')} job aktif`; if (!Array.isArray(items) || items.length === 0) { activeGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-hourglass-half"></i>Belum ada job dengan status queued atau processing.</div></div>`; return; } activeGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card"><div class="job-active-card__header"><div><div class="job-active-card__title">#${job.id} • ${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)}${job.table_name ? ' • ' + escapeHtml(job.table_name) : ''}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta"><span><i class="far fa-user mr-1"></i>${escapeHtml(job.created_by_name || 'System')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span><span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span></div></div><div class="job-active-card__footer">${job.termination_requested ? '<span class="job-active-card__hint">Permintaan terminate sudah dikirim ke worker.</span>' : '<span class="job-active-card__hint">Job aktif masih dapat dihentikan dari halaman ini.</span>'}<div class="job-active-card__actions">${forceStartButton(job)}${terminateButton(job)}</div></div></div></div>`).join(''); }
    function renderManagedDeleteJobs(items, summary = {}) { const activeCount = Number(summary.active_jobs || 0); managedDeleteJobCountLabel.textContent = `${activeCount.toLocaleString('id-ID')} job delete aktif`; if (!Array.isArray(items) || items.length === 0) { managedDeleteGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-trash-alt"></i>Belum ada progress delete yang sedang antre atau berjalan.</div></div>`; return; } managedDeleteGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card job-active-card--delete"><div class="job-active-card__header"><div><div class="job-active-card__title">#${escapeHtml(job.id)} • ${escapeHtml(job.report_name || 'Managed Delete')}</div><div class="job-active-card__sub">${escapeHtml(job.table_name || '-')} • ${escapeHtml(job.file_name || '-')}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta"><span><i class="fas fa-layer-group mr-1"></i>${escapeHtml(job.stage_label || job.phase || '-')}</span><span><i class="fas fa-stream mr-1"></i>${Number(job.scope_count || 0).toLocaleString('id-ID')} scope</span><span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span>${job.queue_name ? `<span><i class="fas fa-server mr-1"></i>${escapeHtml(job.queue_name)}${job.queue_job_id ? ' #' + escapeHtml(job.queue_job_id) : ''}</span>` : ''}</div></div><div class="job-active-card__footer">${job.can_cancel ? (job.termination_requested ? '<span class="job-active-card__hint">Force stop sudah dikirim.</span>' : '<span class="job-active-card__hint">Delete aktif masih bisa dihentikan dari halaman ini.</span>') : '<span class="job-active-card__hint">Delete ini sudah terminal atau tidak lagi di antrean.</span>'}<div class="job-active-card__actions">${forceStopDeleteButton(job)}${cancelDeleteButton(job)}</div></div></div></div>`).join(''); }
    function renderTable(items) { currentJobs = Array.isArray(items) ? items : []; if (currentJobs.length === 0) { tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">Tidak ada data job untuk filter ini.</td></tr>'; syncSelectionState(); return; } tableBody.innerHTML = currentJobs.map((job) => `<tr><td class="text-center job-col-check">${rowCheckbox(job)}</td><td class="font-weight-bold" style="color: var(--jm-primary-color);">#${job.id}</td><td><div class="job-table-primary">${escapeHtml(job.report_name)}</div><div class="job-table-secondary">${escapeHtml(job.table_name || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.file_name)}</div><div class="job-table-secondary">By ${escapeHtml(job.created_by_name || 'System')}</div></td><td>${statusBadge(job)}</td><td>${progressMarkup(job)}<div class="job-table-secondary mt-1">${escapeHtml(job.message || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.updated_at_label || '-')}</div><div class="job-table-secondary">Durasi ${escapeHtml(job.duration_label || '-')}</div></td><td class="text-center"><div class="job-table-actions">${forceStartButton(job, true)}${terminateButton(job, true)}${deleteButton(job, true)}</div></td></tr>`).join(''); syncSelectionState(); }
    function renderPagination(meta) { const total = Number(meta.total || 0); paginationMeta.textContent = `${total.toLocaleString('id-ID')} job`; if (!meta.last_page || meta.last_page <= 1) { pagination.classList.add('d-none'); pagination.innerHTML = ''; return; } const buttons = []; for (let page = 1; page <= meta.last_page; page++) { buttons.push(`<button type="button" class="job-page-btn ${page === meta.current_page ? 'is-active' : ''}" data-page="${page}">${page}</button>`); } pagination.innerHTML = `<div class="job-management-pagination__meta">Menampilkan ${meta.from || 0}-${meta.to || 0} dari ${total.toLocaleString('id-ID')} job</div><div class="job-management-pagination__actions">${buttons.join('')}</div>`; pagination.classList.remove('d-none'); }
    async function fetchData(page = 1) { if (loading) return; loading = true; currentPage = page; try { const params = new URLSearchParams({ page: String(page), status: filterStatus.value || 'all', search: filterSearch.value || '', active_only: activeOnly.checked ? '1' : '0' }); const response = await fetch(`${card.dataset.fetchUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal memuat data job.'); hideNotice(); renderQueueHealth(payload.queue_health || null); renderSummary(payload.summary || {}); renderSnapshotJobs(payload.snapshot_jobs || [], payload.snapshot_summary || {}); renderRawQueueJobs(payload.raw_queue_jobs || [], payload.raw_queue_summary || {}); renderManagedDeleteJobs(payload.managed_delete_jobs || [], payload.managed_delete_summary || {}); renderActiveJobs(payload.active_jobs || []); renderTable(payload.jobs || []); renderPagination(payload.pagination || {}); } catch (error) { showNotice(error.message || 'Gagal memuat data job.', 'warning'); } finally { loading = false; } }
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
    function startAutoRefresh() { if (refreshTimer) clearInterval(refreshTimer); if (!autoRefresh.checked) return; refreshTimer = setInterval(() => fetchData(currentPage), 5000); }

    btnRefresh.addEventListener('click', () => fetchData(currentPage));
    btnPurgeQueueJobs.addEventListener('click', async () => { try { await purgeQueueJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Purge Gagal', text: error.message || 'Gagal purge queue jobs.' }); } });
    btnClear.addEventListener('click', async () => { try { await clearJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Clear Gagal', text: error.message || 'Gagal clear jobs.' }); } });
    btnDeleteSelected.addEventListener('click', async () => { try { await bulkDeleteJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Bulk Delete Gagal', text: error.message || 'Gagal menghapus job terpilih.' }); } });
    filterStatus.addEventListener('change', () => fetchData(1));
    activeOnly.addEventListener('change', () => fetchData(1));
    filterSearch.addEventListener('input', function () { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(() => fetchData(1), 350); });
    autoRefresh.addEventListener('change', startAutoRefresh);
    selectAll.addEventListener('change', function () { currentJobs.forEach((job) => { if (!job.can_delete) return; if (selectAll.checked) selectedJobIds.add(String(job.id)); else selectedJobIds.delete(String(job.id)); }); syncSelectionState(); tableBody.querySelectorAll('.job-row-check').forEach((checkbox) => { checkbox.checked = selectAll.checked; }); });
    document.addEventListener('change', function (event) { const checkbox = event.target.closest('.job-row-check'); if (!checkbox) return; const jobId = String(checkbox.dataset.jobId || ''); if (jobId === '') return; if (checkbox.checked) selectedJobIds.add(jobId); else selectedJobIds.delete(jobId); syncSelectionState(); });
    document.addEventListener('click', async function (event) { const forceStartTarget = event.target.closest('[data-action="force-start"]'); if (forceStartTarget) { try { await forceStartJob(forceStartTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Start Gagal', text: error.message || 'Gagal force start job.' }); } return; } const forceStartSnapshotTarget = event.target.closest('[data-action="force-start-snapshot"]'); if (forceStartSnapshotTarget) { try { await forceStartSnapshot(forceStartSnapshotTarget.dataset.rebuildId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Start Gagal', text: error.message || 'Gagal force start snapshot.' }); } return; } const terminateTarget = event.target.closest('[data-action="terminate"]'); if (terminateTarget) { try { await terminateJob(terminateTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Terminate Gagal', text: error.message || 'Gagal terminate job.' }); } return; } const forceStopDeleteTarget = event.target.closest('[data-action="force-stop-delete"]'); if (forceStopDeleteTarget) { try { await forceStopManagedDelete(forceStopDeleteTarget.dataset.deleteId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Stop Gagal', text: error.message || 'Gagal force stop delete.' }); } return; } const cancelDeleteTarget = event.target.closest('[data-action="cancel-delete"]'); if (cancelDeleteTarget) { try { await cancelManagedDelete(cancelDeleteTarget.dataset.deleteId); } catch (error) { Swal.fire({ icon: 'error', title: 'Cancel Delete Gagal', text: error.message || 'Gagal cancel delete.' }); } return; } const deleteTarget = event.target.closest('[data-action="delete"]'); if (deleteTarget) { try { await deleteJob(deleteTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Delete Gagal', text: error.message || 'Gagal menghapus job.' }); } return; } const forceRunQueueJobTarget = event.target.closest('[data-action="force-run-queue-job"]'); if (forceRunQueueJobTarget) { try { await forceRunQueueJob(forceRunQueueJobTarget.dataset.queueJobId, forceRunQueueJobTarget.closest('tr')?.querySelector('.job-table-primary')?.textContent?.trim()); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Run Gagal', text: error.message || 'Gagal force run queue job.' }); } return; } const deleteQueueJobTarget = event.target.closest('[data-action="delete-queue-job"]'); if (deleteQueueJobTarget) { try { await deleteQueueJob(deleteQueueJobTarget.dataset.queueJobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Delete Queue Job Gagal', text: error.message || 'Gagal menghapus queue job.' }); } return; } const pageTarget = event.target.closest('[data-page]'); if (pageTarget) fetchData(Number(pageTarget.dataset.page || 1)); });

    fetchData();
    startAutoRefresh();
});
</script>
@endsection

@section('styles')
<style>
/* Simplified UI CSS matching JS logic but modern */
.job-active-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; height: 100%; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
.job-active-card__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
.job-active-card__title { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
.job-active-card__sub { font-size: 0.8rem; color: #64748b; }
.job-active-card__body { display: flex; flex-direction: column; gap: 0.5rem; }
.job-active-card__message { font-size: 0.85rem; color: #3b82f6; font-weight: 600; }
.job-active-card__meta { display: flex; gap: 1rem; font-size: 0.75rem; color: #64748b; margin-top: 0.5rem; flex-wrap: wrap; }
.job-active-card__footer { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.job-active-card__hint { font-size: 0.75rem; color: #94a3b8; }
.job-active-card__actions { display: flex; gap: 0.5rem; }

.job-status-badge { display: inline-flex; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.job-status-badge--info { background: #eff6ff; color: #2563eb; }
.job-status-badge--warning { background: #fffbeb; color: #d97706; }
.job-status-badge--success { background: #f0fdf4; color: #16a34a; }
.job-status-badge--danger { background: #fef2f2; color: #ef4444; }
.job-status-badge--dark { background: #f1f5f9; color: #1e293b; }
.job-status-badge--muted { background: #f1f5f9; color: #64748b; }

.job-progress { height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: 0.5rem; }
.job-progress__bar { height: 100%; background: #3b82f6; transition: width 0.3s ease; }
.job-progress__meta { display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }

.job-table-primary { font-weight: 600; color: #1e293b; }
.job-table-secondary { font-size: 0.8rem; color: #64748b; }
.job-table-actions { display: flex; gap: 0.4rem; justify-content: center; }
.job-table-action { border-radius: 6px; font-size: 0.75rem; font-weight: 600; }

.job-management-empty { padding: 1.5rem; border-radius: 12px; background: #fff; border: 1px dashed #cbd5e1; text-align: center; color: #64748b; font-size: 0.85rem; font-weight: 600; }

.job-management-pagination__meta { font-size: 0.85rem; color: #64748b; font-weight: 600; margin-bottom: 0.5rem; display: none; }
.job-management-pagination__actions { display: flex; gap: 0.25rem; flex-wrap: wrap; }
.job-page-btn { min-width: 32px; height: 32px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #475569; font-weight: 600; font-size: 0.85rem; }
.job-page-btn.is-active { background: #2563eb; color: #fff; border-color: #2563eb; }

.raw-queue-table-wrap { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; }

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
        border-radius: 6px;
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

    .job-filter-controls .custom-control {
        min-width: 0;
        margin: 0 !important;
    }

    #btn-job-refresh,
    #btn-job-clear {
        width: 100%;
        min-height: 38px;
        margin: 0 !important;
    }

    #job-management-card > .card-body {
        padding: 0.85rem !important;
    }
}
</style>
@endsection
