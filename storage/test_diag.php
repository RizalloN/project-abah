<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cmd = new \App\Console\Commands\EnsureQueueWorkerRunning();
$ref = new \ReflectionMethod($cmd, 'phpProcessCommandLines');
$lines = $ref->invoke($cmd);
echo "LINES: " . strlen($lines) . " bytes\n";
echo $lines . "\n";

$finder = new \Symfony\Component\Process\PhpExecutableFinder();
echo "PhpExecutableFinder: " . var_export($finder->find(false), true) . "\n";
echo "PHP_BINARY: " . PHP_BINARY . "\n";
echo "PHP_BINDIR: " . PHP_BINDIR . "\n";
echo "PHP_SAPI: " . PHP_SAPI . "\n";
