<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Only promote the reserved existing account; never create credentials.
        $updated = DB::table('users')
            ->where('pn', User::SUPERADMIN_PN)
            ->where('role', '!=', 'superadmin')
            ->update([
                'role' => 'superadmin',
                'remember_token' => Str::random(60),
                'updated_at' => now(),
            ]);

        $this->command?->info($updated > 0
            ? 'Akun '.User::SUPERADMIN_PN.' diperbarui menjadi superadmin. Silakan login kembali.'
            : 'Tidak ada perubahan akun superadmin.');
    }
}
