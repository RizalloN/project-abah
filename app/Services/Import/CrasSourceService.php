<?php

namespace App\Services\Import;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

class CrasSourceService
{
    public const TABLE = 'cras';
    public const BRANCH_INDEX = 1;

    public const SOURCE_HEADERS = [
        'Month, Day, Year of Posisi',
        'Ket Kanca',
        'BR#',
        'Ket Unit Kerja',
        'Status Rekening',
        'Segmen',
        'Produk',
        'Loan Type',
        'Sektor Ekonomi',
        'Sub Sektor Ekonomi',
        'Tahun Realisasi',
        'Ket Produk Tiering',
        'Kualitas Bulan Lalu',
        'Kualitas',
        'Flag Movement Kualitas',
        'Detail Movement Kualitas',
        'Kol Adk',
        'Flag Restruk',
        'Accint',
        'Baki Debet',
        'Biaya CKPN',
        'Ckpn Mo',
        'Denda',
        'Jumlah Debitur',
        'Jumlah Rekening',
        'Nilai Tercatat',
        'Plafond',
        'Realisasi PH',
        'Recovery Total',
        'Saldo PH',
        'Tunggakan Bunga',
        'Tunggakan Kecil',
        'Tunggakan Pokok',
    ];

    public const SOURCE_COLUMNS = [
        'month_day_year_of_posisi',
        'ket_kanca',
        'br_number',
        'ket_unit_kerja',
        'status_rekening',
        'segmen',
        'produk',
        'loan_type',
        'sektor_ekonomi',
        'sub_sektor_ekonomi',
        'tahun_realisasi',
        'ket_produk_tiering',
        'kualitas_bulan_lalu',
        'kualitas',
        'flag_movement_kualitas',
        'detail_movement_kualitas',
        'kol_adk',
        'flag_restruk',
        'accint',
        'baki_debet',
        'biaya_ckpn',
        'ckpn_mo',
        'denda',
        'jumlah_debitur',
        'jumlah_rekening',
        'nilai_tercatat',
        'plafond',
        'realisasi_ph',
        'recovery_total',
        'saldo_ph',
        'tunggakan_bunga',
        'tunggakan_kecil',
        'tunggakan_pokok',
    ];

    private const PREVIEW_ROWS = 100;
    private const PROGRESS_EVERY_ROWS = 25000;

    public function inspect(string $path, ?callable $progress = null): array
    {
        $previewRows = [];
        $branchCounts = [];
        $branchSamples = [];
        $totalRows = 0;
        $period = null;
        $periodSource = null;
        $this->iterate($path, function (array $row, int $sourceRow) use (
            &$previewRows,
            &$branchCounts,
            &$branchSamples,
            &$totalRows,
            &$period,
            &$periodSource,
            $progress
        ): void {
            $rowPeriod = $this->parseSourcePeriod($row[0]);
            if ($period === null) {
                $period = $rowPeriod;
                $periodSource = $row[0];
            } elseif ($period !== $rowPeriod) {
                throw new \RuntimeException(
                    "File CRAS memuat lebih dari satu periode pada baris sumber {$sourceRow}."
                );
            }

            $branch = $row[self::BRANCH_INDEX];
            $branchCounts[$branch] = ($branchCounts[$branch] ?? 0) + 1;
            if (count($branchSamples[$branch] ?? []) < self::PREVIEW_ROWS) {
                $branchSamples[$branch][] = [
                    'source_row' => $sourceRow,
                    'row' => $row,
                ];
            }

            if (count($previewRows) < self::PREVIEW_ROWS) {
                $previewRows[] = $row;
            }

            $totalRows++;
            if ($progress !== null && $totalRows % self::PROGRESS_EVERY_ROWS === 0) {
                $progress([
                    'rows_done' => $totalRows,
                    'percent' => min(95, max(1, (int) round($this->activeProgressRatio * 95))),
                    'message' => 'Memvalidasi struktur dan cabang CRAS...',
                ]);
            }
        });

        if ($totalRows === 0 || $period === null) {
            throw new \RuntimeException('File CRAS tidak memiliki baris data.');
        }

        $branches = array_keys($branchCounts);
        usort($branches, static fn (string $left, string $right): int => strnatcasecmp($left, $right));

        return [
            'headers' => self::SOURCE_HEADERS,
            'preview_rows' => $previewRows,
            'branch_values' => $branches,
            'branch_counts' => $branchCounts,
            'branch_samples' => $branchSamples,
            'total_rows' => $totalRows,
            'period' => $period,
            'period_source' => $periodSource,
            'source_size' => (int) (@filesize($path) ?: 0),
            'source_mtime' => (int) (@filemtime($path) ?: 0),
        ];
    }

    public function stageForImport(
        string $sourcePath,
        string $stagedPath,
        array $selectedBranches,
        string $expectedPeriod,
        ?callable $progress = null
    ): array {
        $selectedLookup = array_fill_keys(array_map('strval', $selectedBranches), true);
        $importedRows = 0;
        $scannedRows = 0;
        $output = @fopen($stagedPath, 'wb');
        if ($output === false) {
            throw new \RuntimeException('File staging CRAS tidak dapat dibuat.');
        }

        try {
            $this->iterate($sourcePath, function (array $row, int $sourceRow) use (
                $output,
                $selectedLookup,
                $expectedPeriod,
                &$importedRows,
                &$scannedRows,
                $progress
            ): void {
                $scannedRows++;
                $rowPeriod = $this->parseSourcePeriod($row[0]);
                if ($rowPeriod !== $expectedPeriod) {
                    throw new \RuntimeException(
                        "Periode berubah pada baris sumber {$sourceRow}; import dibatalkan."
                    );
                }

                if (!array_key_exists($row[self::BRANCH_INDEX], $selectedLookup)) {
                    return;
                }

                $encoded = array_map([$this, 'encodeMysqlCsvField'], $row);
                if (fwrite($output, implode(',', $encoded) . "\n") === false) {
                    throw new \RuntimeException('Gagal menulis staging CRAS.');
                }

                $importedRows++;
                if ($progress !== null && $scannedRows % self::PROGRESS_EVERY_ROWS === 0) {
                    $progress([
                        'rows_done' => $scannedRows,
                        'selected_rows' => $importedRows,
                        'message' => 'Menyiapkan staging CRAS tanpa mengubah nilai sumber...',
                    ]);
                }
            });
        } finally {
            fclose($output);
        }

        if ($importedRows === 0) {
            @unlink($stagedPath);
            throw new \RuntimeException('Tidak ada baris CRAS yang sesuai dengan filter cabang.');
        }

        return [
            'staged_path' => $stagedPath,
            'scanned_rows' => $scannedRows,
            'imported_rows' => $importedRows,
        ];
    }

    public function loadStagedCsv(
        string $stagedPath,
        string $period,
        array $branches,
        string $uuidPrefix,
        int $expectedRows
    ): int {
        if (!is_file($stagedPath)) {
            throw new \RuntimeException('File staging CRAS tidak ditemukan.');
        }
        if (!preg_match('/^[a-f0-9]{20}$/', $uuidPrefix)) {
            throw new \RuntimeException('Prefix UUID CRAS tidak valid.');
        }

        $connection = (string) config('database.default', 'mysql');
        $database = (array) config("database.connections.{$connection}", []);
        if (($database['driver'] ?? null) !== 'mysql') {
            throw new \RuntimeException('Fast import CRAS memerlukan koneksi MySQL.');
        }

        $pdo = $this->createMysqlPdo($database);
        $lockName = 'project_abah:table_write:' . self::TABLE;
        $lockAcquired = false;

        try {
            $lockStatement = $pdo->prepare('SELECT GET_LOCK(?, 30)');
            $lockStatement->execute([$lockName]);
            $lockAcquired = (int) $lockStatement->fetchColumn() === 1;
            if (!$lockAcquired) {
                throw new \RuntimeException('Import CRAS lain sedang berjalan. Tunggu proses sebelumnya selesai.');
            }

            $pdo->beginTransaction();
            $this->assertNoExistingBranchOverlap($pdo, $period, $branches);
            $pdo->exec('SET @skip_snapshot_invalidation = 1');
            $pdo->exec('SET @cras_rownum = 0');
            $pdo->exec('SET @cras_uuid_prefix = ' . $pdo->quote($uuidPrefix));

            $normalizedPath = str_replace('\\', '/', realpath($stagedPath) ?: $stagedPath);
            $variables = array_map(
                static fn (int $index): string => '@cras_col_' . $index,
                array_keys(self::SOURCE_COLUMNS)
            );
            $assignments = [];
            foreach (self::SOURCE_COLUMNS as $index => $column) {
                $assignments[] = '`' . $column . '` = @cras_col_' . $index;
            }
            $assignments[] = '`cras_uuid` = CONCAT(@cras_uuid_prefix, LPAD((@cras_rownum := @cras_rownum + 1), 12, \'0\'))';
            $assignments[] = '`cras_periode` = ' . $pdo->quote($period);
            $assignments[] = '`created_at` = NOW()';
            $assignments[] = '`updated_at` = NOW()';

            $sql = 'LOAD DATA LOCAL INFILE ' . $pdo->quote($normalizedPath)
                . ' INTO TABLE `' . self::TABLE . '` CHARACTER SET utf8mb4 '
                . "FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\\\' "
                . "LINES TERMINATED BY '\\n' (" . implode(', ', $variables) . ') '
                . 'SET ' . implode(', ', $assignments);

            $affected = $pdo->exec($sql);
            if ($affected === false || (int) $affected !== $expectedRows) {
                throw new \RuntimeException(
                    'Jumlah hasil LOAD DATA CRAS tidak sama dengan staging: '
                    . (int) $affected . " dari {$expectedRows} baris."
                );
            }

            $warningCount = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
            if ($warningCount > 0) {
                $warning = $pdo->query('SHOW WARNINGS LIMIT 1')->fetch(\PDO::FETCH_ASSOC) ?: [];
                throw new \RuntimeException(
                    'MySQL mendeteksi warning saat memuat CRAS; transaksi dibatalkan agar data tetap utuh. '
                    . (string) ($warning['Message'] ?? '')
                );
            }

            $verify = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE `cras_uuid` LIKE ?'
            );
            $verify->execute([$uuidPrefix . '%']);
            if ((int) $verify->fetchColumn() !== $expectedRows) {
                throw new \RuntimeException('Verifikasi jumlah UUID CRAS gagal; transaksi dibatalkan.');
            }

            $pdo->exec('SET @skip_snapshot_invalidation = NULL');
            $pdo->commit();

            return (int) $affected;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } finally {
            try {
                $pdo->exec('SET @skip_snapshot_invalidation = NULL');
                $pdo->exec('SET @cras_rownum = NULL');
                $pdo->exec('SET @cras_uuid_prefix = NULL');
            } catch (\Throwable) {
            }
            if ($lockAcquired) {
                try {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                } catch (\Throwable) {
                }
            }
        }
    }

    public function parseSourcePeriod(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat(
            '!F j, Y',
            $value,
            new DateTimeZone('Asia/Jakarta')
        );
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors)
            && ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0);

        if (!$date || $hasErrors || $date->format('F j, Y') !== $value) {
            throw new \RuntimeException("Format periode CRAS tidak valid: `{$value}`.");
        }

        return $date->format('Y-m-d');
    }

    private float $activeProgressRatio = 0.0;

    private function iterate(string $path, callable $callback): void
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $this->iterateXlsx($path, $callback);
            return;
        }

        $this->iterateDelimited($path, $callback);
    }

    private function iterateDelimited(string $path, callable $callback): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('File CRAS tidak ditemukan atau tidak dapat dibaca.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('File CRAS tidak dapat dibuka.');
        }

        $fileSize = max(1, (int) (@filesize($path) ?: 1));
        $this->activeProgressRatio = 0.0;
        try {
            $this->configureEncodingFilter($handle);
            $headers = fgetcsv($handle, null, "\t", '"', '');
            if (!is_array($headers)) {
                throw new \RuntimeException('Header CRAS tidak dapat dibaca.');
            }
            if ($headers !== self::SOURCE_HEADERS) {
                throw new \RuntimeException($this->describeHeaderMismatch($headers));
            }

            $sourceRow = 1;
            while (($row = fgetcsv($handle, null, "\t", '"', '')) !== false) {
                $sourceRow++;
                if (count($row) === 1 && ($row[0] === null || $row[0] === '')) {
                    continue;
                }
                if (count($row) !== count(self::SOURCE_HEADERS)) {
                    throw new \RuntimeException(
                        "Baris sumber {$sourceRow} memiliki " . count($row)
                        . ' kolom; seharusnya ' . count(self::SOURCE_HEADERS) . ' kolom. Import dibatalkan.'
                    );
                }

                $callback(array_map(static fn ($value): string => (string) $value, $row), $sourceRow);
                $this->activeProgressRatio = min(1.0, max(0.0, ((int) @ftell($handle)) / $fileSize));
            }
        } finally {
            $this->activeProgressRatio = 0.0;
            fclose($handle);
        }
    }

    private function iterateXlsx(string $path, callable $callback): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('File CRAS XLSX tidak ditemukan atau tidak dapat dibaca.');
        }
        if (!class_exists(\ZipArchive::class) || !class_exists(\XMLReader::class)) {
            throw new \RuntimeException('Ekstensi ZIP dan XMLReader diperlukan untuk membaca CRAS XLSX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Workbook CRAS XLSX tidak dapat dibuka.');
        }

        $reader = null;
        $this->activeProgressRatio = 0.0;
        try {
            $worksheetEntry = $this->resolveFirstWorksheetEntry($zip);
            if ($worksheetEntry === null) {
                throw new \RuntimeException('Worksheet CRAS XLSX tidak ditemukan.');
            }

            $sharedStrings = $this->readXlsxSharedStrings($path, $zip);
            $reader = new \XMLReader();
            $worksheetUri = 'zip://' . str_replace('\\', '/', $path) . '#' . $worksheetEntry;
            if (!$reader->open($worksheetUri, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
                throw new \RuntimeException('Worksheet CRAS XLSX tidak dapat dibaca secara streaming.');
            }

            $headerRead = false;
            $highestRow = 0;
            $lastRowNumber = 0;

            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }

                if ($reader->localName === 'dimension') {
                    $reference = (string) $reader->getAttribute('ref');
                    if (preg_match('/(?:^|:)[A-Z]+(\d+)$/i', $reference, $matches)) {
                        $highestRow = max(0, (int) $matches[1]);
                    }
                    continue;
                }

                if ($reader->localName !== 'row') {
                    continue;
                }

                $rowNumber = (int) $reader->getAttribute('r');
                if ($rowNumber <= 0) {
                    $rowNumber = $lastRowNumber + 1;
                }
                $lastRowNumber = $rowNumber;
                [$row, $hasExtraValue] = $this->readXlsxRow($reader, $sharedStrings);
                $this->activeProgressRatio = $highestRow > 0
                    ? min(1.0, $rowNumber / $highestRow)
                    : 0.0;

                if ($hasExtraValue) {
                    throw new \RuntimeException(
                        "Baris sumber {$rowNumber} memiliki data setelah kolom ke-33. Import dibatalkan."
                    );
                }

                if (!$headerRead) {
                    if ($rowNumber !== 1 || $row !== self::SOURCE_HEADERS) {
                        throw new \RuntimeException($this->describeHeaderMismatch($row));
                    }
                    $headerRead = true;
                    continue;
                }

                if (!array_filter($row, static fn (string $value): bool => $value !== '')) {
                    continue;
                }

                $callback($row, $rowNumber);
            }

            if (!$headerRead) {
                throw new \RuntimeException('Header CRAS XLSX tidak ditemukan pada baris pertama.');
            }
        } finally {
            if ($reader instanceof \XMLReader) {
                $reader->close();
            }
            $zip->close();
            $this->activeProgressRatio = 0.0;
        }
    }

    private function resolveFirstWorksheetEntry(\ZipArchive $zip): ?string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($workbookXml) || !is_string($relationsXml)) {
            return null;
        }

        $workbook = new \DOMDocument();
        $relations = new \DOMDocument();
        if (!@$workbook->loadXML($workbookXml, LIBXML_NONET | LIBXML_COMPACT)
            || !@$relations->loadXML($relationsXml, LIBXML_NONET | LIBXML_COMPACT)) {
            return null;
        }

        $workbookXpath = new \DOMXPath($workbook);
        $relationXpath = new \DOMXPath($relations);
        $relationTargets = [];
        foreach ($relationXpath->query('//*[local-name()="Relationship"]') ?: [] as $relationship) {
            if (!$relationship instanceof \DOMElement) {
                continue;
            }
            $relationTargets[$relationship->getAttribute('Id')] = $relationship->getAttribute('Target');
        }

        foreach ($workbookXpath->query('//*[local-name()="sheet"]') ?: [] as $sheet) {
            if (!$sheet instanceof \DOMElement) {
                continue;
            }
            $relationId = $sheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id'
            );
            $target = $relationTargets[$relationId] ?? '';
            if ($target === '') {
                continue;
            }

            $entry = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/' . ltrim($target, '/');
            if ($zip->locateName($entry) !== false) {
                return $entry;
            }
        }

        return $zip->locateName('xl/worksheets/sheet1.xml') !== false
            ? 'xl/worksheets/sheet1.xml'
            : null;
    }

    private function readXlsxSharedStrings(string $path, \ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $reader = new \XMLReader();
        $uri = 'zip://' . str_replace('\\', '/', $path) . '#xl/sharedStrings.xml';
        if (!$reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new \RuntimeException('Shared strings CRAS XLSX tidak dapat dibaca.');
        }

        $strings = [];
        $text = '';
        $insideItem = false;
        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                    $insideItem = true;
                    $text = '';
                    continue;
                }
                if ($insideItem && $reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 't') {
                    $text .= $reader->readString();
                    continue;
                }
                if ($insideItem && $reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'si') {
                    $strings[] = $text;
                    $text = '';
                    $insideItem = false;
                }
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    private function readXlsxRow(\XMLReader $reader, array $sharedStrings): array
    {
        $rowDepth = $reader->depth;
        $values = array_fill(0, count(self::SOURCE_HEADERS), '');
        $seenColumns = [];
        $hasExtraValue = false;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT
                && $reader->depth === $rowDepth
                && $reader->localName === 'row') {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            $cellReference = (string) $reader->getAttribute('r');
            $columnIndex = $this->xlsxColumnIndex($cellReference);
            if (isset($seenColumns[$columnIndex])) {
                throw new \RuntimeException("Koordinat sel XLSX duplikat: {$cellReference}.");
            }
            $seenColumns[$columnIndex] = true;
            $value = $this->readXlsxCellValue($reader, (string) $reader->getAttribute('t'), $sharedStrings);

            if ($columnIndex >= count(self::SOURCE_HEADERS)) {
                $hasExtraValue = $hasExtraValue || $value !== '';
                continue;
            }
            $values[$columnIndex] = $value;
        }

        return [$values, $hasExtraValue];
    }

    private function readXlsxCellValue(\XMLReader $reader, string $cellType, array $sharedStrings): string
    {
        $cellDepth = $reader->depth;
        $value = '';
        $valueSeen = false;
        $formulaSeen = false;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT
                && $reader->depth === $cellDepth
                && $reader->localName === 'c') {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->localName === 'f') {
                $formulaSeen = true;
                continue;
            }
            if ($reader->localName === 'v') {
                $value = $reader->readString();
                $valueSeen = true;
                continue;
            }
            if ($cellType === 'inlineStr' && $reader->localName === 't') {
                $value .= $reader->readString();
                $valueSeen = true;
            }
        }

        if ($formulaSeen && !$valueSeen) {
            throw new \RuntimeException('Formula XLSX tidak memiliki cached value. Import dibatalkan.');
        }
        if ($cellType !== 's') {
            return $valueSeen ? $value : '';
        }
        if (!$valueSeen || !ctype_digit($value)) {
            throw new \RuntimeException('Index shared string XLSX tidak valid.');
        }

        $index = (int) $value;
        if (!array_key_exists($index, $sharedStrings)) {
            throw new \RuntimeException("Shared string XLSX index {$index} tidak ditemukan.");
        }

        return (string) $sharedStrings[$index];
    }

    private function xlsxColumnIndex(string $cellReference): int
    {
        if (!preg_match('/^([A-Z]+)\d+$/i', $cellReference, $matches)) {
            throw new \RuntimeException("Koordinat sel XLSX tidak valid: {$cellReference}.");
        }

        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function configureEncodingFilter($handle): void
    {
        $prefix = fread($handle, 3);
        if ($prefix === false) {
            throw new \RuntimeException('Encoding file CRAS tidak dapat dideteksi.');
        }

        if (substr($prefix, 0, 2) === "\xFF\xFE") {
            fseek($handle, 2);
            if (@stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8', STREAM_FILTER_READ) === false) {
                throw new \RuntimeException('Konverter UTF-16LE untuk CRAS tidak tersedia.');
            }
            return;
        }

        if ($prefix === "\xEF\xBB\xBF") {
            return;
        }

        fseek($handle, 0);
        if (strlen($prefix) >= 2 && $prefix[1] === "\0") {
            if (@stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8', STREAM_FILTER_READ) === false) {
                throw new \RuntimeException('Konverter UTF-16LE untuk CRAS tidak tersedia.');
            }
        }
    }

    private function describeHeaderMismatch(array $actual): string
    {
        $expectedCount = count(self::SOURCE_HEADERS);
        if (count($actual) !== $expectedCount) {
            return 'Header CRAS berjumlah ' . count($actual)
                . " kolom; template membutuhkan {$expectedCount} kolom.";
        }

        foreach (self::SOURCE_HEADERS as $index => $expected) {
            if (($actual[$index] ?? null) !== $expected) {
                return 'Header CRAS kolom ke-' . ($index + 1) . " harus `{$expected}`, ditemukan `"
                    . (string) ($actual[$index] ?? '') . '`. Tidak ada normalisasi header otomatis.';
            }
        }

        return 'Header CRAS tidak sesuai template.';
    }

    private function encodeMysqlCsvField(string $value): string
    {
        $escaped = strtr($value, [
            "\\" => "\\\\",
            "\0" => "\\0",
            "\n" => "\\n",
            "\r" => "\\r",
            "\t" => "\\t",
            "\x1A" => "\\Z",
            '"' => '\\"',
        ]);

        return '"' . $escaped . '"';
    }

    private function createMysqlPdo(array $config): \PDO
    {
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $database = (string) ($config['database'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');
        $dsn = $socket !== ''
            ? "mysql:unix_socket={$socket};dbname={$database};charset={$charset}"
            : 'mysql:host=' . (string) ($config['host'] ?? '127.0.0.1')
                . ';port=' . (string) ($config['port'] ?? '3306')
                . ";dbname={$database};charset={$charset}";

        return new \PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            \PDO::ATTR_TIMEOUT => 120,
        ]);
    }

    private function assertNoExistingBranchOverlap(\PDO $pdo, string $period, array $branches): void
    {
        if ($branches === []) {
            throw new \RuntimeException('Filter cabang CRAS kosong.');
        }

        $placeholders = implode(', ', array_fill(0, count($branches), '?'));
        $statement = $pdo->prepare(
            'SELECT `ket_kanca` FROM `' . self::TABLE . '` '
            . 'WHERE `cras_periode` = ? AND `ket_kanca` IN (' . $placeholders . ') LIMIT 1 FOR UPDATE'
        );
        $statement->execute(array_merge([$period], array_values($branches)));
        $existingBranch = $statement->fetchColumn();
        if ($existingBranch !== false) {
            throw new \RuntimeException(
                "Data CRAS periode {$period} untuk cabang `{$existingBranch}` sudah ada di database."
            );
        }
    }
}
