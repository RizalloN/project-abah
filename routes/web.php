<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportFileController;

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

    // Tambahkan baris ini di dalam Route::middleware(['auth', 'role:admin'])->group(...)
    Route::get('/report', [App\Http\Controllers\DataReportController::class, 'index'])->name('report.index');
    Route::post('/report/data', [App\Http\Controllers\DataReportController::class, 'fetchData'])->name('report.data');

    Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/import', [ImportIndexController::class, 'index'])->name('import.index');
    Route::post('/import/upload', [ImportFileController::class, 'upload'])->name('import.upload');
    
    // Route GET untuk menampilkan halaman pilih file
    Route::get('/import/select', function () {
        $files = session('import_files', []);
        return view('import.select-file', compact('files'));
    })->name('import.select'); // Middleware ganda dihapus karena sudah di dalam group

    // Route POST ini untuk menangani form submit dari halaman select-file
    // Route ini akan memanggil method `preview` untuk menampilkan data beserta checkbox
    Route::post('/import/preview', [ImportFileController::class, 'preview'])->name('import.preview');

    // 🔥 UPDATE TERBARU: Route untuk memproses insert ke database setelah user memilih/filter kolom
    Route::post('/import/process', [ImportFileController::class, 'processImport'])->name('import.process');
});

require __DIR__.'/auth.php';