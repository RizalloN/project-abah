<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$config = config('database.connections.mysql');

try {
    $connector = new \Illuminate\Database\Connectors\MySqlConnector();
    $conn = $connector->createConnection("mysql:host=127.0.0.1;port=3306;dbname=project_abah", $config, []);
    echo "Laravel Connector with EMPTY options OK\n";
} catch (\Exception $e) {
    echo "Laravel Connector Failed: " . $e->getMessage() . "\n";
}
