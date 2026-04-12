<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $users->count(),
            'admins' => $users->where('role', 'admin')->count(),
            'users' => $users->where('role', 'user')->count(),
        ];

        return view('admin.user-management', compact('users', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pn' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,pn'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'name' => trim($data['name']),
            'pn' => trim($data['pn']),
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()
            ->route('user-management.index')
            ->with('success', 'User baru berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pn' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'pn')->ignore($user->getKey())],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $user->name = trim($data['name']);
        $user->pn = trim($data['pn']);
        $user->role = $data['role'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $currentUser = Auth::user();
        if ($currentUser && (int) $currentUser->getKey() === (int) $user->getKey() && $data['role'] !== 'admin') {
            return redirect()
                ->route('user-management.index')
                ->withErrors(['user_management' => 'Akun admin yang sedang aktif tidak boleh diturunkan menjadi user biasa.']);
        }

        $user->save();

        return redirect()
            ->route('user-management.index')
            ->with('success', 'Data user berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        if ($currentUser && (int) $currentUser->getKey() === (int) $user->getKey()) {
            return redirect()
                ->route('user-management.index')
                ->withErrors(['user_management' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        $user->delete();

        return redirect()
            ->route('user-management.index')
            ->with('success', 'User berhasil dihapus.');
    }
}
