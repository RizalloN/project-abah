<?php

use App\Services\Import\SchemaDrift\ImportSourceSampler;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftDetector;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SourceHeaderBaseline;
use App\Support\Import\ColumnValueProfiler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
    }

    $this->tables = ['demo_report', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles', 'import_source_header_baselines'];
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
    Schema::create('demo_report', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('posisi')->nullable();
        $table->string('kode_uker', 20)->nullable();
        $table->decimal('saldo_posisi', 20, 2)->nullable();
        $table->timestamps();
    });
    DB::table('nama_report')->insert(['id_report' => 88, 'nama_report' => 'Demo Report', 'table_name' => 'demo_report', 'active' => 1]);

    $this->files = [];
    $this->write = function (array $headers, array $rows): string {
        $path = tempnam(sys_get_temp_dir(), 'baseline_') . '.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);
        $this->files[] = $path;

        return $path;
    };
    $this->rows = fn (int $extra) => array_map(
        fn (int $i) => array_merge(['18/10/2026', 'U' . $i, number_format($i * 1250.5, 2)], array_fill(0, $extra, 'v' . $i)),
        range(1, 30)
    );
});

afterEach(function () {
    foreach ($this->files ?? [] as $file) {
        @unlink($file);
    }
    foreach ($this->tables as $table) {
        Schema::dropIfExists($table);
    }
});

it('treats columns of the first document as the baseline instead of announcing them', function () {
    $first = ($this->write)(['POSISI', 'KODE_UKER', 'SALDO_POSISI', 'KETERANGAN_LAMA'], ($this->rows)(1));

    $report = app(SchemaDriftAnalyzer::class)->analyze('demo_report', $first);

    expect($report['baseline_established'])->toBeFalse()
        ->and($report['status'])->not->toBe('drift')
        ->and($report['stats']['new'])->toBe(0)
        ->and($report['stats']['unmapped'])->toBe(1)
        ->and($report['unmapped_columns'][0]['header'])->toBe('KETERANGAN_LAMA')
        ->and($report['event'])->toBeNull()
        ->and($report['headline'])->toContain('acuan struktur')
        ->and(app(SourceHeaderBaseline::class)->knownKeys('demo_report'))->toEqualCanonicalizing(['posisi', 'kode_uker', 'saldo_posisi', 'keterangan_lama']);

    // The same layout again: still quiet, the ignored column stays informational.
    $again = app(SchemaDriftAnalyzer::class)->analyze('demo_report', $first);
    expect($again['baseline_established'])->toBeTrue()
        ->and($again['stats']['unmapped'])->toBe(1)
        ->and($again['headline'])->toContain('tidak disimpan sejak awal')
        ->and(app(SchemaDriftRegistry::class)->notifications()['count'])->toBe(0);
});

it('asks only about a column that appears after the baseline', function () {
    app(SchemaDriftAnalyzer::class)->analyze('demo_report', ($this->write)(['POSISI', 'KODE_UKER', 'SALDO_POSISI', 'KETERANGAN_LAMA'], ($this->rows)(1)));

    $changed = app(SchemaDriftAnalyzer::class)->analyze(
        'demo_report',
        ($this->write)(['POSISI', 'KODE_UKER', 'SALDO_POSISI', 'KETERANGAN_LAMA', 'FLAG_BARU'], ($this->rows)(2))
    );

    expect($changed['status'])->toBe('drift')
        ->and(collect($changed['new_columns'])->pluck('header')->all())->toBe(['FLAG_BARU'])
        ->and(collect($changed['unmapped_columns'])->pluck('header')->all())->toBe(['KETERANGAN_LAMA'])
        ->and($changed['event']['status'])->toBe('pending')
        // A pending column stays outside the baseline until it is decided.
        ->and(app(SourceHeaderBaseline::class)->knownKeys('demo_report'))->not->toContain('flag_baru');

    app(SchemaDriftRegistry::class)->reject($changed['event']['id'], 'tester');
    $afterReject = app(SchemaDriftAnalyzer::class)->analyze(
        'demo_report',
        ($this->write)(['POSISI', 'KODE_UKER', 'SALDO_POSISI', 'KETERANGAN_LAMA', 'FLAG_BARU'], ($this->rows)(2))
    );

    expect($afterReject['has_pending_decision'])->toBeFalse()
        ->and(app(SourceHeaderBaseline::class)->knownKeys('demo_report'))->toContain('flag_baru');
});

it('never writes a baseline during a dry run', function () {
    app(SchemaDriftAnalyzer::class)->analyze(
        'demo_report',
        ($this->write)(['POSISI', 'KODE_UKER', 'SALDO_POSISI', 'X'], ($this->rows)(1)),
        ['record' => false, 'remember' => false]
    );

    expect(DB::table('import_source_header_baselines')->count())->toBe(0)
        ->and(DB::table('import_column_format_profiles')->count())->toBe(0)
        ->and(DB::table('import_schema_drift_events')->count())->toBe(0);
});

it('does not read eight digit balances under a date-ish header as dates', function () {
    $report = app(SchemaDriftAnalyzer::class)->analyze(
        'demo_report',
        ($this->write)(['POSISI', 'KODE_UKER', 'SALDO_POSISI'], array_map(fn (int $i) => ['18/10/2026', 'U' . $i, (string) (15032019 + $i)], range(1, 30))),
        ['record' => false, 'remember' => false]
    );

    expect($report['status'])->toBe('match')
        ->and(collect($report['columns'])->firstWhere('db_column', 'saldo_posisi')['kind'])->toBe(ColumnValueProfiler::KIND_INTEGER);
});

it('distinguishes meaningful format changes from sample variance', function (string $from, string $to, bool $meaningful) {
    expect(SchemaDriftDetector::isMeaningfulFormatChange($from, $to))->toBe($meaningful);
})->with([
    'day/month order flipped' => ['date|numeric|dmy|notime', 'date|numeric|mdy|time12', true],
    'clock appeared' => ['date|numeric|dmy|notime', 'date|numeric|dmy|time24', true],
    'iso replaced slashes' => ['date|numeric|dmy|notime', 'date|iso|ymd|notime', true],
    'mixed sample keeps a family' => ['date|numeric|dmy|notime', 'date|numeric+iso|dmy|notime', false],
    'decimal separator swapped' => ['number|us', 'number|id', true],
    'no thousands in this sample' => ['number|us', 'number|plain', false],
    'code with or without leading zero' => ['identifier', 'number|plain', false],
    'text vs code' => ['text', 'identifier', false],
    'date became text' => ['date|numeric|dmy|notime', 'text', true],
    'empty sample' => ['number|us', 'empty', false],
]);

it('finds the header row of positional exports by structure', function () {
    $path = ($this->write)(
        ['textbox10', 'textbox11', 'textbox12', 'JUMLAHTRANSAKSI', 'NOMINAL'],
        [['KANWIL MALANG', 'KC Madiun', 'UNIT A', '12', '1,250.00'], ['KANWIL MALANG', 'KC Ngawi', 'UNIT B', '7', '980.50']]
    );

    $sample = app(ImportSourceSampler::class)->sample($path, fn (array $headers) => array_map('strtolower', $headers), ['periode', 'wilayah', 'cabang']);

    expect($sample['header_index'])->toBe(0)
        ->and($sample['headers'])->toBe(['textbox10', 'textbox11', 'textbox12', 'JUMLAHTRANSAKSI', 'NOMINAL'])
        ->and($sample['rows'])->toHaveCount(2);
});
