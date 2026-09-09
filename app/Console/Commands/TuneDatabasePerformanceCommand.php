<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TuneDatabasePerformanceCommand extends Command
{
    protected $signature = 'database:performance-tune {--check : Display current values without changing them}';

    protected $description = 'Apply safe runtime MariaDB settings for the project workload';

    public function handle(): int
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->line('Database performance tuning dilewati untuk driver non-MySQL.');

            return self::SUCCESS;
        }

        $targetBytes = max(1024, (int) config('performance.database.buffer_pool_mb', 4096)) * 1024 * 1024;
        $slowSeconds = max(1, (int) config('performance.database.slow_query_seconds', 2));
        $minRows = max(0, (int) config('performance.database.slow_query_min_examined_rows', 1000));
        $values = $this->globalVariables();
        $currentBufferPoolBytes = (int) $values->get('innodb_buffer_pool_size', 0);
        $bufferPoolGrowthRequested = false;

        if (! (bool) $this->option('check') && (bool) config('performance.database.runtime_tuning_enabled', true)) {
            // Never shrink an administrator-provided buffer pool. This command is
            // also scheduled hourly, so an unconditional SET could silently undo
            // a larger persistent my.ini setting.
            if ($currentBufferPoolBytes < $targetBytes) {
                DB::statement("SET GLOBAL innodb_buffer_pool_size = {$targetBytes}");
                $bufferPoolGrowthRequested = true;
            }

            DB::statement("SET GLOBAL long_query_time = {$slowSeconds}");
            DB::statement("SET GLOBAL min_examined_row_limit = {$minRows}");
            DB::statement("SET GLOBAL slow_query_log = 'ON'");

            $values = $bufferPoolGrowthRequested
                ? $this->waitForBufferPoolResize($targetBytes)
                : $this->globalVariables();
        }

        $this->table(['Variable', 'Value'], $values->map(fn (string $value, string $key): array => [$key, $value])->values()->all());

        if ((int) $values->get('innodb_buffer_pool_size', 0) < $targetBytes && ! (bool) $this->option('check')) {
            $this->warn('Buffer pool belum mencapai target; periksa dukungan dynamic resize atau hak akses database.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function globalVariables(): Collection
    {
        return collect(DB::select("SHOW GLOBAL VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size', 'long_query_time', 'min_examined_row_limit', 'slow_query_log', 'slow_query_log_file')"))
            ->mapWithKeys(fn (object $row): array => [(string) $row->Variable_name => (string) $row->Value]);
    }

    private function waitForBufferPoolResize(int $targetBytes): Collection
    {
        $deadline = microtime(true) + 30;

        do {
            $values = $this->globalVariables();
            if ((int) $values->get('innodb_buffer_pool_size', 0) >= $targetBytes) {
                return $values;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        return $values;
    }
}
