<?php

namespace Tests\Unit;

use App\Support\ConsumerRmPositionHistoryStore;
use App\Support\ConsumerRmRealizationCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumerRmPositionHistoryStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        $this->createSourceTable();
        $migration = require database_path(
            'migrations/2026_09_09_010000_create_consumer_rm_position_history_tables.php'
        );
        $migration->up();
    }

    public function test_migration_uses_only_the_composite_history_identity_and_period_manifest_key(): void
    {
        $migration = require database_path(
            'migrations/2026_09_09_010000_create_consumer_rm_position_history_tables.php'
        );
        $migration->up();

        $historyIndexes = collect(Schema::getIndexes(ConsumerRmPositionHistoryStore::HISTORY_TABLE));
        $this->assertCount(1, $historyIndexes->filter(
            static fn (array $index): bool => $index['columns'] === [
                'periode',
                'produk',
                'cifno_clean',
                'account_key',
            ]
        ));

        $captureIndexes = collect(Schema::getIndexes(ConsumerRmPositionHistoryStore::CAPTURE_TABLE));
        $this->assertCount(1, $captureIndexes->filter(
            static fn (array $index): bool => $index['columns'] === ['periode']
        ));
    }

    public function test_capture_is_atomic_idempotent_canonical_and_preserves_restructure_lineage(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->sourceRow([
                'uniqueid_namareport' => 'SRC-B',
                'nomor_rekening1' => '000450',
                'plafon' => 100,
                'baki_debet1' => 80,
                'status_rekening1' => '1',
                'flag_restruk' => 'Y',
                'pn_restruk1' => '00990011 - PETUGAS RESTRUK',
                'restruk_ke1' => 2,
                'jenis_restruk1' => 'SUPLESI',
                'tgl_akad_restruk' => '2026-09-01',
            ]),
            $this->sourceRow([
                'uniqueid_namareport' => 'SRC-A',
                'nomor_rekening1' => '450',
                'plafon' => 120,
                'baki_debet1' => 70,
            ]),
            $this->sourceRow([
                'uniqueid_namareport' => 'SRC-KPR',
                'produk_kinerja' => 'KPR',
                'cifno_clean' => ' cif-kpr ',
                'nomor_rekening1' => '000900',
                'plafon' => 500,
                'baki_debet1' => 475,
            ]),
            $this->sourceRow([
                'uniqueid_namareport' => 'SRC-MICRO',
                'segmen_kinerja' => 'MICRO',
                'produk_kinerja' => 'KURMIKRO',
                'nomor_rekening1' => '777',
            ]),
        ]);

        $result = app(ConsumerRmPositionHistoryStore::class)->capturePeriod('2026-09-08');

        $this->assertSame('2026-09-08', $result['period']);
        $this->assertSame(3, $result['source_rows']);
        $this->assertSame(2, $result['archived_rows']);
        $this->assertTrue($result['verified']);
        $this->assertFalse($result['skipped']);
        $this->assertTrue(app(ConsumerRmPositionHistoryStore::class)->hasCapture('2026-09-08'));

        $briguna = DB::table(ConsumerRmPositionHistoryStore::HISTORY_TABLE)
            ->where('periode', '2026-09-08')
            ->where('produk', 'BRIGUNA-KONSUMER')
            ->first();
        $this->assertNotNull($briguna);
        $this->assertSame('CIF-001', $briguna->cifno_clean);
        $this->assertSame('450', $briguna->account_key);
        $this->assertEqualsWithDelta(120.0, (float) $briguna->plafon, 0.001);
        $this->assertEqualsWithDelta(80.0, (float) $briguna->baki_debet, 0.001);
        $this->assertSame('SRC-A', $briguna->lookup_order);
        $this->assertSame('1', $briguna->status_rekening1);
        $this->assertSame('Y', $briguna->flag_restruk);
        $this->assertSame('00990011 - PETUGAS RESTRUK', $briguna->pn_restruk1);
        $this->assertSame(2, (int) $briguna->restruk_ke1);
        $this->assertSame('SUPLESI', $briguna->jenis_restruk1);
        $this->assertSame('2026-09-01', $briguna->tgl_akad_restruk);

        $kpr = DB::table(ConsumerRmPositionHistoryStore::HISTORY_TABLE)
            ->where('periode', '2026-09-08')
            ->where('produk', 'KPR')
            ->first();
        $this->assertSame('CIF-KPR', $kpr->cifno_clean);
        $this->assertSame('900', $kpr->account_key);
    }

    public function test_duplicate_account_with_populated_owner_keeps_realization_identical_after_capture(): void
    {
        DB::table('daily_loan_dinamis')->insert($this->sourceRow([
            'uniqueid_namareport' => 'BASELINE',
            'periode' => '2026-08-31',
            'cifno_clean' => 'BASELINE-CIF',
            'nomor_rekening1' => 'BASELINE',
            'tgl_realisasi' => '2026-08-01',
        ]));
        foreach (['BRIGUNAKONSUMER', 'KPR'] as $index => $product) {
            DB::table('daily_loan_dinamis')->insert([
                $this->sourceRow([
                    'uniqueid_namareport' => $product.'-BLANK',
                    'produk_kinerja' => $product,
                    'cifno_clean' => $product.'-CIF',
                    'nomor_rekening1' => '00045'.$index,
                    'rm_normalized' => '',
                    'pn_pengelola1' => '',
                    'pn_pemrakarsa1' => '',
                    'cabang_normalized' => 'CABANG BELUM TERISI',
                    'tgl_realisasi' => '2026-09-04',
                    'plafon' => 120,
                    'baki_debet1' => 100,
                    'flag_restruk' => 'Y',
                ]),
                $this->sourceRow([
                    'uniqueid_namareport' => $product.'-OWNER',
                    'produk_kinerja' => $product,
                    'cifno_clean' => $product.'-CIF',
                    'nomor_rekening1' => '45'.$index,
                    'plafon' => 100,
                    'baki_debet1' => 80,
                ]),
            ]);
        }

        $calculator = app(ConsumerRmRealizationCalculator::class);
        $rawMetrics = $calculator->calculate('2026-09-08');
        $this->assertCount(2, $rawMetrics);
        foreach ($rawMetrics as $metric) {
            $this->assertSame('00112233 - RM UJI', $metric['rm']);
            $this->assertSame(1, $metric['realisasi_deb']);
            $this->assertSame(120.0, $metric['realisasi_os']);
        }

        $store = app(ConsumerRmPositionHistoryStore::class);
        $this->assertTrue($store->capturePeriod('2026-08-31')['verified']);
        $this->assertTrue($store->capturePeriod('2026-09-08')['verified']);
        $this->assertSame($rawMetrics, $calculator->calculate('2026-09-08'));

        DB::table('daily_loan_dinamis')->delete();
        $this->assertSame($rawMetrics, $calculator->calculate('2026-09-08'));
        foreach (DB::table(ConsumerRmPositionHistoryStore::HISTORY_TABLE)
            ->where('periode', '2026-09-08')->get() as $row) {
            $this->assertSame('Y', $row->flag_restruk);
            $this->assertSame('2026-09-04', $row->tgl_realisasi);
            $this->assertSame(120.0, (float) $row->plafon);
            $this->assertSame(100.0, (float) $row->baki_debet);
        }
    }

    public function test_verified_capture_survives_partial_prune_and_explicit_replacement_refreshes_reimport(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->sourceRow(['uniqueid_namareport' => 'SRC-1', 'nomor_rekening1' => '001']),
            $this->sourceRow(['uniqueid_namareport' => 'SRC-2', 'nomor_rekening1' => '002']),
        ]);
        $store = app(ConsumerRmPositionHistoryStore::class);
        $first = $store->capturePeriod('2026-09-08');
        $this->assertSame(2, $first['archived_rows']);

        // A resumed prune sees an incomplete source. The default call must use
        // the already verified archive instead of replacing it with one row.
        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'SRC-2')->delete();
        $resumed = $store->capturePeriod('2026-09-08');
        $this->assertTrue($resumed['verified']);
        $this->assertFalse($resumed['skipped']);
        $this->assertSame('already_verified', $resumed['reason']);
        $this->assertSame(2, $resumed['archived_rows']);
        $this->assertSame(2, DB::table(ConsumerRmPositionHistoryStore::HISTORY_TABLE)->count());

        // A completed legitimate re-import opts into replacement explicitly.
        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'SRC-1')->update(['plafon' => 999]);
        $refreshed = $store->capturePeriod('2026-09-08', true);
        $this->assertTrue($refreshed['verified']);
        $this->assertFalse($refreshed['skipped']);
        $this->assertSame(1, $refreshed['source_rows']);
        $this->assertSame(1, $refreshed['archived_rows']);
        $this->assertEqualsWithDelta(
            999.0,
            (float) DB::table(ConsumerRmPositionHistoryStore::HISTORY_TABLE)->value('plafon'),
            0.001
        );
    }

    public function test_helpers_only_expose_verified_captures_with_matching_row_counts(): void
    {
        foreach (['2026-06-30', '2026-07-31', '2026-08-31'] as $index => $period) {
            DB::table('daily_loan_dinamis')->insert($this->sourceRow([
                'uniqueid_namareport' => 'SRC-'.$index,
                'periode' => $period,
                'nomor_rekening1' => (string) (100 + $index),
            ]));
            app(ConsumerRmPositionHistoryStore::class)->capturePeriod($period);
        }

        $store = app(ConsumerRmPositionHistoryStore::class);
        $this->assertSame(
            ['2026-07-31', '2026-08-31'],
            $store->availablePeriods('2026-07-01', '2026-08-31')
        );

        DB::table(ConsumerRmPositionHistoryStore::HISTORY_TABLE)
            ->where('periode', '2026-07-31')
            ->delete();

        $this->assertFalse($store->hasCapture('2026-07-31'));
        $this->assertSame(
            ['2026-06-30', '2026-08-31'],
            $store->availablePeriods()
        );
    }

    public function test_capture_skips_safely_when_archive_tables_are_not_installed(): void
    {
        Schema::drop(ConsumerRmPositionHistoryStore::CAPTURE_TABLE);

        $result = app(ConsumerRmPositionHistoryStore::class)->capturePeriod('2026-09-08');

        $this->assertFalse($result['verified']);
        $this->assertTrue($result['skipped']);
        $this->assertSame('archive_tables_unavailable', $result['reason']);
        $this->assertFalse(app(ConsumerRmPositionHistoryStore::class)->hasCapture('2026-09-08'));
        $this->assertSame([], app(ConsumerRmPositionHistoryStore::class)->availablePeriods());
    }

    private function createSourceTable(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->text('pn_pengelola1')->nullable();
            $table->text('pn_pemrakarsa1')->nullable();
            $table->string('status_rekening1')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->text('pn_restruk1')->nullable();
            $table->integer('restruk_ke1')->nullable();
            $table->string('jenis_restruk1')->nullable();
            $table->date('tgl_akad_restruk')->nullable();
            $table->timestamps();
        });
    }

    /** @param array<string, mixed> $overrides */
    private function sourceRow(array $overrides = []): array
    {
        return array_replace([
            'uniqueid_namareport' => 'SRC-DEFAULT',
            'periode' => '2026-09-08',
            'segmen_kinerja' => 'CONSUMER',
            'produk_kinerja' => 'BRIGUNAKONSUMER',
            'cifno_clean' => 'CIF-001',
            'nomor_rekening1' => '001',
            'tgl_realisasi' => '2026-09-05',
            'plafon' => 100,
            'baki_debet1' => 80,
            'cabang_normalized' => 'MADIUN',
            'unit_normalized' => 'KC MADIUN',
            'branch_normalized' => '001',
            'rm_normalized' => '00112233 - RM UJI',
            'pn_pengelola1' => '00112233 - RM UJI',
            'pn_pemrakarsa1' => '00112233 - RM UJI',
            'status_rekening1' => null,
            'flag_restruk' => null,
            'pn_restruk1' => null,
            'restruk_ke1' => null,
            'jenis_restruk1' => null,
            'tgl_akad_restruk' => null,
            'created_at' => '2026-09-08 00:00:00',
            'updated_at' => '2026-09-08 00:00:00',
        ], $overrides);
    }
}
