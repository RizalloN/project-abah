<?php

namespace Tests\Unit;

use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportProgressServiceTest extends TestCase
{
    public function test_get_status_payload_merges_job_and_cached_progress(): void
    {
        DB::shouldReceive('table->where->first')
            ->once()
            ->andReturn((object) [
                'id' => 77,
                'id_report' => 8,
                'file_name' => 'sample.xlsx',
                'status' => 'processing',
                'total_files' => 100,
                'total_success' => 25,
                'total_failed' => 5,
                'updated_at' => '2026-04-11 10:00:00',
            ]);

        Cache::shouldReceive('get')
            ->once()
            ->andReturn([
                'message' => 'Masih jalan',
                'processed_rows' => 40,
                'total_rows' => 100,
                'total_success' => 30,
                'total_failed' => 10,
                'percent' => 40,
                'updated_at' => '2026-04-11T10:00:00+07:00',
            ]);

        $payload = app(ImportProgressService::class)->getStatusPayload(77);

        $this->assertSame('processing', $payload['status']);
        $this->assertSame(77, $payload['job_id']);
        $this->assertSame(8, $payload['report_id']);
        $this->assertSame(100, $payload['total_rows']);
        $this->assertSame(40, $payload['processed_rows']);
        $this->assertSame(30, $payload['total_success']);
        $this->assertSame(10, $payload['total_failed']);
        $this->assertSame(40, $payload['percent']);
        $this->assertSame('Masih jalan', $payload['message']);
    }
}
