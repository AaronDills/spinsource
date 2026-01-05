<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AdminJobController;
use Illuminate\Support\Facades\Route;

// Diagnostic route - bypasses views entirely
Route::get('/ping', fn () => response('pong', 200, ['Content-Type' => 'text/plain']));

Route::get('/', function () {
    return view('welcome');
})->name('home');

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

    Route::post('/admin/monitoring/clear-failed', [\App\Http\Controllers\AdminMonitoringController::class, 'clearFailedJobs'])
        ->name('admin.monitoring.clear-failed');

    Route::get('/admin/jobs', [AdminJobController::class, 'index'])
        ->name('admin.jobs');

    Route::get('/admin/jobs/data', [AdminJobController::class, 'data'])
        ->name('admin.jobs.data');

    Route::post('/admin/jobs/dispatch', [AdminJobController::class, 'dispatch'])
        ->name('admin.jobs.dispatch');

    Route::post('/admin/jobs/cancel', [AdminJobController::class, 'cancel'])
        ->name('admin.jobs.cancel');
});

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/search-results', [SearchController::class, 'results'])->name('search.results');
Route::get('/album/{album}', [AlbumController::class, 'show'])->name('album.show');
Route::get('/artist/{artist}', [ArtistController::class, 'show'])->name('artist.show');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Account routes
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/account/reviews', [AccountController::class, 'reviews'])->name('account.reviews');
    Route::get('/account/reviews/{rating}/edit', [AccountController::class, 'editReview'])->name('account.reviews.edit');
    Route::patch('/account/reviews/{rating}', [AccountController::class, 'updateReview'])->name('account.reviews.update');
    Route::delete('/account/reviews/{rating}', [AccountController::class, 'destroyReview'])->name('account.reviews.destroy');
    Route::get('/account/statistics', [AccountController::class, 'statistics'])->name('account.statistics');
});

require __DIR__.'/auth.php';
