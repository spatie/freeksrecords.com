<?php

use App\Support\RecordCollection;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();

    $this->disk = Storage::fake('public');
    $this->collection = app(RecordCollection::class);
    $this->existing = $this->collection->snapshot()[0];
});

it('imports additions and preserves existing records on repeated runs', function () {
    $this->collection->store($this->existing);
    $addition = discogsCollectionEntry(999999991, 999999992);
    $addition['basic_information']['cover_image'] = 'https://i.discogs.com/new-record.jpg';
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([
            $addition,
            discogsCollectionEntry($this->existing['instanceId'], $this->existing['id']),
        ]),
        'https://i.discogs.com/new-record.jpg' => Http::response(file_get_contents(public_path(ltrim($this->existing['cover'], '/')))),
        'api.discogs.com/releases/999999992' => Http::response(['released' => '2026-09-24', 'tracklist' => [
            ['type_' => 'heading', 'title' => 'Side A'],
            ['type_' => 'track', 'title' => 'First Song', 'position' => 'A1', 'duration' => '3:21'],
        ]]),
        'api.discogs.com/masters/999999991' => Http::response(['year' => 1981]),
    ]);

    $this->artisan('records:sync-discogs')->assertSuccessful();
    $this->artisan('records:sync-discogs')->assertSuccessful();

    Http::assertSentCount(5);
    $this->assertDatabaseCount('collection_records', 2);
    $this->disk->assertExists("records/{$this->existing['id']}.jpg");
    $this->disk->assertExists('records/999999992.jpg');
    $this->getJson(route('recordDetails', 999999991))
        ->assertJsonPath('cover', $this->disk->url('records/999999992.jpg'))
        ->assertJsonPath('originalYear', 1981)
        ->assertJsonPath('pressingReleaseDate', '2026-09-24')
        ->assertJsonCount(1, 'tracks')
        ->assertJsonPath('tracks.0.title', 'First Song');
    $this->getJson(route('recordDetails', $this->existing['instanceId']))->assertJsonPath('appleMusicSearch', false);
});

it('strips Discogs disambiguation suffixes from record and track artists', function () {
    $this->collection->store($this->existing);
    $addition = discogsCollectionEntry(999999991, 999999992);
    $addition['basic_information']['artists'] = [['name' => 'Cardinals (3)']];
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([
            $addition,
            discogsCollectionEntry($this->existing['instanceId'], $this->existing['id']),
        ]),
        'api.discogs.com/releases/999999992' => Http::response(['tracklist' => [
            ['title' => 'Guest Song', 'position' => 'A1', 'artists' => [['name' => 'Cardinals (3)'], ['name' => 'Guest (12)']]],
        ]]),
        'api.discogs.com/masters/999999991' => Http::response(['year' => 1981]),
    ]);

    $this->artisan('records:sync-discogs')->assertSuccessful();

    $this->getJson(route('recordDetails', 999999991))
        ->assertJsonPath('artist', 'Cardinals')
        ->assertJsonPath('tracks.0.artist', 'Cardinals, Guest')
        ->assertJsonPath('tracks.0.spotifyUrl', 'https://open.spotify.com/search/Cardinals%2C%20Guest%20Guest%20Song');
});

it('skips a release that cannot be fetched and still imports the others', function () {
    $this->collection->store($this->existing);
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([
            discogsCollectionEntry(999999991, 999999992),
            discogsCollectionEntry(999999993, 999999994),
            discogsCollectionEntry($this->existing['instanceId'], $this->existing['id']),
        ]),
        'api.discogs.com/releases/999999992' => Http::response([], 404),
        'api.discogs.com/releases/999999994' => Http::response(['tracklist' => []]),
        'api.discogs.com/masters/999999991' => Http::response(['year' => 1981]),
    ]);

    $this->artisan('records:sync-discogs')->assertFailed();

    $this->assertDatabaseMissing('collection_records', ['instance_id' => 999999991]);
    $this->assertDatabaseHas('collection_records', ['instance_id' => 999999993]);
});

it('removes records that are no longer in the Discogs collection', function () {
    [$kept, $sold] = $this->collection->snapshot();
    $this->collection->storeMany([$kept, $sold]);
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([discogsCollectionEntry($kept['instanceId'], $kept['id'])]),
    ]);

    $this->artisan('records:sync-discogs')->assertSuccessful();

    $this->assertDatabaseHas('collection_records', ['instance_id' => $kept['instanceId']]);
    $this->assertDatabaseMissing('collection_records', ['instance_id' => $sold['instanceId']]);
});

it('keeps all records when Discogs returns an incomplete collection', function () {
    [$first, $second] = $this->collection->snapshot();
    $this->collection->storeMany([$first, $second]);
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([discogsCollectionEntry($first['instanceId'], $first['id'])], items: 2),
    ]);

    $this->artisan('records:sync-discogs')->assertSuccessful();

    $this->assertDatabaseCount('collection_records', 2);
});

it('imports the bundled snapshot before syncing an empty database', function () {
    $snapshot = $this->collection->snapshot();
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage(array_map(
            fn (array $record): array => discogsCollectionEntry($record['instanceId'], $record['id']),
            $snapshot,
        )),
    ]);

    $this->artisan('records:sync-discogs')->assertSuccessful();

    $this->assertDatabaseCount('collection_records', count($snapshot));
});

it('does not clear the existing collection when Discogs fails', function () {
    $this->collection->store($this->existing);
    Http::fake(['api.discogs.com/users/*' => Http::response([], 503)]);

    $this->artisan('records:sync-discogs')->assertFailed();

    Http::assertSentCount(1);
    $this->assertDatabaseCount('collection_records', 1);
});

it('purges the edge cache after a sync that changes the collection', function () {
    configureEdgeCachePurging();
    [$kept, $sold] = $this->collection->snapshot();
    $this->collection->storeMany([$kept, $sold]);
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([discogsCollectionEntry($kept['instanceId'], $kept['id'])]),
        'cloud.laravel.com/*' => Http::response(),
    ]);

    $this->artisan('records:sync-discogs')
        ->expectsOutput('Purged the edge cache.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://cloud.laravel.com/api/environments/env-123/purge-edge-cache'
        && $request->hasHeader('Authorization', 'Bearer purge-token'));
});

it('keeps the edge cache when a sync changes nothing', function () {
    configureEdgeCachePurging();
    $this->collection->store($this->existing);
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([discogsCollectionEntry($this->existing['instanceId'], $this->existing['id'])]),
        'cloud.laravel.com/*' => Http::response(),
    ]);

    $this->artisan('records:sync-discogs')->assertSuccessful();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'cloud.laravel.com'));
});

it('skips the edge cache purge when it is not configured', function () {
    [$kept, $sold] = $this->collection->snapshot();
    $this->collection->storeMany([$kept, $sold]);
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([discogsCollectionEntry($kept['instanceId'], $kept['id'])]),
    ]);

    $this->artisan('records:sync-discogs')
        ->expectsOutput('The edge cache is not configured, so cached pages update within a day.')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

it('finishes the sync when the edge cache purge fails', function () {
    configureEdgeCachePurging();
    [$kept, $sold] = $this->collection->snapshot();
    $this->collection->storeMany([$kept, $sold]);
    Http::fake([
        'api.discogs.com/users/*' => discogsCollectionPage([discogsCollectionEntry($kept['instanceId'], $kept['id'])]),
        'cloud.laravel.com/*' => Http::response(['message' => 'This action is unauthorized.'], 403),
    ]);

    $this->artisan('records:sync-discogs')
        ->expectsOutput('The edge cache could not be purged, so cached pages update within a day.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('collection_records', ['instance_id' => $sold['instanceId']]);
});

it('can rerun the snapshot import without duplicating collection copies', function () {
    $this->artisan('records:import-snapshot')->assertSuccessful();
    $this->artisan('records:import-snapshot')->assertSuccessful();

    $this->assertDatabaseCount('collection_records', 850);
});

function configureEdgeCachePurging(): void
{
    config([
        'services.laravel_cloud.purge_token' => 'purge-token',
        'services.laravel_cloud.environment_id' => 'env-123',
    ]);
}

/** @return array<string, mixed> */
function discogsCollectionEntry(int $instanceId, int $releaseId): array
{
    return [
        'instance_id' => $instanceId,
        'date_added' => '2026-10-01T10:00:00-07:00',
        'basic_information' => [
            'id' => $releaseId,
            'master_id' => 999999991,
            'title' => 'A New Record',
            'year' => 2026,
            'artists' => [['name' => 'The Artist']],
            'labels' => [['name' => 'The Label', 'catno' => 'NEW-1']],
            'formats' => [['descriptions' => ['LP', 'Reissue']]],
            'genres' => ['Rock'],
            'styles' => [],
            'cover_image' => '',
        ],
    ];
}

/** @param array<int, array<string, mixed>> $entries */
function discogsCollectionPage(array $entries, ?int $items = null): PromiseInterface
{
    return Http::response([
        'pagination' => ['pages' => 1, 'items' => $items ?? count($entries)],
        'releases' => $entries,
    ]);
}
