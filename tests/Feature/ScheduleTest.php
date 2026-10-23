<?php

use App\Jobs\SyncUserCollection;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;

it('syncs the Discogs collection every night', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'records:sync-discogs'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 4 * * *')
        ->and($event->timezone)->toBe('Europe/Brussels');
});

it('schedules full reconciliation nightly and quick additions checks hourly', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains($event->command, 'social:sync-collections'))->values();

    expect($events)->toHaveCount(2)
        ->and($events[0]->expression)->toBe('15 4 * * *')
        ->and($events[1]->command)->toContain('--quick')
        ->and($events[1]->expression)->toBe('30 * * * *')
        ->and($events[1]->timezone)->toBe('Europe/Brussels');
});

it('queues the correct sync mode only for verified connected collectors', function (bool $quick) {
    Bus::fake();
    $connected = socialUser('connected');
    $connected->update(['discogs_token' => 'access']);
    socialUser('unverified')->update(['discogs_token' => 'access', 'email_verified_at' => null]);
    socialUser('disconnected');

    $this->artisan('social:sync-collections', $quick ? ['--quick' => true] : [])->assertSuccessful();

    Bus::assertDispatchedTimes(SyncUserCollection::class, 1);
    Bus::assertDispatched(SyncUserCollection::class, fn ($job) => $job->userId === $connected->id && $job->full === ! $quick);
})->with([true, false]);
