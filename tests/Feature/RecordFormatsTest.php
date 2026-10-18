<?php

use App\Jobs\SyncUserCollection;
use App\Models\CollectionRecord;
use App\Models\User;
use App\Support\DiscogsRecordMapper;
use App\Support\RecordCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

it('imports mixed bundles and unknown media and filters by canonical media without losing descriptions', function () {
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
    foreach (['Vinyl', 'CD', 'Cassette'] as $format) {
        expect($user->records()->ofFormat($format)->pluck('instance_id')->all())->toBe([101]);
    }
    expect($user->records()->ofFormat('')->count())->toBe(2)
        ->and($user->records()->where('instance_id', 102)->firstOrFail()->formats())->toBe([]);
});

it('backfills legacy descriptions without changing dates or activity and filters snapshots stored later', function () {
    $user = socialUser('legacyformats');
    foreach (['5 LP', 'CD, Cassette', 'DVD', ''] as $index => $format) {
        $record = socialRecord($user, instance: 100 + $index, activity: false);
        $record->update(['payload' => [...$record->payload, 'format' => $format]]);
    }
    $before = $user->records()->get()->map(fn ($record) => [$record->added_at, $record->updated_at, $record->is_activity])->toJson();
    $migration = require database_path('migrations/2026_10_18_120000_backfill_record_formats.php');
    $migration->up();
    expect($user->records()->get()->map(fn ($record) => [$record->added_at, $record->updated_at, $record->is_activity])->toJson())->toBe($before)
        ->and($user->records()->ofFormat('Vinyl')->pluck('instance_id')->all())->toBe([100])
        ->and($user->records()->ofFormat('CD')->pluck('instance_id')->all())->toBe([101])
        ->and($user->records()->ofFormat('Cassette')->pluck('instance_id')->all())->toBe([101])
        ->and($user->records()->where('instance_id', 102)->firstOrFail()->formats())->toBe([]);
    $late = socialRecord($user, instance: 200);
    $late->update(['payload' => [...$late->payload, 'format' => '5 LP']]);
    expect($user->records()->ofFormat('Vinyl')->orderBy('instance_id')->pluck('instance_id')->all())->toBe([100, 200]);
    expect(CollectionRecord::query()->where('user_id', $user->id + 1)->ofFormat('Vinyl')->count())->toBe(0);
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

it('does not expose a format selector for inaccessible shelves', function () {
    $user = socialUser('privateformats');
    socialRecord($user);
    $this->get(route('profile', $user->handle))->assertOk()->assertDontSee('name="format"', false)->assertDontSee('records, one at a time');
});

it('filters visible collection pages by format and rejects unsupported selections', function () {
    $user = socialUser('shelfformats', 'everyone');
    foreach (['Vinyl album' => ['Vinyl'], 'CD and tape bundle' => ['CD', 'Cassette'], 'Unknown medium' => []] as $title => $formats) {
        $record = socialRecord($user, $title, instance: $user->records()->count() + 1);
        $record->update(['payload' => [...$record->payload, 'mediaFormats' => $formats]]);
    }
    $this->get('/people/shelfformats')->assertOk()->assertSee('Vinyl album')->assertSee('CD and tape bundle')->assertSee('Unknown medium');
    foreach (['CD', 'Cassette'] as $format) {
        $this->get('/people/shelfformats?format='.$format)->assertOk()->assertSee('CD and tape bundle')->assertDontSee('Vinyl album')->assertDontSee('Unknown medium');
    }
    $this->get('/people/shelfformats?format=Vinyl')->assertOk()->assertSee('Vinyl album')->assertDontSee('CD and tape bundle');
    $this->getJson('/people/shelfformats?format=DVD')->assertUnprocessable();
});

it('keeps useful legacy descriptions when listing metadata is incomplete', function () {
    $record = socialRecord(socialUser('incompleteformats'));
    $payload = $record->payload;
    expect($record->refreshFormats(['formats' => [['descriptions' => ['LP']]]], app(DiscogsRecordMapper::class)))->toBeFalse()
        ->and($record->fresh()->payload)->toBe($payload);
});

it('seeds all three formats without overwriting edited demo records', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->artisan('social:seed-demo')->assertSuccessful();
    $robin = User::query()->where('handle', 'robin')->firstOrFail();
    expect($robin->records()->get()->map(fn ($record) => $record->formats())->all())->toBe([['Vinyl'], ['CD'], ['Cassette']]);
    $record = $robin->records()->firstOrFail();
    $record->update(['payload' => [...$record->payload, 'edition' => 'My own edit'], 'private_note' => 'Keep this']);
    $this->artisan('social:seed-demo')->assertSuccessful();
    expect($record->fresh()->payload['edition'])->toBe('My own edit')->and($record->fresh()->private_note)->toBe('Keep this')
        ->and($robin->records()->count())->toBe(3);
});
