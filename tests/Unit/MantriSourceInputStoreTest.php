<?php

namespace Tests\Unit;

use App\Services\Import\ImportProgressService;
use App\Support\ConsumerRmPositionHistoryStore;
use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use App\Support\DashboardHarianSnapshotService;
use App\Support\Lw321DailyLoanSyncService;
use App\Support\MantriSourceInputStore;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportCacheVersion;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class MantriSourceInputStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        $this->directory = storage_path('framework/testing/mantri-source-'.bin2hex(random_bytes(8)));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_default_testing_store_cannot_read_or_capture_production_archives(): void
    {
        $store = new MantriSourceInputStore;
        $this->assertNull($store->read('2026-10-19'));
        $this->assertSame([], $store->periods());
        $this->assertTrue($store->captureLw321Period('2026-10-19')['disabled']);
        $this->assertTrue($store->captureDailyLoanPeriod('2026-10-19')['disabled']);
        $this->assertDirectoryDoesNotExist($this->directory);
    }

    public function test_atomic_round_trip_is_idempotent_and_preserves_unrelated_files(): void
    {
        $store = new MantriSourceInputStore($this->directory);
        $data = $this->data();
        $store->write('2026-10-19', $data);
        $version = ReportCacheVersion::get('pinjaman');
        $fingerprint = $store->fingerprint('2026-10-19');
        file_put_contents($this->directory.'/unrelated.txt', 'keep');
        $store->write('2026-10-19', $data);

        $this->assertSame(2, $version);
        $this->assertSame($version, ReportCacheVersion::get('pinjaman'));
        $this->assertSame($fingerprint, $store->fingerprint('2026-10-19'));
        $this->assertSame('keep', file_get_contents($this->directory.'/unrelated.txt'));
        $this->assertSame(['123'], $store->read('2026-10-19')['presence']);
        $this->assertSame('100.25', $store->read('2026-10-19')['bookings'][0]['baki_debet1']);
        $this->assertSame($data['owner_evidence'], $store->read('2026-10-19')['owner_evidence']);
    }

    public function test_corruption_or_a_tampered_checksum_never_declares_an_available_period(): void
    {
        $store = new MantriSourceInputStore($this->directory);
        $store->write('2026-10-19', $this->data());
        $path = $this->directory.'/2026-10-19.json.gz';
        $envelope = json_decode(gzdecode(file_get_contents($path)), true);
        $envelope['data']['presence'][] = 'UNVERIFIED';
        file_put_contents($path, gzencode(json_encode($envelope)));
        $this->assertNull($store->read('2026-10-19'));
        $this->assertSame([], $store->periods());

        file_put_contents($path, 'truncated');
        $this->assertNull($store->read('2026-10-19'));
        $this->assertSame([], $store->periods());
    }

    public function test_recapture_repairs_a_gzip_checksum_failure_instead_of_accepting_a_matching_payload(): void
    {
        $store = new MantriSourceInputStore($this->directory);
        $data = $this->data();
        $store->write('2026-10-19', $data);
        $path = $this->directory.'/2026-10-19.json.gz';
        $compressed = file_get_contents($path);
        $compressed[strlen($compressed) - 8] = chr(ord($compressed[strlen($compressed) - 8]) ^ 1);
        file_put_contents($path, $compressed);
        $this->assertNull($store->read('2026-10-19'));
        $store->write('2026-10-19', $data);
        $this->assertSame(['123'], (new MantriSourceInputStore($this->directory))->read('2026-10-19')['presence']);
    }

    public function test_baseline_only_supplements_are_readable_but_not_available_positions(): void
    {
        $store = new MantriSourceInputStore($this->directory);
        $data = $this->data('2026-09-30');
        $data['source']['source_kind'] = 'baseline_only';
        $data['complete_presence'] = false;
        $data['presence'] = [];
        $data['bookings'] = [];
        $unknown = $this->row('2026-09-30');
        $unknown['description'] = null;
        $unknown['tgl_realisasi'] = null;
        $unknown['cifno'] = null;
        $data['unclassified_accounts'] = [$unknown];
        $store->write('2026-09-30', $data);

        $this->assertSame([], $store->periods());
        $saved = $store->read('2026-09-30')['unclassified_accounts'][0];
        $this->assertNull($saved['segmen_kinerja']);
        $this->assertNull($saved['produk_kinerja']);
        $this->assertSame(['blank_cif' => true, 'missing_booking_date' => true], $saved['quality']);
    }

    public function test_fingerprint_tracks_previous_month_and_observed_bookings_without_future_leakage(): void
    {
        $store = new MantriSourceInputStore($this->directory);
        $initial = $store->fingerprint('2026-10-19');
        $store->write('2026-09-30', $this->data('2026-09-30'));
        $baseline = $store->fingerprint('2026-10-19');
        $this->assertNotSame($initial, $baseline);
        $store->write('2026-10-17', $this->data('2026-10-17'));
        $observed = $store->fingerprint('2026-10-19');
        $this->assertNotSame($baseline, $observed);
        $store->write('2026-10-20', $this->data('2026-10-20'));
        $this->assertSame($observed, $store->fingerprint('2026-10-19'));
        $this->assertSame(['2026-09-30', '2026-10-17', '2026-10-20'], $store->periods());
    }

    public function test_period_path_traversal_is_rejected_before_any_file_is_written(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MantriSourceInputStore($this->directory))->write('../2026-10-19', $this->data());
    }

    public function test_period_mismatch_cannot_be_saved_under_another_day(): void
    {
        $data = $this->data();
        $data['bookings'][0]['periode'] = '2026-10-18';
        $this->expectException(InvalidArgumentException::class);
        (new MantriSourceInputStore($this->directory))->write('2026-10-19', $data);
    }

    public function test_invalid_balance_does_not_replace_a_last_valid_archive(): void
    {
        $store = new MantriSourceInputStore($this->directory);
        $store->write('2026-10-19', $this->data());
        $before = $store->fingerprint('2026-10-19');
        $data = $this->data();
        $data['bookings'][0]['baki_debet1'] = null;
        try {
            $store->write('2026-10-19', $data);
            $this->fail('Invalid balance must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $store->fingerprint('2026-10-19'));
            $this->assertSame('100.25', $store->read('2026-10-19')['bookings'][0]['baki_debet1']);
        }
    }

    public function test_current_month_cutoff_and_product_exclusions_preserve_other_segments(): void
    {
        $store = new MantriSourceInputStore($this->directory);
        $data = $this->data();
        $data['presence'] = ['123', 'CONSUMER', 'GBT', 'KUR', 'FUTURE', 'PREVIOUS', 'OUTSIDE'];
        $rows = [$this->row()];
        foreach ([
            ['CONSUMER', '2026-10-19', 'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) KOMERSIAL', 'KC Madiun'],
            ['GBT', '2026-10-19', 'KREDIT MIKRO - GBT', 'KC Madiun'],
            ['KUR', '2026-10-19', 'Kredit Mikro - KUR Ritel 2015', 'KC Madiun'],
            ['FUTURE', '2026-10-20', 'KREDIT MIKRO - KUPEDES', 'KC Madiun'],
            ['PREVIOUS', '2026-09-30', 'KREDIT MIKRO - KUPEDES', 'KC Madiun'],
            ['OUTSIDE', '2026-10-19', 'KREDIT MIKRO - KUPEDES', 'KC Jember'],
        ] as [$account, $date, $description, $branch]) {
            $rows[] = array_replace($this->row(), ['nomor_rekening1' => $account, 'tgl_realisasi' => $date, 'description' => $description, 'cabang1' => $branch]);
        }
        $data['bookings'] = $rows;
        $store->write('2026-10-19', $data);

        $saved = $store->read('2026-10-19');
        $this->assertSame(['123', 'CONSUMER'], array_column($saved['bookings'], 'nomor_rekening1'));
        $this->assertContains('GBT', $saved['presence']);
        $this->assertContains('OUTSIDE', $saved['presence']);
        $this->assertSame('CONSUMER', $saved['bookings'][1]['segmen_kinerja']);
    }

    public function test_raw_capture_retains_complete_presence_zero_balances_and_unknown_input_lineage(): void
    {
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->string('no_rekening');
            $table->date('periode');
            foreach (['kanca', 'uker', 'kode_uker', 'cifno', 'description', 'pn_pengelola_singlepn', 'pn_pengelola_1', 'pn_pemrakarsa'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('tgl_realisasi')->nullable();
            $table->decimal('balance_dalam_idr', 20, 2)->nullable();
            $table->decimal('plafon_dalam_idr', 20, 2)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        $source = [
            'no_rekening' => '000123', 'periode' => '2026-10-19', 'kanca' => 'KC Madiun',
            'uker' => 'UNIT UJI', 'kode_uker' => '3212', 'cifno' => 'CIF-123',
            'description' => null, 'tgl_realisasi' => '2026-10-19',
            'balance_dalam_idr' => 0, 'plafon_dalam_idr' => 100,
            'pn_pengelola_singlepn' => '0057214 - MANTRI UJI', 'pn_pengelola_1' => null,
            'pn_pemrakarsa' => '00012345 - PEMRAKARSA', 'updated_at' => '2026-10-19 12:00:00',
        ];
        DB::table('lw321pn')->insert([
            $source,
            array_replace($source, ['no_rekening' => 'HISTORICAL', 'tgl_realisasi' => null, 'cifno' => null, 'balance_dalam_idr' => 10]),
            array_replace($source, ['no_rekening' => 'NOOWNER', 'pn_pengelola_singlepn' => null, 'pn_pengelola_1' => '00999999 - OTHER MANAGER']),
            array_replace($source, ['no_rekening' => 'OUTSIDE', 'kanca' => 'KC Jember', 'balance_dalam_idr' => 90]),
        ]);
        $before = DB::table('lw321pn')->get()->toJson();
        $store = new MantriSourceInputStore($this->directory);
        $saved = $store->captureLw321Period('2026-10-19');

        $this->assertSame(['123', 'HISTORICAL', 'NOOWNER', 'OUTSIDE'], $saved['presence']);
        $this->assertSame(['2026-10-19'], $store->periods());
        $this->assertCount(2, $saved['bookings']);
        $this->assertCount(3, $saved['unclassified_accounts']);
        $this->assertSame('0.00', $saved['bookings'][0]['baki_debet1']);
        $this->assertNull($saved['bookings'][0]['segmen_kinerja']);
        $this->assertNull($saved['bookings'][0]['produk_kinerja']);
        $this->assertSame('0057214 - MANTRI UJI', $saved['bookings'][0]['pn_pengelola1']);
        $this->assertSame('00012345 - PEMRAKARSA', $saved['bookings'][0]['pn_pemrakarsa1']);
        $this->assertSame(4, $saved['source']['row_count']);
        $this->assertNull($saved['bookings'][1]['pn_pengelola1']);
        $this->assertSame('lw321_import', $saved['source']['source_kind']);
        $this->assertSame($before, DB::table('lw321pn')->get()->toJson());
        $saved['owner_evidence'] = [['account' => '123', 'cif' => 'CIF-123', 'pn' => '57214', 'source' => 'audited']];
        $store->write('2026-10-19', $saved);
        $this->assertSame($saved['owner_evidence'], $store->captureLw321Period('2026-10-19')['owner_evidence']);
    }

    public function test_materialization_captures_only_after_outer_commit_and_skips_daily_precedence(): void
    {
        $this->createMaterializationFixture();
        $store = new MantriSourceInputStore($this->directory);
        app()->instance(MantriSourceInputStore::class, $store);
        DB::beginTransaction();
        $result = app(Lw321DailyLoanSyncService::class)->synchronize('2026-10-19');
        $this->assertSame(['2026-10-19'], $result['mutated_periods']);
        $this->assertNull($store->read('2026-10-19'));
        DB::commit();
        $this->assertSame(['2026-10-19'], $store->periods());
        $fingerprint = $store->fingerprint('2026-10-19');

        DB::table('daily_loan_dinamis')->insert(['uniqueid_namareport' => 'DAILY:AUTHORITATIVE', 'periode' => '2026-10-19']);
        DB::table('lw321pn')->where('no_rekening', '123')->update(['balance_dalam_idr' => 999]);
        $result = app(Lw321DailyLoanSyncService::class)->synchronize('2026-10-19');
        $this->assertSame(['2026-10-19'], $result['skipped_periods']);
        $this->assertSame(1, $result['deleted_rows']);
        $this->assertSame($fingerprint, $store->fingerprint('2026-10-19'));
    }

    public function test_outer_rollback_does_not_publish_uncommitted_inputs(): void
    {
        $this->createMaterializationFixture();
        $store = new MantriSourceInputStore($this->directory);
        app()->instance(MantriSourceInputStore::class, $store);
        DB::beginTransaction();
        app(Lw321DailyLoanSyncService::class)->synchronize('2026-10-19');
        DB::rollBack();
        $this->assertNull($store->read('2026-10-19'));
        $this->assertSame(0, DB::table('daily_loan_dinamis')->count());
    }

    public function test_native_capture_preserves_canonical_classification_initiator_and_full_presence(): void
    {
        $this->createNativeFixture();
        DB::table('daily_loan_dinamis')->insert([
            $this->nativeRow('00101', ['description' => null, 'baki_debet1' => 0]),
            $this->nativeRow('102', ['description' => 'Kredit Mikro - KUR Ritel 2015']),
            $this->nativeRow('103', ['segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => 'KREDIT MIKRO - KUPEDES', 'pn_pemrakarsa1' => null]),
            $this->nativeRow('CONSUMER', ['segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'KPR', 'description' => null]),
            $this->nativeRow('OUTSIDE', ['cabang1' => 'KC Jember']),
            $this->nativeRow('EARLIER', ['tgl_realisasi' => '2026-09-30']),
            $this->nativeRow('FUTURE', ['tgl_realisasi' => '2026-11-20']),
            $this->nativeRow('EXCLUDED', ['produk_kinerja' => 'KURRITEL']),
        ]);
        $before = DB::table('daily_loan_dinamis')->orderBy('nomor_rekening1')->get()->toJson();
        $store = new MantriSourceInputStore($this->directory);
        $saved = $store->captureDailyLoanPeriod('2026-11-19');

        $this->assertSame(['101', '102', '103', 'CONSUMER', 'EARLIER', 'EXCLUDED', 'FUTURE', 'OUTSIDE'], $saved['presence']);
        $bookings = array_column($saved['bookings'], null, 'nomor_rekening1');
        $this->assertSame(['101', '102', '103', 'CONSUMER'], array_column($saved['bookings'], 'nomor_rekening1'));
        $this->assertSame('0.00', $bookings['101']['baki_debet1']);
        $this->assertSame('MICRO', $bookings['101']['segmen_kinerja']);
        $this->assertSame('KUPEDES', $bookings['102']['produk_kinerja']);
        $this->assertSame('00000801 - INITIATOR', $bookings['101']['pn_pemrakarsa1']);
        $this->assertSame('00000802 - CURRENT MANAGER', $bookings['101']['pn_pengelola1']);
        $this->assertNull($bookings['103']['pn_pemrakarsa1']);
        $this->assertNull($bookings['103']['segmen_kinerja']);
        $this->assertNull($bookings['103']['produk_kinerja']);
        $this->assertSame(['103'], array_column($saved['unclassified_accounts'], 'nomor_rekening1'));
        $this->assertSame('CONSUMER', $bookings['CONSUMER']['segmen_kinerja']);
        $this->assertSame('daily_loan_import', $saved['source']['source_kind']);
        $this->assertSame('canonical_daily_loan', $saved['source']['classification_basis']);
        $this->assertSame(8, $saved['source']['row_count']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $saved['source']['raw_sha256']);
        $envelope = json_decode(gzdecode(file_get_contents($this->directory.'/2026-11-19.json.gz')), true);
        $this->assertSame($envelope['checksum_sha256'], hash('sha256', json_encode($envelope['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)));
        $this->assertSame($before, DB::table('daily_loan_dinamis')->orderBy('nomor_rekening1')->get()->toJson());
    }

    public function test_native_capture_skips_materialization_and_excludes_stale_lw_rows_when_native_exists(): void
    {
        $this->createNativeFixture();
        $store = new MantriSourceInputStore($this->directory);
        $data = $this->data('2026-11-19');
        $data['source']['source_kind'] = 'lw321_import';
        $data['presence'][] = 'RAW-UNKNOWN';
        $store->write('2026-11-19', $data);
        $fingerprint = $store->fingerprint('2026-11-19');
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('STALE', ['uniqueid_namareport' => 'LW321PN:STALE']));
        $result = $store->captureDailyLoanPeriod('2026-11-19');
        $this->assertTrue($result['skipped']);
        $this->assertSame('no_native_source_rows', $result['reason']);
        $this->assertSame($fingerprint, $store->fingerprint('2026-11-19'));
        $this->assertContains('RAW-UNKNOWN', $store->read('2026-11-19')['presence']);

        DB::table('daily_loan_dinamis')->insert($this->nativeRow('NATIVE', ['uniqueid_namareport' => null]));
        $saved = $store->captureDailyLoanPeriod('2026-11-19');
        $this->assertSame(['NATIVE'], $saved['presence']);
        $this->assertSame(['NATIVE'], array_column($saved['bookings'], 'nomor_rekening1'));
        $this->assertSame($data['owner_evidence'], $saved['owner_evidence']);
        $this->assertSame(1, $saved['source']['row_count']);
    }

    public function test_native_capture_uses_verified_canonical_scope_when_original_labels_are_blank(): void
    {
        $this->createNativeFixture();
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('CANONICAL', [
            'cabang1' => '', 'unit1' => '', 'branch1' => null,
            'cabang_normalized' => 'KC MAGETAN', 'unit_normalized' => 'UNIT CANONICAL', 'branch_normalized' => '4567',
        ]));
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('RAW-OUTSIDE', [
            'cabang1' => 'KC Jember', 'cabang_normalized' => 'KC MADIUN',
        ]));
        $saved = (new MantriSourceInputStore($this->directory))->captureDailyLoanPeriod('2026-11-19');
        $this->assertCount(1, $saved['bookings']);
        $this->assertSame('KC MAGETAN', $saved['bookings'][0]['cabang_normalized']);
        $this->assertSame('UNIT CANONICAL', $saved['bookings'][0]['unit_normalized']);
        $this->assertSame('4567', $saved['bookings'][0]['branch_normalized']);
        $this->assertContains('RAW-OUTSIDE', $saved['presence']);
        $this->assertNotContains('RAW-OUTSIDE', array_column($saved['bookings'], 'nomor_rekening1'));
    }

    public function test_native_capture_waits_for_outer_commit_and_never_publishes_rolled_back_rows(): void
    {
        $this->createNativeFixture();
        $store = new MantriSourceInputStore($this->directory);
        DB::beginTransaction();
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('COMMITTED'));
        DB::beginTransaction();
        $this->assertTrue($store->captureDailyLoanPeriod('2026-11-19')['deferred']);
        DB::commit();
        $this->assertNull($store->read('2026-11-19'));
        DB::table('daily_loan_dinamis')->where('nomor_rekening1', 'COMMITTED')->update(['plafon' => 250]);
        DB::commit();
        $this->assertSame('250.00', $store->read('2026-11-19')['bookings'][0]['plafon']);
        $fingerprint = $store->fingerprint('2026-11-19');

        DB::beginTransaction();
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('ROLLED-BACK'));
        $this->assertTrue($store->captureDailyLoanPeriod('2026-11-19')['deferred']);
        DB::rollBack();
        $this->assertSame($fingerprint, $store->fingerprint('2026-11-19'));
        $this->assertSame(['COMMITTED'], $store->read('2026-11-19')['presence']);
    }

    public function test_empty_or_wrong_period_source_never_replaces_verified_inputs(): void
    {
        $this->createNativeFixture();
        $store = new MantriSourceInputStore($this->directory);
        $store->write('2026-11-19', $this->data('2026-11-19'));
        $fingerprint = $store->fingerprint('2026-11-19');
        $this->assertTrue($store->captureDailyLoanPeriod('2026-11-19')['skipped']);
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('OTHER-DATE', ['periode' => '2026-11-20']));
        $this->assertTrue($store->captureDailyLoanPeriod('2026-11-19')['skipped']);
        $this->assertSame($fingerprint, $store->fingerprint('2026-11-19'));
        $this->assertSame(['2026-11-19'], $store->periods());
    }

    public function test_native_capture_refreshes_corrected_period_and_preserves_dated_owner_evidence(): void
    {
        $this->createNativeFixture();
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('123'));
        $store = new MantriSourceInputStore($this->directory);
        $first = $store->captureDailyLoanPeriod('2026-11-19');
        $first['owner_evidence'] = [['account' => '123', 'cif' => 'CIF-123', 'realization_date' => '2026-11-05', 'pn' => '00000801', 'as_of' => '2026-11-19', 'owner_basis' => 'pn_pemrakarsa1']];
        $store->write('2026-11-19', $first);
        $version = ReportCacheVersion::get('pinjaman');
        DB::table('daily_loan_dinamis')->update(['plafon' => 175, 'pn_pemrakarsa1' => null]);
        $saved = $store->captureDailyLoanPeriod('2026-11-19');
        $this->assertSame('175.00', $saved['bookings'][0]['plafon']);
        $this->assertNull($saved['bookings'][0]['pn_pemrakarsa1']);
        $this->assertSame($first['owner_evidence'], $saved['owner_evidence']);
        $this->assertNotSame($first['source']['raw_sha256'], $saved['source']['raw_sha256']);
        $this->assertGreaterThan($version, ReportCacheVersion::get('pinjaman'));
    }

    public function test_native_month_end_archive_preserves_classified_and_unknown_unit_baseline_after_source_retention(): void
    {
        $this->createNativeFixture();
        $end = ['periode' => '2026-11-30', 'tgl_realisasi' => '2026-10-01'];
        DB::table('daily_loan_dinamis')->insert([
            $this->nativeRow('MICRO', $end + ['baki_debet1' => 50]),
            $this->nativeRow('GBT', $end + ['baki_debet1' => 20, 'produk_kinerja' => 'BRIGUNA MIKRO', 'unit1' => 'KC MADIUN']),
            $this->nativeRow('UNKNOWN-UNIT', $end + ['baki_debet1' => 30, 'segmen_kinerja' => null, 'produk_kinerja' => null]),
            $this->nativeRow('UNKNOWN-KC', $end + ['baki_debet1' => 37, 'segmen_kinerja' => null, 'produk_kinerja' => null, 'unit1' => 'KC MADIUN']),
            $this->nativeRow('SME', $end + ['segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'KMK']),
            $this->nativeRow('KUR-RITEL', $end + ['produk_kinerja' => 'KURRITEL']),
            $this->nativeRow('OUTSIDE', $end + ['cabang1' => 'KC Jember']),
            $this->nativeRow('ZERO', $end + ['baki_debet1' => 0]),
        ]);
        $store = new MantriSourceInputStore($this->directory);
        $saved = $store->captureDailyLoanPeriod('2026-11-30');
        $this->assertSame(['GBT', 'MICRO', 'UNKNOWN-UNIT', 'ZERO'], array_column($saved['baseline_accounts'], 0));
        $this->assertSame(['20.00', '50.00', '30.00', '0.00'], array_column($saved['baseline_accounts'], 2));
        $this->assertSame('BRIGUNAMIKRO', $saved['baseline_accounts'][0][5]);
        $this->assertSame('baki_debet1', $saved['source']['baseline_columns'][2]);
        $this->assertSame([], $saved['bookings']);
        $decemberFingerprint = $store->fingerprint('2026-12-05');

        DB::table('daily_loan_dinamis')->where('periode', '2026-11-30')->delete();
        $reopened = new MantriSourceInputStore($this->directory);
        $this->assertSame($saved['baseline_accounts'], $reopened->read('2026-11-30')['baseline_accounts']);
        $this->assertSame($decemberFingerprint, $reopened->fingerprint('2026-12-05'));
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('DECEMBER', ['periode' => '2026-12-05', 'tgl_realisasi' => '2026-12-02']));
        $this->assertSame([], $reopened->captureDailyLoanPeriod('2026-12-05')['baseline_accounts']);
        $this->assertSame(['2026-11-30', '2026-12-05'], $reopened->periods());
    }

    public function test_raw_month_end_capture_keeps_gbt_and_unknown_unit_without_inventing_classification(): void
    {
        $this->createMaterializationFixture();
        DB::table('lw321pn')->delete();
        $base = [
            'periode' => '2026-11-30', 'tgl_realisasi' => '2026-10-01', 'cifno' => 'CIF-BASE',
            'kanca' => 'KC Madiun', 'uker' => 'UNIT UJI', 'balance_dalam_idr' => 30,
        ];
        DB::table('lw321pn')->insert([
            $base + ['no_rekening' => 'KNOWN', 'description' => 'KREDIT MIKRO - KUPEDES'],
            array_replace($base, ['no_rekening' => 'GBT', 'description' => 'KREDIT MIKRO - GBT', 'uker' => 'KC MADIUN', 'balance_dalam_idr' => 20]),
            $base + ['no_rekening' => 'UNKNOWN-UNIT', 'description' => null],
            array_replace($base, ['no_rekening' => 'UNKNOWN-KC', 'description' => null, 'uker' => 'KC MADIUN']),
            $base + ['no_rekening' => 'RITEL', 'description' => 'Kredit Mikro - KUR Ritel 2015'],
        ]);
        $saved = (new MantriSourceInputStore($this->directory))->captureLw321Period('2026-11-30');
        $this->assertSame(['GBT', 'KNOWN', 'UNKNOWN-UNIT'], array_column($saved['baseline_accounts'], 0));
        $this->assertSame(['20.00', '30.00', '30.00'], array_column($saved['baseline_accounts'], 2));
        $this->assertNull($saved['baseline_accounts'][2][4]);
        $this->assertNull($saved['baseline_accounts'][2][5]);
        $this->assertSame('lw321_import', $saved['source']['source_kind']);
        $this->assertContains('UNKNOWN-KC', $saved['presence']);
        $this->assertContains('RITEL', $saved['presence']);
    }

    public function test_baseline_rows_cannot_be_declared_for_an_intermediate_position(): void
    {
        $data = $this->data();
        $data['baseline_accounts'] = [$this->row()];
        $this->expectException(InvalidArgumentException::class);
        (new MantriSourceInputStore($this->directory))->write('2026-10-19', $data);
    }

    public function test_native_capture_refuses_an_untracked_pdo_transaction(): void
    {
        $this->createNativeFixture();
        $store = new MantriSourceInputStore($this->directory);
        $pdo = DB::connection()->getPdo();
        $pdo->beginTransaction();
        try {
            DB::table('daily_loan_dinamis')->insert($this->nativeRow('UNCOMMITTED'));
            try {
                $store->captureDailyLoanPeriod('2026-11-19');
                $this->fail('Untracked source transaction must not publish an archive.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('before the source transaction commits', $e->getMessage());
                $this->assertNull($store->read('2026-11-19'));
            }
        } finally {
            $pdo->rollBack();
        }
        $this->assertSame(0, DB::table('daily_loan_dinamis')->count());
    }

    public function test_managed_deletion_refreshes_only_derived_native_archives_and_preserves_owner_evidence(): void
    {
        $this->createNativeFixture();
        DB::table('daily_loan_dinamis')->insert([
            $this->nativeRow('MADIUN'),
            $this->nativeRow('MAGETAN', ['cabang1' => 'KC MAGETAN']),
        ]);
        $store = new MantriSourceInputStore($this->directory);
        $saved = $store->captureDailyLoanPeriod('2026-11-19');
        $saved['owner_evidence'] = [['account' => 'MADIUN', 'pn' => '801', 'owner_basis' => 'pn_pemrakarsa1', 'as_of' => '2026-11-19']];
        $store->write('2026-11-19', $saved);
        $store->write('2026-10-19', $this->data());
        $raw = $this->data('2026-10-20');
        $raw['source']['source_kind'] = 'lw321_import';
        $store->write('2026-10-20', $raw);
        $manualBefore = $store->read('2026-10-19');
        $rawBefore = $store->read('2026-10-20');

        DB::table('daily_loan_dinamis')->where('cabang1', 'KC Madiun')->delete();
        $result = $store->refreshDailyLoanAfterDelete('2026-11-19');
        $this->assertSame(['2026-11-19'], $result['refreshed_periods']);
        $this->assertSame(['MAGETAN'], $store->read('2026-11-19')['presence']);
        $this->assertSame($saved['owner_evidence'], $store->read('2026-11-19')['owner_evidence']);

        DB::table('daily_loan_dinamis')->delete();
        $result = $store->refreshDailyLoanAfterDelete();
        $this->assertSame(['2026-11-19'], $result['invalidated_periods']);
        $this->assertSame(['2026-10-19', '2026-10-20'], $result['skipped_periods']);
        $removed = $store->read('2026-11-19');
        $this->assertFalse($removed['complete_presence']);
        $this->assertSame([], $removed['bookings']);
        $this->assertSame([], $removed['baseline_accounts']);
        $this->assertSame([], $removed['unclassified_accounts']);
        $this->assertSame($saved['owner_evidence'], $removed['owner_evidence']);
        $this->assertNotContains('2026-11-19', $store->periods());
        $this->assertSame($manualBefore, $store->read('2026-10-19'));
        $this->assertSame($rawBefore, $store->read('2026-10-20'));
    }

    public function test_managed_deletion_archive_refresh_respects_outer_rollback_and_commit(): void
    {
        $this->createNativeFixture();
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('KEPT'));
        $store = new MantriSourceInputStore($this->directory);
        $store->captureDailyLoanPeriod('2026-11-19');
        $fingerprint = $store->fingerprint('2026-11-19');
        DB::beginTransaction();
        DB::table('daily_loan_dinamis')->delete();
        $this->assertTrue($store->refreshDailyLoanAfterDelete('2026-11-19')['deferred']);
        $this->assertSame($fingerprint, $store->fingerprint('2026-11-19'));
        DB::rollBack();
        $this->assertSame($fingerprint, $store->fingerprint('2026-11-19'));

        DB::beginTransaction();
        DB::table('daily_loan_dinamis')->delete();
        $store->refreshDailyLoanAfterDelete('2026-11-19');
        DB::commit();
        $this->assertFalse($store->read('2026-11-19')['complete_presence']);
        $this->assertSame([], $store->periods());
    }

    public function test_common_daily_sync_archives_verified_native_inputs_before_other_consumers(): void
    {
        $this->createNativeFixture();
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('123'));
        $store = new MantriSourceInputStore($this->directory);
        app()->instance(MantriSourceInputStore::class, $store);
        $consumer = new class($store)
        {
            public bool $sawVerifiedMantri = false;

            public function __construct(private MantriSourceInputStore $store) {}

            public function capturePeriod(string $period, bool $replaceVerified = false): array
            {
                $this->sawVerifiedMantri = $this->store->read($period)['complete_presence'] ?? false;

                return ['verified' => true];
            }
        };
        app()->instance(ConsumerRmPositionHistoryStore::class, $consumer);
        Bus::fake();
        $this->syncService()->syncImportedTable('daily_loan_dinamis', '2026-11-19', null, 'native-import');
        $this->assertTrue($consumer->sawVerifiedMantri);
        $this->assertSame('daily_loan_import', $store->read('2026-11-19')['source']['source_kind']);
        Bus::assertBatched(fn ($batch): bool => $batch->name === 'daily_loan:2026-11-19');
    }

    public function test_failed_mantri_capture_is_audited_and_rethrown_after_unrelated_consumers_run(): void
    {
        $this->createNativeFixture();
        $this->createAuditFixture();
        DB::table('daily_loan_dinamis')->insert($this->nativeRow('INVALID', ['baki_debet1' => null]));
        $store = new MantriSourceInputStore($this->directory);
        $store->write('2026-11-19', $this->data('2026-11-19'));
        $fingerprint = $store->fingerprint('2026-11-19');
        app()->instance(MantriSourceInputStore::class, $store);
        $consumer = new class
        {
            public bool $called = false;

            public function capturePeriod(string $period, bool $replaceVerified = false): array
            {
                $this->called = true;

                return ['verified' => true];
            }
        };
        app()->instance(ConsumerRmPositionHistoryStore::class, $consumer);
        Bus::fake();
        $service = $this->syncService();
        try {
            $service->syncImportedTable('daily_loan_dinamis', '2026-11-19', null, 'native-import');
            $this->fail('The async synchronization job must retry failed Mantri captures.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('numeric balance', $e->getMessage());
        }
        $this->assertTrue($consumer->called);
        $this->assertSame($fingerprint, $store->fingerprint('2026-11-19'));
        Bus::assertBatched(fn ($batch): bool => $batch->name === 'daily_loan:2026-11-19');
        $this->assertSame('failed', DB::table('report_sync_audits')->where('action', 'mantri_source_input_archive')->value('status'));
        $this->assertSame('success', DB::table('report_sync_audits')->where('action', 'snapshot_parallel_dispatch')->value('status'));

        DB::table('daily_loan_dinamis')->update(['baki_debet1' => 0]);
        $service->syncImportedTable('daily_loan_dinamis', '2026-11-19', null, 'native-import-retry');
        $this->assertSame('0.00', $store->read('2026-11-19')['bookings'][0]['baki_debet1']);
    }

    private function createNativeFixture(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            foreach (['uniqueid_namareport', 'periode', 'nomor_rekening1', 'cifno', 'description', 'cabang1', 'unit1', 'branch1', 'cabang_normalized', 'unit_normalized', 'branch_normalized', 'tgl_realisasi', 'pn_pengelola1', 'pn_pemrakarsa1', 'segmen_kinerja', 'produk_kinerja', 'updated_at'] as $field) {
                $table->string($field)->nullable();
            }
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
        });
    }

    private function nativeRow(string $account, array $overrides = []): array
    {
        return array_replace([
            'uniqueid_namareport' => 'DAILY:'.$account, 'periode' => '2026-11-19', 'nomor_rekening1' => $account,
            'cifno' => 'CIF-'.$account, 'description' => null, 'cabang1' => 'KC Madiun', 'unit1' => 'UNIT UJI', 'branch1' => '3212',
            'tgl_realisasi' => '2026-11-05', 'pn_pengelola1' => '00000802 - CURRENT MANAGER', 'pn_pemrakarsa1' => '00000801 - INITIATOR',
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KUPEDES', 'baki_debet1' => 100, 'plafon' => 150, 'updated_at' => '2026-11-19 12:00:00',
        ], $overrides);
    }

    private function syncService(): ReportDataSyncService
    {
        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('hasActiveProcessingJobsForTable')->andReturnFalse();
        app()->instance(ImportProgressService::class, $progress);

        return new ReportDataSyncService(
            Mockery::mock(ReportSnapshotBuilder::class),
            Mockery::mock(DashboardHarianSnapshotService::class),
            Mockery::mock(PartitionMaintenanceService::class),
            Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class)
        );
    }

    private function createAuditFixture(): void
    {
        Schema::create('report_sync_audits', function (Blueprint $table): void {
            $table->id();
            foreach (['import_job_id', 'source', 'table_name', 'period_hint', 'action', 'status', 'duration_ms', 'affected_rows', 'message', 'context', 'created_at', 'updated_at'] as $field) {
                $table->text($field)->nullable();
            }
        });
    }

    private function createMaterializationFixture(): void
    {
        Schema::create('lw321pn', function (Blueprint $table): void {
            foreach (['uniqueid_namareport', 'periode', 'no_rekening', 'description', 'cifno', 'next_pmt_date', 'next_int_pmt_date', 'kol_adk', 'pn_referral', 'flag_restruk', 'tgl_realisasi', 'kanca', 'uker', 'kode_uker', 'pn_pengelola_singlepn'] as $field) {
                $table->string($field)->nullable();
            }
            $table->decimal('balance_dalam_idr', 20, 2);
            $table->decimal('plafon', 20, 2)->nullable();
        });
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            foreach (['uniqueid_namareport', 'periode', 'nomor_rekening1', 'cifno', 'description', 'kolek', 'kol_adk1', 'kolek_detail', 'umur_tunggakan', 'segmen_dashboard', 'produk_dashboard', 'segmen_kinerja', 'produk_kinerja', 'pn_referral1', 'status_rekening1', 'npl_method'] as $field) {
                $table->string($field)->nullable();
            }
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
        DB::table('lw321pn')->insert([
            'uniqueid_namareport' => 'RAW:1', 'periode' => '2026-10-19', 'no_rekening' => '123',
            'cifno' => 'CIF-123', 'description' => 'KREDIT MIKRO - KUR MIKRO BARU',
            'next_pmt_date' => '2026-10-20', 'balance_dalam_idr' => 100, 'plafon' => 150,
            'tgl_realisasi' => '2026-10-19', 'kanca' => 'KC Madiun', 'uker' => 'UNIT UJI',
            'kode_uker' => '3212', 'pn_pengelola_singlepn' => '0057214 - MANTRI UJI',
        ]);
    }

    private function data(string $period = '2026-10-19'): array
    {
        return [
            'version' => 1, 'period' => $period, 'source' => ['source_kind' => 'validated_workbook'],
            'complete_presence' => true, 'presence' => ['000123'], 'bookings' => [$this->row($period)],
            'unclassified_accounts' => [],
            'owner_evidence' => [['account' => '123', 'cif' => 'CIF-123', 'realization_date' => $period, 'pn' => '57214', 'source' => 'audited']],
        ];
    }

    private function row(string $period = '2026-10-19'): array
    {
        return [
            'periode' => $period, 'nomor_rekening1' => '000123', 'cifno' => 'CIF-123',
            'tgl_realisasi' => $period, 'plafon' => '150.00', 'baki_debet1' => '100.25',
            'pn_pengelola1' => '0057214 - MANTRI UJI', 'cabang1' => 'KC Madiun',
            'unit1' => 'UNIT UJI', 'branch1' => '3212', 'description' => 'KREDIT MIKRO - KUR MIKRO BARU',
        ];
    }
}
