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

    public function test_chunk_upload_consumes_prepare_preview_as_event_stream_before_redirecting(): void
    {
        $source = file_get_contents(base_path('resources/views/import/index.blade.php'));

        $this->assertStringContainsString("String(finalizePayload.redirect).includes('prepare-preview')", $source);
        $this->assertStringContainsString('new EventSource(finalizePayload.redirect)', $source);
        $this->assertStringContainsString('window.location.href = readyData.redirect', $source);
    }

    public function test_excel_init_uses_timeout_and_staging_heartbeat(): void
    {
        $stagingSource = file_get_contents(base_path('app/Services/Import/ExcelStagingService.php'));
        $executionSource = file_get_contents(base_path('app/Services/Import/ImportExecutionService.php'));
        $controllerSource = file_get_contents(base_path('app/Http/Controllers/Import/ImportExcelController.php'));

        $this->assertStringContainsString('proc_open($cmd', $stagingSource);
        $this->assertStringContainsString("config('import.excel_init_timeout_seconds'", $stagingSource);
        $this->assertStringContainsString('terminateProcess($process, $pipes)', $stagingSource);
        $this->assertStringNotContainsString('@shell_exec($cmd)', $stagingSource);

        $this->assertStringContainsString('markStaging($jobId', $executionSource);
        $this->assertStringContainsString("'phase' => 'staging_csv'", $controllerSource);
        $this->assertStringContainsString("'mode' => 'excel_stage'", $controllerSource);
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

        $this->assertStringContainsString('dashboard_pinjaman_kredit_unified:v18-strict-uker-kind-rka-cache-refresh', $source);
        $this->assertStringContainsString('kreditSnapshotSignature', $source);
        $this->assertStringContainsString("ReportCacheVersion::composite(['harian', 'pinjaman', 'simpanan'])", $source);
    }

    public function test_performance_rm_snapshot_keeps_rm_mikro_kur_on_kur_ritel_2015_only(): void
    {
        $source = file_get_contents(base_path('app/Support/ReportSnapshotBuilder.php'));

        $this->assertStringContainsString(
            "['source_segment' => 'MICRO', 'products' => ['KUR-MIKRO', 'KUR-KECIL'], 'descriptions' => ['Kredit Mikro - KUR Ritel 2015']]",
            $source
        );
        $this->assertStringContainsString("'KURKECIL' => 'KUR-MIKRO'", $source);
    }

}
