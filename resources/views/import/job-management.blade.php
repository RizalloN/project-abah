@extends('layouts.admin')

@section('title', 'Job Management')

@section('content')
<div class="job-management-hero mb-4">
    <div class="job-management-hero__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="pr-3">
            <span class="job-management-hero__eyebrow">Job Management</span>
            <div class="job-management-hero__title"><i class="fas fa-tasks mr-2"></i> Monitor Import & Snapshot Jobs</div>
            <p class="job-management-hero__text mb-0">Pantau queue import dan rebuild snapshot report dari satu halaman dengan progress realtime.</p>
        </div>
        <div class="job-management-hero__badge mt-3 mt-md-0"><i class="fas fa-wave-square mr-2"></i> Realtime Monitor</div>
    </div>
</div>

<div class="card shadow-sm border-0 job-management-card" id="job-management-card"
     data-fetch-url="{{ route('job-management.data') }}"
     data-clear-url="{{ route('job-management.clear') }}"
     data-bulk-delete-url="{{ route('job-management.bulk-destroy') }}"
     data-destroy-url-template="{{ route('job-management.destroy', ['jobId' => '__JOB_ID__']) }}"
     data-force-start-url-template="{{ route('job-management.force-start', ['jobId' => '__JOB_ID__']) }}"
     data-force-start-snapshot-url-template="{{ route('job-management.snapshot.force-start', ['rebuildId' => '__REBUILD_ID__']) }}"
     data-terminate-url-template="{{ route('job-management.terminate', ['jobId' => '__JOB_ID__']) }}">
    <div class="card-header bg-white border-0 job-management-card__header">
        <span class="job-management-card__eyebrow">Queue Control</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-server text-primary mr-2"></i> Status Import Queue
        </h5>
        <p class="job-management-card__subtitle mb-0">Halaman ini memantau job import pada tabel <code>import_jobs</code> dan proses rebuild snapshot yang disimpan pada cache state terpisah.</p>
    </div>
    <div class="card-body job-management-card__body">
        <div class="job-management-toolbar mb-4">
            <div class="row">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <label class="job-management-label" for="job-filter-status">Filter Status</label>
                    <select id="job-filter-status" class="form-control">
                        <option value="all">Semua Status</option>
                        <option value="queued">Queued</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="terminated">Terminated</option>
                        <option value="failed">Failed</option>
                        <option value="failed_partial">Partial Failed</option>
                    </select>
                </div>
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <label class="job-management-label" for="job-filter-search">Cari Job</label>
                    <input type="text" id="job-filter-search" class="form-control" placeholder="Cari file, report, user, atau ID job...">
                </div>
                <div class="col-lg-3">
                    <label class="job-management-label d-block">Aksi</label>
                    <div class="job-management-toolbar__actions">
                        <button type="button" id="btn-job-refresh" class="btn btn-primary job-management-btn">
                            <i class="fas fa-sync-alt mr-2"></i> Refresh
                        </button>
                        <button type="button" id="btn-job-clear" class="btn btn-outline-danger job-management-btn">
                            <i class="fas fa-trash-alt mr-2"></i> Clear Jobs
                        </button>
                        <div class="custom-control custom-switch job-management-switch">
                            <input type="checkbox" class="custom-control-input" id="job-auto-refresh" checked>
                            <label class="custom-control-label" for="job-auto-refresh">Auto</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="job-management-toolbar__sub mt-3">
                <div class="custom-control custom-switch job-management-switch job-management-switch--inline">
                    <input type="checkbox" class="custom-control-input" id="job-filter-active-only">
                    <label class="custom-control-label" for="job-filter-active-only">Active Only</label>
                </div>
                <div class="job-management-toolbar__hint">Saat aktif, tabel hanya menampilkan job dengan status queued atau processing.</div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3 mb-md-0"><div class="job-management-stat"><small>Job Aktif</small><strong id="summary-active">0</strong></div></div>
            <div class="col-md-3 mb-3 mb-md-0"><div class="job-management-stat"><small>Queued</small><strong id="summary-queued">0</strong></div></div>
            <div class="col-md-3 mb-3 mb-md-0"><div class="job-management-stat"><small>Processing</small><strong id="summary-processing">0</strong></div></div>
            <div class="col-md-3"><div class="job-management-stat"><small>Dibuat Hari Ini</small><strong id="summary-today">0</strong></div></div>
        </div>

        <div id="job-management-notice" class="job-management-notice d-none mb-3"></div>
        <div id="job-management-queue-health" class="job-management-notice d-none mb-3"></div>

        <div class="job-management-section mb-4">
            <div class="job-management-section__header">
                <div>
                    <div class="job-management-section__eyebrow">Snapshot</div>
                    <div class="job-management-section__title">Snapshot Jobs</div>
                </div>
                <div class="job-management-section__meta" id="snapshot-job-count-label">0 job snapshot aktif</div>
            </div>
            <div id="snapshot-jobs-grid" class="row">
                <div class="col-12"><div class="job-management-empty">Belum ada rebuild snapshot yang sedang antre atau berjalan.</div></div>
            </div>
        </div>

        <div class="job-management-section mb-4">
            <div class="job-management-section__header">
                <div>
                    <div class="job-management-section__eyebrow">Sedang Berjalan</div>
                    <div class="job-management-section__title">Active Jobs</div>
                </div>
                <div class="job-management-section__meta" id="active-job-count-label">0 job aktif</div>
            </div>
            <div id="active-jobs-grid" class="row">
                <div class="col-12"><div class="job-management-empty">Belum ada job aktif.</div></div>
            </div>
        </div>

        <div class="job-management-section">
            <div class="job-management-section__header">
                <div>
                    <div class="job-management-section__eyebrow">Riwayat</div>
                    <div class="job-management-section__title">Recent Jobs</div>
                </div>
                <div class="job-management-section__meta" id="job-pagination-meta">0 job</div>
            </div>

            <div class="job-management-bulkbar mb-3">
                <div class="form-check m-0">
                    <input class="form-check-input" type="checkbox" id="job-select-all">
                    <label class="form-check-label font-weight-bold" for="job-select-all">Pilih Semua di Halaman</label>
                </div>
                <div class="job-management-bulkbar__actions">
                    <div id="job-selected-count" class="job-management-bulkbar__hint">0 job dipilih</div>
                    <button type="button" id="btn-job-delete-selected" class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="fas fa-trash-alt mr-1"></i> Delete Selected
                    </button>
                </div>
            </div>

            <div class="job-management-table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 job-management-table">
                        <thead>
                            <tr>
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
                            <tr><td colspan="8" class="text-center text-muted py-4">Memuat data job...</td></tr>
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
    const snapshotGrid = document.getElementById('snapshot-jobs-grid');
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
    function progressMarkup(job) { const percent = Math.max(0, Math.min(100, Number(job.percent || 0))); return `<div class="job-progress"><div class="job-progress__bar" style="width:${percent}%"></div></div><div class="job-progress__meta">${percent}% • ${Number(job.processed_rows || 0).toLocaleString('id-ID')} / ${Number(job.total_rows || 0).toLocaleString('id-ID')}</div>`; }
    function snapshotDetailMarkup(job) { const items = []; if (job.stage_label) items.push(`<span><i class="fas fa-layer-group mr-1"></i>${escapeHtml(job.stage_label)}</span>`); if (job.current_report_label) items.push(`<span><i class="far fa-chart-bar mr-1"></i>${escapeHtml(job.current_report_label)}</span>`); if (job.current_period) items.push(`<span><i class="far fa-calendar-alt mr-1"></i>${escapeHtml(job.current_period)}</span>`); if (Number(job.report_total_units || 0) > 0) items.push(`<span><i class="fas fa-stream mr-1"></i>${Number(job.report_completed_units || 0).toLocaleString('id-ID')} / ${Number(job.report_total_units || 0).toLocaleString('id-ID')} periode</span>`); return items.join(''); }
    function terminateButton(job, compact = false) { if (!job.can_terminate) return '<span class="text-muted small">-</span>'; const disabled = job.termination_requested ? 'disabled' : ''; const label = job.termination_requested ? 'Menunggu Stop' : 'Terminate'; const classes = compact ? 'btn btn-sm btn-outline-danger' : 'btn btn-outline-danger job-active-card__terminate'; return `<button type="button" class="${classes}" data-action="terminate" data-job-id="${job.id}" ${disabled}><i class="fas fa-stop-circle mr-1"></i>${label}</button>`; }
    function forceStartButton(job, compact = false, snapshot = false) { if (!job.can_force_start) return ''; const classes = compact ? 'btn btn-sm btn-outline-primary' : 'btn btn-outline-primary job-active-card__force'; const action = snapshot ? 'force-start-snapshot' : 'force-start'; const identity = snapshot ? `data-rebuild-id="${job.id}"` : `data-job-id="${job.id}"`; return `<button type="button" class="${classes}" data-action="${action}" ${identity}><i class="fas fa-play mr-1"></i>Force Start</button>`; }
    function deleteButton(job, compact = false) { if (!job.can_delete) return ''; const classes = compact ? 'btn btn-sm btn-outline-secondary ml-1' : 'btn btn-outline-secondary job-active-card__delete'; return `<button type="button" class="${classes}" data-action="delete" data-job-id="${job.id}"><i class="fas fa-times-circle mr-1"></i>Delete</button>`; }
    function rowCheckbox(job) { if (!job.can_delete) return '<span class="text-muted small">-</span>'; const checked = selectedJobIds.has(String(job.id)) ? 'checked' : ''; return `<input type="checkbox" class="job-row-check" data-job-id="${job.id}" ${checked}>`; }
    function syncSelectionState() { const selectableIds = currentJobs.filter((job) => job.can_delete).map((job) => String(job.id)); const selectedOnPage = selectableIds.filter((id) => selectedJobIds.has(id)); selectAll.checked = selectableIds.length > 0 && selectedOnPage.length === selectableIds.length; selectAll.indeterminate = selectedOnPage.length > 0 && selectedOnPage.length < selectableIds.length; selectedCount.textContent = `${selectedJobIds.size} job dipilih`; btnDeleteSelected.disabled = selectedJobIds.size === 0; }
    function renderSummary(summary) { document.getElementById('summary-active').textContent = Number(summary.active_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-queued').textContent = Number(summary.queued_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-processing').textContent = Number(summary.processing_jobs || 0).toLocaleString('id-ID'); document.getElementById('summary-today').textContent = Number(summary.today_jobs || 0).toLocaleString('id-ID'); }
    function renderSnapshotJobs(items, summary = {}) { const activeCount = Number(summary.active_jobs || 0); snapshotJobCountLabel.textContent = `${activeCount.toLocaleString('id-ID')} job snapshot aktif`; if (!Array.isArray(items) || items.length === 0) { snapshotGrid.innerHTML = `<div class="col-12"><div class="job-management-empty">Belum ada rebuild snapshot yang sedang antre atau berjalan.</div></div>`; return; } snapshotGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card job-active-card--snapshot"><div class="job-active-card__header"><div><div class="job-active-card__title">${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)} • ${escapeHtml(job.id)}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta">${snapshotDetailMarkup(job)}<span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span>${job.queue_name ? `<span><i class="fas fa-server mr-1"></i>${escapeHtml(job.queue_name)}</span>` : ''}</div></div><div class="job-active-card__footer"><span class="job-active-card__hint">${job.status === 'failed' ? 'Snapshot stale ditandai gagal otomatis agar progress tidak menggantung.' : 'Snapshot rebuild dipantau dari cache state report management.'}</span>${forceStartButton(job, false, true)}</div></div></div>`).join(''); }
    function renderActiveJobs(items) { activeJobCountLabel.textContent = `${Number(items.length || 0).toLocaleString('id-ID')} job aktif`; if (!Array.isArray(items) || items.length === 0) { activeGrid.innerHTML = `<div class="col-12"><div class="job-management-empty">Belum ada job dengan status queued atau processing.</div></div>`; return; } activeGrid.innerHTML = items.map((job) => `<div class="col-xl-6 mb-3"><div class="job-active-card"><div class="job-active-card__header"><div><div class="job-active-card__title">#${job.id} • ${escapeHtml(job.report_name)}</div><div class="job-active-card__sub">${escapeHtml(job.file_name)}${job.table_name ? ' • ' + escapeHtml(job.table_name) : ''}</div></div>${statusBadge(job)}</div><div class="job-active-card__body">${progressMarkup(job)}<div class="job-active-card__message">${escapeHtml(job.message || '-')}</div><div class="job-active-card__meta"><span><i class="far fa-user mr-1"></i>${escapeHtml(job.created_by_name || 'System')}</span><span><i class="far fa-clock mr-1"></i>${escapeHtml(job.duration_label || '-')}</span><span><i class="fas fa-history mr-1"></i>${escapeHtml(job.updated_at_label || '-')}</span></div></div><div class="job-active-card__footer">${job.termination_requested ? '<span class="job-active-card__hint">Permintaan terminate sudah dikirim ke worker.</span>' : '<span class="job-active-card__hint">Job aktif masih dapat dihentikan dari halaman ini.</span>'}<span class="d-flex flex-wrap" style="gap:.5rem;">${forceStartButton(job)}${terminateButton(job)}</span></div></div></div>`).join(''); }
    function renderTable(items) { currentJobs = Array.isArray(items) ? items : []; if (currentJobs.length === 0) { tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data job untuk filter ini.</td></tr>'; syncSelectionState(); return; } tableBody.innerHTML = currentJobs.map((job) => `<tr><td class="text-center job-col-check">${rowCheckbox(job)}</td><td class="font-weight-bold text-primary">#${job.id}</td><td><div class="job-table-primary">${escapeHtml(job.report_name)}</div><div class="job-table-secondary">${escapeHtml(job.table_name || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.file_name)}</div><div class="job-table-secondary">By ${escapeHtml(job.created_by_name || 'System')}</div></td><td>${statusBadge(job)}</td><td>${progressMarkup(job)}<div class="job-table-secondary mt-1">${escapeHtml(job.message || '-')}</div></td><td><div class="job-table-primary">${escapeHtml(job.updated_at_label || '-')}</div><div class="job-table-secondary">Durasi ${escapeHtml(job.duration_label || '-')}</div></td><td class="text-center">${forceStartButton(job, true)}${terminateButton(job, true)}${deleteButton(job, true)}</td></tr>`).join(''); syncSelectionState(); }
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
.job-management-hero{position:relative;overflow:hidden;border-radius:24px;padding:1.45rem 1.5rem;background:radial-gradient(circle at top right,rgba(37,99,235,.22),transparent 30%),linear-gradient(135deg,#f8fbff 0%,#eef4ff 46%,#dbeafe 100%);border:1px solid rgba(37,99,235,.16);box-shadow:0 22px 45px -32px rgba(29,78,216,.4)}
.job-management-hero__glow{position:absolute;top:-48px;right:-30px;width:170px;height:170px;border-radius:999px;background:rgba(14,165,233,.16);filter:blur(10px)}
.job-management-hero__eyebrow,.job-management-card__eyebrow{display:inline-block;margin-bottom:.55rem;padding:.35rem .7rem;border-radius:999px;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.job-management-hero__eyebrow{color:#1d4ed8;background:rgba(255,255,255,.72);border:1px solid rgba(37,99,235,.16)}
.job-management-hero__title{color:#0f3f8c;font-size:1.4rem;font-weight:800;letter-spacing:-.03em;margin-bottom:.35rem}
.job-management-hero__text{color:#31527c;line-height:1.7;max-width:760px}
.job-management-hero__badge{display:inline-flex;align-items:center;min-height:48px;padding:.8rem 1rem;border-radius:18px;background:rgba(255,255,255,.84);border:1px solid rgba(37,99,235,.14);color:#0f3f8c;font-weight:700;box-shadow:0 18px 32px -24px rgba(29,78,216,.3)}
.job-management-card{border-radius:26px;overflow:hidden;box-shadow:0 28px 60px -40px rgba(15,23,42,.32)!important}
.job-management-card__header{padding:1.45rem 1.5rem 1rem;background:radial-gradient(circle at top left,rgba(59,130,246,.09),transparent 28%),linear-gradient(180deg,#fff 0%,#f8fafc 100%)}
.job-management-card__eyebrow{color:#1d4ed8;background:rgba(37,99,235,.08)}
.job-management-card__subtitle{color:#64748b;max-width:760px;line-height:1.6}
.job-management-card__body{position:relative;padding:1.5rem}
.job-management-toolbar,.job-management-section,.job-management-stat,.job-active-card{border-radius:22px;background:linear-gradient(180deg,#fff 0%,#fbfcfe 100%);border:1px solid rgba(148,163,184,.16);box-shadow:0 16px 34px -30px rgba(15,23,42,.24)}
.job-management-toolbar{padding:1.1rem 1.15rem}.job-management-toolbar__sub{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding-top:.9rem;border-top:1px solid rgba(226,232,240,.86)}
.job-management-toolbar__hint{font-size:.84rem;font-weight:700;color:#64748b}.job-management-label{display:block;margin-bottom:.65rem;color:#0f172a;font-size:.9rem;font-weight:800}
.job-management-toolbar__actions{display:flex;align-items:center;gap:.75rem;min-height:46px;flex-wrap:wrap}.job-management-btn{min-height:46px;border-radius:14px;font-weight:800;padding:0 1.1rem}
.job-management-switch{padding-left:2.2rem}.job-management-switch .custom-control-label{font-weight:800;color:#0f3f8c;cursor:pointer}.job-management-switch--inline{padding-left:2.2rem;margin:0}
.job-management-stat{display:flex;flex-direction:column;justify-content:space-between;height:100%;min-height:118px;padding:1.2rem 1.15rem}.job-management-stat small{display:block;color:#64748b;font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.5rem}.job-management-stat strong{display:block;color:#0f172a;font-size:1.2rem;font-weight:800;line-height:1.5}
.job-management-notice{margin-top:.25rem;padding:.95rem 1rem;border-radius:18px;font-size:.92rem;line-height:1.65}.job-management-notice--info{color:#0f3f8c;background:rgba(219,234,254,.72);border:1px solid rgba(96,165,250,.2)}.job-management-notice--warning{color:#92400e;background:rgba(254,243,199,.78);border:1px solid rgba(251,191,36,.25)}.job-management-notice--subtle{opacity:.9}
.job-management-section{padding:1.15rem}.job-management-section__header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.job-management-section__eyebrow{font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8}.job-management-section__title{font-size:1.08rem;font-weight:800;color:#0f172a}.job-management-section__meta{font-size:.86rem;font-weight:700;color:#64748b}
.job-management-empty{padding:1.5rem;border-radius:18px;background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);border:1px dashed rgba(148,163,184,.32);text-align:center;color:#64748b;font-weight:700}
.job-management-bulkbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:.8rem 1rem;border-radius:16px;background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid rgba(148,163,184,.2)}.job-management-bulkbar__actions{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}.job-management-bulkbar__hint{font-size:.88rem;font-weight:700;color:#475569}
.job-active-card{padding:1rem 1.05rem;height:100%}.job-active-card--snapshot{background:linear-gradient(180deg,#fdfefe 0%,#f5f9ff 100%);border:1px solid rgba(59,130,246,.16)}.job-active-card__header,.job-active-card__footer{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.job-active-card__header{margin-bottom:.95rem}.job-active-card__title{font-size:1rem;font-weight:800;color:#0f172a}.job-active-card__sub,.job-table-secondary{font-size:.83rem;color:#64748b}.job-active-card__message{margin-top:.8rem;color:#0f3f8c;font-weight:700;line-height:1.55}.job-active-card__meta{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:.9rem;font-size:.82rem;font-weight:700;color:#64748b}.job-active-card__footer{margin-top:1rem;padding-top:.9rem;border-top:1px solid rgba(226,232,240,.86)}.job-active-card__hint{font-size:.84rem;font-weight:700;color:#475569}.job-active-card__terminate,.job-active-card__delete,.job-active-card__force{border-radius:12px;font-weight:800}
.job-status-badge{display:inline-flex;align-items:center;justify-content:center;padding:.45rem .72rem;border-radius:999px;font-size:.76rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.job-status-badge--info{background:rgba(37,99,235,.08);color:#1d4ed8}.job-status-badge--warning{background:rgba(245,158,11,.12);color:#b45309}.job-status-badge--success{background:rgba(16,185,129,.12);color:#047857}.job-status-badge--danger{background:rgba(239,68,68,.1);color:#b91c1c}.job-status-badge--dark{background:rgba(15,23,42,.1);color:#0f172a}.job-status-badge--muted{background:rgba(100,116,139,.12);color:#475569}
.job-progress{height:12px;border-radius:999px;background:linear-gradient(180deg,#dbe7ef 0%,#cfe0ea 100%);overflow:hidden;box-shadow:inset 0 1px 2px rgba(15,23,42,.08)}.job-progress__bar{height:100%;background:linear-gradient(90deg,#0f4c81 0%,#1d4ed8 100%);transition:width .35s ease}.job-progress__meta{margin-top:.45rem;font-size:.8rem;font-weight:700;color:#475569}
.job-management-table-wrap{border:1px solid rgba(148,163,184,.18);border-radius:22px;overflow:hidden;background:#fff}.job-management-table thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef4ff 100%);border-bottom:1px solid rgba(148,163,184,.18);color:#334155;font-size:.8rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:1rem}.job-management-table tbody td{padding:1rem;border-top:1px solid rgba(226,232,240,.8);vertical-align:top}.job-col-check{width:52px}.job-table-primary{font-weight:800;color:#0f172a}
.job-management-pagination{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1rem}.job-management-pagination__meta{font-size:.9rem;font-weight:700;color:#475569}.job-management-pagination__actions{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}.job-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 .8rem;border:1px solid rgba(148,163,184,.28);border-radius:12px;background:#fff;color:#334155;font-weight:800}.job-page-btn.is-active{background:linear-gradient(135deg,#0f4c81,#1d4ed8);border-color:transparent;color:#fff;box-shadow:0 16px 32px -24px rgba(29,78,216,.55)}
@media (max-width:767.98px){.job-management-hero,.job-management-card__header{padding-left:1rem;padding-right:1rem}.job-management-card__body{padding:1rem}.job-management-toolbar,.job-management-section,.job-management-stat,.job-active-card{border-radius:18px}.job-management-toolbar__actions,.job-active-card__header,.job-active-card__footer,.job-management-section__header,.job-management-pagination{align-items:flex-start}.job-management-btn{width:100%}.job-management-table thead th,.job-management-table tbody td{padding:.8rem}.job-management-bulkbar{align-items:flex-start}}
</style>
@endsection
