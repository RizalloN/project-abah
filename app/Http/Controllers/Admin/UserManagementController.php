<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(5);

        $stats = [
            'total' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'users' => User::where('role', 'user')->count(),
        ];

        return view('admin.user-management', compact('users', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'pn' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,pn'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['required', 'string', 'min:6'],
        ])->validateWithBag('createUser');

        User::create([
            'name' => trim($data['name']),
            'pn' => trim($data['pn']),
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        return back()
            ->with('success', 'User baru berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'pn' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'pn')->ignore($user->getKey())],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'updateUser')
                ->withInput()
                ->with('open_edit_user', $user->getKey());
        }

        $data = $validator->validated();

        $user->name = trim($data['name']);
        $user->pn = trim($data['pn']);
        $user->role = $data['role'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $currentUser = Auth::user();
        if ($currentUser && (int) $currentUser->getKey() === (int) $user->getKey() && $data['role'] !== 'admin') {
            return back()
                ->withErrors(['user_management' => 'Akun admin yang sedang aktif tidak boleh diturunkan menjadi user biasa.'], 'updateUser')
                ->withInput()
                ->with('open_edit_user', $user->getKey());
        }

        $user->save();

        return back()
            ->with('success', 'Data user berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        if ($currentUser && (int) $currentUser->getKey() === (int) $user->getKey()) {
            return back()
                ->withErrors(['user_management' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        $user->delete();

        return back()
            ->with('success', 'User berhasil dihapus.');
    }
}
