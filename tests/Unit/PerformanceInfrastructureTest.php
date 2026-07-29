<?php

namespace Tests\Unit;

use App\Services\Import\ActiveImportJobCounter;
use App\Support\SargableDateFilter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class PerformanceInfrastructureTest extends TestCase
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

        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });
        Schema::create('dated_rows', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('posisi');
        });
    }

    public function test_active_import_counter_is_cached_and_can_be_invalidated(): void
    {
        DB::table('import_jobs')->insert(['status' => 'queued']);
        $counter = new ActiveImportJobCounter;

        $this->assertSame(1, $counter->count());
        DB::table('import_jobs')->insert(['status' => 'processing']);
        $this->assertSame(1, $counter->count());

        $counter->forget();
        $this->assertSame(2, $counter->count());
    }

    public function test_sargable_date_filter_preserves_full_day_semantics(): void
    {
        DB::table('dated_rows')->insert([
            ['posisi' => '2026-07-10 00:00:00'],
            ['posisi' => '2026-07-10 23:59:59'],
            ['posisi' => '2026-07-11 00:00:00'],
        ]);

        $query = SargableDateFilter::apply(DB::table('dated_rows'), 'posisi', '=', '2026-07-10');

        $this->assertSame(2, $query->count());
        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
    }

    public function test_runtime_application_code_does_not_read_env_outside_config_files(): void
    {
        $violations = [];
        $files = Finder::create()->files()->in(app_path())->name('*.php');

        foreach ($files as $file) {
            if (preg_match('/\benv\s*\(/', $file->getContents()) === 1) {
                $violations[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $violations, 'Runtime env() calls bypass Laravel config cache: '.implode(', ', $violations));
    }

    public function test_application_queries_do_not_wrap_indexed_dates_with_where_date(): void
    {
        $violations = [];
        $files = Finder::create()->files()->in(app_path())->name('*.php');

        foreach ($files as $file) {
            if (str_contains($file->getContents(), '->whereDate(')) {
                $violations[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $violations, 'whereDate() prevents date indexes from being used: '.implode(', ', $violations));
    }
}
