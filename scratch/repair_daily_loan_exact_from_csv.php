<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sources = [
    ['label' => '30042026', 'path' => 'C:\\Users\\msi\\Desktop\\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7.csv', 'period' => '2026-04-30'],
    ['label' => '14052026', 'path' => 'C:\\Users\\msi\\Downloads\\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7 14052026.csv', 'period' => '2026-05-14'],
];

$headers = [
    'PERIODE','KODE_KANWIL1','KANWIL1','KODE_CABANG1','CABANG1','BRANCH1','UNIT1','CURTYP','AO_NAME','CIFNO',
    'NOMOR_REKENING1','STATUS_REKENING1','LN_TYPE','NAMA_DEBITUR1','RATE','JANGKA_WAKTU1','PLAFON','BAKI_DEBET1',
    'CKPN','NILAI_TERCATAT1','KOL_ADK1','KOLEK_DETAIL','KOLEK','KOLEKTABILITAS_LANCAR','KOLEKTABILITAS_DPK',
    'KOLEKTABILITAS_KURANGLANCAR','KOLEKTABILITAS_DIRAGUKAN','KOLEKTABILITAS_MACET','Textbox20','TUNGGAKAN_POKOK',
    'TUNGGAKAN_BUNGA','TUNGGAKAN_PENALTI','UMUR_TUNGGAKAN','TGL_REALISASI','TGL_JATUH_TEMPO','TANGGAL_MENUNGGAK',
    'TGL_BAYAR_TERAKHIR','TGL_TERMINATE','LAST_DATE_MAINTENANCE_BILLING','NEXT_PMT_DATE','NEXT_PMT_INT_DATE',
    'ADVANCE_PAYMENT','BAP','PAYMENT_AMOUNT','FINAL_PAYMENT_AMOUNT','NPB_POKOK_LA','NPB_POKOK_LF','NPB_BUNGA_LA',
    'NPB_BUNGA_LF','JML_ANGSURAN1','JUMLAH_BAYAR','DEFFERED_BUNGA','SAI_TUNGGAKAN','SAI_DEFFERED','SAI1',
    'FREQ_PAYMENT','FREQ_INT_PAYMENT','JADWAL_GP_POKOK','PN_PENGELOLA1','PN_NAME1','PN_PEMRAKARSA1','PN_REFERRAL1',
    'PN_RESTRUK1','PN_PENGELOLA2','PN_PEMUTUS1','PN_CRM1','PN_CRR','PN_REFERRAL_NAIK_KELAS1','JUMLAH_PN1',
    'JUMLAH_PN_ALL1','CODE','DESCRIPTION','KECAMATAN_T_TINGGAL','KELURAHAN_T_TINGGAL','KODEPOS_T_TINGGAL',
    'KECAMATAN_T_USAHA','KELURAHAN_T_USAHA','KODEPOS_T_USAHA','SEGMEN_DASHBOARD','PRODUK_DASHBOARD',
    'DIVISI_SEGMEN_DASHBOARD','NPL_METHOD','RESTRUK_KE1','JENIS_RESTRUK1','TGL_AKAD_RESTRUK','FLAG_RESTRUK',
    'FLAG_RESTRUK_COVID1','FLAG_COMMODITY_CHAIN1','FLAG_BRIGUNA_DIGITAL1','FLAG_AGF','FLAG_AFT','PMTAMT',
    'PMTAMT_Base','OFFCR','LBDOTU','KETERANGAN_PN_PENGELOLA','Textbox21','FLAG_KLAIM','OS_SEBELUM_KLAIM',
    'OS_PENUH_BERJALAN','BILPRN','BILINT','BILLC',
];
$aliases = ['Textbox20' => 'total_kewajiban', 'Textbox21' => 'os_idr', 'PMTAMT_Base' => 'pmtamt_base'];
$dateHeaders = array_fill_keys(['PERIODE','TGL_REALISASI','TGL_JATUH_TEMPO','TANGGAL_MENUNGGAK','TGL_BAYAR_TERAKHIR','TGL_TERMINATE','LAST_DATE_MAINTENANCE_BILLING','NEXT_PMT_DATE','NEXT_PMT_INT_DATE','TGL_AKAD_RESTRUK'], true);
$decimalHeaders = array_fill_keys(['RATE','PLAFON','BAKI_DEBET1','CKPN','NILAI_TERCATAT1','KOLEKTABILITAS_LANCAR','KOLEKTABILITAS_DPK','KOLEKTABILITAS_KURANGLANCAR','KOLEKTABILITAS_DIRAGUKAN','KOLEKTABILITAS_MACET','Textbox20','TUNGGAKAN_POKOK','TUNGGAKAN_BUNGA','TUNGGAKAN_PENALTI','ADVANCE_PAYMENT','BAP','PAYMENT_AMOUNT','FINAL_PAYMENT_AMOUNT','NPB_POKOK_LA','NPB_POKOK_LF','NPB_BUNGA_LA','NPB_BUNGA_LF','JML_ANGSURAN1','JUMLAH_BAYAR','DEFFERED_BUNGA','SAI_TUNGGAKAN','SAI_DEFFERED','SAI1','PMTAMT','PMTAMT_Base','Textbox21','OS_SEBELUM_KLAIM','OS_PENUH_BERJALAN','BILPRN','BILINT','BILLC'], true);
$integerHeaders = array_fill_keys(['JANGKA_WAKTU1','UMUR_TUNGGAKAN','FREQ_PAYMENT','FREQ_INT_PAYMENT','JUMLAH_PN1','JUMLAH_PN_ALL1','RESTRUK_KE1'], true);

function db_column(string $header, array $aliases): string { return $aliases[$header] ?? strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', trim($header))); }
function parse_line(string $line, int $expected): array
{
    $line = preg_replace('/^\xEF\xBB\xBF/', '', rtrim($line, "\r\n"));
    $row = str_getcsv($line);
    return count($row) === 1 && $expected > 1 && str_contains($row[0] ?? '', ',') ? str_getcsv((string) $row[0]) : $row;
}
function parse_date_value($value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'] as $format) {
        $dt = DateTime::createFromFormat('!' . $format, $value);
        if ($dt && $dt->format($format) === $value) return $dt->format('Y-m-d');
    }
    return null;
}
function parse_decimal_value($value, int $scale): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    $value = str_replace(' ', '', $value);
    if (str_starts_with($value, '(') && str_ends_with($value, ')')) $value = '-' . substr($value, 1, -1);
    if (str_ends_with($value, '-')) $value = '-' . substr($value, 0, -1);
    if (preg_match('/^-?[0-9]{1,3}(,[0-9]{3})+(\.[0-9]+)?$/', $value)) $value = str_replace(',', '', $value);
    elseif (preg_match('/^-?[0-9]{1,3}(\.[0-9]{3})+(,[0-9]+)?$/', $value)) $value = str_replace(',', '.', str_replace('.', '', $value));
    elseif (preg_match('/^-?[0-9]+,[0-9]+$/', $value)) $value = str_replace(',', '.', $value);
    return is_numeric($value) ? number_format((float) $value, $scale, '.', '') : null;
}
function parse_int_value($value): ?int { return preg_match('/-?\d+/', trim((string) $value), $m) ? (int) $m[0] : null; }
function parse_text_value($value): ?string { $value = (string) $value; return $value === '' ? null : $value; }
function row_hash(array $row, array $columns): string
{
    $payload = [];
    foreach ($columns as $column) $payload[$column] = array_key_exists($column, $row) && $row[$column] !== null ? (string) $row[$column] : null;
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function bucket(?string $detail, ?string $kolek): string
{
    $raw = strtoupper(trim((string) ($detail ?: $kolek)));
    $normalized = preg_replace('/[^A-Z0-9]+/', '', $raw);
    return match ($normalized) {
        'L' => 'L', 'LR' => 'LR', 'SML1', 'SML01', 'DPK1' => 'SML 1', 'SML2', 'SML02', 'DPK2' => 'SML 2',
        'SML3', 'SML03', 'DPK3' => 'SML 3', 'KL' => 'KL', 'D1' => 'D1', 'D2' => 'D2', 'M' => 'M',
        default => $raw === '' ? 'BLANK' : $raw,
    };
}

$metaRows = DB::select("SELECT COLUMN_NAME, DATA_TYPE, NUMERIC_SCALE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_loan_dinamis'");
$meta = [];
foreach ($metaRows as $row) $meta[strtolower($row->COLUMN_NAME)] = ['scale' => $row->NUMERIC_SCALE === null ? null : (int) $row->NUMERIC_SCALE, 'max' => $row->CHARACTER_MAXIMUM_LENGTH === null ? 0 : (int) $row->CHARACTER_MAXIMUM_LENGTH];
if (($meta['rate']['scale'] ?? 0) < 6) throw new RuntimeException('Kolom rate belum 6 desimal. Jalankan migration rate precision dulu.');

$columns = [];
$rules = [];
foreach ($headers as $i => $header) {
    $column = db_column($header, $aliases);
    if (!isset($meta[$column])) continue;
    $type = isset($dateHeaders[$header]) ? 'date' : (isset($decimalHeaders[$header]) ? 'decimal' : (isset($integerHeaders[$header]) ? 'integer' : 'text'));
    $rules[$i] = ['column' => $column, 'type' => $type, 'scale' => $meta[$column]['scale'] ?? 2, 'max' => $meta[$column]['max'] ?? 0];
    $columns[] = $column;
}
$columns = array_values(array_unique($columns));
$insertColumns = array_values(array_unique(array_merge(['uniqueid_namareport'], $columns, ['created_at', 'updated_at'])));

function stream_source_to_temp(array $source, array $rules, array $columns, array $insertColumns, string $temp): array
{
    $fh = fopen($source['path'], 'rb');
    if (!$fh) throw new RuntimeException("Tidak bisa buka {$source['path']}");
    $headerFound = false; $count = 0; $batch = []; $hashes = []; $totals = ['plafon' => 0.0, 'baki_debet1' => 0.0]; $quality = []; $first = []; $last = [];
    while (($line = fgets($fh)) !== false) {
        $parsed = parse_line($line, count($rules));
        if (!$headerFound) {
            if (strtoupper(trim((string) ($parsed[0] ?? ''), "\xEF\xBB\xBF \t\r\n\"")) === 'PERIODE') $headerFound = true;
            continue;
        }
        $row = [];
        foreach ($rules as $idx => $rule) {
            $raw = $parsed[$idx] ?? '';
            $value = match ($rule['type']) {
                'date' => parse_date_value($raw),
                'decimal' => parse_decimal_value($raw, (int) $rule['scale']),
                'integer' => parse_int_value($raw),
                default => parse_text_value($raw),
            };
            if ($rule['type'] === 'text' && $value !== null && (int) $rule['max'] > 0) $value = mb_substr($value, 0, (int) $rule['max']);
            $row[$rule['column']] = $value;
        }
        if (($row['periode'] ?? null) !== $source['period'] || empty($row['nomor_rekening1']) || $row['baki_debet1'] === null) continue;
        $count++;
        $row['uniqueid_namareport'] = 'repair_' . str_replace('-', '', $source['period']) . '_' . str_pad((string) $count, 12, '0', STR_PAD_LEFT) . '_DLD';
        $row['created_at'] = now(); $row['updated_at'] = now();
        foreach ($columns as $column) $row[$column] = $row[$column] ?? null;
        $payload = array_intersect_key($row, array_flip($insertColumns));
        $hashKey = (string) $row['uniqueid_namareport'];
        $hashes[$hashKey] = row_hash($row, $columns);
        if ($count <= 100) $first[$hashKey] = $hashes[$hashKey];
        $last[$hashKey] = $hashes[$hashKey];
        if (count($last) > 100) array_shift($last);
        $totals['plafon'] += (float) ($row['plafon'] ?? 0); $totals['baki_debet1'] += (float) ($row['baki_debet1'] ?? 0);
        $b = bucket($row['kolek_detail'] ?? null, $row['kolek'] ?? null);
        $quality[$b]['rows'] = ($quality[$b]['rows'] ?? 0) + 1;
        $quality[$b]['plafon'] = ($quality[$b]['plafon'] ?? 0) + (float) ($row['plafon'] ?? 0);
        $quality[$b]['baki_debet1'] = ($quality[$b]['baki_debet1'] ?? 0) + (float) ($row['baki_debet1'] ?? 0);
        $batch[] = $payload;
        if (count($batch) >= 500) { DB::table($temp)->insert($batch); $batch = []; }
    }
    if ($batch) DB::table($temp)->insert($batch);
    fclose($fh);
    return compact('count', 'hashes', 'totals', 'quality', 'first', 'last');
}

function db_hashes_for_period(string $period, array $columns, array $insertColumns): array
{
    $hashes = [];
    DB::table('daily_loan_dinamis')->where('periode', $period)->select($insertColumns)->orderBy('uniqueid_namareport')->chunk(10000, function ($chunk) use (&$hashes, $columns) {
        foreach ($chunk as $row) $hashes[(string) $row->uniqueid_namareport] = row_hash((array) $row, $columns);
    });
    return $hashes;
}

$summary = [];
foreach ($sources as $source) {
    echo "Staging {$source['label']}..." . PHP_EOL;
    $temp = 'tmp_dld_repair_' . $source['label'];
    DB::statement("DROP TABLE IF EXISTS `{$temp}`");
    DB::statement("CREATE TABLE `{$temp}` LIKE `daily_loan_dinamis`");
    $sourceAudit = stream_source_to_temp($source, $rules, $columns, $insertColumns, $temp);
    $tempCount = (int) DB::table($temp)->where('periode', $source['period'])->count();
    if ($tempCount !== $sourceAudit['count']) throw new RuntimeException("Staging count mismatch {$source['label']}");
    DB::transaction(function () use ($source, $temp, $insertColumns) {
        DB::table('daily_loan_dinamis')->where('periode', $source['period'])->delete();
        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $insertColumns));
        DB::statement("INSERT INTO `daily_loan_dinamis` ({$cols}) SELECT {$cols} FROM `{$temp}` WHERE `periode` = ?", [$source['period']]);
    });
    $dbHashes = db_hashes_for_period($source['period'], $columns, $insertColumns);
    $mismatch = 0; foreach ($sourceAudit['hashes'] as $rek => $hash) if (($dbHashes[$rek] ?? null) !== $hash) $mismatch++;
    $edge = [];
    foreach (['first' => $sourceAudit['first'], 'last' => $sourceAudit['last']] as $name => $hashes) {
        $bad = 0; foreach ($hashes as $rek => $hash) if (($dbHashes[$rek] ?? null) !== $hash) $bad++;
        $edge[$name . '_100'] = ['checked' => count($hashes), 'mismatch' => $bad];
    }
    $summary[$source['label']] = ['period' => $source['period'], 'rows' => $sourceAudit['count'], 'db_rows' => count($dbHashes), 'mismatch_rows' => $mismatch, 'totals' => $sourceAudit['totals'], 'quality' => $sourceAudit['quality'], 'edge_validation' => $edge];
    DB::statement("DROP TABLE IF EXISTS `{$temp}`");
}
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
