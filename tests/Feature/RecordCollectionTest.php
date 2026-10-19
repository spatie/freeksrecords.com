<?php

use App\Support\RecordCollection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('lets guests browse the whole collection without downloading every track link', function () {
    $this->get(route('collection'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('sharedRecord', null)
            ->where('social.title', 'Plaat Club')
            ->where('social.imageAlt', 'Plaat Club: a wooden shelf of album sleeves beneath a vintage turntable and speakers')
            ->has('collection.records', 850)
            ->where('collection.records.0.artist', 'George Harrison')
            ->where('collection.records.0.originalYear', 1970)
            ->where('collection.records.0.year', 2021)
            ->where('collection.records.4.artist', 'Elbow')
            ->where('collection.records.1.title', 'Shebang')
            ->where('collection.records.0.trackTitles.1', 'My Sweet Lord')
            ->missing('collection.records.0.tracks')
            ->where('collection.records.1.appleMusicUrl', 'https://music.apple.com/be/album/shebang/1630427079')
            ->where('collection.records.1.spotifyUrl', 'https://open.spotify.com/album/1khpMdVH3EXz5RWt4WRAbg')
            ->where('collection.records.3.spotifySearch', true)
            ->missing('collection.records.0.discogsUrl')
            ->missing('collection.records.0.coverSource')
            ->missing('collection.records.0.catalogNumber')
        );
});

it('provides track positions when opening a record', function () {
    $this->getJson(route('recordDetails', ['instanceId' => 2189134863]))
        ->assertOk()
        ->assertJsonPath('title', 'Shebang')
        ->assertJsonPath('tracks.0.position', 'A1')
        ->assertJsonPath('tracks.2.position', 'B1');
});

it('provides direct and search listening links when opening a record', function () {
    $this->getJson(route('recordDetails', ['instanceId' => 2189134981]))
        ->assertOk()
        ->assertJsonPath('tracks.1.appleMusicSearch', false)
        ->assertJsonPath('tracks.1.appleMusicUrl', fn (string $url) => str_contains($url, '?i='))
        ->assertJsonPath('tracks.1.spotifySearch', true)
        ->assertJsonPath('tracks.1.spotifyUrl', 'https://open.spotify.com/search/George%20Harrison%20My%20Sweet%20Lord');
});

it('returns 404 for unknown collection copies', function () {
    $this->getJson(route('recordDetails', ['instanceId' => 9999999999]))->assertNotFound();
});

it('does not route invalid collection copy ids', function () {
    $this->getJson('/api/records/not-a-record')->assertNotFound();
});

it('renders social metadata for a shared record', function () {
    $this->get(route('recordShare', ['instanceId' => 2189134863, 'slug' => 'oren-ambarchi-shebang']))
        ->assertOk()
        ->assertSee('property="og:site_name" content="Plaat Club"', false)
        ->assertSee('property="og:title" content="Shebang by Oren Ambarchi"', false)
        ->assertSee('Shebang by Oren Ambarchi (2022), from Freek&#039;s vinyl collection.', false)
        ->assertSee('name="twitter:description"', false)
        ->assertSee('property="og:locale" content="en_GB"', false)
        ->assertSee('name="twitter:site" content="@freekmurze"', false)
        ->assertSee('name="theme-color" content="#292a22"', false)
        ->assertSee('name="twitter:card" content="summary_large_image"', false)
        ->assertSee('property="og:image:type" content="image/jpeg"', false)
        ->assertSee('property="og:image" content="'.storedShareImageUrl('share/records/2189134863-'), false)
        ->assertSee('name="twitter:image" content="'.storedShareImageUrl('share/records/2189134863-'), false);
});

it('passes the shared record to the page so it opens immediately', function () {
    $path = '/record/2189134863/oren-ambarchi-shebang';

    $this->get($path)
        ->assertSee('property="og:url" content="'.url($path).'"', false)
        ->assertSee('rel="canonical" href="'.url($path).'"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedRecord.instanceId', 2189134863)
            ->where('sharedRecord.title', 'Shebang')
            ->where('sharedRecord.url', url($path))
            ->where('collection.records.1.shareUrl', $path));
});

it('redirects outdated or missing record slugs to the canonical url', function (string $path) {
    $this->get($path)->assertRedirect('/record/2189134863/oren-ambarchi-shebang')->assertStatus(301);
})->with([
    'outdated slug' => ['/record/2189134863/old-album-title'],
    'missing slug' => ['/record/2189134863'],
]);

it('returns 404 for unknown shared records', function () {
    $this->get('/record/9999999999/unknown-album')->assertNotFound();
});

it('stores a small preview image for a shared record and redirects to it', function () {
    $response = $this->get(route('recordShareImage', 2189134863));

    $response->assertRedirect()->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('public', 's-maxage=3600')
        ->and($response->headers->get('Location'))->toStartWith(storedShareImageUrl('share/records/2189134863-'));

    expectStoredShareImage($response->headers->get('Location'));
});

it('returns 404 for the preview image of an unknown record', function () {
    $this->get(route('recordShareImage', 9999999999))->assertNotFound();
});

it('stores a small preview image for the collection and redirects to it', function () {
    $response = $this->get(route('collectionShareImage', ['v' => 2]));

    $response->assertRedirect()->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('public', 's-maxage=3600')
        ->and($response->headers->get('Location'))->toStartWith(storedShareImageUrl('share/collection-'));

    expectStoredShareImage($response->headers->get('Location'));
});

it('renders each share image only once', function () {
    $this->get(route('collection'))->assertOk();
    $this->get(route('collectionShareImage'))->assertRedirect();

    expect(Storage::disk('public')->allFiles('share'))->toHaveCount(1);
});

it('renders social metadata for the original collection', function () {
    $home = route('collection');

    $this->get(route('collection'))
        ->assertSee('<title>Plaat Club</title>', false)
        ->assertSee('property="og:site_name" content="Plaat Club"', false)
        ->assertSee('property="og:type" content="website"', false)
        ->assertSee('A lifetime of digging, one shelf at a time.')
        ->assertSee('name="twitter:description"', false)
        ->assertSee('property="og:locale" content="en_GB"', false)
        ->assertSee('name="twitter:site" content="@freekmurze"', false)
        ->assertSee('name="theme-color" content="#292a22"', false)
        ->assertSee('property="og:image" content="'.storedShareImageUrl('share/collection-'), false)
        ->assertSee('name="twitter:image" content="'.storedShareImageUrl('share/collection-'), false)
        ->assertSee('property="og:image:type" content="image/jpeg"', false)
        ->assertSee("rel=\"canonical\" href=\"{$home}\"", false);
});

it('describes the collection with structured data', function () {
    $schema = structuredData($this->get(route('collection'))->assertOk()->getContent());

    expect($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('CollectionPage')
        ->and($schema['isPartOf']['name'])->toBe('Plaat Club')
        ->and($schema['author']['name'])->toBe('Freek Van der Herten');
});

it('describes a shared album with structured data', function () {
    $html = $this->get('/record/2189134863/oren-ambarchi-shebang')->assertOk()->getContent();

    $schema = structuredData($html);

    expect($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('MusicAlbum')
        ->and($schema['name'])->toBe('Shebang')
        ->and($schema['byArtist']['name'])->toBe('Oren Ambarchi');
});

it('reads the persisted collection once records are stored', function () {
    $collection = app(RecordCollection::class);
    $record = $collection->snapshot()[0];

    $collection->store($record);

    $this->get(route('collection'))->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->has('collection.records', 1)
        ->where('collection.records.0.instanceId', $record['instanceId'])
        ->missing('collection.records.0.tracks'));
});

it('refreshes the cached collection when a record is stored', function () {
    $collection = app(RecordCollection::class);
    [$first, $second] = $collection->snapshot();

    $collection->store($first);

    expect($collection->summaries())->toHaveCount(1);

    $collection->store($second);

    expect($collection->summaries())->toHaveCount(2);
});

function storedShareImageUrl(string $pathPrefix): string
{
    return Storage::disk('public')->url($pathPrefix);
}

function expectStoredShareImage(string $url): void
{
    $path = substr($url, strlen(storedShareImageUrl('')));

    Storage::disk('public')->assertExists($path);

    $bytes = Storage::disk('public')->get($path);

    [$width, $height, $type] = getimagesizefromstring($bytes);

    expect($path)->toEndWith('.jpg')
        ->and($type)->toBe(IMAGETYPE_JPEG)
        ->and($width)->toBe(1200)
        ->and($height)->toBe(630)
        ->and(strlen($bytes))->toBeLessThan(150 * 1024);
}

/** @return array<string, mixed> */
function structuredData(string $html): array
{
    preg_match('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}
