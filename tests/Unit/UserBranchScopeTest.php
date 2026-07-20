<?php

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Http\Controllers\Report\KinerjaNonPtpReportController;
use App\Http\Middleware\EnforceUserBranchScope;
use App\Models\User;
use App\Support\UserBranchScope;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

it('maps restricted PN accounts to their assigned branch', function (string $pn, string $key, string $label): void {
    $scope = UserBranchScope::forPn($pn);

    expect($scope)
        ->not->toBeNull()
        ->and($scope['key'])->toBe($key)
        ->and($scope['upper_label'])->toBe($label)
        ->and($scope['pn'])->toBe($pn);
})->with([
    ['0045', 'madiun', 'KC MADIUN'],
    ['0049', 'magetan', 'KC MAGETAN'],
    ['0057', 'ngawi', 'KC NGAWI'],
    ['0070', 'ponorogo', 'KC PONOROGO'],
]);

it('keeps every other PN unrestricted for Area 6', function (): void {
    expect(UserBranchScope::forPn('9999'))->toBeNull()
        ->and(UserBranchScope::forPn(null))->toBeNull();
});

it('uses the branch assigned by an admin instead of relying on PN', function (): void {
    $assignedUser = new User([
        'pn' => '99112233',
        'branch_scope' => 'ngawi',
    ]);
    $areaUser = new User([
        'pn' => '0045',
        'branch_scope' => UserBranchScope::AREA_SCOPE,
    ]);

    expect(UserBranchScope::forUser($assignedUser)['key'])->toBe('ngawi')
        ->and(UserBranchScope::forUser($assignedUser)['upper_label'])->toBe('KC NGAWI')
        ->and(UserBranchScope::forUser($areaUser))->toBeNull();
});

it('provides the supported user management scope options', function (): void {
    expect(UserBranchScope::options())->toBe([
        'area6' => 'Area 6 (Semua Cabang)',
        'madiun' => 'KC Madiun',
        'magetan' => 'KC Magetan',
        'ngawi' => 'KC Ngawi',
        'ponorogo' => 'KC Ponorogo',
    ]);
});

it('overrides branch request values for a restricted user', function (): void {
    $user = new User(['pn' => '0049']);
    $request = Request::create('/report', 'GET', [
        'cabang' => 'KC PONOROGO',
        'cabang1' => 'KC PONOROGO',
        'kanca' => 'KC PONOROGO',
        'wilayah' => 'ponorogo',
        'branch_office' => ['KC PONOROGO'],
        'kantor_cabang' => ['KC Ponorogo'],
    ]);
    $request->setUserResolver(fn (): User => $user);

    $response = (new EnforceUserBranchScope)->handle($request, function (Request $scopedRequest) {
        return response()->json($scopedRequest->all());
    });

    $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['cabang'])->toBe('KC Magetan')
        ->and($payload['cabang1'])->toBe('KC Magetan')
        ->and($payload['kanca'])->toBe('KC Magetan')
        ->and($payload['wilayah'])->toBe('magetan')
        ->and($payload['branch_office'])->toBe(['KC MAGETAN'])
        ->and($payload['kantor_cabang'])->toBe(['KC Magetan']);
});

it('preserves branch request values for an unrestricted user', function (): void {
    $user = new User(['pn' => '9999']);
    $request = Request::create('/report', 'GET', [
        'cabang' => 'KC PONOROGO',
        'wilayah' => 'ponorogo',
    ]);
    $request->setUserResolver(fn (): User => $user);

    $response = (new EnforceUserBranchScope)->handle($request, function (Request $scopedRequest) {
        return response()->json($scopedRequest->all());
    });

    $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['cabang'])->toBe('KC PONOROGO')
        ->and($payload['wilayah'])->toBe('ponorogo');
});

it('only exposes the assigned branch in controller filter options', function (): void {
    $this->actingAs(new User(['pn' => '0045']));

    $readPrivate = static function (object $controller, string $methodName) {
        $method = new ReflectionMethod($controller, $methodName);

        return $method->invoke($controller);
    };

    $kreditOptions = $readPrivate(new DashboardPinjamanReportController, 'kreditBranchOptions');
    $arrearsOptions = $readPrivate(new DashboardPinjamanReportController, 'smallArrearsBranchOptions');
    $nonPtpOptions = $readPrivate(new KinerjaNonPtpReportController, 'branchOptions');
    $almafactsOptions = $readPrivate(new AlmafactsDashboardController, 'branchOptions');

    expect($kreditOptions)->toBe([['value' => 'KC Madiun', 'label' => 'KC Madiun']])
        ->and($arrearsOptions->all())->toBe(['KC Madiun'])
        ->and($nonPtpOptions)->toBe(['KC Madiun' => 'KC Madiun'])
        ->and($almafactsOptions)->toBe(['KC Madiun' => 'KC Madiun']);
});

it('locks native and custom branch filters in the shared admin layout', function (): void {
    $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

    expect($layout)
        ->toContain('.filter-branch-checkbox')
        ->toContain('.dormant-branch-checkbox')
        ->toContain('#filterBranchDropdown')
        ->toContain('#businessClusterBranchDropdown')
        ->toContain('#dormantBranchDropdown')
        ->toContain('[data-loan-dropdown-toggle="kanca"]')
        ->toContain('[data-loan-dropdown-toggle="cabang"]')
        ->toContain('[data-dana-dropdown-toggle="cabang"]')
        ->toContain('[data-daily-dropdown-toggle="kanca"]')
        ->toContain('event.stopImmediatePropagation()');
});
