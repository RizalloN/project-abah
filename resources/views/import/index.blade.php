@extends('layouts.admin')

@section('title', 'Import Data')

@section('content')

<div class="import-template-banner mb-4">
    <div class="import-template-banner__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="import-template-banner__content pr-3">
            <span class="import-template-banner__eyebrow">Template Siap Pakai</span>
            <div class="import-template-banner__title">
                <i class="fas fa-download mr-2"></i> Template Import
            </div>
            <p class="import-template-banner__text mb-0">Pilih report, unduh template, lalu isi datanya.</p>
        </div>
        <div class="import-template-banner__actions mt-3 mt-md-0">
            <div class="import-template-banner__download-group">
                <select id="download-template-select" class="form-control import-template-banner__select">
                    <option value="">-- Pilih Template --</option>
                    @foreach($downloadTemplates as $key => $template)
                        <option value="{{ $key }}" data-filename="{{ $template['filename'] }}">{{ $template['label'] }}</option>
                    @endforeach
                </select>
                <a href="#"
                   id="btn-download-template"
                   class="btn import-template-banner__button disabled"
                   aria-disabled="true"
                   data-route-template="{{ route('import.template') }}">
                    <i class="fas fa-file-download mr-2"></i> Unduh Template
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm import-upload-card border-0">
    <div class="card-header bg-white border-0 import-upload-card__header">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <span class="import-upload-card__eyebrow">Import Data</span>
                <h5 class="card-title font-weight-bold text-dark mb-1">
                    <i class="fas fa-cloud-upload-alt text-primary mr-2"></i> Upload Data Report
                </h5>
                <p class="import-upload-card__subtitle mb-0">Unggah file sesuai format report.</p>
            </div>
        </div>
    </div>

    <form id="form-import" method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" data-prepare-preview-url="" data-upload-limits-url="{{ route('import.upload-limits') }}" data-chunked-upload="" data-chunk-init-url="" data-chunk-upload-url="" data-chunk-finalize-url="">
        @csrf

        <div class="card-body import-upload-card__body">
            <div class="form-group">
                <label class="font-weight-bold text-dark">Pilih Report</label>
                <select name="id_report" class="form-control select2" required>
                    <option value="" data-name="" data-table="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}"
                                data-name="{{ strtolower($report->nama_report ?? '') }}"
                                data-table="{{ strtolower($report->table_name ?? '') }}"
                                data-manual-periode="{{ (int) ($report->requires_manual_periode ?? 0) }}"
                                data-manual-periode-type="{{ $report->manual_periode_type ?? '' }}"
                                data-manual-periode-label="{{ $report->manual_periode_label ?? '' }}"
                                data-manual-periode-help="{{ $report->manual_periode_help ?? '' }}">
                            {{ $report->nama_report }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="form-periode" class="form-group" style="display: none;">
                <label id="periode-label" class="font-weight-bold text-dark">
                    <i class="fas fa-calendar-alt text-primary mr-1"></i> Periode
                </label>
                <input type="date" id="periode_input" name="periode" class="form-control">
                <small id="periode-help" class="text-muted mt-2 d-block">Wajib untuk report tertentu.</small>
            </div>

            <div id="form-rar" class="form-group">
                <label class="font-weight-bold text-dark">Upload File (.rar)</label>
                <div class="custom-file">
                    <input type="file" id="file_rar" name="file" class="custom-file-input" accept=".rar" required>
                    <label class="custom-file-label" for="file_rar">Pilih file .rar...</label>
                </div>
                <small class="text-muted mt-2 d-block">File akan diproses otomatis.</small>
            </div>

            <div id="form-excel" class="form-group" style="display: none;">
                <label id="excel-label" class="text-success font-weight-bold"><i class="fas fa-file-excel mr-1"></i> Upload Excel (.xlsx, .xls)</label>
                <input type="file" id="file_excel" name="file" class="form-control border-success shadow-sm" accept=".xlsx,.xls">
                <small class="text-muted mt-2 d-block" id="excel-help">Format .xlsx dan .xls didukung.</small>
                <small class="text-muted mt-2 d-block" id="upload-limit-hint">Format .xlsx dan .xls didukung.</small>
            </div>

            <div id="form-csv" class="form-group" style="display: none;">
                <label id="csv-label" class="text-info font-weight-bold"><i class="fas fa-file-csv mr-1"></i> Upload CSV (.csv, .txt)</label>
                <input type="file" id="file_csv" name="file" class="form-control border-info shadow-sm" accept=".csv,.txt">
                <small id="csv-help" class="text-muted mt-2 d-block">Gunakan CSV sesuai report.</small>
            </div>
        </div>

        <div class="card-footer bg-light border-0 import-upload-card__footer">
            <button type="submit" id="btn-submit" class="btn btn-primary font-weight-bold import-upload-card__submit">
                <i class="fas fa-file-archive"></i> Upload
            </button>
        </div>
    </form>
</div>

@if(!empty($showReportManagementPanel))
    <div class="card shadow-sm border-0 mt-4" id="report-management-card"
         data-fetch-url="{{ route('import.report-management.data') }}"
         data-delete-url="{{ route('import.report-management.delete') }}">
    <div class="card-header bg-white border-0">
        <span class="import-upload-card__eyebrow">Kelola Report</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-database text-danger mr-2"></i> Kelola Data Report
        </h5>
        <p class="text-muted mb-0">Filter report lalu hapus data yang tidak diperlukan.</p>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8 mb-2">
                <label class="font-weight-bold text-dark">Pilih Report</label>
                <select id="management-report-select" class="form-control select2">
                    <option value="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}">{{ $report->nama_report }} ({{ $report->table_name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-2 d-flex align-items-end">
                <button type="button" id="btn-management-filter" class="btn btn-outline-primary btn-block">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 25%;">Periode</th>
                        <th style="width: 35%;">Kanca</th>
                        <th style="width: 20%;" class="text-right">Jumlah Baris</th>
                        <th style="width: 20%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="management-table-body">
                    <tr>
                        <td colspan="4" class="text-center text-muted">Pilih report lalu klik Filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const swalTheme = {
            customClass: {
                popup: 'swal-modern-popup',
                title: 'swal-modern-title',
                htmlContainer: 'swal-modern-html',
                confirmButton: 'swal-modern-confirm',
            },
            buttonsStyling: false,
            background: '#ffffff',
        };

        function themedSwal(options) {
            return Swal.fire(Object.assign({}, swalTheme, options));
        }

        const reportSelect = document.querySelector('select[name="id_report"]');
        const formRAR = document.getElementById('form-rar');
        const formExcel = document.getElementById('form-excel');
        const formCsv = document.getElementById('form-csv');
        const formPeriode = document.getElementById('form-periode');
        const formImport = document.getElementById('form-import');
        const btnSubmit = document.getElementById('btn-submit');
        const btnDownloadTemplate = document.getElementById('btn-download-template');
        const downloadTemplateSelect = document.getElementById('download-template-select');
        const inputRar = document.getElementById('file_rar');
        const inputExcel = document.getElementById('file_excel');
        const inputCsv = document.getElementById('file_csv');
        const periodeInput = document.getElementById('periode_input');
        const periodeLabel = document.getElementById('periode-label');
        const periodeHelp = document.getElementById('periode-help');
        const excelLabel = document.getElementById('excel-label');
        const excelHelp = document.getElementById('excel-help');
        const csvLabel = document.getElementById('csv-label');
        const csvHelp = document.getElementById('csv-help');
        const csrfTokenInput = formImport?.querySelector('input[name="_token"]');
        const reportManagementCard = document.getElementById('report-management-card');
        const managementReportSelect = document.getElementById('management-report-select');
        const btnManagementFilter = document.getElementById('btn-management-filter');
        const managementTableBody = document.getElementById('management-table-body');
        let uploadLimitsPromise = null;

        function formatBytes(bytes) {
            if (!bytes || bytes <= 0) {
                return 'tidak terbatas';
            }

            const units = ['B', 'KB', 'MB', 'GB'];
            let value = bytes;
            let idx = 0;
            while (value >= 1024 && idx < units.length - 1) {
                value /= 1024;
                idx++;
            }

            return `${value.toFixed(idx === 0 ? 0 : 2)} ${units[idx]}`;
        }

        function describeUploadLimitMessage(limits) {
            const maxBytes = Number(limits?.effective_max_upload_bytes || 0);
            if (maxBytes > 0) {
                return `Format .xlsx/.xls hingga ${formatBytes(maxBytes)}.`;
            }

            return 'Format .xlsx/.xls didukung.';
        }

        function applyUploadLimitHints(limits) {
            const message = describeUploadLimitMessage(limits);
            const hintBanner = document.getElementById('upload-limit-hint');

            if (hintBanner) {
                hintBanner.textContent = message;
            }

            if (excelHelp) {
                const current = String(excelHelp.textContent || '').toLowerCase();
                if (current.includes('mendukung format .xlsx dan .xls')) {
                    excelHelp.textContent = message;
                }
            }
        }

        async function getUploadLimits() {
            if (uploadLimitsPromise) {
                return uploadLimitsPromise;
            }

            const limitsUrl = formImport?.dataset.uploadLimitsUrl;
            if (!limitsUrl) {
                return null;
            }

            const cacheBuster = limitsUrl.includes('?')
                ? `${limitsUrl}&_=${Date.now()}`
                : `${limitsUrl}?_=${Date.now()}`;

            uploadLimitsPromise = fetch(cacheBuster, {
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                cache: 'no-store'
            })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.status !== 'success') {
                    applyUploadLimitHints(null);
                    return null;
                }
                applyUploadLimitHints(payload);
                return payload;
            })
            .catch(() => {
                applyUploadLimitHints(null);
                return null;
            });

            return uploadLimitsPromise;
        }

        async function validateFileSizeBeforeUpload() {
            const fileInput = !inputRar.disabled
                ? inputRar
                : (!inputExcel.disabled ? inputExcel : inputCsv);

            const file = fileInput?.files?.[0];
            if (!file) {
                return true;
            }

            const limits = await getUploadLimits();
            const maxBytes = Number(limits?.effective_max_upload_bytes || 0);
            if (maxBytes > 0 && file.size > maxBytes) {
                const limitLabel = formatBytes(maxBytes);
                const shouldWarnOnly = maxBytes <= (128 * 1024 * 1024);

                if (shouldWarnOnly) {
                    console.warn('Upload limit endpoint masih mengembalikan nilai kecil:', limits);
                    return true;
                }

                themedSwal({
                    icon: 'error',
                    title: 'Ukuran File Terlalu Besar',
                    html: `Ukuran file <b>${escapeHtml(file.name)}</b> adalah <b>${formatBytes(file.size)}</b>.<br>Batas upload server saat ini <b>${limitLabel}</b>.`
                });
                return false;
            }

            return true;
        }

        async function uploadDailyLoanChunked(file, uploadProgressBar, uploadProgressText) {
            const csrfToken = formImport.querySelector('input[name="_token"]')?.value || '';
            const initUrl = formImport.dataset.chunkInitUrl;
            const chunkUrl = formImport.dataset.chunkUploadUrl;
            const finalizeUrl = formImport.dataset.chunkFinalizeUrl;
            const chunkSize = 8 * 1024 * 1024;
            const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));

            const initForm = new FormData();
            initForm.append('_token', csrfToken);
            initForm.append('original_name', file.name);
            initForm.append('total_size', String(file.size));

            const initResponse = await fetch(initUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: initForm,
            });

            const initPayload = await initResponse.json().catch(() => ({}));
            if (!initResponse.ok || initPayload.status !== 'success' || !initPayload.upload_id) {
                throw new Error(initPayload.message || 'Gagal memulai upload bertahap.');
            }

            for (let index = 0; index < totalChunks; index++) {
                const start = index * chunkSize;
                const end = Math.min(file.size, start + chunkSize);
                const chunk = file.slice(start, end);
                const chunkForm = new FormData();
                chunkForm.append('_token', csrfToken);
                chunkForm.append('upload_id', initPayload.upload_id);
                chunkForm.append('chunk_index', String(index));
                chunkForm.append('total_chunks', String(totalChunks));
                chunkForm.append('file', chunk, `${file.name}.part${index}`);

                const chunkResponse = await fetch(chunkUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: chunkForm,
                });

                const chunkPayload = await chunkResponse.json().catch(() => ({}));
                if (!chunkResponse.ok || chunkPayload.status !== 'success') {
                    throw new Error(chunkPayload.message || ('Gagal upload potongan file ke-' + (index + 1) + '.'));
                }

                const uploadedBytes = end;
                const percent = Math.min(94, Math.max(3, Math.round((uploadedBytes / file.size) * 94)));
                if (uploadProgressBar) {
                    uploadProgressBar.style.width = percent + '%';
                    uploadProgressBar.innerText = percent + '%';
                }
                const progressPercent = document.getElementById('swal-progress-percent');
                if (progressPercent) {
                    progressPercent.textContent = percent + '%';
                }
                if (uploadProgressText) {
                    uploadProgressText.innerText = `Mengunggah file ke server... ${percent}%`;
                }
            }

            if (uploadProgressBar) {
                uploadProgressBar.style.width = '96%';
                uploadProgressBar.innerText = '96%';
            }
            const progressPercent96 = document.getElementById('swal-progress-percent');
            if (progressPercent96) {
                progressPercent96.textContent = '96%';
            }
            if (uploadProgressText) {
                uploadProgressText.innerText = 'Upload selesai. Menggabungkan file di server...';
            }

            const finalizeForm = new FormData();
            finalizeForm.append('_token', csrfToken);
            finalizeForm.append('upload_id', initPayload.upload_id);
            finalizeForm.append('total_chunks', String(totalChunks));
            finalizeForm.append('original_name', file.name);

            const finalizeResponse = await fetch(finalizeUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: finalizeForm,
            });

            const finalizePayload = await finalizeResponse.json().catch(() => ({}));
            if (!finalizeResponse.ok || finalizePayload.status !== 'success') {
                throw new Error(finalizePayload.message || 'Gagal menyusun file final di server.');
            }

            if (uploadProgressBar) {
                uploadProgressBar.style.width = '100%';
                uploadProgressBar.innerText = '100%';
            }
            if (uploadProgressText) {
                uploadProgressText.innerText = 'Upload selesai. Membuka halaman preview...';
            }

            if (finalizePayload.redirect) {
                window.location.href = finalizePayload.redirect;
                return;
            }

            throw new Error('Server tidak mengembalikan alamat preview setelah upload chunk.');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderManagementRows(rows) {
            if (!managementTableBody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                managementTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted">Tidak ada data untuk kriteria ini.</td>
                    </tr>
                `;
                return;
            }

            managementTableBody.innerHTML = rows.map(function(row) {
                const period = row.period ?? '(Blank)';
                const kanca = row.kanca ?? '(Blank)';
                const total = Number(row.row_count || 0).toLocaleString('id-ID');
                const periodIsNull = row.period_is_null ? '1' : '0';
                const kancaIsNull = row.kanca_is_null ? '1' : '0';
                const periodEncoded = encodeURIComponent(String(period));
                const kancaEncoded = encodeURIComponent(String(kanca));

                return `
                    <tr>
                        <td>${escapeHtml(period)}</td>
                        <td>${escapeHtml(kanca)}</td>
                        <td class="text-right">${total}</td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger btn-management-delete"
                                    data-period="${periodEncoded}"
                                    data-kanca="${kancaEncoded}"
                                    data-period-is-null="${periodIsNull}"
                                    data-kanca-is-null="${kancaIsNull}">
                                <i class="fas fa-trash-alt mr-1"></i> Delete
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function fetchManagementData() {
            if (!managementReportSelect || !managementReportSelect.value) {
                themedSwal({
                    icon: 'warning',
                    title: 'Pilih Report',
                    text: 'Silakan pilih report terlebih dahulu.'
                });
                return;
            }

            if (!reportManagementCard) {
                return;
            }

            const fetchUrl = reportManagementCard.dataset.fetchUrl;
            const token = csrfTokenInput ? csrfTokenInput.value : '';

            managementTableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted">Memuat data...</td>
                </tr>
            `;

            const response = await fetch(fetchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    id_report: managementReportSelect.value
                })
            });

            const payload = await response.json();
            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'Gagal memuat data report management.');
            }

            renderManagementRows(payload.rows || []);
        }

        async function deleteManagedRow(button) {
            if (!reportManagementCard || !managementReportSelect || !managementReportSelect.value) {
                return;
            }

            const deleteUrl = reportManagementCard.dataset.deleteUrl;
            const token = csrfTokenInput ? csrfTokenInput.value : '';
            const period = decodeURIComponent(button.getAttribute('data-period') || '');
            const kanca = decodeURIComponent(button.getAttribute('data-kanca') || '');
            const periodIsNull = button.getAttribute('data-period-is-null') === '1';
            const kancaIsNull = button.getAttribute('data-kanca-is-null') === '1';

            const confirm = await themedSwal({
                icon: 'warning',
                title: 'Hapus Data?',
                html: `Data akan dihapus untuk <b>Periode:</b> ${period}<br><b>Kanca:</b> ${kanca}`,
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            });

            if (!confirm.isConfirmed) {
                return;
            }

            const response = await fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    id_report: managementReportSelect.value,
                    period: periodIsNull ? '' : period,
                    kanca: kancaIsNull ? '' : kanca,
                    period_is_null: periodIsNull,
                    kanca_is_null: kancaIsNull
                })
            });

            const payload = await response.json();
            if (!response.ok || (payload.status !== 'success' && payload.status !== 'warning')) {
                throw new Error(payload.message || 'Gagal menghapus data report.');
            }

            const isWarning = payload.status === 'warning';
            await themedSwal({
                icon: isWarning ? 'warning' : 'success',
                title: isWarning ? 'Selesai dengan Catatan' : 'Berhasil',
                text: isWarning
                    ? (payload.message || 'Data sumber terhapus tetapi sinkronisasi snapshot bermasalah.')
                    : `Data terhapus ${Number(payload.deleted_rows || 0).toLocaleString('id-ID')} baris.`
            });

            await fetchManagementData();
        }

        function syncDownloadButton() {
            if (!btnDownloadTemplate || !downloadTemplateSelect) {
                return;
            }

            const templateKey = downloadTemplateSelect.value || '';
            const selectedOption = downloadTemplateSelect.options[downloadTemplateSelect.selectedIndex];
            const filename = selectedOption ? (selectedOption.getAttribute('data-filename') || '') : '';

            if (templateKey) {
                const params = new URLSearchParams();
                params.set('report', templateKey);

                if (filename) {
                    params.set('file', filename);
                }

                btnDownloadTemplate.href = `${btnDownloadTemplate.dataset.routeTemplate}?${params.toString()}`;
                btnDownloadTemplate.classList.remove('disabled');
                btnDownloadTemplate.removeAttribute('aria-disabled');
                return;
            }

            btnDownloadTemplate.href = '#';
            btnDownloadTemplate.classList.add('disabled');
            btnDownloadTemplate.setAttribute('aria-disabled', 'true');
        }

        function applyButtonState(kind, label) {
            const buttonClasses = {
                rar: 'btn btn-primary font-weight-bold import-upload-card__submit',
                excel: 'btn btn-success font-weight-bold import-upload-card__submit',
                csv: 'btn btn-info font-weight-bold import-upload-card__submit',
            };

            formImport.dataset.uploadKind = kind;
            formImport.dataset.submitLabel = label;
            btnSubmit.className = buttonClasses[kind] || buttonClasses.rar;
            btnSubmit.innerHTML = label;
        }

        function getSelectedReportMeta() {
            const selectedOption = reportSelect?.options?.[reportSelect.selectedIndex];

            return {
                reportName: selectedOption?.getAttribute('data-name') || '',
                tableName: selectedOption?.getAttribute('data-table') || '',
                requiresManualPeriode: selectedOption?.getAttribute('data-manual-periode') === '1',
                manualPeriodeType: selectedOption?.getAttribute('data-manual-periode-type') || '',
                manualPeriodeLabel: selectedOption?.getAttribute('data-manual-periode-label') || '',
                manualPeriodeHelp: selectedOption?.getAttribute('data-manual-periode-help') || '',
            };
        }

        function buildManualPeriodeOptions(defaults = {}) {
            const meta = getSelectedReportMeta();

            return Object.assign({}, defaults, {
                visible: meta.requiresManualPeriode || Boolean(defaults.visible),
                required: meta.requiresManualPeriode || Boolean(defaults.required),
                type: meta.manualPeriodeType || defaults.type || 'date',
                label: meta.manualPeriodeLabel || defaults.label || 'Periode',
                help: meta.manualPeriodeHelp || defaults.help || 'Pilih periode manual sesuai file report.',
            });
        }

        function configurePeriodeInput(options = {}) {
            const {
                visible = false,
                required = false,
                type = 'date',
                label = 'Periode',
                help = 'Pilih periode manual sesuai file report.',
                value = '',
            } = options;

            if (!formPeriode || !periodeInput) {
                return;
            }

            formPeriode.style.display = visible ? 'block' : 'none';
            periodeInput.disabled = !visible;
            periodeInput.required = Boolean(visible && required);
            periodeInput.type = type;
            periodeInput.value = value;

            if (periodeLabel) {
                periodeLabel.textContent = label;
            }

            if (periodeHelp) {
                periodeHelp.textContent = help;
            }
        }

        function getFileExtension(fileName) {
            const parts = String(fileName || '').toLowerCase().split('.');
            return parts.length > 1 ? parts.pop() : '';
        }

        function isSimpananReportSelected() {
            return getSelectedReportMeta().reportName.includes('simpanan multipn');
        }

        function applySimpananUploadMode() {
            if (!isSimpananReportSelected()) {
                return;
            }

            const selectedFile = inputExcel?.files?.[0] || null;
            const extension = getFileExtension(selectedFile?.name || '');
            const isCsvLike = ['csv', 'txt'].includes(extension);

            inputExcel.setAttribute('accept', '.xlsx,.xls,.csv,.txt');

            if (excelLabel) {
                excelLabel.innerHTML = '<i class="fas fa-file-upload mr-1"></i> Upload File Simpanan MultiPN (.xlsx, .xls, .csv, .txt)';
            }

            if (excelHelp) {
                excelHelp.textContent = isCsvLike
                    ? 'File CSV/TXT akan diproses lewat jalur import CSV Simpanan MultiPN.'
                    : 'File Excel akan diproses lewat jalur import Excel Simpanan MultiPN.';
            }

            formImport.action = isCsvLike
                ? "{{ route('import.simpanan.csv.upload') }}"
                : "{{ route('import.simpanan.upload') }}";
            formImport.dataset.preparePreviewUrl = isCsvLike
                ? "{{ route('import.simpanan.csv.prepare-preview') }}"
                : "{{ route('import.simpanan.prepare-preview') }}";

            applyButtonState(
                isCsvLike ? 'csv' : 'excel',
                isCsvLike
                    ? '<i class="fas fa-file-csv"></i> Upload CSV'
                    : '<i class="fas fa-file-excel"></i> Upload Excel'
            );
        }
        function toggleForm() {
            const { reportName, tableName } = getSelectedReportMeta();
            const isDailyLoan = reportName.includes('daily loan');
            const isSimpanan = reportName.includes('simpanan multipn');
            const isPerformancePis = reportName.includes('performance pis per produk');
            const isCasaBrilink = reportName.includes('casa brilink');
            const isReportPh = reportName.includes('report nominatif rekening pinjaman ph');
            const isInputRekanan = tableName === 'input_rekanan';
            const isBodBoc = tableName === 'bod_boc';

            formRAR.style.display = 'none';
            formExcel.style.display = 'none';
            formCsv.style.display = 'none';

            inputRar.disabled = true;
            inputRar.required = false;
            inputExcel.disabled = true;
            inputExcel.required = false;
            inputCsv.disabled = true;
            inputCsv.required = false;
            periodeInput.disabled = true;
            periodeInput.required = false;

            formImport.dataset.preparePreviewUrl = '';
            formImport.dataset.directRedirect = '';
            formImport.dataset.chunkedUpload = '';
            formImport.dataset.chunkInitUrl = '';
            formImport.dataset.chunkUploadUrl = '';
            formImport.dataset.chunkFinalizeUrl = '';

            if (isDailyLoan) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                formImport.action = "{{ route('import.dailyloan.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.dailyloan.prepare-preview') }}";
                formImport.dataset.chunkedUpload = '1';
                formImport.dataset.chunkInitUrl = "{{ route('import.dailyloan.upload-chunk.init') }}";
                formImport.dataset.chunkUploadUrl = "{{ route('import.dailyloan.upload-chunk') }}";
                formImport.dataset.chunkFinalizeUrl = "{{ route('import.dailyloan.upload-chunk.finalize') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-csv mr-1"></i> Upload File CSV Daily Loan Dinamis (.csv, .txt)';
                csvHelp.textContent = 'Gunakan file CSV Daily Loan Dinamis yang sudah sesuai template untuk diproses ke database.';
                applyButtonState('csv', '<i class="fas fa-file-csv"></i> Upload CSV');
                return;
            }

            if (isSimpanan) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                inputExcel.setAttribute('accept', '.xlsx,.xls,.csv');
                configurePeriodeInput({ visible: false });
                applySimpananUploadMode();
                return;
            }

            if (isInputRekanan || isBodBoc) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                inputExcel.setAttribute('accept', '.xlsx,.xls');
                if (excelLabel) {
                    excelLabel.innerHTML = '<i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)';
                }
                if (excelHelp) {
                    excelHelp.textContent = describeUploadLimitMessage(null);
                }
                formImport.action = isInputRekanan
                    ? "{{ route('input.import-template') }}"
                    : (isBodBoc
                        ? "{{ route('bod-boc.import-template') }}"
                        : "{{ route('import.excel.upload') }}");
                formImport.dataset.preparePreviewUrl = (isInputRekanan || isBodBoc)
                    ? ''
                    : "{{ route('import.excel.prepare-preview') }}";
                applyButtonState('excel', '<i class="fas fa-file-excel"></i> Upload Excel');
                configurePeriodeInput(buildManualPeriodeOptions({
                    visible: true,
                    required: true,
                    type: 'date',
                    label: 'Tanggal Periode',
                    help: 'Input Rekanan dan Nasabah Prioritas BOD/BOC wajib diisi tanggal periode manual (YYYY-MM-DD).',
                }));
                return;
            }

            if (isCasaBrilink) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt');
                formImport.action = "{{ route('import.casabrilink.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.casabrilink.prepare-preview') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-csv mr-1"></i> Upload File CASA BRILINK (.csv, .txt)';
                csvHelp.textContent = 'Gunakan file CSV CASA BRILINK WEB/EDC tanpa kolom periode. Periode diisi manual.';
                applyButtonState('csv', '<i class="fas fa-file-csv"></i> Upload CSV');
                configurePeriodeInput(buildManualPeriodeOptions({
                    visible: true,
                    required: true,
                    type: 'month',
                    label: 'Periode Bulan',
                    help: 'Wajib isi periode manual dalam format bulan (YYYY-MM) untuk CASA BRILINK WEB/EDC.',
                }));
                return;
            }

            if (isReportPh) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt,.xlsx,.xls');
                formImport.action = "{{ route('import.reportph.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.reportph.prepare-preview') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-upload mr-1"></i> Upload File Report PH (.csv, .txt, .xlsx, .xls)';
                csvHelp.textContent = 'CSV tetap didukung. File Excel akan distage dulu ke CSV lalu masuk ke jalur bulk import yang sama.';
                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput({ visible: false });
                return;
            }

            if (isPerformancePis) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                inputExcel.setAttribute('accept', '.xlsx,.xls');
                formImport.action = "{{ route('import.performancepis.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.performancepis.prepare-preview') }}";
                if (excelLabel) {
                    excelLabel.innerHTML = '<i class="fas fa-file-upload mr-1"></i> Upload File Performance PIS (.xlsx, .xls)';
                }
                if (excelHelp) {
                    excelHelp.textContent = 'Tanggal periode diisi manual pada form import Performance PIS per Produk.';
                }
                applyButtonState('excel', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput(buildManualPeriodeOptions({
                    visible: true,
                    required: true,
                    type: 'date',
                    label: 'Tanggal Periode',
                    help: 'Wajib isi tanggal periode manual (YYYY-MM-DD) untuk Performance PIS per Produk.',
                }));
                return;
            }

            if (reportName.includes('brimo')) {
                formRAR.style.display = 'block';
                inputRar.disabled = false;
                inputRar.required = true;
                formImport.action = "{{ route('import.brimo.upload') }}";
                applyButtonState('rar', '<i class="fas fa-file-archive"></i> Upload RAR');
                return;
            }

            formRAR.style.display = 'block';
            inputRar.disabled = false;
            inputRar.required = true;
            inputExcel.setAttribute('accept', '.xlsx,.xls');
            if (excelLabel) {
                excelLabel.innerHTML = '<i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)';
            }
            if (excelHelp) {
                excelHelp.textContent = describeUploadLimitMessage(null);
            }
            configurePeriodeInput({ visible: false });
            formImport.action = "{{ route('import.upload') }}";
            applyButtonState('rar', '<i class="fas fa-file-archive"></i> Upload RAR');
        }

        if (reportSelect) {
            reportSelect.addEventListener('change', toggleForm);
            if (window.jQuery && window.jQuery.fn) {
                window.jQuery(reportSelect).on('change.select2 select2:select', toggleForm);
            }
        }

        inputExcel?.addEventListener('change', function () {
            if (isSimpananReportSelected()) {
                applySimpananUploadMode();
            }
        });

        if (downloadTemplateSelect) {
            downloadTemplateSelect.addEventListener('change', syncDownloadButton);
            window.addEventListener('load', syncDownloadButton);
            window.addEventListener('pageshow', syncDownloadButton);

            if (window.jQuery && window.jQuery.fn) {
                window.jQuery(downloadTemplateSelect).on('change select2:select', syncDownloadButton);
            }
        }

        btnDownloadTemplate?.addEventListener('click', function (event) {
            syncDownloadButton();

            if (btnDownloadTemplate.classList.contains('disabled')) {
                event.preventDefault();
            }
        });

        toggleForm();
        syncDownloadButton();
        setTimeout(syncDownloadButton, 0);
        setTimeout(syncDownloadButton, 150);
        getUploadLimits();

        btnManagementFilter?.addEventListener('click', async function () {
            try {
                await fetchManagementData();
            } catch (error) {
                themedSwal({
                    icon: 'error',
                    title: 'Gagal Memuat Data',
                    text: error.message || 'Terjadi kesalahan saat memuat data.'
                });
            }
        });

        managementTableBody?.addEventListener('click', async function (event) {
            const button = event.target.closest('.btn-management-delete');
            if (!button) {
                return;
            }

            button.disabled = true;
            try {
                await deleteManagedRow(button);
            } catch (error) {
                themedSwal({
                    icon: 'error',
                    title: 'Delete Gagal',
                    text: error.message || 'Terjadi kesalahan saat menghapus data.'
                });
            } finally {
                button.disabled = false;
            }
        });

        $('#file_rar').on('change', function () {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });

        formImport.addEventListener('submit', async function(e) {
            e.preventDefault();

            const sizeAllowed = await validateFileSizeBeforeUpload();
            if (!sizeAllowed) {
                return;
            }

            const hasAsyncPreview = Boolean(formImport.dataset.preparePreviewUrl);
            const directRedirect = formImport.dataset.directRedirect === '1';
            const uploadKind = formImport.dataset.uploadKind || 'rar';
            const titleText = uploadKind === 'excel'
                ? 'Proses Excel'
                : uploadKind === 'csv'
                    ? 'Proses CSV'
                    : 'Proses Import';
            const descText = hasAsyncPreview
                ? 'File sedang diproses untuk preview.'
                : 'File sedang diproses.';

            const progressHtml = `
                <div class="swal-import-shell">
                    <div class="swal-import-head">
                        <span class="swal-import-badge"><i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang diproses</span>
                        <div class="swal-import-title">${titleText}</div>
                        <div class="swal-import-desc" id="swal-desc-text">${descText}</div>
                    </div>
                    <div class="swal-import-card">
                        <div class="swal-import-card__top">
                            <span class="swal-import-label">Progress</span>
                            <span class="swal-import-percent" id="swal-progress-percent">0%</span>
                        </div>
                        <div class="progress swal-import-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <div id="swal-progress-bar" class="progress-bar swal-import-progress__bar progress-bar-striped progress-bar-animated"
                                 style="width: 0%;">0%</div>
                        </div>
                        <div class="swal-import-meta">
                            <small id="swal-progress-text" class="swal-import-meta__status">Menunggu proses...</small>
                        </div>
                    </div>
                    <div class="swal-import-stats">
                        <div class="swal-import-stat">
                            <span class="swal-import-stat__label">Baris</span>
                            <span id="swal-rows-info" class="swal-import-stat__value">0 / 0</span>
                        </div>
                        <div class="swal-import-stat">
                            <span class="swal-import-stat__label">Kecepatan</span>
                            <span id="swal-speed-info" class="swal-import-stat__value">-</span>
                        </div>
                    </div>
                </div>
            `;

            themedSwal({
                title: '<i class="fas fa-cloud-upload-alt mr-2 text-success"></i>' + titleText,
                html: progressHtml,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                width: 560,
                didOpen: () => {
                    if (btnSubmit) {
                        btnSubmit.disabled = true;
                        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses';
                    }

                    if (!hasAsyncPreview && !directRedirect) {
                        formImport.submit();
                        return;
                    }

                    const formData = new FormData(formImport);
                    const uploadProgressBar = document.getElementById('swal-progress-bar');
                    const uploadProgressText = document.getElementById('swal-progress-text');
                    const chunkedUpload = formImport.dataset.chunkedUpload === '1';
                    const selectedFile = !inputRar.disabled
                        ? inputRar?.files?.[0]
                        : (!inputExcel.disabled ? inputExcel?.files?.[0] : inputCsv?.files?.[0]);

                    if (chunkedUpload && selectedFile) {
                        uploadDailyLoanChunked(selectedFile, uploadProgressBar, uploadProgressText)
                            .catch((error) => {
                                themedSwal({
                                    icon: 'error',
                                    title: 'Upload Error',
                                    text: error.message || 'Upload bertahap gagal diproses.'
                                });
                                resetSubmitButton();
                            });
                        return;
                    }

                    const uploadRequest = new XMLHttpRequest();
                    uploadRequest.open('POST', formImport.action, true);
                    uploadRequest.setRequestHeader('Accept', 'application/json');
                    uploadRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    uploadRequest.upload.addEventListener('progress', function(event) {
                        if (!event.lengthComputable) {
                            return;
                        }

                        const percent = Math.min(85, Math.max(3, Math.round((event.loaded / event.total) * 85)));
                        if (uploadProgressBar) {
                            uploadProgressBar.style.width = percent + '%';
                            uploadProgressBar.innerText = percent + '%';
                        }
                        const progressPercent = document.getElementById('swal-progress-percent');
                        if (progressPercent) {
                            progressPercent.textContent = percent + '%';
                        }
                        if (uploadProgressText) {
                            uploadProgressText.innerText = 'Mengunggah file ke server... ' + percent + '%';
                        }
                    });

                    uploadRequest.addEventListener('load', function() {
                        if (uploadRequest.status < 200 || uploadRequest.status >= 300) {
                            let serverMessage = '';
                            try {
                                const errorPayload = JSON.parse(uploadRequest.responseText || '{}');
                                serverMessage = errorPayload.message || '';
                            } catch (_) {
                            }

                            if (!serverMessage && uploadRequest.status === 413) {
                                serverMessage = 'Ukuran upload melebihi batas server. Silakan kecilkan file atau naikkan limit upload.';
                            }

                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: serverMessage || ('Upload gagal: ' + (uploadRequest.statusText || 'Unknown error'))
                            });
                            resetSubmitButton();
                            return;
                        }

                        let data = {};
                        try {
                            data = JSON.parse(uploadRequest.responseText || '{}');
                        } catch (error) {
                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: 'Server mengembalikan respons yang tidak valid.'
                            });
                            resetSubmitButton();
                            return;
                        }

                        if (data.status !== 'success') {
                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: data.message || 'Upload gagal diproses oleh server.'
                            });
                            resetSubmitButton();
                            return;
                        }

                        if (data.redirect) {
                            if (uploadProgressBar) {
                                uploadProgressBar.style.width = '100%';
                                uploadProgressBar.innerText = '100%';
                            }
                            const progressPercent = document.getElementById('swal-progress-percent');
                            if (progressPercent) {
                                progressPercent.textContent = '100%';
                            }
                            if (uploadProgressText) {
                                uploadProgressText.innerText = 'Upload selesai. Membuka halaman preview...';
                            }

                            window.location.href = data.redirect;
                            return;
                        }

                        if (directRedirect) {
                            if (data.redirect) {
                                window.location.href = data.redirect;
                                return;
                            }

                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: 'Server tidak mengembalikan alamat preview.'
                            });
                            resetSubmitButton();
                            return;
                        }

                        if (uploadProgressBar) {
                            uploadProgressBar.style.width = '88%';
                            uploadProgressBar.innerText = '88%';
                        }
                        const progressPercent = document.getElementById('swal-progress-percent');
                        if (progressPercent) {
                            progressPercent.textContent = '88%';
                        }
                        if (uploadProgressText) {
                            uploadProgressText.innerText = 'Upload selesai. Menyiapkan preview cepat...';
                        }

                        const eventSource = new EventSource(formImport.dataset.preparePreviewUrl);

                        eventSource.addEventListener('progress', function(event) {
                            var evtData = {};
                            try { evtData = JSON.parse(event.data); } catch (_) {}
                            var progressBar  = document.getElementById('swal-progress-bar');
                            var progressText = document.getElementById('swal-progress-text');
                            if (progressBar && evtData.percent != null) {
                                var composedPercent = Math.max(88, Math.min(100, 88 + Math.round((evtData.percent / 100) * 12)));
                                progressBar.style.width = composedPercent + '%';
                                progressBar.innerText = composedPercent + '%';
                            }
                            if (progressText && evtData.message) {
                                progressText.innerText = evtData.message;
                            }
                        });

                        eventSource.addEventListener('ready', function(event) {
                            var evtData = {};
                            try { evtData = JSON.parse(event.data); } catch (_) {}
                            eventSource.close();
                            if (evtData.redirect) {
                                window.location.href = evtData.redirect;
                            }
                        });

                        eventSource.addEventListener('error_msg', function(event) {
                            var evtData = {};
                            try { evtData = JSON.parse(event.data); } catch (_) {}
                            eventSource.close();
                            themedSwal({
                                icon: 'error',
                                title: 'Error',
                                text: evtData.message || 'Terjadi kesalahan server.'
                            });
                            resetSubmitButton();
                        });

                        eventSource.onerror = function() {
                            eventSource.close();
                            themedSwal({
                                icon: 'error',
                                title: 'Koneksi Terputus',
                                text: 'Gagal terhubung ke server untuk update progress.'
                            });
                            resetSubmitButton();
                        };
                    });

                    uploadRequest.addEventListener('error', function() {
                        themedSwal({
                            icon: 'error',
                            title: 'Upload Error',
                            text: 'Koneksi upload terputus sebelum file selesai dikirim.'
                        });
                        resetSubmitButton();
                    });

                    uploadRequest.send(formData);

                    function resetSubmitButton() {
                        if (btnSubmit) {
                            btnSubmit.disabled = false;
                            btnSubmit.innerHTML = formImport.dataset.submitLabel || '<i class="fas fa-upload"></i> Upload';
                        }
                    }
                }
            });
        });

        let handledNoticeFromQuery = false;
        const currentUrl = new URL(window.location.href);
        const importNotice = currentUrl.searchParams.get('import_notice');
        const importRowsRaw = currentUrl.searchParams.get('import_rows');
        const importRows = Number.isFinite(Number(importRowsRaw)) ? Number(importRowsRaw) : 0;

        if (importNotice === 'input_rekanan_success' || importNotice === 'bod_boc_success') {
            handledNoticeFromQuery = true;
            const tableName = importNotice === 'input_rekanan_success' ? 'input_rekanan' : 'bod_boc';
            themedSwal({
                icon: 'success',
                title: 'Berhasil Disimpan',
                html: `${importRows.toLocaleString('id-ID')} baris data berhasil disimpan ke tabel ${tableName}.`,
                confirmButtonText: 'Tutup'
            });

            currentUrl.searchParams.delete('import_notice');
            currentUrl.searchParams.delete('import_rows');
            window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search + currentUrl.hash);
        }

        @if(session('sweet_success'))
            if (!handledNoticeFromQuery) {
                themedSwal({
                    icon: 'success',
                    title: '{!! session('sweet_success')['title'] !!}',
                    html: '{!! session('sweet_success')['text'] !!}',
                    confirmButtonText: 'Tutup'
                });
            }
        @endif

        @if(session('sweet_warning'))
            themedSwal({
                icon: 'warning',
                title: '{!! session('sweet_warning')['title'] !!}',
                html: '{!! session('sweet_warning')['text'] !!}',
                confirmButtonText: 'Mengerti'
            });
        @endif

        @if(session('error'))
            themedSwal({
                icon: 'error',
                title: 'Gagal!',
                text: '{{ session('error') }}',
                confirmButtonText: 'Tutup'
            });
        @endif
    });
</script>
<style>
    .import-template-banner {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 1.4rem 1.5rem;
        background:
            radial-gradient(circle at top right, rgba(16, 185, 129, 0.22), transparent 30%),
            linear-gradient(135deg, #f8fffc 0%, #ecfdf5 45%, #d1fae5 100%);
        border: 1px solid rgba(16, 185, 129, 0.18);
        box-shadow: 0 22px 45px -32px rgba(5, 150, 105, 0.45);
    }

    .import-template-banner__glow {
        position: absolute;
        top: -42px;
        right: -28px;
        width: 160px;
        height: 160px;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.14);
        filter: blur(8px);
    }

    .import-template-banner__content {
        max-width: 680px;
    }

    .import-template-banner__eyebrow,
    .import-upload-card__eyebrow {
        display: inline-block;
        margin-bottom: 0.55rem;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .import-template-banner__eyebrow {
        color: #047857;
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(16, 185, 129, 0.18);
    }

    .import-template-banner__title {
        color: #064e3b;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 0.35rem;
    }

    .import-template-banner__text {
        color: #166534;
        line-height: 1.65;
        max-width: 620px;
    }

    .import-template-banner__button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 0.8rem 1.25rem;
        border-radius: 16px;
        border: 0;
        background: linear-gradient(135deg, #059669, #047857);
        color: #ffffff;
        font-weight: 700;
        box-shadow: 0 18px 30px -20px rgba(6, 95, 70, 0.55);
        transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    .import-template-banner__download-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .import-template-banner__select {
        min-width: 260px;
        min-height: 48px;
        border-radius: 16px;
        border: 1px solid rgba(16, 185, 129, 0.18);
        box-shadow: none;
        background: rgba(255, 255, 255, 0.88);
    }

    .import-template-banner__button:hover {
        color: #ffffff;
        text-decoration: none;
        transform: translateY(-1px);
        box-shadow: 0 22px 34px -20px rgba(6, 95, 70, 0.6);
    }

    .import-template-banner__button.disabled,
    .import-template-banner__button[aria-disabled="true"] {
        pointer-events: none;
        opacity: 0.6;
        background: linear-gradient(135deg, #94a3b8, #64748b);
        box-shadow: none;
    }

    .import-upload-card {
        border-radius: 26px;
        overflow: hidden;
        box-shadow: 0 28px 60px -40px rgba(15, 23, 42, 0.32) !important;
    }

    .import-upload-card__header {
        padding: 1.45rem 1.5rem 1rem;
        background:
            radial-gradient(circle at top left, rgba(59, 130, 246, 0.09), transparent 28%),
            linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .import-upload-card__eyebrow {
        color: #1d4ed8;
        background: rgba(37, 99, 235, 0.08);
    }

    .import-upload-card__subtitle {
        color: #64748b;
        max-width: 620px;
        line-height: 1.6;
    }

    .import-upload-card__body {
        padding: 1.5rem;
    }

    .import-upload-card__body .form-group label {
        margin-bottom: 0.7rem;
    }

    .import-upload-card__body .form-control,
    .import-upload-card__body .custom-file-label,
    .import-upload-card__body .select2-container--default .select2-selection--single {
        border-radius: 16px;
        min-height: 48px;
        border-color: #dbe4f0;
        box-shadow: none;
    }

    .import-upload-card__body .custom-file-label {
        display: flex;
        align-items: center;
        padding-left: 1rem;
    }

    .import-upload-card__body .custom-file-label::after {
        height: calc(100% - 8px);
        margin: 4px;
        border-radius: 12px;
        background: #e2e8f0;
    }

    .import-upload-card__footer {
        padding: 0 1.5rem 1.5rem;
        background: linear-gradient(180deg, rgba(248, 250, 252, 0) 0%, #f8fafc 100%);
    }

    .import-upload-card__submit {
        min-height: 50px;
        padding: 0.85rem 1.4rem;
        border-radius: 16px;
        box-shadow: 0 18px 34px -22px rgba(37, 99, 235, 0.52);
    }

    .swal-modern-popup {
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 28px;
        padding: 1.4rem 1.4rem 1.2rem;
        box-shadow: 0 30px 80px -35px rgba(15, 23, 42, 0.35);
    }

    .swal-modern-title {
        color: #0f172a;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .swal-modern-html {
        color: #475569;
        font-size: 0.95rem;
        line-height: 1.65;
    }

    .swal-modern-confirm {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 16px;
        background: linear-gradient(135deg, #0f766e, #115e59);
        color: #ffffff;
        font-weight: 700;
        padding: 0.8rem 1.3rem;
        box-shadow: 0 16px 34px -22px rgba(15, 23, 42, 0.45);
    }

    .swal-import-shell {
        display: grid;
        gap: 1rem;
        text-align: left;
    }

    .swal-import-head {
        display: grid;
        gap: 0.45rem;
    }

    .swal-import-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 0.4rem 0.72rem;
        border-radius: 999px;
        background: rgba(15, 118, 110, 0.1);
        color: #0f766e;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-title {
        color: #0f172a;
        font-size: 1.08rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .swal-import-desc {
        color: #64748b;
        font-size: 0.92rem;
        line-height: 1.5;
    }

    .swal-import-card {
        padding: 1rem;
        border-radius: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 18px 42px -32px rgba(15, 23, 42, 0.28);
    }

    .swal-import-card__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.6rem;
    }

    .swal-import-label {
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-percent {
        color: #0f172a;
        font-size: 0.92rem;
        font-weight: 800;
    }

    .swal-import-progress {
        height: 14px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .swal-import-progress__bar {
        background: linear-gradient(135deg, #0f766e, #14b8a6);
        font-weight: 800;
        font-size: 11px;
        line-height: 14px;
    }

    .swal-import-meta {
        margin-top: 0.7rem;
    }

    .swal-import-meta__status {
        color: #0f766e;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .swal-import-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .swal-import-stats--compact {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .swal-import-stat {
        padding: 0.85rem 0.9rem;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.15);
    }

    .swal-import-stat__label {
        display: block;
        margin-bottom: 0.25rem;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-stat__value {
        display: block;
        color: #0f172a;
        font-size: 0.94rem;
        font-weight: 800;
    }

    @media (max-width: 767.98px) {
        .import-template-banner,
        .import-upload-card__header,
        .import-upload-card__body,
        .import-upload-card__footer {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .import-template-banner__title {
            font-size: 1.15rem;
        }

        .import-template-banner__actions,
        .import-template-banner__download-group,
        .import-template-banner__select,
        .import-template-banner__button,
        .import-upload-card__submit {
            width: 100%;
        }
    }
</style>
@endsection
