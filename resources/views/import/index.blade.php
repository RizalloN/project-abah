@extends('layouts.admin')

@section('title', 'Import Data')

@section('content')

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="card-title font-weight-bold text-dark">
            <i class="fas fa-cloud-upload-alt text-primary mr-2"></i> Upload Data Report
        </h5>
    </div>

    <!-- 🔥 FIX 1: UBAH ID FORM -->
    <form id="form-import" method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" data-prepare-preview-url="{{ route('import.excel.prepare-preview') }}">
        @csrf

        <div class="card-body">

            <div class="form-group">
                <label>Pilih Kategori Report</label>
                <select name="id_report" class="form-control select2" required>
                    <option value="" data-name="" data-table-name="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}" data-name="{{ strtolower($report->nama_report) }}" data-table-name="{{ strtolower($report->table_name ?? '') }}">
                            {{ $report->nama_report }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="form-rar" class="form-group">
                <label id="label_rar_upload">Upload File (.rar / .csv)</label>
                <div class="custom-file">
                    <input type="file" id="file_rar" name="file" class="custom-file-input" accept=".rar,.csv" required>
                    <label class="custom-file-label" for="file_rar">Pilih file .rar / .csv...</label>
                </div>
                <small id="label_rar_help" class="text-muted mt-2 d-block">Bisa upload file .rar untuk diekstrak otomatis, atau langsung upload file .csv tanpa dibungkus arsip.</small>
            </div>

            <div id="form-excel" class="form-group" style="display: none;">
                <label class="text-success font-weight-bold"><i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)</label>
                <input type="file" id="file_excel" name="file" class="form-control border-success shadow-sm" accept=".xlsx, .xls">
                <small class="text-muted mt-2 d-block">Mendukung format .xlsx dan .xls hingga 200MB+ (Menggunakan Chunk Reading Mode).</small>
            </div>

            <div id="form-csv" class="form-group" style="display: none;">
                <label class="text-info font-weight-bold"><i class="fas fa-file-csv mr-1"></i> Upload File CSV (.csv)</label>
                <input type="file" id="file_csv" name="file" class="form-control border-info shadow-sm" accept=".csv">
                <small id="label_csv_help" class="text-muted mt-2 d-block">Gunakan file CSV Performance PIS Per Produk dengan metadata posisi di bagian atas file.</small>
            </div>

            <div id="form-period" class="form-group" style="display: none;">
                <label id="label_period_title" class="text-warning font-weight-bold"><i class="fas fa-calendar-alt mr-1"></i> Periode Report</label>
                <input type="month" id="periode_input" name="periode" class="form-control border-warning shadow-sm">
                <small id="label_period_help" class="text-muted mt-2 d-block">Khusus CASA BRILINK WEB/EDC, isi periode manual karena file CSV tidak memuat kolom periode.</small>
            </div>

        </div>

        <div class="card-footer bg-light">
            <button type="submit" id="btn-submit" class="btn btn-primary font-weight-bold">
                <i class="fas fa-upload"></i> Process RAR
            </button>
        </div>

    </form>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reportMetaMap = @json($reports->keyBy('id_report')->map(function ($report) {
            return [
                'name' => strtolower($report->nama_report ?? ''),
                'table_name' => strtolower($report->table_name ?? ''),
            ];
        })->toArray());

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

        // ==========================================
        // 🔥 LOGIKA FORM DINAMIS (RAR vs EXCEL)
        // ==========================================
        const reportSelect = document.querySelector('select[name="id_report"]');
        const formRAR = document.getElementById('form-rar');
        const formExcel = document.getElementById('form-excel');
        const formImport = document.getElementById('form-import');
        const btnSubmit = document.getElementById('btn-submit');
        const inputRar = document.getElementById('file_rar');
        const inputExcel = document.getElementById('file_excel');
        const formCsv = document.getElementById('form-csv');
        const inputCsv = document.getElementById('file_csv');
        const formPeriod = document.getElementById('form-period');
        const inputPeriod = document.getElementById('periode_input');
        const labelRarUpload = document.getElementById('label_rar_upload');
        const labelRarHelp = document.getElementById('label_rar_help');
        const labelCsvHelp = document.getElementById('label_csv_help');
        const labelPeriodTitle = document.getElementById('label_period_title');
        const labelPeriodHelp = document.getElementById('label_period_help');

        function setSubmitButtonState(isProcessing) {
            const currentLabel = btnSubmit.dataset.defaultLabel || '<i class="fas fa-upload"></i> Upload File';
            btnSubmit.disabled = !!isProcessing;
            btnSubmit.innerHTML = isProcessing
                ? '<i class="fas fa-spinner fa-spin"></i> Processing...'
                : currentLabel;
        }

        function updateModalProgress(percent, message) {
            const progressBar = document.getElementById('swal-progress-bar');
            const progressText = document.getElementById('swal-progress-text');

            if (progressBar && percent != null) {
                progressBar.style.width = percent + '%';
                progressBar.innerText = percent + '%';
                progressBar.setAttribute('aria-valuenow', percent);
            }

            if (progressText && message) {
                progressText.innerText = message;
            }
        }

        function toggleForm() {
            const selectedOption = reportSelect.options[reportSelect.selectedIndex];
            const selectedValue = reportSelect.value || '';
            const reportMeta = reportMetaMap[selectedValue] || {};
            const reportName = (reportMeta.name || selectedOption.getAttribute('data-name') || '').toLowerCase();
            const tableName = (reportMeta.table_name || selectedOption.getAttribute('data-table-name') || '').toLowerCase();
            const isDailyLoan = reportName.includes('daily loan');
            const isSimpanan = reportName.includes('simpanan multipn');
            const normalizedReportName = reportName.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
            const isPerformancePis = normalizedReportName.includes('performance pis');
            const isCasaBrilink = tableName === 'casa_brilink_web'
                || tableName === 'casa_brilink_edc'
                || reportName.includes('casa brilink web')
                || reportName.includes('casa_brilink_web')
                || reportName.includes('casa brilink edc')
                || reportName.includes('casa_brilink_edc');
            const isBrimo = tableName === 'user_brimo_rpt_v2'
                || tableName === 'user_brimo_fin'
                || reportName.includes('brimo');

            formCsv.style.display = 'none';
            inputCsv.disabled = true;
            inputCsv.required = false;
            inputCsv.value = '';
            formPeriod.style.display = 'none';
            inputPeriod.disabled = true;
            inputPeriod.required = false;
            inputPeriod.type = 'month';
            inputPeriod.value = '';
            if (labelPeriodTitle) {
                labelPeriodTitle.innerHTML = '<i class="fas fa-calendar-alt mr-1"></i> Periode Report';
            }
            if (labelPeriodHelp) {
                labelPeriodHelp.textContent = 'Khusus CASA BRILINK WEB/EDC, isi periode manual karena file CSV tidak memuat kolom periode.';
            }

            // CEK KEYWORD EXCEL KHUSUS
            if (isDailyLoan) {
                formRAR.style.display = 'none';
                formExcel.style.display = 'none';
                formCsv.style.display = 'block';

                inputRar.disabled = true;
                inputRar.required = false;
                inputExcel.disabled = true;
                inputExcel.required = false;
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv');
                if (labelCsvHelp) {
                    labelCsvHelp.textContent = 'Gunakan file CSV Daily Loan Dinamis terbaru sesuai struktur kolom baru.';
                }

                formImport.action = "{{ route('import.dailyloan.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.dailyloan.prepare-preview') }}";
                formImport.dataset.uploadFlow = 'excel-preview';

                btnSubmit.className = "btn btn-success font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-csv"></i> Upload CSV Daily Loan';
                btnSubmit.dataset.defaultLabel = btnSubmit.innerHTML;

            } else if (isSimpanan) {
                // Tampilkan Excel, Sembunyikan RAR
                formRAR.style.display = 'none';
                formExcel.style.display = 'block';
                formCsv.style.display = 'none';

                // 🔥 MATIKAN input RAR agar tidak bentrok 'name="file"' di Backend
                inputRar.disabled = true;
                inputRar.required = false;

                inputExcel.disabled = false;
                inputExcel.required = true;
                inputCsv.disabled = true;
                inputCsv.required = false;

                // Arahkan submit ke Controller Excel sesuai flow report
                formImport.action = "{{ route('import.excel.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.excel.prepare-preview') }}";
                formImport.dataset.uploadFlow = 'excel-preview';

                // Sesuaikan Tombol
                btnSubmit.className = "btn btn-success font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-excel"></i> Upload Excel';
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
            }
        }

        function refreshReportForm() {
            toggleForm();
        }

        // Jalankan saat User mengganti pilihan dropdown
        reportSelect.addEventListener('change', refreshReportForm);
        window.addEventListener('load', refreshReportForm);
        window.addEventListener('pageshow', refreshReportForm);
        
        // Jalankan pada muatan pertama untuk handle refresh/back browser
        refreshReportForm();
        setTimeout(refreshReportForm, 0);
        setTimeout(refreshReportForm, 150);

        if (window.jQuery && window.jQuery.fn) {
            window.jQuery(reportSelect).on('change.select2 select2:select', refreshReportForm);
        }

        // Kosmetik nama file RAR Bootstrap
        $('#file_rar').on('change',function(){
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });

        // ==========================================
        // 🔥 LOGIKA SCRIPT LOADING UX (PROGRESS BAR)
        // ==========================================
        formImport.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission for all cases to handle uniformly

            const usesPreviewStream = formImport.dataset.uploadFlow === 'excel-preview';
            const selectedValue = reportSelect.value || '';
            const selectedMeta = reportMetaMap[selectedValue] || {};
            const selectedName = (selectedMeta.name || '').toLowerCase();
            const isDailyLoanPreview = selectedName.includes('daily loan');
            const titleText = usesPreviewStream
                ? (isDailyLoanPreview ? 'Uploading CSV Daily Loan...' : 'Uploading Excel...')
                : 'Uploading Report...';
            const descText = usesPreviewStream
                ? (isDailyLoanPreview
                    ? 'File CSV sedang dianalisis untuk preview dan filter.<br><b>Mohon tunggu...</b>'
                    : 'File besar sedang diproses dengan chunking.<br><b>Mohon tunggu...</b>')
                : 'Sedang mengupload dan memproses file.<br><b>Mohon tunggu...</b>';

            // HTML Custom untuk Progress Bar
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
                    // Disable button agar tidak double submit
                    setSubmitButtonState(true);

                    if (!usesPreviewStream) {
                        const formData = new FormData(formImport);
                        const xhr = new XMLHttpRequest();

                        xhr.open('POST', formImport.action, true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Accept', 'text/html,application/xhtml+xml,application/json');

                        xhr.upload.onprogress = function(event) {
                            if (!event.lengthComputable) {
                                updateModalProgress(25, 'Mengupload file ke server...');
                                return;
                            }

                            const percent = Math.min(90, Math.max(5, Math.round((event.loaded / event.total) * 90)));
                            updateModalProgress(percent, `Mengupload file... ${percent}%`);
                        };

                        xhr.onloadstart = function() {
                            updateModalProgress(5, 'Memulai upload file...');
                        };

                        xhr.onload = function() {
                            if (xhr.status >= 200 && xhr.status < 400) {
                                updateModalProgress(100, 'Upload selesai. Membuka halaman preview...');
                                const redirectUrl = xhr.responseURL || formImport.action;
                                window.location.href = redirectUrl;
                                return;
                            }

                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: 'Upload gagal diproses oleh server.'
                            });
                            setSubmitButtonState(false);
                        };

                        xhr.onerror = function() {
                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: 'Terjadi masalah koneksi saat mengupload file.'
                            });
                            setSubmitButtonState(false);
                        };

                        xhr.onloadend = function() {
                            if (xhr.status >= 200 && xhr.status < 400) {
                                updateModalProgress(95, 'Upload selesai. Menyiapkan preview...');
                            }
                        };

                        xhr.send(formData);
                        return;
                    }

                    // For Excel: AJAX upload
                    const formData = new FormData(formImport);
                    fetch(formImport.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData
                    })
                    .then(async function(response) {
                        const rawText = await response.text();
                        let data = {};

                        try {
                            data = rawText ? JSON.parse(rawText) : {};
                        } catch (_) {
                            throw new Error('Server mengembalikan respons yang tidak valid.');
                        }

                        if (!response.ok) {
                            throw new Error(data.message || ('Upload gagal: ' + response.statusText));
                        }

                        return data;
                    })
                    .then(function(data) {
                        if (data.status !== 'success') throw new Error('Upload error: ' + (data.message || 'Unknown'));

                        // Connect to SSE for prepare-preview
                        const preparePreviewUrl = formImport.dataset.preparePreviewUrl || "{{ route('import.excel.prepare-preview') }}";
                        const eventSource = new EventSource(preparePreviewUrl);
                        let previewReady = false;

                        // ── progress event ──────────────────────────────────
                        eventSource.addEventListener('progress', function(event) {
                            var evtData = {};
                            try { evtData = JSON.parse(event.data); } catch(_) {}
                            var progressBar  = document.getElementById('swal-progress-bar');
                            var progressText = document.getElementById('swal-progress-text');
                            if (progressBar && evtData.percent != null) {
                                progressBar.style.width = evtData.percent + '%';
                                progressBar.innerText   = evtData.percent + '%';
                            }
                            if (progressText && evtData.message) {
                                progressText.innerText = evtData.message;
                            }
                        });

                        // ── ready event ─────────────────────────────────────
                        eventSource.addEventListener('ready', function(event) {
                            var evtData = {};
                            try { evtData = JSON.parse(event.data); } catch(_) {}
                            previewReady = true;
                            eventSource.close();
                            if (evtData.redirect) {
                                window.location.href = evtData.redirect;
                            }
                        });

                        // ── error_msg event (server-sent named error) ───────
                        eventSource.addEventListener('error_msg', function(event) {
                            var evtData = {};
                            try { evtData = JSON.parse(event.data); } catch(_) {}
                            eventSource.close();
                            themedSwal({
                                icon: 'error',
                                title: 'Error',
                                text: evtData.message || 'Terjadi kesalahan server.'
                            });
                            resetSubmitButton();
                        });

                        // ── onerror (network drop / connection closed) ───────
                        eventSource.onerror = function() {
                            if (previewReady) {
                                return;
                            }
                            eventSource.close();
                            themedSwal({
                                icon: 'error',
                                title: 'Koneksi Terputus',
                                text: 'Gagal terhubung ke server untuk update progress.'
                            });
                            resetSubmitButton();
                        };
                    })
                    .catch(function(error) {
                        themedSwal({
                            icon: 'error',
                            title: 'Upload Error',
                            text: error.message
                        });
                        resetSubmitButton();
                    });

                    function resetSubmitButton() {
                        setSubmitButtonState(false);
                    }
                }
            });
        });
    });

    // ==========================================
    // NOTIFIKASI SWEETALERT EXISTING
    // ==========================================
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
</script>
<style>
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
</style>
@endsection
