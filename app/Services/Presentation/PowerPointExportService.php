<?php

namespace App\Services\Presentation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class PowerPointExportService
{
    public function __construct(
        private readonly PresentationDeckDataService $deckData,
        private readonly NativeOpenXmlPowerPointRenderer $nativeRenderer,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return array{path: string, filename: string, slide_count: int, renderer: string}
     */
    public function export(array $payload, array $options): array
    {
        $template = public_path('BRI_Presentation Template.pptx');
        $script = base_path('scripts/export_bri_performance_ppt.ps1');
        if (!is_file($template)) {
            throw new RuntimeException('Template PowerPoint BRI tidak ditemukan.');
        }

        $directory = storage_path('app/presentation-exports');
        File::ensureDirectoryExists($directory);

        $token = now()->format('YmdHis') . '-' . bin2hex(random_bytes(5));
        $outputPath = $directory . DIRECTORY_SEPARATOR . $token . '.pptx';
        $deck = $this->deckData->build($payload, $options);
        $result = [];

        try {
            try {
                $result = $this->nativeRenderer->render($deck, $template, $outputPath);
            } catch (\Throwable $nativeException) {
                File::delete($outputPath);
                if (PHP_OS_FAMILY !== 'Windows' || !is_file($script)) {
                    throw new RuntimeException(
                        'Generator PPTX native gagal: ' . $nativeException->getMessage(),
                        previous: $nativeException
                    );
                }

                $result = $this->exportWithPowerPointCom($deck, $script, $template, $outputPath);
                $result['native_error'] = $nativeException->getMessage();
            }

            $slideCount = $this->validatePresentation($outputPath);
            $date = (string) data_get($deck, 'meta.period', now()->toDateString());
            $scope = preg_replace('/[^A-Za-z0-9]+/', '-', (string) data_get($deck, 'meta.scope_label', 'Area 6'));
            $scope = trim((string) $scope, '-') ?: 'Area-6';

            return [
                'path' => $outputPath,
                'filename' => 'Performance-Review-' . $scope . '-' . $date . '.pptx',
                'slide_count' => (int) ($result['slide_count'] ?? $slideCount),
                'renderer' => (string) ($result['renderer'] ?? 'powerpoint-com'),
            ];
        } catch (\Throwable $e) {
            File::delete($outputPath);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $deck
     * @return array<string, mixed>
     */
    private function exportWithPowerPointCom(array $deck, string $script, string $template, string $outputPath): array
    {
        $jsonPath = preg_replace('/\.pptx$/i', '.json', $outputPath) ?: ($outputPath . '.json');
        $encoded = json_encode($deck, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        File::put($jsonPath, $encoded);

        try {
            return Cache::lock('presentation:pptx-export:com', 300)->block(20, function () use ($script, $template, $jsonPath, $outputPath): array {
                $process = new Process([
                    'powershell.exe',
                    '-NoProfile',
                    '-NonInteractive',
                    '-ExecutionPolicy',
                    'Bypass',
                    '-File',
                    $script,
                    '-TemplatePath',
                    $template,
                    '-DataPath',
                    $jsonPath,
                    '-OutputPath',
                    $outputPath,
                ], base_path());
                $process->setTimeout(240);
                $process->run();

                if (!$process->isSuccessful()) {
                    $message = trim($process->getErrorOutput() . PHP_EOL . $process->getOutput());
                    throw new RuntimeException('PowerPoint gagal dibuat: ' . ($message ?: 'proses generator berhenti tanpa detail.'));
                }

                $output = json_decode(trim($process->getOutput()), true);
                $output = is_array($output) ? $output : [];
                $output['renderer'] = 'powerpoint-com';

                return $output;
            });
        } finally {
            File::delete($jsonPath);
        }
    }

    private function validatePresentation(string $path): int
    {
        if (!is_file($path) || filesize($path) < 10000) {
            throw new RuntimeException('File PPT hasil ekspor tidak terbentuk dengan benar.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File hasil ekspor bukan dokumen PPTX yang valid.');
        }

        try {
            if ($zip->locateName('ppt/presentation.xml') === false) {
                throw new RuntimeException('Struktur internal PPTX tidak lengkap.');
            }

            $presentationXml = $zip->getFromName('ppt/presentation.xml');
            $count = is_string($presentationXml)
                ? (int) preg_match_all('#<p:sldId\b#', $presentationXml)
                : 0;

            if ($count < 12) {
                throw new RuntimeException("Deck hanya berisi {$count} slide; ekspor dianggap tidak lengkap.");
            }

            return $count;
        } finally {
            $zip->close();
        }
    }
}
