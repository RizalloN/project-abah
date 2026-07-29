@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="container-fluid pt-3 pb-4">
    <div class="user-management-page-head d-flex justify-content-between align-items-center mb-3">
        <div class="user-management-page-copy">
            <h2 class="h4 font-weight-bold text-dark mb-0"><i class="fas fa-users-cog text-primary mr-2"></i> User Management</h2>
            <span class="text-muted small">Kelola akun internal, role, wilayah binaan, dan reset password.</span>
        </div>
        <span class="user-management-admin-badge badge badge-light border border-primary text-primary px-3 py-2" style="border-radius: 8px;"><i class="fas fa-user-shield mr-1"></i> Admin Only</span>
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
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark" for="branch_scope">Wilayah Binaan</label>
                            <select id="branch_scope" name="branch_scope" data-user-scope-admin-control class="form-control @error('branch_scope', 'createUser') is-invalid @enderror" required style="border-radius: 8px;">
                                @foreach($branchScopeOptions as $scopeKey => $scopeLabel)
                                    <option value="{{ $scopeKey }}" {{ old('branch_scope', \App\Support\UserBranchScope::AREA_SCOPE) === $scopeKey ? 'selected' : '' }}>{{ $scopeLabel }}</option>
                                @endforeach
                            </select>
                            @error('branch_scope', 'createUser')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted d-block mt-1">Area 6 dapat melihat semua cabang. Pilihan KC membatasi seluruh dashboard ke cabang tersebut.</small>
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
                                <th class="border-top-0 border-bottom-0">Wilayah Binaan</th>
                                <th class="border-top-0 border-bottom-0">Terakhir Login</th>
                                <th class="text-center border-top-0 border-bottom-0 pr-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $userItem)
                                <tr class="user-row" data-user-id="{{ $userItem->id }}" data-user-name="{{ $userItem->name }}" data-user-pn="{{ $userItem->pn }}" title="Double klik untuk melihat riwayat login">
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
                                    @php
                                        $userScopeKey = $userItem->branch_scope
                                            ?: (\App\Support\UserBranchScope::forUser($userItem)['key'] ?? \App\Support\UserBranchScope::AREA_SCOPE);
                                    @endphp
                                    <td class="align-middle">
                                        <span class="badge badge-light border px-2 py-1" style="border-radius: 6px;">
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
                                <tr><td colspan="6" class="text-center text-muted py-5">Belum ada user terdaftar.</td></tr>
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
                                        <option value="user" {{ $editRole === 'user' ? 'selected' : '' }}>User</option>
                                        <option value="admin" {{ $editRole === 'admin' ? 'selected' : '' }}>Admin</option>
                                    </select>
                                    @if (session('open_edit_user') == $userItem->id) @error('role', 'updateUser')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-dark">Wilayah Binaan</label>
                                    <select name="branch_scope" data-user-scope-admin-control class="form-control @if (session('open_edit_user') == $userItem->id && $errors->updateUser->has('branch_scope')) is-invalid @endif" required style="border-radius: 8px;">
                                        @foreach($branchScopeOptions as $scopeKey => $scopeLabel)
                                            <option value="{{ $scopeKey }}" {{ $editScopeKey === $scopeKey ? 'selected' : '' }}>{{ $scopeLabel }}</option>
                                        @endforeach
                                    </select>
                                    @if (session('open_edit_user') == $userItem->id) @error('branch_scope', 'updateUser')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
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

<!-- Login History Modal -->
<div class="modal fade" id="loginHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 bg-light py-3 px-4" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
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
            <div class="modal-footer bg-light border-top-0 py-3 px-4" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                <button type="button" class="btn btn-secondary btn-block font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
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

    .modal.user-management-edit-modal { z-index: 2055; }
    .modal-backdrop.user-management-edit-backdrop { z-index: 2050; }
    
    .user-row {
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
    }
    .user-row:hover {
        background-color: rgba(8, 87, 195, 0.04) !important;
    }
    
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
