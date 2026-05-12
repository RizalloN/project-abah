<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Format validasi PN internal: 4–10 digit angka saja.
     * Mengikuti format PN BRI (Pegawai Nomor) yang berupa digit.
     */
    private const PN_REGEX = '/^\d{4,10}$/';

    /**
     * Format validasi Nama: hanya huruf (latin + spasi + tanda baca wajar).
     * Mencegah nama bergaya Faker asing (prefix Miss/Mr/Mrs + suffix Jr/PhD/dll)
     * tidak bisa masuk — nama harus murni karakter Indonesia/latin standar.
     *
     * Aturan:
     *  - min 3, max 60 karakter
     *  - hanya huruf a-z A-Z (termasuk diakritik), spasi, titik, apostrof
     *  - tidak boleh mengandung suffix gelar asing (PhD, Jr., Sr., II, III, IV)
     *  - tidak boleh diawali prefix gelar asing (Miss, Mrs, Mr, Dr tanpa konteks)
     */
    private const NAME_REGEX = '/^[\p{L}\s\'\.\-]{3,60}$/u';

    /**
     * Suffix/prefix Faker yang dilarang untuk mencegah akun dummy.
     * Pengecekan case-insensitive.
     */
    private const BLOCKED_NAME_PATTERNS = [
        '/\b(PhD|Ph\.D|Jr\.|Sr\.|II|III|IV|Miss|Mrs\.|Mr\.|DVM|DDS|MD)\b/i',
    ];

    /**
     * Batas maksimum pembuatan akun per admin dalam 1 jam.
     * Mencegah bulk-create lewat UI.
     */
    private const MAX_CREATES_PER_HOUR = 10;

    public function index(): View
    {
        $users = User::query()
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(15);

        $stats = [
            'total'  => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'users'  => User::where('role', 'user')->count(),
        ];

        return view('admin.user-management', compact('users', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        // ── Rate-limit: max MAX_CREATES_PER_HOUR pembuatan user baru per admin per jam ──
        $currentAdmin = Auth::user();
        $hourAgo = now()->subHour();
        $recentCount = \Illuminate\Support\Facades\DB::table('user_audit_log')
            ->where('actor_id', $currentAdmin->getKey())
            ->where('action', 'create')
            ->where('created_at', '>=', $hourAgo)
            ->count();

        if ($recentCount >= self::MAX_CREATES_PER_HOUR) {
            return back()
                ->withErrors(['user_management' => 'Batas pembuatan akun tercapai. Maksimum ' . self::MAX_CREATES_PER_HOUR . ' akun baru per jam.'])
                ->withInput();
        }

        // ── Validasi input ──
        $data = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'min:3', 'max:60', 'regex:' . self::NAME_REGEX],
            'pn'       => ['required', 'string', 'regex:' . self::PN_REGEX, 'unique:users,pn'],
            'role'     => ['required', Rule::in(['admin', 'user'])],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.regex'     => 'Nama hanya boleh mengandung huruf dan spasi (tanpa gelar asing atau karakter khusus).',
            'pn.regex'       => 'PN harus berupa 4–10 digit angka (contoh: 90179583).',
            'password.min'   => 'Password minimal 8 karakter.',
        ])->validateWithBag('createUser');

        // ── Cek pola nama Faker/gelar asing ──
        foreach (self::BLOCKED_NAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $data['name'])) {
                return back()
                    ->withErrors(['name' => 'Format nama tidak valid. Nama tidak boleh mengandung gelar/suffix asing (Miss, PhD, Jr., dll).'], 'createUser')
                    ->withInput();
            }
        }

        $newUser = User::create([
            'name'     => trim($data['name']),
            'pn'       => trim($data['pn']),
            'role'     => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        // ── Audit log ──
        $this->writeAuditLog('create', $newUser, $currentAdmin);

        return back()->with('success', 'User baru berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'min:3', 'max:60', 'regex:' . self::NAME_REGEX],
            'pn'       => ['required', 'string', 'regex:' . self::PN_REGEX, Rule::unique('users', 'pn')->ignore($user->getKey())],
            'role'     => ['required', Rule::in(['admin', 'user'])],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.regex'   => 'Nama hanya boleh mengandung huruf dan spasi (tanpa gelar asing atau karakter khusus).',
            'pn.regex'     => 'PN harus berupa 4–10 digit angka (contoh: 90179583).',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'updateUser')
                ->withInput()
                ->with('open_edit_user', $user->getKey());
        }

        $data = $validator->validated();

        // ── Cek pola nama Faker/gelar asing ──
        foreach (self::BLOCKED_NAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $data['name'])) {
                return back()
                    ->withErrors(['name' => 'Format nama tidak valid. Nama tidak boleh mengandung gelar/suffix asing.'], 'updateUser')
                    ->withInput()
                    ->with('open_edit_user', $user->getKey());
            }
        }

        $currentUser = Auth::user();

        // ── Cegah admin aktif menurunkan role dirinya sendiri ──
        if ($currentUser && (int) $currentUser->getKey() === (int) $user->getKey() && $data['role'] !== 'admin') {
            return back()
                ->withErrors(['user_management' => 'Akun admin yang sedang aktif tidak boleh diturunkan menjadi user biasa.'], 'updateUser')
                ->withInput()
                ->with('open_edit_user', $user->getKey());
        }

        $oldRole = $user->role;
        $user->name = trim($data['name']);
        $user->pn   = trim($data['pn']);
        $user->role = $data['role'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // ── Audit log ──
        $this->writeAuditLog('update', $user, $currentUser, [
            'old_role' => $oldRole,
            'new_role' => $data['role'],
            'password_changed' => !empty($data['password']),
        ]);

        return back()->with('success', 'Data user berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        if ($currentUser && (int) $currentUser->getKey() === (int) $user->getKey()) {
            return back()
                ->withErrors(['user_management' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        // Simpan info untuk log sebelum delete
        $deletedInfo = ['id' => $user->getKey(), 'name' => $user->name, 'pn' => $user->pn];

        $user->delete();

        // ── Audit log ──
        $this->writeAuditLog('delete', null, $currentUser, $deletedInfo);

        return back()->with('success', 'User berhasil dihapus.');
    }

    /**
     * Tulis audit log ke Laravel daily log channel dan tabel user_audit_log.
     * Semua aksi CRUD user tercatat secara permanen.
     */
    private function writeAuditLog(string $action, ?User $targetUser, ?User $actor, array $extra = []): void
    {
        $actorId   = $actor?->getKey() ?? 0;
        $actorName = $actor?->name ?? 'Unknown';
        $actorPn   = $actor?->pn ?? '-';

        $targetId   = $targetUser?->getKey() ?? ($extra['id'] ?? null);
        $targetName = $targetUser?->name ?? ($extra['name'] ?? '-');
        $targetPn   = $targetUser?->pn ?? ($extra['pn'] ?? '-');

        $ipAddress = request()->ip();

        Log::channel('daily')->warning("[USER-AUDIT] action={$action} actor_id={$actorId} actor_name=\"{$actorName}\" actor_pn={$actorPn} target_id={$targetId} target_name=\"{$targetName}\" target_pn={$targetPn} ip={$ipAddress}", $extra);

        // Simpan ke tabel audit log (best-effort, tidak mengganggu operasi utama)
        try {
            \Illuminate\Support\Facades\DB::table('user_audit_log')->insert([
                'actor_id'    => $actorId,
                'action'      => $action,
                'target_id'   => $targetId,
                'target_name' => $targetName,
                'target_pn'   => $targetPn,
                'ip_address'  => $ipAddress,
                'extra'       => !empty($extra) ? json_encode($extra) : null,
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Tabel mungkin belum ada — log ke file sudah cukup sebagai fallback
        }
    }
}
