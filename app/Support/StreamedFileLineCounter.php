<?php

namespace App\Support;

use RuntimeException;

class StreamedFileLineCounter
{
    public static function countDataRows(string $path, int $headerRows = 1): int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("File tidak dapat dibuka untuk menghitung baris: {$path}");
        }

        $lineFeeds = 0;
        $carriageReturns = 0;
        $lastByte = null;
        $hasContent = false;

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 1048576);
                if ($chunk === false) {
                    throw new RuntimeException("Gagal membaca file saat menghitung baris: {$path}");
                }

                if ($chunk === '') {
                    continue;
                }

                $hasContent = true;
                $lineFeeds += substr_count($chunk, "\n");
                $carriageReturns += substr_count($chunk, "\r");
                $lastByte = substr($chunk, -1);
            }
        } finally {
            fclose($handle);
        }

        if (! $hasContent) {
            return 0;
        }

        $lineCount = $lineFeeds > 0 ? $lineFeeds : $carriageReturns;
        $endsWithLineBreak = $lastByte === "\n" || $lastByte === "\r";
        if (! $endsWithLineBreak) {
            $lineCount++;
        }

        return max(0, $lineCount - max(0, $headerRows));
    }
}
