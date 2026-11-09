<?php

namespace Tests\Unit;

use App\Console\Commands\RefreshRemoteDashboardSourcesCommand;
use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\PublicWorkbookController;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Jobs\RefreshRemoteDashboardSourcesJob;
use App\Services\Reports\BusinessClusterReportService;
use App\Services\Reports\MicroPipelineSyncService;
use App\Services\Reports\SppgReportService;
use App\Support\RemoteDashboardSourceRefreshPolicy;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class RemoteDashboardSourceCooldownTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Queue::fake();
        Carbon::setTestNow('2026-11-03 19:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function refresh(string $source, callable $callback): void
    {
        (new ReflectionMethod(RefreshRemoteDashboardSourcesJob::class, 'refreshSource'))->invoke(new RefreshRemoteDashboardSourcesJob([$source]), $source, $callback);
    }

    public function test_failed_result_keeps_real_error_and_last_success_with_an_increasing_cooldown(): void
    {
        Cache::forever('dashboard_sources:last_refresh:market-share', ['success' => true, 'refreshed_at' => '2026-11-03T18:00:00+07:00']);
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->refresh('market-share', fn () => ['success' => false, 'error' => 'HTTP 429 provider rate limit']);
                $this->fail('A failed source must throw.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('HTTP 429 provider rate limit', $exception->getMessage());
            }
            $status = Cache::get('dashboard_sources:last_refresh:market-share');
            $this->assertFalse($status['success']);
            $this->assertSame($attempt, $status['consecutive_failures']);
            $this->assertSame('2026-11-03T18:00:00+07:00', $status['last_successful_at']);
            $this->assertStringContainsString('HTTP 429', $status['error']);
            $this->assertSame($attempt === 1 ? 900 : 1800, RemoteDashboardSourceRefreshPolicy::retryDelay('market-share'));
        }
    }

    public function test_provider_retry_after_seconds_and_http_date_are_respected(): void
    {
        RemoteDashboardSourceRefreshPolicy::failed('market-share', '429', 3600);
        $this->assertSame(3600, RemoteDashboardSourceRefreshPolicy::retryDelay('market-share'));
        RemoteDashboardSourceRefreshPolicy::failed('kpi', '429', now()->addHours(2)->toRfc7231String());
        $this->assertSame(7200, RemoteDashboardSourceRefreshPolicy::retryDelay('kpi'));
    }

    public function test_thrown_http_error_keeps_its_provider_retry_after_header(): void
    {
        try {
            $this->refresh('market-share', static fn () => (new \Illuminate\Http\Client\Response(new Response(429, ['Retry-After' => '3600'], 'limited')))->throw());
            $this->fail('Expected HTTP failure');
        } catch (RequestException) {
            $this->assertSame(3600, RemoteDashboardSourceRefreshPolicy::retryDelay('market-share'));
        }
    }

    public function test_success_resets_failure_history_and_expired_or_malformed_dates_do_not_block(): void
    {
        RemoteDashboardSourceRefreshPolicy::failed('kpi', '410');
        Carbon::setTestNow(now()->addMinutes(16));
        $this->assertSame(0, RemoteDashboardSourceRefreshPolicy::retryDelay('kpi'));
        $this->refresh('kpi', fn () => ['success' => true]);
        $status = Cache::get('dashboard_sources:last_refresh:kpi');
        $this->assertTrue($status['success']);
        $this->assertSame(0, $status['consecutive_failures']);
        $this->assertNull($status['next_retry_at']);
        $this->assertNull($status['error']);
        Cache::forever('dashboard_sources:last_refresh:kpi', ['success' => false, 'next_retry_at' => 'invalid-date']);
        $this->assertSame(0, RemoteDashboardSourceRefreshPolicy::retryDelay('kpi'));
    }

    public function test_stale_scheduler_skips_cooldown_but_operator_can_explicitly_request_refresh(): void
    {
        foreach (['market-share', 'market-share-mapping', 'market-share-instansi', 'kpi', 'business-cluster', 'sppg'] as $source) {
            RemoteDashboardSourceRefreshPolicy::succeeded($source);
        }
        RemoteDashboardSourceRefreshPolicy::failed('kpi', 'Consumer HTTP 410');
        $this->artisan('dashboard-sources:refresh', ['--queue' => true, '--only-stale' => true])->assertSuccessful();
        Queue::assertNothingPushed();
        $this->artisan('dashboard-sources:refresh', ['--queue' => true, '--source' => 'kpi'])->assertSuccessful();
        Queue::assertPushed(RefreshRemoteDashboardSourcesJob::class, 1);
        Carbon::setTestNow(now()->addMinutes(16));
        $command = app(RefreshRemoteDashboardSourcesCommand::class);
        $this->assertTrue((new ReflectionMethod($command, 'sourceNeedsRefresh'))->invoke($command, 'kpi'));
    }

    private function handle(RefreshRemoteDashboardSourcesJob $job, PublicWorkbookController $workbook, SppgReportService $sppg): void
    {
        $job->handle(
            app(DashboardSimpananController::class), $workbook, app(AlmafactsDashboardController::class),
            app(BusinessClusterReportService::class), $sppg, app(MicroPipelineSyncService::class)
        );
    }

    public function test_queued_cooldown_releases_without_http_or_marking_failed_source_healthy(): void
    {
        RemoteDashboardSourceRefreshPolicy::failed('market-share', '429');
        $status = Cache::get('dashboard_sources:last_refresh:market-share');
        $workbook = Mockery::mock(PublicWorkbookController::class);
        $workbook->shouldNotReceive('refreshMarketShareSource');
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('release')->once()->with(900);
        $job = new RefreshRemoteDashboardSourcesJob(['market-share']);
        $job->setJob($queueJob);
        $this->handle($job, $workbook, app(SppgReportService::class));
        $this->assertSame($status, Cache::get('dashboard_sources:last_refresh:market-share'));
        $this->assertSame(3, $job->maxExceptions);
        $this->assertSame(now()->addDay()->getTimestamp(), $job->retryUntil()->getTimestamp());
    }

    public function test_a_failed_source_does_not_skip_healthy_sibling_and_still_fails_the_job(): void
    {
        $workbook = Mockery::mock(PublicWorkbookController::class);
        $workbook->shouldReceive('refreshMarketShareSource')->once()->andReturn(['success' => false, 'error' => 'HTTP 429']);
        $sppg = Mockery::mock(SppgReportService::class);
        $sppg->shouldReceive('refreshSourceCache')->once()->andReturn(['success' => true]);
        try {
            $this->handle(new RefreshRemoteDashboardSourcesJob(['market-share', 'sppg']), $workbook, $sppg);
            $this->fail('Expected a failure for the unavailable source.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 429', $exception->getMessage());
            $this->assertFalse(Cache::get('dashboard_sources:last_refresh:market-share')['success']);
            $this->assertTrue(Cache::get('dashboard_sources:last_refresh:sppg')['success']);
        }
    }

    public function test_direct_nonqueue_invocation_does_not_report_success_for_a_cooldown_skip(): void
    {
        RemoteDashboardSourceRefreshPolicy::failed('market-share', '429');
        $workbook = Mockery::mock(PublicWorkbookController::class);
        $workbook->shouldNotReceive('refreshMarketShareSource');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refresh ditunda');
        $this->handle(new RefreshRemoteDashboardSourcesJob(['market-share']), $workbook, app(SppgReportService::class));
    }
}
