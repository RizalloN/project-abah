<?php

namespace Tests\Feature\Import;

use App\Http\Controllers\Import\ImportMauBrimoController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MauBrimoImportTest extends TestCase
{
    private const HEADERS = [
        'TAHUN', 'PERIODE', 'POSISI', 'REGION', 'RGDESC', 'MAINBR', 'MBDESC',
        'BRANCH', 'BRDESC', 'JENIS', 'KATEGORI', 'SEGMENTASI', 'FREQUENCY',
        'JUMLAH_CIFNO', 'JUMLAH_REK_FIN',
    ];

    private int $reportId;

    private array $directories = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('MAU import tests require an isolated SQLite database.');
        }

        config([
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'import.cache_store' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'import.snapshot.pause_during_import' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn')->unique();
            $table->string('role');
            $table->string('branch_scope')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active')->default(true);
            $table->string('import_controller')->nullable();
            $table->boolean('requires_manual_periode')->default(false);
            $table->string('manual_periode_type')->nullable();
            $table->string('manual_periode_label')->nullable();
            $table->text('manual_periode_help')->nullable();
            $table->timestamps();
        });
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_report');
            $table->string('file_name');
            $table->text('folder_path');
            $table->string('status');
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('total_success')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedInteger('created_by');
            $table->text('message')->nullable();
            $table->text('job_context')->nullable();
            $table->string('job_fingerprint')->nullable()->unique();
            $table->string('job_content_hash')->nullable();
            $table->timestamps();
        });
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->text('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at');
        });

        $this->migrateReport();
        $this->reportId = (int) DB::table('nama_report')->where('table_name', 'mau_brimo_r_all')->value('id_report');
        $this->actingAs(User::factory()->create(['pn' => User::SUPERADMIN_PN, 'role' => 'superadmin']));
    }

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            if (File::isDirectory($directory)) {
                File::deleteDirectory($directory);
            }
        }

        parent::tearDown();
    }

    public function test_mau_report_registration_is_idempotent_and_has_all_business_columns(): void
    {
        $this->migrateReport();

        $this->assertSame(1, DB::table('nama_report')->where('table_name', 'mau_brimo_r_all')->count());
        $this->assertSame('ImportMauBrimoController', DB::table('nama_report')->where('id_report', $this->reportId)->value('import_controller'));
        foreach (self::HEADERS as $header) {
            $this->assertTrue(Schema::hasColumn('mau_brimo_r_all', strtolower($header)), $header);
        }
    }

    public function test_brimo_process_route_enforces_mbdesc_without_client_filters_and_preserves_source_values(): void
    {
        $rows = [
            $this->row(['MBDESC' => 'KC Madiun', 'BRDESC' => 'UNIT LUAR AREA', 'JUMLAH_CIFNO' => '0', 'JUMLAH_REK_FIN' => '0', 'FREQUENCY' => '0']),
            $this->row(['MAINBR' => '49', 'MBDESC' => '  KC Magetan  ', 'BRANCH' => '1149', 'BRDESC' => 'Unit Magetan', 'JUMLAH_CIFNO' => '123', 'JUMLAH_REK_FIN' => '456']),
            $this->row(['MAINBR' => '51', 'MBDESC' => 'KC Malang Kawi', 'BRDESC' => 'KC Madiun', 'JUMLAH_CIFNO' => '999', 'JUMLAH_REK_FIN' => '999']),
        ];

        $response = $this->process($rows);
        $response->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonPath('total_success', 2)->assertJsonPath('total_failed', 0)
            ->assertJsonPath('source_rows', 3)->assertJsonPath('filtered_rows', 1);
        $this->assertSame(2, DB::table('mau_brimo_r_all')->count());
        $this->assertDatabaseHas('mau_brimo_r_all', [
            'tahun' => 2026, 'periode' => '2026-10', 'posisi' => '2026-10-23',
            'region' => 'R', 'rgdesc' => 'KANWIL MALANG', 'mainbr' => '45',
            'mbdesc' => 'KC Madiun', 'branch' => '4501', 'brdesc' => 'UNIT LUAR AREA',
            'jenis' => 'POSISI', 'kategori' => 'MONTHLY ACTIVE USER', 'segmentasi' => 'RITEL',
            'frequency' => 0, 'jumlah_cifno' => 0, 'jumlah_rek_fin' => 0,
        ]);
        $this->assertDatabaseHas('mau_brimo_r_all', ['mbdesc' => 'KC Magetan', 'jumlah_cifno' => 123, 'jumlah_rek_fin' => 456]);
        $this->assertMatchesRegularExpression('/^MAUBRIMO:[a-f0-9]{64}$/D', DB::table('mau_brimo_r_all')->value('uniqueid_namareport'));
        $this->assertDatabaseHas('import_jobs', ['id' => $response->json('job_id'), 'status' => 'completed', 'total_files' => 2, 'total_success' => 2, 'total_failed' => 0]);
    }

    public function test_tampered_filters_cannot_import_an_outside_mbdesc_even_when_brdesc_matches_area6(): void
    {
        $this->process([
            $this->row(['MAINBR' => '51', 'MBDESC' => 'KC Malang Kawi', 'BRDESC' => 'KC Ngawi']),
        ], ['active_filters_json' => json_encode(['6' => ['KC Malang Kawi']])])->assertUnprocessable();

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->count());
    }

    public function test_client_filters_may_narrow_the_mandatory_area6_selection(): void
    {
        $this->process([
            $this->row(['MBDESC' => 'KC Madiun']),
            $this->row(['MAINBR' => '57', 'MBDESC' => 'KC Ngawi', 'BRANCH' => '5701']),
        ], ['active_filters_json' => json_encode(['6' => ['KC Ngawi']])])
            ->assertOk()->assertJsonPath('total_success', 1);

        $this->assertSame(['KC Ngawi'], DB::table('mau_brimo_r_all')->pluck('mbdesc')->all());
    }

    public function test_invalid_eligible_period_aborts_every_row_before_any_insert(): void
    {
        $this->process([
            $this->row(),
            $this->row(['BRANCH' => '4502', 'PERIODE' => 'not-a-period']),
        ])->assertUnprocessable();

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->where('status', 'completed')->count());
    }

    public function test_an_invalid_outside_row_does_not_abort_valid_area6_data(): void
    {
        $this->process([
            $this->row(['MBDESC' => 'KC LUAR AREA', 'PERIODE' => '', 'JUMLAH_CIFNO' => 'bad']),
            $this->row(),
        ])->assertOk()->assertJsonPath('total_success', 1);

        $this->assertSame(1, DB::table('mau_brimo_r_all')->count());
    }

    public function test_integer_metrics_are_preserved_above_float_safe_precision(): void
    {
        $this->process([
            $this->row(['JUMLAH_CIFNO' => '9007199254740993', 'JUMLAH_REK_FIN' => '9007199254740995']),
        ])->assertOk()->assertJsonPath('total_success', 1);

        $row = DB::table('mau_brimo_r_all')->first();
        $this->assertSame('9007199254740993', (string) $row->jumlah_cifno);
        $this->assertSame('9007199254740995', (string) $row->jumlah_rek_fin);
    }

    public function test_fractional_or_missing_metrics_are_rejected_without_a_successful_job(): void
    {
        foreach (['1.25', '', '-1'] as $value) {
            $this->process([$this->row(['JUMLAH_CIFNO' => $value])])->assertUnprocessable();
        }

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->where('status', 'completed')->count());
    }

    public function test_a_repeat_source_is_rejected_without_adding_rows_or_another_completed_job(): void
    {
        $rows = [$this->row()];
        $this->process($rows)->assertOk()->assertJsonPath('total_success', 1);
        $this->process($rows)->assertStatus(409)->assertJsonPath('duplicate_detected', true);
        $this->process([$this->row(['JUMLAH_CIFNO' => '999'])])->assertStatus(409)->assertJsonPath('duplicate_detected', true);

        $this->assertSame(1, DB::table('mau_brimo_r_all')->count());
        $this->assertDatabaseHas('mau_brimo_r_all', ['jumlah_cifno' => 10, 'jumlah_rek_fin' => 20]);
        $this->assertSame(1, DB::table('import_jobs')->where('status', 'completed')->count());
    }

    public function test_another_position_in_the_same_month_remains_importable(): void
    {
        $this->process([$this->row()])->assertOk();
        $this->process([$this->row(['POSISI' => '2026-10-24 00:00:00'])])->assertOk();

        $this->assertSame(['2026-10-23', '2026-10-24'], DB::table('mau_brimo_r_all')->orderBy('posisi')->pluck('posisi')->all());
    }

    public function test_omitting_a_required_selected_column_cannot_silently_discard_metrics(): void
    {
        $this->process([$this->row()], ['selected_columns' => range(0, 13)])->assertUnprocessable();

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
    }

    public function test_preview_finds_every_area6_branch_after_the_national_sample_and_uses_only_mbdesc(): void
    {
        $rows = [];
        for ($i = 0; $i < 4001; $i++) {
            $rows[] = $this->row(['MAINBR' => '51', 'MBDESC' => 'KC Malang Kawi', 'BRDESC' => 'KC Madiun', 'BRANCH' => (string) (10000 + $i)]);
        }
        for ($i = 0; $i < 105; $i++) {
            $rows[] = $this->row(['BRANCH' => (string) (30000 + $i)]);
        }
        foreach (['KC Magetan', 'KC Ngawi', 'KC Ponorogo'] as $i => $branch) {
            $rows[] = $this->row(['MBDESC' => $branch, 'BRDESC' => 'UNIT LUAR AREA', 'BRANCH' => (string) (20000 + $i)]);
        }
        $path = $this->writeSource($rows);

        $response = $this->withSession($this->uploadSession($path))->postJson('/import/brimo/preview', ['file_path' => $path, 'delimiter' => '|']);
        $response->assertOk()->assertViewIs('import.preview');
        $data = $response->original->getData();
        $this->assertSame([6], array_keys($data['initialArea6Selections']));
        $this->assertEqualsCanonicalizing(['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'], $data['initialArea6Selections'][6]);
        $this->assertCount(100, $data['previewData']);
        $this->assertSame(['MBDESC'], $data['area6ColumnHints']);
        $this->assertFalse($data['warmPreviewIndexOnLoad']);
        foreach ($data['previewData'] as $row) {
            $this->assertNotSame('KC Malang Kawi', trim((string) $row[6]));
        }
    }

    public function test_missing_or_duplicate_mbdesc_is_rejected_on_preview_and_import(): void
    {
        foreach ([[6, 'NAMA_KCI'], [8, 'MBDESC']] as [$index, $replacement]) {
            $headers = self::HEADERS;
            $headers[$index] = $replacement;
            $path = $this->writeSource([$this->row()], $headers);
            $session = $this->uploadSession($path);
            $this->withSession($session)->postJson('/import/brimo/preview', ['file_path' => $path, 'delimiter' => '|'])->assertUnprocessable();
            $this->withSession($session)->postJson('/import/brimo/process', $this->processInput($path))->assertUnprocessable();
        }

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
    }

    public function test_storage_error_after_a_full_chunk_rolls_back_the_entire_mau_batch(): void
    {
        DB::statement("CREATE TRIGGER fail_mau_insert BEFORE INSERT ON mau_brimo_r_all WHEN NEW.branch = '99999' BEGIN SELECT RAISE(ABORT, 'forced storage failure'); END");
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = $this->row(['BRANCH' => (string) (10000 + $i), 'BRDESC' => 'Unit '.$i]);
        }
        $rows[] = $this->row(['BRANCH' => '99999', 'BRDESC' => 'Unit failure']);

        $response = $this->process($rows);
        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertLessThan(600, $response->getStatusCode());
        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->where('status', 'completed')->count());
        $this->assertDatabaseHas('import_jobs', ['status' => 'failed', 'total_success' => 0]);
    }

    public function test_generic_csv_routes_cannot_bypass_the_mandatory_mau_import_policy(): void
    {
        $path = $this->writeSource([
            $this->row(),
            $this->row(['MBDESC' => 'KC Malang Kawi', 'BRDESC' => 'KC Madiun']),
        ]);

        foreach (['/import/preview', '/import/init', '/import/process'] as $route) {
            $this->withSession($this->uploadSession($path))
                ->postJson($route, $this->processInput($path))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('id_report');
        }

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_generic_excel_init_cannot_queue_a_mau_import_outside_the_brimo_policy(): void
    {
        $source = $this->writeSource([$this->row()]);
        $directory = storage_path('app/excel_imports/mau-tests-'.Str::random(12));
        File::ensureDirectoryExists($directory, 0750, true);
        $this->directories[] = $directory;
        File::copy($source, $directory.DIRECTORY_SEPARATOR.'mau.csv');
        $relativePath = 'excel_imports/'.basename($directory).'/mau.csv';

        $this->withSession([
            'active_id_report' => $this->reportId,
            'excel_path' => $relativePath,
        ])->postJson('/import-excel/init', ['path' => $relativePath, 'active_filters_json' => '{}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_report');

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_missing_target_table_is_rejected_without_creating_a_job(): void
    {
        Schema::drop('mau_brimo_r_all');
        $this->process([$this->row()])->assertUnprocessable()->assertJsonValidationErrors('id_report');

        $this->assertSame(0, DB::table('import_jobs')->count());
    }

    public function test_dedicated_mau_controller_rejects_missing_or_wrong_report_configuration(): void
    {
        Route::middleware('web')->post('/_tests/mau-brimo/process', [ImportMauBrimoController::class, 'processImport']);
        $path = $this->writeSource([$this->row()]);
        $session = $this->uploadSession($path);

        $this->withSession(array_replace($session, ['active_id_report' => $this->reportId + 100]))
            ->postJson('/_tests/mau-brimo/process', $this->processInput($path))
            ->assertUnprocessable()->assertJsonValidationErrors('id_report');

        DB::table('nama_report')->where('id_report', $this->reportId)->update(['table_name' => 'wrong_target']);
        $this->withSession($session)->postJson('/_tests/mau-brimo/process', $this->processInput($path))
            ->assertUnprocessable()->assertJsonValidationErrors('id_report');

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->count());
    }

    public function test_ajax_mbdesc_options_include_all_four_branches_while_ignoring_their_own_filter(): void
    {
        $rows = [];
        foreach (['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'] as $i => $branch) {
            $rows[] = $this->row(['MBDESC' => $branch, 'BRANCH' => (string) (10000 + $i), 'BRDESC' => 'UNIT LUAR AREA']);
        }
        $rows[] = $this->row(['MBDESC' => 'KC Malang Kawi', 'BRDESC' => 'KC Madiun']);
        $path = $this->writeSource($rows);

        $response = $this->withSession($this->uploadSession($path))->getJson('/import/preview/filter-options?'.http_build_query([
            'file_path' => $path, 'delimiter' => '|', 'column_index' => 6,
            'active_filters_json' => json_encode([6 => ['KC Madiun']]),
        ]));
        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertEqualsCanonicalizing(['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'], $response->json('values'));
        $this->assertSame(0, DB::table('import_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_ajax_filtered_rows_match_the_base_preview_and_count_every_eligible_row(): void
    {
        $rows = [];
        foreach (['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'] as $i => $branch) {
            $rows[] = $this->row([
                'MBDESC' => $branch, 'BRANCH' => (string) (10000 + $i),
                'JUMLAH_CIFNO' => (string) $i, 'JUMLAH_REK_FIN' => (string) (10 + $i),
            ]);
        }
        $rows[] = $this->row(['MBDESC' => 'KC Malang Kawi', 'BRDESC' => 'KC Madiun']);
        $path = $this->writeSource($rows);
        $session = $this->uploadSession($path);
        $base = $this->withSession($session)->postJson('/import/brimo/preview', ['file_path' => $path, 'delimiter' => '|']);
        $base->assertOk();
        $query = ['file_path' => $path, 'delimiter' => '|', 'active_filters_json' => '{}', 'limit' => 100];

        $response = $this->withSession($session)->getJson('/import/preview/filtered-rows?'.http_build_query($query));
        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('total_matched', 4)
            ->assertJsonPath('returned_rows', 4)->assertJsonPath('truncated', false);
        $this->assertSame($base->original->getData()['previewData'], $response->json('rows'));
        $this->assertSame('2026-10-23', $response->json('rows.0.2'));
        $this->assertSame('0', $response->json('rows.0.13'));
        $this->assertSame('10', $response->json('rows.0.14'));

        $this->withSession($session)->getJson('/import/preview/filtered-rows?'.http_build_query(array_replace($query, ['limit' => 2])))
            ->assertOk()->assertJsonPath('total_matched', 4)->assertJsonPath('returned_rows', 2)
            ->assertJsonPath('truncated', true)->assertJsonCount(2, 'rows');
        $this->assertSame(0, DB::table('import_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_ajax_filtered_rows_cannot_widen_area6_and_respect_explicit_empty_selection(): void
    {
        $path = $this->writeSource([
            $this->row(['MBDESC' => 'KC Madiun']),
            $this->row(['MBDESC' => 'KC Ngawi', 'BRANCH' => '5701']),
            $this->row(['MBDESC' => 'KC Malang Kawi', 'BRDESC' => 'KC Madiun']),
        ]);
        $session = $this->uploadSession($path);
        $query = ['file_path' => $path, 'delimiter' => '|', 'limit' => 100];

        $this->withSession($session)->getJson('/import/preview/filtered-rows?'.http_build_query($query + [
            'active_filters_json' => json_encode([6 => ['KC Ngawi', 'KC Malang Kawi']]),
        ]))->assertOk()->assertJsonPath('total_matched', 1)->assertJsonPath('rows.0.6', 'KC Ngawi');

        foreach ([[6 => ['KC Malang Kawi']], [6 => []]] as $filters) {
            $this->withSession($session)->getJson('/import/preview/filtered-rows?'.http_build_query($query + [
                'active_filters_json' => json_encode($filters),
            ]))->assertOk()->assertJsonPath('total_matched', 0)->assertJsonPath('returned_rows', 0)
                ->assertJsonPath('truncated', false)->assertJsonCount(0, 'rows');
        }

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->count());
    }

    public function test_explicit_empty_process_filter_cannot_fall_back_to_importing_every_branch(): void
    {
        $this->process([$this->row()], ['active_filters_json' => json_encode([6 => []])])->assertUnprocessable();

        $this->assertSame(0, DB::table('mau_brimo_r_all')->count());
        $this->assertSame(0, DB::table('import_jobs')->count());
    }

    private function migrateReport(): void
    {
        $migration = require database_path('migrations/2026_10_25_000001_add_mau_brimo_import_report.php');
        $migration->up();
    }

    private function row(array $overrides = []): array
    {
        return array_replace([
            'TAHUN' => '2026', 'PERIODE' => '2026-10', 'POSISI' => '2026-10-23 00:00:00',
            'REGION' => 'R', 'RGDESC' => 'KANWIL MALANG', 'MAINBR' => '45',
            'MBDESC' => 'KC Madiun', 'BRANCH' => '4501', 'BRDESC' => 'Unit Madiun',
            'JENIS' => 'POSISI', 'KATEGORI' => 'MONTHLY ACTIVE USER', 'SEGMENTASI' => 'RITEL',
            'FREQUENCY' => '4', 'JUMLAH_CIFNO' => '10', 'JUMLAH_REK_FIN' => '20',
        ], $overrides);
    }

    private function writeSource(array $rows, array $headers = self::HEADERS): string
    {
        $directory = storage_path('app/imports/import_'.now()->format('Ymd_His').'_'.Str::random(5));
        File::ensureDirectoryExists($directory, 0750, true);
        $this->directories[] = $directory;
        $path = $directory.DIRECTORY_SEPARATOR.'mau-brimo.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers, '|');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), '|');
        }
        fclose($handle);

        return $path;
    }

    private function uploadSession(string $path): array
    {
        return [
            'active_id_report' => $this->reportId,
            'import_type' => 'brimo',
            'import_files' => [['name' => basename($path), 'path' => $path]],
            'final_import_path' => $path,
        ];
    }

    private function processInput(string $path, array $overrides = []): array
    {
        return array_replace([
            'file_path' => $path,
            'selected_columns' => range(0, count(self::HEADERS) - 1),
            'active_filters_json' => '{}',
            'delimiter' => '|',
        ], $overrides);
    }

    private function process(array $rows, array $overrides = []): TestResponse
    {
        $path = $this->writeSource($rows);

        return $this->withSession($this->uploadSession($path))->postJson('/import/brimo/process', $this->processInput($path, $overrides));
    }
}
