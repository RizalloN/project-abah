<?php

namespace Tests\Unit;

use App\Support\ReportSnapshotBuilder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

class ReportSnapshotBuilderDashboardBucketTest extends TestCase
{
    private string $originalDefaultConnection;

    private string $testPeriod;

    private array $sourceIds = [];

    private array $accountNumbers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->testPeriod = sprintf('2099-12-%02d', random_int(1, 28));

        $this->configureMysqlConnectionFromEnvironment();
        $this->connectMysqlOrSkip();
        $this->ensureSnapshotTablesExistOrSkip();

        if (DB::table('daily_loan_dinamis')->where('periode', $this->testPeriod)->exists()) {
            $this->markTestSkipped("Test period {$this->testPeriod} is already in use.");
        }
    }

    protected function tearDown(): void
    {
        try {
            if (config('database.default') === 'mysql' && !empty($this->sourceIds)) {
                DB::table('dashboard_pinjaman_snapshots')
                    ->where('periode', $this->testPeriod)
                    ->whereIn('account_number', $this->accountNumbers)
                    ->delete();

                DB::table('daily_loan_dinamis')
                    ->whereIn('uniqueid_namareport', $this->sourceIds)
                    ->delete();
            }
        } catch (Throwable) {
            // Best-effort cleanup for isolated MySQL test rows.
        }

        Config::set('database.default', $this->originalDefaultConnection);
        DB::purge('mysql');
        DB::purge($this->originalDefaultConnection);

        parent::tearDown();
    }

    public function test_dashboard_snapshot_rebuild_matches_kolek_detail_crosschecked_by_age(): void
    {
        $this->sourceIds = [
            'test-dashboard-bucket-detail1-' . uniqid(),
            'test-dashboard-bucket-detail2-' . uniqid(),
            'test-dashboard-bucket-detail3-' . uniqid(),
            'test-dashboard-bucket-detail4-' . uniqid(),
            'test-dashboard-bucket-detail5-' . uniqid(),
            'test-dashboard-bucket-detail6-' . uniqid(),
        ];
        $this->accountNumbers = [
            'UT-DETAIL1-' . uniqid(),
            'UT-DETAIL2-' . uniqid(),
            'UT-DETAIL3-' . uniqid(),
            'UT-DETAIL4-' . uniqid(),
            'UT-DETAIL5-' . uniqid(),
            'UT-DETAIL6-' . uniqid(),
        ];

        DB::table('daily_loan_dinamis')->insert([
            [
                'uniqueid_namareport' => $this->sourceIds[0],
                'periode' => $this->testPeriod,
                'nomor_rekening1' => $this->accountNumbers[0],
                'baki_debet1' => 1000,
                'kolek_detail' => 'L',
                'umur_tunggakan' => 2,
                'flag_restruk' => null,
                'kol_adk1' => '2',
                'kolek' => '2',
                'segmen_dashboard' => 'TEST',
                'produk_dashboard' => 'TEST',
                'cabang1' => 'TEST',
                'unit1' => 'TEST',
            ],
            [
                'uniqueid_namareport' => $this->sourceIds[1],
                'periode' => $this->testPeriod,
                'nomor_rekening1' => $this->accountNumbers[1],
                'baki_debet1' => 2000,
                'kolek_detail' => 'L',
                'umur_tunggakan' => 0,
                'flag_restruk' => 'Y',
                'kol_adk1' => '1',
                'kolek' => '1',
                'segmen_dashboard' => 'TEST',
                'produk_dashboard' => 'TEST',
                'cabang1' => 'TEST',
                'unit1' => 'TEST',
            ],
            [
                'uniqueid_namareport' => $this->sourceIds[2],
                'periode' => $this->testPeriod,
                'nomor_rekening1' => $this->accountNumbers[2],
                'baki_debet1' => 3000,
                'kolek_detail' => '',
                'umur_tunggakan' => 0,
                'flag_restruk' => 'Y',
                'kol_adk1' => '1',
                'kolek' => '1',
                'segmen_dashboard' => 'TEST',
                'produk_dashboard' => 'TEST',
                'cabang1' => 'TEST',
                'unit1' => 'TEST',
            ],
            [
                'uniqueid_namareport' => $this->sourceIds[3],
                'periode' => $this->testPeriod,
                'nomor_rekening1' => $this->accountNumbers[3],
                'baki_debet1' => 4000,
                'kolek_detail' => 'KL',
                'umur_tunggakan' => null,
                'flag_restruk' => null,
                'kol_adk1' => '1',
                'kolek' => '1',
                'segmen_dashboard' => 'TEST',
                'produk_dashboard' => 'TEST',
                'cabang1' => 'TEST',
                'unit1' => 'TEST',
            ],
            [
                'uniqueid_namareport' => $this->sourceIds[4],
                'periode' => $this->testPeriod,
                'nomor_rekening1' => $this->accountNumbers[4],
                'baki_debet1' => 5000,
                'kolek_detail' => 'LUNAS',
                'umur_tunggakan' => null,
                'flag_restruk' => null,
                'kol_adk1' => '3',
                'kolek' => '3',
                'segmen_dashboard' => 'TEST',
                'produk_dashboard' => 'TEST',
                'cabang1' => 'TEST',
                'unit1' => 'TEST',
            ],
            [
                'uniqueid_namareport' => $this->sourceIds[5],
                'periode' => $this->testPeriod,
                'nomor_rekening1' => $this->accountNumbers[5],
                'baki_debet1' => 6000,
                'kolek_detail' => '0',
                'umur_tunggakan' => null,
                'flag_restruk' => null,
                'kol_adk1' => '4',
                'kolek' => '4',
                'segmen_dashboard' => 'TEST',
                'produk_dashboard' => 'TEST',
                'cabang1' => 'TEST',
                'unit1' => 'TEST',
            ],
        ]);

        $result = app(ReportSnapshotBuilder::class)->rebuildDashboard($this->testPeriod, true);

        $this->assertArrayHasKey($this->testPeriod, $result);
        $this->assertSame(6, $result[$this->testPeriod]);

        $actualBuckets = DB::table('dashboard_pinjaman_snapshots')
            ->where('periode', $this->testPeriod)
            ->whereIn('account_number', $this->accountNumbers)
            ->orderBy('account_number')
            ->pluck('quality_bucket', 'account_number')
            ->all();

        $expectedBuckets = [
            $this->accountNumbers[0] => 'DPK 1',
            $this->accountNumbers[1] => 'LR',
            $this->accountNumbers[2] => 'L',
            $this->accountNumbers[3] => 'KL',
            $this->accountNumbers[4] => 'Pay',
            $this->accountNumbers[5] => 'Unknown',
        ];

        ksort($expectedBuckets);
        ksort($actualBuckets);

        $this->assertSame($expectedBuckets, $actualBuckets);
    }

    public function test_force_dashboard_snapshot_rebuild_replaces_period_without_antijoin_cleanup(): void
    {
        $this->sourceIds = [
            'test-dashboard-force-source-' . uniqid(),
        ];
        $this->accountNumbers = [
            'UT-FORCE-' . uniqid(),
            'UT-FORCE-STALE-' . uniqid(),
        ];

        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => $this->sourceIds[0],
            'periode' => $this->testPeriod,
            'nomor_rekening1' => $this->accountNumbers[0],
            'baki_debet1' => 1000,
            'kolek_detail' => 'L',
            'umur_tunggakan' => 0,
            'flag_restruk' => null,
            'kol_adk1' => '1',
            'kolek' => '1',
            'segmen_dashboard' => 'TEST',
            'produk_dashboard' => 'TEST',
            'cabang1' => 'TEST',
            'unit1' => 'TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dashboard_pinjaman_snapshots')->insert([
            'uniqueid_dps' => 'test-dashboard-force-stale-' . uniqid(),
            'periode' => $this->testPeriod,
            'account_number' => $this->accountNumbers[1],
            'loan_balance' => 999,
            'quality_bucket' => 'STALE',
            'segmen_dashboard' => 'TEST',
            'produk_dashboard' => 'TEST',
            'cabang1' => 'TEST',
            'unit1' => 'TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $result = app(ReportSnapshotBuilder::class)->rebuildDashboard($this->testPeriod, true);

        $this->assertSame(1, $result[$this->testPeriod]);
        $this->assertSame(
            [$this->accountNumbers[0]],
            DB::table('dashboard_pinjaman_snapshots')
                ->where('periode', $this->testPeriod)
                ->whereIn('account_number', $this->accountNumbers)
                ->pluck('account_number')
                ->all()
        );

        $this->assertNotContains(
            true,
            array_map(
                static fn (string $sql): bool => str_contains($sql, 'DELETE snap') && str_contains($sql, 'LEFT JOIN'),
                $queries
            )
        );
    }

    private function configureMysqlConnectionFromEnvironment(): void
    {
        $env = $this->readDotEnv(base_path('.env'));

        Config::set('database.connections.mysql.host', $env['DB_HOST'] ?? '127.0.0.1');
        Config::set('database.connections.mysql.port', $env['DB_PORT'] ?? '3306');
        Config::set('database.connections.mysql.database', $env['DB_DATABASE'] ?? null);
        Config::set('database.connections.mysql.username', $env['DB_USERNAME'] ?? 'root');
        Config::set('database.connections.mysql.password', $env['DB_PASSWORD'] ?? '');
        Config::set('database.connections.mysql.charset', $env['DB_CHARSET'] ?? 'utf8mb4');
        Config::set('database.connections.mysql.collation', $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci');
        Config::set('database.default', 'mysql');
    }

    private function connectMysqlOrSkip(): void
    {
        try {
            DB::purge('mysql');
            DB::reconnect('mysql')->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL connection is unavailable for snapshot test: ' . $e->getMessage());
        }
    }

    private function ensureSnapshotTablesExistOrSkip(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis') || !Schema::hasTable('dashboard_pinjaman_snapshots')) {
            $this->markTestSkipped('MySQL snapshot tables are unavailable for snapshot test.');
        }
    }

    private function readDotEnv(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $values;
    }
}
