@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="container-fluid pt-2 pb-4">
    <!-- Hero Header -->
    <div class="user-hero mb-4">
        <div class="user-hero__glow"></div>
        <div class="user-management-page-head d-flex justify-content-between align-items-center position-relative">
            <div class="user-management-page-copy pr-3">
                <span class="user-hero__eyebrow"><i class="fas fa-id-badge mr-1"></i> Access Control &amp; Authentication</span>
                <h2 class="user-hero__title h4 font-weight-bold text-white mb-0"><i class="fas fa-users-cog mr-2"></i> User Management</h2>
                <p class="user-hero__text mb-0">Kelola akun internal portal, role hak akses, pemetaan wilayah binaan, dan kredensial pengguna.</p>
            </div>
            <span class="user-management-admin-badge user-hero__badge mt-3 mt-md-0"><i class="fas fa-user-shield mr-1"></i> Admin Only</span>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 10px;"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
    @endif

    @if ($errors->has('user_management'))
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 10px;"><i class="fas fa-exclamation-triangle mr-2"></i>{{ $errors->first('user_management') }}</div>
    @endif

    <div class="row align-items-stretch">
        <!-- Create User Card -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card shadow-sm border-0 h-100 user-card-exec">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center">
                    <span class="user-card-icon user-card-icon--primary mr-2"><i class="fas fa-user-plus"></i></span>
                    <h6 class="font-weight-bold text-dark mb-0">Tambah User Baru</h6>
                </div>
                <div class="card-body p-4 bg-light">
                    <form method="POST" action="{{ route('user-management.store') }}">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="user-form-label" for="name">Nama Lengkap</label>
                            <input type="text" id="name" name="name" class="form-control user-form-control @error('name', 'createUser') is-invalid @enderror" value="{{ old('name') }}" placeholder="Masukkan nama..." required>
                            @error('name', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group mb-3">
                            <label class="user-form-label" for="pn">Personal Number (PN)</label>
                            <input type="text" id="pn" name="pn" class="form-control user-form-control @error('pn', 'createUser') is-invalid @enderror" value="{{ old('pn') }}" placeholder="Contoh: 00123456" required>
                            @error('pn', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group mb-3">
                            <label class="user-form-label" for="role">Role Akses</label>
                            <select id="role" name="role" class="form-control user-form-control custom-select" required>
                                <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>User (Standard)</option>
                                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin (Full Control)</option>
                            </select>
                            @error('role', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group mb-3">
                            <label class="user-form-label" for="branch_scope">Wilayah Binaan</label>
                            <select id="branch_scope" name="branch_scope" data-user-scope-admin-control class="form-control user-form-control custom-select" required>
                                @foreach($branchScopeOptions as $scopeKey => $scopeLabel)
                                    <option value="{{ $scopeKey }}" {{ old('branch_scope', \App\Support\UserBranchScope::AREA_SCOPE) === $scopeKey ? 'selected' : '' }}>{{ $scopeLabel }}</option>
                                @endforeach
                            </select>
                            @error('branch_scope', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted d-block mt-1" style="font-size: 0.78rem;">Area 6 mencakup semua cabang. Pilihan KC membatasi cakupan data dashboard.</small>
                        </div>
                        <div class="form-group mb-4">
                            <label class="user-form-label" for="password">Password Awal</label>
                            <input type="password" id="password" name="password" class="form-control user-form-control @error('password', 'createUser') is-invalid @enderror" placeholder="Min. 8 karakter" required>
                            @error('password', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn user-btn-primary btn-block font-weight-bold">
                            <i class="fas fa-save mr-2"></i>Simpan Pengguna
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Directory Card -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow-sm border-0 h-100 user-card-exec">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <span class="user-card-icon user-card-icon--navy mr-2"><i class="fas fa-address-card"></i></span>
                        <h6 class="font-weight-bold text-dark mb-0">Daftar Pengguna</h6>
                    </div>
                    
                    <!-- Stats in header -->
                    <div class="d-flex align-items-center gap-2 user-header-stats">
                        <div class="user-stat-pill">
                            <div class="user-stat-pill__label">Total</div>
                            <div class="user-stat-pill__val text-dark">{{ number_format($stats['total'], 0, ',', '.') }}</div>
                        </div>
                        <div class="user-stat-pill">
                            <div class="user-stat-pill__label">Admin</div>
                            <div class="user-stat-pill__val text-primary">{{ number_format($stats['admins'], 0, ',', '.') }}</div>
                        </div>
                        <div class="user-stat-pill">
                            <div class="user-stat-pill__label">User Biasa</div>
                            <div class="user-stat-pill__val text-secondary">{{ number_format($stats['users'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive bg-white h-100 user-table-wrap">
                    <table class="table table-hover mb-0 user-table">
                        <thead>
                            <tr>
                                <th class="pl-4">Pengguna</th>
                                <th>PN</th>
                                <th>Role</th>
                                <th>Wilayah Binaan</th>
                                <th>Terakhir Login</th>
                                <th class="text-center pr-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $userItem)
                                <tr class="user-row" data-user-id="{{ $userItem->id }}" data-user-name="{{ $userItem->name }}" data-user-pn="{{ $userItem->pn }}" title="Double klik untuk melihat riwayat login">
                                    <td class="pl-4 align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar mr-3">
                                                {{ strtoupper(substr($userItem->name ?? 'U', 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-weight-bold text-dark user-name-text">{{ $userItem->name }}</div>
                                                <div class="text-muted small user-sub-text">{{ auth()->id() === $userItem->id ? 'Sedang aktif login' : 'Akun internal portal' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle font-weight-bold text-dark">{{ $userItem->pn }}</td>
                                    <td class="align-middle">
                                        <span class="user-role-badge {{ $userItem->role === 'admin' ? 'user-role-badge--admin' : 'user-role-badge--user' }}">
                                            {{ strtoupper($userItem->role) }}
                                        </span>
                                    </td>
                                    @php
                                        $userScopeKey = $userItem->branch_scope
                                            ?: (\App\Support\UserBranchScope::forUser($userItem)['key'] ?? \App\Support\UserBranchScope::AREA_SCOPE);
                                    @endphp
                                    <td class="align-middle">
                                        <span class="user-scope-badge">
                                            <i class="fas fa-map-marker-alt text-primary mr-1"></i>{{ $branchScopeOptions[$userScopeKey] ?? 'Area 6 (Semua Cabang)' }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-dark">
                                        @if($userItem->last_login_at)
                                            <div class="font-weight-bold" style="font-size: 0.85rem;">{{ \Carbon\Carbon::parse($userItem->last_login_at)->diffForHumans() }}</div>
                                            <span class="text-muted small" style="font-size: 0.72rem;"><i class="far fa-clock mr-1"></i>{{ \Carbon\Carbon::parse($userItem->last_login_at)->format('d M Y H:i:s') }}</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle pr-4">
                                        <button type="button" class="btn btn-sm btn-outline-primary user-action-btn" data-toggle="modal" data-target="#editUserModal-{{ $userItem->id }}" title="Edit User">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form method="POST" action="{{ route('user-management.destroy', $userItem) }}" class="d-inline" onsubmit="return confirm('Hapus user {{ addslashes($userItem->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger user-action-btn ml-1" {{ auth()->id() === $userItem->id ? 'disabled' : '' }} title="Hapus User">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">Belum ada user terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if(method_exists($users, 'hasPages') && $users->hasPages())
                <div class="card-footer bg-white py-3 px-4 d-flex justify-content-end border-top">
                    {{ $users->links('pagination::bootstrap-4') }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@foreach ($users as $userItem)
    @php
        $editRole = session('open_edit_user') == $userItem->id
            ? old('role', $userItem->role)
            : $userItem->role;
        $storedScopeKey = $userItem->branch_scope
            ?: (\App\Support\UserBranchScope::forUser($userItem)['key'] ?? \App\Support\UserBranchScope::AREA_SCOPE);
        $editScopeKey = session('open_edit_user') == $userItem->id
            ? old('branch_scope', $storedScopeKey)
            : $storedScopeKey;
    @endphp
    <div class="modal fade user-management-edit-modal" id="editUserModal-{{ $userItem->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header bg-light border-bottom py-3 px-4">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">{{ $userItem->name }}</h5>
                        <div class="text-muted small">Edit Profil Pengguna</div>
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
                                    <label class="user-form-label">Nama Lengkap</label>
                                    <input type="text" name="name" class="form-control user-form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('name')) is-invalid @endif" value="{{ session('open_edit_user') == $userItem->id ? old('name', $userItem->name) : $userItem->name }}" required>
                                    @if (session('open_edit_user') == $userItem->id) @error('name', 'updateUser')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="user-form-label">Personal Number (PN)</label>
                                    <input type="text" name="pn" class="form-control user-form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('pn')) is-invalid @endif" value="{{ session('open_edit_user') == $userItem->id ? old('pn', $userItem->pn) : $userItem->pn }}" required>
                                    @if (session('open_edit_user') == $userItem->id) @error('pn', 'updateUser')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="user-form-label">Role Akses</label>
                                    <select name="role" class="form-control user-form-control custom-select @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('role')) is-invalid @endif" required>
                                        <option value="user" {{ $editRole === 'user' ? 'selected' : '' }}>User (Standard)</option>
                                        <option value="admin" {{ $editRole === 'admin' ? 'selected' : '' }}>Admin (Full Control)</option>
                                    </select>
                                    @if (session('open_edit_user') == $userItem->id) @error('role', 'updateUser')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="user-form-label">Wilayah Binaan</label>
                                    <select name="branch_scope" data-user-scope-admin-control class="form-control user-form-control custom-select @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('branch_scope')) is-invalid @endif" required>
                                        @foreach($branchScopeOptions as $scopeKey => $scopeLabel)
                                            <option value="{{ $scopeKey }}" {{ $editScopeKey === $scopeKey ? 'selected' : '' }}>{{ $scopeLabel }}</option>
                                        @endforeach
                                    </select>
                                    @if (session('open_edit_user') == $userItem->id) @error('branch_scope', 'updateUser')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="user-form-label">Password Baru</label>
                                    <input type="password" name="password" class="form-control user-form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('password')) is-invalid @endif" placeholder="Kosongkan jika tidak diubah">
                                    @if (session('open_edit_user') == $userItem->id) @error('password', 'updateUser')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                    <small class="text-muted d-block mt-1" style="font-size: 0.78rem;">Isi hanya jika ingin mereset password pengguna.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top py-3 px-4">
                        <button type="button" class="btn btn-outline-secondary font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Batal</button>
                        <button type="submit" class="btn user-btn-primary font-weight-bold">
                            <i class="fas fa-save mr-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

<!-- Login History Modal -->
<div class="modal fade" id="loginHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-bottom bg-light py-3 px-4">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark mb-0"><i class="fas fa-history text-primary mr-2"></i> Riwayat Login</h5>
                    <div class="text-muted small mt-1" id="loginHistoryUserSub">Memuat...</div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div id="loginHistoryLoader" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <div class="text-muted mt-2 small">Mengambil data riwayat login...</div>
                </div>
                <div id="loginHistoryContent" style="display: none;">
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th class="border-top-0 border-bottom-0 pl-3 py-2">Tanggal</th>
                                    <th class="border-top-0 border-bottom-0 text-center pr-3 py-2" style="width: 130px;">Jumlah Login</th>
                                </tr>
                            </thead>
                            <tbody id="loginHistoryTableBody">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="loginHistoryEmpty" class="text-center py-4 text-muted small" style="display: none;">
                    <i class="fas fa-info-circle mb-2" style="font-size: 1.5rem;"></i>
                    <div>Tidak ada riwayat login tercatat.</div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-3 px-4">
                <button type="button" class="btn btn-secondary btn-block font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    /* ==========================================================================
       BRI Nusantara - Modern Luxury Theme for User Management
       ========================================================================== */

    /* 1. Hero Header */
    .user-hero {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 1.5rem 2rem;
        background: linear-gradient(135deg, #071d41 0%, #0857c3 55%, #0284c7 100%);
        color: #ffffff;
        box-shadow: 0 10px 25px -5px rgba(8, 87, 195, 0.25), 0 8px 10px -6px rgba(8, 87, 195, 0.2);
        background-image: 
            radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
            linear-gradient(135deg, #071d41 0%, #0857c3 55%, #0284c7 100%);
        background-size: 20px 20px, 100% 100%;
    }
    .user-hero__glow {
        position: absolute;
        top: -50%;
        right: -10%;
        width: 380px;
        height: 380px;
        background: radial-gradient(circle, rgba(113, 197, 232, 0.2) 0%, rgba(8, 87, 195, 0) 70%);
        pointer-events: none;
    }
    .user-hero__eyebrow {
        display: inline-flex;
        align-items: center;
        margin-bottom: 0.5rem;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #e0f2fe;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .user-hero__title {
        color: #ffffff;
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 0.3rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }
    .user-hero__text {
        color: #e2e8f0;
        font-size: 0.9rem;
        max-width: 680px;
        line-height: 1.5;
    }
    .user-hero__badge {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1.1rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #ffffff;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    /* Required Responsive Headings */
    .user-management-page-head {
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .user-management-page-copy {
        flex: 1 1 12rem;
        min-width: 0;
    }
    .user-management-admin-badge {
        flex: 0 0 auto;
        max-width: 100%;
        white-space: nowrap;
    }

    /* 2. Executive Cards */
    .user-card-exec {
        border-radius: 16px !important;
        overflow: hidden;
        border: 1px solid rgba(8, 87, 195, 0.1) !important;
        box-shadow: 0 10px 30px rgba(8, 87, 195, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03) !important;
        background: #ffffff;
    }
    .user-card-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    .user-card-icon--primary {
        background: #eff6ff;
        color: #0857c3;
    }
    .user-card-icon--navy {
        background: #e0f2fe;
        color: #0369a1;
    }

    /* 3. Forms */
    .user-form-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 0.35rem;
    }
    .user-form-control {
        border-radius: 8px !important;
        border: 1px solid #cbd5e1 !important;
        height: 38px;
        font-size: 0.86rem;
        transition: all 0.2s ease;
    }
    .user-form-control:focus {
        border-color: #0857c3 !important;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.15) !important;
    }

    /* 4. Buttons */
    .user-btn-primary {
        border-radius: 8px !important;
        height: 40px;
        font-weight: 700;
        font-size: 0.88rem;
        background: linear-gradient(135deg, #0857c3 0%, #0284c7 100%);
        color: #ffffff;
        border: 0;
        box-shadow: 0 2px 8px rgba(8, 87, 195, 0.25);
        transition: all 0.2s ease;
    }
    .user-btn-primary:hover {
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(8, 87, 195, 0.35);
    }
    .user-action-btn {
        border-radius: 8px !important;
        min-width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        transition: all 0.15s ease;
    }

    /* 5. Header Stats */
    .user-header-stats {
        display: flex;
    }
    .user-stat-pill {
        padding: 0.35rem 0.65rem;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        text-align: center;
        min-width: 62px;
    }
    .user-stat-pill__label {
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #64748b;
        line-height: 1;
        margin-bottom: 0.2rem;
    }
    .user-stat-pill__val {
        font-size: 1rem;
        font-weight: 800;
        line-height: 1;
    }

    /* 6. Table */
    .user-table-wrap {
        border-radius: 0 0 16px 16px;
    }
    .user-table thead th {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 0.95rem 1.25rem;
    }
    .user-table tbody td {
        padding: 0.85rem 1.25rem;
        border-top: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .user-row {
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
    }
    .user-row:hover {
        background-color: rgba(8, 87, 195, 0.04) !important;
    }

    /* Avatar & Badges */
    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, #0857c3, #0284c7);
        color: #ffffff;
        font-weight: 800;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(8, 87, 195, 0.2);
    }
    .user-name-text {
        font-size: 0.92rem;
    }
    .user-sub-text {
        font-size: 0.78rem;
    }
    .user-role-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
    }
    .user-role-badge--admin {
        background: #eff6ff;
        color: #0857c3;
        border: 1px solid #bfdbfe;
    }
    .user-role-badge--user {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .user-scope-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.65rem;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
    }

    /* Modals */
    .modal.user-management-edit-modal { z-index: 2055; }
    .modal-backdrop.user-management-edit-backdrop { z-index: 2050; }
    #loginHistoryModal { z-index: 2065; }
    .modal-backdrop.login-history-backdrop { z-index: 2060; }
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

        // Setup Login History Modal lifecycle in body
        const $historyModal = $('#loginHistoryModal');
        if (!$historyModal.parent().is('body')) {
            $historyModal.appendTo(document.body);
        }

        $historyModal.on('show.bs.modal', function () {
            window.setTimeout(function () {
                $('.modal-backdrop').last().addClass('login-history-backdrop');
            }, 0);
        });

        $historyModal.on('hidden.bs.modal', function () {
            if (!$('.modal.show').length) {
                $('body').removeClass('modal-open').css('padding-right', '');
                $('.modal-backdrop.login-history-backdrop').remove();
            }
        });

        // Double-click row handler
        $('.user-row').on('dblclick', function() {
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            const userPn = $(this).data('user-pn');
            
            $('#loginHistoryUserSub').text(userName + ' (PN: ' + userPn + ')');
            
            $('#loginHistoryLoader').show();
            $('#loginHistoryContent').hide();
            $('#loginHistoryEmpty').hide();
            
            $historyModal.modal('show');
            
            $.ajax({
                url: '/user-management/' + userId + '/login-history',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#loginHistoryLoader').hide();
                    
                    const history = response.history;
                    if (history && history.length > 0) {
                        let html = '';
                        history.forEach(function(row) {
                            const rawDate = new Date(row.date);
                            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                            let formattedDate = rawDate.toLocaleDateString('id-ID', options);
                            if (formattedDate === 'Invalid Date' || !formattedDate) {
                                formattedDate = row.date;
                            }
                            html += `<tr>
                                <td class="pl-3 py-2 align-middle font-weight-bold text-dark">${formattedDate}</td>
                                <td class="text-center pr-3 py-2 align-middle">
                                    <span class="badge badge-primary px-3 py-1 font-weight-bold" style="border-radius: 6px; font-size: 0.82rem;">
                                        ${row.count} x
                                    </span>
                                </td>
                            </tr>`;
                        });
                        $('#loginHistoryTableBody').html(html);
                        $('#loginHistoryContent').show();
                    } else {
                        $('#loginHistoryEmpty').show();
                    }
                },
                error: function() {
                    $('#loginHistoryLoader').hide();
                    $('#loginHistoryEmpty').find('div').text('Gagal memuat data riwayat login.');
                    $('#loginHistoryEmpty').show();
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
