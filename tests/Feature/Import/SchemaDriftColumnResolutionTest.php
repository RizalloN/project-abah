<?php

use App\Services\Import\SchemaDrift\SchemaDriftDetector;
use App\Support\Import\ImportHeaderAliases;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
    }

    foreach (['demo_loan', 'import_schema_drift_events', 'import_column_format_profiles', 'import_source_header_baselines'] as $table) {
        Schema::dropIfExists($table);
    }
    (require base_path('database/migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();

    // Same column order as daily_loan_dinamis: the generic balance column
    // comes before the two Textbox targets.
    Schema::create('demo_loan', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('periode')->nullable();
        $table->decimal('baki_debet1', 20, 2)->nullable();
        $table->decimal('total_kewajiban', 20, 2)->nullable();
        $table->decimal('os_idr', 20, 2)->nullable();
    });
});

afterEach(function () {
    foreach (['demo_loan', 'import_schema_drift_events', 'import_column_format_profiles', 'import_source_header_baselines'] as $table) {
        Schema::dropIfExists($table);
    }
});

function resolutionReport(array $headers, array $row): array
{
    return app(SchemaDriftDetector::class)->detect('demo_loan', $headers, [$row], [
        'baseline_established' => false,
        'managed_columns' => ['uniqueid_namareport'],
    ]);
}

function stateOf(array $report, string $header): array
{
    foreach ($report['columns'] as $column) {
        if ($column['header'] === $header) {
            return [$column['db_column'], $column['state']];
        }
    }

    return [null, null];
}

it('maps Textbox20 and Textbox21 to their own columns instead of a duplicate of baki_debet1', function () {
    $report = resolutionReport(
        ['PERIODE', 'BAKI_DEBET1', 'Textbox20', 'Textbox21'],
        ['20-10-2026', '43,366,039.00', '40,608,944.00', '43,366,039.00']
    );

    expect(stateOf($report, 'BAKI_DEBET1'))->toBe(['baki_debet1', 'matched'])
        ->and(stateOf($report, 'Textbox20'))->toBe(['total_kewajiban', 'matched'])
        ->and(stateOf($report, 'Textbox21'))->toBe(['os_idr', 'matched'])
        ->and(collect($report['columns'])->where('state', 'duplicate'))->toBeEmpty()
        ->and($report['status'])->not->toBe(SchemaDriftDetector::STATUS_DRIFT);
});

it('never lets a fuzzy alias claim a column that another header already fills exactly', function () {
    // "SALDO" is only a family alias of the balance columns; BAKI_DEBET1,
    // Textbox20 and Textbox21 already claim every one of them.
    $report = resolutionReport(
        ['BAKI_DEBET1', 'Textbox20', 'Textbox21', 'SALDO'],
        ['1.00', '2.00', '3.00', '4.00']
    );

    expect(collect($report['columns'])->where('state', 'duplicate'))->toBeEmpty()
        ->and(stateOf($report, 'SALDO')[1])->not->toBe('duplicate')
        ->and(stateOf($report, 'BAKI_DEBET1'))->toBe(['baki_debet1', 'matched']);
});

it('still flags a header that genuinely appears twice in the source', function () {
    $report = resolutionReport(['BAKI_DEBET1', 'BAKI_DEBET1'], ['1.00', '2.00']);

    expect(collect($report['columns'])->pluck('state')->all())->toBe(['matched', 'duplicate']);
});

it('blocks repeated new headers before they can create ambiguous mappings', function () {
    $report = resolutionReport(['BAKI_DEBET1', 'CATATAN_BARU', 'CATATAN_BARU'], ['1.00', 'satu', 'dua']);

    expect(collect($report['columns'])->pluck('state')->all())->toBe(['matched', 'new', 'duplicate'])
        ->and($report['has_blocking_issue'])->toBeTrue()
        ->and($report['new_columns'])->toHaveCount(1)
        ->and($report['review_issues'][0]['code'])->toBe('duplicate_source');
});

it('blocks schema proposals when the Daily Loan core header is missing', function () {
    $report = app(SchemaDriftDetector::class)->detect('daily_loan_dinamis', ['PERIODE', 'CABANG1', 'FLAG_BARU'], [
        ['2026-11-22', 'KC Madiun', 'Y'],
    ], [
        'column_metadata' => [
            'periode' => ['base_type' => 'date'],
            'cabang1' => ['base_type' => 'varchar'],
            'baki_debet1' => ['base_type' => 'decimal'],
            'nomor_rekening1' => ['base_type' => 'varchar'],
        ],
    ]);

    expect($report['has_blocking_issue'])->toBeTrue()
        ->and($report['new_columns'])->toBe([])
        ->and(array_column($report['review_issues'], 'key'))->toContain('required_header:baki_debet1', 'required_header:nomor_rekening1');
});

it('shares the import aliases so the preview and the import map the same way', function () {
    expect(ImportHeaderAliases::importColumnName('Textbox20'))->toBe('total_kewajiban')
        ->and(ImportHeaderAliases::importColumnName('Textbox21'))->toBe('os_idr')
        ->and(ImportHeaderAliases::importColumnName('BAKI_DEBET1'))->toBe('baki_debet1')
        ->and(ImportHeaderAliases::candidates('Textbox21'))->toContain('os_idr');
});
