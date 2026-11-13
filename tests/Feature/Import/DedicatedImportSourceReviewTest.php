<?php

use App\Http\Controllers\Import\ImportCasaBrilinkController;
use App\Http\Controllers\Import\ImportCognosPhController;
use App\Http\Controllers\Import\ImportCognosRecoveryController;
use App\Http\Controllers\Import\ImportCrasController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Http\Controllers\Import\ImportReportPhController;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    $this->sourcePath = 'cras_uploads/import-review-source.csv';
    Storage::put($this->sourcePath, "POSISI,SALDO\n18/10/2026,1500.25\n");
});

afterEach(function () {
    Storage::delete($this->sourcePath);
    Cache::flush();
});

it('checks the original upload before dedicated streams prepare or write rows', function (string $controllerClass, string $sessionKey, string $table) {
    $absolutePath = Storage::path($this->sourcePath);
    $this->mock(SchemaDriftImportGuard::class, function ($mock) use ($absolutePath, $table) {
        $mock->shouldReceive('assertReady')->once()->with($table, $absolutePath)
            ->andThrow(new RuntimeException('SOURCE_REVIEW_REQUIRED'));
    });
    $request = Request::create('/import/review-test/stream', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put($sessionKey, [
        'file_path' => $this->sourcePath, 'table_name' => $table,
        'job_id' => 0, 'selected_columns' => [0, 1], 'total_rows' => 1,
    ]);
    app()->instance('request', $request);
    $response = app($controllerClass)->processImportStream($request);

    ob_start();
    ob_start();
    try {
        $response->sendContent();
    } finally {
        ob_end_clean();
        $output = ob_get_clean();
    }

    expect($output)->toContain('SOURCE_REVIEW_REQUIRED')
        ->and(Storage::exists($this->sourcePath))->toBeTrue();
})->with([
    'Cognos PH' => [ImportCognosPhController::class, 'cognos_ph_import_params', 'cognos_ph'],
    'Cognos Recovery' => [ImportCognosRecoveryController::class, 'cognos_recovery_import_params', 'cognos_recovery'],
    'CASA BRILink' => [ImportCasaBrilinkController::class, 'casa_import_params', 'casa_brilink_web'],
    'Performance PIS' => [ImportPerformancePisPerProdukController::class, 'performance_pis_import_params', 'performance_pis_per_produk'],
]);

it('checks the original PH upload before a prepared CSV can replace it', function () {
    $absolutePath = Storage::path($this->sourcePath);
    $this->mock(SchemaDriftImportGuard::class, function ($mock) use ($absolutePath) {
        $mock->shouldReceive('assertReady')->once()->with('lw325_ph', $absolutePath)
            ->andThrow(new RuntimeException('SOURCE_REVIEW_REQUIRED'));
    });

    expect(fn () => app(ImportReportPhController::class)->executeQueuedImport([
        'params' => ['file_path' => $this->sourcePath, 'staged_csv_path' => 'already-filtered.csv'],
    ]))->toThrow(RuntimeException::class, 'SOURCE_REVIEW_REQUIRED')
        ->and(Storage::exists($this->sourcePath))->toBeTrue();
});

it('checks the original CRAS upload before exact staging starts', function () {
    $absolutePath = realpath(Storage::path($this->sourcePath));
    $this->mock(SchemaDriftImportGuard::class, function ($mock) use ($absolutePath) {
        $mock->shouldReceive('assertReady')->once()->with('cras', $absolutePath)
            ->andThrow(new RuntimeException('SOURCE_REVIEW_REQUIRED'));
    });

    expect(fn () => app(ImportCrasController::class)->executeQueuedImport([
        'params' => ['file_path' => $this->sourcePath],
    ]))->toThrow(RuntimeException::class, 'SOURCE_REVIEW_REQUIRED')
        ->and(Storage::exists($this->sourcePath))->toBeTrue();
});
