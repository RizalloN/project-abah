<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\FileManagementController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

    public function test_stale_running_backup_status_is_reported_as_failed(): void
    {
        Cache::put('backup_progress:backup_test', [
            'status' => 'processing',
            'progress_percent' => 12,
            'message' => 'Mencadangkan tabel besar...',
            'updated_at' => now()->subMinutes(4)->timestamp,
        ], now()->addHour());

        $response = (new FileManagementController())->getBackupStatus('backup_test');
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('failed', $payload['status']);
        $this->assertSame(12, $payload['progress_percent']);
        $this->assertStringContainsString('tidak memberi progress', $payload['message']);
    }
}
