<?php

use App\Models\User;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use App\Services\Import\SchemaDrift\SchemaDriftSourceToken;
use App\Services\Import\SchemaDrift\SchemaEvolutionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        test()->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
    }

    foreach (['demo_ph', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles'] as $table) {
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
    Schema::create('demo_ph', function (Blueprint $table) {
        $table->string('uniqueid_namareport')->primary();
        $table->date('periode')->nullable();
        $table->string('acctno', 50)->nullable();
        $table->date('tgl_ph')->nullable();
        $table->decimal('pokok', 20, 2)->nullable();
        $table->timestamps();
    });
    DB::table('nama_report')->insert(['id_report' => 77, 'nama_report' => 'Demo Nominatif PH', 'table_name' => 'demo_ph', 'active' => 1]);

    $this->files = [];
    $this->writeSource = function (array $headers, array $rows, bool $preamble = true): string {
        $path = tempnam(sys_get_temp_dir(), 'drift_') . '.csv';
        $handle = fopen($path, 'w');
        if ($preamble) {
            fputcsv($handle, ['textbox1', 'Textbox38'], ',', '"', '');
            fputcsv($handle, ['Laporan Nominatif PH', 'Periode Data : 18/10/2026'], ',', '"', '');
            fwrite($handle, "\n");
        }
        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);
        $this->files[] = $path;

        return $path;
    };
});

afterEach(function () {
    foreach ($this->files ?? [] as $file) {
        @unlink($file);
    }
    foreach (['demo_ph', 'import_jobs', 'nama_report', 'import_schema_drift_events', 'import_column_format_profiles'] as $table) {
        Schema::dropIfExists($table);
    }
});

function driftRows(int $count, callable $make): array
{
    return array_map($make, range(1, $count));
}

it('detects new columns, explains the date adaptation and records a pending decision', function () {
    $path = ($this->writeSource)(
        ['Textbox3', 'PERIODE', 'ACCTNO', 'TGL_PH', 'POKOK', 'TGL_UPDATE_PH', 'FLAG_HAPUS_TAGIH', 'NO_REKENING_BARU'],
        driftRows(40, fn (int $i) => [$i, '10/18/2026 12:00:00 AM', '81460100' . $i, sprintf('%02d/07/2026', ($i % 28) + 1), number_format($i * 1500.25, 2), sprintf('%02d/08/2026', ($i % 28) + 1), $i % 2 ? 'Y' : 'N', '0' . (814601000000 + $i)])
    );

    $report = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $path);

    expect($report['status'])->toBe('drift')
        ->and($report['source']['header_row'])->toBe(4)
        ->and($report['stats']['matched'])->toBe(4)
        ->and(collect($report['new_columns'])->pluck('proposed_type', 'proposed_column')->all())->toBe([
            'tgl_update_ph' => 'DATE',
            'flag_hapus_tagih' => 'VARCHAR(50)',
            'no_rekening_baru' => 'VARCHAR(50)',
        ])
        ->and(collect($report['adaptations'])->pluck('code')->all())->toContain('time_ignored', 'month_first')
        ->and($report['date_orders'])->toMatchArray(['periode' => 'mdy', 'tgl_ph' => 'dmy'])
        ->and($report['event']['status'])->toBe('pending')
        ->and(app(SchemaDriftRegistry::class)->notifications()['pending'])->toBe(1);

    // Same file again: one notification, counted twice.
    $again = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $path);
    expect($again['event']['id'])->toBe($report['event']['id'])
        ->and($again['event']['occurrences'])->toBe(2);
});

it('adds accepted columns with the chosen type and stops asking afterwards', function () {
    $path = ($this->writeSource)(
        ['PERIODE', 'ACCTNO', 'TGL_UPDATE_PH', 'NOMINAL_RECOVERY'],
        driftRows(20, fn (int $i) => ['18/10/2026', 'A' . $i, sprintf('%02d/09/2026', $i), number_format($i * 10.5, 2)])
    );
    $report = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $path);

    $event = app(SchemaDriftRegistry::class)->accept($report['event']['id'], 'tester', [
        ['proposed_column' => 'nominal_recovery', 'type' => 'DECIMAL(20,4)'],
    ]);

    expect($event->status)->toBe('applied')
        ->and(Schema::hasColumns('demo_ph', ['tgl_update_ph', 'nominal_recovery']))->toBeTrue()
        ->and(app(SchemaDriftRegistry::class)->evolvedColumns('demo_ph'))->toEqualCanonicalizing(['tgl_update_ph', 'nominal_recovery']);

    $after = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $path);
    expect($after['status'])->not->toBe('drift')
        ->and($after['stats']['new'])->toBe(0)
        ->and($after['stats']['matched'])->toBe(4);
});

it('remembers rejected columns so the import keeps running without them', function () {
    $path = ($this->writeSource)(['PERIODE', 'ACCTNO', 'KOLOM_SEMENTARA'], [['18/10/2026', 'A1', 'x'], ['18/10/2026', 'A2', 'y']]);
    $report = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $path);

    app(SchemaDriftRegistry::class)->reject($report['event']['id'], 'tester');
    $after = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $path);

    expect(Schema::hasColumn('demo_ph', 'kolom_sementara'))->toBeFalse()
        ->and($after['has_pending_decision'])->toBeFalse()
        ->and($after['new_columns'][0]['decision'])->toBe('rejected')
        ->and(app(SchemaDriftRegistry::class)->notifications()['pending'])->toBe(0);
});

it('notifies a compatible format change against the learned baseline', function () {
    $old = ($this->writeSource)(['PERIODE', 'ACCTNO', 'TGL_PH'], driftRows(10, fn (int $i) => ['18/10/2026', 'A' . $i, '27/08/2026']));
    $new = ($this->writeSource)(['PERIODE', 'ACCTNO', 'TGL_PH'], driftRows(10, fn (int $i) => ['10/18/2026 12:00:00 AM', 'A' . $i, '27/08/2026']));

    $baseline = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $old);
    $changed = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $new);
    $formatChange = collect($changed['adaptations'])->firstWhere('code', 'format_changed');

    expect($baseline['status'])->toBe('match')
        ->and($baseline['event'])->toBeNull()
        ->and($changed['status'])->toBe('adapted')
        ->and($formatChange['severity'])->toBe('info')
        ->and($formatChange['detail'])->toContain('DD/MM/YYYY')->toContain('MM/DD/YYYY hh:mm:ss AM/PM')
        ->and($changed['event']['status'])->toBe('auto_adapted');
});

it('flags values that cannot fit the target column type', function () {
    $path = ($this->writeSource)(['PERIODE', 'ACCTNO', 'POKOK'], [['bukan tanggal', 'A1', 'satu juta'], ['juga bukan', 'A2', 'dua juta']]);

    $report = app(SchemaDriftAnalyzer::class)->analyze('demo_ph', $path);

    expect($report['status'])->toBe('attention')
        ->and($report['has_blocking_issue'])->toBeTrue()
        ->and(collect($report['adaptations'])->pluck('code')->all())->toContain('not_date', 'not_numeric')
        ->and($report['event']['kind'])->toBe('incompatibility');
});

it('guards schema evolution against unsafe input and running imports', function () {
    $evolution = app(SchemaEvolutionService::class);

    expect(fn () => $evolution->addColumns('demo_ph', [['name' => 'drop table x', 'type' => 'TEXT']]))->toThrow(RuntimeException::class)
        ->and(fn () => $evolution->addColumns('demo_ph', [['name' => 'aman', 'type' => 'LONGBLOB']]))->toThrow(RuntimeException::class)
        ->and(fn () => $evolution->addColumns('users', [['name' => 'aman', 'type' => 'TEXT']]))->toThrow(RuntimeException::class)
        ->and(fn () => $evolution->addColumns('import_jobs', [['name' => 'aman', 'type' => 'TEXT']]))->toThrow(RuntimeException::class);

    DB::table('import_jobs')->insert(['id_report' => 77, 'status' => 'processing']);
    expect(fn () => $evolution->addColumns('demo_ph', [['name' => 'aman', 'type' => 'TEXT']]))->toThrow(RuntimeException::class, 'import aktif');

    DB::table('import_jobs')->update(['status' => 'completed']);
    expect($evolution->addColumns('demo_ph', [['name' => 'aman', 'type' => 'VARCHAR(100)']])[0]['name'])->toBe('aman')
        ->and(Schema::hasColumn('demo_ph', 'aman'))->toBeTrue();
});

it('serves analysis, notifications and decisions to admins over http', function () {
    $path = ($this->writeSource)(['PERIODE', 'ACCTNO', 'KOLOM_BARU'], [['18/10/2026', 'A1', '12.5'], ['18/10/2026', 'A2', '7.25']]);
    $admin = User::factory()->make(['id' => 1, 'role' => 'admin', 'name' => 'Admin Uji', 'pn' => '00000001']);

    $analysis = $this->actingAs($admin)
        ->postJson(route('import.schema-drift.analyze'), ['source' => SchemaDriftSourceToken::issue('demo_ph', $path)])
        ->assertOk()
        ->assertJsonPath('report.status', 'drift')
        ->assertJsonPath('report.new_columns.0.proposed_type', 'DECIMAL(20,2)');

    $this->actingAs($admin)->getJson(route('import.schema-drift.notifications'))
        ->assertOk()
        ->assertJsonPath('pending', 1);

    $this->actingAs($admin)
        ->postJson(route('import.schema-drift.accept', ['event' => $analysis->json('report.event.id')]), [
            'columns' => [['proposed_column' => 'kolom_baru', 'name' => 'nilai_tambahan', 'type' => 'DECIMAL(20,2)']],
        ])
        ->assertOk()
        ->assertJsonPath('event.status', 'applied')
        ->assertJsonPath('feed.pending', 0);

    expect(Schema::hasColumn('demo_ph', 'nilai_tambahan'))->toBeTrue();

    $this->actingAs($admin)
        ->postJson(route('import.schema-drift.analyze'), ['source' => 'token-palsu'])
        ->assertStatus(422);
});
