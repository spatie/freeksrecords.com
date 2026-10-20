<?php

use App\Models\Friendship;
use App\Models\User;
use App\Support\RecordCollection;
use Inertia\Testing\AssertableInertia as Assert;

it('welcomes visitors with public collections and private response headers', function () {
    $record = socialRecord(User::freek(), 'A real public record');
    $response = $this->get(route('home'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('social/HomePage')
        ->where('collectors.0.covers.0', $record->payload['cover'])
        ->where('collectors.0.profileUrl', '/freek'));

    expect(route('home'))->toBe(url('/'))
        ->and(route('collection'))->toBe(url('/collection'))
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($response->headers->get('Cache-Control'))->not->toContain('s-maxage', 'public');
});

it('decorates the hero with independent bundled artwork without exposing private records or repeating preview covers', function () {
    $privateOwner = socialUser('hiddenowner');
    $privateRecord = socialRecord($privateOwner, 'Secret live release');
    $privateRecord->update(['payload' => [...$privateRecord->payload, 'cover' => '/images/secret-live-cover.jpg']]);
    $bundledCovers = collect(app(RecordCollection::class)->snapshot())->pluck('cover');

    foreach ([false, true] as $withPublicCollection) {
        $previewCover = null;
        if ($withPublicCollection) {
            $publicOwner = socialUser('openowner', 'everyone');
            $previewCover = socialRecord($publicOwner, 'Public preview release')->payload['cover'];
        }

        $response = $this->get('/')->assertOk()
            ->assertDontSee('hiddenowner')->assertDontSee('Secret live release')
            ->assertDontSee('/images/secret-live-cover.jpg', false);
        $covers = collect($response->inertiaProps('heroCovers'));
        expect($covers)->toHaveCount(24)
            ->and($covers->unique())->toHaveCount(24)
            ->and($response->inertiaProps('collectors'))->toHaveCount($withPublicCollection ? 1 : 0);
        foreach ($covers as $cover) {
            expect($cover)->toBeString()
                ->and($bundledCovers->contains($cover))->toBeTrue()
                ->and(is_file(public_path($cover)))->toBeTrue()
                ->and($cover)->not->toBe($previewCover);
        }

        if ($withPublicCollection) {
            $response->assertInertia(fn (Assert $page) => $page
                ->where('collectors.0.profileUrl', '/'.$publicOwner->handle)
                ->where('collectors.0.covers.0', $previewCover));
        }
    }
});

it('previews at most three nonempty public collections in handle order with six actual copies each', function () {
    socialUser('aaaempty', 'everyone');
    foreach (['delta', 'charlie', 'bravo', 'alpha'] as $handle) {
        $owner = socialUser($handle, 'everyone');
        foreach (range(1, 8) as $instance) {
            socialRecord($owner, instance: $instance);
        }
    }

    $collectors = $this->get('/')->assertOk()->inertiaProps('collectors');
    expect(array_column($collectors, 'handle'))->toBe(['alpha', 'bravo', 'charlie']);
    foreach ($collectors as $collector) {
        $owner = User::query()->findOrFail($collector['id']);
        expect($collector['recordCount'])->toBe(8)
            ->and($collector['covers'])->toBe($owner->records()->orderBy('id')->limit(6)->get()->pluck('payload.cover')->all())
            ->and(array_keys($collector))->toBe(['id', 'name', 'handle', 'profileUrl', 'recordCount', 'covers']);
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
            ->where($path === '/' ? 'collectors.0.covers.0' : 'collectors.data.0.covers.0', '/images/discovery-only-cover.jpg'));
    }
    $owner->update(['visibility' => $visibility]);
    foreach (['/', '/collections'] as $path) {
        $this->get($path)->assertOk()->assertDontSee('discoverable')->assertDontSee('Discoverable record')
            ->assertInertia(function (Assert $page) use ($path): void {
                $page->has($path === '/' ? 'collectors' : 'collectors.data', 0);
                if ($path === '/') {
                    $page->where('heroCovers', fn ($covers) => ! $covers->contains('/images/discovery-only-cover.jpg'));
                }
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

    $this->actingAs($viewer)->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('social/FeedPage')->where('collectionCount', 3)->has('incomingRequests', 1)
        ->where('incomingRequests.0.id', $requester->id)->where('auth.user.profileUrl', '/homeviewer'));
    $this->post(route('friend.action', $requester), ['action' => 'accept'])->assertRedirect();
    $this->get('/feed')->assertInertia(fn (Assert $page) => $page->has('incomingRequests', 0));
});

it('previews legal drafts locally without publishing unapproved policies', function (string $document) {
    app()->detectEnvironment(fn () => 'local');
    $this->get(route($document))->assertOk()->assertInertia(fn (Assert $page) => $page->component('social/'.ucfirst($document).'Page'));

    app()->detectEnvironment(fn () => 'production');
    $this->get(route($document))->assertNotFound();
})->with(['privacy', 'terms']);
