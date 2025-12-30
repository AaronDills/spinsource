<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Orchestrate sync operations (daily at midnight)
Schedule::command('wikidata:sync --page-size=2000 --artist-batch-size=100')
    ->dailyAt('00:00')
    ->onOneServer()
    ->withoutOverlapping();

// Rebuild search indexes daily at noon to keep Typesense in sync.
Schedule::command('scout:flush', ['App\Models\Artist'])
    ->dailyAt('12:00')
    ->onOneServer()
    ->then(fn () => Artisan::call('scout:flush', ['model' => 'App\Models\Album']))
    ->then(fn () => Artisan::call('scout:flush', ['model' => 'App\Models\Genre']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Artist']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Album']))
    ->then(fn () => Artisan::call('scout:import', ['model' => 'App\Models\Genre']));
