<?php

$logPath = "C:\\Users\\msi\\.gemini\\antigravity\\brain\\c29b76b3-c802-47a8-b712-b361abb1182f\\.system_generated\\logs\\transcript.jsonl";

if (!file_exists($logPath)) {
    echo "File not found: $logPath\n";
    exit(1);
}

$file = fopen($logPath, "r");
$stepIndex = 0;

while (($line = fgets($file)) !== false) {
    $data = json_decode($line, true);
    if (!$data) continue;
    
    $index = $data['step_index'] ?? $stepIndex;
    
    if ($index >= 388 && $index <= 393) {
        echo "=== Step $index ===\n";
        echo $line . "\n\n";
    }
    $stepIndex++;
}
fclose($file);
