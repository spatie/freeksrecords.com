<?php

use App\Models\Friendship;

it('welcomes visitors with a private landing page and a link to the original shelf', function () {
    $response = $this->get(route('home'));

    $response->assertOk()->assertViewIs('social.intro')
        ->assertSee('Your collection.')
        ->assertSee('Good company')
        ->assertSee('vinyl, CDs and cassettes')
        ->assertSee('Start your collection')
        ->assertSee('See how it works')
        ->assertSee('Connect Discogs')
        ->assertSee('Choose who sees it')
        ->assertSee('Find your friends')
        ->assertSee('href="/collection"', false);

    expect(route('home'))->toBe(url('/'))
        ->and(route('collection'))->toBe(url('/collection'))
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Cache-Control'))->not->toContain('s-maxage', 'public');
});

it('sends signed-in visitors to verification or the chronological friends feed without caching', function (bool $verified, string $destination) {
    $user = socialUser('homevisitor');
    if (! $verified) {
        $user->forceFill(['email_verified_at' => null])->save();
    }

    $response = $this->actingAs($user)->get('/')->assertRedirect($destination);

    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Cache-Control'))->not->toContain('public', 's-maxage');
})->with([[true, '/feed'], [false, '/verify-email']]);

it('renders the feed sidebar with real collection totals and incoming request forms', function () {
    $viewer = socialUser('homeviewer');
    $requester = socialUser('homerequester');
    $other = socialUser('unrelatedrequester');
    $stranger = socialUser('unrelatedrecipient');
    foreach (range(1, 3) as $instance) {
        socialRecord($viewer, instance: $instance);
    }
    Friendship::query()->create(['user_low' => $viewer->id, 'user_high' => $requester->id, 'requested_by' => $requester->id]);
    Friendship::query()->create(['user_low' => $other->id, 'user_high' => $stranger->id, 'requested_by' => $other->id]);

    $this->actingAs($viewer)->get('/feed')->assertOk()->assertSee('3 releases')
        ->assertSee('Homerequester')
        ->assertDontSee('Unrelatedrequester')
        ->assertSee(route('friend.action', $requester), false)
        ->assertSee('value="accept"', false)
        ->assertSee('value="decline"', false)
        ->assertSee(route('profile', $viewer->handle), false);
    $this->post(route('friend.action', $requester), ['action' => 'accept'])->assertRedirect();
    $this->get('/feed')->assertDontSee('value="accept"', false);
});
