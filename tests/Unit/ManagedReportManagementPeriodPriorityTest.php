<?php

namespace Tests\Unit;

use App\Support\ManagedReportManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagedReportManagementPeriodPriorityTest extends TestCase
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
    }

    public function test_daily_priority_column_wins_even_when_it_is_blank_and_monthly_column_is_populated(): void
    {
        Schema::create('jumlah_merchant_detail', function (Blueprint $table): void {
            $table->date('posisi')->nullable();
            $table->string('periode')->nullable();
        });

        DB::table('jumlah_merchant_detail')->insert([
            ['posisi' => null, 'periode' => '2026-09'],
            ['posisi' => null, 'periode' => '2026-09'],
            ['posisi' => null, 'periode' => '2026-09'],
        ]);

        [$periodColumn] = (new ManagedReportManagementService())->resolveManagementScopeColumns(
            'jumlah_merchant_detail',
            Schema::getColumnListing('jumlah_merchant_detail')
        );

        $this->assertSame('posisi', $periodColumn);
    }

    public function test_qris_daily_priority_column_wins_even_when_monthly_column_has_more_populated_rows(): void
    {
        Schema::create('jumlah_merchant_qris_detail', function (Blueprint $table): void {
            $table->date('POSISI')->nullable();
            $table->string('PERIODE')->nullable();
        });

        DB::table('jumlah_merchant_qris_detail')->insert([
            ['POSISI' => '2026-09-23', 'PERIODE' => '2026-09'],
            ['POSISI' => null, 'PERIODE' => '2026-09'],
            ['POSISI' => null, 'PERIODE' => '2026-09'],
        ]);

        [$periodColumn] = (new ManagedReportManagementService())->resolveManagementScopeColumns(
            'jumlah_merchant_qris_detail',
            Schema::getColumnListing('jumlah_merchant_qris_detail')
        );

        $this->assertSame('POSISI', $periodColumn);
    }

    public function test_brimo_fin_all_prefers_daily_position_when_daily_and_monthly_columns_are_both_populated(): void
    {
        Schema::create('brimo_fin_all', function (Blueprint $table): void {
            $table->string('periode')->nullable();
            $table->date('posisi')->nullable();
        });

        DB::table('brimo_fin_all')->insert([
            ['periode' => '2026-09', 'posisi' => '2026-09-23'],
        ]);

        [$periodColumn] = (new ManagedReportManagementService())->resolveManagementScopeColumns(
            'brimo_fin_all',
            Schema::getColumnListing('brimo_fin_all')
        );

        $this->assertSame('posisi', $periodColumn);
    }

    public function test_priority_falls_back_when_preferred_column_is_absent_from_schema(): void
    {
        Schema::create('brimo_fin_all', function (Blueprint $table): void {
            $table->string('periode')->nullable();
        });

        DB::table('brimo_fin_all')->insert([
            ['periode' => '2026-09'],
        ]);

        [$periodColumn] = (new ManagedReportManagementService())->resolveManagementScopeColumns(
            'brimo_fin_all',
            Schema::getColumnListing('brimo_fin_all')
        );

        $this->assertSame('periode', $periodColumn);
    }

    public function test_native_monthly_report_keeps_monthly_period_column(): void
    {
        Schema::create('casa_brilink_web', function (Blueprint $table): void {
            $table->string('periode')->nullable();
            $table->date('posisi')->nullable();
        });

        DB::table('casa_brilink_web')->insert([
            ['periode' => '2026-09', 'posisi' => '2026-09-23'],
        ]);

        [$periodColumn] = (new ManagedReportManagementService())->resolveManagementScopeColumns(
            'casa_brilink_web',
            Schema::getColumnListing('casa_brilink_web')
        );

        $this->assertSame('periode', $periodColumn);
    }
}
