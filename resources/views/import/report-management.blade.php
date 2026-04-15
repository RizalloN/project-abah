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
        <div class="report-management-hero__badge mt-3 mt-md-0"><i class="fas fa-shield-alt mr-2 text-primary"></i> Guard Aktif</div>
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
     data-delete-cancel-url-template="{{ route('import.report-management.delete.cancel', ['deleteId' => '__DELETE_ID__']) }}">
    <div class="card-header bg-white border-0 import-upload-card__header">
        <span class="import-upload-card__eyebrow">Seleksi & Preview</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-database text-primary mr-2"></i> Data per Grup
        </h5>
        <p class="import-upload-card__subtitle mb-0">Pilih grup, lalu hapus data yang diperlukan.</p>
    </div>
    <div class="card-body import-upload-card__body">
        <div class="report-management-top-shell mb-4">
            <div class="row report-management-top-grid">
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <div class="report-management-field-panel h-100">
                        <div class="report-management-field-panel__eyebrow">Sumber Data</div>
                        <label class="report-management-field-panel__label" for="management-report-select">Pilih Report</label>
                        <select id="management-report-select" class="form-control select2">
                            <option value="">-- Pilih Report --</option>
                            @foreach($reports as $report)
                                <option value="{{ $report->id_report }}" data-table-name="{{ $report->table_name }}">{{ $report->nama_report }} ({{ $report->table_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <div class="report-management-recover-panel h-100">
                        <div class="report-management-rebuild-panel__topline mb-2">Recover Dari Backup</div>
                        <label class="report-management-field-panel__label mb-2" for="management-backup-select">File Backup SQL</label>
                        <select id="management-backup-select" class="form-control">
                            <option value="">-- Pilih Backup --</option>
                            @foreach($backupFiles as $backup)
                                <option value="{{ $backup['path'] }}">{{ $backup['name'] }} ({{ $backup['size_human'] }} · {{ $backup['modified_at'] }})</option>
                            @endforeach
                        </select>
                        <div class="report-management-rebuild-hint mt-2 mb-3">Recovery hanya menimpa tabel report yang dipilih. Sistem mengekstrak tabel terkait dari backup full agar lebih aman dan lebih cepat.</div>
                        <button type="button" id="btn-management-recover" class="btn btn-outline-success report-management-filter-btn report-management-filter-btn--secondary mt-auto" {{ empty($backupFiles) ? 'disabled' : '' }}>
                            <i class="fas fa-life-ring mr-2"></i> <span id="management-recover-label">Recover Backup</span>
                        </button>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="report-management-rebuild-panel h-100">
                        <div>
                            <div class="report-management-rebuild-panel__topline mb-2">Sinkronisasi Snapshot</div>
                            <div class="custom-control custom-switch report-management-rebuild-switch mb-1">
                                <input type="checkbox" class="custom-control-input" id="management-rebuild-force">
                                <label class="custom-control-label" for="management-rebuild-force">Mulai dari awal</label>
                            </div>
                            <div class="report-management-rebuild-hint mb-3">Bangun ulang seluruh snapshot untuk semua report dengan mode penuh bila diperlukan.</div>
                        </div>
                        <button type="button" id="btn-management-rebuild" class="btn btn-outline-primary report-management-filter-btn report-management-filter-btn--secondary mt-auto">
                            <i class="fas fa-sync-alt mr-2"></i> <span id="management-rebuild-label">Refresh Snapshot</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row report-management-stat-row mb-4">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="report-management-stat">
                    <div class="report-management-stat__icon"><i class="fas fa-file-alt"></i></div>
                    <div class="report-management-stat__content">
                        <small>Report Aktif</small>
                        <strong id="management-summary-report">Belum dipilih</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="report-management-stat">
                    <div class="report-management-stat__icon report-management-stat__icon--info"><i class="fas fa-users"></i></div>
                    <div class="report-management-stat__content">
                        <small>Jumlah Grup</small>
                        <strong id="management-summary-groups">0</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-management-stat">
                    <div class="report-management-stat__icon report-management-stat__icon--success"><i class="fas fa-table"></i></div>
                    <div class="report-management-stat__content">
                        <small>Grand Total Baris</small>
                        <strong id="management-summary-rows">0</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-management-action-bar mb-4">
            <div class="report-management-action-bar__group">
                <button type="button" id="btn-management-filter" class="btn btn-primary report-management-filter-btn report-management-filter-btn--primary">
                    <i class="fas fa-filter mr-2"></i> Tampilkan Data
                </button>
                <button type="button" id="btn-management-deduplicate" class="btn btn-outline-danger report-management-filter-btn report-management-filter-btn--danger" disabled>
                    <i class="fas fa-clone mr-2"></i> Hapus Duplikat
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

        <div class="report-management-table-wrap">
            <div class="table-responsive">
                <table class="table table-hover mb-0 report-management-table">
                    <thead>
                        <tr>
                            <th class="text-center report-management-col-check"><i class="far fa-check-square"></i></th>
                            <th>Kanca</th>
                            <th class="text-right">Jumlah Baris</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="management-table-body">
                        <tr><td colspan="4" class="text-center text-muted py-5">Pilih report lalu klik "Tampilkan Data".</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="management-pagination" class="report-management-pagination d-none"></div>

        <div class="report-management-selection-toast-shell">
            <div id="management-selection-toast" class="report-management-selection-toast d-none" aria-live="polite">
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
            return;
        }

        function selectedTableName() {
            const selectedOption = managementReportSelect.selectedOptions?.[0];
            return String(selectedOption?.dataset?.tableName || '').trim();
        }

        function formatManagementNumber(value) {
            return Number(value || 0).toLocaleString('id-ID');
        }

        function humanizeRecoveryStage(stage) {
            const lookup = {
                queued: 'Queued',
                validating: 'Validasi',
                extracting_backup: 'Ekstraksi',
                importing_backup: 'Import SQL',
                swapping_data: 'Pulihkan Data',
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
                const canRecover = !!managementReportSelect.value && !!managementBackupSelect?.value;
                btnManagementRecover.disabled = !canRecover;
                btnManagementRecover.title = canRecover
                    ? 'Pulihkan tabel report dari file backup yang dipilih.'
                    : 'Pilih report dan file backup terlebih dahulu.';
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

            await Swal.fire({
                icon: payload.status === 'warning' ? 'warning' : 'success',
                title: payload.status === 'warning' ? 'Dalam Antrean' : 'Berhasil',
                text: payload.message || 'Rebuild snapshot sudah dijadwalkan.',
            });

            if (payload.rebuild_id) {
                const statusUrl = templateUrl(reportManagementCard.dataset.rebuildStatusUrlTemplate, payload.rebuild_id);
                const finalState = await pollRebuildStatus(statusUrl);
                if (finalState?.status === 'error') {
                    throw new Error(finalState.message || 'Progress rebuild gagal dipantau.');
                }
            }

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
                } else if (stage === 'extracting_backup' && payload?.bytes_read && payload?.total_bytes) {
                    managementRecoveryMeta.textContent = `Memindai ${(Number(payload.bytes_read) / 1024 / 1024).toFixed(1)} MB dari ${(Number(payload.total_bytes) / 1024 / 1024).toFixed(1)} MB backup.`;
                } else {
                    managementRecoveryMeta.textContent = 'Recovery dilakukan per tabel agar lebih aman dibanding restore full database.';
                }
            }
        }

        async function pollRecoveryStatus(statusUrl) {
            if (!statusUrl) {
                return null;
            }

            for (let attempt = 0; attempt < 14400; attempt++) {
                const response = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const state = await response.json().catch(() => ({}));
                updateRecoveryProgress(state);

                const status = String(state.status || '').toLowerCase();
                if (['completed', 'failed', 'warning', 'error'].includes(status)) {
                    return state;
                }

                await new Promise((resolve) => setTimeout(resolve, 1500));
            }

            return { status: 'warning', message: 'Recovery backup masih berjalan di background.' };
        }

        async function handleRecovery() {
            if (!btnManagementRecover || !managementReportSelect?.value || !managementBackupSelect?.value) {
                return;
            }

            const backupLabel = managementBackupSelect.selectedOptions?.[0]?.text || 'backup terpilih';
            const reportLabel = managementReportSelect.selectedOptions?.[0]?.text || 'report terpilih';
            const confirmation = await Swal.fire({
                icon: 'warning',
                title: 'Recover Data Report?',
                html: `Data pada <b>${reportLabel}</b> akan diganti dari backup <b>${backupLabel}</b>.`,
                showCancelButton: true,
                confirmButtonText: 'Lanjutkan',
                cancelButtonText: 'Batal',
            });

            if (!confirmation.isConfirmed) {
                return;
            }

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
            if (!recoveryId) {
                throw new Error('Recovery ID tidak diterima dari server.');
            }

            const finalState = await pollRecoveryStatus(
                String(reportManagementCard.dataset.recoverStatusUrlTemplate || '').replace('__RECOVERY_ID__', encodeURIComponent(recoveryId))
            );

            if (String(finalState?.status || '').toLowerCase() === 'failed') {
                throw new Error(finalState?.error || finalState?.message || 'Recovery backup gagal.');
            }

            await Swal.fire({
                icon: String(finalState?.status || '').toLowerCase() === 'warning' ? 'warning' : 'success',
                title: String(finalState?.status || '').toLowerCase() === 'warning' ? 'Recovery Berjalan' : 'Recovery Selesai',
                text: finalState?.message || 'Recovery backup selesai diproses.',
            });

            await refreshCurrentGrid();
        }

        async function pollRebuildStatus(statusUrl) {
            if (!statusUrl) {
                return null;
            }

            for (let attempt = 0; attempt < 120; attempt++) {
                const response = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const state = await response.json().catch(() => ({}));
                const status = String(state.status || '').toLowerCase();

                if (['completed', 'failed', 'warning', 'error'].includes(status)) {
                    return state;
                }

                await new Promise((resolve) => setTimeout(resolve, 1500));
            }

            return { status: 'warning', message: 'Rebuild snapshot masih berjalan di background.' };
        }

        syncExtraActionState();

        managementReportSelect.addEventListener('change', function () {
            if (managementRecoveryProgress) {
                managementRecoveryProgress.classList.add('d-none');
            }
            syncExtraActionState();
        });
        managementBackupSelect?.addEventListener('change', syncExtraActionState);
        managementRebuildForce?.addEventListener('change', syncExtraActionState);
        btnManagementDeduplicate?.addEventListener('click', async function () {
            btnManagementDeduplicate.disabled = true;
            try {
                await handleDeduplicate();
            } catch (error) {
                await Swal.fire({ icon: 'error', title: 'Hapus Duplikat Gagal', text: error.message || 'Terjadi kesalahan saat memproses duplikat.' });
            } finally {
                syncExtraActionState();
            }
        });
        btnManagementRebuild?.addEventListener('click', async function () {
            btnManagementRebuild.disabled = true;
            try {
                await handleRebuild();
            } catch (error) {
                await Swal.fire({ icon: 'error', title: 'Rebuild Gagal', text: error.message || 'Terjadi kesalahan saat menjadwalkan rebuild.' });
            } finally {
                syncExtraActionState();
            }
        });
        btnManagementRecover?.addEventListener('click', async function () {
            btnManagementRecover.disabled = true;
            try {
                await handleRecovery();
            } catch (error) {
                await Swal.fire({ icon: 'error', title: 'Recovery Gagal', text: error.message || 'Terjadi kesalahan saat memproses recovery backup.' });
            } finally {
                syncExtraActionState();
            }
        });
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
    .report-management-rebuild-panel__topline { display:inline-flex; align-items:center; align-self:flex-start; padding:0.35rem 0.85rem; border-radius:999px; background:rgba(37,99,235,0.08); font-size:0.75rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#2563eb; }
    .report-management-rebuild-switch { padding-left:2.5rem; }
    .report-management-rebuild-switch .custom-control-label { font-weight:600; color:#1e293b; cursor:pointer; font-size: 0.95rem; }
    .report-management-rebuild-switch .custom-control-label::before, .report-management-rebuild-switch .custom-control-label::after { top: 0.15rem; left: -2.5rem; }
    .report-management-rebuild-hint { color:#64748b; font-size:0.85rem; line-height:1.5; }

    .report-management-filter-btn { min-height:44px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; font-weight:600; padding:0 1.5rem; font-size: 0.95rem; transition:all 0.2s ease; }
    .report-management-filter-btn:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,0.08); }
    .report-management-filter-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .report-management-filter-btn--primary { min-width:180px; }
    .report-management-filter-btn--danger { min-width:180px; }
    .report-management-filter-btn--secondary { width:100%; border-width: 2px; }

    .report-management-stat-row { align-items:stretch; }
    .report-management-stat { display:flex; align-items:center; gap: 1rem; height:100%; padding:1.25rem; border-radius:12px; background:#ffffff; border:1px solid rgba(226,232,240,0.8); box-shadow:0 4px 6px -1px rgba(0,0,0,0.02); transition: border-color 0.2s; }
    .report-management-stat:hover { border-color: rgba(148,163,184,0.5); }
    .report-management-stat__icon { display:flex; align-items:center; justify-content:center; width:48px; height:48px; border-radius:12px; background:rgba(37,99,235,0.1); color:#2563eb; font-size: 1.25rem; flex-shrink: 0; }
    .report-management-stat__icon--info { background:rgba(14,165,233,0.1); color:#0ea5e9; }
    .report-management-stat__icon--success { background:rgba(16,185,129,0.1); color:#10b981; }
    .report-management-stat__content { display:flex; flex-direction:column; justify-content:center; }
    .report-management-stat small { color:#64748b; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem; }
    .report-management-stat strong { color:#0f172a; font-size:1.1rem; font-weight:700; line-height:1.2; word-break:break-word; }

    .report-management-action-bar { display:flex; align-items:center; padding:1rem 1.25rem; border-radius:12px; background:#f8fafc; border:1px solid rgba(226,232,240,0.8); }
    .report-management-action-bar__group { display:flex; align-items:center; flex-wrap:wrap; gap:1rem; }

    .report-management-notice { padding:1rem 1.25rem; border-radius:12px; font-size:0.95rem; line-height:1.6; border-left: 4px solid transparent; }
    .report-management-notice--info { color:#1e3a8a; background:#eff6ff; border-left-color: #3b82f6; }
    .report-management-notice--warning { color:#78350f; background:#fffbeb; border-left-color: #f59e0b; }

    .report-management-load-card { padding:1.25rem; border-radius:12px; background:#ffffff; border:1px solid #bfdbfe; box-shadow:0 4px 12px -2px rgba(37,99,235,0.1); }
    .report-management-load-card__header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
    .report-management-load-card__eyebrow { font-size:0.75rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:#2563eb; margin-bottom: 0.25rem; }
    .report-management-load-card__title { font-size:1rem; font-weight:700; color:#0f172a; line-height:1.4; }
    .report-management-load-card__stage { display:inline-flex; align-items:center; justify-content:center; padding:0.35rem 0.85rem; border-radius:999px; background:#eff6ff; color:#2563eb; font-size:0.8rem; font-weight:700; letter-spacing:0.02em; text-transform:uppercase; border: 1px solid #bfdbfe; }

    .report-management-progress { height:12px; border-radius:999px; background:#e2e8f0; overflow:hidden; margin-bottom: 0.75rem; }
    .report-management-progress__bar { height:100%; font-weight:700; font-size:10px; line-height:12px; background: linear-gradient(90deg, #0ea5e9, #2563eb); transition:width 0.3s ease; }
    .report-management-progress__bar--indeterminate { background-image:linear-gradient(90deg, #e2e8f0 25%, #94a3b8 50%, #e2e8f0 75%); background-size:200% 100%; animation:reportManagementProgressShift 1.5s infinite linear; }

    .report-management-load-card__meta-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
    .report-management-progress__value { color:#0f172a; font-weight:700; font-size: 0.95rem; }
    .report-management-load-card__units { color:#64748b; font-size:0.85rem; font-weight:600; }
    .report-management-progress__text { color:#475569; font-size: 0.9rem; font-weight:600; }
    .report-management-progress__meta { display:block; color:#94a3b8; font-size: 0.85rem; font-weight:500; min-height:1.2rem; }

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
