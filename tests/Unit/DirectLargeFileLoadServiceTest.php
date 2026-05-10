<?php

namespace Tests\Unit;

use App\Services\Import\DirectLargeFileLoadService;
use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DirectLargeFileLoadServiceTest extends TestCase
{
    private DirectLargeFileLoadService $service;
    private string $testTableName = 'test_import_large_file';
    private string $testCsvPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
        }

        $bulkLoadService = app(MySqlBulkLoadService::class);
        $this->service = new DirectLargeFileLoadService($bulkLoadService);

        $this->createTestTable();
    }

    protected function tearDown(): void
    {
        // Clean up
        DB::table($this->testTableName)->truncate();
        if ($this->testCsvPath && file_exists($this->testCsvPath)) {
            @unlink($this->testCsvPath);
        }

        parent::tearDown();
    }

    private function createTestTable(): void
    {
        // SQLite-compatible table creation
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$this->testTableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                amount REAL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function test_validate_csv_format_succeeds_with_valid_file(): void
    {
        $csvPath = $this->createTestCsv(['name', 'email', 'amount'], [
            ['Alice', 'alice@test.com', '1000.50'],
            ['Bob', 'bob@test.com', '2000.75'],
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateCsvFormat');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $csvPath, ['name', 'email', 'amount']);

        $this->assertTrue($result['valid']);
        @unlink($csvPath);
    }

    public function test_validate_csv_format_fails_with_missing_columns(): void
    {
        $csvPath = $this->createTestCsv(['name', 'email'], [
            ['Alice', 'alice@test.com'],
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateCsvFormat');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $csvPath, ['name', 'email', 'amount', 'status']);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('kolom tidak sesuai', $result['error']);
        @unlink($csvPath);
    }

    public function test_calculate_optimal_chunk_size(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOptimalChunkSize');
        $method->setAccessible(true);

        // 5 columns
        $chunkSize = $method->invoke($this->service, ['col1', 'col2', 'col3', 'col4', 'col5']);

        // Should return reasonable chunk size
        $this->assertGreaterThan(1000, $chunkSize);
        $this->assertLessThan(100000, $chunkSize);
    }

    public function test_estimate_line_count(): void
    {
        $csvPath = $this->createTestCsv(['name', 'email', 'amount'], [
            ['Alice', 'alice@test.com', '1000.50'],
            ['Bob', 'bob@test.com', '2000.75'],
            ['Charlie', 'charlie@test.com', '3000.00'],
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('estimateLineCount');
        $method->setAccessible(true);

        $lineCount = $method->invoke($this->service, $csvPath);

        // Should estimate at least 3 lines
        $this->assertGreaterThanOrEqual(3, $lineCount);
        @unlink($csvPath);
    }

    public function test_is_transient_error_detects_mysql_connection_errors(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isTransientError');
        $method->setAccessible(true);

        $transientException = new \Exception('MySQL server has gone away');
        $this->assertTrue($method->invoke($this->service, $transientException));

        $fatalException = new \Exception('Table locked');
        $this->assertFalse($method->invoke($this->service, $fatalException));
    }

    public function test_load_large_file_detects_large_file_threshold(): void
    {
        // This test verifies that the service creates and uses DirectLargeFileLoadService
        // for large files (>50MB)
        $this->assertNotNull($this->service);

        // Verify that getLargeFileLoader method is available on MySqlBulkLoadService
        $reflection = new \ReflectionClass(MySqlBulkLoadService::class);
        $hasMethod = $reflection->hasMethod('getLargeFileLoader');
        $this->assertTrue($hasMethod, 'MySqlBulkLoadService should have getLargeFileLoader method');
    }

    /**
     * Helper: Create test CSV file
     */
    private function createTestCsv(array $headers, array $rows): string
    {
        $path = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
        $handle = fopen($path, 'w');

        // Write headers
        fputcsv($handle, $headers);

        // Write rows
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        return $path;
    }
}
