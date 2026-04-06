<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$connector = new \Illuminate\Database\Connectors\MySqlConnector();
$base_options = [
    PDO::ATTR_CASE => PDO::CASE_NATURAL,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_EMULATE_PREPARES => false,
];

foreach ($base_options as $key => $val) {
    try {
        $test_opts = [$key => $val];
        $conn = $connector->createConnection("mysql:host=127.0.0.1;port=3306;dbname=project_abah", ['username'=>'root','password'=>''], $test_opts);
        echo "Option {$key} => OK\n";
    } catch (\Exception $e) {
        echo "Option {$key} => FAILED: " . $e->getMessage() . "\n";
    }
}
try {
    $conn = $connector->createConnection("mysql:host=127.0.0.1;port=3306;dbname=project_abah", ['username'=>'root','password'=>''], $base_options);
    echo "All base options together => OK\n";
} catch (\Exception $e) {
    echo "All base options together => FAILED\n";
}
