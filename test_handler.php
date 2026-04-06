<?php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=project_abah', 'root', '');
    echo "Connected successfully WITH error handler.\n";
} catch (\Throwable $e) {
    echo "Crashed: " . get_class($e) . " - " . $e->getMessage() . "\n";
}
