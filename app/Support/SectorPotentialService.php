<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Business-population potential and comparable SSA counts at branch and service-area grains. */
class SectorPotentialService
{
    public const SPREADSHEET_ID = '1rvsgRrblzkIB28pDpBNzSP9scCmhdnDXEl6eOShfLm0';

    public const SHEET_ID = 1225069630;

    private const GROUPS = [
        'agriculture' => ['label' => 'Pertanian, kehutanan dan perikanan', 'categories' => ['A'], 'ssa' => ['PERTANIAN PERBURUAN DAN KEHUTANAN', 'PERIKANAN']],
        'mining' => ['label' => 'Pertambangan dan penggalian', 'categories' => ['B'], 'ssa' => ['PERTAMBANGAN DAN PENGGALIAN']],
        'industry' => ['label' => 'Industri pengolahan', 'categories' => ['C'], 'ssa' => ['INDUSTRI PENGOLAHAN']],
        'utilities' => ['label' => 'Listrik, gas, air dan pengelolaan limbah', 'categories' => ['D', 'E'], 'ssa' => ['LISTRIK GAS DAN AIR']],
        'construction' => ['label' => 'Konstruksi', 'categories' => ['F'], 'ssa' => ['KONSTRUKSI']],
        'trade' => ['label' => 'Perdagangan dan reparasi kendaraan', 'categories' => ['G'], 'ssa' => ['PERDAGANGAN BESAR DAN ECERAN']],
        'transport_information' => ['label' => 'Transportasi, pergudangan, informasi dan komunikasi', 'categories' => ['H', 'J'], 'ssa' => ['TRANSPORTASI PERGUDANGAN DAN KOMUNIKASI']],
        'accommodation' => ['label' => 'Akomodasi dan makan minum', 'categories' => ['I'], 'ssa' => ['PENYEDIAAN AKOMODASI DAN PENYEDIAAN MAKAN MINUM']],
        'finance' => ['label' => 'Jasa keuangan dan asuransi', 'categories' => ['K'], 'ssa' => ['PERANTARA KEUANGAN', 'JASA KEUANGAN DAN ASURANSI']],
        'property_business' => ['label' => 'Real estat dan jasa perusahaan', 'categories' => ['L', 'M-N'], 'ssa' => ['REAL ESTATE USAHA PERSEWAAN DAN JASA PERUSAHAAN']],
        'government' => ['label' => 'Administrasi pemerintahan dan jaminan sosial', 'categories' => ['O'], 'ssa' => ['ADMINISTRASI PEMERINTAHAN PERTAHANAN DAN JAMINAN SOSIAL WAJIB']],
        'education' => ['label' => 'Pendidikan', 'categories' => ['P'], 'ssa' => ['JASA PENDIDIKAN']],
        'health' => ['label' => 'Kesehatan dan kegiatan sosial', 'categories' => ['Q'], 'ssa' => ['JASA KESEHATAN DAN KEGIATAN SOSIAL']],
        'other_services' => ['label' => 'Jasa lainnya', 'categories' => ['R-U'], 'ssa' => ['JASA KEMASYARAKATAN SOSIAL BUDAYA HIBURAN DAN PERORANGAN LAINNYA', 'JASA PERORANGAN YANG MELAYANI RUMAH TANGGA']],
    ];

    private const BRANCHES = ['madiun' => 'KC Madiun', 'magetan' => 'KC Magetan', 'ngawi' => 'KC Ngawi', 'ponorogo' => 'KC Ponorogo'];

    public function payload(?string $branchKey = null): array
    {
        $branchKey = UserBranchScope::current()['key'] ?? $branchKey;
        $branchKey = isset(self::BRANCHES[(string) $branchKey]) ? $branchKey : null;
        $reference = $this->reference();
        $ownership = app(VillageOwnershipReferenceService::class)->reference();
        $period = Schema::hasTable('cras') ? Cache::remember('sector_potential:period:2026-09:v2:'.($branchKey ?? 'area6'), now()->addMinutes(10), function () use ($branchKey) {
            $query = DB::table('cras')->whereBetween('cras_periode', ['2026-09-01', '2026-09-30']);
            if ($branchKey) {
                $query->where('ket_kanca', self::BRANCHES[$branchKey]);
            }

            return $query->max('cras_periode');
        }) : null;
        $base = [
            'ready' => false,
            'source' => $reference['source'] ?? ['spreadsheet_id' => self::SPREADSHEET_ID, 'url' => $this->sourceUrl()],
            'period' => $period ? Carbon::parse($period)->toDateString() : null,
            'period_label' => $period ? Carbon::parse($period)->locale('id')->translatedFormat('d F Y') : 'September 2026 belum tersedia',
            'source_sectors' => $reference['source_sectors'] ?? [],
            'ownership_source' => $ownership['source'] ?? ['spreadsheet_id' => VillageOwnershipReferenceService::SPREADSHEET_ID],
            'comparison_groups' => $this->groups(),
            'denominator_grain' => 'pelaku_usaha_desa_milik_uker',
            'numerator_grain' => 'sum_jumlah_debitur_ssa_cras_aktif_dan_ph_non_consumer',
            'subsector_potential_available' => false,
            'unit_potential_available' => false,
            'note' => 'Intra adalah BRI AKTIF dan ekstra adalah BRI PH; Consumer/Konsumer dikeluarkan. Total mencakup keduanya termasuk OTHER/tanpa sektor. Jumlah debitur merupakan penjumlahan SSA, bukan CIF unik lintas kelompok.',
            'unit_potential_note' => 'Potensi UKER menjumlahkan kolom F hanya pada desa milik UKER menurut mapping desa. Duplikasi pemilik yang sama dideduplikasi, konflik tidak ditebak, dan desa tanpa data populasi ditandai terpisah.',
            'normalization_note' => 'Penetrasi ditampilkan sebagai cakupan yang dinormalisasi ke rentang 0–100%. Potensi, debitur dan rasio asli tetap dipertahankan.',
            'district_note' => 'Potensi kecamatan berasal dari penjumlahan desa. Debitur peta merupakan estimasi alokasi rata unit ke kecamatan layanannya, bukan alamat eksklusif nasabah.',
            'branches' => [], 'districts' => [], 'villages' => [], 'units' => [], 'unmapped_units' => [], 'coverage' => [],
        ];
        if ($reference === null || $ownership === null || $period === null) {
            return $base + ['message' => $reference === null ? 'Sumber potensi sektoral belum dapat dibaca.' : ($ownership === null ? 'Mapping kepemilikan desa belum dapat dibaca.' : 'SSA CRAS September 2026 belum tersedia.')];
        }
        $key = 'sector_potential:payload:v4:'.self::SPREADSHEET_ID.':'.$reference['source']['data_sha256'].':'.VillageOwnershipReferenceService::SPREADSHEET_ID.':'.$ownership['source']['data_sha256'].':'.$period.':'.($branchKey ?? 'area6');
        $computed = Cache::remember($key, now()->addMinutes(5), fn () => $this->build($reference, $ownership, Carbon::parse($period)->toDateString(), $branchKey));

        return [...$base, ...$computed, 'ready' => true, 'message' => ''];
    }

    public function sourceUrl(): string
    {
        return 'https://docs.google.com/spreadsheets/d/'.self::SPREADSHEET_ID.'/edit';
    }

    public function snapshotPath(): string
    {
        return storage_path('app/market-share-sector-potential/'.self::SPREADSHEET_ID.'/reference.json');
    }

    public function reference(): ?array
    {
        $path = $this->snapshotPath();
        $identity = is_file($path) ? hash_file('sha256', $path) : 'missing';

        return Cache::remember('sector_potential:reference:v2:'.self::SPREADSHEET_ID.':'.$identity, now()->addMinutes(30), function () use ($path): ?array {
            $saved = null;
            if (is_file($path)) {
                try {
                    $candidate = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                    if ($this->validReference($candidate)) {
                        $saved = $candidate;
                    }
                } catch (Throwable) {
                    // A corrupt or differently sourced snapshot cannot become the current source.
                }
            }
            if ($saved && filemtime($path) >= now()->subMinutes(30)->timestamp) {
                return $saved;
            }
            try {
                $response = Http::connectTimeout(3)->timeout(12)->get('https://docs.google.com/spreadsheets/d/'.self::SPREADSHEET_ID.'/export', ['format' => 'csv', 'gid' => self::SHEET_ID]);
                $response->throw();
                $reference = $this->referenceFromCsv($response->body());
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }
                $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
                file_put_contents($temporary, json_encode($reference, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
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

    /** CSV values remain the source; no unit or subsector distribution is inferred. */
    public function referenceFromCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $header = fgetcsv($stream, null, ',', '"', '');
        if (is_array($header)) {
            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
        }
        if ($header !== ['Desa/Kelurahan', 'Kecamatan', 'Kabupaten/Kota', 'Keterangan', 'Deskripsi', 'Jumlah']) {
            throw new RuntimeException('Header sumber potensi tidak sesuai.');
        }
        $records = [];
        $sectors = [];
        $villages = [];
        $villageRecords = [];
        $identities = [];
        $rows = 0;
        $allowed = array_merge(...array_column(self::GROUPS, 'categories'));
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }
            if (count($row) !== 6) {
                throw new RuntimeException('Baris sumber potensi tidak lengkap.');
            }
            [$village, $district, $regency, $category, $label, $raw] = array_map('trim', $row);
            $value = str_replace(',', '', $raw);
            $branch = $this->sourceBranch($regency);
            if (! $branch || $village === '' || $district === '' || ! in_array($category, $allowed, true) || ! is_numeric($value) || (float) $value < 0) {
                throw new RuntimeException('Nilai sumber potensi tidak valid.');
            }
            $identity = implode('|', [$regency, $district, $village, $category]);
            if (isset($identities[$identity])) {
                throw new RuntimeException('Duplikasi desa dan kategori pada sumber potensi.');
            }
            $identities[$identity] = true;
            $villageKey = implode('|', [$regency, $district, $village]);
            $villages[$villageKey][$category] = true;
            $qualifiedKey = VillageOwnershipReferenceService::villageKey($regency, $district, $village);
            $villageRecords[$qualifiedKey] ??= ['key' => $qualifiedKey, 'name' => $village, 'district' => $district, 'regency' => $regency, 'physical_branch' => $branch, 'source_values' => []];
            $villageRecords[$qualifiedKey]['source_values'][$category] = (float) $value;
            $key = $regency.'|'.$district;
            $records[$key] ??= ['regency' => $regency, 'name' => $district, 'branch' => $branch, 'source_values' => [], 'village_count' => 0];
            $records[$key]['source_values'][$category] = ($records[$key]['source_values'][$category] ?? 0) + (float) $value;
            $sectors[$category] = $label;
            $rows++;
        }
        fclose($stream);
        if (array_diff($allowed, array_keys($sectors)) !== [] || ! $rows) {
            throw new RuntimeException('Kategori sumber potensi belum lengkap.');
        }
        foreach ($villages as $categories) {
            if (array_diff($allowed, array_keys($categories)) !== []) {
                throw new RuntimeException('Kategori desa belum lengkap.');
            }
        }
        foreach ($records as &$record) {
            if (array_diff($allowed, array_keys($record['source_values'])) !== []) {
                throw new RuntimeException('Kategori kecamatan belum lengkap.');
            }
            $record['village_count'] = count(array_filter(array_keys($villages), fn ($key) => str_starts_with($key, $record['regency'].'|'.$record['name'].'|')));
        }
        unset($record);

        $result = ['schema_version' => 2, 'source' => [
            'spreadsheet_id' => self::SPREADSHEET_ID, 'sheet_id' => self::SHEET_ID, 'sheet' => 'Master Data', 'url' => $this->sourceUrl(), 'label' => 'Maping Pelaku Usaha Area 6',
            'captured_at' => now()->toIso8601String(), 'sha256' => hash('sha256', $csv), 'row_count' => $rows, 'village_count' => count($villages), 'district_count' => count($records), 'capture_method' => 'Google Sheets CSV export',
        ], 'source_sectors' => array_map(fn ($key, $label) => ['key' => $key, 'label' => $label], array_keys($sectors), array_values($sectors)), 'districts' => array_values($records), 'villages' => array_values($villageRecords)];
        $result['source']['data_sha256'] = $this->dataHash($result);

        return $result;
    }

    private function validReference(mixed $data): bool
    {
        return is_array($data) && ($data['schema_version'] ?? null) === 2 && data_get($data, 'source.spreadsheet_id') === self::SPREADSHEET_ID
            && data_get($data, 'source.sheet_id') === self::SHEET_ID && count($data['source_sectors'] ?? []) === 17 && ! empty($data['districts']) && ! empty($data['villages']) && preg_match('/^[a-f0-9]{64}$/', (string) data_get($data, 'source.sha256'))
            && hash_equals((string) data_get($data, 'source.data_sha256', ''), $this->dataHash($data));
    }

    private function dataHash(array $reference): string
    {
        return hash('sha256', json_encode([$reference['source_sectors'], $reference['districts'], $reference['villages']], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private function build(array $reference, array $ownership, string $period, ?string $branchKey): array
    {
        $geography = json_decode((string) file_get_contents(public_path((string) config('marketshare-geography.source.geojson_path', 'data/marketshare-area6-kecamatan.geojson'))), true, 512, JSON_THROW_ON_ERROR);
        $geo = [];
        foreach ($geography['features'] ?? [] as $feature) {
            $p = $feature['properties'];
            $geo[$this->token((string) $p['WADMKK']).'|'.$this->token((string) $p['WADMKC'])] = (string) $p['KDCPUM'];
        }
        $sourceVillages = [];
        foreach ($reference['villages'] as $village) {
            $sourceVillages[$village['key']] = $village;
        }
        $rows = $this->portfolio($period, $branchKey);
        $catalog = [];
        foreach ($rows as $row) {
            $branch = array_search(trim($row['ket_kanca']), self::BRANCHES, true);
            if (! $branch) {
                continue;
            }
            $code = $this->unitCode((string) $row['br_number']);
            if ($code === '') {
                continue;
            }
            $token = VillageOwnershipReferenceService::unitToken((string) $row['ket_unit_kerja'], $branch);
            $catalog[$branch.'|'.$token][$code] = (string) $row['ket_unit_kerja'];
        }
        $units = [];
        $villages = [];
        $missingPopulation = [];
        $conflicts = [];
        $unresolvedNames = [];
        $globallyOwnedKeys = [];
        $conflictDistricts = [];
        foreach ($ownership['villages'] as $ownedVillage) {
            if (count($ownedVillage['owners']) === 1) {
                $globallyOwnedKeys[$ownedVillage['key']] = true;
            }
        }
        foreach ($ownership['villages'] as $ownedVillage) {
            if (count($ownedVillage['owners']) !== 1) {
                if (! $branchKey || collect($ownedVillage['owners'])->contains(fn ($owner) => $owner['branch'] === $branchKey)) {
                    $conflicts[] = $ownedVillage['key'];
                }
                foreach ($ownedVillage['owners'] as $owner) {
                    if ($branchKey && $owner['branch'] !== $branchKey) {
                        continue;
                    }
                    [$unitKey, $unit] = $this->ownedUnit($owner, $catalog);
                    $units[$unitKey] ??= $unit;
                    $units[$unitKey]['conflicting_village_keys'][] = $ownedVillage['key'];
                    $units[$unitKey]['owned_village_keys'][] = $ownedVillage['key'];
                    $conflictingSource = $sourceVillages[$ownedVillage['key']] ?? null;
                    if ($conflictingSource) {
                        $regency = preg_replace('/^KABUPATEN /', '', $this->token($conflictingSource['regency']));
                        $code = $geo[$regency.'|'.$this->token($conflictingSource['district'])] ?? null;
                        if ($code) {
                            $conflictDistricts[$owner['branch'].'|'.$code] = true;
                        }
                    }
                }

                continue;
            }
            $owner = $ownedVillage['owners'][0];
            if ($branchKey && $owner['branch'] !== $branchKey) {
                continue;
            }
            $branch = $owner['branch'];
            [$unitKey, $unit] = $this->ownedUnit($owner, $catalog);
            $units[$unitKey] ??= $unit;
            $code = $units[$unitKey]['code'];
            $units[$unitKey]['owned_village_keys'][] = $ownedVillage['key'];
            if (! isset($sourceVillages[$ownedVillage['key']])) {
                $missing = ['key' => $ownedVillage['key'], 'name' => $ownedVillage['name'], 'district' => $ownedVillage['district'], 'regency' => $ownedVillage['regency'], 'branch' => $branch, 'unit_name' => $owner['unit_name']];
                $units[$unitKey]['missing_population_villages'][] = $missing;
                $missingPopulation[] = $missing;

                continue;
            }
            $village = $sourceVillages[$ownedVillage['key']];
            $regency = preg_replace('/^KABUPATEN /', '', $this->token($village['regency']));
            $village['district_code'] = $geo[$regency.'|'.$this->token($village['district'])] ?? null;
            $village['branch'] = $branch;
            $village['unit_code'] = $code;
            $village['unit_name'] = $owner['unit_name'];
            $village['values'] = $this->emptyValues();
            foreach ($village['values'] as $key => &$value) {
                $value['potential'] = $this->potentialFor($village['source_values'], $key);
                $value['existing'] = null;
            }
            unset($value);
            $villages[$village['key']] = $village;
            $units[$unitKey]['village_keys'][] = $village['key'];
            if ($village['district_code']) {
                $units[$unitKey]['district_codes'][] = $village['district_code'];
            }
            foreach ($village['source_values'] as $key => $potential) {
                $units[$unitKey]['source_values'][$key] = ($units[$unitKey]['source_values'][$key] ?? 0) + $potential;
            }
        }
        foreach ($units as $key => &$unit) {
            $unit['village_keys'] = array_values(array_unique($unit['village_keys']));
            $unit['owned_village_keys'] = array_values(array_unique($unit['owned_village_keys']));
            $unit['district_codes'] = array_values(array_unique($unit['district_codes']));
            $unit['service_district_codes'] = $unit['district_codes'];
            $unit['village_count'] = $unit['matched_village_count'] = count($unit['village_keys']);
            $unit['owned_village_count'] = count($unit['owned_village_keys']);
            $unit['missing_population_village_count'] = count($unit['missing_population_villages']);
            $unit['conflicting_village_count'] = count(array_unique($unit['conflicting_village_keys']));
            $unit['potential_partial'] = $unit['missing_population_village_count'] > 0 || $unit['conflicting_village_count'] > 0;
            $unit['potential_available'] = $unit['village_count'] > 0 && $unit['conflicting_village_count'] === 0;
            foreach ($unit['values'] as $group => &$value) {
                $value['potential'] = $unit['potential_available'] ? $this->potentialFor($unit['source_values'], $group) : null;
            }
            unset($value);
            if (! $unit['numerator_available']) {
                $unresolvedNames[] = ['branch' => $unit['branch'], 'name' => $unit['name'], 'candidate_codes' => array_keys($catalog[$unit['branch'].'|'.VillageOwnershipReferenceService::unitToken($unit['name'], $unit['branch'])] ?? [])];
            }
        }
        unset($unit);
        $coverage = ['ssa_rows' => 0, 'ssa_debtors' => 0, 'eligible_ssa_rows' => 0, 'eligible_debtors' => 0,
            'excluded_consumer_debtors' => 0, 'excluded_status_debtors' => 0, 'owned_intra' => 0, 'owned_extra' => 0,
            'unmapped_intra' => 0, 'unmapped_extra' => 0, 'unclassified_debtors' => 0, 'nonbusiness_debtors' => 0, 'by_branch' => []];
        $unmappedUnits = [];
        foreach ($rows as $row) {
            $branch = array_search(trim($row['ket_kanca']), self::BRANCHES, true);
            if (! $branch) {
                continue;
            }
            $coverage['by_branch'][$branch] ??= ['ssa_debtors' => 0, 'intra' => 0, 'extra' => 0, 'existing' => 0, 'unmapped_intra' => 0, 'unmapped_extra' => 0, 'excluded_consumer_debtors' => 0, 'excluded_status_debtors' => 0];
            $count = (float) $row['existing'];
            $coverage['ssa_rows'] += (int) $row['source_rows'];
            $coverage['ssa_debtors'] += $count;
            $coverage['by_branch'][$branch]['ssa_debtors'] += $count;
            $status = $this->token((string) $row['status_rekening']);
            if (in_array($this->token((string) $row['segmen']), ['CONSUMER', 'KONSUMER'], true)) {
                $coverage['excluded_consumer_debtors'] += $count;
                $coverage['by_branch'][$branch]['excluded_consumer_debtors'] += $count;

                continue;
            }
            if (! in_array($status, ['AKTIF', 'PH'], true)) {
                $coverage['excluded_status_debtors'] += $count;
                $coverage['by_branch'][$branch]['excluded_status_debtors'] += $count;

                continue;
            }
            $coverage['eligible_ssa_rows'] += (int) $row['source_rows'];
            $coverage['eligible_debtors'] += $count;
            $code = $this->unitCode((string) $row['br_number']);
            $unitKey = $branch.'|'.$code;
            $group = $this->ssaGroup((string) $row['sektor_ekonomi']) ?? 'unclassified';
            $metric = $this->rowMetric($row);
            if (isset($units[$unitKey])) {
                foreach ([$group, 'total'] as $key) {
                    $this->addMetric($units[$unitKey]['values'][$key], $metric);
                }
                $side = $status === 'AKTIF' ? 'intra' : 'extra';
                $coverage['owned_'.$side] += $count;
                $coverage['by_branch'][$branch][$side] += $count;
                $coverage['by_branch'][$branch]['existing'] += $count;
                if ($group === 'unclassified') {
                    $coverage[str_contains($this->token((string) $row['sektor_ekonomi']), 'BUKAN LAPANGAN USAHA') ? 'nonbusiness_debtors' : 'unclassified_debtors'] += $count;
                }
            } else {
                $unmappedUnits[$unitKey] ??= ['code' => $code, 'name' => (string) $row['ket_unit_kerja'], 'branch' => $branch, 'ownership_cohort' => false, 'potential_available' => false, 'values' => $this->emptyValues(null)];
                foreach ([$group, 'total'] as $key) {
                    $this->addMetric($unmappedUnits[$unitKey]['values'][$key], $metric);
                }
                $side = $status === 'AKTIF' ? 'intra' : 'extra';
                $coverage['unmapped_'.$side] += $count;
                $coverage['by_branch'][$branch]['unmapped_'.$side] += $count;
            }
        }
        $branches = [];
        $districts = [];
        foreach ($villages as $village) {
            $branch = $village['branch'];
            $branches[$branch] ??= ['key' => $branch, 'label' => self::BRANCHES[$branch], 'source_values' => [], 'values' => $this->emptyValues(), 'village_keys' => [], 'numerator_available' => true, 'allocation_estimate' => false];
            $branches[$branch]['village_keys'][] = $village['key'];
            foreach ($village['source_values'] as $category => $potential) {
                $branches[$branch]['source_values'][$category] = ($branches[$branch]['source_values'][$category] ?? 0) + $potential;
            }
            $code = $village['district_code'];
            if (! $code) {
                continue;
            }
            $districtKey = $branch.'|'.$code;
            $districts[$districtKey] ??= ['code' => $code, 'name' => $village['district'], 'regency' => $village['regency'], 'branch' => $branch, 'source_values' => [], 'values' => $this->emptyValues(), 'village_keys' => [], 'allocation_estimate' => true, 'numerator_grain' => 'ssa_uker_even_owned_district_allocation'];
            $districts[$districtKey]['village_keys'][] = $village['key'];
            foreach ($village['source_values'] as $category => $potential) {
                $districts[$districtKey]['source_values'][$category] = ($districts[$districtKey]['source_values'][$category] ?? 0) + $potential;
            }
        }
        foreach ($branches as &$entity) {
            foreach ($entity['values'] as $key => &$value) {
                $value['potential'] = $this->potentialFor($entity['source_values'], $key);
            }
        }
        unset($entity, $value);
        foreach ($units as $unit) {
            $branches[$unit['branch']] ??= ['key' => $unit['branch'], 'label' => self::BRANCHES[$unit['branch']], 'source_values' => [], 'values' => $this->emptyValues(), 'village_keys' => [], 'numerator_available' => true, 'allocation_estimate' => false];
        }
        foreach ($districts as &$entity) {
            foreach ($entity['values'] as $key => &$value) {
                $value['potential'] = $this->potentialFor($entity['source_values'], $key);
            }
        }
        unset($entity, $value);
        foreach ($units as &$unit) {
            if (! $unit['numerator_available']) {
                foreach ($unit['values'] as &$value) {
                    foreach (['existing', 'intra', 'extra', 'credit_intra', 'credit_extra', 'credit_total'] as $metric) {
                        $value[$metric] = null;
                    }
                }
            }
            unset($value);
            $this->ratios($unit);
            if (! isset($branches[$unit['branch']])) {
                continue;
            }
            if (! $unit['numerator_available']) {
                $branches[$unit['branch']]['numerator_available'] = false;
            }
            if ($unit['conflicting_village_count']) {
                $branches[$unit['branch']]['ownership_conflict'] = true;
            }
            foreach ($unit['values'] as $key => $value) {
                $this->addMetric($branches[$unit['branch']]['values'][$key], $value);
                foreach ($unit['district_codes'] as $code) {
                    $districtKey = $unit['branch'].'|'.$code;
                    if (! isset($districts[$districtKey])) {
                        continue;
                    }
                    if ($unit['conflicting_village_count']) {
                        $districts[$districtKey]['ownership_conflict'] = true;
                    }
                    $allocated = $value;
                    foreach ($this->countFields() as $metric) {
                        $allocated[$metric] = $allocated[$metric] === null ? null : $allocated[$metric] / count($unit['district_codes']);
                    }
                    $this->addMetric($districts[$districtKey]['values'][$key], $allocated);
                }
            }
        }
        unset($unit);
        $area = ['values' => $this->emptyValues()];
        foreach ($branches as &$entity) {
            $entity['village_count'] = count($entity['village_keys']);
            $entity['potential_partial'] = count(array_filter($missingPopulation, fn ($missing) => $missing['branch'] === $entity['key'])) > 0;
            if (! empty($entity['ownership_conflict'])) {
                $entity['potential_partial'] = true;
                foreach ($entity['values'] as &$value) {
                    $value['known_potential'] = $value['potential'];
                    $value['potential'] = null;
                }
                unset($value);
            }
            if (! $entity['numerator_available']) {
                foreach ($entity['values'] as &$value) {
                    foreach ($this->countFields() as $metric) {
                        $value[$metric] = null;
                    }
                }
            }
            unset($value);
            $this->ratios($entity);
            foreach ($entity['values'] as $key => $value) {
                $area['values'][$key]['potential'] += $value['known_potential'] ?? $value['potential'];
                $this->addMetric($area['values'][$key], $value);
            }
        }
        unset($entity);
        if ($conflicts) {
            foreach ($area['values'] as &$value) {
                $value['known_potential'] = $value['potential'];
                $value['potential'] = null;
            }
        }
        unset($value);
        foreach ($districts as $districtKey => &$entity) {
            if (! empty($entity['ownership_conflict']) || isset($conflictDistricts[$districtKey])) {
                $entity['ownership_conflict'] = $entity['potential_partial'] = true;
                foreach ($entity['values'] as &$value) {
                    $value['known_potential'] = $value['potential'];
                    $value['potential'] = null;
                }
                unset($value);
            }
            $this->ratios($entity);
        }
        unset($entity);
        foreach ($unmappedUnits as &$unit) {
            $this->ratios($unit);
        }
        unset($unit);
        $this->ratios($area);
        $unownedSource = [];
        foreach ($sourceVillages as $key => $village) {
            if (! isset($globallyOwnedKeys[$key]) && (! $branchKey || $village['physical_branch'] === $branchKey)) {
                $unownedSource[] = ['key' => $key, 'name' => $village['name'], 'district' => $village['district'], 'regency' => $village['regency']];
            }
        }

        return [
            'branches' => array_values($branches), 'districts' => array_values($districts), 'villages' => array_values($villages),
            'units' => array_values($units), 'unmapped_units' => array_values($unmappedUnits), 'totals' => $area['values'],
            'unit_potential_available' => count(array_filter($units, fn ($unit) => $unit['potential_available'])) > 0,
            'ownership_basis' => 'desa_kecamatan_kabupaten_pemilik_uker',
            'coverage' => [...$coverage, 'owned_unit_count' => count($units), 'unmapped_unit_count' => count($unmappedUnits),
                'owned_village_count' => count($villages), 'missing_population_village_count' => count($missingPopulation), 'missing_population_villages' => $missingPopulation,
                'unowned_source_village_count' => count($unownedSource), 'unowned_source_villages' => $unownedSource,
                'ownership_conflicts' => $conflicts, 'unresolved_ownership_units' => $unresolvedNames,
                'numerator_complete' => $unresolvedNames === [], 'matched_district_count' => count($districts)],
        ];
    }

    private function potentialFor(array $sourceValues, string $group): float
    {
        $categories = $group === 'total' ? array_merge(...array_column(self::GROUPS, 'categories')) : (self::GROUPS[$group]['categories'] ?? []);

        return (float) array_sum(array_map(fn ($category) => $sourceValues[$category] ?? 0, $categories));
    }

    private function ownedUnit(array $owner, array $catalog): array
    {
        $branch = $owner['branch'];
        $candidates = $catalog[$branch.'|'.$owner['unit_token']] ?? [];
        $code = count($candidates) === 1 ? (string) array_key_first($candidates) : '';
        $key = $branch.'|'.($code === '' ? 'name:'.$owner['unit_token'] : $code);

        return [$key, ['code' => $code, 'name' => $owner['unit_name'], 'branch' => $branch,
            'village_keys' => [], 'owned_village_keys' => [], 'missing_population_villages' => [], 'conflicting_village_keys' => [], 'district_codes' => [],
            'source_values' => [], 'values' => $this->emptyValues(null), 'potential_available' => false, 'numerator_available' => $code !== '',
            'allocation_estimate' => false, 'denominator_grain' => 'pelaku_usaha_desa_milik_uker', 'ownership_cohort' => true]];
    }

    private function unitCode(string $raw): string
    {
        $digits = preg_replace('/\D/', '', trim($raw)) ?? '';

        return $digits === '' ? '' : str_pad(substr($digits, -5), 5, '0', STR_PAD_LEFT);
    }

    private function countFields(): array
    {
        return ['existing', 'intra', 'extra', 'accounts_intra', 'accounts_extra', 'credit_intra', 'credit_extra', 'credit_total'];
    }

    private function addMetric(array &$target, array $metric): void
    {
        foreach ($this->countFields() as $key) {
            $target[$key] = $target[$key] === null || ($metric[$key] ?? null) === null ? null : $target[$key] + $metric[$key];
        }
    }

    private function rowMetric(array $row): array
    {
        $intra = $this->token((string) $row['status_rekening']) === 'AKTIF';
        $count = (float) $row['existing'];
        $credit = (float) ($intra ? $row['credit_intra'] : $row['credit_extra']);

        return ['existing' => $count, 'intra' => $intra ? $count : 0.0, 'extra' => $intra ? 0.0 : $count,
            'accounts_intra' => $intra ? (float) $row['accounts'] : 0.0, 'accounts_extra' => $intra ? 0.0 : (float) $row['accounts'],
            'credit_intra' => $intra ? $credit : 0.0, 'credit_extra' => $intra ? 0.0 : $credit, 'credit_total' => $credit];
    }

    private function portfolio(string $period, ?string $branch): array
    {
        $query = DB::table('cras')->where('cras_periode', $period);
        if ($branch) {
            $query->where('ket_kanca', self::BRANCHES[$branch]);
        }

        return $this->referenceRows($query);
    }

    /** One aggregate query for both broad and already-filtered CRAS reference cohorts. */
    public function referenceRows(Builder $query): array
    {
        $keys = ['ket_kanca', 'br_number', 'ket_unit_kerja', 'sektor_ekonomi', 'status_rekening', 'segmen'];
        $number = fn ($column) => 'COALESCE(CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(TRIM('.$column."), ',', ''), '\"', ''), '(', '-'), ')', ''), '') AS DECIMAL(30,4)),0)";
        if (DB::connection()->getDriverName() === 'mysql') {
            $debtors = $number('jumlah_debitur');
            $accounts = $number('jumlah_rekening');
            $os = $number('baki_debet');
            $ph = $number('saldo_ph');

            return $query->selectRaw(implode(',', $keys).', COUNT(*) AS source_rows, SUM('.$debtors.') AS existing, SUM('.$accounts.') AS accounts, SUM('.$os.') AS credit_intra, SUM(CASE WHEN '.$ph.' > 0 THEN '.$ph.' ELSE '.$os.' END) AS credit_extra')
                ->groupBy($keys)->get()->map(fn ($row) => (array) $row)->all();
        }
        $rows = [];
        foreach ($query->select([...$keys, 'jumlah_debitur', 'jumlah_rekening', 'baki_debet', 'saldo_ph'])->cursor() as $row) {
            $key = implode('|', array_map(fn ($key) => (string) $row->{$key}, $keys));
            $rows[$key] ??= array_combine($keys, array_map(fn ($key) => $row->{$key}, $keys)) + ['existing' => 0.0, 'source_rows' => 0, 'accounts' => 0.0, 'credit_intra' => 0.0, 'credit_extra' => 0.0];
            $rows[$key]['existing'] += $this->sourceNumber($row->jumlah_debitur);
            $rows[$key]['accounts'] += $this->sourceNumber($row->jumlah_rekening);
            $os = $this->sourceNumber($row->baki_debet);
            $ph = $this->sourceNumber($row->saldo_ph);
            $rows[$key]['credit_intra'] += $os;
            $rows[$key]['credit_extra'] += $ph > 0 ? $ph : $os;
            $rows[$key]['source_rows']++;
        }

        return array_values($rows);
    }

    private function sourceNumber(mixed $raw): float
    {
        return (float) str_replace([',', '"', '(', ')'], ['', '', '-', ''], trim((string) $raw));
    }

    /** Additive owned-village cohort; the generic CRAS report retains its original metrics. */
    public function referenceUnitsForRows(array $rows, array $payload): array
    {
        $owned = [];
        foreach ($payload['units'] ?? [] as $unit) {
            $owned[$unit['branch'].'|'.$unit['code']] = $unit;
        }
        $result = [];
        foreach ($rows as $row) {
            $branch = array_search(trim($row['ket_kanca']), self::BRANCHES, true);
            $code = $this->unitCode((string) $row['br_number']);
            $key = $branch.'|'.$code;
            if (! $branch || ! isset($owned[$key]) || in_array($this->token((string) $row['segmen']), ['CONSUMER', 'KONSUMER'], true) || ! in_array($this->token((string) $row['status_rekening']), ['AKTIF', 'PH'], true)) {
                continue;
            }
            $result[$key] ??= ['code' => $code, 'name' => (string) $row['ket_unit_kerja'], 'branch_key' => $branch, 'branch' => self::BRANCHES[$branch],
                'district_codes' => $owned[$key]['district_codes'] ?? [], 'village_keys' => $owned[$key]['village_keys'] ?? [],
                'potential_partial' => $owned[$key]['potential_partial'] ?? false, 'missing_population_village_count' => $owned[$key]['missing_population_village_count'] ?? 0,
                'values' => ['existing' => 0.0, 'intra' => 0.0, 'extra' => 0.0, 'credit_intra' => 0.0, 'credit_extra' => 0.0, 'credit_total' => 0.0, 'accounts_intra' => 0.0, 'accounts_extra' => 0.0]];
            $this->addMetric($result[$key]['values'], $this->rowMetric($row));
        }
        foreach ($result as &$unit) {
            $v = &$unit['values'];
            foreach (['credit_intra', 'credit_extra', 'credit_total'] as $metric) {
                $v[$metric] = round($v[$metric], 2);
            }
            $v['jumlah_debitur'] = $v['existing'];
            $v['jumlah_rekening'] = $v['accounts_intra'] + $v['accounts_extra'];
            $v['baki_debet'] = $v['credit_intra'];
            unset($v);
        }
        unset($unit);

        return array_values($result);
    }

    private function groups(): array
    {
        return [['key' => 'total', 'label' => 'Seluruh sektor usaha', 'categories' => array_merge(...array_column(self::GROUPS, 'categories'))],
            ...array_map(fn ($key, $group) => ['key' => $key, 'label' => $group['label'], 'categories' => $group['categories']], array_keys(self::GROUPS), array_values(self::GROUPS)),
            ['key' => 'unclassified', 'label' => 'OTHER / Tanpa sektor', 'categories' => []]];
    }

    private function emptyValues(?float $potential = 0): array
    {
        return array_fill_keys(['total', ...array_keys(self::GROUPS), 'unclassified'], ['potential' => $potential, 'existing' => 0.0, 'intra' => 0.0, 'extra' => 0.0,
            'accounts_intra' => 0.0, 'accounts_extra' => 0.0, 'credit_intra' => 0.0, 'credit_extra' => 0.0, 'credit_total' => 0.0,
            'penetration' => null, 'penetration_raw' => null, 'normalization_applied' => false, 'penetration_excess' => null]);
    }

    private function ratios(array &$entity): void
    {
        foreach ($entity['values'] as &$value) {
            foreach (['credit_intra', 'credit_extra', 'credit_total'] as $metric) {
                if (isset($value[$metric])) {
                    $value[$metric] = round($value[$metric], 2);
                }
            }
            $value = [...$value, ...$this->coverage($value['existing'], $value['potential'])];
        }
        unset($value);
    }

    public function coverage(float|int|null $existing, float|int|null $potential): array
    {
        $raw = $existing !== null && ($potential ?? 0) > 0 ? $existing / $potential * 100 : null;

        return [
            'potential' => $potential, 'existing' => $existing,
            'penetration_raw' => $raw, 'penetration' => $raw === null ? null : min(100.0, max(0.0, $raw)),
            'normalization_applied' => $raw !== null && ($raw < 0 || $raw > 100),
            'penetration_excess' => $raw === null ? null : max(0.0, $raw - 100),
        ];
    }

    public function comparisonGroupForSsaSector(string $sector): ?array
    {
        $key = $this->ssaGroup($sector);

        return $key === null ? null : ['key' => $key, 'label' => self::GROUPS[$key]['label'], 'categories' => self::GROUPS[$key]['categories']];
    }

    private function ssaGroup(string $sector): ?string
    {
        $token = $this->token($sector);
        foreach (self::GROUPS as $key => $group) {
            if (in_array($token, $group['ssa'], true)) {
                return $key;
            }
        }

        return null;
    }

    private function sourceBranch(string $regency): ?string
    {
        return match ($this->token($regency)) {
            'KABUPATEN MADIUN', 'KOTA MADIUN' => 'madiun', 'KABUPATEN MAGETAN' => 'magetan', 'KABUPATEN NGAWI' => 'ngawi', 'KABUPATEN PONOROGO' => 'ponorogo', default => null,
        };
    }

    private function token(string $value): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii($value))));
    }
}
