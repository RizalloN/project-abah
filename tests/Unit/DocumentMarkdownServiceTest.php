<?php

namespace Tests\Unit;

use App\Services\DocumentMarkdownService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DocumentMarkdownServiceTest extends TestCase
{
    public function test_it_fails_gracefully_when_file_does_not_exist(): void
    {
        $service = new DocumentMarkdownService();
        $result = $service->convert('non_existent_file_12345.xlsx');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak ditemukan', $result['error']);
    }

    public function test_it_converts_html_document_to_token_optimized_markdown(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'md_test_') . '.html';
        file_put_contents($tempFile, '<h1>Judul Laporan</h1><p>Paragraf uji coba konversi token.</p>');

        try {
            $service = new DocumentMarkdownService();
            $result = $service->convert($tempFile);

            $this->assertTrue($result['success']);
            $this->assertStringContainsString('Judul Laporan', $result['markdown']);
            $this->assertStringContainsString('Paragraf uji coba konversi token.', $result['markdown']);
            $this->assertArrayHasKey('stats', $result);
            $this->assertGreaterThan(0, $result['stats']['optimized_estimated_tokens']);
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_artisan_doc_markdown_command_executes_successfully(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'md_test_cmd_') . '.html';
        file_put_contents($tempFile, '<h2>Ringkasan Eksekutif</h2><p>Data target tercapai.</p>');

        try {
            $exitCode = Artisan::call('doc:markdown', [
                'file' => $tempFile,
                '--stats-only' => true,
            ]);

            $this->assertSame(0, $exitCode);
            $output = Artisan::output();
            $this->assertStringContainsString('MARKITDOWN TOKEN OPTIMIZER', $output);
            $this->assertStringContainsString('Estimasi Token', $output);
        } finally {
            @unlink($tempFile);
        }
    }
}
