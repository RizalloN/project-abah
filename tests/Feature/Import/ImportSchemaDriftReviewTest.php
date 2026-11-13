<?php

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SourceHeaderBaseline;
use App\Services\Import\Strategies\DailyLoanImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Schema review tests must run on isolated SQLite, never MySQL.');
    }

    $this->reviewTables = ['review_report', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles', 'import_source_header_baselines'];
    foreach ($this->reviewTables as $table) {
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
    Schema::create('review_report', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('posisi')->nullable();
        $table->string('no_rekening', 30)->nullable();
        $table->decimal('saldo', 20, 2)->nullable();
        $table->timestamps();
    });
    DB::table('nama_report')->insert(['id_report' => 93, 'nama_report' => 'Review report', 'table_name' => 'review_report', 'active' => 1]);

    $this->reviewFiles = [];
    $this->writeReviewSource = function (array $headers, array $rows): string {
        $path = tempnam(sys_get_temp_dir(), 'review_');
        $handle = fopen($path, 'wb');
        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);
        $this->reviewFiles[] = $path;

        return $path;
    };
    $this->reviewHeaders = ['POSISI', 'NO_REKENING', 'SALDO'];
    $this->reviewRow = ['18/11/2026', '001234567890', '1,250.25'];
    $this->reviewAnalyze = fn (string $path) => app(SchemaDriftAnalyzer::class)->analyze('review_report', $path);
    $this->insertReviewedRow = function (array $headers, array $row): array {
        $controller = app(ImportExcelController::class);
        $context = (new ReflectionMethod($controller, 'buildImportContext'))->invoke($controller, 'review_report', $headers);
        $mapped = (new ReflectionMethod($controller, 'mapExcelRowForInsert'))->invoke($controller, $row, $headers, $context, '2026-11-12 10:00:00');
        DB::table('review_report')->insert($mapped);

        return $mapped;
    };
});

afterEach(function () {
    foreach ($this->reviewFiles ?? [] as $file) {
        @unlink($file);
    }
    foreach ($this->reviewTables ?? [] as $table) {
        Schema::dropIfExists($table);
    }
});

it('requires a decision for every unknown first-file header including an empty column', function () {
    $path = ($this->writeReviewSource)(
        [...$this->reviewHeaders, 'FLAG_PAYROLL', 'INSTANSI'],
        [[...$this->reviewRow, 'Y', ''], [...$this->reviewRow, 'N', '']]
    );

    $report = ($this->reviewAnalyze)($path);

    expect($report['status'])->toBe('drift')
        ->and($report['has_pending_decision'])->toBeTrue()
        ->and(collect($report['new_columns'])->pluck('header')->all())->toEqualCanonicalizing(['FLAG_PAYROLL', 'INSTANSI'])
        ->and($report['stats']['unmapped'])->toBe(0)
        ->and($report['event']['status'])->toBe('pending')
        ->and(app(SourceHeaderBaseline::class)->knownKeys('review_report'))->not->toContain('flag_payroll', 'instansi');
});

it('does not treat an old baseline with no target as user permission to drop data', function () {
    DB::table('import_source_header_baselines')->insert([
        'table_name' => 'review_report',
        'header_key' => 'flag_payroll',
        'header_label' => 'FLAG_PAYROLL',
        'db_column' => null,
        'occurrences' => 1,
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $path = ($this->writeReviewSource)([...$this->reviewHeaders, 'FLAG_PAYROLL'], [[...$this->reviewRow, 'Y']]);

    $report = ($this->reviewAnalyze)($path);

    expect($report['has_pending_decision'])->toBeTrue()
        ->and(collect($report['new_columns'])->firstWhere('header', 'FLAG_PAYROLL')['decision'])->toBe('pending')
        ->and($report['headline'])->not->toContain('tidak disimpan sejak awal');
});

it('only skips unknown headers after an explicit rejection and still asks about later additions', function () {
    $path = ($this->writeReviewSource)([...$this->reviewHeaders, 'FLAG_PAYROLL'], [[...$this->reviewRow, 'Y']]);
    $report = ($this->reviewAnalyze)($path);
    app(SchemaDriftRegistry::class)->reject($report['event']['id'], 'tester');

    $again = ($this->reviewAnalyze)($path);
    expect($again['has_pending_decision'])->toBeFalse()
        ->and(collect($again['columns'])->firstWhere('header', 'FLAG_PAYROLL')['state'])->toBe('rejected')
        ->and(Schema::hasColumn('review_report', 'flag_payroll'))->toBeFalse();

    $later = ($this->reviewAnalyze)(($this->writeReviewSource)([...$this->reviewHeaders, 'FLAG_PAYROLL', 'INSTANSI'], [[...$this->reviewRow, 'Y', 'BRI']]));
    expect($later['has_pending_decision'])->toBeTrue()
        ->and(collect($later['new_columns'])->where('decision', 'pending')->pluck('header')->all())->toBe(['INSTANSI']);
});

it('stores an approved new header in the user chosen database column through the actual row mapper', function () {
    $headers = [...$this->reviewHeaders, 'FLAG_PAYROLL'];
    $path = ($this->writeReviewSource)($headers, [[...$this->reviewRow, 'Y']]);
    $report = ($this->reviewAnalyze)($path);

    app(SchemaDriftRegistry::class)->accept($report['event']['id'], 'tester', [
        ['proposed_column' => 'flag_payroll', 'action' => 'add', 'name' => 'status_payroll', 'type' => 'VARCHAR(50)'],
    ]);
    ($this->insertReviewedRow)($headers, [...$this->reviewRow, 'Y']);

    expect(Schema::hasColumn('review_report', 'status_payroll'))->toBeTrue()
        ->and(Schema::hasColumn('review_report', 'flag_payroll'))->toBeFalse()
        ->and(DB::table('review_report')->value('status_payroll'))->toBe('Y')
        ->and(DB::table('review_report')->value('no_rekening'))->toBe('001234567890');
    $after = ($this->reviewAnalyze)($path);
    expect($after['has_pending_decision'])->toBeFalse()
        ->and(collect($after['columns'])->firstWhere('header', 'FLAG_PAYROLL')['db_column'])->toBe('status_payroll');
});

it('stores an explicitly mapped replacement header in the existing column', function () {
    ($this->reviewAnalyze)(($this->writeReviewSource)($this->reviewHeaders, [$this->reviewRow]));
    $headers = ['POSISI', 'NOREK_BARU', 'SALDO'];
    $path = ($this->writeReviewSource)($headers, [$this->reviewRow]);
    $report = ($this->reviewAnalyze)($path);
    $column = collect($report['new_columns'])->firstWhere('header', 'NOREK_BARU');

    app(SchemaDriftRegistry::class)->accept($report['event']['id'], 'tester', [
        ['proposed_column' => $column['proposed_column'], 'action' => 'map', 'target' => 'no_rekening'],
    ]);
    ($this->insertReviewedRow)($headers, $this->reviewRow);

    expect(DB::table('review_report')->value('no_rekening'))->toBe('001234567890')
        ->and(Schema::hasColumn('review_report', 'norek_baru'))->toBeFalse()
        ->and(($this->reviewAnalyze)($path)['has_pending_decision'])->toBeFalse();
});

it('keeps analysis read-only for the target table until the user accepts a decision', function () {
    $columns = Schema::getColumnListing('review_report');
    $report = ($this->reviewAnalyze)(($this->writeReviewSource)([...$this->reviewHeaders, 'FLAG_PAYROLL'], [[...$this->reviewRow, 'Y']]));

    expect($report['has_pending_decision'])->toBeTrue()
        ->and(Schema::getColumnListing('review_report'))->toBe($columns)
        ->and(DB::table('review_report')->count())->toBe(0);
});

it('detects an over-wide record after the preview sample and blocks silent truncation', function () {
    $rows = array_fill(0, 700, $this->reviewRow);
    $rows[650][] = 'unannounced extra value';
    $report = ($this->reviewAnalyze)(($this->writeReviewSource)($this->reviewHeaders, $rows));

    expect($report['source']['structural_scan']['complete'])->toBeTrue()
        ->and($report['source']['structural_scan']['misaligned_rows'])->toBe(1)
        ->and($report['has_blocking_issue'])->toBeTrue()
        ->and($report['has_pending_decision'])->toBeTrue()
        ->and(collect($report['review_issues'])->firstWhere('code', 'row_width')['blocking'])->toBeTrue();
});

it('detects a short record after the preview sample without confusing an explicit empty last field', function () {
    $rows = array_fill(0, 700, $this->reviewRow);
    $rows[648][2] = '';
    array_pop($rows[650]);
    $report = ($this->reviewAnalyze)(($this->writeReviewSource)($this->reviewHeaders, $rows));

    expect($report['source']['structural_scan']['misaligned_rows'])->toBe(1)
        ->and($report['has_blocking_issue'])->toBeTrue()
        ->and(collect($report['review_issues'])->firstWhere('code', 'row_width')['blocking'])->toBeTrue();
});

it('blocks two source columns that would overwrite the same destination', function () {
    $report = ($this->reviewAnalyze)(($this->writeReviewSource)([...$this->reviewHeaders, 'SALDO'], [[...$this->reviewRow, '900.50']]));

    expect(collect($report['columns'])->where('state', 'duplicate')->count())->toBe(1)
        ->and($report['has_blocking_issue'])->toBeTrue()
        ->and($report['has_pending_decision'])->toBeTrue();
});

it('profiles a populated amount after row 600 instead of declaring the source column empty', function () {
    $rows = array_fill(0, 700, [...$this->reviewRow, '']);
    $rows[650][3] = '1,748,241.36';
    $report = ($this->reviewAnalyze)(($this->writeReviewSource)([...$this->reviewHeaders, 'ACCRUED_INTEREST'], $rows));
    $column = collect($report['new_columns'])->firstWhere('header', 'ACCRUED_INTEREST');

    expect($report['source']['structural_scan']['complete'])->toBeTrue()
        ->and($report['source']['structural_scan']['total_rows'])->toBe(700)
        ->and($report['source']['structural_scan']['column_non_empty'][3])->toBe(1)
        ->and($column['kind'])->toBe('decimal')
        ->and($column['proposed_type'])->toStartWith('DECIMAL(')
        ->and($report['has_pending_decision'])->toBeTrue();
});

it('requires review when a numeric column becomes text after the preview sample', function () {
    $rows = array_fill(0, 700, $this->reviewRow);
    $rows[650][2] = 'BUKAN ANGKA';
    $report = ($this->reviewAnalyze)(($this->writeReviewSource)($this->reviewHeaders, $rows));

    expect($report['has_pending_decision'])->toBeTrue()
        ->and(collect($report['adaptations'])->pluck('code')->all())->toContain('unparsed_numbers')
        ->and(collect($report['review_issues'])->pluck('code')->all())->toContain('unparsed_numbers');
});

it('carries approved Daily Loan additions and mappings into the direct load plan and fallback row mapper', function () {
    Schema::dropIfExists('daily_loan_dinamis');
    $this->reviewTables[] = 'daily_loan_dinamis';
    // SQLite's Blueprint grammar drops DECIMAL precision. Keep the declared
    // scale in PRAGMA metadata so this exercises the real six-decimal guard.
    DB::statement('CREATE TABLE daily_loan_dinamis (
        uniqueid_namareport VARCHAR(255) PRIMARY KEY,
        periode DATE,
        nomor_rekening1 VARCHAR(30),
        baki_debet1 DECIMAL(20,2),
        rate DECIMAL(20,6),
        accrued_interest DECIMAL(20,2),
        kode_kanwil1 VARCHAR(2),
        created_at DATETIME,
        updated_at DATETIME
    )');
    DB::table('nama_report')->insert(['id_report' => 8, 'nama_report' => 'Daily Loan Dinamis', 'table_name' => 'daily_loan_dinamis', 'active' => 1]);
    $headers = ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1', 'RATE', 'BUNGA_BERJALAN', 'FLAG_PAYROLL', 'KODE_KANWIL1'];
    $row = ['18/11/2026', '001234567890', '606,383,016.00', '0.082500', '1,748,241.36', 'Y', 'R'];
    $path = ($this->writeReviewSource)($headers, [$row]);
    $report = app(SchemaDriftAnalyzer::class)->analyze('daily_loan_dinamis', $path);
    $columns = collect($report['new_columns'])->keyBy('header');

    app(SchemaDriftRegistry::class)->accept($report['event']['id'], 'tester', [
        ['proposed_column' => $columns['BUNGA_BERJALAN']['proposed_column'], 'action' => 'map', 'target' => 'accrued_interest'],
        ['proposed_column' => $columns['FLAG_PAYROLL']['proposed_column'], 'action' => 'add', 'name' => 'payroll_status', 'type' => 'VARCHAR(50)'],
    ]);
    $controller = new class extends ImportExcelController
    {
        protected function resolveExcelTableName(): string
        {
            return 'daily_loan_dinamis';
        }
    };
    $options = [
        'prepared_source' => ['path' => $path, 'headers' => $headers, 'backend' => 'raw', 'written_rows' => 1, 'period_hints' => ['2026-11-18']],
        'delimiter' => ',',
        'replace_periods' => ['2026-11-18'],
    ];
    $plan = (new ReflectionMethod($controller, 'buildDirectDailyLoanCsvLoadPlan'))->invoke($controller, $path, $headers, $options);
    $clauses = implode("\n", $plan['set_clauses']);

    expect($plan['source_headers'])->toBe($headers)
        ->and($plan['field_variables'])->toHaveCount(count($headers))
        ->and($clauses)->toContain('`accrued_interest` =', '@csv_col_4', '`payroll_status` =', '@csv_col_5');

    $context = (new ReflectionMethod($controller, 'buildImportContext'))->invoke($controller, 'daily_loan_dinamis', $headers);
    $mapped = (new ReflectionMethod($controller, 'mapExcelRowForInsert'))->invoke($controller, $row, $headers, $context, '2026-11-12 10:00:00');
    DB::table('daily_loan_dinamis')->insert($mapped);
    $stored = DB::table('daily_loan_dinamis')->first();

    expect($stored->nomor_rekening1)->toBe('001234567890')
        ->and((float) $stored->accrued_interest)->toBe(1748241.36)
        ->and($stored->payroll_status)->toBe('Y')
        ->and((float) $stored->rate)->toBe(0.0825)
        ->and(app(SchemaDriftAnalyzer::class)->analyze('daily_loan_dinamis', $path)['has_pending_decision'])->toBeFalse();
});

it('scans the full UTF-16 tab export with the same header and width as its preview', function () {
    $path = ($this->writeReviewSource)($this->reviewHeaders, [$this->reviewRow]);
    $lines = [implode("\t", $this->reviewHeaders)];
    for ($i = 0; $i < 700; $i++) {
        $lines[] = implode("\t", $this->reviewRow).($i === 650 ? "\tEXTRA" : '');
    }
    file_put_contents($path, "\xFF\xFE".mb_convert_encoding(implode("\r\n", $lines), 'UTF-16LE', 'UTF-8'));

    $report = ($this->reviewAnalyze)($path);

    expect($report['source']['delimiter'])->toBe("\t")
        ->and($report['source']['structural_scan']['total_rows'])->toBe(700)
        ->and($report['source']['structural_scan']['misaligned_rows'])->toBe(1)
        ->and($report['has_blocking_issue'])->toBeTrue();
});

it('validates spreadsheet rows beyond the second chunk and finds an undeclared data column', function () {
    $path = tempnam(sys_get_temp_dir(), 'review_xlsx_').'.xlsx';
    $this->reviewFiles[] = $path;
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->fromArray($this->reviewHeaders, null, 'A1');
    for ($row = 2; $row <= 2502; $row++) {
        $sheet->fromArray($this->reviewRow, null, 'A'.$row);
    }
    $sheet->setCellValue('D2402', 'UNDECLARED');
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $report = ($this->reviewAnalyze)($path);

    expect($report['source']['format'])->toBe('spreadsheet')
        ->and($report['source']['structural_scan']['complete'])->toBeTrue()
        ->and($report['source']['structural_scan']['total_rows'])->toBe(2501)
        ->and($report['source']['structural_scan']['misaligned_rows'])->toBe(1)
        ->and($report['source']['structural_scan']['examples'][0]['row'])->toBe(2402)
        ->and($report['has_blocking_issue'])->toBeTrue();
});

it('streams active-tab xlsx sheets with preamble and shared strings identically to phpspreadsheet', function () {
    $path = tempnam(sys_get_temp_dir(), 'review_xlsx_tab_').'.xlsx';
    $this->reviewFiles[] = $path;
    $book = new Spreadsheet;
    $first = $book->getActiveSheet();
    $first->setTitle('Ringkasan');
    $first->setCellValue('A1', 'RINGKASAN_TIDAK_AKTIF');

    $active = $book->createSheet();
    $active->setTitle('Detail');
    $active->fromArray(['Laporan Review', 'Periode'], null, 'A1');
    $active->fromArray($this->reviewHeaders, null, 'A3');
    for ($row = 4; $row <= 2103; $row++) {
        $active->fromArray($this->reviewRow, null, 'A'.$row);
    }
    $book->setActiveSheetIndex(1);
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $report = ($this->reviewAnalyze)($path);

    expect($report['status'])->toBeIn(['match', 'adapted'])
        ->and($report['has_pending_decision'])->toBeFalse()
        ->and($report['has_blocking_issue'])->toBeFalse()
        ->and($report['stats']['matched'])->toBe(3)
        ->and($report['source']['header_row'])->toBe(3)
        ->and($report['source']['structural_scan']['complete'])->toBeTrue()
        ->and($report['source']['structural_scan']['total_rows'])->toBe(2100)
        ->and($report['source']['structural_scan']['misaligned_rows'])->toBe(0);
});

it('keeps legacy Daily Loan business headers identical in preview strategy and import mapping', function () {
    $headers = ['PERIODE', 'KODE KANWIL', 'KANWIL', 'KODE CABANG', 'CABANG', 'BRANCH', 'UNIT', 'CURTYP', 'AO NAME', 'CIFNO', 'NOMOR REKENING', 'STATUS REKENING', 'LN TYPE', 'NAMA DEBITUR', 'RATE', 'JANGKA WAKTU', 'PLAFON', 'BAKI DEBET', 'PN_PEMUTUS1', 'PN_PEMUTUS'];
    $controller = app(ImportExcelController::class);
    $importHeaders = (new ReflectionMethod($controller, 'canonicalizeDailyLoanSourceHeaders'))->invoke($controller, $headers);
    $strategyHeaders = (new DailyLoanImportStrategy)->transformHeaders($headers);
    $previewHeaders = app(SchemaDriftAnalyzer::class)->canonicalizer('daily_loan_dinamis')($headers);

    expect($strategyHeaders)->toBe($importHeaders)
        ->and($importHeaders[1])->toBe('KODE_KANWIL1')
        ->and($importHeaders[10])->toBe('NOMOR_REKENING1')
        ->and($importHeaders[17])->toBe('BAKI_DEBET1')
        ->and($importHeaders[18])->toBe('PN_PEMUTUS1')
        ->and($importHeaders[19])->toBe('PN_PEMUTUS')
        ->and($previewHeaders)->toBe(array_map('strtolower', $importHeaders));
});
