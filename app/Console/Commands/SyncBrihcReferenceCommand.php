<?php

namespace App\Console\Commands;

use App\Support\ReportCacheVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class SyncBrihcReferenceCommand extends Command
{
    protected $signature = 'reference:sync-brihc
        {file : Full path to the BRIHC workbook}
        {--dry-run : Validate and summarize the workbook without changing the database}';

    protected $description = 'Synchronize the latest BRIHC Mantri reference without removing MBM and decision-maker master data';

    private const REQUIRED_HEADERS = [
        'PERNR',
        'COMPLETENAME',
        'KELOMPOKJABATAN',
    ];

    public function handle(): int
    {
        try {
            $path = trim((string) $this->argument('file'));
            $dryRun = (bool) $this->option('dry-run');

            if ($path === '' || !is_file($path)) {
                throw new RuntimeException('File BRIHC tidak ditemukan: ' . $path);
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
                'dry_run' => $dryRun,
            ];

            if ($dryRun) {
                $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            $timestamp = now()->toDateTimeString();
            $result = DB::transaction(function () use ($source, $timestamp): array {
                $deletedBrihc = $this->deleteReferenceRows('brihc', 'jabatan', $source['role_tokens']);
                $deletedPemasar = $this->deleteReferenceRows('brihc_pemasar', 'positiondesc', $source['role_tokens']);

                $this->insertInChunks('brihc', $this->brihcRows($source['rows'], $timestamp));
                $this->insertInChunks('brihc_pemasar', $this->brihcPemasarRows($source['rows'], $timestamp));

                return [
                    'brihc_deleted' => $deletedBrihc,
                    'brihc_inserted' => count($source['rows']),
                    'brihc_pemasar_deleted' => $deletedPemasar,
                    'brihc_pemasar_inserted' => count($source['rows']),
                ];
            });

            // Kinerja RM Mikro and PPT use the pinjaman cache version in their payload keys.
            ReportCacheVersion::bump('pinjaman');

            $this->line(json_encode(array_merge($summary, $result, [
                'cache_version_bumped' => 'pinjaman',
                'preserved' => [
                    'brihc_roles_outside_source' => 'MBM/KAUNIT/PINCA/RMBH',
                    'wilayah_mbm' => 'not changed',
                ],
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi BRIHC gagal: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function ensureReferenceTablesExist(): void
    {
        foreach (['brihc', 'brihc_pemasar'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Tabel referensi {$table} tidak tersedia.");
            }
        }
    }

    /**
     * @return array{source_rows:int, rows:array<string, array<string, ?string>>, duplicate_pn_skipped:int, role_tokens:array<string, string>}
     */
    private function readSourceRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headers = array_shift($rows) ?? [];
        $headerIndexes = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeHeader($header);
            if ($normalizedHeader !== '') {
                $headerIndexes[$normalizedHeader] = $index;
            }
        }

        $missingHeaders = array_values(array_filter(
            self::REQUIRED_HEADERS,
            fn (string $header): bool => !array_key_exists($header, $headerIndexes)
        ));
        if ($missingHeaders !== []) {
            throw new RuntimeException('Header wajib tidak ditemukan: ' . implode(', ', $missingHeaders));
        }

        $recordsByPn = [];
        $roleTokens = [];
        $duplicatePnSkipped = 0;
        $sourceRows = 0;

        foreach ($rows as $row) {
            $pn = $this->normalizePn($this->sourceValue($row, $headerIndexes, 'PERNR'));
            $name = $this->sourceValue($row, $headerIndexes, 'COMPLETENAME');
            $role = $this->normalizeRole($this->sourceValue($row, $headerIndexes, 'KELOMPOKJABATAN'));
            if ($pn === '' && $name === '' && $role === '') {
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
                'gender' => $this->sourceValue($row, $headerIndexes, 'GENDER'),
                'jg' => $this->sourceValue($row, $headerIndexes, 'JG'),
                'age' => $this->sourceValue($row, $headerIndexes, 'AGE'),
                'esgdesc' => $this->sourceValue($row, $headerIndexes, 'ESGDESC'),
                'padesc' => $this->sourceValue($row, $headerIndexes, 'PADESC'),
                'psadesc' => $this->sourceValue($row, $headerIndexes, 'PSADESC'),
                'orgdesc' => $this->sourceValue($row, $headerIndexes, 'ORGDESC'),
                'mkj' => $this->sourceValue($row, $headerIndexes, 'MKJ'),
                'descprogrammasuk' => $this->sourceValue($row, $headerIndexes, 'DESCPROGRAMMASUK'),
                'bc' => $this->sourceValue($row, $headerIndexes, 'KODEBRANCH'),
            ];

            $roleTokens[$role] = $this->referenceRoleToken($role);
        }

        return [
            'source_rows' => $sourceRows,
            'rows' => $recordsByPn,
            'duplicate_pn_skipped' => $duplicatePnSkipped,
            'role_tokens' => $roleTokens,
        ];
    }

    /** @param array<int, mixed> $row @param array<string, int> $headerIndexes */
    private function sourceValue(array $row, array $headerIndexes, string $header): ?string
    {
        $index = $headerIndexes[$header] ?? null;
        if ($index === null) {
            return null;
        }

        $value = trim((string) ($row[$index] ?? ''));

        return $value !== '' ? $value : null;
    }

    /** @param array<string, array<string, ?string>> $records */
    private function brihcRows(array $records, string $timestamp): array
    {
        return array_values(array_map(static fn (array $record): array => [
            'uniqueid_brihc' => 'reference_brihc_' . $record['pn'] . '_BRIHC',
            'pn' => $record['pn'],
            'nama' => $record['nama'],
            'jabatan' => $record['jabatan'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $records));
    }

    /** @param array<string, array<string, ?string>> $records */
    private function brihcPemasarRows(array $records, string $timestamp): array
    {
        $availableColumns = array_flip(Schema::getColumnListing('brihc_pemasar'));

        return array_values(array_map(function (array $record) use ($timestamp, $availableColumns): array {
            $row = [
                'uniqueid_namareport' => 'reference_brihc_mantri_' . $record['pn'],
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
                'pn_mantri' => $record['pn'],
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
        $tokens = array_values(array_unique(array_filter($roleTokens)));
        if ($tokens === []) {
            return 0;
        }

        return DB::table($table)
            ->where(function ($query) use ($roleColumn, $tokens): void {
                foreach ($tokens as $token) {
                    $query->orWhereRaw(
                        "UPPER(TRIM(COALESCE({$roleColumn}, ''))) LIKE ?",
                        ['%' . $this->escapeLike($token) . '%']
                    );
                }
            })
            ->delete();
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
