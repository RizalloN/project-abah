@extends('layouts.admin')

@section('title', 'Import Data')

@section('content')

<div class="import-template-banner mb-4">
    <div class="import-template-banner__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="import-template-banner__content pr-3">
            <span class="import-template-banner__eyebrow">Template Siap Pakai</span>
            <div class="import-template-banner__title">
                <i class="fas fa-download mr-2"></i> Download Template Import
            </div>
            <p class="import-template-banner__text mb-0">Pilih kategori report, unduh template yang sesuai, lalu isi datanya agar proses import lebih cepat dan rapi. File template fisik disimpan di <code>resources/templates/import</code>.</p>
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
                    <i class="fas fa-file-download mr-2"></i> Download Template
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
                <p class="import-upload-card__subtitle mb-0">Unggah file sesuai format report yang dipilih. Sistem akan menyesuaikan jenis upload secara otomatis.</p>
            </div>
        </div>
    </div>

    <form id="form-import" method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" data-prepare-preview-url="">
        @csrf

        <div class="card-body import-upload-card__body">
            <div class="form-group">
                <label class="font-weight-bold text-dark">Pilih Kategori Report</label>
                <select name="id_report" class="form-control select2" required>
                    <option value="" data-name="" data-table="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}"
                                data-name="{{ strtolower($report->nama_report ?? '') }}"
                                data-table="{{ strtolower($report->table_name ?? '') }}">
                            {{ $report->nama_report }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="form-rar" class="form-group">
                <label class="font-weight-bold text-dark">Upload File Extracted (.rar)</label>
                <div class="custom-file">
                    <input type="file" id="file_rar" name="file" class="custom-file-input" accept=".rar" required>
                    <label class="custom-file-label" for="file_rar">Pilih file .rar...</label>
                </div>
                <small class="text-muted mt-2 d-block">Sistem akan mengekstrak otomatis dan mendeteksi file CSV di dalamnya.</small>
            </div>

            <div id="form-excel" class="form-group" style="display: none;">
<<<<<<< HEAD
                <label class="text-success font-weight-bold"><i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)</label>
                <input type="file" id="file_excel" name="file" class="form-control border-success shadow-sm" accept=".xlsx,.xls">
                <small class="text-muted mt-2 d-block">Mendukung format .xlsx dan .xls hingga 200MB+ dengan preview bertahap.</small>
=======
                <label id="label_excel_upload" class="text-success font-weight-bold"><i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)</label>
                <input type="file" id="file_excel" name="file" class="form-control border-success shadow-sm" accept=".xlsx, .xls">
                <small id="label_excel_help" class="text-muted mt-2 d-block">Mendukung format .xlsx dan .xls hingga 200MB+ (Menggunakan Chunk Reading Mode).</small>
>>>>>>> 7d9de73de61f625c5ab496dc859a4792870a4fe3
            </div>

            <div id="form-csv" class="form-group" style="display: none;">
                <label id="csv-label" class="text-info font-weight-bold"><i class="fas fa-file-csv mr-1"></i> Upload File CSV (.csv, .txt)</label>
                <input type="file" id="file_csv" name="file" class="form-control border-info shadow-sm" accept=".csv,.txt">
                <small id="csv-help" class="text-muted mt-2 d-block">Gunakan file CSV Performance PIS Per Produk dengan metadata posisi di bagian atas file.</small>
            </div>
        </div>

        <div class="card-footer bg-light border-0 import-upload-card__footer">
            <button type="submit" id="btn-submit" class="btn btn-primary font-weight-bold import-upload-card__submit">
                <i class="fas fa-file-archive"></i> Upload RAR
            </button>
        </div>
    </form>
</div>

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
        const formImport = document.getElementById('form-import');
        const btnSubmit = document.getElementById('btn-submit');
        const btnDownloadTemplate = document.getElementById('btn-download-template');
        const downloadTemplateSelect = document.getElementById('download-template-select');
        const inputRar = document.getElementById('file_rar');
        const inputExcel = document.getElementById('file_excel');
        const inputCsv = document.getElementById('file_csv');
<<<<<<< HEAD
        const csvLabel = document.getElementById('csv-label');
        const csvHelp = document.getElementById('csv-help');
=======
        const formPeriod = document.getElementById('form-period');
        const inputPeriod = document.getElementById('periode_input');
        const labelRarUpload = document.getElementById('label_rar_upload');
        const labelRarHelp = document.getElementById('label_rar_help');
        const labelExcelUpload = document.getElementById('label_excel_upload');
        const labelExcelHelp = document.getElementById('label_excel_help');
        const labelCsvHelp = document.getElementById('label_csv_help');
        const labelPeriodTitle = document.getElementById('label_period_title');
        const labelPeriodHelp = document.getElementById('label_period_help');
>>>>>>> 7d9de73de61f625c5ab496dc859a4792870a4fe3

        function syncDownloadButton() {
            if (!btnDownloadTemplate || !downloadTemplateSelect) {
                return;
            }

            const templateKey = downloadTemplateSelect.value || '';
            const selectedOption = downloadTemplateSelect.options[downloadTemplateSelect.selectedIndex];
            const filename = selectedOption ? (selectedOption.getAttribute('data-filename') || '') : '';

            if (templateKey) {
                const query = new URLSearchParams({
                    report: templateKey,
                });

                if (filename) {
                    query.set('file', filename);
                }

                btnDownloadTemplate.href = `${btnDownloadTemplate.dataset.routeTemplate}?${query.toString()}`;
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

        function toggleForm() {
            const selectedOption = reportSelect.options[reportSelect.selectedIndex];
            const reportName = selectedOption.getAttribute('data-name') || '';
            const tableName = selectedOption.getAttribute('data-table') || '';
            const isDailyLoan = reportName.includes('daily loan');
            const isSimpanan = reportName.includes('simpanan multipn');
<<<<<<< HEAD
            const isPerformancePis = reportName.includes('performance pis per produk');
            const isInputRekanan = tableName === 'input_rekanan';
=======
            const normalizedReportName = reportName.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
            const isPerformancePis = normalizedReportName.includes('performance pis');
            const isReportPh = tableName === 'lw325_ph'
                || normalizedReportName === 'report ph'
                || normalizedReportName.includes('report ph')
                || normalizedReportName.includes('report pinjaman')
                || normalizedReportName.includes('rekening pinjaman ph')
                || normalizedReportName.includes('nomintaif per rekening')
                || normalizedReportName.includes('nominatif rekening')
                || normalizedReportName.includes('nominal per rekening');
            const isCasaBrilink = tableName === 'casa_brilink_web'
                || tableName === 'casa_brilink_edc'
                || reportName.includes('casa brilink web')
                || reportName.includes('casa_brilink_web')
                || reportName.includes('casa brilink edc')
                || reportName.includes('casa_brilink_edc');
            const isBrimo = tableName === 'user_brimo_rpt_v2'
                || tableName === 'user_brimo_fin'
                || reportName.includes('brimo');
            const simpananExcelUploadRoute = "{{ route('import.simpanan.upload') }}";
            const simpananExcelPrepareRoute = "{{ route('import.simpanan.prepare-preview') }}";
            const simpananCsvUploadRoute = "{{ route('import.simpanan.csv.upload') }}";
            const simpananCsvPrepareRoute = "{{ route('import.simpanan.csv.prepare-preview') }}";
>>>>>>> 7d9de73de61f625c5ab496dc859a4792870a4fe3

            formRAR.style.display = 'none';
            formExcel.style.display = 'none';
            formCsv.style.display = 'none';

            inputRar.disabled = true;
            inputRar.required = false;
            inputExcel.disabled = true;
            inputExcel.required = false;
            inputCsv.disabled = true;
            inputCsv.required = false;
<<<<<<< HEAD
=======
            inputCsv.value = '';
            formPeriod.style.display = 'none';
            inputPeriod.disabled = true;
            inputPeriod.required = false;
            inputPeriod.type = 'month';
            inputPeriod.value = '';
            formImport.dataset.simpananMode = '0';
            formImport.dataset.simpananExcelUpload = simpananExcelUploadRoute;
            formImport.dataset.simpananExcelPrepare = simpananExcelPrepareRoute;
            formImport.dataset.simpananCsvUpload = simpananCsvUploadRoute;
            formImport.dataset.simpananCsvPrepare = simpananCsvPrepareRoute;
            if (labelExcelUpload) {
                labelExcelUpload.innerHTML = '<i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)';
            }
            if (labelExcelHelp) {
                labelExcelHelp.textContent = 'Mendukung format .xlsx dan .xls hingga 200MB+ (Menggunakan Chunk Reading Mode).';
            }
            if (labelPeriodTitle) {
                labelPeriodTitle.innerHTML = '<i class="fas fa-calendar-alt mr-1"></i> Periode Report';
            }
            if (labelPeriodHelp) {
                labelPeriodHelp.textContent = 'Khusus CASA BRILINK WEB/EDC, isi periode manual karena file CSV tidak memuat kolom periode.';
            }
>>>>>>> 7d9de73de61f625c5ab496dc859a4792870a4fe3

            formImport.dataset.preparePreviewUrl = '';

            if (isDailyLoan) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                formImport.action = "{{ route('import.dailyloan.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.dailyloan.prepare-preview') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-csv mr-1"></i> Upload File CSV Daily Loan Dinamis (.csv, .txt)';
                csvHelp.textContent = 'Gunakan file CSV Daily Loan Dinamis yang sudah sesuai template untuk diproses ke database.';
                applyButtonState('csv', '<i class="fas fa-file-csv"></i> Upload CSV');
                return;
            }

<<<<<<< HEAD
            if (isSimpanan || isInputRekanan) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                formImport.action = "{{ route('import.excel.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.excel.prepare-preview') }}";
                applyButtonState('excel', '<i class="fas fa-file-excel"></i> Upload Excel');
                return;
=======
                formImport.action = "{{ route('import.upload') }}";
                formImport.dataset.preparePreviewUrl = '';
                formImport.dataset.uploadFlow = 'direct-submit';

                btnSubmit.className = "btn btn-success font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-csv"></i> Upload CSV Daily Loan';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;

            } else if (isSimpanan) {
                // Simpanan MultiPN: satu jalur upload spreadsheet, route ditentukan dari ekstensi file
                formRAR.style.display = 'none';
                formExcel.style.display = 'block';
                formCsv.style.display = 'none';

                inputRar.disabled = true;
                inputRar.required = false;

                inputExcel.disabled = false;
                inputExcel.required = true;
                inputExcel.setAttribute('accept', '.xlsx,.xls,.csv,.txt');
                if (labelExcelUpload) {
                    labelExcelUpload.innerHTML = '<i class="fas fa-file-upload mr-1"></i> Upload File Simpanan MultiPN (.csv, .xlsx, .xls)';
                }
                if (labelExcelHelp) {
                    labelExcelHelp.textContent = 'CSV akan diproses lewat jalur import khusus Simpanan MultiPN. Excel tetap memakai engine preview + stream agar insert ke database lebih stabil.';
                }
                inputCsv.disabled = true;
                inputCsv.required = false;

                formImport.action = simpananExcelUploadRoute;
                formImport.dataset.preparePreviewUrl = simpananExcelPrepareRoute;
                formImport.dataset.uploadFlow = 'excel-preview';
                formImport.dataset.simpananMode = '1';

                btnSubmit.className = "btn btn-success font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-upload"></i> Upload CSV / Excel';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;

            } else if (isPerformancePis) {
                formRAR.style.display = 'block';
                formExcel.style.display = 'none';
                formCsv.style.display = 'none';
                formPeriod.style.display = 'block';

                inputRar.disabled = false;
                inputRar.required = true;
                inputRar.setAttribute('accept', '.rar,.csv');
                labelRarUpload.textContent = 'Upload File (.rar / .csv)';
                labelRarHelp.textContent = 'Bisa upload file .rar untuk diekstrak otomatis, atau langsung upload file .csv Performance PIS Per Produk.';
                inputExcel.disabled = true;
                inputExcel.required = false;
                inputCsv.disabled = true;
                inputCsv.required = false;
                inputPeriod.disabled = false;
                inputPeriod.required = true;
                inputPeriod.type = 'date';
                if (labelPeriodTitle) {
                    labelPeriodTitle.innerHTML = '<i class="fas fa-calendar-alt mr-1"></i> Tanggal PIS Per Produk';
                }
                if (labelPeriodHelp) {
                    labelPeriodHelp.textContent = 'Pilih tanggal posisi/report Performance PIS Per Produk sebelum masuk ke halaman preview.';
                }

                formImport.action = "{{ route('import.performancepis.upload') }}";
                formImport.dataset.preparePreviewUrl = '';
                formImport.dataset.uploadFlow = 'direct-submit';

                btnSubmit.className = "btn btn-info font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-upload"></i> Upload File PIS';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;

            } else if (isReportPh) {
                formRAR.style.display = 'none';
                formExcel.style.display = 'none';
                formCsv.style.display = 'block';
                formPeriod.style.display = 'none';

                inputRar.disabled = true;
                inputRar.required = false;
                inputExcel.disabled = true;
                inputExcel.required = false;
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt');
                inputPeriod.disabled = true;
                inputPeriod.required = false;

                if (labelCsvHelp) {
                    labelCsvHelp.textContent = 'Upload file CSV/TXT Report Nominatif Rekening Pinjaman PH. Kolom nomor urut seperti Textbox3 tidak akan ikut diimport ke database.';
                }

                formImport.action = "{{ route('import.reportph.upload') }}";
                formImport.dataset.preparePreviewUrl = '';
                formImport.dataset.uploadFlow = 'direct-submit';

                btnSubmit.className = "btn btn-info font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-csv"></i> Upload CSV Nominatif PH';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;

            } else if (isCasaBrilink) {
                formRAR.style.display = 'none';
                formExcel.style.display = 'none';
                formCsv.style.display = 'block';
                formPeriod.style.display = 'block';

                inputRar.disabled = true;
                inputRar.required = false;
                inputExcel.disabled = true;
                inputExcel.required = false;
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv');
                inputPeriod.disabled = false;
                inputPeriod.required = true;
                inputPeriod.type = 'month';
                if (labelPeriodTitle) {
                    labelPeriodTitle.innerHTML = '<i class="fas fa-calendar-alt mr-1"></i> Periode CASA BRILINK (Bulan/Tahun)';
                }
                if (labelPeriodHelp) {
                    labelPeriodHelp.textContent = 'Wajib pilih periode bulanan karena file CSV CASA BRILINK WEB/EDC tidak memiliki kolom periode.';
                }
                if (labelCsvHelp) {
                    labelCsvHelp.textContent = 'Upload file CSV untuk CASA BRILINK WEB/EDC, lalu pilih periode bulanan secara manual.';
                }

                formImport.action = "{{ route('import.casabrilink.upload') }}";
                formImport.dataset.preparePreviewUrl = '';
                formImport.dataset.uploadFlow = 'direct-submit';

                btnSubmit.className = "btn btn-warning font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-csv"></i> Upload CSV CASA';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;

            } else if (isBrimo) {
                // 🔥 BRIMO: Tampilkan RAR, arahkan ke ImportFileBrimoController
                formRAR.style.display = 'block';
                formExcel.style.display = 'none';
                formCsv.style.display = 'none';

                inputExcel.disabled = true;
                inputExcel.required = false;
                inputCsv.disabled = true;
                inputCsv.required = false;

                inputRar.disabled = false;
                inputRar.required = true;

                // Arahkan submit ke Brimo Controller
                formImport.action = "{{ route('import.brimo.upload') }}";
                formImport.dataset.preparePreviewUrl = '';
                formImport.dataset.uploadFlow = 'direct-submit';
                labelRarUpload.textContent = 'Upload File Extracted (.rar)';
                labelRarHelp.textContent = 'Sistem akan mengekstrak otomatis dan mendeteksi file CSV di dalamnya.';
                inputRar.setAttribute('accept', '.rar');

                btnSubmit.className = "btn btn-primary font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-archive"></i> Upload RAR';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;

            } else {
                // Tampilkan RAR, Sembunyikan Excel
                formRAR.style.display = 'block';
                formExcel.style.display = 'none';
                formCsv.style.display = 'none';

                // 🔥 MATIKAN input EXCEL agar tidak bentrok
                inputExcel.disabled = true;
                inputExcel.required = false;

                inputRar.disabled = false;
                inputRar.required = true;

                // Arahkan submit ke Controller CSV/Legacy
                formImport.action = "{{ route('import.upload') }}";
                formImport.dataset.preparePreviewUrl = '';
                formImport.dataset.uploadFlow = 'direct-submit';
                labelRarUpload.textContent = 'Upload File (.rar / .csv)';
                labelRarHelp.textContent = 'Bisa upload file .rar untuk diekstrak otomatis, atau langsung upload file .csv tanpa dibungkus arsip.';
                inputRar.setAttribute('accept', '.rar,.csv');
                inputPeriod.value = '';
                if (labelCsvHelp) {
                    labelCsvHelp.textContent = 'Gunakan file CSV Performance PIS Per Produk dengan metadata posisi di bagian atas file.';
                }

                // Sesuaikan Tombol
                btnSubmit.className = "btn btn-primary font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-upload"></i> Upload File';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;
>>>>>>> 7d9de73de61f625c5ab496dc859a4792870a4fe3
            }

            if (isPerformancePis) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                formImport.action = "{{ route('import.performancepis.upload') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-csv mr-1"></i> Upload File CSV (.csv, .txt)';
                csvHelp.textContent = 'Gunakan file CSV Performance PIS Per Produk dengan metadata posisi di bagian atas file.';
                applyButtonState('csv', '<i class="fas fa-file-csv"></i> Upload CSV');
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
            formImport.action = "{{ route('import.upload') }}";
            applyButtonState('rar', '<i class="fas fa-file-archive"></i> Upload RAR');
        }

        reportSelect.addEventListener('change', toggleForm);
        downloadTemplateSelect?.addEventListener('change', syncDownloadButton);
        btnDownloadTemplate?.addEventListener('click', function (event) {
            if (btnDownloadTemplate.classList.contains('disabled')) {
                event.preventDefault();
            }
        });

        toggleForm();
        syncDownloadButton();

        $('#file_rar').on('change', function () {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });

        formImport.addEventListener('submit', function(e) {
            e.preventDefault();

<<<<<<< HEAD
            const hasAsyncPreview = Boolean(formImport.dataset.preparePreviewUrl);
            const uploadKind = formImport.dataset.uploadKind || 'rar';
            const titleText = uploadKind === 'excel'
                ? 'Uploading Excel...'
                : uploadKind === 'csv'
                    ? 'Uploading CSV...'
                    : 'Uploading Report...';
            const descText = hasAsyncPreview
                ? 'File sedang diproses dan disiapkan untuk preview.<br><b>Mohon tunggu...</b>'
=======
            const usesPreviewStream = formImport.dataset.uploadFlow === 'excel-preview';
            const selectedValue = reportSelect.value || '';
            const selectedMeta = reportMetaMap[selectedValue] || {};
            const selectedName = (selectedMeta.name || '').toLowerCase();
            const isDailyLoanPreview = selectedName.includes('daily loan');
            const isSimpananUpload = formImport.dataset.simpananMode === '1';
            const simpananSelectedFile = inputExcel && inputExcel.files && inputExcel.files[0] ? inputExcel.files[0].name.toLowerCase() : '';
            const simpananUsesCsv = isSimpananUpload && /\.(csv|txt)$/i.test(simpananSelectedFile);
            if (isSimpananUpload) {
                formImport.action = simpananUsesCsv
                    ? (formImport.dataset.simpananCsvUpload || formImport.action)
                    : (formImport.dataset.simpananExcelUpload || formImport.action);
                formImport.dataset.preparePreviewUrl = simpananUsesCsv
                    ? (formImport.dataset.simpananCsvPrepare || formImport.dataset.preparePreviewUrl)
                    : (formImport.dataset.simpananExcelPrepare || formImport.dataset.preparePreviewUrl);
            }
            const titleText = usesPreviewStream
                ? (isDailyLoanPreview
                    ? 'Uploading CSV Daily Loan...'
                    : (simpananUsesCsv ? 'Uploading CSV Simpanan MultiPN...' : 'Uploading Spreadsheet...'))
                : 'Uploading Report...';
            const descText = usesPreviewStream
                ? (isDailyLoanPreview
                    ? 'File CSV sedang dianalisis untuk preview dan filter.<br><b>Mohon tunggu...</b>'
                    : (simpananUsesCsv
                        ? 'File CSV Simpanan MultiPN sedang dianalisis untuk preview dan filter.<br><b>Mohon tunggu...</b>'
                        : 'File besar sedang diproses dengan chunking.<br><b>Mohon tunggu...</b>'))
>>>>>>> 7d9de73de61f625c5ab496dc859a4792870a4fe3
                : 'Sedang mengupload dan memproses file.<br><b>Mohon tunggu...</b>';

            const progressHtml = `
                <div class="text-center mb-3">
                    <span style="font-size: 14px; color: #64748b;" id="swal-desc-text">${descText}</span>
                </div>
                <div class="progress" style="height: 16px; border-radius: 999px; background-color: #e2e8f0; overflow: hidden; box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);">
                    <div id="swal-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar" style="width: 0%; font-weight: 700; font-size: 12px; line-height: 16px; background: linear-gradient(135deg, #0f766e, #115e59);"
                         aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <div class="text-center mt-3">
                    <small id="swal-progress-text" style="color: #0f766e; font-weight: 700; letter-spacing: 0.02em;">Memulai proses...</small>
                </div>
            `;

            themedSwal({
                title: titleText,
                html: progressHtml,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                width: 520,
                didOpen: () => {
                    if (btnSubmit) {
                        btnSubmit.disabled = true;
                        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }

                    if (!hasAsyncPreview) {
                        formImport.submit();
                        return;
                    }

                    const formData = new FormData(formImport);
                    const uploadProgressBar = document.getElementById('swal-progress-bar');
                    const uploadProgressText = document.getElementById('swal-progress-text');

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
                        if (uploadProgressText) {
                            uploadProgressText.innerText = 'Mengunggah file ke server... ' + percent + '%';
                        }
                    });

                    uploadRequest.addEventListener('load', function() {
                        if (uploadRequest.status < 200 || uploadRequest.status >= 300) {
                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: 'Upload gagal: ' + uploadRequest.statusText
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

                        if (uploadProgressBar) {
                            uploadProgressBar.style.width = '88%';
                            uploadProgressBar.innerText = '88%';
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

        @if(session('sweet_success'))
            themedSwal({
                icon: 'success',
                title: '{!! session('sweet_success')['title'] !!}',
                html: '{!! session('sweet_success')['text'] !!}',
                confirmButtonText: 'Tutup'
            });
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
