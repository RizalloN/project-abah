<?php

namespace App\Services\Import;

use App\Support\StrictDateParser;

class ImportPeriodGuardService
{
    /** @var array<string, array{column: string, type: 'date'|'year'}> */
    private const REPORT_PERIODS = [
        'daily_loan_dinamis' => ['column' => 'periode', 'type' => 'date'],
        'lw321pn' => ['column' => 'periode', 'type' => 'date'],
        'lw325_ph' => ['column' => 'periode', 'type' => 'date'],
        'simpanan_multipn' => ['column' => 'posisi', 'type' => 'date'],
        'hourly_dpk' => ['column' => 'posisi', 'type' => 'date'],
        'ssa_simpanan' => ['column' => 'Month_Day_Year_of_Posisi', 'type' => 'date'],
        'ssa_pinjaman' => ['column' => 'month_day_year_of_periode', 'type' => 'date'],
        'ssa_almafacts' => ['column' => 'month_day_year_of_posisi', 'type' => 'date'],
        'gi405_recovery' => ['column' => 'periode', 'type' => 'date'],
        'cognos_ph' => ['column' => 'periode', 'type' => 'date'],
        'cognos_recovery' => ['column' => 'periode', 'type' => 'date'],
        'dly_kap_resegmentasi' => ['column' => 'periode', 'type' => 'date'],
        'l1133' => ['column' => 'periode', 'type' => 'date'],
        'jumlah_merchant_detail' => ['column' => 'POSISI', 'type' => 'date'],
        'jumlah_merchant_qris_detail' => ['column' => 'POSISI', 'type' => 'date'],
        'sv_merchant' => ['column' => 'POSISI', 'type' => 'date'],
        'user_brimo_rpt_v2' => ['column' => 'posisi', 'type' => 'date'],
        'user_brimo_fin' => ['column' => 'posisi', 'type' => 'date'],
        'brimo_fin' => ['column' => 'posisi', 'type' => 'date'],
        'brimo_fin_all' => ['column' => 'posisi', 'type' => 'date'],
        'performance_pis_per_produk' => ['column' => 'posisi', 'type' => 'date'],
        'input_rekanan' => ['column' => 'periode', 'type' => 'date'],
        'bod_boc' => ['column' => 'periode', 'type' => 'date'],
        'cras' => ['column' => 'cras_periode', 'type' => 'date'],
        'casa_brilink_web' => ['column' => 'periode', 'type' => 'date'],
        'casa_brilink_edc' => ['column' => 'periode', 'type' => 'date'],
        'ibbisniz_corp' => ['column' => 'periode', 'type' => 'date'],
        'usak_ibbiz_uker' => ['column' => 'periode', 'type' => 'date'],
        'rka' => ['column' => 'tahun', 'type' => 'year'],
    ];

    /** @var list<string> */
    private const EXEMPT_TABLES = [
        'brihc',
        'wilayah_mbm',
        'business_cluster',
        'brihc_pemasar',
        'brilink_web_laporan_summary_transaksi_brilink_web',
    ];

    /** @return array{column: string, type: 'date'|'year'}|null */
    public function policyFor(string $tableName): ?array
    {
        $tableName = strtolower(trim($tableName));
        if ($tableName === '' || in_array($tableName, self::EXEMPT_TABLES, true)) {
            return null;
        }

        return self::REPORT_PERIODS[$tableName] ?? null;
    }

    public function assertMappedColumns(string $tableName, array $columns): void
    {
        $policy = $this->policyFor($tableName);
        if ($policy === null) {
            return;
        }

        if ($this->resolveColumnIndex($columns, $policy['column']) === null) {
            throw new \RuntimeException(sprintf(
                'Import `%s` dibatalkan: kolom periode wajib `%s` tidak dipetakan. Pilih kolom tersebut lalu ulangi import.',
                $tableName,
                $policy['column']
            ));
        }
    }

    public function assertRow(string $tableName, array $row, ?int $rowNumber = null): void
    {
        $policy = $this->policyFor($tableName);
        if ($policy === null) {
            return;
        }

        $key = $this->resolveRowKey($row, $policy['column']);
        $value = $key !== null ? $row[$key] ?? null : null;
        $displayRow = $rowNumber !== null ? " pada baris {$rowNumber}" : '';

        if ($key === null || $value === null || trim((string) $value) === '' || trim((string) $value) === '\\N') {
            throw new \RuntimeException(sprintf(
                'Import `%s` dibatalkan: nilai periode `%s` kosong%s.',
                $tableName,
                $policy['column'],
                $displayRow
            ));
        }

        $normalizedValue = trim((string) $value);
        $valid = $policy['type'] === 'year'
            ? preg_match('/^(19|20|21)\d{2}$/', $normalizedValue) === 1
            : !$this->isMonthOnlyValue($normalizedValue)
                && StrictDateParser::normalize($normalizedValue) !== null;

        if (!$valid) {
            throw new \RuntimeException(sprintf(
                'Import `%s` dibatalkan: nilai periode `%s` tidak valid%s (%s).',
                $tableName,
                $policy['column'],
                $displayRow,
                trim((string) $value)
            ));
        }
    }

    public function assertCsvRows(string $csvPath, string $tableName, array $columns): void
    {
        $policy = $this->policyFor($tableName);
        if ($policy === null) {
            return;
        }

        $this->assertMappedColumns($tableName, $columns);
        $periodIndex = $this->resolveColumnIndex($columns, $policy['column']);
        if ($periodIndex === null) {
            return;
        }

        $handle = @fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("File CSV tidak dapat dibaca untuk validasi periode: {$csvPath}");
        }

        try {
            $rowNumber = 0;
            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $hasContent = false;
                foreach ($values as $value) {
                    if ($value !== null && trim((string) $value) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) {
                    continue;
                }

                $this->assertRow($tableName, [
                    $policy['column'] => $values[$periodIndex] ?? null,
                ], $rowNumber);
            }
        } finally {
            fclose($handle);
        }
    }

    private function resolveColumnIndex(array $columns, string $expected): ?int
    {
        $expected = strtolower($expected);
        foreach (array_values($columns) as $index => $value) {
            if (strtolower((string) $value) === $expected) {
                return $index;
            }
        }

        return null;
    }

    private function resolveRowKey(array $row, string $expected): int|string|null
    {
        $expected = strtolower($expected);
        foreach (array_keys($row) as $key) {
            if (strtolower((string) $key) === $expected) {
                return $key;
            }
        }

        return null;
    }

    private function isMonthOnlyValue(string $value): bool
    {
        return preg_match('/^\d{4}[-\/]\d{1,2}$/', $value) === 1
            || preg_match('/^(?:[[:alpha:]]+)[\s-]+\d{4}$/u', $value) === 1
            || preg_match('/^\d{4}[\s-]+(?:[[:alpha:]]+)$/u', $value) === 1;
    }
}
