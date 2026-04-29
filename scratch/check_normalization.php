<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\RkaLookupService;

$service = new RkaLookupService();
$rc = new ReflectionClass($service);
$method = $rc->getMethod('normalizeScopeValue');
$method->setAccessible(true);

echo "KC Ponorogo -> " . ($method->invoke($service, 'KC Ponorogo') ?? 'NULL') . "\n";
echo "KC PONOROGO -> " . ($method->invoke($service, 'KC PONOROGO') ?? 'NULL') . "\n";
echo "KC  PONOROGO -> " . ($method->invoke($service, 'KC  PONOROGO') ?? 'NULL') . "\n";
echo "70-KC Ponorogo -> " . ($method->invoke($service, '70-KC Ponorogo') ?? 'NULL') . "\n";
