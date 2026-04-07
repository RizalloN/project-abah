<?php
try {
    $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ];
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=project_abah', 'root', '', $options);
    echo "Raw PDO Connected\n";
    
    // Mimic Laravel's post-connection queries
    $pdo->prepare("set names 'utf8mb4' collate 'utf8mb4_unicode_ci'")->execute();
    echo "Set names OK\n";
    
    $modes = "ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION";
    $pdo->prepare("set session sql_mode='{$modes}'")->execute();
    echo "Set sql_mode OK\n";
    
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
