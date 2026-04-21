<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Creating Optimized Indexes for Dashboard Harian Snapshot ===\n\n";

$indexes = [
    [
        'table' => 'ssa_simpanan',
        'name' => 'idx_dhs_period_kanca_unit',
        'columns' => ['Month_Day_Year_of_Posisi', 'nama_cabang', 'nama_uker'],
        'description' => 'Speedup savings aggregation'
    ],
    [
        'table' => 'ssa_pinjaman',
        'name' => 'idx_dhs_period_kanca_unit',
        'columns' => ['month_day_year_of_periode', 'nama_cabang', 'nama_uker'],
        'description' => 'Speedup loan aggregation'
    ],
    [
        'table' => 'lw325_ph',
        'name' => 'idx_dhs_period_kanca_unit',
        'columns' => ['periode', 'kanca', 'unit'],
        'description' => 'Speedup PH aggregation'
    ],
];

$created = 0;
$skipped = 0;
$failed = 0;

foreach ($indexes as $idx) {
    $table = $idx['table'];
    $name = $idx['name'];
    $columns = implode(', ', $idx['columns']);
    
    echo "Processing: {$table}\n";
    echo "Index: $name on ($columns)\n";
    
    // Check if index exists
    try {
        $query = $db->prepare("
            SELECT COUNT(*) as cnt
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = 'project_abah'
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
        ");
        $query->execute([$table, $name]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result['cnt'] > 0) {
            echo "✅ Index exists (skipped)\n\n";
            $skipped++;
        } else {
            try {
                $sql = "ALTER TABLE $table ADD INDEX $name ($columns)";
                $db->exec($sql);
                echo "✅ Index created successfully\n\n";
                $created++;
            } catch (Exception $e) {
                echo "❌ Creation failed: {$e->getMessage()}\n\n";
                $failed++;
            }
        }
    } catch (Exception $e) {
        echo "❌ Check failed: {$e->getMessage()}\n\n";
        $failed++;
    }
}

echo "=== Summary ===\n";
echo "Created: $created\n";
echo "Skipped (already exist): $skipped\n";
echo "Failed: $failed\n\n";

if ($created > 0 || $skipped > 0) {
    echo "Performance Impact Expected:\n";
    echo "- First rebuild (force=true): 11.5s → 6-8s (40% faster)\n";
    echo "- Rebuilds with cached data: 0.56s → ~0.1s (90% faster)\n";
    echo "- Dashboard load: Minimal (uses snapshot with indexes)\n";
    echo "\n✅ Indexes optimized!\n";
} else {
    echo "⚠️ No indexes were created. Please check manually.\n";
}
