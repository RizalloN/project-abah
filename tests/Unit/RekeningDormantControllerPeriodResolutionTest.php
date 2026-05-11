<?php

namespace Tests\Unit;

use App\Http\Controllers\RekeningDormantController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RekeningDormantControllerPeriodResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Cache::flush();

        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->string('uniqueid_SMPN')->primary();
            $table->date('posisi')->nullable()->index();
            $table->string('kantor_cabang')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('CIFNO')->nullable();
            $table->string('no_rekening')->nullable();
            $table->string('jenis_simpanan')->nullable();
            $table->string('status')->nullable();
            $table->decimal('saldo_idr', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    public function test_month_period_uses_single_latest_available_posisi_in_that_month(): void
    {
        DB::table('simpanan_multipn')->insert([
            [
                'uniqueid_SMPN' => 'SMP-20260421-1',
                'posisi' => '2026-04-21',
                'kantor_cabang' => '00045 -- KC Madiun',
                'unit_kerja' => 'Unit A',
                'no_rekening' => 'REK-001',
                'status' => '9',
            ],
            [
                'uniqueid_SMPN' => 'SMP-20260421-2',
                'posisi' => '2026-04-21',
                'kantor_cabang' => '00045 -- KC Madiun',
                'unit_kerja' => 'Unit A',
                'no_rekening' => 'REK-002',
                'status' => '9',
            ],
            [
                'uniqueid_SMPN' => 'SMP-20260424-1',
                'posisi' => '2026-04-24',
                'kantor_cabang' => '00045 -- KC Madiun',
                'unit_kerja' => 'Unit A',
                'no_rekening' => 'REK-003',
                'status' => '9',
            ],
            [
                'uniqueid_SMPN' => 'SMP-20260331-1',
                'posisi' => '2026-03-31',
                'kantor_cabang' => '00045 -- KC Madiun',
                'unit_kerja' => 'Unit A',
                'no_rekening' => 'REK-004',
                'status' => '9',
            ],
            [
                'uniqueid_SMPN' => 'SMP-20260228-1',
                'posisi' => '2026-02-28',
                'kantor_cabang' => '00045 -- KC Madiun',
                'unit_kerja' => 'Unit A',
                'no_rekening' => 'REK-005',
                'status' => '9',
            ],
            [
                'uniqueid_SMPN' => 'SMP-20251231-1',
                'posisi' => '2025-12-31',
                'kantor_cabang' => '00045 -- KC Madiun',
                'unit_kerja' => 'Unit A',
                'no_rekening' => 'REK-006',
                'status' => '9',
            ],
        ]);

        $controller = new RekeningDormantController();
        $response = $controller->fetchData(Request::create('/report/data/rekening-dormant', 'POST', [
            'posisi' => '2026-04',
            'kantor_cabang' => ['KC Madiun'],
        ]));

        $payload = $response->getData(true);

        $this->assertSame('2026-04-24', $payload['effective_dates']['curr']);
        $this->assertSame('2026-03-31', $payload['effective_dates']['mtd']);
        $this->assertSame('2026-02-28', $payload['effective_dates']['m2']);
        $this->assertSame('2025-12-31', $payload['effective_dates']['ytd']);
        $this->assertSame(1, $payload['total']['current']);
        $this->assertSame(1, $payload['total']['mtd_base']);
        $this->assertSame(1, $payload['total']['m2_base']);
        $this->assertSame(1, $payload['total']['ytd_base']);
        $this->assertArrayNotHasKey('yoy', $payload['total']);
        $this->assertArrayNotHasKey('yoy_base', $payload['total']);
        $this->assertSame(1, $payload['data'][0]['current']);
        $this->assertSame(1, $payload['data'][0]['m2_base']);
        $this->assertArrayNotHasKey('yoy', $payload['data'][0]);
        $this->assertArrayNotHasKey('yoy_base', $payload['data'][0]);
        $this->assertSame('24 Apr 26', $payload['labels']['curr']);
    }
}
