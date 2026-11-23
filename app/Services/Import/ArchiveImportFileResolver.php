<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

class ArchiveImportFileResolver
{
    private const DELIMITERS = [',', ';', '|', "\t"];
    private const IGNORED_TABLE_COLUMNS = ['id', 'uniqueid_namareport', 'created_at', 'updated_at'];
    private const NOISE_FILENAME_TOKENS = ['r', 'all', 'export', 'data', 'report', 'final'];
    private const MAX_INSPECT_LINES = 10;

    /**
     * Resolve the single intended CSV/TXT file from extracted archive entries.
     * Returns null when no file exists or multiple candidates are genuinely tied.
     *
     * @param  array<int, array{name?: string, path: string}>  $files
     * @return array{name: string, path: string}|null
     */
    public function resolve(array $files, ?object $reportData = null, ?string $tableName = null): ?array
    {
        $candidates = $this->normalizeCandidates($files);
        if ($candidates === []) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $candidates = $this->filterMacOsJunk($candidates);
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $candidates = $this->filterEmptyFiles($candidates);
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $resolvedTable = strtolower(trim((string) ($tableName ?: ($reportData->table_name ?? ''))));
        $resolvedReportName = strtolower(trim((string) ($reportData->nama_report ?? '')));
        $tableColumnLookup = $this->loadTableColumnLookup($resolvedTable);

        $scored = [];
        foreach ($candidates as $candidate) {
            $nameScore = $this->computeFilenameScore($candidate['name'], $resolvedTable, $resolvedReportName);
            $inspection = $this->inspectDelimitedCandidate($candidate['path'], $tableColumnLookup);
            $isCsv = strtolower((string) pathinfo($candidate['name'], PATHINFO_EXTENSION)) === 'csv' ? 1 : 0;

            $tuple = [
                ($tableColumnLookup !== [] && $inspection['header_matched_count'] > 0) ? 1 : 0,
                $inspection['has_data_row'] ? 1 : 0,
                (int) round($nameScore * 10000),
                (int) $inspection['header_matched_count'],
                (int) round($inspection['header_match_ratio'] * 10000),
                $inspection['looks_tabular'] ? 1 : 0,
                $isCsv,
            ];

            $scored[] = [
                'file' => $candidate,
                'tuple' => $tuple,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $this->compareTuples($b['tuple'], $a['tuple']));

        if (count($scored) >= 2 && $this->compareTuples($scored[0]['tuple'], $scored[1]['tuple']) === 0) {
            return null;
        }

        return $scored[0]['file'];
    }

    /**
     * @param  array<int, array{name?: string, path: string}>  $files
     * @return array<int, array{name: string, path: string}>
     */
    private function normalizeCandidates(array $files): array
    {
        $normalized = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = trim((string) ($file['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $name = trim((string) ($file['name'] ?? ''));
            $normalized[] = [
                'name' => $name !== '' ? $name : basename($path),
                'path' => $path,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $candidates
     * @return array<int, array{name: string, path: string}>
     */
    private function filterMacOsJunk(array $candidates): array
    {
        $filtered = array_values(array_filter($candidates, static function (array $candidate): bool {
            $normalizedPath = str_replace('\\', '/', $candidate['path']);
            $baseName = basename($normalizedPath);

            if (str_contains($normalizedPath, '/__MACOSX/') || str_starts_with($normalizedPath, '__MACOSX/')) {
                return false;
            }

            if (str_starts_with($candidate['name'], '._') || str_starts_with($baseName, '._')) {
                return false;
            }

            return true;
        }));

        return $filtered !== [] ? $filtered : $candidates;
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $candidates
     * @return array<int, array{name: string, path: string}>
     */
    private function filterEmptyFiles(array $candidates): array
    {
        $nonEmpty = array_values(array_filter($candidates, static function (array $candidate): bool {
            $path = $candidate['path'];

            return is_file($path) && ((int) @filesize($path)) > 0;
        }));

        return $nonEmpty !== [] ? $nonEmpty : $candidates;
    }

    /**
     * @return array<string, bool>
     */
    private function loadTableColumnLookup(string $tableName): array
    {
        if ($tableName === '') {
            return [];
        }

        try {
            $schema = DB::getSchemaBuilder();
            if (!$schema->hasTable($tableName)) {
                return [];
            }

            $ignored = array_fill_keys(self::IGNORED_TABLE_COLUMNS, true);
            $lookup = [];
            foreach ($schema->getColumnListing($tableName) as $column) {
                $normalized = strtolower(trim((string) $column));
                if ($normalized !== '' && !isset($ignored[$normalized])) {
                    $lookup[$normalized] = true;
                }
            }

            return $lookup;
        } catch (\Throwable) {
            return [];
        }
    }

    private function computeFilenameScore(string $name, string $resolvedTable, string $resolvedReportName): float
    {
        $stem = (string) pathinfo($name, PATHINFO_FILENAME);
        $rawFileTokens = $this->tokenize($stem);
        if ($rawFileTokens === []) {
            return 0.0;
        }

        $bestScore = 0.0;
        foreach ([$resolvedTable, $resolvedReportName] as $target) {
            if ($target === '') {
                continue;
            }

            $targetTokens = array_values(array_unique($this->tokenize($target)));
            if ($targetTokens === []) {
                continue;
            }

            $score = $this->scoreTokensAgainstTarget($rawFileTokens, $targetTokens);
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }

        return $bestScore;
    }

    /**
     * @param  array<int, string>  $rawFileTokens
     * @param  array<int, string>  $targetTokens
     */
    private function scoreTokensAgainstTarget(array $rawFileTokens, array $targetTokens): float
    {
        $targetLookup = array_fill_keys($targetTokens, true);
        $noiseLookup = array_fill_keys(self::NOISE_FILENAME_TOKENS, true);

        $nonDigitFileTokens = [];
        $filteredFileTokens = [];
        foreach ($rawFileTokens as $token) {
            $inTarget = isset($targetLookup[$token]);
            if (ctype_digit($token) && !$inTarget) {
                continue;
            }

            $nonDigitFileTokens[] = $token;

            if (isset($noiseLookup[$token]) && !$inTarget) {
                continue;
            }

            $filteredFileTokens[] = $token;
        }

        $uniqueFileTokens = array_values(array_unique($filteredFileTokens));
        if ($uniqueFileTokens === []) {
            return 0.0;
        }

        $matched = count(array_intersect($targetTokens, $uniqueFileTokens));
        if ($matched === 0) {
            return 0.0;
        }

        $recall = $matched / count($targetTokens);
        $precision = $matched / count($uniqueFileTokens);
        $f1 = (2.0 * $precision * $recall) / ($precision + $recall);

        $normalizedTarget = implode('_', $targetTokens);
        $normalizedStem = implode('_', $nonDigitFileTokens);
        if ($normalizedTarget !== '' && str_contains($normalizedStem, $normalizedTarget)) {
            $f1 += 0.05;
        }

        return $f1;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $value): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($tokens) ? $tokens : [];
    }

    /**
     * @param  array<string, bool>  $tableColumnLookup
     * @return array{has_data_row: bool, looks_tabular: bool, header_matched_count: int, header_match_ratio: float}
     */
    private function inspectDelimitedCandidate(string $path, array $tableColumnLookup): array
    {
        $empty = [
            'has_data_row' => false,
            'looks_tabular' => false,
            'header_matched_count' => 0,
            'header_match_ratio' => 0.0,
        ];

        if (!is_file($path)) {
            return $empty;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return $empty;
        }

        $nonEmptyLines = [];
        try {
            while (count($nonEmptyLines) < self::MAX_INSPECT_LINES && ($line = fgets($handle, 65536)) !== false) {
                $trimmed = trim($line, "\r\n");
                if (trim($trimmed) === '') {
                    continue;
                }
                $nonEmptyLines[] = $trimmed;
            }
        } finally {
            fclose($handle);
        }

        if ($nonEmptyLines === []) {
            return $empty;
        }

        $headerLine = $nonEmptyLines[0];
        $hasDataRow = count($nonEmptyLines) >= 2;
        $delimiter = $this->detectBestDelimiter($nonEmptyLines) ?? ',';
        $rawHeaders = str_getcsv($headerLine, $delimiter, '"', '\\');

        $normalizedHeaders = [];
        foreach ($rawHeaders as $header) {
            $clean = $this->normalizeHeaderName((string) $header);
            if ($clean !== '') {
                $normalizedHeaders[$clean] = true;
            }
        }

        $uniqueHeaders = array_keys($normalizedHeaders);
        $looksTabular = count($uniqueHeaders) >= 2;

        $headerMatchedCount = 0;
        if ($tableColumnLookup !== []) {
            foreach ($uniqueHeaders as $header) {
                if (isset($tableColumnLookup[$header])) {
                    $headerMatchedCount++;
                }
            }
        }

        $headerMatchRatio = $uniqueHeaders !== []
            ? $headerMatchedCount / count($uniqueHeaders)
            : 0.0;

        return [
            'has_data_row' => $hasDataRow,
            'looks_tabular' => $looksTabular,
            'header_matched_count' => $headerMatchedCount,
            'header_match_ratio' => $headerMatchRatio,
        ];
    }

    /**
     * @param  array<int, string>  $nonEmptyLines
     */
    private function detectBestDelimiter(array $nonEmptyLines): ?string
    {
        $headerLine = $nonEmptyLines[0];
        $firstDataLine = $nonEmptyLines[1] ?? null;

        $bestDelimiter = null;
        $bestRank = null;

        foreach (self::DELIMITERS as $candidate) {
            $headerCount = count(str_getcsv($headerLine, $candidate, '"', '\\'));
            if ($headerCount <= 1) {
                continue;
            }

            $consistentFirstData = 0;
            $distancePenalty = 0;
            if ($firstDataLine !== null) {
                $firstDataCount = count(str_getcsv($firstDataLine, $candidate, '"', '\\'));
                if ($firstDataCount === $headerCount) {
                    $consistentFirstData = 1;
                }
                $distancePenalty = abs($headerCount - $firstDataCount);
            }

            $rank = [
                $consistentFirstData,
                -$distancePenalty,
                $headerCount,
            ];

            if ($bestRank === null || $this->compareTuples($rank, $bestRank) > 0) {
                $bestRank = $rank;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function normalizeHeaderName(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = preg_replace('/[^A-Za-z0-9]+/', '_', trim($header)) ?? $header;

        return strtolower(trim($header, '_'));
    }

    /**
     * @param  array<int, int>  $left
     * @param  array<int, int>  $right
     */
    private function compareTuples(array $left, array $right): int
    {
        $count = max(count($left), count($right));
        for ($i = 0; $i < $count; $i++) {
            $a = $left[$i] ?? 0;
            $b = $right[$i] ?? 0;
            if ($a !== $b) {
                return $a <=> $b;
            }
        }

        return 0;
    }
}
