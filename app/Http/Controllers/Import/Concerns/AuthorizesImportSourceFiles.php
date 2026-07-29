<?php

namespace App\Http\Controllers\Import\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

trait AuthorizesImportSourceFiles
{
    protected function createSecureImportDirectory(): string
    {
        $folderName = 'import_' . now()->format('Ymd_His') . '_' . Str::random(5);
        $directory = storage_path('app/imports/' . $folderName);

        File::ensureDirectoryExists($directory, 0750, true);

        return $directory;
    }

    /**
     * @return array{name: string, path: string, extension: string}
     */
    protected function storeImportUpload(UploadedFile $file, string $directory): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedName = Str::uuid()->toString() . ($extension !== '' ? '.' . $extension : '');
        $displayName = $this->sanitizeImportDisplayName($file->getClientOriginalName());

        $file->move($directory, $storedName);

        return [
            'name' => $displayName,
            'path' => $directory . DIRECTORY_SEPARATOR . $storedName,
            'extension' => $extension,
        ];
    }

    /**
     * @return array<int, array{name: string, path: string}>
     */
    protected function extractImportArchive(string $archivePath, string $directory): array
    {
        $extractPath = $directory . DIRECTORY_SEPARATOR . 'extracted';
        File::ensureDirectoryExists($extractPath, 0750, true);

        $binary = trim((string) config(
            'import.security.seven_zip_binary',
            PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\7-Zip\\7z.exe' : '7z'
        ));
        if ($binary === '') {
            throw new RuntimeException('Lokasi aplikasi 7-Zip belum dikonfigurasi.');
        }

        $timeout = max(30, (int) config('import.security.archive_timeout_seconds', 300));
        $this->assertSafeImportArchive($binary, $archivePath, $timeout);

        $process = new Process([
            $binary,
            'x',
            $archivePath,
            '-o' . $extractPath,
            '-y',
            '-r',
            '*.csv',
            '*.CSV',
            '*.txt',
            '*.TXT',
        ]);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Gagal mengekstrak file RAR.');
        }

        $root = realpath($extractPath);
        if ($root === false) {
            throw new RuntimeException('Folder hasil ekstraksi tidak dapat dibaca.');
        }

        $maxFiles = max(1, (int) config('import.security.archive_max_files', 100));
        $maxBytes = max(1, (int) config('import.security.archive_max_expanded_bytes', 8 * 1024 * 1024 * 1024));
        $totalBytes = 0;
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileItem) {
            if (!$fileItem->isFile() || $fileItem->isLink()) {
                continue;
            }

            $resolvedPath = realpath($fileItem->getPathname());
            if ($resolvedPath === false || !$this->pathIsWithin($resolvedPath, $root)) {
                throw new RuntimeException('Arsip berisi path file yang tidak aman.');
            }

            $extension = strtolower((string) $fileItem->getExtension());
            if (!in_array($extension, ['csv', 'txt'], true)) {
                continue;
            }

            $totalBytes += max(0, (int) $fileItem->getSize());
            if ($totalBytes > $maxBytes) {
                throw new RuntimeException('Ukuran hasil ekstraksi melebihi batas keamanan.');
            }

            $files[] = [
                'name' => $this->sanitizeImportDisplayName($fileItem->getFilename()),
                'path' => $resolvedPath,
            ];

            if (count($files) > $maxFiles) {
                throw new RuntimeException('Jumlah file dalam arsip melebihi batas keamanan.');
            }
        }

        if ($files === []) {
            throw new RuntimeException('Arsip tidak berisi file CSV atau TXT yang dapat diproses.');
        }

        return $files;
    }

    private function assertSafeImportArchive(string $binary, string $archivePath, int $timeout): void
    {
        $process = new Process([$binary, 'l', '-slt', $archivePath]);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Struktur file RAR tidak dapat diperiksa.');
        }

        $maxFiles = max(1, (int) config('import.security.archive_max_files', 100));
        $maxBytes = max(1, (int) config('import.security.archive_max_expanded_bytes', 8 * 1024 * 1024 * 1024));
        $records = preg_split('/\R{2,}/', trim($process->getOutput())) ?: [];
        $candidateCount = 0;
        $expandedBytes = 0;

        foreach ($records as $record) {
            $properties = [];
            foreach (preg_split('/\R/', $record) ?: [] as $line) {
                if (!str_contains($line, ' = ')) {
                    continue;
                }

                [$key, $value] = explode(' = ', $line, 2);
                $properties[trim($key)] = trim($value);
            }

            if (isset($properties['Type']) || empty($properties['Path'])) {
                continue;
            }

            $path = str_replace('\\', '/', (string) $properties['Path']);
            $segments = explode('/', $path);
            if (
                str_contains($path, "\0")
                || str_starts_with($path, '/')
                || preg_match('/^[A-Za-z]:\//', $path) === 1
                || in_array('..', $segments, true)
            ) {
                throw new RuntimeException('Arsip berisi path file yang tidak aman.');
            }

            $isFolder = ($properties['Folder'] ?? '-') === '+';
            if ($this->isImportArchiveLink($properties)) {
                throw new RuntimeException('Arsip tidak boleh berisi symbolic link atau hard link.');
            }

            if ($isFolder || !in_array(strtolower((string) pathinfo($path, PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
                continue;
            }

            $candidateCount++;
            $expandedBytes += max(0, (int) ($properties['Size'] ?? 0));
            if ($candidateCount > $maxFiles || $expandedBytes > $maxBytes) {
                throw new RuntimeException('Isi arsip melebihi batas keamanan import.');
            }
        }

        if ($candidateCount === 0) {
            throw new RuntimeException('Arsip tidak berisi file CSV atau TXT yang dapat diproses.');
        }
    }

    /**
     * 7-Zip prints empty link fields for ordinary RAR5 entries, so their
     * presence alone must not classify the archive entry as a link.
     *
     * @param array<string, string> $properties
     */
    protected function isImportArchiveLink(array $properties): bool
    {
        return trim((string) ($properties['Symbolic Link'] ?? '')) !== ''
            || trim((string) ($properties['Hard Link'] ?? '')) !== ''
            || trim((string) ($properties['Copy Link'] ?? '')) !== ''
            || preg_match('/^l/', (string) ($properties['Attributes'] ?? '')) === 1;
    }

    protected function authorizeImportSourceFile(string $requestedPath, array $extensions = ['csv', 'txt']): string
    {
        $resolvedPath = realpath($requestedPath);
        $root = realpath(storage_path('app/imports'));

        if (
            $resolvedPath === false
            || $root === false
            || !is_file($resolvedPath)
            || !$this->pathIsWithin($resolvedPath, $root)
        ) {
            throw ValidationException::withMessages([
                'file_path' => 'Sumber file import tidak valid atau sudah tidak tersedia.',
            ]);
        }

        $allowedPaths = collect(session('import_files', []))
            ->map(static fn ($file): string => is_array($file) ? (string) ($file['path'] ?? '') : (string) $file)
            ->push((string) session('final_import_path', ''))
            ->filter()
            ->map(static fn (string $path): string|false => realpath($path))
            ->filter(static fn (string|false $path): bool => is_string($path))
            ->map(fn (string $path): string => $this->normalizePathForComparison($path))
            ->all();

        if (!in_array($this->normalizePathForComparison($resolvedPath), $allowedPaths, true)) {
            throw ValidationException::withMessages([
                'file_path' => 'File ini bukan bagian dari sesi upload yang aktif.',
            ]);
        }

        $extension = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));
        if (!in_array($extension, $extensions, true)) {
            throw ValidationException::withMessages([
                'file_path' => 'Format sumber file import tidak didukung.',
            ]);
        }

        return $resolvedPath;
    }

    protected function cleanupAuthorizedImportDirectory(string $filePath): void
    {
        $root = realpath(storage_path('app/imports'));
        $resolvedPath = realpath($filePath);

        if ($root === false || $resolvedPath === false || !$this->pathIsWithin($resolvedPath, $root)) {
            return;
        }

        $relativePath = ltrim(substr($resolvedPath, strlen($root)), '\\/');
        $folderName = preg_split('~[\\\\/]~', $relativePath, 2)[0] ?? '';
        if (preg_match('/^import_\d{8}_\d{6}_[A-Za-z0-9]{5}$/', $folderName) !== 1) {
            return;
        }

        $directory = $root . DIRECTORY_SEPARATOR . $folderName;
        if ($this->normalizePathForComparison($directory) === $this->normalizePathForComparison($root)) {
            return;
        }

        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }
    }

    protected function configuredImportUploadMaxKilobytes(): int
    {
        $bytes = max(1, (int) config('import.security.upload_max_bytes', 4 * 1024 * 1024 * 1024));

        return max(1, (int) floor($bytes / 1024));
    }

    private function sanitizeImportDisplayName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';

        return Str::limit(trim($name), 255, '');
    }

    private function pathIsWithin(string $path, string $root): bool
    {
        $normalizedPath = $this->normalizePathForComparison($path);
        $normalizedRoot = rtrim($this->normalizePathForComparison($root), '/');

        return $normalizedPath !== $normalizedRoot
            && str_starts_with($normalizedPath, $normalizedRoot . '/');
    }

    private function normalizePathForComparison(string $path): string
    {
        $normalized = str_replace('\\', '/', rtrim($path, '\\/'));

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }
}
