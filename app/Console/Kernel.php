<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Weekly Incremental Sync (wikidata:sync-weekly):
     * - Discovers NEW entities added since last checkpoint (O-ID cursor)
     * - Discovers CHANGED entities since last run (schema:dateModified)
     * - Dispatches enrichment jobs only for deltas
     * - Much faster than full backfill, generates fewer jobs/events
     * - Suitable for weekly runs with Laravel Nightwatch monitoring
     *
     * The old backfill commands (wikidata:dispatch-seed-*) are still available
     * for initial import or disaster recovery, but are NOT scheduled.
     */
    protected function schedule(Schedule $schedule): void
    {
        /*
        |--------------------------------------------------------------------------
        | Weekly Incremental Wikidata Sync
        |--------------------------------------------------------------------------
        |
        | Runs every Sunday at 2:00 AM to:
        | 1. Discover new genres and changed genres
        | 2. Discover new artists and changed artists
        | 3. Refresh albums for changed artists
        |
        | This incremental approach:
        | - Reduces weekly runtime from hours to minutes
        | - Limits job/event volume for observability tools (Nightwatch)
        | - Uses checkpoints stored in ingestion_checkpoints table
        |
        */
        $schedule->command('wikidata:sync-weekly')
            ->weeklyOn(0, '02:00') // Sunday at 2:00 AM
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/wikidata-sync.log'));

        /*
        |--------------------------------------------------------------------------
        | Search Index Rebuild
        |--------------------------------------------------------------------------
        |
        | Rebuild Typesense search indexes after weekly sync completes.
        | Runs on Sunday at 6:00 AM (after sync should be done).
        |
        */
        $schedule->command('scout:flush', ['App\Models\Artist'])
            ->weeklyOn(0, '06:00') // Sunday at 6:00 AM
            ->onOneServer()
            ->then(fn () => Artisan::call('scout:flush', ['model' => 'App\Models\Album']))
            ->then(fn () => Artisan::call('scout:flush', ['model' => 'App\Models\Genre']))
            ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Artist']))
            ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Album']))
            ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Genre']));
    }

    /**
     * Register the schedule from routes/console.php context.
     * Required for Laravel 12 bootstrapping compatibility.
     */
    public static function registerSchedule(): void
    {
        app(static::class)->schedule(app(Schedule::class));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
