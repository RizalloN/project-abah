<?php

namespace App\Jobs;

use App\Services\Presentation\PowerPointExportService;
use App\Services\Presentation\PresentationExportManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GeneratePresentationPowerPointJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 360;

    /** @var array<int, int> */
    public array $backoff = [5, 20];

    public function __construct(public string $token)
    {
        $this->onQueue('reports-low');
    }

    public function handle(PresentationExportManager $manager, PowerPointExportService $exporter): void
    {
        $manager->markProcessing($this->token);
        $input = $manager->input($this->token);
        $result = $exporter->export(
            (array) ($input['payload'] ?? []),
            (array) ($input['options'] ?? [])
        );
        $manager->markCompleted($this->token, $result);
    }

    public function failed(?Throwable $exception): void
    {
        app(PresentationExportManager::class)->markFailed(
            $this->token,
            'Ekspor gagal: ' . ($exception?->getMessage() ?: 'generator berhenti tanpa detail.')
        );
    }
}
