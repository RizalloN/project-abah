<?php
namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KinerjaMicroNettRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values), -1);

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('description')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('branch1')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('unit1')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_pemrakarsa1')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->string('cifno')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->integer('kol_adk1')->nullable();
            $table->integer('kolek')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->string('pn_pemutus_normalized')->nullable();
        });
        Schema::create('brihc', function (Blueprint $table) {
            $table->id();
            $table->string('pn')->nullable();
            $table->string('jabatan')->nullable();
        });
        Schema::create('brihc_pemasar', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('completename')->nullable();
            $table->string('nip')->nullable();
            $table->string('pernr')->nullable();
            $table->string('esgdesc')->nullable();
            $table->string('psadesc')->nullable();
            $table->string('orgdesc')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('pn_mantri')->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('performance_rm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('cabang')->nullable();
            $table->string('unit')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('rm')->nullable();
            $table->string('segmen');
            $table->string('produk');
            $table->integer('lancar_deb')->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->integer('sml_deb')->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->integer('npl_deb')->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->integer('realisasi_deb')->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
            foreach (['w1', 'w2', 'w3', 'w4'] as $week) {
                $table->integer($week . '_realisasi_deb')->default(0);
                $table->decimal($week . '_realisasi_os', 20, 2)->default(0);
            }
            $table->integer('lt_250_realisasi_deb')->default(0);
            $table->decimal('lt_250_realisasi_os', 20, 2)->default(0);
            $table->integer('gt_250_realisasi_deb')->default(0);
            $table->decimal('gt_250_realisasi_os', 20, 2)->default(0);
        });

        Cache::forget('report_cache_version:pinjaman');
        Cache::forget('report_cache_version:simpanan');
    }


    private function seedReport(): void
    {
        Queue::fake();
        foreach (['KC MADIUN', 'KC NGAWI'] as $index => $branch) {
            DB::table('daily_loan_dinamis')->insert([
                'periode' => '2026-09-30', 'cabang1' => $branch, 'cabang_normalized' => $branch,
                'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KUPEDES', 'description' => 'Kupedes',
                'nomor_rekening1' => 'MANTRI-SOURCE-'.$index, 'tgl_realisasi' => '2026-01-01',
                'plafon' => 0, 'baki_debet1' => 0,
            ]);
            DB::table('performance_rm_snapshots')->insert([
                'periode' => '2026-09-30', 'cabang' => $branch, 'unit' => 'Unit '.$index,
                'branch_code' => $index === 0 ? '45' : '57', 'rm' => 'RM '.$branch,
                'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO',
                'realisasi_deb' => 1, 'realisasi_os' => ($index + 1) * 100000000,
            ]);
        }
        $user = new User();
        $user->forceFill(['id' => 1, 'pn' => '0045', 'name' => 'Branch tester', 'role' => 'admin']);
        $this->actingAs($user);
    }

    public function test_controller_defaults_to_nett_for_rm_and_mantri_but_keeps_explicit_categories(): void
    {
        $this->seedReport();
        foreach (['rm_mikro_kur', 'mantri'] as $kind) {
            $view = app(KinerjaRmMikroReportController::class)->index(Request::create('/', 'GET', ['kategori_rm' => $kind]));
            $data = $view->getData();
            $this->assertSame('nett_disbursement', $data['selectedReportCategory']);
            $this->assertSame($kind, $data['selectedRmCategory']);
            $this->assertArrayHasKey($kind === 'mantri' ? 'mantriPerformance' : 'rmKurProductivity', $data['payload']['nett_workbook']);
        }
        foreach (['rm_mikro_kur' => 'per_rm', 'mantri' => 'produktivitas_mantri'] as $kind => $category) {
            $view = app(KinerjaRmMikroReportController::class)->index(Request::create('/', 'GET', ['kategori_rm' => $kind, 'kategori_report' => $category]));
            $this->assertSame($category, $view->getData()['selectedReportCategory']);
            $this->assertArrayNotHasKey('nett_workbook', $view->getData()['payload']);
        }
    }

    public function test_authenticated_route_renders_default_nett_and_scopes_payload_to_branch(): void
    {
        $this->seedReport();
        $response = $this->get(route('report.dashboard-pinjaman.kinerjarmmikro'));
        $response->assertOk()->assertViewHas('selectedReportCategory', 'nett_disbursement');
        $payload = $response->viewData('payload');
        $rows = data_get($payload, 'nett_workbook.rmKurProductivity.rows', []);
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('KC MADIUN', $row['cabang']);
        }
        $response->assertSee('micro-rm-sheet', false);

        $otherUser = new User();
        $otherUser->forceFill(['id' => 2, 'pn' => '0057', 'name' => 'Ngawi tester', 'role' => 'admin']);
        $this->actingAs($otherUser);
        $otherResponse = $this->get(route('report.dashboard-pinjaman.kinerjarmmikro'));
        $otherResponse->assertOk();
        $otherRows = data_get($otherResponse->viewData('payload'), 'nett_workbook.rmKurProductivity.rows', []);
        $this->assertNotEmpty($otherRows);
        foreach ($otherRows as $row) {
            $this->assertSame('KC NGAWI', $row['cabang']);
        }
    }

    public function test_authenticated_mantri_route_renders_nett_sheet_selector(): void
    {
        $this->seedReport();
        $response = $this->get(route('report.dashboard-pinjaman.kinerjarmmikro', ['kategori_rm' => 'mantri']));
        $response->assertOk()->assertViewHas('selectedReportCategory', 'nett_disbursement');
        $response->assertSee('micro-mantri-sheet', false);
    }

    public function test_kpi_pages_render_when_route_cache_has_no_import_notification_routes(): void
    {
        $this->seedReport();
        $routes = new \Illuminate\Routing\RouteCollection();
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'import.schema-drift.')) {
                $routes->add($route);
            }
        }
        app('router')->setRoutes($routes);
        app('url')->setRoutes($routes);

        foreach (['rm_mikro_kur', 'mantri'] as $category) {
            $this->get(route('report.dashboard-pinjaman.kinerjarmmikro', ['kategori_rm' => $category]))
                ->assertOk()
                ->assertSee('<h1>Kinerja RM Mikro</h1>', false)
                ->assertDontSee('id="schemaDriftBell"', false);
        }
    }
}

