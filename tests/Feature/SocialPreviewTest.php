<?php

use App\Support\RecordShareImage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

it('renders crawler-visible homepage and public collection previews with canonical urls', function () {
    $owner = socialUser('previewcollector', 'everyone');
    socialRecord($owner, 'Public preview album');

    $this->get('/')
        ->assertOk()
        ->assertSee('property="og:title" content="Plaat Club"', false)
        ->assertSee('property="og:image" content="'.url('/share/site.jpg?v=2').'"', false)
        ->assertSee('name="twitter:card" content="summary_large_image"', false);

    $this->get('/people/previewcollector?mode=artist')
        ->assertOk()
        ->assertSee('property="og:title" content="Previewcollector’s collection"', false)
        ->assertSee('property="og:image" content="'.url('/share/collections/previewcollector.jpg').'"', false)
        ->assertSee('rel="canonical" href="'.url('/previewcollector').'"', false);
});

it('previews a public record without publishing notes or credentials', function () {
    $owner = socialUser('previewrecord', 'everyone');
    $record = socialRecord($owner, 'Preview album');
    $record->update(['shared_note' => 'Shared note secret', 'private_note' => 'Private note secret']);

    $response = $this->get('/shelves/'.$record->public_id)->assertOk()
        ->assertSee('property="og:title" content="Preview album by George Harrison"', false)
        ->assertSee('property="og:type" content="music.album"', false)
        ->assertSee('property="og:image" content="'.url('/share/shelves/'.$record->public_id.'.jpg?v=2').'"', false);

    expect(json_encode($response->inertiaProps('socialPreview')))
        ->not->toContain('Shared note secret', 'Private note secret', $owner->email);

    $this->get('/collections?record='.$record->public_id)->assertOk()
        ->assertSee('property="og:title" content="Preview album by George Harrison"', false)
        ->assertSee('rel="canonical" href="'.$record->url().'"', false);
});

it('uses generic metadata for private collections even for authorized viewers', function (string $visibility) {
    $owner = socialUser('privatepreview', $visibility);
    $friend = socialUser('previewfriend');
    socialFriends($owner, $friend);
    $record = socialRecord($owner, 'Never publish this album');

    foreach ([null, $owner, $friend] as $viewer) {
        auth()->forgetGuards();
        if ($viewer) {
            $this->actingAs($viewer);
        }

        $response = $this->get('/privatepreview');
        if ($viewer) {
            $response->assertOk();
            expect($response->inertiaProps('socialPreview.title'))->toBe('Plaat Club')
                ->and($response->inertiaProps('socialPreview.image'))->toBe(url('/share/site.jpg?v=2'));
        } else {
            $response->assertRedirect('/login')->assertDontSee('Never publish this album');
        }
        $this->get('/share/collections/privatepreview.jpg')->assertNotFound();
        $this->get('/share/shelves/'.$record->public_id.'.jpg')->assertNotFound();
    }

    $response = $this->actingAs($owner)->get('/shelves/'.$record->public_id)->assertOk();
    expect($response->inertiaProps('socialPreview.title'))->toBe('Plaat Club');
})->with(['only_me', 'friends']);

it('serves the dedicated homepage artwork without reusing the previous cached image', function () {
    $oldKey = 'social-preview:v3:'.hash('sha256', json_encode(['home'], JSON_THROW_ON_ERROR));
    Cache::put($oldKey, base64_encode('old homepage artwork'), now()->addDay());

    $expected = app(RecordShareImage::class)->renderHome();

    foreach (['/share/site.jpg?v=2', '/share/site.jpg'] as $url) {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        expect($response->getContent())->toBe($expected);
    }
});

it('preserves personalized artwork for empty public collections', function () {
    $owner = socialUser('emptypreview', 'everyone');
    $image = app(RecordShareImage::class);
    $expected = $image->renderClub('Emptypreview’s collection', '@emptypreview / Plaat Club');

    $response = $this->get('/share/collections/emptypreview.jpg')->assertOk();

    expect($response->getContent())->toBe($expected)->not->toBe($image->renderHome());

    $owner->update(['visibility' => 'only_me']);
    $this->actingAs($owner)->get('/share/collections/emptypreview.jpg')->assertNotFound();
});

it('serves the revised record artwork instead of stale cached artwork', function () {
    $record = socialRecord(socialUser('freshpreview', 'everyone'), 'Hope Downs');
    $fields = Arr::only($record->payload, ['cover', 'artist', 'displayTitle', 'originalYear']);
    $oldKey = 'social-preview:v3:'.hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));
    Cache::put($oldKey, base64_encode('old record artwork'), now()->addDay());

    foreach (['/share/shelves/'.$record->public_id.'.jpg?v=2', '/share/shelves/'.$record->public_id.'.jpg'] as $url) {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        expect($response->getContent())->not->toBe('old record artwork');
        $dimensions = getimagesizefromstring($response->getContent());
        expect([$dimensions[0], $dimensions[1]])->toBe([1200, 630]);
    }
});

it('serves real jpeg previews without permanently publishing collection membership', function () {
    $owner = socialUser('imagepreview', 'everyone');
    $record = socialRecord($owner, 'Visible preview');

    foreach (['/share/collections/imagepreview.jpg', '/share/shelves/'.$record->public_id.'.jpg'] as $url) {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $dimensions = getimagesizefromstring($response->getContent());
        expect([$dimensions[0], $dimensions[1]])->toBe([1200, 630])
            ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    }

    expect(Storage::disk('public')->allFiles('share'))->toBe([]);

    $owner->update(['visibility' => 'friends']);
    $this->actingAs($owner)->get('/share/collections/imagepreview.jpg')->assertNotFound();
    $this->get('/share/shelves/'.$record->public_id.'.jpg')->assertNotFound();

    $owner->update(['visibility' => 'everyone']);
    $this->get('/share/shelves/'.$record->public_id.'.jpg')->assertOk();
    $record->delete();
    $this->get('/share/shelves/'.$record->public_id.'.jpg')->assertNotFound();
    $owner->delete();
    $this->get('/share/collections/imagepreview.jpg')->assertNotFound();
});

it('keeps mixed-owner copies distinct and refreshes previews when public metadata changes', function () {
    $first = socialRecord(socialUser('firstpreview', 'everyone'), 'First public album');
    $second = socialRecord(socialUser('secondpreview', 'everyone'), 'Second public album');
    $firstImage = $this->get('/share/shelves/'.$first->public_id.'.jpg')->assertOk()->getContent();
    $secondImage = $this->get('/share/shelves/'.$second->public_id.'.jpg')->assertOk()->getContent();
    expect($firstImage)->not->toBe($secondImage);

    $first->update(['payload' => [...$first->payload, 'displayTitle' => 'Renamed public album']]);
    expect($this->get('/share/shelves/'.$first->public_id.'.jpg')->assertOk()->getContent())->not->toBe($firstImage);
});
