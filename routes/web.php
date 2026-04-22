<?php

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Http\Controllers\DashboardHarianController;
use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\Report\DigitalPerformanceController;
use App\Http\Controllers\Report\KejarLabaReportController;
use App\Http\Controllers\Report\KinerjaKonsumerReportController;
use App\Http\Controllers\Report\KolaborasiReportController;
use App\Http\Controllers\Report\NewPayrollReportController;
use App\Http\Controllers\Import\ImportCasaBrilinkController;
use App\Http\Controllers\Import\ImportCognosPhController;
use App\Http\Controllers\Import\ImportCognosRecoveryController;
use App\Http\Controllers\Import\ImportCleanupController;
use App\Http\Controllers\Import\ImportDailyLoanBackendController;
use App\Http\Controllers\Import\ImportFileBrimoController;
use App\Http\Controllers\Import\ImportFileController;
use App\Http\Controllers\Import\Gi405RecDhImportExcelController;
use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportJobStatusController;
use App\Http\Controllers\Import\ImportJobManagementController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Http\Controllers\Import\ImportReportPhController;
use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Http\Controllers\Import\SnapshotAuditController;
use App\Http\Controllers\Input\BodBocController;
use App\Http\Controllers\Input\InputRekananController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\FileManagementDownloadController;
use App\Http\Controllers\Admin\FileManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\RasioCasaDebiturController;
use App\Http\Controllers\RekeningDormantController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return app(AuthenticatedSessionController::class)->create();
})->name('home');

Route::middleware(['auth', 'release.session.lock', 'throttle:240,1'])->group(function () {
    Route::get('/dashboard-harian', [DashboardHarianController::class, 'index'])
        ->name('dashboard.harian');
    Route::get('/dashboard-harian/timeseries', [DashboardHarianController::class, 'timeseries'])
        ->name('dashboard.harian.timeseries');
    Route::get('/dashboard-harian/timeseries/data', [DashboardHarianController::class, 'timeseriesData'])
        ->name('dashboard.harian.timeseries.data');
    Route::get('/dashboard-harian/data', [DashboardHarianController::class, 'data'])
        ->name('dashboard.harian.data');

    Route::get('/dashboard', [DashboardSimpananController::class, 'index'])
        ->name('dashboard');

    Route::get('/report/dashboard-pinjaman', [DashboardPinjamanReportController::class, 'summaryIndex'])
        ->name('report.dashboard-pinjaman');
    Route::get('/report/dashboard-pinjaman/summary', [DashboardPinjamanReportController::class, 'summaryIndex'])
        ->name('report.dashboard-pinjaman.summary');
    Route::get('/report/dashboard-pinjaman/matrix-pergeseran-kolek', [DashboardPinjamanReportController::class, 'matrixIndex'])
        ->name('report.dashboard-pinjaman.matrix');
    Route::get('/report/dashboard-pinjaman/matrix-pergeseran-kolek/detail', [DashboardPinjamanReportController::class, 'matrixDetail'])
        ->name('report.dashboard-pinjaman.matrix.detail');
    Route::get('/report/dashboard-pinjaman/matrix-pergeseran-kolek/export', [DashboardPinjamanReportController::class, 'matrixExport'])
        ->name('report.dashboard-pinjaman.matrix.export');

    Route::get('/report/dashboard-pinjaman/kolek-tidak-sesuai', [DashboardPinjamanReportController::class, 'mismatchIndex'])
        ->name('report.dashboard-pinjaman.kolek-tidak-sesuai');
    Route::get('/report/dashboard-pinjaman/filters', [DashboardPinjamanReportController::class, 'filters'])
        ->name('report.dashboard-pinjaman.filters');
    Route::get('/report/dashboard-pinjaman/data', [DashboardPinjamanReportController::class, 'data'])
        ->name('report.dashboard-pinjaman.data');
    Route::get('/report/dashboard-pinjaman/kolek-tidak-sesuai/filters', [DashboardPinjamanReportController::class, 'mismatchFilters'])
        ->name('report.dashboard-pinjaman.kolek-tidak-sesuai.filters');
    Route::get('/report/dashboard-pinjaman/kolek-tidak-sesuai/data', [DashboardPinjamanReportController::class, 'mismatchData'])
        ->name('report.dashboard-pinjaman.kolek-tidak-sesuai.data');
    Route::get('/report/dashboard-pinjaman/kolek-tidak-sesuai/export', [DashboardPinjamanReportController::class, 'mismatchExport'])
        ->name('report.dashboard-pinjaman.kolek-tidak-sesuai.export');
    Route::get('/report/dashboard-pinjaman/kredit', [DashboardPinjamanReportController::class, 'kreditIndex'])
        ->name('report.dashboard-pinjaman.kredit');
    Route::get('/report/dashboard-pinjaman/kredit/data', [DashboardPinjamanReportController::class, 'kreditData'])
        ->name('report.dashboard-pinjaman.kredit.data');
    Route::get('/report/dashboard-pinjaman/kejar-laba', [KejarLabaReportController::class, 'index'])
        ->name('report.dashboard-pinjaman.kejar-laba');
    Route::get('/report/dashboard-pinjaman/kinerja-konsumer', [KinerjaKonsumerReportController::class, 'index'])
        ->name('report.dashboard-pinjaman.kinerja-konsumer');

    // Digital Performance Reports (EDC, QRIS, Brilink) — dihandle oleh DigitalPerformanceController
    Route::get('/report/optimalisasi-digital/edc', [DigitalPerformanceController::class, 'performanceEdc'])->name('report.edc');
    Route::get('/report/optimalisasi-digital/qris', [DigitalPerformanceController::class, 'performanceQris'])->name('report.qris');
    Route::get('/report/optimalisasi-digital/brilink', [DigitalPerformanceController::class, 'performanceBrilink'])->name('report.brilink');
    Route::post('/report/data', [DigitalPerformanceController::class, 'fetchData'])->name('report.data');
    Route::post('/report/data/qris/ukers', [DigitalPerformanceController::class, 'fetchQrisUkers'])->name('report.qris.ukers');

    // BRIMO — menggunakan PerformanceBrimoController yang sudah di-fix N+1-nya
    Route::get('/report/optimalisasi-digital/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'index'])->name('report.brimo');
    Route::post('/report/data/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'fetchData'])->name('report.data.brimo');

    // Kolaborasi Perusahaan Anak — dihandle oleh KolaborasiReportController
    Route::get('/report/kolaborasi-perusahaan-anak/program-referral-partner-perusahaan-anak', [KolaborasiReportController::class, 'programReferralPartnerPerusahaanAnak'])->name('report.kolaborasi.referral');
    Route::get('/report/kolaborasi-perusahaan-anak/nasabah-prioritas-bod-boc', [KolaborasiReportController::class, 'nasabahPrioritasBodBoc'])->name('report.kolaborasi.bodboc');

    Route::get('/report/rekening-transaksi-debitur', [RasioCasaDebiturController::class, 'index'])->name('report.rasiocasa.debitur');
    Route::post('/report/data/rasiocasa', [RasioCasaDebiturController::class, 'fetchData'])->name('report.data.rasiocasa');
    Route::post('/report/data/rasiocasa-per-rm', [RasioCasaDebiturController::class, 'fetchDataPerRm'])->name('report.data.rasiocasa-per-rm');
    Route::get('/report/rekening-transaksi-debitur/filters-per-rm', [RasioCasaDebiturController::class, 'filtersPerRm'])->name('report.rasiocasa.filters-per-rm');
    Route::get('/report/rekening-transaksi-debitur/rekening-dormant', [RekeningDormantController::class, 'index'])->name('report.rekening-dormant');
    Route::get('/report/rekening-transaksi-debitur/rekening-dormant/filters', [RekeningDormantController::class, 'filters'])->name('report.rekening-dormant.filters');
    Route::post('/report/data/rekening-dormant', [RekeningDormantController::class, 'fetchData'])->name('report.data.rekening-dormant');

    // New Payroll — dihandle oleh NewPayrollReportController
    Route::get('/report/peningkatan-payroll-berkualitas/kinerja-new-payroll', [NewPayrollReportController::class, 'index'])->name('report.kinerja.newpayroll');
    Route::post('/report/data/newpayroll', [NewPayrollReportController::class, 'fetchData'])->name('report.data.newpayroll');
});

Route::middleware(['auth', 'role:admin', 'release.session.lock'])->group(function () {
    Route::get('/input-data', [InputRekananController::class, 'index'])->name('input.index');
    Route::post('/input-data', [InputRekananController::class, 'store'])->name('input.store');
    Route::post('/input-data/import-template', [InputRekananController::class, 'importTemplate'])->name('input.import-template');
    Route::get('/input-data/import-preview', [InputRekananController::class, 'previewImport'])->name('input.import-preview');
    Route::post('/bod-boc/import-template', [BodBocController::class, 'importTemplate'])->name('bod-boc.import-template');
    Route::get('/bod-boc/import-preview', [BodBocController::class, 'previewImport'])->name('bod-boc.import-preview');
    Route::post('/bod-boc/store', [BodBocController::class, 'store'])->name('bod-boc.store');
    Route::get('/import', [ImportIndexController::class, 'index'])->name('import.index');
    Route::get('/report-management', [ImportIndexController::class, 'reportManagement'])->name('report-management.index');
    Route::get('/job-management', [ImportJobManagementController::class, 'index'])->name('job-management.index');
    Route::get('/job-management/data', [ImportJobManagementController::class, 'data'])->name('job-management.data');
    Route::post('/job-management/clear', [ImportJobManagementController::class, 'clear'])->name('job-management.clear');
    Route::post('/job-management/bulk-delete', [ImportJobManagementController::class, 'bulkDestroy'])->name('job-management.bulk-destroy');
    Route::post('/job-management/{jobId}/force-start', [ImportJobManagementController::class, 'forceStart'])->name('job-management.force-start');
    Route::post('/job-management/snapshot/{rebuildId}/force-start', [ImportJobManagementController::class, 'forceStartSnapshot'])->name('job-management.snapshot.force-start');
    Route::post('/job-management/{jobId}/terminate', [ImportJobManagementController::class, 'terminate'])->name('job-management.terminate');
    Route::delete('/job-management/{jobId}', [ImportJobManagementController::class, 'destroy'])->name('job-management.destroy');
    Route::delete('/job-management/queue-job/{queueJobId}', [ImportJobManagementController::class, 'destroyQueueJob'])->name('job-management.queue.destroy');
    Route::post('/job-management/queue-job/{queueJobId}/force-run', [ImportJobManagementController::class, 'forceRunQueueJob'])->name('job-management.queue.force-run');
    Route::post('/job-management/queue-job/purge', [ImportJobManagementController::class, 'purgeQueueJobs'])->name('job-management.queue.purge');
    Route::get('/file-management', [FileManagementController::class, 'index'])->name('file-management.index');
    Route::post('/file-management/database-backup', [FileManagementController::class, 'backupDatabase'])->name('file-management.database-backup');
    Route::get('/file-management/database-backup/{backupId}/status', [FileManagementController::class, 'getBackupStatus'])->name('file-management.database-backup.status');
    Route::get('/file-management/download', FileManagementDownloadController::class)->name('file-management.download');
    Route::post('/file-management/delete', [FileManagementController::class, 'destroy'])->name('file-management.destroy');
    Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management.index');
    Route::post('/user-management', [UserManagementController::class, 'store'])->name('user-management.store');
    Route::put('/user-management/{user}', [UserManagementController::class, 'update'])->name('user-management.update');
    Route::delete('/user-management/{user}', [UserManagementController::class, 'destroy'])->name('user-management.destroy');
    Route::get('/import/upload-limits', [ImportIndexController::class, 'uploadLimits'])->name('import.upload-limits');
    Route::get('/import/template', [ImportIndexController::class, 'downloadTemplate'])->name('import.template');
    Route::post('/import/report-management/data', [ImportIndexController::class, 'reportManagementData'])->name('import.report-management.data');
    Route::get('/import/report-management/queue-status', [ImportIndexController::class, 'getQueueStatus'])->name('import.queue-status');
    Route::post('/import/report-management/load', [ImportIndexController::class, 'startManagedReportLoad'])->name('import.report-management.load');
    Route::get('/import/report-management/load/{loadId}/status', [ImportIndexController::class, 'managedReportLoadStatus'])->name('import.report-management.load.status');
    Route::post('/import/report-management/rebuild', [ImportIndexController::class, 'rebuildManagedReportSnapshots'])->name('import.report-management.rebuild');
    Route::get('/import/report-management/rebuild/{rebuildId}/status', [ImportIndexController::class, 'managedReportRebuildStatus'])->name('import.report-management.rebuild.status');
    Route::post('/import/report-management/recover', [ImportIndexController::class, 'startManagedReportRecovery'])->name('import.report-management.recover');
    Route::get('/import/report-management/recover/{recoveryId}/status', [ImportIndexController::class, 'managedReportRecoveryStatus'])->name('import.report-management.recover.status');
    Route::post('/import/report-management/delete', [ImportIndexController::class, 'deleteManagedReportRows'])->name('import.report-management.delete');
    Route::post('/import/report-management/duplicates', [ImportIndexController::class, 'deleteManagedReportDuplicates'])->name('import.report-management.duplicates');
    Route::post('/import/report-management/delete/{deleteId}/process', [ImportIndexController::class, 'processManagedReportDelete'])->name('import.report-management.delete.process');
    Route::get('/import/report-management/delete/{deleteId}/status', [ImportIndexController::class, 'managedReportDeleteStatus'])->name('import.report-management.delete.status');
    Route::post('/import/report-management/delete/{deleteId}/force-stop', [ImportIndexController::class, 'forceStopManagedReportDelete'])->name('import.report-management.delete.force-stop');
    Route::post('/import/report-management/delete/{deleteId}/cancel', [ImportIndexController::class, 'cancelManagedReportDelete'])->name('import.report-management.delete.cancel');

    // Snapshot Audit & Smart Sync Routes
    Route::post('/import/snapshot-audit/run', [SnapshotAuditController::class, 'runAudit'])->name('snapshot-audit.run');
    Route::get('/import/snapshot-audit/{auditId}/result', [SnapshotAuditController::class, 'getAuditResult'])->name('snapshot-audit.result');
    Route::post('/import/snapshot-audit/{auditId}/rebuild', [SnapshotAuditController::class, 'triggerSmartRebuild'])->name('snapshot-audit.rebuild');
    Route::post('/import/snapshot-audit/compare', [SnapshotAuditController::class, 'compareAudits'])->name('snapshot-audit.compare');
    Route::get('/import/snapshot-audit/action/{tableName}', [SnapshotAuditController::class, 'getRecommendedAction'])->name('snapshot-audit.action');

    Route::get('/import/jobs/{jobId}/status', ImportJobStatusController::class)->name('import.jobs.status');
    Route::post('/import/upload', [ImportFileController::class, 'upload'])->name('import.upload');
    Route::post('/import/backend/daily-loan/local-file', [ImportDailyLoanBackendController::class, 'importLocalCsv'])
        ->name('import.backend.daily-loan.local-file');

    Route::get('/import/select', function () {
        $files = session('import_files', []);
        return view('import.select-file', compact('files'));
    })->name('import.select');

    Route::post('/import/preview', [ImportFileController::class, 'preview'])->name('import.preview');
    Route::get('/import/preview/direct', [ImportFileController::class, 'preview'])->name('import.preview.direct');
    Route::get('/import/preview/filter-options', [ImportFileController::class, 'previewFilterOptions'])->name('import.preview.filter-options');
    Route::get('/import/preview/warm-index', [ImportFileController::class, 'previewWarmIndex'])->name('import.preview.warm-index');
    Route::get('/import/preview/dynamic-filter-options', [ImportFileController::class, 'previewDynamicFilterOptions'])->name('import.preview.dynamic-filter-options');
    Route::get('/import/preview/filtered-rows', [ImportFileController::class, 'previewFilteredRows'])->name('import.preview.filtered-rows');
    Route::post('/import/casa-brilink/upload', [ImportCasaBrilinkController::class, 'upload'])->name('import.casabrilink.upload');
    Route::get('/import/casa-brilink/preview', [ImportCasaBrilinkController::class, 'preview'])->name('import.casabrilink.preview');
    Route::post('/import/casa-brilink/preview', [ImportCasaBrilinkController::class, 'preview'])->name('import.casabrilink.preview.refresh');
    Route::get('/import/casa-brilink/prepare-preview', [ImportCasaBrilinkController::class, 'preparePreviewStream'])->name('import.casabrilink.prepare-preview');
    Route::post('/import/casa-brilink/init', [ImportCasaBrilinkController::class, 'initImport'])->name('import.casabrilink.init');
    Route::get('/import/casa-brilink/stream', [ImportCasaBrilinkController::class, 'processImportStream'])->name('import.casabrilink.stream');
    Route::post('/import/casa-brilink/process', [ImportCasaBrilinkController::class, 'processImport'])->name('import.casabrilink.process');
    Route::post('/import/performance-pis/upload', [ImportPerformancePisPerProdukController::class, 'upload'])->name('import.performancepis.upload');
    Route::get('/import/performance-pis/preview', [ImportPerformancePisPerProdukController::class, 'preview'])->name('import.performancepis.preview');
    Route::post('/import/performance-pis/preview', [ImportPerformancePisPerProdukController::class, 'preview'])->name('import.performancepis.preview.refresh');
    Route::get('/import/performance-pis/prepare-preview', [ImportPerformancePisPerProdukController::class, 'preparePreviewStream'])->name('import.performancepis.prepare-preview');
    Route::post('/import/performance-pis/init', [ImportPerformancePisPerProdukController::class, 'initImport'])->name('import.performancepis.init');
    Route::get('/import/performance-pis/stream', [ImportPerformancePisPerProdukController::class, 'processImportStream'])->name('import.performancepis.stream');
    Route::post('/import/performance-pis/process', [ImportPerformancePisPerProdukController::class, 'processImport'])->name('import.performancepis.process');
    Route::post('/import/report-ph/upload', [ImportReportPhController::class, 'upload'])->name('import.reportph.upload');
    Route::get('/import/report-ph/preview', [ImportReportPhController::class, 'preview'])->name('import.reportph.preview');
    Route::post('/import/report-ph/preview', [ImportReportPhController::class, 'preview'])->name('import.reportph.preview.refresh');
    Route::get('/import/report-ph/prepare-preview', [ImportReportPhController::class, 'preparePreviewStream'])->name('import.reportph.prepare-preview');
    Route::post('/import/report-ph/init', [ImportReportPhController::class, 'initImport'])->name('import.reportph.init');
    Route::get('/import/report-ph/stream', [ImportReportPhController::class, 'processImportStream'])->name('import.reportph.stream');
    Route::post('/import/report-ph/process', [ImportReportPhController::class, 'processImport'])->name('import.reportph.process');
    Route::post('/import/cognos-recovery/upload', [ImportCognosRecoveryController::class, 'upload'])->name('import.cognos-recovery.upload');
    Route::get('/import/cognos-recovery/preview', [ImportCognosRecoveryController::class, 'preview'])->name('import.cognos-recovery.preview');
    Route::post('/import/cognos-recovery/preview', [ImportCognosRecoveryController::class, 'preview'])->name('import.cognos-recovery.preview.refresh');
    Route::get('/import/cognos-recovery/prepare-preview', [ImportCognosRecoveryController::class, 'preparePreviewStream'])->name('import.cognos-recovery.prepare-preview');
    Route::post('/import/cognos-recovery/init', [ImportCognosRecoveryController::class, 'initImport'])->name('import.cognos-recovery.init');
    Route::get('/import/cognos-recovery/stream', [ImportCognosRecoveryController::class, 'processImportStream'])->name('import.cognos-recovery.stream');
    Route::post('/import/cognos-recovery/process', [ImportCognosRecoveryController::class, 'processImport'])->name('import.cognos-recovery.process');
    Route::post('/import/cognos-ph/upload', [ImportCognosPhController::class, 'upload'])->name('import.cognos-ph.upload');
    Route::get('/import/cognos-ph/preview', [ImportCognosPhController::class, 'preview'])->name('import.cognos-ph.preview');
    Route::post('/import/cognos-ph/preview', [ImportCognosPhController::class, 'preview'])->name('import.cognos-ph.preview.refresh');
    Route::get('/import/cognos-ph/prepare-preview', [ImportCognosPhController::class, 'preparePreviewStream'])->name('import.cognos-ph.prepare-preview');
    Route::post('/import/cognos-ph/init', [ImportCognosPhController::class, 'initImport'])->name('import.cognos-ph.init');
    Route::get('/import/cognos-ph/stream', [ImportCognosPhController::class, 'processImportStream'])->name('import.cognos-ph.stream');
    Route::post('/import/cognos-ph/process', [ImportCognosPhController::class, 'processImport'])->name('import.cognos-ph.process');

    Route::post('/import-excel/init', [App\Http\Controllers\Import\ImportExcelController::class, 'initExcelImport'])->name('import.excel.init');
    Route::get('/import-excel/stream', [App\Http\Controllers\Import\ImportExcelController::class, 'processExcelStream'])->name('import.excel.stream');
    Route::post('/import-excel/chunk', [App\Http\Controllers\Import\ImportExcelController::class, 'processExcelChunk'])->name('import.excel.chunk');

    Route::post('/import/init', [ImportFileController::class, 'initImport'])->name('import.init');
    Route::get('/import/stream', [ImportFileController::class, 'processImportStream'])->name('import.stream');

    Route::post('/import/process', [ImportFileController::class, 'processImport'])->name('import.process');
    Route::post('/import/cleanup-artifacts', [ImportCleanupController::class, 'cleanupStaleArtifacts'])->name('import.cleanup-artifacts');

    Route::post('/import/brimo/upload', [ImportFileBrimoController::class, 'upload'])->name('import.brimo.upload');
    Route::post('/import/brimo/preview', [ImportFileBrimoController::class, 'preview'])->name('import.brimo.preview');
    Route::post('/import/brimo/process', [ImportFileBrimoController::class, 'processImport'])->name('import.brimo.process');

    Route::prefix('import-excel')->group(function () {
        Route::post('/upload', [App\Http\Controllers\Import\ImportExcelController::class, 'uploadExcel'])->name('import.excel.upload');
        Route::get('/preview', [App\Http\Controllers\Import\ImportExcelController::class, 'previewExcel'])->name('import.excel.preview');
        Route::get('/prepare-preview', [App\Http\Controllers\Import\ImportExcelController::class, 'preparePreviewStream'])->name('import.excel.prepare-preview');
    });

    Route::prefix('import-excel/gi405-rec-dh')->group(function () {
        Route::post('/upload', [Gi405RecDhImportExcelController::class, 'uploadExcel'])->name('import.gi405.upload');
        Route::get('/preview', [Gi405RecDhImportExcelController::class, 'previewExcel'])->name('import.gi405.preview');
        Route::get('/prepare-preview', [Gi405RecDhImportExcelController::class, 'preparePreviewStream'])->name('import.gi405.prepare-preview');
        Route::post('/init', [Gi405RecDhImportExcelController::class, 'initExcelImport'])->name('import.gi405.init');
        Route::get('/stream', [Gi405RecDhImportExcelController::class, 'processExcelStream'])->name('import.gi405.stream');
        Route::post('/chunk', [Gi405RecDhImportExcelController::class, 'processExcelChunk'])->name('import.gi405.chunk');
    });

    Route::prefix('import-excel/daily-loan-dinamis')->group(function () {
        Route::post('/upload', [App\Http\Controllers\Import\ImportExcelController::class, 'uploadDailyLoanExcel'])->name('import.dailyloan.upload');
        Route::post('/upload-chunk/init', [App\Http\Controllers\Import\ImportExcelController::class, 'initDailyLoanChunkUpload'])->name('import.dailyloan.upload-chunk.init');
        Route::post('/upload-chunk', [App\Http\Controllers\Import\ImportExcelController::class, 'uploadDailyLoanChunk'])->name('import.dailyloan.upload-chunk');
        Route::post('/upload-chunk/finalize', [App\Http\Controllers\Import\ImportExcelController::class, 'finalizeDailyLoanChunkUpload'])->name('import.dailyloan.upload-chunk.finalize');
        Route::get('/preview', [App\Http\Controllers\Import\ImportExcelController::class, 'previewDailyLoanExcel'])->name('import.dailyloan.preview');
        Route::get('/prepare-preview', [App\Http\Controllers\Import\ImportExcelController::class, 'prepareDailyLoanPreview'])->name('import.dailyloan.prepare-preview');
        Route::post('/init', [App\Http\Controllers\Import\ImportExcelController::class, 'initDailyLoanImport'])->name('import.dailyloan.init');
        Route::get('/stream', [App\Http\Controllers\Import\ImportExcelController::class, 'streamDailyLoanImport'])->name('import.dailyloan.stream');
        Route::post('/chunk', [App\Http\Controllers\Import\ImportExcelController::class, 'chunkDailyLoanImport'])->name('import.dailyloan.chunk');
    });

    Route::prefix('import-excel/simpanan-multipn')->group(function () {
        Route::post('/upload', [App\Http\Controllers\Import\ImportExcelController::class, 'uploadSimpananMultiPnExcel'])->name('import.simpanan.upload');
        Route::get('/preview', [App\Http\Controllers\Import\ImportExcelController::class, 'previewSimpananMultiPnExcel'])->name('import.simpanan.preview');
        Route::get('/prepare-preview', [App\Http\Controllers\Import\ImportExcelController::class, 'prepareSimpananMultiPnPreview'])->name('import.simpanan.prepare-preview');
        Route::post('/init', [App\Http\Controllers\Import\ImportExcelController::class, 'initSimpananMultiPnImport'])->name('import.simpanan.init');
        Route::get('/stream', [App\Http\Controllers\Import\ImportExcelController::class, 'streamSimpananMultiPnImport'])->name('import.simpanan.stream');
        Route::post('/chunk', [App\Http\Controllers\Import\ImportExcelController::class, 'chunkSimpananMultiPnImport'])->name('import.simpanan.chunk');
    });

    Route::prefix('import-csv/simpanan-multipn')->group(function () {
        Route::post('/upload', [ImportSimpananMultiPnCsvController::class, 'upload'])->name('import.simpanan.csv.upload');
        Route::get('/preview', [ImportSimpananMultiPnCsvController::class, 'preview'])->name('import.simpanan.csv.preview');
        Route::get('/prepare-preview', [ImportSimpananMultiPnCsvController::class, 'preparePreviewStream'])->name('import.simpanan.csv.prepare-preview');
        Route::post('/init', [ImportSimpananMultiPnCsvController::class, 'initImport'])->name('import.simpanan.csv.init');
        Route::get('/stream', [ImportSimpananMultiPnCsvController::class, 'processImportStream'])->name('import.simpanan.csv.stream');
    });
});

require __DIR__.'/auth.php';
