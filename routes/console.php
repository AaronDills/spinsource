<?php

use Illuminate\Support\Facades\Artisan;
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

// Orchestrate sync operations (daily at 8:00 PM)
Schedule::command('wikidata:sync')
    ->dailyAt('00:00')
    ->onOneServer()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Scout Index Sync Schedule
|--------------------------------------------------------------------------
|
| Rebuild search indexes daily at noon to keep Typesense in sync.
|
*/

Schedule::command('scout:flush', ['App\Models\Artist'])
    ->dailyAt('12:00')
    ->onOneServer()
    ->then(fn () => Artisan::call('scout:flush', ['model' => 'App\Models\Album']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Artist']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Album']));
