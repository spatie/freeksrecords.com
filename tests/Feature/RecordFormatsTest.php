<?php

use App\Jobs\SyncUserCollection;
use App\Models\User;
use App\Support\DiscogsRecordMapper;
use App\Support\RecordCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

it('imports mixed bundles and unknown media without losing descriptions', function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config(['services.discogs.consumer_key' => 'test', 'services.discogs.consumer_secret' => 'secret']);
    $user = socialUser('formats', 'everyone');
    $user->update(['discogs_username' => 'formats', 'discogs_token' => 'access', 'discogs_secret' => 'secret']);
    $mixed = discogsCollectionEntry(101, 201);
    $mixed['basic_information']['master_id'] = 0;
    $mixed['basic_information']['formats'] = [
        ['name' => 'Vinyl', 'qty' => '2', 'descriptions' => ['LP', 'Reissue'], 'text' => 'Blue'],
        ['name' => 'CD', 'qty' => '1'], ['name' => 'Cassette', 'qty' => '1'],
    ];
    $unknown = [...$mixed, 'instance_id' => 102];
    $unknown['basic_information']['formats'] = [['name' => 'DVD', 'descriptions' => ['LP sized sleeve']]];
    Http::fake([
        'api.discogs.com/users/*' => Http::response(['pagination' => ['pages' => 1, 'items' => 2], 'releases' => [$mixed, $unknown]]),
        'api.discogs.com/releases/*' => Http::response(['tracklist' => []]),
    ]);
    (new SyncUserCollection($user->id))->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    $record = $user->records()->where('instance_id', 101)->firstOrFail();
    expect($record->formats())->toBe(['Vinyl', 'CD', 'Cassette'])
        ->and($record->payload['format'])->toBe('2 × Vinyl, CD, Cassette')
        ->and($record->payload['edition'])->toBe('LP, Reissue, Blue');
    expect($user->records()->count())->toBe(2)
        ->and($user->records()->where('instance_id', 102)->firstOrFail()->formats())->toBe([]);
});

it('backfills legacy descriptions without changing dates or activity and recognizes snapshots stored later', function () {
    $user = socialUser('legacyformats');
    foreach (['5 LP', 'CD, Cassette', 'DVD', ''] as $index => $format) {
        $record = socialRecord($user, instance: 100 + $index, activity: false);
        $record->update(['payload' => [...$record->payload, 'format' => $format]]);
    }
    $before = $user->records()->get()->map(fn ($record) => [$record->added_at, $record->updated_at, $record->is_activity])->toJson();
    $migration = require database_path('migrations/2026_10_18_120000_backfill_record_formats.php');
    $migration->up();
    expect($user->records()->get()->map(fn ($record) => [$record->added_at, $record->updated_at, $record->is_activity])->toJson())->toBe($before)
        ->and($user->records()->where('instance_id', 100)->firstOrFail()->formats())->toBe(['Vinyl'])
        ->and($user->records()->where('instance_id', 101)->firstOrFail()->formats())->toBe(['CD', 'Cassette'])
        ->and($user->records()->where('instance_id', 102)->firstOrFail()->formats())->toBe([]);
    $late = socialRecord($user, instance: 200);
    $late->update(['payload' => [...$late->payload, 'format' => '5 LP']]);
    expect($late->fresh()->formats())->toBe(['Vinyl']);
});

it('refreshes known copies from the listing without fetching releases or changing notes dates and activity', function (bool $activity) {
    Http::preventStrayRequests();
    Sleep::fake();
    config(['services.discogs.consumer_key' => 'test', 'services.discogs.consumer_secret' => 'secret']);
    $user = socialUser('knownformats');
    $user->update(['discogs_username' => 'knownformats', 'discogs_token' => 'access', 'discogs_secret' => 'secret', 'collection_imported_at' => now()]);
    $record = socialRecord($user, activity: $activity);
    $record->update(['shared_note' => 'My edition', 'private_note' => 'Gift for a friend']);
    $entry = discogsCollectionEntry($record->instance_id, $record->discogs_release_id);
    $entry['basic_information']['formats'] = [['name' => 'CD', 'descriptions' => ['Remastered']]];
    Http::fake(['api.discogs.com/users/*' => Http::response(['pagination' => ['pages' => 1, 'items' => 1], 'releases' => [$entry]])]);
    $job = new SyncUserCollection($user->id);
    $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    $job->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));
    $fresh = $record->fresh();
    expect($fresh->formats())->toBe(['CD'])->and($fresh->payload['edition'])->toBe('Remastered')
        ->and($fresh->payload['tracks'])->toBe($record->payload['tracks'])
        ->and($fresh->added_at->equalTo($record->added_at))->toBeTrue()
        ->and($fresh->is_activity)->toBe($activity)
        ->and($fresh->shared_note)->toBe('My edition')->and($fresh->private_note)->toBe('Gift for a friend')
        ->and($fresh->toArray())->not->toHaveKey('private_note');
    Http::assertSentCount(2);
})->with([true, false]);

it('shares all media together without a format filter and preserves canonical formats for packaging', function () {
    $user = socialUser('shelfformats', 'everyone');
    foreach (['Vinyl album' => ['Vinyl'], 'CD and tape bundle' => ['CD', 'Cassette'], 'Unknown medium' => []] as $title => $formats) {
        $record = socialRecord($user, $title, instance: $user->records()->count() + 1);
        $record->update(['payload' => [...$record->payload, 'mediaFormats' => $formats]]);
    }
    foreach (['', '?format=CD', '?format=DVD'] as $query) {
        $response = $this->get('/people/shelfformats'.$query)->assertOk()
            ->assertSee('Vinyl album')->assertSee('CD and tape bundle')->assertSee('Unknown medium')
            ->assertDontSee('name="format"', false);
        $summaries = collect($response->inertiaProps('records'))->keyBy('title');
        expect($summaries['Vinyl album']['mediaFormats'])->toBe(['Vinyl'])
            ->and($summaries['CD and tape bundle']['mediaFormats'])->toBe(['CD', 'Cassette'])
            ->and($summaries['Unknown medium']['mediaFormats'])->toBe([]);
    }
});

it('keeps useful legacy descriptions when listing metadata is incomplete', function () {
    $record = socialRecord(socialUser('incompleteformats'));
    $payload = $record->payload;
    expect($record->refreshFormats(['formats' => [['descriptions' => ['LP']]]], app(DiscogsRecordMapper::class)))->toBeFalse()
        ->and($record->fresh()->payload)->toBe($payload);
});

it('seeds all three formats without overwriting edited demo records', function () {
    Http::preventStrayRequests();
    app()->detectEnvironment(fn () => 'local');
    $owner = socialUser('robin', 'friends');
    $legacy = app(DiscogsRecordMapper::class)->record([
        'instance_id' => 900000, 'date_added' => '2026-09-01T12:00:00+00:00',
        'basic_information' => ['id' => 900000, 'title' => 'After the Rain', 'artists' => [['name' => 'The Paper Satellites']], 'year' => 2026, 'labels' => [['name' => 'Synthetic demo release']], 'cover_image' => '', 'formats' => [['name' => 'Vinyl', 'qty' => '1']]],
    ], ['year' => 2026, 'tracklist' => [['title' => 'A Small Beginning', 'position' => 'A1', 'duration' => '4:12']]], []);
    $legacy['cover'] = '/images/demo-sleeve-1.svg';
    $original = $owner->records()->create(['instance_id' => 900000, 'discogs_release_id' => 900000, 'payload' => $legacy, 'added_at' => '2026-09-01 12:00:00', 'is_activity' => false, 'private_note' => 'My gift']);
    $edited = $owner->records()->create(['instance_id' => 900001, 'discogs_release_id' => 900001, 'payload' => [...$legacy, 'title' => 'Tester changed this'], 'added_at' => '2026-09-01 12:00:00', 'is_activity' => false]);
    $this->artisan('social:seed-demo')->assertSuccessful();
    $robin = User::query()->where('handle', 'robin')->firstOrFail();
    expect($original->fresh()->id)->toBe($original->id)
        ->and($original->fresh()->discogs_release_id)->not->toBe(900000)
        ->and($original->fresh()->private_note)->toBe('My gift')
        ->and($original->fresh()->added_at->equalTo($original->added_at))->toBeTrue()
        ->and($original->fresh()->is_activity)->toBeFalse()
        ->and($edited->fresh()->payload)->toBe($edited->payload);
    foreach (['Vinyl', 'CD', 'Cassette'] as $format) {
        expect($robin->records()->get()->contains(fn ($record) => in_array($format, $record->formats(), true)))->toBeTrue();
    }
    $record = $robin->records()->firstOrFail();
    $record->update(['payload' => [...$record->payload, 'edition' => 'My own edit'], 'private_note' => 'Keep this']);
    $this->artisan('social:seed-demo')->assertSuccessful();
    expect($record->fresh()->payload['edition'])->toBe('My own edit')->and($record->fresh()->private_note)->toBe('Keep this')
        ->and($robin->records()->count())->toBe(90);
});
