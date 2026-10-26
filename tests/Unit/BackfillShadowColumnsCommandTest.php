<?php

namespace Tests\Unit;

use App\Console\Commands\BackfillShadowColumnsCommand;
use App\Jobs\ProcessShadowBackfillJob;
use App\Support\DailyLoanManualSegmentRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillShadowColumnsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->string('description')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('branch1')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('cifno')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->dateTime('shadow_built_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function test_backfill_streams_safe_key_ranges_and_handles_quotes_in_source_ids(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        foreach (["row'001", 'row002', 'row003'] as $id) {
            DB::table('daily_loan_dinamis')->insert([
                'uniqueid_namareport' => $id,
                'periode' => '2026-07-21',
                'segmen_dashboard' => 'Micro Retail',
                'produk_dashboard' => 'KUR-Kecil',
                'cabang1' => 'KC Madiun',
                'unit1' => 'Unit 1',
                'branch1' => 'Branch 1',
                'pn_pengelola1' => ' 00123 ',
                'cifno' => ' cif-01 ',
                'updated_at' => '2026-07-21 10:00:00',
            ]);
        }

        $this->artisan('shadow:backfill', [
            '--periods' => '2026-07-21',
            '--chunk-size' => 2,
            '--skip-snapshot' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $rows = DB::table('daily_loan_dinamis')->orderBy('uniqueid_namareport')->get();

        $this->assertCount(3, $rows);
        $this->assertTrue($rows->every(fn ($row): bool => $row->segmen_kinerja === 'MICRORETAIL'));
        $this->assertTrue($rows->every(fn ($row): bool => $row->produk_kinerja === 'KURKECIL'));
        $this->assertTrue($rows->every(fn ($row): bool => $row->shadow_built_at !== null));
        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'daily_loan_dinamis') && str_contains($sql, 'count(')
        ));
    }

    public function test_backfill_reclassifies_completed_shadow_rows_when_description_rule_changes(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'ritkom-001',
            'periode' => '2026-08-22',
            'description' => '10. RITKOM -> Rp. 5 M S/D 15 M',
            'segmen_dashboard' => 'Small',
            'produk_dashboard' => 'Commercial',
            'cabang1' => 'KC Madiun',
            'unit1' => 'KC Madiun',
            'branch1' => '45',
            'pn_pengelola1' => '00123',
            'cifno' => 'CIF-01',
            'segmen_kinerja' => 'SMALL',
            'produk_kinerja' => 'COMMERCIAL',
            'cabang_normalized' => 'KC MADIUN',
            'unit_normalized' => 'KC MADIUN',
            'branch_normalized' => '45',
            'rm_normalized' => '00123',
            'cifno_clean' => 'CIF-01',
            'shadow_built_at' => '2026-08-22 11:00:00',
            'updated_at' => '2026-08-22 10:00:00',
        ]);

        $this->artisan('shadow:backfill', [
            '--periods' => '2026-08-22',
            '--chunk-size' => 100,
            '--skip-snapshot' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $row = DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'ritkom-001')->first();

        $this->assertSame('MEDIUM', $row->segmen_kinerja);
        $this->assertSame('COMMERCIAL', $row->produk_kinerja);
        $this->assertNotNull($row->shadow_built_at);
    }

    public function test_auto_discover_trusts_verified_older_periods_and_always_rechecks_recent_ones(): void
    {
        $periods = ['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04', '2026-07-05'];
        foreach ($periods as $period) {
            DB::table('daily_loan_dinamis')->insert([
                'uniqueid_namareport' => 'row-' . $period,
                'periode' => $period,
                'description' => '10. RITKOM -> Rp. 5 M S/D 15 M',
                'segmen_dashboard' => 'Small',
                'produk_dashboard' => 'Commercial',
                'cabang1' => 'KC Madiun',
                'unit1' => 'KC Madiun',
                'branch1' => '45',
                'pn_pengelola1' => '00123',
                'cifno' => 'CIF-01',
                'updated_at' => $period . ' 10:00:00',
            ]);
        }
        $this->artisan('shadow:backfill', ['--periods' => implode(',', $periods), '--skip-snapshot' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $existsProbes = function (): int {
            $count = 0;
            DB::listen(function ($query) use (&$count): void {
                $sql = strtolower($query->sql);
                if (str_contains($sql, 'exists') && str_contains($sql, 'from "daily_loan_dinamis"')) {
                    $count++;
                }
            });
            $this->artisan('shadow:backfill', ['--skip-snapshot' => true, '--no-interaction' => true])->assertSuccessful();

            return $count;
        };

        $this->assertSame(5, $existsProbes());
        Queue::fake();
        DB::table('daily_loan_dinamis')->where('periode', '2026-07-05')->update(['shadow_built_at' => null]);

        $this->artisan('shadow:backfill', ['--queue' => true, '--skip-snapshot' => true, '--no-interaction' => true])->assertSuccessful();

        Queue::assertPushed(ProcessShadowBackfillJob::class, fn (ProcessShadowBackfillJob $job): bool => $job->periods === ['2026-07-05']);
        $this->assertTrue(Cache::has(BackfillShadowColumnsCommand::VERIFIED_CACHE_PREFIX . DailyLoanManualSegmentRule::VERSION . ':2026-07-01'));
        $this->assertFalse(Cache::has(BackfillShadowColumnsCommand::VERIFIED_CACHE_PREFIX . DailyLoanManualSegmentRule::VERSION . ':2026-07-05'));
    }

    public function test_auto_discover_skips_verified_older_periods_on_the_next_run(): void
    {
        foreach (['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04', '2026-07-05'] as $period) {
            Cache::put(BackfillShadowColumnsCommand::VERIFIED_CACHE_PREFIX . DailyLoanManualSegmentRule::VERSION . ':' . $period, time(), 600);
            DB::table('daily_loan_dinamis')->insert([
                'uniqueid_namareport' => 'row-' . $period,
                'periode' => $period,
                'updated_at' => $period . ' 10:00:00',
            ]);
        }

        $probed = [];
        DB::listen(function ($query) use (&$probed): void {
            if (str_contains(strtolower($query->sql), 'exists') && str_contains(strtolower($query->sql), 'from "daily_loan_dinamis"')) {
                $probed[] = $query->bindings[0] ?? null;
            }
        });
        Queue::fake();

        $this->artisan('shadow:backfill', ['--queue' => true, '--skip-snapshot' => true, '--no-interaction' => true])->assertSuccessful();

        // Only the three newest periods are probed; older verified ones are trusted.
        $this->assertCount(3, $probed);
        Queue::assertPushed(ProcessShadowBackfillJob::class, fn (ProcessShadowBackfillJob $job): bool => $job->periods === ['2026-07-05', '2026-07-04', '2026-07-03']);
    }
}
