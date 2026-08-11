<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class SsaSimpananBusinessSegmentBackfillService
{
    private const CATEGORIES = ['Consumer', 'Micro', 'Ritel', 'SMC', 'Wealth', 'Wholesale'];

    private const AGGREGATE_ROW_LIMIT = 600;

    /**
     * @param  array<int, string>  $referencePaths
     * @return array<string, mixed>
     */
    public function run(array $referencePaths, bool $apply = false): array
    {
        $references = $this->loadReferences($referencePaths);
        $latestPeriod = array_key_first($references);
        $latestTemplate = $references[$latestPeriod];
        $periods = DB::table('ssa_simpanan')
            ->selectRaw('DATE(`Month_Day_Year_of_Posisi`) AS periode, COUNT(*) AS total')
            ->groupByRaw('DATE(`Month_Day_Year_of_Posisi`)')
            ->orderByDesc('periode')
            ->get();

        if ($periods->isEmpty()) {
            throw new RuntimeException('Tabel ssa_simpanan tidak memiliki data untuk dipetakan.');
        }

        $tempTable = 'tmp_ssa_simpanan_business_segment';
        if ($apply) {
            DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$tempTable}`");
            DB::statement(
                "CREATE TEMPORARY TABLE `{$tempTable}` ("
                .'`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,'
                .'`category` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL'
                .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $stats = [
            'reference_periods' => array_keys($references),
            'periods' => $periods->count(),
            'rows' => 0,
            'reference_exact' => 0,
            'template_exact' => 0,
            'temporal_reconciled' => 0,
            'aggregate_total' => 0,
            'fallback_segmentasi' => 0,
            'fallback_total' => 0,
            'ambiguous_reconciliations' => 0,
            'changed_rows' => 0,
        ];
        $lastBalances = $this->seedBalances($latestTemplate);
        $pending = [];

        try {
            foreach ($periods as $periodInfo) {
                $period = (string) $periodInfo->periode;
                $rows = DB::table('ssa_simpanan')
                    ->whereDate('Month_Day_Year_of_Posisi', $period)
                    ->orderBy('id')
                    ->get([
                        'id', 'nama_cabang', 'nama_uker', 'produk', 'segmentasi',
                        'saldo', 'segmen_kategorisasi_bisnis',
                    ]);
                $stats['rows'] += $rows->count();

                $groups = [];
                foreach ($rows as $row) {
                    $groups[$this->dimensionKey($row->nama_cabang, $row->nama_uker, $row->produk, $row->segmentasi)][] = $row;
                }

                $isAggregatePeriod = $rows->count() < self::AGGREGATE_ROW_LIMIT;
                foreach ($groups as $key => $groupRows) {
                    $assignments = $isAggregatePeriod
                        ? array_fill(0, count($groupRows), 'Total')
                        : $this->assignDetailedGroup(
                            $period,
                            $key,
                            $groupRows,
                            $references,
                            $latestTemplate,
                            $lastBalances,
                            $stats
                        );

                    if ($isAggregatePeriod) {
                        $stats['aggregate_total'] += count($groupRows);
                    }

                    foreach ($groupRows as $index => $row) {
                        $category = $assignments[$index] ?? 'Total';
                        if ((string) $row->segmen_kategorisasi_bisnis !== $category) {
                            $stats['changed_rows']++;
                        }
                        if ($apply) {
                            $pending[] = ['id' => (int) $row->id, 'category' => $category];
                            if (count($pending) >= 1000) {
                                DB::table($tempTable)->insert($pending);
                                $pending = [];
                            }
                        }
                    }
                }
            }

            if ($apply) {
                if ($pending !== []) {
                    DB::table($tempTable)->insert($pending);
                }

                DB::transaction(function () use ($tempTable): void {
                    DB::affectingStatement(
                        'UPDATE `ssa_simpanan` AS s '
                        ."INNER JOIN `{$tempTable}` AS t ON t.id = s.id "
                        .'SET s.segmen_kategorisasi_bisnis = t.category '
                        .'WHERE s.segmen_kategorisasi_bisnis IS NULL '
                        ."OR TRIM(s.segmen_kategorisasi_bisnis) = '' "
                        .'OR s.segmen_kategorisasi_bisnis <> t.category'
                    );
                }, 3);

                $stats['remaining_blank'] = DB::table('ssa_simpanan')
                    ->whereNull('segmen_kategorisasi_bisnis')
                    ->orWhereRaw("TRIM(segmen_kategorisasi_bisnis) = ''")
                    ->count();
                $stats['category_counts'] = DB::table('ssa_simpanan')
                    ->selectRaw('segmen_kategorisasi_bisnis AS category, COUNT(*) AS total')
                    ->groupBy('segmen_kategorisasi_bisnis')
                    ->orderBy('segmen_kategorisasi_bisnis')
                    ->pluck('total', 'category')
                    ->all();
            }
        } finally {
            if ($apply) {
                DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$tempTable}`");
            }
        }

        return $stats;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<string, array<string, array<int, array{category: string, balance: float}>>>
     */
    private function loadReferences(array $paths): array
    {
        if (count($paths) < 2) {
            throw new RuntimeException('Minimal dua file referensi SSA Simpanan berurutan diperlukan.');
        }

        $references = [];
        foreach ($paths as $path) {
            if (! is_file($path)) {
                throw new RuntimeException("File referensi tidak ditemukan: {$path}");
            }

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [];
            for ($column = 1; $column <= 7; $column++) {
                $headers[] = $this->normalizeHeader($sheet->getCell([$column, 1])->getValue());
            }
            $expected = [
                'month_day_year_of_posisi', 'nama_cabang', 'nama_uker', 'produk',
                'segmentasi', 'segmen_kategorisasi_bisnis', 'saldo',
            ];
            if ($headers !== $expected) {
                $spreadsheet->disconnectWorksheets();
                throw new RuntimeException('Urutan kolom file referensi SSA Simpanan tidak sesuai kontrak sumber terbaru.');
            }

            $period = null;
            $template = [];
            for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                $values = [];
                for ($column = 1; $column <= 7; $column++) {
                    $values[] = $sheet->getCell([$column, $row])->getValue();
                }
                if (trim(implode('', array_map(static fn ($value): string => (string) $value, $values))) === '') {
                    continue;
                }

                $rowPeriod = $this->parsePeriod($values[0]);
                $period ??= $rowPeriod;
                if ($period !== $rowPeriod) {
                    throw new RuntimeException("File {$path} memuat lebih dari satu periode.");
                }

                $category = trim((string) $values[5]);
                if (! in_array($category, self::CATEGORIES, true)) {
                    throw new RuntimeException("Kategori bisnis tidak dikenal pada baris {$row}: {$category}");
                }

                $key = $this->dimensionKey($values[1], $values[2], $values[3], $values[4]);
                $template[$key][] = ['category' => $category, 'balance' => (float) $values[6]];
            }
            $spreadsheet->disconnectWorksheets();

            if ($period === null || array_sum(array_map('count', $template)) === 0) {
                throw new RuntimeException("File referensi kosong: {$path}");
            }
            $references[$period] = $template;
        }

        krsort($references);
        $templates = array_values($references);
        $expectedSignature = $this->templateSignature($templates[0]);
        foreach (array_slice($templates, 1) as $template) {
            if ($this->templateSignature($template) !== $expectedSignature) {
                throw new RuntimeException('Dua file referensi tidak memiliki susunan dimensi dan kategori yang sama.');
            }
        }

        return $references;
    }

    /**
     * @param  array<string, array<int, array{category: string, balance: float}>>  $template
     * @return array<string, array<string, float>>
     */
    private function seedBalances(array $template): array
    {
        $balances = [];
        foreach ($template as $key => $entries) {
            foreach ($entries as $entry) {
                $balances[$key][$entry['category']] = $entry['balance'];
            }
        }

        return $balances;
    }

    /**
     * @param  array<int, object>  $rows
     * @param  array<string, array<string, array<int, array{category: string, balance: float}>>>  $references
     * @param  array<string, array<int, array{category: string, balance: float}>>  $latestTemplate
     * @param  array<string, array<string, float>>  $lastBalances
     * @param  array<string, mixed>  $stats
     * @return array<int, string>
     */
    private function assignDetailedGroup(
        string $period,
        string $key,
        array $rows,
        array $references,
        array $latestTemplate,
        array &$lastBalances,
        array &$stats
    ): array {
        if (isset($references[$period][$key])) {
            $entries = $references[$period][$key];
            if (count($entries) !== count($rows)) {
                throw new RuntimeException("Jumlah baris database periode {$period} tidak cocok dengan file referensi.");
            }
            $categories = [];
            foreach ($rows as $index => $row) {
                if (! $this->sameBalance((float) $row->saldo, $entries[$index]['balance'])) {
                    throw new RuntimeException("Saldo database periode {$period} tidak cocok dengan file referensi pada ID {$row->id}.");
                }
                $categories[] = $entries[$index]['category'];
            }
            $stats['reference_exact'] += count($rows);
            $this->rememberBalances($key, $rows, $categories, $lastBalances);

            return $categories;
        }

        $templateEntries = $latestTemplate[$key] ?? [];
        if (count($templateEntries) === count($rows) && $templateEntries !== []) {
            $categories = array_column($templateEntries, 'category');
            $stats['template_exact'] += count($rows);
            $this->rememberBalances($key, $rows, $categories, $lastBalances);

            return $categories;
        }

        if (count($rows) <= count(self::CATEGORIES) && ($templateEntries !== [] || isset($lastBalances[$key]))) {
            [$categories, $ambiguous] = $this->bestTemporalCategories($key, $rows, $templateEntries, $lastBalances);
            $stats['temporal_reconciled'] += count($rows);
            if ($ambiguous) {
                $stats['ambiguous_reconciliations']++;
            }
            $this->rememberBalances($key, $rows, $categories, $lastBalances);

            return $categories;
        }

        if (count($rows) === 1) {
            $segmentasi = $this->canonicalCategory((string) $rows[0]->segmentasi);
            if ($segmentasi !== null) {
                $stats['fallback_segmentasi']++;
                $this->rememberBalances($key, $rows, [$segmentasi], $lastBalances);

                return [$segmentasi];
            }
        }

        $stats['fallback_total'] += count($rows);

        return array_fill(0, count($rows), 'Total');
    }

    /**
     * @param  array<int, object>  $rows
     * @param  array<int, array{category: string, balance: float}>  $templateEntries
     * @param  array<string, array<string, float>>  $lastBalances
     * @return array{0: array<int, string>, 1: bool}
     */
    private function bestTemporalCategories(string $key, array $rows, array $templateEntries, array $lastBalances): array
    {
        $templateBalances = [];
        $templateCategories = [];
        foreach ($templateEntries as $entry) {
            $templateBalances[$entry['category']] = $entry['balance'];
            $templateCategories[$entry['category']] = true;
        }

        $scored = [];
        foreach ($this->combinations(self::CATEGORIES, count($rows)) as $categories) {
            $score = 0.0;
            foreach ($categories as $index => $category) {
                $anchor = $lastBalances[$key][$category] ?? $templateBalances[$category] ?? null;
                $score += $anchor === null
                    ? 2.5
                    : abs(log10(abs((float) $rows[$index]->saldo) + 1) - log10(abs($anchor) + 1));
                if (! isset($templateCategories[$category])) {
                    $score += 0.2;
                }
            }
            $scored[] = ['categories' => $categories, 'score' => $score];
        }
        usort($scored, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        return [$scored[0]['categories'], isset($scored[1]) && abs($scored[1]['score'] - $scored[0]['score']) < 0.05];
    }

    /** @return array<int, array<int, string>> */
    private function combinations(array $values, int $length, int $offset = 0, array $prefix = []): array
    {
        if ($length === 0) {
            return [$prefix];
        }

        $result = [];
        for ($index = $offset; $index <= count($values) - $length; $index++) {
            $next = $prefix;
            $next[] = $values[$index];
            array_push($result, ...$this->combinations($values, $length - 1, $index + 1, $next));
        }

        return $result;
    }

    private function rememberBalances(string $key, array $rows, array $categories, array &$lastBalances): void
    {
        foreach ($rows as $index => $row) {
            $lastBalances[$key][$categories[$index]] = (float) $row->saldo;
        }
    }

    private function templateSignature(array $template): string
    {
        $signature = [];
        foreach ($template as $key => $entries) {
            $signature[$key] = array_column($entries, 'category');
        }
        ksort($signature);

        return hash('sha256', json_encode($signature, JSON_UNESCAPED_UNICODE));
    }

    private function dimensionKey(mixed $cabang, mixed $uker, mixed $produk, mixed $segmentasi): string
    {
        return implode('|', array_map([$this, 'normalizeDimension'], [$cabang, $uker, $produk, $segmentasi]));
    }

    private function normalizeDimension(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?? '';
    }

    private function normalizeHeader(mixed $value): string
    {
        return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string) $value)) ?? ''), '_');
    }

    private function parsePeriod(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
        }

        $text = str_ireplace(
            ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            trim((string) $value)
        );

        return Carbon::parse($text)->format('Y-m-d');
    }

    private function canonicalCategory(string $value): ?string
    {
        foreach (self::CATEGORIES as $category) {
            if (strcasecmp(trim($value), $category) === 0) {
                return $category;
            }
        }

        return null;
    }

    private function sameBalance(float $left, float $right): bool
    {
        return abs($left - $right) < 0.005;
    }
}
