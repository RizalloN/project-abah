<?php

namespace App\Console\Commands;

use App\Jobs\AuditAndHealSnapshotsJob;
use App\Services\Snapshot\SnapshotAuditAndHealService;
use Illuminate\Console\Command;

class AuditSnapshotsCommand extends Command
{
    protected $signature = 'snapshot:audit-heal
                            {--table= : Table name to audit (daily_loan_dinamis, simpanan_multipn, ssa_simpanan)}
                            {--period= : Optional period hint to audit}
                            {--recent=5 : Audit N most recent periods per table (default 5)}
                            {--all : Perform full historical audit across all periods}
                            {--queue : Dispatch audit as a background queue job}
                            {--force : Force audit even if active imports are running}';

    protected $description = 'Audit snapshot data integrity against source tables and auto-heal discrepancies without impacting import speed';

    public function handle(SnapshotAuditAndHealService $service): int
    {
        $table = trim((string) $this->option('table')) ?: null;
        $period = trim((string) $this->option('period')) ?: null;
        $force = (bool) $this->option('force');
        $asQueue = (bool) $this->option('queue');
        $isAll = (bool) $this->option('all');
        $recentLimit = $isAll ? 0 : max(1, (int) ($this->option('recent') ?: 5));

        if ($asQueue) {
            AuditAndHealSnapshotsJob::dispatch($force);
            $this->info('AuditAndHealSnapshotsJob has been dispatched to the snapshots queue.');
            return self::SUCCESS;
        }

        $this->info('Memulai audit integritas snapshot...');

        if ($table !== null) {
            $result = $service->auditAndHealTable($table, $period, $force, $recentLimit);
            $this->outputTableResult($table, $result);
            return self::SUCCESS;
        }

        $result = $service->auditAndHealAll($force, $recentLimit);

        if (($result['status'] ?? '') === 'yielded') {
            $this->warn('Audit ditunda karena terdeteksi aktivitas import/update/delete yang sedang berjalan.');
            return self::SUCCESS;
        }

        $this->info("Audit selesai. Status: {$result['status']}");
        $this->line("Tabel diperiksa: {$result['tables_checked']}");
        $this->line("Perbedaan ditemukan: {$result['discrepancies_found']}");
        $this->line("Rebuild di-dispatch: {$result['rebuilds_dispatched']}");

        foreach ((array) ($result['results'] ?? []) as $tbl => $tblRes) {
            $this->outputTableResult($tbl, $tblRes);
        }

        return self::SUCCESS;
    }

    private function outputTableResult(string $table, array $result): void
    {
        $status = $result['status'] ?? 'unknown';
        $color = match ($status) {
            'clean' => 'green',
            'discrepancies_detected', 'repaired' => 'yellow',
            'yielded' => 'magenta',
            'error' => 'red',
            default => 'cyan',
        };

        $discrepancies = (int) ($result['discrepancies_found'] ?? 0);
        $rebuilds = (int) ($result['rebuilds_dispatched'] ?? 0);

        $this->line("  [<fg={$color}>{$status}</>] <options=bold>{$table}</> | Discrepancies: {$discrepancies} | Rebuilds dispatched: {$rebuilds}");

        if (!empty($result['affected_periods'])) {
            $this->line("     Periode terdampak: " . implode(', ', (array) $result['affected_periods']));
        }
        if (!empty($result['message'])) {
            $this->line("     Pesan: {$result['message']}");
        }
    }
}
