<?php

namespace App\Services\Reports;

use RuntimeException;

/** Immutable KPI reference data, separate from personnel and productivity sources. */
class KpiWorkbookSnapshotService
{
    private const SHEETS = ['mbm', 'ka-unit', 'mantri', 'mantri-briguna'];

    public function available(string $sheetKey, string $period): bool
    {
        return $period === '2026-09' && in_array($sheetKey, self::SHEETS, true);
    }

    public function read(string $sheetKey, string $period): array
    {
        if (! $this->available($sheetKey, $period)) {
            throw new RuntimeException('Snapshot KPI tidak tersedia untuk periode tersebut.');
        }
        $data = json_decode(file_get_contents(resource_path('data/kpi-'.$sheetKey.'-'.$period.'.json')), true, 512, JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== 1 || ($data['period'] ?? null) !== $period
            || count($data['rows'] ?? []) !== count($data['source_rows'] ?? [])) {
            throw new RuntimeException('Struktur snapshot KPI tidak valid.');
        }

        return $data;
    }
}
