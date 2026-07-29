<?php

namespace App\Console\Commands;

use App\Services\SsaPinjamanDailyLoanRewriteService;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportDataSyncService;
use App\Support\SnapshotSourceSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RewriteSsaPinjamanYtdReferenceCommand extends Command
{
    protected $signature = 'ssa-pinjaman:rewrite-ytd-reference {--apply : Tulis ulang SSA Pinjaman 31/12/2025 setelah dry-run}';

    protected $description = 'Rewrite SSA Pinjaman 31/12/2025 dari Daily Loan yang sama dan bangun ulang snapshot harian.';

    public function handle(
        SsaPinjamanDailyLoanRewriteService $rewriter,
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): int {
        try {
            $inspection = $rewriter->inspect();
            $this->table(
                ['Sumber', 'Baris', 'Cabang', 'UKER', 'Baki Debet'],
                [
                    [
                        'Daily Loan 31/12/2025',
                        number_format((int) ($inspection['source']['row_count'] ?? 0), 0, ',', '.'),
                        number_format((int) ($inspection['source']['branch_count'] ?? 0), 0, ',', '.'),
                        number_format((int) ($inspection['source']['unit_count'] ?? 0), 0, ',', '.'),
                        number_format((float) ($inspection['source']['baki_debet'] ?? 0), 2, ',', '.'),
                    ],
                    [
                        'SSA saat ini',
                        number_format((int) ($inspection['current']['row_count'] ?? 0), 0, ',', '.'),
                        '-',
                        '-',
                        number_format((float) ($inspection['current']['baki_debet'] ?? 0), 2, ',', '.'),
                    ],
                    [
                        'Proyeksi SSA baru',
                        number_format((int) ($inspection['projection']['row_count'] ?? 0), 0, ',', '.'),
                        '-',
                        '-',
                        number_format((float) ($inspection['projection']['baki_debet'] ?? 0), 2, ',', '.'),
                    ],
                ]
            );

            if (!$this->option('apply')) {
                $this->warn('Dry-run selesai. Tidak ada data yang ditulis. Jalankan ulang dengan --apply untuk eksekusi.');

                return self::SUCCESS;
            }

            $backupPath = $rewriter->backupCurrentPeriod();
            $this->info("Backup SSA lama dibuat: {$backupPath}");

            $result = $rewriter->rewrite($inspection);
            $this->info('SSA Pinjaman 31/12/2025 berhasil ditulis ulang dari Daily Loan.');
            $this->line('Baris: ' . number_format((int) ($result['after']['row_count'] ?? 0), 0, ',', '.'));
            $this->line('Baki debet: ' . number_format((float) ($result['after']['baki_debet'] ?? 0), 2, ',', '.'));

            $snapshotBefore = (int) DB::table('dashboard_harian_snapshots')
                ->where('snapshot_period', SsaPinjamanDailyLoanRewriteService::TARGET_PERIOD)
                ->count();
            $rebuilt = $dashboardHarian->rebuild(SsaPinjamanDailyLoanRewriteService::TARGET_PERIOD, true);
            $snapshotAfter = (int) DB::table('dashboard_harian_snapshots')
                ->where('snapshot_period', SsaPinjamanDailyLoanRewriteService::TARGET_PERIOD)
                ->count();

            if ($snapshotAfter <= 0) {
                throw new \RuntimeException('Snapshot Dashboard Harian 31/12/2025 tidak berhasil dibangun ulang.');
            }

            $sourceMetadata = $sourceSignatures->capture(
                'ssa_pinjaman',
                'month_day_year_of_periode',
                SsaPinjamanDailyLoanRewriteService::TARGET_PERIOD
            );
            if ($sourceMetadata !== null) {
                $sourceSignatures->markBuilt(
                    'ssa_pinjaman',
                    'dashboard_harian_snapshots',
                    SsaPinjamanDailyLoanRewriteService::TARGET_PERIOD,
                    $sourceMetadata,
                    [
                        'period_column' => 'snapshot_period',
                        'rows_before' => $snapshotBefore,
                        'rows_after' => $snapshotAfter,
                        'source' => static::class,
                    ]
                );
            }

            ReportDataSyncService::analyzeTable('ssa_pinjaman');
            ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');

            $this->info('Snapshot Dashboard Harian berhasil dibangun ulang.');
            $this->line('Snapshot sebelum/sesudah: ' . $snapshotBefore . ' / ' . $snapshotAfter);
            $this->line('Hasil rebuild: ' . json_encode($rebuilt, JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Rewrite SSA Pinjaman gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
