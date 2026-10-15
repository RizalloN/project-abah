<?php

namespace Tests\Feature;

use App\Models\DriveAsixFile;
use App\Models\User;
use App\Support\UserBranchScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationLayoutRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        $this->withoutVite();
        session()->start();
        Auth::setUser(new User([
            'id' => 1,
            'name' => 'Notification Layout Admin',
            'pn' => '80000001',
            'role' => 'admin',
            'branch_scope' => UserBranchScope::AREA_SCOPE,
        ]));
        View::share('errors', new ViewErrorBag);
    }

    #[DataProvider('notificationPages')]
    public function test_real_blade_pages_render_the_local_library_and_shared_theme_once_in_dependency_order(string $view): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $html = view($view, $this->fixtureData($view))->render();

        $library = 'adminlte/plugins/sweetalert2/sweetalert2.all.min.js';
        $this->assertSame(1, substr_count($html, $library), $view);
        $this->assertSame(1, substr_count($html, 'id="import-swal-modern-styles"'), $view);
        $this->assertSame(1, substr_count($html, 'id="import-swal-modern-scripts"'), $view);
        $this->assertFalse(str_contains($html, 'cdn.jsdelivr.net/npm/sweetalert2'), 'Remote SweetAlert still present on '.$view);
        $this->assertFalse(str_contains($html, '@include('), 'Uncompiled include on '.$view);
        $this->assertFalse(str_contains($html, '@once'), 'Uncompiled once directive on '.$view);
        $this->assertLessThan(
            strpos($html, 'id="import-swal-modern-scripts"'),
            strpos($html, $library),
            'SweetAlert must load before the shared theme on '.$view
        );
    }

    public function test_rendered_import_previews_preserve_source_values_filter_inputs_and_submission_routes(): void
    {
        foreach (['import.preview', 'import.preview_excel'] as $view) {
            $html = view($view, $this->fixtureData($view))->render();
            foreach (['UNIT &lt;A&gt; &amp; B', '12345.67', 'name="active_filters_json"', 'name="_token"', 'id="btnSubmitImport"', 'name="delimiter"'] as $expected) {
                $this->assertTrue(str_contains($html, $expected), 'Missing '.$expected.' on '.$view);
            }
            $statusRoute = json_encode(route('import.jobs.status', ['jobId' => '__JOB_ID__']), JSON_THROW_ON_ERROR);
            $this->assertTrue(str_contains($html, $statusRoute), 'Missing encoded status route on '.$view);
        }

        $csv = view('import.preview', $this->fixtureData('import.preview'))->render();
        $this->assertTrue(str_contains($csv, 'action="'.route('import.process').'"'), 'Missing CSV import action');
        $this->assertTrue(str_contains($csv, 'name="file_path" value="fixture/source.csv"'), 'Missing CSV source path');
        $excel = view('import.preview_excel', $this->fixtureData('import.preview_excel'))->render();
        $this->assertTrue(str_contains($excel, 'value="fixture/source.xlsx"'), 'Missing Excel source path');
        $excelInitRoute = route('import.excel.init');
        $this->assertTrue(str_contains($excel, $excelInitRoute), 'Missing Excel init route');
    }

    public static function notificationPages(): iterable
    {
        foreach ([
            'import.index',
            'import.report-management',
            'import.job-management',
            'import.preview',
            'import.preview_excel',
            'input.import-preview',
            'input.bod-boc-import-preview',
            'admin.user-management',
            'admin.file-management',
            'drive.index',
            'drive.spreadsheet-editor',
            'dashboard',
        ] as $view) {
            yield $view => [$view];
        }
    }

    private function fixtureData(string $view): array
    {
        $report = (object) [
            'id_report' => 1,
            'nama_report' => 'Notification Fixture Report',
            'table_name' => 'notification_fixture',
        ];
        $headers = ['NAMA_UKER', 'NILAI'];
        $rows = [['UNIT <A> & B', '12345.67']];

        return match ($view) {
            'import.index' => ['reports' => collect([$report]), 'downloadTemplates' => []],
            'import.report-management' => ['reports' => collect([$report]), 'backupFiles' => []],
            'import.job-management' => [],
            'import.preview' => [
                'headers' => $headers,
                'previewData' => $rows,
                'filePath' => 'fixture/source.csv',
                'currentDelimiter' => ',',
                'formattedUniqueValues' => [['UNIT <A> & B'], ['12345.67']],
            ],
            'import.preview_excel' => [
                'headers' => $headers,
                'preview' => $rows,
                'path' => 'fixture/source.xlsx',
                'formattedUniqueValues' => [['UNIT <A> & B'], ['12345.67']],
            ],
            'input.import-preview', 'input.bod-boc-import-preview' => [
                'previewRows' => [['cif' => '000123', 'perusahaan_anak' => 'UNIT <A> & B', 'instansi' => 'UNIT <A> & B']],
                'sourceName' => 'Notification Fixture.xlsx',
                'periode' => '2026-10-14',
            ],
            'admin.user-management' => [
                'users' => collect(),
                'stats' => ['total' => 0, 'admins' => 0, 'users' => 0],
                'branchScopeOptions' => [UserBranchScope::AREA_SCOPE => 'Area 6 (Semua Cabang)'],
            ],
            'admin.file-management' => [
                'directories' => [],
                'files' => collect(),
                'totals' => ['files' => 0, 'size_human' => '0 B'],
            ],
            'drive.index' => [
                'currentFolder' => null,
                'breadcrumbs' => [],
                'folders' => collect(),
                'files' => collect(),
                'folderId' => null,
                'allFolders' => [],
                'trashedFiles' => collect(),
                'trashedCount' => 0,
            ],
            'drive.spreadsheet-editor' => [
                'file' => (new DriveAsixFile)->forceFill(['id' => 123, 'original_name' => 'Notification Fixture.xlsx']),
                'backUrl' => route('drive.index'),
            ],
            'dashboard' => ['dashboard' => [], 'selectedPeriod' => '2026-10-14'],
        };
    }
}
