<?php

use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Diagnostic route - bypasses views entirely
Route::get('/ping', fn () => response('pong', 200, ['Content-Type' => 'text/plain']));

Route::get('/', function () {
    return view('welcome');
});
    
// Admin monitoring routes
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/monitoring', [\App\Http\Controllers\AdminMonitoringController::class, 'index'])
        ->name('admin.monitoring');

    Route::get('/admin/monitoring/data', [\App\Http\Controllers\AdminMonitoringController::class, 'data'])
        ->name('admin.monitoring.data');

    Route::get('/admin/logs', [\App\Http\Controllers\AdminLogController::class, 'index'])
        ->name('admin.logs');

    Route::get('/admin/logs/data', [\App\Http\Controllers\AdminLogController::class, 'data'])
        ->name('admin.logs.data');

    Route::get('/admin/logs/files', [\App\Http\Controllers\AdminLogController::class, 'files'])
        ->name('admin.logs.files');
});

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/search-results', [SearchController::class, 'results'])->name('search.results');
Route::get('/album/{album}', [AlbumController::class, 'show'])->name('album.show');
Route::get('/artist/{artist}', [ArtistController::class, 'show'])->name('artist.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
