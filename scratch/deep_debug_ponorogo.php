<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\RkaLookupService;

class DeepDebug extends RkaLookupService {
    public function debugPonorogo($monthCol, $year) {
        $definitions = [
            'total' => ['mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL']]
        ];
        
        $normalizedKancas = [strtoupper('KC Ponorogo')]; // Manual normalization for simplicity
        
        // Emulate loadRows
        $rows = DB::table('rka')
            ->where('kanca', 'LIKE', '%Ponorogo%')
            ->select('kanca', 'desc_uker', 'mata_anggaran', $monthCol)
            ->whereYear('created_at', $year)
            ->get();
            
        echo "Found " . $rows->count() . " raw rows for Ponorogo in DB\n";
        
        $count = 0;
        foreach ($rows as $row) {
            $kanca_key = strtoupper(trim($row->kanca));
            $ma_key = strtoupper(trim($row->mata_anggaran));
            
            if ($ma_key === 'C. RECOVERY EKSTRAKOMTABEL') {
                echo "Match MA: " . $row->mata_anggaran . " | Kanca: " . $row->kanca . " | Value: " . $row->{$monthCol} . "\n";
                $count += (float)$row->{$monthCol};
            }
        }
        echo "Manual count: " . $count . "\n";
    }
}

$debug = new DeepDebug();
$debug->debugPonorogo('apr', 2026);
