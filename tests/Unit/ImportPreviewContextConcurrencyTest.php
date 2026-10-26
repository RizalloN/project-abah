<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Middleware\RestoreImportPreviewContext;
use App\Services\Import\ExcelImportJobService;
use App\Support\Import\ImportSessionContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ImportPreviewContextConcurrencyTest extends TestCase
{
    private Store $session;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->session = new Store('import-test', new ArraySessionHandler(120));
        $this->session->start();
    }

    public function test_second_upload_does_not_redirect_first_preview_import(): void
    {
        // Tab A: upload report 11 lalu preview (ck-A).
        $this->send('POST', '/import-excel/upload', [], function (): void {
            $this->session->put([
                'excel_path' => 'excel_imports/a.xlsx',
                'active_id_report' => 11,
                'excel_preview_key' => 'ck-A',
                'excel_manual_kanca' => 'KC MADIUN',
            ]);
        });

        // Tab B: upload report 22 sebelum tab A menekan import.
        $this->send('POST', '/import-excel/upload', [], function (): void {
            $this->session->forget(['excel_preview_meta', 'excel_display_filter_map']);
            $this->session->put([
                'excel_path' => 'excel_imports/b.csv',
                'active_id_report' => 22,
                'excel_preview_key' => 'ck-B',
                'excel_manual_kanca' => null,
                'excel_preview_meta' => ['path' => 'excel_imports/b.csv'],
            ]);
        });
        $this->assertSame('excel_imports/b.csv', $this->session->get('excel_path'));

        // Tab A menjalankan import: konteks A dipakai, bukan upload terakhir (B).
        $seen = $this->send('POST', '/import-excel/init', ['preview_state_key' => 'ck-A'], fn (): array => [
            'path' => $this->session->get('excel_path'),
            'report' => $this->session->get('active_id_report'),
            'kanca' => $this->session->get('excel_manual_kanca'),
            'meta' => $this->session->has('excel_preview_meta'),
        ]);
        $this->assertSame(['path' => 'excel_imports/a.xlsx', 'report' => 11, 'kanca' => 'KC MADIUN', 'meta' => false], $seen['result']);
        $this->assertSame(RestoreImportPreviewContext::RESTORED, $seen['attribute']);

        // Tab B tetap mendapatkan konteksnya sendiri.
        $seenB = $this->send('GET', '/import-excel/preview', ['ck' => 'ck-B'], fn (): array => [
            $this->session->get('excel_path'),
            $this->session->get('active_id_report'),
        ]);
        $this->assertSame(['excel_imports/b.csv', 22], $seenB['result']);
    }

    public function test_expired_or_foreign_preview_is_marked_missing(): void
    {
        $seen = $this->send('POST', '/import/init', ['preview_state_key' => 'csv_preview_unknown'], fn () => null);

        $this->assertSame(RestoreImportPreviewContext::MISSING, $seen['attribute']);
    }

    public function test_non_import_requests_are_untouched(): void
    {
        $this->session->put('active_id_report', 5);
        app(ExcelImportJobService::class)->putPreviewState('ck-X', [
            ExcelImportJobService::SESSION_CONTEXT_KEY => ['active_id_report' => 9],
        ]);

        $seen = $this->send('GET', '/dashboard/preview', ['ck' => 'ck-X'], fn () => $this->session->get('active_id_report'));

        $this->assertSame(5, $seen['result']);
        $this->assertNull($seen['attribute']);
    }

    public function test_preview_state_keeps_its_original_context_when_updated_from_another_tab(): void
    {
        $service = app(ExcelImportJobService::class);
        $this->bindRequest(Request::create('/import-excel/preview', 'GET'));

        $this->session->put(['active_id_report' => 11, 'excel_path' => 'excel_imports/a.xlsx']);
        $service->putPreviewState('ck-A', ['id_report' => 11]);

        $this->session->put(['active_id_report' => 22, 'excel_path' => 'excel_imports/b.csv']);
        $service->putPreviewState('ck-A', ['id_report' => 11, 'previewMeta' => ['staged_csv_path' => 'x.csv']]);

        $state = $service->getPreviewState('ck-A');
        $this->assertSame(11, $state[ExcelImportJobService::SESSION_CONTEXT_KEY]['active_id_report']);
        $this->assertSame('excel_imports/a.xlsx', $state[ExcelImportJobService::SESSION_CONTEXT_KEY]['excel_path']);
        $this->assertSame(['staged_csv_path' => 'x.csv'], $state['previewMeta']);
    }

    public function test_context_capture_keeps_null_values_and_restore_is_exact(): void
    {
        $this->session->put(['active_id_report' => 3, 'excel_manual_kanca' => null, 'unrelated' => 'keep']);
        $context = ImportSessionContext::capture($this->session);
        $this->assertSame(['active_id_report' => 3, 'excel_manual_kanca' => null], $context);

        $this->session->put(['active_id_report' => 7, 'excel_manual_kanca' => 'KC NGAWI', 'excel_preview_meta' => ['x' => 1]]);
        ImportSessionContext::restore($this->session, $context);

        $this->assertSame(3, $this->session->get('active_id_report'));
        $this->assertNull($this->session->get('excel_manual_kanca'));
        $this->assertFalse($this->session->has('excel_preview_meta'));
        $this->assertSame('keep', $this->session->get('unrelated'));
    }

    public function test_stream_follows_the_tab_job_owned_by_the_same_user(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
        });
        DB::table('import_jobs')->insert([['id' => 10, 'created_by' => 1], ['id' => 11, 'created_by' => 2]]);

        $resolve = new ReflectionMethod(ImportExcelController::class, 'resolveStreamJobId');
        $controller = app(ImportExcelController::class);
        $request = fn (int $jobId): Request => Request::create('/import-excel/stream', 'GET', ['job_id' => $jobId]);

        // Tamu (tanpa auth) dan session menunjuk job terakhir (11): tab job 10 tetap memantau job 10.
        $this->assertSame(10, $resolve->invoke($controller, $request(10), 11));
        $this->assertSame(11, $resolve->invoke($controller, $request(0), 11));
        $this->assertSame(11, $resolve->invoke($controller, $request(999), 11));

        $this->actingAs(new \App\Models\User(['id' => 1]));
        auth()->user()->id = 1;
        $this->assertSame(10, $resolve->invoke($controller, $request(10), 11));
        auth()->user()->id = 2;
        $this->assertSame(11, $resolve->invoke($controller, $request(10), 11), 'Job milik user lain tidak boleh dipantau.');
    }

    /**
     * @return array{result:mixed, attribute:mixed}
     */
    private function send(string $method, string $uri, array $parameters, callable $controller): array
    {
        $request = Request::create($uri, $method, $parameters);
        $this->bindRequest($request);
        $result = null;

        app(RestoreImportPreviewContext::class)->handle($request, function () use ($controller, &$result) {
            $result = $controller();

            return response('ok');
        });

        return ['result' => $result, 'attribute' => $request->attributes->get(RestoreImportPreviewContext::ATTRIBUTE)];
    }

    private function bindRequest(Request $request): void
    {
        $request->setLaravelSession($this->session);
        app()->instance('request', $request);
    }
}
