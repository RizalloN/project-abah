@extends('layouts.admin')

@section('title', 'Report Management')

@section('content')
<div class="report-management-hero mb-4">
    <div class="report-management-hero__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="pr-3">
            <span class="report-management-hero__eyebrow">Report Management</span>
            <div class="report-management-hero__title"><i class="fas fa-layer-group mr-2"></i> Kontrol Data Report</div>
            <p class="report-management-hero__text mb-0">Pilih report, review grouping per periode, lalu kelola delete secara aman tanpa perlu scroll panjang untuk memantau seleksi.</p>
        </div>
        <div class="report-management-hero__badge mt-3 mt-md-0"><i class="fas fa-shield-alt mr-2"></i> Delete Guard Aktif</div>
    </div>
</div>

<div class="card shadow-sm border-0 import-upload-card" id="report-management-card"
     data-fetch-url="{{ route('import.report-management.data') }}"
     data-delete-url="{{ route('import.report-management.delete') }}"
     data-delete-process-url-template="{{ route('import.report-management.delete.process', ['deleteId' => '__DELETE_ID__']) }}"
     data-delete-status-url-template="{{ route('import.report-management.delete.status', ['deleteId' => '__DELETE_ID__']) }}">
    <div class="card-header bg-white border-0 import-upload-card__header">
        <span class="import-upload-card__eyebrow">Scope & Preview</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-database text-primary mr-2"></i> Kelola Data per Grup
        </h5>
        <p class="import-upload-card__subtitle mb-0">Klik baris untuk memilih grup, gunakan centang per periode untuk bulk select yang lebih rapi, lalu jalankan delete dengan guard controller yang tetap aktif.</p>
    </div>
    <div class="card-body import-upload-card__body">
        <div class="row align-items-end">
            <div class="col-lg-8 mb-3">
                <label class="font-weight-bold text-dark">Pilih Report</label>
                <select id="management-report-select" class="form-control select2">
                    <option value="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}">{{ $report->nama_report }} ({{ $report->table_name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 mb-3">
                <button type="button" id="btn-management-filter" class="btn btn-primary btn-block report-management-filter-btn">
                    <i class="fas fa-filter mr-2"></i> Tampilkan Data
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3"><div class="report-management-stat"><small>Report Aktif</small><strong id="management-summary-report">Belum dipilih</strong></div></div>
            <div class="col-md-4 mb-3"><div class="report-management-stat"><small>Jumlah Grup</small><strong id="management-summary-groups">0</strong></div></div>
            <div class="col-md-4 mb-3"><div class="report-management-stat"><small>Grand Total Baris</small><strong id="management-summary-rows">0</strong></div></div>
        </div>

        <div id="management-notice" class="report-management-notice d-none"></div>

        <div class="report-management-bulkbar mt-3">
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="management-select-all" disabled>
                <label class="form-check-label font-weight-bold" for="management-select-all">Pilih Semua di Halaman</label>
            </div>
            <div class="report-management-bulkbar__hint">
                Klik baris untuk centang cepat. Setiap header periode punya pilihan "Pilih semua periode ini".
            </div>
        </div>

        <div class="report-management-table-wrap mt-3">
            <div class="table-responsive">
                <table class="table table-hover mb-0 report-management-table">
                    <thead>
                        <tr>
                            <th class="text-center report-management-col-check"><i class="far fa-check-square"></i></th>
                            <th>Kanca</th>
                            <th class="text-right">Jumlah Baris</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="management-table-body">
                        <tr><td colspan="4" class="text-center text-muted py-4">Pilih report lalu klik "Tampilkan Data".</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="management-pagination" class="report-management-pagination d-none"></div>

        <div class="report-management-selection-toast-shell">
            <div id="management-selection-toast" class="report-management-selection-toast d-none" aria-live="polite">
                <div class="report-management-selection-toast__body">
                    <div class="report-management-selection-toast__eyebrow">Seleksi Aktif</div>
                    <div id="management-selection-toast-text" class="report-management-selection-toast__text">0 grup dipilih</div>
                    <div id="management-selection-toast-subtext" class="report-management-selection-toast__subtext">0 baris siap dihapus</div>
                </div>
                <div class="report-management-selection-toast__actions">
                    <button type="button" id="btn-management-clear-selected" class="btn btn-sm report-management-selection-toast__btn report-management-selection-toast__btn--ghost" disabled>
                        Reset
                    </button>
                    <button type="button" id="btn-management-delete-selected" class="btn btn-sm report-management-selection-toast__btn report-management-selection-toast__btn--danger" disabled>
                        <i class="fas fa-trash-alt mr-1"></i> Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reportManagementCard = document.getElementById('report-management-card');
        const managementReportSelect = document.getElementById('management-report-select');
        const btnManagementFilter = document.getElementById('btn-management-filter');
        const managementTableBody = document.getElementById('management-table-body');
        const managementPagination = document.getElementById('management-pagination');
        const managementNotice = document.getElementById('management-notice');
        const summaryReport = document.getElementById('management-summary-report');
        const summaryGroups = document.getElementById('management-summary-groups');
        const summaryRows = document.getElementById('management-summary-rows');
        const managementSelectAll = document.getElementById('management-select-all');
        const managementSelectionToastShell = document.querySelector('.report-management-selection-toast-shell');
        const managementSelectionToast = document.getElementById('management-selection-toast');
        const managementSelectionToastText = document.getElementById('management-selection-toast-text');
        const managementSelectionToastSubtext = document.getElementById('management-selection-toast-subtext');
        const btnDeleteSelected = document.getElementById('btn-management-delete-selected');
        const btnClearSelected = document.getElementById('btn-management-clear-selected');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
        const managementState = { currentPage: 1, perPage: 8, selectedScopes: new Map() };

        if (managementSelectionToastShell && managementSelectionToastShell.parentElement !== document.body) {
            document.body.appendChild(managementSelectionToastShell);
        }

        function themedSwal(options) {
            return Swal.fire(Object.assign({
                customClass: { popup: 'swal-modern-popup', title: 'swal-modern-title', htmlContainer: 'swal-modern-html', confirmButton: 'swal-modern-confirm', cancelButton: 'swal-modern-cancel' },
                buttonsStyling: false,
                background: '#ffffff',
            }, options));
        }

        function escapeHtml(value) {
            return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function formatNumber(value) { return Number(value || 0).toLocaleString('id-ID'); }
        function normalizeShortDateLabel(value) {
            const text = String(value ?? '').trim();
            if (!text || text === '(Blank)' || text === '(Tanpa Periode)') return text || '(Blank)';
            const isoMatch = text.match(/^(\d{4}-\d{2}-\d{2})/);
            if (isoMatch) return isoMatch[1];
            const slashMatch = text.match(/^(\d{2}\/\d{2}\/\d{4})/);
            if (slashMatch) return slashMatch[1].split('/').reverse().join('-');
            return text;
        }
        function buildDeleteUrl(template, deleteId) { return String(template || '').replace('__DELETE_ID__', encodeURIComponent(deleteId)); }
        function createScopeKey(scope) { return JSON.stringify([scope?.period_filter ?? scope?.period ?? '', scope?.kanca_filter ?? scope?.kanca ?? '', !!scope?.period_is_null, !!scope?.kanca_is_null]); }
        function createPeriodBucketKey(periodLabel, periodIsNull) { return periodIsNull ? '__blank__' : String(periodLabel || '(Tanpa Periode)'); }

        function updateDeleteProgressUi(payload) {
            const progressBar = document.getElementById('delete-progress-bar');
            const progressValue = document.getElementById('delete-progress-value');
            const progressText = document.getElementById('delete-progress-text');
            const progressDesc = document.getElementById('delete-progress-desc');
            const progressMeta = document.getElementById('delete-progress-meta');
            const percent = Math.max(0, Math.min(100, Number(payload?.progress_percent || 0)));
            const waitingOnBatch = !!payload?.is_waiting_on_batch;
            const currentScope = Math.min(Math.max(1, Number(payload?.scope_count || 1)), Math.max(1, Number(payload?.current_scope_index || 0) + 1));
            const activeBatchSize = Math.max(0, Number(payload?.active_batch_size || payload?.chunk_size || 0));
            const lastBatchDeletedRows = Math.max(0, Number(payload?.last_batch_deleted_rows || 0));
            const errorCode = payload?.error_code ? `Kode ${escapeHtml(payload.error_code)}` : '';
            if (progressBar) {
                progressBar.classList.remove('report-management-progress__bar--indeterminate');
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.remove('progress-bar-striped');
                progressBar.style.width = percent + '%';
                progressBar.innerText = '';
                progressBar.setAttribute('aria-valuetext', waitingOnBatch ? `Progress riil ${percent}% - batch sedang diproses` : `${percent}%`);
            }
            if (progressValue) progressValue.innerText = `${percent}%`;
            if (progressText) progressText.innerText = payload?.message || 'Memproses delete...';
            if (progressDesc) progressDesc.innerHTML = `Terhapus <b>${formatNumber(payload?.deleted_rows || 0)}</b> dari <b>${formatNumber(payload?.total_rows || 0)}</b> baris.`;
            if (progressMeta) {
                if (payload?.status === 'failed') {
                    progressMeta.innerText = [errorCode, payload?.error || 'Delete gagal diproses.'].filter(Boolean).join(' - ');
                } else if ((payload?.stage || '') === 'queued') {
                    progressMeta.innerText = 'Menunggu queue worker. Fallback controller akan mengambil alih bila progres tidak bergerak.';
                } else if (waitingOnBatch) {
                    progressMeta.innerText = `Memproses batch ${formatNumber(activeBatchSize)} baris - Grup ${formatNumber(currentScope)}/${formatNumber(payload?.scope_count || 1)}`;
                } else if ((payload?.stage || '') === 'cleanup') {
                    progressMeta.innerText = 'Delete sumber selesai, membersihkan snapshot...';
                } else if ((payload?.stage || '') === 'syncing') {
                    progressMeta.innerText = 'Menyegarkan statistik dan cache...';
                } else if (lastBatchDeletedRows > 0) {
                    progressMeta.innerText = `Batch terakhir menghapus ${formatNumber(lastBatchDeletedRows)} baris.`;
                } else {
                    progressMeta.innerText = '';
                }
            }
        }

        async function getJson(url) {
            const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            let data = {};
            try { data = await response.json(); } catch (_) { data = {}; }
            if (!response.ok && data.status !== 'warning') throw new Error(data.message || 'Gagal mengambil status delete.');
            return data;
        }

        async function runDeleteProgress(processUrl, statusUrl, initialPayload) {
            themedSwal({
                title: 'Memproses Delete',
                html: `<div class="text-center mb-3"><span style="font-size: 14px; color: #64748b;" id="delete-progress-desc">Menginisialisasi delete bertahap...</span></div><div class="progress report-management-progress"><div id="delete-progress-bar" class="progress-bar report-management-progress__bar" role="progressbar" style="width: 0%;"></div></div><div class="text-center mt-2"><small id="delete-progress-value" class="report-management-progress__value">0%</small></div><div class="text-center mt-3"><small id="delete-progress-text" class="report-management-progress__text">Menyiapkan chunk pertama...</small></div><div class="text-center mt-2"><small id="delete-progress-meta" class="report-management-progress__meta"></small></div>`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                width: 520,
            });
            await new Promise(resolve => setTimeout(resolve, 30));
            let finalPayload = initialPayload;
            let lastProcessAttemptAt = 0;
            updateDeleteProgressUi(finalPayload);
            try {
                while (true) {
                    if (!statusUrl && !processUrl) throw new Error('Endpoint progress delete tidak tersedia.');
                    if (statusUrl) {
                        finalPayload = await getJson(statusUrl);
                    } else if (processUrl) {
                        finalPayload = await postJson(processUrl, {});
                    }
                    const canUseFallback = !!processUrl
                        && !!finalPayload?.can_process_fallback
                        && (Date.now() - lastProcessAttemptAt) >= 1500
                        && !['completed', 'warning', 'failed'].includes(finalPayload?.status);
                    if (canUseFallback) {
                        lastProcessAttemptAt = Date.now();
                        finalPayload = await postJson(processUrl, {});
                    }
                    updateDeleteProgressUi(finalPayload);
                    if (['completed', 'warning', 'failed'].includes(finalPayload.status)) {
                        Swal.close();
                        return finalPayload;
                    }
                    await new Promise(resolve => setTimeout(resolve, 700));
                }
            } catch (error) {
                Swal.close();
                throw error;
            }
        }

        function setNotice(type, message) {
            if (!managementNotice) return;
            if (!message) {
                managementNotice.className = 'report-management-notice d-none';
                managementNotice.innerHTML = '';
                return;
            }
            managementNotice.className = 'report-management-notice report-management-notice--' + type;
            managementNotice.innerHTML = message;
        }

        function updateSummary(rows, meta = {}) {
            const label = managementReportSelect?.options?.[managementReportSelect.selectedIndex]?.text || 'Belum dipilih';
            const groups = Number(meta.total_groups ?? (Array.isArray(rows) ? rows.length : 0));
            const rowsCount = Number(meta.grand_total_rows ?? 0);
            if (summaryReport) summaryReport.textContent = label;
            if (summaryGroups) summaryGroups.textContent = formatNumber(groups);
            if (summaryRows) summaryRows.textContent = formatNumber(rowsCount);
        }

        function decodeScopeDataset(element) {
            if (!element) return null;
            const period = decodeURIComponent(element.getAttribute('data-period') || '');
            const periodLabel = decodeURIComponent(element.getAttribute('data-period-label') || period || '');
            const kanca = decodeURIComponent(element.getAttribute('data-kanca') || '');
            const kancaLabel = decodeURIComponent(element.getAttribute('data-kanca-label') || kanca || '');
            const periodIsNull = element.getAttribute('data-period-is-null') === '1';
            const kancaIsNull = element.getAttribute('data-kanca-is-null') === '1';
            return { period: periodIsNull ? '' : period, period_filter: periodIsNull ? '' : period, kanca: kancaIsNull ? '' : kanca, kanca_filter: kancaIsNull ? '' : kanca, row_count: Number(element.getAttribute('data-row-count') || 0), period_is_null: periodIsNull, kanca_is_null: kancaIsNull, period_label: periodLabel || '(Blank)', kanca_label: kancaLabel || '(Blank)' };
        }

        function getSelectedScopeCheckboxes() { return Array.from(managementTableBody?.querySelectorAll('.management-row-checkbox:checked') || []); }
        function setRowVisualState(element, checked) { const row = element?.closest('.management-data-row'); if (row) row.classList.toggle('is-selected', !!checked); }
        function setScopeSelection(scope, checked) { const scopeKey = createScopeKey(scope); if (!scopeKey) return; if (checked) managementState.selectedScopes.set(scopeKey, Object.assign({}, scope)); else managementState.selectedScopes.delete(scopeKey); }

        function updateSelectionToast() {
            const selectedScopes = Array.from(managementState.selectedScopes.values());
            const selectedCount = selectedScopes.length;
            const selectedRows = selectedScopes.reduce((sum, scope) => sum + Number(scope.row_count || 0), 0);
            if (managementSelectionToastText) managementSelectionToastText.textContent = `${formatNumber(selectedCount)} grup dipilih`;
            if (managementSelectionToastSubtext) managementSelectionToastSubtext.textContent = `${formatNumber(selectedRows)} baris siap dihapus`;
            if (btnDeleteSelected) btnDeleteSelected.disabled = selectedCount === 0;
            if (btnClearSelected) btnClearSelected.disabled = selectedCount === 0;
            if (managementSelectionToast) managementSelectionToast.classList.toggle('d-none', selectedCount === 0);
        }

        function syncBulkSelectionUi() {
            const allCheckboxes = Array.from(managementTableBody?.querySelectorAll('.management-row-checkbox') || []);
            const selectedCheckboxes = getSelectedScopeCheckboxes();
            const totalCount = allCheckboxes.length;
            if (managementSelectAll) {
                managementSelectAll.disabled = totalCount === 0;
                managementSelectAll.checked = totalCount > 0 && selectedCheckboxes.length === totalCount;
                managementSelectAll.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < totalCount;
            }
            Array.from(managementTableBody?.querySelectorAll('.management-period-checkbox') || []).forEach(function (periodCheckbox) {
                const bucket = periodCheckbox.getAttribute('data-period-bucket') || '';
                const periodRows = Array.from(managementTableBody?.querySelectorAll(`.management-row-checkbox[data-period-bucket="${bucket}"]`) || []);
                const checkedRows = periodRows.filter(item => item.checked);
                periodCheckbox.checked = periodRows.length > 0 && checkedRows.length === periodRows.length;
                periodCheckbox.indeterminate = checkedRows.length > 0 && checkedRows.length < periodRows.length;
            });
            allCheckboxes.forEach(function (checkbox) { setRowVisualState(checkbox, checkbox.checked); });
            updateSelectionToast();
        }

        function renderPagination(pagination = {}) {
            if (!managementPagination) return;
            const totalPages = Number(pagination.total_pages || 0);
            if (totalPages <= 1) {
                managementPagination.classList.add('d-none');
                managementPagination.innerHTML = '';
                return;
            }
            const currentPage = Number(pagination.current_page || 1);
            const buttons = [];
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            buttons.push(`<button type="button" class="report-management-page-btn" data-page="${currentPage - 1}" ${pagination.has_prev ? '' : 'disabled'}><i class="fas fa-chevron-left"></i></button>`);
            for (let page = startPage; page <= endPage; page++) {
                buttons.push(`<button type="button" class="report-management-page-btn ${page === currentPage ? 'is-active' : ''}" data-page="${page}">${page}</button>`);
            }
            buttons.push(`<button type="button" class="report-management-page-btn" data-page="${currentPage + 1}" ${pagination.has_next ? '' : 'disabled'}><i class="fas fa-chevron-right"></i></button>`);
            managementPagination.classList.remove('d-none');
            managementPagination.innerHTML = `<div class="report-management-pagination__meta">Menampilkan periode ${formatNumber(pagination.from_period || 0)}-${formatNumber(pagination.to_period || 0)} dari ${formatNumber(pagination.total_periods || 0)} periode</div><div class="report-management-pagination__actions">${buttons.join('')}</div>`;
        }

        function renderManagementRows(periods, meta = {}) {
            if (!managementTableBody) return;
            updateSummary(meta.rows || [], meta);
            renderPagination(meta.pagination || {});
            if (!Array.isArray(periods) || periods.length === 0) {
                managementTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada data untuk kriteria ini.</td></tr>';
                syncBulkSelectionUi();
                return;
            }
            managementTableBody.innerHTML = periods.map(function (periodGroup) {
                const periodFilter = String(periodGroup.period ?? '');
                const periodLabel = normalizeShortDateLabel(periodGroup.period_label ?? periodGroup.period ?? '(Blank)');
                const periodBucket = encodeURIComponent(createPeriodBucketKey(periodLabel, !!periodGroup.period_is_null));
                const periodMeta = `${formatNumber(periodGroup.group_count || 0)} grup - ${formatNumber(periodGroup.total_rows || 0)} baris`;
                const periodRows = Array.isArray(periodGroup.rows) ? periodGroup.rows : [];
                const renderedRows = periodRows.map(function (row) {
                    const rowPeriodFilter = String(row.period ?? '');
                    const rowPeriodLabel = normalizeShortDateLabel(row.period_label ?? row.period ?? '(Blank)');
                    const kanca = row.kanca ?? '';
                    const kancaLabel = row.kanca_label ?? kanca ?? '(Blank)';
                    const total = formatNumber(row.row_count || 0);
                    const periodIsNull = row.period_is_null ? '1' : '0';
                    const kancaIsNull = row.kanca_is_null ? '1' : '0';
                    const periodEncoded = encodeURIComponent(String(rowPeriodFilter));
                    const periodLabelEncoded = encodeURIComponent(String(rowPeriodLabel));
                    const kancaEncoded = encodeURIComponent(String(kanca));
                    const kancaLabelEncoded = encodeURIComponent(String(kancaLabel));
                    const rowCount = Number(row.row_count || 0);
                    const isChecked = managementState.selectedScopes.has(createScopeKey({ period_filter: row.period_is_null ? '' : String(rowPeriodFilter), kanca_filter: row.kanca_is_null ? '' : String(kanca), period_is_null: !!row.period_is_null, kanca_is_null: !!row.kanca_is_null }));
                    return `<tr class="management-data-row" data-period="${periodEncoded}" data-period-label="${periodLabelEncoded}" data-kanca="${kancaEncoded}" data-kanca-label="${kancaLabelEncoded}" data-row-count="${rowCount}" data-period-is-null="${periodIsNull}" data-kanca-is-null="${kancaIsNull}"><td class="text-center report-management-col-check"><input type="checkbox" class="management-row-checkbox" data-period="${periodEncoded}" data-period-label="${periodLabelEncoded}" data-kanca="${kancaEncoded}" data-kanca-label="${kancaLabelEncoded}" data-row-count="${rowCount}" data-period-is-null="${periodIsNull}" data-kanca-is-null="${kancaIsNull}" data-period-bucket="${periodBucket}" ${isChecked ? 'checked' : ''}></td><td><span class="report-management-primary">${escapeHtml(kancaLabel)}</span></td><td class="text-right"><span class="report-management-count">${total}</span></td><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-management-delete report-management-delete-btn" data-period="${periodEncoded}" data-period-label="${periodLabelEncoded}" data-kanca="${kancaEncoded}" data-kanca-label="${kancaLabelEncoded}" data-row-count="${rowCount}" data-period-is-null="${periodIsNull}" data-kanca-is-null="${kancaIsNull}"><i class="fas fa-trash-alt mr-1"></i> Delete</button></td></tr>`;
                }).join('');
                return `<tr class="report-management-period-row"><td colspan="4"><div class="report-management-period-card"><div><div class="report-management-period-card__title">${escapeHtml(periodLabel)}</div><div class="report-management-period-card__meta">${periodMeta}</div></div><label class="report-management-period-card__toggle"><input type="checkbox" class="management-period-checkbox" data-period-bucket="${periodBucket}"><span>Pilih semua periode ini</span></label></div></td></tr>${renderedRows}`;
            }).join('');
            syncBulkSelectionUi();
        }

        async function postJson(url, payload) {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(payload) });
            let data = {};
            try { data = await response.json(); } catch (_) { data = {}; }
            if (!response.ok && data.status !== 'warning') throw new Error(data.message || 'Terjadi kesalahan pada server.');
            return data;
        }

        async function fetchManagementData(page = 1) {
            if (!managementReportSelect || !managementReportSelect.value) {
                themedSwal({ icon: 'warning', title: 'Pilih Report', text: 'Silakan pilih report terlebih dahulu.' });
                return;
            }
            const fetchUrl = reportManagementCard?.dataset.fetchUrl;
            if (!fetchUrl) return;
            setNotice('', '');
            managementTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Memuat data...</td></tr>';
            const payload = await postJson(fetchUrl, { id_report: managementReportSelect.value, page: page, per_page: managementState.perPage });
            if (payload.status !== 'success') throw new Error(payload.message || 'Gagal memuat data report management.');
            managementState.currentPage = Number(payload.pagination?.current_page || page || 1);
            setNotice(payload.truncated ? 'warning' : 'info', payload.truncated ? 'Daftar grup dibatasi oleh server untuk menjaga performa. Pagination diterapkan pada hasil grouping yang berhasil dimuat.' : 'Data siap dikelola. Gunakan klik baris, centang per periode, dan pagination agar review data tetap ringkas.');
            renderManagementRows(payload.periods || [], Object.assign({}, payload, { rows: payload.rows || [] }));
        }

        async function deleteManagedScopes(scopes) {
            const deleteUrl = reportManagementCard?.dataset.deleteUrl;
            const processUrlTemplate = reportManagementCard?.dataset.deleteProcessUrlTemplate;
            const statusUrlTemplate = reportManagementCard?.dataset.deleteStatusUrlTemplate;
            if (!deleteUrl || !managementReportSelect || !managementReportSelect.value) return;
            if (!Array.isArray(scopes) || scopes.length === 0) {
                themedSwal({ icon: 'warning', title: 'Pilih Data', text: 'Pilih minimal satu grup yang ingin dihapus.' });
                return;
            }
            const previewItems = scopes.slice(0, 5).map(function (scope) {
                const rowCount = formatNumber(scope.row_count || 0);
                return `<li><b>${escapeHtml(scope.period_label || '(Blank)')}</b> | ${escapeHtml(scope.kanca_label || '(Blank)')} <span class="text-muted">(${rowCount} baris)</span></li>`;
            }).join('');
            const extraInfo = scopes.length > 5 ? `<div class="mt-2 text-muted">+${formatNumber(scopes.length - 5)} grup lainnya</div>` : '';
            const selectedRows = scopes.reduce((sum, scope) => sum + Number(scope.row_count || 0), 0);
            const confirm = await themedSwal({ icon: 'warning', title: 'Hapus Data?', html: `Data akan dihapus untuk <b>${formatNumber(scopes.length)}</b> grup dengan total <b>${formatNumber(selectedRows)}</b> baris terpilih:<ul class="text-left mb-0 pl-4">${previewItems}</ul>${extraInfo}`, showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' });
            if (!confirm.isConfirmed) return;
            const deletePayload = { id_report: managementReportSelect.value, scopes: scopes.map(function (scope) { return { period_filter: scope.period_filter || scope.period || '', period_label: scope.period_label || '', kanca_filter: scope.kanca_filter || scope.kanca || '', kanca_label: scope.kanca_label || '', period_is_null: !!scope.period_is_null, kanca_is_null: !!scope.kanca_is_null }; }) };
            let payload = await postJson(deleteUrl, deletePayload);
            if (payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus data report.');
            if (payload.status === 'completed') {
                scopes.forEach(function (scope) { managementState.selectedScopes.delete(createScopeKey(scope)); });
                await themedSwal({ icon: 'success', title: 'Selesai', text: payload.message || 'Tidak ada data yang perlu dihapus.' });
                await fetchManagementData(managementState.currentPage);
                return;
            }
            if (payload.status === 'warning' && payload.requires_force) {
                const candidateRows = formatNumber(payload.candidate_rows || 0);
                const forceConfirm = await themedSwal({ icon: 'warning', title: 'Data Sangat Besar', html: `Penghapusan ini akan menghapus sekitar <b>${candidateRows}</b> baris.<br>Lanjutkan hanya jika Anda yakin ingin menghapus seluruh grup tersebut.`, showCancelButton: true, confirmButtonText: 'Lanjutkan Delete', cancelButtonText: 'Batal' });
                if (!forceConfirm.isConfirmed) return;
                payload = await postJson(deleteUrl, Object.assign({}, deletePayload, { force: true }));
                if (payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus data report.');
            }
            if (payload.status === 'warning' && payload.requires_hard_force) {
                const candidateRows = formatNumber(payload.candidate_rows || 0);
                const tableTotalRows = formatNumber(payload.table_total_rows || 0);
                const ratioText = Number(payload.delete_ratio_percent || 0) + '%';
                const hardForceHtml = payload.full_table_scope
                    ? `Scope ini akan menghapus <b>seluruh isi tabel</b> (${tableTotalRows} baris).<br><br>Jika ini memang tujuan Anda, lanjutkan konfirmasi final untuk mengosongkan tabel.`
                    : `Delete ini mencakup <b>${candidateRows}</b> dari <b>${tableTotalRows}</b> baris (~ <b>${ratioText}</b>).<br>Guard keamanan aktif untuk memastikan penghapusan besar tetap disengaja.<br><br>Lanjutkan hanya jika benar-benar yakin.`;
                const hardForceConfirm = await themedSwal({ icon: 'warning', title: payload.full_table_scope ? 'Konfirmasi Kosongkan Tabel' : 'Konfirmasi Final Diperlukan', html: hardForceHtml, showCancelButton: true, confirmButtonText: payload.full_table_scope ? 'Ya, Kosongkan Tabel' : 'Ya, Hapus Besar', cancelButtonText: 'Batal' });
                if (!hardForceConfirm.isConfirmed) return;
                payload = await postJson(deleteUrl, Object.assign({}, deletePayload, { force: true, hard_force: true }));
                if (payload.status === 'error') throw new Error(payload.message || 'Gagal menghapus data report.');
            }
            if (!payload.delete_id || (!processUrlTemplate && !statusUrlTemplate)) throw new Error(payload.message || 'Delete progress tidak dapat dimulai.');
            const finalPayload = await runDeleteProgress(
                processUrlTemplate ? buildDeleteUrl(processUrlTemplate, payload.delete_id) : '',
                statusUrlTemplate ? buildDeleteUrl(statusUrlTemplate, payload.delete_id) : '',
                payload
            );
            if (finalPayload.status === 'failed') {
                const errorCode = finalPayload.error_code ? ` (${finalPayload.error_code})` : '';
                throw new Error((finalPayload.error || finalPayload.message || 'Terjadi kesalahan saat menghapus data.') + errorCode);
            }
            scopes.forEach(function (scope) { managementState.selectedScopes.delete(createScopeKey(scope)); });
            await themedSwal({ icon: finalPayload.status === 'warning' ? 'warning' : 'success', title: finalPayload.status === 'warning' ? 'Selesai dengan Catatan' : 'Berhasil', text: finalPayload.status === 'warning' ? (finalPayload.error || finalPayload.message || 'Delete selesai dengan catatan.') : `Data terhapus ${formatNumber(finalPayload.deleted_rows || 0)} baris. Snapshot, cache index, dan statistik optimizer sudah diperbarui.` });
            await fetchManagementData(managementState.currentPage);
        }

        async function deleteManagedRow(button) {
            const scope = decodeScopeDataset(button);
            if (!scope) return;
            await deleteManagedScopes([scope]);
        }

        btnManagementFilter?.addEventListener('click', async function () {
            try {
                btnManagementFilter.disabled = true;
                managementState.currentPage = 1;
                managementState.selectedScopes.clear();
                updateSelectionToast();
                await fetchManagementData(1);
            } catch (error) {
                themedSwal({ icon: 'error', title: 'Gagal Memuat Data', text: error.message || 'Terjadi kesalahan saat memuat data.' });
            } finally {
                btnManagementFilter.disabled = false;
            }
        });

        managementTableBody?.addEventListener('click', async function (event) {
            const button = event.target.closest('.btn-management-delete');
            if (!button) return;
            button.disabled = true;
            try {
                await deleteManagedRow(button);
            } catch (error) {
                themedSwal({ icon: 'error', title: 'Delete Gagal', text: error.message || 'Terjadi kesalahan saat menghapus data.' });
            } finally {
                button.disabled = false;
            }
        });

        managementSelectAll?.addEventListener('change', function () {
            const allCheckboxes = managementTableBody?.querySelectorAll('.management-row-checkbox') || [];
            allCheckboxes.forEach(function (checkbox) {
                checkbox.checked = !!managementSelectAll.checked;
                const scope = decodeScopeDataset(checkbox);
                if (scope) setScopeSelection(scope, checkbox.checked);
            });
            syncBulkSelectionUi();
        });

        managementTableBody?.addEventListener('change', function (event) {
            if (event.target.closest('.management-row-checkbox')) {
                const checkbox = event.target.closest('.management-row-checkbox');
                const scope = decodeScopeDataset(checkbox);
                if (scope) setScopeSelection(scope, checkbox.checked);
                syncBulkSelectionUi();
                return;
            }
            if (event.target.closest('.management-period-checkbox')) {
                const periodCheckbox = event.target.closest('.management-period-checkbox');
                const bucket = periodCheckbox.getAttribute('data-period-bucket') || '';
                const periodRows = Array.from(managementTableBody?.querySelectorAll(`.management-row-checkbox[data-period-bucket="${bucket}"]`) || []);
                periodRows.forEach(function (checkbox) {
                    checkbox.checked = !!periodCheckbox.checked;
                    const scope = decodeScopeDataset(checkbox);
                    if (scope) setScopeSelection(scope, checkbox.checked);
                });
                syncBulkSelectionUi();
            }
        });

        managementTableBody?.addEventListener('click', function (event) {
            const row = event.target.closest('.management-data-row');
            if (!row || event.target.closest('button, input, label, a')) return;
            const checkbox = row.querySelector('.management-row-checkbox');
            if (!checkbox) return;
            checkbox.checked = !checkbox.checked;
            const scope = decodeScopeDataset(checkbox);
            if (scope) setScopeSelection(scope, checkbox.checked);
            syncBulkSelectionUi();
        });

        btnDeleteSelected?.addEventListener('click', async function () {
            const selectedScopes = Array.from(managementState.selectedScopes.values());
            btnDeleteSelected.disabled = true;
            try {
                await deleteManagedScopes(selectedScopes);
            } catch (error) {
                themedSwal({ icon: 'error', title: 'Delete Gagal', text: error.message || 'Terjadi kesalahan saat menghapus data.' });
            } finally {
                updateSelectionToast();
            }
        });

        btnClearSelected?.addEventListener('click', function () {
            managementState.selectedScopes.clear();
            Array.from(managementTableBody?.querySelectorAll('.management-row-checkbox') || []).forEach(function (checkbox) { checkbox.checked = false; });
            syncBulkSelectionUi();
        });

        managementPagination?.addEventListener('click', async function (event) {
            const button = event.target.closest('.report-management-page-btn');
            if (!button || button.disabled) return;
            const targetPage = Number(button.getAttribute('data-page') || 1);
            if (!targetPage || targetPage === managementState.currentPage) return;
            try {
                await fetchManagementData(targetPage);
            } catch (error) {
                themedSwal({ icon: 'error', title: 'Gagal Memuat Halaman', text: error.message || 'Terjadi kesalahan saat memuat halaman data.' });
            }
        });

        managementReportSelect?.addEventListener('change', function () {
            managementState.currentPage = 1;
            managementState.selectedScopes.clear();
            if (managementTableBody) managementTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Pilih report lalu klik "Tampilkan Data".</td></tr>';
            if (managementPagination) {
                managementPagination.classList.add('d-none');
                managementPagination.innerHTML = '';
            }
            updateSummary([], {});
            updateSelectionToast();
        });
    });
</script>
<style>
    .report-management-hero{position:relative;overflow:hidden;border-radius:24px;padding:1.45rem 1.5rem;background:radial-gradient(circle at top right,rgba(37,99,235,.22),transparent 30%),linear-gradient(135deg,#f8fbff 0%,#eef4ff 46%,#dbeafe 100%);border:1px solid rgba(37,99,235,.16);box-shadow:0 22px 45px -32px rgba(29,78,216,.4)}
    .report-management-hero__glow{position:absolute;top:-48px;right:-30px;width:170px;height:170px;border-radius:999px;background:rgba(14,165,233,.16);filter:blur(10px)}
    .report-management-hero__eyebrow,.import-upload-card__eyebrow{display:inline-block;margin-bottom:.55rem;padding:.35rem .7rem;border-radius:999px;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .report-management-hero__eyebrow{color:#1d4ed8;background:rgba(255,255,255,.72);border:1px solid rgba(37,99,235,.16)}
    .report-management-hero__title{color:#0f3f8c;font-size:1.4rem;font-weight:800;letter-spacing:-.03em;margin-bottom:.35rem}
    .report-management-hero__text{color:#31527c;line-height:1.7;max-width:700px}
    .report-management-hero__badge{display:inline-flex;align-items:center;min-height:48px;padding:.8rem 1rem;border-radius:18px;background:rgba(255,255,255,.84);border:1px solid rgba(37,99,235,.14);color:#0f3f8c;font-weight:700;box-shadow:0 18px 32px -24px rgba(29,78,216,.3)}
    .import-upload-card{border-radius:26px;overflow:hidden;box-shadow:0 28px 60px -40px rgba(15,23,42,.32)!important}
    .import-upload-card__header{padding:1.45rem 1.5rem 1rem;background:radial-gradient(circle at top left,rgba(59,130,246,.09),transparent 28%),linear-gradient(180deg,#fff 0%,#f8fafc 100%)}
    .import-upload-card__eyebrow{color:#1d4ed8;background:rgba(37,99,235,.08)}
    .import-upload-card__subtitle{color:#64748b;max-width:700px;line-height:1.6}
    .import-upload-card__body{position:relative;padding:1.5rem 1.5rem 7rem}
    .report-management-filter-btn{min-height:48px;border-radius:16px}
    .report-management-stat{height:100%;padding:1rem 1.05rem;border-radius:20px;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid rgba(148,163,184,.22)}
    .report-management-stat small{display:block;color:#64748b;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.45rem}
    .report-management-stat strong{display:block;color:#0f172a;font-size:1.05rem;font-weight:800;line-height:1.45;word-break:break-word}
    .report-management-notice{margin-top:.25rem;padding:.95rem 1rem;border-radius:18px;font-size:.92rem;line-height:1.65}
    .report-management-notice--info{color:#0f3f8c;background:rgba(219,234,254,.72);border:1px solid rgba(96,165,250,.2)}
    .report-management-notice--warning{color:#92400e;background:rgba(254,243,199,.78);border:1px solid rgba(251,191,36,.25)}
    .report-management-bulkbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:.8rem 1rem;border-radius:16px;background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid rgba(148,163,184,.2)}
    .report-management-bulkbar__hint{font-size:.88rem;font-weight:600;color:#475569}
    .report-management-table-wrap{border:1px solid rgba(148,163,184,.18);border-radius:22px;overflow:hidden;background:#fff}
    .report-management-table thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef4ff 100%);border-bottom:1px solid rgba(148,163,184,.18);color:#334155;font-size:.8rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:1rem}
    .report-management-table tbody td{padding:1rem;border-top:1px solid rgba(226,232,240,.8);vertical-align:middle}
    .report-management-col-check{width:52px}
    .report-management-primary{color:#0f172a;font-weight:700}
    .report-management-count{display:inline-flex;align-items:center;justify-content:flex-end;min-width:72px;padding:.4rem .7rem;border-radius:999px;background:rgba(37,99,235,.08);color:#1d4ed8;font-weight:800}
    .report-management-delete-btn{min-width:118px;border-radius:14px;font-weight:700}
    .report-management-period-row td{padding:.9rem 1rem!important;background:linear-gradient(180deg,#f8fbff 0%,#f1f7ff 100%);border-top:1px solid rgba(191,219,254,.65)!important}
    .report-management-period-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
    .report-management-period-card__title{font-size:1rem;font-weight:800;color:#0f3f8c;letter-spacing:-.02em}
    .report-management-period-card__meta{font-size:.84rem;font-weight:700;color:#64748b}
    .report-management-period-card__toggle{display:inline-flex;align-items:center;gap:.55rem;margin:0;padding:.55rem .8rem;border-radius:999px;background:rgba(37,99,235,.08);color:#1d4ed8;font-weight:700;cursor:pointer}
    .management-data-row{cursor:pointer;transition:background-color .18s ease,box-shadow .18s ease}
    .management-data-row.is-selected{background:linear-gradient(180deg,rgba(219,234,254,.48) 0%,rgba(239,246,255,.78) 100%)}
    .management-data-row:hover{background-color:rgba(248,250,252,.92)}
    .report-management-pagination{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1rem}
    .report-management-pagination__meta{font-size:.9rem;font-weight:700;color:#475569}
    .report-management-pagination__actions{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}
    .report-management-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 .8rem;border:1px solid rgba(148,163,184,.28);border-radius:12px;background:#fff;color:#334155;font-weight:800}
    .report-management-page-btn.is-active{background:linear-gradient(135deg,#0f4c81,#1d4ed8);border-color:transparent;color:#fff;box-shadow:0 16px 32px -24px rgba(29,78,216,.55)}
    .report-management-page-btn:disabled{opacity:.45;cursor:not-allowed}
    .report-management-selection-toast-shell{position:fixed;right:1.5rem;bottom:1.5rem;z-index:1080;display:flex;justify-content:flex-end;align-items:flex-end;width:min(420px,calc(100vw - 3rem));max-width:calc(100vw - 3rem);margin:0;pointer-events:none}
    .report-management-selection-toast{position:relative;display:flex;align-items:center;justify-content:space-between;gap:1rem;width:100%;max-width:100%;margin-left:auto;padding:1rem 1.05rem;border-radius:20px;background:linear-gradient(135deg,#0a4f8f 0%,#1166b1 52%,#0f82c9 100%);color:#fff;box-shadow:0 26px 60px -28px rgba(8,47,73,.58);border:1px solid rgba(191,219,254,.24);pointer-events:auto}
    .report-management-selection-toast__body{min-width:0}
    .report-management-selection-toast__eyebrow{font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:rgba(219,234,254,.88)}
    .report-management-selection-toast__text{font-size:1rem;font-weight:800;line-height:1.35}
    .report-management-selection-toast__subtext{font-size:.84rem;font-weight:600;color:rgba(239,246,255,.9)}
    .report-management-selection-toast__actions{display:flex;align-items:center;gap:.55rem}
    .report-management-selection-toast__btn{border-radius:12px;font-weight:800;padding:.65rem .95rem}
    .report-management-selection-toast__btn--ghost{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);color:#fff}
    .report-management-selection-toast__btn--danger{background:#fff;border:1px solid rgba(255,255,255,.18);color:#0f3f8c}
    .report-management-progress{height:14px;border-radius:999px;background:linear-gradient(180deg,#dbe7ef 0%,#cfe0ea 100%);overflow:hidden;box-shadow:inset 0 1px 2px rgba(15,23,42,.08)}
    .report-management-progress__bar{height:100%;font-weight:700;font-size:12px;line-height:14px;background:linear-gradient(90deg,#0f766e 0%,#147a72 55%,#1a8b80 100%);box-shadow:0 0 0 1px rgba(15,118,110,.05) inset;transition:width .45s cubic-bezier(.22,1,.36,1)}
    .report-management-progress__value{display:inline-block;color:#0f172a;font-weight:800;letter-spacing:.04em}
    .report-management-progress__text{color:#0f766e;font-weight:700;letter-spacing:.02em}
    .report-management-progress__meta{display:block;color:#64748b;font-weight:600;letter-spacing:.01em;min-height:1.2rem}
    .swal-modern-popup{border:1px solid rgba(226,232,240,.95);border-radius:28px;padding:1.4rem 1.4rem 1.2rem;box-shadow:0 30px 80px -35px rgba(15,23,42,.35)}
    .swal-modern-title{color:#0f172a;font-weight:800;letter-spacing:-.02em}
    .swal-modern-html{color:#475569;font-size:.95rem;line-height:1.65}
    .swal-modern-confirm,.swal-modern-cancel{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:16px;font-weight:700;padding:.8rem 1.3rem}
    .swal-modern-confirm{background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;box-shadow:0 16px 34px -22px rgba(15,23,42,.45)}
    .swal-modern-cancel{background:#e2e8f0;color:#334155;margin-left:.5rem}
    @media (max-width:767.98px){.report-management-hero,.import-upload-card__header{padding-left:1rem;padding-right:1rem}.import-upload-card__body{padding:1rem 1rem 7.5rem}.report-management-hero__title{font-size:1.15rem}.report-management-hero__badge,.report-management-filter-btn{width:100%}.report-management-table thead th,.report-management-table tbody td{padding:.8rem}.report-management-bulkbar,.report-management-period-card,.report-management-selection-toast,.report-management-pagination{align-items:flex-start}.report-management-period-card__toggle,.report-management-selection-toast,.report-management-selection-toast__actions{width:100%}.report-management-selection-toast-shell{left:1rem;right:1rem;bottom:1rem;width:calc(100vw - 2rem);max-width:calc(100vw - 2rem)}.report-management-selection-toast{flex-direction:column}}
</style>
@endsection
