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

// Orchestrate sync operations (daily at 8:00 PM)
Schedule::command('wikidata:sync')
    ->dailyAt('00:00')
    ->onOneServer()
    ->withoutOverlapping();
