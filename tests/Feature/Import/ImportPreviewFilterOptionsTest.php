<?php

use App\Http\Controllers\Import\ImportFileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
    Schema::dropIfExists('jumlah_merchant_qris_detail');
    Schema::dropIfExists('nama_report');

    Schema::create('nama_report', function (Blueprint $table) {
        $table->id('id_report');
        $table->string('nama_report');
        $table->string('table_name');
        $table->boolean('active')->default(true);
        $table->string('import_controller', 150)->nullable();
        $table->boolean('requires_manual_periode')->default(false);
        $table->string('manual_periode_type', 20)->nullable();
        $table->string('manual_periode_label', 100)->nullable();
        $table->string('manual_periode_help', 255)->nullable();
        $table->timestamps();
    });

    Schema::create('jumlah_merchant_qris_detail', function (Blueprint $table) {
        $table->string('uniqueid_namareport', 255)->primary();
        $table->string('MBDESC', 150)->nullable();
        $table->string('BRDESC', 180)->nullable();
        $table->date('POSISI')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('jumlah_merchant_qris_detail');
    Schema::dropIfExists('nama_report');
});

it('loads qris detail filter options from the full file instead of the first preview rows', function () {
    $user = User::factory()->make([
        'role' => 'admin',
    ]);

    DB::table('nama_report')->insert([
        'id_report' => 99,
        'nama_report' => 'Jumlah Merchant Qris Detail',
        'table_name' => 'jumlah_merchant_qris_detail',
        'active' => 1,
        'import_controller' => 'ImportFileController',
        'requires_manual_periode' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $reportId = (int) DB::table('nama_report')
        ->where('table_name', 'jumlah_merchant_qris_detail')
        ->value('id_report');

    expect($reportId)->toBeGreaterThan(0);

    $csvDirectory = storage_path('framework/testing/import-preview');
    File::ensureDirectoryExists($csvDirectory);
    $csvPath = $csvDirectory . DIRECTORY_SEPARATOR . 'qris-detail-filter-full-scan.csv';

    $rows = ['MBDESC,BRDESC,POSISI,TAHUN'];

    for ($i = 1; $i <= 120; $i++) {
        $rows[] = sprintf('CABANG A,UKER A %03d,2025-04-30,2025', $i);
    }

    $rows[] = 'CABANG B,UKER B 999,2025-04-30,2025';

    File::put($csvPath, implode(PHP_EOL, $rows) . PHP_EOL);

    try {
        $this->be($user);
        $session = app('session.store');
        $session->start();
        $session->put('active_id_report', $reportId);

        $request = Request::create('/import/preview/filter-options', 'GET', [
            'file_path' => $csvPath,
            'delimiter' => ',',
            'column_index' => 1,
            'active_filters_json' => json_encode([
                0 => ['CABANG B'],
            ]),
        ]);
        $request->setLaravelSession($session);

        $response = app(ImportFileController::class)->previewFilterOptions($request);

        expect($response->getStatusCode())->toBe(200);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        expect($payload['status'] ?? null)->toBe('success');
        expect($payload['values'] ?? null)->toBe(['UKER B 999']);
    } finally {
        File::delete($csvPath);
    }
});
