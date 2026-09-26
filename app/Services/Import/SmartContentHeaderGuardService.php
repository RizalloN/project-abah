<?php

namespace App\Services\Import;

use App\Support\StrictDateParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SmartContentHeaderGuardService
 *
 * Provides intelligent, content-aware column header guard and recognition
 * for preview and pre-import validation.
 *
 * KEY PRINCIPLES:
 * 1. Hybrid Guard: Checks header names first (fast-path). If headers were renamed
 *    by internal web export tools (e.g. TEXTBOX..., TANGGAL alih-alih PERIODE,
 *    OUTSTANDING alih-alih BAKI_DEBET, NOREK alih-alih ACCTNO), it inspects the
 *    data contents of sample rows (10-30 rows) already loaded in preview.
 * 2. Zero Import Overhead: Resolves and caches column mappings strictly during the
 *    preview / initialization phase. The bulk data load (LOAD DATA LOCAL INFILE / Polars)
 *    consumes the pre-computed blueprint directly with 0 ms overhead per imported row.
 */
class SmartContentHeaderGuardService
{
    private const MAX_SAMPLE_VALUES_PER_COLUMN = 30;
    private const DOMINANT_TYPE_THRESHOLD = 0.65; // 65% match required for semantic typing

    private SchemaIntrospectionService $schemaService;
    private array $resolvedMappingCache = [];

    public function __construct(?SchemaIntrospectionService $schemaService = null)
    {
        $this->schemaService = $schemaService ?? app(SchemaIntrospectionService::class);
    }

    /**
     * Profile sample cell values in a single column to detect its semantic data type.
     *
     * @param array<int|string, mixed> $values
     * @return array{
     *     dominant_type: string,
     *     confidence: float,
     *     types: array<string, int>,
     *     sample_count: int,
     *     has_constant_value: bool,
     *     sample_values: array
     * }
     */
    public function profileColumnValues(array $values): array
    {
        $cleanedValues = [];
        foreach ($values as $v) {
            if ($v === null) {
                continue;
            }
            $str = trim((string) $v);
            if ($str === '' || $str === '\\N' || strtolower($str) === 'null' || strtolower($str) === 'nan') {
                continue;
            }
            $cleanedValues[] = $str;
            if (count($cleanedValues) >= self::MAX_SAMPLE_VALUES_PER_COLUMN) {
                break;
            }
        }

        $sampleCount = count($cleanedValues);
        if ($sampleCount === 0) {
            return [
                'dominant_type' => 'unknown',
                'confidence' => 0.0,
                'types' => [],
                'sample_count' => 0,
                'has_constant_value' => false,
                'sample_values' => [],
            ];
        }

        $uniqueValues = array_unique($cleanedValues);
        $hasConstantValue = count($uniqueValues) === 1 && $sampleCount >= 2;

        $typeCounts = [
            'date' => 0,
            'account_number' => 0,
            'cif' => 0,
            'currency_amount' => 0,
            'integer_id' => 0,
            'percentage_rate' => 0,
            'branch_code' => 0,
            'branch_name' => 0,
            'boolean_flag' => 0,
            'phone' => 0,
            'email' => 0,
            'text' => 0,
        ];

        foreach ($cleanedValues as $val) {
            $inferred = $this->inferValueSemanticType($val);
            if (isset($typeCounts[$inferred])) {
                $typeCounts[$inferred]++;
            } else {
                $typeCounts['text']++;
            }
        }

        $dominantType = 'text';
        $bestScore = 0;
        foreach ($typeCounts as $type => $count) {
            if ($type === 'text') {
                continue;
            }
            if ($count > $bestScore) {
                $bestScore = $count;
                $dominantType = $type;
            }
        }

        $confidence = $sampleCount > 0 ? ($bestScore / $sampleCount) : 0.0;
        if ($confidence < self::DOMINANT_TYPE_THRESHOLD) {
            // Check if combined numeric (currency + integer) forms dominant
            $combinedNumeric = $typeCounts['currency_amount'] + $typeCounts['integer_id'];
            if (($combinedNumeric / $sampleCount) >= self::DOMINANT_TYPE_THRESHOLD) {
                $dominantType = 'currency_amount';
                $confidence = $combinedNumeric / $sampleCount;
            } else {
                $dominantType = 'text';
            }
        }

        return [
            'dominant_type' => $dominantType,
            'confidence' => round($confidence, 2),
            'types' => array_filter($typeCounts, static fn (int $c): bool => $c > 0),
            'sample_count' => $sampleCount,
            'has_constant_value' => $hasConstantValue,
            'sample_values' => array_slice($cleanedValues, 0, 5),
        ];
    }

    /**
     * Infer the semantic type of a single string value.
     */
    public function inferValueSemanticType(string $val): string
    {
        $val = trim($val);
        if ($val === '') {
            return 'unknown';
        }

        // 1. Boolean flag (Y/N, 1/0, YA/TIDAK, TRUE/FALSE)
        if (preg_match('/^(Y|N|YA|TIDAK|TRUE|FALSE|AKTIF|NONAKTIF)$/i', $val)) {
            return 'boolean_flag';
        }

        // 2. Email
        if (filter_var($val, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        // 3. Phone (Indonesian format 08..., +628..., 628...)
        if (preg_match('/^(\+?62|0)8[1-9][0-9]{7,11}$/', $val)) {
            return 'phone';
        }

        // 4. Rate / Percentage (e.g. "6.50%", "0.065", "12.5%")
        if (preg_match('/^-?\d+(?:[.,]\d+)?\s*%$/', $val)) {
            return 'percentage_rate';
        }

        // 5. Date detection (ISO, Slash, Dash, Textual Indonesian/English, Excel numeric)
        if ($this->isDateLikeValue($val)) {
            return 'date';
        }

        // 6. Branch Name pattern (contains KC, KCP, UNIT, KANCA, CABANG, KANWIL)
        if (preg_match('/\b(KC|KCP|UNIT|KANCA|CABANG|KANWIL|KANTOR\s+CABANG)\b/i', $val)) {
            return 'branch_name';
        }

        // 7. Branch Code (3-5 digit integer, e.g. 0012, 6120, 0413) or "0012 - KC..."
        if (preg_match('/^\d{4,5}\s*[-–]\s*.+$/', $val)) {
            return 'branch_code';
        }

        // 8. Account Number (Bank BRI 15 digit rekening, or 10-18 digit numeric)
        $cleanDigits = preg_replace('/[^0-9]/', '', $val);
        if (strlen($cleanDigits) >= 10 && strlen($cleanDigits) <= 18 && (strlen($cleanDigits) === strlen($val) || preg_match('/^\d{3,5}[-.]\d{2,4}[-.]\d{5,10}$/', $val))) {
            return 'account_number';
        }

        // 9. CIF Number (7 to 9 pure digits, not starting with 0 if short)
        if (preg_match('/^[0-9]{7,9}$/', $val)) {
            return 'cif';
        }

        // 10. Branch Code standalone (e.g. 4 digits like 0012, 0123, 6120)
        if (preg_match('/^0\d{3}$/', $val) || (strlen($cleanDigits) === 4 && (int) $val <= 9999)) {
            return 'branch_code';
        }

        // 11. Currency / Decimal Amount
        // e.g. "1,250,000.00", "1250000.50", "-5000", "(1,000.00)", "Rp 50.000"
        if ($this->isCurrencyOrDecimalAmount($val)) {
            return 'currency_amount';
        }

        // 12. Integer ID or small sequence (e.g. 1, 2, 3... 100)
        if (preg_match('/^-?\d{1,6}$/', $val)) {
            return 'integer_id';
        }

        return 'text';
    }

    /**
     * Check if a value represents a date or timestamp.
     */
    public function isDateLikeValue(string $val): bool
    {
        $val = trim($val);
        if ($val === '' || strlen($val) < 4) {
            return false;
        }

        // Standard ISO date: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
        if (preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/', $val)) {
            $year = (int) substr($val, 0, 4);
            return $year >= 1990 && $year <= 2099;
        }

        // Slash/Dash date: DD/MM/YYYY or DD-MM-YYYY or MM/DD/YYYY
        if (preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}$/', $val)) {
            $parts = preg_split('/[-\/]/', $val);
            $year = (int) ($parts[2] ?? 0);
            return $year >= 1990 && $year <= 2099;
        }

        // DDMMYYYY or YYYYMMDD 8-digit numeric
        if (preg_match('/^\d{8}$/', $val)) {
            $year1 = (int) substr($val, 0, 4);
            $year2 = (int) substr($val, 4, 4);
            if ($year1 >= 1990 && $year1 <= 2099) {
                $month = (int) substr($val, 4, 2);
                $day = (int) substr($val, 6, 2);
                return $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31;
            }
            if ($year2 >= 1990 && $year2 <= 2099) {
                $day = (int) substr($val, 0, 2);
                $month = (int) substr($val, 2, 2);
                return $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31;
            }
        }

        // Textual dates with month names (Indonesian / English): e.g. "31 Maret 2026", "23-Mar-2026", "June 26, 2026 at 6:00 AM"
        $monthPattern = '(?:jan|feb|mar|apr|mei|may|jun|jul|agu|agt|aug|sep|okt|oct|nov|des|dec)[a-z]*';
        if (preg_match('/^(?:\d{1,2}[\s\-\/]+' . $monthPattern . '[\s\-\/]+\d{4}|' . $monthPattern . '[\s\-\/]+\d{1,2}(?:st|nd|rd|th)?,?[\s\-\/]+\d{4})/i', $val)) {
            return true;
        }

        // Excel numeric serial date (e.g. 40000 to 55000 corresponds to 2009 to 2050)
        // Only consider if float/int in that specific range without currency symbols
        if (is_numeric($val)) {
            $num = (float) $val;
            if ($num >= 35000 && $num <= 60000 && strpos($val, ',') === false) {
                // If it has no decimal or typical time decimal (.0 - .999)
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value represents a currency or decimal amount.
     */
    public function isCurrencyOrDecimalAmount(string $val): bool
    {
        $val = trim($val);
        // Strip Rp, IDR, spaces
        $val = preg_replace('/^(?:rp|idr)\.?\s*/i', '', $val);
        $val = trim($val);

        // Account for parenthesized negative numbers e.g. (1,250.00)
        if (str_starts_with($val, '(') && str_ends_with($val, ')')) {
            $val = '-' . substr($val, 1, -1);
        }

        // Check if numeric with thousand dots/commas
        // Format 1: 1,250,000.50
        if (preg_match('/^-?\d{1,3}(?:,\d{3})*(?:\.\d+)?$/', $val)) {
            return true;
        }
        // Format 2: 1.250.000,50 (Indonesian / European format)
        if (preg_match('/^-?\d{1,3}(?:\.\d{3})*(?:,\d+)?$/', $val)) {
            return true;
        }
        // Plain decimal e.g. 1250000.50 or -5000
        if (preg_match('/^-?\d+(?:\.\d+)?$/', $val)) {
            // Avoid misidentifying single digit sequences like 1, 2, 3 as big currency
            return strlen($val) > 4 || str_contains($val, '.');
        }

        return false;
    }

    /**
     * Detect which column index contains the primary date (posisi / periode).
     *
     * @param array<int|string, string> $headers
     * @param array<int, array<int|string, mixed>> $sampleRows
     * @return int|null 0-based column index, or null if not detected
     */
    public function detectDateColumnIndex(array $headers, array $sampleRows): ?int
    {
        $headersList = array_values($headers);
        $dateCandidates = [];

        foreach ($headersList as $index => $header) {
            $headerNorm = $this->normalizeHeaderName((string) $header);

            // Extract sample values for this column
            $columnValues = [];
            foreach ($sampleRows as $row) {
                $rowList = array_values((array) $row);
                if (isset($rowList[$index])) {
                    $columnValues[] = $rowList[$index];
                }
            }

            $profile = $this->profileColumnValues($columnValues);
            if ($profile['dominant_type'] === 'date') {
                $score = $profile['confidence'] * 50;

                // Strong header name hints
                if (in_array($headerNorm, ['POSISI', 'PERIODE', 'TGL_POSISI', 'TANGGAL_POSISI', 'MONTH_DAY_YEAR_OF_POSISI', 'TANGGAL'], true)) {
                    $score += 100;
                } elseif (str_contains($headerNorm, 'POSISI') || str_contains($headerNorm, 'PERIODE') || str_contains($headerNorm, 'TGL') || str_contains($headerNorm, 'DATE')) {
                    $score += 50;
                }

                // In banking daily snapshot reports, periode/posisi is constant across all rows
                if ($profile['has_constant_value']) {
                    $score += 30;
                }

                $dateCandidates[$index] = $score;
            }
        }

        if (empty($dateCandidates)) {
            // Fallback: Check header names directly even if sample rows were empty
            foreach ($headersList as $index => $header) {
                $headerNorm = $this->normalizeHeaderName((string) $header);
                if (in_array($headerNorm, ['POSISI', 'PERIODE', 'TGL_POSISI', 'TANGGAL_POSISI', 'MONTH_DAY_YEAR_OF_POSISI'], true)) {
                    return (int) $index;
                }
            }
            return null;
        }

        arsort($dateCandidates);
        return (int) key($dateCandidates);
    }

    /**
     * Detect which row in an array of spreadsheet/CSV rows is the header row.
     * Uses hybrid label scoring + next-row data profiling.
     *
     * @param array<int, array> $rows Array of raw rows (e.g. first 20-50 rows)
     * @param string|null $tableName Target database table name
     * @return int|null 0-based index of the detected header row, or null
     */
    public function detectHeaderRow(array $rows, ?string $tableName = null): ?int
    {
        if (empty($rows)) {
            return null;
        }

        $bestIndex = null;
        $bestScore = -1.0;

        $expectedColumnsLookup = [];
        if ($tableName && $this->schemaService->hasTable($tableName)) {
            $expectedColumnsLookup = array_fill_keys(
                array_map('strtolower', $this->schemaService->getColumnListing($tableName)),
                true
            );
        }

        $maxScan = min(count($rows), 50);
        for ($i = 0; $i < $maxScan; $i++) {
            $row = (array) ($rows[$i] ?? []);
            $nonEmptyCells = array_values(array_filter(
                $row,
                static fn ($c): bool => $c !== null && trim((string) $c) !== ''
            ));

            if (count($nonEmptyCells) < 3) {
                continue;
            }

            // A valid header row consists mostly of TEXT LABELS, not numbers or pure dates
            $textLabelCount = 0;
            $headerMatchCount = 0;
            $hasPeriodeOrPosisi = false;

            foreach ($nonEmptyCells as $cell) {
                $str = trim((string) $cell);
                $norm = $this->normalizeHeaderName($str);

                if (in_array($norm, ['PERIODE', 'POSISI', 'TGL_POSISI', 'TANGGAL'], true)) {
                    $hasPeriodeOrPosisi = true;
                }

                if (!$this->isDateLikeValue($str) && !$this->isCurrencyOrDecimalAmount($str)) {
                    $textLabelCount++;
                }

                if ($tableName && (isset($expectedColumnsLookup[strtolower($norm)]) || $this->hasKnownSemanticAlias($norm, $tableName))) {
                    $headerMatchCount++;
                }
            }

            $labelRatio = count($nonEmptyCells) > 0 ? ($textLabelCount / count($nonEmptyCells)) : 0;
            if ($labelRatio < 0.6) {
                // Too many numbers/dates to be a header row
                continue;
            }

            // Inspect the NEXT rows (i+1 to i+3) to confirm they look like DATA rows
            $dataRowScore = 0;
            $nextRowsSample = array_slice($rows, $i + 1, 3);
            if (!empty($nextRowsSample)) {
                foreach ($nextRowsSample as $nextRow) {
                    $nextNonEmpty = array_filter((array) $nextRow, static fn ($c): bool => $c !== null && trim((string) $c) !== '');
                    if (count($nextNonEmpty) >= count($nonEmptyCells) * 0.7) {
                        $dataRowScore += 10;
                    }
                }
            }

            $totalScore = ($labelRatio * 20) + ($headerMatchCount * 15) + $dataRowScore;
            if ($hasPeriodeOrPosisi) {
                $totalScore += 50;
            }

            if ($totalScore > $bestScore) {
                $bestScore = $totalScore;
                $bestIndex = $i;
            }
        }

        return $bestIndex;
    }

    /**
     * Match source headers and sample row contents to target database table columns.
     *
     * @param array<int|string, string> $headers
     * @param array<int, array> $sampleRows
     * @param string $tableName
     * @param array<string, mixed> $options
     * @return array{
     *     mapping: array<int, string>,
     *     by_header: array<string, string>,
     *     posisi_index: int|null,
     *     periode_index: int|null,
     *     confidence: array<int, float>,
     *     repaired_count: int,
     *     unmapped_indexes: array<int>
     * }
     */
    public function matchColumns(array $headers, array $sampleRows, string $tableName, array $options = []): array
    {
        $headersList = array_values($headers);
        $tableColumns = $this->schemaService->getColumnListing($tableName);
        $tableMeta = $this->schemaService->getColumnMetadata($tableName);

        $tableColumnsLower = [];
        foreach ($tableColumns as $col) {
            $tableColumnsLower[strtolower($col)] = $col;
        }

        $mapping = [];
        $byHeader = [];
        $confidence = [];
        $unmappedIndexes = [];
        $assignedDbColumns = [];
        $repairedCount = 0;

        // Phase 1: Fast-Path Name Matching (Exact & High-Confidence Aliases)
        foreach ($headersList as $index => $header) {
            $raw = trim((string) $header);
            $normalized = $this->normalizeHeaderName($raw);
            $dbCol = $this->resolveDirectNameCandidate($normalized, $raw, $tableColumnsLower, $tableName);

            if ($dbCol !== null && !isset($assignedDbColumns[strtolower($dbCol)])) {
                $mapping[$index] = $dbCol;
                $byHeader[$raw] = $dbCol;
                $confidence[$index] = 1.0;
                $assignedDbColumns[strtolower($dbCol)] = true;
            } else {
                $unmappedIndexes[] = $index;
            }
        }

        // Phase 2: Content-Aware Matching for Unmapped / Generic Headers (e.g. TEXTBOX..., Field..., or Renamed)
        if (!empty($unmappedIndexes) && !empty($sampleRows)) {
            // Profile unmapped columns
            $unmappedProfiles = [];
            foreach ($unmappedIndexes as $index) {
                $colValues = [];
                foreach ($sampleRows as $row) {
                    $rowList = array_values((array) $row);
                    if (isset($rowList[$index])) {
                        $colValues[] = $rowList[$index];
                    }
                }
                $unmappedProfiles[$index] = $this->profileColumnValues($colValues);
            }

            // Find remaining unassigned DB columns
            $availableDbColumns = [];
            foreach ($tableColumns as $col) {
                $lower = strtolower($col);
                if (!isset($assignedDbColumns[$lower]) && !in_array($lower, ['id', 'created_at', 'updated_at'], true)) {
                    $availableDbColumns[$lower] = [
                        'name' => $col,
                        'role' => $this->inferDbColumnSemanticRole($col, $tableMeta[$lower] ?? []),
                        'meta' => $tableMeta[$lower] ?? [],
                    ];
                }
            }

            // Score and assign best matches
            foreach ($unmappedIndexes as $sourceIndex) {
                $profile = $unmappedProfiles[$sourceIndex] ?? null;
                if (!$profile || $profile['dominant_type'] === 'unknown') {
                    continue;
                }

                $rawHeader = (string) ($headersList[$sourceIndex] ?? '');
                $normHeader = $this->normalizeHeaderName($rawHeader);

                $bestCandidate = null;
                $highestScore = 0.0;

                foreach ($availableDbColumns as $dbColLower => $colInfo) {
                    $score = $this->calculateColumnMatchScore(
                        $rawHeader,
                        $normHeader,
                        $sourceIndex,
                        $profile,
                        $colInfo['name'],
                        $colInfo['role'],
                        $colInfo['meta'],
                        count($headersList)
                    );

                    if ($score > $highestScore && $score >= 60.0) {
                        $highestScore = $score;
                        $bestCandidate = $colInfo['name'];
                    }
                }

                if ($bestCandidate !== null) {
                    $mapping[$sourceIndex] = $bestCandidate;
                    $byHeader[$rawHeader] = $bestCandidate;
                    $confidence[$sourceIndex] = round($highestScore / 100.0, 2);
                    $assignedDbColumns[strtolower($bestCandidate)] = true;
                    unset($availableDbColumns[strtolower($bestCandidate)]);
                    $repairedCount++;
                }
            }
        }

        // Detect positions for key date columns
        $posisiIndex = null;
        $periodeIndex = null;
        foreach ($mapping as $idx => $colName) {
            $lower = strtolower($colName);
            if ($lower === 'posisi' || str_contains($lower, 'posisi')) {
                $posisiIndex ??= $idx;
            }
            if ($lower === 'periode' || str_contains($lower, 'periode')) {
                $periodeIndex ??= $idx;
            }
        }

        if ($posisiIndex === null && $periodeIndex !== null) {
            $posisiIndex = $periodeIndex;
        }

        return [
            'mapping' => $mapping,
            'by_header' => $byHeader,
            'posisi_index' => $posisiIndex,
            'periode_index' => $periodeIndex,
            'confidence' => $confidence,
            'repaired_count' => $repairedCount,
            'unmapped_indexes' => array_values(array_diff($unmappedIndexes, array_keys($mapping))),
        ];
    }

    /**
     * Calculate score between a source column profile and a target DB column candidate.
     */
    private function calculateColumnMatchScore(
        string $rawHeader,
        string $normHeader,
        int $sourceIndex,
        array $profile,
        string $dbColName,
        string $dbColRole,
        array $dbColMeta,
        int $totalColumnCount
    ): float {
        $score = 0.0;
        $dbColLower = strtolower($dbColName);

        // 1. Semantic Type Match
        $sourceType = $profile['dominant_type'];
        if ($sourceType === $dbColRole) {
            $score += 65.0 * $profile['confidence'];
        } elseif ($sourceType === 'currency_amount' && in_array($dbColRole, ['currency_amount', 'integer_id'], true)) {
            $score += 50.0 * $profile['confidence'];
        } elseif ($sourceType === 'account_number' && in_array($dbColRole, ['account_number', 'cif'], true)) {
            $score += 45.0 * $profile['confidence'];
        }

        // 2. Partial / Substring name match
        if ($normHeader !== '' && (str_contains($normHeader, $dbColLower) || str_contains($dbColLower, $normHeader))) {
            $score += 25.0;
        }

        // 3. Known Aliases for this DB column
        if ($this->isKnownAliasOf($normHeader, $dbColLower)) {
            $score += 35.0;
        }

        // 4. Constant Value Bonus for Periode / Posisi in Daily Reports
        if ($profile['has_constant_value'] && in_array($dbColLower, ['periode', 'posisi'], true)) {
            $score += 20.0;
        }

        return min(100.0, $score);
    }

    /**
     * Infer the expected semantic role of a DB column from its name and SQL metadata.
     */
    public function inferDbColumnSemanticRole(string $columnName, array $meta = []): string
    {
        $lower = strtolower($columnName);

        if (in_array($lower, ['periode', 'posisi', 'tgl_posisi', 'tgl_realisasi', 'tgl_ph', 'tgl_jatuh_tempo', 'next_pmt_date'], true) || str_starts_with($lower, 'tgl_') || str_ends_with($lower, '_date')) {
            return 'date';
        }

        if (in_array($lower, ['acctno', 'no_rekening', 'nomor_rekening', 'nomor_rekening1', 'rekening', 'account_no'], true)) {
            return 'account_number';
        }

        if (in_array($lower, ['cif', 'cif1', 'cifno', 'cif_no'], true)) {
            return 'cif';
        }

        if (in_array($lower, ['saldo', 'saldo_idr', 'baki_debet', 'baki_debet1', 'nominal', 'plafon', 'plafon_dalam_idr', 'os_idr', 'total_kewajiban', 'outstanding', 'fee_transaksi', 'jml_trx_sukses', 't_pokok', 't_bunga', 't_total'], true) || str_starts_with($lower, 'saldo_') || str_starts_with($lower, 'os_')) {
            return 'currency_amount';
        }

        if (in_array($lower, ['rate', 'suku_bunga', 'bunga_rate'], true)) {
            return 'percentage_rate';
        }

        if (in_array($lower, ['kode_kanca', 'kode_uker', 'kode_cabang', 'kode_kanwil', 'kanca', 'uker'], true) && ($meta['type'] ?? '') !== 'varchar') {
            return 'branch_code';
        }

        if (in_array($lower, ['nama_kanca', 'nama_cabang', 'nama_uker', 'nama_kanwil', 'brname', 'mbname', 'cabang1', 'unit1'], true)) {
            return 'branch_name';
        }

        if (in_array($lower, ['nama_debitur', 'nama_nasabah', 'nama_perusahaan', 'nama_agen', 'nama_rekening'], true)) {
            return 'person_name';
        }

        if (str_starts_with($lower, 'flag_') || in_array($lower, ['status', 'is_active'], true)) {
            return 'boolean_flag';
        }

        $baseType = strtolower((string) ($meta['base_type'] ?? ''));
        if (in_array($baseType, ['decimal', 'float', 'double'], true)) {
            return 'currency_amount';
        }
        if (in_array($baseType, ['date', 'datetime', 'timestamp'], true)) {
            return 'date';
        }
        if (in_array($baseType, ['int', 'integer', 'bigint', 'tinyint', 'smallint'], true)) {
            return 'integer_id';
        }

        return 'text';
    }

    /**
     * Check if a normalized header is a known alias for a DB column.
     */
    private function isKnownAliasOf(string $normHeader, string $dbColLower): bool
    {
        $accountAliases = ['acctno', 'no_rekening', 'nomor_rekening', 'nomor_rekening1', 'rekening', 'norek', 'account_number', 'acc_no'];
        $balanceAliases = ['saldo', 'saldo_idr', 'baki_debet', 'baki_debet1', 'outstanding', 'os_idr', 'os', 'total_kewajiban', 'nominal', 'balance', 'balance_dalam_idr', 'cbal_base', 'cbal', 'jml_nominal_casa', 'textbox9', 'textbox20', 'textbox21'];
        $plafonAliases = ['plafon', 'plafon_dalam_idr', 'orgamt_base', 'orgamt'];
        $dateAliases = ['periode', 'posisi', 'tgl_posisi', 'tanggal_posisi', 'tanggal', 'date', 'tgl_data', 'waktu', 'month_day_year_of_posisi'];
        $cifAliases = ['cif', 'cif1', 'cifno', 'cif_no', 'nomor_cif', 'no_cif'];
        $branchNameAliases = ['nama_kanca', 'nama_cabang', 'cabang', 'cabang1', 'kanca', 'brname', 'mbname', 'nama_uker', 'unit1', 'branch_name'];
        $branchCodeAliases = ['kode_kanca', 'kode_cabang', 'kanca', 'kode_uker', 'uker', 'branch_code', 'branch'];

        $norm = strtolower($normHeader);

        if (in_array($dbColLower, $accountAliases, true) && in_array($norm, $accountAliases, true)) {
            return true;
        }

        if (in_array($dbColLower, $balanceAliases, true) && in_array($norm, $balanceAliases, true)) {
            return true;
        }

        if (in_array($dbColLower, $plafonAliases, true) && in_array($norm, $plafonAliases, true)) {
            return true;
        }

        if (in_array($dbColLower, $dateAliases, true) && in_array($norm, $dateAliases, true)) {
            return true;
        }

        if (in_array($dbColLower, $cifAliases, true) && in_array($norm, $cifAliases, true)) {
            return true;
        }

        if (in_array($dbColLower, $branchNameAliases, true) && in_array($norm, $branchNameAliases, true)) {
            return true;
        }

        if (in_array($dbColLower, $branchCodeAliases, true) && in_array($norm, $branchCodeAliases, true)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve candidate DB column name for a header using known semantic aliases.
     *
     * @param string $header
     * @param array<string> $availableColumnsLower
     * @return string|null Lowercase column name if matched, or null
     */
    public function resolveAliasCandidate(string $header, array $availableColumnsLower): ?string
    {
        $norm = strtolower($this->normalizeHeaderName($header));
        foreach ($availableColumnsLower as $colLower) {
            $colLowerClean = strtolower(trim((string) $colLower));
            if ($this->isKnownAliasOf($norm, $colLowerClean)) {
                return $colLowerClean;
            }
        }
        return null;
    }

    /**
     * Resolve direct name candidate with table-specific alias rules.
     */
    private function resolveDirectNameCandidate(string $normalized, string $raw, array $tableColumnsLower, string $tableName): ?string
    {
        $normLower = strtolower($normalized);

        // Direct table column match
        if (isset($tableColumnsLower[$normLower])) {
            return $tableColumnsLower[$normLower];
        }

        // Common Textbox Aliases from internal reporting tools
        $textboxAliases = [
            'TEXTBOX20' => 'total_kewajiban',
            'TEXTBOX21' => 'os_idr',
            'TEXTBOX9' => 'jml_nominal_casa',
            'TEXTBOX10' => 'wilayah',
            'TEXTBOX11' => 'cabang',
            'TEXTBOX12' => 'uker',
            'TEXTBOX7' => 'corporate_id',
            'TEXTBOX13' => 'nama_perusahaan',
            'TEXTBOX3' => 'textbox3',
        ];

        if (isset($textboxAliases[strtoupper($raw)])) {
            $mapped = $textboxAliases[strtoupper($raw)];
            if (isset($tableColumnsLower[strtolower($mapped)])) {
                return $tableColumnsLower[strtolower($mapped)];
            }
        }

        // Table-specific rules
        if ($tableName === 'hourly_dpk') {
            if ($normLower === 'minute_of_posisi' || $normLower === 'posisi_jam') {
                return $tableColumnsLower['posisi'] ?? 'posisi';
            }
        }

        // General alias matching
        foreach ($tableColumnsLower as $lowerCol => $originalCol) {
            if ($this->isKnownAliasOf($normLower, $lowerCol)) {
                return $originalCol;
            }
        }

        return null;
    }

    /**
     * Check if a normalized header has a known semantic alias in a given table.
     */
    private function hasKnownSemanticAlias(string $normHeader, string $tableName): bool
    {
        $tableColumns = $this->schemaService->getColumnListing($tableName);
        foreach ($tableColumns as $col) {
            if ($this->isKnownAliasOf(strtolower($normHeader), strtolower($col))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalize a header string to uppercase alphanumeric with single underscores.
     */
    public function normalizeHeaderName(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', '_', $header);
        return strtoupper(trim((string) $normalized, '_'));
    }

    /**
     * Validate if the detected header or rows are valid for a given report table.
     * Combines name verification with sample content verification.
     *
     * @param array<int|string, string> $headers
     * @param string $tableName
     * @param array<int, array> $sampleRows
     * @return bool
     */
    public function isHeaderOrContentValidForTable(array $headers, string $tableName, array $sampleRows = []): bool
    {
        if (empty($headers)) {
            return false;
        }

        $tableNameLower = strtolower(trim($tableName));
        $dbColumns = array_fill_keys(
            array_map('strtolower', $this->schemaService->getColumnListing($tableName)),
            true
        );

        if (empty($dbColumns)) {
            return true; // Table not in DB or no columns, defer
        }

        // 1. Direct Name Match Score
        $score = 0;
        foreach ($headers as $h) {
            $norm = strtolower($this->normalizeHeaderName((string) $h));
            if (isset($dbColumns[$norm]) || $this->hasKnownSemanticAlias($norm, $tableName)) {
                $score++;
            }
        }

        if ($score >= 2) {
            return true;
        }

        // 2. Content-Aware Check on Sample Rows
        if (!empty($sampleRows)) {
            $matchResult = $this->matchColumns($headers, $sampleRows, $tableName);
            // If at least 2 key columns (or date + amount/account) were identified with confidence
            if (count($matchResult['mapping']) >= 2) {
                Log::info("SmartContentHeaderGuard: Accepted renamed headers for table [{$tableName}] via content matching.", [
                    'matched_columns' => array_values($matchResult['mapping']),
                    'repaired_count' => $matchResult['repaired_count'],
                ]);
                return true;
            }
        }

        // 3. Known Width / Report-Specific Candidates
        return match ($tableNameLower) {
            'ssa_simpanan' => count(array_filter($headers, static fn ($h): bool => trim((string) $h) !== '')) === 7,
            'ssa_pinjaman' => count(array_filter($headers, static fn ($h): bool => trim((string) $h) !== '')) === 14,
            'casa_brilink_web', 'casa_brilink_edc' => count($headers) >= 12 && count($headers) <= 14,
            'cras' => count($headers) >= 30 && count($headers) <= 35,
            default => false,
        };
    }
}
