<?php

namespace App\Services\Import\SchemaDrift;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use Illuminate\Support\Facades\Cache;

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

    public function __construct(private ?XlsxStreamReader $xlsx = null)
    {
    }

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
        $sourceHash = hash_file('sha256', $path);
        if (!is_string($sourceHash)) {
            throw new RuntimeException('Identitas file sumber tidak dapat dibaca.');
        }
        $cacheKey = 'import-schema-sample:v4:'.hash('sha256', json_encode([$sourceHash, strtolower(pathinfo($path, PATHINFO_EXTENSION)), $sampleSize, $tableColumnsLower, $options['delimiter'] ?? null, $options['header_index'] ?? null]));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['canonical_headers'] = array_values($canonicalize($cached['headers']));

            return $cached;
        }
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

        // Header discovery is bounded; structural validation is not. In
        // particular, a populated field after the first 600 rows is still data.
        $scan = $this->scanSource($path, $records, $headerIndex, $headerCount, $sampleSize, $sourceHash);

        $sample = [
            'headers' => $headers,
            'canonical_headers' => array_values($canonicalize($headers)),
            'rows' => $scan['rows'],
            'profile_values' => $scan['profile_values'],
            'header_index' => $headerIndex,
            'delimiter' => $records['delimiter'],
            'metadata' => $metadata,
            'misaligned_rows' => $scan['structural_scan']['misaligned_rows'],
            'structural_scan' => $scan['structural_scan'],
            'format' => $records['format'],
        ];
        Cache::put($cacheKey, $sample, now()->addHours(2));

        return $sample;
    }

    private function scanSource(string $path, array $records, int $headerIndex, int $width, int $sampleSize, string $sourceHash): array
    {
        $rows = [];
        $witnesses = array_fill(0, $width, []);
        $nonEmpty = array_fill(0, $width, 0);
        $maxLength = array_fill(0, $width, 0);
        $total = 0;
        $misaligned = 0;
        $examples = [];
        $consume = function (array $row, int $number, bool $spreadsheet = false) use (&$rows, &$witnesses, &$nonEmpty, &$maxLength, &$total, &$misaligned, &$examples, $width, $sampleSize): void {
            if ($this->isBlank($row)) {
                return;
            }
            $total++;
            $actual = $spreadsheet ? max($width, count($this->trimTrailingEmpty($row))) : count($row);
            if ($actual !== $width) {
                $misaligned++;
                if (count($examples) < 8) {
                    $examples[] = ['row' => $number, 'expected' => $width, 'actual' => $actual];
                }
                // Never truncate a malformed record into valid-looking data.
                return;
            }
            $row = array_pad($row, $width, null);
            if (count($rows) < $sampleSize) {
                $rows[] = $row;
            }
            foreach ($nonEmpty as $index => $_) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value === '') {
                    continue;
                }
                $nonEmpty[$index]++;
                $length = mb_strlen($value);
                if ($length > $maxLength[$index]) {
                    $maxLength[$index] = $length;
                    $witnesses[$index]['longest'] = $value;
                }
                // A bounded set of witnesses retains late non-empty fields,
                // invalid numeric/date values, separator and date-order changes.
                $shape = $this->valueShape($value);
                if (!isset($witnesses[$index][$shape]) && (count($witnesses[$index]) < 24 || $shape === 'text')) {
                    $witnesses[$index][$shape] = $value;
                }
            }
        };

        if ($records['format'] === 'delimited') {
            $handle = $this->openDelimited($path);
            try {
                $number = 0;
                while (($row = fgetcsv($handle, 0, $records['delimiter'], '"', '')) !== false) {
                    if ($number++ <= $headerIndex) {
                        continue;
                    }
                    $consume(array_map(fn ($v) => $this->toUtf8((string) $v), $row), $number);
                }
            } finally {
                fclose($handle);
            }
        } else {
            $sheetName = $records['sheet_name'];
            $streamed = $this->xlsxReader()->streamRows(
                $path,
                $sheetName,
                $headerIndex + 2,
                $width,
                static function (int $number, array $row) use ($consume): void {
                    $consume($row, $number, true);
                }
            );

            if (!$streamed) {
                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly($sheetName);
                $info = array_values(array_filter($reader->listWorksheetInfo($path), static fn ($sheet) => $sheet['worksheetName'] === $sheetName))[0];
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max($width, (int) $info['totalColumns']));
                for ($start = $headerIndex + 2; $start <= (int) $info['totalRows']; $start += 2000) {
                    $end = min($start + 1999, (int) $info['totalRows']);
                    $reader->setReadFilter(new class($start, $end) implements IReadFilter {
                        public function __construct(private int $start, private int $end) {}
                        public function readCell($columnAddress, $row, $worksheetName = ''): bool
                        {
                            return $row >= $this->start && $row <= $this->end;
                        }
                    });
                    $book = $reader->load($path);
                    try {
                        foreach ($book->getSheetByName($sheetName)->rangeToArray('A'.$start.':'.$lastColumn.$end, null, false, false, true) as $number => $row) {
                            $consume(array_values($row), (int) $number, true);
                        }
                    } finally {
                        $book->disconnectWorksheets();
                    }
                }
            }
        }
        if (!hash_equals($sourceHash, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('File sumber berubah saat dipindai. Pindai ulang file sebelum mengambil keputusan.');
        }
        $values = [];
        foreach ($nonEmpty as $index => $_) {
            $values[$index] = array_merge(array_column($rows, $index), array_values(array_unique($witnesses[$index])));
        }

        return [
            'rows' => $rows,
            'profile_values' => $values,
            'structural_scan' => [
                'complete' => true, 'total_rows' => $total, 'misaligned_rows' => $misaligned,
                'examples' => $examples, 'column_non_empty' => $nonEmpty, 'column_max_length' => $maxLength,
                'source_fingerprint' => $sourceHash,
            ],
        ];
    }

    private function valueShape(string $value): string
    {
        if (preg_match('/\b(?:jan(?:uary|uari)?|feb(?:ruary|ruari)?|mar(?:ch|et)?|apr(?:il)?|may|mei|jun(?:e|i)?|jul(?:y|i)?|aug(?:ust)?|agu(?:stus)?|sep(?:tember)?|oct(?:ober)?|okt(?:ober)?|nov(?:ember)?|dec(?:ember)?|des(?:ember)?)\b/i', $value)
            && preg_match('/\d{4}/', $value)) {
            return 'date_text:'.(preg_match('/[AP]M/i', $value) ? '12h' : 'plain');
        }
        if (preg_match('/^[+-]?\d+$/D', $value)) {
            return 'int:'.min(20, strlen($value)).':'.(str_starts_with($value, '0') ? 'zero' : 'plain');
        }
        if (preg_match('/^[+-]?[\d.,]+$/D', $value)) {
            return 'number:'.substr((string) preg_replace('/\d+/', '#', $value), -14).':'.min(12, strlen((string) substr(strrchr($value, '.'), 1)));
        }
        if (preg_match('/^(\d{1,4})[-\/](\d{1,2})[-\/]\d{1,4}/', $value, $date)) {
            return 'date:'.strlen($date[1]).':'.((int) $date[1] > 12 ? 'd' : 'm').':'.((int) $date[2] > 12 ? 'd' : 'm').':'.(preg_match('/[AP]M/i', $value) ? '12h' : '24h');
        }

        return 'text';
    }

    private function openDelimited(string $path)
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File sumber tidak dapat dibuka.');
        }
        $prefix = (string) fread($handle, 4);
        rewind($handle);
        $encoding = match (true) {
            str_starts_with($prefix, "\xFF\xFE"), strlen($prefix) >= 2 && $prefix[1] === "\0" => 'UTF-16LE',
            str_starts_with($prefix, "\xFE\xFF"), strlen($prefix) >= 2 && $prefix[0] === "\0" => 'UTF-16BE',
            default => null,
        };
        if ($encoding !== null && @stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8', STREAM_FILTER_READ) === false) {
            fclose($handle);
            throw new RuntimeException('Konverter encoding '.$encoding.' untuk preview tidak tersedia.');
        }

        return $handle;
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
            // Decode the stream before CSV parsing: UTF-16 tabs/quotes contain
            // NUL bytes and cannot be repaired by converting individual cells.
            $prefix = (string) fread($handle, 4);
            rewind($handle);
            $encoding = match (true) {
                str_starts_with($prefix, "\xFF\xFE") => 'UTF-16LE',
                str_starts_with($prefix, "\xFE\xFF") => 'UTF-16BE',
                strlen($prefix) >= 2 && $prefix[1] === "\0" => 'UTF-16LE',
                strlen($prefix) >= 2 && $prefix[0] === "\0" => 'UTF-16BE',
                default => null,
            };
            if ($encoding !== null && @stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8', STREAM_FILTER_READ) === false) {
                throw new RuntimeException('Konverter encoding '.$encoding.' untuk preview tidak tersedia.');
            }
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
        $streamed = $this->xlsxReader()->readSampleRows($path, $limit);
        if ($streamed !== null) {
            return [
                'rows' => $streamed['rows'],
                'delimiter' => null,
                'format' => 'spreadsheet',
                'sheet_name' => $streamed['sheet_name'],
            ];
        }

        $reader = IOFactory::createReaderForFile($path);
        // Xlsx discards activeTab in data-only mode. This bounded read selects
        // the workbook's active sheet; the full scan loads only that sheet.
        $reader->setReadDataOnly(false);
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
        $sheetName = $spreadsheet->getActiveSheet()->getTitle();
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
            'sheet_name' => $sheetName,
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

    private function xlsxReader(): XlsxStreamReader
    {
        return $this->xlsx ??= new XlsxStreamReader;
    }
}
