<?php

namespace Tests\Unit;

use App\Support\LandingConsumerOperationalService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LandingConsumerOperationalServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();
        Cache::flush();

        Schema::create('performance_rm_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('cabang');
            $table->string('segmen');
            $table->string('produk');
            $table->string('rm');
            $table->decimal('realisasi_os', 20, 2)->default(0);
        });
        Schema::create('performance_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('category');
            $table->string('rm_name');
            $table->decimal('target_os', 20, 2)->default(0);
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->id();
            $table->string('pernr')->nullable();
            $table->string('completename')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('psadesc')->nullable();
        });
    }

    public function test_pipeline_parser_ranks_top_ten_by_potensi_briguna_and_accepts_caruban_alias(): void
    {
        $service = app(LandingConsumerOperationalService::class);
        $rows = collect(range(1, 12))->map(function (int $index): string {
            $potential = $index === 2 ? '(3)' : (string) ($index * 10);

            return implode(',', [
                '"Instansi '.$index.'"',
                '"RM '.$index.'"',
                '"'.$potential.'"',
                '"'.($index * 20).'"',
                '"90%"',
            ]);
        })->push('"TOTAL","","9999","",""')->implode("\n");
        $csv = "\xEF\xBB\xBF\"Nama Instansi \" ,\"RM PIC 1 \" ,\"Potensi Brig \" ,\"Total Pegawai \" ,\"Sudah Terlayani \"\n".$rows;

        $payload = $service->parseInstitutionCsv($csv, [
            'key' => 'caruban',
            'branch_key' => 'madiun',
            'branch' => 'KC Madiun',
            'label' => 'KCP Caruban',
            'spreadsheet_id' => 'sheet-id',
            'sheet' => 'KCP Caruban',
        ]);

        $this->assertSame(10, $payload['row_count']);
        $this->assertSame('Instansi 12', $payload['rows'][0]['institution']);
        $this->assertSame(120, $payload['rows'][0]['potential']);
        $this->assertSame(['RM 12'], $payload['rows'][0]['rm_names']);
        $this->assertSame('90%', $payload['rows'][0]['served']);
        $this->assertNotContains('TOTAL', array_column($payload['rows'], 'institution'));
    }

    public function test_kpr_pipeline_parser_keeps_active_debtors_and_source_order(): void
    {
        $csv = <<<'CSV'
No,Kode Uker,Branch Office,Nama RM,DIINPUT RM Nama Debitur,Fasilitas,Jenis Income,Nama Developer,Tgl Rencana Real,Plafond (dalam jutaan),PLAFON DALAM JUTAAN Keterangan Proses
1,45,Madiun,RM Pertama,Debitur Pertama,FLPP,Fixed,Developer A,12,2500,Collect Data
2,45,Madiun,RM Pertama,,,,,13,0,
3,45,Madiun,RM Kedua,Debitur Kedua,ETB,Non Fixed,Developer B,18,160,Diputus
CSV;

        $payload = app(LandingConsumerOperationalService::class)->parseKprPipelineCsv($csv, [
            'key' => 'kpr-madiun',
            'branch_key' => 'madiun',
            'branch' => 'KC Madiun',
            'label' => 'KC Madiun',
            'spreadsheet_id' => 'sheet-id',
            'sheet' => 'Madiun',
        ]);

        $this->assertTrue($payload['available']);
        $this->assertSame(2, $payload['row_count']);
        $this->assertSame(2, $payload['rm_count']);
        $this->assertSame(2660.0, $payload['total_plafond_juta']);
        $this->assertSame('Debitur Pertama', $payload['rows'][0]['debtor']);
        $this->assertSame('Collect Data', $payload['rows'][0]['process']);
        $this->assertSame('3', $payload['rows'][1]['source_number']);
    }

    public function test_payload_scopes_madiun_sources_and_separates_briguna_from_kpr_quadrants(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'sheet=Madiun')) {
                return Http::response(
                    "No,Branch Office,Nama RM,DIINPUT RM Nama Debitur,Fasilitas,Jenis Income,Nama Developer,Tgl Rencana Real,Plafond (dalam jutaan),PLAFON DALAM JUTAAN Keterangan Proses\n1,Madiun,RM K,Debitur K,FLPP,Fixed,Developer K,12,100,Diputus",
                    200
                );
            }

            return Http::response("Nama Instansi,Potensi Briguna,RM PIC 1\nInstansi A,100,RM A", 200);
        });

        DB::table('performance_targets')->insert([
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'RM A', 'target_os' => 100],
            ['category' => 'KPR', 'rm_name' => 'RM K', 'target_os' => 100],
        ]);
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '001 - RM A', 105),
            $this->snapshot('2026-02-28', 'BRIGUNA-KONSUMER', '001 - RM A', 95),
            $this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '999 - TANPA TARGET', 500),
            $this->snapshot('2026-02-28', 'BRIGUNA-KONSUMER', '999 - TANPA TARGET', 500),
            $this->snapshot('2026-01-31', 'KPR', '002 - RM K', 50),
            $this->snapshot('2026-02-28', 'KPR', '002 - RM K', 0),
        ]);

        $payload = app(LandingConsumerOperationalService::class)->payload(
            '2026-02-28',
            UserBranchScope::forKey('madiun'),
            true
        );

        $this->assertSame(['madiun', 'caruban'], array_column($payload['pipeline']['sources'], 'key'));
        $this->assertTrue($payload['kpr_pipeline']['visible']);
        $this->assertTrue($payload['kpr_pipeline']['available']);
        $this->assertSame(1, $payload['kpr_pipeline']['row_count']);
        $this->assertSame('Debitur K', $payload['kpr_pipeline']['rows'][0]['debtor']);
        $this->assertCount(1, $payload['quadrants']['branches']);
        $branch = $payload['quadrants']['branches'][0];
        $this->assertSame('madiun', $branch['key']);
        $briguna = $branch['products']['briguna'];
        $kpr = $branch['products']['kpr'];

        $this->assertSame(1, $briguna['rows'][0]['q1']);
        $this->assertSame(
            [['name' => 'RM A', 'branch' => 'KC Madiun']],
            $briguna['rows'][0]['rm_details']['q1']
        );
        $this->assertSame(1, $briguna['rows'][1]['q3']);
        $this->assertSame(1, $briguna['coverage']['classified']);
        $this->assertSame(1, $briguna['coverage']['unclassified']);
        $this->assertSame(1, $kpr['rows'][0]['q3']);
        $this->assertSame(1, $kpr['rows'][1]['q4']);

        $magetanPayload = app(LandingConsumerOperationalService::class)->payload(
            '2026-02-28',
            UserBranchScope::forKey('magetan'),
            true
        );
        $this->assertFalse($magetanPayload['kpr_pipeline']['visible']);
    }

    public function test_area6_quadrant_trigger_aggregates_madiun_magetan_ngawi_and_ponorogo(): void
    {
        Http::fake(fn () => Http::response("Nama Instansi,Potensi Briguna,RM PIC 1\nInstansi A,100,RM A", 200));
        DB::table('performance_targets')->insert([
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'RM MADIUN', 'target_os' => 100],
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'RM MAGETAN', 'target_os' => 100],
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'RM NGAWI', 'target_os' => 100],
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'RM PONOROGO', 'target_os' => 100],
        ]);
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '001 - RM MADIUN', 105),
            [...$this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '002 - RM MAGETAN', 100), 'cabang' => 'KC MAGETAN'],
            [...$this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '003 - RM NGAWI', 75), 'cabang' => 'KC NGAWI'],
            [...$this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '004 - RM PONOROGO', 25), 'cabang' => 'KC PONOROGO'],
        ]);

        $payload = app(LandingConsumerOperationalService::class)->payload('2026-01-31', null, true);

        $this->assertSame(
            ['area6', 'madiun', 'magetan', 'ngawi', 'ponorogo'],
            array_column($payload['quadrants']['branches'], 'key')
        );
        $area6 = data_get($payload, 'quadrants.branches.0');
        $latest = data_get($area6, 'products.briguna.rows.0');
        $this->assertSame('Area 6 (4 KC)', data_get($area6, 'label'));
        $this->assertSame(1, data_get($latest, 'q1'));
        $this->assertSame(1, data_get($latest, 'q2'));
        $this->assertSame(1, data_get($latest, 'q3'));
        $this->assertSame(1, data_get($latest, 'q4'));
        $this->assertSame(4, data_get($latest, 'total'));
        $this->assertSame(
            [['name' => 'RM NGAWI', 'branch' => 'KC Ngawi']],
            data_get($latest, 'rm_details.q3')
        );
        $this->assertSame(4, data_get($area6, 'products.briguna.coverage.classified'));
        $this->assertSame(0, data_get($area6, 'products.briguna.coverage.unclassified'));

        $html = view('dashboard.partials.consumer-operations', [
            'consumerOperations' => $payload,
        ])->render();
        $this->assertSame(2, substr_count($html, 'data-consumer-area6-trigger="1"'));
        $this->assertStringContainsString('data-consumer-quadrant-detail', $html);
        $this->assertStringContainsString('RM NGAWI', html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
    }

    public function test_consumer_quadrants_use_brihc_roster_before_daily_loan_snapshot_and_keep_daily_fallback(): void
    {
        Http::fake(fn () => Http::response("Nama Instansi,Potensi Briguna,RM PIC 1\nInstansi A,100,RM A", 200));
        DB::table('performance_targets')->insert([
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'BRIHC BRIGUNA', 'target_os' => 100],
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'DAILY BACKUP', 'target_os' => 100],
            ['category' => 'BRIGUNA-KONSUMER', 'rm_name' => 'BRIHC NOL', 'target_os' => 100],
        ]);
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshot('2026-02-28', 'BRIGUNA-KONSUMER', '00000001 - DAILY LAMA', 210),
            $this->snapshot('2026-02-28', 'BRIGUNA-KONSUMER', '00000002 - SUDAH PINDAH PRODUK', 100),
            $this->snapshot('2026-02-28', 'BRIGUNA-KONSUMER', '00000003 - DAILY BACKUP', 100),
        ]);
        DB::table('brihc_pemasar')->insert([
            [
                'pernr' => '00000001',
                'completename' => 'BRIHC BRIGUNA',
                'positiondesc' => 'RM BISNIS KONSUMER - BRIGUNA',
                'psadesc' => 'KC Madiun',
            ],
            [
                'pernr' => '00000002',
                'completename' => 'BRIHC KPR',
                'positiondesc' => 'RM BISNIS KONSUMER - KPR',
                'psadesc' => 'KC Madiun',
            ],
            [
                'pernr' => '00000004',
                'completename' => 'BRIHC NOL',
                'positiondesc' => 'RM BISNIS KONSUMER - BRIGUNA',
                'psadesc' => 'KC Madiun',
            ],
        ]);

        $payload = app(LandingConsumerOperationalService::class)->payload(
            '2026-02-28',
            UserBranchScope::forKey('madiun'),
            true
        );
        $latest = data_get($payload, 'quadrants.branches.0.products.briguna.rows.0');
        $this->assertSame(1, data_get($latest, 'q1'));
        $this->assertSame(1, data_get($latest, 'q2'));
        $this->assertSame(1, data_get($latest, 'q4'));
        $this->assertSame(3, data_get($latest, 'total'));
        $this->assertSame(3, data_get($payload, 'quadrants.branches.0.products.briguna.coverage.classified'));
        $this->assertSame(4, data_get($payload, 'quadrants.branches.0.products.briguna.coverage.source_total'));
        $this->assertSame(1, data_get($payload, 'quadrants.branches.0.products.briguna.coverage.unclassified'));
    }

    public function test_consumer_history_uses_latest_snapshot_branch_at_requested_cutoff_not_current_brihc_branch(): void
    {
        DB::table('brihc_pemasar')->insert([
            [
                'pernr' => '00000001',
                'completename' => 'RM REFERENSI',
                'positiondesc' => 'RM BISNIS KONSUMER - BRIGUNA',
                'psadesc' => 'KC Ponorogo',
            ],
            [
                'pernr' => '00000002',
                'completename' => 'RM KPR DI LUAR SCOPE',
                'positiondesc' => 'RM BISNIS KONSUMER - KPR',
                'psadesc' => 'KC Madiun',
            ],
        ]);
        $rows = collect([
            (object) [
                'periode' => '2026-01-31',
                'cabang' => 'KC MADIUN',
                'unit' => 'KC MADIUN',
                'branch_code' => '45',
                'produk' => 'BRIGUNA-KONSUMER',
                'rm' => '00000001 - RM LAMA',
                'realisasi_os' => 100.0,
            ],
            (object) [
                'periode' => '2026-02-28',
                'cabang' => 'KC MAGETAN',
                'unit' => 'KC MAGETAN',
                'branch_code' => '49',
                'produk' => 'BRIGUNA-KONSUMER',
                'rm' => '00000001 - RM LAMA',
                'realisasi_os' => 200.0,
            ],
        ]);

        $service = app(LandingConsumerOperationalService::class);
        $mapped = $service->applyLatestConsumerSnapshotAssignments(
            $service->applyBrihcPrimaryConsumerAssignments($rows, '2026-02-28')
        );

        $this->assertSame(['KC MAGETAN'], $mapped->pluck('cabang')->unique()->values()->all());
        $this->assertSame(['KC MAGETAN'], $mapped->pluck('unit')->unique()->values()->all());
        $this->assertSame(['49'], $mapped->pluck('branch_code')->unique()->values()->all());
        $this->assertSame(['00000001 - RM REFERENSI'], $mapped->pluck('rm')->unique()->values()->all());
        $this->assertSame(['BRIGUNA-KONSUMER'], $mapped->pluck('produk')->unique()->values()->all());
    }

    public function test_direct_branch_payload_filters_after_latest_area6_assignment(): void
    {
        Http::fake(fn () => Http::response("Nama Instansi,Potensi Briguna,RM PIC 1\nInstansi A,100,RM A", 200));
        DB::table('performance_targets')->insert([
            'category' => 'BRIGUNA-KONSUMER',
            'rm_name' => 'RM PINDAH',
            'target_os' => 100,
        ]);
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '00000001 - RM PINDAH', 105),
            [...$this->snapshot('2026-02-28', 'BRIGUNA-KONSUMER', '00000001 - RM PINDAH', 95), 'cabang' => 'KC MAGETAN'],
        ]);

        $service = app(LandingConsumerOperationalService::class);
        $areaPayload = $service->payload('2026-02-28', null, true);
        $madiunPayload = $service->payload('2026-02-28', UserBranchScope::forKey('madiun'), true);
        $magetanPayload = $service->payload('2026-02-28', UserBranchScope::forKey('magetan'), true);

        $areaBranches = collect(data_get($areaPayload, 'quadrants.branches'))
            ->keyBy('key');
        $this->assertSame(
            data_get($areaBranches->get('madiun'), 'products'),
            data_get($madiunPayload, 'quadrants.branches.0.products')
        );
        $this->assertSame(
            data_get($areaBranches->get('magetan'), 'products'),
            data_get($magetanPayload, 'quadrants.branches.0.products')
        );
        $this->assertSame(0, data_get($madiunPayload, 'quadrants.branches.0.products.briguna.coverage.classified'));
        $this->assertSame(1, data_get($magetanPayload, 'quadrants.branches.0.products.briguna.coverage.classified'));
    }

    public function test_quadrant_period_uses_latest_snapshot_in_requested_month_like_kpi(): void
    {
        Http::fake(fn () => Http::response("Nama Instansi,Potensi Briguna,RM PIC 1\nInstansi A,100,RM A", 200));
        DB::table('performance_targets')->insert([
            'category' => 'BRIGUNA-KONSUMER',
            'rm_name' => 'RM BULANAN',
            'target_os' => 100,
        ]);
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshot('2026-08-30', 'BRIGUNA-KONSUMER', '00000001 - RM BULANAN', 50),
            $this->snapshot('2026-08-31', 'BRIGUNA-KONSUMER', '00000001 - RM BULANAN', 105),
        ]);

        $payload = app(LandingConsumerOperationalService::class)->payload(
            '2026-08-30',
            UserBranchScope::forKey('madiun'),
            true
        );

        $this->assertSame('2026-08-31', data_get($payload, 'quadrants.period'));
        $this->assertSame(1, data_get($payload, 'quadrants.branches.0.products.briguna.rows.0.q1'));
        $this->assertSame(0, data_get($payload, 'quadrants.branches.0.products.briguna.rows.0.q4'));
    }

    public function test_briguna_quadrant_uses_audited_target_and_dynamic_snapshot_realisation(): void
    {
        Http::fake(fn () => Http::response("Nama Instansi,Potensi Briguna,RM PIC 1\nInstansi A,100,RM A", 200));
        DB::table('performance_targets')->insert([
            'category' => 'BRIGUNA-KONSUMER',
            'rm_name' => 'NOVAN YOGA PRATAMA',
            'target_os' => 1900000000,
        ]);

        foreach (range(1, 8) as $month) {
            $period = now()->setDate(2026, $month, 1)->endOfMonth()->toDateString();
            DB::table('performance_rm_snapshots')->insert(
                $this->snapshot(
                    $period,
                    'BRIGUNA-KONSUMER',
                    '00409222 - NOVAN YOGA PRATAMA',
                    $month === 8 ? 0 : 3550000000
                )
            );
        }

        $payload = app(LandingConsumerOperationalService::class)->payload(
            '2026-08-31',
            UserBranchScope::forKey('madiun'),
            true
        );
        $latest = data_get($payload, 'quadrants.branches.0.products.briguna.rows.7');

        $this->assertSame(0, data_get($latest, 'q1'));
        $this->assertSame(1, data_get($latest, 'q4'));
    }

    public function test_consumer_quadrant_sums_all_snapshot_fragments_for_the_same_rm_and_period(): void
    {
        Http::fake(fn () => Http::response("Nama Instansi,Potensi Briguna,RM PIC 1\nInstansi A,100,RM A", 200));
        DB::table('performance_targets')->insert([
            'category' => 'BRIGUNA-KONSUMER',
            'rm_name' => 'RM FRAGMENT',
            'target_os' => 100,
        ]);
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '001 - RM FRAGMENT', 60),
            $this->snapshot('2026-01-31', 'BRIGUNA-KONSUMER', '001 - RM FRAGMENT', 60),
        ]);

        $payload = app(LandingConsumerOperationalService::class)->payload(
            '2026-01-31',
            UserBranchScope::forKey('madiun'),
            true
        );
        $latest = data_get($payload, 'quadrants.branches.0.products.briguna.rows.0');

        $this->assertSame(1, data_get($latest, 'q1'));
        $this->assertSame(0, data_get($latest, 'q3'));
        $this->assertSame(1, data_get($latest, 'total'));
    }

    public function test_consumer_dashboard_contract_has_separate_product_tables_and_protected_route(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $partial = file_get_contents(resource_path('views/dashboard/partials/consumer-operations.blade.php'));
        $route = app('router')->getRoutes()->getByName('dashboard.consumer-operations');

        $this->assertNotNull($route);
        $this->assertSame('dashboard/consumer-operations', $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('user.branch.scope', $route->gatherMiddleware());
        $this->assertStringContainsString('id="consumer-operations-dashboard"', $view);
        $this->assertStringContainsString('loadConsumerOperations', $view);
        $this->assertStringContainsString('data-consumer-pipeline-tab', $partial);
        $this->assertStringContainsString('Pipeline KPR KC Madiun', $partial);
        $this->assertStringContainsString('Keterangan Proses', $partial);
        $this->assertStringContainsString('data_get($consumerOperations, \'kpr_pipeline\'', $partial);
        $this->assertStringContainsString('Kuadran RM Briguna', $partial);
        $this->assertStringContainsString('Kuadran RM KPR', $partial);
        $this->assertStringContainsString('<th>Kuadran 1</th>', $partial);
        $this->assertStringContainsString('<th>Total RM</th>', $partial);
        $this->assertStringContainsString('consumer-rm-illustration-title', $partial);
        $this->assertStringContainsString('data-consumer-area6-trigger="1"', $partial);
        $this->assertStringContainsString('data-consumer-quadrant-detail', $partial);
        $this->assertStringContainsString('data-consumer-quadrant-rms', $partial);
        $this->assertStringContainsString('openConsumerQuadrantDetails', $view);
        $this->assertStringContainsString("addEventListener('dblclick'", $view);
    }

    /** @return array<string, mixed> */
    private function snapshot(string $period, string $product, string $rm, float $realisation): array
    {
        return [
            'periode' => $period,
            'cabang' => 'KC MADIUN',
            'segmen' => 'CONSUMER',
            'produk' => $product,
            'rm' => $rm,
            'realisasi_os' => $realisation,
        ];
    }
}
