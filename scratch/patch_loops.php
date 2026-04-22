<?php
$filePath = 'app/Http/Controllers/Import/ImportExcelController.php';
$content = file_get_contents($filePath);

// Patch Simpanan MultiPN PHP Loop
$target1 = '                fputcsv($outputHandle, $row, $delimiter, \'"\', \'\\\\\');
                $writtenRows++;
            }';
$replacement1 = '                fputcsv($outputHandle, $row, $delimiter, \'"\', \'\\\\\');
                $writtenRows++;

                if ($jobId > 0 && $writtenRows % 5000 === 0) {
                    $this->checkJobTermination($jobId);
                }
            }';

// Patch Daily Loan PHP Loop
$target2 = '                fputcsv($outputHandle, $row, $delimiter, \'"\', \'\\\\\');
                $writtenRows++;
            }';
// Since both are identical, we might need a more unique target.
// I'll search for the function context.

if (strpos($content, $target1) !== false) {
    $content = str_replace($target1, $replacement1, $content);
    echo "Patched loops successfully.\n";
} else {
    echo "Target not found.\n";
}

file_put_contents($filePath, $content);
