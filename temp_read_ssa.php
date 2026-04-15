<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$path = 'C:/Users/Danang/Downloads/SSA SIMPANAN.xlsx';
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($path);
foreach ($spreadsheet->getWorksheetIterator() as $ws) {
    echo "SHEET: {$ws->getTitle()}\n";
    $highestRow = min($ws->getHighestDataRow(), 20);
    $highestColumn = $ws->getHighestDataColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    $limitCol = min($highestColumnIndex, 30);
    echo "ROWS={$ws->getHighestDataRow()} COLS={$highestColumnIndex}\n";
    for ($row = 1; $row <= $highestRow; $row++) {
        $values = [];
        for ($col = 1; $col <= $limitCol; $col++) {
            $values[] = $ws->getCellByColumnAndRow($col, $row)->getValue();
        }
        echo $row . ': ' . json_encode($values, JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "----\n";
}
