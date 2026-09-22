<?php

namespace App\Console\Commands;

use App\Services\DocumentMarkdownService;
use Illuminate\Console\Command;

class ConvertDocumentToMarkdownCommand extends Command
{
    protected $signature = 'doc:markdown
        {file : Path to document file (.pdf, .xlsx, .docx, .pptx, .html, etc.)}
        {--o|output= : Path to save output Markdown file}
        {--c|clipboard : Copy optimized Markdown directly to Windows clipboard}
        {--preview-lines= : Only preview the first N lines}
        {--stats-only : Show only token statistics without displaying the markdown body}';

    protected $description = 'Convert Office documents (Excel, Word, PPT), PDF, and HTML to token-optimized Markdown for AI';

    public function handle(DocumentMarkdownService $service): int
    {
        $filePath = (string) $this->argument('file');
        $outputFile = $this->option('output');
        $clipboard = (bool) $this->option('clipboard');
        $previewLines = $this->option('preview-lines');
        $statsOnly = (bool) $this->option('stats-only');

        $this->info("Memproses konversi dokumen via MarkItDown: {$filePath}...");

        $options = [
            'output' => $outputFile ? (string) $outputFile : null,
            'clipboard' => $clipboard,
            'preview_lines' => $previewLines ? (int) $previewLines : null,
        ];

        $result = $service->convert($filePath, $options);

        if (!$result['success']) {
            $this->error("Gagal mengonversi: " . ($result['error'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $stats = $result['stats'] ?? [];

        $this->line("");
        $this->line("<fg=cyan;options=bold>============================================================</>");
        $this->line("<fg=cyan;options=bold>   MARKITDOWN TOKEN OPTIMIZER (PROJECT ABAH)                </>");
        $this->line("<fg=cyan;options=bold>============================================================</>");
        $this->line(" File            : <fg=yellow>{$stats['file_name']}</> (" . number_format($stats['file_size_bytes'] ?? 0) . " bytes)");
        $this->line(" Estimasi Token  : <fg=green;options=bold>~" . number_format($stats['optimized_estimated_tokens'] ?? 0) . " tokens</>");
        $this->line(" Karakter Bersih : " . number_format($stats['optimized_characters'] ?? 0) . " chars");

        if (!empty($stats['tokens_saved']) && $stats['tokens_saved'] > 0) {
            $this->line(" Penghematan     : <fg=green>~" . number_format($stats['tokens_saved']) . " tokens ({$stats['savings_percentage']}%)</>");
        }

        if (!empty($outputFile)) {
            $this->line(" Tersimpan di    : <fg=yellow>{$outputFile}</>");
        }

        if ($clipboard) {
            $copied = !empty($stats['clipboard_copied']);
            $this->line(" Clipboard       : " . ($copied ? "<fg=green>[Berhasil disalin ke Windows Clipboard!]</>" : "<fg=red>[Gagal salin ke clipboard]</>"));
        }
        $this->line("<fg=cyan;options=bold>============================================================</>");
        $this->line("");

        if (!$statsOnly && !empty($result['markdown'])) {
            $this->line($result['markdown']);
        }

        return self::SUCCESS;
    }
}
