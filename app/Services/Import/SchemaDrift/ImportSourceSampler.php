<?php

namespace App\Services\Import\SchemaDrift;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;

/**
 * Reads the header row and a sample of data rows from an import source (CSV,
 * TXT or Excel). The header row is located by content — the row whose labels
 * map to the most target-table columns — so report preambles such as
 * "Laporan Nominatif PH, Periode Data : 18/10/2026" are skipped and kept as
 * metadata.
 */
class ImportSourceSampler
{
    private const HEADER_SCAN_ROWS = 40;
    private const DEFAULT_SAMPLE_ROWS = 600;
    private const DELIMITERS = [',', ';', "\t", '|'];

    /**
     * @param callable(array<int, string>): array<int, string> $canonicalize maps raw labels to canonical column names
     * @param array<int, string> $tableColumnsLower
     */
    public function sample(string $path, callable $canonicalize, array $tableColumnsLower, array $options = []): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File sumber tidak ditemukan atau tidak dapat dibaca.');
        }

        $sampleSize = max(50, (int) ($options['sample_rows'] ?? self::DEFAULT_SAMPLE_ROWS));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $records = in_array($extension, ['xlsx', 'xls', 'xlsm'], true)
            ? $this->readSpreadsheet($path, self::HEADER_SCAN_ROWS + $sampleSize)
            : $this->readDelimited($path, (string) ($options['delimiter'] ?? ''), self::HEADER_SCAN_ROWS + $sampleSize);

        $headerIndex = $this->locateHeader($records['rows'], $canonicalize, $tableColumnsLower, $options['header_index'] ?? null);
        if ($headerIndex === null) {
            throw new RuntimeException('Baris header tidak dapat dikenali dari isi file.');
        }

        $headers = $this->trimTrailingEmpty(array_map(static fn ($value): string => trim((string) $value), $records['rows'][$headerIndex]));
        $headers[0] = isset($headers[0]) ? (preg_replace('/^\x{FEFF}/u', '', $headers[0]) ?? $headers[0]) : '';
        $headerCount = count($headers);

        $metadata = [];
        for ($i = 0; $i < $headerIndex; $i++) {
            $text = trim(implode(' ', array_map(static fn ($value): string => trim((string) $value), $records['rows'][$i])));
            if ($text !== '') {
                $metadata[] = mb_substr($text, 0, 300);
            }
        }

        $rows = [];
        $misaligned = 0;
        $rowCount = count($records['rows']);
        for ($i = $headerIndex + 1; $i < $rowCount && count($rows) < $sampleSize; $i++) {
            $row = $records['rows'][$i];
            if ($this->isBlank($row)) {
                continue;
            }

            $row = $this->trimTrailingEmpty($row);
            if (count($row) > $headerCount) {
                $misaligned++;
            }
            $rows[] = array_slice(array_pad($row, $headerCount, null), 0, $headerCount);
        }

        return [
            'headers' => $headers,
            'canonical_headers' => array_values($canonicalize($headers)),
            'rows' => $rows,
            'header_index' => $headerIndex,
            'delimiter' => $records['delimiter'],
            'metadata' => $metadata,
            'misaligned_rows' => $misaligned,
            'format' => $records['format'],
        ];
    }

    private function locateHeader(array $rows, callable $canonicalize, array $tableColumnsLower, mixed $hint): ?int
    {
        $lookup = array_fill_keys($tableColumnsLower, true);
        $best = null;
        $bestScore = 0;

        foreach (array_slice($rows, 0, self::HEADER_SCAN_ROWS, true) as $index => $row) {
            $labels = $this->trimTrailingEmpty(array_map(static fn ($value): string => trim((string) $value), $row));
            if (count($labels) < 2) {
                continue;
            }

            $score = 0;
            foreach ($canonicalize($labels) as $canonical) {
                if (isset($lookup[strtolower((string) $canonical)])) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $index;
            }
        }

        if ($best !== null && $bestScore >= min(2, max(1, count($tableColumnsLower)))) {
            return (int) $best;
        }

        if (is_numeric($hint) && isset($rows[(int) $hint])) {
            return (int) $hint;
        }

        return $this->locateStructuralHeader($rows);
    }

    /**
     * Positional exports (SSRS "textbox10, textbox11 …") carry labels that never
     * match column names. Their header is the first label-like row that is
     * immediately followed by data rows of the same width.
     */
    private function locateStructuralHeader(array $rows): ?int
    {
        $window = array_slice($rows, 0, self::HEADER_SCAN_ROWS, true);
        $indexes = array_keys($window);

        foreach ($indexes as $position => $index) {
            $labels = $this->trimTrailingEmpty(array_map(static fn ($value): string => trim((string) $value), $rows[$index]));
            $filled = array_values(array_filter($labels, static fn (string $label): bool => $label !== ''));
            if (count($filled) < 3 || count($filled) < count($labels) * 0.8) {
                continue;
            }

            $numeric = count(array_filter($filled, static fn (string $label): bool => preg_match('/^[\d.,\-\/\s:]+$/', $label) === 1));
            if ($numeric > count($filled) * 0.2 || count(array_unique(array_map('strtolower', $filled))) !== count($filled)) {
                continue;
            }

            $width = count($labels);
            $followers = 0;
            foreach (array_slice($indexes, $position + 1, 3) as $nextIndex) {
                $next = $this->trimTrailingEmpty((array) $rows[$nextIndex]);
                if ($next === []) {
                    continue;
                }
                if (abs(count($next) - $width) <= 1) {
                    $followers++;
                }
            }

            if ($followers >= 1) {
                return (int) $index;
            }
        }

        return null;
    }

    private function readDelimited(string $path, string $delimiter, int $limit): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File sumber tidak dapat dibuka.');
        }

        try {
            if ($delimiter === '' || !in_array($delimiter, self::DELIMITERS, true)) {
                $delimiter = $this->detectDelimiter($handle);
            }

            $rows = [];
            while (count($rows) < $limit && ($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $row = array_map(fn ($value) => $this->toUtf8((string) $value), (array) $row);
                if ($rows === [] && isset($row[0])) {
                    $row[0] = preg_replace('/^\x{FEFF}/u', '', $row[0]) ?? $row[0];
                }
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return ['rows' => $rows, 'delimiter' => $delimiter, 'format' => 'delimited'];
    }

    private function detectDelimiter($handle): string
    {
        $lines = [];
        while (count($lines) < 30 && ($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }
        rewind($handle);

        $best = ',';
        $bestScore = -1;
        foreach (self::DELIMITERS as $candidate) {
            $counts = array_map(static fn (string $line): int => count(str_getcsv($line, $candidate, '"', '')), $lines);
            $score = $counts === [] ? 0 : max($counts);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function readSpreadsheet(string $path, int $limit): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter(new class($limit) implements IReadFilter {
            public function __construct(private readonly int $limit)
            {
            }

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= $this->limit;
            }
        });

        $spreadsheet = $reader->load($path);
        try {
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return [
            'rows' => array_map(static fn ($row): array => array_map(
                static fn ($value): string => $value === null ? '' : (is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value),
                (array) $row
            ), array_slice($rows, 0, $limit)),
            'delimiter' => null,
            'format' => 'spreadsheet',
        ];
    }

    private function isBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function trimTrailingEmpty(array $row): array
    {
        $row = array_values($row);
        while ($row !== [] && trim((string) end($row)) === '') {
            array_pop($row);
        }

        return $row;
    }

    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
