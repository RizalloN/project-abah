<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserIdReuseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('user');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropAllTables();

        parent::tearDown();
    }

    public function test_user_creation_reuses_smallest_missing_id_after_deletion(): void
    {
        DB::table('users')->insert([
            [
                'id' => 1,
                'name' => 'Admin',
                'pn' => '90000001',
                'password' => bcrypt('secret123'),
                'role' => 'admin',
            ],
            [
                'id' => 3,
                'name' => 'Existing User',
                'pn' => '90000003',
                'password' => bcrypt('secret123'),
                'role' => 'user',
            ],
        ]);

        $created = User::create([
            'name' => 'New User',
            'pn' => '90000002',
            'role' => 'user',
            'password' => 'secret123',
        ]);

        $this->assertSame(2, $created->id);
        $this->assertSame([1, 2, 3], DB::table('users')->orderBy('id')->pluck('id')->all());
    }

    public function test_user_creation_starts_from_id_one_when_table_is_empty(): void
    {
        $created = User::create([
            'name' => 'First User',
            'pn' => '90000010',
            'role' => 'user',
            'password' => 'secret123',
        ]);

        $this->assertSame(1, $created->id);
        $this->assertSame(1, DB::table('users')->count());
    }
}
