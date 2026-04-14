@extends('layouts.admin')

@section('title', 'Kelola Report')

@section('content')
<div class="report-management-hero mb-4">
    <div class="report-management-hero__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="pr-3">
            <span class="report-management-hero__eyebrow">Kelola Report</span>
            <div class="report-management-hero__title"><i class="fas fa-layer-group mr-2"></i> Kelola Data Report</div>
            <p class="report-management-hero__text mb-0">Pilih report, lalu hapus data per grup.</p>
        </div>
        <div class="report-management-hero__badge mt-3 mt-md-0"><i class="fas fa-shield-alt mr-2"></i> Guard Aktif</div>
    </div>
</div>

<div class="card shadow-sm border-0 import-upload-card" id="report-management-card"
     data-fetch-url="{{ route('import.report-management.data') }}"
     data-rebuild-url="{{ route('import.report-management.rebuild') }}"
     data-rebuild-status-url-template="{{ route('import.report-management.rebuild.status', ['rebuildId' => '__REBUILD_ID__']) }}"
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
                <div class="col-xl-8 mb-3 mb-xl-0">
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
                <div class="col-xl-4">
                    <div class="report-management-rebuild-panel report-management-rebuild-panel--formal h-100">
                        <div class="report-management-rebuild-panel__topline">Sinkronisasi Snapshot</div>
                        <div class="custom-control custom-switch report-management-rebuild-switch">
                            <input type="checkbox" class="custom-control-input" id="management-rebuild-force">
                            <label class="custom-control-label" for="management-rebuild-force">Mulai dari awal</label>
                        </div>
                        <div class="report-management-rebuild-hint">Bangun ulang seluruh snapshot untuk semua report dengan mode penuh bila diperlukan.</div>
                        <button type="button" id="btn-management-rebuild" class="btn btn-outline-primary report-management-filter-btn report-management-filter-btn--secondary">
                            <i class="fas fa-sync-alt mr-2"></i> <span id="management-rebuild-label">Refresh Snapshot</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row report-management-stat-row mb-4">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="report-management-stat">
                    <small>Report Aktif</small>
                    <strong id="management-summary-report">Belum dipilih</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="report-management-stat">
                    <small>Jumlah Grup</small>
                    <strong id="management-summary-groups">0</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-management-stat">
                    <small>Grand Total Baris</small>
                    <strong id="management-summary-rows">0</strong>
                </div>
            </div>
        </div>

        <div class="report-management-action-bar mb-4">
            <div class="report-management-action-bar__group">
                <button type="button" id="btn-management-filter" class="btn btn-primary report-management-filter-btn report-management-filter-btn--primary shadow-sm">
                    <i class="fas fa-filter mr-2"></i> Tampilkan Data
                </button>
                <button type="button" id="btn-management-deduplicate" class="btn btn-outline-danger report-management-filter-btn report-management-filter-btn--danger shadow-sm" disabled>
                    <i class="fas fa-clone mr-2"></i> Hapus Duplikat
                </button>
            </div>
        </div>

        <div id="management-notice" class="report-management-notice d-none mb-3"></div>

        <div class="report-management-bulkbar mt-3">
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="management-select-all" disabled>
                <label class="form-check-label font-weight-bold" for="management-select-all">Pilih Semua di Halaman</label>
            </div>
            <div class="report-management-bulkbar__hint">
                Klik baris untuk centang cepat. Setiap header periode punya pilihan "Pilih semua periode ini".
            </div>
        </div>

        <div class="report-management-table-wrap mt-3">
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
                        <tr><td colspan="4" class="text-center text-muted py-4">Pilih report lalu klik "Tampilkan Data".</td></tr>
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
        const managementRebuildForce = document.getElementById('management-rebuild-force');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        if (!reportManagementCard || !managementReportSelect) {
            return;
        }

        function selectedTableName() {
            const selectedOption = managementReportSelect.selectedOptions?.[0];
            return String(selectedOption?.dataset?.tableName || '').trim();
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

        managementReportSelect.addEventListener('change', syncExtraActionState);
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
    });
</script>
@endsection

@section('styles')
<style>
    .report-management-hero{position:relative;overflow:hidden;border-radius:24px;padding:1.45rem 1.5rem;background:radial-gradient(circle at top right,rgba(37,99,235,.22),transparent 30%),linear-gradient(135deg,#f8fbff 0%,#eef4ff 46%,#dbeafe 100%);border:1px solid rgba(37,99,235,.16);box-shadow:0 22px 45px -32px rgba(29,78,216,.4)}
    .report-management-hero__glow{position:absolute;top:-48px;right:-30px;width:170px;height:170px;border-radius:999px;background:rgba(14,165,233,.16);filter:blur(10px)}
    .report-management-hero__eyebrow,.import-upload-card__eyebrow{display:inline-block;margin-bottom:.55rem;padding:.35rem .7rem;border-radius:999px;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .report-management-hero__eyebrow{color:#1d4ed8;background:rgba(255,255,255,.72);border:1px solid rgba(37,99,235,.16)}
    .report-management-hero__title{color:#0f3f8c;font-size:1.4rem;font-weight:800;letter-spacing:-.03em;margin-bottom:.35rem}
    .report-management-hero__text{color:#31527c;line-height:1.7;max-width:700px}
    .report-management-hero__badge{display:inline-flex;align-items:center;min-height:48px;padding:.8rem 1rem;border-radius:18px;background:rgba(255,255,255,.84);border:1px solid rgba(37,99,235,.14);color:#0f3f8c;font-weight:700;box-shadow:0 18px 32px -24px rgba(29,78,216,.3)}
    .import-upload-card{border-radius:26px;overflow:hidden;box-shadow:0 28px 60px -40px rgba(15,23,42,.32)!important}
    .import-upload-card__header{padding:1.45rem 1.5rem 1rem;background:radial-gradient(circle at top left,rgba(59,130,246,.09),transparent 28%),linear-gradient(180deg,#fff 0%,#f8fafc 100%)}
    .import-upload-card__eyebrow{color:#1d4ed8;background:rgba(37,99,235,.08)}
    .import-upload-card__subtitle{color:#64748b;max-width:700px;line-height:1.6}
    .import-upload-card__body{position:relative;padding:1.5rem 1.5rem 7rem}
    .report-management-top-shell{padding:1.1rem;border-radius:24px;background:linear-gradient(180deg,#fafcff 0%,#f3f7fd 100%);border:1px solid rgba(191,219,254,.52);box-shadow:inset 0 1px 0 rgba(255,255,255,.78)}
    .report-management-top-grid{align-items:stretch}
    .report-management-field-panel{display:flex;flex-direction:column;justify-content:center;height:100%;padding:1.2rem 1.25rem;border-radius:22px;background:linear-gradient(180deg,#fff 0%,#fcfdff 100%);border:1px solid rgba(148,163,184,.16);box-shadow:0 18px 40px -34px rgba(15,23,42,.3)}
    .report-management-field-panel__eyebrow{display:inline-flex;align-items:center;align-self:flex-start;margin-bottom:.6rem;padding:.32rem .72rem;border-radius:999px;background:rgba(15,76,129,.08);color:#0f4c81;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .report-management-field-panel__label{margin-bottom:.72rem;color:#0f172a;font-size:1rem;font-weight:800;letter-spacing:.01em}
    .report-management-filter-btn{min-height:48px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;padding:0 1.3rem;letter-spacing:.01em;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background-color .18s ease}
    .report-management-filter-btn:hover:not(:disabled){transform:translateY(-1px)}
    .report-management-filter-btn:disabled{transform:none;box-shadow:none}
    .report-management-filter-btn--primary{min-width:196px;box-shadow:0 18px 36px -24px rgba(29,78,216,.48)}
    .report-management-filter-btn--danger{min-width:196px}
    .report-management-filter-btn--secondary{width:100%;margin-top:.15rem}
    .report-management-rebuild-panel{padding:1.2rem 1.25rem;border-radius:22px;background:linear-gradient(180deg,#ffffff 0%,#f6f9ff 100%);border:1px solid rgba(148,163,184,.16);box-shadow:0 18px 40px -34px rgba(15,23,42,.28)}
    .report-management-rebuild-panel--formal{display:flex;flex-direction:column;justify-content:space-between;gap:.78rem}
    .report-management-rebuild-panel__topline{display:inline-flex;align-items:center;align-self:flex-start;padding:.32rem .72rem;border-radius:999px;background:rgba(29,78,216,.08);font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8}
    .report-management-rebuild-switch{padding-left:2.2rem}
    .report-management-rebuild-switch .custom-control-label{font-weight:800;color:#0f3f8c;cursor:pointer}
    .report-management-rebuild-hint{margin-top:.1rem;color:#5f6f86;font-size:.84rem;line-height:1.55}
    .report-management-stat-row{align-items:stretch}
    .report-management-stat{display:flex;flex-direction:column;justify-content:space-between;height:100%;min-height:118px;padding:1.2rem 1.15rem;border-radius:22px;background:linear-gradient(180deg,#fff 0%,#fbfcfe 100%);border:1px solid rgba(148,163,184,.16);box-shadow:0 16px 34px -32px rgba(15,23,42,.28)}
    .report-management-stat small{display:block;color:#64748b;font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.5rem}
    .report-management-stat strong{display:block;color:#0f172a;font-size:1.08rem;font-weight:800;line-height:1.5;word-break:break-word}
    .report-management-action-bar{display:flex;align-items:center;justify-content:flex-start;padding:1rem 1.05rem;border-radius:22px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);border:1px solid rgba(226,232,240,.92);box-shadow:0 16px 34px -30px rgba(15,23,42,.24)}
    .report-management-action-bar__group{display:flex;align-items:center;flex-wrap:wrap;gap:.85rem}
    .report-management-notice{margin-top:.25rem;padding:.95rem 1rem;border-radius:18px;font-size:.92rem;line-height:1.65}
    .report-management-notice--info{color:#0f3f8c;background:rgba(219,234,254,.72);border:1px solid rgba(96,165,250,.2)}
    .report-management-notice--warning{color:#92400e;background:rgba(254,243,199,.78);border:1px solid rgba(251,191,36,.25)}
    .report-management-bulkbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:.8rem 1rem;border-radius:16px;background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid rgba(148,163,184,.2)}
    .report-management-bulkbar__hint{font-size:.88rem;font-weight:600;color:#475569}
    .report-management-table-wrap{border:1px solid rgba(148,163,184,.18);border-radius:22px;overflow:hidden;background:#fff}
    .report-management-table thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef4ff 100%);border-bottom:1px solid rgba(148,163,184,.18);color:#334155;font-size:.8rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:1rem}
    .report-management-table tbody td{padding:1rem;border-top:1px solid rgba(226,232,240,.8);vertical-align:middle}
    .report-management-col-check{width:52px}
    .report-management-primary{color:#0f172a;font-weight:700}
    .report-management-count{display:inline-flex;align-items:center;justify-content:flex-end;min-width:72px;padding:.4rem .7rem;border-radius:999px;background:rgba(37,99,235,.08);color:#1d4ed8;font-weight:800}
    .report-management-delete-btn{min-width:118px;border-radius:14px;font-weight:700}
    .report-management-period-row td{padding:.9rem 1rem!important;background:linear-gradient(180deg,#f8fbff 0%,#f1f7ff 100%);border-top:1px solid rgba(191,219,254,.65)!important}
    .report-management-period-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
    .report-management-period-card__title{font-size:1rem;font-weight:800;color:#0f3f8c;letter-spacing:-.02em}
    .report-management-period-card__meta{font-size:.84rem;font-weight:700;color:#64748b}
    .report-management-period-card__toggle{display:inline-flex;align-items:center;gap:.55rem;margin:0;padding:.55rem .8rem;border-radius:999px;background:rgba(37,99,235,.08);color:#1d4ed8;font-weight:700;cursor:pointer}
    .management-data-row{cursor:pointer;transition:background-color .18s ease,box-shadow .18s ease}
    .management-data-row.is-selected{background:linear-gradient(180deg,rgba(219,234,254,.48) 0%,rgba(239,246,255,.78) 100%)}
    .management-data-row:hover{background-color:rgba(248,250,252,.92)}
    .report-management-pagination{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1rem}
    .report-management-pagination__meta{font-size:.9rem;font-weight:700;color:#475569}
    .report-management-pagination__actions{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}
    .report-management-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 .8rem;border:1px solid rgba(148,163,184,.28);border-radius:12px;background:#fff;color:#334155;font-weight:800}
    .report-management-page-btn.is-active{background:linear-gradient(135deg,#0f4c81,#1d4ed8);border-color:transparent;color:#fff;box-shadow:0 16px 32px -24px rgba(29,78,216,.55)}
    .report-management-page-btn:disabled{opacity:.45;cursor:not-allowed}
    .report-management-selection-toast-shell{position:fixed;right:1.5rem;bottom:1.5rem;z-index:1080;display:flex;justify-content:flex-end;align-items:flex-end;width:min(420px,calc(100vw - 3rem));max-width:calc(100vw - 3rem);margin:0;pointer-events:none}
    .report-management-selection-toast{position:relative;display:flex;align-items:center;justify-content:space-between;gap:1rem;width:100%;max-width:100%;margin-left:auto;padding:1rem 1.05rem;border-radius:20px;background:linear-gradient(135deg,#0a4f8f 0%,#1166b1 52%,#0f82c9 100%);color:#fff;box-shadow:0 26px 60px -28px rgba(8,47,73,.58);border:1px solid rgba(191,219,254,.24);pointer-events:auto}
    .report-management-selection-toast__body{min-width:0}
    .report-management-selection-toast__eyebrow{font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:rgba(219,234,254,.88)}
    .report-management-selection-toast__text{font-size:1rem;font-weight:800;line-height:1.35}
    .report-management-selection-toast__subtext{font-size:.84rem;font-weight:600;color:rgba(239,246,255,.9)}
    .report-management-selection-toast__actions{display:flex;align-items:center;gap:.55rem}
    .report-management-selection-toast__btn{border-radius:12px;font-weight:800;padding:.65rem .95rem}
    .report-management-selection-toast__btn--ghost{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);color:#fff}
    .report-management-selection-toast__btn--danger{background:#fff;border:1px solid rgba(255,255,255,.18);color:#0f3f8c}
    .report-management-progress{height:14px;border-radius:999px;background:linear-gradient(180deg,#dbe7ef 0%,#cfe0ea 100%);overflow:hidden;box-shadow:inset 0 1px 2px rgba(15,23,42,.08)}
    .report-management-progress__bar{height:100%;font-weight:700;font-size:12px;line-height:14px;background:linear-gradient(90deg,#0f766e 0%,#147a72 55%,#1a8b80 100%);box-shadow:0 0 0 1px rgba(15,118,110,.05) inset;transition:width .45s cubic-bezier(.22,1,.36,1)}
    .report-management-progress__value{display:inline-block;color:#0f172a;font-weight:800;letter-spacing:.04em}
    .report-management-progress__text{color:#0f766e;font-weight:700;letter-spacing:.02em}
    .report-management-progress__meta{display:block;color:#64748b;font-weight:600;letter-spacing:.01em;min-height:1.2rem}
    .swal-modern-popup{border:1px solid rgba(226,232,240,.95);border-radius:28px;padding:1.4rem 1.4rem 1.2rem;box-shadow:0 30px 80px -35px rgba(15,23,42,.35)}
    .swal-modern-title{color:#0f172a;font-weight:800;letter-spacing:-.02em}
    .swal-modern-html{color:#475569;font-size:.95rem;line-height:1.65}
    .swal-modern-confirm,.swal-modern-cancel{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:16px;font-weight:700;padding:.8rem 1.3rem}
    .swal-modern-confirm{background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;box-shadow:0 16px 34px -22px rgba(15,23,42,.45)}
    .swal-modern-cancel{background:#e2e8f0;color:#334155;margin-left:.5rem}
    @media (max-width:575.98px){.report-management-filter-btn{width:100%}.report-management-action-bar__group{width:100%}.report-management-filter-btn--primary,.report-management-filter-btn--danger{min-width:0}}
    @media (max-width:767.98px){.report-management-hero,.import-upload-card__header{padding-left:1rem;padding-right:1rem}.import-upload-card__body{padding:1rem 1rem 7.5rem}.report-management-hero__title{font-size:1.15rem}.report-management-hero__badge,.report-management-rebuild-panel,.report-management-top-shell{width:100%}.report-management-top-shell{padding:1rem}.report-management-field-panel,.report-management-rebuild-panel,.report-management-action-bar,.report-management-stat{border-radius:18px}.report-management-table thead th,.report-management-table tbody td{padding:.8rem}.report-management-bulkbar,.report-management-period-card,.report-management-selection-toast,.report-management-pagination{align-items:flex-start}.report-management-period-card__toggle,.report-management-selection-toast,.report-management-selection-toast__actions{width:100%}.report-management-selection-toast-shell{left:1rem;right:1rem;bottom:1rem;width:calc(100vw - 2rem);max-width:calc(100vw - 2rem)}.report-management-selection-toast{flex-direction:column}}
</style>
@endsection
