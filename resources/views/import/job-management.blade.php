@extends('layouts.admin')

@section('title', 'Kelola Job')

@section('content')
<div class="job-management-hero mb-4">
    <div class="job-management-hero__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="pr-3">
            <span class="job-management-hero__eyebrow">Manajemen Job</span>
            <div class="job-management-hero__title"><i class="fas fa-tasks mr-2"></i> Monitor Job Import & Snapshot</div>
            <p class="job-management-hero__text mb-0">Pantau queue import dan rebuild snapshot report dari satu halaman dengan progress realtime.</p>
        </div>
        <div class="job-management-hero__badge mt-3 mt-md-0"><i class="fas fa-wave-square mr-2"></i> Realtime Monitor</div>
    </div>
</div>

<div class="card border-0 job-management-card" id="job-management-card"
     data-fetch-url="{{ route('job-management.data') }}"
     data-clear-url="{{ route('job-management.clear') }}"
     data-bulk-delete-url="{{ route('job-management.bulk-destroy') }}"
     data-destroy-url-template="{{ route('job-management.destroy', ['jobId' => '__JOB_ID__']) }}"
     data-force-start-url-template="{{ route('job-management.force-start', ['jobId' => '__JOB_ID__']) }}"
     data-force-start-snapshot-url-template="{{ route('job-management.snapshot.force-start', ['rebuildId' => '__REBUILD_ID__']) }}"
     data-terminate-url-template="{{ route('job-management.terminate', ['jobId' => '__JOB_ID__']) }}"
     style="--jm-primary-color: #2563eb; --jm-primary-color-light: #eff6ff; --jm-primary-color-border: #dbeafe;">
    <div class="card-header bg-transparent border-0 job-management-card__header">
        <span class="job-management-card__eyebrow" style="background: var(--jm-primary-color-light); color: var(--jm-primary-color);">Kontrol Queue</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-server mr-2" style="color: var(--jm-primary-color);"></i> Status Import Queue
        </h5>
        <p class="job-management-card__subtitle mb-0">Halaman ini memantau job import pada tabel <code>import_jobs</code> dan proses rebuild snapshot yang disimpan pada cache state terpisah.</p>
    </div>
    <div class="card-body job-management-card__body">
        <div class="job-management-toolbar mb-4">
            <div class="row">
                <div class="col-lg-4 mb-lg-0">
                    <label class="job-management-label" for="job-filter-status">Filter Status</label>
                    <select id="job-filter-status" class="form-control job-management-select">
                        <option value="all">Semua Status</option>
                        <option value="queued">Queued</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="terminated">Terminated</option>
                        <option value="failed">Failed</option>
                        <option value="failed_partial">Gagal Sebagian</option>
                    </select>
                </div>
                <div class="col-lg-5 mb-lg-0 mt-3 mt-lg-0">
                    <label class="job-management-label" for="job-filter-search">Cari Job</label>
                    <input type="text" id="job-filter-search" class="form-control job-management-input" placeholder="Cari file, report, user, atau ID job...">
                </div>
                <div class="col-lg-3 mt-3 mt-lg-0">
                    <label class="job-management-label d-block">Aksi</label>
                    <div class="job-management-toolbar__actions">
                        <button type="button" id="btn-job-refresh" class="btn job-management-btn job-management-btn--primary">
                            <i class="fas fa-sync-alt mr-2"></i> Refresh
                        </button>
                        <button type="button" id="btn-job-clear" class="btn job-management-btn job-management-btn--danger-outline">
                            <i class="fas fa-trash-alt mr-2"></i> Clear Jobs
                        </button>
                        <div class="custom-control custom-switch job-management-switch">
                            <input type="checkbox" class="custom-control-input" id="job-auto-refresh" checked>
                            <label class="custom-control-label" for="job-auto-refresh">Auto Refresh</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="job-management-toolbar__sub mt-3">
                <div class="custom-control custom-switch job-management-switch">
                    <input type="checkbox" class="custom-control-input" id="job-filter-active-only">
                    <label class="custom-control-label" for="job-filter-active-only">Hanya Tampilkan Job Aktif</label>
                </div>
                <div class="job-management-toolbar__hint">Saat aktif, tabel hanya menampilkan job dengan status queued atau processing.</div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3 mb-md-0"><div class="job-management-stat"><div class="job-management-stat__icon" style="--icon-bg: #eff6ff; --icon-color: #2563eb;"><i class="fas fa-tasks"></i></div><div class="job-management-stat__body"><small>Job Aktif</small><strong id="summary-active">0</strong></div></div></div>
            <div class="col-md-3 mb-3 mb-md-0"><div class="job-management-stat"><div class="job-management-stat__icon" style="--icon-bg: #fffbeb; --icon-color: #f59e0b;"><i class="far fa-clock"></i></div><div class="job-management-stat__body"><small>Queued</small><strong id="summary-queued">0</strong></div></div></div>
            <div class="col-md-3 mb-3 mb-md-0"><div class="job-management-stat"><div class="job-management-stat__icon" style="--icon-bg: #ecfdf5; --icon-color: #10b981;"><i class="fas fa-cogs"></i></div><div class="job-management-stat__body"><small>Processing</small><strong id="summary-processing">0</strong></div></div></div>
            <div class="col-md-3"><div class="job-management-stat"><div class="job-management-stat__icon" style="--icon-bg: #f0f9ff; --icon-color: #0ea5e9;"><i class="far fa-calendar-check"></i></div><div class="job-management-stat__body"><small>Dibuat Hari Ini</small><strong id="summary-today">0</strong></div></div></div>
        </div>

        <div id="job-management-notice" class="job-management-notice d-none mb-3"></div>
        <div id="job-management-queue-health" class="job-management-notice d-none mb-3"></div>

        <div class="job-management-section">
            <div class="job-management-section__header">
                <div>
                    <div class="job-management-section__eyebrow" style="background: rgba(139, 92, 246, 0.1); color: #7c3aed;">Snapshot</div>
                    <div class="job-management-section__title">Snapshot Jobs</div>
                </div>
                <div class="job-management-section__meta" id="snapshot-job-count-label">0 job snapshot aktif</div>
            </div>
            <div id="snapshot-jobs-grid" class="row">
                <div class="col-12"><div class="job-management-empty"><i class="fas fa-clone"></i>Belum ada rebuild snapshot yang sedang antre atau berjalan.</div></div>
            </div>
        </div>

        <div class="job-management-section">
            <div class="job-management-section__header">
                <div>
                    <div class="job-management-section__eyebrow" style="background: rgba(16, 185, 129, 0.1); color: #059669;">Sedang Berjalan</div>
                    <div class="job-management-section__title">Active Jobs</div>
                </div>
                <div class="job-management-section__meta" id="active-job-count-label">0 job aktif</div>
            </div>
            <div id="active-jobs-grid" class="row">
                <div class="col-12"><div class="job-management-empty"><i class="fas fa-hourglass-half"></i>Belum ada job aktif.</div></div>
            </div>
        </div>

        <div class="job-management-section" style="background: #fff;">
            <div class="job-management-section__header">
                <div>
                    <div class="job-management-section__eyebrow" style="background: rgba(100, 116, 139, 0.1); color: #475569;">Riwayat</div>
                    <div class="job-management-section__title">Recent Jobs</div>
                </div>
                <div class="job-management-section__meta" id="job-pagination-meta">0 job</div>
            </div>

            <div class="job-management-bulkbar">
                <div class="form-check m-0">
                    <input class="form-check-input" type="checkbox" id="job-select-all">
                    <label class="form-check-label font-weight-bold" for="job-select-all">Pilih Semua di Halaman</label>
                </div>
                <div class="job-management-bulkbar__actions">
                    <div id="job-selected-count" class="job-management-bulkbar__hint">0 job dipilih</div>
                    <button type="button" id="btn-job-delete-selected" class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="fas fa-trash-alt mr-1"></i> Hapus
                    </button>
                </div>
            </div>

            <div class="job-management-table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 job-management-table">
                        <thead>
                            <tr class="job-management-table__header">
                                <th class="text-center job-col-check"><i class="far fa-check-square"></i></th>
                                <th>ID</th>
                                <th>Report</th>
                                <th>File</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Updated</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="job-table-body">
                            <tr><td colspan="8" class="text-center text-muted py-5">Memuat data job...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="job-pagination" class="job-management-pagination d-none"></div>
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
    function rowCheckbox(job) { if (!job.can_delete) return '<span class="text-muted small">-</span>'; const checked = selectedJobIds.has(String(job.id)) ? 'checked' : ''; return `<input type="checkbox" class="job-row-check" data-job-id="${job.id}" ${checked}>`; }
    function syncSelectionState() { const selectableIds = currentJobs.filter((job) => job.can_delete).map((job) => String(job.id)); const selectedOnPage = selectableIds.filter((id) => selectedJobIds.has(id)); selectAll.checked = selectableIds.length > 0 && selectedOnPage.length === selectableIds.length; selectAll.indeterminate = selectedOnPage.length > 0 && selectedOnPage.length < selectableIds.length; selectedCount.textContent = `${selectedJobIds.size} job dipilih`; btnDeleteSelected.disabled = selectedJobIds.size === 0; }
    function renderSummary(summary) { document.getElementById('summary-active').textContent = Number(summary.active_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-queued').textContent = Number(summary.queued_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-processing').textContent = Number(summary.processing_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-today').textContent = Number(summary.today_jobs || 0).toLocaleString('id-ID'); }
    function renderSnapshotJobs(items, summary = {}) { const activeCount = Number(summary.active_jobs || 0); snapshotJobCountLabel.textContent = `${activeCount.toLocaleString('id-ID')} job snapshot aktif`; if (!Array.isArray(items) || items.length === 0) { snapshotGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-clone"></i>Belum ada rebuild snapshot yang sedang antre atau berjalan.</div></div>`; return; } snapshotGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card job-active-card--snapshot"><div class="job-active-card__header"><div><div class="job-active-card__title">${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)} • ${escapeHtml(job.id)}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta">${snapshotDetailMarkup(job)}<span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span>${job.queue_name ? `<span><i class="fas fa-server mr-1"></i>${escapeHtml(job.queue_name)}</span>` : ''}</div></div><div class="job-active-card__footer"><span class="job-active-card__hint">${job.status === 'failed' ? 'Snapshot stale ditandai gagal otomatis agar progress tidak menggantung.' : 'Snapshot rebuild dipantau dari cache state report management.'}</span><div class="job-active-card__actions">${forceStartButton(job, false, true)}</div></div></div></div>`).join(''); }
    function renderActiveJobs(items) { activeJobCountLabel.textContent = `${Number(items.length || 0).toLocaleString('id-ID')} job aktif`; if (!Array.isArray(items) || items.length === 0) { activeGrid.innerHTML = `<div class="col-12"><div class="job-management-empty"><i class="fas fa-hourglass-half"></i>Belum ada job dengan status queued atau processing.</div></div>`; return; } activeGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card"><div class="job-active-card__header"><div><div class="job-active-card__title">#${job.id} • ${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)}${job.table_name ? ' • ' + escapeHtml(job.table_name) : ''}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta"><span><i class="far fa-user mr-1"></i>${escapeHtml(job.created_by_name || 'System')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span><span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span></div></div><div class="job-active-card__footer">${job.termination_requested ? '<span class="job-active-card__hint">Permintaan terminate sudah dikirim ke worker.</span>' : '<span class="job-active-card__hint">Job aktif masih dapat dihentikan dari halaman ini.</span>'}<div class="job-active-card__actions">${forceStartButton(job)}${terminateButton(job)}</div></div></div></div>`).join(''); }
    function renderTable(items) { currentJobs = Array.isArray(items) ? items : []; if (currentJobs.length === 0) { tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">Tidak ada data job untuk filter ini.</td></tr>'; syncSelectionState(); return; } tableBody.innerHTML = currentJobs.map((job) => `<tr><td class="text-center job-col-check">${rowCheckbox(job)}</td><td class="font-weight-bold" style="color: var(--jm-primary-color);">#${job.id}</td><td><div class="job-table-primary">${escapeHtml(job.report_name)}</div><div class="job-table-secondary">${escapeHtml(job.table_name || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.file_name)}</div><div class="job-table-secondary">By ${escapeHtml(job.created_by_name || 'System')}</div></td><td>${statusBadge(job)}</td><td>${progressMarkup(job)}<div class="job-table-secondary mt-1">${escapeHtml(job.message || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.updated_at_label || '-')}</div><div class="job-table-secondary">Durasi ${escapeHtml(job.duration_label || '-')}</div></td><td class="text-center"><div class="job-table-actions">${forceStartButton(job, true)}${terminateButton(job, true)}${deleteButton(job, true)}</div></td></tr>`).join(''); syncSelectionState(); }
    function renderPagination(meta) { const total = Number(meta.total || 0); paginationMeta.textContent = `${total.toLocaleString('id-ID')} job`; if (!meta.last_page || meta.last_page <= 1) { pagination.classList.add('d-none'); pagination.innerHTML = ''; return; } const buttons = []; for (let page = 1; page <= meta.last_page; page++) { buttons.push(`<button type="button" class="job-page-btn ${page === meta.current_page ? 'is-active' : ''}" data-page="${page}">${page}</button>`); } pagination.innerHTML = `<div class="job-management-pagination__meta">Menampilkan ${meta.from || 0}-${meta.to || 0} dari ${total.toLocaleString('id-ID')} job</div><div class="job-management-pagination__actions">${buttons.join('')}</div>`; pagination.classList.remove('d-none'); }
    async function fetchData(page = 1) { if (loading) return; loading = true; currentPage = page; try { const params = new URLSearchParams({ page: String(page), status: filterStatus.value || 'all', search: filterSearch.value || '', active_only: activeOnly.checked ? '1' : '0' }); const response = await fetch(`${card.dataset.fetchUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal memuat data job.'); hideNotice(); renderQueueHealth(payload.queue_health || null); renderSummary(payload.summary || {}); renderSnapshotJobs(payload.snapshot_jobs || [], payload.snapshot_summary || {}); renderActiveJobs(payload.active_jobs || []); renderTable(payload.jobs || []); renderPagination(payload.pagination || {}); } catch (error) { showNotice(error.message || 'Gagal memuat data job.', 'warning'); } finally { loading = false; } }
    async function forceStartJob(jobId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Force start job ini?', text: 'Job queued akan diproses langsung tanpa menunggu worker queue.', showCancelButton: true, confirmButtonText: 'Force Start', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.forceStartUrlTemplate, jobId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal force start job.'); showNotice(payload.message || 'Force start dijalankan.', 'info'); await fetchData(currentPage); }
    async function forceStartSnapshot(rebuildId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Force start snapshot ini?', text: 'Snapshot rebuild queued akan diproses langsung tanpa menunggu worker queue.', showCancelButton: true, confirmButtonText: 'Force Start', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.forceStartSnapshotUrlTemplate, rebuildId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal force start snapshot.'); showNotice(payload.message || 'Force start snapshot dijalankan.', 'info'); await fetchData(currentPage); }
    async function terminateJob(jobId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Terminate job ini?', text: 'Jika job sedang processing, worker akan menghentikan proses pada checkpoint berikutnya.', showCancelButton: true, confirmButtonText: 'Terminate', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.terminateUrlTemplate, jobId), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal terminate job.'); showNotice(payload.message || 'Permintaan terminate dikirim.', 'info'); await fetchData(currentPage); }
    async function deleteJob(jobId) { const confirmation = await Swal.fire({ icon: 'warning', title: 'Hapus job ini?', text: 'Record job akan dihapus dari database dan cache progress terkait juga dibersihkan.', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(templateUrl(card.dataset.destroyUrlTemplate, jobId), { method: 'DELETE', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({}) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus job.'); selectedJobIds.delete(String(jobId)); showNotice(payload.message || 'Job berhasil dihapus.', 'info'); await fetchData(currentPage); }
    async function clearJobs() { const confirmation = await Swal.fire({ icon: 'warning', title: 'Clear jobs?', text: 'Aksi ini akan menghapus riwayat job terminal yang sesuai filter saat ini dari database.', showCancelButton: true, confirmButtonText: 'Clear', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(card.dataset.clearUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ status: filterStatus.value || 'all', search: filterSearch.value || '' }) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal clear jobs.'); selectedJobIds.clear(); showNotice(payload.message || 'Riwayat job berhasil dibersihkan.', payload.status === 'warning' ? 'warning' : 'info'); await fetchData(1); }
    async function bulkDeleteJobs() { const jobIds = Array.from(selectedJobIds.values()); if (jobIds.length === 0) return; const confirmation = await Swal.fire({ icon: 'warning', title: 'Delete selected jobs?', text: `${jobIds.length} job terminal akan dihapus dari database.`, showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Batal' }); if (!confirmation.isConfirmed) return; const response = await fetch(card.dataset.bulkDeleteUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ job_ids: jobIds }) }); const payload = await response.json().catch(() => ({})); if (!response.ok || payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus job terpilih.'); selectedJobIds.clear(); showNotice(payload.message || 'Job terpilih berhasil dihapus.', payload.status === 'warning' ? 'warning' : 'info'); await fetchData(currentPage); }
    function startAutoRefresh() { if (refreshTimer) clearInterval(refreshTimer); if (!autoRefresh.checked) return; refreshTimer = setInterval(() => fetchData(currentPage), 5000); }

    btnRefresh.addEventListener('click', () => fetchData(currentPage));
    btnClear.addEventListener('click', async () => { try { await clearJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Clear Gagal', text: error.message || 'Gagal clear jobs.' }); } });
    btnDeleteSelected.addEventListener('click', async () => { try { await bulkDeleteJobs(); } catch (error) { Swal.fire({ icon: 'error', title: 'Bulk Delete Gagal', text: error.message || 'Gagal menghapus job terpilih.' }); } });
    filterStatus.addEventListener('change', () => fetchData(1));
    activeOnly.addEventListener('change', () => fetchData(1));
    filterSearch.addEventListener('input', function () { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(() => fetchData(1), 350); });
    autoRefresh.addEventListener('change', startAutoRefresh);
    selectAll.addEventListener('change', function () { currentJobs.forEach((job) => { if (!job.can_delete) return; if (selectAll.checked) selectedJobIds.add(String(job.id)); else selectedJobIds.delete(String(job.id)); }); syncSelectionState(); tableBody.querySelectorAll('.job-row-check').forEach((checkbox) => { checkbox.checked = selectAll.checked; }); });
    document.addEventListener('change', function (event) { const checkbox = event.target.closest('.job-row-check'); if (!checkbox) return; const jobId = String(checkbox.dataset.jobId || ''); if (jobId === '') return; if (checkbox.checked) selectedJobIds.add(jobId); else selectedJobIds.delete(jobId); syncSelectionState(); });
    document.addEventListener('click', async function (event) { const forceStartTarget = event.target.closest('[data-action="force-start"]'); if (forceStartTarget) { try { await forceStartJob(forceStartTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Start Gagal', text: error.message || 'Gagal force start job.' }); } return; } const forceStartSnapshotTarget = event.target.closest('[data-action="force-start-snapshot"]'); if (forceStartSnapshotTarget) { try { await forceStartSnapshot(forceStartSnapshotTarget.dataset.rebuildId); } catch (error) { Swal.fire({ icon: 'error', title: 'Force Start Gagal', text: error.message || 'Gagal force start snapshot.' }); } return; } const terminateTarget = event.target.closest('[data-action="terminate"]'); if (terminateTarget) { try { await terminateJob(terminateTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Terminate Gagal', text: error.message || 'Gagal terminate job.' }); } return; } const deleteTarget = event.target.closest('[data-action="delete"]'); if (deleteTarget) { try { await deleteJob(deleteTarget.dataset.jobId); } catch (error) { Swal.fire({ icon: 'error', title: 'Delete Gagal', text: error.message || 'Gagal menghapus job.' }); } return; } const pageTarget = event.target.closest('[data-page]'); if (pageTarget) fetchData(Number(pageTarget.dataset.page || 1)); });

    fetchData();
    startAutoRefresh();
});
</script>
@endsection

@section('styles')
<style>
.job-management-hero { position: relative; overflow: hidden; border-radius: 24px; padding: 1.75rem 2rem; background: radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 35%), linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%); border: 1px solid rgba(37, 99, 235, 0.16); box-shadow: 0 22px 45px -32px rgba(29, 78, 216, 0.3); }
.job-management-hero__glow { position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; border-radius: 50%; background: rgba(56, 189, 248, 0.18); filter: blur(40px); }
.job-management-hero__eyebrow { display: inline-block; margin-bottom: 0.75rem; padding: 0.4rem 0.9rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #1d4ed8; background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(37, 99, 235, 0.15); }
.job-management-hero__title { color: #1e3a8a; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 0.5rem; }
.job-management-hero__text { color: #334155; font-size: 0.95rem; line-height: 1.6; max-width: 700px; }
.job-management-hero__badge { display: inline-flex; align-items: center; padding: 0.6rem 1.25rem; border-radius: 16px; background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.8); color: #334155; font-size: 0.9rem; font-weight: 600; box-shadow: 0 4px 12px -4px rgba(0, 0, 0, 0.05); }
.job-management-card { border-radius: 26px; overflow: hidden; box-shadow: 0 28px 60px -40px rgba(15, 23, 42, 0.2) !important; background: #f8fafc; }
.job-management-card__header { padding: 1.5rem 1.75rem 1rem; background: #ffffff; border-bottom: 1px solid #e2e8f0; }
.job-management-card__eyebrow { display: inline-block; margin-bottom: 0.5rem; padding: 0.4rem 0.9rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
.job-management-card__subtitle { color: #64748b; max-width: 760px; line-height: 1.6; font-size: 0.9rem; }
.job-management-card__body { position: relative; padding: 1.75rem; display: grid; gap: 1.5rem; }
.job-management-toolbar { padding: 1.25rem; border-radius: 22px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); }
.job-management-toolbar__sub { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding-top: 1rem; border-top: 1px solid #e2e8f0; }
.job-management-toolbar__hint { font-size: 0.85rem; font-weight: 500; color: #64748b; }
.job-management-label { display: block; margin-bottom: 0.75rem; color: #0f172a; font-size: 0.9rem; font-weight: 600; }
.job-management-toolbar__actions { display: flex; align-items: center; gap: 0.75rem; min-height: 48px; flex-wrap: wrap; }
.job-management-btn { min-height: 48px; border-radius: 14px; font-weight: 700; padding: 0 1.25rem; font-size: 0.9rem; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
.job-management-btn--primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; box-shadow: 0 14px 24px -14px rgba(37, 99, 235, 0.5); }
.job-management-btn--primary:hover { transform: translateY(-2px); box-shadow: 0 18px 28px -14px rgba(37, 99, 235, 0.6); color: #fff; }
.job-management-btn--danger-outline { border: 2px solid #ef4444; color: #ef4444; background: transparent; }
.job-management-btn--danger-outline:hover { background: #fef2f2; color: #dc2626; transform: translateY(-1px); }
.job-management-input, .job-management-select { min-height: 48px; border-radius: 14px; border: 1px solid #d1d5db; background: #fff; font-weight: 600; color: #111827; }
.job-management-input:focus, .job-management-select:focus { border-color: var(--jm-primary-color); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
.job-management-switch { padding-left: 2.5rem; }
.job-management-switch .custom-control-label { font-weight: 600; color: #334155; cursor: pointer; }
.job-management-switch .custom-control-label::before, .job-management-switch .custom-control-label::after { top: 0.15rem; left: -2.5rem; }
.job-management-stat { display: flex; align-items: center; gap: 1rem; height: 100%; padding: 1.25rem; border-radius: 20px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
.job-management-stat:hover { box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
.job-management-stat__icon { width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; background: var(--icon-bg); color: var(--icon-color); }
.job-management-stat__body small { display: block; color: #64748b; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 0.25rem; }
.job-management-stat__body strong { display: block; color: #0f172a; font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
.job-management-notice { padding: 1rem 1.25rem; border-radius: 18px; font-size: 0.92rem; line-height: 1.65; border-left: 4px solid; }
.job-management-notice--info { color: #1e3a8a; background: #eff6ff; border-color: #3b82f6; }
.job-management-notice--warning { color: #92400e; background: #fffbeb; border-color: #f59e0b; }
.job-management-notice--subtle { opacity: 0.9; }
.job-management-section { padding: 1.5rem; border-radius: 22px; background: #f1f5f9; border: 1px solid #e2e8f0; }
.job-management-section__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
.job-management-section__eyebrow { font-size: 0.75rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 0.35rem 0.85rem; border-radius: 999px; }
.job-management-section__title { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-top: 0.5rem; }
.job-management-section__meta { font-size: 0.88rem; font-weight: 700; color: #64748b; }
.job-management-empty { padding: 2rem; border-radius: 18px; background: #fff; border: 1px dashed #d1d5db; text-align: center; color: #64748b; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.75rem; }
.job-management-bulkbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 0.8rem 1rem; border-radius: 16px; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 1rem; }
.job-management-bulkbar__actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.job-management-bulkbar__hint { font-size: 0.88rem; font-weight: 600; color: #475569; }
.job-active-card { padding: 1.25rem; height: 100%; border-radius: 20px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); }
.job-active-card--snapshot { background: linear-gradient(180deg, #fdfdff 0%, #f7f9ff 100%); border-color: rgba(139, 92, 246, 0.2); }
.job-active-card__header, .job-active-card__footer { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.job-active-card__header { margin-bottom: 1rem; }
.job-active-card__title { font-size: 1rem; font-weight: 700; color: #0f172a; }
.job-active-card__sub, .job-table-secondary { font-size: 0.85rem; color: #64748b; }
.job-active-card__message { margin-top: 0.8rem; color: #1e3a8a; font-weight: 600; line-height: 1.55; font-size: 0.9rem; }
.job-active-card__meta { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; font-size: 0.82rem; font-weight: 600; color: #475569; }
.job-active-card__footer { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; }
.job-active-card__hint { font-size: 0.84rem; font-weight: 500; color: #64748b; }
.job-active-card__actions { display: flex; gap: 0.5rem; }
.job-active-card__action { border-radius: 12px; font-weight: 700; font-size: 0.85rem; }
.job-status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 0.4rem 0.8rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
.job-status-badge--info { background: #eff6ff; color: #2563eb; }
.job-status-badge--warning { background: #fffbeb; color: #d97706; }
.job-status-badge--success { background: #f0fdf4; color: #16a34a; }
.job-status-badge--danger { background: #fef2f2; color: #ef4444; }
.job-status-badge--dark { background: #f1f5f9; color: #1e293b; }
.job-status-badge--muted { background: #f1f5f9; color: #64748b; }
.job-progress { height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05); }
.job-progress__bar { height: 100%; background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%); transition: width 0.35s ease; }
.job-progress__meta { display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.8rem; color: #475569; }
.job-management-table-wrap { border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; background: #fff; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); }
.job-management-table__header th { background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; padding: 1rem 1.25rem; }
.job-management-table tbody td { padding: 1rem 1.25rem; border-top: 1px solid #f1f5f9; vertical-align: middle; }
.job-col-check { width: 52px; }
.job-table-primary { font-weight: 600; color: #0f172a; }
.job-table-actions { display: flex; gap: 0.5rem; justify-content: center; }
.job-table-action { border-radius: 10px; font-weight: 700; font-size: 0.8rem; }
.job-management-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem; }
.job-management-pagination__meta { font-size: 0.9rem; font-weight: 600; color: #475569; }
.job-management-pagination__actions { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; }
.job-page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; padding: 0 0.8rem; border: 1px solid #d1d5db; border-radius: 12px; background: #fff; color: #334155; font-weight: 700; }
.job-page-btn.is-active { background: linear-gradient(135deg, #2563eb, #1d4ed8); border-color: transparent; color: #fff; box-shadow: 0 10px 20px -10px rgba(37, 99, 235, 0.4); }
@media (max-width: 767.98px) { .job-management-hero, .job-management-card__header { padding-left: 1rem; padding-right: 1rem; } .job-management-card__body { padding: 1rem; } .job-management-toolbar, .job-management-section, .job-management-stat, .job-active-card { border-radius: 18px; } .job-management-toolbar__actions, .job-active-card__header, .job-active-card__footer, .job-management-section__header, .job-management-pagination { flex-direction: column; align-items: flex-start; } .job-management-btn { width: 100%; } .job-management-table thead th, .job-management-table tbody td { padding: .8rem; } .job-management-bulkbar { align-items: flex-start; } }
</style>
@endsection
