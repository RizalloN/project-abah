<?php

namespace App\Services\DriveAsix;

use App\Exceptions\DriveAsixOfficeException;
use App\Exceptions\DriveAsixWorkbookException;
use App\Models\DriveAsixFile;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class OnlyOfficeDocumentStorageService
{
    private const DISK = 'local';

    private const BASE_PATH = 'drive_asix';

    private const TEMP_PATH = 'drive_asix_tmp';

    private const VERSION_PATH = 'drive_asix_versions';

    private const VERSION_RETENTION = 10;

    private const DEFAULT_MAX_DOWNLOAD_BYTES = 52_428_800;

    private const MAX_ARCHIVE_ENTRIES = 4_096;

    private const MAX_ARCHIVE_ENTRY_BYTES = 268_435_456;

    private const MAX_UNCOMPRESSED_BYTES = 805_306_368;

    private const MAX_DOWNLOAD_BYTES_HARD_CAP = 268_435_456;

    private const MAX_CONTENT_TYPES_BYTES = 2_097_152;

    private const ZIP_RATIO_INSPECTION_THRESHOLD = 1_048_576;

    private const MAX_ZIP_COMPRESSION_RATIO = 250;

    public function __construct(
        private readonly OnlyOfficeJwtService $jwt,
        private readonly SpreadsheetWorkbookService $workbooks
    ) {}

    /**
     * Fail closed before an editor session is issued. Validation is performed
     * under the same lock used by saves and permanent deletion.
     */
    public function validateEditableSource(DriveAsixFile $file): void
    {
        $extension = $this->editableExtension($file);
        $lock = Cache::lock('drive-asix:workbook:'.$file->getKey(), 600);

        try {
            $lock->block(8, function () use ($file, $extension): void {
                $this->validateExpectedPackage(
                    $this->resolveStoredPath($file),
                    $extension
                );
            });
        } catch (DriveAsixOfficeException $exception) {
            throw $exception;
        } catch (LockTimeoutException $exception) {
            throw new DriveAsixOfficeException(
                'File sedang diproses pengguna lain. Coba buka kembali beberapa detik lagi.',
                previous: $exception
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new DriveAsixOfficeException(
                'File tidak dapat divalidasi untuk editor full-fidelity.',
                previous: $exception
            );
        }
    }

    /**
     * Persist the OOXML file produced by OnlyOffice without exposing DriveASIX
     * storage to an arbitrary callback URL.
     *
     * @return array{
     *     revision: string,
     *     size_bytes: int,
     *     mime_type: string,
     *     sha256: string,
     *     document_key: string,
     *     idempotent: bool,
     *     backup_created: bool
     * }
     */
    public function persistEditedFile(
        DriveAsixFile $file,
        string $downloadUrl,
        string $expectedRevision,
        string $documentKey
    ): array {
        $expectedExtension = $this->editableExtension($file);
        $this->assertRevision($expectedRevision);
        $this->assertDocumentKey($documentKey);
        $this->assertAllowedDownloadUrl($downloadUrl);

        $temporaryPath = $this->temporaryPath($expectedExtension);

        try {
            $this->download($downloadUrl, $temporaryPath, $documentKey);
            $this->validateExpectedPackage($temporaryPath, $expectedExtension);

            return $this->replaceUnderLock(
                $file,
                $temporaryPath,
                $expectedExtension,
                $expectedRevision,
                $documentKey
            );
        } finally {
            if (is_file($temporaryPath) && ! @unlink($temporaryPath)) {
                report(new RuntimeException(
                    'File sementara hasil edit OnlyOffice gagal dibersihkan: '.$temporaryPath
                ));
            }
        }
    }

    private function editableExtension(DriveAsixFile $file): string
    {
        if (! $file->exists || $file->trashed()) {
            throw new DriveAsixOfficeException('File DriveASIX tidak tersedia untuk diedit.');
        }

        if (basename($file->stored_name) !== $file->stored_name) {
            throw new DriveAsixOfficeException('Lokasi file DriveASIX tidak valid.');
        }

        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        if (! in_array($extension, ['docx', 'pptx', 'xlsx'], true)) {
            throw new DriveAsixOfficeException(
                'Editor full-fidelity hanya tersedia untuk file DOCX, PPTX, dan XLSX asli.'
            );
        }

        return $extension;
    }

    private function assertRevision(string $revision): void
    {
        if (preg_match('/^sha256:[a-f0-9]{64}$/D', $revision) !== 1) {
            throw new DriveAsixOfficeException('Versi dasar file tidak valid.');
        }
    }

    private function assertDocumentKey(string $documentKey): void
    {
        if (
            $documentKey === ''
            || strlen($documentKey) > 128
            || preg_match('/^[A-Za-z0-9._=-]+$/D', $documentKey) !== 1
        ) {
            throw new DriveAsixOfficeException('Kunci dokumen OnlyOffice tidak valid.');
        }
    }

    private function assertAllowedDownloadUrl(string $downloadUrl): void
    {
        $downloadOrigin = $this->origin($downloadUrl, true);
        $allowedOrigins = $this->configuredDownloadOrigins();

        foreach ($allowedOrigins as $allowedOrigin) {
            if ($downloadOrigin === $allowedOrigin) {
                return;
            }
        }

        throw new DriveAsixOfficeException(
            'Sumber file hasil edit tidak termasuk origin OnlyOffice yang diizinkan.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function configuredDownloadOrigins(): array
    {
        $configured = config('services.onlyoffice.allowed_download_origins', []);
        $values = is_array($configured)
            ? $configured
            : preg_split('/[\s,;]+/', (string) $configured, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($values)) {
            $values = [];
        }

        $values[] = config('services.onlyoffice.internal_url');
        $values[] = config('services.onlyoffice.public_url');

        $origins = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            try {
                $origins[] = $this->origin($value, false);
            } catch (DriveAsixOfficeException $exception) {
                throw new DriveAsixOfficeException(
                    'Konfigurasi origin OnlyOffice tidak valid.',
                    previous: $exception
                );
            }
        }

        $origins = array_values(array_unique($origins));
        if ($origins === []) {
            throw new DriveAsixOfficeException(
                'Origin unduhan OnlyOffice belum dikonfigurasi.'
            );
        }

        return $origins;
    }

    private function origin(string $url, bool $isDownloadUrl): string
    {
        if (
            $url === ''
            || preg_match('/[\x00-\x20\x7f]/', $url) === 1
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            throw new DriveAsixOfficeException('URL hasil edit OnlyOffice tidak valid.');
        }

        $parts = parse_url($url);
        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new DriveAsixOfficeException('URL hasil edit OnlyOffice tidak aman.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if (
            ! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || str_contains($host, '%')
        ) {
            throw new DriveAsixOfficeException('Origin hasil edit OnlyOffice tidak valid.');
        }

        if ($isDownloadUrl && (! isset($parts['path']) || $parts['path'] === '')) {
            throw new DriveAsixOfficeException('Path file hasil edit OnlyOffice tidak valid.');
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65_535) {
            throw new DriveAsixOfficeException('Port OnlyOffice tidak valid.');
        }

        return $scheme.'://'.$host.':'.$port;
    }

    private function temporaryPath(string $extension): string
    {
        $disk = Storage::disk(self::DISK);
        if (! $disk->makeDirectory(self::TEMP_PATH)
            && ! $disk->directoryExists(self::TEMP_PATH)) {
            throw new DriveAsixOfficeException(
                'Direktori sementara DriveASIX tidak dapat disiapkan.'
            );
        }

        return $disk->path(
            self::TEMP_PATH.'/onlyoffice-'.Str::uuid().'.'.$extension
        );
    }

    private function download(string $downloadUrl, string $temporaryPath, string $documentKey): void
    {
        $maximumBytes = $this->maximumDownloadBytes();
        $timeout = max(5, min(300, (int) config('services.onlyoffice.timeout_seconds', 120)));
        $verifyTls = filter_var(
            config('services.onlyoffice.verify_tls', true),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
        $downloadExceeded = false;

        $token = $this->jwt->sign([
            'payload' => [
                'url' => $downloadUrl,
            ],
            'iat' => time(),
            'exp' => time() + $timeout + 30,
        ]);
        $jwtHeader = $this->jwt->jwtHeaderName();

        try {
            $response = Http::connectTimeout(min(15, $timeout))
                ->timeout($timeout)
                ->withHeaders([
                    $jwtHeader => 'Bearer '.$token,
                    'Accept' => 'application/octet-stream',
                    'User-Agent' => 'DriveASIX-OnlyOffice/1.0',
                ])
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => $verifyTls,
                    'sink' => $temporaryPath,
                    'on_headers' => function ($response) use (
                        $maximumBytes,
                        &$downloadExceeded
                    ): void {
                        $contentLength = trim($response->getHeaderLine('Content-Length'));
                        if (
                            $contentLength !== ''
                            && ctype_digit($contentLength)
                            && (int) $contentLength > $maximumBytes
                        ) {
                            $downloadExceeded = true;
                            throw new RuntimeException('Unduhan OnlyOffice melebihi batas ukuran.');
                        }
                    },
                    'progress' => function (
                        $downloadTotal,
                        $downloadedBytes
                    ) use ($maximumBytes, &$downloadExceeded): void {
                        if (
                            (float) $downloadTotal > $maximumBytes
                            || (float) $downloadedBytes > $maximumBytes
                        ) {
                            $downloadExceeded = true;
                            throw new RuntimeException('Unduhan OnlyOffice melebihi batas ukuran.');
                        }
                    },
                ])
                ->get($downloadUrl);
        } catch (Throwable $exception) {
            if ($downloadExceeded) {
                throw new DriveAsixOfficeException(
                    'File hasil edit melebihi batas unduhan DriveASIX.',
                    previous: $exception
                );
            }

            if ($exception instanceof DriveAsixOfficeException) {
                throw $exception;
            }

            if (! $exception instanceof ConnectionException) {
                report($exception);
            }

            throw new DriveAsixOfficeException(
                'File hasil edit tidak dapat diunduh dari OnlyOffice.',
                previous: $exception
            );
        }

        if ($response->status() >= 300 && $response->status() < 400) {
            throw new DriveAsixOfficeException(
                'Redirect unduhan OnlyOffice ditolak untuk mencegah perpindahan origin.'
            );
        }
        if (! $response->successful()) {
            throw new DriveAsixOfficeException(
                'OnlyOffice gagal menyediakan file hasil edit (HTTP '.$response->status().').'
            );
        }

        // Laravel's HTTP fake does not run Guzzle's sink handler. Keeping this
        // bounded fallback makes the service testable without weakening the
        // streamed production download path.
        clearstatcache(true, $temporaryPath);
        if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            $body = $response->body();
            if ($body === '' || strlen($body) > $maximumBytes) {
                throw new DriveAsixOfficeException(
                    $body === ''
                        ? 'File hasil edit OnlyOffice kosong.'
                        : 'File hasil edit melebihi batas unduhan DriveASIX.'
                );
            }

            if (file_put_contents($temporaryPath, $body, LOCK_EX) !== strlen($body)) {
                throw new DriveAsixOfficeException(
                    'File hasil edit gagal ditulis ke penyimpanan sementara.'
                );
            }
        }

        clearstatcache(true, $temporaryPath);
        $size = filesize($temporaryPath);
        if ($size === false || $size <= 0) {
            throw new DriveAsixOfficeException('File hasil edit OnlyOffice kosong.');
        }
        if ($size > $maximumBytes) {
            throw new DriveAsixOfficeException(
                'File hasil edit melebihi batas unduhan DriveASIX.'
            );
        }
    }

    private function maximumDownloadBytes(): int
    {
        $configured = filter_var(
            config('services.onlyoffice.max_download_bytes', self::DEFAULT_MAX_DOWNLOAD_BYTES),
            FILTER_VALIDATE_INT
        );

        return $configured !== false && $configured > 0
            ? min($configured, self::MAX_DOWNLOAD_BYTES_HARD_CAP)
            : self::DEFAULT_MAX_DOWNLOAD_BYTES;
    }

    private function validateExpectedPackage(string $path, string $expectedExtension): void
    {
        $this->validateOoxmlArchive($path, $expectedExtension);

        if ($expectedExtension !== 'xlsx') {
            return;
        }

        try {
            $detected = $this->workbooks->validateUploadedWorkbook($path, true);
        } catch (DriveAsixWorkbookException $exception) {
            throw new DriveAsixOfficeException(
                'Workbook hasil edit OnlyOffice tidak valid atau memuat konten yang dilarang.',
                previous: $exception
            );
        }

        if ($detected !== 'xlsx') {
            throw new DriveAsixOfficeException(
                'OnlyOffice mengembalikan workbook dengan format yang berbeda.'
            );
        }
    }

    private function validateOoxmlArchive(string $path, string $expectedExtension): void
    {
        if (! is_file($path) || ! is_readable($path) || (filesize($path) ?: 0) <= 0) {
            throw new DriveAsixOfficeException('File hasil edit tidak dapat dibaca.');
        }

        $archive = new ZipArchive;
        $openResult = $archive->open($path, ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new DriveAsixOfficeException(
                'OnlyOffice mengembalikan file yang bukan paket OOXML valid.'
            );
        }

        try {
            if ($archive->numFiles < 1 || $archive->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new DriveAsixOfficeException(
                    'Jumlah bagian paket Office melampaui batas aman.'
                );
            }

            $totalUncompressed = 0;
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                if (! is_array($stat) || ! isset($stat['name'])) {
                    throw new DriveAsixOfficeException(
                        'Struktur paket Office tidak dapat diverifikasi.'
                    );
                }

                $entryName = str_replace('\\', '/', (string) $stat['name']);
                $this->assertSafeArchiveEntry($entryName);
                $this->assertNoMacroEntry($entryName);

                $entrySize = (int) ($stat['size'] ?? -1);
                $compressedSize = (int) ($stat['comp_size'] ?? -1);
                if (
                    $entrySize < 0
                    || $compressedSize < 0
                    || $entrySize > self::MAX_ARCHIVE_ENTRY_BYTES
                ) {
                    throw new DriveAsixOfficeException(
                        'Bagian paket Office melampaui batas aman.'
                    );
                }

                $totalUncompressed += $entrySize;
                if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new DriveAsixOfficeException(
                        'Ukuran ekstraksi paket Office melampaui batas aman.'
                    );
                }

                if (
                    $entrySize >= self::ZIP_RATIO_INSPECTION_THRESHOLD
                    && ($compressedSize === 0
                        || $entrySize > $compressedSize * self::MAX_ZIP_COMPRESSION_RATIO)
                ) {
                    throw new DriveAsixOfficeException(
                        'Paket Office terindikasi sebagai ZIP bomb.'
                    );
                }

                if (
                    (str_ends_with(strtolower($entryName), '.xml')
                        || str_ends_with(strtolower($entryName), '.rels'))
                    && $entrySize > 0
                ) {
                    $xmlPrefix = $archive->getFromIndex(
                        $index,
                        min($entrySize, 16_384)
                    );
                    if (
                        ! is_string($xmlPrefix)
                        || stripos($xmlPrefix, '<!DOCTYPE') !== false
                        || stripos($xmlPrefix, '<!ENTITY') !== false
                    ) {
                        throw new DriveAsixOfficeException(
                            'Paket Office memuat deklarasi XML yang tidak aman.'
                        );
                    }
                }
            }

            $contentTypes = $this->contentTypes($archive);
            $this->assertExpectedPackageType(
                $archive,
                $contentTypes,
                $expectedExtension
            );
        } finally {
            $archive->close();
        }
    }

    private function assertSafeArchiveEntry(string $entryName): void
    {
        $segments = explode('/', $entryName);
        if (
            $entryName === ''
            || str_contains($entryName, "\0")
            || str_starts_with($entryName, '/')
            || preg_match('/^[A-Za-z]:\//', $entryName) === 1
            || in_array('..', $segments, true)
        ) {
            throw new DriveAsixOfficeException(
                'Paket Office memuat path arsip yang tidak aman.'
            );
        }
    }

    private function assertNoMacroEntry(string $entryName): void
    {
        $name = strtolower($entryName);
        if (
            str_contains($name, 'vbaproject.bin')
            || str_contains($name, 'vbaprojectsignature.bin')
            || str_contains($name, 'vbadata.xml')
            || str_contains($name, '/_vba_project')
            || str_contains($name, '/macros/')
        ) {
            throw new DriveAsixOfficeException(
                'File hasil edit memuat macro/VBA yang tidak diizinkan.'
            );
        }
    }

    private function contentTypes(ZipArchive $archive): string
    {
        $stat = $archive->statName('[Content_Types].xml');
        if (
            ! is_array($stat)
            || (int) ($stat['size'] ?? 0) <= 0
            || (int) ($stat['size'] ?? 0) > self::MAX_CONTENT_TYPES_BYTES
        ) {
            throw new DriveAsixOfficeException(
                'Metadata tipe paket Office tidak tersedia atau terlalu besar.'
            );
        }

        $contentTypes = $archive->getFromName(
            '[Content_Types].xml',
            self::MAX_CONTENT_TYPES_BYTES + 1
        );
        if (! is_string($contentTypes) || $contentTypes === '') {
            throw new DriveAsixOfficeException(
                'Metadata tipe paket Office tidak dapat dibaca.'
            );
        }

        $lowerContentTypes = strtolower($contentTypes);
        if (
            str_contains($lowerContentTypes, '<!doctype')
            || str_contains($lowerContentTypes, '<!entity')
            || str_contains($lowerContentTypes, 'macroenabled')
            || str_contains($lowerContentTypes, 'vbaproject')
            || str_contains($lowerContentTypes, 'vba-project')
        ) {
            throw new DriveAsixOfficeException(
                'Metadata paket Office memuat macro/VBA atau XML yang tidak aman.'
            );
        }

        return $lowerContentTypes;
    }

    private function assertExpectedPackageType(
        ZipArchive $archive,
        string $contentTypes,
        string $expectedExtension
    ): void {
        $requirements = [
            'docx' => [
                'entry' => 'word/document.xml',
                'content_type' => 'wordprocessingml.document.main+xml',
            ],
            'pptx' => [
                'entry' => 'ppt/presentation.xml',
                'content_type' => 'presentationml.presentation.main+xml',
            ],
            'xlsx' => [
                'entry' => 'xl/workbook.xml',
                'content_type' => 'spreadsheetml.sheet.main+xml',
            ],
        ];
        $required = $requirements[$expectedExtension];

        if (
            $archive->locateName($required['entry']) === false
            || ! str_contains($contentTypes, $required['content_type'])
        ) {
            throw new DriveAsixOfficeException(
                'Format file hasil edit tidak sesuai dengan file DriveASIX.'
            );
        }

        foreach ($requirements as $extension => $signature) {
            if (
                $extension !== $expectedExtension
                && $archive->locateName($signature['entry']) !== false
            ) {
                throw new DriveAsixOfficeException(
                    'Paket hasil edit memiliki format Office yang ambigu.'
                );
            }
        }
    }

    /**
     * @return array{
     *     revision: string,
     *     size_bytes: int,
     *     mime_type: string,
     *     sha256: string,
     *     document_key: string,
     *     idempotent: bool,
     *     backup_created: bool
     * }
     */
    private function replaceUnderLock(
        DriveAsixFile $file,
        string $temporaryPath,
        string $extension,
        string $expectedRevision,
        string $documentKey
    ): array {
        $lock = Cache::lock('drive-asix:workbook:'.$file->getKey(), 600);

        try {
            return $lock->block(8, function () use (
                $file,
                $temporaryPath,
                $extension,
                $expectedRevision,
                $documentKey
            ): array {
                $targetPath = $this->resolveStoredPath($file);
                $currentRevision = $this->revision($targetPath);
                $downloadHash = $this->hash($temporaryPath);
                if (hash_equals(substr($currentRevision, 7), $downloadHash)) {
                    return $this->result(
                        $targetPath,
                        $extension,
                        $documentKey,
                        true,
                        false
                    );
                }

                if (! hash_equals($expectedRevision, $currentRevision)) {
                    throw new DriveAsixOfficeException(
                        'File sudah berubah sejak editor dibuka. Muat ulang dokumen sebelum menyimpan.'
                    );
                }

                $this->validateExpectedPackage($targetPath, $extension);
                $backupPath = $this->storeVersion(
                    $file,
                    $targetPath,
                    $extension,
                    $currentRevision
                );
                $replaced = false;

                try {
                    // Recheck after the backup stream is complete so an
                    // out-of-band write cannot be silently overwritten.
                    $latestRevision = $this->revision($targetPath);
                    if (! hash_equals($currentRevision, $latestRevision)) {
                        throw new DriveAsixOfficeException(
                            'File berubah saat versi cadangan dibuat. Penyimpanan dibatalkan.'
                        );
                    }

                    $contents = file_get_contents($temporaryPath);
                    if (! is_string($contents) || $contents === '') {
                        throw new DriveAsixOfficeException(
                            'File hasil edit tidak dapat dibaca untuk penyimpanan.'
                        );
                    }

                    (new Filesystem)->replace($targetPath, $contents);
                    $replaced = true;
                    clearstatcache(true, $targetPath);

                    if (! hash_equals($downloadHash, $this->hash($targetPath))) {
                        throw new DriveAsixOfficeException(
                            'Verifikasi hasil tulis gagal; file asli akan dipulihkan.'
                        );
                    }
                    $this->validateExpectedPackage($targetPath, $extension);

                    $size = filesize($targetPath);
                    if ($size === false || $size <= 0) {
                        throw new DriveAsixOfficeException(
                            'Ukuran file hasil simpan tidak valid.'
                        );
                    }
                    $mime = $this->mimeType($extension);
                    $hash = $this->hash($targetPath);

                    $file->getConnection()->transaction(function () use (
                        $file,
                        $size,
                        $mime
                    ): void {
                        /** @var DriveAsixFile|null $record */
                        $record = $file->newQuery()
                            ->withTrashed()
                            ->whereKey($file->getKey())
                            ->lockForUpdate()
                            ->first();

                        if (
                            $record === null
                            || $record->trashed()
                            || $record->stored_name !== $file->stored_name
                        ) {
                            throw new DriveAsixOfficeException(
                                'Metadata file berubah saat hasil edit disimpan.'
                            );
                        }

                        $record->forceFill([
                            'size_bytes' => $size,
                            'mime_type' => $mime,
                        ])->saveOrFail();
                    });

                    return $this->result(
                        $targetPath,
                        $extension,
                        $documentKey,
                        false,
                        true,
                        $hash,
                        (int) $size,
                        $mime
                    );
                } catch (Throwable $exception) {
                    if ($replaced) {
                        $this->restoreVersion(
                            $targetPath,
                            $backupPath,
                            $currentRevision,
                            $exception
                        );
                    }

                    if ($exception instanceof DriveAsixOfficeException) {
                        throw $exception;
                    }

                    report($exception);
                    throw new DriveAsixOfficeException(
                        'Hasil edit gagal disimpan. File asli telah dipertahankan.',
                        previous: $exception
                    );
                }
            });
        } catch (DriveAsixOfficeException $exception) {
            throw $exception;
        } catch (LockTimeoutException $exception) {
            throw new DriveAsixOfficeException(
                'File sedang disimpan pengguna lain. Coba kembali beberapa detik lagi.',
                previous: $exception
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new DriveAsixOfficeException(
                'Hasil edit tidak dapat diproses dengan aman oleh DriveASIX.',
                previous: $exception
            );
        }
    }

    private function resolveStoredPath(DriveAsixFile $file): string
    {
        if (basename($file->stored_name) !== $file->stored_name) {
            throw new DriveAsixOfficeException('Lokasi file DriveASIX tidak valid.');
        }

        $disk = Storage::disk(self::DISK);
        $relativePath = self::BASE_PATH.'/'.$file->stored_name;
        if (! $disk->exists($relativePath)) {
            throw new DriveAsixOfficeException(
                'File asli tidak ditemukan di penyimpanan DriveASIX.'
            );
        }

        $path = $disk->path($relativePath);
        if (! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new DriveAsixOfficeException(
                'File asli tidak dapat dibaca dengan aman.'
            );
        }

        $base = realpath($disk->path(self::BASE_PATH));
        $resolved = realpath($path);
        if (
            $base === false
            || $resolved === false
            || ! str_starts_with(
                strtolower(str_replace('\\', '/', $resolved)),
                rtrim(strtolower(str_replace('\\', '/', $base)), '/').'/'
            )
        ) {
            throw new DriveAsixOfficeException(
                'Lokasi file berada di luar penyimpanan DriveASIX.'
            );
        }

        return $resolved;
    }

    private function revision(string $path): string
    {
        return 'sha256:'.$this->hash($path);
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if (! is_string($hash) || $hash === '') {
            throw new DriveAsixOfficeException('Hash file DriveASIX tidak dapat dihitung.');
        }

        return $hash;
    }

    private function storeVersion(
        DriveAsixFile $file,
        string $sourcePath,
        string $extension,
        string $revision
    ): string {
        $disk = Storage::disk(self::DISK);
        $directory = self::VERSION_PATH.'/'.$file->getKey();
        if (! $disk->makeDirectory($directory) && ! $disk->directoryExists($directory)) {
            throw new DriveAsixOfficeException(
                'Direktori versi DriveASIX tidak dapat disiapkan.'
            );
        }

        $name = now()->format('Ymd_His_u').'_'
            .substr(str_replace('sha256:', '', $revision), 0, 12)
            .'_onlyoffice.'.$extension;
        $versionPath = $directory.'/'.$name;
        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new DriveAsixOfficeException(
                'File asli tidak dapat dibaca untuk membuat versi cadangan.'
            );
        }

        try {
            if (! $disk->put($versionPath, $stream)) {
                throw new DriveAsixOfficeException(
                    'Versi cadangan gagal dibuat; file asli tidak diubah.'
                );
            }
        } finally {
            fclose($stream);
        }

        if (! $disk->exists($versionPath)
            || ! hash_equals($this->hash($sourcePath), $this->hash($disk->path($versionPath)))) {
            $disk->delete($versionPath);
            throw new DriveAsixOfficeException(
                'Verifikasi versi cadangan gagal; file asli tidak diubah.'
            );
        }

        $versions = collect($disk->files($directory))->sortDesc()->values();
        foreach ($versions->slice(self::VERSION_RETENTION) as $oldVersion) {
            if (! $disk->delete($oldVersion) && $disk->exists($oldVersion)) {
                report(new RuntimeException(
                    'Versi lama DriveASIX gagal dibersihkan: '.$oldVersion
                ));
            }
        }

        return $versionPath;
    }

    private function restoreVersion(
        string $targetPath,
        string $backupPath,
        string $expectedRevision,
        Throwable $originalException
    ): void {
        try {
            $disk = Storage::disk(self::DISK);
            if (! $disk->exists($backupPath)) {
                throw new RuntimeException('Versi cadangan tidak ditemukan.');
            }

            $backup = $disk->get($backupPath);
            if ($backup === '') {
                throw new RuntimeException('Versi cadangan kosong.');
            }

            (new Filesystem)->replace($targetPath, $backup);
            clearstatcache(true, $targetPath);

            if (! hash_equals($expectedRevision, $this->revision($targetPath))) {
                throw new RuntimeException('Hash hasil pemulihan tidak sesuai file asli.');
            }
        } catch (Throwable $restoreException) {
            report($originalException);
            report($restoreException);

            throw new DriveAsixOfficeException(
                'Penyimpanan gagal dan pemulihan otomatis file asli juga gagal. Versi cadangan tetap tersedia untuk pemulihan manual.',
                previous: $restoreException
            );
        }
    }

    private function mimeType(string $extension): string
    {
        return match ($extension) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    /**
     * @return array{
     *     revision: string,
     *     size_bytes: int,
     *     mime_type: string,
     *     sha256: string,
     *     document_key: string,
     *     idempotent: bool,
     *     backup_created: bool
     * }
     */
    private function result(
        string $path,
        string $extension,
        string $documentKey,
        bool $idempotent,
        bool $backupCreated,
        ?string $knownHash = null,
        ?int $knownSize = null,
        ?string $knownMime = null
    ): array {
        $hash = $knownHash ?? $this->hash($path);
        $size = $knownSize ?? filesize($path);
        if ($size === false || $size <= 0) {
            throw new DriveAsixOfficeException(
                'Ukuran file hasil simpan tidak valid.'
            );
        }

        return [
            'revision' => 'sha256:'.$hash,
            'size_bytes' => (int) $size,
            'mime_type' => $knownMime ?? $this->mimeType($extension),
            'sha256' => $hash,
            'document_key' => $documentKey,
            'idempotent' => $idempotent,
            'backup_created' => $backupCreated,
        ];
    }
}
