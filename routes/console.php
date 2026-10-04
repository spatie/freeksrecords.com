<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('records:sync-discogs')
    ->dailyAt('04:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->onOneServer();
