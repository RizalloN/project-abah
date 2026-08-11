<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class DriveAsixDocumentPreviewViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(new User([
            'name' => 'Preview Tester',
            'pn' => '00000000',
            'role' => 'user',
        ]));
    }

    public function test_document_preview_renders_escaped_content_and_local_controls(): void
    {
        $html = view('drive.document-preview', [
            'file' => null,
            'backUrl' => '/drive',
            'downloadUrl' => '/drive/download/1',
            'preview' => [
                'format' => 'docx',
                'kind' => 'document',
                'name' => '</title><script>alert("title")</script>.docx',
                'title' => 'Dokumen Uji',
                'blocks' => [
                    ['type' => 'paragraph', 'style' => 'heading-2', 'text' => 'Judul Bagian'],
                    ['type' => 'paragraph', 'style' => 'paragraph', 'text' => '<script>alert("x")</script>'],
                ],
                'meta' => [
                    'paragraph_count' => 2,
                    'table_count' => 0,
                    'word_count' => 4,
                ],
                'warnings' => [],
            ],
        ])->render();

        $this->assertStringContainsString('Preview lokal DriveASIX', $html);
        $this->assertStringContainsString('<h2 class="doc-heading-2">Judul Bagian</h2>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringContainsString(
            '&lt;/title&gt;&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;.docx | DriveASIX',
            $html
        );
        $this->assertStringNotContainsString('<script>alert("title")</script>', $html);
        $this->assertStringContainsString('id="driveZoomFit"', $html);
        $this->assertStringContainsString('Diproses di server lokal', $html);
    }

    public function test_presentation_preview_renders_slide_navigation_and_tables(): void
    {
        $html = view('drive.document-preview', [
            'file' => null,
            'backUrl' => '/drive',
            'downloadUrl' => null,
            'preview' => [
                'format' => 'pptx',
                'kind' => 'presentation',
                'name' => 'paparan.pptx',
                'title' => 'Kinerja',
                'slides' => [[
                    'number' => 1,
                    'title' => 'Kinerja',
                    'texts' => [
                        ['role' => 'title', 'text' => 'Kinerja'],
                        ['role' => 'body', 'text' => 'Ringkasan cabang'],
                    ],
                    'tables' => [[
                        'rows' => [['Cabang', 'OS'], ['Madiun', '125']],
                    ]],
                ]],
                'meta' => [
                    'slide_count' => 1,
                    'table_count' => 1,
                    'word_count' => 5,
                ],
                'warnings' => [],
            ],
        ])->render();

        $this->assertStringContainsString('href="#drive-slide-1"', $html);
        $this->assertStringContainsString('data-drive-slide="1"', $html);
        $this->assertStringContainsString('Ringkasan cabang', $html);
        $this->assertStringContainsString('<td>Madiun</td>', $html);
        $this->assertStringContainsString('<td>125</td>', $html);
    }
}
