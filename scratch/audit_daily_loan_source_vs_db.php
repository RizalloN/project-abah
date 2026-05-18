<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\LoanQualityBucketMapper;
use Illuminate\Support\Facades\DB;

$csvPath = 'C:\Users\msi\Downloads\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7 14052026.csv';
$curr = '2026-05-14';
$prev = '2026-04-30';
$wantedBranches = ['KC Madiun','KC Magetan','KC Ngawi','KC Ponorogo'];

echo "Loading prev bucket map..." . PHP_EOL;
$prevMap = [];
DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $prev)
    ->select('account_number', 'quality_bucket')
    ->orderBy('account_number')
    ->chunk(50000, function ($chunk) use (&$prevMap) {
        foreach ($chunk as $r) {
            $prevMap[$r->account_number] = $r->quality_bucket;
        }
    });
echo "  prev map: " . count($prevMap) . PHP_EOL;

$fh = fopen($csvPath, 'r');
$first = fgets($fh);
$first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
$header = str_getcsv(trim($first, "\r\n"));
$idx = array_flip($header);

// Each data line is itself a CSV row wrapped as ONE standard-CSV quoted field:
// the whole record is in outer "..." and inner " is escaped as "".
// So pass it through str_getcsv twice: outer returns ['<inner content>'],
// inner is the real per-column array. Inner field values may still have
// surrounding quotes if they contained commas, which we strip.
function parseDataLine(string $line): array {
    $line = rtrim($line, "\r\n");
    $outer = str_getcsv($line, ',', '"', '\\');
    $inner = $outer[0] ?? '';
    $fields = str_getcsv($inner, ',', '"', '\\');
    return $fields;
}

function col(array $row, array $idx, string $key) {
    return isset($idx[$key]) ? ($row[$idx[$key]] ?? null) : null;
}

function parseNumeric($v): float {
    if ($v === null) return 0.0;
    $v = str_replace(',', '', trim((string)$v));
    return is_numeric($v) ? (float)$v : 0.0;
}

$labels = ['New Account', 'L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];
$cols   = ['L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];

$byBranch = [];
$rawSum = [];
$totalRows = 0;
$matchedRows = 0;
$skippedNoRek = 0;
$skippedBucket = 0;
$bucketCounts = [];

while (($line = fgets($fh)) !== false) {
    $totalRows++;
    if ($totalRows % 100000 === 0) {
        fwrite(STDERR, "  read $totalRows rows (matched=$matchedRows)\n");
    }

    $row = parseDataLine($line);
    $branch = trim(col($row, $idx, 'CABANG1') ?? '');
    if (!in_array($branch, $wantedBranches, true)) {
        continue;
    }

    $rek = trim(col($row, $idx, 'NOMOR_REKENING1') ?? '');
    if ($rek === '') {
        $skippedNoRek++;
        continue;
    }

    $baki = parseNumeric(col($row, $idx, 'BAKI_DEBET1'));
    $rawSum[$branch] = ($rawSum[$branch] ?? 0) + $baki;

    $kolek = trim(col($row, $idx, 'KOLEK') ?? '');
    $kolekDetail = trim(col($row, $idx, 'KOLEK_DETAIL') ?? '');
    $umur = col($row, $idx, 'UMUR_TUNGGAKAN');
    $flagRestruk = trim(col($row, $idx, 'FLAG_RESTRUK') ?? '');
    $kolAdk1 = trim(col($row, $idx, 'KOL_ADK1') ?? '');
    $periode = trim(col($row, $idx, 'PERIODE') ?? '');
    $nextPmt = trim(col($row, $idx, 'NEXT_PMT_DATE') ?? '');
    $nextPmtInt = trim(col($row, $idx, 'NEXT_PMT_INT_DATE') ?? '');

    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $periode, $m)) {
        $periodeISO = "{$m[3]}-{$m[2]}-{$m[1]}";
    } else {
        $periodeISO = $periode;
    }
    $nextPmtISO = preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $nextPmt, $m) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
    $nextPmtIntISO = preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $nextPmtInt, $m) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;

    $umurStr = trim((string)$umur);
    $umurInt = ($umurStr === '') ? null : (int) $umurStr;

    $afterBucket = LoanQualityBucketMapper::map(
        $kolekDetail !== '' ? $kolekDetail : null,
        $umurInt,
        $flagRestruk !== '' ? $flagRestruk : null,
        $kolAdk1 !== '' ? $kolAdk1 : null,
        $kolek !== '' ? $kolek : null,
        $periodeISO,
        $nextPmtISO,
        $nextPmtIntISO
    );

    $bucketCounts[$branch][$afterBucket] = ($bucketCounts[$branch][$afterBucket] ?? 0) + 1;

    if (!in_array($afterBucket, $cols, true)) {
        $skippedBucket++;
        continue;
    }

    $beforeBucket = $prevMap[$rek] ?? 'New Account';
    $byBranch[$branch][$beforeBucket][$afterBucket] = ($byBranch[$branch][$beforeBucket][$afterBucket] ?? 0) + $baki;
    $matchedRows++;
}
fclose($fh);

fwrite(STDERR, "\nDONE. total=$totalRows matched=$matchedRows skipped_norek=$skippedNoRek skipped_bucket=$skippedBucket\n\n");

echo PHP_EOL . "Raw SUM(BAKI_DEBET1) per branch (CSV source):" . PHP_EOL;
foreach ($wantedBranches as $b) {
    echo sprintf("  %-13s %s" . PHP_EOL, $b, number_format($rawSum[$b] ?? 0, 0));
}

echo PHP_EOL . "Bucket distribution per branch (CSV mapping):" . PHP_EOL;
foreach ($wantedBranches as $b) {
    echo "  $b:" . PHP_EOL;
    $bc = $bucketCounts[$b] ?? [];
    ksort($bc);
    foreach ($bc as $k => $n) {
        echo sprintf("    %-12s %d" . PHP_EOL, $k, $n);
    }
}

foreach ($wantedBranches as $branch) {
    echo PHP_EOL . "=== CSV pivot $branch | posisi=$curr | delta=$prev ===" . PHP_EOL;
    $mat = $byBranch[$branch] ?? [];

    printf("%-12s", '');
    foreach ($cols as $c) printf(" %18s", $c);
    printf(" %18s\n", 'Total');

    $colTotals = array_fill_keys($cols, 0.0);
    foreach ($labels as $row) {
        printf("%-12s", $row);
        $rowTotal = 0;
        foreach ($cols as $c) {
            $v = $mat[$row][$c] ?? 0;
            $rowTotal += $v;
            $colTotals[$c] += $v;
            printf(" %18s", number_format($v, 0));
        }
        printf(" %18s\n", number_format($rowTotal, 0));
    }
    printf("%-12s", 'Grand Total');
    $gt = 0;
    foreach ($cols as $c) {
        $gt += $colTotals[$c];
        printf(" %18s", number_format($colTotals[$c], 0));
    }
    printf(" %18s\n", number_format($gt, 0));
}
