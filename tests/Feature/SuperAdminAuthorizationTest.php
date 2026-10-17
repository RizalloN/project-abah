<?php

namespace Tests\Feature;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\UserBranchScope;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SuperAdminAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('branch_scope')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('login_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('login_at');
        });
        Schema::create('user_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('action');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_name')->nullable();
            $table->string('target_pn')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('extra')->nullable();
            $table->timestamp('created_at');
        });

        app('session.store')->start();
        Auth::forgetGuards();
        $this->withoutVite();
        View::share('errors', new ViewErrorBag);
    }

    #[DataProvider('roleIdentities')]
    public function test_superadmin_privilege_requires_both_the_reserved_pn_and_role(string $pn, string $role, bool $superadmin, bool $admin): void
    {
        $user = new User(['pn' => $pn, 'role' => $role]);

        $this->assertSame($superadmin, $user->isSuperAdmin());
        $this->assertSame($admin, $user->isAdmin());
    }

    public static function roleIdentities(): array
    {
        return [
            'designated superadmin' => ['90179583', 'superadmin', true, true],
            'forged superadmin' => ['80000001', 'superadmin', false, false],
            'prefixed reserved pn' => ['090179583', 'superadmin', false, false],
            'reserved pn without role' => ['90179583', 'user', false, false],
            'reserved pn still admin' => ['90179583', 'admin', false, true],
            'ordinary admin' => ['80000001', 'admin', false, true],
            'ordinary user' => ['80000002', 'user', false, false],
        ];
    }

    public function test_the_designated_superadmin_can_authenticate_but_another_pn_cannot_claim_the_role(): void
    {
        $superadmin = $this->createUser('superadmin', '90179583');
        $this->createUser('superadmin', '80000001');
        $request = LoginRequest::create('/login', 'POST', ['pn' => $superadmin->pn, 'password' => 'Original-password-123']);
        $this->assertSame($superadmin->id, $request->authenticate()->id);

        $this->expectException(ValidationException::class);
        LoginRequest::create('/login', 'POST', ['pn' => '80000001', 'password' => 'Original-password-123'])->authenticate();
    }

    public function test_activation_promotes_only_the_reserved_existing_account_and_is_idempotent(): void
    {
        $owner = $this->createUser('admin', '90179583');
        $otherAdmin = $this->createUser('admin', '80000001');
        $otherUser = $this->createUser('user', '80000002');
        $originalPassword = $owner->password;
        $otherAdminBefore = $otherAdmin->fresh()->getAttributes();
        $otherUserBefore = $otherUser->fresh()->getAttributes();

        app(SuperAdminSeeder::class)->run();

        $activated = $owner->fresh();
        $this->assertTrue($activated->isSuperAdmin());
        $this->assertSame($originalPassword, $activated->password);
        $this->assertSame($owner->branch_scope, $activated->branch_scope);
        $this->assertSame($otherAdminBefore, $otherAdmin->fresh()->getAttributes());
        $this->assertSame($otherUserBefore, $otherUser->fresh()->getAttributes());
        $this->assertSame(60, strlen($activated->getRememberToken()));
        $afterFirstActivation = $activated->getAttributes();

        app(SuperAdminSeeder::class)->run();

        $this->assertSame($afterFirstActivation, $owner->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 3);
    }

    public function test_activation_does_not_create_credentials_when_the_reserved_account_is_absent(): void
    {
        app(SuperAdminSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_every_exclusive_route_enforces_the_superadmin_boundary_before_the_endpoint(): void
    {
        $identities = [
            'superadmin' => new User(['pn' => '90179583', 'role' => 'superadmin']),
            'admin' => new User(['pn' => '80000001', 'role' => 'admin']),
            'user' => new User(['pn' => '80000002', 'role' => 'user']),
            'forged superadmin' => new User(['pn' => '80000003', 'role' => 'superadmin']),
        ];

        foreach (self::exclusiveRoutes() as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('role:superadmin', $route->gatherMiddleware(), $name);

            foreach ($identities as $label => $identity) {
                $response = $this->roleBoundary($name, $identity);
                $this->assertSame($label === 'superadmin' ? 204 : 403, $response->getStatusCode(), $name.' / '.$label);
            }
        }

        foreach (Route::getRoutes() as $route) {
            if (preg_match('/^(job-management|link-management)\./', $route->getName() ?? '')) {
                $this->assertContains($route->getName(), self::exclusiveRoutes(), 'New management endpoints must be included in the access matrix.');
            }
        }
    }

    public function test_admin_and_superadmin_retain_report_read_load_and_normal_import_access(): void
    {
        foreach (['report-management.index', 'import.report-management.data', 'import.report-management.load', 'import.report-management.load.status', 'import.index', 'user-management.index', 'file-management.index', 'file-management.download', 'file-management.destroy'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertNotContains('role:superadmin', $route->gatherMiddleware(), $name);

            foreach ([['80000001', 'admin'], ['90179583', 'superadmin']] as [$pn, $role]) {
                $this->assertSame(204, $this->roleBoundary($name, new User(compact('pn', 'role')))->getStatusCode(), $name.' / '.$role);
            }
            $this->assertSame(403, $this->roleBoundary($name, new User(['pn' => '80000002', 'role' => 'user']))->getStatusCode(), $name);
        }
    }

    #[DataProvider('directRestrictedRequests')]
    public function test_admin_direct_requests_to_protected_features_return_403(string $method, string $name): void
    {
        $admin = $this->createUser('admin', '80000001');

        $this->actingAs($admin)->json($method, route($name), [])->assertForbidden();

        $this->assertDatabaseCount('users', 1);
    }

    public static function directRestrictedRequests(): array
    {
        return [
            ['POST', 'import.report-management.delete'],
            ['POST', 'import.report-management.rebuild'],
            ['POST', 'import.report-management.recover'],
            ['POST', 'file-management.database-backup'],
            ['GET', 'job-management.index'],
            ['POST', 'link-management.update'],
        ];
    }

    #[DataProvider('protectedAccountTargets')]
    public function test_admin_cannot_change_or_delete_a_peer_admin_itself_or_the_superadmin(string $targetRole, string $targetPn): void
    {
        $actor = $this->createUser('admin', '80000001');
        $target = $targetPn === $actor->pn ? $actor : $this->createUser($targetRole, $targetPn);
        $original = $target->fresh()->getAttributes();

        $this->actingAs($actor)->putJson(route('user-management.update', $target), $this->profile($target, ['password' => 'Changed-password-123']))->assertForbidden();
        $this->deleteJson(route('user-management.destroy', $target))->assertForbidden();

        $this->assertSame($original, $target->fresh()->getAttributes());
        $this->assertDatabaseCount('user_audit_log', 0);
    }

    public static function protectedAccountTargets(): array
    {
        return [
            'peer admin' => ['admin', '80000002'],
            'own admin account' => ['admin', '80000001'],
            'superadmin' => ['superadmin', '90179583'],
        ];
    }

    #[DataProvider('invalidAdminCreates')]
    public function test_admin_cannot_create_privileged_accounts_or_take_the_reserved_pn(string $role, string $pn, string $errorField): void
    {
        $admin = $this->createUser('admin', '80000001');

        $response = $this->actingAs($admin)->post(route('user-management.store'), [
            'name' => 'Budi Santoso',
            'pn' => $pn,
            'role' => $role,
            'branch_scope' => UserBranchScope::AREA_SCOPE,
            'password' => 'Initial-password-123',
        ]);

        $response->assertRedirect()->assertSessionHasErrors([$errorField], null, 'createUser');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_audit_log', 0);
    }

    public static function invalidAdminCreates(): array
    {
        return [
            ['admin', '80000002', 'role'],
            ['superadmin', '80000002', 'role'],
            ['user', '90179583', 'pn'],
        ];
    }

    #[DataProvider('invalidAdminUpdates')]
    public function test_admin_cannot_promote_a_user_or_replace_its_pn_with_the_reserved_identity(array $changes, string $errorField): void
    {
        $admin = $this->createUser('admin', '80000001');
        $target = $this->createUser('user', '80000002');
        $original = $target->fresh()->getAttributes();

        $response = $this->actingAs($admin)->put(route('user-management.update', $target), $this->profile($target, $changes));

        $response->assertRedirect()->assertSessionHasErrors([$errorField], null, 'updateUser');
        $this->assertSame($original, $target->fresh()->getAttributes());
        $this->assertDatabaseCount('user_audit_log', 0);
    }

    public static function invalidAdminUpdates(): array
    {
        return [
            [['role' => 'admin'], 'role'],
            [['role' => 'superadmin'], 'role'],
            [['pn' => '90179583'], 'pn'],
        ];
    }

    #[DataProvider('passwordResetActors')]
    public function test_allowed_password_resets_rotate_credentials_and_preserve_the_audit_log(string $actorRole, string $actorPn, string $targetRole): void
    {
        $actor = $this->createUser($actorRole, $actorPn);
        $target = $this->createUser($targetRole, '80000002');
        $target->setRememberToken('old-remember-token');
        $target->save();

        $this->actingAs($actor)->put(route('user-management.update', $target), $this->profile($target, ['password' => 'Changed-password-123']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $updated = $target->fresh();
        $this->assertTrue(Hash::check('Changed-password-123', $updated->password));
        $this->assertNotSame('old-remember-token', $updated->getRememberToken());
        $this->assertSame($targetRole, $updated->role);
        $audit = DB::table('user_audit_log')->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame($target->id, $audit->target_id);
        $this->assertSame('update', $audit->action);
        $this->assertTrue(json_decode($audit->extra, true)['password_changed']);
        $this->assertTrue(json_decode($audit->extra, true)['sessions_revoked']);
        $this->assertStringNotContainsString('Changed-password-123', $audit->extra);
    }

    public static function passwordResetActors(): array
    {
        return [
            ['admin', '80000001', 'user'],
            ['superadmin', '90179583', 'admin'],
            ['superadmin', '90179583', 'user'],
        ];
    }

    public function test_superadmin_can_create_an_admin_and_admin_can_create_and_delete_a_user(): void
    {
        $superadmin = $this->createUser('superadmin', '90179583');
        $profile = ['name' => 'Budi Santoso', 'pn' => '80000001', 'role' => 'admin', 'branch_scope' => UserBranchScope::AREA_SCOPE, 'password' => 'Initial-password-123'];

        $this->actingAs($superadmin)->post(route('user-management.store'), $profile)->assertRedirect()->assertSessionHasNoErrors();
        $admin = User::query()->where('pn', '80000001')->sole();
        $this->assertSame('admin', $admin->role);

        $this->actingAs($admin)->post(route('user-management.store'), array_replace($profile, ['pn' => '80000002', 'role' => 'user']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $target = User::query()->where('pn', '80000002')->sole();
        $this->delete(route('user-management.destroy', $target))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['pn' => '80000002']);
        $this->assertSame(['create', 'create', 'delete'], DB::table('user_audit_log')->orderBy('id')->pluck('action')->all());
    }

    public function test_superadmin_can_promote_and_demote_ordinary_accounts_but_cannot_make_another_superadmin(): void
    {
        $actor = $this->createUser('superadmin', '90179583');
        $target = $this->createUser('user', '80000002');

        foreach (['admin', 'user'] as $role) {
            $this->actingAs($actor)->put(route('user-management.update', $target), $this->profile($target, ['role' => $role]))
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($role, $target->fresh()->role);
            $target->refresh();
        }

        $this->put(route('user-management.update', $target), $this->profile($target, ['role' => 'superadmin']))
            ->assertRedirect()->assertSessionHasErrors(['role'], null, 'updateUser');
        $this->assertSame('user', $target->fresh()->role);
    }

    #[DataProvider('reservedIdentityChanges')]
    public function test_the_reserved_superadmin_cannot_demote_or_rename_its_identity(array $changes, string $errorField): void
    {
        $superadmin = $this->createUser('superadmin', '90179583');
        $original = $superadmin->fresh()->getAttributes();

        $this->actingAs($superadmin)->put(route('user-management.update', $superadmin), $this->profile($superadmin, $changes))
            ->assertRedirect()->assertSessionHasErrors([$errorField], null, 'updateUser');
        $this->assertSame($original, $superadmin->fresh()->getAttributes());
        $this->deleteJson(route('user-management.destroy', $superadmin))->assertForbidden();
        $this->assertNotNull($superadmin->fresh());
    }

    public static function reservedIdentityChanges(): array
    {
        return [[['role' => 'admin'], 'role'], [['pn' => '80000002'], 'pn']];
    }

    public function test_admin_cannot_download_or_delete_database_backups_and_mixed_requests_never_partially_delete(): void
    {
        $admin = $this->createUser('admin', '80000001');
        $token = 'superadmin_auth_'.bin2hex(random_bytes(8));
        $backupRelativePath = 'private/database_backups/'.$token.'.sql';
        $importRelativePath = 'private/excel_imports/'.$token.'.tmp';
        $backupPath = storage_path('app/'.$backupRelativePath);
        $importPath = storage_path('app/'.$importRelativePath);
        File::ensureDirectoryExists(dirname($backupPath));
        File::ensureDirectoryExists(dirname($importPath));
        File::put($backupPath, 'backup authorization fixture');
        File::put($importPath, 'import artifact authorization fixture');

        try {
            $this->actingAs($admin)->getJson(route('file-management.download', ['path' => $backupRelativePath]))->assertForbidden();
            $this->postJson(route('file-management.destroy'), ['scope' => 'database_backups', 'paths' => [$backupRelativePath]])->assertForbidden();
            $this->postJson(route('file-management.destroy'), ['scope' => 'import_artifacts', 'paths' => [$importRelativePath, $backupRelativePath]])->assertForbidden();
            $this->assertFileExists($backupPath);
            $this->assertFileExists($importPath);

            $this->get(route('file-management.download', ['path' => $importRelativePath]))->assertDownload(basename($importPath));
            $this->postJson(route('file-management.destroy'), ['scope' => 'import_artifacts', 'paths' => [$importRelativePath]])
                ->assertOk()->assertJsonPath('deleted_count', 1);
            $this->assertFileDoesNotExist($importPath);
            $this->assertFileExists($backupPath);
        } finally {
            File::delete([$backupPath, $importPath]);
        }
    }

    public function test_real_report_management_blade_hides_superadmin_controls_for_admin_and_preserves_report_loading(): void
    {
        foreach ([['admin', '80000001', false], ['superadmin', '90179583', true]] as [$role, $pn, $privileged]) {
            Auth::setUser(new User(['id' => 1, 'name' => 'Authorization Admin', 'pn' => $pn, 'role' => $role, 'branch_scope' => UserBranchScope::AREA_SCOPE]));
            $html = view('import.report-management', ['reports' => collect(), 'backupFiles' => []])->render();

            $this->assertStringContainsString('id="management-report-select"', $html);
            $this->assertStringContainsString('id="btn-management-filter"', $html);
            foreach (['btn-management-recover', 'btn-management-force-sync', 'btn-management-rebuild', 'btn-management-deduplicate', 'btn-management-delete-selected'] as $id) {
                $this->assertSame($privileged, str_contains($html, 'id="'.$id.'"'), $role.' / '.$id);
            }
            foreach (['job-management.index', 'link-management.index'] as $route) {
                $this->assertSame($privileged, str_contains($html, 'href="'.route($route).'"'), $role.' / '.$route);
            }
            $this->assertStringContainsString('href="'.route('file-management.index').'"', $html);
        }
    }

    #[DataProvider('hiddenAccounts')]
    public function test_admin_directory_excludes_protected_accounts_from_rows_pagination_statistics_and_markup(string $role, string $pn): void
    {
        $protected = $this->createUser($role, $pn);
        $protected->update(['name' => 'Pemilik Portal Rahasia']);
        $admin = $this->createUser('admin', '80000001');
        $user = $this->createUser('user', '80000002');

        $response = $this->actingAs($admin)->get(route('user-management.index'));

        $response->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 2
                && $users->pluck('id')->sort()->values()->all() === [$admin->id, $user->id])
            ->assertViewHas('stats', ['total' => 2, 'admins' => 1, 'users' => 1])
            ->assertDontSee($protected->pn)
            ->assertDontSee($protected->name)
            ->assertDontSee('<div class="user-stat-pill__label">Superadmin</div>', false);
        $this->assertSame(3, User::count(), 'Visibility must not remove the account.');
    }

    public function test_superadmin_still_sees_the_complete_directory_and_statistics(): void
    {
        $superadmin = $this->createUser('superadmin', User::SUPERADMIN_PN);
        $this->createUser('admin', '80000001');
        $this->createUser('user', '80000002');

        $this->actingAs($superadmin)->get(route('user-management.index'))
            ->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 3 && $users->contains('id', $superadmin->id))
            ->assertViewHas('stats', ['total' => 3, 'admins' => 1, 'users' => 1, 'superadmins' => 1])
            ->assertSee(User::SUPERADMIN_PN)
            ->assertSee('<div class="user-stat-pill__label">Superadmin</div>', false);
    }

    #[DataProvider('hiddenAccounts')]
    public function test_admin_cannot_read_protected_login_history_by_direct_url(string $role, string $pn): void
    {
        $protected = $this->createUser($role, $pn);
        $admin = $this->createUser('admin', '80000001');
        DB::table('login_histories')->insert(['user_id' => $protected->id, 'login_at' => '2026-10-14 09:00:00']);

        $this->actingAs($admin)->getJson(route('user-management.login-history', $protected))
            ->assertNotFound()
            ->assertJsonMissingPath('user')
            ->assertJsonMissingPath('history');
    }

    public function test_visible_account_history_remains_available_to_admin_and_superadmin(): void
    {
        $superadmin = $this->createUser('superadmin', User::SUPERADMIN_PN);
        $admin = $this->createUser('admin', '80000001');
        $user = $this->createUser('user', '80000002');
        foreach ([$superadmin, $admin, $user] as $target) {
            DB::table('login_histories')->insert(['user_id' => $target->id, 'login_at' => '2026-10-14 09:00:00']);
        }

        foreach ([[$admin, $user], [$admin, $admin], [$superadmin, $superadmin], [$superadmin, $admin], [$superadmin, $user]] as [$actor, $target]) {
            $this->actingAs($actor)->getJson(route('user-management.login-history', $target))
                ->assertOk()
                ->assertJsonPath('user.pn', $target->pn)
                ->assertJsonPath('history.0.count', 1);
        }
    }

    public function test_users_cannot_open_the_directory_or_superadmin_history(): void
    {
        $superadmin = $this->createUser('superadmin', User::SUPERADMIN_PN);
        $user = $this->createUser('user', '80000002');

        $this->actingAs($user)->get(route('user-management.index'))->assertForbidden();
        $this->getJson(route('user-management.login-history', $superadmin))->assertForbidden();
    }

    public static function hiddenAccounts(): array
    {
        return [
            'superadmin' => ['superadmin', User::SUPERADMIN_PN],
            'reserved PN still admin' => ['admin', User::SUPERADMIN_PN],
            'reserved PN with user role' => ['user', User::SUPERADMIN_PN],
            'nonreserved superadmin role' => ['superadmin', '80000003'],
        ];
    }

    public function test_real_user_management_blade_omits_peer_edit_forms_for_admin_and_exposes_them_to_superadmin(): void
    {
        $superadmin = $this->createUser('superadmin', '90179583');
        $admin = $this->createUser('admin', '80000001');
        $user = $this->createUser('user', '80000002');
        $data = [
            'users' => collect([$superadmin, $admin, $user]),
            'stats' => ['total' => 3, 'superadmins' => 1, 'admins' => 1, 'users' => 1],
            'branchScopeOptions' => UserBranchScope::options(),
        ];

        Auth::setUser($admin);
        $html = view('admin.user-management', $data)->render();
        $this->assertStringContainsString('action="'.route('user-management.update', $user).'"', $html);
        $this->assertStringNotContainsString('action="'.route('user-management.update', $admin).'"', $html);
        $this->assertStringNotContainsString('action="'.route('user-management.update', $superadmin).'"', $html);
        $this->assertStringNotContainsString('<option value="admin"', $html);
        $this->assertStringNotContainsString('<option value="superadmin"', $html);

        Auth::setUser($superadmin);
        $this->assertTrue(Auth::user()->isSuperAdmin());
        $html = view('admin.user-management', $data)->render();
        $this->assertTrue(str_contains($html, 'action="'.route('user-management.update', $admin).'"'), 'Superadmin must receive the admin edit form.');
        $this->assertStringContainsString('<option value="admin"', $html);
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $deleteForms = $xpath->query('//form[@action="'.route('user-management.destroy', $superadmin).'"][input[@name="_method" and @value="DELETE"]]');
        $this->assertCount(0, $deleteForms, 'The reserved account must not have a delete form.');
    }

    public function test_file_controls_and_both_import_previews_render_the_current_roles_permissions(): void
    {
        foreach ([['admin', '80000001', false], ['superadmin', '90179583', true]] as [$role, $pn, $privileged]) {
            Auth::setUser(new User(['name' => 'Authorization Admin', 'pn' => $pn, 'role' => $role, 'branch_scope' => UserBranchScope::AREA_SCOPE]));
            $html = view('admin.file-management', [
                'directories' => [], 'files' => collect(), 'totals' => ['files' => 0, 'size_human' => '0 B'],
            ])->render();

            $this->assertTrue(str_contains($html, 'id="btn-file-management-cleanup"'), 'Admin must retain artifact cleanup.');
            foreach (['btn-file-management-snapshot-check', 'btn-file-management-backup'] as $id) {
                $this->assertSame($privileged, str_contains($html, 'id="'.$id.'"'), $role.' / '.$id);
            }
            $this->assertSame($privileged, str_contains($html, 'data-backup-url="'), $role.' backup URL metadata');
            $this->assertSame($privileged, str_contains($html, 'data-backup-status-url-template="'), $role.' backup status metadata');

            foreach (['import.preview', 'import.preview_excel'] as $view) {
                $html = view($view, [
                    'headers' => ['NAMA_UKER'], 'previewData' => [['UNIT']], 'preview' => [['UNIT']],
                    'filePath' => 'fixture/source.csv', 'path' => 'fixture/source.xlsx',
                    'currentDelimiter' => ',', 'formattedUniqueValues' => [['UNIT']],
                ])->render();

                $this->assertSame(1, preg_match('/(?:const|var) allowForceStart\s*=\s*(true|false)/', $html, $matches), $view.' permission flag');
                $this->assertSame($privileged ? 'true' : 'false', $matches[1], $view.' / '.$role);
            }
        }
    }

    private function createUser(string $role, string $pn): User
    {
        return User::create(['name' => 'Budi Santoso', 'pn' => $pn, 'role' => $role, 'branch_scope' => UserBranchScope::AREA_SCOPE, 'password' => 'Original-password-123']);
    }

    private function profile(User $user, array $changes = []): array
    {
        return array_replace($user->only('name', 'pn', 'role', 'branch_scope'), $changes);
    }

    private function roleBoundary(string $name, User $user): Response
    {
        app(Kernel::class);
        Auth::setUser($user);
        $request = Request::create('/authorization-probe');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');
        $route = Route::getRoutes()->getByName($name);
        $request->setRouteResolver(fn () => $route);
        $middleware = array_values(array_filter($route->gatherMiddleware(), fn (string $middleware) => str_starts_with($middleware, 'role:')));
        $middleware = app('router')->resolveMiddleware($middleware);
        $this->assertNotEmpty($middleware, $name.' must have an explicit role boundary.');

        try {
            return app(Pipeline::class)->send($request)->through($middleware)->then(fn () => response('', 204));
        } catch (\Throwable $exception) {
            return app(ExceptionHandler::class)->render($request, $exception);
        }
    }

    private static function exclusiveRoutes(): array
    {
        return [
            'job-management.index', 'job-management.badge', 'job-management.data',
            'job-management.runtime.status', 'job-management.runtime.control',
            'job-management.worker.start', 'job-management.worker.stop', 'job-management.worker.restart', 'job-management.worker.restart.cancel',
            'job-management.clear', 'job-management.bulk-destroy', 'job-management.force-start', 'job-management.snapshot.force-start',
            'job-management.terminate', 'job-management.destroy', 'job-management.queue.destroy', 'job-management.queue.force-run', 'job-management.queue.purge',
            'file-management.snapshots.latest-check', 'file-management.database-backup', 'file-management.database-backup.status',
            'link-management.index', 'link-management.update',
            'import.queue-status',
            'import.report-management.rebuild', 'import.report-management.rebuild.status',
            'import.report-management.recover', 'import.report-management.recover.status',
            'import.report-management.force-sync', 'import.report-management.force-sync.status',
            'import.report-management.delete', 'import.report-management.duplicates', 'import.report-management.delete.process',
            'import.report-management.delete.status', 'import.report-management.delete.force-stop', 'import.report-management.delete.cancel',
            'snapshot-audit.rebuild',
        ];
    }
}
