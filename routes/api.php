<?php

use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\AdminLogController;
use App\Http\Controllers\AdminMonitoringController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::get('/search', [SearchController::class, 'search'])->name('api.search');

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| JSON endpoints for admin dashboard functionality.
| Requires authentication.
|
*/

Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    // Monitoring
    Route::get('/monitoring/data', [AdminMonitoringController::class, 'data'])
        ->name('api.admin.monitoring.data');
    Route::post('/monitoring/clear-failed', [AdminMonitoringController::class, 'clearFailedJobs'])
        ->name('api.admin.monitoring.clear-failed');

    // Logs
    Route::get('/logs/data', [AdminLogController::class, 'data'])
        ->name('api.admin.logs.data');
    Route::get('/logs/files', [AdminLogController::class, 'files'])
        ->name('api.admin.logs.files');

    // Jobs
    Route::get('/jobs/data', [AdminJobController::class, 'data'])
        ->name('api.admin.jobs.data');
    Route::post('/jobs/dispatch', [AdminJobController::class, 'dispatch'])
        ->name('api.admin.jobs.dispatch');
    Route::post('/jobs/cancel', [AdminJobController::class, 'cancel'])
        ->name('api.admin.jobs.cancel');
    Route::post('/jobs/failed/clear', [AdminJobController::class, 'clearFailed'])
        ->name('api.admin.jobs.failed.clear');
    Route::post('/jobs/failed/retry', [AdminJobController::class, 'retryFailed'])
        ->name('api.admin.jobs.failed.retry');
});
