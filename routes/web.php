<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Diagnostic route - bypasses views entirely
Route::get('/ping', fn () => response('pong', 200, ['Content-Type' => 'text/plain']));

// SEO routes - dynamic robots.txt with resolved APP_URL
Route::get('/robots.txt', [\App\Http\Controllers\SeoController::class, 'robots'])->name('seo.robots');

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Admin page routes (HTML pages only - JSON endpoints are in api.php)
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/monitoring', [\App\Http\Controllers\AdminMonitoringController::class, 'index'])
        ->name('admin.monitoring');

    Route::get('/admin/logs', [\App\Http\Controllers\AdminLogController::class, 'index'])
        ->name('admin.logs');

    Route::get('/admin/jobs', [AdminJobController::class, 'index'])
        ->name('admin.jobs');
});

Route::get('/search', function () {
    return view('search.index');
})->name('search.page');
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
