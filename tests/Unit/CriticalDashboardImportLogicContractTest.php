<?php

namespace Tests\Unit;

use Tests\TestCase;

class CriticalDashboardImportLogicContractTest extends TestCase
{
    public function test_simpanan_multipn_csv_import_stays_on_dedicated_stream_executor(): void
    {
        $source = file_get_contents(base_path('app/Services/Import/ImportExecutionService.php'));

        $this->assertStringContainsString('isSimpananMultiPnCsvStreamJob', $source);
        $this->assertStringContainsString('generic queue dispatch skipped', $source);
        $this->assertStringContainsString('Generic import worker skipped Simpanan MultiPN CSV job', $source);
    }

    public function test_import_preview_prevents_duplicate_submit_and_generic_force_start_for_simpanan_csv(): void
    {
        $source = file_get_contents(base_path('resources/views/import/preview.blade.php'));

        $this->assertStringContainsString('importSubmitInProgress', $source);
        $this->assertStringContainsString('/import-csv/simpanan-multipn/stream', $source);
        $this->assertStringContainsString('allowForceStart', $source);
    }

    public function test_dashboard_pinjaman_kredit_reads_dashboard_harian_summary_key_rows_only(): void
    {
        $source = file_get_contents(base_path('app/Support/DashboardPinjamanKreditService.php'));

        $this->assertStringContainsString("whereColumn('kanca_key', 'unit_key')", $source);
        $this->assertStringNotContainsString("whereRaw('unit_label = kanca_label')", $source);
    }

    public function test_dashboard_pinjaman_kredit_cache_tracks_dashboard_harian_snapshot_signature(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/DashboardPinjamanReportController.php'));

        $this->assertStringContainsString('dashboard_pinjaman_kredit_unified:v12-quality-rka-direction', $source);
        $this->assertStringContainsString('kreditSnapshotSignature', $source);
        $this->assertStringContainsString("ReportCacheVersion::composite(['harian', 'pinjaman', 'simpanan'])", $source);
    }
}
