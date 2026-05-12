<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  ⚠️  PERINGATAN KEAMANAN — HANYA UNTUK TESTING/LOCAL            ║
 * ║                                                                  ║
 * ║  UserFactory menggunakan Faker (fake()->name()) yang             ║
 * ║  menghasilkan nama asing seperti "Miss Edwina Monahan PhD"       ║
 * ║  dan "Candido Pollich" — TIDAK cocok untuk production!           ║
 * ║                                                                  ║
 * ║  JANGAN panggil User::factory() di luar test suite atau          ║
 * ║  DatabaseSeeder environment local. Insiden: 2026-05-09.          ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     * Hanya untuk digunakan dalam automated tests (PHPUnit/Pest).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Guard: blokir eksekusi factory di production environment
        if (\Illuminate\Support\Facades\App::isProduction()) {
            throw new \RuntimeException(
                '[SECURITY] UserFactory tidak boleh digunakan di environment PRODUCTION. ' .
                'Tambah user melalui halaman User Management.'
            );
        }

        return [
            'name' => fake()->name(),
            'pn' => fake()->unique()->numerify('########'),
            'role' => 'user',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Legacy hook kept as no-op because users no longer use email verification.
     */
    public function unverified(): static
    {
        return $this;
    }
}
