<?php

use App\Http\Controllers\Import\ImportFileController;
use App\Models\User;
use App\Services\Import\ImportProgressService;
use App\Services\Import\MerchantQrisDetailCsv;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SchemaDriftSourceToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('QRIS import tests require SQLite.');
    }

    config(['database.connections.sqlite.database' => ':memory:', 'import.cache_store' => 'array']);
    DB::purge('sqlite');
    Schema::create('nama_report', function (Blueprint $table) {
        $table->id('id_report');
        $table->string('nama_report');
        $table->string('table_name');
        $table->boolean('active');
        $table->string('import_controller');
        $table->boolean('requires_manual_periode');
        $table->timestamps();
    });
    Schema::create('import_jobs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('id_report');
        $table->string('status');
        $table->timestamps();
    });
    (require base_path('database/migrations/2026_04_17_000002_add_jumlah_merchant_qris_detail_report.php'))->up();
    (require base_path('database/migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
    (require base_path('database/migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();

    $this->qrisFiles = [];
    $this->qrisHeaders = [
        'PERIODE', 'POSISI', 'REGION', 'RGDESC', 'MAINBR', 'MBDESC', 'BRANCH', 'BRDESC',
        'MERCHANT_PAN', 'STOREID', 'NAMA_MERCHANT', 'KRITERIA', 'JENIS_USAHA', 'KODE_MCC',
        'MCC', 'ALAMAT', 'KODE_POS', 'KOTA', 'PROVINSI', 'NO_REK', 'CIF', 'PN', 'PN_PEMRAKASA',
        'JABATAN', 'TGL_BALIKAN_PTEN', 'STATUS', 'MERCHANT_TYPE', 'AKUMULASI_SV_ONUS',
        'AKUMULASI_SV_OFFUS', 'AKUMULASI_SV_LINKAJA', 'AKUMULASI_SV_TOTAL', 'POSISI_SV_TOTAL',
        'AKUMULASI_TRX_ONUS', 'AKUMULASI_TRX_OFFUS', 'AKUMULASI_TRX_LINKAJA', 'AKUMULASI_TRX_TOTAL',
        'POSISI_TRX_TOTAL', 'SALDO_POSISI', 'RATAS_SALDO', 'FLAGGING_BRI_MERCHANT',
    ];
    $this->qrisRow = [
        '2026.11', '22/11/2026', 'R', 'KANWIL MALANG', '56', 'KANCAB A', '0057', 'UNIT A',
        '1234567890123456789', '00001234', 'TOKO CONTOH', 'UMI', '', '', '', 'Jalan Contoh',
        '12345', 'KAB A', 'JAWA TIMUR', '012345678901234', 'ABC1234', '000001', 'PETUGAS',
        '', '20261122', 'AKTIF', 'STATIS', '1000.00', '2000.00', '0.0', '3000.00', '2000.00',
        '1', '2', '0.0', '3.0', '2.0', '750.25', '300.50', 'NO',
    ];
    $this->qrisOptions = SchemaDriftImportGuard::delimitedOptions('csv', '|');
    $this->writeQrisSource = function (array $headers, array $rows): string {
        $file = tempnam(sys_get_temp_dir(), 'qris_detail_');
        file_put_contents($file, implode('|', $headers)."\n".implode("\n", array_map(
            static fn (array $row) => implode('|', $row),
            $rows
        ))."\n");
        $this->qrisFiles[] = $file;

        return $file;
    };
    $this->analyzeQris = fn (string $file): array => app(SchemaDriftAnalyzer::class)->analyze(
        MerchantQrisDetailCsv::TABLE, $file, $this->qrisOptions
    );
});

afterEach(function () {
    foreach ($this->qrisFiles ?? [] as $file) {
        unlink($file);
    }
});

it('restores a late unquoted pipe in the merchant name and preserves the other fields during staging', function () {
    $unquoted = $this->qrisRow;
    $unquoted[10] = 'TOKO CONTOH | CABANG';
    $quoted = $this->qrisRow;
    $quoted[10] = '"TOKO LAIN | CABANG"';
    $rows = [...array_fill(0, 610, $this->qrisRow), $unquoted, $quoted];
    $path = ($this->writeQrisSource)($this->qrisHeaders, $rows);
    $report = ($this->analyzeQris)($path);
    $scan = $report['source']['structural_scan'];

    expect($report['stats']['matched'])->toBe(40)
        ->and($report['stats']['new'])->toBe(0)
        ->and($report['has_pending_decision'])->toBeFalse()
        ->and($scan['misaligned_rows'])->toBe(0)
        ->and($scan['merchant_name_pipe_repairs'])->toBe(['count' => 1, 'rows' => [612]]);

    $staged = app(ImportFileController::class)->prepareCsvStaging(101, [
        'file_path' => $path,
        'table_name' => MerchantQrisDetailCsv::TABLE,
        'headers' => $this->qrisHeaders,
        'selected_columns' => array_keys($this->qrisHeaders),
        'delimiter' => '|',
        'total_rows' => count($rows),
    ], app(ImportProgressService::class));
    $this->qrisFiles[] = $staged;
    $prepared = Cache::store('array')->get('csv_import_params_101');
    expect($prepared['prepared_rows'])->toBe(612);
    $records = new SplFileObject($staged);
    $records->seek(610);
    $fieldValues = array_combine($prepared['bulkColumns'], $records->fgetcsv(',', '"', '\\'));
    expect($fieldValues['NAMA_MERCHANT'])->toBe('TOKO CONTOH | CABANG')
        ->and($fieldValues['KRITERIA'])->toBe('UMI')
        ->and($fieldValues['SALDO_POSISI'])->toBe('750.25')
        ->and($fieldValues['POSISI'])->toBe('2026-11-22');
    $fieldValues = array_combine($prepared['bulkColumns'], $records->fgetcsv(',', '"', '\\'));
    expect($fieldValues['NAMA_MERCHANT'])->toBe('TOKO LAIN | CABANG');
    unset($records);
});

it('keeps a width error in a different field blocking and does not propose a table change', function () {
    $row = $this->qrisRow;
    $row[15] = 'Jalan Contoh | Gang A';
    $path = ($this->writeQrisSource)($this->qrisHeaders, [$row]);
    $report = ($this->analyzeQris)($path);

    expect($report['has_blocking_issue'])->toBeTrue()
        ->and(array_column($report['review_issues'], 'code'))->toContain('row_width')
        ->and($report['new_columns'])->toBe([])
        ->and($report['source']['structural_scan'])->not->toHaveKey('merchant_name_pipe_repairs');
    expect(fn () => app(SchemaDriftImportGuard::class)->assertReady(MerchantQrisDetailCsv::TABLE, $path, $this->qrisOptions))
        ->toThrow(RuntimeException::class, 'Import ditahan');
});

it('allows an admin to approve or reject actual new headers through the preview decision routes', function () {
    $headers = [...$this->qrisHeaders, 'FLAG_PAYROLL', 'CATATAN_VENDOR'];
    $row = $this->qrisRow;
    $row[10] = 'TOKO CONTOH | CABANG';
    $path = ($this->writeQrisSource)($headers, [[...$row, 'YA', 'contoh catatan']]);
    $table = MerchantQrisDetailCsv::TABLE;
    $source = SchemaDriftSourceToken::issue($table, $path, options: $this->qrisOptions);
    $this->actingAs(User::factory()->make(['id' => 1, 'role' => 'admin', 'pn' => '00000001']));
    $report = $this->postJson(route('import.schema-drift.analyze'), ['source' => $source])
        ->assertOk()
        ->assertJsonPath('report.evolution_mode', 'evolve')
        ->assertJsonPath('report.has_blocking_issue', false)
        ->assertJsonPath('report.has_pending_decision', true)
        ->assertJsonPath('report.stats.new', 2)
        ->json('report');
    $this->assertFalse(Schema::hasColumn($table, 'flag_payroll'));
    $this->postJson(route('import.schema-drift.accept', $report['event']['id']), [
        'source' => $source,
        'source_fingerprint' => $report['source_fingerprint'],
        'columns' => [
            ['proposed_column' => 'flag_payroll', 'name' => 'flag_payroll', 'type' => 'VARCHAR(50)', 'action' => 'add'],
            ['proposed_column' => 'catatan_vendor', 'action' => 'skip'],
        ],
    ])->assertOk()->assertJsonPath('event.status', 'applied');
    $this->assertTrue(Schema::hasColumn($table, 'flag_payroll'));
    $this->assertFalse(Schema::hasColumn($table, 'catatan_vendor'));

    $staged = app(ImportFileController::class)->prepareCsvStaging(102, [
        'file_path' => $path,
        'table_name' => $table,
        'headers' => $headers,
        'selected_columns' => array_keys($headers),
        'delimiter' => '|',
        'total_rows' => 1,
    ], app(ImportProgressService::class));
    $this->qrisFiles[] = $staged;
    $prepared = Cache::store('array')->get('csv_import_params_102');
    $record = new SplFileObject($staged);
    expect(array_combine($prepared['bulkColumns'], $record->fgetcsv(',', '"', '\\')))
        ->toMatchArray(['flag_payroll' => 'YA', 'NAMA_MERCHANT' => 'TOKO CONTOH | CABANG']);
    unset($record);

    $differentFile = ($this->writeQrisSource)([...$headers, 'LABEL_VENDOR'], [[...$row, 'YA', 'lain', 'uji']]);
    $differentSource = SchemaDriftSourceToken::issue($table, $differentFile, options: $this->qrisOptions);
    $differentReport = $this->postJson(route('import.schema-drift.analyze'), ['source' => $differentSource])
        ->assertOk()->json('report');
    $this->postJson(route('import.schema-drift.reject', $differentReport['event']['id']), [
        'source' => $differentSource,
        'source_fingerprint' => $differentReport['source_fingerprint'],
    ])->assertOk()->assertJsonPath('event.status', 'rejected');
    $this->assertFalse(Schema::hasColumn($table, 'label_vendor'));
    expect(($this->analyzeQris)($differentFile)['has_pending_decision'])->toBeFalse();
});

it('resolves an old width alert only for the same fully repaired source', function () {
    $row = $this->qrisRow;
    $row[10] = 'TOKO CONTOH | CABANG';
    $path = ($this->writeQrisSource)($this->qrisHeaders, [$row]);
    $registry = app(SchemaDriftRegistry::class);
    $fixed = app(SchemaDriftAnalyzer::class)->analyze(MerchantQrisDetailCsv::TABLE, $path, [
        'record' => false, 'remember' => false,
    ] + $this->qrisOptions);
    $old = array_replace($fixed, [
        'status' => 'drift',
        'has_pending_decision' => true,
        'has_blocking_issue' => true,
        'review_issues' => [['key' => 'row_width', 'code' => 'row_width', 'decision' => 'pending', 'blocking' => true]],
    ]);
    $old['source']['structural_scan']['misaligned_rows'] = 1;
    unset($old['source']['structural_scan']['merchant_name_pipe_repairs']);
    $event = $registry->record($old);
    $unrelatedEvent = $registry->record(array_replace($old, ['source_fingerprint' => str_repeat('b', 64)]));

    expect(($this->analyzeQris)($path)['resolved_events'])->toBe([$event->id])
        ->and($registry->find($event->id)->status)->toBe('resolved')
        ->and($registry->find($unrelatedEvent->id)->status)->toBe('pending');
});
