<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportFileController;
use App\Http\Controllers\Import\ImportFileBrimoController;

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/debug-upload-limits', function () {
    return [
        'sapi' => PHP_SAPI,
        'loaded_ini' => php_ini_loaded_file(),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'memory_limit' => ini_get('memory_limit'),
        'content_length' => request()->server('CONTENT_LENGTH'),
    ];
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// 🔥 ROUTES FOR PERFORMANCE REPORTS
Route::get('/report/optimalisasi-digital/edc', [App\Http\Controllers\DataReportController::class, 'performanceEdc'])->name('report.edc');
Route::get('/report/optimalisasi-digital/qris', [App\Http\Controllers\DataReportController::class, 'performanceQris'])->name('report.qris');
Route::get('/report/optimalisasi-digital/brilink', [App\Http\Controllers\DataReportController::class, 'performanceBrilink'])->name('report.brilink');
Route::get('/report/optimalisasi-digital/brimo', [App\Http\Controllers\DataReportController::class, 'performanceBrimo'])->name('report.brimo');

// 🔥 ROUTE REKENING TRANSAKSI DEBITUR
Route::get('/report/rekening-transaksi-debitur', [App\Http\Controllers\DataReportController::class, 'rasioCasaDebitur'])->name('report.rasiocasa.debitur');

Route::post('/report/data', [App\Http\Controllers\DataReportController::class, 'fetchData'])->name('report.data');


// 🔥 ADMIN ROUTES
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/import', [ImportIndexController::class, 'index'])->name('import.index');
    Route::post('/import/upload', [ImportFileController::class, 'upload'])->name('import.upload');
    
    // Route GET untuk menampilkan halaman pilih file
    Route::get('/import/select', function () {
        $files = session('import_files', []);
        return view('import.select-file', compact('files'));
    })->name('import.select');

    // Route POST untuk preview
    Route::post('/import/preview', [ImportFileController::class, 'preview'])->name('import.preview');

    // ENGINE ANTRIAN EXCEL
    Route::post('/import-excel/init', [App\Http\Controllers\Import\ImportExcelController::class, 'initExcelImport'])->name('import.excel.init');
    Route::get('/import-excel/stream', [App\Http\Controllers\Import\ImportExcelController::class, 'processExcelStream'])->name('import.excel.stream');
    Route::post('/import-excel/chunk', [App\Http\Controllers\Import\ImportExcelController::class, 'processExcelChunk'])->name('import.excel.chunk');
    Route::post('/import-excel/finish', [App\Http\Controllers\Import\ImportExcelController::class, 'finishExcelImport'])->name('import.excel.finish');
    
    // Process import
    Route::post('/import/process', [ImportFileController::class, 'processImport'])->name('import.process');

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
        Route::post('/dailyloan', [App\Http\Controllers\Import\ImportExcelController::class, 'importDailyLoan'])->name('import.excel.dailyloan');
        Route::post('/simpanan', [App\Http\Controllers\Import\ImportExcelController::class, 'importSimpanan'])->name('import.excel.simpanan');
    });
});

require __DIR__.'/auth.php';

