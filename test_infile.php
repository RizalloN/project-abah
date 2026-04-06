<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$connector = new \Illuminate\Database\Connectors\MySqlConnector();
try {
    $conn = $connector->createConnection("mysql:host=127.0.0.1;port=3306;dbname=project_abah", ['username'=>'root','password'=>''], [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ]);
    echo "WITH LOCAL INFILE => OK\n";
} catch (\Exception $e) {
    echo "WITH LOCAL INFILE => FAILED: " . $e->getMessage() . "\n";
}
