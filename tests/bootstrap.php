<?php

$basePath = dirname(__DIR__);
$testingPath = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'testing';
$testingDatabase = $testingPath . DIRECTORY_SEPARATOR . 'phpunit-' . getmypid() . '.sqlite';
$templateDatabase = $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'testing.sqlite';

if (!is_dir($testingPath)) {
    mkdir($testingPath, 0777, true);
}

if (is_file($templateDatabase)) {
    copy($templateDatabase, $testingDatabase);
} elseif (!file_exists($testingDatabase)) {
    touch($testingDatabase);
}

$forcedTestEnvironment = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $testingPath . DIRECTORY_SEPARATOR . 'config-' . getmypid() . '.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $testingDatabase,
    'DB_URL' => '',
    'IMPORT_CACHE_STORE' => '',
];

foreach ($forcedTestEnvironment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

register_shutdown_function(static function () use ($testingDatabase): void {
    if (is_file($testingDatabase)) {
        @unlink($testingDatabase);
    }
});

require $basePath . '/vendor/autoload.php';
