<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use App\Support\DashboardHarianSnapshotService;
use App\Support\Lw321DailyLoanSyncService;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
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
        $effectivePeriodMethod = new ReflectionMethod($controller, 'resolveEffectivePeriod');
        $effectivePeriodMethod->setAccessible(true);
        $this->assertSame('2026-09-08', $effectivePeriodMethod->invoke($controller, null));
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

    public function test_latest_date_wins_across_sources_when_daily_is_newer_than_lw(): void
    {
        DB::table('lw321pn')->insert($this->sourceRow());
        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:NEWER',
            'periode' => '2026-09-09',
            'nomor_rekening1' => 'DAILY-NEWER',
            'baki_debet1' => 50,
        ]);

        Cache::flush();
        $controller = new DashboardPinjamanReportController;
        $method = new ReflectionMethod($controller, 'resolveEffectivePeriod');
        $method->setAccessible(true);

        $this->assertSame('2026-09-09', $method->invoke($controller, null));
    }

    public function test_sync_skips_lw_when_same_period_is_owned_by_real_daily_loan(): void
    {
        DB::table('lw321pn')->insert($this->sourceRow());
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:CURRENT',
            'periode' => '2026-09-08',
            'nomor_rekening1' => 'OTHER',
            'baki_debet1' => 50,
        ]);

        $result = app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');

        $this->assertSame(0, $result['inserted_rows']);
        $this->assertSame(['2026-09-08'], $result['skipped_periods']);
        $this->assertSame('skipped_daily_precedence', $result['validation']['periods']['2026-09-08']['status']);
        $this->assertDatabaseHas('daily_loan_dinamis', [
            'uniqueid_namareport' => 'DAILY:CURRENT',
            'periode' => '2026-09-08',
        ]);
        $this->assertDatabaseHas('lw321pn', [
            'uniqueid_namareport' => 'LW:ROW:1',
            'periode' => '2026-09-08',
        ]);
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

    public function test_deleting_same_date_daily_promotes_the_retained_raw_lw_source(): void
    {
        DB::table('lw321pn')->insert($this->sourceRow());
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:CURRENT',
            'periode' => '2026-09-08',
            'nomor_rekening1' => 'DAILY-ACCOUNT',
            'baki_debet1' => 50,
        ]);

        $first = app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        $this->assertSame(['2026-09-08'], $first['skipped_periods']);

        DB::table('daily_loan_dinamis')
            ->where('periode', '2026-09-08')
            ->where('uniqueid_namareport', 'not like', Lw321DailyLoanSyncService::TARGET_ID_PREFIX.'%')
            ->delete();

        $sync = new ReportDataSyncService(
            $this->createMock(ReportSnapshotBuilder::class),
            $this->createMock(DashboardHarianSnapshotService::class),
            $this->createMock(PartitionMaintenanceService::class),
            $this->createMock(DashboardHarianSnapshotDirtyPeriodQueue::class)
        );
        $cleanup = $sync->cleanupDerivedArtifactsAfterDelete(
            'daily_loan_dinamis',
            '2026-09-08',
            'unit-test'
        );

        $this->assertSame(1, $cleanup['lw321_fallback_inserted_rows']);
        $this->assertDatabaseHas('daily_loan_dinamis', [
            'periode' => '2026-09-08',
            'nomor_rekening1' => 'ACC-001',
        ]);
        $this->assertStringStartsWith(
            Lw321DailyLoanSyncService::TARGET_ID_PREFIX,
            (string) DB::table('daily_loan_dinamis')->where('periode', '2026-09-08')->value('uniqueid_namareport')
        );
    }

    public function test_invalid_lw_fallback_is_reported_after_stale_snapshot_rows_are_removed(): void
    {
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'balance_dalam_idr' => null,
        ]));
        DB::table('dashboard_pinjaman_snapshots')->insert([
            'periode' => '2026-09-08',
            'account_number' => 'STALE',
            'loan_balance' => 50,
        ]);

        $sync = new ReportDataSyncService(
            $this->createMock(ReportSnapshotBuilder::class),
            $this->createMock(DashboardHarianSnapshotService::class),
            $this->createMock(PartitionMaintenanceService::class),
            $this->createMock(DashboardHarianSnapshotDirtyPeriodQueue::class)
        );

        try {
            $sync->cleanupDerivedArtifactsAfterDelete('daily_loan_dinamis', '2026-09-08', 'unit-test');
            $this->fail('Fallback invalid harus dilaporkan setelah cleanup snapshot.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('fallback LW321', $exception->getMessage());
            $this->assertStringContainsString('balance_dalam_idr NULL', $exception->getMessage());
        }

        $this->assertDatabaseMissing('dashboard_pinjaman_snapshots', [
            'periode' => '2026-09-08',
            'account_number' => 'STALE',
        ]);
    }

    public function test_invalid_source_does_not_replace_existing_materialized_target(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => Lw321DailyLoanSyncService::TARGET_ID_PREFIX.'EXISTING',
            'periode' => '2026-09-08',
            'nomor_rekening1' => 'OLD-ACCOUNT',
            'baki_debet1' => 125,
        ]);
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'balance_dalam_idr' => null,
        ]));

        try {
            app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
            $this->fail('Sinkronisasi seharusnya menolak saldo source NULL.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('balance_dalam_idr NULL', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('daily_loan_dinamis')->count());
        $this->assertDatabaseHas('daily_loan_dinamis', [
            'uniqueid_namareport' => Lw321DailyLoanSyncService::TARGET_ID_PREFIX.'EXISTING',
            'nomor_rekening1' => 'OLD-ACCOUNT',
            'baki_debet1' => 125,
        ]);
    }

    public function test_sync_rejects_blank_account_and_duplicate_exact_account(): void
    {
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'no_rekening' => ' ',
        ]));

        try {
            app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
            $this->fail('Sinkronisasi seharusnya menolak nomor rekening kosong.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nomor rekening kosong', $exception->getMessage());
        }

        DB::table('lw321pn')->delete();
        DB::table('lw321pn')->insert([
            $this->sourceRow(),
            array_merge($this->sourceRow(), [
                'uniqueid_namareport' => 'LW:ROW:2',
            ]),
        ]);

        try {
            app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
            $this->fail('Sinkronisasi seharusnya menolak rekening exact duplikat.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nomor rekening duplikat', $exception->getMessage());
        }

        $this->assertFalse(DB::table('daily_loan_dinamis')->where('periode', '2026-09-08')->exists());
    }

    public function test_sync_rejects_accounts_that_only_differ_by_leading_zeroes(): void
    {
        DB::table('lw321pn')->insert([
            array_merge($this->sourceRow(), [
                'no_rekening' => '000123456',
            ]),
            array_merge($this->sourceRow(), [
                'uniqueid_namareport' => 'LW:ROW:2',
                'no_rekening' => '123456',
                'cifno' => 'CIF-002',
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('normalisasi nol di depan');

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
    }

    public function test_sync_rejects_an_all_zero_balance_dataset(): void
    {
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'balance_dalam_idr' => 0,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('seluruh 1 baris memiliki saldo nol');

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
    }

    public function test_sync_rejects_a_nonblank_description_outside_the_mapping_reference(): void
    {
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'description' => 'PRODUK BARU YANG BELUM DIPETAKAN',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pasangan segmen/produk');

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
    }

    public function test_sync_rejects_a_blank_description_instead_of_silently_dropping_its_classification(): void
    {
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'description' => '',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pasangan segmen/produk');

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
    }

    public function test_sync_accepts_zero_balance_rows_when_dataset_has_nonzero_balance_and_reports_parity(): void
    {
        DB::table('lw321pn')->insert([
            array_merge($this->sourceRow(), [
                'balance_dalam_idr' => 0,
            ]),
            array_merge($this->sourceRow(), [
                'uniqueid_namareport' => 'LW:ROW:2',
                'no_rekening' => 'ACC-002',
                'cifno' => 'CIF-002',
                'balance_dalam_idr' => 80,
            ]),
        ]);

        $result = app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        $validation = $result['validation']['periods']['2026-09-08'];

        $this->assertSame(2, $result['inserted_rows']);
        $this->assertSame('passed', $validation['status']);
        $this->assertSame(1, $validation['source']['zero_balance_rows']);
        $this->assertSame(1, $validation['source']['nonzero_balance_rows']);
        $this->assertSame('80.00', $validation['source']['balance_total']);
        $this->assertSame('80.00', $validation['target']['balance_total']);
        $this->assertTrue($validation['parity']['row_count_matches']);
        $this->assertTrue($validation['parity']['balance_total_matches']);
        $this->assertTrue($validation['parity']['classification_complete']);
        $this->assertTrue($validation['parity']['quality_complete']);
        $this->assertSame(2, DB::table('daily_loan_dinamis')->where('periode', '2026-09-08')->count());
    }

    public function test_blank_description_uses_prior_daily_only_for_same_account_and_cif(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:PRIOR',
            'periode' => '2026-09-07',
            'nomor_rekening1' => '123456',
            'cifno' => 'CIF-001',
            'description' => 'KREDIT MIKRO - KUPEDES',
            'segmen_dashboard' => 'Micro',
            'produk_dashboard' => 'Kupedes',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KUPEDES',
            'pn_referral1' => '00123456 - REFERRAL UJI',
            'baki_debet1' => 90,
        ]);
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'no_rekening' => '000123456',
            'description' => null,
            'pn_referral' => null,
        ]));

        $result = app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        $mapped = DB::table('daily_loan_dinamis')->where('periode', '2026-09-08')->first();

        $this->assertSame(1, $result['inserted_rows']);
        $this->assertSame(1, $result['validation']['periods']['2026-09-08']['source']['account_reference_rows']);
        $this->assertSame('KREDIT MIKRO - KUPEDES', $mapped->description);
        $this->assertSame('Micro', $mapped->segmen_dashboard);
        $this->assertSame('Kupedes', $mapped->produk_dashboard);
        $this->assertSame('00123456 - REFERRAL UJI', $mapped->pn_referral1);
    }

    public function test_prior_daily_fallback_is_rejected_when_cif_differs(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:PRIOR',
            'periode' => '2026-09-07',
            'nomor_rekening1' => '123456',
            'cifno' => 'CIF-LAIN',
            'description' => 'KREDIT MIKRO - KUPEDES',
            'segmen_dashboard' => 'Micro',
            'produk_dashboard' => 'Kupedes',
            'baki_debet1' => 90,
        ]);
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'no_rekening' => '000123456',
            'description' => null,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pasangan segmen/produk');

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
    }

    public function test_current_valid_description_wins_over_prior_daily_classification(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'DAILY:PRIOR',
            'periode' => '2026-09-07',
            'nomor_rekening1' => 'ACC-001',
            'cifno' => 'CIF-001',
            'description' => '01. RITKOM - S/D Rp 50 JUTA',
            'segmen_dashboard' => 'Small',
            'produk_dashboard' => 'Commercial',
            'baki_debet1' => 90,
        ]);
        DB::table('lw321pn')->insert($this->sourceRow());

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        $mapped = DB::table('daily_loan_dinamis')->where('periode', '2026-09-08')->first();

        $this->assertSame('KREDIT MIKRO - KUR MIKRO BARU', $mapped->description);
        $this->assertSame('Micro', $mapped->segmen_dashboard);
        $this->assertSame('KUR-Mikro', $mapped->produk_dashboard);
    }

    public function test_effective_quality_uses_due_dates_but_preserves_raw_kol_for_audit(): void
    {
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'kol_adk' => '5',
        ]));

        $result = app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
        $mapped = DB::table('daily_loan_dinamis')->where('periode', '2026-09-08')->first();

        $this->assertSame('5', $mapped->kol_adk1);
        $this->assertSame('2', $mapped->kolek);
        $this->assertSame('DPK 1', $mapped->kolek_detail);
        $this->assertSame(1, $result['validation']['periods']['2026-09-08']['source']['raw_kol_mismatch_rows']);
    }

    public function test_sync_rejects_rows_without_both_due_dates(): void
    {
        DB::table('lw321pn')->insert(array_merge($this->sourceRow(), [
            'next_pmt_date' => null,
            'next_int_pmt_date' => null,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NEXT_PMT_DATE/NEXT_INT_PMT_DATE');

        app(Lw321DailyLoanSyncService::class)->synchronize('2026-09-08');
    }

    public function test_source_period_can_be_validated_before_materialization_without_touching_target(): void
    {
        DB::table('lw321pn')->insert($this->sourceRow());

        $metrics = app(Lw321DailyLoanSyncService::class)->validateSourcePeriod('08/09/2026');

        $this->assertSame(1, $metrics['row_count']);
        $this->assertSame(0, $metrics['null_balance_rows']);
        $this->assertSame(1, $metrics['nonzero_balance_rows']);
        $this->assertSame(1, $metrics['mapped_description_rows']);
        $this->assertSame(0, $metrics['unmapped_description_rows']);
        $this->assertSame('80.00', $metrics['balance_total']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->count());
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
            $table->string('kol_adk')->nullable();
            $table->string('pn_pengelola_singlepn')->nullable();
            $table->string('pn_pengelola_1')->nullable();
            $table->string('pn_pemutus')->nullable();
            $table->string('pn_referral')->nullable();
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
            $table->string('kol_adk1')->nullable();
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
            $table->string('description')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('branch1')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_name1')->nullable();
            $table->string('pn_referral1')->nullable();
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
