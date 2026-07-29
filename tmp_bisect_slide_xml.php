<?php

$root = __DIR__;
$template = $root . '/public/BRI_Presentation Template.pptx';
$generated = $root . '/storage/framework/testing/presentation-browser-audit/final-structured/performance-review-area6-structured.pptx';
$outputDir = $root . '/storage/framework/testing/presentation-browser-audit/final-structured';

$source = new ZipArchive();
$source->open($generated);
$slideXml = (string) $source->getFromName('ppt/slides/slide1.xml');
$slideRels = (string) $source->getFromName('ppt/slides/_rels/slide1.xml.rels');
$bri = (string) $source->getFromName('ppt/media/presentation-bri.png');
$danantara = (string) $source->getFromName('ppt/media/presentation-danantara.png');
$source->close();

$base = new DOMDocument();
$base->preserveWhiteSpace = false;
$base->loadXML($slideXml);
$xpath = new DOMXPath($base);
$xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
$bodyCount = max(0, ($xpath->query('/p:sld/p:cSld/p:spTree/*')?->length ?? 2) - 2);
$counts = array_values(array_unique(array_filter(
    [0, 1, 2, 3, 4, 5, 6, 8, 12, 16, 20, 24, 32, $bodyCount],
    fn (int $count): bool => $count <= $bodyCount
)));

foreach ($counts as $count) {
    $document = new DOMDocument();
    $document->preserveWhiteSpace = false;
    $document->loadXML($slideXml);
    $documentXpath = new DOMXPath($document);
    $documentXpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
    $children = iterator_to_array($documentXpath->query('/p:sld/p:cSld/p:spTree/*') ?: []);
    foreach (array_slice($children, 2 + $count) as $child) {
        $child->parentNode?->removeChild($child);
    }

    $path = $outputDir . "/native-bisect-{$count}.pptx";
    copy($template, $path);
    $zip = new ZipArchive();
    $zip->open($path);
    $replacements = [
        'ppt/slides/slide1.xml' => (string) $document->saveXML(),
        'ppt/slides/_rels/slide1.xml.rels' => $slideRels,
        'ppt/media/presentation-bri.png' => $bri,
        'ppt/media/presentation-danantara.png' => $danantara,
    ];
    foreach ($replacements as $part => $content) {
        if ($zip->locateName($part) !== false) {
            $zip->deleteName($part);
        }
        $zip->addFromString($part, $content);
    }
    $zip->close();
}

echo json_encode(['body_count' => $bodyCount, 'counts' => $counts], JSON_PRETTY_PRINT) . PHP_EOL;
