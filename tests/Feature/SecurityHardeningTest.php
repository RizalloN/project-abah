<?php

use App\Http\Controllers\Import\Concerns\AuthorizesImportSourceFiles;
use App\Http\Controllers\Import\Concerns\AuthorizesSessionImportStorageFiles;
use App\Http\Controllers\Import\ImportCrasController;
use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Models\User;
use App\Rules\TrustedSpreadsheetUrl;
use App\Services\Import\ExcelImportJobService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('adds browser security headers to web responses', function () {
    $response = app()->handle(Request::create('/login', 'GET'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('X-Download-Options'))->toBe('noopen');
    expect($response->headers->get('X-DNS-Prefetch-Control'))->toBe('off');
    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
    expect($response->headers->get('Content-Security-Policy'))->not->toContain("'unsafe-eval'");
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->has('X-Powered-By'))->toBeFalse();
});

it('rejects legacy tracing methods before they reach controllers', function () {
    $middleware = new SecurityHeadersMiddleware;
    $request = Request::create('/login', 'TRACE');

    $response = $middleware->handle($request, fn () => response('should not run'));

    expect($response->getStatusCode())->toBe(405);
    expect($response->getContent())->toBe('Method Not Allowed');
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('enforces HTTPS transport and isolates workbook framing policy', function () {
    $middleware = new SecurityHeadersMiddleware;
    $secureLogin = Request::create('https://asixdashboard.online/login', 'GET');
    $loginResponse = $middleware->handle($secureLogin, fn () => response('login'));

    expect($loginResponse->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains');
    expect($loginResponse->headers->get('Content-Security-Policy'))
        ->toContain('upgrade-insecure-requests');
    expect($loginResponse->headers->get('X-Frame-Options'))->toBe('DENY');

    $workbookRequest = Request::create(
        'https://asixdashboard.online/workbooks/market-share/token/market-share.xlsx',
        'GET'
    );
    $workbookResponse = $middleware->handle($workbookRequest, fn () => response('workbook'));

    expect($workbookResponse->headers->has('X-Frame-Options'))->toBeFalse();
    expect($workbookResponse->headers->get('Cross-Origin-Resource-Policy'))->toBe('cross-origin');
    expect($workbookResponse->headers->get('Content-Security-Policy'))
        ->toContain('frame-ancestors https://*.officeapps.live.com');
});

it('rate limits sensitive admin control actions without throttling the import page', function () {
    $importPageRoute = Route::getRoutes()->getByName('import.index');
    $deleteRoute = Route::getRoutes()->getByName('import.report-management.delete');
    $userDeleteRoute = Route::getRoutes()->getByName('user-management.destroy');

    expect($importPageRoute?->gatherMiddleware())->not->toContain('throttle:admin-sensitive');
    expect($deleteRoute?->gatherMiddleware())->toContain('throttle:admin-sensitive');
    expect($userDeleteRoute?->gatherMiddleware())->toContain('throttle:admin-sensitive');
});

it('renders the secured import page with sanitized flash notices', function () {
    $createdUsersTable = false;
    $createdReportsTable = false;

    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn', 30)->unique();
            $table->string('password');
            $table->string('role', 20)->default('user');
            $table->string('branch_scope', 30)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        $createdUsersTable = true;
    }

    if (!Schema::hasTable('nama_report')) {
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->id('id_report');
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active')->default(true);
            $table->string('import_controller', 150)->nullable();
            $table->boolean('requires_manual_periode')->default(false);
            $table->timestamps();
        });
        $createdReportsTable = true;
    }

    $admin = User::factory()->create([
        'pn' => 'import-render-' . Str::random(6),
        'role' => 'admin',
        'branch_scope' => null,
    ]);

    try {
        $response = $this->actingAs($admin)
            ->withSession([
                'sweet_success' => [
                    'title' => 'Import selesai',
                    'text' => "Baris satu<br>Baris dua <script>alert('x')</script>",
                ],
                'sweet_warning' => [
                    'title' => 'Peringatan import',
                    'text' => '<b>Periksa kembali</b>',
                ],
            ])
            ->get(route('import.index'));

        $response->assertOk();
        $response->assertSee('const successTitle = "Import selesai";', false);
        $response->assertSee('const warningTitle = "Peringatan import";', false);
        $response->assertDontSee("<script>alert('x')</script>", false);
    } finally {
        $admin->delete();
        Auth::guard()->forgetUser();

        if ($createdReportsTable) {
            Schema::dropIfExists('nama_report');
        }
        if ($createdUsersTable) {
            Schema::dropIfExists('users');
        }
    }
});

it('rate limits sensitive authentication actions', function () {
    $loginRoute = collect(Route::getRoutes())->first(
        fn ($route) => $route->uri() === 'login' && in_array('POST', $route->methods(), true)
    );
    $passwordRoute = Route::getRoutes()->getByName('password.update');

    expect($loginRoute?->gatherMiddleware())->toContain('throttle:auth-sensitive');
    expect($passwordRoute?->gatherMiddleware())->toContain('throttle:auth-sensitive');
});

it('keeps the public directory limited to the Laravel PHP entrypoint', function () {
    expect(file_exists(public_path('presentation-layout-audit.php')))->toBeFalse();

    $publicHtaccess = (string) file_get_contents(public_path('.htaccess'));
    expect($publicHtaccess)->toContain('^(?!index\\.php$).*\\.php$');
    expect($publicHtaccess)->toContain('php_flag display_errors Off');
    expect($publicHtaccess)->toContain('php_flag session.use_strict_mode On');
});

it('does not expose the private local filesystem through Laravel storage routes', function () {
    expect(config('filesystems.disks.local.serve'))->toBeFalse();
    expect(Route::getRoutes()->getByName('storage.local'))->toBeNull();
    expect(Route::getRoutes()->getByName('storage.local.upload'))->toBeNull();
});

it('protects public workbook proxies with tokens and rate limiting', function () {
    config([
        'services.market_share.public_token' => 'security-market-share-token',
        'services.market_share_mapping.public_token' => 'security-mapping-token',
    ]);

    $this->get(route('public-workbooks.market-share'))->assertNotFound();
    $this->get(route('public-workbooks.market-share.token', ['token' => 'wrong-token']))->assertNotFound();
    $this->get(route('public-workbooks.market-share-mapping'))->assertNotFound();
    $this->get(route('public-workbooks.market-share-mapping.token', ['token' => 'wrong-token']))->assertNotFound();

    foreach ([
        'public-workbooks.market-share',
        'public-workbooks.market-share.token',
        'public-workbooks.market-share-mapping',
        'public-workbooks.market-share-mapping.token',
    ] as $routeName) {
        expect(Route::getRoutes()->getByName($routeName)?->gatherMiddleware())
            ->toContain('throttle:60,1');
    }
});

it('accepts only trusted Google Sheets HTTPS sources', function () {
    $rule = new TrustedSpreadsheetUrl;

    expect(Validator::make([
        'url' => 'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/edit',
    ], ['url' => ['required', $rule]])->passes())->toBeTrue();

    foreach ([
        'http://docs.google.com/spreadsheets/d/example/edit',
        'https://127.0.0.1/internal',
        'https://example.com/spreadsheets/d/example/edit',
        'https://docs.google.com.evil.example/spreadsheets/d/example/edit',
    ] as $url) {
        expect(Validator::make(['url' => $url], [
            'url' => ['required', new TrustedSpreadsheetUrl],
        ])->fails())->toBeTrue();
    }
});

it('authorizes preview files by import root and active upload session', function () {
    $guard = new class {
        use AuthorizesImportSourceFiles;

        public function authorize(string $path): string
        {
            return $this->authorizeImportSourceFile($path);
        }

        public function cleanup(string $path): void
        {
            $this->cleanupAuthorizedImportDirectory($path);
        }
    };

    $folderA = storage_path('app/imports/import_20260724_120000_' . Str::random(5));
    $folderB = storage_path('app/imports/import_20260724_120001_' . Str::random(5));
    $outside = storage_path('framework/testing/security-outside-' . Str::random(8) . '.csv');

    File::ensureDirectoryExists($folderA, 0750, true);
    File::ensureDirectoryExists($folderB, 0750, true);
    File::ensureDirectoryExists(dirname($outside), 0750, true);
    File::put($folderA . '/source.csv', "A,B\n1,2\n");
    File::put($folderB . '/source.csv', "A,B\n3,4\n");
    File::put($outside, "A,B\n5,6\n");

    try {
        session(['import_files' => [[
            'name' => 'source.csv',
            'path' => $folderA . '/source.csv',
        ]]]);

        expect($guard->authorize($folderA . '/source.csv'))->toBe(realpath($folderA . '/source.csv'));
        expect(fn () => $guard->authorize($folderB . '/source.csv'))
            ->toThrow(ValidationException::class);
        expect(fn () => $guard->authorize($outside))
            ->toThrow(ValidationException::class);

        $guard->cleanup($folderA . '/source.csv');

        expect(File::isDirectory($folderA))->toBeFalse();
        expect(File::isDirectory($folderB))->toBeTrue();
        expect(File::isDirectory(storage_path('app/imports')))->toBeTrue();
    } finally {
        File::deleteDirectory($folderA);
        File::deleteDirectory($folderB);
        File::delete($outside);
        session()->forget(['import_files', 'final_import_path']);
    }
});

it('preflights expanded archive size before extracting import files', function () {
    $sevenZip = 'C:\\Program Files\\7-Zip\\7z.exe';
    if (!is_file($sevenZip) || !class_exists(ZipArchive::class)) {
        $this->markTestSkipped('7-Zip atau ZipArchive tidak tersedia pada environment test.');
    }

    $guard = new class {
        use AuthorizesImportSourceFiles;

        public function extract(string $archivePath, string $directory): array
        {
            return $this->extractImportArchive($archivePath, $directory);
        }
    };

    $directory = storage_path('framework/testing/security-archive-' . Str::random(8));
    $archivePath = $directory . '.zip';
    File::ensureDirectoryExists(dirname($archivePath), 0750, true);

    $archive = new ZipArchive();
    expect($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $archive->addFromString('source.csv', str_repeat('A', 128));
    $archive->close();

    config([
        'import.security.seven_zip_binary' => $sevenZip,
        'import.security.archive_max_expanded_bytes' => 32,
    ]);

    try {
        expect(fn () => $guard->extract($archivePath, $directory))
            ->toThrow(RuntimeException::class, 'Isi arsip melebihi batas keamanan import.');
        expect(is_file($directory . '/extracted/source.csv'))->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
        File::delete($archivePath);
    }
});

it('accepts RAR metadata with empty link fields while retaining real link detection', function () {
    $guard = new class {
        use AuthorizesImportSourceFiles;

        public function isLink(array $properties): bool
        {
            return $this->isImportArchiveLink($properties);
        }
    };

    expect($guard->isLink([
        'Symbolic Link' => '',
        'Hard Link' => '',
        'Copy Link' => '',
        'Attributes' => 'A',
    ]))->toBeFalse();

    expect($guard->isLink(['Symbolic Link' => 'outside.csv']))->toBeTrue();
    expect($guard->isLink(['Hard Link' => 'outside.csv']))->toBeTrue();
    expect($guard->isLink(['Copy Link' => 'outside.csv']))->toBeTrue();
    expect($guard->isLink(['Attributes' => 'lrwxrwxrwx']))->toBeTrue();
});

it('binds specialized importer paths to the current upload session', function () {
    $guard = new class {
        use AuthorizesSessionImportStorageFiles;

        public function authorize(string $path): array
        {
            return $this->authorizeSessionImportStorageFile(
                $path,
                'security_import_file',
                ['performance_pis_imports'],
                ['csv']
            );
        }
    };

    $directory = storage_path('app/private/performance_pis_imports');
    $ownedRelativePath = 'performance_pis_imports/security-owned-' . Str::random(8) . '.csv';
    $foreignRelativePath = 'performance_pis_imports/security-foreign-' . Str::random(8) . '.csv';
    $ownedPath = storage_path('app/private/' . $ownedRelativePath);
    $foreignPath = storage_path('app/private/' . $foreignRelativePath);

    File::ensureDirectoryExists($directory, 0750, true);
    File::put($ownedPath, "A,B\n1,2\n");
    File::put($foreignPath, "A,B\n3,4\n");

    try {
        session(['security_import_file' => $ownedRelativePath]);

        [$relativePath, $absolutePath] = $guard->authorize($ownedRelativePath);
        expect($relativePath)->toBe($ownedRelativePath);
        expect($absolutePath)->toBe(realpath($ownedPath));

        expect(fn () => $guard->authorize($foreignRelativePath))
            ->toThrow(HttpException::class);
        expect(fn () => $guard->authorize($foreignPath))
            ->toThrow(HttpException::class);
    } finally {
        File::delete($ownedPath);
        File::delete($foreignPath);
        session()->forget('security_import_file');
    }
});

it('binds cached Excel preview state to the user who created it', function () {
    $owner = User::factory()->make([
        'id' => 91001,
        'pn' => 'preview-owner-' . Str::random(6),
    ]);
    $otherUser = User::factory()->make([
        'id' => 91002,
        'pn' => 'preview-other-' . Str::random(6),
    ]);
    $previewKey = 'security-preview-' . Str::uuid();
    $service = app(ExcelImportJobService::class);

    Auth::setUser($owner);
    $service->putPreviewState($previewKey, ['path' => 'excel_imports/owned.csv']);
    expect($service->getPreviewState($previewKey)['path'] ?? null)->toBe('excel_imports/owned.csv');

    Auth::setUser($otherUser);
    expect($service->getPreviewState($previewKey))->toBe([]);

    Auth::guard()->forgetUser();
});

it('rejects Excel chunk parameters that do not match the active upload job', function () {
    $owner = User::factory()->make([
        'id' => 92001,
        'pn' => 'excel-chunk-owner-' . Str::random(6),
        'role' => 'admin',
    ]);
    $relativePath = 'excel_imports/security-chunk-' . Str::random(8) . '.csv';
    Storage::put($relativePath, "HEADER\nVALUE\n");

    $createdReportTable = false;
    $createdJobTable = false;

    if (!Schema::hasTable('nama_report')) {
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->id('id_report');
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active')->default(true);
            $table->string('import_controller', 150)->nullable();
            $table->boolean('requires_manual_periode')->default(false);
            $table->timestamps();
        });
        $createdReportTable = true;
    }

    if (!Schema::hasTable('import_jobs')) {
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_report');
            $table->string('file_name');
            $table->text('folder_path');
            $table->string('status')->default('uploaded');
            $table->integer('total_files')->nullable();
            $table->unsignedInteger('total_success')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->text('message')->nullable();
            $table->longText('job_context')->nullable();
            $table->timestamps();
        });
        $createdJobTable = true;
    }

    $reportId = DB::table('nama_report')->insertGetId([
        'nama_report' => 'Security Chunk Report ' . Str::random(6),
        'table_name' => 'security_chunk_target',
        'active' => true,
        'import_controller' => ImportExcelController::class,
        'requires_manual_periode' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], 'id_report');

    $jobId = DB::table('import_jobs')->insertGetId([
        'id_report' => $reportId,
        'file_name' => basename($relativePath),
        'folder_path' => dirname(Storage::path($relativePath)),
        'status' => 'queued',
        'total_files' => 1,
        'total_success' => 0,
        'total_failed' => 0,
        'created_by' => $owner->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        Auth::setUser($owner);
        session([
            'active_id_report' => $reportId,
            'excel_path' => $relativePath,
            'excel_import_params' => ['job_id' => $jobId],
        ]);
        app(ExcelImportJobService::class)->putImportJobState($jobId, [
            'params' => [
                'job_id' => $jobId,
                'header_index' => 0,
                'table_name' => 'security_chunk_target',
                'file_path' => $relativePath,
                'active_filters' => [],
            ],
            'headers' => ['HEADER'],
        ]);

        $forgedRequest = Request::create('/import-excel/chunk', 'POST', [
            'job_id' => $jobId,
            'header_index' => 0,
            'table_name' => 'security_chunk_target',
            'start_row' => 1,
            'chunk_size' => 100,
            'active_filters_json' => '{}',
            'file_path' => 'excel_imports/another-users-file.csv',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        expect(fn () => app(ImportExcelController::class)->processExcelChunk($forgedRequest))
            ->toThrow(HttpException::class);
    } finally {
        DB::table('import_jobs')->where('id', $jobId)->delete();
        DB::table('nama_report')->where('id_report', $reportId)->delete();
        Storage::delete($relativePath);
        session()->forget(['active_id_report', 'excel_path', 'excel_import_params']);
        Auth::guard()->forgetUser();
        if ($createdJobTable) {
            Schema::dropIfExists('import_jobs');
        }
        if ($createdReportTable) {
            Schema::dropIfExists('nama_report');
        }
    }
});

it('uses encrypted server-side sessions by default', function () {
    expect(config('session.encrypt'))->toBeTrue();
});

it('rejects chunk sessions whose declared size exceeds the configured upload cap', function () {
    config(['import.security.upload_max_bytes' => 1024]);

    $requestData = [
        'original_name' => 'source.csv',
        'total_size' => 1025,
        'total_chunks' => 1,
    ];

    expect(fn () => app(ImportSimpananMultiPnCsvController::class)->initChunkUpload(
        Request::create('/import-csv/simpanan-multipn/upload-chunk/init', 'POST', $requestData)
    ))->toThrow(ValidationException::class);

    expect(fn () => app(ImportCrasController::class)->initChunkUpload(
        Request::create('/import/cras/upload-chunk/init', 'POST', $requestData)
    ))->toThrow(ValidationException::class);
});

it('binds Daily Loan chunks to their initiating user and rejects traversal IDs', function () {
    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('user');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    $owner = User::factory()->create(['pn' => 'chunk-owner-' . Str::random(6)]);
    $otherUser = User::factory()->create(['pn' => 'chunk-other-' . Str::random(6)]);
    $controller = app(ImportExcelController::class);

    Auth::login($owner);
    $initRequest = Request::create('/import-excel/daily-loan-dinamis/upload-chunk/init', 'POST', [
        'original_name' => 'daily-loan.csv',
        'total_size' => 1024,
        'total_chunks' => 1,
    ], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $initResponse = $controller->initDailyLoanChunkUpload($initRequest);
    $uploadId = (string) $initResponse->getData(true)['upload_id'];
    $chunkDirectory = storage_path('app/chunk_uploads/' . $uploadId);

    try {
        Auth::login($otherUser);
        $foreignChunkRequest = Request::create('/import-excel/daily-loan-dinamis/upload-chunk', 'POST', [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 1,
        ], [], [
            'file' => UploadedFile::fake()->create('part.bin', 1, 'application/octet-stream'),
        ], ['HTTP_ACCEPT' => 'application/json']);

        expect($controller->uploadDailyLoanChunk($foreignChunkRequest)->getStatusCode())->toBe(403);

        $traversalRequest = Request::create('/import-excel/daily-loan-dinamis/upload-chunk', 'POST', [
            'upload_id' => '..\\..\\logs',
            'chunk_index' => 0,
            'total_chunks' => 1,
        ], [], [
            'file' => UploadedFile::fake()->create('part.bin', 1, 'application/octet-stream'),
        ], ['HTTP_ACCEPT' => 'application/json']);

        expect(fn () => $controller->uploadDailyLoanChunk($traversalRequest))
            ->toThrow(ValidationException::class);
    } finally {
        File::deleteDirectory($chunkDirectory);
        Auth::logout();
    }
});
