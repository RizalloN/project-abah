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
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=project_abah', 'root', null, $options);
    echo "Raw PDO with NULL password OK\n";
} catch (\Exception $e) {
    echo "Raw PDO Failed: " . $e->getMessage() . "\n";
}
