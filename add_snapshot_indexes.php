<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Adding Indexes for Dashboard Harian Snapshot Performance ===\n\n";

$indexes = [
    'ssa_simpanan' => [
        'index_name' => 'idx_dhs_period_kanca_unit',
        'columns' => ['Month_Day_Year_of_Posisi', 'nama_cabang', 'nama_uker'],
        'description' => 'Speedup savings aggregation by period'
    ],
    'ssa_pinjaman' => [
        'index_name' => 'idx_dhs_period_kanca_unit',
        'columns' => ['periode', 'kantor_cabang', 'unit_kerja'],
        'description' => 'Speedup loan aggregation by period'
    ],
    'lw325_ph' => [
        'index_name' => 'idx_dhs_period_kanca_unit',
        'columns' => ['periode', 'kantor_cabang', 'unit_kerja'],
        'description' => 'Speedup PH aggregation by period'
    ],
];

foreach ($indexes as $table => $indexInfo) {
    echo "Table: $table\n";
    echo "Index: {$indexInfo['index_name']}\n";
    echo "Columns: " . implode(', ', $indexInfo['columns']) . "\n";
    echo "Purpose: {$indexInfo['description']}\n";
    
    // Check if index exists
    $query = $db->prepare("
        SELECT COUNT(*) as index_exists
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = 'project_abah'
        AND TABLE_NAME = ?
        AND INDEX_NAME = ?
    ");
    $query->execute([$table, $indexInfo['index_name']]);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    
    if ($result['index_exists'] > 0) {
        echo "✅ Index already exists\n\n";
    } else {
        echo "⏳ Creating index...\n";
        try {
            $columnList = implode(', ', $indexInfo['columns']);
            $sql = "ALTER TABLE $table ADD INDEX {$indexInfo['index_name']} ($columnList)";
            $db->exec($sql);
            echo "✅ Index created successfully\n\n";
        } catch (Exception $e) {
            echo "❌ Error creating index: " . $e->getMessage() . "\n\n";
        }
    }
}

echo "=== Index Creation Complete ===\n";
echo "\nExpected performance improvement:\n";
echo "Before: ~11.5 seconds per period (first rebuild with force=true)\n";
echo "After: ~6-8 seconds per period (40% faster)\n";
echo "\nNext rebuild will be faster due to improved index performance.\n";
