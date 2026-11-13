<?php

namespace App\Services\Import;

use DateTimeImmutable;
use RuntimeException;

class EvaluasiKunjunganSourceService
{
    public const TABLE = 'evaluasi_aktivitas_kunjungan_pemasar';

    public const SOURCE_HEADERS = [
        'Textbox2', 'ID', 'TGL_KUNJUNGAN', 'LATITUDE', 'LONGITUDE', 'TIPE_KUNJUNGAN',
        'PN', 'NAMA_MANTRI', 'JABATAN', 'KODE_UNIT', 'NAMA_UKER', 'KODE_MAIN_BRANCH',
        'MAIN_BRANCH', 'KODE_REGION', 'REGION', 'STATUS_KUNJUNGAN', 'ALAMAT_KUNJUNGAN',
        'CIFNO', 'NAMA_DEBITUR', 'HASIL_KUNJUNGAN', 'DETAIL_HASIL_KUNJUNGAN',
        'PERKIRAAN_PLAFOND', 'CHANNEL', 'FEEDBACK_MASALAH',
    ];

    public const COLUMNS = [
        'nomor_sumber', 'id_aktivitas', 'tgl_kunjungan', 'latitude', 'longitude', 'tipe_kunjungan',
        'pn', 'nama_mantri', 'jabatan', 'kode_unit', 'nama_uker', 'kode_main_branch',
        'main_branch', 'kode_region', 'region', 'status_kunjungan', 'alamat_kunjungan',
        'cifno', 'nama_debitur', 'hasil_kunjungan', 'detail_hasil_kunjungan',
        'perkiraan_plafond', 'channel', 'feedback_masalah',
    ];

    public const IMPORT_COLUMNS = [...self::COLUMNS, 'periode', 'date_printed'];

    public const LONG_TEXT_COLUMNS = ['alamat_kunjungan', 'hasil_kunjungan', 'detail_hasil_kunjungan', 'feedback_masalah'];

    public const CODE_COLUMNS = ['nomor_sumber', 'id_aktivitas', 'pn', 'kode_unit', 'kode_main_branch', 'kode_region', 'cifno'];

    /** Read canonical CSV without generic repair or database lookups per record. */
    public function preview(string $path, ?callable $progress = null): array
    {
        $input = $this->open($path, 'rb');
        $preview = [];
        $distinct = array_fill(0, count(self::IMPORT_COLUMNS), []);
        $rows = 0;
        $metadata = null;
        $size = max(1, (int) filesize($path));
        try {
            if (fgetcsv($input, 0, ',', '"', '') !== self::IMPORT_COLUMNS) {
                throw new RuntimeException('Header staging Kunjungan Pemasar tidak memiliki metadata Date Printed; upload ulang CSV sumber asli.');
            }
            while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                if (count($row) !== count(self::IMPORT_COLUMNS)) {
                    throw new RuntimeException('Jumlah kolom staging Kunjungan Pemasar tidak sesuai pada record '.($rows + 2).'.');
                }
                $this->assertStagedMetadata($row, $rows + 2, $metadata);
                $rows++;
                if (count($preview) < 100) {
                    $preview[] = $row;
                }
                foreach ($row as $index => $value) {
                    if (count($distinct[$index]) < 256) {
                        $key = trim((string) $value);
                        $distinct[$index][$key === '' ? '(Blank)' : $key] = true;
                    }
                }
                if ($progress !== null && $rows % 25000 === 0) {
                    $progress($rows, min(1, ftell($input) / $size));
                }
            }
        } finally {
            fclose($input);
        }
        if ($rows === 0) {
            throw new RuntimeException('Staging Kunjungan Pemasar tidak memiliki data.');
        }
        $options = array_map(static function (array $values): array {
            $values = array_map('strval', array_keys($values));
            sort($values, SORT_STRING);

            return $values;
        }, $distinct);

        return [
            'headers' => self::IMPORT_COLUMNS, 'sourceHeaders' => self::IMPORT_COLUMNS,
            'preview' => $preview, 'formattedUniqueValues' => $options,
            // Queue initialization subtracts the header record from this total.
            'total_rows' => $rows + 1, 'header_index' => 0, 'delimiter' => ',',
            'staged_csv_path' => $path,
        ];
    }

    /** Remove only the export preamble; never cast source numbers through float. */
    public function stage(string $source, string $destination): array
    {
        if (realpath($source) === realpath($destination)) {
            throw new RuntimeException('File sumber dan staging harus berbeda.');
        }
        $printedAt = $this->printedAtFromSource($source);
        $period = substr($printedAt, 0, 10);
        $input = $this->open($source, 'rb');
        $output = $this->open($destination, 'wb');
        $foundHeader = false;
        $rows = 0;
        $recordNumber = 0;
        try {
            $this->writeCsv($output, self::IMPORT_COLUMNS);
            while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                $recordNumber++;
                if ($row === [null]) {
                    continue;
                }
                if (! $foundHeader) {
                    $labels = array_map(static fn ($value): string => trim((string) $value), $row);
                    $labels[0] = preg_replace('/^\xEF\xBB\xBF/', '', $labels[0]);
                    if ($labels === self::SOURCE_HEADERS) {
                        $foundHeader = true;

                        continue;
                    }
                    if ($recordNumber > 10 || ($labels[1] ?? '') === 'ID') {
                        throw new RuntimeException('Header CSV Evaluasi Aktivitas Kunjungan Pemasar tidak sesuai dengan 24 kolom sumber.');
                    }

                    continue;
                }
                $this->validateRow($row, $recordNumber);
                $this->writeCsv($output, [...$row, $period, $printedAt]);
                $rows++;
            }
            if (! $foundHeader || $rows === 0) {
                throw new RuntimeException('CSV tidak memiliki header dan data Evaluasi Aktivitas Kunjungan Pemasar yang valid.');
            }
        } catch (\Throwable $error) {
            fclose($output);
            @unlink($destination);
            throw $error;
        } finally {
            fclose($input);
        }
        fclose($output);

        return ['rows' => $rows, 'columns' => self::IMPORT_COLUMNS, 'periode' => $period, 'date_printed' => $printedAt];
    }

    public function printedAtFromSource(string $path): string
    {
        $input = $this->open($path, 'rb');
        $printedAt = null;
        try {
            for ($record = 0; $record < 10 && ($row = fgetcsv($input, 0, ',', '"', '')) !== false; $record++) {
                foreach ($row as $value) {
                    if (preg_match('/^\s*Date\s+Printed\s*:/i', (string) $value) !== 1) {
                        continue;
                    }
                    $candidate = $this->parsePrintedAt((string) $value);
                    if ($printedAt !== null && $printedAt !== $candidate) {
                        throw new RuntimeException('CSV memuat Date Printed yang saling berbeda.');
                    }
                    $printedAt = $candidate;
                }
                $labels = array_map(static fn ($value): string => trim((string) $value), $row);
                $labels[0] = preg_replace('/^\xEF\xBB\xBF/', '', $labels[0] ?? '');
                if ($labels === self::SOURCE_HEADERS) {
                    break;
                }
            }
        } finally {
            fclose($input);
        }
        if ($printedAt === null) {
            throw new RuntimeException('Date Printed wajib ada pada metadata CSV sumber asli Kunjungan Pemasar.');
        }

        return $printedAt;
    }

    public function parsePrintedAt(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        if (preg_match('/^Date Printed\s*:\s*(\d{1,2}) ([A-Za-z]{3}) (\d{4}) (\d{1,2}):(\d{2}):(\d{2}) (AM|PM)$/iD', $value, $parts) !== 1) {
            throw new RuntimeException('Format Date Printed CSV Kunjungan Pemasar tidak valid.');
        }
        $normalized = sprintf('%02d %s %04d %02d:%02d:%02d %s', (int) $parts[1], ucfirst(strtolower($parts[2])), (int) $parts[3], (int) $parts[4], (int) $parts[5], (int) $parts[6], strtoupper($parts[7]));
        $date = DateTimeImmutable::createFromFormat('!d M Y h:i:s A', $normalized);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || $date->format('d M Y h:i:s A') !== $normalized
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException('Tanggal atau jam Date Printed CSV Kunjungan Pemasar tidak valid.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function assertStagedMetadata(array $row, int $record, ?array &$expected): void
    {
        $metadata = [$row[24] ?? null, $row[25] ?? null];
        if ($expected === null) {
            $printed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $metadata[1]);
            if (! $printed || $printed->format('Y-m-d H:i:s') !== $metadata[1] || substr((string) $metadata[1], 0, 10) !== $metadata[0]) {
                throw new RuntimeException("Metadata periode/Date Printed tidak valid pada record {$record}.");
            }
            $expected = $metadata;
        } elseif ($metadata !== $expected) {
            throw new RuntimeException("Metadata periode/Date Printed berubah pada record {$record}.");
        }
    }

    public function validateRow(array $row, int $recordNumber): void
    {
        if (count($row) !== count(self::COLUMNS)) {
            throw new RuntimeException("Jumlah kolom CSV tidak sesuai pada record {$recordNumber}; import dibatalkan.");
        }
        foreach (self::COLUMNS as $index => $column) {
            $value = (string) $row[$index];
            if (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                throw new RuntimeException("Encoding atau karakter NUL tidak valid pada {$column}, record {$recordNumber}.");
            }
            $longText = in_array($column, self::LONG_TEXT_COLUMNS, true);
            $maxLength = in_array($column, self::CODE_COLUMNS, true) ? 64 : 255;
            if (($longText && strlen($value) > 16777215) || (! $longText && mb_strlen($value, 'UTF-8') > $maxLength)) {
                throw new RuntimeException("Nilai {$column} melebihi kapasitas tabel pada record {$recordNumber}; nilai tidak dipotong.");
            }
        }
        foreach ([0, 1] as $index) {
            if (preg_match('/^\d+$/D', (string) $row[$index]) !== 1) {
                throw new RuntimeException('Nomor/ID aktivitas tidak valid pada record '.$recordNumber.'.');
            }
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row[2]);
        if (! $date || $date->format('Y-m-d') !== $row[2]) {
            throw new RuntimeException("TGL_KUNJUNGAN tidak valid pada record {$recordNumber}.");
        }
        foreach ([3 => [18, 6], 4 => [18, 6], 21 => [30, 2]] as $index => [$precision, $scale]) {
            $value = (string) $row[$index];
            if ($value === '') {
                continue;
            }
            if (preg_match('/^-?(\d+)(?:\.(\d+))?$/D', $value, $parts) !== 1
                || strlen(ltrim($parts[1] ?? '', '0')) > $precision - $scale
                || strlen($parts[2] ?? '') > $scale) {
                throw new RuntimeException(self::SOURCE_HEADERS[$index]." tidak valid atau melebihi presisi pada record {$recordNumber}; angka tidak dibulatkan.");
            }
        }
    }

    /** Prevalidate all records and write a MySQL-escaped, headerless bulk file. */
    public function prepareBulk(string $source, string $destination, array $headers, array $filters = [], ?callable $progress = null): array
    {
        $input = $this->open($source, 'rb');
        $output = $this->open($destination, 'wb');
        $rows = $scanned = 0;
        $timestamp = now()->format('Y-m-d H:i:s');
        $filterRules = [];
        $metadata = null;
        try {
            if (fgetcsv($input, 0, ',', '"', '') !== self::IMPORT_COLUMNS) {
                throw new RuntimeException('Header staging Kunjungan Pemasar tidak memiliki metadata Date Printed; upload ulang CSV sumber asli.');
            }
            foreach ($filters as $index => $values) {
                $column = strtolower((string) ($headers[$index] ?? ''));
                $sourceIndex = array_search($column, self::IMPORT_COLUMNS, true);
                if ($sourceIndex === false) {
                    throw new RuntimeException('Kolom filter Kunjungan Pemasar tidak dikenali.');
                }
                $filterRules[$sourceIndex] = array_fill_keys(array_map('strval', (array) $values), true);
            }
            while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
                $scanned++;
                if (count($row) !== count(self::IMPORT_COLUMNS)) {
                    throw new RuntimeException('Jumlah kolom CSV tidak sesuai pada record '.($scanned + 1).'.');
                }
                $this->validateRow(array_slice($row, 0, count(self::COLUMNS)), $scanned + 1);
                $this->assertStagedMetadata($row, $scanned + 1, $metadata);
                $selected = true;
                foreach ($filterRules as $index => $allowed) {
                    $value = trim((string) $row[$index]);
                    $value = $value === '' ? '(Blank)' : $value;
                    if (! isset($allowed[$value])) {
                        $selected = false;
                        break;
                    }
                }
                if ($selected) {
                    $values = $row;
                    foreach ([3, 4, 21] as $index) {
                        if ($values[$index] === '') {
                            $values[$index] = null;
                        }
                    }
                    $values = [...$values, 'kunjungan_'.$row[1], $timestamp, $timestamp];
                    $encoded = array_map([$this, 'encodeMysqlField'], $values);
                    $this->write($output, implode(',', $encoded)."\n");
                    $rows++;
                }
                if ($scanned % 25000 === 0 && $progress !== null) {
                    $progress($scanned, $rows);
                }
            }
            if ($rows === 0) {
                throw new RuntimeException('Tidak ada baris Kunjungan Pemasar yang terpilih untuk diimport.');
            }
        } catch (\Throwable $error) {
            fclose($output);
            @unlink($destination);
            throw $error;
        } finally {
            fclose($input);
        }
        fclose($output);

        return ['rows' => $rows, 'scanned' => $scanned, 'columns' => [...self::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at']];
    }

    /** One transaction: reject duplicate IDs, MySQL warnings, and row count differences. */
    public function loadBulk(string $path, int $expectedRows, ?\PDO $pdo = null): int
    {
        $loader = app(MySqlBulkLoadService::class);
        $loader->assertTransactionalTable(self::TABLE, 'import Kunjungan Pemasar');

        return $loader->withTableWriteLock(self::TABLE, function () use ($path, $expectedRows, $pdo, $loader): int {
            $pdo ??= $loader->createPersistentPdo();
            if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
                throw new RuntimeException('Import cepat Kunjungan Pemasar memerlukan MySQL/MariaDB.');
            }
            $columns = [...self::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at'];
            $variables = array_map(static fn ($index): string => '@kunjungan_'.$index, array_keys($columns));
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
                $affected = $pdo->exec($sql);
                // Read diagnostics immediately: any other statement can clear them.
                $warnings = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
                if ($warnings > 0 || $affected === false || (int) $affected !== $expectedRows) {
                    throw new RuntimeException('Import Kunjungan Pemasar dibatalkan: ID duplikat, warning MySQL, atau jumlah tersimpan berbeda dari sumber ('.(int) $affected.'/'.$expectedRows.', warnings '.$warnings.').');
                }
                $pdo->commit();

                return (int) $affected;
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        });
    }

    public function import(string $source, array $headers, array $filters = [], ?callable $progress = null): int
    {
        $bulkPath = tempnam(dirname($source), 'kunjungan_bulk_');
        if ($bulkPath === false) {
            throw new RuntimeException('Tidak dapat membuat file bulk Kunjungan Pemasar.');
        }
        try {
            $bulk = $this->prepareBulk($source, $bulkPath, $headers, $filters, $progress);

            return $this->loadBulk($bulkPath, $bulk['rows']);
        } finally {
            @unlink($bulkPath);
        }
    }

    public function encodeMysqlField(?string $value): string
    {
        if ($value === null) {
            return '\\N';
        }

        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\r" => '\\r', "\n" => '\\n', "\t" => '\\t', "\x1a" => '\\Z']).'"';
    }

    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException('File Kunjungan Pemasar tidak dapat dibuka.');
        }

        return $handle;
    }

    private function writeCsv($handle, array $row): void
    {
        if (fputcsv($handle, $row, ',', '"', '') === false) {
            throw new RuntimeException('Gagal menulis staging Kunjungan Pemasar.');
        }
    }

    private function write($handle, string $record): void
    {
        if (fwrite($handle, $record) !== strlen($record)) {
            throw new RuntimeException('Gagal menulis seluruh record bulk Kunjungan Pemasar.');
        }
    }
}
