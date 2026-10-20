<?php

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SchemaEvolutionPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
    }

    foreach (['demo_ssa', 'cognos_ph', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles'] as $table) {
        Schema::dropIfExists($table);
    }

    (require base_path('database/migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();

    Schema::create('nama_report', function (Blueprint $table) {
        $table->id('id_report');
        $table->string('nama_report');
        $table->string('table_name');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
    Schema::create('import_jobs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('id_report');
        $table->string('status');
        $table->timestamps();
    });
    Schema::create('cognos_ph', function (Blueprint $table) {
        $table->id();
        $table->date('periode')->nullable();
    });
    Schema::create('demo_ssa', function (Blueprint $table) {
        $table->id();
        $table->date('posisi')->nullable();
        $table->decimal('saldo', 20, 2)->nullable();
        $table->date('tgl_update')->nullable();
        $table->decimal('nilai_tambahan', 20, 2)->nullable();
    });
    DB::table('nama_report')->insert([
        ['id_report' => 1, 'nama_report' => 'Cognos PH', 'table_name' => 'cognos_ph', 'active' => 1],
        ['id_report' => 2, 'nama_report' => 'Demo SSA', 'table_name' => 'demo_ssa', 'active' => 1],
    ]);
});

afterEach(function () {
    foreach (['demo_ssa', 'cognos_ph', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles'] as $table) {
        Schema::dropIfExists($table);
    }
});

function pendingDriftEvent(string $table): int
{
    return (int) DB::table('import_schema_drift_events')->insertGetId([
        'table_name' => $table,
        'report_label' => $table,
        'fingerprint' => hash('sha256', $table . microtime()),
        'kind' => 'new_columns',
        'status' => 'pending',
        'summary' => 'uji',
        'payload' => json_encode(['new_columns' => [[
            'header' => 'KOLOM_BARU', 'proposed_column' => 'kolom_baru', 'proposed_type' => 'VARCHAR(50)', 'decision' => 'pending',
        ]]]),
        'occurrences' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('classifies fixed-layout pipelines as monitor and the generic importer as evolve', function () {
    $policy = app(SchemaEvolutionPolicy::class);

    expect($policy->modeFor('cognos_ph'))->toBe('monitor')
        ->and($policy->modeFor('daily_loan_dinamis'))->toBe('monitor')
        ->and($policy->modeFor('lw325_ph'))->toBe('evolve')
        ->and($policy->modeFor('ssa_simpanan'))->toBe('evolve')
        ->and($policy->modeFor('SSA_PINJAMAN'))->toBe('evolve');
});

it('refuses to add columns a fixed-layout import would never fill but lets them be acknowledged', function () {
    $registry = app(SchemaDriftRegistry::class);
    $id = pendingDriftEvent('cognos_ph');

    expect(fn () => $registry->accept($id, 'tester'))->toThrow(RuntimeException::class, 'layout import kolom tetap')
        ->and(Schema::hasColumn('cognos_ph', 'kolom_baru'))->toBeFalse()
        ->and($registry->find($id)->status)->toBe('pending')
        ->and($registry->notifications()['items'][0]['evolution_mode'])->toBe('monitor');

    expect($registry->reject($id, 'tester')->status)->toBe('rejected');
});

it('parses evolved columns with the full parsers even when polars pre-normalized the fixed layout', function () {
    DB::table('import_schema_drift_events')->insert([
        'table_name' => 'demo_ssa', 'fingerprint' => 'applied-1', 'kind' => 'new_columns', 'status' => 'applied',
        'applied_columns' => json_encode([['name' => 'tgl_update', 'type' => 'DATE'], ['name' => 'nilai_tambahan', 'type' => 'DECIMAL(20,2)']]),
        'occurrences' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('import_column_format_profiles')->insert([
        'table_name' => 'demo_ssa', 'column_name' => 'tgl_update', 'kind' => 'date', 'signature' => 'date|numeric|mdy|time12',
        'date_order' => 'mdy', 'date_order_basis' => 'evidence', 'has_time' => 1, 'observations' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $controller = app(ImportExcelController::class);
    $method = new ReflectionMethod($controller, 'buildImportContext');
    $context = $method->invoke($controller, 'demo_ssa', ['POSISI', 'SALDO', 'TGL_UPDATE', 'NILAI_TAMBAHAN'], [], [
        'source_backend' => 'polars',
        'source_pre_normalized' => true,
    ]);
    $rules = collect($context['header_rules'])->keyBy('header_name');

    expect($rules['TGL_UPDATE']['is_date'])->toBeTrue()
        ->and($rules['TGL_UPDATE']['date_order'])->toBe('mdy')
        ->and($rules['TGL_UPDATE']['source_pre_normalized'])->toBeFalse()
        ->and($rules['NILAI_TAMBAHAN']['is_decimal'])->toBeTrue()
        ->and($rules['NILAI_TAMBAHAN']['source_pre_normalized'])->toBeFalse()
        ->and($rules['POSISI']['source_pre_normalized'])->toBeTrue()
        ->and($rules['POSISI']['is_date'])->toBeTrue();
});

it('reads staged polars headers so carried columns reach LOAD DATA and falls back safely', function () {
    $controller = app(ImportExcelController::class);
    $method = new ReflectionMethod($controller, 'readStagedCsvHeaders');
    $base = ['month_day_year_of_posisi', 'nama_cabang', 'saldo'];
    $carried = tempnam(sys_get_temp_dir(), 'stage_');
    $foreign = tempnam(sys_get_temp_dir(), 'stage_');
    file_put_contents($carried, "month_day_year_of_posisi,nama_cabang,saldo,jumlah_nasabah_baru\n2026-10-16,KC Madiun,10,1\n");
    file_put_contents($foreign, "tanggal,cabang,nilai\n");

    try {
        expect($method->invoke($controller, $carried, ',', $base))->toBe([...$base, 'jumlah_nasabah_baru'])
            ->and($method->invoke($controller, $foreign, ',', $base))->toBe($base)
            ->and($method->invoke($controller, $carried . '.missing', ',', $base))->toBe($base);
    } finally {
        @unlink($carried);
        @unlink($foreign);
    }
});

it('builds a LOAD DATA plan that fills an accepted column from a staged polars csv', function () {
    DB::table('import_schema_drift_events')->insert([
        'table_name' => 'demo_ssa', 'fingerprint' => 'applied-2', 'kind' => 'new_columns', 'status' => 'applied',
        'applied_columns' => json_encode([['name' => 'tgl_update', 'type' => 'DATE']]),
        'occurrences' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'plan_') . '.csv';
    file_put_contents($path, "posisi,saldo,tgl_update\n2026-10-16,1000.00,10/18/2026 12:00:00 AM\n");

    try {
        $controller = app(ImportExcelController::class);
        $method = new ReflectionMethod($controller, 'buildDirectGenericCsvLoadPlan');
        $plan = $method->invoke($controller, 'demo_ssa', $path, ['posisi', 'saldo', 'tgl_update'], [
            'source_backend' => 'polars',
            'source_pre_normalized' => true,
        ]);
        $clauses = collect($plan['set_clauses'] ?? []);
        $updateClause = (string) $clauses->first(fn (string $clause) => str_contains($clause, '`tgl_update`'));
        $posisiClause = (string) $clauses->first(fn (string $clause) => str_contains($clause, '`posisi`'));

        expect($updateClause)->toContain('DATE_ADD(CASE')
            ->and($updateClause)->not->toContain('AS DATE)')
            ->and($posisiClause)->toContain('AS DATE');
    } finally {
        @unlink($path);
    }
});
