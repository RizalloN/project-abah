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
            return self::isValidOleContainer($path) ? 'xls' : null;
        }

        if (! str_starts_with($signature, 'PK')) {
            return null;
        }

        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            return null;
        }

        try {
            $contentTypes = $archive->getFromName('[Content_Types].xml');
            $workbook = $archive->getFromName('xl/workbook.xml');
            if (! is_string($contentTypes) || $contentTypes === ''
                || ! is_string($workbook) || $workbook === '') {
                return null;
            }

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                $name = (string) ($entry['name'] ?? '');
                if (! str_starts_with($name, 'xl/worksheets/') || ! str_ends_with($name, '.xml')) {
                    continue;
                }

                // Satu byte cukup untuk membuktikan entry worksheet dapat dibaca.
                // Jangan ekstrak seluruh XML karena worksheet valid dapat berukuran
                // ratusan MB walaupun paket XLSX masih di bawah batas upload.
                $worksheet = $archive->getFromIndex($index, 1);
                if (is_string($worksheet) && $worksheet !== '') {
                    return 'xlsx';
                }
            }

            return null;
        } finally {
            $archive->close();
        }
    }

    public static function isValidOleContainer(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 512);
        } finally {
            fclose($handle);
        }

        if (! is_string($header) || strlen($header) !== 512) {
            return false;
        }

        $majorVersion = unpack('vvalue', substr($header, 26, 2))['value'] ?? 0;
        $byteOrder = unpack('vvalue', substr($header, 28, 2))['value'] ?? 0;
        $sectorShift = unpack('vvalue', substr($header, 30, 2))['value'] ?? 0;
        $miniSectorShift = unpack('vvalue', substr($header, 32, 2))['value'] ?? 0;
        $fatSectorCount = unpack('Vvalue', substr($header, 44, 4))['value'] ?? 0;
        $directorySector = unpack('Vvalue', substr($header, 48, 4))['value'] ?? 0xFFFFFFFF;

        if (! in_array($majorVersion, [3, 4], true)
            || $byteOrder !== 0xFFFE
            || ! in_array($sectorShift, [9, 12], true)
            || $miniSectorShift !== 6
            || $fatSectorCount < 1
            || $directorySector === 0xFFFFFFFF) {
            return false;
        }

        $sectorSize = 1 << $sectorShift;
        $fileSize = filesize($path);
        if (! is_int($fileSize) || $fileSize < $sectorSize * 2) {
            return false;
        }

        $directoryOffset = ($directorySector + 1) * $sectorSize;

        return $directoryOffset >= $sectorSize
            && $directoryOffset + $sectorSize <= $fileSize;
    }
}
