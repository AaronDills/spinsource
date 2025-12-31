<?php

namespace App\Console;

use App\Jobs\Incremental\DiscoverChangedArtists;
use App\Jobs\Incremental\DiscoverChangedGenres;
use App\Jobs\Incremental\DiscoverNewArtistIds;
use App\Jobs\Incremental\DiscoverNewGenres;
use App\Jobs\Incremental\RefreshAlbumsForChangedArtists;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Weekly Incremental Sync:
     * Jobs are dispatched in dependency order to the wikidata queue.
     * A single worker processes them sequentially, respecting rate limits.
     *
     * The backfill commands (wikidata:dispatch-seed-*) are still available
     * for initial import or disaster recovery, but are NOT scheduled.
     */
    protected function schedule(Schedule $schedule): void
    {
        /*
        |--------------------------------------------------------------------------
        | Weekly Incremental Wikidata Sync
        |--------------------------------------------------------------------------
        |
        | Runs every Sunday at 2:00 AM. Jobs are dispatched in dependency order:
        |   1. Genres (standalone reference data)
        |   2. Artists (depend on genres for pivot linking)
        |   3. Albums (depend on artists existing)
        |
        | All jobs go to the wikidata queue with a single worker, ensuring
        | sequential processing in dispatch order.
        |
        */
        $schedule->call(fn () => DiscoverNewGenres::dispatch())
            ->weeklyOn(0, '02:00') // Sunday at 2:00 AM
            ->onOneServer()
            ->withoutOverlapping()
            ->then(fn () => DiscoverChangedGenres::dispatch())
            ->then(fn () => DiscoverNewArtistIds::dispatch())
            ->then(fn () => DiscoverChangedArtists::dispatch())
            ->then(fn () => RefreshAlbumsForChangedArtists::dispatch());

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
            ->then(fn () => Artisan::call('scout:queue-import', ['model' => 'App\Models\Artist']))
            ->then(fn () => Artisan::call('scout:queue-import', ['model' => 'App\Models\Album']))
            ->then(fn () => Artisan::call('scout:queue-import', ['model' => 'App\Models\Genre']));
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
