<?php

namespace App\Services\Import;

use App\Services\Import\Strategies\DailyLoanImportStrategy;
use App\Services\Import\Strategies\ConfiguredExcelImportStrategy;
use App\Services\Import\Strategies\CognosPhImportStrategy;
use App\Services\Import\Strategies\CognosRecoveryImportStrategy;
use App\Services\Import\Strategies\DlyKapResegmentasiImportStrategy;
use App\Services\Import\Strategies\Gi405RecDhImportStrategy;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\HourlyDpkImportStrategy;
use App\Services\Import\Strategies\ImportStrategyInterface;
use App\Services\Import\Strategies\L1133ImportStrategy;
use App\Services\Import\Strategies\Lw321NpdImportStrategy;
use App\Services\Import\Strategies\Lw321NpddImportStrategy;
use App\Services\Import\Strategies\Lw321PnImportStrategy;
use App\Services\Import\Strategies\Lw325PhImportStrategy;
use App\Services\Import\Strategies\PerformancePisImportStrategy;
use App\Services\Import\Strategies\SimpananMultiPnImportStrategy;
use App\Services\Import\Strategies\SsaPinjamanImportStrategy;
use App\Services\Import\Strategies\SsaSimpananImportStrategy;

class ImportStrategyFactory
{
    /**
     * @return array<int, ImportStrategyInterface>
     */
    public function all(): array
    {
        return [
            app(DailyLoanImportStrategy::class),
            app(SimpananMultiPnImportStrategy::class),
            app(Gi405RecDhImportStrategy::class),
            app(SsaPinjamanImportStrategy::class),
            app(SsaSimpananImportStrategy::class),
            app(HourlyDpkImportStrategy::class),
            app(L1133ImportStrategy::class),
            app(Lw321PnImportStrategy::class),
            app(Lw321NpdImportStrategy::class),
            app(Lw321NpddImportStrategy::class),
            app(Lw325PhImportStrategy::class),
            app(DlyKapResegmentasiImportStrategy::class),
            app(CognosPhImportStrategy::class),
            app(CognosRecoveryImportStrategy::class),
            app(PerformancePisImportStrategy::class),
            app(ConfiguredExcelImportStrategy::class),
            app(GenericCsvImportStrategy::class),
        ];
    }

    public function resolve(?object $report, ?string $tableName = null): ImportStrategyInterface
    {
        $generic = null;

        foreach ($this->all() as $strategy) {
            if ($strategy instanceof GenericCsvImportStrategy) {
                $generic = $strategy;
                continue;
            }

            if ($strategy->supports($report, $tableName)) {
                return $strategy;
            }
        }

        $generic ??= app(GenericCsvImportStrategy::class);
        if ($generic->supports($report, $tableName)) {
            return $generic;
        }

        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? 'unknown')));
        throw new \RuntimeException("Import strategy khusus untuk tabel `{$table}` tidak ditemukan. Fallback generic ditolak.");
    }
}
