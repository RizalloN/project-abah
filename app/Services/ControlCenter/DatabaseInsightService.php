<?php

namespace App\Services\ControlCenter;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only operational view of the MySQL/MariaDB server: connection pressure,
 * long-running statements, storage growth and free disk space. Every query
 * reads server metadata only; no table data is scanned.
 */
class DatabaseInsightService
{
    private const CACHE_KEY = 'control-center:database-insight:v1';
    private const CACHE_SECONDS = 20;
    public const LONG_QUERY_SECONDS = 300;

    public function snapshot(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->build());
    }

    private function build(): array
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return ['available' => false, 'generated_at' => now()->toIso8601String()];
        }

        $status = $this->keyValue('SHOW GLOBAL STATUS WHERE Variable_name IN (\'Threads_connected\', \'Threads_running\', \'Max_used_connections\', \'Innodb_row_lock_waits\', \'Uptime\', \'Slow_queries\', \'Aborted_connects\', \'Questions\')');
        $variables = $this->keyValue('SHOW VARIABLES WHERE Variable_name IN (\'max_connections\', \'datadir\', \'innodb_buffer_pool_size\', \'version\', \'long_query_time\')');
        $maxConnections = max(1, (int) ($variables['max_connections'] ?? 151));
        $connected = (int) ($status['Threads_connected'] ?? 0);

        $size = DB::selectOne('SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes, COUNT(*) AS tables FROM information_schema.tables WHERE table_schema = DATABASE()');
        $topTables = array_map(static fn ($row): array => [
            'table' => (string) $row->table_name,
            'bytes' => (int) $row->bytes,
            'rows' => (int) $row->table_rows,
            'engine' => (string) $row->engine,
        ], DB::select('SELECT table_name, engine, table_rows, (data_length + index_length) AS bytes FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY bytes DESC LIMIT 12'));

        $uptime = max(1, (int) ($status['Uptime'] ?? 1));

        return [
            'available' => true,
            'generated_at' => now()->toIso8601String(),
            'server' => [
                'version' => (string) ($variables['version'] ?? ''),
                'uptime_seconds' => $uptime,
                'buffer_pool_bytes' => (int) ($variables['innodb_buffer_pool_size'] ?? 0),
                'queries_per_second' => round(((int) ($status['Questions'] ?? 0)) / $uptime, 1),
            ],
            'connections' => [
                'connected' => $connected,
                'running' => (int) ($status['Threads_running'] ?? 0),
                'max' => $maxConnections,
                'peak' => (int) ($status['Max_used_connections'] ?? 0),
                'usage_ratio' => round($connected / $maxConnections, 3),
                'aborted_connects' => (int) ($status['Aborted_connects'] ?? 0),
            ],
            'pressure' => [
                'row_lock_waits' => (int) ($status['Innodb_row_lock_waits'] ?? 0),
                'slow_queries' => (int) ($status['Slow_queries'] ?? 0),
                'long_query_time' => (float) ($variables['long_query_time'] ?? 10),
            ],
            'long_queries' => $this->longQueries(),
            'storage' => [
                'database_bytes' => (int) ($size->bytes ?? 0),
                'tables' => (int) ($size->tables ?? 0),
                'top_tables' => $topTables,
                'disks' => $this->disks((string) ($variables['datadir'] ?? '')),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function longQueries(int $minSeconds = self::LONG_QUERY_SECONDS): array
    {
        try {
            $rows = DB::select(
                'SELECT id, user, host, db, command, time, state, LEFT(info, 400) AS info FROM information_schema.processlist '
                . 'WHERE command NOT IN (\'Sleep\', \'Daemon\', \'Binlog Dump\') AND time >= ? AND id <> CONNECTION_ID() ORDER BY time DESC LIMIT 20',
                [$minSeconds]
            );
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn ($row): array => [
            'id' => (int) $row->id,
            'user' => (string) $row->user,
            'db' => (string) ($row->db ?? ''),
            'command' => (string) $row->command,
            'seconds' => (int) $row->time,
            'state' => (string) ($row->state ?? ''),
            // Literals may carry customer data: show the statement shape only.
            'sql' => mb_strimwidth((string) preg_replace(["/'(?:[^'\\\\]|\\\\.)*'/", '/\b\d{6,}\b/'], ["'…'", '#'], (string) ($row->info ?? '')), 0, 260, '…'),
        ], $rows);
    }

    /**
     * @return array<int, array{label: string, path: string, free_bytes: int|null, total_bytes: int|null}>
     */
    private function disks(string $dataDir): array
    {
        $paths = array_filter([
            'Data MySQL' => $dataDir,
            'Storage aplikasi' => storage_path(),
        ]);

        $seen = [];
        $disks = [];
        foreach ($paths as $label => $path) {
            $root = PHP_OS_FAMILY === 'Windows' ? strtoupper(substr($path, 0, 3)) : $path;
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            if (isset($seen[$root]) && PHP_OS_FAMILY === 'Windows') {
                $disks[$seen[$root]]['label'] .= ' + ' . $label;
                continue;
            }
            $seen[$root] = count($disks);
            $disks[] = [
                'label' => $label,
                'path' => $root,
                'free_bytes' => $free === false ? null : (int) $free,
                'total_bytes' => $total === false ? null : (int) $total,
            ];
        }

        return $disks;
    }

    /**
     * @return array<string, string>
     */
    private function keyValue(string $sql): array
    {
        $values = [];
        foreach (DB::select($sql) as $row) {
            $row = (array) $row;
            $values[(string) ($row['Variable_name'] ?? '')] = (string) ($row['Value'] ?? '');
        }

        return $values;
    }
}
