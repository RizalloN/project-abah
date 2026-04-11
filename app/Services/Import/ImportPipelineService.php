<?php

namespace App\Services\Import;

class ImportPipelineService
{
    public function runCsvPipeline(array $payload): array
    {
        $mode = (string) ($payload['mode'] ?? 'bulk_csv_staging');

        return match ($mode) {
            'bulk_csv_direct' => $this->runMode([
                $payload['direct_handler'] ?? null,
                $payload['staged_handler'] ?? null,
            ]),
            'bulk_csv_filtered' => $this->runMode([
                $payload['filtered_handler'] ?? null,
                $payload['staged_handler'] ?? null,
            ]),
            default => $this->runMode([
                $payload['staged_handler'] ?? null,
            ]),
        };
    }

    public function runSpreadsheetPipeline(array $payload): array
    {
        return $this->runMode([
            $payload['python_bulk_handler'] ?? null,
            $payload['python_gpu_handler'] ?? null,
            $payload['php_chunk_handler'] ?? null,
        ]);
    }

    /**
     * @param array<int, callable|null> $handlers
     */
    private function runMode(array $handlers): array
    {
        foreach ($handlers as $handler) {
            if (!is_callable($handler)) {
                continue;
            }

            $result = $handler();
            if (is_array($result) && ($result['handled'] ?? false)) {
                return $result;
            }
        }

        return [
            'handled' => false,
            'status' => 'failed',
        ];
    }
}
