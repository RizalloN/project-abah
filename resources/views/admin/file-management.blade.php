@extends('layouts.admin')

@section('title', 'File Management')

@section('content')
@php
    $importFiles = $files->where('storage_group', 'import_artifacts');
    $databaseFiles = $files->where('storage_group', 'database_backups');
    $importSize = (int) $importFiles->sum('size');
    $databaseSize = (int) $databaseFiles->sum('size');
    $formatManagedBytes = static function (int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1, ',', '.') . ' ' . $units[$power];
    };
@endphp
<div class="container-fluid pt-3 pb-4 file-management-page">
    <div class="fm-page-head d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 font-weight-bold text-dark mb-0"><i class="fas fa-folder-open text-primary mr-2"></i> File Management</h2>
            <span class="text-muted small">Kelola artefak Excel/import dan backup database dalam ruang aksi terpisah.</span>
        </div>
        <span class="fm-admin-badge badge badge-light border border-primary text-primary px-3 py-2"><i class="fas fa-user-shield mr-1"></i> Admin Only</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 8px;"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 8px;"><i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm border-0 fm-main-card" id="file-management-card"
         data-backup-url="{{ route('file-management.database-backup') }}"
         data-delete-url="{{ route('file-management.destroy') }}"
         data-cleanup-url="{{ route('import.cleanup-artifacts') }}"
         data-snapshot-check-url="{{ route('file-management.snapshots.latest-check') }}"
         data-current-scope="import_artifacts">

        <!-- Toolbar & Stats -->
        <div class="card-header bg-white border-bottom py-3 px-4 fm-toolbar">
            <div class="row align-items-center">
                <div class="col-xl-5 col-lg-12 fm-stats-grid pr-xl-4 border-xl-right">
                    <div class="text-center fm-stat">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Total File</div>
                        <div class="h5 mb-0 text-primary font-weight-bold">{{ number_format($totals['files'], 0, ',', '.') }}</div>
                    </div>
                    <div class="text-center fm-stat">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Ukuran</div>
                        <div class="h5 mb-0 text-warning font-weight-bold">{{ $totals['size_human'] }}</div>
                    </div>
                    <div class="text-center fm-stat">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Excel / Import</div>
                        <div class="h5 mb-0 text-success font-weight-bold">{{ number_format($importFiles->count(), 0, ',', '.') }}</div>
                        <div class="small text-muted">{{ $formatManagedBytes($importSize) }}</div>
                    </div>
                    <div class="text-center fm-stat">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Backup DB</div>
                        <div class="h5 mb-0 text-info font-weight-bold">{{ number_format($databaseFiles->count(), 0, ',', '.') }}</div>
                        <div class="small text-muted">{{ $formatManagedBytes($databaseSize) }}</div>
                    </div>
                </div>
                <div class="col-xl-7 col-lg-12 pl-xl-4 mt-3 mt-xl-0">
                    <div class="fm-tools">
                        <div class="fm-scope-switch" role="group" aria-label="Filter jenis file">
                            <button type="button" class="fm-scope-btn active" data-file-scope="import_artifacts">
                                <i class="fas fa-file-excel"></i>
                                <span>Excel / Import</span>
                            </button>
                            <button type="button" class="fm-scope-btn" data-file-scope="database_backups">
                                <i class="fas fa-database"></i>
                                <span>Database Backup</span>
                            </button>
                        </div>

                        <div class="input-group input-group-sm fm-search">
                            <input type="text" id="file-management-search" class="form-control" placeholder="Cari file...">
                            <div class="input-group-append">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                            </div>
                        </div>

                        <div class="fm-aging d-flex align-items-center bg-light px-2 py-1">
                            <label for="file-management-hours" class="mb-0 small font-weight-bold text-muted mr-2">Aging (h)</label>
                            <input type="number" min="1" max="168" id="file-management-hours" class="form-control form-control-sm text-center p-0" value="12">
                        </div>

                        <button type="button" id="btn-file-management-cleanup" class="btn btn-sm btn-outline-primary fm-action-btn" data-scope-action="import_artifacts"><i class="fas fa-broom mr-1"></i> Cleanup Excel</button>
                        <button type="button" id="btn-file-management-snapshot-check" class="btn btn-sm btn-outline-secondary fm-action-btn fm-snapshot-action-btn"><i class="fas fa-sync-alt mr-1"></i> Cek Snapshot Terbaru</button>
                        <button type="button" id="btn-file-management-backup" class="btn btn-sm btn-success fm-action-btn d-none" data-scope-action="database_backups"><i class="fas fa-database mr-1"></i> Backup DB</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4 bg-light">
            <div class="fm-safety-note mb-3">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Excel/import dan backup database dipisah.</strong>
                    <span>Clear atau hapus massal di tab Excel / Import tidak akan menyertakan file backup database. File database hanya bisa dihapus dari tab Database Backup dengan konfirmasi terpisah.</span>
                </div>
            </div>

            <div class="fm-directory-grid mb-4">
                @foreach ($directories as $directory)
                    <div class="fm-directory-item" data-directory-group="{{ $directory['group'] ?? 'import_artifacts' }}">
                        <div class="card h-100 border-0 shadow-sm fm-directory-card">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-start justify-content-between">
                                    <h6 class="font-weight-bold text-dark mb-1">{{ $directory['label'] }}</h6>
                                    <span class="fm-group-pill {{ ($directory['group'] ?? 'import_artifacts') === 'database_backups' ? 'fm-group-pill--db' : 'fm-group-pill--excel' }}">
                                        {{ ($directory['group'] ?? 'import_artifacts') === 'database_backups' ? 'DB' : 'Excel' }}
                                    </span>
                                </div>
                                <div class="small font-weight-bold text-primary mb-2">
                                    {{ number_format($directory['files'], 0, ',', '.') }} file &middot; {{ $directory['size_human'] }}
                                </div>
                                <div class="small text-muted" style="line-height: 1.4;">{{ $directory['description'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card border-0 shadow-sm fm-table-card">
                <div class="card-header bg-white py-3 px-4 fm-table-head">
                    <div>
                        <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-archive text-secondary mr-2"></i> <span id="file-management-table-title">Daftar Excel / Import</span></h6>
                        <div class="small text-muted mt-1" id="file-management-table-subtitle">Aksi massal hanya berlaku untuk artefak Excel/import.</div>
                    </div>
                    <div class="fm-selection-actions">
                        <div class="custom-control custom-checkbox d-inline-block mr-3 mt-1">
                            <input type="checkbox" class="custom-control-input" id="file-management-select-all" {{ $files->isEmpty() ? 'disabled' : '' }}>
                            <label class="custom-control-label small font-weight-bold text-muted" for="file-management-select-all" id="file-management-select-all-label">Pilih Semua Excel</label>
                        </div>
                        <button type="button" id="btn-file-management-delete-selected" class="btn btn-sm btn-outline-danger fm-danger-btn" disabled>
                            <i class="fas fa-trash-alt mr-1"></i> <span id="file-management-delete-label">Hapus Excel Terpilih</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive bg-white fm-table-wrap">
                    <table class="table table-hover mb-0 fm-table">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="text-center border-top-0 border-bottom-0" style="width: 50px;"><i class="far fa-check-square"></i></th>
                                <th class="border-top-0 border-bottom-0">File</th>
                                <th class="border-top-0 border-bottom-0">Jenis</th>
                                <th class="border-top-0 border-bottom-0">Folder</th>
                                <th class="text-right border-top-0 border-bottom-0">Ukuran</th>
                                <th class="border-top-0 border-bottom-0">Modified</th>
                                <th class="text-center border-top-0 border-bottom-0">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="file-management-table-body">
                            @forelse ($files as $file)
                                <tr class="fm-table-row" data-file-row data-storage-group="{{ $file['storage_group'] ?? 'import_artifacts' }}" data-search="{{ strtolower($file['name'] . ' ' . $file['directory_label'] . ' ' . $file['relative_path'] . ' ' . ($file['storage_group_label'] ?? '')) }}">
                                    <td class="text-center align-middle">
                                        <div class="custom-control custom-checkbox d-inline-block">
                                            <input type="checkbox" class="custom-control-input management-file-checkbox" id="check-{{ Str::slug($file['name'].$loop->index) }}" value="{{ $file['relative_path'] }}" data-storage-group="{{ $file['storage_group'] ?? 'import_artifacts' }}" {{ !empty($file['is_active']) ? 'disabled' : '' }}>
                                            <label class="custom-control-label" for="check-{{ Str::slug($file['name'].$loop->index) }}"></label>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-dark">{{ $file['name'] }}</div>
                                        <div class="text-muted small" style="word-break: break-all;">{{ $file['relative_path'] }}</div>
                                        @if(!empty($file['is_active']))
                                            <span class="badge badge-danger mt-1"><i class="fas fa-shield-alt mr-1"></i> Dipakai job aktif</span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        <span class="fm-type-badge {{ !empty($file['is_database_backup']) ? 'fm-type-badge--db' : 'fm-type-badge--excel' }}">
                                            <i class="fas {{ !empty($file['is_database_backup']) ? 'fa-database' : 'fa-file-excel' }} mr-1"></i>
                                            {{ $file['storage_group_label'] ?? 'Excel / Import' }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-dark">{{ $file['directory_label'] }}</div>
                                        <div class="text-muted small">{{ $file['directory_description'] }}</div>
                                    </td>
                                    <td class="text-right font-weight-bold text-primary align-middle">{{ $file['size_human'] }}</td>
                                    <td class="font-weight-bold text-dark align-middle">{{ $file['modified_human'] }}</td>
                                    <td class="text-center align-middle">
                                        @if(str_ends_with(strtolower($file['name']), '.sql') || str_ends_with(strtolower($file['name']), '.sql.gz'))
                                            <a href="{{ route('file-management.download', ['path' => $file['relative_path']]) }}" class="btn btn-sm btn-outline-primary" style="border-radius: 6px;">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-danger file-management-delete-btn ml-1" style="border-radius: 6px;"
                                                data-path="{{ $file['relative_path'] }}"
                                                data-name="{{ $file['name'] }}"
                                                data-storage-group="{{ $file['storage_group'] ?? 'import_artifacts' }}"
                                                {{ !empty($file['is_active']) ? 'disabled' : '' }}>
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        Tidak ada file import tersisa pada folder yang dipantau.
                                    </td>
                                </tr>
                            @endforelse
                            <tr id="file-management-empty-scope-row" class="d-none">
                                <td colspan="7" class="text-center text-muted py-5">
                                    Tidak ada file pada kategori ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
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
        const snapshotCheckUrl = card?.getAttribute('data-snapshot-check-url') || '';
        const searchInput = document.getElementById('file-management-search');
        const selectAll = document.getElementById('file-management-select-all');
        const tableBody = document.getElementById('file-management-table-body');
        const btnDeleteSelected = document.getElementById('btn-file-management-delete-selected');
        const btnBackup = document.getElementById('btn-file-management-backup');
        const btnCleanup = document.getElementById('btn-file-management-cleanup');
        const btnSnapshotCheck = document.getElementById('btn-file-management-snapshot-check');
        const hoursInput = document.getElementById('file-management-hours');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
        const scopeButtons = Array.from(document.querySelectorAll('[data-file-scope]'));
        const directoryCards = Array.from(document.querySelectorAll('[data-directory-group]'));
        const emptyScopeRow = document.getElementById('file-management-empty-scope-row');
        const tableTitle = document.getElementById('file-management-table-title');
        const tableSubtitle = document.getElementById('file-management-table-subtitle');
        const selectAllLabel = document.getElementById('file-management-select-all-label');
        const deleteLabel = document.getElementById('file-management-delete-label');
        let currentScope = card?.getAttribute('data-current-scope') || 'import_artifacts';
        const scopeCopy = {
            import_artifacts: {
                title: 'Daftar Excel / Import',
                subtitle: 'Aksi massal hanya berlaku untuk artefak Excel/import. Backup database tidak ikut terseleksi.',
                select: 'Pilih Semua Excel',
                delete: 'Hapus Excel Terpilih',
                cleanupVisible: true,
            },
            database_backups: {
                title: 'Daftar Database Backup',
                subtitle: 'Backup database dipisah dan perlu konfirmasi eksplisit sebelum dihapus.',
                select: 'Pilih Backup DB',
                delete: 'Hapus Backup Terpilih',
                cleanupVisible: false,
            },
        };

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
                const row = checkbox.closest('.fm-table-row');
                return row
                    && row.getAttribute('data-storage-group') === currentScope
                    && !row.classList.contains('d-none')
                    && !checkbox.disabled;
            });
        }

        function updateSelectionState() {
            const visible = getVisibleCheckboxes();
            const selectedVisible = visible.filter((checkbox) => checkbox.checked);
            const selectedAll = getCheckboxes().filter((checkbox) => checkbox.checked && checkbox.dataset.storageGroup === currentScope);
            if (btnDeleteSelected) {
                btnDeleteSelected.disabled = selectedAll.length === 0;
            }
            if (selectAll) {
                selectAll.disabled = visible.length === 0;
                selectAll.checked = visible.length > 0 && selectedVisible.length === visible.length;
                selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visible.length;
            }

            const visibleRows = Array.from(tableBody?.querySelectorAll('.fm-table-row') || [])
                .filter((row) => row.getAttribute('data-storage-group') === currentScope && !row.classList.contains('d-none'));
            if (emptyScopeRow) {
                emptyScopeRow.classList.toggle('d-none', visibleRows.length > 0);
            }
        }

        function applySearch() {
            const keyword = String(searchInput?.value || '').trim().toLowerCase();
            Array.from(tableBody?.querySelectorAll('.fm-table-row') || []).forEach((row) => {
                if (!row) return;
                const haystack = String(row.getAttribute('data-search') || '');
                const matchesScope = row.getAttribute('data-storage-group') === currentScope;
                const matchesKeyword = !keyword || haystack.includes(keyword);
                row.classList.toggle('d-none', !matchesScope || !matchesKeyword);
            });
            updateSelectionState();
        }

        function applyScope(scope) {
            currentScope = scope === 'database_backups' ? 'database_backups' : 'import_artifacts';
            if (card) {
                card.setAttribute('data-current-scope', currentScope);
            }

            scopeButtons.forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-file-scope') === currentScope);
            });

            directoryCards.forEach((directoryCard) => {
                directoryCard.classList.toggle('d-none', directoryCard.getAttribute('data-directory-group') !== currentScope);
            });

            const copy = scopeCopy[currentScope] || scopeCopy.import_artifacts;
            if (tableTitle) tableTitle.textContent = copy.title;
            if (tableSubtitle) tableSubtitle.textContent = copy.subtitle;
            if (selectAllLabel) selectAllLabel.textContent = copy.select;
            if (deleteLabel) deleteLabel.textContent = copy.delete;
            if (btnCleanup) btnCleanup.classList.toggle('d-none', !copy.cleanupVisible);
            if (btnBackup) btnBackup.classList.toggle('d-none', currentScope !== 'database_backups');

            getCheckboxes().forEach((checkbox) => {
                if (checkbox.dataset.storageGroup !== currentScope) {
                    checkbox.checked = false;
                }
            });

            applySearch();
        }

        async function postJson(url, payload) {
            try {
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
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error('Response server tidak valid (invalid JSON)');
                }

                if (!response.ok) {
                    const errorMsg = data.message || data.error || `Server error: ${response.status} ${response.statusText}`;
                    throw new Error(errorMsg);
                }

                if (data.status === 'error') {
                    throw new Error(data.message || 'Request gagal diproses oleh server.');
                }

                return data;
            } catch (error) {
                console.error('postJson error:', error);
                throw error;
            }
        }

        async function deleteFiles(paths, label, scope) {
            if (!paths.length) {
                return;
            }

            const deleteScope = scope || currentScope;
            const isDatabaseScope = deleteScope === 'database_backups';
            const confirm = await themedSwal({
                icon: isDatabaseScope ? 'error' : 'warning',
                title: isDatabaseScope ? 'Hapus Backup Database?' : 'Hapus File Excel / Import',
                html: isDatabaseScope
                    ? `Anda akan menghapus <b>${paths.length}</b> file backup database${label ? ` dari <b>${escapeHtml(label)}</b>` : ''}.<br><b>Aksi ini tidak digunakan untuk clear Excel/import dan bersifat permanen.</b>`
                    : `Anda akan menghapus <b>${paths.length}</b> file Excel/import${label ? ` dari <b>${escapeHtml(label)}</b>` : ''}.<br>Backup database tidak termasuk aksi ini.`,
                showCancelButton: true,
                confirmButtonText: isDatabaseScope ? 'Ya, hapus backup' : 'Ya, hapus file',
                cancelButtonText: 'Batal',
                allowOutsideClick: false,
                allowEscapeKey: false,
            });

            if (!confirm.isConfirmed) {
                return;
            }

            Swal.fire({
                title: 'Memproses Penghapusan...',
                html: '<div class="text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Menghapus file, mohon tunggu...</div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
            });

            let payload = null;
            try {
                payload = await postJson(deleteUrl, { paths, scope: deleteScope });
            } finally {
                Swal.close();
            }

            if (!payload) {
                throw new Error('Tidak ada response dari server');
            }

            const isPartial = payload.status === 'partial' || (payload.failed_count && payload.failed_count > 0);
            const icon = isPartial ? 'warning' : 'success';
            const title = isPartial ? 'Selesai dengan Peringatan' : 'Selesai';

            let html = `<div class="text-left">
                ${payload.message ? `<p class="mb-3">${escapeHtml(payload.message)}</p>` : ''}
            `;

            if (isPartial && payload.failed_items && payload.failed_items.length > 0) {
                html += `<div class="alert alert-warning mb-0" style="font-size: 0.9rem;">
                    <strong>File yang gagal dihapus:</strong>
                    <ul class="mb-0 mt-2">`;
                payload.failed_items.slice(0, 5).forEach(item => {
                    html += `<li>${escapeHtml(item.path)} - ${escapeHtml(item.reason)}</li>`;
                });
                if (payload.failed_items.length > 5) {
                    html += `<li>... dan ${payload.failed_items.length - 5} file lainnya</li>`;
                }
                html += `</ul></div>`;
            }

            html += `</div>`;

            await themedSwal({
                icon: icon,
                title: title,
                html: html,
            });

            if (!isPartial || payload.deleted_count > 0) {
                window.location.reload();
            }

            return payload;
        }

        searchInput?.addEventListener('input', applySearch);

        scopeButtons.forEach((button) => {
            button.addEventListener('click', function () {
                applyScope(button.getAttribute('data-file-scope') || 'import_artifacts');
            });
        });

        selectAll?.addEventListener('change', function () {
            const visible = getVisibleCheckboxes();
            visible.forEach((checkbox) => {
                if (!checkbox.disabled) {
                    checkbox.checked = !!selectAll.checked;
                }
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

            if (button.disabled) return;
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menghapus...';
            
            try {
                await deleteFiles(
                    [button.getAttribute('data-path') || ''],
                    button.getAttribute('data-name') || '',
                    button.getAttribute('data-storage-group') || 'import_artifacts'
                );
            } catch (error) {
                const errorMsg = error.message || 'Terjadi kesalahan saat menghapus file.';
                console.error('Delete failed:', error);
                
                await themedSwal({
                    icon: 'error',
                    title: 'Gagal Menghapus',
                    text: errorMsg,
                    didClose: () => {
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-trash-alt mr-1"></i>Hapus';
                    }
                });
            } finally {
                if (button.isConnected) {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-trash-alt mr-1"></i>Hapus';
                }
            }
        });

        btnDeleteSelected?.addEventListener('click', async function () {
            const paths = getCheckboxes()
                .filter((checkbox) => checkbox.checked && checkbox.dataset.storageGroup === currentScope)
                .map((checkbox) => checkbox.value)
                .filter(Boolean);

            if (!paths.length) {
                await themedSwal({
                    icon: 'info',
                    title: 'Tidak Ada File',
                    text: 'Pilih file terlebih dahulu untuk dihapus.',
                });
                return;
            }

            btnDeleteSelected.disabled = true;
            const originalText = btnDeleteSelected.innerHTML;
            btnDeleteSelected.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Menghapus...';

            try {
                await deleteFiles(paths, currentScope === 'database_backups' ? 'backup terpilih' : 'file terpilih', currentScope);
            } catch (error) {
                const errorMsg = error.message || 'Terjadi kesalahan saat menghapus file.';
                console.error('Bulk delete failed:', error);
                
                await themedSwal({
                    icon: 'error',
                    title: 'Gagal Menghapus',
                    text: errorMsg,
                });
            } finally {
                btnDeleteSelected.disabled = false;
                btnDeleteSelected.innerHTML = originalText;
            }
        });

        btnCleanup?.addEventListener('click', async function () {
            const hours = Math.max(1, Math.min(168, Number(hoursInput?.value || 12)));
            const confirm = await themedSwal({
                icon: 'question',
                title: 'Cleanup Excel / Import',
                html: `Jalankan cleanup artefak Excel/import lebih lama dari <b>${hours}</b> jam?<br>Backup database tidak termasuk aksi ini.`,
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

        btnSnapshotCheck?.addEventListener('click', async function () {
            if (!snapshotCheckUrl) return;

            const originalHtml = btnSnapshotCheck.innerHTML;
            btnSnapshotCheck.disabled = true;
            btnSnapshotCheck.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Memeriksa...';

            try {
                const payload = await postJson(snapshotCheckUrl, {});
                const sources = Array.isArray(payload.queued_sources) ? payload.queued_sources : [];
                const sourceList = sources.length
                    ? `<ul class="text-left mb-0 mt-2 small">${sources.map((item) => `<li><b>${escapeHtml(item.table)}</b>: ${escapeHtml(item.period)}</li>`).join('')}</ul>`
                    : '';

                await themedSwal({
                    icon: payload.status === 'completed' ? 'info' : 'success',
                    title: payload.status === 'completed' ? 'Snapshot Tidak Perlu Dijadwalkan' : 'Pengecekan Snapshot Dijadwalkan',
                    html: `<div>${escapeHtml(payload.message || 'Pengecekan snapshot telah diproses.')}</div>${sourceList}`,
                });
            } catch (error) {
                await themedSwal({
                    icon: 'error',
                    title: 'Pengecekan Snapshot Gagal',
                    text: error.message || 'Tidak dapat menjadwalkan pengecekan snapshot.',
                });
            } finally {
                btnSnapshotCheck.disabled = false;
                btnSnapshotCheck.innerHTML = originalHtml;
            }
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

            if (!confirm.isConfirmed) return;

            btnBackup.disabled = true;
            try {
                const startPayload = await postJson(backupUrl, {});
                const backupId = startPayload.backup_id;

                if (!backupId) throw new Error('Gagal menginisialisasi proses backup.');

                let polling = true;
                const statusUrlTemplate = '{{ route("file-management.database-backup.status", ["backupId" => ":id"]) }}';

                Swal.fire({
                    title: 'Memproses Backup...',
                    html: `
                        <div class="mb-3">
                            <div class="progress" style="height: 25px; border-radius: 12px; overflow: hidden; background: #f1f5f9; border: 1px solid #e2e8f0;">
                                <div id="backup-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                    role="progressbar" style="width: 0%; background: linear-gradient(90deg, #0857c3, #307fe2); transition: width 0.4s ease; font-weight: 800; font-size: 0.85rem; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                                    0%
                                </div>
                            </div>
                        </div>
                        <div id="backup-status-text" class="text-muted small fw-bold" style="letter-spacing: 0.02em;">Menyiapkan database...</div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        const poll = async () => {
                            if (!polling) return;
                            try {
                                const statusUrl = statusUrlTemplate.replace(':id', backupId);
                                const response = await fetch(statusUrl);
                                const status = await response.json();
                                if (!response.ok) throw new Error(status.message || 'Gagal mengambil status backup.');

                                if (status.status === 'processing' || status.status === 'starting' || status.status === 'stalled') {
                                    const percent = status.progress_percent || 0;
                                    const bar = document.getElementById('backup-progress-bar');
                                    const text = document.getElementById('backup-status-text');
                                    
                                    if (bar) {
                                        bar.style.width = percent + '%';
                                        bar.innerText = percent + '%';
                                    }
                                    if (text) text.innerText = status.message || (status.status === 'stalled' ? 'Backup masih berjalan, menunggu update ukuran file...' : 'Mencadangkan data...');

                                    setTimeout(poll, status.status === 'stalled' ? 2000 : 700);
                                } else if (status.status === 'completed') {
                                    polling = false;
                                    Swal.close();
                                    
                                    await themedSwal({
                                        icon: 'success',
                                        title: 'Backup Selesai',
                                        html: `File <b>${escapeHtml(status.file.name)}</b> berhasil dibuat.`,
                                    });

                                    if (status.file.download_url) {
                                        window.location.assign(status.file.download_url);
                                    }
                                    window.setTimeout(() => window.location.reload(), 700);
                                } else if (status.status === 'failed') {
                                    polling = false;
                                    throw new Error(status.message || 'Backup gagal diproses.');
                                } else {
                                    setTimeout(poll, 1000);
                                }
                            } catch (error) {
                                polling = false;
                                Swal.close();
                                themedSwal({
                                    icon: 'error',
                                    title: 'Backup Gagal',
                                    text: error.message
                                });
                                btnBackup.disabled = false;
                            }
                        };
                        poll();
                    }
                });

            } catch (error) {
                btnBackup.disabled = false;
                themedSwal({
                    icon: 'error',
                    title: 'Kesalahan',
                    text: error.message
                });
            }
        });

        tableBody?.addEventListener('click', function (event) {
            const row = event.target.closest('.fm-table-row');
            if (!row || event.target.closest('button, input, a')) return;

            const checkbox = row.querySelector('.management-file-checkbox');
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                updateSelectionState();
            }
        });

        applyScope(currentScope);
    });
</script>
@endsection

@section('styles')
<style>
    .file-management-page {
        color: #0f172a;
    }

    .file-management-page *,
    .file-management-page *::before,
    .file-management-page *::after {
        box-sizing: border-box;
    }

    .fm-admin-badge,
    .fm-main-card,
    .fm-table-card,
    .fm-directory-card {
        border-radius: 12px !important;
    }

    .fm-main-card,
    .fm-table-card {
        overflow: hidden;
    }

    .fm-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .fm-stat {
        min-width: 0;
        padding: 0.35rem 0.45rem;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .fm-stat .h5,
    .fm-stat .small {
        overflow-wrap: anywhere;
    }

    .fm-tools {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.55rem;
        min-width: 0;
    }

    .fm-scope-switch {
        display: inline-grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.25rem;
        padding: 0.25rem;
        border-radius: 10px;
        background: #eef2f7;
        border: 1px solid #dbe5ef;
    }

    .fm-scope-btn {
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        border: 0;
        border-radius: 8px;
        padding: 0.35rem 0.65rem;
        background: transparent;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .fm-scope-btn.active {
        background: #ffffff;
        color: #0857c3;
        box-shadow: 0 8px 20px -16px rgba(15, 23, 42, 0.35);
    }

    .fm-search {
        width: min(230px, 100%);
    }

    .fm-search .form-control {
        border-radius: 8px 0 0 8px;
    }

    .fm-search .input-group-text {
        border-radius: 0 8px 8px 0;
    }

    .fm-aging {
        border-radius: 8px;
        border: 1px solid #ced4da;
        min-height: 32px;
    }

    .fm-aging input {
        width: 44px;
        border: none;
        background: transparent;
        font-weight: 800;
    }

    .fm-action-btn,
    .fm-danger-btn {
        border-radius: 8px !important;
        min-height: 32px;
        font-weight: 800;
    }

    .fm-snapshot-action-btn {
        color: #334155;
        border-color: #cbd5e1;
        background: #ffffff;
    }

    .fm-snapshot-action-btn:hover,
    .fm-snapshot-action-btn:focus {
        color: #0f4c81;
        border-color: #93c5fd;
        background: #eff6ff;
        box-shadow: none;
    }

    .fm-safety-note {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.8rem 0.95rem;
        border-radius: 12px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1e3a8a;
        font-size: 0.86rem;
    }

    .fm-safety-note i {
        margin-top: 0.15rem;
    }

    .fm-safety-note strong,
    .fm-safety-note span {
        display: block;
        line-height: 1.45;
    }

    .fm-directory-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.9rem;
        align-items: stretch;
    }

    .fm-directory-item {
        min-width: 0;
    }

    .fm-directory-card {
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .fm-directory-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px -28px rgba(15, 23, 42, 0.42) !important;
    }

    .fm-group-pill,
    .fm-type-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        font-size: 0.66rem;
        font-weight: 900;
        line-height: 1;
        white-space: nowrap;
    }

    .fm-group-pill {
        padding: 0.28rem 0.45rem;
    }

    .fm-type-badge {
        padding: 0.4rem 0.55rem;
    }

    .fm-group-pill--excel,
    .fm-type-badge--excel {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #bbf7d0;
    }

    .fm-group-pill--db,
    .fm-type-badge--db {
        background: #eff6ff;
        color: #0857c3;
        border: 1px solid #bfdbfe;
    }

    .fm-table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .fm-selection-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.65rem;
        flex-wrap: wrap;
    }

    .fm-table-wrap {
        max-height: min(680px, calc(100dvh - 320px));
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }

    .fm-table {
        min-width: 980px;
        font-size: 0.84rem;
    }

    .fm-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8fafc;
        white-space: nowrap;
    }

    .fm-table-row {
        transition: background-color 0.18s ease;
        cursor: pointer;
    }

    .fm-table-row:hover {
        background-color: #f8fafc;
    }

    .fm-table-row td {
        vertical-align: middle;
    }

    .swal-modern-popup { border: 1px solid rgba(226,232,240,0.95); border-radius: 28px; padding: 1.4rem 1.4rem 1.2rem; box-shadow: 0 30px 80px -35px rgba(15,23,42,0.35); }
    .swal-modern-title { color: #0f172a; font-weight: 800; letter-spacing: 0; }
    .swal-modern-html { color: #475569; font-size: 0.95rem; line-height: 1.65; }
    .swal-modern-confirm, .swal-modern-cancel { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 16px; font-weight: 700; padding: 0.8rem 1.3rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .swal-modern-confirm { background: linear-gradient(135deg, #0f766e, #115e59); color: #fff; box-shadow: 0 16px 34px -22px rgba(15,23,42,0.45); }
    .swal-modern-confirm:hover { transform: translateY(-2px); box-shadow: 0 20px 38px -20px rgba(15,23,42,0.5); }
    .swal-modern-cancel { background: #f1f5f9; color: #334155; margin-left: 0.5rem; border: 1px solid rgba(148,163,184,0.2); }
    .swal-modern-cancel:hover { background: #e2e8f0; transform: translateY(-1px); }

    @media (min-width: 1200px) {
        .border-xl-right {
            border-right: 1px solid #dee2e6;
        }
    }

    @media (max-width: 1199.98px) {
        .fm-tools {
            justify-content: flex-start;
        }

        .fm-search {
            flex: 1 1 220px;
        }
    }

    @media (max-width: 991.98px) {
        .fm-page-head {
            align-items: flex-start !important;
            flex-direction: column;
            gap: 0.75rem;
        }

        .fm-admin-badge {
            align-self: flex-start;
        }

        .fm-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fm-toolbar,
        .fm-main-card .card-body {
            padding: 1rem !important;
        }

        .fm-table-wrap {
            max-height: min(620px, calc(100dvh - 280px));
        }
    }

    @media (max-width: 767.98px) {
        .fm-tools,
        .fm-selection-actions {
            align-items: stretch;
            flex-direction: column;
            width: 100%;
        }

        .fm-scope-switch,
        .fm-search,
        .fm-aging,
        .fm-action-btn,
        .fm-danger-btn {
            width: 100%;
        }

        .fm-scope-btn {
            white-space: normal;
            min-height: 40px;
        }

        .fm-aging {
            justify-content: space-between;
        }

        .fm-table-head {
            align-items: stretch;
            flex-direction: column;
        }

        .fm-table-wrap {
            max-height: min(560px, calc(100dvh - 250px));
        }
    }

    @media (max-width: 575.98px) {
        .file-management-page {
            padding-left: 0.35rem;
            padding-right: 0.35rem;
        }

        .fm-stats-grid {
            grid-template-columns: 1fr;
        }

        .fm-directory-grid {
            grid-template-columns: 1fr;
            gap: 0.65rem;
        }

        .fm-safety-note {
            padding: 0.7rem;
            font-size: 0.8rem;
        }

        .fm-table {
            min-width: 860px;
            font-size: 0.78rem;
        }

        .swal-modern-popup {
            border-radius: 18px;
            padding: 1rem;
        }
    }
</style>
@endsection
