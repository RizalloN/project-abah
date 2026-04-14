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
            const isCancelled = payload?.status === 'cancelled' || payload?.stage === 'cancelled';
            if (progressBar) {
                progressBar.classList.remove('report-management-progress__bar--indeterminate');
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.remove('progress-bar-striped');
                progressBar.style.width = percent + '%';
                progressBar.innerText = '';
                progressBar.setAttribute('aria-valuetext', waitingOnBatch ? `Progress riil ${percent}% - batch sedang diproses` : `${percent}%`);
            }
            if (progressValue) progressValue.innerText = `${percent}%`;
            if (progressText) progressText.innerText = payload?.message || (isCancelled ? 'Delete dibatalkan.' : 'Memproses delete...');
            if (progressDesc) progressDesc.innerHTML = `Terhapus <b>${formatNumber(payload?.deleted_rows || 0)}</b> dari <b>${formatNumber(payload?.total_rows || 0)}</b> baris.`;
            if (progressMeta) {
                if (payload?.status === 'failed') {
                    progressMeta.innerText = [errorCode, payload?.error || 'Delete gagal diproses.'].filter(Boolean).join(' - ');
                } else if (isCancelled) {
                    progressMeta.innerText = 'Delete dibatalkan aman. Worker akan berhenti tanpa cleanup lanjutan.';
                } else if ((payload?.stage || '') === 'queued') {
                    progressMeta.innerText = 'Menunggu queue worker. Fallback controller akan mengambil alih bila progres tidak bergerak.';
                } else if (waitingOnBatch) {
                    progressMeta.innerText = `Memproses batch ${formatNumber(activeBatchSize)} baris - Grup ${formatNumber(currentScope)}/${formatNumber(payload?.scope_count || 1)}`;
                } else if ((payload?.stage || '') === 'cleanup') {
                    progressMeta.innerText = 'Delete sumber selesai, membersihkan snapshot...';
                } else if ((payload?.stage || '') === 'syncing') {
                    progressMeta.innerText = 'Membersihkan snapshot turunan, statistik, dan cache...';
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

        async function runDeleteProgress(processUrl, statusUrl, cancelUrl, initialPayload) {
            themedSwal({
                title: 'Memproses Delete',
                html: `<div class="text-center mb-3"><span style="font-size: 14px; color: #64748b;" id="delete-progress-desc">Menginisialisasi delete bertahap...</span></div><div class="progress report-management-progress"><div id="delete-progress-bar" class="progress-bar report-management-progress__bar" role="progressbar" style="width: 0%;"></div></div><div class="text-center mt-2"><small id="delete-progress-value" class="report-management-progress__value">0%</small></div><div class="text-center mt-3"><small id="delete-progress-text" class="report-management-progress__text">Menyiapkan chunk pertama...</small></div><div class="text-center mt-2"><small id="delete-progress-meta" class="report-management-progress__meta"></small></div><div class="text-center mt-3"><button type="button" class="btn btn-sm btn-outline-danger" id="delete-cancel-btn"><i class="fas fa-ban mr-1"></i> Batalkan Delete</button></div>`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                width: 520,
            });
            await new Promise(resolve => setTimeout(resolve, 30));
            let finalPayload = initialPayload;
            let lastProcessAttemptAt = 0;
            let cancelInFlight = false;
            updateDeleteProgressUi(finalPayload);
            const cancelButton = document.getElementById('delete-cancel-btn');
            if (cancelButton && cancelUrl) {
                cancelButton.addEventListener('click', async function () {
                    if (cancelInFlight || ['completed', 'warning', 'failed', 'cancelled'].includes(finalPayload?.status)) return;
                    cancelInFlight = true;
                    cancelButton.disabled = true;
                    cancelButton.innerText = 'Membatalkan...';
                    try {
                        finalPayload = await postJson(cancelUrl, {});
                        updateDeleteProgressUi(finalPayload);
                    } catch (error) {
                        cancelInFlight = false;
                        cancelButton.disabled = false;
                        cancelButton.innerText = 'Batalkan Delete';
                        themedSwal({ icon: 'error', title: 'Gagal Membatalkan', text: error.message || 'Pembatalan delete gagal diproses.' });
                    }
                });
            }
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
                        && (Date.now() - lastProcessAttemptAt) >= 250
                        && !['completed', 'warning', 'failed'].includes(finalPayload?.status);
                    if (canUseFallback) {
                        lastProcessAttemptAt = Date.now();
                        finalPayload = await postJson(processUrl, {});
                    }
                    updateDeleteProgressUi(finalPayload);
                    if (['completed', 'warning', 'failed', 'cancelled'].includes(finalPayload.status)) {
                        Swal.close();
                        return finalPayload;
                    }
                    await new Promise(resolve => setTimeout(resolve, 350));
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
            const recoveredWarning = data.status === 'failed' && Number(data.deleted_rows || 0) > 0;
            if (!response.ok && data.status !== 'warning' && !recoveredWarning) throw new Error(data.message || 'Terjadi kesalahan pada server.');
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
            const cancelUrlTemplate = reportManagementCard?.dataset.deleteCancelUrlTemplate;
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
                cancelUrlTemplate ? buildDeleteUrl(cancelUrlTemplate, payload.delete_id) : '',
                payload
            );
            const deletedRows = Number(finalPayload.deleted_rows || 0);
            const recoveredWarning = finalPayload.status === 'failed' && deletedRows > 0;
            const outcomeStatus = recoveredWarning ? 'warning' : finalPayload.status;
            if (outcomeStatus === 'failed') {
                const normalizedErrorCode = String(finalPayload.error_code ?? '').trim();
                const errorCode = normalizedErrorCode && normalizedErrorCode !== '0' ? ` (${normalizedErrorCode})` : '';
                throw new Error((finalPayload.error || finalPayload.message || 'Terjadi kesalahan saat menghapus data.') + errorCode);
            }
            scopes.forEach(function (scope) { managementState.selectedScopes.delete(createScopeKey(scope)); });
            await themedSwal({
                icon: outcomeStatus === 'warning' ? 'warning' : 'success',
                title: outcomeStatus === 'warning' ? 'Selesai dengan Catatan' : 'Berhasil',
                text: outcomeStatus === 'warning'
                    ? (finalPayload.error || finalPayload.message || `Data terhapus ${formatNumber(deletedRows)} baris, tetapi sinkronisasi lanjutan gagal.`)
                    : `Data terhapus ${formatNumber(deletedRows)} baris. Snapshot, cache index, dan statistik optimizer sudah diperbarui.`
            });
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
