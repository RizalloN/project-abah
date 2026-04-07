<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection();
var_dump(get_class($conn));

try {
    $conn->getPdo();
    echo "OK";
} catch (\Exception $e) {
    echo $e->getMessage();
}
