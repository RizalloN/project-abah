<?php

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportCognosRecoveryController;
use App\Http\Controllers\Import\ImportFileController;
use App\Http\Controllers\Import\Gi405RecDhImportExcelController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Http\Controllers\Import\ImportReportPhController;
use App\Http\Middleware\EnsureImportSourceReviewed;
use App\Http\Controllers\Input\BodBocController;
use App\Http\Controllers\Input\InputRekananController;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\DlyKapResegmentasiCsvImporter;
use App\Services\Import\L1133CsvImporter;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SchemaDriftSourceToken;
use App\Services\Import\SchemaDrift\ReportTemplateSource;
use App\Services\Import\Strategies\PerformancePisImportStrategy;
use App\View\Composers\SchemaDriftPreviewComposer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Schema import guard tests must run on isolated SQLite.');
    }
    $this->guardTables = ['guard_report', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles', 'import_source_header_baselines'];
    foreach ($this->guardTables as $table) {
        Schema::dropIfExists($table);
    }
    (require base_path('database/migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
    (require base_path('database/migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();
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
        $table->unsignedBigInteger('created_by')->nullable();
        $table->text('job_context')->nullable();
        $table->timestamps();
    });
    Schema::create('guard_report', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('posisi')->nullable();
        $table->string('no_rekening', 30)->nullable();
        $table->decimal('saldo', 20, 2)->nullable();
        $table->timestamps();
    });
    DB::table('nama_report')->insert(['id_report' => 95, 'nama_report' => 'Guard report', 'table_name' => 'guard_report', 'active' => 1]);
    DB::table('guard_report')->insert(['uniqueid_namareport' => 'existing', 'posisi' => '2026-11-18', 'no_rekening' => '00123', 'saldo' => 99]);
    Storage::fake('local');
    Bus::fake();
    $this->guardPath = 'excel_imports/guard-source.csv';
    Storage::disk('local')->put($this->guardPath, "POSISI,NO_REKENING,SALDO,FLAG_PAYROLL,INSTANSI\n18/11/2026,001234567890,1250.25,Y,\n");
});

afterEach(function () {
    foreach ($this->guardTables ?? [] as $table) {
        Schema::dropIfExists($table);
    }
});

it('blocks undecided columns on the server without changing target rows or the approved reference', function () {
    $guard = app(SchemaDriftImportGuard::class);

    expect(fn () => $guard->assertReady('guard_report', Storage::path($this->guardPath)))->toThrow(RuntimeException::class, 'belum diputuskan')
        ->and($guard->lastReport()['stats']['new'])->toBe(2)
        ->and(DB::table('guard_report')->count())->toBe(1)
        ->and((float) DB::table('guard_report')->value('saldo'))->toBe(99.0)
        ->and(DB::table('import_source_header_baselines')->count())->toBe(0)
        ->and(DB::table('import_column_format_profiles')->count())->toBe(0);
});

it('keeps every PIS field aligned through the source review, workbook stage, and insert', function () {
    $tableName = 'performance_pis_per_produk';
    $this->guardTables[] = $tableName;
    Schema::dropIfExists($tableName);
    DB::table('nama_report')->insert(['id_report' => 14, 'nama_report' => 'Performance PIS per Produk', 'table_name' => $tableName]);
    $core = PerformancePisImportStrategy::TARGET_COLUMNS;
    Schema::create($tableName, function (Blueprint $table) use ($core) {
        $table->string('uniqueid_namareport')->primary();
        foreach ($core as $column) {
            $table->text($column)->nullable();
        }
        $table->timestamps();
    });

    $headers = ['NO'];
    $values = ['1'];
    $expected = [];
    $exportAliases = array_flip(PerformancePisImportStrategy::HEADER_ALIASES);
    foreach (array_reverse(array_slice($core, 1)) as $index => $column) {
        $headers[] = strtoupper($exportAliases[$column] ?? $column);
        $values[] = $expected[$column] = match ($column) {
            'saldo_britama_kerjasama' => '1234.56',
            'tanggal_pembuatan_rekening' => '2026-11-01',
            default => '00'.($index + 10),
        };
    }
    for ($i = 1; $i <= 20; $i++) {
        $column = 'atribut_baru_'.$i;
        $value = match ($i) {
            1 => 'Tim\Area',
            2 => '\N',
            3 => 'Cabang, "Utama"',
            default => '00100'.$i,
        };
        array_splice($headers, $i, 0, [strtoupper($column)]);
        array_splice($values, $i, 0, [$value]);
        $expected[$column] = $value;
    }

    Storage::makeDirectory('performance_pis_imports');
    $path = Storage::path('performance_pis_imports/pis-drift.xlsx');
    $stagePath = Storage::path('performance_pis_imports/pis-stage.csv');
    $workbook = new Spreadsheet;
    $workbook->getSheet(0)->fromArray([['Posisi: 23/11/2026'], $headers, $values]);
    $workbook->createSheet()->fromArray([['TABEL_LAIN', 'JUMLAH'], ['salah', '1']]);
    $workbook->setActiveSheetIndex(1);
    (new Xlsx($workbook))->save($path);
    $workbook->disconnectWorksheets();

    $analyzer = app(SchemaDriftAnalyzer::class);
    $report = $analyzer->analyze($tableName, $path);
    expect($report['stats']['matched'])->toBe(21)
        ->and($report['stats']['new'])->toBe(20)
        ->and($report['source']['header_row'])->toBe(2)
        ->and($report['has_blocking_issue'])->toBeFalse()
        ->and(fn () => app(SchemaDriftImportGuard::class)->assertReady($tableName, $path))->toThrow(RuntimeException::class, 'belum diputuskan')
        ->and(DB::table($tableName)->count())->toBe(0);

    $decisions = array_map(static fn (array $column): array => ['proposed_column' => $column['proposed_column'], 'action' => 'add', 'type' => 'TEXT'], $report['new_columns']);
    app(SchemaDriftRegistry::class)->accept((int) $report['event']['id'], 'Test', $decisions, [], [], $report['source_fingerprint']);
    expect(app(SchemaDriftImportGuard::class)->assertReady($tableName, $path)['stats']['matched'])->toBe(41);

    $controller = app(ImportPerformancePisPerProdukController::class);
    $excel = (new ReflectionMethod($controller, 'buildExcelContext'))->invoke($controller, $path);
    $signature = new ReflectionMethod($controller, 'previewColumnSignature');
    $checkColumns = new ReflectionMethod($controller, 'assertPreviewColumnsCurrent');
    expect(fn () => $checkColumns->invoke($controller, $excel['headers'], $signature->invoke($controller, $core)))
        ->toThrow(RuntimeException::class, 'Daftar kolom Performance PIS berubah');
    $stage = (new ReflectionMethod($controller, 'stagePerformancePisExcelToCsv'))->invoke($controller, static function () {}, $path, $excel, $stagePath);
    $csv = (new ReflectionMethod($controller, 'buildCsvContext'))->invoke($controller, $stage['staged_csv_path'], '2026-11-23');
    $checkColumns->invoke($controller, $csv['headers'], $signature->invoke($controller, $excel['headers']));
    expect($csv['headers'])->toBe($excel['headers']);
    $filtered = (new ReflectionMethod($controller, 'createFilteredCsvStage'))->invoke($controller, $stage['staged_csv_path'], $csv, [], array_keys($csv['headers']), 1, static function () {});
    try {
        $result = (new ReflectionMethod($controller, 'insertStagedCsvInBatches'))->invoke($controller, $filtered['path'], $filtered['columns']);
        expect($result['total_success'])->toBe(1)
            ->and(array_intersect_key((array) DB::table($tableName)->first(), $expected))->toMatchArray($expected);
    } finally {
        unlink($filtered['path']);
    }
});

it('binds CSV and BRImo previews to the authorized file and parser', function (string $routeName, string $mapping) {
    $directory = storage_path('app/imports/schema-preview-'.bin2hex(random_bytes(6)));
    mkdir($directory, 0777, true);
    $path = $directory.DIRECTORY_SEPARATOR.'source.csv';
    file_put_contents($path, "\n\"POSISI;NO_REKENING;SALDO;SALDO_2\"\n\"2026-11-23;00123;99;125\"\n");
    try {
        session(['active_id_report' => 95, 'import_files' => [['path' => $path]]]);
        request()->setRouteResolver(fn () => app('router')->getRoutes()->getByName($routeName));
        $view = view('import.preview', ['filePath' => $path, 'schemaDriftDelimiter' => ';']);
        app(SchemaDriftPreviewComposer::class)->compose($view);
        $source = SchemaDriftSourceToken::read($view->getData()['schemaDriftSource']);
        expect($source['table'])->toBe('guard_report')
            ->and($source['path'])->toBe(realpath($path))
            ->and($source['options']['mapping'])->toBe($mapping);

        $report = app(SchemaDriftAnalyzer::class)->analyze($source['table'], $source['path'], $source['options']);
        expect($report['stats']['matched'])->toBe(3)
            ->and($report['source']['header_row'])->toBe(2)
            ->and(array_column($report['new_columns'], 'header'))->toBe(['SALDO_2'])
            ->and($report['has_pending_decision'])->toBeTrue()
            ->and($report['has_blocking_issue'])->toBeFalse();
    } finally {
        unlink($path);
        rmdir($directory);
    }
})->with([['import.preview.direct', 'csv'], ['import.brimo.preview', 'brimo'], ['import.brimo.preview.direct', 'brimo']]);

it('writes an approved CSV column whose source label contains punctuation', function () {
    Storage::put($this->guardPath, "POSISI,NO_REKENING,SALDO,FLAG-BARU\n2026-11-23,00123,99,ABC\n");
    $path = Storage::path($this->guardPath);
    $options = SchemaDriftImportGuard::delimitedOptions('csv', ',');
    $report = app(SchemaDriftAnalyzer::class)->analyze('guard_report', $path, $options);
    expect(array_column($report['new_columns'], 'proposed_column'))->toBe(['flag_baru']);
    app(SchemaDriftRegistry::class)->accept((int) $report['event']['id'], 'Test', [], [], [], $report['source_fingerprint']);
    expect(app(SchemaDriftImportGuard::class)->assertReady('guard_report', $path, $options)['stats']['matched'])->toBe(4);

    $controller = app(ImportFileController::class);
    $headers = ['POSISI', 'NO_REKENING', 'SALDO', 'FLAG-BARU'];
    $selected = array_keys($headers);
    $blueprint = (new ReflectionMethod($controller, 'buildColumnImportBlueprint'))->invoke($controller, $selected, $headers, 'guard_report');
    expect(array_column($blueprint, 'column'))->toBe(['posisi', 'no_rekening', 'saldo', 'flag_baru']);
    $row = (new ReflectionMethod($controller, 'mapRowForInsert'))->invoke($controller, ['2026-11-23', '00123', '99', 'ABC'], $selected, $headers, false, '_TEST', $blueprint);
    DB::table('guard_report')->insert($row);
    expect(DB::table('guard_report')->where('uniqueid_namareport', $row['uniqueid_namareport'])->value('flag_baru'))->toBe('ABC');
});

it('matches the two Cognos Recovery segment fields and blocks a truncated source row', function () {
    $tableName = 'cognos_recovery';
    $this->guardTables[] = $tableName;
    Schema::dropIfExists($tableName);
    $headers = explode(';', 'PERIODE;CIFNO;KETERANGAN;BC;SUB BC;KANWIL;RO FIX;REGION;CABANG;UNIT_KERJA;GL_ACCOUNT;PRODUK_CODE;SEGMEN_FPSL;REKENING;STATUS;STSDT_DT;SNAME;SEGMEN;SEGMEN_BISNIS;SEGMEN_BISNIS_2025;PRODUK;SEGMEN KUR;SEGMEN;SEGMEN 2;COMPLIANCE;RECOVERY;Recovery Klaim;Recovery Olsib;Total Recovery;Recovery Non Klaim');
    $targets = app(ImportCognosRecoveryController::class)->schemaDriftSourceTargets($headers);
    Schema::create($tableName, function (Blueprint $table) use ($targets) {
        foreach ($targets as $column) {
            $column === 'periode' ? $table->date($column)->nullable() : $table->text($column)->nullable();
        }
    });
    $row = ['202611', ...array_fill(0, 29, '1')];
    $row[17] = 'SMALL';
    $row[22] = 'PROGRAM';
    $path = 'cognos_recovery_imports/segments.txt';
    $csv = '"'.implode(';', $headers)."\"\n\"".implode(';', $row)."\"\n";
    Storage::put($path, $csv);
    $source = Storage::path($path);
    $guard = app(SchemaDriftImportGuard::class);
    $report = $guard->assertReady($tableName, $source);
    expect($report['stats']['matched'])->toBe(30)
        ->and($report['columns'][17]['db_column'])->toBe('segmen')
        ->and($report['columns'][22]['db_column'])->toBe('segmen_repeat');

    Storage::put($path, $csv.implode(';', array_slice($row, 0, 10))."\n");
    expect(fn () => $guard->assertReady($tableName, $source))->toThrow(RuntimeException::class, 'berisiko kehilangan data')
        ->and(array_column($guard->lastReport()['review_issues'], 'code'))->toBe(['row_width']);
});

it('keeps additional GI405 source fields visible after preparing the workbook preview', function () {
    $table = 'gi405_recovery';
    $this->guardTables[] = $table;
    Schema::dropIfExists($table);
    Schema::create($table, function (Blueprint $table) {
        $table->date('periode');
        $table->string('kode_uker');
        $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 20, 2);
    });
    $path = Storage::path('excel_imports/gi405-with-an-extra-column.xlsx');
    $book = new Spreadsheet;
    $book->getSheet(0)->fromArray([
        ['Ringkasan GI405'],
        ['Periode', 'KODE', 'KANCA BARU', 'Pendapatan Koreksi PPAP-dr Angsuran PH'],
        ['2026-11-23', '00123', 'Madiun', '1234.56'],
    ]);
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $controller = app(Gi405RecDhImportExcelController::class);
    $staged = (new ReflectionMethod($controller, 'stageGi405WorkbookSheetToCsv'))->invoke($controller, $path);
    $report = app(SchemaDriftAnalyzer::class)->analyze($table, $staged['absolute_path']);
    expect($report['stats']['matched'])->toBe(3)
        ->and(array_column($report['new_columns'], 'header'))->toBe(['KANCA BARU'])
        ->and($report['has_pending_decision'])->toBeTrue()
        ->and($report['evolution_mode'])->toBe('monitor');
});

it('reviews the real layout of structured reports and binds confirmations to the original source', function (string $tableName, array $columns, array $rows) {
    $this->guardTables[] = $tableName;
    Schema::dropIfExists($tableName);
    Schema::create($tableName, function (Blueprint $table) use ($columns) {
        foreach ($columns as $column) {
            $table->text($column)->nullable();
        }
    });
    $path = Storage::path('excel_imports/structured-report.csv');
    $file = fopen($path, 'wb');
    foreach ($rows as $row) {
        fputcsv($file, $row);
    }
    fclose($file);

    $report = app(SchemaDriftAnalyzer::class)->analyze($tableName, $path);
    expect($report['stats']['matched'])->toBe(count($columns))
        ->and($report['stats']['new'])->toBe(0)
        ->and($report['has_blocking_issue'])->toBeFalse()
        ->and($report['has_pending_decision'])->toBeTrue()
        ->and($report['review_issues'][0]['detail'])->toContain('KOLOM BARU')
        ->and($report['source_fingerprint'])->toBe(hash_file('sha256', $path));

    $guard = app(SchemaDriftImportGuard::class);
    expect(fn () => $guard->assertReady($tableName, $path))->toThrow(RuntimeException::class, 'belum diputuskan');
    $reviews = array_map(static fn (array $issue): array => ['key' => $issue['key'], 'action' => 'continue'], $report['review_issues']);
    app(SchemaDriftRegistry::class)->accept((int) $report['event']['id'], 'Test', [], [], $reviews, $report['source_fingerprint']);
    expect($guard->assertReady($tableName, $path)['has_pending_decision'])->toBeFalse();
})->with([
    [DlyKapResegmentasiCsvImporter::TABLE, DlyKapResegmentasiCsvImporter::NORMALIZED_HEADERS, [
        ['BTData', 'KANWIL', 'KODE_CABANG', 'KODE_UNIT'],
        ['23/11/2026', 'R', '45', '552'],
        ['SEGMEN2', 'textbox171', ...array_map(static fn (int $i): string => 'METRIK_'.$i, range(1, 14)), 'KOLOM BARU'],
        ['10000', 'KREDIT MIKRO', ...array_fill(0, 14, '1'), 'Nilai sumber'],
    ]],
    [L1133CsvImporter::TABLE, L1133CsvImporter::NORMALIZED_HEADERS, [
        ['L1133'],
        ['45--MADIUN', '23/11/2026'],
        ['kode_br', 'mbdesc', 'jenis', 'textbox3', 'textbox11', 'textbox16', 'deb_npl', 'textbox23', 'deb_dpk', 'textbox25', 'KOLOM BARU'],
        ['45--MADIUN', '552--UNIT', 'MIKRO', ...array_fill(0, 7, '1'), 'Nilai sumber'],
    ]],
]);

it('reviews custom template previews and blocks a save while a source field is undecided', function (string $table, string $controllerClass, string $routeName, string $viewName) {
    $fields = ReportTemplateSource::columns($table);
    $headers = array_reverse($fields);
    $values = array_map(static fn (string $field): string => 'Sumber '.$field, $headers);
    $headers[] = 'KOLOM TAMBAHAN';
    $values[] = 'Ikut dideteksi';
    $path = Storage::path('excel_imports/template.xlsx');
    $book = new Spreadsheet;
    $book->getSheet(0)->fromArray([$headers, $values]);
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $controller = app($controllerClass);
    $upload = Request::create('/upload', 'POST', ['periode' => '2026-11-23'], [], ['file' => new UploadedFile($path, 'template.xlsx', test: true)]);
    $controller->importTemplate($upload);
    request()->setRouteResolver(fn () => app('router')->getRoutes()->getByName($routeName));
    $view = $controller->previewImport();
    expect($view->name())->toBe($viewName)
        ->and($view->getData()['previewRows'][0][$fields[0]])->toBe('Sumber '.$fields[0]);
    app(SchemaDriftPreviewComposer::class)->compose($view);
    $source = SchemaDriftSourceToken::read($view->getData()['schemaDriftSource']);
    expect($source['table'])->toBe($table);
    $report = app(SchemaDriftAnalyzer::class)->analyze($table, $source['path'], $source['options']);
    expect($report['stats']['matched'])->toBe(8)
        ->and(array_column($report['new_columns'], 'header'))->toBe(['KOLOM TAMBAHAN']);
    $save = Request::create('/save', 'POST', ['periode' => '2026-11-23', 'rows_payload' => json_encode($view->getData()['previewRows'])]);
    $response = $controller->store($save);
    expect($response->getSession()->get('error'))->toContain('belum diputuskan');
})->with([
    ['input_rekanan', InputRekananController::class, 'input.import-preview', 'input.import-preview'],
    ['bod_boc', BodBocController::class, 'bod-boc.import-preview', 'input.bod-boc-import-preview'],
]);

it('allows skipping populated and empty unknown fields only after explicit user rejection', function () {
    $guard = app(SchemaDriftImportGuard::class);
    try {
        $guard->assertReady('guard_report', Storage::path($this->guardPath));
    } catch (RuntimeException) {
    }
    $report = $guard->lastReport();
    app(SchemaDriftRegistry::class)->reject($report['event']['id'], 'tester');

    $ready = $guard->assertReady('guard_report', Storage::path($this->guardPath));

    expect($ready['has_pending_decision'])->toBeFalse()
        ->and($ready['stats']['rejected'])->toBe(2)
        ->and(DB::table('guard_report')->count())->toBe(1);
});

it('refuses a malformed source without truncating its extra values', function () {
    Storage::disk('local')->put($this->guardPath, "POSISI,NO_REKENING,SALDO\n18/11/2026,001234567890,1250.25,EXTRA\n");
    $guard = app(SchemaDriftImportGuard::class);

    expect(fn () => $guard->assertReady('guard_report', Storage::path($this->guardPath)))->toThrow(RuntimeException::class, 'berisiko kehilangan data')
        ->and($guard->lastReport()['has_blocking_issue'])->toBeTrue()
        ->and(DB::table('guard_report')->count())->toBe(1);
});

it('rejects an enqueue request that bypasses the preview decision before creating a job', function () {
    $response = $this->withoutMiddleware()->withSession([
        'active_id_report' => 95,
        'excel_path' => $this->guardPath,
    ])->postJson(route('import.excel.init'), [
        'path' => $this->guardPath,
        'active_filters_json' => '{}',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('schema_drift.has_pending_decision', true)
        ->assertJsonPath('schema_drift.stats.new', 2);
    expect(DB::table('import_jobs')->count())->toBe(0)
        ->and(DB::table('guard_report')->count())->toBe(1)
        ->and((float) DB::table('guard_report')->value('saldo'))->toBe(99.0);
    Bus::assertNothingDispatched();
});

it('rechecks pending decisions in a worker before loading or replacing report data', function () {
    $state = [
        'params' => ['table_name' => 'guard_report', 'file_path' => $this->guardPath],
        'headers' => ['POSISI', 'NO_REKENING', 'SALDO', 'FLAG_PAYROLL', 'INSTANSI'],
    ];

    expect(fn () => app(ImportExcelController::class)->executeQueuedImport($state))->toThrow(RuntimeException::class, 'belum diputuskan')
        ->and(DB::table('guard_report')->count())->toBe(1)
        ->and((float) DB::table('guard_report')->value('saldo'))->toBe(99.0);
});

it('keeps reviewing the original upload when a staged file omits pending source columns', function () {
    $staged = 'excel_imports/normalized.csv';
    Storage::disk('local')->put($staged, "POSISI,NO_REKENING,SALDO\n18/11/2026,001234567890,1250.25\n");
    $state = [
        'params' => ['table_name' => 'guard_report', 'file_path' => $staged, 'schema_drift_source_path' => $this->guardPath],
        'headers' => ['POSISI', 'NO_REKENING', 'SALDO'],
    ];

    expect(fn () => app(ImportExcelController::class)->executeQueuedImport($state))->toThrow(RuntimeException::class, 'belum diputuskan')
        ->and(DB::table('guard_report')->count())->toBe(1);
});

it('blocks a chunk request with pending columns before inserting any row', function () {
    $jobId = DB::table('import_jobs')->insertGetId(['id_report' => 95, 'status' => 'queued']);
    app(ExcelImportJobService::class)->putImportJobState($jobId, [
        'params' => ['job_id' => $jobId, 'table_name' => 'guard_report', 'file_path' => $this->guardPath, 'header_index' => 0],
        'headers' => ['POSISI', 'NO_REKENING', 'SALDO', 'FLAG_PAYROLL', 'INSTANSI'],
    ]);
    $response = $this->withoutMiddleware()->withSession([
        'active_id_report' => 95,
        'excel_path' => $this->guardPath,
        'excel_import_params' => ['job_id' => $jobId],
    ])->postJson(route('import.excel.chunk'), [
        'job_id' => $jobId,
        'header_index' => 0,
        'table_name' => 'guard_report',
        'start_row' => 1,
        'chunk_size' => 100,
        'file_path' => $this->guardPath,
    ]);

    $response->assertStatus(422)->assertJsonPath('schema_drift.has_pending_decision', true);
    expect(DB::table('guard_report')->count())->toBe(1)
        ->and((float) DB::table('guard_report')->value('saldo'))->toBe(99.0);
});

it('blocks a dedicated report before invoking its controller and permits it after an explicit decision', function () {
    Schema::dropIfExists('lw325_ph');
    $this->guardTables[] = 'lw325_ph';
    Schema::create('lw325_ph', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('periode')->nullable();
        $table->string('acctno')->nullable();
        $table->decimal('pokok', 20, 2)->nullable();
        $table->timestamps();
    });
    $path = 'report_ph_imports/guard-source.csv';
    Storage::disk('local')->put($path, "PERIODE,ACCTNO,POKOK,FLAG_BARU\n18/11/2026,001234567890,1250.25,Y\n");
    session(['report_ph_file' => $path]);
    $request = Request::create('/import/report-ph/init', 'POST', ['file_path' => $path]);
    $route = (new Route('POST', '/import/report-ph/init', fn () => null))->name('import.reportph.init');
    $request->setRouteResolver(fn () => $route);
    $called = false;
    $next = function () use (&$called) {
        $called = true;

        return response()->json(['controller_called' => true]);
    };
    $middleware = app(EnsureImportSourceReviewed::class);
    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(422)
        ->and($called)->toBeFalse()
        ->and($response->getData(true)['schema_drift']['table'])->toBe('lw325_ph')
        ->and(DB::table('import_jobs')->count())->toBe(0);
    app(SchemaDriftRegistry::class)->reject($response->getData(true)['schema_drift']['event']['id'], 'tester');
    $ready = $middleware->handle($request, $next);
    expect($ready->getStatusCode())->toBe(200)->and($called)->toBeTrue();
});

it('authorizes the dedicated source path before the detector can inspect client supplied files', function () {
    session(['report_ph_file' => 'report_ph_imports/expected.csv']);
    Storage::disk('local')->put('report_ph_imports/expected.csv', 'EXPECTED');
    Storage::disk('local')->put('report_ph_imports/other.csv', 'OTHER');
    $request = Request::create('/import/report-ph/init', 'POST', ['file_path' => 'report_ph_imports/other.csv']);
    $request->setRouteResolver(fn () => (new Route('POST', '/import/report-ph/init', fn () => null))->name('import.reportph.init'));
    $guard = Mockery::mock(SchemaDriftImportGuard::class);
    $guard->shouldNotReceive('assertReady');

    expect(fn () => (new EnsureImportSourceReviewed($guard))->handle($request, fn () => response('unexpected')))
        ->toThrow(HttpException::class, 'File import tidak sesuai');
});

it('carries accepted PH columns through preview and row mapping without dropping clocks or decimal places', function () {
    Schema::dropIfExists('lw325_ph');
    $this->guardTables[] = 'lw325_ph';
    Schema::create('lw325_ph', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('periode')->nullable();
        $table->string('acctno')->nullable();
        $table->decimal('pokok', 20, 2)->nullable();
        $table->timestamps();
    });
    DB::table('nama_report')->insert(['id_report' => 15, 'nama_report' => 'Nominatif PH', 'table_name' => 'lw325_ph']);
    $path = 'report_ph_imports/approved-columns.csv';
    $data = ['11/22/2026 12:00:00 AM', '001234567890', '1250.25', '2026-11-22 13:27:05', '0.1234'];
    Storage::put($path, "PERIODE,ACCTNO,POKOK,TGL_TAMBAHAN,NOMINAL_DETAIL\n".implode(',', $data)."\n");
    $source = Storage::path($path);
    $report = app(SchemaDriftAnalyzer::class)->analyze('lw325_ph', $source);
    expect(array_column($report['new_columns'], 'preserve_as_text'))->toBe([true, true]);

    $registry = app(SchemaDriftRegistry::class);
    expect(fn () => $registry->accept($report['event']['id'], 'tester', [[
        'proposed_column' => 'tgl_tambahan', 'action' => 'add', 'type' => 'DATE',
    ]]))->toThrow(RuntimeException::class, 'Pilih tipe teks');
    $registry->accept($report['event']['id'], 'tester', [[
        'proposed_column' => 'tgl_tambahan', 'action' => 'add', 'name' => 'waktu_sumber',
    ]]);
    $ready = app(SchemaDriftImportGuard::class)->assertReady('lw325_ph', $source);
    expect($ready['has_pending_decision'])->toBeFalse();

    $controller = app(ImportReportPhController::class);
    $context = (new ReflectionMethod($controller, 'buildCsvContext'))->invoke($controller, $source);
    $row = (new ReflectionMethod($controller, 'mapCsvRow'))->invoke($controller, $context, $data);
    $mapped = array_combine($context['headers'], $row);
    expect($mapped['acctno'])->toBe('001234567890')
        ->and($mapped['waktu_sumber'])->toBe('2026-11-22 13:27:05')
        ->and($mapped['nominal_detail'])->toBe('0.1234');

    $columns = array_intersect_key($mapped, array_fill_keys(Schema::getColumnListing('lw325_ph'), true));
    DB::table('lw325_ph')->insert(['uniqueid_namareport' => 'approved'] + $columns);
    expect(DB::table('lw325_ph')->where('uniqueid_namareport', 'approved')->value('waktu_sumber'))->toBe($data[3]);
});

it('uses the active report only for the two supported CASA BRILink tables', function () {
    session(['active_id_report' => 95, 'casa_brilink_file' => 'casa_brilink_imports/source.csv']);
    $request = Request::create('/import/casa-brilink/init', 'POST');
    $request->setRouteResolver(fn () => (new Route('POST', '/import/casa-brilink/init', fn () => null))->name('import.casabrilink.init'));
    $guard = Mockery::mock(SchemaDriftImportGuard::class);
    $guard->shouldNotReceive('assertReady');

    expect(fn () => (new EnsureImportSourceReviewed($guard))->handle($request, fn () => response('unexpected')))
        ->toThrow(HttpException::class, 'Report aktif tidak sesuai');
});

it('attaches source review to dedicated import actions while leaving uploads and status polling available', function () {
    $routes = app('router')->getRoutes();
    foreach (['import.cras.init', 'import.reportph.init', 'import.reportph.process', 'import.cognos-ph.init', 'import.cognos-ph.process', 'import.cognos-recovery.init', 'import.cognos-recovery.process', 'import.performancepis.init', 'import.performancepis.process', 'import.casabrilink.init', 'import.casabrilink.process'] as $name) {
        expect($routes->getByName($name)->gatherMiddleware())->toContain(EnsureImportSourceReviewed::class);
    }
    foreach (['import.cras.upload-chunk.init', 'import.reportph.upload', 'import.cognos-ph.stream', 'import.cras.stream'] as $name) {
        expect($routes->getByName($name)->gatherMiddleware())->not->toContain(EnsureImportSourceReviewed::class);
    }
});
