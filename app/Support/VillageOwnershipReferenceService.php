<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Read only village geography and its declared KC/UKER owner; never retain PN or staff names. */
class VillageOwnershipReferenceService
{
    public const SPREADSHEET_ID = '1m2Mx7JjK3r0oH7gLzAaOjPREasIdxxiKdq-aJcOB0hM';

    public const SHEET_ID = 1767923615;

    public function snapshotPath(): string
    {
        return storage_path('app/market-share-village-ownership/'.self::SPREADSHEET_ID.'/reference.json');
    }

    public function reference(): ?array
    {
        $path = $this->snapshotPath();
        $identity = is_file($path) ? hash_file('sha256', $path) : 'missing';

        return Cache::remember('village_ownership:reference:v1:'.self::SPREADSHEET_ID.':'.$identity, now()->addMinutes(30), function () use ($path): ?array {
            $saved = null;
            if (is_file($path)) {
                try {
                    $candidate = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                    if ($this->valid($candidate)) {
                        $saved = $candidate;
                    }
                } catch (Throwable) {
                }
            }
            if ($saved && filemtime($path) >= now()->subMinutes(30)->timestamp) {
                return $saved;
            }
            try {
                // Explicit projection excludes the source's PN/staff columns I and J.
                $response = Http::connectTimeout(3)->timeout(12)->get('https://docs.google.com/spreadsheets/d/'.self::SPREADSHEET_ID.'/gviz/tq', ['tqx' => 'out:csv', 'gid' => self::SHEET_ID, 'range' => 'A:H', 'headers' => 1]);
                $response->throw();
                $reference = $this->referenceFromCsv($response->body());
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }
                $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
                file_put_contents($temporary, json_encode($reference, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                rename($temporary, $path);

                return $reference;
            } catch (Throwable) {
                if ($saved) {
                    $saved['source']['read_state'] = 'last_verified_snapshot';
                }

                return $saved;
            }
        });
    }

    public function referenceFromCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $header = fgetcsv($stream, null, ',', '"', '');
        if (is_array($header)) {
            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
        }
        $expected = ['No.', 'Desa/Kelurahan', 'Kecamatan', 'Kabupaten/Kota', 'Jenis Wilayah', 'Provinsi', 'Nama Cabang', 'Nama Unit'];
        if (! is_array($header) || array_slice($header, 0, 8) !== $expected) {
            throw new RuntimeException('Header mapping kepemilikan desa tidak sesuai.');
        }
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }
            if (count($row) < 8) {
                throw new RuntimeException('Mapping desa belum lengkap.');
            }
            $rows[] = array_slice($row, 0, 8);
        }
        fclose($stream);

        return $this->referenceFromRows($rows);
    }

    /** Rows contain A:H only, so metadata, hashing and snapshots cannot expose staff columns. */
    public function referenceFromRows(array $rows): array
    {
        $villages = [];
        $duplicates = [];
        $conflicts = [];
        foreach ($rows as $index => $row) {
            $row = array_map(fn ($value) => trim((string) $value), array_slice($row, 0, 8));
            if (count($row) < 8) {
                throw new RuntimeException('Mapping desa belum lengkap.');
            }
            [$number, $name, $district, $regency, $kind, $province, $branchLabel, $unitName] = $row;
            $branch = $this->branchKey($branchLabel);
            if ($name === '' || $district === '' || $regency === '' || $branch === null || $unitName === '') {
                throw new RuntimeException('Identitas desa atau UKER mapping belum lengkap.');
            }
            $key = self::villageKey($regency, $district, $name);
            $ownerKey = $branch.'|'.self::unitToken($unitName, $branch);
            $villages[$key] ??= ['key' => $key, 'name' => $name, 'district' => $district, 'regency' => $regency, 'owners' => []];
            if (isset($villages[$key]['owners'][$ownerKey])) {
                $duplicates[] = ['key' => $key, 'source_row' => $index + 2];
            }
            $villages[$key]['owners'][$ownerKey] ??= ['branch' => $branch, 'unit_name' => $unitName, 'unit_token' => self::unitToken($unitName, $branch), 'source_rows' => []];
            $villages[$key]['owners'][$ownerKey]['source_rows'][] = $index + 2;
        }
        if (! $villages) {
            throw new RuntimeException('Mapping desa kosong.');
        }
        foreach ($villages as &$village) {
            $village['owners'] = array_values($village['owners']);
            $village['conflicting_ownership'] = count($village['owners']) !== 1;
            if ($village['conflicting_ownership']) {
                $conflicts[] = $village['key'];
            }
        }
        unset($village);
        $records = array_values($villages);

        return ['schema_version' => 1, 'source' => [
            'spreadsheet_id' => self::SPREADSHEET_ID, 'sheet_id' => self::SHEET_ID, 'sheet' => 'data', 'url' => 'https://docs.google.com/spreadsheets/d/'.self::SPREADSHEET_ID.'/edit',
            'label' => 'Mapping Wilayah Desa Area 6', 'retained_columns' => ['Desa/Kelurahan', 'Kecamatan', 'Kabupaten/Kota', 'Nama Cabang', 'Nama Unit'],
            'captured_at' => now()->toIso8601String(), 'sha256' => hash('sha256', json_encode(array_map(fn ($row) => array_slice($row, 0, 8), $rows), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'data_sha256' => hash('sha256', json_encode($records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'row_count' => count($rows), 'unique_village_count' => count($records), 'duplicate_row_count' => count($duplicates), 'conflict_count' => count($conflicts),
        ], 'villages' => $records, 'duplicates' => $duplicates, 'conflicts' => $conflicts];
    }

    public static function villageKey(string $regency, string $district, string $name): string
    {
        return implode('|', [self::token($regency), self::token($district), self::token($name)]);
    }

    public static function unitToken(string $name, string $branch): string
    {
        $token = preg_replace('/^UNIT /', '', self::token($name));

        return trim((string) preg_replace('/ '.preg_quote(strtoupper($branch), '/').'$/', '', $token));
    }

    public static function token(string $value): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii($value))));
    }

    private function branchKey(string $label): ?string
    {
        return match (self::token($label)) {
            'KC MADIUN', 'MADIUN' => 'madiun', 'KC MAGETAN', 'MAGETAN' => 'magetan', 'KC NGAWI', 'NGAWI' => 'ngawi', 'KC PONOROGO', 'PONOROGO' => 'ponorogo', default => null,
        };
    }

    private function valid(mixed $data): bool
    {
        return is_array($data) && ($data['schema_version'] ?? null) === 1 && data_get($data, 'source.spreadsheet_id') === self::SPREADSHEET_ID
            && data_get($data, 'source.sheet_id') === self::SHEET_ID && ! empty($data['villages'])
            && hash_equals((string) data_get($data, 'source.data_sha256', ''), hash('sha256', json_encode($data['villages'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
    }
}
