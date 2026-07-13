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
use App\Support\DatabaseBackupStatusStore;
use RuntimeException;

class FileManagementController extends Controller
{
    private const MANAGED_DIRECTORIES = [
        [
            'key' => 'database_backups',
            'label' => 'Database Backups',
            'description' => 'Backup SQL penuh database untuk restore/import ulang.',
            'path' => 'private/database_backups',
            'group' => 'database_backups',
        ],
        [
            'key' => 'excel_imports',
            'label' => 'Excel Imports',
            'description' => 'File hasil upload import Excel / daily loan.',
            'path' => 'private/excel_imports',
            'group' => 'import_artifacts',
        ],
        [
            'key' => 'casa_brilink_imports',
            'label' => 'Casa Brilink Imports',
            'description' => 'Artefak sementara import Casa Brilink.',
            'path' => 'private/casa_brilink_imports',
            'group' => 'import_artifacts',
        ],
        [
            'key' => 'report_ph_imports',
            'label' => 'Report PH Imports',
            'description' => 'File staging untuk report PH.',
            'path' => 'private/report_ph_imports',
            'group' => 'import_artifacts',
        ],
        [
            'key' => 'performance_pis_imports',
            'label' => 'Performance PIS Imports',
            'description' => 'File staging untuk performance PIS per produk.',
            'path' => 'private/performance_pis_imports',
            'group' => 'import_artifacts',
        ],
        [
            'key' => 'import_bulk',
            'label' => 'Import Bulk',
            'description' => 'Folder kerja sementara untuk proses chunked import.',
            'path' => 'import_bulk',
            'group' => 'import_artifacts',
        ],
        [
            'key' => 'excel_stage',
            'label' => 'Excel Stage',
            'description' => 'Folder kerja staging file Excel.',
            'path' => 'excel_stage',
            'group' => 'import_artifacts',
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
                'group' => $directoryConfig['group'] ?? 'import_artifacts',
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
            $runningBackup = DatabaseBackupStatusStore::latestRunning();
            if ($runningBackup !== null && !empty($runningBackup['backup_id'])) {
                return response()->json([
                    'status' => 'running',
                    'backup_id' => $runningBackup['backup_id'],
                    'message' => 'Backup database masih berjalan. Menyambungkan modal ke proses yang sedang aktif.',
                ]);
            }

            $backupId = 'backup_' . uniqid();
            $tables = $backupService->getTables();
            
            DatabaseBackupStatusStore::put($backupId, [
                'status' => 'starting',
                'progress_percent' => 0,
                'current_table_index' => 0,
                'total_tables' => count($tables),
                'message' => 'Menyiapkan backup database...',
                'updated_at' => now()->timestamp,
            ]);
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
        $status = DatabaseBackupStatusStore::get($backupId);

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

        $status = $this->enrichBackupStatusWithLiveFileState($status);

        // Enhanced timeout logic: only fail if no updates for 5+ minutes AND progress is stalled
        // (this accommodates large table processing without false positives)
        $lastUpdate = (int) ($status['updated_at'] ?? 0);
        if (
            in_array($status['status'] ?? null, ['starting', 'processing'], true)
            && $lastUpdate > 0
            && now()->timestamp - $lastUpdate > 300
        ) {
            return response()->json([
                'status' => 'stalled',
                'progress_percent' => (int) ($status['progress_percent'] ?? 0),
                'message' => 'Backup sudah tidak ada update progress selama 5 menit. Proses mungkin sedang memproses tabel yang sangat besar. Silakan tunggu lebih lama atau check log di storage/logs/database-backup-*.log',
                'file' => $status['file'] ?? null,
                'backup_file' => $status['backup_file'] ?? null,
            ]);
        }

        return response()->json($status);
    }

    /**
     * @param array<string, mixed> $status
     * @return array<string, mixed>
     */
    private function enrichBackupStatusWithLiveFileState(array $status): array
    {
        $candidatePaths = [];
        if (!empty($status['backup_file']) && is_string($status['backup_file'])) {
            $candidatePaths[] = $status['backup_file'];
            if (!str_ends_with($status['backup_file'], '.gz')) {
                $candidatePaths[] = $status['backup_file'] . '.gz';
            }
        }

        $file = is_array($status['file'] ?? null) ? $status['file'] : [];
        if (!empty($file['relative_path']) && is_string($file['relative_path'])) {
            $candidatePaths[] = storage_path('app/' . ltrim($file['relative_path'], '/\\'));
        }

        $existingPath = null;
        foreach (array_unique($candidatePaths) as $candidatePath) {
            if (is_file($candidatePath)) {
                $existingPath = $candidatePath;
                break;
            }
        }

        if ($existingPath === null) {
            return $status;
        }

        clearstatcache(true, $existingPath);
        $relativePath = $this->toRelativeStoragePath($existingPath);
        $status['backup_file'] = $existingPath;
        $status['file'] = array_merge($file, [
            'name' => basename($existingPath),
            'relative_path' => $relativePath,
            'download_url' => route('file-management.download', ['path' => $relativePath]),
            'size' => filesize($existingPath) ?: 0,
            'size_human' => $this->formatBytes((int) (filesize($existingPath) ?: 0)),
            'modified_at' => filemtime($existingPath) ?: null,
        ]);

        if (in_array($status['status'] ?? null, ['starting', 'processing', 'stalled'], true)) {
            $status['message'] = sprintf(
                '%s Ukuran file saat ini: %s.',
                rtrim((string) ($status['message'] ?? 'Backup sedang berjalan.'), '.'),
                $status['file']['size_human']
            );
        }

        return $status;
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
            '"' . $this->resolvePhpCliBinary() . '" "' . base_path('artisan') . '" db:backup-progressive "' . $backupId . '" >> "' . $logPath . '" 2>&1',
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

    private function resolvePhpCliBinary(): string
    {
        $candidates = [
            PHP_BINARY,
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe',
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php',
            'C:\\xampp\\php\\php.exe',
            'php',
        ];

        foreach ($candidates as $candidate) {
            $name = strtolower((string) basename($candidate));
            if (in_array($name, ['php.exe', 'php'], true) && ($candidate === 'php' || is_file($candidate))) {
                return $candidate;
            }
        }

        throw new RuntimeException('Binary PHP CLI tidak ditemukan untuk menjalankan backup database.');
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['required', 'string', 'max:2048'],
            'scope' => ['nullable', 'string', 'in:import_artifacts,database_backups'],
        ]);
        $scope = (string) ($data['scope'] ?? 'import_artifacts');

        $deleted = [];
        $failed = [];
        $skipped = [];
        $blocked = [];
        $deletedImportJobs = [];
        $activeFiles = $this->collectActiveImportFiles();
        $activeBackupFiles = $this->collectActiveBackupFiles();
        $importProgressService = app(ImportProgressService::class);

        foreach ($data['paths'] as $path) {
            $resolvedPath = $this->resolveRequestedPath($path);
            if ($resolvedPath === null) {
                $skipped[] = $path;
                continue;
            }

            $normalizedResolvedPath = strtolower(str_replace('\\', '/', $resolvedPath));
            if (isset($activeBackupFiles[$normalizedResolvedPath])) {
                $failed[] = [
                    'path' => $path,
                    'reason' => 'Backup database masih berjalan atau file masih ditulis. Tunggu proses selesai sebelum menghapus.',
                ];
                continue;
            }

            if (isset($activeFiles[strtolower($resolvedPath)])) {
                $blocked[] = $resolvedPath;
                continue;
            }

            if (!is_file($resolvedPath)) {
                $skipped[] = $path;
                continue;
            }

            $isDatabaseBackup = $this->isDatabaseBackupPath($resolvedPath);
            if ($isDatabaseBackup && $scope !== 'database_backups') {
                $failed[] = [
                    'path' => $path,
                    'reason' => 'Backup database dipisahkan dari clear Excel/import. Buka tab Database Backup untuk menghapus file ini secara eksplisit.',
                ];
                continue;
            }

            if (!$isDatabaseBackup && $scope === 'database_backups') {
                $failed[] = [
                    'path' => $path,
                    'reason' => 'File ini bukan backup database. Buka tab Excel / Import untuk menghapus artefak import.',
                ];
                continue;
            }

            // Verify file exists before attempting delete
            if (!file_exists($resolvedPath)) {
                $skipped[] = $path;
                continue;
            }

            // Attempt to delete file with proper error handling
            $deleteSuccess = false;
            try {
                $deleteSuccess = @unlink($resolvedPath);
            } catch (\Throwable $e) {
                \Log::warning("Failed to unlink file {$resolvedPath}: " . $e->getMessage());
                $deleteSuccess = false;
            }

            // Verify file was actually deleted
            if ($deleteSuccess && !file_exists($resolvedPath)) {
                $deleted[] = $resolvedPath;
                
                // Clean up associated import jobs
                if (!$isDatabaseBackup) {
                    try {
                        $jobCleanup = $importProgressService->deleteJobsForSourcePath($resolvedPath);
                        if (!empty($jobCleanup['deleted_job_ids'])) {
                            $deletedImportJobs = array_merge($deletedImportJobs, $jobCleanup['deleted_job_ids']);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning("Failed to cleanup import jobs for {$resolvedPath}: " . $e->getMessage());
                    }
                }

                // Prune empty parent directories
                try {
                    $this->pruneEmptyManagedParents(dirname($resolvedPath));
                } catch (\Throwable $e) {
                    \Log::warning("Failed to prune empty parents for {$resolvedPath}: " . $e->getMessage());
                }

                // Clear stale import session
                if (!$isDatabaseBackup) {
                    try {
                        $this->clearStaleImportSessionIfMatched($resolvedPath);
                    } catch (\Throwable $e) {
                        \Log::warning("Failed to clear stale import session for {$resolvedPath}: " . $e->getMessage());
                    }
                }
            } else {
                $failed[] = [
                    'path' => $path,
                    'reason' => $deleteSuccess ? 'File masih ada setelah delete' : $this->describeDeleteFailure($resolvedPath)
                ];
            }
        }

        // Prepare response message
        $uniqueDeleted = array_unique($deleted);
        $message = sprintf(
            'Berhasil menghapus %d file%s.',
            count($uniqueDeleted),
            !empty($skipped) ? ' dan melewati ' . count(array_unique($skipped)) . ' item tidak valid' : ''
        );

        if (!empty($failed)) {
            $message .= sprintf(' Gagal menghapus %d file: %s', 
                count($failed),
                implode(', ', array_slice(array_column($failed, 'reason'), 0, 3))
            );
        }

        if (!empty($deletedImportJobs)) {
            $message .= sprintf(' %d record import terkait dibersihkan.', count(array_unique($deletedImportJobs)));
        }

        if (!empty($blocked)) {
            $message .= sprintf(' %d file aktif dilewati.', count(array_unique($blocked)));
        }

        if ($request->expectsJson() || $request->ajax()) {
            $statusCode = empty($failed) ? 200 : 207; // 207 Multi-Status if partial failure
            $status = empty($failed) ? 'success' : 'partial';
            
            return response()->json([
                'status' => $status,
                'message' => $message,
                'deleted_count' => count($uniqueDeleted),
                'deleted_import_job_count' => count(array_unique($deletedImportJobs)),
                'failed_count' => count($failed),
                'failed_items' => $failed,
                'skipped_count' => count(array_unique($skipped)),
                'blocked_count' => count(array_unique($blocked)),
            ], $statusCode);
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
                'storage_group' => $directoryConfig['group'] ?? 'import_artifacts',
                'storage_group_label' => ($directoryConfig['group'] ?? 'import_artifacts') === 'database_backups' ? 'Database Backup' : 'Excel / Import',
                'is_database_backup' => ($directoryConfig['group'] ?? 'import_artifacts') === 'database_backups',
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

    private function isDatabaseBackupPath(string $path): bool
    {
        $normalizedPath = strtolower(str_replace('\\', '/', $path));
        $backupRoot = strtolower(str_replace('\\', '/', $this->resolveDirectoryPath('private/database_backups')));

        return $normalizedPath === rtrim($backupRoot, '/') || str_starts_with($normalizedPath, rtrim($backupRoot, '/') . '/');
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

    private function collectActiveBackupFiles(): array
    {
        $runningBackup = DatabaseBackupStatusStore::latestRunning();
        if ($runningBackup === null) {
            return [];
        }

        $active = [];
        $candidatePaths = [];

        if (!empty($runningBackup['backup_file']) && is_string($runningBackup['backup_file'])) {
            $candidatePaths[] = $runningBackup['backup_file'];
            if (!str_ends_with($runningBackup['backup_file'], '.gz')) {
                $candidatePaths[] = $runningBackup['backup_file'] . '.gz';
            }
        }

        $file = is_array($runningBackup['file'] ?? null) ? $runningBackup['file'] : [];
        if (!empty($file['relative_path']) && is_string($file['relative_path'])) {
            $candidatePaths[] = storage_path('app/' . ltrim($file['relative_path'], '/\\'));
        }

        foreach (array_unique($candidatePaths) as $candidatePath) {
            $resolved = realpath($candidatePath) ?: $candidatePath;
            if (!is_string($resolved) || $resolved === '' || !$this->isWithinManagedRoots($resolved)) {
                continue;
            }

            $active[strtolower(str_replace('\\', '/', $resolved))] = [
                'backup_id' => $runningBackup['backup_id'] ?? null,
                'status' => $runningBackup['status'] ?? null,
            ];
        }

        return $active;
    }

    private function describeDeleteFailure(string $path): string
    {
        if (strncasecmp(PHP_OS_FAMILY, 'Windows', 7) === 0 && is_file($path)) {
            try {
                $handle = fopen($path, 'rb+');
                if (is_resource($handle)) {
                    fclose($handle);
                }
            } catch (\Throwable) {
                return 'File sedang digunakan proses lain. Tutup proses backup/import lalu coba lagi.';
            }
        }

        return 'Permission denied atau file locked';
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
