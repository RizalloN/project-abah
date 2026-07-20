<?php

use App\Models\User;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
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
        $table->string('password');
        $table->string('role', 20)->default('user');
        $table->string('branch_scope', 20)->default(UserBranchScope::AREA_SCOPE);
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('login_histories', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->timestamp('login_at');
    });

    Schema::create('user_audit_log', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('actor_id');
        $table->string('action', 20);
        $table->unsignedBigInteger('target_id')->nullable();
        $table->string('target_name')->nullable();
        $table->string('target_pn', 20)->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('extra')->nullable();
        $table->timestamp('created_at')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropAllTables();
});

it('renders branch assignment controls and the current user scope for admins', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Area',
        'pn' => '90001000',
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);
    User::factory()->create([
        'name' => 'User Ngawi',
        'pn' => '90002000',
        'role' => 'user',
        'branch_scope' => 'ngawi',
    ]);

    $this->actingAs($admin)
        ->get(route('user-management.index'))
        ->assertOk()
        ->assertSee('Wilayah Binaan')
        ->assertSee('KC Ngawi')
        ->assertSee('data-user-scope-admin-control', false)
        ->assertSee('name="branch_scope"', false);
});

it('allows an admin to create a user for one assigned branch', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Area',
        'pn' => '90001001',
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    $response = $this->actingAs($admin)->post(route('user-management.store'), [
        'name' => 'User Magetan',
        'pn' => '90002001',
        'role' => 'user',
        'branch_scope' => 'magetan',
        'password' => 'password123',
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $createdUser = User::where('pn', '90002001')->firstOrFail();
    expect($createdUser->branch_scope)->toBe('magetan')
        ->and(UserBranchScope::forUser($createdUser)['key'])->toBe('magetan');
});

it('allows an admin to change a user between branch and area access', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Area',
        'pn' => '90001002',
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);
    $managedUser = User::factory()->create([
        'name' => 'User Cabang',
        'pn' => '90002002',
        'role' => 'user',
        'branch_scope' => 'madiun',
    ]);

    $response = $this->actingAs($admin)->put(route('user-management.update', $managedUser), [
        'name' => $managedUser->name,
        'pn' => $managedUser->pn,
        'role' => $managedUser->role,
        'branch_scope' => UserBranchScope::AREA_SCOPE,
        'password' => '',
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $managedUser->refresh();
    expect($managedUser->branch_scope)->toBe(UserBranchScope::AREA_SCOPE)
        ->and(UserBranchScope::forUser($managedUser))->toBeNull();
});

it('rejects unsupported branch assignments', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Area',
        'pn' => '90001003',
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    $response = $this->actingAs($admin)->post(route('user-management.store'), [
        'name' => 'User Invalid',
        'pn' => '90002003',
        'role' => 'user',
        'branch_scope' => 'surabaya',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors('branch_scope', null, 'createUser');
    $this->assertDatabaseMissing('users', ['pn' => '90002003']);
});

it('keeps user scope management inaccessible to non admin roles', function (): void {
    $user = User::factory()->create([
        'name' => 'User Biasa',
        'pn' => '90001004',
        'role' => 'user',
        'branch_scope' => 'ponorogo',
    ]);

    $this->actingAs($user)
        ->get(route('user-management.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('user-management.store'), [
            'name' => 'Tidak Diizinkan',
            'pn' => '90002004',
            'role' => 'user',
            'branch_scope' => 'madiun',
            'password' => 'password123',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['pn' => '90002004']);
});
