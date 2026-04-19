@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="user-management-hero mb-4">
    <div class="user-management-hero__glow"></div>
    <div class="d-flex align-items-center justify-content-between flex-wrap position-relative">
        <div class="pr-3">
            <span class="user-management-hero__eyebrow">User Management</span>
            <div class="user-management-hero__title"><i class="fas fa-users-cog mr-2"></i> Kontrol Akses Pengguna</div>
            <p class="user-management-hero__text mb-0">Kelola akun internal, role akses, dan reset password dari satu halaman dengan tampilan yang konsisten dengan portal A-Six.</p>
        </div>
        <div class="user-management-hero__badge mt-3 mt-md-0"><i class="fas fa-user-shield mr-2"></i> Admin Only</div>
    </div>
</div>

@if (session('success'))
    <div class="user-management-flash user-management-flash--success mb-4">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
@endif

@if ($errors->has('user_management'))
    <div class="user-management-flash user-management-flash--danger mb-4">
        <i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->first('user_management') }}
    </div>
@endif

<div class="row align-items-stretch">
    <div class="col-xl-5 col-lg-5 mb-4">
        <div class="card shadow-sm border-0 user-management-card h-100">
            <div class="card-header bg-white border-0 user-management-card__header">
                <span class="user-management-card__eyebrow">Create User</span>
                <h5 class="card-title font-weight-bold text-dark mb-1">
                    <i class="fas fa-user-plus text-primary mr-2"></i> Tambah User Baru
                </h5>
                <p class="user-management-card__subtitle mb-0">Buat akun admin atau user biasa dengan password awal yang langsung di-hash oleh Laravel.</p>
            </div>
            <div class="card-body user-management-card__body">
                <form method="POST" action="{{ route('user-management.store') }}">
                    @csrf

                    <div class="form-group">
                        <label class="font-weight-bold text-dark" for="name">Nama</label>
                        <input type="text" id="name" name="name" class="form-control user-management-input @error('name', 'createUser') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name', 'createUser')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark" for="pn">PN</label>
                        <input type="text" id="pn" name="pn" class="form-control user-management-input @error('pn', 'createUser') is-invalid @enderror" value="{{ old('pn') }}" required>
                        @error('pn', 'createUser')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark" for="role">Role</label>
                        <select id="role" name="role" class="form-control user-management-input @error('role', 'createUser') is-invalid @enderror" required>
                            <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>User</option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role', 'createUser')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark" for="password">Password Awal</label>
                        <input type="password" id="password" name="password" class="form-control user-management-input @error('password', 'createUser') is-invalid @enderror" required>
                        @error('password', 'createUser')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-block user-management-btn">
                        <i class="fas fa-save mr-2"></i>Simpan User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7 col-lg-7 mb-4 d-flex">
        <div class="user-management-directory-column w-100">
            <div class="row align-items-stretch user-management-stat-row">
            <div class="col-lg-4 col-md-12 col-sm-12 mb-3">
                <div class="user-management-stat">
                    <small>Total User</small>
                    <strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong>
                </div>
            </div>
            <div class="col-lg-4 col-md-12 col-sm-12 mb-3">
                <div class="user-management-stat">
                    <small>Admin</small>
                    <strong>{{ number_format($stats['admins'], 0, ',', '.') }}</strong>
                </div>
            </div>
            <div class="col-lg-4 col-md-12 col-sm-12 mb-3">
                <div class="user-management-stat">
                    <small>User Biasa</small>
                    <strong>{{ number_format($stats['users'], 0, ',', '.') }}</strong>
                </div>
            </div>
            </div>

            <div class="card shadow-sm border-0 user-management-card user-management-directory-card">
                <div class="card-header bg-white border-0 user-management-card__header">
                    <span class="user-management-card__eyebrow">Directory</span>
                    <h5 class="card-title font-weight-bold text-dark mb-1">
                        <i class="fas fa-address-card text-primary mr-2"></i> Daftar Pengguna
                    </h5>
                    <p class="user-management-card__subtitle mb-0">Edit nama, PN, role, atau reset password langsung dari daftar berikut.</p>
                </div>
                <div class="card-body user-management-card__body pt-0">
                    <div class="table-responsive user-management-table-wrap">
                        <table class="table table-hover mb-0 user-management-table">
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th>PN</th>
                                    <th>Role</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $userItem)
                                    <tr>
                                        <td>
                                            <div class="user-management-usercell">
                                                <div class="user-management-avatar">{{ strtoupper(substr($userItem->name ?? 'U', 0, 2)) }}</div>
                                                <div>
                                                    <div class="user-management-usercell__name">{{ $userItem->name }}</div>
                                                    <div class="user-management-usercell__meta">
                                                        {{ auth()->id() === $userItem->id ? 'Sedang aktif login' : 'Akun internal portal' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="user-management-pill user-management-pill--plain">{{ $userItem->pn }}</span></td>
                                        <td>
                                            <span class="user-management-pill {{ $userItem->role === 'admin' ? 'user-management-pill--admin' : 'user-management-pill--user' }}">
                                                {{ strtoupper($userItem->role) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary user-management-action" data-toggle="modal" data-target="#editUserModal-{{ $userItem->id }}">
                                                <i class="fas fa-pen mr-1"></i>Edit
                                            </button>
                                            <form method="POST" action="{{ route('user-management.destroy', $userItem) }}" class="d-inline" onsubmit="return confirm('Hapus user {{ addslashes($userItem->name) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger user-management-action" {{ auth()->id() === $userItem->id ? 'disabled' : '' }}>
                                                    <i class="fas fa-trash-alt mr-1"></i>Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada user terdaftar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    @if(method_exists($users, 'hasPages') && $users->hasPages())
                    <div class="user-management-pagination mt-4 d-flex justify-content-end">
                        {{ $users->links('pagination::bootstrap-4') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@foreach ($users as $userItem)
    <div class="modal fade user-management-edit-modal" id="editUserModal-{{ $userItem->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content user-management-modal">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="user-management-card__eyebrow mb-2">Edit User</div>
                        <h5 class="modal-title font-weight-bold mb-0">{{ $userItem->name }}</h5>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('user-management.update', $userItem) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-body pt-3">
                        @if (session('open_edit_user') == $userItem->id && ($errors->updateUser->any() || $errors->updateUser->has('user_management')))
                            <div class="user-management-flash user-management-flash--danger mb-3">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                {{ $errors->updateUser->first('user_management') ?: 'Periksa kembali data yang diisi.' }}
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold text-dark">Nama</label>
                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control user-management-input @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('name')) is-invalid @endif"
                                        value="{{ session('open_edit_user') == $userItem->id ? old('name', $userItem->name) : $userItem->name }}"
                                        required
                                    >
                                    @if (session('open_edit_user') == $userItem->id)
                                        @error('name', 'updateUser')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold text-dark">PN</label>
                                    <input
                                        type="text"
                                        name="pn"
                                        class="form-control user-management-input @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('pn')) is-invalid @endif"
                                        value="{{ session('open_edit_user') == $userItem->id ? old('pn', $userItem->pn) : $userItem->pn }}"
                                        required
                                    >
                                    @if (session('open_edit_user') == $userItem->id)
                                        @error('pn', 'updateUser')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold text-dark">Role</label>
                                    <select name="role" class="form-control user-management-input @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('role')) is-invalid @endif" required>
                                        @php($editRole = session('open_edit_user') == $userItem->id ? old('role', $userItem->role) : $userItem->role)
                                        <option value="user" {{ $editRole === 'user' ? 'selected' : '' }}>User</option>
                                        <option value="admin" {{ $editRole === 'admin' ? 'selected' : '' }}>Admin</option>
                                    </select>
                                    @if (session('open_edit_user') == $userItem->id)
                                        @error('role', 'updateUser')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold text-dark">Password Baru</label>
                                    <input
                                        type="password"
                                        name="password"
                                        class="form-control user-management-input @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('password')) is-invalid @endif"
                                        placeholder="Kosongkan jika tidak diubah"
                                    >
                                    @if (session('open_edit_user') == $userItem->id)
                                        @error('password', 'updateUser')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    @endif
                                    <small class="text-muted d-block mt-2">Isi hanya jika ingin reset password user ini.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light user-management-modal__btn" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary user-management-modal__btn">
                            <i class="fas fa-save mr-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection

@section('styles')
<style>
    .user-management-hero { position: relative; overflow: hidden; border-radius: 26px; padding: 1.45rem 1.5rem; background: radial-gradient(circle at top right, rgba(37,99,235,0.22), transparent 30%), linear-gradient(135deg, #f8fbff 0%, #eef4ff 46%, #dbeafe 100%); border: 1px solid rgba(37,99,235,0.16); box-shadow: 0 22px 45px -32px rgba(29,78,216,0.4); }
    .user-management-hero__glow { position: absolute; top: -48px; right: -30px; width: 170px; height: 170px; border-radius: 999px; background: rgba(14,165,233,0.16); filter: blur(10px); }
    .user-management-hero__eyebrow, .user-management-card__eyebrow { display: inline-block; margin-bottom: 0.55rem; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
    .user-management-hero__eyebrow { color: #1d4ed8; background: rgba(255,255,255,0.72); border: 1px solid rgba(37,99,235,0.16); }
    .user-management-hero__title { color: #0f3f8c; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 0.35rem; }
    .user-management-hero__text { color: #31527c; line-height: 1.7; max-width: 760px; }
    .user-management-hero__badge { display: inline-flex; align-items: center; min-height: 48px; padding: 0.8rem 1.25rem; border-radius: 18px; background: rgba(255,255,255,0.84); border: 1px solid rgba(37,99,235,0.14); color: #0f3f8c; font-weight: 700; box-shadow: 0 18px 32px -24px rgba(29,78,216,0.3); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .user-management-hero__badge:hover { transform: translateY(-2px); box-shadow: 0 20px 38px -20px rgba(29,78,216,0.4); }
    .user-management-flash { padding: 1rem 1.1rem; border-radius: 18px; font-weight: 700; }
    .user-management-flash--success { background: rgba(220,252,231,0.85); border: 1px solid rgba(34,197,94,0.2); color: #166534; }
    .user-management-flash--danger { background: rgba(254,226,226,0.88); border: 1px solid rgba(239,68,68,0.18); color: #b91c1c; }
    .user-management-stat { height: 100%; padding: 1.5rem 1.25rem; border-radius: 20px; background: #ffffff; border: 1px solid rgba(148, 163, 184, 0.2); box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; display: flex; flex-direction: column; justify-content: center; }
    .user-management-stat:hover { box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); transform: translateY(-2px); }
    .user-management-stat small { display: block; color: #64748b; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; }
    .user-management-stat strong { display: block; color: #0f172a; font-size: 1.15rem; font-weight: 800; line-height: 1.3; word-break: break-word; }
    .user-management-directory-column { display: flex; flex-direction: column; width: 100%; min-height: 100%; }
    .user-management-stat-row { flex: 0 0 auto; }
    .user-management-card { border-radius: 26px; overflow: hidden; box-shadow: 0 28px 60px -40px rgba(15,23,42,0.32) !important; border: 1px solid rgba(226,232,240,0.8); background: #fff; }
    .user-management-directory-card { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; }
    .user-management-card__header { padding: 1.45rem 1.5rem 1rem; border-bottom: 1px solid rgba(226,232,240,0.5); background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.09), transparent 28%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); }
    .user-management-card__eyebrow { color: #1d4ed8; background: rgba(37,99,235,0.08); }
    .user-management-card__subtitle { color: #64748b; max-width: 780px; line-height: 1.6; }
    .user-management-card__body { padding: 1.75rem; background: #f8fafc; }
    .user-management-directory-card .user-management-card__body { display: flex; flex: 1 1 auto; flex-direction: column; }
    .user-management-input { min-height: 48px; border-radius: 16px; border: 1px solid rgba(148, 163, 184, 0.3); background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .user-management-input:focus { border-color: #307fe2; background: #fff; box-shadow: 0 0 0 4px rgba(48,127,226,0.16); }
    .user-management-btn, .user-management-modal__btn { min-height: 48px; border-radius: 16px; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .user-management-btn:hover, .user-management-modal__btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
    .user-management-table-wrap { border: 1px solid rgba(148,163,184,0.2); border-radius: 22px; overflow: hidden; background: #fff; box-shadow: 0 10px 25px -10px rgba(15, 23, 42, 0.05); flex: 1 1 auto; }
    .user-management-table thead th { background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 1px solid rgba(148,163,184,0.2); color: #334155; font-size: 0.8rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; padding: 1.15rem 1rem; }
    .user-management-table tbody td { padding: 1.15rem 1rem; border-top: 1px solid rgba(226,232,240,0.8); vertical-align: middle; }
    .user-management-usercell { display: flex; align-items: center; gap: 1rem; }
    .user-management-avatar { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 16px; background: linear-gradient(140deg, #0857c3, #307fe2); color: #fff; font-size: 1.25rem; box-shadow: 0 14px 30px -18px rgba(8,87,195,0.55); }
    .user-management-usercell__name { font-weight: 800; color: #0f172a; font-size: 0.95rem; margin-bottom: 0.15rem; }
    .user-management-usercell__meta { font-size: 0.85rem; color: #64748b; }
    .user-management-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 84px; padding: 0.45rem 0.85rem; border-radius: 999px; font-weight: 800; font-size: 0.8rem; }
    .user-management-pill--plain { background: rgba(15,23,42,0.06); color: #334155; }
    .user-management-pill--admin { background: rgba(219,234,254,0.88); color: #1d4ed8; }
    .user-management-pill--user { background: rgba(226,232,240,0.8); color: #475569; }
    .user-management-action { border-radius: 14px; font-weight: 800; transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .user-management-action:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .modal.user-management-edit-modal { z-index: 2055; }
    .modal-backdrop.user-management-edit-backdrop { z-index: 2050; }
    .user-management-modal { border: 1px solid rgba(226,232,240,0.95); border-radius: 28px; padding: 1.4rem 1.4rem 1.2rem; box-shadow: 0 30px 80px -35px rgba(15,23,42,0.35); }
    .user-management-pagination .page-item .page-link { border-radius: 12px; margin: 0 4px; color: #475569; font-weight: 700; border: 1px solid transparent; background: transparent; transition: all 0.2s ease; }
    .user-management-pagination .page-item:not(.active):not(.disabled) .page-link:hover { background: rgba(37,99,235,0.08); color: #1d4ed8; border-color: rgba(37,99,235,0.1); transform: translateY(-1px); }
    .user-management-pagination .page-item.active .page-link { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border-color: transparent; box-shadow: 0 8px 16px -8px rgba(37,99,235,0.5); }
    .user-management-pagination .page-item.disabled .page-link { color: #94a3b8; background: transparent; border-color: transparent; }
    @media (max-width: 767.98px) {
        .user-management-hero, .user-management-card__header, .user-management-card__body { padding-left: 1.25rem; padding-right: 1.25rem; }
        .user-management-hero__title { font-size: 1.25rem; }
        .user-management-hero__badge, .user-management-btn { width: 100%; }
        .user-management-table thead th, .user-management-table tbody td { padding: 1rem; }
        .user-management-directory-column { min-height: auto; }
    }
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.jQuery !== 'undefined') {
        const $ = window.jQuery;

        $('.user-management-edit-modal').each(function () {
            const $modal = $(this);

            // Move modal out of page layout containers to avoid stacking issues with AdminLTE wrappers.
            if (!$modal.parent().is('body')) {
                $modal.appendTo(document.body);
            }

            $modal.on('show.bs.modal', function () {
                const $currentModal = $(this);

                if (!$currentModal.parent().is('body')) {
                    $currentModal.appendTo(document.body);
                }

                window.setTimeout(function () {
                    $('.modal-backdrop').last().addClass('user-management-edit-backdrop');
                }, 0);
            });

            $modal.on('hidden.bs.modal', function () {
                if (!$('.modal.show').length) {
                    $('body').removeClass('modal-open').css('padding-right', '');
                    $('.modal-backdrop.user-management-edit-backdrop').remove();
                }
            });
        });
    }

    const modalId = @json(session('open_edit_user'));
    if (!modalId || typeof window.jQuery === 'undefined') {
        return;
    }

    const modalEl = document.getElementById('editUserModal-' + modalId);
    if (!modalEl) {
        return;
    }

    window.jQuery(modalEl).modal('show');
});
</script>
@endsection
