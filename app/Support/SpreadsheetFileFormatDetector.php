<?php

namespace App\Support;

use ZipArchive;

final class SpreadsheetFileFormatDetector
{
    public static function detect(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $signature = fread($handle, 8);
        } finally {
            fclose($handle);
        }

        if ($signature === false) {
            return null;
        }

        if (str_starts_with($signature, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return 'xls';
        }

        if (!str_starts_with($signature, 'PK')) {
            return null;
        }

        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            return null;
        }

        try {
            $contentTypes = $archive->getFromName('[Content_Types].xml');
            $workbook = $archive->getFromName('xl/workbook.xml');
            if (!is_string($contentTypes) || $contentTypes === ''
                || !is_string($workbook) || $workbook === '') {
                return null;
            }

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                $name = (string) ($entry['name'] ?? '');
                if (!str_starts_with($name, 'xl/worksheets/') || !str_ends_with($name, '.xml')) {
                    continue;
                }

                $worksheet = $archive->getFromIndex($index);
                if (is_string($worksheet) && $worksheet !== '') {
                    return 'xlsx';
                }
            }

            return null;
        } finally {
            $archive->close();
        }
    }
}
