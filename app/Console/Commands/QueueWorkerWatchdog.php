<?php

namespace App\Console\Commands;

use App\Services\Import\QueueWorkerControlService;
use App\Services\Import\SafeWorkerRestartService;
use Illuminate\Console\Command;

class QueueWorkerWatchdog extends Command
{
    protected $signature = 'queue:watchdog {--force : Bypass recovery backoff for this check}';

    protected $description = 'Verify the persistent queue monitor and recover it with adaptive backoff';

    public function handle(QueueWorkerControlService $workerControl, SafeWorkerRestartService $safeRestart): int
    {
        // Runs in a fresh process each minute, so a pending restart (or one
        // for newly deployed code) is executed by current code once idle.
        try {
            $restart = $safeRestart->tick();
            if (($restart['action'] ?? '') === 'restarted') {
                $this->info('Restart aman dijalankan: tidak ada import, snapshot, atau job yang sedang berjalan.');
            } elseif (($restart['action'] ?? '') === 'waiting') {
                $this->line('Restart aman menunggu: '.implode('; ', array_column($restart['busy'] ?? [], 'label')));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        $status = $workerControl->ensureMonitorRunning('scheduled-watchdog', (bool) $this->option('force'));

        if (!($status['enabled'] ?? false)) {
            $this->line('Queue monitor sengaja dinonaktifkan; watchdog tidak mengaktifkannya kembali.');
            return self::SUCCESS;
        }

        if ($status['monitor_active'] ?? false) {
            $this->info('Queue monitor sehat.');
            return self::SUCCESS;
        }

        if (($status['recovery_skipped'] ?? null) === 'backoff') {
            $this->warn('Queue monitor belum aktif; retry ditunda oleh adaptive backoff.');
            return self::SUCCESS;
        }

        if ($status['started'] ?? false) {
            $this->warn('Supervisor monitor sudah dimulai dan sedang menunggu heartbeat.');
            return self::SUCCESS;
        }

        $this->error('Supervisor monitor gagal dimulai; watchdog akan mencoba ulang sesuai backoff.');

        return self::FAILURE;
    }
}
