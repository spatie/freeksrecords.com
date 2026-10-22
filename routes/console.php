<?php

use App\Models\SocialNotification;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [SocialNotification::class]])
    ->hourly()->withoutOverlapping()->onOneServer();

Schedule::command('records:sync-discogs')
    ->dailyAt('04:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('social:sync-collections')
    ->dailyAt('04:15')->timezone('Europe/Brussels')->withoutOverlapping()->onOneServer();

Schedule::command('social:send-digests')
    ->weeklyOn(1, '09:00')->timezone('Europe/Brussels')->withoutOverlapping()->onOneServer();
