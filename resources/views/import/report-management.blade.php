@extends('layouts.admin')

@section('title', 'Kelola Report')

@section('content')
<div class="report-management-hero mb-4">
    <div class="report-management-hero__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="pr-3">
            <span class="report-management-hero__eyebrow">Kelola Report</span>
            <div class="report-management-hero__title"><i class="fas fa-layer-group mr-2"></i> Kelola Data Report</div>
            <p class="report-management-hero__text mb-0">Pilih report, lalu hapus data per grup dengan mudah.</p>
        </div>
        <div class="report-management-hero__badge mt-3 mt-md-0 status-pulse"><i class="fas fa-shield-alt mr-2 text-primary"></i> Guard Aktif</div>
    </div>
</div>

<div class="card shadow-sm border-0 import-upload-card" id="report-management-card"
     data-fetch-url="{{ route('import.report-management.data') }}"
     data-load-start-url="{{ route('import.report-management.load') }}"
     data-load-status-url-template="{{ route('import.report-management.load.status', ['loadId' => '__LOAD_ID__']) }}"
     data-rebuild-url="{{ route('import.report-management.rebuild') }}"
     data-rebuild-status-url-template="{{ route('import.report-management.rebuild.status', ['rebuildId' => '__REBUILD_ID__']) }}"
     data-recover-url="{{ route('import.report-management.recover') }}"
     data-recover-status-url-template="{{ route('import.report-management.recover.status', ['recoveryId' => '__RECOVERY_ID__']) }}"
     data-delete-url="{{ route('import.report-management.delete') }}"
     data-duplicate-url="{{ route('import.report-management.duplicates') }}"
     data-delete-process-url-template="{{ route('import.report-management.delete.process', ['deleteId' => '__DELETE_ID__']) }}"
     data-delete-status-url-template="{{ route('import.report-management.delete.status', ['deleteId' => '__DELETE_ID__']) }}"
     data-delete-cancel-url-template="{{ route('import.report-management.delete.cancel', ['deleteId' => '__DELETE_ID__']) }}"
     data-force-sync-url="{{ route('import.report-management.force-sync') }}"
     data-force-sync-status-url-template="{{ route('import.report-management.force-sync.status', ['syncId' => '__SYNC_ID__']) }}">
    <div class="card-header bg-transparent border-0 px-4 pt-4 pb-0">
        <h5 class="font-weight-bold text-dark mb-0" style="font-size: 1.25rem;">
            <i class="fas fa-database text-primary mr-2"></i> Data per Grup
        </h5>
    </div>
    <div class="card-body p-4">
        <!-- Control Panel (4 Columns) -->
        <div class="row mb-4 align-items-end">
            <!-- 1. Pilihan Report -->
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <div class="form-group mb-0">
                    <label class="font-weight-bold text-dark mb-2" for="management-report-select" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Report Utama</label>
                    <select id="management-report-select" class="form-control select2" style="border-radius: 12px;">
                        <option value="">-- Pilih Report --</option>
                        @foreach($reports as $report)
                            <option value="{{ $report->id_report }}" data-table-name="{{ $report->table_name }}">{{ $report->nama_report }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- 2. Data Recovery -->
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <div class="form-group mb-0">
                    <label class="font-weight-bold text-dark mb-2" for="management-backup-select" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Recovery SQL</label>
                    <div class="d-flex" style="gap: 8px;">
                        <select id="management-backup-select" class="form-control flex-grow-1" style="border-radius: 12px; border: 1px solid #cbd5e1;">
                            <option value="">-- File Backup --</option>
                            @foreach($backupFiles as $backup)
                                <option value="{{ $backup['path'] }}">{{ $backup['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" id="btn-management-recover" class="btn btn-outline-success" style="border-radius: 12px; padding: 0.5rem 1rem;" title="Jalankan Recovery" disabled>
                            <i class="fas fa-life-ring"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3. Sinkronisasi Manual -->
            <div class="col-lg-3 col-md-6 mb-3 mb-md-0">
                <div class="form-group mb-0">
                    <label class="font-weight-bold text-dark mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Sync Spesifik</label>
                    <div class="d-flex" style="gap: 8px;">
                        <input type="text" id="management-force-sync-period" class="form-control flex-grow-1" placeholder="YYYY-MM-DD" style="border-radius: 12px; border: 1px solid #cbd5e1;">
                        <button type="button" id="btn-management-force-sync" class="btn btn-outline-warning" style="border-radius: 12px; padding: 0.5rem 1rem;" title="Sync Sekarang">
                            <i class="fas fa-bolt"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 4. Pembersihan / Rebuild -->
            <div class="col-lg-3 col-md-6">
                <div class="form-group mb-0 d-flex flex-column h-100 justify-content-end">
                    <label class="font-weight-bold text-dark mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Pembaruan Penuh</label>
                    <div class="d-flex align-items-center" style="gap: 12px; min-height: 38px;">
                        <div class="custom-control custom-switch m-0 flex-grow-1">
                            <input type="checkbox" class="custom-control-input" id="management-rebuild-force">
                            <label class="custom-control-label text-dark font-weight-bold" for="management-rebuild-force" style="cursor: pointer; padding-top: 2px;">Mode Full</label>
                        </div>
                        <button type="button" id="btn-management-rebuild" class="btn btn-outline-primary" style="border-radius: 12px; padding: 0.5rem 1rem;" title="Pembaruan Snapshot">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary & Actions Bar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between p-3 mb-4" style="background: #f8fafc; border-radius: 16px; border: 1px solid #f1f5f9;">
            <!-- Stats -->
            <div class="d-flex flex-wrap align-items-center" style="gap: 1.5rem;">
                <div>
                    <small class="text-muted font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Report</small>
                    <div id="management-summary-report" class="font-weight-bold text-dark" style="font-size: 0.95rem;">-</div>
                </div>
                <div class="border-left d-none d-md-block" style="height: 30px; border-color: #e2e8f0 !important;"></div>
                <div>
                    <small class="text-muted font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Grup</small>
                    <div id="management-summary-groups" class="font-weight-bold text-info" style="font-size: 0.95rem;">0</div>
                </div>
                <div class="border-left d-none d-md-block" style="height: 30px; border-color: #e2e8f0 !important;"></div>
                <div>
                    <small class="text-muted font-weight-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Baris</small>
                    <div id="management-summary-rows" class="font-weight-bold text-success" style="font-size: 0.95rem;">0</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex align-items-center mt-3 mt-md-0" style="gap: 8px;">
                <button type="button" id="btn-management-filter" class="btn btn-primary" style="border-radius: 12px; font-weight: 600; padding: 0.55rem 1.25rem; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);">
                    <i class="fas fa-filter mr-2"></i> Tampilkan
                </button>
                <button type="button" id="btn-management-deduplicate" class="btn btn-outline-danger" style="border-radius: 12px; font-weight: 600; padding: 0.55rem 1.25rem;" disabled>
                    <i class="fas fa-clone mr-2"></i> Deduplikasi
                </button>
            </div>
        </div>

        <div id="management-notice" class="report-management-notice d-none mb-4"></div>

        <div id="management-load-progress" class="report-management-load-card d-none mb-4" aria-live="polite">
            <div class="report-management-load-card__header">
                <div>
                    <div class="report-management-load-card__eyebrow">Realtime Progress</div>
                    <div id="management-load-title" class="report-management-load-card__title">Memuat data report management...</div>
                </div>
                <div id="management-load-stage" class="report-management-load-card__stage">Queued</div>
            </div>
            <div class="report-management-progress">
                <div id="management-load-progress-bar" class="progress-bar report-management-progress__bar report-management-progress__bar--indeterminate" role="progressbar" style="width: 0%;"></div>
            </div>
            <div class="report-management-load-card__meta-row">
                <div id="management-load-percent" class="report-management-progress__value">0%</div>
                <div id="management-load-units" class="report-management-load-card__units">0 / 4 tahap</div>
            </div>
            <div id="management-load-text" class="report-management-progress__text mt-2">Menunggu worker memulai proses...</div>
            <div id="management-load-meta" class="report-management-progress__meta mt-1"></div>
        </div>

        <div id="management-recovery-progress" class="report-management-load-card d-none mb-4" aria-live="polite">
            <div class="report-management-load-card__header">
                <div>
                    <div class="report-management-load-card__eyebrow">Recovery Progress</div>
                    <div id="management-recovery-title" class="report-management-load-card__title">Recovery backup report sedang berjalan...</div>
                </div>
                <div id="management-recovery-stage" class="report-management-load-card__stage">Queued</div>
            </div>
            <div class="report-management-progress">
                <div id="management-recovery-progress-bar" class="progress-bar report-management-progress__bar report-management-progress__bar--indeterminate" role="progressbar" style="width: 0%;"></div>
            </div>
            <div class="report-management-load-card__meta-row">
                <div id="management-recovery-percent" class="report-management-progress__value">0%</div>
                <div id="management-recovery-units" class="report-management-load-card__units">0 / 6 tahap</div>
            </div>
            <div id="management-recovery-text" class="report-management-progress__text mt-2">Menunggu worker memulai proses recovery...</div>
            <div id="management-recovery-meta" class="report-management-progress__meta mt-1"></div>
        </div>

        <div class="report-management-bulkbar mb-3">
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="management-select-all" disabled>
                <label class="form-check-label" for="management-select-all">Pilih Semua di Halaman</label>
            </div>
            <div class="report-management-bulkbar__hint">
                Klik baris untuk centang cepat. Setiap header periode punya pilihan "Pilih semua periode ini".
            </div>
        </div>

        <div class="table-responsive mt-4" style="border-radius: 12px; border: 1px solid #e2e8f0;">
            <table class="table table-hover mb-0" style="border-collapse: collapse;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th class="text-center" style="width: 5%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;"><i class="far fa-check-square"></i></th>
                        <th style="width: 45%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Kanca</th>
                        <th class="text-right" style="width: 25%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Jumlah Baris</th>
                        <th class="text-center" style="width: 25%; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="management-table-body">
                    <tr><td colspan="4" class="text-center text-muted py-4">Pilih report lalu klik "Tampilkan Data".</td></tr>
                </tbody>
            </table>
        </div>

        <div id="management-pagination" class="report-management-pagination d-none"></div>

        <div class="report-management-selection-toast-shell">
            <div id="management-selection-toast" class="report-management-selection-toast d-none animate-reveal" aria-live="polite">
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
@include('import.partials.report-management-scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reportManagementCard = document.getElementById('report-management-card');
        const managementReportSelect = document.getElementById('management-report-select');
        const btnManagementFilter = document.getElementById('btn-management-filter');
        const btnManagementDeduplicate = document.getElementById('btn-management-deduplicate');
        const btnManagementRebuild = document.getElementById('btn-management-rebuild');
        const btnManagementRecover = document.getElementById('btn-management-recover');
        const btnManagementForceSync = document.getElementById('btn-management-force-sync');
        const managementForceSyncPeriod = document.getElementById('management-force-sync-period');
        const managementBackupSelect = document.getElementById('management-backup-select');
        const managementRebuildForce = document.getElementById('management-rebuild-force');
        const managementRecoveryProgress = document.getElementById('management-recovery-progress');
        const managementRecoveryTitle = document.getElementById('management-recovery-title');
        const managementRecoveryStage = document.getElementById('management-recovery-stage');
        const managementRecoveryProgressBar = document.getElementById('management-recovery-progress-bar');
        const managementRecoveryPercent = document.getElementById('management-recovery-percent');
        const managementRecoveryUnits = document.getElementById('management-recovery-units');
        const managementRecoveryText = document.getElementById('management-recovery-text');
        const managementRecoveryMeta = document.getElementById('management-recovery-meta');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        if (!reportManagementCard || !managementReportSelect) {
            console.error('[Recovery] Report management card or select not found');
            return;
        }

        // Validate critical elements
        if (!managementBackupSelect) {
            console.error('[Recovery] Backup select element not found');
        }
        if (!btnManagementRecover) {
            console.error('[Recovery] Recovery button not found');
        }

        function selectedTableName() {
            const selectedOption = managementReportSelect.selectedOptions?.[0];
            return String(selectedOption?.dataset?.tableName || '').trim();
        }

        function formatManagementNumber(value) {
            return Number(value || 0).toLocaleString('id-ID');
        }

        function formatManagementBytes(value) {
            const bytes = Number(value || 0);
            if (!Number.isFinite(bytes) || bytes <= 0) {
                return '0 B';
            }

            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            const scaled = bytes / Math.pow(1024, unitIndex);

            return `${scaled.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
        }

        function humanizeRecoveryStage(stage) {
            const lookup = {
                queued: 'Queued',
                validating: 'Validasi',
                extracting_backup: 'Ekstraksi',
                importing_backup: 'Import SQL',
                swapping_data: 'Tukar Tabel',
                syncing: 'Sinkronisasi',
                cleanup: 'Cleanup',
                completed: 'Selesai',
                failed: 'Gagal',
            };

            return lookup[String(stage || '').trim().toLowerCase()] || 'Recovery';
        }

        function syncExtraActionState() {
            if (btnManagementDeduplicate) {
                const canDeduplicate = managementReportSelect.value && selectedTableName() === 'simpanan_multipn';
                btnManagementDeduplicate.disabled = !canDeduplicate;
                btnManagementDeduplicate.title = canDeduplicate
                    ? 'Hapus duplikat exact-match untuk Simpanan MultiPN.'
                    : 'Hapus duplikat hanya tersedia untuk Simpanan MultiPN.';
            }

            if (btnManagementRebuild) {
                btnManagementRebuild.title = managementRebuildForce?.checked
                    ? 'Bangun ulang seluruh snapshot report dari awal.'
                    : 'Refresh snapshot seluruh report tanpa memaksa rebuild penuh.';
            }

            if (btnManagementRecover) {
                // ✅ FIXED: Check both report and backup selection explicitly
                const hasReportSelected = Boolean(managementReportSelect?.value);
                const hasBackupSelected = Boolean(managementBackupSelect?.value);
                const canRecover = hasReportSelected && hasBackupSelected;
                
                // ✅ Enable/disable button based on selections
                btnManagementRecover.disabled = !canRecover;
                
                // ✅ Debug logging
                const prevState = btnManagementRecover.title;
                btnManagementRecover.title = canRecover
                    ? 'Pulihkan tabel report dari file backup yang dipilih.'
                    : 'Pilih report dan file backup terlebih dahulu.';
                
                // Log state changes for debugging
                if (canRecover && prevState !== btnManagementRecover.title) {
                    console.log('[Recovery Debug]', {
                        event: 'button_state_changed',
                        enabled: !btnManagementRecover.disabled,
                        hasReportSelected: hasReportSelected,
                        hasBackupSelected: hasBackupSelected,
                        reportValue: managementReportSelect?.value,
                        backupValue: managementBackupSelect?.value
                    });
                }
            }
        }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload || {}),
            });

            let data = {};
            try {
                data = await response.json();
            } catch (_) {
                data = {};
            }

            if (!response.ok && data.status !== 'warning') {
                throw new Error(data.message || 'Request gagal diproses.');
            }

            return data;
        }

        function templateUrl(template, value) {
            return String(template || '').replace('__REBUILD_ID__', encodeURIComponent(value || ''));
        }

        async function refreshCurrentGrid() {
            if (btnManagementFilter) {
                btnManagementFilter.click();
            } else {
                window.location.reload();
            }
        }

        async function handleDeduplicate() {
            if (!btnManagementDeduplicate || btnManagementDeduplicate.disabled) {
                return;
            }

            const idReport = Number(managementReportSelect.value || 0);
            if (!idReport) {
                return;
            }

            const confirmation = await Swal.fire({
                icon: 'warning',
                title: 'Hapus Duplikat Simpanan MultiPN?',
                text: 'Aksi ini akan menghapus baris duplikat exact-match dari tabel Simpanan MultiPN.',
                showCancelButton: true,
                confirmButtonText: 'Lanjutkan',
                cancelButtonText: 'Batal',
            });

            if (!confirmation.isConfirmed) {
                return;
            }

            const payload = await postJson(reportManagementCard.dataset.duplicateUrl, { id_report: idReport });
            if (payload.status === 'error') {
                throw new Error(payload.message || 'Gagal menghapus duplikat.');
            }

            await Swal.fire({
                icon: payload.status === 'warning' ? 'warning' : 'success',
                title: payload.status === 'warning' ? 'Selesai dengan Catatan' : 'Berhasil',
                text: payload.message || 'Duplikat berhasil diproses.',
            });

            await refreshCurrentGrid();
        }

        async function handleRebuild() {
            if (!btnManagementRebuild) {
                return;
            }

            const confirmation = await Swal.fire({
                icon: 'question',
                title: managementRebuildForce?.checked ? 'Rebuild dari Awal?' : 'Refresh Snapshot?',
                text: managementRebuildForce?.checked
                    ? 'Seluruh snapshot akan dibangun ulang dari awal.'
                    : 'Snapshot seluruh report akan direfresh tanpa force rebuild penuh.',
                showCancelButton: true,
                confirmButtonText: 'Lanjutkan',
                cancelButtonText: 'Batal',
            });

            if (!confirmation.isConfirmed) {
                return;
            }

            const payload = await postJson(reportManagementCard.dataset.rebuildUrl, {
                force_rebuild: !!managementRebuildForce?.checked,
            });

            if (payload.status === 'error') {
                throw new Error(payload.message || 'Gagal menjadwalkan rebuild.');
            }

            if (!payload.rebuild_id) {
                await Swal.fire({
                    icon: payload.status === 'warning' ? 'warning' : 'success',
                    title: payload.status === 'warning' ? 'Dalam Antrean' : 'Berhasil',
                    text: payload.message || 'Rebuild snapshot sudah dijadwalkan.',
                });
                await refreshCurrentGrid();
                return;
            }

            Swal.fire({
                title: 'Rebuild Snapshot Berjalan',
                html: `<div class="mb-2">${escapeHtmlSafe(payload.message || 'Rebuild snapshot diantrekan...')}</div><div class="progress" style="height: 12px;"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%;"></div></div><div class="mt-2 text-muted" style="font-size: 0.85rem;">0% selesai</div>`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
            });

            const statusUrl = templateUrl(reportManagementCard.dataset.rebuildStatusUrlTemplate, payload.rebuild_id);
            const finalState = await pollRebuildStatus(statusUrl);
            Swal.close();

            const finalStatus = String(finalState?.status || '').toLowerCase();
            if (finalStatus === 'error') {
                throw new Error(finalState.message || 'Progress rebuild gagal dipantau.');
            }

            await Swal.fire({
                icon: finalStatus === 'completed' ? 'success' : (finalStatus === 'failed' ? 'error' : 'warning'),
                title: finalStatus === 'completed' ? 'Rebuild Selesai' : (finalStatus === 'failed' ? 'Rebuild Gagal' : 'Rebuild Belum Selesai'),
                text: finalState?.message || 'Rebuild snapshot selesai diproses.',
            });

            await refreshCurrentGrid();
        }

        function updateRecoveryProgress(payload) {
            if (!managementRecoveryProgress) {
                return;
            }

            const percent = Math.max(0, Math.min(100, Number(payload?.progress_percent || 0)));
            const completedUnits = Math.max(0, Number(payload?.completed_units || 0));
            const totalUnits = Math.max(1, Number(payload?.total_units || 6));
            const stage = String(payload?.stage || 'queued');
            const status = String(payload?.status || '');
            const isIndeterminate = ['queued', 'extracting_backup'].includes(stage) && !['completed', 'failed'].includes(status) && percent < 100;

            managementRecoveryProgress.classList.remove('d-none');
            if (managementRecoveryTitle) {
                managementRecoveryTitle.textContent = status === 'completed'
                    ? 'Recovery backup selesai'
                    : 'Recovery backup report sedang berjalan...';
            }
            if (managementRecoveryStage) managementRecoveryStage.textContent = humanizeRecoveryStage(stage);
            if (managementRecoveryProgressBar) {
                managementRecoveryProgressBar.style.width = percent + '%';
                managementRecoveryProgressBar.classList.toggle('report-management-progress__bar--indeterminate', isIndeterminate);
            }
            if (managementRecoveryPercent) managementRecoveryPercent.textContent = `${percent}%`;
            if (managementRecoveryUnits) managementRecoveryUnits.textContent = `${formatManagementNumber(completedUnits)} / ${formatManagementNumber(totalUnits)} tahap`;
            if (managementRecoveryText) managementRecoveryText.textContent = payload?.message || 'Recovery backup sedang berjalan...';
            if (managementRecoveryMeta) {
                if (status === 'completed') {
                    const result = payload?.result || {};
                    managementRecoveryMeta.textContent = `${formatManagementNumber(result.restored_rows || 0)} baris dipulihkan ke tabel ${result.table_name || '-'}.`;
                } else if (status === 'failed') {
                    managementRecoveryMeta.textContent = payload?.error || 'Recovery backup gagal.';
                } else if (payload?.bytes_read !== undefined && payload?.total_bytes !== undefined) {
                    managementRecoveryMeta.textContent = `Memindai ${formatManagementBytes(payload.bytes_read)} dari ${formatManagementBytes(payload.total_bytes)} backup.`;
                } else if (payload?.bytes_written !== undefined && payload?.total_bytes !== undefined) {
                    managementRecoveryMeta.textContent = `Mengimpor ${formatManagementBytes(payload.bytes_written)} dari ${formatManagementBytes(payload.total_bytes)} staging SQL.`;
                } else {
                    managementRecoveryMeta.textContent = 'Recovery dilakukan per tabel agar lebih aman dibanding restore full database.';
                }
            }
        }

        async function pollRecoveryStatus(statusUrl) {
            if (!statusUrl) {
                return null;
            }

            let attempt = 0;
            let consecutiveErrors = 0;
            const maxAttempts = 14400; // ~4 hours at base rate
            const maxConsecutiveErrors = 3;
            const baseDelayMs = 500; // Start with 500ms
            const maxDelayMs = 5000; // Cap at 5 seconds

            while (attempt < maxAttempts) {
                try {
                    const response = await fetch(statusUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: AbortSignal.timeout(10000), // 10 second timeout per request
                    });

                    // Handle non-OK responses
                    if (!response.ok) {
                        consecutiveErrors++;
                        if (consecutiveErrors >= maxConsecutiveErrors) {
                            return {
                                status: 'error',
                                message: `Recovery status polling failed (HTTP ${response.status}). Jalankan ulang recovery.`,
                                error: `HTTP ${response.status} after ${consecutiveErrors} attempts`,
                            };
                        }
                        // Continue polling with delay
                        const delayMs = Math.min(baseDelayMs * (1 + attempt / 100), maxDelayMs);
                        await new Promise((resolve) => setTimeout(resolve, delayMs));
                        attempt++;
                        continue;
                    }

                    const state = await response.json().catch(() => ({}));
                    
                    // Reset error counter on successful response
                    if (state && typeof state === 'object') {
                        consecutiveErrors = 0;
                    }

                    updateRecoveryProgress(state);

                    const status = String(state?.status || '').toLowerCase();
                    if (['completed', 'failed', 'warning', 'error'].includes(status)) {
                        return state;
                    }

                    // Calculate exponential backoff delay
                    // Start at 500ms, increase progressively, cap at 5 seconds
                    const progress = (attempt + 1) / maxAttempts;
                    const delayMs = Math.min(
                        baseDelayMs + Math.floor(progress * progress * 4500),
                        maxDelayMs
                    );

                    await new Promise((resolve) => setTimeout(resolve, delayMs));
                } catch (error) {
                    consecutiveErrors++;
                    
                    // Special handling for timeout
                    if (error instanceof DOMException && error.name === 'AbortError') {
                        if (consecutiveErrors >= maxConsecutiveErrors) {
                            return {
                                status: 'error',
                                message: 'Recovery status polling timeout. Koneksi terputus. Jalankan ulang recovery.',
                                error: 'Request timeout',
                            };
                        }
                    } else if (error instanceof TypeError && error.message.includes('Failed to fetch')) {
                        // Network error
                        if (consecutiveErrors >= maxConsecutiveErrors) {
                            return {
                                status: 'error',
                                message: 'Network error saat polling recovery status. Periksa koneksi internet dan jalankan ulang.',
                                error: 'Network error',
                            };
                        }
                    }

                    // Continue polling with delay
                    const delayMs = Math.min(baseDelayMs * Math.pow(1.5, consecutiveErrors), maxDelayMs);
                    await new Promise((resolve) => setTimeout(resolve, delayMs));
                }

                attempt++;
            }

            return {
                status: 'warning',
                message: 'Recovery backup masih berjalan di background setelah 4 jam polling. Cek status ulang nanti.',
            };
        }

        async function handleRecovery() {
            // ✅ Validate all required elements and values
            if (!btnManagementRecover) {
                console.error('[Recovery] Recovery button not found');
                return;
            }

            // Get current values
            const reportId = managementReportSelect?.value;
            const backupPath = managementBackupSelect?.value;

            // ✅ Check if both selections are made
            if (!reportId || !backupPath) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Input Tidak Lengkap',
                    text: 'Silakan pilih report dan file backup sebelum menjalankan recovery.',
                });
                console.warn('[Recovery] Missing input:', { hasReport: !!reportId, hasBackup: !!backupPath });
                return;
            }

            // ✅ Get human-readable labels
            const backupLabel = managementBackupSelect.selectedOptions?.[0]?.text || 'backup terpilih';
            const reportLabel = managementReportSelect.selectedOptions?.[0]?.text || 'report terpilih';
            
            console.log('[Recovery] Starting recovery with:', { reportId, backupPath, reportLabel, backupLabel });
            
            const confirmation = await Swal.fire({
                icon: 'warning',
                title: 'Recover Data Report?',
                html: `
                    <p>Data pada <b>${reportLabel}</b> akan diganti sepenuhnya dari backup:</p>
                    <p><b>${backupLabel}</b></p>
                    <p style="color: #dc3545; font-size: 0.9rem;">⚠️ Aksi ini tidak bisa dibatalkan. Pastikan backup dipilih dengan benar.</p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Ya, Lanjutkan Recovery',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545',
            });

            if (!confirmation.isConfirmed) {
                console.log('[Recovery] User cancelled recovery');
                return;
            }

            try {
                updateRecoveryProgress({
                    status: 'queued',
                    stage: 'queued',
                    progress_percent: 0,
                    completed_units: 0,
                    total_units: 6,
                    message: 'Menjadwalkan recovery backup report...',
                });

                const payload = await postJson(reportManagementCard.dataset.recoverUrl, {
                    id_report: Number(managementReportSelect.value || 0),
                    backup_path: String(managementBackupSelect.value || ''),
                });

                if (payload.status === 'error') {
                    throw new Error(payload.message || 'Gagal memulai recovery backup.');
                }

                const recoveryId = String(payload.recovery_id || '').trim();
                if (!recoveryId || !/^[a-f0-9\-]{36}$/i.test(recoveryId)) {
                    throw new Error('Recovery ID tidak valid. Server response error.');
                }

                const statusUrl = String(reportManagementCard.dataset.recoverStatusUrlTemplate || '')
                    .replace('__RECOVERY_ID__', encodeURIComponent(recoveryId));
                
                if (!statusUrl) {
                    throw new Error('Status URL template tidak dikonfigurasi dengan benar.');
                }

                const finalState = await pollRecoveryStatus(statusUrl);

                if (!finalState) {
                    throw new Error('Tidak ada response dari server saat polling status.');
                }

                if (String(finalState?.status || '').toLowerCase() === 'failed') {
                    throw new Error(finalState?.error || finalState?.message || 'Recovery backup gagal dijalankan.');
                }

                await Swal.fire({
                    icon: String(finalState?.status || '').toLowerCase() === 'warning' ? 'warning' : 'success',
                    title: String(finalState?.status || '').toLowerCase() === 'warning' ? 'Recovery Berlanjut' : 'Recovery Selesai',
                    text: finalState?.message || 'Recovery backup selesai diproses.',
                    confirmButtonColor: '#28a745',
                });

                await refreshCurrentGrid();
            } catch (error) {
                const errorMessage = error instanceof Error ? error.message : 'Error tidak diketahui';
                
                await Swal.fire({
                    icon: 'error',
                    title: 'Recovery Gagal',
                    text: errorMessage,
                    confirmButtonColor: '#dc3545',
                });

                console.error('Recovery error:', error);
                
                // Reset progress display on error
                if (managementRecoveryProgress) {
                    managementRecoveryProgress.classList.add('d-none');
                }
            }
        }

        async function pollRebuildStatus(statusUrl) {
            if (!statusUrl) {
                return null;
            }

            // Rebuild snapshot bisa berjalan 5-30 menit untuk dataset besar. Kita poll
            // sampai 30 menit (1800s) dengan backoff yang ramah server, dan tetap
            // tampilkan progress kepada user supaya tidak terasa hang.
            const maxRuntimeMs = 30 * 60 * 1000;
            const baseDelayMs = 1500;
            const maxDelayMs = 5000;
            const maxConsecutiveErrors = 3;

            const startedAt = Date.now();
            let consecutiveErrors = 0;
            let lastShownPercent = -1;

            while (Date.now() - startedAt < maxRuntimeMs) {
                try {
                    const response = await fetch(statusUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: AbortSignal.timeout(10000),
                    });

                    if (!response.ok) {
                        consecutiveErrors++;
                        if (consecutiveErrors >= maxConsecutiveErrors) {
                            return { status: 'error', message: `Polling status rebuild gagal (HTTP ${response.status}). Cek status di Monitoring Antrean Job.` };
                        }
                    } else {
                        consecutiveErrors = 0;
                        const state = await response.json().catch(() => ({}));
                        const status = String(state.status || '').toLowerCase();
                        const percent = Math.max(0, Math.min(100, Number(state.progress_percent || 0)));
                        if (percent !== lastShownPercent && typeof Swal !== 'undefined' && Swal.isVisible()) {
                            lastShownPercent = percent;
                            Swal.update({
                                title: 'Rebuild Snapshot Berjalan',
                                html: `<div class="mb-2">${escapeHtmlSafe(state.message || 'Memproses snapshot...')}</div><div class="progress" style="height: 12px;"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: ${percent}%;"></div></div><div class="mt-2 text-muted" style="font-size: 0.85rem;">${percent}% selesai${state.current_report_label ? ' — ' + escapeHtmlSafe(state.current_report_label) : ''}</div>`,
                            });
                        }
                        if (['completed', 'failed', 'warning', 'error'].includes(status)) {
                            return state;
                        }
                    }
                } catch (error) {
                    consecutiveErrors++;
                    if (consecutiveErrors >= maxConsecutiveErrors) {
                        return { status: 'error', message: 'Polling status rebuild putus koneksi. Cek status di Monitoring Antrean Job.' };
                    }
                }

                const elapsedRatio = (Date.now() - startedAt) / maxRuntimeMs;
                const delayMs = Math.min(baseDelayMs + Math.floor(elapsedRatio * (maxDelayMs - baseDelayMs)), maxDelayMs);
                await new Promise((resolve) => setTimeout(resolve, delayMs));
            }

            return { status: 'warning', message: 'Rebuild snapshot masih berjalan di background setelah 30 menit polling. Cek status di Monitoring Antrean Job.' };
        }

        function escapeHtmlSafe(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }

        async function pollForceSyncStatus(statusUrl) {
            if (!statusUrl) return null;

            Swal.fire({
                title: 'Sinkronisasi Berjalan',
                html: `
                    <div class="mb-3" id="force-sync-status-text">Memeriksa status...</div>
                    <div class="progress report-management-progress" style="height: 12px;">
                        <div id="force-sync-progress-bar" class="progress-bar report-management-progress__bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div>
                    </div>
                    <div class="mt-2 text-muted" id="force-sync-progress-meta" style="font-size: 0.85rem;">0% (0/6 tabel selesai)</div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Force-sync untuk periode bisa berjalan 5-30 menit. Poll sampai 30 menit
            // dengan adaptive backoff dan tolerance error sementara.
            const maxRuntimeMs = 30 * 60 * 1000;
            const baseDelayMs = 1500;
            const maxDelayMs = 5000;
            const maxConsecutiveErrors = 3;

            const startedAt = Date.now();
            let consecutiveErrors = 0;

            while (Date.now() - startedAt < maxRuntimeMs) {
                try {
                    const response = await fetch(statusUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: AbortSignal.timeout(10000),
                    });

                    if (!response.ok) {
                        consecutiveErrors++;
                        if (consecutiveErrors >= maxConsecutiveErrors) {
                            return { status: 'error', message: `Polling sync gagal (HTTP ${response.status}).` };
                        }
                    } else {
                        consecutiveErrors = 0;
                        const state = await response.json().catch(() => ({}));
                        const status = String(state.status || '').toLowerCase();
                        const percent = Math.max(0, Math.min(100, Number(state.progress || 0)));

                        const pBar = document.getElementById('force-sync-progress-bar');
                        if (pBar) {
                            pBar.style.width = percent + '%';
                            if (['completed', 'failed', 'error'].includes(status)) {
                                pBar.classList.remove('progress-bar-animated');
                                pBar.classList.remove('progress-bar-striped');
                                if (status === 'completed') pBar.classList.add('bg-success');
                                if (['failed', 'error'].includes(status)) pBar.classList.add('bg-danger');
                            }
                        }

                        const pText = document.getElementById('force-sync-status-text');
                        if (pText && state.message) {
                            pText.innerText = state.message;
                        }

                        const pMeta = document.getElementById('force-sync-progress-meta');
                        if (pMeta) {
                            pMeta.innerText = `${percent}% (${state.completed_tables || 0}/${state.total_tables || 6} tabel selesai)`;
                        }

                        if (['completed', 'failed', 'warning', 'error'].includes(status)) {
                            return state;
                        }
                    }
                } catch (e) {
                    consecutiveErrors++;
                    if (consecutiveErrors >= maxConsecutiveErrors) {
                        return { status: 'error', message: 'Polling sync putus koneksi.' };
                    }
                }

                const elapsedRatio = (Date.now() - startedAt) / maxRuntimeMs;
                const delayMs = Math.min(baseDelayMs + Math.floor(elapsedRatio * (maxDelayMs - baseDelayMs)), maxDelayMs);
                await new Promise((resolve) => setTimeout(resolve, delayMs));
            }

            return { status: 'warning', message: 'Sync masih berjalan di background setelah 30 menit polling. Cek Monitoring Antrean Job.' };
        }

        async function handleForceSync() {
            if (!managementForceSyncPeriod || !managementForceSyncPeriod.value) {
                await Swal.fire({ icon: 'warning', title: 'Input Tidak Lengkap', text: 'Silakan isi periode (YYYY-MM-DD) terlebih dahulu.' });
                return;
            }

            const period = managementForceSyncPeriod.value;
            const confirmation = await Swal.fire({
                icon: 'question',
                title: 'Sinkronisasi Spesifik?',
                html: `Jalankan snapshot force sync untuk periode <b>${period}</b>?`,
                showCancelButton: true,
                confirmButtonText: 'Ya, Sinkronisasi',
                cancelButtonText: 'Batal',
            });

            if (!confirmation.isConfirmed) return;

            const payload = await postJson(reportManagementCard.dataset.forceSyncUrl, {
                period: period,
            });

            if (payload.status === 'error') {
                throw new Error(payload.message || 'Gagal memulai sinkronisasi.');
            }

            if (payload.sync_id) {
                const statusUrl = templateUrl(reportManagementCard.dataset.forceSyncStatusUrlTemplate, payload.sync_id).replace('__REBUILD_ID__', encodeURIComponent(payload.sync_id)).replace('__SYNC_ID__', encodeURIComponent(payload.sync_id));
                const finalState = await pollForceSyncStatus(statusUrl);
                
                await Swal.fire({
                    icon: ['failed', 'error'].includes(finalState?.status) ? 'error' : (finalState?.status === 'warning' ? 'warning' : 'success'),
                    title: ['failed', 'error'].includes(finalState?.status) ? 'Sync Gagal' : (finalState?.status === 'warning' ? 'Selesai dengan Catatan' : 'Berhasil'),
                    text: finalState?.message || 'Proses force sync selesai.',
                });
            } else {
                await Swal.fire({ icon: 'success', title: 'Berhasil', text: payload.message || 'Sinkronisasi dijalankan.' });
            }

            await refreshCurrentGrid();
        }

        // ✅ FIXED: Initialize button state immediately
        syncExtraActionState();

        // ✅ FIXED: Attach event listeners with explicit null checks (not optional chaining)
        if (managementReportSelect) {
            managementReportSelect.addEventListener('change', function () {
                if (managementRecoveryProgress) {
                    managementRecoveryProgress.classList.add('d-none');
                }
                syncExtraActionState();
            });
        }

        // ✅ CRITICAL FIX: This is the main issue - backup select listener must be attached!
        if (managementBackupSelect) {
            managementBackupSelect.addEventListener('change', function () {
                console.debug('[Recovery] Backup selection changed to:', this.value);
                syncExtraActionState();
            });
        } else {
            console.error('[Recovery] managementBackupSelect not found - cannot attach change listener!');
        }

        if (managementRebuildForce) {
            managementRebuildForce.addEventListener('change', syncExtraActionState);
        }
        if (btnManagementDeduplicate) {
            btnManagementDeduplicate.addEventListener('click', async function () {
                btnManagementDeduplicate.disabled = true;
                try {
                    await handleDeduplicate();
                } catch (error) {
                    await Swal.fire({ icon: 'error', title: 'Hapus Duplikat Gagal', text: error.message || 'Terjadi kesalahan saat memproses duplikat.' });
                } finally {
                    syncExtraActionState();
                }
            });
        }

        if (btnManagementRebuild) {
            btnManagementRebuild.addEventListener('click', async function () {
                btnManagementRebuild.disabled = true;
                try {
                    await handleRebuild();
                } catch (error) {
                    await Swal.fire({ icon: 'error', title: 'Rebuild Gagal', text: error.message || 'Terjadi kesalahan saat menjadwalkan rebuild.' });
                } finally {
                    syncExtraActionState();
                    btnManagementRebuild.disabled = false;
                }
            });
        }

        if (btnManagementForceSync) {
            btnManagementForceSync.addEventListener('click', async function () {
                btnManagementForceSync.disabled = true;
                try {
                    await handleForceSync();
                } catch (error) {
                    await Swal.fire({ icon: 'error', title: 'Sync Gagal', text: error.message || 'Terjadi kesalahan saat memulai sinkronisasi.' });
                } finally {
                    syncExtraActionState();
                    btnManagementForceSync.disabled = false;
                }
            });
        }

        if (btnManagementRecover) {
            btnManagementRecover.addEventListener('click', async function () {
                console.log('[Recovery] Recovery button clicked');
                btnManagementRecover.disabled = true;
                try {
                    await handleRecovery();
                } catch (error) {
                    console.error('[Recovery] Recovery error:', error);
                    await Swal.fire({ icon: 'error', title: 'Recovery Gagal', text: error.message || 'Terjadi kesalahan saat memproses recovery backup.' });
                } finally {
                    syncExtraActionState();
                }
            });
        } else {
            console.error('[Recovery] Recovery button not found - cannot attach click handler!');
        }
    });
</script>
@endsection

@section('styles')
<style>
    .report-management-hero { position:relative; overflow:hidden; border-radius:16px; padding:1.75rem 2rem; background: radial-gradient(circle at top right, rgba(37,99,235,0.1) 0%, transparent 40%), linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%); border:1px solid rgba(37,99,235,0.15); box-shadow:0 8px 16px -4px rgba(29,78,216,0.08); }
    .report-management-hero__glow { position:absolute; top:-50px; right:-50px; width:200px; height:200px; border-radius:50%; background:rgba(56,189,248,0.2); filter:blur(30px); }
    .report-management-hero__eyebrow { display:inline-block; margin-bottom:0.75rem; padding:0.35rem 0.85rem; border-radius:999px; font-size:0.75rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#1d4ed8; background:rgba(255,255,255,0.8); border:1px solid rgba(37,99,235,0.15); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .report-management-hero__title { color:#0f172a; font-size:1.5rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:0.5rem; }
    .report-management-hero__text { color:#475569; font-size: 0.95rem; line-height:1.6; max-width:650px; }
    .report-management-hero__badge { display:inline-flex; align-items:center; padding:0.6rem 1.25rem; border-radius:12px; background:#ffffff; border:1px solid rgba(226,232,240,0.8); color:#334155; font-size: 0.9rem; font-weight:600; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); }

    .import-upload-card { border-radius:16px; overflow:hidden; box-shadow:0 10px 25px -5px rgba(15,23,42,0.08) !important; border: 1px solid rgba(226,232,240, 0.8) !important; }
    .import-upload-card__header { padding:1.5rem 1.75rem 1rem; border-bottom: 1px solid rgba(226,232,240,0.5); background:#ffffff; }
    .import-upload-card__eyebrow { display:inline-block; margin-bottom:0.5rem; padding:0.35rem 0.85rem; border-radius:999px; font-size:0.75rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#2563eb; background:rgba(37,99,235,0.08); }
    .import-upload-card__subtitle { color:#64748b; max-width:700px; line-height:1.6; font-size: 0.9rem; }
    .import-upload-card__body { position:relative; padding:1.75rem 1.75rem 7rem; }

    .report-management-top-shell { padding:1.25rem; border-radius:16px; background:#f8fafc; border:1px solid rgba(226,232,240,0.8); }
    .report-management-top-grid { align-items:stretch; }
    .report-management-field-panel { display:flex; flex-direction:column; justify-content:center; padding:1.5rem; border-radius:12px; background:#ffffff; border:1px solid rgba(226,232,240,0.8); box-shadow:0 4px 6px -1px rgba(0,0,0,0.02); }
    .report-management-field-panel__eyebrow { display:inline-flex; align-items:center; align-self:flex-start; margin-bottom:0.75rem; padding:0.35rem 0.85rem; border-radius:999px; background:rgba(15,23,42,0.04); color:#475569; font-size:0.75rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; }
    .report-management-field-panel__label { margin-bottom:0.75rem; color:#0f172a; font-size:0.95rem; font-weight:600; }

    .report-management-rebuild-panel, .report-management-recover-panel { display:flex; flex-direction:column; justify-content:space-between; padding:1.5rem; border-radius:12px; background:#ffffff; border:1px solid rgba(226,232,240,0.8); box-shadow:0 4px 6px -1px rgba(0,0,0,0.02); }
    .report-management-progress__meta { display:block; color:#94a3b8; font-size: 0.85rem; font-weight:500; min-height:1.2rem; }

    .report-management-notice { padding:1.25rem 1.5rem; border-radius:12px; background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; font-size:0.9rem; font-weight:600; line-height:1.5; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); }

    .report-management-load-card { padding:1.5rem; border-radius:16px; background:#ffffff; border:1px solid rgba(226,232,240,0.8); box-shadow:0 10px 15px -3px rgba(0,0,0,0.05); overflow:hidden; position:relative; }
    .report-management-load-card::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:#2563eb; }
    .report-management-load-card__header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.25rem; }
    .report-management-load-card__eyebrow { font-size:0.7rem; font-weight:800; letter-spacing:0.05em; text-transform:uppercase; color:#64748b; margin-bottom:0.25rem; }
    .report-management-load-card__title { font-size:1.1rem; font-weight:700; color:#0f172a; line-height:1.2; }
    .report-management-load-card__stage { padding:0.35rem 0.85rem; border-radius:999px; background:#eff6ff; color:#2563eb; font-size:0.75rem; font-weight:700; letter-spacing:0.02em; border:1px solid #bfdbfe; }
    
    .report-management-progress { height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; margin:1.25rem 0 0.75rem; }
    .report-management-progress__bar { background:linear-gradient(90deg, #2563eb 0%, #60a5fa 100%); transition:width 0.4s cubic-bezier(0.4, 0, 0.2, 1); border-radius:999px; }
    .report-management-progress__bar--indeterminate { background: linear-gradient(90deg, #2563eb 25%, #60a5fa 50%, #2563eb 75%); background-size: 200% 100%; animation: reportManagementProgressShift 1.5s infinite linear; }

    .report-management-load-card__meta-row { display:flex; justify-content:space-between; align-items:center; }
    .report-management-progress__value { font-size:1.25rem; font-weight:800; color:#0f172a; }
    .report-management-load-card__units { font-size:0.85rem; font-weight:600; color:#64748b; }
    .report-management-progress__text { color:#475569; font-size:0.9rem; font-weight:500; }

    .report-management-bulkbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; padding:1rem 1.25rem; border-radius:12px; background:#f8fafc; border:1px solid rgba(226,232,240,0.8); }
    .report-management-bulkbar .form-check-label { font-weight:600; color:#1e293b; cursor: pointer; }
    .report-management-bulkbar__hint { font-size:0.85rem; font-weight:500; color:#64748b; }

    .report-management-table-wrap { border:1px solid rgba(226,232,240,0.8); border-radius:12px; overflow:hidden; background:#fff; box-shadow:0 2px 4px -1px rgba(0,0,0,0.02); }
    .report-management-table thead th { background:#f8fafc; border-bottom:1px solid rgba(226,232,240,0.8); color:#475569; font-size:0.8rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; padding:1rem 1.25rem; }
    .report-management-table tbody td { padding:1rem 1.25rem; border-top:1px solid rgba(226,232,240,0.5); vertical-align:middle; color:#334155; font-size: 0.95rem; }
    .report-management-col-check { width:60px; }
    .report-management-primary { color:#0f172a; font-weight:600; }
    .report-management-count { display:inline-flex; align-items:center; justify-content:center; min-width:60px; padding:0.35rem 0.75rem; border-radius:999px; background:#eff6ff; color:#2563eb; font-size: 0.85rem; font-weight:700; border: 1px solid #bfdbfe; }

    .report-management-period-row td { padding:0.75rem 1.25rem !important; background:#f8fafc !important; border-top:1px solid rgba(226,232,240,0.8) !important; border-bottom:1px solid rgba(226,232,240,0.8) !important; }
    .report-management-period-card { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
    .report-management-period-card__title { font-size:0.95rem; font-weight:700; color:#1e293b; }
    .report-management-period-card__meta { font-size:0.85rem; font-weight:600; color:#64748b; }
    .report-management-period-card__toggle { display:inline-flex; align-items:center; gap:0.5rem; margin:0; padding:0.4rem 0.85rem; border-radius:999px; background:#ffffff; border: 1px solid rgba(226,232,240,0.8); color:#475569; font-size: 0.85rem; font-weight:600; cursor:pointer; transition: all 0.2s; }
    .report-management-period-card__toggle:hover { background: #f1f5f9; color: #0f172a; }

    .management-data-row { cursor:pointer; transition:background-color 0.15s ease; }
    .management-data-row.is-selected { background-color: #eff6ff; }
    .management-data-row:hover { background-color: #f8fafc; }

    .report-management-pagination { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-top:1.5rem; padding: 0 0.5rem; }
    .report-management-pagination__meta { font-size:0.9rem; font-weight:500; color:#64748b; }
    .report-management-pagination__actions { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; }
    .report-management-page-btn { display:inline-flex; align-items:center; justify-content:center; min-width:36px; height:36px; padding:0 0.75rem; border:1px solid rgba(226,232,240,0.8); border-radius:8px; background:#ffffff; color:#475569; font-weight:600; font-size: 0.9rem; transition: all 0.2s; }
    .report-management-page-btn:hover:not(:disabled) { background: #f8fafc; border-color: #cbd5e1; }
    .report-management-page-btn.is-active { background:#2563eb; border-color:#2563eb; color:#ffffff; }
    .report-management-page-btn:disabled { opacity:0.5; cursor:not-allowed; }

    .report-management-selection-toast-shell { position:fixed; right:2rem; bottom:2rem; z-index:1080; display:flex; justify-content:flex-end; align-items:flex-end; width:min(400px,calc(100vw - 4rem)); max-width:calc(100vw - 4rem); pointer-events:none; }
    .report-management-selection-toast { position:relative; display:flex; align-items:center; justify-content:space-between; gap:1rem; width:100%; padding:1.25rem; border-radius:16px; background:#1e293b; color:#ffffff; box-shadow:0 10px 25px -5px rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); pointer-events:auto; }
    .report-management-selection-toast__body { min-width:0; }
    .report-management-selection-toast__eyebrow { font-size:0.75rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#94a3b8; margin-bottom: 0.25rem; }
    .report-management-selection-toast__text { font-size:1rem; font-weight:700; line-height:1.4; color: #f8fafc; }
    .report-management-selection-toast__subtext { font-size:0.85rem; font-weight:500; color:#cbd5e1; }
    .report-management-selection-toast__actions { display:flex; align-items:center; gap:0.5rem; }
    .report-management-selection-toast__btn { border-radius:10px; font-weight:600; padding:0.6rem 1rem; font-size: 0.9rem; transition: all 0.2s; }
    .report-management-selection-toast__btn--ghost { background:transparent; border:1px solid rgba(255,255,255,0.2); color:#f8fafc; }
    .report-management-selection-toast__btn--ghost:hover:not(:disabled) { background:rgba(255,255,255,0.1); }
    .report-management-selection-toast__btn--danger { background:#ef4444; border:1px solid #ef4444; color:#ffffff; }
    .report-management-selection-toast__btn--danger:hover:not(:disabled) { background:#dc2626; }

    @keyframes reportManagementProgressShift { 0% { background-position:200% 0; } 100% { background-position:-200% 0; } }

    .swal-modern-popup { border-radius:16px; padding:1.5rem; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); }
    .swal-modern-title { color:#0f172a; font-weight:700; font-size: 1.25rem; }
    .swal-modern-html { color:#475569; font-size:0.95rem; line-height:1.6; }
    .swal-modern-confirm, .swal-modern-cancel { border-radius:10px; font-weight:600; padding:0.75rem 1.5rem; font-size: 0.95rem; }
    .swal-modern-confirm { background:#2563eb; color:#ffffff; }
    .swal-modern-cancel { background:#f1f5f9; color:#475569; }

    @media (max-width:991.98px) {
        .report-management-top-grid > div { margin-bottom: 1rem; }
        .report-management-top-grid > div:last-child { margin-bottom: 0; }
    }

    @media (max-width:767.98px) {
        .report-management-hero, .import-upload-card__header { padding-left:1.25rem; padding-right:1.25rem; }
        .import-upload-card__body { padding:1.25rem 1.25rem 6rem; }
        .report-management-stat { flex-direction: row; gap: 1rem; }
        .report-management-stat__icon { width: 40px; height: 40px; font-size: 1rem; }
        .report-management-action-bar { flex-direction: column; align-items: stretch; gap: 1rem; }
        .report-management-action-bar__group { flex-direction: column; align-items: stretch; }
        .report-management-filter-btn { width: 100%; min-width: 0; }
        .report-management-selection-toast-shell { left:1rem; right:1rem; bottom:1rem; width:calc(100vw - 2rem); max-width:calc(100vw - 2rem); }
        .report-management-selection-toast { flex-direction:column; align-items: stretch; text-align: center; }
        .report-management-selection-toast__actions { justify-content: center; margin-top: 0.5rem; }
    }
</style>
@endsection
