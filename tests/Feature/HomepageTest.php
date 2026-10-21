<?php

use App\Models\Friendship;
use App\Models\User;
use App\Support\RecordCollection;
use Inertia\Testing\AssertableInertia as Assert;

it('welcomes visitors with linked public artwork and private response headers', function () {
    $record = socialRecord(User::freek(), 'A real public record');
    $response = $this->get(route('home'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('social/HomePage')
        ->where('artwork.0.cover', $record->payload['cover'])
        ->where('artwork.0.url', '/shelves/'.$record->id)
        ->missing('collectors'));

    expect(route('home'))->toBe(url('/'))
        ->and(route('collection'))->toBe(url('/collection'))
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Cache-Control'))->not->toContain('s-maxage', 'public');
});

it('uses only live public records for linked artwork and never falls back to the bundled snapshot', function (string $path) {
    $friendsOwner = socialUser('friendsonly', 'friends');
    if ($path === '/verify-email') {
        $viewer = socialUser('unverifiedviewer');
        socialFriends($friendsOwner, $viewer);
        socialRecord($viewer, 'My private album');
        $this->actingAs($viewer);
    }
    socialRecord(socialUser('hiddenowner'), 'Private album');
    socialRecord($friendsOwner, 'Friends-only album');
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->has('artwork', 0));

    $owner = socialUser('openowner', 'everyone');
    $records = collect(app(RecordCollection::class)->snapshot())->unique('cover')->take(28)
        ->values()->map(function (array $payload, int $index) use ($owner) {
            $record = socialRecord($owner, $payload['displayTitle'], instance: $index + 1);
            $record->update(['payload' => $payload]);

            return $record;
        });
    $response = $this->get($path)->assertOk();
    $artwork = collect($response->inertiaProps('artwork'));
    $expectedCount = $path === '/' ? 6 : 12;
    expect($artwork)->toHaveCount($expectedCount)->and($artwork->pluck('cover')->unique())->toHaveCount($expectedCount);
    foreach ($artwork as $item) {
        $record = $records->firstWhere('payload.cover', $item['cover']);
        expect($record)->not->toBeNull()
            ->and($item['url'])->toBe('/shelves/'.$record->id)
            ->and($item['title'])->toBe($record->payload['artist'].' — '.$record->payload['displayTitle']);
    }
    $link = $artwork->first()['url'];
    $this->get($link)->assertOk();
    $selected = $records->firstWhere('id', (int) basename($link));
    $this->get($path.'?record='.$selected->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('recordOverlay.record.id', $selected->id)
        ->where('recordOverlay.record.payload.displayTitle', $selected->payload['displayTitle'])
        ->missing('recordOverlay.record.privateNote'));
    foreach (['friends', 'only_me'] as $visibility) {
        $owner->update(['visibility' => $visibility]);
        $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->has('artwork', 0));
        $this->get($link)->assertNotFound();
        $this->get($path.'?record='.$selected->id)->assertNotFound();
    }
})->with(['/', '/login', '/register', '/forgot-password', '/reset-password/test-token', '/verify-email']);

it('previews nonempty public collections with their latest additions first', function () {
    socialUser('aaaempty', 'everyone');
    $expected = [];
    foreach (['delta', 'charlie', 'bravo', 'alpha'] as $handle) {
        $owner = socialUser($handle, 'everyone');
        $records = [];
        foreach ([3, 7, 1, 9, 5, 7, 2, 4] as $index => $day) {
            $records[] = socialRecord($owner, date: "2026-10-{$day} 12:00:00", instance: $index + 1);
        }
        $expected[$handle] = array_map(fn ($index) => '/shelves/'.$records[$index]->id, [3, 5, 1, 4, 7, 0]);
    }

    $collectors = $this->get('/collections')->assertOk()->inertiaProps('collectors.data');
    expect(array_column($collectors, 'handle'))->toBe(['alpha', 'bravo', 'charlie', 'delta']);
    foreach ($collectors as $collector) {
        expect($collector['recordCount'])->toBe(8)
            ->and(array_column($collector['records'], 'url'))->toBe($expected[$collector['handle']])
            ->and(array_keys($collector))->toBe(['id', 'name', 'handle', 'profileUrl', 'recordCount', 'records']);
    }
});

it('paginates public collections alphabetically without including empty shelves', function () {
    socialUser('aaaempty', 'everyone');
    foreach (range(1, 13) as $index) {
        socialRecord(socialUser(sprintf('collector%02d', $index), 'everyone'));
    }

    $response = $this->get(route('collections'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('social/CollectionsPage')->has('collectors.data', 12)
        ->where('collectors.data.0.handle', 'collector01')->where('collectors.data.11.handle', 'collector12')
        ->where('collectors.prev_page_url', null)->where('collectors.next_page_url', url('/collections?page=2')));
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $this->get('/collections?page=2')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('collectors.data', 1)->where('collectors.data.0.handle', 'collector13')->where('collectors.next_page_url', null));
});

it('removes collections and their artwork from discovery immediately when privacy changes', function (string $visibility) {
    $owner = socialUser('discoverable', 'everyone');
    $viewer = socialUser('discoveryfriend');
    socialFriends($owner, $viewer);
    $record = socialRecord($owner, 'Discoverable record');
    $record->update(['payload' => [...$record->payload, 'cover' => '/images/discovery-only-cover.jpg']]);

    foreach (['/', '/collections'] as $path) {
        $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where($path === '/' ? 'artwork.0.cover' : 'collectors.data.0.records.0.cover', '/images/discovery-only-cover.jpg'));
    }
    $owner->update(['visibility' => $visibility]);
    foreach (['/', '/collections'] as $path) {
        $this->get($path)->assertOk()->assertDontSee('discoverable')->assertDontSee('Discoverable record')
            ->assertInertia(function (Assert $page) use ($path): void {
                $page->has($path === '/' ? 'artwork' : 'collectors.data', 0);
            });
    }
    $this->actingAs($viewer)->get('/collections')->assertOk()->assertDontSee('discoverable')
        ->assertDontSee('Discoverable record')->assertDontSee('/images/discovery-only-cover.jpg')
        ->assertInertia(fn (Assert $page) => $page->has('collectors.data', 0));
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

it('keeps friend requests off the feed and available on the friends page', function () {
    $viewer = socialUser('homeviewer');
    $requester = socialUser('homerequester');
    $other = socialUser('unrelatedrequester');
    $stranger = socialUser('unrelatedrecipient');
    foreach (range(1, 3) as $instance) {
        socialRecord($viewer, instance: $instance);
    }
    Friendship::query()->create(['user_low' => $viewer->id, 'user_high' => $requester->id, 'requested_by' => $requester->id]);
    Friendship::query()->create(['user_low' => $other->id, 'user_high' => $stranger->id, 'requested_by' => $other->id]);

    $this->actingAs($viewer)->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('social/FeedPage')->where('collectionCount', 3)->missing('incomingRequests')
        ->where('auth.user.profileUrl', '/homeviewer'));
    $this->get('/friends')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('social/FriendsPage')->has('people.data', 1)->where('people.data.0.id', $requester->id)
        ->has('connections', 1)->where('connections.0.requested_by', $requester->id)->where('connections.0.accepted_at', null));
});

it('previews legal drafts locally without publishing unapproved policies', function (string $document) {
    app()->detectEnvironment(fn () => 'local');
    $this->get(route($document))->assertOk()->assertInertia(fn (Assert $page) => $page->component('social/'.ucfirst($document).'Page'));

    app()->detectEnvironment(fn () => 'production');
    $this->get(route($document))->assertNotFound();
})->with(['privacy', 'terms']);
