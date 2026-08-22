<?php

namespace App\Services\Reports;

use App\Support\ReportCacheVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiPersonnelReferenceSyncService
{
    private const BRANCHES = [
        'MADIUN' => ['label' => 'KC Madiun', 'code' => '45'],
        'MAGETAN' => ['label' => 'KC Magetan', 'code' => '49'],
        'NGAWI' => ['label' => 'KC Ngawi', 'code' => '57'],
        'PONOROGO' => ['label' => 'KC Ponorogo', 'code' => '70'],
    ];

    /** @return array<string, int|bool|string> */
    public function sync(string $sheetKey, array $payload): array
    {
        if ($sheetKey === 'ka-unit') {
            return $this->syncMbmAssignments($payload);
        }

        if ($sheetKey === 'mbm') {
            return $this->syncMbmNames($payload);
        }

        $records = $this->personnelRecords($sheetKey, $payload);
        if ($records === [] || !Schema::hasTable('brihc') || !Schema::hasTable('brihc_pemasar')) {
            return $this->summary($sheetKey, count($records), 0, 0, true);
        }

        $now = now()->toDateTimeString();
        $existingBrihc = DB::table('brihc')
            ->select('uniqueid_brihc', 'pn', 'nama', 'jabatan', 'created_at')
            ->get()
            ->groupBy(fn ($row): string => $this->normalizePn($row->pn ?? ''));
        $pemasarColumns = Schema::getColumnListing('brihc_pemasar');
        $existingPemasar = DB::table('brihc_pemasar')
            ->select($pemasarColumns)
            ->get()
            ->groupBy(fn ($row): string => $this->normalizePn($row->pernr ?? $row->pn_mantri ?? ''));
        $brihcRows = [];
        $pemasarRows = [];
        $inserted = 0;
        $updated = 0;

        foreach ($records as $pn => $record) {
            $brihcMatches = $existingBrihc->get($pn, collect())
                ->filter(fn ($row): bool => $this->sameRole($row->jabatan ?? '', $record['role']))
                ->values();
            $pemasarMatches = $existingPemasar->get($pn, collect())
                ->filter(fn ($row): bool => $this->sameRole($row->positiondesc ?? '', $record['role']))
                ->values();
            $inserted += $brihcMatches->isEmpty() || $pemasarMatches->isEmpty() ? 1 : 0;
            $updated += $brihcMatches->isNotEmpty() || $pemasarMatches->isNotEmpty() ? 1 : 0;

            if ($brihcMatches->isEmpty()) {
                $brihcMatches = collect([(object) [
                    'uniqueid_brihc' => 'kpi_reference_' . $sheetKey . '_' . $pn . '_BRIHC',
                    'created_at' => $now,
                ]]);
            }
            foreach ($brihcMatches as $existing) {
                $brihcRows[] = [
                    'uniqueid_brihc' => (string) $existing->uniqueid_brihc,
                    'pn' => $pn,
                    'nama' => $record['name'],
                    'jabatan' => $record['role'],
                    'created_at' => $existing->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }

            if ($pemasarMatches->isEmpty()) {
                $pemasarMatches = collect([(object) [
                    'uniqueid_namareport' => 'kpi_reference_' . $sheetKey . '_' . $pn,
                    'created_at' => $now,
                ]]);
            }
            foreach ($pemasarMatches as $existing) {
                $pemasarRows[] = $this->pemasarRow($existing, $record, $pemasarColumns, $now);
            }
        }

        DB::transaction(function () use ($brihcRows, $pemasarRows, $pemasarColumns): void {
            foreach (array_chunk($brihcRows, 500) as $chunk) {
                DB::table('brihc')->upsert(
                    $chunk,
                    ['uniqueid_brihc'],
                    ['pn', 'nama', 'jabatan', 'updated_at']
                );
            }

            $updateColumns = array_values(array_intersect([
                'completename', 'pernr', 'esgdesc', 'padesc', 'psadesc', 'orgdesc',
                'positiondesc', 'jobgrade', 'bc', 'pn_mantri', 'status', 'jg',
                'bln_2026', 'updated_at',
            ], $pemasarColumns));
            foreach (array_chunk($pemasarRows, 500) as $chunk) {
                DB::table('brihc_pemasar')->upsert($chunk, ['uniqueid_namareport'], $updateColumns);
            }
        });

        if ($inserted + $updated > 0) {
            ReportCacheVersion::bump('pinjaman');
        }

        return $this->summary($sheetKey, count($records), $inserted, $updated, false);
    }

    /** @return array<string, array<string, ?string>> */
    private function personnelRecords(string $sheetKey, array $payload): array
    {
        $headers = array_values($payload['header'] ?? []);
        $rows = array_values($payload['rows'] ?? []);
        $indexes = match ($sheetKey) {
            'rm-sme' => [
                'person' => $this->headerIndex($headers, ['UKER', 'NAMA']),
                'branch' => $this->headerIndex($headers, ['BO', 'KANCA']),
                'jg' => $this->headerIndex($headers, ['JG']),
            ],
            'rm-mikro' => [
                'person' => $this->headerIndex($headers, ['NAMA']),
                'branch' => $this->headerIndex($headers, ['BC UKER', 'UKER', 'BO', 'KANCA']),
                'jg' => $this->headerIndex($headers, ['JG']),
            ],
            'consumer' => [
                'person' => $this->headerIndex($headers, ['PN PENGELOLA SINGLEPN', 'PN PENGELOLA', 'NAMA']),
                'branch' => $this->headerIndex($headers, ['KANCA', 'BO']),
                'segment' => $this->headerIndex($headers, ['SEGMEN']),
            ],
            'mantri' => [
                'person' => $this->headerIndex($headers, ['PN PENGELOLA', 'NAMA MANTRI', 'NAMA']),
                'pn' => $this->headerIndex($headers, ['PN']),
                'name' => $this->headerIndex($headers, ['NAMA']),
                'branch' => $this->headerIndex($headers, ['BO', 'KANCA']),
                'unit' => $this->headerIndex($headers, ['UKER', 'UNIT KERJA']),
                'bc' => $this->headerIndex($headers, ['BC']),
                'jg' => $this->headerIndex($headers, ['JG']),
                'status' => $this->headerIndex($headers, ['STATUS']),
                'july_unit' => $this->headerIndex($headers, ['JUL']),
            ],
            default => [],
        };

        if (($indexes['person'] ?? null) === null || ($indexes['branch'] ?? null) === null) {
            return [];
        }

        $records = [];
        foreach ($rows as $row) {
            $person = $this->personFromRow($row, $indexes);
            $branch = $this->branchInfo($this->cell($row, $indexes['branch']));
            if ($person['pn'] === '' || $person['name'] === '' || $branch === null) {
                continue;
            }

            $role = match ($sheetKey) {
                'rm-sme' => 'RM BISNIS KECIL',
                'rm-mikro' => 'RM MIKRO',
                'consumer' => str_contains(strtoupper($this->cell($row, $indexes['segment'] ?? null)), 'KPR')
                    ? 'RM BISNIS KONSUMER - KPR'
                    : 'RM BISNIS KONSUMER - BRIGUNA',
                'mantri' => 'MANTRI',
                default => '',
            };
            if ($role === '') {
                continue;
            }

            $unit = $sheetKey === 'mantri'
                ? $this->cleanUnitName($this->cell($row, $indexes['unit'] ?? null))
                : $this->defaultOrganization($role);
            $bc = $sheetKey === 'mantri'
                ? $this->normalizeBc($this->cell($row, $indexes['bc'] ?? null))
                : $this->branchCodeFromValue($this->cell($row, $indexes['branch']), $branch['code']);
            $status = $this->nullableCell($row, $indexes['status'] ?? null);
            $jg = $this->nullableCell($row, $indexes['jg'] ?? null);

            $records[$person['pn']] = [
                'pn' => $person['pn'],
                'name' => $person['name'],
                'role' => $role,
                'branch' => $branch['label'],
                'unit' => $unit,
                'bc' => $bc !== '' ? $bc : $branch['code'],
                'jg' => $jg,
                'status' => $status,
                'july_unit' => $this->nullableCell($row, $indexes['july_unit'] ?? null),
            ];
        }

        return $records;
    }

    /** @return array{pn:string,name:string} */
    private function personFromRow(array $row, array $indexes): array
    {
        $pn = $this->normalizePn($this->cell($row, $indexes['pn'] ?? null));
        $name = trim($this->cell($row, $indexes['name'] ?? null));
        $personCell = $this->cell($row, $indexes['person'] ?? null);

        if (preg_match('/^\s*(\d+)\s*-+\s*(.+)$/u', $personCell, $matches) === 1) {
            $pn = $pn !== '' ? $pn : $this->normalizePn($matches[1]);
            $name = $name !== '' ? $name : trim($matches[2]);
        }

        return ['pn' => $pn, 'name' => $name];
    }

    private function pemasarRow(object $existing, array $record, array $columns, string $now): array
    {
        $current = (array) $existing;
        $source = [
            'uniqueid_namareport' => (string) $existing->uniqueid_namareport,
            'completename' => $record['name'],
            'pernr' => $record['pn'],
            'esgdesc' => $record['status'],
            'padesc' => $current['padesc'] ?? 'Region 13 Malang',
            'psadesc' => $record['branch'],
            'orgdesc' => $record['unit'],
            'positiondesc' => $record['role'],
            'jobgrade' => $record['jg'],
            'bc' => $record['bc'],
            'pn_mantri' => $record['role'] === 'MANTRI' ? $record['pn'] : '-',
            'status' => $record['status'],
            'jg' => $record['jg'],
            'bln_2026' => $record['july_unit'],
            'created_at' => $current['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        foreach ($source as $column => $value) {
            if (($value === null || $value === '') && array_key_exists($column, $current)) {
                $source[$column] = $current[$column];
            }
        }

        return array_filter(
            $source,
            static fn (string $column): bool => in_array($column, $columns, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** @return array<string, int|bool|string> */
    private function syncMbmNames(array $payload): array
    {
        if (!Schema::hasTable('brihc')) {
            return $this->summary('mbm', 0, 0, 0, true);
        }

        $headers = array_values($payload['header'] ?? []);
        $branchIndex = $this->headerIndex($headers, ['BO', 'KANCA']);
        $nameIndex = $this->headerIndex($headers, ['MBM', 'NAMA MBM']);
        if ($branchIndex === null || $nameIndex === null) {
            return $this->summary('mbm', 0, 0, 0, true);
        }

        $names = [];
        foreach (array_values($payload['rows'] ?? []) as $row) {
            $branch = $this->branchInfo($this->cell($row, $branchIndex));
            $name = trim($this->cell($row, $nameIndex));
            if ($branch !== null && $name !== '') {
                $names[$this->textKey($name)] = ['name' => $name, 'branch' => $branch['label']];
            }
        }
        if ($names === []) {
            return $this->summary('mbm', 0, 0, 0, true);
        }

        $now = now()->toDateTimeString();
        $existing = DB::table('brihc')->get(['uniqueid_brihc', 'pn', 'nama', 'jabatan', 'created_at'])
            ->groupBy(fn ($row): string => $this->textKey($row->nama ?? ''));
        $rows = [];
        $inserted = 0;
        $updated = 0;

        foreach ($names as $key => $record) {
            $matches = $existing->get($key, collect())->values();
            if ($matches->isEmpty()) {
                $inserted++;
                $matches = collect([(object) [
                    'uniqueid_brihc' => 'kpi_reference_mbm_' . sha1($key) . '_BRIHC',
                    'pn' => null,
                    'created_at' => $now,
                ]]);
            } else {
                $updated++;
            }

            foreach ($matches as $match) {
                $rows[] = [
                    'uniqueid_brihc' => (string) $match->uniqueid_brihc,
                    'pn' => $match->pn,
                    'nama' => $record['name'],
                    'jabatan' => 'MBM',
                    'created_at' => $match->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('brihc')->upsert($rows, ['uniqueid_brihc'], ['pn', 'nama', 'jabatan', 'updated_at']);
        ReportCacheVersion::bump('pinjaman');

        return $this->summary('mbm', count($names), $inserted, $updated, false);
    }

    /** @return array<string, int|bool|string> */
    private function syncMbmAssignments(array $payload): array
    {
        if (!Schema::hasTable('wilayah_mbm')) {
            return $this->summary('ka-unit', 0, 0, 0, true);
        }

        $headers = array_values($payload['header'] ?? []);
        $indexes = [
            'branch' => $this->headerIndex($headers, ['BO', 'KANCA']),
            'mbm' => $this->headerIndex($headers, ['MBM', 'NAMA MBM']),
            'bc' => $this->headerIndex($headers, ['BC']),
            'unit' => $this->headerIndex($headers, ['UNIT KERJA', 'UKER']),
        ];
        if (in_array(null, $indexes, true)) {
            return $this->summary('ka-unit', 0, 0, 0, true);
        }

        $assignments = [];
        foreach (array_values($payload['rows'] ?? []) as $row) {
            $branch = $this->branchInfo($this->cell($row, $indexes['branch']));
            $bc = $this->normalizeBc($this->cell($row, $indexes['bc']));
            $unit = trim($this->cell($row, $indexes['unit']));
            $mbm = trim($this->cell($row, $indexes['mbm']));
            if ($branch === null || $bc === '' || $unit === '' || $mbm === '') {
                continue;
            }
            $assignments[$bc] = [
                'bc' => $bc,
                'nama_uker' => $unit,
                'cabang' => strtoupper(str_replace('KC ', '', $branch['label'])),
                'nama_mbm' => $mbm,
            ];
        }
        if ($assignments === []) {
            return $this->summary('ka-unit', 0, 0, 0, true);
        }

        $now = now()->toDateTimeString();
        $existing = DB::table('wilayah_mbm')
            ->get(['uniqueid_mbm', 'bc', 'created_at'])
            ->groupBy(fn ($row): string => $this->normalizeBc($row->bc ?? ''));
        $rows = [];
        $inserted = 0;
        $updated = 0;

        foreach ($assignments as $bc => $assignment) {
            $matches = $existing->get($bc, collect())->values();
            if ($matches->isEmpty()) {
                $inserted++;
                $matches = collect([(object) [
                    'uniqueid_mbm' => 'kpi_reference_wilayah_mbm_' . $bc,
                    'created_at' => $now,
                ]]);
            } else {
                $updated++;
            }
            foreach ($matches as $match) {
                $rows[] = array_merge($assignment, [
                    'uniqueid_mbm' => (string) $match->uniqueid_mbm,
                    'created_at' => $match->created_at ?? $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('wilayah_mbm')->upsert(
            $rows,
            ['uniqueid_mbm'],
            ['bc', 'nama_uker', 'cabang', 'nama_mbm', 'updated_at']
        );
        ReportCacheVersion::bump('pinjaman');

        return $this->summary('ka-unit', count($assignments), $inserted, $updated, false);
    }

    private function headerIndex(array $headers, array $candidates): ?int
    {
        $normalizedCandidates = array_map(fn (string $value): string => $this->headerKey($value), $candidates);
        foreach ($headers as $index => $header) {
            if (in_array($this->headerKey($header), $normalizedCandidates, true)) {
                return (int) $index;
            }
        }

        return null;
    }

    private function headerKey(mixed $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(trim((string) $value))) ?: '';
    }

    private function textKey(mixed $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim((string) $value))) ?: '';
    }

    private function sameRole(mixed $currentRole, string $expectedRole): bool
    {
        return $this->textKey($currentRole) === $this->textKey($expectedRole);
    }

    private function cell(array $row, ?int $index): string
    {
        return $index === null ? '' : trim((string) ($row[$index] ?? ''));
    }

    private function nullableCell(array $row, ?int $index): ?string
    {
        $value = $this->cell($row, $index);

        return in_array($value, ['', '-', '--', '#ERROR!'], true) ? null : $value;
    }

    private function normalizePn(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        return ltrim($digits, '0');
    }

    private function normalizeBc(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        return ltrim($digits, '0');
    }

    /** @return array{label:string,code:string}|null */
    private function branchInfo(string $value): ?array
    {
        $normalized = strtoupper(trim($value));
        foreach (self::BRANCHES as $name => $branch) {
            if (str_contains($normalized, $name)) {
                return $branch;
            }
        }

        return null;
    }

    private function branchCodeFromValue(string $value, string $fallback): string
    {
        if (preg_match('/^\s*0*(\d+)\s*-+/', $value, $matches) === 1) {
            return ltrim($matches[1], '0');
        }

        return $fallback;
    }

    private function cleanUnitName(string $value): string
    {
        return trim((string) preg_replace('/^\s*\d+\s*-+\s*/', '', $value));
    }

    private function defaultOrganization(string $role): string
    {
        return match ($role) {
            'RM BISNIS KECIL' => 'FUNGSI BISNIS KECIL',
            'RM MIKRO' => 'FUNGSI BISNIS MIKRO',
            'RM BISNIS KONSUMER - KPR', 'RM BISNIS KONSUMER - BRIGUNA' => 'FUNGSI BISNIS KONSUMER',
            default => '',
        };
    }

    /** @return array<string, int|bool|string> */
    private function summary(string $sheetKey, int $sourceRecords, int $inserted, int $updated, bool $skipped): array
    {
        return [
            'sheet' => $sheetKey,
            'source_records' => $sourceRecords,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
