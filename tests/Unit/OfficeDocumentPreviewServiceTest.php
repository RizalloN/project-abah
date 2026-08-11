<?php

namespace Tests\Unit;

use App\Services\DriveAsix\OfficeDocumentPreviewService;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class OfficeDocumentPreviewServiceTest extends TestCase
{
    public function test_it_extracts_word_paragraphs_headings_and_simple_tables(): void
    {
        $path = $this->makeOfficeArchive([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Title"/></w:pPr>
      <w:r><w:t>Ringkasan Area 6</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>Posisi </w:t></w:r><w:r><w:instrText>FIELD_CODE</w:instrText><w:t>terkini</w:t></w:r></w:p>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Cabang</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Nominal</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Madiun</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>125.000</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML,
        ]);

        try {
            $preview = (new OfficeDocumentPreviewService)->preview($path, 'ringkasan.docx');

            $this->assertSame('docx', $preview['format']);
            $this->assertSame('document', $preview['kind']);
            $this->assertSame('Ringkasan Area 6', $preview['title']);
            $this->assertSame('title', $preview['blocks'][0]['style']);
            $this->assertSame('Posisi terkini', $preview['blocks'][1]['text']);
            $this->assertSame(
                [['Cabang', 'Nominal'], ['Madiun', '125.000']],
                $preview['blocks'][2]['rows']
            );
            $this->assertSame(2, $preview['meta']['paragraph_count']);
            $this->assertSame(1, $preview['meta']['table_count']);
            $this->assertSame([], $preview['warnings']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_extracts_presentation_text_in_the_relationship_defined_slide_order(): void
    {
        $slideTemplate = static fn (string $title, string $body): string => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld>
    <p:spTree>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="1" name="Title"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$title}</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Body"/><p:cNvSpPr/><p:nvPr><p:ph type="body"/></p:nvPr></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$body}</a:t></a:r></a:p></p:txBody>
      </p:sp>
    </p:spTree>
  </p:cSld>
</p:sld>
XML;

        $path = $this->makeOfficeArchive([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'ppt/presentation.xml' => <<<'XML'
<?xml version="1.0"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="257" r:id="rId2"/>
    <p:sldId id="256" r:id="rId1"/>
    <p:sldId id="258" r:id="rId10"/>
  </p:sldIdLst>
</p:presentation>
XML,
            'ppt/_rels/presentation.xml.rels' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Target="slides/slide1.xml"/>
  <Relationship Id="rId2" Target="slides/slide2.xml"/>
  <Relationship Id="rId10" Target="slides/slide10.xml"/>
</Relationships>
XML,
            'ppt/slides/slide10.xml' => $slideTemplate('Penutup', 'Selesai'),
            'ppt/slides/slide2.xml' => $slideTemplate('Analisis', 'Kinerja cabang'),
            'ppt/slides/slide1.xml' => $slideTemplate('Pembuka', 'Area 6'),
        ]);

        try {
            $preview = (new OfficeDocumentPreviewService)->preview($path, 'paparan.pptx');

            $this->assertSame('pptx', $preview['format']);
            $this->assertSame('presentation', $preview['kind']);
            $this->assertSame('Analisis', $preview['title']);
            $this->assertSame([1, 2, 3], array_column($preview['slides'], 'number'));
            $this->assertSame(['Analisis', 'Pembuka', 'Penutup'], array_column($preview['slides'], 'title'));
            $this->assertSame('Kinerja cabang', $preview['slides'][0]['texts'][1]['text']);
            $this->assertSame(3, $preview['meta']['slide_count']);
            $this->assertSame(6, $preview['meta']['text_block_count']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_xml_with_document_type_declarations(): void
    {
        $path = $this->makeOfficeArchive([
            'word/document.xml' => <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE document [<!ENTITY unsafe SYSTEM "file:///etc/passwd">]>
<document><body><p>&unsafe;</p></body></document>
XML,
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('deklarasi XML yang tidak diizinkan');

            (new OfficeDocumentPreviewService)->preview($path, 'unsafe.docx');
        } finally {
            @unlink($path);
        }
    }

    public function test_support_check_is_limited_to_modern_local_office_packages(): void
    {
        $service = new OfficeDocumentPreviewService;

        $this->assertTrue($service->supports('laporan.DOCX'));
        $this->assertTrue($service->supports('paparan.pptx'));
        $this->assertFalse($service->supports('legacy.doc'));
        $this->assertFalse($service->supports('lembar.xlsx'));
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function makeOfficeArchive(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'drive-asix-preview-');
        $this->assertIsString($path);

        $zip = new ZipArchive;
        $result = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->assertTrue($result);

        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }

        $this->assertTrue($zip->close());

        return $path;
    }
}
