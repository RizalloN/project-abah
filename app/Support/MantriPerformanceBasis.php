<?php

namespace App\Support;

use App\Console\Commands\SyncMantriSpreadsheetCommand;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MantriPerformanceBasis
{
    private const BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private const NATIONAL_HOLIDAYS = [
        2025 => [
            '2025-01-01', '2025-01-27', '2025-01-29', '2025-03-29', '2025-03-31',
            '2025-04-01', '2025-04-18', '2025-04-20', '2025-05-01', '2025-05-12',
            '2025-05-29', '2025-06-01', '2025-06-06', '2025-06-27', '2025-08-17',
            '2025-09-05', '2025-12-25',
        ],
        2026 => [
            '2026-01-01', '2026-01-16', '2026-02-17', '2026-03-19', '2026-03-21',
            '2026-03-22', '2026-04-03', '2026-04-05', '2026-05-01', '2026-05-14',
            '2026-05-27', '2026-05-31', '2026-06-01', '2026-06-16', '2026-08-17',
            '2026-08-25', '2026-12-25',
        ],
    ];

    /** @return array{available:bool, keys:array<int,string>, people:array<string,array<string,string>>} */
    public function rosterState(?string $period = null): array
    {
        $reference = $period === null ? null : app(MantriProductivityReference::class)->dataForPeriod($period);
        if ($reference !== null && ! $this->hasAuthoritativeRoster()) {
            $people = [];
            $inactivePns = array_fill_keys($this->inactivePns(), true);
            foreach ($reference['people'] as $person) {
                if (isset($inactivePns[$this->pnKey($person['pn'])])) {
                    continue;
                }
                $person['roster_source'] = 'workbook_reference';
                $people[$person['pn']] = $person;
            }

            return ['available' => true, 'keys' => array_map('strval', array_keys($people)), 'people' => $people];
        }
        $empty = ['available' => false, 'keys' => [], 'people' => []];
        if (! Schema::hasTable('brihc_pemasar')) {
            return $empty;
        }
        $columns = array_values(array_intersect([
            'uniqueid_namareport', 'nip', 'pernr', 'completename', 'positiondesc',
            'psadesc', 'orgdesc', 'bc', 'pn_mantri', 'esgdesc', 'keterangan',
        ], Schema::getColumnListing('brihc_pemasar')));
        if (! in_array('positiondesc', $columns, true) || ! in_array('psadesc', $columns, true)
            || (! in_array('pernr', $columns, true) && ! in_array('pn_mantri', $columns, true))) {
            return $empty;
        }

        $rows = DB::table('brihc_pemasar')->select($columns)->get();
        $people = [];
        foreach ($rows as $row) {
            $position = $this->normalize($row->positiondesc ?? '');
            $branch = $this->normalize($row->psadesc ?? '');
            if ($position !== 'MANTRI' || ! in_array($branch, self::BRANCHES, true)
                || $this->isInactiveForProductivity($row->keterangan ?? null)) {
                continue;
            }
            $pn = '';
            foreach (['pn_mantri', 'pernr', 'nip', 'uniqueid_namareport', 'completename'] as $column) {
                $pn = $this->pnKey($row->{$column} ?? '');
                if ($pn !== '') {
                    break;
                }
            }
            if ($pn === '') {
                continue;
            }
            $status = $this->normalize($row->esgdesc ?? '');
            $people[$pn] = [
                'pn' => $pn, 'name' => trim((string) ($row->completename ?? '')),
                'branch' => $branch, 'unit' => trim((string) ($row->orgdesc ?? '')),
                'bc' => trim((string) ($row->bc ?? '')),
                'category' => match (true) {
                    $position === 'MANTRI BRIGUNA' => 'briguna',
                    str_contains($status, 'KONTRAK') || str_contains($status, 'PROBATION') => 'contract',
                    str_contains($status, 'PT') => 'pt',
                    default => 'unknown',
                },
            ];
        }

        return ['available' => $rows->isNotEmpty(), 'keys' => array_map('strval', array_keys($people)), 'people' => $people];
    }

    public function isInactiveForProductivity(mixed $note): bool
    {
        $note = is_scalar($note) ? str_replace("\xC2\xA0", ' ', (string) $note) : '';

        return preg_match('/\b(?:sakit|cuti)\b/iu', $note) === 1;
    }

    /** @return array<int, string> */
    public function inactivePns(): array
    {
        if (! Schema::hasTable('brihc_pemasar') || ! Schema::hasColumn('brihc_pemasar', 'keterangan')) {
            return [];
        }
        $columns = array_values(array_intersect(
            ['pernr', 'pn_mantri', 'nip', 'positiondesc', 'keterangan'],
            Schema::getColumnListing('brihc_pemasar')
        ));
        $inactive = [];
        foreach (DB::table('brihc_pemasar')->select($columns)->get() as $row) {
            if ($this->normalize($row->positiondesc ?? '') !== 'MANTRI'
                || ! $this->isInactiveForProductivity($row->keterangan ?? null)) {
                continue;
            }
            foreach (['pn_mantri', 'pernr', 'nip'] as $column) {
                $pn = $this->pnKey($row->{$column} ?? '');
                if ($pn !== '') {
                    $inactive[$pn] = $pn;
                    break;
                }
            }
        }

        return array_values($inactive);
    }

    private function hasAuthoritativeRoster(): bool
    {
        if (Cache::has(SyncMantriSpreadsheetCommand::CACHE_KEY)) {
            return true;
        }

        return Schema::hasTable('brihc_pemasar')
            && Schema::hasColumn('brihc_pemasar', 'uniqueid_namareport')
            && DB::table('brihc_pemasar')
                ->where('uniqueid_namareport', 'like', 'reference_brihc_hc_%')
                ->whereIn(DB::raw('UPPER(TRIM(positiondesc))'), ['MANTRI', 'MANTRI BRIGUNA', 'MANTRI RECOVERY'])
                ->whereIn(DB::raw('UPPER(TRIM(psadesc))'), self::BRANCHES)
                ->exists();
    }

    public function pnKey(mixed $value): string
    {
        $value = trim((string) $value);
        if (str_contains($value, '|')) {
            $value = trim((string) substr($value, strrpos($value, '|') + 1));
        }
        $identifier = trim(explode('-', $value, 2)[0]);
        $digits = preg_replace('/\D+/', '', $identifier) ?? '';

        return $digits !== '' ? (ltrim($digits, '0') ?: '0') : (preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '');
    }

    public function workingDays(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $holidays = array_flip(self::NATIONAL_HOLIDAYS[(int) $end->year] ?? []);
        $total = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekday() && ! isset($holidays[$day->toDateString()])) {
                $total++;
            }
        }

        return $total;
    }

    private function normalize(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $value))) ?? '';
    }
}
