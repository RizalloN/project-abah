@extends('layouts.admin')

@section('title', 'File Management')

@section('content')
<div class="file-management-hero mb-4">
    <div class="file-management-hero__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="pr-3">
            <span class="file-management-hero__eyebrow">File Management</span>
            <div class="file-management-hero__title"><i class="fas fa-folder-open mr-2"></i> Kelola File Bekas Import</div>
            <p class="file-management-hero__text mb-0">Pantau, cari, dan hapus file import yang sudah tidak dipakai. Semua aksi dibatasi ke folder yang memang dikelola sistem.</p>
        </div>
        <div class="file-management-hero__badge mt-3 mt-md-0"><i class="fas fa-user-shield mr-2"></i> Admin Only</div>
    </div>
</div>

@if (session('success'))
    <div class="file-management-flash file-management-flash--success mb-4">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="file-management-flash file-management-flash--danger mb-4">
        <i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->first() }}
    </div>
@endif

<div class="row mb-4">
    <div class="col-xl col-lg-4 col-md-6 mb-4 mb-xl-0">
        <div class="fm-stat-card h-100">
            <small>Total File</small>
            <strong>{{ number_format($totals['files'], 0, ',', '.') }}</strong>
        </div>
    </div>
    <div class="col-xl col-lg-4 col-md-6 mb-4 mb-xl-0">
        <div class="fm-stat-card h-100">
            <small>Total Ukuran</small>
            <strong>{{ $totals['size_human'] }}</strong>
        </div>
    </div>
    <div class="col-xl col-lg-4 col-md-6 mb-4 mb-xl-0">
        <div class="fm-stat-card h-100">
            <small>Folder Terkelola</small>
            <strong>{{ number_format($totals['directories'], 0, ',', '.') }}</strong>
        </div>
    </div>
    <div class="col-xl col-lg-4 col-md-6 mb-4 mb-md-0">
        <div class="fm-stat-card h-100">
            <small>Modified Terakhir</small>
            <strong>{{ $totals['latest_modified_at'] ? $totals['latest_modified_at']->format('d M Y H:i') : '-' }}</strong>
        </div>
    </div>
    <div class="col-xl col-lg-4 col-md-6">
        <div class="fm-stat-card h-100">
            <small>File Aktif</small>
            <strong>{{ number_format($totals['active_files'] ?? 0, 0, ',', '.') }}</strong>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 fm-main-card" id="file-management-card"
     data-backup-url="{{ route('file-management.database-backup') }}"
     data-delete-url="{{ route('file-management.destroy') }}"
     data-cleanup-url="{{ route('import.cleanup-artifacts') }}">
    <div class="card-header bg-white border-0 fm-main-card__header">
        <span class="fm-eyebrow">Managed Storage</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-archive text-primary mr-2"></i> Daftar File Import
        </h5>
        <p class="fm-main-card__subtitle mb-0">Folder yang dipantau: import excel, casa brilink, report PH, performance PIS, dan workspace staging.</p>
    </div>
    <div class="card-body file-management-card__body">
        <div class="file-management-backup-panel mb-3">
            <div>
                <div class="file-management-backup-panel__title">Backup Database Full</div>
                <div class="file-management-backup-panel__text">Generate file `.sql` bersih dari seluruh database aktif. Output dipisahkan dari stderr agar tidak tercampur HTML atau karakter aneh yang mengganggu proses import.</div>
            </div>
            <button type="button" id="btn-file-management-backup" class="btn btn-success fm-action-btn">
                <i class="fas fa-database mr-2"></i> Backup Database
            </button>
        </div>

        <div class="row align-items-end mb-4">
            <div class="col-lg-5 mb-3 mb-lg-0">
                <label for="file-management-search" class="font-weight-bold text-dark">Cari File</label>
                <div class="input-group fm-input-group">
                    <input type="text" id="file-management-search" class="form-control fm-input" placeholder="Nama file, folder, atau ekstensi">
                    <div class="input-group-append">
                        <span class="input-group-text fm-input-group__append"><i class="fas fa-search"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 mb-3 mb-lg-0">
                <label for="file-management-hours" class="font-weight-bold text-dark">Aging Cleanup</label>
                <input type="number" min="1" max="168" id="file-management-hours" class="form-control fm-input" value="12">
            </div>
            <div class="col-lg-5">
                <div class="d-flex flex-wrap justify-content-lg-end fm-action-group">
                    <button type="button" id="btn-file-management-cleanup" class="btn btn-outline-primary fm-action-btn">
                        <i class="fas fa-broom mr-2"></i> Cleanup Otomatis
                    </button>
                    <button type="button" id="btn-file-management-delete-selected" class="btn btn-danger fm-action-btn" disabled>
                        <i class="fas fa-trash-alt mr-2"></i> Hapus Terpilih
                    </button>
                </div>
            </div>
        </div>

        <div class="fm-category-grid mb-4">
            @foreach ($directories as $directory)
                <div class="fm-category-card">
                    <div class="fm-category-card__label">{{ $directory['label'] }}</div>
                    <div class="fm-category-card__stat">
                        {{ number_format($directory['files'], 0, ',', '.') }} file &middot; {{ $directory['size_human'] }}
                    </div>
                    <div class="fm-category-card__desc">{{ $directory['description'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="fm-bulk-actions mb-3">
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="file-management-select-all" {{ $files->isEmpty() ? 'disabled' : '' }}>
                <label class="form-check-label font-weight-bold" for="file-management-select-all">Pilih semua file yang terlihat</label>
            </div>
            <div class="fm-bulk-actions__hint">
                Gunakan tombol hapus untuk membersihkan file lama. File di luar folder terkelola tidak bisa dihapus dari halaman ini.
            </div>
        </div>

        <div class="table-responsive fm-table-wrapper">
            <table class="table table-hover mb-0 fm-table">
                <thead>
                    <tr>
                        <th class="text-center file-management-col-check"><i class="far fa-check-square"></i></th>
                        <th>File</th>
                        <th>Folder</th>
                        <th class="text-right">Ukuran</th>
                        <th>Modified</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="file-management-table-body">
                    @forelse ($files as $file)
                        <tr class="fm-table-row" data-file-row data-search="{{ strtolower($file['name'] . ' ' . $file['directory_label'] . ' ' . $file['relative_path']) }}">
                            <td class="text-center">
                                <input type="checkbox" class="management-file-checkbox" value="{{ $file['relative_path'] }}" {{ !empty($file['is_active']) ? 'disabled' : '' }}>
                            </td>
                            <td>
                                <div class="file-management-filecell">
                                    <div class="file-management-filecell__icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div>
                                        <div class="file-management-filecell__name">{{ $file['name'] }}</div>
                                        <div class="file-management-filecell__meta">{{ $file['relative_path'] }}</div>
                                        @if(!empty($file['is_active']))
                                            <div class="file-management-active-tag">
                                                <i class="fas fa-shield-alt mr-1"></i> Sedang dipakai job aktif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="file-management-folder">{{ $file['directory_label'] }}</div>
                                <div class="file-management-folder__meta">{{ $file['directory_description'] }}</div>
                            </td>
                            <td class="text-right">
                                <span class="file-management-size">{{ $file['size_human'] }}</span>
                            </td>
                            <td>
                                <div class="file-management-time">{{ $file['modified_human'] }}</div>
                            </td>
                            <td class="text-center">
                                @if(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'sql')
                                    <a href="{{ route('file-management.download', ['path' => $file['relative_path']]) }}" class="btn btn-sm btn-outline-primary file-management-download-btn mr-2">
                                        <i class="fas fa-download mr-1"></i>Unduh
                                    </a>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-danger file-management-delete-btn"
                                        data-path="{{ $file['relative_path'] }}"
                                        data-name="{{ $file['name'] }}"
                                        {{ !empty($file['is_active']) ? 'disabled' : '' }}>
                                    <i class="fas fa-trash-alt mr-1"></i>Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                Tidak ada file import tersisa pada folder yang dipantau.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const card = document.getElementById('file-management-card');
        const deleteUrl = card?.getAttribute('data-delete-url') || '';
        const backupUrl = card?.getAttribute('data-backup-url') || '';
        const cleanupUrl = card?.getAttribute('data-cleanup-url') || '';
        const searchInput = document.getElementById('file-management-search');
        const selectAll = document.getElementById('file-management-select-all');
        const tableBody = document.getElementById('file-management-table-body');
        const btnDeleteSelected = document.getElementById('btn-file-management-delete-selected');
        const btnBackup = document.getElementById('btn-file-management-backup');
        const btnCleanup = document.getElementById('btn-file-management-cleanup');
        const hoursInput = document.getElementById('file-management-hours');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        function themedSwal(options) {
            return Swal.fire(Object.assign({
                customClass: { popup: 'swal-modern-popup', title: 'swal-modern-title', htmlContainer: 'swal-modern-html', confirmButton: 'swal-modern-confirm', cancelButton: 'swal-modern-cancel' },
                buttonsStyling: false,
                background: '#ffffff',
            }, options));
        }

        function escapeHtml(value) {
            return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function getCheckboxes() {
            return Array.from(tableBody?.querySelectorAll('.management-file-checkbox') || []);
        }

        function getVisibleCheckboxes() {
            return getCheckboxes().filter((checkbox) => {
                const row = checkbox.closest('.file-management-row');
                return row && !row.classList.contains('d-none');
            });
        }

        function updateSelectionState() {
            const visible = getVisibleCheckboxes();
            const selectedVisible = visible.filter((checkbox) => checkbox.checked);
            const selectedAll = getCheckboxes().filter((checkbox) => checkbox.checked);
            if (btnDeleteSelected) {
                btnDeleteSelected.disabled = selectedAll.length === 0;
            }
            if (selectAll) {
                selectAll.checked = visible.length > 0 && selectedVisible.length === visible.length;
                selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visible.length;
            }
        }

        function applySearch() {
            const keyword = String(searchInput?.value || '').trim().toLowerCase();
            getCheckboxes().forEach((checkbox) => {
                const row = checkbox.closest('.file-management-row');
                if (!row) return;
                const haystack = String(row.getAttribute('data-search') || '');
                row.classList.toggle('d-none', keyword && !haystack.includes(keyword));
            });
            updateSelectionState();
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

            if (!response.ok) {
                throw new Error(data.message || 'Request gagal diproses.');
            }

            return data;
        }

        async function deleteFiles(paths, label) {
            if (!paths.length) {
                return;
            }

            const confirm = await themedSwal({
                icon: 'warning',
                title: 'Hapus File',
                html: `Anda akan menghapus <b>${paths.length}</b> file${label ? ` dari <b>${escapeHtml(label)}</b>` : ''}.<br>Aksi ini permanen.`,
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal',
            });

            if (!confirm.isConfirmed) {
                return;
            }

            const payload = await postJson(deleteUrl, { paths });

            await themedSwal({
                icon: 'success',
                title: 'Selesai',
                text: payload.message || 'File berhasil dihapus.',
            });

            window.location.reload();
        }

        searchInput?.addEventListener('input', applySearch);

        selectAll?.addEventListener('change', function () {
            const visible = getVisibleCheckboxes();
            visible.forEach((checkbox) => {
                checkbox.checked = !!selectAll.checked;
            });
            updateSelectionState();
        });

        tableBody?.addEventListener('change', function (event) {
            if (event.target.closest('.management-file-checkbox')) {
                updateSelectionState();
            }
        });

        tableBody?.addEventListener('click', async function (event) {
            const button = event.target.closest('.file-management-delete-btn');
            if (!button) return;

            button.disabled = true;
            try {
                await deleteFiles([button.getAttribute('data-path') || ''], button.getAttribute('data-name') || '');
            } catch (error) {
                await themedSwal({
                    icon: 'error',
                    title: 'Gagal Menghapus',
                    text: error.message || 'Terjadi kesalahan saat menghapus file.',
                });
            } finally {
                button.disabled = false;
            }
        });

        btnDeleteSelected?.addEventListener('click', async function () {
            const paths = getCheckboxes()
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value)
                .filter(Boolean);

            try {
                await deleteFiles(paths, 'file terpilih');
            } catch (error) {
                await themedSwal({
                    icon: 'error',
                    title: 'Gagal Menghapus',
                    text: error.message || 'Terjadi kesalahan saat menghapus file.',
                });
            }
        });

        btnCleanup?.addEventListener('click', async function () {
            const hours = Math.max(1, Math.min(168, Number(hoursInput?.value || 12)));
            const confirm = await themedSwal({
                icon: 'question',
                title: 'Cleanup Otomatis',
                html: `Jalankan cleanup untuk file lebih lama dari <b>${hours}</b> jam?`,
                showCancelButton: true,
                confirmButtonText: 'Jalankan',
                cancelButtonText: 'Batal',
            });

            if (!confirm.isConfirmed) {
                return;
            }

            const payload = await postJson(cleanupUrl, { hours });

            await themedSwal({
                icon: 'success',
                title: 'Cleanup Selesai',
                html: `Terhapus <b>${Number(payload.deleted_file_count || 0).toLocaleString('id-ID')}</b> file dan <b>${Number(payload.deleted_directory_count || 0).toLocaleString('id-ID')}</b> folder kosong.`,
            });

            window.location.reload();
        });

        btnBackup?.addEventListener('click', async function () {
            const confirm = await themedSwal({
                icon: 'question',
                title: 'Backup Database Full',
                html: 'Buat backup SQL penuh dari database aktif sekarang?',
                showCancelButton: true,
                confirmButtonText: 'Buat Backup',
                cancelButtonText: 'Batal',
            });

            if (!confirm.isConfirmed) {
                return;
            }

            btnBackup.disabled = true;
            try {
                const payload = await postJson(backupUrl, {});
                const downloadUrl = String(payload?.file?.download_url || '').trim();

                await themedSwal({
                    icon: 'success',
                    title: 'Backup Selesai',
                    html: `File <b>${escapeHtml(payload?.file?.name || 'backup.sql')}</b> berhasil dibuat.`,
                });

                if (downloadUrl) {
                    window.location.assign(downloadUrl);
                }

                window.setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                await themedSwal({
                    icon: 'error',
                    title: 'Backup Gagal',
                    text: error.message || 'Terjadi kesalahan saat membuat backup database.',
                });
            } finally {
                btnBackup.disabled = false;
            }
        });

        updateSelectionState();
        applySearch();
    });
</script>
@endsection

@section('styles')
<style>
    /* --- HERO & BASE --- */
    .file-management-hero { position: relative; overflow: hidden; border-radius: 26px; padding: 1.45rem 1.5rem; background: radial-gradient(circle at top right, rgba(37,99,235,0.22), transparent 30%), linear-gradient(135deg, #f8fbff 0%, #eef4ff 46%, #dbeafe 100%); border: 1px solid rgba(37,99,235,0.16); box-shadow: 0 22px 45px -32px rgba(29,78,216,0.4); }
    .file-management-hero__glow { position: absolute; top: -48px; right: -30px; width: 170px; height: 170px; border-radius: 999px; background: rgba(14,165,233,0.16); filter: blur(10px); }
    .file-management-hero__eyebrow, .fm-eyebrow { display: inline-block; margin-bottom: 0.55rem; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
    .file-management-hero__eyebrow { color: #1d4ed8; background: rgba(255,255,255,0.72); border: 1px solid rgba(37,99,235,0.16); }
    .file-management-hero__title { color: #0f3f8c; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 0.35rem; }
    .file-management-hero__text { color: #31527c; line-height: 1.7; max-width: 760px; }
    .file-management-hero__badge { display: inline-flex; align-items: center; min-height: 48px; padding: 0.8rem 1.25rem; border-radius: 18px; background: rgba(255,255,255,0.84); border: 1px solid rgba(37,99,235,0.14); color: #0f3f8c; font-weight: 700; box-shadow: 0 18px 32px -24px rgba(29,78,216,0.3); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .file-management-hero__badge:hover { transform: translateY(-2px); box-shadow: 0 20px 38px -20px rgba(29,78,216,0.4); }
    .file-management-flash { padding: 1rem 1.1rem; border-radius: 18px; font-weight: 700; }
    .file-management-flash--success { background: rgba(220,252,231,0.85); border: 1px solid rgba(34,197,94,0.2); color: #166534; }
    .file-management-flash--danger { background: rgba(254,226,226,0.88); border: 1px solid rgba(239,68,68,0.18); color: #b91c1c; }

    /* --- REFACTOR: VARIABLES & MAIN CARD --- */
    .fm-main-card {
        --fm-radius-xl: 24px;
        --fm-radius-lg: 20px;
        --fm-radius-md: 16px;
        --fm-shadow-soft: 0 2px 8px -1px rgba(15, 23, 42, 0.06), 0 4px 12px -2px rgba(15, 23, 42, 0.08);
        --fm-shadow-lifted: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.08);
        --fm-border-color: rgba(226, 232, 240, 0.9);
        --fm-transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);

        border-radius: var(--fm-radius-xl);
        overflow: hidden;
        box-shadow: 0 28px 60px -40px rgba(15,23,42,0.2) !important;
        border: 1px solid var(--fm-border-color);
        background: #fff;
    }
    .fm-main-card__header { padding: 1.45rem 1.75rem 1.25rem; border-bottom: 1px solid var(--fm-border-color); background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.07), transparent 28%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); }
    .fm-eyebrow { color: #1d4ed8; background: rgba(37,99,235,0.08); }
    .fm-main-card__subtitle { color: #64748b; max-width: 780px; line-height: 1.6; font-size: 0.95rem; }
    .file-management-card__body { padding: 1.75rem; background: #f8fafc; }

    /* --- REFACTOR: HEADER STATS --- */
    .fm-stat-card {
        padding: 1.25rem 1.5rem;
        border-radius: var(--fm-radius-lg, 20px);
        background: #ffffff;
        border: 1px solid var(--fm-border-color, #e2e8f0);
        box-shadow: var(--fm-shadow-soft, 0 10px 25px -10px rgba(15, 23, 42, 0.05));
        transition: var(--fm-transition, all 0.2s ease);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .fm-stat-card:hover {
        box-shadow: var(--fm-shadow-lifted, 0 20px 40px -15px rgba(15, 23, 42, 0.08));
        transform: translateY(-3px);
        border-color: rgba(199, 210, 225, 0.8);
    }
    .fm-stat-card small { display: block; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.65rem; }
    .fm-stat-card strong { display: block; color: #0f172a; font-size: 1.5rem; font-weight: 800; line-height: 1.2; word-break: break-word; }

    /* --- REFACTOR: BACKUP BANNER --- */
    .file-management-backup-panel { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; padding: 1.5rem 1.75rem; border-radius: var(--fm-radius-lg, 20px); background: linear-gradient(105deg, #f0f9ff 0%, #f7f8ff 100%); border: 1px solid #dbeafe; }
    .file-management-backup-panel__title { color: #0f172a; font-size: 1rem; font-weight: 800; margin-bottom: 0.25rem; }
    .file-management-backup-panel__text { color: #475569; font-size: 0.9rem; line-height: 1.65; max-width: 760px; }

    /* --- REFACTOR: FILTER & ACTION ROW --- */
    .fm-input { min-height: 44px; border-radius: var(--fm-radius-md, 16px); border: 1px solid rgba(148, 163, 184, 0.4); background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: var(--fm-transition, all 0.2s ease); }
    .fm-input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }
    .fm-input-group .fm-input { border-right: none; }
    .fm-input-group__append { border-radius: 0 var(--fm-radius-md, 16px) var(--fm-radius-md, 16px) 0; border: 1px solid rgba(148, 163, 184, 0.4); border-left: none; background: #ffffff; color: #64748b; }
    .fm-action-group { gap: 0.75rem; }
    .fm-action-btn { min-height: 44px; border-radius: var(--fm-radius-md, 16px); font-weight: 800; box-shadow: var(--fm-shadow-soft, 0 4px 12px rgba(0,0,0,0.05)); transition: var(--fm-transition, all 0.2s ease); }
    .fm-action-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: var(--fm-shadow-lifted, 0 10px 20px rgba(0,0,0,0.08)); }

    /* --- REFACTOR: CATEGORY GRID --- */
    .fm-category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
    .fm-category-card { padding: 1.25rem 1.5rem; border-radius: var(--fm-radius-lg, 20px); background: #ffffff; border: 1px solid var(--fm-border-color, #e2e8f0); box-shadow: var(--fm-shadow-soft, 0 10px 25px -10px rgba(15, 23, 42, 0.05)); transition: var(--fm-transition, all 0.2s ease); }
    .fm-category-card:hover { box-shadow: var(--fm-shadow-lifted, 0 20px 40px -15px rgba(15, 23, 42, 0.08)); transform: translateY(-3px); border-color: rgba(199, 210, 225, 0.8); }
    .fm-category-card__label { font-weight: 800; color: #0f172a; margin-bottom: 0.5rem; font-size: 1.05rem; }
    .fm-category-card__stat { font-size: 0.9rem; font-weight: 800; color: #1e40af; margin-bottom: 0.5rem; }
    .fm-category-card__desc { font-size: 0.85rem; color: #64748b; line-height: 1.5; }

    /* --- REFACTOR: BULK ACTIONS & TABLE --- */
    .fm-bulk-actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 0.75rem 1.25rem; border-radius: var(--fm-radius-lg, 20px); background: #ffffff; border: 1px solid var(--fm-border-color, #e2e8f0); }
    .fm-bulk-actions .form-check-label { font-size: 0.9rem; }
    .fm-bulk-actions__hint { font-size: 0.85rem; font-weight: 500; color: #475569; }
    .fm-table-wrapper { border: 1px solid var(--fm-border-color, #e2e8f0); border-radius: var(--fm-radius-xl, 24px); overflow: hidden; background: #fff; box-shadow: var(--fm-shadow-soft, 0 10px 25px -10px rgba(15, 23, 42, 0.05)); }
    .fm-table thead th { background: #f8fafc; border-bottom: 1px solid var(--fm-border-color, #e2e8f0); color: #334155; font-size: 0.8rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; padding: 1.15rem 1rem; }
    .fm-table tbody td { padding: 1.15rem 1rem; border-top: 1px solid #f1f5f9; vertical-align: middle; }
    .file-management-col-check { width: 60px; }
    .fm-table-row { transition: background-color 0.18s ease; }
    .fm-table-row:hover { background-color: #f8fafc; }
    .file-management-filecell { display: flex; align-items: center; gap: 1rem; }
    .file-management-filecell__icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 16px; background: linear-gradient(140deg, #0857c3, #307fe2); color: #fff; font-size: 1.25rem; box-shadow: 0 14px 30px -18px rgba(8,87,195,0.55); }
    .file-management-filecell__name { font-weight: 800; color: #0f172a; font-size: 0.95rem; margin-bottom: 0.15rem; }
    .file-management-filecell__meta { font-size: 0.85rem; color: #64748b; word-break: break-all; }
    .file-management-active-tag { display: inline-flex; align-items: center; margin-top: 0.4rem; padding: 0.35rem 0.75rem; border-radius: 999px; background: rgba(239,68,68,0.09); color: #b91c1c; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(239,68,68,0.15); }
    .file-management-folder { font-weight: 800; color: #0f172a; font-size: 0.95rem; margin-bottom: 0.15rem; }
    .file-management-folder__meta { font-size: 0.85rem; color: #64748b; line-height: 1.4; }
    .file-management-size { display: inline-flex; align-items: center; justify-content: flex-end; min-width: 90px; padding: 0.45rem 0.85rem; border-radius: 999px; background: rgba(37,99,235,0.08); color: #1d4ed8; font-weight: 800; border: 1px solid rgba(37,99,235,0.15); }
    .file-management-time { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
    .file-management-download-btn,
    .file-management-delete-btn { min-width: 110px; border-radius: 14px; font-weight: 800; }
    .management-file-checkbox { width: 1.2rem; height: 1.2rem; border-radius: 6px; cursor: pointer; border: 2px solid #cbd5e1; }
    .management-file-checkbox:disabled { cursor: not-allowed; opacity: 0.45; }
    .swal-modern-popup { border: 1px solid rgba(226,232,240,0.95); border-radius: 28px; padding: 1.4rem 1.4rem 1.2rem; box-shadow: 0 30px 80px -35px rgba(15,23,42,0.35); }
    .swal-modern-title { color: #0f172a; font-weight: 800; letter-spacing: -0.02em; }
    .swal-modern-html { color: #475569; font-size: 0.95rem; line-height: 1.65; }
    .swal-modern-confirm, .swal-modern-cancel { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 16px; font-weight: 700; padding: 0.8rem 1.3rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .swal-modern-confirm { background: linear-gradient(135deg, #0f766e, #115e59); color: #fff; box-shadow: 0 16px 34px -22px rgba(15,23,42,0.45); }
    .swal-modern-confirm:hover { transform: translateY(-2px); box-shadow: 0 20px 38px -20px rgba(15,23,42,0.5); }
    .swal-modern-cancel { background: #f1f5f9; color: #334155; margin-left: 0.5rem; border: 1px solid rgba(148,163,184,0.2); }
    .swal-modern-cancel:hover { background: #e2e8f0; transform: translateY(-1px); }
    @media (max-width: 767.98px) {
        .file-management-hero, .fm-main-card__header, .file-management-card__body { padding-left: 1.25rem; padding-right: 1.25rem; }
        .file-management-hero__title { font-size: 1.25rem; }
        .file-management-hero__badge, .fm-action-btn { width: 100%; }
        .fm-table thead th, .fm-table tbody td { padding: 1rem; }
        .fm-bulk-actions { align-items: flex-start; }
    }
</style>
@endsection
