<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

class CsvAutoRepairService
{
    private array $lastParseMeta = [
        'status' => 'normal',
        'reason' => null,
        'expected_columns' => null,
        'actual_columns' => null,
        'repaired' => false,
    ];

    public function getLastParseMeta(): array
    {
        return $this->lastParseMeta;
    }

    public function parseDailyLoanCsvRow(array $row, string $delimiter, ?int $expectedColumns = null): array
    {
        $this->lastParseMeta = [
            'status' => 'normal',
            'reason' => null,
            'expected_columns' => $expectedColumns,
            'actual_columns' => null,
            'repaired' => false,
        ];

        $candidates = $this->buildDailyLoanCsvParseCandidates($row, $delimiter);
        if ($candidates === []) {
            $this->lastParseMeta = [
                'status' => 'irrecoverable',
                'reason' => 'no_parse_candidate',
                'expected_columns' => $expectedColumns,
                'actual_columns' => count($row),
                'repaired' => false,
            ];

            return $row;
        }

        $bestCandidate = $row;
        $bestMeta = [
            'status' => count($row) === $expectedColumns ? 'normal' : 'irrecoverable',
            'reason' => count($row) === $expectedColumns ? null : 'field_count_mismatch',
            'expected_columns' => $expectedColumns,
            'actual_columns' => count($row),
            'repaired' => false,
        ];
        $bestDistance = $expectedColumns === null ? 0 : abs(count($row) - $expectedColumns);

        foreach ($candidates as $candidate) {
            $candidateMeta = [
                'status' => 'normal',
                'reason' => null,
                'expected_columns' => $expectedColumns,
                'actual_columns' => count($candidate['row']),
                'repaired' => !empty($candidate['repaired']),
            ];
            $candidateRow = $candidate['row'];

            if ($expectedColumns !== null && count($candidateRow) !== $expectedColumns) {
                $repaired = $this->repairDailyLoanParsedFields($candidateRow, $expectedColumns);
                $candidateRow = $repaired['row'];
                $candidateMeta['actual_columns'] = count($candidateRow);
                $candidateMeta['repaired'] = $candidateMeta['repaired'] || !empty($repaired['repaired']);
                $candidateMeta['reason'] = $repaired['reason'];
            }

            if ($expectedColumns !== null && count($candidateRow) === $expectedColumns) {
                $candidateMeta['status'] = $candidateMeta['repaired'] ? 'repaired' : 'normal';
                $candidateMeta['reason'] = $candidateMeta['repaired'] ? ($candidateMeta['reason'] ?? 'auto_repaired') : null;
                $this->lastParseMeta = $candidateMeta;
                return $candidateRow;
            }

            $distance = $expectedColumns === null ? 0 : abs(count($candidateRow) - $expectedColumns);
            if ($distance < $bestDistance || ($distance === $bestDistance && count($candidateRow) > count($bestCandidate))) {
                $bestCandidate = $candidateRow;
                $bestDistance = $distance;
                $bestMeta = [
                    'status' => 'irrecoverable',
                    'reason' => $candidateMeta['reason'] ?? 'field_count_mismatch',
                    'expected_columns' => $expectedColumns,
                    'actual_columns' => count($candidateRow),
                    'repaired' => $candidateMeta['repaired'],
                ];
            }
        }

        $this->lastParseMeta = $bestMeta;
        return $bestCandidate;
    }

    public function buildCsvRowPreview(array $row, string $delimiter): string
    {
        if (count($row) === 1 && isset($row[0]) && is_string($row[0])) {
            return Str::limit(trim($row[0]), 500);
        }

        $previewRow = array_slice($row, 0, 12);
        $encoded = json_encode($previewRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded !== false) {
            return Str::limit($encoded, 500);
        }

        return Str::limit(implode($delimiter, array_map(static fn ($value): string => (string) $value, $previewRow)), 500);
    }

    private function buildDailyLoanCsvParseCandidates(array $row, string $delimiter): array
    {
        $candidates = [];
        $seen = [];

        $pushCandidate = static function (array $candidateRow, bool $repaired = false) use (&$candidates, &$seen): void {
            $key = md5(json_encode(array_values($candidateRow), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $candidates[] = [
                'row' => array_values($candidateRow),
                'repaired' => $repaired,
            ];
        };

        $pushCandidate($row, false);

        if (count($row) === 1 && isset($row[0]) && is_string($row[0])) {
            $rawValue = trim($row[0]);
            if ($rawValue !== '' && str_contains($rawValue, $delimiter)) {
                foreach ($this->buildDailyLoanSerializedPayloadVariants($rawValue) as $variant) {
                    $parsed = str_getcsv($variant, $delimiter, '"', '\\');
                    if (count($parsed) > 1) {
                        $pushCandidate($parsed, true);
                    }
                }
            }
        }

        return $candidates;
    }

    private function buildDailyLoanSerializedPayloadVariants(string $payload): array
    {
        $variants = [];
        $seen = [];

        $pushVariant = static function (string $value) use (&$variants, &$seen): void {
            $normalized = trim($value);
            if ($normalized === '' || isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $variants[] = $normalized;
        };

        $pushVariant($payload);

        $normalizedSerializedPayload = $this->normalizeSerializedDailyLoanPayload($payload);
        if ($normalizedSerializedPayload !== null) {
            $pushVariant($normalizedSerializedPayload);
        }

        $pushVariant(str_replace('\,', ',', $payload));
        $pushVariant(str_replace(';"', '', $payload));
        $pushVariant(str_replace(['\,', ';"'], [',', ''], $payload));

        return $variants;
    }

    private function normalizeSerializedDailyLoanPayload(string $payload): ?string
    {
        $payload = trim($payload);
        if ($payload === '' || !str_starts_with($payload, '"')) {
            return null;
        }

        if (preg_match('/^"(.*)"(?:;*)$/s', $payload, $matches) !== 1) {
            return null;
        }

        $normalized = str_replace('""', '"', (string) ($matches[1] ?? ''));
        $normalized = trim($normalized);

        return $normalized === '' ? null : $normalized;
    }

    private function repairDailyLoanParsedFields(array $fields, int $expectedColumns): array
    {
        $current = array_values($fields);
        $repaired = false;

        foreach ($current as $index => $value) {
            if (!is_string($value)) {
                continue;
            }

            $normalizedValue = preg_replace('/^;"?/', '', $value);
            if ($normalizedValue !== $value) {
                $repaired = true;
                $current[$index] = $normalizedValue;
            }
        }

        if (count($current) < $expectedColumns) {
            foreach ($current as $index => $value) {
                if (!is_string($value) || !preg_match('/^(.*?),(\\d{4,6})$/', trim($value), $matches)) {
                    continue;
                }

                $left = trim((string) $matches[1]);
                $right = trim((string) $matches[2]);
                if ($left === '' || $right === '') {
                    continue;
                }

                array_splice($current, $index, 1, [$left, $right]);
                $repaired = true;
                if (count($current) >= $expectedColumns) {
                    break;
                }
            }
        }

        if (count($current) > $expectedColumns) {
            $merged = [];
            for ($index = 0; $index < count($current); $index++) {
                $partA = (string) $current[$index];
                $partB = (string) ($current[$index + 1] ?? '');
                $partC = (string) ($current[$index + 2] ?? '');

                if (
                    $index + 2 < count($current)
                    && preg_match('/^-?\d{1,3}$/', trim($partA))
                    && preg_match('/^\d{3}$/', trim($partB))
                    && preg_match('/^\d{1,3}(?:\.\d+)?(?:""|")?;?$/', trim($partC))
                ) {
                    $merged[] = trim($partA) . ',' . trim($partB) . ',' . trim($partC);
                    $index += 2;
                    $repaired = true;
                    continue;
                }

                if (
                    $index + 1 < count($current)
                    && preg_match('/^-?\d{1,3}$/', trim($partA))
                    && preg_match('/^\d{1,3}(?:\.\d+)?(?:""|")?;?$/', trim($partB))
                ) {
                    $merged[] = trim($partA) . ',' . trim($partB);
                    $index += 1;
                    $repaired = true;
                    continue;
                }

                $merged[] = $current[$index];
            }
            $current = $merged;
        }

        if (count($current) !== $expectedColumns) {
            return [
                'row' => $current,
                'repaired' => $repaired,
                'reason' => count($current) < $expectedColumns ? 'repair_failed_underflow' : 'repair_failed_overflow',
            ];
        }

        return [
            'row' => $current,
            'repaired' => $repaired,
            'reason' => $repaired ? 'auto_repaired' : null,
        ];
    }
}
