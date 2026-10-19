<?php

use App\Models\Friendship;
use App\Models\User;

it('welcomes visitors with public collections and private response headers', function () {
    $record = socialRecord(User::freek(), 'A real public record');
    $response = $this->get(route('home'));

    $response->assertOk()->assertViewIs('social.intro')
        ->assertSee('A shelf for every record.')
        ->assertSee('Create account')
        ->assertSee('Browse collections')
        ->assertSee($record->payload['cover'], false)
        ->assertSee(route('profile', 'freek'), false);

    expect(route('home'))->toBe(url('/'))
        ->and(route('collection'))->toBe(url('/collection'))
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Cache-Control'))->not->toContain('s-maxage', 'public');
});

it('previews at most three nonempty public collections in handle order with six actual copies each', function () {
    socialUser('aaaempty', 'everyone');
    foreach (['delta', 'charlie', 'bravo', 'alpha'] as $handle) {
        $owner = socialUser($handle, 'everyone');
        foreach (range(1, 8) as $instance) {
            socialRecord($owner, instance: $instance);
        }
    }

    $this->get('/')->assertOk()->assertViewHas('collectors', function ($collectors) {
        expect($collectors->pluck('handle')->all())->toBe(['alpha', 'bravo', 'charlie']);
        foreach ($collectors as $collector) {
            expect($collector->records_count)->toBe(8)
                ->and($collector->records->pluck('id')->all())->toBe($collector->records()->orderBy('id')->limit(6)->pluck('id')->all());
            foreach ($collector->records as $record) {
                expect(array_keys($record->getAttributes()))->toBe(['id', 'user_id', 'payload']);
            }
        }

        return true;
    });
});

it('paginates public collections alphabetically without including empty shelves', function () {
    socialUser('aaaempty', 'everyone');
    foreach (range(1, 13) as $index) {
        socialRecord(socialUser(sprintf('collector%02d', $index), 'everyone'));
    }

    $response = $this->get(route('collections'))->assertOk()->assertViewIs('social.collections')
        ->assertViewHas('collectors', fn ($collectors) => $collectors->total() === 13
            && $collectors->perPage() === 12 && $collectors->count() === 12
            && $collectors->first()->handle === 'collector01' && $collectors->last()->handle === 'collector12');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $this->get('/collections?page=2')->assertOk()
        ->assertViewHas('collectors', fn ($collectors) => $collectors->pluck('handle')->all() === ['collector13']);
});

it('removes collections and their artwork from discovery immediately when privacy changes', function (string $visibility) {
    $owner = socialUser('discoverable', 'everyone');
    $viewer = socialUser('discoveryfriend');
    socialFriends($owner, $viewer);
    $record = socialRecord($owner, 'Discoverable record');
    $record->update(['payload' => [...$record->payload, 'cover' => '/images/discovery-only-cover.jpg']]);

    foreach (['/', '/collections'] as $path) {
        $this->get($path)->assertOk()->assertSee('discoverable')->assertSee('/images/discovery-only-cover.jpg', false);
    }
    $owner->update(['visibility' => $visibility]);
    foreach (['/', '/collections'] as $path) {
        $this->get($path)->assertOk()->assertDontSee('discoverable')->assertDontSee('Discoverable record')
            ->assertDontSee('/images/discovery-only-cover.jpg', false)
            ->assertViewHas('collectors', fn ($collectors) => ! $collectors->contains('id', $owner->id));
    }
    $this->actingAs($viewer)->get('/collections')->assertOk()->assertDontSee('discoverable')
        ->assertDontSee('Discoverable record')->assertDontSee('/images/discovery-only-cover.jpg', false)
        ->assertViewHas('collectors', fn ($collectors) => ! $collectors->contains('id', $owner->id));
})->with(['only_me', 'friends']);

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
