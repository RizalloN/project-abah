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
                    <option value="" data-name="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}" data-name="{{ strtolower($report->nama_report) }}">
                            {{ $report->nama_report }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="form-rar" class="form-group">
                <label>Upload File Extracted (.rar)</label>
                <div class="custom-file">
                    <input type="file" id="file_rar" name="file" class="custom-file-input" accept=".rar" required>
                    <label class="custom-file-label" for="file_rar">Pilih file .rar...</label>
                </div>
                <small class="text-muted mt-2 d-block">Sistem akan mengekstrak otomatis dan mendeteksi file CSV di dalamnya.</small>
            </div>

            <div id="form-excel" class="form-group" style="display: none;">
                <label class="text-success font-weight-bold"><i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)</label>
                <input type="file" id="file_excel" name="file" class="form-control border-success shadow-sm" accept=".xlsx, .xls">
                <small class="text-muted mt-2 d-block">Mendukung format .xlsx dan .xls hingga 200MB+ (Menggunakan Chunk Reading Mode).</small>
            </div>

            <div id="form-csv" class="form-group" style="display: none;">
                <label class="text-info font-weight-bold"><i class="fas fa-file-csv mr-1"></i> Upload File CSV (.csv, .txt)</label>
                <input type="file" id="file_csv" name="file" class="form-control border-info shadow-sm" accept=".csv,.txt">
                <small class="text-muted mt-2 d-block">Gunakan file CSV Performance PIS Per Produk dengan metadata posisi di bagian atas file.</small>
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

        function toggleForm() {
            const selectedOption = reportSelect.options[reportSelect.selectedIndex];
            const reportName = selectedOption.getAttribute('data-name') || '';
            const isDailyLoan = reportName.includes('daily loan');
            const isSimpanan = reportName.includes('simpanan multipn');
            const isPerformancePis = reportName.includes('performance pis per produk');

            formCsv.style.display = 'none';
            inputCsv.disabled = true;
            inputCsv.required = false;

            // CEK KEYWORD EXCEL KHUSUS
            if (isDailyLoan || isSimpanan) {
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
                formImport.action = isDailyLoan ? "{{ route('import.dailyloan.upload') }}" : "{{ route('import.excel.upload') }}";
                formImport.dataset.preparePreviewUrl = isDailyLoan ? "{{ route('import.dailyloan.prepare-preview') }}" : "{{ route('import.excel.prepare-preview') }}";

                // Sesuaikan Tombol
                btnSubmit.className = "btn btn-success font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-excel"></i> Upload Excel';

            } else if (isPerformancePis) {
                formRAR.style.display = 'none';
                formExcel.style.display = 'none';
                formCsv.style.display = 'block';

                inputRar.disabled = true;
                inputRar.required = false;
                inputExcel.disabled = true;
                inputExcel.required = false;
                inputCsv.disabled = false;
                inputCsv.required = true;

                formImport.action = "{{ route('import.performancepis.upload') }}";
                formImport.dataset.preparePreviewUrl = '';

                btnSubmit.className = "btn btn-info font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-csv"></i> Upload CSV';

            } else if (reportName.includes('brimo')) {
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

                btnSubmit.className = "btn btn-primary font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-archive"></i> Upload RAR';

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

                // Sesuaikan Tombol
                btnSubmit.className = "btn btn-primary font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-archive"></i> Upload RAR';
            }
        }

        // Jalankan saat User mengganti pilihan dropdown
        reportSelect.addEventListener('change', toggleForm);
        
        // Jalankan pada muatan pertama untuk handle refresh/back browser
        toggleForm();

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

            // Cek apakah target action mengandung kata 'excel'
            const isExcel = formImport.action.includes('excel');
            const titleText = isExcel ? 'Uploading Excel...' : 'Uploading Report...';
            const descText = isExcel 
                ? 'File besar sedang diproses dengan chunking.<br><b>Mohon tunggu...</b>' 
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
                    if (btnSubmit) {
                        btnSubmit.disabled = true;
                        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }

                    if (!isExcel) {
                        // For non-Excel, submit synchronously as before
                        formImport.submit();
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
                    .then(function(response) {
                        if (!response.ok) throw new Error('Upload gagal: ' + response.statusText);
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.status !== 'success') throw new Error('Upload error: ' + (data.message || 'Unknown'));

                        // Connect to SSE for prepare-preview
                        const preparePreviewUrl = formImport.dataset.preparePreviewUrl || "{{ route('import.excel.prepare-preview') }}";
                        const eventSource = new EventSource(preparePreviewUrl);

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
                        if (btnSubmit) {
                            btnSubmit.disabled = false;
                            btnSubmit.innerHTML = '<i class="fas fa-file-excel"></i> Upload Excel';
                        }
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
