<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('syncs the Discogs collection every night', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'records:sync-discogs'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 4 * * *')
        ->and($event->timezone)->toBe('Europe/Brussels');
});
