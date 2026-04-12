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

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="file-management-stat">
            <small>Total File</small>
            <strong>{{ number_format($totals['files'], 0, ',', '.') }}</strong>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="file-management-stat">
            <small>Total Ukuran</small>
            <strong>{{ $totals['size_human'] }}</strong>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="file-management-stat">
            <small>Folder Terkelola</small>
            <strong>{{ number_format($totals['directories'], 0, ',', '.') }}</strong>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="file-management-stat">
            <small>Modified Terakhir</small>
            <strong>{{ $totals['latest_modified_at'] ? $totals['latest_modified_at']->format('d M Y H:i') : '-' }}</strong>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="file-management-stat">
            <small>File Aktif</small>
            <strong>{{ number_format($totals['active_files'] ?? 0, 0, ',', '.') }}</strong>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 file-management-card" id="file-management-card"
     data-delete-url="{{ route('file-management.destroy') }}"
     data-cleanup-url="{{ route('import.cleanup-artifacts') }}">
    <div class="card-header bg-white border-0 file-management-card__header">
        <span class="file-management-card__eyebrow">Managed Storage</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-archive text-primary mr-2"></i> Daftar File Import
        </h5>
        <p class="file-management-card__subtitle mb-0">Folder yang dipantau: import excel, casa brilink, report PH, performance PIS, dan workspace staging.</p>
    </div>
    <div class="card-body file-management-card__body">
        <div class="row align-items-end mb-3">
            <div class="col-lg-5 mb-3 mb-lg-0">
                <label class="font-weight-bold text-dark">Cari File</label>
                <div class="input-group file-management-input-group">
                    <input type="text" id="file-management-search" class="form-control file-management-input" placeholder="Nama file, folder, atau ekstensi">
                    <div class="input-group-append">
                        <span class="input-group-text file-management-input-group__append"><i class="fas fa-search"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 mb-3 mb-lg-0">
                <label class="font-weight-bold text-dark">Aging Cleanup</label>
                <input type="number" min="1" max="168" id="file-management-hours" class="form-control file-management-input" value="12">
            </div>
            <div class="col-lg-5">
                <div class="d-flex flex-wrap justify-content-lg-end" style="gap:.65rem">
                    <button type="button" id="btn-file-management-cleanup" class="btn btn-outline-primary file-management-action-btn">
                        <i class="fas fa-broom mr-2"></i> Cleanup Otomatis
                    </button>
                    <button type="button" id="btn-file-management-delete-selected" class="btn btn-danger file-management-action-btn" disabled>
                        <i class="fas fa-trash-alt mr-2"></i> Hapus Terpilih
                    </button>
                </div>
            </div>
        </div>

        <div class="file-management-directory-strip mb-3">
            @foreach ($directories as $directory)
                <div class="file-management-directory-pill">
                    <div class="file-management-directory-pill__label">{{ $directory['label'] }}</div>
                    <div class="file-management-directory-pill__meta">
                        {{ number_format($directory['files'], 0, ',', '.') }} file &middot; {{ $directory['size_human'] }}
                    </div>
                    <div class="file-management-directory-pill__desc">{{ $directory['description'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="file-management-bulkbar mb-3">
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="file-management-select-all" {{ $files->isEmpty() ? 'disabled' : '' }}>
                <label class="form-check-label font-weight-bold" for="file-management-select-all">Pilih semua file yang terlihat</label>
            </div>
            <div class="file-management-bulkbar__hint">
                Gunakan tombol hapus untuk membersihkan file lama. File di luar folder terkelola tidak bisa dihapus dari halaman ini.
            </div>
        </div>

        <div class="table-responsive file-management-table-wrap">
            <table class="table table-hover mb-0 file-management-table">
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
                        <tr class="file-management-row" data-file-row data-search="{{ strtolower($file['name'] . ' ' . $file['directory_label'] . ' ' . $file['relative_path']) }}">
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
        const cleanupUrl = card?.getAttribute('data-cleanup-url') || '';
        const searchInput = document.getElementById('file-management-search');
        const selectAll = document.getElementById('file-management-select-all');
        const tableBody = document.getElementById('file-management-table-body');
        const btnDeleteSelected = document.getElementById('btn-file-management-delete-selected');
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

        updateSelectionState();
        applySearch();
    });
</script>
@endsection

@section('styles')
<style>
    .file-management-hero{position:relative;overflow:hidden;border-radius:24px;padding:1.45rem 1.5rem;background:radial-gradient(circle at top right,rgba(37,99,235,.22),transparent 30%),linear-gradient(135deg,#f8fbff 0%,#eef4ff 46%,#dbeafe 100%);border:1px solid rgba(37,99,235,.16);box-shadow:0 22px 45px -32px rgba(29,78,216,.4)}
    .file-management-hero__glow{position:absolute;top:-48px;right:-30px;width:170px;height:170px;border-radius:999px;background:rgba(14,165,233,.16);filter:blur(10px)}
    .file-management-hero__eyebrow,.file-management-card__eyebrow{display:inline-block;margin-bottom:.55rem;padding:.35rem .7rem;border-radius:999px;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .file-management-hero__eyebrow{color:#1d4ed8;background:rgba(255,255,255,.72);border:1px solid rgba(37,99,235,.16)}
    .file-management-hero__title{color:#0f3f8c;font-size:1.4rem;font-weight:800;letter-spacing:-.03em;margin-bottom:.35rem}
    .file-management-hero__text{color:#31527c;line-height:1.7;max-width:760px}
    .file-management-hero__badge{display:inline-flex;align-items:center;min-height:48px;padding:.8rem 1rem;border-radius:18px;background:rgba(255,255,255,.84);border:1px solid rgba(37,99,235,.14);color:#0f3f8c;font-weight:700;box-shadow:0 18px 32px -24px rgba(29,78,216,.3)}
    .file-management-flash{padding:1rem 1.1rem;border-radius:18px;font-weight:700}
    .file-management-flash--success{background:rgba(220,252,231,.85);border:1px solid rgba(34,197,94,.2);color:#166534}
    .file-management-flash--danger{background:rgba(254,226,226,.88);border:1px solid rgba(239,68,68,.18);color:#b91c1c}
    .file-management-stat{height:100%;padding:1rem 1.05rem;border-radius:20px;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid rgba(148,163,184,.22)}
    .file-management-stat small{display:block;color:#64748b;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.45rem}
    .file-management-stat strong{display:block;color:#0f172a;font-size:1.05rem;font-weight:800;line-height:1.45;word-break:break-word}
    .file-management-card{border-radius:26px;overflow:hidden;box-shadow:0 28px 60px -40px rgba(15,23,42,.32)!important}
    .file-management-card__header{padding:1.45rem 1.5rem 1rem;background:radial-gradient(circle at top left,rgba(59,130,246,.09),transparent 28%),linear-gradient(180deg,#fff 0%,#f8fafc 100%)}
    .file-management-card__eyebrow{color:#1d4ed8;background:rgba(37,99,235,.08)}
    .file-management-card__subtitle{color:#64748b;max-width:780px;line-height:1.6}
    .file-management-card__body{padding:1.5rem}
    .file-management-input{min-height:48px;border-radius:16px;border:1px solid #c7dcfb;background:#f4f9ff}
    .file-management-input:focus{border-color:#307fe2;background:#fff;box-shadow:0 0 0 4px rgba(48,127,226,.16)}
    .file-management-input-group__append{border-radius:0 16px 16px 0;border:1px solid #c7dcfb;background:#fff;color:#4b5563}
    .file-management-action-btn{min-height:48px;border-radius:16px;font-weight:800}
    .file-management-directory-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem}
    .file-management-directory-pill{padding:1rem 1rem .95rem;border-radius:20px;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid rgba(148,163,184,.18)}
    .file-management-directory-pill__label{font-weight:800;color:#0f172a;margin-bottom:.3rem}
    .file-management-directory-pill__meta{font-size:.84rem;font-weight:700;color:#1d4ed8;margin-bottom:.15rem}
    .file-management-directory-pill__desc{font-size:.83rem;color:#64748b;line-height:1.45}
    .file-management-bulkbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:.8rem 1rem;border-radius:16px;background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid rgba(148,163,184,.2)}
    .file-management-bulkbar__hint{font-size:.88rem;font-weight:600;color:#475569}
    .file-management-table-wrap{border:1px solid rgba(148,163,184,.18);border-radius:22px;overflow:hidden;background:#fff}
    .file-management-table thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef4ff 100%);border-bottom:1px solid rgba(148,163,184,.18);color:#334155;font-size:.8rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:1rem}
    .file-management-table tbody td{padding:1rem;border-top:1px solid rgba(226,232,240,.8);vertical-align:middle}
    .file-management-col-check{width:52px}
    .file-management-row{transition:background-color .18s ease}
    .file-management-row:hover{background-color:rgba(248,250,252,.92)}
    .file-management-filecell{display:flex;align-items:center;gap:.9rem}
    .file-management-filecell__icon{display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:15px;background:linear-gradient(140deg,#0857c3,#307fe2);color:#fff;font-weight:800;box-shadow:0 14px 30px -18px rgba(8,87,195,.55)}
    .file-management-filecell__name{font-weight:800;color:#0f172a}
    .file-management-filecell__meta{font-size:.84rem;color:#64748b;word-break:break-all}
    .file-management-active-tag{display:inline-flex;align-items:center;margin-top:.3rem;padding:.3rem .55rem;border-radius:999px;background:rgba(239,68,68,.09);color:#b91c1c;font-size:.75rem;font-weight:800}
    .file-management-folder{font-weight:800;color:#0f172a}
    .file-management-folder__meta{font-size:.84rem;color:#64748b;line-height:1.4}
    .file-management-size{display:inline-flex;align-items:center;justify-content:flex-end;min-width:88px;padding:.4rem .7rem;border-radius:999px;background:rgba(37,99,235,.08);color:#1d4ed8;font-weight:800}
    .file-management-time{font-weight:700;color:#0f172a}
    .file-management-delete-btn{min-width:110px;border-radius:12px;font-weight:700}
    .management-file-checkbox:disabled{cursor:not-allowed;opacity:.45}
    .swal-modern-popup{border:1px solid rgba(226,232,240,.95);border-radius:28px;padding:1.4rem 1.4rem 1.2rem;box-shadow:0 30px 80px -35px rgba(15,23,42,.35)}
    .swal-modern-title{color:#0f172a;font-weight:800;letter-spacing:-.02em}
    .swal-modern-html{color:#475569;font-size:.95rem;line-height:1.65}
    .swal-modern-confirm,.swal-modern-cancel{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:16px;font-weight:700;padding:.8rem 1.3rem}
    .swal-modern-confirm{background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;box-shadow:0 16px 34px -22px rgba(15,23,42,.45)}
    .swal-modern-cancel{background:#e2e8f0;color:#334155;margin-left:.5rem}
    @media (max-width:991.98px){.file-management-directory-strip{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:767.98px){
        .file-management-hero,.file-management-card__header,.file-management-card__body{padding-left:1rem;padding-right:1rem}
        .file-management-hero__title{font-size:1.15rem}
        .file-management-hero__badge,.file-management-action-btn{width:100%}
        .file-management-directory-strip{grid-template-columns:1fr}
        .file-management-table thead th,.file-management-table tbody td{padding:.8rem}
        .file-management-bulkbar{align-items:flex-start}
    }
</style>
@endsection
