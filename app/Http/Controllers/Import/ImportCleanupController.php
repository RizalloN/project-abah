<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportCleanupController extends Controller
{
    private const PRIVATE_IMPORT_DIRECTORIES = [
        'excel_imports',
        'casa_brilink_imports',
        'report_ph_imports',
        'performance_pis_imports',
    ];

    private const TEMP_DIRECTORIES = [
        'app/import_bulk',
        'app/excel_stage',
    ];

    public function cleanupStaleArtifacts(Request $request)
    {
        $olderThanHours = max(1, (int) $request->input('hours', 12));

        $summary = $this->cleanupCompletedJobsAndOrphanedFiles($olderThanHours);

        return response()->json([
            'status' => 'success',
            'older_than_hours' => $olderThanHours,
        ] + $summary);
    }

    public function cleanupSuccessfulJobArtifacts(int $jobId, array $paths = []): array
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if (!$this->isCleanupEligibleJob($job)) {
            return [
                'job_id' => $jobId,
                'eligible' => false,
                'deleted_files' => [],
                'deleted_directories' => [],
            ];
        }

        $candidatePaths = array_filter(array_unique(array_merge(
            [$this->resolveJobSourcePath($job)],
            $paths
        )));

        return [
            'job_id' => $jobId,
            'eligible' => true,
        ] + $this->deleteArtifacts($candidatePaths);
    }

    public function cleanupCompletedJobsAndOrphanedFiles(int $olderThanHours = 12): array
    {
        $threshold = now()->subHours(max(1, $olderThanHours));
        $deletedFiles = [];
        $deletedDirectories = [];
        $eligibleJobs = 0;

        $completedJobs = DB::table('import_jobs')
            ->whereIn('status', ['completed', 'failed_partial'])
            ->where('updated_at', '<=', $threshold)
            ->orderBy('id')
            ->get();

        foreach ($completedJobs as $job) {
            if (!$this->isCleanupEligibleJob($job)) {
                continue;
            }

            $eligibleJobs++;
            $result = $this->cleanupSuccessfulJobArtifacts((int) $job->id);
            $deletedFiles = array_merge($deletedFiles, $result['deleted_files'] ?? []);
            $deletedDirectories = array_merge($deletedDirectories, $result['deleted_directories'] ?? []);
        }

        $activePaths = [];
        $activeJobs = DB::table('import_jobs')
            ->whereIn('status', ['uploaded', 'queued', 'staging', 'processing', 'failed', 'failed_partial', 'completed'])
            ->get();

        foreach ($activeJobs as $job) {
            if ($this->isCleanupEligibleJob($job)) {
                continue;
            }
            foreach ($this->recoverableArtifactPaths($job) as $resolved) {
                $activePaths[$this->artifactPathKey($resolved)] = true;
            }
        }

        foreach (self::PRIVATE_IMPORT_DIRECTORIES as $relativeDirectory) {
            $directory = storage_path('app/private/' . $relativeDirectory);
            if (!is_dir($directory)) {
                continue;
            }

            foreach (File::files($directory) as $file) {
                $fullPath = $file->getPathname();
                if (isset($activePaths[$this->artifactPathKey($fullPath)])) {
                    continue;
                }

                if ($file->getMTime() > $threshold->getTimestamp()) {
                    continue;
                }

                $result = $this->deleteArtifacts([$fullPath]);
                $deletedFiles = array_merge($deletedFiles, $result['deleted_files']);
                $deletedDirectories = array_merge($deletedDirectories, $result['deleted_directories']);
            }
        }

        foreach (self::TEMP_DIRECTORIES as $relativeDirectory) {
            $directory = storage_path($relativeDirectory);
            if (!is_dir($directory)) {
                continue;
            }

            foreach (File::files($directory) as $file) {
                $fullPath = $file->getPathname();
                if (isset($activePaths[$this->artifactPathKey($fullPath)])) {
                    continue;
                }

                if ($file->getMTime() > $threshold->getTimestamp()) {
                    continue;
                }

                $result = $this->deleteArtifacts([$fullPath]);
                $deletedFiles = array_merge($deletedFiles, $result['deleted_files']);
                $deletedDirectories = array_merge($deletedDirectories, $result['deleted_directories']);
            }
        }

        return [
            'eligible_jobs' => $eligibleJobs,
            'deleted_file_count' => count(array_unique($deletedFiles)),
            'deleted_directory_count' => count(array_unique($deletedDirectories)),
            'deleted_files' => array_values(array_unique($deletedFiles)),
            'deleted_directories' => array_values(array_unique($deletedDirectories)),
        ];
    }

    private function isCleanupEligibleJob($job): bool
    {
        if (!$job) {
            return false;
        }

        $status = strtolower((string) ($job->status ?? ''));
        $context = json_decode((string) ($job->job_context ?? ''), true) ?: [];
        $isMultiPn = (int) ($job->id_report ?? 0) === 9
            || strtolower((string) ($context['table_name'] ?? '')) === 'simpanan_multipn';
        if ($isMultiPn) {
            // Keep recovery evidence for a week; never discard a partial import.
            if ($status !== 'completed' || (int) ($job->total_failed ?? 0) > 0
                || (int) ($job->total_success ?? 0) !== (int) ($job->total_files ?? 0)
                || empty($job->updated_at)
                || \Illuminate\Support\Carbon::parse($job->updated_at)->gt(now()->subDays(7))) {
                return false;
            }
        }
        if (!in_array($status, ['completed', 'failed_partial'], true)) {
            return false;
        }

        $totalFiles = max(0, (int) ($job->total_files ?? 0));
        $totalSuccess = max(0, (int) ($job->total_success ?? 0));

        return $totalFiles === 0 || $totalSuccess > 0;
    }

    private function recoverableArtifactPaths(object $job): array
    {
        $paths = array_filter([$this->resolveJobSourcePath($job)]);
        $context = json_decode((string) ($job->job_context ?? ''), true) ?: [];
        foreach (['file_path', 'staged_csv_path'] as $field) {
            foreach ([$context[$field] ?? null, data_get($context, 'state.params.' . $field)] as $path) {
                if (!is_string($path) || trim($path) === '') {
                    continue;
                }
                if ($this->isAbsolutePath($path) || str_starts_with($path, '/')) {
                    $paths[] = $path;
                } else {
                    $paths[] = storage_path('app/' . $path);
                    $paths[] = storage_path('app/private/' . $path);
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function artifactPathKey(string $path): string
    {
        return strtolower(str_replace('\\', '/', realpath($path) ?: $path));
    }

    private function resolveJobSourcePath($job): ?string
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

        return $candidates[0] ?? null;
    }

    private function deleteArtifacts(array $paths): array
    {
        $deletedFiles = [];
        $deletedDirectories = [];

        foreach ($paths as $path) {
            $normalizedPath = $this->normalizePath($path);
            if ($normalizedPath === null) {
                continue;
            }

            try {
                if (Storage::exists($normalizedPath)) {
                    Storage::delete($normalizedPath);
                    $deletedFiles[] = Storage::path($normalizedPath);
                    $deletedDirectories = array_merge($deletedDirectories, $this->pruneEmptyImportDirectories(dirname(Storage::path($normalizedPath))));
                    continue;
                }

                if (is_file($path)) {
                    @unlink($path);
                    $deletedFiles[] = $path;
                    $deletedDirectories = array_merge($deletedDirectories, $this->pruneEmptyImportDirectories(dirname($path)));
                }
            } catch (\Throwable $e) {
                Log::warning('Import cleanup gagal menghapus artefak.', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'deleted_files' => array_values(array_unique($deletedFiles)),
            'deleted_directories' => array_values(array_unique($deletedDirectories)),
        ];
    }

    private function pruneEmptyImportDirectories(string $directory): array
    {
        $deleted = [];
        $current = rtrim($directory, '\\/');
        $privateRoot = rtrim(storage_path('app/private'), '\\/');
        $appRoot = rtrim(storage_path('app'), '\\/');

        while ($current !== '' && $current !== $privateRoot && $current !== $appRoot) {
            if (!is_dir($current)) {
                $current = dirname($current);
                continue;
            }

            if (!$this->isManagedImportDirectory($current)) {
                break;
            }

            $files = File::files($current);
            $directories = File::directories($current);

            if (!empty($files) || !empty($directories)) {
                break;
            }

            @rmdir($current);
            $deleted[] = $current;
            $current = dirname($current);
        }

        return $deleted;
    }

    private function isManagedImportDirectory(string $directory): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $directory));
        $allowedFragments = [
            '/storage/app/private/excel_imports',
            '/storage/app/private/casa_brilink_imports',
            '/storage/app/private/report_ph_imports',
            '/storage/app/private/performance_pis_imports',
            '/storage/app/import_bulk',
            '/storage/app/excel_stage',
        ];

        foreach ($allowedFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $storageBase = rtrim(str_replace('\\', '/', storage_path('app')), '/');
        $privateBase = $storageBase . '/private';
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, $privateBase . '/')) {
            return substr($normalized, strlen($privateBase) + 1);
        }

        if (str_starts_with($normalized, $storageBase . '/')) {
            return substr($normalized, strlen($storageBase) + 1);
        }

        if (!$this->isAbsolutePath($path)) {
            return ltrim(str_replace('\\', '/', $path), '/');
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\');
    }
}
