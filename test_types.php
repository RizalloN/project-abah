<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$connector = new \Illuminate\Database\Connectors\MySqlConnector();
$config = config('database.connections.mysql');
$options = $connector->getOptions($config);

echo "Poisoned array:\n";
foreach ($options as $k => $v) {
    echo gettype($k)."($k) => ".gettype($v)."(" . var_export($v, true) . ")\n";
}

echo "\nLiteral array:\n";
$literal = [
    PDO::ATTR_CASE => PDO::CASE_NATURAL,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_LOCAL_INFILE => true,
];
foreach ($literal as $k => $v) {
    echo gettype($k)."($k) => ".gettype($v)."(" . var_export($v, true) . ")\n";
}
