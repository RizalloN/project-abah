<?php

namespace App\Services\Import\Crosstab;

use App\Support\StrictDateParser;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;

/**
 * Flattens an Excel crosstab export into one row per record, the way an analyst
 * does it by hand: unmerge, select blanks (Ctrl+G), "=↑", Ctrl+Enter.
 *
 * - The header row is found anywhere in the first rows (title and filter rows
 *   above it are skipped) and matched by name, alias or close spelling, so column
 *   order and labels may vary. A trailing measure column with no label (Tableau's
 *   single-measure export) takes the missing measure name, e.g. `saldo`.
 * - Merged cells are source truth: when the export has merges, only merged
 *   dimension cells are filled from their anchor.
 * - Without merges (already unmerged by hand), a blank dimension takes the value
 *   above only while every dimension to its left still belongs to the same group,
 *   so a new branch never inherits the previous branch's unit.
 * - Measures are never copied from another row. Total/subtotal rows are dropped so
 *   nothing is counted twice.
 *
 * A file that needs none of this returns null and keeps its existing import path.
 */
class SpreadsheetCrosstabNormalizer
{
    private const HEADER_SCAN_ROWS = 60;
    private const PROGRESS_EVERY_ROWS = 25000;
    private const FUZZY_THRESHOLD = 0.86;
    private const TOTAL_LABEL = '/^(grand\s*-?\s*total|sub\s*-?\s*total|total(\s+(keseluruhan|akhir|umum))?|jumlah(\s+total)?)$/iu';
    private const PLACEHOLDER_LABEL = '/^(col_?\d+|column_?\d+|kolom_?\d+|measure_values|f\d+)$/';

    public function __construct(private readonly CrosstabProfileRegistry $profiles)
    {
    }

    public function profileFor(string $tableName): ?CrosstabProfile
    {
        return $this->profiles->for($tableName);
    }

    /**
     * @return array{staged_csv_path:string,headers:list<string>,header_index:int,total_rows:int,normalization:array}|null
     */
    public function normalize(string $sourcePath, CrosstabProfile $profile, string $outputPath, ?callable $progress = null): ?array
    {
        $reader = new SpreadsheetGridReader($sourcePath);
        if (!$reader->isWorkbook()) {
            return null;
        }
        $merges = $reader->merges();
        $header = $this->detectHeader($reader, $profile, $merges);
        $hasMerges = $merges !== [];

        if (!$header['complete']) {
            // An already flat sheet with a full first row is someone else's layout rule to judge.
            if (!$hasMerges && $header['first_row_fully_labeled']) {
                return null;
            }

            throw new RuntimeException($this->describeMissingHeader($profile, $header));
        }

        $handle = @fopen($outputPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("File staging {$profile->label} tidak dapat dibuat.");
        }

        try {
            $result = $this->flatten($reader, $profile, $header, $merges, $handle, $progress);
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($outputPath);
            throw $e;
        }
        fclose($handle);

        $structural = $hasMerges
            || $header['row'] !== $header['first_row']
            || count($header['rows']) > 1
            || $header['unlabeled'] !== []
            || ($profile->passthroughNeedsExactHeaders && !$header['exact'])
            || $result['stats']['blank_filled'] > 0
            || $result['stats']['merge_filled'] > 0
            || $result['stats']['total_rows_skipped'] > 0
            || $result['stats']['dates_converted'] > 0
            || $result['stats']['formulas_resolved'] > 0;

        if (!$structural) {
            @unlink($outputPath);

            return null;
        }

        $failure = $result['error'] ?? null;
        if ($failure === null && $result['unknown_with_data'] !== []) {
            $failure = 'Kolom '.implode(', ', $result['unknown_with_data'])." berisi data tetapi tidak dikenali sebagai kolom {$profile->label}.";
        }
        if ($failure === null && $result['rows'] === 0) {
            $failure = "File {$profile->label} tidak memiliki baris data.";
        }
        if ($failure !== null) {
            @unlink($outputPath);
            throw new RuntimeException($failure);
        }

        return [
            'staged_csv_path' => $outputPath,
            'headers' => $profile->headers(),
            'header_index' => 0,
            'total_rows' => $result['rows'],
            'normalization' => $this->summarize($profile, $header, $result['stats'], $result['fill_down']),
        ];
    }

    public static function normalizeLabel(string $label): string
    {
        $label = str_replace("\u{00A0}", ' ', $label);
        // Tableau numbers its fields ("03. Kanca Konsolidasi"); the number is not part of the name.
        $label = (string) preg_replace('/(?<![\w.])\d{1,3}[.)]\s+/u', '', $label);
        $label = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $label));

        return trim($label, '_');
    }

    private function detectHeader(SpreadsheetGridReader $reader, CrosstabProfile $profile, array $merges): array
    {
        $scan = [];
        foreach ($reader->rows() as [$rowNumber, $values]) {
            $scan[$rowNumber] = $values;
            if (count($scan) >= self::HEADER_SCAN_ROWS) {
                break;
            }
        }
        if ($scan === []) {
            throw new RuntimeException("File {$profile->label} kosong.");
        }

        // Header labels can be merged across rows or columns; spread them before matching.
        foreach ($merges as [$firstColumn, $firstRow, $lastColumn, $lastRow]) {
            if (!isset($scan[$firstRow])) {
                continue;
            }
            $anchor = $scan[$firstRow][$firstColumn] ?? '';
            for ($row = $firstRow; $row <= $lastRow; $row++) {
                if (!isset($scan[$row])) {
                    continue;
                }
                for ($column = $firstColumn; $column <= $lastColumn; $column++) {
                    if (self::isBlank($scan[$row][$column] ?? '')) {
                        $scan[$row][$column] = $anchor;
                    }
                }
            }
        }

        $rowNumbers = array_keys($scan);
        $firstRow = $rowNumbers[0];
        $width = 0;
        foreach ($scan as $values) {
            $width = max($width, $values === [] ? 0 : max(array_keys($values)) + 1);
        }
        $firstLabels = $scan[$firstRow];
        $firstRowFullyLabeled = $width > 0 && count(array_filter(
            range(0, $width - 1),
            static fn (int $column): bool => !self::isBlank($firstLabels[$column] ?? '')
        )) === $width;

        $best = null;
        foreach ($rowNumbers as $position => $rowNumber) {
            $candidates = [[[$rowNumber], $scan[$rowNumber]]];
            if ($position > 0) {
                $upper = $scan[$rowNumbers[$position - 1]];
                $combined = $scan[$rowNumber];
                foreach ($upper as $column => $label) {
                    if (self::isBlank($combined[$column] ?? '')) {
                        $combined[$column] = $label;
                    }
                }
                ksort($combined);
                $candidates[] = [[$rowNumbers[$position - 1], $rowNumber], $combined];
            }

            foreach ($candidates as [$rows, $labels]) {
                $match = $this->matchHeader($profile, $labels, $width);
                $match += ['row' => $rowNumber, 'rows' => $rows, 'first_row' => $firstRow, 'first_row_fully_labeled' => $firstRowFullyLabeled];
                if ($match['complete']) {
                    return $match;
                }
                if ($best === null || count($match['map']) > count($best['map'])) {
                    $best = $match;
                }
            }
        }

        return $best;
    }

    private function matchHeader(CrosstabProfile $profile, array $labels, int $width): array
    {
        $map = [];
        $assigned = [];
        $unknown = [];
        $unlabeled = [];
        $renamed = [];

        for ($column = 0; $column < $width; $column++) {
            $label = trim(str_replace("\u{00A0}", ' ', (string) ($labels[$column] ?? '')));
            $normalized = self::normalizeLabel($label);
            if ($normalized === '' || preg_match(self::PLACEHOLDER_LABEL, $normalized)) {
                $unlabeled[] = $column;
                continue;
            }
            $canonical = $this->resolveCanonical($profile, $label, $normalized);
            if ($canonical !== null && !isset($assigned[$canonical])) {
                $map[$column] = $canonical;
                $assigned[$canonical] = $column;
                $expected = $profile->headers()[array_search($canonical, $profile->columns, true)];
                if ($normalized !== $canonical && $normalized !== self::normalizeLabel((string) $expected)) {
                    $renamed[$label] = $canonical;
                }
                continue;
            }
            $unknown[$column] = $label;
        }

        // Close spellings ("Kolektibilitas" for "Kolektabilitas") match only one unclaimed column.
        foreach ($unknown as $column => $label) {
            $canonical = $this->fuzzyCanonical($profile, self::normalizeLabel($label), $assigned);
            if ($canonical !== null) {
                $map[$column] = $canonical;
                $assigned[$canonical] = $column;
                $renamed[$label] = $canonical;
                unset($unknown[$column]);
            }
        }

        // A single-measure crosstab leaves the measure header blank: name it after the missing measure.
        $missing = array_values(array_diff($profile->columns, array_keys($assigned)));
        $named = [];
        $dimensionColumns = array_intersect_key($assigned, array_flip($profile->dimensions()));
        if ($missing !== []
            && count($dimensionColumns) === $profile->dimensionCount
            && array_filter($missing, fn (string $column): bool => !$profile->isMeasure($column)) === []) {
            $lastDimension = max($dimensionColumns);
            $candidates = array_values(array_filter($unlabeled, static fn (int $column): bool => $column > $lastDimension));
            if (count($candidates) === count($missing)) {
                foreach ($candidates as $index => $column) {
                    $map[$column] = $missing[$index];
                    $assigned[$missing[$index]] = $column;
                    $named[$column] = $missing[$index];
                }
                $unlabeled = array_values(array_diff($unlabeled, $candidates));
                $missing = [];
            }
        }

        ksort($map);
        $exact = array_values($map) === $profile->columns
            && array_keys($map) === range(0, count($profile->columns) - 1)
            && $named === []
            && array_map(static fn (int $column): string => (string) ($labels[$column] ?? ''), array_keys($map)) === $profile->headers();

        return [
            'complete' => $missing === [],
            'map' => $map,
            'missing' => $missing,
            'unknown' => $unknown,
            'unlabeled' => $named,
            'renamed' => $renamed,
            'exact' => $exact,
        ];
    }

    private function resolveCanonical(CrosstabProfile $profile, string $label, string $normalized): ?string
    {
        if (in_array($normalized, $profile->columns, true)) {
            return $normalized;
        }
        if (isset($profile->aliases[$normalized]) && in_array($profile->aliases[$normalized], $profile->columns, true)) {
            return $profile->aliases[$normalized];
        }
        foreach ($profile->headers() as $index => $header) {
            if (self::normalizeLabel((string) $header) === $normalized) {
                return $profile->columns[$index];
            }
        }
        if ($profile->headerResolver !== null) {
            $stripped = (string) preg_replace('/(?<![\w.])\d{1,3}[.)]\s+/u', '', $label);
            $resolved = strtolower((string) ($profile->headerResolver)($stripped));
            if (in_array($resolved, $profile->columns, true)) {
                return $resolved;
            }
        }

        return null;
    }

    private function fuzzyCanonical(CrosstabProfile $profile, string $normalized, array $assigned): ?string
    {
        if (strlen($normalized) < 5 || strlen($normalized) > 255) {
            return null;
        }

        $names = [];
        foreach ($profile->columns as $column) {
            $names[$column] = [$column];
        }
        foreach ($profile->aliases as $alias => $column) {
            $names[$column][] = $alias;
        }

        $best = null;
        $bestScore = 0.0;
        $tied = false;
        foreach ($names as $column => $candidates) {
            if (isset($assigned[$column])) {
                continue;
            }
            $score = 0.0;
            foreach ($candidates as $candidate) {
                $length = max(strlen($candidate), strlen($normalized));
                if ($length === 0 || $length > 255) {
                    continue;
                }
                $score = max($score, 1 - levenshtein($candidate, $normalized) / $length);
            }
            if ($score > $bestScore) {
                [$best, $bestScore, $tied] = [$column, $score, false];
            } elseif ($score === $bestScore && $score > 0) {
                $tied = true;
            }
        }

        return $best !== null && !$tied && $bestScore >= self::FUZZY_THRESHOLD ? $best : null;
    }

    /**
     * @param  resource  $handle
     */
    private function flatten(SpreadsheetGridReader $reader, CrosstabProfile $profile, array $header, array $merges, $handle, ?callable $progress): array
    {
        $headerLastRow = max($header['rows']);
        $columnOf = array_flip($header['map']);
        $dimensionColumns = array_map(static fn (string $column): int => $columnOf[$column], $profile->dimensions());
        $dimensionLookup = array_flip($dimensionColumns);
        $ignoredColumns = array_keys($header['unknown']);
        $date1904 = $reader->usesDate1904();

        // Index data-area merges per column so each cell finds its anchor in one step.
        $intervals = [];
        $anchors = [];
        $verticalDimensionMerge = false;
        foreach ($merges as $merge) {
            [$firstColumn, $firstRow, $lastColumn, $lastRow] = $merge;
            if ($firstRow <= $headerLastRow) {
                continue;
            }
            for ($column = $firstColumn; $column <= $lastColumn; $column++) {
                $intervals[$column][] = $merge;
                if ($lastRow > $firstRow && isset($dimensionLookup[$column])) {
                    $verticalDimensionMerge = true;
                }
            }
            $anchors["{$firstColumn}:{$firstRow}"] = '';
        }
        foreach ($intervals as &$list) {
            usort($list, static fn (array $left, array $right): int => $left[1] <=> $right[1]);
        }
        unset($list);
        $pointers = array_fill_keys(array_keys($intervals), 0);
        // Merges are the source's own grouping; without them the blanks are the grouping.
        $fillDown = !$verticalDimensionMerge;

        fputcsv($handle, $profile->headers(), $profile->delimiter, '"', '');
        $stats = [
            'merge_filled' => 0,
            'blank_filled' => 0,
            'total_rows_skipped' => 0,
            'dates_converted' => 0,
            'formulas_resolved' => 0,
            'blank_dimensions_kept' => 0,
        ];
        $error = null;
        $unknownWithData = [];
        $previous = null;
        $previousValues = [];
        $previousRowNumber = null;
        $written = 0;
        $scanned = 0;

        foreach ($reader->rows() as [$rowNumber, $values, $formulas]) {
            if ($rowNumber <= $headerLastRow) {
                continue;
            }
            $scanned++;

            foreach ($formulas as $column => $formula) {
                $resolved = $this->resolveFormula($formula, $column, $rowNumber, $values, $previousValues, $previousRowNumber);
                if ($resolved === null) {
                    throw new RuntimeException(
                        'Sel '.Coordinate::stringFromColumnIndex($column + 1).$rowNumber
                        .' berisi formula tanpa nilai tersimpan. Buka file di Excel, simpan ulang, lalu upload kembali.'
                    );
                }
                $values[$column] = $resolved;
                $stats['formulas_resolved']++;
            }

            foreach ($values as $column => $value) {
                if (array_key_exists("{$column}:{$rowNumber}", $anchors)) {
                    $anchors["{$column}:{$rowNumber}"] = $value;
                }
            }

            $hasValue = false;
            foreach ($values as $value) {
                if (!self::isBlank($value)) {
                    $hasValue = true;
                    break;
                }
            }
            if (!$hasValue) {
                continue;
            }

            if ($this->isTotalRow($values, $dimensionColumns)) {
                $stats['total_rows_skipped']++;
                continue;
            }

            foreach ($intervals as $column => $list) {
                $pointer = $pointers[$column];
                while ($pointer < count($list) && $list[$pointer][3] < $rowNumber) {
                    $pointer++;
                }
                $pointers[$column] = $pointer;
                $merge = $list[$pointer] ?? null;
                if ($merge === null || $merge[1] > $rowNumber || ($merge[1] === $rowNumber && $merge[0] === $column)) {
                    continue;
                }
                // Measures inside a merge stay as the source left them: never borrowed from the anchor row.
                if (!isset($dimensionLookup[$column]) || !self::isBlank($values[$column] ?? '')) {
                    continue;
                }
                if ($merge[0] !== $column && $profile->valueMode === CrosstabProfile::VALUES_VALIDATED) {
                    $range = Coordinate::stringFromColumnIndex($merge[0] + 1).$merge[1].':'.Coordinate::stringFromColumnIndex($merge[2] + 1).$merge[3];
                    $error ??= "Merge lintas kolom {$profile->label} tidak didukung: {$range}.";
                    continue;
                }
                $values[$column] = $anchors["{$merge[0]}:{$merge[1]}"] ?? '';
                $stats['merge_filled']++;
            }

            if ($fillDown && $previous !== null) {
                foreach ($dimensionColumns as $position => $column) {
                    if (!self::isBlank($values[$column] ?? '')) {
                        continue;
                    }
                    // Fill only while the outer groups to the left are unchanged.
                    $sameGroup = true;
                    for ($left = 0; $left < $position; $left++) {
                        if (($values[$dimensionColumns[$left]] ?? '') !== $previous[$left]) {
                            $sameGroup = false;
                            break;
                        }
                    }
                    if ($sameGroup && !self::isBlank($previous[$position])) {
                        $values[$column] = $previous[$position];
                        $stats['blank_filled']++;
                    }
                }
            }

            $output = [];
            foreach ($profile->columns as $index => $canonical) {
                $value = (string) ($values[$columnOf[$canonical]] ?? '');
                if (self::isBlank($value)) {
                    $isMeasure = $index >= $profile->dimensionCount;
                    if ($isMeasure ? $profile->rejectBlankMeasures : $profile->rejectBlankDimensions) {
                        $reason = match (true) {
                            $isMeasure => 'kosong; nilai measure tidak pernah diambil dari baris lain',
                            $fillDown => 'kosong dan tidak memiliki nilai induk di atasnya',
                            default => 'kosong di luar merge sumber',
                        };
                        $error ??= "Baris {$profile->label} {$rowNumber}: kolom {$canonical} {$reason}.";
                    } elseif (!$isMeasure) {
                        $stats['blank_dimensions_kept']++;
                    }
                    $output[] = $profile->valueMode === CrosstabProfile::VALUES_VALIDATED ? '' : $value;
                    continue;
                }

                if ($index === 0) {
                    [$value, $converted, $dateError] = $this->finalizeDate($profile, $value, $date1904, $rowNumber);
                    $stats['dates_converted'] += $converted ? 1 : 0;
                    $error ??= $dateError;
                } elseif ($profile->valueMode === CrosstabProfile::VALUES_VALIDATED) {
                    [$value, $numberError] = $this->finalizeValidated($profile, $canonical, $index, $value, $rowNumber);
                    $error ??= $numberError;
                }
                $output[] = $value;
            }

            foreach ($ignoredColumns as $column) {
                if (!self::isBlank($values[$column] ?? '')) {
                    $unknownWithData[$column] = '`'.$header['unknown'][$column].'`';
                }
            }

            fputcsv($handle, $output, $profile->delimiter, '"', '');
            $written++;
            $previous = array_map(static fn (int $column): string => (string) ($values[$column] ?? ''), $dimensionColumns);
            $previousValues = $values;
            $previousRowNumber = $rowNumber;

            if ($progress !== null && $scanned % self::PROGRESS_EVERY_ROWS === 0) {
                $progress(['rows_done' => $scanned, 'message' => "Menormalisasi crosstab {$profile->label}..."]);
            }
        }

        return [
            'rows' => $written,
            'stats' => $stats,
            'error' => $error,
            'unknown_with_data' => array_values($unknownWithData),
            'fill_down' => $fillDown,
        ];
    }

    private function resolveFormula(string $formula, int $column, int $rowNumber, array $values, array $previousValues, ?int $previousRowNumber): ?string
    {
        // Only the "=↑" reference that Ctrl+Enter writes can be resolved without a calculation engine.
        if (!preg_match('/^=?\$?([A-Z]{1,3})\$?(\d+)$/i', trim($formula), $matches)) {
            return null;
        }
        $referenceColumn = Coordinate::columnIndexFromString(strtoupper($matches[1])) - 1;
        $referenceRow = (int) $matches[2];
        if ($referenceRow === $previousRowNumber && array_key_exists($referenceColumn, $previousValues)) {
            return (string) $previousValues[$referenceColumn];
        }
        if ($referenceRow === $rowNumber && $referenceColumn !== $column && array_key_exists($referenceColumn, $values)) {
            return (string) $values[$referenceColumn];
        }

        return null;
    }

    private function isTotalRow(array $values, array $dimensionColumns): bool
    {
        foreach ($dimensionColumns as $column) {
            $value = trim(str_replace("\u{00A0}", ' ', (string) ($values[$column] ?? '')));
            if ($value !== '') {
                return preg_match(self::TOTAL_LABEL, $value) === 1;
            }
        }

        return false;
    }

    /**
     * @return array{0:string,1:bool,2:?string} [value, converted, error]
     */
    private function finalizeDate(CrosstabProfile $profile, string $value, bool $date1904, int $rowNumber): array
    {
        $error = "Baris {$profile->label} {$rowNumber}: tanggal periode tidak valid.";
        $text = trim(str_replace("\u{00A0}", ' ', $value));
        if ($date1904 && is_numeric($text)) {
            $text = (string) ((float) $text + 1462);
        }

        if ($profile->valueMode === CrosstabProfile::VALUES_SOURCE) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$profile->dateFormat, $value);
            if ($parsed !== false && $parsed->format($profile->dateFormat) === $value) {
                return [$value, false, null];
            }
        }

        $normalized = StrictDateParser::normalize($text);
        // "31 Februari" must not roll over into March.
        $rolledOver = $normalized !== null
            && preg_match('/^(\d{1,2})\s+[a-z]+\s+(\d{4})$/i', $text, $parts)
            && ((int) substr($normalized, 8, 2) !== (int) $parts[1] || (int) substr($normalized, 0, 4) !== (int) $parts[2]);
        if ($normalized === null || $rolledOver) {
            return [$value, false, $error];
        }

        $formatted = (new DateTimeImmutable($normalized))->format($profile->dateFormat);

        return [$formatted, $profile->valueMode === CrosstabProfile::VALUES_SOURCE && $formatted !== $value, null];
    }

    /**
     * @return array{0:string,1:?string} [value, error]
     */
    private function finalizeValidated(CrosstabProfile $profile, string $column, int $index, string $value, int $rowNumber): array
    {
        $value = trim(str_replace("\u{00A0}", ' ', $value));
        if ($index < $profile->dimensionCount && !in_array($column, $profile->numericDimensions, true)) {
            return [$value, null];
        }
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return [$value, "Baris {$profile->label} {$rowNumber}: kolom {$column} bukan angka yang valid."];
        }
        $integer = in_array($column, $profile->integerColumns, true);
        if ($integer && ((float) $value < 0 || floor((float) $value) !== (float) $value)) {
            return [$value, "Baris {$profile->label} {$rowNumber}: kolom {$column} harus bilangan bulat nonnegatif."];
        }

        return [number_format((float) $value, $integer ? 0 : 2, '.', ''), null];
    }

    private function describeMissingHeader(CrosstabProfile $profile, array $header): string
    {
        $message = "Header file {$profile->label} tidak lengkap atau tidak dikenali.";
        if (($header['map'] ?? []) !== []) {
            $message .= ' Baris '.$header['row'].' paling mirip header, tetapi kolom berikut tidak ditemukan: '
                .implode(', ', $header['missing']).'.';
        }

        return $message;
    }

    private function summarize(CrosstabProfile $profile, array $header, array $stats, bool $fillDown): array
    {
        $notices = [];
        $skippedAbove = $header['rows'][0] - $header['first_row'];
        $notices[] = 'Header dikenali di baris '.implode('–', $header['rows'])
            .($skippedAbove > 0 ? " ({$skippedAbove} baris judul/filter di atasnya dilewati)." : '.');
        foreach ($header['unlabeled'] as $column => $canonical) {
            $notices[] = 'Kolom '.Coordinate::stringFromColumnIndex($column + 1)." tanpa judul dinamai `{$canonical}`.";
        }
        if ($header['renamed'] !== []) {
            $pairs = [];
            foreach ($header['renamed'] as $label => $canonical) {
                $pairs[] = "`{$label}` → {$canonical}";
            }
            $notices[] = 'Header disesuaikan: '.implode(', ', $pairs).'.';
        }
        if ($stats['merge_filled'] > 0) {
            $notices[] = number_format($stats['merge_filled'], 0, ',', '.').' sel merge di-unmerge dan diisi nilai induknya.';
        }
        if ($stats['blank_filled'] > 0) {
            $notices[] = number_format($stats['blank_filled'], 0, ',', '.').' sel kosong diisi dari baris di atasnya (Ctrl+G → =↑ → Ctrl+Enter).';
        }
        if ($stats['formulas_resolved'] > 0) {
            $notices[] = number_format($stats['formulas_resolved'], 0, ',', '.').' formula referensi sel (=↑) dibaca sebagai nilainya.';
        }
        if ($stats['total_rows_skipped'] > 0) {
            $notices[] = number_format($stats['total_rows_skipped'], 0, ',', '.').' baris Total/Subtotal dilewati agar tidak terhitung ganda.';
        }
        if ($stats['dates_converted'] > 0) {
            $notices[] = 'Tanggal periode dikonversi ke format `'.(new DateTimeImmutable('2026-01-31'))->format($profile->dateFormat).'`.';
        }
        if ($stats['blank_dimensions_kept'] > 0) {
            $notices[] = number_format($stats['blank_dimensions_kept'], 0, ',', '.').' sel dimensi tetap kosong karena tidak ada nilai induk yang sah.';
        }

        return [
            'profile' => $profile->key,
            'mode' => $fillDown ? 'fill_down' : 'merge',
            'header_rows' => $header['rows'],
            'stats' => $stats,
            'notices' => $notices,
        ];
    }

    private static function isBlank(mixed $value): bool
    {
        return trim(str_replace("\u{00A0}", ' ', (string) $value)) === '';
    }
}
