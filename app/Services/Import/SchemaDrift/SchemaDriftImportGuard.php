<?php

namespace App\Services\Import\SchemaDrift;

use RuntimeException;

/** Enforces the same source review at preview, enqueue and database execution. */
class SchemaDriftImportGuard
{
    private ?array $report = null;

    public function __construct(private readonly SchemaDriftAnalyzer $analyzer) {}

    public function assertReady(string $table, string $path, array $options = []): array
    {
        $this->report = null;
        $report = $this->analyzer->analyze($table, $path, array_merge($options, [
            'record' => true,
            // A worker replay must never replace the last reviewed reference.
            'remember' => false,
        ]));
        $this->report = $report;

        if (empty($report['source']['structural_scan']['complete'])) {
            throw new RuntimeException('Import ditahan: pemeriksaan seluruh struktur sumber belum selesai. Pindai ulang preview.');
        }
        if (! empty($report['has_blocking_issue'])) {
            throw new RuntimeException('Import ditahan: struktur atau isi sumber berisiko kehilangan data. Periksa temuan di preview dan perbaiki sumber sebelum melanjutkan.');
        }
        if (! empty($report['has_pending_decision'])) {
            throw new RuntimeException('Import ditahan: ada kolom atau perubahan format yang belum diputuskan. Buka pemindai struktur di preview, pilih tujuan kolom atau konfirmasi perubahan, lalu ulangi import.');
        }

        return $report;
    }

    public function lastReport(): ?array
    {
        return $this->report;
    }
}
