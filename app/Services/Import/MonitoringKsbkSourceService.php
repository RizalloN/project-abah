<?php

namespace App\Services\Import;

use DateTimeImmutable;
use Generator;
use PDO;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use RuntimeException;
use Throwable;
use XMLReader;
use ZipArchive;

/** Stages the thirteen source fields; worksheet realization formulas are never trusted. */
class MonitoringKsbkSourceService
{
    public const TABLE = 'monitoring_realisasi_ksbk';

    public const SHEET = 'Lolos';

    public const BASE_COLUMNS = ['branch', 'pernr', 'nik', 'cifno', 'nama', 'plafond_pengajuan', 'rencana_real_date', 'produk_sbk', 'hasil_cek', 'bo', 'ro', 'batch', 'tgl_inject'];

    public const IMPORT_COLUMNS = self::BASE_COLUMNS;

    public const SOURCE_HEADERS = ['branch', 'pernr', 'nik', 'cifno', 'nama', 'plafond_pengajuan', 'rencana_real_date', 'produk_sbk', 'hasil_cek', 'BO', 'RO', 'Batch', 'Tgl Inject', 'Cek Real', 'Plafond', 'Ln Type', 'KSBK/ Non'];

    private const ID_COLUMNS = ['branch', 'pernr', 'nik', 'cifno', 'bo', 'ro', 'batch'];

    private int $excelCalendar = Date::CALENDAR_WINDOWS_1900;

    public function stage(string $source, string $destination): array
    {
        $this->assertDifferentPaths($source, $destination);
        $zip = new ZipArchive;
        if ($zip->open($source) !== true) {
            throw new RuntimeException('Monitoring KSBK wajib menggunakan workbook XLSX yang valid.');
        }
        $output = $strings = null;
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $this->excelCalendar = Date::CALENDAR_WINDOWS_1900;
            $sheet = $this->sheetPath($source);
            $strings = $this->sharedStrings($source, $zip->locateName('xl/sharedStrings.xml') !== false);
            $output = $this->open($destination, 'wb');
            $this->writeCsv($output, self::IMPORT_COLUMNS);
            $header = null;
            $rows = $derivedOnly = 0;
            foreach ($this->sheetRows($source, $sheet, $strings) as $record => $cells) {
                if (libxml_get_errors() !== []) {
                    throw new RuntimeException('XML workbook Monitoring KSBK rusak; seluruh staging dibatalkan.');
                }
                if ($cells === []) {
                    continue;
                }
                if ($header === null) {
                    $header = [];
                    foreach ($cells as $index => $cell) {
                        if ($cell['formula'] || $cell['error']) {
                            throw new RuntimeException('Header Monitoring KSBK tidak boleh berupa formula/error.');
                        }
                        $header[$index] = strtolower(trim($cell['value']));
                    }
                    if ($header !== array_map('strtolower', self::SOURCE_HEADERS)) {
                        throw new RuntimeException('Header Monitoring KSBK wajib memuat 17 kolom berurutan sesuai workbook Lolos.');
                    }

                    continue;
                }
                if (array_intersect_key($cells, array_flip(range(0, 12))) === []) {
                    $derivedOnly++;

                    continue;
                }
                $row = [];
                foreach (self::BASE_COLUMNS as $index => $column) {
                    $cell = $cells[$index] ?? ['value' => '', 'numeric' => false, 'formula' => false, 'error' => false];
                    if ($cell['formula'] || $cell['error']) {
                        throw new RuntimeException("Kolom sumber {$column} memiliki formula/error pada baris {$record}.");
                    }
                    $value = $cell['value'];
                    if ($value !== '' && $column === 'plafond_pengajuan') {
                        $value = PipelinePrewashDecimal::normalize($value);
                    } elseif ($value !== '' && in_array($column, ['rencana_real_date', 'tgl_inject'], true)) {
                        $value = $this->dateValue($value, $column, $cell['numeric']);
                    } elseif ($value !== '' && in_array($column, self::ID_COLUMNS, true)
                        && ($cell['numeric'] || preg_match('/^\d+(?:\.\d+)?[eE][+-]?\d+$/D', $value))) {
                        $value = PipelinePrewashDecimal::integer($value);
                    }
                    $row[] = $value;
                }
                $this->validateRow($row, $record);
                $this->writeCsv($output, $row);
                $rows++;
            }
            if ($header === null || $rows === 0) {
                throw new RuntimeException('Workbook Monitoring KSBK tidak memiliki data sumber.');
            }
            if (libxml_get_errors() !== []) {
                throw new RuntimeException('XML workbook Monitoring KSBK rusak; seluruh staging dibatalkan.');
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
            libxml_use_internal_errors($previous);
        }

        return ['rows' => $rows, 'derived_only_rows' => $derivedOnly, 'columns' => self::IMPORT_COLUMNS, 'sheet' => self::SHEET,
            'source_file' => basename($source), 'source_bytes' => filesize($source),
            'source_sha256' => hash_file('sha256', $source), 'canonical_sha256' => hash_file('sha256', $destination)];
    }

    public function preview(string $canonical, ?callable $progress = null): array
    {
        $input = $this->open($canonical, 'rb');
        $preview = [];
        $options = array_fill(0, count(self::IMPORT_COLUMNS), []);
        $rows = 0;
        try {
            $this->assertCanonicalHeader(fgetcsv($input, 0, ',', '"', ''));
            while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                $this->validateRow($row, $rows + 2);
                $rows++;
                if ($rows <= 100) {
                    $preview[] = $row;
                }
                foreach ($row as $index => $value) {
                    if (count($options[$index]) < 256) {
                        $options[$index][$value === '' ? '(Blank)' : $value] = true;
                    }
                }
                if ($rows % 5000 === 0 && $progress !== null) {
                    $progress($rows, min(1, ftell($input) / max(1, filesize($canonical))));
                }
            }
        } finally {
            fclose($input);
        }
        if ($rows === 0) {
            throw new RuntimeException('Staging Monitoring KSBK tidak memiliki data.');
        }

        return ['headers' => self::IMPORT_COLUMNS, 'sourceHeaders' => self::IMPORT_COLUMNS, 'preview' => $preview,
            'formattedUniqueValues' => array_map(static function (array $values): array {
                $values = array_map('strval', array_keys($values));
                sort($values, SORT_STRING);

                return $values;
            }, $options), 'total_rows' => $rows + 1, 'header_index' => 0, 'delimiter' => ',', 'staged_csv_path' => $canonical];
    }

    public function validateCanonical(string $canonical): array
    {
        return $this->preview($canonical);
    }

    public function prepareBulk(string $canonical, string $destination, array $headers, array $filters = [], ?callable $progress = null): array
    {
        $this->assertDifferentPaths($canonical, $destination);
        $input = $this->open($canonical, 'rb');
        $output = $this->open($destination, 'wb');
        $rows = $scanned = 0;
        $timestamp = now()->format('Y-m-d H:i:s');
        try {
            $this->assertCanonicalHeader(fgetcsv($input, 0, ',', '"', ''));
            $rules = [];
            foreach ($filters as $index => $values) {
                $column = strtolower((string) ($headers[$index] ?? ''));
                $sourceIndex = array_search($column, self::IMPORT_COLUMNS, true);
                if ($sourceIndex === false) {
                    throw new RuntimeException('Kolom filter Monitoring KSBK tidak dikenali.');
                }
                $rules[$sourceIndex] = array_fill_keys(array_map('strval', (array) $values), true);
            }
            while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                $this->validateRow($row, ++$scanned + 1);
                $selected = true;
                foreach ($rules as $index => $allowed) {
                    if (! isset($allowed[$row[$index] === '' ? '(Blank)' : $row[$index]])) {
                        $selected = false;
                        break;
                    }
                }
                if ($selected) {
                    $values = array_map(static fn ($value) => trim($value) === '' ? null : $value, $row);
                    $values = [...$values, 'ksbk_'.$scanned, $timestamp, $timestamp];
                    $this->write($output, implode(',', array_map([$this, 'encodeMysqlField'], $values))."\n");
                    $rows++;
                }
                if ($scanned % 5000 === 0 && $progress !== null) {
                    $progress($scanned, $rows);
                }
            }
            if ($rows === 0) {
                throw new RuntimeException('Tidak ada baris Monitoring KSBK yang terpilih untuk diimport.');
            }
        } catch (Throwable $error) {
            fclose($output);
            $output = null;
            @unlink($destination);
            throw $error;
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
            }
        }

        return ['rows' => $rows, 'scanned' => $scanned, 'columns' => [...self::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at']];
    }

    /** Import an empty report atomically, after the entire staging has passed validation. */
    public function loadBulk(string $path, int $expectedRows, ?PDO $pdo = null): int
    {
        if ($expectedRows < 1) {
            throw new RuntimeException('Jumlah baris Monitoring KSBK yang akan diimport tidak valid.');
        }
        $loader = app(MySqlBulkLoadService::class);
        $loader->assertTransactionalTable(self::TABLE, 'import Monitoring KSBK');

        return $loader->withTableWriteLock(self::TABLE, function () use ($path, $expectedRows, $pdo, $loader): int {
            $pdo ??= $loader->createPersistentPdo();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
                throw new RuntimeException('Import cepat Monitoring KSBK memerlukan MySQL/MariaDB.');
            }
            $columns = [...self::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at'];
            $variables = array_map(static fn ($index): string => '@ksbk_'.$index, array_keys($columns));
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
                if ((int) $pdo->query('SELECT COUNT(*) FROM `'.self::TABLE.'`')->fetchColumn() !== 0) {
                    throw new RuntimeException('Monitoring KSBK sudah ada; hapus dataset melalui Kelola Report sebelum import ulang.');
                }
                $affected = $pdo->exec($sql);
                $warnings = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
                if ($warnings > 0 || $affected === false || (int) $affected !== $expectedRows) {
                    throw new RuntimeException('Import Monitoring KSBK dibatalkan: warning MySQL atau jumlah tersimpan berbeda dari sumber ('.(int) $affected.'/'.$expectedRows.', warnings '.$warnings.').');
                }
                if ((int) $pdo->query('SELECT COUNT(*) FROM `'.self::TABLE.'`')->fetchColumn() !== $expectedRows) {
                    throw new RuntimeException('Verifikasi jumlah Monitoring KSBK tidak sesuai; transaksi dibatalkan.');
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
        $bulk = tempnam(dirname($source), 'ksbk_bulk_');
        if ($bulk === false) {
            throw new RuntimeException('Tidak dapat membuat file bulk Monitoring KSBK.');
        }
        try {
            $metadata = $this->prepareBulk($source, $bulk, $headers, $filters, $progress);

            return $this->loadBulk($bulk, $metadata['rows']);
        } finally {
            @unlink($bulk);
        }
    }

    public function encodeMysqlField(?string $value): string
    {
        return $value === null ? '\\N' : '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\r" => '\\r', "\n" => '\\n', "\t" => '\\t', "\x1a" => '\\Z']).'"';
    }

    public function validateRow(array $row, int $record): void
    {
        if (count($row) !== count(self::BASE_COLUMNS)) {
            throw new RuntimeException("Jumlah kolom sumber Monitoring KSBK tidak sesuai pada record {$record}.");
        }
        foreach (self::BASE_COLUMNS as $index => $column) {
            $value = (string) $row[$index];
            if (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                throw new RuntimeException("Encoding/karakter {$column} tidak valid pada record {$record}.");
            }
            if (trim($value) === '' && in_array($column, ['branch', 'nama', 'produk_sbk', 'bo'], true)) {
                throw new RuntimeException("Kolom wajib {$column} kosong pada record {$record}; baris tidak dibuang.");
            }
            if (mb_strlen($value) > (in_array($column, self::ID_COLUMNS, true) ? 64 : 255)) {
                throw new RuntimeException("Kolom {$column} melebihi kapasitas pada record {$record}; data tidak dipotong.");
            }
            if ($column === 'plafond_pengajuan' && $value !== '') {
                $normalized = PipelinePrewashDecimal::normalize($value);
                if ($normalized !== $value || strlen(ltrim(explode('.', ltrim($value, '-'))[0], '0')) > 28) {
                    throw new RuntimeException("Nilai {$column} tidak sesuai DECIMAL(30,2) pada record {$record}.");
                }
            }
            if (in_array($column, ['rencana_real_date', 'tgl_inject'], true) && $value !== '' && $this->dateValue($value, $column, false) !== $value) {
                throw new RuntimeException("Tanggal staging {$column} tidak valid pada record {$record}.");
            }
        }
    }

    private function dateValue(string $value, string $column, bool $numeric): string
    {
        try {
            if ($numeric) {
                if (! is_numeric($value) || (float) $value < 1 || (float) $value > 2958465) {
                    throw new RuntimeException('Serial tanggal Excel di luar jangkauan.');
                }
                $previousCalendar = Date::getExcelCalendar();
                Date::setExcelCalendar($this->excelCalendar);
                try {
                    $date = Date::excelToDateTimeObject((float) $value);
                } finally {
                    Date::setExcelCalendar($previousCalendar);
                }
            } else {
                if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)?$/D', trim($value)) !== 1) {
                    throw new RuntimeException('Gunakan tanggal ISO atau serial Excel.');
                }
                $date = new DateTimeImmutable(trim($value));
                $errors = DateTimeImmutable::getLastErrors();
                if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                    throw new RuntimeException('Tanggal kalender tidak valid.');
                }
            }

            return $date->format($column === 'tgl_inject' ? 'Y-m-d' : 'Y-m-d H:i:s');
        } catch (Throwable $error) {
            throw new RuntimeException("Tanggal {$column} Monitoring KSBK tidak valid: ".$error->getMessage(), 0, $error);
        }
    }

    private function assertCanonicalHeader(array|false $header): void
    {
        if ($header !== self::IMPORT_COLUMNS) {
            throw new RuntimeException('Header staging Monitoring KSBK tidak sesuai; upload ulang workbook sumber.');
        }
    }

    private function sheetPath(string $source): string
    {
        $workbook = $this->xml($source, 'xl/workbook.xml');
        $relation = null;
        try {
            while ($workbook->read()) {
                if ($workbook->nodeType === XMLReader::ELEMENT && $workbook->localName === 'workbookPr'
                    && in_array(strtolower((string) $workbook->getAttribute('date1904')), ['1', 'true'], true)) {
                    $this->excelCalendar = Date::CALENDAR_MAC_1904;
                }
                if ($workbook->nodeType === XMLReader::ELEMENT && $workbook->localName === 'sheet' && $workbook->getAttribute('name') === self::SHEET) {
                    if ($relation !== null) {
                        throw new RuntimeException('Nama sheet Monitoring KSBK duplikat.');
                    }
                    $relation = $workbook->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships') ?? $workbook->getAttribute('r:id');
                }
            }
        } finally {
            $workbook->close();
        }
        if ($relation === null) {
            throw new RuntimeException('Sheet '.self::SHEET.' tidak ditemukan dalam workbook Monitoring KSBK.');
        }
        $relations = $this->xml($source, 'xl/_rels/workbook.xml.rels');
        try {
            while ($relations->read()) {
                if ($relations->nodeType === XMLReader::ELEMENT && $relations->localName === 'Relationship' && $relations->getAttribute('Id') === $relation) {
                    if ($relations->getAttribute('TargetMode') === 'External') {
                        throw new RuntimeException('Sheet eksternal Monitoring KSBK tidak diterima.');
                    }
                    $target = str_replace('\\', '/', (string) $relations->getAttribute('Target'));
                    $path = [];
                    foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target) as $part) {
                        if ($part === '..') {
                            if ($path === []) {
                                throw new RuntimeException('Lokasi sheet Monitoring KSBK tidak valid.');
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
        throw new RuntimeException('Relasi sheet Monitoring KSBK tidak ditemukan.');
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
            throw new RuntimeException('Tidak dapat membuat penyimpanan teks sementara Monitoring KSBK.');
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
            throw new RuntimeException('Referensi teks workbook Monitoring KSBK tidak valid.');
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
            throw new RuntimeException('Teks workbook Monitoring KSBK tidak terbaca sempurna.');
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
                                throw new RuntimeException('Posisi sel workbook Monitoring KSBK tidak valid.');
                            }
                            $index = 0;
                            foreach (str_split($parts[1]) as $letter) {
                                $index = $index * 26 + ord($letter) - 64;
                            }
                            if ($index > count(self::SOURCE_HEADERS) || isset($cells[$index - 1])) {
                                throw new RuntimeException("Jumlah/posisi kolom workbook Monitoring KSBK tidak sesuai pada baris {$record}.");
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

        return ['value' => $value, 'numeric' => $type === 'n', 'formula' => $formula, 'error' => in_array($type, ['e', 'b'], true)];
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
            throw new RuntimeException('Struktur XML workbook Monitoring KSBK tidak dapat dibaca.');
        }
        $xml->setParserProperty(XMLReader::LOADDTD, false);
        $xml->setParserProperty(XMLReader::SUBST_ENTITIES, false);

        return $xml;
    }

    private function assertDifferentPaths(string $source, string $destination): void
    {
        if (realpath($source) !== false && realpath($source) === realpath($destination)) {
            throw new RuntimeException('File sumber dan staging Monitoring KSBK harus berbeda.');
        }
    }

    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException('File Monitoring KSBK tidak dapat dibuka.');
        }

        return $handle;
    }

    private function writeCsv($handle, array $row): void
    {
        if (fputcsv($handle, $row, ',', '"', '') === false) {
            throw new RuntimeException('Gagal menulis staging Monitoring KSBK.');
        }
    }

    private function write($handle, string $record): void
    {
        if (fwrite($handle, $record) !== strlen($record)) {
            throw new RuntimeException('Gagal menulis seluruh record Monitoring KSBK.');
        }
    }
}
