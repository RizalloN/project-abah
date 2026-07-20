@extends('layouts.admin')

@section('title', 'Import Data')

@section('content')

<div class="import-page">
    <div class="card border-0 mb-3 shadow-sm import-template-card import-template-bar">
        <div class="card-body import-template-card__body">
            <div class="import-template-bar__intro">
                <i class="fas fa-file-download import-template-bar__icon"></i>
                <div>
                    <div class="import-template-bar__title">Template report</div>
                    <div class="import-template-card__text">Unduh format referensi sebelum menyiapkan file.</div>
                </div>
            </div>
            <div class="import-template-actions">
                <div class="import-template-select">
                    <select id="download-template-select" class="form-control select2" data-placeholder="Cari Report">
                        <option value="">Cari report template</option>
                        @foreach($downloadTemplates as $key => $template)
                            <option value="{{ $key }}" data-filename="{{ $template['filename'] }}">{{ $template['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button"
                   id="btn-download-template"
                   class="btn btn-primary disabled import-template-button"
                   aria-disabled="true"
                   data-route-template="{{ route('import.template') }}">
                    <i class="fas fa-download mr-1"></i> Unduh
                </button>
            </div>
        </div>
    </div>

<div id="download-toast-stack" class="download-toast-stack" aria-live="polite" aria-atomic="true"></div>

    <div class="card border-0 mb-4 shadow-sm import-upload-card import-workspace">
        <div class="card-header border-0 bg-transparent import-upload-card__header">
            <div>
                <span class="import-upload-card__eyebrow">Import data</span>
                <h5 class="font-weight-bold text-dark mb-1 import-upload-card__title">
                    Siapkan file import
                </h5>
                <p class="import-upload-card__subtitle mb-0">Pilih report, isi konteks yang diperlukan, lalu pilih file untuk menuju preview.</p>
            </div>
            <div class="import-workspace__context" aria-live="polite">
                <div class="import-workspace__context-item import-workspace__context-item--report">
                    <span>Report</span>
                    <strong id="summary-report-name">Belum dipilih</strong>
                </div>
                <div class="import-workspace__context-item">
                    <span>Format</span>
                    <strong id="summary-upload-type">-</strong>
                </div>
                <div class="import-workspace__context-item">
                    <span>Periode</span>
                    <strong id="summary-periode-status">Otomatis</strong>
                </div>
                <div class="import-workspace__context-item">
                    <span>Target</span>
                    <strong id="summary-target-table">-</strong>
                </div>
            </div>
        </div>

    <form id="form-import" method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" data-prepare-preview-url="" data-upload-limits-url="{{ route('import.upload-limits') }}" data-chunked-upload="" data-chunk-init-url="" data-chunk-upload-url="" data-chunk-finalize-url="" data-no-route-loading>
        @csrf

        <div class="card-body import-upload-card__body">
            <div class="import-workflow">
                <section class="import-workflow__section import-workflow__section--details">
                    <div class="import-workflow__heading">
                        <span class="import-workflow__step">1</span>
                        <div>
                            <h6>Konfigurasi report</h6>
                            <p>Gunakan report yang sesuai dengan file sumber.</p>
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label class="font-weight-bold text-dark mb-2">Pilih Report</label>
                        <select name="id_report" class="form-control select2" required>
                            <option value="" data-name="" data-table="">-- Pilih Report --</option>
                            @foreach($reports as $report)
                                <option value="{{ $report->id_report }}"
                                        data-name="{{ strtolower($report->nama_report ?? '') }}"
                                        data-table="{{ strtolower($report->table_name ?? '') }}"
                                        data-import-controller="{{ $report->import_controller ?? '' }}"
                                        data-manual-periode="{{ (int) ($report->requires_manual_periode ?? 0) }}"
                                        data-manual-periode-type="{{ $report->manual_periode_type ?? '' }}"
                                        data-manual-periode-label="{{ $report->manual_periode_label ?? '' }}"
                                        data-manual-periode-help="{{ $report->manual_periode_help ?? '' }}">
                                    {{ $report->nama_report }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="form-periode" class="form-group mb-4" style="display: none;">
                        <label id="periode-label" class="font-weight-bold text-dark mb-2">Periode</label>
                        <input type="date" id="periode_input" name="periode" class="form-control">
                        <small id="periode-help" class="d-none"></small>
                    </div>

                    <div id="form-kanca" class="form-group mb-0" style="display: none;">
                        <label id="kanca-label" class="font-weight-bold text-dark mb-2">Kanca</label>
                        <select id="kanca_input" name="kanca_manual" class="form-control">
                            <option value="">-- Pilih Kanca --</option>
                            <option value="KC Madiun">KC Madiun</option>
                            <option value="KC Magetan">KC Magetan</option>
                            <option value="KC Ngawi">KC Ngawi</option>
                            <option value="KC Ponorogo">KC Ponorogo</option>
                        </select>
                        <small id="kanca-help" class="d-none"></small>
                    </div>

                    <div id="form-business-cluster-link" class="form-group mb-0 mt-4" style="display: none;">
                        <label id="business-cluster-link-label" class="font-weight-bold text-dark mb-2">Link URL Spreadsheet</label>
                        <input type="url" id="business_cluster_link_url" name="link_url" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                        <small id="business-cluster-link-help" class="text-muted">Tempel link spreadsheet Business Cluster yang sudah bisa diakses.</small>
                    </div>
                </section>

                <section class="import-workflow__section import-workflow__section--file">
                    <div class="import-workflow__heading">
                        <span class="import-workflow__step">2</span>
                        <div>
                            <h6>Pilih file</h6>
                            <p>Format file akan mengikuti report yang dipilih.</p>
                        </div>
                    </div>
                    <div id="form-rar" class="form-group mb-3 d-none">
                        <input type="file" id="file_rar" name="file" class="custom-file-input" accept=".rar" required>
                    </div>
                    <div id="form-excel" class="form-group mb-3" style="display: none;">
                        <label id="excel-label" class="d-none"></label>
                        <input type="file" id="file_excel" name="file" class="form-control d-none" accept=".xlsx,.xls">
                        <small id="excel-help" class="d-none"></small>
                        <small id="upload-limit-hint" class="d-none"></small>
                    </div>
                    <div id="form-csv" class="form-group mb-3" style="display: none;">
                        <label id="csv-label" class="d-none"></label>
                        <input type="file" id="file_csv" name="file" class="form-control d-none" accept=".csv,.txt">
                        <small id="csv-help" class="d-none"></small>
                    </div>

                    <div id="import-dropzone" class="import-dropzone d-flex flex-column align-items-center justify-content-center" tabindex="0" role="button" aria-label="Area upload file">
                        <div class="import-dropzone__icon mb-3">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="import-dropzone__content text-center">
                            <div id="import-dropzone-title" class="import-dropzone__title">Pilih file atau tarik ke sini</div>
                            <div id="import-dropzone-text" class="import-dropzone__text">File otomatis disesuaikan dengan report</div>
                            <div class="import-dropzone__hint">
                                <span><i class="fas fa-file-archive mr-1"></i> RAR</span>
                                <span><i class="fas fa-file-excel mr-1"></i> Excel</span>
                                <span><i class="fas fa-file-csv mr-1"></i> CSV/TXT</span>
                            </div>
                        </div>
                    </div>

                    <div id="import-file-preview" class="import-file-preview d-none align-items-center mt-3">
                        <div class="import-file-preview__icon">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div class="import-file-preview__body">
                            <div id="import-file-name" class="import-file-preview__name">-</div>
                            <div class="import-file-preview__meta text-muted">
                                <span id="import-file-size" class="font-weight-bold text-secondary">0 KB</span>
                                <span id="import-file-extension" class="badge badge-light border text-uppercase">-</span>
                            </div>
                        </div>
                        <button type="button" id="import-file-clear" class="import-file-preview__clear ml-3" title="Ganti File">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <div class="card-footer bg-transparent border-0 import-upload-card__footer">
            <div class="import-workflow__footer-note">
                <span class="import-workflow__step">3</span>
                <span>Periksa data pada halaman preview sebelum menjalankan import.</span>
            </div>
            <button type="submit" id="btn-submit" class="btn btn-primary font-weight-bold import-upload-card__submit">
                <i class="fas fa-arrow-right mr-2"></i> Lanjut ke Preview
            </button>
        </div>
    </form>
    </div>
</div>

@if(!empty($showReportManagementPanel))
<style>
    .rm-panel { border-radius: 26px; overflow: hidden; box-shadow: 0 28px 60px -40px rgba(15,23,42,0.32); border: 1px solid rgba(15, 76, 186, 0.12); background: #fff; margin-top: 2rem; margin-bottom: 2rem; }
    .rm-header { padding: 1.45rem 1.5rem 1rem; border-bottom: 1px solid rgba(15, 76, 186, 0.1); background: radial-gradient(circle at top left, rgba(15, 76, 186, 0.08), transparent 28%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); }
    .rm-eyebrow { display: inline-block; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #0f4cba; background: rgba(15, 76, 186, 0.08); margin-bottom: 0.55rem; }
    .rm-card-inner { padding: 1.75rem; border-radius: 20px; background: #ffffff; border: 1px solid rgba(148, 163, 184, 0.2); height: 100%; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .rm-card-inner:hover { box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
    .rm-card-inner--sync { justify-content: space-between; }
    .rm-card-eyebrow { display: inline-flex; align-items: center; padding: 0.45rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 1.25rem; align-self: flex-start; }
    .rm-card-eyebrow--source { background: rgba(15, 23, 42, 0.04); color: #475569; }
    .rm-card-eyebrow--sync { background: rgba(15, 76, 186, 0.08); color: #0f4cba; }
    .rm-stat-card { padding: 1.5rem; border-radius: 20px; background: #fff; border: 1px solid rgba(148, 163, 184, 0.2); display: flex; align-items: center; gap: 1rem; height: 100%; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .rm-stat-card:hover { box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
    .rm-stat-icon { width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
    .rm-action-bar { padding: 1.25rem 1.5rem; border-radius: 20px; background: #ffffff; border: 1px solid rgba(148, 163, 184, 0.2); display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); }
    .rm-btn { min-height: 48px; border-radius: 16px; padding: 0 1.75rem; font-weight: 700; font-size: 0.95rem; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
    .rm-btn-primary { background: linear-gradient(135deg, #0f4cba, #2563eb); color: #fff; border: none; box-shadow: 0 14px 24px -14px rgba(15, 76, 186, 0.5); }
    .rm-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 18px 28px -14px rgba(15, 76, 186, 0.6); color: #fff; }
    .rm-btn-danger-outline { border: 2px solid #ef4444; color: #ef4444; background: transparent; }
    .rm-btn-danger-outline:hover { background: #fef2f2; color: #dc2626; transform: translateY(-1px); }
    .rm-btn-secondary-outline { border: 2px solid #0f4cba; color: #0f4cba; background: transparent; width: 100%; margin-top: 1rem; }
    .rm-btn-secondary-outline:hover { background: rgba(15, 76, 186, 0.05); color: #0f4cba; transform: translateY(-1px); }
    .rm-progress { padding: 1.5rem; border-radius: 20px; background: #ffffff; border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); }
    .rm-progress-badge { display: inline-flex; padding: 0.35rem 0.85rem; border-radius: 999px; background: #dcfce7; color: #059669; font-size: 0.8rem; font-weight: 800; border: 1px solid #a7f3d0; text-transform: uppercase; letter-spacing: 0.05em; }
    .rm-progress-bar { height: 12px; border-radius: 999px; background: #e2e8f0; margin-bottom: 0.75rem; overflow: hidden; box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08); }
    .rm-progress-fill { height: 100%; background: linear-gradient(90deg, #10b981, #059669); width: 100%; transition: width 0.3s ease; }

    /* Queue Monitor Styles */
    .queue-monitor { margin-bottom: 2rem; border-radius: 24px; background: #fff; border: 1px solid rgba(226,232,240,0.8); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
    .queue-monitor__header { padding: 1rem 1.5rem; background: #f8fafc; border-bottom: 1px solid rgba(226,232,240,0.8); display: flex; align-items: center; justify-content: space-between; }
    .queue-monitor__title { font-size: 0.85rem; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem; }
    .queue-monitor__status { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.75rem; border-radius: 999px; }
    .queue-monitor__status--active { background: #dcfce7; color: #15803d; }
    .queue-monitor__status--warning { background: #fef9c3; color: #a16207; }
    .queue-monitor__status--danger { background: #fee2e2; color: #b91c1c; }
    .queue-monitor__body { padding: 1.25rem 1.5rem; display: flex; gap: 1.5rem; flex-wrap: wrap; }
    .queue-item { flex: 1; min-width: 200px; display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 16px; background: #f1f5f9; border: 1px solid rgba(226,232,240,0.5); }
    .queue-item__icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .queue-item__info { display: flex; flex-direction: column; }
    .queue-item__label { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 0.15rem; }
    .queue-item__value { font-size: 1.1rem; font-weight: 800; color: #0f172a; line-height: 1; }
    .queue-item--high-load { background: #fff1f2; border-color: #fecaca; }
    .queue-item--high-load .queue-item__icon { color: #e11d48; }

    .pulse { width: 8px; height: 8px; background: currentColor; border-radius: 50%; display: inline-block; position: relative; }
    .pulse::after { content: ''; width: 100%; height: 100%; background: currentColor; border-radius: 50%; position: absolute; top: 0; left: 0; animation: pulse-anim 2s infinite; opacity: 0.5; }
    @keyframes pulse-anim { 0% { transform: scale(1); opacity: 0.5; } 100% { transform: scale(2.5); opacity: 0; } }

    /* Detailed Job Tables */
    .queue-monitor__nav { display: flex; padding: 0.5rem 1.5rem; background: #fdfdfe; border-bottom: 1px solid rgba(226,232,240,0.8); gap: 1rem; }
    .queue-nav-item { padding: 0.4rem 0.8rem; font-size: 0.75rem; font-weight: 700; color: #64748b; cursor: pointer; border-radius: 8px; transition: 0.2s; }
    .queue-nav-item:hover { background: #f1f5f9; color: #1e293b; }
    .queue-nav-item.active { background: #eff6ff; color: #0f4cba; }

    .queue-table-container { display: none; padding: 1rem 1.5rem; max-height: 400px; overflow-y: auto; }
    .queue-table-container.active { display: block; }
    .queue-table { width: 100%; font-size: 0.75rem; border-collapse: collapse; }
    .queue-table th { text-align: left; padding: 0.6rem; color: #64748b; font-weight: 700; border-bottom: 1px solid #f1f5f9; text-transform: uppercase; letter-spacing: 0.025em; }
    .queue-table td { padding: 0.6rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .job-badge { padding: 0.15rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.65rem; }
    .job-badge--waiting { background: #f1f5f9; color: #475569; }
    .job-badge--processing { background: #dcfce7; color: #166534; }
    .job-badge--queue { background: #fef9c3; color: #854d0e; }
    .error-text { font-family: monospace; font-size: 0.7rem; color: #e11d48; display: block; max-width: 400px; white-space: pre-wrap; word-break: break-all; }
</style>

<div id="queue-monitor-container" data-status-url="{{ route('import.queue-status') }}"></div>

<div class="card border-0 mb-4 shadow-sm mt-4" id="report-management-card" style="border-radius: 20px; background: #ffffff; overflow: hidden;"
     data-fetch-url="{{ route('import.report-management.data') }}"
     data-delete-url="{{ route('import.report-management.delete') }}">
    <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
        <h5 class="font-weight-bold text-dark mb-0" style="font-size: 1.25rem;">
            <i class="fas fa-database text-danger mr-2"></i> Kelola Data Report
        </h5>
    </div>
    
    <div class="card-body p-4">
        <div class="row align-items-end">
            <!-- Pilihan Report -->
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="form-group mb-0">
                    <label class="font-weight-bold text-dark mb-2" for="management-report-select" style="font-size: 0.95rem;">Pilih Report</label>
                    <select id="management-report-select" class="form-control select2" style="border-radius: 12px;">
                        <option value="">-- Pilih Report --</option>
                        @foreach($reports as $report)
                            <option value="{{ $report->id_report }}" @if(strpos(strtolower($report->nama_report), 'simpanan multipn') !== false) selected @endif>{{ $report->nama_report }} ({{ $report->table_name }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Sinkronisasi Snapshot -->
            <div class="col-lg-4 pl-lg-4">
                <div class="d-flex flex-column h-100 justify-content-end p-3" style="background: #f8fafc; border-radius: 16px; border: 1px solid #f1f5f9;">
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="management-rebuild-force">
                        <label class="custom-control-label font-weight-bold text-dark" for="management-rebuild-force" style="cursor: pointer; padding-top: 2px;">Mode Sinkronisasi Penuh</label>
                    </div>
                    <button type="button" id="btn-management-rebuild" class="btn btn-outline-primary btn-block" style="border-radius: 12px; font-weight: 600;">
                        <i class="fas fa-sync-alt mr-2"></i> Refresh Snapshot
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-4 pt-3" style="gap: 12px;">
            <button type="button" id="btn-management-filter" class="btn btn-primary" style="border-radius: 12px; font-weight: 600; padding: 0.6rem 1.5rem;">
                <i class="fas fa-filter mr-2"></i> Tampilkan Data
            </button>
            <button type="button" id="btn-management-deduplicate" class="btn btn-outline-danger" style="border-radius: 12px; font-weight: 600; padding: 0.6rem 1.5rem;">
                <i class="fas fa-clone mr-2"></i> Hapus Duplikat
            </button>
        </div>

        <!-- Hidden but existing elements so JS logic does not crash. Dummy elements removed safely. -->
        <div class="table-responsive mt-4" style="border-radius: 12px; border: 1px solid #e2e8f0;">
            <table class="table table-hover mb-0" style="overflow: hidden; border-collapse: collapse;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="width: 25%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Periode</th>
                        <th style="width: 35%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Kanca</th>
                        <th style="width: 20%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;" class="text-right">Jumlah Baris</th>
                        <th style="width: 20%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="management-table-body">
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Pilih report lalu klik Tampilkan Data.</td>
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

        function isDuplicateImportMessage(message) {
            const text = String(message || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .toLowerCase();
            return text.includes('duplikat')
                || text.includes('sudah ada di database')
                || text.includes('data ditolak (duplikat)')
                || text.includes('duplicate entry');
        }

        function redirectToImportIndex() {
            if (typeof window.showRouteLoading === 'function') {
                window.showRouteLoading('Memuat halaman', 'Menyiapkan tampilan berikutnya dengan data terbaru.');
            }
            window.location.href = "{{ route('import.index') }}";
        }

        async function showDuplicateImportPopup(message, title = 'Data Duplikat') {
            await themedSwal({
                icon: 'warning',
                title: title,
                html: message || 'Data duplikat terdeteksi.',
                confirmButtonText: 'Kembali ke Import',
            });
            redirectToImportIndex();
        }

        function showDownloadToast(icon, title, text) {
            const stack = document.getElementById('download-toast-stack');
            if (!stack) {
                return;
            }

            const variant = icon === 'error' ? 'error' : 'success';
            const toast = document.createElement('div');
            toast.className = `download-toast download-toast--${variant}`;

            const iconWrap = document.createElement('div');
            iconWrap.className = 'download-toast__icon';
            const iconNode = document.createElement('i');
            iconNode.className = `fas ${variant === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}`;
            iconWrap.appendChild(iconNode);

            const body = document.createElement('div');
            body.className = 'download-toast__body';

            const titleNode = document.createElement('div');
            titleNode.className = 'download-toast__title';
            titleNode.textContent = title;

            const textNode = document.createElement('div');
            textNode.className = 'download-toast__text';
            textNode.textContent = text;

            body.appendChild(titleNode);
            body.appendChild(textNode);

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'download-toast__close';
            closeButton.setAttribute('aria-label', 'Tutup');
            closeButton.textContent = '×';

            toast.appendChild(iconWrap);
            toast.appendChild(body);
            toast.appendChild(closeButton);

            const closeToast = () => {
                toast.classList.add('is-hiding');
                window.setTimeout(() => toast.remove(), 220);
            };

            closeButton.addEventListener('click', closeToast);
            stack.appendChild(toast);

            window.setTimeout(closeToast, 3200);
        }

        function normalizeProgressStatus(message) {
            const text = String(message || '').trim();
            const speedMatch = text.match(/\(([\d.,]+)\s+baris\/detik\)$/i);

            if (speedMatch) {
                return {
                    message: text,
                    speed: speedMatch[1].replace(/[^\d]/g, ''),
                    speedLabel: 'baris/detik',
                };
            }

            return {
                message: text,
                speed: '',
                speedLabel: '',
            };
        }

        const reportSelect = document.querySelector('select[name="id_report"]');
        const formRAR = document.getElementById('form-rar');
        const formExcel = document.getElementById('form-excel');
        const formCsv = document.getElementById('form-csv');
        const formPeriode = document.getElementById('form-periode');
        const formKanca = document.getElementById('form-kanca');
        const formBusinessClusterLink = document.getElementById('form-business-cluster-link');
        const formImport = document.getElementById('form-import');
        const btnSubmit = document.getElementById('btn-submit');
        const btnDownloadTemplate = document.getElementById('btn-download-template');
        const downloadTemplateSelect = document.getElementById('download-template-select');
        const inputRar = document.getElementById('file_rar');
        const inputExcel = document.getElementById('file_excel');
        const inputCsv = document.getElementById('file_csv');
        const periodeInput = document.getElementById('periode_input');
        const kancaInput = document.getElementById('kanca_input');
        const businessClusterLinkInput = document.getElementById('business_cluster_link_url');
        const periodeLabel = document.getElementById('periode-label');
        const periodeHelp = document.getElementById('periode-help');
        const kancaLabel = document.getElementById('kanca-label');
        const kancaHelp = document.getElementById('kanca-help');
        const businessClusterLinkHelp = document.getElementById('business-cluster-link-help');
        const excelLabel = document.getElementById('excel-label');
        const excelHelp = document.getElementById('excel-help');
        const csvLabel = document.getElementById('csv-label');
        const csvHelp = document.getElementById('csv-help');
        const importDropzone = document.getElementById('import-dropzone');
        const importDropzoneTitle = document.getElementById('import-dropzone-title');
        const importDropzoneText = document.getElementById('import-dropzone-text');
        const importFilePreview = document.getElementById('import-file-preview');
        const importFileName = document.getElementById('import-file-name');
        const importFileSize = document.getElementById('import-file-size');
        const importFileExtension = document.getElementById('import-file-extension');
        const importFileClear = document.getElementById('import-file-clear');
        const summaryReportName = document.getElementById('summary-report-name');
        const summaryUploadType = document.getElementById('summary-upload-type');
        const summaryPeriodeStatus = document.getElementById('summary-periode-status');
        const summaryTargetTable = document.getElementById('summary-target-table');
        const csrfTokenInput = formImport?.querySelector('input[name="_token"]');
        const reportManagementCard = document.getElementById('report-management-card');
        const managementReportSelect = document.getElementById('management-report-select');
        const btnManagementFilter = document.getElementById('btn-management-filter');
        const managementTableBody = document.getElementById('management-table-body');
        let uploadLimitsPromise = null;
        let activeUploadLimits = null;

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
                    activeUploadLimits = null;
                    applyUploadLimitHints(null);
                    return null;
                }
                activeUploadLimits = payload;
                applyUploadLimitHints(payload);
                return payload;
            })
            .catch(() => {
                activeUploadLimits = null;
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
            const chunkedUploadEnabled = formImport?.dataset.chunkedUpload === '1';
            if (maxBytes > 0 && file.size > maxBytes && !chunkedUploadEnabled) {
                const limitLabel = formatBytes(maxBytes);

                themedSwal({
                    icon: 'error',
                    title: 'Ukuran File Terlalu Besar',
                    html: `Ukuran file <b>${escapeHtml(file.name)}</b> adalah <b>${formatBytes(file.size)}</b>.<br>Batas upload server saat ini <b>${limitLabel}</b>.<br><small>Upload belum dijalankan agar koneksi tidak ditolak oleh server.</small>`
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
            initForm.append('total_chunks', String(totalChunks));

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
                if (isDuplicateImportMessage(initPayload.message || initPayload.text || initPayload.title)) {
                    await showDuplicateImportPopup(initPayload.text || initPayload.message || initPayload.title || 'Data duplikat terdeteksi.');
                    return;
                }
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
                    if (isDuplicateImportMessage(chunkPayload.message || chunkPayload.text || chunkPayload.title)) {
                        await showDuplicateImportPopup(chunkPayload.text || chunkPayload.message || chunkPayload.title || 'Data duplikat terdeteksi.', chunkPayload.title || 'Data Duplikat');
                        return;
                    }
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
                if (isDuplicateImportMessage(finalizePayload.message || finalizePayload.text || finalizePayload.title)) {
                    await showDuplicateImportPopup(finalizePayload.text || finalizePayload.message || finalizePayload.title || 'Data duplikat terdeteksi.');
                    return;
                }
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
                if (String(finalizePayload.redirect).includes('prepare-preview')) {
                    await new Promise((resolve, reject) => {
                        const eventSource = new EventSource(finalizePayload.redirect);
                        let settled = false;

                        const failPreview = (message) => {
                            if (settled) {
                                return;
                            }
                            settled = true;
                            eventSource.close();
                            reject(new Error(message || 'Gagal menyiapkan preview file.'));
                        };

                        eventSource.addEventListener('progress', function(event) {
                            let progressData = {};
                            try { progressData = JSON.parse(event.data); } catch (_) {}

                            const previewPercent = Number(progressData.percent);
                            const percent = Number.isFinite(previewPercent)
                                ? Math.max(96, Math.min(99, 96 + Math.round((previewPercent / 100) * 3)))
                                : 96;

                            if (uploadProgressBar) {
                                uploadProgressBar.style.width = percent + '%';
                                uploadProgressBar.innerText = percent + '%';
                            }
                            const progressPercent = document.getElementById('swal-progress-percent');
                            if (progressPercent) {
                                progressPercent.textContent = percent + '%';
                            }
                            if (uploadProgressText) {
                                uploadProgressText.innerText = progressData.message || 'Menyiapkan preview file...';
                            }
                        });

                        eventSource.addEventListener('ready', function(event) {
                            let readyData = {};
                            try { readyData = JSON.parse(event.data); } catch (_) {}
                            if (!readyData.redirect) {
                                failPreview('Server tidak mengembalikan alamat halaman preview.');
                                return;
                            }

                            settled = true;
                            eventSource.close();
                            if (uploadProgressBar) {
                                uploadProgressBar.style.width = '100%';
                                uploadProgressBar.innerText = '100%';
                            }
                            if (typeof window.showRouteLoading === 'function') {
                                window.showRouteLoading('Memuat halaman', 'Menyiapkan preview data terbaru.');
                            }
                            window.location.href = readyData.redirect;
                            resolve();
                        });

                        eventSource.addEventListener('error_msg', function(event) {
                            let errorData = {};
                            try { errorData = JSON.parse(event.data); } catch (_) {}
                            failPreview(errorData.message || 'Gagal menyiapkan preview file.');
                        });

                        eventSource.onerror = function() {
                            failPreview('Koneksi progress preview terputus.');
                        };
                    });
                    return;
                }

                if (typeof window.showRouteLoading === 'function') {
                    window.showRouteLoading('Memuat halaman', 'Menyiapkan preview data terbaru.');
                }
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

            // Function to fetch and render queue status
            function updateQueueMonitor() {
                const container = document.getElementById('queue-monitor-container');
                if (!container) return;

                fetch(container.dataset.statusUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') return;

                        const isWorkerActive = data.worker_status.is_active;
                        const hasJobs = data.queues.default.count > 0 || data.queues['imports-high'].count > 0;
                        
                        let statusClass = 'queue-monitor__status--active';
                        let statusText = 'Worker Berjalan';
                        let dotPulse = 'pulse';

                        if (!isWorkerActive && hasJobs) {
                            statusClass = 'queue-monitor__status--danger';
                            statusText = 'Worker Berhenti (Backlog!)';
                        } else if (data.failed_jobs_count > 0) {
                            statusClass = 'queue-monitor__status--warning';
                            statusText = 'Worker Aktif (Ada Error)';
                        } else if (!isWorkerActive) {
                            statusClass = 'queue-monitor__status--warning';
                            statusText = 'Worker Idle/Off';
                            dotPulse = '';
                        }

                        const defaultLoad = data.queues.default.count >= 20 ? 'queue-item--high-load' : '';
                        const highLoad = data.queues['imports-high'].count >= 5 ? 'queue-item--high-load' : '';

                        // Preserve active tab
                        const activeTab = document.querySelector('.queue-nav-item.active')?.dataset.tab || 'summary';

                        container.innerHTML = `
                            <div class="queue-monitor">
                                <div class="queue-monitor__header">
                                    <div class="queue-monitor__title">
                                        <i class="fas fa-microchip"></i> Monitoring Antrean Job
                                    </div>
                                    <div class="queue-monitor__status ${statusClass}">
                                        <span class="${dotPulse}"></span> ${statusText}
                                    </div>
                                </div>
                                <div class="queue-monitor__nav">
                                    <div class="queue-nav-item ${activeTab === 'summary' ? 'active' : ''}" data-tab="summary">Ringkasan</div>
                                    <div class="queue-nav-item ${activeTab === 'list' ? 'active' : ''}" data-tab="list">Daftar Antrean (${data.recent_jobs.length})</div>
                                    <div class="queue-nav-item ${activeTab === 'failed' ? 'active' : ''}" data-tab="failed">Job Gagal (${data.failed_jobs_count})</div>
                                </div>
                                
                                <div class="queue-table-container ${activeTab === 'summary' ? 'active' : ''}" id="tab-summary">
                                    <div class="queue-monitor__body" style="padding:0">
                                        <div class="queue-item ${defaultLoad}">
                                            <div class="queue-item__icon"><i class="fas fa-file-import"></i></div>
                                            <div class="queue-item__info">
                                                <span class="queue-item__label">${data.queues.default.label}</span>
                                                <span class="queue-item__value">${data.queues.default.count} <small class="text-muted font-weight-normal">menunggu</small></span>
                                            </div>
                                        </div>
                                        <div class="queue-item ${highLoad}">
                                            <div class="queue-item__icon"><i class="fas fa-eraser"></i></div>
                                            <div class="queue-item__info">
                                                <span class="queue-item__label">${data.queues['imports-high'].label}</span>
                                                <span class="queue-item__value">${data.queues['imports-high'].count} <small class="text-muted font-weight-normal">menunggu</small></span>
                                            </div>
                                        </div>
                                        <div class="queue-item ${data.failed_jobs_count > 0 ? 'queue-item--high-load' : ''}">
                                            <div class="queue-item__icon"><i class="fas fa-exclamation-triangle"></i></div>
                                            <div class="queue-item__info">
                                                <span class="queue-item__label">Failed Jobs</span>
                                                <span class="queue-item__value">${data.failed_jobs_count} <small class="text-muted font-weight-normal">error</small></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="queue-table-container ${activeTab === 'list' ? 'active' : ''}" id="tab-list">
                                    <table class="queue-table text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nama Job</th>
                                                <th>Queue</th>
                                                <th>Status</th>
                                                <th>Jam</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${data.recent_jobs.length === 0 ? '<tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada job di antrean</td></tr>' : ''}
                                            ${data.recent_jobs.map(job => `
                                                <tr>
                                                    <td>#${job.id}</td>
                                                    <td class="font-weight-bold">${job.name}</td>
                                                    <td><span class="job-badge job-badge--queue">${job.queue}</span></td>
                                                    <td><span class="job-badge ${job.status === 'Processing' ? 'job-badge--processing' : 'job-badge--waiting'}">${job.status}</span></td>
                                                    <td>${job.created_at}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>

                                <div class="queue-table-container ${activeTab === 'failed' ? 'active' : ''}" id="tab-failed">
                                    <table class="queue-table">
                                        <thead>
                                            <tr>
                                                <th>Job / Error</th>
                                                <th class="text-right">Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${data.detailed_failed_jobs.length === 0 ? '<tr><td colspan="2" class="text-center py-4 text-muted">Tidak ada job gagal</td></tr>' : ''}
                                            ${data.detailed_failed_jobs.map(f => `
                                                <tr>
                                                    <td>
                                                        <div class="font-weight-bold mb-1">${f.name} <span class="badge badge-light border ml-1">${f.queue}</span></div>
                                                        <code class="error-text">${f.error}...</code>
                                                    </td>
                                                    <td class="text-right text-muted" style="white-space:nowrap">${f.failed_at}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;

                        // Reattach tab listeners
                        document.querySelectorAll('.queue-nav-item').forEach(nav => {
                            nav.addEventListener('click', function() {
                                document.querySelectorAll('.queue-nav-item').forEach(n => n.classList.remove('active'));
                                document.querySelectorAll('.queue-table-container').forEach(c => c.classList.remove('active'));
                                this.classList.add('active');
                                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
                            });
                        });
                    })
                    .catch(err => console.error('Gagal memuat status queue:', err));
            }

            updateQueueMonitor();
            // Poll every 10 seconds
            setInterval(updateQueueMonitor, 10000);

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

        function isSuccessLikeDeleteMessage(message) {
            const normalized = String(message || '').trim().toLowerCase();
            if (!normalized) return false;
            return normalized.startsWith('delete selesai.')
                || normalized.startsWith('delete sumber selesai')
                || normalized.includes('statistik dan cache sudah disegarkan')
                || normalized.includes('report ini tidak menggunakan snapshot/index');
        }

        function normalizeDeleteResponse(payload) {
            if (!payload || typeof payload !== 'object') {
                return payload;
            }

            const normalized = Object.assign({}, payload);
            const deletedRows = Number(normalized.deleted_rows || 0);

            if (normalized.status === 'failed' && isSuccessLikeDeleteMessage(normalized.message)) {
                normalized.status = deletedRows > 0 ? 'warning' : 'completed';
                if (!normalized.stage || normalized.stage === 'failed') {
                    normalized.stage = 'completed';
                }
                normalized.progress_percent = 100;
            }

            return normalized;
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

            const payload = normalizeDeleteResponse(await response.json());
            const deletedRows = Number(payload.deleted_rows || 0);
            const recoveredWarning = payload.status === 'failed' && deletedRows > 0;
            const outcomeStatus = recoveredWarning ? 'warning' : payload.status;
            const successLikeDelete = isSuccessLikeDeleteMessage(payload.message) || ['completed', 'warning'].includes(outcomeStatus);
            if ((!response.ok && !recoveredWarning && !successLikeDelete) || !['success', 'warning', 'completed'].includes(outcomeStatus)) {
                throw new Error(payload.message || 'Gagal menghapus data report.');
            }

            const isWarning = outcomeStatus === 'warning';
            await themedSwal({
                icon: isWarning ? 'warning' : 'success',
                title: isWarning ? 'Selesai dengan Catatan' : 'Berhasil',
                text: isWarning
                    ? (payload.error || payload.message || `Data terhapus ${deletedRows.toLocaleString('id-ID')} baris, tetapi sinkronisasi lanjutan gagal.`)
                    : (payload.message || `Data terhapus ${deletedRows.toLocaleString('id-ID')} baris.`)
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
                btnDownloadTemplate.classList.remove('disabled');
                btnDownloadTemplate.removeAttribute('aria-disabled');
                return;
            }

            btnDownloadTemplate.classList.add('disabled');
            btnDownloadTemplate.setAttribute('aria-disabled', 'true');
        }

        function buildTemplateDownloadUrl(templateKey, filename, directDownload = false) {
            const params = new URLSearchParams();
            params.set('report', templateKey);

            if (filename) {
                params.set('file', filename);
            }

            if (directDownload) {
                params.set('download', '1');
            }

            return `${btnDownloadTemplate.dataset.routeTemplate}?${params.toString()}`;
        }

        function getSelectedTemplateMeta() {
            if (!downloadTemplateSelect) {
                return {
                    templateKey: '',
                    label: '',
                    filename: '',
                };
            }

            const templateKey = downloadTemplateSelect.value || '';
            const selectedOption = downloadTemplateSelect.options[downloadTemplateSelect.selectedIndex];

            return {
                templateKey,
                label: selectedOption ? selectedOption.textContent.trim() : '',
                filename: selectedOption ? (selectedOption.getAttribute('data-filename') || '') : '',
            };
        }

        function setDownloadTemplateBusy(isBusy) {
            if (!btnDownloadTemplate) {
                return;
            }

            if (isBusy) {
                if (!btnDownloadTemplate.dataset.defaultLabel) {
                    btnDownloadTemplate.dataset.defaultLabel = btnDownloadTemplate.innerHTML;
                }
                btnDownloadTemplate.classList.add('disabled');
                btnDownloadTemplate.setAttribute('aria-disabled', 'true');
                btnDownloadTemplate.setAttribute('aria-busy', 'true');
                btnDownloadTemplate.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Mengunduh...';
                return;
            }

            btnDownloadTemplate.removeAttribute('aria-busy');
            if (btnDownloadTemplate.dataset.defaultLabel) {
                btnDownloadTemplate.innerHTML = btnDownloadTemplate.dataset.defaultLabel;
            }
            syncDownloadButton();
        }

        async function downloadSelectedTemplate() {
            const meta = getSelectedTemplateMeta();

            if (!meta.templateKey) {
                return;
            }

            const requestUrl = buildTemplateDownloadUrl(meta.templateKey, meta.filename, false);
            setDownloadTemplateBusy(true);

            try {
                const response = await fetch(requestUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const responseType = response.headers.get('content-type') || '';
                if (!response.ok) {
                    throw new Error('Template tidak dapat diproses.');
                }

                if (!responseType.includes('application/json')) {
                    throw new Error('Respon server tidak valid saat menyiapkan template.');
                }

                const payload = await response.json();

                if (payload.status !== 'success' || !payload.download_url) {
                    throw new Error(payload.message || 'Template tidak dapat diunduh.');
                }

                const fileResponse = await fetch(payload.download_url, {
                    method: 'GET',
                    credentials: 'same-origin',
                });

                const fileResponseType = fileResponse.headers.get('content-type') || '';
                if (!fileResponse.ok || fileResponseType.includes('text/html')) {
                    throw new Error('Gagal mengambil file template dari server.');
                }

                const blob = await fileResponse.blob();
                const blobUrl = window.URL.createObjectURL(blob);
                const tempLink = document.createElement('a');
                tempLink.href = blobUrl;
                tempLink.download = payload.filename || meta.filename || 'template.xlsx';
                document.body.appendChild(tempLink);
                tempLink.click();
                tempLink.remove();
                setTimeout(() => window.URL.revokeObjectURL(blobUrl), 1000);

                showDownloadToast('success', 'Berhasil Diunduh', `Template ${meta.label || meta.templateKey} berhasil diunduh.`);
            } catch (error) {
                showDownloadToast('error', 'Gagal Mengunduh', error?.message || 'Template gagal diunduh.');
            } finally {
                setDownloadTemplateBusy(false);
            }
        }

        if (btnDownloadTemplate) {
            btnDownloadTemplate.addEventListener('click', function (event) {
                if (btnDownloadTemplate.classList.contains('disabled') || btnDownloadTemplate.getAttribute('aria-disabled') === 'true') {
                    event.preventDefault();
                    return;
                }

                event.preventDefault();
                downloadSelectedTemplate();
            });
        }

        function applyButtonState(kind, label) {
            const buttonClasses = {
                rar: 'btn btn-primary font-weight-bold import-upload-card__submit',
                excel: 'btn btn-success font-weight-bold import-upload-card__submit',
                csv: 'btn btn-info font-weight-bold import-upload-card__submit',
                cras: 'btn btn-primary font-weight-bold import-upload-card__submit',
                link: 'btn btn-primary font-weight-bold import-upload-card__submit',
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
                importController: selectedOption?.getAttribute('data-import-controller') || '',
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

        function configureKancaInput(options = {}) {
            const {
                visible = false,
                required = false,
                label = 'Kanca',
                help = 'Pilih kanca untuk import.',
                value = '',
            } = options;

            if (!formKanca || !kancaInput) {
                return;
            }

            formKanca.style.display = visible ? 'block' : 'none';
            kancaInput.disabled = !visible;
            kancaInput.required = Boolean(visible && required);
            kancaInput.value = value;

            if (kancaLabel) {
                kancaLabel.textContent = label;
            }

            if (kancaHelp) {
                kancaHelp.textContent = help;
            }
        }

        function configureBusinessClusterLink(options = {}) {
            const {
                visible = false,
                required = false,
                help = 'Tempel link spreadsheet Business Cluster yang sudah bisa diakses.',
                value = '',
            } = options;

            if (!formBusinessClusterLink || !businessClusterLinkInput) {
                return;
            }

            formBusinessClusterLink.style.display = visible ? 'block' : 'none';
            businessClusterLinkInput.disabled = !visible;
            businessClusterLinkInput.required = Boolean(visible && required);
            businessClusterLinkInput.value = value;

            if (businessClusterLinkHelp) {
                businessClusterLinkHelp.textContent = help;
            }
        }

        function getFileExtension(fileName) {
            const parts = String(fileName || '').toLowerCase().split('.');
            return parts.length > 1 ? parts.pop() : '';
        }

        function getActiveFileInput() {
            if (inputRar && !inputRar.disabled) {
                return inputRar;
            }

            if (inputExcel && !inputExcel.disabled) {
                return inputExcel;
            }

            if (inputCsv && !inputCsv.disabled) {
                return inputCsv;
            }

            return null;
        }

        function getActiveUploadDescriptor() {
            if (formImport.dataset.uploadKind === 'link') {
                return {
                    type: 'Link Spreadsheet',
                    accept: 'Google Sheets URL',
                };
            }

            const activeInput = getActiveFileInput();

            if (activeInput === inputExcel) {
                return {
                    type: 'Excel',
                    accept: activeInput.getAttribute('accept') || '.xlsx,.xls',
                };
            }

            if (activeInput === inputCsv) {
                return {
                    type: 'CSV',
                    accept: activeInput.getAttribute('accept') || '.csv,.txt',
                };
            }

            return {
                type: 'RAR',
                accept: activeInput?.getAttribute('accept') || '.rar',
            };
        }

        function updateDropzoneCopy() {
            const descriptor = getActiveUploadDescriptor();

            if (importDropzoneTitle) {
                importDropzoneTitle.textContent = `Tarik file ${descriptor.type} ke sini atau klik untuk memilih`;
            }

            if (importDropzoneText) {
                importDropzoneText.textContent = `Format aktif: ${descriptor.accept}. Area upload akan mengikuti report yang dipilih.`;
            }
        }

        function updateReportSummary() {
            const meta = getSelectedReportMeta();
            const selectedOption = reportSelect?.options?.[reportSelect.selectedIndex];
            const descriptor = getActiveUploadDescriptor();

            if (summaryReportName) {
                summaryReportName.textContent = reportSelect?.value
                    ? (selectedOption?.textContent?.trim() || 'Terpilih')
                    : 'Belum dipilih';
            }

            if (summaryUploadType) {
                summaryUploadType.textContent = descriptor.type;
            }

            if (summaryPeriodeStatus) {
                summaryPeriodeStatus.textContent = formPeriode && formPeriode.style.display !== 'none'
                    ? (periodeInput?.type === 'month' ? 'Manual Bulanan' : 'Manual Harian')
                    : (meta.requiresManualPeriode ? 'Manual' : 'Otomatis');
            }

            if (summaryTargetTable) {
                summaryTargetTable.textContent = meta.tableName ? meta.tableName.toUpperCase() : '-';
            }

            updateDropzoneCopy();
        }

        function updateFileSelectionUI() {
            const activeInput = getActiveFileInput();
            const file = activeInput?.files?.[0] || null;
            const extension = getFileExtension(file?.name || '');

            if (importDropzone) {
                importDropzone.classList.toggle('has-file', Boolean(file));
            }

            if (!file) {
                importFilePreview?.classList.add('d-none');
                return;
            }

            importFilePreview?.classList.remove('d-none');

            if (importFileName) {
                importFileName.textContent = file.name;
            }

            if (importFileSize) {
                importFileSize.textContent = formatBytes(file.size);
            }

            if (importFileExtension) {
                importFileExtension.textContent = extension ? extension.toUpperCase() : 'FILE';
            }
        }

        function assignFileToActiveInput(fileList) {
            const activeInput = getActiveFileInput();
            if (!activeInput || !fileList || !fileList.length) {
                return;
            }

            try {
                const transfer = new DataTransfer();
                transfer.items.add(fileList[0]);
                activeInput.files = transfer.files;
            } catch (_) {
                return;
            }

            if (activeInput === inputRar) {
                const customLabel = activeInput.nextElementSibling;
                if (customLabel?.classList.contains('custom-file-label')) {
                    customLabel.textContent = fileList[0].name;
                }
            }

            if (activeInput === inputExcel && isSimpananReportSelected()) {
                applySimpananUploadMode();
            }

            updateFileSelectionUI();
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
            formImport.dataset.chunkedUpload = isCsvLike ? '1' : '';
            formImport.dataset.chunkInitUrl = isCsvLike
                ? "{{ route('import.simpanan.csv.upload-chunk.init') }}"
                : '';
            formImport.dataset.chunkUploadUrl = isCsvLike
                ? "{{ route('import.simpanan.csv.upload-chunk') }}"
                : '';
            formImport.dataset.chunkFinalizeUrl = isCsvLike
                ? "{{ route('import.simpanan.csv.upload-chunk.finalize') }}"
                : '';

            applyButtonState(
                isCsvLike ? 'csv' : 'excel',
                isCsvLike
                    ? '<i class="fas fa-file-csv"></i> Upload CSV'
                    : '<i class="fas fa-file-excel"></i> Upload Excel'
            );
            updateReportSummary();
        }
        function toggleForm() {
            const meta = getSelectedReportMeta();
            const { 
                reportName, 
                tableName, 
                importController, 
                requiresManualPeriode, 
                manualPeriodeType, 
                manualPeriodeLabel, 
                manualPeriodeHelp 
            } = meta;
            const isDailyLoan = reportName.includes('daily loan');
            const isSimpanan = reportName.includes('simpanan multipn');
            const isPerformancePis = reportName.includes('performance pis per produk');
            const isCasaBrilink = reportName.includes('casa brilink');
            const isReportPh = reportName.includes('report nominatif rekening pinjaman ph');
            const isCognosPh = reportName.includes('cognos ph');
            const isCognosRecovery = reportName.includes('cognos recovery');
            const isGi405RecDh = tableName === 'gi405_recovery' || importController.includes('Gi405RecDhImportExcelController');
            const isSsaSimpanan = tableName === 'ssa_simpanan';
            const isSsaPinjaman = tableName === 'ssa_pinjaman';
            const isInputRekanan = tableName === 'input_rekanan';
            const isBodBoc = tableName === 'bod_boc';
            const isBusinessCluster = tableName === 'business_cluster';
            const isRka = tableName === 'rka';
            const isDlyKapResegmentasi = tableName === 'dly_kap_resegmentasi';
            const isL1133 = tableName === 'l1133';
            const isLw321Pn = tableName === 'lw321pn';
            const isCras = tableName === 'cras' || importController.includes('ImportCrasController');
            const isIbbiz = tableName === 'ibbisniz_corp' || tableName === 'usak_ibbiz_uker';
            const usesGenericExcelFlow = importController.includes('ImportExcelController') && !isDailyLoan && !isSimpanan;

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
            if (kancaInput) {
                kancaInput.disabled = true;
                kancaInput.required = false;
                kancaInput.name = 'kanca_manual';
            }
            configureKancaInput({ visible: false });
            configureBusinessClusterLink({ visible: false });
            if (importDropzone) {
                importDropzone.classList.remove('d-none');
            }

            formImport.dataset.preparePreviewUrl = '';
            formImport.dataset.directRedirect = '';
            formImport.dataset.chunkedUpload = '';
            formImport.dataset.chunkInitUrl = '';
            formImport.dataset.chunkUploadUrl = '';
            formImport.dataset.chunkFinalizeUrl = '';

            if (isBusinessCluster) {
                formImport.action = "{{ route('business-cluster.store') }}";
                formImport.dataset.preparePreviewUrl = '';
                formImport.dataset.directRedirect = '';

                if (kancaInput) {
                    kancaInput.name = 'nama_kanca';
                }

                configurePeriodeInput({ visible: false });
                configureKancaInput({
                    visible: true,
                    required: true,
                    label: 'Nama Kanca',
                    help: 'Pilih kanca pemilik link spreadsheet Business Cluster.',
                });
                configureBusinessClusterLink({
                    visible: true,
                    required: true,
                    help: 'Gunakan link spreadsheet Business Cluster yang sudah bisa diakses oleh aplikasi.',
                });

                if (importDropzone) {
                    importDropzone.classList.add('d-none');
                }

                importFilePreview?.classList.add('d-none');
                applyButtonState('link', '<i class="fas fa-link"></i> Simpan Link');
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isCras) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt,.xlsx');
                formImport.action = "{{ route('import.cras.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.cras.prepare-preview') }}";
                formImport.dataset.chunkedUpload = '1';
                formImport.dataset.chunkInitUrl = "{{ route('import.cras.upload-chunk.init') }}";
                formImport.dataset.chunkUploadUrl = "{{ route('import.cras.upload-chunk') }}";
                formImport.dataset.chunkFinalizeUrl = "{{ route('import.cras.upload-chunk.finalize') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-excel mr-1"></i> Upload File SSA CRAS (.csv, .txt, .xlsx)';
                csvHelp.textContent = 'CSV/TXT UTF-16LE dan XLSX diproses tanpa membulatkan atau menormalisasi nilai sumber. Periode dibaca dari file.';
                applyButtonState('cras', '<i class="fas fa-file-upload"></i> Upload CRAS');
                configurePeriodeInput({ visible: false });
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

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
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isSimpanan) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                inputExcel.setAttribute('accept', '.xlsx,.xls,.csv');
                configurePeriodeInput({ visible: false });
                applySimpananUploadMode();
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isGi405RecDh) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                inputExcel.setAttribute('accept', '.xlsx,.xls');
                formImport.action = "{{ route('import.gi405.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.gi405.prepare-preview') }}";

                if (excelLabel) {
                    excelLabel.innerHTML = '<i class="fas fa-file-excel mr-1"></i> Upload File Excel GI405 Recovery (.xlsx, .xls)';
                }

                if (excelHelp) {
                    excelHelp.textContent = 'File Excel akan divalidasi dulu agar kombinasi tanggal dan kode unit tidak duplikat.';
                }

                configurePeriodeInput({ visible: false, required: false, type: 'date', label: 'Periode', help: 'Periode dibaca dari kolom Tanggal pada file.' });
                configureKancaInput({ visible: false });
                applyButtonState('excel', '<i class="fas fa-file-excel"></i> Upload Excel');
                updateReportSummary();
                updateFileSelectionUI();
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
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
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
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
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
                csvHelp.textContent = 'File CSV atau Excel akan masuk ke flow khusus LW325 PH, termasuk staging Excel dan Polars Fastpath (LOAD DATA INFILE).';
                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput({ visible: false });
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isCognosRecovery) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt,.xlsx,.xls');
                formImport.action = "{{ route('import.cognos-recovery.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.cognos-recovery.prepare-preview') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-upload mr-1"></i> Upload File Cognos Recovery (.csv, .txt, .xlsx, .xls)';
                csvHelp.textContent = 'File CSV menjadi jalur utama. File Excel akan distage dulu ke CSV agar parser nominal dan rekening tetap aman.';
                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput({ visible: false });
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isCognosPh) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt,.xlsx,.xls');
                formImport.action = "{{ route('import.cognos-ph.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.cognos-ph.prepare-preview') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-upload mr-1"></i> Upload File Cognos PH (.csv, .txt, .xlsx, .xls)';
                csvHelp.textContent = 'File CSV menjadi jalur utama. File Excel akan distage dulu ke CSV agar parser ACCTNO dan nominal tetap aman.';
                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput({ visible: false });
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isPerformancePis) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt,.xlsx,.xls');
                formImport.action = "{{ route('import.performancepis.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.performancepis.prepare-preview') }}";
                csvLabel.innerHTML = '<i class="fas fa-file-upload mr-1"></i> Upload File Performance PIS (.csv, .txt, .xlsx, .xls)';
                csvHelp.textContent = 'File CSV dan Excel didukung. Tanggal periode wajib diisi manual di bawah.';
                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput(buildManualPeriodeOptions({
                    visible: true,
                    required: true,
                    type: 'date',
                    label: 'Tanggal Periode',
                    help: 'Wajib isi tanggal periode manual (YYYY-MM-DD) untuk Performance PIS per Produk.',
                }));
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isIbbiz) {
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt');
                formImport.action = "{{ route('import.upload') }}";
                formImport.dataset.preparePreviewUrl = '';
                csvLabel.innerHTML = '<i class="fas fa-file-csv mr-1"></i> Upload File IB Biz (.csv, .txt)';
                csvHelp.textContent = 'Pilih tanggal periode manual, lalu upload CSV IB Biz untuk preview dan import.';
                applyButtonState('csv', '<i class="fas fa-file-csv"></i> Upload CSV');
                configurePeriodeInput(buildManualPeriodeOptions({
                    visible: true,
                    required: true,
                    type: 'date',
                    label: 'Tanggal Periode',
                    help: 'Wajib isi tanggal periode manual (YYYY-MM-DD) untuk IB Biz.',
                }));
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isDlyKapResegmentasi || isL1133 || isLw321Pn) {
                const label = isDlyKapResegmentasi
                    ? 'DLY KAP Resegmentasi'
                    : (isLw321Pn ? 'LW321PN' : 'L1133');
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt,.xlsx,.xls');
                formImport.action = "{{ route('import.excel.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.excel.prepare-preview') }}";

                csvLabel.innerHTML = `<i class="fas fa-file-upload mr-1"></i> Upload File ${label} (.csv, .txt, .xlsx, .xls)`;
                csvHelp.textContent = isLw321Pn
                    ? 'Gunakan CSV LW321PN hasil optimasi untuk import tercepat. File Excel tetap didukung dan akan distage ke CSV.'
                    : `File CSV dan Excel didukung untuk ${label}. Format RAR tidak digunakan.`;

                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput({ visible: false });
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (isSsaPinjaman || isSsaSimpanan) {
                const label = isSsaPinjaman ? 'SSA Pinjaman' : 'SSA Simpanan';
                formCsv.style.display = 'block';
                inputCsv.disabled = false;
                inputCsv.required = true;
                inputCsv.setAttribute('accept', '.csv,.txt,.xlsx,.xls');
                formImport.action = "{{ route('import.excel.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.excel.prepare-preview') }}";
                
                csvLabel.innerHTML = `<i class="fas fa-file-upload mr-1"></i> Upload File ${label} (.csv, .txt, .xlsx, .xls)`;
                csvHelp.textContent = `File CSV dan Excel didukung untuk ${label}.`;
                
                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput({ visible: false });
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (usesGenericExcelFlow) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                
                // RKA support CSV as per user request
                const accept = isRka ? '.xlsx,.xls,.csv' : '.xlsx,.xls';
                inputExcel.setAttribute('accept', accept);
                
                formImport.action = "{{ route('import.excel.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.excel.prepare-preview') }}";

                if (excelLabel) {
                    const labelText = isRka 
                        ? '<i class="fas fa-file-excel mr-1"></i> Upload File RKA (.xlsx, .xls, .csv)'
                        : '<i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)';
                    excelLabel.innerHTML = labelText;
                }

                if (excelHelp) {
                    excelHelp.textContent = describeUploadLimitMessage(null);
                }

                configurePeriodeInput(buildManualPeriodeOptions({
                    visible: requiresManualPeriode,
                    required: requiresManualPeriode,
                    type: manualPeriodeType || 'date',
                    label: manualPeriodeLabel || 'Periode',
                    help: manualPeriodeHelp || 'Pilih periode manual sesuai kebutuhan report.',
                }));

                configureKancaInput({
                    visible: isRka,
                    required: isRka,
                    label: 'Pilih Cabang (Kanca)',
                    help: 'Pilih Cabang untuk mengisi kolom `kanca` pada semua baris import RKA.',
                });
                applyButtonState('excel', '<i class="fas fa-file-excel"></i> Upload File');
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (reportName.includes('brimo')) {
                formRAR.style.display = 'block';
                inputRar.disabled = false;
                inputRar.required = true;
                formImport.action = "{{ route('import.brimo.upload') }}";
                configureKancaInput({ visible: false });
                applyButtonState('rar', '<i class="fas fa-file-archive"></i> Upload RAR');
                updateReportSummary();
                updateFileSelectionUI();
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
            configureKancaInput({ visible: false });
            formImport.action = "{{ route('import.upload') }}";
            applyButtonState('rar', '<i class="fas fa-file-archive"></i> Upload RAR');
            updateReportSummary();
            updateFileSelectionUI();
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
            updateFileSelectionUI();
        });

        inputCsv?.addEventListener('change', updateFileSelectionUI);
        inputRar?.addEventListener('change', updateFileSelectionUI);

        importDropzone?.addEventListener('click', function () {
            getActiveFileInput()?.click();
        });

        importDropzone?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                getActiveFileInput()?.click();
            }
        });

        ['dragenter', 'dragover'].forEach(function(eventName) {
            importDropzone?.addEventListener(eventName, function (event) {
                event.preventDefault();
                importDropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach(function(eventName) {
            importDropzone?.addEventListener(eventName, function (event) {
                event.preventDefault();
                importDropzone.classList.remove('is-dragover');
            });
        });

        importDropzone?.addEventListener('drop', function (event) {
            assignFileToActiveInput(event.dataTransfer?.files);
        });

        importFileClear?.addEventListener('click', function () {
            [inputRar, inputExcel, inputCsv].forEach(function (input) {
                if (input) {
                    input.value = '';
                }
            });

            const rarLabel = inputRar?.nextElementSibling;
            if (rarLabel?.classList.contains('custom-file-label')) {
                rarLabel.textContent = 'Pilih file .rar...';
            }

            if (isSimpananReportSelected()) {
                applySimpananUploadMode();
            }

            updateFileSelectionUI();
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
        updateReportSummary();
        updateFileSelectionUI();
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
            updateFileSelectionUI();
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
            const isPolarsFlow = uploadKind === 'excel' || uploadKind === 'csv';
            const titleText = uploadKind === 'excel'
                ? 'Proses Excel'
                : uploadKind === 'csv'
                    ? 'Proses CSV'
                    : (uploadKind === 'cras' ? 'Proses CRAS' : (uploadKind === 'link' ? 'Simpan Link' : 'Proses Import'));
            const descText = hasAsyncPreview
                ? (isPolarsFlow ? 'File sedang diproses menuju fase Polars.' : 'File sedang diproses untuk preview.')
                : (uploadKind === 'link'
                    ? 'Link spreadsheet sedang disimpan.'
                    : (isPolarsFlow ? 'File sedang diproses menuju fase Polars.' : 'File sedang diproses.'));
            const initialPhaseText = uploadKind === 'link' ? 'Menyiapkan penyimpanan link...' : (isPolarsFlow ? 'Fase Polars dimulai...' : 'Menyiapkan proses...');
            const initialStatusText = uploadKind === 'link' ? 'Menyimpan data link...' : (isPolarsFlow ? 'Menyiapkan batch Polars...' : 'Menunggu proses...');

            const progressHtml = `
                <div class="swal-import-shell">
                    <div class="swal-import-head">
                        <span class="swal-import-badge"><i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang diproses</span>
                        <div class="swal-import-desc" id="swal-desc-text">${descText}</div>
                        <div class="swal-import-phase" id="swal-progress-phase">${initialPhaseText}</div>
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
                            <small id="swal-progress-text" class="swal-import-meta__status">${initialStatusText}</small>
                        </div>
                    </div>
                    <div id="swal-import-metrics" class="swal-import-metrics" hidden>
                        <div class="swal-import-metrics__head">
                            <div class="swal-import-metrics__title-group">
                                <span id="swal-import-metrics-label" class="swal-import-label">Progress Data</span>
                                <div id="swal-import-metrics-note" class="swal-import-metrics__note">Menunggu data...</div>
                            </div>
                            <div id="swal-import-metrics-state" class="swal-import-metrics__state">Akan muncul saat data tersedia</div>
                        </div>
                        <div class="swal-import-stats swal-import-stats--compact">
                            <div class="swal-import-stat">
                                <span class="swal-import-stat__label">Baris</span>
                                <span id="swal-rows-info" class="swal-import-stat__value">-</span>
                                <span id="swal-rows-detail" class="swal-import-stat__detail">Menunggu data baris pertama...</span>
                            </div>
                            <div class="swal-import-stat">
                                <span class="swal-import-stat__label">Kecepatan</span>
                                <span id="swal-speed-info" class="swal-import-stat__value">-</span>
                                <span id="swal-speed-detail" class="swal-import-stat__detail">Menunggu data kecepatan pertama...</span>
                            </div>
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
                    const progressPhase = document.getElementById('swal-progress-phase');
                    const progressPercent = document.getElementById('swal-progress-percent');
                    const progressMetrics = document.getElementById('swal-import-metrics');
                    const progressMetricsLabel = document.getElementById('swal-import-metrics-label');
                    const progressMetricsNote = document.getElementById('swal-import-metrics-note');
                    const progressMetricsState = document.getElementById('swal-import-metrics-state');
                    const rowsInfo = document.getElementById('swal-rows-info');
                    const rowsDetail = document.getElementById('swal-rows-detail');
                    const speedInfo = document.getElementById('swal-speed-info');
                    const speedDetail = document.getElementById('swal-speed-detail');
                    const chunkedUpload = formImport.dataset.chunkedUpload === '1';
                    const selectedFile = !inputRar.disabled
                        ? inputRar?.files?.[0]
                        : (!inputExcel.disabled ? inputExcel?.files?.[0] : inputCsv?.files?.[0]);
                    const processStartedAt = Date.now();

                    function formatDuration(seconds) {
                        const totalSeconds = Math.max(0, Math.round(Number(seconds) || 0));
                        const hours = Math.floor(totalSeconds / 3600);
                        const minutes = Math.floor((totalSeconds % 3600) / 60);
                        const secs = totalSeconds % 60;

                        if (hours > 0) {
                            return `${hours}j ${String(minutes).padStart(2, '0')}m`;
                        }

                        if (minutes > 0) {
                            return `${minutes}m ${String(secs).padStart(2, '0')} dtk`;
                        }

                        return `${secs} dtk`;
                    }

                    function setMetricsVisible(visible) {
                        if (!progressMetrics) {
                            return;
                        }

                        progressMetrics.hidden = !visible;
                        progressMetrics.classList.toggle('is-hidden', !visible);
                    }

                    updateProgressSurface(
                        isPolarsFlow ? 5 : 3,
                        initialStatusText,
                        0,
                        0,
                        0,
                        '',
                        isPolarsFlow ? 'polars' : '',
                        isPolarsFlow ? 'polars' : ''
                    );

                    function updateProgressSurface(percent, message, rowsDone = null, totalRows = null, speedValue = null, speedLabel = '', mode = '', phase = '') {
                        if (uploadProgressBar && Number.isFinite(percent)) {
                            uploadProgressBar.style.width = percent + '%';
                            uploadProgressBar.innerText = percent + '%';
                        }
                        if (progressPercent && Number.isFinite(percent)) {
                            progressPercent.textContent = percent + '%';
                        }
                        if (uploadProgressText && message) {
                            uploadProgressText.innerText = message;
                        }
                        if (progressPhase && message) {
                            progressPhase.textContent = message;
                        }

                        const numericPercent = Number(percent);
                        const numericRowsDone = Number(rowsDone);
                        const numericTotalRows = Number(totalRows);
                        const numericSpeed = Number(speedValue || 0);
                        const isDirectLoad = mode === 'direct_load' || phase === 'direct_load';
                        const isPolars = mode === 'polars';
                        const hasRows = Number.isFinite(numericRowsDone) && numericRowsDone > 0;
                        const hasTotal = Number.isFinite(numericTotalRows) && numericTotalRows > 0;
                        const hasSpeed = Number.isFinite(numericSpeed) && numericSpeed > 0;
                        const isFinalLoadStage = Number.isFinite(numericPercent) && numericPercent >= 95;
                        const showMetrics = !isDirectLoad && !isFinalLoadStage && (hasRows || hasSpeed || (isPolars && hasTotal));

                        setMetricsVisible(showMetrics);

                        if (progressMetricsLabel) {
                            progressMetricsLabel.textContent = isPolars ? 'Progress Polars' : 'Progress Data';
                        }

                        if (progressMetricsNote) {
                            progressMetricsNote.textContent = isPolars
                                ? 'Normalisasi Polars aktif'
                                : (showMetrics ? 'Data proses aktif' : 'Menunggu data...');
                        }

                        if (progressMetricsState) {
                            progressMetricsState.textContent = isDirectLoad
                                ? 'Mode direct load disembunyikan'
                                : (showMetrics
                                    ? (isPolars ? 'Batch normalisasi aktif' : 'Panel aktif saat data tersedia')
                                    : 'Akan muncul saat data tersedia');
                        }

                        if (rowsInfo) {
                            if (hasRows && hasTotal) {
                                rowsInfo.textContent = numericRowsDone.toLocaleString('id-ID') + ' / ' + numericTotalRows.toLocaleString('id-ID');
                            } else if (hasRows) {
                                rowsInfo.textContent = numericRowsDone.toLocaleString('id-ID');
                            } else {
                                rowsInfo.textContent = '-';
                            }
                        }

                        if (rowsDetail) {
                            rowsDetail.textContent = isPolars
                                ? 'Baris terhitung dari tahap sanitasi dan normalisasi Polars.'
                                : (showMetrics ? 'Total baris dari batch aktif.' : 'Menunggu data baris pertama...');
                        }

                        if (speedInfo) {
                            const displayLabel = speedLabel || 'baris/detik';
                            speedInfo.textContent = hasSpeed ? numericSpeed.toLocaleString('id-ID') + ' ' + displayLabel : '-';
                        }

                        if (speedDetail) {
                            speedDetail.textContent = isPolars
                                ? (hasSpeed
                                    ? 'Kecepatan normalisasi dihitung dari batch yang sedang berjalan.'
                                    : 'Menunggu pembacaan batch berikutnya.')
                                : (hasSpeed
                                    ? 'Rata-rata proses saat ini.'
                                    : 'Menunggu data kecepatan pertama...');
                        }
                    }

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
                    let nativeUploadFallbackStarted = false;
                    uploadRequest.open('POST', formImport.action, true);
                    uploadRequest.setRequestHeader('Accept', 'application/json');
                    uploadRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    uploadRequest.upload.addEventListener('progress', function(event) {
                        if (!event.lengthComputable) {
                            return;
                        }

                        const percent = Math.min(85, Math.max(3, Math.round((event.loaded / event.total) * 85)));
                        updateProgressSurface(percent, 'Mengunggah file ke server...', null, null, null, '', 'upload');
                    });

                    uploadRequest.addEventListener('load', function() {
                        if (uploadRequest.status < 200 || uploadRequest.status >= 300) {
                            let serverMessage = '';
                            let serverTitle = 'Upload Error';
                            try {
                                const errorPayload = JSON.parse(uploadRequest.responseText || '{}');
                                serverMessage = errorPayload.message || '';
                                serverTitle = errorPayload.title || serverTitle;
                            } catch (_) {
                            }

                            if (!serverMessage && uploadRequest.status === 413) {
                                serverMessage = 'Ukuran upload melebihi batas server. Silakan kecilkan file atau naikkan limit upload.';
                            }

                            if (isDuplicateImportMessage(serverMessage) || isDuplicateImportMessage(serverTitle)) {
                                showDuplicateImportPopup(serverMessage || serverTitle || 'Data duplikat terdeteksi.', serverTitle || 'Data Duplikat');
                                resetSubmitButton();
                                return;
                            }

                            themedSwal({
                                icon: 'error',
                                title: serverTitle,
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
                            const duplicateText = data.text || data.message || data.title || '';
                            if (isDuplicateImportMessage(duplicateText)) {
                                showDuplicateImportPopup(duplicateText || 'Data duplikat terdeteksi.', data.title || 'Data Duplikat');
                                resetSubmitButton();
                                return;
                            }

                            themedSwal({
                                icon: 'error',
                                title: 'Upload Error',
                                text: data.message || 'Upload gagal diproses oleh server.'
                            });
                            resetSubmitButton();
                            return;
                        }

                        if (data.redirect && String(data.redirect).includes('prepare-preview')) {
                            if (uploadProgressBar) {
                                uploadProgressBar.style.width = '88%';
                                uploadProgressBar.innerText = '88%';
                            }
                            updateProgressSurface(88, 'Upload selesai. Menyiapkan preview cepat...', null, null, null, '', 'preview');

                            const eventSource = new EventSource(data.redirect);

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
                                var normalized = normalizeProgressStatus(evtData.message || '');
                                var speedValue = normalized.speed || (evtData.speed != null ? String(evtData.speed).replace(/[^\d]/g, '') : '');
                                updateProgressSurface(
                                    evtData.percent != null ? Math.max(88, Math.min(100, 88 + Math.round((evtData.percent / 100) * 12))) : 88,
                                    normalized.message || evtData.message || 'Memproses data...',
                                    evtData.rows_done != null ? Number(evtData.rows_done) : null,
                                    evtData.total != null ? Number(evtData.total) : null,
                                    speedValue,
                                    evtData.speed_label || normalized.speedLabel || '',
                                    evtData.mode || normalized.mode || '',
                                    evtData.phase || ''
                                );
                            });

                            eventSource.addEventListener('ready', function(event) {
                                var evtData = {};
                                try { evtData = JSON.parse(event.data); } catch (_) {}
                                eventSource.close();
                                if (evtData.redirect) {
                                    if (typeof window.showRouteLoading === 'function') {
                                        window.showRouteLoading('Memuat halaman', 'Menyiapkan preview data terbaru.');
                                    }
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

                            if (typeof window.showRouteLoading === 'function') {
                                window.showRouteLoading('Memuat halaman', 'Menyiapkan preview data terbaru.');
                            }
                            window.location.href = data.redirect;
                            return;
                        }

                        if (directRedirect) {
                            if (data.redirect) {
                                if (typeof window.showRouteLoading === 'function') {
                                    window.showRouteLoading('Memuat halaman', 'Menyiapkan preview data terbaru.');
                                }
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
                        updateProgressSurface(88, 'Upload selesai. Menyiapkan preview cepat...', null, null, null, '', 'preview');

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
                            var normalized = normalizeProgressStatus(evtData.message || '');
                            var speedValue = normalized.speed || (evtData.speed != null ? String(evtData.speed).replace(/[^\d]/g, '') : '');
                            updateProgressSurface(
                                evtData.percent != null ? Math.max(88, Math.min(100, 88 + Math.round((evtData.percent / 100) * 12))) : 88,
                                normalized.message || evtData.message || 'Memproses data...',
                                evtData.rows_done != null ? Number(evtData.rows_done) : null,
                                evtData.total != null ? Number(evtData.total) : null,
                                speedValue,
                                evtData.speed_label || normalized.speedLabel || '',
                                evtData.mode || normalized.mode || '',
                                evtData.phase || ''
                            );
                        });

                        eventSource.addEventListener('ready', function(event) {
                            var evtData = {};
                            try { evtData = JSON.parse(event.data); } catch (_) {}
                            eventSource.close();
                            if (evtData.redirect) {
                                if (typeof window.showRouteLoading === 'function') {
                                    window.showRouteLoading('Memuat halaman', 'Menyiapkan preview data terbaru.');
                                }
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

                        // Add heartbeat timeout detection (if no message for 90 sec, something is wrong)
                        var heartbeatTimeout = null;
                        const resetHeartbeatTimeout = () => {
                            if (heartbeatTimeout) clearTimeout(heartbeatTimeout);
                            heartbeatTimeout = setTimeout(() => {
                                eventSource.close();
                                themedSwal({
                                    icon: 'error',
                                    title: 'Timeout',
                                    text: 'Server tidak merespons selama 90 detik. Silakan coba lagi.'
                                });
                                resetSubmitButton();
                            }, 90000); // 90 second timeout
                        };
                        resetHeartbeatTimeout();

                        // Reset heartbeat timeout on any message
                        eventSource.addEventListener('open', resetHeartbeatTimeout);
                        eventSource.addEventListener('progress', resetHeartbeatTimeout);
                        eventSource.addEventListener('heartbeat', resetHeartbeatTimeout);
                        eventSource.addEventListener('ready', resetHeartbeatTimeout);
                        eventSource.addEventListener('error_msg', resetHeartbeatTimeout);

                        eventSource.onerror = function() {
                            if (heartbeatTimeout) clearTimeout(heartbeatTimeout);
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
                        const configuredLimit = Number(activeUploadLimits?.effective_max_upload_bytes || 0);
                        const exceedsConfiguredLimit = selectedFile && configuredLimit > 0 && selectedFile.size > configuredLimit;

                        if (!exceedsConfiguredLimit && selectedFile && uploadRequest.status === 0 && !nativeUploadFallbackStarted) {
                            nativeUploadFallbackStarted = true;
                            updateProgressSurface(3, 'Mencoba jalur upload standar...', null, null, null, '', 'upload');
                            window.setTimeout(function () {
                                HTMLFormElement.prototype.submit.call(formImport);
                            }, 80);
                            return;
                        }

                        themedSwal({
                            icon: 'error',
                            title: 'Upload Error',
                            text: exceedsConfiguredLimit
                                ? 'Ukuran file melebihi batas upload server. Upload tidak dapat dimulai.'
                                : 'Server belum dapat menerima upload. Periksa koneksi ke server lalu coba lagi.'
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
            (async function () {
                const warningTitle = {!! json_encode(session('sweet_warning')['title']) !!};
                const warningText = {!! json_encode(session('sweet_warning')['text']) !!};
                const isDuplicateWarning = isDuplicateImportMessage(warningTitle) || isDuplicateImportMessage(warningText);
                await themedSwal({
                    icon: 'warning',
                    title: warningTitle,
                    html: warningText,
                    confirmButtonText: isDuplicateWarning ? 'Kembali ke Import' : 'Mengerti'
                });
                if (isDuplicateWarning) {
                    redirectToImportIndex();
                }
            })();
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
    .download-toast-stack {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 1085;
        display: flex;
        flex-direction: column;
        gap: .75rem;
        width: min(360px, calc(100vw - 2rem));
        pointer-events: none;
    }

    .download-toast {
        pointer-events: auto;
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .9rem 1rem;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 18px 40px -24px rgba(15, 76, 186, .45);
        border: 1px solid rgba(148, 163, 184, .24);
        transform: translateY(0);
        opacity: 1;
        transition: transform .18s ease, opacity .18s ease;
    }

    .download-toast.is-hiding {
        transform: translateY(-8px);
        opacity: 0;
    }

    .download-toast--success {
        border-left: 4px solid #10b981;
    }

    .download-toast--error {
        border-left: 4px solid #ef4444;
    }

    .download-toast__icon {
        flex: 0 0 auto;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        font-size: .95rem;
        color: #fff;
        margin-top: 2px;
    }

    .download-toast--success .download-toast__icon {
        background: #10b981;
    }

    .download-toast--error .download-toast__icon {
        background: #ef4444;
    }

    .download-toast__body {
        min-width: 0;
        flex: 1 1 auto;
    }

    .download-toast__title {
        font-weight: 800;
        color: #0f172a;
        line-height: 1.25;
        margin-bottom: .2rem;
    }

    .download-toast__text {
        color: #475569;
        font-size: .93rem;
        line-height: 1.35;
        word-break: break-word;
    }

    .download-toast__close {
        flex: 0 0 auto;
        border: 0;
        background: transparent;
        color: #94a3b8;
        font-size: 1.4rem;
        line-height: 1;
        padding: 0 .1rem;
        margin-top: -2px;
        cursor: pointer;
    }

    .download-toast__close:hover {
        color: #0f172a;
    }

    .import-template-card,
    .import-upload-card {
        border-radius: 24px !important;
        overflow: hidden;
        border: 1px solid rgba(15, 76, 186, 0.12) !important;
        box-shadow: 0 20px 40px -20px rgba(15, 76, 186, 0.08), 0 1px 3px rgba(15, 76, 186, 0.02) !important;
        background: #ffffff;
    }

    .import-template-card__body {
        padding: 1.5rem;
    }

    .import-template-card__eyebrow,
    .import-upload-card__eyebrow {
        display: inline-block;
        margin-bottom: 0.65rem;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #0f4cba;
        background: rgba(15, 76, 186, 0.08);
        border: 1px solid rgba(15, 76, 186, 0.12);
    }

    .import-template-card__title {
        color: #0f172a;
        font-size: 1.2rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 0.35rem;
    }

    .import-template-card__text {
        color: #64748b;
        line-height: 1.65;
        max-width: 620px;
    }

    .import-template-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .import-template-select {
        min-width: 280px;
        max-width: 420px;
        flex: 1 1 auto;
    }

    .import-template-select__control,
    .import-template-select .select2-container--bootstrap4 .select2-selection--single {
        min-height: 48px;
        border-radius: 14px !important;
        border: 1px solid rgba(15, 76, 186, 0.14) !important;
        background: #ffffff !important;
        display: flex;
        align-items: center;
        box-shadow: none !important;
    }

    .import-template-select .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        color: #0f172a;
        font-weight: 600;
        padding-left: 1rem;
    }

    .import-template-select .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: 46px;
        right: 8px;
    }

    .import-template-select .select2-container--bootstrap4.select2-container--focus .select2-selection--single {
        border-color: #0f4cba !important;
        box-shadow: 0 0 0 0.2rem rgba(15, 76, 186, 0.16) !important;
    }

    .import-template-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 0.8rem 1.5rem;
        border-radius: 16px;
        border: 0;
        background: linear-gradient(135deg, #0f4cba, #2563eb);
        color: #ffffff;
        font-weight: 700;
        box-shadow: 0 14px 28px -10px rgba(15, 76, 186, 0.4);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .import-template-button:hover {
        color: #ffffff;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 18px 34px -10px rgba(15, 76, 186, 0.5);
    }

    .import-template-button.disabled,
    .import-template-button[aria-disabled="true"] {
        pointer-events: none;
        opacity: 0.6;
        background: linear-gradient(135deg, #94a3b8, #64748b);
        box-shadow: none;
    }

    .import-upload-card__header {
        padding: 1.45rem 1.5rem 1.15rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-bottom: 1px solid rgba(15, 76, 186, 0.08);
    }

    .import-upload-card__subtitle {
        color: #64748b;
        max-width: 620px;
        line-height: 1.6;
    }

    .import-upload-card__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.55rem 0.95rem;
        border-radius: 999px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
        box-shadow: 0 2px 4px rgba(4, 120, 87, 0.04);
    }

    .import-upload-card__body {
        padding: 1.5rem;
    }

    .import-report-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .import-report-summary__item {
        padding: 1.1rem 1.25rem;
        border-radius: 18px;
        min-width: 0;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.12);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
    }
    .import-report-summary__item:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 20px -8px rgba(15, 76, 186, 0.08);
    }

    /* Column 1: Report dipilih (Blue theme) */
    .import-report-summary__item:nth-child(1) {
        background: linear-gradient(180deg, #ffffff 0%, #edf4fe 100%);
        border-left: 4px solid #0f4cba;
        box-shadow: inset 1px 0 0 rgba(15, 76, 186, 0.1), 0 4px 6px -1px rgba(15, 76, 186, 0.02);
    }
    .import-report-summary__item:nth-child(1) .import-report-summary__label {
        color: #0f4cba;
    }

    /* Column 2: Format (Cyan theme) */
    .import-report-summary__item:nth-child(2) {
        background: linear-gradient(180deg, #ffffff 0%, #e0f7fa 100%);
        border-left: 4px solid #00a3ff;
    }
    .import-report-summary__item:nth-child(2) .import-report-summary__label {
        color: #00a3ff;
    }

    /* Column 3: Periode (Amber/Yellow theme) */
    .import-report-summary__item:nth-child(3) {
        background: linear-gradient(180deg, #ffffff 0%, #fffbeb 100%);
        border-left: 4px solid #f59e0b;
    }
    .import-report-summary__item:nth-child(3) .import-report-summary__label {
        color: #f59e0b;
    }

    /* Column 4: Target tabel (Red/Danger theme) */
    .import-report-summary__item:nth-child(4) {
        background: linear-gradient(180deg, #ffffff 0%, #fef2f2 100%);
        border-left: 4px solid #ef4444;
    }
    .import-report-summary__item:nth-child(4) .import-report-summary__label {
        color: #ef4444;
    }

    .import-report-summary__label {
        display: block;
        margin-bottom: 0.35rem;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .import-report-summary__item strong {
        display: block;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 800;
        line-height: 1.35;
        word-break: break-word;
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
        background: #eaf2ff;
        color: #0f4cba;
    }

    .import-upload-card__footer {
        padding: 0 1.5rem 1.5rem;
        background: linear-gradient(180deg, rgba(248, 251, 255, 0) 0%, #f8fbff 100%);
    }

    .import-upload-card__submit {
        min-height: 50px;
        padding: 0.85rem 1.4rem;
        border-radius: 16px;
        box-shadow: 0 14px 28px -10px rgba(15, 76, 186, 0.4);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .import-upload-card__submit.btn-primary,
    .import-upload-card__submit.btn-success,
    .import-upload-card__submit.btn-info {
        border: 0;
        background: linear-gradient(135deg, #0f4cba, #2563eb);
        box-shadow: 0 14px 28px -10px rgba(15, 76, 186, 0.4);
    }

    .import-upload-card__submit.btn-primary:hover,
    .import-upload-card__submit.btn-success:hover,
    .import-upload-card__submit.btn-info:hover {
        background: linear-gradient(135deg, #0d43a5, #1d5ec2);
        transform: translateY(-2px);
        box-shadow: 0 18px 34px -10px rgba(15, 76, 186, 0.5);
    }

    @keyframes bounceCloud {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-5px);
        }
    }

    .import-dropzone {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        margin-top: 1.15rem;
        padding: 1.5rem 1.75rem;
        border-radius: 20px !important;
        border: 2px dashed rgba(15, 76, 186, 0.25) !important;
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 200px;
    }

    .import-dropzone:hover,
    .import-dropzone:focus,
    .import-dropzone.is-dragover {
        outline: none;
        transform: translateY(-3px);
        border-color: #0f4cba !important;
        box-shadow: 0 20px 35px -20px rgba(15, 76, 186, 0.15), 0 0 0 4px rgba(15, 76, 186, 0.08);
        background: linear-gradient(180deg, #edf4fe 0%, #ffffff 100%);
    }

    .import-dropzone:hover .import-dropzone__icon i {
        animation: bounceCloud 1.2s infinite ease-in-out;
    }

    .import-dropzone.has-file {
        border-style: solid;
        border-color: #10b981 !important;
        background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);
    }

    .import-dropzone__icon {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: rgba(15, 76, 186, 0.08);
        color: #0f4cba !important;
        font-size: 1.6rem;
        flex: 0 0 auto;
        transition: all 0.2s ease;
    }

    .import-dropzone.has-file .import-dropzone__icon {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981 !important;
    }

    .import-dropzone__title {
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 800;
        margin-bottom: 0.25rem;
    }

    .import-dropzone__text {
        color: #64748b;
        font-size: 0.88rem;
        line-height: 1.5;
    }

    .import-dropzone__hint {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.85rem;
    }

    .import-dropzone__hint span {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        transition: all 0.2s ease;
    }

    .import-dropzone__hint span:hover {
        border-color: #cbd5e1;
        color: #1e293b;
        transform: scale(1.05);
    }

    .import-file-preview {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 1.15rem;
        padding: 1.1rem 1.25rem;
        border-radius: 18px !important;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        box-shadow: 0 10px 25px -15px rgba(16, 185, 129, 0.15);
    }

    .import-file-preview__icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: rgba(16, 185, 129, 0.12);
        color: #10b981;
        font-size: 1.25rem;
        flex: 0 0 auto;
    }

    .import-file-preview__body {
        flex: 1 1 auto;
        min-width: 0;
    }

    .import-file-preview__name {
        color: #0f172a;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .import-file-preview__meta {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 0.2rem;
        color: #64748b;
        font-size: 0.88rem;
    }

    .import-file-preview__clear {
        border: 0;
        border-radius: 14px;
        padding: 0.7rem 0.95rem;
        background: rgba(239, 68, 68, 0.08);
        color: #ef4444;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .import-file-preview__clear:hover {
        background: rgba(239, 68, 68, 0.14);
        color: #dc2626;
        transform: translateY(-1px);
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
        background: linear-gradient(135deg, #0f4cba, #2563eb);
        color: #ffffff;
        font-weight: 700;
        padding: 0.8rem 1.3rem;
        box-shadow: 0 14px 28px -10px rgba(15, 76, 186, 0.4);
    }

    .swal-import-shell {
        display: grid;
        gap: 1rem;
        text-align: left;
    }

    .swal-import-head {
        display: grid;
        justify-items: center;
        gap: 0.45rem;
        text-align: center;
    }

    .swal-import-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: fit-content;
        margin-inline: auto;
        padding: 0.4rem 0.72rem;
        border-radius: 999px;
        background: rgba(15, 76, 186, 0.08);
        color: #0f4cba;
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

    .swal-import-phase {
        color: #0f4cba;
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
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
        height: 15px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .swal-import-progress__bar {
        position: relative;
        background: linear-gradient(90deg, #0f4cba 0%, #2563eb 48%, #10b981 100%);
        background-size: 200% 100%;
        font-weight: 800;
        font-size: 11px;
        line-height: 14px;
        transition: width 220ms cubic-bezier(0.22, 1, 0.36, 1);
        animation: swalImportShine 1.8s linear infinite;
    }

    .swal-import-meta {
        margin-top: 0.7rem;
    }

    .swal-import-meta__status {
        color: #0f4cba;
        font-weight: 700;
        letter-spacing: 0.02em;
        display: block;
        word-break: break-word;
        white-space: normal;
    }

    .swal-import-metrics {
        display: grid;
        gap: 0.85rem;
        padding: 0.95rem;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(241, 245, 249, 0.98));
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
    }

    .swal-import-metrics.is-hidden {
        display: none !important;
    }

    .swal-import-metrics__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .swal-import-metrics__title-group {
        min-width: 0;
    }

    .swal-import-metrics__note {
        margin-top: 0.2rem;
        color: #475569;
        font-size: 0.74rem;
        line-height: 1.35;
    }

    .swal-import-metrics__state {
        flex: 0 0 auto;
        color: #0f4cba;
        font-size: 0.73rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        text-align: right;
    }

    .swal-import-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .swal-import-stats--compact {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @keyframes swalImportShine {
        0% {
            background-position: 0% 50%;
        }

        100% {
            background-position: 200% 50%;
        }
    }

    .swal-import-stat {
        min-height: 94px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
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

    .swal-import-stat__detail {
        display: block;
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.72rem;
        line-height: 1.35;
    }

    @media (max-width: 767.98px) {
        .import-template-actions,
        .import-template-select,
        .import-template-button,
        .import-upload-card__submit,
        .import-dropzone,
        .import-file-preview {
            width: 100%;
        }

        .import-dropzone {
            flex-direction: column;
            text-align: center;
        }

        .import-report-summary {
            grid-template-columns: 1fr;
        }

        .import-upload-card__header,
        .import-upload-card__body,
        .import-upload-card__footer {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .import-template-card__title {
            font-size: 1.15rem;
        }
    }
    /* Modern Select2 & Custom Select Styling */
    .select2-container--default .select2-selection--single {
        height: 52px !important;
        padding: 0 1rem !important;
        border-radius: 16px !important;
        border: 2px solid #eef2f6 !important;
        background-color: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
    }

    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #0f4cba !important;
        box-shadow: 0 0 0 4px rgba(15, 76, 186, 0.1) !important;
        background-color: #f8fafc !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #1e293b !important;
        font-weight: 700 !important;
        font-size: 0.95rem !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 50px !important;
        right: 12px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent !important;
        border-width: 6px 5px 0 5px !important;
    }

    .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #0f4cba transparent !important;
        border-width: 0 5px 6px 5px !important;
    }

    .select2-dropdown {
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
        border-radius: 20px !important;
        box-shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.15) !important;
        overflow: hidden !important;
        margin-top: 8px !important;
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(10px) !important;
        z-index: 9999 !important;
    }

    .select2-results__option {
        padding: 0.85rem 1.25rem !important;
        font-weight: 600 !important;
        font-size: 0.92rem !important;
        color: #475569 !important;
        transition: all 0.2s ease !important;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: #f1f5f9 !important;
        color: #0f4cba !important;
    }

    .select2-results__option[aria-selected=true] {
        background-color: rgba(15, 76, 186, 0.05) !important;
        color: #0f4cba !important;
    }

    .select2-search--dropdown {
        padding: 12px 12px 8px !important;
    }

    .select2-search--dropdown .select2-search__field {
        border-radius: 12px !important;
        border: 1.5px solid #e2e8f0 !important;
        padding: 8px 12px !important;
        font-weight: 600 !important;
    }

    /* Native select styling for consistency */
    select.form-control:not(.select2-hidden-accessible) {
        height: 52px !important;
        border-radius: 16px !important;
        border: 2px solid #eef2f6 !important;
        padding: 0 1.25rem !important;
        font-weight: 700 !important;
        font-size: 0.95rem !important;
        color: #1e293b !important;
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 1.25rem center !important;
        background-size: 1.25rem !important;
    }

    select.form-control:not(.select2-hidden-accessible):focus {
        border-color: #0f4cba !important;
        box-shadow: 0 0 0 4px rgba(15, 76, 186, 0.1) !important;
        outline: none !important;
    }

    /* Form group hover effects */
    .form-group {
        transition: transform 0.2s ease;
    }
    .form-group:focus-within {
        transform: translateX(4px);
    }
    .form-group:focus-within label {
        color: #0f4cba !important;
    }

    @keyframes selectDropdownReveal {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .select2-container--open .select2-dropdown {
        animation: selectDropdownReveal 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Quiet upload workspace: compact, neutral, and centered on the next action. */
    .import-template-card,
    .import-upload-card {
        border: 1px solid #dbe3ec !important;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 8px 22px -20px rgba(15, 23, 42, 0.42) !important;
    }

    .import-template-card__body,
    .import-upload-card__body {
        padding: 1rem !important;
    }

    .import-template-card__eyebrow,
    .import-upload-card__eyebrow {
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: #64748b;
        font-size: 0.68rem;
        letter-spacing: 0.08em;
    }

    .import-template-card__title,
    .import-upload-card__title {
        color: #172033 !important;
        font-size: 1.05rem;
    }

    .import-template-card__title .text-primary,
    .import-upload-card__title .text-primary {
        color: #0b5cab !important;
    }

    .import-template-card__text,
    .import-upload-card__subtitle {
        color: #64748b;
        font-size: 0.88rem;
        line-height: 1.45;
    }

    .import-template-select .select2-container--bootstrap4 .select2-selection--single,
    .import-upload-card__body .form-control,
    .import-upload-card__body .select2-container--default .select2-selection--single,
    select.form-control:not(.select2-hidden-accessible) {
        min-height: 40px !important;
        height: 40px !important;
        padding: 0 0.8rem !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        background-color: #ffffff !important;
        box-shadow: none !important;
        color: #334155 !important;
        font-size: 0.88rem !important;
        font-weight: 600 !important;
    }

    .import-upload-card__header {
        padding: 1rem !important;
        background: #ffffff !important;
        border-bottom: 1px solid #e5eaf0 !important;
    }

    .import-upload-card__badge {
        padding: 0.38rem 0.6rem;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        background: #f8fafc;
        color: #475569;
        font-size: 0.7rem;
        box-shadow: none;
    }

    .import-report-summary {
        gap: 0.6rem;
        margin-bottom: 1rem !important;
    }

    .import-report-summary__item,
    .import-report-summary__item:nth-child(n) {
        padding: 0.7rem 0.8rem;
        border: 1px solid #e2e8f0;
        border-left: 3px solid #94a3b8;
        border-radius: 6px;
        background: #f8fafc;
        box-shadow: none;
        transform: none;
    }

    .import-report-summary__item:nth-child(1) {
        border-left-color: #0b5cab;
    }

    .import-report-summary__item:hover {
        box-shadow: none;
        transform: none;
    }

    .import-report-summary__item .import-report-summary__label,
    .import-report-summary__item:nth-child(n) .import-report-summary__label {
        color: #64748b;
        font-size: 0.64rem;
    }

    .import-report-summary__item strong {
        color: #1e293b;
        font-size: 0.88rem;
    }

    .import-upload-card__body .form-group label {
        margin-bottom: 0.4rem;
        color: #334155 !important;
        font-size: 0.82rem;
    }

    .import-dropzone {
        min-height: 170px;
        margin-top: 0;
        padding: 1.1rem;
        border: 1px dashed #94a3b8 !important;
        border-radius: 8px !important;
        background: #f8fafc;
        box-shadow: none;
    }

    .import-dropzone:hover,
    .import-dropzone:focus,
    .import-dropzone.is-dragover {
        transform: none;
        border-color: #0b5cab !important;
        background: #f1f5f9;
        box-shadow: 0 0 0 3px rgba(11, 92, 171, 0.1);
    }

    .import-dropzone__icon,
    .import-dropzone.has-file .import-dropzone__icon {
        width: 46px;
        height: 46px;
        border-radius: 6px;
        background: #e2e8f0;
        color: #0b5cab !important;
    }

    .import-dropzone__title {
        font-size: 0.98rem;
    }

    .import-dropzone__text {
        font-size: 0.82rem;
    }

    .import-dropzone__hint {
        margin-top: 0.6rem;
    }

    .import-dropzone__hint span {
        min-height: 24px;
        padding: 0.2rem 0.45rem;
        border-radius: 4px;
        background: #ffffff;
        font-size: 0.68rem;
    }

    .import-file-preview {
        padding: 0.7rem 0.8rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px !important;
        background: #f8fafc;
        box-shadow: none;
    }

    .import-file-preview__icon {
        width: 38px;
        height: 38px;
        border-radius: 5px;
        background: #e2e8f0;
        color: #0b5cab;
    }

    .import-file-preview__clear {
        padding: 0.45rem 0.55rem;
        border-radius: 5px;
        background: #f1f5f9;
        color: #475569;
    }

    .import-upload-card__footer {
        padding: 0 1rem 1rem;
        background: #ffffff;
    }

    .import-template-button,
    .import-upload-card__submit,
    .import-upload-card__submit.btn-primary,
    .import-upload-card__submit.btn-success,
    .import-upload-card__submit.btn-info {
        min-height: 40px;
        padding: 0.5rem 0.85rem;
        border: 1px solid #0b5cab;
        border-radius: 6px;
        background: #0b5cab;
        box-shadow: none;
        font-size: 0.82rem;
    }

    .import-template-button:hover,
    .import-upload-card__submit.btn-primary:hover,
    .import-upload-card__submit.btn-success:hover,
    .import-upload-card__submit.btn-info:hover {
        transform: none;
        border-color: #084a88;
        background: #084a88;
        box-shadow: none;
    }

    .swal-modern-popup {
        width: min(520px, calc(100vw - 24px)) !important;
        padding: 0.95rem !important;
        border-color: #dbe3ec;
        border-radius: 8px;
        box-shadow: 0 24px 56px -30px rgba(15, 23, 42, 0.44);
    }

    .swal-modern-title {
        margin: 0.15rem 0 0.3rem;
        color: #172033;
        font-size: 1.35rem;
        letter-spacing: 0;
    }

    .swal-import-shell,
    .swal-import-head {
        gap: 0.55rem;
    }

    .swal-import-desc {
        font-size: 0.84rem;
        line-height: 1.4;
    }

    .swal-import-card,
    .swal-import-metrics,
    .swal-import-stat {
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        box-shadow: none;
    }

    .swal-import-progress__bar,
    .swal-modern-confirm {
        background: #0b5cab;
        animation: none;
    }

    .swal-import-stats--compact {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .swal-import-stats--compact .swal-import-stat {
        min-height: 68px;
    }

    /* Workflow layout: one clear path from report selection to file preview. */
    .import-page {
        display: grid;
        gap: 0.85rem;
    }

    .import-page .import-template-bar,
    .import-page .import-workspace {
        margin: 0 !important;
    }

    .import-template-bar .import-template-card__body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .import-template-bar__intro {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-width: 0;
    }

    .import-template-bar__icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 5px;
        background: #e2e8f0;
        color: #0b5cab;
        flex: 0 0 auto;
    }

    .import-template-bar__title {
        color: #1e293b;
        font-size: 0.84rem;
        font-weight: 700;
    }

    .import-template-bar .import-template-card__text {
        margin-top: 0.1rem;
        font-size: 0.76rem;
    }

    .import-template-bar .import-template-actions {
        flex: 0 1 440px;
        width: 100%;
    }

    .import-workspace {
        overflow: hidden;
    }

    .import-workspace .import-upload-card__header {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(330px, 0.9fr);
        align-items: end;
        gap: 1.25rem;
    }

    .import-workspace__context {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border-top: 1px solid #e5eaf0;
        border-left: 1px solid #e5eaf0;
    }

    .import-workspace__context-item {
        min-width: 0;
        padding: 0.45rem 0.6rem;
        border-right: 1px solid #e5eaf0;
        border-bottom: 1px solid #e5eaf0;
        background: #ffffff;
    }

    .import-workspace__context-item span {
        display: block;
        margin-bottom: 0.15rem;
        color: #64748b;
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .import-workspace__context-item strong {
        display: block;
        overflow: hidden;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .import-workspace__context-item--report strong {
        color: #0b5cab;
    }

    .import-workflow {
        display: grid;
        grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.2fr);
        min-height: 300px;
    }

    .import-workflow__section {
        min-width: 0;
        padding: 1rem;
    }

    .import-workflow__section--details {
        border-right: 1px solid #e5eaf0;
    }

    .import-workflow__section--file {
        display: flex;
        flex-direction: column;
    }

    .import-workflow__heading {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin-bottom: 1rem;
    }

    .import-workflow__heading h6 {
        margin: 0;
        color: #1e293b;
        font-size: 0.9rem;
        font-weight: 700;
    }

    .import-workflow__heading p {
        margin: 0.12rem 0 0;
        color: #64748b;
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .import-workflow__step {
        display: inline-grid;
        width: 22px;
        height: 22px;
        place-items: center;
        border-radius: 50%;
        background: #0b5cab;
        color: #ffffff;
        font-size: 0.7rem;
        font-weight: 800;
        flex: 0 0 auto;
    }

    .import-workflow__section--details .form-group {
        margin-bottom: 1rem !important;
    }

    .import-workflow__section--file .import-dropzone {
        flex: 1 1 auto;
    }

    .import-workspace .import-upload-card__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-height: 60px;
        border-top: 1px solid #e5eaf0;
    }

    .import-workflow__footer-note {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
        font-size: 0.76rem;
        line-height: 1.35;
    }

    .import-workflow__footer-note .import-workflow__step {
        width: 20px;
        height: 20px;
        background: #64748b;
        font-size: 0.64rem;
    }

    @media (max-width: 767.98px) {
        .import-template-card__body,
        .import-upload-card__body,
        .import-upload-card__header {
            padding: 0.85rem !important;
        }

        .import-upload-card__footer {
            padding: 0 0.85rem 0.85rem;
        }

        .import-upload-card__badge,
        .import-template-button,
        .import-upload-card__submit {
            width: 100%;
            justify-content: center;
        }

        .import-template-bar .import-template-card__body,
        .import-workspace .import-upload-card__header,
        .import-workspace .import-upload-card__footer {
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .import-template-bar .import-template-actions {
            flex-basis: auto;
        }

        .import-template-bar .import-template-select {
            flex: 1 1 100%;
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        .import-template-bar .import-template-select .select2-container {
            width: 100% !important;
            min-width: 0;
        }

        .import-workspace__context,
        .import-workflow {
            grid-template-columns: 1fr;
        }

        .import-workflow__section--details {
            border-right: 0;
            border-bottom: 1px solid #e5eaf0;
        }

        .import-workspace .import-upload-card__footer {
            align-items: stretch;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enhance Select2 behavior without changing original scripts
        const $selects = $('.select2');
        
        $selects.on('select2:open', function() {
            const dropdown = $('.select2-dropdown');
            dropdown.css('opacity', 0);
            setTimeout(() => {
                dropdown.css('opacity', 1);
            }, 10);
        });

        // Add visual feedback to parent form groups
        $('select, input, textarea').on('focus', function() {
            $(this).closest('.form-group').addClass('is-focused');
        }).on('blur', function() {
            $(this).closest('.form-group').removeClass('is-focused');
        });
    });
</script>
@endsection
