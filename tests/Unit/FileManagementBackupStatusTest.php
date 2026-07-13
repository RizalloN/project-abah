<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\FileManagementController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Tests\TestCase;

class FileManagementBackupStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-04-26 15:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_import_scope_delete_does_not_remove_database_backup_file(): void
    {
        $backupDirectory = storage_path('app/private/database_backups');
        File::ensureDirectoryExists($backupDirectory);
        $backupPath = $backupDirectory . DIRECTORY_SEPARATOR . 'backup_scope_guard_test.sql';
        File::put($backupPath, 'database backup');

        $request = Request::create('/file-management/delete', 'POST', [
            'paths' => ['private/database_backups/backup_scope_guard_test.sql'],
            'scope' => 'import_artifacts',
        ], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        try {
            $response = (new FileManagementController())->destroy($request);
            $payload = $response->getData(true);

            $this->assertSame(207, $response->getStatusCode());
            $this->assertSame('partial', $payload['status']);
            $this->assertSame(0, $payload['deleted_count']);
            $this->assertSame(1, $payload['failed_count']);
            $this->assertFileExists($backupPath);
            $this->assertStringContainsString('Backup database dipisahkan', $payload['failed_items'][0]['reason']);
        } finally {
            File::delete($backupPath);
        }
    }

    public function test_database_backup_scope_can_delete_database_backup_file(): void
    {
        $backupDirectory = storage_path('app/private/database_backups');
        File::ensureDirectoryExists($backupDirectory);
        $backupPath = $backupDirectory . DIRECTORY_SEPARATOR . 'backup_scope_delete_test.sql';
        File::put($backupPath, 'database backup');

        $request = Request::create('/file-management/delete', 'POST', [
            'paths' => ['private/database_backups/backup_scope_delete_test.sql'],
            'scope' => 'database_backups',
        ], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        try {
            $response = (new FileManagementController())->destroy($request);
            $payload = $response->getData(true);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('success', $payload['status']);
            $this->assertSame(1, $payload['deleted_count']);
            $this->assertFileDoesNotExist($backupPath);
        } finally {
            File::delete($backupPath);
        }
    }

    public function test_recent_backup_meta_returns_starting_status_when_progress_cache_is_not_ready(): void
    {
        Cache::put('backup_meta:backup_test', [
            'created_at' => now()->timestamp,
        ], now()->addHour());

        $response = (new FileManagementController())->getBackupStatus('backup_test');
        $payload = $response->getData(true);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('starting', $payload['status']);
        $this->assertSame('Menunggu proses backup database dimulai...', $payload['message']);
    }

    public function test_stale_running_backup_status_is_reported_as_stalled_not_failed(): void
    {
        Cache::put('backup_progress:backup_test', [
            'status' => 'processing',
            'progress_percent' => 12,
            'message' => 'Mencadangkan tabel besar...',
            'updated_at' => now()->subMinutes(6)->timestamp,
        ], now()->addHour());

        $response = (new FileManagementController())->getBackupStatus('backup_test');
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('stalled', $payload['status']);
        $this->assertSame(12, $payload['progress_percent']);
        $this->assertStringContainsString('tidak ada update progress', $payload['message']);
    }
}
