<?php

use App\Models\User;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

it('verifies password update behavior via controller update and login check', function (): void {
    $admin = User::create([
        'name' => 'Admin Test',
        'pn' => '10001000',
        'password' => Hash::make('AdminPass1234'),
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    $targetUser = User::create([
        'name' => 'Target User',
        'pn' => '20002000',
        'password' => Hash::make('OldTargetPass1234'),
        'role' => 'user',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    // Test 1: update with too short password "12345" (< 8 characters)
    $response = $this->actingAs($admin)->put(route('user-management.update', $targetUser), [
        'name' => $targetUser->name,
        'pn' => $targetUser->pn,
        'role' => $targetUser->role,
        'branch_scope' => $targetUser->branch_scope,
        'password' => '12345',
    ]);

    // This fails validation!
    $response->assertSessionHasErrors('password', null, 'updateUser');
    $response->assertSessionHas('error');

    $targetUser->refresh();
    // Old password is still intact!
    expect(Hash::check('OldTargetPass1234', $targetUser->password))->toBeTrue();
    expect(Hash::check('12345', $targetUser->password))->toBeFalse();

    // Test 2: update with valid password of at least 8 characters (e.g. admin123 or NewPassword123)
    $response2 = $this->actingAs($admin)->put(route('user-management.update', $targetUser), [
        'name' => $targetUser->name,
        'pn' => $targetUser->pn,
        'role' => $targetUser->role,
        'branch_scope' => $targetUser->branch_scope,
        'password' => 'admin123',
    ]);

    $response2->assertRedirect()->assertSessionHas('success');

    $targetUser->refresh();
    expect(Hash::check('admin123', $targetUser->password))->toBeTrue();
    expect(Hash::check('OldTargetPass1234', $targetUser->password))->toBeFalse();
});

it('allows an admin to update password of another admin user', function (): void {
    $admin1 = User::create([
        'name' => 'Super Admin',
        'pn' => '10001001',
        'password' => Hash::make('AdminPass1234'),
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    $admin2 = User::create([
        'name' => 'Second Admin',
        'pn' => '10001002',
        'password' => Hash::make('AdminOldPass123'),
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    $response = $this->actingAs($admin1)->put(route('user-management.update', $admin2), [
        'name' => $admin2->name,
        'pn' => $admin2->pn,
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
        'password' => 'bri12345',
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $admin2->refresh();
    expect(Hash::check('bri12345', $admin2->password))->toBeTrue();
});

it('logs out and redirects to login when admin updates their own password', function (): void {
    $admin = User::create([
        'name' => 'Self Admin',
        'pn' => '10001003',
        'password' => Hash::make('AdminOldPass123'),
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    $response = $this->actingAs($admin)->put(route('user-management.update', $admin), [
        'name' => $admin->name,
        'pn' => $admin->pn,
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
        'password' => 'MyNewPassword123',
    ]);

    $response->assertRedirect(route('login'))->assertSessionHas('status');

    $admin->refresh();
    expect(Hash::check('MyNewPassword123', $admin->password))->toBeTrue();
});

it('renders user management view with custom Toast elements and password toggle button', function (): void {
    $admin = User::create([
        'name' => 'View Admin',
        'pn' => '10001004',
        'password' => Hash::make('AdminPass1234'),
        'role' => 'admin',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    $response = $this->actingAs($admin)->get(route('user-management.index'));

    $response->assertOk()
        ->assertSee('toggle-password-visibility')
        ->assertSee('user-toast-stack')
        ->assertSee('showUserToast');
});
