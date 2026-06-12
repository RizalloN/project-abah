<?php

use App\Http\Controllers\Import\ImportFileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
    }

    Schema::dropIfExists('jumlah_merchant_qris_detail');
    Schema::dropIfExists('jumlah_merchant_detail');
    Schema::dropIfExists('sv_merchant');
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

    Schema::create('jumlah_merchant_detail', function (Blueprint $table) {
        $table->string('uniqueid_namareport', 255)->primary();
        $table->string('NAMA_KANCA', 150)->nullable();
        $table->string('NAMA_UKER', 180)->nullable();
        $table->date('POSISI')->nullable();
        $table->timestamps();
    });

    Schema::create('sv_merchant', function (Blueprint $table) {
        $table->string('uniqueid_namareport', 255)->primary();
        $table->string('TAHUN', 10)->nullable();
        $table->string('PERIODE', 20)->nullable();
        $table->string('POSISI', 50)->nullable();
        $table->string('KODE_KANWIL', 50)->nullable();
        $table->string('NAMA_KANWIL', 100)->nullable();
        $table->string('KODE_KCI', 50)->nullable();
        $table->string('NAMA_KCI', 100)->nullable();
        $table->string('KODE_BRANCH', 50)->nullable();
        $table->string('NAMA_BRANCH', 150)->nullable();
        $table->string('JENIS', 50)->nullable();
        $table->string('SEGMENTASI_JENIS', 100)->nullable();
        $table->string('SV_MERCHANT', 50)->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('sv_merchant');
    Schema::dropIfExists('jumlah_merchant_detail');
    Schema::dropIfExists('jumlah_merchant_qris_detail');
    Schema::dropIfExists('nama_report');
});

it('limits merchant detail preview filters and finds area 6 branches beyond the large file sample', function () {
    $user = User::factory()->make([
        'role' => 'admin',
    ]);

    DB::table('nama_report')->insert([
        'id_report' => 101,
        'nama_report' => 'Jumlah Merchant Detail',
        'table_name' => 'jumlah_merchant_detail',
        'active' => 1,
        'import_controller' => 'ImportFileController',
        'requires_manual_periode' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $csvDirectory = storage_path('framework/testing/import-preview');
    File::ensureDirectoryExists($csvDirectory);
    $csvPath = $csvDirectory . DIRECTORY_SEPARATOR . 'merchant-detail-large-filter-preview.csv';
    $payload = str_repeat('X', 1100);
    $rows = ['TAHUN|NAMA_KANCA|NAMA_UKER|MERCHANT_PAYLOAD'];

    for ($i = 1; $i <= 5000; $i++) {
        $rows[] = "2026|KC Banyuwangi|UKER BANYUWANGI|{$payload}";
    }

    foreach (['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'] as $branch) {
        $rows[] = "2026|{$branch}|{$branch}|{$payload}";
    }

    File::put($csvPath, implode(PHP_EOL, $rows) . PHP_EOL);

    try {
        expect(File::size($csvPath))->toBeGreaterThan(5 * 1024 * 1024);

        $this->be($user);
        $session = app('session.store');
        $session->start();
        $session->put('active_id_report', 101);

        $request = Request::create('/import/preview/direct', 'GET', [
            'file_path' => $csvPath,
            'delimiter' => '|',
        ]);
        $request->setLaravelSession($session);

        $response = app(ImportFileController::class)->preview($request);
        $data = $response->getData();

        expect(array_keys($data['formattedUniqueValues']))->toBe([1, 2]);
        expect($data['filterableColumnIndices'])->toBe([1, 2]);
        expect($data['initialArea6Selections']['1'] ?? [])->toBe([
            'KC Madiun',
            'KC Magetan',
            'KC Ngawi',
            'KC Ponorogo',
        ]);
        expect($data['formattedUniqueValues'])->not->toHaveKey(3);
    } finally {
        File::delete($csvPath);
    }
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

it('maps sv merchant preview display filters to the correct source columns', function () {
    $user = User::factory()->make([
        'role' => 'admin',
    ]);

    DB::table('nama_report')->insert([
        'id_report' => 100,
        'nama_report' => 'SV Merchant',
        'table_name' => 'sv_merchant',
        'active' => 1,
        'import_controller' => 'ImportFileController',
        'requires_manual_periode' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $csvDirectory = storage_path('framework/testing/import-preview');
    File::ensureDirectoryExists($csvDirectory);
    $csvPath = $csvDirectory . DIRECTORY_SEPARATOR . 'sv-merchant-display-map.csv';

    $rows = [
        'TAHUN|PERIODE|POSISI|KODE KANWIL|NAMA KANWIL|KODE KCI|NAMA KCI|KODE BRANCH|NAMA BRANCH|JENIS|SEGMENTASI JENIS|SV_MERCHANT',
        '2026|2026-04|2026-04-25|R|KANWIL MALANG|120|KC MADIUN|1111|UNIT MADIUN A|AKUMULASI|Ritel|100.00',
        '2026|2026-04|2026-04-25|R|KANWIL MALANG|120|KC MADIUN|2222|UNIT MADIUN B|AKUMULASI|Ritel|200.00',
        '2026|2026-04|2026-04-25|R|KANWIL MALANG|110|KC TULUNGAGUNG|3333|UNIT TULUNGAGUNG|AKUMULASI|Ritel|300.00',
    ];

    File::put($csvPath, implode(PHP_EOL, $rows) . PHP_EOL);

    try {
        $this->be($user);
        $session = app('session.store');
        $session->start();
        $session->put('active_id_report', 100);

        $request = Request::create('/import/preview/filter-options', 'GET', [
            'file_path' => $csvPath,
            'delimiter' => '|',
            'column_index' => 1,
            'display_filter_map_json' => json_encode([
                0 => 6,
                1 => 8,
            ]),
            'active_filters_json' => json_encode([
                0 => ['KC MADIUN'],
            ]),
        ]);
        $request->setLaravelSession($session);

        $response = app(ImportFileController::class)->previewFilterOptions($request);

        expect($response->getStatusCode())->toBe(200);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        expect($payload['status'] ?? null)->toBe('success');
        expect($payload['values'] ?? null)->toBe(['UNIT MADIUN A', 'UNIT MADIUN B']);
    } finally {
        File::delete($csvPath);
    }
});
