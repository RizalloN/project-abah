<?php

use App\Support\ConsumerRmRealizationCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cutoff = '2026-09-12';
$start = '2026-09-01';
$baseline = '2026-08-31';
$table = 'consumer_rm_position_history';

$periods = DB::table($table)
    ->whereBetween('periode', [$start, $cutoff])
    ->distinct()
    ->orderBy('periode')
    ->pluck('periode')
    ->map(fn ($v) => substr((string) $v, 0, 10))
    ->all();

echo json_encode(['periods' => $periods], JSON_PRETTY_PRINT).PHP_EOL;

$bookings = DB::table($table)
    ->whereBetween('periode', [$start, $cutoff])
    ->where('produk', 'BRIGUNA-KONSUMER')
    ->whereBetween('tgl_realisasi', [$start, $cutoff])
    ->whereNotNull('account_key')->where('account_key', '<>', '')
    ->whereNotNull('cifno_clean')->where('cifno_clean', '<>', '')
    ->selectRaw("UPPER(TRIM(cifno_clean)) cif")
    ->selectRaw("UPPER(TRIM(account_key)) account")
    ->selectRaw("MIN(periode) first_seen")
    ->selectRaw("MAX(periode) last_seen")
    ->selectRaw("MIN(tgl_realisasi) tgl_realisasi")
    ->selectRaw("MAX(COALESCE(plafon,0)) plafon")
    ->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(pn_pemrakarsa),'') ORDER BY periode SEPARATOR ' || ') initiators")
    ->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(rm),'') ORDER BY periode SEPARATOR ' || ') managers")
    ->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(cabang),'') ORDER BY periode SEPARATOR ' || ') cabang")
    ->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(unit),'') ORDER BY periode SEPARATOR ' || ') unit")
    ->groupByRaw('UPPER(TRIM(cifno_clean)), UPPER(TRIM(account_key))')
    ->orderBy('first_seen')->orderBy('account')
    ->get();

echo json_encode([
    'booking_count' => $bookings->count(),
    'gross' => $bookings->sum(fn ($r) => (float) $r->plafon),
    'blank_both' => $bookings->filter(fn ($r) => trim((string) $r->initiators) === '' && trim((string) $r->managers) === '')->count(),
], JSON_PRETTY_PRINT).PHP_EOL;

$result = (new ConsumerRmRealizationCalculator)->calculate($cutoff, $cutoff, 'BRIGUNA-KONSUMER');
$rows = collect($result)->values()->map(fn ($r) => [
    'rm' => $r['rm'],
    'cabang' => $r['cabang'],
    'deb' => $r['realisasi_deb'],
    'os' => $r['realisasi_os'],
    'new_deb' => $r['realisasi_baru_deb'],
    'new_os' => $r['realisasi_baru_os'],
    'sup_deb' => $r['suplesi_deb'],
    'sup_os' => $r['suplesi_os'],
])->sortBy('rm')->values();
echo json_encode(['calculator' => $rows], JSON_PRETTY_PRINT).PHP_EOL;

// Full booking rows are intentionally not printed; subsequent diagnostics are
// kept concise enough to inspect interactively.

echo "BLANK_CIF_CONTEXT\n";
foreach ($bookings->filter(fn ($r) => trim((string) $r->initiators) === '' && trim((string) $r->managers) === '') as $booking) {
    $context = DB::table($table)
        ->whereIn('periode', [$baseline, (string) $booking->first_seen, $cutoff])
        ->where('produk', 'BRIGUNA-KONSUMER')
        ->where('cifno_clean', $booking->cif)
        ->select(['periode', 'account_key', 'tgl_realisasi', 'plafon', 'baki_debet', 'rm', 'pn_pengelola', 'pn_pemrakarsa', 'cabang', 'unit'])
        ->orderBy('periode')->orderBy('account_key')->get();
    echo json_encode(['booking' => $booking, 'context' => $context], JSON_UNESCAPED_SLASHES).PHP_EOL;
}

$interestingColumns = collect(DB::getSchemaBuilder()->getColumnListing('daily_loan_dinamis'))
    ->filter(fn ($column) => preg_match('/pn|rm|mantri|ao|pemasar|refer|sales|nama|cif|rekening|restruk/i', $column))
    ->values()->all();
echo json_encode(['interesting_raw_columns' => $interestingColumns], JSON_PRETTY_PRINT).PHP_EOL;

$blankAccounts = $bookings
    ->filter(fn ($r) => trim((string) $r->initiators) === '' && trim((string) $r->managers) === '')
    ->pluck('account')->all();
$rawBlank = DB::table('daily_loan_dinamis')
    ->where('periode', $cutoff)
    ->whereIn('nomor_rekening1', $blankAccounts)
    ->get($interestingColumns);
echo json_encode(['raw_blank' => $rawBlank], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
