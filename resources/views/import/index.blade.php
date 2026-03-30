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
    <form id="form-import" method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data">
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

        function toggleForm() {
            const selectedOption = reportSelect.options[reportSelect.selectedIndex];
            const reportName = selectedOption.getAttribute('data-name') || '';

            // CEK KEYWORD EXCEL KHUSUS
            if (reportName.includes('daily loan') || reportName.includes('simpanan multipn')) {
                // Tampilkan Excel, Sembunyikan RAR
                formRAR.style.display = 'none';
                formExcel.style.display = 'block';

                // 🔥 MATIKAN input RAR agar tidak bentrok 'name="file"' di Backend
                inputRar.disabled = true;
                inputRar.required = false;

                inputExcel.disabled = false;
                inputExcel.required = true;

                // Arahkan submit ke Controller Excel
                formImport.action = "{{ route('import.excel.upload') }}";

                // Sesuaikan Tombol
                btnSubmit.className = "btn btn-success font-weight-bold";
                btnSubmit.innerHTML = '<i class="fas fa-file-excel"></i> Upload Excel';
            } else {
                // Tampilkan RAR, Sembunyikan Excel
                formRAR.style.display = 'block';
                formExcel.style.display = 'none';

                // 🔥 MATIKAN input EXCEL agar tidak bentrok
                inputExcel.disabled = true;
                inputExcel.required = false;

                inputRar.disabled = false;
                inputRar.required = true;

                // Arahkan submit ke Controller CSV/Legacy
                formImport.action = "{{ route('import.upload') }}";

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
        // 🔥 LOGIKA SCRIPT LOADING UX 
        // ==========================================
        formImport.addEventListener('submit', function(e) {
            // Cek apakah target action mengandung kata 'excel'
            const isExcel = formImport.action.includes('excel');

            Swal.fire({
                title: isExcel ? 'Uploading Excel...' : 'Uploading...',
                html: isExcel 
                    ? 'File besar sedang diproses dengan chunking.<br><b>Mohon tunggu...</b>' 
                    : 'Sedang mengupload dan memproses file.<br><b>Mohon tunggu...</b>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Bonus: Disable button agar lebih Pro Level!
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            }
        });
    });

    // ==========================================
    // NOTIFIKASI SWEETALERT EXISTING
    // ==========================================
    @if(session('sweet_success'))
        Swal.fire({
            icon: 'success',
            title: '{!! session('sweet_success')['title'] !!}',
            html: '{!! session('sweet_success')['text'] !!}',
            confirmButtonColor: '#28a745'
        });
    @endif

    @if(session('sweet_warning'))
        Swal.fire({
            icon: 'warning',
            title: '{!! session('sweet_warning')['title'] !!}',
            html: '{!! session('sweet_warning')['text'] !!}',
            confirmButtonColor: '#ffc107'
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '{{ session('error') }}',
            confirmButtonColor: '#dc3545'
        });
    @endif
</script>
@endsection