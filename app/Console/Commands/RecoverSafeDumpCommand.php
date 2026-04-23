<?php

namespace App\Console\Commands;

use App\Services\SafeSqlDumpRecoveryService;
use Illuminate\Console\Command;
use RuntimeException;

class RecoverSafeDumpCommand extends Command
{
    protected $signature = 'db:recover-safe-dump {dump_path : Absolute path to the SQL dump file}';

    protected $description = 'Safely recover a partially lost database from a full SQL dump without dropping live tables';

    public function handle(SafeSqlDumpRecoveryService $service): int
    {
        $dumpPath = (string) $this->argument('dump_path');
        $dumpPath = str_replace(['"', "'"], '', trim($dumpPath));

        if ($dumpPath === '') {
            $this->error('Path dump wajib diisi.');
            return 1;
        }

        if (!is_file($dumpPath)) {
            $this->error("File dump tidak ditemukan: {$dumpPath}");
            return 1;
        }

        $this->info('Memulai restore aman dari dump...');
        $this->line("Dump: {$dumpPath}");

        try {
            $summary = $service->restore($dumpPath, function (array $progress): void {
                $this->output->write("\rProgress: " . str_pad((string) $progress['progress_percent'], 3, ' ', STR_PAD_LEFT) . "%");
            });

            $this->newLine(2);
            $this->info('Restore selesai.');
            $this->line('Statements processed: ' . $summary['statements_processed']);
            $this->line('Statements executed: ' . $summary['statements_executed']);
            $this->line('CREATE TABLE executed: ' . $summary['tables_created']);
            $this->line('INSERT statements executed: ' . $summary['insert_statements']);

            return 0;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $this->error('Restore gagal: ' . $e->getMessage());
            return 1;
        }
    }
}
