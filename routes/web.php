<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportFileController;
use App\Http\Controllers\Import\ImportFileBrimoController;
use App\Http\Controllers\RasioCasaDebiturController;

/* * 1. HALAMAN UTAMA (/)
 * Lebih baik di-redirect ke rute login. Jika user sudah login, 
 * middleware bawaan Laravel akan otomatis melemparnya ke /dashboard.
 */
Route::get('/', function () {
    return redirect()->route('login');
});

/*
 * 2. ROUTES LOGIN (PENTING!)
 * Karena Anda memiliki "require __DIR__.'/auth.php';" di paling bawah,
 * Laravel SUDAH memiliki rute GET /login dan POST /login bawaan.
 * Agar tidak bentrok, rute manual AuthController di bawah ini SAYA MATIKAN (comment).
 *
 * NOTE: Jika Anda MEMANG membuat AuthController sendiri secara manual (tidak pakai bawaan),
 * silakan hapus/comment "require __DIR__.'/auth.php';" di paling bawah, 
 * tambahkan "use App\Http\Controllers\AuthController;" di atas, lalu hidupkan kode di bawah ini:
 */
// Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
// Route::post('/login', [AuthController::class, 'login']);


/* * 3. ROUTE LAINNYA
 */
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
Route::get('/report/optimalisasi-digital/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'index'])->name('report.brimo');
Route::post('/report/data/brimo', [App\Http\Controllers\PerformanceBrimoController::class, 'fetchData'])->name('report.data.brimo');

// 🔥 ROUTE REKENING TRANSAKSI DEBITUR
Route::get('/report/rekening-transaksi-debitur', [RasioCasaDebiturController::class, 'index'])->name('report.rasiocasa.debitur');
Route::post('/report/data/rasiocasa', [RasioCasaDebiturController::class, 'fetchData'])->name('report.data.rasiocasa');
Route::view('/report/peningkatan-payroll-berkualitas/kinerja-new-payroll', 'report.kinerja-new-payroll')->name('report.kinerja.newpayroll');

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
        Route::get('/prepare-preview', [App\Http\Controllers\Import\ImportExcelController::class, 'preparePreviewStream'])->name('import.excel.prepare-preview');
        Route::post('/dailyloan', [App\Http\Controllers\Import\ImportExcelController::class, 'importDailyLoan'])->name('import.excel.dailyloan');
        Route::post('/simpanan', [App\Http\Controllers\Import\ImportExcelController::class, 'importSimpanan'])->name('import.excel.simpanan');
    });
});

// Sistem auth bawaan Laravel diletakkan di akhir
require __DIR__.'/auth.php';
