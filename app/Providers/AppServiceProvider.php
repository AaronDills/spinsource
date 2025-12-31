<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAdminGates();
    }

    /**
     * Configure authorization gates for admin dashboards.
     * Uses the same logic as Horizon (is_admin check).
     */
    protected function configureAdminGates(): void
    {
        Gate::define('viewPulse', fn ($user = null) => $user?->is_admin === true);

        LogViewer::auth(fn ($request) => $request->user()?->is_admin === true);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('wikidata-wdqs', function () {
            return Limit::perMinute(
                config('wikidata.requests_per_minute', 30)
            );
        });
    }
}
