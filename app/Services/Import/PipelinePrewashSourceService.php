<?php

namespace App\Services\Import;

use Generator;
use PDO;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use RuntimeException;
use Throwable;
use XMLReader;
use ZipArchive;

class PipelinePrewashSourceService
{
    public const TABLE = 'pipeline_prewash';

    public const SHEET = 'Pipeline SLIK Hijau';

    public const BASE_COLUMNS = [
        'nama_debt', 'alamat', 'nomorhandphone', 'cif', 'norek_pinjaman', 'branch',
        'nama_unit', 'nama_kanca', 'plafond', 'os', 'sumberpipeline', 'pn_mantri',
        'nama_mantri', 'product_rekomendasi', 'score', 'idpipeline_lama', 'sumberpipeline_lama',
    ];

    public const IMPORT_COLUMNS = [...self::BASE_COLUMNS, 'tahun'];

    public const MONTH_COLUMNS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agt', 'sep', 'okt', 'nov', 'des'];

    public const SOURCE_HEADERS = [
        'nama_debt', 'alamat', 'nomorhandphone', 'cif', 'norek_pinjaman', 'branch',
        'nama_unit', 'nama_kanca', 'kanwil', 'plafond', 'os', 'sumberpipeline', 'pn_mantri',
        'nama_mantri', 'product_rekomendasi', 'score', 'idpipeline_lama', 'sumberpipeline_lama',
        'Jumlah Kunjungan', 'Kunjungan', 'Plafond Real', 'Status',
        'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGT', 'SEP', 'OKT', 'NOV', 'DES',
    ];

    private const REQUIRED_COLUMNS = ['nama_debt', 'branch', 'nama_unit', 'nama_kanca', 'plafond', 'os'];

    private const ID_COLUMNS = ['nomorhandphone', 'cif', 'norek_pinjaman', 'branch', 'pn_mantri', 'idpipeline_lama'];

    /** Read only OOXML records; derived worksheet values are deliberately never staged. */
    public function stage(string $source, string $destination, int $year): array
    {
        $this->assertYear((string) $year);
        $this->assertDifferentPaths($source, $destination);
        $zip = new ZipArchive;
        if ($zip->open($source) !== true) {
            throw new RuntimeException('Pipeline Prewash wajib menggunakan workbook XLSX yang valid.');
        }
        $output = null;
        $strings = null;
        $previousXmlErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $sheetPath = $this->sheetPath($source);
            $strings = $this->sharedStrings($source, $zip->locateName('xl/sharedStrings.xml') !== false);
            $output = $this->open($destination, 'wb');
            $this->writeCsv($output, self::IMPORT_COLUMNS);
            $header = null;
            $sourceIndexes = [];
            $rows = 0;
            foreach ($this->sheetRows($source, $sheetPath, $strings) as $record => $cells) {
                if ($cells === []) {
                    continue;
                }
                if ($header === null) {
                    $header = array_fill(0, count(self::SOURCE_HEADERS), '');
                    foreach ($cells as $index => $cell) {
                        $header[$index] = trim($cell['value']);
                    }
                    if (array_map('strtolower', $header) !== array_map('strtolower', self::SOURCE_HEADERS)) {
                        throw new RuntimeException('Header Pipeline Prewash tidak sesuai dengan 34 kolom sumber.');
                    }
                    if (! app(SmartContentHeaderGuardService::class)->isHeaderOrContentValidForTable($header, self::TABLE)) {
                        throw new RuntimeException('Header Pipeline Prewash ditolak oleh pemeriksaan header report.');
                    }
                    $sourceIndexes = array_flip(array_map('strtolower', $header));

                    continue;
                }
                $row = [];
                foreach (self::BASE_COLUMNS as $column) {
                    $index = $sourceIndexes[$column];
                    $cell = $cells[$index] ?? ['value' => '', 'numeric' => false, 'formula' => false, 'error' => false];
                    if ($cell['formula'] || $cell['error']) {
                        throw new RuntimeException("Kolom sumber {$column} memiliki formula/error pada baris {$record}; gunakan nilai sumber yang pasti.");
                    }
                    $value = $cell['value'];
                    if ($value !== '' && in_array($column, ['plafond', 'os'], true)) {
                        $value = PipelinePrewashDecimal::normalize($value);
                    } elseif ($value !== '' && in_array($column, self::ID_COLUMNS, true)
                        && ($cell['numeric'] || preg_match('/^[+]?\d+(?:\.\d+)?[eE][+-]?\d+$/D', $value))) {
                        $value = PipelinePrewashDecimal::integer($value);
                    }
                    $row[] = $value;
                }
                $this->validateRow($row, $record);
                $this->writeCsv($output, [...$row, (string) $year]);
                $rows++;
            }
            if ($header === null || $rows === 0) {
                throw new RuntimeException('Workbook Pipeline Prewash tidak memiliki data sumber.');
            }
            if (libxml_get_errors() !== []) {
                throw new RuntimeException('XML workbook Pipeline Prewash tidak lengkap atau rusak; seluruh staging dibatalkan.');
            }
        } catch (Throwable $error) {
            if (is_resource($output)) {
                fclose($output);
                $output = null;
                @unlink($destination);
            }
            throw $error;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
            if (is_array($strings)) {
                fclose($strings['data']);
                fclose($strings['offsets']);
            }
            $zip->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousXmlErrors);
        }

        return [
            'rows' => $rows, 'columns' => self::IMPORT_COLUMNS, 'tahun' => $year,
            'sheet' => self::SHEET, 'source_file' => basename($source), 'source_bytes' => filesize($source),
            'source_sha256' => hash_file('sha256', $source), 'canonical_sha256' => hash_file('sha256', $destination),
        ];
    }

    public function preview(string $canonical, ?callable $progress = null): array
    {
        $input = $this->open($canonical, 'rb');
        $preview = [];
        $options = array_fill(0, count(self::IMPORT_COLUMNS), []);
        $rows = 0;
        $year = null;
        $size = max(1, (int) filesize($canonical));
        try {
            $this->assertCanonicalHeader(fgetcsv($input, 0, ',', '"', ''));
            while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                $this->assertCanonicalRow($row, $rows + 2, $year);
                $rows++;
                if ($rows <= 100) {
                    $preview[] = $row;
                }
                foreach ($row as $index => $value) {
                    if (count($options[$index]) < 256) {
                        $key = trim((string) $value);
                        $options[$index][$key === '' ? '(Blank)' : $key] = true;
                    }
                }
                if ($rows % 5000 === 0 && $progress !== null) {
                    $progress($rows, min(1, ftell($input) / $size));
                }
            }
        } finally {
            fclose($input);
        }
        if ($rows === 0) {
            throw new RuntimeException('Staging Pipeline Prewash tidak memiliki data.');
        }

        return [
            'headers' => self::IMPORT_COLUMNS, 'sourceHeaders' => self::IMPORT_COLUMNS, 'preview' => $preview,
            'formattedUniqueValues' => array_map(static function (array $values): array {
                $values = array_map('strval', array_keys($values));
                sort($values, SORT_STRING);

                return $values;
            }, $options),
            'total_rows' => $rows + 1, 'header_index' => 0, 'delimiter' => ',', 'tahun' => $year,
            'staged_csv_path' => $canonical,
        ];
    }

    public function prepareBulk(string $canonical, string $destination, array $headers, array $filters = [], ?callable $progress = null): array
    {
        $this->assertDifferentPaths($canonical, $destination);
        $input = $this->open($canonical, 'rb');
        $output = $this->open($destination, 'wb');
        $rows = $scanned = 0;
        $year = null;
        $timestamp = now()->format('Y-m-d H:i:s');
        try {
            $this->assertCanonicalHeader(fgetcsv($input, 0, ',', '"', ''));
            $rules = [];
            foreach ($filters as $index => $values) {
                $column = strtolower((string) ($headers[$index] ?? ''));
                $sourceIndex = array_search($column, self::IMPORT_COLUMNS, true);
                if ($sourceIndex === false) {
                    throw new RuntimeException('Kolom filter Pipeline Prewash tidak dikenali.');
                }
                $rules[$sourceIndex] = array_fill_keys(array_map('strval', (array) $values), true);
            }
            while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                $scanned++;
                $this->assertCanonicalRow($row, $scanned + 1, $year);
                $selected = true;
                foreach ($rules as $index => $allowed) {
                    $value = trim((string) $row[$index]);
                    if (! isset($allowed[$value === '' ? '(Blank)' : $value])) {
                        $selected = false;
                        break;
                    }
                }
                if ($selected) {
                    $values = array_map(static fn ($value) => trim($value) === '' ? null : $value, $row);
                    $values = [...$values, 'prewash_'.$year.'_'.$scanned, $timestamp, $timestamp];
                    $this->write($output, implode(',', array_map([$this, 'encodeMysqlField'], $values))."\n");
                    $rows++;
                }
                if ($scanned % 5000 === 0 && $progress !== null) {
                    $progress($scanned, $rows);
                }
            }
            if ($rows === 0) {
                throw new RuntimeException('Tidak ada baris Pipeline Prewash yang terpilih untuk diimport.');
            }
        } catch (Throwable $error) {
            fclose($output);
            @unlink($destination);
            throw $error;
        } finally {
            fclose($input);
        }
        fclose($output);

        return ['rows' => $rows, 'scanned' => $scanned, 'tahun' => $year, 'columns' => [...self::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at']];
    }

    /** Fail closed on an existing annual dataset, duplicate identity, warning or count mismatch. */
    public function loadBulk(string $path, int $expectedRows, int $year, ?PDO $pdo = null): int
    {
        $this->assertYear((string) $year);
        if ($expectedRows < 1) {
            throw new RuntimeException('Jumlah baris Pipeline Prewash yang akan diimport tidak valid.');
        }
        $loader = app(MySqlBulkLoadService::class);
        $loader->assertTransactionalTable(self::TABLE, 'import Pipeline Prewash');

        return $loader->withTableWriteLock(self::TABLE, function () use ($path, $expectedRows, $year, $pdo, $loader): int {
            $pdo ??= $loader->createPersistentPdo();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
                throw new RuntimeException('Import cepat Pipeline Prewash memerlukan MySQL/MariaDB.');
            }
            $columns = [...self::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at'];
            $variables = array_map(static fn ($index): string => '@prewash_'.$index, array_keys($columns));
            $assignments = [];
            foreach ($columns as $index => $column) {
                $assignments[] = '`'.$column.'` = '.$variables[$index];
            }
            $sql = 'LOAD DATA LOCAL INFILE '.$pdo->quote(str_replace('\\', '/', realpath($path) ?: $path))
                .' INTO TABLE `'.self::TABLE.'` CHARACTER SET utf8mb4 '
                ."FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\\\' LINES TERMINATED BY '\\n' ("
                .implode(', ', $variables).') SET '.implode(', ', $assignments);
            $pdo->beginTransaction();
            try {
                $count = $pdo->prepare('SELECT COUNT(*) FROM `'.self::TABLE.'` WHERE `tahun` = ?');
                $count->execute([$year]);
                if ((int) $count->fetchColumn() !== 0) {
                    throw new RuntimeException("Pipeline Prewash tahun {$year} sudah ada; hapus dataset tahun tersebut melalui Kelola Report sebelum import ulang.");
                }
                $affected = $pdo->exec($sql);
                // MySQL diagnostics must be captured before another statement clears them.
                $warnings = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
                if ($warnings > 0 || $affected === false || (int) $affected !== $expectedRows) {
                    throw new RuntimeException('Import Pipeline Prewash dibatalkan: identitas duplikat, warning MySQL, atau jumlah tersimpan berbeda dari sumber ('.(int) $affected.'/'.$expectedRows.', warnings '.$warnings.').');
                }
                $count->execute([$year]);
                if ((int) $count->fetchColumn() !== $expectedRows) {
                    throw new RuntimeException('Verifikasi jumlah dataset tahunan Pipeline Prewash tidak sesuai; transaksi dibatalkan.');
                }
                $pdo->commit();

                return (int) $affected;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        });
    }

    public function import(string $source, array $headers, array $filters = [], ?callable $progress = null): int
    {
        $bulkPath = tempnam(dirname($source), 'prewash_bulk_');
        if ($bulkPath === false) {
            throw new RuntimeException('Tidak dapat membuat file bulk Pipeline Prewash.');
        }
        try {
            $bulk = $this->prepareBulk($source, $bulkPath, $headers, $filters, $progress);

            return $this->loadBulk($bulkPath, $bulk['rows'], $bulk['tahun']);
        } finally {
            @unlink($bulkPath);
        }
    }

    public function encodeMysqlField(?string $value): string
    {
        return $value === null ? '\\N' : '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\r" => '\\r', "\n" => '\\n', "\t" => '\\t', "\x1a" => '\\Z']).'"';
    }

    public function validateRow(array $row, int $record): void
    {
        if (count($row) !== count(self::BASE_COLUMNS)) {
            throw new RuntimeException("Jumlah kolom sumber Pipeline Prewash tidak sesuai pada record {$record}.");
        }
        foreach (self::BASE_COLUMNS as $index => $column) {
            $value = (string) $row[$index];
            if (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                throw new RuntimeException("Encoding/karakter {$column} tidak valid pada record {$record}.");
            }
            if (trim($value) === '' && in_array($column, self::REQUIRED_COLUMNS, true)) {
                throw new RuntimeException("Kolom wajib {$column} kosong pada record {$record}; baris tidak dibuang.");
            }
            $maximum = in_array($column, [...self::ID_COLUMNS, 'score'], true) ? 64 : 255;
            if (($column === 'alamat' && strlen($value) > 65535) || ($column !== 'alamat' && mb_strlen($value) > $maximum)) {
                throw new RuntimeException("Kolom {$column} melebihi kapasitas pada record {$record}; data tidak dipotong.");
            }
            if (in_array($column, ['plafond', 'os'], true)) {
                $normalized = PipelinePrewashDecimal::normalize($value);
                if ($normalized === null || $normalized !== $value || strlen(ltrim(explode('.', ltrim($normalized, '-'))[0], '0')) > 28) {
                    throw new RuntimeException("Nilai {$column} tidak sesuai DECIMAL(30,2) pada record {$record}; angka tidak dibulatkan.");
                }
            }
        }
    }

    private function assertCanonicalHeader(array|false $header): void
    {
        if ($header !== self::IMPORT_COLUMNS) {
            throw new RuntimeException('Header staging Pipeline Prewash tidak sesuai; upload ulang workbook sumber.');
        }
    }

    private function assertCanonicalRow(array $row, int $record, ?int &$year): void
    {
        if (count($row) !== count(self::IMPORT_COLUMNS)) {
            throw new RuntimeException("Jumlah kolom staging Pipeline Prewash tidak sesuai pada record {$record}.");
        }
        $this->validateRow(array_slice($row, 0, count(self::BASE_COLUMNS)), $record);
        $value = (string) $row[count(self::BASE_COLUMNS)];
        $this->assertYear($value);
        if ($year !== null && $year !== (int) $value) {
            throw new RuntimeException("Tahun staging Pipeline Prewash berubah pada record {$record}.");
        }
        $year = (int) $value;
    }

    private function assertYear(string $year): void
    {
        if (preg_match('/^\d{4}$/D', $year) !== 1 || (int) $year < 1900 || (int) $year > 2199) {
            throw new RuntimeException('Tahun laporan Pipeline Prewash wajib berupa tahun 1900 sampai 2199.');
        }
    }

    private function sheetPath(string $source): string
    {
        $workbook = $this->xml($source, 'xl/workbook.xml');
        $relation = null;
        try {
            while ($workbook->read()) {
                if ($workbook->nodeType === XMLReader::ELEMENT && $workbook->localName === 'sheet' && $workbook->getAttribute('name') === self::SHEET) {
                    if ($relation !== null) {
                        throw new RuntimeException('Nama sheet Pipeline Prewash duplikat.');
                    }
                    $relation = $workbook->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships') ?? $workbook->getAttribute('r:id');
                }
            }
        } finally {
            $workbook->close();
        }
        if ($relation === null) {
            throw new RuntimeException('Sheet '.self::SHEET.' tidak ditemukan dalam workbook Pipeline Prewash.');
        }
        $relations = $this->xml($source, 'xl/_rels/workbook.xml.rels');
        try {
            while ($relations->read()) {
                if ($relations->nodeType === XMLReader::ELEMENT && $relations->localName === 'Relationship' && $relations->getAttribute('Id') === $relation) {
                    if ($relations->getAttribute('TargetMode') === 'External') {
                        throw new RuntimeException('Sheet eksternal Pipeline Prewash tidak diterima.');
                    }
                    $target = str_replace('\\', '/', (string) $relations->getAttribute('Target'));
                    $path = [];
                    foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target) as $part) {
                        if ($part === '..') {
                            if ($path === []) {
                                throw new RuntimeException('Lokasi sheet Pipeline Prewash tidak valid.');
                            }
                            array_pop($path);
                        } elseif ($part !== '' && $part !== '.') {
                            $path[] = $part;
                        }
                    }

                    return implode('/', $path);
                }
            }
        } finally {
            $relations->close();
        }
        throw new RuntimeException('Relasi sheet Pipeline Prewash tidak ditemukan.');
    }

    /** Keep shared text and its fixed-width offset index on disk rather than retaining all strings in RAM. */
    private function sharedStrings(string $source, bool $exists): array
    {
        $data = tmpfile();
        $offsets = tmpfile();
        if ($data === false || $offsets === false) {
            if (is_resource($data)) {
                fclose($data);
            }
            if (is_resource($offsets)) {
                fclose($offsets);
            }
            throw new RuntimeException('Tidak dapat membuat penyimpanan teks sementara Pipeline Prewash.');
        }
        $count = 0;
        if ($exists) {
            $xml = $this->xml($source, 'xl/sharedStrings.xml');
            try {
                while ($xml->read()) {
                    if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si') {
                        $value = $this->elementText($xml, 'si');
                        $this->write($offsets, pack('P', ftell($data)));
                        $this->write($data, pack('V', strlen($value)).$value);
                        $count++;
                    }
                }
            } catch (Throwable $error) {
                fclose($data);
                fclose($offsets);
                throw $error;
            } finally {
                $xml->close();
            }
        }

        return ['data' => $data, 'offsets' => $offsets, 'count' => $count, 'cache' => []];
    }

    private function sharedString(array &$strings, string $index): string
    {
        if (preg_match('/^\d{1,9}$/D', $index) !== 1 || (int) $index >= $strings['count']) {
            throw new RuntimeException('Referensi teks workbook Pipeline Prewash tidak valid.');
        }
        $index = (int) $index;
        if (isset($strings['cache'][$index])) {
            return $strings['cache'][$index];
        }
        fseek($strings['offsets'], $index * 8);
        $offset = unpack('Pvalue', fread($strings['offsets'], 8))['value'];
        fseek($strings['data'], $offset);
        $length = unpack('Vvalue', fread($strings['data'], 4))['value'];
        $value = $length === 0 ? '' : fread($strings['data'], $length);
        if ($value === false || strlen($value) !== $length) {
            throw new RuntimeException('Teks workbook Pipeline Prewash tidak terbaca sempurna.');
        }
        if (count($strings['cache']) >= 4096) {
            $strings['cache'] = [];
        }
        $strings['cache'][$index] = $value;

        return $value;
    }

    private function sheetRows(string $source, string $entry, array &$strings): Generator
    {
        $xml = $this->xml($source, $entry);
        try {
            while ($xml->read()) {
                if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'row') {
                    continue;
                }
                $record = (int) $xml->getAttribute('r');
                $depth = $xml->depth;
                $cells = [];
                if (! $xml->isEmptyElement) {
                    while ($xml->read()) {
                        if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->depth === $depth) {
                            break;
                        }
                        if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'c') {
                            $reference = (string) $xml->getAttribute('r');
                            if (preg_match('/^([A-Z]+)\d+$/D', $reference, $parts) !== 1) {
                                throw new RuntimeException('Posisi sel workbook Pipeline Prewash tidak valid.');
                            }
                            $index = 0;
                            foreach (str_split($parts[1]) as $letter) {
                                $index = $index * 26 + ord($letter) - 64;
                            }
                            if ($index > count(self::SOURCE_HEADERS) || isset($cells[$index - 1])) {
                                throw new RuntimeException("Jumlah/posisi kolom workbook Pipeline Prewash tidak sesuai pada baris {$record}.");
                            }
                            $cell = $this->readCell($xml, $strings);
                            if ($cell['value'] !== '' || $cell['formula'] || $cell['error']) {
                                $cells[$index - 1] = $cell;
                            }
                        }
                    }
                }
                yield $record => $cells;
            }
        } finally {
            $xml->close();
        }
    }

    private function readCell(XMLReader $xml, array &$strings): array
    {
        $type = $xml->getAttribute('t') ?? 'n';
        $depth = $xml->depth;
        $value = '';
        $formula = false;
        if (! $xml->isEmptyElement) {
            while ($xml->read()) {
                if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->depth === $depth) {
                    break;
                }
                if ($xml->nodeType === XMLReader::ELEMENT) {
                    if ($xml->localName === 'f') {
                        $formula = true;
                    } elseif ($xml->localName === 'v') {
                        $value = $xml->readString();
                    } elseif ($xml->localName === 'is') {
                        $value = $this->elementText($xml, 'is');
                    }
                }
            }
        }
        if ($type === 's' && $value !== '') {
            $value = $this->sharedString($strings, $value);
        }

        if (in_array($type, ['s', 'inlineStr', 'str'], true)) {
            $value = StringHelper::controlCharacterOOXML2PHP($value);
        }

        return ['value' => $value, 'numeric' => $type === 'n', 'formula' => $formula, 'error' => in_array($type, ['e', 'b', 'd'], true)];
    }

    private function elementText(XMLReader $xml, string $name): string
    {
        $depth = $xml->depth;
        $value = '';
        if (! $xml->isEmptyElement) {
            while ($xml->read()) {
                if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->depth === $depth && $xml->localName === $name) {
                    break;
                }
                if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 't') {
                    $value .= $xml->readString();
                }
            }
        }

        return $value;
    }

    private function xml(string $source, string $entry): XMLReader
    {
        $xml = new XMLReader;
        if (! @$xml->open('zip://'.str_replace('\\', '/', realpath($source) ?: $source).'#'.$entry, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Struktur XML workbook Pipeline Prewash tidak dapat dibaca.');
        }
        $xml->setParserProperty(XMLReader::LOADDTD, false);
        $xml->setParserProperty(XMLReader::SUBST_ENTITIES, false);

        return $xml;
    }

    private function assertDifferentPaths(string $source, string $destination): void
    {
        if (realpath($source) !== false && realpath($source) === realpath($destination)) {
            throw new RuntimeException('File sumber dan staging Pipeline Prewash harus berbeda.');
        }
    }

    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException('File Pipeline Prewash tidak dapat dibuka.');
        }

        return $handle;
    }

    private function writeCsv($handle, array $row): void
    {
        if (fputcsv($handle, $row, ',', '"', '') === false) {
            throw new RuntimeException('Gagal menulis staging Pipeline Prewash.');
        }
    }

    private function write($handle, string $record): void
    {
        if (fwrite($handle, $record) !== strlen($record)) {
            throw new RuntimeException('Gagal menulis seluruh record Pipeline Prewash.');
        }
    }
}
