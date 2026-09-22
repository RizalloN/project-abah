<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Service to convert Office files (Word, Excel, PPT), PDF, HTML, etc.
 * into token-optimized Markdown for AI models (Claude, Gemini, Codex).
 */
class DocumentMarkdownService
{
    private const SCRIPT_PATH = 'scripts/markitdown_token_optimizer.py';

    /**
     * Convert a document file to token-optimized Markdown.
     *
     * @param string $filePath Absolute or relative path to document file
     * @param array<string, mixed> $options
     *        - output: (string|null) destination path for markdown file
     *        - clipboard: (bool) whether to copy to Windows clipboard
     *        - preview_lines: (int|null) limit markdown to first N lines
     *        - timeout: (int) process timeout in seconds (default 120)
     * @return array<string, mixed>
     */
    public function convert(string $filePath, array $options = []): array
    {
        $resolvedPath = file_exists($filePath) ? realpath($filePath) : base_path($filePath);

        if (!$resolvedPath || !file_exists($resolvedPath)) {
            return [
                'success' => false,
                'error' => "File tidak ditemukan: {$filePath}",
                'markdown' => '',
                'stats' => [],
            ];
        }

        $scriptFullPath = base_path(self::SCRIPT_PATH);
        if (!file_exists($scriptFullPath)) {
            return [
                'success' => false,
                'error' => "Script optimizer tidak ditemukan: {$scriptFullPath}",
                'markdown' => '',
                'stats' => [],
            ];
        }

        $cmd = [
            'python',
            $scriptFullPath,
            $resolvedPath,
            '--json',
        ];

        if (!empty($options['output'])) {
            $cmd[] = '-o';
            $cmd[] = $options['output'];
        }

        if (!empty($options['clipboard'])) {
            $cmd[] = '-c';
        }

        if (!empty($options['preview_lines']) && is_numeric($options['preview_lines'])) {
            $cmd[] = '--preview-lines';
            $cmd[] = (string) $options['preview_lines'];
        }

        $timeout = (int) ($options['timeout'] ?? 120);

        try {
            $process = new Process($cmd);
            $process->setTimeout($timeout);
            $process->run();

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            if (!$process->isSuccessful()) {
                Log::error('DocumentMarkdownService conversion failed', [
                    'file' => $resolvedPath,
                    'error' => $errorOutput,
                    'exit_code' => $process->getExitCode(),
                ]);

                return [
                    'success' => false,
                    'error' => $errorOutput ?: 'Proses konversi gagal tanpa pesan error spesifik.',
                    'markdown' => '',
                    'stats' => [],
                ];
            }

            $decoded = json_decode($output, true);
            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'error' => 'Gagal mem-parsing output JSON dari optimizer.',
                    'raw_output' => $output,
                    'markdown' => '',
                    'stats' => [],
                ];
            }

            return $decoded;

        } catch (Throwable $e) {
            Log::error('DocumentMarkdownService exception', [
                'file' => $resolvedPath,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'markdown' => '',
                'stats' => [],
            ];
        }
    }
}
