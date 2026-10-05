<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCleanupController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportCleanupControllerTest extends TestCase
{
    public function test_completed_multipn_keeps_recovery_evidence_for_seven_days(): void
    {
        [$sourcePath, $stagingPath] = $this->createImportArtifacts('multipn-retained');
        DB::shouldReceive('table->where->first')->once()->andReturn((object) [
            'id' => 65, 'id_report' => 9, 'status' => 'completed',
            'total_files' => 10, 'total_success' => 10, 'total_failed' => 0,
            'updated_at' => now()->subDay()->toDateTimeString(),
            'folder_path' => dirname($sourcePath), 'file_name' => basename($sourcePath),
        ]);
        try {
            $result = (new ImportCleanupController())->cleanupSuccessfulJobArtifacts(65, [$stagingPath]);
            $this->assertFalse($result['eligible']);
            $this->assertFileExists($sourcePath);
            $this->assertFileExists($stagingPath);
        } finally {
            $this->cleanupIfExists($sourcePath);
            $this->cleanupIfExists($stagingPath);
        }
    }

    public function test_orphan_sweep_protects_old_queued_source_and_staging_files(): void
    {
        $originalStorage = storage_path();
        $this->app->useStoragePath($originalStorage . '/framework/testing/cleanup-protection-' . getmypid());
        [$sourcePath, $stagingPath] = $this->createImportArtifacts('pending');
        touch($sourcePath, now()->subDays(2)->timestamp);
        touch($stagingPath, now()->subDays(2)->timestamp);
        DB::shouldReceive('table->whereIn->where->orderBy->get')->once()->andReturn(collect());
        DB::shouldReceive('table->whereIn->get')->once()->andReturn(collect([(object) [
            'id' => 63, 'id_report' => 9, 'status' => 'queued',
            'folder_path' => dirname($sourcePath), 'file_name' => basename($sourcePath),
            'job_context' => json_encode(['state' => ['params' => ['staged_csv_path' => str_replace('\\', '/', $stagingPath)]]]),
        ]]));
        try {
            $result = (new ImportCleanupController())->cleanupCompletedJobsAndOrphanedFiles(12);
            $this->assertSame(0, $result['deleted_file_count']);
            $this->assertFileExists($sourcePath);
            $this->assertFileExists($stagingPath);
        } finally {
            $this->cleanupIfExists($sourcePath);
            $this->cleanupIfExists($stagingPath);
            $this->app->useStoragePath($originalStorage);
        }
    }

    public function test_cleanup_successful_job_artifacts_removes_source_and_staging_files_for_partial_success(): void
    {
        $controller = new ImportCleanupController();
        [$sourcePath, $stagingPath] = $this->createImportArtifacts();

        DB::shouldReceive('table->where->first')
            ->once()
            ->andReturn((object) [
                'id' => 77,
                'status' => 'failed_partial',
                'total_files' => 10,
                'total_success' => 8,
                'total_failed' => 2,
                'folder_path' => dirname($sourcePath),
                'file_name' => basename($sourcePath),
            ]);

        try {
            $result = $controller->cleanupSuccessfulJobArtifacts(77, [$stagingPath]);

            $this->assertTrue($result['eligible']);
            $this->assertFileDoesNotExist($sourcePath);
            $this->assertFileDoesNotExist($stagingPath);
            $deletedFiles = array_map([$this, 'normalizePath'], $result['deleted_files']);
            $this->assertContains($this->normalizePath($sourcePath), $deletedFiles);
            $this->assertContains($this->normalizePath($stagingPath), $deletedFiles);
        } finally {
            $this->cleanupIfExists($sourcePath);
            $this->cleanupIfExists($stagingPath);
        }
    }

    public function test_cleanup_successful_job_artifacts_skips_jobs_without_successful_rows(): void
    {
        $controller = new ImportCleanupController();
        [$sourcePath, $stagingPath] = $this->createImportArtifacts('no-success');

        DB::shouldReceive('table->where->first')
            ->once()
            ->andReturn((object) [
                'id' => 78,
                'status' => 'completed',
                'total_files' => 10,
                'total_success' => 0,
                'total_failed' => 10,
                'folder_path' => dirname($sourcePath),
                'file_name' => basename($sourcePath),
            ]);

        try {
            $result = $controller->cleanupSuccessfulJobArtifacts(78, [$stagingPath]);

            $this->assertFalse($result['eligible']);
            $this->assertFileExists($sourcePath);
            $this->assertFileExists($stagingPath);
        } finally {
            $this->cleanupIfExists($sourcePath);
            $this->cleanupIfExists($stagingPath);
        }
    }

    private function createImportArtifacts(string $suffix = 'cleanup-test'): array
    {
        $sourceDir = storage_path('app/private/performance_pis_imports');
        $stagingDir = storage_path('app/import_bulk');

        if (!is_dir($sourceDir)) {
            @mkdir($sourceDir, 0777, true);
        }

        if (!is_dir($stagingDir)) {
            @mkdir($stagingDir, 0777, true);
        }

        $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $suffix . '.xlsx';
        $stagingPath = $stagingDir . DIRECTORY_SEPARATOR . $suffix . '.csv';

        file_put_contents($sourcePath, 'source-artifact');
        file_put_contents($stagingPath, 'staging-artifact');

        return [$sourcePath, $stagingPath];
    }

    private function cleanupIfExists(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
