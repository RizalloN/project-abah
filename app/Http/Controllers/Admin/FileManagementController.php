<?php

namespace App\Http\Controllers\Admin;

use App\Services\DatabaseBackupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Services\Import\ImportProgressService;
use RuntimeException;

class FileManagementController extends Controller
{
    private const MANAGED_DIRECTORIES = [
        [
            'key' => 'database_backups',
            'label' => 'Database Backups',
            'description' => 'Backup SQL penuh database untuk restore/import ulang.',
            'path' => 'private/database_backups',
        ],
        [
            'key' => 'excel_imports',
            'label' => 'Excel Imports',
            'description' => 'File hasil upload import Excel / daily loan.',
            'path' => 'private/excel_imports',
        ],
        [
            'key' => 'casa_brilink_imports',
            'label' => 'Casa Brilink Imports',
            'description' => 'Artefak sementara import Casa Brilink.',
            'path' => 'private/casa_brilink_imports',
        ],
        [
            'key' => 'report_ph_imports',
            'label' => 'Report PH Imports',
            'description' => 'File staging untuk report PH.',
            'path' => 'private/report_ph_imports',
        ],
        [
            'key' => 'performance_pis_imports',
            'label' => 'Performance PIS Imports',
            'description' => 'File staging untuk performance PIS per produk.',
            'path' => 'private/performance_pis_imports',
        ],
        [
            'key' => 'import_bulk',
            'label' => 'Import Bulk',
            'description' => 'Folder kerja sementara untuk proses chunked import.',
            'path' => 'import_bulk',
        ],
        [
            'key' => 'excel_stage',
            'label' => 'Excel Stage',
            'description' => 'Folder kerja staging file Excel.',
            'path' => 'excel_stage',
        ],
    ];

    public function index(): View
    {
        $activeFiles = $this->collectActiveImportFiles();
        $directories = [];
        $files = collect();
        $totals = [
            'files' => 0,
            'size' => 0,
            'size_human' => '0 B',
            'directories' => 0,
            'latest_modified_at' => null,
            'active_files' => 0,
        ];

        foreach (self::MANAGED_DIRECTORIES as $directoryConfig) {
            $directory = $this->resolveDirectoryPath($directoryConfig['path']);
            $entries = $this->scanDirectoryFiles($directoryConfig, $directory);

            $entries = $entries->map(function (array $entry) use ($activeFiles) {
                $entry['is_active'] = isset($activeFiles[strtolower($entry['absolute_path'])]);
                $entry['active_job'] = $activeFiles[strtolower($entry['absolute_path'])] ?? null;

                return $entry;
            });

            $directories[] = [
                'key' => $directoryConfig['key'],
                'label' => $directoryConfig['label'],
                'description' => $directoryConfig['description'],
                'path' => $directory,
                'exists' => is_dir($directory),
                'files' => $entries->count(),
                'size' => $entries->sum('size'),
                'size_human' => $this->formatBytes((int) $entries->sum('size')),
            ];

            $files = $files->concat($entries);
        }

        $files = $files
            ->sortByDesc(fn (array $item) => $item['modified_timestamp'])
            ->values();

        $totals['files'] = $files->count();
        $totals['size'] = $files->sum('size');
        $totals['size_human'] = $this->formatBytes((int) $totals['size']);
        $totals['directories'] = count(self::MANAGED_DIRECTORIES);
        $totals['latest_modified_at'] = $files->first()['modified_at'] ?? null;
        $totals['active_files'] = $files->where('is_active', true)->count();

        return view('admin.file-management', compact('directories', 'files', 'totals'));
    }

    public function backupDatabase(DatabaseBackupService $backupService): JsonResponse
    {
        try {
            $backupId = 'backup_' . uniqid();
            $tables = $backupService->getTables();
            
            Cache::put("backup_progress:{$backupId}", [
                'status' => 'starting',
                'progress_percent' => 0,
                'current_table_index' => 0,
                'total_tables' => count($tables),
                'message' => 'Menyiapkan backup database...',
                'updated_at' => now()->timestamp,
            ], now()->addHours(1));
            Cache::put("backup_meta:{$backupId}", [
                'created_at' => now()->timestamp,
            ], now()->addHours(1));

            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                $this->startWindowsBackupProcess($backupId);
            } else {
                $command = sprintf(
                    'cd %s && %s %s db:backup-progressive %s > /dev/null 2>&1 &',
                    escapeshellarg(base_path()),
                    escapeshellarg(PHP_BINARY),
                    escapeshellarg(base_path('artisan')),
                    escapeshellarg($backupId)
                );
                exec($command);
            }

            return response()->json([
                'status' => 'success',
                'backup_id' => $backupId,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getBackupStatus(string $backupId): JsonResponse
    {
        $status = Cache::get("backup_progress:{$backupId}");

        if (!$status) {
            $meta = Cache::get("backup_meta:{$backupId}");
            if ($meta && now()->timestamp - (int) ($meta['created_at'] ?? 0) <= 30) {
                return response()->json([
                    'status' => 'starting',
                    'progress_percent' => 0,
                    'message' => 'Menunggu proses backup database dimulai...',
                ], 202);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Status backup tidak ditemukan.',
            ], 404);
        }

        $lastUpdate = (int) ($status['updated_at'] ?? 0);
        if (
            in_array($status['status'] ?? null, ['starting', 'processing'], true)
            && $lastUpdate > 0
            && now()->timestamp - $lastUpdate > 180
        ) {
            return response()->json([
                'status' => 'failed',
                'progress_percent' => (int) ($status['progress_percent'] ?? 0),
                'message' => 'Backup tidak memberi progress lebih dari 3 menit. Pastikan proses PHP CLI dan mysqldump dapat dijalankan.',
            ]);
        }

        return response()->json($status);
    }

    private function startWindowsBackupProcess(string $backupId): void
    {
        $logPath = storage_path("logs/database-backup-{$backupId}.log");
        $launcherDirectory = storage_path('framework/backup-launchers');
        if (!is_dir($launcherDirectory)) {
            File::makeDirectory($launcherDirectory, 0755, true);
        }

        $launcherPath = $launcherDirectory . DIRECTORY_SEPARATOR . "{$backupId}.cmd";
        File::put($launcherPath, implode(PHP_EOL, [
            '@echo off',
            'cd /D "' . base_path() . '"',
            '"' . PHP_BINARY . '" "' . base_path('artisan') . '" db:backup-progressive "' . $backupId . '" >> "' . $logPath . '" 2>&1',
            'del "%~f0" >NUL 2>&1',
            '',
        ]));

        $command = sprintf('cmd /C start "" /B "%s"', $launcherPath);

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            base_path()
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Gagal menjalankan proses backup database di background.');
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException("Gagal menjalankan proses backup database di background. Lihat log: {$logPath}");
        }
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['required', 'string', 'max:2048'],
        ]);

        $deleted = [];
        $skipped = [];
        $blocked = [];
        $deletedImportJobs = [];
        $activeFiles = $this->collectActiveImportFiles();
        $importProgressService = app(ImportProgressService::class);

        foreach ($data['paths'] as $path) {
            $resolvedPath = $this->resolveRequestedPath($path);
            if ($resolvedPath === null) {
                $skipped[] = $path;
                continue;
            }

            if (isset($activeFiles[strtolower($resolvedPath)])) {
                $blocked[] = $resolvedPath;
                continue;
            }

            if (is_file($resolvedPath)) {
                @unlink($resolvedPath);
                $deleted[] = $resolvedPath;
                $jobCleanup = $importProgressService->deleteJobsForSourcePath($resolvedPath);
                if (!empty($jobCleanup['deleted_job_ids'])) {
                    $deletedImportJobs = array_merge($deletedImportJobs, $jobCleanup['deleted_job_ids']);
                }
                $this->pruneEmptyManagedParents(dirname($resolvedPath));
                $this->clearStaleImportSessionIfMatched($resolvedPath);
                continue;
            }

            $skipped[] = $path;
        }

        $message = sprintf(
            'Berhasil menghapus %d file%s.',
            count(array_unique($deleted)),
            !empty($skipped) ? ' dan melewati ' . count(array_unique($skipped)) . ' item yang tidak valid' : ''
        );

        if (!empty($deletedImportJobs)) {
            $message .= sprintf(' %d record import terkait ikut dibersihkan.', count(array_unique($deletedImportJobs)));
        }

        if (!empty($blocked)) {
            $message .= sprintf(' %d file aktif dilewati.', count(array_unique($blocked)));
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'deleted_count' => count(array_unique($deleted)),
                'deleted_import_job_count' => count(array_unique($deletedImportJobs)),
                'skipped_count' => count(array_unique($skipped)),
                'blocked_count' => count(array_unique($blocked)),
            ]);
        }

        return redirect()
            ->route('file-management.index')
            ->with('success', $message);
    }

    private function scanDirectoryFiles(array $directoryConfig, string $directory): Collection
    {
        if (!is_dir($directory)) {
            return collect();
        }

        return collect(File::allFiles($directory))->map(function ($file) use ($directoryConfig) {
            $absolutePath = $file->getPathname();
            $resolvedPath = realpath($absolutePath) ?: $absolutePath;
            if (!$this->isWithinManagedRoots($resolvedPath)) {
                return null;
            }

            if (!file_exists($absolutePath)) {
                return null;
            }

            $relativePath = $this->toRelativeStoragePath($absolutePath);
            $modifiedTimestamp = (int) $file->getMTime();

            return [
                'key' => md5($absolutePath),
                'directory_key' => $directoryConfig['key'],
                'directory_label' => $directoryConfig['label'],
                'directory_description' => $directoryConfig['description'],
                'name' => $file->getFilename(),
                'absolute_path' => $absolutePath,
                'relative_path' => $relativePath,
                'size' => (int) $file->getSize(),
                'size_human' => $this->formatBytes((int) $file->getSize()),
                'modified_timestamp' => $modifiedTimestamp,
                'modified_at' => Carbon::createFromTimestamp($modifiedTimestamp)->timezone(config('app.timezone')),
                'modified_human' => Carbon::createFromTimestamp($modifiedTimestamp)->timezone(config('app.timezone'))->format('d M Y H:i'),
            ];
        })->filter()->values();
    }

    private function resolveDirectoryPath(string $relativePath): string
    {
        return storage_path('app/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
    }

    private function resolveRequestedPath(string $requestedPath): ?string
    {
        $normalized = str_replace(['\\', '//'], ['/', '/'], trim($requestedPath));
        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }

        $candidate = $normalized;

        if (!preg_match('/^[A-Za-z]:[\\\\\\/]/', $candidate) && !str_starts_with($candidate, '/')) {
            $candidate = $this->resolveDirectoryPath($candidate);
        }

        $realPath = realpath($candidate);
        if ($realPath === false || !$this->isWithinManagedRoots($realPath)) {
            return null;
        }

        return $realPath;
    }

    public function resolveDownloadablePath(string $requestedPath): ?string
    {
        $resolvedPath = $this->resolveRequestedPath($requestedPath);
        if ($resolvedPath === null || !is_file($resolvedPath)) {
            return null;
        }

        return $resolvedPath;
    }

    private function isWithinManagedRoots(string $path): bool
    {
        $normalizedPath = strtolower(str_replace('\\', '/', $path));

        foreach (self::MANAGED_DIRECTORIES as $directoryConfig) {
            $rootPath = strtolower(str_replace('\\', '/', $this->resolveDirectoryPath($directoryConfig['path'])));
            if (str_starts_with($normalizedPath, rtrim($rootPath, '/') . '/')) {
                return true;
            }
            if ($normalizedPath === rtrim($rootPath, '/')) {
                return true;
            }
        }

        return false;
    }

    private function pruneEmptyManagedParents(string $directory): void
    {
        $current = rtrim(str_replace('\\', '/', $directory), '/');
        $storageApp = rtrim(str_replace('\\', '/', storage_path('app')), '/');

        while ($current !== '' && str_starts_with($current, $storageApp)) {
            if (!$this->isManagedDirectory($current)) {
                break;
            }

            if (!is_dir($current)) {
                $current = dirname($current);
                continue;
            }

            if (!empty(File::files($current)) || !empty(File::directories($current))) {
                break;
            }

            @rmdir($current);
            $current = dirname($current);
        }
    }

    private function isManagedDirectory(string $directory): bool
    {
        $normalizedDirectory = strtolower(str_replace('\\', '/', rtrim($directory, '/')));

        foreach (self::MANAGED_DIRECTORIES as $directoryConfig) {
            $rootPath = strtolower(str_replace('\\', '/', rtrim($this->resolveDirectoryPath($directoryConfig['path']), '/')));
            if ($normalizedDirectory === $rootPath || str_starts_with($normalizedDirectory, $rootPath . '/')) {
                return true;
            }
        }

        return false;
    }

    private function toRelativeStoragePath(string $absolutePath): string
    {
        $storageBase = rtrim(str_replace('\\', '/', storage_path('app')), '/');
        $normalized = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalized, $storageBase . '/')) {
            return substr($normalized, strlen($storageBase) + 1);
        }

        return $normalized;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1, ',', '.') . ' ' . $units[$power];
    }

    private function collectActiveImportFiles(): array
    {
        if (!Schema::hasTable('import_jobs')) {
            return [];
        }

        $active = [];

        $jobs = DB::table('import_jobs')
            ->whereIn('status', ['uploaded', 'processing'])
            ->get(['folder_path', 'file_name', 'status']);

        foreach ($jobs as $job) {
            $resolved = $this->resolveJobSourcePath($job);
            if (!$resolved) {
                continue;
            }

            $active[strtolower($resolved)] = [
                'status' => $job->status ?? null,
                'folder_path' => $job->folder_path ?? null,
                'file_name' => $job->file_name ?? null,
            ];
        }

        return $active;
    }

    private function resolveJobSourcePath(object $job): ?string
    {
        $folderPath = trim((string) ($job->folder_path ?? ''));
        $fileName = trim((string) ($job->file_name ?? ''));

        if ($folderPath === '' || $fileName === '') {
            return null;
        }

        $candidates = [];

        if ($this->isAbsolutePath($folderPath)) {
            $candidates[] = $folderPath . DIRECTORY_SEPARATOR . $fileName;
        } else {
            $cleanFolder = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $folderPath), DIRECTORY_SEPARATOR);
            $candidates[] = storage_path('app/private/' . $cleanFolder . DIRECTORY_SEPARATOR . $fileName);
            $candidates[] = storage_path('app/' . $cleanFolder . DIRECTORY_SEPARATOR . $fileName);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\');
    }

    private function clearStaleImportSessionIfMatched(string $deletedPath): void
    {
        $sessionPath = (string) session('excel_path', '');
        if ($sessionPath === '') {
            return;
        }

        $resolvedSessionPath = $this->resolveSessionImportPath($sessionPath);
        if ($resolvedSessionPath === null) {
            return;
        }

        if (strtolower(str_replace('\\', '/', $resolvedSessionPath)) !== strtolower(str_replace('\\', '/', $deletedPath))) {
            return;
        }

        session()->forget([
            'active_id_report',
            'excel_path',
            'excel_preview_key',
            'excel_headers',
            'excel_display_filter_map',
            'excel_preview_meta',
            'excel_import_params',
            'excel_import_source',
        ]);
    }

    private function resolveSessionImportPath(string $sessionPath): ?string
    {
        $normalized = trim(str_replace(['\\', '//'], ['/', '/'], $sessionPath));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $normalized) === 1 || str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return storage_path('app/' . ltrim($normalized, '/'));
    }
}
