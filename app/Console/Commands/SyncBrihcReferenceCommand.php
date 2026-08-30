<?php

namespace App\Console\Commands;

use App\Support\ReportCacheVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class SyncBrihcReferenceCommand extends Command
{
    protected $signature = 'reference:sync-brihc
        {file : Full path to the BRIHC workbook}
        {--dry-run : Validate and summarize the workbook without changing the database}';

    protected $description = 'Synchronize BRIHC personnel or PDWK decision-maker references from the latest workbook';

    private const PERSONNEL_REQUIRED_HEADERS = [
        'PERNR',
        'COMPLETENAME',
        'KELOMPOKJABATAN',
    ];

    private const PDWK_REQUIRED_HEADERS = [
        'PN',
        'NAMA',
        'JABATAN',
    ];

    private const HEADER_SCAN_COLUMN_LIMIT = 64;

    private const HEADER_SCAN_ROW_LIMIT = 50;

    private const AREA_6_BRANCHES = [
        'MADIUN' => ['label' => 'KC Madiun', 'code' => '45'],
        'MAGETAN' => ['label' => 'KC Magetan', 'code' => '49'],
        'NGAWI' => ['label' => 'KC Ngawi', 'code' => '57'],
        'PONOROGO' => ['label' => 'KC Ponorogo', 'code' => '70'],
    ];

    public function handle(): int
    {
        try {
            $path = trim((string) $this->argument('file'));
            $dryRun = (bool) $this->option('dry-run');

            if ($path === '' || ! is_file($path)) {
                throw new RuntimeException('File BRIHC tidak ditemukan: '.$path);
            }

            $this->ensureReferenceTablesExist();

            $source = $this->readSourceRows($path);
            if ($source['rows'] === []) {
                throw new RuntimeException('Tidak ada baris BRIHC yang valid untuk disinkronkan.');
            }

            $summary = [
                'source_file' => basename($path),
                'source_rows' => $source['source_rows'],
                'valid_rows' => count($source['rows']),
                'duplicate_pn_skipped' => $source['duplicate_pn_skipped'],
                'roles' => array_keys($source['role_tokens']),
                'source_format' => $source['format'],
                'sync_brihc_pemasar' => $source['sync_brihc_pemasar'],
                'would_delete_brihc' => $this->countReferenceRows('brihc', 'jabatan', $source['role_tokens']),
                'would_delete_brihc_pemasar' => $source['sync_brihc_pemasar']
                    ? $this->countReferenceRows('brihc_pemasar', 'positiondesc', $source['role_tokens'])
                    : 0,
                'dry_run' => $dryRun,
            ];

            if ($dryRun) {
                $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            $timestamp = now()->toDateTimeString();
            $result = DB::transaction(function () use ($source, $timestamp): array {
                $deletedBrihc = $this->deleteReferenceRows('brihc', 'jabatan', $source['role_tokens']);
                $this->insertInChunks('brihc', $this->brihcRows($source['rows'], $timestamp));

                $deletedPemasar = 0;
                $insertedPemasar = 0;
                if ($source['sync_brihc_pemasar']) {
                    $deletedPemasar = $this->deleteReferenceRows('brihc_pemasar', 'positiondesc', $source['role_tokens']);
                    $this->insertInChunks('brihc_pemasar', $this->brihcPemasarRows($source['rows'], $timestamp));
                    $insertedPemasar = count($source['rows']);
                }

                return [
                    'brihc_deleted' => $deletedBrihc,
                    'brihc_inserted' => count($source['rows']),
                    'brihc_pemasar_deleted' => $deletedPemasar,
                    'brihc_pemasar_inserted' => $insertedPemasar,
                ];
            });

            // Kinerja RM Mikro and PPT use the pinjaman cache version in their payload keys.
            ReportCacheVersion::bump('pinjaman');

            $this->line(json_encode(array_merge($summary, $result, [
                'cache_version_bumped' => 'pinjaman',
                'preserved' => [
                    'brihc_roles_outside_source' => 'not changed',
                    'brihc_pemasar' => $source['sync_brihc_pemasar'] ? 'synchronized' : 'not changed',
                    'wilayah_mbm' => 'not changed',
                ],
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi BRIHC gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function ensureReferenceTablesExist(): void
    {
        foreach (['brihc', 'brihc_pemasar'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tabel referensi {$table} tidak tersedia.");
            }
        }
    }

    /**
     * @return array{source_rows:int, rows:array<string, array<string, ?string>>, duplicate_pn_skipped:int, role_tokens:array<string, string>, format:string, sync_brihc_pemasar:bool}
     */
    private function readSourceRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);
        if ($this->isListRmWorkbook($workbook)) {
            return $this->readListRmSourceRows($workbook);
        }

        $sheet = $workbook->getActiveSheet();
        $header = $this->findHeader($sheet);
        $headerIndexes = $header['indexes'];
        $isPdwkFormat = $header['format'] === 'pdwk';

        $recordsByPn = [];
        $duplicatePnSkipped = 0;
        $sourceRows = 0;

        for ($rowNumber = $header['row'] + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $pnHeader = $isPdwkFormat ? 'PN' : 'PERNR';
            $nameHeader = $isPdwkFormat ? 'NAMA' : 'COMPLETENAME';
            $roleHeader = $isPdwkFormat ? 'JABATAN' : 'KELOMPOKJABATAN';
            $rawPn = $this->sourceValue($sheet, $rowNumber, $headerIndexes, $pnHeader);
            $name = $this->sourceValue($sheet, $rowNumber, $headerIndexes, $nameHeader);
            $sourceRole = $this->normalizeRole($this->sourceValue($sheet, $rowNumber, $headerIndexes, $roleHeader));

            if ($isPdwkFormat && $this->isPdwkStructuralRow($rawPn, $name, $sourceRole)) {
                continue;
            }

            $pn = $this->normalizePn($rawPn);
            $role = $isPdwkFormat ? $this->canonicalPdwkRole($sourceRole) : $sourceRole;
            if ($pn === '' && $name === null && $role === '') {
                continue;
            }

            $sourceRows++;
            if ($pn === '' || $name === '' || $role === '') {
                continue;
            }

            if (isset($recordsByPn[$pn])) {
                $duplicatePnSkipped++;
            }

            $recordsByPn[$pn] = [
                'pn' => $pn,
                'nama' => $name,
                'jabatan' => $role,
                'gender' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'GENDER'),
                'jg' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'JG'),
                'age' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'AGE'),
                'esgdesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'ESGDESC'),
                'padesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'PADESC'),
                'psadesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'PSADESC'),
                'orgdesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'ORGDESC'),
                'mkj' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'MKJ'),
                'descprogrammasuk' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'DESCPROGRAMMASUK'),
                'bc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'KODEBRANCH'),
            ];
        }

        $roleTokens = [];
        foreach ($recordsByPn as $record) {
            $role = (string) $record['jabatan'];
            $roleTokens[$role] = $this->referenceRoleToken($role);
        }

        return [
            'source_rows' => $sourceRows,
            'rows' => $recordsByPn,
            'duplicate_pn_skipped' => $duplicatePnSkipped,
            'role_tokens' => $roleTokens,
            'format' => $header['format'],
            'sync_brihc_pemasar' => ! $isPdwkFormat,
        ];
    }

    private function isListRmWorkbook(Spreadsheet $workbook): bool
    {
        $sheetNames = collect($workbook->getSheetNames())
            ->mapWithKeys(fn (string $name): array => [$this->normalizeHeader($name) => true]);

        return collect(['KONSUMER', 'RMMIKRO', 'RMSMALL'])
            ->every(fn (string $name): bool => $sheetNames->has($name));
    }

    /**
     * @return array{source_rows:int, rows:array<string, array<string, ?string>>, duplicate_pn_skipped:int, role_tokens:array<string, string>, format:string, sync_brihc_pemasar:bool}
     */
    private function readListRmSourceRows(Spreadsheet $workbook): array
    {
        $records = [];
        $sourceRows = 0;
        $duplicatePnSkipped = 0;

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $sheetKey = $this->normalizeHeader($sheet->getTitle());
            if (! in_array($sheetKey, ['KONSUMER', 'RMMIKRO', 'RMSMALL'], true)) {
                continue;
            }

            $headers = $this->headerIndexes($sheet, 1);
            $required = match ($sheetKey) {
                'KONSUMER' => ['KANCA', 'UKER', 'SEGMEN', 'PNPENGELOLASINGLEPN'],
                'RMMIKRO' => ['PN', 'NAMA', 'BCUKER', 'UKER', 'KANCA'],
                'RMSMALL' => ['KANCA', 'KODEUKER', 'UKER', 'PN', 'NAMARM'],
            };
            if (! $this->hasHeaders($headers, $required)) {
                throw new RuntimeException(
                    'Header sheet '.$sheet->getTitle().' tidak lengkap: '.implode(', ', $required)
                );
            }

            for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                $record = match ($sheetKey) {
                    'KONSUMER' => $this->listRmConsumerRecord($sheet, $rowNumber, $headers),
                    'RMMIKRO' => $this->listRmMicroRecord($sheet, $rowNumber, $headers),
                    'RMSMALL' => $this->listRmSmallRecord($sheet, $rowNumber, $headers),
                };
                if ($record === null) {
                    continue;
                }

                $sourceRows++;
                $key = $this->referenceRecordKey((string) $record['pn'], (string) $record['jabatan']);
                if (isset($records[$key])) {
                    $duplicatePnSkipped++;
                }
                $records[$key] = $record;
            }
        }

        $roleTokens = [];
        foreach ($records as $record) {
            $role = (string) $record['jabatan'];
            $roleTokens[$role] = $this->referenceRoleToken($role);
        }

        return [
            'source_rows' => $sourceRows,
            'rows' => $records,
            'duplicate_pn_skipped' => $duplicatePnSkipped,
            'role_tokens' => $roleTokens,
            'format' => 'list_rm',
            'sync_brihc_pemasar' => true,
        ];
    }

    /** @param array<string, int> $headers @return array<string, ?string>|null */
    private function listRmConsumerRecord(Worksheet $sheet, int $rowNumber, array $headers): ?array
    {
        $person = $this->sourceValue($sheet, $rowNumber, $headers, 'PNPENGELOLASINGLEPN');
        if (preg_match('/^\s*(\d+)\s*-+\s*(.+)$/u', (string) $person, $matches) !== 1) {
            return null;
        }

        $segment = $this->normalizeHeader($this->sourceValue($sheet, $rowNumber, $headers, 'SEGMEN'));
        $role = match (true) {
            str_contains($segment, 'KPR') => 'RM BISNIS KONSUMER - KPR',
            str_contains($segment, 'BRIGUNA') => 'RM BISNIS KONSUMER - BRIGUNA',
            default => '',
        };

        return $this->listRmRecord(
            $matches[1],
            $matches[2],
            $role,
            $this->sourceValue($sheet, $rowNumber, $headers, 'KANCA'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'UKER'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'BC')
        );
    }

    /** @param array<string, int> $headers @return array<string, ?string>|null */
    private function listRmMicroRecord(Worksheet $sheet, int $rowNumber, array $headers): ?array
    {
        return $this->listRmRecord(
            $this->sourceValue($sheet, $rowNumber, $headers, 'PN'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'NAMA'),
            'RM MIKRO',
            $this->sourceValue($sheet, $rowNumber, $headers, 'KANCA'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'UKER'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'BCUKER')
        );
    }

    /** @param array<string, int> $headers @return array<string, ?string>|null */
    private function listRmSmallRecord(Worksheet $sheet, int $rowNumber, array $headers): ?array
    {
        return $this->listRmRecord(
            $this->sourceValue($sheet, $rowNumber, $headers, 'PN'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'NAMARM'),
            'RM BISNIS KECIL',
            $this->sourceValue($sheet, $rowNumber, $headers, 'KANCA'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'UKER'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'KODEUKER'),
            $this->sourceValue($sheet, $rowNumber, $headers, 'JG')
        );
    }

    /** @return array<string, ?string>|null */
    private function listRmRecord(
        ?string $rawPn,
        ?string $rawName,
        string $role,
        ?string $rawBranch,
        ?string $rawUnit,
        ?string $rawBc,
        ?string $jg = null
    ): ?array {
        $pn = $this->normalizePn($rawPn);
        $name = trim((string) $rawName);
        $branch = $this->area6Branch($rawBranch);
        if ($pn === '' || $name === '' || $role === '' || $branch === null) {
            return null;
        }

        $bc = $this->normalizeCode($rawBc);

        return [
            'pn' => $pn,
            'nama' => $name,
            'jabatan' => $role,
            'gender' => null,
            'jg' => trim((string) $jg) ?: null,
            'age' => null,
            'esgdesc' => null,
            'padesc' => 'Region 13 Malang',
            'psadesc' => $branch['label'],
            'orgdesc' => $this->cleanListRmUnit($rawUnit) ?: $branch['label'],
            'mkj' => null,
            'descprogrammasuk' => null,
            'bc' => $bc !== '' ? $bc : $branch['code'],
        ];
    }

    /** @return array{label:string,code:string}|null */
    private function area6Branch(?string $value): ?array
    {
        $normalized = strtoupper(trim((string) $value));
        foreach (self::AREA_6_BRANCHES as $name => $branch) {
            if (str_contains($normalized, $name)) {
                return $branch;
            }
        }

        return null;
    }

    private function cleanListRmUnit(?string $value): string
    {
        return trim((string) preg_replace('/^\s*\d+\s*-+\s*/', '', trim((string) $value)));
    }

    private function normalizeCode(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        return ltrim($digits, '0');
    }

    /** @return array<string, int> */
    private function headerIndexes(Worksheet $sheet, int $rowNumber): array
    {
        $indexes = [];
        for ($columnIndex = 1; $columnIndex <= self::HEADER_SCAN_COLUMN_LIMIT; $columnIndex++) {
            $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowNumber;
            $header = $this->normalizeHeader($sheet->getCell($coordinate)->getFormattedValue());
            if ($header !== '') {
                $indexes[$header] = $columnIndex - 1;
            }
        }

        return $indexes;
    }

    private function referenceRecordKey(string $pn, string $role): string
    {
        return $this->normalizeHeader($role).'|'.$pn;
    }

    /**
     * @return array{row:int, indexes:array<string, int>, format:string}
     */
    private function findHeader(Worksheet $sheet): array
    {
        $lastRow = min($sheet->getHighestDataRow(), self::HEADER_SCAN_ROW_LIMIT);

        for ($rowNumber = 1; $rowNumber <= $lastRow; $rowNumber++) {
            $indexes = [];
            for ($columnIndex = 1; $columnIndex <= self::HEADER_SCAN_COLUMN_LIMIT; $columnIndex++) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowNumber;
                $header = $this->normalizeHeader($sheet->getCell($coordinate)->getFormattedValue());
                if ($header !== '') {
                    $indexes[$header] = $columnIndex - 1;
                }
            }

            if ($this->hasHeaders($indexes, self::PERSONNEL_REQUIRED_HEADERS)) {
                return ['row' => $rowNumber, 'indexes' => $indexes, 'format' => 'personnel'];
            }

            if ($this->hasHeaders($indexes, self::PDWK_REQUIRED_HEADERS)) {
                return ['row' => $rowNumber, 'indexes' => $indexes, 'format' => 'pdwk'];
            }
        }

        throw new RuntimeException(
            'Header wajib tidak ditemukan. Format yang didukung: '
            .implode(', ', self::PERSONNEL_REQUIRED_HEADERS)
            .' atau '
            .implode(', ', self::PDWK_REQUIRED_HEADERS)
        );
    }

    /** @param array<string, int> $indexes @param array<int, string> $requiredHeaders */
    private function hasHeaders(array $indexes, array $requiredHeaders): bool
    {
        foreach ($requiredHeaders as $header) {
            if (! array_key_exists($header, $indexes)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, int> $headerIndexes */
    private function sourceValue(Worksheet $sheet, int $rowNumber, array $headerIndexes, string $header): ?string
    {
        $index = $headerIndexes[$header] ?? null;
        if ($index === null) {
            return null;
        }

        $coordinate = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;
        $value = trim((string) $sheet->getCell($coordinate)->getFormattedValue());

        return $value !== '' ? $value : null;
    }

    private function isPdwkStructuralRow(?string $pn, ?string $name, string $role): bool
    {
        $normalizedPn = $this->normalizeHeader($pn);

        return ($normalizedPn === 'PN' && $this->normalizeHeader($name) === 'NAMA')
            || ($name === null && $role === '' && in_array($normalizedPn, ['KAUNIT', 'MBM', 'PINCA'], true))
            || ($pn === null && $name === null && $role === 'JABATAN');
    }

    private function canonicalPdwkRole(string $role): string
    {
        return match ($role) {
            'BOH' => 'PINCA',
            'SBOH' => 'MBM',
            default => $role,
        };
    }

    /** @param array<string, array<string, ?string>> $records */
    private function brihcRows(array $records, string $timestamp): array
    {
        return array_values(array_map(function (array $record) use ($timestamp): array {
            $roleKey = strtolower(trim($this->normalizeHeader($record['jabatan']), '_'));

            return [
                'uniqueid_brihc' => 'reference_brihc_'.$roleKey.'_'.$record['pn'].'_BRIHC',
                'pn' => $record['pn'],
                'nama' => $record['nama'],
                'jabatan' => $record['jabatan'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $records));
    }

    /** @param array<string, array<string, ?string>> $records */
    private function brihcPemasarRows(array $records, string $timestamp): array
    {
        $availableColumns = array_flip(Schema::getColumnListing('brihc_pemasar'));

        return array_values(array_map(function (array $record) use ($timestamp, $availableColumns): array {
            $roleKey = strtolower(trim($this->normalizeHeader($record['jabatan']), '_'));
            $isMantri = str_contains($this->normalizeHeader($record['jabatan']), 'MANTRI');
            $row = [
                'uniqueid_namareport' => 'reference_brihc_'.$roleKey.'_'.$record['pn'],
                'completename' => $record['nama'],
                'pernr' => $record['pn'],
                'sex' => $record['gender'],
                'age' => $record['age'],
                'esgdesc' => $record['esgdesc'],
                'padesc' => $record['padesc'],
                'psadesc' => $record['psadesc'],
                'orgdesc' => $record['orgdesc'],
                'positiondesc' => $record['jabatan'],
                'mkj' => $record['mkj'],
                'descprogrammasuk' => $record['descprogrammasuk'],
                'jobgrade' => $record['jg'],
                'bc' => $record['bc'],
                'pn_mantri' => $isMantri ? $record['pn'] : '-',
                'status' => $record['descprogrammasuk'],
                'jg' => $record['jg'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            return array_filter(
                $row,
                static fn (string $column): bool => isset($availableColumns[$column]),
                ARRAY_FILTER_USE_KEY
            );
        }, $records));
    }

    /** @param array<string, string> $roleTokens */
    private function deleteReferenceRows(string $table, string $roleColumn, array $roleTokens): int
    {
        return $this->referenceRowsQuery($table, $roleColumn, $roleTokens)->delete();
    }

    /** @param array<string, string> $roleTokens */
    private function countReferenceRows(string $table, string $roleColumn, array $roleTokens): int
    {
        return $this->referenceRowsQuery($table, $roleColumn, $roleTokens)->count();
    }

    /** @param array<string, string> $roleTokens */
    private function referenceRowsQuery(string $table, string $roleColumn, array $roleTokens): mixed
    {
        $tokens = array_values(array_unique(array_filter($roleTokens)));
        if ($tokens === []) {
            return DB::table($table)->whereRaw('1 = 0');
        }

        return DB::table($table)
            ->where(function ($query) use ($roleColumn, $tokens): void {
                foreach ($tokens as $token) {
                    $query->orWhereRaw(
                        "UPPER(TRIM(COALESCE({$roleColumn}, ''))) LIKE ?",
                        ['%'.$this->escapeLike($token).'%']
                    );
                }
            });
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function insertInChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function normalizeHeader(mixed $header): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim((string) $header))) ?? '';
    }

    private function normalizePn(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        return ltrim($digits, '0');
    }

    private function normalizeRole(?string $value): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $value)));
    }

    private function referenceRoleToken(string $role): string
    {
        return str_contains($role, 'MANTRI') ? 'MANTRI' : $role;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
