<?php

namespace Tests\Unit;

use App\Support\DashboardPinjamanKreditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardPinjamanKreditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::dropAllTables();

        Schema::create('rka', function (Blueprint $table): void {
            $table->string('kanca')->nullable();
            $table->string('desc_uker')->nullable();
            $table->string('mata_anggaran')->nullable();
            $table->decimal('apr', 22, 2)->default(0);
            $table->decimal('may', 22, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->date('snapshot_period')->nullable();
            $table->string('kanca_label')->nullable();
            $table->string('unit_label')->nullable();

            foreach ($this->snapshotMetricColumns() as $column) {
                $table->decimal($column, 22, 2)->default(0);
            }
        });
    }

    public function test_sme_rka_uses_kcp_detail_then_falls_back_to_kanca_summary_when_detail_is_zero(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            $this->snapshotRow('2026-05-15', 'KC Madiun', 500_000_000),
            $this->snapshotRow('2026-05-15', 'KC Magetan', 350_000_000),
        ]);

        DB::table('rka')->insert([
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '45-KC Madiun',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'apr' => 900_000_000,
                'may' => 900_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Madiun',
                'desc_uker' => '552-KCP Caruban',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'apr' => 100_000_000,
                'may' => 110_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'kanca' => 'KC Magetan',
                'desc_uker' => '49-KC Magetan',
                'mata_anggaran' => 'B.2.a. Kredit Kecil Non Cash Collateral',
                'apr' => 300_000_000,
                'may' => 310_000_000,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $payload = app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData('2026-05-15', 'SME');
        $osRows = collect($payload['os']);

        $madiun = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Madiun' && ($row['category'] ?? '') === 'Kecil non Cashcoll');
        $magetan = $osRows->first(fn (array $row): bool => ($row['branch'] ?? '') === 'KC Magetan' && ($row['category'] ?? '') === 'Kecil non Cashcoll');

        $this->assertNotNull($madiun);
        $this->assertNotNull($magetan);
        $this->assertEqualsWithDelta(100_000_000, $madiun['rka_m1'], 0.01);
        $this->assertEqualsWithDelta(110_000_000, $madiun['rka_current'], 0.01);
        $this->assertEqualsWithDelta(300_000_000, $magetan['rka_m1'], 0.01);
        $this->assertEqualsWithDelta(310_000_000, $magetan['rka_current'], 0.01);
    }

    private function snapshotRow(string $period, string $branch, int $os): array
    {
        $row = [
            'snapshot_period' => $period,
            'kanca_label' => $branch,
            'unit_label' => $branch,
        ];

        foreach ($this->snapshotMetricColumns() as $column) {
            $row[$column] = 0;
        }

        $row['kecil_non_cashcoll_os'] = $os;

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function snapshotMetricColumns(): array
    {
        return [
            'kecil_non_cashcoll_os',
            'cashcoll_os',
            'kecil_non_cashcoll_sml',
            'cashcoll_sml',
            'kecil_non_cashcoll_npl',
            'cashcoll_npl',
            'briguna_konsumer_os',
            'briguna_konsumer_sml',
            'briguna_konsumer_npl',
            'kpr_os',
            'kpr_sml',
            'kpr_npl',
            'micro_os',
            'briguna_mikro_os',
            'kupedes_os',
            'kur_mikro_os',
            'kur_kecil_os',
            'kur_kpp_os',
            'micro_sml',
            'briguna_mikro_sml',
            'kupedes_sml',
            'kur_mikro_sml',
            'kur_kecil_sml',
            'kur_kpp_sml',
            'micro_npl',
            'briguna_mikro_npl',
            'kupedes_npl',
            'kur_mikro_npl',
            'kur_kecil_npl',
            'kur_kpp_npl',
        ];
    }
}
