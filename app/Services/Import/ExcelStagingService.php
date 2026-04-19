<?php

namespace App\Services\Import;

class ExcelStagingService
{
    public function isExcelFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true);
    }

    public function findPython(): ?string
    {
        $candidates = array_values(array_filter(array_unique([
            env('IMPORT_PYTHON_BIN'),
            'python',
            'python3',
            'py',
            'C:\\Python313\\python.exe',
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe',
            'C:\\Users\\Danang\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            'C:\\Users\\Danang\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
            'C:\\Users\\Danang\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
            'C:\\Users\\Danang\\AppData\\Local\\Programs\\Python\\Python310\\python.exe',
        ])));

        foreach ($candidates as $cmd) {
            $output = @shell_exec(escapeshellcmd($cmd) . ' --version 2>&1');
            if ($output && str_contains($output, 'Python 3')) {
                return $cmd;
            }
        }

        return null;
    }

    public function detectExcelHeaderViaPython(string $path, ?string $scriptPath = null, string $configPrefix = 'excel_stage_init_'): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath ??= base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return $this->detectExcelHeaderViaNativeXlsx($path);
        }

        $configFile = storage_path('app/' . $configPrefix . uniqid() . '.json');
        file_put_contents($configFile, json_encode(['file_path' => $path], JSON_UNESCAPED_UNICODE));

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode init'
            . ' 2>' . $nullDevice;

        $output = @shell_exec($cmd);
        @unlink($configFile);

        if (!$output) {
            return null;
        }

        $result = json_decode(trim($output), true);
        if (!$result || ($result['status'] ?? '') !== 'ok') {
            return $this->detectExcelHeaderViaNativeXlsx($path);
        }

        return [
            'header_index' => (int) ($result['header_index'] ?? 0),
            'total_rows' => (int) ($result['total_rows'] ?? 0),
            'header_values' => (array) ($result['header_values'] ?? []),
        ];
    }

    public function createStagedCsvPath(string $directory, string $prefix): string
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . '_' . bin2hex(random_bytes(6)) . '.csv';
    }

    public function stageExcelToCsv(
        callable $send,
        string $sourcePath,
        int $headerIndex,
        array $normalizedHeaders,
        string $stagedCsvPath,
        ?string $scriptPath = null,
        string $configPrefix = 'excel_stage_'
    ): ?array {
        $pythonExe = $this->findPython();
        $scriptPath ??= base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return $this->stageExcelToCsvViaNativeXlsx(
                $send,
                $sourcePath,
                $headerIndex,
                $normalizedHeaders,
                $stagedCsvPath
            );
        }

        $configFile = storage_path('app/' . $configPrefix . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $sourcePath,
            'header_index' => $headerIndex,
            'normalized_headers' => $normalizedHeaders,
            'output_csv_path' => $stagedCsvPath,
        ], JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode stage';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($configFile);
            @unlink($stagedCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;

        $processLine = static function (string $line) use ($send, &$donePayload, &$pythonError): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                $send('progress', $data);
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python staging error tidak diketahui';
            }
        };

        while (true) {
            $status = proc_get_status($process);
            $chunk = fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $processLine($line);
                }
            }

            if (!$status['running']) {
                break;
            }

            usleep(50000);
        }

        $remaining = stream_get_contents($pipes[1]);
        if ($remaining) {
            $buffer .= $remaining;
            foreach (explode("\n", $buffer) as $line) {
                $processLine($line);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($configFile);

        if ($pythonError !== null || !$donePayload || !file_exists($stagedCsvPath)) {
            @unlink($stagedCsvPath);
            return $this->stageExcelToCsvViaNativeXlsx(
                $send,
                $sourcePath,
                $headerIndex,
                $normalizedHeaders,
                $stagedCsvPath
            );
        }

        return [
            'staged_csv_path' => $stagedCsvPath,
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
            'header_index' => 0,
            'headers' => array_values($normalizedHeaders),
        ];
    }

    private function stageExcelToCsvViaNativeXlsx(
        callable $send,
        string $sourcePath,
        int $headerIndex,
        array $normalizedHeaders,
        string $stagedCsvPath
    ): ?array {
        if (!$this->supportsNativeXlsxStreaming($sourcePath)) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            return null;
        }

        $outputHandle = @fopen($stagedCsvPath, 'wb');
        if ($outputHandle === false) {
            $zip->close();
            return null;
        }

        try {
            $worksheetEntry = $this->resolveFirstWorksheetEntry($zip);
            if ($worksheetEntry === null) {
                return null;
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $reader = new \XMLReader();
            if (!$reader->open('zip://' . str_replace('\\', '/', $sourcePath) . '#' . $worksheetEntry, null, LIBXML_NONET | LIBXML_COMPACT)) {
                return null;
            }

            fputcsv($outputHandle, array_values($normalizedHeaders), ',', '"', '\\');

            $headerCount = max(1, count($normalizedHeaders));
            $writtenRows = 0;
            $processedRows = 0;
            $progressEvery = 5000;
            $lastProgressAt = 0;

            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                    continue;
                }

                $rowNumber = max(1, (int) $reader->getAttribute('r'));
                $rowValues = $this->extractWorksheetRowValues($reader, $sharedStrings, $headerCount);
                $zeroBasedRowIndex = $rowNumber - 1;

                if ($zeroBasedRowIndex <= $headerIndex) {
                    continue;
                }

                if ($this->rowIsEmpty($rowValues)) {
                    continue;
                }

                $normalizedRowValues = array_map(function ($value) {
                    return $this->normalizeDecimalValueForStaging($value);
                }, $rowValues);

                fputcsv($outputHandle, $normalizedRowValues, ',', '"', '\\');
                $writtenRows++;
                $processedRows++;

                if (($processedRows - $lastProgressAt) >= $progressEvery) {
                    $lastProgressAt = $processedRows;
                    $send('progress', [
                        'percent' => 8,
                        'message' => 'Menyiapkan CSV staging dari Excel... (' . number_format($processedRows, 0, ',', '.') . ' baris)',
                        'rows_done' => $processedRows,
                        'total' => 0,
                        'speed' => 0,
                    ]);
                }
            }

            $reader->close();

            return [
                'staged_csv_path' => $stagedCsvPath,
                'total_rows' => $writtenRows,
                'header_index' => 0,
                'headers' => array_values($normalizedHeaders),
            ];
        } catch (\Throwable) {
            @unlink($stagedCsvPath);
            return null;
        } finally {
            fclose($outputHandle);
            $zip->close();
        }
    }

    private function supportsNativeXlsxStreaming(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx'
            && class_exists(\ZipArchive::class)
            && class_exists(\XMLReader::class);
    }

    private function detectExcelHeaderViaNativeXlsx(string $path): ?array
    {
        if (!$this->supportsNativeXlsxStreaming($path)) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            $worksheetEntry = $this->resolveFirstWorksheetEntry($zip);
            if ($worksheetEntry === null) {
                return null;
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $reader = new \XMLReader();
            if (!$reader->open('zip://' . str_replace('\\', '/', $path) . '#' . $worksheetEntry, null, LIBXML_NONET | LIBXML_COMPACT)) {
                return null;
            }

            $bestHeaderRowIndex = null;
            $bestHeaderValues = [];
            $bestFilledCells = -1;
            $highestRowSeen = 0;

            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                    continue;
                }

                $rowNumber = max(1, (int) $reader->getAttribute('r'));
                $highestRowSeen = max($highestRowSeen, $rowNumber);

                if ($rowNumber > 50) {
                    break;
                }

                $rowValues = $this->extractWorksheetRowValues($reader, $sharedStrings, 256);
                $filledCells = 0;
                foreach ($rowValues as $value) {
                    if ($value !== null && trim((string) $value) !== '') {
                        $filledCells++;
                    }
                }

                if ($filledCells > $bestFilledCells) {
                    $bestFilledCells = $filledCells;
                    $bestHeaderRowIndex = $rowNumber - 1;
                    $bestHeaderValues = array_values(array_map(
                        static fn ($value) => $value === null ? '' : trim((string) $value),
                        array_slice($rowValues, 0, max(1, $filledCells))
                    ));
                }
            }

            $reader->close();

            if ($bestHeaderRowIndex === null || $bestHeaderValues === []) {
                return null;
            }

            $totalRows = $this->resolveWorksheetTotalRows($zip, $worksheetEntry);
            if ($totalRows <= 0) {
                $totalRows = $highestRowSeen;
            }

            return [
                'header_index' => $bestHeaderRowIndex,
                'total_rows' => $totalRows,
                'header_values' => $bestHeaderValues,
            ];
        } finally {
            $zip->close();
        }
    }

    public function extractPreviewViaNativeXlsx(string $path, int $maxPreviewRows = 100): ?array
    {
        if (!$this->supportsNativeXlsxStreaming($path)) {
            return null;
        }

        $headerMeta = $this->detectExcelHeaderViaNativeXlsx($path);
        if ($headerMeta === null) {
            return null;
        }

        $headerIndex = (int) ($headerMeta['header_index'] ?? 0);
        $headers = array_values((array) ($headerMeta['header_values'] ?? []));
        if ($headers === []) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            $worksheetEntry = $this->resolveFirstWorksheetEntry($zip);
            if ($worksheetEntry === null) {
                return null;
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $reader = new \XMLReader();
            if (!$reader->open('zip://' . str_replace('\\', '/', $path) . '#' . $worksheetEntry, null, LIBXML_NONET | LIBXML_COMPACT)) {
                return null;
            }

            $headerCount = count($headers);
            $previewRows = [];

            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                    continue;
                }

                $rowNumber = max(1, (int) $reader->getAttribute('r'));
                $rowValues = $this->extractWorksheetRowValues($reader, $sharedStrings, $headerCount);
                $zeroBasedRowIndex = $rowNumber - 1;

                if ($zeroBasedRowIndex <= $headerIndex) {
                    continue;
                }

                if ($this->rowIsEmpty($rowValues)) {
                    continue;
                }

                $mappedRow = [];
                foreach ($headers as $index => $headerLabel) {
                    $mappedRow[$headerLabel] = $rowValues[$index] ?? null;
                }

                $previewRows[] = $mappedRow;
                if (count($previewRows) >= $maxPreviewRows) {
                    break;
                }
            }

            $reader->close();

            return [
                'header_index' => $headerIndex,
                'headers' => $headers,
                'preview_rows' => $previewRows,
                'total_rows' => (int) ($headerMeta['total_rows'] ?? 0),
            ];
        } finally {
            $zip->close();
        }
    }

    private function resolveWorksheetTotalRows(\ZipArchive $zip, string $worksheetEntry): int
    {
        $worksheetXml = $zip->getFromName($worksheetEntry);
        if (!is_string($worksheetXml) || $worksheetXml === '') {
            return 0;
        }

        if (preg_match('/<dimension[^>]*ref="[^:"]+:([A-Z]+)(\d+)"/i', $worksheetXml, $matches)) {
            return (int) ($matches[2] ?? 0);
        }

        if (preg_match('/<dimension[^>]*ref="[A-Z]+(\d+)"/i', $worksheetXml, $matches)) {
            return (int) ($matches[1] ?? 0);
        }

        return 0;
    }

    private function resolveFirstWorksheetEntry(\ZipArchive $zip): ?string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($workbookXml) || !is_string($relationsXml)) {
            return null;
        }

        $workbook = @simplexml_load_string($workbookXml);
        $relations = @simplexml_load_string($relationsXml);
        if (!$workbook || !$relations) {
            return null;
        }

        $relationMap = [];
        foreach ($relations->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationMap[(string) ($attributes['Id'] ?? '')] = (string) ($attributes['Target'] ?? '');
        }

        $namespaces = $workbook->getNamespaces(true);
        $sheets = isset($namespaces['']) ? $workbook->children($namespaces[''])->sheets : $workbook->sheets;
        if (!$sheets) {
            return null;
        }

        foreach ($sheets->sheet as $sheet) {
            $sheetAttributes = $sheet->attributes($namespaces['r'] ?? null);
            $relationId = (string) ($sheetAttributes['id'] ?? '');
            $target = $relationMap[$relationId] ?? null;
            if (!$target) {
                continue;
            }

            $normalizedTarget = str_starts_with($target, '/xl/')
                ? ltrim($target, '/')
                : 'xl/' . ltrim($target, '/');

            if ($zip->locateName($normalizedTarget) !== false) {
                return $normalizedTarget;
            }
        }

        return $zip->locateName('xl/worksheets/sheet1.xml') !== false
            ? 'xl/worksheets/sheet1.xml'
            : null;
    }

    private function readSharedStrings(\ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($sharedStringsXml) || trim($sharedStringsXml) === '') {
            return [];
        }

        $reader = new \XMLReader();
        if (!$reader->XML($sharedStringsXml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            return [];
        }

        $strings = [];
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'si') {
                continue;
            }

            $node = $reader->expand();
            if (!$node) {
                $strings[] = '';
                continue;
            }

            $text = '';
            $textNodes = $node->getElementsByTagName('t');
            foreach ($textNodes as $textNode) {
                $text .= $textNode->textContent;
            }

            $strings[] = $text;
        }

        $reader->close();

        return $strings;
    }

    private function extractWorksheetRowValues(\XMLReader $reader, array $sharedStrings, int $headerCount): array
    {
        $rowDepth = $reader->depth;
        $rowValues = array_fill(0, $headerCount, null);

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $rowDepth && $reader->name === 'row') {
                break;
            }

            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'c') {
                continue;
            }

            $cellReference = (string) $reader->getAttribute('r');
            $cellType = (string) $reader->getAttribute('t');
            $columnIndex = $this->columnReferenceToIndex($cellReference);

            if ($columnIndex < 0 || $columnIndex >= $headerCount) {
                $this->consumeCellNode($reader);
                continue;
            }

            $rowValues[$columnIndex] = $this->readCellValue($reader, $cellType, $sharedStrings);
        }

        return $rowValues;
    }

    private function consumeCellNode(\XMLReader $reader): void
    {
        $cellDepth = $reader->depth;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $cellDepth && $reader->name === 'c') {
                break;
            }
        }
    }

    private function readCellValue(\XMLReader $reader, string $cellType, array $sharedStrings): ?string
    {
        $cellDepth = $reader->depth;
        $value = null;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $cellDepth && $reader->name === 'c') {
                break;
            }

            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'v') {
                $value = $reader->readString();
                continue;
            }

            if ($reader->name === 'is') {
                $inlineNode = $reader->expand();
                if ($inlineNode) {
                    $text = '';
                    $textNodes = $inlineNode->getElementsByTagName('t');
                    foreach ($textNodes as $textNode) {
                        $text .= $textNode->textContent;
                    }
                    $value = $text;
                }
            }
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($cellType === 's') {
            $index = (int) $value;
            return array_key_exists($index, $sharedStrings) ? (string) $sharedStrings[$index] : null;
        }

        return (string) $value;
    }

    private function columnReferenceToIndex(string $cellReference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            return -1;
        }

        $letters = strtoupper((string) ($matches[1] ?? ''));
        $index = 0;
        $length = strlen($letters);

        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(-1, $index - 1);
    }

    private function normalizeDecimalValueForStaging($value): ?string
    {
        if ($value === null || !is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        // Cek apakah terlihat seperti angka dengan ribuan (misal "219,000.00" atau "219.000,00")
        // Minimal mengandung satu koma dan angka
        if (str_contains($trimmed, ',') && preg_match('/[0-9]/', $trimmed)) {
            $filtered = preg_replace('/[^0-9,\.\-]/', '', $trimmed);
            if ($filtered === '') {
                return $trimmed;
            }

            $hasComma = str_contains($filtered, ',');
            $hasDot = str_contains($filtered, '.');

            if ($hasComma && $hasDot) {
                if (strrpos($filtered, ',') > strrpos($filtered, '.')) {
                    $filtered = str_replace('.', '', $filtered);
                    $filtered = str_replace(',', '.', $filtered);
                } else {
                    $filtered = str_replace(',', '', $filtered);
                }
            } elseif ($hasComma) {
                $parts = explode(',', $filtered);
                $lastPart = end($parts);
                if (count($parts) > 2 || strlen((string) $lastPart) === 3) {
                    $filtered = str_replace(',', '', $filtered);
                } else {
                    $filtered = str_replace(',', '.', $filtered);
                }
            }

            if (is_numeric($filtered)) {
                return number_format((float) $filtered, 2, '.', '');
            }
        }

        return $value;
    }

    private function rowIsEmpty(array $rowValues): bool
    {
        foreach ($rowValues as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
