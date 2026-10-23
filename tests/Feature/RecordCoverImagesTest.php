<?php

use App\Jobs\GenerateRecordCoverPreview;
use App\Jobs\SyncUserCollection;
use App\Support\DiscogsRecordMapper;
use App\Support\RecordCollection;
use App\Support\RecordCoverImages;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->disk = Storage::fake('public');
    $this->collection = app(RecordCollection::class);
    $this->record = $this->collection->snapshot()[0];
});

it('moves bundled covers to public storage and can safely copy them again', function () {
    $this->collection->store($this->record);

    $this->artisan('records:copy-covers')->assertSuccessful();
    $this->artisan('records:copy-covers')->expectsOutput('Copied 0 covers. Failed: 0.')->assertSuccessful();

    $path = "records/{$this->record['id']}.jpg";
    $this->disk->assertExists($path);
    expect($this->disk->get($path))->toBe(file_get_contents(public_path(ltrim($this->record['cover'], '/'))))
        ->and($this->collection->find($this->record['instanceId'])['cover'])->toBe($this->disk->url($path));
    Http::assertNothingSent();
});

it('copies a deferred Discogs cover and replaces the placeholder with its public url', function () {
    $bytes = file_get_contents(public_path(ltrim($this->record['cover'], '/')));
    $this->collection->store([
        ...$this->record,
        'coverSource' => 'https://i.discogs.com/test-cover.jpg',
        'cover' => '/images/record-placeholder.svg',
    ]);
    Http::fake(['https://i.discogs.com/test-cover.jpg' => Http::response($bytes)]);

    $this->artisan('records:copy-covers')->assertSuccessful();

    $path = "records/{$this->record['id']}.jpg";
    $this->disk->assertExists($path);
    expect($this->collection->find($this->record['instanceId'])['cover'])->toBe($this->disk->url($path));
    Http::assertSentCount(1);
    $this->get(route('recordShareImage', $this->record['instanceId']))->assertRedirect();
    expect($this->disk->allFiles('share/records'))->toHaveCount(1);
});

it('keeps the record when a cover download fails so it can be retried', function () {
    $this->collection->store([...$this->record, 'cover' => 'https://i.discogs.com/unavailable.jpg']);
    Http::fake(['https://i.discogs.com/unavailable.jpg' => Http::response('', 503)]);

    $this->artisan('records:copy-covers')->assertFailed();

    $this->disk->assertMissing("records/{$this->record['id']}.jpg");
    expect($this->collection->find($this->record['instanceId'])['cover'])->toBe('https://i.discogs.com/unavailable.jpg');
    Http::assertSentCount(1);
});

it('rejects covers from unexpected hosts without fetching them', function () {
    $this->collection->store([...$this->record, 'cover' => 'http://127.0.0.1/private']);

    $this->artisan('records:copy-covers')->assertFailed();

    $this->disk->assertMissing("records/{$this->record['id']}.jpg");
    Http::assertNothingSent();
});

it('serves Discogs artwork from the same origin for detail and overlay palettes', function () {
    $owner = socialUser('coverowner', 'everyone');
    $record = socialRecord($owner);
    $record->update(['payload' => [...$record->payload, 'cover' => 'https://i.discogs.com/palette.jpg']]);
    $bytes = file_get_contents(public_path(ltrim($this->record['cover'], '/')));
    Http::fake(['https://i.discogs.com/palette.jpg' => Http::response($bytes)]);

    $url = route('shelf.cover', $record, false);
    $this->get('/shelves/'.$record->id)->assertInertia(fn (Assert $page) => $page->where('record.payload.cover', $url));
    $this->get('/coverowner?record='.$record->id)->assertInertia(fn (Assert $page) => $page->where('recordOverlay.record.payload.cover', $url));
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertContent($bytes);
    $cached = $this->get($url)->assertOk()->assertContent($bytes);
    expect($cached->headers->get('Cache-Control'))->toContain('private', 'no-store');

    $owner->update(['visibility' => 'only_me']);
    $this->get($url)->assertNotFound();
    $this->actingAs($owner)->get($url)->assertOk()->assertContent($bytes);
});

it('revokes access to cached artwork when a friendship ends', function () {
    $owner = socialUser('privatecover', 'friends');
    $friend = socialUser('coverfriend');
    $friendship = socialFriends($owner, $friend);
    $record = socialRecord($owner);
    $url = route('shelf.cover', $record, false);

    $this->actingAs($friend)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $friendship->delete();
    $this->get($url)->assertNotFound();
    $this->actingAs($owner)->get($url)->assertOk();
});

it('reads stored artwork locally instead of making a cross-origin storage request', function () {
    $owner = socialUser('storedcover', 'everyone');
    $record = socialRecord($owner);
    $bytes = file_get_contents(public_path(ltrim($this->record['cover'], '/')));
    $this->disk->put('records/123.jpg', $bytes);
    $record->update(['payload' => [...$record->payload, 'cover' => $this->disk->url('records/123.jpg')]]);

    $this->get(route('shelf.cover', $record))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertContent($bytes);
    Http::assertNothingSent();
});

it('backfills inline 16 pixel artwork once and exposes it only to authorized collection viewers without generating on render', function () {
    $owner = socialUser('previewowner', 'friends');
    $friend = socialUser('previewfriend');
    $friendship = socialFriends($owner, $friend);
    $record = socialRecord($owner);
    $record->update(['private_note' => 'Keep this private', 'shared_note' => 'Keep this too']);
    $original = $record->payload;

    $this->artisan('records:generate-cover-previews')->expectsOutput('Queued 1 cover previews.')->assertSuccessful();
    $preview = $record->fresh()->payload['coverPreview'];
    expect($preview)->toStartWith('data:image/jpeg;base64,');
    $dimensions = getimagesizefromstring(base64_decode(explode(',', $preview, 2)[1]));
    expect([$dimensions[0], $dimensions[1], $dimensions['mime']])->toBe([16, 16, 'image/jpeg'])
        ->and(strlen($preview))->toBeLessThan(1800)
        ->and($record->fresh()->payload)->toBe([...$original, 'coverPreview' => $preview])
        ->and($record->fresh()->private_note)->toBe('Keep this private')
        ->and($record->fresh()->shared_note)->toBe('Keep this too');

    $this->mock(RecordCoverImages::class)->shouldNotReceive('preview', 'read');
    $this->artisan('records:generate-cover-previews')->expectsOutput('Queued 0 cover previews.')->assertSuccessful();
    $response = $this->actingAs($friend)->get('/previewowner')->assertInertia(fn (Assert $page) => $page
        ->where('records.data.0.coverPreview', $preview)->missing('records.data.0.coverSource'));
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $friendship->delete();
    $this->get('/previewowner')->assertDontSee($preview, false);
    Http::assertNothingSent();
});

it('queues preview generation during import without downloading artwork in the import job', function () {
    Bus::fake([GenerateRecordCoverPreview::class]);
    $owner = socialUser('previewimport');
    $owner->update(['discogs_username' => 'collector', 'discogs_token' => 'test-access', 'discogs_secret' => 'test-secret']);
    $entry = discogsCollectionEntry(321, 654);
    $entry['basic_information']['master_id'] = 0;
    $entry['basic_information']['cover_image'] = 'https://i.discogs.com/deferred-preview.jpg';
    Http::fake([
        'api.discogs.com/users/*' => Http::response(['pagination' => ['pages' => 1, 'items' => 1], 'releases' => [$entry]]),
        'api.discogs.com/releases/*' => Http::response(['tracklist' => []]),
    ]);

    (new SyncUserCollection($owner->id))->handle(app(DiscogsRecordMapper::class), $this->collection);

    $record = $owner->records()->firstOrFail();
    expect($record->payload['cover'])->toBe('https://i.discogs.com/deferred-preview.jpg')
        ->and($record->payload)->not->toHaveKey('coverPreview');
    Bus::assertDispatched(GenerateRecordCoverPreview::class, fn ($job) => $job->recordId === $record->id);
    Http::assertNotSent(fn ($request) => parse_url($request->url(), PHP_URL_HOST) === 'i.discogs.com');
});

it('preserves concurrent metadata edits and reuses previews for identical artwork without another download', function () {
    $owner = socialUser('previewcopies');
    $record = socialRecord($owner);
    $source = 'https://i.discogs.com/preview.jpg';
    $record->update(['payload' => [...$record->payload, 'cover' => $source]]);
    $bytes = file_get_contents(public_path(ltrim($this->record['cover'], '/')));
    Http::fake([$source => function () use ($record, $bytes) {
        $record->update(['payload' => [...$record->payload, 'releaseNotes' => 'Updated during download']]);

        return Http::response($bytes);
    }]);

    (new GenerateRecordCoverPreview($record->id))->handle(app(RecordCoverImages::class), $this->collection);
    $second = socialRecord($owner, instance: 456);
    $second->update(['payload' => [...$second->payload, 'cover' => $source]]);
    (new GenerateRecordCoverPreview($second->id))->handle(app(RecordCoverImages::class), $this->collection);

    expect($record->fresh()->payload['releaseNotes'])->toBe('Updated during download')
        ->and($record->fresh()->payload['coverPreview'])->toBe($second->fresh()->payload['coverPreview']);
    Http::assertSentCount(1);
});

it('does not attach stale previews when the cover changes during generation and allows failed downloads to retry', function () {
    $record = socialRecord(socialUser('previewretry'));
    $source = 'https://i.discogs.com/retry-preview.jpg';
    $record->update(['payload' => [...$record->payload, 'cover' => $source]]);
    $bytes = file_get_contents(public_path(ltrim($this->record['cover'], '/')));
    $attempt = 0;
    Http::fake([$source => function () use ($record, $bytes, &$attempt) {
        if ($attempt++ === 0) {
            return Http::response('', 503);
        }

        $record->update(['payload' => [...$record->payload, 'cover' => '/images/records/123.jpg']]);

        return Http::response($bytes);
    }]);
    $job = new GenerateRecordCoverPreview($record->id);
    expect(fn () => $job->handle(app(RecordCoverImages::class), $this->collection))->toThrow(RequestException::class);
    expect($record->fresh()->payload)->not->toHaveKey('coverPreview');

    $job->handle(app(RecordCoverImages::class), $this->collection);
    expect($record->fresh()->payload)->not->toHaveKey('coverPreview');
});
