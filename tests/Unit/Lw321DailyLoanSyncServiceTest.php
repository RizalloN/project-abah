<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Support\Lw321DailyLoanSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class Lw321DailyLoanSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('DATEDIFF', static function ($a, $b): ?int {
            if ($a === null || $b === null) {
                return null;
            }

            return (int) floor((strtotime((string) $a) - strtotime((string) $b)) / 86400);
        }, 2);
        $pdo->sqliteCreateFunction('LEAST', static function (...$values) {
            $values = array_values(array_filter($values, static fn ($value): bool => $value !== null));

            return $values === [] ? null : min($values);
        });

        Schema::dropAllTables();
        $this->createSourceTable();
        $this->createTargetTable();

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('acctno')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->date('tgl_ph')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
        });

        Schema::create('dashboard_pinjaman_snapshots', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('account_number')->nullable();
            $table->decimal('loan_balance', 20, 2)->nullable();
            $table->string('quality_bucket')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
        });
    }

    public function test_lw321_materialization_is_idempotent_and_cross_period_matrix_remains_readable(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:BASELINE',
            'periode' => '2026-08-31',
            'nomor_rekening1' => 'ACC-001',
            'baki_debet1' => 100,
            'kolek' => '1',
            'kolek_detail' => 'L',
            'umur_tunggakan' => 0,
            'status_rekening1' => '1',
            'cabang1' => 'KC Madiun',
            'unit1' => 'UNIT KARTOHARJO',
        ]);
        DB::table('lw321pn')->insert($this->sourceRow());
        DB::table('lw325_ph')->insert([
            'periode' => '2026-09-08',
            'acctno' => '999000111222333',
            'pokok' => 0,
        ]);

        $first = app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        $mapped = DB::table('daily_loan_dinamis')
            ->where('periode', '2026-09-08')
            ->where('nomor_rekening1', 'ACC-001')
            ->first();

        $this->assertSame(1, $first['source_rows']);
        $this->assertSame(1, $first['inserted_rows']);
        $this->assertSame(7, (int) $mapped->umur_tunggakan);
        $this->assertSame('2', $mapped->kolek);
        $this->assertSame('DPK 1', $mapped->kolek_detail);
        $this->assertEqualsWithDelta(0.0, (float) $mapped->kolektabilitas_lancar, 0.001);
        $this->assertEqualsWithDelta(80.0, (float) $mapped->kolektabilitas_dpk, 0.001);
        $this->assertSame('Micro', $mapped->segmen_dashboard);
        $this->assertSame('KUR-Mikro', $mapped->produk_dashboard);
        $this->assertSame('MICRO', $mapped->segmen_kinerja);
        $this->assertSame('KURMIKRO', $mapped->produk_kinerja);
        $this->assertSame('00322928 - RM UJI', $mapped->pn_pengelola1);
        $this->assertSame('RM UJI', $mapped->pn_name1);
        $this->assertSame('00322928 - RM UJI', $mapped->rm_normalized);

        $second = app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        $this->assertSame(1, $second['deleted_rows']);
        $this->assertSame(1, $second['inserted_rows']);
        $this->assertSame(2, DB::table('daily_loan_dinamis')->count());

        Queue::fake();
        $controller = new DashboardPinjamanReportController;
        $this->assertSame(['2026-08-31', '2026-09-08'], DB::table('daily_loan_dinamis')->orderBy('periode')->pluck('periode')->all());
        $periodMethod = new ReflectionMethod($controller, 'fetchRecoveryReportPeriods');
        $periodMethod->setAccessible(true);
        $availablePeriods = $periodMethod->invoke($controller);
        $this->assertContains('2026-09-08', $availablePeriods->all(), json_encode($availablePeriods->all()));

        $payload = $controller->data(Request::create(
            '/report/dashboard-pinjaman/data',
            'GET',
            ['periode' => '2026-09-08', 'refresh' => '1']
        ))->getData(true);
        $rows = collect($payload['matrix_rows'])->keyBy('label');

        $this->assertSame('ready', $payload['status'], json_encode($payload));
        $this->assertEqualsWithDelta(100.0, $rows['L']['values'][2], 0.001);
        $this->assertSame('balanced', $payload['reconciliation']['status']);
    }

    public function test_sync_rejects_a_period_already_owned_by_real_daily_loan(): void
    {
        DB::table('lw321pn')->insert($this->sourceRow());
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:CURRENT',
            'periode' => '2026-09-08',
            'nomor_rekening1' => 'OTHER',
            'baki_debet1' => 50,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah dimiliki Daily Loan Dinamis');

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
    }

    public function test_sync_removes_materialized_rows_after_source_period_is_deleted(): void
    {
        DB::table('lw321pn')->insert($this->sourceRow());
        $service = app(Lw321DailyLoanSyncService::class);
        $service->synchronize('2026-09-08');

        DB::table('lw321pn')->where('periode', '2026-09-08')->delete();
        $result = $service->synchronize('2026-09-08');

        $this->assertSame(1, $result['deleted_rows']);
        $this->assertSame(0, $result['inserted_rows']);
        $this->assertFalse(DB::table('daily_loan_dinamis')->where('periode', '2026-09-08')->exists());
    }

    private function createSourceTable(): void
    {
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('kode_kanwil')->nullable();
            $table->string('kanwil')->nullable();
            $table->string('kode_kanca')->nullable();
            $table->string('kanca')->nullable();
            $table->string('kode_uker')->nullable();
            $table->string('uker')->nullable();
            $table->string('currency')->nullable();
            $table->string('ln_type')->nullable();
            $table->string('no_rekening')->nullable();
            $table->string('nama_debitur')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('plafon_dalam_idr', 20, 2)->nullable();
            $table->decimal('balance_dalam_idr', 20, 2)->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_int_pmt_date')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->date('tgl_jatuh_tempo')->nullable();
            $table->date('tgl_menunggak')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('cifno')->nullable();
            $table->string('description')->nullable();
            $table->string('pn_pengelola_singlepn')->nullable();
            $table->string('pn_pengelola_1')->nullable();
            $table->string('pn_pemutus')->nullable();
        });
    }

    private function createTargetTable(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable()->index();
            $table->string('nomor_rekening1')->nullable();
            $table->string('cifno')->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->decimal('nilai_tercatat1', 20, 2)->nullable();
            $table->string('kolek_detail')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->string('kolek')->nullable();
            $table->decimal('kolektabilitas_lancar', 20, 2)->nullable();
            $table->decimal('kolektabilitas_dpk', 20, 2)->nullable();
            $table->decimal('kolektabilitas_kuranglancar', 20, 2)->nullable();
            $table->decimal('kolektabilitas_diragukan', 20, 2)->nullable();
            $table->decimal('kolektabilitas_macet', 20, 2)->nullable();
            $table->string('status_rekening1')->nullable();
            $table->string('ln_type')->nullable();
            $table->date('tgl_jatuh_tempo')->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_pmt_int_date')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('divisi_segmen_dashboard')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('branch1')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_name1')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->string('nama_debitur1')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('tunggakan_pokok', 20, 2)->nullable();
            $table->decimal('tunggakan_bunga', 20, 2)->nullable();
            $table->decimal('tunggakan_penalti', 20, 2)->nullable();
            $table->decimal('npb_pokok_la', 20, 2)->nullable();
            $table->decimal('npb_bunga_la', 20, 2)->nullable();
            $table->decimal('payment_amount', 20, 2)->nullable();
            $table->decimal('pmtamt', 20, 2)->nullable();
            $table->integer('freq_payment')->nullable();
            $table->integer('freq_int_payment')->nullable();
            $table->timestamp('shadow_built_at')->nullable();
            $table->timestamps();
        });
    }

    private function sourceRow(): array
    {
        return [
            'uniqueid_namareport' => 'LW:ROW:1',
            'periode' => '2026-09-08',
            'kode_kanwil' => 'R',
            'kanwil' => 'KANWIL MALANG',
            'kode_kanca' => '45',
            'kanca' => 'KC Madiun',
            'kode_uker' => '3886',
            'uker' => 'UNIT KARTOHARJO',
            'currency' => 'IDR',
            'ln_type' => 'KM',
            'no_rekening' => 'ACC-001',
            'nama_debitur' => 'DEBITUR UJI',
            'plafon' => 150,
            'plafon_dalam_idr' => 150,
            'balance_dalam_idr' => 80,
            'next_pmt_date' => '2026-09-01',
            'next_int_pmt_date' => '2026-09-03',
            'tgl_realisasi' => '2026-01-01',
            'tgl_jatuh_tempo' => '2027-01-01',
            'flag_restruk' => 'N',
            'cifno' => 'CIF-001',
            'description' => 'KREDIT MIKRO - KUR MIKRO BARU',
            'pn_pengelola_singlepn' => '00322928 - RM UJI',
        ];
    }
}
