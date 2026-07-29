<?php

namespace Tests\Unit;

use App\Services\Reports\NewPayrollReportService;
use App\Support\RkaLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class NewPayrollReportServiceTest extends TestCase
{
    private string $originalDefaultConnection;
    private ?string $originalSqliteDatabase;
    private string $tempDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) Config::get('database.default');
        $this->originalSqliteDatabase = Config::get('database.connections.sqlite.database');
        $this->tempDatabase = tempnam(sys_get_temp_dir(), 'abah_payroll_test_');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->tempDatabase);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('performance_pis_per_produk', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('posisi')->nullable();
            $table->string('kanca')->nullable();
            $table->string('uker')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->decimal('saldo_britama_kerjasama', 20, 2)->nullable();
            $table->date('tanggal_pembuatan_rekening')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        DB::disconnect('sqlite');
        Config::set('database.default', $this->originalDefaultConnection);
        Config::set('database.connections.sqlite.database', $this->originalSqliteDatabase);
        DB::purge('sqlite');

        if (isset($this->tempDatabase) && is_file($this->tempDatabase)) {
            unlink($this->tempDatabase);
        }

        parent::tearDown();
    }

    public function test_new_payroll_uses_year_to_date_opened_accounts_and_balance(): void
    {
        DB::table('performance_pis_per_produk')->insert([
            $this->row('curr-jan', '2026-07-11', 'KC MADIUN', 'KC MADIUN', '2026-01-10', 100),
            $this->row('curr-jun', '2026-07-11', 'KC MADIUN', 'KC MADIUN', '2026-06-15', 200),
            $this->row('curr-jul', '2026-07-11', 'KC MADIUN', 'KC MADIUN', '2026-07-05', 300),
            $this->row('curr-prev-year', '2026-07-11', 'KC MADIUN', 'KC MADIUN', '2025-12-31', 999),
            $this->row('prev-jan', '2026-06-30', 'KC MADIUN', 'KC MADIUN', '2026-01-12', 50),
            $this->row('prev-jun', '2026-06-30', 'KC MADIUN', 'KC MADIUN', '2026-06-20', 60),
            $this->row('prev-jul-excluded', '2026-06-30', 'KC MADIUN', 'KC MADIUN', '2026-07-01', 700),
            $this->row('yoy-jan', '2025-07-31', 'KC MADIUN', 'KC MADIUN', '2025-01-03', 10),
            $this->row('yoy-jul', '2025-07-31', 'KC MADIUN', 'KC MADIUN', '2025-07-20', 20),
            $this->row('yoy-aug-excluded', '2025-07-31', 'KC MADIUN', 'KC MADIUN', '2025-08-01', 800),
        ]);

        $service = new NewPayrollReportService($this->fakeRkaLookup());
        $response = $service->fetchData(new Request([
            'posisi' => '2026-07-11',
            'branch_office' => ['KC MADIUN'],
        ]));

        $payload = $response->getData(true);
        $row = $payload['data'][0];

        $this->assertSame('Jan-Jul 26', $payload['labels']['curr']);
        $this->assertSame('Jan-Jun 26', $payload['labels']['prev']);
        $this->assertSame('Jan-Jul 25', $payload['labels']['yoy_prev']);
        $this->assertSame(3, $row['rekening']['curr']);
        $this->assertSame(2, $row['rekening']['prev']);
        $this->assertSame(2, $row['rekening']['yoy_prev']);
        $this->assertEqualsWithDelta(600.0, $row['saldo']['curr'], 0.01);
        $this->assertEqualsWithDelta(110.0, $row['saldo']['prev'], 0.01);
        $this->assertEqualsWithDelta(30.0, $row['saldo']['yoy_prev'], 0.01);
    }

    public function test_payroll_berkualitas_counts_debitur_with_balance_of_at_least_five_million(): void
    {
        DB::table('performance_pis_per_produk')->insert([
            $this->row('curr-qualified-exact', '2026-07-11', 'KC MADIUN', 'KC MADIUN', '2026-01-10', 5000000),
            $this->row('curr-qualified-above', '2026-07-11', 'KC MADIUN', 'KC MADIUN', '2026-05-10', 7500000),
            $this->row('curr-not-qualified', '2026-07-11', 'KC MADIUN', 'KC MADIUN', '2026-06-10', 4999999),
            $this->row('prev-qualified', '2026-06-30', 'KC MADIUN', 'KC MADIUN', '2026-04-10', 5000000),
            $this->row('yoy-qualified', '2025-07-31', 'KC MADIUN', 'KC MADIUN', '2025-03-10', 6000000),
        ]);

        $service = new NewPayrollReportService($this->fakeRkaLookup());
        $payload = $service->fetchData(new Request([
            'posisi' => '2026-07-11',
            'branch_office' => ['KC MADIUN'],
        ]))->getData(true);

        $row = $payload['data'][0];

        $this->assertSame(2, $row['kualitas']['curr']);
        $this->assertSame(1, $row['kualitas']['prev']);
        $this->assertSame(1, $row['kualitas']['yoy_prev']);
        $this->assertSame(2, $payload['total']['kualitas']['curr']);
    }

    private function fakeRkaLookup(): RkaLookupService
    {
        $mock = Mockery::mock(RkaLookupService::class);
        $mock->shouldReceive('resolveMonthColumn')->andReturn('juli');
        $mock->shouldReceive('resolveMonthLabel')->andReturn('Jul 2026');
        $mock->shouldReceive('aggregateByGroup')->andReturn(['rekening' => []]);
        $mock->shouldReceive('aggregateByGroupWithRegionalFilter')->andReturn(['rekening' => []]);
        $mock->shouldReceive('aggregateByKancaWithSummaryFallback')->andReturn(['rekening' => []]);

        return $mock;
    }

    private function row(string $id, string $posisi, string $kanca, string $uker, string $openedAt, float $saldo): array
    {
        return [
            'uniqueid_namareport' => $id,
            'posisi' => $posisi,
            'kanca' => $kanca,
            'uker' => $uker,
            'nomor_rekening' => $id,
            'tanggal_pembuatan_rekening' => $openedAt,
            'saldo_britama_kerjasama' => $saldo,
        ];
    }
}
