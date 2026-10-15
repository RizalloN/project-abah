<?php

namespace Tests\Feature\Import;

use App\Jobs\RunImportJob;
use App\Models\User;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\SnapshotQueuePauseService;
use App\Support\AuthenticatedSessionContext;
use App\Support\ReportDataSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ImportSecurityCompatibilityTest extends TestCase
{
    private string $originalStoragePath;

    private string $sandbox;

    private array $browserCookies = [];

    private string $csrfToken = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = storage_path();
        $this->sandbox = $this->originalStoragePath.'/framework/testing/import-http-'.Str::uuid();
        foreach (['app/private', 'app/imports', 'framework/sessions', 'logs'] as $directory) {
            File::ensureDirectoryExists($this->sandbox.'/'.$directory);
        }
        app()->useStoragePath($this->sandbox);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'import.cache_store' => 'array',
            'session.driver' => 'file',
            'session.files' => $this->sandbox.'/framework/sessions',
            'session.encrypt' => true,
            'filesystems.default' => 'local',
            'filesystems.disks.local.root' => $this->sandbox.'/app/private',
            'logging.channels.single.path' => $this->sandbox.'/logs/laravel.log',
            'logging.channels.daily.path' => $this->sandbox.'/logs/laravel.log',
        ]);
        DB::purge('sqlite');
        Storage::forgetDisk('local');
        Http::preventStrayRequests();
        Queue::fake();
        Bus::fake([RunImportJob::class]);

        // Exercise the production CSRF checks, including missing-token rejection.
        app()->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('branch_scope')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('login_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('login_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
        });
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->increments('id_report');
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active')->default(true);
            $table->string('import_controller')->nullable();
            $table->boolean('requires_manual_periode')->default(false);
            $table->timestamps();
        });
        Schema::create('jumlah_merchant_detail', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('POSISI');
            $table->string('NAMA_KANCA');
            $table->string('NAMA_UKER');
            $table->decimal('SALDO_POSISI', 20, 2);
            $table->timestamps();
        });
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedInteger('id_report');
            $table->string('file_name');
            $table->string('folder_path');
            $table->string('status');
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('total_success')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->text('job_context')->nullable();
            $table->string('job_fingerprint')->nullable();
            $table->timestamps();
        });
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->integer('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
        });
        DB::table('nama_report')->insert([
            'id_report' => 101,
            'nama_report' => 'Jumlah Merchant Detail',
            'table_name' => 'jumlah_merchant_detail',
            'active' => true,
            'import_controller' => 'ImportFileController',
            'requires_manual_periode' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->originalStoragePath)) {
            app()->useStoragePath($this->originalStoragePath);
            $root = realpath($this->originalStoragePath.'/framework/testing');
            $target = realpath($this->sandbox);
            if ($root !== false && $target !== false && str_starts_with($target, $root.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($target);
            }
        }

        parent::tearDown();
    }

    public function test_real_admin_cookie_session_can_preview_import_and_poll_two_persisted_csv_rows(): void
    {
        $admin = $this->login();
        $this->browser('GET', route('import.index'))->assertOk()->assertSee('Jumlah Merchant Detail');
        $upload = $this->uploadCsv();
        $filePath = app('session.store')->get('import_files.0.path');
        $preview = $this->browser('GET', $upload->json('redirect'));
        $preview->assertOk()->assertViewIs('import.preview')->assertViewHas('previewData', function (array $rows): bool {
            return count($rows) === 2 && in_array('UNIT A', $rows[0], true) && in_array('UNIT B', $rows[1], true);
        });
        $this->assertSame($admin->id, Auth::id());
        $this->assertSame(AuthenticatedSessionContext::fingerprint($admin), app('session.store')->get(AuthenticatedSessionContext::KEY));

        $init = $this->browser('POST', route('import.init'), [
            'file_path' => $filePath,
            'selected_columns' => [0, 1, 2, 3],
            'active_filters_json' => '{}',
            'delimiter' => '|',
        ], json: true)->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('total_rows', 2);
        $jobId = $init->json('job_id');
        $this->assertSame($admin->id, (int) DB::table('import_jobs')->where('id', $jobId)->value('created_by'));

        $this->mock(ReportDataSyncService::class, fn ($mock) => $mock->shouldReceive('syncImportedJob')->once());
        $this->mock(SnapshotQueuePauseService::class, fn ($mock) => $mock->shouldReceive('resumeWhenNoActiveImports')->once());
        $stream = $this->browser('GET', route('import.stream', ['job_id' => $jobId]));
        $stream->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $events = $stream->streamedContent();
        $this->assertStringContainsString('event: complete', $events);
        $this->assertStringNotContainsString('event: error', $events);

        $rows = DB::table('jumlah_merchant_detail')->orderBy('NAMA_UKER')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['UNIT A', 'UNIT B'], $rows->pluck('NAMA_UKER')->all());
        $this->assertSame(['KC Madiun', 'KC Magetan'], $rows->pluck('NAMA_KANCA')->all());
        $this->assertSame(['2026-10-01', '2026-10-01'], $rows->pluck('POSISI')->all());
        $this->assertSame([1250.5, 2000.25], $rows->map(fn ($row) => (float) $row->SALDO_POSISI)->all());
        $this->browser('GET', route('import.jobs.status', ['jobId' => $jobId]), json: true)
            ->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('total_rows', 2)
            ->assertJsonPath('total_success', 2)->assertJsonPath('total_failed', 0)->assertJsonPath('percent', 100);
    }

    #[DataProvider('spreadsheetFormats')]
    public function test_real_session_accepts_spreadsheet_upload_sse_and_cached_preview(string $format, bool $largeMetadata): void
    {
        $admin = $this->login();
        $contents = $this->spreadsheetContents($format, $largeMetadata);
        $upload = $this->browser('POST', route('import.excel.upload'), ['id_report' => 101], [
            'file' => UploadedFile::fake()->createWithContent('merchant.'.($format === 'xlsx-as-xls' ? 'xls' : $format), $contents),
        ], json: true)->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame($contents, Storage::get(app('session.store')->get('excel_path')));
        $stream = $this->browser('GET', $upload->json('redirect'))->assertOk();
        $this->assertStringContainsString('event: ready', $stream->streamedContent());
        $this->assertStringNotContainsString('event: error_msg', $stream->streamedContent());
        $preview = $this->browser('GET', $upload->json('preview_redirect'))->assertOk()->assertSee('UNIT A')->assertSee('UNIT B');
        $state = app(ExcelImportJobService::class)->getPreviewState($preview->viewData('previewStateKey'));
        $this->assertSame($admin->id, $state['_owner_id']);
        $previewRows = $preview->viewData('preview');
        $this->assertCount(2, $previewRows);
        $this->assertContains('UNIT A', array_values($previewRows[0]));
        $this->assertContains('UNIT B', array_values($previewRows[1]));
        // XLS fallback leaves the total for execution; native XLSX includes its header.
        $expectedTotal = match ($format) {
            'csv', 'txt' => 2,
            'xls' => null,
            default => 3,
        };
        $this->assertSame($expectedTotal, $state['previewMeta']['total_rows']);
        $this->assertSame(101, (int) app('session.store')->get('active_id_report'));
    }

    public static function spreadsheetFormats(): array
    {
        return [
            'CSV' => ['csv', false],
            'TXT' => ['txt', false],
            'XLS' => ['xls', false],
            'XLSX' => ['xlsx', false],
            'XLSX named XLS' => ['xlsx-as-xls', false],
            'large valid XLSX metadata' => ['xlsx', true],
        ];
    }

    #[DataProvider('sessionModes')]
    public function test_real_fresh_and_remembered_sessions_dispatch_owned_excel_jobs_without_running_workers(bool $remember): void
    {
        $admin = $this->login(remember: $remember);
        if ($remember) {
            unset($this->browserCookies[config('session.cookie')]);
            $this->browser('GET', route('import.index'))->assertOk();
            $this->assertTrue(Auth::guard()->viaRemember());
        }
        $upload = $this->browser('POST', route('import.excel.upload'), ['id_report' => 101], [
            'file' => UploadedFile::fake()->createWithContent('merchant.csv', $this->csv()),
        ], json: true)->assertOk();
        $stream = $this->browser('GET', $upload->json('redirect'))->assertOk();
        $this->assertStringContainsString('event: ready', $stream->streamedContent());
        $this->browser('GET', $upload->json('preview_redirect'))->assertOk()->assertSee('UNIT A');
        $relativePath = app('session.store')->get('excel_path');
        $init = $this->browser('POST', route('import.excel.init'), [
            'preview_state_key' => $upload->json('cache_key'),
            'id_report' => 101,
            'selected_columns' => [0, 1, 2, 3],
            'active_filters_json' => '{}',
        ], json: true)->assertOk()->assertJsonPath('status', 'success');
        $jobId = $init->json('job_id');
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        $this->assertSame($admin->id, (int) $job->created_by);
        $this->assertSame('queued', $job->status);
        $state = app(ExcelImportJobService::class)->getImportJobState($jobId);
        $this->assertSame('jumlah_merchant_detail', $state['params']['table_name']);
        $this->assertSame($relativePath, $state['params']['file_path']);
        Bus::assertDispatched(RunImportJob::class, fn (RunImportJob $job): bool => $job->jobId === $jobId
            && $job->connection === 'database' && $job->queue === config('import.queue.import_queue'));
        Bus::assertDispatchedTimes(RunImportJob::class, 1);
        $this->browser('GET', route('import.jobs.status', ['jobId' => $jobId]), json: true)
            ->assertOk()->assertJsonPath('status', 'queued')->assertJsonPath('job_id', $jobId)->assertJsonPath('total_success', 0);
        $this->assertSame(0, DB::table('jumlah_merchant_detail')->count());
        $admin->update(['role' => 'user']);
        $this->browser('GET', route('import.jobs.status', ['jobId' => $jobId]), json: true)->assertUnauthorized();
        $this->assertSame('queued', DB::table('import_jobs')->where('id', $jobId)->value('status'));
        $this->assertFileExists(Storage::path($relativePath));
        Bus::assertDispatchedTimes(RunImportJob::class, 1);
    }

    public static function sessionModes(): array
    {
        return ['fresh login' => [false], 'remembered login' => [true]];
    }

    #[DataProvider('chunkFormats')]
    public function test_real_session_can_finalize_chunk_bytes_and_prepare_preview(string $format): void
    {
        $this->login();
        $contents = $this->spreadsheetContents($format);
        $name = 'merchant.'.$format;
        $init = $this->browser('POST', route('import.excel.upload-chunk.init'), [
            'original_name' => $name, 'total_size' => strlen($contents), 'total_chunks' => 1, 'id_report' => 101,
        ], json: true)->assertOk();
        $id = $init->json('upload_id');
        $this->browser('POST', route('import.excel.upload-chunk'), [
            'upload_id' => $id, 'chunk_index' => 0, 'total_chunks' => 1,
        ], ['file' => UploadedFile::fake()->createWithContent('part.bin', $contents)], json: true)
            ->assertOk()->assertJsonPath('chunk_index', 0);
        $final = $this->browser('POST', route('import.excel.upload-chunk.finalize'), [
            'upload_id' => $id, 'total_chunks' => 1, 'original_name' => $name, 'id_report' => 101,
        ], json: true)->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame($contents, Storage::get(app('session.store')->get('excel_path')));
        $prepared = $this->browser('GET', $final->json('redirect'))->assertOk();
        $this->assertStringContainsString('event: ready', $prepared->streamedContent());
        $this->browser('GET', $final->json('preview_redirect'))->assertOk()->assertSee('UNIT A')->assertSee('UNIT B');
        $this->assertDirectoryDoesNotExist($this->sandbox.'/app/chunk_uploads/'.$id);
    }

    public static function chunkFormats(): array
    {
        return ['CSV' => ['csv'], 'XLSX' => ['xlsx']];
    }

    public function test_csrf_is_required_and_a_valid_retry_keeps_the_authenticated_import_session(): void
    {
        $this->login();
        $this->browser('POST', route('import.upload'), ['id_report' => 101], [
            'file' => UploadedFile::fake()->createWithContent('merchant.csv', $this->csv()),
        ], json: true, csrf: false)->assertStatus(419);
        $this->assertSame([], File::directories($this->sandbox.'/app/imports'));
        $this->uploadCsv()->assertOk()->assertJsonPath('status', 'success');
    }

    public function test_remember_cookie_restores_admin_context_and_preserves_the_upload_across_requests(): void
    {
        $admin = $this->login(remember: true);
        unset($this->browserCookies[config('session.cookie')]);
        $this->browser('GET', route('import.index'))->assertOk();
        $this->assertTrue(Auth::guard()->viaRemember());
        $this->assertSame(AuthenticatedSessionContext::fingerprint($admin), app('session.store')->get(AuthenticatedSessionContext::KEY));
        $this->csrfToken = app('session.store')->token();
        $upload = $this->uploadCsv();
        $this->assertFalse(Auth::guard()->viaRemember());
        $this->browser('GET', $upload->json('redirect'))->assertOk()->assertSee('UNIT A')->assertSee('UNIT B');
    }

    public function test_guests_and_real_nonadmin_sessions_cannot_access_import_routes(): void
    {
        $this->browser('GET', route('import.index'), json: true)->assertUnauthorized();
        $this->login('user');
        $this->browser('GET', route('import.index'), json: true)->assertForbidden();
        $this->browser('POST', route('import.upload'), ['id_report' => 101], [
            'file' => UploadedFile::fake()->createWithContent('merchant.csv', $this->csv()),
        ], json: true)->assertForbidden();
        $this->assertSame([], File::directories($this->sandbox.'/app/imports'));
    }

    public function test_a_second_real_admin_session_cannot_use_the_first_admins_preview_file_or_chunks(): void
    {
        $this->login();
        $upload = $this->uploadCsv();
        $chunk = $this->browser('POST', route('import.excel.upload-chunk.init'), [
            'original_name' => 'merchant.csv', 'total_size' => strlen($this->csv()), 'total_chunks' => 1, 'id_report' => 101,
        ], json: true)->assertOk();
        $ownerCookies = $this->browserCookies;
        $this->browserCookies = [];
        $this->login(pn: 'second-admin');
        $this->browser('GET', $upload->json('redirect'), json: true)->assertUnprocessable()->assertJsonValidationErrors('file_path');
        $this->browser('POST', route('import.excel.upload-chunk'), [
            'upload_id' => $chunk->json('upload_id'), 'chunk_index' => 0, 'total_chunks' => 1,
        ], ['file' => UploadedFile::fake()->createWithContent('part.bin', $this->csv())], json: true)->assertForbidden();
        $this->browser('POST', route('import.excel.upload-chunk.finalize'), [
            'upload_id' => $chunk->json('upload_id'), 'total_chunks' => 1, 'original_name' => 'merchant.csv', 'id_report' => 101,
        ], json: true)->assertForbidden();
        $this->browserCookies = $ownerCookies;
        $this->browser('GET', $upload->json('redirect'))->assertOk()->assertSee('UNIT A');
        $this->browser('POST', route('import.excel.upload-chunk'), [
            'upload_id' => $chunk->json('upload_id'), 'chunk_index' => 0, 'total_chunks' => 1,
        ], ['file' => UploadedFile::fake()->createWithContent('part.bin', $this->csv())], json: true)->assertOk();
    }

    public function test_revoked_admin_session_stops_http_preview_without_deleting_uploads_or_creating_jobs(): void
    {
        $admin = $this->login();
        $upload = $this->uploadCsv();
        $sourcePath = app('session.store')->get('import_files.0.path');
        $this->browser('GET', $upload->json('redirect'))->assertOk()->assertSee('UNIT A');
        $admin->update(['role' => 'user']);
        $this->browser('GET', $upload->json('redirect'), json: true)->assertUnauthorized();
        $this->assertFileExists($sourcePath);
        $this->assertSame(0, DB::table('import_jobs')->count());
        $this->assertSame(0, DB::table('jumlah_merchant_detail')->count());
        Bus::assertNotDispatched(RunImportJob::class);
    }

    private function login(string $role = 'admin', bool $remember = false, string $pn = 'http-admin'): User
    {
        $user = User::create(['name' => 'HTTP Import User', 'pn' => $pn, 'password' => 'Test-password-123', 'role' => $role, 'branch_scope' => null]);
        $page = $this->browser('GET', route('login'))->assertOk();
        $this->assertSame(1, preg_match('/name="_token"\s+value="([^"]+)"/', $page->getContent(), $matches));
        $this->csrfToken = $matches[1];
        $this->browser('POST', route('login'), ['pn' => $pn, 'password' => 'Test-password-123', 'remember' => $remember, '_token' => $this->csrfToken])
            ->assertRedirect(route('dashboard'));
        $this->assertArrayHasKey(config('session.cookie'), $this->browserCookies);

        return $user;
    }

    private function browser(string $method, string $uri, array $parameters = [], array $files = [], bool $json = false, bool $csrf = true): TestResponse
    {
        // Recreate request-local services so the guard must restore the real cookie.
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        Cookie::flushQueuedCookies();
        $server = [
            'HTTPS' => 'on', 'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html',
        ];
        if ($csrf && ! in_array($method, ['GET', 'HEAD'], true) && isset($this->browserCookies['XSRF-TOKEN'])) {
            $server['HTTP_X_XSRF_TOKEN'] = $this->browserCookies['XSRF-TOKEN'];
        }
        $response = $this->call($method, $uri, $parameters, $this->browserCookies, $files, $server);
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < time()) {
                unset($this->browserCookies[$cookie->getName()]);
            } else {
                $this->browserCookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    private function uploadCsv(): TestResponse
    {
        return $this->browser('POST', route('import.upload'), ['id_report' => 101], [
            'file' => UploadedFile::fake()->createWithContent('merchant.csv', $this->csv()),
        ], json: true)->assertOk();
    }

    private function csv(): string
    {
        return "POSISI|NAMA_KANCA|NAMA_UKER|SALDO_POSISI\n2026-10-01|KC Madiun|UNIT A|1250.50\n2026-10-01|KC Magetan|UNIT B|2000.25\n";
    }

    private function spreadsheetContents(string $format, bool $largeMetadata = false): string
    {
        if (in_array($format, ['csv', 'txt'], true)) {
            return $this->csv();
        }

        $path = $this->sandbox.'/merchant.xlsx';
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray([
            ['POSISI', 'NAMA_KANCA', 'NAMA_UKER', 'SALDO_POSISI'],
            ['2026-10-01', 'KC Madiun', 'UNIT A', 1250.50],
            ['2026-10-01', 'KC Magetan', 'UNIT B', 2000.25],
        ]);
        $writer = $format === 'xls' ? new Xls($workbook) : new Xlsx($workbook);
        $writer->save($path);
        $workbook->disconnectWorksheets();
        if ($largeMetadata) {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            foreach (['[Content_Types].xml' => '</Types>', 'xl/workbook.xml' => '</workbook>'] as $name => $closing) {
                $xml = $zip->getFromName($name);
                $xml = str_replace($closing, '<!--'.str_repeat('x', 4_194_305).'-->'.$closing, $xml);
                $this->assertGreaterThan(4_194_304, strlen($xml));
                $zip->addFromString($name, $xml);
            }
            $zip->close();
        }

        return file_get_contents($path);
    }
}
