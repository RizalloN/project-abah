<?php

use App\Http\Controllers\Input\InputRekananController;
use App\Http\Controllers\Input\BodBocController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardPinjamanReportController;
use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportFileController;
use App\Http\Controllers\Import\ImportFileBrimoController;
use App\Http\Controllers\Import\ImportCasaBrilinkController;
use App\Http\Controllers\Import\ImportCleanupController;
use App\Http\Controllers\Import\ImportPerformancePisPerProdukController;
use App\Http\Controllers\Import\ImportReportPhController;
use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Http\Controllers\RekeningDormantController;
use App\Http\Controllers\RasioCasaDebiturController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/debug-upload-limits', function () {
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
        'content_length' => request()->server('CONTENT_LENGTH'),
    ];
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// 🔥 ROUTES FOR PERFORMANCE REPORTS
Route::get('/report/dashboard-pinjaman', [DashboardPinjamanReportController::class, 'index'])
    ->middleware('auth')
    ->name('report.dashboard-pinjaman');
Route::get('/report/dashboard-pinjaman/filters', [DashboardPinjamanReportController::class, 'filters'])
    ->middleware('auth')
    ->name('report.dashboard-pinjaman.filters');
Route::get('/report/dashboard-pinjaman/data', [DashboardPinjamanReportController::class, 'data'])
    ->middleware('auth')
    ->name('report.dashboard-pinjaman.data');
Route::get('/report/optimalisasi-digital/edc', [App\Http\Controllers\DataReportController::class, 'performanceEdc'])->name('report.edc');
Route::get('/report/optimalisasi-digital/qris', [App\Http\Controllers\DataReportController::class, 'performanceQris'])->name('report.qris');
Route::get('/report/optimalisasi-digital/brilink', [App\Http\Controllers\DataReportController::class, 'performanceBrilink'])->name('report.brilink');
Route::get('/report/optimalisasi-digital/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'index'])->name('report.brimo');
Route::post('/report/data/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'fetchData'])->name('report.data.brimo');
Route::get('/report/kolaborasi-perusahaan-anak/program-referral-partner-perusahaan-anak', [App\Http\Controllers\DataReportController::class, 'programReferralPartnerPerusahaanAnak'])->name('report.kolaborasi.referral');
Route::get('/report/kolaborasi-perusahaan-anak/nasabah-prioritas-bod-boc', [App\Http\Controllers\DataReportController::class, 'nasabahPrioritasBodBoc'])->name('report.kolaborasi.bodboc');

// 🔥 ROUTE REKENING TRANSAKSI DEBITUR
Route::get('/report/rekening-transaksi-debitur', [RasioCasaDebiturController::class, 'index'])->name('report.rasiocasa.debitur');
Route::post('/report/data/rasiocasa', [RasioCasaDebiturController::class, 'fetchData'])->name('report.data.rasiocasa');
Route::get('/report/rekening-transaksi-debitur/rekening-dormant', [RekeningDormantController::class, 'index'])->name('report.rekening-dormant');
Route::get('/report/rekening-transaksi-debitur/rekening-dormant/filters', [RekeningDormantController::class, 'filters'])->name('report.rekening-dormant.filters');
Route::post('/report/data/rekening-dormant', [RekeningDormantController::class, 'fetchData'])->name('report.data.rekening-dormant');
Route::get('/report/peningkatan-payroll-berkualitas/kinerja-new-payroll', [App\Http\Controllers\DataReportController::class, 'performanceNewPayroll'])->name('report.kinerja.newpayroll');
Route::post('/report/data/newpayroll', [App\Http\Controllers\DataReportController::class, 'fetchNewPayrollData'])->name('report.data.newpayroll');

Route::post('/report/data', [App\Http\Controllers\DataReportController::class, 'fetchData'])->name('report.data');

// 🔥 ADMIN ROUTES
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/input-data', [InputRekananController::class, 'index'])->name('input.index');
    Route::post('/input-data', [InputRekananController::class, 'store'])->name('input.store');
    Route::post('/input-data/import-template', [InputRekananController::class, 'importTemplate'])->name('input.import-template');
    Route::get('/input-data/import-preview', [InputRekananController::class, 'previewImport'])->name('input.import-preview');
    Route::post('/bod-boc/import-template', [BodBocController::class, 'importTemplate'])->name('bod-boc.import-template');
    Route::get('/bod-boc/import-preview', [BodBocController::class, 'previewImport'])->name('bod-boc.import-preview');
    Route::post('/bod-boc/store', [BodBocController::class, 'store'])->name('bod-boc.store');
    Route::get('/import', [ImportIndexController::class, 'index'])->name('import.index');
    Route::get('/report-management', [ImportIndexController::class, 'reportManagement'])->name('report-management.index');
    Route::get('/import/upload-limits', [ImportIndexController::class, 'uploadLimits'])->name('import.upload-limits');
    Route::get('/import/template', [ImportIndexController::class, 'downloadTemplate'])->name('import.template');
    Route::post('/import/report-management/data', [ImportIndexController::class, 'reportManagementData'])->name('import.report-management.data');
    Route::post('/import/report-management/delete', [ImportIndexController::class, 'deleteManagedReportRows'])->name('import.report-management.delete');
    Route::post('/import/upload', [ImportFileController::class, 'upload'])->name('import.upload');
    
    // Route GET untuk menampilkan halaman pilih file
    Route::get('/import/select', function () {
        $files = session('import_files', []);
        return view('import.select-file', compact('files'));
    })->name('import.select');

    // Route POST untuk preview
    Route::post('/import/preview', [ImportFileController::class, 'preview'])->name('import.preview');
    Route::get('/import/preview/direct', [ImportFileController::class, 'preview'])->name('import.preview.direct');
    Route::post('/import/casa-brilink/upload', [ImportCasaBrilinkController::class, 'upload'])->name('import.casabrilink.upload');
    Route::get('/import/casa-brilink/preview', [ImportCasaBrilinkController::class, 'preview'])->name('import.casabrilink.preview');
    Route::post('/import/casa-brilink/preview', [ImportCasaBrilinkController::class, 'preview'])->name('import.casabrilink.preview.refresh');
    Route::post('/import/casa-brilink/init', [ImportCasaBrilinkController::class, 'initImport'])->name('import.casabrilink.init');
    Route::get('/import/casa-brilink/stream', [ImportCasaBrilinkController::class, 'processImportStream'])->name('import.casabrilink.stream');
    Route::post('/import/casa-brilink/process', [ImportCasaBrilinkController::class, 'processImport'])->name('import.casabrilink.process');
    Route::post('/import/performance-pis/upload', [ImportPerformancePisPerProdukController::class, 'upload'])->name('import.performancepis.upload');
    Route::get('/import/performance-pis/preview', [ImportPerformancePisPerProdukController::class, 'preview'])->name('import.performancepis.preview');
    Route::post('/import/performance-pis/preview', [ImportPerformancePisPerProdukController::class, 'preview'])->name('import.performancepis.preview.refresh');
    Route::post('/import/performance-pis/init', [ImportPerformancePisPerProdukController::class, 'initImport'])->name('import.performancepis.init');
    Route::get('/import/performance-pis/stream', [ImportPerformancePisPerProdukController::class, 'processImportStream'])->name('import.performancepis.stream');
    Route::post('/import/performance-pis/process', [ImportPerformancePisPerProdukController::class, 'processImport'])->name('import.performancepis.process');
    Route::post('/import/report-ph/upload', [ImportReportPhController::class, 'upload'])->name('import.reportph.upload');
    Route::get('/import/report-ph/preview', [ImportReportPhController::class, 'preview'])->name('import.reportph.preview');
    Route::post('/import/report-ph/preview', [ImportReportPhController::class, 'preview'])->name('import.reportph.preview.refresh');
    Route::post('/import/report-ph/init', [ImportReportPhController::class, 'initImport'])->name('import.reportph.init');
    Route::get('/import/report-ph/stream', [ImportReportPhController::class, 'processImportStream'])->name('import.reportph.stream');
    Route::post('/import/report-ph/process', [ImportReportPhController::class, 'processImport'])->name('import.reportph.process');

    // ENGINE ANTRIAN EXCEL
    Route::post('/import-excel/init', [App\Http\Controllers\Import\ImportExcelController::class, 'initExcelImport'])->name('import.excel.init');
    Route::get('/import-excel/stream', [App\Http\Controllers\Import\ImportExcelController::class, 'processExcelStream'])->name('import.excel.stream');
    Route::post('/import-excel/chunk', [App\Http\Controllers\Import\ImportExcelController::class, 'processExcelChunk'])->name('import.excel.chunk');
    
    // ENGINE ANTRIAN CSV / TXT
    Route::post('/import/init', [ImportFileController::class, 'initImport'])->name('import.init');
    Route::get('/import/stream', [ImportFileController::class, 'processImportStream'])->name('import.stream');

    // Process import
    Route::post('/import/process', [ImportFileController::class, 'processImport'])->name('import.process');
    Route::post('/import/cleanup-artifacts', [ImportCleanupController::class, 'cleanupStaleArtifacts'])->name('import.cleanup-artifacts');

    // =======================================================
    // ROUTE IMPORT BRIMO (USER BRIMO RPT V2 & USER BRIMO FIN)
    // =======================================================
    Route::post('/import/brimo/upload', [ImportFileBrimoController::class, 'upload'])->name('import.brimo.upload');
    Route::post('/import/brimo/preview', [ImportFileBrimoController::class, 'preview'])->name('import.brimo.preview');
    Route::post('/import/brimo/process', [ImportFileBrimoController::class, 'processImport'])->name('import.brimo.process');

    // =======================================================
    // ROUTE IMPORT EXCEL (DAILY LOAN / SIMPANAN MULTI PN)
    // =======================================================
    Route::prefix('import-excel')->group(function () {
        Route::post('/upload', [App\Http\Controllers\Import\ImportExcelController::class, 'uploadExcel'])->name('import.excel.upload');
        Route::get('/preview', [App\Http\Controllers\Import\ImportExcelController::class, 'previewExcel'])->name('import.excel.preview');
        Route::get('/prepare-preview', [App\Http\Controllers\Import\ImportExcelController::class, 'preparePreviewStream'])->name('import.excel.prepare-preview');
    });

    Route::prefix('import-excel/daily-loan-dinamis')->group(function () {
        Route::post('/upload', [App\Http\Controllers\Import\ImportExcelController::class, 'uploadDailyLoanExcel'])->name('import.dailyloan.upload');
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

// Sistem auth bawaan Laravel diletakkan di akhir
require __DIR__.'/auth.php';
