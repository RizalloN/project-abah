<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$path = 'C:/Users/Danang/Downloads/SSA SIMPANAN.xlsx';
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$info = $reader->listWorksheetInfo($path);
echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
