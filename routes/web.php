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

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/import', [ImportIndexController::class, 'index'])->name('import.index');
    Route::post('/import/upload', [ImportFileController::class, 'upload'])->name('import.upload');
    Route::get('/import/select', function () {
        $files = session('import_files', []);
        return view('import.select-file', compact('files'));
    })->middleware(['auth', 'role:admin'])->name('import.select');
});



require __DIR__.'/auth.php';