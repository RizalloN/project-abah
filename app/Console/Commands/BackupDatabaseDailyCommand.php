<?php

namespace App\Console\Commands;

use App\Exceptions\DatabaseBackupAlreadyRunningException;
use App\Services\DailyDatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDatabaseDailyCommand extends Command
{
    protected $signature = 'database:backup-daily
        {--dry-run : Validasi konfigurasi dan ruang disk tanpa membuat backup}
        {--if-missing : Lewati bila folder backup hari ini sudah ada (catch-up setelah server mati)}';

    protected $description = 'Create one verified compressed database backup per day and retain only the latest day';

    public function handle(DailyDatabaseBackupService $backupService): int
    {
        set_time_limit(0);

        if ($this->option('if-missing') && ! $this->option('dry-run')) {
            try {
                if ($backupService->hasBackupForToday()) {
                    $this->info('Backup database hari ini sudah ada; catch-up dilewati.');

                    return self::SUCCESS;
                }
            } catch (Throwable) {
                // Configuration problems are reported by the full run below.
            }
        }

        try {
            $result = $backupService->backup(null, (bool) $this->option('dry-run'));
        } catch (DatabaseBackupAlreadyRunningException $exception) {
            // The Windows task and the Laravel schedule can both start at 00:00;
            // the run that finds the lock taken is a duplicate, not a failure.
            Log::info('Backup database harian dilewati karena proses lain sedang berjalan.');
            $this->warn($exception->getMessage().' Proses ini dilewati.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Backup database harian gagal.', [
                'exception' => $exception,
            ]);
            $this->error('Backup database harian gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $status = (string) ($result['status'] ?? '');
        if ($status === 'completed') {
            $this->info('Backup database harian selesai dan lolos verifikasi.');
        } elseif ($status === 'dry-run') {
            $this->info('Dry-run backup database harian berhasil.');
        } elseif ($status === 'disabled') {
            $this->warn('Backup database harian dinonaktifkan.');
        } else {
            $this->info('Backup database hari ini tersedia dan lolos verifikasi ulang; dump ulang dilewati.');
        }

        if (($result['directory'] ?? '') !== '') {
            $this->line('Folder: '.$result['directory']);
        }
        if (($result['backup_file'] ?? null) !== null) {
            $this->line('File: '.$result['backup_file']);
        }
        if ((int) ($result['compressed_bytes'] ?? 0) > 0) {
            $this->line('Ukuran gzip: '.$this->formatBytes((int) $result['compressed_bytes']));
        }

        $stats = (array) ($result['stats'] ?? []);
        if (isset($stats['parts_total'])) {
            $this->line(sprintf(
                'Part: %d total, %d di-dump, %d dipakai ulang dari backup sebelumnya (%s SQL di-dump, %d detik).',
                (int) $stats['parts_total'],
                (int) ($stats['parts_dumped'] ?? 0),
                (int) ($stats['parts_reused'] ?? 0),
                $this->formatBytes((int) ($stats['dumped_uncompressed_bytes'] ?? 0)),
                (int) ($stats['duration_seconds'] ?? 0)
            ));
        }

        foreach ((array) ($result['deleted_backups'] ?? []) as $deleted) {
            $this->line('Retensi menghapus: '.$deleted);
        }

        $warnings = (array) ($result['warnings'] ?? []);
        foreach ($warnings as $warning) {
            $this->warn((string) $warning);
        }

        Log::info('Backup database harian diproses.', [
            'status' => $status,
            'directory' => $result['directory'] ?? null,
            'compressed_bytes' => $result['compressed_bytes'] ?? 0,
            'uncompressed_bytes' => $result['uncompressed_bytes'] ?? 0,
            'deleted_backups' => $result['deleted_backups'] ?? [],
            'stats' => $stats,
            'warnings' => $warnings,
        ]);

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $value = max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 2, ',', '.').' '.$units[$unit];
    }
}
