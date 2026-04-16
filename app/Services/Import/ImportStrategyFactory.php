<?php

namespace App\Services\Import;

use App\Services\Import\Strategies\DailyLoanImportStrategy;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\ImportStrategyInterface;
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
            app(SsaPinjamanImportStrategy::class),
            app(SsaSimpananImportStrategy::class),
            app(SsaPinjamanImportStrategy::class),
            app(Lw325PhImportStrategy::class),
            app(PerformancePisImportStrategy::class),
            app(GenericCsvImportStrategy::class),
        ];
    }

    public function resolve(?object $report, ?string $tableName = null): ImportStrategyInterface
    {
        foreach ($this->all() as $strategy) {
            if ($strategy->supports($report, $tableName)) {
                return $strategy;
            }
        }

        return app(GenericCsvImportStrategy::class);
    }
}
