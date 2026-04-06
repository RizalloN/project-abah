<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$connector = new \Illuminate\Database\Connectors\MySqlConnector();
$config = config('database.connections.mysql');
$options = $connector->getOptions($config);
var_dump($options);
