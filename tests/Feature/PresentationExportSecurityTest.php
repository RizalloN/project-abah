<?php

use App\Http\Controllers\DashboardSimpananController;
use App\Models\User;
use App\Services\Presentation\PowerPointExportService;
use App\Services\Presentation\PresentationExportManager;
use Illuminate\Http\Request;

function presentationSecurityOptions(string $scope): array
{
    return [
        'title' => 'Review cabang',
        'periode' => '2026-09-30',
        'global_scope' => $scope,
        'funding_scope' => $scope,
        'funding_product' => 'total',
        'sme_scope' => $scope,
        'sme_product' => 'total',
        'consumer_scope' => $scope,
        'consumer_product' => 'total',
    ];
}

it('rejects foreign export scopes before reading data or queuing a deck', function (string $endpoint, string $field, string $foreign): void {
    $user = (new User)->forceFill(['id' => 1, 'pn' => '12345678', 'role' => 'user', 'branch_scope' => 'madiun']);
    $this->mock(PowerPointExportService::class)->shouldNotReceive('export');
    $this->mock(PresentationExportManager::class)->shouldNotReceive('queue');
    $options = presentationSecurityOptions('KC Madiun');
    $options[$field] = $foreign;

    $this->actingAs($user)->postJson(route($endpoint), $options)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['dashboard.presentation.export-pptx', 'dashboard.presentation.export-pptx.start'])
    ->with(['global_scope', 'funding_scope', 'sme_scope', 'consumer_scope'])
    ->with(['KC Ponorogo', 'area6']);

it('preserves valid export options for every assigned branch and area account', function (string $assigned, string $selected): void {
    $user = (new User)->forceFill(['id' => 1, 'pn' => '12345678', 'role' => 'user', 'branch_scope' => $assigned]);
    $options = presentationSecurityOptions($selected);
    $request = Request::create('/presentation-export', 'POST', $options);
    $request->setUserResolver(fn () => $user);

    $validated = (new ReflectionMethod(DashboardSimpananController::class, 'validatedPresentationExport'))
        ->invoke(app(DashboardSimpananController::class), $request);

    expect($validated)->toBe($options);
})->with([
    ['madiun', 'KC Madiun'],
    ['magetan', 'KC Magetan'],
    ['ngawi', 'KC Ngawi'],
    ['ponorogo', 'KC Ponorogo'],
    ['area6', 'area6'],
    ['area6', 'KC Madiun'],
    ['area6', 'KC Magetan'],
    ['area6', 'KC Ngawi'],
    ['area6', 'KC Ponorogo'],
]);

it('renders only permitted export choices while preserving all area account choices', function (string $assigned, array $expected): void {
    $user = (new User)->forceFill(['id' => 1, 'pn' => '12345678', 'role' => 'user', 'branch_scope' => $assigned]);
    $this->actingAs($user);
    $html = view('presentation', [
        'selectedPeriod' => '2026-09-30',
        'periods' => ['2026-09-30'],
        'presentationPayload' => null,
    ])->render();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    foreach (['global_scope', 'funding_scope', 'sme_scope', 'consumer_scope'] as $name) {
        $values = [];
        foreach ($xpath->query('//select[@name="'.$name.'"]/option') as $option) {
            $values[] = $option->getAttribute('value');
        }
        expect($values)->toBe($expected);
    }
})->with([
    ['madiun', ['KC Madiun']],
    ['ponorogo', ['KC Ponorogo']],
    ['area6', ['area6', 'KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo']],
]);
