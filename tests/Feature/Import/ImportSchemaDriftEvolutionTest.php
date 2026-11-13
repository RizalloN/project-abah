<?php

use App\Services\Import\SchemaDrift\ColumnFormatMemory;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftDetector;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SchemaEvolutionService;
use App\Services\Import\SchemaIntrospectionService;
use App\Support\Import\ColumnValueProfiler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
    }

    $this->tables = ['demo_evolve', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles', 'import_source_header_baselines'];
    foreach ($this->tables as $table) {
        Schema::dropIfExists($table);
    }

    (require base_path('database/migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
    (require base_path('database/migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();

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
    Schema::create('demo_evolve', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('posisi')->nullable();
        $table->string('no_rekening', 30)->nullable();
        $table->string('nama_nasabah', 100)->nullable();
        $table->decimal('saldo', 20, 2)->nullable();
        $table->timestamps();
    });
    DB::table('nama_report')->insert(['id_report' => 91, 'nama_report' => 'Demo Evolusi', 'table_name' => 'demo_evolve', 'active' => 1]);

    $this->files = [];
    $this->write = function (array $headers, int $rows = 30, ?callable $row = null): string {
        $row ??= fn (int $i, string $header) => match ($header) {
            'POSISI' => '18/10/2026',
            'NO_REKENING', 'NOMOR_REK', 'NOREK_BARU' => '0' . (814601000000 + $i),
            'NAMA_NASABAH', 'NAMA_DEBITUR' => 'Nasabah ' . $i,
            'SALDO', 'SALDO_AKHIR' => number_format($i * 1500.25, 2),
            default => 'v' . $i,
        };
        $path = tempnam(sys_get_temp_dir(), 'evolve_') . '.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers, ',', '"', '');
        for ($i = 1; $i <= $rows; $i++) {
            fputcsv($handle, array_map(fn (string $header) => $row($i, $header), $headers), ',', '"', '');
        }
        fclose($handle);
        $this->files[] = $path;

        return $path;
    };
    $this->analyze = fn (array $headers, array $options = []) => app(SchemaDriftAnalyzer::class)->analyze('demo_evolve', ($this->write)($headers), $options);
});

afterEach(function () {
    foreach ($this->files ?? [] as $file) {
        @unlink($file);
    }
    foreach ($this->tables as $table) {
        Schema::dropIfExists($table);
    }
});

it('recognises a renamed header and maps it onto the existing column', function () {
    ($this->analyze)(['POSISI', 'NO_REKENING', 'NAMA_NASABAH', 'SALDO']);

    $renamed = ($this->analyze)(['POSISI', 'NOMOR_REK', 'NAMA_NASABAH', 'SALDO']);
    $column = collect($renamed['new_columns'])->firstWhere('header', 'NOMOR_REK');

    expect($renamed['status'])->toBe('drift')
        ->and($column['suggestion']['action'])->toBe('map')
        ->and($column['suggestion']['column'])->toBe('no_rekening')
        ->and($renamed['stats']['renamed'])->toBe(1)
        // A rename is not reported as a dropped column as well.
        ->and(collect($renamed['adaptations'])->pluck('code')->all())->not->toContain('column_dropped');

    $event = app(SchemaDriftRegistry::class)->accept($renamed['event']['id'], 'tester');

    expect($event->status)->toBe('applied')
        ->and(Schema::hasColumn('demo_evolve', 'nomor_rek'))->toBeFalse()
        ->and(app(SchemaDriftRegistry::class)->columnAliases('demo_evolve'))->toBe(['nomor_rek' => 'no_rekening']);

    $after = ($this->analyze)(['POSISI', 'NOMOR_REK', 'NAMA_NASABAH', 'SALDO']);
    expect($after['status'])->not->toBe('drift')
        ->and(collect($after['columns'])->firstWhere('header', 'NOMOR_REK')['db_column'])->toBe('no_rekening');
});

it('warns when a column the report always carried disappears', function () {
    ($this->analyze)(['POSISI', 'NO_REKENING', 'NAMA_NASABAH', 'SALDO']);

    $report = ($this->analyze)(['POSISI', 'NO_REKENING', 'NAMA_NASABAH']);
    $dropped = collect($report['adaptations'])->firstWhere('code', 'column_dropped');

    expect($report['status'])->toBe('drift')
        ->and($dropped['column'])->toBe('saldo')
        ->and($dropped['severity'])->toBe('warning')
        ->and($report['stats']['dropped'])->toBe(1)
        ->and($report['event']['kind'])->toBe('new_columns')
        ->and(collect($report['review_issues'])->pluck('code')->all())->toContain('column_dropped')
        ->and($report['event']['summary'])->toContain('perlu dikonfirmasi');
});

it('reopens a failed schema change after a fresh scan instead of stranding the preview', function () {
    $report = ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO', 'FLAG_BARU']);
    DB::table(SchemaDriftRegistry::TABLE)->where('id', $report['event']['id'])->update([
        'status' => SchemaDriftRegistry::STATUS_FAILED,
        'error' => 'Temporary schema lock',
    ]);
    $again = ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO', 'FLAG_BARU']);

    expect($again['event']['status'])->toBe(SchemaDriftRegistry::STATUS_PENDING)
        ->and($again['has_pending_decision'])->toBeTrue()
        ->and(DB::table(SchemaDriftRegistry::TABLE)->where('id', $again['event']['id'])->value('error'))->toBeNull();
});

it('requires review when a different layout variant omits previously stored fields', function () {
    ($this->analyze)(['POSISI', 'NO_REKENING', 'NAMA_NASABAH', 'SALDO']);

    $variant = ($this->analyze)(['POSISI', 'NAMA_NASABAH', 'KETERANGAN', 'CATATAN']);

    expect(collect($variant['adaptations'])->pluck('code')->all())->toContain('column_dropped')
        ->and($variant['has_pending_decision'])->toBeTrue();
});

it('fills a renamed new column through the generic importer mapping', function () {
    ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO']);
    $report = ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO', 'KODE_CABANG_BARU']);

    app(SchemaDriftRegistry::class)->accept($report['event']['id'], 'tester', [
        ['proposed_column' => 'kode_cabang_baru', 'action' => 'add', 'name' => 'kode_cabang', 'type' => 'VARCHAR(50)'],
    ]);

    $controller = app(\App\Http\Controllers\Import\ImportExcelController::class);
    $context = (new ReflectionMethod($controller, 'buildImportContext'))->invoke($controller, 'demo_evolve', ['POSISI', 'NO_REKENING', 'SALDO', 'KODE_CABANG_BARU']);
    $rule = $context['header_rules'][3];

    expect(Schema::hasColumn('demo_evolve', 'kode_cabang'))->toBeTrue()
        ->and(app(SchemaDriftRegistry::class)->columnAliases('demo_evolve'))->toBe(['kode_cabang_baru' => 'kode_cabang'])
        ->and(array_map('strtolower', $rule['db_candidates']))->toBe(['kode_cabang'])
        ->and($context['import_indexes'])->toContain(3);
});

it('opens a source-bound decision when another upload changes format', function () {
    ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO']);

    $first = ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO', 'FLAG_BARU']);
    $path = ($this->write)(['POSISI', 'NO_REKENING', 'SALDO', 'FLAG_BARU'], 30, fn (int $i, string $header) => match ($header) {
        'POSISI' => '10/18/2026 12:00:00 AM',
        'NO_REKENING' => '0' . (814601000000 + $i),
        'SALDO' => number_format($i * 1500.25, 2),
        default => 'v' . $i,
    });
    $second = app(SchemaDriftAnalyzer::class)->analyze('demo_evolve', $path);

    expect(collect($second['adaptations'])->pluck('code')->all())->toContain('format_changed')
        ->and($second['event']['id'])->not->toBe($first['event']['id'])
        ->and($second['source_fingerprint'])->not->toBe($first['source_fingerprint'])
        ->and(DB::table('import_schema_drift_events')->where('status', 'pending')->count())->toBe(2);
});

it('resolves a pending decision once the column exists by other means', function () {
    ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO']);
    $pending = ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO', 'CATATAN']);

    Schema::table('demo_evolve', fn (Blueprint $table) => $table->string('catatan', 100)->nullable());
    app(\App\Support\SchemaMetadataCache::class)->invalidate(DB::connection());

    $after = ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO', 'CATATAN']);

    expect($after['resolved_events'])->toBe([$pending['event']['id']])
        ->and(DB::table('import_schema_drift_events')->where('id', $pending['event']['id'])->value('status'))->toBe('resolved')
        ->and(app(SchemaDriftRegistry::class)->notifications()['pending'])->toBe(0);
});

function sourceReviewReport(string $source = 'source-one', bool $blocking = false, bool $newColumn = false): array
{
    $report = app(SchemaDriftDetector::class)->detect('demo_evolve', ['POSISI', 'NO_REKENING', 'SALDO'], [
        ['18/10/2026', '0814601000001', '1,500.25'],
    ]);
    $report['source_fingerprint'] = hash('sha256', $source);
    $report['review_issues'] = [[
        'key' => 'source:row-shape',
        'code' => 'row_shape_changed',
        'title' => 'Susunan baris berubah',
        'detail' => 'Perubahan harus ditinjau sebelum sumber ini diimpor.',
        'severity' => $blocking ? 'danger' : 'warning',
        'blocking' => $blocking,
        'decision' => 'pending',
    ]];
    if ($newColumn) {
        $report['new_columns'][] = [
            'header' => 'CATATAN_BARU', 'canonical' => 'catatan_baru',
            'proposed_column' => 'catatan_baru', 'proposed_type' => 'VARCHAR(50)',
            'decision' => 'pending',
        ];
    }
    $report['has_pending_decision'] = true;
    $report['fingerprint'] = hash('sha256', 'review-plan:' . (int) $newColumn);

    return $report;
}

it('requires an explicit source-bound continuation and never reuses it for another file', function () {
    $registry = app(SchemaDriftRegistry::class);
    $report = sourceReviewReport();
    $event = $registry->record($report, 'source.csv');

    expect(fn () => $registry->accept($event->id, 'tester'))->toThrow(RuntimeException::class, 'eksplisit')
        ->and($registry->find($event->id)->status)->toBe('pending');

    $accepted = $registry->accept($event->id, 'tester', [], [], [
        ['key' => 'source:row-shape', 'action' => 'continue'],
    ], $report['source_fingerprint']);

    expect($accepted->status)->toBe('applied')
        ->and($accepted->payload['review_issues'][0]['decision'])->toBe('approved')
        ->and($registry->approvedReviewKeys('demo_evolve', $report['source_fingerprint']))->toBe(['source:row-shape'])
        ->and($registry->approvedReviewKeys('demo_evolve', hash('sha256', 'other-file')))->toBe([]);

    $other = $registry->record(sourceReviewReport('other-file'), 'other.csv');
    expect($other->id)->not->toBe($event->id)
        ->and($other->status)->toBe('pending');
});

it('refuses continuation of a structurally invalid source even with an explicit selection', function () {
    $registry = app(SchemaDriftRegistry::class);
    $report = sourceReviewReport('bad-structure', true);
    $event = $registry->record($report);

    expect(fn () => $registry->accept($event->id, 'tester', [], [], [
        ['key' => 'source:row-shape', 'action' => 'continue'],
    ], $report['source_fingerprint']))->toThrow(RuntimeException::class, 'harus diperbaiki')
        ->and($registry->find($event->id)->status)->toBe('pending')
        ->and($registry->approvedReviewKeys('demo_evolve', $report['source_fingerprint']))->toBe([]);
});

it('rejects a stale source decision before adding any selected column', function () {
    $registry = app(SchemaDriftRegistry::class);
    $report = sourceReviewReport('current-file', false, true);
    $event = $registry->record($report);

    expect(fn () => $registry->accept($event->id, 'tester', [], [], [
        ['key' => 'source:row-shape', 'action' => 'continue'],
    ], hash('sha256', 'stale-file')))->toThrow(RuntimeException::class, 'sudah berubah')
        ->and(Schema::hasColumn('demo_evolve', 'catatan_baru'))->toBeFalse()
        ->and($registry->find($event->id)->status)->toBe('pending');
});

it('returns an actionable HTTP error for a stale preview approval', function () {
    $report = sourceReviewReport('current-preview', false, true);
    $event = app(SchemaDriftRegistry::class)->record($report);
    $admin = \App\Models\User::factory()->make(['id' => 1, 'role' => 'admin', 'name' => 'Admin Uji', 'pn' => '00000001']);

    $this->actingAs($admin)->withoutMiddleware()
        ->postJson(route('import.schema-drift.accept', ['event' => $event->id]), [
            'source_fingerprint' => hash('sha256', 'older-preview'),
            'reviews' => [['key' => 'source:row-shape', 'action' => 'continue']],
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');

    expect(Schema::hasColumn('demo_evolve', 'catatan_baru'))->toBeFalse();
});

it('refuses preview decisions when source bytes change after the scan', function (bool $includeFingerprint) {
    $path = ($this->write)(['POSISI', 'NO_REKENING', 'SALDO']);
    $report = sourceReviewReport('preview-file', false, true);
    $report['source_fingerprint'] = hash_file('sha256', $path);
    $registry = app(SchemaDriftRegistry::class);
    $event = $registry->record($report);
    $token = \App\Services\Import\SchemaDrift\SchemaDriftSourceToken::issue('demo_evolve', $path);
    file_put_contents($path, "\nchanged after preview", FILE_APPEND);
    $body = [
        'source' => $token,
        'reviews' => [['key' => 'source:row-shape', 'action' => 'continue']],
    ];
    if ($includeFingerprint) {
        $body['source_fingerprint'] = $report['source_fingerprint'];
    }
    $admin = \App\Models\User::factory()->make(['id' => 1, 'role' => 'admin', 'name' => 'Admin Uji', 'pn' => '00000001']);

    $this->actingAs($admin)->withoutMiddleware()
        ->postJson(route('import.schema-drift.accept', ['event' => $event->id]), $body)
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Isi file sumber sudah berubah sejak pemindaian. Pindai ulang preview sebelum menerapkan perubahan kolom.');

    expect(Schema::hasColumn('demo_evolve', 'catatan_baru'))->toBeFalse()
        ->and($registry->find($event->id)->status)->toBe('pending')
        ->and($registry->approvedReviewKeys('demo_evolve', $report['source_fingerprint']))->toBe([]);
})->with([true, false]);

it('applies a preview decision when its token and current source still match', function () {
    $path = ($this->write)(['POSISI', 'NO_REKENING', 'SALDO']);
    $report = sourceReviewReport('matching-preview', false, true);
    $report['source_fingerprint'] = hash_file('sha256', $path);
    $registry = app(SchemaDriftRegistry::class);
    $event = $registry->record($report);
    $admin = \App\Models\User::factory()->make(['id' => 1, 'role' => 'admin', 'name' => 'Admin Uji', 'pn' => '00000001']);

    $this->actingAs($admin)->withoutMiddleware()
        ->postJson(route('import.schema-drift.accept', ['event' => $event->id]), [
            'source' => \App\Services\Import\SchemaDrift\SchemaDriftSourceToken::issue('demo_evolve', $path),
            'source_fingerprint' => $report['source_fingerprint'],
            'reviews' => [['key' => 'source:row-shape', 'action' => 'continue']],
        ])
        ->assertOk()
        ->assertJsonPath('event.status', 'applied');

    expect(Schema::hasColumn('demo_evolve', 'catatan_baru'))->toBeTrue()
        ->and($registry->approvedReviewKeys('demo_evolve', $report['source_fingerprint']))->toBe(['source:row-shape']);
});

it('keeps source reviews open after the user skips new columns', function () {
    $registry = app(SchemaDriftRegistry::class);
    $report = sourceReviewReport('file-with-extra', false, true);
    $event = $registry->record($report);
    $after = $registry->reject($event->id, 'tester', $report['source_fingerprint']);

    expect($after->status)->toBe('pending')
        ->and($after->payload['new_columns'][0]['decision'])->toBe('rejected')
        ->and($after->payload['review_issues'][0]['decision'])->toBe('pending')
        ->and($registry->rejectedColumns('demo_evolve'))->toContain('catatan_baru')
        ->and($registry->approvedReviewKeys('demo_evolve', $report['source_fingerprint']))->toBe([])
        ->and(Schema::hasColumn('demo_evolve', 'catatan_baru'))->toBeFalse();

    $accepted = $registry->accept($event->id, 'tester', [], [], [
        ['key' => 'source:row-shape', 'action' => 'continue'],
    ], $report['source_fingerprint']);
    expect($accepted->status)->toBe('applied')
        ->and(Schema::hasColumn('demo_evolve', 'catatan_baru'))->toBeFalse()
        ->and($registry->rejectedColumns('demo_evolve'))->toContain('catatan_baru');
});

it('does not reconcile away an unapproved source review when every database column exists', function () {
    $registry = app(SchemaDriftRegistry::class);
    $report = sourceReviewReport();
    $event = $registry->record($report);

    expect($registry->reconcile($report))->toBe([])
        ->and($registry->find($event->id)->status)->toBe('pending')
        ->and(fn () => $registry->reject($event->id, 'tester'))->toThrow(RuntimeException::class, 'belum dikonfirmasi');
});

it('refuses mapping a second source header onto an already populated target', function () {
    $report = ($this->analyze)(['POSISI', 'NO_REKENING', 'SALDO', 'SALDO_LAIN']);
    $registry = app(SchemaDriftRegistry::class);

    expect(fn () => $registry->accept($report['event']['id'], 'tester', [[
        'proposed_column' => 'saldo_lain', 'action' => 'map', 'target' => 'saldo',
    ]]))->toThrow(RuntimeException::class, 'sudah diisi kolom sumber lain')
        ->and($registry->columnAliases('demo_evolve'))->toBe([])
        ->and($registry->find($report['event']['id'])->status)->toBe('pending');
});

it('refuses ambiguous or unsafe execution choices', function () {
    $evolution = app(SchemaEvolutionService::class);

    expect(fn () => $evolution->addColumns('demo_evolve', [['name' => 'saldo', 'type' => 'TEXT']]))->toThrow(RuntimeException::class, 'sudah ada')
        ->and(fn () => $evolution->validateMappings('demo_evolve', [['name' => 'tidak_ada']]))->toThrow(RuntimeException::class, 'tidak ada di tabel')
        ->and(fn () => $evolution->validateMappings('demo_evolve', [['name' => 'saldo'], ['name' => 'saldo']]))->toThrow(RuntimeException::class, 'kolom yang sama')
        ->and(fn () => $evolution->validateMappings('demo_evolve', [['name' => 'uniqueid_namareport']]))->toThrow(RuntimeException::class, 'dikelola sistem')
        ->and(fn () => $evolution->widenColumns('demo_evolve', [['name' => 'tidak_ada', 'type' => 'VARCHAR(100)']]))->toThrow(RuntimeException::class);

    DB::table('import_jobs')->insert(['id_report' => 91, 'status' => 'staging', 'updated_at' => now()]);
    expect(fn () => $evolution->addColumns('demo_evolve', [['name' => 'baru', 'type' => 'TEXT']]))->toThrow(RuntimeException::class, 'import aktif');

    // An import abandoned for hours no longer blocks schema changes.
    DB::table('import_jobs')->update(['updated_at' => now()->subHours(7)]);
    expect($evolution->addColumns('demo_evolve', [['name' => 'baru', 'type' => 'TEXT']])[0]['name'])->toBe('baru');
});

it('only ever widens a column', function (string $from, string $to, bool $allowed) {
    expect(app(SchemaEvolutionService::class)->isWidening($from, $to))->toBe($allowed);
})->with([
    'longer varchar' => ['varchar(50)', 'VARCHAR(100)', true],
    'varchar to text' => ['varchar(255)', 'TEXT', true],
    'shorter varchar' => ['varchar(100)', 'VARCHAR(50)', false],
    'more decimal precision' => ['decimal(20,2)', 'DECIMAL(24,2)', true],
    'more decimal scale keeping integer digits' => ['decimal(20,2)', 'DECIMAL(22,4)', true],
    'fewer integer digits' => ['decimal(20,2)', 'DECIMAL(20,4)', false],
    'int to bigint' => ['int(10) unsigned', 'BIGINT', true],
    'int to decimal' => ['int(11)', 'DECIMAL(20,2)', true],
    'bigint to int' => ['bigint(20)', 'INT', false],
    'text to number' => ['varchar(50)', 'DECIMAL(20,2)', false],
    'date to text' => ['date', 'TEXT', false],
]);

it('proposes safe widenings for overflowing values', function () {
    $schema = new class extends SchemaIntrospectionService {
        public function getColumnMetadata(string $tableName): array
        {
            return [
                'nama_nasabah' => $this->parseMysqlColumnTypeMetadata('varchar(10)') + ['name' => 'nama_nasabah'],
                'saldo' => $this->parseMysqlColumnTypeMetadata('decimal(8,2)') + ['name' => 'saldo'],
                'jumlah' => $this->parseMysqlColumnTypeMetadata('int(11)') + ['name' => 'jumlah'],
            ];
        }
    };
    $detector = new SchemaDriftDetector(new ColumnValueProfiler(), app(ColumnFormatMemory::class), $schema);
    $rows = array_map(fn (int $i) => ['Nasabah dengan nama panjang ' . $i, number_format(98765432.5 + $i, 2), (string) (99999999999 + $i)], range(1, 20));

    $report = $detector->detect('demo_widen', ['NAMA_NASABAH', 'SALDO', 'JUMLAH'], $rows, ['baseline_established' => true]);
    $adjustments = collect($report['adjustments'])->keyBy('column');

    expect($report['status'])->toBe('drift')
        ->and($report['has_pending_decision'])->toBeTrue()
        ->and($adjustments['nama_nasabah']['to_type'])->toBe('VARCHAR(50)')
        ->and($adjustments['saldo']['to_type'])->toBe('DECIMAL(20,2)')
        ->and($adjustments['jumlah']['to_type'])->toBe('BIGINT')
        ->and($report['headline'])->toContain('3 kolom perlu diperlebar');

    $rejected = $detector->detect('demo_widen', ['NAMA_NASABAH', 'SALDO', 'JUMLAH'], $rows, ['baseline_established' => true, 'rejected_columns' => ['adjust:nama_nasabah', 'adjust:saldo', 'adjust:jumlah']]);
    expect($rejected['has_pending_decision'])->toBeFalse();
});
