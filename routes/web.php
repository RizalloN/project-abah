<?php

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Http\Controllers\DashboardHarianController;
use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\Import\ImportCasaBrilinkController;
use App\Http\Controllers\Import\ImportCleanupController;
use App\Http\Controllers\Import\ImportFileBrimoController;
use App\Http\Controllers\Import\ImportFileController;
use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportJobStatusController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Http\Controllers\Import\ImportReportPhController;
use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Http\Controllers\Input\BodBocController;
use App\Http\Controllers\Input\InputRekananController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\FileManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\RasioCasaDebiturController;
use App\Http\Controllers\RekeningDormantController;
use Illuminate\Http\Request;
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
    Route::get('/dashboard-harian/data', [DashboardHarianController::class, 'data'])
        ->name('dashboard.harian.data');

    Route::get('/dashboard', [DashboardSimpananController::class, 'index'])
        ->name('dashboard');

    Route::get('/report/dashboard-pinjaman', [DashboardPinjamanReportController::class, 'index'])
        ->name('report.dashboard-pinjaman');
    Route::get('/report/dashboard-pinjaman/filters', [DashboardPinjamanReportController::class, 'filters'])
        ->name('report.dashboard-pinjaman.filters');
    Route::get('/report/dashboard-pinjaman/data', [DashboardPinjamanReportController::class, 'data'])
        ->name('report.dashboard-pinjaman.data');

    Route::get('/report/optimalisasi-digital/edc', [App\Http\Controllers\DataReportController::class, 'performanceEdc'])->name('report.edc');
    Route::get('/report/optimalisasi-digital/qris', [App\Http\Controllers\DataReportController::class, 'performanceQris'])->name('report.qris');
    Route::get('/report/optimalisasi-digital/brilink', [App\Http\Controllers\DataReportController::class, 'performanceBrilink'])->name('report.brilink');
    Route::get('/report/optimalisasi-digital/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'index'])->name('report.brimo');
    Route::post('/report/data/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'fetchData'])->name('report.data.brimo');

    Route::get('/report/kolaborasi-perusahaan-anak/program-referral-partner-perusahaan-anak', [App\Http\Controllers\DataReportController::class, 'programReferralPartnerPerusahaanAnak'])->name('report.kolaborasi.referral');
    Route::get('/report/kolaborasi-perusahaan-anak/nasabah-prioritas-bod-boc', [App\Http\Controllers\DataReportController::class, 'nasabahPrioritasBodBoc'])->name('report.kolaborasi.bodboc');

    Route::get('/report/rekening-transaksi-debitur', [RasioCasaDebiturController::class, 'index'])->name('report.rasiocasa.debitur');
    Route::post('/report/data/rasiocasa', [RasioCasaDebiturController::class, 'fetchData'])->name('report.data.rasiocasa');
    Route::get('/report/rekening-transaksi-debitur/rekening-dormant', [RekeningDormantController::class, 'index'])->name('report.rekening-dormant');
    Route::get('/report/rekening-transaksi-debitur/rekening-dormant/filters', [RekeningDormantController::class, 'filters'])->name('report.rekening-dormant.filters');
    Route::post('/report/data/rekening-dormant', [RekeningDormantController::class, 'fetchData'])->name('report.data.rekening-dormant');

    Route::get('/report/peningkatan-payroll-berkualitas/kinerja-new-payroll', [App\Http\Controllers\DataReportController::class, 'performanceNewPayroll'])->name('report.kinerja.newpayroll');
    Route::post('/report/data/newpayroll', [App\Http\Controllers\DataReportController::class, 'fetchNewPayrollData'])->name('report.data.newpayroll');
    Route::post('/report/data', [App\Http\Controllers\DataReportController::class, 'fetchData'])->name('report.data');
});

Route::middleware(['auth', 'role:admin', 'release.session.lock'])->group(function () {
    Route::get('/debug-upload-limits', function (Request $request) {
        abort_unless(app()->environment('local'), 404);

        return [
            'sapi' => PHP_SAPI,
            'loaded_ini' => php_ini_loaded_file(),
            'scanned_ini' => php_ini_scanned_files(),
            'user_ini_filename' => ini_get('user_ini.filename'),
            'user_ini_cache_ttl' => ini_get('user_ini.cache_ttl'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'content_length' => $request->server('CONTENT_LENGTH'),
        ];
    })->middleware('throttle:10,1');

    Route::get('/input-data', [InputRekananController::class, 'index'])->name('input.index');
    Route::post('/input-data', [InputRekananController::class, 'store'])->name('input.store');
    Route::post('/input-data/import-template', [InputRekananController::class, 'importTemplate'])->name('input.import-template');
    Route::get('/input-data/import-preview', [InputRekananController::class, 'previewImport'])->name('input.import-preview');
    Route::post('/bod-boc/import-template', [BodBocController::class, 'importTemplate'])->name('bod-boc.import-template');
    Route::get('/bod-boc/import-preview', [BodBocController::class, 'previewImport'])->name('bod-boc.import-preview');
    Route::post('/bod-boc/store', [BodBocController::class, 'store'])->name('bod-boc.store');
    Route::get('/import', [ImportIndexController::class, 'index'])->name('import.index');
    Route::get('/report-management', [ImportIndexController::class, 'reportManagement'])->name('report-management.index');
    Route::get('/file-management', [FileManagementController::class, 'index'])->name('file-management.index');
    Route::post('/file-management/delete', [FileManagementController::class, 'destroy'])->name('file-management.destroy');
    Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management.index');
    Route::post('/user-management', [UserManagementController::class, 'store'])->name('user-management.store');
    Route::put('/user-management/{user}', [UserManagementController::class, 'update'])->name('user-management.update');
    Route::delete('/user-management/{user}', [UserManagementController::class, 'destroy'])->name('user-management.destroy');
    Route::get('/import/upload-limits', [ImportIndexController::class, 'uploadLimits'])->name('import.upload-limits');
    Route::get('/import/template', [ImportIndexController::class, 'downloadTemplate'])->name('import.template');
    Route::post('/import/report-management/data', [ImportIndexController::class, 'reportManagementData'])->name('import.report-management.data');
    Route::post('/import/report-management/delete', [ImportIndexController::class, 'deleteManagedReportRows'])->name('import.report-management.delete');
    Route::post('/import/report-management/delete/{deleteId}/process', [ImportIndexController::class, 'processManagedReportDelete'])->name('import.report-management.delete.process');
    Route::get('/import/report-management/delete/{deleteId}/status', [ImportIndexController::class, 'managedReportDeleteStatus'])->name('import.report-management.delete.status');
    Route::post('/import/report-management/delete/{deleteId}/cancel', [ImportIndexController::class, 'cancelManagedReportDelete'])->name('import.report-management.delete.cancel');
    Route::get('/import/jobs/{jobId}/status', ImportJobStatusController::class)->name('import.jobs.status');
    Route::post('/import/upload', [ImportFileController::class, 'upload'])->name('import.upload');

    Route::get('/import/select', function () {
        $files = session('import_files', []);
        return view('import.select-file', compact('files'));
    })->name('import.select');

    Route::post('/import/preview', [ImportFileController::class, 'preview'])->name('import.preview');
    Route::get('/import/preview/direct', [ImportFileController::class, 'preview'])->name('import.preview.direct');
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
