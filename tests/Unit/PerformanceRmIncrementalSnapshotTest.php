<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceRmIncrementalSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction(
            'REGEXP_REPLACE',
            static fn ($value, $pattern, $replacement): string => preg_replace('/[^0-9]/', (string) $replacement, (string) $value) ?? '',
            3
        );

        Schema::dropAllTables();
        $this->createTables();
    }

    public function test_performance_rm_incremental_rebuild_matches_force_rebuild_and_removes_stale_rows(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 1000, 800, '123');
        DB::table('simpanan_multipn')->insert([
            'posisi' => '2026-05-06',
            'CIFNO' => '123',
            'saldo_idr' => 500,
        ]);

        $builder->rebuildPerformanceRm('2026-05-06', true);

        DB::table('performance_rm_snapshots')->insert($this->stalePerformanceRmRow());
        $this->insertDailyLoanRow('R2', 'KPR', 2000, 1500, '456');
        DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'R1')->update(['baki_debet1' => 900]);

        $incrementalResult = $builder->rebuildPerformanceRm('2026-05-06', false);
        $incrementalRows = $this->snapshotRows('performance_rm_snapshots');
        $incrementalCabangRows = $this->snapshotRows('performance_rm_cabang_snapshots');

        DB::table('performance_rm_cabang_snapshots')->where('periode', '2026-05-06')->delete();
        DB::table('performance_rm_snapshots')->where('periode', '2026-05-06')->delete();

        $forceResult = $builder->rebuildPerformanceRm('2026-05-06', true);

        $this->assertSame($forceResult, $incrementalResult);
        $this->assertSame($this->snapshotRows('performance_rm_snapshots'), $incrementalRows);
        $this->assertSame($this->snapshotRows('performance_rm_cabang_snapshots'), $incrementalCabangRows);
        $this->assertDatabaseMissing('performance_rm_snapshots', [
            'periode' => '2026-05-06',
            'produk' => 'STALE',
        ]);
    }

    public function test_consumer_realisasi_uses_positive_plafon_surplus_against_latest_previous_month_period(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 300000000, 250000000, '123', '2026-04-20');
        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 320000000, 260000000, '123', '2026-04-30');
        $this->insertDailyLoanRow('R2', 'KPR', 200000000, 180000000, '456', '2026-04-30');
        $this->insertDailyLoanRow('R3', 'KPR', 100000000, 90000000, '789', '2026-04-30');

        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 330000000, 270000000, '123', '2026-05-10');
        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 350000000, 290000000, '123', '2026-05-15');
        $this->insertDailyLoanRow('R2', 'KPR', 180000000, 170000000, '456', '2026-05-15');
        $this->insertDailyLoanRow('R4', 'KPR', 125000000, 100000000, '999', '2026-05-15');

        $builder->rebuildPerformanceRm('2026-05-15', true);

        $briguna = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $kpr = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'KPR')
            ->first();

        $this->assertNotNull($briguna);
        $this->assertSame(1, (int) $briguna->realisasi_deb);
        $this->assertSame(30000000.0, (float) $briguna->realisasi_os);
        $this->assertSame(0, (int) $briguna->w1_realisasi_deb);
        $this->assertSame(0.0, (float) $briguna->w1_realisasi_os);

        $this->assertNotNull($kpr);
        $this->assertSame(2, (int) $kpr->realisasi_deb);
        $this->assertSame(5000000.0, (float) $kpr->realisasi_os);
    }

    public function test_consumer_realisasi_ignores_current_plafon_when_previous_group_has_no_valid_basis(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow('R1', 'BRIGUNAKONSUMER', 300000000, 250000000, '123', '2026-04-30');

        $this->insertDailyLoanRow('R3', 'KPR', 125000000, 100000000, '999', '2026-05-15');
        $this->insertDailyLoanRow('R4', 'BRIGUNAKONSUMER', 275000000, 225000000, '789', '2026-05-15');

        $builder->rebuildPerformanceRm('2026-05-15', true);

        $briguna = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();

        $kpr = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-15')
            ->where('segmen', 'CONSUMER')
            ->where('produk', 'KPR')
            ->first();

        $this->assertNotNull($briguna);
        $this->assertSame(0, (int) $briguna->realisasi_deb);
        $this->assertSame(0.0, (float) $briguna->realisasi_os);

        $this->assertNotNull($kpr);
        $this->assertSame(0, (int) $kpr->realisasi_deb);
        $this->assertSame(0.0, (float) $kpr->realisasi_os);
    }

    public function test_performance_rm_quality_uses_kolek_instead_of_kol_adk1(): void
    {
        $builder = new ReportSnapshotBuilder(app(DashboardHarianSnapshotService::class));

        $this->insertDailyLoanRow(
            'SMALL-1',
            'COMMERCIAL',
            100000000,
            90000000,
            'S001',
            '2026-05-06',
            'SMALL',
            1,
            4,
            'Y'
        );

        $builder->rebuildPerformanceRm('2026-05-06', true);

        $snapshot = DB::table('performance_rm_snapshots')
            ->where('periode', '2026-05-06')
            ->where('segmen', 'SMALL')
            ->where('produk', 'SMALL')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, (float) $snapshot->lancar_os);
        $this->assertSame(0.0, (float) $snapshot->restruk_os);
        $this->assertSame(0.0, (float) $snapshot->sml_os);
        $this->assertSame(90000000.0, (float) $snapshot->npl_os);
        $this->assertSame(0, (int) $snapshot->lancar_deb);
        $this->assertSame(1, (int) $snapshot->npl_deb);
    }

    private function createTables(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('description')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->integer('kol_adk1')->nullable();
            $table->integer('kolek')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->date('tgl_realisasi')->nullable();
        });

        Schema::create('simpanan_multipn', function (Blueprint $table): void {
            $table->id();
            $table->date('posisi')->nullable();
            $table->string('CIFNO')->nullable();
            $table->decimal('saldo_idr', 20, 2)->nullable();
        });

        Schema::create('performance_rm_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('cabang', 100);
            $table->string('unit', 100);
            $table->string('branch_code', 100)->nullable();
            $table->string('rm', 255);
            $table->string('segmen', 50);
            $table->string('produk', 100);
            $table->decimal('plafon', 20, 2)->default(0);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->integer('lancar_deb')->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->integer('sml_deb')->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('npl_deb')->default(0);
            $table->decimal('restruk_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
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
            $table->decimal('total_deposit', 20, 2)->default(0);
            $table->tinyInteger('quadrant')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_rm_cabang_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('cabang', 100);
            $table->string('segmen', 50);
            $table->string('produk', 100);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->integer('lancar_deb')->default(0);
            $table->integer('sml_deb')->default(0);
            $table->integer('npl_deb')->default(0);
            $table->decimal('restruk_os', 20, 2)->default(0);
            $table->integer('realisasi_deb')->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
            $table->decimal('total_deposit', 20, 2)->default(0);
            $table->decimal('plafon', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    private function insertDailyLoanRow(
        string $account,
        string $product,
        int $plafon,
        int $bakiDebet,
        string $cif,
        string $period = '2026-05-06',
        string $segment = 'CONSUMER',
        int $kolAdk = 1,
        int $kolek = 1,
        string $flagRestruk = ''
    ): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => $period,
            'segmen_kinerja' => $segment,
            'produk_kinerja' => $product,
            'description' => '',
            'cabang_normalized' => 'KC MADIUN',
            'unit_normalized' => 'UNIT A',
            'branch_normalized' => '123',
            'rm_normalized' => 'RM A',
            'plafon' => $plafon,
            'baki_debet1' => $bakiDebet,
            'kol_adk1' => $kolAdk,
            'kolek' => $kolek,
            'flag_restruk' => $flagRestruk,
            'nomor_rekening1' => $account,
            'pn_pengelola1' => 'RM A',
            'cifno_clean' => $cif,
            'tgl_realisasi' => $period,
        ]);
    }

    private function stalePerformanceRmRow(): array
    {
        return [
            'periode' => '2026-05-06',
            'cabang' => 'KC MADIUN',
            'unit' => 'UNIT A',
            'branch_code' => '123',
            'rm' => 'RM A',
            'segmen' => 'CONSUMER',
            'produk' => 'STALE',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function snapshotRows(string $table): array
    {
        $query = DB::table($table)->where('periode', '2026-05-06');
        foreach (['cabang', 'unit', 'rm', 'segmen', 'produk'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $query->orderBy($column);
            }
        }

        return $query->get()
            ->map(function ($row): array {
                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                return array_map(static fn ($value) => is_numeric($value) ? (string) $value : $value, $data);
            })
            ->all();
    }
}
