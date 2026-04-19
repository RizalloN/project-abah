<?php
require __DIR__ . '/../vendor/autoload.php';

$method = new ReflectionMethod(Illuminate\Queue\DatabaseQueue::class, 'pushToDatabase');
echo "Name: " . $method->getName() . "\n";
foreach ($method->getParameters() as $param) {
    echo "Param: " . $param->getName() . ( $param->isOptional() ? " (Default: " . var_export($param->getDefaultValue(), true) . ")" : "" ) . "\n";
}
