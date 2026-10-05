<?php

namespace App\Console\Commands;

use App\Services\Import\QueueWorkerControlService;
use Illuminate\Console\Command;

class QueueWorkerWatchdog extends Command
{
    protected $signature = 'queue:watchdog {--force : Bypass recovery backoff for this check}';

    protected $description = 'Verify the persistent queue monitor and recover it with adaptive backoff';

    public function handle(QueueWorkerControlService $workerControl): int
    {
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
