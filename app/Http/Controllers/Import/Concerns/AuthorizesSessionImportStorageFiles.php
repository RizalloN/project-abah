<?php

namespace App\Http\Controllers\Import\Concerns;

use Illuminate\Support\Facades\Storage;

trait AuthorizesSessionImportStorageFiles
{
    /**
     * @param  array<int, string>  $directories
     * @param  array<int, string>  $extensions
     * @return array{0: string, 1: string}
     */
    protected function authorizeSessionImportStorageFile(
        ?string $requestedPath,
        string $sessionKey,
        array $directories,
        array $extensions
    ): array {
        $rawSessionPath = trim((string) session($sessionKey, ''));
        $rawRequestedPath = trim((string) ($requestedPath ?: $rawSessionPath));

        abort_if(
            $this->isAbsoluteImportStoragePath($rawSessionPath)
                || $this->isAbsoluteImportStoragePath($rawRequestedPath),
            403,
            'Lokasi file import tidak valid.'
        );

        $sessionPath = $this->normalizeImportStoragePath($rawSessionPath);
        $requestedPath = $this->normalizeImportStoragePath($rawRequestedPath);

        abort_if(
            $sessionPath === ''
                || $requestedPath === ''
                || !hash_equals($sessionPath, $requestedPath),
            403,
            'File import tidak sesuai dengan sesi upload yang aktif.'
        );

        abort_if(
            str_contains($requestedPath, "\0")
                || in_array('..', explode('/', $requestedPath), true),
            403,
            'Lokasi file import tidak valid.'
        );

        $extension = strtolower((string) pathinfo($requestedPath, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, $extensions, true), 403, 'Format file import tidak diizinkan.');

        $resolvedPath = null;
        foreach ($this->importStoragePathCandidates($requestedPath) as $candidate) {
            $realPath = realpath($candidate);
            if ($realPath !== false && is_file($realPath)) {
                $resolvedPath = $realPath;
                break;
            }
        }

        abort_if($resolvedPath === null, 404, 'File import tidak ditemukan.');

        $insideAllowedDirectory = false;
        foreach ($directories as $directory) {
            foreach ($this->importStoragePathCandidates(trim($directory, '/\\')) as $rootCandidate) {
                $root = realpath($rootCandidate);
                if ($root !== false && $this->importPathIsWithin($resolvedPath, $root)) {
                    $insideAllowedDirectory = true;
                    break 2;
                }
            }
        }

        abort_unless($insideAllowedDirectory, 403, 'File berada di luar direktori import yang diizinkan.');

        return [$requestedPath, $resolvedPath];
    }

    protected function configuredSessionImportUploadMaxKilobytes(): int
    {
        $bytes = max(1, (int) config('import.security.upload_max_bytes', 4 * 1024 * 1024 * 1024));

        return max(1, (int) floor($bytes / 1024));
    }

    /**
     * @return array<int, string>
     */
    private function importStoragePathCandidates(string $relativePath): array
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $candidates = [
            Storage::path($relativePath),
            storage_path('app/' . $relativePath),
        ];

        if (str_starts_with($relativePath, 'private/')) {
            $candidates[] = storage_path('app/' . substr($relativePath, strlen('private/')));
        } else {
            $candidates[] = storage_path('app/private/' . $relativePath);
        }

        return array_values(array_unique($candidates));
    }

    private function normalizeImportStoragePath(string $path): string
    {
        return trim(str_replace('\\', '/', trim($path)), '/');
    }

    private function isAbsoluteImportStoragePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1;
    }

    private function importPathIsWithin(string $path, string $root): bool
    {
        $normalize = static function (string $value): string {
            $value = str_replace('\\', '/', rtrim($value, '\\/'));

            return PHP_OS_FAMILY === 'Windows' ? strtolower($value) : $value;
        };

        $path = $normalize($path);
        $root = $normalize($root);

        return $path !== $root && str_starts_with($path, $root . '/');
    }
}
