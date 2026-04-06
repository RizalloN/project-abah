<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$connector = new \Illuminate\Database\Connectors\MySqlConnector();
$config = config('database.connections.mysql');
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=project_abah';
$options = $connector->getOptions($config);

try {
    $conn = $connector->createConnection($dsn, $config, $options);
    echo "createConnection OK\n";
} catch (\Exception $e) {
    echo "createConnection FAILED: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
