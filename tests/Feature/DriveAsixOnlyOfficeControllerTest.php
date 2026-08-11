<?php

use App\Models\DriveAsixFile;
use App\Models\User;
use App\Services\DriveAsix\OnlyOfficeEditorService;
use App\Services\DriveAsix\OnlyOfficeJwtService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    Config::set('database.default', 'sqlite');
    Config::set('database.connections.sqlite.database', ':memory:');
    Config::set('cache.default', 'array');
    Config::set('app.url', 'https://app.example.test');
    Config::set('services.onlyoffice.enabled', true);
    Config::set('services.onlyoffice.public_url', 'https://office.example.test');
    Config::set('services.onlyoffice.internal_url', 'http://onlyoffice.local');
    Config::set('services.onlyoffice.app_url', 'https://app.example.test');
    Config::set('services.onlyoffice.jwt_secret', str_repeat('o', 64));
    Config::set('services.onlyoffice.jwt_header', 'AuthorizationJwt');
    Config::set('services.onlyoffice.allowed_download_origins', []);
    Config::set('services.onlyoffice.access_ttl_minutes', 120);
    Config::set('services.onlyoffice.max_download_bytes', 5_242_880);
    Config::set('services.onlyoffice.timeout_seconds', 30);
    Config::set('services.onlyoffice.verify_tls', true);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    Schema::dropAllTables();

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('pn', 20)->unique();
        $table->string('password');
        $table->string('role', 20)->default('user');
        $table->string('branch_scope', 20)->default('area6');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('drive_asix_folders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('name');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('drive_asix_files', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('folder_id')->nullable();
        $table->string('original_name');
        $table->string('stored_name');
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size_bytes')->default(0);
        $table->unsignedBigInteger('uploaded_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Storage::fake('local');
});

afterEach(function (): void {
    Schema::dropAllTables();
});

function officeControllerTestUser(string $role = 'user'): User
{
    static $sequence = 0;
    $sequence++;

    return User::query()->create([
        'name' => $role === 'admin' ? 'Admin DriveASIX' : 'Pengguna DriveASIX',
        'pn' => sprintf('97%06d', $sequence),
        'password' => 'password',
        'role' => $role,
        'branch_scope' => 'area6',
    ]);
}

function officeControllerTestXlsx(string $value = 'Versi awal'): string
{
    $workbook = new Spreadsheet;
    $workbook->getActiveSheet()
        ->setTitle('Data')
        ->setCellValue('A1', $value);
    $path = tempnam(sys_get_temp_dir(), 'drive-asix-office-controller-');

    if ($path === false) {
        throw new RuntimeException('File sementara XLSX test tidak dapat dibuat.');
    }

    try {
        (new Xlsx($workbook))->save($path);
        $binary = file_get_contents($path);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('XLSX test tidak dapat dibaca.');
        }

        return $binary;
    } finally {
        $workbook->disconnectWorksheets();
        @unlink($path);
    }
}

function officeControllerTestPackage(string $extension): string
{
    if ($extension === 'xlsx') {
        return officeControllerTestXlsx();
    }

    $definitions = [
        'docx' => [
            'entry' => 'word/document.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'content' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Dokumen DriveASIX</w:t></w:r></w:p></w:body></w:document>',
        ],
        'pptx' => [
            'entry' => 'ppt/presentation.xml',
            'content_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml',
            'content' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>',
        ],
    ];
    $definition = $definitions[$extension] ?? null;
    if ($definition === null) {
        throw new InvalidArgumentException('Ekstensi paket test tidak didukung.');
    }

    $path = tempnam(sys_get_temp_dir(), 'drive-asix-office-package-');
    if ($path === false) {
        throw new RuntimeException('File sementara OOXML test tidak dapat dibuat.');
    }

    $archive = new ZipArchive;
    $open = false;

    try {
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Paket OOXML test tidak dapat dibuat.');
        }
        $open = true;
        $archive->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Override PartName="/'.$definition['entry'].'" ContentType="'
            .$definition['content_type'].'"/>'
            .'</Types>'
        );
        $archive->addFromString($definition['entry'], $definition['content']);
        $archive->close();
        $open = false;

        $binary = file_get_contents($path);
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Paket OOXML test tidak dapat dibaca.');
        }

        return $binary;
    } finally {
        if ($open) {
            $archive->close();
        }
        @unlink($path);
    }
}

function officeControllerTestFile(
    string $extension = 'xlsx',
    ?User $uploader = null,
    ?string $binary = null
): DriveAsixFile {
    $binary ??= officeControllerTestPackage($extension);
    $storedName = 'office-controller-'.bin2hex(random_bytes(5)).'.'.$extension;
    Storage::disk('local')->put('drive_asix/'.$storedName, $binary);

    $mime = match ($extension) {
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    };

    return DriveAsixFile::query()->create([
        'folder_id' => null,
        'original_name' => 'Dokumen Uji.'.strtoupper($extension),
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'size_bytes' => strlen($binary),
        'uploaded_by' => $uploader?->getKey(),
    ]);
}

/**
 * @return array{
 *     office: OnlyOfficeEditorService,
 *     jwt: OnlyOfficeJwtService,
 *     revision: string,
 *     session: array<string, mixed>,
 *     callback_token: string
 * }
 */
function officeControllerTestSession(DriveAsixFile $file, User $user): array
{
    $office = app(OnlyOfficeEditorService::class);
    $jwt = app(OnlyOfficeJwtService::class);
    $revision = $office->revision($file);
    $session = $office->openOrReuseSession($file, $revision, $user->getKey());
    $callbackToken = $jwt->issueAccessToken(
        'callback',
        $file->getKey(),
        $session['document_key'],
        $session['access_revision']
    );

    return [
        'office' => $office,
        'jwt' => $jwt,
        'revision' => $revision,
        'session' => $session,
        'callback_token' => $callbackToken,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function officeControllerTestSignedCallback(
    OnlyOfficeJwtService $jwt,
    array $payload
): array {
    return [
        ...$payload,
        'token' => $jwt->sign([
            'payload' => $payload,
            'iat' => time(),
            'exp' => time() + 600,
        ]),
    ];
}

it('menampilkan fallback aman ketika Document Server dinonaktifkan', function (): void {
    Config::set('services.onlyoffice.enabled', false);
    $user = officeControllerTestUser();
    $file = officeControllerTestFile('xlsx', $user);
    Http::fake();

    $this->actingAs($user)
        ->get(route('drive.file.office-editor', $file))
        ->assertOk()
        ->assertViewIs('drive.office-editor')
        ->assertViewHas('available', false)
        ->assertViewHas('fallbackUrl', route('drive.file.editor', $file))
        ->assertSee('Document Server belum dikonfigurasi');

    Http::assertNothingSent();
});

it('membangun konfigurasi editor penuh sesuai tipe dokumen dan perangkat', function (
    string $extension,
    string $expectedDocumentType,
    string $userAgent,
    string $expectedEditorType
): void {
    $user = officeControllerTestUser();
    $file = officeControllerTestFile($extension, $user);
    Http::fake([
        'http://onlyoffice.local/healthcheck' => Http::response('true', 200),
    ]);

    $this->actingAs($user)
        ->withHeader('User-Agent', $userAgent)
        ->get(route('drive.file.office-editor', $file))
        ->assertOk()
        ->assertViewIs('drive.office-editor')
        ->assertViewHas('available', true)
        ->assertViewHas(
            'editorScriptUrl',
            'https://office.example.test/web-apps/apps/api/documents/api.js'
        )
        ->assertViewHas('editorConfig', function (array $config) use (
            $file,
            $expectedDocumentType,
            $expectedEditorType
        ): bool {
            expect($config['type'])->toBe($expectedEditorType)
                ->and($config['documentType'])->toBe($expectedDocumentType)
                ->and($config['document']['fileType'])->toBe($file->extension())
                ->and($config['document']['permissions']['edit'])->toBeTrue()
                ->and($config['editorConfig']['mode'])->toBe('edit')
                ->and($config['editorConfig']['customization']['autosave'])->toBeTrue()
                ->and($config['document']['url'])
                ->toStartWith('https://app.example.test/drive/office/files/')
                ->toContain('/source?access_token=')
                ->and($config['editorConfig']['callbackUrl'])
                ->toStartWith('https://app.example.test/drive/office/files/')
                ->toContain('/callback?access_token=');

            app(OnlyOfficeJwtService::class)->verify($config['token']);

            return true;
        });
})->with([
    'DOCX desktop' => [
        'docx',
        'word',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'desktop',
    ],
    'PPTX desktop' => [
        'pptx',
        'slide',
        'Mozilla/5.0 (X11; Linux x86_64)',
        'desktop',
    ],
    'XLSX desktop' => [
        'xlsx',
        'cell',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'desktop',
    ],
    'XLSX mobile' => [
        'xlsx',
        'cell',
        'Mozilla/5.0 (Linux; Android 15; Mobile)',
        'mobile',
    ],
]);

it('melayani file sumber hanya dengan token sesi yang cocok', function (): void {
    $user = officeControllerTestUser();
    $binary = officeControllerTestXlsx('Sumber privat');
    $file = officeControllerTestFile('xlsx', $user, $binary);
    $context = officeControllerTestSession($file, $user);
    $key = $context['session']['document_key'];
    $sourceToken = $context['jwt']->issueAccessToken(
        'source',
        $file->getKey(),
        $key,
        $context['session']['access_revision']
    );

    $response = $this->get(route('drive.office.source', [
        'file' => $file,
        'documentKey' => $key,
        'access_token' => $sourceToken,
    ]));

    $response->assertOk()
        ->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

    expect($response->baseResponse->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store')
        ->not->toContain('public')
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toBe($binary);

    $this->get(route('drive.office.source', [
        'file' => $file,
        'documentKey' => $key,
        'access_token' => 'token-tidak-valid',
    ]))->assertForbidden();
});

it('menyimpan force-save status 6 lalu final-save status 2 dan menutup sesi', function (): void {
    $user = officeControllerTestUser();
    $initial = officeControllerTestXlsx('Versi awal');
    $forceSaved = officeControllerTestXlsx('Force save');
    $finalSaved = officeControllerTestXlsx('Final save');
    $file = officeControllerTestFile('xlsx', $user, $initial);
    $context = officeControllerTestSession($file, $user);
    $key = $context['session']['document_key'];
    $forceUrl = 'http://onlyoffice.local/cache/force-save.xlsx';
    $finalUrl = 'http://onlyoffice.local/cache/final-save.xlsx';

    Http::fake([
        $forceUrl => Http::response($forceSaved, 200, [
            'Content-Length' => (string) strlen($forceSaved),
        ]),
        $finalUrl => Http::response($finalSaved, 200, [
            'Content-Length' => (string) strlen($finalSaved),
        ]),
    ]);

    $forcePayload = [
        'key' => $key,
        'status' => 6,
        'url' => $forceUrl,
        'filetype' => 'xlsx',
    ];
    $this->postJson(
        route('drive.office.callback', [
            'file' => $file,
            'documentKey' => $key,
            'access_token' => $context['callback_token'],
        ]),
        officeControllerTestSignedCallback($context['jwt'], $forcePayload)
    )->assertOk()->assertExactJson(['error' => 0]);

    $forceSession = $context['office']->readSession($file->getKey(), $key);
    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))
        ->toBe($forceSaved)
        ->and($forceSession['active'])->toBeTrue()
        ->and($forceSession['last_callback_status'])->toBe(6)
        ->and($forceSession['last_revision'])
        ->toBe('sha256:'.hash('sha256', $forceSaved));

    $finalPayload = [
        'key' => $key,
        'status' => 2,
        'url' => $finalUrl,
        'filetype' => 'xlsx',
    ];
    $this->postJson(
        route('drive.office.callback', [
            'file' => $file,
            'documentKey' => $key,
            'access_token' => $context['callback_token'],
        ]),
        officeControllerTestSignedCallback($context['jwt'], $finalPayload)
    )->assertOk()->assertExactJson(['error' => 0]);

    $closedSession = $context['office']->readSession($file->getKey(), $key);
    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))
        ->toBe($finalSaved)
        ->and($closedSession['active'])->toBeFalse()
        ->and($closedSession['last_callback_status'])->toBe(2)
        ->and($closedSession['last_revision'])
        ->toBe('sha256:'.hash('sha256', $finalSaved))
        ->and(Storage::disk('local')->files('drive_asix_versions/'.$file->getKey()))
        ->toHaveCount(2);
});

it('menolak callback bertanda tangan rusak dan URL asing tanpa menimpa file', function (): void {
    $user = officeControllerTestUser();
    $initial = officeControllerTestXlsx('Tidak boleh tertimpa');
    $edited = officeControllerTestXlsx('Payload jahat');
    $file = officeControllerTestFile('xlsx', $user, $initial);
    $context = officeControllerTestSession($file, $user);
    $key = $context['session']['document_key'];
    $trustedUrl = 'http://onlyoffice.local/cache/edited.xlsx';
    Http::fake([$trustedUrl => Http::response($edited)]);

    $payload = [
        'key' => $key,
        'status' => 6,
        'url' => $trustedUrl,
        'filetype' => 'xlsx',
    ];
    $signed = officeControllerTestSignedCallback($context['jwt'], $payload);
    $token = $signed['token'];
    $signed['token'] = substr($token, 0, -1)
        .($token[strlen($token) - 1] === 'a' ? 'b' : 'a');

    $this->postJson(
        route('drive.office.callback', [
            'file' => $file,
            'documentKey' => $key,
            'access_token' => $context['callback_token'],
        ]),
        $signed
    )->assertForbidden()->assertJsonPath('error', 1);

    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))
        ->toBe($initial);

    Http::fake();
    $foreignPayload = [
        'key' => $key,
        'status' => 6,
        'url' => 'http://untrusted.example/cache/edited.xlsx',
        'filetype' => 'xlsx',
    ];
    $this->postJson(
        route('drive.office.callback', [
            'file' => $file,
            'documentKey' => $key,
            'access_token' => $context['callback_token'],
        ]),
        officeControllerTestSignedCallback($context['jwt'], $foreignPayload)
    )->assertStatus(500)->assertJsonPath('error', 1);

    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))
        ->toBe($initial);
    Http::assertNothingSent();
});

it('menerima callback resmi yang hanya membawa token di body', function (): void {
    $user = officeControllerTestUser();
    $file = officeControllerTestFile('docx', $user);
    $context = officeControllerTestSession($file, $user);
    $key = $context['session']['document_key'];

    $bodyToken = $context['jwt']->sign([
        'key' => $key,
        'status' => 4,
        'iat' => time(),
        'exp' => time() + 600,
    ]);

    $this->postJson(
        route('drive.office.callback', [
            'file' => $file,
            'documentKey' => $key,
            'access_token' => $context['callback_token'],
        ]),
        ['token' => $bodyToken]
    )->assertOk()->assertExactJson(['error' => 0]);

    expect($context['office']->readSession($file->getKey(), $key)['active'])
        ->toBeFalse();
});

it('menerima header JWT yang sengaja tidak memuat history berukuran besar', function (): void {
    $user = officeControllerTestUser();
    $initial = officeControllerTestXlsx('Sebelum callback header');
    $edited = officeControllerTestXlsx('Sesudah callback header');
    $file = officeControllerTestFile('xlsx', $user, $initial);
    $context = officeControllerTestSession($file, $user);
    $key = $context['session']['document_key'];
    $downloadUrl = 'http://onlyoffice.local/cache/header-token.xlsx';
    $signedFields = [
        'key' => $key,
        'status' => 2,
        'url' => $downloadUrl,
        'filetype' => 'xlsx',
    ];
    $headerToken = $context['jwt']->sign([
        'payload' => $signedFields,
        'iat' => time(),
        'exp' => time() + 600,
    ]);

    Http::fake([$downloadUrl => Http::response($edited)]);

    $this->withHeader('AuthorizationJwt', 'Bearer '.$headerToken)
        ->postJson(
            route('drive.office.callback', [
                'file' => $file,
                'documentKey' => $key,
                'access_token' => $context['callback_token'],
            ]),
            [
                ...$signedFields,
                'history' => [
                    'changes' => ['large-field-can-be-unsigned'],
                    'serverVersion' => 1,
                ],
                'changesurl' => 'http://onlyoffice.local/cache/changes.zip',
            ]
        )
        ->assertOk()
        ->assertExactJson(['error' => 0]);

    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))
        ->toBe($edited)
        ->and($context['office']->readSession($file->getKey(), $key)['active'])
        ->toBeFalse();
});

it('mengunci save kompatibel delete dan purge selama sesi Office aktif', function (): void {
    $admin = officeControllerTestUser('admin');
    $file = officeControllerTestFile('xlsx', $admin);
    officeControllerTestSession($file, $admin);

    $this->actingAs($admin)
        ->patchJson(route('drive.file.workbook.save', $file), [])
        ->assertStatus(423);

    $this->actingAs($admin)
        ->delete(route('drive.file.delete', $file))
        ->assertStatus(423);
    expect($file->fresh()->trashed())->toBeFalse();

    $file->delete();
    $this->actingAs($admin)
        ->delete(route('drive.file.purge', $file->getKey()))
        ->assertStatus(423);

    expect(DriveAsixFile::onlyTrashed()->find($file->getKey()))->not->toBeNull()
        ->and(Storage::disk('local')->exists('drive_asix/'.$file->stored_name))
        ->toBeTrue();
});

it('membersihkan sidecar sesi Office setelah sesi ditutup dan file dipurge', function (): void {
    $admin = officeControllerTestUser('admin');
    $file = officeControllerTestFile('pptx', $admin);
    $context = officeControllerTestSession($file, $admin);
    $key = $context['session']['document_key'];
    $context['office']->closeSession($file->getKey(), $key);
    $sessionDirectory = 'drive_asix_office_sessions/'.$file->getKey();

    expect(Storage::disk('local')->directoryExists($sessionDirectory))->toBeTrue();

    $file->delete();
    $this->actingAs($admin)
        ->delete(route('drive.file.purge', $file->getKey()))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DriveAsixFile::withTrashed()->find($file->getKey()))->toBeNull()
        ->and(Storage::disk('local')->directoryExists($sessionDirectory))
        ->toBeFalse();
});
