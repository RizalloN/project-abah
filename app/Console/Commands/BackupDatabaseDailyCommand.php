<?php

namespace App\Console\Commands;

use App\Services\DailyDatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDatabaseDailyCommand extends Command
{
    protected $signature = 'database:backup-daily
        {--dry-run : Validasi konfigurasi dan ruang disk tanpa membuat backup}';

    protected $description = 'Create one verified compressed database backup per day and retain only the latest day';

    public function handle(DailyDatabaseBackupService $backupService): int
    {
        set_time_limit(0);

        try {
            $result = $backupService->backup(null, (bool) $this->option('dry-run'));
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
