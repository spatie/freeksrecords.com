<?php

use Illuminate\Support\Facades\Storage;

it('renders crawler-visible homepage and public collection previews with canonical urls', function () {
    $owner = socialUser('previewcollector', 'everyone');
    socialRecord($owner, 'Public preview album');

    $this->get('/')
        ->assertOk()
        ->assertSee('property="og:title" content="Plaat Club"', false)
        ->assertSee('property="og:image" content="'.url('/share/site.jpg').'"', false)
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

    $response = $this->get('/shelves/'.$record->id)->assertOk()
        ->assertSee('property="og:title" content="Preview album by George Harrison"', false)
        ->assertSee('property="og:type" content="music.album"', false)
        ->assertSee('property="og:image" content="'.url('/share/shelves/'.$record->id.'.jpg').'"', false);

    expect(json_encode($response->inertiaProps('socialPreview')))
        ->not->toContain('Shared note secret', 'Private note secret', $owner->email);
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
                ->and($response->inertiaProps('socialPreview.image'))->toBe(url('/share/site.jpg'));
        } else {
            $response->assertRedirect('/login')->assertDontSee('Never publish this album');
        }
        $this->get('/share/collections/privatepreview.jpg')->assertNotFound();
        $this->get('/share/shelves/'.$record->id.'.jpg')->assertNotFound();
    }

    $response = $this->actingAs($owner)->get('/shelves/'.$record->id)->assertOk();
    expect($response->inertiaProps('socialPreview.title'))->toBe('Plaat Club');
})->with(['only_me', 'friends']);

it('serves real jpeg previews without permanently publishing collection membership', function () {
    $owner = socialUser('imagepreview', 'everyone');
    $record = socialRecord($owner, 'Visible preview');

    foreach (['/share/site.jpg', '/share/collections/imagepreview.jpg', '/share/shelves/'.$record->id.'.jpg'] as $url) {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $dimensions = getimagesizefromstring($response->getContent());
        expect([$dimensions[0], $dimensions[1]])->toBe([1200, 630])
            ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    }

    expect(Storage::disk('public')->allFiles('share'))->toBe([]);

    $owner->update(['visibility' => 'friends']);
    $this->actingAs($owner)->get('/share/collections/imagepreview.jpg')->assertNotFound();
    $this->get('/share/shelves/'.$record->id.'.jpg')->assertNotFound();

    $owner->update(['visibility' => 'everyone']);
    $this->get('/share/shelves/'.$record->id.'.jpg')->assertOk();
    $record->delete();
    $this->get('/share/shelves/'.$record->id.'.jpg')->assertNotFound();
    $owner->delete();
    $this->get('/share/collections/imagepreview.jpg')->assertNotFound();
});

it('keeps mixed-owner copies distinct and refreshes previews when public metadata changes', function () {
    $first = socialRecord(socialUser('firstpreview', 'everyone'), 'First public album');
    $second = socialRecord(socialUser('secondpreview', 'everyone'), 'Second public album');
    $firstImage = $this->get('/share/shelves/'.$first->id.'.jpg')->assertOk()->getContent();
    $secondImage = $this->get('/share/shelves/'.$second->id.'.jpg')->assertOk()->getContent();
    expect($firstImage)->not->toBe($secondImage);

    $first->update(['payload' => [...$first->payload, 'displayTitle' => 'Renamed public album']]);
    expect($this->get('/share/shelves/'.$first->id.'.jpg')->assertOk()->getContent())->not->toBe($firstImage);
});
