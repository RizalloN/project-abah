@extends('layouts.admin')

@section('title', 'File Management')

@section('content')
<div class="container-fluid pt-3 pb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 font-weight-bold text-dark mb-0"><i class="fas fa-folder-open text-primary mr-2"></i> File Management</h2>
            <span class="text-muted small">Kelola file bekas import dan backup database aktif.</span>
        </div>
        <span class="badge badge-light border border-primary text-primary px-3 py-2" style="border-radius: 8px;"><i class="fas fa-user-shield mr-1"></i> Admin Only</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 8px;"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 8px;"><i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm border-0 fm-main-card" style="border-radius: 12px; overflow: hidden;" id="file-management-card"
         data-backup-url="{{ route('file-management.database-backup') }}"
         data-delete-url="{{ route('file-management.destroy') }}"
         data-cleanup-url="{{ route('import.cleanup-artifacts') }}">

        <!-- Toolbar & Stats -->
        <div class="card-header bg-white border-bottom py-3 px-4">
            <div class="row align-items-center">
                <div class="col-lg-5 d-flex justify-content-between pr-4 border-right">
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Total File</div>
                        <div class="h5 mb-0 text-primary font-weight-bold">{{ number_format($totals['files'], 0, ',', '.') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Ukuran</div>
                        <div class="h5 mb-0 text-warning font-weight-bold">{{ $totals['size_human'] }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Folder</div>
                        <div class="h5 mb-0 text-success font-weight-bold">{{ number_format($totals['directories'], 0, ',', '.') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted small text-uppercase font-weight-bold" style="font-size: 0.7rem;">Aktif</div>
                        <div class="h5 mb-0 text-info font-weight-bold">{{ number_format($totals['active_files'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-lg-7 pl-4">
                    <div class="d-flex align-items-center justify-content-end flex-wrap gap-2">
                        <div class="input-group input-group-sm mr-2" style="width: 200px;">
                            <input type="text" id="file-management-search" class="form-control" placeholder="Cari file..." style="border-radius: 6px 0 0 6px;">
                            <div class="input-group-append">
                                <span class="input-group-text bg-white" style="border-radius: 0 6px 6px 0;"><i class="fas fa-search"></i></span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center bg-light px-2 py-1 mr-2" style="border-radius: 6px; border: 1px solid #ced4da;">
                            <label for="file-management-hours" class="mb-0 small font-weight-bold text-muted mr-2">Aging (h)</label>
                            <input type="number" min="1" max="168" id="file-management-hours" class="form-control form-control-sm text-center p-0" value="12" style="width: 40px; border: none; background: transparent; font-weight: bold;">
                        </div>

                        <button type="button" id="btn-file-management-cleanup" class="btn btn-sm btn-outline-primary mr-1" style="border-radius: 6px;"><i class="fas fa-broom mr-1"></i> Cleanup</button>
                        <button type="button" id="btn-file-management-backup" class="btn btn-sm btn-success" style="border-radius: 6px;"><i class="fas fa-database mr-1"></i> Backup</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4 bg-light">
            <div class="row mb-4">
                @foreach ($directories as $directory)
                    <div class="col-lg-3 col-md-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 10px;">
                            <div class="card-body p-3">
                                <h6 class="font-weight-bold text-dark mb-1">{{ $directory['label'] }}</h6>
                                <div class="small font-weight-bold text-primary mb-2">
                                    {{ number_format($directory['files'], 0, ',', '.') }} file &middot; {{ $directory['size_human'] }}
                                </div>
                                <div class="small text-muted" style="line-height: 1.4;">{{ $directory['description'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap">
                    <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-archive text-secondary mr-2"></i> Daftar File Tersimpan</h6>
                    <div class="d-flex align-items-center">
                        <div class="custom-control custom-checkbox d-inline-block mr-3 mt-1">
                            <input type="checkbox" class="custom-control-input" id="file-management-select-all" {{ $files->isEmpty() ? 'disabled' : '' }}>
                            <label class="custom-control-label small font-weight-bold text-muted" for="file-management-select-all">Pilih Semua</label>
                        </div>
                        <button type="button" id="btn-file-management-delete-selected" class="btn btn-sm btn-outline-danger" style="border-radius: 6px;" disabled>
                            <i class="fas fa-trash-alt mr-1"></i> Hapus Terpilih
                        </button>
                    </div>
                </div>

                <div class="table-responsive bg-white">
                    <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="text-center border-top-0 border-bottom-0" style="width: 50px;"><i class="far fa-check-square"></i></th>
                                <th class="border-top-0 border-bottom-0">File</th>
                                <th class="border-top-0 border-bottom-0">Folder</th>
                                <th class="text-right border-top-0 border-bottom-0">Ukuran</th>
                                <th class="border-top-0 border-bottom-0">Modified</th>
                                <th class="text-center border-top-0 border-bottom-0">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="file-management-table-body">
                            @forelse ($files as $file)
                                <tr class="fm-table-row" data-file-row data-search="{{ strtolower($file['name'] . ' ' . $file['directory_label'] . ' ' . $file['relative_path']) }}">
                                    <td class="text-center align-middle">
                                        <div class="custom-control custom-checkbox d-inline-block">
                                            <input type="checkbox" class="custom-control-input management-file-checkbox" id="check-{{ Str::slug($file['name'].$loop->index) }}" value="{{ $file['relative_path'] }}" {{ !empty($file['is_active']) ? 'disabled' : '' }}>
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
                                                {{ !empty($file['is_active']) ? 'disabled' : '' }}>
                                            <i class="fas fa-trash-alt"></i>
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
                const row = checkbox.closest('.fm-table-row');
                return row && !row.classList.contains('d-none') && !checkbox.disabled;
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
                const row = checkbox.closest('.fm-table-row');
                if (!row) return;
                const haystack = String(row.getAttribute('data-search') || '');
                row.classList.toggle('d-none', keyword && !haystack.includes(keyword));
            });
            updateSelectionState();
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
                payload = await postJson(deleteUrl, { paths });
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
                await deleteFiles([button.getAttribute('data-path') || ''], button.getAttribute('data-name') || '');
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
                .filter((checkbox) => checkbox.checked)
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
                await deleteFiles(paths, 'file terpilih');
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

        updateSelectionState();
        applySearch();
    });
</script>
@endsection

@section('styles')
<style>
    .fm-table-row { transition: background-color 0.18s ease; cursor: pointer; }
    .fm-table-row:hover { background-color: #f8fafc; }
    .swal-modern-popup { border: 1px solid rgba(226,232,240,0.95); border-radius: 28px; padding: 1.4rem 1.4rem 1.2rem; box-shadow: 0 30px 80px -35px rgba(15,23,42,0.35); }
    .swal-modern-title { color: #0f172a; font-weight: 800; letter-spacing: -0.02em; }
    .swal-modern-html { color: #475569; font-size: 0.95rem; line-height: 1.65; }
    .swal-modern-confirm, .swal-modern-cancel { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 16px; font-weight: 700; padding: 0.8rem 1.3rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .swal-modern-confirm { background: linear-gradient(135deg, #0f766e, #115e59); color: #fff; box-shadow: 0 16px 34px -22px rgba(15,23,42,0.45); }
    .swal-modern-confirm:hover { transform: translateY(-2px); box-shadow: 0 20px 38px -20px rgba(15,23,42,0.5); }
    .swal-modern-cancel { background: #f1f5f9; color: #334155; margin-left: 0.5rem; border: 1px solid rgba(148,163,184,0.2); }
    .swal-modern-cancel:hover { background: #e2e8f0; transform: translateY(-1px); }
</style>
@endsection
