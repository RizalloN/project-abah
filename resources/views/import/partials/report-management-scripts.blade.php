<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reportManagementCard = document.getElementById('report-management-card');
        const managementReportSelect = document.getElementById('management-report-select');
        const btnManagementFilter = document.getElementById('btn-management-filter');
        const btnManagementDeduplicate = document.getElementById('btn-management-deduplicate');
        const managementTableBody = document.getElementById('management-table-body');
        const managementPagination = document.getElementById('management-pagination');
        const managementNotice = document.getElementById('management-notice');
        const managementLoadProgress = document.getElementById('management-load-progress');
        const managementLoadTitle = document.getElementById('management-load-title');
        const managementLoadStage = document.getElementById('management-load-stage');
        const managementLoadProgressBar = document.getElementById('management-load-progress-bar');
        const managementLoadPercent = document.getElementById('management-load-percent');
        const managementLoadUnits = document.getElementById('management-load-units');
        const managementLoadText = document.getElementById('management-load-text');
        const managementLoadMeta = document.getElementById('management-load-meta');
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
        const managementState = { currentPage: 1, perPage: 8, selectedScopes: new Map(), isLoading: false, activeLoadId: null, loadToken: 0, directLoadTimer: null };

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
        function humanizeStage(stage) {
            const normalized = String(stage || '').trim().toLowerCase();
            const lookup = {
                queued: 'Queued',
                validating: 'Validasi',
                scanning_columns: 'Deteksi Kolom',
                grouping: 'Grouping',
                counting: 'Hitung Total',
                finalizing: 'Finalisasi',
                completed: 'Selesai',
                failed: 'Gagal',
            };
            return lookup[normalized] || 'Memuat';
        }
        function buildDeleteUrl(template, deleteId) { return String(template || '').replace('__DELETE_ID__', encodeURIComponent(deleteId)); }
        function normalizeExtraFilters(filters) {
            if (!Array.isArray(filters)) return [];
            return filters.map(function (filter) {
                return {
                    column: String(filter?.column || ''),
                    label: String(filter?.label || filter?.column || ''),
                    value: filter?.is_null ? '' : String(filter?.value ?? ''),
                    value_label: String(filter?.value_label ?? filter?.value ?? ''),
                    is_null: !!filter?.is_null,
                };
            }).filter(filter => filter.column !== '');
        }
        function encodeExtraFilters(filters) { return encodeURIComponent(JSON.stringify(normalizeExtraFilters(filters))); }
        function decodeExtraFilters(value) {
            try { return normalizeExtraFilters(JSON.parse(decodeURIComponent(value || '[]'))); } catch (_) { return []; }
        }
        function createScopeKey(scope) { return JSON.stringify([scope?.period_filter ?? scope?.period ?? '', scope?.kanca_filter ?? scope?.kanca ?? '', !!scope?.period_is_null, !!scope?.kanca_is_null, normalizeExtraFilters(scope?.extra_filters)]); }
        function createPeriodBucketKey(periodLabel, periodIsNull) { return periodIsNull ? '__blank__' : String(periodLabel || '(Tanpa Periode)'); }

        function setManagementLoadingState(isLoading) {
            managementState.isLoading = !!isLoading;
            if (btnManagementFilter) {
                btnManagementFilter.disabled = !!isLoading;
                btnManagementFilter.innerHTML = isLoading
                    ? '<i class="fas fa-spinner fa-spin mr-2"></i> Memuat Data...'
                    : '<i class="fas fa-filter mr-2"></i> Tampilkan Data';
            }
            if (managementReportSelect) {
                managementReportSelect.disabled = !!isLoading;
            }
            if (managementSelectAll) {
                managementSelectAll.disabled = !!isLoading || !(managementTableBody?.querySelector('.management-row-checkbox'));
            }
            if (btnDeleteSelected) {
                btnDeleteSelected.disabled = !!isLoading || managementState.selectedScopes.size === 0;
            }
            if (btnClearSelected) {
                btnClearSelected.disabled = !!isLoading || managementState.selectedScopes.size === 0;
            }
            Array.from(managementTableBody?.querySelectorAll('.btn-management-delete, .management-row-checkbox, .management-period-checkbox') || []).forEach(function (element) {
                element.disabled = !!isLoading;
            });
            if (btnManagementDeduplicate) {
                const selectedOption = managementReportSelect?.selectedOptions?.[0];
                const canDeduplicate = !!managementReportSelect?.value && String(selectedOption?.dataset?.tableName || '').trim() === 'simpanan_multipn';
                btnManagementDeduplicate.disabled = !!isLoading || !canDeduplicate;
            }
        }

        function setLoadingTableState(message) {
            if (!managementTableBody) return;
            managementTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">${escapeHtml(message || 'Memuat data...')}</td></tr>`;
            if (managementPagination) {
                managementPagination.classList.add('d-none');
                managementPagination.innerHTML = '';
            }
        }

        function showLoadProgress(payload) {
            if (!managementLoadProgress) return;

            if (managementState.autoHideTimer) {
                clearTimeout(managementState.autoHideTimer);
                managementState.autoHideTimer = null;
            }

            const percent = Math.max(0, Math.min(100, Number(payload?.progress_percent || 0)));
            const completedUnits = Math.max(0, Number(payload?.completed_units || 0));
            const totalUnits = Math.max(1, Number(payload?.total_units || 4));
            const stage = String(payload?.stage || 'queued');
            const status = String(payload?.status || '');
            const isIndeterminate = ['queued', 'grouping'].includes(stage) && !['completed', 'failed'].includes(status);

            console.log("Current Progress:", percent, "Status:", status);

            managementLoadProgress.classList.remove('d-none');
            if (managementLoadTitle) {
                managementLoadTitle.textContent = status === 'completed'
                    ? 'Data report management siap dipakai'
                    : 'Memuat data report management...';
            }
            if (managementLoadStage) managementLoadStage.textContent = humanizeStage(stage);
            if (managementLoadProgressBar) {
                managementLoadProgressBar.style.width = percent + '%';
                managementLoadProgressBar.classList.toggle('report-management-progress__bar--indeterminate', isIndeterminate);
                managementLoadProgressBar.setAttribute('aria-valuetext', `${percent}% - ${humanizeStage(stage)}`);
            }
            if (managementLoadPercent) managementLoadPercent.textContent = `${percent}%`;
            if (managementLoadUnits) managementLoadUnits.textContent = `${formatNumber(completedUnits)} / ${formatNumber(totalUnits)} tahap`;
            if (managementLoadText) managementLoadText.textContent = payload?.message || 'Memuat data report management...';
            if (managementLoadMeta) {
                if (status === 'completed') {
                    const result = payload?.result || {};
                    managementLoadMeta.textContent = `${formatNumber(result.total_groups || 0)} grup, ${formatNumber(result.grand_total_rows || 0)} baris sumber, halaman ${formatNumber(result.pagination?.current_page || 1)} siap ditampilkan.`;
                } else if (status === 'failed') {
                    managementLoadMeta.textContent = payload?.error || 'Load data report management gagal.';
                } else if (stage === 'grouping') {
                    managementLoadMeta.textContent = 'Database sedang menjalankan query grouping. Tahap ini bisa memakan waktu lebih lama pada report besar.';
                } else if (stage === 'counting') {
                    managementLoadMeta.textContent = 'Hasil grouping sudah didapat. Sistem sedang menghitung total baris dan pagination.';
                } else {
                    managementLoadMeta.textContent = '';
                }
            }

            if (status === 'completed' && percent === 100) {
                console.log("Timer auto-hide berjalan");
                managementState.autoHideTimer = setTimeout(() => {
                    hideLoadProgress();
                }, 2500);
            } else if (status === 'failed') {
                managementState.autoHideTimer = setTimeout(() => {
                    hideLoadProgress();
                }, 8000);
            }
        }

        function hideLoadProgress() {
            if (managementLoadProgress) {
                managementLoadProgress.classList.add('d-none');
            }
            if (managementState.autoHideTimer) {
                clearTimeout(managementState.autoHideTimer);
                managementState.autoHideTimer = null;
            }
        }

        function resetManagementStatusState() {
            hideLoadProgress();
            setNotice('', '');
        }

        function stopDirectLoadTimer() {
            if (managementState.directLoadTimer) {
                clearInterval(managementState.directLoadTimer);
                managementState.directLoadTimer = null;
            }
        }

        function startDirectLoadTimer(selectedLabel) {
            stopDirectLoadTimer();
            const startedAt = Date.now();
            const label = String(selectedLabel || 'report yang dipilih').trim();

            const update = function () {
                const elapsedMs = Math.max(0, Date.now() - startedAt);
                const elapsedSec = Math.floor(elapsedMs / 1000);
                let stage = 'validating';
                let completedUnits = 1;
                let percent = 12;
                let message = `Memvalidasi report ${label}...`;
                let meta = 'Mengecek konfigurasi report dan tabel sumber.';

                if (elapsedMs >= 900) {
                    stage = 'scanning_columns';
                    completedUnits = 2;
                    percent = 28;
                    message = 'Mendeteksi kolom periode dan kanca...';
                    meta = 'Menentukan kolom yang dipakai untuk grouping.';
                }

                if (elapsedMs >= 1800) {
                    stage = 'grouping';
                    completedUnits = 3;
                    percent = Math.min(88, 40 + Math.floor(elapsedSec * 2));
                    message = 'Menjalankan query grouping data report...';
                    meta = elapsedSec >= 10
                        ? `Query grouping masih berjalan selama ${formatNumber(elapsedSec)} detik. Report besar memang bisa lebih lama.`
                        : 'Database sedang menghitung grouping data. Tahap ini paling berat.';
                }

                showLoadProgress({
                    status: 'running',
                    stage: stage,
                    progress_percent: percent,
                    completed_units: completedUnits,
                    total_units: 4,
                    message: message,
                    error: null,
                });

                if (managementLoadMeta) {
                    managementLoadMeta.textContent = meta;
                }
            };

            update();
            managementState.directLoadTimer = setInterval(update, 400);
        }

        function updateDeleteProgressUi(payload) {
            const progressBar = document.getElementById('delete-progress-bar');
            const progressValue = document.getElementById('delete-progress-value');
            const progressText = document.getElementById('delete-progress-text');
            const progressDesc = document.getElementById('delete-progress-desc');
            const progressMeta = document.getElementById('delete-progress-meta');
            const deletePlan = String(payload?.delete_plan || 'normal');
            const problemSignature = String(payload?.problem_signature || '');
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
                    progressMeta.innerText = deletePlan === 'recovery_blank_scope'
                        ? 'Plan B recovery aktif. Sistem akan beralih ke delete scope langsung bila worker queue tidak bergerak.'
                        : 'Menunggu queue worker. Fallback controller akan mengambil alih bila progres tidak bergerak.';
                } else if (waitingOnBatch) {
                    progressMeta.innerText = deletePlan === 'recovery_blank_scope'
                        ? `Plan B recovery memproses batch ${formatNumber(activeBatchSize)} baris untuk scope blank/null.`
                        : `Memproses batch ${formatNumber(activeBatchSize)} baris - Grup ${formatNumber(currentScope)}/${formatNumber(payload?.scope_count || 1)}`;
                } else if ((payload?.stage || '') === 'cleanup') {
                    progressMeta.innerText = 'Delete sumber selesai, membersihkan snapshot...';
                } else if ((payload?.stage || '') === 'syncing') {
                    progressMeta.innerText = 'Membersihkan snapshot turunan, statistik, dan cache...';
                } else if (problemSignature) {
                    progressMeta.innerText = 'Scope anomali terdeteksi. Sistem menjalankan recovery lane khusus agar delete tetap aman.';
                } else if (lastBatchDeletedRows > 0) {
                    progressMeta.innerText = `Batch terakhir menghapus ${formatNumber(lastBatchDeletedRows)} baris.`;
                } else {
                    progressMeta.innerText = '';
                }
            }
        }

        function isSuccessLikeDeleteMessage(message) {
            const normalized = String(message || '').trim().toLowerCase();
            if (!normalized) return false;
            return normalized.startsWith('delete selesai.')
                || normalized.startsWith('delete sumber selesai')
                || normalized.includes('statistik dan cache sudah disegarkan')
                || normalized.includes('report ini tidak menggunakan snapshot/index');
        }

        function normalizeDeletePayload(payload) {
            if (!payload || typeof payload !== 'object') {
                return payload;
            }

            const normalized = Object.assign({}, payload);
            const deletedRows = Number(normalized.deleted_rows || 0);
            const successLikeMessage = isSuccessLikeDeleteMessage(normalized.message);

            if (normalized.status === 'failed' && successLikeMessage) {
                normalized.status = deletedRows > 0 ? 'warning' : 'completed';
                normalized.progress_percent = 100;
                if (!normalized.stage || normalized.stage === 'failed') {
                    normalized.stage = 'completed';
                }
            }

            return normalized;
        }

        async function getJson(url) {
            const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            let data = {};
            try { data = await response.json(); } catch (_) { data = {}; }
            data = normalizeDeletePayload(data);
            const successLikeDelete = isSuccessLikeDeleteMessage(data.message) || ['completed', 'warning'].includes(data.status);
            if (!response.ok && data.status !== 'warning' && !successLikeDelete) throw new Error(data.message || 'Gagal mengambil status proses.');
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
                        try {
                            finalPayload = normalizeDeletePayload(await getJson(statusUrl));
                        } catch (error) {
                            if (!processUrl) throw error;
                            lastProcessAttemptAt = Date.now();
                            finalPayload = normalizeDeletePayload(await postJson(processUrl, {}));
                        }
                    } else if (processUrl) {
                        finalPayload = normalizeDeletePayload(await postJson(processUrl, {}));
                    }
                    const canUseFallback = !!processUrl
                        && !!finalPayload?.can_process_fallback
                        && (Date.now() - lastProcessAttemptAt) >= 250
                        && !['completed', 'warning', 'failed'].includes(finalPayload?.status);
                    if (canUseFallback) {
                        lastProcessAttemptAt = Date.now();
                        finalPayload = normalizeDeletePayload(await postJson(processUrl, {}));
                    }
                    updateDeleteProgressUi(finalPayload);
                    if (['completed', 'warning', 'failed', 'cancelled'].includes(finalPayload.status)) {
                        finalPayload = normalizeDeletePayload(finalPayload);
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
            const extraFilters = decodeExtraFilters(element.getAttribute('data-extra-filters') || '');
            const periodIsNull = element.getAttribute('data-period-is-null') === '1';
            const kancaIsNull = element.getAttribute('data-kanca-is-null') === '1';
            return { period: periodIsNull ? '' : period, period_filter: periodIsNull ? '' : period, kanca: kancaIsNull ? '' : kanca, kanca_filter: kancaIsNull ? '' : kanca, row_count: Number(element.getAttribute('data-row-count') || 0), period_is_null: periodIsNull, kanca_is_null: kancaIsNull, period_label: periodLabel || '(Blank)', kanca_label: kancaLabel || '(Blank)', extra_filters: extraFilters };
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
            const totalPeriodsExact = pagination.total_periods_exact !== false;
            const buttons = [];
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            const lastButtonTarget = totalPeriodsExact ? '' : ' data-page-target="last"';
            const canGoLast = currentPage < totalPages || (!totalPeriodsExact && pagination.has_next);
            const jumpInputMax = totalPeriodsExact ? totalPages : Math.max(totalPages, currentPage + 1);
            const previousJumpValue = managementPagination.querySelector('.report-management-jump-input')?.value;
            const jumpInputValue = (previousJumpValue !== undefined && previousJumpValue !== '' && Number(previousJumpValue) >= 1)
                ? previousJumpValue
                : String(currentPage);

            buttons.push(`<button type="button" class="report-management-page-btn" data-page="1" ${currentPage > 1 ? '' : 'disabled'} title="Halaman Pertama"><i class="fas fa-angle-double-left"></i></button>`);
            buttons.push(`<button type="button" class="report-management-page-btn" data-page="${currentPage - 1}" ${pagination.has_prev ? '' : 'disabled'} title="Halaman Sebelumnya"><i class="fas fa-chevron-left"></i></button>`);
            for (let page = startPage; page <= endPage; page++) {
                buttons.push(`<button type="button" class="report-management-page-btn ${page === currentPage ? 'is-active' : ''}" data-page="${page}">${page}</button>`);
            }
            buttons.push(`<button type="button" class="report-management-page-btn" data-page="${currentPage + 1}" ${pagination.has_next ? '' : 'disabled'} title="Halaman Selanjutnya"><i class="fas fa-chevron-right"></i></button>`);
            buttons.push(`<button type="button" class="report-management-page-btn" data-page="${totalPages}"${lastButtonTarget} ${canGoLast ? '' : 'disabled'} title="Halaman Terakhir"><i class="fas fa-angle-double-right"></i></button>`);

            const jumpMaxAttr = totalPeriodsExact ? ` max="${jumpInputMax}"` : '';
            const jumpHint = totalPeriodsExact
                ? `1-${formatNumber(jumpInputMax)}`
                : `min. 1 (total belum pasti)`;
            buttons.push(`
                <div class="report-management-page-jump ml-3 d-inline-flex align-items-center">
                    <span class="mr-2 text-muted" style="font-size: 0.75rem; font-weight: 700;">Loncat ke:</span>
                    <input type="number" min="1"${jumpMaxAttr} class="form-control form-control-sm text-center report-management-jump-input" style="width: 64px; height: 32px; border-radius: 6px; padding: 0.2rem;" value="${jumpInputValue}" title="${jumpHint}" aria-label="Nomor halaman (${jumpHint})">
                    <button type="button" class="btn btn-sm btn-primary ml-1 report-management-jump-btn" style="height: 32px; border-radius: 6px; padding: 0 0.6rem;"><i class="fas fa-share"></i> Go</button>
                </div>
            `);

            managementPagination.classList.remove('d-none');
            managementPagination.innerHTML = `<div class="report-management-pagination__meta">Menampilkan periode ${formatNumber(pagination.from_period || 0)}-${formatNumber(pagination.to_period || 0)} dari ${formatNumber(pagination.total_periods || 0)} periode${totalPeriodsExact ? '' : ' (estimasi)'}</div><div class="report-management-pagination__actions">${buttons.join('')}</div>`;
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
                    const extraFilters = normalizeExtraFilters(row.extra_filters || []);
                    const extraFiltersEncoded = encodeExtraFilters(extraFilters);
                    const rowCount = Number(row.row_count || 0);
                    const isChecked = managementState.selectedScopes.has(createScopeKey({ period_filter: row.period_is_null ? '' : String(rowPeriodFilter), kanca_filter: row.kanca_is_null ? '' : String(kanca), period_is_null: !!row.period_is_null, kanca_is_null: !!row.kanca_is_null, extra_filters: extraFilters }));
                    return `<tr class="management-data-row" data-period="${periodEncoded}" data-period-label="${periodLabelEncoded}" data-kanca="${kancaEncoded}" data-kanca-label="${kancaLabelEncoded}" data-extra-filters="${extraFiltersEncoded}" data-row-count="${rowCount}" data-period-is-null="${periodIsNull}" data-kanca-is-null="${kancaIsNull}"><td class="text-center report-management-col-check"><input type="checkbox" class="management-row-checkbox" data-period="${periodEncoded}" data-period-label="${periodLabelEncoded}" data-kanca="${kancaEncoded}" data-kanca-label="${kancaLabelEncoded}" data-extra-filters="${extraFiltersEncoded}" data-row-count="${rowCount}" data-period-is-null="${periodIsNull}" data-kanca-is-null="${kancaIsNull}" data-period-bucket="${periodBucket}" ${isChecked ? 'checked' : ''}></td><td><span class="report-management-primary">${escapeHtml(kancaLabel)}</span></td><td class="text-right"><span class="report-management-count">${total}</span></td><td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-management-delete report-management-delete-btn" data-period="${periodEncoded}" data-period-label="${periodLabelEncoded}" data-kanca="${kancaEncoded}" data-kanca-label="${kancaLabelEncoded}" data-extra-filters="${extraFiltersEncoded}" data-row-count="${rowCount}" data-period-is-null="${periodIsNull}" data-kanca-is-null="${kancaIsNull}"><i class="fas fa-trash-alt mr-1"></i> Delete</button></td></tr>`;
                }).join('');
                return `<tr class="report-management-period-row"><td colspan="4"><div class="report-management-period-card"><div><div class="report-management-period-card__title">${escapeHtml(periodLabel)}</div><div class="report-management-period-card__meta">${periodMeta}</div></div><label class="report-management-period-card__toggle"><input type="checkbox" class="management-period-checkbox" data-period-bucket="${periodBucket}"><span>Pilih semua periode ini</span></label></div></td></tr>${renderedRows}`;
            }).join('');
            syncBulkSelectionUi();
        }

        async function postJson(url, payload, options = {}) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
                signal: options.signal,
            });
            let data = {};
            try { data = await response.json(); } catch (_) { data = {}; }
            data = normalizeDeletePayload(data);
            const recoveredWarning = data.status === 'failed' && Number(data.deleted_rows || 0) > 0;
            const successLikeDelete = isSuccessLikeDeleteMessage(data.message) || ['completed', 'warning'].includes(data.status);
            if (!response.ok && data.status !== 'warning' && !recoveredWarning && !successLikeDelete) throw new Error(data.message || 'Terjadi kesalahan pada server.');
            return data;
        }

        async function fetchManagementData(page = 1, options = {}) {
            if (!managementReportSelect || !managementReportSelect.value) {
                themedSwal({ icon: 'warning', title: 'Pilih Report', text: 'Silakan pilih report terlebih dahulu.' });
                return;
            }
            const fetchUrl = reportManagementCard?.dataset.fetchUrl;
            if (!fetchUrl) return;

            const token = managementState.loadToken + 1;
            managementState.loadToken = token;
            managementState.activeLoadId = null;
            const selectedLabel = managementReportSelect?.options?.[managementReportSelect.selectedIndex]?.text || 'report yang dipilih';
            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const timeoutId = setTimeout(function () {
                controller?.abort();
            }, 180000);

            try {
                resetManagementStatusState();
                setManagementLoadingState(true);
                managementState.selectedScopes.clear();
                updateSelectionToast();
                setLoadingTableState('Menjalankan load data report management...');
                startDirectLoadTimer(selectedLabel);

                const payload = await postJson(fetchUrl, {
                    id_report: managementReportSelect.value,
                    page: page,
                    per_page: managementState.perPage,
                    page_target: options.pageTarget || undefined,
                }, { signal: controller?.signal });

                if (token !== managementState.loadToken) {
                    return;
                }

                if (payload.status !== 'success') {
                    throw new Error(payload.message || 'Gagal memuat data report management.');
                }

                stopDirectLoadTimer();
                showLoadProgress({
                    status: 'completed',
                    stage: 'completed',
                    progress_percent: 100,
                    completed_units: 4,
                    total_units: 4,
                    message: 'Data report management selesai dimuat.',
                    result: payload,
                });

                managementState.currentPage = Number(payload.pagination?.current_page || page || 1);
                setNotice(payload.truncated ? 'warning' : 'info', payload.truncated ? 'Daftar grup dibatasi oleh server untuk menjaga performa. Pagination diterapkan pada hasil grouping yang berhasil dimuat.' : 'Data siap dikelola. Gunakan klik baris, centang per periode, dan pagination agar review data tetap ringkas.');
                renderManagementRows(payload.periods || [], Object.assign({}, payload, { rows: payload.rows || [] }));
            } catch (error) {
                stopDirectLoadTimer();
                const isAbort = error?.name === 'AbortError';
                showLoadProgress({
                    status: 'failed',
                    stage: 'failed',
                    progress_percent: 100,
                    completed_units: 4,
                    total_units: 4,
                    message: isAbort
                        ? 'Load data dihentikan karena melewati batas waktu aman.'
                        : 'Load data report management gagal.',
                    error: isAbort
                        ? 'Request load report management dihentikan otomatis setelah 180 detik agar tidak menggantung tanpa kepastian.'
                        : (error?.message || 'Terjadi kesalahan saat memuat data report management.'),
                });
                setLoadingTableState(isAbort ? 'Load data dihentikan karena timeout aman.' : 'Gagal memuat data report management.');
                throw isAbort
                    ? new Error('Load data report management melebihi 180 detik dan dihentikan otomatis agar tidak menggantung.')
                    : error;
            } finally {
                clearTimeout(timeoutId);
                if (token === managementState.loadToken) {
                    setManagementLoadingState(false);
                }
            }
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
            const deletePayload = { id_report: managementReportSelect.value, scopes: scopes.map(function (scope) { return { period_filter: scope.period_filter || scope.period || '', period_label: scope.period_label || '', kanca_filter: scope.kanca_filter || scope.kanca || '', kanca_label: scope.kanca_label || '', period_is_null: !!scope.period_is_null, kanca_is_null: !!scope.kanca_is_null, extra_filters: normalizeExtraFilters(scope.extra_filters || []) }; }) };
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
            const normalizedFinalPayload = normalizeDeletePayload(finalPayload);
            const deletedRows = Number(normalizedFinalPayload.deleted_rows || 0);
            const recoveredWarning = normalizedFinalPayload.status === 'failed' && deletedRows > 0;
            const outcomeStatus = recoveredWarning ? 'warning' : normalizedFinalPayload.status;
            if (outcomeStatus === 'failed') {
                const normalizedErrorCode = String(normalizedFinalPayload.error_code ?? '').trim();
                const errorCode = normalizedErrorCode && normalizedErrorCode !== '0' ? ` (${normalizedErrorCode})` : '';
                throw new Error((normalizedFinalPayload.error || normalizedFinalPayload.message || 'Terjadi kesalahan saat menghapus data.') + errorCode);
            }
            scopes.forEach(function (scope) { managementState.selectedScopes.delete(createScopeKey(scope)); });
            await themedSwal({
                icon: outcomeStatus === 'warning' ? 'warning' : 'success',
                title: outcomeStatus === 'warning' ? 'Selesai dengan Catatan' : 'Berhasil',
                text: outcomeStatus === 'warning'
                    ? (normalizedFinalPayload.error || normalizedFinalPayload.message || `Data terhapus ${formatNumber(deletedRows)} baris, tetapi sinkronisasi lanjutan gagal.`)
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
                managementState.currentPage = 1;
                await fetchManagementData(1);
            } catch (error) {
                themedSwal({ icon: 'error', title: 'Gagal Memuat Data', text: error.message || 'Terjadi kesalahan saat memuat data.' });
            } finally {
                setManagementLoadingState(false);
            }
        });

        managementReportSelect?.addEventListener('change', function () {
            resetManagementStatusState();
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
            if (managementState.isLoading) return;
            const allCheckboxes = managementTableBody?.querySelectorAll('.management-row-checkbox') || [];
            allCheckboxes.forEach(function (checkbox) {
                checkbox.checked = !!managementSelectAll.checked;
                const scope = decodeScopeDataset(checkbox);
                if (scope) setScopeSelection(scope, checkbox.checked);
            });
            syncBulkSelectionUi();
        });

        managementTableBody?.addEventListener('change', function (event) {
            if (managementState.isLoading) return;
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
            if (managementState.isLoading) return;
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
            if (managementState.isLoading) return;
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
            if (managementState.isLoading) return;
            managementState.selectedScopes.clear();
            Array.from(managementTableBody?.querySelectorAll('.management-row-checkbox') || []).forEach(function (checkbox) { checkbox.checked = false; });
            syncBulkSelectionUi();
        });

        managementPagination?.addEventListener('click', async function (event) {
            if (managementState.isLoading) return;

            const jumpBtn = event.target.closest('.report-management-jump-btn');
            if (jumpBtn) {
                const input = managementPagination.querySelector('.report-management-jump-input');
                const rawValue = String(input?.value ?? '').trim();
                if (rawValue === '' || !/^\d+$/.test(rawValue)) {
                    input?.classList.add('is-invalid');
                    input?.focus();
                    themedSwal({ icon: 'warning', title: 'Nomor Halaman Kosong', text: 'Masukkan nomor halaman yang valid sebelum klik Go.' });
                    return;
                }
                let targetPage = Number(rawValue);
                const declaredMax = Number(input?.max || 0);
                const minPage = 1;
                if (targetPage < minPage) targetPage = minPage;
                if (declaredMax > 0 && targetPage > declaredMax) targetPage = declaredMax;
                if (input) {
                    input.value = String(targetPage);
                    input.classList.remove('is-invalid');
                }
                if (targetPage === managementState.currentPage) {
                    themedSwal({ icon: 'info', title: 'Sudah di Halaman Ini', text: `Anda sedang di halaman ${targetPage}. Ketik nomor lain lalu klik Go.`, timer: 2500, showConfirmButton: false });
                    return;
                }

                const previousLabel = jumpBtn.innerHTML;
                jumpBtn.disabled = true;
                jumpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                try {
                    await fetchManagementData(targetPage);
                } catch (error) {
                    themedSwal({ icon: 'error', title: 'Gagal Memuat Halaman', text: error.message || 'Terjadi kesalahan saat memuat halaman data.' });
                } finally {
                    setManagementLoadingState(false);
                    if (jumpBtn.isConnected) {
                        jumpBtn.disabled = false;
                        jumpBtn.innerHTML = previousLabel;
                    }
                }
                return;
            }

            const button = event.target.closest('.report-management-page-btn');
            if (!button || button.disabled) return;
            const targetPage = Number(button.getAttribute('data-page') || 1);
            const pageTarget = String(button.getAttribute('data-page-target') || '').trim();
            if (!targetPage || (targetPage === managementState.currentPage && pageTarget !== 'last')) return;
            try {
                await fetchManagementData(targetPage, { pageTarget: pageTarget || undefined });
            } catch (error) {
                themedSwal({ icon: 'error', title: 'Gagal Memuat Halaman', text: error.message || 'Terjadi kesalahan saat memuat halaman data.' });
            } finally {
                setManagementLoadingState(false);
            }
        });

        managementPagination?.addEventListener('input', function (event) {
            const target = event.target;
            if (target?.classList?.contains('report-management-jump-input')) {
                target.classList.remove('is-invalid');
            }
        });

        managementPagination?.addEventListener('keypress', function(event) {
            if (event.target.classList.contains('report-management-jump-input') && event.key === 'Enter') {
                event.preventDefault();
                const btn = managementPagination.querySelector('.report-management-jump-btn');
                if (btn) btn.click();
            }
        });

        managementReportSelect?.addEventListener('change', function () {
            managementState.loadToken += 1;
            managementState.isLoading = false;
            managementState.currentPage = 1;
            managementState.activeLoadId = null;
            managementState.selectedScopes.clear();
            stopDirectLoadTimer();
            if (managementTableBody) managementTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Pilih report lalu klik "Tampilkan Data".</td></tr>';
            if (managementPagination) {
                managementPagination.classList.add('d-none');
                managementPagination.innerHTML = '';
            }
            hideLoadProgress();
            setManagementLoadingState(false);
            updateSummary([], {});
            updateSelectionToast();
        });
    });
</script>
