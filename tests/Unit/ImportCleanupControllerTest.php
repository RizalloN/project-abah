<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCleanupController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportCleanupControllerTest extends TestCase
{
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
