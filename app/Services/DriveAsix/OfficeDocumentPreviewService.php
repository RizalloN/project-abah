<?php

namespace App\Services\DriveAsix;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class OfficeDocumentPreviewService
{
    private const SUPPORTED_FORMATS = ['docx', 'pptx'];

    private const MAX_ARCHIVE_BYTES = 67_108_864; // 64 MiB

    private const MAX_UNCOMPRESSED_BYTES = 268_435_456; // 256 MiB

    private const MAX_XML_ENTRY_BYTES = 25_165_824; // 24 MiB

    private const MAX_ARCHIVE_ENTRIES = 4_096;

    private const MAX_PREVIEW_CHARACTERS = 500_000;

    private const MAX_DOCUMENT_BLOCKS = 2_500;

    private const MAX_PRESENTATION_SLIDES = 300;

    private const MAX_TABLE_ROWS = 500;

    private const MAX_TABLE_COLUMNS = 60;

    public function supports(string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::SUPPORTED_FORMATS, true);
    }

    /**
     * Read a local OOXML document without sending its contents to an external service.
     *
     * @return array{
     *     format: 'docx'|'pptx',
     *     kind: 'document'|'presentation',
     *     name: string,
     *     title: string,
     *     blocks?: array<int, array<string, mixed>>,
     *     slides?: array<int, array<string, mixed>>,
     *     meta: array<string, int|string>,
     *     warnings: array<int, string>
     * }
     */
    public function preview(string $path, ?string $originalName = null): array
    {
        $this->assertReadableArchive($path);

        $zip = new ZipArchive;
        $openResult = $zip->open($path, ZipArchive::RDONLY);

        if ($openResult !== true) {
            throw new RuntimeException('File Office tidak dapat dibuka atau arsipnya rusak.');
        }

        try {
            $this->assertSafeArchive($zip);
            $format = $this->detectFormat($zip);
            $name = $originalName ?: basename($path);

            return $format === 'docx'
                ? $this->previewWordDocument($zip, $name)
                : $this->previewPresentation($zip, $name);
        } finally {
            $zip->close();
        }
    }

    private function assertReadableArchive(string $path): void
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('File Office lokal tidak ditemukan atau tidak dapat dibaca.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            throw new InvalidArgumentException('File Office kosong atau ukurannya tidak dapat dibaca.');
        }

        if ($size > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('File Office melebihi batas preview lokal 64 MB.');
        }
    }

    private function assertSafeArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw new RuntimeException('Struktur file Office terlalu besar atau tidak valid untuk dipreview.');
        }

        $totalUncompressed = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (! is_array($stat) || ! isset($stat['name'])) {
                throw new RuntimeException('Struktur arsip Office tidak dapat diverifikasi.');
            }

            $entryName = str_replace('\\', '/', (string) $stat['name']);
            $segments = explode('/', $entryName);

            if (
                $entryName === ''
                || str_contains($entryName, "\0")
                || str_starts_with($entryName, '/')
                || preg_match('/^[a-zA-Z]:\//', $entryName) === 1
                || in_array('..', $segments, true)
            ) {
                throw new RuntimeException('File Office memuat path arsip yang tidak aman.');
            }

            $entrySize = max(0, (int) ($stat['size'] ?? 0));
            $totalUncompressed += $entrySize;

            if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('Ukuran hasil ekstraksi file Office melampaui batas aman.');
            }

            if (str_ends_with(strtolower($entryName), '.xml') && $entrySize > self::MAX_XML_ENTRY_BYTES) {
                throw new RuntimeException('Bagian XML file Office terlalu besar untuk dipreview.');
            }
        }
    }

    /**
     * Detect from the package structure so a renamed file cannot select the wrong parser.
     *
     * @return 'docx'|'pptx'
     */
    private function detectFormat(ZipArchive $zip): string
    {
        if ($zip->locateName('word/document.xml') !== false) {
            return 'docx';
        }

        if (
            $zip->locateName('ppt/presentation.xml') !== false
            || $this->presentationSlideNames($zip) !== []
        ) {
            return 'pptx';
        }

        throw new InvalidArgumentException('Format belum didukung. Gunakan dokumen DOCX atau presentasi PPTX.');
    }

    /**
     * @return array<string, mixed>
     */
    private function previewWordDocument(ZipArchive $zip, string $name): array
    {
        $document = $this->loadXmlEntry($zip, 'word/document.xml');
        $xpath = new DOMXPath($document);
        $body = $xpath->query('/*[local-name()="document"]/*[local-name()="body"]')->item(0);

        if (! $body instanceof DOMElement) {
            throw new RuntimeException('Isi dokumen Word tidak memiliki struktur body yang valid.');
        }

        $blocks = [];
        $warnings = [];
        $characters = 0;
        $paragraphCount = 0;
        $tableCount = 0;
        $wordCount = 0;
        $title = '';
        $truncated = false;

        foreach ($body->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (count($blocks) >= self::MAX_DOCUMENT_BLOCKS || $characters >= self::MAX_PREVIEW_CHARACTERS) {
                $truncated = true;
                break;
            }

            if ($child->localName === 'p') {
                $text = $this->normaliseText($this->extractNodeText($child));
                if ($text === '') {
                    continue;
                }

                $style = $this->wordParagraphStyle($xpath, $child);
                $text = $this->fitTextToBudget($text, self::MAX_PREVIEW_CHARACTERS - $characters, $truncated);
                if ($text === '') {
                    break;
                }

                $blocks[] = [
                    'type' => 'paragraph',
                    'style' => $style,
                    'text' => $text,
                ];
                $paragraphCount++;
                $characters += $this->textLength($text);
                $wordCount += $this->wordCount($text);

                if ($title === '' && in_array($style, ['title', 'heading-1'], true)) {
                    $title = $text;
                }

                continue;
            }

            if ($child->localName !== 'tbl') {
                continue;
            }

            [$rows, $tableTruncated] = $this->extractTable($xpath, $child);
            if ($rows === []) {
                continue;
            }

            $tableCharacters = $this->tableCharacterCount($rows);
            if ($characters + $tableCharacters > self::MAX_PREVIEW_CHARACTERS) {
                $rows = $this->fitTableToBudget($rows, self::MAX_PREVIEW_CHARACTERS - $characters);
                $tableCharacters = $this->tableCharacterCount($rows);
                $tableTruncated = true;
                $truncated = true;
            }

            if ($rows !== []) {
                $blocks[] = [
                    'type' => 'table',
                    'rows' => $rows,
                ];
                $tableCount++;
                $characters += $tableCharacters;
                $wordCount += $this->wordCount(implode(' ', array_merge(...$rows)));
            }

            $truncated = $truncated || $tableTruncated;
        }

        if ($truncated) {
            $warnings[] = 'Preview dipotong agar tetap cepat dan aman. Unduh file untuk melihat seluruh isi.';
        }

        if ($title === '') {
            $title = $this->firstDocumentText($blocks) ?: $this->nameWithoutExtension($name);
        }

        return [
            'format' => 'docx',
            'kind' => 'document',
            'name' => $name,
            'title' => $title,
            'blocks' => $blocks,
            'meta' => [
                'paragraph_count' => $paragraphCount,
                'table_count' => $tableCount,
                'word_count' => $wordCount,
                'characters' => $characters,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewPresentation(ZipArchive $zip, string $name): array
    {
        $slideNames = $this->presentationSlideNames($zip);
        $warnings = [];

        if (count($slideNames) > self::MAX_PRESENTATION_SLIDES) {
            $slideNames = array_slice($slideNames, 0, self::MAX_PRESENTATION_SLIDES);
            $warnings[] = 'Preview dibatasi sampai 300 slide. Unduh file untuk melihat seluruh isi.';
        }

        $slides = [];
        $characters = 0;
        $wordCount = 0;
        $tableCount = 0;
        $textBlockCount = 0;
        $truncated = false;

        foreach ($slideNames as $position => $slideName) {
            if ($characters >= self::MAX_PREVIEW_CHARACTERS) {
                $truncated = true;
                break;
            }

            $slideDocument = $this->loadXmlEntry($zip, $slideName);
            $xpath = new DOMXPath($slideDocument);
            $texts = [];
            $slideTitle = '';

            foreach ($xpath->query('//*[local-name()="sp"]') as $shape) {
                if (! $shape instanceof DOMElement) {
                    continue;
                }

                $role = $this->presentationShapeRole($xpath, $shape);
                $paragraphs = [];

                foreach ($xpath->query('.//*[local-name()="txBody"]/*[local-name()="p"]', $shape) as $paragraph) {
                    $text = $this->normaliseText($this->extractNodeText($paragraph));
                    if ($text !== '') {
                        $paragraphs[] = $text;
                    }
                }

                $text = implode("\n", $paragraphs);
                if ($text === '') {
                    continue;
                }

                $text = $this->fitTextToBudget($text, self::MAX_PREVIEW_CHARACTERS - $characters, $truncated);
                if ($text === '') {
                    break;
                }

                if ($slideTitle === '' && $role === 'title') {
                    $slideTitle = $text;
                }

                $texts[] = [
                    'role' => $role,
                    'text' => $text,
                ];
                $characters += $this->textLength($text);
                $wordCount += $this->wordCount($text);
                $textBlockCount++;
            }

            $tables = [];
            foreach ($xpath->query('//*[local-name()="tbl"]') as $table) {
                if (! $table instanceof DOMElement || $characters >= self::MAX_PREVIEW_CHARACTERS) {
                    $truncated = true;
                    break;
                }

                [$rows, $tableTruncated] = $this->extractTable($xpath, $table);
                $rows = $this->fitTableToBudget($rows, self::MAX_PREVIEW_CHARACTERS - $characters);
                if ($rows === []) {
                    continue;
                }

                $tableCharacters = $this->tableCharacterCount($rows);
                $tables[] = ['rows' => $rows];
                $characters += $tableCharacters;
                $wordCount += $this->wordCount(implode(' ', array_merge(...$rows)));
                $tableCount++;
                $truncated = $truncated || $tableTruncated;
            }

            $slideNumber = $position + 1;
            $slides[] = [
                'number' => $slideNumber,
                'title' => $slideTitle ?: 'Slide '.$slideNumber,
                'texts' => $texts,
                'tables' => $tables,
            ];

            if ($truncated) {
                break;
            }
        }

        if ($truncated && ! in_array('Preview dipotong agar tetap cepat dan aman. Unduh file untuk melihat seluruh isi.', $warnings, true)) {
            $warnings[] = 'Preview dipotong agar tetap cepat dan aman. Unduh file untuk melihat seluruh isi.';
        }

        $title = $this->firstPresentationTitle($slides) ?: $this->nameWithoutExtension($name);

        return [
            'format' => 'pptx',
            'kind' => 'presentation',
            'name' => $name,
            'title' => $title,
            'slides' => $slides,
            'meta' => [
                'slide_count' => count($slides),
                'text_block_count' => $textBlockCount,
                'table_count' => $tableCount,
                'word_count' => $wordCount,
                'characters' => $characters,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function presentationSlideNames(ZipArchive $zip): array
    {
        $slidesByNumber = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && preg_match('#^ppt/slides/slide(\d+)\.xml$#i', $name, $matches) === 1) {
                $slidesByNumber[(int) $matches[1]] = $name;
            }
        }

        ksort($slidesByNumber, SORT_NUMERIC);
        $fallbackSlides = array_values($slidesByNumber);

        if (
            $fallbackSlides === []
            || $zip->locateName('ppt/presentation.xml') === false
            || $zip->locateName('ppt/_rels/presentation.xml.rels') === false
        ) {
            return $fallbackSlides;
        }

        $relationships = $this->loadXmlEntry($zip, 'ppt/_rels/presentation.xml.rels');
        $relationshipXPath = new DOMXPath($relationships);
        $targetsByRelationship = [];

        foreach ($relationshipXPath->query('/*[local-name()="Relationships"]/*[local-name()="Relationship"]') as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            if (strtolower($relationship->getAttribute('TargetMode')) === 'external') {
                continue;
            }

            $id = $relationship->getAttribute('Id');
            $target = $this->normalisePresentationTarget($relationship->getAttribute('Target'));
            if ($id !== '' && $target !== null && isset($slidesByNumber[$this->slideFileNumber($target)])) {
                $targetsByRelationship[$id] = $target;
            }
        }

        if ($targetsByRelationship === []) {
            return $fallbackSlides;
        }

        $presentation = $this->loadXmlEntry($zip, 'ppt/presentation.xml');
        $presentationXPath = new DOMXPath($presentation);
        $orderedSlides = [];

        foreach ($presentationXPath->query('/*[local-name()="presentation"]/*[local-name()="sldIdLst"]/*[local-name()="sldId"]') as $slideId) {
            if (! $slideId instanceof DOMElement || ! $slideId->hasAttributes()) {
                continue;
            }

            foreach ($slideId->attributes as $attribute) {
                $relationshipId = (string) $attribute->nodeValue;
                if ($attribute->localName === 'id' && isset($targetsByRelationship[$relationshipId])) {
                    $orderedSlides[] = $targetsByRelationship[$relationshipId];
                    break;
                }
            }
        }

        foreach ($fallbackSlides as $fallbackSlide) {
            if (! in_array($fallbackSlide, $orderedSlides, true)) {
                $orderedSlides[] = $fallbackSlide;
            }
        }

        return $orderedSlides;
    }

    private function normalisePresentationTarget(string $target): ?string
    {
        $target = str_replace('\\', '/', trim($target));
        if ($target === '' || str_contains($target, "\0") || preg_match('#^[a-z][a-z0-9+.-]*:#i', $target) === 1) {
            return null;
        }

        $segments = str_starts_with($target, '/')
            ? explode('/', ltrim($target, '/'))
            : array_merge(['ppt'], explode('/', $target));
        $normalised = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($normalised === []) {
                    return null;
                }
                array_pop($normalised);

                continue;
            }

            $normalised[] = $segment;
        }

        $path = implode('/', $normalised);

        return preg_match('#^ppt/slides/slide\d+\.xml$#i', $path) === 1 ? $path : null;
    }

    private function slideFileNumber(string $name): int
    {
        return preg_match('#/slide(\d+)\.xml$#i', $name, $matches) === 1
            ? (int) $matches[1]
            : 0;
    }

    private function loadXmlEntry(ZipArchive $zip, string $entryName): DOMDocument
    {
        $stat = $zip->statName($entryName);
        if (! is_array($stat) || (int) ($stat['size'] ?? 0) > self::MAX_XML_ENTRY_BYTES) {
            throw new RuntimeException('Bagian dokumen yang diperlukan tidak tersedia atau terlalu besar.');
        }

        $xml = $zip->getFromName($entryName, self::MAX_XML_ENTRY_BYTES + 1);
        if (! is_string($xml) || $xml === '' || strlen($xml) > self::MAX_XML_ENTRY_BYTES) {
            throw new RuntimeException('Bagian XML dokumen tidak dapat dibaca dengan aman.');
        }

        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException('Dokumen memuat deklarasi XML yang tidak diizinkan.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS);
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new RuntimeException('Struktur XML dokumen rusak atau tidak valid.');
        }

        return $document;
    }

    private function wordParagraphStyle(DOMXPath $xpath, DOMElement $paragraph): string
    {
        $style = strtolower((string) $xpath->evaluate(
            'string(./*[local-name()="pPr"]/*[local-name()="pStyle"]/@*[local-name()="val"])',
            $paragraph
        ));
        $normalisedStyle = preg_replace('/[^a-z0-9]+/', '', $style) ?: '';

        if ($normalisedStyle === 'title') {
            return 'title';
        }

        if ($normalisedStyle === 'subtitle') {
            return 'subtitle';
        }

        if (preg_match('/(?:heading|judul)([1-6])/', $normalisedStyle, $matches) === 1) {
            return 'heading-'.$matches[1];
        }

        if ($xpath->query('./*[local-name()="pPr"]/*[local-name()="numPr"]', $paragraph)->length > 0) {
            return 'list';
        }

        return 'paragraph';
    }

    private function presentationShapeRole(DOMXPath $xpath, DOMElement $shape): string
    {
        $placeholder = strtolower((string) $xpath->evaluate(
            'string(./*[local-name()="nvSpPr"]/*[local-name()="nvPr"]/*[local-name()="ph"]/@*[local-name()="type"])',
            $shape
        ));

        return match ($placeholder) {
            'title', 'ctrtitle' => 'title',
            'subtitle' => 'subtitle',
            'dt', 'ftr', 'sldnum' => 'footer',
            default => 'body',
        };
    }

    private function extractNodeText(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $text .= match ($child->localName) {
                    't' => $child->textContent,
                    'tab' => "\t",
                    'br', 'cr' => "\n",
                    'noBreakHyphen' => '-',
                    default => $this->extractNodeText($child),
                };
            }
        }

        return $text;
    }

    /**
     * @return array{0: array<int, array<int, string>>, 1: bool}
     */
    private function extractTable(DOMXPath $xpath, DOMElement $table): array
    {
        $rows = [];
        $truncated = false;
        $rowNodes = $xpath->query('./*[local-name()="tr"]', $table);

        foreach ($rowNodes as $rowIndex => $row) {
            if ($rowIndex >= self::MAX_TABLE_ROWS) {
                $truncated = true;
                break;
            }

            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];
            foreach ($xpath->query('./*[local-name()="tc"]', $row) as $columnIndex => $cell) {
                if ($columnIndex >= self::MAX_TABLE_COLUMNS) {
                    $truncated = true;
                    break;
                }

                $paragraphs = [];
                foreach ($xpath->query('.//*[local-name()="p"]', $cell) as $paragraph) {
                    $text = $this->normaliseText($this->extractNodeText($paragraph));
                    if ($text !== '') {
                        $paragraphs[] = $text;
                    }
                }
                $cells[] = implode("\n", $paragraphs);
            }

            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return [$rows, $truncated];
    }

    private function normaliseText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function fitTextToBudget(string $text, int $remaining, bool &$truncated): string
    {
        if ($remaining <= 0) {
            $truncated = true;

            return '';
        }

        if ($this->textLength($text) <= $remaining) {
            return $text;
        }

        $truncated = true;

        return rtrim($this->textSlice($text, 0, max(0, $remaining - 1))).'…';
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array<int, string>>
     */
    private function fitTableToBudget(array $rows, int $remaining): array
    {
        if ($remaining <= 0) {
            return [];
        }

        $fitted = [];
        foreach ($rows as $row) {
            $fittedRow = [];
            foreach ($row as $cell) {
                if ($remaining <= 0) {
                    break 2;
                }

                $cellLength = $this->textLength($cell);
                if ($cellLength > $remaining) {
                    $cell = rtrim($this->textSlice($cell, 0, max(0, $remaining - 1))).'…';
                    $cellLength = $this->textLength($cell);
                }

                $fittedRow[] = $cell;
                $remaining -= $cellLength;
            }

            if ($fittedRow !== []) {
                $fitted[] = $fittedRow;
            }
        }

        return $fitted;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function tableCharacterCount(array $rows): int
    {
        $characters = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $characters += $this->textLength($cell);
            }
        }

        return $characters;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function firstDocumentText(array $blocks): string
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'paragraph' && ! empty($block['text'])) {
                return (string) $block['text'];
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $slides
     */
    private function firstPresentationTitle(array $slides): string
    {
        foreach ($slides as $slide) {
            foreach (($slide['texts'] ?? []) as $text) {
                if (($text['role'] ?? null) === 'title' && ! empty($text['text'])) {
                    return (string) $text['text'];
                }
            }
        }

        return '';
    }

    private function nameWithoutExtension(string $name): string
    {
        return pathinfo($name, PATHINFO_FILENAME) ?: 'Dokumen';
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/[\s\p{P}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? count($words) : 0;
    }

    private function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private function textSlice(string $text, int $offset, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($text, $offset, $length, 'UTF-8')
            : substr($text, $offset, $length);
    }
}
