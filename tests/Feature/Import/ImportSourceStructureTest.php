<?php

use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Source structure tests require isolated SQLite.');
    }
    $this->structureTables = ['structure_report', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles', 'import_source_header_baselines'];
    foreach ($this->structureTables as $table) {
        Schema::dropIfExists($table);
    }
    (require base_path('database/migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
    (require base_path('database/migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();
    Schema::create('structure_report', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('posisi')->nullable();
        $table->string('no_rekening', 30)->nullable();
        $table->decimal('saldo', 20, 2)->nullable();
        $table->text('catatan')->nullable();
        $table->timestamps();
    });
    Schema::create('nama_report', function (Blueprint $table) {
        $table->id('id_report');
        $table->string('nama_report');
        $table->string('table_name');
        $table->boolean('active')->default(true);
    });
    Schema::create('import_jobs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('id_report');
        $table->string('status');
        $table->timestamps();
    });
    DB::table('nama_report')->insert(['id_report' => 96, 'nama_report' => 'Structure report', 'table_name' => 'structure_report', 'active' => 1]);
    $this->structureFiles = [];
    $this->structurePath = function (string $extension = 'csv'): string {
        $base = tempnam(sys_get_temp_dir(), 'structure_');
        $path = $base.'.'.$extension;
        rename($base, $path);
        $this->structureFiles[] = $path;

        return $path;
    };
    $this->structureAnalyze = fn (string $path, array $options = []) => app(SchemaDriftAnalyzer::class)->analyze('structure_report', $path, $options);
});

afterEach(function () {
    foreach ($this->structureFiles ?? [] as $path) {
        @unlink($path);
    }
    foreach ($this->structureTables ?? [] as $table) {
        Schema::dropIfExists($table);
    }
});

it('scans the active worksheet rather than a cover sheet and profiles a late populated amount', function () {
    $path = ($this->structurePath)('xlsx');
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Cover')->setCellValue('A1', 'Cover only');
    $sheet = $book->createSheet()->setTitle('Data');
    $sheet->fromArray(['POSISI', 'NO_REKENING', 'SALDO', 'ACCRUED_INTEREST'], null, 'A1');
    for ($row = 2; $row <= 701; $row++) {
        $sheet->fromArray(['18/11/2026', '001234567890', '1250.25', ''], null, 'A'.$row);
    }
    $sheet->setCellValue('D652', '1,748,241.36');
    $book->setActiveSheetIndex(1);
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $report = ($this->structureAnalyze)($path);
    $amount = collect($report['new_columns'])->firstWhere('header', 'ACCRUED_INTEREST');

    expect($report['source']['structural_scan']['complete'])->toBeTrue()
        ->and($report['source']['structural_scan']['total_rows'])->toBe(700)
        ->and($report['source']['structural_scan']['column_non_empty'][3])->toBe(1)
        ->and($amount['kind'])->toBe('decimal')
        ->and($amount['proposed_type'])->toStartWith('DECIMAL(')
        ->and($report['has_pending_decision'])->toBeTrue();
});

it('treats a quoted multiline field as one valid record without shifting later values', function () {
    $path = ($this->structurePath)();
    $handle = fopen($path, 'wb');
    fputcsv($handle, ['POSISI', 'NO_REKENING', 'SALDO', 'CATATAN'], ',', '"', '');
    fputcsv($handle, ['18/11/2026', '001234567890', '1250.25', "First line, with comma\nSecond line with \"quote\""], ',', '"', '');
    fputcsv($handle, ['18/11/2026', '001234567891', '9000.25', 'Following record'], ',', '"', '');
    fclose($handle);

    $report = ($this->structureAnalyze)($path, ['record' => false, 'remember' => false]);

    expect($report['source']['structural_scan']['total_rows'])->toBe(2)
        ->and($report['source']['structural_scan']['misaligned_rows'])->toBe(0)
        ->and($report['source']['structural_scan']['column_non_empty'][3])->toBe(2)
        ->and($report['has_blocking_issue'])->toBeFalse()
        ->and($report['has_pending_decision'])->toBeFalse();
});

it('invalidates cached scans and source approvals when bytes change at the same path size and timestamp', function () {
    $path = ($this->structurePath)();
    $original = "POSISI,NO_REKENING,SALDO\n18/11/2026,001234567890,\"1,234.50\"\n";
    file_put_contents($path, $original);
    $timestamp = filemtime($path);
    $size = filesize($path);
    $initial = ($this->structureAnalyze)($path);
    expect($initial['has_pending_decision'])->toBeFalse();

    $changed = str_replace('1,234.50', '1.234,50', $original);
    file_put_contents($path, $changed);
    touch($path, $timestamp);
    clearstatcache(true, $path);
    $review = ($this->structureAnalyze)($path, ['remember' => false]);
    $decisions = collect($review['review_issues'])->where('decision', 'pending')->map(fn ($issue) => ['key' => $issue['key'], 'action' => 'continue'])->values()->all();
    app(SchemaDriftRegistry::class)->accept($review['event']['id'], 'tester', [], [], $decisions, $review['source_fingerprint']);
    $approved = app(SchemaDriftImportGuard::class)->assertReady('structure_report', $path);
    expect($approved['has_pending_decision'])->toBeFalse();

    file_put_contents($path, str_replace('18/11/2026', '19/11/2026', $changed));
    touch($path, $timestamp);
    clearstatcache(true, $path);
    $latest = ($this->structureAnalyze)($path, ['remember' => false]);

    expect(filesize($path))->toBe($size)
        ->and(filemtime($path))->toBe($timestamp)
        ->and($review['source_fingerprint'])->not->toBe($initial['source_fingerprint'])
        ->and($latest['source_fingerprint'])->not->toBe($review['source_fingerprint'])
        ->and($latest['has_pending_decision'])->toBeTrue()
        ->and(collect($latest['review_issues'])->firstWhere('code', 'format_changed')['decision'])->toBe('pending');
});

it('blocks an invalid date after 600 English month dates even when a later row is valid again', function () {
    $path = ($this->structurePath)();
    $handle = fopen($path, 'wb');
    fputcsv($handle, ['POSISI', 'NO_REKENING', 'SALDO'], ',', '"', '');
    for ($row = 0; $row < 700; $row++) {
        fputcsv($handle, [$row === 650 ? 'BAD' : 'November 18, 2026', '001234567890', '1250.25'], ',', '"', '');
    }
    fclose($handle);

    $report = ($this->structureAnalyze)($path);

    expect($report['source']['structural_scan']['total_rows'])->toBe(700)
        ->and($report['has_pending_decision'])->toBeTrue()
        ->and($report['has_blocking_issue'])->toBeTrue()
        ->and(collect($report['review_issues'])->firstWhere('code', 'unparsed_dates')['blocking'])->toBeTrue();
});
