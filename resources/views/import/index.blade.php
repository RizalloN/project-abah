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
            <div class="import-template-banner__meta">
                <span><i class="fas fa-palette mr-1"></i> Tema selaras logo</span>
                <span><i class="fas fa-bolt mr-1"></i> Lebih responsif</span>
                <span><i class="fas fa-hand-pointer mr-1"></i> Upload lebih interaktif</span>
            </div>
        </div>
        <div class="import-template-banner__brand mt-3 mt-lg-0">
            <div class="import-template-banner__brand-card">
                <img src="{{ asset('images/a-six-logo.svg') }}" alt="Logo A-SIX" class="import-template-banner__logo">
                <div>
                    <div class="import-template-banner__brand-label">A-SIX Dashboard</div>
                    <div class="import-template-banner__brand-text">Aksen warna mengikuti logo aktif dengan nuansa gelap dan oranye.</div>
                </div>
            </div>
        </div>
        <div class="import-template-banner__actions mt-3 mt-md-0">
            <div class="import-template-banner__download-group">
                <div class="import-template-banner__select-wrapper">
                    <select id="download-template-select" class="form-control select2 import-template-banner__select" data-placeholder="-- Cari & Pilih Report --">
                        <option value="">-- Cari & Pilih Report --</option>
                        @foreach($downloadTemplates as $key => $template)
                            <option value="{{ $key }}" data-filename="{{ $template['filename'] }}">{{ $template['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button"
                   id="btn-download-template"
                   class="btn import-template-banner__button disabled"
                   aria-disabled="true"
                   data-route-template="{{ route('import.template') }}">
                    <i class="fas fa-file-download mr-2"></i> Unduh Template
                </button>
            </div>
        </div>
    </div>
</div>

<div id="download-toast-stack" class="download-toast-stack" aria-live="polite" aria-atomic="true"></div>

<div class="card import-upload-card border-0 mb-4">
    <div class="import-upload-card__header border-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <span class="import-upload-card__eyebrow">Import Data</span>
                <h5 class="card-title font-weight-bold text-dark mb-1" style="font-size: 1.25rem;">
                    <i class="fas fa-cloud-upload-alt text-primary mr-2"></i> Upload Data Report
                </h5>
                <p class="import-upload-card__subtitle mb-0" style="font-size: 0.9rem;">Unggah file sesuai format report yang ditentukan.</p>
            </div>
        </div>
    </div>

    <form id="form-import" method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" data-prepare-preview-url="" data-upload-limits-url="{{ route('import.upload-limits') }}" data-chunked-upload="" data-chunk-init-url="" data-chunk-upload-url="" data-chunk-finalize-url="">
        @csrf

        <div class="card-body import-upload-card__body">
            <div class="import-report-hero mb-4">
                <div class="import-report-hero__head">
                    <div>
                        <span class="import-upload-card__eyebrow">Alur Import</span>
                        <h6 class="mb-1 font-weight-bold text-dark">Pilih report, cek format, lalu unggah file</h6>
                        <p class="mb-0 text-muted">Halaman akan menyesuaikan jenis file dan kebutuhan periode secara otomatis.</p>
                    </div>
                </div>
                <div class="import-report-steps">
                    <div class="import-report-step is-active">
                        <span class="import-report-step__num">1</span>
                        <div>
                            <strong>Pilih Report</strong>
                            <small>Tentukan sumber data</small>
                        </div>
                    </div>
                    <div class="import-report-step">
                        <span class="import-report-step__num">2</span>
                        <div>
                            <strong>Siapkan File</strong>
                            <small>Format menyesuaikan report</small>
                        </div>
                    </div>
                    <div class="import-report-step">
                        <span class="import-report-step__num">3</span>
                        <div>
                            <strong>Upload & Proses</strong>
                            <small>Preview muncul setelah valid</small>
                        </div>
                    </div>
                </div>
                <div class="import-report-summary">
                    <div class="import-report-summary__item">
                        <span class="import-report-summary__label">Report</span>
                        <strong id="summary-report-name">Belum dipilih</strong>
                    </div>
                    <div class="import-report-summary__item">
                        <span class="import-report-summary__label">Format Aktif</span>
                        <strong id="summary-upload-type">RAR</strong>
                    </div>
                    <div class="import-report-summary__item">
                        <span class="import-report-summary__label">Periode</span>
                        <strong id="summary-periode-status">Otomatis</strong>
                    </div>
                    <div class="import-report-summary__item">
                        <span class="import-report-summary__label">Target</span>
                        <strong id="summary-target-table">-</strong>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="font-weight-bold text-dark">Pilih Report</label>
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

            <div id="form-periode" class="form-group" style="display: none;">
                <label id="periode-label" class="font-weight-bold text-dark">
                    <i class="fas fa-calendar-alt text-primary mr-1"></i> Periode
                </label>
                <input type="date" id="periode_input" name="periode" class="form-control">
                <small id="periode-help" class="text-muted mt-2 d-block">Wajib untuk report tertentu.</small>
            </div>

            <div id="form-kanca" class="form-group" style="display: none;">
                <label id="kanca-label" class="font-weight-bold text-dark">
                    <i class="fas fa-building text-primary mr-1"></i> Kanca
                </label>
                <select id="kanca_input" name="kanca_manual" class="form-control">
                    <option value="">-- Pilih Kanca --</option>
                    <option value="KC Madiun">KC Madiun</option>
                    <option value="KC Magetan">KC Magetan</option>
                    <option value="KC Ngawi">KC Ngawi</option>
                    <option value="KC Ponorogo">KC Ponorogo</option>
                </select>
                <small id="kanca-help" class="text-muted mt-2 d-block">Nilai ini akan dipakai untuk kolom `kanca` saat import RKA.</small>
            </div>

            <div id="form-rar" class="form-group">
                <label class="font-weight-bold text-dark">Upload File (.rar)</label>
                <div class="custom-file">
                    <input type="file" id="file_rar" name="file" class="custom-file-input" accept=".rar" required>
                    <label class="custom-file-label" for="file_rar">Pilih file .rar...</label>
                </div>

                <div class="col-lg-6">
                    <div class="import-step-panel">
                        <span class="import-step-badge import-step-badge--step2">2. Upload File</span>

                        <div id="form-rar" class="form-group mb-0">
                            <label class="font-weight-bold text-dark mb-2" style="font-size: 0.95rem;">Upload File (.rar)</label>
                            <div class="custom-file">
                                <input type="file" id="file_rar" name="file" class="custom-file-input" accept=".rar" required>
                                <label class="custom-file-label" for="file_rar">Pilih file .rar...</label>
                            </div>
                            <small class="text-muted mt-2 d-block" style="font-size: 0.85rem;">File akan diproses otomatis.</small>
                        </div>

                        <div id="form-excel" class="form-group mb-0" style="display: none;">
                            <label id="excel-label" class="font-weight-bold mb-2" style="color: #10b981; font-size: 0.95rem;"><i class="fas fa-file-excel mr-1"></i> Upload Excel (.xlsx, .xls)</label>
                            <input type="file" id="file_excel" name="file" class="form-control shadow-sm" accept=".xlsx,.xls" style="height: auto; padding: 0.75rem; border: 1px solid #10b981;">
                            <small class="text-muted mt-2 d-block" id="excel-help" style="font-size: 0.85rem;">Format .xlsx dan .xls didukung.</small>
                            <small class="text-muted mt-1 d-block" id="upload-limit-hint" style="font-size: 0.85rem;">Format .xlsx dan .xls didukung.</small>
                        </div>

                        <div id="form-csv" class="form-group mb-0" style="display: none;">
                            <label id="csv-label" class="font-weight-bold mb-2" style="color: #0ea5e9; font-size: 0.95rem;"><i class="fas fa-file-csv mr-1"></i> Upload CSV (.csv, .txt)</label>
                            <input type="file" id="file_csv" name="file" class="form-control shadow-sm" accept=".csv,.txt" style="height: auto; padding: 0.75rem; border: 1px solid #0ea5e9;">
                            <small id="csv-help" class="text-muted mt-2 d-block" style="font-size: 0.85rem;">Gunakan CSV sesuai report.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div id="import-dropzone" class="import-dropzone" tabindex="0" role="button" aria-label="Area upload file">
                <div class="import-dropzone__icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="import-dropzone__content">
                    <div id="import-dropzone-title" class="import-dropzone__title">Tarik file ke sini atau klik untuk memilih</div>
                    <div id="import-dropzone-text" class="import-dropzone__text">Input aktif akan otomatis mengikuti report yang dipilih.</div>
                </div>
            </div>

            <div id="import-file-preview" class="import-file-preview d-none">
                <div class="import-file-preview__icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="import-file-preview__body">
                    <div id="import-file-name" class="import-file-preview__name">-</div>
                    <div class="import-file-preview__meta">
                        <span id="import-file-size">0 KB</span>
                        <span id="import-file-extension">-</span>
                    </div>
                </div>
                <button type="button" id="import-file-clear" class="import-file-preview__clear">Ganti File</button>
            </div>
        </div>

        <div class="import-upload-card__footer border-0">
            <button type="submit" id="btn-submit" class="btn btn-primary font-weight-bold import-upload-card__submit">
                <i class="fas fa-file-archive mr-2"></i> Upload Sekarang
            </button>
        </div>
    </form>
</div>

@if(!empty($showReportManagementPanel))
<style>
    .rm-panel { border-radius: 26px; overflow: hidden; box-shadow: 0 28px 60px -40px rgba(15,23,42,0.32); border: 1px solid rgba(226,232,240,0.8); background: #fff; margin-top: 2rem; margin-bottom: 2rem; }
    .rm-header { padding: 1.45rem 1.5rem 1rem; border-bottom: 1px solid rgba(226,232,240,0.5); background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.09), transparent 28%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); }
    .rm-eyebrow { display: inline-block; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #1d4ed8; background: rgba(37,99,235,0.08); margin-bottom: 0.55rem; }
    .rm-card-inner { padding: 1.75rem; border-radius: 20px; background: #ffffff; border: 1px solid rgba(148, 163, 184, 0.2); height: 100%; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .rm-card-inner:hover { box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
    .rm-card-inner--sync { justify-content: space-between; }
    .rm-card-eyebrow { display: inline-flex; align-items: center; padding: 0.45rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 1.25rem; align-self: flex-start; }
    .rm-card-eyebrow--source { background: rgba(15, 23, 42, 0.04); color: #475569; }
    .rm-card-eyebrow--sync { background: rgba(37,99,235,0.08); color: #2563eb; }
    .rm-stat-card { padding: 1.5rem; border-radius: 20px; background: #fff; border: 1px solid rgba(148, 163, 184, 0.2); display: flex; align-items: center; gap: 1rem; height: 100%; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .rm-stat-card:hover { box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
    .rm-stat-icon { width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
    .rm-action-bar { padding: 1.25rem 1.5rem; border-radius: 20px; background: #ffffff; border: 1px solid rgba(148, 163, 184, 0.2); display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); }
    .rm-btn { min-height: 48px; border-radius: 16px; padding: 0 1.75rem; font-weight: 700; font-size: 0.95rem; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
    .rm-btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; box-shadow: 0 14px 24px -14px rgba(37,99,235,0.5); }
    .rm-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 18px 28px -14px rgba(37,99,235,0.6); color: #fff; }
    .rm-btn-danger-outline { border: 2px solid #ef4444; color: #ef4444; background: transparent; }
    .rm-btn-danger-outline:hover { background: #fef2f2; color: #dc2626; transform: translateY(-1px); }
    .rm-btn-secondary-outline { border: 2px solid #2563eb; color: #2563eb; background: transparent; width: 100%; margin-top: 1rem; }
    .rm-btn-secondary-outline:hover { background: rgba(37,99,235,0.05); color: #1d4ed8; transform: translateY(-1px); }
    .rm-progress { padding: 1.5rem; border-radius: 20px; background: #ffffff; border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); }
    .rm-progress-badge { display: inline-flex; padding: 0.35rem 0.85rem; border-radius: 999px; background: #dcfce7; color: #059669; font-size: 0.8rem; font-weight: 800; border: 1px solid #a7f3d0; text-transform: uppercase; letter-spacing: 0.05em; }
    .rm-progress-bar { height: 12px; border-radius: 999px; background: #e2e8f0; margin-bottom: 0.75rem; overflow: hidden; box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08); }
    .rm-progress-fill { height: 100%; background: linear-gradient(90deg, #10b981, #059669); width: 100%; transition: width 0.3s ease; }
</style>

<div class="rm-panel" id="report-management-card"
     data-fetch-url="{{ route('import.report-management.data') }}"
     data-delete-url="{{ route('import.report-management.delete') }}">
    <div class="rm-header">
        <span class="rm-eyebrow">Kelola Report</span>
        <h5 class="font-weight-bold text-dark mb-1" style="font-size: 1.25rem;">
            <i class="fas fa-database text-danger mr-2"></i> Kelola Data Report
        </h5>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">Filter report lalu hapus data yang tidak diperlukan.</p>
    </div>
    
    <div class="card-body" style="padding: 1.75rem;">
        <!-- Baris 1: Grid Proporsional -->
        <div class="row mb-4 align-items-stretch">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="rm-card-inner">
                    <span class="rm-card-eyebrow rm-card-eyebrow--source">Sumber Data</span>
                    <label class="font-weight-bold text-dark mb-2" style="font-size: 0.95rem;" for="management-report-select">Pilih Report</label>
                    <select id="management-report-select" class="form-control select2">
                        <option value="">-- Pilih Report --</option>
                        @foreach($reports as $report)
                            <option value="{{ $report->id_report }}" @if(strpos(strtolower($report->nama_report), 'simpanan multipn') !== false) selected @endif>{{ $report->nama_report }} ({{ $report->table_name }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="rm-card-inner rm-card-inner--sync">
                    <div>
                        <span class="rm-card-eyebrow rm-card-eyebrow--sync">Sinkronisasi Snapshot</span>
                        <div class="custom-control custom-switch mb-1 mt-2">
                            <input type="checkbox" class="custom-control-input" id="management-rebuild-force">
                            <label class="custom-control-label font-weight-bold text-dark" for="management-rebuild-force" style="cursor: pointer; font-size: 0.95rem; padding-top: 2px;">Mulai dari awal</label>
                        </div>
                        <p class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.5;">Bangun ulang seluruh snapshot untuk semua report dengan mode penuh bila diperlukan.</p>
                    </div>
                    <button type="button" id="btn-management-rebuild" class="rm-btn rm-btn-secondary-outline">
                        <i class="fas fa-sync-alt mr-2"></i> Refresh Snapshot
                    </button>
                </div>
            </div>
        </div>

        <!-- Baris 2: Kartu Statistik Grid -->
        <div class="row mb-4 align-items-stretch">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="rm-stat-card">
                    <div class="rm-stat-icon" style="background: rgba(37,99,235,0.1); color: #2563eb;"><i class="fas fa-file-alt"></i></div>
                    <div class="d-flex flex-column">
                        <small style="color: #64748b; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 0.25rem;">Report Aktif</small>
                        <strong style="color: #0f172a; font-size: 1.1rem; font-weight: 700; line-height: 1.2;">Simpanan MultiPN</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="rm-stat-card">
                    <div class="rm-stat-icon" style="background: rgba(14,165,233,0.1); color: #0ea5e9;"><i class="fas fa-users"></i></div>
                    <div class="d-flex flex-column">
                        <small style="color: #64748b; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 0.25rem;">Jumlah Grup</small>
                        <strong style="color: #0f172a; font-size: 1.1rem; font-weight: 700; line-height: 1.2;">12</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="rm-stat-card">
                    <div class="rm-stat-icon" style="background: rgba(16,185,129,0.1); color: #10b981;"><i class="fas fa-table"></i></div>
                    <div class="d-flex flex-column">
                        <small style="color: #64748b; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 0.25rem;">Grand Total Baris</small>
                        <strong style="color: #0f172a; font-size: 1.1rem; font-weight: 700; line-height: 1.2;">7.800.927</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Baris 3: Tombol Aksi yang Diselaraskan -->
        <div class="rm-action-bar">
            <button type="button" id="btn-management-filter" class="rm-btn rm-btn-primary">
                <i class="fas fa-filter mr-2"></i> Tampilkan Data
            </button>
            <button type="button" id="btn-management-deduplicate" class="rm-btn rm-btn-danger-outline">
                <i class="fas fa-clone mr-2"></i> Hapus Duplikat
            </button>
        </div>

        <!-- Baris 4: Progress Block yang Terintegrasi -->
        <div class="rm-progress">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #2563eb; margin-bottom: 0.25rem;">Realtime Progress</div>
                    <div style="font-size: 1rem; font-weight: 700; color: #0f172a;">Memuat data report management...</div>
                </div>
                <div class="rm-progress-badge">SELESAI</div>
            </div>
            <div class="rm-progress-bar">
                <div class="rm-progress-fill"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">100%</div>
                <div style="color: #64748b; font-size: 0.85rem; font-weight: 600;">4 / 4 tahap</div>
            </div>
            <div style="color: #059669; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.25rem;">Data report management selesai dimuat.</div>
            <div style="color: #64748b; font-size: 0.85rem; font-weight: 500;">12 grup, 7.800.927 baris sumber, halaman 1 siap ditampilkan.</div>
        </div>

        <div class="table-responsive mt-3 d-none">
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
                    message: text.replace(speedMatch[0], '').trim(),
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
        const formImport = document.getElementById('form-import');
        const btnSubmit = document.getElementById('btn-submit');
        const btnDownloadTemplate = document.getElementById('btn-download-template');
        const downloadTemplateSelect = document.getElementById('download-template-select');
        const inputRar = document.getElementById('file_rar');
        const inputExcel = document.getElementById('file_excel');
        const inputCsv = document.getElementById('file_csv');
        const periodeInput = document.getElementById('periode_input');
        const kancaInput = document.getElementById('kanca_input');
        const periodeLabel = document.getElementById('periode-label');
        const periodeHelp = document.getElementById('periode-help');
        const kancaLabel = document.getElementById('kanca-label');
        const kancaHelp = document.getElementById('kanca-help');
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
            const deletedRows = Number(payload.deleted_rows || 0);
            const recoveredWarning = payload.status === 'failed' && deletedRows > 0;
            const outcomeStatus = recoveredWarning ? 'warning' : payload.status;
            if ((!response.ok && !recoveredWarning) || !['success', 'warning', 'completed'].includes(outcomeStatus)) {
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

            applyButtonState(
                isCsvLike ? 'csv' : 'excel',
                isCsvLike
                    ? '<i class="fas fa-file-csv"></i> Upload CSV'
                    : '<i class="fas fa-file-excel"></i> Upload Excel'
            );
            updateReportSummary();
        }
        function toggleForm() {
            const { reportName, tableName, importController } = getSelectedReportMeta();
            const isDailyLoan = reportName.includes('daily loan');
            const isSimpanan = reportName.includes('simpanan multipn');
            const isPerformancePis = reportName.includes('performance pis per produk');
            const isCasaBrilink = reportName.includes('casa brilink');
            const isReportPh = reportName.includes('report nominatif rekening pinjaman ph');
            const isInputRekanan = tableName === 'input_rekanan';
            const isBodBoc = tableName === 'bod_boc';
            const isRka = tableName === 'rka';
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
            }
            configureKancaInput({ visible: false });

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
                csvHelp.textContent = 'CSV tetap didukung. File Excel akan distage dulu ke CSV lalu masuk ke jalur bulk import yang sama.';
                applyButtonState('csv', '<i class="fas fa-file-upload"></i> Upload File');
                configurePeriodeInput({ visible: false });
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
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
                configureKancaInput({ visible: false });
                updateReportSummary();
                updateFileSelectionUI();
                return;
            }

            if (usesGenericExcelFlow) {
                formExcel.style.display = 'block';
                inputExcel.disabled = false;
                inputExcel.required = true;
                inputExcel.setAttribute('accept', '.xlsx,.xls');
                formImport.action = "{{ route('import.excel.upload') }}";
                formImport.dataset.preparePreviewUrl = "{{ route('import.excel.prepare-preview') }}";

                if (excelLabel) {
                    excelLabel.innerHTML = '<i class="fas fa-file-excel mr-1"></i> Upload File Excel (.xlsx, .xls)';
                }

                if (excelHelp) {
                    excelHelp.textContent = describeUploadLimitMessage(null);
                }

                configurePeriodeInput(buildManualPeriodeOptions({
                    visible: false,
                    required: false,
                    type: 'date',
                    label: 'Periode',
                    help: 'Pilih periode manual sesuai kebutuhan report.',
                }));
                configureKancaInput({
                    visible: isRka,
                    required: isRka,
                    label: 'Kanca',
                    help: 'Pilih Kanca untuk mengisi kolom `kanca` pada semua baris import RKA.',
                });
                applyButtonState('excel', '<i class="fas fa-file-excel"></i> Upload Excel');
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
                    : 'Proses Import';
            const descText = hasAsyncPreview
                ? (isPolarsFlow ? 'File sedang diproses menuju fase Polars.' : 'File sedang diproses untuk preview.')
                : (isPolarsFlow ? 'File sedang diproses menuju fase Polars.' : 'File sedang diproses.');
            const initialPhaseText = isPolarsFlow ? 'Fase Polars dimulai...' : 'Menyiapkan proses...';
            const initialStatusText = isPolarsFlow ? 'Menyiapkan batch Polars...' : 'Menunggu proses...';

            const progressHtml = `
                <div class="swal-import-shell">
                    <div class="swal-import-head">
                        <span class="swal-import-badge"><i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang diproses</span>
                        <div class="swal-import-title">${titleText}</div>
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
        box-shadow: 0 18px 40px -24px rgba(15, 23, 42, .45);
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
        border-left: 4px solid #16a34a;
    }

    .download-toast--error {
        border-left: 4px solid #dc2626;
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
        background: #16a34a;
    }

    .download-toast--error .download-toast__icon {
        background: #dc2626;
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

    .import-template-banner {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 1.4rem 1.5rem;
        background:
            radial-gradient(circle at top right, rgba(251, 146, 60, 0.18), transparent 28%),
            radial-gradient(circle at bottom left, rgba(17, 24, 39, 0.22), transparent 30%),
            linear-gradient(135deg, #111827 0%, #1f2937 52%, #2f3b52 100%);
        border: 1px solid rgba(249, 115, 22, 0.18);
        box-shadow: 0 28px 54px -34px rgba(17, 24, 39, 0.68);
    }

    .import-template-banner__glow {
        position: absolute;
        top: -42px;
        right: -28px;
        width: 160px;
        height: 160px;
        border-radius: 999px;
        background: rgba(249, 115, 22, 0.22);
        filter: blur(10px);
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
        color: #fdba74;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(251, 146, 60, 0.18);
    }

    .import-template-banner__title {
        color: #ffffff;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 0.35rem;
    }

    .import-template-banner__text {
        color: rgba(255, 255, 255, 0.78);
        line-height: 1.65;
        max-width: 620px;
    }

    .import-template-banner__meta {
        display: flex;
        gap: 0.65rem;
        flex-wrap: wrap;
        margin-top: 0.9rem;
    }

    .import-template-banner__meta span {
        display: inline-flex;
        align-items: center;
        padding: 0.45rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.82rem;
        font-weight: 600;
    }

    .import-template-banner__brand {
        display: flex;
        align-items: center;
        padding-right: 1rem;
    }

    .import-template-banner__brand-card {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        min-width: 290px;
        max-width: 340px;
        padding: 0.95rem 1rem;
        border-radius: 20px;
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.04));
        border: 1px solid rgba(251, 146, 60, 0.14);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
    }

    .import-template-banner__logo {
        width: 58px;
        height: 58px;
        padding: 0.2rem;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.96);
        flex: 0 0 auto;
    }

    .import-template-banner__brand-label {
        color: #fdba74;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        margin-bottom: 0.18rem;
    }

    .import-template-banner__brand-text {
        color: rgba(255, 255, 255, 0.84);
        font-size: 0.9rem;
        line-height: 1.45;
        font-weight: 600;
    }

    .import-template-banner__button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 0.8rem 1.25rem;
        border-radius: 16px;
        border: 0;
        background: linear-gradient(135deg, #fb923c, #ea580c);
        color: #ffffff;
        font-weight: 700;
        box-shadow: 0 18px 30px -20px rgba(249, 115, 22, 0.48);
        transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    .import-template-banner__download-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .import-template-banner__select-wrapper {
        min-width: 280px;
        flex: 1 1 auto;
        max-width: 400px;
    }

    .import-template-banner .select2-container--bootstrap4 .select2-selection--single {
        min-height: 48px;
        border-radius: 16px !important;
        border: 1px solid rgba(251, 146, 60, 0.22) !important;
        background: rgba(255, 255, 255, 0.95) !important;
        display: flex;
        align-items: center;
        box-shadow: 0 4px 12px -8px rgba(17, 24, 39, 0.28) !important;
    }

    .import-template-banner .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        color: #1f2937;
        font-weight: 600;
        padding-left: 1.25rem;
    }

    .import-template-banner .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: 46px;
        right: 8px;
    }

    .import-template-banner .select2-container--bootstrap4.select2-container--focus .select2-selection--single {
        border-color: #fb923c !important;
        box-shadow: 0 0 0 0.25rem rgba(251, 146, 60, 0.16) !important;
    }

    .import-template-banner__button:hover {
        color: #ffffff;
        text-decoration: none;
        transform: translateY(-1px);
        box-shadow: 0 22px 34px -20px rgba(249, 115, 22, 0.55);
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
            radial-gradient(circle at top left, rgba(249, 115, 22, 0.1), transparent 24%),
            radial-gradient(circle at top right, rgba(17, 24, 39, 0.06), transparent 26%),
            linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .import-upload-card__eyebrow {
        color: #c2410c;
        background: rgba(251, 146, 60, 0.12);
    }

    .import-upload-card__subtitle {
        color: #64748b;
        max-width: 620px;
        line-height: 1.6;
    }

    .import-upload-card__body {
        padding: 1.5rem;
    }

    .import-report-hero {
        padding: 1.15rem;
        border-radius: 22px;
        background:
            radial-gradient(circle at top right, rgba(251, 146, 60, 0.12), transparent 30%),
            linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
        border: 1px solid rgba(251, 146, 60, 0.14);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    .import-report-steps {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
        margin-top: 1rem;
    }

    .import-report-step {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.95rem 1rem;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.18);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .import-report-step.is-active,
    .import-report-step:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 30px -26px rgba(17, 24, 39, 0.3);
    }

    .import-report-step__num {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #fb923c, #ea580c);
        color: #ffffff;
        font-weight: 800;
        flex: 0 0 auto;
    }

    .import-report-step strong,
    .import-report-step small {
        display: block;
    }

    .import-report-step small {
        margin-top: 0.16rem;
        color: #64748b;
        line-height: 1.45;
    }

    .import-report-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        margin-top: 1rem;
    }

    .import-report-summary__item {
        padding: 0.95rem 1rem;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.14);
    }

    .import-report-summary__label {
        display: block;
        margin-bottom: 0.26rem;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .import-report-summary__item strong {
        display: block;
        color: #111827;
        font-size: 0.94rem;
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

    .import-upload-card__submit.btn-primary,
    .import-upload-card__submit.btn-success,
    .import-upload-card__submit.btn-info {
        border: 0;
        background: linear-gradient(135deg, #fb923c, #ea580c);
        box-shadow: 0 18px 34px -22px rgba(249, 115, 22, 0.5);
    }

    .import-upload-card__submit.btn-primary:hover,
    .import-upload-card__submit.btn-success:hover,
    .import-upload-card__submit.btn-info:hover,
    .import-upload-card__submit.btn-primary:focus,
    .import-upload-card__submit.btn-success:focus,
    .import-upload-card__submit.btn-info:focus {
        background: linear-gradient(135deg, #f97316, #c2410c);
        box-shadow: 0 22px 38px -24px rgba(249, 115, 22, 0.55);
    }

    .import-dropzone {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 1.15rem;
        padding: 1.1rem 1.15rem;
        border-radius: 22px;
        border: 1.5px dashed rgba(251, 146, 60, 0.34);
        background: linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
        cursor: pointer;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .import-dropzone:hover,
    .import-dropzone:focus,
    .import-dropzone.is-dragover {
        outline: none;
        transform: translateY(-2px);
        border-color: rgba(234, 88, 12, 0.6);
        box-shadow: 0 20px 34px -28px rgba(249, 115, 22, 0.42);
    }

    .import-dropzone.has-file {
        border-style: solid;
        background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
    }

    .import-dropzone__icon {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #111827, #2f3b52);
        color: #fb923c;
        font-size: 1.45rem;
        flex: 0 0 auto;
    }

    .import-dropzone__title {
        color: #111827;
        font-size: 1rem;
        font-weight: 800;
        margin-bottom: 0.18rem;
    }

    .import-dropzone__text {
        color: #64748b;
        line-height: 1.55;
    }

    .import-file-preview {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        margin-top: 1rem;
        padding: 0.95rem 1rem;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid rgba(251, 146, 60, 0.16);
        box-shadow: 0 16px 28px -24px rgba(17, 24, 39, 0.24);
    }

    .import-file-preview__icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: rgba(251, 146, 60, 0.12);
        color: #c2410c;
        font-size: 1.1rem;
        flex: 0 0 auto;
    }

    .import-file-preview__body {
        flex: 1 1 auto;
        min-width: 0;
    }

    .import-file-preview__name {
        color: #111827;
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
        background: rgba(17, 24, 39, 0.08);
        color: #111827;
        font-weight: 700;
        transition: background 0.2s ease;
    }

    .import-file-preview__clear:hover {
        background: rgba(17, 24, 39, 0.14);
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
        background: linear-gradient(135deg, #fb923c, #ea580c);
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
        background: rgba(251, 146, 60, 0.12);
        color: #c2410c;
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
        color: #c2410c;
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
        background: linear-gradient(90deg, #111827 0%, #fb923c 48%, #ea580c 100%);
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
        color: #c2410c;
        font-weight: 700;
        letter-spacing: 0.02em;
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
        color: #c2410c;
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
        .import-template-banner__brand {
            width: 100%;
            padding-right: 0;
        }

        .import-template-banner__brand-card,
        .import-dropzone,
        .import-file-preview {
            width: 100%;
        }

        .import-dropzone {
            flex-direction: column;
            text-align: center;
        }

        .import-report-steps,
        .import-report-summary {
            grid-template-columns: 1fr;
        }

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
