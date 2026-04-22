<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class DashboardHarianSnapshotServiceTest extends TestCase
{
    private string $originalDefaultConnection;
    private ?string $originalSqliteDatabase;
    private string $tempDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) Config::get('database.default');
        $this->originalSqliteDatabase = Config::get('database.connections.sqlite.database');
        $this->tempDatabase = tempnam(sys_get_temp_dir(), 'abah_snapshot_test_');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->tempDatabase);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        Config::set('database.default', $this->originalDefaultConnection);
        Config::set('database.connections.sqlite.database', $this->originalSqliteDatabase);
        DB::purge('sqlite');

        if (isset($this->tempDatabase) && is_file($this->tempDatabase)) {
            unlink($this->tempDatabase);
        }

        parent::tearDown();
    }

    public function test_npl_rka_metric_definitions_follow_latest_mapping(): void
    {
        $service = new DashboardHarianSnapshotService();

        $reflection = new \ReflectionMethod($service, 'dashboardRkaMetricDefinitions');
        $reflection->setAccessible(true);

        $definitions = $reflection->invoke($service);

        $this->assertSame(['NPL % Total', 'DPK % Total'], $definitions['total_npl_pct_non_commercial']['mata_anggaran']);
        $this->assertSame(['A.1. DPK Retail Funding Total'], $definitions['total_simpanan']['mata_anggaran']);
        $this->assertSame(['KC', 'KCP'], $definitions['simpanan_ritel']['uker_contains_any']);
        $this->assertSame(['UNIT'], $definitions['simpanan_mikro']['uker_contains_any']);
        $this->assertSame(['KC', 'KCP'], $definitions['kecil_non_cashcoll_os']['uker_contains_any']);
        $this->assertSame(['KC', 'KCP'], $definitions['briguna_konsumer_os']['uker_contains_any']);
        $this->assertSame(['KC', 'KCP'], $definitions['kpr_os']['uker_contains_any']);
        $this->assertSame(['KC', 'KCP'], $definitions['kkb_os']['uker_contains_any']);
        $this->assertSame(['UNIT'], $definitions['micro_os']['uker_contains_any']);
        $this->assertSame(['NPL Rp Kecil Non Cash Collateral', 'DPK Rp Kecil Non Cash Collateral'], $definitions['kecil_non_cashcoll_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Kecil Cash Collateral', 'DPK Rp Kecil Cash Collateral'], $definitions['cashcoll_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Medium', 'DPK Rp Medium'], $definitions['medium_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Briguna', 'DPK Rp Briguna'], $definitions['briguna_konsumer_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KPR', 'DPK Rp KPR'], $definitions['kpr_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KKB', 'DPK Rp KKB'], $definitions['kkb_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Mikro', 'DPK Rp Mikro'], $definitions['micro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Briguna Mikro', 'DPK Rp Briguna Mikro'], $definitions['briguna_mikro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp Kupedes Komersial', 'DPK Rp Kupedes Komersial'], $definitions['kupedes_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KUR Mikro', 'DPK Rp KUR Mikro'], $definitions['kur_mikro_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KUR Kecil', 'DPK Rp KUR Kecil'], $definitions['kur_kecil_npl']['mata_anggaran']);
        $this->assertSame(['NPL Rp KPP', 'DPK Rp KPP'], $definitions['kur_kpp_npl']['mata_anggaran']);
    }

    public function test_finalize_rka_metrics_keeps_raw_total_os_value(): void
    {
        $service = new DashboardHarianSnapshotService();

        $reflection = new \ReflectionMethod($service, 'finalizeRkaMetrics');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($service, [
            'total_simpanan' => 2000.0,
            'total_os' => 12345.0,
            'total_sml_pct_non_commercial' => 12.5,
            'total_npl_pct_non_commercial' => 3.5,
            'kecil_non_cashcoll_os' => 100.0,
            'cashcoll_os' => 50.0,
            'medium_os' => 25.0,
            'briguna_konsumer_os' => 10.0,
            'kpr_os' => 5.0,
            'kkb_os' => 5.0,
            'micro_os' => 20.0,
            'giro_ritel' => 300.0,
            'tabungan_ritel' => 200.0,
            'giro_mikro' => 180.0,
            'tabungan_mikro' => 120.0,
            'kecil_non_cashcoll_npl' => 11.0,
            'cashcoll_npl' => 4.0,
            'medium_npl' => 100.0,
            'briguna_konsumer_npl' => 7.0,
            'kpr_npl' => 3.0,
            'kkb_npl' => 2.0,
            'micro_npl' => 20.0,
        ]);

        $this->assertSame(12345.0, $result['total_os']);
        $this->assertSame(215.0, $result['total_os_non_commercial']);
        $this->assertSame(12.5, $result['total_sml_pct_non_commercial']);
        $this->assertSame(3.5, $result['total_npl_pct_non_commercial']);
        $this->assertSame(15.0, $result['sme_npl']);
        $this->assertSame(12.0, $result['consumer_npl']);
        $this->assertSame(47.0, $result['total_npl_abs_non_commercial']);
        $this->assertSame(500.0, $result['casa_ritel']);
        $this->assertSame(300.0, $result['casa_mikro']);
        $this->assertSame(800.0, $result['total_casa']);
        $this->assertSame(100.0, $result['casa_pct']);
        $this->assertSame(1543.125, $result['ldr_non_commercial']);
        $this->assertSame(34.0, $result['ldr_ritel_non_commercial']);
        $this->assertEqualsWithDelta(6.6666666667, $result['ldr_mikro_non_commercial'], 0.0001);
    }

    public function test_source_metadata_signature_changes_when_source_values_change(): void
    {
        $this->createSourceMetadataTables();

        DB::table('ssa_pinjaman')->insert([
            'month_day_year_of_periode' => '2026-04-20',
            'baki_debet' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-04-20',
            'saldo' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cognos_recovery')->insert([
            'periode' => '2026-04-20',
            'total_recovery' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'buildSourceMetadata');
        $reflection->setAccessible(true);

        $before = $reflection->invoke($service, '2026-04-20');

        DB::table('ssa_pinjaman')
            ->where('month_day_year_of_periode', '2026-04-20')
            ->update(['baki_debet' => 1200]);

        $after = $reflection->invoke($service, '2026-04-20');

        $this->assertNotSame($before['source_signature'], $after['source_signature']);
        $this->assertSame(1, $after['source_loan_row_count']);
        $this->assertSame(1, $after['source_savings_row_count']);
        $this->assertSame(1, $after['source_recovery_row_count']);
        $this->assertSame('2026-04-20', $after['source_recovery_period']);
    }

    public function test_snapshot_freshness_accepts_legacy_rows_and_rejects_changed_signature(): void
    {
        $this->createSourceMetadataTables();

        DB::table('dashboard_harian_snapshots')->insert([
            'uniqueid_dhs' => 'legacy',
            'snapshot_period' => '2026-04-20',
            'kanca_key' => 'legacy',
            'unit_key' => 'legacy',
            'source_signature' => null,
        ]);

        $service = new DashboardHarianSnapshotService();
        $reflection = new \ReflectionMethod($service, 'snapshotSourceIsFresh');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($service, '2026-04-20', ['source_signature' => 'new']));

        DB::table('dashboard_harian_snapshots')
            ->where('uniqueid_dhs', 'legacy')
            ->update(['source_signature' => 'old']);

        $this->assertFalse($reflection->invoke($service, '2026-04-20', ['source_signature' => 'new']));
        $this->assertTrue($reflection->invoke($service, '2026-04-20', ['source_signature' => 'old']));
    }

    private function createSourceMetadataTables(): void
    {
        foreach (['dashboard_harian_snapshots', 'ssa_pinjaman', 'ssa_simpanan', 'cognos_recovery', 'lw325_ph'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dhs')->primary();
            $table->date('snapshot_period')->nullable();
            $table->string('kanca_key')->default('');
            $table->string('unit_key')->default('');
            $table->integer('source_row_count')->default(0);
            $table->string('source_signature', 64)->nullable();
            $table->unsignedBigInteger('source_loan_row_count')->nullable();
            $table->unsignedBigInteger('source_savings_row_count')->nullable();
            $table->unsignedBigInteger('source_recovery_row_count')->nullable();
            $table->date('source_recovery_period')->nullable();
            $table->timestamps();
        });

        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_periode')->nullable();
            $table->decimal('baki_debet', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ssa_simpanan', function (Blueprint $table): void {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi')->nullable();
            $table->decimal('saldo', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('cognos_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->decimal('total_recovery', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
            $table->timestamps();
        });
    }
}
