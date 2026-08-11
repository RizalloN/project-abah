<?php

namespace App\Services\Rka;

use App\Support\OptimizedRkaLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class BreakdownRkaSyncService
{
    private const EXPECTED_BRANCHES = [
        'MADIUN' => 'KC Madiun',
        'MAGETAN' => 'KC Magetan',
        'NGAWI' => 'KC Ngawi',
        'PONOROGO' => 'KC Ponorogo',
    ];

    private const MONTH_COLUMNS = [
        'jan', 'feb', 'mar', 'apr', 'may', 'jun',
        'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
    ];

    /**
     * @param  array<int, string>  $paths
     * @return array<string, mixed>
     */
    public function sync(array $paths, int $year, bool $apply = false): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new RuntimeException("Tahun RKA tidak valid: {$year}.");
        }

        $this->assertTargetSchema();

        $lock = Cache::lock('rka:sync-breakdown:'.$year, 900);
        if (! $lock->get()) {
            throw new RuntimeException("Sinkronisasi RKA {$year} sedang dijalankan oleh proses lain.");
        }

        try {
            $prepared = $this->prepareWorkbooks($paths, $year);
            $branches = array_keys($prepared['branches']);
            $existingRows = (int) DB::table('rka')
                ->where('tahun', $year)
                ->whereIn('kanca', $branches)
                ->count();
            $databaseHashBefore = $this->databaseHash($year, $branches);
            $changesDetected = $existingRows !== count($prepared['rows'])
                || ! hash_equals($prepared['source_hash'], $databaseHashBefore);

            $databaseHash = $databaseHashBefore;
            if ($apply && $changesDetected) {
                $databaseHash = DB::transaction(function () use ($prepared, $branches, $year): string {
                    DB::table('rka')
                        ->where('tahun', $year)
                        ->whereIn('kanca', $branches)
                        ->delete();

                    foreach (array_chunk($prepared['rows'], 500) as $chunk) {
                        DB::table('rka')->insert($chunk);
                    }

                    $insertedRows = (int) DB::table('rka')
                        ->where('tahun', $year)
                        ->whereIn('kanca', $branches)
                        ->count();

                    if ($insertedRows !== count($prepared['rows'])) {
                        throw new RuntimeException(
                            "Verifikasi RKA gagal: {$insertedRows} baris tersimpan, ".count($prepared['rows']).' baris diharapkan.'
                        );
                    }

                    $invalidRows = (int) DB::table('rka')
                        ->where('tahun', $year)
                        ->whereIn('kanca', $branches)
                        ->where(function ($query): void {
                            $query->whereNull('desc_uker')
                                ->orWhere('desc_uker', '')
                                ->orWhereNull('mata_anggaran')
                                ->orWhere('mata_anggaran', '');
                        })
                        ->count();

                    if ($invalidRows > 0) {
                        throw new RuntimeException("Verifikasi RKA gagal: {$invalidRows} baris kehilangan unit kerja atau mata anggaran.");
                    }

                    $hash = $this->databaseHash($year, $branches);
                    if (! hash_equals($prepared['source_hash'], $hash)) {
                        throw new RuntimeException('Verifikasi RKA gagal: hash data database berbeda dari workbook sumber.');
                    }

                    return $hash;
                }, 3);

                app(OptimizedRkaLookupService::class)->invalidateCache();
            }

            $result = [
                'year' => $year,
                'apply_requested' => $apply,
                'applied' => $apply && $changesDetected,
                'changes_detected' => $changesDetected,
                'source_rows' => count($prepared['rows']),
                'replaced_rows' => $apply && $changesDetected ? $existingRows : 0,
                'existing_rows' => $existingRows,
                'source_hash' => $prepared['source_hash'],
                'database_hash_before' => $databaseHashBefore,
                'database_hash' => $databaseHash,
                'branches' => $prepared['branches'],
            ];
            $result['audit_path'] = $this->writeAudit($result);

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function assertTargetSchema(): void
    {
        if (! Schema::hasTable('rka')) {
            throw new RuntimeException('Tabel rka tidak tersedia.');
        }

        $required = array_merge(
            ['uniqueid_namareport', 'tahun', 'kanca', 'desc_uker', 'mata_anggaran'],
            self::MONTH_COLUMNS,
            ['created_at', 'updated_at']
        );
        $available = array_map('strtolower', Schema::getColumnListing('rka'));
        $missing = array_values(array_diff($required, $available));

        if ($missing !== []) {
            throw new RuntimeException('Schema tabel rka belum lengkap: '.implode(', ', $missing).'.');
        }
    }

    /**
     * @param  array<int, string>  $paths
     * @return array{rows: array<int, array<string, mixed>>, branches: array<string, array<string, mixed>>, source_hash: string}
     */
    private function prepareWorkbooks(array $paths, int $year): array
    {
        $filesByBranch = [];
        foreach ($paths as $path) {
            $resolvedPath = realpath((string) $path);
            if ($resolvedPath === false || ! is_file($resolvedPath)) {
                throw new RuntimeException("File RKA tidak ditemukan: {$path}.");
            }

            $branch = $this->resolveBranchFromFilename(basename($resolvedPath));
            if (isset($filesByBranch[$branch])) {
                throw new RuntimeException("File RKA untuk {$branch} diberikan lebih dari satu kali.");
            }
            $filesByBranch[$branch] = $resolvedPath;
        }

        $expected = array_values(self::EXPECTED_BRANCHES);
        sort($expected);
        $actual = array_keys($filesByBranch);
        sort($actual);
        if ($actual !== $expected) {
            throw new RuntimeException(
                'Paket RKA harus berisi tepat empat cabang: '.implode(', ', $expected)
                .'. Diterima: '.($actual === [] ? '-' : implode(', ', $actual)).'.'
            );
        }

        ksort($filesByBranch);
        $rows = [];
        $branches = [];
        foreach ($filesByBranch as $branch => $path) {
            $parsed = $this->parseWorkbook($path, $branch, $year);
            array_push($rows, ...$parsed['rows']);
            $branches[$branch] = $parsed['manifest'];
        }

        usort($rows, static fn (array $left, array $right): int => [
            $left['kanca'],
            $left['uniqueid_namareport'],
        ] <=> [
            $right['kanca'],
            $right['uniqueid_namareport'],
        ]);

        return [
            'rows' => $rows,
            'branches' => $branches,
            'source_hash' => $this->rowsHash($rows),
        ];
    }

    /** @return array{rows: array<int, array<string, mixed>>, manifest: array<string, mixed>} */
    private function parseWorkbook(string $path, string $branch, int $year): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = (int) $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            $scanEnd = min(200, $highestRow);
            $scanRows = $sheet->rangeToArray("A1:{$highestColumn}{$scanEnd}", null, true, false);
            $headerOffset = $this->detectHeaderOffset($scanRows);

            if ($headerOffset === null) {
                throw new RuntimeException('Header RKA lengkap tidak ditemukan pada '.basename($path).'.');
            }

            $headerRow = $headerOffset + 1;
            $headerMap = [];
            foreach ($scanRows[$headerOffset] as $index => $header) {
                $normalized = $this->normalizeHeader((string) $header);
                if ($normalized !== '') {
                    $headerMap[$normalized] = (int) $index;
                }
            }

            $requiredHeaders = array_merge(['desc_uker', 'mata_anggaran'], self::MONTH_COLUMNS);
            $missingHeaders = array_values(array_diff($requiredHeaders, array_keys($headerMap)));
            if ($missingHeaders !== []) {
                throw new RuntimeException(
                    basename($path).' kehilangan header wajib: '.implode(', ', $missingHeaders).'.'
                );
            }

            $fileHash = hash_file('sha256', $path);
            $timestamp = now()->format('Y-m-d H:i:s');
            $rows = [];
            $units = [];
            $budgetNames = [];
            $allZeroRows = 0;

            for ($startRow = $headerRow + 1; $startRow <= $highestRow; $startRow += 1000) {
                $endRow = min($highestRow, $startRow + 999);
                $chunk = $sheet->rangeToArray("A{$startRow}:{$highestColumn}{$endRow}", null, true, false);

                foreach ($chunk as $offset => $sourceRow) {
                    $sourceRow = array_pad((array) $sourceRow, $highestColumnIndex, null);
                    $rowNumber = $startRow + $offset;
                    $descUker = trim((string) ($sourceRow[$headerMap['desc_uker']] ?? ''));
                    $mataAnggaran = trim((string) ($sourceRow[$headerMap['mata_anggaran']] ?? ''));

                    if ($descUker === '' && $mataAnggaran === '' && $this->isSourceRowEmpty($sourceRow)) {
                        continue;
                    }
                    if ($descUker === '' || $mataAnggaran === '') {
                        throw new RuntimeException(
                            basename($path)." baris {$rowNumber} kehilangan DESC UKER atau MATA ANGGARAN."
                        );
                    }
                    if (mb_strlen($descUker) > 180 || mb_strlen($mataAnggaran) > 100) {
                        throw new RuntimeException(
                            basename($path)." baris {$rowNumber} melebihi kapasitas kolom tabel rka."
                        );
                    }

                    $row = [
                        'uniqueid_namareport' => sprintf(
                            'rka_%d_%s_%06d_%s',
                            $year,
                            Str::slug($branch, '_'),
                            $rowNumber,
                            substr($fileHash, 0, 12)
                        ),
                        'tahun' => $year,
                        'kanca' => $branch,
                        'desc_uker' => $descUker,
                        'mata_anggaran' => $mataAnggaran,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    $allZero = true;
                    foreach (self::MONTH_COLUMNS as $month) {
                        $value = $this->normalizeDecimal(
                            $sourceRow[$headerMap[$month]] ?? null,
                            basename($path),
                            $rowNumber,
                            $month
                        );
                        $row[$month] = $value;
                        $allZero = $allZero && $value === '0.00';
                    }

                    if ($allZero) {
                        $allZeroRows++;
                    }
                    $units[$descUker] = true;
                    $budgetNames[$mataAnggaran] = true;
                    $rows[] = $row;
                }
            }

            if ($rows === []) {
                throw new RuntimeException(basename($path).' tidak memiliki baris data RKA.');
            }

            return [
                'rows' => $rows,
                'manifest' => [
                    'file' => $path,
                    'sha256' => $fileHash,
                    'header_row' => $headerRow,
                    'rows' => count($rows),
                    'distinct_units' => count($units),
                    'distinct_mata_anggaran' => count($budgetNames),
                    'all_months_zero_rows' => $allZeroRows,
                ],
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function resolveBranchFromFilename(string $filename): string
    {
        $upper = strtoupper($filename);
        foreach (self::EXPECTED_BRANCHES as $needle => $branch) {
            if (str_contains($upper, $needle)) {
                return $branch;
            }
        }

        throw new RuntimeException("Nama file tidak menunjukkan cabang RKA yang didukung: {$filename}.");
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function detectHeaderOffset(array $rows): ?int
    {
        $required = array_merge(['desc_uker', 'mata_anggaran'], self::MONTH_COLUMNS);
        foreach ($rows as $offset => $row) {
            $headers = array_values(array_unique(array_filter(array_map(
                fn ($value): string => $this->normalizeHeader((string) $value),
                (array) $row
            ))));
            if (array_diff($required, $headers) === []) {
                return (int) $offset;
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value) ?? '';

        return strtolower(trim($value, '_'));
    }

    /** @param array<int, mixed> $row */
    private function isSourceRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDecimal(mixed $value, string $file, int $row, string $column): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '0.00';
        }
        if (! is_numeric($value)) {
            throw new RuntimeException("{$file} baris {$row} kolom {$column} bukan angka: {$value}.");
        }

        return number_format((float) $value, 2, '.', '');
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function rowsHash(array $rows): string
    {
        $context = hash_init('sha256');
        foreach ($rows as $row) {
            hash_update($context, json_encode($this->canonicalRow($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }

        return hash_final($context);
    }

    /** @param array<int, string> $branches */
    private function databaseHash(int $year, array $branches): string
    {
        $columns = array_merge(
            ['uniqueid_namareport', 'kanca', 'desc_uker', 'mata_anggaran'],
            self::MONTH_COLUMNS
        );
        $context = hash_init('sha256');

        foreach (DB::table('rka')
            ->select($columns)
            ->where('tahun', $year)
            ->whereIn('kanca', $branches)
            ->orderBy('kanca')
            ->orderBy('uniqueid_namareport')
            ->cursor() as $row) {
            hash_update(
                $context,
                json_encode($this->canonicalRow((array) $row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );
        }

        return hash_final($context);
    }

    /** @param array<string, mixed> $row */
    private function canonicalRow(array $row): array
    {
        $canonical = [
            (string) ($row['kanca'] ?? ''),
            (string) ($row['uniqueid_namareport'] ?? ''),
            (string) ($row['desc_uker'] ?? ''),
            (string) ($row['mata_anggaran'] ?? ''),
        ];

        foreach (self::MONTH_COLUMNS as $month) {
            $canonical[] = number_format((float) ($row[$month] ?? 0), 2, '.', '');
        }

        return $canonical;
    }

    /** @param array<string, mixed> $result */
    private function writeAudit(array $result): ?string
    {
        try {
            $directory = storage_path('app/rka-sync-audits');
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return null;
            }

            $path = $directory.DIRECTORY_SEPARATOR.now()->format('Ymd_His_u').'.json';
            $payload = $result;
            $payload['recorded_at'] = now()->toIso8601String();
            file_put_contents(
                $path,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
