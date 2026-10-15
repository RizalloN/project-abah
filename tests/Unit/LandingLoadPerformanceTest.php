<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Middleware\FinishResponseBeforeDeferredCallbacks;
use App\Support\ReportCacheVersion;
use App\Support\RkaLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class LandingLoadPerformanceTest extends TestCase
{
    public function test_pending_deferred_work_gets_gzip_body_with_exact_content_length(): void
    {
        $html = str_repeat('<div class="card">Landing</div>', 200);
        $middleware = $this->apacheMiddleware(true);
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br']);

        $response = $middleware->handle($request, fn () => new Response($html, 200, ['Content-Type' => 'text/html']));

        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
        $this->assertSame($html, gzdecode($response->getContent()));
        $this->assertContains('Accept-Encoding', $response->getVary());
        $this->assertTrue($middleware->compressionDisabled);
    }

    public function test_client_without_gzip_gets_identity_body_with_content_length(): void
    {
        $payload = ['rows' => array_fill(0, 200, ['branch' => 'KC Madiun', 'amount' => 123.45])];
        $middleware = $this->apacheMiddleware(true);

        foreach (['', 'identity', 'gzip;q=0', 'br'] as $acceptEncoding) {
            $request = Request::create('/dashboard/loan-analytics', 'GET', server: ['HTTP_ACCEPT_ENCODING' => $acceptEncoding]);
            $response = $middleware->handle($request, fn () => new JsonResponse($payload));

            $this->assertFalse($response->headers->has('Content-Encoding'), $acceptEncoding);
            $this->assertSame(json_encode($payload), $response->getContent());
            $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
        }
    }

    public function test_response_is_untouched_without_deferred_work_or_outside_apache(): void
    {
        $html = str_repeat('x', 5000);
        $request = Request::create('/dashboard', 'GET', server: ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        foreach ([$this->apacheMiddleware(false), new FinishResponseBeforeDeferredCallbacks()] as $middleware) {
            $response = $middleware->handle($request, fn () => new Response($html));

            $this->assertSame($html, $response->getContent());
            $this->assertFalse($response->headers->has('Content-Length'));
            $this->assertFalse($response->headers->has('Content-Encoding'));
        }
    }

    public function test_streamed_head_and_empty_responses_are_never_rewritten(): void
    {
        $middleware = $this->apacheMiddleware(true);
        $get = Request::create('/export', 'GET', server: ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        $streamed = $middleware->handle($get, fn () => new StreamedResponse(fn () => print('data')));
        $this->assertFalse($streamed->headers->has('Content-Length'));

        $empty = $middleware->handle($get, fn () => new Response('', 204));
        $this->assertFalse($empty->headers->has('Content-Length'));

        $head = $middleware->handle(Request::create('/dashboard', 'HEAD'), fn () => new Response(str_repeat('x', 5000)));
        $this->assertFalse($head->headers->has('Content-Length'));
    }

    public function test_real_defer_through_http_kernel_finishes_with_complete_gzip_body(): void
    {
        $this->app->bind(FinishResponseBeforeDeferredCallbacks::class, fn () => new class extends FinishResponseBeforeDeferredCallbacks {
            protected function runningUnderApacheModule(): bool
            {
                return true;
            }

            protected function disableServerCompression(): void
            {
            }
        });
        $html = str_repeat('<p>landing</p>', 500);
        $ran = false;
        \Illuminate\Support\Facades\Route::get('/__landing-defer-probe', function () use ($html, &$ran) {
            defer(function () use (&$ran): void {
                $ran = true;
            });

            return response($html);
        });
        \Illuminate\Support\Facades\Route::get('/__landing-plain-probe', fn () => response($html));

        $deferred = $this->get('/__landing-defer-probe', ['Accept-Encoding' => 'gzip']);
        $this->assertSame('gzip', $deferred->headers->get('Content-Encoding'));
        $this->assertSame((string) strlen($deferred->getContent()), $deferred->headers->get('Content-Length'));
        $this->assertSame($html, gzdecode($deferred->getContent()));
        $this->assertTrue($ran, 'deferred callback still runs after the response');

        $plain = $this->get('/__landing-plain-probe', ['Accept-Encoding' => 'gzip']);
        $this->assertFalse($plain->headers->has('Content-Encoding'));
        $this->assertSame($html, $plain->getContent());
    }

    public function test_landing_cache_version_changes_when_global_reports_are_imported(): void
    {
        Cache::flush();
        $controller = app(DashboardSimpananController::class);
        $version = new ReflectionMethod($controller, 'reportCacheVersion');
        $before = $version->invoke($controller);

        // QRIS, EDC, BRILink, merchant, PIS and IBBIZ imports bump the global scope.
        ReportCacheVersion::bump('global');

        $this->assertNotSame($before, $version->invoke($controller));
    }

    public function test_report_cache_versions_do_not_expire_and_reset(): void
    {
        Cache::flush();
        ReportCacheVersion::bump('pinjaman');
        ReportCacheVersion::bump('pinjaman');

        $this->travel(400)->days();

        $this->assertSame(3, ReportCacheVersion::get('pinjaman'));
        $this->assertSame(4, ReportCacheVersion::bump('pinjaman'));
    }

    public function test_rka_compiled_definitions_keep_token_and_summary_semantics(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();
        Schema::create('rka', function (Blueprint $table) {
            $table->string('kanca')->nullable();
            $table->string('desc_uker')->nullable();
            $table->string('mata_anggaran')->nullable();
            $table->decimal('oct', 20, 2)->nullable();
            $table->integer('tahun')->nullable();
        });
        DB::table('rka')->insert([
            ['kanca' => 'KC Madiun', 'desc_uker' => 'KC Madiun', 'mata_anggaran' => 'A. DPK RETAIL FUNDING TOTAL', 'oct' => 1000, 'tahun' => 2026],
            ['kanca' => 'KC Madiun', 'desc_uker' => '3885-KCP Dolopo', 'mata_anggaran' => 'A.1. DPK RETAIL FUNDING TOTAL', 'oct' => 300, 'tahun' => 2026],
            ['kanca' => 'KC Madiun', 'desc_uker' => '4001-UNIT Caruban', 'mata_anggaran' => 'A.1. DPK RETAIL FUNDING TOTAL', 'oct' => 200, 'tahun' => 2026],
            ['kanca' => 'KC Madiun', 'desc_uker' => '4002-UNIT Wungu', 'mata_anggaran' => 'OTHER', 'oct' => 50, 'tahun' => 2026],
        ]);
        RkaLookupService::clearMemoization();
        $service = new RkaLookupService();
        $definitions = [
            'kcp' => ['mata_anggaran' => ['A.1. DPK RETAIL FUNDING TOTAL'], 'uker_contains_any' => ['KCP']],
            'kc_token' => ['mata_anggaran' => ['A.1. DPK RETAIL FUNDING TOTAL'], 'uker_contains_any' => ['KC']],
            'not_unit' => ['mata_anggaran' => ['A.1. DPK RETAIL FUNDING TOTAL'], 'uker_not_contains_any' => ['UNIT']],
            'empty_filter' => ['mata_anggaran' => []],
            'all' => [],
        ];

        $groups = $service->aggregateByGroup($definitions, 'oct', ['KC MADIUN'], [], 'kanca', 2026);
        $this->assertSame(300.0, $groups['kcp']['KC MADIUN']);
        $this->assertSame([], $groups['kc_token'], 'KC must not match KCP and summary rows stay excluded');
        $this->assertSame(1300.0, $groups['not_unit']['KC MADIUN'], 'alias row and KCP row, units excluded');
        $this->assertSame([], $groups['empty_filter']);
        $this->assertSame(1550.0, $groups['all']['KC MADIUN']);

        $summary = $service->aggregateByGroup($definitions, 'oct', ['KC MADIUN'], ['KC MADIUN'], 'kanca', 2026);
        $this->assertSame(1000.0, $summary['kc_token']['KC MADIUN'], 'explicit summary scope includes the rollup row');
    }

    private function apacheMiddleware(bool $pending): FinishResponseBeforeDeferredCallbacks
    {
        return new class($pending) extends FinishResponseBeforeDeferredCallbacks {
            public bool $compressionDisabled = false;

            public function __construct(private readonly bool $pending)
            {
            }

            protected function runningUnderApacheModule(): bool
            {
                return true;
            }

            protected function hasPendingDeferredCallbacks(): bool
            {
                return $this->pending;
            }

            protected function disableServerCompression(): void
            {
                $this->compressionDisabled = true;
            }
        };
    }
}
