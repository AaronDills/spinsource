<?php

use App\Http\Controllers\Admin\DatabaseViewerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/database', [DatabaseViewerController::class, 'index'])->name('database.index');
    Route::get('/database/query', [DatabaseViewerController::class, 'query'])->name('database.query');
    Route::post('/database/query', [DatabaseViewerController::class, 'query']);
    Route::get('/database/{table}', [DatabaseViewerController::class, 'show'])->name('database.show');
});

require __DIR__.'/auth.php';
