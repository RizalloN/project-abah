require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$date = '2026-03-31';
$rows = DB::table('jumlah_merchant_detail')
    ->selectRaw("UPPER(TRIM(NAMA_KANCA)) as branch, COUNT(*) as rows_count, COUNT(DISTINCT MID) as distinct_mid, COUNT(DISTINCT TID) as distinct_tid, COUNT(DISTINCT CONCAT(COALESCE(MID,''),'|',COALESCE(TID,''))) as distinct_mid_tid")
    ->whereDate('POSISI', $date)
    ->whereIn(DB::raw('UPPER(TRIM(NAMA_KANCA))'), ['MADIUN','MAGETAN','NGAWI','PONOROGO'])
    ->groupBy(DB::raw('UPPER(TRIM(NAMA_KANCA))'))
    ->orderBy('branch')
    ->get();
echo json_encode($rows, JSON_PRETTY_PRINT);
