<?php
require __DIR__ . '/../vendor/autoload.php';

$method = new ReflectionMethod(Illuminate\Queue\Failed\DatabaseFailedJobProvider::class, 'log');
echo "Name: " . $method->getName() . "\n";
foreach ($method->getParameters() as $param) {
    echo "Param: " . $param->getName() . ( $param->isOptional() ? " (Default: " . var_export($param->getDefaultValue(), true) . ")" : "" ) . "\n";
}
