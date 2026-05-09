@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="container-fluid pt-3 pb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 font-weight-bold text-dark mb-0"><i class="fas fa-users-cog text-primary mr-2"></i> User Management</h2>
            <span class="text-muted small">Kelola akun internal, role akses, dan reset password.</span>
        </div>
        <span class="badge badge-light border border-primary text-primary px-3 py-2" style="border-radius: 8px;"><i class="fas fa-user-shield mr-1"></i> Admin Only</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 8px;"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
    @endif

    @if ($errors->has('user_management'))
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 8px;"><i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->first('user_management') }}</div>
    @endif

    <div class="row align-items-stretch">
        <!-- Create User Card -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-user-plus text-primary mr-2"></i> Tambah User Baru</h6>
                </div>
                <div class="card-body p-4 bg-light">
                    <form method="POST" action="{{ route('user-management.store') }}">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark" for="name">Nama</label>
                            <input type="text" id="name" name="name" class="form-control @error('name', 'createUser') is-invalid @enderror" value="{{ old('name') }}" required style="border-radius: 8px;">
                            @error('name', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark" for="pn">PN</label>
                            <input type="text" id="pn" name="pn" class="form-control @error('pn', 'createUser') is-invalid @enderror" value="{{ old('pn') }}" required style="border-radius: 8px;">
                            @error('pn', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark" for="role">Role</label>
                            <select id="role" name="role" class="form-control @error('role', 'createUser') is-invalid @enderror" required style="border-radius: 8px;">
                                <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>User</option>
                                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                            @error('role', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="small font-weight-bold text-dark" for="password">Password Awal</label>
                            <input type="password" id="password" name="password" class="form-control @error('password', 'createUser') is-invalid @enderror" required style="border-radius: 8px;">
                            @error('password', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-block font-weight-bold" style="border-radius: 8px;">
                            <i class="fas fa-save mr-2"></i>Simpan User
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Directory Card -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-address-card text-primary mr-2"></i> Daftar Pengguna</h6>
                    
                    <!-- Stats in header -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-center px-3 border-right">
                            <div class="text-muted small font-weight-bold text-uppercase" style="font-size: 0.65rem;">Total</div>
                            <div class="h6 mb-0 text-dark font-weight-bold">{{ number_format($stats['total'], 0, ',', '.') }}</div>
                        </div>
                        <div class="text-center px-3 border-right">
                            <div class="text-muted small font-weight-bold text-uppercase" style="font-size: 0.65rem;">Admin</div>
                            <div class="h6 mb-0 text-primary font-weight-bold">{{ number_format($stats['admins'], 0, ',', '.') }}</div>
                        </div>
                        <div class="text-center px-3">
                            <div class="text-muted small font-weight-bold text-uppercase" style="font-size: 0.65rem;">User Biasa</div>
                            <div class="h6 mb-0 text-secondary font-weight-bold">{{ number_format($stats['users'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive bg-white h-100">
                    <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="border-top-0 border-bottom-0 pl-4">Pengguna</th>
                                <th class="border-top-0 border-bottom-0">PN</th>
                                <th class="border-top-0 border-bottom-0">Role</th>
                                <th class="text-center border-top-0 border-bottom-0 pr-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $userItem)
                                <tr>
                                    <td class="pl-4 align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white d-flex align-items-center justify-content-center mr-3 font-weight-bold" style="width: 40px; height: 40px; border-radius: 10px; font-size: 1.1rem;">
                                                {{ strtoupper(substr($userItem->name ?? 'U', 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-weight-bold text-dark" style="font-size: 0.95rem;">{{ $userItem->name }}</div>
                                                <div class="text-muted small">{{ auth()->id() === $userItem->id ? 'Sedang aktif login' : 'Akun internal portal' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle font-weight-bold text-dark">{{ $userItem->pn }}</td>
                                    <td class="align-middle">
                                        <span class="badge {{ $userItem->role === 'admin' ? 'badge-primary' : 'badge-secondary' }} px-3 py-1" style="border-radius: 6px;">
                                            {{ strtoupper($userItem->role) }}
                                        </span>
                                    </td>
                                    <td class="text-center align-middle pr-4">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editUserModal-{{ $userItem->id }}" style="border-radius: 6px;">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form method="POST" action="{{ route('user-management.destroy', $userItem) }}" class="d-inline" onsubmit="return confirm('Hapus user {{ addslashes($userItem->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger ml-1" {{ auth()->id() === $userItem->id ? 'disabled' : '' }} style="border-radius: 6px;">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-5">Belum ada user terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if(method_exists($users, 'hasPages') && $users->hasPages())
                <div class="card-footer bg-white py-3 px-4 d-flex justify-content-end">
                    {{ $users->links('pagination::bootstrap-4') }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@foreach ($users as $userItem)
    <div class="modal fade user-management-edit-modal" id="editUserModal-{{ $userItem->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-sm" style="border-radius: 16px;">
                <div class="modal-header bg-light border-bottom-0 py-3 px-4">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">{{ $userItem->name }}</h5>
                        <div class="text-muted small">Edit Profile</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('user-management.update', $userItem) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-body p-4">
                        @if (session('open_edit_user') == $userItem->id && ($errors->updateUser->any() || $errors->updateUser->has('user_management')))
                            <div class="alert alert-danger" style="border-radius: 8px;">
                                <i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->updateUser->first('user_management') ?: 'Periksa kembali data yang diisi.' }}
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-dark">Nama</label>
                                    <input type="text" name="name" class="form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('name')) is-invalid @endif" value="{{ session('open_edit_user') == $userItem->id ? old('name', $userItem->name) : $userItem->name }}" required style="border-radius: 8px;">
                                    @if (session('open_edit_user') == $userItem->id) @error('name', 'updateUser')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-dark">PN</label>
                                    <input type="text" name="pn" class="form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('pn')) is-invalid @endif" value="{{ session('open_edit_user') == $userItem->id ? old('pn', $userItem->pn) : $userItem->pn }}" required style="border-radius: 8px;">
                                    @if (session('open_edit_user') == $userItem->id) @error('pn', 'updateUser')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-dark">Role</label>
                                    <select name="role" class="form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('role')) is-invalid @endif" required style="border-radius: 8px;">
                                        @php($editRole = session('open_edit_user') == $userItem->id ? old('role', $userItem->role) : $userItem->role)
                                        <option value="user" {{ $editRole === 'user' ? 'selected' : '' }}>User</option>
                                        <option value="admin" {{ $editRole === 'admin' ? 'selected' : '' }}>Admin</option>
                                    </select>
                                    @if (session('open_edit_user') == $userItem->id) @error('role', 'updateUser')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold text-dark">Password Baru</label>
                                    <input type="password" name="password" class="form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('password')) is-invalid @endif" placeholder="Kosongkan jika tidak diubah" style="border-radius: 8px;">
                                    @if (session('open_edit_user') == $userItem->id) @error('password', 'updateUser')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                    <small class="text-muted d-block mt-2">Isi hanya jika ingin reset password user ini.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 py-3 px-4">
                        <button type="button" class="btn btn-outline-secondary font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Batal</button>
                        <button type="submit" class="btn btn-primary font-weight-bold" style="border-radius: 8px;">
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
    .modal.user-management-edit-modal { z-index: 2055; }
    .modal-backdrop.user-management-edit-backdrop { z-index: 2050; }
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
