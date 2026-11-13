<?php

namespace App\Services\Import\SchemaDrift;

use SimpleXMLElement;
use XMLReader;
use ZipArchive;

/**
 * Single-pass streaming reader for OOXML spreadsheets (.xlsx / .xlsm).
 *
 * Avoids PhpSpreadsheet's O(N^2) full-workbook chunk reloads when sampling or
 * structurally validating large spreadsheets, while matching its raw
 * unformatted cell values and active-sheet selection.
 */
class XlsxStreamReader
{
    /**
     * Reads up to $limit rows from the active worksheet.
     *
     * @return array{rows: array<int, array<int, string>>, sheet_name: string}|null
     */
    public function readSampleRows(string $path, int $limit): ?array
    {
        $rows = [];
        $sheetName = $this->streamSheet(
            $path,
            null,
            $limit,
            function (int $rowNumber, array $cells, int $maxCol) use (&$rows, $limit): bool {
                while (count($rows) < $rowNumber - 1) {
                    $rows[] = [];
                }
                $rows[$rowNumber - 1] = $this->densify($cells, $maxCol);

                return $rowNumber < $limit;
            }
        );

        if ($sheetName === null) {
            return null;
        }

        return [
            'rows' => array_slice($rows, 0, $limit),
            'sheet_name' => $sheetName,
        ];
    }

    /**
     * Streams non-blank rows starting at 1-based Excel row $startRow.
     *
     * @param callable(int, array<int, string>): void $onRow receives 1-based row number and dense cells
     */
    public function streamRows(string $path, ?string $sheetName, int $startRow, int $minWidth, callable $onRow): bool
    {
        $resolved = $this->streamSheet(
            $path,
            $sheetName,
            null,
            function (int $rowNumber, array $cells, int $maxCol) use ($startRow, $minWidth, $onRow): bool {
                if ($rowNumber < $startRow) {
                    return true;
                }
                $onRow($rowNumber, $this->densify($cells, max($minWidth - 1, $maxCol)));

                return true;
            }
        );

        return $resolved !== null;
    }

    /**
     * @param callable(int, array<int, string>, int): bool $onRow return false to stop early
     */
    private function streamSheet(string $path, ?string $sheetName, ?int $maxRow, callable $onRow): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            $entries = $this->indexEntries($zip);
            $target = $this->resolveSheetEntry($zip, $entries, $sheetName);
            if ($target === null) {
                return null;
            }

            $sharedStrings = $this->readSharedStrings($zip, $path, $entries['xl/sharedstrings.xml'] ?? null);
            if ($sharedStrings === null) {
                return null;
            }

            $streamed = $this->openXmlEntry($zip, $path, $target['entry'], function (XMLReader $reader) use (&$sharedStrings, $maxRow, $onRow): void {
                $this->parseWorksheet($reader, $sharedStrings, $maxRow, $onRow);
            });

            return $streamed ? $target['name'] : null;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, string> lowercase zip path => exact zip path
     */
    private function indexEntries(ZipArchive $zip): array
    {
        $map = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name)) {
                $map[strtolower($name)] = $name;
            }
        }

        return $map;
    }

    /**
     * @param array<string, string> $entries
     * @return array{name: string, entry: string}|null
     */
    private function resolveSheetEntry(ZipArchive $zip, array $entries, ?string $preferredName): ?array
    {
        $wbEntry = $entries['xl/workbook.xml'] ?? null;
        $relsEntry = $entries['xl/_rels/workbook.xml.rels'] ?? null;
        if ($wbEntry === null || $relsEntry === null) {
            return null;
        }

        $wbXml = $zip->getFromName($wbEntry);
        $relsXml = $zip->getFromName($relsEntry);
        if (!is_string($wbXml) || !is_string($relsXml)) {
            return null;
        }

        $wb = @simplexml_load_string($wbXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        $rels = @simplexml_load_string($relsXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if (!$wb instanceof SimpleXMLElement || !$rels instanceof SimpleXMLElement) {
            return null;
        }

        $activeTab = isset($wb->bookViews->workbookView[0]['activeTab'])
            ? max(0, (int) $wb->bookViews->workbookView[0]['activeTab'])
            : 0;

        $sheets = [];
        if (isset($wb->sheets->sheet)) {
            foreach ($wb->sheets->sheet as $sheet) {
                $attrs = $sheet->attributes();
                $relAttrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid = (string) ($relAttrs['id'] ?? $attrs['id'] ?? '');
                $sheets[] = [
                    'name' => (string) ($attrs['name'] ?? ''),
                    'rid' => $rid,
                ];
            }
        }
        if ($sheets === []) {
            return null;
        }

        $selected = null;
        if ($preferredName !== null) {
            foreach ($sheets as $candidate) {
                if ($candidate['name'] === $preferredName) {
                    $selected = $candidate;
                    break;
                }
            }
        }
        $selected ??= $sheets[$activeTab] ?? $sheets[0];

        $target = null;
        foreach ($rels->Relationship as $rel) {
            $attrs = $rel->attributes();
            if ((string) ($attrs['Id'] ?? '') === $selected['rid']) {
                $target = (string) ($attrs['Target'] ?? '');
                break;
            }
        }
        if ($target === null || $target === '') {
            return null;
        }

        $normalized = $this->normalizeRelationshipTarget($target);
        $sheetEntry = $entries[strtolower($normalized)] ?? null;

        return $sheetEntry !== null ? ['name' => $selected['name'], 'entry' => $sheetEntry] : null;
    }

    private function normalizeRelationshipTarget(string $target): string
    {
        $path = str_replace('\\', '/', trim($target));
        if (str_starts_with($path, '/')) {
            $path = ltrim($path, '/');
        } elseif (!str_starts_with(strtolower($path), 'xl/')) {
            $path = 'xl/' . $path;
        }

        $segments = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $part;
        }

        return implode('/', $segments);
    }

    /**
     * @return array<int, string>|null null indicates read failure (triggers fallback)
     */
    private function readSharedStrings(ZipArchive $zip, string $zipPath, ?string $entry): ?array
    {
        if ($entry === null) {
            return [];
        }

        $strings = [];
        $ok = $this->openXmlEntry($zip, $zipPath, $entry, function (XMLReader $reader) use (&$strings): void {
            $inSi = false;
            $inT = false;
            $inRPh = false;
            $buffer = '';

            while ($reader->read()) {
                $type = $reader->nodeType;
                if ($type === XMLReader::ELEMENT) {
                    $name = $reader->localName;
                    if ($name === 'si') {
                        $inSi = true;
                        $buffer = '';
                    } elseif ($inSi && $name === 'rPh') {
                        $inRPh = true;
                    } elseif ($inSi && !$inRPh && $name === 't' && !$reader->isEmptyElement) {
                        $inT = true;
                    }
                } elseif ($type === XMLReader::TEXT || $type === XMLReader::CDATA || $type === XMLReader::SIGNIFICANT_WHITESPACE) {
                    if ($inT) {
                        $buffer .= $reader->value;
                    }
                } elseif ($type === XMLReader::END_ELEMENT) {
                    $name = $reader->localName;
                    if ($name === 't') {
                        $inT = false;
                    } elseif ($name === 'rPh') {
                        $inRPh = false;
                    } elseif ($name === 'si') {
                        $strings[] = $buffer;
                        $inSi = false;
                    }
                }
            }
        });

        return $ok ? $strings : null;
    }

    /**
     * @param array<int, string> $sharedStrings
     * @param callable(int, array<int, string>, int): bool $onRow
     */
    private function parseWorksheet(XMLReader $reader, array &$sharedStrings, ?int $maxRow, callable $onRow): void
    {
        $inSheetData = false;
        $inRow = false;
        $currentRow = 0;
        $prevCol = -1;
        $cells = [];
        $maxCol = -1;

        $inCell = false;
        $cellType = '';
        $cellCol = 0;
        $vText = '';
        $isText = '';
        $fText = '';
        $hasFormula = false;
        $inV = false;
        $inIs = false;
        $inIsT = false;
        $inIsRPh = false;
        $inF = false;

        while ($reader->read()) {
            $type = $reader->nodeType;
            if ($type === XMLReader::ELEMENT) {
                $name = $reader->localName;
                if (!$inSheetData) {
                    if ($name === 'sheetData') {
                        if ($reader->isEmptyElement) {
                            return;
                        }
                        $inSheetData = true;
                    }
                    continue;
                }

                if ($name === 'row') {
                    $rAttr = $reader->getAttribute('r');
                    $currentRow = ($rAttr !== null && $rAttr !== '') ? (int) $rAttr : ($currentRow + 1);
                    if ($maxRow !== null && $currentRow > $maxRow) {
                        return;
                    }
                    if ($reader->isEmptyElement) {
                        continue;
                    }
                    $inRow = true;
                    $prevCol = -1;
                    $cells = [];
                    $maxCol = -1;
                } elseif ($inRow && $name === 'c') {
                    $ref = $reader->getAttribute('r');
                    $cellType = (string) ($reader->getAttribute('t') ?? '');
                    $cellCol = ($ref !== null && $ref !== '')
                        ? $this->columnIndexFromReference($ref)
                        : ($prevCol + 1);
                    $prevCol = $cellCol;
                    if ($reader->isEmptyElement) {
                        continue;
                    }
                    $inCell = true;
                    $vText = '';
                    $isText = '';
                    $fText = '';
                    $hasFormula = false;
                    $inV = false;
                    $inIs = false;
                    $inIsT = false;
                    $inIsRPh = false;
                    $inF = false;
                } elseif ($inCell) {
                    if ($name === 'v' && !$reader->isEmptyElement) {
                        $inV = true;
                    } elseif ($name === 'is' && !$reader->isEmptyElement) {
                        $inIs = true;
                    } elseif ($inIs && $name === 'rPh' && !$reader->isEmptyElement) {
                        $inIsRPh = true;
                    } elseif ($inIs && !$inIsRPh && $name === 't' && !$reader->isEmptyElement) {
                        $inIsT = true;
                    } elseif ($name === 'f') {
                        $hasFormula = true;
                        if (!$reader->isEmptyElement) {
                            $inF = true;
                        }
                    }
                }
            } elseif ($type === XMLReader::TEXT || $type === XMLReader::CDATA || $type === XMLReader::SIGNIFICANT_WHITESPACE) {
                if ($inV) {
                    $vText .= $reader->value;
                } elseif ($inIsT) {
                    $isText .= $reader->value;
                } elseif ($inF) {
                    $fText .= $reader->value;
                }
            } elseif ($type === XMLReader::END_ELEMENT) {
                $name = $reader->localName;
                if ($inCell) {
                    if ($name === 'v') {
                        $inV = false;
                    } elseif ($name === 't') {
                        $inIsT = false;
                    } elseif ($name === 'rPh') {
                        $inIsRPh = false;
                    } elseif ($name === 'is') {
                        $inIs = false;
                    } elseif ($name === 'f') {
                        $inF = false;
                    } elseif ($name === 'c') {
                        $inCell = false;
                        $value = $this->resolveCellValue($cellType, $vText, $isText, $fText, $hasFormula, $sharedStrings);
                        if ($value !== '') {
                            $cells[$cellCol] = $value;
                            if ($cellCol > $maxCol) {
                                $maxCol = $cellCol;
                            }
                        }
                    }
                } elseif ($inRow && $name === 'row') {
                    $inRow = false;
                    if ($cells !== [] && $onRow($currentRow, $cells, $maxCol) === false) {
                        return;
                    }
                } elseif ($inSheetData && $name === 'sheetData') {
                    return;
                }
            }
        }
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function resolveCellValue(
        string $cellType,
        string $vText,
        string $isText,
        string $fText,
        bool $hasFormula,
        array &$sharedStrings
    ): string {
        if ($hasFormula && $fText !== '') {
            return '=' . $fText;
        }

        return match ($cellType) {
            's' => $vText !== '' ? ($sharedStrings[(int) $vText] ?? '') : '',
            'inlineStr' => $isText,
            'str', 'e', 'd' => $vText,
            'b' => ($vText === '1' || strcasecmp($vText, 'true') === 0) ? '1' : '',
            default => $this->normalizeNumericString($vText),
        };
    }

    private function normalizeNumericString(string $raw): string
    {
        if ($raw === '' || !is_numeric($raw)) {
            return $raw;
        }

        $number = (str_contains($raw, '.') || str_contains($raw, 'e') || str_contains($raw, 'E'))
            ? (float) $raw
            : ($raw + 0);

        return (string) $number;
    }

    private function columnIndexFromReference(string $reference): int
    {
        $column = 0;
        $length = strlen($reference);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($reference[$i]);
            if ($ord >= 65 && $ord <= 90) {
                $column = $column * 26 + ($ord - 64);
            } elseif ($ord >= 97 && $ord <= 122) {
                $column = $column * 26 + ($ord - 96);
            } else {
                break;
            }
        }

        return max(0, $column - 1);
    }

    /**
     * @param array<int, string> $cells
     * @return array<int, string>
     */
    private function densify(array $cells, int $maxCol): array
    {
        if ($maxCol < 0) {
            return [];
        }
        $dense = array_fill(0, $maxCol + 1, '');
        foreach ($cells as $col => $value) {
            $dense[$col] = $value;
        }

        return $dense;
    }

    private function openXmlEntry(ZipArchive $zip, string $zipPath, string $entry, callable $callback): bool
    {
        $reader = new XMLReader;
        $tempFile = null;

        try {
            $opened = false;
            $real = realpath($zipPath);
            if (is_string($real) && !str_contains($real, '#')) {
                $uri = 'zip://' . str_replace('\\', '/', $real) . '#' . $entry;
                $opened = @$reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT);
            }

            if (!$opened) {
                $stream = $zip->getStream($entry);
                if (!is_resource($stream)) {
                    return false;
                }
                $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_xml_');
                if ($tempFile === false) {
                    fclose($stream);
                    return false;
                }
                $out = fopen($tempFile, 'wb');
                if ($out === false) {
                    fclose($stream);
                    return false;
                }
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);

                $opened = @$reader->open($tempFile, null, LIBXML_NONET | LIBXML_COMPACT);
                if (!$opened) {
                    return false;
                }
            }

            $callback($reader);
            $reader->close();

            return true;
        } finally {
            if ($tempFile !== null && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
}
