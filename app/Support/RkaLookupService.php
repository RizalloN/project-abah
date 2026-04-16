<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RkaLookupService
{
    private array $loadedRowsCache = [];

    private const MONTH_COLUMN_MAP = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dec',
    ];

    private const MONTH_LABEL_MAP = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AUG',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DEC',
    ];

    public function resolveMonthColumn(Carbon|string|null $date): string
    {
        $resolvedDate = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

        return self::MONTH_COLUMN_MAP[(int) $resolvedDate->format('n')] ?? 'jan';
    }

    public function resolveMonthLabel(Carbon|string|null $date): string
    {
        $resolvedDate = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

        return self::MONTH_LABEL_MAP[(int) $resolvedDate->format('n')] ?? 'JAN';
    }

    public function aggregateByGroup(
        array $definitions,
        string $monthColumn,
        array $kancas = [],
        array $units = [],
        string $groupBy = 'kanca',
        ?int $year = null
    ): array {
        $monthColumn = strtolower(trim($monthColumn));
        $normalizedKancas = $this->normalizeLookupValues($kancas);
        $normalizedUnits = $this->normalizeLookupValues($units);
        $rows = $this->loadRows($normalizedKancas, [$monthColumn], $year);

        $groups = [];
        foreach ($definitions as $definitionKey => $definition) {
            $groups[$definitionKey] = [];
        }

        foreach ($rows as $row) {
            if (!empty($normalizedUnits) && !in_array($row['uker_key'], $normalizedUnits, true)) {
                continue;
            }

            $groupKey = $groupBy === 'uker' ? $row['uker_key'] : $row['kanca_key'];
            if ($groupKey === '') {
                continue;
            }

            foreach ($definitions as $definitionKey => $definition) {
                if (!$this->matchesDefinition($row, $definition)) {
                    continue;
                }

                $groups[$definitionKey][$groupKey] = round(
                    ($groups[$definitionKey][$groupKey] ?? 0) + (float) ($row['months'][$monthColumn] ?? 0),
                    2
                );
            }
        }

        return $groups;
    }

    public function aggregateForScope(
        array $definitions,
        string $monthColumn,
        ?string $kanca = null,
        ?string $unit = null,
        ?int $year = null
    ): array {
        $monthColumn = strtolower(trim($monthColumn));
        $normalizedKanca = $this->normalizeLookupValue($kanca);
        $normalizedUnit = $this->normalizeLookupValue($unit);
        $rows = $this->loadRows(
            $normalizedKanca !== null ? [$normalizedKanca] : [],
            [$monthColumn],
            $year
        );

        $result = [];
        foreach (array_keys($definitions) as $definitionKey) {
            $result[$definitionKey] = 0.0;
        }

        foreach ($rows as $row) {
            if ($normalizedUnit !== null && $row['uker_key'] !== $normalizedUnit) {
                continue;
            }

            foreach ($definitions as $definitionKey => $definition) {
                if (!$this->matchesDefinition($row, $definition)) {
                    continue;
                }

                $result[$definitionKey] = round(
                    (float) ($result[$definitionKey] ?? 0) + (float) ($row['months'][$monthColumn] ?? 0),
                    2
                );
            }
        }

        return $result;
    }

    private function loadRows(array $kancas, array $monthColumns, ?int $year = null): Collection
    {
        $normalizedKancas = $this->normalizeLookupValues($kancas);
        $normalizedMonthColumns = collect($monthColumns)
            ->map(fn ($column) => strtolower(trim((string) $column)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedMonthColumns)) {
            $normalizedMonthColumns = ['jan'];
        }

        $cacheKey = md5(json_encode([
            'kancas' => $normalizedKancas,
            'months' => $normalizedMonthColumns,
            'year' => $year,
        ]));

        if (isset($this->loadedRowsCache[$cacheKey])) {
            return $this->loadedRowsCache[$cacheKey];
        }

        return $this->loadedRowsCache[$cacheKey] = DB::table('rka')
            ->select(array_merge(['kanca', 'desc_uker', 'mata_anggaran'], $normalizedMonthColumns))
            ->when(!empty($normalizedKancas), function ($query) use ($normalizedKancas) {
                $query->whereIn(DB::raw('UPPER(TRIM(`kanca`))'), $normalizedKancas);
            })
            ->when($year !== null, function ($query) use ($year) {
                $query->whereYear('created_at', $year);
            })
            ->get()
            ->map(function ($row) use ($normalizedMonthColumns) {
                $parsedUker = $this->extractUkerName((string) ($row->desc_uker ?? ''));
                $months = [];

                foreach ($normalizedMonthColumns as $monthColumn) {
                    $months[$monthColumn] = (float) ($row->{$monthColumn} ?? 0);
                }

                return [
                    'kanca_key' => $this->normalizeLookupValue($row->kanca) ?? '',
                    'uker_key' => $parsedUker,
                    'mata_anggaran_key' => $this->normalizeLookupValue($row->mata_anggaran) ?? '',
                    'months' => $months,
                ];
            });
    }

    public function availableYears(): array
    {
        return DB::table('rka')
            ->whereNotNull('created_at')
            ->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values()
            ->all();
    }

    private function extractUkerName(string $value): string
    {
        $parts = explode('-', trim($value), 2);

        return $this->normalizeLookupValue($parts[1] ?? $parts[0] ?? '') ?? '';
    }

    private function matchesDefinition(array $row, array $definition): bool
    {
        $mataAnggarans = $this->normalizeLookupValues((array) ($definition['mata_anggaran'] ?? []));
        if (!empty($mataAnggarans) && !in_array($row['mata_anggaran_key'], $mataAnggarans, true)) {
            return false;
        }

        $ukerContainsAny = $this->normalizeLookupValues((array) ($definition['uker_contains_any'] ?? []));
        if (!empty($ukerContainsAny)) {
            $matchesAny = false;

            foreach ($ukerContainsAny as $needle) {
                if ($needle !== '' && str_contains($row['uker_key'], $needle)) {
                    $matchesAny = true;
                    break;
                }
            }

            if (!$matchesAny) {
                return false;
            }
        }

        $ukerNotContainsAny = $this->normalizeLookupValues((array) ($definition['uker_not_contains_any'] ?? []));
        foreach ($ukerNotContainsAny as $needle) {
            if ($needle !== '' && str_contains($row['uker_key'], $needle)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeLookupValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $this->normalizeLookupValue($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeLookupValue($value): ?string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized !== '' ? $normalized : null;
    }
}
