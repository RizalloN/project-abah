<?php

namespace App\Console\Commands;

use App\Support\ReportCacheVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
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

    private const MANTRI_REFERENCE_REQUIRED_HEADERS = [
        'PERSONALNUMBER',
        'NAMAPEKERJA',
        'JABATAN',
    ];

    private const UPDATE_HC_REQUIRED_HEADERS = [
        'PSADESC',
        'NIP',
        'PERNR',
        'COMPLETENAME',
        'ESGDESC',
        'JG',
        'PG',
        'JOBDESC',
        'BC',
        'UKER',
    ];

    private const UPDATE_HC_SHEETS = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];

    private const UPDATE_HC_MANTRI_SHEETS = [
        'MANTRITUMBUH' => 'MANTRI',
        'MANTRIBRIGUNA' => 'MANTRI BRIGUNA',
        'MANTRIRECOVERY' => 'MANTRI RECOVERY',
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

            $source = $this->preserveMantriReferenceFields($source);
            $source = $this->resolveIncompleteReferenceFields($source);
            $source['stale_brihc_pairs'] = $source['format'] === 'update_hc'
                ? $this->scopedReferencePairs($source['role_tokens'], $source['branch_labels'])
                : [];
            if ($source['unresolved_incomplete_fields'] !== []) {
                throw new RuntimeException(
                    'Data target memiliki BC/UKER kosong yang tidak dapat dipulihkan dari referensi lama: '
                    .implode(', ', array_slice($source['unresolved_incomplete_fields'], 0, 10))
                );
            }

            $summary = [
                'source_file' => basename($path),
                'source_rows' => $source['source_rows'],
                'valid_rows' => count($source['rows']),
                'duplicate_pn_skipped' => $source['duplicate_pn_skipped'],
                'roles' => array_keys($source['role_tokens']),
                'source_format' => $source['format'],
                'sync_brihc_pemasar' => $source['sync_brihc_pemasar'],
                'incomplete_fields' => $source['incomplete_fields'],
                'incomplete_fields_preserved' => $source['incomplete_fields_preserved'],
                'incomplete_fields_defaulted' => $source['incomplete_fields_defaulted'],
                'unresolved_incomplete_fields' => $source['unresolved_incomplete_fields'],
                'would_delete_brihc' => $source['format'] === 'update_hc'
                    ? $this->countBrihcReferencePairs($source['stale_brihc_pairs'])
                    : ($source['format'] === 'mantri_reference'
                        ? 0
                        : $this->countReferenceRows('brihc', 'jabatan', $source['role_tokens'])),
                'would_delete_brihc_pemasar' => $source['sync_brihc_pemasar']
                    ? (in_array($source['format'], ['mantri_reference', 'update_hc'], true)
                        ? ($source['format'] === 'update_hc'
                            ? $this->countExactReferenceRowsForBranches('brihc_pemasar', 'positiondesc', $source['role_tokens'], $source['branch_labels'])
                            : $this->countReferenceRowsForBranches('brihc_pemasar', 'positiondesc', $source['role_tokens'], $source['branch_labels']))
                        : $this->countReferenceRows('brihc_pemasar', 'positiondesc', $source['role_tokens']))
                    : 0,
                'dry_run' => $dryRun,
            ];

            if ($dryRun) {
                $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            $timestamp = now()->toDateTimeString();
            $result = DB::transaction(function () use ($source, $timestamp): array {
                $isScopedReference = in_array($source['format'], ['mantri_reference', 'update_hc'], true);
                $deletedBrihc = $source['format'] === 'update_hc'
                    ? $this->deleteBrihcReferencePairs($source['stale_brihc_pairs'])
                    : ($isScopedReference
                        ? 0
                        : $this->deleteReferenceRows('brihc', 'jabatan', $source['role_tokens']));
                $brihcRows = $this->brihcRows($source['rows'], $timestamp);
                if ($isScopedReference) {
                    $this->upsertInChunks('brihc', $brihcRows, ['uniqueid_brihc'], ['pn', 'nama', 'jabatan', 'updated_at']);
                } else {
                    $this->insertInChunks('brihc', $brihcRows);
                }

                $deletedPemasar = 0;
                $insertedPemasar = 0;
                if ($source['sync_brihc_pemasar']) {
                    $deletedPemasar = $isScopedReference
                        ? ($source['format'] === 'update_hc'
                            ? $this->deleteExactReferenceRowsForBranches('brihc_pemasar', 'positiondesc', $source['role_tokens'], $source['branch_labels'])
                            : $this->deleteReferenceRowsForBranches('brihc_pemasar', 'positiondesc', $source['role_tokens'], $source['branch_labels']))
                        : $this->deleteReferenceRows('brihc_pemasar', 'positiondesc', $source['role_tokens']);
                    $brihcPemasarRows = $this->brihcPemasarRows($source['rows'], $timestamp);
                    if ($isScopedReference) {
                        // A Mantri can move in from a branch outside the scoped cleanup.
                        // The deterministic reference key must update that existing row,
                        // rather than causing the entire atomic synchronization to fail.
                        $this->upsertInChunks(
                            'brihc_pemasar',
                            $brihcPemasarRows,
                            ['uniqueid_namareport'],
                            array_values(array_filter(
                                array_keys($brihcPemasarRows[0] ?? []),
                                static fn (string $column): bool => ! in_array($column, ['uniqueid_namareport', 'created_at'], true)
                            ))
                        );
                    } else {
                        $this->insertInChunks('brihc_pemasar', $brihcPemasarRows);
                    }
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
            ReportCacheVersion::bump('consumer');
            ReportCacheVersion::bump('brihc');

            $this->line(json_encode(array_merge($summary, $result, [
                'cache_version_bumped' => ['pinjaman', 'consumer', 'brihc'],
                'preserved' => [
                    'brihc_roles_outside_source' => 'not changed',
                    'brihc_pemasar_outside_source_branches' => in_array($source['format'], ['mantri_reference', 'update_hc'], true) ? 'not changed' : null,
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
        if ($this->isMantriUpdateHcWorkbook($workbook)) {
            return $this->readMantriUpdateHcSourceRows($workbook);
        }
        if ($this->isUpdateHcWorkbook($workbook)) {
            return $this->readUpdateHcSourceRows($workbook);
        }
        if ($this->isListRmWorkbook($workbook)) {
            return $this->readListRmSourceRows($workbook);
        }

        $sheet = $workbook->getActiveSheet();
        $header = $this->findHeader($sheet);
        $headerIndexes = $header['indexes'];
        $isPdwkFormat = $header['format'] === 'pdwk';
        $isMantriReferenceFormat = $header['format'] === 'mantri_reference';

        $recordsByPn = [];
        $duplicatePnSkipped = 0;
        $sourceRows = 0;

        for ($rowNumber = $header['row'] + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $pnHeader = match (true) {
                $isPdwkFormat => 'PN',
                $isMantriReferenceFormat => 'PERSONALNUMBER',
                default => 'PERNR',
            };
            $nameHeader = match (true) {
                $isPdwkFormat => 'NAMA',
                $isMantriReferenceFormat => 'NAMAPEKERJA',
                default => 'COMPLETENAME',
            };
            $roleHeader = $isPdwkFormat ? 'JABATAN' : ($isMantriReferenceFormat ? 'JABATAN' : 'KELOMPOKJABATAN');
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
                'gender' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, $isMantriReferenceFormat ? 'JENISKELAMIN' : 'GENDER'),
                'jg' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'JG'),
                'age' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'AGE'),
                'esgdesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'ESGDESC'),
                'padesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'PADESC'),
                'psadesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, $isMantriReferenceFormat ? 'KANCAINDUK' : 'PSADESC'),
                'orgdesc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, $isMantriReferenceFormat ? 'ORGHDESK' : 'ORGDESC')
                    ?? $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'UNITKERJA'),
                'mkj' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'MKJ'),
                'descprogrammasuk' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, 'DESCPROGRAMMASUK'),
                'bc' => $this->sourceValue($sheet, $rowNumber, $headerIndexes, $isMantriReferenceFormat ? 'KODEUNITKERJA' : 'KODEBRANCH'),
                'tmt_masuk' => $this->sourceDateValue($sheet, $rowNumber, $headerIndexes, 'TMTMASUK'),
                'tmt_jabatan' => $this->sourceDateValue($sheet, $rowNumber, $headerIndexes, 'TMTJABATAN'),
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
            'branch_labels' => $isMantriReferenceFormat
                ? array_values(array_unique(array_filter(array_map(
                    static fn (array $record): string => strtoupper(trim((string) ($record['psadesc'] ?? ''))),
                    $recordsByPn
                ))))
                : [],
        ];
    }

    private function isUpdateHcWorkbook(Spreadsheet $workbook): bool
    {
        $sheetNames = collect($workbook->getSheetNames())
            ->map(fn (string $name): string => $this->normalizeHeader($name));

        return collect(array_merge(['REKAP'], self::UPDATE_HC_SHEETS))
            ->every(fn (string $name): bool => $sheetNames->contains($name));
    }

    private function isMantriUpdateHcWorkbook(Spreadsheet $workbook): bool
    {
        foreach ($workbook->getSheetNames() as $name) {
            if (array_key_exists($this->normalizeHeader($name), self::UPDATE_HC_MANTRI_SHEETS)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function readMantriUpdateHcSourceRows(Spreadsheet $workbook): array
    {
        $sheetsByName = collect($workbook->getWorksheetIterator())
            ->mapWithKeys(fn (Worksheet $sheet): array => [$this->normalizeHeader($sheet->getTitle()) => $sheet]);
        foreach (array_merge(['REKAP'], array_keys(self::UPDATE_HC_MANTRI_SHEETS)) as $sheetName) {
            if (! $sheetsByName->get($sheetName) instanceof Worksheet) {
                throw new RuntimeException("Sheet Update HC Mantri wajib tidak ditemukan: {$sheetName}");
            }
        }

        $records = [];
        $pnLocations = [];
        $branchLabels = [];
        $sourceRows = 0;
        $duplicates = 0;
        foreach (self::UPDATE_HC_MANTRI_SHEETS as $sheetName => $expectedRole) {
            /** @var Worksheet $sheet */
            $sheet = $sheetsByName->get($sheetName);
            $headers = $this->headerIndexes($sheet, 1);
            if (! $this->hasHeaders($headers, self::UPDATE_HC_REQUIRED_HEADERS)) {
                $missing = array_values(array_diff(self::UPDATE_HC_REQUIRED_HEADERS, array_keys($headers)));
                throw new RuntimeException('Header sheet '.$sheet->getTitle().' tidak lengkap: '.implode(', ', $missing));
            }
            $seenHeaders = [];
            for ($column = 1; $column <= self::HEADER_SCAN_COLUMN_LIMIT; $column++) {
                $header = $this->normalizeHeader($sheet->getCell(Coordinate::stringFromColumnIndex($column).'1')->getFormattedValue());
                if (in_array($header, self::UPDATE_HC_REQUIRED_HEADERS, true) && isset($seenHeaders[$header])) {
                    throw new RuntimeException('Header ganda pada sheet '.$sheet->getTitle().': '.$header);
                }
                $seenHeaders[$header] = true;
            }

            for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                $values = [];
                foreach (self::UPDATE_HC_REQUIRED_HEADERS as $header) {
                    $values[$header] = $this->sourceValue($sheet, $rowNumber, $headers, $header);
                }
                if (count(array_filter($values, static fn (?string $value): bool => $value !== null)) === 0) {
                    continue;
                }

                $sourceRows++;
                $location = $sheet->getTitle().' baris '.$rowNumber;
                $pn = $this->normalizePn($values['PERNR']);
                $name = trim((string) $values['COMPLETENAME']);
                if ($pn === '' || preg_match('/^\d+$/D', (string) $values['PERNR']) !== 1 || $name === '') {
                    throw new RuntimeException("PN/nama target kosong atau tidak valid pada sheet {$location}.");
                }
                $role = match ($this->normalizeRole($values['JOBDESC'])) {
                    'ASSOCIATE MANTRI 1', 'ASSOCIATE MANTRI 2', 'JUNIOR ASSOCIATE MANTRI', 'MANTRI' => 'MANTRI',
                    'MANTRI BRIGUNA' => 'MANTRI BRIGUNA',
                    'MANTRI RECOVERY' => 'MANTRI RECOVERY',
                    default => '',
                };
                if ($role !== $expectedRole) {
                    throw new RuntimeException("JOBDESC tidak sesuai sheet {$location}: ".(string) $values['JOBDESC']);
                }
                $branch = $this->area6Branch($values['PSADESC']);
                if ($branch === null || ! in_array(
                    $this->normalizeHeader($values['PSADESC']),
                    [$this->normalizeHeader($branch['label']), $this->normalizeHeader(substr($branch['label'], 3))],
                    true
                )) {
                    throw new RuntimeException("PSADESC tidak valid pada sheet {$location}.");
                }
                $bc = $this->normalizeCode($values['BC']);
                if ($bc === '' || preg_match('/^\d+$/D', (string) $values['BC']) !== 1 || $values['UKER'] === null) {
                    throw new RuntimeException("BC/UKER kosong atau tidak valid pada sheet {$location}.");
                }

                $record = [
                    'pn' => $pn,
                    'nama' => $name,
                    'jabatan' => $role,
                    'gender' => null,
                    'jg' => $values['JG'],
                    'age' => null,
                    'esgdesc' => $values['ESGDESC'],
                    'padesc' => 'Region 13 Malang',
                    'psadesc' => $branch['label'],
                    'orgdesc' => $values['UKER'],
                    'mkj' => null,
                    'descprogrammasuk' => null,
                    'bc' => $bc,
                    'tmt_masuk' => null,
                    'tmt_jabatan' => null,
                    'nip' => $values['NIP'],
                    'pg' => $values['PG'],
                    'reference_prefix' => 'reference_brihc_hc_',
                    'source_jobdesc' => $this->normalizeRole($values['JOBDESC']),
                ];
                $key = $this->referenceRecordKey($pn, $role);
                if (isset($pnLocations[$pn])) {
                    if (! isset($records[$key]) || $records[$key] !== $record) {
                        throw new RuntimeException("PN {$pn} memiliki penugasan yang bertentangan pada {$pnLocations[$pn]} dan {$location}.");
                    }
                    $duplicates++;

                    continue;
                }

                $records[$key] = $record;
                $pnLocations[$pn] = $location;
                $branchLabels[$branch['label']] = $branch['label'];
            }
        }

        $roles = array_values(self::UPDATE_HC_MANTRI_SHEETS);

        return [
            'source_rows' => $sourceRows,
            'rows' => $records,
            'duplicate_pn_skipped' => $duplicates,
            'role_tokens' => array_combine($roles, $roles),
            'format' => 'update_hc',
            'layout' => 'update_hc_mantri_roles',
            'sync_brihc_pemasar' => true,
            'branch_labels' => array_values($branchLabels),
        ];
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function preserveMantriReferenceFields(array $source): array
    {
        if (($source['layout'] ?? null) !== 'update_hc_mantri_roles') {
            return $source;
        }

        $availableColumns = Schema::getColumnListing('brihc_pemasar');
        $preservedColumns = array_values(array_diff($availableColumns, [
            'id', 'uniqueid_namareport', 'completename', 'nip', 'pernr', 'esgdesc', 'psadesc',
            'orgdesc', 'positiondesc', 'jobgrade', 'pg', 'bc', 'pn_mantri', 'jg', 'created_at', 'updated_at',
        ]));
        $existing = DB::table('brihc_pemasar')
            ->select(array_merge(['pernr', 'positiondesc', 'psadesc'], $preservedColumns))
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(positiondesc, '')))"), array_values($source['role_tokens']))
            ->get()
            ->groupBy(fn ($row): string => $this->referenceRecordKey(
                $this->normalizePn((string) ($row->pernr ?? '')),
                $this->normalizeRole((string) ($row->positiondesc ?? ''))
            ));

        foreach ($source['rows'] as $key => &$record) {
            $matches = $existing->get($key, collect());
            $sameBranch = $matches->filter(fn ($row): bool => $this->normalizeHeader($row->psadesc ?? '') === $this->normalizeHeader($record['psadesc']));
            if ($sameBranch->isNotEmpty()) {
                $matches = $sameBranch;
            }
            $record['preserved_pemasar_fields'] = array_fill_keys($preservedColumns, null);
            if ($matches->isEmpty() && in_array('padesc', $preservedColumns, true)) {
                $record['preserved_pemasar_fields']['padesc'] = $record['padesc'];
            }
            foreach ($preservedColumns as $column) {
                $values = $matches->map(fn ($row): mixed => $row->{$column})
                    ->filter(static fn (mixed $value): bool => $value !== null && trim((string) $value) !== '')
                    ->unique()
                    ->values();
                if ($values->count() > 1) {
                    throw new RuntimeException("Nilai referensi lama {$column} bertentangan untuk PN {$record['pn']} {$record['jabatan']}.");
                }
                if ($values->isNotEmpty()) {
                    $record['preserved_pemasar_fields'][$column] = $values->first();
                } elseif ($matches->isNotEmpty()) {
                    $record['preserved_pemasar_fields'][$column] = $matches->first()->{$column};
                }
            }
        }
        unset($record);

        return $source;
    }

    /**
     * @return array{source_rows:int, rows:array<string, array<string, ?string>>, duplicate_pn_skipped:int, role_tokens:array<string, string>, format:string, sync_brihc_pemasar:bool, branch_labels:array<int, string>}
     */
    private function readUpdateHcSourceRows(Spreadsheet $workbook): array
    {
        $sheetsByName = collect($workbook->getWorksheetIterator())
            ->mapWithKeys(fn (Worksheet $sheet): array => [$this->normalizeHeader($sheet->getTitle()) => $sheet]);
        $records = [];
        $sourceRows = 0;
        $duplicates = 0;

        foreach (self::UPDATE_HC_SHEETS as $sheetName) {
            /** @var Worksheet|null $sheet */
            $sheet = $sheetsByName->get($sheetName);
            if (! $sheet instanceof Worksheet) {
                throw new RuntimeException("Sheet Update HC wajib tidak ditemukan: {$sheetName}");
            }

            $headers = $this->headerIndexes($sheet, 1);
            if (! $this->hasHeaders($headers, self::UPDATE_HC_REQUIRED_HEADERS)) {
                $missing = array_values(array_diff(self::UPDATE_HC_REQUIRED_HEADERS, array_keys($headers)));
                throw new RuntimeException('Header sheet '.$sheet->getTitle().' tidak lengkap: '.implode(', ', $missing));
            }

            for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                $pn = $this->normalizePn($this->sourceValue($sheet, $rowNumber, $headers, 'PERNR'));
                $name = trim((string) $this->sourceValue($sheet, $rowNumber, $headers, 'COMPLETENAME'));
                $role = $this->canonicalUpdateHcRole($this->sourceValue($sheet, $rowNumber, $headers, 'JOBDESC'));
                if ($pn === '' && $name === '' && $this->sourceValue($sheet, $rowNumber, $headers, 'JOBDESC') === null) {
                    continue;
                }
                if ($role === '') {
                    continue;
                }

                $sourceRows++;
                if ($pn === '' || $name === '') {
                    throw new RuntimeException("PN/nama target kosong pada sheet {$sheetName} baris {$rowNumber}.");
                }

                $branch = self::AREA_6_BRANCHES[$sheetName];
                $sourceBranch = $this->area6Branch($this->sourceValue($sheet, $rowNumber, $headers, 'PSADESC'));
                if ($sourceBranch === null || $sourceBranch['code'] !== $branch['code']) {
                    throw new RuntimeException("PSADESC tidak sesuai sheet {$sheetName} pada baris {$rowNumber}.");
                }

                $key = $this->referenceRecordKey($pn, $role);
                if (isset($records[$key])) {
                    $duplicates++;
                }
                $records[$key] = [
                    'pn' => $pn,
                    'nama' => $name,
                    'jabatan' => $role,
                    'gender' => null,
                    'jg' => $this->sourceValue($sheet, $rowNumber, $headers, 'JG'),
                    'age' => null,
                    'esgdesc' => $this->sourceValue($sheet, $rowNumber, $headers, 'ESGDESC'),
                    'padesc' => 'Region 13 Malang',
                    'psadesc' => $branch['label'],
                    'orgdesc' => $this->sourceValue($sheet, $rowNumber, $headers, 'UKER'),
                    'mkj' => null,
                    'descprogrammasuk' => null,
                    'bc' => $this->normalizeCode($this->sourceValue($sheet, $rowNumber, $headers, 'BC')) ?: null,
                    'tmt_masuk' => null,
                    'tmt_jabatan' => null,
                    'reference_prefix' => 'reference_brihc_hc_',
                ];
            }
        }

        $roleTokens = [];
        foreach ($records as $record) {
            $roleTokens[$record['jabatan']] = $record['jabatan'];
        }

        return [
            'source_rows' => $sourceRows,
            'rows' => $records,
            'duplicate_pn_skipped' => $duplicates,
            'role_tokens' => $roleTokens,
            'format' => 'update_hc',
            'sync_brihc_pemasar' => true,
            'branch_labels' => array_column(self::AREA_6_BRANCHES, 'label'),
        ];
    }

    private function canonicalUpdateHcRole(?string $value): string
    {
        return match ($this->normalizeRole($value)) {
            'ASSOCIATE MANTRI 1', 'ASSOCIATE MANTRI 2', 'JUNIOR ASSOCIATE MANTRI' => 'MANTRI',
            'MANTRI BRIGUNA' => 'MANTRI BRIGUNA',
            'RM BISNIS KECIL' => 'RM BISNIS KECIL',
            'RM MIKRO' => 'RM MIKRO',
            'RM BISNIS KONSUMER - BRIGUNA' => 'RM BISNIS KONSUMER - BRIGUNA',
            'RM BISNIS KONSUMER - KPR' => 'RM BISNIS KONSUMER - KPR',
            default => '',
        };
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function resolveIncompleteReferenceFields(array $source): array
    {
        $source['incomplete_fields'] = ['bc' => 0, 'uker' => 0];
        $source['incomplete_fields_preserved'] = ['bc' => 0, 'uker' => 0];
        $source['incomplete_fields_defaulted'] = ['bc' => 0, 'uker' => 0];
        $source['unresolved_incomplete_fields'] = [];
        if ($source['format'] !== 'update_hc') {
            return $source;
        }

        $existing = DB::table('brihc_pemasar')
            ->select('pernr', 'positiondesc', 'psadesc', 'bc', 'orgdesc')
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(positiondesc, '')))"), array_values($source['role_tokens']))
            ->get()
            ->groupBy(function ($row): string {
                return $this->referenceRecordKey(
                    $this->normalizePn((string) ($row->pernr ?? '')),
                    $this->normalizeRole((string) ($row->positiondesc ?? ''))
                );
            });

        foreach ($source['rows'] as $key => &$record) {
            $allMatches = $existing->get($key, collect())->values();
            $sameBranchMatches = $allMatches
                ->filter(fn ($row): bool => strtoupper(trim((string) ($row->psadesc ?? ''))) === strtoupper((string) $record['psadesc']))
                ->values();
            $matches = $sameBranchMatches->isNotEmpty() ? $sameBranchMatches : $allMatches;
            foreach (['bc' => 'bc', 'orgdesc' => 'uker'] as $recordField => $label) {
                if (trim((string) ($record[$recordField] ?? '')) !== '') {
                    continue;
                }

                $source['incomplete_fields'][$label]++;
                $values = $matches
                    ->map(fn ($row): string => trim((string) ($row->{$recordField} ?? '')))
                    ->filter()
                    ->unique()
                    ->values();
                if ($values->count() === 1) {
                    $record[$recordField] = $values->first();
                    $source['incomplete_fields_preserved'][$label]++;

                    continue;
                }

                $branch = $this->area6Branch($record['psadesc'] ?? null);
                if ($branch !== null && ! str_contains($this->normalizeRole($record['jabatan'] ?? null), 'MANTRI')) {
                    $record[$recordField] = $recordField === 'bc' ? $branch['code'] : $branch['label'];
                    $source['incomplete_fields_defaulted'][$label]++;

                    continue;
                }

                $source['unresolved_incomplete_fields'][] = $record['pn'].' '.$record['jabatan'].' '.$label;
            }
        }
        unset($record);

        return $source;
    }

    /** @param array<string, string> $roleTokens @param array<int, string> $branchLabels @return array<int, array{pn:string,role:string}> */
    private function scopedReferencePairs(array $roleTokens, array $branchLabels): array
    {
        return $this->exactReferenceRowsForBranchesQuery('brihc_pemasar', 'positiondesc', $roleTokens, $branchLabels)
            ->select('pernr', 'positiondesc')
            ->get()
            ->map(fn ($row): array => [
                'pn' => $this->normalizePn((string) ($row->pernr ?? '')),
                'role' => $this->normalizeRole((string) ($row->positiondesc ?? '')),
            ])
            ->filter(fn (array $pair): bool => $pair['pn'] !== '' && $pair['role'] !== '')
            ->unique(fn (array $pair): string => $this->referenceRecordKey($pair['pn'], $pair['role']))
            ->values()
            ->all();
    }

    /** @param array<int, array{pn:string,role:string}> $pairs */
    private function countBrihcReferencePairs(array $pairs): int
    {
        $count = 0;
        foreach (array_chunk($pairs, 200) as $chunk) {
            $count += $this->brihcReferencePairsQuery($chunk)->count();
        }

        return $count;
    }

    /** @param array<int, array{pn:string,role:string}> $pairs */
    private function deleteBrihcReferencePairs(array $pairs): int
    {
        $deleted = 0;
        foreach (array_chunk($pairs, 200) as $chunk) {
            $deleted += $this->brihcReferencePairsQuery($chunk)->delete();
        }

        return $deleted;
    }

    /** @param array<int, array{pn:string,role:string}> $pairs */
    private function brihcReferencePairsQuery(array $pairs): mixed
    {
        if ($pairs === []) {
            return DB::table('brihc')->whereRaw('1 = 0');
        }

        return DB::table('brihc')->where(function ($query) use ($pairs): void {
            foreach ($pairs as $pair) {
                $query->orWhere(function ($pairQuery) use ($pair): void {
                    $pairQuery->where('pn', $pair['pn'])
                        ->whereRaw("UPPER(TRIM(COALESCE(jabatan, ''))) = ?", [$pair['role']]);
                });
            }
        });
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
            'tmt_masuk' => null,
            'tmt_jabatan' => null,
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

            if ($this->hasHeaders($indexes, self::MANTRI_REFERENCE_REQUIRED_HEADERS)) {
                return ['row' => $rowNumber, 'indexes' => $indexes, 'format' => 'mantri_reference'];
            }

            if ($this->hasHeaders($indexes, self::PDWK_REQUIRED_HEADERS)) {
                return ['row' => $rowNumber, 'indexes' => $indexes, 'format' => 'pdwk'];
            }
        }

        throw new RuntimeException(
            'Header wajib tidak ditemukan. Format yang didukung: '
            .implode(', ', self::PERSONNEL_REQUIRED_HEADERS)
            .' atau '
            .implode(', ', self::MANTRI_REFERENCE_REQUIRED_HEADERS)
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

    /** @param array<string, int> $headerIndexes */
    private function sourceDateValue(Worksheet $sheet, int $rowNumber, array $headerIndexes, string $header): ?string
    {
        $index = $headerIndexes[$header] ?? null;
        if ($index === null) {
            return null;
        }

        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($index + 1).$rowNumber);
        $rawValue = $cell->getValue();
        if (is_numeric($rawValue) && (float) $rawValue > 20_000 && (float) $rawValue < 80_000) {
            return ExcelDate::excelToDateTimeObject((float) $rawValue)->format('Y-m-d');
        }

        $value = trim((string) $cell->getFormattedValue());
        if ($value === '' || str_starts_with($value, '#')) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd M Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        return null;
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
            $prefix = (string) ($record['reference_prefix'] ?? 'reference_brihc_');

            return [
                'uniqueid_brihc' => $prefix.$roleKey.'_'.$record['pn'].'_BRIHC',
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
            $prefix = (string) ($record['reference_prefix'] ?? 'reference_brihc_');
            $isMantri = str_contains($this->normalizeHeader($record['jabatan']), 'MANTRI');
            $row = [
                'uniqueid_namareport' => $prefix.$roleKey.'_'.$record['pn'],
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
                'tmt_masuk' => $record['tmt_masuk'],
                'tmt_jabatan' => $record['tmt_jabatan'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            foreach (['nip', 'pg'] as $column) {
                if (array_key_exists($column, $record)) {
                    $row[$column] = $record[$column];
                }
            }
            $row = array_replace($row, $record['preserved_pemasar_fields'] ?? []);

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

    /** @param array<string, string> $roleTokens @param array<int, string> $branchLabels */
    private function deleteReferenceRowsForBranches(string $table, string $roleColumn, array $roleTokens, array $branchLabels): int
    {
        return $this->referenceRowsForBranchesQuery($table, $roleColumn, $roleTokens, $branchLabels)->delete();
    }

    /** @param array<string, string> $roleTokens @param array<int, string> $branchLabels */
    private function countReferenceRowsForBranches(string $table, string $roleColumn, array $roleTokens, array $branchLabels): int
    {
        return $this->referenceRowsForBranchesQuery($table, $roleColumn, $roleTokens, $branchLabels)->count();
    }

    /** @param array<string, string> $roleTokens @param array<int, string> $branchLabels */
    private function deleteExactReferenceRowsForBranches(string $table, string $roleColumn, array $roleTokens, array $branchLabels): int
    {
        return $this->exactReferenceRowsForBranchesQuery($table, $roleColumn, $roleTokens, $branchLabels)->delete();
    }

    /** @param array<string, string> $roleTokens @param array<int, string> $branchLabels */
    private function countExactReferenceRowsForBranches(string $table, string $roleColumn, array $roleTokens, array $branchLabels): int
    {
        return $this->exactReferenceRowsForBranchesQuery($table, $roleColumn, $roleTokens, $branchLabels)->count();
    }

    /** @param array<string, string> $roleTokens @param array<int, string> $branchLabels */
    private function exactReferenceRowsForBranchesQuery(string $table, string $roleColumn, array $roleTokens, array $branchLabels): mixed
    {
        $roles = array_values(array_unique(array_filter(array_map([$this, 'normalizeRole'], $roleTokens))));
        $branches = array_values(array_unique(array_filter(array_map(
            static fn (string $branch): string => strtoupper(trim($branch)),
            $branchLabels
        ))));
        if ($roles === [] || $branches === [] || ! Schema::hasColumn($table, 'psadesc')) {
            return DB::table($table)->whereRaw('1 = 0');
        }

        return DB::table($table)
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE({$roleColumn}, '')))"), $roles)
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(psadesc, '')))"), $branches);
    }

    /** @param array<string, string> $roleTokens @param array<int, string> $branchLabels */
    private function referenceRowsForBranchesQuery(string $table, string $roleColumn, array $roleTokens, array $branchLabels): mixed
    {
        $query = $this->referenceRowsQuery($table, $roleColumn, $roleTokens);
        $branches = array_values(array_unique(array_filter(array_map(
            static fn (string $branch): string => strtoupper(trim($branch)),
            $branchLabels
        ))));

        if ($branches === [] || ! Schema::hasColumn($table, 'psadesc')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(DB::raw("UPPER(TRIM(COALESCE(psadesc, '')) )"), $branches);
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

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $uniqueBy @param array<int, string> $updateColumns */
    private function upsertInChunks(string $table, array $rows, array $uniqueBy, array $updateColumns): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
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
