<?php

use App\Http\Controllers\Import\ImportDailyLoanBackendController;
use App\Services\Import\ImportExecutionService;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

beforeEach(function () {
    Cache::flush();

    Schema::dropIfExists('import_jobs');

    Schema::create('import_jobs', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('id_report');
        $table->string('file_name');
        $table->string('folder_path');
        $table->string('status', 40);
        $table->unsignedBigInteger('total_files')->default(0);
        $table->unsignedBigInteger('total_success')->default(0);
        $table->unsignedBigInteger('total_failed')->default(0);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->text('job_context')->nullable();
        $table->string('job_fingerprint')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

afterEach(function () {
    Cache::flush();
    Schema::dropIfExists('import_jobs');

    $directory = storage_path('framework/testing/import-backend-daily-loan');
    if (is_dir($directory)) {
        File::deleteDirectory($directory);
    }
});

function createDailyLoanBackendCsv(string $fileName, string $period = '19-04-2026'): string
{
    $directory = storage_path('framework/testing/import-backend-daily-loan');
    File::ensureDirectoryExists($directory);

    $path = $directory . DIRECTORY_SEPARATOR . $fileName;

    File::put($path, implode(PHP_EOL, [
        'Textbox1,Textbox3',
        'Date Printed : 21 Apr 2026,Laporan Nominatif Pinjaman',
        '',
        'PERIODE,KODE_KANWIL1,KANWIL1,NOMOR_REKENING1,BAKI_DEBET1',
        sprintf('%s,R,KANWIL MALANG,101053983100,"17,587,572.00"', $period),
    ]) . PHP_EOL);

    return $path;
}

it('queues backend daily loan import from a local csv file', function () {
    $csvPath = createDailyLoanBackendCsv('backend-daily-loan.csv');

    $this->mock(ImportExecutionService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dispatch')
            ->once()
            ->andReturn(true);
    });

    $request = Request::create('/import/backend/daily-loan/local-file', 'POST', [
        'source_path' => $csvPath,
        'periode' => '19042026',
        'mode' => 'queue',
        'replace_existing_periods' => false,
    ]);
    $request->headers->set('Accept', 'application/json');

    $response = app(ImportDailyLoanBackendController::class)->importLocalCsv($request);

    expect($response->getStatusCode())->toBe(202);

    $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['status'] ?? null)->toBe('queued');
    expect($payload['detected_periods'] ?? null)->toBe(['2026-04-19']);
    expect($payload['deleted_existing_rows'] ?? null)->toBe(0);

    expect(DB::table('import_jobs')->count())->toBe(1);

    $job = DB::table('import_jobs')->first();

    expect($job)->not->toBeNull();
    expect($job->status)->toBe('queued');
    expect($job->file_name)->toContain('backend-daily-loan.csv');
    expect((string) $job->folder_path)->toContain('storage');
});

it('rejects backend daily loan import when requested period does not match the csv', function () {
    $csvPath = createDailyLoanBackendCsv('backend-daily-loan-mismatch.csv', '19-04-2026');

    $request = Request::create('/import/backend/daily-loan/local-file', 'POST', [
        'source_path' => $csvPath,
        'periode' => '20042026',
        'mode' => 'queue',
    ]);
    $request->headers->set('Accept', 'application/json');

    $response = app(ImportDailyLoanBackendController::class)->importLocalCsv($request);

    expect($response->getStatusCode())->toBe(422);

    $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['status'] ?? null)->toBe('error');
    expect($payload['requested_period'] ?? null)->toBe('2026-04-20');
    expect($payload['detected_periods'] ?? null)->toBe(['2026-04-19']);

    expect(DB::table('import_jobs')->count())->toBe(0);
});
