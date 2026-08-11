<?php

use App\Exceptions\DriveAsixOfficeException;
use App\Models\DriveAsixFile;
use App\Services\DriveAsix\OnlyOfficeDocumentStorageService;
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
    Config::set('services.onlyoffice.jwt_secret', str_repeat('s', 64));
    Config::set('services.onlyoffice.jwt_header', 'AuthorizationJwt');
    Config::set('services.onlyoffice.internal_url', 'http://onlyoffice.local');
    Config::set('services.onlyoffice.public_url', 'https://office.example.test');
    Config::set('services.onlyoffice.allowed_download_origins', []);
    Config::set('services.onlyoffice.max_download_bytes', 5_242_880);
    Config::set('services.onlyoffice.timeout_seconds', 30);
    Config::set('services.onlyoffice.verify_tls', true);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    Schema::dropAllTables();

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

function onlyOfficeStorageWorkbook(string $value): string
{
    $workbook = new Spreadsheet;
    $workbook->getActiveSheet()->setCellValue('A1', $value);
    $path = tempnam(sys_get_temp_dir(), 'onlyoffice-storage-test-');

    if ($path === false) {
        throw new RuntimeException('File sementara test tidak dapat dibuat.');
    }

    try {
        (new Xlsx($workbook))->save($path);
        $binary = file_get_contents($path);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Workbook test tidak dapat dibaca.');
        }

        return $binary;
    } finally {
        $workbook->disconnectWorksheets();
        @unlink($path);
    }
}

function onlyOfficeStoragePackage(string $extension, string $text): string
{
    $path = tempnam(sys_get_temp_dir(), 'onlyoffice-package-test-');
    if ($path === false) {
        throw new RuntimeException('File paket test tidak dapat dibuat.');
    }

    $archive = new ZipArchive;
    $opened = false;

    try {
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Paket Office test tidak dapat dibuat.');
        }
        $opened = true;

        if ($extension === 'docx') {
            $archive->addFromString(
                '[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Override PartName="/word/document.xml" '
                .'ContentType="application/vnd.openxmlformats-officedocument.'
                .'wordprocessingml.document.main+xml"/></Types>'
            );
            $archive->addFromString(
                'word/document.xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                .'<w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1)
                .'</w:t></w:r></w:p></w:body></w:document>'
            );
        } elseif ($extension === 'pptx') {
            $archive->addFromString(
                '[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Override PartName="/ppt/presentation.xml" '
                .'ContentType="application/vnd.openxmlformats-officedocument.'
                .'presentationml.presentation.main+xml"/></Types>'
            );
            $archive->addFromString(
                'ppt/presentation.xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
                .'<p:sldIdLst/><!--'.htmlspecialchars($text, ENT_XML1)
                .'--></p:presentation>'
            );
        } else {
            throw new InvalidArgumentException('Ekstensi paket test tidak didukung.');
        }

        $archive->close();
        $opened = false;
        $binary = file_get_contents($path);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Paket Office test tidak dapat dibaca.');
        }

        return $binary;
    } finally {
        if ($opened) {
            $archive->close();
        }
        @unlink($path);
    }
}

function onlyOfficeStorageFile(
    string $binary,
    string $extension = 'xlsx'
): DriveAsixFile {
    $storedName = 'onlyoffice-'.bin2hex(random_bytes(5)).'.'.$extension;
    Storage::disk('local')->put('drive_asix/'.$storedName, $binary);

    return DriveAsixFile::query()->create([
        'original_name' => 'Rekap.'.$extension,
        'stored_name' => $storedName,
        'mime_type' => match ($extension) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        },
        'size_bytes' => strlen($binary),
    ]);
}

it('menyimpan hasil OnlyOffice secara atomik dengan versi dan metadata baru', function (): void {
    $before = onlyOfficeStorageWorkbook('Sebelum');
    $after = onlyOfficeStorageWorkbook('Sesudah');
    $file = onlyOfficeStorageFile($before);
    $downloadUrl = 'http://onlyoffice.local/cache/output.xlsx';

    Http::fake([
        $downloadUrl => Http::response($after, 200, [
            'Content-Length' => (string) strlen($after),
        ]),
    ]);

    $result = app(OnlyOfficeDocumentStorageService::class)->persistEditedFile(
        $file,
        $downloadUrl,
        'sha256:'.hash('sha256', $before),
        'document-key-1'
    );

    $file->refresh();
    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))->toBe($after)
        ->and($result['revision'])->toBe('sha256:'.hash('sha256', $after))
        ->and($result['sha256'])->toBe(hash('sha256', $after))
        ->and($result['size_bytes'])->toBe(strlen($after))
        ->and($result['idempotent'])->toBeFalse()
        ->and($result['backup_created'])->toBeTrue()
        ->and($file->size_bytes)->toBe(strlen($after))
        ->and(Storage::disk('local')->files('drive_asix_versions/'.$file->getKey()))
        ->toHaveCount(1);

    Http::assertSent(fn ($request): bool => $request->url() === $downloadUrl
        && str_starts_with($request->header('AuthorizationJwt')[0] ?? '', 'Bearer '));
});

it('menganggap callback ulang idempotent hanya saat byte hasil sudah tersimpan', function (): void {
    $old = onlyOfficeStorageWorkbook('Versi lama');
    $current = onlyOfficeStorageWorkbook('Sudah tersimpan');
    $file = onlyOfficeStorageFile($current);
    $downloadUrl = 'http://onlyoffice.local/cache/retry.xlsx';

    Http::fake([$downloadUrl => Http::response($current)]);

    $result = app(OnlyOfficeDocumentStorageService::class)->persistEditedFile(
        $file,
        $downloadUrl,
        'sha256:'.hash('sha256', $old),
        'document-key-retry'
    );

    expect($result['idempotent'])->toBeTrue()
        ->and($result['backup_created'])->toBeFalse()
        ->and($result['revision'])->toBe('sha256:'.hash('sha256', $current))
        ->and(Storage::disk('local')->directoryExists(
            'drive_asix_versions/'.$file->getKey()
        ))->toBeFalse();
});

it('menolak revisi stale berbeda dan origin di luar allowlist tanpa overwrite', function (): void {
    $current = onlyOfficeStorageWorkbook('Current');
    $different = onlyOfficeStorageWorkbook('Different');
    $file = onlyOfficeStorageFile($current);
    $service = app(OnlyOfficeDocumentStorageService::class);
    $allowedUrl = 'http://onlyoffice.local/cache/different.xlsx';

    Http::fake([$allowedUrl => Http::response($different)]);

    expect(fn () => $service->persistEditedFile(
        $file,
        $allowedUrl,
        'sha256:'.str_repeat('0', 64),
        'document-key-stale'
    ))->toThrow(DriveAsixOfficeException::class);

    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))
        ->toBe($current);

    Http::fake();
    expect(fn () => $service->persistEditedFile(
        $file,
        'http://untrusted.example/cache/output.xlsx',
        'sha256:'.hash('sha256', $current),
        'document-key-origin'
    ))->toThrow(DriveAsixOfficeException::class);

    Http::assertNothingSent();
});

it('memvalidasi dan menyimpan paket Office full-fidelity selain workbook', function (
    string $extension
): void {
    $before = onlyOfficeStoragePackage($extension, 'Sebelum');
    $after = onlyOfficeStoragePackage($extension, 'Sesudah');
    $file = onlyOfficeStorageFile($before, $extension);
    $downloadUrl = 'http://onlyoffice.local/cache/output.'.$extension;
    $service = app(OnlyOfficeDocumentStorageService::class);

    $service->validateEditableSource($file);
    Http::fake([$downloadUrl => Http::response($after)]);

    $result = $service->persistEditedFile(
        $file,
        $downloadUrl,
        'sha256:'.hash('sha256', $before),
        'document-key-'.$extension
    );

    expect(Storage::disk('local')->get('drive_asix/'.$file->stored_name))
        ->toBe($after)
        ->and($result['revision'])->toBe('sha256:'.hash('sha256', $after))
        ->and($result['mime_type'])->toBe(match ($extension) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        })
        ->and($result['backup_created'])->toBeTrue();
})->with(['docx', 'pptx']);

it('menolak workbook OOXML yang masih bernama XLS dari editor full-fidelity', function (): void {
    $binary = onlyOfficeStorageWorkbook('Misnamed');
    $file = onlyOfficeStorageFile($binary);
    $file->forceFill(['original_name' => 'Rekap.xls'])->save();
    Http::fake();

    expect(fn () => app(OnlyOfficeDocumentStorageService::class)
        ->validateEditableSource($file->fresh()))
        ->toThrow(DriveAsixOfficeException::class);

    Http::assertNothingSent();
});

it('memberi ruang paket XLSX besar tanpa melemahkan hard cap unduhan', function (): void {
    $service = new ReflectionClass(OnlyOfficeDocumentStorageService::class);

    expect($service->getConstant('MAX_ARCHIVE_ENTRY_BYTES'))
        ->toBe(268_435_456)
        ->and($service->getConstant('MAX_UNCOMPRESSED_BYTES'))
        ->toBe(805_306_368)
        ->and($service->getConstant('MAX_DOWNLOAD_BYTES_HARD_CAP'))
        ->toBe(268_435_456);
});
