<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$config = config('database.connections.mysql');

class MyTestConnector extends Illuminate\Database\Connectors\MySqlConnector {
    public function __construct() {}
    public function getDsn(array $config) {
        return parent::getDsn($config);
    }
}
$connector = new MyTestConnector();
$dsn = $connector->getDsn($config);
echo "DSN: $dsn\n";
