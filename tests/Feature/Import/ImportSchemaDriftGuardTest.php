<?php

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Middleware\EnsureImportSourceReviewed;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
