<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Wikidata Sync Schedule
|--------------------------------------------------------------------------
|
| Jobs must run in dependency order:
| 1. Genres - standalone reference data (no dependencies)
| 2. Artists - depends on genres for artist_genre pivot linking
| 3. Albums - depends on artists existing in local DB
|
| Each job is paginated and self-dispatches until complete, so we schedule
| them on different days to ensure the previous job finishes first.
|
*/

// Day 1: Seed genres (standalone, no dependencies)
Schedule::command('wikidata:dispatch-seed-genres --page-size=500')
    ->weeklyOn(1, '00:00') // Monday midnight
    ->onOneServer()
    ->withoutOverlapping();

// Day 2: Seed artists (depends on genres for pivot linking)
Schedule::command('wikidata:dispatch-seed-artists --page-size=2000 --batch-size=100')
    ->weeklyOn(3, '00:00') // Wednesday midnight
    ->onOneServer()
    ->withoutOverlapping();

// Day 3: Seed albums (depends on artists existing)
Schedule::command('wikidata:dispatch-seed-albums --artist-batch-size=25')
    ->weeklyOn(5, '00:00') // Friday midnight
    ->onOneServer()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Search Index Rebuild
|--------------------------------------------------------------------------
|
| Rebuild Typesense search indexes after data sync completes.
| Runs on Sunday to ensure all Wikidata syncs have completed.
|
*/

Schedule::command('scout:flush', ['App\Models\Artist'])
    ->weeklyOn(0, '00:00') // Sunday midnight
    ->onOneServer()
    ->then(fn () => Artisan::call('scout:flush', ['model' => 'App\Models\Album']))
    ->then(fn () => Artisan::call('scout:flush', ['model' => 'App\Models\Genre']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Artist']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Album']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Genre']));
