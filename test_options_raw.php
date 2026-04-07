<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$connector = new \Illuminate\Database\Connectors\MySqlConnector();
$config = config('database.connections.mysql');
$options = $connector->getOptions($config);

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=project_abah", 'root', '', $options);
    echo "Raw PDO with Laravel options OK\n";
} catch (\Exception $e) {
    echo "Raw PDO with Laravel options FAILED: " . $e->getMessage() . "\n";
}
