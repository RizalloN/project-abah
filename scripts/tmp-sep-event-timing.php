<?php

// Read-only diagnostic for September 2026 Briguna timing sensitivities.
use App\Support\ConsumerRmRealizationCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$references = [
    '00247876' => ['Bagus', 15, 2427000000],
    '00079608' => ['Aris', 21, 1555000000],
    '00187063' => ['Rona', 10, 1049000000],
    '00052944' => ['Ariani', 17, 1330000000],
    '00275199' => ['Ratna', 16, 1432000000],
    '00409222' => ['Novan', 8, 941000000],
    '00322928' => ['Dimas', 22, 1989000000],
    '00021951' => ['Titin', 15, 1223000000],
    '00322927' => ['Farid', 10, 490000000],
    '00323014' => ['Zulfa', 11, 1372000000],
    '00300861' => ['Ridho', 12, 1110000000],
    '00089738' => ['Ardini', 12, 1012000000],
];
$source = 'consumer_rm_position_history';
$bookingRows = DB::table($source)
    ->whereBetween('periode', ['2026-09-01', '2026-09-12'])
    ->where('produk', 'BRIGUNA-KONSUMER')
    ->whereBetween('tgl_realisasi', ['2026-09-01', '2026-09-12'])
    ->whereNotNull('account_key')->where('account_key', '<>', '')
    ->whereNotNull('cifno_clean')->where('cifno_clean', '<>', '')
    ->get(['periode','cifno_clean','account_key','plafon','pn_pemrakarsa','rm']);
$bookings = [];
foreach ($bookingRows as $r) {
    $cif = strtoupper(trim((string) $r->cifno_clean));
    $account = strtoupper(trim((string) $r->account_key));
    $key = $cif.'|'.$account;
    $rmText = trim((string) $r->pn_pemrakarsa) ?: trim((string) $r->rm);
    $bookings[$key] ??= ['cif'=>$cif,'account'=>$account,'first'=>(string)$r->periode,
        'last'=>(string)$r->periode,'gross'=>0.0,'rm'=>''];
    $bookings[$key]['first'] = min($bookings[$key]['first'], (string)$r->periode);
    $bookings[$key]['last'] = max($bookings[$key]['last'], (string)$r->periode);
    $bookings[$key]['gross'] = max($bookings[$key]['gross'], (float)$r->plafon);
    if ($bookings[$key]['rm'] === '' && preg_match('/^(\d{8})/', $rmText, $match)) {
        $bookings[$key]['rm'] = $match[1];
    }
}
$cifs = array_values(array_unique(array_column($bookings, 'cif')));
$positionRows = DB::table($source)
    ->whereIn('cifno_clean', $cifs)
    ->whereIn('periode', array_merge(['2026-08-31'], ['2026-09-01','2026-09-02','2026-09-04','2026-09-05','2026-09-06','2026-09-07','2026-09-08','2026-09-09','2026-09-11','2026-09-12']))
    ->where('produk', 'BRIGUNA-KONSUMER')
    ->get(['periode','cifno_clean','account_key','baki_debet']);
$positions = [];
foreach ($positionRows as $r) {
    $date = substr((string)$r->periode, 0, 10);
    $cif = strtoupper(trim((string)$r->cifno_clean));
    $account = strtoupper(trim((string)$r->account_key));
    $positions[$cif][$date][$account] = max((float)($positions[$cif][$date][$account] ?? 0), (float)$r->baki_debet);
}
$events = [];
foreach ($bookings as $booking) {
    $key = $booking['cif'].'|'.$booking['first'];
    $events[$key] ??= ['cif'=>$booking['cif'], 'first'=>$booking['first'], 'accounts'=>[], 'gross'=>0];
    $events[$key]['accounts'][] = $booking;
    $events[$key]['gross'] += $booking['gross'];
}

function positionDate(array $event, array $positions, string $method, ?string $nextEvent): string {
    $cif = $event['cif'];
    $first = $event['first'];
    $dates = array_values(array_filter(array_keys($positions[$cif] ?? []), fn($date) => $date >= $first));
    sort($dates);
    if (str_ends_with($method, '_isolated')) {
        $dates = array_values(array_filter($dates, fn($date) => $nextEvent === null || $date < $nextEvent));
        $method = substr($method, 0, -9);
    }
    if ($method === 'first' || count($dates) < 2) return $first;
    if ($method === 'next') return $dates[1];
    if ($method === 'cutoff') return end($dates);
    if ($method === 'last_booking') {
        $last = min(array_column($event['accounts'], 'last'));
        return $last >= $first ? $last : $first;
    }
    $baselineAccounts = $positions[$cif]['2026-08-31'] ?? [];
    if ($method === 'first_closure' || $method === 'first_decline') {
        foreach ($dates as $date) {
            $current = $positions[$cif][$date] ?? [];
            $hasBookings = true;
            foreach ($event['accounts'] as $booking) {
                if (!array_key_exists($booking['account'], $current)) $hasBookings = false;
            }
            if (!$hasBookings) continue;
            $gone = 0;
            foreach ($baselineAccounts as $account => $os) {
                if (!array_key_exists($account, $current) || $current[$account] <= 0) $gone++;
            }
            if (($method === 'first_closure' && $gone === count($baselineAccounts) && $gone > 0)
                || ($method === 'first_decline' && $gone > 0)) return $date;
        }
    }
    return $first;
}

$methods = ['first','first_cap','next','next_cap','next_isolated','cutoff','cutoff_cap','last_booking','last_booking_cap','first_closure','first_closure_cap','first_closure_isolated','first_closure_isolated_cap','first_decline','first_decline_cap'];
$totals = [];
$eventChanges = [];
$excessEvents = [];
foreach ($events as $eventKey => $event) {
    $cif = $event['cif'];
    $baseline = array_sum($positions[$cif]['2026-08-31'] ?? []);
    $nextEvent = null;
    foreach ($events as $otherEvent) {
        if ($otherEvent['cif'] === $cif && $otherEvent['first'] > $event['first'] && ($nextEvent === null || $otherEvent['first'] < $nextEvent)) {
            $nextEvent = $otherEvent['first'];
        }
    }
    foreach ($methods as $method) {
        $cap = str_ends_with($method, '_cap');
        $timing = $cap ? substr($method, 0, -4) : $method;
        $date = positionDate($event, $positions, $timing, $nextEvent);
        $os = array_sum($positions[$cif][$date] ?? []);
        $net = $baseline <= 0 ? $event['gross'] : max(0, $os-$baseline);
        if ($timing === 'first' && $net > $event['gross']+1) {
            $excessEvents[] = [$eventKey,round($net),round($event['gross']),round($net-$event['gross'])];
        }
        if ($cap) $net = min($event['gross'], $net);
        $eventChanges[$method][$eventKey] = [$date, $net];
        foreach ($event['accounts'] as $booking) {
            $rm = $booking['rm'];
            if (!isset($references[$rm])) continue;
            $share = $event['gross'] > 0 ? $booking['gross']/$event['gross'] : 1/count($event['accounts']);
            $totals[$method][$rm] = ($totals[$method][$rm] ?? 0) + $net*$share;
        }
    }
}
$calculator = (new ConsumerRmRealizationCalculator)->calculate('2026-09-12','2026-09-12','BRIGUNA-KONSUMER');
$baselineCalc = [];
foreach ($calculator as $row) {
    if (preg_match('/^(\d{8})/', (string)$row['rm'], $m)) $baselineCalc[$m[1]] = [$row['realisasi_deb'], $row['realisasi_os']];
}
echo 'bookings='.count($bookings).' events='.count($events).' known='.count(array_filter($bookings, fn($b)=>isset($references[$b['rm']]))).' positions='.count($positionRows).PHP_EOL;
echo 'first_vs_calculator'.PHP_EOL;
foreach ($references as $rm => [$name,$count,$ref]) {
    echo "$name ($rm) reference=$count/".round($ref)." calc=".($baselineCalc[$rm][0] ?? 'n/a').'/'.round($baselineCalc[$rm][1] ?? 0)." reconstructed=".round($totals['first'][$rm] ?? 0)." delta=".round(($totals['first'][$rm] ?? 0)-($baselineCalc[$rm][1] ?? 0)).PHP_EOL;
}
echo 'scenario_metrics'.PHP_EOL;
foreach ($methods as $method) {
    $abs = 0; $signed = 0; $modeled = 0; $reference = 0; $improved = 0; $worsened = 0; $within500k = 0;
    foreach ($references as $rm => [$name,$count,$ref]) {
        $v = $totals[$method][$rm] ?? 0;
        $baselineError = abs(($totals['first'][$rm] ?? 0)-$ref);
        $error = abs($v-$ref);
        $abs += $error; $signed += $v-$ref; $modeled += $v; $reference += $ref;
        if ($error < $baselineError-1) $improved++;
        if ($error > $baselineError+1) $worsened++;
        if ($error <= 500000) $within500k++;
    }
    $changed = 0;
    foreach ($events as $key => $_) if (abs($eventChanges[$method][$key][1]-$eventChanges['first'][$key][1]) > 1) $changed++;
    echo "$method abs=".round($abs)." wape=".round($abs/$reference*100,4)."% signed=".round($signed)." total=".round($modeled)." improve=$improved worsen=$worsened within500k=$within500k changed_events=$changed".PHP_EOL;
}
echo 'rm_matrix'.PHP_EOL;
foreach ($references as $rm => [$name,$count,$ref]) {
    echo "$name ref=".round($ref);
    foreach ($methods as $method) echo " $method=".round($totals[$method][$rm] ?? 0);
    echo PHP_EOL;
}
echo 'excess_events='.json_encode(array_values(array_unique($excessEvents, SORT_REGULAR))).PHP_EOL;
echo 'event_changes_first_closure='.json_encode(array_filter($eventChanges['first_closure'], fn($v,$key)=>abs($v[1]-$eventChanges['first'][$key][1]) > 1, ARRAY_FILTER_USE_BOTH)).PHP_EOL;
echo 'event_changes_first_closure_isolated='.json_encode(array_filter($eventChanges['first_closure_isolated'], fn($v,$key)=>abs($v[1]-$eventChanges['first'][$key][1]) > 1, ARRAY_FILTER_USE_BOTH)).PHP_EOL;
foreach ($eventChanges['first_closure_isolated'] as $key => [$date,$net]) {
    if (abs($net-$eventChanges['first'][$key][1]) <= 1) continue;
    $event = $events[$key];
    $rm = implode(',', array_unique(array_column($event['accounts'], 'rm')));
    $baselineAccounts = $positions[$event['cif']]['2026-08-31'] ?? [];
    $firstAccounts = $positions[$event['cif']][$event['first']] ?? [];
    $laterAccounts = $positions[$event['cif']][$date] ?? [];
    $oldReappeared = [];
    foreach ($positions[$event['cif']] as $laterDate => $accountsAtDate) {
        if ($laterDate <= $date) continue;
        foreach ($baselineAccounts as $oldAccount => $_) {
            if (isset($accountsAtDate[$oldAccount]) && $accountsAtDate[$oldAccount] > 0) $oldReappeared[$oldAccount][] = $laterDate;
        }
    }
    echo 'event_detail='.json_encode([
        'event'=>$key,'rm'=>$rm,'gross'=>round($event['gross']),
        'first'=>$eventChanges['first'][$key], 'chosen'=>[$date,round($net)],
        'prior_accounts'=>$baselineAccounts,'first_accounts'=>$firstAccounts,'chosen_accounts'=>$laterAccounts,
        'old_reappeared'=>$oldReappeared,
    ]).PHP_EOL;
}
