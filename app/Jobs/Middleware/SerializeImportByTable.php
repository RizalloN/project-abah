<?php

namespace App\Jobs\Middleware;

use App\Services\Import\ImportProgressService;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SerializeImportByTable
{
    private const LOCK_TTL_SECONDS = 21600;

    public function __construct(
        private readonly ?ImportProgressService $importProgressService = null,
        private readonly int $releaseAfterSeconds = 30,
        private readonly ?string $tableName = null
    ) {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $jobId = max(0, (int) ($job->jobId ?? 0));
        $scope = $this->resolveTableScope($jobId);
        $lock = $this->importCache()->lock(
            'import:table-execution:' . $scope,
            self::LOCK_TTL_SECONDS
        );

        if (!$lock->get()) {
            Log::info('Import job ditunda karena tabel sumber sedang diproses worker lain.', [
                'job_id' => $jobId,
                'table_scope' => $scope,
                'delay_seconds' => max(1, $this->releaseAfterSeconds),
            ]);

            if (method_exists($job, 'release')) {
                $job->release(max(1, $this->releaseAfterSeconds));
            }

            return null;
        }

        try {
            if (!str_starts_with($scope, 'job-')
                && $this->progressService()->hasActiveProcessingJobsForTable($scope, $jobId)) {
                Log::info('Import job ditunda sampai pipeline tabel sebelumnya mencapai status terminal.', [
                    'job_id' => $jobId,
                    'table_scope' => $scope,
                    'delay_seconds' => max(1, $this->releaseAfterSeconds),
                ]);

                if (method_exists($job, 'release')) {
                    $job->release(max(1, $this->releaseAfterSeconds));
                }

                return null;
            }

            return $next($job);
        } finally {
            $lock->release();
        }
    }

    private function resolveTableScope(int $jobId): string
    {
        $explicitTableName = strtolower(trim((string) $this->tableName));
        if ($explicitTableName !== '') {
            return preg_replace('/[^a-z0-9_]+/', '_', $explicitTableName) ?: 'unknown';
        }

        if ($jobId <= 0) {
            return 'unknown';
        }

        $state = $this->progressService()->getJobState($jobId);
        $tableName = strtolower(trim((string) data_get($state, 'params.table_name', '')));

        if ($tableName === '') {
            try {
                $tableName = strtolower(trim((string) DB::table('import_jobs as ij')
                    ->leftJoin('nama_report as nr', 'nr.id_report', '=', 'ij.id_report')
                    ->where('ij.id', $jobId)
                    ->value('nr.table_name')));
            } catch (\Throwable) {
                $tableName = '';
            }
        }

        $tableName = preg_replace('/[^a-z0-9_]+/', '_', $tableName) ?: '';

        return $tableName !== '' ? $tableName : 'job-' . $jobId;
    }

    private function progressService(): ImportProgressService
    {
        return $this->importProgressService ?? app(ImportProgressService::class);
    }

    private function importCache()
    {
        $store = trim((string) config('import.cache_store', 'file'));

        return $store !== '' ? Cache::store($store) : Cache::store();
    }
}
