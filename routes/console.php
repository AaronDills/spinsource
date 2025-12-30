<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Wikidata Sync Schedule
|--------------------------------------------------------------------------
|
| Dispatch jobs seed data from Wikidata in the early morning hours.
| The sync job runs in the evening to orchestrate any additional operations.
|
*/

// Seed genres and artists from Wikidata (daily at 3:00 AM)
Schedule::command('wikidata:dispatch-seed-genres')
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wikidata-seed-genres.log'));

Schedule::command('wikidata:dispatch-seed-artists')
    ->dailyAt('00:00')
    ->onOneServer()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wikidata-seed-artists.log'));

// Orchestrate sync operations (daily at 8:00 PM)
Schedule::command('wikidata:sync')
    ->dailyAt('20:00')
    ->onOneServer()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wikidata-sync.log'));
