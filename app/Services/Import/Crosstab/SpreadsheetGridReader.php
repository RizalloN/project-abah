<?php

namespace App\Services\Import\Crosstab;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reads the first worksheet of an Excel file as raw source text, row by row.
 *
 * XLSX is streamed (XMLReader) so large SSA/CRAS exports never load into memory;
 * cell text is the stored `<v>` value, exactly as the source wrote it. XLS has no
 * streaming format and goes through PhpSpreadsheet. Both expose merge ranges,
 * because merge boundaries are source data a crosstab export relies on.
 */
class SpreadsheetGridReader
{
    private ?array $merges = null;
    private ?array $bookRows = null;
    private array $bookMergeRanges = [];
    private ?array $sharedStrings = null;
    private ?string $worksheetEntry = null;
    private ?string $format = null;
    private bool $date1904 = false;

    public function __construct(private readonly string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File Excel tidak ditemukan atau tidak dapat dibaca.');
        }
    }

    public function isStreaming(): bool
    {
        return $this->format() === 'xlsx';
    }

    public function usesDate1904(): bool
    {
        $this->merges();

        return $this->date1904;
    }

    /**
     * @return list<array{0:int,1:int,2:int,3:int}> [firstColumn, firstRow, lastColumn, lastRow], zero-based columns, one-based rows
     */
    public function merges(): array
    {
        if ($this->merges !== null) {
            return $this->merges;
        }

        $ranges = $this->format() === 'xlsx' ? $this->readXlsxMergeRanges() : $this->readBookMergeRanges();
        $this->merges = [];
        foreach ($ranges as $range) {
            [[$firstColumn, $firstRow], [$lastColumn, $lastRow]] = Coordinate::rangeBoundaries($range);
            if ($firstColumn === $lastColumn && $firstRow === $lastRow) {
                continue;
            }
            $this->merges[] = [$firstColumn - 1, (int) $firstRow, $lastColumn - 1, (int) $lastRow];
        }

        return $this->merges;
    }

    /**
     * Yields [rowNumber, values, formulas]. Values are zero-indexed raw strings;
     * formulas holds only cells whose formula has no stored result.
     *
     * @return \Generator<int, array{0:int,1:array<int,string>,2:array<int,string>}>
     */
    public function rows(): \Generator
    {
        if ($this->format() === 'xlsx') {
            yield from $this->streamXlsxRows();

            return;
        }

        yield from $this->bookRows();
    }

    /**
     * False for text files saved with an Excel extension; those keep their own import path.
     */
    public function isWorkbook(): bool
    {
        return $this->format() !== 'text';
    }

    private function format(): string
    {
        if ($this->format !== null) {
            return $this->format;
        }

        $handle = @fopen($this->path, 'rb');
        $signature = $handle ? (string) fread($handle, 8) : '';
        if ($handle) {
            fclose($handle);
        }

        if (str_starts_with($signature, "PK\x03\x04") && class_exists(\ZipArchive::class) && class_exists(\XMLReader::class)) {
            return $this->format = 'xlsx';
        }
        if (str_starts_with($signature, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return $this->format = 'book';
        }

        // HTML tables and SpreadsheetML 2003 are still workbooks PhpSpreadsheet can read.
        try {
            $type = IOFactory::identify($this->path);
        } catch (\Throwable) {
            return $this->format = 'text';
        }

        return $this->format = in_array($type, ['Xlsx', 'Xls', 'Ods', 'Xml', 'Html', 'Gnumeric'], true) ? 'book' : 'text';
    }

    private function readXlsxMergeRanges(): array
    {
        $entry = $this->resolveWorksheetEntry();
        $reader = $this->openXml($entry);
        $ranges = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                if ($reader->localName === 'sheetData') {
                    // Skip the cell payload; merge ranges are listed after it.
                    if (!$reader->isEmptyElement) {
                        $reader->next();
                    }
                    continue;
                }
                if ($reader->localName === 'mergeCell') {
                    $reference = strtoupper((string) $reader->getAttribute('ref'));
                    if (preg_match('/^\$?[A-Z]+\$?\d+:\$?[A-Z]+\$?\d+$/', $reference)) {
                        $ranges[] = str_replace('$', '', $reference);
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return $ranges;
    }

    private function streamXlsxRows(): \Generator
    {
        $sharedStrings = $this->sharedStrings();
        $reader = $this->openXml($this->resolveWorksheetEntry());
        $lastRowNumber = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $rowNumber = (int) $reader->getAttribute('r');
                if ($rowNumber <= 0) {
                    $rowNumber = $lastRowNumber + 1;
                }
                $lastRowNumber = $rowNumber;
                if ($reader->isEmptyElement) {
                    continue;
                }

                [$values, $formulas] = $this->readXlsxRow($reader, $sharedStrings);
                if ($values !== [] || $formulas !== []) {
                    yield [$rowNumber, $values, $formulas];
                }
            }
        } finally {
            $reader->close();
        }
    }

    private function readXlsxRow(\XMLReader $reader, array $sharedStrings): array
    {
        $rowDepth = $reader->depth;
        $values = [];
        $formulas = [];
        $nextColumn = 0;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $rowDepth && $reader->localName === 'row') {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            $reference = (string) $reader->getAttribute('r');
            $column = $reference !== '' ? $this->columnIndex($reference) : $nextColumn;
            $nextColumn = $column + 1;
            $type = (string) $reader->getAttribute('t');
            if ($reader->isEmptyElement) {
                continue;
            }

            [$value, $formula] = $this->readXlsxCell($reader, $type, $sharedStrings);
            if ($value !== null) {
                $values[$column] = $value;
            } elseif ($formula !== null) {
                $formulas[$column] = $formula;
            }
        }

        return [$values, $formulas];
    }

    /**
     * @return array{0:?string,1:?string} [stored value, formula text when the value is missing]
     */
    private function readXlsxCell(\XMLReader $reader, string $type, array $sharedStrings): array
    {
        $cellDepth = $reader->depth;
        $value = null;
        $formula = null;
        $inline = '';

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $cellDepth && $reader->localName === 'c') {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->localName === 'f') {
                $formula = $reader->isEmptyElement ? '' : $reader->readString();
            } elseif ($reader->localName === 'v') {
                $value = $reader->readString();
            } elseif ($type === 'inlineStr' && $reader->localName === 't') {
                $inline .= $reader->readString();
                $value = $inline;
            }
        }

        if ($type === 's' && $value !== null) {
            if (!ctype_digit($value) || !array_key_exists((int) $value, $sharedStrings)) {
                throw new RuntimeException("Index shared string XLSX tidak valid: {$value}.");
            }
            $value = (string) $sharedStrings[(int) $value];
        }

        return [$value, $value === null ? $formula : null];
    }

    private function sharedStrings(): array
    {
        if ($this->sharedStrings !== null) {
            return $this->sharedStrings;
        }

        $this->sharedStrings = [];
        $zip = $this->openZip();
        $exists = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();
        if (!$exists) {
            return $this->sharedStrings;
        }

        $reader = $this->openXml('xl/sharedStrings.xml');
        $text = '';
        $inside = false;
        $phonetic = false;
        try {
            while ($reader->read()) {
                $isElement = $reader->nodeType === \XMLReader::ELEMENT;
                $isEnd = $reader->nodeType === \XMLReader::END_ELEMENT;
                if ($isElement && $reader->localName === 'si') {
                    $inside = !$reader->isEmptyElement;
                    $text = '';
                    if (!$inside) {
                        $this->sharedStrings[] = '';
                    }
                } elseif ($inside && $isElement && $reader->localName === 'rPh') {
                    // Phonetic guides are reading aids, not cell text.
                    $phonetic = !$reader->isEmptyElement;
                } elseif ($inside && $isEnd && $reader->localName === 'rPh') {
                    $phonetic = false;
                } elseif ($inside && !$phonetic && $isElement && $reader->localName === 't') {
                    $text .= $reader->readString();
                } elseif ($inside && $isEnd && $reader->localName === 'si') {
                    $this->sharedStrings[] = $text;
                    $inside = false;
                }
            }
        } finally {
            $reader->close();
        }

        return $this->sharedStrings;
    }

    private function resolveWorksheetEntry(): string
    {
        if ($this->worksheetEntry !== null) {
            return $this->worksheetEntry;
        }

        $zip = $this->openZip();
        try {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            $entry = null;
            if (is_string($workbookXml) && is_string($relationsXml)) {
                $workbook = new \DOMDocument;
                $relations = new \DOMDocument;
                if (@$workbook->loadXML($workbookXml, LIBXML_NONET | LIBXML_COMPACT)
                    && @$relations->loadXML($relationsXml, LIBXML_NONET | LIBXML_COMPACT)) {
                    $workbookXpath = new \DOMXPath($workbook);
                    $properties = $workbookXpath->query('//*[local-name()="workbookPr"]')?->item(0);
                    if ($properties instanceof \DOMElement) {
                        $this->date1904 = in_array(strtolower($properties->getAttribute('date1904')), ['1', 'true'], true);
                    }

                    $targets = [];
                    foreach ((new \DOMXPath($relations))->query('//*[local-name()="Relationship"]') ?: [] as $relationship) {
                        if ($relationship instanceof \DOMElement) {
                            $targets[$relationship->getAttribute('Id')] = $relationship->getAttribute('Target');
                        }
                    }
                    foreach ($workbookXpath->query('//*[local-name()="sheet"]') ?: [] as $sheet) {
                        if (!$sheet instanceof \DOMElement) {
                            continue;
                        }
                        $target = $targets[$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')] ?? '';
                        $candidate = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
                        if ($target !== '' && $zip->locateName($candidate) !== false) {
                            $entry = $candidate;
                            break;
                        }
                    }
                }
            }
            $entry ??= $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        } finally {
            $zip->close();
        }

        if ($entry === null) {
            throw new RuntimeException('Worksheet XLSX tidak ditemukan.');
        }

        return $this->worksheetEntry = $entry;
    }

    private function openZip(): \ZipArchive
    {
        $zip = new \ZipArchive;
        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('Workbook XLSX tidak dapat dibuka.');
        }

        return $zip;
    }

    private function openXml(string $entry): \XMLReader
    {
        $reader = new \XMLReader;
        $uri = 'zip://'.str_replace('\\', '/', $this->path).'#'.$entry;
        if (!$reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('Isi workbook XLSX tidak dapat dibaca.');
        }

        return $reader;
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)\d*$/i', $reference, $matches)) {
            throw new RuntimeException("Koordinat sel XLSX tidak valid: {$reference}.");
        }

        return Coordinate::columnIndexFromString(strtoupper($matches[1])) - 1;
    }

    private function readBookMergeRanges(): array
    {
        $this->loadBook();

        return $this->bookMergeRanges;
    }

    private function bookRows(): \Generator
    {
        $this->loadBook();

        yield from $this->bookRows;
    }

    /**
     * XLS has no streaming reader, so the sheet is read once and kept as raw rows.
     */
    private function loadBook(): void
    {
        if ($this->bookRows !== null) {
            return;
        }

        $reader = IOFactory::createReaderForFile($this->path);
        // Merge boundaries are source data: data-only mode drops this metadata.
        $reader->setReadDataOnly(false);
        $book = $reader->load($this->path);
        try {
            $sheet = $book->getSheet(0);
            $this->bookMergeRanges = array_values($sheet->getMergeCells());
            $rows = [];
            $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            $lastRow = $sheet->getHighestDataRow();
            for ($row = 1; $row <= $lastRow; $row++) {
                $values = [];
                $formulas = [];
                for ($column = 1; $column <= $lastColumn; $column++) {
                    if (!$sheet->cellExists([$column, $row])) {
                        continue;
                    }
                    $cell = $sheet->getCell([$column, $row]);
                    $value = $cell->getValue();
                    if ($cell->isFormula()) {
                        $stored = $cell->getOldCalculatedValue();
                        if ($stored === null) {
                            $formulas[$column - 1] = ltrim((string) $value, '=');
                            continue;
                        }
                        $value = $stored;
                    }
                    if ($value !== null) {
                        $values[$column - 1] = self::scalarText($value);
                    }
                }
                if ($values !== [] || $formulas !== []) {
                    $rows[] = [$row, $values, $formulas];
                }
            }
            $this->bookRows = $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private static function scalarText(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => sprintf('%.0f', $value),
            is_float($value) => var_export($value, true),
            $value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText => $value->getPlainText(),
            default => (string) $value,
        };
    }
}
