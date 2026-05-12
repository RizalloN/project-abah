<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * PERHATIAN: Seeder ini hanya boleh dijalankan di environment testing/local.
     * Di production, seeder ini akan langsung berhenti tanpa melakukan apapun.
     *
     * User factory TIDAK boleh dijalankan di production — akun dummy Faker
     * adalah risiko keamanan nyata (terbukti dari insiden 2026-05-09).
     */
    public function run(): void
    {
        // ── Guard production: STOP jika bukan environment local/testing ──
        if (App::isProduction()) {
            $this->command->error('');
            $this->command->error('  [SECURITY] DatabaseSeeder diblokir di environment PRODUCTION!  ');
            $this->command->error('  Seeder tidak boleh dijalankan di server live.               ');
            $this->command->error('  Jika perlu menambah user, gunakan halaman User Management.  ');
            $this->command->error('');
            return;
        }

        // ── Hanya untuk local/testing ──
        // User::factory(10)->create();  // ← JANGAN pernah aktifkan di production!
    }
}
