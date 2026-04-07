require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$date = '2026-03-31';
$rows = DB::table('jumlah_merchant_detail')
    ->selectRaw("NAMA_KANCA as branch, COUNT(DISTINCT MID) as mid_count")
    ->whereDate('POSISI', $date)
    ->whereIn('NAMA_KANCA', ['MADIUN','MAGETAN','NGAWI','PONOROGO'])
    ->groupBy('NAMA_KANCA')
    ->orderBy('NAMA_KANCA')
    ->get();
echo json_encode($rows, JSON_PRETTY_PRINT);
