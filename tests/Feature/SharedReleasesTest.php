<?php

use App\Jobs\SyncUserCollection;
use App\Models\CollectionRecord;
use App\Models\Release;
use App\Models\User;
use App\Support\DiscogsRecordMapper;
use App\Support\RecordCollection;
use App\Support\RecordCoverImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

it('migrates duplicate releases without losing copies, enrichment, artwork or personal data', function () {
    Http::preventStrayRequests();
    $connection = DB::getDefaultConnection();
    config(['database.connections.release_migration' => [...config('database.connections.sqlite'), 'database' => ':memory:']]);
    DB::setDefaultConnection('release_migration');

    try {
        $normalization = database_path('migrations/2026_10_23_120000_share_releases_and_artists.php');
        $browsing = [database_path('migrations/2026_10_23_100000_index_collection_browsing.php'), database_path('migrations/2026_10_23_110000_cover_decade_browse_queries.php')];
        $this->artisan('migrate', [
            '--database' => 'release_migration', '--realpath' => true,
            '--path' => array_values(array_diff(glob(database_path('migrations/*.php')), [$normalization, ...$browsing])),
        ])->assertSuccessful();
        $first = socialUser('first');
        $second = socialUser('second');
        $base = [...app(RecordCollection::class)->snapshot()[0],
            'id' => 201, 'title' => 'Ambient 1', 'cover' => 'https://i.discogs.com/original.jpg',
            'spotifyUrl' => 'https://open.spotify.com/search/Ambient', 'spotifySearch' => true,
            'credits' => ['release' => [], 'tracks' => []],
        ];
        $enriched = [...$base,
            'cover' => '/storage/records/201.jpg', 'releaseNotes' => 'First pressing.',
            'coverPreview' => app(RecordCoverImages::class)->preview(app(RecordCollection::class)->snapshot()[0]['cover']),
            'credits' => ['release' => [['name' => 'Brian Eno', 'role' => 'Producer']], 'tracks' => []],
            'artistBios' => [['id' => 634, 'name' => 'Brian Eno', 'profile' => 'Composer and producer.']],
            'spotifyUrl' => 'https://open.spotify.com/album/ambient', 'spotifySearch' => false,
        ];
        $otherPressing = [...$enriched, 'id' => 202, 'title' => 'Another pressing', 'cover' => '/storage/records/202.jpg'];
        unset($otherPressing['coverPreview']);
        foreach ([$base, $enriched, $otherPressing, [...$otherPressing, 'cover' => '/storage/alternate-202.jpg', 'coverPreview' => $enriched['coverPreview']]] as $index => $payload) {
            DB::table('collection_records')->insert([
                'id' => 100 + $index, 'user_id' => $index === 0 ? $first->id : $second->id,
                'instance_id' => 300 + $index, 'discogs_release_id' => $payload['id'],
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'added_at' => '2026-10-01 12:00:00', 'updated_at' => '2026-10-02 12:00:00',
                'is_activity' => $index !== 0, 'shared_note' => "Copy {$index}", 'private_note' => "Price {$index}",
            ]);
        }
        DB::table('comments')->insert([
            'user_id' => $first->id, 'collection_record_id' => 101, 'body' => 'Great album!',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('appreciations')->insert(['user_id' => $first->id, 'collection_record_id' => 101]);
        $this->artisan('migrate', ['--database' => 'release_migration', '--realpath' => true, '--path' => $browsing])->assertSuccessful();
        $copies = DB::table('collection_records')->get()->map(fn ($copy) => (array) $copy)->map(function ($copy) {
            unset($copy['payload']);

            return $copy;
        })->all();

        $migration = require $normalization;
        $migration->up();

        expect(Schema::hasColumn('collection_records', 'payload'))->toBeFalse()
            ->and(DB::table('collection_records')->get()->map(fn ($copy) => (array) $copy)->all())->toBe($copies);
        $this->assertDatabaseCount('releases', 2);
        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('artist_release', 2);
        $record = CollectionRecord::query()->findOrFail(100);
        expect($record->payload['cover'])->toBe('/storage/records/201.jpg')
            ->and($record->payload['coverPreview'])->toBe($enriched['coverPreview'])
            ->and($record->payload['tracks'])->toBe($base['tracks'])
            ->and($record->payload['releaseNotes'])->toBe('First pressing.')
            ->and($record->payload['credits'])->toBe($enriched['credits'])
            ->and($record->payload['artistBios'][0]['profile'])->toBe('Composer and producer.')
            ->and($record->payload['spotifyUrl'])->toBe('https://open.spotify.com/album/ambient')
            ->and($record->payload['spotifySearch'])->toBeFalse()
            ->and($record->release->info_imported_at)->not->toBeNull()
            ->and(CollectionRecord::query()->findOrFail(102)->payload['title'])->toBe('Another pressing')
            ->and(CollectionRecord::query()->findOrFail(102)->payload)->not->toHaveKey('coverPreview');
        $this->assertDatabaseHas('comments', ['collection_record_id' => 101, 'body' => 'Great album!']);
        $this->assertDatabaseHas('appreciations', ['collection_record_id' => 101, 'user_id' => $first->id]);
        Http::assertNothingSent();
    } finally {
        DB::purge('release_migration');
        DB::setDefaultConnection($connection);
    }
});

it('shares release updates and artwork across owners while keeping copies independent', function () {
    $first = socialRecord(socialUser('first'), releaseId: 201);
    $second = socialRecord(socialUser('second'), date: '2026-10-20 10:00:00', instance: 456, releaseId: 201);
    $second->update(['shared_note' => 'A gift', 'private_note' => '€20']);
    $first->update(['payload' => [...$first->payload, 'cover' => '/storage/records/201.jpg', 'releaseNotes' => 'Shared notes.']]);
    expect($second->refreshFormats(['formats' => [['name' => 'CD', 'qty' => '1']]], app(DiscogsRecordMapper::class)))->toBeTrue();

    $this->assertDatabaseCount('releases', 1);
    expect($second->fresh()->payload['cover'])->toBe('/storage/records/201.jpg')
        ->and($second->fresh()->formats())->toBe(['CD'])
        ->and($second->fresh()->payload['releaseNotes'])->toBe('Shared notes.')
        ->and($second->fresh()->payload['instanceId'])->toBe(456)
        ->and($second->fresh()->payload['addedAt'])->toBe('2026-10-20T10:00:00+00:00')
        ->and(Release::query()->firstOrFail()->data)->not->toHaveKeys(['instanceId', 'addedAt', 'private_note', 'shared_note']);
    $first->user->delete();

    $this->assertDatabaseCount('releases', 1);
    $this->assertDatabaseCount('collection_records', 1);
    expect($second->fresh()->private_note)->toBe('€20')
        ->and($second->fresh()->shared_note)->toBe('A gift')
        ->and($second->fresh()->payload['cover'])->toBe('/storage/records/201.jpg');
});

it('reuses stored releases and artist biographies when another owner imports their collection', function () {
    Http::preventStrayRequests();
    Sleep::fake();
    Sleep::syncWithCarbon();
    config(['services.discogs.consumer_key' => 'test', 'services.discogs.consumer_secret' => 'secret']);
    $existing = socialRecord(socialUser('first'), releaseId: 201);
    $existing->update(['payload' => [...$existing->payload,
        'cover' => '/storage/records/201.jpg', 'artistBios' => [['id' => 634, 'name' => 'Brian Eno', 'profile' => 'Composer.']],
        'coverPreview' => app(RecordCoverImages::class)->preview($existing->payload['cover']),
        'releaseNotes' => '', 'credits' => ['release' => [], 'tracks' => []],
    ]]);
    $owner = socialUser('second');
    $owner->update(['discogs_username' => 'second', 'discogs_token' => 'access', 'discogs_secret' => 'secret']);
    $newPressing = discogsCollectionEntry(302, 202);
    $newPressing['basic_information']['master_id'] = 0;
    Http::fake([
        'api.discogs.com/users/*' => Http::response([
            'pagination' => ['pages' => 1, 'items' => 2], 'releases' => [discogsCollectionEntry(301, 201), $newPressing],
        ]),
        'api.discogs.com/releases/202' => Http::response(['artists' => [['id' => 634, 'name' => 'Brian Eno']], 'tracklist' => []]),
    ]);

    (new SyncUserCollection($owner->id))->handle(app(DiscogsRecordMapper::class), app(RecordCollection::class));

    $this->assertDatabaseCount('releases', 2);
    $this->assertDatabaseCount('artists', 1);
    $this->assertDatabaseCount('artist_release', 2);
    expect($owner->records()->count())->toBe(2)
        ->and($owner->records()->where('instance_id', 301)->firstOrFail()->payload['cover'])->toBe('/storage/records/201.jpg')
        ->and($owner->records()->where('instance_id', 302)->firstOrFail()->payload['artistBios'][0]['profile'])->toBe('Composer.');
    Http::assertSentCount(2);
});

it('keeps every owners SQL browsing current when shared metadata changes or a bulk import adds another copy', function () {
    $firstOwner = User::freek();
    $firstOwner->update(['handle' => 'shared_first']);
    $secondOwner = socialUser('shared_second', 'everyone');
    $first = socialRecord($firstOwner, date: '2026-10-01 20:30:00', releaseId: 201);
    $second = socialRecord($secondOwner, date: '2026-10-01 22:30:00', releaseId: 201);
    $unrelated = socialRecord($secondOwner, 'Unrelated pressing', instance: 456, releaseId: 202);
    $unrelated->update(['payload' => [...$unrelated->payload, 'artists' => ['Other artist'], 'genres' => ['Rock']]]);
    $this->travel(5)->minutes();

    Release::storePayload(201, [
        'title' => 'Lucid Music', 'displayTitle' => 'Lucid Music', 'originalYear' => 1957,
        'artists' => ['Brian Eno'], 'genres' => ['Ambient'], 'tracks' => [['title' => 'Needle Song']],
    ], replace: true);

    foreach (['shared_first', 'shared_second'] as $handle) {
        $this->get("/{$handle}?mode=artist&q=needle")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('records.total', 1)->where('groups.0.name', 'Brian Eno')->where('records.data.0.displayTitle', 'Lucid Music'));
        $this->get("/{$handle}?mode=genre&q=needle")->assertOk()->assertInertia(fn (Assert $page) => $page->where('groups.0.name', 'Ambient'));
        $this->get("/{$handle}?mode=decade&q=needle")->assertOk()->assertInertia(fn (Assert $page) => $page->where('groups.0.name', '1950s'));
    }
    expect($first->fresh()->browse_added_day)->toBe('2026-10-01')
        ->and($second->fresh()->browse_added_day)->toBe('2026-10-02')
        ->and($first->fresh()->updated_at->equalTo($first->updated_at))->toBeTrue()
        ->and($second->fresh()->updated_at->equalTo($second->updated_at))->toBeTrue();

    app(RecordCollection::class)->storeMany([[...$first->fresh()->payload,
        'instanceId' => 789, 'title' => 'Stale listing', 'artists' => ['Stale artist'], 'genres' => ['Pop'],
    ]]);
    $this->get('/shared_first?mode=artist&q=needle')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('records.total', 2)->where('groups.0.name', 'Brian Eno')->where('records.data.0.title', 'Lucid Music'));

    app(RecordCollection::class)->storeMany([[...$first->fresh()->payload, 'artists' => ['Updated artist']]]);
    $this->get('/shared_second?mode=artist&q=needle')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('records.total', 1)->where('groups.0.name', 'Updated artist'));
    $this->get('/shared_second?mode=genre&q=unrelated')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('records.total', 1)->where('groups.0.name', 'Rock'));
});
