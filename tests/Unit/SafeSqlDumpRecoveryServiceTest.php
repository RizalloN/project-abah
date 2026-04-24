<?php

namespace Tests\Unit;

use App\Services\SafeSqlDumpRecoveryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SafeSqlDumpRecoveryServiceTest extends TestCase
{
    public function test_restore_rewrites_destructive_statements_into_safe_statements(): void
    {
        $dumpPath = storage_path('framework/testing/safe_dump_recovery_sample.sql');
        if (!is_dir(dirname($dumpPath))) {
            @mkdir(dirname($dumpPath), 0777, true);
        }

        file_put_contents($dumpPath, implode(PHP_EOL, [
            'DROP TABLE IF EXISTS `foo`;',
            'CREATE TABLE `foo` (',
            '  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,',
            '  `name` varchar(255) DEFAULT NULL,',
            '  PRIMARY KEY (`id`)',
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
            'INSERT INTO `foo` VALUES (1,\'alpha\');',
            'LOCK TABLES `foo` WRITE;',
            'UNLOCK TABLES;',
            '',
        ]));

        DB::shouldReceive('statement')->once()->with('SET FOREIGN_KEY_CHECKS=0');
        DB::shouldReceive('statement')->once()->with('SET UNIQUE_CHECKS=0');
        DB::shouldReceive('unprepared')->once()->with(\Mockery::on(static function (string $sql): bool {
            return str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS `foo`');
        }));
        DB::shouldReceive('unprepared')->once()->with('INSERT IGNORE INTO `foo` VALUES (1,\'alpha\')');
        DB::shouldReceive('statement')->once()->with('SET UNIQUE_CHECKS=1');
        DB::shouldReceive('statement')->once()->with('SET FOREIGN_KEY_CHECKS=1');

        try {
            $service = new SafeSqlDumpRecoveryService();
            $summary = $service->restore($dumpPath);

            $this->assertSame(5, $summary['statements_processed']);
            $this->assertSame(2, $summary['statements_executed']);
            $this->assertSame(1, $summary['tables_created']);
            $this->assertSame(1, $summary['insert_statements']);
        } finally {
            @unlink($dumpPath);
        }
    }
}
