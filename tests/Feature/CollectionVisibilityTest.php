<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('saves only the signed-in collectors visibility immediately and confirms the saved value', function (string $visibility) {
    $owner = socialUser('sharing_owner');
    $other = socialUser('sharing_other', 'friends');
    $preferences = $owner->fresh()->only(['name', 'handle', 'email', 'comments_enabled', 'digest_enabled', 'legacy_owner']);

    $this->actingAs($owner)->patchJson(route('collection.visibility.update'), [
        'visibility' => $visibility,
        'id' => $other->id, 'user_id' => $other->id, 'handle' => $other->handle,
        'name' => 'Tampered', 'email' => 'tampered@example.test',
        'comments_enabled' => false, 'digest_enabled' => false, 'legacy_owner' => true,
    ])->assertOk()->assertExactJson(['visibility' => $visibility]);

    $this->get(route('profile', $owner->handle))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.visibility', $visibility)->where('person.id', $owner->id));

    expect($owner->fresh()->visibility)->toBe($visibility)
        ->and($owner->fresh()->only(array_keys($preferences)))->toEqual($preferences)
        ->and($other->fresh()->visibility)->toBe('friends');
})->with(['only_me', 'friends', 'everyone']);

it('changes sharing from anywhere without leaving the current page or changing import state', function (string $path, string $component) {
    $owner = socialUser('sharing_owner');
    $owner->update(['sync_status' => 'Syncing', 'collection_imported_at' => null]);

    $this->actingAs($owner)->from($path)->patch(route('collection.visibility.update'), ['visibility' => 'friends'])
        ->assertRedirect($path)->assertSessionHasNoErrors();

    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($component)->where('auth.user.visibility', 'friends'));
    expect($owner->fresh()->visibility)->toBe('friends')
        ->and($owner->fresh()->sync_status)->toBe('Syncing')
        ->and($owner->fresh()->collection_imported_at)->toBeNull();
})->with([
    ['/friends', 'social/FeedPage'],
    ['/settings', 'social/SettingsPage'],
    ['/collections', 'social/CollectionsPage'],
]);

it('requires authentication and verification before changing sharing', function () {
    $owner = socialUser('sharing_owner');
    $this->patch(route('collection.visibility.update'), ['visibility' => 'everyone'])->assertRedirect('/login');
    $owner->update(['email_verified_at' => null]);
    $this->actingAs($owner)->patch(route('collection.visibility.update'), ['visibility' => 'everyone'])->assertRedirect('/verify-email');
    expect($owner->fresh()->visibility)->toBe('only_me');
    $this->get(route('profile', $owner->handle))->assertRedirect('/verify-email');
});

it('rejects invalid sharing values without changing the collection', function (array $payload) {
    $owner = socialUser('sharing_owner', 'friends');
    $this->actingAs($owner)->from(route('profile', $owner->handle))->followingRedirects()
        ->patch(route('collection.visibility.update'), $payload)
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('errors.collectionVisibility.visibility')->where('auth.user.visibility', 'friends'));
    expect($owner->fresh()->visibility)->toBe('friends');
})->with([
    'missing' => [[]], 'unknown' => [['visibility' => 'public']],
    'array' => [['visibility' => ['everyone']]], 'empty' => [['visibility' => '']],
]);

it('keeps the original collection public and presents a noneditable explanation', function (string $visibility) {
    $owner = User::freek();
    $owner->update(['email_verified_at' => now()]);
    $this->actingAs($owner)->patch(route('collection.visibility.update'), ['visibility' => $visibility, 'legacy_owner' => false])
        ->assertSessionHasErrors(['visibility'], null, 'collectionVisibility');
    expect($owner->fresh()->visibility)->toBe('everyone');
    $this->get(route('profile', $owner->handle))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.legacyOwner', true)->where('auth.user.visibility', 'everyone'));
})->with(['only_me', 'friends']);

it('never offers another collectors sharing controls to a visitor', function (string $viewer) {
    $owner = socialUser('sharing_owner', 'everyone');
    if ($viewer === 'member') {
        $this->actingAs(socialUser('sharing_viewer'));
    }
    $this->get(route('profile', $owner->handle))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('person.id', $owner->id)->missing('person.visibility')->missing('person.legacyOwner'));
})->with(['guest', 'member']);

it('enforces the saved choice immediately on profiles and records for every audience', function (string $visibility, string $audience, bool $visible) {
    $owner = socialUser('sharing_owner', 'everyone');
    $record = socialRecord($owner, 'Visibility contract album');
    $viewer = $audience === 'owner' ? $owner : socialUser('sharing_viewer');
    if (in_array($audience, ['friend', 'pending'])) {
        $friendship = socialFriends($owner, $viewer);
        if ($audience === 'pending') {
            $friendship->update(['accepted_at' => null]);
        }
    }
    $this->actingAs($owner)->patch(route('collection.visibility.update'), ['visibility' => $visibility])
        ->assertRedirect(route('profile', $owner->handle))->assertSessionHasNoErrors();
    $audience === 'guest' ? $this->app['auth']->forgetGuards() : $this->actingAs($viewer);
    if ($audience === 'guest') {
        $this->flushSession();
    }
    $profile = $this->get(route('profile', $owner->handle));
    $release = $this->get("/shelves/{$record->public_id}");
    if (! $visible && $audience === 'guest') {
        $profile->assertRedirect('/login')->assertDontSee('Visibility contract album');
        $release->assertRedirect('/login');

        return;
    }
    $profile->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('person.name', $owner->name)->where('person.handle', $owner->handle)->where('visible', $visible));
    if ($visible) {
        $profile->assertSee('Visibility contract album');
        $release->assertOk();

        return;
    }
    $profile->assertDontSee('Visibility contract album')->assertInertia(fn (Assert $page) => $page->where('records', null));
    $release->assertRedirect(route('profile', $owner->handle));
})->with([
    ['only_me', 'owner', true], ['only_me', 'friend', false], ['only_me', 'stranger', false], ['only_me', 'guest', false],
    ['friends', 'owner', true], ['friends', 'friend', true], ['friends', 'pending', false], ['friends', 'stranger', false], ['friends', 'guest', false],
    ['everyone', 'owner', true], ['everyone', 'friend', true], ['everyone', 'pending', true], ['everyone', 'stranger', true], ['everyone', 'guest', true],
]);
