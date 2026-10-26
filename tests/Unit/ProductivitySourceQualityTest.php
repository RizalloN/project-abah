<?php

namespace Tests\Unit;

use App\Support\ProductivitySourceQuality;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductivitySourceQualityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable()->index();
            $table->string('nomor_rekening1')->nullable();
            $table->string('nama_debitur1')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->string('kolek')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_pemrakarsa1')->nullable();
        });
    }

    public function test_reports_blank_pn_and_kolek_anomaly_on_current_month_realizations(): void
    {
        $this->insertRows('2026-10-22', 'LW321PN:', [
            ['acc' => 'A1', 'tgl' => '2026-10-22', 'plafon' => 50_000_000],
            ['acc' => 'A2', 'tgl' => '2026-10-22', 'plafon' => 25_000_000, 'pn' => null],
            ['acc' => 'A3', 'tgl' => '2026-10-05', 'plafon' => 10_000_000, 'pemrakarsa' => '-'],
            ['acc' => 'A4', 'tgl' => '2026-10-08', 'plafon' => 200_000_000, 'kolek' => '2', 'umur' => -15],
            ['acc' => 'A5', 'tgl' => '2026-10-02', 'plafon' => 30_000_000, 'kolek' => '2', 'umur' => 12],
            // Di luar bulan berjalan dan di luar produk: tidak dihitung.
            ['acc' => 'A6', 'tgl' => '2026-09-30', 'plafon' => 99_000_000, 'pn' => null],
            ['acc' => 'A7', 'tgl' => '2026-10-21', 'plafon' => 99_000_000, 'pn' => null, 'produk' => 'BRIGUNAMIKRO'],
        ]);
        $this->insertRows('2026-10-21', 'DAILY:', [
            ['acc' => 'A1', 'tgl' => '2026-10-21', 'plafon' => 1],
        ]);

        $summary = app(ProductivitySourceQuality::class)->summarize('2026-10-22', 'MICRO', ['KURMIKRO'], ['KC MADIUN']);

        $this->assertSame('LW321PN', $summary['source']);
        $this->assertTrue($summary['from_lw321']);
        $this->assertSame('2026-10-21', $summary['reference_period']);
        $this->assertSame(5, $summary['realized_accounts']);
        $this->assertSame(2, $summary['blank_pn_accounts']);
        $this->assertEqualsWithDelta(35_000_000, $summary['blank_pn_plafon'], 0.01);
        $this->assertSame(1, $summary['blank_pn_latest_accounts']);
        $this->assertSame(2, $summary['kolek_issue_accounts']);
        $this->assertSame(1, $summary['kolek_age_mismatch_accounts']);
        $this->assertTrue($summary['has_issues']);
        $this->assertSame(['A2', 'A4', 'A3', 'A5'], array_column($summary['samples'], 'account'));
        $this->assertSame(['Kolek 2 (belum menunggak)'], $summary['samples'][1]['issues']);
    }

    public function test_native_daily_position_without_issues_has_nothing_to_report(): void
    {
        $this->insertRows('2026-10-21', 'DAILY:', [
            ['acc' => 'A1', 'tgl' => '2026-10-21', 'plafon' => 50_000_000],
        ]);

        $summary = app(ProductivitySourceQuality::class)->summarize('2026-10-21', 'MICRO', ['KURMIKRO']);

        $this->assertSame('Daily Loan Dinamis', $summary['source']);
        $this->assertFalse($summary['from_lw321']);
        $this->assertNull($summary['reference_period']);
        $this->assertFalse($summary['has_issues']);
        $this->assertSame([], $summary['samples']);
    }

    public function test_branch_scope_limits_counts_to_the_user_branch(): void
    {
        $this->insertRows('2026-10-21', 'DAILY:', [
            ['acc' => 'A1', 'tgl' => '2026-10-21', 'plafon' => 1, 'pn' => null, 'cabang' => 'KC MADIUN'],
            ['acc' => 'A2', 'tgl' => '2026-10-21', 'plafon' => 1, 'pn' => null, 'cabang' => 'KC NGAWI'],
        ]);
        $scope = ['key' => 'ngawi', 'label' => 'KC Ngawi', 'code4' => '', 'code5' => '', 'slug' => 'kc-ngawi',
            'upper_label' => 'KC NGAWI', 'plain_label' => 'Ngawi', 'pn' => '1'];

        $summary = UserBranchScope::runAs($scope, fn () => app(ProductivitySourceQuality::class)
            ->summarize('2026-10-21', 'MICRO', [], ['KC MADIUN', 'KC NGAWI']));
        $this->assertSame(1, $summary['blank_pn_accounts']);
        $this->assertSame(['A2'], array_column($summary['samples'], 'account'));

        $outside = UserBranchScope::runAs($scope, fn () => app(ProductivitySourceQuality::class)
            ->summarize('2026-10-21', 'MICRO', [], ['KC MADIUN']));
        $this->assertNull($outside);
    }

    public function test_cached_dataset_refreshes_when_loan_data_version_changes(): void
    {
        $service = app(ProductivitySourceQuality::class);
        $this->insertRows('2026-10-21', 'DAILY:', [
            ['acc' => 'A1', 'tgl' => '2026-10-21', 'plafon' => 1, 'pn' => null],
        ]);
        $this->assertSame(1, $service->summarize('2026-10-21', 'MICRO')['blank_pn_accounts']);

        DB::table('daily_loan_dinamis')->update(['pn_pengelola1' => '00001 - RM']);
        $this->assertSame(1, $service->summarize('2026-10-21', 'MICRO')['blank_pn_accounts']);

        ReportCacheVersion::bump('pinjaman');
        $this->assertSame(0, $service->summarize('2026-10-21', 'MICRO')['blank_pn_accounts']);
    }

    public function test_invalid_period_returns_null(): void
    {
        $this->assertNull(app(ProductivitySourceQuality::class)->summarize(null, 'MICRO'));
        $this->assertNull(app(ProductivitySourceQuality::class)->summarize('bukan-tanggal', 'MICRO'));
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function insertRows(string $period, string $prefix, array $rows): void
    {
        DB::table('daily_loan_dinamis')->insert(array_map(static fn (array $row): array => [
            'uniqueid_namareport' => $prefix.$period.':'.$row['acc'],
            'periode' => $period,
            'nomor_rekening1' => $row['acc'],
            'nama_debitur1' => 'DEBITUR '.$row['acc'],
            'cabang1' => 'KC Madiun',
            'unit1' => 'UNIT UJI',
            'cabang_normalized' => $row['cabang'] ?? 'KC MADIUN',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => $row['produk'] ?? 'KURMIKRO',
            'tgl_realisasi' => $row['tgl'],
            'plafon' => $row['plafon'],
            'kolek' => $row['kolek'] ?? '1',
            'umur_tunggakan' => $row['umur'] ?? 0,
            'pn_pengelola1' => array_key_exists('pn', $row) ? $row['pn'] : '00322928 - RM UJI',
            'pn_pemrakarsa1' => $row['pemrakarsa'] ?? '00322928 - RM UJI',
        ], $rows));
    }
}
